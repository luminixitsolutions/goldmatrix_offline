<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

$voucher_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($voucher_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid voucher ID']);
    exit;
}

try {
    // Get voucher
    $voucher = getRecord("SELECT * FROM tbl_customer_advance_vouchers WHERE id = $voucher_id");
    
    if (!$voucher) {
        echo json_encode(['status' => 'error', 'message' => 'Voucher not found']);
        exit;
    }
    
    // Get voucher items
    $items = getList("SELECT * FROM tbl_customer_advance_voucher_items WHERE voucher_id = $voucher_id AND status = 1");
    
    // Calculate amount for each item
    foreach ($items as &$item) {
        $item['amount'] = $item['purity_wt'] ? $item['purity_wt'] : 0;
    }
    
    $voucher['items'] = $items;
    
    echo json_encode([
        'status' => 'success',
        'voucher' => $voucher
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
