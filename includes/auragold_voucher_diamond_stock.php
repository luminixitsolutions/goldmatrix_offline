<?php

/**
 * Multi-voucher diamond serial stock allocation — shared by sale order, invoices, etc.
 * Logs to tbl_voucher_diamond_stock_issue; deducts tbl_stock + outward + stock history audit.
 */

if (!function_exists('auragold_voucher_diamond_allowed_kinds')) {
    /**
     * @return array<int, string>
     */
    function auragold_voucher_diamond_allowed_kinds(): array
    {
        return [
            'sale_order',
            'sale_invoice',
            'sale_quotation',
            'sale_return',
            'purchase_invoice',
            'purchase_quotation',
            'purchase_return',
            'payment_voucher',
            'receipt_voucher',
            'advance_payment',
            'old_jewelry_scrap_invoice',
            'material_issue',
            'material_receive',
            'jobwork_order',
            'jobwork_invoice',
            'old_jewellery_scrap_stock_in',
            'consignment_in',
            'consignment_out',
            'pos_sale_invoice',
        ];
    }
}

if (!function_exists('auragold_voucher_diamond_kind_valid')) {
    function auragold_voucher_diamond_kind_valid(string $kind): bool
    {
        $kind = strtolower(trim($kind));

        return $kind !== '' && in_array($kind, auragold_voucher_diamond_allowed_kinds(), true);
    }
}

if (!function_exists('auragold_voucher_diamond_journal_meta')) {
    /**
     * @return array{0:string,1:string,2:string} [label, sj_prefix, comment_tag]
     */
    function auragold_voucher_diamond_journal_meta(string $kind): array
    {
        $kind = strtolower(trim($kind));
        static $map = [
            'sale_order' => ['Sales Order', 'so', 'sod'],
            'sale_invoice' => ['Sale Invoice', 'si', 'sid'],
            'sale_quotation' => ['Sale Quotation', 'sq', 'sqd'],
            'sale_return' => ['Sale Return', 'sr', 'srd'],
            'purchase_invoice' => ['Purchase Invoice', 'pi', 'pid'],
            'purchase_quotation' => ['Purchase Quotation', 'pq', 'pqd'],
            'purchase_return' => ['Purchase Return', 'pr', 'prd'],
            'payment_voucher' => ['Payment Voucher', 'pv', 'pvd'],
            'receipt_voucher' => ['Receipt Voucher', 'rv', 'rvd'],
            'advance_payment' => ['Advance Payment', 'ap', 'apd'],
            'old_jewelry_scrap_invoice' => ['Old Jewelry Scrap Invoice', 'oj', 'ojd'],
            'material_issue' => ['Material Issue', 'mi', 'mid'],
            'material_receive' => ['Material Receive', 'mr', 'mrd'],
            'jobwork_order' => ['Jobwork Order', 'jo', 'jod'],
            'jobwork_invoice' => ['Jobwork Invoice', 'ji', 'jid'],
            'old_jewellery_scrap_stock_in' => ['Scrap Stock In', 'zi', 'zid'],
            'consignment_in' => ['Consignment In', 'ci', 'cid'],
            'consignment_out' => ['Consignment Out', 'co', 'cod'],
            'pos_sale_invoice' => ['POS Sale Invoice', 'ps', 'psd'],
        ];

        return $map[$kind] ?? ['Document', 'dc', 'vdd'];
    }
}

if (!function_exists('auragold_voucher_ensure_voucher_gem_issue_source_column')) {
    function auragold_voucher_ensure_voucher_gem_issue_source_column(mysqli $conn, string $tbl): void
    {
        if (!function_exists('auragold_tbl_has_column')) {
            return;
        }
        if (!auragold_tbl_has_column($conn, $tbl, 'source_issue_id')) {
            @mysqli_query(
                $conn,
                'ALTER TABLE `' . preg_replace('/[^a-zA-Z0-9_]/', '', $tbl) . '` '
                . 'ADD COLUMN `source_issue_id` int(11) DEFAULT NULL AFTER `voucher_id`, '
                . 'ADD KEY `idx_vd_source_issue` (`source_issue_id`)'
            );
        }
    }
}

if (!function_exists('auragold_voucher_received_sum_for_source_issue')) {
    /**
     * @return array{weight: float, qty: float}
     */
    function auragold_voucher_received_sum_for_source_issue(mysqli $conn, string $tbl, int $source_issue_id): array
    {
        $out = ['weight' => 0.0, 'qty' => 0.0];
        if ($source_issue_id < 1 || !function_exists('getRecord')) {
            return $out;
        }
        auragold_voucher_ensure_voucher_gem_issue_source_column($conn, $tbl);
        if (!function_exists('auragold_tbl_has_column') || !auragold_tbl_has_column($conn, $tbl, 'source_issue_id')) {
            return $out;
        }
        $r = getRecord(
            'SELECT COALESCE(SUM(weight),0) AS w, COALESCE(SUM(qty),0) AS q FROM `' . preg_replace('/[^a-zA-Z0-9_]/', '', $tbl) . '` '
            . " WHERE voucher_kind IN ('material_receive', 'jobwork_invoice') AND source_issue_id = " . (int) $source_issue_id
        );
        if (is_array($r)) {
            $out['weight'] = (float) ($r['w'] ?? 0);
            $out['qty'] = (float) ($r['q'] ?? 0);
        }

        return $out;
    }
}

if (!function_exists('auragold_voucher_ensure_diamond_issue_table')) {
    function auragold_voucher_ensure_diamond_issue_table(mysqli $conn): void
    {
        $tbl = 'tbl_voucher_diamond_stock_issue';
        @mysqli_query(
            $conn,
            'CREATE TABLE IF NOT EXISTS `' . $tbl . '` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `voucher_kind` varchar(64) NOT NULL,
              `voucher_id` int(11) NOT NULL,
              `stock_id` int(11) NOT NULL,
              `barcode` varchar(100) DEFAULT NULL,
              `product_name` varchar(255) DEFAULT NULL,
              `diamond_category` varchar(100) DEFAULT NULL,
              `weight` decimal(14,4) NOT NULL DEFAULT 0,
              `qty` decimal(14,4) NOT NULL DEFAULT 0,
              `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_vd_kind_id` (`voucher_kind`, `voucher_id`),
              KEY `idx_vd_stock` (`stock_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        auragold_voucher_ensure_voucher_gem_issue_source_column($conn, $tbl);
    }
}

if (!function_exists('auragold_voucher_list_diamond_issue_rows_for_kind')) {
    /**
     * Rows for UI tables / get-document embed (barcode, product_name, diamond_category, qty, weight).
     *
     * @return array<int, array<string, mixed>>
     */
    function auragold_voucher_list_diamond_issue_rows_for_kind(mysqli $conn, string $kind, int $voucher_id): array
    {
        $kind = strtolower(trim($kind));
        if ($voucher_id < 1 || !auragold_voucher_diamond_kind_valid($kind)) {
            return [];
        }
        auragold_voucher_ensure_diamond_issue_table($conn);
        $out = [];
        $kesc = mysqli_real_escape_string($conn, $kind);
        $tmp = function_exists('getList')
            ? getList(
                'SELECT id, barcode, product_name, diamond_category, weight, qty '
                . 'FROM tbl_voucher_diamond_stock_issue WHERE voucher_kind = \'' . $kesc . '\' AND voucher_id = ' . (int) $voucher_id
                . ' ORDER BY id ASC'
            )
            : [];
        if (is_array($tmp)) {
            foreach ($tmp as $row) {
                $out[] = $row;
            }
        }
        if ($kind === 'sale_order') {
            $tLegacy = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_order_diamond_stock_issue'");
            if ($tLegacy && mysqli_num_rows($tLegacy) > 0) {
                mysqli_free_result($tLegacy);
                $leg = function_exists('getList')
                    ? getList(
                        'SELECT id, barcode, product_name, diamond_category, weight, qty '
                        . 'FROM tbl_sale_order_diamond_stock_issue WHERE order_id = ' . (int) $voucher_id . ' ORDER BY id ASC'
                    )
                    : [];
                if (is_array($leg)) {
                    foreach ($leg as $row) {
                        $out[] = $row;
                    }
                }
            } elseif ($tLegacy) {
                mysqli_free_result($tLegacy);
            }
        }

        return $out;
    }
}

if (!function_exists('auragold_voucher_restore_inward_diamond_stock_after_removal')) {
    /**
     * Undo voucher diamond allocation: add weight/qty back to inward tbl_stock and soft-clear matching outward row.
     */
    function auragold_voucher_restore_inward_diamond_stock_after_removal(
        mysqli $conn,
        int $stock_id,
        float $add_wt,
        float $add_qty,
        string $bc_raw,
        bool &$tx_ok,
        string &$tx_err
    ): void {
        if (!$tx_ok || $stock_id < 1 || $add_wt <= 0.0000001) {
            return;
        }
        if (!function_exists('getRecord')) {
            return;
        }
        $st = getRecord('SELECT id, current_weight, current_qty, rate FROM tbl_stock WHERE id = ' . $stock_id . ' LIMIT 1');
        if (!$st || empty($st['id'])) {
            return;
        }
        $add_wt = round(max(0.0, $add_wt), 4);
        $prev_cw = (float) ($st['current_weight'] ?? 0);
        $prev_cq = (float) ($st['current_qty'] ?? 0);
        $rate = (float) ($st['rate'] ?? 0);
        $new_cw = round($prev_cw + $add_wt, 4);
        $add_q = $add_qty > 0.0000001 ? round($add_qty, 4) : 0.0;
        if ($add_q <= 0.0000001 && $prev_cw > 0.0000001 && $prev_cq > 0.0000001) {
            $add_q = round($add_wt * ($prev_cq / $prev_cw), 4);
        }
        $new_cq = round($prev_cq + $add_q, 4);
        $new_val = round($rate * $new_cw, 2);
        $upd = 'UPDATE tbl_stock SET current_weight = ' . $new_cw . ', final_weight = ' . $new_cw . ', current_qty = ' . $new_cq . ', value = ' . $new_val . ' WHERE id = ' . $stock_id . ' LIMIT 1';
        if (!@mysqli_query($conn, $upd)) {
            $tx_ok = false;
            $tx_err = 'Could not restore inward diamond stock: ' . mysqli_error($conn);

            return;
        }
        $bc_trim = trim($bc_raw);
        if ($bc_trim !== '') {
            $bc_esc = mysqli_real_escape_string($conn, $bc_trim);
            $out = getRecord(
                'SELECT id FROM tbl_stock WHERE status = 1'
                . " AND LOWER(TRIM(COALESCE(stock_type,''))) = 'outward'"
                . " AND barcode = '" . $bc_esc . "'"
                . ' AND ABS(COALESCE(current_weight,0) - ' . $add_wt . ') < 0.0001'
                . ' ORDER BY id DESC LIMIT 1'
            );
            if ($out && !empty($out['id'])) {
                $oid = (int) $out['id'];
                @mysqli_query(
                    $conn,
                    'UPDATE tbl_stock SET status = 0, current_weight = 0, current_qty = 0, final_weight = 0, value = 0 WHERE id = ' . $oid . ' LIMIT 1'
                );
            }
        }
    }
}

if (!function_exists('auragold_voucher_remove_diamond_issue')) {
    /**
     * Delete one tbl_voucher_diamond_stock_issue (or legacy sale-order issue) row and return stock.
     *
     * @return bool True when a row was deleted and stock restore attempted
     */
    function auragold_voucher_remove_diamond_issue(
        mysqli $conn,
        string $voucher_kind,
        int $voucher_id,
        int $issue_id,
        bool &$tx_ok,
        string &$tx_err
    ): bool {
        if (!$tx_ok || $issue_id < 1 || $voucher_id < 1) {
            $tx_err = 'Invalid parameters.';
            $tx_ok = false;

            return false;
        }
        $voucher_kind = strtolower(trim($voucher_kind));
        if (!auragold_voucher_diamond_kind_valid($voucher_kind)) {
            $tx_err = 'Invalid voucher type.';
            $tx_ok = false;

            return false;
        }
        auragold_voucher_ensure_diamond_issue_table($conn);
        $kind_esc = mysqli_real_escape_string($conn, $voucher_kind);
        $rec = function_exists('getRecord')
            ? getRecord(
                'SELECT * FROM tbl_voucher_diamond_stock_issue WHERE id = ' . (int) $issue_id
                . " AND voucher_kind = '" . $kind_esc . "' AND voucher_id = " . (int) $voucher_id . ' LIMIT 1'
            )
            : null;
        $legacy_tbl = '';
        if (!$rec || empty($rec['id'])) {
            if ($voucher_kind === 'sale_order') {
                require_once __DIR__ . '/auragold_sale_order_diamond_stock.php';
                auragold_sale_order_ensure_diamond_issue_table($conn);
                $rec = function_exists('getRecord')
                    ? getRecord(
                        'SELECT * FROM tbl_sale_order_diamond_stock_issue WHERE id = ' . (int) $issue_id
                        . ' AND order_id = ' . (int) $voucher_id . ' LIMIT 1'
                    )
                    : null;
                $legacy_tbl = 'tbl_sale_order_diamond_stock_issue';
            }
        }
        if (!$rec || empty($rec['id'])) {
            $tx_err = 'Allocation not found.';
            $tx_ok = false;

            return false;
        }
        $tbl = $legacy_tbl !== '' ? $legacy_tbl : 'tbl_voucher_diamond_stock_issue';
        $del_id = (int) $rec['id'];
        $sid = (int) ($rec['stock_id'] ?? 0);
        $wt = (float) ($rec['weight'] ?? 0);
        $qt = (float) ($rec['qty'] ?? 0);
        $bc = trim((string) ($rec['barcode'] ?? ''));

        if (!@mysqli_query($conn, 'DELETE FROM `' . $tbl . '` WHERE id = ' . $del_id . ' LIMIT 1')) {
            $tx_ok = false;
            $tx_err = 'Could not remove allocation: ' . mysqli_error($conn);

            return false;
        }
        if ($sid > 0 && $wt > 0.0000001) {
            auragold_voucher_restore_inward_diamond_stock_after_removal($conn, $sid, $wt, $qt, $bc, $tx_ok, $tx_err);
        }

        return $tx_ok;
    }
}

if (!function_exists('auragold_voucher_receive_stock_inward_partial')) {
    /**
     * Material receive: add weight/qty back to inward stock and reduce matching outward rows (partial OK).
     */
    function auragold_voucher_receive_stock_inward_partial(
        mysqli $conn,
        int $stock_id,
        float $add_wt,
        float $add_qty,
        string $bc_raw,
        bool &$tx_ok,
        string &$tx_err
    ): void {
        if (!$tx_ok || $stock_id < 1 || $add_wt <= 0.0000001) {
            return;
        }
        if (!function_exists('getRecord')) {
            return;
        }
        $st = getRecord('SELECT id, current_weight, current_qty, rate FROM tbl_stock WHERE id = ' . $stock_id . ' LIMIT 1');
        if (!$st || empty($st['id'])) {
            $tx_ok = false;
            $tx_err = 'Inward stock row not found (id ' . $stock_id . ').';

            return;
        }
        $add_wt = round(max(0.0, $add_wt), 4);
        $prev_cw = (float) ($st['current_weight'] ?? 0);
        $prev_cq = (float) ($st['current_qty'] ?? 0);
        $rate = (float) ($st['rate'] ?? 0);
        $add_q = $add_qty > 0.0000001 ? round($add_qty, 4) : 0.0;
        if ($add_q <= 0.0000001 && $prev_cw > 0.0000001 && $prev_cq > 0.0000001) {
            $add_q = round($add_wt * ($prev_cq / $prev_cw), 4);
        } elseif ($add_q <= 0.0000001) {
            $add_q = 1.0;
        }
        $new_cw = round($prev_cw + $add_wt, 4);
        $new_cq = round($prev_cq + $add_q, 4);
        $new_val = round($rate * $new_cw, 2);
        $upd = 'UPDATE tbl_stock SET current_weight = ' . $new_cw . ', final_weight = ' . $new_cw
            . ', current_qty = ' . $new_cq . ', value = ' . $new_val . ' WHERE id = ' . $stock_id . ' LIMIT 1';
        if (!@mysqli_query($conn, $upd)) {
            $tx_ok = false;
            $tx_err = 'Could not restore inward stock: ' . mysqli_error($conn);

            return;
        }

        $remaining = $add_wt;
        $bc_trim = trim($bc_raw);
        if ($bc_trim === '' || !function_exists('getList')) {
            return;
        }
        $bc_esc = mysqli_real_escape_string($conn, $bc_trim);
        $outs = getList(
            'SELECT id, current_weight, current_qty, rate FROM tbl_stock WHERE status = 1'
            . " AND LOWER(TRIM(COALESCE(stock_type,''))) = 'outward'"
            . " AND barcode = '" . $bc_esc . "'"
            . ' AND COALESCE(current_weight,0) > 0.00001'
            . ' ORDER BY id DESC'
        );
        if (!is_array($outs)) {
            return;
        }
        foreach ($outs as $out) {
            if ($remaining <= 0.0000001) {
                break;
            }
            $oid = (int) ($out['id'] ?? 0);
            $ow = (float) ($out['current_weight'] ?? 0);
            if ($oid < 1 || $ow <= 0.0000001) {
                continue;
            }
            $reduce = min($remaining, $ow);
            $new_ow = round($ow - $reduce, 4);
            $oq = (float) ($out['current_qty'] ?? 0);
            $reduce_q = $ow > 0.0000001 ? round($oq * ($reduce / $ow), 4) : 0.0;
            $new_oq = max(0.0, round($oq - $reduce_q, 4));
            if ($new_ow <= 0.0000001) {
                @mysqli_query(
                    $conn,
                    'UPDATE tbl_stock SET status = 0, current_weight = 0, current_qty = 0, final_weight = 0, value = 0 WHERE id = ' . $oid . ' LIMIT 1'
                );
            } else {
                $or = (float) ($out['rate'] ?? 0);
                $oval = round($or * $new_ow, 2);
                @mysqli_query(
                    $conn,
                    'UPDATE tbl_stock SET current_weight = ' . $new_ow . ', final_weight = ' . $new_ow
                    . ', current_qty = ' . $new_oq . ', value = ' . $oval . ' WHERE id = ' . $oid . ' LIMIT 1'
                );
            }
            $remaining = round($remaining - $reduce, 4);
        }
    }
}

if (!function_exists('auragold_voucher_apply_diamond_receive_allocations')) {
    /**
     * Material receive against prior material issue lines (partial weight/qty allowed).
     *
     * @param array<int, array<string, mixed>> $rows source_issue_id, weight, qty, barcode, product_name, diamond_category
     * @return array{saved:int}
     */
    function auragold_voucher_apply_diamond_receive_allocations(
        mysqli $conn,
        int $voucher_id,
        array $rows,
        string $document_no,
        string $document_date_ymd,
        bool &$tx_ok,
        string &$tx_err,
        string $receive_voucher_kind = 'material_receive'
    ): array {
        $stats = ['saved' => 0];
        if (!$tx_ok || $voucher_id < 1 || $rows === []) {
            return $stats;
        }
        $receive_voucher_kind = strtolower(trim($receive_voucher_kind));
        if ($receive_voucher_kind === '') {
            $receive_voucher_kind = 'material_receive';
        }
        if (!auragold_voucher_diamond_kind_valid($receive_voucher_kind)) {
            $tx_ok = false;
            $tx_err = 'Invalid receive voucher type for diamond stock.';

            return $stats;
        }
        auragold_voucher_ensure_diamond_issue_table($conn);
        $tbl = 'tbl_voucher_diamond_stock_issue';
        [$voucher_label, $sj_prefix, $comment_tag] = auragold_voucher_diamond_journal_meta($receive_voucher_kind);
        $document_no = trim($document_no);
        $d = trim($document_date_ymd);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            $d = date('Y-m-d');
        }
        require_once __DIR__ . '/stock_history_audit_journal.php';
        $kind_esc = mysqli_real_escape_string($conn, $receive_voucher_kind);
        $has_src_col = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, $tbl, 'source_issue_id');

        foreach ($rows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $source_issue_id = (int) ($r['source_issue_id'] ?? 0);
            $recv_wt = isset($r['weight']) ? (float) $r['weight'] : 0.0;
            $recv_qty = isset($r['qty']) ? (float) $r['qty'] : 0.0;
            if ($source_issue_id < 1 || $recv_wt <= 0.0000001) {
                continue;
            }
            $src = function_exists('getRecord')
                ? getRecord(
                    'SELECT * FROM `' . $tbl . '` WHERE id = ' . $source_issue_id
                    . " AND voucher_kind = 'material_issue' LIMIT 1"
                )
                : null;
            if (!$src || empty($src['id'])) {
                $tx_ok = false;
                $tx_err = 'Issued diamond line not found (id ' . $source_issue_id . ').';

                return $stats;
            }
            $issued_wt = (float) ($src['weight'] ?? 0);
            $issued_qty = (float) ($src['qty'] ?? 0);
            $already = $has_src_col
                ? auragold_voucher_received_sum_for_source_issue($conn, $tbl, $source_issue_id)
                : ['weight' => 0.0, 'qty' => 0.0];
            $bal_wt = max(0.0, round($issued_wt - (float) $already['weight'], 4));
            if ($recv_wt > $bal_wt + 0.0001) {
                $tx_ok = false;
                $tx_err = 'Receive weight exceeds balance for barcode ' . trim((string) ($src['barcode'] ?? ''))
                    . ' (balance ' . $bal_wt . ').';

                return $stats;
            }
            if ($recv_qty <= 0.0000001 && $issued_qty > 0.0000001 && $issued_wt > 0.0000001) {
                $recv_qty = round($issued_qty * ($recv_wt / $issued_wt), 4);
            }
            $issued_sid = (int) ($src['stock_id'] ?? 0);
            $issued_bc = trim((string) ($src['barcode'] ?? ($r['barcode'] ?? '')));
            $target_pcid = (int) ($r['product_characteristic_id'] ?? 0);
            $target_sid = (int) ($r['stock_id'] ?? 0);
            if ($target_pcid > 0) {
                $target_st = auragold_voucher_resolve_inward_stock_by_characteristic($conn, $target_pcid, $target_sid);
                if (!$target_st || empty($target_st['id'])) {
                    $tx_ok = false;
                    $tx_err = 'No inward stock found for selected diamond product.';

                    return $stats;
                }
                $target_sid = (int) $target_st['id'];
            } elseif ($target_sid < 1 || $target_sid === $issued_sid) {
                $target_sid = $issued_sid;
            }
            if ($target_sid < 1) {
                $tx_ok = false;
                $tx_err = 'Issued line has no stock reference for barcode ' . ($issued_bc !== '' ? $issued_bc : '?') . '.';

                return $stats;
            }
            auragold_voucher_receive_stock_inward_partial($conn, $target_sid, $recv_wt, $recv_qty, $issued_bc, $tx_ok, $tx_err);
            if (!$tx_ok) {
                return $stats;
            }

            $pn_esc = mysqli_real_escape_string($conn, trim((string) ($r['product_name'] ?? $src['product_name'] ?? '')));
            $cat_esc = mysqli_real_escape_string($conn, trim((string) ($r['diamond_category'] ?? $src['diamond_category'] ?? 'Diamonds')));
            $bc_esc = mysqli_real_escape_string($conn, $issued_bc);
            $w_log = round($recv_wt, 4);
            $q_log = round(max(0.0, $recv_qty), 4);
            $src_sql = $has_src_col ? (string) (int) $source_issue_id : 'NULL';
            $ins = "INSERT INTO `$tbl` (voucher_kind, voucher_id, source_issue_id, stock_id, barcode, product_name, diamond_category, weight, qty, created_at) VALUES ("
                . "'" . $kind_esc . "', " . (int) $voucher_id . ', ' . $src_sql . ', ' . $target_sid . ", '" . $bc_esc . "', '" . $pn_esc . "', '" . $cat_esc . "', "
                . $w_log . ', ' . $q_log . ', NOW())';
            if (!@mysqli_query($conn, $ins)) {
                $tx_ok = false;
                $tx_err = 'Could not log diamond stock receive: ' . mysqli_error($conn);

                return $stats;
            }
            $alloc_id = (int) mysqli_insert_id($conn);
            if ($document_no !== '' && $alloc_id > 0) {
                auragold_stock_history_audit_for_document_barcode_line(
                    $conn,
                    $voucher_label,
                    $document_no,
                    $d,
                    $sj_prefix,
                    $voucher_id,
                    $alloc_id,
                    $comment_tag,
                    [
                        'barcode' => $bc,
                        'product_id' => 0,
                        'product_characteristic_id' => 0,
                        'product_name' => trim((string) ($r['product_name'] ?? $src['product_name'] ?? '')),
                        'quantity' => $q_log > 0.0000001 ? $q_log : 1,
                        'gross_weight' => $w_log,
                        'final_weight' => $w_log,
                        'net_weight' => $w_log,
                        'category' => trim((string) ($r['diamond_category'] ?? $src['diamond_category'] ?? 'Diamonds')),
                        'diamond_category' => trim((string) ($r['diamond_category'] ?? $src['diamond_category'] ?? 'Diamonds')),
                    ]
                );
            }
            $stats['saved']++;
        }

        return $stats;
    }
}

if (!function_exists('auragold_voucher_stock_inward_where_sql')) {
    function auragold_voucher_stock_inward_where_sql(): string
    {
        return "status = 1 AND (stock_type IS NULL OR LOWER(TRIM(stock_type)) <> 'outward')";
    }
}

if (!function_exists('auragold_voucher_stock_effective_weight')) {
    /** Matches diamond_stock_list_sql_include per-row weight (current, else opening). */
    function auragold_voucher_stock_effective_weight(array $st): float
    {
        $stt = strtolower(trim((string) ($st['stock_type'] ?? '')));
        if ($stt === 'outward') {
            return 0.0;
        }
        $cw = (float) ($st['current_weight'] ?? 0);
        if ($cw > 0.0000001) {
            return $cw;
        }

        return max(0.0, (float) ($st['opening_weight'] ?? 0));
    }
}

if (!function_exists('auragold_voucher_stock_effective_qty')) {
    function auragold_voucher_stock_effective_qty(array $st): float
    {
        $stt = strtolower(trim((string) ($st['stock_type'] ?? '')));
        if ($stt === 'outward') {
            return 0.0;
        }
        $cq = (float) ($st['current_qty'] ?? 0);
        if ($cq > 0.0000001) {
            return $cq;
        }

        return max(0.0, (float) ($st['opening_qty'] ?? 0));
    }
}

if (!function_exists('auragold_voucher_barcode_inward_balance')) {
    /**
     * Barcode-level balance (same formula as diamond_stock_list_sql_include bal_wt / bal_qty).
     *
     * @return array{bal_wt:float, bal_qty:float}
     */
    function auragold_voucher_barcode_inward_balance(mysqli $conn, string $barcode): array
    {
        $bc = trim($barcode);
        if ($bc === '' || !function_exists('getRecord')) {
            return ['bal_wt' => 0.0, 'bal_qty' => 0.0];
        }
        $bc_esc = mysqli_real_escape_string($conn, $bc);
        $in_types = "'opening','purchase','stock_journal','balance','sale_return'";
        $row = getRecord(
            'SELECT'
            . " (SUM(CASE WHEN stock_type IN ($in_types) THEN COALESCE(NULLIF(current_weight, 0), opening_weight, 0) ELSE 0 END)"
            . "  - SUM(CASE WHEN stock_type = 'outward' THEN COALESCE(NULLIF(current_weight, 0), opening_weight, 0) ELSE 0 END)) AS bal_wt,"
            . " (SUM(CASE WHEN stock_type IN ($in_types) THEN COALESCE(NULLIF(current_qty, 0), opening_qty, 0) ELSE 0 END)"
            . "  - SUM(CASE WHEN stock_type = 'outward' THEN COALESCE(NULLIF(current_qty, 0), opening_qty, 0) ELSE 0 END)) AS bal_qty"
            . " FROM tbl_stock WHERE status = 1 AND barcode = '" . $bc_esc . "'"
        );

        return [
            'bal_wt' => (float) ($row['bal_wt'] ?? 0),
            'bal_qty' => (float) ($row['bal_qty'] ?? 0),
        ];
    }
}

if (!function_exists('auragold_voucher_resolve_inward_stock_by_characteristic')) {
    /**
     * Find inward stock row for a product characteristic (Material Receive target product).
     *
     * @return array<string, mixed>|null
     */
    function auragold_voucher_resolve_inward_stock_by_characteristic(
        mysqli $conn,
        int $product_characteristic_id,
        int $prefer_stock_id = 0
    ): ?array {
        if ($product_characteristic_id < 1 || !function_exists('getRecord')) {
            return null;
        }
        $where = auragold_voucher_stock_inward_where_sql();
        if ($prefer_stock_id > 0) {
            $st = getRecord(
                'SELECT * FROM tbl_stock WHERE id = ' . $prefer_stock_id
                . ' AND ' . $where
                . ' AND product_characteristic_id = ' . (int) $product_characteristic_id
                . ' LIMIT 1'
            );
            if ($st && !empty($st['id'])) {
                return $st;
            }
        }

        return getRecord(
            'SELECT * FROM tbl_stock WHERE ' . $where
            . ' AND product_characteristic_id = ' . (int) $product_characteristic_id
            . ' ORDER BY COALESCE(NULLIF(current_weight, 0), opening_weight, 0) DESC, id DESC LIMIT 1'
        ) ?: null;
    }
}

if (!function_exists('auragold_voucher_resolve_inward_stock_row')) {
    /**
     * Resolve inward stock row for allocation (aligned with diamond stock list pick_id).
     *
     * @return array<string, mixed>|null
     */
    function auragold_voucher_resolve_inward_stock_row(mysqli $conn, int $prefer_stock_id, string $barcode): ?array
    {
        if (!function_exists('getRecord')) {
            return null;
        }
        $where = auragold_voucher_stock_inward_where_sql();
        if ($prefer_stock_id > 0) {
            $st = getRecord('SELECT * FROM tbl_stock WHERE id = ' . $prefer_stock_id . ' AND ' . $where . ' LIMIT 1');
            if ($st && auragold_voucher_stock_effective_weight($st) > 0.0000001) {
                return $st;
            }
        }
        $bc = trim($barcode);
        if ($bc === '') {
            return null;
        }
        $bc_esc = mysqli_real_escape_string($conn, $bc);
        $st = getRecord(
            'SELECT * FROM tbl_stock WHERE ' . $where
            . " AND barcode = '" . $bc_esc . "'"
            . ' AND COALESCE(current_weight, 0) > 0'
            . ' ORDER BY current_weight DESC, id DESC LIMIT 1'
        );
        if ($st) {
            return $st;
        }

        return getRecord(
            'SELECT * FROM tbl_stock WHERE ' . $where
            . " AND barcode = '" . $bc_esc . "'"
            . ' AND (COALESCE(NULLIF(current_weight, 0), opening_weight, 0) > 0.00001'
            . ' OR COALESCE(NULLIF(current_qty, 0), opening_qty, 0) > 0.00001)'
            . ' ORDER BY COALESCE(NULLIF(current_weight, 0), opening_weight, 0) DESC,'
            . ' COALESCE(NULLIF(current_qty, 0), opening_qty, 0) DESC, id DESC LIMIT 1'
        ) ?: null;
    }
}

if (!function_exists('auragold_voucher_apply_diamond_allocations')) {
    /**
     * @param array<int, array<string, mixed>> $rows Each: stock_id, barcode (opt), qty, weight, product_name (opt), diamond_category (opt)
     * @return array{saved:int}
     */
    function auragold_voucher_apply_diamond_allocations(
        mysqli $conn,
        string $voucher_kind,
        int $voucher_id,
        array $rows,
        string $document_no,
        string $document_date_ymd,
        bool &$tx_ok,
        string &$tx_err
    ): array {
        $stats = ['saved' => 0];
        $voucher_kind = strtolower(trim($voucher_kind));
        if (!$tx_ok || $voucher_id < 1 || $rows === [] || !auragold_voucher_diamond_kind_valid($voucher_kind)) {
            return $stats;
        }
        if ($voucher_kind === 'material_receive') {
            $receive_rows = [];
            foreach ($rows as $r) {
                if (!is_array($r)) {
                    continue;
                }
                if ((int) ($r['source_issue_id'] ?? 0) > 0) {
                    $receive_rows[] = $r;
                }
            }
            if ($receive_rows !== []) {
                return auragold_voucher_apply_diamond_receive_allocations(
                    $conn,
                    $voucher_id,
                    $receive_rows,
                    $document_no,
                    $document_date_ymd,
                    $tx_ok,
                    $tx_err
                );
            }

            return $stats;
        }
        auragold_voucher_ensure_diamond_issue_table($conn);
        $tbl = 'tbl_voucher_diamond_stock_issue';
        [$voucher_label, $sj_prefix, $comment_tag] = auragold_voucher_diamond_journal_meta($voucher_kind);
        $document_no = trim($document_no);
        $d = trim($document_date_ymd);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            $d = date('Y-m-d');
        }

        require_once __DIR__ . '/stock_history_audit_journal.php';
        $kind_esc = mysqli_real_escape_string($conn, $voucher_kind);

        foreach ($rows as $r) {
            if (!is_array($r)) {
                continue;
            }
            $req_bc = trim((string) ($r['barcode'] ?? ''));
            $sid = (int) ($r['stock_id'] ?? 0);

            $alloc_qty = isset($r['qty']) ? (float) $r['qty'] : 0.0;
            $alloc_wt = isset($r['weight']) ? (float) $r['weight'] : 0.0;
            if ($alloc_qty <= 0.0000001 && $alloc_wt <= 0.0000001) {
                continue;
            }

            $st = auragold_voucher_resolve_inward_stock_row($conn, $sid, $req_bc);
            if (!$st && $sid > 0 && function_exists('getRecord')) {
                $st = getRecord('SELECT * FROM tbl_stock WHERE id = ' . $sid . ' AND status = 1 LIMIT 1');
            }
            if (!$st) {
                $tx_ok = false;
                $tx_err = $sid > 0
                    ? ('Stock row not found or inactive (id ' . $sid . ').')
                    : ('No stock found for barcode ' . ($req_bc !== '' ? $req_bc : '?') . '.');

                return $stats;
            }
            $sid = (int) ($st['id'] ?? 0);
            $stbc = trim((string) ($st['barcode'] ?? ''));
            if ($req_bc !== '' && $stbc !== '' && strcasecmp($stbc, $req_bc) !== 0) {
                $st_alt = auragold_voucher_resolve_inward_stock_row($conn, 0, $req_bc);
                if ($st_alt) {
                    $st = $st_alt;
                    $sid = (int) ($st['id'] ?? 0);
                    $stbc = trim((string) ($st['barcode'] ?? ''));
                } else {
                    $tx_ok = false;
                    $tx_err = 'Barcode does not match stock row.';

                    return $stats;
                }
            }

            $avail = auragold_voucher_stock_effective_weight($st);
            $prev_cq = auragold_voucher_stock_effective_qty($st);
            $bc_for_bal = $req_bc !== '' ? $req_bc : $stbc;
            if ($avail <= 0.0000001 && $bc_for_bal !== '') {
                $bal_probe = auragold_voucher_barcode_inward_balance($conn, $bc_for_bal);
                if ($bal_probe['bal_wt'] > 0.0000001) {
                    $st_retry = auragold_voucher_resolve_inward_stock_row($conn, 0, $bc_for_bal);
                    if ($st_retry) {
                        $st = $st_retry;
                        $sid = (int) ($st['id'] ?? 0);
                        $stbc = trim((string) ($st['barcode'] ?? ''));
                        $avail = auragold_voucher_stock_effective_weight($st);
                        $prev_cq = auragold_voucher_stock_effective_qty($st);
                    }
                }
            }
            if ($avail <= 0.0000001) {
                $tx_ok = false;
                $tx_err = 'No weight left for barcode ' . ($stbc ?: $req_bc) . '.';

                return $stats;
            }
            if ($bc_for_bal !== '') {
                $bal_cap = auragold_voucher_barcode_inward_balance($conn, $bc_for_bal);
                if ($bal_cap['bal_wt'] > 0.0000001 && $avail > $bal_cap['bal_wt'] + 0.0001) {
                    $avail = $bal_cap['bal_wt'];
                }
                if ($bal_cap['bal_qty'] > 0.0000001 && $prev_cq > $bal_cap['bal_qty'] + 0.0001) {
                    $prev_cq = $bal_cap['bal_qty'];
                }
            }

            $take = $alloc_wt;
            if ($take <= 0.0000001 && $alloc_qty > 0.0000001) {
                if ($prev_cq > 0.0000001) {
                    $take = $avail * ($alloc_qty / $prev_cq);
                } else {
                    $take = $avail;
                }
            }
            if ($take > $avail + 0.0001) {
                $take = $avail;
            }
            if ($take <= 0.0000001) {
                continue;
            }

            if ($alloc_qty > 0.0000001) {
                $sold_q = min($alloc_qty, $prev_cq);
            } elseif ($avail > 0.0000001) {
                $sold_q = $prev_cq * ($take / $avail);
            } else {
                $sold_q = 0.0;
            }
            $sold_q = max(0.0, min($sold_q, $prev_cq));
            $balance_wt = max(0.0, $avail - $take);
            $new_cq = max(0.0, $prev_cq - $sold_q);
            $rate = (float) ($st['rate'] ?? 0);
            $new_val = round($rate * $balance_wt, 2);

            $bal_wt_sql = round($balance_wt, 4);
            $new_cq_sql = round($new_cq, 4);
            $upd = 'UPDATE tbl_stock SET current_weight = ' . $bal_wt_sql . ', current_qty = ' . $new_cq_sql
                . ', final_weight = ' . $bal_wt_sql . ', value = ' . $new_val . ' WHERE id = ' . $sid . ' LIMIT 1';
            if (!@mysqli_query($conn, $upd)) {
                $tx_ok = false;
                $tx_err = 'Could not update stock: ' . mysqli_error($conn);

                return $stats;
            }

            $pid = (int) ($st['product_id'] ?? 0);
            $pcid = isset($st['product_characteristic_id']) ? (int) $st['product_characteristic_id'] : 0;
            $branch_id = (int) ($st['branch_id'] ?? 0);
            $metal_id = (int) ($st['metal_id'] ?? 0);
            $purity = (float) ($st['opening_purity'] ?? 0);
            $rate_sql = (float) ($st['rate'] ?? 0);
            $out_val = round($rate_sql * $take, 2);
            $barcode_sql = 'NULL';
            $bc_src = trim((string) ($st['barcode'] ?? ''));
            if ($bc_src !== '') {
                $barcode_sql = "'" . mysqli_real_escape_string($conn, $bc_src) . "'";
            }
            $pcid_sql = $pcid > 0 ? (string) $pcid : 'NULL';
            $out_sql = "INSERT INTO tbl_stock (product_id, product_characteristic_id, barcode, branch_id, metal_id, opening_weight, opening_purity, opening_qty, final_weight, rate, value, current_weight, current_qty, stock_type, transaction_date, created_at) VALUES ("
                . $pid . ', ' . $pcid_sql . ', ' . $barcode_sql . ', ' . $branch_id . ', ' . $metal_id . ', '
                . round($take, 4) . ', ' . round($purity, 4) . ', ' . round($sold_q, 4) . ', ' . round($take, 4) . ', '
                . $rate_sql . ', ' . $out_val . ', ' . round($take, 4) . ', ' . round($sold_q, 4) . ", 'outward', CURDATE(), NOW())";
            if (!@mysqli_query($conn, $out_sql)) {
                $tx_ok = false;
                $tx_err = 'Could not insert outward stock: ' . mysqli_error($conn);

                return $stats;
            }

            $product_name_plain = trim((string) ($r['product_name'] ?? ''));
            if ($product_name_plain === '' && $pid > 0 && function_exists('getRecord')) {
                $pnr = getRecord('SELECT name FROM tbl_products WHERE id = ' . $pid . ' LIMIT 1');
                if ($pnr && isset($pnr['name'])) {
                    $product_name_plain = trim((string) $pnr['name']);
                }
            }
            $pn_esc = mysqli_real_escape_string($conn, $product_name_plain);
            $cat_raw = trim((string) ($r['diamond_category'] ?? ''));
            $cat_esc = mysqli_real_escape_string($conn, $cat_raw !== '' ? $cat_raw : 'Diamonds');
            $bc_esc = mysqli_real_escape_string($conn, $bc_src !== '' ? $bc_src : $req_bc);
            $w_log = round($take, 4);
            $q_log = round($sold_q, 4);

            $ins = "INSERT INTO `$tbl` (voucher_kind, voucher_id, stock_id, barcode, product_name, diamond_category, weight, qty, created_at) VALUES ("
                . "'" . $kind_esc . "', " . (int) $voucher_id . ', ' . $sid . ", '" . $bc_esc . "', '" . $pn_esc . "', '" . $cat_esc . "', "
                . $w_log . ', ' . $q_log . ', NOW())';
            if (!@mysqli_query($conn, $ins)) {
                $tx_ok = false;
                $tx_err = 'Could not log diamond allocation: ' . mysqli_error($conn);

                return $stats;
            }
            $alloc_id = (int) mysqli_insert_id($conn);

            if ($document_no !== '' && $alloc_id > 0) {
                auragold_stock_history_audit_for_document_barcode_line(
                    $conn,
                    $voucher_label,
                    $document_no,
                    $d,
                    $sj_prefix,
                    $voucher_id,
                    $alloc_id,
                    $comment_tag,
                    [
                        'barcode' => $bc_src !== '' ? $bc_src : $req_bc,
                        'product_id' => $pid,
                        'product_characteristic_id' => $pcid,
                        'product_name' => $product_name_plain,
                        'metal_id' => $metal_id,
                        'quantity' => $q_log > 0.0000001 ? $q_log : 1,
                        'gross_weight' => $w_log,
                        'final_weight' => $w_log,
                        'net_weight' => $w_log,
                        'rate' => $rate_sql,
                        'amount' => $out_val,
                        'net_amount' => $out_val,
                        'category' => $cat_raw !== '' ? $cat_raw : 'Diamonds',
                        'diamond_category' => $cat_raw !== '' ? $cat_raw : 'Diamonds',
                    ]
                );
            }

            $stats['saved']++;
        }

        return $stats;
    }
}
