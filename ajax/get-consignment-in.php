<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$consignment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($consignment_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid consignment ID']);
    exit;
}

$consignment = getRecord("SELECT * FROM tbl_consignment_in WHERE id = $consignment_id");

if (!$consignment) {
    echo json_encode(['status' => 'error', 'message' => 'Consignment In not found']);
    exit;
}

$items = getList("SELECT * FROM tbl_consignment_in_items WHERE consignment_id = $consignment_id ORDER BY id ASC");

$order = [
    'id' => $consignment['id'],
    'order_no' => $consignment['consignment_no'],
    'invoice_no' => $consignment['consignment_no'],
    'consignment_no' => $consignment['consignment_no'],
    'consignment_out_id' => $consignment['consignment_out_id'],
    'customer_id' => $consignment['customer_id'],
    'supplier_id' => $consignment['customer_id'],
    'customer_name' => $consignment['customer_name'],
    'supplier_name' => $consignment['customer_name'],
    'order_date' => $consignment['consignment_date'],
    'invoice_date' => $consignment['consignment_date'],
    'consignment_date' => $consignment['consignment_date'],
    'ref_no' => $consignment['ref_no'],
    'against_of' => $consignment['against_of'],
    'currency' => $consignment['currency'],
    'fixing_type' => $consignment['fixing_type'],
    'sales_person' => $consignment['sales_person'],
    'purchase_person' => $consignment['sales_person'],
    'previous_balance' => $consignment['previous_balance'],
    'previous_gold' => $consignment['previous_gold'],
    'previous_silver' => $consignment['previous_silver'],
    'gross_total' => $consignment['gross_total'],
    'subtotal' => $consignment['gross_total'],
    'net_total' => $consignment['gross_total'],
    'discount_amount' => $consignment['discount_amount'],
    'discount_amt' => $consignment['discount_amount'],
    'tax_amount' => $consignment['tax_amount'],
    'grand_total' => $consignment['grand_total'],
    'paid_amt' => 0,
    'balance_amt' => 0,
    'metal_amt' => 0,
    'round_off' => 0,
    'previous_diamond' => 0,
    'previous_gemstone' => 0,
    'total_quantity' => $consignment['total_quantity'],
    'total_gross_weight' => $consignment['total_gross_weight'],
    'total_net_weight' => $consignment['total_net_weight'],
    'total_pure_weight' => $consignment['total_pure_weight'],
    'comment' => $consignment['comment'],
    'status' => $consignment['status']
];

$formatted_items = [];
foreach ($items as $item) {
    $formatted_items[] = [
        'id' => $item['id'],
        'consignment_out_item_id' => $item['consignment_out_item_id'],
        'product_id' => $item['product_id'],
        'characteristic_id' => $item['product_characteristic_id'],
        'product_characteristic_id' => $item['product_characteristic_id'],
        'barcode' => $item['barcode'],
        'product_name' => $item['product_name'],
        'design_no' => $item['design_no'],
        'huid_no' => $item['huid_no'],
        'category' => $item['category'],
        'calculation_mode' => $item['calculation_mode'],
        'location' => $item['location'],
        'metal_id' => $item['metal_id'],
        'carat' => $item['carat'],
        'quantity' => $item['quantity'],
        'gross_weight' => $item['gross_weight'],
        'less_weight' => $item['less_weight'],
        'net_weight' => $item['net_weight'],
        'purity' => $item['purity'],
        'purity_weight' => $item['purity_weight'],
        'wastage_percent' => $item['wastage_percent'],
        'wastage_weight' => $item['wastage_weight'],
        'final_weight' => $item['final_weight'],
        'pure_weight' => $item['pure_weight'],
        'rate' => $item['rate'],
        'metal_value' => $item['metal_value'],
        'amount' => $item['amount'],
        'making_type' => $item['making_type'],
        'making_rate' => $item['making_rate'],
        'making_amount' => $item['making_amount'],
        'stone_weight' => $item['stone_weight'],
        'stone_rate' => $item['stone_rate'],
        'stone_amount' => $item['stone_amount'],
        'diamond_amount' => $item['diamond_amount'],
        'other_amount' => $item['other_amount'],
        'discount_percent' => $item['discount_percent'],
        'discount_amount' => $item['discount_amount'],
        'tax_percent' => $item['tax_percent'],
        'tax_amount' => $item['tax_amount'],
        'net_amount' => $item['net_amount'],
        'net_amt_with_tax' => $item['net_amt_with_tax']
    ];
}

echo json_encode([
    'status' => 'success',
    'order' => $order,
    'items' => $formatted_items
]);
?>
