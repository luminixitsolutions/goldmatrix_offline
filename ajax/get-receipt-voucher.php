<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$voucher_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($voucher_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid voucher ID']);
    exit;
}

// Get voucher details from tbl_receipt_vouchers
$voucher = getRecord("SELECT * FROM tbl_receipt_vouchers WHERE id = $voucher_id");

if (!$voucher) {
    echo json_encode(['status' => 'error', 'message' => 'Voucher not found']);
    exit;
}

// Get voucher items
$items = getList("SELECT * FROM tbl_receipt_voucher_items WHERE voucher_id = $voucher_id ORDER BY id ASC");

$voucher['items'] = $items;

echo json_encode([
    'status' => 'success',
    'voucher' => $voucher
]);
?>
