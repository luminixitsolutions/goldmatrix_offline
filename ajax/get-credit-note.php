<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid credit note ID']);
    exit;
}

$tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_credit_notes'");
if (!$tbl || mysqli_num_rows($tbl) == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Credit note tables not found']);
    exit;
}

$note = getRecord("SELECT * FROM tbl_credit_notes WHERE id = $order_id");

if (!$note) {
    echo json_encode(['status' => 'error', 'message' => 'Credit note not found']);
    exit;
}

$order = $note;
$order['order_no'] = $note['credit_note_no'];
$order['order_date'] = $note['credit_note_date'];
$order['order_id'] = $note['id'];
$order['invoice_no'] = $note['credit_note_no'];
$order['invoice_date'] = $note['credit_note_date'];

$items = getList("SELECT * FROM tbl_credit_note_items WHERE credit_note_id = $order_id ORDER BY id ASC");
$payments = getList("SELECT * FROM tbl_credit_note_payments WHERE credit_note_id = $order_id ORDER BY id ASC");

echo json_encode([
    'status' => 'success',
    'order' => $order,
    'items' => $items,
    'payments' => $payments
]);
?>
