<?php
/**
 * JSON for Old Jewellery Stock In modal — purchase invoice line (scrap payment on invoice).
 * Same shape as get-old-jewelry-scrap-item.php for client reuse.
 */
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$invoice_id = isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : 0;
$item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;

if ($invoice_id <= 0 || $item_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid invoice or item ID']);
    exit;
}

$pii = getRecord("
    SELECT pii.*, pi.invoice_no, pi.against_of, pi.supplier_name, pi.grand_total, pi.invoice_date
    FROM tbl_purchase_invoice_items pii
    INNER JOIN tbl_purchase_invoices pi ON pii.invoice_id = pi.id
    WHERE pii.id = $item_id AND pii.invoice_id = $invoice_id
    AND IFNULL(pii.active, 1) = 1
    LIMIT 1
");
if (!$pii) {
    echo json_encode(['status' => 'error', 'message' => 'Purchase item not found']);
    exit;
}

$scrap_pay = getRecord("
    SELECT id FROM tbl_purchase_invoice_payments
    WHERE invoice_id = $invoice_id AND LOWER(TRIM(payment_type)) = 'scrap' AND status = 1
    LIMIT 1
");
if (!$scrap_pay) {
    echo json_encode(['status' => 'error', 'message' => 'No scrap payment on this purchase invoice']);
    exit;
}

$metal_name = '';
$branch_id = 0;
$loc_name = '';
$metal_id_for_stock = 0;
$pc_id = isset($pii['product_characteristic_id']) ? (int)$pii['product_characteristic_id'] : 0;
if ($pc_id > 0) {
    $pc = getRecord("
        SELECT pc.metal_id, pc.branch_id, m.display_name AS metal_display
        FROM tbl_product_characteristics pc
        LEFT JOIN tbl_metal m ON pc.metal_id = m.id
        WHERE pc.id = $pc_id AND pc.status = 1
        LIMIT 1
    ");
    if ($pc) {
        $metal_name = trim((string)($pc['metal_display'] ?? ''));
        $branch_id = (int)($pc['branch_id'] ?? 0);
        $metal_id_for_stock = (int)($pc['metal_id'] ?? 0);
    }
}
$lid = isset($pii['location_id']) ? (int)$pii['location_id'] : 0;
if ($lid > 0) {
    $lr = getRecord("SELECT name FROM tbl_location WHERE id = $lid LIMIT 1");
    if ($lr) {
        $loc_name = trim((string)($lr['name'] ?? ''));
    }
}

$purity_wt = isset($pii['purity_weight']) ? (float)$pii['purity_weight'] : 0;

echo json_encode([
    'status' => 'success',
    'source' => 'purchase',
    'item' => [
        'id' => (int)$pii['id'],
        'invoice_id' => (int)$pii['invoice_id'],
        'barcode' => $pii['barcode'] ?? '',
        'description' => $pii['product_name'] ?? '',
        'gross_wt' => (float)($pii['gross_weight'] ?? 0),
        'final_wt' => (float)($pii['final_weight'] ?? 0),
        'net_wt' => (float)($pii['net_weight'] ?? 0),
        'pure_wt' => $purity_wt,
        'purity' => (float)($pii['purity'] ?? 0),
        'rate' => (float)($pii['rate'] ?? 0),
        'amount' => (float)($pii['amount'] ?? 0),
        'quantity' => (float)($pii['quantity'] ?? 1),
        'less_wt' => (float)($pii['less_weight'] ?? 0),
        'metal' => $metal_name,
        'metal_id' => $metal_id_for_stock,
        'product_id' => (int)($pii['product_id'] ?? 0),
        'product_characteristic_id' => $pc_id,
        'location' => $loc_name,
        'branch_id' => $branch_id,
    ],
    'invoice' => [
        'id' => $invoice_id,
        'invoice_no' => $pii['invoice_no'] ?? '',
        'customer_name' => $pii['supplier_name'] ?? '',
        'against_of' => $pii['against_of'] ?? '',
        'invoice_date' => $pii['invoice_date'] ?? '',
        'grand_total' => (float)($pii['grand_total'] ?? 0),
    ],
]);
