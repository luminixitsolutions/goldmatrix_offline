<?php

/**
 * Diamond Stock — serial list export to styled .xlsx (financial banner; same scope as screen / session branch).
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
require_once __DIR__ . '/../includes/diamond_stock_export_data.php';
require_once __DIR__ . '/../includes/auragold_excel_financial_banner.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$data = auragold_diamond_stock_export_fetch_rows($conn, 15000);
if ($data['error'] !== '') {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/plain; charset=utf-8');
    echo $data['error'];
    exit;
}

$rows = $data['rows'];
$siteUrl = $SiteUrl ?? null;
$keys = auragold_diamond_stock_export_column_keys();
$titles = auragold_diamond_stock_export_header_titles();
$nCols = count($titles);
$lastCol = Coordinate::stringFromColumnIndex($nCols);
$numKeySet = array_flip(auragold_diamond_stock_export_numeric_keys());

$grand = auragold_diamond_stock_export_grand_initial();
$wps = 0.0;
$wpc = 0;
$rowScalars = [];
foreach ($rows as $r) {
    $s = auragold_diamond_stock_export_row_scalars($r, $siteUrl);
    $rowScalars[] = $s;
    $acc = auragold_diamond_stock_export_accumulate_grand($grand, $wps, $wpc, $s);
    $grand = $acc['grand'];
    $wps = $acc['wastage_per_sum'];
    $wpc = $acc['wastage_per_cnt'];
}
$wastagePerAvg = ($wpc > 0) ? ($wps / $wpc) : null;

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
$periodLine = 'Diamond Stock — serial list (up to 15,000 rows; same branch scope as screen)';

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
$sheet->setTitle('DIAMOND STOCK');

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

$peachStart = 'G';
$r = $hdrRow + 1;
foreach ($rowScalars as $s) {
    for ($i = 0; $i < $nCols; ++$i) {
        $ck = $keys[$i];
        $col = Coordinate::stringFromColumnIndex($i + 1);
        $v = $s[$ck] ?? '';
        if (isset($numKeySet[$ck]) && ($v === null || $v === '')) {
            $sheet->setCellValue($col . $r, '');
        } elseif (isset($numKeySet[$ck]) && is_numeric($v)) {
            $fv = (float) $v;
            if ($ck === 'qty') {
                $sheet->setCellValueExplicit($col . $r, $fv, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            } elseif (in_array($ck, ['metal_cost', 'making_cost', 'making_charge_amt', 'stone_cost', 'purchase_amount', 'metal_value', 'stone_rate', 'stone_amt', 'making_charge_rate'], true)) {
                $sheet->setCellValueExplicit($col . $r, round($fv, 2), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            } else {
                $sheet->setCellValueExplicit($col . $r, $fv, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            }
        } else {
            $sheet->setCellValue($col . $r, is_scalar($v) ? (string) $v : '');
        }
    }
    $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->applyFromArray($thinBorder);
    $sheet->getStyle($peachStart . $r . ':' . $lastCol . $r)->getFill()->applyFromArray($fillPeachMetric);
    $sheet->getStyle($peachStart . $r . ':' . $lastCol . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    ++$r;
}

$footRow = $r;
$footMap = static function (string $k) use ($grand, $wastagePerAvg): string {
    if ($k === 'imageUrls') {
        return 'Grand Total';
    }
    if ($k === 'weight') {
        return (string) round($grand['weight'], 3);
    }
    if ($k === 'gross_wt') {
        return (string) round($grand['gross_wt'], 3);
    }
    if ($k === 'purity_wt') {
        return (string) round($grand['purity_wt'], 3);
    }
    if ($k === 'qty') {
        return (string) round($grand['qty'], 2);
    }
    if ($k === 'stone_wt') {
        return (string) round($grand['stone_wt'], 3);
    }
    if ($k === 'net_wt') {
        return (string) round($grand['net_wt'], 3);
    }
    if ($k === 'wastage_wt') {
        return (string) round($grand['wastage_wt'], 3);
    }
    if ($k === 'wastage_per') {
        return $wastagePerAvg !== null ? (string) round($wastagePerAvg, 2) : '';
    }
    if ($k === 'metal_cost') {
        return (string) round($grand['metal_cost'], 2);
    }
    if ($k === 'making_cost') {
        return (string) round($grand['making_cost'], 2);
    }
    if ($k === 'making_charge_amt') {
        return (string) round($grand['making_charge_amt'], 2);
    }
    if ($k === 'stone_cost') {
        return (string) round($grand['stone_cost'], 2);
    }
    if ($k === 'purchase_amount') {
        return (string) round($grand['purchase_amount'], 2);
    }
    if ($k === 'metal_value') {
        return (string) round($grand['metal_value'], 2);
    }
    if ($k === 'stone_amt') {
        return (string) round($grand['stone_amt'], 2);
    }
    if (in_array($k, ['stone_rate', 'making_charge_rate'], true)) {
        return '—';
    }

    return '';
};

for ($i = 0; $i < $nCols; ++$i) {
    $ck = $keys[$i];
    $col = Coordinate::stringFromColumnIndex($i + 1);
    $cellStr = $footMap($ck);
    if (isset($numKeySet[$ck]) && $cellStr !== '' && $cellStr !== '—' && is_numeric($cellStr)) {
        $fv = (float) $cellStr;
        if ($ck === 'qty') {
            $sheet->setCellValueExplicit($col . $footRow, $fv, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        } elseif (in_array($ck, ['metal_cost', 'making_cost', 'making_charge_amt', 'stone_cost', 'purchase_amount', 'metal_value', 'stone_rate', 'stone_amt', 'making_charge_rate'], true) && $cellStr !== '—') {
            $sheet->setCellValueExplicit($col . $footRow, round($fv, 2), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        } elseif ($cellStr !== '—') {
            $sheet->setCellValueExplicit($col . $footRow, $fv, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        } else {
            $sheet->setCellValue($col . $footRow, $cellStr);
        }
    } else {
        $sheet->setCellValue($col . $footRow, $cellStr);
    }
}

$sheet->getStyle('A' . $footRow . ':' . $lastCol . $footRow)->applyFromArray($thinBorder);
$sheet->getStyle('A' . $footRow . ':' . $lastCol . $footRow)->getFill()->applyFromArray($fillTotalsBlue);
$sheet->getStyle('A' . $footRow . ':' . $lastCol . $footRow)->getFont()->applyFromArray($fontTotalsWhite);
$sheet->getStyle('A' . $footRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle($peachStart . $footRow . ':' . $lastCol . $footRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

for ($ci = 1; $ci <= $nCols; ++$ci) {
    $sheet->getColumnDimensionByColumn($ci)->setAutoSize(true);
}

$fname = 'Diamond_Stock_' . date('Y-m-d') . '.xlsx';
while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
