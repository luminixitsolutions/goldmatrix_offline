<?php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../includes/auragold-gst.php';

$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$characteristic_id = isset($_GET['characteristic_id']) ? (int)$_GET['characteristic_id'] : 0;
$metal_id_param = isset($_GET['metal_id']) ? (int)$_GET['metal_id'] : 0;

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit;
}

// Get product details
$product = getRecord("
    SELECT p.*, c.name as category_name
    FROM tbl_products p
    LEFT JOIN tbl_categories c ON p.category_id = c.id
    WHERE p.id = $product_id AND p.status = 1
");

if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Product not found']);
    exit;
}

if (isset($conn) && $conn instanceof mysqli) {
    require_once __DIR__ . '/../includes/auragold_product_branch_local_schema.php';
    auragold_ensure_product_branch_local_schema($conn);
}

$branch_res = 0;
if (!empty($_SESSION['working_branch_id'])) {
    $branch_res = (int)$_SESSION['working_branch_id'];
} elseif (!empty($_SESSION['branch_id'])) {
    $branch_res = (int)$_SESSION['branch_id'];
}

// tbl_product_tax is saved per branch (product opening); scope GST/VAT to login branch like product-opening.php
$tax_branch_id = $branch_res;
if ($tax_branch_id <= 0 && !empty($conn) && function_exists('getRecordMaster')) {
    $mbr_tax = @getRecordMaster('SELECT id FROM tbl_branches WHERE IFNULL(main_branch_id,0)=0 AND status = 1 ORDER BY id ASC LIMIT 1');
    if ($mbr_tax && !empty($mbr_tax['id'])) {
        $tax_branch_id = (int) $mbr_tax['id'];
    }
}
$tax_scope_id = $tax_branch_id > 0 ? $tax_branch_id : 0;

$sel_pc = "
    SELECT pc.*, m.display_name as metal_name, pc.barcode_prefix, pc.barcode_digits
    FROM tbl_product_characteristics pc
    LEFT JOIN tbl_metal m ON pc.metal_id = m.id
";

// Prefer product + metal (+ branch) so prefix/digits match the active tab (not another metal's RN row)
$characteristic = null;
if ($metal_id_param > 0) {
    if ($branch_res > 0) {
        $characteristic = getRecord($sel_pc . "
            WHERE pc.product_id = $product_id AND pc.metal_id = $metal_id_param AND pc.branch_id = $branch_res AND pc.status = 1
            ORDER BY pc.id DESC
            LIMIT 1
        ");
    }
    if (!$characteristic) {
        $characteristic = getRecord($sel_pc . "
            WHERE pc.product_id = $product_id AND pc.metal_id = $metal_id_param AND pc.status = 1
            ORDER BY pc.id DESC
            LIMIT 1
        ");
    }
}

if (!$characteristic && $characteristic_id > 0) {
    $characteristic = getRecord($sel_pc . "
        WHERE pc.id = $characteristic_id AND pc.product_id = $product_id AND pc.status = 1
    ");
}

if (!$characteristic) {
    $characteristic = getRecord($sel_pc . "
        WHERE pc.product_id = $product_id AND pc.status = 1
        ORDER BY pc.id ASC
        LIMIT 1
    ");
}

if (!$characteristic) {
    echo json_encode(['success' => false, 'message' => 'Product characteristics not found']);
    exit;
}

// Get product opening VAT/tax percentage (from tbl_product_tax) for product-wise tax %
$vat_value = null;
try {
    $vat_scope = function_exists('auragold_tbl_product_tax_branch_scope_sql')
        ? auragold_tbl_product_tax_branch_scope_sql($conn, (int) $product_id, $tax_scope_id)
        : '';
    $vat_tax = getRecord("
        SELECT pt.tax_value FROM tbl_product_tax pt
        WHERE pt.product_id = $product_id AND pt.tax_type = 'VAT' AND (pt.status = 1 OR pt.status IS NULL)
        $vat_scope
        ORDER BY pt.id DESC LIMIT 1
    ");
    $vat_value = $vat_tax && isset($vat_tax['tax_value']) ? (float)$vat_tax['tax_value'] : null;
} catch (Exception $e) {
    $vat_value = null;
}

// GST lines: raw rows for legacy fallback; scoped lines come from auragold_product_gst_tax_breakdown below.
$pid = (int) $product_id;
$product_tax_rows = [];
try {
    $pt_scope = function_exists('auragold_tbl_product_tax_branch_scope_sql')
        ? auragold_tbl_product_tax_branch_scope_sql($conn, $pid, $tax_scope_id)
        : '';
    $product_tax_rows = getList("
        SELECT pt.tax_type, pt.tax_value
        FROM tbl_product_tax pt
        WHERE pt.product_id = $pid AND (pt.status = 1 OR pt.status IS NULL)
        $pt_scope
    ");
    if (!is_array($product_tax_rows)) {
        $product_tax_rows = [];
    }
} catch (Exception $e) {
    $product_tax_rows = [];
}

$gst_local_percent = 0.0;
$gst_interstate_percent = 0.0;
$gst_tax_breakdown = ['local_state' => [], 'out_of_state' => []];

try {
    // Prefer Tax Master gst_supply_scope (join on name) + CGST/SGST/IGST heuristics — same as auragold-gst.php / sale invoice.
    if (function_exists('auragold_product_gst_tax_breakdown')) {
        $gst_tax_breakdown = auragold_product_gst_tax_breakdown($conn, $pid, $tax_scope_id);
    }
    if (function_exists('auragold_product_gst_percent_by_supply_scope')) {
        $scopes = auragold_product_gst_percent_by_supply_scope($conn, $pid, $tax_scope_id);
        $gst_local_percent = (float) ($scopes['local'] ?? 0);
        $gst_interstate_percent = (float) ($scopes['interstate'] ?? 0);
    }
} catch (Exception $e) {
    $gst_local_percent = 0.0;
    $gst_interstate_percent = 0.0;
    $gst_tax_breakdown = ['local_state' => [], 'out_of_state' => []];
}

// Legacy fallback when helpers return nothing but tbl_product_tax has GST-style rows (name-only heuristic).
if (
    ($gst_local_percent < 0.00001 && $gst_interstate_percent < 0.00001)
    && empty($gst_tax_breakdown['local_state'])
    && empty($gst_tax_breakdown['out_of_state'])
    && is_array($product_tax_rows)
) {
    try {
        foreach ($product_tax_rows as $t) {
            $taxTypeRaw = trim((string) ($t['tax_type'] ?? ''));
            if (strtoupper($taxTypeRaw) === 'VAT') {
                continue;
            }
            $percent = (float) ($t['tax_value'] ?? 0);
            $scope = '';
            $tn = function_exists('auragold_normalize_state_label') ? auragold_normalize_state_label($taxTypeRaw) : strtolower($taxTypeRaw);
            if ($tn === 'igst') {
                $scope = 'out_of_state';
            } elseif ($tn === 'cgst' || $tn === 'sgst') {
                $scope = 'local_state';
            }
            if ($scope !== 'local_state' && $scope !== 'out_of_state') {
                continue;
            }
            $lineItem = [
                'name' => $taxTypeRaw,
                'default_value' => $percent,
                'gst_supply_scope' => $scope,
            ];
            if ($scope === 'local_state') {
                $gst_local_percent += $percent;
                $gst_tax_breakdown['local_state'][] = $lineItem;
            } else {
                $gst_interstate_percent += $percent;
                $gst_tax_breakdown['out_of_state'][] = $lineItem;
            }
        }
    } catch (Exception $e2) {
        // keep zeros
    }
}

// API lines include gst_supply_scope so sale-invoice.js can sum local vs interstate from master, not 4+3+6 always.
$taxes_for_api = [];
try {
    foreach ($gst_tax_breakdown['local_state'] ?? [] as $x) {
        $taxes_for_api[] = [
            'tax_type' => (string) ($x['name'] ?? ''),
            'tax_value' => (float) ($x['default_value'] ?? 0),
            'gst_supply_scope' => 'local_state',
        ];
    }
    foreach ($gst_tax_breakdown['out_of_state'] ?? [] as $x) {
        $taxes_for_api[] = [
            'tax_type' => (string) ($x['name'] ?? ''),
            'tax_value' => (float) ($x['default_value'] ?? 0),
            'gst_supply_scope' => 'out_of_state',
        ];
    }
} catch (Exception $e) {
    $taxes_for_api = [];
}

// If breakdown produced no lines, expose raw non-VAT rows (JS will use CGST/SGST/IGST name heuristics).
if (count($taxes_for_api) === 0 && is_array($product_tax_rows)) {
    foreach ($product_tax_rows as $t) {
        $taxTypeRaw = trim((string) ($t['tax_type'] ?? ''));
        if ($taxTypeRaw === '' || strtoupper($taxTypeRaw) === 'VAT') {
            continue;
        }
        $taxes_for_api[] = [
            'tax_type' => $taxTypeRaw,
            'tax_value' => (float) ($t['tax_value'] ?? 0),
        ];
    }
}

$gst_invoice_slab_percent = ($gst_local_percent > 0.0 || $gst_interstate_percent > 0.0)
    ? max($gst_local_percent, $gst_interstate_percent)
    : 0.0;

// Effective % is chosen in JS from branch vs customer state; do not pick local vs interstate here.
$total_tax_percent = null;

// Do not use VAT % when product has GST component lines; VAT is often legacy and would mask GST.
if ($total_tax_percent === null && $vat_value !== null) {
    $gst_has_any = ($gst_local_percent > 0.00001 || $gst_interstate_percent > 0.00001);
    $bd_ls = isset($gst_tax_breakdown['local_state']) && is_array($gst_tax_breakdown['local_state']) ? count($gst_tax_breakdown['local_state']) : 0;
    $bd_os = isset($gst_tax_breakdown['out_of_state']) && is_array($gst_tax_breakdown['out_of_state']) ? count($gst_tax_breakdown['out_of_state']) : 0;
    if (!$gst_has_any && $bd_ls === 0 && $bd_os === 0) {
        $total_tax_percent = $vat_value;
    }
}

// Get saved barcode from characteristics, or generate fallback (only when branch setting allows barcodes)
$barcode_enabled = true;
if (isset($conn) && $conn instanceof mysqli && function_exists('auragold_product_branch_is_barcode_enabled')) {
    $barcode_enabled = auragold_product_branch_is_barcode_enabled($conn, (int) $product_id, $branch_res);
}

$barcode = '';
if ($barcode_enabled) {
    if (!empty($characteristic['barcode'])) {
        // Use saved barcode from product characteristics
        $barcode = $characteristic['barcode'];
    } elseif (!empty($characteristic['barcode_prefix']) && !empty($characteristic['barcode_digits'])) {
        // Next unique barcode for this prefix (same sequence as invoices / stock journal)
        $prefix = trim((string)$characteristic['barcode_prefix']);
        $digits = (int)$characteristic['barcode_digits'];
        if ($digits > 0 && $prefix !== '' && function_exists('generateBarcode')) {
            $barcode = generateBarcode($conn, $prefix, $digits, []);
        }
    } elseif (!empty($characteristic['sku_code'])) {
        // Fallback to SKU code
        $barcode = $characteristic['sku_code'];
    } else {
        // Last resort: generate with default format
        $barcode = 'RN' . str_pad($product_id, 5, '0', STR_PAD_LEFT);
    }
}

// Combine product and characteristic data
$result = [
    'id' => $product['id'],
    'name' => $product['name'],
    'alternate_name' => $product['alternate_name'],
    'article' => $product['article'],
    'barcode' => $barcode,
    'is_barcode' => $barcode_enabled ? 1 : 0,
    'characteristic_id' => $characteristic['id'],
    'carat' => $characteristic['carat'] ?: '',
    'opening_purity' => $characteristic['opening_purity'] ?: 0,
    'opening_qty' => (float) ($characteristic['opening_qty'] ?? 0),
    'metal_qty' => 1,
    'opening_weight' => $characteristic['opening_weight'] ?: 0,
    'final_weight' => $characteristic['final_weight'] ?: $characteristic['opening_weight'] ?: 0,
    'rate' => $characteristic['rate'] ?: 0,
    'value' => $characteristic['value'] ?: 0,
    'making_on' => $characteristic['making_on'] ?: '',
    'diamond_category' => $characteristic['diamond_category'] ?: '',
    'sku_code' => $characteristic['sku_code'] ?: '',
    'metal_name' => $characteristic['metal_name'] ?: '',
    'barcode_prefix' => $characteristic['barcode_prefix'] ?: 'RN',
    'barcode_digits' => (int)($characteristic['barcode_digits'] ?: 5) ?: 5,
    'vat_value' => $vat_value,
    'total_tax_percent' => $total_tax_percent,
    'gst_local_percent' => $gst_local_percent,
    'gst_interstate_percent' => $gst_interstate_percent,
    'gst_invoice_slab_percent' => $gst_invoice_slab_percent > 0 ? $gst_invoice_slab_percent : null,
    'gst_tax_breakdown' => $gst_tax_breakdown,
    'taxes' => $taxes_for_api,
];

echo json_encode(['success' => true, 'product' => $result]);
?>

