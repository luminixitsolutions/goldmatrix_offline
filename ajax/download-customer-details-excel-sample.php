<?php
/**
 * Download sample .xlsx for Customer Details import.
 */
session_start();

if (empty($_SESSION['Admin']['id']) && empty($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Session expired. Please log in.';
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_require_login.php';
require_once __DIR__ . '/../includes/customer_details_excel_template.php';
require_once __DIR__ . '/../vendor/autoload.php';

auragold_require_login_or_exit();

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$headers = auragold_customer_details_excel_headers();
$body = auragold_customer_details_excel_sample_rows($conn);
$listValues = auragold_customer_details_excel_list_values($conn);
$dropdownMap = auragold_customer_details_excel_dropdown_map();

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Customer Details');

$sheet->fromArray([$headers], null, 'A1', true);
if ($body !== []) {
    $sheet->fromArray($body, null, 'A2', true);
}

$lastCol = Coordinate::stringFromColumnIndex(count($headers));
$sheet->getStyle('A1:' . $lastCol . '1')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '11294B']],
]);
$sheet->freezePane('A2');
$sheet->getStyle('F2:F5000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
$sheet->getStyle('H2:H5000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

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

$listRangeMeta = [];
$nextListCol = 1;
foreach ($listValues as $key => $vals) {
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
foreach ($dropdownMap as $headerLabel => $listKey) {
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
$info->setCellValue('A1', 'Customer Details — Excel Import');
$info->setCellValue('A3', '1. Required columns: Name, Customer Type (for new customers). Mobile No should be unique.');
$info->setCellValue('A4', '2. Dropdown columns: Customer Type, Nationality, Country, State, City, Group, Sundry Debtors, KYC, AML, Bill to Bill, Billing/Shipping location fields.');
$info->setCellValue('A5', '3. Dates use dd-mm-yyyy format (e.g. 15-06-2024).');
$info->setCellValue('A6', '4. Existing customers (same Name) are updated. New names create a customer record.');
$info->setCellValue('A7', '5. Delete sample rows before import or replace with your data. Maximum 5000 rows per upload.');
$info->getColumnDimension('A')->setWidth(105);

$spreadsheet->setActiveSheetIndex(0);

while (ob_get_level() > 0) {
    ob_end_clean();
}

$filename = 'Customer_Details_Import_Sample_' . date('Y-m-d') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
(new Xlsx($spreadsheet))->save('php://output');
exit;
