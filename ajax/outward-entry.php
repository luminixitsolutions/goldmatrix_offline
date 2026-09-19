<?php
session_start();
require_once __DIR__ . '/../config.php';
$fn = __DIR__ . '/../includes/outward-entry-functions.php';
if (is_file($fn)) require_once $fn;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$selected_barcodes = isset($_POST['selected_barcodes']) && is_array($_POST['selected_barcodes']) ? $_POST['selected_barcodes'] : [];
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$opening_barcode = isset($_POST['opening_barcode']) ? trim($_POST['opening_barcode']) : '';

$selected_barcodes = array_filter(array_map('trim', $selected_barcodes));
if (empty($selected_barcodes) || $product_id <= 0 || $opening_barcode === '') {
    echo json_encode(['success' => false, 'message' => 'Missing selected_barcodes, product_id or opening_barcode']);
    exit;
}

mysqli_begin_transaction($conn);
try {
    $result = createOutwardEntry($conn, $product_id, $opening_barcode, $selected_barcodes);
    mysqli_commit($conn);
    echo json_encode(['success' => true, 'message' => 'Outward entry created', 'outward_id' => $result['outward_id'] ?? null]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
