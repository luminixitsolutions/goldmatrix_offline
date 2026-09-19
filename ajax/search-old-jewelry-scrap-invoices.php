<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$search_term = isset($_GET['q']) ? esc($_GET['q']) : '';

if (strlen($search_term) < 2) {
    echo json_encode(['status' => 'success', 'invoices' => []]);
    exit;
}

$t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_scrap_invoices'");
if (!$t || mysqli_num_rows($t) === 0) {
    echo json_encode(['status' => 'success', 'invoices' => []]);
    exit;
}

// Search old jewelry scrap invoices by customer name or invoice number
$query = "
    SELECT 
        id, 
        invoice_no,
        customer_name,
        customer_id,
        invoice_date,
        grand_total,
        currency
    FROM tbl_old_jewelry_scrap_invoices 
    WHERE status != 'deleted'
    AND (
        customer_name LIKE '%$search_term%' 
        OR invoice_no LIKE '%$search_term%'
    )
    ORDER BY id DESC
    LIMIT 20
";

$invoices = getList($query);

$results = [];
foreach ($invoices as $invoice) {
    $results[] = [
        'id' => $invoice['id'],
        'invoice_no' => $invoice['invoice_no'],
        'customer_name' => $invoice['customer_name'] ?? '',
        'customer_id' => $invoice['customer_id'] ?? 0,
        'invoice_date' => $invoice['invoice_date'] ?? '',
        'grand_total' => $invoice['grand_total'] ?? 0,
        'currency' => $invoice['currency'] ?? 'AED',
        'display_text' => $invoice['invoice_no'] . ' - ' . $invoice['customer_name'] . 
            ($invoice['invoice_date'] ? ' (' . date('d M Y', strtotime($invoice['invoice_date'])) . ')' : ''),
        'formatted_date' => $invoice['invoice_date'] ? date('d M Y', strtotime($invoice['invoice_date'])) : ''
    ];
}

echo json_encode([
    'status' => 'success',
    'invoices' => $results
]);
