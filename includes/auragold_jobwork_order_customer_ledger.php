<?php

/**
 * Post Job Work Order metal exchange + allocated diamond weights to tbl_customer_ledger (party line).
 * Mirrors payment voucher convention: customer metal/diamond received → credit gold/silver/diamond; running balances updated.
 */

if (!function_exists('auragold_jobwork_order_load_tagged_metal_exchange_lines')) {
    /**
     * Rows saved on tbl_sale_order_payments for this JWO (payment_details JSON tags jobwork_order_id).
     *
     * @return array<int, array<string, mixed>>
     */
    function auragold_jobwork_order_load_tagged_metal_exchange_lines(mysqli $conn, int $sale_order_id, int $jwo_id): array
    {
        $out = [];
        if ($sale_order_id < 1 || $jwo_id < 1) {
            return $out;
        }
        $chk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_order_payments LIKE 'payment_details'");
        $has_pd = ($chk && mysqli_num_rows($chk) > 0);
        if ($chk) {
            mysqli_free_result($chk);
        }
        if (!$has_pd || !function_exists('getList')) {
            return $out;
        }
        $rows = getList(
            'SELECT payment_type, deposit_into, transaction_no, cheque_date, purity_carat, amount, diamond_category, quantity, payment_details '
            . 'FROM tbl_sale_order_payments WHERE order_id = ' . (int) $sale_order_id . ' ORDER BY id ASC'
        );
        if (!is_array($rows)) {
            return $out;
        }
        foreach ($rows as $rw) {
            if (!is_array($rw)) {
                continue;
            }
            $jd = isset($rw['payment_details']) ? json_decode((string) $rw['payment_details'], true) : null;
            if (!is_array($jd) || empty($jd['jobwork_order_metal_exchange']) || (int) ($jd['jobwork_order_id'] ?? 0) !== $jwo_id) {
                continue;
            }
            unset($rw['payment_details']);
            $out[] = array_merge($rw, $jd);
        }

        return $out;
    }
}

if (!function_exists('auragold_jobwork_order_sync_customer_ledger')) {
    /**
     * Replace ledger rows for this JWO from current ME payment tags + voucher diamond issues.
     */
    function auragold_jobwork_order_sync_customer_ledger(mysqli $conn, int $sale_order_id, int $jwo_id): void
    {
        if (!$conn instanceof mysqli || $sale_order_id < 1 || $jwo_id < 1 || !function_exists('getRecord')) {
            return;
        }

        require_once __DIR__ . '/ensure_customer_ledger_branch_column.php';
        require_once __DIR__ . '/ensure_metal_amount_conversion.php';
        require_once __DIR__ . '/auragold_metal_exchange_stock.php';
        require_once __DIR__ . '/auragold_voucher_diamond_stock.php';

        auragold_ensure_customer_ledger_branch_column($conn);
        auragold_ensure_ledger_diamond_columns($conn);

        $so = getRecord('SELECT customer_name FROM tbl_sale_orders WHERE id = ' . (int) $sale_order_id . ' LIMIT 1');
        $sale_order_customer_plain = ($so && trim((string) ($so['customer_name'] ?? '')) !== '')
            ? trim((string) $so['customer_name'])
            : '';

        $jwo = getRecord(
            'SELECT jobwork_no, order_date, department_user_id FROM tbl_jobwork_orders WHERE id = '
            . (int) $jwo_id . ' LIMIT 1'
        );
        if (!$jwo || !is_array($jwo)) {
            return;
        }
        $jobwork_no_plain = trim((string) ($jwo['jobwork_no'] ?? ''));
        if ($jobwork_no_plain === '') {
            $jobwork_no_plain = 'JWO-' . $jwo_id;
        }
        $jobwork_no_esc = mysqli_real_escape_string($conn, $jobwork_no_plain);

        $txn_date = substr(trim((string) ($jwo['order_date'] ?? '')), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $txn_date)) {
            $txn_date = date('Y-m-d');
        }
        $txn_date_esc = mysqli_real_escape_string($conn, $txn_date);

        /** Ledger party: job worker (Name field) when set; else sale-order customer (legacy). */
        $ledger_customer_id = 0;
        $ledger_customer_name_plain = '';
        $against_ledger_plain = '';
        $worker_id = (int) ($jwo['department_user_id'] ?? 0);
        if ($worker_id > 0) {
            $wr = getRecord('SELECT id, name FROM tbl_customers WHERE id = ' . $worker_id . ' AND status = 1 LIMIT 1');
            if ($wr && trim((string) ($wr['name'] ?? '')) !== '') {
                $ledger_customer_id = (int) $wr['id'];
                $ledger_customer_name_plain = trim((string) $wr['name']);
                if ($sale_order_customer_plain !== '' && strcasecmp($sale_order_customer_plain, $ledger_customer_name_plain) !== 0) {
                    $against_ledger_plain = $sale_order_customer_plain;
                }
            }
        }
        if ($ledger_customer_name_plain === '' && $sale_order_customer_plain !== '') {
            $ledger_customer_name_plain = $sale_order_customer_plain;
            $cr = getRecord(
                "SELECT id FROM tbl_customers WHERE TRIM(name) = '"
                . mysqli_real_escape_string($conn, $sale_order_customer_plain) . "' LIMIT 1"
            );
            if ($cr && !empty($cr['id'])) {
                $ledger_customer_id = (int) $cr['id'];
            }
        }
        if ($ledger_customer_name_plain === '') {
            return;
        }
        $customer_name_esc = mysqli_real_escape_string($conn, $ledger_customer_name_plain);
        $customer_id = $ledger_customer_id;

        mysqli_query(
            $conn,
            'DELETE FROM tbl_customer_ledger WHERE transaction_type = \'jobwork_order\' AND transaction_id = '
            . (int) $jwo_id . ' AND status = 1'
        );

        $me_lines = auragold_jobwork_order_load_tagged_metal_exchange_lines($conn, $sale_order_id, $jwo_id);

        $total_gold = 0.0;
        $total_silver = 0.0;
        $total_gold_pure = 0.0;
        foreach ($me_lines as $pay) {
            if (!is_array($pay)) {
                continue;
            }
            if (!auragold_payment_is_metal_exchange_inward($conn, $pay)) {
                continue;
            }
            $r = auragold_metal_exchange_resolve($conn, $pay);
            $wt_use = ($r['pure'] > 1e-8) ? $r['pure'] : $r['gross'];
            if ($r['is_silver']) {
                $total_silver += $wt_use;
            } else {
                $total_gold += $wt_use;
                $total_gold_pure += $wt_use;
            }
        }

        if ($worker_id > 0 && $sale_order_id > 0 && function_exists('getList')) {
            $mi_me_rows = getList(
                'SELECT s.opening_weight, s.final_weight, s.metal_id, m.system_name AS metal_system'
                . ' FROM tbl_stock s'
                . ' INNER JOIN tbl_material_issues mi ON mi.id = s.reference_id'
                . " WHERE s.status = 1 AND s.reference_type = 'material_issue_metal_exchange'"
                . ' AND mi.sale_order_id = ' . (int) $sale_order_id
                . ' AND mi.department_user_id = ' . (int) $worker_id
            );
            if (is_array($mi_me_rows)) {
                foreach ($mi_me_rows as $mrow) {
                    if (!is_array($mrow)) {
                        continue;
                    }
                    $gross = (float) ($mrow['opening_weight'] ?? 0);
                    $pure = (float) ($mrow['final_weight'] ?? 0);
                    if ($pure <= 1e-8) {
                        $pure = $gross;
                    }
                    $wt_use = ($pure > 1e-8) ? $pure : $gross;
                    if ($wt_use <= 1e-8) {
                        continue;
                    }
                    $msys = strtolower(trim((string) ($mrow['metal_system'] ?? '')));
                    $is_silver = ($msys !== '' && strpos($msys, 'silver') !== false);
                    if ($is_silver) {
                        $total_silver += $wt_use;
                    } else {
                        $total_gold += $wt_use;
                        $total_gold_pure += $wt_use;
                    }
                }
            }
        }

        auragold_voucher_ensure_diamond_issue_table($conn);
        $drow = getRecord(
            'SELECT COALESCE(SUM(weight), 0) AS w FROM tbl_voucher_diamond_stock_issue WHERE voucher_kind = \'jobwork_order\' AND voucher_id = '
            . (int) $jwo_id
        );
        $total_diamond = (float) ($drow['w'] ?? 0);

        if ($total_gold <= 1e-8 && $total_silver <= 1e-8 && $total_diamond <= 1e-8) {
            return;
        }

        $eff_branch = function_exists('auragold_effective_branch_id') ? (int) auragold_effective_branch_id() : 0;
        if ($eff_branch <= 0) {
            $eff_branch = auragold_metal_exchange_default_branch_id();
        }

        $ledger_has_branch_col = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'branch_id');
        $ledger_branch_sql_col = $ledger_has_branch_col ? ', branch_id' : '';
        $ledger_branch_sql_val = '';
        if ($ledger_has_branch_col) {
            $ledger_branch_sql_val = ($eff_branch > 0) ? ', ' . $eff_branch : ', NULL';
        }

        $ledger_br_scope = function_exists('auragold_customer_ledger_branch_scope_sql')
            ? auragold_customer_ledger_branch_scope_sql($conn, $eff_branch)
            : '';

        $has_gold_pure_cols = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'debit_gold_pure');
        $ledger_has_diamond_cols = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'debit_diamond');

        $gold_pure_select = $has_gold_pure_cols ? ', balance_gold_pure' : '';
        $diamond_balance_select = $ledger_has_diamond_cols ? ', balance_diamond' : '';

        $cid_sql = $customer_id > 0 ? (string) $customer_id : '0';
        $last_balance = null;
        if ($customer_id > 0) {
            $last_balance = getRecord(
                'SELECT balance_amount, balance_gold, balance_silver' . $gold_pure_select . $diamond_balance_select
                . ' FROM tbl_customer_ledger WHERE customer_id = ' . $customer_id . ' AND status = 1'
                . $ledger_br_scope
                . ' ORDER BY transaction_date DESC, id DESC LIMIT 1'
            );
        }
        if (!$last_balance && $customer_name_esc !== '') {
            $last_balance = getRecord(
                'SELECT balance_amount, balance_gold, balance_silver' . $gold_pure_select . $diamond_balance_select
                . " FROM tbl_customer_ledger WHERE customer_name = '$customer_name_esc' AND status = 1"
                . $ledger_br_scope
                . ' ORDER BY transaction_date DESC, id DESC LIMIT 1'
            );
        }
        if (!$last_balance || !is_array($last_balance)) {
            $last_balance = [];
        }

        $prev_amt = (float) ($last_balance['balance_amount'] ?? 0);
        $prev_gold = (float) ($last_balance['balance_gold'] ?? 0);
        $prev_silver = (float) ($last_balance['balance_silver'] ?? 0);
        $prev_gold_pure = $has_gold_pure_cols ? (float) ($last_balance['balance_gold_pure'] ?? 0) : 0.0;
        $prev_diamond = $ledger_has_diamond_cols ? (float) ($last_balance['balance_diamond'] ?? 0) : 0.0;

        $new_balance_amt = $prev_amt;
        $new_balance_gold = $prev_gold + $total_gold;
        $new_balance_silver = $prev_silver + $total_silver;
        $new_balance_gold_pure = $prev_gold_pure + $total_gold_pure;
        $new_balance_diamond = $prev_diamond + $total_diamond;

        $user_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : (isset($_SESSION['Admin']['id']) ? (int) $_SESSION['Admin']['id'] : null);

        $desc_plain = 'Job Work Order: ' . $jobwork_no_plain . ' (metal / diamond received)';
        if ($against_ledger_plain !== '') {
            $desc_plain .= ' — SO customer: ' . $against_ledger_plain;
        }
        $desc_esc = mysqli_real_escape_string($conn, $desc_plain);

        $has_against = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_customer_ledger', 'against_ledger');
        $against_cols = $has_against ? ', against_ledger, against_invoice_no' : '';
        $against_vals = '';
        if ($has_against) {
            if ($against_ledger_plain !== '') {
                $against_vals = ", '"
                    . mysqli_real_escape_string($conn, $against_ledger_plain) . "', '"
                    . mysqli_real_escape_string($conn, $sale_order_customer_plain !== '' ? $sale_order_customer_plain : $jobwork_no_plain)
                    . "'";
            } else {
                $against_vals = ', NULL, NULL';
            }
        }

        $gold_pure_cols = $has_gold_pure_cols ? ', debit_gold_pure, credit_gold_pure, balance_gold_pure' : '';
        $gold_pure_vals = $has_gold_pure_cols
            ? ', 0, ' . (float) $total_gold_pure . ', ' . (float) $new_balance_gold_pure
            : '';

        $diamond_cols = $ledger_has_diamond_cols ? ', debit_diamond, credit_diamond, balance_diamond' : '';
        $diamond_vals = $ledger_has_diamond_cols
            ? ', 0, ' . (float) $total_diamond . ', ' . (float) $new_balance_diamond
            : '';

        $ledger_sql = 'INSERT INTO tbl_customer_ledger (
                customer_id' . $ledger_branch_sql_col . ', customer_name, transaction_type, transaction_id, transaction_no,
                transaction_date, debit_amount, credit_amount,
                debit_gold, credit_gold, debit_silver, credit_silver,
                balance_amount, balance_gold, balance_silver'
                . $gold_pure_cols . $diamond_cols
                . $against_cols . '
                , description, reference_no, status, created_by, created_at
            ) VALUES (
                ' . $cid_sql . $ledger_branch_sql_val . ',
                \'' . $customer_name_esc . '\',
                \'jobwork_order\',
                ' . (int) $jwo_id . ',
                \'' . $jobwork_no_esc . '\',
                \'' . $txn_date_esc . '\',
                0,
                0,
                0,
                ' . (float) $total_gold . ',
                0,
                ' . (float) $total_silver . ',
                ' . (float) $new_balance_amt . ',
                ' . (float) $new_balance_gold . ',
                ' . (float) $new_balance_silver
                . $gold_pure_vals . $diamond_vals
                . $against_vals . ',
                \'' . $desc_esc . '\',
                NULL,
                1,
                ' . ($user_id ? (string) $user_id : 'NULL') . ',
                NOW()
            )';
        if (!mysqli_query($conn, $ledger_sql)) {
            error_log('auragold_jobwork_order_sync_customer_ledger: ' . mysqli_error($conn));
        }
    }
}
