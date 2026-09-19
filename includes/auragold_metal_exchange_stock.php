<?php

/**
 * Shared Metal Exchange → tbl_stock (new barcode) + stock history audit.
 * Used by sale order, sale invoice, purchase vouchers, etc.
 */

if (!function_exists('auragold_payment_merge_stored_details')) {
    /**
     * Merge JSON from payment_details column into the payment row (edit/save round-trip).
     *
     * @param array<string, mixed> $payment
     *
     * @return array<string, mixed>
     */
    function auragold_payment_merge_stored_details(array $payment): array
    {
        $pd_raw = $payment['payment_details'] ?? '';
        if (is_string($pd_raw) && $pd_raw !== '') {
            $j = json_decode($pd_raw, true);
            if (is_array($j)) {
                return array_merge($payment, $j);
            }
        }

        return $payment;
    }
}

if (!function_exists('auragold_metal_exchange_resolve')) {
    /**
     * Detect metal-exchange payment line and resolved gross/pure weights.
     *
     * @return array{is_me: bool, gross: float, pure: float, metal_id: int, qty: float, is_silver: bool}
     */
    function auragold_metal_exchange_resolve($conn, array $payment): array
    {
        $payment = auragold_payment_merge_stored_details($payment);
        $dep = strtolower(trim((string) ($payment['deposit_into'] ?? '')));
        $pt = strtolower(trim((string) ($payment['payment_type'] ?? '')));
        $ui_type = strtolower(trim((string) ($payment['type'] ?? '')));
        $is_me = ($dep === 'metal exchange')
            || ($ui_type === 'metal-exchange' || $ui_type === 'metal_exchange')
            || (strpos($pt, 'm. exch') !== false)
            || (strpos($pt, 'metal') !== false && strpos($pt, 'exch') !== false)
            || ($pt === 'metal_exchange')
            || (strpos($pt, 'metal-exchange') !== false);
        $empty = ['is_me' => false, 'gross' => 0.0, 'pure' => 0.0, 'metal_id' => 0, 'qty' => 1.0, 'is_silver' => false];
        if (!$is_me) {
            return $empty;
        }
        $qty = (float) ($payment['quantity'] ?? 1);
        if ($qty < 1e-8) {
            $qty = 1.0;
        }
        $gross = (float) ($payment['metal_exchange_gross_wt'] ?? 0) * $qty;
        if ($gross <= 1e-8) {
            $gross = (float) ($payment['gross_weight'] ?? $payment['gross_wt'] ?? $payment['net_weight'] ?? $payment['weight'] ?? 0) * $qty;
        }
        if ($gross <= 1e-8 && $qty > 0) {
            $gross = $qty;
        }
        $pure = (float) ($payment['metal_exchange_purity_wt'] ?? 0) * $qty;
        if ($pure <= 1e-8) {
            $pure = (float) ($payment['purity_weight'] ?? $payment['pure_wt'] ?? $payment['purity_wt'] ?? 0) * $qty;
        }
        $pur_num = (float) ($payment['purity_carat'] ?? $payment['purity'] ?? 0);
        if ($pure <= 1e-8 && $gross > 1e-8 && $pur_num > 0) {
            if ($pur_num <= 1) {
                $pure = $gross * $pur_num;
            } elseif ($pur_num <= 100) {
                $pure = $gross * ($pur_num / 100);
            } else {
                $pure = $gross * ($pur_num / 1000);
            }
        }
        if ($pure <= 1e-8 && $gross > 1e-8) {
            $pure = $gross;
        }
        $mid = (int) ($payment['metal_exchange_metal_id'] ?? $payment['metal_id'] ?? 0);
        $nm = '';
        if ($mid > 0) {
            $mr = getRecord("SELECT LOWER(TRIM(COALESCE(display_name, system_name, ''))) AS n FROM tbl_metal WHERE id = $mid LIMIT 1");
            $nm = strtolower(trim((string) ($mr['n'] ?? '')));
        }
        $is_silver = strpos($nm, 'silver') !== false;

        return [
            'is_me' => true,
            'gross' => $gross,
            'pure' => $pure,
            'metal_id' => $mid,
            'qty' => $qty,
            'is_silver' => $is_silver,
        ];
    }
}

if (!function_exists('auragold_prepare_tbl_stock_reference_columns')) {
    /** Ensure tbl_stock.reference_id / reference_type exist (for tagging metal-exchange inward rows). */
    function auragold_prepare_tbl_stock_reference_columns($conn): bool
    {
        $t_stock = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_stock'");
        if (!$t_stock || mysqli_num_rows($t_stock) === 0) {
            if ($t_stock) {
                mysqli_free_result($t_stock);
            }

            return false;
        }
        mysqli_free_result($t_stock);

        $tbl_stock_has_reference = false;
        $__stk_ref = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock WHERE Field IN ('reference_id','reference_type')");
        if ($__stk_ref && mysqli_num_rows($__stk_ref) >= 2) {
            $tbl_stock_has_reference = true;
        }
        if ($__stk_ref) {
            mysqli_free_result($__stk_ref);
        }
        if (!$tbl_stock_has_reference) {
            @mysqli_query($conn, "ALTER TABLE `tbl_stock` ADD COLUMN `reference_id` INT NULL DEFAULT NULL AFTER `transaction_date`");
            @mysqli_query($conn, "ALTER TABLE `tbl_stock` ADD COLUMN `reference_type` VARCHAR(50) NULL DEFAULT NULL AFTER `reference_id`");
            $__stk_ref2 = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock WHERE Field IN ('reference_id','reference_type')");
            if ($__stk_ref2 && mysqli_num_rows($__stk_ref2) >= 2) {
                $tbl_stock_has_reference = true;
            }
            if ($__stk_ref2) {
                mysqli_free_result($__stk_ref2);
            }
        }

        return $tbl_stock_has_reference;
    }
}

if (!function_exists('auragold_metal_exchange_reference_types_sql_list_safe')) {
    /** Same list without relying on $conn global — escape via mysqli when provided */
    function auragold_metal_exchange_reference_types_sql_list_safe(?mysqli $conn): string
    {
        $types = [
            'sale_order_metal_exchange',
            'sale_invoice_metal_exchange',
            'sale_quotation_metal_exchange',
            'sale_return_metal_exchange',
            'purchase_invoice_metal_exchange',
            'purchase_quotation_metal_exchange',
            'purchase_return_metal_exchange',
            'payment_voucher_metal_exchange',
            'receipt_voucher_metal_exchange',
            'advance_payment_metal_exchange',
            'old_jewelry_scrap_invoice_metal_exchange',
            'material_issue_metal_exchange',
            'material_receive_metal_exchange',
            'jobwork_order_metal_exchange',
            'jobwork_invoice_metal_exchange',
            'old_jewellery_scrap_stock_in_metal_exchange',
            'consignment_in_metal_exchange',
            'consignment_out_metal_exchange',
            'pos_sale_invoice_metal_exchange',
        ];
        $parts = [];
        foreach ($types as $t) {
            $parts[] = $conn ? ("'" . mysqli_real_escape_string($conn, $t) . "'") : ("'" . str_replace("'", "''", $t) . "'");
        }

        return implode(',', $parts);
    }
}

if (!function_exists('auragold_metal_exchange_resolve_characteristic_id')) {
    /**
     * Resolve tbl_product_characteristics.id for a metal-exchange payment line.
     * Accepts characteristic id in metal_exchange_product_id / metal_exchange_characteristic_id,
     * or product_id + metal_id when only catalog product id was stored.
     */
    function auragold_metal_exchange_resolve_characteristic_id(mysqli $conn, array $payment): int
    {
        $p = auragold_payment_merge_stored_details($payment);
        $mid = (int) ($p['metal_exchange_metal_id'] ?? $p['metal_id'] ?? 0);
        $candidates = [];
        foreach (
            [
                'metal_exchange_characteristic_id',
                'metal_exchange_product_id',
                'product_characteristic_id',
                'characteristic_id',
                'product_id',
            ] as $key
        ) {
            $v = (int) ($p[$key] ?? 0);
            if ($v > 0) {
                $candidates[] = $v;
            }
        }
        $candidates = array_values(array_unique($candidates));
        if ($candidates === []) {
            return 0;
        }
        foreach ($candidates as $cid) {
            $row = getRecord(
                'SELECT id, metal_id FROM tbl_product_characteristics WHERE id = ' . (int) $cid
                . ' AND status = 1 LIMIT 1'
            );
            if ($row && !empty($row['id'])) {
                if ($mid > 0 && (int) ($row['metal_id'] ?? 0) !== $mid) {
                    continue;
                }

                return (int) $row['id'];
            }
        }
        foreach ($candidates as $pidGuess) {
            if ($mid > 0) {
                $row = getRecord(
                    'SELECT id FROM tbl_product_characteristics WHERE product_id = ' . (int) $pidGuess
                    . ' AND metal_id = ' . $mid . ' AND status = 1 ORDER BY id DESC LIMIT 1'
                );
                if ($row && !empty($row['id'])) {
                    return (int) $row['id'];
                }
            }
            $row = getRecord(
                'SELECT id FROM tbl_product_characteristics WHERE product_id = ' . (int) $pidGuess
                . ' AND status = 1 ORDER BY id DESC LIMIT 1'
            );
            if ($row && !empty($row['id'])) {
                return (int) $row['id'];
            }
        }

        return 0;
    }
}

if (!function_exists('auragold_payment_is_metal_exchange_inward')) {
    function auragold_payment_is_metal_exchange_inward($conn, array $payment): bool
    {
        $p = auragold_payment_merge_stored_details($payment);
        $r = auragold_metal_exchange_resolve($conn, $p);
        if (!$r['is_me'] || $r['gross'] <= 1e-8) {
            return false;
        }
        $pcid = auragold_metal_exchange_resolve_characteristic_id($conn, $p);
        $mid = (int) ($p['metal_exchange_metal_id'] ?? $p['metal_id'] ?? 0);

        return $pcid > 0 && $mid > 0;
    }
}

if (!function_exists('auragold_should_persist_payment_row_with_metal_exchange')) {
    /** Persist payment row when amount > 0, valid metal exchange inward, or any explicit UI payment line (incl. zero amount). */
    function auragold_should_persist_payment_row_with_metal_exchange($conn, array $payment): bool
    {
        $p = auragold_payment_merge_stored_details($payment);
        $amt = (float) ($p['amount'] ?? 0);
        if ($amt > 0.00001) {
            return true;
        }
        if (auragold_payment_is_metal_exchange_inward($conn, $p)) {
            return true;
        }

        $type = strtolower(trim((string) ($p['type'] ?? '')));
        $pt = strtolower(trim((string) ($p['payment_type'] ?? '')));
        $dep = strtolower(trim((string) ($p['deposit_into'] ?? '')));
        if ($type !== '' || $pt !== '' || $dep !== '') {
            return true;
        }

        return false;
    }
}

if (!function_exists('auragold_metal_exchange_so_source_stock_available')) {
    /** Remaining issue weight on sale_order_metal_exchange stock (min of current vs opening minus prior MI issues). */
    function auragold_metal_exchange_so_source_stock_available(mysqli $conn, int $source_stock_id): float
    {
        if ($source_stock_id < 1 || !function_exists('getRecord')) {
            return 0.0;
        }
        $issued = getRecord(
            "SELECT id, opening_weight, current_weight, reference_type FROM tbl_stock WHERE id = "
            . (int) $source_stock_id
            . " AND reference_type = 'sale_order_metal_exchange' AND status = 1 LIMIT 1"
        );
        if (!$issued || !is_array($issued)) {
            return 0.0;
        }
        $cur = (float) ($issued['current_weight'] ?? 0);
        $open = (float) ($issued['opening_weight'] ?? 0);
        $issued_out = 0.0;
        if (is_file(__DIR__ . '/auragold_material_issue_rows_for_sale_order.php')) {
            require_once __DIR__ . '/auragold_material_issue_rows_for_sale_order.php';
            if (function_exists('auragold_metal_exchange_issued_sum_for_source_stock')) {
                $issued_out = auragold_metal_exchange_issued_sum_for_source_stock($conn, $source_stock_id);
            }
        }
        if ($cur > 1e-8) {
            $from_open = $open > 1e-8 ? max(0.0, $open - $issued_out) : $cur;

            return max(0.0, min($cur, $from_open));
        }

        return max(0.0, $open - $issued_out);
    }
}

if (!function_exists('auragold_metal_exchange_payment_strip_stored_weight_overrides')) {
    /** Prevent payment_details JSON from overriding explicit issue weights on merge. */
    function auragold_metal_exchange_payment_strip_stored_weight_overrides(array $payment): array
    {
        unset($payment['payment_details']);
        foreach (
            [
                'gross_weight',
                'gross_wt',
                'net_weight',
                'weight',
                'pure_wt',
                'purity_weight',
            ] as $key
        ) {
            unset($payment[$key]);
        }

        return $payment;
    }
}

if (!function_exists('auragold_jwo_metal_exchange_strip_receive_source_ids')) {
    /**
     * Job work order metal exchange = new inward stock (product + weight), not receive-from-issued-line.
     *
     * @param array<string, mixed> $payment
     * @return array<string, mixed>
     */
    function auragold_jwo_metal_exchange_strip_receive_source_ids(array $payment): array
    {
        $p = function_exists('auragold_payment_merge_stored_details')
            ? auragold_payment_merge_stored_details($payment)
            : $payment;
        unset(
            $p['metal_exchange_source_stock_id'],
            $p['source_issue_stock_id'],
            $p['source_stock_id']
        );
        unset($p['payment_details']);

        return $p;
    }
}

if (!function_exists('auragold_metal_exchange_enrich_payment_from_issue_stock')) {
    /**
     * Fill metal / product ids from issued stock when Material Receive queues ME by barcode only.
     */
    function auragold_metal_exchange_enrich_payment_from_issue_stock(mysqli $conn, array $payment): array
    {
        $p = auragold_payment_merge_stored_details($payment);
        $mid = (int) ($p['metal_exchange_metal_id'] ?? $p['metal_id'] ?? 0);
        $pcid = (int) ($p['metal_exchange_product_id'] ?? $p['product_characteristic_id'] ?? 0);
        $src_stock_id = (int) ($p['metal_exchange_source_stock_id'] ?? $p['source_issue_stock_id'] ?? 0);
        if ($src_stock_id < 1) {
            return $p;
        }
        if ($mid >= 1 && $pcid >= 1) {
            return $p;
        }
        $st = getRecord(
            'SELECT metal_id, product_characteristic_id, product_id FROM tbl_stock WHERE id = '
            . $src_stock_id
            . ' AND status = 1 LIMIT 1'
        );
        if (!is_array($st)) {
            return $p;
        }
        if ($mid < 1 && (int) ($st['metal_id'] ?? 0) > 0) {
            $p['metal_exchange_metal_id'] = (int) $st['metal_id'];
        }
        if ($pcid < 1 && (int) ($st['product_characteristic_id'] ?? 0) > 0) {
            $p['metal_exchange_product_id'] = (int) $st['product_characteristic_id'];
        }

        return $p;
    }
}

if (!function_exists('auragold_validate_metal_exchange_for_stock')) {
    function auragold_validate_metal_exchange_for_stock($conn, array $payment): void
    {
        if (!auragold_payment_is_metal_exchange_inward($conn, $payment)) {
            return;
        }
        $p = auragold_metal_exchange_enrich_payment_from_issue_stock($conn, $payment);
        $mid = (int) ($p['metal_exchange_metal_id'] ?? $p['metal_id'] ?? 0);
        $pcid = auragold_metal_exchange_resolve_characteristic_id($conn, $p);
        if ($pcid < 1 || $mid < 1) {
            throw new Exception('Metal exchange: select metal, product from the list, and enter gross weight.');
        }
        $row = getRecord(
            'SELECT pc.id FROM tbl_product_characteristics pc '
            . 'INNER JOIN tbl_products p ON p.id = pc.product_id '
            . "WHERE pc.id = $pcid AND pc.metal_id = $mid AND p.status = 1 AND pc.status = 1 LIMIT 1"
        );
        if (!$row) {
            throw new Exception('Metal exchange: choose a valid product for the selected metal (from the search list).');
        }
        $src_stock_id = (int) ($p['metal_exchange_source_stock_id'] ?? $p['source_issue_stock_id'] ?? 0);
        if ($src_stock_id > 0 && is_file(__DIR__ . '/auragold_material_issue_rows_for_sale_order.php')) {
            require_once __DIR__ . '/auragold_material_issue_rows_for_sale_order.php';
            $issued = getRecord(
                "SELECT id, opening_weight, current_weight, reference_type FROM tbl_stock WHERE id = $src_stock_id"
                . " AND reference_type IN ('material_issue_metal_exchange', 'sale_order_metal_exchange', 'jobwork_order_metal_exchange')"
                . ' AND status = 1 LIMIT 1'
            );
            if (!$issued) {
                throw new Exception('Metal exchange receive: issued metal line not found.');
            }
            $src_ref = trim((string) ($issued['reference_type'] ?? ''));
            if ($src_ref === 'jobwork_order_metal_exchange') {
                $wt_gross = (float) auragold_metal_exchange_resolve($conn, $p)['gross'];
                $avail_jwo = (float) ($issued['current_weight'] ?? 0);
                if ($avail_jwo <= 1e-8) {
                    $avail_jwo = (float) ($issued['opening_weight'] ?? 0);
                }
                if ($wt_gross > $avail_jwo + 0.0001) {
                    throw new Exception(
                        'Metal exchange issue weight (' . round($wt_gross, 3) . ') exceeds job work metal stock ('
                        . round(max(0, $avail_jwo), 3) . ').'
                    );
                }

                return;
            }
            $wt_gross = (float) auragold_metal_exchange_resolve($conn, $p)['gross'];

            if ($src_ref === 'sale_order_metal_exchange') {
                $avail = auragold_metal_exchange_so_source_stock_available($conn, $src_stock_id);
                if ($wt_gross > $avail + 0.0001) {
                    throw new Exception(
                        'Metal exchange issue weight (' . round($wt_gross, 3) . ') exceeds available stock ('
                        . round(max(0, $avail), 3) . ').'
                    );
                }

                return;
            }

            $already = auragold_material_issue_me_received_sum_for_source_stock($conn, $src_stock_id);
            $balance = (float) ($issued['opening_weight'] ?? 0) - $already;
            if ($wt_gross > $balance + 0.0001) {
                throw new Exception(
                    'Metal exchange receive weight (' . round($wt_gross, 3) . ') exceeds balance (' . round(max(0, $balance), 3) . ').'
                );
            }
        }
    }
}

if (!function_exists('auragold_metal_exchange_sj_prefix_and_audit_src')) {
    /**
     * @return array{0: string, 1: string}|null [sj_invoice_prefix, audit_src comment tag]
     */
    function auragold_metal_exchange_sj_prefix_and_audit_src(string $reference_type): ?array
    {
        static $map = [
            'sale_order_metal_exchange' => ['SO-ME-', 'so_me'],
            'sale_invoice_metal_exchange' => ['SI-ME-', 'si_me'],
            'sale_quotation_metal_exchange' => ['SQ-ME-', 'sq_me'],
            'sale_return_metal_exchange' => ['SR-ME-', 'sr_me'],
            'pos_sale_invoice_metal_exchange' => ['POSI-ME-', 'posi_me'],
            'purchase_invoice_metal_exchange' => ['PI-ME-', 'pi_me'],
            'purchase_quotation_metal_exchange' => ['PQ-ME-', 'pq_me'],
            'purchase_return_metal_exchange' => ['PR-ME-', 'pr_me'],
            'payment_voucher_metal_exchange' => ['PV-ME-', 'pv_me'],
            'receipt_voucher_metal_exchange' => ['RV-ME-', 'rv_me'],
            'advance_payment_metal_exchange' => ['AP-ME-', 'ap_me'],
            'old_jewelry_scrap_invoice_metal_exchange' => ['OJSI-ME-', 'ojsi_me'],
            'old_jewellery_scrap_stock_in_metal_exchange' => ['OJSTK-ME-', 'ojstk_me'],
            'material_issue_metal_exchange' => ['MI-ME-', 'mi_me'],
            'material_receive_metal_exchange' => ['MR-ME-', 'mr_me'],
            'jobwork_order_metal_exchange' => ['JWO-ME-', 'jwo_me'],
            'jobwork_invoice_metal_exchange' => ['JWI-ME-', 'jwi_me'],
            'consignment_in_metal_exchange' => ['CIN-ME-', 'cin_me'],
            'consignment_out_metal_exchange' => ['COU-ME-', 'cou_me'],
        ];

        return $map[$reference_type] ?? null;
    }
}

if (!function_exists('auragold_metal_exchange_delete_journal_for_reference')) {
    /** Remove prior metal-exchange stock journal rows before re-posting (sj_invoice_no is UNIQUE). */
    function auragold_metal_exchange_delete_journal_for_reference(mysqli $conn, string $reference_type, int $reference_id): void
    {
        if ($reference_id < 1 || trim($reference_type) === '') {
            return;
        }
        $meta = auragold_metal_exchange_sj_prefix_and_audit_src($reference_type);
        if ($meta === null) {
            return;
        }
        $t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_stock_journal'");
        if (!$t || mysqli_num_rows($t) === 0) {
            if ($t) {
                mysqli_free_result($t);
            }

            return;
        }
        mysqli_free_result($t);

        [$prefix, $audit_src] = $meta;
        $rid = (int) $reference_id;
        $pfx_esc = mysqli_real_escape_string($conn, $prefix . $rid . '-');
        mysqli_query($conn, "DELETE FROM tbl_stock_journal WHERE sj_invoice_no LIKE '{$pfx_esc}%'");

        $src_esc = mysqli_real_escape_string($conn, $audit_src);
        mysqli_query($conn, "DELETE FROM tbl_stock_journal WHERE comment LIKE 'auragold_doc|src={$src_esc}|rid={$rid}|%'");
    }
}

if (!function_exists('auragold_metal_exchange_delete_stock_for_reference')) {
    function auragold_metal_exchange_delete_stock_for_reference(mysqli $conn, string $reference_type, int $reference_id): void
    {
        if ($reference_id < 1 || trim($reference_type) === '') {
            return;
        }
        $rt = mysqli_real_escape_string($conn, $reference_type);
        mysqli_query($conn, "DELETE FROM tbl_stock WHERE reference_type = '$rt' AND reference_id = " . (int) $reference_id);
    }
}

if (!function_exists('auragold_metal_exchange_document_init')) {
    /**
     * Call before re-inserting payments on document save (creates reference columns if needed; deletes prior ME stock on edit).
     */
    function auragold_metal_exchange_document_init(mysqli $conn, bool $is_update, int $doc_id, string $reference_type): bool
    {
        $has_ref = auragold_prepare_tbl_stock_reference_columns($conn);
        if ($is_update && $doc_id > 0) {
            auragold_metal_exchange_delete_journal_for_reference($conn, $reference_type, $doc_id);
            if ($has_ref) {
                auragold_metal_exchange_delete_stock_for_reference($conn, $reference_type, $doc_id);
            }
        }

        return $has_ref;
    }
}

if (!function_exists('auragold_metal_exchange_default_branch_id')) {
    function auragold_metal_exchange_default_branch_id(): int
    {
        $bid = (int) ($_SESSION['working_branch_id'] ?? $_SESSION['branch_id'] ?? 0);

        return $bid > 0 ? $bid : 1;
    }
}

if (!function_exists('auragold_metal_exchange_stock_matches_payment_product')) {
    function auragold_metal_exchange_stock_matches_payment_product(array $stock_row, int $pcid, int $mid): bool
    {
        $st_pcid = (int) ($stock_row['product_characteristic_id'] ?? 0);
        $st_mid = (int) ($stock_row['metal_id'] ?? 0);

        return $st_pcid === $pcid && ($st_mid < 1 || $mid < 1 || $st_mid === $mid);
    }
}

if (!function_exists('auragold_metal_exchange_barcode_plan_from_payment')) {
    /**
     * @return array{barcode: string, reuse_stock_id: int}
     */
    function auragold_metal_exchange_barcode_plan_from_payment(
        mysqli $conn,
        array $p,
        string $reference_type,
        int $reference_id,
        int $pid,
        int $pcid,
        int $mid,
        int $branch_id,
        array $reserved_barcodes = [],
        array $reserved_stock_ids = []
    ): array {
        $empty = ['barcode' => '', 'reuse_stock_id' => 0];
        $payment_stock_id = (int) ($p['mi_stock_id'] ?? $p['metal_exchange_stock_id'] ?? 0);
        $reserved_stock_ids = is_array($reserved_stock_ids) ? $reserved_stock_ids : [];
        $user_bc = trim((string) ($p['metal_exchange_item_code'] ?? $p['item_code'] ?? ''));
        $src_stock_id = (int) ($p['metal_exchange_source_stock_id'] ?? $p['source_issue_stock_id'] ?? 0);
        $is_mr_receive = ($reference_type === 'material_receive_metal_exchange');
        if ($user_bc !== '') {
            $b_esc = mysqli_real_escape_string($conn, $user_bc);
            $st = function_exists('getRecord')
                ? getRecord(
                    'SELECT id, reference_type, reference_id, status, metal_id, product_characteristic_id'
                    . ' FROM tbl_stock'
                    . " WHERE barcode = '$b_esc' ORDER BY id DESC LIMIT 1"
                )
                : null;
            if (!$st || !is_array($st)) {
                return ['barcode' => $user_bc, 'reuse_stock_id' => 0];
            }
            $st_id = (int) ($st['id'] ?? 0);
            $st_ref = trim((string) ($st['reference_type'] ?? ''));
            // Material Receive settles against issued ME stock and must create a NEW inward lot
            // (fresh barcode). Keep issued barcode only as source linkage via source_stock_id.
            // Do not throw when receive target product differs from the issued line.
            if (
                $is_mr_receive
                && $st_ref === 'material_issue_metal_exchange'
                && ($src_stock_id < 1 || $src_stock_id === $st_id)
            ) {
                // Fall through to allocate a new barcode below.
            } elseif ($payment_stock_id > 0 && $st_id > 0 && $payment_stock_id === $st_id) {
                if ($is_mr_receive) {
                    // Fall through — new inward lot for receive.
                } else {
                    return ['barcode' => $user_bc, 'reuse_stock_id' => $st_id];
                }
            } elseif ((int) ($st['status'] ?? 0) !== 1) {
                if ($is_mr_receive) {
                    // Fall through — new inward lot for receive.
                } else {
                    return ['barcode' => $user_bc, 'reuse_stock_id' => $st_id];
                }
            } else {
                $same_doc = $st_ref === $reference_type
                    && (int) ($st['reference_id'] ?? 0) === $reference_id;
                if ($same_doc && $st_id > 0 && in_array($st_id, $reserved_stock_ids, true)) {
                    // Same item code reused on another line in this save — allocate a new barcode below.
                } elseif ($same_doc) {
                    return ['barcode' => $user_bc, 'reuse_stock_id' => $st_id];
                } elseif ($st_ref === 'jobwork_order_metal_exchange') {
                    // Another JWO ME line — allocate a fresh barcode for this inward receipt.
                } elseif ($st_ref === 'sale_order_metal_exchange') {
                    $src_id = (int) ($p['metal_exchange_source_stock_id'] ?? $p['source_issue_stock_id'] ?? 0);
                    if (
                        in_array($reference_type, ['material_issue_metal_exchange', 'material_receive_metal_exchange'], true)
                        && $src_id > 0
                        && $src_id === $st_id
                    ) {
                        if ($is_mr_receive) {
                            // Fall through — new inward lot for receive against SO ME source.
                        } else {
                            return ['barcode' => $user_bc, 'reuse_stock_id' => 0];
                        }
                    } else {
                        throw new Exception(
                            'Metal exchange: barcode "' . $user_bc . '" is already used on sale order metal exchange stock.'
                        );
                    }
                } elseif ($st_ref === 'material_issue_metal_exchange' && (int) ($st['reference_id'] ?? 0) !== $reference_id) {
                    if ($is_mr_receive) {
                        // Fall through — new inward lot for receive against any issued ME line.
                    } else {
                        throw new Exception(
                            'Metal exchange: barcode "' . $user_bc . '" is already used on another material issue.'
                        );
                    }
                } elseif (
                    auragold_metal_exchange_stock_matches_payment_product($st, $pcid, $mid)
                    && !(
                        $reference_type === 'jobwork_order_metal_exchange'
                        && function_exists('auragold_stock_row_is_inventory_for_issue')
                        && auragold_stock_row_is_inventory_for_issue($st)
                    )
                ) {
                    if ($is_mr_receive) {
                        // Fall through — receive creates a separate inward lot (do not reuse shop barcode).
                    } else {
                        return ['barcode' => $user_bc, 'reuse_stock_id' => $st_id];
                    }
                } elseif (
                    $reference_type === 'jobwork_order_metal_exchange'
                    && function_exists('auragold_stock_row_is_inventory_for_issue')
                    && auragold_stock_row_is_inventory_for_issue($st)
                ) {
                    // Inventory lot — handled by job work order outward path, not inward ME reuse.
                } else {
                    throw new Exception(
                        'Metal exchange: barcode "' . $user_bc . '" is already used on another product or metal.'
                    );
                }
            }
        }

        if (!function_exists('auragold_next_product_stock_barcode')) {
            $nb = __DIR__ . '/next_product_stock_barcode.php';
            if (is_file($nb)) {
                require_once $nb;
            }
        }
        if (!function_exists('generateBarcode')) {
            $cfg = dirname(__DIR__) . '/config.php';
            if (is_file($cfg)) {
                require_once $cfg;
            }
        }
        list($prefix, $digits) = auragold_resolve_product_barcode_prefix_digits($conn, $pid, $pcid, $mid, $branch_id);
        $reserve = is_array($reserved_barcodes) ? $reserved_barcodes : [];
        if (function_exists('generateBarcode')) {
            $gen = generateBarcode($conn, $prefix, $digits, $reserve);
            if ($gen !== '') {
                return ['barcode' => $gen, 'reuse_stock_id' => 0];
            }
        }
        for ($bc_attempt = 0; $bc_attempt < 12; $bc_attempt++) {
            $nb = auragold_next_product_stock_barcode($conn, $pid, $pcid, $mid, $branch_id, $reserve);
            $try_bc = trim((string) ($nb['barcode'] ?? ''));
            if ($try_bc === '') {
                break;
            }
            if (!function_exists('auragold_barcode_exists_in_system') || !auragold_barcode_exists_in_system($conn, $try_bc)) {
                return ['barcode' => $try_bc, 'reuse_stock_id' => 0];
            }
            $reserve[] = $try_bc;
        }

        return $empty;
    }
}

if (!function_exists('auragold_metal_exchange_barcode_from_payment')) {
    function auragold_metal_exchange_barcode_from_payment(
        mysqli $conn,
        array $p,
        string $reference_type,
        int $reference_id,
        int $pid,
        int $pcid,
        int $mid,
        int $branch_id,
        array $reserved_barcodes = [],
        array $reserved_stock_ids = []
    ): string {
        $plan = auragold_metal_exchange_barcode_plan_from_payment(
            $conn,
            $p,
            $reference_type,
            $reference_id,
            $pid,
            $pcid,
            $mid,
            $branch_id,
            $reserved_barcodes,
            $reserved_stock_ids
        );

        return (string) ($plan['barcode'] ?? '');
    }
}

if (!function_exists('auragold_post_metal_exchange_payment_to_stock')) {
    /**
     * New inward tbl_stock row + stock history for one metal-exchange payment line.
     *
     * @param array<string, mixed> $payment merged or raw (merged inside)
     * @param array<int, array{barcode: string, product_name: string}> $created_barcodes_out
     */
    function auragold_post_metal_exchange_payment_to_stock(
        mysqli $conn,
        string $reference_type,
        int $reference_id,
        string $doc_no_plain,
        string $doc_date_ymd,
        array $payment,
        int $branch_id,
        int $pay_seq,
        bool $tbl_stock_has_reference,
        string $history_voucher_label,
        string $audit_src,
        string $sj_invoice_prefix,
        array &$created_barcodes_out,
        array $reserved_barcodes = [],
        array $reserved_stock_ids = []
    ): void {
        if (!auragold_payment_is_metal_exchange_inward($conn, $payment)) {
            return;
        }
        $p = auragold_payment_merge_stored_details($payment);
        if ($reference_type === 'jobwork_order_metal_exchange' && function_exists('auragold_jwo_metal_exchange_strip_receive_source_ids')) {
            $p = auragold_jwo_metal_exchange_strip_receive_source_ids($p);
        }
        auragold_validate_metal_exchange_for_stock($conn, $p);
        $r = auragold_metal_exchange_resolve($conn, $p);
        $pcid = auragold_metal_exchange_resolve_characteristic_id($conn, $p);
        $mid = (int) ($p['metal_exchange_metal_id'] ?? $p['metal_id'] ?? 0);
        if ($pcid < 1 || $mid < 1) {
            throw new Exception(
                'Metal exchange: select metal and product from the list, and enter gross weight before saving.'
            );
        }
        $pcrow = getRecord("SELECT product_id FROM tbl_product_characteristics WHERE id = $pcid AND status = 1 LIMIT 1");
        $pid = (int) ($pcrow['product_id'] ?? 0);
        if ($pid <= 0) {
            throw new Exception('Metal exchange: invalid product characteristic.');
        }
        $bc_plan = auragold_metal_exchange_barcode_plan_from_payment(
            $conn,
            $p,
            $reference_type,
            $reference_id,
            $pid,
            $pcid,
            $mid,
            $branch_id,
            $reserved_barcodes,
            $reserved_stock_ids
        );
        $barcode_plain = trim((string) ($bc_plan['barcode'] ?? ''));
        $reuse_stock_id = (int) ($bc_plan['reuse_stock_id'] ?? 0);
        if ($barcode_plain === '') {
            throw new Exception('Metal exchange: could not allocate a unique barcode.');
        }

        error_log('[' . $audit_src . '] metal_exchange stock ref=' . $reference_type . ':' . $reference_id . ' pid=' . $pid . ' bc=' . $barcode_plain);
        $gross = (float) $r['gross'];
        $pure = (float) $r['pure'];
        $qty = (float) $r['qty'];
        if ($qty <= 1e-8) {
            $qty = 1.0;
        }
        $opening_purity = ($gross > 1e-8) ? min(1.0, max(0.0001, $pure / $gross)) : 0.916;
        $final_w = ($pure > 1e-8) ? $pure : $gross;
        $rate = (float) ($p['metal_exchange_rate'] ?? $p['rate'] ?? 0);
        $line_amt = (float) ($p['amount'] ?? 0);
        if ($line_amt <= 1e-8 && $rate > 1e-8 && $pure > 1e-8) {
            $line_amt = $rate * $pure;
        } elseif ($line_amt <= 1e-8 && $rate > 1e-8) {
            $line_amt = $rate * $gross;
        }
        $product_name_plain = trim((string) ($p['metal_exchange_product_name'] ?? $p['product_name'] ?? ''));

        $t_stock = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_stock'");
        if (!$t_stock || mysqli_num_rows($t_stock) === 0) {
            if ($t_stock) {
                mysqli_free_result($t_stock);
            }
            throw new Exception('Metal exchange: inventory table not available.');
        }
        mysqli_free_result($t_stock);

        $tbl_stock_has_barcode = false;
        $bc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'barcode'");
        if ($bc && mysqli_num_rows($bc) > 0) {
            $tbl_stock_has_barcode = true;
        }
        if ($bc) {
            mysqli_free_result($bc);
        }
        if (!$tbl_stock_has_barcode) {
            throw new Exception('Metal exchange: tbl_stock has no barcode column.');
        }

        $has_sj = false;
        $sj_check = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'stock_journal_id'");
        if ($sj_check && mysqli_num_rows($sj_check) > 0) {
            $has_sj = true;
        }
        if ($sj_check) {
            mysqli_free_result($sj_check);
        }

        $has_status = false;
        $stc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'status'");
        if ($stc && mysqli_num_rows($stc) > 0) {
            $has_status = true;
        }
        if ($stc) {
            mysqli_free_result($stc);
        }

        $bc_sql = "'" . mysqli_real_escape_string($conn, $barcode_plain) . "'";
        $txn_esc = mysqli_real_escape_string($conn, $doc_date_ymd);
        $bid = $branch_id > 0 ? $branch_id : 1;
        $ref_id = (int) $reference_id;
        $rt_esc = mysqli_real_escape_string($conn, $reference_type);

        if ($reuse_stock_id > 0) {
            $upd = 'UPDATE tbl_stock SET product_id = ' . (int) $pid
                . ', product_characteristic_id = ' . (int) $pcid
                . ', branch_id = ' . (int) $bid
                . ', metal_id = ' . (int) $mid
                . ', opening_weight = ' . $gross
                . ', opening_purity = ' . $opening_purity
                . ', opening_qty = ' . $qty
                . ', final_weight = ' . $final_w
                . ', rate = ' . $rate
                . ', value = ' . $line_amt
                . ', current_weight = ' . $gross
                . ', current_qty = ' . $qty
                . ", stock_type = 'purchase'"
                . ", transaction_date = '$txn_esc'"
                . ", reference_id = $ref_id, reference_type = '$rt_esc', status = 1";
            if (
                function_exists('auragold_tbl_has_column')
                && auragold_tbl_has_column($conn, 'tbl_stock', 'source_stock_id')
            ) {
                $src_stock_id = (int) ($p['metal_exchange_source_stock_id'] ?? $p['source_issue_stock_id'] ?? 0);
                $upd .= $src_stock_id > 0 ? ', source_stock_id = ' . $src_stock_id : ', source_stock_id = NULL';
            }
            if (!mysqli_query($conn, $upd . ' WHERE id = ' . (int) $reuse_stock_id)) {
                throw new Exception('Metal exchange: could not update stock for barcode ' . $barcode_plain . '.');
            }
            require_once __DIR__ . '/stock_history_audit_journal.php';
            $sj_no_reuse = $sj_invoice_prefix . (int) $reference_id . '-' . (int) $pay_seq;
            if (strlen($sj_no_reuse) > 48) {
                $sj_no_reuse = preg_replace('/[^A-Za-z0-9\\-]/', '', substr($sj_invoice_prefix, 0, 4)) . (int) $reference_id . 'x' . (int) $pay_seq;
            }
            auragold_stock_history_audit_insert_row($conn, [
                'sj_invoice_no' => $sj_no_reuse,
                'item_id' => 0,
                'invoice_id' => 0,
                'invoice_no' => $doc_no_plain,
                'sj_date' => $doc_date_ymd,
                'barcode' => $barcode_plain,
                'product_id' => $pid,
                'product_characteristic_id' => $pcid,
                'product_name' => $product_name_plain,
                'metal_id' => $mid,
                'metal_type' => function_exists('auragold_stock_history_metal_type') ? auragold_stock_history_metal_type($conn, $mid) : '',
                'quantity' => $qty,
                'gross_weight' => $gross,
                'less_weight' => 0,
                'net_weight' => $gross,
                'purity' => $opening_purity * 100,
                'purity_weight' => $pure,
                'pure_weight' => $pure,
                'final_weight' => $final_w,
                'rate' => $rate,
                'amount' => $line_amt,
                'making_amount' => 0,
                'tax_amount' => 0,
                'net_amount' => $line_amt,
                'net_amt_with_tax' => $line_amt,
                'rfid_code' => '',
                'voucher_type' => $history_voucher_label,
                'design_no' => '',
                'category' => '',
                'comment' => 'auragold_doc|src=' . $audit_src . '|rid=' . (int) $reference_id . '|seq=' . (int) $pay_seq . '|reuse=1|',
            ]);
            $created_barcodes_out[] = [
                'barcode' => $barcode_plain,
                'product_name' => $product_name_plain,
                'source' => 'metal_exchange',
                'stock_id' => $reuse_stock_id,
            ];

            return;
        }

        $src_stock_id = (int) ($p['metal_exchange_source_stock_id'] ?? $p['source_issue_stock_id'] ?? 0);
        if ($src_stock_id > 0 && is_file(__DIR__ . '/auragold_material_issue_rows_for_sale_order.php')) {
            require_once __DIR__ . '/auragold_material_issue_rows_for_sale_order.php';
            auragold_ensure_stock_source_stock_id_column($conn);
        }

        $stock_cols = 'product_id, product_characteristic_id, barcode, branch_id, metal_id, opening_weight, opening_purity, opening_qty, final_weight, rate, value, current_weight, current_qty, stock_type, transaction_date';
        $stock_vals = "$pid, $pcid, $bc_sql, $bid, $mid, $gross, $opening_purity, $qty, $final_w, $rate, $line_amt, $gross, $qty, 'purchase', '$txn_esc'";
        if ($tbl_stock_has_reference) {
            $stock_cols .= ', reference_id, reference_type';
            $stock_vals .= ", $ref_id, '$rt_esc'";
        }
        if (
            $src_stock_id > 0
            && function_exists('auragold_tbl_has_column')
            && auragold_tbl_has_column($conn, 'tbl_stock', 'source_stock_id')
        ) {
            $stock_cols .= ', source_stock_id';
            $stock_vals .= ', ' . (int) $src_stock_id;
        }
        if ($has_sj) {
            $stock_cols .= ', stock_journal_id';
            $stock_vals .= ', NULL';
        }
        if ($has_status) {
            $stock_cols .= ', status, created_at';
            $stock_vals .= ', 1, NOW()';
        } else {
            $stock_cols .= ', created_at';
            $stock_vals .= ', NOW()';
        }

        $stock_insert_sql = "INSERT INTO tbl_stock ($stock_cols) VALUES ($stock_vals)";
        if (!mysqli_query($conn, $stock_insert_sql)) {
            $err = mysqli_error($conn);
            error_log('[' . $audit_src . '] STOCK INSERT FAILED ref=' . $reference_type . ':' . $reference_id . ' err=' . $err);
            throw new Exception('Metal exchange: stock insert failed: ' . $err);
        }
        $new_stock_id = (int) mysqli_insert_id($conn);
        $t_inward = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_inward_stock'");
        if ($t_inward && mysqli_num_rows($t_inward) > 0) {
            mysqli_free_result($t_inward);
            $inward_sql = "
                INSERT INTO tbl_inward_stock (
                    stock_journal_id, product_id, product_characteristic_id, barcode_no,
                    branch_id, metal_id, qty, weight, rate, value, stock_type, transaction_date, created_at
                ) VALUES (
                    NULL, $pid, $pcid, $bc_sql, $bid, $mid, $qty, $gross, $rate, $line_amt, 'purchase', '$txn_esc', NOW()
                )";
            @mysqli_query($conn, $inward_sql);
        } elseif ($t_inward) {
            mysqli_free_result($t_inward);
        }

        $has_qty = false;
        $has_wt = false;
        $upd_cols = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_product_characteristics WHERE Field IN ('opening_qty','opening_weight')");
        if ($upd_cols) {
            while ($c = mysqli_fetch_assoc($upd_cols)) {
                if (($c['Field'] ?? '') === 'opening_qty') {
                    $has_qty = true;
                }
                if (($c['Field'] ?? '') === 'opening_weight') {
                    $has_wt = true;
                }
            }
            mysqli_free_result($upd_cols);
        }
        if ($has_qty || $has_wt) {
            $set_parts = [];
            if ($has_qty) {
                $set_parts[] = 'opening_qty = COALESCE(opening_qty, 0) + ' . $qty;
            }
            if ($has_wt) {
                $set_parts[] = 'opening_weight = COALESCE(opening_weight, 0) + ' . $gross;
            }
            @mysqli_query($conn, 'UPDATE tbl_product_characteristics SET ' . implode(', ', $set_parts) . " WHERE id = $pcid");
        }

        require_once __DIR__ . '/stock_history_audit_journal.php';
        $sj_no = $sj_invoice_prefix . (int) $reference_id . '-' . (int) $pay_seq;
        if (strlen($sj_no) > 48) {
            $sj_no = preg_replace('/[^A-Za-z0-9\\-]/', '', substr($sj_invoice_prefix, 0, 4)) . (int) $reference_id . 'x' . (int) $pay_seq;
        }
        auragold_stock_history_audit_insert_row($conn, [
            'sj_invoice_no' => $sj_no,
            'item_id' => 0,
            'invoice_id' => 0,
            'invoice_no' => $doc_no_plain,
            'sj_date' => $doc_date_ymd,
            'barcode' => $barcode_plain,
            'product_id' => $pid,
            'product_characteristic_id' => $pcid,
            'product_name' => $product_name_plain,
            'metal_id' => $mid,
            'metal_type' => function_exists('auragold_stock_history_metal_type') ? auragold_stock_history_metal_type($conn, $mid) : '',
            'quantity' => $qty,
            'gross_weight' => $gross,
            'less_weight' => 0,
            'net_weight' => $gross,
            'purity' => $opening_purity * 100,
            'purity_weight' => $pure,
            'pure_weight' => $pure,
            'final_weight' => $final_w,
            'rate' => $rate,
            'amount' => $line_amt,
            'making_amount' => 0,
            'tax_amount' => 0,
            'net_amount' => $line_amt,
            'net_amt_with_tax' => $line_amt,
            'rfid_code' => '',
            'voucher_type' => $history_voucher_label,
            'design_no' => '',
            'category' => '',
            'comment' => 'auragold_doc|src=' . $audit_src . '|rid=' . (int) $reference_id . '|seq=' . (int) $pay_seq . '|',
        ]);

        $created_barcodes_out[] = [
            'barcode' => $barcode_plain,
            'product_name' => $product_name_plain,
            'source' => 'metal_exchange',
            'stock_id' => $new_stock_id > 0 ? $new_stock_id : 0,
        ];
    }
}

if (!function_exists('auragold_metal_exchange_payment_display_amount')) {
    /**
     * INR amount for payment cards / summary (rate × pure wt when amount column is 0).
     */
    function auragold_metal_exchange_payment_display_amount(array $payment): float
    {
        $p = function_exists('auragold_payment_merge_stored_details')
            ? auragold_payment_merge_stored_details($payment)
            : $payment;
        $amt = (float) ($p['amount'] ?? 0);
        if ($amt > 0.00001) {
            return round($amt, 2);
        }
        $cur = isset($p['current_order_amount']) ? (float) $p['current_order_amount'] : 0.0;
        if ($cur > 0.00001) {
            return round($cur, 2);
        }
        $rate = (float) ($p['metal_exchange_rate'] ?? $p['rate'] ?? 0);
        $pure = (float) ($p['metal_exchange_purity_wt'] ?? $p['purity_weight'] ?? 0);
        $gross = (float) ($p['metal_exchange_gross_wt'] ?? $p['gross_weight'] ?? $p['gross_wt'] ?? 0);
        if ($rate > 0.00001 && $pure > 0.00001) {
            return round($rate * $pure, 2);
        }
        if ($rate > 0.00001 && $gross > 0.00001) {
            return round($rate * $gross, 2);
        }
        $qty = (float) ($p['quantity'] ?? 0);
        if ($qty > 0.00001 && $rate > 0.00001) {
            return round($rate * $qty, 2);
        }

        return 0.0;
    }
}

if (!function_exists('auragold_jobwork_payment_row_is_jwo_metal_exchange')) {
    /**
     * True when payment_details tags this row as metal exchange for a specific job work order.
     */
    function auragold_jobwork_payment_row_is_jwo_metal_exchange(array $row, int $jwo_id = 0): bool
    {
        if (empty($row['payment_details'])) {
            return false;
        }
        $jd = json_decode((string) $row['payment_details'], true);
        if (!is_array($jd) || empty($jd['jobwork_order_metal_exchange'])) {
            return false;
        }
        if ($jwo_id > 0) {
            return (int) ($jd['jobwork_order_id'] ?? 0) === $jwo_id;
        }

        return (int) ($jd['jobwork_order_id'] ?? 0) > 0;
    }
}

if (!function_exists('auragold_jobwork_embed_should_hide_payment_row')) {
    /**
     * On JWO screens: show only metal exchange added on this job work order; hide SO-screen ME and plumbing rows.
     */
    function auragold_jobwork_embed_should_hide_payment_row(mysqli $conn, array $row, int $keep_jwo_id = 0): bool
    {
        if (auragold_jobwork_payment_row_is_jwo_metal_exchange($row, $keep_jwo_id)) {
            return false;
        }
        if (auragold_jobwork_payment_row_is_jwo_metal_exchange($row, 0)) {
            return true;
        }
        $merged = function_exists('auragold_payment_merge_stored_details')
            ? auragold_payment_merge_stored_details($row)
            : $row;
        if (auragold_payment_is_metal_exchange_inward($conn, $merged)) {
            return true;
        }
        if (function_exists('auragold_sale_order_payment_row_is_internal_only')
            && auragold_sale_order_payment_row_is_internal_only($row)) {
            return true;
        }

        return false;
    }
}

if (!function_exists('auragold_jobwork_embed_strip_sale_order_metal_exchange_payments')) {
    /**
     * Remove inherited sale-order metal exchange rows from JWO payment embed.
     *
     * @param array<int, array<string, mixed>> $edit_payments
     */
    function auragold_jobwork_embed_strip_sale_order_metal_exchange_payments(mysqli $conn, array &$edit_payments, int $keep_jwo_id = 0): void
    {
        if (!is_array($edit_payments) || empty($edit_payments)) {
            return;
        }
        $edit_payments = array_values(array_filter($edit_payments, function ($ep) use ($conn, $keep_jwo_id) {
            if (!is_array($ep)) {
                return false;
            }

            return !auragold_jobwork_embed_should_hide_payment_row($conn, $ep, $keep_jwo_id);
        }));
    }
}

if (!function_exists('auragold_sale_order_payment_row_is_internal_only')) {
    /**
     * Rows stored on tbl_sale_order_payments for job work / issue plumbing — not entered on the sale order screen.
     */
    function auragold_sale_order_payment_row_is_internal_only(array $row): bool
    {
        if (!is_array($row)) {
            return true;
        }
        if (function_exists('auragold_jobwork_payment_row_is_jwo_metal_exchange')
            && auragold_jobwork_payment_row_is_jwo_metal_exchange($row, 0)) {
            return true;
        }
        $merged = function_exists('auragold_payment_merge_stored_details')
            ? auragold_payment_merge_stored_details($row)
            : $row;
        if (!empty($merged['jobwork_order_metal_exchange'])) {
            return true;
        }
        if (!empty($merged['auto_material_issue_metal_exchange'])) {
            return true;
        }

        return false;
    }
}

if (!function_exists('auragold_sale_order_get_metal_exchange_stocks')) {
    /** @return list<array<string, mixed>> */
    function auragold_sale_order_get_metal_exchange_stocks(mysqli $conn, int $sale_order_id, string $reference_type): array
    {
        if ($sale_order_id < 1 || !function_exists('getList')) {
            return [];
        }
        if ($reference_type === 'sale_order_metal_exchange') {
            $rows = getList(
                'SELECT * FROM tbl_stock WHERE status = 1'
                . " AND reference_type = 'sale_order_metal_exchange'"
                . ' AND reference_id = ' . (int) $sale_order_id
                . ' ORDER BY id ASC'
            );

            return is_array($rows) ? $rows : [];
        }
        if ($reference_type === 'jobwork_order_metal_exchange') {
            $rows = getList(
                'SELECT s.* FROM tbl_stock s'
                . ' INNER JOIN tbl_jobwork_orders j ON j.id = s.reference_id'
                . " WHERE s.status = 1 AND s.reference_type = 'jobwork_order_metal_exchange'"
                . ' AND j.sale_order_id = ' . (int) $sale_order_id
                . ' ORDER BY s.id ASC'
            );

            return is_array($rows) ? $rows : [];
        }

        return [];
    }
}

if (!function_exists('auragold_sale_order_me_payment_row_matches_stock')) {
    function auragold_sale_order_me_payment_row_matches_stock(mysqli $conn, array $payment, array $stock_row): bool
    {
        $m = auragold_payment_merge_stored_details($payment);
        if (!auragold_payment_is_metal_exchange_inward($conn, $m)) {
            return false;
        }
        $resolved = auragold_metal_exchange_resolve($conn, $m);
        if (empty($resolved['is_me'])) {
            return false;
        }
        $gross = (float) ($resolved['gross'] ?? 0);
        $pcid = auragold_metal_exchange_resolve_characteristic_id($conn, $m);
        $stock_pcid = (int) ($stock_row['product_characteristic_id'] ?? 0);
        $stock_w = (float) ($stock_row['opening_weight'] ?? 0);
        if ($stock_w <= 1e-8) {
            $stock_w = (float) ($stock_row['current_weight'] ?? 0);
        }
        $bc_pay = trim((string) ($m['metal_exchange_item_code'] ?? $m['item_code'] ?? ''));
        $bc_st = trim((string) ($stock_row['barcode'] ?? ''));
        if ($pcid > 0 && $stock_pcid > 0 && $pcid !== $stock_pcid) {
            return false;
        }
        if ($bc_pay !== '' && $bc_st !== '' && strcasecmp($bc_pay, $bc_st) !== 0) {
            return false;
        }

        return abs($gross - $stock_w) <= 0.0001;
    }
}

if (!function_exists('auragold_sale_order_filter_customer_payments')) {
    /**
     * Keep only payment lines the user added on the sale order (hide JWO/internal ME copies).
     *
     * @param array<int, array<string, mixed>> $payments
     */
    function auragold_sale_order_filter_customer_payments(array &$payments): void
    {
        if (!is_array($payments) || $payments === []) {
            return;
        }
        $payments = array_values(array_filter($payments, function ($row) {
            return is_array($row) && !auragold_sale_order_payment_row_is_internal_only($row);
        }));
    }
}

if (!function_exists('auragold_sale_order_filter_display_payments')) {
    /**
     * Sale order screen: hide JWO/internal ME copies; keep the first customer ME per product.
     *
     * @param array<int, array<string, mixed>> $payments
     */
    function auragold_sale_order_filter_display_payments(mysqli $conn, int $order_id, array &$payments): void
    {
        auragold_sale_order_filter_customer_payments($payments);
        if ($order_id < 1 || !is_array($payments) || $payments === []) {
            return;
        }

        $non_me = [];
        $me_rows = [];
        foreach ($payments as $row) {
            if (!is_array($row)) {
                continue;
            }
            $merged = auragold_payment_merge_stored_details($row);
            if (auragold_payment_is_metal_exchange_inward($conn, $merged)) {
                $me_rows[] = $row;
            } else {
                $non_me[] = $row;
            }
        }
        if ($me_rows === []) {
            $payments = $non_me;

            return;
        }

        usort($me_rows, static function ($a, $b) {
            return (int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0);
        });

        // Earliest row per metal + product is the sale-order entry; later rows are JWO/save duplicates.
        $seen = [];
        $deduped = [];
        foreach ($me_rows as $row) {
            $merged = auragold_payment_merge_stored_details($row);
            $pcid = auragold_metal_exchange_resolve_characteristic_id($conn, $merged);
            $mid = (int) ($merged['metal_exchange_metal_id'] ?? $merged['metal_id'] ?? 0);
            $key = $mid . ':' . $pcid;
            if ($pcid < 1 && $mid < 1) {
                $key = 'me:row:' . count($deduped);
            }
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $deduped[] = $row;
        }

        $payments = array_values(array_merge($non_me, $deduped));
        usort($payments, static function ($a, $b) {
            return (int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0);
        });
    }
}

if (!function_exists('auragold_metal_exchange_inventory_stock_in_types_sql')) {
    function auragold_metal_exchange_inventory_stock_in_types_sql(): string
    {
        return "'opening','purchase','stock_journal','balance','sale_return','inward'";
    }
}

if (!function_exists('auragold_stock_row_is_inventory_for_issue')) {
    /** Regular shop inventory lot (opening / purchase / inward), not metal-exchange document stock. */
    function auragold_stock_row_is_inventory_for_issue(array $st): bool
    {
        $st_type = trim((string) ($st['stock_type'] ?? ''));
        $in_types = ['opening', 'purchase', 'stock_journal', 'balance', 'sale_return', 'inward'];
        if (!in_array($st_type, $in_types, true)) {
            return false;
        }
        $ref = trim((string) ($st['reference_type'] ?? ''));
        if ($ref === '') {
            return true;
        }
        static $me_refs = null;
        if ($me_refs === null) {
            $me_refs = [
                'sale_order_metal_exchange', 'sale_invoice_metal_exchange', 'sale_quotation_metal_exchange',
                'sale_return_metal_exchange', 'purchase_invoice_metal_exchange', 'purchase_quotation_metal_exchange',
                'purchase_return_metal_exchange', 'payment_voucher_metal_exchange', 'receipt_voucher_metal_exchange',
                'advance_payment_metal_exchange', 'old_jewelry_scrap_invoice_metal_exchange',
                'material_issue_metal_exchange', 'material_receive_metal_exchange',
                'jobwork_order_metal_exchange', 'jobwork_invoice_metal_exchange',
                'old_jewellery_scrap_stock_in_metal_exchange', 'consignment_in_metal_exchange',
                'consignment_out_metal_exchange', 'pos_sale_invoice_metal_exchange',
            ];
        }

        return !in_array($ref, $me_refs, true);
    }
}

if (!function_exists('auragold_metal_exchange_inventory_branch_sql')) {
    function auragold_metal_exchange_inventory_branch_sql(mysqli $conn, string $column = 'branch_id'): string
    {
        $bid = auragold_metal_exchange_default_branch_id();
        if ($bid < 1 || !function_exists('auragold_tbl_has_column') || !auragold_tbl_has_column($conn, 'tbl_stock', 'branch_id')) {
            return '';
        }
        $main_bid = function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0;
        if ($main_bid > 0 && $bid === $main_bid) {
            return ' AND (' . $column . ' = ' . $bid . ' OR ' . $column . ' IS NULL OR ' . $column . ' = 0)';
        }

        return ' AND ' . $column . ' = ' . $bid;
    }
}

if (!function_exists('auragold_inventory_stock_available_weight')) {
    function auragold_inventory_stock_available_weight(array $stock_row): float
    {
        $cw = (float) ($stock_row['current_weight'] ?? 0);
        if ($cw > 1e-8) {
            return $cw;
        }
        $ow = (float) ($stock_row['opening_weight'] ?? 0);
        if ($ow > 1e-8) {
            return $ow;
        }

        return 0.0;
    }
}

if (!function_exists('auragold_fetch_available_inventory_stock')) {
    /**
     * Pick one inventory lot matching $where_sql (must start with AND …).
     *
     * @return array<string, mixed>|null
     */
    function auragold_fetch_available_inventory_stock(mysqli $conn, string $where_sql, string $order_sql = ''): ?array
    {
        if (!function_exists('getRecord')) {
            return null;
        }
        $stock_in_types = auragold_metal_exchange_inventory_stock_in_types_sql();
        $avail_sql = ' AND (COALESCE(current_qty, 0) > 0 OR COALESCE(current_weight, 0) > 0'
            . ' OR COALESCE(opening_qty, 0) > 0 OR COALESCE(opening_weight, 0) > 0)';
        if ($order_sql === '') {
            $order_sql = 'CASE WHEN COALESCE(current_weight, 0) > 0 OR COALESCE(current_qty, 0) > 0 THEN 0 ELSE 1 END, id DESC';
        }
        $branch_sql = auragold_metal_exchange_inventory_branch_sql($conn);
        $attempts = $branch_sql !== '' ? [$branch_sql, ''] : [''];
        foreach ($attempts as $bs) {
            $row = getRecord(
                'SELECT * FROM tbl_stock'
                . ' WHERE status = 1'
                . " AND stock_type IN ($stock_in_types)"
                . $avail_sql
                . $where_sql
                . $bs
                . ' ORDER BY ' . $order_sql
                . ' LIMIT 1'
            );
            if ($row && is_array($row) && auragold_stock_row_is_inventory_for_issue($row)) {
                return $row;
            }
        }

        return null;
    }
}

if (!function_exists('auragold_resolve_inventory_stock_for_metal_exchange')) {
    /**
     * Find shop inventory stock for a metal-exchange payment (barcode or product + metal).
     *
     * @return array<string, mixed>|null
     */
    function auragold_resolve_inventory_stock_for_metal_exchange(mysqli $conn, array $payment): ?array
    {
        if (!function_exists('getRecord') || !auragold_payment_is_metal_exchange_inward($conn, $payment)) {
            return null;
        }
        $p = auragold_payment_merge_stored_details($payment);
        $pcid = auragold_metal_exchange_resolve_characteristic_id($conn, $p);
        $mid = (int) ($p['metal_exchange_metal_id'] ?? $p['metal_id'] ?? 0);
        if ($pcid < 1 || $mid < 1) {
            return null;
        }
        $barcode_raw = trim((string) ($p['metal_exchange_item_code'] ?? $p['item_code'] ?? $p['barcode_no'] ?? $p['barcode'] ?? ''));
        $source_stock = null;
        $strict_product_match = false;

        if ($barcode_raw !== '') {
            $strict_product_match = true;
            $stock_in_types = auragold_metal_exchange_inventory_stock_in_types_sql();
            $branch_sql = auragold_metal_exchange_inventory_branch_sql($conn);
            $barcode_esc = mysqli_real_escape_string($conn, $barcode_raw);
            $source_stock = getRecord(
                'SELECT * FROM tbl_stock'
                . " WHERE barcode = '$barcode_esc' AND status = 1"
                . " AND stock_type IN ($stock_in_types)"
                . ' AND (COALESCE(current_qty, 0) > 0 OR COALESCE(current_weight, 0) > 0'
                . ' OR COALESCE(opening_qty, 0) > 0 OR COALESCE(opening_weight, 0) > 0)'
                . $branch_sql
                . ' ORDER BY CASE WHEN COALESCE(current_weight, 0) > 0 OR COALESCE(current_qty, 0) > 0 THEN 0 ELSE 1 END, id DESC'
                . ' LIMIT 1'
            );
            if (!$source_stock && $branch_sql !== '') {
                $source_stock = getRecord(
                    'SELECT * FROM tbl_stock'
                    . " WHERE barcode = '$barcode_esc' AND status = 1"
                    . " AND stock_type IN ($stock_in_types)"
                    . ' AND (COALESCE(current_qty, 0) > 0 OR COALESCE(current_weight, 0) > 0'
                    . ' OR COALESCE(opening_qty, 0) > 0 OR COALESCE(opening_weight, 0) > 0)'
                    . ' ORDER BY CASE WHEN COALESCE(current_weight, 0) > 0 OR COALESCE(current_qty, 0) > 0 THEN 0 ELSE 1 END, id DESC'
                    . ' LIMIT 1'
                );
            }
        }

        $product_id = (int) ($p['product_id'] ?? 0);
        $pcrow = getRecord('SELECT product_id FROM tbl_product_characteristics WHERE id = ' . (int) $pcid . ' AND status = 1 LIMIT 1');
        if ($pcrow && !empty($pcrow['product_id'])) {
            $product_id = (int) $pcrow['product_id'];
        }

        if (!$source_stock && $product_id > 0 && $pcid > 0 && $mid > 0) {
            $strict_product_match = true;
            $source_stock = auragold_fetch_available_inventory_stock(
                $conn,
                ' AND product_id = ' . (int) $product_id
                . ' AND product_characteristic_id = ' . (int) $pcid
                . ' AND metal_id = ' . (int) $mid
            );
        }

        if (!$source_stock && $product_id > 0 && $mid > 0) {
            $source_stock = auragold_fetch_available_inventory_stock(
                $conn,
                ' AND product_id = ' . (int) $product_id . ' AND metal_id = ' . (int) $mid
            );
        }

        if (!$source_stock && $mid > 0) {
            $source_stock = auragold_fetch_available_inventory_stock(
                $conn,
                ' AND metal_id = ' . (int) $mid,
                'COALESCE(NULLIF(current_weight, 0), opening_weight, 0) DESC, id DESC'
            );
        }

        if (!$source_stock || !auragold_stock_row_is_inventory_for_issue($source_stock)) {
            return null;
        }
        $st_mid = (int) ($source_stock['metal_id'] ?? 0);
        if ($st_mid > 0 && $mid > 0 && $st_mid !== $mid) {
            return null;
        }
        if ($strict_product_match && !auragold_metal_exchange_stock_matches_payment_product($source_stock, $pcid, $mid)) {
            return null;
        }
        if (auragold_inventory_stock_available_weight($source_stock) <= 1e-8) {
            return null;
        }

        return $source_stock;
    }
}

if (!function_exists('auragold_jobwork_order_payment_issues_shop_metal')) {
    /** JWO metal line that issues metal from shop stock (not auto-issue from prior ME receipt). */
    function auragold_jobwork_order_payment_issues_shop_metal(mysqli $conn, array $payment): bool
    {
        if (!auragold_payment_is_metal_exchange_inward($conn, $payment)) {
            return false;
        }
        $p = auragold_payment_merge_stored_details($payment);
        if (function_exists('auragold_jwo_metal_exchange_strip_receive_source_ids')) {
            $p = auragold_jwo_metal_exchange_strip_receive_source_ids($p);
        }
        $src_stock = (int) ($p['metal_exchange_source_stock_id'] ?? $p['source_issue_stock_id'] ?? 0);

        return $src_stock < 1;
    }
}

if (!function_exists('auragold_jobwork_order_delete_metal_outward_journal')) {
    /** Remove prior JWO metal-outward stock journal rows before re-posting (sj_invoice_no is UNIQUE). */
    function auragold_jobwork_order_delete_metal_outward_journal(mysqli $conn, int $jwo_id): void
    {
        if ($jwo_id < 1) {
            return;
        }
        $t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_stock_journal'");
        if (!$t || mysqli_num_rows($t) === 0) {
            if ($t) {
                mysqli_free_result($t);
            }

            return;
        }
        mysqli_free_result($t);
        $rid = (int) $jwo_id;
        $rid_esc = mysqli_real_escape_string($conn, (string) $rid);
        @mysqli_query(
            $conn,
            "DELETE FROM tbl_stock_journal WHERE comment LIKE 'auragold_doc|src=jwo_out|rid={$rid_esc}|%'"
        );
        @mysqli_query(
            $conn,
            "DELETE FROM tbl_stock_journal WHERE sj_invoice_no LIKE 'JWO-MO-{$rid_esc}-%'"
        );
        @mysqli_query(
            $conn,
            "DELETE FROM tbl_stock_journal WHERE sj_invoice_no LIKE 'JWO{$rid_esc}O%'"
        );
        @mysqli_query(
            $conn,
            "DELETE FROM tbl_stock_journal WHERE sj_invoice_no = 'JWO{$rid_esc}00'"
        );
    }
}

if (!function_exists('auragold_jobwork_order_reverse_metal_outward_stock')) {
    /** Restore inventory from prior JWO metal outward rows, then delete those outward rows. */
    function auragold_jobwork_order_reverse_metal_outward_stock(mysqli $conn, int $jwo_id): void
    {
        if ($jwo_id < 1 || !function_exists('getList') || !auragold_prepare_tbl_stock_reference_columns($conn)) {
            return;
        }
        if (function_exists('auragold_jobwork_order_delete_metal_outward_journal')) {
            auragold_jobwork_order_delete_metal_outward_journal($conn, $jwo_id);
        }
        $stock_in_types = auragold_metal_exchange_inventory_stock_in_types_sql();
        $rev_rows = getList(
            "SELECT barcode, opening_weight, opening_qty, product_id, product_characteristic_id, branch_id"
            . " FROM tbl_stock WHERE stock_type = 'outward' AND reference_id = " . (int) $jwo_id
            . " AND reference_type = 'jobwork_order' AND status = 1"
        );
        if (!is_array($rev_rows)) {
            return;
        }
        foreach ($rev_rows as $rv) {
            $ow = (float) ($rv['opening_weight'] ?? 0);
            $oq = (float) ($rv['opening_qty'] ?? 0);
            if ($ow <= 0 && $oq <= 0) {
                continue;
            }
            $b = trim((string) ($rv['barcode'] ?? ''));
            $target_id = 0;
            if ($b !== '') {
                $be = mysqli_real_escape_string($conn, $b);
                $tr = getRecord(
                    "SELECT id FROM tbl_stock WHERE barcode = '$be' AND status = 1"
                    . " AND stock_type IN ($stock_in_types) ORDER BY id DESC LIMIT 1"
                );
                if ($tr && !empty($tr['id'])) {
                    $target_id = (int) $tr['id'];
                }
            }
            if ($target_id <= 0) {
                $pid = (int) ($rv['product_id'] ?? 0);
                $bid = (int) ($rv['branch_id'] ?? 0);
                $cid_raw = $rv['product_characteristic_id'] ?? null;
                if ($pid > 0 && $bid > 0) {
                    if ($cid_raw !== null && $cid_raw !== '') {
                        $cid = (int) $cid_raw;
                        $tr = getRecord(
                            "SELECT id FROM tbl_stock WHERE product_id = $pid AND product_characteristic_id = $cid"
                            . " AND branch_id = $bid AND status = 1 AND stock_type IN ($stock_in_types) ORDER BY id DESC LIMIT 1"
                        );
                    } else {
                        $tr = getRecord(
                            "SELECT id FROM tbl_stock WHERE product_id = $pid AND product_characteristic_id IS NULL"
                            . " AND branch_id = $bid AND status = 1 AND stock_type IN ($stock_in_types) ORDER BY id DESC LIMIT 1"
                        );
                    }
                    if ($tr && !empty($tr['id'])) {
                        $target_id = (int) $tr['id'];
                    }
                }
            }
            if ($target_id > 0) {
                mysqli_query(
                    $conn,
                    'UPDATE tbl_stock SET current_weight = COALESCE(current_weight, 0) + ' . $ow
                    . ', current_qty = COALESCE(current_qty, 0) + ' . $oq
                    . ' WHERE id = ' . $target_id
                );
            }
        }
        mysqli_query(
            $conn,
            "DELETE FROM tbl_stock WHERE stock_type = 'outward' AND reference_id = " . (int) $jwo_id
            . " AND reference_type = 'jobwork_order'"
        );
    }
}

if (!function_exists('auragold_post_jobwork_order_metal_outward_from_inventory')) {
    /**
     * Issue shop metal to a job work order: deduct inventory, insert outward row, stock history audit.
     */
    function auragold_post_jobwork_order_metal_outward_from_inventory(
        mysqli $conn,
        int $jwo_id,
        string $jobwork_no_plain,
        string $doc_date_ymd,
        array $payment,
        int $pay_seq,
        array $source_stock
    ): void {
        if ($jwo_id < 1 || !function_exists('getRecord')) {
            return;
        }
        $p = auragold_payment_merge_stored_details($payment);
        auragold_validate_metal_exchange_for_stock($conn, $p);
        $r = auragold_metal_exchange_resolve($conn, $p);
        $pcid = auragold_metal_exchange_resolve_characteristic_id($conn, $p);
        $mid = (int) ($p['metal_exchange_metal_id'] ?? $p['metal_id'] ?? 0);
        $pcrow = getRecord('SELECT product_id FROM tbl_product_characteristics WHERE id = ' . (int) $pcid . ' AND status = 1 LIMIT 1');
        $pid = (int) ($pcrow['product_id'] ?? $source_stock['product_id'] ?? 0);
        if ($pid < 1 || $mid < 1) {
            throw new Exception('Metal exchange: select metal and product before saving the job work order.');
        }

        $gross = (float) $r['gross'];
        $pure = (float) $r['pure'];
        if ($gross <= 1e-8) {
            throw new Exception('Metal exchange: enter gross weight before saving.');
        }
        $avail = auragold_inventory_stock_available_weight($source_stock);
        if ($gross > $avail + 0.0001) {
            throw new Exception(
                'Metal exchange issue weight (' . round($gross, 3) . ') exceeds available stock ('
                . round(max(0, $avail), 3) . ').'
            );
        }

        $qty = (float) ($r['qty'] ?? $p['quantity'] ?? 1);
        if ($qty <= 1e-8) {
            $qty = 1.0;
        }
        $stock_purity = (float) ($source_stock['opening_purity'] ?? 0);
        if ($stock_purity <= 1e-8 && $gross > 1e-8 && $pure > 1e-8) {
            $stock_purity = min(1.0, max(0.0001, $pure / $gross));
        }
        $final_w = $pure > 1e-8 ? $pure : $gross;
        $rate = (float) ($p['metal_exchange_rate'] ?? $p['rate'] ?? $source_stock['rate'] ?? 0);
        $line_amt = (float) ($p['amount'] ?? 0);
        if ($line_amt <= 1e-8 && $rate > 1e-8) {
            $line_amt = $rate * ($pure > 1e-8 ? $pure : $gross);
        }
        $deduct_weight = $gross;
        $branch_id = (int) ($source_stock['branch_id'] ?? auragold_metal_exchange_default_branch_id());
        $barcode_plain = trim((string) ($p['metal_exchange_item_code'] ?? $p['item_code'] ?? $source_stock['barcode'] ?? ''));
        $doc_date = trim($doc_date_ymd);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $doc_date)) {
            $doc_date = date('Y-m-d');
        }
        $txn_esc = mysqli_real_escape_string($conn, $doc_date);
        $barcode_esc = $barcode_plain !== '' ? mysqli_real_escape_string($conn, $barcode_plain) : '';
        $stock_rate_val = (float) ($source_stock['rate'] ?? $rate);
        $stock_value = $deduct_weight * $stock_rate_val;
        $has_ref = auragold_prepare_tbl_stock_reference_columns($conn);
        $src_id = (int) ($source_stock['id'] ?? 0);

        $bc_col = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'barcode'");
        $has_barcode = ($bc_col && mysqli_num_rows($bc_col) > 0);
        if ($bc_col) {
            mysqli_free_result($bc_col);
        }

        $ow_cols = 'product_id, product_characteristic_id, branch_id, metal_id, opening_weight, opening_purity, opening_qty, final_weight, rate, value, current_weight, current_qty, stock_type, transaction_date, status, created_at';
        $pcid_sql = $pcid > 0 ? (string) (int) $pcid : 'NULL';
        $ow_vals = (int) $pid . ', ' . $pcid_sql . ', ' . (int) $branch_id . ', ' . (int) $mid
            . ', ' . $deduct_weight . ', ' . $stock_purity . ', ' . $qty . ', ' . $final_w
            . ', ' . $rate . ', ' . $line_amt . ', ' . $deduct_weight . ', ' . $qty
            . ", 'outward', '$txn_esc', 1, NOW()";
        if ($has_barcode) {
            $ow_cols = 'product_id, product_characteristic_id, barcode, branch_id, metal_id, opening_weight, opening_purity, opening_qty, final_weight, rate, value, current_weight, current_qty, stock_type, transaction_date, status, created_at';
            $bc_sql = $barcode_esc !== '' ? "'$barcode_esc'" : 'NULL';
            $ow_vals = (int) $pid . ', ' . $pcid_sql . ', ' . $bc_sql . ', ' . (int) $branch_id . ', ' . (int) $mid
                . ', ' . $deduct_weight . ', ' . $stock_purity . ', ' . $qty . ', ' . $final_w
                . ', ' . $rate . ', ' . $line_amt . ', ' . $deduct_weight . ', ' . $qty
                . ", 'outward', '$txn_esc', 1, NOW()";
        }
        if ($has_ref) {
            $ow_cols = str_replace('transaction_date, status, created_at', 'transaction_date, status, reference_id, reference_type, created_at', $ow_cols);
            $ow_vals = str_replace("'$txn_esc', 1, NOW()", "'$txn_esc', 1, " . (int) $jwo_id . ", 'jobwork_order', NOW()", $ow_vals);
        }
        if ($src_id > 0 && function_exists('auragold_ensure_stock_source_stock_id_column')) {
            auragold_ensure_stock_source_stock_id_column($conn);
            if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_stock', 'source_stock_id')) {
                $ow_cols = str_replace(', created_at', ', source_stock_id, created_at', $ow_cols);
                $ow_vals = str_replace(', NOW()', ', ' . $src_id . ', NOW()', $ow_vals);
            }
        }
        if (!mysqli_query($conn, 'INSERT INTO tbl_stock (' . $ow_cols . ') VALUES (' . $ow_vals . ')')) {
            throw new Exception('Job work order outward stock insert failed: ' . mysqli_error($conn));
        }

        $prev_cq = (float) ($source_stock['current_qty'] ?? 0);
        $prev_cw = (float) ($source_stock['current_weight'] ?? 0);
        $op_q = (float) ($source_stock['opening_qty'] ?? 0);
        $op_w = (float) ($source_stock['opening_weight'] ?? 0);
        $sold_q = $qty;
        if ($prev_cq > 0 || $prev_cw > 0) {
            if ($sold_q <= 0 && $prev_cw > 0 && $prev_cq > 0) {
                $sold_q = $prev_cq * ($deduct_weight / $prev_cw);
            }
            $balance_weight = $prev_cw - $deduct_weight;
            $new_cq = max(0.0, $prev_cq - $sold_q);
            if ($balance_weight <= 0) {
                mysqli_query($conn, 'UPDATE tbl_stock SET current_weight = 0, current_qty = 0, value = 0 WHERE id = ' . $src_id);
            } else {
                $new_val = $stock_rate_val * $balance_weight;
                mysqli_query(
                    $conn,
                    'UPDATE tbl_stock SET current_weight = ' . $balance_weight
                    . ', current_qty = ' . $new_cq
                    . ', final_weight = ' . $balance_weight
                    . ', value = ' . $new_val
                    . ' WHERE id = ' . $src_id
                );
            }
        } else {
            $new_op_q = max(0.0, $op_q - $sold_q);
            $new_op_w = max(0.0, $op_w - $deduct_weight);
            if ($new_op_w <= 0.00001 && $new_op_q <= 0.00001) {
                mysqli_query($conn, 'UPDATE tbl_stock SET opening_qty = 0, opening_weight = 0, final_weight = 0, value = 0 WHERE id = ' . $src_id);
            } else {
                $new_val = $stock_rate_val * $new_op_w;
                mysqli_query(
                    $conn,
                    'UPDATE tbl_stock SET opening_qty = ' . $new_op_q
                    . ', opening_weight = ' . $new_op_w
                    . ', final_weight = ' . $new_op_w
                    . ', value = ' . $new_val
                    . ' WHERE id = ' . $src_id
                );
            }
        }

        require_once __DIR__ . '/stock_history_audit_journal.php';
        $product_name_plain = trim((string) ($p['metal_exchange_product_name'] ?? $p['product_name'] ?? ''));
        if ($product_name_plain === '') {
            $pn_row = getRecord('SELECT name FROM tbl_products WHERE id = ' . (int) $pid . ' LIMIT 1');
            if ($pn_row && isset($pn_row['name'])) {
                $product_name_plain = trim((string) $pn_row['name']);
            }
        }
        $sj_key = 'JWO-MO-' . (int) $jwo_id . '-' . (int) $pay_seq;
        if (strlen($sj_key) > 48) {
            $sj_key = 'J' . (int) $jwo_id . 'mo' . (int) $pay_seq;
        }
        $legacy_sj_key = 'JWO' . (int) $jwo_id . '0' . (int) $pay_seq;
        if ($legacy_sj_key !== $sj_key) {
            $legacy_esc = mysqli_real_escape_string($conn, $legacy_sj_key);
            @mysqli_query($conn, "DELETE FROM tbl_stock_journal WHERE sj_invoice_no = '$legacy_esc' LIMIT 1");
        }
        auragold_stock_history_audit_insert_row($conn, [
            'sj_invoice_no' => $sj_key,
            'item_id' => 0,
            'invoice_id' => 0,
            'invoice_no' => trim($jobwork_no_plain),
            'sj_date' => $doc_date,
            'barcode' => $barcode_plain,
            'product_id' => $pid,
            'product_characteristic_id' => $pcid,
            'product_name' => $product_name_plain,
            'metal_id' => $mid,
            'metal_type' => function_exists('auragold_stock_history_metal_type') ? auragold_stock_history_metal_type($conn, $mid) : '',
            'quantity' => $qty,
            'gross_weight' => $gross,
            'less_weight' => 0,
            'net_weight' => $final_w,
            'purity' => $stock_purity > 0 ? ($stock_purity <= 1 ? $stock_purity * 100 : $stock_purity) : 0,
            'purity_weight' => $pure,
            'pure_weight' => $pure,
            'final_weight' => $final_w,
            'rate' => $rate,
            'amount' => $line_amt,
            'making_amount' => 0,
            'tax_amount' => 0,
            'net_amount' => $line_amt,
            'net_amt_with_tax' => $line_amt,
            'rfid_code' => '',
            'voucher_type' => 'Job Work Order',
            'design_no' => '',
            'category' => trim((string) ($p['diamond_category'] ?? '')),
            'comment' => 'auragold_doc|src=jwo_out|rid=' . (int) $jwo_id . '|seq=' . (int) $pay_seq . '|',
        ]);
    }
}

if (!function_exists('auragold_post_jobwork_order_metal_inward_to_shop_stock')) {
    /**
     * Return reduced job metal to shop stock: restore inventory, insert inward row, stock history audit.
     */
    function auragold_post_jobwork_order_metal_inward_to_shop_stock(
        mysqli $conn,
        int $jwo_id,
        string $jobwork_no_plain,
        string $doc_date_ymd,
        array $payment,
        int $pay_seq
    ): void {
        if ($jwo_id < 1 || !function_exists('getRecord')) {
            return;
        }
        $p = auragold_payment_merge_stored_details($payment);
        if (!auragold_payment_is_metal_exchange_inward($conn, $p)) {
            throw new Exception('Metal exchange: select metal, product, and gross weight for shop stock return.');
        }
        auragold_validate_metal_exchange_for_stock($conn, $p);
        $r = auragold_metal_exchange_resolve($conn, $p);
        $pcid = auragold_metal_exchange_resolve_characteristic_id($conn, $p);
        $mid = (int) ($p['metal_exchange_metal_id'] ?? $p['metal_id'] ?? 0);
        $pcrow = getRecord('SELECT product_id FROM tbl_product_characteristics WHERE id = ' . (int) $pcid . ' AND status = 1 LIMIT 1');
        $pid = (int) ($pcrow['product_id'] ?? 0);
        if ($pid < 1 || $mid < 1) {
            throw new Exception('Metal exchange: select metal and product before saving reduce weight.');
        }

        $gross = (float) $r['gross'];
        $pure = (float) $r['pure'];
        if ($gross <= 1e-8) {
            throw new Exception('Metal exchange: enter gross weight before saving.');
        }
        $qty = (float) ($r['qty'] ?? $p['quantity'] ?? 1);
        if ($qty <= 1e-8) {
            $qty = 1.0;
        }

        $stock_purity = 0.0;
        $pur_carat = trim((string) ($p['purity_carat'] ?? $p['metal_exchange_purity'] ?? ''));
        if ($pur_carat !== '') {
            $pur_num = (float) preg_replace('/[^0-9.]/', '', $pur_carat);
            if ($pur_num > 0) {
                if ($pur_num <= 1) {
                    $stock_purity = $pur_num;
                } elseif ($pur_num <= 100) {
                    $stock_purity = $pur_num / 100;
                } else {
                    $stock_purity = $pur_num / 1000;
                }
            }
        }
        if ($stock_purity <= 1e-8 && $gross > 1e-8 && $pure > 1e-8) {
            $stock_purity = min(1.0, max(0.0001, $pure / $gross));
        }

        $rate = (float) ($p['metal_exchange_rate'] ?? $p['rate'] ?? 0);
        $line_amt = (float) ($p['amount'] ?? 0);
        if ($line_amt <= 1e-8 && $rate > 1e-8) {
            $line_amt = $rate * ($pure > 1e-8 ? $pure : $gross);
        }
        $final_w = $pure > 1e-8 ? $pure : $gross;
        $branch_id = auragold_metal_exchange_default_branch_id();
        $barcode_plain = trim((string) ($p['metal_exchange_item_code'] ?? $p['item_code'] ?? ''));
        $doc_date = trim($doc_date_ymd);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $doc_date)) {
            $doc_date = date('Y-m-d');
        }
        $txn_esc = mysqli_real_escape_string($conn, $doc_date);
        $barcode_esc = $barcode_plain !== '' ? mysqli_real_escape_string($conn, $barcode_plain) : '';

        $inv_src = function_exists('auragold_resolve_inventory_stock_for_metal_exchange')
            ? auragold_resolve_inventory_stock_for_metal_exchange($conn, $p)
            : null;
        if (is_array($inv_src) && (int) ($inv_src['id'] ?? 0) > 0) {
            $src_id = (int) $inv_src['id'];
            $prev_cq = (float) ($inv_src['current_qty'] ?? 0);
            $prev_cw = (float) ($inv_src['current_weight'] ?? 0);
            $op_q = (float) ($inv_src['opening_qty'] ?? 0);
            $op_w = (float) ($inv_src['opening_weight'] ?? 0);
            $stock_rate_val = (float) ($inv_src['rate'] ?? $rate);
            $add_q = $qty;
            if ($prev_cq > 0 || $prev_cw > 0) {
                $balance_weight = $prev_cw + $gross;
                $new_cq = $prev_cq + $add_q;
                $new_val = $stock_rate_val * $balance_weight;
                mysqli_query(
                    $conn,
                    'UPDATE tbl_stock SET current_weight = ' . $balance_weight
                    . ', current_qty = ' . $new_cq
                    . ', final_weight = ' . $balance_weight
                    . ', value = ' . $new_val
                    . ' WHERE id = ' . $src_id
                );
            } else {
                $new_op_q = $op_q + $add_q;
                $new_op_w = $op_w + $gross;
                $new_val = $stock_rate_val * $new_op_w;
                mysqli_query(
                    $conn,
                    'UPDATE tbl_stock SET opening_qty = ' . $new_op_q
                    . ', opening_weight = ' . $new_op_w
                    . ', current_weight = ' . $new_op_w
                    . ', current_qty = ' . $new_op_q
                    . ', final_weight = ' . $new_op_w
                    . ', value = ' . $new_val
                    . ' WHERE id = ' . $src_id
                );
            }
        }

        $has_ref = auragold_prepare_tbl_stock_reference_columns($conn);
        $bc_col = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'barcode'");
        $has_barcode = ($bc_col && mysqli_num_rows($bc_col) > 0);
        if ($bc_col) {
            mysqli_free_result($bc_col);
        }

        $in_cols = 'product_id, product_characteristic_id, branch_id, metal_id, opening_weight, opening_purity, opening_qty, final_weight, rate, value, current_weight, current_qty, stock_type, transaction_date, status, created_at';
        $pcid_sql = $pcid > 0 ? (string) (int) $pcid : 'NULL';
        $in_vals = (int) $pid . ', ' . $pcid_sql . ', ' . (int) $branch_id . ', ' . (int) $mid
            . ', ' . $gross . ', ' . $stock_purity . ', ' . $qty . ', ' . $final_w
            . ', ' . $rate . ', ' . $line_amt . ', ' . $gross . ', ' . $qty
            . ", 'inward', '$txn_esc', 1, NOW()";
        if ($has_barcode) {
            $in_cols = 'product_id, product_characteristic_id, barcode, branch_id, metal_id, opening_weight, opening_purity, opening_qty, final_weight, rate, value, current_weight, current_qty, stock_type, transaction_date, status, created_at';
            $bc_sql = $barcode_esc !== '' ? "'$barcode_esc'" : 'NULL';
            $in_vals = (int) $pid . ', ' . $pcid_sql . ', ' . $bc_sql . ', ' . (int) $branch_id . ', ' . (int) $mid
                . ', ' . $gross . ', ' . $stock_purity . ', ' . $qty . ', ' . $final_w
                . ', ' . $rate . ', ' . $line_amt . ', ' . $gross . ', ' . $qty
                . ", 'inward', '$txn_esc', 1, NOW()";
        }
        if ($has_ref) {
            $in_cols = str_replace('transaction_date, status, created_at', 'transaction_date, status, reference_id, reference_type, created_at', $in_cols);
            $in_vals = str_replace("'$txn_esc', 1, NOW()", "'$txn_esc', 1, " . (int) $jwo_id . ", 'jobwork_order', NOW()", $in_vals);
        }
        if (!mysqli_query($conn, 'INSERT INTO tbl_stock (' . $in_cols . ') VALUES (' . $in_vals . ')')) {
            throw new Exception('Job work order inward stock insert failed: ' . mysqli_error($conn));
        }

        require_once __DIR__ . '/stock_history_audit_journal.php';
        $product_name_plain = trim((string) ($p['metal_exchange_product_name'] ?? $p['product_name'] ?? ''));
        if ($product_name_plain === '') {
            $pn_row = getRecord('SELECT name FROM tbl_products WHERE id = ' . (int) $pid . ' LIMIT 1');
            if ($pn_row && isset($pn_row['name'])) {
                $product_name_plain = trim((string) $pn_row['name']);
            }
        }
        $sj_key = 'JWO-MI-' . (int) $jwo_id . '-' . (int) $pay_seq;
        if (strlen($sj_key) > 48) {
            $sj_key = 'J' . (int) $jwo_id . 'mi' . (int) $pay_seq;
        }
        auragold_stock_history_audit_insert_row($conn, [
            'sj_invoice_no' => $sj_key,
            'item_id' => 0,
            'invoice_id' => 0,
            'invoice_no' => trim($jobwork_no_plain),
            'sj_date' => $doc_date,
            'barcode' => $barcode_plain,
            'product_id' => $pid,
            'product_characteristic_id' => $pcid,
            'product_name' => $product_name_plain,
            'metal_id' => $mid,
            'metal_type' => function_exists('auragold_stock_history_metal_type') ? auragold_stock_history_metal_type($conn, $mid) : '',
            'quantity' => $qty,
            'gross_weight' => $gross,
            'less_weight' => 0,
            'net_weight' => $final_w,
            'purity' => $stock_purity > 0 ? ($stock_purity <= 1 ? $stock_purity * 100 : $stock_purity) : 0,
            'purity_weight' => $pure,
            'pure_weight' => $pure,
            'final_weight' => $final_w,
            'rate' => $rate,
            'amount' => $line_amt,
            'making_amount' => 0,
            'tax_amount' => 0,
            'net_amount' => $line_amt,
            'net_amt_with_tax' => $line_amt,
            'rfid_code' => '',
            'voucher_type' => 'Job Work Order',
            'design_no' => '',
            'category' => trim((string) ($p['diamond_category'] ?? '')),
            'comment' => 'auragold_doc|src=jwo_in|rid=' . (int) $jwo_id . '|seq=' . (int) $pay_seq . '|',
        ]);
    }
}

if (!function_exists('auragold_payment_is_metal_exchange_outward')) {
    /** Payment voucher metal lines pay the party with shop metal — outward stock (not inward receipt). */
    function auragold_payment_is_metal_exchange_outward(array $payment): bool
    {
        $p = auragold_payment_merge_stored_details($payment);
        $pt = strtolower(trim((string) ($p['payment_type'] ?? '')));
        if (in_array($pt, ['metal', 'm. exch.', 'metal exchange', 'metal_exchange'], true)) {
            return true;
        }
        $ui = strtolower(trim((string) ($p['type'] ?? '')));

        return $ui === 'metal-exchange' || $ui === 'metal_exchange';
    }
}

if (!function_exists('auragold_post_payment_voucher_metal_outward_to_stock')) {
    /**
     * Deduct metal stock for a payment-voucher metal-exchange line and record outward + stock history.
     */
    function auragold_post_payment_voucher_metal_outward_to_stock(
        mysqli $conn,
        int $voucher_id,
        string $doc_no_plain,
        string $doc_date_ymd,
        array $payment,
        int $line_id,
        int $pay_seq,
        int $branch_id,
        bool $tbl_stock_has_reference
    ): void {
        if (!auragold_payment_is_metal_exchange_outward($payment)) {
            return;
        }
        $p = auragold_payment_merge_stored_details($payment);
        $mid = (int) ($p['metal_id'] ?? 0);
        $pcid = auragold_metal_exchange_resolve_characteristic_id($conn, $p);
        $pid = (int) ($p['product_id'] ?? 0);
        if ($pcid > 0 && function_exists('getRecord')) {
            $pcrow = getRecord(
                'SELECT product_id FROM tbl_product_characteristics WHERE id = ' . (int) $pcid
                . ' AND status = 1 LIMIT 1'
            );
            if ($pcrow && !empty($pcrow['product_id'])) {
                $pid = (int) $pcrow['product_id'];
            }
        }
        if ($pid < 1 || $mid < 1) {
            throw new Exception('Metal exchange: select metal and product before saving the payment voucher.');
        }

        $gross = (float) ($p['weight'] ?? $p['gross_weight'] ?? 0);
        $pure = (float) ($p['purity_wt'] ?? $p['purity_weight'] ?? 0);
        if ($pure <= 1e-8) {
            $pure = $gross;
        }
        if ($gross <= 1e-8) {
            $gross = $pure;
        }
        if ($gross <= 1e-8) {
            throw new Exception('Metal exchange: enter gross or purity weight before saving.');
        }

        $qty = (float) ($p['quantity'] ?? 1);
        if ($qty <= 1e-8) {
            $qty = 1.0;
        }

        $stock_purity = 0.0;
        $pur_carat = trim((string) ($p['purity_carat'] ?? ''));
        if ($pur_carat !== '') {
            $pur_num = (float) preg_replace('/[^0-9.]/', '', $pur_carat);
            if ($pur_num > 0) {
                if ($pur_num <= 1) {
                    $stock_purity = $pur_num;
                } elseif ($pur_num <= 100) {
                    $stock_purity = $pur_num / 100;
                } else {
                    $stock_purity = $pur_num / 1000;
                }
            }
        }
        if ($stock_purity <= 1e-8 && $gross > 1e-8 && $pure > 1e-8) {
            $stock_purity = min(1.0, max(0.0001, $pure / $gross));
        }

        $rate = (float) ($p['rate'] ?? 0);
        $line_amt = (float) ($p['amount'] ?? 0);
        if ($line_amt <= 1e-8 && $rate > 1e-8) {
            $line_amt = $rate * ($pure > 1e-8 ? $pure : $gross);
        }
        $deduct_weight = $gross;
        $final_w = $pure > 1e-8 ? $pure : $gross;
        $bid = $branch_id > 0 ? $branch_id : auragold_metal_exchange_default_branch_id();
        $doc_date = trim($doc_date_ymd);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $doc_date)) {
            $doc_date = date('Y-m-d');
        }
        $txn_esc = mysqli_real_escape_string($conn, $doc_date);

        $barcode_plain = trim((string) ($p['item_code'] ?? $p['barcode_no'] ?? $p['barcode'] ?? ''));
        if ($barcode_plain === '' && $pcid > 0 && function_exists('getRecord')) {
            $bpc = getRecord(
                'SELECT NULLIF(TRIM(barcode), \'\') AS bc FROM tbl_product_characteristics WHERE id = '
                . (int) $pcid . ' AND status = 1 LIMIT 1'
            );
            if ($bpc && !empty($bpc['bc'])) {
                $barcode_plain = trim((string) $bpc['bc']);
            }
        }

        $tbl_stock_has_barcode = false;
        $bc_col = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'barcode'");
        if ($bc_col && mysqli_num_rows($bc_col) > 0) {
            $tbl_stock_has_barcode = true;
        }
        if ($bc_col) {
            mysqli_free_result($bc_col);
        }

        $ow_cols = 'product_id, product_characteristic_id, branch_id, metal_id, opening_weight, opening_purity, opening_qty, final_weight, rate, value, current_weight, current_qty, stock_type, transaction_date, status, created_at';
        $pcid_sql = $pcid > 0 ? (string) (int) $pcid : 'NULL';
        $ow_vals = (int) $pid . ', ' . $pcid_sql . ', ' . (int) $bid . ', ' . (int) $mid
            . ', ' . $deduct_weight . ', ' . $stock_purity . ', ' . $qty . ', ' . $final_w
            . ', ' . $rate . ', ' . $line_amt . ', ' . $deduct_weight . ', ' . $qty
            . ", 'outward', '$txn_esc', 1, NOW()";
        if ($tbl_stock_has_barcode) {
            $ow_cols = 'product_id, product_characteristic_id, barcode, branch_id, metal_id, opening_weight, opening_purity, opening_qty, final_weight, rate, value, current_weight, current_qty, stock_type, transaction_date, status, created_at';
            $bc_sql = $barcode_plain !== '' ? "'" . mysqli_real_escape_string($conn, $barcode_plain) . "'" : 'NULL';
            $ow_vals = (int) $pid . ', ' . $pcid_sql . ', ' . $bc_sql . ', ' . (int) $bid . ', ' . (int) $mid
                . ', ' . $deduct_weight . ', ' . $stock_purity . ', ' . $qty . ', ' . $final_w
                . ', ' . $rate . ', ' . $line_amt . ', ' . $deduct_weight . ', ' . $qty
                . ", 'outward', '$txn_esc', 1, NOW()";
        }
        if ($tbl_stock_has_reference) {
            $ow_cols = str_replace('transaction_date, status, created_at', 'transaction_date, status, reference_id, reference_type, created_at', $ow_cols);
            $ow_vals = str_replace("'$txn_esc', 1, NOW()", "'$txn_esc', 1, " . (int) $voucher_id . ", 'payment_voucher', NOW()", $ow_vals);
        }
        if (!mysqli_query($conn, 'INSERT INTO tbl_stock (' . $ow_cols . ') VALUES (' . $ow_vals . ')')) {
            throw new Exception('Payment voucher outward stock insert failed: ' . mysqli_error($conn));
        }

        require_once __DIR__ . '/stock_history_audit_journal.php';
        $product_name_plain = trim((string) ($p['product'] ?? $p['product_name'] ?? ''));
        if ($product_name_plain === '' && function_exists('getRecord')) {
            $pn_row = getRecord('SELECT name FROM tbl_products WHERE id = ' . (int) $pid . ' LIMIT 1');
            if ($pn_row && isset($pn_row['name'])) {
                $product_name_plain = trim((string) $pn_row['name']);
            }
        }
        $sj_key = 'PV' . (int) $voucher_id . 'O' . (int) $line_id;
        if (strlen($sj_key) > 48) {
            $sj_key = 'P' . (int) $voucher_id . 'o' . (int) $line_id;
        }
        auragold_stock_history_audit_insert_row($conn, [
            'sj_invoice_no' => $sj_key,
            'item_id' => 0,
            'invoice_id' => 0,
            'invoice_no' => trim($doc_no_plain),
            'sj_date' => $doc_date,
            'barcode' => $barcode_plain,
            'product_id' => $pid,
            'product_characteristic_id' => $pcid,
            'product_name' => $product_name_plain,
            'metal_id' => $mid,
            'metal_type' => function_exists('auragold_stock_history_metal_type') ? auragold_stock_history_metal_type($conn, $mid) : '',
            'quantity' => $qty,
            'gross_weight' => $gross,
            'less_weight' => 0,
            'net_weight' => $final_w,
            'purity' => $stock_purity > 0 ? ($stock_purity <= 1 ? $stock_purity * 100 : $stock_purity) : 0,
            'purity_weight' => $pure,
            'pure_weight' => $pure,
            'final_weight' => $final_w,
            'rate' => $rate,
            'amount' => $line_amt,
            'making_amount' => 0,
            'tax_amount' => 0,
            'net_amount' => $line_amt,
            'net_amt_with_tax' => $line_amt,
            'rfid_code' => '',
            'voucher_type' => 'Payment Voucher',
            'design_no' => '',
            'category' => trim((string) ($p['diamond_category'] ?? '')),
            'comment' => 'auragold_doc|src=pv_out|rid=' . (int) $voucher_id . '|lid=' . (int) $line_id . '|seq=' . (int) $pay_seq . '|',
        ]);
    }
}

if (!function_exists('auragold_post_receipt_voucher_metal_inward_to_stock')) {
    /**
     * Add metal stock for a receipt-voucher metal-exchange line (inward) + stock history.
     */
    function auragold_post_receipt_voucher_metal_inward_to_stock(
        mysqli $conn,
        int $voucher_id,
        string $doc_no_plain,
        string $doc_date_ymd,
        array $payment,
        int $line_id,
        int $pay_seq,
        int $branch_id,
        bool $tbl_stock_has_reference
    ): void {
        if (!auragold_payment_is_metal_exchange_outward($payment)) {
            return;
        }
        $p = auragold_payment_merge_stored_details($payment);
        $mid = (int) ($p['metal_id'] ?? 0);
        $pcid = auragold_metal_exchange_resolve_characteristic_id($conn, $p);
        $pid = (int) ($p['product_id'] ?? 0);
        if ($pcid > 0 && function_exists('getRecord')) {
            $pcrow = getRecord(
                'SELECT product_id FROM tbl_product_characteristics WHERE id = ' . (int) $pcid
                . ' AND status = 1 LIMIT 1'
            );
            if ($pcrow && !empty($pcrow['product_id'])) {
                $pid = (int) $pcrow['product_id'];
            }
        }
        if ($pid < 1 || $mid < 1) {
            throw new Exception('Metal exchange: select metal and product before saving the receipt voucher.');
        }

        $gross = (float) ($p['weight'] ?? $p['gross_weight'] ?? 0);
        $pure = (float) ($p['purity_wt'] ?? $p['purity_weight'] ?? 0);
        if ($pure <= 1e-8) {
            $pure = $gross;
        }
        if ($gross <= 1e-8) {
            $gross = $pure;
        }
        if ($gross <= 1e-8) {
            throw new Exception('Metal exchange: enter gross or purity weight before saving.');
        }

        $qty = (float) ($p['quantity'] ?? 1);
        if ($qty <= 1e-8) {
            $qty = 1.0;
        }

        $stock_purity = 0.0;
        $pur_carat = trim((string) ($p['purity_carat'] ?? ''));
        if ($pur_carat !== '') {
            $pur_num = (float) preg_replace('/[^0-9.]/', '', $pur_carat);
            if ($pur_num > 0) {
                if ($pur_num <= 1) {
                    $stock_purity = $pur_num;
                } elseif ($pur_num <= 100) {
                    $stock_purity = $pur_num / 100;
                } else {
                    $stock_purity = $pur_num / 1000;
                }
            }
        }
        if ($stock_purity <= 1e-8 && $gross > 1e-8 && $pure > 1e-8) {
            $stock_purity = min(1.0, max(0.0001, $pure / $gross));
        }

        $rate = (float) ($p['rate'] ?? 0);
        $line_amt = (float) ($p['amount'] ?? 0);
        if ($line_amt <= 1e-8 && $rate > 1e-8) {
            $line_amt = $rate * ($pure > 1e-8 ? $pure : $gross);
        }
        $in_weight = $gross;
        $final_w = $pure > 1e-8 ? $pure : $gross;
        $bid = $branch_id > 0 ? $branch_id : auragold_metal_exchange_default_branch_id();
        $doc_date = trim($doc_date_ymd);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $doc_date)) {
            $doc_date = date('Y-m-d');
        }
        $txn_esc = mysqli_real_escape_string($conn, $doc_date);

        $barcode_plain = trim((string) ($p['item_code'] ?? $p['barcode_no'] ?? $p['barcode'] ?? ''));
        if ($barcode_plain === '' && $pcid > 0 && function_exists('getRecord')) {
            $bpc = getRecord(
                'SELECT NULLIF(TRIM(barcode), \'\') AS bc FROM tbl_product_characteristics WHERE id = '
                . (int) $pcid . ' AND status = 1 LIMIT 1'
            );
            if ($bpc && !empty($bpc['bc'])) {
                $barcode_plain = trim((string) $bpc['bc']);
            }
        }

        $tbl_stock_has_barcode = false;
        $bc_col = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'barcode'");
        if ($bc_col && mysqli_num_rows($bc_col) > 0) {
            $tbl_stock_has_barcode = true;
        }
        if ($bc_col) {
            mysqli_free_result($bc_col);
        }

        $in_cols = 'product_id, product_characteristic_id, branch_id, metal_id, opening_weight, opening_purity, opening_qty, final_weight, rate, value, current_weight, current_qty, stock_type, transaction_date, status, created_at';
        $pcid_sql = $pcid > 0 ? (string) (int) $pcid : 'NULL';
        $in_vals = (int) $pid . ', ' . $pcid_sql . ', ' . (int) $bid . ', ' . (int) $mid
            . ', ' . $in_weight . ', ' . $stock_purity . ', ' . $qty . ', ' . $final_w
            . ', ' . $rate . ', ' . $line_amt . ', ' . $in_weight . ', ' . $qty
            . ", 'inward', '$txn_esc', 1, NOW()";
        if ($tbl_stock_has_barcode) {
            $in_cols = 'product_id, product_characteristic_id, barcode, branch_id, metal_id, opening_weight, opening_purity, opening_qty, final_weight, rate, value, current_weight, current_qty, stock_type, transaction_date, status, created_at';
            $bc_sql = $barcode_plain !== '' ? "'" . mysqli_real_escape_string($conn, $barcode_plain) . "'" : 'NULL';
            $in_vals = (int) $pid . ', ' . $pcid_sql . ', ' . $bc_sql . ', ' . (int) $bid . ', ' . (int) $mid
                . ', ' . $in_weight . ', ' . $stock_purity . ', ' . $qty . ', ' . $final_w
                . ', ' . $rate . ', ' . $line_amt . ', ' . $in_weight . ', ' . $qty
                . ", 'inward', '$txn_esc', 1, NOW()";
        }
        if ($tbl_stock_has_reference) {
            $in_cols = str_replace('transaction_date, status, created_at', 'transaction_date, status, reference_id, reference_type, created_at', $in_cols);
            $in_vals = str_replace("'$txn_esc', 1, NOW()", "'$txn_esc', 1, " . (int) $voucher_id . ", 'receipt_voucher', NOW()", $in_vals);
        }
        if (!mysqli_query($conn, 'INSERT INTO tbl_stock (' . $in_cols . ') VALUES (' . $in_vals . ')')) {
            throw new Exception('Receipt voucher inward stock insert failed: ' . mysqli_error($conn));
        }

        require_once __DIR__ . '/stock_history_audit_journal.php';
        $product_name_plain = trim((string) ($p['product'] ?? $p['product_name'] ?? ''));
        if ($product_name_plain === '' && function_exists('getRecord')) {
            $pn_row = getRecord('SELECT name FROM tbl_products WHERE id = ' . (int) $pid . ' LIMIT 1');
            if ($pn_row && isset($pn_row['name'])) {
                $product_name_plain = trim((string) $pn_row['name']);
            }
        }
        $sj_key = 'RV' . (int) $voucher_id . 'I' . (int) $line_id;
        if (strlen($sj_key) > 48) {
            $sj_key = 'R' . (int) $voucher_id . 'i' . (int) $line_id;
        }
        auragold_stock_history_audit_insert_row($conn, [
            'sj_invoice_no' => $sj_key,
            'item_id' => 0,
            'invoice_id' => 0,
            'invoice_no' => trim($doc_no_plain),
            'sj_date' => $doc_date,
            'barcode' => $barcode_plain,
            'product_id' => $pid,
            'product_characteristic_id' => $pcid,
            'product_name' => $product_name_plain,
            'metal_id' => $mid,
            'metal_type' => function_exists('auragold_stock_history_metal_type') ? auragold_stock_history_metal_type($conn, $mid) : '',
            'quantity' => $qty,
            'gross_weight' => $gross,
            'less_weight' => 0,
            'net_weight' => $final_w,
            'purity' => $stock_purity > 0 ? ($stock_purity <= 1 ? $stock_purity * 100 : $stock_purity) : 0,
            'purity_weight' => $pure,
            'pure_weight' => $pure,
            'final_weight' => $final_w,
            'rate' => $rate,
            'amount' => $line_amt,
            'making_amount' => 0,
            'tax_amount' => 0,
            'net_amount' => $line_amt,
            'net_amt_with_tax' => $line_amt,
            'rfid_code' => '',
            'voucher_type' => 'Receipt Voucher',
            'design_no' => '',
            'category' => trim((string) ($p['diamond_category'] ?? '')),
            'comment' => 'auragold_doc|src=rv_in|rid=' . (int) $voucher_id . '|lid=' . (int) $line_id . '|seq=' . (int) $pay_seq . '|',
        ]);
    }
}

if (!function_exists('auragold_sale_order_delete_customer_payments')) {
    /** Delete replaceable sale-order payment rows; keep internal JWO/issue tagged rows. */
    function auragold_sale_order_delete_customer_payments(mysqli $conn, int $order_id): void
    {
        if ($order_id < 1 || !function_exists('getList')) {
            return;
        }
        $rows = getList('SELECT id, payment_details FROM tbl_sale_order_payments WHERE order_id = ' . (int) $order_id);
        if (!is_array($rows)) {
            return;
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (auragold_sale_order_payment_row_is_internal_only($row)) {
                continue;
            }
            mysqli_query($conn, 'DELETE FROM tbl_sale_order_payments WHERE id = ' . (int) ($row['id'] ?? 0));
        }
    }
}
