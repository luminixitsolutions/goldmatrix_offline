<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid voucher ID', 'voucher' => null]);
    exit;
}

$voucher = getRecord("SELECT * FROM tbl_payment_vouchers WHERE id = $id");
if (!$voucher) {
    echo json_encode(['status' => 'error', 'message' => 'Voucher not found', 'voucher' => null]);
    exit;
}

$items = getList("SELECT * FROM tbl_payment_voucher_items WHERE voucher_id = $id ORDER BY id ASC");
$voucher['items'] = $items;

echo json_encode([
    'status' => 'success',
    'voucher' => $voucher
]);
