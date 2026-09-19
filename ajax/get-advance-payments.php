<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

// Get vouchers from tbl_advance_payments
$vouchers = getList("
    SELECT 
        ap.*,
        (SELECT COUNT(*) FROM tbl_advance_payment_items WHERE voucher_id = ap.id) as item_count
    FROM tbl_advance_payments ap 
    ORDER BY ap.id DESC 
    LIMIT $limit OFFSET $offset
");

// Get items for each voucher
foreach ($vouchers as &$voucher) {
    $items = getList("SELECT * FROM tbl_advance_payment_items WHERE voucher_id = {$voucher['id']} ORDER BY id ASC");
    $voucher['items'] = $items;
}

echo json_encode([
    'status' => 'success',
    'vouchers' => $vouchers,
    'count' => count($vouchers)
]);
?>
