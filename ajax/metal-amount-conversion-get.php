<?php
/**
 * Single metal ↔ amount conversion row (for edit / open from Transaction Report).
 */
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/ensure_metal_amount_conversion.php';

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/auragold_require_login.php';
auragold_require_login_or_exit();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid id']);
    exit;
}

auragold_ensure_metal_amount_conversion_table($conn);
if (function_exists('auragold_ensure_mac_against_item_columns')) {
    auragold_ensure_mac_against_item_columns($conn);
}
$row = getRecord('SELECT * FROM tbl_metal_amount_conversions WHERE id = ' . (int) $id . ' AND status = 1 LIMIT 1');
if (!$row) {
    echo json_encode(['status' => 'error', 'message' => 'Record not found']);
    exit;
}

$out = [
    'id'            => (int) ($row['id'] ?? 0),
    'branch_id'     => isset($row['branch_id']) ? (int) $row['branch_id'] : 0,
    'customer_id'   => (int) ($row['customer_id'] ?? 0),
    'customer_name' => (string) ($row['customer_name'] ?? ''),
    'direction'     => (string) ($row['direction'] ?? ''),
    'metal_type'    => strtolower((string) ($row['metal_type'] ?? 'gold')),
    'metal_weight'  => (float) ($row['metal_weight'] ?? 0),
    'rate'          => (float) ($row['rate'] ?? 0),
    'amount'        => (float) ($row['amount'] ?? 0),
    'trans_date'    => (string) ($row['trans_date'] ?? ''),
    'trans_no'      => (string) ($row['trans_no'] ?? ''),
    'comment'       => (string) ($row['comment'] ?? ''),
    'product_id'    => isset($row['product_id']) ? (int) $row['product_id'] : 0,
    'product_characteristic_id' => isset($row['product_characteristic_id']) ? (int) $row['product_characteristic_id'] : 0,
    'against_item_label' => (string) ($row['against_item_label'] ?? ''),
];

echo json_encode(['status' => 'success', 'row' => $out]);
