<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$limit = isset($_GET['limit']) ? min(50, (int)$_GET['limit']) : 20;

if ($q === '') {
    echo json_encode(['status' => 'success', 'vouchers' => []]);
    exit;
}

$esc = mysqli_real_escape_string($conn, $q);
$vouchers = getList("
    SELECT id, voucher_no, customer_name, voucher_date, total_amount
    FROM tbl_advance_payments
    WHERE voucher_no LIKE '%$esc%' OR customer_name LIKE '%$esc%' OR ref_no LIKE '%$esc%'
    ORDER BY id DESC
    LIMIT $limit
");

echo json_encode([
    'status' => 'success',
    'vouchers' => $vouchers
]);
