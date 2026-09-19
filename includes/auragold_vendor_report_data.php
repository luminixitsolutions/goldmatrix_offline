<?php
/**
 * Vendor Report — products assigned to vendors with opening / sale / purchase / balance by metal.
 */
require_once __DIR__ . '/auragold_sale_analysis_data.php';
require_once __DIR__ . '/auragold_branch_data_scope.php';
require_once __DIR__ . '/auragold_product_branch_local_schema.php';

if (!function_exists('auragold_vendor_report_fmt_wt')) {
    function auragold_vendor_report_fmt_wt($v): string {
        return number_format((float) $v, 3, '.', '');
    }
}

if (!function_exists('auragold_vendor_report_fmt_qty')) {
    function auragold_vendor_report_fmt_qty($v): string {
        $n = (float) $v;
        if (abs($n - round($n)) < 0.0001) {
            return (string) (int) round($n);
        }

        return number_format($n, 2, '.', '');
    }
}

if (!function_exists('auragold_vendor_report_branch_sql')) {
    /** @return array{pc:string,stock:string} */
    function auragold_vendor_report_branch_sql(mysqli $conn): array {
        $eff = function_exists('auragold_effective_branch_id') ? (int) auragold_effective_branch_id() : 0;
        if ($eff <= 0) {
            return ['pc' => '', 'stock' => ''];
        }
        $main = function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0;
        if ($main > 0 && $eff === $main) {
            return [
                'pc'    => " AND (pc.branch_id = {$eff} OR pc.branch_id IS NULL OR pc.branch_id = 0) ",
                'stock' => " AND (s.branch_id = {$eff} OR s.branch_id IS NULL OR s.branch_id = 0) ",
            ];
        }

        return [
            'pc'    => " AND COALESCE(pc.branch_id, 0) = {$eff} ",
            'stock' => " AND COALESCE(s.branch_id, 0) = {$eff} ",
        ];
    }
}

if (!function_exists('auragold_vendor_report_filter_lists')) {
    /**
     * @return array{vendors:array<int,array>,products:array<int,array>,metals:array<int,array>}
     */
    function auragold_vendor_report_filter_lists(mysqli $conn): array {
        auragold_ensure_product_vendor_id_column($conn);

        $vendors = getList(
            "SELECT DISTINCT c.id, c.name
             FROM tbl_customers c
             INNER JOIN tbl_products p ON p.vendor_id = c.id AND p.status = 1
             WHERE c.status = 1 AND COALESCE(p.vendor_id, 0) > 0
             ORDER BY c.name ASC"
        );
        if (!is_array($vendors)) {
            $vendors = [];
        }

        $products = getList(
            "SELECT p.id, p.name, COALESCE(p.article, '') AS article
             FROM tbl_products p
             WHERE p.status = 1 AND COALESCE(p.vendor_id, 0) > 0
             ORDER BY p.name ASC"
        );
        if (!is_array($products)) {
            $products = [];
        }

        $metals_sql_suffix = '';
        if (function_exists('auragold_master_list_sql_suffix')) {
            $metals_sql_suffix = auragold_master_list_sql_suffix($conn, 'tbl_metal');
        }
        $metals = getList(
            "SELECT id, display_name FROM tbl_metal WHERE status = 1 {$metals_sql_suffix} ORDER BY display_name ASC"
        );
        if (!is_array($metals)) {
            $metals = [];
        }

        return [
            'vendors'  => $vendors,
            'products' => $products,
            'metals'   => $metals,
        ];
    }
}

if (!function_exists('auragold_vendor_report_fetch_rows')) {
    /**
     * @param array<string,mixed> $filters vendor_id, product_id, metal_id, search, from_ymd, to_ymd
     * @return array<int,array<string,string>>
     */
    function auragold_vendor_report_fetch_rows(mysqli $conn, array $filters = []): array {
        auragold_ensure_product_vendor_id_column($conn);

        $chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_products'");
        if (!$chk || mysqli_num_rows($chk) === 0) {
            if ($chk) {
                mysqli_free_result($chk);
            }

            return [];
        }
        mysqli_free_result($chk);

        $vendor_id  = max(0, (int) ($filters['vendor_id'] ?? 0));
        $product_id = max(0, (int) ($filters['product_id'] ?? 0));
        $metal_id   = max(0, (int) ($filters['metal_id'] ?? 0));
        $search     = trim((string) ($filters['search'] ?? ''));
        $from_ymd   = trim((string) ($filters['from_ymd'] ?? ''));
        $to_ymd     = trim((string) ($filters['to_ymd'] ?? ''));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_ymd) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_ymd)) {
            $today = new DateTimeImmutable('today');
            $y = (int) $today->format('Y');
            $m = (int) $today->format('n');
            $fyStart = $m >= 4 ? $y : ($y - 1);
            $from_ymd = sprintf('%04d-04-01', $fyStart);
            $to_ymd = sprintf('%04d-03-31', $fyStart + 1);
        }

        $from_e = esc($from_ymd);
        $to_e = esc($to_ymd);
        $branch = auragold_vendor_report_branch_sql($conn);

        $where = " p.status = 1 AND COALESCE(p.vendor_id, 0) > 0 {$branch['pc']} ";
        if ($vendor_id > 0) {
            $where .= ' AND p.vendor_id = ' . $vendor_id . ' ';
        }
        if ($product_id > 0) {
            $where .= ' AND p.id = ' . $product_id . ' ';
        }
        if ($metal_id > 0) {
            $where .= ' AND m.id = ' . $metal_id . ' ';
        }
        if ($search !== '') {
            $s = esc('%' . $search . '%');
            $where .= " AND (
                p.name LIKE '{$s}'
                OR p.article LIKE '{$s}'
                OR v.name LIKE '{$s}'
                OR m.display_name LIKE '{$s}'
                OR COALESCE(cat.name, '') LIKE '{$s}'
            ) ";
        }

        $sale_branch = function_exists('auragold_sale_invoices_branch_where_sql')
            ? auragold_sale_invoices_branch_where_sql($conn, 'si')
            : '';
        $pur_branch = function_exists('auragold_purchase_invoices_branch_where_sql')
            ? auragold_purchase_invoices_branch_where_sql($conn, 'pi')
            : '';

        $sale_status = " AND LOWER(TRIM(IFNULL(si.status, ''))) NOT IN ('cancelled', 'void', 'deleted') ";
        $pur_status = " AND LOWER(TRIM(IFNULL(pi.status, ''))) NOT IN ('cancelled', 'void', 'deleted') ";

        $opening_sub = "
            SELECT s.product_id, s.metal_id,
                SUM(CASE WHEN s.stock_type = 'opening'
                    THEN COALESCE(s.opening_weight, s.current_weight, 0) ELSE 0 END) AS opening_wt,
                SUM(CASE WHEN s.stock_type = 'opening'
                    THEN COALESCE(s.opening_qty, s.current_qty, 0) ELSE 0 END) AS opening_qty
            FROM tbl_stock s
            WHERE s.status = 1 {$branch['stock']}
            GROUP BY s.product_id, s.metal_id
        ";

        $sale_sub = "
            SELECT sii.product_id, COALESCE(pc.metal_id, 0) AS metal_id,
                SUM(COALESCE(NULLIF(sii.gross_weight, 0), sii.final_weight, 0)) AS sale_wt,
                SUM(COALESCE(sii.quantity, 0)) AS sale_qty
            FROM tbl_sale_invoice_items sii
            INNER JOIN tbl_sale_invoices si ON si.id = sii.invoice_id {$sale_status}
            LEFT JOIN tbl_product_characteristics pc
                ON pc.id = sii.product_characteristic_id AND pc.product_id = sii.product_id
            WHERE IFNULL(sii.status, 1) = 1
                AND DATE(si.invoice_date) BETWEEN '{$from_e}' AND '{$to_e}'
                {$sale_branch}
            GROUP BY sii.product_id, COALESCE(pc.metal_id, 0)
        ";

        $pur_sub = "
            SELECT pii.product_id, COALESCE(pc.metal_id, 0) AS metal_id,
                SUM(COALESCE(NULLIF(pii.metal_weight, 0), pii.gross_weight, 0)) AS purchase_wt,
                SUM(COALESCE(NULLIF(pii.metal_qty, 0), pii.quantity, 0)) AS purchase_qty
            FROM tbl_purchase_invoice_items pii
            INNER JOIN tbl_purchase_invoices pi ON pi.id = pii.invoice_id {$pur_status}
            LEFT JOIN tbl_product_characteristics pc
                ON pc.id = pii.product_characteristic_id AND pc.product_id = pii.product_id
            WHERE IFNULL(pii.status, 1) = 1
                AND DATE(pi.invoice_date) BETWEEN '{$from_e}' AND '{$to_e}'
                {$pur_branch}
            GROUP BY pii.product_id, COALESCE(pc.metal_id, 0)
        ";

        $balance_sub = "
            SELECT s.product_id, s.metal_id,
                (
                    SUM(CASE WHEN s.stock_type IN ('opening','purchase','inward','balance','stock_journal','sale_return')
                        THEN COALESCE(s.opening_weight, s.current_weight, 0) ELSE 0 END)
                    - SUM(CASE WHEN s.stock_type = 'outward'
                        THEN COALESCE(s.opening_weight, s.current_weight, 0) ELSE 0 END)
                ) AS balance_wt,
                (
                    SUM(CASE WHEN s.stock_type IN ('opening','purchase','inward','balance','stock_journal','sale_return')
                        THEN COALESCE(s.current_qty, 0) ELSE 0 END)
                    - SUM(CASE WHEN s.stock_type = 'outward'
                        THEN COALESCE(s.current_qty, 0) ELSE 0 END)
                ) AS balance_qty
            FROM tbl_stock s
            WHERE s.status = 1 {$branch['stock']}
            GROUP BY s.product_id, s.metal_id
        ";

        $branch_sel = "'' AS branch_name";
        $branch_join = '';
        $group_branch = '';
        if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_product_characteristics', 'branch_id')) {
            $branch_sel = 'COALESCE(br.name, \'\') AS branch_name';
            $branch_join = 'LEFT JOIN tbl_branches br ON br.id = pc.branch_id';
            $group_branch = ', br.name';
        }

        $sql = "
            SELECT
                p.id AS product_id,
                p.name AS product_name,
                COALESCE(p.article, '') AS article,
                COALESCE(cat.name, '') AS category_name,
                p.vendor_id,
                COALESCE(v.name, '') AS vendor_name,
                m.id AS metal_id,
                COALESCE(m.display_name, '') AS metal_name,
                {$branch_sel},
                COALESCE(op.opening_wt, 0) AS opening_wt,
                COALESCE(op.opening_qty, 0) AS opening_qty,
                COALESCE(sl.sale_wt, 0) AS sale_wt,
                COALESCE(sl.sale_qty, 0) AS sale_qty,
                COALESCE(pu.purchase_wt, 0) AS purchase_wt,
                COALESCE(pu.purchase_qty, 0) AS purchase_qty,
                COALESCE(bal.balance_wt, 0) AS balance_wt,
                COALESCE(bal.balance_qty, 0) AS balance_qty
            FROM tbl_products p
            INNER JOIN tbl_customers v ON v.id = p.vendor_id AND v.status = 1
            INNER JOIN tbl_product_characteristics pc ON pc.product_id = p.id AND pc.status = 1
            INNER JOIN tbl_metal m ON m.id = pc.metal_id AND m.status = 1
            LEFT JOIN tbl_categories cat ON cat.id = p.category_id
            {$branch_join}
            LEFT JOIN ({$opening_sub}) op ON op.product_id = p.id AND op.metal_id = m.id
            LEFT JOIN ({$sale_sub}) sl ON sl.product_id = p.id AND sl.metal_id = m.id
            LEFT JOIN ({$pur_sub}) pu ON pu.product_id = p.id AND pu.metal_id = m.id
            LEFT JOIN ({$balance_sub}) bal ON bal.product_id = p.id AND bal.metal_id = m.id
            WHERE {$where}
            GROUP BY p.id, m.id, p.name, p.article, cat.name, p.vendor_id, v.name, m.display_name{$group_branch},
                     op.opening_wt, op.opening_qty, sl.sale_wt, sl.sale_qty, pu.purchase_wt, pu.purchase_qty,
                     bal.balance_wt, bal.balance_qty
            ORDER BY v.name ASC, p.name ASC, m.display_name ASC
            LIMIT 5000
        ";

        $raw = getList($sql);
        if (!is_array($raw)) {
            return [];
        }

        $rows = [];
        foreach ($raw as $r) {
            $rows[] = [
                'product_name'   => (string) ($r['product_name'] ?? ''),
                'vendor_name'    => (string) ($r['vendor_name'] ?? ''),
                'metal_name'     => (string) ($r['metal_name'] ?? ''),
                'article'        => (string) ($r['article'] ?? ''),
                'category'       => (string) ($r['category_name'] ?? ''),
                'branch'         => (string) ($r['branch_name'] ?? ''),
                'opening_wt'     => auragold_vendor_report_fmt_wt($r['opening_wt'] ?? 0),
                'opening_qty'    => auragold_vendor_report_fmt_qty($r['opening_qty'] ?? 0),
                'sale_wt'        => auragold_vendor_report_fmt_wt($r['sale_wt'] ?? 0),
                'sale_qty'       => auragold_vendor_report_fmt_qty($r['sale_qty'] ?? 0),
                'purchase_wt'    => auragold_vendor_report_fmt_wt($r['purchase_wt'] ?? 0),
                'purchase_qty'   => auragold_vendor_report_fmt_qty($r['purchase_qty'] ?? 0),
                'balance_wt'     => auragold_vendor_report_fmt_wt($r['balance_wt'] ?? 0),
                'balance_qty'    => auragold_vendor_report_fmt_qty($r['balance_qty'] ?? 0),
            ];
        }

        return $rows;
    }
}

if (!function_exists('auragold_vendor_report_totals')) {
    /**
     * @param array<int,array<string,string>> $rows
     * @return array<string,string>
     */
    function auragold_vendor_report_totals(array $rows): array {
        $keys = ['opening_wt', 'opening_qty', 'sale_wt', 'sale_qty', 'purchase_wt', 'purchase_qty', 'balance_wt', 'balance_qty'];
        $sum = array_fill_keys($keys, 0.0);
        foreach ($rows as $row) {
            foreach ($keys as $k) {
                $sum[$k] += (float) ($row[$k] ?? 0);
            }
        }
        $out = ['product_name' => 'Total', 'vendor_name' => '', 'metal_name' => '', 'article' => '', 'category' => '', 'branch' => ''];
        foreach ($keys as $k) {
            $out[$k] = strpos($k, '_wt') !== false
                ? auragold_vendor_report_fmt_wt($sum[$k])
                : auragold_vendor_report_fmt_qty($sum[$k]);
        }

        return $out;
    }
}
