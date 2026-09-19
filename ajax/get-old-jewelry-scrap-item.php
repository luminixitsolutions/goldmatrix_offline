<?php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../includes/sync_purchase_scrap_to_ojb.php';

header('Content-Type: application/json');

$item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;

if ($item_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid item ID']);
    exit;
}

$t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_scrap_invoice_items'");
if (!$t || mysqli_num_rows($t) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Scrap invoice tables do not exist']);
    exit;
}

$item = getRecord("SELECT * FROM tbl_old_jewelry_scrap_invoice_items WHERE id = $item_id AND status = 1");
if (!$item) {
    echo json_encode(['status' => 'error', 'message' => 'Item not found']);
    exit;
}

$invoice_id = (int)$item['invoice_id'];
$invoice = getRecord("SELECT id, invoice_no, customer_name, against_of, invoice_date, grand_total, ref_no FROM tbl_old_jewelry_scrap_invoices WHERE id = $invoice_id");
if (!$invoice) {
    echo json_encode(['status' => 'error', 'message' => 'Invoice not found']);
    exit;
}

$ref_no = trim((string)($invoice['ref_no'] ?? ''));
$pd = [];
if ($ref_no !== '' && preg_match('/^PI:(\d+)$/i', $ref_no, $m)) {
    $pi_id = (int)$m[1];
    $scrap_pd_row = null;
    if (function_exists('pipTableHasPaymentDetailsColumn') && pipTableHasPaymentDetailsColumn($conn)) {
        $scrap_pd_row = getRecord("SELECT payment_details FROM tbl_purchase_invoice_payments WHERE invoice_id = $pi_id AND LOWER(TRIM(payment_type)) = 'scrap' AND status = 1 ORDER BY id DESC LIMIT 1");
    }
    $pd = [];
    if ($scrap_pd_row && !empty($scrap_pd_row['payment_details'])) {
        $pd = json_decode($scrap_pd_row['payment_details'], true);
    }
    $modal_vals = ojbBuildScrapModalItemValuesFromPaymentDetails(is_array($pd) ? $pd : []);
    if ($modal_vals !== null) {
        $item['gross_wt'] = $modal_vals['gross_wt'];
        $item['less_wt'] = $modal_vals['less_wt'];
        $item['final_wt'] = $modal_vals['final_wt'];
        $item['net_wt'] = $modal_vals['net_wt'];
        $item['pure_wt'] = $modal_vals['pure_wt'];
        $item['quantity'] = $modal_vals['quantity'];
        $item['purity'] = $modal_vals['purity'];
        $item['rate'] = $modal_vals['rate'];
        $item['amount'] = $modal_vals['amount'];
        if ($modal_vals['description'] !== '') {
            $item['description'] = $modal_vals['description'];
        }
        if ($modal_vals['barcode'] !== '') {
            $item['barcode'] = $modal_vals['barcode'];
        }
        if ($modal_vals['metal_id'] > 0) {
            $item['metal_id'] = $modal_vals['metal_id'];
        }
    }
}

$scrap_product_id = 0;
if (isset($pd) && is_array($pd) && !empty($pd['scrap_product_id'])) {
    $scrap_product_id = (int) $pd['scrap_product_id'];
}

$metal_name = '';
$mid = isset($item['metal_id']) ? (int) $item['metal_id'] : 0;
$desc_for_pc = trim((string) ($item['description'] ?? ''));
if ($scrap_product_id <= 0 && $desc_for_pc !== '') {
    $base = preg_replace('/\s*\([^)]+\)\s*$/', '', $desc_for_pc);
    $base = trim($base);
    if ($base !== '') {
        $b_esc = mysqli_real_escape_string($conn, $base);
        $pr = getRecord("SELECT id FROM tbl_products WHERE status = 1 AND (name = '$b_esc' OR alternate_name = '$b_esc') LIMIT 1");
        if (!$pr) {
            $pr = getRecord("SELECT id FROM tbl_products WHERE status = 1 AND name LIKE '%" . mysqli_real_escape_string($conn, $base) . "%' ORDER BY id ASC LIMIT 1");
        }
        if ($pr) {
            $scrap_product_id = (int) $pr['id'];
        }
    }
}
if ($mid <= 0 && $desc_for_pc !== '' && preg_match('/\(([^)]+)\)\s*$/', $desc_for_pc, $mm)) {
    $metal_hint = trim($mm[1]);
    $mr = getRecord("SELECT id FROM tbl_metal WHERE status = 1 AND (display_name = '" . mysqli_real_escape_string($conn, $metal_hint) . "' OR name = '" . mysqli_real_escape_string($conn, $metal_hint) . "') LIMIT 1");
    if ($mr) {
        $mid = (int) $mr['id'];
    }
}
$scrap_pc_id = 0;
if ($scrap_product_id > 0 && $mid > 0) {
    $pcr = getRecord("SELECT id FROM tbl_product_characteristics WHERE product_id = $scrap_product_id AND metal_id = $mid AND status = 1 ORDER BY id DESC LIMIT 1");
    if ($pcr) {
        $scrap_pc_id = (int) $pcr['id'];
    }
}
if ($mid > 0) {
    $mrow = getRecord("SELECT display_name FROM tbl_metal WHERE id = $mid LIMIT 1");
    if ($mrow) {
        $metal_name = trim((string)($mrow['display_name'] ?? ''));
    }
}
$less_wt = isset($item['less_wt']) ? (float)$item['less_wt'] : 0;

require_once __DIR__ . '/../includes/old_jewelry_scrap_stock_balance.php';
$orig_gross_for_balance = (float) ($item['gross_wt'] ?? 0);
$stocked_gross_sum = auragold_oj_scrap_stocked_gross_sum_for_line_including_single_line_orphans($conn, $invoice_id, (int) $item['id']);
$remaining_gross = max(0, $orig_gross_for_balance - $stocked_gross_sum);
$ratio_item = $orig_gross_for_balance > 0 ? ($remaining_gross / $orig_gross_for_balance) : 0;
$item['original_gross_wt'] = $orig_gross_for_balance;
$item['stocked_gross_wt'] = $stocked_gross_sum;
$item['remaining_gross_wt'] = $remaining_gross;
$item['gross_wt'] = $remaining_gross;
$item['final_wt'] = round((float) ($item['final_wt'] ?? 0) * $ratio_item, 4);
$item['net_wt'] = round((float) ($item['net_wt'] ?? 0) * $ratio_item, 4);
$item['pure_wt'] = round((float) ($item['pure_wt'] ?? 0) * $ratio_item, 4);
$item['amount'] = round((float) ($item['amount'] ?? 0) * $ratio_item, 2);
$less_wt = round($less_wt * $ratio_item, 4);
$item['less_wt'] = $less_wt;

echo json_encode([
    'status' => 'success',
    'total_scrap_gross_wt' => $orig_gross_for_balance,
    'stocked_gross_wt' => $stocked_gross_sum,
    'remaining_gross_wt' => $remaining_gross,
    'item' => [
        'id' => $item['id'],
        'invoice_id' => $item['invoice_id'],
        'barcode' => $item['barcode'] ?? '',
        'description' => $item['description'] ?? '',
        'gross_wt' => $item['gross_wt'] ?? 0,
        'original_gross_wt' => $orig_gross_for_balance,
        'final_wt' => $item['final_wt'] ?? 0,
        'net_wt' => $item['net_wt'] ?? 0,
        'pure_wt' => $item['pure_wt'] ?? 0,
        'purity' => $item['purity'] ?? 0,
        'rate' => $item['rate'] ?? 0,
        'amount' => $item['amount'] ?? 0,
        'quantity' => $item['quantity'] ?? 1,
        'less_wt' => $less_wt,
        'metal' => $metal_name,
        'metal_id' => $mid,
        'product_id' => $scrap_product_id,
        'product_characteristic_id' => $scrap_pc_id,
        'location' => '',
        'branch_id' => 0,
    ],
    'invoice' => [
        'id' => $invoice['id'],
        'invoice_no' => $invoice['invoice_no'],
        'customer_name' => $invoice['customer_name'],
        'against_of' => $invoice['against_of'] ?? '',
        'invoice_date' => $invoice['invoice_date'],
        'grand_total' => $invoice['grand_total'] ?? 0,
    ]
]);
