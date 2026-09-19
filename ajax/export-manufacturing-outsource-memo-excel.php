<?php

/**
 * Manufacturing Outsource — Memo In & Out report (.xlsx).
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_department_schema.php';
auragold_ensure_department_schema($conn);
require_once __DIR__ . '/../includes/auragold_require_login.php';
auragold_require_login_or_exit();

if (!isset($conn) || !($conn instanceof mysqli)) {
    header('HTTP/1.1 500 Internal Server Error');
    echo 'No database connection';
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/manufacturing_outsource_memo_list.php';
require_once __DIR__ . '/../includes/auragold_excel_financial_banner.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$filter_dept_id = isset($_GET['dept_id']) ? (int) $_GET['dept_id'] : 0;
$filter_user_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
$search = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
$outsource_dept_ids = auragold_outsource_department_ids($conn);
if ($filter_dept_id > 0 && !in_array($filter_dept_id, $outsource_dept_ids, true)) {
    $filter_dept_id = 0;
    $filter_user_id = 0;
}

$rows = mfg_outsource_load_memo_rows($conn, $filter_dept_id, $filter_user_id, $search, $outsource_dept_ids);
$columns = mfg_memo_column_defs();
$titles = array_column($columns, 'label');
$keys = array_column($columns, 'key');
$nCols = count($titles);
$lastCol = Coordinate::stringFromColumnIndex($nCols);

$numericKeys = ['issue_wt', 'receive_wt', 'pending_wt'];

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

$filterParts = [];
if ($filter_dept_id > 0) {
    $filterParts[] = 'Dept #' . $filter_dept_id;
}
if ($filter_user_id > 0) {
    $filterParts[] = 'User #' . $filter_user_id;
}
if ($search !== '') {
    $filterParts[] = 'Search: ' . $search;
}
$periodLine = 'Memo In & Out — Issue / Receive / Pending by Jobwork Order & Item';
if ($filterParts !== []) {
    $periodLine .= ' (' . implode(' · ', $filterParts) . ')';
}

$thinBorder = [
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
    ],
];
$headerFill = [
    'fillType' => Fill::FILL_SOLID,
    'startColor' => ['rgb' => 'E8EDF8'],
];

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Memo In Out');

$headerRow = auragold_excel_financial_banner_layout(
    $sheet,
    $lastCol,
    strtoupper($shopName),
    'Business License No - ' . ($licenseNo !== '' ? $licenseNo : '—'),
    $periodLine
);

foreach ($titles as $i => $title) {
    $col = Coordinate::stringFromColumnIndex($i + 1);
    $sheet->setCellValue($col . $headerRow, $title);
}
$sheet->getStyle('A' . $headerRow . ':' . $lastCol . $headerRow)->getFont()->setBold(true);
$sheet->getStyle('A' . $headerRow . ':' . $lastCol . $headerRow)->getFill()->applyFromArray($headerFill);
$sheet->getStyle('A' . $headerRow . ':' . $lastCol . $headerRow)->applyFromArray($thinBorder);
$sheet->getStyle('A' . $headerRow . ':' . $lastCol . $headerRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$dataRow = $headerRow + 1;
$totIssue = 0.0;
$totRecv = 0.0;
$totPending = 0.0;

foreach ($rows as $r) {
    foreach ($keys as $ci => $key) {
        $col = Coordinate::stringFromColumnIndex($ci + 1);
        $val = mfg_memo_row_export_value($r, $key);
        if (in_array($key, $numericKeys, true)) {
            $num = (float) str_replace(',', '', $val);
            $sheet->setCellValue($col . $dataRow, $num !== 0.0 ? $num : '');
            $sheet->getStyle($col . $dataRow)->getNumberFormat()->setFormatCode('0.000');
            $sheet->getStyle($col . $dataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            if ($key === 'issue_wt') {
                $totIssue += $num;
            } elseif ($key === 'receive_wt') {
                $totRecv += $num;
            } elseif ($key === 'pending_wt') {
                $totPending += $num;
            }
        } else {
            $sheet->setCellValueExplicit($col . $dataRow, $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        }
    }
    $sheet->getStyle('A' . $dataRow . ':' . $lastCol . $dataRow)->applyFromArray($thinBorder);
    $dataRow++;
}

if ($rows !== []) {
    $sheet->setCellValue('A' . $dataRow, 'TOTAL');
    $sheet->getStyle('A' . $dataRow)->getFont()->setBold(true);
    foreach ($keys as $ci => $key) {
        if (!in_array($key, $numericKeys, true)) {
            continue;
        }
        $col = Coordinate::stringFromColumnIndex($ci + 1);
        $tot = $key === 'issue_wt' ? $totIssue : ($key === 'receive_wt' ? $totRecv : $totPending);
        $sheet->setCellValue($col . $dataRow, $tot !== 0.0 ? $tot : '');
        $sheet->getStyle($col . $dataRow)->getNumberFormat()->setFormatCode('0.000');
        $sheet->getStyle($col . $dataRow)->getFont()->setBold(true);
        $sheet->getStyle($col . $dataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }
    $sheet->getStyle('A' . $dataRow . ':' . $lastCol . $dataRow)->applyFromArray($thinBorder);
}

for ($i = 1; $i <= $nCols; $i++) {
    $col = Coordinate::stringFromColumnIndex($i);
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$fname = 'Memo_In_Out_' . date('Y-m-d') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
