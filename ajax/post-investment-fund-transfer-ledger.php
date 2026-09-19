<?php
/**
 * Post investment / layaway fund transfer to tbl_customer_ledger (Account Ledger report).
 * Customer: credit transfer amount; Layaways Fund: debit same amount (double entry).
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/ensure_customer_ledger_branch_column.php';

if (!auragold_is_logged_in_session()) {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$in = json_decode($raw, true);
if (!is_array($in)) {
    $in = $_POST;
}

$customer_id = isset($in['customer_id']) ? (int) $in['customer_id'] : 0;
$customer_name = isset($in['customer_name']) ? trim((string) $in['customer_name']) : '';
$fund_no = isset($in['fund_no']) ? trim((string) $in['fund_no']) : '';
$transfer_date = isset($in['transfer_date']) ? trim((string) $in['transfer_date']) : date('Y-m-d');
$transfer_amt = isset($in['transfer_amount']) ? round((float) $in['transfer_amount'], 2) : 0.0;
$ft_no = isset($in['ft_no']) ? trim((string) $in['ft_no']) : '';

if ($customer_name === '' || $fund_no === '' || $transfer_amt <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Customer name, fund number and a positive transfer amount are required.']);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $transfer_date)) {
    $transfer_date = date('Y-m-d');
}

$layaways_fund_name = 'Layaways Fund';
$txn_type = 'investment_fund_transfer';

auragold_ensure_customer_ledger_branch_column($conn);
$ledger_has_branch_col = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'branch_id');
$post_branch = function_exists('auragold_transaction_header_branch_id') ? (int) auragold_transaction_header_branch_id() : 0;
if ($post_branch <= 0 && function_exists('auragold_effective_branch_id')) {
    $post_branch = (int) auragold_effective_branch_id();
}
if ($post_branch <= 0 && function_exists('auragold_settings_main_branch_id')) {
    $post_branch = (int) auragold_settings_main_branch_id();
}
$ledger_branch_sql_col = $ledger_has_branch_col ? ', branch_id' : '';
$ledger_branch_sql_val = ($ledger_has_branch_col && $post_branch > 0)
    ? ', ' . $post_branch
    : ($ledger_has_branch_col ? ', NULL' : '');
$ledger_br_scope = function_exists('auragold_customer_ledger_branch_scope_sql')
    ? auragold_customer_ledger_branch_scope_sql($conn, $post_branch)
    : '';

$fund_no_esc = mysqli_real_escape_string($conn, $fund_no);
$txn_esc = mysqli_real_escape_string($conn, $txn_type);
$dup = getRecord(
    "SELECT id FROM tbl_customer_ledger WHERE transaction_type = '$txn_esc' AND transaction_no = '$fund_no_esc' AND status = 1 LIMIT 1"
);
if ($dup && !empty($dup['id'])) {
    echo json_encode(['ok' => true, 'duplicate' => true, 'message' => 'Ledger entries already exist for this fund.']);
    exit;
}

$user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : (int) ($_SESSION['Admin']['id'] ?? 0);

$has_gold_pure = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customer_ledger LIKE 'balance_gold_pure'");
$use_gold_pure = ($has_gold_pure && mysqli_num_rows($has_gold_pure) > 0);
if ($has_gold_pure) {
    mysqli_free_result($has_gold_pure);
}

$has_against = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customer_ledger LIKE 'against_ledger'");
$ledger_has_against_cols = ($has_against && mysqli_num_rows($has_against) > 0);
if ($has_against) {
    mysqli_free_result($has_against);
}

$against_cols = $ledger_has_against_cols ? ', against_ledger, against_invoice_no' : '';

$cols_bal = $use_gold_pure
    ? 'balance_amount, balance_gold, balance_silver, balance_gold_pure'
    : 'balance_amount, balance_gold, balance_silver';

$cust_name_esc = mysqli_real_escape_string($conn, $customer_name);
$lay_esc = mysqli_real_escape_string($conn, $layaways_fund_name);
$ref = $ft_no !== '' ? $ft_no : $fund_no;
$ref_sql = "'" . mysqli_real_escape_string($conn, $ref) . "'";
$date_esc = mysqli_real_escape_string($conn, $transfer_date);

$ledger_customer_id = $customer_id > 0 ? $customer_id : 0;

$last_balance = null;
if ($ledger_customer_id > 0) {
    $last_balance = getRecord(
        "SELECT $cols_bal FROM tbl_customer_ledger WHERE customer_id = $ledger_customer_id AND status = 1
        $ledger_br_scope ORDER BY transaction_date DESC, id DESC LIMIT 1"
    );
}
if (!$last_balance) {
    $last_balance = getRecord(
        "SELECT $cols_bal FROM tbl_customer_ledger WHERE customer_name = '$cust_name_esc' AND status = 1
        $ledger_br_scope ORDER BY transaction_date DESC, id DESC LIMIT 1"
    );
}

$prev_amt = (float) ($last_balance['balance_amount'] ?? 0);
$prev_gold = (float) ($last_balance['balance_gold'] ?? 0);
$prev_silver = (float) ($last_balance['balance_silver'] ?? 0);
$prev_gold_pure = $use_gold_pure ? (float) ($last_balance['balance_gold_pure'] ?? 0) : 0.0;

$new_cust_bal = $prev_amt - $transfer_amt;

$ledger_debit_credit = $use_gold_pure
    ? 'debit_gold, credit_gold, debit_gold_pure, credit_gold_pure, debit_silver, credit_silver,'
    : 'debit_gold, credit_gold, debit_silver, credit_silver,';
$zero_metal = $use_gold_pure ? '0, 0, 0, 0, 0, 0,' : '0, 0, 0, 0,';
$ledger_balance = $use_gold_pure
    ? 'balance_amount, balance_gold, balance_gold_pure, balance_silver,'
    : 'balance_amount, balance_gold, balance_silver,';
$balance_vals_cust = $use_gold_pure
    ? "$new_cust_bal, $prev_gold, $prev_gold_pure, $prev_silver,"
    : "$new_cust_bal, $prev_gold, $prev_silver,";

$desc_cust = mysqli_real_escape_string(
    $conn,
    'Investment fund transfer — ' . $fund_no . ($ft_no !== '' ? ' (' . $ft_no . ')' : '')
);

if ($ledger_has_against_cols) {
    $against_cust = mysqli_real_escape_string(
        $conn,
        $layaways_fund_name . '(' . number_format($transfer_amt, 2, '.', '') . 'Dr)'
    );
    $against_inv_cust = mysqli_real_escape_string($conn, $ref);
    $against_vals_cust = ", '$against_cust', '$against_inv_cust'";
} else {
    $against_vals_cust = '';
}

if (function_exists('mysqli_begin_transaction')) {
    @mysqli_begin_transaction($conn);
} else {
    @mysqli_query($conn, 'START TRANSACTION');
}
try {
    $sql_cust = "
        INSERT INTO tbl_customer_ledger (
            customer_id" . $ledger_branch_sql_col . ", customer_name, transaction_type, transaction_id, transaction_no,
            transaction_date, debit_amount, credit_amount,
            $ledger_debit_credit
            $ledger_balance
            description, reference_no, status, created_by, created_at
            $against_cols
        ) VALUES (
            " . ($ledger_customer_id > 0 ? $ledger_customer_id : 0) . $ledger_branch_sql_val . ",
            '$cust_name_esc',
            '$txn_esc',
            0,
            '$fund_no_esc',
            '$date_esc',
            0,
            $transfer_amt,
            $zero_metal
            $balance_vals_cust
            '$desc_cust',
            $ref_sql,
            1,
            " . ($user_id > 0 ? $user_id : 'NULL') . ",
            NOW()
            $against_vals_cust
        )
    ";
    if (!mysqli_query($conn, $sql_cust)) {
        throw new Exception(mysqli_error($conn) ?: 'Customer ledger insert failed');
    }

    $last_lay = getRecord(
        "SELECT $cols_bal FROM tbl_customer_ledger WHERE customer_name = '$lay_esc' AND status = 1
        $ledger_br_scope ORDER BY transaction_date DESC, id DESC LIMIT 1"
    );
    $lay_prev = (float) ($last_lay['balance_amount'] ?? 0);
    $lg = (float) ($last_lay['balance_gold'] ?? 0);
    $ls = (float) ($last_lay['balance_silver'] ?? 0);
    $lgp = $use_gold_pure ? (float) ($last_lay['balance_gold_pure'] ?? 0) : 0.0;
    $new_lay_bal = $lay_prev + $transfer_amt;

    $balance_vals_lay = $use_gold_pure
        ? "$new_lay_bal, $lg, $lgp, $ls,"
        : "$new_lay_bal, $lg, $ls,";

    $desc_lay = mysqli_real_escape_string(
        $conn,
        'Investment fund transfer from ' . $layaways_fund_name . ' — ' . $customer_name . ' (' . $fund_no . ')'
    );

    if ($ledger_has_against_cols) {
        $against_lay = mysqli_real_escape_string(
            $conn,
            $customer_name . '(' . number_format($transfer_amt, 2, '.', '') . 'Cr)'
        );
        $against_inv_lay = mysqli_real_escape_string($conn, $ref);
        $against_vals_lay = ", '$against_lay', '$against_inv_lay'";
    } else {
        $against_vals_lay = '';
    }

    $sql_lay = "
        INSERT INTO tbl_customer_ledger (
            customer_id" . $ledger_branch_sql_col . ", customer_name, transaction_type, transaction_id, transaction_no,
            transaction_date, debit_amount, credit_amount,
            $ledger_debit_credit
            $ledger_balance
            description, reference_no, status, created_by, created_at
            $against_cols
        ) VALUES (
            0" . $ledger_branch_sql_val . ",
            '$lay_esc',
            '$txn_esc',
            0,
            '$fund_no_esc',
            '$date_esc',
            $transfer_amt,
            0,
            $zero_metal
            $balance_vals_lay
            '$desc_lay',
            $ref_sql,
            1,
            " . ($user_id > 0 ? $user_id : 'NULL') . ",
            NOW()
            $against_vals_lay
        )
    ";
    if (!mysqli_query($conn, $sql_lay)) {
        throw new Exception(mysqli_error($conn) ?: 'Layaways Fund ledger insert failed');
    }

    if (function_exists('mysqli_commit')) {
        mysqli_commit($conn);
    } else {
        mysqli_query($conn, 'COMMIT');
    }
    echo json_encode(['ok' => true, 'amount' => $transfer_amt, 'branch_id' => $post_branch]);
} catch (Throwable $e) {
    if (function_exists('mysqli_rollback')) {
        mysqli_rollback($conn);
    } else {
        mysqli_query($conn, 'ROLLBACK');
    }
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
