<?php

/**
 * Salesperson Performance — aggregate POS + standard sale invoice lines by metal/product category.
 */

declare(strict_types=1);

if (!function_exists('auragold_sale_analysis_parse_range')) {
    require_once __DIR__ . '/auragold_sale_analysis_data.php';
}

if (!function_exists('auragold_sp_performance_pos_branch_where_sql')) {
    /**
     * Mirrors {@see auragold_sale_invoices_branch_where_sql} for tbl_pos_sale_invoices alias.
     */
    function auragold_sp_performance_pos_branch_where_sql($conn, string $alias = 'psi'): string {
        if (!$conn instanceof mysqli) {
            return '';
        }
        if (!function_exists('auragold_tbl_has_column') || !auragold_tbl_has_column($conn, 'tbl_pos_sale_invoices', 'branch_id')) {
            return '';
        }
        $eff = function_exists('auragold_effective_branch_id') ? (int) auragold_effective_branch_id() : 0;
        if ($eff <= 0) {
            return '';
        }
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
        if ($a === '') {
            $a = 'psi';
        }
        $main = function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0;
        if ($main > 0 && $eff === $main) {
            return " AND ({$a}.branch_id = {$eff} OR {$a}.branch_id IS NULL OR {$a}.branch_id = 0) ";
        }

        return " AND COALESCE({$a}.branch_id, 0) = {$eff} ";
    }
}

if (!function_exists('auragold_sp_performance_doc_status_where_sql')) {
    /**
     * Match sale analysis: include saved/draft documents; exclude cancelled/void/deleted only.
     */
    function auragold_sp_performance_doc_status_where_sql(string $hdrAlias = 'hdr'): string {
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', $hdrAlias);
        if ($a === '') {
            $a = 'hdr';
        }

        return " AND LOWER(TRIM(IFNULL({$a}.status, ''))) NOT IN ('cancelled', 'void', 'deleted', 'canceled') ";
    }
}

if (!function_exists('auragold_sp_performance_sale_order_branch_where_sql')) {
    function auragold_sp_performance_sale_order_branch_where_sql($conn, string $alias = 'hdr'): string {
        if (!$conn instanceof mysqli) {
            return '';
        }
        if (!function_exists('auragold_tbl_has_column') || !auragold_tbl_has_column($conn, 'tbl_sale_orders', 'branch_id')) {
            return '';
        }
        $eff = function_exists('auragold_effective_branch_id') ? (int) auragold_effective_branch_id() : 0;
        if ($eff <= 0) {
            return '';
        }
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
        if ($a === '') {
            $a = 'hdr';
        }
        $main = function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0;
        if ($main > 0 && $eff === $main) {
            return " AND ({$a}.branch_id = {$eff} OR {$a}.branch_id IS NULL OR {$a}.branch_id = 0) ";
        }

        return " AND COALESCE({$a}.branch_id, 0) = {$eff} ";
    }
}

if (!function_exists('auragold_sp_performance_line_outer_sql')) {
    /**
     * Shared SELECT list: category bucket + qty/wts/amounts per invoice/order line.
     *
     * Expects correlated tables: hdr (header alias), it (items alias).
     *
     * @param string $invoice_src literal 'si', 'psi', or 'so' for DISTINCT bill counts
     * @param string $metal_join fragment joining `mt` to resolved metal id
     * @param string $hdr_date_col header date column (invoice_date / order_date)
     * @param string $item_fk_col items FK column (invoice_id / order_id)
     * @param string $stone_weight_expr SQL expression for stone weight
     * @param string $metal_qty_expr SQL expression for metal qty
     * @param string $calculation_type_expr SQL expression for calculation type
     */
    function auragold_sp_performance_line_outer_sql(
        string $invoice_src,
        string $metal_join,
        string $carat_decimal_expr,
        string $hdr_date_col = 'invoice_date',
        string $item_fk_col = 'invoice_id',
        string $stone_weight_expr = 'IFNULL(it.stone_weight, 0)',
        string $metal_qty_expr = 'IFNULL(it.metal_qty, 0)',
        string $calculation_type_expr = 'IFNULL(it.calculation_type, \'\')',
        string $person_col = 'sales_person',
        string $metal_value_expr = 'COALESCE(it.metal_value, 0)'
    ): string {
        $hdr_date_col = preg_replace('/[^a-zA-Z0-9_]/', '', $hdr_date_col);
        if ($hdr_date_col === '') {
            $hdr_date_col = 'invoice_date';
        }
        $item_fk_col = preg_replace('/[^a-zA-Z0-9_]/', '', $item_fk_col);
        if ($item_fk_col === '') {
            $item_fk_col = 'invoice_id';
        }
        $person_col = preg_replace('/[^a-zA-Z0-9_]/', '', $person_col);
        if ($person_col === '') {
            $person_col = 'sales_person';
        }
        $status_where = auragold_sp_performance_doc_status_where_sql('hdr');
        // phpcs:disable Generic.Files.LineLength.TooLong
        return "
            SELECT
                IF(IFNULL(TRIM(hdr.{$person_col}), '') = '', '(Unassigned)', TRIM(hdr.{$person_col})) AS salesperson_key,
                '{$invoice_src}' AS invoice_src,
                hdr.id AS invoice_id,
                CASE
                    WHEN LOWER(IFNULL(TRIM(it.product_name), '')) REGEXP '(imitation|fashion jewel|artificial jewel|oxidized imitation|american diamond|watch|timepiece)'
                    THEN 'imitation_watches'
                    WHEN LOWER(CONCAT(' ', IFNULL(mt.display_name, ''), ' ', IFNULL(mt.system_name, ''))) LIKE '% silver %'
                         OR LOWER(CONCAT(IFNULL(mt.display_name, ''), IFNULL(mt.system_name, ''))) LIKE 'silver %'
                         OR LOWER(TRIM(CONCAT(IFNULL(mt.display_name, ''), IFNULL(mt.system_name, '')))) LIKE '%silver%'
                    THEN 'silver'
                    WHEN LOWER(CONCAT(IFNULL(mt.display_name, ''), IFNULL(mt.system_name, ''))) LIKE '% platin%' THEN 'platinum'
                    WHEN LOWER(CONCAT(IFNULL(mt.display_name, ''), IFNULL(mt.system_name, ''))) LIKE '%gold%' THEN 'gold'
                    WHEN IFNULL(it.diamond_amount, 0) + IFNULL(it.stone_amount, 0) > 0
                         OR ({$stone_weight_expr}) > 0
                         OR (({$metal_qty_expr}) > 0 AND LOWER({$calculation_type_expr}) LIKE '%carat%')
                         OR LOWER({$calculation_type_expr}) REGEXP '(carat|stone|diamond)'
                         OR (it.carat IS NOT NULL AND TRIM(it.carat) <> ''
                             AND REPLACE(REPLACE(TRIM(it.carat), '+', ''), ' ', '') REGEXP '^[0-9]+(\\\\.[0-9]+)?')
                         OR LOWER(CONCAT(' ', IFNULL(mt.display_name, ''), IFNULL(mt.system_name, ''))) REGEXP '(diamond|gem stone|gemstone)'
                    THEN 'diamond_stones'
                    ELSE 'other_services'
                END AS cat_slug,
                CAST(IFNULL(it.quantity, 0) AS DECIMAL(18, 4)) AS qty_num,
                CAST(IFNULL(it.gross_weight, 0) AS DECIMAL(18, 4)) AS gross_w_num,
                CAST(COALESCE(it.net_weight, it.final_weight, it.gross_weight, 0) AS DECIMAL(18, 4)) AS net_line_num,
                CAST({$carat_decimal_expr} AS DECIMAL(18, 6)) AS d_ct_num,
                CAST(COALESCE(NULLIF(it.net_amt_with_tax, 0), NULLIF(it.net_amount, 0), it.amount, 0) AS DECIMAL(18, 4)) AS sale_line_num,
                CAST(COALESCE(NULLIF(it.net_amt_with_tax, 0), NULLIF(it.net_amount, 0), it.amount, 0)
                    - {$metal_value_expr} - COALESCE(it.making_amount, 0)
                AS DECIMAL(18, 4)) AS gp_line_num
            FROM LINE_ITEMS_TABLE_ALIAS it
            INNER JOIN SALE_HDR_TABLE_ALIAS hdr ON hdr.id = it.{$item_fk_col}
                {$status_where}
                AND DATE(hdr.{$hdr_date_col}) BETWEEN RANGE_FROM AND RANGE_TO
            {$metal_join}
            WHERE IFNULL(it.status, 1) = 1
              BRANCH_WHERE_ITEMS
        ";
        // phpcs:enable Generic.Files.LineLength.TooLong
    }
}

if (!function_exists('auragold_salesperson_performance_fetch_rows')) {
    /**
     * @param array<string, mixed> $sp_groups keys = category slugs in salesperson-performance UI
     * @param array<string, string> $sp_metrics unused but kept for API parity with page
     *
     * @return array<int, array<string, mixed>> rows shaped like sp_sample_row()
     */
    function auragold_salesperson_performance_fetch_rows($conn, string $from_ymd, string $to_ymd, array $sp_groups): array {
        if (!$conn instanceof mysqli || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_ymd) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_ymd)) {
            return [];
        }
        $chk_si = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_invoices'");
        if (!$chk_si || mysqli_num_rows($chk_si) === 0) {
            if ($chk_si) {
                mysqli_free_result($chk_si);
            }

            return [];
        }
        mysqli_free_result($chk_si);

        $from_e = esc($from_ymd);
        $to_e = esc($to_ymd);

        $carat_decimal_expr_base = "
            CASE
                WHEN REPLACE(REPLACE(REPLACE(IFNULL(TRIM(it.carat), ''), '+', ''), ' ', ''), ',', '.')
                     REGEXP '^[0-9]+(\\.[0-9]+)?'
                    THEN REPLACE(REPLACE(REPLACE(IFNULL(TRIM(it.carat), ''), '+', ''), ' ', ''), ',', '.')
                ELSE '0'
            END
        ";

        $metal_join_legacy = '
            LEFT JOIN tbl_product_characteristics pc
                ON pc.id = it.product_characteristic_id AND pc.product_id = it.product_id
            LEFT JOIN tbl_metal mt ON mt.id = COALESCE(NULLIF(pc.metal_id, 0),
                IFNULL((SELECT pc2.metal_id FROM tbl_product_characteristics pc2
                    WHERE pc2.product_id = it.product_id AND pc2.status = 1
                    ORDER BY (CASE WHEN pc2.id <=> it.product_characteristic_id THEN 0 ELSE 1 END), pc2.id DESC
                    LIMIT 1), 0))
        ';

        $branch_si = function_exists('auragold_sale_invoices_branch_where_sql')
            ? auragold_sale_invoices_branch_where_sql($conn, 'hdr')
            : '';

        $branch_psi = auragold_sp_performance_pos_branch_where_sql($conn, 'hdr');

        $source_sqls = [];

        $tpl_si = auragold_sp_performance_line_outer_sql('si', $metal_join_legacy, $carat_decimal_expr_base);
        $source_sqls[] = str_replace(
            ['LINE_ITEMS_TABLE_ALIAS', 'SALE_HDR_TABLE_ALIAS', 'RANGE_FROM', 'RANGE_TO', 'BRANCH_WHERE_ITEMS'],
            ['tbl_sale_invoice_items', 'tbl_sale_invoices', "'{$from_e}'", "'{$to_e}'", $branch_si],
            $tpl_si
        );

        $chk_psi = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_pos_sale_invoices'");
        $has_psi = $chk_psi && mysqli_num_rows($chk_psi) > 0;
        if ($chk_psi) {
            mysqli_free_result($chk_psi);
        }
        if ($has_psi) {
            $tpl_psi = auragold_sp_performance_line_outer_sql('psi', $metal_join_legacy, $carat_decimal_expr_base);
            $source_sqls[] = str_replace(
                ['LINE_ITEMS_TABLE_ALIAS', 'SALE_HDR_TABLE_ALIAS', 'RANGE_FROM', 'RANGE_TO', 'BRANCH_WHERE_ITEMS'],
                ['tbl_pos_sale_invoice_items', 'tbl_pos_sale_invoices', "'{$from_e}'", "'{$to_e}'", $branch_psi],
                $tpl_psi
            );
        }

        $chk_so = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_orders'");
        $has_so = $chk_so && mysqli_num_rows($chk_so) > 0;
        if ($chk_so) {
            mysqli_free_result($chk_so);
        }
        $chk_soi = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_order_items'");
        $has_soi = $chk_soi && mysqli_num_rows($chk_soi) > 0;
        if ($chk_soi) {
            mysqli_free_result($chk_soi);
        }
        if ($has_so && $has_soi) {
            $branch_so = auragold_sp_performance_sale_order_branch_where_sql($conn, 'hdr');
            $tpl_so = auragold_sp_performance_line_outer_sql(
                'so',
                $metal_join_legacy,
                $carat_decimal_expr_base,
                'order_date',
                'order_id',
                '0',
                '0',
                "''"
            );
            $source_sqls[] = str_replace(
                ['LINE_ITEMS_TABLE_ALIAS', 'SALE_HDR_TABLE_ALIAS', 'RANGE_FROM', 'RANGE_TO', 'BRANCH_WHERE_ITEMS'],
                ['tbl_sale_order_items', 'tbl_sale_orders', "'{$from_e}'", "'{$to_e}'", $branch_so],
                $tpl_so
            );
        }

        $agg_rows = [];
        foreach ($source_sqls as $line_sql) {
            $agg_sql = "
                SELECT
                    salesperson_key,
                    cat_slug AS bucket,
                    SUM(qty_num) AS qty_sum,
                    SUM(gross_w_num) AS gw_sum,
                    SUM(net_line_num) AS nw_sum,
                    SUM(d_ct_num) AS d_ct_sum,
                    SUM(sale_line_num) AS sale_sum,
                    SUM(gp_line_num) AS gp_sum
                FROM (\n {$line_sql} \n) _lines
                GROUP BY salesperson_key, cat_slug
            ";
            $part_agg = function_exists('getList') ? getList($agg_sql) : [];
            if (is_array($part_agg)) {
                $agg_rows = array_merge($agg_rows, $part_agg);
            }
        }

        // Merge per-source aggregates (same salesperson + bucket may appear in multiple sources).
        $agg_merged = [];
        foreach ($agg_rows as $ar) {
            $spkey = trim((string) ($ar['salesperson_key'] ?? ''));
            if ($spkey === '') {
                $spkey = '(Unassigned)';
            }
            $b = trim((string) ($ar['bucket'] ?? ''));
            $mk = $spkey . "\0" . $b;
            if (!isset($agg_merged[$mk])) {
                $agg_merged[$mk] = [
                    'salesperson_key' => $spkey,
                    'bucket' => $b,
                    'qty_sum' => 0.0,
                    'gw_sum' => 0.0,
                    'nw_sum' => 0.0,
                    'd_ct_sum' => 0.0,
                    'sale_sum' => 0.0,
                    'gp_sum' => 0.0,
                ];
            }
            $agg_merged[$mk]['qty_sum'] += (float) ($ar['qty_sum'] ?? 0);
            $agg_merged[$mk]['gw_sum'] += (float) ($ar['gw_sum'] ?? 0);
            $agg_merged[$mk]['nw_sum'] += (float) ($ar['nw_sum'] ?? 0);
            $agg_merged[$mk]['d_ct_sum'] += (float) ($ar['d_ct_sum'] ?? 0);
            $agg_merged[$mk]['sale_sum'] += (float) ($ar['sale_sum'] ?? 0);
            $agg_merged[$mk]['gp_sum'] += (float) ($ar['gp_sum'] ?? 0);
        }
        $agg_rows = array_values($agg_merged);

        $status_where_hdr = auragold_sp_performance_doc_status_where_sql('hdr');
        $header_bill_sources = [
            [
                'table' => 'tbl_sale_invoices',
                'date_col' => 'invoice_date',
                'branch' => $branch_si,
            ],
        ];
        if ($has_psi) {
            $header_bill_sources[] = [
                'table' => 'tbl_pos_sale_invoices',
                'date_col' => 'invoice_date',
                'branch' => $branch_psi,
            ];
        }
        if ($has_so) {
            $header_bill_sources[] = [
                'table' => 'tbl_sale_orders',
                'date_col' => 'order_date',
                'branch' => auragold_sp_performance_sale_order_branch_where_sql($conn, 'hdr'),
            ];
        }

        $bills_by_sp = [];
        foreach ($header_bill_sources as $hb) {
            $tbl = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $hb['table']);
            $date_col = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $hb['date_col']);
            if ($tbl === '' || $date_col === '') {
                continue;
            }
            $branch = (string) ($hb['branch'] ?? '');
            $hdr_bills_sql = "
                SELECT
                    IF(IFNULL(TRIM(hdr.sales_person), '') = '', '(Unassigned)', TRIM(hdr.sales_person)) AS salesperson_key,
                    COUNT(DISTINCT hdr.id) AS bill_cnt
                FROM {$tbl} hdr
                WHERE DATE(hdr.{$date_col}) BETWEEN '{$from_e}' AND '{$to_e}'
                  {$status_where_hdr}
                  {$branch}
                GROUP BY salesperson_key
            ";
            $hdr_bill_rows = function_exists('getList') ? getList($hdr_bills_sql) : [];
            if (!is_array($hdr_bill_rows)) {
                continue;
            }
            foreach ($hdr_bill_rows as $hbr) {
                $k = trim((string) ($hbr['salesperson_key'] ?? ''));
                if ($k === '') {
                    $k = '(Unassigned)';
                }
                $bills_by_sp[$k] = ($bills_by_sp[$k] ?? 0) + (int) ($hbr['bill_cnt'] ?? 0);
            }
        }

        $fmt = [
            'qty' => static function ($x): string {
                if (abs((float) $x - round((float) $x)) < 0.000001) {
                    return (string) (int) round((float) $x);
                }

                return number_format((float) $x, 2, '.', '');
            },
            'wt3' => static fn (float $x): string => number_format($x, 3, '.', ''),
            'wt6' => static fn (float $x): string => number_format($x, 6, '.', ''),
            'money' => static fn (float $x): string => number_format($x, 2, '.', ''),
        ];

        $metrics_template = [];
        foreach (array_keys($sp_groups) as $slug) {
            $metrics_template[$slug] = [
                'qty' => $fmt['qty'](0),
                'gross_wt' => $fmt['wt3'](0.0),
                'net_wt' => $fmt['wt3'](0.0),
                'd_ct' => $fmt['wt6'](0.0),
                'sale_amount' => $fmt['money'](0.0),
                'gross_profit' => $fmt['money'](0.0),
            ];
        }

        $clone_metrics_template = static function (array $tpl): array {
            $o = [];
            foreach ($tpl as $slug => $cells) {
                $o[$slug] = $cells;
            }

            return $o;
        };

        $merged = [];

        foreach ($agg_rows as $ar) {
            $spkey = trim((string) ($ar['salesperson_key'] ?? ''));
            if ($spkey === '') {
                $spkey = '(Unassigned)';
            }
            $b = trim((string) ($ar['bucket'] ?? ''));

            if (!isset($merged[$spkey])) {
                $merged[$spkey] = [
                    'name' => $spkey === '(Unassigned)' ? '(Unassigned)' : $spkey,
                    'bills' => (string) ($bills_by_sp[$spkey] ?? 0),
                    'groups' => $clone_metrics_template($metrics_template),
                    'scheme' => ['no_of_scheme' => '0', 'scheme_amount' => $fmt['money'](0.0)],
                ];
            }

            if (!isset($merged[$spkey]['groups'][$b])) {
                continue;
            }
            $merged[$spkey]['groups'][$b] = [
                'qty' => $fmt['qty']((float) ($ar['qty_sum'] ?? 0)),
                'gross_wt' => $fmt['wt3']((float) ($ar['gw_sum'] ?? 0)),
                'net_wt' => $fmt['wt3']((float) ($ar['nw_sum'] ?? 0)),
                'd_ct' => $fmt['wt6']((float) ($ar['d_ct_sum'] ?? 0)),
                'sale_amount' => $fmt['money']((float) ($ar['sale_sum'] ?? 0)),
                'gross_profit' => $fmt['money']((float) ($ar['gp_sum'] ?? 0)),
            ];
        }

        foreach ($bills_by_sp as $spkey => $bcnt) {
            if (!isset($merged[$spkey])) {
                $merged[$spkey] = [
                    'name' => $spkey,
                    'bills' => (string) $bcnt,
                    'groups' => $clone_metrics_template($metrics_template),
                    'scheme' => ['no_of_scheme' => '0', 'scheme_amount' => $fmt['money'](0.0)],
                ];
            } else {
                $merged[$spkey]['bills'] = (string) $bcnt;
            }
        }

        $out_rows = [];
        foreach ($merged as $sp_data) {
            $row = ['name' => $sp_data['name'], 'bills' => $sp_data['bills'], 'scheme' => $sp_data['scheme']];
            foreach (array_keys($sp_groups) as $slug) {
                $row[$slug] = $sp_data['groups'][$slug];
            }
            $out_rows[] = $row;
        }

        usort($out_rows, static function ($a, $b): int {
            $an = strtolower((string) ($a['name'] ?? ''));
            $bn = strtolower((string) ($b['name'] ?? ''));

            return $an <=> $bn;
        });

        return $out_rows;
    }
}

if (!function_exists('auragold_sp_performance_line_detail_sql')) {
    /**
     * Line-level detail rows for qty drill-down modal.
     */
    function auragold_sp_performance_line_detail_sql(
        string $invoice_src,
        string $doc_type_label,
        string $doc_no_col,
        string $party_col,
        string $metal_join,
        string $carat_decimal_expr,
        string $hdr_date_col = 'invoice_date',
        string $item_fk_col = 'invoice_id',
        string $stone_weight_expr = 'IFNULL(it.stone_weight, 0)',
        string $metal_qty_expr = 'IFNULL(it.metal_qty, 0)',
        string $calculation_type_expr = 'IFNULL(it.calculation_type, \'\')',
        string $person_col = 'sales_person',
        string $metal_value_expr = 'COALESCE(it.metal_value, 0)',
        string $diamond_stone_expr = 'IFNULL(it.diamond_amount, 0) + IFNULL(it.stone_amount, 0)'
    ): string {
        $doc_no_col = preg_replace('/[^a-zA-Z0-9_]/', '', $doc_no_col);
        $party_col = preg_replace('/[^a-zA-Z0-9_]/', '', $party_col);
        $hdr_date_col = preg_replace('/[^a-zA-Z0-9_]/', '', $hdr_date_col);
        if ($hdr_date_col === '') {
            $hdr_date_col = 'invoice_date';
        }
        $item_fk_col = preg_replace('/[^a-zA-Z0-9_]/', '', $item_fk_col);
        if ($item_fk_col === '') {
            $item_fk_col = 'invoice_id';
        }
        $person_col = preg_replace('/[^a-zA-Z0-9_]/', '', $person_col);
        if ($person_col === '') {
            $person_col = 'sales_person';
        }
        $doc_label_e = str_replace("'", "''", $doc_type_label);
        $status_where = auragold_sp_performance_doc_status_where_sql('hdr');

        // phpcs:disable Generic.Files.LineLength.TooLong
        return "
            SELECT
                '{$doc_label_e}' AS doc_type,
                '{$invoice_src}' AS doc_src,
                hdr.id AS doc_id,
                IFNULL(hdr.{$doc_no_col}, '') AS doc_no,
                DATE_FORMAT(hdr.{$hdr_date_col}, '%d-%m-%Y') AS doc_date,
                hdr.{$hdr_date_col} AS doc_date_sort,
                IFNULL(hdr.{$party_col}, '') AS party_name,
                IFNULL(it.product_name, '') AS product_name,
                IFNULL(it.barcode, '') AS barcode,
                IFNULL(hdr.status, '') AS doc_status,
                IF(IFNULL(TRIM(hdr.{$person_col}), '') = '', '(Unassigned)', TRIM(hdr.{$person_col})) AS salesperson_key,
                CASE
                    WHEN LOWER(IFNULL(TRIM(it.product_name), '')) REGEXP '(imitation|fashion jewel|artificial jewel|oxidized imitation|american diamond|watch|timepiece)'
                    THEN 'imitation_watches'
                    WHEN LOWER(CONCAT(' ', IFNULL(mt.display_name, ''), ' ', IFNULL(mt.system_name, ''))) LIKE '% silver %'
                         OR LOWER(CONCAT(IFNULL(mt.display_name, ''), IFNULL(mt.system_name, ''))) LIKE 'silver %'
                         OR LOWER(TRIM(CONCAT(IFNULL(mt.display_name, ''), IFNULL(mt.system_name, '')))) LIKE '%silver%'
                    THEN 'silver'
                    WHEN LOWER(CONCAT(IFNULL(mt.display_name, ''), IFNULL(mt.system_name, ''))) LIKE '% platin%' THEN 'platinum'
                    WHEN LOWER(CONCAT(IFNULL(mt.display_name, ''), IFNULL(mt.system_name, ''))) LIKE '%gold%' THEN 'gold'
                    WHEN {$diamond_stone_expr} > 0
                         OR ({$stone_weight_expr}) > 0
                         OR (({$metal_qty_expr}) > 0 AND LOWER({$calculation_type_expr}) LIKE '%carat%')
                         OR LOWER({$calculation_type_expr}) REGEXP '(carat|stone|diamond)'
                         OR (it.carat IS NOT NULL AND TRIM(it.carat) <> ''
                             AND REPLACE(REPLACE(TRIM(it.carat), '+', ''), ' ', '') REGEXP '^[0-9]+(\\\\.[0-9]+)?')
                         OR LOWER(CONCAT(' ', IFNULL(mt.display_name, ''), IFNULL(mt.system_name, ''))) REGEXP '(diamond|gem stone|gemstone)'
                    THEN 'diamond_stones'
                    ELSE 'other_services'
                END AS cat_slug,
                CAST(IFNULL(it.quantity, 0) AS DECIMAL(18, 4)) AS qty_num,
                CAST(IFNULL(it.gross_weight, 0) AS DECIMAL(18, 4)) AS gross_w_num,
                CAST(COALESCE(it.net_weight, it.final_weight, it.gross_weight, 0) AS DECIMAL(18, 4)) AS net_line_num,
                CAST(COALESCE(NULLIF(it.net_amt_with_tax, 0), NULLIF(it.net_amount, 0), it.amount, 0) AS DECIMAL(18, 4)) AS sale_line_num
            FROM LINE_ITEMS_TABLE_ALIAS it
            INNER JOIN SALE_HDR_TABLE_ALIAS hdr ON hdr.id = it.{$item_fk_col}
                {$status_where}
                AND DATE(hdr.{$hdr_date_col}) BETWEEN RANGE_FROM AND RANGE_TO
            {$metal_join}
            WHERE IFNULL(it.status, 1) = 1
              BRANCH_WHERE_ITEMS
        ";
        // phpcs:enable Generic.Files.LineLength.TooLong
    }
}

if (!function_exists('auragold_sp_performance_doc_url')) {
    function auragold_sp_performance_doc_url(string $doc_src, int $doc_id): string {
        if ($doc_id <= 0) {
            return '';
        }
        $map = [
            'si' => 'sale-invoice.php?id=',
            'psi' => 'pos-sale-invoice.php?id=',
            'so' => 'sale-order.php?id=',
            'pi' => 'purchase-invoice.php?id=',
        ];
        $base = $map[$doc_src] ?? '';

        return $base !== '' ? $base . $doc_id : '';
    }
}

if (!function_exists('auragold_salesperson_performance_qty_detail_rows')) {
    /**
     * Line items for qty drill-down: sale/POS invoices, sale orders, purchase invoices.
     *
     * @return list<array<string, string>>
     */
    function auragold_salesperson_performance_qty_detail_rows(
        $conn,
        string $from_ymd,
        string $to_ymd,
        string $salesperson,
        string $cat_slug
    ): array {
        if (!$conn instanceof mysqli
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_ymd)
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_ymd)) {
            return [];
        }

        $allowed_cats = ['gold', 'silver', 'platinum', 'diamond_stones', 'imitation_watches', 'other_services'];
        $cat_slug = trim($cat_slug);
        if (!in_array($cat_slug, $allowed_cats, true)) {
            return [];
        }

        $salesperson = trim($salesperson);
        if ($salesperson === '') {
            return [];
        }

        $from_e = esc($from_ymd);
        $to_e = esc($to_ymd);
        $cat_e = esc($cat_slug);
        $sp_e = esc($salesperson);

        $carat_decimal_expr_base = "
            CASE
                WHEN REPLACE(REPLACE(REPLACE(IFNULL(TRIM(it.carat), ''), '+', ''), ' ', ''), ',', '.')
                     REGEXP '^[0-9]+(\\.[0-9]+)?'
                    THEN REPLACE(REPLACE(REPLACE(IFNULL(TRIM(it.carat), ''), '+', ''), ' ', ''), ',', '.')
                ELSE '0'
            END
        ";

        $metal_join_legacy = '
            LEFT JOIN tbl_product_characteristics pc
                ON pc.id = it.product_characteristic_id AND pc.product_id = it.product_id
            LEFT JOIN tbl_metal mt ON mt.id = COALESCE(NULLIF(pc.metal_id, 0),
                IFNULL((SELECT pc2.metal_id FROM tbl_product_characteristics pc2
                    WHERE pc2.product_id = it.product_id AND pc2.status = 1
                    ORDER BY (CASE WHEN pc2.id <=> it.product_characteristic_id THEN 0 ELSE 1 END), pc2.id DESC
                    LIMIT 1), 0))
        ';

        $branch_si = function_exists('auragold_sale_invoices_branch_where_sql')
            ? auragold_sale_invoices_branch_where_sql($conn, 'hdr')
            : '';
        $branch_psi = auragold_sp_performance_pos_branch_where_sql($conn, 'hdr');
        $branch_so = auragold_sp_performance_sale_order_branch_where_sql($conn, 'hdr');
        $branch_pi = function_exists('auragold_purchase_invoices_branch_where_sql')
            ? auragold_purchase_invoices_branch_where_sql($conn, 'hdr')
            : '';

        $zero_exprs = ['0', '0', "''", '0', '0'];
        $source_defs = [
            [
                'items' => 'tbl_sale_invoice_items',
                'hdr' => 'tbl_sale_invoices',
                'src' => 'si',
                'label' => 'Sale Invoice',
                'no' => 'invoice_no',
                'party' => 'customer_name',
                'date' => 'invoice_date',
                'fk' => 'invoice_id',
                'person' => 'sales_person',
                'branch' => $branch_si,
                'exprs' => null,
            ],
            [
                'items' => 'tbl_pos_sale_invoice_items',
                'hdr' => 'tbl_pos_sale_invoices',
                'src' => 'psi',
                'label' => 'POS Sale',
                'no' => 'invoice_no',
                'party' => 'customer_name',
                'date' => 'invoice_date',
                'fk' => 'invoice_id',
                'person' => 'sales_person',
                'branch' => $branch_psi,
                'exprs' => null,
                'table' => 'tbl_pos_sale_invoices',
            ],
            [
                'items' => 'tbl_sale_order_items',
                'hdr' => 'tbl_sale_orders',
                'src' => 'so',
                'label' => 'Sale Order',
                'no' => 'order_no',
                'party' => 'customer_name',
                'date' => 'order_date',
                'fk' => 'order_id',
                'person' => 'sales_person',
                'branch' => $branch_so,
                'exprs' => $zero_exprs,
                'table' => 'tbl_sale_orders',
            ],
            [
                'items' => 'tbl_purchase_invoice_items',
                'hdr' => 'tbl_purchase_invoices',
                'src' => 'pi',
                'label' => 'Purchase Invoice',
                'no' => 'invoice_no',
                'party' => 'supplier_name',
                'date' => 'invoice_date',
                'fk' => 'invoice_id',
                'person' => 'purchase_person',
                'branch' => $branch_pi,
                'exprs' => $zero_exprs,
                'table' => 'tbl_purchase_invoices',
            ],
        ];

        $fmt_qty = static function ($x): string {
            if (abs((float) $x - round((float) $x)) < 0.000001) {
                return (string) (int) round((float) $x);
            }

            return number_format((float) $x, 2, '.', '');
        };

        $raw_rows = [];
        foreach ($source_defs as $def) {
            $tbl_chk = $def['table'] ?? $def['hdr'];
            $chk = @mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $tbl_chk) . "'");
            if (!$chk || mysqli_num_rows($chk) === 0) {
                if ($chk) {
                    mysqli_free_result($chk);
                }
                continue;
            }
            mysqli_free_result($chk);

            $stone_expr = 'IFNULL(it.stone_weight, 0)';
            $metal_qty_expr = 'IFNULL(it.metal_qty, 0)';
            $calc_expr = "IFNULL(it.calculation_type, '')";
            $metal_val_expr = 'COALESCE(it.metal_value, 0)';
            $diamond_stone_expr = 'IFNULL(it.diamond_amount, 0) + IFNULL(it.stone_amount, 0)';
            if (is_array($def['exprs'])) {
                [$stone_expr, $metal_qty_expr, $calc_expr, $metal_val_expr, $diamond_stone_expr] = $def['exprs'];
            }

            $tpl = auragold_sp_performance_line_detail_sql(
                (string) $def['src'],
                (string) $def['label'],
                (string) $def['no'],
                (string) $def['party'],
                $metal_join_legacy,
                $carat_decimal_expr_base,
                (string) $def['date'],
                (string) $def['fk'],
                $stone_expr,
                $metal_qty_expr,
                $calc_expr,
                (string) $def['person'],
                $metal_val_expr,
                $diamond_stone_expr
            );
            $sql = str_replace(
                ['LINE_ITEMS_TABLE_ALIAS', 'SALE_HDR_TABLE_ALIAS', 'RANGE_FROM', 'RANGE_TO', 'BRANCH_WHERE_ITEMS'],
                [$def['items'], $def['hdr'], "'{$from_e}'", "'{$to_e}'", (string) $def['branch']],
                $tpl
            );
            $sql = "
                SELECT * FROM (\n{$sql}\n) _detail
                WHERE cat_slug = '{$cat_e}'
                  AND salesperson_key = '{$sp_e}'
                  AND qty_num > 0
                ORDER BY doc_date_sort DESC, doc_no DESC, product_name ASC
            ";
            $part = function_exists('getList') ? getList($sql) : [];
            if (is_array($part) && $part !== []) {
                $raw_rows = array_merge($raw_rows, $part);
            }
        }

        usort($raw_rows, static function ($a, $b): int {
            $da = (string) ($a['doc_date_sort'] ?? '');
            $db = (string) ($b['doc_date_sort'] ?? '');
            if ($da !== $db) {
                return strcmp($db, $da);
            }
            $na = strtolower((string) ($a['doc_no'] ?? ''));
            $nb = strtolower((string) ($b['doc_no'] ?? ''));

            return strcmp($nb, $na);
        });

        $out = [];
        foreach ($raw_rows as $r) {
            $doc_src = (string) ($r['doc_src'] ?? '');
            $doc_id = (int) ($r['doc_id'] ?? 0);
            $out[] = [
                'doc_type' => (string) ($r['doc_type'] ?? ''),
                'doc_no' => (string) ($r['doc_no'] ?? ''),
                'date' => (string) ($r['doc_date'] ?? ''),
                'party' => (string) ($r['party_name'] ?? ''),
                'product' => (string) ($r['product_name'] ?? ''),
                'qty' => $fmt_qty((float) ($r['qty_num'] ?? 0)),
                'gross_wt' => number_format((float) ($r['gross_w_num'] ?? 0), 3, '.', ''),
                'net_wt' => number_format((float) ($r['net_line_num'] ?? 0), 3, '.', ''),
                'amount' => number_format((float) ($r['sale_line_num'] ?? 0), 2, '.', ''),
                'status' => (string) ($r['doc_status'] ?? ''),
                'url' => auragold_sp_performance_doc_url($doc_src, $doc_id),
            ];
        }

        return $out;
    }
}

if (!function_exists('auragold_sp_performance_person_filter_sql')) {
    function auragold_sp_performance_person_filter_sql($conn, string $alias, string $personCol, string $salesperson): string {
        if (!$conn instanceof mysqli) {
            return '';
        }
        $a = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
        if ($a === '') {
            $a = 'hdr';
        }
        $col = preg_replace('/[^a-zA-Z0-9_]/', '', $personCol);
        if ($col === '') {
            $col = 'sales_person';
        }
        if ($salesperson === '(Unassigned)') {
            return " AND IFNULL(TRIM({$a}.{$col}), '') = '' ";
        }
        $esc = mysqli_real_escape_string($conn, $salesperson);

        return " AND LOWER(TRIM({$a}.{$col})) = LOWER('{$esc}') ";
    }
}

if (!function_exists('auragold_salesperson_performance_bills_detail_rows')) {
    /**
     * Document headers for No Of Bills drill-down (sale/POS/order/purchase).
     *
     * @return list<array<string, string>>
     */
    function auragold_salesperson_performance_bills_detail_rows($conn, string $from_ymd, string $to_ymd, string $salesperson): array {
        if (!$conn instanceof mysqli
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_ymd)
            || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_ymd)) {
            return [];
        }

        $salesperson = trim($salesperson);
        if ($salesperson === '') {
            return [];
        }

        $from_e = esc($from_ymd);
        $to_e = esc($to_ymd);
        $status_where = auragold_sp_performance_doc_status_where_sql('hdr');
        $person_filter = auragold_sp_performance_person_filter_sql($conn, 'hdr', 'PERSON_COL', $salesperson);

        $branch_si = function_exists('auragold_sale_invoices_branch_where_sql')
            ? auragold_sale_invoices_branch_where_sql($conn, 'hdr')
            : '';
        $branch_psi = auragold_sp_performance_pos_branch_where_sql($conn, 'hdr');
        $branch_so = auragold_sp_performance_sale_order_branch_where_sql($conn, 'hdr');
        $branch_pi = function_exists('auragold_purchase_invoices_branch_where_sql')
            ? auragold_purchase_invoices_branch_where_sql($conn, 'hdr')
            : '';

        $sources = [
            [
                'table' => 'tbl_sale_invoices',
                'date_col' => 'invoice_date',
                'no_col' => 'invoice_no',
                'party_col' => 'customer_name',
                'person_col' => 'sales_person',
                'amount_col' => 'grand_total',
                'src' => 'si',
                'label' => 'Sale Invoice',
                'branch' => $branch_si,
            ],
            [
                'table' => 'tbl_pos_sale_invoices',
                'date_col' => 'invoice_date',
                'no_col' => 'invoice_no',
                'party_col' => 'customer_name',
                'person_col' => 'sales_person',
                'amount_col' => 'grand_total',
                'src' => 'psi',
                'label' => 'POS Sale',
                'branch' => $branch_psi,
            ],
            [
                'table' => 'tbl_sale_orders',
                'date_col' => 'order_date',
                'no_col' => 'order_no',
                'party_col' => 'customer_name',
                'person_col' => 'sales_person',
                'amount_col' => 'grand_total',
                'src' => 'so',
                'label' => 'Sale Order',
                'branch' => $branch_so,
            ],
            [
                'table' => 'tbl_purchase_invoices',
                'date_col' => 'invoice_date',
                'no_col' => 'invoice_no',
                'party_col' => 'supplier_name',
                'person_col' => 'purchase_person',
                'amount_col' => 'grand_total',
                'src' => 'pi',
                'label' => 'Purchase Invoice',
                'branch' => $branch_pi,
            ],
        ];

        $raw_rows = [];
        foreach ($sources as $def) {
            $tbl = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $def['table']);
            $date_col = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $def['date_col']);
            $no_col = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $def['no_col']);
            $party_col = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $def['party_col']);
            $person_col = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $def['person_col']);
            $amount_col = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $def['amount_col']);
            if ($tbl === '' || $date_col === '' || $no_col === '') {
                continue;
            }

            $chk = @mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $tbl) . "'");
            if (!$chk || mysqli_num_rows($chk) === 0) {
                if ($chk) {
                    mysqli_free_result($chk);
                }
                continue;
            }
            mysqli_free_result($chk);

            $branch = (string) ($def['branch'] ?? '');
            $pf = str_replace('PERSON_COL', $person_col, $person_filter);
            $label = str_replace("'", "''", (string) $def['label']);
            $src = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $def['src']);

            $sql = "
                SELECT
                    '{$label}' AS doc_type,
                    '{$src}' AS doc_src,
                    hdr.id AS doc_id,
                    IFNULL(hdr.{$no_col}, '') AS doc_no,
                    DATE_FORMAT(hdr.{$date_col}, '%d-%m-%Y') AS doc_date,
                    hdr.{$date_col} AS doc_date_sort,
                    IFNULL(hdr.{$party_col}, '') AS party_name,
                    CAST(COALESCE(hdr.{$amount_col}, 0) AS DECIMAL(18, 4)) AS amount_num,
                    IFNULL(hdr.status, '') AS doc_status
                FROM {$tbl} hdr
                WHERE DATE(hdr.{$date_col}) BETWEEN '{$from_e}' AND '{$to_e}'
                  {$status_where}
                  {$branch}
                  {$pf}
                ORDER BY hdr.{$date_col} DESC, hdr.id DESC
            ";
            $part = function_exists('getList') ? getList($sql) : [];
            if (is_array($part) && $part !== []) {
                $raw_rows = array_merge($raw_rows, $part);
            }
        }

        usort($raw_rows, static function ($a, $b): int {
            $da = (string) ($a['doc_date_sort'] ?? '');
            $db = (string) ($b['doc_date_sort'] ?? '');
            if ($da !== $db) {
                return strcmp($db, $da);
            }
            $na = strtolower((string) ($a['doc_no'] ?? ''));
            $nb = strtolower((string) ($b['doc_no'] ?? ''));

            return strcmp($nb, $na);
        });

        $out = [];
        foreach ($raw_rows as $r) {
            $doc_src = (string) ($r['doc_src'] ?? '');
            $doc_id = (int) ($r['doc_id'] ?? 0);
            $out[] = [
                'doc_type' => (string) ($r['doc_type'] ?? ''),
                'doc_no' => (string) ($r['doc_no'] ?? ''),
                'date' => (string) ($r['doc_date'] ?? ''),
                'party' => (string) ($r['party_name'] ?? ''),
                'amount' => number_format((float) ($r['amount_num'] ?? 0), 2, '.', ''),
                'status' => (string) ($r['doc_status'] ?? ''),
                'url' => auragold_sp_performance_doc_url($doc_src, $doc_id),
            ];
        }

        return $out;
    }
}
