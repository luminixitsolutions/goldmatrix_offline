<?php

/**
 * Loss Tracking Excel export — branded .xlsx (PhpSpreadsheet), same banner style as Ageing Report.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_require_login.php';
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

$raw = file_get_contents('php://input');
$payload = json_decode((string) $raw, true);
if (!is_array($payload)) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Invalid JSON body';
    exit;
}

$dateFrom = isset($payload['date_from']) ? trim((string) $payload['date_from']) : '';
$dateTo = isset($payload['date_to']) ? trim((string) $payload['date_to']) : '';
if ($dateFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = '';
}
if ($dateTo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = '';
}

$columnsIn = isset($payload['columns']) && is_array($payload['columns']) ? $payload['columns'] : [];
$rowsIn = isset($payload['rows']) && is_array($payload['rows']) ? $payload['rows'] : [];
$footerIn = isset($payload['footer']) && is_array($payload['footer']) ? $payload['footer'] : [];

if (count($columnsIn) < 1) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No columns';
    exit;
}
if (count($rowsIn) > 8000) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Too many rows';
    exit;
}

$columns = [];
foreach ($columnsIn as $col) {
    if (!is_array($col)) {
        continue;
    }
    $key = isset($col['key']) ? trim((string) $col['key']) : '';
    if ($key === '') {
        continue;
    }
    $label = isset($col['label']) ? trim((string) $col['label']) : $key;
    $columns[] = ['key' => $key, 'label' => $label !== '' ? $label : $key];
}
if (count($columns) < 1) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'No columns';
    exit;
}

$numericKeys = ['out_sum', 'loss_sum'];
$rightAlignKeys = array_merge($numericKeys, ['sr_no']);

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

$fSlash = $dateFrom !== '' ? date('Y/m/d', strtotime($dateFrom . ' 12:00:00')) : '';
$tSlash = $dateTo !== '' ? date('Y/m/d', strtotime($dateTo . ' 12:00:00')) : '';
if ($fSlash !== '' && $tSlash !== '') {
    $periodLine = 'Loss Tracking Report From :- ' . $fSlash . ' To :- ' . $tSlash;
} elseif ($fSlash !== '') {
    $periodLine = 'Loss Tracking Report From :- ' . $fSlash . ' To :- —';
} elseif ($tSlash !== '') {
    $periodLine = 'Loss Tracking Report Up To :- ' . $tSlash;
} else {
    $periodLine = 'Loss Tracking Report';
}

$BANNER_BLUE = '4472C4';
$fillGreenHdr = [
    'fillType'   => Fill::FILL_SOLID,
    'startColor' => ['rgb' => 'C8E6C9'],
];
$fillTotalsBlue = [
    'fillType'   => Fill::FILL_SOLID,
    'startColor' => ['rgb' => $BANNER_BLUE],
];
$fontTotalsWhite = ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11];
$thinBorder = [
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
    ],
];

$colCount = count($columns);
$lastCol = Coordinate::stringFromColumnIndex($colCount);

$spreadsheet = new Spreadsheet();
$spreadsheet->getDefaultStyle()->getFont()->setName('Calibri');
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('LOSS TRACKING');

auragold_excel_financial_banner_layout(
    $sheet,
    $lastCol,
    strtoupper($shopName),
    'Business License No - ' . $licenseNo,
    $periodLine,
    ['title_blue_rgb' => $BANNER_BLUE, 'period_fill_rgb' => 'F8CBAD']
);

$hdrRow = 5;
foreach ($columns as $i => $col) {
    $colLetter = Coordinate::stringFromColumnIndex($i + 1);
    $sheet->setCellValue($colLetter . $hdrRow, $col['label']);
}
$sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->applyFromArray($thinBorder);
$sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->getFont()->setBold(true);
$sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->getFill()->applyFromArray($fillGreenHdr);
$sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

foreach ($columns as $i => $col) {
    if (!in_array($col['key'], $rightAlignKeys, true)) {
        continue;
    }
    $colLetter = Coordinate::stringFromColumnIndex($i + 1);
    $sheet->getStyle($colLetter . $hdrRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
}

$r = $hdrRow + 1;
$hasData = false;
foreach ($rowsIn as $row) {
    if (!is_array($row)) {
        continue;
    }
    foreach ($columns as $i => $col) {
        $colLetter = Coordinate::stringFromColumnIndex($i + 1);
        $key = $col['key'];
        $v = isset($row[$key]) ? $row[$key] : '';
        if (in_array($key, $numericKeys, true) && $v !== '' && is_numeric($v)) {
            $sheet->setCellValueExplicit($colLetter . $r, (float) $v, DataType::TYPE_NUMERIC);
        } elseif ($key === 'sr_no' && $v !== '' && is_numeric($v)) {
            $sheet->setCellValueExplicit($colLetter . $r, (int) $v, DataType::TYPE_NUMERIC);
        } else {
            $sheet->setCellValue($colLetter . $r, is_scalar($v) ? (string) $v : '');
        }
    }
    $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->applyFromArray($thinBorder);
    foreach ($columns as $i => $col) {
        if (!in_array($col['key'], $rightAlignKeys, true)) {
            continue;
        }
        $colLetter = Coordinate::stringFromColumnIndex($i + 1);
        $sheet->getStyle($colLetter . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        if (in_array($col['key'], $numericKeys, true)) {
            $sheet->getStyle($colLetter . $r)->getNumberFormat()->setFormatCode('#,##0.00_);[Red](#,##0.00)');
        }
    }
    ++$r;
    $hasData = true;
}

if (!$hasData) {
    foreach ($columns as $i => $col) {
        $colLetter = Coordinate::stringFromColumnIndex($i + 1);
        $sheet->setCellValue($colLetter . $r, '');
    }
    $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->applyFromArray($thinBorder);
    ++$r;
}

$totalColIdx = null;
foreach ($columns as $i => $col) {
    $fv = isset($footerIn[$col['key']]) ? trim((string) $footerIn[$col['key']]) : '';
    if (strcasecmp($fv, 'Total') === 0) {
        $totalColIdx = $i;
        break;
    }
}

$mergeEndIdx = null;
if ($totalColIdx !== null && $totalColIdx > 0) {
    $mergeEndIdx = $totalColIdx;
} else {
    $firstNumIdx = null;
    foreach ($columns as $i => $col) {
        if (in_array($col['key'], $numericKeys, true)) {
            $firstNumIdx = $i;
            break;
        }
    }
    if ($firstNumIdx !== null && $firstNumIdx > 0) {
        $mergeEndIdx = $firstNumIdx - 1;
    }
}

if ($mergeEndIdx !== null) {
    $mergeStart = 'A' . $r;
    $mergeEnd = Coordinate::stringFromColumnIndex($mergeEndIdx + 1) . $r;
    $sheet->mergeCells($mergeStart . ':' . $mergeEnd);
    $sheet->setCellValue('A' . $r, 'Total');
    foreach ($columns as $i => $col) {
        if ($i <= $mergeEndIdx) {
            continue;
        }
        $colLetter = Coordinate::stringFromColumnIndex($i + 1);
        $key = $col['key'];
        $v = isset($footerIn[$key]) ? trim((string) $footerIn[$key]) : '';
        if (in_array($key, $numericKeys, true) && $v !== '' && is_numeric(str_replace(',', '', $v))) {
            $sheet->setCellValueExplicit($colLetter . $r, (float) str_replace(',', '', $v), DataType::TYPE_NUMERIC);
        } else {
            $sheet->setCellValue($colLetter . $r, $v);
        }
    }
} else {
    foreach ($columns as $i => $col) {
        $colLetter = Coordinate::stringFromColumnIndex($i + 1);
        $key = $col['key'];
        $v = isset($footerIn[$key]) ? trim((string) $footerIn[$key]) : '';
        if (in_array($key, $numericKeys, true) && $v !== '' && is_numeric(str_replace(',', '', $v))) {
            $sheet->setCellValueExplicit($colLetter . $r, (float) str_replace(',', '', $v), DataType::TYPE_NUMERIC);
        } else {
            $sheet->setCellValue($colLetter . $r, $v);
        }
    }
}

$sheet->getStyle('A' . $r . ':' . $lastCol . $r)->applyFromArray($thinBorder);
$sheet->getStyle('A' . $r . ':' . $lastCol . $r)->getFont()->applyFromArray($fontTotalsWhite);
$sheet->getStyle('A' . $r . ':' . $lastCol . $r)->getFill()->applyFromArray($fillTotalsBlue);
$sheet->getStyle('A' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setVertical(Alignment::VERTICAL_CENTER);
foreach ($columns as $i => $col) {
    if (!in_array($col['key'], $rightAlignKeys, true)) {
        continue;
    }
    $colLetter = Coordinate::stringFromColumnIndex($i + 1);
    $sheet->getStyle($colLetter . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    if (in_array($col['key'], $numericKeys, true)) {
        $sheet->getStyle($colLetter . $r)->getNumberFormat()->setFormatCode('#,##0.00_);[Red](#,##0.00)');
    }
}

foreach (range(1, $colCount) as $ci) {
    $sheet->getColumnDimensionByColumn($ci)->setAutoSize(true);
}

$stamp = $dateTo !== '' ? str_replace('-', '_', $dateTo) : ($dateFrom !== '' ? str_replace('-', '_', $dateFrom) : date('Y_m_d'));
$fname = 'Loss_Tracking_Report_' . $stamp . '.xlsx';

while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
