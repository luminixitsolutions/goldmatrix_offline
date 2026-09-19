<?php
/**
 * Download sample .xlsx for bulk jewellery catalogue import.
 */
session_start();

if (empty($_SESSION['Admin']['id']) && empty($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Session expired. Please log in.';
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$categories = [];
if (function_exists('gas_tbl_exists') && gas_tbl_exists($conn, 'tbl_categories')) {
    foreach (getList('SELECT name FROM tbl_categories WHERE status = 1 ORDER BY name ASC LIMIT 500') as $c) {
        $n = trim((string) ($c['name'] ?? ''));
        if ($n !== '') {
            $categories[] = $n;
        }
    }
}

$metals = [];
if (function_exists('gas_tbl_exists') && gas_tbl_exists($conn, 'tbl_metal')) {
    $metals_sql = function_exists('auragold_master_list_sql_suffix') ? auragold_master_list_sql_suffix($conn, 'tbl_metal') : '';
    foreach (getList(
        "SELECT COALESCE(NULLIF(TRIM(display_name), ''), NULLIF(TRIM(system_name), ''), CONCAT('Metal ', id)) AS name
         FROM tbl_metal WHERE status = 1 $metals_sql ORDER BY id ASC"
    ) as $m) {
        $n = trim((string) ($m['name'] ?? ''));
        if ($n !== '') {
            $metals[] = $n;
        }
    }
}
$metals = array_values(array_unique($metals));

$products = [];
if (function_exists('gas_tbl_exists') && gas_tbl_exists($conn, 'tbl_products')) {
    foreach (getList('SELECT name FROM tbl_products WHERE status = 1 ORDER BY name ASC LIMIT 800') as $p) {
        $n = trim((string) ($p['name'] ?? ''));
        if ($n !== '') {
            $products[] = $n;
        }
    }
}

$headers = [
    'Metal',
    'Product',
    'Category',
    'Title',
    'Short Desc',
    'Full Desc',
    'Barcode',
    'Design No',
    'SKU',
    'Weight',
    'Amount',
    'Image URL',
];

$example1 = [
    !empty($metals) ? $metals[0] : 'Gold',
    !empty($products) ? $products[0] : 'Sample Ring',
    !empty($categories) ? $categories[0] : '',
    'Classic Gold Ring Design',
    '22K gold ring with floral pattern',
    'Detailed catalogue description for showroom display.',
    '',
    'JC-SAMPLE-001',
    'SKU-RING-001',
    '12.500',
    '85000',
    '',
];

$example2 = [
    count($metals) > 1 ? $metals[1] : (!empty($metals) ? $metals[0] : 'Silver'),
    count($products) > 1 ? $products[1] : (!empty($products) ? $products[0] : 'Sample Pendant'),
    count($categories) > 1 ? $categories[1] : (!empty($categories) ? $categories[0] : ''),
    'Silver Pendant Design',
    'Sterling silver pendant',
    '',
    '',
    'JC-SAMPLE-002',
    'SKU-PEND-002',
    '8.250',
    '42000',
    '',
];

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Catalogue');

$sheet->fromArray([$headers], null, 'A1', true);
$sheet->fromArray([$example1, $example2], null, 'A2', true);

$lastCol = Coordinate::stringFromColumnIndex(count($headers));
$sheet->getStyle('A1:' . $lastCol . '1')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '11294B']],
]);
$sheet->freezePane('A2');

$listSheetTitle = 'ListValues';
$listSheet = $spreadsheet->createSheet();
$listSheet->setTitle($listSheetTitle);

$listWrite = static function (Worksheet $ls, int $listCol, array $vals): int {
    $colLetter = Coordinate::stringFromColumnIndex($listCol);
    $i = 0;
    foreach ($vals as $v) {
        $i++;
        $ls->setCellValue($colLetter . $i, (string) $v);
    }

    return $i;
};

$listDefs = [
    'metal' => $metals,
    'product' => $products,
    'category' => $categories,
];

$listRangeMeta = [];
$nextListCol = 1;
foreach ($listDefs as $key => $vals) {
    $n = $listWrite($listSheet, $nextListCol, $vals);
    if ($n < 1) {
        continue;
    }
    $listRangeMeta[$key] = [
        'colLetter' => Coordinate::stringFromColumnIndex($nextListCol),
        'n' => $n,
    ];
    $nextListCol++;
}
$listSheet->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

$listFormula = static function (string $sheetTitle, string $colLetter, int $n): ?string {
    if ($n < 1) {
        return null;
    }

    return "'" . $sheetTitle . "'!" . '$' . $colLetter . '$1:$' . $colLetter . '$' . $n;
};

$applyListDv = static function (Worksheet $sh, int $dataCol1Based, ?string $formula1, int $maxDataRow): void {
    if ($formula1 === null) {
        return;
    }
    $colL = Coordinate::stringFromColumnIndex($dataCol1Based);
    $val = new DataValidation();
    $val->setType(DataValidation::TYPE_LIST);
    $val->setAllowBlank(true);
    $val->setShowDropDown(true);
    $val->setFormula1($formula1);
    $sh->setDataValidation($colL . '2:' . $colL . $maxDataRow, $val);
};

$headerToCol = static function (array $hdr, string $label): ?int {
    $i = array_search($label, $hdr, true);

    return $i === false ? null : ((int) $i + 1);
};

$maxDvRows = 5000;
$dvMap = [
    'Metal' => 'metal',
    'Product' => 'product',
    'Category' => 'category',
];
foreach ($dvMap as $headerLabel => $listKey) {
    $col1 = $headerToCol($headers, $headerLabel);
    if ($col1 === null || empty($listRangeMeta[$listKey])) {
        continue;
    }
    $meta = $listRangeMeta[$listKey];
    $f1 = $listFormula($listSheetTitle, (string) $meta['colLetter'], (int) $meta['n']);
    $applyListDv($sheet, $col1, $f1, $maxDvRows);
}

$info = $spreadsheet->createSheet();
$info->setTitle('Instructions');
$info->setCellValue('A1', 'Jewellery Catalogue — Bulk Excel Import');
$info->setCellValue('A3', '1. One row = one catalogue design. Add as many rows as you need.');
$info->setCellValue('A4', '2. Required: Title (or Product / Design No), Weight, Amount.');
$info->setCellValue('A5', '3. Short Desc is optional — if blank, Title is copied.');
$info->setCellValue('A6', '4. Design No is optional — auto-generated (JC-1, JC-2, …) when left blank.');
$info->setCellValue('A7', '5. Metal, Product, Category must match master names (use dropdowns where available).');
$info->setCellValue('A8', '6. Image URL: optional; separate multiple URLs with comma or semicolon.');
$info->setCellValue('A9', '7. Maximum 5000 data rows per upload.');
$info->getColumnDimension('A')->setWidth(100);

$spreadsheet->setActiveSheetIndex(0);

while (ob_get_level() > 0) {
    ob_end_clean();
}

$filename = 'jewellery_catalogue_import_sample_' . date('Y-m-d') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
