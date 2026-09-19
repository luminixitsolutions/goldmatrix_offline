<?php
/**
 * Manufacturing Outsource — Memo In & Out rows (material issue/receive + JWO diamond/stone issues).
 */

if (!function_exists('mfg_memo_fmt_wt')) {
    function mfg_memo_fmt_wt($v, $dec = 3) {
        if ($v === null || $v === '') {
            return '';
        }
        $f = (float) $v;
        if (!is_finite($f)) {
            return '';
        }
        if (abs($f) < 0.0000001) {
            return '';
        }
        return rtrim(rtrim(number_format($f, $dec, '.', ''), '0'), '.');
    }
}

if (!function_exists('mfg_memo_assign_label')) {
    function mfg_memo_assign_label($worker, $dept) {
        $w = trim((string) $worker);
        $d = trim((string) $dept);
        if ($w !== '') {
            return strtoupper($w);
        }
        if ($d !== '') {
            return strtoupper($d);
        }
        return 'FACTORY';
    }
}

if (!function_exists('mfg_memo_remark')) {
    function mfg_memo_remark($product, $desc) {
        $p = trim((string) $product);
        $d = trim((string) $desc);
        if ($p !== '' && $d !== '' && strcasecmp($p, $d) !== 0) {
            return $p . '<br/>' . $d;
        }
        return $p !== '' ? $p : $d;
    }
}

if (!function_exists('mfg_memo_table_exists')) {
    function mfg_memo_table_exists($conn, $table) {
        $t = @mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $table) . "'");
        $ok = ($t && mysqli_num_rows($t) > 0);
        if ($t) {
            mysqli_free_result($t);
        }
        return $ok;
    }
}

if (!function_exists('mfg_memo_stock_join_sql')) {
    /** @return array{join:string, clarity:string, size:string, color:string, invo:string, remark_extra:string} */
    function mfg_memo_stock_join_sql($conn, $barcode_expr, $pc_id_expr) {
        $join = '';
        $clarity = "'' AS clarity";
        $size = "'' AS stone_size";
        $color = "'' AS stone_color";
        $invo = "'' AS invo1";
        $remark_extra = "'' AS stock_remark";

        if (!mfg_memo_table_exists($conn, 'tbl_stock')) {
            return compact('join', 'clarity', 'size', 'color', 'invo', 'remark_extra');
        }

        $join = " LEFT JOIN tbl_stock mst ON mst.status = 1 AND TRIM(IFNULL(mst.barcode,'')) <> ''"
            . " AND TRIM(mst.barcode) COLLATE utf8mb4_unicode_ci = TRIM(IFNULL({$barcode_expr},'')) COLLATE utf8mb4_unicode_ci"
            . ' ORDER BY mst.id DESC LIMIT 1';
        // MySQL does not allow ORDER BY in JOIN — use subselect instead
        $join = " LEFT JOIN tbl_stock mst ON mst.id = ("
            . "SELECT s2.id FROM tbl_stock s2 WHERE s2.status = 1"
            . " AND TRIM(IFNULL(s2.barcode,'')) <> ''"
            . " AND TRIM(s2.barcode) COLLATE utf8mb4_unicode_ci = TRIM(IFNULL({$barcode_expr},'')) COLLATE utf8mb4_unicode_ci"
            . ' ORDER BY s2.id DESC LIMIT 1)';

        $pc_join_id = "COALESCE(NULLIF({$pc_id_expr},0), NULLIF(mst.product_characteristic_id,0), 0)";
        if (mfg_memo_table_exists($conn, 'tbl_product_characteristics')) {
            $join .= ' LEFT JOIN tbl_product_characteristics mpc ON mpc.id = ' . $pc_join_id;
            $cc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_product_characteristics LIKE 'clarity'");
            if ($cc && mysqli_num_rows($cc) > 0) {
                $clarity = "IFNULL(mpc.clarity,'') AS clarity";
            }
            if ($cc) {
                mysqli_free_result($cc);
            }
            $sc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_product_characteristics LIKE 'size'");
            if ($sc && mysqli_num_rows($sc) > 0) {
                $size = "IFNULL(mpc.size,'') AS stone_size";
            }
            if ($sc) {
                mysqli_free_result($sc);
            }
            $colc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_product_characteristics LIKE 'color'");
            if ($colc && mysqli_num_rows($colc) > 0) {
                $color = "IFNULL(mpc.color,'') AS stone_color";
            }
            if ($colc) {
                mysqli_free_result($colc);
            }
        }

        if (mfg_memo_table_exists($conn, 'tbl_stock_journal')) {
            $sj_chk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'stock_journal_id'");
            $has_sj = ($sj_chk && mysqli_num_rows($sj_chk) > 0);
            if ($sj_chk) {
                mysqli_free_result($sj_chk);
            }
            if ($has_sj) {
                $invo = "IFNULL((SELECT sj.invoice_no FROM tbl_stock_journal sj WHERE sj.id = mst.stock_journal_id LIMIT 1),'') AS invo1";
            }
        }

        if (mfg_memo_table_exists($conn, 'tbl_products')) {
            $pd = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_products LIKE 'description'");
            if ($pd && mysqli_num_rows($pd) > 0) {
                $remark_extra = "(SELECT IFNULL(NULLIF(TRIM(p.description),''),'') FROM tbl_products p WHERE p.id = COALESCE(mpc.product_id, mst.product_id) LIMIT 1) AS stock_remark";
            }
            if ($pd) {
                mysqli_free_result($pd);
            }
        }

        return compact('join', 'clarity', 'size', 'color', 'invo', 'remark_extra');
    }
}

if (!function_exists('mfg_memo_map_row')) {
    function mfg_memo_map_row(array $r, $flow) {
        $issued = (float) ($r['wt_issue'] ?? 0);
        $returned = (float) ($r['wt_return'] ?? 0);
        $pending = (float) ($r['wt_pending'] ?? 0);
        if ($pending <= 0 && ($issued > 0 || $returned > 0)) {
            $pending = max(0, $issued - $returned);
        }
        $memo_no = '';
        if (!empty($r['memo_no'])) {
            $memo_no = (string) $r['memo_no'];
        } elseif ((int) ($r['sale_order_id'] ?? 0) > 0) {
            $memo_no = (string) (int) $r['sale_order_id'];
        }
        $product = trim((string) ($r['product_name'] ?? ''));
        $remark_html = mfg_memo_remark($product, $r['stock_remark'] ?? '');

        return [
            'row_uid' => (string) ($r['row_uid'] ?? uniqid('memo_', true)),
            'flow' => $flow,
            'memo_no' => $memo_no,
            'jobwork_no' => trim((string) ($r['jobwork_no'] ?? '')),
            'assign_to' => mfg_memo_assign_label($r['worker_name'] ?? '', $r['dept_name'] ?? ''),
            'barcode_no' => trim((string) ($r['barcode'] ?? '')),
            'product' => $product,
            'design_no' => trim((string) ($r['design_no'] ?? '')),
            'clarity' => trim((string) ($r['clarity'] ?? '')),
            'stone_size' => trim((string) ($r['stone_size'] ?? '')),
            'remark_html' => $remark_html,
            'carat' => trim((string) ($r['carat'] ?? '')),
            'issue_wt' => mfg_memo_fmt_wt($issued),
            'receive_wt' => mfg_memo_fmt_wt($returned),
            'pending_wt' => mfg_memo_fmt_wt($pending),
            'issue_wt_num' => $issued,
            'receive_wt_num' => $returned,
            'pending_wt_num' => $pending,
            'invo1' => mfg_memo_fmt_wt($r['invo1_wt'] ?? $issued),
            'return_wt' => mfg_memo_fmt_wt($returned),
            'color' => trim((string) ($r['stone_color'] ?? '')),
            'invo1_doc' => trim((string) ($r['invo1'] ?? '')),
            'sort_ts' => (int) ($r['sort_ts'] ?? 0),
            'department_id' => (int) ($r['department_id'] ?? 0),
            'department_user_id' => (int) ($r['department_user_id'] ?? 0),
        ];
    }
}

if (!function_exists('mfg_memo_group_key')) {
    function mfg_memo_group_key(array $r) {
        $jw = strtolower(trim((string) ($r['jobwork_no'] ?? '')));
        $bc = strtolower(trim((string) ($r['barcode_no'] ?? '')));
        if ($bc !== '') {
            return $jw . '|bc:' . $bc;
        }
        $prod = strtolower(trim((string) ($r['product'] ?? '')));
        $design = strtolower(trim((string) ($r['design_no'] ?? '')));
        $carat = strtolower(trim((string) ($r['carat'] ?? '')));

        return $jw . '|pd:' . $prod . '|' . $design . '|' . $carat;
    }
}

if (!function_exists('mfg_memo_pick_meta')) {
    function mfg_memo_pick_meta(array $base, array $incoming) {
        $fields = ['memo_no', 'assign_to', 'barcode_no', 'product', 'design_no', 'clarity', 'stone_size', 'remark_html', 'carat', 'color', 'invo1_doc'];
        foreach ($fields as $f) {
            $cur = trim((string) ($base[$f] ?? ''));
            $nxt = trim((string) ($incoming[$f] ?? ''));
            if ($cur === '' && $nxt !== '') {
                $base[$f] = $incoming[$f];
            }
        }

        return $base;
    }
}

if (!function_exists('mfg_memo_aggregate_rows')) {
    /**
     * Consolidate issue/receive lines into one row per jobwork order + item (barcode or product).
     *
     * @param list<array<string,mixed>> $rows
     * @return list<array<string,mixed>>
     */
    function mfg_memo_aggregate_rows(array $rows) {
        if ($rows === []) {
            return [];
        }

        $groups = [];
        foreach ($rows as $r) {
            $flow = (string) ($r['flow'] ?? '');
            $issue_num = (float) ($r['issue_wt_num'] ?? 0);
            $recv_num = (float) ($r['receive_wt_num'] ?? 0);
            if ($issue_num <= 0 && $flow === 'Issue') {
                $issue_num = (float) ($r['wt_issue'] ?? 0);
            }
            if ($recv_num <= 0 && $flow === 'Receive') {
                $recv_num = (float) ($r['wt_return'] ?? 0);
            }
            if ($issue_num <= 0 && $recv_num <= 0) {
                if ($flow === 'Issue') {
                    $issue_num = (float) str_replace(',', '', (string) ($r['invo1'] ?? '0'));
                } elseif ($flow === 'Receive') {
                    $recv_num = (float) str_replace(',', '', (string) ($r['return_wt'] ?? '0'));
                }
            }

            $key = mfg_memo_group_key($r);
            if (!isset($groups[$key])) {
                $groups[$key] = $r;
                $groups[$key]['issue_wt_num'] = 0.0;
                $groups[$key]['receive_wt_num'] = 0.0;
                $groups[$key]['txn_issue'] = 0;
                $groups[$key]['txn_receive'] = 0;
            } else {
                if ((int) ($r['sort_ts'] ?? 0) >= (int) ($groups[$key]['sort_ts'] ?? 0)) {
                    $saved = [
                        'issue_wt_num' => (float) ($groups[$key]['issue_wt_num'] ?? 0),
                        'receive_wt_num' => (float) ($groups[$key]['receive_wt_num'] ?? 0),
                        'txn_issue' => (int) ($groups[$key]['txn_issue'] ?? 0),
                        'txn_receive' => (int) ($groups[$key]['txn_receive'] ?? 0),
                    ];
                    $groups[$key] = mfg_memo_pick_meta($r, $groups[$key]);
                    $groups[$key]['sort_ts'] = (int) ($r['sort_ts'] ?? 0);
                    $groups[$key]['issue_wt_num'] = $saved['issue_wt_num'];
                    $groups[$key]['receive_wt_num'] = $saved['receive_wt_num'];
                    $groups[$key]['txn_issue'] = $saved['txn_issue'];
                    $groups[$key]['txn_receive'] = $saved['txn_receive'];
                } else {
                    $groups[$key] = mfg_memo_pick_meta($groups[$key], $r);
                }
            }

            $groups[$key]['issue_wt_num'] += $issue_num;
            $groups[$key]['receive_wt_num'] += $recv_num;
            if ($flow === 'Issue') {
                $groups[$key]['txn_issue'] = (int) ($groups[$key]['txn_issue'] ?? 0) + 1;
            } elseif ($flow === 'Receive') {
                $groups[$key]['txn_receive'] = (int) ($groups[$key]['txn_receive'] ?? 0) + 1;
            }
        }

        $out = [];
        foreach ($groups as $key => $g) {
            $issue = (float) ($g['issue_wt_num'] ?? 0);
            $recv = (float) ($g['receive_wt_num'] ?? 0);
            $pending = max(0, $issue - $recv);
            $g['issue_wt_num'] = $issue;
            $g['receive_wt_num'] = $recv;
            $g['pending_wt_num'] = $pending;
            $g['issue_wt'] = mfg_memo_fmt_wt($issue);
            $g['receive_wt'] = mfg_memo_fmt_wt($recv);
            $g['pending_wt'] = mfg_memo_fmt_wt($pending);
            $g['return_wt'] = $g['receive_wt'];
            $g['invo1'] = $g['issue_wt'];
            if ($pending <= 0.0000001 && $issue > 0 && $recv >= $issue) {
                $g['memo_status'] = 'Closed';
            } elseif ($recv > 0 && $pending > 0) {
                $g['memo_status'] = 'Partial';
            } elseif ($issue > 0 && $recv <= 0) {
                $g['memo_status'] = 'Out';
            } elseif ($recv > 0 && $issue <= 0) {
                $g['memo_status'] = 'In';
            } else {
                $g['memo_status'] = '—';
            }
            $g['row_uid'] = 'agg-' . md5($key);
            $g['flow'] = ($g['txn_receive'] ?? 0) > ($g['txn_issue'] ?? 0) ? 'Receive' : 'Issue';
            unset($g['txn_issue'], $g['txn_receive']);
            $out[] = $g;
        }

        usort($out, function ($a, $b) {
            return ($b['sort_ts'] ?? 0) <=> ($a['sort_ts'] ?? 0);
        });

        return $out;
    }
}

if (!function_exists('mfg_memo_column_defs')) {
    /** @return list<array{key:string,label:string}> */
    function mfg_memo_column_defs() {
        return [
            ['key' => 'jobwork_no', 'label' => 'Jobwork Order No'],
            ['key' => 'memo_no', 'label' => 'Memo / SO No'],
            ['key' => 'assign_to', 'label' => 'Assign To'],
            ['key' => 'barcode_no', 'label' => 'Barcode No'],
            ['key' => 'product', 'label' => 'Product / Item'],
            ['key' => 'design_no', 'label' => 'Design No'],
            ['key' => 'clarity', 'label' => 'Clarity'],
            ['key' => 'stone_size', 'label' => 'Size'],
            ['key' => 'remark', 'label' => 'Remark'],
            ['key' => 'carat', 'label' => 'Carat'],
            ['key' => 'issue_wt', 'label' => 'Issue Wt (Out)'],
            ['key' => 'receive_wt', 'label' => 'Receive Wt (In)'],
            ['key' => 'pending_wt', 'label' => 'Pending Wt'],
            ['key' => 'memo_status', 'label' => 'Status'],
            ['key' => 'color', 'label' => 'Color'],
        ];
    }
}

if (!function_exists('mfg_memo_row_export_value')) {
    function mfg_memo_row_export_value(array $row, string $key) {
        switch ($key) {
            case 'remark':
                $rh = (string) ($row['remark_html'] ?? '');
                return trim(strip_tags(str_replace(['<br/>', '<br>', '<br />'], ' ', $rh)));
            case 'stone_size':
                return trim((string) ($row['stone_size'] ?? ''));
            default:
                return trim((string) ($row[$key] ?? ''));
        }
    }
}

if (!function_exists('mfg_memo_normalize_barcode')) {
    function mfg_memo_normalize_barcode($bc) {
        $bc = trim((string) $bc);
        if ($bc === '' || $bc === '—' || $bc === '-' || strcasecmp($bc, 'n/a') === 0) {
            return '';
        }

        return $bc;
    }
}

if (!function_exists('mfg_memo_parse_wt_from_line')) {
    function mfg_memo_parse_wt_from_line(array $ln) {
        $wt = $ln['wt'] ?? '';
        if ($wt === '' || $wt === '—' || $wt === null) {
            return 0.0;
        }

        return (float) str_replace(',', '', (string) $wt);
    }
}

if (!function_exists('mfg_memo_batch_stock_meta')) {
    /**
     * @param list<string> $barcodes
     * @return array<string, array{clarity:string,stone_size:string,stone_color:string,design_no:string,stock_remark:string,invo1:string}>
     */
    function mfg_memo_batch_stock_meta($conn, array $barcodes) {
        $out = [];
        if (!$conn || !function_exists('getList') || !mfg_memo_table_exists($conn, 'tbl_stock')) {
            return $out;
        }
        $barcodes = array_values(array_unique(array_filter(array_map('mfg_memo_normalize_barcode', $barcodes))));
        if ($barcodes === []) {
            return $out;
        }
        $esc = [];
        foreach ($barcodes as $bc) {
            $esc[] = "'" . mysqli_real_escape_string($conn, $bc) . "'";
        }
        $in = implode(',', $esc);
        $has_pc = mfg_memo_table_exists($conn, 'tbl_product_characteristics');
        $has_sj = false;
        $sj_col = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'stock_journal_id'");
        if ($sj_col && mysqli_num_rows($sj_col) > 0) {
            $has_sj = true;
        }
        if ($sj_col) {
            mysqli_free_result($sj_col);
        }
        $has_prod_desc = false;
        if (mfg_memo_table_exists($conn, 'tbl_products')) {
            $pd = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_products LIKE 'description'");
            $has_prod_desc = ($pd && mysqli_num_rows($pd) > 0);
            if ($pd) {
                mysqli_free_result($pd);
            }
        }
        $clarity_sel = "'' AS clarity";
        $size_sel = "'' AS stone_size";
        $color_sel = "'' AS stone_color";
        $design_sel = "'' AS design_no";
        $remark_sel = "'' AS stock_remark";
        $invo_sel = "'' AS invo1";
        $join = '';
        if ($has_pc) {
            $join = ' LEFT JOIN tbl_product_characteristics mpc ON mpc.id = s.product_characteristic_id';
            $cc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_product_characteristics LIKE 'clarity'");
            if ($cc && mysqli_num_rows($cc) > 0) {
                $clarity_sel = "IFNULL(mpc.clarity,'') AS clarity";
            }
            if ($cc) {
                mysqli_free_result($cc);
            }
            $sc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_product_characteristics LIKE 'size'");
            if ($sc && mysqli_num_rows($sc) > 0) {
                $size_sel = "IFNULL(mpc.size,'') AS stone_size";
            }
            if ($sc) {
                mysqli_free_result($sc);
            }
            $colc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_product_characteristics LIKE 'color'");
            if ($colc && mysqli_num_rows($colc) > 0) {
                $color_sel = "IFNULL(mpc.color,'') AS stone_color";
            }
            if ($colc) {
                mysqli_free_result($colc);
            }
        }
        $sj_join = mfg_memo_table_exists($conn, 'tbl_stock_journal') ? ' LEFT JOIN tbl_stock_journal sj ON sj.id = s.stock_journal_id ' : '';
        if ($has_sj && $sj_join !== '') {
            $invo_sel = "IFNULL(sj.invoice_no,'') AS invo1";
        }
        if ($has_prod_desc) {
            $join .= ' LEFT JOIN tbl_products p ON p.id = COALESCE(mpc.product_id, s.product_id)';
            $remark_sel = "IFNULL(NULLIF(TRIM(p.description),''),'') AS stock_remark";
        }
        $sj_design = mfg_memo_table_exists($conn, 'tbl_stock_journal')
            ? '(SELECT sj2.design_no FROM tbl_stock_journal sj2 WHERE sj2.id = s.stock_journal_id LIMIT 1)'
            : "''";
        $design_sel = "IFNULL(NULLIF(TRIM({$sj_design}),''),'') AS design_no";

        $sql = "SELECT TRIM(s.barcode) AS barcode, {$clarity_sel}, {$size_sel}, {$color_sel}, {$design_sel}, {$remark_sel}, {$invo_sel}
            FROM tbl_stock s
            {$join}
            {$sj_join}
            WHERE s.status = 1 AND TRIM(IFNULL(s.barcode,'')) IN ($in)
            ORDER BY s.id DESC";
        $list = getList($sql);
        if (!is_array($list)) {
            return $out;
        }
        foreach ($list as $r) {
            $bc = mfg_memo_normalize_barcode($r['barcode'] ?? '');
            if ($bc === '' || isset($out[$bc])) {
                continue;
            }
            $out[$bc] = [
                'clarity' => trim((string) ($r['clarity'] ?? '')),
                'stone_size' => trim((string) ($r['stone_size'] ?? '')),
                'stone_color' => trim((string) ($r['stone_color'] ?? '')),
                'design_no' => trim((string) ($r['design_no'] ?? '')),
                'stock_remark' => trim((string) ($r['stock_remark'] ?? '')),
                'invo1' => trim((string) ($r['invo1'] ?? '')),
            ];
        }

        return $out;
    }
}

if (!function_exists('mfg_memo_row_from_voucher_line')) {
    function mfg_memo_row_from_voucher_line(array $hdr, array $ln, string $flow, string $prefix, int $line_idx, array $stock_meta) {
        $bc = mfg_memo_normalize_barcode($ln['barcode'] ?? '');
        $wt = mfg_memo_parse_wt_from_line($ln);
        $is_issue = ($flow === 'Issue');
        $meta = $bc !== '' && isset($stock_meta[$bc]) ? $stock_meta[$bc] : [
            'clarity' => '',
            'stone_size' => '',
            'stone_color' => '',
            'design_no' => '',
            'stock_remark' => '',
            'invo1' => '',
        ];
        $product = trim((string) ($ln['product_name'] ?? ''));
        if ($product === '—') {
            $product = '';
        }
        if ($product === '') {
            $product = trim((string) ($ln['item_type'] ?? ''));
            if ($product === '—') {
                $product = '';
            }
        }
        $design = trim((string) ($ln['category'] ?? ''));
        if ($design === '—' || $design === 'Stock' || $design === 'Metal exchange') {
            $design = $meta['design_no'];
        }

        return mfg_memo_map_row([
            'row_uid' => $prefix . '-' . (int) ($hdr['id'] ?? 0) . '-' . $line_idx . '-' . md5(
                $bc . '|' . $product . '|' . (string) ($ln['wt'] ?? '') . '|' . (string) ($ln['status'] ?? '')
            ),
            'memo_no' => (int) ($hdr['sale_order_id'] ?? 0) > 0 ? (int) $hdr['sale_order_id'] : (int) ($hdr['id'] ?? 0),
            'sale_order_id' => (int) ($hdr['sale_order_id'] ?? 0),
            'jobwork_no' => $hdr['jobwork_no'] ?? '',
            'worker_name' => $hdr['worker_name'] ?? '',
            'dept_name' => $hdr['dept_name'] ?? '',
            'department_id' => (int) ($hdr['department_id'] ?? 0),
            'department_user_id' => (int) ($hdr['department_user_id'] ?? 0),
            'barcode' => $bc,
            'product_name' => $product,
            'design_no' => $design,
            'carat' => '',
            'wt_issue' => $is_issue ? $wt : 0,
            'wt_return' => $is_issue ? 0 : $wt,
            'wt_pending' => $is_issue ? $wt : 0,
            'invo1_wt' => $wt,
            'clarity' => $meta['clarity'],
            'stone_size' => $meta['stone_size'],
            'stone_color' => $meta['stone_color'],
            'invo1' => $meta['invo1'],
            'stock_remark' => $meta['stock_remark'],
            'sort_ts' => (int) ($hdr['sort_ts'] ?? 0),
        ], $flow);
    }
}

if (!function_exists('mfg_memo_material_hdr_search_sql')) {
    /**
     * Match header, jobwork no, line items, voucher diamonds, or stock allocations.
     */
    function mfg_memo_material_hdr_search_sql($conn, $search_esc, $hdr_alias, $doc_type, $jwo_join) {
        if ($search_esc === '') {
            return '';
        }
        $is_issue = ($doc_type === 'issue');
        $no_col = $is_issue ? 'material_issue_no' : 'material_receive_no';
        $items_tbl = $is_issue ? 'tbl_material_issue_items' : 'tbl_material_receive_items';
        $fk = $is_issue ? 'material_issue_id' : 'material_receive_id';
        $vkind = $is_issue ? 'material_issue' : 'material_receive';
        $ref_types = $is_issue
            ? "'material_issue','material_issue_metal_exchange'"
            : "'material_receive','material_receive_metal_exchange'";

        $parts = [
            "{$hdr_alias}.{$no_col} LIKE '%{$search_esc}%'",
            "{$hdr_alias}.sale_order_no LIKE '%{$search_esc}%'",
        ];
        if ($jwo_join !== '' && mfg_memo_table_exists($conn, 'tbl_jobwork_orders')) {
            $parts[] = "j.jobwork_no LIKE '%{$search_esc}%'";
        }
        if (mfg_memo_table_exists($conn, $items_tbl)) {
            $parts[] = "EXISTS (SELECT 1 FROM `{$items_tbl}` it"
                . " WHERE it.`{$fk}` = {$hdr_alias}.id"
                . " AND (it.barcode LIKE '%{$search_esc}%'"
                . " OR it.product_name LIKE '%{$search_esc}%'"
                . " OR it.design_no LIKE '%{$search_esc}%'))";
        }
        if (mfg_memo_table_exists($conn, 'tbl_voucher_diamond_stock_issue')) {
            $vk = mysqli_real_escape_string($conn, $vkind);
            $parts[] = "EXISTS (SELECT 1 FROM tbl_voucher_diamond_stock_issue vd"
                . " WHERE vd.voucher_kind = '{$vk}' AND vd.voucher_id = {$hdr_alias}.id"
                . " AND (vd.barcode LIKE '%{$search_esc}%'"
                . " OR vd.product_name LIKE '%{$search_esc}%'))";
        }
        if (mfg_memo_table_exists($conn, 'tbl_stock')) {
            $parts[] = "EXISTS (SELECT 1 FROM tbl_stock s"
                . " WHERE s.reference_id = {$hdr_alias}.id"
                . " AND s.reference_type IN ({$ref_types})"
                . " AND s.status = 1"
                . " AND s.barcode LIKE '%{$search_esc}%')";
        }

        return ' AND (' . implode(' OR ', $parts) . ') ';
    }
}

if (!function_exists('mfg_memo_append_material_doc_rows')) {
    /**
     * One memo row per material issue/receive line (items + voucher diamonds + stock allocations).
     *
     * @return list<array<string,mixed>>
     */
    function mfg_memo_append_material_doc_rows($conn, array &$rows, string $doc_type, $filter_dept_id, $filter_user_id, $search_esc, array $allowed_dept_ids = []) {
        if (!$conn || !function_exists('getList')) {
            return;
        }
        if (!is_file(__DIR__ . '/jwm_material_links.php')) {
            return;
        }
        require_once __DIR__ . '/jwm_material_links.php';
        if (!function_exists('jwm_batch_material_voucher_lines_map')) {
            return;
        }

        $is_issue = ($doc_type === 'issue');
        $hdr_tbl = $is_issue ? 'tbl_material_issues' : 'tbl_material_receives';
        $hdr_alias = $is_issue ? 'mi' : 'mr';
        if (!mfg_memo_table_exists($conn, $hdr_tbl)) {
            return;
        }

        $jwo_join = mfg_memo_table_exists($conn, 'tbl_jobwork_orders')
            ? " LEFT JOIN tbl_jobwork_orders j ON j.sale_order_id = {$hdr_alias}.sale_order_id AND {$hdr_alias}.sale_order_id > 0 "
            : " LEFT JOIN (SELECT NULL AS jobwork_no, NULL AS sale_order_id) j ON 1=0 ";

        $where = ' WHERE 1=1 ';
        if ($filter_dept_id > 0) {
            $where .= ' AND IFNULL(' . $hdr_alias . '.department_id,0) = ' . (int) $filter_dept_id;
        } elseif ($allowed_dept_ids !== []) {
            $in = function_exists('auragold_department_sql_in_ids')
                ? auragold_department_sql_in_ids($allowed_dept_ids)
                : '0';
            $where .= ' AND IFNULL(' . $hdr_alias . '.department_id,0) IN (' . $in . ')';
        } else {
            $where .= ' AND 1=0 ';
        }
        if ($filter_user_id > 0) {
            $where .= ' AND IFNULL(' . $hdr_alias . '.department_user_id,0) = ' . (int) $filter_user_id;
        }
        if ($search_esc !== '') {
            $where .= mfg_memo_material_hdr_search_sql($conn, $search_esc, $hdr_alias, $doc_type, $jwo_join);
        }

        $sql = "SELECT {$hdr_alias}.id, {$hdr_alias}.sale_order_id,
                IFNULL(j.jobwork_no,'') AS jobwork_no,
                IFNULL(d.dept_name,'') AS dept_name,
                IFNULL(cu.name,'') AS worker_name,
                IFNULL({$hdr_alias}.department_id,0) AS department_id,
                IFNULL({$hdr_alias}.department_user_id,0) AS department_user_id,
                UNIX_TIMESTAMP(COALESCE({$hdr_alias}.updated_at, {$hdr_alias}.created_at)) AS sort_ts
            FROM `{$hdr_tbl}` {$hdr_alias}
            {$jwo_join}
            LEFT JOIN tbl_departments d ON d.id = {$hdr_alias}.department_id
            LEFT JOIN tbl_customers cu ON cu.id = {$hdr_alias}.department_user_id
            {$where}
            ORDER BY {$hdr_alias}.id DESC
            LIMIT 500";

        $hdr_list = getList($sql);
        if (!is_array($hdr_list) || $hdr_list === []) {
            return;
        }

        $ids = [];
        foreach ($hdr_list as $h) {
            $hid = (int) ($h['id'] ?? 0);
            if ($hid > 0) {
                $ids[] = $hid;
            }
        }
        if ($ids === []) {
            return;
        }

        $lines_map = jwm_batch_material_voucher_lines_map($conn, $ids, $doc_type, false);
        $barcodes = [];
        foreach ($lines_map as $doc_lines) {
            if (!is_array($doc_lines)) {
                continue;
            }
            foreach ($doc_lines as $ln) {
                if (!is_array($ln)) {
                    continue;
                }
                $bc = mfg_memo_normalize_barcode($ln['barcode'] ?? '');
                if ($bc !== '') {
                    $barcodes[] = $bc;
                }
            }
        }
        $stock_meta = mfg_memo_batch_stock_meta($conn, $barcodes);
        $flow = $is_issue ? 'Issue' : 'Receive';
        $prefix = $is_issue ? 'mi-exp' : 'mr-exp';

        foreach ($hdr_list as $hdr) {
            $vid = (int) ($hdr['id'] ?? 0);
            if ($vid < 1) {
                continue;
            }
            $doc_lines = $lines_map[$vid] ?? [];
            if (!is_array($doc_lines) || $doc_lines === []) {
                continue;
            }
            $idx = 0;
            foreach ($doc_lines as $ln) {
                if (!is_array($ln)) {
                    continue;
                }
                $rows[] = mfg_memo_row_from_voucher_line($hdr, $ln, $flow, $prefix, $idx++, $stock_meta);
            }
        }
    }
}

if (!function_exists('mfg_outsource_load_memo_rows')) {
    /**
     * @return list<array<string,mixed>>
     */
    function mfg_outsource_load_memo_rows($conn, $filter_dept_id = 0, $filter_user_id = 0, $search = '', array $allowed_dept_ids = []) {
        if (!$conn || !function_exists('getList')) {
            return [];
        }

        $rows = [];
        $search = trim((string) $search);
        $search_esc = $search !== '' ? mysqli_real_escape_string($conn, $search) : '';

        // Material issue/receive: expand to per-barcode lines (same as Material history modal).
        mfg_memo_append_material_doc_rows($conn, $rows, 'issue', $filter_dept_id, $filter_user_id, $search_esc, $allowed_dept_ids);
        mfg_memo_append_material_doc_rows($conn, $rows, 'receive', $filter_dept_id, $filter_user_id, $search_esc, $allowed_dept_ids);

        $seen_jwo_barcode = [];
        foreach ($rows as $r) {
            $jw = strtolower(trim((string) ($r['jobwork_no'] ?? '')));
            $bc = strtolower(trim((string) ($r['barcode_no'] ?? '')));
            if ($jw !== '' && $bc !== '') {
                $seen_jwo_barcode[$jw . '|' . $bc] = true;
            }
        }

        // —— Jobwork diamond / stone issues (queue) ——
        if (mfg_memo_table_exists($conn, 'tbl_jobwork_queue_diamond_stock_issue')) {
            require_once __DIR__ . '/mp-jobwork-queue-diamond-stock.php';
            mp_jwq_ensure_diamond_issue_table($conn);
            $tbl = mp_jwq_diamond_issue_table_name();
            $ji_design = mfg_memo_table_exists($conn, 'tbl_jobwork_order_items')
                ? '(SELECT ji.design_no FROM tbl_jobwork_order_items ji WHERE ji.id = ds.jobwork_order_item_id LIMIT 1)'
                : "''";
            $stk = mfg_memo_stock_join_sql($conn, 'ds.barcode', 'mst.product_characteristic_id');
            $where = ' WHERE ds.jobwork_order_id > 0 ';
            if ($filter_dept_id > 0) {
                $where .= ' AND (IFNULL(ds.to_dept_id,0) = ' . (int) $filter_dept_id
                    . ' OR IFNULL(j.department_id,0) = ' . (int) $filter_dept_id . ')';
            } elseif ($allowed_dept_ids !== []) {
                $in = function_exists('auragold_department_sql_in_ids')
                    ? auragold_department_sql_in_ids($allowed_dept_ids)
                    : '0';
                $where .= ' AND (IFNULL(ds.to_dept_id,0) IN (' . $in . ') OR IFNULL(j.department_id,0) IN (' . $in . '))';
            } else {
                $where .= ' AND 1=0 ';
            }
            if ($filter_user_id > 0) {
                $where .= ' AND (IFNULL(ds.to_user_id,0) = ' . (int) $filter_user_id
                    . ' OR IFNULL(j.department_user_id,0) = ' . (int) $filter_user_id . ')';
            }
            if ($search_esc !== '') {
                $where .= " AND (j.jobwork_no LIKE '%{$search_esc}%'"
                    . " OR ds.barcode LIKE '%{$search_esc}%'"
                    . " OR ds.product_name LIKE '%{$search_esc}%') ";
            }

            $sql_ds = "SELECT
                CONCAT('ds-', ds.id) AS row_uid,
                IFNULL(j.sale_order_id, ds.jobwork_order_id) AS memo_no,
                j.sale_order_id,
                IFNULL(j.jobwork_no,'') AS jobwork_no,
                COALESCE(NULLIF(td.dept_name,''), NULLIF(fd.dept_name,''), '') AS dept_name,
                COALESCE(NULLIF(tu.name,''), NULLIF(fu.name,''), '') AS worker_name,
                COALESCE(NULLIF(ds.to_dept_id,0), NULLIF(j.department_id,0), 0) AS department_id,
                COALESCE(NULLIF(ds.to_user_id,0), NULLIF(j.department_user_id,0), 0) AS department_user_id,
                TRIM(IFNULL(ds.barcode,'')) AS barcode,
                TRIM(IFNULL(ds.product_name,'')) AS product_name,
                {$ji_design} AS design_no,
                '' AS carat,
                COALESCE(NULLIF(ds.weight_out,0), NULLIF(ds.weight,0), 0) AS wt_issue,
                0 AS wt_return,
                GREATEST(0, COALESCE(ds.weight,0) - COALESCE(ds.weight_out,0)) AS wt_pending,
                COALESCE(NULLIF(ds.weight,0), 0) AS invo1_wt,
                {$stk['clarity']},
                {$stk['size']},
                {$stk['color']},
                {$stk['invo']},
                {$stk['remark_extra']},
                UNIX_TIMESTAMP(ds.created_at) AS sort_ts
            FROM `{$tbl}` ds
            INNER JOIN tbl_jobwork_orders j ON j.id = ds.jobwork_order_id
            LEFT JOIN tbl_departments fd ON fd.id = ds.from_dept_id
            LEFT JOIN tbl_departments td ON td.id = ds.to_dept_id
            LEFT JOIN tbl_customers fu ON fu.id = ds.from_user_id
            LEFT JOIN tbl_customers tu ON tu.id = ds.to_user_id
            {$stk['join']}
            {$where}
            ORDER BY ds.id DESC
            LIMIT 2000";

            $list = getList($sql_ds);
            if (is_array($list)) {
                foreach ($list as $r) {
                    $jw = strtolower(trim((string) ($r['jobwork_no'] ?? '')));
                    $bc = strtolower(trim((string) ($r['barcode'] ?? '')));
                    if ($jw !== '' && $bc !== '' && isset($seen_jwo_barcode[$jw . '|' . $bc])) {
                        continue;
                    }
                    $rows[] = mfg_memo_map_row($r, 'Issue');
                    if ($jw !== '' && $bc !== '') {
                        $seen_jwo_barcode[$jw . '|' . $bc] = true;
                    }
                }
            }
        }

        usort($rows, function ($a, $b) {
            return ($b['sort_ts'] ?? 0) <=> ($a['sort_ts'] ?? 0);
        });

        return mfg_memo_aggregate_rows($rows);
    }
}
