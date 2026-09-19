<?php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../includes/current_stock_balance.php';

header('Content-Type: application/json; charset=utf-8');

$product_id = isset($_REQUEST['product_id']) ? (int) $_REQUEST['product_id'] : 0;
$metal_id = isset($_REQUEST['metal_id']) ? (int) $_REQUEST['metal_id'] : 0;
$branch_id = isset($_REQUEST['branch_id']) ? (int) $_REQUEST['branch_id'] : 0;
$characteristic_id = isset($_REQUEST['characteristic_id']) ? (int) $_REQUEST['characteristic_id'] : 0;
$location_id = isset($_REQUEST['location_id']) ? (int) $_REQUEST['location_id'] : 0;

if ($product_id <= 0 || $metal_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'product_id and metal_id required']);
    exit;
}

if ($branch_id <= 0) {
    $branch_id = auragold_resolve_branch_id_for_stock_balance($conn, $product_id, $metal_id, $characteristic_id, $location_id);
}

if ($branch_id <= 0) {
    echo json_encode([
        'success' => true,
        'display_qty' => 0,
        'display_gross_weight' => 0,
        'display_pure_weight' => 0,
        'branch_name' => '',
        'branch_id' => 0,
        'note' => 'no_branch',
    ]);
    exit;
}

$row = auragold_get_current_stock_balance_row($conn, $product_id, $branch_id, $metal_id);

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'lookup failed']);
    exit;
}

echo json_encode([
    'success' => true,
    'branch_id' => (int) ($row['branch_id'] ?? $branch_id),
    'branch_name' => $row['branch_name'] ?? '',
    'display_qty' => (float) ($row['display_qty'] ?? 0),
    'display_gross_weight' => (float) ($row['display_gross_weight'] ?? 0),
    'display_pure_weight' => (float) ($row['display_pure_weight'] ?? 0),
]);
