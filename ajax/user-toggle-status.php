<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/session_login_type.php';
require_once __DIR__ . '/../includes/user_management_schema.php';

if (empty($_SESSION['Admin']) || !auragold_session_is_admin_login_type()) {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

$raw = file_get_contents('php://input');
$in  = json_decode($raw, true);
if (!is_array($in)) {
    $in = $_POST;
}

$id     = isset($in['id']) ? (int) $in['id'] : 0;
$active = !empty($in['active']);
if ($id <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Invalid user.']);
    exit;
}

$src = isset($_SESSION['login_source']) ? (string) $_SESSION['login_source'] : '';
$sid = (int) ($_SESSION['user_id'] ?? 0);
if ($src === 'user' && $sid === $id && !$active) {
    echo json_encode(['ok' => false, 'message' => 'You cannot deactivate your own account.']);
    exit;
}

$row = getRecord('SELECT * FROM tbl_users WHERE id = ' . (int) $id . ' LIMIT 1');
if (!$row || !auragold_um_user_row_in_management_scope($conn_master, $row)) {
    echo json_encode(['ok' => false, 'message' => 'You cannot change this user from your branch context.']);
    exit;
}

$st     = $active ? '1' : '0';
$st_esc = esc($st);
$id_esc = (int) $id;

$sql = "UPDATE tbl_users SET Status = '$st_esc', ModifiedBy = " . (int) ($_SESSION['user_id'] ?? 0) . " WHERE id = $id_esc LIMIT 1";
if (!mysqli_query($conn, $sql)) {
    echo json_encode(['ok' => false, 'message' => mysqli_error($conn)]);
    exit;
}

echo json_encode(['ok' => true]);
