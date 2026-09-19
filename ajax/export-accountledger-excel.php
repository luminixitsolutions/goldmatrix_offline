<?php

/**
 * Account Ledger Report — styled .xlsx (financial banner + mint headers + zebra + totals band).
 * Expects JSON from the browser mirroring the on-screen table (visible columns only).
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

$raw = file_get_contents('php://input');
$payload = json_decode((string) $raw, true);
if (!is_array($payload)) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Invalid JSON body';
    exit;
}

$headers = isset($payload['headers']) && is_array($payload['headers']) ? $payload['headers'] : [];
$rows = isset($payload['rows']) && is_array($payload['rows']) ? $payload['rows'] : [];
$footer = isset($payload['footer']) && is_array($payload['footer']) ? $payload['footer'] : null;

$tab = isset($payload['tab']) ? trim((string) $payload['tab']) : 'balance';
if ($tab !== 'balance' && $tab !== 'all') {
    $tab = 'balance';
}

$pistaFrom = isset($payload['pistaFrom']) ? (int) $payload['pistaFrom'] : -1;
$fromDate = isset($payload['fromDate']) ? trim((string) $payload['fromDate']) : '';
$toDate = isset($payload['toDate']) ? trim((string) $payload['toDate']) : '';

if (count($headers) < 1 || count($headers) > 64) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Invalid headers';
    exit;
}
if (count($rows) > 8000) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Too many rows';
    exit;
}

$allowedKinds = ['', 'pista', 'dr', 'cr', 'red', 'blue', 'cl_bg', 'pista_light', 'footer'];

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

$periodLine = $tab === 'balance'
    ? 'Account Ledger — Balance Amounts'
    : 'Account Ledger — View All Ledger';
if ($fromDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
    $f = str_replace('-', '/', $fromDate);
    if ($toDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
        $t = str_replace('-', '/', $toDate);
        $periodLine .= ' From :- ' . $f . ' To :- ' . $t;
    } else {
        $periodLine .= ' From :- ' . $f;
    }
} elseif ($toDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
    $periodLine .= ' To :- ' . str_replace('-', '/', $toDate);
}

$BANNER_BLUE = '4472C4';

$thinBorder = [
    'borders' => [
        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
    ],
];

$fillMintHdr = [
    'fillType'   => Fill::FILL_SOLID,
    'startColor' => ['rgb' => 'C8E6C9'],
];
$fillPistaHdr = [
    'fillType'   => Fill::FILL_SOLID,
    'startColor' => ['rgb' => 'C5DFC5'],
];
$fillZebraA = [
    'fillType'   => Fill::FILL_SOLID,
    'startColor' => ['rgb' => 'FFFFFF'],
];
$fillZebraB = [
    'fillType'   => Fill::FILL_SOLID,
    'startColor' => ['rgb' => 'EFF6FF'],
];
$fillPista = [
    'fillType'   => Fill::FILL_SOLID,
    'startColor' => ['rgb' => 'E2F0E0'],
];
$fillPistaLight = [
    'fillType'   => Fill::FILL_SOLID,
    'startColor' => ['rgb' => 'D1FAE5'],
];
$fillDr = [
    'fillType'   => Fill::FILL_SOLID,
    'startColor' => ['rgb' => 'FEE2E2'],
];
$fillCr = [
    'fillType'   => Fill::FILL_SOLID,
    'startColor' => ['rgb' => 'DBEAFE'],
];
$fillClBg = [
    'fillType'   => Fill::FILL_SOLID,
    'startColor' => ['rgb' => 'F1EDFF'],
];
$fillTotalsBlue = [
    'fillType'   => Fill::FILL_SOLID,
    'startColor' => ['rgb' => $BANNER_BLUE],
];
$fontTotalsWhite = ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11];

$nCols = count($headers);
$lastCol = Coordinate::stringFromColumnIndex($nCols);

$spreadsheet = new Spreadsheet();
$spreadsheet->getDefaultStyle()->getFont()->setName('Calibri');
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('ACCOUNT LEDGER');

auragold_excel_financial_banner_layout(
    $sheet,
    $lastCol,
    strtoupper($shopName),
    'Business License No - ' . $licenseNo,
    $periodLine,
    ['title_blue_rgb' => $BANNER_BLUE, 'period_fill_rgb' => 'F8CBAD']
);

$hdrRow = 5;
foreach ($headers as $i => $h) {
    $col = Coordinate::stringFromColumnIndex($i + 1);
    $sheet->setCellValue($col . $hdrRow, is_string($h) ? $h : (string) $h);
}
$sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->applyFromArray($thinBorder);
$sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->getFont()->setBold(true);
$sheet->getStyle('A' . $hdrRow . ':' . $lastCol . $hdrRow)->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);

for ($i = 0; $i < $nCols; ++$i) {
    $col = Coordinate::stringFromColumnIndex($i + 1);
    $hdrFill = ($pistaFrom >= 0 && $i >= $pistaFrom) ? $fillPistaHdr : $fillMintHdr;
    $sheet->getStyle($col . $hdrRow)->getFill()->applyFromArray($hdrFill);
}

$r = $hdrRow + 1;
foreach ($rows as $ri => $row) {
    if (!is_array($row)) {
        continue;
    }
    if (count($row) !== $nCols) {
        header('HTTP/1.1 400 Bad Request');
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Row/column mismatch';
        exit;
    }
    $zebra = ($ri % 2 === 1) ? $fillZebraB : $fillZebraA;
    for ($i = 0; $i < $nCols; ++$i) {
        $col = Coordinate::stringFromColumnIndex($i + 1);
        $cell = $row[$i];
        $v = is_array($cell) ? (string) ($cell['v'] ?? '') : (string) $cell;
        $k = is_array($cell) ? trim((string) ($cell['k'] ?? '')) : '';
        if (!in_array($k, $allowedKinds, true)) {
            $k = '';
        }
        if (strlen($v) > 512) {
            $v = substr($v, 0, 509) . '...';
        }
        $sheet->setCellValue($col . $r, $v);

        $fontColor = null;
        $fill = $zebra;
        if ($k === 'pista') {
            $fill = $fillPista;
        } elseif ($k === 'pista_light') {
            $fill = $fillPistaLight;
        } elseif ($k === 'dr') {
            $fill = $fillDr;
            $fontColor = 'DC2626';
        } elseif ($k === 'cr') {
            $fill = $fillCr;
            $fontColor = '2563EB';
        } elseif ($k === 'red') {
            $fontColor = 'DC2626';
        } elseif ($k === 'blue') {
            $fontColor = '11294B';
        } elseif ($k === 'cl_bg') {
            $fill = $fillClBg;
            if (strpos($v, '(') !== false) {
                $fontColor = 'DC2626';
            }
        }

        $sheet->getStyle($col . $r)->getFill()->applyFromArray($fill);
        if ($fontColor !== null) {
            $sheet->getStyle($col . $r)->getFont()->getColor()->setRGB($fontColor);
        }
        $sheet->getStyle($col . $r)->applyFromArray($thinBorder);
        if ($i <= 1) {
            $sheet->getStyle($col . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        } else {
            $sheet->getStyle($col . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
    }
    ++$r;
}

if ($footer !== null && count($footer) === $nCols) {
    for ($i = 0; $i < $nCols; ++$i) {
        $col = Coordinate::stringFromColumnIndex($i + 1);
        $cell = $footer[$i];
        $v = is_array($cell) ? (string) ($cell['v'] ?? '') : (string) $cell;
        if (strlen($v) > 512) {
            $v = substr($v, 0, 509) . '...';
        }
        $sheet->setCellValue($col . $r, $v);
    }
    $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->applyFromArray($thinBorder);
    $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->getFont()->applyFromArray($fontTotalsWhite);
    $sheet->getStyle('A' . $r . ':' . $lastCol . $r)->getFill()->applyFromArray($fillTotalsBlue);
    $sheet->getStyle('A' . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    if ($nCols > 1) {
        $sheet->getStyle('B' . $r . ':' . $lastCol . $r)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }
    ++$r;
}

for ($ci = 1; $ci <= $nCols; ++$ci) {
    $sheet->getColumnDimensionByColumn($ci)->setAutoSize(true);
}

$fname = 'Account_Ledger_Report_' . date('Y-m-d') . '.xlsx';

while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $fname . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
