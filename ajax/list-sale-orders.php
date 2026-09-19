<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$search_term = isset($_GET['q']) ? trim(esc($_GET['q'])) : '';

$query = "
    SELECT 
        o.id, 
        o.order_no,
        o.customer_name,
        o.customer_id,
        o.order_date,
        o.due_date,
        o.grand_total,
        o.paid_amt,
        o.currency,
        c.mobile_no AS contact_no,
        (SELECT soi.product_name FROM tbl_sale_order_items soi WHERE soi.order_id = o.id ORDER BY soi.id LIMIT 1) AS first_item,
        (SELECT COALESCE(SUM(soi.gross_weight), 0) FROM tbl_sale_order_items soi WHERE soi.order_id = o.id) AS gross_wt
    FROM tbl_sale_orders o
    LEFT JOIN tbl_customers c ON c.id = o.customer_id AND c.status = 1
";
$params = [];
if ($search_term !== '') {
    $query .= " WHERE (o.customer_name LIKE ? OR o.order_no LIKE ?) ";
    $term = '%' . $search_term . '%';
    $params = [$term, $term];
}
$query .= " ORDER BY o.id DESC LIMIT 20";

if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'ss', $params[0], $params[1]);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, $query);
}

$results = [];
if ($result) {
    while ($order = mysqli_fetch_assoc($result)) {
        $results[] = [
            'id' => $order['id'],
            'order_no' => $order['order_no'],
            'customer_name' => $order['customer_name'] ?? '',
            'customer_id' => $order['customer_id'] ?? 0,
            'order_date' => $order['order_date'] ?? '',
            'due_date' => $order['due_date'] ?? '',
            'grand_total' => $order['grand_total'] ?? 0,
            'paid_amt' => $order['paid_amt'] ?? 0,
            'contact_no' => $order['contact_no'] ?? '',
            'first_item' => $order['first_item'] ?? '',
            'gross_wt' => $order['gross_wt'] ?? 0,
            'currency' => $order['currency'] ?? 'AED',
            'formatted_date' => !empty($order['order_date']) ? date('d-m-Y', strtotime($order['order_date'])) : '',
            'formatted_due_date' => !empty($order['due_date']) ? date('d-m-Y', strtotime($order['due_date'])) : ''
        ];
    }
    mysqli_free_result($result);
}

echo json_encode([
    'status' => 'success',
    'orders' => $results
]);
?>
