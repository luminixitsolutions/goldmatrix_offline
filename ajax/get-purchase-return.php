<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$return_id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($_GET['return_id']) ? (int)$_GET['return_id'] : 0);

if ($return_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid return ID']);
    exit;
}

// Get return details
$return = getRecord("SELECT * FROM tbl_purchase_returns WHERE id = $return_id");

if (!$return) {
    echo json_encode(['status' => 'error', 'message' => 'Return not found']);
    exit;
}

// Normalize order object so front-end always gets expected keys (order_no, against_of, supplier_name, etc.)
$order = [
    'id' => (int)($return['id'] ?? 0),
    'return_no' => $return['return_no'] ?? '',
    'order_no' => $return['return_no'] ?? '',
    'invoice_no' => $return['return_no'] ?? '',
    'supplier_id' => (int)($return['supplier_id'] ?? 0),
    'customer_id' => (int)($return['supplier_id'] ?? 0),
    'supplier_name' => $return['supplier_name'] ?? '',
    'customer_name' => $return['supplier_name'] ?? '',
    'against_of' => $return['against_of'] ?? '',
    'against_type' => $return['against_type'] ?? '',
    'against_id' => (int)($return['against_id'] ?? 0),
    'currency' => $return['currency'] ?? 'AED',
    'ref_no' => $return['ref_no'] ?? '',
    'sales_person' => $return['sales_person'] ?? '',
    'return_date' => $return['return_date'] ?? '',
    'order_date' => $return['return_date'] ?? '',
    'invoice_date' => $return['return_date'] ?? '',
    'due_date' => $return['due_date'] ?? '',
    'layaways_id' => $return['layaways_id'] ?? '',
    'fixing_type' => $return['fixing_type'] ?? 'Standard',
    'ounce_rate' => (float)($return['ounce_rate'] ?? 0),
    'unfix_dmd_gms' => (int)($return['unfix_dmd_gms'] ?? 0),
    'unfix_metal' => (int)($return['unfix_metal'] ?? 0),
    'unfix' => (int)($return['unfix'] ?? 0),
    'group_name' => $return['group_name'] ?? '',
    'comment' => $return['comment'] ?? '',
    'previous_balance' => (float)($return['previous_balance'] ?? 0),
    'previous_gold' => (float)($return['previous_gold'] ?? 0),
    'previous_silver' => (float)($return['previous_silver'] ?? 0),
    'subtotal' => (float)($return['subtotal'] ?? 0),
    'net_total' => (float)($return['net_total'] ?? $return['subtotal'] ?? 0),
    'grand_total' => (float)($return['grand_total'] ?? 0),
    'paid_amt' => (float)($return['paid_amt'] ?? 0),
    'balance_amt' => (float)($return['balance_amt'] ?? 0),
    'metal_amt' => (float)($return['metal_amt'] ?? 0),
    'round_off' => (float)($return['round_off'] ?? 0),
];
// Include any extra columns from DB not listed above
foreach ($return as $k => $v) {
    if (!array_key_exists($k, $order)) {
        $order[$k] = $v;
    }
}

// Get return items - normalize invoice_id to return_id for compatibility
$items_raw = getList("SELECT * FROM tbl_purchase_return_items WHERE return_id = $return_id ORDER BY id ASC");
$items = [];
foreach ($items_raw as $item) {
    $item['invoice_id'] = $item['return_id'];
    $items[] = $item;
}

// Get return payments
$payments = getList("SELECT * FROM tbl_purchase_return_payments WHERE return_id = $return_id ORDER BY id ASC");
require_once __DIR__ . '/../includes/auragold_payment_details_merge.php';
auragold_merge_payment_details_into_payments($payments);

echo json_encode([
    'status' => 'success',
    'order' => $order,
    'items' => $items,
    'payments' => $payments
]);
?>
