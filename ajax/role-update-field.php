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

$id    = isset($in['id']) ? (int) $in['id'] : 0;
$field = isset($in['field']) ? trim((string) $in['field']) : '';
$val   = filter_var($in['value'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;

if ($id <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Invalid role.']);
    exit;
}

$col = '';
if ($field === 'is_active') {
    $col = 'is_active';
} elseif ($field === 'account_ledger_assigned') {
    $col = 'account_ledger_assigned';
} else {
    echo json_encode(['ok' => false, 'message' => 'Invalid field.']);
    exit;
}

$sql = 'UPDATE tbl_roles SET `' . $col . '` = ' . $val . ' WHERE id = ' . (int) $id . ' LIMIT 1';
if (!mysqli_query($conn_master, $sql)) {
    echo json_encode(['ok' => false, 'message' => mysqli_error($conn_master)]);
    exit;
}

echo json_encode(['ok' => true]);
