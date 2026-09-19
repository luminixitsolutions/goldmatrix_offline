<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_require_login.php';
require_once __DIR__ . '/../includes/auragold_simple_voucher_list_export_data.php';
require_once __DIR__ . '/../vendor/autoload.php';

auragold_require_login_or_exit();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$kind = isset($_GET['kind']) && $_GET['kind'] === 'journal' ? 'journal' : 'contra';
$rows = auragold_simple_voucher_list_export_rows($conn, $kind);
$title = auragold_simple_voucher_list_export_title($kind);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle(substr($title, 0, 31));

if ($kind === 'journal') {
    $headers = ['Sr.No.', 'Date', 'Voucher No.', 'Comment', 'Debit Total', 'Credit Total'];
} else {
    $headers = ['Sr.No.', 'Date', 'Voucher No.', 'Comment', 'Total Amount'];
}
$sheet->fromArray($headers, null, 'A1');
$lastCol = chr(ord('A') + count($headers) - 1);
$sheet->getStyle('A1:' . $lastCol . '1')->getFont()->setBold(true);
$sheet->getStyle('A1:' . $lastCol . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('11294B');
$sheet->getStyle('A1:' . $lastCol . '1')->getFont()->getColor()->setRGB('FFFFFF');

$r = 2;
$sr = 1;
foreach ($rows as $row) {
    if ($kind === 'journal') {
        $sheet->fromArray([
            $sr++,
            substr((string) ($row['voucher_date'] ?? ''), 0, 10),
            $row['voucher_no'] ?? '',
            $row['comment'] ?? '',
            (float) ($row['debit_total'] ?? 0),
            (float) ($row['credit_total'] ?? 0),
        ], null, 'A' . $r);
    } else {
        $sheet->fromArray([
            $sr++,
            substr((string) ($row['voucher_date'] ?? ''), 0, 10),
            $row['voucher_no'] ?? '',
            $row['comment'] ?? '',
            (float) ($row['total_amount'] ?? 0),
        ], null, 'A' . $r);
    }
    $r++;
}

foreach (range('A', $lastCol) as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$filename = ($kind === 'journal' ? 'journal-voucher-list' : 'contra-voucher-list') . '-' . date('Y-m-d') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
