<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$char_id = isset($input['characteristic_id']) ? (int)$input['characteristic_id'] : 0;

if ($char_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid characteristic ID']);
    exit;
}

$row = getRecord("SELECT id, barcode_prefix, barcode_digits, barcode FROM tbl_product_characteristics WHERE id = $char_id AND status = 1");
if (!$row) {
    echo json_encode(['status' => 'error', 'message' => 'Characteristic not found']);
    exit;
}

$prefix = isset($row['barcode_prefix']) && trim($row['barcode_prefix']) !== '' ? trim($row['barcode_prefix']) : 'RN';
$digit = isset($row['barcode_digits']) && (int)$row['barcode_digits'] > 0 ? (int)$row['barcode_digits'] : 5;

try {
    $barcode = generateBarcode($conn, $prefix, $digit);
    $barcode_esc = mysqli_real_escape_string($conn, $barcode);
    $ok = mysqli_query($conn, "UPDATE tbl_product_characteristics SET barcode = '$barcode_esc' WHERE id = $char_id");
    if ($ok) {
        echo json_encode(['status' => 'success', 'barcode' => $barcode]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update barcode']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
