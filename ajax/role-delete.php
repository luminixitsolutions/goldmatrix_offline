<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/session_login_type.php';
require_once __DIR__ . '/../includes/roles_schema.php';

if (empty($_SESSION['Admin']) || !auragold_session_is_admin_login_type()) {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

auragold_ensure_roles_table($conn_master);

$raw = file_get_contents('php://input');
$in  = json_decode($raw, true);
if (!is_array($in)) {
    $in = $_POST;
}

$id = isset($in['id']) ? (int) $in['id'] : 0;
if ($id <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Invalid role.']);
    exit;
}

$id_esc = (int) $id;
$res    = mysqli_query($conn_master, "DELETE FROM tbl_roles WHERE id = $id_esc LIMIT 1");
if (!$res || mysqli_affected_rows($conn_master) === 0) {
    echo json_encode(['ok' => false, 'message' => 'Could not delete role.']);
    exit;
}

echo json_encode(['ok' => true, 'message' => 'Role deleted.']);
