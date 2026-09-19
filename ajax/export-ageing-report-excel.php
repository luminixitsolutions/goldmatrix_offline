<?php

/**
 * Ageing Report Excel export — shared financial banner (blue title, license row,
 * pastel period band), light-green column headers, branded totals row.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_require_login.php';
auragold_require_login_or_exit();

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/auragold_excel_financial_banner.php';

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

$tab = isset($payload['tab']) ? trim((string) $payload['tab']) : 'ledger';
if ($tab !== 'ledger' && $tab !== 'stock') {
    $tab = 'ledger';
}

$agingDate = isset($payload['aging_date']) ? trim((string) $payload['aging_date']) : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $agingDate)) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Invalid aging_date';
    exit;
}

$rowsIn = isset($payload['rows']) && is_array($payload['rows']) ? $payload['rows'] : [];
$totalsIn = isset($payload['totals']) && is_array($payload['totals']) ? $payload['totals'] : [];

if (count($rowsIn) > 8000) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Too many rows';
    exit;
}

$shopName  = defined('COMPANY_NAME') ? (string) COMPANY_NAME : 'Gold Matrix';
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

$tsBanner = strtotime($agingDate . ' 12:00:00');
$periodFromSlash = $tsBanner ? date('Y/m/01', $tsBanner) : date('Y/m/01');
$periodToSlash = $tsBanner ? date('Y/m/t', $tsBanner) : date('Y/m/t');
$fromToLineLedger = 'Ageing Report From :- ' . $periodFromSlash . ' To :- ' . $periodToSlash;
$fromToLineStock  = 'Stock Ageing Report From :- ' . $periodFromSlash . ' To :- ' . $periodToSlash;

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

$spreadsheet = new Spreadsheet();
$spreadsheet->getDefaultStyle()->getFont()->setName('Calibri');
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('AGEING REPORT');

if ($tab === 'ledger') {
    $lastCol = 'K';
    $headers = [
        'Account Ledger',
        'Voucher Name',
        'Account No',
        'Invoice No.',
        'Date',
        '1 to 30 Days',
        '30 to 60 Days',
        '60 to 90 Days',
        '90 to 120 Days',
        '120 Above',
        'Total',
    ];

    auragold_excel_financial_banner_layout(
        $sheet,
        $lastCol,
        strtoupper($shopName),
        'Business License No - ' . $licenseNo,
        $fromToLineLedger,
        ['title_blue_rgb' => $BANNER_BLUE, 'period_fill_rgb' => 'F8CBAD']
    );

    $hdrRow = 5;
    $colIndex = 0;
    foreach ($headers as $h) {
        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
        $sheet->setCellValue($col . $hdrRow, $h);
        ++$colIndex;
    }
    $sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->applyFromArray($thinBorder);
    $sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->getFont()->setBold(true);
    $sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->getFill()->applyFromArray($fillGreenHdr);
    $sheet->getStyle('F' . $hdrRow . ':' . $lastCol . $hdrRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

    $r = $hdrRow + 1;
    $hasData = false;
    foreach ($rowsIn as $row) {
        if (!is_array($row)) {
            continue;
        }
        $vals = [
            isset($row['account_ledger']) ? (string) $row['account_ledger'] : '',
            isset($row['voucher_name']) ? (string) $row['voucher_name'] : '',
            isset($row['account_no']) ? (string) $row['account_no'] : '',
            isset($row['invoice_no']) ? (string) $row['invoice_no'] : '',
            isset($row['date']) ? (string) $row['date'] : '',
            isset($row['d1']) ? $row['d1'] : '',
            isset($row['d2']) ? $row['d2'] : '',
            isset($row['d3']) ? $row['d3'] : '',
            isset($row['d4']) ? $row['d4'] : '',
            isset($row['d5']) ? $row['d5'] : '',
            isset($row['total']) ? $row['total'] : '',
        ];
        for ($i = 0; $i < 11; ++$i) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $v = $vals[$i];
            if ($i >= 5 && $v !== '' && is_numeric($v)) {
                $sheet->setCellValueExplicit($col . $r, (float) $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            } else {
                $sheet->setCellValue($col . $r, $v);
            }
        }
        $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->applyFromArray($thinBorder);
        $sheet->getStyle('F' . $r . ':' . $lastCol . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('F' . $r . ':' . $lastCol . $r)->getNumberFormat()->setFormatCode('#,##0.00_);[Red](#,##0.00)');
        ++$r;
        $hasData = true;
    }

    if (!$hasData) {
        for ($i = 0; $i < 11; ++$i) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($col . $r, '');
        }
        $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->applyFromArray($thinBorder);
        ++$r;
    }

    $t = array_map(static function ($x) {
        return is_numeric($x) ? (float) $x : 0.0;
    }, array_values($totalsIn));
    if (count($t) > 6) {
        $t = array_slice($t, 0, 6);
    }
    $t = array_pad($t, 6, 0.0);

    $sheet->mergeCells('A' . $r . ':E' . $r);
    $sheet->setCellValue('A' . $r, 'Total');
    $sheet->setCellValue('F' . $r, $t[0]);
    $sheet->setCellValue('G' . $r, $t[1]);
    $sheet->setCellValue('H' . $r, $t[2]);
    $sheet->setCellValue('I' . $r, $t[3]);
    $sheet->setCellValue('J' . $r, $t[4]);
    $sheet->setCellValue('K' . $r, $t[5]);
    $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->applyFromArray($thinBorder);
    $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->getFont()->applyFromArray($fontTotalsWhite);
    $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->getFill()->applyFromArray($fillTotalsBlue);
    $sheet->getStyle('A' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle('F' . $r . ':' . $lastCol . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle('F' . $r . ':' . $lastCol . $r)->getNumberFormat()->setFormatCode('#,##0.00_);[Red](#,##0.00)');

    foreach (range(1, 11) as $ci) {
        $sheet->getColumnDimensionByColumn($ci)->setAutoSize(true);
    }
} else {
    // Stock tab: 15 columns A–O (inventory-style ageing)
    $lastCol = 'O';
    $headers = [
        'Branch',
        'Carat',
        'Metal',
        'Product Code',
        'RFID Code',
        'Barcode',
        'Qty',
        'Location',
        'Age',
        'Gross Wt',
        'Purity Wt',
        'Net Wt.',
        'Final Wt.',
        'Voucher Type',
        'Invoice No.',
    ];

    auragold_excel_financial_banner_layout(
        $sheet,
        $lastCol,
        strtoupper($shopName),
        'Business License No - ' . $licenseNo,
        $fromToLineStock,
        ['title_blue_rgb' => $BANNER_BLUE, 'period_fill_rgb' => 'F8CBAD']
    );

    $hdrRow = 5;
    foreach ($headers as $i => $h) {
        $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
        $sheet->setCellValue($col . $hdrRow, $h);
    }
    $sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->applyFromArray($thinBorder);
    $sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->getFont()->setBold(true);
    $sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->getFill()->applyFromArray($fillGreenHdr);
    foreach (['G', 'I'] as $hc) {
        $sheet->getStyle($hc . $hdrRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }
    $sheet->getStyle('G' . $hdrRow)->getNumberFormat()->setFormatCode('#,##0');
    $sheet->getStyle('I' . $hdrRow)->getNumberFormat()->setFormatCode('#,##0');
    foreach (['J', 'K', 'L', 'M'] as $hc) {
        $sheet->getStyle($hc . $hdrRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle($hc . $hdrRow)->getNumberFormat()->setFormatCode('#,##0.000');
    }

    $r = $hdrRow + 1;
    $hasData = false;
    $stockNumericIdx = [6, 8, 9, 10, 11, 12];

    foreach ($rowsIn as $row) {
        if (!is_array($row)) {
            continue;
        }
        $vals = [
            isset($row['branch']) ? (string) $row['branch'] : '',
            isset($row['carat']) ? (string) $row['carat'] : '',
            isset($row['metal']) ? (string) $row['metal'] : '',
            isset($row['product_code']) ? (string) $row['product_code'] : '',
            isset($row['rfid_code']) ? (string) $row['rfid_code'] : '',
            isset($row['barcode']) ? (string) $row['barcode'] : '',
            isset($row['qty']) ? $row['qty'] : '',
            isset($row['location']) ? (string) $row['location'] : '',
            isset($row['age']) ? $row['age'] : '',
            isset($row['gross_wt']) ? $row['gross_wt'] : '',
            isset($row['purity_wt']) ? $row['purity_wt'] : '',
            isset($row['net_wt']) ? $row['net_wt'] : '',
            isset($row['final_wt']) ? $row['final_wt'] : '',
            isset($row['voucher_type']) ? (string) $row['voucher_type'] : '',
            isset($row['invoice_no']) ? (string) $row['invoice_no'] : '',
        ];
        for ($i = 0; $i < 15; ++$i) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $v = $vals[$i];
            if (in_array($i, $stockNumericIdx, true) && $v !== '' && is_numeric($v)) {
                $sheet->setCellValueExplicit($col . $r, (float) $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
            } else {
                $sheet->setCellValue($col . $r, $v);
            }
        }
        $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->applyFromArray($thinBorder);
        $sheet->getStyle('G' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('G' . $r)->getNumberFormat()->setFormatCode('#,##0');
        foreach (['I'] as $nc) {
            $sheet->getStyle($nc . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle($nc . $r)->getNumberFormat()->setFormatCode('#,##0');
        }
        foreach (['J', 'K', 'L', 'M'] as $nc) {
            $sheet->getStyle($nc . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle($nc . $r)->getNumberFormat()->setFormatCode('#,##0.000');
        }
        ++$r;
        $hasData = true;
    }

    if (!$hasData) {
        for ($i = 0; $i < 15; ++$i) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
            $sheet->setCellValue($col . $r, '');
        }
        $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->applyFromArray($thinBorder);
        ++$r;
    }

    $t = array_map(static function ($x) {
        return is_numeric($x) ? (float) $x : 0.0;
    }, array_values($totalsIn));
    if (count($t) > 5) {
        $t = array_slice($t, 0, 5);
    }
    $t = array_pad($t, 5, 0.0);

    $sheet->mergeCells('A' . $r . ':F' . $r);
    $sheet->setCellValue('A' . $r, 'Total');
    $sheet->setCellValue('G' . $r, $t[0]);
    $sheet->setCellValue('H' . $r, '');
    $sheet->setCellValue('I' . $r, '');
    $sheet->setCellValue('J' . $r, $t[1]);
    $sheet->setCellValue('K' . $r, $t[2]);
    $sheet->setCellValue('L' . $r, $t[3]);
    $sheet->setCellValue('M' . $r, $t[4]);
    $sheet->setCellValue('N' . $r, '');
    $sheet->setCellValue('O' . $r, '');
    $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->applyFromArray($thinBorder);
    $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->getFont()->applyFromArray($fontTotalsWhite);
    $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->getFill()->applyFromArray($fillTotalsBlue);
    $sheet->getStyle('A' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle('G' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    $sheet->getStyle('G' . $r)->getNumberFormat()->setFormatCode('#,##0');
    foreach (['J', 'K', 'L', 'M'] as $tc) {
        $sheet->getStyle($tc . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle($tc . $r)->getNumberFormat()->setFormatCode('#,##0.000');
    }

    foreach (range(1, 15) as $ci) {
        $sheet->getColumnDimensionByColumn($ci)->setAutoSize(true);
    }
}

$fname = 'Ageing_Report_' . $agingDate . '.xlsx';
if ($tab === 'stock') {
    $fname = 'Ageing_Report_Stock_' . $agingDate . '.xlsx';
}

while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;