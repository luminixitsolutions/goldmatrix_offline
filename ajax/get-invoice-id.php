<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$invoice_no = isset($_GET['invoice_no']) ? esc($_GET['invoice_no']) : '';
$type = isset($_GET['type']) ? esc($_GET['type']) : '';

if (empty($invoice_no)) {
    echo json_encode(['status' => 'error', 'message' => 'Invoice number is required']);
    exit;
}

if ($type === 'purchase') {
    // Look up Purchase Invoice ID by invoice number
    $invoice = getRecord("SELECT id FROM tbl_purchase_invoices WHERE invoice_no = '$invoice_no' LIMIT 1");
    if ($invoice && isset($invoice['id'])) {
        echo json_encode([
            'status' => 'success',
            'invoice_id' => (int)$invoice['id']
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Purchase invoice not found']);
    }
} else if ($type === 'sale') {
    // Look up Sale Order ID by order number
    $order = getRecord("SELECT id FROM tbl_sale_orders WHERE order_no = '$invoice_no' LIMIT 1");
    if ($order && isset($order['id'])) {
        echo json_encode([
            'status' => 'success',
            'order_id' => (int)$order['id']
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Sale order not found']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid type']);
}
?>

