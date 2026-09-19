<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$search_term = isset($_GET['q']) ? esc($_GET['q']) : '';

if (strlen($search_term) < 2) {
    echo json_encode(['status' => 'success', 'orders' => []]);
    exit;
}

// Search repair orders by customer name or order number
$query = "
    SELECT 
        id, 
        order_no,
        customer_name,
        customer_id,
        order_date,
        grand_total,
        currency
    FROM tbl_repair_orders 
    WHERE (
        customer_name LIKE '%$search_term%' 
        OR order_no LIKE '%$search_term%'
    )
    ORDER BY id DESC
    LIMIT 20
";

$orders = getList($query);

$results = [];
foreach ($orders as $order) {
    $results[] = [
        'id' => $order['id'],
        'order_no' => $order['order_no'],
        'customer_name' => $order['customer_name'] ?? '',
        'customer_id' => $order['customer_id'] ?? 0,
        'order_date' => $order['order_date'] ?? '',
        'grand_total' => $order['grand_total'] ?? 0,
        'currency' => $order['currency'] ?? 'AED',
        'formatted_date' => !empty($order['order_date']) ? date('d M Y', strtotime($order['order_date'])) : ''
    ];
}

echo json_encode([
    'status' => 'success',
    'orders' => $results
]);
?>
