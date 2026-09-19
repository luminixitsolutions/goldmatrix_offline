<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

if ($order_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid order ID']);
    exit;
}

// Get order details from tbl_repair_orders
$order = getRecord("SELECT * FROM tbl_repair_orders WHERE id = $order_id");

if (!$order) {
    echo json_encode(['status' => 'error', 'message' => 'Order not found']);
    exit;
}

// Ensure order_no, order_date, order_id for form compatibility (same as get-sale-order.php)
$order['order_no'] = $order['order_no'] ?? '';
$order['order_date'] = $order['order_date'] ?? '';
$order['order_id'] = $order['id'] ?? 0;

// Get order items from tbl_repair_order_items
$items = getList("SELECT * FROM tbl_repair_order_items WHERE order_id = $order_id ORDER BY id ASC");

// Map item column names for compatibility with populateOrderForm / addProductRowToSelectionTable
foreach ($items as &$item) {
    $item['barcode_no'] = $item['barcode_no'] ?? $item['barcode'] ?? '';
    $item['product_characteristic_id'] = $item['product_characteristic_id'] ?? $item['characteristic_id'] ?? null;
    $item['tax_amount'] = $item['tax_amount'] ?? $item['tax'] ?? 0;
}

// Get order payments from tbl_repair_order_payments
$payments = getList("SELECT * FROM tbl_repair_order_payments WHERE order_id = $order_id ORDER BY id ASC");

echo json_encode([
    'status' => 'success',
    'order' => $order,
    'items' => $items,
    'payments' => $payments
]);
?>
