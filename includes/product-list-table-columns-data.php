<?php
/**
 * Shared Product List column definitions (invoice-style line grid).
 * Used by includes/product-list-table.php — edit here to update all voucher pages that include it.
 * Include with require_once only.
 */
$product_list_table_columns = [
    ['id', 'Id', 'basic'],
    ['rfid', 'RFIDCode', 'basic'],
    ['voucher-type', 'Voucher Type', 'basic'],
    ['photo', 'Photo', 'basic'],
    ['barcode', 'Barcode', 'basic'],
    ['design-no', 'Design No', 'basic'],
    ['huid', 'HUID No', 'basic'],
    ['category', 'Category', 'basic'],
    ['calculation', 'Calculation', 'basic'],
    ['product', 'Product', 'basic'],
    ['location', 'Location', 'basic'],
    ['pkt-wt', 'Pkt. Wt.', 'diamond'],
    ['pkt-less-wt', 'Pkt. Less Wt.', 'diamond'],
    ['gross-wt', 'Gross Wt.', 'diamond'],
    ['diamond-carat', 'Diamond Carat', 'diamond'],
    ['less-wt', 'D.Weight', 'diamond'],
    ['net-wt', 'Net Wt.', 'diamond'],
    ['quantity', 'Quantity', 'diamond'],
    ['rate', 'Rate', 'diamond'],
    ['amount', 'Amount', 'diamond'],
    ['metal-qty', 'Metal Qty', 'metal'],
    ['metal-weight', 'Weight', 'metal'],
    ['carat', 'Karat', 'metal'],
    ['purity', 'Purity', 'metal'],
    ['purity-wt', 'Purity Wt', 'metal'],
    ['gold-loss1', 'Gold Loss 1', 'metal'],
    ['gold-loss2', 'Gold Loss 2', 'metal'],
    ['metal-loss-value', 'Loss Value', 'metal'],
    ['wastage-per', 'Wastage Per.', 'metal'],
    ['wastage-wt', 'Wastage Wt.', 'metal'],
    ['metal-rate', 'Metal Rate', 'metal'],
    ['metal-value', 'Metal Value', 'metal'],
    ['metal-cost', 'Metal Cost', 'metal'],
    ['requested-purity', 'Requested Purity', 'reqfinal'],
    ['requested', 'Requested', 'reqfinal'],
    ['setting-charge', 'Setting Charge', 'reqfinal'],
    ['final-wt', 'Final Wt.', 'reqfinal'],
    ['alloy-wt', 'Alloy Wt.', 'reqfinal'],
    ['discount-type', 'Discount Type', 'disc'],
    ['discount-per', 'Discount Per.', 'disc'],
    ['discount-amount', 'Discount Amount', 'disc'],
    ['discount', 'Discount', 'disc'],
    ['making-type', 'Making Type', 'making'],
    ['making-rate', 'Making Rate', 'making'],
    ['making-discount-amt', 'Making Discount Amt.', 'making'],
    ['making-amount', 'Making Amount', 'making'],
    ['making-actual-value', 'Making Actual Value', 'making'],
    ['making-cost', 'Making Cost', 'making'],
    ['minimum', 'Minimum', 'making'],
    ['min-price', 'Minimum Price', 'making'],
    ['minimum-code', 'Minimum Code', 'making'],
    ['stone-charge-type', 'Stone Charge Type', 'stone'],
    ['stone-weight', 'Stone Weight', 'stone'],
    ['stone-rate', 'Stone Rate', 'stone'],
    ['stone-amount', 'Stone Amount', 'stone'],
    ['stone-cost', 'Stone Cost', 'stone'],
    ['diamond-amount', 'Diamond Amount', 'stone'],
    ['purchase-amount', 'Purchase Amount', 'amt'],
    ['sale-amount', 'Sale Amount', 'amt'],
    ['sale-amount-with', 'Sale Amount With Tax', 'amt'],
    ['net-amt', 'Net Amt', 'amt'],
    ['tax-type', 'Tax Type', 'amt'],
    ['tax-percent', 'Tax %', 'amt'],
    ['tax', 'Tax', 'amt'],
    ['other-charge-type', 'Other Charge Type', 'other'],
    ['other-weight', 'Other Weight', 'other'],
    ['other-rate', 'Other Rate', 'other'],
    ['other-info', 'Other Info', 'other'],
    ['other-amount', 'Other Amount', 'other'],
    ['hallmark-amount', 'Hallmark Amount', 'hall'],
    ['hallmark-rate', 'Hallmark Rate', 'hall'],
    ['net-amt-tax', 'Net Amt+Tax', 'netrev'],
    ['reverse', 'Reverse', 'netrev'],
];

$product_list_table_group_labels = [
    'basic' => 'Basic Information',
    'diamond' => 'Diamond group',
    'metal' => 'Metal group',
    'reqfinal' => 'Request & Final Wt.',
    'disc' => 'Discount',
    'making' => 'Making',
    'stone' => 'Stone group',
    'amt' => 'Amounts',
    'other' => 'Other Charge',
    'hall' => 'Hallmark',
    'netrev' => 'Net Amt+Tax / Reverse',
];

$product_list_table_column_keys = array_map(function ($c) {
    return $c[0];
}, $product_list_table_columns);

$product_list_table_column_group_map = [];
foreach ($product_list_table_columns as $_plcol) {
    $product_list_table_column_group_map[$_plcol[0]] = $_plcol[2];
}
