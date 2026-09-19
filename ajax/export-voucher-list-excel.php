<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_require_login.php';
require_once __DIR__ . '/../includes/auragold_voucher_list_query.php';
require_once __DIR__ . '/../includes/auragold_voucher_list_export_data.php';
require_once __DIR__ . '/../vendor/autoload.php';

auragold_require_login_or_exit();

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$kind = isset($_GET['kind']) && $_GET['kind'] === 'receipt' ? 'receipt' : 'payment';
$filters = auragold_voucher_list_filter_from_request();
$rows = auragold_voucher_list_export_rows($conn, $kind, $filters);
$title = auragold_voucher_list_export_title($kind);

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle(substr($title, 0, 31));

$headers = ['Sr.No.', 'Date', 'Voucher No.', 'Ledger Name', 'Branch', 'Ref No.', 'Against', 'Against Of', 'Total Amount', 'Sales Person', 'Comment'];
$sheet->fromArray($headers, null, 'A1');
$sheet->getStyle('A1:K1')->getFont()->setBold(true);
$sheet->getStyle('A1:K1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('11294B');
$sheet->getStyle('A1:K1')->getFont()->getColor()->setRGB('FFFFFF');

$r = 2;
$sr = 1;
foreach ($rows as $row) {
    $sheet->fromArray([
        $sr++,
        substr((string) ($row['voucher_date'] ?? ''), 0, 10),
        $row['voucher_no'] ?? '',
        $row['customer_name'] ?? '',
        $row['branch_name'] ?? '',
        $row['ref_no'] ?? '',
        $row['against'] ?? '',
        $row['against_of'] ?? '',
        (float) ($row['total_amount'] ?? 0),
        $row['sales_person'] ?? '',
        $row['comment'] ?? '',
    ], null, 'A' . $r);
    $r++;
}

foreach (range('A', 'K') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$filename = ($kind === 'receipt' ? 'receipt-voucher-list' : 'payment-voucher-list') . '-' . date('Y-m-d') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
