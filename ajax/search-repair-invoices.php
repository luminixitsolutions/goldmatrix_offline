<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$search_term = isset($_GET['q']) ? esc($_GET['q']) : '';

if (strlen($search_term) < 2) {
    echo json_encode(['status' => 'success', 'invoices' => []]);
    exit;
}

$tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_repair_invoices'");
if (!$tbl || mysqli_num_rows($tbl) == 0) {
    echo json_encode(['status' => 'success', 'invoices' => []]);
    exit;
}

$query = "
    SELECT 
        id, 
        repair_invoice_no,
        customer_name,
        customer_id,
        repair_invoice_date,
        grand_total,
        currency
    FROM tbl_repair_invoices 
    WHERE (status IS NULL OR status != 'deleted')
    AND (
        customer_name LIKE '%$search_term%' 
        OR repair_invoice_no LIKE '%$search_term%'
    )
    ORDER BY id DESC
    LIMIT 20
";

$list = getList($query);

$results = [];
foreach ($list as $row) {
    $results[] = [
        'id' => $row['id'],
        'order_id' => $row['id'],
        'invoice_no' => $row['repair_invoice_no'],
        'repair_invoice_no' => $row['repair_invoice_no'],
        'customer_name' => $row['customer_name'] ?? '',
        'customer_id' => $row['customer_id'] ?? 0,
        'invoice_date' => $row['repair_invoice_date'] ?? '',
        'repair_invoice_date' => $row['repair_invoice_date'] ?? '',
        'grand_total' => $row['grand_total'] ?? 0,
        'currency' => $row['currency'] ?? 'AED',
        'formatted_date' => !empty($row['repair_invoice_date']) ? date('d M Y', strtotime($row['repair_invoice_date'])) : ''
    ];
}

echo json_encode([
    'status' => 'success',
    'invoices' => $results
]);
?>
