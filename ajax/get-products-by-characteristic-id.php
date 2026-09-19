<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$characteristic_id = isset($_GET['characteristic_id']) ? (int)$_GET['characteristic_id'] : 0;
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

if ($characteristic_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid characteristic ID']);
    exit;
}

// Branch scope — same rules as stock-journal-create.php product opening balance block
$pc_branch_sql = '';
$branch_id = function_exists('auragold_effective_branch_id') ? (int) auragold_effective_branch_id() : 0;
if ($branch_id <= 0 && !empty($_SESSION['working_branch_id'])) {
    $branch_id = (int) $_SESSION['working_branch_id'];
} elseif ($branch_id <= 0 && !empty($_SESSION['branch_id'])) {
    $branch_id = (int) $_SESSION['branch_id'];
}
if ($branch_id > 0 && !empty($conn_master) && function_exists('getRecordMaster')) {
    $br_pc = getRecordMaster('SELECT main_branch_id FROM tbl_branches WHERE id = ' . (int) $branch_id . ' LIMIT 1');
    if ($br_pc && (int) ($br_pc['main_branch_id'] ?? 0) > 0) {
        $pc_branch_sql = ' AND pc.branch_id = ' . (int) $branch_id;
    } elseif ($br_pc) {
        $pc_branch_sql = ' AND (pc.branch_id = ' . (int) $branch_id . ' OR pc.branch_id IS NULL OR pc.branch_id = 0)';
    }
}

$product_sql = ($product_id > 0) ? ' AND pc.product_id = ' . (int) $product_id : '';

// Return the product line for this characteristic (product_opening barcode creation).
// Do not filter pc/p status here — stock-journal-create.php loads the same row without status filters.
$query = "
    SELECT
        p.id,
        p.name,
        p.alternate_name,
        p.article,
        p.category_id,
        pc.id as characteristic_id,
        pc.sku_code,
        pc.barcode,
        pc.carat,
        pc.opening_purity,
        pc.opening_weight,
        pc.final_weight,
        pc.rate,
        pc.value,
        pc.making_on,
        pc.diamond_category,
        pc.barcode_prefix,
        pc.barcode_digits,
        m.display_name as metal_name,
        m.id as metal_id
    FROM tbl_product_characteristics pc
    INNER JOIN tbl_products p ON pc.product_id = p.id
    INNER JOIN tbl_metal m ON pc.metal_id = m.id
    WHERE pc.id = $characteristic_id
      $product_sql
      $pc_branch_sql
    ORDER BY p.name ASC, pc.id ASC
    LIMIT 1
";

$products = getList($query);

if (empty($products)) {
    echo json_encode(['success' => false, 'message' => 'Product characteristic not found']);
    exit;
}

echo json_encode(['success' => true, 'products' => $products]);
