<?php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../includes/auragold_require_login.php';
if (is_file(__DIR__ . '/../includes/auragold_sale_order_jobwork_lock.php')) {
    require_once __DIR__ . '/../includes/auragold_sale_order_jobwork_lock.php';
}
require_once __DIR__ . '/../includes/customer_deletable_entries.php';

auragold_require_login_or_exit();

header('Content-Type: application/json');

$customer_id = isset($_GET['customer_id']) ? (int) $_GET['customer_id'] : 0;
$customer_name = isset($_GET['customer_name']) ? trim((string) $_GET['customer_name']) : '';

if ($customer_id <= 0 && $customer_name === '') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Please select a customer.',
    ]);
    exit;
}

$party = auragold_customer_deletable_resolve_party($conn, $customer_id, $customer_name);
if ($party['customer_id'] <= 0 && $party['customer_name'] === '') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Customer not found.',
    ]);
    exit;
}

$entries = auragold_customer_deletable_fetch_entries($conn, (int) $party['customer_id'], (string) $party['customer_name']);

$out = [];
foreach ($entries as $e) {
    $out[] = [
        'type' => (string) ($e['type'] ?? ''),
        'type_label' => (string) ($e['type_label'] ?? ''),
        'id' => (int) ($e['id'] ?? 0),
        'voucher_no' => (string) ($e['voucher_no'] ?? ''),
        'date' => (string) ($e['date'] ?? ''),
        'amount' => (float) ($e['amount'] ?? 0),
        'balance' => (float) ($e['balance'] ?? 0),
        'deletable' => !empty($e['deletable']),
        'block_reason' => (string) ($e['block_reason'] ?? ''),
        'delete_endpoint' => auragold_customer_deletable_delete_endpoint((string) ($e['type'] ?? '')),
    ];
}

echo json_encode([
    'status' => 'success',
    'customer_id' => (int) $party['customer_id'],
    'customer_name' => (string) $party['customer_name'],
    'count' => count($out),
    'entries' => $out,
]);
