<?php
/**
 * Add a bank-statement row that is missing in software into the bank ledger
 * via a balanced Journal Voucher (bank + opposite ledger).
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_require_login.php';
require_once __DIR__ . '/../includes/auragold_bank_reconciliation.php';

auragold_require_login_or_exit();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$bank_id = isset($_POST['bank_id']) ? (int) $_POST['bank_id'] : 0;
$branch_id = isset($_POST['branch_id']) ? (int) $_POST['branch_id'] : 0;
if ($branch_id <= 0) {
    $branch_id = auragold_bank_reconciliation_effective_branch_id();
}

$date = isset($_POST['date']) ? trim((string) $_POST['date']) : '';
$reference = isset($_POST['reference']) ? trim((string) $_POST['reference']) : '';
$description = isset($_POST['description']) ? trim((string) $_POST['description']) : '';
$against_ledger = isset($_POST['against_ledger']) ? trim((string) $_POST['against_ledger']) : '';
$withdrawal = isset($_POST['withdrawal']) ? (float) $_POST['withdrawal'] : 0.0;
$deposit = isset($_POST['deposit']) ? (float) $_POST['deposit'] : 0.0;

$jsonExit = static function (array $payload): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
};

if ($bank_id <= 0) {
    $jsonExit(['status' => 'error', 'message' => 'bank_id required']);
}
if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $jsonExit(['status' => 'error', 'message' => 'Valid date is required (YYYY-MM-DD).']);
}
if ($against_ledger === '') {
    $jsonExit(['status' => 'error', 'message' => 'Select the opposite ledger (against account).']);
}
if ($deposit > 0.009 && $withdrawal > 0.009) {
    $jsonExit(['status' => 'error', 'message' => 'Row cannot have both withdrawal and deposit.']);
}
$amount = $deposit > 0.009 ? $deposit : ($withdrawal > 0.009 ? $withdrawal : 0.0);
if ($amount <= 0.009) {
    $jsonExit(['status' => 'error', 'message' => 'Amount is required (withdrawal or deposit).']);
}

$bank = getRecord('SELECT id, name FROM tbl_customers WHERE id = ' . (int) $bank_id . ' AND status = 1 LIMIT 1');
if (!$bank) {
    $jsonExit(['status' => 'error', 'message' => 'Bank not found']);
}
$bank_name = trim((string) ($bank['name'] ?? ''));
if ($bank_name === '') {
    $jsonExit(['status' => 'error', 'message' => 'Bank name is empty']);
}
if (strcasecmp($bank_name, $against_ledger) === 0) {
    $jsonExit(['status' => 'error', 'message' => 'Opposite ledger must be different from the bank account.']);
}

// Deposit in bank statement = money into bank = Debit bank / Credit against
// Withdrawal in bank statement = money out of bank = Credit bank / Debit against
$is_deposit = $deposit > 0.009;
$bank_side = $is_deposit ? 'Dr' : 'Cr';
$against_side = $is_deposit ? 'Cr' : 'Dr';

$next_voucher_no = 'JV-1';
$check_table = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_journal_vouchers'");
if ($check_table && mysqli_num_rows($check_table) > 0) {
    $last_voucher = getRecord('SELECT voucher_no FROM tbl_journal_vouchers ORDER BY id DESC LIMIT 1');
    if ($last_voucher && !empty($last_voucher['voucher_no'])) {
        $last_num = (int) preg_replace('/[^0-9]/', '', (string) $last_voucher['voucher_no']);
        $next_voucher_no = 'JV-' . ($last_num + 1);
    }
}
if ($check_table) {
    mysqli_free_result($check_table);
}

$comment_parts = ['Bank reconciliation'];
if ($description !== '') {
    $comment_parts[] = $description;
}
if ($reference !== '') {
    $comment_parts[] = 'Ref ' . $reference;
}
$comment = implode(' — ', $comment_parts);
// Keep ASCII-friendly separators for latin1 ledgers
$comment = str_replace(['—', '↔'], ['-', '<->'], $comment);

$items = [
    [
        'branch_id' => $branch_id,
        'branch_name' => '',
        'account_ledger' => $bank_name,
        'cr_dr' => $bank_side,
        'against' => $reference,
        'ref_no' => $reference !== '' ? $reference : $next_voucher_no,
        'ref_date' => $date,
        'amount' => $amount,
        'metal' => '',
        'purity_wt' => 0,
    ],
    [
        'branch_id' => $branch_id,
        'branch_name' => '',
        'account_ledger' => $against_ledger,
        'cr_dr' => $against_side,
        'against' => $reference,
        'ref_no' => $reference !== '' ? $reference : $next_voucher_no,
        'ref_date' => $date,
        'amount' => $amount,
        'metal' => '',
        'purity_wt' => 0,
    ],
];

$_POST['voucher_id'] = 0;
$_POST['voucher_no'] = $next_voucher_no;
$_POST['voucher_date'] = $date;
$_POST['comment'] = $comment;
$_POST['credit_wt'] = 0;
$_POST['debit_wt'] = 0;
$_POST['debit_total'] = $amount;
$_POST['credit_total'] = $amount;
$_POST['items'] = $items;

require __DIR__ . '/save-journal-voucher.php';
