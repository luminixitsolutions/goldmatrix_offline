<?php

/**
 * Stock History (ledger) — styled .xlsx (banner + peach period + mint headers + metric band + blue totals).
 * Uses same filters as stock-history.php?ledger=1 (GET params).
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
require_once __DIR__ . '/../includes/stock_history_ledger_fetch.php';
require_once __DIR__ . '/../includes/auragold_excel_financial_banner.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$data = auragold_stock_history_ledger_fetch($conn, $_GET);
if ($data['err'] !== '') {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/plain; charset=utf-8');
    echo $data['err'];
    exit;
}

$rows = $data['rows'];
$tot_qty = $data['tot_qty'];
$tot_gross = $data['tot_gross'];
$tot_pure = $data['tot_pure'];
$adv_date_from = $data['adv_date_from'];
$adv_date_to = $data['adv_date_to'];

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

$periodLine = 'Stock History (Ledger)';
if ($adv_date_from !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $adv_date_from)) {
    $f = str_replace('-', '/', $adv_date_from);
    if ($adv_date_to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $adv_date_to)) {
        $t = str_replace('-', '/', $adv_date_to);
        $periodLine .= ' From :- ' . $f . ' To :- ' . $t;
    } else {
        $periodLine .= ' From :- ' . $f;
    }
} elseif ($adv_date_to !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $adv_date_to)) {
    $periodLine .= ' To :- ' . str_replace('-', '/', $adv_date_to);
}

$lastCol = 'P';
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

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('STOCK HISTORY');

$hdrRow = auragold_excel_financial_banner_layout(
    $sheet,
    $lastCol,
    strtoupper($shopName),
    $licenseLine,
    $periodLine
);

$titles = [
    'Date', 'Barcode No', 'RFID', 'Against Invoice No', 'Voucher Type', 'Location', 'Invoice No.',
    'Against Voucher Type', 'Branch', 'Qty.', 'Gross Wt', 'Pure Wt.', 'Product Name', 'Metal', 'Category', 'Article',
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
    $d = !empty($row['sj_date']) ? $row['sj_date'] : '';
    $dShow = $d ? date('d-m-Y', strtotime((string) $d)) : '';
    $son = trim((string) ($row['sale_order_no'] ?? ''));
    $voucher = auragold_stock_history_ledger_voucher_display(trim((string) ($row['voucher_type'] ?? '')));
    $jwn = trim((string) ($row['jobwork_no'] ?? ''));

    $vals = [
        $dShow,
        trim((string) ($row['barcode'] ?? '')),
        trim((string) ($row['rfid'] ?? '')),
        $son !== '' ? $son : '—',
        $voucher,
        trim((string) ($row['location'] ?? '')),
        trim((string) ($row['doc_invoice_no'] ?? '')),
        $jwn !== '' ? $jwn : '—',
        trim((string) ($row['branch_name'] ?? '')),
        (float) ($row['qty'] ?? 0),
        (float) ($row['gross_wt'] ?? 0),
        (float) ($row['pure_wt'] ?? 0),
        trim((string) ($row['product_name'] ?? '')),
        trim((string) ($row['metal_name'] ?? '')),
        trim((string) ($row['category_name'] ?? '')),
        trim((string) ($row['article'] ?? '')),
    ];
    for ($i = 0; $i < $nCols; ++$i) {
        $col = Coordinate::stringFromColumnIndex($i + 1);
        $v = $vals[$i];
        if ($i >= 9 && $i <= 11 && is_numeric($v)) {
            $sheet->setCellValueExplicit($col . $r, $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        } else {
            $sheet->setCellValue($col . $r, $v);
        }
    }
    $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->applyFromArray($thinBorder);
    $sheet->getStyle('J' . $r . ':L' . $r)->getFill()->applyFromArray($fillPeachMetric);
    $sheet->getStyle('J' . $r . ':L' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    ++$r;
}

$footRow = $r;
$sheet->mergeCells('A' . $footRow . ':I' . $footRow);
$sheet->setCellValue('A' . $footRow, 'Total');
$sheet->setCellValueExplicit('J' . $footRow, $tot_qty, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
$sheet->setCellValueExplicit('K' . $footRow, $tot_gross, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
$sheet->setCellValueExplicit('L' . $footRow, $tot_pure, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
$sheet->mergeCells('M' . $footRow . ':' . $lastCol . $footRow);
$sheet->setCellValue('M' . $footRow, '');
$sheet->getStyle('A' . $footRow . ':' . $lastCol . $footRow)->applyFromArray($thinBorder);
$sheet->getStyle('A' . $footRow . ':' . $lastCol . $footRow)->getFill()->applyFromArray($fillTotalsBlue);
$sheet->getStyle('A' . $footRow . ':' . $lastCol . $footRow)->getFont()->applyFromArray($fontTotalsWhite);
$sheet->getStyle('A' . $footRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle('J' . $footRow . ':L' . $footRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

for ($ci = 1; $ci <= $nCols; ++$ci) {
    $sheet->getColumnDimensionByColumn($ci)->setAutoSize(true);
}

$fname = 'Stock_History_Ledger_' . date('Y-m-d') . '.xlsx';
while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
