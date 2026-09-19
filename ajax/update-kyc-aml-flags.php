<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$customer_id = isset($_POST['customer_id']) ? (int) $_POST['customer_id'] : 0;
$field = isset($_POST['field']) ? strtolower(trim((string) $_POST['field'])) : '';
$value = isset($_POST['value']) ? (int) $_POST['value'] : 0;
$value = $value ? 1 : 0;

if ($customer_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid customer id']);
    exit;
}
if ($field !== 'kyc' && $field !== 'aml') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid field']);
    exit;
}

$exists = getRecord("SELECT id FROM tbl_customers WHERE id = $customer_id AND status = 1 LIMIT 1");
if (!$exists) {
    echo json_encode(['status' => 'error', 'message' => 'Customer not found']);
    exit;
}

$col = $field === 'kyc' ? 'kyc' : 'aml';
$sql = "UPDATE tbl_customers SET `$col` = $value, updated_at = NOW() WHERE id = $customer_id AND status = 1";
if (!mysqli_query($conn, $sql)) {
    echo json_encode(['status' => 'error', 'message' => 'Update failed']);
    exit;
}

echo json_encode([
    'status' => 'success',
    'customer_id' => $customer_id,
    'field' => $field,
    'value' => $value,
]);
