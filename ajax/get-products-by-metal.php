<?php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../includes/auragold_product_metal_tab_match.php';

$metal_id = isset($_GET['metal_id']) ? (int)$_GET['metal_id'] : 0;
$search = isset($_GET['search']) ? esc($_GET['search']) : '';
$diamond_category = isset($_GET['diamond_category']) ? trim(esc($_GET['diamond_category'])) : '';

if ($metal_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid metal ID']);
    exit;
}

$where_clause = 'p.status = 1 AND pc.status = 1' . auragold_sql_pc_metal_for_product_list($metal_id);
if ($search != '') {
    $where_clause .= " AND (p.name LIKE '%$search%' OR p.alternate_name LIKE '%$search%' OR p.article LIKE '%$search%' OR pc.sku_code LIKE '%$search%')";
}
if ($diamond_category !== '') {
    $where_clause .= auragold_sql_pc_diamond_category_filter($diamond_category);
}

$branch_filter = 0;
if (!empty($_SESSION['working_branch_id'])) {
    $branch_filter = (int)$_SESSION['working_branch_id'];
} elseif (!empty($_SESSION['branch_id'])) {
    $branch_filter = (int)$_SESSION['branch_id'];
}
$where_for_query = $where_clause;
if ($branch_filter > 0) {
    $where_for_query .= " AND pc.branch_id = $branch_filter ";
}

$query = "
    SELECT DISTINCT
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
        pc.opening_qty,
        pc.opening_weight,
        pc.final_weight,
        pc.rate,
        pc.value,
        pc.making_on,
        pc.diamond_category,
        pc.barcode_prefix,
        pc.barcode_digits,
        m.display_name as metal_name,
        m.id as metal_id,
        (SELECT pt.tax_value FROM tbl_product_tax pt WHERE pt.product_id = p.id AND (pt.status = 1 OR pt.status IS NULL) ORDER BY pt.id DESC LIMIT 1) as vat_value,
        (SELECT COALESCE(SUM(pt.tax_value), 0) FROM tbl_product_tax pt WHERE pt.product_id = p.id AND (pt.status = 1 OR pt.status IS NULL)) as total_tax_percent
    FROM tbl_products p
    INNER JOIN tbl_product_characteristics pc ON p.id = pc.product_id
    INNER JOIN tbl_metal m ON pc.metal_id = m.id
    WHERE $where_for_query
    ORDER BY p.name ASC, pc.id ASC
    LIMIT 100
";

$products = getList($query);
if ((!is_array($products) || count($products) === 0) && $branch_filter > 0) {
    $query_fallback = "
    SELECT DISTINCT
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
        pc.opening_qty,
        pc.opening_weight,
        pc.final_weight,
        pc.rate,
        pc.value,
        pc.making_on,
        pc.diamond_category,
        pc.barcode_prefix,
        pc.barcode_digits,
        m.display_name as metal_name,
        m.id as metal_id,
        (SELECT pt.tax_value FROM tbl_product_tax pt WHERE pt.product_id = p.id AND (pt.status = 1 OR pt.status IS NULL) ORDER BY pt.id DESC LIMIT 1) as vat_value,
        (SELECT COALESCE(SUM(pt.tax_value), 0) FROM tbl_product_tax pt WHERE pt.product_id = p.id AND (pt.status = 1 OR pt.status IS NULL)) as total_tax_percent
    FROM tbl_products p
    INNER JOIN tbl_product_characteristics pc ON p.id = pc.product_id
    INNER JOIN tbl_metal m ON pc.metal_id = m.id
    WHERE $where_clause
    ORDER BY p.name ASC, pc.id ASC
    LIMIT 100
";
    $products = getList($query_fallback);
}

// For each product with barcode, fetch metal_qty and metal_weight from stock journal then tbl_stock so modal row shows them
foreach ($products as &$prod) {
    $prod['metal_qty'] = null;
    $prod['metal_weight'] = null;
    $barcode = isset($prod['barcode']) ? trim($prod['barcode']) : '';
    if ($barcode === '') {
        // No barcode: use opening from characteristic
        $prod['metal_qty'] = (float)($prod['opening_qty'] ?? 0) ?: 1;
        $prod['metal_weight'] = (float)($prod['opening_weight'] ?? 0);
        continue;
    }
    $barcode_esc = esc($barcode);
    $sj = getRecord("SELECT sj.quantity, sj.gross_weight, sj.item_id FROM tbl_stock_journal sj WHERE sj.barcode = '$barcode_esc' AND sj.status = 'active' ORDER BY sj.id DESC LIMIT 1");
    if ($sj) {
        $q = (float)$sj['quantity'];
        $w = (float)$sj['gross_weight'];
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
    // Fallback to tbl_stock when no journal or journal has zero
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
    // If still no values, use opening from characteristic
    if ($prod['metal_qty'] === null || $prod['metal_qty'] <= 0) $prod['metal_qty'] = (float)($prod['opening_qty'] ?? 0) ?: 1;
    if ($prod['metal_weight'] === null || $prod['metal_weight'] <= 0) $prod['metal_weight'] = (float)($prod['opening_weight'] ?? 0);
}
unset($prod);

// Effective GST slab: max(CGST+SGST, IGST) — not SUM(all tbl_product_tax rows) when all three are enabled in product opening.
$branchForGst = 0;
if (!empty($_SESSION['working_branch_id'])) {
    $branchForGst = (int) $_SESSION['working_branch_id'];
} elseif (!empty($_SESSION['branch_id'])) {
    $branchForGst = (int) $_SESSION['branch_id'];
}
if (is_array($products) && $branchForGst >= 0 && !empty($conn) && is_file(__DIR__ . '/../includes/auragold-gst.php')) {
    require_once __DIR__ . '/../includes/auragold-gst.php';
    if (function_exists('auragold_product_gst_percent_by_supply_scope')) {
        foreach ($products as &$gprod) {
            $pidG = (int) ($gprod['id'] ?? 0);
            if ($pidG <= 0) {
                continue;
            }
            $g = auragold_product_gst_percent_by_supply_scope($conn, $pidG, $branchForGst > 0 ? $branchForGst : null);
            $lo = (float) ($g['local'] ?? 0);
            $iG = (float) ($g['interstate'] ?? 0);
            $gprod['gst_local_percent'] = $lo;
            $gprod['gst_interstate_percent'] = $iG;
            $m = (float) max($lo, $iG);
            if ($m > 0) {
                $gprod['total_tax_percent'] = $m;
            }
        }
        unset($gprod);
    }
}

echo json_encode(['success' => true, 'products' => $products]);
?>

