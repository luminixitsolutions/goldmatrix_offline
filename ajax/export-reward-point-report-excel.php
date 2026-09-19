<?php
/**
 * Reward Point Report — styled .xlsx (Jewelstep-style banner + green headers like KYC / ageing exports).
 * POST JSON: { from_date?, to_date?, search?, sort?, order?, columns?: string[] }
 * GET: same query string params; columns = comma-separated keys.
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_require_login.php';
require_once __DIR__ . '/../includes/auragold_reward_point_report_data.php';

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

/** @var array<string,string> */
$COLUMN_LABELS = [
    'customer_name'    => 'Customer Name',
    'invoice_no'       => 'Invoice No.',
    'invoice_date'     => 'Date',
    'generated_point'  => 'GeneratedPoint.',
    'redeemed_point'   => 'RedeemedPoint.',
    'redeem_value'     => 'RedeemValue.',
    'account_no'       => 'Account No.',
];

$DEFAULT_ORDER = ['customer_name', 'invoice_no', 'invoice_date', 'generated_point', 'redeemed_point', 'redeem_value', 'account_no'];
$NUMERIC_KEYS = ['generated_point' => true, 'redeemed_point' => true, 'redeem_value' => true];

$payload = null;
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    if ($raw !== false && trim($raw) !== '') {
        $payload = json_decode((string) $raw, true);
    }
}

if (is_array($payload)) {
    $from_date = isset($payload['from_date']) ? trim((string) $payload['from_date']) : '';
    $to_date = isset($payload['to_date']) ? trim((string) $payload['to_date']) : '';
    $search = isset($payload['search']) ? trim((string) esc($payload['search'])) : '';
    $sort = isset($payload['sort']) ? preg_replace('/[^a-z0-9_]/', '', (string) $payload['sort']) : 'invoice_date';
    $order = isset($payload['order']) ? strtolower(trim((string) $payload['order'])) : 'desc';
    $columns_req = isset($payload['columns']) && is_array($payload['columns']) ? $payload['columns'] : null;
} else {
    $from_date = isset($_GET['from_date']) ? trim((string) esc($_GET['from_date'])) : '';
    $to_date = isset($_GET['to_date']) ? trim((string) esc($_GET['to_date'])) : '';
    $search = isset($_GET['search']) ? trim((string) esc($_GET['search'])) : '';
    $sort = isset($_GET['sort']) ? preg_replace('/[^a-z0-9_]/', '', (string) $_GET['sort']) : 'invoice_date';
    $order = isset($_GET['order']) ? strtolower(trim((string) $_GET['order'])) : 'desc';
    $columns_req = isset($_GET['columns']) ? explode(',', (string) $_GET['columns']) : null;
}

if ($order !== 'asc' && $order !== 'desc') {
    $order = 'desc';
}

$allowed = array_flip(array_keys($COLUMN_LABELS));
$export_cols = [];
if (is_array($columns_req)) {
    $seen = [];
    foreach ($columns_req as $item) {
        $k = preg_replace('/[^a-z0-9_]/', '', (string) $item);
        if ($k === '' || !isset($allowed[$k]) || isset($seen[$k])) {
            continue;
        }
        $seen[$k] = true;
        $export_cols[] = $k;
    }
}
if ($export_cols === []) {
    $export_cols = $DEFAULT_ORDER;
}

$num_cols = count($export_cols);
if ($num_cols < 1) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No columns to export';
    exit;
}

$fetch = auragold_reward_point_report_fetch($conn, [
    'from_date'     => $from_date,
    'to_date'       => $to_date,
    'search'        => $search,
    'sort'          => $sort,
    'order'         => $order,
    'unlimited'     => true,
    'max_limit_cap' => 50000,
]);

if (!empty($fetch['error'])) {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Export failed.';
    exit;
}

$rows = $fetch['rows'];
$fd = $fetch['from_date'];
$td = $fetch['to_date'];

$fmtSlash = static function (string $ymd): string {
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m)) {
        return $ymd;
    }

    return $m[1] . '/' . $m[2] . '/' . $m[3];
};

$periodLine = 'Reward Point Report From :- ' . $fmtSlash($fd) . ' To :- ' . $fmtSlash($td);

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

$lastCol = Coordinate::stringFromColumnIndex($num_cols);

$spreadsheet = new Spreadsheet();
$spreadsheet->getDefaultStyle()->getFont()->setName('Calibri');
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('REWARD POINT REPORT');

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
    'customer_name'    => 28,
    'invoice_no'       => 16,
    'invoice_date'     => 14,
    'generated_point'  => 16,
    'redeemed_point'   => 16,
    'redeem_value'     => 14,
    'account_no'       => 14,
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

        if ($key === 'invoice_date') {
            $iso = isset($fr['invoice_date']) ? (string) $fr['invoice_date'] : '';
            $display = $iso;
            if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $iso, $m)) {
                $display = $m[3] . '/' . $m[2] . '/' . $m[1];
            }
            $sheet->setCellValue($cell, $display);
            $sheet->getStyle($cell)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_LEFT)
                ->setVertical(Alignment::VERTICAL_CENTER);
        } elseif (isset($NUMERIC_KEYS[$key])) {
            $v = isset($fr[$key]) ? $fr[$key] : '';
            $num = is_numeric($v) ? (float) $v : 0.0;
            $sheet->setCellValueExplicit($cell, $num, DataType::TYPE_NUMERIC);
            $sheet->getStyle($cell)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
                ->setVertical(Alignment::VERTICAL_CENTER);
            if ($key === 'redeem_value') {
                $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.00');
            } else {
                $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0.####');
            }
        } else {
            $sheet->setCellValue($cell, isset($fr[$key]) ? (string) $fr[$key] : '');
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
}

for ($i = 0; $i < $num_cols; ++$i) {
    $key = $export_cols[$i];
    $col = Coordinate::stringFromColumnIndex($i + 1);
    $w = $width_hints[$key] ?? 14;
    $sheet->getColumnDimension($col)->setWidth((float) $w);
}

$fname = 'Reward_Point_Report_' . date('d_m_Y') . '.xlsx';
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
