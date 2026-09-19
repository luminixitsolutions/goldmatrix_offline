<?php

/**
 * Full ledger balance list + grand totals for grid + Excel (no pagination).
 *
 * @return array{rows: list<array{id:int,ledger_name:string,ledger_type:string,balance_amount:float,balance:float}>, totals: array{total_balance_amount:float,total_balance:float}}
 */
function auragold_ledger_balance_report_collect(mysqli $conn, array $opt): array
{
    $search = isset($opt['search']) ? esc((string) $opt['search']) : '';
    $from_date = isset($opt['from_date']) ? trim((string) esc((string) $opt['from_date'])) : '';
    $to_date = isset($opt['to_date']) ? trim((string) esc((string) $opt['to_date'])) : '';
    $customers_only = !empty($opt['customers_only']);

    $ledger_has_branch = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'branch_id');
    $scope_branch_id = isset($opt['branch_id']) ? (int) $opt['branch_id'] : 0;
    if (function_exists('auragold_resolve_branch_id_for_session')) {
        $scope_branch_id = (int) auragold_resolve_branch_id_for_session($scope_branch_id);
    }
    $main_bid = function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0;
    if ($ledger_has_branch && $scope_branch_id <= 0 && function_exists('auragold_effective_branch_id')) {
        $scope_branch_id = (int) auragold_effective_branch_id();
    }
    if ($ledger_has_branch && $scope_branch_id <= 0 && $main_bid > 0) {
        $scope_branch_id = $main_bid;
    }

    $brLedgerAnd = '';
    if ($ledger_has_branch && $scope_branch_id > 0) {
        if ($main_bid > 0 && $scope_branch_id === $main_bid) {
            $brLedgerAnd = ' AND (branch_id = ' . (int) $scope_branch_id . ' OR branch_id IS NULL OR branch_id = 0)';
        } else {
            $brLedgerAnd = ' AND COALESCE(branch_id, 0) = ' . (int) $scope_branch_id;
        }
    }

    $si_has_branch = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_sale_invoices', 'branch_id');
    $brSiAnd = '';
    if ($si_has_branch && $scope_branch_id > 0) {
        if ($main_bid > 0 && $scope_branch_id === $main_bid) {
            $brSiAnd = ' AND (branch_id = ' . (int) $scope_branch_id . ' OR branch_id IS NULL OR branch_id = 0)';
        } else {
            $brSiAnd = ' AND COALESCE(branch_id, 0) = ' . (int) $scope_branch_id;
        }
    }

    $pi_has_branch = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_purchase_invoices', 'branch_id');
    $brPiAnd = '';
    if ($pi_has_branch && $scope_branch_id > 0) {
        if ($main_bid > 0 && $scope_branch_id === $main_bid) {
            $brPiAnd = ' AND (branch_id = ' . (int) $scope_branch_id . ' OR branch_id IS NULL OR branch_id = 0)';
        } else {
            $brPiAnd = ' AND COALESCE(branch_id, 0) = ' . (int) $scope_branch_id;
        }
    }

    $searchSql = '';
    if ($search !== '') {
        $searchSql = " AND l.customer_name LIKE '%$search%'";
    }

    $ledger_query = "
        SELECT
            MAX(l.customer_id) AS customer_id,
            l.customer_name AS ledger_name,
            MAX(l.id) AS latest_ledger_id
        FROM tbl_customer_ledger l
        WHERE l.status = 1
        AND l.customer_name IS NOT NULL
        AND TRIM(l.customer_name) != ''
        $brLedgerAnd
        $searchSql
        GROUP BY l.customer_name
        ORDER BY l.customer_name ASC
    ";

    $all_ledgers = getList($ledger_query);
    $ledger_data = [];

    foreach ($all_ledgers as $ledger_info) {
        $customer_id = (int) ($ledger_info['customer_id'] ?? 0);
        $ledger_name = $ledger_info['ledger_name'] ?? '';
        $ledger_name_esc = mysqli_real_escape_string($conn, (string) $ledger_name);

        $latest_ledger_id = (int) ($ledger_info['latest_ledger_id'] ?? 0);

        $latest_balance = getRecord("
            SELECT balance_amount, balance_gold, balance_silver
            FROM tbl_customer_ledger
            WHERE id = $latest_ledger_id
            LIMIT 1
        ");

        $has_dates = ($from_date !== '' || $to_date !== '');
        $balance_amount = 0.0;
        $balance_gold = 0.0;
        $balance_silver = 0.0;

        if ($has_dates) {
            $opening_where_parts = ["customer_name = '$ledger_name_esc'", 'status = 1'];
            if ($ledger_has_branch && $scope_branch_id > 0) {
                if ($main_bid > 0 && $scope_branch_id === $main_bid) {
                    $opening_where_parts[] = '(branch_id = ' . (int) $scope_branch_id . ' OR branch_id IS NULL OR branch_id = 0)';
                } else {
                    $opening_where_parts[] = 'COALESCE(branch_id, 0) = ' . (int) $scope_branch_id;
                }
            }
            if ($from_date !== '') {
                $opening_where_parts[] = "transaction_date < '" . mysqli_real_escape_string($conn, $from_date) . "'";
            }
            $opening_where_sql = implode(' AND ', $opening_where_parts);

            $opening_balance = getRecord("
                SELECT balance_amount, balance_gold, balance_silver
                FROM tbl_customer_ledger
                WHERE $opening_where_sql
                ORDER BY transaction_date DESC, id DESC
                LIMIT 1
            ");

            $opening_amt = $opening_balance ? (float) $opening_balance['balance_amount'] : 0.0;
            $opening_gold = $opening_balance ? (float) $opening_balance['balance_gold'] : 0.0;
            $opening_silver = $opening_balance ? (float) $opening_balance['balance_silver'] : 0.0;

            $range_where_parts = ["customer_name = '$ledger_name_esc'", 'status = 1'];
            if ($ledger_has_branch && $scope_branch_id > 0) {
                if ($main_bid > 0 && $scope_branch_id === $main_bid) {
                    $range_where_parts[] = '(branch_id = ' . (int) $scope_branch_id . ' OR branch_id IS NULL OR branch_id = 0)';
                } else {
                    $range_where_parts[] = 'COALESCE(branch_id, 0) = ' . (int) $scope_branch_id;
                }
            }
            if ($from_date !== '' && $to_date !== '') {
                $range_where_parts[] = "transaction_date BETWEEN '" . mysqli_real_escape_string($conn, $from_date) . "' AND '" . mysqli_real_escape_string($conn, $to_date) . "'";
            } elseif ($from_date !== '') {
                $range_where_parts[] = "transaction_date >= '" . mysqli_real_escape_string($conn, $from_date) . "'";
            } elseif ($to_date !== '') {
                $range_where_parts[] = "transaction_date <= '" . mysqli_real_escape_string($conn, $to_date) . "'";
            }
            $range_where_sql = implode(' AND ', $range_where_parts);

            $range_transactions = getRecord("
                SELECT
                    COALESCE(SUM(debit_amount), 0) AS total_debit,
                    COALESCE(SUM(credit_amount), 0) AS total_credit,
                    COALESCE(SUM(debit_gold), 0) AS total_debit_gold,
                    COALESCE(SUM(credit_gold), 0) AS total_credit_gold,
                    COALESCE(SUM(debit_silver), 0) AS total_debit_silver,
                    COALESCE(SUM(credit_silver), 0) AS total_credit_silver
                FROM tbl_customer_ledger
                WHERE $range_where_sql
            ");

            $balance_amount = $opening_amt + (float) $range_transactions['total_debit'] - (float) $range_transactions['total_credit'];
            $balance_gold = $opening_gold + (float) $range_transactions['total_debit_gold'] - (float) $range_transactions['total_credit_gold'];
            $balance_silver = $opening_silver + (float) $range_transactions['total_debit_silver'] - (float) $range_transactions['total_credit_silver'];
        } else {
            $balance_amount = $latest_balance ? (float) $latest_balance['balance_amount'] : 0.0;
            $balance_gold = $latest_balance ? (float) $latest_balance['balance_gold'] : 0.0;
            $balance_silver = $latest_balance ? (float) $latest_balance['balance_silver'] : 0.0;
        }

        $is_customer = getRecord("
            SELECT COUNT(*) AS cnt 
            FROM tbl_sale_invoices 
            WHERE customer_name = '$ledger_name_esc' AND status != 'cancelled'
            $brSiAnd
            LIMIT 1
        ");

        $is_supplier = getRecord("
            SELECT COUNT(*) AS cnt 
            FROM tbl_purchase_invoices 
            WHERE supplier_name = '$ledger_name_esc' AND status != 'cancelled'
            $brPiAnd
            LIMIT 1
        ");

        $is_job_worker = getRecord("
            SELECT COUNT(*) AS cnt 
            FROM tbl_customer_ledger 
            WHERE customer_name = '$ledger_name_esc' AND status = 1
            AND (transaction_type LIKE '%job%' OR transaction_type LIKE '%worker%')
            $brLedgerAnd
            LIMIT 1
        ");

        $ledger_type = 'Account';
        if ($is_customer && (int) $is_customer['cnt'] > 0) {
            $ledger_type = 'Customer';
        } elseif ($is_supplier && (int) $is_supplier['cnt'] > 0) {
            $ledger_type = 'Supplier';
        } elseif ($is_job_worker && (int) $is_job_worker['cnt'] > 0) {
            $ledger_type = 'Job Worker';
        }

        if ($customers_only && $ledger_type !== 'Customer') {
            continue;
        }

        $ledger_data[] = [
            'id' => $customer_id > 0 ? $customer_id : 0,
            'ledger_name' => $ledger_name,
            'ledger_type' => $ledger_type,
            'balance_amount' => $balance_amount,
            'balance' => $balance_gold + $balance_silver,
        ];
    }

    $total_balance_amount = 0.0;
    $total_balance = 0.0;
    foreach ($ledger_data as $ledger) {
        $total_balance_amount += (float) $ledger['balance_amount'];
        $total_balance += (float) $ledger['balance'];
    }

    return [
        'rows' => $ledger_data,
        'totals' => [
            'total_balance_amount' => $total_balance_amount,
            'total_balance' => $total_balance,
        ],
    ];
}
