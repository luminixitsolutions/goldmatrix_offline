<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$quotation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($quotation_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid quotation ID']);
    exit;
}

// Get quotation details
$quotation = getRecord("SELECT * FROM tbl_sale_quotations WHERE id = $quotation_id");

if (!$quotation) {
    echo json_encode(['status' => 'error', 'message' => 'Quotation not found']);
    exit;
}

try {
    auragold_branch_require_document_access($conn, 'tbl_sale_quotations', $quotation_id);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}

// Get quotation items
$items = getList("SELECT * FROM tbl_sale_quotation_items WHERE quotation_id = $quotation_id ORDER BY id ASC");

// Get quotation payments
$payments = getList("SELECT * FROM tbl_sale_quotation_payments WHERE quotation_id = $quotation_id ORDER BY id ASC");
require_once __DIR__ . '/../includes/auragold_payment_details_merge.php';
auragold_merge_payment_details_into_payments($payments);

// Map quotation to "order" for populateOrderForm (order_no, order_date, etc.)
$order = $quotation;
$order['order_no'] = $quotation['quotation_no'] ?? '';
$order['quotation_no'] = $quotation['quotation_no'] ?? '';
$order['invoice_no'] = $quotation['quotation_no'] ?? '';
$order['order_date'] = $quotation['quotation_date'] ?? '';
$order['invoice_date'] = $quotation['quotation_date'] ?? '';
$order['order_id'] = $quotation['id'];
$order['against_type'] = $quotation['against_type'] ?? '';
$order['against_id'] = isset($quotation['against_id']) ? (int)$quotation['against_id'] : 0;

echo json_encode([
    'status' => 'success',
    'quotation' => $quotation,
    'order' => $order,
    'items' => $items,
    'payments' => $payments
]);
?>
