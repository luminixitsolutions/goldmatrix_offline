<?php
/**
 * Shared queries for role / segment dashboards (retailer, wholesaler, manufacturing, etc.).
 * Uses tbl_customer_types.code (CUSTOMER, WHOLESALER, JOB_WORKER, …).
 */

if (!function_exists('auragold_dashboard_normalize_fy_date')) {
    function auragold_dashboard_normalize_fy_date($raw): string {
        $d = trim((string) $raw);
        if ($d !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            return $d;
        }

        return '';
    }

    /**
     * Filter a date column to the logged-in financial year (session), when set.
     */
    function auragold_dashboard_fy_date_sql(string $alias, string $dateColumn): string {
        if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['financial_year']) || !is_array($_SESSION['financial_year'])) {
            return '';
        }
        $start = auragold_dashboard_normalize_fy_date($_SESSION['financial_year']['start_date'] ?? '');
        $end   = auragold_dashboard_normalize_fy_date($_SESSION['financial_year']['end_date'] ?? '');
        if ($start === '' || $end === '') {
            return '';
        }
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
        $c = preg_replace('/[^a-zA-Z0-9_]/', '', $dateColumn);
        if ($a === '' || $c === '') {
            return '';
        }

        return " AND {$a}.{$c} >= '" . $start . "' AND {$a}.{$c} <= '" . $end . "' ";
    }

    function auragold_dashboard_mysqli(): ?mysqli {
        global $conn;
        $dbc = $conn ?? $GLOBALS['conn'] ?? null;

        return ($dbc instanceof mysqli) ? $dbc : null;
    }

    /** Branch + FY on tbl_sale_invoices (alias si). */
    function auragold_dashboard_si_extra_sql(string $alias = 'si'): string {
        $dbc = auragold_dashboard_mysqli();
        if (!$dbc) {
            return '';
        }
        $br = function_exists('auragold_sale_invoices_branch_where_sql') ? auragold_sale_invoices_branch_where_sql($dbc, $alias) : '';

        return $br . auragold_dashboard_fy_date_sql($alias, 'invoice_date');
    }

    /**
     * Branch + invoice_date range intersected with logged-in FY (when set). Use when the page already applies a date range (e.g. salesperson period).
     *
     * @param string $rangeStart Y-m-d
     * @param string $rangeEnd   Y-m-d
     */
    function auragold_dashboard_si_scope_for_range_sql(string $alias, string $rangeStart, string $rangeEnd): string {
        $dbc = auragold_dashboard_mysqli();
        if (!$dbc) {
            return '';
        }
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
        if ($a === '') {
            $a = 'si';
        }
        $br = function_exists('auragold_sale_invoices_branch_where_sql') ? auragold_sale_invoices_branch_where_sql($dbc, $a) : '';
        $rs = auragold_dashboard_normalize_fy_date($rangeStart);
        $re = auragold_dashboard_normalize_fy_date($rangeEnd);
        if ($rs === '' || $re === '') {
            return $br;
        }
        if ($rs > $re) {
            return $br . ' AND 1=0 ';
        }
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['financial_year']) && is_array($_SESSION['financial_year'])) {
            $fs = auragold_dashboard_normalize_fy_date($_SESSION['financial_year']['start_date'] ?? '');
            $fe = auragold_dashboard_normalize_fy_date($_SESSION['financial_year']['end_date'] ?? '');
            if ($fs !== '' && $fe !== '') {
                if ($re < $fs || $rs > $fe) {
                    return $br . ' AND 1=0 ';
                }
                $rs = max($rs, $fs);
                $re = min($re, $fe);
            }
        }

        return $br . " AND {$a}.invoice_date >= '" . $rs . "' AND {$a}.invoice_date <= '" . $re . "' ";
    }

    function auragold_dashboard_pi_extra_sql(string $alias = 'pi'): string {
        $dbc = auragold_dashboard_mysqli();
        if (!$dbc) {
            return '';
        }
        $br = function_exists('auragold_sql_and_branch_scope') ? auragold_sql_and_branch_scope($dbc, 'tbl_purchase_invoices', $alias) : '';

        return $br . auragold_dashboard_fy_date_sql($alias, 'invoice_date');
    }

    function auragold_dashboard_so_extra_sql(string $alias = 'so'): string {
        $dbc = auragold_dashboard_mysqli();
        if (!$dbc) {
            return '';
        }
        $br = function_exists('auragold_sql_and_branch_scope') ? auragold_sql_and_branch_scope($dbc, 'tbl_sale_orders', $alias) : '';

        return $br . auragold_dashboard_fy_date_sql($alias, 'order_date');
    }

    function auragold_dashboard_jwo_extra_sql(string $alias = 'j'): string {
        $dbc = auragold_dashboard_mysqli();
        if (!$dbc) {
            return '';
        }
        $br = function_exists('auragold_sql_and_branch_scope') ? auragold_sql_and_branch_scope($dbc, 'tbl_jobwork_orders', $alias) : '';

        return $br . auragold_dashboard_fy_date_sql($alias, 'order_date');
    }

    /** Branch filter for tbl_stock snapshot queries (no FY). */
    function auragold_dashboard_stock_branch_sql(string $alias = 's'): string {
        $dbc = auragold_dashboard_mysqli();
        if (!$dbc) {
            return '';
        }

        return function_exists('auragold_sql_and_branch_scope') ? auragold_sql_and_branch_scope($dbc, 'tbl_stock', $alias) : '';
    }

    /** Inward stock types used for net on-hand calculations. */
    function auragold_dashboard_stock_in_types_sql(): string {
        return "'opening','purchase','stock_journal','balance','inward','sale_return'";
    }

    function auragold_dashboard_stock_move_weight_expr(string $alias = 's'): string {
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
        if ($a === '') {
            $a = 's';
        }

        return "COALESCE(NULLIF({$a}.opening_weight,0), {$a}.current_weight, 0)";
    }

    function auragold_dashboard_stock_move_qty_expr(string $alias = 's'): string {
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
        if ($a === '') {
            $a = 's';
        }

        return "COALESCE(NULLIF({$a}.opening_qty,0), {$a}.current_qty, 0)";
    }

    function auragold_dashboard_stock_net_weight_sum_sql(string $alias = 's'): string {
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
        if ($a === '') {
            $a = 's';
        }
        $in = auragold_dashboard_stock_in_types_sql();
        $wt = auragold_dashboard_stock_move_weight_expr($a);

        return "COALESCE(SUM(CASE WHEN {$a}.stock_type IN ($in) THEN $wt WHEN {$a}.stock_type = 'outward' THEN -ABS($wt) ELSE 0 END),0)";
    }

    function auragold_dashboard_stock_net_qty_sum_sql(string $alias = 's'): string {
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
        if ($a === '') {
            $a = 's';
        }
        $in = auragold_dashboard_stock_in_types_sql();
        $qty = auragold_dashboard_stock_move_qty_expr($a);

        return "COALESCE(SUM(CASE WHEN {$a}.stock_type IN ($in) THEN $qty WHEN {$a}.stock_type = 'outward' THEN -ABS($qty) ELSE 0 END),0)";
    }

    function auragold_dashboard_stock_tx_date_sql(string $alias, string $rangeStart, string $rangeEnd): string {
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
        if ($a === '') {
            $a = 's';
        }
        $rs = auragold_dashboard_normalize_fy_date($rangeStart);
        $re = auragold_dashboard_normalize_fy_date($rangeEnd);
        if ($rs === '' || $re === '') {
            return '';
        }

        return " AND DATE(COALESCE(NULLIF({$a}.transaction_date,'0000-00-00'), {$a}.created_at)) >= '" . $rs . "'"
            . " AND DATE(COALESCE(NULLIF({$a}.transaction_date,'0000-00-00'), {$a}.created_at)) <= '" . $re . "' ";
    }

    function auragold_dashboard_sj_extra_sql(string $alias = 'sj'): string {
        $dbc = auragold_dashboard_mysqli();
        if (!$dbc) {
            return '';
        }

        return function_exists('auragold_sql_and_branch_scope') ? auragold_sql_and_branch_scope($dbc, 'tbl_stock_journal', $alias) : '';
    }
}

if (!function_exists('auragold_dashboard_sale_status_where')) {
    /**
     * Exclude cancelled/void sale invoices from aggregates.
     */
    function auragold_dashboard_sale_status_condition($siAlias = 'si') {
        return "({$siAlias}.status IS NULL OR LOWER(TRIM({$siAlias}.status)) NOT IN ('cancelled','void','canceled'))";
    }

    function auragold_dashboard_sale_status_where($siAlias = 'si') {
        return ' AND ' . auragold_dashboard_sale_status_condition($siAlias) . ' ';
    }

    /**
     * Resolve customer type id by code (case-insensitive), e.g. CUSTOMER, WHOLESALER, JOB_WORKER.
     *
     * @return int 0 if not found
     */
    function auragold_customer_type_id_by_code($code) {
        $code = trim((string) $code);
        if ($code === '' || !function_exists('getRecord')) {
            return 0;
        }
        global $conn;
        $dbc = $conn ?? $GLOBALS['conn'] ?? null;
        if (!$dbc) {
            return 0;
        }
        $esc = mysqli_real_escape_string($dbc, $code);
        $r = getRecord(
            "SELECT id FROM tbl_customer_types WHERE status = 1 AND LOWER(TRIM(code)) = LOWER('$esc') LIMIT 1"
        );
        return $r && isset($r['id']) ? (int) $r['id'] : 0;
    }

    /**
     * @param int $customerTypeId
     * @return array{
     *   customer_count:int,
     *   invoice_count:int,
     *   sale_total:float,
     *   customers_with_sales:int
     * }
     */
    function auragold_dashboard_segment_summary($customerTypeId) {
        $out = [
            'customer_count' => 0,
            'invoice_count' => 0,
            'sale_total' => 0.0,
            'customers_with_sales' => 0,
        ];
        $tid = (int) $customerTypeId;
        if ($tid <= 0 || !function_exists('getRecord')) {
            return $out;
        }
        $st  = auragold_dashboard_sale_status_where('si');
        $siX = function_exists('auragold_dashboard_si_extra_sql') ? auragold_dashboard_si_extra_sql('si') : '';
        $r1  = getRecord(
            "SELECT COUNT(DISTINCT c.id) AS c
             FROM tbl_customers c
             INNER JOIN tbl_sale_invoices si ON si.customer_id = c.id
             WHERE c.status = 1 AND c.customer_type_id = $tid
             $st $siX"
        );
        if ($r1) {
            $out['customer_count'] = (int) ($r1['c'] ?? 0);
        }
        $r2 = getRecord(
            "SELECT COUNT(DISTINCT si.customer_id) AS c,
                    COUNT(DISTINCT si.id) AS inv,
                    COALESCE(SUM(si.grand_total),0) AS gtot
             FROM tbl_sale_invoices si
             INNER JOIN tbl_customers c ON si.customer_id = c.id
             WHERE c.customer_type_id = $tid
             $st $siX"
        );
        if ($r2) {
            $out['customers_with_sales'] = (int) ($r2['c'] ?? 0);
            $out['invoice_count'] = (int) ($r2['inv'] ?? 0);
            $out['sale_total'] = (float) ($r2['gtot'] ?? 0);
        }
        return $out;
    }

    /**
     * Recent sale invoices for customers of a given type.
     *
     * @return list<array<string,mixed>>
     */
    function auragold_dashboard_segment_recent_invoices($customerTypeId, $limit = 15) {
        $tid = (int) $customerTypeId;
        $lim = max(1, min(100, (int) $limit));
        if ($tid <= 0 || !function_exists('getList')) {
            return [];
        }
        $st  = auragold_dashboard_sale_status_where('si');
        $siX = function_exists('auragold_dashboard_si_extra_sql') ? auragold_dashboard_si_extra_sql('si') : '';
        return getList(
            "SELECT si.id, si.invoice_no, si.invoice_date, si.customer_name, si.grand_total,
                    si.sales_person, si.status, c.customer_type_id
             FROM tbl_sale_invoices si
             INNER JOIN tbl_customers c ON si.customer_id = c.id
             WHERE c.customer_type_id = $tid
             $st $siX
             ORDER BY si.invoice_date DESC, si.id DESC
             LIMIT $lim"
        ) ?: [];
    }

    /**
     * Top customers by sale total for a customer type.
     *
     * @return list<array<string,mixed>>
     */
    function auragold_dashboard_segment_top_customers($customerTypeId, $limit = 10) {
        $tid = (int) $customerTypeId;
        $lim = max(1, min(50, (int) $limit));
        if ($tid <= 0 || !function_exists('getList')) {
            return [];
        }
        $cond = auragold_dashboard_sale_status_condition('si');
        $siX  = function_exists('auragold_dashboard_si_extra_sql') ? auragold_dashboard_si_extra_sql('si') : '';
        return getList(
            "SELECT c.id, c.name,
                    (SELECT COUNT(*) FROM tbl_sale_invoices si
                     WHERE si.customer_id = c.id AND $cond $siX) AS inv_count,
                    COALESCE((SELECT SUM(si.grand_total) FROM tbl_sale_invoices si
                     WHERE si.customer_id = c.id AND $cond $siX),0) AS sale_total
             FROM tbl_customers c
             WHERE c.status = 1 AND c.customer_type_id = $tid
             ORDER BY sale_total DESC
             LIMIT $lim"
        ) ?: [];
    }

    /**
     * Sales grouped by sales_person (tbl_sale_invoices.sales_person).
     *
     * @return list<array<string,mixed>>
     */
    function auragold_dashboard_sales_by_salesperson($limit = 25) {
        $lim = max(1, min(100, (int) $limit));
        if (!function_exists('getList')) {
            return [];
        }
        $st  = auragold_dashboard_sale_status_where('si');
        $siX = function_exists('auragold_dashboard_si_extra_sql') ? auragold_dashboard_si_extra_sql('si') : '';
        return getList(
            "SELECT TRIM(si.sales_person) AS sp,
                    COUNT(si.id) AS inv_count,
                    COALESCE(SUM(si.grand_total),0) AS sale_total
             FROM tbl_sale_invoices si
             WHERE si.sales_person IS NOT NULL AND TRIM(si.sales_person) <> ''
             $st $siX
             GROUP BY TRIM(si.sales_person)
             ORDER BY sale_total DESC
             LIMIT $lim"
        ) ?: [];
    }

    /**
     * Recent invoices that have a sales person set.
     *
     * @return list<array<string,mixed>>
     */
    function auragold_dashboard_recent_invoices_with_salesperson($limit = 20) {
        $lim = max(1, min(100, (int) $limit));
        if (!function_exists('getList')) {
            return [];
        }
        $st  = auragold_dashboard_sale_status_where('si');
        $siX = function_exists('auragold_dashboard_si_extra_sql') ? auragold_dashboard_si_extra_sql('si') : '';
        return getList(
            "SELECT si.invoice_no, si.invoice_date, si.customer_name, si.grand_total,
                    TRIM(si.sales_person) AS sales_person, si.status
             FROM tbl_sale_invoices si
             WHERE si.sales_person IS NOT NULL AND TRIM(si.sales_person) <> ''
             $st $siX
             ORDER BY si.invoice_date DESC, si.id DESC
             LIMIT $lim"
        ) ?: [];
    }

    /**
     * Gold jewellery lines: latest / average metal_rate by carat (from sale invoice lines, Gold metal).
     *
     * @return list<array<string,mixed>>
     */
    function auragold_dashboard_gold_metal_rates_from_sales($limitCarats = 20) {
        $lim = max(1, min(50, (int) $limitCarats));
        if (!function_exists('getList')) {
            return [];
        }
        $st  = auragold_dashboard_sale_status_where('si');
        $siX = function_exists('auragold_dashboard_si_extra_sql') ? auragold_dashboard_si_extra_sql('si') : '';
        return getList(
            "SELECT TRIM(sii.carat) AS carat,
                    AVG(sii.metal_rate) AS avg_metal_rate,
                    MAX(sii.metal_rate) AS max_metal_rate,
                    MIN(sii.metal_rate) AS min_metal_rate,
                    MAX(si.invoice_date) AS last_invoice_date,
                    COUNT(*) AS line_count
             FROM tbl_sale_invoice_items sii
             INNER JOIN tbl_sale_invoices si ON sii.invoice_id = si.id
             LEFT JOIN tbl_product_characteristics pc ON sii.product_characteristic_id = pc.id
             LEFT JOIN tbl_metal m ON m.id = pc.metal_id
             WHERE sii.status = 1
             AND sii.metal_rate IS NOT NULL AND sii.metal_rate > 0
             AND (pc.metal_id = 1 OR LOWER(COALESCE(m.display_name, '')) LIKE '%gold%' OR LOWER(TRIM(COALESCE(sii.carat, ''))) REGEXP '^[0-9]+')
             $st $siX
             GROUP BY TRIM(sii.carat)
             HAVING carat IS NOT NULL AND TRIM(carat) <> ''
             ORDER BY last_invoice_date DESC
             LIMIT $lim"
        ) ?: [];
    }

    /**
     * Carat master rows (reference).
     *
     * @return list<array<string,mixed>>
     */
    function auragold_dashboard_carat_master() {
        if (!function_exists('getList')) {
            return [];
        }
        return getList(
            "SELECT id, name, purity, description FROM tbl_carat WHERE status = 1 ORDER BY name ASC"
        ) ?: [];
    }

    /**
     * Stock overview: by metal and branch.
     *
     * @return array{by_metal:list,by_branch:list,totals:array}
     */
    function auragold_dashboard_stock_overview() {
        $empty = ['by_metal' => [], 'by_branch' => [], 'totals' => []];
        if (!function_exists('getList') || !function_exists('getRecord')) {
            return $empty;
        }
        $stkBr = function_exists('auragold_dashboard_stock_branch_sql') ? auragold_dashboard_stock_branch_sql('s') : '';
        $byMetal = getList(
            "SELECT m.id, m.display_name AS metal_name,
                    COUNT(s.id) AS row_count,
                    " . auragold_dashboard_stock_net_weight_sum_sql('s') . " AS sum_current_weight,
                    " . auragold_dashboard_stock_net_qty_sum_sql('s') . " AS sum_current_qty,
                    COALESCE(SUM(CASE WHEN s.stock_type IN (" . auragold_dashboard_stock_in_types_sql() . ") THEN s.value WHEN s.stock_type = 'outward' THEN -ABS(s.value) ELSE 0 END),0) AS sum_value
             FROM tbl_stock s
             LEFT JOIN tbl_metal m ON s.metal_id = m.id
             WHERE s.status = 1 $stkBr
             GROUP BY m.id, m.display_name
             HAVING sum_current_weight > 0 OR sum_current_qty > 0 OR row_count > 0
             ORDER BY m.display_name ASC"
        ) ?: [];

        $byBranch = getList(
            "SELECT b.id, b.name AS branch_name,
                    COUNT(s.id) AS row_count,
                    " . auragold_dashboard_stock_net_weight_sum_sql('s') . " AS sum_current_weight,
                    COALESCE(SUM(CASE WHEN s.stock_type IN (" . auragold_dashboard_stock_in_types_sql() . ") THEN s.value WHEN s.stock_type = 'outward' THEN -ABS(s.value) ELSE 0 END),0) AS sum_value
             FROM tbl_stock s
             LEFT JOIN tbl_branches b ON s.branch_id = b.id
             WHERE s.status = 1 $stkBr
             GROUP BY b.id, b.name
             HAVING sum_current_weight > 0 OR row_count > 0
             ORDER BY b.name ASC"
        ) ?: [];

        $tot = getRecord(
            "SELECT COUNT(*) AS rows_n,
                    " . auragold_dashboard_stock_net_weight_sum_sql('s') . " AS w,
                    " . auragold_dashboard_stock_net_qty_sum_sql('s') . " AS q,
                    COALESCE(SUM(CASE WHEN s.stock_type IN (" . auragold_dashboard_stock_in_types_sql() . ") THEN s.value WHEN s.stock_type = 'outward' THEN -ABS(s.value) ELSE 0 END),0) AS v
             FROM tbl_stock s WHERE s.status = 1 $stkBr"
        );

        return [
            'by_metal' => $byMetal,
            'by_branch' => $byBranch,
            'totals' => [
                'rows' => $tot ? (int) ($tot['rows_n'] ?? 0) : 0,
                'weight' => $tot ? (float) ($tot['w'] ?? 0) : 0,
                'qty' => $tot ? (float) ($tot['q'] ?? 0) : 0,
                'value' => $tot ? (float) ($tot['v'] ?? 0) : 0,
            ],
        ];
    }

    /**
     * JewelSteps-style stock dashboard: KPI grid, metal chart series, karat bars, low stock list.
     *
     * @return array<string,mixed>
     */
    function auragold_stock_dashboard_jewelsteps() {
        $base = auragold_dashboard_stock_overview();
        $out = array_merge($base, [
            'kpi' => [
                'total_products' => 0,
                'total_products_qty' => 0.0,
                'zero_stock_lines' => 0,
                'zero_stock_qty' => 0.0,
                'inward_weight' => 0.0,
                'inward_qty' => 0.0,
                'outward_weight' => 0.0,
                'outward_qty' => 0.0,
                'metals' => [],
            ],
            'metal_chart' => [],
            'metal_chart_branchwise' => [
                'branch_labels' => [],
                'datasets' => [],
                'table_rows' => [],
            ],
            'karatwise' => [],
            'low_stock' => [],
        ]);
        if (!function_exists('getRecord') || !function_exists('getList')) {
            return $out;
        }

        global $conn;
        $dbc = $conn ?? $GLOBALS['conn'] ?? null;

        $stkBr = function_exists('auragold_dashboard_stock_branch_sql') ? auragold_dashboard_stock_branch_sql('s') : '';

        $rProd = getRecord(
            'SELECT COUNT(DISTINCT s.product_id) AS c FROM tbl_stock s WHERE s.status = 1' . $stkBr
        );
        $out['kpi']['total_products'] = $rProd ? (int) ($rProd['c'] ?? 0) : 0;
        $out['kpi']['total_products_qty'] = (float) ($out['totals']['qty'] ?? 0);

        $rZero = getRecord(
            "SELECT COUNT(*) AS c, COALESCE(SUM(sub.q),0) AS q FROM (
                SELECT s.product_id, s.branch_id, s.metal_id,
                       " . auragold_dashboard_stock_net_weight_sum_sql('s') . " AS w,
                       " . auragold_dashboard_stock_net_qty_sum_sql('s') . " AS q
                FROM tbl_stock s
                WHERE s.status = 1 $stkBr
                GROUP BY s.product_id, s.branch_id, s.metal_id
                HAVING w <= 0 AND q <= 0
             ) sub"
        );
        if ($rZero) {
            $out['kpi']['zero_stock_lines'] = (int) ($rZero['c'] ?? 0);
            $out['kpi']['zero_stock_qty'] = (float) ($rZero['q'] ?? 0);
        }

        $out['kpi']['inward_weight'] = 0.0;
        $out['kpi']['inward_qty'] = 0.0;
        $out['kpi']['outward_weight'] = 0.0;
        $out['kpi']['outward_qty'] = 0.0;

        $mMetalWhere = '';
        if ($dbc instanceof mysqli && function_exists('auragold_tbl_has_column') && function_exists('auragold_master_list_sql_suffix')
            && auragold_tbl_has_column($dbc, 'tbl_metal', 'branch_id')) {
            $mMetalWhere = auragold_master_list_sql_suffix($dbc, 'tbl_metal', 'm.branch_id');
        }
        $metals = getList(
            "SELECT m.id, m.display_name AS name,
                    " . auragold_dashboard_stock_net_weight_sum_sql('s') . " AS w,
                    " . auragold_dashboard_stock_net_qty_sum_sql('s') . " AS q
             FROM tbl_metal m
             LEFT JOIN tbl_stock s ON s.metal_id = m.id AND s.status = 1 $stkBr
             WHERE m.status = 1 $mMetalWhere
             GROUP BY m.id, m.display_name
             ORDER BY m.id ASC"
        ) ?: [];
        $out['kpi']['metals'] = $metals;

        $chart = [];
        foreach ($metals as $m) {
            $chart[] = [
                'label' => (string) ($m['name'] ?? ''),
                'weight' => round((float) ($m['w'] ?? 0), 3),
            ];
        }
        $out['metal_chart'] = $chart;

        // Branch × metal master: show every active tbl_metal row for each branch (zeros when no stock).
        $stkBrS2 = function_exists('auragold_dashboard_stock_branch_sql') ? auragold_dashboard_stock_branch_sql('s2') : '';
        $hasMetalBranchCol = $dbc instanceof mysqli && function_exists('auragold_tbl_has_column')
            && auragold_tbl_has_column($dbc, 'tbl_metal', 'branch_id');
        $metalJoinOn = $hasMetalBranchCol
            ? 'm.status = 1 AND (m.branch_id = b.id OR m.branch_id IS NULL OR m.branch_id = 0)'
            : 'm.status = 1';
        $bwRows = getList(
            "SELECT b.id AS branch_id,
                    COALESCE(b.name, '—') AS branch_name,
                    m.id AS metal_id,
                    COALESCE(NULLIF(TRIM(m.display_name), ''), 'Unknown') AS metal_display,
                    COALESCE(SUM(s.current_weight), 0) AS w
             FROM tbl_branches b
             INNER JOIN tbl_metal m ON $metalJoinOn
             LEFT JOIN tbl_stock s
                ON s.branch_id = b.id
                AND s.metal_id = m.id
                AND s.status = 1
                $stkBr
             WHERE b.status = 1
               AND b.id IN (
                   SELECT DISTINCT s2.branch_id
                   FROM tbl_stock s2
                   WHERE s2.status = 1
                     AND s2.branch_id IS NOT NULL
                     $stkBrS2
               )
             GROUP BY b.id, b.name, m.id, m.display_name
             ORDER BY branch_name ASC, metal_display ASC, m.id ASC"
        ) ?: [];

        $branchOrder = [];
        $branchSeen = [];
        foreach ($bwRows as $br) {
            $bid = (int) ($br['branch_id'] ?? 0);
            if (!isset($branchSeen[$bid])) {
                $branchSeen[$bid] = true;
                $branchOrder[] = [
                    'id' => $bid,
                    'name' => (string) ($br['branch_name'] ?? '—'),
                ];
            }
        }
        $metalNames = [];
        foreach ($bwRows as $br) {
            $metalNames[(string) ($br['metal_display'] ?? 'Unknown')] = true;
        }
        $metalLabels = array_keys($metalNames);
        sort($metalLabels, SORT_NATURAL | SORT_FLAG_CASE);

        $stacked = [];
        foreach ($metalLabels as $ml) {
            $stacked[$ml] = array_fill(0, count($branchOrder), 0.0);
        }
        foreach ($bwRows as $br) {
            $bid = (int) ($br['branch_id'] ?? 0);
            $idx = null;
            foreach ($branchOrder as $i => $bo) {
                if ((int) ($bo['id'] ?? 0) === $bid) {
                    $idx = $i;
                    break;
                }
            }
            if ($idx === null) {
                continue;
            }
            $mn = (string) ($br['metal_display'] ?? 'Unknown');
            if (!isset($stacked[$mn])) {
                continue;
            }
            $stacked[$mn][$idx] += round((float) ($br['w'] ?? 0), 3);
        }

        $palette = ['#eab308', '#38bdf8', '#a78bfa', '#94a3b8', '#f472b6', '#c084fc', '#34d399', '#fb923c', '#f87171', '#818cf8'];
        $bwDatasets = [];
        foreach ($metalLabels as $mi => $ml) {
            $bwDatasets[] = [
                'label' => $ml,
                'data' => array_values($stacked[$ml]),
                'backgroundColor' => $palette[$mi % count($palette)],
            ];
        }
        $tableRows = [];
        foreach ($bwRows as $br) {
            $tableRows[] = [
                'branch_name' => (string) ($br['branch_name'] ?? '—'),
                'metal_display' => (string) ($br['metal_display'] ?? 'Unknown'),
                'weight' => round((float) ($br['w'] ?? 0), 3),
            ];
        }
        $out['metal_chart_branchwise'] = [
            'branch_labels' => array_map(static function ($b) {
                return (string) ($b['name'] ?? '—');
            }, $branchOrder),
            'datasets' => $bwDatasets,
            'table_rows' => $tableRows,
        ];

        $karatRows = getList(
            "SELECT
                TRIM(CAST(pc.carat AS CHAR)) AS carat_label,
                COALESCE(SUM(s.current_weight),0) AS w,
                COALESCE(SUM(s.current_qty),0) AS q
             FROM tbl_stock s
             INNER JOIN tbl_product_characteristics pc ON s.product_characteristic_id = pc.id
             WHERE s.status = 1 AND s.metal_id = 1 $stkBr
             GROUP BY TRIM(CAST(pc.carat AS CHAR))
             HAVING carat_label IS NOT NULL AND carat_label <> ''
             ORDER BY carat_label ASC"
        ) ?: [];
        foreach ($karatRows as &$kr) {
            $lab = (string) ($kr['carat_label'] ?? '');
            $kr['title'] = $lab !== '' ? $lab . ' (Gold)' : '—';
            $kr['weight'] = (float) ($kr['w'] ?? 0);
            $kr['qty'] = (float) ($kr['q'] ?? 0);
        }
        unset($kr);
        $out['karatwise'] = $karatRows;

        $hasImg = false;
        if ($dbc) {
            $imgCol = @mysqli_query($dbc, "SHOW COLUMNS FROM tbl_product_characteristics LIKE 'images'");
            if ($imgCol && mysqli_num_rows($imgCol) > 0) {
                $hasImg = true;
                mysqli_free_result($imgCol);
            } elseif ($imgCol) {
                mysqli_free_result($imgCol);
            }
        }
        // One row per branch + product. Weight/Qty = totals for that item at that branch (all lines).
        // Low-line count = how many lines match the threshold (qty ≤ 1 or weight ≤ 0); those lines can be 0/0 while other lines hold stock.
        if ($hasImg) {
            $low = getList(
                "SELECT p.id AS product_id,
                        p.name AS product_name,
                        s.branch_id,
                        COALESCE(MAX(b.name), '—') AS branch_name,
                        COUNT(s.id) AS low_line_count,
                        COALESCE(MAX(st.total_weight), 0) AS total_weight,
                        COALESCE(MAX(st.total_qty), 0) AS total_qty,
                        MAX(pc.images) AS images
                 FROM tbl_stock s
                 INNER JOIN tbl_products p ON s.product_id = p.id
                 LEFT JOIN tbl_branches b ON s.branch_id = b.id
                 LEFT JOIN tbl_product_characteristics pc ON s.product_characteristic_id = pc.id
                 LEFT JOIN (
                     SELECT s2.product_id,
                            s2.branch_id,
                            COALESCE(SUM(s2.current_weight), 0) AS total_weight,
                            COALESCE(SUM(s2.current_qty), 0) AS total_qty
                     FROM tbl_stock s2
                     WHERE s2.status = 1 $stkBrS2
                     GROUP BY s2.product_id, s2.branch_id
                 ) st ON st.product_id = p.id AND (st.branch_id <=> s.branch_id)
                 WHERE s.status = 1 $stkBr
                 AND (COALESCE(s.current_qty,0) <= 1 OR COALESCE(s.current_weight,0) <= 0)
                 GROUP BY s.branch_id, p.id, p.name
                 ORDER BY COALESCE(MAX(st.total_qty), 0) ASC, COALESCE(MAX(st.total_weight), 0) ASC
                 LIMIT 20"
            ) ?: [];
        } else {
            $low = getList(
                "SELECT p.id AS product_id,
                        p.name AS product_name,
                        s.branch_id,
                        COALESCE(MAX(b.name), '—') AS branch_name,
                        COUNT(s.id) AS low_line_count,
                        COALESCE(MAX(st.total_weight), 0) AS total_weight,
                        COALESCE(MAX(st.total_qty), 0) AS total_qty,
                        NULL AS images
                 FROM tbl_stock s
                 INNER JOIN tbl_products p ON s.product_id = p.id
                 LEFT JOIN tbl_branches b ON s.branch_id = b.id
                 LEFT JOIN tbl_product_characteristics pc ON s.product_characteristic_id = pc.id
                 LEFT JOIN (
                     SELECT s2.product_id,
                            s2.branch_id,
                            COALESCE(SUM(s2.current_weight), 0) AS total_weight,
                            COALESCE(SUM(s2.current_qty), 0) AS total_qty
                     FROM tbl_stock s2
                     WHERE s2.status = 1 $stkBrS2
                     GROUP BY s2.product_id, s2.branch_id
                 ) st ON st.product_id = p.id AND (st.branch_id <=> s.branch_id)
                 WHERE s.status = 1 $stkBr
                 AND (COALESCE(s.current_qty,0) <= 1 OR COALESCE(s.current_weight,0) <= 0)
                 GROUP BY s.branch_id, p.id, p.name
                 ORDER BY COALESCE(MAX(st.total_qty), 0) ASC, COALESCE(MAX(st.total_weight), 0) ASC
                 LIMIT 20"
            ) ?: [];
        }
        $out['low_stock'] = $low;

        return $out;
    }

    /**
     * Extra stock dashboard: branch count, recent journal lines, metal weights.
     *
     * @return array<string,mixed>
     */
    function auragold_stock_dashboard_extras($dateFrom = '', $dateTo = '') {
        $out = [
            'branch_count' => 0,
            'low_stock_count' => 0,
            'recent_journal' => [],
            'gold_weight' => 0.0,
            'silver_weight' => 0.0,
            'inward_weight' => 0.0,
            'inward_qty' => 0.0,
            'outward_weight' => 0.0,
            'outward_qty' => 0.0,
            'date_from' => '',
            'date_to' => '',
            'is_today' => true,
            'is_single_day' => true,
        ];
        if (!function_exists('getRecord') || !function_exists('getList')) {
            return $out;
        }

        $bounds = auragold_retailer_dashboard_date_bounds($dateFrom, $dateTo);
        $rangeStart = $bounds['start'];
        $rangeEnd = $bounds['end'];
        $out['date_from'] = $rangeStart;
        $out['date_to'] = $rangeEnd;
        $out['is_today'] = !empty($bounds['is_today']);
        $out['is_single_day'] = !empty($bounds['is_single_day']);

        $stkBr = function_exists('auragold_dashboard_stock_branch_sql') ? auragold_dashboard_stock_branch_sql('s') : '';

        if (auragold_table_exists('tbl_stock')) {
            $rBr = getRecord(
                "SELECT COUNT(DISTINCT s.branch_id) AS c FROM tbl_stock s
                 WHERE s.status = 1 AND s.branch_id IS NOT NULL $stkBr"
            );
            $out['branch_count'] = $rBr ? (int) ($rBr['c'] ?? 0) : 0;

            $rLow = getRecord(
                "SELECT COUNT(DISTINCT CONCAT(COALESCE(s.branch_id,0), '-', s.product_id)) AS c
                 FROM tbl_stock s
                 WHERE s.status = 1 $stkBr
                 AND (COALESCE(s.current_qty,0) <= 1 OR COALESCE(s.current_weight,0) <= 0)"
            );
            $out['low_stock_count'] = $rLow ? (int) ($rLow['c'] ?? 0) : 0;

            $rGold = getRecord(
                "SELECT " . auragold_dashboard_stock_net_weight_sum_sql('s') . " AS w FROM tbl_stock s
                 WHERE s.status = 1 AND s.metal_id = 1 $stkBr"
            );
            $out['gold_weight'] = $rGold ? (float) ($rGold['w'] ?? 0) : 0.0;

            $rSilver = getRecord(
                "SELECT " . auragold_dashboard_stock_net_weight_sum_sql('s') . " AS w FROM tbl_stock s
                 WHERE s.status = 1 AND s.metal_id = 2 $stkBr"
            );
            $out['silver_weight'] = $rSilver ? (float) ($rSilver['w'] ?? 0) : 0.0;

            $inTypes = auragold_dashboard_stock_in_types_sql();
            $txSql = auragold_dashboard_stock_tx_date_sql('s', $rangeStart, $rangeEnd);
            $wt = auragold_dashboard_stock_move_weight_expr('s');
            $qty = auragold_dashboard_stock_move_qty_expr('s');
            $rIn = getRecord(
                "SELECT COALESCE(SUM($wt),0) AS w, COALESCE(SUM($qty),0) AS q
                 FROM tbl_stock s
                 WHERE s.status = 1 $stkBr AND s.stock_type IN ($inTypes) $txSql"
            );
            if ($rIn) {
                $out['inward_weight'] = (float) ($rIn['w'] ?? 0);
                $out['inward_qty'] = (float) ($rIn['q'] ?? 0);
            }
            $rOut = getRecord(
                "SELECT COALESCE(SUM(ABS($wt)),0) AS w, COALESCE(SUM(ABS($qty)),0) AS q
                 FROM tbl_stock s
                 WHERE s.status = 1 $stkBr AND s.stock_type = 'outward' $txSql"
            );
            if ($rOut) {
                $out['outward_weight'] = (float) ($rOut['w'] ?? 0);
                $out['outward_qty'] = (float) ($rOut['q'] ?? 0);
            }
        }

        if (auragold_table_exists('tbl_stock_journal')) {
            $sjX = function_exists('auragold_dashboard_sj_extra_sql') ? auragold_dashboard_sj_extra_sql('sj') : '';
            $sjDateSql = " AND sj.sj_date >= '" . $rangeStart . "' AND sj.sj_date <= '" . $rangeEnd . "' ";
            $out['recent_journal'] = getList(
                "SELECT sj.id, sj.sj_invoice_no, sj.sj_date, sj.product_name, sj.barcode,
                        sj.gross_weight, sj.metal_type, sj.voucher_type,
                        COALESCE(NULLIF(TRIM(sj.voucher_type), ''), 'Stock') AS type_label
                 FROM tbl_stock_journal sj
                 WHERE sj.status IS NULL OR LOWER(TRIM(sj.status)) NOT IN ('cancelled','void','canceled')
                 $sjX $sjDateSql
                 ORDER BY sj.sj_date DESC, sj.id DESC
                 LIMIT 10"
            ) ?: [];
        }

        return $out;
    }

    /**
     * Human label for customer type code (for page titles).
     */
    function auragold_customer_type_label($code) {
        $map = [
            'CUSTOMER' => 'Retailer (Customer)',
            'WHOLESALER' => 'Wholesaler',
            'JOB_WORKER' => 'Manufacturing / Job worker',
        ];
        $k = strtoupper(trim((string) $code));
        return $map[$k] ?? $k;
    }

    function auragold_dashboard_purchase_status_where($alias = 'pi') {
        return ' AND (' . $alias . '.status IS NULL OR LOWER(TRIM(' . $alias . '.status)) NOT IN (\'cancelled\',\'void\',\'canceled\')) ';
    }

    function auragold_dashboard_order_status_where($alias = 'so') {
        return ' AND (' . $alias . '.status IS NULL OR LOWER(TRIM(' . $alias . '.status)) NOT IN (\'cancelled\',\'void\',\'canceled\')) ';
    }

    /**
     * Latest running balance for a system ledger row (customer_id = 0) in tbl_customer_ledger.
     */
    function auragold_ledger_system_balance($ledgerName) {
        global $conn;
        $dbc = $conn ?? $GLOBALS['conn'] ?? null;
        if (!$dbc || !function_exists('getRecord')) {
            return 0.0;
        }
        $n = mysqli_real_escape_string($dbc, trim((string) $ledgerName));
        if ($n === '') {
            return 0.0;
        }
        $r = getRecord(
            "SELECT balance_amount FROM tbl_customer_ledger
             WHERE customer_id = 0 AND customer_name = '$n'
             ORDER BY id DESC LIMIT 1"
        );
        return $r ? (float) ($r['balance_amount'] ?? 0) : 0.0;
    }

    /**
     * Sum latest system-ledger balances for multiple account names (e.g. Bank + Bank Account).
     */
    function auragold_ledger_system_balance_sum(array $ledgerNames) {
        $sum = 0.0;
        foreach ($ledgerNames as $name) {
            $sum += auragold_ledger_system_balance((string) $name);
        }
        return $sum;
    }

    /**
     * Normalize retailer dashboard date range (defaults both ends to today).
     *
     * @return array{start:string,end:string,is_single_day:bool,is_today:bool}
     */
    function auragold_retailer_dashboard_date_bounds($dateFrom = '', $dateTo = ''): array {
        $today = date('Y-m-d');
        $start = auragold_dashboard_normalize_fy_date($dateFrom);
        $end   = auragold_dashboard_normalize_fy_date($dateTo);
        if ($start === '' && $end === '') {
            $start = $today;
            $end   = $today;
        } elseif ($start === '') {
            $start = $end;
        } elseif ($end === '') {
            $end = $start;
        }
        if ($start > $end) {
            $tmp = $start;
            $start = $end;
            $end = $tmp;
        }
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['financial_year']) && is_array($_SESSION['financial_year'])) {
            $fs = auragold_dashboard_normalize_fy_date($_SESSION['financial_year']['start_date'] ?? '');
            $fe = auragold_dashboard_normalize_fy_date($_SESSION['financial_year']['end_date'] ?? '');
            if ($fs !== '' && $fe !== '') {
                if ($end < $fs || $start > $fe) {
                    $start = $today;
                    $end = $today;
                } else {
                    $start = max($start, $fs);
                    $end = min($end, $fe);
                }
            }
        }

        return [
            'start' => $start,
            'end' => $end,
            'is_single_day' => ($start === $end),
            'is_today' => ($start === $today && $end === $today),
        ];
    }

    /** Branch + FY on tbl_pos_sale_invoices (alias psi). */
    function auragold_dashboard_pos_extra_sql(string $alias = 'psi'): string {
        $dbc = auragold_dashboard_mysqli();
        if (!$dbc) {
            return '';
        }
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
        if ($a === '') {
            $a = 'psi';
        }
        $br = '';
        if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($dbc, 'tbl_pos_sale_invoices', 'branch_id')) {
            $eff = function_exists('auragold_effective_branch_id') ? (int) auragold_effective_branch_id() : 0;
            if ($eff > 0) {
                $main = function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0;
                if ($main > 0 && $eff === $main) {
                    $br = " AND ({$a}.branch_id = {$eff} OR {$a}.branch_id IS NULL OR {$a}.branch_id = 0) ";
                } else {
                    $br = " AND COALESCE({$a}.branch_id, 0) = {$eff} ";
                }
            }
        }

        return $br . auragold_dashboard_fy_date_sql($a, 'invoice_date');
    }

    /** Branch + invoice_date range intersected with logged-in FY (when set) for POS invoices. */
    function auragold_dashboard_pos_scope_for_range_sql(string $alias, string $rangeStart, string $rangeEnd): string {
        $dbc = auragold_dashboard_mysqli();
        if (!$dbc) {
            return '';
        }
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
        if ($a === '') {
            $a = 'psi';
        }
        $br = '';
        if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($dbc, 'tbl_pos_sale_invoices', 'branch_id')) {
            $eff = function_exists('auragold_effective_branch_id') ? (int) auragold_effective_branch_id() : 0;
            if ($eff > 0) {
                $main = function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0;
                if ($main > 0 && $eff === $main) {
                    $br = " AND ({$a}.branch_id = {$eff} OR {$a}.branch_id IS NULL OR {$a}.branch_id = 0) ";
                } else {
                    $br = " AND COALESCE({$a}.branch_id, 0) = {$eff} ";
                }
            }
        }
        $rs = auragold_dashboard_normalize_fy_date($rangeStart);
        $re = auragold_dashboard_normalize_fy_date($rangeEnd);
        if ($rs === '' || $re === '') {
            return $br;
        }
        if ($rs > $re) {
            return $br . ' AND 1=0 ';
        }
        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['financial_year']) && is_array($_SESSION['financial_year'])) {
            $fs = auragold_dashboard_normalize_fy_date($_SESSION['financial_year']['start_date'] ?? '');
            $fe = auragold_dashboard_normalize_fy_date($_SESSION['financial_year']['end_date'] ?? '');
            if ($fs !== '' && $fe !== '') {
                if ($re < $fs || $rs > $fe) {
                    return $br . ' AND 1=0 ';
                }
                $rs = max($rs, $fs);
                $re = min($re, $fe);
            }
        }

        return $br . " AND {$a}.invoice_date >= '" . $rs . "' AND {$a}.invoice_date <= '" . $re . "' ";
    }

    function auragold_dashboard_payment_amount_expr(string $alias = 'p'): string {
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
        if ($a === '') {
            $a = 'p';
        }

        return "COALESCE(NULLIF({$a}.amount, 0), {$a}.current_order_amount, 0)";
    }

    /**
     * Effective sale value for dashboard KPIs: grand_total, else paid_amt, else net_total, else line net amounts.
     */
    function auragold_dashboard_sale_invoice_amount_expr(string $alias = 'si'): string {
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
        if ($a === '') {
            $a = 'si';
        }
        $itemsTable = (strpos($a, 'pos') !== false || $a === 'psi') ? 'tbl_pos_sale_invoice_items' : 'tbl_sale_invoice_items';
        $payTable = (strpos($a, 'pos') !== false || $a === 'psi') ? 'tbl_pos_sale_invoice_payments' : 'tbl_sale_invoice_payments';
        $itemFk = 'invoice_id';
        $payAmtSub = auragold_dashboard_payment_amount_expr('p');

        return "CASE
            WHEN COALESCE({$a}.grand_total, 0) > 0 THEN COALESCE({$a}.grand_total, 0)
            WHEN COALESCE({$a}.paid_amt, 0) > 0 THEN COALESCE({$a}.paid_amt, 0)
            WHEN COALESCE({$a}.net_total, 0) > 0 THEN COALESCE({$a}.net_total, 0)
            WHEN COALESCE({$a}.subtotal, 0) > 0 THEN COALESCE({$a}.subtotal, 0)
            ELSE GREATEST(
                COALESCE((
                    SELECT SUM($payAmtSub)
                    FROM {$payTable} p
                    WHERE p.invoice_id = {$a}.id AND IFNULL(p.status, 1) = 1
                ), 0),
                COALESCE((
                    SELECT SUM(COALESCE(NULLIF(it.net_amt_with_tax, 0), it.net_amount, it.amount, 0))
                    FROM {$itemsTable} it
                    WHERE it.{$itemFk} = {$a}.id AND IFNULL(it.status, 1) = 1
                ), 0)
            )
        END";
    }

    /**
     * Sum effective sale amounts for standard + POS invoices in a date scope.
     */
    function auragold_dashboard_sum_segment_sales(
        string $siDateSql,
        string $psiDateSql,
        int $customerTypeId,
        string $st,
        string $siX,
        string $pst,
        string $psiX
    ): float {
        if (!function_exists('getRecord')) {
            return 0.0;
        }
        $tid = (int) $customerTypeId;
        $saleJoin = '';
        $saleWhereExtra = '';
        $posSaleJoin = '';
        $posSaleWhereExtra = '';
        if ($tid > 0) {
            $saleJoin = ' INNER JOIN tbl_customers c ON si.customer_id = c.id ';
            $saleWhereExtra = " AND c.customer_type_id = $tid ";
            $posSaleJoin = ' INNER JOIN tbl_customers c ON psi.customer_id = c.id ';
            $posSaleWhereExtra = " AND c.customer_type_id = $tid ";
        }

        $siAmt = auragold_dashboard_sale_invoice_amount_expr('si');
        $total = 0.0;
        $rSales = getRecord(
            "SELECT COALESCE(SUM($siAmt),0) AS t FROM tbl_sale_invoices si
             $saleJoin
             WHERE 1=1 $siDateSql $saleWhereExtra $st $siX"
        );
        $total += $rSales ? (float) ($rSales['t'] ?? 0) : 0.0;

        if (auragold_table_exists('tbl_pos_sale_invoices')) {
            $psiAmt = auragold_dashboard_sale_invoice_amount_expr('psi');
            $rPosSales = getRecord(
                "SELECT COALESCE(SUM($psiAmt),0) AS t FROM tbl_pos_sale_invoices psi
                 $posSaleJoin
                 WHERE 1=1 $psiDateSql $posSaleWhereExtra $pst $psiX"
            );
            $total += $rPosSales ? (float) ($rPosSales['t'] ?? 0) : 0.0;
        }

        return $total;
    }

    /** Pick the best display/calculation amount from a sale invoice row. */
    function auragold_dashboard_invoice_display_amount($row): float {
        if (!$row || !is_array($row)) {
            return 0.0;
        }
        foreach (['grand_total', 'paid_amt', 'net_total', 'subtotal'] as $key) {
            $v = (float) ($row[$key] ?? 0);
            if ($v > 0) {
                return $v;
            }
        }

        return 0.0;
    }

    /**
     * Gold karat strip for retailer dashboard: dashboard rate sheet → tbl_settings → sale lines.
     *
     * @return array{18k:?array,21k:?array,22k:?array,24k:?array}
     */
    function auragold_retailer_dashboard_gold_market_rates(): array {
        $market = ['18k' => null, '21k' => null, '22k' => null, '24k' => null];
        $assignRate = static function (array &$bucket, string $label, float $rate): void {
            if ($rate <= 0) {
                return;
            }
            $k = strtolower(str_replace(' ', '', $label));
            if (preg_match('/^18/', $k) && $bucket['18k'] === null) {
                $bucket['18k'] = ['avg_metal_rate' => $rate, 'max_metal_rate' => $rate];
            } elseif (preg_match('/^21/', $k) && $bucket['21k'] === null) {
                $bucket['21k'] = ['avg_metal_rate' => $rate, 'max_metal_rate' => $rate];
            } elseif (preg_match('/^22/', $k) && $bucket['22k'] === null) {
                $bucket['22k'] = ['avg_metal_rate' => $rate, 'max_metal_rate' => $rate];
            } elseif (preg_match('/^24/', $k) && $bucket['24k'] === null) {
                $bucket['24k'] = ['avg_metal_rate' => $rate, 'max_metal_rate' => $rate];
            }
        };

        global $conn;
        $dbc = $conn ?? $GLOBALS['conn'] ?? null;

        if ($dbc instanceof mysqli) {
            require_once __DIR__ . '/dashboard_metal_rates_branch_schema.php';
            auragold_ensure_dashboard_metal_rates_branch_columns($dbc);
            require_once __DIR__ . '/dashboard_metal_rates_db.php';
            if (auragold_dashboard_rates_tables_exist($dbc)) {
                $bid = function_exists('auragold_effective_branch_id') ? max(0, (int) auragold_effective_branch_id()) : 0;
                $branchSql = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($dbc, 'tbl_dashboard_metal_rates', 'branch_id')
                    ? 'branch_id IN (0, ' . (int) $bid . ')'
                    : '1=1';
                $rows = getList(
                    "SELECT carat_label, rate, branch_id FROM tbl_dashboard_metal_rates
                     WHERE metal = 'gold' AND {$branchSql}
                     ORDER BY branch_id DESC, sort_order ASC, id ASC"
                ) ?: [];
                foreach ($rows as $row) {
                    $assignRate($market, (string) ($row['carat_label'] ?? ''), (float) ($row['rate'] ?? 0));
                }
            }

            $tchk = @mysqli_query($dbc, "SHOW TABLES LIKE 'tbl_settings'");
            if ($tchk && mysqli_num_rows($tchk) > 0) {
                mysqli_free_result($tchk);
                $srow = getRecord('SELECT * FROM tbl_settings LIMIT 1');
                if (is_array($srow)) {
                    foreach (['24k' => 'gold_rate_24k', '22k' => 'gold_rate_22k', '21k' => 'gold_rate_21k', '18k' => 'gold_rate_18k'] as $mk => $col) {
                        if ($market[$mk] === null && array_key_exists($col, $srow) && $srow[$col] !== '' && $srow[$col] !== null) {
                            $rate = (float) $srow[$col];
                            if ($rate > 0) {
                                $market[$mk] = ['avg_metal_rate' => $rate, 'max_metal_rate' => $rate];
                            }
                        }
                    }
                }
            } elseif ($tchk) {
                mysqli_free_result($tchk);
            }
        }

        $missing = false;
        foreach ($market as $v) {
            if ($v === null) {
                $missing = true;
                break;
            }
        }
        if ($missing) {
            foreach (auragold_dashboard_gold_metal_rates_from_sales(40) as $row) {
                $k = strtolower(str_replace(' ', '', (string) ($row['carat'] ?? '')));
                if (preg_match('/^18/', $k) && $market['18k'] === null) {
                    $market['18k'] = $row;
                } elseif (preg_match('/^21/', $k) && $market['21k'] === null) {
                    $market['21k'] = $row;
                } elseif (preg_match('/^22/', $k) && $market['22k'] === null) {
                    $market['22k'] = $row;
                } elseif (preg_match('/^24/', $k) && $market['24k'] === null) {
                    $market['24k'] = $row;
                }
            }
        }

        return $market;
    }

    /**
     * JewelSteps-style home KPIs for a customer segment (CUSTOMER / WHOLESALER): today’s figures,
     * 7-day sales series, gold market lines. Purchase totals are global (not filtered by segment).
     *
     * @param string $customerTypeCode tbl_customer_types.code e.g. CUSTOMER, WHOLESALER
     * @param string $dateFrom Y-m-d (optional; defaults to today)
     * @param string $dateTo   Y-m-d (optional; defaults to today / dateFrom)
     * @param bool $filterSalesByCustomerType When false, sales/chart/payment KPIs include all customers (retailer dashboard).
     * @return array<string,mixed>
     */
    function auragold_segment_retail_dashboard_kpis($customerTypeCode, $dateFrom = '', $dateTo = '', $filterSalesByCustomerType = true) {
        $empty = [
            'sales_today' => 0.0,
            'purchase_today' => 0.0,
            'orders_today' => 0,
            'cash_today' => 0.0,
            'bank_today' => 0.0,
            'card_today' => 0.0,
            'balance_cash' => 0.0,
            'balance_bank' => 0.0,
            'balance_card' => 0.0,
            'chart_labels' => [],
            'chart_values' => [],
            'market' => ['18k' => null, '21k' => null, '22k' => null, '24k' => null],
            'customer_type_id' => 0,
        ];
        if (!function_exists('getRecord') || !function_exists('getList')) {
            return $empty;
        }

        $bounds = auragold_retailer_dashboard_date_bounds($dateFrom, $dateTo);
        $rangeStart = $bounds['start'];
        $rangeEnd = $bounds['end'];
        $dateSql = " AND invoice_date >= '" . $rangeStart . "' AND invoice_date <= '" . $rangeEnd . "' ";
        $siDateSql = " AND si.invoice_date >= '" . $rangeStart . "' AND si.invoice_date <= '" . $rangeEnd . "' ";
        $psiDateSql = " AND psi.invoice_date >= '" . $rangeStart . "' AND psi.invoice_date <= '" . $rangeEnd . "' ";
        $orderDateSql = " AND order_date >= '" . $rangeStart . "' AND order_date <= '" . $rangeEnd . "' ";

        $code = strtoupper(trim((string) $customerTypeCode));
        $tid = $code !== '' ? auragold_customer_type_id_by_code($code) : 0;
        $salesTid = $filterSalesByCustomerType ? $tid : 0;
        $st  = auragold_dashboard_sale_status_where('si');
        $pst = auragold_dashboard_sale_status_where('psi');
        $pt  = auragold_dashboard_purchase_status_where('pi');
        $ot  = auragold_dashboard_order_status_where('so');
        $siX = function_exists('auragold_dashboard_si_extra_sql') ? auragold_dashboard_si_extra_sql('si') : '';
        $psiX = function_exists('auragold_dashboard_pos_extra_sql') ? auragold_dashboard_pos_extra_sql('psi') : '';
        $piX = function_exists('auragold_dashboard_pi_extra_sql') ? auragold_dashboard_pi_extra_sql('pi') : '';
        $soX = function_exists('auragold_dashboard_so_extra_sql') ? auragold_dashboard_so_extra_sql('so') : '';

        $salesToday = auragold_dashboard_sum_segment_sales(
            $siDateSql,
            $psiDateSql,
            $salesTid,
            $st,
            $siX,
            $pst,
            $psiX
        );

        $rPur = getRecord(
            "SELECT COALESCE(SUM(pi.grand_total),0) AS t FROM tbl_purchase_invoices pi
             WHERE 1=1 $dateSql $pt $piX"
        );
        $purchaseToday = $rPur ? (float) ($rPur['t'] ?? 0) : 0.0;

        $orderExtra = '';
        if ($tid > 0) {
            $orderExtra = " AND (so.customer_id IS NULL OR EXISTS (SELECT 1 FROM tbl_customers cx WHERE cx.id = so.customer_id AND cx.customer_type_id = $tid))";
        }
        $rOrd = getRecord(
            "SELECT COUNT(*) AS c FROM tbl_sale_orders so
             WHERE 1=1 $orderDateSql $ot $orderExtra $soX"
        );
        $ordersToday = $rOrd ? (int) ($rOrd['c'] ?? 0) : 0;

        $payAmt = auragold_dashboard_payment_amount_expr('sip');
        $payJoin = ' INNER JOIN tbl_sale_invoices si ON sip.invoice_id = si.id ';
        $payExtra = '';
        if ($salesTid > 0) {
            $payJoin .= ' INNER JOIN tbl_customers c2 ON si.customer_id = c2.id ';
            $payExtra = " AND c2.customer_type_id = $salesTid ";
        }
        $payWhere = " IFNULL(sip.status, 1) = 1 $siDateSql $payExtra $st $siX ";
        $ptCash = "LOWER(TRIM(COALESCE(sip.payment_type,''))) = 'cash'";
        $ptBank = "LOWER(TRIM(COALESCE(sip.payment_type,''))) IN ('bank','upi','cheque','check')";
        $ptCard = "LOWER(TRIM(COALESCE(sip.payment_type,''))) = 'card'";

        $cashToday = 0.0;
        $bankToday = 0.0;
        $cardToday = 0.0;

        $rCash = getRecord(
            "SELECT COALESCE(SUM($payAmt),0) AS t FROM tbl_sale_invoice_payments sip
             $payJoin WHERE $payWhere AND $ptCash"
        );
        $cashToday = $rCash ? (float) ($rCash['t'] ?? 0) : 0.0;
        $rBank = getRecord(
            "SELECT COALESCE(SUM($payAmt),0) AS t FROM tbl_sale_invoice_payments sip
             $payJoin WHERE $payWhere AND $ptBank"
        );
        $bankToday = $rBank ? (float) ($rBank['t'] ?? 0) : 0.0;
        $rCard = getRecord(
            "SELECT COALESCE(SUM($payAmt),0) AS t FROM tbl_sale_invoice_payments sip
             $payJoin WHERE $payWhere AND $ptCard"
        );
        $cardToday = $rCard ? (float) ($rCard['t'] ?? 0) : 0.0;

        if (auragold_table_exists('tbl_pos_sale_invoice_payments')) {
            $posPayAmt = auragold_dashboard_payment_amount_expr('psip');
            $posPayJoin = ' INNER JOIN tbl_pos_sale_invoices psi ON psip.invoice_id = psi.id ';
            $posPayExtra = '';
            if ($salesTid > 0) {
                $posPayJoin .= ' INNER JOIN tbl_customers c3 ON psi.customer_id = c3.id ';
                $posPayExtra = " AND c3.customer_type_id = $salesTid ";
            }
            $posPayWhere = " IFNULL(psip.status, 1) = 1 $psiDateSql $posPayExtra $pst $psiX ";
            $ptCashPos = "LOWER(TRIM(COALESCE(psip.payment_type,''))) = 'cash'";
            $ptBankPos = "LOWER(TRIM(COALESCE(psip.payment_type,''))) IN ('bank','upi','cheque','check')";
            $ptCardPos = "LOWER(TRIM(COALESCE(psip.payment_type,''))) = 'card'";

            $rCashPos = getRecord(
                "SELECT COALESCE(SUM($posPayAmt),0) AS t FROM tbl_pos_sale_invoice_payments psip
                 $posPayJoin WHERE $posPayWhere AND $ptCashPos"
            );
            $cashToday += $rCashPos ? (float) ($rCashPos['t'] ?? 0) : 0.0;
            $rBankPos = getRecord(
                "SELECT COALESCE(SUM($posPayAmt),0) AS t FROM tbl_pos_sale_invoice_payments psip
                 $posPayJoin WHERE $posPayWhere AND $ptBankPos"
            );
            $bankToday += $rBankPos ? (float) ($rBankPos['t'] ?? 0) : 0.0;
            $rCardPos = getRecord(
                "SELECT COALESCE(SUM($posPayAmt),0) AS t FROM tbl_pos_sale_invoice_payments psip
                 $posPayJoin WHERE $posPayWhere AND $ptCardPos"
            );
            $cardToday += $rCardPos ? (float) ($rCardPos['t'] ?? 0) : 0.0;
        }

        $chartLabels = [];
        $chartValues = [];
        $chartStart = new \DateTime($rangeStart);
        $chartEnd = new \DateTime($rangeEnd);
        $iter = clone $chartStart;
        while ($iter <= $chartEnd) {
            $d = $iter->format('Y-m-d');
            $chartLabels[] = $iter->format('D j');
            $daySiSql = " AND si.invoice_date = '$d' ";
            $dayPsiSql = " AND psi.invoice_date = '$d' ";
            $dayTotal = auragold_dashboard_sum_segment_sales(
                $daySiSql,
                $dayPsiSql,
                $salesTid,
                $st,
                $siX,
                $pst,
                $psiX
            );
            $chartValues[] = round($dayTotal, 2);
            $iter->modify('+1 day');
        }

        $market = auragold_retailer_dashboard_gold_market_rates();

        return [
            'sales_today' => $salesToday,
            'purchase_today' => $purchaseToday,
            'orders_today' => $ordersToday,
            'cash_today' => $cashToday,
            'bank_today' => $bankToday,
            'card_today' => $cardToday,
            'balance_cash' => auragold_ledger_system_balance('Cash'),
            'balance_bank' => auragold_ledger_system_balance_sum(['Bank Account', 'Bank']),
            'balance_card' => auragold_ledger_system_balance('Card'),
            'chart_labels' => $chartLabels,
            'chart_values' => $chartValues,
            'market' => $market,
            'customer_type_id' => $tid,
            'date_from' => $rangeStart,
            'date_to' => $rangeEnd,
            'is_today' => $bounds['is_today'],
            'is_single_day' => $bounds['is_single_day'],
        ];
    }

    /**
     * Retailer (CUSTOMER type) — same data as {@see auragold_segment_retail_dashboard_kpis}('CUSTOMER').
     *
     * @return array<string,mixed>
     */
    function auragold_retailer_dashboard_kpis($dateFrom = '', $dateTo = '') {
        // Include all sale invoices for the branch — not only customers tagged CUSTOMER type.
        $r = auragold_segment_retail_dashboard_kpis('CUSTOMER', $dateFrom, $dateTo, false);
        $r['retailer_type_id'] = (int) ($r['customer_type_id'] ?? 0);

        return $r;
    }

    /**
     * Extra retailer dashboard lists: recent invoices, pending orders, month totals.
     *
     * @return array<string,mixed>
     */
    function auragold_retailer_dashboard_extras() {
        $out = [
            'recent_invoices' => [],
            'pending_orders' => [],
            'sales_month' => 0.0,
            'sales_week' => 0.0,
            'customers_count' => 0,
        ];
        if (!function_exists('getRecord') || !function_exists('getList')) {
            return $out;
        }

        $tid = auragold_customer_type_id_by_code('CUSTOMER');
        $st  = auragold_dashboard_sale_status_where('si');
        $pst = auragold_dashboard_sale_status_where('psi');
        $ot  = auragold_dashboard_order_status_where('so');
        $siX = function_exists('auragold_dashboard_si_extra_sql') ? auragold_dashboard_si_extra_sql('si') : '';
        $psiX = function_exists('auragold_dashboard_pos_extra_sql') ? auragold_dashboard_pos_extra_sql('psi') : '';
        $soX = function_exists('auragold_dashboard_so_extra_sql') ? auragold_dashboard_so_extra_sql('so') : '';

        $saleJoin = '';
        $saleWhereExtra = '';
        $orderExtra = '';
        if ($tid > 0) {
            $orderExtra = " AND (so.customer_id IS NULL OR EXISTS (SELECT 1 FROM tbl_customers cx WHERE cx.id = so.customer_id AND cx.customer_type_id = $tid))";
        }

        $monthStart = date('Y-m-01');
        $monthSiSql = " AND si.invoice_date >= '$monthStart' ";
        $monthPsiSql = " AND psi.invoice_date >= '$monthStart' ";
        $out['sales_month'] = auragold_dashboard_sum_segment_sales(
            $monthSiSql,
            $monthPsiSql,
            0,
            $st,
            $siX,
            $pst,
            $psiX
        );

        $weekStart = date('Y-m-d', strtotime('-6 days'));
        $weekSiSql = " AND si.invoice_date >= '$weekStart' ";
        $weekPsiSql = " AND psi.invoice_date >= '$weekStart' ";
        $out['sales_week'] = auragold_dashboard_sum_segment_sales(
            $weekSiSql,
            $weekPsiSql,
            0,
            $st,
            $siX,
            $pst,
            $psiX
        );

        if ($tid > 0 && auragold_table_exists('tbl_customers')) {
            $rCust = getRecord("SELECT COUNT(*) AS c FROM tbl_customers WHERE status = 1 AND customer_type_id = $tid");
            $out['customers_count'] = $rCust ? (int) ($rCust['c'] ?? 0) : 0;
        }

        if (auragold_table_exists('tbl_sale_invoices')) {
            $out['recent_invoices'] = getList(
                "SELECT si.id, si.invoice_no, si.customer_name, si.invoice_date,
                        si.grand_total, si.paid_amt, si.net_total, si.status
                 FROM tbl_sale_invoices si $saleJoin
                 WHERE 1=1 $saleWhereExtra $st $siX
                 ORDER BY si.invoice_date DESC, si.id DESC
                 LIMIT 8"
            ) ?: [];
        }

        if (auragold_table_exists('tbl_sale_orders')) {
            $out['pending_orders'] = getList(
                "SELECT so.id, so.order_no, so.customer_name, so.order_date, so.status,
                        COALESCE(so.grand_total, 0) AS grand_total
                 FROM tbl_sale_orders so
                 WHERE LOWER(TRIM(IFNULL(so.status,''))) NOT IN ('completed','done','closed','delivered','fulfilled','cancelled','void')
                 AND TRIM(IFNULL(so.status,'')) <> '' $ot $orderExtra $soX
                 ORDER BY so.order_date DESC, so.id DESC
                 LIMIT 6"
            ) ?: [];
        }

        return $out;
    }

    /**
     * Wholesaler (WHOLESALER type) — same layout/KPI logic as retailer, filtered by wholesaler customers.
     *
     * @param string $dateFrom Y-m-d (optional; defaults to today)
     * @param string $dateTo   Y-m-d (optional; defaults to today / dateFrom)
     * @return array<string,mixed>
     */
    function auragold_wholesaler_dashboard_kpis($dateFrom = '', $dateTo = '') {
        return auragold_segment_retail_dashboard_kpis('WHOLESALER', $dateFrom, $dateTo, true);
    }

    /**
     * Extra wholesaler dashboard lists: recent invoices, purchases, orders, consignments.
     *
     * @return array<string,mixed>
     */
    function auragold_wholesaler_dashboard_extras() {
        $out = [
            'recent_invoices' => [],
            'recent_purchases' => [],
            'pending_orders' => [],
            'active_consignments' => [],
            'sales_month' => 0.0,
            'sales_week' => 0.0,
            'purchases_month' => 0.0,
            'customers_count' => 0,
        ];
        if (!function_exists('getRecord') || !function_exists('getList')) {
            return $out;
        }

        $tid = auragold_customer_type_id_by_code('WHOLESALER');
        $st  = auragold_dashboard_sale_status_where('si');
        $pst = auragold_dashboard_sale_status_where('psi');
        $pt  = auragold_dashboard_purchase_status_where('pi');
        $ot  = auragold_dashboard_order_status_where('so');
        $siX = function_exists('auragold_dashboard_si_extra_sql') ? auragold_dashboard_si_extra_sql('si') : '';
        $psiX = function_exists('auragold_dashboard_pos_extra_sql') ? auragold_dashboard_pos_extra_sql('psi') : '';
        $piX = function_exists('auragold_dashboard_pi_extra_sql') ? auragold_dashboard_pi_extra_sql('pi') : '';
        $soX = function_exists('auragold_dashboard_so_extra_sql') ? auragold_dashboard_so_extra_sql('so') : '';

        $saleJoin = '';
        $saleWhereExtra = '';
        $orderExtra = '';
        $consJoin = '';
        $consExtra = '';
        if ($tid > 0) {
            $saleJoin = ' INNER JOIN tbl_customers c ON si.customer_id = c.id ';
            $saleWhereExtra = " AND c.customer_type_id = $tid ";
            $orderExtra = " AND (so.customer_id IS NULL OR EXISTS (SELECT 1 FROM tbl_customers cx WHERE cx.id = so.customer_id AND cx.customer_type_id = $tid))";
            $consJoin = ' INNER JOIN tbl_customers c ON co.customer_id = c.id ';
            $consExtra = " AND c.customer_type_id = $tid ";
        }

        $monthStart = date('Y-m-01');
        $monthSiSql = " AND si.invoice_date >= '$monthStart' ";
        $monthPsiSql = " AND psi.invoice_date >= '$monthStart' ";
        $out['sales_month'] = auragold_dashboard_sum_segment_sales(
            $monthSiSql,
            $monthPsiSql,
            $tid,
            $st,
            $siX,
            $pst,
            $psiX
        );

        $weekStart = date('Y-m-d', strtotime('-6 days'));
        $weekSiSql = " AND si.invoice_date >= '$weekStart' ";
        $weekPsiSql = " AND psi.invoice_date >= '$weekStart' ";
        $out['sales_week'] = auragold_dashboard_sum_segment_sales(
            $weekSiSql,
            $weekPsiSql,
            $tid,
            $st,
            $siX,
            $pst,
            $psiX
        );

        if (auragold_table_exists('tbl_purchase_invoices')) {
            $rPurMonth = getRecord(
                "SELECT COALESCE(SUM(pi.grand_total),0) AS t FROM tbl_purchase_invoices pi
                 WHERE pi.invoice_date >= '$monthStart' $pt $piX"
            );
            $out['purchases_month'] = $rPurMonth ? (float) ($rPurMonth['t'] ?? 0) : 0.0;
        }

        if ($tid > 0 && auragold_table_exists('tbl_customers')) {
            $rCust = getRecord("SELECT COUNT(*) AS c FROM tbl_customers WHERE status = 1 AND customer_type_id = $tid");
            $out['customers_count'] = $rCust ? (int) ($rCust['c'] ?? 0) : 0;
        }

        if (auragold_table_exists('tbl_sale_invoices')) {
            $out['recent_invoices'] = getList(
                "SELECT si.id, si.invoice_no, si.customer_name, si.invoice_date, si.grand_total, si.status
                 FROM tbl_sale_invoices si $saleJoin
                 WHERE 1=1 $saleWhereExtra $st $siX
                 ORDER BY si.invoice_date DESC, si.id DESC
                 LIMIT 8"
            ) ?: [];
        }

        if (auragold_table_exists('tbl_purchase_invoices')) {
            $out['recent_purchases'] = getList(
                "SELECT pi.id, pi.invoice_no, pi.supplier_name, pi.invoice_date, pi.grand_total, pi.status
                 FROM tbl_purchase_invoices pi
                 WHERE 1=1 $pt $piX
                 ORDER BY pi.invoice_date DESC, pi.id DESC
                 LIMIT 8"
            ) ?: [];
        }

        if (auragold_table_exists('tbl_sale_orders')) {
            $out['pending_orders'] = getList(
                "SELECT so.id, so.order_no, so.customer_name, so.order_date, so.status,
                        COALESCE(so.grand_total, 0) AS grand_total
                 FROM tbl_sale_orders so
                 WHERE LOWER(TRIM(IFNULL(so.status,''))) NOT IN ('completed','done','closed','delivered','fulfilled','cancelled','void')
                 AND TRIM(IFNULL(so.status,'')) <> '' $ot $orderExtra $soX
                 ORDER BY so.order_date DESC, so.id DESC
                 LIMIT 6"
            ) ?: [];
        }

        if (auragold_table_exists('tbl_consignment_out')) {
            $out['active_consignments'] = getList(
                "SELECT co.id, co.consignment_no, co.customer_name, co.consignment_date, co.grand_total, co.status
                 FROM tbl_consignment_out co $consJoin
                 WHERE LOWER(TRIM(IFNULL(co.status,''))) IN ('active','open','pending')
                 OR (TRIM(IFNULL(co.status,'')) <> '' AND LOWER(TRIM(co.status)) NOT IN ('cancelled','void','canceled','returned','closed','completed'))
                 $consExtra
                 ORDER BY co.consignment_date DESC, co.id DESC
                 LIMIT 6"
            ) ?: [];
        }

        return $out;
    }

    /**
     * @param string $tableName
     */
    function auragold_table_exists($tableName) {
        global $conn;
        $dbc = $conn ?? $GLOBALS['conn'] ?? null;
        if (!$dbc) {
            return false;
        }
        $t = mysqli_real_escape_string($dbc, $tableName);
        $r = @mysqli_query($dbc, "SHOW TABLES LIKE '$t'");
        $ok = $r && mysqli_num_rows($r) > 0;
        if ($r) {
            mysqli_free_result($r);
        }
        return $ok;
    }

    /**
     * Extra manufacturing dashboard metrics.
     *
     * @return array<string,mixed>
     */
    function auragold_manufacturing_dashboard_extras($dateFrom = '', $dateTo = '') {
        $out = [
            'jobs_due_week' => 0,
            'jobs_completed_month' => 0,
            'pending_sale_orders' => 0,
            'date_from' => '',
            'date_to' => '',
            'is_today' => true,
            'is_single_day' => true,
        ];
        if (!function_exists('getRecord')) {
            return $out;
        }

        $bounds = auragold_retailer_dashboard_date_bounds($dateFrom, $dateTo);
        $rangeStart = $bounds['start'];
        $rangeEnd = $bounds['end'];
        $out['date_from'] = $rangeStart;
        $out['date_to'] = $rangeEnd;
        $out['is_today'] = !empty($bounds['is_today']);
        $out['is_single_day'] = !empty($bounds['is_single_day']);

        $jw  = 'tbl_jobwork_orders';
        $jwoX = function_exists('auragold_dashboard_jwo_extra_sql') ? auragold_dashboard_jwo_extra_sql('j') : '';
        $soX  = function_exists('auragold_dashboard_so_extra_sql') ? auragold_dashboard_so_extra_sql('so') : '';
        $ot   = auragold_dashboard_order_status_where('so');
        $stDone = " LOWER(TRIM(IFNULL(j.status,''))) IN ('cancelled','void','canceled','completed','done','closed') ";
        $stOpen = " NOT ($stDone) ";
        $jDateSql = " AND j.order_date >= '" . $rangeStart . "' AND j.order_date <= '" . $rangeEnd . "' ";
        $soDateSql = " AND so.order_date >= '" . $rangeStart . "' AND so.order_date <= '" . $rangeEnd . "' ";

        if (auragold_table_exists($jw)) {
            $rDue = getRecord(
                "SELECT COUNT(*) AS c FROM $jw j WHERE 1=1 $jwoX AND $stOpen
                 AND j.due_date IS NOT NULL AND j.due_date >= '" . $rangeStart . "' AND j.due_date <= '" . $rangeEnd . "'"
            );
            $out['jobs_due_week'] = $rDue ? (int) ($rDue['c'] ?? 0) : 0;

            $rDone = getRecord(
                "SELECT COUNT(*) AS c FROM $jw j WHERE 1=1 $jwoX
                 AND LOWER(TRIM(IFNULL(j.status,''))) IN ('completed','done','closed')
                 $jDateSql"
            );
            $out['jobs_completed_month'] = $rDone ? (int) ($rDone['c'] ?? 0) : 0;
        }

        if (auragold_table_exists('tbl_sale_orders')) {
            $rPending = getRecord(
                "SELECT COUNT(*) AS c FROM tbl_sale_orders so
                 WHERE LOWER(TRIM(IFNULL(so.status,''))) NOT IN ('completed','done','closed','delivered','fulfilled','cancelled','void')
                 AND TRIM(IFNULL(so.status,'')) <> '' $ot $soX"
            );
            $out['pending_sale_orders'] = $rPending ? (int) ($rPending['c'] ?? 0) : 0;
        }

        return $out;
    }

    /**
     * Manufacturing / jobwork dashboard: KPIs from tbl_jobwork_orders + sale order lists.
     *
     * @param string $dateFrom Y-m-d (optional; defaults to today)
     * @param string $dateTo   Y-m-d (optional; defaults to today / dateFrom)
     * @return array<string,mixed>
     */
    function auragold_manufacturing_dashboard($dateFrom = '', $dateTo = '') {
        $bounds = auragold_retailer_dashboard_date_bounds($dateFrom, $dateTo);
        $rangeStart = $bounds['start'];
        $rangeEnd = $bounds['end'];
        $jDateSql = " AND j.order_date >= '" . $rangeStart . "' AND j.order_date <= '" . $rangeEnd . "' ";

        $out = [
            'has_jobwork' => false,
            'kpi' => [
                'in_progress' => 0,
                'delayed' => 0,
                'on_hold' => 0,
                'not_initiate' => 0,
            ],
            'list_in_progress' => [],
            'workstation_rows' => [],
            'list_on_hold' => [],
            'list_delayed' => [],
            'recent_sale_orders' => [],
            'completed_orders' => [],
            'total_jobwork' => 0,
            'total_sale_orders' => 0,
            'date_from' => $rangeStart,
            'date_to' => $rangeEnd,
            'is_today' => !empty($bounds['is_today']),
            'is_single_day' => !empty($bounds['is_single_day']),
        ];
        if (!function_exists('getRecord') || !function_exists('getList')) {
            return $out;
        }

        $jw  = 'tbl_jobwork_orders';
        $jwoX = function_exists('auragold_dashboard_jwo_extra_sql') ? auragold_dashboard_jwo_extra_sql('j') : '';
        $soX  = function_exists('auragold_dashboard_so_extra_sql') ? auragold_dashboard_so_extra_sql('so') : '';
        if (!auragold_table_exists($jw)) {
            $out['recent_sale_orders'] = auragold_mfg_recent_sale_orders(12, $rangeStart, $rangeEnd);
            $out['completed_orders'] = auragold_mfg_completed_sale_orders(12, $rangeStart, $rangeEnd);
            if (auragold_table_exists('tbl_sale_orders')) {
                $soDateSql = " AND so.order_date >= '" . $rangeStart . "' AND so.order_date <= '" . $rangeEnd . "' ";
                $rso = getRecord('SELECT COUNT(*) AS c FROM tbl_sale_orders so WHERE 1=1 ' . $soX . $soDateSql);
                $out['total_sale_orders'] = $rso ? (int) ($rso['c'] ?? 0) : 0;
            }
            return $out;
        }

        $out['has_jobwork'] = true;
        $stDone = " LOWER(TRIM(IFNULL(j.status,''))) IN ('cancelled','void','canceled','completed','done','closed') ";
        $stOpen = " NOT ($stDone) ";

        $rTot = getRecord("SELECT COUNT(*) AS c FROM $jw j WHERE 1=1 $jwoX $jDateSql");
        $out['total_jobwork'] = $rTot ? (int) ($rTot['c'] ?? 0) : 0;

        $rHold = getRecord(
            "SELECT COUNT(*) AS c FROM $jw j WHERE 1=1 $jwoX AND (
             LOWER(TRIM(IFNULL(j.status,''))) LIKE '%hold%'
             OR LOWER(TRIM(IFNULL(j.status,''))) IN ('on hold','on_hold','hold'))"
        );
        $out['kpi']['on_hold'] = $rHold ? (int) ($rHold['c'] ?? 0) : 0;

        $rNi = getRecord(
            "SELECT COUNT(*) AS c FROM $jw j WHERE 1=1 $jwoX AND (
             LOWER(TRIM(IFNULL(j.status,''))) IN ('draft','pending','not_initiated','not initiate')
             OR TRIM(IFNULL(j.status,'')) = '')"
        );
        $out['kpi']['not_initiate'] = $rNi ? (int) ($rNi['c'] ?? 0) : 0;

        $rDel = getRecord(
            "SELECT COUNT(*) AS c FROM $jw j WHERE 1=1 $jwoX AND $stOpen
             AND j.due_date IS NOT NULL AND j.due_date < CURDATE()"
        );
        $out['kpi']['delayed'] = $rDel ? (int) ($rDel['c'] ?? 0) : 0;

        $rIp = getRecord(
            "SELECT COUNT(*) AS c FROM $jw j WHERE 1=1 $jwoX AND $stOpen
             AND LOWER(TRIM(IFNULL(j.status,''))) NOT IN ('draft','pending')
             AND TRIM(IFNULL(j.status,'')) <> ''
             AND LOWER(TRIM(IFNULL(j.status,''))) NOT LIKE '%hold%'
             AND NOT (j.due_date IS NOT NULL AND j.due_date < CURDATE())"
        );
        $out['kpi']['in_progress'] = $rIp ? (int) ($rIp['c'] ?? 0) : 0;

        $out['list_in_progress'] = getList(
            "SELECT j.id, j.jobwork_no, j.customer_name, j.sale_order_no, j.order_date, j.due_date, j.status, j.department_id
             FROM $jw j WHERE 1=1 $jwoX AND $stOpen
             AND LOWER(TRIM(IFNULL(j.status,''))) NOT IN ('draft','pending')
             AND TRIM(IFNULL(j.status,'')) <> ''
             AND LOWER(TRIM(IFNULL(j.status,''))) NOT LIKE '%hold%'
             AND NOT (j.due_date IS NOT NULL AND j.due_date < CURDATE())
             ORDER BY j.due_date ASC, j.id DESC
             LIMIT 20"
        ) ?: [];

        $out['list_on_hold'] = getList(
            "SELECT j.id, j.jobwork_no, j.customer_name, j.sale_order_no, j.order_date, j.due_date, j.status, j.department_id
             FROM $jw j WHERE 1=1 $jwoX AND
             (LOWER(TRIM(IFNULL(j.status,''))) LIKE '%hold%' OR LOWER(TRIM(IFNULL(j.status,''))) IN ('on hold','on_hold','hold'))
             ORDER BY j.order_date DESC
             LIMIT 20"
        ) ?: [];

        $out['list_delayed'] = getList(
            "SELECT j.id, j.jobwork_no, j.customer_name, j.sale_order_no, j.order_date, j.due_date, j.status, j.department_id
             FROM $jw j WHERE 1=1 $jwoX AND $stOpen
             AND j.due_date IS NOT NULL AND j.due_date < CURDATE()
             ORDER BY j.due_date ASC
             LIMIT 20"
        ) ?: [];

        $out['workstation_rows'] = auragold_mfg_workstation_summary($rangeStart, $rangeEnd);

        $out['recent_sale_orders'] = auragold_mfg_recent_sale_orders(12, $rangeStart, $rangeEnd);
        $out['completed_orders'] = auragold_mfg_completed_sale_orders(12, $rangeStart, $rangeEnd);

        if (auragold_table_exists('tbl_sale_orders')) {
            $soDateSql = " AND so.order_date >= '" . $rangeStart . "' AND so.order_date <= '" . $rangeEnd . "' ";
            $rso = getRecord('SELECT COUNT(*) AS c FROM tbl_sale_orders so WHERE 1=1 ' . $soX . $soDateSql);
            $out['total_sale_orders'] = $rso ? (int) ($rso['c'] ?? 0) : 0;
        }

        return $out;
    }

    /**
     * @return list<array<string,mixed>>
     */
    function auragold_mfg_workstation_summary($dateFrom = '', $dateTo = '') {
        if (!function_exists('getList') || !auragold_table_exists('tbl_jobwork_orders')) {
            return [];
        }
        $bounds = auragold_retailer_dashboard_date_bounds($dateFrom, $dateTo);
        $jDateSql = " AND j.order_date >= '" . $bounds['start'] . "' AND j.order_date <= '" . $bounds['end'] . "' ";
        $jwoX = function_exists('auragold_dashboard_jwo_extra_sql') ? auragold_dashboard_jwo_extra_sql('j') : '';
        $rows = getList(
            "SELECT TRIM(j.department_id) AS dept_key, COUNT(*) AS order_count
             FROM tbl_jobwork_orders j
             WHERE 1=1 $jwoX $jDateSql AND j.department_id IS NOT NULL AND TRIM(j.department_id) <> ''
             GROUP BY TRIM(j.department_id)
             ORDER BY order_count DESC
             LIMIT 25"
        ) ?: [];
        $deptNames = [];
        if (auragold_table_exists('tbl_departments')) {
            $dl = getList('SELECT id, dept_name FROM tbl_departments WHERE status = 1');
            foreach ($dl ?: [] as $d) {
                $deptNames[(string) ($d['id'] ?? '')] = (string) ($d['dept_name'] ?? '');
            }
        }
        foreach ($rows as &$r) {
            $k = (string) ($r['dept_key'] ?? '');
            $r['dept_label'] = $deptNames[$k] ?? $k;
        }
        unset($r);
        return $rows;
    }

    /**
     * @return list<array<string,mixed>>
     */
    function auragold_mfg_recent_sale_orders($limit = 12, $dateFrom = '', $dateTo = '') {
        if (!function_exists('getList') || !auragold_table_exists('tbl_sale_orders')) {
            return [];
        }
        $lim = max(1, min(50, (int) $limit));
        $bounds = auragold_retailer_dashboard_date_bounds($dateFrom, $dateTo);
        $soDateSql = " AND so.order_date >= '" . $bounds['start'] . "' AND so.order_date <= '" . $bounds['end'] . "' ";
        $soX = function_exists('auragold_dashboard_so_extra_sql') ? auragold_dashboard_so_extra_sql('so') : '';
        return getList(
            "SELECT so.id, so.customer_name, so.order_no, so.order_date, so.status,
                    (SELECT MIN(soi.barcode) FROM tbl_sale_order_items soi WHERE soi.order_id = so.id AND soi.barcode IS NOT NULL AND TRIM(soi.barcode) <> '') AS tag_no
             FROM tbl_sale_orders so
             WHERE 1=1 $soX $soDateSql
             ORDER BY so.order_date DESC, so.id DESC
             LIMIT $lim"
        ) ?: [];
    }

    /**
     * @return list<array<string,mixed>>
     */
    function auragold_mfg_completed_sale_orders($limit = 12, $dateFrom = '', $dateTo = '') {
        if (!function_exists('getList') || !auragold_table_exists('tbl_sale_orders')) {
            return [];
        }
        $lim = max(1, min(50, (int) $limit));
        $bounds = auragold_retailer_dashboard_date_bounds($dateFrom, $dateTo);
        $soDateSql = " AND so.order_date >= '" . $bounds['start'] . "' AND so.order_date <= '" . $bounds['end'] . "' ";
        $ot  = auragold_dashboard_order_status_where('so');
        $soX = function_exists('auragold_dashboard_so_extra_sql') ? auragold_dashboard_so_extra_sql('so') : '';
        return getList(
            "SELECT so.id, so.customer_name, so.order_no, so.order_date, so.status,
                    (SELECT MIN(soi.barcode) FROM tbl_sale_order_items soi WHERE soi.order_id = so.id AND soi.barcode IS NOT NULL AND TRIM(soi.barcode) <> '') AS tag_no
             FROM tbl_sale_orders so
             WHERE LOWER(TRIM(IFNULL(so.status,''))) IN ('completed','done','closed','delivered','fulfilled')
             $ot $soX $soDateSql
             ORDER BY so.order_date DESC, so.id DESC
             LIMIT $lim"
        ) ?: [];
    }

    /**
     * Sales person filter for tbl_sale_invoices (alias si). Empty or ALL = no filter.
     */
    function auragold_salesperson_sql_filter($selectedSp, $alias = 'si') {
        global $conn;
        $dbc = $conn ?? $GLOBALS['conn'] ?? null;
        $sp = trim((string) $selectedSp);
        if ($sp === '' || strtoupper($sp) === 'ALL') {
            return '';
        }
        if (!$dbc) {
            return '';
        }
        $esc = mysqli_real_escape_string($dbc, $sp);
        return " AND LOWER(TRIM($alias.sales_person)) = LOWER('$esc') ";
    }

    /**
     * @return list<string>
     */
    function auragold_salesperson_distinct_names() {
        if (!function_exists('getList')) {
            return [];
        }
        $st  = auragold_dashboard_sale_status_where('si');
        $pst = auragold_dashboard_sale_status_where('psi');
        $siX = function_exists('auragold_dashboard_si_extra_sql') ? auragold_dashboard_si_extra_sql('si') : '';
        $psiX = function_exists('auragold_dashboard_pos_extra_sql') ? auragold_dashboard_pos_extra_sql('psi') : '';
        $unionPos = auragold_table_exists('tbl_pos_sale_invoices')
            ? " UNION SELECT TRIM(psi.sales_person) AS sp_name FROM tbl_pos_sale_invoices psi
                 WHERE psi.sales_person IS NOT NULL AND TRIM(psi.sales_person) <> '' $pst $psiX "
            : '';
        $rows = getList(
            "SELECT DISTINCT TRIM(sp_name) AS sp FROM (
                SELECT TRIM(si.sales_person) AS sp_name FROM tbl_sale_invoices si
                 WHERE si.sales_person IS NOT NULL AND TRIM(si.sales_person) <> '' $st $siX
                $unionPos
             ) names
             WHERE TRIM(sp_name) <> ''
             ORDER BY sp ASC"
        ) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $s = trim((string) ($r['sp'] ?? ''));
            if ($s !== '') {
                $out[] = $s;
            }
        }
        return $out;
    }

    /**
     * @return array{sales:float,invoices:int}
     */
    function auragold_salesperson_sum_sales_range($selectedSp, $rangeStart, $rangeEnd) {
        if (!function_exists('getRecord')) {
            return ['sales' => 0.0, 'invoices' => 0];
        }
        $st  = auragold_dashboard_sale_status_where('si');
        $pst = auragold_dashboard_sale_status_where('psi');
        $spf = auragold_salesperson_sql_filter($selectedSp, 'si');
        $pspf = auragold_salesperson_sql_filter($selectedSp, 'psi');
        $siX = function_exists('auragold_dashboard_si_extra_sql') ? auragold_dashboard_si_extra_sql('si') : '';
        $psiX = function_exists('auragold_dashboard_pos_extra_sql') ? auragold_dashboard_pos_extra_sql('psi') : '';
        $rngSi = function_exists('auragold_dashboard_si_scope_for_range_sql')
            ? auragold_dashboard_si_scope_for_range_sql('si', $rangeStart, $rangeEnd)
            : '';
        $rngPsi = function_exists('auragold_dashboard_pos_scope_for_range_sql')
            ? auragold_dashboard_pos_scope_for_range_sql('psi', $rangeStart, $rangeEnd)
            : '';
        $siAmt = auragold_dashboard_sale_invoice_amount_expr('si');
        $psiAmt = auragold_dashboard_sale_invoice_amount_expr('psi');

        $sales = 0.0;
        $invoices = 0;
        $rSi = getRecord(
            "SELECT COALESCE(SUM($siAmt),0) AS t, COUNT(DISTINCT si.id) AS c
             FROM tbl_sale_invoices si
             WHERE 1=1 $spf $st $rngSi $siX"
        );
        if ($rSi) {
            $sales += (float) ($rSi['t'] ?? 0);
            $invoices += (int) ($rSi['c'] ?? 0);
        }
        if (auragold_table_exists('tbl_pos_sale_invoices')) {
            $rPsi = getRecord(
                "SELECT COALESCE(SUM($psiAmt),0) AS t, COUNT(DISTINCT psi.id) AS c
                 FROM tbl_pos_sale_invoices psi
                 WHERE 1=1 $pspf $pst $rngPsi $psiX"
            );
            if ($rPsi) {
                $sales += (float) ($rPsi['t'] ?? 0);
                $invoices += (int) ($rPsi['c'] ?? 0);
            }
        }

        return ['sales' => $sales, 'invoices' => $invoices];
    }

    function auragold_salesperson_sum_making_range($selectedSp, $rangeStart, $rangeEnd) {
        if (!function_exists('getRecord')) {
            return 0.0;
        }
        $st  = auragold_dashboard_sale_status_where('si');
        $pst = auragold_dashboard_sale_status_where('psi');
        $spf = auragold_salesperson_sql_filter($selectedSp, 'si');
        $pspf = auragold_salesperson_sql_filter($selectedSp, 'psi');
        $siX = function_exists('auragold_dashboard_si_extra_sql') ? auragold_dashboard_si_extra_sql('si') : '';
        $psiX = function_exists('auragold_dashboard_pos_extra_sql') ? auragold_dashboard_pos_extra_sql('psi') : '';
        $rngSi = function_exists('auragold_dashboard_si_scope_for_range_sql')
            ? auragold_dashboard_si_scope_for_range_sql('si', $rangeStart, $rangeEnd)
            : '';
        $rngPsi = function_exists('auragold_dashboard_pos_scope_for_range_sql')
            ? auragold_dashboard_pos_scope_for_range_sql('psi', $rangeStart, $rangeEnd)
            : '';

        $making = 0.0;
        $rMk = getRecord(
            "SELECT COALESCE(SUM(sii.making_amount),0) AS m
             FROM tbl_sale_invoice_items sii
             INNER JOIN tbl_sale_invoices si ON sii.invoice_id = si.id
             WHERE sii.status = 1 $spf $st $rngSi $siX"
        );
        $making += $rMk ? (float) ($rMk['m'] ?? 0) : 0.0;
        if (auragold_table_exists('tbl_pos_sale_invoice_items')) {
            $rMkPos = getRecord(
                "SELECT COALESCE(SUM(psii.making_amount),0) AS m
                 FROM tbl_pos_sale_invoice_items psii
                 INNER JOIN tbl_pos_sale_invoices psi ON psii.invoice_id = psi.id
                 WHERE psii.status = 1 $pspf $pst $rngPsi $psiX"
            );
            $making += $rMkPos ? (float) ($rMkPos['m'] ?? 0) : 0.0;
        }

        return $making;
    }

    /**
     * @return array{start:string,end:string,kpi_end:string,period_key:string}
     */
    function auragold_salesperson_period_bounds($period) {
        $today = new \DateTime('today');
        $p = strtolower(trim((string) $period));

        if ($p === 'today') {
            $d = $today->format('Y-m-d');
            return ['start' => $d, 'end' => $d, 'kpi_end' => $d, 'period_key' => 'today'];
        }

        if ($p === 'this_week') {
            $mon = clone $today;
            $mon->modify('monday this week');
            $sun = clone $mon;
            $sun->modify('+6 days');
            $kpiEnd = $today <= $sun ? $today : $sun;
            return [
                'start' => $mon->format('Y-m-d'),
                'end' => $sun->format('Y-m-d'),
                'kpi_end' => $kpiEnd->format('Y-m-d'),
                'period_key' => 'week',
            ];
        }

        if ($p === 'last_month') {
            $first = new \DateTime('first day of last month');
            $last = new \DateTime('last day of last month');
            $ds = $first->format('Y-m-d');
            $de = $last->format('Y-m-d');
            return ['start' => $ds, 'end' => $de, 'kpi_end' => $de, 'period_key' => 'month'];
        }

        // this_month (default)
        $first = new \DateTime($today->format('Y-m-01'));
        $last = new \DateTime($today->format('Y-m-t'));
        $kpiEnd = $today <= $last ? $today : $last;
        return [
            'start' => $first->format('Y-m-d'),
            'end' => $last->format('Y-m-d'),
            'kpi_end' => $kpiEnd->format('Y-m-d'),
            'period_key' => 'month',
        ];
    }

    /**
     * Extra salesperson dashboard: recent invoices, averages, team size.
     *
     * @param string $selectedSp
     * @param string $dateFrom Y-m-d (optional)
     * @param string $dateTo   Y-m-d (optional)
     * @return array<string,mixed>
     */
    function auragold_salesperson_dashboard_extras($selectedSp, $dateFrom = '', $dateTo = '') {
        $out = [
            'recent_invoices' => [],
            'avg_ticket' => 0.0,
            'team_count' => 0,
            'date_from' => '',
            'date_to' => '',
            'is_today' => true,
            'is_single_day' => true,
        ];
        if (!function_exists('getRecord') || !function_exists('getList')) {
            return $out;
        }

        $st  = auragold_dashboard_sale_status_where('si');
        $pst = auragold_dashboard_sale_status_where('psi');
        $spf = auragold_salesperson_sql_filter($selectedSp, 'si');
        $pspf = auragold_salesperson_sql_filter($selectedSp, 'psi');
        $siX = function_exists('auragold_dashboard_si_extra_sql') ? auragold_dashboard_si_extra_sql('si') : '';
        $psiX = function_exists('auragold_dashboard_pos_extra_sql') ? auragold_dashboard_pos_extra_sql('psi') : '';
        $bounds = auragold_retailer_dashboard_date_bounds($dateFrom, $dateTo);
        $start = $bounds['start'];
        $end = $bounds['end'];
        $out['date_from'] = $start;
        $out['date_to'] = $end;
        $out['is_today'] = !empty($bounds['is_today']);
        $out['is_single_day'] = !empty($bounds['is_single_day']);
        $rngKpi = function_exists('auragold_dashboard_si_scope_for_range_sql')
            ? auragold_dashboard_si_scope_for_range_sql('si', $start, $end)
            : '';
        $rngPosKpi = function_exists('auragold_dashboard_pos_scope_for_range_sql')
            ? auragold_dashboard_pos_scope_for_range_sql('psi', $start, $end)
            : '';

        if (auragold_table_exists('tbl_sale_invoices')) {
            $out['recent_invoices'] = getList(
                "SELECT si.id, si.invoice_no, si.customer_name, si.invoice_date, si.grand_total,
                        TRIM(si.sales_person) AS sales_person, 'Sale' AS invoice_source
                 FROM tbl_sale_invoices si
                 WHERE 1=1 $spf $st $rngKpi $siX
                 ORDER BY si.invoice_date DESC, si.id DESC
                 LIMIT 10"
            ) ?: [];
            if (auragold_table_exists('tbl_pos_sale_invoices')) {
                $posRecent = getList(
                    "SELECT psi.id, psi.invoice_no, psi.customer_name, psi.invoice_date, psi.grand_total,
                            TRIM(psi.sales_person) AS sales_person, 'POS' AS invoice_source
                     FROM tbl_pos_sale_invoices psi
                     WHERE 1=1 $pspf $pst $rngPosKpi $psiX
                     ORDER BY psi.invoice_date DESC, psi.id DESC
                     LIMIT 10"
                ) ?: [];
                $out['recent_invoices'] = array_slice(array_merge($out['recent_invoices'], $posRecent), 0, 10);
                usort($out['recent_invoices'], static function ($a, $b) {
                    return strcmp((string) ($b['invoice_date'] ?? ''), (string) ($a['invoice_date'] ?? ''));
                });
                $out['recent_invoices'] = array_slice($out['recent_invoices'], 0, 10);
            }

            $periodTotals = auragold_salesperson_sum_sales_range($selectedSp, $start, $end);
            $out['avg_ticket'] = ($periodTotals['invoices'] ?? 0) > 0
                ? ((float) ($periodTotals['sales'] ?? 0) / (int) $periodTotals['invoices'])
                : 0.0;

            $teamUnionPos = auragold_table_exists('tbl_pos_sale_invoices')
                ? " UNION SELECT TRIM(psi.sales_person) AS sp_name FROM tbl_pos_sale_invoices psi
                     WHERE TRIM(IFNULL(psi.sales_person,'')) <> '' $pst $rngPosKpi $psiX "
                : '';
            $teamRows = getList(
                "SELECT DISTINCT TRIM(sp_name) AS sp FROM (
                    SELECT TRIM(si.sales_person) AS sp_name FROM tbl_sale_invoices si
                     WHERE TRIM(IFNULL(si.sales_person,'')) <> '' $st $rngKpi $siX
                    $teamUnionPos
                 ) names
                 WHERE TRIM(sp_name) <> ''"
            ) ?: [];
            $out['team_count'] = count($teamRows);
        }

        return $out;
    }

    /**
     * JewelSteps-style salesperson dashboard: KPIs, daily chart, leaderboards.
     *
     * @param string $selectedSp
     * @param string $dateFrom Y-m-d (optional)
     * @param string $dateTo   Y-m-d (optional)
     * @return array<string,mixed>
     */
    function auragold_salesperson_dashboard_data($selectedSp, $dateFrom = '', $dateTo = '') {
        $bounds = auragold_retailer_dashboard_date_bounds($dateFrom, $dateTo);
        $empty = [
            'kpi' => [
                'total_sales' => 0.0,
                'total_making' => 0.0,
                'total_invoices' => 0,
                'today_sales' => 0.0,
                'today_making' => 0.0,
            ],
            'chart_labels' => [],
            'chart_values' => [],
            'top_performers' => [],
            'weak_performers' => [],
            'salesperson_options' => [],
            'bounds' => [
                'start' => $bounds['start'],
                'end' => $bounds['end'],
                'kpi_end' => $bounds['end'],
                'period_key' => !empty($bounds['is_today']) ? 'today' : (!empty($bounds['is_single_day']) ? 'day' : 'range'),
            ],
            'date_from' => $bounds['start'],
            'date_to' => $bounds['end'],
            'is_today' => !empty($bounds['is_today']),
            'is_single_day' => !empty($bounds['is_single_day']),
        ];
        if (!function_exists('getRecord') || !function_exists('getList')) {
            return $empty;
        }

        $st  = auragold_dashboard_sale_status_where('si');
        $pst = auragold_dashboard_sale_status_where('psi');
        $spf = auragold_salesperson_sql_filter($selectedSp, 'si');
        $pspf = auragold_salesperson_sql_filter($selectedSp, 'psi');
        $start = $bounds['start'];
        $end = $bounds['end'];
        $today = date('Y-m-d');
        $siX = function_exists('auragold_dashboard_si_extra_sql') ? auragold_dashboard_si_extra_sql('si') : '';
        $psiX = function_exists('auragold_dashboard_pos_extra_sql') ? auragold_dashboard_pos_extra_sql('psi') : '';

        $empty['salesperson_options'] = auragold_salesperson_distinct_names();

        $periodTotals = auragold_salesperson_sum_sales_range($selectedSp, $start, $end);
        $todayTotals = auragold_salesperson_sum_sales_range($selectedSp, $today, $today);
        $periodMaking = auragold_salesperson_sum_making_range($selectedSp, $start, $end);
        $todayMaking = auragold_salesperson_sum_making_range($selectedSp, $today, $today);

        $rngKpi = function_exists('auragold_dashboard_si_scope_for_range_sql')
            ? auragold_dashboard_si_scope_for_range_sql('si', $start, $end)
            : '';
        $rngPosKpi = function_exists('auragold_dashboard_pos_scope_for_range_sql')
            ? auragold_dashboard_pos_scope_for_range_sql('psi', $start, $end)
            : '';
        $siAmt = auragold_dashboard_sale_invoice_amount_expr('si');
        $psiAmt = auragold_dashboard_sale_invoice_amount_expr('psi');

        $chartLabels = [];
        $chartValues = [];
        $chartStart = new \DateTime($start);
        $chartEnd = new \DateTime($end);
        // Cap chart days to avoid huge ranges
        $daySpan = (int) $chartStart->diff($chartEnd)->days + 1;
        if ($daySpan > 92) {
            $chartStart = clone $chartEnd;
            $chartStart->modify('-91 days');
            if ($chartStart->format('Y-m-d') < $start) {
                $chartStart = new \DateTime($start);
            }
        }
        $iter = clone $chartStart;
        while ($iter <= $chartEnd) {
            $ds = $iter->format('Y-m-d');
            $chartLabels[] = $iter->format('D j');
            $rngDs = function_exists('auragold_dashboard_si_scope_for_range_sql')
                ? auragold_dashboard_si_scope_for_range_sql('si', $ds, $ds)
                : '';
            $rngDsPos = function_exists('auragold_dashboard_pos_scope_for_range_sql')
                ? auragold_dashboard_pos_scope_for_range_sql('psi', $ds, $ds)
                : '';
            $rDay = getRecord(
                "SELECT COALESCE(SUM($siAmt),0) AS t FROM tbl_sale_invoices si
                 WHERE 1=1 $spf $st $rngDs $siX"
            );
            $dayTotal = $rDay ? (float) ($rDay['t'] ?? 0) : 0.0;
            if (auragold_table_exists('tbl_pos_sale_invoices')) {
                $rDayPos = getRecord(
                    "SELECT COALESCE(SUM($psiAmt),0) AS t FROM tbl_pos_sale_invoices psi
                     WHERE 1=1 $pspf $pst $rngDsPos $psiX"
                );
                $dayTotal += $rDayPos ? (float) ($rDayPos['t'] ?? 0) : 0.0;
            }
            $chartValues[] = round($dayTotal, 2);
            $iter->modify('+1 day');
        }

        $topUnionPos = auragold_table_exists('tbl_pos_sale_invoices')
            ? " UNION ALL
                SELECT TRIM(psi.sales_person) AS name, $psiAmt AS amount
                FROM tbl_pos_sale_invoices psi
                WHERE TRIM(IFNULL(psi.sales_person,'')) <> '' $pst $rngPosKpi $psiX "
            : '';
        $top = getList(
            "SELECT name, COALESCE(SUM(amount),0) AS amount FROM (
                SELECT TRIM(si.sales_person) AS name, $siAmt AS amount
                FROM tbl_sale_invoices si
                WHERE TRIM(IFNULL(si.sales_person,'')) <> '' $st $rngKpi $siX
                $topUnionPos
             ) perf
             GROUP BY name
             ORDER BY COALESCE(SUM(amount),0) DESC
             LIMIT 10"
        ) ?: [];

        $weak = getList(
            "SELECT name, COALESCE(SUM(amount),0) AS amount FROM (
                SELECT TRIM(si.sales_person) AS name, $siAmt AS amount
                FROM tbl_sale_invoices si
                WHERE TRIM(IFNULL(si.sales_person,'')) <> '' $st $rngKpi $siX
                $topUnionPos
             ) perf
             GROUP BY name
             HAVING COALESCE(SUM(amount),0) > 0
             ORDER BY COALESCE(SUM(amount),0) ASC
             LIMIT 10"
        ) ?: [];

        return [
            'kpi' => [
                'total_sales' => (float) ($periodTotals['sales'] ?? 0),
                'total_making' => $periodMaking,
                'total_invoices' => (int) ($periodTotals['invoices'] ?? 0),
                'today_sales' => (float) ($todayTotals['sales'] ?? 0),
                'today_making' => $todayMaking,
            ],
            'chart_labels' => $chartLabels,
            'chart_values' => $chartValues,
            'top_performers' => $top,
            'weak_performers' => $weak,
            'salesperson_options' => $empty['salesperson_options'],
            'bounds' => $empty['bounds'],
            'date_from' => $start,
            'date_to' => $end,
            'is_today' => !empty($bounds['is_today']),
            'is_single_day' => !empty($bounds['is_single_day']),
        ];
    }
}
