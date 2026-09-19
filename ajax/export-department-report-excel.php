<?php

/**
 * Department Report (Insource / Outsource) — Excel export for active dept or all depts.
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
require_once __DIR__ . '/../includes/department_report_build.php';
require_once __DIR__ . '/../includes/auragold_excel_financial_banner.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$report_date = isset($_GET['report_date']) ? trim((string) $_GET['report_date']) : date('d-m-Y');
if (!preg_match('/^\d{2}-\d{2}-\d{4}$/', $report_date)) {
    $report_date = date('d-m-Y');
}
$deptFilter = isset($_GET['dept']) ? (int) $_GET['dept'] : 0;
$exportAll = isset($_GET['all']) && (string) $_GET['all'] === '1';
$scope = isset($_GET['scope']) ? strtolower(trim((string) $_GET['scope'])) : 'insource';
$isOutsource = ($scope === 'outsource');
$scopeLabel = $isOutsource ? 'OUTSOURCE' : 'INHOUSE';
$scopeSlug = $isOutsource ? 'outsource' : 'insource';

$report_columns = auragold_department_report_columns();
$departments = $isOutsource
    ? auragold_department_report_load_outsource_departments($conn)
    : auragold_department_report_load_inhouse_departments($conn);
$tables = auragold_department_report_build_tables($conn, $departments, $report_columns, $report_date);
if (!is_array($tables)) {
    $tables = [];
}

if (!$exportAll && $deptFilter > 0) {
    $tables = array_values(array_filter($tables, static function ($b) use ($deptFilter) {
        return (int) ($b['id'] ?? 0) === $deptFilter;
    }));
}
if ($tables === [] && $deptFilter <= 0 && !$exportAll) {
    // Keep empty — still write a workbook with a notice sheet.
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

$headers = array_merge(['User / Worker'], array_column($report_columns, 'label'));
$nCols = count($headers);
$lastCol = Coordinate::stringFromColumnIndex($nCols);

$spreadsheet = new Spreadsheet();
$spreadsheet->removeSheetByIndex(0);

$navy = '11294B';
$gold = 'C9A227';
$goldSoft = 'F5ECD8';
$headerFill = [
    'fillType' => Fill::FILL_SOLID,
    'startColor' => ['rgb' => $navy],
];
$sumFill = [
    'fillType' => Fill::FILL_SOLID,
    'startColor' => ['rgb' => $goldSoft],
];
$thin = [
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']],
    ],
];

if ($tables === []) {
    $sheet = $spreadsheet->createSheet();
    $sheet->setTitle('Department Report');
    $title = 'DEPARTMENT REPORT (' . $scopeLabel . ')';
    $licenseLine = $licenseNo !== '' ? ('Business License No. : ' . $licenseNo) : ('Shop: ' . $shopName);
    auragold_excel_financial_banner_layout(
        $sheet,
        $lastCol,
        $title,
        $licenseLine,
        'Date: ' . $report_date,
        ['title_blue_rgb' => $navy, 'period_fill_rgb' => $goldSoft]
    );
    $sheet->setCellValue('A5', 'No department data to export for this date.');
} else {
    foreach ($tables as $ti => $block) {
        $titleRaw = (string) ($block['title'] ?? ('Dept ' . ($ti + 1)));
        $sheetTitle = preg_replace('/[\\\\\/\*\?\:\[\]]+/', ' ', $titleRaw);
        $sheetTitle = trim((string) $sheetTitle);
        if ($sheetTitle === '') {
            $sheetTitle = 'Dept ' . ($ti + 1);
        }
        if (strlen($sheetTitle) > 31) {
            $sheetTitle = substr($sheetTitle, 0, 31);
        }
        // Ensure unique sheet name
        $base = $sheetTitle;
        $suffix = 2;
        while ($spreadsheet->getSheetByName($sheetTitle) !== null) {
            $trunc = substr($base, 0, max(1, 31 - strlen((string) $suffix) - 1));
            $sheetTitle = $trunc . '-' . $suffix;
            $suffix++;
        }

        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle($sheetTitle);

        $title = 'DEPARTMENT REPORT (' . $scopeLabel . ') — ' . strtoupper($titleRaw);
        $licenseLine = $licenseNo !== '' ? ('Business License No. : ' . $licenseNo) : ('Shop: ' . $shopName);
        $headerRow = auragold_excel_financial_banner_layout(
            $sheet,
            $lastCol,
            $title,
            $licenseLine,
            'Date: ' . $report_date . '  |  Department: ' . $titleRaw,
            ['title_blue_rgb' => $navy, 'period_fill_rgb' => $goldSoft]
        );

        foreach ($headers as $ci => $h) {
            $col = Coordinate::stringFromColumnIndex($ci + 1);
            $sheet->setCellValue($col . $headerRow, $h);
        }
        $sheet->getStyle('A' . $headerRow . ':' . $lastCol . $headerRow)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => $headerFill,
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => $thin['borders'],
        ]);
        $sheet->getStyle('A' . $headerRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        $r = $headerRow + 1;
        $rows = is_array($block['rows'] ?? null) ? $block['rows'] : [];
        foreach ($rows as $row) {
            $sheet->setCellValueExplicit('A' . $r, (string) ($row['name'] ?? ''), DataType::TYPE_STRING);
            foreach ($report_columns as $ci => $colDef) {
                $col = Coordinate::stringFromColumnIndex($ci + 2);
                $val = (float) ($row[$colDef['key']] ?? 0);
                $sheet->setCellValueExplicit($col . $r, $val, DataType::TYPE_NUMERIC);
                $sheet->getStyle($col . $r)->getNumberFormat()->setFormatCode('0.000');
            }
            $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->applyFromArray($thin);
            $r++;
        }

        // Sum row
        $sheet->setCellValueExplicit('A' . $r, 'Sum:', DataType::TYPE_STRING);
        $sums = is_array($block['sums'] ?? null) ? $block['sums'] : [];
        foreach ($report_columns as $ci => $colDef) {
            $col = Coordinate::stringFromColumnIndex($ci + 2);
            $val = (float) ($sums[$colDef['key']] ?? 0);
            $sheet->setCellValueExplicit($col . $r, $val, DataType::TYPE_NUMERIC);
            $sheet->getStyle($col . $r)->getNumberFormat()->setFormatCode('0.000');
        }
        $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => $navy]],
            'fill' => $sumFill,
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $gold]],
            ],
        ]);

        $sheet->getColumnDimension('A')->setWidth(22);
        for ($ci = 2; $ci <= $nCols; $ci++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($ci))->setWidth(16);
        }
    }
}

if ($spreadsheet->getSheetCount() > 0) {
    $spreadsheet->setActiveSheetIndex(0);
}

$fname = 'department-report-' . $scopeSlug . '-' . preg_replace('/[^0-9\-]/', '', $report_date);
if (!$exportAll && $deptFilter > 0 && isset($tables[0]['title'])) {
    $safe = preg_replace('/[^A-Za-z0-9_\-]+/', '-', (string) $tables[0]['title']);
    $fname .= '-' . strtolower(trim($safe, '-'));
} elseif ($exportAll) {
    $fname .= '-all';
}
$fname .= '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
