<?php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../includes/auragold_require_login.php';
auragold_require_login_or_exit();

header('Content-Type: application/json');

$customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$customer_name = isset($_GET['customer_name']) ? esc($_GET['customer_name']) : '';
$type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : '';
// Purchase / Sale invoice: align money "Previous Balance" with Account Ledger CL = Σ(debit_amount − credit_amount).
// Last row balance_amount can be wrong after hedging + multi-payment.
$purchase_ledger_prev_balance = !empty($_GET['purchase_ledger_prev_balance']) || !empty($_GET['ledger_cl_balance']);

require_once __DIR__ . '/../includes/ensure_customer_ledger_branch_column.php';
auragold_ensure_customer_ledger_branch_column($conn);
$ledger_has_branch = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'branch_id');
$scope_branch_id = isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : 0;
if (function_exists('auragold_resolve_branch_id_for_session')) {
    $scope_branch_id = (int) auragold_resolve_branch_id_for_session($scope_branch_id);
}
$main_bid = function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0;
if ($ledger_has_branch && $scope_branch_id <= 0 && function_exists('auragold_effective_branch_id')) {
    $scope_branch_id = (int) auragold_effective_branch_id();
}
if ($ledger_has_branch && $scope_branch_id <= 0 && function_exists('auragold_account_ledger_resolved_branch_ids')) {
    $resolved_pb = auragold_account_ledger_resolved_branch_ids();
    if (!empty($resolved_pb[0])) {
        $scope_branch_id = (int) $resolved_pb[0];
    }
}
if ($ledger_has_branch && $scope_branch_id <= 0 && $main_bid > 0) {
    $scope_branch_id = $main_bid;
}
if (function_exists('auragold_normalize_branch_scope_for_working_db')) {
    $scope_branch_id = auragold_normalize_branch_scope_for_working_db($scope_branch_id);
}
// Same branch scope as accountledger-report.php so Previous Balance CL matches Account Ledger.
$brLedgerAnd = '';
if ($ledger_has_branch && function_exists('auragold_account_ledger_branch_scope_sql')) {
    $brLedgerAnd = auragold_account_ledger_branch_scope_sql('');
} elseif ($ledger_has_branch && $scope_branch_id > 0) {
    $brLedgerAnd = function_exists('auragold_customer_ledger_branch_and_sql')
        ? auragold_customer_ledger_branch_and_sql($scope_branch_id)
        : ' AND COALESCE(branch_id, 0) = ' . (int) $scope_branch_id;
}
$si_has_branch = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_sale_invoices', 'branch_id');
$brSiAnd = '';
if ($si_has_branch) {
    if (function_exists('auragold_account_ledger_branch_scope_sql')) {
        $brSiAnd = auragold_account_ledger_branch_scope_sql('');
    } elseif ($scope_branch_id > 0) {
        $si_scope_is_main = function_exists('auragold_customer_ledger_branch_is_main_scope')
            && auragold_customer_ledger_branch_is_main_scope($scope_branch_id);
        if ($si_scope_is_main) {
            $brSiAnd = ' AND (branch_id = ' . (int) $scope_branch_id . ' OR branch_id IS NULL OR branch_id = 0)';
        } else {
            $brSiAnd = ' AND COALESCE(branch_id, 0) = ' . (int) $scope_branch_id;
        }
    }
}
// When branch-scoped, do not use tbl_customer_balance — it is not per-branch and causes wrong Previous Balance vs Account Ledger.
$skip_global_customer_balance = ($ledger_has_branch && ($brLedgerAnd !== '' || $scope_branch_id > 0));

if ($customer_id <= 0 && empty($customer_name)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Customer ID or name is required',
        'balance' => [
            'amount' => 0,
            'gold' => 0,
            'silver' => 0
        ]
    ]);
    exit;
}

// Prefer balance_gold_pure for "Previous Balance" metal wt (purity wt) when column exists
$has_balance_gold_pure = false;
$col_check = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_customer_ledger LIKE 'balance_gold_pure'");
if ($col_check && mysqli_num_rows($col_check) > 0) {
    $has_balance_gold_pure = true;
}
$gold_pure_sql = $has_balance_gold_pure ? ", balance_gold_pure" : "";

/**
 * Sale / purchase invoice panel (ledger_cl_balance=1): compute CL balance with minimal queries and return.
 */
function auragold_get_customer_balance_ledger_cl_fast(
    mysqli $conn,
    string $ledger_scope,
    string $brLedgerAnd,
    bool $has_balance_gold_pure,
    string $gold_pure_sql
): ?array {
    if ($ledger_scope === '') {
        return null;
    }
    $ledger_cnt = getRecord("SELECT COUNT(*) AS n FROM tbl_customer_ledger WHERE status = 1 AND $ledger_scope $brLedgerAnd");
    if ((int)($ledger_cnt['n'] ?? 0) <= 0) {
        return null;
    }

    $ledger_excl_pb = " AND COALESCE(transaction_type,'') <> 'previous_balance_payment'";
    $base_where = "status = 1 AND $ledger_scope $ledger_excl_pb $brLedgerAnd";

    $has_against_ledger = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'against_ledger');
    if ($has_against_ledger) {
        $amount_row = getRecord("
            SELECT
                COALESCE(SUM(debit_amount - credit_amount), 0) AS net_amt,
                COALESCE(SUM(
                    CASE
                        WHEN transaction_type = 'payment'
                            AND debit_amount > 0
                            AND ABS(credit_amount) < 0.00001
                            AND LOWER(description) LIKE '%purchase invoice%'
                            AND LOWER(TRIM(against_ledger)) LIKE 'scrap(%'
                        THEN debit_amount ELSE 0
                    END
                ), 0) AS pi_scrap,
                COALESCE(SUM(
                    CASE
                        WHEN transaction_type = 'payment'
                            AND debit_amount > 0
                            AND ABS(credit_amount) < 0.00001
                            AND LOWER(description) LIKE '%sale invoice%'
                            AND LOWER(TRIM(against_ledger)) LIKE 'scrap(%'
                        THEN debit_amount ELSE 0
                    END
                ), 0) AS si_scrap
            FROM tbl_customer_ledger
            WHERE $base_where
        ");
        $balance_amount = (float)($amount_row['net_amt'] ?? 0)
            - 2.0 * (float)($amount_row['pi_scrap'] ?? 0)
            - 2.0 * (float)($amount_row['si_scrap'] ?? 0);
    } else {
        $amount_row = getRecord("
            SELECT COALESCE(SUM(debit_amount - credit_amount), 0) AS net_amt
            FROM tbl_customer_ledger
            WHERE $base_where
        ");
        $balance_amount = (float)($amount_row['net_amt'] ?? 0);
    }

    $hedging_metal_sql = "LOWER(COALESCE(description,'')) LIKE '%(hedging)%'";
    $payment_metal_sql = "(COALESCE(transaction_type,'') = 'payment' AND (ABS(COALESCE(debit_gold,0)) + ABS(COALESCE(credit_gold,0)) + ABS(COALESCE(debit_silver,0)) + ABS(COALESCE(credit_silver,0)) > 0.00001))";
    $rv_pv_metal_sql = "(COALESCE(transaction_type,'') IN ('receipt_voucher','sale_receipt_voucher','payment_voucher') AND (ABS(COALESCE(debit_gold,0)) + ABS(COALESCE(credit_gold,0)) + ABS(COALESCE(debit_silver,0)) + ABS(COALESCE(credit_silver,0)) > 0.00001))";
    $ledger_metal_view_sql = "($hedging_metal_sql OR $payment_metal_sql OR $rv_pv_metal_sql)";

    $metal_cl_row = getRecord("
        SELECT
            COALESCE(SUM(debit_gold - credit_gold), 0) AS net_gold,
            COALESCE(SUM(debit_silver - credit_silver), 0) AS net_silver
            " . ($has_balance_gold_pure ? ", COALESCE(SUM(debit_gold_pure - credit_gold_pure), 0) AS net_gold_pure" : "") . "
        FROM tbl_customer_ledger
        WHERE status = 1 AND $ledger_scope AND ($ledger_metal_view_sql)
        $brLedgerAnd
    ");

    $balance_gold = $has_balance_gold_pure && isset($metal_cl_row['net_gold_pure'])
        ? (float)$metal_cl_row['net_gold_pure']
        : (float)($metal_cl_row['net_gold'] ?? 0);
    $balance_silver = (float)($metal_cl_row['net_silver'] ?? 0);

    $diamond_cols = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'balance_diamond')
        ? ', balance_diamond, balance_gemstone'
        : '';
    $last_row = getRecord("
        SELECT balance_amount $diamond_cols
        FROM tbl_customer_ledger
        WHERE status = 1 AND $ledger_scope
        $brLedgerAnd
        ORDER BY id DESC
        LIMIT 1
    ");
    $balance_diamond = (float)($last_row['balance_diamond'] ?? 0);
    $balance_gemstone = (float)($last_row['balance_gemstone'] ?? 0);

    return [
        'balance_amount' => $balance_amount,
        'balance_gold' => $balance_gold,
        'balance_silver' => $balance_silver,
        'balance_diamond' => $balance_diamond,
        'balance_gemstone' => $balance_gemstone,
    ];
}

$ledger_scope_for_cl = '';
if (!empty($customer_name)) {
    $esc_sum_name = mysqli_real_escape_string($conn, trim((string) $customer_name));
    if ($esc_sum_name !== '') {
        $ledger_scope_for_cl = "LOWER(TRIM(customer_name)) = LOWER(TRIM('$esc_sum_name'))";
    }
}
if ($ledger_scope_for_cl === '' && $customer_id > 0) {
    $ledger_scope_for_cl = 'customer_id = ' . (int) $customer_id;
}

if ($purchase_ledger_prev_balance && function_exists('auragold_account_ledger_party_cl_balance')) {
    $cl_balance = auragold_account_ledger_party_cl_balance($customer_id, $customer_name, $has_balance_gold_pure);
    if (!empty($cl_balance['found'])) {
        $original_amount = (float) ($cl_balance['balance_amount'] ?? 0);
        $original_gold = (float) ($cl_balance['balance_gold'] ?? 0);
        $original_silver = (float) ($cl_balance['balance_silver'] ?? 0);
        $balance_diamond = (float) ($cl_balance['balance_diamond'] ?? 0);
        $balance_gemstone = (float) ($cl_balance['balance_gemstone'] ?? 0);
        echo json_encode([
            'status' => 'success',
            'balance' => [
                'amount' => $original_amount,
                'gold' => $original_gold,
                'silver' => $original_silver,
                'diamond' => $balance_diamond,
                'gemstone' => $balance_gemstone,
            ],
            'advance' => [
                'amount' => 0,
                'total_amount' => 0,
                'used_adjusted_balance' => 0,
                'gold' => 0,
                'silver' => 0,
            ],
            'original_balance' => [
                'amount' => $original_amount,
                'gold' => $original_gold,
                'silver' => $original_silver,
                'diamond' => $balance_diamond,
                'gemstone' => $balance_gemstone,
            ],
        ]);
        exit;
    }
}

if ($purchase_ledger_prev_balance && $ledger_scope_for_cl !== '') {
    $fast_balance = auragold_get_customer_balance_ledger_cl_fast(
        $conn,
        $ledger_scope_for_cl,
        $brLedgerAnd,
        $has_balance_gold_pure,
        $gold_pure_sql
    );
    if (is_array($fast_balance)) {
        $original_amount = (float)($fast_balance['balance_amount'] ?? 0);
        $original_gold = (float)($fast_balance['balance_gold'] ?? 0);
        $original_silver = (float)($fast_balance['balance_silver'] ?? 0);
        $balance_diamond = (float)($fast_balance['balance_diamond'] ?? 0);
        $balance_gemstone = (float)($fast_balance['balance_gemstone'] ?? 0);
        echo json_encode([
            'status' => 'success',
            'balance' => [
                'amount' => $original_amount,
                'gold' => $original_gold,
                'silver' => $original_silver,
                'diamond' => $balance_diamond,
                'gemstone' => $balance_gemstone,
            ],
            'advance' => [
                'amount' => 0,
                'total_amount' => 0,
                'used_adjusted_balance' => 0,
                'gold' => 0,
                'silver' => 0,
            ],
            'original_balance' => [
                'amount' => $original_amount,
                'gold' => $original_gold,
                'silver' => $original_silver,
                'diamond' => $balance_diamond,
                'gemstone' => $balance_gemstone,
            ],
        ]);
        exit;
    }
}

// Single source of truth: last ledger entry so "Previous Balance" matches Transaction History
// ORDER BY id DESC ensures we get the very latest row (e.g. after sale_invoice + previous_balance_payment)
$balance = null;
$ledger_balance = null;
if ($customer_id > 0) {
    $ledger_balance = getRecord("
        SELECT balance_amount, balance_gold, balance_silver $gold_pure_sql
        FROM tbl_customer_ledger
        WHERE status = 1 AND customer_id = " . (int)$customer_id . "
        $brLedgerAnd
        ORDER BY id DESC
        LIMIT 1
    ");
}
if (!$ledger_balance && !empty($customer_name)) {
    $esc_name = mysqli_real_escape_string($conn, $customer_name);
    $ledger_balance = getRecord("
        SELECT balance_amount, balance_gold, balance_silver $gold_pure_sql
        FROM tbl_customer_ledger
        WHERE status = 1 AND customer_name = '$esc_name'
        $brLedgerAnd
        ORDER BY id DESC
        LIMIT 1
    ");
}
if (!$ledger_balance && !empty($customer_name)) {
    $esc_name = mysqli_real_escape_string($conn, $customer_name);
    $ledger_balance = getRecord("
        SELECT balance_amount, balance_gold, balance_silver $gold_pure_sql
        FROM tbl_customer_ledger
        WHERE status = 1 AND LOWER(TRIM(customer_name)) = LOWER(TRIM('$esc_name'))
        $brLedgerAnd
        ORDER BY id DESC
        LIMIT 1
    ");
}
if ($ledger_balance) {
    // Amount: use last ledger row (unchanged)
    $balance_amount = (float)($ledger_balance['balance_amount'] ?? 0);
    // Metal: must match account ledger report — only Hedging entries contribute (Fixing Type = Hedging)
    $esc_for_metal = $customer_id > 0
        ? "customer_id = " . (int)$customer_id
        : "customer_name = '" . mysqli_real_escape_string($conn, $customer_name) . "'";
    $hedging_cond = "LOWER(COALESCE(description,'')) LIKE '%(hedging)%'";
    $metal_row = getRecord("
        SELECT
            COALESCE(SUM(debit_gold - credit_gold), 0) as net_gold,
            COALESCE(SUM(debit_silver - credit_silver), 0) as net_silver
            " . ($has_balance_gold_pure ? ", COALESCE(SUM(debit_gold_pure - credit_gold_pure), 0) as net_gold_pure" : "") . "
        FROM tbl_customer_ledger
        WHERE status = 1 AND $esc_for_metal AND ($hedging_cond)
        $brLedgerAnd
    ");
    $balance_gold = 0;
    $balance_silver = (float)($metal_row['net_silver'] ?? 0);
    if ($has_balance_gold_pure && isset($metal_row['net_gold_pure'])) {
        $balance_gold = (float)$metal_row['net_gold_pure'];
    } else {
        $balance_gold = (float)($metal_row['net_gold'] ?? 0);
    }
    $balance = [
        'balance_amount' => $balance_amount,
        'balance_gold' => $balance_gold,
        'balance_silver' => $balance_silver,
        'balance_diamond' => (float)($ledger_balance['balance_diamond'] ?? 0),
        'balance_gemstone' => (float)($ledger_balance['balance_gemstone'] ?? 0)
    ];
}

// If no ledger entries, try summary table (not branch-scoped — skip when filtering by branch so invoice matches Account Ledger for that branch)
if (!$balance && !$skip_global_customer_balance && $customer_id > 0) {
    $balance = getRecord("SELECT * FROM tbl_customer_balance WHERE customer_id = $customer_id");
}
if (!$balance && !$skip_global_customer_balance && !empty($customer_name)) {
    $balance = getRecord("SELECT * FROM tbl_customer_balance WHERE customer_name = '$customer_name' LIMIT 1");
    if (!$balance) {
        $balance = getRecord("SELECT * FROM tbl_customer_balance WHERE LOWER(customer_name) = LOWER('$customer_name') LIMIT 1");
    }
    if (!$balance) {
        $trimmed_name = trim($customer_name);
        $balance = getRecord("SELECT * FROM tbl_customer_balance WHERE TRIM(customer_name) = '$trimmed_name' LIMIT 1");
    }
}

// If type=supplier (purchase invoice) and still no balance, try last purchase invoice (customer ledger source for suppliers)
if (!$balance && $type === 'supplier') {
    $pi_where = "status != 'cancelled' AND status != 'draft'";
    if ($customer_id > 0) {
        $pi_where = "supplier_id = $customer_id AND status != 'cancelled' AND status != 'draft'";
    } else if (!empty($customer_name)) {
        $pi_where = "supplier_name = '$customer_name' AND status != 'cancelled' AND status != 'draft'";
    }
    $last_purchase = getRecord("
        SELECT balance_amt, previous_gold, previous_silver
        FROM tbl_purchase_invoices
        WHERE $pi_where
        ORDER BY invoice_date DESC, id DESC
        LIMIT 1
    ");
    if (!$last_purchase && !empty($customer_name) && $customer_id <= 0) {
        $name_lower = strtolower(trim($customer_name));
        $last_purchase = getRecord("
            SELECT balance_amt, previous_gold, previous_silver
            FROM tbl_purchase_invoices
            WHERE LOWER(TRIM(supplier_name)) = '" . addslashes($name_lower) . "'
            AND status != 'cancelled' AND status != 'draft'
            ORDER BY invoice_date DESC, id DESC
            LIMIT 1
        ");
    }
    if ($last_purchase) {
        $balance = [
            'balance_amount' => (float)($last_purchase['balance_amt'] ?? 0),
            'balance_gold' => (float)($last_purchase['previous_gold'] ?? 0),
            'balance_silver' => (float)($last_purchase['previous_silver'] ?? 0),
            'balance_diamond' => 0,
            'balance_gemstone' => 0
        ];
    }
}

// If still not found, get balance from last sale invoice's balance_amt (skip when ledger CL mode — must match Account Ledger, not draft SI balance_amt)
if (!$balance && !$purchase_ledger_prev_balance) {
    $invoice_where = "status != 'cancelled'";
    if ($customer_id > 0) {
        $invoice_where = "customer_id = $customer_id AND status != 'cancelled'";
    } else if (!empty($customer_name)) {
        $invoice_where = "customer_name = '$customer_name' AND status != 'cancelled'";
    }
    
    // Get last sale invoice's balance_amt (this is the previous balance for next invoice)
    $last_invoice = getRecord("
        SELECT 
            balance_amt,
            previous_gold,
            previous_silver
        FROM tbl_sale_invoices
        WHERE $invoice_where
        $brSiAnd
        ORDER BY invoice_date DESC, id DESC
        LIMIT 1
    ");
    
    // If not found with exact match, try case-insensitive
    if (!$last_invoice && !empty($customer_name) && $customer_id <= 0) {
        $last_invoice = getRecord("
            SELECT 
                balance_amt,
                previous_gold,
                previous_silver
            FROM tbl_sale_invoices
            WHERE LOWER(customer_name) = LOWER('$customer_name') AND status != 'cancelled'
            $brSiAnd
            ORDER BY invoice_date DESC, id DESC
            LIMIT 1
        ");
    }
    
    if ($last_invoice) {
        // Use balance_amt from last invoice as the previous balance
        $balance = [
            'balance_amount' => (float)($last_invoice['balance_amt'] ?? 0),
            'balance_gold' => (float)($last_invoice['previous_gold'] ?? 0),
            'balance_silver' => (float)($last_invoice['previous_silver'] ?? 0),
            'balance_diamond' => 0,
            'balance_gemstone' => 0
        ];
    } else {
        // If no sale invoice found, try ledger entry
        $where_clause = "status = 1";
        if ($customer_id > 0) {
            $where_clause = "status = 1 AND customer_id = $customer_id";
        } else if (!empty($customer_name)) {
            $where_clause = "status = 1 AND customer_name = '$customer_name'";
        }
        
        // Get last ledger entry's running balance (most recent transaction)
        $ledger_balance = getRecord("
            SELECT 
                balance_amount,
                balance_gold,
                balance_silver $gold_pure_sql
            FROM tbl_customer_ledger
            WHERE $where_clause
            $brLedgerAnd
            ORDER BY transaction_date DESC, id DESC
            LIMIT 1
        ");
        
        // If not found with exact match, try case-insensitive
        if (!$ledger_balance && !empty($customer_name) && $customer_id <= 0) {
            $ledger_balance = getRecord("
                SELECT 
                    balance_amount,
                    balance_gold,
                    balance_silver $gold_pure_sql
                FROM tbl_customer_ledger
                WHERE status = 1 AND LOWER(customer_name) = LOWER('$customer_name')
                $brLedgerAnd
                ORDER BY transaction_date DESC, id DESC
                LIMIT 1
            ");
        }
        
        if ($ledger_balance) {
            $balance_amount = (float)($ledger_balance['balance_amount'] ?? 0);
            $esc_for_metal = $customer_id > 0
                ? "customer_id = " . (int)$customer_id
                : "customer_name = '" . mysqli_real_escape_string($conn, $customer_name) . "'";
            $hedging_cond = "LOWER(COALESCE(description,'')) LIKE '%(hedging)%'";
            $metal_row = getRecord("
                SELECT
                    COALESCE(SUM(debit_gold - credit_gold), 0) as net_gold,
                    COALESCE(SUM(debit_silver - credit_silver), 0) as net_silver
                    " . ($has_balance_gold_pure ? ", COALESCE(SUM(debit_gold_pure - credit_gold_pure), 0) as net_gold_pure" : "") . "
                FROM tbl_customer_ledger
                WHERE status = 1 AND $esc_for_metal AND ($hedging_cond)
                $brLedgerAnd
            ");
            $balance_gold = $has_balance_gold_pure && isset($metal_row['net_gold_pure'])
                ? (float)$metal_row['net_gold_pure']
                : (float)($metal_row['net_gold'] ?? 0);
            $balance_silver = (float)($metal_row['net_silver'] ?? 0);
            $balance = [
                'balance_amount' => $balance_amount,
                'balance_gold' => $balance_gold,
                'balance_silver' => $balance_silver,
                'balance_diamond' => (float)($ledger_balance['balance_diamond'] ?? 0),
                'balance_gemstone' => (float)($ledger_balance['balance_gemstone'] ?? 0)
            ];
        } else {
            $balance = [
                'balance_amount' => 0,
                'balance_gold' => 0,
                'balance_silver' => 0,
                'balance_diamond' => 0,
                'balance_gemstone' => 0
            ];
        }
    }
}

// Calculate total advance vouchers for this customer
$advance_where = "status != 'cancelled'";
if ($customer_id > 0) {
    $advance_where = "customer_id = $customer_id AND status != 'cancelled'";
} else if (!empty($customer_name)) {
    $advance_where = "customer_name = '$customer_name' AND status != 'cancelled'";
}

// Get total advance amount from customer advance vouchers
$advance_total = getRecord("
    SELECT 
        COALESCE(SUM(total_amount), 0) as total_advance_amount,
        COALESCE(SUM(total_gold), 0) as total_advance_gold,
        COALESCE(SUM(total_silver), 0) as total_advance_silver
    FROM tbl_customer_advance_vouchers
    WHERE $advance_where
");

// If not found with exact match, try case-insensitive
if (!$advance_total && !empty($customer_name) && $customer_id <= 0) {
    $advance_total = getRecord("
        SELECT 
            COALESCE(SUM(total_amount), 0) as total_advance_amount,
            COALESCE(SUM(total_gold), 0) as total_advance_gold,
            COALESCE(SUM(total_silver), 0) as total_advance_silver
        FROM tbl_customer_advance_vouchers
        WHERE LOWER(customer_name) = LOWER('$customer_name') AND status != 'cancelled'
    ");
}

// Also get advance payments from tbl_advance_payments table
$advance_payment_where = "status != 'cancelled'";
if ($customer_id > 0) {
    $advance_payment_where = "customer_id = $customer_id AND status != 'cancelled'";
} else if (!empty($customer_name)) {
    $advance_payment_where = "customer_name = '$customer_name' AND status != 'cancelled'";
}

$advance_payment_total = getRecord("
    SELECT 
        COALESCE(SUM(total_amount), 0) as total_advance_amount,
        COALESCE(SUM(total_gold), 0) as total_advance_gold,
        COALESCE(SUM(total_silver), 0) as total_advance_silver
    FROM tbl_advance_payments
    WHERE $advance_payment_where
");

// If not found with exact match, try case-insensitive
if ((!$advance_payment_total || (float)($advance_payment_total['total_advance_amount'] ?? 0) == 0) && !empty($customer_name) && $customer_id <= 0) {
    $advance_payment_total = getRecord("
        SELECT 
            COALESCE(SUM(total_amount), 0) as total_advance_amount,
            COALESCE(SUM(total_gold), 0) as total_advance_gold,
            COALESCE(SUM(total_silver), 0) as total_advance_silver
        FROM tbl_advance_payments
        WHERE LOWER(customer_name) = LOWER('$customer_name') AND status != 'cancelled'
    ");
}

// Combine advance amounts from both tables
$advance_amount = (float)($advance_total['total_advance_amount'] ?? 0) + (float)($advance_payment_total['total_advance_amount'] ?? 0);
$advance_gold = (float)($advance_total['total_advance_gold'] ?? 0) + (float)($advance_payment_total['total_advance_gold'] ?? 0);
$advance_silver = (float)($advance_total['total_advance_silver'] ?? 0) + (float)($advance_payment_total['total_advance_silver'] ?? 0);

// Calculate how much advance has been used in previous sale invoices
// The used advance is calculated from sale invoices where adjusted balance was deducted
// Formula: Used Advance = Grand Total - Balance Amt - Paid Amt (if this represents adjusted balance usage)
// But a better approach: Track the adjusted_balance_used field if it exists, or calculate from the difference

$used_advance_where = "status != 'cancelled'";
if ($customer_id > 0) {
    $used_advance_where = "customer_id = $customer_id AND status != 'cancelled'";
} else if (!empty($customer_name)) {
    $used_advance_where = "customer_name = '$customer_name' AND status != 'cancelled'";
}

// Check if adjusted_balance_used column exists in sale_invoices table
// If it exists, use it directly; otherwise calculate from balance_amt
$check_column = mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_invoices LIKE 'adjusted_balance_used'");
$has_adjusted_balance_used = ($check_column && mysqli_num_rows($check_column) > 0);

if ($has_adjusted_balance_used) {
    // Use the stored adjusted_balance_used field
    $used_advance_query = "
        SELECT COALESCE(SUM(adjusted_balance_used), 0) as used_advance_amount
        FROM tbl_sale_invoices
        WHERE $used_advance_where
        $brSiAnd
    ";
} else {
    // Calculate used advance: If balance_amt was reduced by adjusted balance, 
    // the difference shows how much adjusted balance was used
    // Formula: Used = (Grand Total - Expected Balance) - Actual Balance
    // Expected Balance = Grand Total - Paid Amt (without adjusted balance deduction)
    // Actual Balance = balance_amt (with adjusted balance deduction)
    // Used = Expected Balance - Actual Balance = (Grand Total - Paid Amt) - balance_amt
    $used_advance_query = "
        SELECT 
            COALESCE(SUM(
                CASE 
                    WHEN (grand_total - paid_amt - balance_amt) > 0 
                    THEN (grand_total - paid_amt - balance_amt)
                    ELSE 0
                END
            ), 0) as used_advance_amount
        FROM tbl_sale_invoices
        WHERE $used_advance_where
        $brSiAnd
        AND grand_total > 0
    ";
}

$used_advance_result = getRecord($used_advance_query);

// If not found with exact match, try case-insensitive
if (!$used_advance_result && !empty($customer_name) && $customer_id <= 0) {
    if ($has_adjusted_balance_used) {
        $used_advance_query = "
            SELECT COALESCE(SUM(adjusted_balance_used), 0) as used_advance_amount
            FROM tbl_sale_invoices
            WHERE LOWER(customer_name) = LOWER('$customer_name') AND status != 'cancelled'
            $brSiAnd
        ";
    } else {
        $used_advance_query = "
            SELECT 
                COALESCE(SUM(
                    CASE 
                        WHEN (grand_total - paid_amt - balance_amt) > 0 
                        THEN (grand_total - paid_amt - balance_amt)
                        ELSE 0
                    END
                ), 0) as used_advance_amount
            FROM tbl_sale_invoices
            WHERE LOWER(customer_name) = LOWER('$customer_name') AND status != 'cancelled'
            $brSiAnd
            AND grand_total > 0
        ";
    }
    $used_advance_result = getRecord($used_advance_query);
}

$used_adjusted_balance_amount = (float)($used_advance_result['used_advance_amount'] ?? 0);

// Available advance = Total advance - Used adjusted balance
// The adjusted balance represents: Previous Balance - Advance Amount
// When adjusted balance is used, it reduces the available advance for next time
$available_advance_amount = max(0, $advance_amount - $used_adjusted_balance_amount);

// Ensure diamond/gemstone exist on balance (0 if not in ledger/balance table)
$balance_diamond = (float)($balance['balance_diamond'] ?? 0);
$balance_gemstone = (float)($balance['balance_gemstone'] ?? 0);

if ($purchase_ledger_prev_balance && is_array($balance) && array_key_exists('balance_amount', $balance)) {
    $ledger_scope = '';
    if ($customer_id > 0) {
        $ledger_scope = 'customer_id = ' . (int)$customer_id;
    } else {
        $esc_sum_name = mysqli_real_escape_string($conn, trim((string)$customer_name));
        if ($esc_sum_name !== '') {
            $ledger_scope = "LOWER(TRIM(customer_name)) = LOWER(TRIM('$esc_sum_name'))";
        }
    }
    if ($ledger_scope !== '') {
        $ledger_cnt = getRecord("SELECT COUNT(*) AS n FROM tbl_customer_ledger WHERE status = 1 AND $ledger_scope $brLedgerAnd");
        if ((int)($ledger_cnt['n'] ?? 0) > 0) {
            // Align with accountledger-report.php: hide merged previous_balance_payment lines (legacy duplicates).
            $ledger_excl_pb = " AND COALESCE(transaction_type,'') <> 'previous_balance_payment'";
            $sum_row = getRecord("
                SELECT COALESCE(SUM(debit_amount - credit_amount), 0) AS net_amt
                FROM tbl_customer_ledger
                WHERE status = 1 AND $ledger_scope
                $ledger_excl_pb
                $brLedgerAnd
            ");
            // Same as accountledger-report.php footer: PI scrap party lines stored as debit, shown under credit (net − 2×scrap).
            $pi_scrap = getRecord("
                SELECT COALESCE(SUM(debit_amount), 0) AS s
                FROM tbl_customer_ledger
                WHERE status = 1 AND $ledger_scope
                $ledger_excl_pb
                $brLedgerAnd
                AND transaction_type = 'payment'
                AND debit_amount > 0
                AND ABS(credit_amount) < 0.00001
                AND LOWER(description) LIKE '%purchase invoice%'
                AND LOWER(TRIM(against_ledger)) LIKE 'scrap(%'
            ");
            $scrap_s = (float)($pi_scrap['s'] ?? 0);
            // Sale invoice scrap payment: same Dr/Cr flip as PI scrap for net Σ(debit−credit).
            $si_scrap = getRecord("
                SELECT COALESCE(SUM(debit_amount), 0) AS s
                FROM tbl_customer_ledger
                WHERE status = 1 AND $ledger_scope
                $ledger_excl_pb
                $brLedgerAnd
                AND transaction_type = 'payment'
                AND debit_amount > 0
                AND ABS(credit_amount) < 0.00001
                AND LOWER(description) LIKE '%sale invoice%'
                AND LOWER(TRIM(against_ledger)) LIKE 'scrap(%'
            ");
            $si_scrap_s = (float)($si_scrap['s'] ?? 0);
            $balance['balance_amount'] = (float)($sum_row['net_amt'] ?? 0) - 2.0 * $scrap_s - 2.0 * $si_scrap_s;

            // Match accountledger-report.php View All metal footer: hedging + payment + RV/PV party rows with weight (e.g. Metal Exchange on PI payment and sale auto receipt voucher).
            $hedging_metal_sql = "LOWER(COALESCE(description,'')) LIKE '%(hedging)%'";
            $payment_metal_sql = "(COALESCE(transaction_type,'') = 'payment' AND (ABS(COALESCE(debit_gold,0)) + ABS(COALESCE(credit_gold,0)) + ABS(COALESCE(debit_silver,0)) + ABS(COALESCE(credit_silver,0)) > 0.00001))";
            $rv_pv_metal_sql = "(COALESCE(transaction_type,'') IN ('receipt_voucher','sale_receipt_voucher','payment_voucher') AND (ABS(COALESCE(debit_gold,0)) + ABS(COALESCE(credit_gold,0)) + ABS(COALESCE(debit_silver,0)) + ABS(COALESCE(credit_silver,0)) > 0.00001))";
            $ledger_metal_view_sql = "($hedging_metal_sql OR $payment_metal_sql OR $rv_pv_metal_sql)";
            $metal_cl_row = getRecord("
                SELECT
                    COALESCE(SUM(debit_gold - credit_gold), 0) AS net_gold,
                    COALESCE(SUM(debit_silver - credit_silver), 0) AS net_silver
                    " . ($has_balance_gold_pure ? ", COALESCE(SUM(debit_gold_pure - credit_gold_pure), 0) AS net_gold_pure" : "") . "
                FROM tbl_customer_ledger
                WHERE status = 1 AND $ledger_scope AND ($ledger_metal_view_sql)
                $brLedgerAnd
            ");
            $balance['balance_silver'] = (float)($metal_cl_row['net_silver'] ?? 0);
            if ($has_balance_gold_pure && isset($metal_cl_row['net_gold_pure'])) {
                $balance['balance_gold'] = (float)$metal_cl_row['net_gold_pure'];
            } else {
                $balance['balance_gold'] = (float)($metal_cl_row['net_gold'] ?? 0);
            }
        }
    }
}

// Calculate adjusted balance: Previous Balance - Available Advance Amount (after deducting used adjusted balance)
$adjusted_balance = [
    'amount' => (float)($balance['balance_amount'] ?? 0) - $available_advance_amount,
    'gold' => (float)($balance['balance_gold'] ?? 0) - $advance_gold,
    'silver' => (float)($balance['balance_silver'] ?? 0) - $advance_silver,
    'diamond' => $balance_diamond,
    'gemstone' => $balance_gemstone
];

// original_balance = ledger running balance — use for "Previous Balance" display
$original_amount = (float)($balance['balance_amount'] ?? 0);
$original_gold = (float)($balance['balance_gold'] ?? 0);
$original_silver = (float)($balance['balance_silver'] ?? 0);

echo json_encode([
    'status' => 'success',
    'balance' => [
        'amount' => $adjusted_balance['amount'],
        'gold' => $adjusted_balance['gold'],
        'silver' => $adjusted_balance['silver'],
        'diamond' => $adjusted_balance['diamond'],
        'gemstone' => $adjusted_balance['gemstone']
    ],
    'advance' => [
        'amount' => $available_advance_amount,
        'total_amount' => $advance_amount,
        'used_adjusted_balance' => $used_adjusted_balance_amount,
        'gold' => $advance_gold,
        'silver' => $advance_silver
    ],
    'original_balance' => [
        'amount' => $original_amount,
        'gold' => $original_gold,
        'silver' => $original_silver,
        'diamond' => $balance_diamond,
        'gemstone' => $balance_gemstone
    ]
]);
?>

