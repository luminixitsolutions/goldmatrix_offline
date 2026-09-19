<?php
/**
 * Download sample .xlsx for Account Ledger import (opening balances + metal opening).
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
require_once __DIR__ . '/../includes/account_ledger_excel_template.php';
require_once __DIR__ . '/../vendor/autoload.php';

auragold_require_login_or_exit();

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$branchId = auragold_account_ledger_excel_login_branch_id(0);

$headers = auragold_account_ledger_excel_headers();
$body = auragold_account_ledger_excel_sample_rows($conn, $branchId);
$sundryNames = auragold_sundry_debtors_names_list();
$branchNames = auragold_account_ledger_excel_branch_names($conn, $branchId);
$crdrList = ['Dr', 'Cr'];

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Account Ledger');

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
$sheet->getStyle('B2:B5000')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);

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
    'sundry' => $sundryNames,
    'branch' => $branchNames,
    'crdr'   => $crdrList,
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
    'Sundry Debtors' => 'sundry',
    'Branch Name'    => 'branch',
    'Cr/Dr'          => 'crdr',
];
foreach (auragold_account_ledger_excel_metal_keys() as $key) {
    $label = auragold_account_ledger_excel_metal_label($key);
    $dvMap[$label . ' Cr/Dr'] = 'crdr';
}

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
$info->setCellValue('A1', 'Account Ledger — Excel Import (Opening Balances)');
$info->setCellValue('A3', '1. Sample file has 2 example rows only. Copy rows or add more before import. Required: Ledger name.');
$loginBranchLabel = auragold_account_ledger_excel_branch_name($conn, $branchId);
$info->setCellValue('A4', '2. Opening Balance + Cr/Dr = amount opening (Credit or Debit). Branch Name is your current login branch'
    . ($loginBranchLabel !== '' ? ' (' . $loginBranchLabel . ')' : '') . ' only.');
$info->setCellValue('A5', '3. Metal Opening columns (Gold / Silver / Platinum / Diamond) match the Metal Opening (gm) section on ledger-opening.php.');
$info->setCellValue('A6', '4. Existing ledgers are updated; new names create a ledger with opening balance. Fixed system ledgers cannot be deleted.');
$info->setCellValue('A7', '5. Fill rows on the Account Ledger sheet, then use Import on account-ledger.php. Maximum 5000 data rows.');
$info->getColumnDimension('A')->setWidth(105);

$spreadsheet->setActiveSheetIndex(0);

while (ob_get_level() > 0) {
    ob_end_clean();
}

$filename = 'Account_Ledger_Import_Sample_' . date('Y-m-d') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
(new Xlsx($spreadsheet))->save('php://output');
exit;
