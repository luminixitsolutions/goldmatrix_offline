<?php

/**
 * Platinum Analysis — Current Stock / Stock Details styled .xlsx (financial banner; same filters as page via GET).
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_require_login.php';
auragold_require_login_or_exit();

if (!isset($conn) || !($conn instanceof mysqli)) {
    header('HTTP/1.1 500 Internal Server Error');
    echo 'No database connection';
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/platinum_analysis_export_data.php';
require_once __DIR__ . '/../includes/auragold_excel_financial_banner.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$data = auragold_platinum_analysis_export_fetch();
if ($data['error'] !== '') {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/plain; charset=utf-8');
    echo $data['error'];
    exit;
}

$rows = $data['rows'];
$totals = $data['totals'];
$tab = $data['tab'];
$titles = auragold_platinum_analysis_export_headers();
$nCols = count($titles);
$lastCol = Coordinate::stringFromColumnIndex($nCols);

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

$licenseLine = 'Business License No - ' . ($licenseNo !== '' ? $licenseNo : '—');
$periodLine = $tab === 'stock-details'
    ? 'Platinum Analysis — Stock Details (up to 15,000 rows; same filters as screen)'
    : 'Platinum Analysis — Current Stock (up to 15,000 rows; same filters as screen)';

$thinBorder = [
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
    ],
];
$fillMintHdr = [
    'fillType' => Fill::FILL_SOLID,
    'startColor' => ['rgb' => 'E2EFDA'],
];
$fillPeachMetric = [
    'fillType' => Fill::FILL_SOLID,
    'startColor' => ['rgb' => 'F8CBAD'],
];
$fillTotalsBlue = [
    'fillType' => Fill::FILL_SOLID,
    'startColor' => ['rgb' => '4472C4'],
];
$fontTotalsWhite = ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11];

$tg = static function (array $tt, string $key): float {
    foreach ($tt as $k => $v) {
        if (strcasecmp((string) $k, $key) === 0) {
            return (float) $v;
        }
    }
    return 0.0;
};

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle($tab === 'stock-details' ? 'STOCK DETAILS' : 'CURRENT STOCK');

$hdrRow = auragold_excel_financial_banner_layout(
    $sheet,
    $lastCol,
    strtoupper($shopName),
    $licenseLine,
    $periodLine
);

for ($i = 0; $i < $nCols; ++$i) {
    $col = Coordinate::stringFromColumnIndex($i + 1);
    $sheet->setCellValue($col . $hdrRow, $titles[$i]);
}
$sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->applyFromArray($thinBorder);
$sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->getFill()->applyFromArray($fillMintHdr);
$sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->getFont()->setBold(true);
$sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

$r = $hdrRow + 1;
foreach ($rows as $row) {
    $vals = auragold_platinum_analysis_export_flat_row($row, $tab);
    for ($i = 0; $i < $nCols; ++$i) {
        $col = Coordinate::stringFromColumnIndex($i + 1);
        $v = $vals[$i] ?? '';
        if ($i === 2 && is_numeric($v)) {
            $sheet->setCellValueExplicit($col . $r, (int) $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        } elseif (($i >= 3 && $i <= 4) || ($i >= 7 && $i <= 8)) {
            $sheet->setCellValueExplicit($col . $r, (float) $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        } elseif ($i === 9 && is_numeric($v)) {
            $sheet->setCellValueExplicit($col . $r, (float) $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        } else {
            $sheet->setCellValue($col . $r, is_scalar($v) ? (string) $v : '');
        }
    }
    $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->applyFromArray($thinBorder);
    $sheet->getStyle('C' . $r . ':' . $lastCol . $r)->getFill()->applyFromArray($fillPeachMetric);
    $sheet->getStyle('C' . $r . ':' . $lastCol . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    ++$r;
}

$footRow = $r;
$sheet->mergeCells('A' . $footRow . ':B' . $footRow);
$sheet->setCellValue('A' . $footRow, 'Total');
$sheet->setCellValueExplicit('C' . $footRow, (int) round($tg($totals, 'total_qty'), 0), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
$sheet->setCellValueExplicit('D' . $footRow, $tg($totals, 'total_gross_weight'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
$sheet->setCellValueExplicit('E' . $footRow, $tg($totals, 'total_pure_weight'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
$sheet->setCellValue('F' . $footRow, '');
$sheet->setCellValue('G' . $footRow, '');
$sheet->setCellValueExplicit('H' . $footRow, $tg($totals, 'total_net_weight'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
$sheet->setCellValueExplicit('I' . $footRow, $tg($totals, 'total_stone_weight'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
$sheet->setCellValueExplicit('J' . $footRow, $tg($totals, 'total_purchase_amount'), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);

$sheet->getStyle('A' . $footRow . ':' . $lastCol . $footRow)->applyFromArray($thinBorder);
$sheet->getStyle('A' . $footRow . ':' . $lastCol . $footRow)->getFill()->applyFromArray($fillTotalsBlue);
$sheet->getStyle('A' . $footRow . ':' . $lastCol . $footRow)->getFont()->applyFromArray($fontTotalsWhite);
$sheet->getStyle('A' . $footRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle('C' . $footRow . ':' . $lastCol . $footRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

for ($ci = 1; $ci <= $nCols; ++$ci) {
    $sheet->getColumnDimensionByColumn($ci)->setAutoSize(true);
}

$fnameSuffix = $tab === 'stock-details' ? 'Stock_Details' : 'Current_Stock';
$fname = 'Platinum_Analysis_' . $fnameSuffix . '_' . date('Y-m-d') . '.xlsx';
while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
