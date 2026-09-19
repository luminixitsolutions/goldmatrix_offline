<?php
/**
 * Shared ledger → TB group aggregation for financial statements (trial balance, balance sheet).
 * Depends on global $conn, getList(), getRecord().
 */

if (!function_exists('fs_normalize_sql_date')) {
    function fs_normalize_sql_date($s) {
        $s = trim((string) $s);
        if ($s === '') {
            return '';
        }
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $s, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            return $s;
        }
        return $s;
    }
}

if (!function_exists('fs_map_ledger_to_group')) {
    /**
     * @param array<string,int> $customer_sundry_map
     * @param array<string,int> $customer_sundry_lower
     */
    function fs_map_ledger_to_group($ledger_name, array $customer_sundry_map, array $customer_sundry_lower) {
        $n = trim((string) $ledger_name);
        if ($n === '') {
            return 'Current Assets';
        }
        $lower = strtolower($n);

        if ($lower === 'profit and loss opening' || strcasecmp($n, 'Profit and Loss Opening') === 0) {
            return 'Profit and Loss Opening';
        }
        if ($lower === 'profit and loss' || strcasecmp($n, 'Profit and Loss') === 0) {
            return 'Profit and Loss';
        }

        if ($n === 'Sales Account' || $n === 'Making Sale Account') {
            return 'Sales Account';
        }
        if ($n === 'Purchase Account' || $n === 'Making Purchase Account') {
            return 'Purchase Account';
        }

        if (stripos($lower, 'expense') !== false || $lower === 'expenses' || $lower === 'indirect expenses') {
            return 'Indirect Expenses';
        }

        if ($n === 'Cash' || $n === 'Bank Account') {
            return 'Current Assets';
        }

        if (in_array($n, ['Tax Ledger', 'Hedging Account', 'Discount Received', 'Manufacturing Account', 'Sundry Debtors'], true)) {
            return 'Current Assets';
        }
        if ($n === 'Sundry Creditors') {
            return 'Current Liabilities';
        }

        $sid = null;
        if (isset($customer_sundry_map[$n])) {
            $sid = (int) $customer_sundry_map[$n];
        } elseif (isset($customer_sundry_lower[strtolower($n)])) {
            $sid = (int) $customer_sundry_lower[strtolower($n)];
        }
        if ($sid !== null) {
            if ($sid === 2) {
                return 'Current Liabilities';
            }
            if ($sid === 1 || $sid === 29) {
                return 'Current Assets';
            }
        }

        return 'Current Assets';
    }
}

/**
 * Load customer name → sundry_debtors_id maps.
 *
 * @return array{0: array<string,int>, 1: array<string,int>}
 */
function fs_load_customer_sundry_maps($conn) {
    $customer_sundry_map = [];
    $customer_sundry_lower = [];
    $tcust = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_customers'");
    if ($tcust && mysqli_num_rows($tcust) > 0) {
        $cm = getList("SELECT name, sundry_debtors_id FROM tbl_customers WHERE status = 1");
        foreach ($cm as $row) {
            $nm = trim($row['name'] ?? '');
            if ($nm === '') {
                continue;
            }
            $sid = (int) ($row['sundry_debtors_id'] ?? 0);
            if (!isset($customer_sundry_map[$nm])) {
                $customer_sundry_map[$nm] = $sid;
            }
            $lk = strtolower($nm);
            if (!isset($customer_sundry_lower[$lk])) {
                $customer_sundry_lower[$lk] = $sid;
            }
        }
    }
    if ($tcust) {
        mysqli_free_result($tcust);
    }
    return [$customer_sundry_map, $customer_sundry_lower];
}

/**
 * SQL AND fragment for tbl_customer_ledger when session has a working/login branch (matches get-customer-balance.php).
 */
function fs_customer_ledger_branch_and_sql(mysqli $conn) {
    $path = __DIR__ . '/ensure_customer_ledger_branch_column.php';
    if (is_file($path)) {
        require_once $path;
    }
    if (function_exists('auragold_ensure_customer_ledger_branch_column')) {
        auragold_ensure_customer_ledger_branch_column($conn);
    }
    if (!function_exists('auragold_tbl_has_column') || !auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'branch_id')) {
        return '';
    }
    $eff = function_exists('auragold_effective_branch_id') ? (int) auragold_effective_branch_id() : 0;
    if ($eff <= 0) {
        return '';
    }
    $main = function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0;
    if ($main > 0 && $eff === $main) {
        return ' AND (branch_id = ' . $eff . ' OR branch_id IS NULL OR branch_id = 0)';
    }
    return ' AND COALESCE(branch_id, 0) = ' . $eff;
}

/**
 * @return array{groups: array<string, array{opening: float, debit: float, credit: float, closing: float}>, ok: bool}
 */
function fs_compute_ledger_groups($conn, $from_date, $to_date, $tb_hidden_sql) {
    $tb_group_order = [
        'Current Liabilities',
        'Current Assets',
        'Sales Account',
        'Purchase Account',
        'Indirect Expenses',
        'Profit and Loss',
        'Profit and Loss Opening',
    ];

    $tchk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_customer_ledger'");
    if (!$tchk || mysqli_num_rows($tchk) === 0) {
        if ($tchk) {
            mysqli_free_result($tchk);
        }
        return ['groups' => [], 'ok' => false];
    }
    mysqli_free_result($tchk);

    [$customer_sundry_map, $customer_sundry_lower] = fs_load_customer_sundry_maps($conn);
    $br_sql = fs_customer_ledger_branch_and_sql($conn);

    $groups = [];
    foreach ($tb_group_order as $g) {
        $groups[$g] = ['opening' => 0.0, 'debit' => 0.0, 'credit' => 0.0, 'closing' => 0.0];
    }

    $names_list = getList(
        "SELECT DISTINCT customer_name FROM tbl_customer_ledger WHERE status = 1" . $tb_hidden_sql . $br_sql . " ORDER BY customer_name ASC"
    );
    foreach ($names_list as $nr) {
        $customer_name = isset($nr['customer_name']) ? $nr['customer_name'] : '';
        if ($customer_name === '') {
            continue;
        }
        $cust_esc = mysqli_real_escape_string($conn, $customer_name);

        $opening_amt = 0.0;
        if ($from_date !== '') {
            $opening_balance = getRecord(
                "SELECT balance_amount FROM tbl_customer_ledger
                WHERE customer_name = '$cust_esc' AND status = 1" . $br_sql . " AND transaction_date < '$from_date'
                ORDER BY transaction_date DESC, id DESC LIMIT 1"
            );
            if ($opening_balance) {
                $opening_amt = (float) ($opening_balance['balance_amount'] ?? 0);
            }
        } else {
            $opening_row = getRecord(
                "SELECT balance_amount, debit_amount, credit_amount FROM tbl_customer_ledger
                WHERE customer_name = '$cust_esc' AND status = 1" . $br_sql . " AND transaction_type = 'opening'
                ORDER BY transaction_date DESC, id DESC LIMIT 1"
            );
            if ($opening_row) {
                $ob = (float) ($opening_row['balance_amount'] ?? 0);
                if ($ob != 0.0) {
                    $opening_amt = $ob;
                } else {
                    $dr = (float) ($opening_row['debit_amount'] ?? 0);
                    $cr = (float) ($opening_row['credit_amount'] ?? 0);
                    $opening_amt = $dr > 0 ? $dr : -$cr;
                }
            }
        }

        $period_where = "customer_name = '$cust_esc' AND status = 1 AND COALESCE(transaction_type,'') != 'opening'";
        if ($from_date !== '') {
            $period_where .= " AND transaction_date >= '$from_date'";
        }
        if ($to_date !== '') {
            $period_where .= " AND transaction_date <= '$to_date'";
        }
        $psum = getRecord(
            "SELECT COALESCE(SUM(debit_amount),0) AS td, COALESCE(SUM(credit_amount),0) AS tc
            FROM tbl_customer_ledger WHERE $period_where" . $br_sql
        );
        $td = $psum ? (float) ($psum['td'] ?? 0) : 0.0;
        $tc = $psum ? (float) ($psum['tc'] ?? 0) : 0.0;

        $closing_amt = $opening_amt + $td - $tc;
        if (abs($opening_amt) < 0.0000001 && $td < 0.0000001 && $tc < 0.0000001 && abs($closing_amt) < 0.0000001) {
            continue;
        }

        $g = fs_map_ledger_to_group($customer_name, $customer_sundry_map, $customer_sundry_lower);
        if (!isset($groups[$g])) {
            $groups[$g] = ['opening' => 0.0, 'debit' => 0.0, 'credit' => 0.0, 'closing' => 0.0];
        }
        $groups[$g]['opening'] += $opening_amt;
        $groups[$g]['debit'] += $td;
        $groups[$g]['credit'] += $tc;
    }

    foreach ($groups as $k => $v) {
        $groups[$k]['closing'] = (float) $v['opening'] + (float) $v['debit'] - (float) $v['credit'];
    }

    return ['groups' => $groups, 'ok' => true];
}

/**
 * Map ledger to P&L trading buckets. Excludes balance-sheet-only ledgers (Cash, parties, P&L reserve, etc.).
 *
 * @param array<string,int> $customer_sundry_map
 * @param array<string,int> $customer_sundry_lower
 */
function pl_map_ledger_for_pnl($ledger_name, array $customer_sundry_map, array $customer_sundry_lower) {
    $n = trim((string) $ledger_name);
    if ($n === '') {
        return 'exclude';
    }
    $lower = strtolower($n);

    if ($lower === 'profit and loss opening' || strcasecmp($n, 'Profit and Loss Opening') === 0) {
        return 'exclude';
    }
    if ($lower === 'profit and loss' || strcasecmp($n, 'Profit and Loss') === 0) {
        return 'exclude';
    }

    if ($n === 'Sales Account' || $n === 'Making Sale Account') {
        return 'sales';
    }
    if ($n === 'Purchase Account' || $n === 'Making Purchase Account') {
        return 'purchase';
    }

    if ($n === 'Direct Expenses' || $lower === 'direct expenses') {
        return 'direct_expense';
    }
    if (stripos($lower, 'direct income') !== false) {
        return 'direct_income';
    }
    if ($n === 'Discount Received' || stripos($lower, 'indirect income') !== false) {
        return 'indirect_income';
    }

    if (stripos($lower, 'expense') !== false || $lower === 'expenses' || $lower === 'indirect expenses') {
        return 'indirect_expense';
    }

    return 'exclude';
}

/**
 * Period activity for P&L (sales, purchases, expenses). Same date rules as fs_compute_ledger_groups.
 *
 * @return array{
 *   sales_net: float,
 *   purchase_net: float,
 *   direct_expense_net: float,
 *   indirect_expense_net: float,
 *   direct_income_net: float,
 *   indirect_income_net: float,
 *   ok: bool
 * }
 */
function fs_compute_pnl_buckets($conn, $from_date, $to_date, $tb_hidden_sql) {
    $tchk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_customer_ledger'");
    if (!$tchk || mysqli_num_rows($tchk) === 0) {
        if ($tchk) {
            mysqli_free_result($tchk);
        }
        return [
            'sales_net' => 0.0,
            'purchase_net' => 0.0,
            'direct_expense_net' => 0.0,
            'indirect_expense_net' => 0.0,
            'direct_income_net' => 0.0,
            'indirect_income_net' => 0.0,
            'ok' => false,
        ];
    }
    mysqli_free_result($tchk);

    [$customer_sundry_map, $customer_sundry_lower] = fs_load_customer_sundry_maps($conn);
    $br_sql = fs_customer_ledger_branch_and_sql($conn);

    $acc = [
        'sales' => ['td' => 0.0, 'tc' => 0.0],
        'purchase' => ['td' => 0.0, 'tc' => 0.0],
        'direct_expense' => ['td' => 0.0, 'tc' => 0.0],
        'indirect_expense' => ['td' => 0.0, 'tc' => 0.0],
        'direct_income' => ['td' => 0.0, 'tc' => 0.0],
        'indirect_income' => ['td' => 0.0, 'tc' => 0.0],
    ];

    $names_list = getList(
        "SELECT DISTINCT customer_name FROM tbl_customer_ledger WHERE status = 1" . $tb_hidden_sql . $br_sql . " ORDER BY customer_name ASC"
    );
    foreach ($names_list as $nr) {
        $customer_name = isset($nr['customer_name']) ? $nr['customer_name'] : '';
        if ($customer_name === '') {
            continue;
        }
        $cust_esc = mysqli_real_escape_string($conn, $customer_name);

        $opening_amt = 0.0;
        if ($from_date !== '') {
            $opening_balance = getRecord(
                "SELECT balance_amount FROM tbl_customer_ledger
                WHERE customer_name = '$cust_esc' AND status = 1" . $br_sql . " AND transaction_date < '$from_date'
                ORDER BY transaction_date DESC, id DESC LIMIT 1"
            );
            if ($opening_balance) {
                $opening_amt = (float) ($opening_balance['balance_amount'] ?? 0);
            }
        } else {
            $opening_row = getRecord(
                "SELECT balance_amount, debit_amount, credit_amount FROM tbl_customer_ledger
                WHERE customer_name = '$cust_esc' AND status = 1" . $br_sql . " AND transaction_type = 'opening'
                ORDER BY transaction_date DESC, id DESC LIMIT 1"
            );
            if ($opening_row) {
                $ob = (float) ($opening_row['balance_amount'] ?? 0);
                if ($ob != 0.0) {
                    $opening_amt = $ob;
                } else {
                    $dr = (float) ($opening_row['debit_amount'] ?? 0);
                    $cr = (float) ($opening_row['credit_amount'] ?? 0);
                    $opening_amt = $dr > 0 ? $dr : -$cr;
                }
            }
        }

        $period_where = "customer_name = '$cust_esc' AND status = 1 AND COALESCE(transaction_type,'') != 'opening'";
        if ($from_date !== '') {
            $period_where .= " AND transaction_date >= '$from_date'";
        }
        if ($to_date !== '') {
            $period_where .= " AND transaction_date <= '$to_date'";
        }
        $psum = getRecord(
            "SELECT COALESCE(SUM(debit_amount),0) AS td, COALESCE(SUM(credit_amount),0) AS tc
            FROM tbl_customer_ledger WHERE $period_where" . $br_sql
        );
        $td = $psum ? (float) ($psum['td'] ?? 0) : 0.0;
        $tc = $psum ? (float) ($psum['tc'] ?? 0) : 0.0;

        $closing_amt = $opening_amt + $td - $tc;
        if (abs($opening_amt) < 0.0000001 && $td < 0.0000001 && $tc < 0.0000001 && abs($closing_amt) < 0.0000001) {
            continue;
        }

        $bucket = pl_map_ledger_for_pnl($customer_name, $customer_sundry_map, $customer_sundry_lower);
        if ($bucket === 'exclude' || !isset($acc[$bucket])) {
            continue;
        }
        $acc[$bucket]['td'] += $td;
        $acc[$bucket]['tc'] += $tc;
    }

    $sales_net = $acc['sales']['tc'] - $acc['sales']['td'];
    $purchase_net = $acc['purchase']['td'] - $acc['purchase']['tc'];
    $direct_expense_net = $acc['direct_expense']['td'] - $acc['direct_expense']['tc'];
    $indirect_expense_net = $acc['indirect_expense']['td'] - $acc['indirect_expense']['tc'];
    $direct_income_net = $acc['direct_income']['tc'] - $acc['direct_income']['td'];
    $indirect_income_net = $acc['indirect_income']['tc'] - $acc['indirect_income']['td'];

    return [
        'sales_net' => $sales_net,
        'purchase_net' => $purchase_net,
        'direct_expense_net' => $direct_expense_net,
        'indirect_expense_net' => $indirect_expense_net,
        'direct_income_net' => $direct_income_net,
        'indirect_income_net' => $indirect_income_net,
        'ok' => true,
    ];
}

/**
 * Trial-balance style: absolute value + Dr|Cr (positive = Dr, negative = Cr).
 */
function fs_balance_fmt_signed($signed) {
    $signed = (float) $signed;
    if (abs($signed) < 0.0000001) {
        return '0.00';
    }
    $suf = $signed >= 0 ? 'Dr' : 'Cr';
    return number_format(abs($signed), 2, '.', '') . $suf;
}

/**
 * Ledgers mapped to one of the given TB groups with opening / period / closing (same rules as fs_compute_ledger_groups).
 *
 * @param array<int,string> $group_names
 * @return array<int, array{name: string, opening: float, debit: float, credit: float, closing: float}>
 */
function fs_list_ledgers_for_tb_groups($conn, array $group_names, $from_date, $to_date, $tb_hidden_sql) {
    $group_set = [];
    foreach ($group_names as $gn) {
        $gn = trim((string) $gn);
        if ($gn !== '') {
            $group_set[$gn] = true;
        }
    }
    if (empty($group_set)) {
        return [];
    }

    [$customer_sundry_map, $customer_sundry_lower] = fs_load_customer_sundry_maps($conn);
    $br_sql = fs_customer_ledger_branch_and_sql($conn);

    $rows = [];
    $names_list = getList(
        "SELECT DISTINCT customer_name FROM tbl_customer_ledger WHERE status = 1" . $tb_hidden_sql . $br_sql . " ORDER BY customer_name ASC"
    );
    foreach ($names_list as $nr) {
        $customer_name = isset($nr['customer_name']) ? $nr['customer_name'] : '';
        if ($customer_name === '') {
            continue;
        }
        $cust_esc = mysqli_real_escape_string($conn, $customer_name);

        $opening_amt = 0.0;
        if ($from_date !== '') {
            $opening_balance = getRecord(
                "SELECT balance_amount FROM tbl_customer_ledger
                WHERE customer_name = '$cust_esc' AND status = 1" . $br_sql . " AND transaction_date < '$from_date'
                ORDER BY transaction_date DESC, id DESC LIMIT 1"
            );
            if ($opening_balance) {
                $opening_amt = (float) ($opening_balance['balance_amount'] ?? 0);
            }
        } else {
            $opening_row = getRecord(
                "SELECT balance_amount, debit_amount, credit_amount FROM tbl_customer_ledger
                WHERE customer_name = '$cust_esc' AND status = 1" . $br_sql . " AND transaction_type = 'opening'
                ORDER BY transaction_date DESC, id DESC LIMIT 1"
            );
            if ($opening_row) {
                $ob = (float) ($opening_row['balance_amount'] ?? 0);
                if ($ob != 0.0) {
                    $opening_amt = $ob;
                } else {
                    $dr = (float) ($opening_row['debit_amount'] ?? 0);
                    $cr = (float) ($opening_row['credit_amount'] ?? 0);
                    $opening_amt = $dr > 0 ? $dr : -$cr;
                }
            }
        }

        $period_where = "customer_name = '$cust_esc' AND status = 1 AND COALESCE(transaction_type,'') != 'opening'";
        if ($from_date !== '') {
            $period_where .= " AND transaction_date >= '$from_date'";
        }
        if ($to_date !== '') {
            $period_where .= " AND transaction_date <= '$to_date'";
        }
        $psum = getRecord(
            "SELECT COALESCE(SUM(debit_amount),0) AS td, COALESCE(SUM(credit_amount),0) AS tc
            FROM tbl_customer_ledger WHERE $period_where" . $br_sql
        );
        $td = $psum ? (float) ($psum['td'] ?? 0) : 0.0;
        $tc = $psum ? (float) ($psum['tc'] ?? 0) : 0.0;

        $closing_amt = $opening_amt + $td - $tc;
        if (abs($opening_amt) < 0.0000001 && $td < 0.0000001 && $tc < 0.0000001 && abs($closing_amt) < 0.0000001) {
            continue;
        }

        $g = fs_map_ledger_to_group($customer_name, $customer_sundry_map, $customer_sundry_lower);
        if (!isset($group_set[$g])) {
            continue;
        }

        $rows[] = [
            'name' => $customer_name,
            'opening' => $opening_amt,
            'debit' => $td,
            'credit' => $tc,
            'closing' => $closing_amt,
        ];
    }

    usort($rows, static function ($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });

    return $rows;
}

/**
 * Ledgers that belong to a P&L trading bucket (same rules as fs_compute_pnl_buckets / pl_map_ledger_for_pnl).
 *
 * @param string $bucket One of: sales, purchase, direct_expense, indirect_expense, direct_income, indirect_income
 * @return array<int, array{name: string, opening: float, debit: float, credit: float, closing: float}>
 */
function fs_list_ledgers_for_pnl_bucket($conn, $bucket, $from_date, $to_date, $tb_hidden_sql) {
    $allowed = [
        'sales' => true,
        'purchase' => true,
        'direct_expense' => true,
        'indirect_expense' => true,
        'direct_income' => true,
        'indirect_income' => true,
    ];
    if (!isset($allowed[$bucket])) {
        return [];
    }

    $tchk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_customer_ledger'");
    if (!$tchk || mysqli_num_rows($tchk) === 0) {
        if ($tchk) {
            mysqli_free_result($tchk);
        }
        return [];
    }
    mysqli_free_result($tchk);

    [$customer_sundry_map, $customer_sundry_lower] = fs_load_customer_sundry_maps($conn);
    $br_sql = fs_customer_ledger_branch_and_sql($conn);

    $rows = [];
    $names_list = getList(
        "SELECT DISTINCT customer_name FROM tbl_customer_ledger WHERE status = 1" . $tb_hidden_sql . $br_sql . " ORDER BY customer_name ASC"
    );
    foreach ($names_list as $nr) {
        $customer_name = isset($nr['customer_name']) ? $nr['customer_name'] : '';
        if ($customer_name === '') {
            continue;
        }
        $b = pl_map_ledger_for_pnl($customer_name, $customer_sundry_map, $customer_sundry_lower);
        if ($b !== $bucket) {
            continue;
        }

        $cust_esc = mysqli_real_escape_string($conn, $customer_name);

        $opening_amt = 0.0;
        if ($from_date !== '') {
            $opening_balance = getRecord(
                "SELECT balance_amount FROM tbl_customer_ledger
                WHERE customer_name = '$cust_esc' AND status = 1" . $br_sql . " AND transaction_date < '$from_date'
                ORDER BY transaction_date DESC, id DESC LIMIT 1"
            );
            if ($opening_balance) {
                $opening_amt = (float) ($opening_balance['balance_amount'] ?? 0);
            }
        } else {
            $opening_row = getRecord(
                "SELECT balance_amount, debit_amount, credit_amount FROM tbl_customer_ledger
                WHERE customer_name = '$cust_esc' AND status = 1" . $br_sql . " AND transaction_type = 'opening'
                ORDER BY transaction_date DESC, id DESC LIMIT 1"
            );
            if ($opening_row) {
                $ob = (float) ($opening_row['balance_amount'] ?? 0);
                if ($ob != 0.0) {
                    $opening_amt = $ob;
                } else {
                    $dr = (float) ($opening_row['debit_amount'] ?? 0);
                    $cr = (float) ($opening_row['credit_amount'] ?? 0);
                    $opening_amt = $dr > 0 ? $dr : -$cr;
                }
            }
        }

        $period_where = "customer_name = '$cust_esc' AND status = 1 AND COALESCE(transaction_type,'') != 'opening'";
        if ($from_date !== '') {
            $period_where .= " AND transaction_date >= '$from_date'";
        }
        if ($to_date !== '') {
            $period_where .= " AND transaction_date <= '$to_date'";
        }
        $psum = getRecord(
            "SELECT COALESCE(SUM(debit_amount),0) AS td, COALESCE(SUM(credit_amount),0) AS tc
            FROM tbl_customer_ledger WHERE $period_where" . $br_sql
        );
        $td = $psum ? (float) ($psum['td'] ?? 0) : 0.0;
        $tc = $psum ? (float) ($psum['tc'] ?? 0) : 0.0;

        $closing_amt = $opening_amt + $td - $tc;
        if (abs($opening_amt) < 0.0000001 && $td < 0.0000001 && $tc < 0.0000001 && abs($closing_amt) < 0.0000001) {
            continue;
        }

        $rows[] = [
            'name' => $customer_name,
            'opening' => $opening_amt,
            'debit' => $td,
            'credit' => $tc,
            'closing' => $closing_amt,
        ];
    }

    usort($rows, static function ($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });

    return $rows;
}

/**
 * Map a ledger name to a Cash Flow row bucket (same rules as admin/cash-flow.php).
 *
 * @param array<string,int> $customer_sundry_map
 * @param array<string,int> $customer_sundry_lower
 */
function fs_map_ledger_to_cash_flow_bucket($ledger_name, array $customer_sundry_map, array $customer_sundry_lower) {
    $n = trim((string) $ledger_name);
    if ($n === '') {
        return 'Current Assets';
    }
    $lower = strtolower($n);

    if (stripos($lower, 'suspense') !== false) {
        return 'Suspense A/C';
    }
    if (preg_match('/\bbranch\b/i', $n) || preg_match('/\bdivision\b/i', $n)) {
        return 'Branch / Divisions';
    }
    if ((stripos($lower, 'capital') !== false || stripos($lower, 'partner') !== false)
        && stripos($lower, 'profit and loss') === false) {
        return 'Capital Account';
    }
    if (stripos($lower, 'loan') !== false || stripos($lower, 'borrowing') !== false || stripos($lower, 'od ') !== false) {
        return 'Loans (Liability)';
    }
    if (stripos($lower, 'fixed') !== false && stripos($lower, 'asset') !== false) {
        return 'Fixed Assets';
    }
    if (stripos($lower, 'invest') !== false) {
        return 'Investments';
    }
    if ((stripos($lower, 'misc') !== false || stripos($lower, 'preliminary') !== false)
        && (stripos($lower, 'expense') !== false || stripos($lower, 'asset') !== false)) {
        return 'Misc. Expenses (ASSET)';
    }
    if (stripos($lower, 'direct') !== false && stripos($lower, 'expense') !== false) {
        return 'Direct Expenses';
    }
    if (stripos($lower, 'indirect') !== false && stripos($lower, 'expense') !== false) {
        return 'Indirect Expenses';
    }
    if (stripos($lower, 'direct') !== false && stripos($lower, 'income') !== false) {
        return 'Direct Income';
    }
    if (stripos($lower, 'indirect') !== false && stripos($lower, 'income') !== false) {
        return 'Indirect Income';
    }

    $g = fs_map_ledger_to_group($ledger_name, $customer_sundry_map, $customer_sundry_lower);
    switch ($g) {
        case 'Current Liabilities':
            return 'Current Liabilities';
        case 'Current Assets':
            return 'Current Assets';
        case 'Sales Account':
            return 'Sales Account';
        case 'Purchase Account':
            return 'Purchase Account';
        case 'Indirect Expenses':
            return 'Indirect Expenses';
        case 'Profit and Loss':
        case 'Profit and Loss Opening':
            return 'Capital Account';
        default:
            return 'Current Assets';
    }
}

/**
 * Ledgers whose cash-flow bucket matches $bucket_name (opening / period / closing same as TB helpers).
 *
 * @return array<int, array{name: string, opening: float, debit: float, credit: float, closing: float}>
 */
function fs_list_ledgers_for_cash_flow_bucket($conn, $bucket_name, $from_date, $to_date, $tb_hidden_sql) {
    $bucket_name = trim((string) $bucket_name);
    if ($bucket_name === '') {
        return [];
    }

    [$customer_sundry_map, $customer_sundry_lower] = fs_load_customer_sundry_maps($conn);
    $br_sql = fs_customer_ledger_branch_and_sql($conn);

    $rows = [];
    $names_list = getList(
        "SELECT DISTINCT customer_name FROM tbl_customer_ledger WHERE status = 1" . $tb_hidden_sql . $br_sql . " ORDER BY customer_name ASC"
    );
    foreach ($names_list as $nr) {
        $customer_name = isset($nr['customer_name']) ? $nr['customer_name'] : '';
        if ($customer_name === '') {
            continue;
        }
        $cust_esc = mysqli_real_escape_string($conn, $customer_name);

        $opening_amt = 0.0;
        if ($from_date !== '') {
            $opening_balance = getRecord(
                "SELECT balance_amount FROM tbl_customer_ledger
                WHERE customer_name = '$cust_esc' AND status = 1" . $br_sql . " AND transaction_date < '$from_date'
                ORDER BY transaction_date DESC, id DESC LIMIT 1"
            );
            if ($opening_balance) {
                $opening_amt = (float) ($opening_balance['balance_amount'] ?? 0);
            }
        } else {
            $opening_row = getRecord(
                "SELECT balance_amount, debit_amount, credit_amount FROM tbl_customer_ledger
                WHERE customer_name = '$cust_esc' AND status = 1" . $br_sql . " AND transaction_type = 'opening'
                ORDER BY transaction_date DESC, id DESC LIMIT 1"
            );
            if ($opening_row) {
                $ob = (float) ($opening_row['balance_amount'] ?? 0);
                if ($ob != 0.0) {
                    $opening_amt = $ob;
                } else {
                    $dr = (float) ($opening_row['debit_amount'] ?? 0);
                    $cr = (float) ($opening_row['credit_amount'] ?? 0);
                    $opening_amt = $dr > 0 ? $dr : -$cr;
                }
            }
        }

        $period_where = "customer_name = '$cust_esc' AND status = 1 AND COALESCE(transaction_type,'') != 'opening'";
        if ($from_date !== '') {
            $period_where .= " AND transaction_date >= '$from_date'";
        }
        if ($to_date !== '') {
            $period_where .= " AND transaction_date <= '$to_date'";
        }
        $psum = getRecord(
            "SELECT COALESCE(SUM(debit_amount),0) AS td, COALESCE(SUM(credit_amount),0) AS tc
            FROM tbl_customer_ledger WHERE $period_where" . $br_sql
        );
        $td = $psum ? (float) ($psum['td'] ?? 0) : 0.0;
        $tc = $psum ? (float) ($psum['tc'] ?? 0) : 0.0;

        $closing_amt = $opening_amt + $td - $tc;
        if (abs($opening_amt) < 0.0000001 && $td < 0.0000001 && $tc < 0.0000001 && abs($closing_amt) < 0.0000001) {
            continue;
        }

        $b = fs_map_ledger_to_cash_flow_bucket($customer_name, $customer_sundry_map, $customer_sundry_lower);
        if ($b !== $bucket_name) {
            continue;
        }

        $rows[] = [
            'name' => $customer_name,
            'opening' => $opening_amt,
            'debit' => $td,
            'credit' => $tc,
            'closing' => $closing_amt,
        ];
    }

    usort($rows, static function ($a, $b) {
        return strcasecmp($a['name'], $b['name']);
    });

    return $rows;
}
