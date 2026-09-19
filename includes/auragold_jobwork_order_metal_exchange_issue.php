<?php

/**
 * On Job Work Order save: issue sale-order metal exchange stock to Assign To (department worker)
 * via Material Issue so manufacturing material history shows the lines.
 */
require_once __DIR__ . '/auragold_metal_exchange_stock.php';
require_once __DIR__ . '/auragold_material_issue_rows_for_sale_order.php';

if (!function_exists('auragold_metal_exchange_deduct_source_stock')) {
    /** Reduce available weight on sale-order / prior ME stock after material issue. */
    function auragold_metal_exchange_deduct_source_stock(mysqli $conn, int $source_stock_id, float $deduct_gross): void
    {
        if ($source_stock_id < 1 || $deduct_gross <= 1e-8 || !function_exists('getRecord')) {
            return;
        }
        $src = getRecord(
            'SELECT id, current_weight, current_qty, opening_weight, opening_qty, value, rate '
            . 'FROM tbl_stock WHERE id = ' . (int) $source_stock_id . ' AND status = 1 LIMIT 1'
        );
        if (!$src || !is_array($src)) {
            return;
        }
        $prev_w = (float) ($src['current_weight'] ?? 0);
        if ($prev_w <= 1e-8) {
            $prev_w = (float) ($src['opening_weight'] ?? 0);
        }
        $prev_q = (float) ($src['current_qty'] ?? 0);
        if ($prev_q <= 1e-8) {
            $prev_q = (float) ($src['opening_qty'] ?? 0);
        }
        $new_w = max(0.0, round($prev_w - $deduct_gross, 4));
        $new_q = $prev_q;
        if ($prev_w > 1e-8 && $prev_q > 1e-8) {
            $new_q = max(0.0, round($prev_q * ($new_w / $prev_w), 4));
        }
        $rate = (float) ($src['rate'] ?? 0);
        $new_val = $new_w * $rate;
        mysqli_query(
            $conn,
            'UPDATE tbl_stock SET current_weight = ' . $new_w
            . ', current_qty = ' . $new_q
            . ', value = ' . $new_val
            . ' WHERE id = ' . (int) $source_stock_id
        );
    }
}

if (!function_exists('auragold_jobwork_order_load_sale_order_metal_exchange_payments')) {
    /**
     * Sale-order ME payment rows (exclude JWO-tagged lines).
     *
     * @return list<array<string, mixed>>
     */
    function auragold_jobwork_order_load_sale_order_metal_exchange_payments(mysqli $conn, int $sale_order_id): array
    {
        if ($sale_order_id < 1 || !function_exists('getList')) {
            return [];
        }
        $t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_order_payments'");
        if (!$t || mysqli_num_rows($t) < 1) {
            if ($t) {
                mysqli_free_result($t);
            }

            return [];
        }
        mysqli_free_result($t);
        $rows = getList('SELECT * FROM tbl_sale_order_payments WHERE order_id = ' . (int) $sale_order_id . ' ORDER BY id ASC');
        if (!is_array($rows)) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (function_exists('auragold_jobwork_payment_row_is_jwo_metal_exchange')
                && auragold_jobwork_payment_row_is_jwo_metal_exchange($row, 0)) {
                continue;
            }
            $p = auragold_payment_merge_stored_details($row);
            if (!auragold_payment_is_metal_exchange_inward($conn, $p)) {
                continue;
            }
            $out[] = $p;
        }

        return $out;
    }
}

if (!function_exists('auragold_jobwork_me_issue_weight_from_payment')) {
    /**
     * Sale-order ME payment weight, capped by remaining stock on sale_order_metal_exchange line.
     *
     * @return array{gross: float, pure: float}
     */
    function auragold_jobwork_me_issue_weight_from_payment(mysqli $conn, array $payment, float $max_avail_gross): array
    {
        $r = auragold_metal_exchange_resolve($conn, $payment);
        $pay_gross = (float) ($r['gross'] ?? 0);
        $pay_pure = (float) ($r['pure'] ?? 0);
        if ($pay_gross <= 1e-8) {
            return ['gross' => 0.0, 'pure' => 0.0];
        }
        $issue_gross = $pay_gross;
        $issue_pure = $pay_pure > 1e-8 ? $pay_pure : $pay_gross;
        if ($max_avail_gross > 1e-8 && $issue_gross > $max_avail_gross + 0.0001) {
            $issue_gross = $max_avail_gross;
            $issue_pure = ($pay_pure > 1e-8 && $pay_gross > 1e-8)
                ? ($pay_pure * ($issue_gross / $pay_gross))
                : $issue_gross;
        }

        return ['gross' => $issue_gross, 'pure' => $issue_pure];
    }
}

if (!function_exists('auragold_jobwork_me_payment_from_stock_and_payments')) {
    /**
     * Build issue payload from the sale-order metal exchange payment that matches this SO stock line.
     * Weight always comes from the payment row (e.g. 2 g on SO), not full tbl_stock balance (e.g. 7 g).
     *
     * @param array<string, mixed> $stock_row sale_order_metal_exchange tbl_stock row
     * @param list<array<string, mixed>> $so_payments
     * @param float $max_avail_gross remaining weight on source stock (cap only)
     * @return array<string, mixed>|null
     */
    function auragold_jobwork_me_payment_from_stock_and_payments(
        mysqli $conn,
        array $stock_row,
        array $so_payments,
        float $max_avail_gross
    ): ?array {
        $stock_id = (int) ($stock_row['id'] ?? 0);
        $bc = trim((string) ($stock_row['barcode'] ?? ''));
        $pcid = (int) ($stock_row['product_characteristic_id'] ?? 0);
        $mid = (int) ($stock_row['metal_id'] ?? 0);

        foreach ($so_payments as $pay) {
            if (!is_array($pay)) {
                continue;
            }
            $p = auragold_payment_merge_stored_details($pay);
            if (!auragold_payment_is_metal_exchange_inward($conn, $p)) {
                continue;
            }
            $pay_bc = trim((string) ($p['metal_exchange_item_code'] ?? $p['item_code'] ?? ''));
            $pay_pcid = (int) ($p['metal_exchange_characteristic_id'] ?? $p['metal_exchange_product_id'] ?? $p['product_id'] ?? 0);
            $pay_mid = (int) ($p['metal_exchange_metal_id'] ?? $p['metal_id'] ?? 0);
            $matched = false;
            if ($bc !== '' && $pay_bc !== '' && strcasecmp($bc, $pay_bc) === 0) {
                $matched = true;
            } elseif ($pay_pcid > 0 && $pay_pcid === $pcid && ($pay_mid <= 0 || $pay_mid === $mid)) {
                $matched = true;
            }
            if (!$matched) {
                continue;
            }
            $wt = auragold_jobwork_me_issue_weight_from_payment($conn, $p, $max_avail_gross);
            if ($wt['gross'] <= 1e-8) {
                continue;
            }
            $p['metal_exchange_source_stock_id'] = $stock_id;
            $p['metal_exchange_item_code'] = $bc !== '' ? $bc : $pay_bc;
            $p['metal_exchange_gross_wt'] = $wt['gross'];
            $p['metal_exchange_purity_wt'] = $wt['pure'];
            $p['quantity'] = 1;
            if (function_exists('auragold_metal_exchange_payment_strip_stored_weight_overrides')) {
                $p = auragold_metal_exchange_payment_strip_stored_weight_overrides($p);
            } else {
                unset($p['payment_details']);
            }

            return $p;
        }

        return null;
    }
}

if (!function_exists('auragold_jobwork_order_resolve_material_issue_for_auto_me')) {
    /**
     * Reuse latest material issue for this sale order, or create one for the assignee.
     */
    function auragold_jobwork_order_resolve_material_issue_for_auto_me(
        mysqli $conn,
        int $sale_order_id,
        ?int $department_id,
        ?int $department_user_id,
        string $priority,
        string $status
    ): int {
        if ($sale_order_id < 1 || !function_exists('getRecord')) {
            return 0;
        }
        $scope = function_exists('auragold_material_issue_scope_sql_for_alias')
            ? auragold_material_issue_scope_sql_for_alias($conn, 'tbl_material_issues', 'mi')
            : '';
        $mi = getRecord(
            'SELECT id FROM tbl_material_issues mi WHERE mi.sale_order_id = '
            . (int) $sale_order_id . $scope . ' ORDER BY mi.id DESC LIMIT 1'
        );
        if ($mi && (int) ($mi['id'] ?? 0) > 0) {
            $mi_id = (int) $mi['id'];
            $upd = 'UPDATE tbl_material_issues SET updated_at = NOW(), status = '
                . "'" . mysqli_real_escape_string($conn, $status !== '' ? $status : 'Processing') . "'";
            if ($department_id !== null && (int) $department_id > 0) {
                $upd .= ', department_id = ' . (int) $department_id;
            }
            if ($department_user_id !== null && (int) $department_user_id > 0) {
                $upd .= ', department_user_id = ' . (int) $department_user_id;
            }
            if ($priority !== '') {
                $upd .= ", priority = '" . mysqli_real_escape_string($conn, $priority) . "'";
            }
            $upd .= ' WHERE id = ' . $mi_id;
            mysqli_query($conn, $upd);

            return $mi_id;
        }

        $so = getRecord(
            'SELECT order_no, customer_name, order_date, due_date FROM tbl_sale_orders WHERE id = '
            . (int) $sale_order_id . ' LIMIT 1'
        );
        if (!$so || !is_array($so)) {
            return 0;
        }

        $cfg = function_exists('getMaterialIssueBillSeriesConfig')
            ? getMaterialIssueBillSeriesConfig($conn)
            : ['prefix' => 'MI-', 'suffix' => '', 'start_count' => 1, 'from_series_table' => false];
        $mi_no = function_exists('getNextMaterialIssueNo') ? getNextMaterialIssueNo($conn) : 'MI-1';
        $mi_no_esc = mysqli_real_escape_string($conn, $mi_no);
        $guard = 0;
        while ($guard < 5000) {
            $ex = getRecord("SELECT id FROM tbl_material_issues WHERE material_issue_no = '$mi_no_esc' LIMIT 1");
            if (!$ex) {
                break;
            }
            $mi_no = function_exists('bumpMaterialIssueNo') ? bumpMaterialIssueNo($conn, $mi_no, $cfg) : ($mi_no . '-1');
            $mi_no_esc = mysqli_real_escape_string($conn, $mi_no);
            $guard++;
        }

        $so_no = mysqli_real_escape_string($conn, trim((string) ($so['order_no'] ?? '')));
        $cust = mysqli_real_escape_string($conn, trim((string) ($so['customer_name'] ?? '')));
        $od = trim((string) ($so['order_date'] ?? ''));
        $od_sql = ($od !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', substr($od, 0, 10)))
            ? "'" . mysqli_real_escape_string($conn, substr($od, 0, 10)) . "'"
            : 'NULL';
        $dd = trim((string) ($so['due_date'] ?? ''));
        $dd_sql = ($dd !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', substr($dd, 0, 10)))
            ? "'" . mysqli_real_escape_string($conn, substr($dd, 0, 10)) . "'"
            : 'NULL';
        $st_esc = mysqli_real_escape_string($conn, $status !== '' ? $status : 'Processing');

        $has_branch = function_exists('auragold_ensure_table_branch_id_column')
            ? auragold_ensure_table_branch_id_column($conn, 'tbl_material_issues')
            : false;
        $branch_id = function_exists('auragold_transaction_header_branch_id')
            ? (int) auragold_transaction_header_branch_id()
            : 0;

        $ins = 'INSERT INTO tbl_material_issues (material_issue_no, sale_order_id, sale_order_no, customer_name, order_date, due_date, grand_total, status, created_at'
            . ($has_branch ? ', branch_id' : '')
            . ") VALUES ('$mi_no_esc', " . (int) $sale_order_id . ", '$so_no', '$cust', $od_sql, $dd_sql, 0, '$st_esc', NOW()"
            . ($has_branch ? ', ' . ($branch_id > 0 ? (int) $branch_id : 'NULL') : '')
            . ')';
        if (!mysqli_query($conn, $ins)) {
            throw new Exception('Could not create material issue for metal exchange: ' . mysqli_error($conn));
        }
        $mi_id = (int) mysqli_insert_id($conn);
        if ($department_id !== null && (int) $department_id > 0) {
            mysqli_query($conn, 'UPDATE tbl_material_issues SET department_id = ' . (int) $department_id . ' WHERE id = ' . $mi_id);
        }
        if ($department_user_id !== null && (int) $department_user_id > 0) {
            mysqli_query($conn, 'UPDATE tbl_material_issues SET department_user_id = ' . (int) $department_user_id . ' WHERE id = ' . $mi_id);
        }
        if ($priority !== '') {
            mysqli_query($conn, "UPDATE tbl_material_issues SET priority = '" . mysqli_real_escape_string($conn, $priority) . "' WHERE id = $mi_id");
        }

        return $mi_id;
    }
}

if (!function_exists('auragold_jobwork_order_me_already_issued_from_source')) {
    function auragold_jobwork_order_me_already_issued_from_source(mysqli $conn, int $source_stock_id): bool
    {
        if ($source_stock_id < 1 || !function_exists('getRecord')) {
            return false;
        }
        if (function_exists('auragold_ensure_stock_source_stock_id_column')) {
            auragold_ensure_stock_source_stock_id_column($conn);
        }
        if (function_exists('auragold_tbl_has_column') && !auragold_tbl_has_column($conn, 'tbl_stock', 'source_stock_id')) {
            return false;
        }
        $row = getRecord(
            "SELECT id FROM tbl_stock WHERE status = 1 AND reference_type = 'material_issue_metal_exchange'"
            . ' AND source_stock_id = ' . (int) $source_stock_id . ' LIMIT 1'
        );
        if ($row && !empty($row['id'])) {
            return true;
        }
        $src = getRecord(
            'SELECT barcode, reference_id FROM tbl_stock WHERE id = ' . (int) $source_stock_id
            . " AND reference_type = 'sale_order_metal_exchange' AND status = 1 LIMIT 1"
        );
        $bc = trim((string) ($src['barcode'] ?? ''));
        $soid = (int) ($src['reference_id'] ?? 0);
        if ($bc === '' || $soid < 1) {
            return false;
        }
        $bc_esc = mysqli_real_escape_string($conn, $bc);
        $row2 = getRecord(
            'SELECT s.id FROM tbl_stock s'
            . ' INNER JOIN tbl_material_issues mi ON mi.id = s.reference_id'
            . " WHERE s.status = 1 AND s.reference_type = 'material_issue_metal_exchange'"
            . " AND s.barcode = '$bc_esc' AND mi.sale_order_id = " . (int) $soid
            . ' LIMIT 1'
        );

        return ($row2 && !empty($row2['id']));
    }
}

if (!function_exists('auragold_jobwork_order_auto_issue_sale_order_metal_exchange')) {
    /**
     * Issue sale-order metal exchange to Assign To via Material Issue (shows in material history).
     *
     * @param array<int, array{barcode: string, product_name: string}> $metal_exchange_barcodes_out
     * @return int material_issue_id used (0 if nothing to issue)
     */
    function auragold_jobwork_order_auto_issue_sale_order_metal_exchange(
        mysqli $conn,
        int $sale_order_id,
        int $jwo_id,
        ?int $department_id,
        ?int $department_user_id,
        string $priority,
        string $status,
        array &$metal_exchange_barcodes_out
    ): int {
        if ($sale_order_id < 1 || $jwo_id < 1) {
            return 0;
        }
        if ($department_user_id === null || (int) $department_user_id < 1) {
            return 0;
        }

        $so_payments = auragold_jobwork_order_load_sale_order_metal_exchange_payments($conn, $sale_order_id);
        if ($so_payments === []) {
            return 0;
        }

        auragold_material_issue_sync_me_stock_on_sale_order($conn, $sale_order_id);
        if (!auragold_prepare_tbl_stock_reference_columns($conn)) {
            return 0;
        }

        $so_stocks = getList(
            'SELECT * FROM tbl_stock WHERE status = 1'
            . " AND reference_type = 'sale_order_metal_exchange'"
            . ' AND reference_id = ' . (int) $sale_order_id
            . ' ORDER BY id ASC'
        );
        if (!is_array($so_stocks) || $so_stocks === []) {
            return 0;
        }

        $mi_id = auragold_jobwork_order_resolve_material_issue_for_auto_me(
            $conn,
            $sale_order_id,
            $department_id,
            $department_user_id,
            $priority,
            $status
        );
        if ($mi_id < 1) {
            return 0;
        }

        $mi_hdr = getRecord(
            'SELECT material_issue_no, order_date FROM tbl_material_issues WHERE id = '
            . (int) $mi_id . ' LIMIT 1'
        );
        $mi_no = trim((string) ($mi_hdr['material_issue_no'] ?? ''));
        $mi_dt = substr(trim((string) ($mi_hdr['order_date'] ?? '')), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $mi_dt)) {
            $mi_dt = date('Y-m-d');
        }

        $has_ref = auragold_metal_exchange_document_init($conn, false, $mi_id, 'material_issue_metal_exchange');
        $seq_row = getRecord(
            "SELECT COUNT(*) AS c FROM tbl_stock WHERE status = 1"
            . " AND reference_type = 'material_issue_metal_exchange' AND reference_id = " . (int) $mi_id
        );
        $pay_seq = (int) ($seq_row['c'] ?? 0);
        $issued_any = false;

        foreach ($so_stocks as $stock_row) {
            if (!is_array($stock_row)) {
                continue;
            }
            $src_id = (int) ($stock_row['id'] ?? 0);
            if ($src_id < 1) {
                continue;
            }
            if (auragold_jobwork_order_me_already_issued_from_source($conn, $src_id)) {
                continue;
            }
            $avail = function_exists('auragold_metal_exchange_so_source_stock_available')
                ? auragold_metal_exchange_so_source_stock_available($conn, $src_id)
                : 0.0;
            if ($avail <= 1e-8) {
                continue;
            }
            $fresh = getRecord('SELECT * FROM tbl_stock WHERE id = ' . (int) $src_id . ' AND status = 1 LIMIT 1');
            if (!$fresh || !is_array($fresh)) {
                continue;
            }
            $stock_row = $fresh;

            $payment = auragold_jobwork_me_payment_from_stock_and_payments(
                $conn,
                $stock_row,
                $so_payments,
                $avail
            );
            if ($payment === null) {
                continue;
            }

            $issue_wt = auragold_metal_exchange_resolve($conn, $payment);
            $deduct_gross = (float) ($issue_wt['gross'] ?? 0);
            if ($deduct_gross <= 1e-8) {
                continue;
            }

            auragold_validate_metal_exchange_for_stock($conn, $payment);
            auragold_post_metal_exchange_payment_to_stock(
                $conn,
                'material_issue_metal_exchange',
                $mi_id,
                $mi_no,
                $mi_dt,
                $payment,
                auragold_metal_exchange_default_branch_id(),
                $pay_seq,
                $has_ref,
                'Material Issue — Metal Exchange',
                'mi_me',
                'MI-ME-',
                $metal_exchange_barcodes_out
            );
            auragold_metal_exchange_deduct_source_stock($conn, $src_id, $deduct_gross);
            $pay_seq++;
            $issued_any = true;
        }

        return $issued_any ? $mi_id : 0;
    }
}

if (!function_exists('auragold_jobwork_me_payment_from_jwo_stock_row')) {
    /**
     * @param array<string, mixed> $stock_row tbl_stock jobwork_order_metal_exchange
     * @return array<string, mixed>|null
     */
    function auragold_jobwork_me_payment_from_jwo_stock_row(mysqli $conn, array $stock_row): ?array
    {
        $pcid = (int) ($stock_row['product_characteristic_id'] ?? 0);
        $mid = (int) ($stock_row['metal_id'] ?? 0);
        if ($pcid < 1 || $mid < 1) {
            return null;
        }
        $gross = (float) ($stock_row['opening_weight'] ?? 0);
        if ($gross <= 1e-8) {
            $gross = (float) ($stock_row['current_weight'] ?? 0);
        }
        if ($gross <= 1e-8) {
            return null;
        }
        $pure = (float) ($stock_row['final_weight'] ?? 0);
        if ($pure <= 1e-8) {
            $pure = $gross;
        }
        $pname = trim((string) ($stock_row['product_name'] ?? ''));
        if ($pname === '' && (int) ($stock_row['product_id'] ?? 0) > 0 && function_exists('getRecord')) {
            $pr = getRecord('SELECT name FROM tbl_products WHERE id = ' . (int) $stock_row['product_id'] . ' LIMIT 1');
            $pname = trim((string) ($pr['name'] ?? ''));
        }

        return [
            'type' => 'metal-exchange',
            'payment_type' => 'Metal Exchange',
            'deposit_into' => 'Metal Exchange',
            'amount' => (float) ($stock_row['value'] ?? 0),
            'quantity' => 1,
            'metal_exchange_metal_id' => $mid,
            'metal_exchange_characteristic_id' => $pcid,
            'metal_exchange_product_id' => $pcid,
            'metal_exchange_product_name' => $pname,
            'metal_exchange_gross_wt' => $gross,
            'metal_exchange_purity_wt' => $pure,
            'metal_exchange_item_code' => trim((string) ($stock_row['barcode'] ?? '')),
            'metal_exchange_rate' => (float) ($stock_row['rate'] ?? 0),
            'metal_exchange_source_stock_id' => (int) ($stock_row['id'] ?? 0),
        ];
    }
}

if (!function_exists('auragold_jobwork_order_auto_issue_jwo_metal_exchange_stocks')) {
    /**
     * Issue metal exchange stock posted on this JWO (user-added on job work screen) to Assign To.
     */
    function auragold_jobwork_order_auto_issue_jwo_metal_exchange_stocks(
        mysqli $conn,
        int $sale_order_id,
        int $jwo_id,
        ?int $department_id,
        ?int $department_user_id,
        string $priority,
        string $status,
        array &$metal_exchange_barcodes_out
    ): int {
        if ($sale_order_id < 1 || $jwo_id < 1) {
            return 0;
        }
        if ($department_user_id === null || (int) $department_user_id < 1) {
            return 0;
        }
        if (!auragold_prepare_tbl_stock_reference_columns($conn)) {
            return 0;
        }

        $jwo_stocks = getList(
            'SELECT * FROM tbl_stock WHERE status = 1'
            . " AND reference_type = 'jobwork_order_metal_exchange'"
            . ' AND reference_id = ' . (int) $jwo_id
            . ' ORDER BY id ASC'
        );
        if (!is_array($jwo_stocks) || $jwo_stocks === []) {
            return 0;
        }

        $mi_id = auragold_jobwork_order_resolve_material_issue_for_auto_me(
            $conn,
            $sale_order_id,
            $department_id,
            $department_user_id,
            $priority,
            $status
        );
        if ($mi_id < 1) {
            return 0;
        }

        $mi_hdr = getRecord(
            'SELECT material_issue_no, order_date FROM tbl_material_issues WHERE id = '
            . (int) $mi_id . ' LIMIT 1'
        );
        $mi_no = trim((string) ($mi_hdr['material_issue_no'] ?? ''));
        $mi_dt = substr(trim((string) ($mi_hdr['order_date'] ?? '')), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $mi_dt)) {
            $mi_dt = date('Y-m-d');
        }

        $has_ref = auragold_metal_exchange_document_init($conn, false, $mi_id, 'material_issue_metal_exchange');
        $seq_row = getRecord(
            "SELECT COUNT(*) AS c FROM tbl_stock WHERE status = 1"
            . " AND reference_type = 'material_issue_metal_exchange' AND reference_id = " . (int) $mi_id
        );
        $pay_seq = (int) ($seq_row['c'] ?? 0);
        $issued_any = false;

        foreach ($jwo_stocks as $stock_row) {
            if (!is_array($stock_row)) {
                continue;
            }
            $src_id = (int) ($stock_row['id'] ?? 0);
            if ($src_id < 1) {
                continue;
            }
            if (auragold_jobwork_order_me_already_issued_from_source($conn, $src_id)) {
                continue;
            }
            $payment = auragold_jobwork_me_payment_from_jwo_stock_row($conn, $stock_row);
            if ($payment === null) {
                continue;
            }
            $issue_wt = auragold_metal_exchange_resolve($conn, $payment);
            if ((float) ($issue_wt['gross'] ?? 0) <= 1e-8) {
                continue;
            }

            auragold_validate_metal_exchange_for_stock($conn, $payment);
            auragold_post_metal_exchange_payment_to_stock(
                $conn,
                'material_issue_metal_exchange',
                $mi_id,
                $mi_no,
                $mi_dt,
                $payment,
                auragold_metal_exchange_default_branch_id(),
                $pay_seq,
                $has_ref,
                'Material Issue — Metal Exchange',
                'mi_me',
                'MI-ME-',
                $metal_exchange_barcodes_out
            );
            $deduct = (float) ($issue_wt['gross'] ?? 0);
            auragold_metal_exchange_deduct_source_stock($conn, $src_id, $deduct);
            $pay_seq++;
            $issued_any = true;
        }

        return $issued_any ? $mi_id : 0;
    }
}
