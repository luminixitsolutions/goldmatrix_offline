<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/session_login_type.php';
require_once __DIR__ . '/../includes/blocklist_schema.php';

if (empty($_SESSION['Admin']) || !auragold_session_is_admin_login_type()) {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

auragold_ensure_blocklist_table($conn_master);

$raw = file_get_contents('php://input');
$in  = json_decode($raw, true);
if (!is_array($in)) {
    $in = $_POST;
}

$id = isset($in['id']) ? (int) $in['id'] : 0;
if ($id <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Invalid entry.']);
    exit;
}

if (!mysqli_query($conn_master, 'DELETE FROM tbl_login_blocklist WHERE id = ' . (int) $id . ' LIMIT 1')) {
    echo json_encode(['ok' => false, 'message' => mysqli_error($conn_master)]);
    exit;
}

echo json_encode(['ok' => true, 'message' => 'Removed from blocklist.']);
