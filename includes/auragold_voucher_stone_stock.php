<?php

/**
 * Multi-voucher gemstone/stone serial stock allocation — shared across vouchers.
 */

require_once __DIR__ . '/auragold_voucher_diamond_stock.php';

if (!function_exists('auragold_voucher_stone_journal_meta')) {
    /**
     * @return array{0:string,1:string,2:string} [label, sj_prefix, comment_tag]
     */
    function auragold_voucher_stone_journal_meta(string $kind): array
    {
        $kind = strtolower(trim($kind));
        static $map = [
            'sale_order' => ['Sales Order', 'so', 'sos'],
            'sale_invoice' => ['Sale Invoice', 'si', 'sis'],
            'sale_quotation' => ['Sale Quotation', 'sq', 'sqs'],
            'sale_return' => ['Sale Return', 'sr', 'srs'],
            'purchase_invoice' => ['Purchase Invoice', 'pi', 'pis'],
            'purchase_quotation' => ['Purchase Quotation', 'pq', 'pqs'],
            'purchase_return' => ['Purchase Return', 'pr', 'prs'],
            'payment_voucher' => ['Payment Voucher', 'pv', 'pvs'],
            'receipt_voucher' => ['Receipt Voucher', 'rv', 'rvs'],
            'advance_payment' => ['Advance Payment', 'ap', 'aps'],
            'old_jewelry_scrap_invoice' => ['Old Jewelry Scrap Invoice', 'oj', 'ojs'],
            'material_issue' => ['Material Issue', 'mi', 'mis'],
            'material_receive' => ['Material Receive', 'mr', 'mrs'],
            'jobwork_order' => ['Jobwork Order', 'jo', 'jos'],
            'jobwork_invoice' => ['Jobwork Invoice', 'ji', 'jis'],
            'old_jewellery_scrap_stock_in' => ['Scrap Stock In', 'zi', 'zis'],
            'consignment_in' => ['Consignment In', 'ci', 'cis'],
            'consignment_out' => ['Consignment Out', 'co', 'cos'],
            'pos_sale_invoice' => ['POS Sale Invoice', 'ps', 'pss'],
        ];

        return $map[$kind] ?? ['Document', 'dc', 'vds'];
    }
}

if (!function_exists('auragold_voucher_ensure_stone_issue_table')) {
    function auragold_voucher_ensure_stone_issue_table(mysqli $conn): void
    {
        $tbl = 'tbl_voucher_stone_stock_issue';
        @mysqli_query(
            $conn,
            'CREATE TABLE IF NOT EXISTS `' . $tbl . '` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `voucher_kind` varchar(64) NOT NULL,
              `voucher_id` int(11) NOT NULL,
              `stock_id` int(11) NOT NULL,
              `barcode` varchar(100) DEFAULT NULL,
              `product_name` varchar(255) DEFAULT NULL,
              `stone_category` varchar(100) DEFAULT NULL,
              `weight` decimal(14,4) NOT NULL DEFAULT 0,
              `qty` decimal(14,4) NOT NULL DEFAULT 0,
              `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_vs_kind_id` (`voucher_kind`, `voucher_id`),
              KEY `idx_vs_stock` (`stock_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        auragold_voucher_ensure_voucher_gem_issue_source_column($conn, $tbl);
    }
}

if (!function_exists('auragold_voucher_list_stone_issue_rows_for_kind')) {
    /**
     * @return array<int, array<string, mixed>>
     */
    function auragold_voucher_list_stone_issue_rows_for_kind(mysqli $conn, string $kind, int $voucher_id): array
    {
        $kind = strtolower(trim($kind));
        if ($voucher_id < 1 || !auragold_voucher_diamond_kind_valid($kind)) {
            return [];
        }
        auragold_voucher_ensure_stone_issue_table($conn);
        $out = [];
        $kesc = mysqli_real_escape_string($conn, $kind);
        $tmp = function_exists('getList')
            ? getList(
                'SELECT barcode, product_name, stone_category, weight, qty '
                . 'FROM tbl_voucher_stone_stock_issue WHERE voucher_kind = \'' . $kesc . '\' AND voucher_id = ' . (int) $voucher_id
                . ' ORDER BY id ASC'
            )
            : [];
        if (is_array($tmp)) {
            foreach ($tmp as $row) {
                $out[] = $row;
            }
        }
        if ($kind === 'sale_order') {
            $tLegacy = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_order_stone_stock_issue'");
            if ($tLegacy && mysqli_num_rows($tLegacy) > 0) {
                mysqli_free_result($tLegacy);
                $leg = function_exists('getList')
                    ? getList(
                        'SELECT barcode, product_name, stone_category, weight, qty '
                        . 'FROM tbl_sale_order_stone_stock_issue WHERE order_id = ' . (int) $voucher_id . ' ORDER BY id ASC'
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

if (!function_exists('auragold_voucher_apply_stone_receive_allocations')) {
    /**
     * @param array<int, array<string, mixed>> $rows source_issue_id, weight, qty, ...
     * @return array{saved:int}
     */
    function auragold_voucher_apply_stone_receive_allocations(
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
            $tx_err = 'Invalid receive voucher type for stone stock.';

            return $stats;
        }
        auragold_voucher_ensure_stone_issue_table($conn);
        $tbl = 'tbl_voucher_stone_stock_issue';
        [$voucher_label, $sj_prefix, $comment_tag] = auragold_voucher_stone_journal_meta($receive_voucher_kind);
        $document_no = trim($document_no);
        $d = trim($document_date_ymd);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            $d = date('Y-m-d');
        }
        require_once __DIR__ . '/stock_history_audit_journal.php';
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
                $tx_err = 'Issued stone line not found (id ' . $source_issue_id . ').';

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
                if (!function_exists('auragold_voucher_resolve_inward_stock_by_characteristic')) {
                    require_once __DIR__ . '/auragold_voucher_diamond_stock.php';
                }
                $target_st = auragold_voucher_resolve_inward_stock_by_characteristic($conn, $target_pcid, $target_sid);
                if (!$target_st || empty($target_st['id'])) {
                    $tx_ok = false;
                    $tx_err = 'No inward stock found for selected gemstone product.';

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
            $cat_esc = mysqli_real_escape_string($conn, trim((string) ($r['stone_category'] ?? $src['stone_category'] ?? 'Stones')));
            $bc_esc = mysqli_real_escape_string($conn, $issued_bc);
            $w_log = round($recv_wt, 4);
            $q_log = round(max(0.0, $recv_qty), 4);
            $src_sql = $has_src_col ? (string) (int) $source_issue_id : 'NULL';
            $kind_esc = mysqli_real_escape_string($conn, $receive_voucher_kind);
            $ins = "INSERT INTO `$tbl` (voucher_kind, voucher_id, source_issue_id, stock_id, barcode, product_name, stone_category, weight, qty, created_at) VALUES ("
                . "'" . $kind_esc . "', " . (int) $voucher_id . ', ' . $src_sql . ', ' . $target_sid . ", '" . $bc_esc . "', '" . $pn_esc . "', '" . $cat_esc . "', "
                . $w_log . ', ' . $q_log . ', NOW())';
            if (!@mysqli_query($conn, $ins)) {
                $tx_ok = false;
                $tx_err = 'Could not log stone stock receive: ' . mysqli_error($conn);

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
                        'product_name' => trim((string) ($r['product_name'] ?? $src['product_name'] ?? '')),
                        'quantity' => $q_log > 0.0000001 ? $q_log : 1,
                        'gross_weight' => $w_log,
                        'final_weight' => $w_log,
                        'net_weight' => $w_log,
                        'category' => trim((string) ($r['stone_category'] ?? $src['stone_category'] ?? 'Stones')),
                    ]
                );
            }
            $stats['saved']++;
        }

        return $stats;
    }
}

if (!function_exists('auragold_voucher_apply_stone_allocations')) {
    /**
     * @param array<int, array<string, mixed>> $rows Each: stock_id, barcode (opt), qty, weight, product_name (opt), stone_category (opt)
     * @return array{saved:int}
     */
    function auragold_voucher_apply_stone_allocations(
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
                return auragold_voucher_apply_stone_receive_allocations(
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
        auragold_voucher_ensure_stone_issue_table($conn);
        $tbl = 'tbl_voucher_stone_stock_issue';
        [$voucher_label, $sj_prefix, $comment_tag] = auragold_voucher_stone_journal_meta($voucher_kind);
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
            $cat_raw = trim((string) ($r['stone_category'] ?? ''));
            $cat_esc = mysqli_real_escape_string($conn, $cat_raw !== '' ? $cat_raw : 'Stones');
            $bc_esc = mysqli_real_escape_string($conn, $bc_src !== '' ? $bc_src : $req_bc);
            $w_log = round($take, 4);
            $q_log = round($sold_q, 4);

            $ins = "INSERT INTO `$tbl` (voucher_kind, voucher_id, stock_id, barcode, product_name, stone_category, weight, qty, created_at) VALUES ("
                . "'" . $kind_esc . "', " . (int) $voucher_id . ', ' . $sid . ", '" . $bc_esc . "', '" . $pn_esc . "', '" . $cat_esc . "', "
                . $w_log . ', ' . $q_log . ', NOW())';
            if (!@mysqli_query($conn, $ins)) {
                $tx_ok = false;
                $tx_err = 'Could not log stone allocation: ' . mysqli_error($conn);

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
                        'category' => $cat_raw !== '' ? $cat_raw : 'Stones',
                        'diamond_category' => $cat_raw !== '' ? $cat_raw : 'Stones',
                    ]
                );
            }

            $stats['saved']++;
        }

        return $stats;
    }
}
