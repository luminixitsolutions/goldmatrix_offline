<?php
/**
 * Next sequential stock barcode from Product Opening rules (tbl_product_characteristics barcode_prefix / barcode_digits).
 * Scans tbl_stock + tbl_old_jewelry_stock so GB00001 is first free when nothing exists with prefix GB.
 *
 * Params: product_id, product_characteristic_id, metal_id, branch_id (optional overrides).
 * Legacy: prefix=RN&digits=5 still works if no product context.
 */
session_start();
require_once '../config.php';
require_once __DIR__ . '/../includes/next_product_stock_barcode.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$product_id = isset($_REQUEST['product_id']) ? (int) $_REQUEST['product_id'] : 0;
$characteristic_id = isset($_REQUEST['product_characteristic_id']) ? (int) $_REQUEST['product_characteristic_id'] : 0;
$metal_id = isset($_REQUEST['metal_id']) ? (int) $_REQUEST['metal_id'] : 0;
$branch_id = isset($_REQUEST['branch_id']) ? (int) $_REQUEST['branch_id'] : 0;

$legacy_prefix = isset($_REQUEST['prefix']) ? trim((string) $_REQUEST['prefix']) : '';
$legacy_digits = isset($_REQUEST['digits']) ? (int) $_REQUEST['digits'] : 0;

if ($product_id > 0 || $characteristic_id > 0) {
    $out = auragold_next_product_stock_barcode($conn, $product_id, $characteristic_id, $metal_id, $branch_id);
    echo json_encode(array_merge(['status' => 'success'], $out));
    exit;
}

if ($legacy_prefix !== '' && preg_match('/^[A-Za-z0-9]{1,15}$/', $legacy_prefix)) {
    $d = ($legacy_digits >= 1 && $legacy_digits <= 12) ? $legacy_digits : 5;
    $barcode = auragold_next_barcode_for_prefix($conn, $legacy_prefix, $d);
    echo json_encode([
        'status' => 'success',
        'barcode' => $barcode,
        'prefix' => $legacy_prefix,
        'digits' => $d,
    ]);
    exit;
}

$out = auragold_next_product_stock_barcode($conn, 0, 0, 0, 0);
echo json_encode(array_merge(['status' => 'success'], $out));
