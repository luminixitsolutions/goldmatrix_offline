<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid repair invoice ID']);
    exit;
}

$tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_repair_invoices'");
if (!$tbl || mysqli_num_rows($tbl) == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Repair invoice tables not found']);
    exit;
}

$note = getRecord("SELECT * FROM tbl_repair_invoices WHERE id = $order_id");

if (!$note) {
    echo json_encode(['status' => 'error', 'message' => 'Repair invoice not found']);
    exit;
}

$order = $note;
$order['order_no'] = $note['repair_invoice_no'];
$order['order_date'] = $note['repair_invoice_date'];
$order['order_id'] = $note['id'];
$order['invoice_no'] = $note['repair_invoice_no'];
$order['invoice_date'] = $note['repair_invoice_date'];

$items = getList("SELECT * FROM tbl_repair_invoice_items WHERE repair_invoice_id = $order_id ORDER BY id ASC");
$payments = getList("SELECT * FROM tbl_repair_invoice_payments WHERE repair_invoice_id = $order_id ORDER BY id ASC");

echo json_encode([
    'status' => 'success',
    'order' => $order,
    'items' => $items,
    'payments' => $payments
]);
?>
