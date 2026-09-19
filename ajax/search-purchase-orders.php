<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$search_term = isset($_GET['q']) ? trim($_GET['q']) : '';
$search_esc = mysqli_real_escape_string($conn, $search_term);

// Check table exists
$tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_purchase_orders'");
if (!$tbl || mysqli_num_rows($tbl) === 0) {
    if ($tbl) mysqli_free_result($tbl);
    echo json_encode(['status' => 'success', 'orders' => []]);
    exit;
}
mysqli_free_result($tbl);

// When search term is empty or short: return recent purchase orders (for modal list). Otherwise search.
if (strlen($search_term) < 2) {
    $query = "
        SELECT id, order_no, customer_name, customer_id, order_date, grand_total, currency
        FROM tbl_purchase_orders
        ORDER BY id DESC
        LIMIT 50
    ";
} else {
    $query = "
        SELECT id, order_no, customer_name, customer_id, order_date, grand_total, currency
        FROM tbl_purchase_orders
        WHERE (customer_name LIKE '%$search_esc%' OR order_no LIKE '%$search_esc%')
        ORDER BY id DESC
        LIMIT 20
    ";
}

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
