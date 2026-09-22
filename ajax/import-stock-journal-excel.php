<?php
/**
 * Bulk Excel import for stock journal (preview loads rows into the page; optional direct save when preview_only off).
 * POST: excel_file (xlsx), voucher=product_opening|purchase_invoice|sale_order,
 *       product_opening: product_id, characteristic_id
 *       purchase_invoice: item_id (required), optional product_id / characteristic_id (must match line)
 *       sale_order: preview_only (each row: Barcode and/or Product ID + Characteristic ID)
 */
session_start();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$voucher = isset($_POST['voucher']) ? trim((string) $_POST['voucher']) : '';
$item_id_imp = isset($_POST['item_id']) ? (int) $_POST['item_id'] : 0;
if ($voucher !== 'product_opening' && $voucher !== 'purchase_invoice' && $voucher !== 'sale_order') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid voucher type for Excel import']);
    exit;
}

$product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
$characteristic_id = isset($_POST['characteristic_id']) ? (int) $_POST['characteristic_id'] : 0;
$metal_id_sample = isset($_POST['metal_id']) ? (int) $_POST['metal_id'] : 0;

if (empty($_SESSION['Admin']['id']) && empty($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Session expired']);
    exit;
}

require_once __DIR__ . '/../config.php';

if ($voucher === 'sale_order') {
    $product_id = 0;
    $characteristic_id = 0;
} elseif ($voucher === 'purchase_invoice') {
    if ($item_id_imp <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'item_id is required for purchase invoice Excel import']);
        exit;
    }
    $piiImp = getRecord('SELECT product_id, product_characteristic_id FROM tbl_purchase_invoice_items WHERE id = ' . $item_id_imp . ' LIMIT 1');
    if (!$piiImp) {
        echo json_encode(['status' => 'error', 'message' => 'Purchase invoice line not found']);
        exit;
    }
    $dbPid = (int) ($piiImp['product_id'] ?? 0);
    $dbCid = (int) ($piiImp['product_characteristic_id'] ?? 0);
    if ($product_id > 0 && $dbPid > 0 && $product_id !== $dbPid) {
        echo json_encode(['status' => 'error', 'message' => 'product_id does not match this invoice line']);
        exit;
    }
    if ($characteristic_id > 0 && $dbCid > 0 && $characteristic_id !== $dbCid) {
        echo json_encode(['status' => 'error', 'message' => 'characteristic_id does not match this invoice line']);
        exit;
    }
    $product_id = $dbPid > 0 ? $dbPid : $product_id;
    $characteristic_id = $dbCid > 0 ? $dbCid : $characteristic_id;
} elseif ($voucher !== 'sale_order' && ($product_id <= 0 || $characteristic_id <= 0)) {
    echo json_encode(['status' => 'error', 'message' => 'product_id and characteristic_id are required']);
    exit;
}

if ($voucher !== 'sale_order' && ($product_id <= 0 || $characteristic_id <= 0)) {
    echo json_encode(['status' => 'error', 'message' => 'Could not resolve product_id and characteristic_id for import']);
    exit;
}

@ini_set('display_errors', '1');
@ini_set('display_startup_errors', '1');
@error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/stock_journal_excel_import.php';
require_once __DIR__ . '/../includes/auragold_excel_sample_columns.php';

$preview_only = $voucher === 'sale_order' || (
    !empty($_POST['preview_only']) && (
        (string) $_POST['preview_only'] === '1'
        || strcasecmp((string) $_POST['preview_only'], 'true') === 0
        || strcasecmp((string) $_POST['preview_only'], 'yes') === 0
    )
);

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

$uploadDirStock = dirname(__DIR__) . '/uploads/stock';
if (!is_dir($uploadDirStock)) {
    @mkdir($uploadDirStock, 0775, true);
}

if (empty($_FILES['excel_file']['tmp_name']) || !is_uploaded_file($_FILES['excel_file']['tmp_name'])) {
    echo json_encode(['status' => 'error', 'message' => 'Please upload an Excel file (.xlsx)']);
    exit;
}

$ext = strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['xlsx', 'xls'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Only .xlsx or .xls files are supported']);
    exit;
}

// .xlsx is a ZIP package; PhpSpreadsheet needs ext-zip (ZipArchive).
if ($ext === 'xlsx' && !class_exists('ZipArchive', false)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Your PHP build does not load the zip extension, so .xlsx cannot be read. Fix: open php.ini for the PHP version Laragon uses, uncomment `extension=zip`, save, then restart Apache (Stop All / Start All). Workaround: export the sheet as Excel 97-2003 (.xls) and upload that file.',
    ]);
    exit;
}

@set_time_limit(0);
@ini_set('memory_limit', '512M');

$pc = null;
$product_name = '';
if ($voucher !== 'sale_order') {
    $pcSelect = "id, product_id, barcode_prefix, barcode_digits, opening_purity";
    if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_product_characteristics', 'metal_id')) {
        $pcSelect .= ", metal_id";
    }
    $pc = getRecord("SELECT $pcSelect FROM tbl_product_characteristics WHERE id = $characteristic_id AND product_id = $product_id AND status = 1 LIMIT 1");
    if (!$pc) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid product / characteristic combination']);
        exit;
    }
    $prod = getRecord("SELECT id, name FROM tbl_products WHERE id = $product_id LIMIT 1");
    $product_name = $prod ? trim((string) ($prod['name'] ?? '')) : '';
    if ($metal_id_sample <= 0 && !empty($pc['metal_id'])) {
        $metal_id_sample = (int) $pc['metal_id'];
    }
}

$tmpPath = $_FILES['excel_file']['tmp_name'];

try {
    $spreadsheet = IOFactory::load($tmpPath);
} catch (Throwable $e) {
    $em = $e->getMessage();
    if ($ext === 'xlsx' && stripos($em, 'ZipArchive') !== false) {
        $em = 'PHP zip extension is required for .xlsx. Enable extension=zip in php.ini and restart the web server, or use an .xls file instead.';
    } else {
        $em = 'Could not read Excel: ' . $em;
    }
    echo json_encode(['status' => 'error', 'message' => $em]);
    exit;
}

$sheet = $spreadsheet->getActiveSheet();
$highestRow = (int) $sheet->getHighestDataRow();
try {
    $hiCol = $sheet->getHighestDataColumn();
    $highestCol = Coordinate::columnIndexFromString($hiCol);
} catch (Throwable $e) {
    echo json_encode(['status' => 'error', 'message' => 'Could not read sheet columns (empty or invalid layout).']);
    exit;
}
if ($highestCol < 1) {
    echo json_encode(['status' => 'error', 'message' => 'Sheet has no column range to import.']);
    exit;
}

if ($highestRow < 2) {
    echo json_encode(['status' => 'error', 'message' => 'Excel has no data rows']);
    exit;
}

if ($highestRow > 5000) {
    echo json_encode(['status' => 'error', 'message' => 'Maximum 5000 rows per upload']);
    exit;
}

$headers = [];
for ($c = 1; $c <= $highestCol; $c++) {
    $headers[$c] = (string) auragold_sj_excel_ws_cell($sheet, $c, 1)->getValue();
}
$map = auragold_sj_excel_map_headers($headers);
$col = array_merge($map, auragold_sj_excel_map_stock_journals_extended($headers, $metal_id_sample));
$hasQ = !empty($map['quantity']) || !empty($col['qty_metal']) || !empty($col['qty_line']);
$hasW = !empty($map['gross_weight']) || !empty($col['gross_diamond']) || !empty($col['weight_metal']);
if (!$hasQ && !$hasW) {
    echo json_encode(['status' => 'error', 'message' => 'Header row must include Metal Qty / Quantity and/or Gross Wt. / Weight columns.']);
    exit;
}
unset($hasQ, $hasW);

// Columns already mapped → remaining header columns shown as "Excel other" in Product List.
$excelUsedIndexes = [];
$markExcelUsed = static function ($idx) use (&$excelUsedIndexes) {
    $i = (int) $idx;
    if ($i > 0) {
        $excelUsedIndexes[$i] = true;
    }
};
foreach ($map as $__mk => $midx) {
    if ($__mk === 'image_columns' && is_array($midx)) {
        foreach ($midx as $__ic) {
            $markExcelUsed($__ic);
        }
        continue;
    }
    $markExcelUsed($midx);
}
foreach ($col as $__ck => $cidx) {
    if ($__ck === 'image_columns' && is_array($cidx)) {
        foreach ($cidx as $__ic2) {
            $markExcelUsed($__ic2);
        }
        continue;
    }
    $markExcelUsed($cidx);
}
$ef_branch_id = function_exists('auragold_settings_branch_id') ? (int) auragold_settings_branch_id() : 0;
$extra_field_defs_imp = auragold_excel_sample_extra_field_defs($conn, $metal_id_sample, $ef_branch_id);
$extra_field_col_map = auragold_sj_excel_map_extra_field_columns($headers, $extra_field_defs_imp);
foreach ($extra_field_col_map as $efColIdx) {
    $markExcelUsed((int) $efColIdx);
}
$excelExtraColIndexes = [];
for ($__eci = 1; $__eci <= $highestCol; $__eci++) {
    if (!empty($excelUsedIndexes[$__eci])) {
        continue;
    }
    if (trim((string) ($headers[$__eci] ?? '')) !== '') {
        $excelExtraColIndexes[] = $__eci;
    }
}

// Images: file-based drawings + embedded MemoryDrawing (PhpSpreadsheet 5); maps row => temp image paths for save.
$drawingsByRow = [];
try {
    $drawingsByRow = auragold_sj_excel_collect_drawings_by_row($sheet);
} catch (Throwable $e) {
    $drawingsByRow = [];
}

$openingPurity = $pc ? (float) ($pc['opening_purity'] ?? 0) : 1.0;
if ($openingPurity <= 0) {
    $openingPurity = 1.0;
}

$defaultExcelVoucherType = 'product_opening';
if ($voucher === 'purchase_invoice') {
    $defaultExcelVoucherType = 'purchase_invoice';
} elseif ($voucher === 'sale_order') {
    $defaultExcelVoucherType = 'sale_order';
}

$products = [];
$imageFilesByRow = []; // 0-based index in $products => array of temp paths for CURL
$drawingTempFiles = [];
$importSkippedNoQtyWeight = 0;
$importSkippedNoProduct = 0;

$sjF = static function (array $colMap, string $key, $sheet, int $row): float {
    if (empty($colMap[$key])) {
        return 0.0;
    }
    return auragold_sj_excel_sj_cell_f($sheet, (int) $colMap[$key], $row);
};
$sjS = static function (array $colMap, string $key, $sheet, int $row): string {
    if (empty($colMap[$key])) {
        return '';
    }
    return auragold_sj_excel_sj_cell_s($sheet, (int) $colMap[$key], $row);
};

for ($r = 2; $r <= $highestRow; $r++) {
    $cM = $col;
    $grossDiamond = $sjF($cM, 'gross_diamond', $sheet, $r);
    if ($grossDiamond <= 0) {
        $grossDiamond = $sjF($cM, 'gross_weight', $sheet, $r);
    }
    $weightMetal = $sjF($cM, 'weight_metal', $sheet, $r);
    $gw = $grossDiamond > 0 ? $grossDiamond : $weightMetal;
    if ($gw <= 0) {
        $gw = $sjF($cM, 'gross_weight', $sheet, $r);
    }
    $qtyM = $sjF($cM, 'qty_metal', $sheet, $r);
    $qtyL = $sjF($cM, 'qty_line', $sheet, $r);
    if (abs($qtyL) < 0.00001) {
        $qtyL = $sjF($cM, 'quantity', $sheet, $r);
    }
    $qty = (abs($qtyM) > 0.00001) ? $qtyM : $qtyL;
    if ($qty <= 0) {
        $qty = 0.0;
    }
    if ($qty <= 0 && $gw <= 0) {
        $importSkippedNoQtyWeight++;
        continue;
    }
    if ($qty <= 0) {
        $qty = 1.0;
    }
    $gw = auragold_sj_excel_round_weight($gw);
    $purity = $openingPurity;
    if (!empty($cM['purity']) || !empty($map['purity'])) {
        $pv = $sjF($cM, 'purity', $sheet, $r);
        if (abs($pv) > 0) {
            $purity = $pv;
        }
    }
    $less = 0.0;
    if (!empty($cM['less_diamond']) || !empty($map['less_weight'])) {
        if (!empty($cM['less_diamond'])) {
            $less = $sjF($cM, 'less_diamond', $sheet, $r);
        } elseif (!empty($map['less_weight'])) {
            $less = auragold_sj_excel_sj_cell_f($sheet, (int) $map['less_weight'], $r);
        }
    }
    $less = auragold_sj_excel_round_weight($less);
    $net_weight = auragold_sj_excel_round_weight((float) $sjF($cM, 'net_wt', $sheet, $r));
    if (abs($net_weight) < 0.00001) {
        $net_weight = auragold_sj_excel_round_weight(max(0.0, $gw - $less));
    }
    $purity_frac = $purity > 1 ? ($purity / 100) : $purity;
    $pure_weight = auragold_sj_excel_round_weight($net_weight * $purity_frac);
    $pwi = $sjF($cM, 'pure_wt_in', $sheet, $r);
    if (abs($pwi) > 0) {
        $pure_weight = auragold_sj_excel_round_weight($pwi);
    }
    $pname = $product_name;
    if ($sjS($cM, 'product_name_in', $sheet, $r) !== '') {
        $pname = $sjS($cM, 'product_name_in', $sheet, $r);
    } elseif (trim($pname) === '' && $product_name !== '') {
        $pname = $product_name;
    }
    $vt = $sjS($cM, 'voucher_type', $sheet, $r) !== '' ? $sjS($cM, 'voucher_type', $sheet, $r) : $defaultExcelVoucherType;
    $rowPid = $product_id;
    $rowCid = $characteristic_id;
    if ($voucher === 'sale_order') {
        if (!empty($col['product_id_in'])) {
            $rowPid = (int) $sjS($col, 'product_id_in', $sheet, $r);
        }
        if (!empty($col['characteristic_id_in'])) {
            $rowCid = (int) $sjS($col, 'characteristic_id_in', $sheet, $r);
        }
        $rowBc = !empty($map['barcode']) ? auragold_sj_excel_sj_cell_s($sheet, (int) $map['barcode'], $r) : '';
        [$rowPid, $rowCid] = auragold_sj_excel_resolve_sale_order_product_ids(
            $conn,
            $rowPid,
            $rowCid,
            $rowBc,
            $pname,
            $metal_id_sample
        );
        if ($rowPid <= 0 || $rowCid <= 0) {
            $importSkippedNoProduct++;
            continue;
        }
        if ($pname === '' && $rowPid > 0) {
            $prRow = getRecord('SELECT name FROM tbl_products WHERE id = ' . (int) $rowPid . ' LIMIT 1');
            if ($prRow && trim((string) ($prRow['name'] ?? '')) !== '') {
                $pname = trim((string) $prRow['name']);
            }
        }
    }
    $row = [
        'product_id' => $rowPid,
        'characteristic_id' => $rowCid,
        'product_name' => $pname,
        'code' => $sjS($cM, 'excel_id', $sheet, $r),
        'quantity' => $qty,
        'gross_weight' => $gw,
        'less_weight' => $less,
        'purity' => $purity,
        'final_weight' => auragold_sj_excel_round_weight(!empty($cM['final_wt']) ? $sjF($cM, 'final_wt', $sheet, $r) : $gw),
        'net_weight' => $net_weight,
        'pure_weight' => $pure_weight,
        'design_no' => $sjS($cM, 'design_no', $sheet, $r),
        'huid_no' => $sjS($cM, 'huid_no', $sheet, $r),
        'rfid_code' => $sjS($cM, 'rfid_code', $sheet, $r),
        'barcode' => !empty($map['barcode']) ? auragold_sj_excel_sj_cell_s($sheet, (int) $map['barcode'], $r) : $sjS($cM, 'barcode', $sheet, $r),
        'voucher_type' => $vt,
        'category' => $sjS($cM, 'category', $sheet, $r),
        'calculation' => $sjS($cM, 'calculation', $sheet, $r),
        'location' => $sjS($cM, 'location', $sheet, $r),
        'stone_weight' => 0.0,
        'karat' => '',
        'carat_id' => '',
        'rate' => $sjF($cM, 'rate', $sheet, $r),
        'amount' => $sjF($cM, 'amount', $sheet, $r),
        'net_amount' => $sjF($cM, 'net_amount', $sheet, $r),
        'net_amt_tax' => $sjF($cM, 'net_amt_tax', $sheet, $r),
        'tax_amount' => $sjF($cM, 'tax_amount', $sheet, $r),
        'making_amount' => $sjF($cM, 'making_amount', $sheet, $r),
        'pkt_wt' => $sjF($cM, 'pkt_wt', $sheet, $r),
    ];
    $karatRaw = !empty($cM['karat_carat']) ? $sjS($cM, 'karat_carat', $sheet, $r) : '';
    $stoneWt = !empty($cM['stone_weight_carat']) ? $sjF($cM, 'stone_weight_carat', $sheet, $r) : 0.0;
    $stoneRaw = !empty($cM['stone_weight_carat']) ? $sjS($cM, 'stone_weight_carat', $sheet, $r) : '';
    if ($karatRaw === '' && $stoneRaw !== '' && function_exists('auragold_sj_excel_value_looks_like_karat')
        && auragold_sj_excel_value_looks_like_karat($stoneRaw)) {
        $karatRaw = $stoneRaw;
        $stoneWt = 0.0;
    } elseif ($karatRaw !== '' && $stoneRaw === '' && function_exists('auragold_sj_excel_value_looks_like_karat')
        && !auragold_sj_excel_value_looks_like_karat($karatRaw) && is_numeric($karatRaw)) {
        $stoneWt = (float) $karatRaw;
        $karatRaw = '';
    }
    $karatResolved = function_exists('auragold_sj_excel_resolve_carat_id')
        ? auragold_sj_excel_resolve_carat_id($conn, $karatRaw)
        : ['karat' => $karatRaw, 'carat_id' => ''];
    $row['stone_weight'] = $stoneWt;
    $row['karat'] = $karatResolved['karat'];
    $row['carat_id'] = $karatResolved['carat_id'];
    $row['pkt_less_wt'] = $sjF($cM, 'pkt_less_wt', $sheet, $r);
    $row['gold_loss_1'] = $sjF($cM, 'gold_loss_1', $sheet, $r);
    $row['gold_loss_2'] = $sjF($cM, 'gold_loss_2', $sheet, $r);
    $row['wastage_per'] = $sjF($cM, 'wastage_per', $sheet, $r);
    $row['wastage_wt'] = $sjF($cM, 'wastage_wt', $sheet, $r);
    $row['alloy_wt'] = $sjF($cM, 'alloy_wt', $sheet, $r);
    $row['metal_rate'] = $sjF($cM, 'metal_rate', $sheet, $r);
    $row['metal_value'] = $sjF($cM, 'metal_value', $sheet, $r);
    $row['metal_cost'] = $sjF($cM, 'metal_cost', $sheet, $r);
    $lineRateExcel = (float) ($row['rate'] ?? 0);
    $metalRateExcel = (float) ($row['metal_rate'] ?? 0);
    if ($lineRateExcel <= 0.00001 && $metalRateExcel > 0) {
        $row['rate'] = $metalRateExcel;
        $lineRateExcel = $metalRateExcel;
    } elseif ($metalRateExcel <= 0.00001 && $lineRateExcel > 0) {
        $row['metal_rate'] = $lineRateExcel;
    }
    if ((float) ($row['metal_value'] ?? 0) <= 0.00001 && $lineRateExcel > 0 && $pure_weight > 0) {
        $row['metal_value'] = $pure_weight * $lineRateExcel;
    }
    $row['setting_charge'] = $sjF($cM, 'setting_charge', $sheet, $r);
    $row['requested_purity'] = $sjF($cM, 'requested_purity', $sheet, $r);
    $row['requested'] = $sjF($cM, 'requested', $sheet, $r);
    $row['discount'] = $sjF($cM, 'discount', $sheet, $r);
    $row['discount_type'] = $sjS($cM, 'discount_type', $sheet, $r);
    $row['discount_per'] = $sjF($cM, 'discount_per', $sheet, $r);
    $row['discount_amount'] = $sjF($cM, 'discount_amount', $sheet, $r);
    $row['making_type'] = $sjS($cM, 'making_type', $sheet, $r);
    $row['making_rate'] = $sjF($cM, 'making_rate', $sheet, $r);
    $row['making_discount_amt'] = $sjF($cM, 'making_discount_amt', $sheet, $r);
    $row['making_actual_value'] = $sjF($cM, 'making_actual_value', $sheet, $r);
    $row['making_cost'] = $sjF($cM, 'making_cost', $sheet, $r);
    $row['stone_charge_type'] = $sjS($cM, 'stone_charge_type', $sheet, $r);
    $row['stone_rate'] = $sjF($cM, 'stone_rate', $sheet, $r);
    $row['stone_amount'] = $sjF($cM, 'stone_amount', $sheet, $r);
    $row['stone_cost'] = $sjF($cM, 'stone_cost', $sheet, $r);
    $row['diamond_amount'] = $sjF($cM, 'diamond_amount', $sheet, $r);
    $row['purchase_amount'] = $sjF($cM, 'purchase_amount', $sheet, $r);
    $row['sale_percent'] = $sjF($cM, 'sale_percent', $sheet, $r);
    $row['sale_amount'] = $sjF($cM, 'sale_amount', $sheet, $r);
    $row['sale_amount_with'] = $sjF($cM, 'sale_amount_with', $sheet, $r);
    $row['reverse'] = $sjF($cM, 'reverse', $sheet, $r);
    $row['tax_percent'] = $sjF($cM, 'tax_percent', $sheet, $r);
    $row['other_charges'] = 0.0;
    $row['other_amount'] = $sjF($cM, 'other_amount', $sheet, $r);
    $row['other_charge_type'] = $sjS($cM, 'other_charge_type', $sheet, $r);
    $row['other_weight'] = $sjF($cM, 'other_weight', $sheet, $r);
    $row['other_rate'] = $sjF($cM, 'other_rate', $sheet, $r);
    $row['other_info'] = $sjS($cM, 'other_info', $sheet, $r);
    $row['hallmark_amount'] = $sjF($cM, 'hallmark_amount', $sheet, $r);
    $row['hallmark_rate'] = $sjF($cM, 'hallmark_rate', $sheet, $r);
    $row['tax_type'] = $sjS($cM, 'tax_type', $sheet, $r);
    $row['stone_charges'] = 0.0;
    $row['diamond_value'] = 0.0;
    $row['gemstone_value'] = 0.0;
    if (!empty($cM['minimum_price'])) {
        $row['minimum_price'] = $sjF($cM, 'minimum_price', $sheet, $r);
    } else {
        $row['minimum_price'] = null;
    }

    // Making Amount: Excel may only have Making Type + Rate ("Fix", 1000). Match modal formulas.
    $explicitMa = (float) ($row['making_amount'] ?? 0);
    $qtyR = (float) ($row['quantity'] ?? 1);
    if ($qtyR <= 0) {
        $qtyR = 1.0;
    }
    $pPur = (float) ($row['purity'] ?? 0);
    if ($pPur > 1) {
        $pPur /= 100.0;
    }
    if ($pPur <= 0) {
        $pPur = 1.0;
    }
    $nwtMk = (float) ($row['net_weight'] ?? 0);
    $rateMk = (float) ($row['rate'] ?? 0);
    $metalForMk = (float) ($row['metal_value'] ?? 0);
    if ($metalForMk <= 0.00001) {
        $metalForMk = $nwtMk * $pPur * $rateMk;
    }
    $mdiscMk = (float) ($row['making_discount_amt'] ?? 0);
    $row['making_amount'] = auragold_sj_excel_stock_journal_making_amount(
        $explicitMa,
        trim((string) ($row['making_type'] ?? 'Fix')),
        (float) ($row['making_rate'] ?? 0),
        $mdiscMk,
        $nwtMk,
        $qtyR,
        $metalForMk
    );

    // Fill Amount / Net / Purchase / Sale from each other (imitation rows often only fill Purchase or Sale).
    $compAmt = (float) ($row['metal_value'] ?? 0)
        + (float) ($row['making_amount'] ?? 0)
        + (float) ($row['stone_amount'] ?? 0)
        + (float) ($row['other_amount'] ?? 0)
        + (float) ($row['diamond_amount'] ?? 0);
    $purchaseAmt = (float) ($row['purchase_amount'] ?? 0);
    $salePct = (float) ($row['sale_percent'] ?? 0);
    $saleAmt = (float) ($row['sale_amount'] ?? 0);
    $saleWith = (float) ($row['sale_amount_with'] ?? 0);
    $reverseAmt = (float) ($row['reverse'] ?? 0);
    $lineAmt = (float) ($row['amount'] ?? 0);
    $netAmt = (float) ($row['net_amount'] ?? 0);
    $netTax = (float) ($row['net_amt_tax'] ?? 0);
    $taxAmt = (float) ($row['tax_amount'] ?? 0);
    $taxPct = (float) ($row['tax_percent'] ?? 0);
    if ($salePct > 0 && $purchaseAmt > 0 && $saleAmt <= 0.00001) {
        $saleAmt = $purchaseAmt + ($purchaseAmt * ($salePct / 100.0));
    }
    if ($lineAmt <= 0.00001 && $compAmt > 0.00001) {
        $lineAmt = $compAmt;
    }
    $fallbackCost = $purchaseAmt > 0.00001 ? $purchaseAmt
        : ($netAmt > 0.00001 ? $netAmt
        : ($lineAmt > 0.00001 ? $lineAmt
        : ($saleAmt > 0.00001 ? $saleAmt : $reverseAmt)));
    if ($purchaseAmt <= 0.00001 && $fallbackCost > 0.00001) {
        $purchaseAmt = $fallbackCost;
    }
    if ($lineAmt <= 0.00001 && $fallbackCost > 0.00001) {
        $lineAmt = $fallbackCost;
    }
    if ($netAmt <= 0.00001 && $fallbackCost > 0.00001) {
        $netAmt = $fallbackCost;
    }
    if ($saleAmt <= 0.00001 && $reverseAmt > 0.00001) {
        $saleAmt = $reverseAmt;
    }
    if ($taxAmt <= 0.00001 && $taxPct > 0 && $netAmt > 0) {
        $taxAmt = $netAmt * ($taxPct / 100.0);
    }
    if ($netTax <= 0.00001) {
        $netTax = $netAmt + $taxAmt;
    }
    if ($saleWith <= 0.00001 && $saleAmt > 0.00001) {
        $saleWith = $saleAmt + $taxAmt;
    }
    $row['purchase_amount'] = $purchaseAmt;
    $row['sale_percent'] = $salePct;
    $row['sale_amount'] = $saleAmt;
    $row['sale_amount_with'] = $saleWith;
    $row['amount'] = $lineAmt;
    $row['net_amount'] = $netAmt;
    $row['net_amt_tax'] = $netTax;
    $row['tax_amount'] = $taxAmt;
    if ((float) ($row['diamond_value'] ?? 0) <= 0.00001 && (float) ($row['diamond_amount'] ?? 0) > 0) {
        $row['diamond_value'] = (float) $row['diamond_amount'];
    }
    if ((float) ($row['stone_charges'] ?? 0) <= 0.00001 && (float) ($row['stone_amount'] ?? 0) > 0) {
        $row['stone_charges'] = (float) $row['stone_amount'];
    }

    $row['extra_fields'] = [];
    foreach ($extra_field_col_map as $efId => $efCol) {
        $cv = auragold_sj_excel_ws_cell($sheet, (int) $efCol, $r);
        try {
            $vx = $cv->getCalculatedValue();
        } catch (Throwable $__ef) {
            $vx = $cv->getValue();
        }
        if ($vx instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
            $vx = $vx->__toString();
        }
        if ($vx === null) {
            $vs = '';
        } elseif (is_float($vx) || is_int($vx)) {
            $vxF = (float) $vx;
            $vs = (is_nan($vxF) || is_infinite($vxF)) ? '' : trim((string) $vx);
        } else {
            $vs = trim(preg_replace('/\s+/', ' ', (string) $vx));
        }
        if ($vs !== '') {
            $row['extra_fields'][(string) $efId] = $vs;
        }
    }

    $extrasRow = [];
    foreach ($excelExtraColIndexes as $eci) {
        $hlRaw = trim((string) ($headers[$eci] ?? ''));
        if ($hlRaw === '') {
            continue;
        }
        $cv = auragold_sj_excel_ws_cell($sheet, $eci, $r);
        try {
            $vx = $cv->getCalculatedValue();
        } catch (Throwable $__exc) {
            $vx = $cv->getValue();
        }
        if ($vx instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
            $vx = $vx->__toString();
        }
        if ($vx === null) {
            $vs = '';
        } elseif (is_float($vx) || is_int($vx)) {
            $vxF = (float) $vx;
            $vs = (is_nan($vxF) || is_infinite($vxF)) ? '' : trim((string) $vx);
        } else {
            $vs = trim(preg_replace('/\s+/', ' ', (string) $vx));
        }
        $extrasRow[] = ['h' => $hlRaw, 'v' => $vs];
    }
    $row['excel_extra_columns'] = $extrasRow;

    $rowFileList = [];
    $tempRels = [];
    $imageColList = [];
    if (!empty($map['image_columns']) && is_array($map['image_columns'])) {
        foreach ($map['image_columns'] as $ic) {
            $ic = (int) $ic;
            if ($ic > 0) {
                $imageColList[] = $ic;
            }
        }
    } elseif (!empty($map['image'])) {
        $imageColList = [(int) $map['image']];
    }
    foreach ($imageColList as $imgIdx) {
        $cellImg = trim((string) auragold_sj_excel_ws_cell($sheet, $imgIdx, $r)->getValue());
        if ($cellImg === '') {
            continue;
        }
        $parsed = auragold_sj_excel_parse_image_cell($cellImg);
        if ($parsed) {
            $p = auragold_sj_excel_save_image_bytes($parsed[0], $parsed[1]);
            if ($p) {
                $full = dirname(__DIR__) . '/' . str_replace('/', DIRECTORY_SEPARATOR, $p);
                if (is_readable($full)) {
                    $rowFileList[] = $full;
                    $tempRels[] = $p;
                }
            }
            break;
        }
    }
    if (!empty($drawingsByRow[$r])) {
        foreach ($drawingsByRow[$r] as $p) {
            if (!is_readable($p)) {
                continue;
            }
            $rel = function_exists('auragold_sj_excel_copy_abs_to_temp_excel') ? auragold_sj_excel_copy_abs_to_temp_excel($p) : null;
            if ($rel) {
                $full2 = dirname(__DIR__) . '/' . str_replace('/', DIRECTORY_SEPARATOR, $rel);
                if (is_readable($full2)) {
                    $rowFileList[] = $full2;
                    $tempRels[] = $rel;
                }
            } else {
                $rowFileList[] = $p;
            }
            $drawingTempFiles[] = $p;
        }
    }
    $row['temp_image_paths'] = $tempRels;

    $pi = count($products);
    $products[] = $row;
    if (!empty($rowFileList)) {
        $imageFilesByRow[$pi] = $rowFileList;
    }
}

if (empty($products)) {
    $emptyMsg = 'No rows to import: each data row needs Metal Qty and/or Weight (or Gross Wt.) greater than zero. Images or RFID alone are not enough.';
    if ($importSkippedNoProduct > 0 && $importSkippedNoQtyWeight === 0) {
        $emptyMsg = 'No rows to import: could not match Product / Barcode / Product ID + Characteristic ID on any row. '
            . 'Use the exact Product name from Sample download (e.g. "RING - SILVER"), or fill Product ID and Characteristic ID columns. '
            . 'If products are for another metal (e.g. Silver rows while Gold tab is open), the metal suffix in the Product column is used automatically.';
    } elseif ($importSkippedNoProduct > 0 && $importSkippedNoQtyWeight > 0) {
        $emptyMsg = 'No rows to import. '
            . $importSkippedNoQtyWeight . ' row(s) had no Metal Qty / Weight / Gross Wt., and '
            . $importSkippedNoProduct . ' row(s) could not be matched to a product.';
    }
    echo json_encode([
        'status' => 'error',
        'message' => $emptyMsg,
    ]);
    exit;
}

$floatKeys = [
    'quantity', 'gross_weight', 'less_weight', 'purity', 'final_weight', 'net_weight', 'pure_weight',
    'rate', 'amount', 'net_amount', 'net_amt_tax', 'tax_amount', 'making_amount', 'pkt_wt', 'pkt_less_wt',
    'stone_weight', 'gold_loss_1', 'gold_loss_2', 'wastage_per', 'wastage_wt', 'alloy_wt', 'metal_rate', 'metal_value', 'metal_cost',
    'setting_charge', 'requested_purity', 'requested', 'discount', 'discount_per', 'discount_amount', 'making_rate', 'making_discount_amt', 'making_actual_value', 'making_cost',
    'stone_rate', 'stone_amount', 'stone_cost', 'diamond_amount', 'purchase_amount', 'sale_amount', 'sale_amount_with',
    'sale_percent', 'tax_percent',
    'reverse', 'other_amount', 'other_weight', 'other_rate', 'hallmark_amount', 'hallmark_rate', 'diamond_value', 'gemstone_value', 'stone_charges', 'other_charges',
];
foreach ($products as &$p) {
    foreach ($floatKeys as $fk) {
        if (!array_key_exists($fk, $p)) {
            continue;
        }
        $v = (float) $p[$fk];
        if (is_nan($v) || is_infinite($v)) {
            $v = 0.0;
        }
        $p[$fk] = $v;
    }
}
unset($p);

if (!empty($preview_only)) {
    $previewUsed = [];
    foreach ($products as &$p) {
        $bcRaw = trim((string) ($p['barcode'] ?? ''));
        if ($voucher === 'sale_order') {
            if ($bcRaw !== '' && in_array($bcRaw, $previewUsed, true)) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Barcode "' . $bcRaw . '" appears more than once in this Excel file.',
                ]);
                exit;
            }
            if ($bcRaw !== '') {
                $previewUsed[] = $bcRaw;
            }
            $cidSo = (int) ($p['characteristic_id'] ?? 0);
            $metal_id_so = 0;
            $metal_name_so = '';
            if ($cidSo > 0 && function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_product_characteristics', 'metal_id')) {
                $pcSo = getRecord('SELECT metal_id FROM tbl_product_characteristics WHERE id = ' . $cidSo . ' LIMIT 1');
                $metal_id_so = (int) ($pcSo['metal_id'] ?? 0);
            }
            if ($metal_id_so > 0) {
                $miSo = getRecord("SELECT display_name, system_name FROM tbl_metal WHERE id = $metal_id_so LIMIT 1");
                if ($miSo) {
                    $metal_name_so = trim((string) ($miSo['display_name'] ?? $miSo['system_name'] ?? ''));
                }
            }
            $p['metal_id'] = $metal_id_so;
            $p['metal_name'] = $metal_name_so;
            $p['barcode_prefix'] = '';
            $p['barcode_digits'] = 0;
            continue;
        }
        if ($bcRaw !== '') {
            if (!auragold_sj_excel_validate_barcode($conn, $bcRaw, $previewUsed)) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Barcode "' . $bcRaw . '" is duplicated in this Excel file or already exists in stock. Remove duplicates or use a new code.',
                ]);
                exit;
            }
            $p['barcode'] = $bcRaw;
            $previewUsed[] = $bcRaw;
        } else {
            $p['barcode'] = '';
        }
        $metal_id = $metal_id_sample > 0 ? $metal_id_sample : (int) ($pc['metal_id'] ?? 0);
        $metal_name = '';
        if ($metal_id > 0) {
            $mi = getRecord("SELECT display_name, system_name FROM tbl_metal WHERE id = $metal_id LIMIT 1");
            if ($mi) {
                $metal_name = trim((string) ($mi['display_name'] ?? $mi['system_name'] ?? ''));
            }
        }
        $p['metal_id'] = $metal_id;
        $p['metal_name'] = $metal_name;
        $p['barcode_prefix'] = trim((string) ($pc['barcode_prefix'] ?? ''));
        $p['barcode_digits'] = (int) ($pc['barcode_digits'] ?? 0);
        if ($p['barcode_digits'] < 1) {
            $p['barcode_digits'] = 5;
        }
    }
    unset($p);
    $_SESSION['excel_import_data'] = [
        'voucher' => $voucher,
        'item_id' => ($voucher === 'purchase_invoice') ? $item_id_imp : 0,
        'product_id' => $product_id,
        'characteristic_id' => $characteristic_id,
        'rows' => $products,
        'imported_at' => time(),
    ];
    $previewMessage = $voucher === 'sale_order'
        ? 'Excel loaded into the product list. Review rows, then click Add (Shift + A) to add lines to the order.'
        : 'Excel loaded into preview. Barcodes are assigned in the list; click Save Stock Journal to post to stock.';
    echo json_encode([
        'status' => 'success',
        'message' => $previewMessage,
        'products' => $products,
        'imported_rows' => count($products),
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$used = [];
foreach ($products as &$p) {
    $bc = trim((string) ($p['barcode'] ?? ''));
    if ($bc === '') {
        $p['barcode'] = auragold_sj_excel_generate_unique_barcode($conn, $p, $used);
        if (trim((string) ($p['barcode'] ?? '')) === '') {
            do {
                $fallback = 'BC' . date('YmdHis') . random_int(100, 999) . substr(bin2hex(random_bytes(2)), 0, 4);
            } while (
                in_array($fallback, $used, true)
                || (function_exists('auragold_barcode_exists_in_system') && auragold_barcode_exists_in_system($conn, $fallback))
            );
            $p['barcode'] = $fallback;
        }
    }
    if (isset($p['barcode']) && trim((string) $p['barcode']) !== '') {
        $used[] = trim((string) $p['barcode']);
    }
}
unset($p);

// Balance check: product opening uses characteristic opening_*; purchase invoice uses PI line totals vs item_id stock journal usage
$sumQty = 0.0;
$sumGw = 0.0;
foreach ($products as $p) {
    $sumQty += (float) ($p['quantity'] ?? 0);
    $sumGw += (float) ($p['gross_weight'] ?? 0);
}
if ($voucher === 'product_opening') {
    $sj_used = getRecord("
        SELECT COALESCE(SUM(sj.quantity), 0) AS used_qty, COALESCE(SUM(sj.gross_weight), 0) AS used_gross_wt
        FROM tbl_stock_journal sj
        WHERE sj.product_characteristic_id = $characteristic_id AND sj.status = 'active'
            AND (sj.item_id IS NULL OR sj.item_id = 0)
            AND (sj.comment IS NULL OR sj.comment NOT LIKE 'auragold_doc|src=pi|%')
    ");
    $used_q = (float) ($sj_used['used_qty'] ?? 0);
    $used_w = (float) ($sj_used['used_gross_wt'] ?? 0);
    $pcRow = getRecord("SELECT COALESCE(opening_qty,0) AS opening_qty, COALESCE(opening_weight,0) AS opening_weight FROM tbl_product_characteristics WHERE id = $characteristic_id LIMIT 1");
    $tot_q = (float) ($pcRow['opening_qty'] ?? 0);
    $tot_w = (float) ($pcRow['opening_weight'] ?? 0);
    if ($tot_q > 0 && $sumQty + $used_q > $tot_q + 0.0001) {
        echo json_encode(['status' => 'error', 'message' => 'Excel total quantity exceeds balance for this product opening']);
        exit;
    }
    if ($tot_w > 0 && $sumGw + $used_w > $tot_w + 0.0001) {
        echo json_encode(['status' => 'error', 'message' => 'Excel total gross weight exceeds balance for this product opening']);
        exit;
    }
} elseif ($voucher === 'purchase_invoice' && $item_id_imp > 0) {
    $piLine = getRecord('SELECT COALESCE(metal_qty, quantity, 0) AS tq, COALESCE(gross_weight, 0) AS tw FROM tbl_purchase_invoice_items WHERE id = ' . $item_id_imp . ' LIMIT 1');
    $tot_q_pi = (float) ($piLine['tq'] ?? 0);
    $tot_w_pi = (float) ($piLine['tw'] ?? 0);
    $sj_used_pi = getRecord("
        SELECT COALESCE(SUM(sj.quantity), 0) AS used_qty, COALESCE(SUM(sj.gross_weight), 0) AS used_gross_wt
        FROM tbl_stock_journal sj
        WHERE sj.item_id = $item_id_imp AND sj.status = 'active'
            AND (sj.comment IS NULL OR sj.comment NOT LIKE 'auragold_doc|src=pi|%')
    ");
    $used_q_pi = (float) ($sj_used_pi['used_qty'] ?? 0);
    $used_w_pi = (float) ($sj_used_pi['used_gross_wt'] ?? 0);
    if ($tot_q_pi > 0 && $sumQty + $used_q_pi > $tot_q_pi + 0.0001) {
        echo json_encode(['status' => 'error', 'message' => 'Excel total quantity exceeds balance for this purchase invoice line']);
        exit;
    }
    if ($tot_w_pi > 0 && $sumGw + $used_w_pi > $tot_w_pi + 0.0001) {
        echo json_encode(['status' => 'error', 'message' => 'Excel total gross weight exceeds balance for this purchase invoice line']);
        exit;
    }
}

$journal_date = isset($_POST['date']) && $_POST['date'] !== '' ? esc($_POST['date']) : date('Y-m-d');
$group_name = isset($_POST['group_name']) ? esc($_POST['group_name']) : '';
$comment = isset($_POST['comment']) ? esc($_POST['comment']) : '';

$payload = [
    'date' => $journal_date,
    'item_id' => ($voucher === 'purchase_invoice') ? $item_id_imp : 0,
    'products' => $products,
    'group_name' => $group_name,
    'comment' => $comment,
    'edit' => false,
    'product_id' => $product_id,
    'characteristic_id' => $characteristic_id,
];
// Internal save sends files via $_FILES; drop JSON temp paths to avoid double upload with save-stock-journal.php merge.
foreach ($payload['products'] as &$pr) {
    unset($pr['temp_image_paths']);
}
unset($pr);

$payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
if ($payloadJson === false || $payloadJson === '') {
    echo json_encode(['status' => 'error', 'message' => 'Could not build save data (check for invalid numbers or text in the sheet).']);
    exit;
}

if (!empty($drawingTempFiles)) {
    register_shutdown_function(function () use ($drawingTempFiles) {
        foreach ($drawingTempFiles as $tf) {
            if (is_file($tf)) {
                @unlink($tf);
            }
        }
    });
}

$savedPost = $_POST;
$savedFiles = $_FILES;
$savedMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$resp = '';
try {
    $_POST = ['data' => $payloadJson];
    $_FILES = [];
    foreach ($imageFilesByRow as $rowIdx => $paths) {
        foreach ($paths as $j => $path) {
            if (!is_readable($path)) {
                continue;
            }
            $sz = @filesize($path);
            if ($sz === false || $sz <= 0) {
                continue;
            }
            $mime = 'application/octet-stream';
            if (function_exists('mime_content_type')) {
                $mt = @mime_content_type($path);
                if (is_string($mt) && $mt !== '') {
                    $mime = $mt;
                }
            }
            $_FILES['images_' . $rowIdx . '_' . $j] = [
                'name' => basename($path),
                'type' => $mime,
                'tmp_name' => $path,
                'error' => UPLOAD_ERR_OK,
                'size' => (int) $sz,
            ];
        }
    }
    $_SERVER['REQUEST_METHOD'] = 'POST';

    if (!defined('AURAGOLD_STOCK_JOURNAL_INTERNAL_SAVE')) {
        define('AURAGOLD_STOCK_JOURNAL_INTERNAL_SAVE', true);
    }

    ob_start();
    require __DIR__ . '/save-stock-journal.php';
    $resp = ob_get_clean();
} catch (Throwable $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $resp = json_encode([
        'status' => 'error',
        'message' => 'Save failed: ' . $e->getMessage(),
    ]);
} finally {
    $_POST = $savedPost;
    $_FILES = $savedFiles;
    $_SERVER['REQUEST_METHOD'] = $savedMethod;
}

foreach ($drawingTempFiles as $tf) {
    if (is_file($tf)) {
        @unlink($tf);
    }
}

if (!is_string($resp) || trim($resp) === '') {
    echo json_encode(['status' => 'error', 'message' => 'Save produced no response. Check PHP error log.']);
    exit;
}

$decoded = json_decode($resp, true);
if (!is_array($decoded)) {
    $snippet = preg_replace('/\s+/', ' ', substr(strip_tags($resp), 0, 240));
    echo json_encode([
        'status' => 'error',
        'message' => 'Unexpected save response (not JSON). ' . ($snippet !== '' ? $snippet : 'Empty or invalid body.'),
    ]);
    exit;
}

$decoded['imported_rows'] = count($products);
if (($decoded['status'] ?? '') === 'success' || ($decoded['status'] ?? '') === true) {
    $decoded['message'] = !empty($decoded['message']) ? $decoded['message'] : 'Stock Imported Successfully';
}
echo json_encode($decoded);
