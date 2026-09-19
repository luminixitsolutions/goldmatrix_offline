<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$voucher_no = isset($_GET['voucher_no']) ? trim((string)$_GET['voucher_no']) : '';
if ($voucher_no === '') {
    echo json_encode(['status' => 'error', 'message' => 'voucher_no is required']);
    exit;
}

$voucher_no_esc = mysqli_real_escape_string($conn, $voucher_no);
$row = getRecord("SELECT id FROM tbl_payment_vouchers WHERE voucher_no = '$voucher_no_esc' LIMIT 1");
if ($row && !empty($row['id'])) {
    echo json_encode(['status' => 'success', 'voucher_id' => (int)$row['id']]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Payment voucher not found']);
