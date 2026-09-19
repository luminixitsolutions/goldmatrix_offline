<?php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../includes/auragold_product_metal_tab_match.php';

$search = isset($_GET['search']) ? esc($_GET['search']) : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$metal_id = isset($_GET['metal_id']) ? (int)$_GET['metal_id'] : 0;
$diamond_category = isset($_GET['diamond_category']) ? trim(esc($_GET['diamond_category'])) : '';

$eff_branch = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;
if ($eff_branch <= 0 && function_exists('auragold_effective_branch_id')) {
    $eff_branch = auragold_effective_branch_id();
}
if ($eff_branch > 0 && function_exists('auragold_resolve_branch_id_for_session')) {
    $eff_branch = auragold_resolve_branch_id_for_session($eff_branch);
}

$where_core = 'p.status = 1 AND pc.status = 1';
$branch_sql = '';
if ($eff_branch > 0 && !empty($conn_master) && function_exists('getRecordMaster')) {
    $br_pc = getRecordMaster('SELECT main_branch_id FROM tbl_branches WHERE id = ' . (int) $eff_branch . ' LIMIT 1');
    if ($br_pc && (int) ($br_pc['main_branch_id'] ?? 0) > 0) {
        $branch_sql = ' AND pc.branch_id = ' . (int) $eff_branch;
    } elseif ($br_pc) {
        $branch_sql = ' AND (pc.branch_id = ' . (int) $eff_branch . ' OR pc.branch_id IS NULL OR pc.branch_id = 0)';
    }
}

$metal_search_diamond = '';
if ($metal_id > 0) {
    $metal_search_diamond .= auragold_sql_pc_metal_for_product_list($metal_id);
}
if ($search != '') {
    $metal_search_diamond .= " AND (p.name LIKE '%$search%' OR p.alternate_name LIKE '%$search%' OR p.article LIKE '%$search%' OR pc.sku_code LIKE '%$search%')";
}
if ($diamond_category !== '') {
    $metal_search_diamond .= auragold_sql_pc_diamond_category_filter($diamond_category);
}

$sql_from = "
    SELECT DISTINCT
        p.id,
        p.name,
        p.alternate_name,
        p.article,
        p.category_id,
        pc.id as characteristic_id,
        pc.sku_code,
        pc.barcode,
        pc.opening_qty,
        pc.carat,
        pc.opening_purity,
        pc.opening_weight,
        pc.final_weight,
        pc.rate,
        pc.value,
        pc.barcode_prefix,
        pc.barcode_digits,
        pc.making_on,
        pc.diamond_category,
        m.display_name as metal_name,
        m.id as metal_id,
        (SELECT pt.tax_value FROM tbl_product_tax pt WHERE pt.product_id = p.id AND (pt.status = 1 OR pt.status IS NULL) ORDER BY pt.id DESC LIMIT 1) as vat_value,
        (SELECT COALESCE(SUM(pt.tax_value), 0) FROM tbl_product_tax pt WHERE pt.product_id = p.id AND (pt.status = 1 OR pt.status IS NULL)) as total_tax_percent
    FROM tbl_products p
    INNER JOIN tbl_product_characteristics pc ON p.id = pc.product_id
    INNER JOIN tbl_metal m ON pc.metal_id = m.id
";
$sql_tail = " ORDER BY p.name ASC, pc.id ASC LIMIT $limit ";

$where_clause = $where_core . $branch_sql . $metal_search_diamond;
$products = getList($sql_from . ' WHERE ' . $where_clause . $sql_tail);
// Same idea as ajax/get-products-by-metal.php: if branch filter yields nothing, retry without it (legacy / mismatched pc.branch_id).
if ((!is_array($products) || count($products) === 0) && $branch_sql !== '') {
    $where_clause = $where_core . $metal_search_diamond;
    $products = getList($sql_from . ' WHERE ' . $where_clause . $sql_tail);
}

// For each product with barcode, fetch metal_qty and metal_weight from stock (same as get-products-by-metal / get-product-by-barcode)
foreach ($products as &$prod) {
    $prod['metal_qty'] = null;
    $prod['metal_weight'] = null;
    $barcode = isset($prod['barcode']) ? trim($prod['barcode']) : '';
    if ($barcode === '') {
        $prod['metal_qty'] = (float)($prod['opening_qty'] ?? 0) ?: 1;
        $prod['metal_weight'] = (float)($prod['opening_weight'] ?? 0);
        continue;
    }
    $barcode_esc = esc($barcode);
    $sj = getRecord("SELECT sj.quantity, sj.gross_weight, sj.item_id FROM tbl_stock_journal sj WHERE sj.barcode = '$barcode_esc' AND sj.status = 'active' ORDER BY sj.id DESC LIMIT 1");
    if ($sj) {
        $q = (float)($sj['quantity'] ?? 0);
        $w = (float)($sj['gross_weight'] ?? 0);
        if (!empty($sj['item_id'])) {
            $pii = getRecord("SELECT metal_qty, metal_weight, gross_weight FROM tbl_purchase_invoice_items WHERE id = " . (int)$sj['item_id'] . " LIMIT 1");
            if ($pii) {
                if (isset($pii['metal_qty']) && $pii['metal_qty'] !== null && $pii['metal_qty'] !== '') $q = (float)$pii['metal_qty'];
                if (isset($pii['metal_weight']) && $pii['metal_weight'] !== null && $pii['metal_weight'] !== '') $w = (float)$pii['metal_weight'];
                elseif (isset($pii['gross_weight']) && $pii['gross_weight'] !== null && $pii['gross_weight'] !== '') $w = (float)$pii['gross_weight'];
            }
        }
        if ($q > 0) $prod['metal_qty'] = $q;
        if ($w > 0) $prod['metal_weight'] = $w;
    }
    if (($prod['metal_qty'] === null || $prod['metal_qty'] <= 0) || ($prod['metal_weight'] === null || $prod['metal_weight'] <= 0)) {
        $st = getRecord("SELECT current_qty, current_weight, opening_qty, opening_weight FROM tbl_stock WHERE barcode = '$barcode_esc' AND status = 1 ORDER BY id DESC LIMIT 1");
        if ($st) {
            if ($prod['metal_qty'] === null || $prod['metal_qty'] <= 0) {
                $cq = (float)($st['current_qty'] ?? 0);
                $prod['metal_qty'] = $cq > 0 ? $cq : ((float)($st['opening_qty'] ?? 0) ?: 1);
            }
            if ($prod['metal_weight'] === null || $prod['metal_weight'] <= 0) {
                $cw = (float)($st['current_weight'] ?? $st['opening_weight'] ?? 0);
                $prod['metal_weight'] = $cw > 0 ? $cw : (float)($st['opening_weight'] ?? 0);
            }
        }
    }
    if ($prod['metal_qty'] === null || $prod['metal_qty'] <= 0) $prod['metal_qty'] = (float)($prod['opening_qty'] ?? 0) ?: 1;
    if ($prod['metal_weight'] === null || $prod['metal_weight'] <= 0) $prod['metal_weight'] = (float)($prod['opening_weight'] ?? 0);
}
unset($prod);

echo json_encode(['success' => true, 'products' => $products]);
?>

