<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$search_term = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($search_term) < 2) {
    echo json_encode(['status' => 'success', 'orders' => []]);
    exit;
}

$search_esc = mysqli_real_escape_string($conn, $search_term);

$tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_material_issues'");
if (!$tbl || mysqli_num_rows($tbl) === 0) {
    if ($tbl) {
        mysqli_free_result($tbl);
    }
    echo json_encode(['status' => 'success', 'orders' => []]);
    exit;
}
mysqli_free_result($tbl);

$mi_br_scope = function_exists('auragold_effective_branch_list_scope_sql')
    ? auragold_effective_branch_list_scope_sql($conn, 'tbl_material_issues') : '';

$query = "
    SELECT 
        id,
        sale_order_id,
        material_issue_no AS order_no,
        customer_name,
        order_date,
        due_date,
        grand_total,
        sale_order_no,
        status
    FROM tbl_material_issues
    WHERE (
        material_issue_no LIKE '%$search_esc%'
        OR customer_name LIKE '%$search_esc%'
        OR sale_order_no LIKE '%$search_esc%'
    )
    $mi_br_scope
    ORDER BY id DESC
    LIMIT 20
";

$orders = getList($query);

$results = [];
foreach ($orders as $order) {
    $results[] = [
        'id' => (int)$order['id'],
        'sale_order_id' => isset($order['sale_order_id']) ? (int)$order['sale_order_id'] : 0,
        'order_no' => $order['order_no'] ?? '',
        'customer_name' => $order['customer_name'] ?? '',
        'order_date' => $order['order_date'] ?? '',
        'grand_total' => (float)($order['grand_total'] ?? 0),
        'currency' => 'AED',
        'formatted_date' => !empty($order['order_date']) ? date('d M Y', strtotime($order['order_date'])) : ''
    ];
}

echo json_encode([
    'status' => 'success',
    'orders' => $results
]);
