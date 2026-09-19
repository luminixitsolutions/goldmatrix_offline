<?php

/**
 * Shared SQL + helpers for gold-and-silver.php and export endpoints.
 */

function gas_tbl_exists($conn, $table) {
    $t = mysqli_real_escape_string($conn, $table);
    $r = @mysqli_query($conn, "SHOW TABLES LIKE '$t'");
    $ok = $r && mysqli_num_rows($r) > 0;
    if ($r) {
        mysqli_free_result($r);
    }
    return $ok;
}

/** @return array<string, array> */
function gas_sj_columns($conn) {
    $cols = [];
    $r = @mysqli_query($conn, 'SHOW COLUMNS FROM tbl_stock_journal');
    if ($r) {
        while ($row = mysqli_fetch_assoc($r)) {
            $f = strtolower((string) ($row['Field'] ?? ''));
            if ($f !== '') {
                $cols[$f] = $row;
            }
        }
        mysqli_free_result($r);
    }
    return $cols;
}

function gas_sj_has(array $sj_cols, $name) {
    return isset($sj_cols[strtolower((string) $name)]);
}

/** Inward qty: prefer live current_qty; when zeroed (e.g. stock-journal merge), fall back to opening_qty. */
function gas_stock_inward_qty_expr(string $alias = 's'): string
{
    return 'COALESCE(NULLIF(' . $alias . '.current_qty, 0), ' . $alias . '.opening_qty, 0)';
}

/** Inward weight: prefer live current_weight; when zeroed (e.g. stock-journal merge), fall back to opening_weight. */
function gas_stock_inward_wt_expr(string $alias = 's'): string
{
    return 'COALESCE(NULLIF(' . $alias . '.current_weight, 0), ' . $alias . '.opening_weight, 0)';
}

/** @alias gas_stock_inward_wt_expr Net on-hand receipt weight (gold-silver-analysis / stock-history). */
function gas_stock_balance_inward_wt_expr(string $alias = 's'): string
{
    return gas_stock_inward_wt_expr($alias);
}

/** @alias gas_stock_inward_qty_expr Net on-hand receipt qty (gold-silver-analysis / stock-history). */
function gas_stock_balance_inward_qty_expr(string $alias = 's'): string
{
    return gas_stock_inward_qty_expr($alias);
}

/** @return string[] lowercased tbl_metal.display_name values for a tab */
function gas_tab_metal_display_names(string $tab): array
{
    $tab = strtolower(trim($tab));
    if ($tab === 'gold') {
        return ['gold'];
    }
    if ($tab === 'silver') {
        return ['silver'];
    }

    return ['gold', 'silver'];
}

/**
 * Stock row belongs on a gold/silver tab when tbl_stock.metal_id matches or the product characteristic metal does
 * (stock journal rows sometimes default metal_id to Gold when characteristic metal was not resolved).
 */
function gas_sql_stock_matches_tab_metal(mysqli $conn, string $tab, array $tab_metal_ids, string $alias = 's'): string
{
    if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $alias)) {
        $alias = 's';
    }
    $tab_metal_ids = array_values(array_unique(array_filter(array_map('intval', $tab_metal_ids))));
    $parts = [];
    if ($tab_metal_ids !== []) {
        $parts[] = $alias . '.metal_id IN (' . implode(',', $tab_metal_ids) . ')';
    }
    $names = gas_tab_metal_display_names($tab);
    $name_sql = implode(',', array_map(static function ($n) use ($conn) {
        return "'" . mysqli_real_escape_string($conn, $n) . "'";
    }, $names));
    if ($name_sql !== '') {
        $parts[] = "EXISTS (
            SELECT 1 FROM tbl_product_characteristics pc_gas
            INNER JOIN tbl_metal m_gas ON m_gas.id = pc_gas.metal_id AND m_gas.status = 1
            WHERE pc_gas.status = 1
            AND LOWER(TRIM(m_gas.display_name)) IN ($name_sql)
            AND (
                pc_gas.id = {$alias}.product_characteristic_id
                OR (
                    {$alias}.product_characteristic_id IS NULL
                    AND pc_gas.product_id = {$alias}.product_id
                    AND (pc_gas.branch_id = {$alias}.branch_id OR pc_gas.branch_id IS NULL OR {$alias}.branch_id IS NULL OR pc_gas.branch_id = 0 OR {$alias}.branch_id = 0)
                )
            )
        )";
    }
    if ($parts === []) {
        return '1=0';
    }

    return '(' . implode(' OR ', $parts) . ')';
}

function gas_sj_active_sql(array $sj_cols) {
    if (!gas_sj_has($sj_cols, 'status')) {
        return '1=1';
    }
    $type = strtolower((string) ($sj_cols['status']['Type'] ?? ''));
    if (strpos($type, 'char') !== false || strpos($type, 'text') !== false || strpos($type, 'enum') !== false) {
        return "(status = 'active' OR LOWER(TRIM(CAST(status AS CHAR))) = 'active')";
    }
    return '(status = 1 OR status = \'1\')';
}

function gas_sj_sel_expr(array $sj_cols, $field, $alias = null) {
    $a = $alias !== null ? $alias : $field;
    if (gas_sj_has($sj_cols, $field)) {
        return 'sj1.`' . str_replace('`', '', $field) . '` AS `' . str_replace('`', '', $a) . '`';
    }
    return 'NULL AS `' . str_replace('`', '', $a) . '`';
}

function gas_fmt_num($v, $dec = 3) {
    if ($v === null || $v === '') {
        return '';
    }
    $x = (float) $v;
    if (!is_finite($x)) {
        return '';
    }
    return number_format($x, $dec, '.', '');
}

function gas_fmt_money($v) {
    if ($v === null || $v === '') {
        return '';
    }
    $x = (float) $v;
    if (!is_finite($x)) {
        return '';
    }
    return number_format($x, 2, '.', '');
}

/**
 * Web path to app root (parent of /admin), e.g. /auragold from script .../admin/ajax/x.php or .../admin/x.php.
 */
function gas_app_web_root_path_from_script(): string {
    $sn = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($sn === '' || $sn === '/') {
        return '';
    }
    $dir = rtrim(dirname($sn), '/');
    if (preg_match('#^(.*)/admin(?:/|$)#u', $dir . '/', $m)) {
        return rtrim($m[1], '/') ?: '';
    }
    return '';
}

/**
 * Browser URL for a stored path such as uploads/stock_journal/file.jpg (under site root per $SiteUrl).
 */
function gas_public_url_for_stored_path(?string $path, $SiteUrl): string {
    $path = trim((string) $path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    if (strpos($path, '/') === 0) {
        if (preg_match('#^/uploads/#', $path)) {
            $under = auragold_uploads_public_rel(ltrim($path, '/'));
            $base = isset($SiteUrl) ? rtrim((string) $SiteUrl, '/') : '';
            if ($base !== '') {
                return $base . '/' . $under;
            }
            $appRoot = gas_app_web_root_path_from_script();
            if ($appRoot !== '') {
                return $appRoot . '/' . $under;
            }
            return '/' . $under;
        }
        return $path;
    }
    $rel = ltrim($path, '/');
    $under = auragold_uploads_public_rel($rel);
    $base = isset($SiteUrl) ? rtrim((string) $SiteUrl, '/') : '';
    if ($base !== '') {
        return $base . '/' . $under;
    }
    $appRoot = gas_app_web_root_path_from_script();
    if ($appRoot !== '') {
        return $appRoot . '/' . $under;
    }
    return '/' . $under;
}

/**
 * Match closing-stock branch rules: main branch rows may be stored as NULL/0 branch_id.
 *
 * @return string SQL predicate (no leading AND) or empty when no filter
 */
function gas_tbl_stock_branch_predicate(mysqli $conn, int $bid, string $alias = 's') {
    if ($bid <= 0) {
        return '';
    }
    if (!function_exists('auragold_tbl_has_column') || !auragold_tbl_has_column($conn, 'tbl_stock', 'branch_id')) {
        return '';
    }
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $alias)) {
        $alias = 's';
    }
    $main = function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0;
    $bid = (int) $bid;
    if ($main > 0 && $bid === $main) {
        return '(' . $alias . '.branch_id = ' . $bid . ' OR ' . $alias . '.branch_id IS NULL OR ' . $alias . '.branch_id = 0)';
    }
    return 'COALESCE(' . $alias . '.branch_id, 0) = ' . $bid;
}

/**
 * @param array{
 *   meta_only?: bool,
 *   paginate?: bool,
 *   fast_paginate?: bool,
 *   page?: int,
 *   per_page?: int,
 *   skip_rows?: bool,
 *   totals_only?: bool
 * } $options
 * @return array{
 *   rows: array<int, array<string, mixed>>,
 *   error: string,
 *   has_journal_images: bool,
 *   extra_field_defs: array<string, array{id:int,label:string,metal_type:string}>,
 *   branch_id: int,
 *   pagination?: array{page:int,per_page:int,total:int,total_pages:int}
 * }
 */
function auragold_gold_silver_stock_list_fetch(mysqli $conn, string $tab, array $options = []): array
{
    $tab = strtolower(trim($tab));
    if (!in_array($tab, ['gold', 'silver', 'all'], true)) {
        $tab = 'gold';
    }

    $meta_only = !empty($options['meta_only']);
    $paginate = !empty($options['paginate']);
    $fast_paginate = !empty($options['fast_paginate']);
    $skip_rows = !empty($options['skip_rows']);
    $totals_only = !empty($options['totals_only']);
    $page = max(1, (int) ($options['page'] ?? 1));
    $per_page = max(10, min(100, (int) ($options['per_page'] ?? 20)));
    $offset = ($page - 1) * $per_page;

    $branch_filter = 0;
    $eff_branch = function_exists('auragold_effective_branch_id') ? (int) auragold_effective_branch_id() : 0;
    if ($eff_branch > 0) {
        $branch_filter = $eff_branch;
    } else {
        $wb = isset($_SESSION['working_branch_id']) ? (int) $_SESSION['working_branch_id'] : 0;
        if ($wb <= 0 && !empty($_SESSION['branch_id'])) {
            $wb = (int) $_SESSION['branch_id'];
        }
        if ($wb > 0) {
            $branch_filter = $wb;
        }
    }

    $scope_metals = getList("SELECT id, display_name AS name FROM tbl_metal WHERE status = 1 AND LOWER(TRIM(display_name)) IN ('gold','silver') ORDER BY display_name ASC, id ASC");
    $scope_metal_ids = array_map('intval', array_column($scope_metals ?: [], 'id'));
    $gold_metal_ids = [];
    $silver_metal_ids = [];
    foreach ($scope_metals ?: [] as $sm) {
        $nm = strtolower(trim((string) ($sm['name'] ?? '')));
        $mid = (int) ($sm['id'] ?? 0);
        if ($mid <= 0) {
            continue;
        }
        if ($nm === 'gold') {
            $gold_metal_ids[] = $mid;
        } elseif ($nm === 'silver') {
            $silver_metal_ids[] = $mid;
        }
    }
    if (empty($scope_metal_ids)) {
        $scope_metal_ids = [1, 2];
    }
    $tab_metal_ids = $scope_metal_ids;
    if ($tab === 'gold' && $gold_metal_ids !== []) {
        $tab_metal_ids = $gold_metal_ids;
    } elseif ($tab === 'silver' && $silver_metal_ids !== []) {
        $tab_metal_ids = $silver_metal_ids;
    }
    $tab_metal_ids = array_values(array_unique(array_map('intval', $tab_metal_ids)));
    if ($tab_metal_ids === []) {
        $tab_metal_ids = [0];
    }

    $gas_has_journal_images = gas_tbl_exists($conn, 'tbl_stock_journal_images');
    $extra_field_defs = auragold_gold_silver_stock_extra_field_defs($conn, $branch_filter, $tab);

    if ($meta_only) {
        return [
            'rows' => [],
            'error' => '',
            'has_journal_images' => $gas_has_journal_images,
            'extra_field_defs' => $extra_field_defs,
            'branch_id' => $branch_filter,
        ];
    }

    $has_stock = gas_tbl_exists($conn, 'tbl_stock');
    $sj_cols = [];
    $has_stock_journal = gas_tbl_exists($conn, 'tbl_stock_journal');
    if ($has_stock_journal) {
        $sj_cols = gas_sj_columns($conn);
        if (!gas_sj_has($sj_cols, 'id') || !gas_sj_has($sj_cols, 'barcode')) {
            $has_stock_journal = false;
        }
    }

    $metal_has_display = false;
    $metal_has_system = false;
    $mh = @mysqli_query($conn, 'SHOW COLUMNS FROM tbl_metal');
    if ($mh) {
        while ($mr = mysqli_fetch_assoc($mh)) {
            $fn = strtolower((string) ($mr['Field'] ?? ''));
            if ($fn === 'display_name') {
                $metal_has_display = true;
            }
            if ($fn === 'system_name') {
                $metal_has_system = true;
            }
        }
        mysqli_free_result($mh);
    }
    if ($metal_has_display && $metal_has_system) {
        $metal_name_expr = 'COALESCE(NULLIF(TRIM(m.display_name), \'\'), NULLIF(TRIM(m.system_name), \'\'), \'\')';
    } elseif ($metal_has_display) {
        $metal_name_expr = 'COALESCE(NULLIF(TRIM(m.display_name), \'\'), \'\')';
    } elseif ($metal_has_system) {
        $metal_name_expr = 'COALESCE(NULLIF(TRIM(m.system_name), \'\'), \'\')';
    } else {
        $metal_name_expr = '\'\'';
    }

    $inner_where = [
        's.status = 1',
        "(s.barcode IS NOT NULL AND TRIM(COALESCE(s.barcode,'')) <> '')",
        gas_sql_stock_matches_tab_metal($conn, $tab, $tab_metal_ids, 's'),
    ];
    $gas_br_pred = gas_tbl_stock_branch_predicate($conn, $branch_filter, 's');
    if ($gas_br_pred !== '') {
        $inner_where[] = $gas_br_pred;
    }
    $inner_sql = implode(' AND ', $inner_where);

    $gas_stk_in_types_sql = "'opening','purchase','stock_journal','balance','sale_return','inward'";

    $in_qty = gas_stock_balance_inward_qty_expr('s');
    $in_wt = gas_stock_balance_inward_wt_expr('s');
    $agg_subquery = "
    SELECT s.barcode, s.branch_id,
        (SUM(CASE WHEN s.stock_type IN ($gas_stk_in_types_sql) THEN $in_qty ELSE 0 END)
         - SUM(CASE WHEN s.stock_type = 'outward' THEN COALESCE(NULLIF(s.current_qty, 0), s.opening_qty, 0) ELSE 0 END)) AS bal_qty,
        (SUM(CASE WHEN s.stock_type IN ($gas_stk_in_types_sql) THEN $in_wt ELSE 0 END)
         - SUM(CASE WHEN s.stock_type = 'outward' THEN COALESCE(NULLIF(s.current_weight, 0), s.opening_weight, 0) ELSE 0 END)) AS bal_wt,
        COALESCE(
            MAX(CASE WHEN s.stock_type IN ($gas_stk_in_types_sql)
                AND ($in_wt) > 0.00001 THEN s.id END),
            MAX(CASE WHEN s.stock_type IN ($gas_stk_in_types_sql) THEN s.id END),
            MAX(s.id)
        ) AS pick_id
    FROM tbl_stock s
    LEFT JOIN tbl_products p ON s.product_id = p.id
    LEFT JOIN tbl_product_characteristics pc ON s.product_characteristic_id = pc.id
    WHERE $inner_sql
    GROUP BY s.barcode, s.branch_id
    HAVING (bal_qty > 0.00001 OR bal_wt > 0.00001)
";

    $sj_join_sql = '';
    $sj_sel_sql = '';
    $sj_status_sql = '';
    $img_join_sql = '';
    if ($has_stock_journal) {
        $sj_status_sql = gas_sj_active_sql($sj_cols);
        $sj_parts = [
            gas_sj_sel_expr($sj_cols, 'id', 'sj_row_id'),
            gas_sj_sel_expr($sj_cols, 'item_id', 'sj_item_id'),
            gas_sj_sel_expr($sj_cols, 'barcode', 'sj_barcode'),
            gas_sj_sel_expr($sj_cols, 'invoice_id', 'sj_invoice_id'),
            gas_sj_sel_expr($sj_cols, 'huid_no', 'huid_no'),
            gas_sj_sel_expr($sj_cols, 'voucher_type', 'voucher_type'),
            gas_sj_sel_expr($sj_cols, 'invoice_no', 'invoice_no'),
            gas_sj_sel_expr($sj_cols, 'location', 'sj_location'),
            gas_sj_sel_expr($sj_cols, 'gross_weight', 'gross_weight'),
            gas_sj_sel_expr($sj_cols, 'net_weight', 'net_weight'),
            gas_sj_sel_expr($sj_cols, 'purity_weight', 'purity_weight'),
            gas_sj_sel_expr($sj_cols, 'pure_weight', 'pure_weight'),
            gas_sj_sel_expr($sj_cols, 'quantity', 'sj_quantity'),
            gas_sj_sel_expr($sj_cols, 'karat', 'sj_karat'),
            gas_sj_sel_expr($sj_cols, 'metal_cost', 'metal_cost'),
            gas_sj_sel_expr($sj_cols, 'making_cost', 'making_cost'),
            gas_sj_sel_expr($sj_cols, 'stone_weight', 'stone_weight'),
            gas_sj_sel_expr($sj_cols, 'making_amount', 'making_amount'),
            gas_sj_sel_expr($sj_cols, 'stone_cost', 'stone_cost'),
            gas_sj_sel_expr($sj_cols, 'purchase_amount', 'purchase_amount'),
            gas_sj_sel_expr($sj_cols, 'making_type', 'making_type'),
            gas_sj_sel_expr($sj_cols, 'metal_value', 'metal_value'),
            gas_sj_sel_expr($sj_cols, 'stone_rate', 'stone_rate'),
            gas_sj_sel_expr($sj_cols, 'stone_charge_type', 'stone_charge_type'),
            gas_sj_sel_expr($sj_cols, 'stone_amount', 'stone_amount'),
            gas_sj_sel_expr($sj_cols, 'making_rate', 'making_rate'),
            gas_sj_sel_expr($sj_cols, 'wastage_wt', 'wastage_wt'),
            gas_sj_sel_expr($sj_cols, 'wastage_per', 'wastage_per'),
            gas_sj_sel_expr($sj_cols, 'comment', 'sj_comment'),
            gas_sj_sel_expr($sj_cols, 'other_info', 'other_info'),
            gas_sj_sel_expr($sj_cols, 'group_name', 'group_name'),
            gas_sj_sel_expr($sj_cols, 'category', 'sj_category'),
            gas_sj_sel_expr($sj_cols, 'created_at', 'sj_created_at'),
            gas_sj_sel_expr($sj_cols, 'status', 'sj_status'),
        ];
        $sj_sel_sql = implode(",\n            ", $sj_parts);
        $sj_join_sql = "
    LEFT JOIN (
        SELECT
            $sj_sel_sql
        FROM tbl_stock_journal sj1
        INNER JOIN (
            SELECT barcode, MAX(id) AS max_id
            FROM tbl_stock_journal
            WHERE ($sj_status_sql) AND barcode IS NOT NULL AND TRIM(barcode) <> ''
            GROUP BY barcode
        ) sjmx ON sjmx.max_id = sj1.id
    ) sj ON (sj.sj_barcode COLLATE utf8mb4_general_ci) = (s.barcode COLLATE utf8mb4_general_ci)
        AND s.barcode IS NOT NULL AND TRIM(COALESCE(s.barcode,'')) <> ''
    ";
    }

    $gas_has_journal_images = gas_tbl_exists($conn, 'tbl_stock_journal_images');
    if ($gas_has_journal_images) {
        $img_join_sql = "
    LEFT JOIN (
        SELECT barcode_no, GROUP_CONCAT(image_path ORDER BY id SEPARATOR ',') AS image_urls_agg
        FROM tbl_stock_journal_images
        GROUP BY barcode_no
    ) imgs ON TRIM(imgs.barcode_no) = TRIM(s.barcode)
    ";
    }

    $pi_join = '';
    if (gas_tbl_exists($conn, 'tbl_purchase_invoices')) {
        $pi_join = 'LEFT JOIN tbl_purchase_invoices pi ON pi.id = sj.sj_invoice_id';
    }

    $cat_join = '';
    if (gas_tbl_exists($conn, 'tbl_categories')) {
        $cat_join = 'LEFT JOIN tbl_categories cat ON cat.id = p.category_id';
    }

    $img_select_expr = ($img_join_sql !== '') ? 'COALESCE(imgs.image_urls_agg, \'\')' : '\'\'';

    $select_outer = "
        s.id AS stock_id,
        s.barcode,
        bal.bal_qty AS current_qty,
        bal.bal_wt AS current_weight,
        s.final_weight,
        s.opening_weight,
        s.opening_purity,
        s.status AS stock_status,
        s.created_at AS stock_created_at,
        b.name AS branch_name,
        $metal_name_expr AS metal_name,
        p.name AS product_name,
        p.article AS article,
        p.id AS product_id,
        pc.carat AS pc_carat,
        " . ($cat_join !== '' ? "COALESCE(NULLIF(TRIM(cat.name),''), NULLIF(TRIM(sj.sj_category),''), '')" : "COALESCE(NULLIF(TRIM(sj.sj_category),''), '')") . " AS category_display,
        " . ($pi_join !== '' ? 'pi.supplier_name' : "''") . " AS supplier_name,
        $img_select_expr AS image_urls,
        TRIM(CONCAT_WS(' | ',
            NULLIF(TRIM(sj.sj_comment),''),
            NULLIF(TRIM(sj.other_info),''),
            NULLIF(TRIM(sj.group_name),'')
        )) AS info_text,
        sj.huid_no,
        sj.voucher_type,
        sj.invoice_no,
        sj.sj_location,
        sj.gross_weight AS sj_gross_weight,
        sj.net_weight AS sj_net_weight,
        sj.purity_weight AS sj_purity_weight,
        sj.pure_weight AS sj_pure_weight,
        sj.sj_quantity,
        sj.sj_karat,
        sj.metal_cost,
        sj.making_cost,
        sj.stone_weight,
        sj.making_amount,
        sj.stone_cost,
        sj.purchase_amount,
        sj.making_type,
        sj.metal_value,
        sj.stone_rate,
        sj.stone_charge_type,
        sj.stone_amount,
        sj.making_rate,
        sj.wastage_wt,
        sj.wastage_per,
        sj.sj_created_at,
        sj.sj_status,
        sj.sj_item_id
";

    $select_detail = preg_replace(
        '/bal\.bal_qty AS current_qty,\s*\n\s*bal\.bal_wt AS current_weight,/',
        "0 AS current_qty,\n        0 AS current_weight,",
        $select_outer
    );
    if ($select_detail === null || $select_detail === $select_outer) {
        $select_detail = $select_outer;
    }

    $rows = [];
    $load_error = '';
    $pagination = null;

    if (!$has_stock) {
        $load_error = 'Stock table not found.';
    } else {
        $use_paginate = $paginate && !$totals_only;
        $limit_sql = ' LIMIT 5000';

        if ($use_paginate || $totals_only) {
            if ($use_paginate && !$skip_rows) {
                $count_row = getRecord('SELECT COUNT(*) AS c FROM (' . $agg_subquery . ') bal');
                $total_rows = (int) ($count_row['c'] ?? 0);
                $total_pages = $total_rows > 0 ? (int) ceil($total_rows / $per_page) : 1;
                if ($page > $total_pages) {
                    $page = $total_pages;
                    $offset = ($page - 1) * $per_page;
                }
                $pagination = [
                    'page' => $page,
                    'per_page' => $per_page,
                    'total' => $total_rows,
                    'total_pages' => max(1, $total_pages),
                ];
            }

            if (!$skip_rows) {
                if ($use_paginate && $fast_paginate) {
                    $keys_sql = '
    SELECT bal.pick_id, bal.barcode, bal.bal_qty, bal.bal_wt
    FROM (' . $agg_subquery . ') bal
    ORDER BY bal.barcode ASC, bal.pick_id ASC
    LIMIT ' . (int) $per_page . ' OFFSET ' . (int) $offset;
                    $keys_res = mysqli_query($conn, $keys_sql);
                    $pick_ids = [];
                    $bal_map = [];
                    if (!$keys_res) {
                        $load_error = 'Could not load stock: ' . htmlspecialchars(mysqli_error($conn), ENT_QUOTES, 'UTF-8');
                    } else {
                        while ($kr = mysqli_fetch_assoc($keys_res)) {
                            $pid = (int) ($kr['pick_id'] ?? 0);
                            if ($pid <= 0) {
                                continue;
                            }
                            $pick_ids[] = $pid;
                            $bal_map[$pid] = $kr;
                        }
                        mysqli_free_result($keys_res);
                    }

                    if ($load_error === '' && $pick_ids !== []) {
                        $ids_sql = implode(',', array_map('intval', $pick_ids));
                        $sj_join_detail = $sj_join_sql;
                        if ($has_stock_journal && $sj_sel_sql !== '') {
                            $bc_esc = [];
                            foreach ($bal_map as $kr) {
                                $bc = trim((string) ($kr['barcode'] ?? ''));
                                if ($bc !== '') {
                                    $bc_esc[] = "'" . mysqli_real_escape_string($conn, $bc) . "'";
                                }
                            }
                            if ($bc_esc !== []) {
                                $bc_in_sql = implode(',', $bc_esc);
                                $sj_join_detail = "
    LEFT JOIN (
        SELECT
            $sj_sel_sql
        FROM tbl_stock_journal sj1
        INNER JOIN (
            SELECT barcode, MAX(id) AS max_id
            FROM tbl_stock_journal
            WHERE ($sj_status_sql) AND barcode IN ($bc_in_sql)
            GROUP BY barcode
        ) sjmx ON sjmx.max_id = sj1.id
    ) sj ON (sj.sj_barcode COLLATE utf8mb4_general_ci) = (s.barcode COLLATE utf8mb4_general_ci)
        AND s.barcode IS NOT NULL AND TRIM(COALESCE(s.barcode,'')) <> ''";
                            }
                        }

                        if (!$has_stock_journal) {
                            $detail_sql = "
    SELECT
        s.id AS stock_id,
        s.barcode,
        0 AS current_qty,
        0 AS current_weight,
        s.final_weight,
        s.opening_weight,
        s.opening_purity,
        s.status AS stock_status,
        s.created_at AS stock_created_at,
        b.name AS branch_name,
        $metal_name_expr AS metal_name,
        p.name AS product_name,
        p.article AS article,
        p.id AS product_id,
        pc.carat AS pc_carat,
        '' AS category_display,
        '' AS supplier_name,
        $img_select_expr AS image_urls,
        '' AS info_text,
        NULL AS huid_no,
        NULL AS voucher_type,
        NULL AS invoice_no,
        NULL AS sj_location,
        NULL AS sj_gross_weight,
        NULL AS sj_net_weight,
        NULL AS sj_purity_weight,
        NULL AS sj_pure_weight,
        NULL AS sj_quantity,
        NULL AS sj_karat,
        NULL AS metal_cost,
        NULL AS making_cost,
        NULL AS stone_weight,
        NULL AS making_amount,
        NULL AS stone_cost,
        NULL AS purchase_amount,
        NULL AS making_type,
        NULL AS metal_value,
        NULL AS stone_rate,
        NULL AS stone_charge_type,
        NULL AS stone_amount,
        NULL AS making_rate,
        NULL AS wastage_wt,
        NULL AS wastage_per,
        NULL AS sj_created_at,
        NULL AS sj_status,
        NULL AS sj_item_id
    FROM tbl_stock s
    LEFT JOIN tbl_branches b ON s.branch_id = b.id
    LEFT JOIN tbl_metal m ON s.metal_id = m.id
    LEFT JOIN tbl_products p ON s.product_id = p.id
    $cat_join
    LEFT JOIN tbl_product_characteristics pc ON s.product_characteristic_id = pc.id
    $img_join_sql
    WHERE s.id IN ($ids_sql)
    ORDER BY s.barcode ASC, s.branch_id ASC";
                        } else {
                            $detail_sql = "
    SELECT $select_detail
    FROM tbl_stock s
    LEFT JOIN tbl_branches b ON s.branch_id = b.id
    LEFT JOIN tbl_metal m ON s.metal_id = m.id
    LEFT JOIN tbl_products p ON s.product_id = p.id
    $cat_join
    LEFT JOIN tbl_product_characteristics pc ON s.product_characteristic_id = pc.id
    $sj_join_detail
    $pi_join
    $img_join_sql
    WHERE s.id IN ($ids_sql)
    ORDER BY s.barcode ASC, s.branch_id ASC";
                        }

                        $detail_res = mysqli_query($conn, $detail_sql);
                        if (!$detail_res) {
                            $load_error = 'Could not load stock details: ' . htmlspecialchars(mysqli_error($conn), ENT_QUOTES, 'UTF-8');
                        } else {
                            while ($r = mysqli_fetch_assoc($detail_res)) {
                                $pid = (int) ($r['stock_id'] ?? 0);
                                $bal = $bal_map[$pid] ?? [];
                                if ($bal !== []) {
                                    $r['current_qty'] = $bal['bal_qty'] ?? 0;
                                    $r['current_weight'] = $bal['bal_wt'] ?? 0;
                                }
                                $rows[] = $r;
                            }
                            mysqli_free_result($detail_res);
                        }
                    } elseif ($load_error === '' && $pick_ids === [] && ($pagination['total'] ?? 0) > 0) {
                        $load_error = 'Could not resolve stock rows for this page.';
                    }
                } else {
                    $limit_sql = $totals_only
                        ? ' LIMIT 5000'
                        : (' LIMIT ' . (int) $per_page . ' OFFSET ' . (int) $offset);
                    if (!$has_stock_journal) {
                        $sql = "
    SELECT
        s.id AS stock_id,
        s.barcode,
        bal.bal_qty AS current_qty,
        bal.bal_wt AS current_weight,
        s.final_weight,
        s.opening_weight,
        s.opening_purity,
        s.status AS stock_status,
        s.created_at AS stock_created_at,
        b.name AS branch_name,
        $metal_name_expr AS metal_name,
        p.name AS product_name,
        p.article AS article,
        p.id AS product_id,
        pc.carat AS pc_carat,
        '' AS category_display,
        '' AS supplier_name,
        $img_select_expr AS image_urls,
        '' AS info_text,
        NULL AS huid_no,
        NULL AS voucher_type,
        NULL AS invoice_no,
        NULL AS sj_location,
        NULL AS sj_gross_weight,
        NULL AS sj_net_weight,
        NULL AS sj_purity_weight,
        NULL AS sj_pure_weight,
        NULL AS sj_quantity,
        NULL AS sj_karat,
        NULL AS metal_cost,
        NULL AS making_cost,
        NULL AS stone_weight,
        NULL AS making_amount,
        NULL AS stone_cost,
        NULL AS purchase_amount,
        NULL AS making_type,
        NULL AS metal_value,
        NULL AS stone_rate,
        NULL AS stone_charge_type,
        NULL AS stone_amount,
        NULL AS making_rate,
        NULL AS wastage_wt,
        NULL AS wastage_per,
        NULL AS sj_created_at,
        NULL AS sj_status,
        NULL AS sj_item_id
    FROM ($agg_subquery) bal
    INNER JOIN tbl_stock s ON s.id = bal.pick_id
    LEFT JOIN tbl_branches b ON s.branch_id = b.id
    LEFT JOIN tbl_metal m ON s.metal_id = m.id
    LEFT JOIN tbl_products p ON s.product_id = p.id
    $cat_join
    LEFT JOIN tbl_product_characteristics pc ON s.product_characteristic_id = pc.id
    $img_join_sql
    ORDER BY s.barcode ASC, s.branch_id ASC" . $limit_sql;
                    } else {
                        $sql = "
    SELECT $select_outer
    FROM ($agg_subquery) bal
    INNER JOIN tbl_stock s ON s.id = bal.pick_id
    LEFT JOIN tbl_branches b ON s.branch_id = b.id
    LEFT JOIN tbl_metal m ON s.metal_id = m.id
    LEFT JOIN tbl_products p ON s.product_id = p.id
    $cat_join
    LEFT JOIN tbl_product_characteristics pc ON s.product_characteristic_id = pc.id
    $sj_join_sql
    $pi_join
    $img_join_sql
    ORDER BY s.barcode ASC, s.branch_id ASC" . $limit_sql;
                    }
                    $res = mysqli_query($conn, $sql);
                    if (!$res) {
                        $load_error = 'Could not load stock: ' . htmlspecialchars(mysqli_error($conn), ENT_QUOTES, 'UTF-8');
                    } else {
                        while ($r = mysqli_fetch_assoc($res)) {
                            $rows[] = $r;
                        }
                        mysqli_free_result($res);
                    }
                }
            }
        } else {
            if (!$has_stock_journal) {
                $sql = "
    SELECT
        s.id AS stock_id,
        s.barcode,
        bal.bal_qty AS current_qty,
        bal.bal_wt AS current_weight,
        s.final_weight,
        s.opening_weight,
        s.opening_purity,
        s.status AS stock_status,
        s.created_at AS stock_created_at,
        b.name AS branch_name,
        $metal_name_expr AS metal_name,
        p.name AS product_name,
        p.article AS article,
        p.id AS product_id,
        pc.carat AS pc_carat,
        '' AS category_display,
        '' AS supplier_name,
        $img_select_expr AS image_urls,
        '' AS info_text,
        NULL AS huid_no,
        NULL AS voucher_type,
        NULL AS invoice_no,
        NULL AS sj_location,
        NULL AS sj_gross_weight,
        NULL AS sj_net_weight,
        NULL AS sj_purity_weight,
        NULL AS sj_pure_weight,
        NULL AS sj_quantity,
        NULL AS sj_karat,
        NULL AS metal_cost,
        NULL AS making_cost,
        NULL AS stone_weight,
        NULL AS making_amount,
        NULL AS stone_cost,
        NULL AS purchase_amount,
        NULL AS making_type,
        NULL AS metal_value,
        NULL AS stone_rate,
        NULL AS stone_charge_type,
        NULL AS stone_amount,
        NULL AS making_rate,
        NULL AS wastage_wt,
        NULL AS wastage_per,
        NULL AS sj_created_at,
        NULL AS sj_status,
        NULL AS sj_item_id
    FROM ($agg_subquery) bal
    INNER JOIN tbl_stock s ON s.id = bal.pick_id
    LEFT JOIN tbl_branches b ON s.branch_id = b.id
    LEFT JOIN tbl_metal m ON s.metal_id = m.id
    LEFT JOIN tbl_products p ON s.product_id = p.id
    $cat_join
    LEFT JOIN tbl_product_characteristics pc ON s.product_characteristic_id = pc.id
    $img_join_sql
    ORDER BY s.barcode ASC, s.branch_id ASC
    LIMIT 5000
    ";
            } else {
                $sql = "
    SELECT $select_outer
    FROM ($agg_subquery) bal
    INNER JOIN tbl_stock s ON s.id = bal.pick_id
    LEFT JOIN tbl_branches b ON s.branch_id = b.id
    LEFT JOIN tbl_metal m ON s.metal_id = m.id
    LEFT JOIN tbl_products p ON s.product_id = p.id
    $cat_join
    LEFT JOIN tbl_product_characteristics pc ON s.product_characteristic_id = pc.id
    $sj_join_sql
    $pi_join
    $img_join_sql
    ORDER BY s.barcode ASC, s.branch_id ASC
    LIMIT 5000
    ";
            }
            $res = mysqli_query($conn, $sql);
            if (!$res) {
                $load_error = 'Could not load stock: ' . htmlspecialchars(mysqli_error($conn), ENT_QUOTES, 'UTF-8');
            } else {
                while ($r = mysqli_fetch_assoc($res)) {
                    $rows[] = $r;
                }
                mysqli_free_result($res);
            }
        }
    }

    if (!empty($rows) && !empty($extra_field_defs)) {
        auragold_gold_silver_stock_attach_extra_field_values($conn, $rows, $extra_field_defs);
    }

    $result = [
        'rows' => $rows,
        'error' => $load_error,
        'has_journal_images' => $gas_has_journal_images,
        'extra_field_defs' => $extra_field_defs,
        'branch_id' => $branch_filter,
    ];
    if ($pagination !== null) {
        $result['pagination'] = $pagination;
    }

    return $result;
}

/**
 * Active extra-field column definitions for gold-and-silver tabs.
 *
 * @return array<string, array{id:int,label:string,metal_type:string}>
 */
function auragold_gold_silver_stock_extra_field_defs($conn, int $branch_id, string $tab): array
{
    if (!$conn instanceof mysqli) {
        return [];
    }
    if (!function_exists('auragold_get_extra_fields')) {
        require_once __DIR__ . '/auragold_extra_fields_schema.php';
    }

    $metals = [];
    $tab = strtolower(trim($tab));
    if ($tab === 'gold' || $tab === 'all') {
        $metals[] = 'Gold';
    }
    if ($tab === 'silver' || $tab === 'all') {
        $metals[] = 'Silver';
    }

    $defs = [];
    foreach ($metals as $metal) {
        foreach (auragold_get_extra_fields($conn, $branch_id, $metal) as $field) {
            if ((int) ($field['status'] ?? 0) !== 1) {
                continue;
            }
            $id = (int) ($field['id'] ?? 0);
            $label = trim((string) ($field['display_name'] ?? ''));
            if ($id <= 0 || $label === '') {
                continue;
            }
            $key = 'ef_' . $id;
            $defs[$key] = [
                'id' => $id,
                'label' => $label,
                'metal_type' => (string) ($field['metal_type'] ?? $metal),
            ];
        }
    }

    return $defs;
}

/**
 * Attach parsed extra-field values to each stock row (by barcode / purchase line id).
 *
 * @param array<int, array<string, mixed>> $rows
 * @param array<string, array{id:int,label:string,metal_type:string}> $field_defs
 */
function auragold_gold_silver_stock_attach_extra_field_values($conn, array &$rows, array $field_defs): void
{
    if (!$conn instanceof mysqli || empty($rows) || empty($field_defs)) {
        return;
    }
    if (!function_exists('auragold_ensure_extra_fields_json_column')) {
        require_once __DIR__ . '/auragold_extra_fields_item_values.php';
    }
    if (!auragold_ensure_extra_fields_json_column($conn, 'tbl_purchase_invoice_items')) {
        return;
    }

    $barcodes = [];
    $item_ids = [];
    foreach ($rows as $row) {
        $bc = trim((string) ($row['barcode'] ?? ''));
        if ($bc !== '') {
            $barcodes[$bc] = $bc;
        }
        $iid = (int) ($row['sj_item_id'] ?? 0);
        if ($iid > 0) {
            $item_ids[$iid] = $iid;
        }
    }

    $json_by_barcode = [];
    $json_by_item_id = [];
    if (!empty($barcodes)) {
        $in = [];
        foreach ($barcodes as $bc) {
            $in[] = "'" . mysqli_real_escape_string($conn, $bc) . "'";
        }
        $res = @mysqli_query(
            $conn,
            'SELECT id, barcode, extra_fields_json FROM tbl_purchase_invoice_items
             WHERE barcode IN (' . implode(',', $in) . ')
               AND extra_fields_json IS NOT NULL AND TRIM(extra_fields_json) <> \'\'
             ORDER BY id DESC'
        );
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) {
                $bc = trim((string) ($r['barcode'] ?? ''));
                $raw = trim((string) ($r['extra_fields_json'] ?? ''));
                if ($bc !== '' && $raw !== '' && !isset($json_by_barcode[$bc])) {
                    $json_by_barcode[$bc] = $raw;
                }
                $iid = (int) ($r['id'] ?? 0);
                if ($iid > 0 && $raw !== '' && !isset($json_by_item_id[$iid])) {
                    $json_by_item_id[$iid] = $raw;
                }
            }
            mysqli_free_result($res);
        }
    }

    if (!empty($item_ids)) {
        $missing = array_diff_key($item_ids, $json_by_item_id);
        if (!empty($missing)) {
            $inIds = implode(',', array_map('intval', array_values($missing)));
            $res = @mysqli_query(
                $conn,
                'SELECT id, extra_fields_json FROM tbl_purchase_invoice_items
                 WHERE id IN (' . $inIds . ')
                   AND extra_fields_json IS NOT NULL AND TRIM(extra_fields_json) <> \'\'
                 ORDER BY id DESC'
            );
            if ($res) {
                while ($r = mysqli_fetch_assoc($res)) {
                    $iid = (int) ($r['id'] ?? 0);
                    $raw = trim((string) ($r['extra_fields_json'] ?? ''));
                    if ($iid > 0 && $raw !== '' && !isset($json_by_item_id[$iid])) {
                        $json_by_item_id[$iid] = $raw;
                    }
                }
                mysqli_free_result($res);
            }
        }
    }

    foreach ($rows as $idx => $row) {
        $raw = '';
        $bc = trim((string) ($row['barcode'] ?? ''));
        if ($bc !== '' && isset($json_by_barcode[$bc])) {
            $raw = $json_by_barcode[$bc];
        } else {
            $iid = (int) ($row['sj_item_id'] ?? 0);
            if ($iid > 0 && isset($json_by_item_id[$iid])) {
                $raw = $json_by_item_id[$iid];
            }
        }

        $values = [];
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $fid => $val) {
                    if ($val === null || $val === '') {
                        continue;
                    }
                    $values[(string) $fid] = (string) $val;
                }
            }
        }

        $rows[$idx]['extra_field_values'] = $values;
    }
}

function auragold_gold_silver_export_tab_period_line(string $tab): string
{
    $tab = strtolower(trim($tab));
    if ($tab === 'silver') {
        return 'Silver stock only — up to 5,000 barcode lines';
    }
    if ($tab === 'all') {
        return 'Gold & Silver — up to 5,000 barcode lines';
    }
    return 'Gold stock only — up to 5,000 barcode lines';
}

function auragold_gold_silver_export_file_slug(string $tab): string
{
    $tab = strtolower(trim($tab));
    if ($tab === 'silver') {
        return 'Silver';
    }
    if ($tab === 'all') {
        return 'Gold_Silver_All';
    }
    return 'Gold';
}

/**
 * Display metrics aligned with gold-and-silver.php table body.
 *
 * @return array{wt: mixed, gw: mixed, nw: mixed, pw: mixed, carat: string, active_disp: string, barcoded: string}
 */
function auragold_gold_silver_export_compute_metrics(array $r): array
{
    $gw = $r['sj_gross_weight'];
    if ($gw === null || $gw === '') {
        $gw = $r['opening_weight'] ?? null;
    }
    $nw = $r['sj_net_weight'] ?? null;
    $wt = $r['current_weight'];
    if ($wt === null || (float) $wt <= 0) {
        $wt = $r['final_weight'] ?? null;
    }
    $pw = $r['sj_purity_weight'];
    if ($pw === null || $pw === '') {
        $pw = $r['sj_pure_weight'] ?? null;
    }
    $nw_for_purity = $nw;
    if ($nw_for_purity === null || $nw_for_purity === '' || (float) $nw_for_purity <= 0) {
        $nw_for_purity = $wt;
    }
    $voucher_disp = isset($r['voucher_type']) ? trim((string) $r['voucher_type']) : '';
    $op_raw = $r['opening_purity'] ?? null;
    if ($voucher_disp === 'product_opening' && $op_raw !== null && $op_raw !== '' && is_numeric($op_raw) && is_numeric($nw_for_purity) && (float) $nw_for_purity > 0) {
        $opc = (float) $op_raw;
        $p_eff = ($opc > 1) ? ($opc / 100.0) : $opc;
        if ($p_eff > 0 && $p_eff <= 1.001) {
            $pw_exp = (float) $nw_for_purity * $p_eff;
            if ($pw_exp > 0.0001) {
                $pw_f = ($pw !== null && $pw !== '' && is_numeric($pw)) ? (float) $pw : -1.0;
                if ($pw === null || $pw === '' || $pw_exp > $pw_f * 1.5 + 0.0001) {
                    $pw = $pw_exp;
                }
            }
        }
    }
    $carat = $r['pc_carat'] ?? '';
    if ($carat === null || $carat === '') {
        $carat = $r['sj_karat'] ?? '';
    }
    $sjst = isset($r['sj_status']) ? trim((string) $r['sj_status']) : '';
    $sst = isset($r['stock_status']) ? (int) $r['stock_status'] : 0;
    $active_disp = ($sst === 1 ? '1' : '0');
    if ($sjst !== '') {
        $active_disp .= ' / ' . $sjst;
    }
    $barcoded = $r['sj_created_at'] ?? $r['stock_created_at'] ?? '';
    if ($barcoded !== '' && $barcoded !== null) {
        $barcoded = substr((string) $barcoded, 0, 19);
    }

    return [
        'wt' => $wt,
        'gw' => $gw,
        'nw' => $nw,
        'pw' => $pw,
        'carat' => (string) $carat,
        'active_disp' => $active_disp,
        'barcoded' => (string) $barcoded,
    ];
}

/**
 * One flat row for Excel/PDF (29 columns).
 *
 * @return array<int, string|float|int>
 */
function auragold_gold_silver_export_flat_row(array $r): array
{
    $m = auragold_gold_silver_export_compute_metrics($r);

    $fnum = static function ($v, $dec = 3) {
        if ($v === null || $v === '' || !is_numeric($v)) {
            return '';
        }
        return round((float) $v, (int) $dec);
    };
    $fmoney = static function ($v) {
        if ($v === null || $v === '' || !is_numeric($v)) {
            return '';
        }
        return round((float) $v, 2);
    };

    return [
        trim((string) ($r['metal_name'] ?? '')),
        trim((string) ($r['branch_name'] ?? '')),
        trim((string) ($r['barcode'] ?? '')),
        trim((string) ($r['product_name'] ?? '')),
        trim((string) ($r['article'] ?? '')),
        trim((string) ($r['category_display'] ?? '')),
        trim((string) ($r['sj_location'] ?? '')),
        trim((string) ($r['huid_no'] ?? '')),
        $fnum($m['wt'], 3),
        $fnum($m['gw'], 3),
        $fnum($m['pw'], 3),
        $fnum($m['nw'], 3),
        $fnum($r['current_qty'] ?? null, 2),
        trim($m['carat']),
        $fnum($r['stone_weight'] ?? null, 3),
        $fnum($r['wastage_wt'] ?? null, 3),
        $fnum($r['wastage_per'] ?? null, 2),
        $m['active_disp'],
        trim((string) ($r['voucher_type'] ?? '')),
        trim((string) ($r['invoice_no'] ?? '')),
        trim((string) ($r['supplier_name'] ?? '')),
        $m['barcoded'],
        $fmoney($r['metal_cost'] ?? null),
        $fmoney($r['making_cost'] ?? null),
        $fmoney($r['stone_cost'] ?? null),
        $fmoney($r['purchase_amount'] ?? null),
        $fmoney($r['making_amount'] ?? null),
        $fmoney($r['metal_value'] ?? null),
        $fmoney($r['stone_amount'] ?? null),
    ];
}

/** @return array<int, string> */
function auragold_gold_silver_export_headers(): array
{
    return [
        'Metal', 'Branch', 'Barcode', 'Product', 'Article', 'Category', 'Location', 'HUID No.',
        'Weight', 'Gross Wt', 'Purity Wt', 'Net Wt', 'Qty', 'Carat', 'Stone Wt', 'Wastage Wt', 'Wastage %',
        'Active', 'Voucher Type', 'Invoice No.', 'Supplier', 'Barcoded',
        'Metal Cost', 'Making Cost', 'Stone Cost', 'Purchase Amt', 'Making Chg Amt', 'Metal Value', 'Stone Amt',
    ];
}
