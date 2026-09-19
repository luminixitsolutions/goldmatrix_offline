<?php

/**
 * Gold & Silver stock list — styled .xlsx (tab filter: gold | silver | all).
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

$tab = isset($_GET['tab']) ? strtolower(trim((string) $_GET['tab'])) : 'gold';
if (!in_array($tab, ['gold', 'silver', 'all'], true)) {
    $tab = 'gold';
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/gold_silver_stock_list_fetch.php';
require_once __DIR__ . '/../includes/auragold_excel_financial_banner.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$result = auragold_gold_silver_stock_list_fetch($conn, $tab);
if ($result['error'] !== '') {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/plain; charset=utf-8');
    echo strip_tags($result['error']);
    exit;
}

$rows = $result['rows'];
$titles = auragold_gold_silver_export_headers();
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
$periodLine = 'Gold & Silver — ' . auragold_gold_silver_export_tab_period_line($tab);

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

$tot = array_fill(0, $nCols, 0.0);
$totHas = array_fill(0, $nCols, false);
$sumIdxWt = [8, 9, 10, 11, 12, 14, 15, 16];
$sumIdxMoney = [22, 23, 24, 25, 26, 27, 28];

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('GOLD SILVER');

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
    $vals = auragold_gold_silver_export_flat_row($row);
    foreach ($sumIdxWt as $si) {
        if (isset($vals[$si]) && is_numeric($vals[$si])) {
            $tot[$si] += (float) $vals[$si];
            $totHas[$si] = true;
        }
    }
    foreach ($sumIdxMoney as $si) {
        if (isset($vals[$si]) && is_numeric($vals[$si])) {
            $tot[$si] += (float) $vals[$si];
            $totHas[$si] = true;
        }
    }

    for ($i = 0; $i < $nCols; ++$i) {
        $col = Coordinate::stringFromColumnIndex($i + 1);
        $v = $vals[$i] ?? '';
        $isNum = is_int($v) || is_float($v) || (is_string($v) && $v !== '' && is_numeric($v));
        if ($isNum && $i !== 13) {
            $sheet->setCellValueExplicit($col . $r, is_string($v) ? (float) $v : $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        } else {
            $sheet->setCellValue($col . $r, is_scalar($v) ? (string) $v : '');
        }
    }
    $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->applyFromArray($thinBorder);
    $sheet->getStyle('I' . $r . ':Q' . $r)->getFill()->applyFromArray($fillPeachMetric);
    $sheet->getStyle('I' . $r . ':Q' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle('W' . $r . ':' . $lastCol . $r)->getFill()->applyFromArray($fillPeachMetric);
    $sheet->getStyle('W' . $r . ':' . $lastCol . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    ++$r;
}

$footRow = $r;
$sheet->mergeCells('A' . $footRow . ':H' . $footRow);
$sheet->setCellValue('A' . $footRow, 'Total');
for ($i = 8; $i < $nCols; ++$i) {
    $col = Coordinate::stringFromColumnIndex($i + 1);
    if ($i <= 16 && $i !== 13 && $totHas[$i]) {
        $sheet->setCellValueExplicit($col . $footRow, $tot[$i], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    } elseif ($i <= 16) {
        $sheet->setCellValue($col . $footRow, '');
    } elseif ($i <= 21) {
        $sheet->setCellValue($col . $footRow, '');
    } elseif ($totHas[$i]) {
        $sheet->setCellValueExplicit($col . $footRow, $tot[$i], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    } else {
        $sheet->setCellValue($col . $footRow, '');
    }
}
$sheet->getStyle('A' . $footRow . ':' . $lastCol . $footRow)->applyFromArray($thinBorder);
$sheet->getStyle('A' . $footRow . ':' . $lastCol . $footRow)->getFill()->applyFromArray($fillTotalsBlue);
$sheet->getStyle('A' . $footRow . ':' . $lastCol . $footRow)->getFont()->applyFromArray($fontTotalsWhite);
$sheet->getStyle('A' . $footRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle('I' . $footRow . ':Q' . $footRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('W' . $footRow . ':' . $lastCol . $footRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

for ($ci = 1; $ci <= $nCols; ++$ci) {
    $sheet->getColumnDimensionByColumn($ci)->setAutoSize(true);
}

$slug = auragold_gold_silver_export_file_slug($tab);
$fname = 'Gold_Silver_Stock_' . $slug . '_' . date('Y-m-d') . '.xlsx';
while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
