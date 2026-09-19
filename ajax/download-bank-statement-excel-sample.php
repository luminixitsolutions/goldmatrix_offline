<?php
/**
 * Download sample .xlsx for bank statement import / reconciliation.
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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$headers = ['Date', 'Reference No', 'Description', 'Withdrawal', 'Deposit'];
$examples = [
    ['2026-08-01', 'UTR123456', 'Customer receipt NEFT', '', '50000.00'],
    ['2026-08-03', 'CHQ889900', 'Supplier payment', '25000.00', ''],
    ['2026-08-05', 'IMPS7788', 'POS settlement', '', '12500.50'],
];

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Bank Statement');

$sheet->fromArray([$headers], null, 'A1', true);
$sheet->fromArray($examples, null, 'A2', true);

$lastCol = Coordinate::stringFromColumnIndex(count($headers));
$sheet->getStyle('A1:' . $lastCol . '1')->applyFromArray([
    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['argb' => 'FF11294B'],
    ],
]);

$noteRow = count($examples) + 3;
$sheet->setCellValue('A' . $noteRow, 'Notes:');
$sheet->setCellValue('A' . ($noteRow + 1), 'Withdrawal = money out from bank (matches software Credit).');
$sheet->setCellValue('A' . ($noteRow + 2), 'Deposit = money into bank (matches software Debit).');
$sheet->setCellValue('A' . ($noteRow + 3), 'Date format YYYY-MM-DD or DD/MM/YYYY.');

for ($i = 1; $i <= count($headers); $i++) {
    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
}

$filename = 'bank-statement-sample.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
