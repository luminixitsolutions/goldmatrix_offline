<?php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../includes/auragold_branch_data_scope.php';

header('Content-Type: application/json');

$search_term = isset($_GET['q']) ? esc($_GET['q']) : '';

if (strlen($search_term) < 2) {
    echo json_encode(['status' => 'success', 'invoices' => []]);
    exit;
}

// Search sale invoices only (filter by invoice_type = 'sale' if column exists; tbl_sale_invoices holds only sale invoices)
$has_invoice_type = false;
$col_check = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_invoices LIKE 'invoice_type'");
if ($col_check && mysqli_num_rows($col_check) > 0) {
    $has_invoice_type = true;
    mysqli_free_result($col_check);
}
$invoice_type_cond = $has_invoice_type ? " AND (invoice_type = 'sale' OR invoice_type IS NULL)" : "";

$branch_cond = '';
if (auragold_effective_branch_id() > 0 && auragold_ensure_sale_invoice_branch_id_column($conn)) {
    $b = (int) auragold_effective_branch_id();
    $branch_cond = " AND branch_id = $b ";
}

$query = "
    SELECT 
        id, 
        invoice_no,
        customer_name,
        customer_id,
        invoice_date,
        grand_total,
        currency
    FROM tbl_sale_invoices 
    WHERE status != 'deleted'
    $invoice_type_cond
    $branch_cond
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
?>
