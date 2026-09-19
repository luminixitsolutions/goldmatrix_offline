<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$customer_name = isset($_GET['customer_name']) ? esc($_GET['customer_name']) : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

if ($customer_id <= 0 && empty($customer_name)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Customer ID or name is required',
        'transactions' => []
    ]);
    exit;
}

$where_clause = "status = 1";
if ($customer_id > 0) {
    $where_clause = "status = 1 AND customer_id = $customer_id";
} else if (!empty($customer_name)) {
    $where_clause = "status = 1 AND customer_name = '$customer_name'";
}

// Get transaction history from customer ledger
$transactions = getList("
    SELECT 
        id,
        transaction_type,
        transaction_no,
        transaction_date,
        debit_amount,
        credit_amount,
        debit_gold,
        credit_gold,
        debit_silver,
        credit_silver,
        balance_amount,
        balance_gold,
        balance_silver,
        description,
        reference_no,
        created_at
    FROM tbl_customer_ledger
    WHERE $where_clause
    ORDER BY transaction_date DESC, id DESC
    LIMIT $limit
");

// If not found with exact match, try case-insensitive
if (empty($transactions) && !empty($customer_name) && $customer_id <= 0) {
    $transactions = getList("
        SELECT 
            id,
            transaction_type,
            transaction_no,
            transaction_date,
            debit_amount,
            credit_amount,
            debit_gold,
            credit_gold,
            debit_silver,
            credit_silver,
            balance_amount,
            balance_gold,
            balance_silver,
            description,
            reference_no,
            created_at
        FROM tbl_customer_ledger
        WHERE status = 1 AND LOWER(customer_name) = LOWER('$customer_name')
        ORDER BY transaction_date DESC, id DESC
        LIMIT $limit
    ");
}

// Format transactions
foreach ($transactions as &$transaction) {
    $transaction['debit_amount'] = (float)($transaction['debit_amount'] ?? 0);
    $transaction['credit_amount'] = (float)($transaction['credit_amount'] ?? 0);
    $transaction['debit_gold'] = (float)($transaction['debit_gold'] ?? 0);
    $transaction['credit_gold'] = (float)($transaction['credit_gold'] ?? 0);
    $transaction['debit_silver'] = (float)($transaction['debit_silver'] ?? 0);
    $transaction['credit_silver'] = (float)($transaction['credit_silver'] ?? 0);
    $transaction['balance_amount'] = (float)($transaction['balance_amount'] ?? 0);
    $transaction['balance_gold'] = (float)($transaction['balance_gold'] ?? 0);
    $transaction['balance_silver'] = (float)($transaction['balance_silver'] ?? 0);
}

echo json_encode([
    'status' => 'success',
    'transactions' => $transactions
]);
?>
