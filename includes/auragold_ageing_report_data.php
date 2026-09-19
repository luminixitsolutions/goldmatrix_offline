<?php

/**
 * Ageing Report — ledger (receivable/payable) + stock serial data for grid + JSON API.
 */

declare(strict_types=1);

/**
 * @return array{scope_branch_id:int, main_bid:int}
 */
function auragold_ageing_branch_context(mysqli $conn): array
{
    $scope_branch_id = 0;
    if (function_exists('auragold_effective_branch_id')) {
        $scope_branch_id = (int) auragold_effective_branch_id();
    }
    if ($scope_branch_id <= 0) {
        $wb = isset($_SESSION['working_branch_id']) ? (int) $_SESSION['working_branch_id'] : 0;
        if ($wb <= 0 && !empty($_SESSION['branch_id'])) {
            $wb = (int) $_SESSION['branch_id'];
        }
        if ($wb > 0) {
            $scope_branch_id = $wb;
        }
    }
    $main_bid = function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0;
    return ['scope_branch_id' => $scope_branch_id, 'main_bid' => $main_bid];
}

/** SQL AND fragment for sale / pos invoice branch (matches other reports). */
function auragold_ageing_invoice_branch_and(
    mysqli $conn,
    string $table,
    int $scope_branch_id,
    int $main_bid
): string {
    if (!function_exists('auragold_tbl_has_column') || !auragold_tbl_has_column($conn, $table, 'branch_id')) {
        return '';
    }
    if ($scope_branch_id <= 0) {
        return '';
    }
    if ($main_bid > 0 && $scope_branch_id === $main_bid) {
        return ' AND (branch_id = ' . (int) $scope_branch_id . ' OR branch_id IS NULL OR branch_id = 0)';
    }
    return ' AND COALESCE(branch_id, 0) = ' . (int) $scope_branch_id;
}

/** tbl_stock branch predicate (no leading AND). */
function auragold_ageing_stock_branch_predicate(mysqli $conn, int $bid, string $alias = 's'): string
{
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

function auragold_ageing_gas_tbl_exists(mysqli $conn, string $table): bool
{
    $t = mysqli_real_escape_string($conn, $table);
    $r = @mysqli_query($conn, "SHOW TABLES LIKE '$t'");
    $ok = $r && mysqli_num_rows($r) > 0;
    if ($r) {
        mysqli_free_result($r);
    }
    return (bool) $ok;
}

/** @return array<string, array> */
function auragold_ageing_sj_columns(mysqli $conn): array
{
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

function auragold_ageing_sj_has(array $sj_cols, string $name): bool
{
    return isset($sj_cols[strtolower($name)]);
}

function auragold_ageing_sj_active_sql(array $sj_cols, string $tblAlias = ''): string
{
    if (!auragold_ageing_sj_has($sj_cols, 'status')) {
        return '1=1';
    }
    $p = ($tblAlias !== '' && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $tblAlias)) ? ($tblAlias . '.') : '';
    $type = strtolower((string) ($sj_cols['status']['Type'] ?? ''));
    if (strpos($type, 'char') !== false || strpos($type, 'text') !== false || strpos($type, 'enum') !== false) {
        return '(' . $p . "status = 'active' OR LOWER(TRIM(CAST(" . $p . 'status AS CHAR))) = \'active\')';
    }
    return '(' . $p . 'status = 1 OR ' . $p . 'status = \'1\')';
}

function auragold_ageing_sj_sel(array $sj_cols, string $field, string $alias): string
{
    if (auragold_ageing_sj_has($sj_cols, $field)) {
        return 'sj1.`' . str_replace('`', '', $field) . '` AS `' . str_replace('`', '', $alias) . '`';
    }
    return 'NULL AS `' . str_replace('`', '', $alias) . '`';
}

function auragold_ageing_metal_name_expr(mysqli $conn): string
{
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
        return "COALESCE(NULLIF(TRIM(m.display_name), ''), NULLIF(TRIM(m.system_name), ''), '')";
    }
    if ($metal_has_display) {
        return "COALESCE(NULLIF(TRIM(m.display_name), ''), '')";
    }
    if ($metal_has_system) {
        return "COALESCE(NULLIF(TRIM(m.system_name), ''), '')";
    }
    return "''";
}

/**
 * @param array{
 *   aging_date:string,
 *   pr_type:string,
 *   vl_wise:string,
 *   ledger_customer_id?:int,
 *   account_ledger?:string,
 *   search?:string,
 *   page:int,
 *   per_page:int,
 *   unlimited?:bool
 * } $params
 * @return array{error?:string, rows: array<int,array>, total:int, totals: array<string,float>}
 */
function auragold_ageing_ledger_fetch(mysqli $conn, array $params): array
{
    $aging = isset($params['aging_date']) ? trim((string) $params['aging_date']) : '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $aging)) {
        return ['error' => 'Invalid aging_date', 'rows' => [], 'total' => 0, 'totals' => []];
    }
    $aging_esc = mysqli_real_escape_string($conn, $aging);

    $pr_type = strtolower(trim((string) ($params['pr_type'] ?? 'receivable')));
    if ($pr_type !== 'receivable' && $pr_type !== 'payable') {
        $pr_type = 'receivable';
    }

    $vl_wise = strtolower(trim((string) ($params['vl_wise'] ?? 'voucher')));
    if ($vl_wise !== 'ledger') {
        $vl_wise = 'voucher';
    }

    $cid_filter = isset($params['ledger_customer_id']) ? (int) $params['ledger_customer_id'] : 0;

    $account_ledger_raw = isset($params['account_ledger']) ? trim((string) $params['account_ledger']) : '';
    if (strlen($account_ledger_raw) > 200) {
        $account_ledger_raw = substr($account_ledger_raw, 0, 200);
    }

    $search_raw = isset($params['search']) ? trim((string) $params['search']) : '';
    $search_esc = $search_raw !== '' ? mysqli_real_escape_string($conn, $search_raw) : '';
    $search_sql = '';
    if ($search_esc !== '') {
        $search_sql = " AND (
            t.customer_name LIKE '%$search_esc%'
            OR t.invoice_no LIKE '%$search_esc%'
            OR CAST(t.acct_no AS CHAR) LIKE '%$search_esc%'
        )";
    }

    $cust_filter_sale = '';
    $cust_filter_pos = '';
    $cust_filter_pi = '';
    if ($cid_filter > 0) {
        $ci = (int) $cid_filter;
        $cust_row = getRecord('SELECT TRIM(IFNULL(name,\'\')) AS nm FROM tbl_customers WHERE id = ' . $ci . ' LIMIT 1');
        $cn_esc = mysqli_real_escape_string($conn, (string) (($cust_row && trim((string) ($cust_row['nm'] ?? '')) !== '') ? trim((string) $cust_row['nm']) : '__no_match__'));
        $cust_filter_sale = ' AND (si.customer_id = ' . $ci . " OR TRIM(si.customer_name) = '$cn_esc')";
        $cust_filter_pos = ' AND (psi.customer_id = ' . $ci . " OR TRIM(COALESCE(psi.customer_name,'')) = '$cn_esc')";
        $cust_filter_pi = ' AND (pi.supplier_id = ' . $ci . " OR TRIM(pi.supplier_name) = '$cn_esc')";
    }

    $party_extra_sale = '';
    $party_extra_pos = '';
    $party_extra_pi = '';
    if ($cid_filter <= 0 && $account_ledger_raw !== '') {
        $ap = mysqli_real_escape_string($conn, $account_ledger_raw);
        $party_extra_sale = " AND (TRIM(si.customer_name) LIKE '%$ap%' OR TRIM(IFNULL(c.name,'')) LIKE '%$ap%')";
        $party_extra_pos = " AND (TRIM(COALESCE(psi.customer_name,'')) LIKE '%$ap%' OR TRIM(IFNULL(c2.name,'')) LIKE '%$ap%')";
        $party_extra_pi = " AND TRIM(pi.supplier_name) LIKE '%$ap%'";
    }

    /** Invoice amount still due: balance_amt when meaningful; elif balance reads 0 but grand−paid > 0 use computed; elif NULL use grand−paid */
    $out_si_sql = '(GREATEST(0, CAST((CASE
        WHEN si.balance_amt IS NULL THEN (COALESCE(si.grand_total, 0) - COALESCE(si.paid_amt, 0))
        WHEN ABS(CAST(si.balance_amt AS DECIMAL(18,4))) < 0.0001
            AND (COALESCE(si.grand_total, 0) - COALESCE(si.paid_amt, 0)) > 0.005
            THEN (COALESCE(si.grand_total, 0) - COALESCE(si.paid_amt, 0))
        ELSE si.balance_amt
    END) AS DECIMAL(18,4))))';

    $out_psi_sql = '(GREATEST(0, CAST((CASE
        WHEN psi.balance_amt IS NULL THEN (COALESCE(psi.grand_total, 0) - COALESCE(psi.paid_amt, 0))
        WHEN ABS(CAST(psi.balance_amt AS DECIMAL(18,4))) < 0.0001
            AND (COALESCE(psi.grand_total, 0) - COALESCE(psi.paid_amt, 0)) > 0.005
            THEN (COALESCE(psi.grand_total, 0) - COALESCE(psi.paid_amt, 0))
        ELSE psi.balance_amt
    END) AS DECIMAL(18,4))))';

    $out_pi_sql = '(GREATEST(0, CAST((CASE
        WHEN pi.balance_amt IS NULL THEN (COALESCE(pi.grand_total, 0) - COALESCE(pi.paid_amt, 0))
        WHEN ABS(CAST(pi.balance_amt AS DECIMAL(18,4))) < 0.0001
            AND (COALESCE(pi.grand_total, 0) - COALESCE(pi.paid_amt, 0)) > 0.005
            THEN (COALESCE(pi.grand_total, 0) - COALESCE(pi.paid_amt, 0))
        ELSE pi.balance_amt
    END) AS DECIMAL(18,4))))';

    $ctx = auragold_ageing_branch_context($conn);
    $scope_bid = (int) $ctx['scope_branch_id'];
    $main_bid = (int) $ctx['main_bid'];

    $parts = [];
    $status_ok_si = "(si.status IS NULL OR TRIM(si.status) = '' OR LOWER(TRIM(si.status)) NOT IN ('cancelled','void','canceled'))";
    $status_ok_psi = "(psi.status IS NULL OR TRIM(psi.status) = '' OR LOWER(TRIM(psi.status)) NOT IN ('cancelled','void','canceled'))";
    $status_ok_pi = "(pi.status IS NULL OR TRIM(pi.status) = '' OR LOWER(TRIM(pi.status)) NOT IN ('cancelled','void','canceled'))";

    if ($pr_type === 'receivable') {
        $br_si = auragold_ageing_invoice_branch_and($conn, 'tbl_sale_invoices', $scope_bid, $main_bid);
        $br_psi = auragold_ageing_invoice_branch_and($conn, 'tbl_pos_sale_invoices', $scope_bid, $main_bid);

        if (auragold_ageing_gas_tbl_exists($conn, 'tbl_sale_invoices')) {
            $parts[] = "
                SELECT
                    'sale' AS src,
                    TRIM(IFNULL(si.customer_name,'')) AS customer_name,
                    si.invoice_no AS invoice_no,
                    DATE(si.invoice_date) AS inv_date,
                    COALESCE(NULLIF(TRIM(IFNULL(c.bank_account_no,'')), ''), CAST(IFNULL(si.customer_id,0) AS CHAR)) AS acct_no,
                    GREATEST(0, DATEDIFF('$aging_esc', DATE(si.invoice_date))) AS age_days,
                    $out_si_sql AS bal_amt
                FROM tbl_sale_invoices si
                LEFT JOIN tbl_customers c ON c.id = si.customer_id
                WHERE $status_ok_si
                  AND DATE(si.invoice_date) <= '$aging_esc'
                  AND $out_si_sql > 0.005
                  $br_si
                  $cust_filter_sale
                  $party_extra_sale
            ";
        }
        if (auragold_ageing_gas_tbl_exists($conn, 'tbl_pos_sale_invoices')) {
            $parts[] = "
                SELECT
                    'pos' AS src,
                    TRIM(COALESCE(NULLIF(psi.customer_name,''), IFNULL(c2.name,''))) AS customer_name,
                    psi.invoice_no AS invoice_no,
                    DATE(psi.invoice_date) AS inv_date,
                    COALESCE(NULLIF(TRIM(IFNULL(c2.bank_account_no,'')), ''), CAST(IFNULL(psi.customer_id,0) AS CHAR)) AS acct_no,
                    GREATEST(0, DATEDIFF('$aging_esc', DATE(psi.invoice_date))) AS age_days,
                    $out_psi_sql AS bal_amt
                FROM tbl_pos_sale_invoices psi
                LEFT JOIN tbl_customers c2 ON c2.id = psi.customer_id
                WHERE $status_ok_psi
                  AND DATE(psi.invoice_date) <= '$aging_esc'
                  AND $out_psi_sql > 0.005
                  $br_psi
                  $cust_filter_pos
                  $party_extra_pos
            ";
        }
    } else {
        $br_pi = auragold_ageing_invoice_branch_and($conn, 'tbl_purchase_invoices', $scope_bid, $main_bid);
        if (auragold_ageing_gas_tbl_exists($conn, 'tbl_purchase_invoices')) {
            $parts[] = "
                SELECT
                    'pi' AS src,
                    TRIM(IFNULL(pi.supplier_name,'')) AS customer_name,
                    pi.invoice_no AS invoice_no,
                    DATE(pi.invoice_date) AS inv_date,
                    CAST(IFNULL(pi.supplier_id,0) AS CHAR) AS acct_no,
                    GREATEST(0, DATEDIFF('$aging_esc', DATE(pi.invoice_date))) AS age_days,
                    $out_pi_sql AS bal_amt
                FROM tbl_purchase_invoices pi
                WHERE $status_ok_pi
                  AND DATE(pi.invoice_date) <= '$aging_esc'
                  AND $out_pi_sql > 0.005
                  $br_pi
                  $cust_filter_pi
                  $party_extra_pi
            ";
        }
    }

    if ($parts === []) {
        return ['rows' => [], 'total' => 0, 'totals' => ['d1' => 0.0, 'd2' => 0.0, 'd3' => 0.0, 'd4' => 0.0, 'd5' => 0.0, 'total' => 0.0]];
    }

    $inner_union = '(' . implode(' UNION ALL ', $parts) . ')';

    $base_from = "
        FROM $inner_union t
        WHERE 1=1
        $search_sql
    ";

    $tot_row = getRecord("
        SELECT
            SUM(CASE WHEN t.age_days BETWEEN 0 AND 30 THEN t.bal_amt ELSE 0 END) AS d1,
            SUM(CASE WHEN t.age_days BETWEEN 31 AND 60 THEN t.bal_amt ELSE 0 END) AS d2,
            SUM(CASE WHEN t.age_days BETWEEN 61 AND 90 THEN t.bal_amt ELSE 0 END) AS d3,
            SUM(CASE WHEN t.age_days BETWEEN 91 AND 120 THEN t.bal_amt ELSE 0 END) AS d4,
            SUM(CASE WHEN t.age_days >= 121 THEN t.bal_amt ELSE 0 END) AS d5,
            SUM(t.bal_amt) AS tot
        $base_from
    ");

    $totals = [
        'd1' => round((float) ($tot_row['d1'] ?? 0), 2),
        'd2' => round((float) ($tot_row['d2'] ?? 0), 2),
        'd3' => round((float) ($tot_row['d3'] ?? 0), 2),
        'd4' => round((float) ($tot_row['d4'] ?? 0), 2),
        'd5' => round((float) ($tot_row['d5'] ?? 0), 2),
        'total' => round((float) ($tot_row['tot'] ?? 0), 2),
    ];

    if ($vl_wise === 'voucher') {
        $cnt = getRecord("SELECT COUNT(*) AS c $base_from");
        $total = (int) ($cnt['c'] ?? 0);

        $page = max(1, (int) ($params['page'] ?? 1));
        $raw_per = $params['per_page'] ?? 25;
        $unlimited = !empty($params['unlimited']) || $raw_per === 'all' || (int) $raw_per <= 0;
        $per = $unlimited ? 500000 : max(1, min(500, (int) $raw_per));
        $offset = $unlimited ? 0 : ($page - 1) * $per;
        $lim = $unlimited ? '' : ' LIMIT ' . $per . ' OFFSET ' . $offset;

        $sql_rows = "
            SELECT
                t.customer_name AS ledger,
                CASE t.src WHEN 'pi' THEN 'Purchase Invoice' WHEN 'pos' THEN 'POS Sale' ELSE 'Sale Invoice' END AS voucher,
                CAST(t.acct_no AS CHAR) AS acct_no,
                t.invoice_no AS invoice,
                DATE_FORMAT(t.inv_date, '%Y-%m-%d') AS inv_date_disp,
                CASE WHEN t.age_days BETWEEN 0 AND 30 THEN t.bal_amt ELSE 0 END AS d1,
                CASE WHEN t.age_days BETWEEN 31 AND 60 THEN t.bal_amt ELSE 0 END AS d2,
                CASE WHEN t.age_days BETWEEN 61 AND 90 THEN t.bal_amt ELSE 0 END AS d3,
                CASE WHEN t.age_days BETWEEN 91 AND 120 THEN t.bal_amt ELSE 0 END AS d4,
                CASE WHEN t.age_days >= 121 THEN t.bal_amt ELSE 0 END AS d5,
                t.bal_amt AS total_amt
            $base_from
            ORDER BY t.customer_name ASC, t.inv_date ASC, t.invoice_no ASC
            $lim
        ";

        $raw = getList($sql_rows);
        if (!is_array($raw)) {
            $raw = [];
        }

        $out = [];
        foreach ($raw as $r) {
            $out[] = [
                'ledger' => (string) ($r['ledger'] ?? ''),
                'voucher' => (string) ($r['voucher'] ?? ''),
                'acct_no' => (string) ($r['acct_no'] ?? ''),
                'invoice' => (string) ($r['invoice'] ?? ''),
                'date' => (string) ($r['inv_date_disp'] ?? ''),
                'd1' => round((float) ($r['d1'] ?? 0), 2),
                'd2' => round((float) ($r['d2'] ?? 0), 2),
                'd3' => round((float) ($r['d3'] ?? 0), 2),
                'd4' => round((float) ($r['d4'] ?? 0), 2),
                'd5' => round((float) ($r['d5'] ?? 0), 2),
                'total' => round((float) ($r['total_amt'] ?? 0), 2),
            ];
        }

        return ['rows' => $out, 'total' => $total, 'totals' => $totals];
    }

    $ledger_group_from = "
        FROM $inner_union t
        WHERE 1=1
        $search_sql
        GROUP BY t.customer_name
    ";

    $cnt_row = getRecord("SELECT COUNT(*) AS c FROM (SELECT t.customer_name AS cn $ledger_group_from) cnt");
    $total = (int) ($cnt_row['c'] ?? 0);

    $page = max(1, (int) ($params['page'] ?? 1));
    $raw_per = $params['per_page'] ?? 25;
    $unlimited = !empty($params['unlimited']) || $raw_per === 'all' || (int) $raw_per <= 0;
    $per = $unlimited ? 500000 : max(1, min(500, (int) $raw_per));
    $offset = $unlimited ? 0 : ($page - 1) * $per;
    $lim = $unlimited ? '' : ' LIMIT ' . $per . ' OFFSET ' . $offset;

    $sql_rows = "
        SELECT
            t.customer_name AS ledger,
            '—' AS voucher,
            MAX(CAST(t.acct_no AS CHAR)) AS acct_no,
            '' AS invoice,
            DATE_FORMAT(MIN(t.inv_date), '%Y-%m-%d') AS inv_date_disp,
            SUM(CASE WHEN t.age_days BETWEEN 0 AND 30 THEN t.bal_amt ELSE 0 END) AS d1,
            SUM(CASE WHEN t.age_days BETWEEN 31 AND 60 THEN t.bal_amt ELSE 0 END) AS d2,
            SUM(CASE WHEN t.age_days BETWEEN 61 AND 90 THEN t.bal_amt ELSE 0 END) AS d3,
            SUM(CASE WHEN t.age_days BETWEEN 91 AND 120 THEN t.bal_amt ELSE 0 END) AS d4,
            SUM(CASE WHEN t.age_days >= 121 THEN t.bal_amt ELSE 0 END) AS d5,
            SUM(t.bal_amt) AS total_amt
        $ledger_group_from
        ORDER BY t.customer_name ASC
        $lim
    ";

    $raw = getList($sql_rows);
    if (!is_array($raw)) {
        $raw = [];
    }

    $out = [];
    foreach ($raw as $r) {
        $out[] = [
            'ledger' => (string) ($r['ledger'] ?? ''),
            'voucher' => '—',
            'acct_no' => (string) ($r['acct_no'] ?? ''),
            'invoice' => '',
            'date' => (string) ($r['inv_date_disp'] ?? ''),
            'd1' => round((float) ($r['d1'] ?? 0), 2),
            'd2' => round((float) ($r['d2'] ?? 0), 2),
            'd3' => round((float) ($r['d3'] ?? 0), 2),
            'd4' => round((float) ($r['d4'] ?? 0), 2),
            'd5' => round((float) ($r['d5'] ?? 0), 2),
            'total' => round((float) ($r['total_amt'] ?? 0), 2),
        ];
    }

    return ['rows' => $out, 'total' => $total, 'totals' => $totals];
}

/**
 * @param array{
 *   aging_date:string,
 *   product_id?:int,
 *   search?:string,
 *   page:int,
 *   per_page:int,
 *   unlimited?:bool
 * } $params
 * @return array{error?:string, rows: array<int,array>, total:int, totals: array<string,float>}
 */
function auragold_ageing_stock_fetch(mysqli $conn, array $params): array
{
    $aging = isset($params['aging_date']) ? trim((string) $params['aging_date']) : '';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $aging)) {
        return ['error' => 'Invalid aging_date', 'rows' => [], 'total' => 0, 'totals' => []];
    }
    $aging_esc = mysqli_real_escape_string($conn, $aging);

    if (!auragold_ageing_gas_tbl_exists($conn, 'tbl_stock')) {
        return ['error' => 'Stock table not available', 'rows' => [], 'total' => 0, 'totals' => []];
    }

    $ctx = auragold_ageing_branch_context($conn);
    $scope_bid = (int) $ctx['scope_branch_id'];

    $product_id = isset($params['product_id']) ? (int) $params['product_id'] : 0;

    $search_raw = isset($params['search']) ? trim((string) $params['search']) : '';
    $search_esc = $search_raw !== '' ? mysqli_real_escape_string($conn, $search_raw) : '';

    $gas_stk_in_types_sql = "'opening','purchase','stock_journal','balance','sale_return'";

    $inner_where = [
        's.status = 1',
        "(s.barcode IS NOT NULL AND TRIM(COALESCE(s.barcode,'')) <> '')",
    ];
    $br_pred = auragold_ageing_stock_branch_predicate($conn, $scope_bid, 's');
    if ($br_pred !== '') {
        $inner_where[] = $br_pred;
    }
    if ($product_id > 0) {
        $inner_where[] = 's.product_id = ' . $product_id;
    }
    $inner_sql = implode(' AND ', $inner_where);

    $agg_subquery = "
        SELECT s.barcode, s.branch_id,
            (SUM(CASE WHEN s.stock_type IN ($gas_stk_in_types_sql) THEN COALESCE(NULLIF(s.current_qty, 0), s.opening_qty, 0) ELSE 0 END)
             - SUM(CASE WHEN s.stock_type = 'outward' THEN COALESCE(NULLIF(s.current_qty, 0), s.opening_qty, 0) ELSE 0 END)) AS bal_qty,
            (SUM(CASE WHEN s.stock_type IN ($gas_stk_in_types_sql) THEN COALESCE(NULLIF(s.current_weight, 0), s.opening_weight, 0) ELSE 0 END)
             - SUM(CASE WHEN s.stock_type = 'outward' THEN COALESCE(NULLIF(s.current_weight, 0), s.opening_weight, 0) ELSE 0 END)) AS bal_wt,
            COALESCE(MAX(CASE WHEN s.stock_type IN ($gas_stk_in_types_sql) THEN s.id END), MAX(s.id)) AS pick_id
        FROM tbl_stock s
        WHERE $inner_sql
        GROUP BY s.barcode, s.branch_id
        HAVING (ABS(bal_qty) > 0.000001 OR ABS(bal_wt) > 0.000001)
    ";

    $has_sj = auragold_ageing_gas_tbl_exists($conn, 'tbl_stock_journal');
    $sj_cols = $has_sj ? auragold_ageing_sj_columns($conn) : [];
    $sj_active_unqualified = auragold_ageing_sj_active_sql($sj_cols, '');
    $metal_name_expr = auragold_ageing_metal_name_expr($conn);

    $sj_join_sql = '';
    if ($has_sj && auragold_ageing_sj_has($sj_cols, 'barcode') && auragold_ageing_sj_has($sj_cols, 'id')) {
        $parts = [
            auragold_ageing_sj_sel($sj_cols, 'barcode', 'sj_barcode'),
            auragold_ageing_sj_sel($sj_cols, 'voucher_type', 'sj_voucher_type'),
            auragold_ageing_sj_sel($sj_cols, 'invoice_no', 'sj_invoice_no'),
            auragold_ageing_sj_sel($sj_cols, 'location', 'sj_location'),
            auragold_ageing_sj_sel($sj_cols, 'gross_weight', 'sj_gross_weight'),
            auragold_ageing_sj_sel($sj_cols, 'net_weight', 'sj_net_weight'),
            auragold_ageing_sj_sel($sj_cols, 'purity_weight', 'sj_purity_weight'),
            auragold_ageing_sj_sel($sj_cols, 'pure_weight', 'sj_pure_weight'),
            auragold_ageing_sj_sel($sj_cols, 'final_weight', 'sj_final_weight'),
            auragold_ageing_sj_sel($sj_cols, 'quantity', 'sj_quantity'),
            auragold_ageing_sj_sel($sj_cols, 'karat', 'sj_karat'),
            auragold_ageing_sj_sel($sj_cols, 'rfid_code', 'sj_rfid_code'),
        ];
        $sj_sel = implode(",\n            ", $parts);
        $sj_join_sql = "
        LEFT JOIN (
            SELECT
                $sj_sel
            FROM tbl_stock_journal sj1
            INNER JOIN (
                SELECT barcode, MAX(id) AS max_id
                FROM tbl_stock_journal
                WHERE ($sj_active_unqualified) AND barcode IS NOT NULL AND TRIM(barcode) <> ''
                GROUP BY barcode
            ) sjmx ON sjmx.max_id = sj1.id
        ) sj ON (sj.sj_barcode COLLATE utf8mb4_general_ci) = (s.barcode COLLATE utf8mb4_general_ci)
            AND s.barcode IS NOT NULL AND TRIM(COALESCE(s.barcode,'')) <> ''
        ";
    }

    $first_in_sql = 'NULL';
    if ($has_sj && auragold_ageing_sj_has($sj_cols, 'sj_date') && auragold_ageing_sj_has($sj_cols, 'barcode')) {
        $st_act = auragold_ageing_sj_active_sql($sj_cols, 'sjf');
        $first_in_sql = "(SELECT MIN(DATE(sjf.sj_date)) FROM tbl_stock_journal sjf
            WHERE sjf.barcode = s.barcode AND ($st_act)
            AND COALESCE(sjf.quantity,0) > 0
            AND DATE(sjf.sj_date) <= '$aging_esc')";
    }

    $where_search = '';
    if ($search_esc !== '') {
        $where_search = " AND (
            s.barcode LIKE '%$search_esc%'
            OR IFNULL(p.article,'') LIKE '%$search_esc%'
            OR IFNULL(p.name,'') LIKE '%$search_esc%'
            OR IFNULL(b.name,'') LIKE '%$search_esc%'
            OR IFNULL(sj.sj_rfid_code,'') LIKE '%$search_esc%'
            OR IFNULL(sj.sj_invoice_no,'') LIKE '%$search_esc%'
        )";
    }

    $base_from = "
        FROM ($agg_subquery) bal
        INNER JOIN tbl_stock s ON s.id = bal.pick_id
        LEFT JOIN tbl_branches b ON s.branch_id = b.id
        LEFT JOIN tbl_metal m ON s.metal_id = m.id
        LEFT JOIN tbl_products p ON s.product_id = p.id
        LEFT JOIN tbl_product_characteristics pc ON s.product_characteristic_id = pc.id
        $sj_join_sql
        WHERE (bal.bal_qty > 0.000001 OR bal.bal_wt > 0.000001)
        $where_search
    ";

    $cnt = getRecord("SELECT COUNT(*) AS c $base_from");
    $total = (int) ($cnt['c'] ?? 0);

    $tot_row = getRecord("
        SELECT
            COALESCE(SUM(bal.bal_qty), 0) AS sum_qty,
            COALESCE(SUM(COALESCE(sj.sj_gross_weight, s.current_weight, s.opening_weight, 0)), 0) AS sum_gross,
            COALESCE(SUM(COALESCE(sj.sj_purity_weight, sj.sj_pure_weight, 0)), 0) AS sum_purity,
            COALESCE(SUM(COALESCE(sj.sj_net_weight, 0)), 0) AS sum_net,
            COALESCE(SUM(COALESCE(sj.sj_final_weight, s.final_weight, 0)), 0) AS sum_final
        $base_from
    ");

    $totals = [
        'qty' => round((float) ($tot_row['sum_qty'] ?? 0), 3),
        'gross_wt' => round((float) ($tot_row['sum_gross'] ?? 0), 3),
        'purity_wt' => round((float) ($tot_row['sum_purity'] ?? 0), 3),
        'net_wt' => round((float) ($tot_row['sum_net'] ?? 0), 3),
        'final_wt' => round((float) ($tot_row['sum_final'] ?? 0), 3),
    ];

    $page = max(1, (int) ($params['page'] ?? 1));
    $raw_per = $params['per_page'] ?? 25;
    $unlimited = !empty($params['unlimited']) || $raw_per === 'all' || (int) $raw_per <= 0;
    $per = $unlimited ? 500000 : max(1, min(500, (int) $raw_per));
    $offset = $unlimited ? 0 : ($page - 1) * $per;
    $lim = $unlimited ? '' : ' LIMIT ' . $per . ' OFFSET ' . $offset;

    $sql_rows = "
        SELECT
            IFNULL(b.name,'') AS branch_name,
            (COALESCE(sj.sj_karat, pc.carat, 0) + 0) AS carat_num,
            $metal_name_expr AS metal_name,
            IFNULL(p.article,'') AS article,
            IFNULL(sj.sj_rfid_code,'') AS rfid_code,
            s.barcode AS barcode,
            bal.bal_qty AS qty,
            IFNULL(sj.sj_location,'') AS location_name,
            $first_in_sql AS first_in_date,
            COALESCE(sj.sj_gross_weight, s.current_weight, s.opening_weight, 0) AS gross_wt,
            COALESCE(sj.sj_purity_weight, sj.sj_pure_weight, 0) AS purity_wt,
            COALESCE(sj.sj_net_weight, 0) AS net_wt,
            COALESCE(sj.sj_final_weight, s.final_weight, 0) AS final_wt,
            IFNULL(sj.sj_voucher_type,'') AS voucher_type,
            IFNULL(sj.sj_invoice_no,'') AS invoice_no,
            DATE(s.created_at) AS stock_created
        $base_from
        ORDER BY s.barcode ASC, s.branch_id ASC
        $lim
    ";

    $raw = getList($sql_rows);
    if (!is_array($raw)) {
        $raw = [];
    }

    $ts_aging = strtotime($aging . ' 12:00:00') ?: time();
    $out = [];
    foreach ($raw as $r) {
        $fid = isset($r['first_in_date']) && $r['first_in_date'] ? (string) $r['first_in_date'] : '';
        if ($fid === '' || $fid === '0000-00-00') {
            $fid = isset($r['stock_created']) ? (string) $r['stock_created'] : '';
        }
        $age_days = 0;
        if ($fid !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fid)) {
            $ts_f = strtotime($fid . ' 12:00:00');
            if ($ts_f) {
                $age_days = (int) floor(($ts_aging - $ts_f) / 86400);
                if ($age_days < 0) {
                    $age_days = 0;
                }
            }
        }

        $cn = isset($r['carat_num']) ? (float) $r['carat_num'] : 0.0;
        $carat_str = ($cn !== 0.0) ? rtrim(rtrim(number_format($cn, 3, '.', ''), '0'), '.') : '';

        $out[] = [
            'branch' => (string) ($r['branch_name'] ?? ''),
            'carat' => $carat_str,
            'metal' => (string) ($r['metal_name'] ?? ''),
            'product_code' => (string) ($r['article'] ?? ''),
            'rfid_code' => (string) ($r['rfid_code'] ?? ''),
            'barcode' => (string) ($r['barcode'] ?? ''),
            'qty' => round((float) ($r['qty'] ?? 0), 3),
            'location' => (string) ($r['location_name'] ?? ''),
            'age' => $age_days,
            'gross_wt' => round((float) ($r['gross_wt'] ?? 0), 3),
            'purity_wt' => round((float) ($r['purity_wt'] ?? 0), 3),
            'net_wt' => round((float) ($r['net_wt'] ?? 0), 3),
            'final_wt' => round((float) ($r['final_wt'] ?? 0), 3),
            'voucher_type' => (string) ($r['voucher_type'] ?? ''),
            'invoice_no' => (string) ($r['invoice_no'] ?? ''),
        ];
    }

    return ['rows' => $out, 'total' => $total, 'totals' => $totals];
}
