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

$id = isset($in['id']) ? (int) $in['id'] : 0;
if ($id <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Invalid user.']);
    exit;
}

$src = isset($_SESSION['login_source']) ? (string) $_SESSION['login_source'] : '';
$sid = (int) ($_SESSION['user_id'] ?? 0);
if ($src === 'user' && $sid === $id) {
    echo json_encode(['ok' => false, 'message' => 'You cannot delete your own account.']);
    exit;
}

$row = getRecord('SELECT * FROM tbl_users WHERE id = ' . (int) $id . ' LIMIT 1');
if (!$row || !auragold_um_user_row_in_management_scope($conn_master, $row)) {
    echo json_encode(['ok' => false, 'message' => 'You cannot delete this user from your branch context.']);
    exit;
}

$id_esc = (int) $id;
$res      = mysqli_query($conn, "DELETE FROM tbl_users WHERE id = $id_esc LIMIT 1");
if (!$res || mysqli_affected_rows($conn) === 0) {
    echo json_encode(['ok' => false, 'message' => 'Could not delete user.']);
    exit;
}

echo json_encode(['ok' => true, 'message' => 'User deleted.']);
