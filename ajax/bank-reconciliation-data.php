<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_require_login.php';
require_once __DIR__ . '/../includes/auragold_bank_reconciliation.php';

auragold_require_login_or_exit();
header('Content-Type: application/json; charset=utf-8');

$branch_id = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : 0;
if ($branch_id <= 0) {
    $branch_id = auragold_bank_reconciliation_effective_branch_id();
}

$from_date = isset($_GET['from_date']) ? trim((string) $_GET['from_date']) : '';
$to_date = isset($_GET['to_date']) ? trim((string) $_GET['to_date']) : '';

$bank_id = isset($_GET['bank_id']) ? (int) $_GET['bank_id'] : 0;
$bank_name = isset($_GET['bank_name']) ? trim((string) $_GET['bank_name']) : '';

if ($bank_id <= 0 && $bank_name === '') {
    $banks = auragold_bank_reconciliation_accounts($conn, $branch_id);
    echo json_encode([
        'status'    => 'success',
        'branch_id' => $branch_id,
        'banks'     => $banks,
    ]);
    exit;
}

if ($bank_id > 0 && $bank_name === '') {
    $row = getRecord('SELECT id, name FROM tbl_customers WHERE id = ' . (int) $bank_id . ' AND status = 1 LIMIT 1');
    if ($row) {
        $bank_name = trim((string) ($row['name'] ?? ''));
    }
}

$statement = auragold_bank_reconciliation_statement($conn, $bank_id, $bank_name, $from_date, $to_date, $branch_id);

echo json_encode([
    'status'      => 'success',
    'branch_id'   => $branch_id,
    'bank_id'     => $bank_id,
    'bank_name'   => $bank_name,
    'from_date'   => $from_date,
    'to_date'     => $to_date,
    'opening'     => $statement['opening'],
    'closing'     => $statement['closing'],
    'total_debit' => $statement['total_debit'],
    'total_credit'=> $statement['total_credit'],
    'rows'        => $statement['rows'],
]);
