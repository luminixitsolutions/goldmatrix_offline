<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$return_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($return_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid return ID']);
    exit;
}

// Get return details
$return = getRecord("SELECT * FROM tbl_sale_returns WHERE id = $return_id");

if (!$return) {
    echo json_encode(['status' => 'error', 'message' => 'Return not found']);
    exit;
}

// Get return items
$items = getList("SELECT * FROM tbl_sale_return_items WHERE return_id = $return_id ORDER BY id ASC");

// Get return payments
$payments = getList("SELECT * FROM tbl_sale_return_payments WHERE return_id = $return_id ORDER BY id ASC");
require_once __DIR__ . '/../includes/auragold_payment_details_merge.php';
auragold_merge_payment_details_into_payments($payments);

// Map return to "order" for populateOrderForm (order_no, order_date, etc.)
$order = $return;
$order['order_no'] = $return['return_no'] ?? '';
$order['return_no'] = $return['return_no'] ?? '';
$order['invoice_no'] = $return['return_no'] ?? '';
$order['order_date'] = $return['return_date'] ?? '';
$order['invoice_date'] = $return['return_date'] ?? '';
$order['order_id'] = $return['id'];
$order['against_of'] = $return['against_of'] ?? '';
$order['against_type'] = $return['against_type'] ?? $return['against_of'] ?? '';
$order['against_id'] = isset($return['against_id']) ? (int)$return['against_id'] : 0;
$order['payment_comments'] = $return['payment_comments'] ?? '[]';

echo json_encode([
    'status' => 'success',
    'return' => $return,
    'order' => $order,
    'items' => $items,
    'payments' => $payments
]);
?>
