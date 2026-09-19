<?php
/**
 * Export all product opening records to Excel (same columns as bulk import sample).
 */
session_start();

if (empty($_SESSION['Admin']['id']) && empty($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Session expired. Please log in.';
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_product_branch_local_schema.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

@set_time_limit(120);
@ini_set('memory_limit', '512M');

$auragold_working_branch_id = 0;
if (!empty($_SESSION['working_branch_id'])) {
    $auragold_working_branch_id = (int) $_SESSION['working_branch_id'];
} elseif (!empty($_SESSION['branch_id'])) {
    $auragold_working_branch_id = (int) $_SESSION['branch_id'];
}

$auragold_sub_branch_mode = false;
if ($auragold_working_branch_id > 0 && !empty($conn_master)) {
    $brCtx = getRecordMaster('SELECT main_branch_id FROM tbl_branches WHERE id = ' . $auragold_working_branch_id . ' LIMIT 1');
    if ($brCtx && (int) ($brCtx['main_branch_id'] ?? 0) > 0) {
        $auragold_sub_branch_mode = true;
    }
}

$pc_branch_filter = '';
if ($auragold_working_branch_id > 0) {
    if ($auragold_sub_branch_mode) {
        $pc_branch_filter = ' AND pc.branch_id = ' . (int) $auragold_working_branch_id;
    } else {
        $pc_branch_filter = ' AND (pc.branch_id = ' . (int) $auragold_working_branch_id . ' OR pc.branch_id IS NULL OR pc.branch_id = 0)';
    }
}

$product_scope = 'p.status = 1';
if ($auragold_sub_branch_mode && $auragold_working_branch_id > 0) {
    $sub_b = (int) $auragold_working_branch_id;
    $product_scope = 'p.status IN (0, 1) AND p.id IN (SELECT product_id FROM tbl_product_branches WHERE branch_id = ' . $sub_b . ')';
}

$has_pbs = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_product_branch_settings', 'category_id');
$cat_join = $has_pbs && $auragold_working_branch_id > 0
    ? 'LEFT JOIN tbl_product_branch_settings pbs ON pbs.product_id = p.id AND pbs.branch_id = ' . (int) $auragold_working_branch_id
    : '';
$cat_select = $has_pbs
    ? 'COALESCE(catpbs.name, catp.name, \'\') AS category_name, COALESCE(pbs.is_stock_item, p.is_stock_item, 0) AS show_in_stock'
    : 'COALESCE(catp.name, \'\') AS category_name, COALESCE(p.is_stock_item, 0) AS show_in_stock';
$cat_pbs_join = $has_pbs ? 'LEFT JOIN tbl_categories catpbs ON catpbs.id = pbs.category_id' : '';

$sql = "
    SELECT
        p.name AS product_name,
        p.article,
        p.alternate_name,
        $cat_select,
        m.display_name AS metal_name,
        pc.hsn,
        u.name AS unit_name,
        pc.sku_code,
        pc.making_on,
        pc.diamond_category,
        loc.name AS location_name,
        pc.carat,
        pc.discount,
        pc.purity_sale,
        pc.purity_purchase,
        pc.wastage_sale,
        pc.wastage_purchase,
        pc.wt_per_piece,
        pc.opening_weight,
        pc.opening_purity,
        pc.opening_qty,
        pc.rate,
        pc.barcode_prefix,
        pc.barcode_digits,
        pc.barcode,
        pc.serialized_barcode,
        pc.cut,
        pc.shape,
        pc.color,
        pc.clarity,
        pc.sieve,
        pc.size,
        pc.style_code
    FROM tbl_products p
    INNER JOIN tbl_product_characteristics pc ON pc.product_id = p.id AND pc.status = 1 AND pc.is_selected = 1
    $cat_join
    LEFT JOIN tbl_categories catp ON catp.id = p.category_id
    $cat_pbs_join
    LEFT JOIN tbl_metal m ON m.id = pc.metal_id
    LEFT JOIN tbl_unit u ON u.id = pc.unit_id
    LEFT JOIN tbl_location loc ON loc.id = pc.location_id
    WHERE $product_scope $pc_branch_filter
    ORDER BY p.name ASC, m.display_name ASC, pc.id ASC
    LIMIT 50000
";

$rows = getList($sql);
if (!is_array($rows)) {
    $rows = [];
}

$headers = [
    'Product Name', 'Article', 'Alternate Name', 'Category', 'Show In Stock', 'Metal',
    'HSN', 'Unit', 'SKU Code', 'Making On', 'Diamond Category', 'Location', 'Karat', 'Discount',
    'Purity Sale', 'Purity Purchase', 'Wastage Sale', 'Wastage Purchase', 'Wt Per Piece',
    'Opening Weight', 'Opening Purity', 'Opening Qty', 'Rate', 'Barcode Prefix', 'Barcode Digits',
    'Barcode', 'Serialized Barcode', 'Cut', 'Shape', 'Color', 'Clarity', 'Sieve', 'Size', 'Style Code',
];

$fmtNum = static function ($v): string {
    if ($v === null || $v === '') {
        return '';
    }
    if (!is_numeric($v)) {
        return trim((string) $v);
    }
    $f = (float) $v;
    if ($f == (int) $f) {
        return (string) (int) $f;
    }

    return rtrim(rtrim(number_format($f, 4, '.', ''), '0'), '.');
};

$dataRows = [];
foreach ($rows as $r) {
    $dataRows[] = [
        (string) ($r['product_name'] ?? ''),
        (string) ($r['article'] ?? ''),
        (string) ($r['alternate_name'] ?? ''),
        (string) ($r['category_name'] ?? ''),
        !empty($r['show_in_stock']) ? 'Yes' : 'No',
        (string) ($r['metal_name'] ?? ''),
        (string) ($r['hsn'] ?? ''),
        (string) ($r['unit_name'] ?? ''),
        (string) ($r['sku_code'] ?? ''),
        (string) ($r['making_on'] ?? ''),
        (string) ($r['diamond_category'] ?? ''),
        (string) ($r['location_name'] ?? ''),
        (string) ($r['carat'] ?? ''),
        $fmtNum($r['discount'] ?? ''),
        $fmtNum($r['purity_sale'] ?? ''),
        !empty($r['purity_purchase']) ? 'Yes' : 'No',
        $fmtNum($r['wastage_sale'] ?? ''),
        $fmtNum($r['wastage_purchase'] ?? ''),
        $fmtNum($r['wt_per_piece'] ?? ''),
        $fmtNum($r['opening_weight'] ?? ''),
        $fmtNum($r['opening_purity'] ?? ''),
        $fmtNum($r['opening_qty'] ?? ''),
        $fmtNum($r['rate'] ?? ''),
        (string) ($r['barcode_prefix'] ?? ''),
        $fmtNum($r['barcode_digits'] ?? ''),
        (string) ($r['barcode'] ?? ''),
        !empty($r['serialized_barcode']) ? 'Yes' : 'No',
        (string) ($r['cut'] ?? ''),
        (string) ($r['shape'] ?? ''),
        (string) ($r['color'] ?? ''),
        (string) ($r['clarity'] ?? ''),
        (string) ($r['sieve'] ?? ''),
        (string) ($r['size'] ?? ''),
        (string) ($r['style_code'] ?? ''),
    ];
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Products');
$sheet->fromArray([$headers], null, 'A1', true);
if (!empty($dataRows)) {
    $sheet->fromArray($dataRows, null, 'A2', true);
}
$lastCol = $sheet->getHighestColumn();
$sheet->getStyle('A1:' . $lastCol . '1')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '11294B']],
]);
$sheet->freezePane('A2');

while (ob_get_level() > 0) {
    ob_end_clean();
}

$filename = 'product_opening_export_' . date('Y-m-d_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
