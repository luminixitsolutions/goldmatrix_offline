<?php

/**
 * Ledger Balance Report — branded .xlsx (PhpSpreadsheet), full dataset for current filters.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_require_login.php';
require_once __DIR__ . '/../includes/ensure_customer_ledger_branch_column.php';
require_once __DIR__ . '/../includes/auragold_ledger_balance_report_data.php';
require_once __DIR__ . '/../includes/auragold_excel_financial_banner.php';
require_once __DIR__ . '/../vendor/autoload.php';

auragold_require_login_or_exit();
auragold_ensure_customer_ledger_branch_column($conn);

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$search = isset($_GET['search']) ? esc($_GET['search']) : '';
$from_date = isset($_GET['from_date']) ? trim((string) esc($_GET['from_date'])) : '';
$to_date = isset($_GET['to_date']) ? trim((string) esc($_GET['to_date'])) : '';
$customers_only = isset($_GET['customers_only']) && ($_GET['customers_only'] === '1' || $_GET['customers_only'] === 'true');

$shopName = defined('COMPANY_NAME') ? (string) COMPANY_NAME : 'Gold Matrix';
$licenseNo = '';
$targetBranchId = 0;
if (function_exists('auragold_effective_branch_id')) {
    $targetBranchId = (int) auragold_effective_branch_id();
}
if ($targetBranchId <= 0 && function_exists('auragold_my_profile_target_branch_id')) {
    $targetBranchId = (int) auragold_my_profile_target_branch_id();
}
if ($targetBranchId > 0 && function_exists('getRecordMaster') && isset($conn_master) && $conn_master instanceof mysqli) {
    $hasBizLic = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn_master, 'tbl_branches', 'business_license_no');
    if ($hasBizLic) {
        $br = getRecordMaster(
            'SELECT name, IFNULL(business_license_no,\'\') AS business_license_no, gst_no, pan_no FROM tbl_branches WHERE id = ' . $targetBranchId . ' LIMIT 1'
        );
    } else {
        $br = getRecordMaster('SELECT name, gst_no, pan_no FROM tbl_branches WHERE id = ' . $targetBranchId . ' LIMIT 1');
    }
    if (is_array($br)) {
        $nm = trim((string) ($br['name'] ?? ''));
        if ($nm !== '') {
            $shopName = $nm;
        }
        if ($hasBizLic) {
            $licenseNo = trim((string) ($br['business_license_no'] ?? ''));
        }
        if ($licenseNo === '') {
            $licenseNo = trim((string) ($br['gst_no'] ?? ''));
        }
        if ($licenseNo === '') {
            $licenseNo = trim((string) ($br['pan_no'] ?? ''));
        }
    }
}

$fSlash = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date) ? date('Y/m/d', strtotime($from_date)) : '';
$tSlash = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date) ? date('Y/m/d', strtotime($to_date)) : '';

if ($fSlash !== '' && $tSlash !== '') {
    $periodLine = 'Ledger Balance Report From :- ' . $fSlash . ' To :- ' . $tSlash;
} elseif ($fSlash !== '') {
    $periodLine = 'Ledger Balance Report From :- ' . $fSlash . ' To :- —';
} elseif ($tSlash !== '') {
    $periodLine = 'Ledger Balance Report Up To :- ' . $tSlash;
} else {
    $periodLine = 'Ledger Balance Report (balances as per last ledger)';
}

$BANNER_BLUE = '4472C4';
$fillTotalsBlue = [
    'fillType' => Fill::FILL_SOLID,
    'startColor' => ['rgb' => $BANNER_BLUE],
];
$fillGreenHdr = [
    'fillType' => Fill::FILL_SOLID,
    'startColor' => ['rgb' => 'C8E6C9'],
];
$fontTotalsWhite = ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11];
$thinBorder = [
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
    ],
];

$packed = auragold_ledger_balance_report_collect($conn, [
    'search' => $search,
    'from_date' => $from_date,
    'to_date' => $to_date,
    'customers_only' => $customers_only,
    'branch_id' => isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : 0,
]);
$rows = $packed['rows'];
$grandAmt = (float) ($packed['totals']['total_balance_amount'] ?? 0);
$grandWt = (float) ($packed['totals']['total_balance'] ?? 0);

$lastCol = 'D';
$spreadsheet = new Spreadsheet();
$spreadsheet->getDefaultStyle()->getFont()->setName('Calibri');
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('LEDGER BALANCE');

auragold_excel_financial_banner_layout(
    $sheet,
    $lastCol,
    strtoupper((string) $shopName),
    'Business License No - ' . $licenseNo,
    $periodLine,
    ['title_blue_rgb' => $BANNER_BLUE, 'period_fill_rgb' => 'F8CBAD']
);

$headers = ['Ledger', 'Ledger Type', 'Balance Amount', 'Balance Wt'];
$hdrRow = 5;
foreach ($headers as $i => $h) {
    $col = Coordinate::stringFromColumnIndex($i + 1);
    $sheet->setCellValue($col . $hdrRow, $h);
}
$sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->applyFromArray($thinBorder);
$sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->getFont()->setBold(true);
$sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->getFill()->applyFromArray($fillGreenHdr);
$sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('C' . $hdrRow . ':D' . $hdrRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

$r = $hdrRow + 1;
foreach ($rows as $row) {
    $sheet->setCellValue('A' . $r, (string) ($row['ledger_name'] ?? ''));
    $sheet->setCellValue('B' . $r, (string) ($row['ledger_type'] ?? ''));
    $sheet->setCellValueExplicit('C' . $r, (float) ($row['balance_amount'] ?? 0), DataType::TYPE_NUMERIC);
    $sheet->setCellValueExplicit('D' . $r, (float) ($row['balance'] ?? 0), DataType::TYPE_NUMERIC);

    $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->applyFromArray($thinBorder);
    $sheet->getStyle('C' . $r . ':D' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle('C' . $r)->getNumberFormat()->setFormatCode('#,##0.00_);[Red](#,##0.00)');
    $sheet->getStyle('D' . $r)->getNumberFormat()->setFormatCode('#,##0.000');

    ++$r;
}

if ($r === $hdrRow + 1 && count($rows) === 0) {
    $sheet->setCellValue('A' . $r, '');
    $sheet->setCellValue('B' . $r, '');
    $sheet->setCellValue('C' . $r, '');
    $sheet->setCellValue('D' . $r, '');
    $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->applyFromArray($thinBorder);
    ++$r;
}

$sheet->mergeCells('A' . $r . ':B' . $r);
$sheet->setCellValue('A' . $r, 'Total');
$sheet->setCellValue('C' . $r, $grandAmt);
$sheet->setCellValue('D' . $r, $grandWt);

$sheet->getStyle('A' . $r . ':' . $lastCol . $r)->applyFromArray($thinBorder);
$sheet->getStyle('A' . $r . ':' . $lastCol . $r)->getFont()->applyFromArray($fontTotalsWhite);
$sheet->getStyle('A' . $r . ':' . $lastCol . $r)->getFill()->applyFromArray($fillTotalsBlue);
$sheet->getStyle('A' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle('C' . $r . ':D' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
$sheet->getStyle('C' . $r)->getNumberFormat()->setFormatCode('#,##0.00_);[Red](#,##0.00)');
$sheet->getStyle('D' . $r)->getNumberFormat()->setFormatCode('#,##0.000');

foreach (range(1, Coordinate::columnIndexFromString($lastCol)) as $ci) {
    $sheet->getColumnDimensionByColumn($ci)->setAutoSize(true);
}

$stamp = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date) ? str_replace('-', '_', $from_date) : date('d_m_Y');
$fname = 'Ledger_Balance_Report_' . $stamp . '.xlsx';

while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
