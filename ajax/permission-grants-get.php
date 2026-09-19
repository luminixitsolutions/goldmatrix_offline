<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/session_login_type.php';
require_once __DIR__ . '/../includes/permissions_schema.php';
require_once __DIR__ . '/../includes/permission_definitions.php';
require_once __DIR__ . '/../includes/permission_helpers.php';
require_once __DIR__ . '/../includes/user_management_schema.php';

if (empty($_SESSION['Admin']) || !auragold_session_is_admin_login_type()) {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

auragold_ensure_user_permissions_table($conn);
auragold_ensure_user_management_columns($conn);

$userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
if ($userId <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Invalid user.']);
    exit;
}

$branchId = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : 0;
if ($branchId < 0) {
    $branchId = 0;
}

$forcedBranch = auragold_um_permission_locked_branch_id($conn_master);
if ($forcedBranch > 0) {
    $branchId = $forcedBranch;
}

$u = getRecord('SELECT * FROM tbl_users WHERE id = ' . $userId . ' LIMIT 1');
if (!$u || !auragold_um_user_row_allowed_for_permission_page($conn_master, $u)) {
    echo json_encode(['ok' => false, 'message' => 'User not found or not available in your branch context.']);
    exit;
}

$defaults = auragold_permission_all_keys_flat();
$stored   = auragold_permission_grants_map_for_user_branch($conn, $userId, $branchId);
foreach ($defaults as $k => $_) {
    $defaults[$k] = array_key_exists($k, $stored) ? (int) $stored[$k] : 0;
}

echo json_encode(['ok' => true, 'grants' => $defaults, 'branch_id' => $branchId]);
