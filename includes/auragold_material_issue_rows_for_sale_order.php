<?php

/**
 * Aggregate diamond/stone lines issued on Material Issue (or Repair Material Issue) for a sale/repair order.
 * Used on Material Receive to show what was issued and remaining balance for partial receive.
 */

if (!function_exists('auragold_material_issue_scope_sql_for_alias')) {
    function auragold_material_issue_scope_sql_for_alias(mysqli $conn, string $table, string $alias): string
    {
        $scope = function_exists('auragold_effective_branch_list_scope_sql')
            ? auragold_effective_branch_list_scope_sql($conn, $table)
            : '';
        if ($scope === '') {
            return '';
        }

        return preg_replace('/\bbranch_id\b/', $alias . '.branch_id', $scope);
    }
}

if (!function_exists('auragold_material_issue_enrich_issued_gem_rows')) {
    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    function auragold_material_issue_enrich_issued_gem_rows(mysqli $conn, array $rows, string $receive_tbl, string $category_key): array
    {
        require_once __DIR__ . '/auragold_voucher_diamond_stock.php';
        if ($receive_tbl === 'tbl_voucher_stone_stock_issue') {
            require_once __DIR__ . '/auragold_voucher_stone_stock.php';
            auragold_voucher_ensure_stone_issue_table($conn);
        } else {
            auragold_voucher_ensure_diamond_issue_table($conn);
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $issue_line_id = (int) ($row['issue_line_id'] ?? $row['id'] ?? 0);
            $issued_wt = (float) ($row['issued_weight'] ?? $row['weight'] ?? 0);
            $issued_qty = (float) ($row['issued_qty'] ?? $row['qty'] ?? 0);
            $recv = $issue_line_id > 0
                ? auragold_voucher_received_sum_for_source_issue($conn, $receive_tbl, $issue_line_id)
                : ['weight' => 0.0, 'qty' => 0.0];
            $recv_wt = (float) $recv['weight'];
            $recv_qty = (float) $recv['qty'];
            $bal_wt = max(0.0, round($issued_wt - $recv_wt, 4));
            $bal_qty = max(0.0, round($issued_qty - $recv_qty, 4));
            $cat_val = trim((string) ($row[$category_key] ?? ''));
            $out[] = [
                'issue_line_id' => $issue_line_id,
                'issue_id' => $issue_line_id,
                'id' => $issue_line_id,
                'stock_id' => (int) ($row['stock_id'] ?? 0),
                'material_issue_id' => (int) ($row['material_issue_id'] ?? 0),
                'barcode' => trim((string) ($row['barcode'] ?? '')),
                'product_name' => trim((string) ($row['product_name'] ?? '')),
                $category_key => $cat_val,
                'diamond_category' => $category_key === 'diamond_category' ? $cat_val : '',
                'stone_category' => $category_key === 'stone_category' ? $cat_val : '',
                'issued_weight' => $issued_wt,
                'issued_qty' => $issued_qty,
                'weight' => $issued_wt,
                'qty' => $issued_qty,
                'received_weight' => $recv_wt,
                'received_qty' => $recv_qty,
                'balance_weight' => $bal_wt,
                'balance_qty' => $bal_qty,
                'reference_status' => $bal_wt <= 0.0000001 ? 'fully_received' : ($recv_wt > 0.0000001 ? 'partial' : 'issued'),
            ];
        }

        return $out;
    }
}

if (!function_exists('auragold_material_issue_list_diamond_rows_for_sale_order')) {
    /**
     * @return list<array<string, mixed>>
     */
    function auragold_material_issue_list_diamond_rows_for_sale_order(
        mysqli $conn,
        int $sale_order_id,
        string $hdr_scope_sql = ''
    ): array {
        if ($sale_order_id < 1 || !function_exists('getList')) {
            return [];
        }
        $scope = $hdr_scope_sql !== ''
            ? preg_replace('/\bbranch_id\b/', 'mi.branch_id', $hdr_scope_sql)
            : auragold_material_issue_scope_sql_for_alias($conn, 'tbl_material_issues', 'mi');
        $rows = getList(
            'SELECT ds.id AS issue_line_id, ds.stock_id, ds.barcode, ds.product_name, ds.diamond_category,'
            . ' ds.weight AS issued_weight, ds.qty AS issued_qty, ds.voucher_id AS material_issue_id'
            . ' FROM tbl_voucher_diamond_stock_issue ds'
            . ' INNER JOIN tbl_material_issues mi ON mi.id = ds.voucher_id'
            . " WHERE ds.voucher_kind = 'material_issue' AND mi.sale_order_id = " . (int) $sale_order_id
            . $scope
            . ' ORDER BY ds.id ASC'
        );

        return auragold_material_issue_enrich_issued_gem_rows(
            $conn,
            is_array($rows) ? $rows : [],
            'tbl_voucher_diamond_stock_issue',
            'diamond_category'
        );
    }
}

if (!function_exists('auragold_material_issue_list_stone_rows_for_sale_order')) {
    /**
     * @return list<array<string, mixed>>
     */
    function auragold_material_issue_list_stone_rows_for_sale_order(
        mysqli $conn,
        int $sale_order_id,
        string $hdr_scope_sql = ''
    ): array {
        if ($sale_order_id < 1 || !function_exists('getList')) {
            return [];
        }
        $scope = $hdr_scope_sql !== ''
            ? preg_replace('/\bbranch_id\b/', 'mi.branch_id', $hdr_scope_sql)
            : auragold_material_issue_scope_sql_for_alias($conn, 'tbl_material_issues', 'mi');
        $rows = getList(
            'SELECT ds.id AS issue_line_id, ds.stock_id, ds.barcode, ds.product_name, ds.stone_category,'
            . ' ds.weight AS issued_weight, ds.qty AS issued_qty, ds.voucher_id AS material_issue_id'
            . ' FROM tbl_voucher_stone_stock_issue ds'
            . ' INNER JOIN tbl_material_issues mi ON mi.id = ds.voucher_id'
            . " WHERE ds.voucher_kind = 'material_issue' AND mi.sale_order_id = " . (int) $sale_order_id
            . $scope
            . ' ORDER BY ds.id ASC'
        );

        return auragold_material_issue_enrich_issued_gem_rows(
            $conn,
            is_array($rows) ? $rows : [],
            'tbl_voucher_stone_stock_issue',
            'stone_category'
        );
    }
}

if (!function_exists('auragold_material_issue_list_diamond_rows_for_repair_order')) {
    /**
     * @return list<array<string, mixed>>
     */
    function auragold_material_issue_list_diamond_rows_for_repair_order(mysqli $conn, int $repair_order_id): array
    {
        if ($repair_order_id < 1 || !function_exists('getList')) {
            return [];
        }
        $t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_repair_material_issues'");
        if (!$t || mysqli_num_rows($t) < 1) {
            if ($t) {
                mysqli_free_result($t);
            }

            return [];
        }
        mysqli_free_result($t);
        $rows = getList(
            'SELECT ds.id AS issue_line_id, ds.stock_id, ds.barcode, ds.product_name, ds.diamond_category,'
            . ' ds.weight AS issued_weight, ds.qty AS issued_qty, ds.voucher_id AS material_issue_id'
            . ' FROM tbl_voucher_diamond_stock_issue ds'
            . ' INNER JOIN tbl_repair_material_issues mi ON mi.id = ds.voucher_id'
            . " WHERE ds.voucher_kind = 'material_issue' AND mi.repair_order_id = " . (int) $repair_order_id
            . ' ORDER BY ds.id ASC'
        );

        return auragold_material_issue_enrich_issued_gem_rows(
            $conn,
            is_array($rows) ? $rows : [],
            'tbl_voucher_diamond_stock_issue',
            'diamond_category'
        );
    }
}

if (!function_exists('auragold_material_issue_list_stone_rows_for_repair_order')) {
    /**
     * @return list<array<string, mixed>>
     */
    function auragold_material_issue_list_stone_rows_for_repair_order(mysqli $conn, int $repair_order_id): array
    {
        if ($repair_order_id < 1 || !function_exists('getList')) {
            return [];
        }
        $t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_repair_material_issues'");
        if (!$t || mysqli_num_rows($t) < 1) {
            if ($t) {
                mysqli_free_result($t);
            }

            return [];
        }
        mysqli_free_result($t);
        $rows = getList(
            'SELECT ds.id AS issue_line_id, ds.stock_id, ds.barcode, ds.product_name, ds.stone_category,'
            . ' ds.weight AS issued_weight, ds.qty AS issued_qty, ds.voucher_id AS material_issue_id'
            . ' FROM tbl_voucher_stone_stock_issue ds'
            . ' INNER JOIN tbl_repair_material_issues mi ON mi.id = ds.voucher_id'
            . " WHERE ds.voucher_kind = 'material_issue' AND mi.repair_order_id = " . (int) $repair_order_id
            . ' ORDER BY ds.id ASC'
        );

        return auragold_material_issue_enrich_issued_gem_rows(
            $conn,
            is_array($rows) ? $rows : [],
            'tbl_voucher_stone_stock_issue',
            'stone_category'
        );
    }
}

if (!function_exists('auragold_ensure_stock_source_stock_id_column')) {
    function auragold_ensure_stock_source_stock_id_column(mysqli $conn): bool
    {
        if (!function_exists('auragold_tbl_has_column')) {
            if (is_file(__DIR__ . '/auragold_branch_data_scope.php')) {
                require_once __DIR__ . '/auragold_branch_data_scope.php';
            }
        }
        if (!function_exists('auragold_tbl_has_column')) {
            return false;
        }
        if (auragold_tbl_has_column($conn, 'tbl_stock', 'source_stock_id')) {
            return true;
        }
        @mysqli_query(
            $conn,
            'ALTER TABLE `tbl_stock` ADD COLUMN `source_stock_id` INT NULL DEFAULT NULL AFTER `reference_type`, ADD KEY `idx_stock_source_stock` (`source_stock_id`)'
        );

        return auragold_tbl_has_column($conn, 'tbl_stock', 'source_stock_id');
    }
}

if (!function_exists('auragold_material_issue_me_received_sum_for_source_stock')) {
    function auragold_material_issue_me_received_sum_for_source_stock(mysqli $conn, int $source_stock_id): float
    {
        if ($source_stock_id < 1 || !function_exists('getRecord')) {
            return 0.0;
        }
        if (!auragold_ensure_stock_source_stock_id_column($conn)) {
            return 0.0;
        }
        $row = getRecord(
            'SELECT COALESCE(SUM(opening_weight), 0) AS w FROM tbl_stock'
            . " WHERE status = 1 AND reference_type = 'material_receive_metal_exchange'"
            . ' AND source_stock_id = ' . (int) $source_stock_id
        );

        return (float) ($row['w'] ?? 0);
    }
}

if (!function_exists('auragold_metal_exchange_issued_sum_for_source_stock')) {
    /** Total gross already issued from a sale-order / issue ME stock row (material_issue_metal_exchange). */
    function auragold_metal_exchange_issued_sum_for_source_stock(mysqli $conn, int $source_stock_id): float
    {
        if ($source_stock_id < 1 || !function_exists('getRecord')) {
            return 0.0;
        }
        if (!auragold_ensure_stock_source_stock_id_column($conn)) {
            return 0.0;
        }
        $row = getRecord(
            'SELECT COALESCE(SUM(opening_weight), 0) AS w FROM tbl_stock'
            . " WHERE status = 1 AND reference_type = 'material_issue_metal_exchange'"
            . ' AND source_stock_id = ' . (int) $source_stock_id
        );

        return (float) ($row['w'] ?? 0);
    }
}

if (!function_exists('auragold_material_issue_enrich_issued_metal_exchange_rows')) {
    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    function auragold_material_issue_enrich_issued_metal_exchange_rows(mysqli $conn, array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $issue_stock_id = (int) ($row['issue_stock_id'] ?? $row['id'] ?? 0);
            $issued_gross = (float) ($row['issued_gross'] ?? $row['opening_weight'] ?? 0);
            if ($issued_gross <= 1e-8) {
                $issued_gross = (float) ($row['current_weight'] ?? 0);
            }
            $issued_pure = (float) ($row['issued_pure'] ?? $row['final_weight'] ?? $issued_gross);
            $recv_gross = $issue_stock_id > 0
                ? auragold_material_issue_me_received_sum_for_source_stock($conn, $issue_stock_id)
                : 0.0;
            $ratio = ($issued_gross > 1e-8) ? ($issued_pure / $issued_gross) : 1.0;
            $bal_gross = max(0.0, round($issued_gross - $recv_gross, 4));
            $recv_pure = round($recv_gross * $ratio, 4);
            $bal_pure = max(0.0, round($issued_pure - $recv_pure, 4));
            $metal_id = (int) ($row['metal_id'] ?? 0);
            $pcid = (int) ($row['product_characteristic_id'] ?? 0);
            $product_name = trim((string) ($row['product_name'] ?? ''));
            $metal_name = trim((string) ($row['metal_name'] ?? $row['metal_system'] ?? ''));
            if ($issue_stock_id > 0 && ($metal_id < 1 || $pcid < 1 || $product_name === '')) {
                $st = getRecord(
                    'SELECT s.metal_id, s.product_characteristic_id, s.product_id, p.name AS product_name'
                    . ' FROM tbl_stock s'
                    . ' LEFT JOIN tbl_products p ON p.id = s.product_id'
                    . ' WHERE s.id = '
                    . $issue_stock_id
                    . ' AND s.status = 1 LIMIT 1'
                );
                if (is_array($st)) {
                    if ($metal_id < 1) {
                        $metal_id = (int) ($st['metal_id'] ?? 0);
                    }
                    if ($pcid < 1) {
                        $pcid = (int) ($st['product_characteristic_id'] ?? 0);
                    }
                    if ($product_name === '') {
                        $product_name = trim((string) ($st['product_name'] ?? ''));
                    }
                }
            }
            if ($metal_id > 0 && $metal_name === '' && function_exists('getRecord')) {
                $mr = getRecord(
                    'SELECT COALESCE(display_name, system_name, \'\') AS n FROM tbl_metal WHERE id = '
                    . $metal_id . ' LIMIT 1'
                );
                $metal_name = trim((string) ($mr['n'] ?? ''));
            }
            $out[] = [
                'issue_stock_id' => $issue_stock_id,
                'material_issue_id' => (int) ($row['material_issue_id'] ?? 0),
                'barcode' => trim((string) ($row['barcode'] ?? '')),
                'product_name' => $product_name,
                'metal_id' => $metal_id,
                'product_characteristic_id' => $pcid,
                'metal_name' => $metal_name !== '' ? $metal_name : 'Gold',
                'issued_gross' => $issued_gross,
                'issued_pure' => $issued_pure,
                'issued_weight' => $issued_gross,
                'received_gross' => $recv_gross,
                'received_pure' => $recv_pure,
                'balance_gross' => $bal_gross,
                'balance_pure' => $bal_pure,
                'balance_weight' => $bal_gross,
                'purity' => (float) ($row['opening_purity'] ?? 0),
                'reference_status' => $bal_gross <= 0.0000001 ? 'fully_received' : ($recv_gross > 0.0000001 ? 'partial' : 'to_receive'),
                'me_source' => (string) ($row['me_source'] ?? ''),
                'issue_source_label' => (string) ($row['issue_source_label'] ?? ''),
            ];
        }

        return $out;
    }
}

if (!function_exists('auragold_material_issue_merge_me_row_lists')) {
    /**
     * @param list<array<string, mixed>> $primary
     * @param list<array<string, mixed>> $extra
     * @return list<array<string, mixed>>
     */
    function auragold_material_issue_merge_me_row_lists(array $primary, array $extra): array
    {
        $out = $primary;
        $seen = [];
        foreach ($primary as $row) {
            if (!is_array($row)) {
                continue;
            }
            $sid = (int) ($row['issue_stock_id'] ?? 0);
            if ($sid > 0) {
                $seen['s' . $sid] = true;
            }
            if ($sid > 0) {
                $key = 's' . $sid;
            } else {
                $key = strtolower(
                    'w|'
                    . (int) ($row['metal_id'] ?? 0) . '|'
                    . (int) ($row['product_characteristic_id'] ?? 0) . '|'
                    . trim((string) ($row['barcode'] ?? '')) . '|'
                    . round((float) ($row['issued_gross'] ?? $row['issued_weight'] ?? 0), 4)
                );
            }
            $seen[$key] = true;
        }
        foreach ($extra as $row) {
            if (!is_array($row)) {
                continue;
            }
            $sid = (int) ($row['issue_stock_id'] ?? 0);
            if ($sid > 0 && isset($seen['s' . $sid])) {
                continue;
            }
            $key = $sid > 0
                ? ('s' . $sid)
                : strtolower(
                    'w|'
                    . (int) ($row['metal_id'] ?? 0) . '|'
                    . (int) ($row['product_characteristic_id'] ?? 0) . '|'
                    . trim((string) ($row['barcode'] ?? '')) . '|'
                    . round((float) ($row['issued_gross'] ?? $row['issued_weight'] ?? 0), 4)
                );
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            if ($sid > 0) {
                $seen['s' . $sid] = true;
            }
            $out[] = $row;
        }

        return $out;
    }
}

if (!function_exists('auragold_material_issue_me_payment_dedupe_key')) {
    function auragold_material_issue_me_payment_dedupe_key(mysqli $conn, array $payment): string
    {
        $mi_stock_id = (int) ($payment['mi_stock_id'] ?? $payment['metal_exchange_stock_id'] ?? 0);
        if ($mi_stock_id > 0) {
            return 'mi_stock:' . $mi_stock_id;
        }
        $pay_id = (int) ($payment['id'] ?? 0);
        if ($pay_id > 0) {
            return 'pay_db:' . $pay_id;
        }
        $client_id = trim((string) ($payment['id'] ?? ''));
        if ($client_id !== '' && preg_match('/^payment-/i', $client_id)) {
            return 'client:' . $client_id;
        }
        if (!function_exists('auragold_payment_is_metal_exchange_inward')) {
            require_once __DIR__ . '/auragold_metal_exchange_stock.php';
        }
        $p = function_exists('auragold_payment_merge_stored_details')
            ? auragold_payment_merge_stored_details($payment)
            : $payment;
        $r = auragold_metal_exchange_resolve($conn, $p);
        $user_bc = trim((string) ($p['metal_exchange_item_code'] ?? $p['item_code'] ?? ''));

        return implode('|', [
            (int) ($p['metal_exchange_metal_id'] ?? $p['metal_id'] ?? 0),
            (int) ($p['metal_exchange_product_id'] ?? $p['product_characteristic_id'] ?? $p['product_id'] ?? 0),
            $user_bc,
            number_format((float) ($r['gross'] ?? 0), 4, '.', ''),
            number_format((float) ($r['pure'] ?? 0), 4, '.', ''),
        ]);
    }
}

if (!function_exists('auragold_material_issue_payments_from_mi_stock')) {
    /**
     * Metal exchange posted on this material issue (MI-ME stock), for payment cards when not on sale_order_payments.
     *
     * @return list<array<string, mixed>>
     */
    function auragold_material_issue_payments_from_mi_stock(
        mysqli $conn,
        int $material_issue_id,
        bool $include_jwo_sourced = false
    ): array {
        if ($material_issue_id < 1 || !function_exists('getList')) {
            return [];
        }
        if (!function_exists('auragold_payment_is_metal_exchange_inward')) {
            require_once __DIR__ . '/auragold_metal_exchange_stock.php';
        }
        if (!auragold_prepare_tbl_stock_reference_columns($conn)) {
            return [];
        }
        $rows = getList(
            'SELECT s.*, p.name AS product_name, m.display_name AS metal_name'
            . ' FROM tbl_stock s'
            . ' LEFT JOIN tbl_products p ON p.id = s.product_id'
            . ' LEFT JOIN tbl_metal m ON m.id = s.metal_id'
            . " WHERE s.status = 1 AND s.reference_type = 'material_issue_metal_exchange'"
            . ' AND s.reference_id = ' . (int) $material_issue_id
            . ' ORDER BY s.id ASC'
        );
        if (!is_array($rows) || $rows === []) {
            return [];
        }
        $out = [];
        foreach ($rows as $stock) {
            if (!is_array($stock)) {
                continue;
            }
            if (!$include_jwo_sourced) {
                $src_id = (int) ($stock['source_stock_id'] ?? 0);
                if ($src_id > 0 && function_exists('getRecord')) {
                    $src = getRecord(
                        'SELECT reference_type FROM tbl_stock WHERE id = ' . (int) $src_id . ' AND status = 1 LIMIT 1'
                    );
                    if ($src) {
                        $src_ref = (string) ($src['reference_type'] ?? '');
                        // Already shown via tbl_sale_order_payments (JWO ME) or must not show SO ME on MI.
                        if ($src_ref === 'sale_order_metal_exchange' || $src_ref === 'jobwork_order_metal_exchange') {
                            continue;
                        }
                    }
                }
            }
            $gross = (float) ($stock['opening_weight'] ?? 0);
            if ($gross <= 1e-8) {
                $gross = (float) ($stock['current_weight'] ?? 0);
            }
            $pure = (float) ($stock['final_weight'] ?? 0);
            if ($pure <= 1e-8) {
                $pure = $gross;
            }
            $stock_id = (int) ($stock['id'] ?? 0);
            $out[] = [
                'id' => 'mi-stock-' . $stock_id,
                'material_issue_id' => $material_issue_id,
                'type' => 'metal-exchange',
                'payment_type' => 'Metal Exchange',
                'deposit_into' => 'Metal Exchange',
                'amount' => (float) ($stock['value'] ?? 0),
                'quantity' => (float) ($stock['opening_qty'] ?? $stock['current_qty'] ?? 1),
                'metal_exchange_metal_id' => (int) ($stock['metal_id'] ?? 0),
                'metal_exchange_product_id' => (int) ($stock['product_characteristic_id'] ?? 0),
                'metal_exchange_product_name' => trim((string) ($stock['product_name'] ?? '')),
                'metal_exchange_gross_wt' => $gross,
                'metal_exchange_purity_wt' => $pure,
                'metal_exchange_item_code' => trim((string) ($stock['barcode'] ?? '')),
                'metal_exchange_rate' => (float) ($stock['rate'] ?? 0),
                'mi_stock_id' => $stock_id,
                'mi_metal_exchange' => 1,
                'mi_client_metal_exchange' => 1,
            ];
        }

        return $out;
    }
}

if (!function_exists('auragold_material_issue_me_payment_matches_stock_row')) {
    /**
     * Match payment to issued ME stock by product + weight (ignore stale item code on payment row).
     */
    function auragold_material_issue_me_payment_matches_stock_row(mysqli $conn, array $payment, array $stock_row): bool
    {
        $pay_stock_id = (int) ($payment['mi_stock_id'] ?? $payment['metal_exchange_stock_id'] ?? 0);
        $stock_id = (int) ($stock_row['id'] ?? 0);
        if ($pay_stock_id > 0 && $stock_id > 0) {
            return $pay_stock_id === $stock_id;
        }
        if (!function_exists('auragold_payment_is_metal_exchange_inward')) {
            require_once __DIR__ . '/auragold_metal_exchange_stock.php';
        }
        $m = auragold_payment_merge_stored_details($payment);
        if (!auragold_payment_is_metal_exchange_inward($conn, $m)) {
            return false;
        }
        $resolved = auragold_metal_exchange_resolve($conn, $m);
        $gross = (float) ($resolved['gross'] ?? 0);
        $pcid = auragold_metal_exchange_resolve_characteristic_id($conn, $m);
        $stock_pcid = (int) ($stock_row['product_characteristic_id'] ?? 0);
        $stock_w = (float) ($stock_row['opening_weight'] ?? 0);
        if ($stock_w <= 1e-8) {
            $stock_w = (float) ($stock_row['current_weight'] ?? 0);
        }
        $pay_bc = trim((string) ($m['metal_exchange_item_code'] ?? $m['item_code'] ?? ''));
        $stock_bc = trim((string) ($stock_row['barcode'] ?? ''));
        if ($pay_bc !== '' && $stock_bc !== '' && strcasecmp($pay_bc, $stock_bc) === 0) {
            return $pcid < 1 || $stock_pcid < 1 || $pcid === $stock_pcid;
        }

        return $gross > 1e-8
            && abs($gross - $stock_w) <= 0.0001
            && ($pcid < 1 || $stock_pcid < 1 || $pcid === $stock_pcid);
    }
}

if (!function_exists('auragold_material_issue_resolve_me_barcode_for_payment')) {
    /**
     * Barcode shown in material history (MI-ME stock) overrides typed item code on payment row.
     */
    function auragold_material_issue_resolve_me_barcode_for_payment(
        mysqli $conn,
        array $payment,
        int $sale_order_id,
        int $material_issue_id = 0
    ): string {
        if (!function_exists('auragold_payment_is_metal_exchange_inward')) {
            require_once __DIR__ . '/auragold_metal_exchange_stock.php';
        }
        $m = auragold_payment_merge_stored_details($payment);

        if ($material_issue_id > 0 && function_exists('getList')) {
            $mi_stocks = getList(
                'SELECT s.* FROM tbl_stock s'
                . " WHERE s.status = 1 AND s.reference_type = 'material_issue_metal_exchange'"
                . ' AND s.reference_id = ' . (int) $material_issue_id
                . ' ORDER BY s.id DESC'
            );
            if (is_array($mi_stocks)) {
                foreach ($mi_stocks as $stock) {
                    if (!is_array($stock)) {
                        continue;
                    }
                    if (auragold_material_issue_me_payment_matches_stock_row($conn, $m, $stock)) {
                        $bc = trim((string) ($stock['barcode'] ?? ''));
                        if ($bc !== '') {
                            return $bc;
                        }
                    }
                }
            }
        }

        if ($sale_order_id > 0 && function_exists('auragold_sale_order_get_metal_exchange_stocks')) {
            foreach (auragold_sale_order_get_metal_exchange_stocks($conn, $sale_order_id, 'jobwork_order_metal_exchange') as $stock) {
                if (!is_array($stock)) {
                    continue;
                }
                if (auragold_material_issue_me_payment_matches_stock_row($conn, $m, $stock)) {
                    $bc = trim((string) ($stock['barcode'] ?? ''));
                    if ($bc !== '') {
                        return $bc;
                    }
                }
            }
        }

        return '';
    }
}

if (!function_exists('auragold_material_issue_enrich_me_payment_barcodes')) {
    /**
     * @param array<int, array<string, mixed>> $payments
     * @return array<int, array<string, mixed>>
     */
    function auragold_material_issue_enrich_me_payment_barcodes(
        mysqli $conn,
        array $payments,
        int $sale_order_id,
        int $material_issue_id = 0
    ): array {
        foreach ($payments as $idx => $row) {
            if (!is_array($row)) {
                continue;
            }
            $merged = function_exists('auragold_payment_merge_stored_details')
                ? auragold_payment_merge_stored_details($row)
                : $row;
            $is_jwo = function_exists('auragold_jobwork_payment_row_is_jwo_metal_exchange')
                && auragold_jobwork_payment_row_is_jwo_metal_exchange($merged, 0);
            if (!empty($merged['mi_client_metal_exchange'])) {
                $user_bc = trim((string) ($merged['metal_exchange_item_code'] ?? $merged['item_code'] ?? ''));
                if ($user_bc !== '') {
                    continue;
                }
            }
            if (!$is_jwo && empty($merged['mi_metal_exchange'])) {
                continue;
            }
            $bc = auragold_material_issue_resolve_me_barcode_for_payment(
                $conn,
                $row,
                $sale_order_id,
                $material_issue_id
            );
            if ($bc === '') {
                continue;
            }
            $payments[$idx]['metal_exchange_item_code'] = $bc;
            $payments[$idx]['item_code'] = $bc;
            if (!empty($row['payment_details']) && is_string($row['payment_details'])) {
                $jd = json_decode($row['payment_details'], true);
                if (is_array($jd)) {
                    $jd['metal_exchange_item_code'] = $bc;
                    $jd['item_code'] = $bc;
                    $payments[$idx]['payment_details'] = json_encode($jd);
                }
            }
        }

        return array_values($payments);
    }
}

if (!function_exists('auragold_material_issue_payment_is_mi_client_added')) {
    function auragold_material_issue_payment_is_mi_client_added(array $payment): bool
    {
        $merged = function_exists('auragold_payment_merge_stored_details')
            ? auragold_payment_merge_stored_details($payment)
            : $payment;

        return !empty($merged['mi_client_metal_exchange']) || !empty($merged['mi_metal_exchange']);
    }
}

if (!function_exists('auragold_material_issue_payments_for_mi_stock_save')) {
    /**
     * Only metal exchange added on Material Issue screen — not JWO/SO inherited payment cards.
     *
     * @param array<int, array<string, mixed>> $payments
     * @return array<int, array<string, mixed>>
     */
    function auragold_material_issue_payments_for_mi_stock_save(mysqli $conn, array $payments): array
    {
        if ($payments === []) {
            return [];
        }
        if (!function_exists('auragold_payment_is_metal_exchange_inward')) {
            require_once __DIR__ . '/auragold_metal_exchange_stock.php';
        }
        $out = [];
        foreach ($payments as $payment) {
            if (!is_array($payment)) {
                continue;
            }
            $merged = auragold_payment_merge_stored_details($payment);
            if (!auragold_payment_is_metal_exchange_inward($conn, $merged)) {
                continue;
            }
            if (function_exists('auragold_jobwork_payment_row_is_jwo_metal_exchange')
                && auragold_jobwork_payment_row_is_jwo_metal_exchange($merged, 0)) {
                continue;
            }
            if (auragold_material_issue_should_hide_payment_row($conn, $merged)) {
                continue;
            }
            if (!empty($merged['mi_client_metal_exchange']) || !empty($merged['mi_metal_exchange'])) {
                $out[] = $payment;

                continue;
            }
            $ui_type = strtolower(trim((string) ($merged['type'] ?? '')));
            if ($ui_type === 'metal-exchange' || $ui_type === 'metal_exchange') {
                $payment['mi_client_metal_exchange'] = true;
                $payment['mi_metal_exchange'] = 1;
                $out[] = $payment;
            }
        }

        return array_values($out);
    }
}

if (!function_exists('auragold_material_issue_delete_client_added_me_stock')) {
    /**
     * Remove MI metal exchange stock created on this screen; keep lines issued from job work (JWO) stock.
     */
    function auragold_material_issue_delete_client_added_me_stock(mysqli $conn, int $material_issue_id): void
    {
        if ($material_issue_id < 1 || !function_exists('getList')) {
            return;
        }
        if (!function_exists('auragold_prepare_tbl_stock_reference_columns')) {
            require_once __DIR__ . '/auragold_metal_exchange_stock.php';
        }
        auragold_prepare_tbl_stock_reference_columns($conn);
        $has_src = function_exists('auragold_tbl_has_column')
            && auragold_tbl_has_column($conn, 'tbl_stock', 'source_stock_id');
        $rows = getList(
            'SELECT id, barcode, source_stock_id FROM tbl_stock'
            . " WHERE status = 1 AND reference_type = 'material_issue_metal_exchange'"
            . ' AND reference_id = ' . (int) $material_issue_id
            . ' ORDER BY id ASC'
        );
        if (!is_array($rows) || $rows === []) {
            return;
        }
        $t_sj = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_stock_journal'");
        $has_sj = ($t_sj && mysqli_num_rows($t_sj) > 0);
        if ($t_sj) {
            mysqli_free_result($t_sj);
        }
        $pfx_esc = mysqli_real_escape_string($conn, 'MI-ME-' . (int) $material_issue_id . '-');
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $keep = false;
            if ($has_src) {
                $src_id = (int) ($row['source_stock_id'] ?? 0);
                if ($src_id > 0 && function_exists('getRecord')) {
                    $src = getRecord(
                        'SELECT reference_type FROM tbl_stock WHERE id = ' . (int) $src_id . ' AND status = 1 LIMIT 1'
                    );
                    if ($src && (string) ($src['reference_type'] ?? '') === 'jobwork_order_metal_exchange') {
                        $keep = true;
                    }
                }
            }
            if ($keep) {
                continue;
            }
            $sid = (int) ($row['id'] ?? 0);
            if ($sid < 1) {
                continue;
            }
            $bc = trim((string) ($row['barcode'] ?? ''));
            if ($bc !== '' && $has_sj) {
                $bc_esc = mysqli_real_escape_string($conn, $bc);
                @mysqli_query(
                    $conn,
                    "DELETE FROM tbl_stock_journal WHERE sj_invoice_no LIKE '{$pfx_esc}%' AND barcode = '$bc_esc'"
                );
            }
            @mysqli_query($conn, 'DELETE FROM tbl_stock WHERE id = ' . $sid);
        }
    }
}

if (!function_exists('auragold_material_issue_should_hide_payment_row')) {
    /**
     * Hide sale-order metal exchange on Material Issue (incl. zero-weight rows that fail inward validation).
     */
    function auragold_material_issue_should_hide_payment_row(mysqli $conn, array $row): bool
    {
        if (!function_exists('auragold_payment_is_metal_exchange_inward')) {
            require_once __DIR__ . '/auragold_metal_exchange_stock.php';
        }
        $merged = function_exists('auragold_payment_merge_stored_details')
            ? auragold_payment_merge_stored_details($row)
            : $row;
        if (function_exists('auragold_jobwork_payment_row_is_jwo_metal_exchange')
            && auragold_jobwork_payment_row_is_jwo_metal_exchange($merged, 0)) {
            return false;
        }
        if (!empty($merged['mi_metal_exchange']) || !empty($merged['mi_client_metal_exchange'])) {
            return false;
        }
        if (auragold_payment_is_metal_exchange_inward($conn, $merged)) {
            return true;
        }
        $dep = strtolower(trim((string) ($merged['deposit_into'] ?? '')));
        $pt = strtolower(trim((string) ($merged['payment_type'] ?? '')));
        $type = strtolower(trim((string) ($merged['type'] ?? '')));

        return $dep === 'metal exchange'
            || strpos($pt, 'exch') !== false
            || strpos($type, 'exch') !== false
            || $type === 'metal-exchange';
    }
}

if (!function_exists('auragold_material_issue_embed_payments_for_sale_order')) {
    /**
     * Material Issue (?sale_order_id=): payment cards = JWO metal exchange + this MI's ME only (not sale-order ME).
     *
     * @param array<int, array<string, mixed>> $payments
     * @return array<int, array<string, mixed>>
     */
    function auragold_material_issue_embed_payments_for_sale_order(
        mysqli $conn,
        array $payments,
        int $sale_order_id,
        int $material_issue_id = 0
    ): array {
        if ($sale_order_id < 1) {
            return $payments;
        }
        if (!function_exists('auragold_payment_is_metal_exchange_inward')) {
            require_once __DIR__ . '/auragold_metal_exchange_stock.php';
        }
        if (is_file(__DIR__ . '/auragold_payment_details_merge.php')) {
            require_once __DIR__ . '/auragold_payment_details_merge.php';
        }

        $out = [];
        $seen_me = [];
        $seen_pay_ids = [];

        $append_me = static function (array $row) use ($conn, &$out, &$seen_me, &$seen_pay_ids): void {
            if (!auragold_payment_is_metal_exchange_inward($conn, $row)) {
                return;
            }
            if (!function_exists('auragold_jobwork_payment_row_is_jwo_metal_exchange')
                || !auragold_jobwork_payment_row_is_jwo_metal_exchange($row, 0)) {
                if (empty($row['mi_metal_exchange'])) {
                    return;
                }
            }
            $key = auragold_material_issue_me_payment_dedupe_key($conn, $row);
            if (isset($seen_me[$key])) {
                return;
            }
            $seen_me[$key] = true;
            $pay_id = (int) ($row['id'] ?? 0);
            if ($pay_id > 0) {
                $seen_pay_ids[$pay_id] = true;
            }
            $out[] = $row;
        };

        foreach ($payments as $row) {
            if (!is_array($row)) {
                continue;
            }
            $merged = function_exists('auragold_payment_merge_stored_details')
                ? auragold_payment_merge_stored_details($row)
                : $row;
            if (auragold_payment_is_metal_exchange_inward($conn, $merged)) {
                $append_me($merged);

                continue;
            }
            if (auragold_material_issue_should_hide_payment_row($conn, $merged)) {
                continue;
            }
            $out[] = $row;
        }

        if (function_exists('getList')) {
            $db_pays = getList(
                'SELECT * FROM tbl_sale_order_payments WHERE order_id = ' . (int) $sale_order_id . ' ORDER BY id ASC'
            );
            if (is_array($db_pays)) {
                foreach ($db_pays as $db_row) {
                    if (!is_array($db_row)) {
                        continue;
                    }
                    $db_pay_id = (int) ($db_row['id'] ?? 0);
                    if ($db_pay_id > 0 && isset($seen_pay_ids[$db_pay_id])) {
                        continue;
                    }
                    $merged = function_exists('auragold_payment_merge_stored_details')
                        ? auragold_payment_merge_stored_details($db_row)
                        : $db_row;
                    $append_me($merged);
                }
            }
        }

        if ($material_issue_id > 0) {
            foreach (auragold_material_issue_payments_from_mi_stock($conn, $material_issue_id) as $syn) {
                $append_me($syn);
            }
        }

        return auragold_material_issue_enrich_me_payment_barcodes(
            $conn,
            array_values($out),
            $sale_order_id,
            $material_issue_id
        );
    }
}

if (!function_exists('auragold_material_issue_metal_exchange_rows_from_payments')) {
    /**
     * Build receive lines from sale-order / document payment rows (Metal Exchange card data).
     *
     * @param list<array<string, mixed>> $payment_rows
     * @return list<array<string, mixed>>
     */
    function auragold_material_issue_metal_exchange_rows_from_payments(mysqli $conn, int $sale_order_id, array $payment_rows): array
    {
        if ($sale_order_id < 1 || $payment_rows === []) {
            return [];
        }
        if (!is_file(__DIR__ . '/auragold_metal_exchange_stock.php')) {
            return [];
        }
        require_once __DIR__ . '/auragold_metal_exchange_stock.php';
        $out = [];
        foreach ($payment_rows as $payment) {
            if (!is_array($payment)) {
                continue;
            }
            $p = auragold_payment_merge_stored_details($payment);
            if (!auragold_payment_is_metal_exchange_inward($conn, $p)) {
                continue;
            }
            $stock_id = (int) ($p['mi_stock_id'] ?? $p['metal_exchange_stock_id'] ?? 0);
            $is_mi_line = !empty($p['mi_client_metal_exchange']) || !empty($p['mi_metal_exchange']);
            $is_jwo_line = function_exists('auragold_jobwork_payment_row_is_jwo_metal_exchange')
                && auragold_jobwork_payment_row_is_jwo_metal_exchange($p, 0);
            if ($stock_id > 0) {
                $is_mi_line = true;
            }
            if (!$is_mi_line && !$is_jwo_line) {
                continue;
            }
            $r = auragold_metal_exchange_resolve($conn, $p);
            $mid = (int) ($p['metal_exchange_metal_id'] ?? $p['metal_id'] ?? 0);
            $pcid = (int) ($p['metal_exchange_product_id'] ?? $p['product_id'] ?? 0);
            $bc = trim((string) ($p['metal_exchange_item_code'] ?? $p['item_code'] ?? ''));
            if (($mid < 1 || $pcid < 1) && $stock_id > 0 && function_exists('getRecord')) {
                $st_row = getRecord(
                    'SELECT metal_id, product_characteristic_id, barcode, product_id FROM tbl_stock'
                    . ' WHERE id = ' . $stock_id . ' AND status = 1 LIMIT 1'
                );
                if ($st_row && is_array($st_row)) {
                    if ($mid < 1) {
                        $mid = (int) ($st_row['metal_id'] ?? 0);
                    }
                    if ($pcid < 1) {
                        $pcid = (int) ($st_row['product_characteristic_id'] ?? 0);
                    }
                    if ($bc === '' && trim((string) ($st_row['barcode'] ?? '')) !== '') {
                        $bc = trim((string) $st_row['barcode']);
                    }
                }
            }
            if ($mid < 1 || $pcid < 1) {
                if ($stock_id > 0 && function_exists('getRecord')) {
                    $st_full = getRecord(
                        'SELECT metal_id, product_characteristic_id, barcode, opening_weight, current_weight, final_weight,'
                        . ' reference_id, product_id FROM tbl_stock WHERE id = ' . (int) $stock_id . ' AND status = 1 LIMIT 1'
                    );
                    if ($st_full && is_array($st_full)) {
                        if ($mid < 1) {
                            $mid = (int) ($st_full['metal_id'] ?? 0);
                        }
                        if ($pcid < 1) {
                            $pcid = (int) ($st_full['product_characteristic_id'] ?? 0);
                        }
                        if ($bc === '') {
                            $bc = trim((string) ($st_full['barcode'] ?? ''));
                        }
                        $ow = (float) ($st_full['opening_weight'] ?? 0);
                        $cw = (float) ($st_full['current_weight'] ?? 0);
                        $fw = (float) ($st_full['final_weight'] ?? 0);
                        $issued_gross = abs($ow) > 1e-8 ? abs($ow) : abs($cw);
                        $issued_pure = abs($fw) > 1e-8 ? abs($fw) : $issued_gross;
                        $mi_ref = (int) ($st_full['reference_id'] ?? 0);
                        $metal_label = '';
                        if ($mid > 0) {
                            $mr = getRecord(
                                'SELECT COALESCE(display_name, system_name, \'\') AS n FROM tbl_metal WHERE id = '
                                . (int) $mid . ' LIMIT 1'
                            );
                            $metal_label = trim((string) ($mr['n'] ?? ''));
                        }
                        if ($issued_gross > 1e-8) {
                            $out[] = [
                                'issue_stock_id' => $stock_id,
                                'material_issue_id' => $mi_ref > 0 ? $mi_ref : (int) ($p['material_issue_id'] ?? 0),
                                'barcode' => $bc,
                                'product_name' => trim((string) ($p['metal_exchange_product_name'] ?? $p['product_name'] ?? '')),
                                'metal_id' => $mid,
                                'product_characteristic_id' => $pcid,
                                'metal_name' => $metal_label,
                                'issued_gross' => $issued_gross,
                                'issued_pure' => $issued_pure,
                                'issued_weight' => $issued_gross,
                                'opening_purity' => 0,
                                'me_source' => 'material_issue',
                                'issue_source_label' => 'Material issue',
                                'from_payment' => true,
                            ];
                            continue;
                        }
                    }
                }
                if ($mid < 1 || $pcid < 1) {
                    continue;
                }
            }
            $issued_gross = (float) $r['gross'];
            $issued_pure = (float) $r['pure'];
            if ($stock_id < 1 && $bc !== '' && function_exists('getRecord')) {
                $bc_esc = mysqli_real_escape_string($conn, $bc);
                $st = getRecord(
                    "SELECT id FROM tbl_stock WHERE status = 1 AND barcode = '$bc_esc'"
                    . " AND reference_type IN ('sale_order_metal_exchange', 'material_issue_metal_exchange')"
                    . ' AND (reference_id = ' . (int) $sale_order_id
                    . ' OR reference_id IN (SELECT id FROM tbl_material_issues WHERE sale_order_id = '
                    . (int) $sale_order_id . '))'
                    . ' ORDER BY id DESC LIMIT 1'
                );
                $stock_id = (int) ($st['id'] ?? 0);
            }
            $metal_label = '';
            if ($mid > 0 && function_exists('getRecord')) {
                $mr = getRecord(
                    'SELECT COALESCE(display_name, system_name, \'\') AS n FROM tbl_metal WHERE id = '
                    . (int) $mid . ' LIMIT 1'
                );
                $metal_label = trim((string) ($mr['n'] ?? ''));
            }
            $pname = trim((string) ($p['metal_exchange_product_name'] ?? $p['product_name'] ?? ''));
            $pur = (float) ($p['purity_carat'] ?? $p['purity'] ?? 0);
            if ($pur > 1 && $pur <= 100) {
                $pur = $pur / 100;
            } elseif ($pur > 100) {
                $pur = $pur / 1000;
            }
            $out[] = [
                'issue_stock_id' => $stock_id,
                'material_issue_id' => (int) ($p['material_issue_id'] ?? 0),
                'barcode' => $bc,
                'product_name' => $pname,
                'metal_id' => $mid,
                'product_characteristic_id' => $pcid,
                'metal_name' => $metal_label,
                'issued_gross' => $issued_gross,
                'issued_pure' => $issued_pure,
                'issued_weight' => $issued_gross,
                'opening_purity' => $pur > 0 ? $pur : ($issued_gross > 1e-8 ? min(1.0, $issued_pure / $issued_gross) : 0),
                'me_source' => $is_mi_line ? 'material_issue' : 'sale_order_payment',
                'issue_source_label' => $is_mi_line ? 'Material issue' : 'Order payment',
                'from_payment' => true,
            ];
        }

        return $out;
    }
}

if (!function_exists('auragold_material_issue_sync_me_stock_on_sale_order')) {
    /** Create sale_order_metal_exchange stock rows from sale-order payments when missing. */
    function auragold_material_issue_sync_me_stock_on_sale_order(mysqli $conn, int $sale_order_id): void
    {
        if ($sale_order_id < 1 || !function_exists('getList') || !function_exists('getRecord')) {
            return;
        }
        if (!is_file(__DIR__ . '/auragold_metal_exchange_stock.php')) {
            return;
        }
        require_once __DIR__ . '/auragold_metal_exchange_stock.php';
        if (!auragold_prepare_tbl_stock_reference_columns($conn)) {
            return;
        }
        $have = getRecord(
            "SELECT COUNT(*) AS c FROM tbl_stock WHERE status = 1"
            . " AND reference_type = 'sale_order_metal_exchange' AND reference_id = " . (int) $sale_order_id
        );
        if ((int) ($have['c'] ?? 0) > 0) {
            return;
        }
        $t_pay = @mysqli_query($conn, 'SHOW TABLES LIKE \'tbl_sale_order_payments\'');
        if (!$t_pay || mysqli_num_rows($t_pay) < 1) {
            if ($t_pay) {
                mysqli_free_result($t_pay);
            }

            return;
        }
        mysqli_free_result($t_pay);
        $pays = getList('SELECT * FROM tbl_sale_order_payments WHERE order_id = ' . (int) $sale_order_id . ' ORDER BY id ASC');
        if (!is_array($pays) || $pays === []) {
            return;
        }
        $so = getRecord('SELECT order_no, order_date FROM tbl_sale_orders WHERE id = ' . (int) $sale_order_id . ' LIMIT 1');
        $doc_no = trim((string) ($so['order_no'] ?? ''));
        $doc_dt = substr(trim((string) ($so['order_date'] ?? '')), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $doc_dt)) {
            $doc_dt = date('Y-m-d');
        }
        $has_ref = auragold_metal_exchange_document_init($conn, false, (int) $sale_order_id, 'sale_order_metal_exchange');
        $created = [];
        $seq = 0;
        foreach ($pays as $payment) {
            if (!is_array($payment)) {
                continue;
            }
            $p = auragold_payment_merge_stored_details($payment);
            if (!auragold_payment_is_metal_exchange_inward($conn, $p)) {
                continue;
            }
            try {
                auragold_validate_metal_exchange_for_stock($conn, $p);
                auragold_post_metal_exchange_payment_to_stock(
                    $conn,
                    'sale_order_metal_exchange',
                    (int) $sale_order_id,
                    $doc_no,
                    $doc_dt,
                    $p,
                    auragold_metal_exchange_default_branch_id(),
                    $seq,
                    $has_ref,
                    'Sale Order — Metal Exchange',
                    'so_me',
                    'SO-ME-',
                    $created
                );
                $seq++;
            } catch (Throwable $e) {
                error_log('auragold_material_issue_sync_me_stock_on_sale_order: ' . $e->getMessage());
            }
        }
    }
}

if (!function_exists('auragold_material_issue_sync_me_stock_on_material_issue')) {
    /**
     * If Material Issue exists but MI-ME stock rows are missing, post metal-exchange lines from
     * sale-order payments (common when ME was saved on Sale Order or MI before payments were sent on save).
     */
    function auragold_material_issue_sync_me_stock_on_material_issue(mysqli $conn, int $sale_order_id, string $hdr_scope_sql = ''): void
    {
        if ($sale_order_id < 1 || !function_exists('getList') || !function_exists('getRecord')) {
            return;
        }
        if (!is_file(__DIR__ . '/auragold_metal_exchange_stock.php')) {
            return;
        }
        require_once __DIR__ . '/auragold_metal_exchange_stock.php';
        if (!auragold_prepare_tbl_stock_reference_columns($conn)) {
            return;
        }
        $scope = $hdr_scope_sql !== ''
            ? preg_replace('/\bbranch_id\b/', 'mi.branch_id', $hdr_scope_sql)
            : auragold_material_issue_scope_sql_for_alias($conn, 'tbl_material_issues', 'mi');
        $mi = getRecord(
            'SELECT id, material_issue_no, order_date FROM tbl_material_issues'
            . ' WHERE sale_order_id = ' . (int) $sale_order_id . $scope
            . ' ORDER BY id DESC LIMIT 1'
        );
        if (!$mi || (int) ($mi['id'] ?? 0) < 1) {
            return;
        }
        $mi_id = (int) $mi['id'];
        $have = getRecord(
            "SELECT COUNT(*) AS c FROM tbl_stock WHERE status = 1"
            . " AND reference_type = 'material_issue_metal_exchange' AND reference_id = $mi_id"
        );
        if ((int) ($have['c'] ?? 0) > 0) {
            return;
        }
        $t_pay = @mysqli_query($conn, 'SHOW TABLES LIKE \'tbl_sale_order_payments\'');
        if (!$t_pay || mysqli_num_rows($t_pay) < 1) {
            if ($t_pay) {
                mysqli_free_result($t_pay);
            }

            return;
        }
        mysqli_free_result($t_pay);
        $pays = getList('SELECT * FROM tbl_sale_order_payments WHERE order_id = ' . (int) $sale_order_id . ' ORDER BY id ASC');
        if (!is_array($pays) || $pays === []) {
            return;
        }
        $doc_no = trim((string) ($mi['material_issue_no'] ?? ''));
        $doc_dt = substr(trim((string) ($mi['order_date'] ?? '')), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $doc_dt)) {
            $doc_dt = date('Y-m-d');
        }
        $has_ref = auragold_metal_exchange_document_init($conn, false, $mi_id, 'material_issue_metal_exchange');
        $created = [];
        $seq = 0;
        foreach ($pays as $payment) {
            if (!is_array($payment)) {
                continue;
            }
            $p = auragold_payment_merge_stored_details($payment);
            if (!auragold_payment_is_metal_exchange_inward($conn, $p)) {
                continue;
            }
            if (function_exists('auragold_jobwork_payment_row_is_jwo_metal_exchange')
                && !auragold_jobwork_payment_row_is_jwo_metal_exchange($p, 0)) {
                continue;
            }
            try {
                auragold_validate_metal_exchange_for_stock($conn, $p);
                auragold_post_metal_exchange_payment_to_stock(
                    $conn,
                    'material_issue_metal_exchange',
                    $mi_id,
                    $doc_no,
                    $doc_dt,
                    $p,
                    auragold_metal_exchange_default_branch_id(),
                    $seq,
                    $has_ref,
                    'Material Issue — Metal Exchange',
                    'mi_me',
                    'MI-ME-',
                    $created
                );
                $seq++;
            } catch (Throwable $e) {
                error_log('auragold_material_issue_sync_me_stock_on_material_issue: ' . $e->getMessage());
            }
        }
    }
}

if (!function_exists('auragold_material_issue_stock_has_reference_columns')) {
    function auragold_material_issue_stock_has_reference_columns(mysqli $conn): bool
    {
        if (!function_exists('auragold_tbl_has_column')) {
            if (is_file(__DIR__ . '/auragold_branch_data_scope.php')) {
                require_once __DIR__ . '/auragold_branch_data_scope.php';
            }
        }
        if (!function_exists('auragold_prepare_tbl_stock_reference_columns')) {
            require_once __DIR__ . '/auragold_metal_exchange_stock.php';
        }
        auragold_prepare_tbl_stock_reference_columns($conn);

        return function_exists('auragold_tbl_has_column')
            && auragold_tbl_has_column($conn, 'tbl_stock', 'reference_id')
            && auragold_tbl_has_column($conn, 'tbl_stock', 'reference_type');
    }
}

if (!function_exists('auragold_material_issue_resolve_mi_ids_for_sale_order')) {
    /**
     * Material issue document ids for a sale order (same scope as diamond/stone issued lists).
     *
     * @return list<int>
     */
    function auragold_material_issue_resolve_mi_ids_for_sale_order(
        mysqli $conn,
        int $sale_order_id,
        string $hdr_scope_sql = ''
    ): array {
        if ($sale_order_id < 1 || !function_exists('getList')) {
            return [];
        }
        $scope = $hdr_scope_sql !== ''
            ? preg_replace('/\bbranch_id\b/', 'mi.branch_id', $hdr_scope_sql)
            : auragold_material_issue_scope_sql_for_alias($conn, 'tbl_material_issues', 'mi');
        $soid = (int) $sale_order_id;
        $ids = [];

        $add_id = static function (array $rows) use (&$ids): void {
            if (!is_array($rows)) {
                return;
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $mid = (int) ($row['id'] ?? $row['voucher_id'] ?? 0);
                if ($mid > 0) {
                    $ids[$mid] = true;
                }
            }
        };

        // Same MI ids as diamond issued list (if diamonds show on receive, these ids must exist).
        $add_id(getList(
            'SELECT DISTINCT ds.voucher_id AS id FROM tbl_voucher_diamond_stock_issue ds'
            . ' INNER JOIN tbl_material_issues mi ON mi.id = ds.voucher_id'
            . " WHERE ds.voucher_kind = 'material_issue' AND mi.sale_order_id = " . $soid . $scope
        ));
        $add_id(getList(
            'SELECT mi.id FROM tbl_material_issues mi WHERE mi.sale_order_id = ' . $soid . $scope . ' ORDER BY mi.id ASC'
        ));
        $add_id(getList(
            'SELECT DISTINCT ds.voucher_id AS id FROM tbl_voucher_stone_stock_issue ds'
            . ' INNER JOIN tbl_material_issues mi ON mi.id = ds.voucher_id'
            . " WHERE ds.voucher_kind = 'material_issue' AND mi.sale_order_id = " . $soid . $scope
        ));

        if ($ids === []) {
            $add_id(getList(
                'SELECT mi.id FROM tbl_material_issues mi WHERE mi.sale_order_id = ' . $soid . ' ORDER BY mi.id ASC'
            ));
            $add_id(getList(
                'SELECT DISTINCT ds.voucher_id AS id FROM tbl_voucher_diamond_stock_issue ds'
                . ' INNER JOIN tbl_material_issues mi ON mi.id = ds.voucher_id'
                . " WHERE ds.voucher_kind = 'material_issue' AND mi.sale_order_id = " . $soid
            ));
        }

        return array_values(array_map('intval', array_keys($ids)));
    }
}

if (!function_exists('auragold_material_issue_me_weight_sql_exprs')) {
    /**
     * @return array{0:string,1:string}
     */
    function auragold_material_issue_me_weight_sql_exprs(mysqli $conn): array
    {
        $wt_expr = 'CASE WHEN ABS(s.opening_weight) > 0.00005 THEN ABS(s.opening_weight) ELSE ABS(COALESCE(s.current_weight, 0)) END';
        $has_final = function_exists('auragold_tbl_has_column')
            && auragold_tbl_has_column($conn, 'tbl_stock', 'final_weight');
        $pure_expr = $has_final
            ? ('CASE WHEN ABS(s.final_weight) > 0.00005 THEN ABS(s.final_weight) ELSE ' . $wt_expr . ' END')
            : $wt_expr;

        return [$wt_expr, $pure_expr];
    }
}

if (!function_exists('auragold_material_issue_fetch_me_stock_rows_for_mi_ids')) {
    /**
     * MI metal-exchange stock rows (same filter as material history stock lines).
     *
     * @param list<int> $mi_ids
     * @return list<array<string, mixed>>
     */
    function auragold_material_issue_fetch_me_stock_rows_for_mi_ids(mysqli $conn, array $mi_ids): array
    {
        if ($mi_ids === [] || !function_exists('getList')) {
            return [];
        }
        if (!auragold_material_issue_stock_has_reference_columns($conn)) {
            return [];
        }
        $in = implode(',', array_map('intval', $mi_ids));
        [$wt_expr, $pure_expr] = auragold_material_issue_me_weight_sql_exprs($conn);
        $ref_me_esc = mysqli_real_escape_string($conn, 'material_issue_metal_exchange');

        return getList(
            'SELECT s.id AS issue_stock_id, s.barcode, s.metal_id, s.product_characteristic_id,'
            . " $wt_expr AS issued_gross, $pure_expr AS issued_pure,"
            . ' s.opening_purity, s.current_weight,'
            . ' s.reference_id AS material_issue_id, p.name AS product_name,'
            . ' m.display_name AS metal_name, m.system_name AS metal_system,'
            . " 'material_issue' AS me_source"
            . ' FROM tbl_stock s'
            . ' LEFT JOIN tbl_products p ON p.id = s.product_id'
            . ' LEFT JOIN tbl_metal m ON m.id = s.metal_id'
            . " WHERE s.reference_type = '$ref_me_esc'"
            . " AND s.reference_id IN ($in)"
            . ' AND s.status = 1'
            . ' ORDER BY s.reference_id ASC, s.id ASC'
        ) ?: [];
    }
}

if (!function_exists('auragold_material_issue_fetch_me_stock_mislinked_for_sale_order')) {
    /**
     * Legacy rows where ME stock was saved with sale_order_id or jobwork_order_id as reference_id.
     *
     * @param list<int> $mi_ids
     * @return list<array<string, mixed>>
     */
    function auragold_material_issue_fetch_me_stock_mislinked_for_sale_order(
        mysqli $conn,
        int $sale_order_id,
        array $mi_ids
    ): array {
        if ($sale_order_id < 1 || !function_exists('getList') || !auragold_material_issue_stock_has_reference_columns($conn)) {
            return [];
        }
        $soid = (int) $sale_order_id;
        $default_mi = $mi_ids !== [] ? (int) $mi_ids[0] : 0;
        [$wt_expr, $pure_expr] = auragold_material_issue_me_weight_sql_exprs($conn);
        $ref_me_esc = mysqli_real_escape_string($conn, 'material_issue_metal_exchange');
        $ref_ids = [$soid];
        $t_jwo = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_orders'");
        if ($t_jwo && mysqli_num_rows($t_jwo) > 0) {
            mysqli_free_result($t_jwo);
            $jwo_rows = getList('SELECT id FROM tbl_jobwork_orders WHERE sale_order_id = ' . $soid);
            if (is_array($jwo_rows)) {
                foreach ($jwo_rows as $jr) {
                    $jid = (int) ($jr['id'] ?? 0);
                    if ($jid > 0) {
                        $ref_ids[] = $jid;
                    }
                }
            }
        } elseif ($t_jwo) {
            mysqli_free_result($t_jwo);
        }
        $ref_ids = array_values(array_unique(array_filter(array_map('intval', $ref_ids))));
        if ($ref_ids === []) {
            return [];
        }
        $in_ref = implode(',', $ref_ids);
        $rows = getList(
            'SELECT s.id AS issue_stock_id, s.barcode, s.metal_id, s.product_characteristic_id,'
            . " $wt_expr AS issued_gross, $pure_expr AS issued_pure,"
            . ' s.opening_purity, s.current_weight,'
            . ' s.reference_id AS material_issue_id, p.name AS product_name,'
            . ' m.display_name AS metal_name, m.system_name AS metal_system,'
            . " 'material_issue' AS me_source"
            . ' FROM tbl_stock s'
            . ' LEFT JOIN tbl_products p ON p.id = s.product_id'
            . ' LEFT JOIN tbl_metal m ON m.id = s.metal_id'
            . " WHERE s.reference_type = '$ref_me_esc'"
            . " AND s.reference_id IN ($in_ref)"
            . ' AND s.status = 1'
            . ' ORDER BY s.id ASC'
        );
        if (!is_array($rows) || $rows === []) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rid = (int) ($row['material_issue_id'] ?? 0);
            if ($mi_ids !== [] && in_array($rid, $mi_ids, true)) {
                continue;
            }
            if ($rid === $soid || $default_mi > 0) {
                $row['material_issue_id'] = $default_mi > 0 ? $default_mi : $rid;
            }
            $out[] = $row;
        }

        return $out;
    }
}

if (!function_exists('auragold_material_issue_fetch_me_rows_from_journal')) {
    /**
     * @param list<int> $mi_ids
     * @return list<array<string, mixed>>
     */
    function auragold_material_issue_fetch_me_rows_from_journal(mysqli $conn, array $mi_ids): array
    {
        if ($mi_ids === [] || !function_exists('getList')) {
            return [];
        }
        $t_sj = @mysqli_query($conn, 'SHOW TABLES LIKE \'tbl_stock_journal\'');
        if (!$t_sj || mysqli_num_rows($t_sj) < 1) {
            if ($t_sj) {
                mysqli_free_result($t_sj);
            }

            return [];
        }
        mysqli_free_result($t_sj);
        $mi_in = implode(',', array_map('intval', $mi_ids));
        $pfx_esc = mysqli_real_escape_string($conn, 'MI-ME-');
        $rows_j = getList(
            'SELECT sj.id AS journal_id, sj.barcode, sj.metal_id, sj.product_characteristic_id,'
            . ' sj.gross_weight AS issued_gross, COALESCE(sj.pure_weight, sj.purity_weight, sj.gross_weight) AS issued_pure,'
            . ' sj.purity, sj.product_name, sj.metal_type AS metal_name,'
            . ' mi.id AS material_issue_id,'
            . " 'journal' AS me_source"
            . ' FROM tbl_stock_journal sj'
            . ' INNER JOIN tbl_material_issues mi ON sj.sj_invoice_no LIKE CONCAT(\''
            . $pfx_esc
            . '\', mi.id, \'-%\')'
            . ' WHERE mi.id IN (' . $mi_in . ')'
            . ' ORDER BY sj.id ASC'
        );
        if (!is_array($rows_j) || $rows_j === []) {
            return [];
        }
        $out = [];
        foreach ($rows_j as $jr) {
            if (!is_array($jr)) {
                continue;
            }
            $bc = trim((string) ($jr['barcode'] ?? ''));
            $stock_id = 0;
            if ($bc !== '' && auragold_material_issue_stock_has_reference_columns($conn)) {
                $bc_esc = mysqli_real_escape_string($conn, $bc);
                $mi_id = (int) ($jr['material_issue_id'] ?? 0);
                $st = getRecord(
                    "SELECT id FROM tbl_stock WHERE barcode = '$bc_esc' AND status = 1"
                    . " AND reference_type = 'material_issue_metal_exchange'"
                    . ' AND reference_id = ' . $mi_id
                    . ' LIMIT 1'
                );
                $stock_id = (int) ($st['id'] ?? 0);
            }
            $jr['issue_stock_id'] = $stock_id > 0 ? $stock_id : (int) ($jr['journal_id'] ?? 0);
            $jr['opening_purity'] = (float) ($jr['purity'] ?? 0) / 100;
            if ((float) ($jr['issued_gross'] ?? 0) <= 1e-8) {
                continue;
            }
            $out[] = $jr;
        }

        return $out;
    }
}

if (!function_exists('auragold_material_issue_me_rows_from_history_map')) {
    /**
     * Same Metal exchange lines as Material history modal (jwm_batch_material_voucher_lines_map).
     *
     * @param list<int> $mi_ids
     * @return list<array<string, mixed>>
     */
    function auragold_material_issue_me_rows_from_history_map(mysqli $conn, array $mi_ids): array
    {
        if ($mi_ids === [] || !function_exists('getList')) {
            return [];
        }
        if (!is_file(__DIR__ . '/jwm_material_links.php')) {
            return [];
        }
        require_once __DIR__ . '/jwm_material_links.php';
        if (!function_exists('jwm_batch_material_voucher_lines_map')) {
            return [];
        }
        $map = jwm_batch_material_voucher_lines_map($conn, $mi_ids, 'issue', false, 0);
        $out = [];
        $seen_bc_mi = [];
        foreach ($mi_ids as $mid) {
            $mid = (int) $mid;
            if ($mid < 1) {
                continue;
            }
            foreach ($map[$mid] ?? [] as $ln) {
                if (!is_array($ln)) {
                    continue;
                }
                if (trim((string) ($ln['category'] ?? '')) !== 'Metal exchange') {
                    continue;
                }
                $bc = trim((string) ($ln['barcode'] ?? ''));
                if ($bc === '' || $bc === '—') {
                    continue;
                }
                $wt_raw = trim((string) ($ln['wt'] ?? ''));
                $wt = is_numeric(str_replace(',', '', $wt_raw)) ? (float) str_replace(',', '', $wt_raw) : 0.0;
                if ($wt <= 1e-8) {
                    continue;
                }
                $dedupe_key = strtolower($bc) . '|' . $mid . '|' . number_format($wt, 4, '.', '');
                if (isset($seen_bc_mi[$dedupe_key])) {
                    continue;
                }
                $seen_bc_mi[$dedupe_key] = true;
                $stock_id = 0;
                $metal_id = 0;
                $pcid = 0;
                $issued_gross = $wt;
                $issued_pure = $wt;
                $purity = 0.0;
                if (auragold_material_issue_stock_has_reference_columns($conn)) {
                    $bc_esc = mysqli_real_escape_string($conn, $bc);
                    $st_rows = getList(
                        'SELECT id, metal_id, product_characteristic_id, opening_weight, current_weight, final_weight, opening_purity'
                        . ' FROM tbl_stock WHERE status = 1 AND barcode = \''
                        . $bc_esc
                        . '\' AND reference_id = '
                        . $mid
                        . " AND reference_type IN ('material_issue_metal_exchange', 'material_issue')"
                        . ' ORDER BY id ASC'
                    );
                    if (is_array($st_rows)) {
                        foreach ($st_rows as $st) {
                            if (!is_array($st)) {
                                continue;
                            }
                            $ow = (float) ($st['opening_weight'] ?? 0);
                            $cw = (float) ($st['current_weight'] ?? 0);
                            $sw = abs($ow) > 1e-8 ? abs($ow) : abs($cw);
                            if (count($st_rows) > 1 && abs($sw - $wt) > 0.001 && abs($ow) > 1e-8) {
                                continue;
                            }
                            $stock_id = (int) ($st['id'] ?? 0);
                            $metal_id = (int) ($st['metal_id'] ?? 0);
                            $pcid = (int) ($st['product_characteristic_id'] ?? 0);
                            $issued_gross = $sw > 1e-8 ? $sw : $wt;
                            $fw = (float) ($st['final_weight'] ?? 0);
                            $issued_pure = abs($fw) > 1e-8 ? abs($fw) : $issued_gross;
                            $purity = (float) ($st['opening_purity'] ?? 0);
                            break;
                        }
                    }
                }
                $metal_label = trim((string) ($ln['metal_type'] ?? ''));
                if ($metal_id > 0 && $metal_label === '' && function_exists('getRecord')) {
                    $mr = getRecord(
                        'SELECT COALESCE(display_name, system_name, \'\') AS n FROM tbl_metal WHERE id = '
                        . (int) $metal_id . ' LIMIT 1'
                    );
                    $metal_label = trim((string) ($mr['n'] ?? ''));
                }
                $pname = trim((string) ($ln['product_name'] ?? ''));
                if ($pname === '—') {
                    $pname = '';
                }
                $out[] = [
                    'issue_stock_id' => $stock_id,
                    'material_issue_id' => $mid,
                    'barcode' => $bc,
                    'product_name' => $pname,
                    'metal_id' => $metal_id,
                    'product_characteristic_id' => $pcid,
                    'metal_name' => $metal_label !== '' && $metal_label !== '—' ? $metal_label : 'Gold',
                    'issued_gross' => $issued_gross,
                    'issued_pure' => $issued_pure,
                    'issued_weight' => $issued_gross,
                    'opening_purity' => $purity,
                    'me_source' => 'material_history',
                    'issue_source_label' => 'Material issue',
                ];
            }
        }

        return $out;
    }
}

if (!function_exists('auragold_material_issue_list_metal_exchange_rows_for_sale_order')) {
    /**
     * Metal exchange available to receive: MI-ME stock, Sale Order ME stock, and stock-journal fallback.
     *
     * @return list<array<string, mixed>>
     */
    function auragold_material_issue_list_metal_exchange_rows_for_sale_order(
        mysqli $conn,
        int $sale_order_id,
        string $hdr_scope_sql = '',
        bool $try_sync_mi_from_so_payments = true
    ): array {
        if ($sale_order_id < 1 || !function_exists('getList')) {
            return [];
        }
        if (!function_exists('auragold_prepare_tbl_stock_reference_columns')) {
            require_once __DIR__ . '/auragold_metal_exchange_stock.php';
        }
        $has_ref = auragold_material_issue_stock_has_reference_columns($conn);
        if ($try_sync_mi_from_so_payments) {
            auragold_material_issue_sync_me_stock_on_material_issue($conn, $sale_order_id, $hdr_scope_sql);
        }
        $scope = $hdr_scope_sql !== ''
            ? preg_replace('/\bbranch_id\b/', 'mi.branch_id', $hdr_scope_sql)
            : auragold_material_issue_scope_sql_for_alias($conn, 'tbl_material_issues', 'mi');
        $soid = (int) $sale_order_id;
        $mi_ids = auragold_material_issue_resolve_mi_ids_for_sale_order($conn, $soid, $hdr_scope_sql);
        $rows_mi = [];
        if ($has_ref && $mi_ids !== []) {
            $rows_mi = auragold_material_issue_fetch_me_stock_rows_for_mi_ids($conn, $mi_ids);
            if ($rows_mi === []) {
                $rows_mi = auragold_material_issue_fetch_me_stock_mislinked_for_sale_order($conn, $soid, $mi_ids);
            }
        }
        $rows_so = getList(
            'SELECT s.id AS issue_stock_id, s.barcode, s.metal_id, s.product_characteristic_id,'
            . ' s.opening_weight AS issued_gross, s.final_weight AS issued_pure, s.opening_purity,'
            . ' 0 AS material_issue_id, p.name AS product_name,'
            . ' m.display_name AS metal_name, m.system_name AS metal_system,'
            . " 'sale_order' AS me_source"
            . ' FROM tbl_stock s'
            . " LEFT JOIN tbl_products p ON p.id = s.product_id"
            . ' LEFT JOIN tbl_metal m ON m.id = s.metal_id'
            . " WHERE s.reference_type = 'sale_order_metal_exchange'"
            . " AND s.reference_id = $soid AND s.status = 1"
            . ' ORDER BY s.id ASC'
        );
        $rows = is_array($rows_mi) ? $rows_mi : [];
        if (is_array($rows_so)) {
            foreach ($rows_so as $so_row) {
                if (!is_array($so_row)) {
                    continue;
                }
                $sid = (int) ($so_row['issue_stock_id'] ?? 0);
                if ($sid < 1) {
                    continue;
                }
                $src = function_exists('getRecord')
                    ? getRecord('SELECT source_stock_id FROM tbl_stock WHERE id = ' . $sid . ' AND status = 1 LIMIT 1')
                    : null;
                $src_id = (int) ($src['source_stock_id'] ?? 0);
                if ($src_id > 0) {
                    $src_type = getRecord('SELECT reference_type, reference_id FROM tbl_stock WHERE id = ' . $src_id . ' LIMIT 1');
                    if ($src_type && (string) ($src_type['reference_type'] ?? '') === 'jobwork_order_metal_exchange') {
                        $rows[] = $so_row;
                    }
                }
            }
        }
        $seen_stock = [];
        $deduped = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $sid = (int) ($row['issue_stock_id'] ?? 0);
            if ($sid > 0 && isset($seen_stock[$sid])) {
                continue;
            }
            if ($sid > 0) {
                $seen_stock[$sid] = true;
            }
            $deduped[] = $row;
        }
        if ($deduped === [] && $mi_ids !== []) {
            $deduped = auragold_material_issue_fetch_me_rows_from_journal($conn, $mi_ids);
        }
        if ($deduped === [] && $mi_ids === []) {
            $mi_ids = auragold_material_issue_resolve_mi_ids_for_sale_order($conn, $soid, '');
            if ($mi_ids !== []) {
                $deduped = auragold_material_issue_merge_me_row_lists(
                    [],
                    auragold_material_issue_fetch_me_stock_rows_for_mi_ids($conn, $mi_ids)
                );
                if ($deduped === []) {
                    $deduped = auragold_material_issue_fetch_me_rows_from_journal($conn, $mi_ids);
                }
            }
        }
        $pay_rows = [];
        $t_pay = @mysqli_query($conn, 'SHOW TABLES LIKE \'tbl_sale_order_payments\'');
        if ($t_pay && mysqli_num_rows($t_pay) > 0) {
            mysqli_free_result($t_pay);
            $pay_rows = getList('SELECT * FROM tbl_sale_order_payments WHERE order_id = ' . (int) $sale_order_id . ' ORDER BY id ASC');
        } elseif ($t_pay) {
            mysqli_free_result($t_pay);
        }
        if (is_array($pay_rows) && $pay_rows !== []) {
            $from_pay = auragold_material_issue_metal_exchange_rows_from_payments($conn, $sale_order_id, $pay_rows);
            $deduped = auragold_material_issue_merge_me_row_lists($deduped, $from_pay);
        }

        foreach ($mi_ids as $mi_id_loop) {
                $mi_id_loop = (int) $mi_id_loop;
                if ($mi_id_loop < 1) {
                    continue;
                }
                $syn_pays = auragold_material_issue_payments_from_mi_stock($conn, $mi_id_loop, true);
                if ($syn_pays === []) {
                    continue;
                }
                foreach ($syn_pays as &$sp) {
                    if (!is_array($sp)) {
                        continue;
                    }
                    $sp['material_issue_id'] = $mi_id_loop;
                }
                unset($sp);
                $from_mi_syn = auragold_material_issue_metal_exchange_rows_from_payments($conn, $soid, $syn_pays);
                if ($from_mi_syn !== []) {
                    $deduped = auragold_material_issue_merge_me_row_lists($deduped, $from_mi_syn);
                }
        }

        if ($mi_ids !== []) {
            $deduped = auragold_material_issue_merge_me_row_lists(
                $deduped,
                auragold_material_issue_me_rows_from_history_map($conn, $mi_ids)
            );
        }

        $enriched = auragold_material_issue_enrich_issued_metal_exchange_rows($conn, $deduped);
        foreach ($enriched as &$ln) {
            if (!empty($ln['issue_source_label'])) {
                continue;
            }
            $src = (string) ($ln['me_source'] ?? '');
            if ($src === 'sale_order' || $src === 'sale_order_payment') {
                $ln['issue_source_label'] = 'Sale order';
            } elseif ($src === 'journal') {
                $ln['issue_source_label'] = 'Material issue (history)';
            } else {
                $ln['issue_source_label'] = 'Material issue';
            }
        }
        unset($ln);

        return $enriched;
    }
}
