<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$search_term = isset($_GET['q']) ? esc($_GET['q']) : '';

if (strlen($search_term) < 2) {
    echo json_encode(['status' => 'success', 'quotations' => []]);
    exit;
}

$scope = auragold_tbl_has_column($conn, 'tbl_sale_quotations', 'branch_id')
    ? auragold_sql_and_branch_scope($conn, 'tbl_sale_quotations', 'tbl_sale_quotations')
    : '';
// Search sale quotations by customer name or quotation number
$query = "
    SELECT 
        id, 
        quotation_no,
        customer_name,
        customer_id,
        quotation_date,
        grand_total,
        currency
    FROM tbl_sale_quotations 
    WHERE status != 'deleted'
    $scope
    AND (
        customer_name LIKE '%$search_term%' 
        OR quotation_no LIKE '%$search_term%'
    )
    ORDER BY id DESC
    LIMIT 20
";

$quotations = getList($query);

$results = [];
foreach ($quotations as $quotation) {
    $results[] = [
        'id' => $quotation['id'],
        'quotation_no' => $quotation['quotation_no'],
        'customer_name' => $quotation['customer_name'] ?? '',
        'customer_id' => $quotation['customer_id'] ?? 0,
        'quotation_date' => $quotation['quotation_date'] ?? '',
        'grand_total' => $quotation['grand_total'] ?? 0,
        'currency' => $quotation['currency'] ?? 'AED',
        'display_text' => $quotation['quotation_no'] . ' - ' . $quotation['customer_name'] . 
            ($quotation['quotation_date'] ? ' (' . date('d M Y', strtotime($quotation['quotation_date'])) . ')' : ''),
        'formatted_date' => $quotation['quotation_date'] ? date('d M Y', strtotime($quotation['quotation_date'])) : ''
    ];
}

echo json_encode([
    'status' => 'success',
    'quotations' => $results
]);
?>
