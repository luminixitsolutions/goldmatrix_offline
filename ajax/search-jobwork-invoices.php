<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$search_term = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($search_term) < 2) {
    echo json_encode(['status' => 'success', 'orders' => []]);
    exit;
}

$search_esc = mysqli_real_escape_string($conn, $search_term);

$tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_invoices'");
if (!$tbl || mysqli_num_rows($tbl) === 0) {
    if ($tbl) {
        mysqli_free_result($tbl);
    }
    echo json_encode(['status' => 'success', 'orders' => []]);
    exit;
}
mysqli_free_result($tbl);

$query = "
    SELECT 
        i.id AS invoice_row_id,
        i.invoice_no,
        i.jobwork_order_id,
        COALESCE(NULLIF(TRIM(i.customer_name), ''), j.customer_name, '') AS customer_name,
        i.grand_total,
        j.order_date,
        j.jobwork_no,
        j.sale_order_no
    FROM tbl_jobwork_invoices i
    INNER JOIN tbl_jobwork_orders j ON j.id = i.jobwork_order_id
    WHERE (
        i.invoice_no LIKE '%$search_esc%'
        OR IFNULL(i.customer_name, '') LIKE '%$search_esc%'
        OR IFNULL(j.customer_name, '') LIKE '%$search_esc%'
        OR IFNULL(j.jobwork_no, '') LIKE '%$search_esc%'
        OR IFNULL(j.sale_order_no, '') LIKE '%$search_esc%'
    )
    ORDER BY i.id DESC
    LIMIT 20
";

$orders = function_exists('getList') ? getList($query) : [];
if (!is_array($orders)) {
    $orders = [];
}

$results = [];
foreach ($orders as $order) {
    $results[] = [
        'id' => (int)($order['jobwork_order_id'] ?? 0),
        'invoice_no' => $order['invoice_no'] ?? '',
        'jobwork_no' => $order['jobwork_no'] ?? '',
        'customer_name' => $order['customer_name'] ?? '',
        'order_date' => $order['order_date'] ?? '',
        'grand_total' => (float)($order['grand_total'] ?? 0),
        'currency' => 'AED',
        'formatted_date' => !empty($order['order_date']) ? date('d M Y', strtotime($order['order_date'])) : '',
    ];
}

echo json_encode([
    'status' => 'success',
    'orders' => $results,
]);
