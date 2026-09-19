<?php
/**
 * Post investment / layaway fund installment payment(s) to tbl_customer_ledger
 * and surface them on Transaction Report.
 *
 * Double entry (inverse of fund transfer):
 *   Customer: debit installment amount (receipt)
 *   Layaways Fund: credit same amount
 *
 * Accepts one installment or a batch under `installments`.
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

$action = isset($in['action']) ? strtolower(trim((string) $in['action'])) : 'post';
$txn_type = 'investment_fund_installment';
$txn_esc = mysqli_real_escape_string($conn, $txn_type);

// Soft-void prior ledger rows for one installment (clear from Investment Fund UI)
if ($action === 'void') {
    $void_no = isset($in['transaction_no']) ? trim((string) $in['transaction_no']) : '';
    if ($void_no === '') {
        echo json_encode(['ok' => false, 'message' => 'transaction_no required to void.']);
        exit;
    }
    $void_esc = mysqli_real_escape_string($conn, $void_no);
    $ok = @mysqli_query(
        $conn,
        "UPDATE tbl_customer_ledger SET status = 0
         WHERE transaction_type = '$txn_esc' AND transaction_no = '$void_esc' AND status = 1"
    );
    echo json_encode([
        'ok' => (bool) $ok,
        'voided' => $ok ? (int) mysqli_affected_rows($conn) : 0,
        'transaction_no' => $void_no,
    ]);
    exit;
}

$items = [];
if (isset($in['installments']) && is_array($in['installments'])) {
    $items = $in['installments'];
} else {
    $items = [$in];
}

$customer_id = isset($in['customer_id']) ? (int) $in['customer_id'] : 0;
$customer_name = isset($in['customer_name']) ? trim((string) $in['customer_name']) : '';
$fund_no = isset($in['fund_no']) ? trim((string) $in['fund_no']) : '';
$fund_local_id = isset($in['fund_local_id']) ? trim((string) $in['fund_local_id']) : '';
$entry_by = isset($in['entry_by']) ? trim((string) $in['entry_by']) : '';

if ($customer_name === '' || $fund_no === '') {
    echo json_encode(['ok' => false, 'message' => 'Customer name and fund number are required.']);
    exit;
}

$layaways_fund_name = 'Layaways Fund';

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

$ledger_debit_credit = $use_gold_pure
    ? 'debit_gold, credit_gold, debit_gold_pure, credit_gold_pure, debit_silver, credit_silver,'
    : 'debit_gold, credit_gold, debit_silver, credit_silver,';
$zero_metal = $use_gold_pure ? '0, 0, 0, 0, 0, 0,' : '0, 0, 0, 0,';
$ledger_balance = $use_gold_pure
    ? 'balance_amount, balance_gold, balance_gold_pure, balance_silver,'
    : 'balance_amount, balance_gold, balance_silver,';

$txn_esc = mysqli_real_escape_string($conn, $txn_type);
$cust_name_esc = mysqli_real_escape_string($conn, $customer_name);
$lay_esc = mysqli_real_escape_string($conn, $layaways_fund_name);
$fund_no_esc = mysqli_real_escape_string($conn, $fund_no);
$ledger_customer_id = $customer_id > 0 ? $customer_id : 0;

$posted = [];
$skipped = [];
$errors = [];

/**
 * Normalize one installment payload.
 *
 * @param array<string,mixed> $row
 * @return array{ok:bool,message?:string,inst_no?:int,amount?:float,pay_date?:string,txn_no?:string,label?:string,gold_wt?:float,gold_rate?:float,pay_mode?:string}
 */
function auragold_if_inst_normalize_row(array $row, string $fund_no): array
{
    $inst_no = isset($row['inst_no']) ? (int) $row['inst_no'] : 0;
    if ($inst_no <= 0 && isset($row['inst_index'])) {
        $inst_no = (int) $row['inst_index'] + 1;
    }
    $amount = isset($row['amount']) ? round((float) $row['amount'], 2) : 0.0;
    $tax = isset($row['tax']) ? round((float) $row['tax'], 2) : 0.0;
    $total = round($amount + $tax, 2);
    $pay_date = isset($row['pay_date']) ? trim((string) $row['pay_date']) : date('Y-m-d');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $pay_date)) {
        if (preg_match('/^(\d{1,2})[-\/](\d{1,2})[-\/](\d{4})$/', $pay_date, $m)) {
            $pay_date = sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        } else {
            $pay_date = date('Y-m-d');
        }
    }
    if ($inst_no <= 0) {
        return ['ok' => false, 'message' => 'Installment number is required.'];
    }
    if ($total <= 0) {
        return ['ok' => false, 'message' => 'Installment amount must be greater than zero.'];
    }
    $txn_no = isset($row['transaction_no']) ? trim((string) $row['transaction_no']) : '';
    if ($txn_no === '') {
        $txn_no = $fund_no . '-I' . $inst_no;
    }
    $label = isset($row['payment_desc']) ? trim((string) $row['payment_desc']) : '';
    if ($label === '') {
        $label = $inst_no . (in_array($inst_no % 10, [1, 2, 3], true) && !in_array($inst_no % 100, [11, 12, 13], true)
            ? ['th', 'st', 'nd', 'rd'][$inst_no % 10]
            : 'th') . ' installment';
    }
    return [
        'ok' => true,
        'inst_no' => $inst_no,
        'amount' => $total,
        'pay_date' => $pay_date,
        'txn_no' => $txn_no,
        'label' => $label,
        'gold_wt' => isset($row['gold_wt']) ? (float) $row['gold_wt'] : 0.0,
        'gold_rate' => isset($row['gold_rate']) ? (float) $row['gold_rate'] : 0.0,
        'pay_mode' => isset($row['pay_mode']) ? trim((string) $row['pay_mode']) : '',
    ];
}

if (function_exists('mysqli_begin_transaction')) {
    @mysqli_begin_transaction($conn);
} else {
    @mysqli_query($conn, 'START TRANSACTION');
}

try {
    foreach ($items as $rawItem) {
        if (!is_array($rawItem)) {
            continue;
        }
        // Allow per-item overrides of party/fund when batching
        $row_customer_id = isset($rawItem['customer_id']) ? (int) $rawItem['customer_id'] : $ledger_customer_id;
        $row_customer_name = isset($rawItem['customer_name']) ? trim((string) $rawItem['customer_name']) : $customer_name;
        $row_fund_no = isset($rawItem['fund_no']) ? trim((string) $rawItem['fund_no']) : $fund_no;
        if ($row_customer_name === '' || $row_fund_no === '') {
            $errors[] = 'Missing customer/fund on an installment row.';
            continue;
        }
        $norm = auragold_if_inst_normalize_row($rawItem, $row_fund_no);
        if (empty($norm['ok'])) {
            $errors[] = $norm['message'] ?? 'Invalid installment';
            continue;
        }

        $txn_no = (string) $norm['txn_no'];
        $txn_no_esc = mysqli_real_escape_string($conn, $txn_no);
        $amt = (float) $norm['amount'];
        $date_esc = mysqli_real_escape_string($conn, (string) $norm['pay_date']);
        $row_cust_esc = mysqli_real_escape_string($conn, $row_customer_name);
        $row_fund_esc = mysqli_real_escape_string($conn, $row_fund_no);

        // Soft-delete prior entries for this installment so re-saves stay accurate
        @mysqli_query(
            $conn,
            "UPDATE tbl_customer_ledger SET status = 0
             WHERE transaction_type = '$txn_esc' AND transaction_no = '$txn_no_esc' AND status = 1"
        );

        $last_balance = null;
        if ($row_customer_id > 0) {
            $last_balance = getRecord(
                "SELECT $cols_bal FROM tbl_customer_ledger WHERE customer_id = $row_customer_id AND status = 1
                $ledger_br_scope ORDER BY transaction_date DESC, id DESC LIMIT 1"
            );
        }
        if (!$last_balance) {
            $last_balance = getRecord(
                "SELECT $cols_bal FROM tbl_customer_ledger WHERE customer_name = '$row_cust_esc' AND status = 1
                $ledger_br_scope ORDER BY transaction_date DESC, id DESC LIMIT 1"
            );
        }

        $prev_amt = (float) ($last_balance['balance_amount'] ?? 0);
        $prev_gold = (float) ($last_balance['balance_gold'] ?? 0);
        $prev_silver = (float) ($last_balance['balance_silver'] ?? 0);
        $prev_gold_pure = $use_gold_pure ? (float) ($last_balance['balance_gold_pure'] ?? 0) : 0.0;
        $new_cust_bal = $prev_amt + $amt;

        $balance_vals_cust = $use_gold_pure
            ? "$new_cust_bal, $prev_gold, $prev_gold_pure, $prev_silver,"
            : "$new_cust_bal, $prev_gold, $prev_silver,";

        $desc_bits = [
            'Investment fund installment — ' . $row_fund_no,
            (string) $norm['label'],
        ];
        if ($entry_by !== '') {
            $desc_bits[] = 'Entry by ' . $entry_by;
        }
        if (!empty($norm['pay_mode'])) {
            $desc_bits[] = 'Pay: ' . $norm['pay_mode'];
        }
        $desc_cust = mysqli_real_escape_string($conn, implode(' · ', $desc_bits));

        if ($ledger_has_against_cols) {
            $against_cust = mysqli_real_escape_string(
                $conn,
                $layaways_fund_name . '(' . number_format($amt, 2, '.', '') . 'Cr)'
            );
            $against_inv_cust = mysqli_real_escape_string($conn, $txn_no);
            $against_vals_cust = ", '$against_cust', '$against_inv_cust'";
        } else {
            $against_vals_cust = '';
        }

        $ref_sql = "'" . $txn_no_esc . "'";
        $sql_cust = "
            INSERT INTO tbl_customer_ledger (
                customer_id" . $ledger_branch_sql_col . ", customer_name, transaction_type, transaction_id, transaction_no,
                transaction_date, debit_amount, credit_amount,
                $ledger_debit_credit
                $ledger_balance
                description, reference_no, status, created_by, created_at
                $against_cols
            ) VALUES (
                " . ($row_customer_id > 0 ? $row_customer_id : 0) . $ledger_branch_sql_val . ",
                '$row_cust_esc',
                '$txn_esc',
                0,
                '$txn_no_esc',
                '$date_esc',
                $amt,
                0,
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
            throw new Exception(mysqli_error($conn) ?: 'Customer ledger insert failed for ' . $txn_no);
        }
        $cust_ledger_id = (int) mysqli_insert_id($conn);

        $last_lay = getRecord(
            "SELECT $cols_bal FROM tbl_customer_ledger WHERE customer_name = '$lay_esc' AND status = 1
            $ledger_br_scope ORDER BY transaction_date DESC, id DESC LIMIT 1"
        );
        $lay_prev = (float) ($last_lay['balance_amount'] ?? 0);
        $lg = (float) ($last_lay['balance_gold'] ?? 0);
        $ls = (float) ($last_lay['balance_silver'] ?? 0);
        $lgp = $use_gold_pure ? (float) ($last_lay['balance_gold_pure'] ?? 0) : 0.0;
        $new_lay_bal = $lay_prev - $amt;

        $balance_vals_lay = $use_gold_pure
            ? "$new_lay_bal, $lg, $lgp, $ls,"
            : "$new_lay_bal, $lg, $ls,";

        $desc_lay = mysqli_real_escape_string(
            $conn,
            'Investment fund installment from ' . $row_customer_name . ' — ' . $row_fund_no . ' · ' . $norm['label']
        );

        if ($ledger_has_against_cols) {
            $against_lay = mysqli_real_escape_string(
                $conn,
                $row_customer_name . '(' . number_format($amt, 2, '.', '') . 'Dr)'
            );
            $against_inv_lay = mysqli_real_escape_string($conn, $txn_no);
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
                '$txn_no_esc',
                '$date_esc',
                0,
                $amt,
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
            throw new Exception(mysqli_error($conn) ?: 'Layaways Fund ledger insert failed for ' . $txn_no);
        }

        $posted[] = [
            'inst_no' => (int) $norm['inst_no'],
            'transaction_no' => $txn_no,
            'amount' => $amt,
            'ledger_id' => $cust_ledger_id,
            'fund_no' => $row_fund_no,
            'fund_local_id' => $fund_local_id,
        ];
    }

    if (empty($posted) && !empty($errors)) {
        throw new Exception($errors[0]);
    }
    if (empty($posted)) {
        throw new Exception('No installment rows to post.');
    }

    if (function_exists('mysqli_commit')) {
        mysqli_commit($conn);
    } else {
        mysqli_query($conn, 'COMMIT');
    }

    echo json_encode([
        'ok' => true,
        'posted' => $posted,
        'count' => count($posted),
        'skipped' => $skipped,
        'warnings' => $errors,
        'branch_id' => $post_branch,
    ]);
} catch (Throwable $e) {
    if (function_exists('mysqli_rollback')) {
        mysqli_rollback($conn);
    } else {
        mysqli_query($conn, 'ROLLBACK');
    }
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
