<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;

try {
    // Get all vouchers
    $vouchers = getList("SELECT * FROM tbl_customer_advance_vouchers ORDER BY id DESC LIMIT $limit");
    
    // Get items for each voucher
    foreach ($vouchers as &$voucher) {
        $items = getList("SELECT * FROM tbl_customer_advance_voucher_items WHERE voucher_id = {$voucher['id']} AND status = 1");
        
        // Calculate amount for each item
        foreach ($items as &$item) {
            $item['amount'] = $item['purity_wt'] ? $item['purity_wt'] : ($item['amount'] ? $item['amount'] : 0);
        }
        
        $voucher['items'] = $items;
    }
    
    echo json_encode([
        'status' => 'success',
        'vouchers' => $vouchers
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
