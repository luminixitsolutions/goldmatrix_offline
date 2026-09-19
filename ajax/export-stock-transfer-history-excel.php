<?php

/**
 * Stock Transfer History — styled .xlsx (banner + mint headers + peach metrics + blue totals).
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
require_once __DIR__ . '/../includes/stock_transfer_history_fetch.php';
require_once __DIR__ . '/../includes/auragold_excel_financial_banner.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$result = auragold_stock_transfer_history_fetch($conn);
if ($result['error'] !== '') {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/plain; charset=utf-8');
    echo $result['error'];
    exit;
}

$rows = $result['rows'];

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
$periodLine = 'Stock Transfer History — up to 5,000 most recent transfers';

$lastCol = 'Q';
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

$totNet = 0.0;
$totGross = 0.0;
$totQty = 0.0;
$totDia = 0.0;
$totStone = 0.0;
$totPur = 0.0;
$totMetal = 0.0;
$totStoneC = 0.0;
$totMaking = 0.0;
foreach ($rows as $rw) {
    $totNet += (float) ($rw['net_wt'] ?? 0);
    $totGross += (float) ($rw['gross_wt'] ?? 0);
    $totQty += (float) ($rw['qty'] ?? 0);
    $totDia += (float) ($rw['diamond_wt'] ?? 0);
    $totStone += (float) ($rw['stone_wt'] ?? 0);
    $totPur += (float) ($rw['purchase_value'] ?? 0);
    $totMetal += (float) ($rw['metal_cost'] ?? 0);
    $totStoneC += (float) ($rw['stone_cost'] ?? 0);
    $totMaking += (float) ($rw['making_cost'] ?? 0);
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('TRANSFER HISTORY');

$hdrRow = auragold_excel_financial_banner_layout(
    $sheet,
    $lastCol,
    strtoupper($shopName),
    $licenseLine,
    $periodLine
);

$titles = [
    'Date', 'Invoice No', 'Product Name', 'Transfer To', 'From Branch', 'Barcode',
    'Net Wt', 'Gross Wt', 'Qty', 'Diamond', 'Stone Wt', 'Purchase', 'Metal Cost', 'Stone Cost', 'Making',
    'Against', 'Status',
];
$nCols = count($titles);
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
    $d = !empty($row['transaction_date']) ? $row['transaction_date'] : ($row['created_at'] ?? '');
    $dShow = $d ? date('d/m/Y', strtotime((string) $d)) : '';
    $inv = trim((string) ($row['invoice_no'] ?? ''));
    if ($inv === '') {
        $inv = 'ST-' . str_pad((string) ($row['outward_id'] ?? 0), 6, '0', STR_PAD_LEFT);
    }
    $vals = [
        $dShow,
        $inv,
        trim((string) ($row['product_name'] ?? '')),
        trim((string) ($row['to_branch_name'] ?? '')),
        trim((string) ($row['from_branch_name'] ?? '')),
        trim((string) ($row['barcode'] ?? '')),
        (float) ($row['net_wt'] ?? 0),
        (float) ($row['gross_wt'] ?? 0),
        (float) ($row['qty'] ?? 0),
        (float) ($row['diamond_wt'] ?? 0),
        (float) ($row['stone_wt'] ?? 0),
        (float) ($row['purchase_value'] ?? 0),
        (float) ($row['metal_cost'] ?? 0),
        (float) ($row['stone_cost'] ?? 0),
        (float) ($row['making_cost'] ?? 0),
        trim((string) ($row['against_ref'] ?? '')),
        auragold_stock_transfer_history_status_label($row),
    ];
    for ($i = 0; $i < $nCols; ++$i) {
        $col = Coordinate::stringFromColumnIndex($i + 1);
        $v = $vals[$i];
        if ($i >= 6 && $i <= 14 && is_numeric($v)) {
            $sheet->setCellValueExplicit($col . $r, $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        } else {
            $sheet->setCellValue($col . $r, $v);
        }
    }
    $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->applyFromArray($thinBorder);
    $sheet->getStyle('G' . $r . ':O' . $r)->getFill()->applyFromArray($fillPeachMetric);
    $sheet->getStyle('G' . $r . ':O' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    ++$r;
}

$footRow = $r;
$sheet->mergeCells('A' . $footRow . ':F' . $footRow);
$sheet->setCellValue('A' . $footRow, 'Total');
$sheet->setCellValueExplicit('G' . $footRow, $totNet, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
$sheet->setCellValueExplicit('H' . $footRow, $totGross, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
$sheet->setCellValueExplicit('I' . $footRow, $totQty, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
$sheet->setCellValueExplicit('J' . $footRow, $totDia, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
$sheet->setCellValueExplicit('K' . $footRow, $totStone, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
$sheet->setCellValueExplicit('L' . $footRow, $totPur, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
$sheet->setCellValueExplicit('M' . $footRow, $totMetal, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
$sheet->setCellValueExplicit('N' . $footRow, $totStoneC, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
$sheet->setCellValueExplicit('O' . $footRow, $totMaking, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
$sheet->mergeCells('P' . $footRow . ':Q' . $footRow);
$sheet->setCellValue('P' . $footRow, '');
$sheet->getStyle('A' . $footRow . ':' . $lastCol . $footRow)->applyFromArray($thinBorder);
$sheet->getStyle('A' . $footRow . ':' . $lastCol . $footRow)->getFill()->applyFromArray($fillTotalsBlue);
$sheet->getStyle('A' . $footRow . ':' . $lastCol . $footRow)->getFont()->applyFromArray($fontTotalsWhite);
$sheet->getStyle('A' . $footRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle('G' . $footRow . ':O' . $footRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

for ($ci = 1; $ci <= $nCols; ++$ci) {
    $sheet->getColumnDimensionByColumn($ci)->setAutoSize(true);
}

$fname = 'Stock_Transfer_History_' . date('Y-m-d') . '.xlsx';
while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
