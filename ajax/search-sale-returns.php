<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$search_term = isset($_GET['q']) ? esc($_GET['q']) : '';

if (strlen($search_term) < 2) {
    echo json_encode(['status' => 'success', 'returns' => []]);
    exit;
}

// Search sale returns by customer name or return number
$query = "
    SELECT 
        id, 
        return_no,
        customer_name,
        customer_id,
        return_date,
        grand_total,
        currency
    FROM tbl_sale_returns 
    WHERE status != 'deleted'
    AND (
        customer_name LIKE '%$search_term%' 
        OR return_no LIKE '%$search_term%'
    )
    ORDER BY id DESC
    LIMIT 20
";

$returns = getList($query);

$results = [];
foreach ($returns as $return) {
    $results[] = [
        'id' => $return['id'],
        'return_no' => $return['return_no'],
        'customer_name' => $return['customer_name'] ?? '',
        'customer_id' => $return['customer_id'] ?? 0,
        'return_date' => $return['return_date'] ?? '',
        'grand_total' => $return['grand_total'] ?? 0,
        'currency' => $return['currency'] ?? 'USD',
        'display_text' => $return['return_no'] . ' - ' . $return['customer_name'] . 
            ($return['return_date'] ? ' (' . date('d M Y', strtotime($return['return_date'])) . ')' : ''),
        'formatted_date' => $return['return_date'] ? date('d M Y', strtotime($return['return_date'])) : ''
    ];
}

echo json_encode([
    'status' => 'success',
    'returns' => $results
]);
?>
