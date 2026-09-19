<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$search_term = isset($_GET['q']) ? esc($_GET['q']) : '';

if (strlen($search_term) < 2) {
    echo json_encode(['status' => 'success', 'invoices' => []]);
    exit;
}

$tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_debit_notes'");
if (!$tbl || mysqli_num_rows($tbl) == 0) {
    echo json_encode(['status' => 'success', 'invoices' => []]);
    exit;
}

$query = "SELECT id, debit_note_no, customer_name, customer_id, debit_note_date, grand_total, currency FROM tbl_debit_notes WHERE (status IS NULL OR status != 'deleted') AND (customer_name LIKE '%" . mysqli_real_escape_string($conn, $search_term) . "%' OR debit_note_no LIKE '%" . mysqli_real_escape_string($conn, $search_term) . "%') ORDER BY id DESC LIMIT 20";
$list = getList($query);

$results = [];
foreach ($list as $row) {
    $results[] = [
        'id' => $row['id'],
        'invoice_no' => $row['debit_note_no'],
        'debit_note_no' => $row['debit_note_no'],
        'customer_name' => $row['customer_name'] ?? '',
        'customer_id' => $row['customer_id'] ?? 0,
        'invoice_date' => $row['debit_note_date'] ?? '',
        'debit_note_date' => $row['debit_note_date'] ?? '',
        'grand_total' => $row['grand_total'] ?? 0,
        'currency' => $row['currency'] ?? 'AED',
        'formatted_date' => !empty($row['debit_note_date']) ? date('d M Y', strtotime($row['debit_note_date'])) : ''
    ];
}

echo json_encode(['status' => 'success', 'invoices' => $results]);
?>
