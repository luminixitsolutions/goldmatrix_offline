<?php
/**
 * Cheque Entry / PDC list — styled .xlsx export.
 * POST JSON: { columns: string[], search?, settings_branch_id?, filters?: {...} }
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_require_login.php';
auragold_require_login_or_exit();

require_once __DIR__ . '/../includes/auragold_cheque_entry_schema.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/auragold_excel_financial_banner.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** @var array<string,string> */
$column_labels = [
    'sr-no'                  => 'Sr.No',
    'pdc-no'                 => 'PDC No.',
    'account-no'             => 'Account No',
    'account-ledger'         => 'Account Ledger',
    'bank-name'              => 'Bank Name.',
    'cheque-no'              => 'Cheque No.',
    'cheque-date'            => 'Cheque Date',
    'pay-date'               => 'Pay Dt.',
    'amount'                 => 'Amount',
    'branch-name'            => 'Branch Name',
    'status'                 => 'Status',
    'bounced-cleared-date'   => 'Bounced/Cleared Date',
    'against-voucher-no'     => 'Against Voucher No.',
    'against-voucher-type'   => 'Against Voucher Type',
    'nsf-fees'               => 'NSF Fees',
    'recoverable'            => 'Recoverable',
    'invoice-date'           => 'Invoice Date',
    'reference-voucher-type' => 'Refrence Voucher Type',
    'ref-invoice-no'         => 'Ref Invoice No.',
    'comment'                => 'Comment',
    'pdc-voucher-type'       => 'PDC VoucherType',
];

$default_export_order = array_keys($column_labels);

$payload = null;
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = file_get_contents('php://input');
    $payload = json_decode((string) $raw, true);
}

if (!is_array($payload)) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Invalid request';
    exit;
}

$search = isset($payload['search']) ? esc(trim((string) $payload['search'])) : '';
$settings_branch_id = isset($payload['settings_branch_id']) ? (int) $payload['settings_branch_id'] : 0;
$filters = isset($payload['filters']) && is_array($payload['filters']) ? $payload['filters'] : [];
$columns_req = isset($payload['columns']) && is_array($payload['columns']) ? $payload['columns'] : null;

$allowed_keys = array_flip(array_keys($column_labels));
$export_cols = [];
if (is_array($columns_req)) {
    $seen = [];
    foreach ($columns_req as $item) {
        $k = preg_replace('/[^a-z0-9\-]/', '', (string) $item);
        if ($k === '' || !isset($allowed_keys[$k]) || isset($seen[$k])) {
            continue;
        }
        $seen[$k] = true;
        $export_cols[] = $k;
    }
}
if ($export_cols === []) {
    $export_cols = $default_export_order;
}

$num_cols = count($export_cols);
if ($num_cols < 1) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No columns to export';
    exit;
}

auragold_ensure_tbl_cheque_entry($conn);
$branch_id = auragold_cheque_entry_resolve_branch_id($settings_branch_id > 0 ? $settings_branch_id : null);
$entries = auragold_get_cheque_entries($conn, $branch_id, $search, 5000, 0, $filters);

$formatted = [];
$total_amount = 0.0;
$sr = 0;
foreach ($entries as $row) {
    ++$sr;
    $total_amount += (float) ($row['amount'] ?? 0);
    $formatted[] = [
        'sr-no'                  => (string) $sr,
        'pdc-no'                 => (string) ($row['pdc_no'] ?? ''),
        'account-no'             => (string) ($row['account_no'] ?? ''),
        'account-ledger'         => (string) ($row['account_ledger'] ?? ''),
        'bank-name'              => (string) ($row['bank_name'] ?? ''),
        'cheque-no'              => (string) ($row['cheque_no'] ?? ''),
        'cheque-date'            => (string) ($row['cheque_date_fmt'] ?? ''),
        'pay-date'               => (string) ($row['pay_date_fmt'] ?? ''),
        'amount'                 => number_format((float) ($row['amount'] ?? 0), 2, '.', ''),
        'branch-name'            => (string) ($row['branch_name'] ?? ''),
        'status'                 => (string) ($row['status'] ?? ''),
        'bounced-cleared-date'   => (string) ($row['bounced_cleared_date_fmt'] ?? ''),
        'against-voucher-no'     => (string) ($row['against_voucher_no'] ?? ''),
        'against-voucher-type'   => (string) ($row['against_voucher_type'] ?? ''),
        'nsf-fees'               => number_format((float) ($row['nsf_fees'] ?? 0), 2, '.', ''),
        'recoverable'            => (int) ($row['recoverable'] ?? 0) === 1 ? 'Yes' : 'No',
        'invoice-date'           => (string) ($row['invoice_date_fmt'] ?? ''),
        'reference-voucher-type' => (string) ($row['reference_voucher_type'] ?? ''),
        'ref-invoice-no'         => (string) ($row['ref_invoice_no'] ?? ''),
        'comment'                => (string) ($row['comment'] ?? ''),
        'pdc-voucher-type'       => (string) ($row['pdc_voucher_type'] ?? ''),
    ];
}

$shopName = defined('COMPANY_NAME') ? (string) COMPANY_NAME : 'Gold Matrix';
$licenseNo = '';
$targetBranchId = $branch_id;
if ($targetBranchId <= 0 && function_exists('auragold_effective_branch_id')) {
    $targetBranchId = (int) auragold_effective_branch_id();
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

$periodFromSlash = date('Y/m/d');
$periodToSlash = date('Y/m/d');
$chequeFrom = auragold_cheque_entry_parse_date_filter($filters['cheque_date_from'] ?? '');
$chequeTo = auragold_cheque_entry_parse_date_filter($filters['cheque_date_to'] ?? '');
if ($chequeFrom !== '') {
    $ts = strtotime($chequeFrom);
    if ($ts !== false) {
        $periodFromSlash = date('Y/m/d', $ts);
    }
}
if ($chequeTo !== '') {
    $ts = strtotime($chequeTo);
    if ($ts !== false) {
        $periodToSlash = date('Y/m/d', $ts);
    }
} elseif (!empty($_SESSION['financial_year']) && is_array($_SESSION['financial_year'])) {
    $fyS = trim((string) ($_SESSION['financial_year']['start_date'] ?? ''));
    $fyE = trim((string) ($_SESSION['financial_year']['end_date'] ?? ''));
    $tsS = $fyS !== '' ? strtotime($fyS) : false;
    $tsE = $fyE !== '' ? strtotime($fyE) : false;
    if ($tsS !== false) {
        $periodFromSlash = date('Y/m/d', $tsS);
    }
    if ($tsE !== false) {
        $periodToSlash = date('Y/m/d', $tsE);
    }
}

$periodLine = 'Cheque Entry Report From :- ' . $periodFromSlash . ' To :- ' . $periodToSlash;

$lastCol = Coordinate::stringFromColumnIndex($num_cols);

$thinBorder = [
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
    ],
];
$fillGreenHdr = [
    'fillType'   => Fill::FILL_SOLID,
    'startColor' => ['rgb' => 'C8E6C9'],
];
$fillAltRow = [
    'fillType'   => Fill::FILL_SOLID,
    'startColor' => ['rgb' => 'E3F2FD'],
];
$fillTotalRow = [
    'fillType'   => Fill::FILL_SOLID,
    'startColor' => ['rgb' => 'FFF9C4'],
];

$width_hints = [
    'sr-no'                  => 8,
    'pdc-no'                 => 14,
    'account-no'             => 14,
    'account-ledger'         => 24,
    'bank-name'              => 18,
    'cheque-no'              => 14,
    'cheque-date'            => 14,
    'pay-date'               => 12,
    'amount'                 => 14,
    'branch-name'            => 16,
    'status'                 => 12,
    'bounced-cleared-date'   => 18,
    'against-voucher-no'     => 18,
    'against-voucher-type'   => 18,
    'nsf-fees'               => 12,
    'recoverable'            => 12,
    'invoice-date'           => 14,
    'reference-voucher-type' => 18,
    'ref-invoice-no'         => 16,
    'comment'                => 28,
    'pdc-voucher-type'       => 16,
];

$right_align_keys = ['amount', 'nsf-fees', 'sr-no'];

$spreadsheet = new Spreadsheet();
$spreadsheet->getDefaultStyle()->getFont()->setName('Calibri');
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Cheque Entry');

$hdrRow = auragold_excel_financial_banner_layout(
    $sheet,
    $lastCol,
    strtoupper($shopName),
    'Business License No -' . ($licenseNo !== '' ? ' ' . $licenseNo : ''),
    $periodLine,
    [
        'title_font'       => ['size' => 18],
        'title_row_height' => 36,
        'license_font'     => ['color' => ['rgb' => '000000'], 'bold' => false],
        'period_font'      => ['color' => ['rgb' => '000000']],
    ]
);

for ($i = 0; $i < $num_cols; $i++) {
    $key = $export_cols[$i];
    $col = Coordinate::stringFromColumnIndex($i + 1);
    $sheet->setCellValue($col . $hdrRow, $column_labels[$key] ?? $key);
}
$sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->applyFromArray($thinBorder);
$sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->getFont()->setBold(true);
$sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->getFill()->applyFromArray($fillGreenHdr);
$sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER);

$r = $hdrRow + 1;
$rowIndex = 0;
foreach ($formatted as $fr) {
    for ($i = 0; $i < $num_cols; $i++) {
        $key = $export_cols[$i];
        $col = Coordinate::stringFromColumnIndex($i + 1);
        $val = isset($fr[$key]) ? (string) $fr[$key] : '';
        $sheet->setCellValue($col . $r, $val);
    }
    $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->applyFromArray($thinBorder);
    $align = Alignment::HORIZONTAL_LEFT;
    $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->getAlignment()
        ->setHorizontal($align)
        ->setVertical(Alignment::VERTICAL_CENTER);
    if ($rowIndex % 2 === 1) {
        $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->getFill()->applyFromArray($fillAltRow);
    }
    for ($i = 0; $i < $num_cols; $i++) {
        $key = $export_cols[$i];
        if (in_array($key, $right_align_keys, true)) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $sheet->getStyle($col . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        if ($key === 'status') {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $statusVal = strtolower(trim((string) ($fr['status'] ?? '')));
            if ($statusVal === 'cleared') {
                $sheet->getStyle($col . $r)->getFont()->getColor()->setRGB('166534');
            } elseif ($statusVal === 'bounced') {
                $sheet->getStyle($col . $r)->getFont()->getColor()->setRGB('991B1B');
            } elseif ($statusVal === 'pending') {
                $sheet->getStyle($col . $r)->getFont()->getColor()->setRGB('92400E');
            }
        }
    }
    ++$r;
    ++$rowIndex;
}

$amountColIdx = array_search('amount', $export_cols, true);
if ($amountColIdx !== false && count($formatted) > 0) {
    $totalLabelCol = Coordinate::stringFromColumnIndex(max(1, $amountColIdx));
    $amountCol = Coordinate::stringFromColumnIndex($amountColIdx + 1);
    $sheet->setCellValue($totalLabelCol . $r, 'Total Amount');
    $sheet->setCellValue($amountCol . $r, number_format($total_amount, 2, '.', ''));
    $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->applyFromArray($thinBorder);
    $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->getFill()->applyFromArray($fillTotalRow);
    $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->getFont()->setBold(true);
    $sheet->getStyle($amountCol . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
}

for ($i = 0; $i < $num_cols; $i++) {
    $key = $export_cols[$i];
    $col = Coordinate::stringFromColumnIndex($i + 1);
    $w = $width_hints[$key] ?? 14;
    $sheet->getColumnDimension($col)->setWidth((float) $w);
}

$filename = 'Cheque_Entry_Report_' . date('d_m_Y') . '.xlsx';
$filename = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $filename);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
