<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$search_term = isset($_GET['q']) ? esc($_GET['q']) : '';

if (strlen($search_term) < 2) {
    echo json_encode(['status' => 'success', 'invoices' => []]);
    exit;
}

// Search purchase invoices by supplier name, invoice number, or customer name
$query = "
    SELECT 
        id, 
        invoice_no,
        supplier_name,
        supplier_id,
        invoice_date,
        grand_total,
        currency
    FROM tbl_purchase_invoices 
    WHERE status != 'deleted'
    AND (
        supplier_name LIKE '%$search_term%' 
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
        'supplier_name' => $invoice['supplier_name'] ?? '',
        'supplier_id' => $invoice['supplier_id'] ?? 0,
        'invoice_date' => $invoice['invoice_date'] ?? '',
        'grand_total' => $invoice['grand_total'] ?? 0,
        'currency' => $invoice['currency'] ?? 'AED',
        'display_text' => $invoice['invoice_no'] . ' - ' . $invoice['supplier_name'] . 
            ($invoice['invoice_date'] ? ' (' . date('d M Y', strtotime($invoice['invoice_date'])) . ')' : ''),
        'formatted_date' => $invoice['invoice_date'] ? date('d M Y', strtotime($invoice['invoice_date'])) : ''
    ];
}

echo json_encode([
    'status' => 'success',
    'invoices' => $results
]);
?>
