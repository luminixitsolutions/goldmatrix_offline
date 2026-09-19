<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$invoice_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : 0);

if ($invoice_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid invoice ID']);
    exit;
}

// Get invoice details
$invoice = getRecord("SELECT * FROM tbl_purchase_invoices WHERE id = $invoice_id");

if (!$invoice) {
    echo json_encode(['status' => 'error', 'message' => 'Invoice not found']);
    exit;
}

// Normalize order object so front-end always gets expected keys (order_no, against_of, supplier_name, etc.)
$order = [
    'id' => (int)($invoice['id'] ?? 0),
    'invoice_no' => $invoice['invoice_no'] ?? '',
    'order_no' => $invoice['invoice_no'] ?? '',
    'supplier_id' => (int)($invoice['supplier_id'] ?? 0),
    'customer_id' => (int)($invoice['supplier_id'] ?? 0),
    'supplier_name' => $invoice['supplier_name'] ?? '',
    'customer_name' => $invoice['supplier_name'] ?? '',
    'against_of' => $invoice['against_of'] ?? '',
    'currency' => $invoice['currency'] ?? 'AED',
    'ref_no' => $invoice['ref_no'] ?? '',
    'purchase_person' => $invoice['purchase_person'] ?? '',
    'sales_person' => $invoice['purchase_person'] ?? '',
    'invoice_date' => $invoice['invoice_date'] ?? '',
    'order_date' => $invoice['invoice_date'] ?? '',
    'due_date' => $invoice['due_date'] ?? '',
    'layaways_id' => $invoice['layaways_id'] ?? '',
    'fixing_type' => $invoice['fixing_type'] ?? 'Standard',
    'hedge_contract_ref' => $invoice['hedge_contract_ref'] ?? '',
    'hedge_date' => $invoice['hedge_date'] ?? '',
    'group_name' => $invoice['group_name'] ?? '',
    'comment' => $invoice['comment'] ?? '',
    'previous_balance' => (float)($invoice['previous_balance'] ?? 0),
    'previous_gold' => (float)($invoice['previous_gold'] ?? 0),
    'previous_silver' => (float)($invoice['previous_silver'] ?? 0),
    'previous_diamond' => (float)($invoice['previous_diamond'] ?? 0),
    'previous_gemstone' => (float)($invoice['previous_gemstone'] ?? 0),
    'subtotal' => (float)($invoice['subtotal'] ?? 0),
    'net_total' => (float)($invoice['net_total'] ?? $invoice['subtotal'] ?? 0),
    'grand_total' => (float)($invoice['grand_total'] ?? 0),
    'paid_amt' => (float)($invoice['paid_amt'] ?? 0),
    'balance_amt' => (float)($invoice['balance_amt'] ?? 0),
    'metal_amt' => (float)($invoice['metal_amt'] ?? 0),
    'round_off' => (float)($invoice['round_off'] ?? 0),
    'previous_balance_used_amt' => (float)($invoice['previous_balance_used_amt'] ?? 0),
    'discount_amt' => (float)($invoice['discount_amt'] ?? 0),
    'discount_percent' => (float)($invoice['discount_percent'] ?? 0),
    'payment_comments' => $invoice['payment_comments'] ?? '[]',
    'show_in_stock_journal' => (int)($invoice['show_in_stock_journal'] ?? 0),
];
// Include any extra columns from DB not listed above (e.g. use_previous_balance)
foreach ($invoice as $k => $v) {
    if (!array_key_exists($k, $order)) {
        $order[$k] = $v;
    }
}

// Get invoice items
$items = getList("SELECT * FROM tbl_purchase_invoice_items WHERE invoice_id = $invoice_id ORDER BY id ASC");
// Ensure making_amount is present for edit form (support making_amount, making, making_amt from DB)
foreach ($items as &$it) {
    $making = null;
    if (isset($it['making_amount']) && $it['making_amount'] !== '' && $it['making_amount'] !== null) {
        $making = (float)$it['making_amount'];
    } elseif (isset($it['making']) && $it['making'] !== '' && $it['making'] !== null) {
        $making = (float)$it['making'];
    } elseif (isset($it['making_amt']) && $it['making_amt'] !== '' && $it['making_amt'] !== null) {
        $making = (float)$it['making_amt'];
    } else {
        $making = 0.0;
    }
    $it['making_amount'] = $making;
    $it['making'] = $making;
}
unset($it);

// Get invoice payments
$payments = getList("SELECT * FROM tbl_purchase_invoice_payments WHERE invoice_id = $invoice_id ORDER BY id ASC");
require_once __DIR__ . '/../includes/auragold_payment_details_merge.php';
auragold_merge_payment_details_into_payments($payments);

echo json_encode([
    'status' => 'success',
    'order' => $order,
    'items' => $items,
    'payments' => $payments
]);
?>

