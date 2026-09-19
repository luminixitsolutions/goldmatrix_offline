<?php

/**
 * Day Book multi-sheet Excel export (PhpSpreadsheet).
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['Admin']['id']) && empty($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Session expired. Please log in.';
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/branch_profile_schema.php';
require_once __DIR__ . '/../includes/auragold_day_report_data.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$report_date = isset($_GET['date']) ? trim((string) $_GET['date']) : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $report_date)) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Invalid date';
    exit;
}

$payload  = auragold_day_report_collect($conn, $report_date);
$sections = $payload['sections'];
$summary  = $payload['summary'];

auragold_ensure_tbl_branches_profile_columns($conn_master);

$shopName      = defined('COMPANY_NAME') ? (string) COMPANY_NAME : 'Gold Matrix';
$licenseNo     = '';
$targetBranchId = 0;
if (function_exists('auragold_my_profile_target_branch_id')) {
    $targetBranchId = (int) auragold_my_profile_target_branch_id();
}
if ($targetBranchId > 0 && function_exists('getRecordMaster')) {
    $br = getRecordMaster(
        'SELECT name, gst_no, pan_no FROM tbl_branches WHERE id = ' . $targetBranchId . ' LIMIT 1'
    );
    if (is_array($br)) {
        $nm = trim((string) ($br['name'] ?? ''));
        if ($nm !== '') {
            $shopName = $nm;
        }
        $licenseNo = trim((string) ($br['gst_no'] ?? ''));
        if ($licenseNo === '') {
            $licenseNo = trim((string) ($br['pan_no'] ?? ''));
        }
    }
}

$dateSlash = date('Y/m/d', strtotime($report_date));
$fromToDayBook    = 'DayBook Report From :- ' . $dateSlash . ' To :- ' . $dateSlash;
$fromToSummary    = 'DayBook Summary Report From :- ' . $dateSlash . ' To :- ' . $dateSlash;
$fromToDenom      = 'Cash Denomination Summary Report From :- ' . $dateSlash;

$fillBlue = [
    'fillType'   => Fill::FILL_SOLID,
    'startColor' => ['rgb' => '4472C4'],
];
$fillPeach = [
    'fillType'   => Fill::FILL_SOLID,
    'startColor' => ['rgb' => 'FCE4D6'],
];
$fillGreen = [
    'fillType'   => Fill::FILL_SOLID,
    'startColor' => ['rgb' => 'C6E0B4'],
];
$fillPinkBand = [
    'fillType'   => Fill::FILL_SOLID,
    'startColor' => ['rgb' => 'F8CBAD'],
];
$fontWhiteBold = ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 14];
$thinBorder = [
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
    ],
];

$excludeFromGrand = ['bank' => true, 'sale_quotation' => true, 'purchase_quotation' => true];
$grandDebit = $grandCredit = $grandIn = $grandOut = 0.0;
foreach ($sections as $sec) {
    $k = $sec['key'] ?? '';
    if (!empty($excludeFromGrand[$k])) {
        continue;
    }
    $t = $sec['totals'] ?? [];
    $grandDebit  += (float) ($t['debit'] ?? 0);
    $grandCredit += (float) ($t['credit'] ?? 0);
    $grandIn     += (float) ($t['inward_wt'] ?? 0);
    $grandOut    += (float) ($t['outward_wt'] ?? 0);
}
$closingCash = (float) ($summary['closing_cash'] ?? 0);

$spreadsheet = new Spreadsheet();
$spreadsheet->getDefaultStyle()->getFont()->setName('Calibri');

// ---- Sheet 1: Day Book Report ----
$s1 = $spreadsheet->getActiveSheet();
$s1->setTitle('Day Book Report');

$s1->mergeCells('A1:F1');
$s1->setCellValue('A1', strtoupper($shopName));
$s1->getStyle('A1')->getFont()->applyFromArray($fontWhiteBold);
$s1->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$s1->getStyle('A1')->getFill()->applyFromArray($fillBlue);
$s1->getRowDimension(1)->setRowHeight(28);

$s1->mergeCells('A3:F3');
$s1->setCellValue('A3', 'Business License No - ' . $licenseNo);
$s1->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$s1->mergeCells('A4:F4');
$s1->setCellValue('A4', $fromToDayBook);
$s1->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$s1->getStyle('A4')->getFill()->applyFromArray($fillPeach);

$hdrRow = 5;
$s1->fromArray([
    ['Voucher Type', '', 'Debit', 'Credit', 'Inward Qty', 'Outward Qty'],
], null, 'A' . $hdrRow, true);
$s1->getStyle('A' . $hdrRow . ':F' . $hdrRow)->applyFromArray($thinBorder);
$s1->getStyle('A' . $hdrRow . ':F' . $hdrRow)->getFont()->setBold(true);
$s1->getStyle('A' . $hdrRow . ':F' . $hdrRow)->getFill()->applyFromArray($fillGreen);

$r = $hdrRow + 1;
foreach ($sections as $sec) {
    $label = (string) ($sec['label'] ?? '');
    $t     = $sec['totals'] ?? [];
    $s1->setCellValue('A' . $r, $label);
    $s1->setCellValue('C' . $r, (float) ($t['debit'] ?? 0));
    $s1->setCellValue('D' . $r, (float) ($t['credit'] ?? 0));
    $s1->setCellValue('E' . $r, (float) ($t['inward_wt'] ?? 0));
    $s1->setCellValue('F' . $r, (float) ($t['outward_wt'] ?? 0));
    $s1->getStyle('C' . $r . ':D' . $r)->getNumberFormat()->setFormatCode('#,##0.00');
    $s1->getStyle('E' . $r . ':F' . $r)->getNumberFormat()->setFormatCode('#,##0.000');
    $s1->getStyle('A' . $r . ':F' . $r)->applyFromArray($thinBorder);
    $s1->getStyle('A' . $r)->getFont()->setBold(true);
    ++$r;
    foreach ($sec['rows'] ?? [] as $row) {
        $s1->setCellValue('A' . $r, (string) ($row['description'] ?? ''));
        $s1->setCellValue('C' . $r, (float) ($row['debit'] ?? 0));
        $s1->setCellValue('D' . $r, (float) ($row['credit'] ?? 0));
        $s1->setCellValue('E' . $r, (float) ($row['inward_wt'] ?? 0));
        $s1->setCellValue('F' . $r, (float) ($row['outward_wt'] ?? 0));
        $s1->getStyle('C' . $r . ':D' . $r)->getNumberFormat()->setFormatCode('#,##0.00');
        $s1->getStyle('E' . $r . ':F' . $r)->getNumberFormat()->setFormatCode('#,##0.000');
        $s1->getStyle('A' . $r . ':F' . $r)->applyFromArray($thinBorder);
        ++$r;
    }
}

$s1->setCellValue('A' . $r, 'TOTAL');
$s1->setCellValue('C' . $r, $grandDebit);
$s1->setCellValue('D' . $r, $grandCredit);
$s1->setCellValue('E' . $r, $grandIn);
$s1->setCellValue('F' . $r, $grandOut);
$s1->getStyle('A' . $r . ':F' . $r)->applyFromArray($thinBorder);
$s1->getStyle('A' . $r . ':F' . $r)->getFont()->setBold(true);
$s1->getStyle('C' . $r . ':D' . $r)->getNumberFormat()->setFormatCode('#,##0.00');
$s1->getStyle('E' . $r . ':F' . $r)->getNumberFormat()->setFormatCode('#,##0.000');
++$r;
++$r;

$s1->setCellValue('B' . $r, 'CLOSING CASH BALANCE');
$s1->setCellValue('C' . $r, 0);
$s1->setCellValue('D' . $r, $closingCash);
$s1->setCellValue('E' . $r, 0);
$s1->setCellValue('F' . $r, 0);
$s1->getStyle('A' . $r . ':F' . $r)->applyFromArray($thinBorder);
$s1->getStyle('B' . $r . ':F' . $r)->getFont()->getColor()->setRGB('FF0000');
$s1->getStyle('C' . $r . ':D' . $r)->getNumberFormat()->setFormatCode('#,##0.00');
$s1->getStyle('E' . $r . ':F' . $r)->getNumberFormat()->setFormatCode('#,##0.000');

foreach (range('A', 'F') as $col) {
    $s1->getColumnDimension($col)->setAutoSize(true);
}

// ---- Sheet 2: Day Book Summary ----
$s2 = $spreadsheet->createSheet();
$s2->setTitle('Day Book Summary');

$s2->mergeCells('A1:F1');
$s2->setCellValue('A1', strtoupper($shopName));
$s2->getStyle('A1')->getFont()->applyFromArray($fontWhiteBold);
$s2->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$s2->getStyle('A1')->getFill()->applyFromArray($fillBlue);
$s2->getRowDimension(1)->setRowHeight(28);

$s2->mergeCells('A3:F3');
$s2->setCellValue('A3', 'Business License No - ' . $licenseNo);
$s2->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$s2->mergeCells('A4:F4');
$s2->setCellValue('A4', $fromToSummary);
$s2->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$s2->getStyle('A4')->getFill()->applyFromArray($fillPeach);

$s2->fromArray([[
    'Opening',
    'Expected',
    'Online Cheque Payment',
    'Closing Cash',
    'Cash Denomination',
    'Difference',
]], null, 'A5', true);
$s2->getStyle('A5:F5')->applyFromArray($thinBorder);
$s2->getStyle('A5:F5')->getFont()->setBold(true);
$s2->getStyle('A5:F5')->getFill()->applyFromArray($fillGreen);

$s2->setCellValue('A6', (float) ($summary['opening_amount'] ?? 0));
$s2->setCellValue('B6', (float) ($summary['expected_amount'] ?? 0));
$s2->setCellValue('C6', (float) ($summary['online_cheque_payment'] ?? 0));
$s2->setCellValue('D6', (float) ($summary['closing_cash'] ?? 0));
$s2->setCellValue('E6', (float) ($summary['cash_denomination'] ?? 0));
$s2->setCellValue('F6', (float) ($summary['difference'] ?? 0));
$s2->getStyle('A6:F6')->applyFromArray($thinBorder);
$s2->getStyle('A6:F6')->getNumberFormat()->setFormatCode('#,##0.00');
foreach (range('A', 'F') as $col) {
    $s2->getColumnDimension($col)->setAutoSize(true);
}

// ---- Sheet 3: Cash denomination ----
// Excel / PhpSpreadsheet allow max 31 characters per worksheet title.
$s3      = $spreadsheet->createSheet();
$s3Title = 'DAYBOOK CASH DENOM SUMMAY';
$s3->setTitle($s3Title);

$s3->mergeCells('A1:D1');
$s3->setCellValue('A1', strtoupper($shopName));
$s3->getStyle('A1')->getFont()->applyFromArray($fontWhiteBold);
$s3->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$s3->getStyle('A1')->getFill()->applyFromArray($fillBlue);
$s3->getRowDimension(1)->setRowHeight(28);

$s3->mergeCells('A3:D3');
$s3->setCellValue('A3', 'Business License No - ' . $licenseNo);
$s3->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$s3->mergeCells('A4:D4');
$s3->setCellValue('A4', $fromToDenom);
$s3->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$s3->getStyle('A4')->getFill()->applyFromArray($fillPinkBand);

$s3->fromArray([['Denomination', '', 'Quantity', 'Amount']], null, 'A5', true);
$s3->getStyle('A5:D5')->applyFromArray($thinBorder);
$s3->getStyle('A5:D5')->getFont()->setBold(true);
$s3->getStyle('A5:D5')->getFill()->applyFromArray($fillGreen);
foreach (range('A', 'D') as $col) {
    $s3->getColumnDimension($col)->setAutoSize(true);
}

$spreadsheet->setActiveSheetIndex(0);

$fnDate = date('d_m_Y', strtotime($report_date));
$filename = 'DayBook_DayBook_Summary_DayBook_Cash_Denomination_Summary_' . $fnDate . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
