<?php
/**
 * Barcode Scan Report — styled .xlsx export.
 * GET/POST: from_date, to_date, status?, q?|search?
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_require_login.php';
require_once __DIR__ . '/../includes/auragold_barcode_scan_report_data.php';

auragold_require_login_or_exit();

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/auragold_excel_financial_banner.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$cols_def = auragold_barcode_scan_report_columns();
/** @var array<string,string> */
$COLUMN_LABELS = [];
/** @var array<string,bool> */
$NUMERIC_KEYS = [];
$DEFAULT_ORDER = [];
foreach ($cols_def as $c) {
    $k = (string) ($c['key'] ?? '');
    if ($k === '') {
        continue;
    }
    $COLUMN_LABELS[$k] = (string) ($c['label'] ?? $k);
    $DEFAULT_ORDER[] = $k;
    if (!empty($c['numeric'])) {
        $NUMERIC_KEYS[$k] = true;
    }
}

$payload = null;
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    if ($raw !== false && trim($raw) !== '') {
        $payload = json_decode((string) $raw, true);
    }
}

if (is_array($payload)) {
    $filters = [
        'from_date' => isset($payload['from_date']) ? trim((string) $payload['from_date']) : '',
        'to_date'   => isset($payload['to_date']) ? trim((string) $payload['to_date']) : '',
        'status'    => isset($payload['status']) ? trim((string) $payload['status']) : '',
        'q'         => isset($payload['q']) ? trim((string) $payload['q']) : (isset($payload['search']) ? trim((string) $payload['search']) : ''),
    ];
} else {
    $filters = auragold_barcode_scan_report_filters_from_request();
}

$fetch = auragold_barcode_scan_report_fetch($conn, $filters);
if (empty($fetch['success'])) {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Export failed.';
    exit;
}

$rows = $fetch['rows'];
$summary = $fetch['summary'];
$fd = $fetch['from_date'];
$td = $fetch['to_date'];

$export_cols = $DEFAULT_ORDER;
$num_cols = count($export_cols);
$lastCol = Coordinate::stringFromColumnIndex($num_cols);

$fmtSlash = static function (string $ymd): string {
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m)) {
        return $ymd;
    }

    return $m[1] . '/' . $m[2] . '/' . $m[3];
};

$periodLine = 'Barcode Scan Report From :- ' . $fmtSlash($fd) . ' To :- ' . $fmtSlash($td);
if (!empty($filters['status'])) {
    $periodLine .= ' | Status: ' . ucfirst(strtolower((string) $filters['status']));
}

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

$BANNER_BLUE = '4472C4';
$thinBorder = [
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
    ],
];
$fillGreenHdr = [
    'fillType'   => Fill::FILL_SOLID,
    'startColor' => ['rgb' => 'C8E6C9'],
];
$fillStripeA = [
    'fillType'   => Fill::FILL_SOLID,
    'startColor' => ['rgb' => 'E8F5E9'],
];
$fillStripeB = [
    'fillType'   => Fill::FILL_SOLID,
    'startColor' => ['rgb' => 'FFFFFF'],
];
$fillTotal = [
    'fillType'   => Fill::FILL_SOLID,
    'startColor' => ['rgb' => '4472C4'],
];

$spreadsheet = new Spreadsheet();
$spreadsheet->getDefaultStyle()->getFont()->setName('Calibri');
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('BARCODE SCAN REPORT');

$hdrRow = auragold_excel_financial_banner_layout(
    $sheet,
    $lastCol,
    strtoupper($shopName),
    'Business License No -' . ($licenseNo !== '' ? ' ' . $licenseNo : ''),
    $periodLine,
    [
        'title_blue_rgb'   => $BANNER_BLUE,
        'period_fill_rgb'  => 'F8CBAD',
        'title_font'       => ['size' => 18],
        'title_row_height' => 36,
        'license_font'     => ['color' => ['rgb' => '000000'], 'bold' => false],
        'period_font'      => ['bold' => true, 'color' => ['rgb' => '000000']],
    ]
);

$width_hints = [
    'sr'           => 6,
    'scan_date'    => 12,
    'scan_time'    => 10,
    'scan_status'  => 10,
    'scan_code'    => 14,
    'barcode'      => 14,
    'rfid_code'    => 14,
    'product_name' => 22,
    'article'      => 14,
    'metal_name'   => 12,
    'branch_name'  => 12,
    'location'     => 12,
    'qty'          => 8,
    'gross_wt'     => 10,
    'net_wt'       => 10,
    'final_wt'     => 10,
    'voucher_type' => 16,
    'invoice_no'   => 14,
    'user_name'    => 16,
];

for ($i = 0; $i < $num_cols; ++$i) {
    $key = $export_cols[$i];
    $col = Coordinate::stringFromColumnIndex($i + 1);
    $sheet->setCellValue($col . $hdrRow, $COLUMN_LABELS[$key] ?? $key);
}

$sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->applyFromArray($thinBorder);
$sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->getFont()->setBold(true);
$sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->getFill()->applyFromArray($fillGreenHdr);
$sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);

$r = $hdrRow + 1;
$rowIdx = 0;
foreach ($rows as $fr) {
    ++$rowIdx;
    $stripeFill = ($rowIdx % 2 === 1) ? $fillStripeA : $fillStripeB;

    for ($i = 0; $i < $num_cols; ++$i) {
        $key = $export_cols[$i];
        $colLetter = Coordinate::stringFromColumnIndex($i + 1);
        $cell = $colLetter . $r;
        $val = isset($fr[$key]) ? $fr[$key] : '';

        if ($key === 'scan_status') {
            $val = (string) ($fr['scan_status_label'] ?? ($val === 'unknown' ? 'Unknown' : 'Matched'));
        }

        if (isset($NUMERIC_KEYS[$key])) {
            $num = is_numeric($val) ? (float) $val : 0.0;
            $sheet->setCellValueExplicit($cell, $num, DataType::TYPE_NUMERIC);
            $sheet->getStyle($cell)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
                ->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.####');
        } else {
            $sheet->setCellValue($cell, (string) $val);
            $sheet->getStyle($cell)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                ->setVertical(Alignment::VERTICAL_CENTER);
        }
    }

    $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->applyFromArray($thinBorder);
    $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->getFill()->applyFromArray($stripeFill);
    ++$r;
}

if ($rowIdx === 0) {
    for ($i = 0; $i < $num_cols; ++$i) {
        $col = Coordinate::stringFromColumnIndex($i + 1);
        $sheet->setCellValue($col . $r, '');
    }
    $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->applyFromArray($thinBorder);
    $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->getFill()->applyFromArray($fillStripeB);
    ++$r;
}

$totalRow = $r;
$totalLabelCol = null;
for ($i = 0; $i < $num_cols; ++$i) {
    $key = $export_cols[$i];
    $colLetter = Coordinate::stringFromColumnIndex($i + 1);
    $cell = $colLetter . $totalRow;

    if ($key === 'qty') {
        $sheet->setCellValueExplicit($cell, (float) ($summary['total_qty'] ?? 0), DataType::TYPE_NUMERIC);
        $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.####');
        $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    } elseif ($key === 'gross_wt') {
        $sheet->setCellValueExplicit($cell, (float) ($summary['total_gross_wt'] ?? 0), DataType::TYPE_NUMERIC);
        $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.####');
        $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    } elseif ($key === 'net_wt') {
        $sheet->setCellValueExplicit($cell, (float) ($summary['total_net_wt'] ?? 0), DataType::TYPE_NUMERIC);
        $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.####');
        $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    } elseif ($key === 'final_wt') {
        $sheet->setCellValueExplicit($cell, (float) ($summary['total_final_wt'] ?? 0), DataType::TYPE_NUMERIC);
        $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.####');
        $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    } elseif ($totalLabelCol === null && $key === 'product_name') {
        $sheet->setCellValue($cell, 'Total');
        $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $totalLabelCol = $colLetter;
    } else {
        $sheet->setCellValue($cell, '');
    }
}

$sheet->getStyle('A' . $totalRow . ':' . $lastCol . $totalRow)->applyFromArray($thinBorder);
$sheet->getStyle('A' . $totalRow . ':' . $lastCol . $totalRow)->getFill()->applyFromArray($fillTotal);
$sheet->getStyle('A' . $totalRow . ':' . $lastCol . $totalRow)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');

for ($i = 0; $i < $num_cols; ++$i) {
    $key = $export_cols[$i];
    $col = Coordinate::stringFromColumnIndex($i + 1);
    $w = $width_hints[$key] ?? 14;
    $sheet->getColumnDimension($col)->setWidth((float) $w);
}

$fname = 'Barcode_Scan_Report_' . date('d_m_Y') . '.xlsx';
$fname = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $fname);

while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
