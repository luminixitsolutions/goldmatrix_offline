<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/session_login_type.php';
require_once __DIR__ . '/../includes/whitelist_schema.php';

if (empty($_SESSION['Admin']) || !auragold_session_is_admin_login_type()) {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

auragold_ensure_whitelist_tables($conn_master);

$raw = file_get_contents('php://input');
$in  = json_decode($raw, true);
if (!is_array($in)) {
    $in = $_POST;
}

$id     = isset($in['id']) ? (int) $in['id'] : 0;
$entity = isset($in['entity_value']) ? trim((string) $in['entity_value']) : '';
$type   = isset($in['entry_type']) ? trim((string) $in['entry_type']) : 'IP';
$notes  = isset($in['notes']) ? trim((string) $in['notes']) : '';
$active = !empty($in['is_active']);

if ($entity === '') {
    echo json_encode(['ok' => false, 'message' => 'Entity value is required.']);
    exit;
}
if (strlen($entity) > 255) {
    echo json_encode(['ok' => false, 'message' => 'Entity value is too long.']);
    exit;
}
if ($type === '') {
    $type = 'IP';
}
if (strlen($type) > 32) {
    echo json_encode(['ok' => false, 'message' => 'Invalid type.']);
    exit;
}

$ia = $active ? 1 : 0;
$ev = esc($entity);
$tp = esc($type);
$nt = esc($notes);

if ($id > 0) {
    $dup = getRecordMaster("SELECT id FROM tbl_ip_whitelist WHERE entity_value = '$ev' AND entry_type = '$tp' AND id != " . (int) $id . " LIMIT 1");
    if ($dup) {
        echo json_encode(['ok' => false, 'message' => 'This entry already exists.']);
        exit;
    }
    $sql = "UPDATE tbl_ip_whitelist SET entity_value = '$ev', entry_type = '$tp', notes = '$nt', is_active = $ia WHERE id = " . (int) $id . " LIMIT 1";
    if (!mysqli_query($conn_master, $sql)) {
        echo json_encode(['ok' => false, 'message' => mysqli_error($conn_master)]);
        exit;
    }
    echo json_encode(['ok' => true, 'message' => 'Entry updated.']);
    exit;
}

$dup = getRecordMaster("SELECT id FROM tbl_ip_whitelist WHERE entity_value = '$ev' AND entry_type = '$tp' LIMIT 1");
if ($dup) {
    echo json_encode(['ok' => false, 'message' => 'This entry already exists.']);
    exit;
}

$sql = "INSERT INTO tbl_ip_whitelist (entity_value, entry_type, notes, is_active) VALUES ('$ev', '$tp', '$nt', $ia)";
if (!mysqli_query($conn_master, $sql)) {
    echo json_encode(['ok' => false, 'message' => mysqli_error($conn_master)]);
    exit;
}

echo json_encode(['ok' => true, 'message' => 'Entry saved.']);
