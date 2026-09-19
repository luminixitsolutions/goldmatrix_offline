<?php
/**
 * Create or repair a branch’s dedicated database: clone or backfill all tables from the registry schema, copy masters
 * when tables are empty, re-sync tbl_branches family, re-seed bill series. If the database already has tables, the
 * same action runs a backfill to add any missing tables from the registry.
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/session_login_type.php';
require_once dirname(__DIR__) . '/includes/branch_working_context.php';
require_once dirname(__DIR__) . '/includes/ensure_tbl_settings.php';
require_once dirname(__DIR__) . '/includes/branch_create_db_after_save.php';
require_once dirname(__DIR__) . '/includes/branch_database_provision.php';

header('Content-Type: application/json; charset=utf-8');

if (function_exists('set_time_limit')) {
    @set_time_limit(600);
}
@ini_set('max_execution_time', '600');
@ini_set('memory_limit', '512M');

if (!function_exists('auragold_repair_branch_json_out')) {
    /**
     * @param array<string,mixed> $payload
     */
    function auragold_repair_branch_json_out(array $payload): void {
        $flags = JSON_UNESCAPED_UNICODE;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }
        echo json_encode($payload, $flags) ?: '{"ok":false,"message":"Encode error"}';
    }
}

if (empty($_SESSION['Admin'])) {
    auragold_repair_branch_json_out(['ok' => false, 'message' => 'Not logged in']);
    exit;
}

if (!auragold_session_is_admin_login_type()) {
    auragold_repair_branch_json_out(['ok' => false, 'message' => 'Only admin users can repair branch databases.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    auragold_repair_branch_json_out(['ok' => false, 'message' => 'Invalid request method']);
    exit;
}

$password = isset($_REQUEST['password']) ? trim((string) $_REQUEST['password']) : '';
if ($password === '') {
    auragold_repair_branch_json_out(['ok' => false, 'message' => 'Enter the master password used when adding a branch.']);
    exit;
}

if (!auragold_ensure_tbl_settings_branch_password($conn_master)) {
    auragold_repair_branch_json_out(['ok' => false, 'message' => 'Could not read branch security settings.']);
    exit;
}

$srow = getRecordMaster('SELECT branch_password_hash FROM tbl_settings ORDER BY id ASC LIMIT 1');
$hash = $srow && isset($srow['branch_password_hash']) ? trim((string) $srow['branch_password_hash']) : '';
if ($hash === '' || !password_verify($password, $hash)) {
    auragold_repair_branch_json_out(['ok' => false, 'message' => 'Invalid password']);
    exit;
}

$branchId = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;
if ($branchId <= 0) {
    auragold_repair_branch_json_out(['ok' => false, 'message' => 'Invalid branch']);
    exit;
}

$target = getRecordMaster(
    'SELECT id, name, main_branch_id, db_name, db_users, db_password FROM tbl_branches WHERE id = ' . $branchId . ' LIMIT 1'
);
if (!$target) {
    auragold_repair_branch_json_out(['ok' => false, 'message' => 'Branch not found']);
    exit;
}

$dbName  = trim((string) ($target['db_name'] ?? ''));
$dbUsers = trim((string) ($target['db_users'] ?? ''));
$dbPass  = (string) ($target['db_password'] ?? '');

$mainBranchId = (int) ($target['main_branch_id'] ?? 0);
$regName      = function_exists('auragold_branch_schema_clone_source_db')
    ? (string) auragold_branch_schema_clone_source_db()
    : (defined('DB_NAME') ? (string) DB_NAME : '');
if ($regName === '' && defined('DB_NAME')) {
    $regName = (string) DB_NAME;
}
if ($mainBranchId === 0 && ($dbName === '' || $regName === '' || strcasecmp($dbName, $regName) === 0)) {
    auragold_repair_branch_json_out([
        'ok'      => false,
        'message' => 'Use this only for a branch with its own MySQL database name in tbl_branches (not the same as the main registry).',
    ]);
    exit;
}

$scope_main = auragold_session_restrict_sub_branch_ops_main_id();
if ($scope_main > 0 && (int) $target['main_branch_id'] !== $scope_main) {
    auragold_repair_branch_json_out(['ok' => false, 'message' => 'You can only repair sub-branches under your main branch.']);
    exit;
}

if ($dbName === '') {
    auragold_repair_branch_json_out([
        'ok'      => false,
        'message' => 'This branch has no dedicated database name in tbl_branches. Re-create the branch or set db_name.',
    ]);
    exit;
}

try {
    $result = auragold_after_branch_insert_create_db_and_schema(
        $conn_master,
        $dbName,
        $dbUsers,
        $dbPass,
        $branchId
    );

    $backfill = null;
    if (!empty($result['ok']) && $regName !== '' && $dbName !== '' && strcasecmp($dbName, $regName) !== 0) {
        if (!empty($result['skipped'])) {
            $backfill = auragold_branch_backfill_from_registry(
                $dbName,
                $regName,
                $branchId,
                ['seed_masters' => true]
            );
            if (!empty($backfill['ok'])) {
                $result['message']  = trim((string) ($result['message'] ?? '') . ' ' . ($backfill['message'] ?? ''));
                $result['backfill'] = $backfill;
            } else {
                $result['ok']      = false;
                $result['message'] = (string) ($backfill['message'] ?? 'Backfill failed after skipped provision.');
                $result['backfill'] = $backfill;
            }
        }
    }

    auragold_repair_branch_json_out([
        'ok'          => !empty($result['ok']),
        'message'     => (string) ($result['message'] ?? ''),
        'provisioned' => !empty($result['provisioned']) || !empty($backfill['ok']),
        'skipped'     => !empty($result['skipped']) && empty($backfill),
        'branch_id'   => $branchId,
        'db_name'     => $dbName,
        'backfill'    => is_array($backfill) ? [
            'ok'            => !empty($backfill['ok']),
            'created'       => (int) ($backfill['created'] ?? 0),
            'master_seeded' => (int) ($backfill['master_seeded'] ?? 0),
            'message'       => (string) ($backfill['message'] ?? ''),
        ] : null,
    ]);
} catch (Throwable $e) {
    error_log('AuraGold repair_branch_database: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    auragold_repair_branch_json_out(['ok' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
