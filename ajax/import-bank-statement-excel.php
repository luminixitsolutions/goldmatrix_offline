<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_require_login.php';
require_once __DIR__ . '/../includes/auragold_bank_reconciliation_excel.php';

auragold_require_login_or_exit();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$bank_id = isset($_POST['bank_id']) ? (int) $_POST['bank_id'] : 0;
$from_date = isset($_POST['from_date']) ? trim((string) $_POST['from_date']) : '';
$to_date = isset($_POST['to_date']) ? trim((string) $_POST['to_date']) : '';
$branch_id = isset($_POST['branch_id']) ? (int) $_POST['branch_id'] : 0;
if ($branch_id <= 0) {
    $branch_id = auragold_bank_reconciliation_effective_branch_id();
}

if ($bank_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Select a bank account']);
    exit;
}

if (empty($_FILES['excel_file']['tmp_name']) || !is_uploaded_file($_FILES['excel_file']['tmp_name'])) {
    echo json_encode(['status' => 'error', 'message' => 'Please upload an Excel file (.xlsx)']);
    exit;
}

$ext = strtolower(pathinfo((string) $_FILES['excel_file']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['xlsx', 'xls'], true)) {
    echo json_encode(['status' => 'error', 'message' => 'Only .xlsx or .xls files are supported']);
    exit;
}

if ($ext === 'xlsx' && !class_exists('ZipArchive', false)) {
    echo json_encode(['status' => 'error', 'message' => 'PHP zip extension is required for .xlsx files']);
    exit;
}

$bank = getRecord('SELECT id, name FROM tbl_customers WHERE id = ' . (int) $bank_id . ' AND status = 1 LIMIT 1');
if (!$bank) {
    echo json_encode(['status' => 'error', 'message' => 'Bank account not found']);
    exit;
}

$bank_name = trim((string) ($bank['name'] ?? ''));

$parsed = auragold_bank_reconciliation_parse_excel_rows($_FILES['excel_file']['tmp_name']);
if (!empty($parsed['errors']) && empty($parsed['rows'])) {
    echo json_encode(['status' => 'error', 'message' => implode(' ', $parsed['errors'])]);
    exit;
}

$statement = auragold_bank_reconciliation_statement($conn, $bank_id, $bank_name, $from_date, $to_date, $branch_id);
$match = auragold_bank_reconciliation_match_rows($statement['rows'], $parsed['rows']);

echo json_encode([
    'status'              => 'success',
    'bank_id'             => $bank_id,
    'bank_name'           => $bank_name,
    'parse_warnings'      => $parsed['errors'],
    'counts'              => $match['counts'],
    'matched'             => $match['matched'],
    'missing_in_software' => $match['missing_in_software'],
    'missing_in_bank'     => $match['missing_in_bank'],
    'software_summary'    => [
        'opening'      => $statement['opening'],
        'closing'      => $statement['closing'],
        'total_debit'  => $statement['total_debit'],
        'total_credit' => $statement['total_credit'],
    ],
]);
