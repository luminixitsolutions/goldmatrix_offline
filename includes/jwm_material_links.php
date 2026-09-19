<?php
/**
 * Material issue / receive URLs and history for job-work-order-manufacturing list.
 */

if (!function_exists('jwm_material_tables_ready')) {
    function jwm_material_tables_ready($conn) {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = ['mi' => false, 'mr' => false, 'rmi' => false, 'rmr' => false];
        if (!$conn) {
            return $cache;
        }
        foreach (['mi' => 'tbl_material_issues', 'mr' => 'tbl_material_receives', 'rmi' => 'tbl_repair_material_issues', 'rmr' => 'tbl_repair_material_receives'] as $k => $tbl) {
            $t = @mysqli_query($conn, "SHOW TABLES LIKE '$tbl'");
            $cache[$k] = ($t && mysqli_num_rows($t) > 0);
            if ($t) {
                mysqli_free_result($t);
            }
        }
        return $cache;
    }
}

if (!function_exists('jwm_material_issue_url')) {
    /**
     * @param string $list_source jwo|rjwo|sale|repair
     */
    function jwm_material_issue_url($list_source, $jobwork_order_id, $sale_order_id, $repair_order_id, $return_to_jwm_list = true) {
        $ls = (string) $list_source;
        $jid = (int) $jobwork_order_id;
        $soid = (int) $sale_order_id;
        $rid = (int) $repair_order_id;
        $ret = ($return_to_jwm_list ? '&return=jwm' : '');
        if ($ls === 'jwo' && $jid > 0) {
            return 'material-issue.php?jobwork_order_id=' . $jid . $ret;
        }
        if ($ls === 'rjwo' && $jid > 0) {
            return 'material-issue.php?repair_jobwork_order_id=' . $jid . '&from_repair=1' . $ret;
        }
        if ($ls === 'sale' && $soid > 0) {
            return 'material-issue.php?sale_order_id=' . $soid . $ret;
        }
        if (($ls === 'repair' || $ls === 'rjwo') && $rid > 0) {
            return 'material-issue.php?sale_order_id=' . $rid . '&from_repair=1' . $ret;
        }
        if ($soid > 0) {
            return 'material-issue.php?sale_order_id=' . $soid . $ret;
        }
        return '';
    }
}

if (!function_exists('jwm_material_receive_url')) {
    function jwm_material_receive_url($list_source, $jobwork_order_id, $sale_order_id, $repair_order_id, $return_to_jwm_list = true) {
        $ls = (string) $list_source;
        $jid = (int) $jobwork_order_id;
        $soid = (int) $sale_order_id;
        $rid = (int) $repair_order_id;
        $ret = ($return_to_jwm_list ? '&return=jwm' : '');
        if ($ls === 'jwo' && $jid > 0) {
            return 'material-receive.php?jobwork_order_id=' . $jid . $ret;
        }
        if ($ls === 'rjwo' && $jid > 0) {
            return 'material-receive.php?repair_jobwork_order_id=' . $jid . '&from_repair=1' . $ret;
        }
        if ($ls === 'sale' && $soid > 0) {
            return 'material-receive.php?sale_order_id=' . $soid . $ret;
        }
        if (($ls === 'repair' || $ls === 'rjwo') && $rid > 0) {
            return 'material-receive.php?sale_order_id=' . $rid . '&from_repair=1' . $ret;
        }
        if ($soid > 0) {
            return 'material-receive.php?sale_order_id=' . $soid . $ret;
        }
        return '';
    }
}

if (!function_exists('jwm_material_doc_edit_url')) {
    function jwm_material_doc_edit_url($type, $id, $is_repair) {
        $id = (int) $id;
        if ($id < 1) {
            return '';
        }
        if ($type === 'issue') {
            return $is_repair ? 'material-issue.php?id=' . $id : 'material-issue.php?id=' . $id;
        }
        return $is_repair ? 'material-receive.php?id=' . $id : 'material-receive.php?id=' . $id;
    }
}

if (!function_exists('jwm_load_material_histories_for_rows')) {
    /**
     * @param array<int,array<string,mixed>> $rows page rows
     * @return array{sale_issues:array<int,array>,sale_receives:array<int,array>,repair_issues:array<int,array>,repair_receives:array<int,array>}
     */
    function jwm_load_material_histories_for_rows($conn, array $rows) {
        $out = [
            'sale_issues' => [],
            'sale_receives' => [],
            'repair_issues' => [],
            'repair_receives' => [],
        ];
        if (!$conn || empty($rows)) {
            return $out;
        }
        $tabs = jwm_material_tables_ready($conn);
        $sale_ids = [];
        $repair_ids = [];
        foreach ($rows as $r) {
            $ls = (string) ($r['list_source'] ?? 'jwo');
            $soid = (int) ($r['sale_order_id'] ?? 0);
            $rid = (int) ($r['repair_order_id'] ?? 0);
            if ($ls === 'repair' || $ls === 'rjwo') {
                if ($rid > 0) {
                    $repair_ids[$rid] = true;
                }
            } elseif ($soid > 0) {
                $sale_ids[$soid] = true;
            }
        }
        $scope_mi = function_exists('auragold_effective_branch_list_scope_sql')
            ? auragold_effective_branch_list_scope_sql($conn, 'tbl_material_issues') : '';
        $scope_mr = function_exists('auragold_effective_branch_list_scope_sql')
            ? auragold_effective_branch_list_scope_sql($conn, 'tbl_material_receives') : '';

        if ($tabs['mi'] && !empty($sale_ids)) {
            $in = implode(',', array_map('intval', array_keys($sale_ids)));
            $sql = "SELECT id, material_issue_no, sale_order_id, order_date, status, grand_total, created_at
                FROM tbl_material_issues WHERE sale_order_id IN ($in) $scope_mi ORDER BY id DESC";
            $list = function_exists('getList') ? getList($sql) : [];
            if (is_array($list)) {
                foreach ($list as $row) {
                    $oid = (int) ($row['sale_order_id'] ?? 0);
                    if ($oid > 0) {
                        if (!isset($out['sale_issues'][$oid])) {
                            $out['sale_issues'][$oid] = [];
                        }
                        $out['sale_issues'][$oid][] = $row;
                    }
                }
            }
        }
        if ($tabs['mr'] && !empty($sale_ids)) {
            $in = implode(',', array_map('intval', array_keys($sale_ids)));
            $sql = "SELECT id, material_receive_no, sale_order_id, order_date, status, grand_total, created_at
                FROM tbl_material_receives WHERE sale_order_id IN ($in) $scope_mr ORDER BY id DESC";
            $list = function_exists('getList') ? getList($sql) : [];
            if (is_array($list)) {
                foreach ($list as $row) {
                    $oid = (int) ($row['sale_order_id'] ?? 0);
                    if ($oid > 0) {
                        if (!isset($out['sale_receives'][$oid])) {
                            $out['sale_receives'][$oid] = [];
                        }
                        $out['sale_receives'][$oid][] = $row;
                    }
                }
            }
        }
        if ($tabs['rmi'] && !empty($repair_ids)) {
            $in = implode(',', array_map('intval', array_keys($repair_ids)));
            $sql = "SELECT id, material_issue_no, repair_order_id, order_date, status, grand_total, created_at
                FROM tbl_repair_material_issues WHERE repair_order_id IN ($in) ORDER BY id DESC";
            $list = function_exists('getList') ? getList($sql) : [];
            if (is_array($list)) {
                foreach ($list as $row) {
                    $oid = (int) ($row['repair_order_id'] ?? 0);
                    if ($oid > 0) {
                        if (!isset($out['repair_issues'][$oid])) {
                            $out['repair_issues'][$oid] = [];
                        }
                        $out['repair_issues'][$oid][] = $row;
                    }
                }
            }
        }
        if ($tabs['rmr'] && !empty($repair_ids)) {
            $in = implode(',', array_map('intval', array_keys($repair_ids)));
            $sql = "SELECT id, material_receive_no, repair_order_id, order_date, status, grand_total, created_at
                FROM tbl_repair_material_receives WHERE repair_order_id IN ($in) ORDER BY id DESC";
            $list = function_exists('getList') ? getList($sql) : [];
            if (is_array($list)) {
                foreach ($list as $row) {
                    $oid = (int) ($row['repair_order_id'] ?? 0);
                    if ($oid > 0) {
                        if (!isset($out['repair_receives'][$oid])) {
                            $out['repair_receives'][$oid] = [];
                        }
                        $out['repair_receives'][$oid][] = $row;
                    }
                }
            }
        }
        return $out;
    }
}

if (!function_exists('jwm_material_history_doc_url')) {
    function jwm_material_history_doc_url(string $type, array $doc_row, bool $is_repair, int $repair_order_id, bool $return_to_jwm_list = true): string
    {
        $id = (int) ($doc_row['id'] ?? 0);
        $ret = $return_to_jwm_list ? '&return=jwm' : '';
        if ($type === 'issue') {
            if ($is_repair && $repair_order_id > 0) {
                return 'material-issue.php?sale_order_id=' . $repair_order_id . '&from_repair=1' . $ret;
            }

            return $id > 0 ? 'material-issue.php?id=' . $id . $ret : '';
        }
        if ($is_repair && $repair_order_id > 0) {
            return 'material-receive.php?sale_order_id=' . $repair_order_id . '&from_repair=1' . $ret;
        }

        return $id > 0 ? 'material-receive.php?id=' . $id . $ret : '';
    }
}

if (!function_exists('jwm_material_history_doc_date')) {
    function jwm_material_history_doc_date(array $doc_row): string
    {
        if (!empty($doc_row['order_date'])) {
            return date('d-m-Y', strtotime((string) $doc_row['order_date']));
        }
        if (!empty($doc_row['created_at'])) {
            return date('d-m-Y', strtotime((string) $doc_row['created_at']));
        }

        return '';
    }
}

if (!function_exists('jwm_material_fmt_num')) {
    function jwm_material_fmt_num($v, int $dec = 3): string
    {
        if ($v === null || $v === '') {
            return '';
        }
        $f = (float) $v;
        if (!is_finite($f)) {
            return '';
        }

        return number_format($f, $dec, '.', '');
    }
}

if (!function_exists('jwm_material_guess_item_type')) {
    function jwm_material_guess_item_type(string $metal_label, string $carat, string $product_name, string $category = ''): string
    {
        $blob = strtolower($metal_label . ' ' . $carat . ' ' . $product_name . ' ' . $category);
        if (strpos($blob, 'diamond') !== false) {
            return 'Diamond';
        }
        if (strpos($blob, 'stone') !== false || strpos($blob, 'gem') !== false) {
            return 'Stone';
        }
        if (strpos($blob, 'silver') !== false) {
            return 'Silver';
        }
        if (strpos($blob, 'gold') !== false) {
            return 'Gold';
        }
        if (trim($metal_label) !== '') {
            return $metal_label;
        }

        return 'Jewellery';
    }
}

if (!function_exists('jwm_material_history_line_row')) {
    /**
     * @return array{item_type:string,barcode:string,product_name:string,qty:string,wt:string,metal_type:string,category:string,status:string,date:string}
     */
    function jwm_material_history_line_row(
        string $item_type,
        string $barcode,
        string $product_name,
        $qty,
        $wt,
        string $metal_type,
        string $category,
        string $date,
        string $status = 'Allocated'
    ): array {
        return [
            'item_type' => $item_type !== '' ? $item_type : '—',
            'barcode' => $barcode !== '' ? $barcode : '—',
            'product_name' => $product_name !== '' ? $product_name : '—',
            'qty' => $qty !== '' && $qty !== null ? (string) $qty : '—',
            'wt' => $wt !== '' && $wt !== null ? (string) $wt : '—',
            'metal_type' => $metal_type !== '' ? $metal_type : '—',
            'category' => $category !== '' ? $category : '—',
            'status' => $status !== '' ? $status : '—',
            'date' => $date !== '' ? $date : '—',
        ];
    }
}

if (!function_exists('jwm_material_voucher_doc_context')) {
    /**
     * @return array{sale_order_id:int,repair_order_id:int,jobwork_order_id:int}
     */
    function jwm_material_voucher_doc_context($conn, string $doc_type, int $doc_id, bool $is_repair): array
    {
        $ctx = ['sale_order_id' => 0, 'repair_order_id' => 0, 'jobwork_order_id' => 0];
        if (!$conn || $doc_id < 1) {
            return $ctx;
        }
        $is_issue = ($doc_type === 'issue');
        if ($is_repair) {
            $hdr_tbl = $is_issue ? 'tbl_repair_material_issues' : 'tbl_repair_material_receives';
            $row = function_exists('getRecord')
                ? getRecord('SELECT repair_order_id FROM `' . $hdr_tbl . '` WHERE id = ' . (int) $doc_id . ' LIMIT 1')
                : null;
            $ctx['repair_order_id'] = (int) ($row['repair_order_id'] ?? 0);

            return $ctx;
        }
        $hdr_tbl = $is_issue ? 'tbl_material_issues' : 'tbl_material_receives';
        $row = function_exists('getRecord')
            ? getRecord('SELECT sale_order_id FROM `' . $hdr_tbl . '` WHERE id = ' . (int) $doc_id . ' LIMIT 1')
            : null;
        $soid = (int) ($row['sale_order_id'] ?? 0);
        $ctx['sale_order_id'] = $soid;
        if ($soid > 0) {
            $t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_orders'");
            if ($t && mysqli_num_rows($t) > 0) {
                mysqli_free_result($t);
                $jwo = function_exists('getRecord')
                    ? getRecord('SELECT id FROM tbl_jobwork_orders WHERE sale_order_id = ' . $soid . ' ORDER BY id DESC LIMIT 1')
                    : null;
                $ctx['jobwork_order_id'] = (int) ($jwo['id'] ?? 0);
            } elseif ($t) {
                mysqli_free_result($t);
            }
        }

        return $ctx;
    }
}

if (!function_exists('jwm_material_history_collect_diamond_lines')) {
    /**
     * @param list<array{0:string,1:int}> $fallback_sources [voucher_kind, voucher_id]
     * @return list<array<string,string>>
     */
    function jwm_material_history_collect_diamond_lines(
        $conn,
        string $primary_kind,
        int $primary_id,
        array $fallback_sources,
        string $doc_date,
        string $line_status = 'Allocated'
    ): array {
        if (!$conn || !function_exists('auragold_voucher_list_diamond_issue_rows_for_kind')) {
            return [];
        }
        $out = [];
        $seen = [];
        $sources = array_merge([[$primary_kind, $primary_id]], $fallback_sources);
        foreach ($sources as $src) {
            $kind = (string) ($src[0] ?? '');
            $vid = (int) ($src[1] ?? 0);
            if ($kind === '' || $vid < 1) {
                continue;
            }
            $rows = auragold_voucher_list_diamond_issue_rows_for_kind($conn, $kind, $vid);
            if ($kind === 'sale_order') {
                $tLegacy = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_order_diamond_stock_issue'");
                if ($tLegacy && mysqli_num_rows($tLegacy) > 0) {
                    mysqli_free_result($tLegacy);
                    $leg = function_exists('getList')
                        ? getList(
                            'SELECT barcode, product_name, diamond_category, weight, qty '
                            . 'FROM tbl_sale_order_diamond_stock_issue WHERE order_id = ' . $vid . ' ORDER BY id ASC'
                        )
                        : [];
                    if (is_array($leg)) {
                        $rows = array_merge($rows, $leg);
                    }
                } elseif ($tLegacy) {
                    mysqli_free_result($tLegacy);
                }
            }
            foreach ($rows as $d) {
                if (!is_array($d)) {
                    continue;
                }
                $bc = trim((string) ($d['barcode'] ?? ''));
                $key = strtolower($kind) . '|' . $vid . '|' . strtolower($bc);
                if ($bc !== '' && isset($seen[$key])) {
                    continue;
                }
                if ($bc !== '') {
                    $seen[$key] = true;
                }
                $out[] = jwm_material_history_line_row(
                    'Diamond',
                    $bc,
                    trim((string) ($d['product_name'] ?? '')),
                    jwm_material_fmt_num($d['qty'] ?? 0, 2),
                    jwm_material_fmt_num($d['weight'] ?? 0, 3),
                    'Diamond',
                    trim((string) ($d['diamond_category'] ?? '')),
                    $doc_date,
                    $line_status
                );
            }
        }

        return $out;
    }
}

if (!function_exists('jwm_material_history_collect_stone_lines')) {
    /**
     * @param list<array{0:string,1:int}> $fallback_sources
     * @return list<array<string,string>>
     */
    function jwm_material_history_collect_stone_lines(
        $conn,
        string $primary_kind,
        int $primary_id,
        array $fallback_sources,
        string $doc_date,
        string $line_status = 'Allocated'
    ): array {
        if (!$conn || !function_exists('auragold_voucher_list_stone_issue_rows_for_kind')) {
            return [];
        }
        $out = [];
        $seen = [];
        $sources = array_merge([[$primary_kind, $primary_id]], $fallback_sources);
        foreach ($sources as $src) {
            $kind = (string) ($src[0] ?? '');
            $vid = (int) ($src[1] ?? 0);
            if ($kind === '' || $vid < 1) {
                continue;
            }
            $rows = auragold_voucher_list_stone_issue_rows_for_kind($conn, $kind, $vid);
            if ($kind === 'sale_order') {
                $tLegacy = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_order_stone_stock_issue'");
                if ($tLegacy && mysqli_num_rows($tLegacy) > 0) {
                    mysqli_free_result($tLegacy);
                    $leg = function_exists('getList')
                        ? getList(
                            'SELECT barcode, product_name, stone_category, weight, qty '
                            . 'FROM tbl_sale_order_stone_stock_issue WHERE order_id = ' . $vid . ' ORDER BY id ASC'
                        )
                        : [];
                    if (is_array($leg)) {
                        $rows = array_merge($rows, $leg);
                    }
                } elseif ($tLegacy) {
                    mysqli_free_result($tLegacy);
                }
            }
            foreach ($rows as $s) {
                if (!is_array($s)) {
                    continue;
                }
                $bc = trim((string) ($s['barcode'] ?? ''));
                $key = strtolower($kind) . '|' . $vid . '|' . strtolower($bc);
                if ($bc !== '' && isset($seen[$key])) {
                    continue;
                }
                if ($bc !== '') {
                    $seen[$key] = true;
                }
                $out[] = jwm_material_history_line_row(
                    'Stone',
                    $bc,
                    trim((string) ($s['product_name'] ?? '')),
                    jwm_material_fmt_num($s['qty'] ?? 0, 2),
                    jwm_material_fmt_num($s['weight'] ?? 0, 3),
                    'Stone',
                    trim((string) ($s['stone_category'] ?? '')),
                    $doc_date,
                    $line_status
                );
            }
        }

        return $out;
    }
}

if (!function_exists('jwm_material_history_me_issue_owned_by_jwo')) {
    /**
     * On JWO material history: hide sale-order metal exchange auto-issued to MI; keep JWO-added ME only.
     */
    function jwm_material_history_me_issue_owned_by_jwo(mysqli $conn, array $stock_row, int $jwo_id): bool
    {
        if ($jwo_id < 1) {
            return true;
        }
        if ((string) ($stock_row['reference_type'] ?? '') !== 'material_issue_metal_exchange') {
            return true;
        }
        $src_id = (int) ($stock_row['source_stock_id'] ?? 0);
        if ($src_id > 0 && function_exists('getRecord')) {
            $src = getRecord(
                'SELECT reference_type, reference_id FROM tbl_stock WHERE id = '
                . (int) $src_id . ' AND status = 1 LIMIT 1'
            );
            if (!$src || !is_array($src)) {
                return false;
            }
            $rt = trim((string) ($src['reference_type'] ?? ''));
            if ($rt === 'sale_order_metal_exchange') {
                return false;
            }
            if ($rt === 'jobwork_order_metal_exchange') {
                return (int) ($src['reference_id'] ?? 0) === $jwo_id;
            }

            return false;
        }

        $bc = trim((string) ($stock_row['barcode'] ?? ''));
        if ($bc === '') {
            return false;
        }
        $bc_esc = mysqli_real_escape_string($conn, $bc);
        $jwo_me = getRecord(
            "SELECT id FROM tbl_stock WHERE status = 1 AND reference_type = 'jobwork_order_metal_exchange'"
            . ' AND reference_id = ' . (int) $jwo_id
            . " AND barcode = '$bc_esc' LIMIT 1"
        );
        if ($jwo_me && !empty($jwo_me['id'])) {
            return true;
        }
        $jwo_hdr = getRecord('SELECT sale_order_id FROM tbl_jobwork_orders WHERE id = ' . (int) $jwo_id . ' LIMIT 1');
        $soid = (int) ($jwo_hdr['sale_order_id'] ?? 0);
        if ($soid > 0) {
            $so_me = getRecord(
                "SELECT id FROM tbl_stock WHERE status = 1 AND reference_type = 'sale_order_metal_exchange'"
                . ' AND reference_id = ' . (int) $soid
                . " AND barcode = '$bc_esc' LIMIT 1"
            );
            if ($so_me && !empty($so_me['id'])) {
                return false;
            }
        }

        return false;
    }
}

if (!function_exists('jwm_batch_material_voucher_lines_map')) {
    /**
     * Full line detail per material issue/receive document.
     *
     * @param list<int> $voucher_ids
     * @param int $filter_jwo_id When &gt; 0, issue history shows only JWO metal exchange / diamond / stone (not sale order).
     * @return array<int, list<array<string,string>>>
     */
    function jwm_batch_material_voucher_lines_map($conn, array $voucher_ids, string $doc_type, bool $is_repair, int $filter_jwo_id = 0): array
    {
        $map = [];
        if (!$conn) {
            return $map;
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $voucher_ids))));
        if ($ids === []) {
            return $map;
        }
        foreach ($ids as $id) {
            $map[$id] = [];
        }

        $is_issue = ($doc_type === 'issue');
        $voucher_kind = $is_issue ? 'material_issue' : 'material_receive';
        $ref_issue = $is_issue ? 'material_issue' : 'material_receive';
        $ref_me = $is_issue ? 'material_issue_metal_exchange' : 'material_receive_metal_exchange';
        $line_item_status = $is_issue ? 'Issued' : 'Received';
        $line_alloc_status = $is_issue ? 'Allocated' : 'Received';

        if ($is_repair) {
            $items_tbl = $is_issue ? 'tbl_repair_material_issue_items' : 'tbl_repair_material_receive_items';
            $fk = $is_issue ? 'repair_material_issue_id' : 'repair_material_receive_id';
        } else {
            $items_tbl = $is_issue ? 'tbl_material_issue_items' : 'tbl_material_receive_items';
            $fk = $is_issue ? 'material_issue_id' : 'material_receive_id';
        }

        $tchk = @mysqli_query($conn, "SHOW TABLES LIKE '$items_tbl'");
        $has_items = ($tchk && mysqli_num_rows($tchk) > 0);
        if ($tchk) {
            mysqli_free_result($tchk);
        }

        $doc_dates = [];
        if ($is_repair) {
            $hdr_tbl = $is_issue ? 'tbl_repair_material_issues' : 'tbl_repair_material_receives';
        } else {
            $hdr_tbl = $is_issue ? 'tbl_material_issues' : 'tbl_material_receives';
        }
        $ht = @mysqli_query($conn, "SHOW TABLES LIKE '$hdr_tbl'");
        if ($ht && mysqli_num_rows($ht) > 0) {
            mysqli_free_result($ht);
            $in_hdr = implode(',', $ids);
            $date_col = 'order_date';
            $hdr_rows = function_exists('getList')
                ? getList("SELECT id, $date_col, created_at FROM `$hdr_tbl` WHERE id IN ($in_hdr)")
                : [];
            if (is_array($hdr_rows)) {
                foreach ($hdr_rows as $hr) {
                    $doc_dates[(int) ($hr['id'] ?? 0)] = jwm_material_history_doc_date($hr);
                }
            }
        } elseif ($ht) {
            mysqli_free_result($ht);
        }

        if ($has_items) {
            $in = implode(',', $ids);
            $sql = "SELECT it.*, m.display_name AS metal_name, m.system_name AS metal_system
                FROM `$items_tbl` it
                LEFT JOIN tbl_product_characteristics pc ON pc.id = it.product_characteristic_id
                LEFT JOIN tbl_metal m ON m.id = pc.metal_id
                WHERE it.`$fk` IN ($in)
                ORDER BY it.`$fk` ASC, it.id ASC";
            $item_rows = function_exists('getList') ? getList($sql) : [];
            if (is_array($item_rows)) {
                foreach ($item_rows as $it) {
                    $vid = (int) ($it[$fk] ?? 0);
                    if ($vid < 1) {
                        continue;
                    }
                    $dt = $doc_dates[$vid] ?? '';
                    $metal_label = trim((string) ($it['metal_name'] ?? $it['metal_system'] ?? ''));
                    $carat = trim((string) ($it['carat'] ?? ''));
                    $pname = trim((string) ($it['product_name'] ?? ''));
                    $bc = trim((string) ($it['barcode'] ?? ''));
                    $qty = jwm_material_fmt_num($it['quantity'] ?? 1, 2);
                    $fw = (float) ($it['final_weight'] ?? 0);
                    $gw = (float) ($it['gross_weight'] ?? 0);
                    $nw = (float) ($it['net_weight'] ?? 0);
                    $rw = (float) ($it['requested_wt'] ?? 0);
                    $wt_val = $rw > 0.00005 ? $rw : ($fw > 0.00005 ? $fw : ($nw > 0.00005 ? $nw : $gw));
                    $wt = jwm_material_fmt_num($wt_val, 3);
                    $itype = jwm_material_guess_item_type($metal_label, $carat, $pname);
                    $map[$vid][] = jwm_material_history_line_row(
                        $itype,
                        $bc,
                        $pname,
                        $qty,
                        $wt,
                        $metal_label !== '' ? $metal_label : $itype,
                        trim((string) ($it['design_no'] ?? '')) !== '' ? (string) $it['design_no'] : $carat,
                        $dt,
                        $line_item_status
                    );
                }
            }
        }

        if (file_exists(__DIR__ . '/auragold_voucher_diamond_stock.php')) {
            require_once __DIR__ . '/auragold_voucher_diamond_stock.php';
        }
        if (file_exists(__DIR__ . '/auragold_voucher_stone_stock.php')) {
            require_once __DIR__ . '/auragold_voucher_stone_stock.php';
        }
        foreach ($ids as $vid) {
            $dt = $doc_dates[$vid] ?? '';
            $ctx = jwm_material_voucher_doc_context($conn, $doc_type, $vid, $is_repair);
            // Receive history: only lines saved on this MR document (not sale order / JWO allocations).
            $fb = [];
            if ($is_issue) {
                $jwo_for_lines = $filter_jwo_id > 0 ? $filter_jwo_id : (int) ($ctx['jobwork_order_id'] ?? 0);
                if ($filter_jwo_id > 0 && $jwo_for_lines > 0) {
                    $fb[] = ['jobwork_order', $jwo_for_lines];
                } else {
                    if ($ctx['sale_order_id'] > 0) {
                        $fb[] = ['sale_order', $ctx['sale_order_id']];
                    }
                    if ($ctx['jobwork_order_id'] > 0) {
                        $fb[] = ['jobwork_order', $ctx['jobwork_order_id']];
                    }
                }
            }
            foreach (jwm_material_history_collect_diamond_lines($conn, $voucher_kind, $vid, $fb, $dt, $line_alloc_status) as $ln) {
                $map[$vid][] = $ln;
            }
            foreach (jwm_material_history_collect_stone_lines($conn, $voucher_kind, $vid, $fb, $dt, $line_alloc_status) as $ln) {
                $map[$vid][] = $ln;
            }
        }

        $tstock = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_stock'");
        $has_stock = ($tstock && mysqli_num_rows($tstock) > 0);
        if ($tstock) {
            mysqli_free_result($tstock);
        }
        $has_ref = false;
        if ($has_stock) {
            if (!function_exists('auragold_tbl_has_column') && is_file(__DIR__ . '/auragold_branch_data_scope.php')) {
                require_once __DIR__ . '/auragold_branch_data_scope.php';
            }
            if (function_exists('auragold_tbl_has_column')) {
                $has_ref = auragold_tbl_has_column($conn, 'tbl_stock', 'reference_id')
                    && auragold_tbl_has_column($conn, 'tbl_stock', 'reference_type');
            } else {
                $cr = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'reference_id'");
                $ct = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock LIKE 'reference_type'");
                $has_ref = ($cr && mysqli_num_rows($cr) > 0 && $ct && mysqli_num_rows($ct) > 0);
                if ($cr) {
                    mysqli_free_result($cr);
                }
                if ($ct) {
                    mysqli_free_result($ct);
                }
            }
        }
        if ($has_ref) {
            $in = implode(',', $ids);
            $ref_esc = mysqli_real_escape_string($conn, $ref_issue);
            $ref_me_esc = mysqli_real_escape_string($conn, $ref_me);
            $src_col = '';
            if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_stock', 'source_stock_id')) {
                $src_col = ', s.source_stock_id';
            }
            $stock_sql = "SELECT s.reference_id, s.reference_type, s.barcode, s.opening_weight, s.opening_qty,
                    s.current_weight, s.current_qty, s.opening_purity, s.transaction_date $src_col,
                    p.name AS product_name, m.display_name AS metal_name, m.system_name AS metal_system
                FROM tbl_stock s
                LEFT JOIN tbl_products p ON p.id = s.product_id
                LEFT JOIN tbl_product_characteristics pc ON pc.id = s.product_characteristic_id
                LEFT JOIN tbl_metal m ON m.id = COALESCE(NULLIF(s.metal_id, 0), pc.metal_id)
                WHERE s.reference_id IN ($in)
                AND s.reference_type IN ('$ref_esc', '$ref_me_esc')
                AND s.status = 1
                ORDER BY s.reference_id ASC, s.id ASC";
            $stock_rows = function_exists('getList') ? getList($stock_sql) : [];
            if (is_array($stock_rows)) {
                foreach ($stock_rows as $sr) {
                    $vid = (int) ($sr['reference_id'] ?? 0);
                    if ($vid < 1) {
                        continue;
                    }
                    $dt = $doc_dates[$vid] ?? '';
                    if (!empty($sr['transaction_date'])) {
                        $dt = date('d-m-Y', strtotime((string) $sr['transaction_date']));
                    }
                    $cw = (float) ($sr['current_weight'] ?? 0);
                    $ow = (float) ($sr['opening_weight'] ?? 0);
                    $is_me = ((string) ($sr['reference_type'] ?? '')) === $ref_me;
                    // ME issue lines: show weight issued on this MI (opening), not full SO stock balance.
                    if ($is_me && $is_issue) {
                        $wt_val = abs($ow) > 0.00005 ? abs($ow) : abs($cw);
                    } else {
                        $wt_val = abs($cw) > 0.00005 ? abs($cw) : abs($ow);
                    }
                    $cq = (float) ($sr['current_qty'] ?? 0);
                    $oq = (float) ($sr['opening_qty'] ?? 0);
                    $qty_val = abs($cq) > 0.00005 ? abs($cq) : abs($oq);
                    $metal_label = trim((string) ($sr['metal_name'] ?? $sr['metal_system'] ?? ''));
                    $pname = trim((string) ($sr['product_name'] ?? ''));
                    $bc_stock = trim((string) ($sr['barcode'] ?? ''));
                    if (!$is_issue && $bc_stock !== '') {
                        $bc_key = strtolower($bc_stock);
                        $dup = false;
                        foreach ($map[$vid] as $existing) {
                            if (strtolower(trim((string) ($existing['barcode'] ?? ''))) === $bc_key) {
                                $dup = true;
                                break;
                            }
                        }
                        if ($dup) {
                            continue;
                        }
                    }
                    $itype = $is_me ? ($metal_label !== '' ? $metal_label : 'Metal exchange') : jwm_material_guess_item_type($metal_label, '', $pname);
                    $map[$vid][] = jwm_material_history_line_row(
                        $itype,
                        $bc_stock,
                        $pname,
                        jwm_material_fmt_num($qty_val, 2),
                        jwm_material_fmt_num($wt_val, 3),
                        $metal_label !== '' ? $metal_label : $itype,
                        $is_me ? 'Metal exchange' : 'Stock',
                        $dt,
                        $line_alloc_status
                    );
                }
            }
        }

        return $map;
    }
}

if (!function_exists('jwm_material_history_entries_for_js')) {
    /**
     * @param array<int, array<string, mixed>> $rows
     * @return list<array{id:int,no:string,date:string,status:string,grand_total:?float,url:string,lines:list}>
     */
    function jwm_material_history_entries_for_js($conn, array $rows, string $type, bool $is_repair, int $repair_order_id, bool $include_lines = true, int $filter_jwo_id = 0): array
    {
        $is_issue = ($type === 'issue');
        $no_field = $is_issue ? 'material_issue_no' : 'material_receive_no';
        $out = [];
        $ids = [];
        foreach ($rows as $hi) {
            if (!is_array($hi)) {
                continue;
            }
            $hid = (int) ($hi['id'] ?? 0);
            if ($hid > 0) {
                $ids[] = $hid;
            }
        }
        $lines_map = ($include_lines && $conn && $ids !== [])
            ? jwm_batch_material_voucher_lines_map($conn, $ids, $type, $is_repair, $filter_jwo_id)
            : [];

        foreach ($rows as $hi) {
            if (!is_array($hi)) {
                continue;
            }
            $hno = trim((string) ($hi[$no_field] ?? ''));
            if ($hno === '') {
                $hno = '—';
            }
            $hid = (int) ($hi['id'] ?? 0);
            $hdt = jwm_material_history_doc_date($hi);
            $hst = ucfirst(strtolower(trim((string) ($hi['status'] ?? ''))));
            $gt = array_key_exists('grand_total', $hi) ? (float) $hi['grand_total'] : null;
            $out[] = [
                'id' => $hid,
                'no' => $hno,
                'date' => $hdt,
                'status' => $hst,
                'grand_total' => $gt,
                'url' => jwm_material_history_doc_url($type, $hi, $is_repair, $repair_order_id),
                'lines' => $lines_map[$hid] ?? [],
            ];
        }

        return $out;
    }
}

if (!function_exists('jwm_row_material_history')) {
    /**
     * @return array{issues:array,receives:array,is_repair:bool}
     */
    function jwm_row_material_history(array $histories, array $row) {
        $ls = (string) ($row['list_source'] ?? 'jwo');
        $soid = (int) ($row['sale_order_id'] ?? 0);
        $rid = (int) ($row['repair_order_id'] ?? 0);
        $is_repair = ($ls === 'repair' || $ls === 'rjwo');
        if ($is_repair && $rid > 0) {
            return [
                'issues' => $histories['repair_issues'][$rid] ?? [],
                'receives' => $histories['repair_receives'][$rid] ?? [],
                'is_repair' => true,
            ];
        }
        return [
            'issues' => $histories['sale_issues'][$soid] ?? [],
            'receives' => $histories['sale_receives'][$soid] ?? [],
            'is_repair' => false,
        ];
    }
}
