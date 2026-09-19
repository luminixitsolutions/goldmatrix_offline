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

$id        = isset($in['id']) ? (int) $in['id'] : 0;
$role_name = isset($in['role_name']) ? trim((string) $in['role_name']) : '';
$active    = !empty($in['is_active']);
$ledger    = !empty($in['account_ledger_assigned']);

if ($role_name === '') {
    echo json_encode(['ok' => false, 'message' => 'Role name is required.']);
    exit;
}
if (strlen($role_name) > 128) {
    echo json_encode(['ok' => false, 'message' => 'Role name is too long.']);
    exit;
}

$ia = $active ? 1 : 0;
$al = $ledger ? 1 : 0;
$rn = esc($role_name);

if ($id > 0) {
    $dup = getRecordMaster("SELECT id FROM tbl_roles WHERE role_name = '$rn' AND id != " . (int) $id . " LIMIT 1");
    if ($dup) {
        echo json_encode(['ok' => false, 'message' => 'A role with this name already exists.']);
        exit;
    }
    $sql = "UPDATE tbl_roles SET role_name = '$rn', is_active = $ia, account_ledger_assigned = $al WHERE id = " . (int) $id . " LIMIT 1";
    if (!mysqli_query($conn_master, $sql)) {
        echo json_encode(['ok' => false, 'message' => mysqli_error($conn_master)]);
        exit;
    }
    echo json_encode(['ok' => true, 'message' => 'Role updated.']);
    exit;
}

$dup = getRecordMaster("SELECT id FROM tbl_roles WHERE role_name = '$rn' LIMIT 1");
if ($dup) {
    echo json_encode(['ok' => false, 'message' => 'A role with this name already exists.']);
    exit;
}

$sql = "INSERT INTO tbl_roles (role_name, is_active, account_ledger_assigned) VALUES ('$rn', $ia, $al)";
if (!mysqli_query($conn_master, $sql)) {
    echo json_encode(['ok' => false, 'message' => mysqli_error($conn_master)]);
    exit;
}

echo json_encode(['ok' => true, 'message' => 'Role saved.']);
