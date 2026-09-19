<?php
/**
 * Permanently delete a branch: sub-branch (admin + scope rules) or main branch (superadmin only, cascades subs).
 */
require_once dirname(__DIR__) . '/includes/session_init.php';
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/session_login_type.php';
require_once dirname(__DIR__) . '/includes/branch_working_context.php';
require_once dirname(__DIR__) . '/includes/ensure_tbl_settings.php';
require_once dirname(__DIR__) . '/includes/branch_delete_cascade.php';
require_once dirname(__DIR__) . '/includes/branch_db_auto_credentials.php';

@ini_set('display_errors', '0');
@set_time_limit(600);
@ignore_user_abort(true);

header('Content-Type: application/json; charset=utf-8');

function auragold_delete_branch_json_response(array $payload, int $httpCode = 200): void {
    if (ob_get_level() > 0) {
        ob_clean();
    }
    if ($httpCode !== 200) {
        http_response_code($httpCode);
    }
    echo json_encode($payload);
    exit;
}

try {
    if (empty($_SESSION['Admin'])) {
        auragold_delete_branch_json_response(['ok' => false, 'message' => 'Not logged in'], 401);
    }

    if (!auragold_session_is_admin_login_type()) {
        auragold_delete_branch_json_response(['ok' => false, 'message' => 'Only admin users can delete branches.']);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        auragold_delete_branch_json_response(['ok' => false, 'message' => 'Invalid request method']);
    }

    if (empty($conn_master) || !($conn_master instanceof mysqli)) {
        auragold_delete_branch_json_response(['ok' => false, 'message' => 'Registry database connection is not available.']);
    }

    $password = isset($_REQUEST['password']) ? trim((string) $_REQUEST['password']) : '';
    if ($password === '') {
        auragold_delete_branch_json_response(['ok' => false, 'message' => 'Enter the master password to confirm deletion.']);
    }

    if (!auragold_ensure_tbl_settings_branch_password($conn_master)) {
        auragold_delete_branch_json_response(['ok' => false, 'message' => 'Could not read branch security settings.']);
    }

    $srow = getRecordMaster('SELECT branch_password_hash FROM tbl_settings ORDER BY id ASC LIMIT 1');
    $hash = $srow && isset($srow['branch_password_hash']) ? trim((string) $srow['branch_password_hash']) : '';
    if ($hash === '' || !password_verify($password, $hash)) {
        auragold_delete_branch_json_response(['ok' => false, 'message' => 'Invalid password']);
    }

    $branchId = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;
    if ($branchId <= 0) {
        auragold_delete_branch_json_response(['ok' => false, 'message' => 'Invalid branch']);
    }

    $target = getRecordMaster('SELECT id, name, main_branch_id, db_name FROM tbl_branches WHERE id = ' . $branchId . ' LIMIT 1');
    if (!$target) {
        auragold_delete_branch_json_response(['ok' => false, 'message' => 'Branch not found']);
    }

    $isMain = (int) ($target['main_branch_id'] ?? 0) === 0;

    if ($isMain) {
        if (!auragold_session_is_superadmin()) {
            auragold_delete_branch_json_response(['ok' => false, 'message' => 'Only a superadmin can delete a main branch.']);
        }
        $result = auragold_branch_delete_main_branch_cascade($conn_master, $conn, $branchId);
        if (empty($result['ok'])) {
            auragold_delete_branch_json_response([
                'ok'      => false,
                'message' => $result['message'] ?? 'Could not delete main branch.',
                'detail'  => $result['subs'] ?? null,
            ]);
        }
        $payload = [
            'ok'            => true,
            'message'       => $result['message'] ?? 'Main branch deleted.',
            'users_updated' => (int) ($result['users_updated'] ?? 0),
            'data_deleted'  => $result['data_deleted'] ?? null,
            'db_drop'       => $result['db_drop'] ?? null,
            'subs_deleted'  => $result['subs'] ?? [],
        ];
        $dbDrop = $result['db_drop'] ?? null;
        if (is_array($dbDrop) && empty($dbDrop['ok'])) {
            $payload['warning'] = 'Main branch removed, but the dedicated database could not be dropped: ' . ($dbDrop['message'] ?? 'unknown error');
        }
        auragold_delete_branch_json_response($payload);
    }

    $scope_main = auragold_session_restrict_sub_branch_ops_main_id();
    if ($scope_main > 0 && (int) $target['main_branch_id'] !== $scope_main) {
        auragold_delete_branch_json_response(['ok' => false, 'message' => 'You can only delete sub-branches under your main branch.']);
    }

    $result = auragold_branch_delete_sub_branch_core($conn_master, $conn, $branchId);
    if (empty($result['ok'])) {
        auragold_delete_branch_json_response([
            'ok'      => false,
            'message' => $result['message'] ?? 'Could not delete branch.',
            'detail'  => $result['appReport'] ?? null,
        ]);
    }

    $appReport = $result['appReport'] ?? ['tables' => [], 'errors' => []];
    $dbDrop = $result['db_drop'] ?? null;

    $payload = [
        'ok'            => true,
        'message'       => $result['message'] ?? 'Branch permanently deleted.',
        'users_updated' => (int) ($result['users_updated'] ?? 0),
        'data_deleted'  => $appReport,
        'db_drop'       => $dbDrop,
    ];
    if (is_array($dbDrop) && empty($dbDrop['ok'])) {
        $payload['warning'] = 'Branch record removed, but the dedicated database could not be dropped: ' . ($dbDrop['message'] ?? 'unknown error');
    }

    auragold_delete_branch_json_response($payload);
} catch (Throwable $e) {
    error_log('AuraGold delete_branch.php: ' . $e->getMessage());
    auragold_delete_branch_json_response([
        'ok'      => false,
        'message' => 'Server error while deleting branch: ' . $e->getMessage(),
    ], 500);
}
