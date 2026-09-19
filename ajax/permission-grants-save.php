<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/session_login_type.php';
require_once __DIR__ . '/../includes/permissions_schema.php';
require_once __DIR__ . '/../includes/permission_definitions.php';
require_once __DIR__ . '/../includes/user_management_schema.php';

if (empty($_SESSION['Admin']) || !auragold_session_is_admin_login_type()) {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

// Release session lock before many DB writes (other tabs / requests can proceed).
if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

auragold_ensure_user_permissions_table($conn);
auragold_ensure_user_management_columns($conn);

$raw = file_get_contents('php://input');
$in  = json_decode($raw, true);
if (!is_array($in)) {
    $in = $_POST;
}

$userId = isset($in['user_id']) ? (int) $in['user_id'] : 0;
if ($userId <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Invalid user.']);
    exit;
}

$u = getRecord('SELECT * FROM tbl_users WHERE id = ' . $userId . ' LIMIT 1');
if (!$u || !auragold_um_user_row_allowed_for_permission_page($conn_master, $u)) {
    echo json_encode(['ok' => false, 'message' => 'User not found or not available in your branch context.']);
    exit;
}

$branchId = isset($in['branch_id']) ? (int) $in['branch_id'] : 0;
if ($branchId < 0) {
    $branchId = 0;
}

$forcedBranch = auragold_um_permission_locked_branch_id($conn_master);
if ($forcedBranch > 0) {
    $branchId = $forcedBranch;
}

if (!$conn || !($conn instanceof mysqli)) {
    echo json_encode(['ok' => false, 'message' => 'Database not available.']);
    exit;
}

$allowed = auragold_permission_all_keys_flat();
$grants  = isset($in['grants']) && is_array($in['grants']) ? $in['grants'] : [];

// Nav checks require the parent "{module}.menu" grant before any page is visible,
// so a granted sub-menu action must imply menu access (otherwise the grant is dead).
foreach (auragold_permission_tree() as $pmMod) {
    $pmMenuKey = $pmMod['key'] . '.menu';
    if (!empty($grants[$pmMenuKey]) && filter_var($grants[$pmMenuKey], FILTER_VALIDATE_BOOLEAN)) {
        continue;
    }
    foreach ($pmMod['pages'] as $pmPage) {
        $pmNs = auragold_permission_grant_namespace_for_page($pmMod, $pmPage);
        foreach ($pmPage['actions'] as $pmAct) {
            $pmActKey = $pmNs . '.' . $pmPage['key'] . '.' . $pmAct;
            if (!empty($grants[$pmActKey]) && filter_var($grants[$pmActKey], FILTER_VALIDATE_BOOLEAN)) {
                $grants[$pmMenuKey] = 1;
                continue 3;
            }
        }
    }
}

mysqli_begin_transaction($conn);

$del = mysqli_query(
    $conn,
    'DELETE FROM tbl_user_permission_grants WHERE user_id = ' . (int) $userId . ' AND branch_id = ' . (int) $branchId
);
if (!$del) {
    mysqli_rollback($conn);
    echo json_encode(['ok' => false, 'message' => 'Could not clear old permissions: ' . mysqli_error($conn)]);
    exit;
}

$stmt = mysqli_prepare(
    $conn,
    'INSERT INTO tbl_user_permission_grants (user_id, branch_id, perm_key, granted) VALUES (?, ?, ?, ?)'
);
if (!$stmt) {
    mysqli_rollback($conn);
    echo json_encode(['ok' => false, 'message' => 'Could not prepare save: ' . mysqli_error($conn)]);
    exit;
}

$uid = (int) $userId;
$bid = (int) $branchId;
$pkey = '';
$gval = 0;
mysqli_stmt_bind_param($stmt, 'iisi', $uid, $bid, $pkey, $gval);

foreach ($allowed as $key => $_) {
    $g = isset($grants[$key]) ? (filter_var($grants[$key], FILTER_VALIDATE_BOOLEAN) ? 1 : 0) : 0;
    $pkey = (string) $key;
    $gval = (int) $g;
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        mysqli_rollback($conn);
        echo json_encode(['ok' => false, 'message' => 'Save failed: ' . $err]);
        exit;
    }
}

mysqli_stmt_close($stmt);
mysqli_commit($conn);

require_once __DIR__ . '/../includes/permission_helpers.php';
if (function_exists('auragold_permission_invalidate_session_cache')) {
    auragold_permission_invalidate_session_cache($userId);
}

echo json_encode(['ok' => true, 'message' => 'Permission saved successfully']);
