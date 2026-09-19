<?php
/**
 * Product name / code / partial barcode search for POS (Enter-triggered from client).
 * Barcode scans should use ajax/get-product-by-barcode.php directly.
 */
require_once __DIR__ . '/../includes/session_init.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_require_login.php';
auragold_require_login_or_exit();
require_once __DIR__ . '/../includes/auragold_branch_data_scope.php';

header('Content-Type: application/json; charset=utf-8');

$q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
if (strlen($q) < 2) {
    echo json_encode(['status' => 'success', 'products' => []]);
    exit;
}

$q_esc = esc($q);
$suffix_pc = function_exists('auragold_master_list_sql_suffix') ? auragold_master_list_sql_suffix($conn, 'tbl_product_characteristics', 'pc.branch_id') : '';
$suffix_p = function_exists('auragold_master_list_sql_suffix') ? auragold_master_list_sql_suffix($conn, 'tbl_products', 'p.branch_id') : '';

$sql = "
    SELECT
        p.id AS product_id,
        p.name AS product_name,
        p.article,
        pc.id AS characteristic_id,
        TRIM(IFNULL(pc.barcode, '')) AS barcode,
        IFNULL(pc.rate, 0) AS rate,
        IFNULL(pc.opening_weight, 0) AS opening_weight,
        IFNULL(pc.opening_purity, 0) AS opening_purity,
        IFNULL(pc.final_weight, 0) AS final_weight,
        IFNULL(pc.metal_id, 0) AS metal_id,
        IFNULL(pc.diamond_category, '') AS diamond_category
    FROM tbl_products p
    INNER JOIN tbl_product_characteristics pc ON pc.product_id = p.id AND pc.status = 1
    WHERE p.status = 1
    $suffix_p
    $suffix_pc
    AND (
        p.name LIKE '%$q_esc%'
        OR IFNULL(p.article, '') LIKE '%$q_esc%'
        OR IFNULL(p.alternate_name, '') LIKE '%$q_esc%'
        OR IFNULL(pc.barcode, '') LIKE '%$q_esc%'
        OR IFNULL(pc.sku_code, '') LIKE '%$q_esc%'
    )
    ORDER BY p.name ASC, pc.id DESC
    LIMIT 25
";

$rows = function_exists('getList') ? getList($sql) : [];
if (!is_array($rows)) {
    $rows = [];
}

$out = [];
foreach ($rows as $r) {
    $pid = (int) ($r['product_id'] ?? 0);
    $gst = 0.0;
    if ($pid > 0 && function_exists('getRecord')) {
        $sum_tax = @getRecord("SELECT COALESCE(SUM(tax_value), 0) AS total FROM tbl_product_tax WHERE product_id = $pid AND (status = 1 OR status IS NULL)");
        if ($sum_tax && isset($sum_tax['total'])) {
            $gst = (float) $sum_tax['total'];
        }
    }
    $out[] = [
        'product_id' => $pid,
        'characteristic_id' => (int) ($r['characteristic_id'] ?? 0),
        'product_name' => (string) ($r['product_name'] ?? ''),
        'barcode' => (string) ($r['barcode'] ?? ''),
        'rate' => (float) ($r['rate'] ?? 0),
        'metal_id' => (int) ($r['metal_id'] ?? 0),
        'opening_weight' => (float) ($r['opening_weight'] ?? 0),
        'opening_purity' => (float) ($r['opening_purity'] ?? 0),
        'final_weight' => (float) ($r['final_weight'] ?? 0),
        'diamond_category' => trim((string) ($r['diamond_category'] ?? '')),
        'gst_percent' => $gst,
    ];
}

echo json_encode(['status' => 'success', 'products' => $out]);
