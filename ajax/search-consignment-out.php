<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$search_term = isset($_GET['q']) ? esc($_GET['q']) : '';

if (strlen($search_term) < 2) {
    echo json_encode(['status' => 'success', 'consignments' => []]);
    exit;
}

$query = "
    SELECT 
        id,
        consignment_no,
        customer_name,
        consignment_date,
        DATE_FORMAT(consignment_date, '%d %b %Y') as formatted_date,
        currency,
        grand_total,
        status
    FROM tbl_consignment_out 
    WHERE status = 'active'
    AND (
        consignment_no LIKE '%$search_term%'
        OR customer_name LIKE '%$search_term%'
    )
    ORDER BY id DESC
    LIMIT 20
";

$consignments = getList($query);

$invoices = [];
if (is_array($consignments)) {
    foreach ($consignments as $c) {
        $invoices[] = [
            'id' => (int)($c['id'] ?? 0),
            'invoice_no' => $c['consignment_no'] ?? '',
            'customer_name' => $c['customer_name'] ?? '',
            'supplier_name' => $c['customer_name'] ?? '',
            'formatted_date' => $c['formatted_date'] ?? '',
            'currency' => $c['currency'] ?? 'AED',
            'grand_total' => $c['grand_total'] ?? 0,
        ];
    }
}

echo json_encode([
    'status' => 'success',
    'consignments' => $consignments ?: [],
    'invoices' => $invoices,
]);
?>
