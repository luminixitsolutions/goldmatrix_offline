<?php
/**
 * Download sample .xlsx for bulk product opening import (product master + characteristics).
 */
session_start();

if (empty($_SESSION['Admin']['id']) && empty($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Session expired. Please log in.';
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_carat_purity_for_schema.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$categories = [];
foreach (getList('SELECT name FROM tbl_categories WHERE status = 1 ORDER BY name ASC') as $c) {
    $n = trim((string) ($c['name'] ?? ''));
    if ($n !== '') {
        $categories[] = $n;
    }
}

$metals = [];
$metals_sql = function_exists('auragold_master_list_sql_suffix') ? auragold_master_list_sql_suffix($conn, 'tbl_metal') : '';
foreach (getList('SELECT display_name FROM tbl_metal WHERE status = 1 ' . $metals_sql . ' ORDER BY id ASC') as $m) {
    $n = trim((string) ($m['display_name'] ?? ''));
    if ($n !== '') {
        $metals[] = $n;
    }
}
$metals = array_values(array_unique($metals));

$branchIdForMasters = 0;
if (!empty($_SESSION['working_branch_id'])) {
    $branchIdForMasters = (int) $_SESSION['working_branch_id'];
} elseif (!empty($_SESSION['branch_id'])) {
    $branchIdForMasters = (int) $_SESSION['branch_id'];
} elseif (function_exists('auragold_effective_branch_id')) {
    $branchIdForMasters = (int) auragold_effective_branch_id();
}

$karats = [];
if (function_exists('auragold_get_carat_list')) {
    $caratRows = auragold_get_carat_list($conn, 'all', $branchIdForMasters);
    if (is_array($caratRows)) {
        foreach ($caratRows as $cr) {
            $n = trim((string) ($cr['name'] ?? ''));
            if ($n !== '') {
                $karats[] = $n;
            }
        }
    }
}
$karats = array_values(array_unique($karats));
if ($karats === []) {
    $karats = ['24K', '22K', '21K', '18K'];
}

$headers = [
    'Product Name',
    'Article',
    'Alternate Name',
    'Category',
    'Show In Stock',
    'Metal',
    'Barcode Digits',
    'Barcode Prefix',
    'HSN',
    'Karat',
    'Diamond Category',
];

$exampleGold = [
    'SAMPLE RING',
    'ART-001',
    '',
    !empty($categories) ? $categories[0] : '',
    'Yes',
    !empty($metals) ? $metals[0] : 'Gold',
    '5',
    'GD',
    '7113',
    in_array('22K', $karats, true) ? '22K' : ($karats[0] ?? ''),
    '',
];

$exampleSilver = $exampleGold;
$exampleSilver[5] = in_array('Silver', $metals, true) ? 'Silver' : (!empty($metals) ? $metals[min(1, count($metals) - 1)] : 'Silver');
$exampleSilver[7] = 'SV';

$examplePlatinum = $exampleGold;
$examplePlatinum[5] = in_array('Platinum', $metals, true) ? 'Platinum' : 'Platinum';
$examplePlatinum[7] = 'PL';

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Products');

$sheet->fromArray([$headers], null, 'A1', true);
$sheet->fromArray([$exampleGold, $exampleSilver, $examplePlatinum], null, 'A2', true);

$lastCol = Coordinate::stringFromColumnIndex(count($headers));
$sheet->getStyle('A1:' . $lastCol . '1')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '11294B']],
]);
$sheet->freezePane('A2');

// Hidden sheet for dropdown lists (range validation — fast; supports commas in labels).
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
    'category' => $categories,
    'yes_no' => ['Yes', 'No'],
    'metal' => $metals,
    'karat' => $karats,
    'diamond_category' => ['Diamonds', 'GemStones', 'Jewellery'],
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
    'Category' => 'category',
    'Show In Stock' => 'yes_no',
    'Metal' => 'metal',
    'Karat' => 'karat',
    'Diamond Category' => 'diamond_category',
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
$info->setCellValue('A1', 'Product Opening — Bulk Excel Import');
$info->setCellValue('A3', '1. One row = one metal for a product. Repeat the SAME Product Name on multiple rows for Gold + Silver + Platinum (see sample rows 2–4).');
$info->setCellValue('A4', '2. Required columns: Product Name, Metal.');
$info->setCellValue('A5', '3. Columns match the Product Opening form. Karat, Category, Metal, and Diamond Category columns have dropdown lists from Masters.');
$info->setCellValue('A6', '4. Each row must have a different Metal for the same product (no duplicate Gold + Gold).');
$info->setCellValue('A7', '5. Product names must be unique in the system. Existing names are skipped on import.');
$info->setCellValue('A8', '6. Maximum 5000 data rows per upload.');
$info->getColumnDimension('A')->setWidth(100);

$spreadsheet->setActiveSheetIndex(0);

while (ob_get_level() > 0) {
    ob_end_clean();
}

$filename = 'product_opening_import_sample_' . date('Y-m-d') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
