<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

if ($order_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid invoice ID']);
    exit;
}

$t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_scrap_invoices'");
if (!$t || mysqli_num_rows($t) === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Scrap invoice tables do not exist']);
    exit;
}

// Get invoice from old jewelry scrap invoices
$invoice = getRecord("SELECT * FROM tbl_old_jewelry_scrap_invoices WHERE id = $order_id");

if (!$invoice) {
    echo json_encode(['status' => 'error', 'message' => 'Invoice not found']);
    exit;
}

// Map to order structure expected by populateOrderForm
$order = $invoice;
$order['order_no'] = $invoice['invoice_no'];
$order['order_date'] = $invoice['invoice_date'];
$order['order_id'] = $invoice['id'];

// purpose=stock_remaining — scale weights to what's left to stock-in (old-jewellery-scrap-stock-in.php).
// Default / purpose=invoice — full saved lines for old-jewelry-scrap-invoice.php edit (no remaining_gross_wt cap).
$purpose = isset($_GET['purpose']) ? trim((string) $_GET['purpose']) : '';
$use_stock_remaining = ($purpose === 'stock_remaining');

$items_raw = getList("SELECT * FROM tbl_old_jewelry_scrap_invoice_items WHERE invoice_id = $order_id AND status = 1 ORDER BY id ASC");
$items = [];

if ($use_stock_remaining) {
    require_once __DIR__ . '/../includes/old_jewelry_scrap_stock_balance.php';
    foreach ($items_raw as $it) {
        $iid = (int) ($it['id'] ?? 0);
        $orig = (float) ($it['gross_wt'] ?? 0);
        $stocked = auragold_oj_scrap_stocked_gross_sum_for_line_including_single_line_orphans($conn, $order_id, $iid);
        $rem = max(0, $orig - $stocked);
        $ratio = $orig > 0 ? ($rem / $orig) : 0;
        $less_src = isset($it['less_wt']) ? (float) $it['less_wt'] : 0;
        $naw_raw = (float) ($it['net_amt_wt'] ?? 0);
        $net_line = (float) ($it['net_amt'] ?? 0);
        $tax_line = (float) ($it['tax'] ?? 0);
        $amt_line = (float) ($it['amount'] ?? 0);
        if ($naw_raw <= 0) {
            $naw_raw = ($net_line > 0 ? $net_line : $amt_line) + $tax_line;
        }
        $naw_scaled = round($naw_raw * $ratio, 2);
        $items[] = [
            'id' => $it['id'],
            'invoice_id' => $it['invoice_id'],
            'product_id' => 0,
            'product_name' => $it['description'] ?? '',
            'description' => $it['description'] ?? '',
            'gross_weight' => $rem,
            'gross_wt' => $rem,
            'original_gross_wt' => $orig,
            'stocked_gross_wt' => $stocked,
            'remaining_gross_wt' => $rem,
            'final_weight' => round((float) ($it['final_wt'] ?? 0) * $ratio, 4),
            'final_wt' => round((float) ($it['final_wt'] ?? 0) * $ratio, 4),
            'net_weight' => round((float) ($it['net_wt'] ?? 0) * $ratio, 4),
            'net_wt' => round((float) ($it['net_wt'] ?? 0) * $ratio, 4),
            'pure_weight' => round((float) ($it['pure_wt'] ?? 0) * $ratio, 4),
            'pure_wt' => round((float) ($it['pure_wt'] ?? 0) * $ratio, 4),
            'less_weight' => round($less_src * $ratio, 4),
            'less_wt' => round($less_src * $ratio, 4),
            'purity' => $it['purity'] ?? 0,
            'rate' => $it['rate'] ?? 0,
            'metal_rate' => isset($it['rate']) ? (float) $it['rate'] : 0,
            'amount' => round((float) ($it['amount'] ?? 0) * $ratio, 2),
            'quantity' => $it['quantity'] ?? 1,
            'making' => round((float) ($it['making'] ?? 0) * $ratio, 2),
            'making_amount' => round((float) ($it['making'] ?? 0) * $ratio, 2),
            'tax' => round((float) ($it['tax'] ?? 0) * $ratio, 2),
            'net_amt' => round((float) ($it['net_amt'] ?? 0) * $ratio, 2),
            'net_amt_wt' => $naw_scaled,
            'net_amt_with_tax' => $naw_scaled,
            'net_amt_tax' => $naw_scaled,
            'barcode' => $it['barcode'] ?? '',
            'design_no' => '',
            'article' => '',
            'metal_id' => isset($it['metal_id']) ? (int) $it['metal_id'] : 0,
        ];
    }
} else {
    foreach ($items_raw as $it) {
        $gw = (float) ($it['gross_wt'] ?? 0);
        $lw = isset($it['less_wt']) ? (float) $it['less_wt'] : 0;
        $naw_full = (float) ($it['net_amt_wt'] ?? 0);
        $net_line = (float) ($it['net_amt'] ?? 0);
        $tax_line = (float) ($it['tax'] ?? 0);
        $amt_line = (float) ($it['amount'] ?? 0);
        if ($naw_full <= 0) {
            $naw_full = ($net_line > 0 ? $net_line : $amt_line) + $tax_line;
        }
        $items[] = [
            'id' => $it['id'],
            'invoice_id' => $it['invoice_id'],
            'product_id' => 0,
            'product_name' => $it['description'] ?? '',
            'description' => $it['description'] ?? '',
            'gross_weight' => $gw,
            'gross_wt' => $gw,
            'final_weight' => (float) ($it['final_wt'] ?? 0),
            'final_wt' => (float) ($it['final_wt'] ?? 0),
            'net_weight' => (float) ($it['net_wt'] ?? 0),
            'net_wt' => (float) ($it['net_wt'] ?? 0),
            'pure_weight' => (float) ($it['pure_wt'] ?? 0),
            'pure_wt' => (float) ($it['pure_wt'] ?? 0),
            'less_weight' => $lw,
            'less_wt' => $lw,
            'purity' => $it['purity'] ?? 0,
            'rate' => $it['rate'] ?? 0,
            'metal_rate' => isset($it['rate']) ? (float) $it['rate'] : 0,
            'amount' => (float) ($it['amount'] ?? 0),
            'quantity' => $it['quantity'] ?? 1,
            'making' => (float) ($it['making'] ?? 0),
            'making_amount' => (float) ($it['making'] ?? 0),
            'tax' => (float) ($it['tax'] ?? 0),
            'net_amt' => (float) ($it['net_amt'] ?? 0),
            'net_amt_wt' => $naw_full,
            'net_amt_with_tax' => $naw_full,
            'net_amt_tax' => $naw_full,
            'barcode' => $it['barcode'] ?? '',
            'design_no' => '',
            'article' => '',
            'metal_id' => isset($it['metal_id']) ? (int) $it['metal_id'] : 0,
        ];
    }
}

// Get payments from scrap invoice payments (merge payment_details JSON for scrap modal round-trip)
$payments_raw = getList("SELECT * FROM tbl_old_jewelry_scrap_invoice_payments WHERE invoice_id = $order_id AND status = 1 ORDER BY id ASC");
$payments = [];
foreach ($payments_raw as $pr) {
    if (is_array($pr)) {
        $payments[] = $pr;
    }
}
require_once __DIR__ . '/../includes/auragold_payment_details_merge.php';
auragold_merge_payment_details_into_payments($payments);

echo json_encode([
    'status' => 'success',
    'order' => $order,
    'items' => $items,
    'payments' => $payments
]);
