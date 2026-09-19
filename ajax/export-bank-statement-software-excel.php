<?php
/**
 * Export GoldMatrix bank ledger as Excel for reconciliation.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_require_login.php';
require_once __DIR__ . '/../includes/auragold_bank_reconciliation.php';

auragold_require_login_or_exit();

if (!class_exists('ZipArchive', false)) {
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'PHP zip extension is required to export Excel files.';
    exit;
}

require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$bank_id = isset($_GET['bank_id']) ? (int) $_GET['bank_id'] : 0;
$from_date = isset($_GET['from_date']) ? trim((string) $_GET['from_date']) : '';
$to_date = isset($_GET['to_date']) ? trim((string) $_GET['to_date']) : '';
$branch_id = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : 0;
if ($branch_id <= 0) {
    $branch_id = auragold_bank_reconciliation_effective_branch_id();
}

if ($bank_id <= 0) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'bank_id required';
    exit;
}

$bank = getRecord('SELECT id, name FROM tbl_customers WHERE id = ' . (int) $bank_id . ' AND status = 1 LIMIT 1');
if (!$bank) {
    header('HTTP/1.1 404 Not Found');
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Bank not found';
    exit;
}

$bank_name = trim((string) ($bank['name'] ?? 'Bank'));
$statement = auragold_bank_reconciliation_statement($conn, $bank_id, $bank_name, $from_date, $to_date, $branch_id);

$headers = ['Date', 'Reference No', 'Voucher Type', 'Description', 'Withdrawal', 'Deposit', 'Ledger Debit', 'Ledger Credit', 'Balance'];
$rows = [];
$rows[] = [
    $from_date !== '' ? $from_date : 'Opening',
    '',
    '',
    'Opening Balance',
    '',
    '',
    '',
    '',
    $statement['opening'],
];

foreach ($statement['rows'] as $r) {
    $debit = (float) ($r['debit'] ?? 0);
    $credit = (float) ($r['credit'] ?? 0);
    $rows[] = [
        substr((string) ($r['date'] ?? ''), 0, 10),
        (string) ($r['voucher_no'] ?? ''),
        (string) ($r['voucher_type'] ?? ''),
        (string) ($r['description'] ?? ''),
        $credit > 0 ? $credit : '',
        $debit > 0 ? $debit : '',
        $debit > 0 ? $debit : '',
        $credit > 0 ? $credit : '',
        (float) ($r['balance'] ?? 0),
    ];
}

$rows[] = ['', '', '', 'Closing Balance', '', '', '', '', $statement['closing']];

try {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Software Statement');
    $sheet->fromArray([$headers], null, 'A1', true);
    $sheet->fromArray($rows, null, 'A2', true);

    $lastCol = Coordinate::stringFromColumnIndex(count($headers));
    $sheet->getStyle('A1:' . $lastCol . '1')->applyFromArray([
        'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['argb' => 'FF11294B'],
        ],
    ]);

    for ($i = 1; $i <= count($headers); $i++) {
        $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $safe = preg_replace('/[^a-zA-Z0-9_-]+/', '_', $bank_name);
    $filename = 'software-bank-statement-' . $safe . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
} catch (Throwable $e) {
    if (!headers_sent()) {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo 'Export failed: ' . $e->getMessage();
}
exit;
