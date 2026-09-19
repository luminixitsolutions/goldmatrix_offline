<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/session_login_type.php';
require_once dirname(__DIR__) . '/includes/branch_working_context.php';
require_once dirname(__DIR__) . '/includes/branch_database_provision.php';

if (empty($_SESSION['Admin'])) {
    echo json_encode(['ok' => false, 'message' => 'Not logged in']);
    exit;
}
if (!auragold_session_is_admin_login_type()) {
    echo json_encode(['ok' => false, 'message' => 'Only a main-branch user can provision databases.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'message' => 'Invalid request']);
    exit;
}

$branch_id = isset($_POST['branch_id']) ? (int) $_POST['branch_id'] : 0;
$copy_data = !empty($_POST['copy_data']);

if ($branch_id <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Invalid branch']);
    exit;
}

$row = getRecordMaster('SELECT * FROM tbl_branches WHERE id = ' . $branch_id . ' LIMIT 1');
if (!$row) {
    echo json_encode(['ok' => false, 'message' => 'Branch not found']);
    exit;
}

$db_name = trim((string) ($row['db_name'] ?? ''));
if ($db_name === '') {
    echo json_encode(['ok' => false, 'message' => 'Set db_name on this branch row in tbl_branches first.']);
    exit;
}

$scope_main  = auragold_session_restrict_sub_branch_ops_main_id();
$row_main_id = (int) ($row['main_branch_id'] ?? 0);
$row_id      = (int) ($row['id'] ?? 0);

if ($scope_main > 0) {
    if ($row_main_id === 0) {
        if ($row_id !== $scope_main) {
            echo json_encode(['ok' => false, 'message' => 'Not allowed for this branch.']);
            exit;
        }
    } elseif ($row_main_id !== $scope_main) {
        echo json_encode(['ok' => false, 'message' => 'You can only provision sub-branches under your main branch.']);
        exit;
    }
}

if (strcasecmp($db_name, DB_NAME) === 0) {
    echo json_encode(['ok' => false, 'message' => 'db_name must differ from the main application database.']);
    exit;
}

@set_time_limit(600);

$schemaSrc = function_exists('auragold_branch_schema_clone_source_db')
    ? auragold_branch_schema_clone_source_db()
    : (string) (defined('DB_NAME') ? DB_NAME : '');
if ($schemaSrc === '' && defined('DB_NAME')) {
    $schemaSrc = (string) DB_NAME;
}
$result = auragold_provision_branch_database(
    $db_name,
    $schemaSrc,
    $branch_id,
    [
        'copy_all_data'     => $copy_data,
        'minimal_schema'    => !$copy_data && defined('AURAGOLD_BRANCH_MINIMAL_SCHEMA') && AURAGOLD_BRANCH_MINIMAL_SCHEMA,
        'branch_mysql_user' => trim((string) ($row['db_users'] ?? $row['db_user'] ?? '')),
        'branch_mysql_pass' => (string) ($row['db_password'] ?? $row['db_pass'] ?? ''),
    ]
);
echo json_encode($result);
exit;
