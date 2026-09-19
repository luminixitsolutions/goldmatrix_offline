<?php
/**
 * Save a single scrap payment item from the Scrap Payment modal (e.g. on sale-invoice, purchase-invoice).
 * Creates one tbl_old_jewelry_scrap_invoices header and one tbl_old_jewelry_scrap_invoice_items row
 * so the record appears on old-jewellery.php with all details.
 */
session_start();
require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$t1 = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_scrap_invoices'");
$t2 = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_scrap_invoice_items'");
if (!$t1 || mysqli_num_rows($t1) === 0 || !$t2 || mysqli_num_rows($t2) === 0) {
    echo json_encode(['success' => false, 'message' => 'Scrap invoice tables do not exist. Please run admin/sql/create_old_jewelry_scrap_invoice_tables.sql first.']);
    exit;
}

$source_invoice_no = isset($_POST['source_invoice_no']) ? trim($_POST['source_invoice_no']) : '';
$source_customer_name = isset($_POST['source_customer_name']) ? trim($_POST['source_customer_name']) : '';
$metal_id = isset($_POST['metal_id']) ? (int)$_POST['metal_id'] : 0;
$metal_name = isset($_POST['metal_name']) ? trim($_POST['metal_name']) : '';
$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$product_name = isset($_POST['product_name']) ? trim($_POST['product_name']) : '';
$quantity = (float)($_POST['quantity'] ?? 1);
$gross_wt = (float)($_POST['gross_wt'] ?? 0);
$less_wt = (float)($_POST['less_wt'] ?? 0);
$stone_wt = (float)($_POST['stone_wt'] ?? 0);
$net_wt = (float)($_POST['net_wt'] ?? 0);
$purity = (float)($_POST['purity'] ?? 0);
$purity_wt = (float)($_POST['purity_wt'] ?? 0);
$rate = (float)($_POST['rate'] ?? 0);
$amount = (float)($_POST['amount'] ?? 0);
$item_code = isset($_POST['item_code']) ? trim($_POST['item_code']) : '';

$description = $product_name ?: 'Scrap gold';
if ($metal_name) {
    $description = $product_name ? ($product_name . ' (' . $metal_name . ')') : $metal_name;
}

$amount = $amount > 0 ? $amount : ($rate * $net_wt);

$customer_name = $source_customer_name ?: 'Scrap from Sale';
$against_of = $source_invoice_no ?: '';

// Next OJB invoice number
$last = getRecord("SELECT invoice_no FROM tbl_old_jewelry_scrap_invoices ORDER BY id DESC LIMIT 1");
$next_num = 1;
if ($last && !empty($last['invoice_no'])) {
    $next_num = (int)preg_replace('/[^0-9]/', '', $last['invoice_no']) + 1;
}
$invoice_no = 'OJB-' . $next_num;
$invoice_date = date('Y-m-d');
$created_by = isset($_SESSION['Admin']['id']) ? (int)$_SESSION['Admin']['id'] : 0;

$invoice_no_esc = esc($invoice_no);
$customer_name_esc = esc($customer_name);
$against_of_esc = esc($against_of);
$description_esc = esc($description);
$item_code_esc = esc($item_code);

$ins_inv = "INSERT INTO tbl_old_jewelry_scrap_invoices (
    invoice_no, customer_name, against_of, invoice_date, grand_total, status, created_by
) VALUES (
    '$invoice_no_esc', '$customer_name_esc', " . ($against_of_esc ? "'$against_of_esc'" : 'NULL') . ",
    '$invoice_date', $amount, 'saved', $created_by
)";
if (!mysqli_query($conn, $ins_inv)) {
    echo json_encode(['success' => false, 'message' => 'Failed to create scrap invoice: ' . mysqli_error($conn)]);
    exit;
}
$invoice_id = (int)mysqli_insert_id($conn);

$has_metal_id = false;
$has_less_wt = false;
$cols = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_old_jewelry_scrap_invoice_items");
if ($cols) {
    while ($c = mysqli_fetch_assoc($cols)) {
        if (isset($c['Field']) && $c['Field'] === 'metal_id') $has_metal_id = true;
        if (isset($c['Field']) && $c['Field'] === 'less_wt') $has_less_wt = true;
    }
}

if ($has_metal_id && $has_less_wt) {
    $metal_sql = $metal_id > 0 ? $metal_id : 'NULL';
    $qi = "INSERT INTO tbl_old_jewelry_scrap_invoice_items (invoice_id, metal_id, barcode, description, gross_wt, less_wt, final_wt, net_wt, pure_wt, quantity, purity, rate, amount, diamond_wt, gemstone_wt) VALUES (
        $invoice_id, $metal_sql, " . ($item_code_esc ? "'$item_code_esc'" : 'NULL') . ", " . ($description_esc ? "'$description_esc'" : 'NULL') . ",
        $gross_wt, $less_wt, $net_wt, $net_wt, $purity_wt, $quantity, $purity, $rate, $amount, 0, $stone_wt
    )";
} else {
    $qi = "INSERT INTO tbl_old_jewelry_scrap_invoice_items (invoice_id, barcode, description, gross_wt, final_wt, net_wt, pure_wt, quantity, purity, rate, amount, diamond_wt, gemstone_wt) VALUES (
        $invoice_id, " . ($item_code_esc ? "'$item_code_esc'" : 'NULL') . ", " . ($description_esc ? "'$description_esc'" : 'NULL') . ",
        $gross_wt, $net_wt, $net_wt, $purity_wt, $quantity, $purity, $rate, $amount, 0, $stone_wt
    )";
}
if (!mysqli_query($conn, $qi)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save scrap item: ' . mysqli_error($conn)]);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Scrap gold saved. View in Old Jewellery - Scrap.', 'invoice_id' => $invoice_id, 'invoice_no' => $invoice_no]);
