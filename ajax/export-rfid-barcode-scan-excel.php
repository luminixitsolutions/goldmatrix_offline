<?php

/**
 * RFID / Barcode Scan — export Available + Scanned stock (POST JSON from page; same filters as screen).
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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** @return array<int, array{key: string, title: string}> */
function rfid_export_available_columns(): array
{
    return [
        ['key' => 'isScanned', 'title' => 'isScanned'],
        ['key' => 'branch', 'title' => 'Branch'],
        ['key' => 'carat', 'title' => 'Carat'],
        ['key' => 'metal', 'title' => 'Metal'],
        ['key' => 'product_code', 'title' => 'Product Code'],
        ['key' => 'article', 'title' => 'Article'],
        ['key' => 'rfid_code', 'title' => 'RFID Code'],
        ['key' => 'barcode', 'title' => 'Barcode'],
        ['key' => 'qty', 'title' => 'Qty'],
        ['key' => 'location', 'title' => 'Location'],
        ['key' => 'gross_wt', 'title' => 'Gross Wt'],
        ['key' => 'purity_wt', 'title' => 'Purity Wt'],
        ['key' => 'net_wt', 'title' => 'Net Wt'],
        ['key' => 'final_wt', 'title' => 'Final Wt'],
        ['key' => 'voucher_type', 'title' => 'Voucher Type'],
        ['key' => 'invoice_no', 'title' => 'Invoice No'],
    ];
}

/** @return array<int, array{key: string, title: string}> */
function rfid_export_scanned_columns(): array
{
    return [
        ['key' => 'active', 'title' => 'active'],
        ['key' => 'branch', 'title' => 'Branch'],
        ['key' => 'product_code', 'title' => 'Product Code'],
        ['key' => 'article', 'title' => 'Article'],
        ['key' => 'location', 'title' => 'Location'],
        ['key' => 'rfid_code', 'title' => 'RFID Code'],
        ['key' => 'barcode', 'title' => 'Barcode'],
        ['key' => 'qty', 'title' => 'Qty'],
        ['key' => 'metal', 'title' => 'Metal'],
        ['key' => 'gross_wt', 'title' => 'Gross Wt'],
        ['key' => 'purity_wt', 'title' => 'Purity Wt'],
        ['key' => 'net_wt', 'title' => 'Net Wt.'],
        ['key' => 'final_wt', 'title' => 'Final Wt.'],
        ['key' => 'voucher_type', 'title' => 'Voucher Type'],
    ];
}

/** @return list<string> */
function rfid_export_numeric_keys(): array
{
    return ['qty', 'gross_wt', 'purity_wt', 'net_wt', 'final_wt'];
}

/**
 * @param array<string, mixed> $row
 */
function rfid_export_cell_value(array $row, string $key)
{
    if (!array_key_exists($key, $row)) {
        return '';
    }
    $v = $row[$key];
    if ($v === null) {
        return '';
    }
    if (in_array($key, rfid_export_numeric_keys(), true)) {
        if ($v === '' || !is_numeric($v)) {
            return '';
        }
        return (float) $v;
    }

    return is_scalar($v) ? (string) $v : '';
}

/**
 * @param array<int, array<string, mixed>> $rows
 * @return array{qty: float, final_wt: float}
 */
function rfid_export_sum_totals(array $rows): array
{
    $qty = 0.0;
    $fw = 0.0;
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $q = $row['qty'] ?? null;
        if (is_numeric($q)) {
            $qty += (float) $q;
        }
        $w = $row['final_wt'] ?? null;
        if (is_numeric($w)) {
            $fw += (float) $w;
        }
    }

    return ['qty' => $qty, 'final_wt' => $fw];
}

/**
 * @param array<int, array<string, mixed>> $rows
 */
function rfid_export_write_sheet(
    \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
    string $lastCol,
    string $shopName,
    string $licenseLine,
    string $periodLine,
    array $columns,
    array $rows,
    string $totalLabel
): void {
    $nCols = count($columns);
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
    $numKeys = array_flip(rfid_export_numeric_keys());
    $peachStartIdx = null;
    foreach ($columns as $i => $col) {
        if (isset($numKeys[$col['key']])) {
            $peachStartIdx = $i;
            break;
        }
    }
    $peachStartCol = $peachStartIdx !== null
        ? Coordinate::stringFromColumnIndex($peachStartIdx + 1)
        : 'A';

    $hdrRow = auragold_excel_financial_banner_layout(
        $sheet,
        $lastCol,
        strtoupper($shopName),
        $licenseLine,
        $periodLine
    );

    for ($i = 0; $i < $nCols; ++$i) {
        $col = Coordinate::stringFromColumnIndex($i + 1);
        $sheet->setCellValue($col . $hdrRow, $columns[$i]['title']);
    }
    $sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->applyFromArray($thinBorder);
    $sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->getFill()->applyFromArray($fillMintHdr);
    $sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->getFont()->setBold(true);
    $sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

    $r = $hdrRow + 1;
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        for ($i = 0; $i < $nCols; ++$i) {
            $key = $columns[$i]['key'];
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $v = rfid_export_cell_value($row, $key);
            if (isset($numKeys[$key]) && $v !== '' && is_numeric($v)) {
                $sheet->setCellValueExplicit($col . $r, (float) $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            } else {
                $sheet->setCellValue($col . $r, is_scalar($v) ? (string) $v : '');
            }
        }
        $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->applyFromArray($thinBorder);
        if ($peachStartIdx !== null) {
            $sheet->getStyle($peachStartCol . $r . ':' . $lastCol . $r)->getFill()->applyFromArray($fillPeachMetric);
            $sheet->getStyle($peachStartCol . $r . ':' . $lastCol . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        ++$r;
    }

    $totals = rfid_export_sum_totals($rows);
    $footRow = $r;
    $sheet->setCellValue('A' . $footRow, $totalLabel);
    for ($i = 0; $i < $nCols; ++$i) {
        $key = $columns[$i]['key'];
        $col = Coordinate::stringFromColumnIndex($i + 1);
        if ($key === 'qty') {
            $sheet->setCellValueExplicit($col . $footRow, $totals['qty'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        } elseif ($key === 'final_wt') {
            $sheet->setCellValueExplicit($col . $footRow, $totals['final_wt'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        } elseif ($i > 0) {
            $sheet->setCellValue($col . $footRow, '');
        }
    }
    $sheet->getStyle('A' . $footRow . ':' . $lastCol . $footRow)->applyFromArray($thinBorder);
    $sheet->getStyle('A' . $footRow . ':' . $lastCol . $footRow)->getFill()->applyFromArray($fillTotalsBlue);
    $sheet->getStyle('A' . $footRow . ':' . $lastCol . $footRow)->getFont()->applyFromArray($fontTotalsWhite);
    $sheet->getStyle('A' . $footRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setVertical(Alignment::VERTICAL_CENTER);
    if ($peachStartIdx !== null) {
        $sheet->getStyle($peachStartCol . $footRow . ':' . $lastCol . $footRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    for ($ci = 1; $ci <= $nCols; ++$ci) {
        $sheet->getColumnDimensionByColumn($ci)->setAutoSize(true);
    }
}

$availJson = isset($_POST['available_json']) ? (string) $_POST['available_json'] : '[]';
$scanJson = isset($_POST['scanned_json']) ? (string) $_POST['scanned_json'] : '[]';
$unknownCount = isset($_POST['unknown_count']) ? (int) $_POST['unknown_count'] : 0;

$availRows = json_decode($availJson, true);
$scanRows = json_decode($scanJson, true);
if (!is_array($availRows)) {
    $availRows = [];
}
if (!is_array($scanRows)) {
    $scanRows = [];
}

$availTotals = rfid_export_sum_totals($availRows);
$scanTotals = rfid_export_sum_totals($scanRows);

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
$summaryLine = sprintf(
    'RFID / Barcode Scan — Unknown tag: %d | Available Wt: %s, Qty: %s | Scanned Wt: %s, Qty: %s',
    $unknownCount,
    number_format($availTotals['final_wt'], 3, '.', ''),
    number_format($availTotals['qty'], 0, '.', ''),
    number_format($scanTotals['final_wt'], 3, '.', ''),
    number_format($scanTotals['qty'], 0, '.', '')
);

$availCols = rfid_export_available_columns();
$scanCols = rfid_export_scanned_columns();
$availLastCol = Coordinate::stringFromColumnIndex(count($availCols));
$scanLastCol = Coordinate::stringFromColumnIndex(count($scanCols));

$spreadsheet = new Spreadsheet();
$sheetAvail = $spreadsheet->getActiveSheet();
$sheetAvail->setTitle('AVAILABLE STOCK');
rfid_export_write_sheet(
    $sheetAvail,
    $availLastCol,
    $shopName,
    $licenseLine,
    $summaryLine . ' — Available Stock',
    $availCols,
    $availRows,
    'Total'
);

$sheetScan = $spreadsheet->createSheet();
$sheetScan->setTitle('SCANNED STOCK');
rfid_export_write_sheet(
    $sheetScan,
    $scanLastCol,
    $shopName,
    $licenseLine,
    $summaryLine . ' — Scanned Stock',
    $scanCols,
    $scanRows,
    'Total'
);

$fname = 'RFID_Barcode_Scan_' . date('Y-m-d') . '.xlsx';
while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
