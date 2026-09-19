<?php

/**
 * SQL joins: tbl_purchase_invoice_items → Diamond & Stones stock list display values.
 */

require_once __DIR__ . '/diamond_barcode_cursor.php';

if (!function_exists('dass_pi_tbl_exists')) {
    function dass_pi_tbl_exists(mysqli $conn, string $table): bool
    {
        static $cache = [];
        if (isset($cache[$table])) {
            return $cache[$table];
        }
        $t = mysqli_real_escape_string($conn, $table);
        $r = @mysqli_query($conn, "SHOW TABLES LIKE '$t'");
        $ok = ($r && mysqli_num_rows($r) > 0);
        if ($r) {
            mysqli_free_result($r);
        }
        $cache[$table] = $ok;
        return $ok;
    }
}

if (!function_exists('dass_pi_col_exists')) {
    function dass_pi_col_exists(mysqli $conn, string $table, string $column): bool
    {
        static $cache = [];
        $key = strtolower($table . '.' . $column);
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        $t = mysqli_real_escape_string($conn, $table);
        $c = mysqli_real_escape_string($conn, $column);
        $r = @mysqli_query($conn, "SHOW COLUMNS FROM `$t` LIKE '$c'");
        $ok = ($r && mysqli_num_rows($r) > 0);
        if ($r) {
            mysqli_free_result($r);
        }
        $cache[$key] = $ok;
        return $ok;
    }
}

if (!function_exists('dass_pi_items_table_exists')) {
    function dass_pi_items_table_exists(mysqli $conn): bool
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        $t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_purchase_invoice_items'");
        $ok = ($t && mysqli_num_rows($t) > 0);
        if ($t) {
            mysqli_free_result($t);
        }
        return $ok;
    }
}

if (!function_exists('dass_pi_jewellery_category_sql')) {
    function dass_pi_jewellery_category_sql(string $alias = 'pii'): string
    {
        $a = preg_match('/^[a-zA-Z0-9_]+$/', $alias) ? $alias : 'pii';
        return "(
            TRIM(IFNULL({$a}.diamond_category, '')) = ''
            OR LOWER(TRIM({$a}.diamond_category)) LIKE '%jewel%'
        )";
    }
}

if (!function_exists('dass_pi_barcode_match_sql')) {
    /** Match stock barcode against PI physical barcode or shared tag (barcode_no). */
    function dass_pi_barcode_match_sql(string $pii_alias, string $stock_alias = 's'): string
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $pii_alias)) {
            $pii_alias = 'pii';
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $stock_alias)) {
            $stock_alias = 's';
        }
        $base = "TRIM(IFNULL({$pii_alias}.barcode, '')) COLLATE utf8mb4_general_ci = TRIM(IFNULL({$stock_alias}.barcode, '')) COLLATE utf8mb4_general_ci";
        if (!function_exists('auragold_pii_has_barcode_no_column') || !auragold_pii_has_barcode_no_column()) {
            return $base;
        }
        return "(
            {$base}
            OR TRIM(IFNULL({$pii_alias}.barcode_no, '')) COLLATE utf8mb4_general_ci = TRIM(IFNULL({$stock_alias}.barcode, '')) COLLATE utf8mb4_general_ci
        )";
    }
}

if (!function_exists('dass_pi_stock_join_sql')) {
    /**
     * Join latest purchase-invoice line for each stock barcode (tab-aware).
     */
    function dass_pi_stock_join_sql(mysqli $conn, string $tab): string
    {
        $tab = function_exists('dass_stock_normalize_tab') ? dass_stock_normalize_tab($tab) : 'jewellery';
        $jewel_order = dass_pi_jewellery_category_sql('pii');

        if ($tab === 'diamond') {
            $cat_filter = "AND LOWER(TRIM(IFNULL(pii.diamond_category, ''))) LIKE '%diamond%' AND LOWER(TRIM(IFNULL(pii.diamond_category, ''))) NOT LIKE '%stone%'";
        } elseif ($tab === 'gemstone') {
            $cat_filter = "AND LOWER(TRIM(IFNULL(pii.diamond_category, ''))) LIKE '%gem%'";
        } else {
            $cat_filter = '';
        }

        $match = dass_pi_barcode_match_sql('pii', 's');
        $order = ($tab === 'jewellery')
            ? "CASE WHEN {$jewel_order} THEN 0 ELSE 1 END, pii.id DESC"
            : 'pii.id DESC';

        $loc_join = dass_pi_tbl_exists($conn, 'tbl_locations')
            ? 'LEFT JOIN tbl_locations pij_loc ON pij_loc.id = pij.location_id'
            : '';

        $has_mgi = dass_pi_col_exists($conn, 'tbl_purchase_invoice_items', 'merge_group_index');
        $has_bc_no = function_exists('auragold_pii_has_barcode_no_column') && auragold_pii_has_barcode_no_column();

        if ($has_mgi && $has_bc_no) {
            $sib_link = "(
                (pii_j.merge_group_index IS NOT NULL AND pii_j.merge_group_index <> ''
                    AND pii_x.merge_group_index = pii_j.merge_group_index)
                OR (
                    (pii_j.merge_group_index IS NULL OR pii_j.merge_group_index = '')
                    AND (
                        TRIM(IFNULL(pii_x.barcode_no, '')) = TRIM(IFNULL(pii_j.barcode_no, ''))
                        OR TRIM(IFNULL(pii_x.barcode_no, '')) = TRIM(IFNULL(pii_j.barcode, ''))
                        OR TRIM(IFNULL(pii_x.barcode, '')) = TRIM(IFNULL(pii_j.barcode_no, ''))
                    )
                )
            )";
        } elseif ($has_mgi) {
            $sib_link = "(
                pii_j.merge_group_index IS NOT NULL AND pii_j.merge_group_index <> ''
                AND pii_x.merge_group_index = pii_j.merge_group_index
            )";
        } elseif ($has_bc_no) {
            $sib_link = "(
                TRIM(IFNULL(pii_x.barcode_no, '')) = TRIM(IFNULL(pii_j.barcode_no, ''))
                OR TRIM(IFNULL(pii_x.barcode_no, '')) = TRIM(IFNULL(pii_j.barcode, ''))
                OR TRIM(IFNULL(pii_x.barcode, '')) = TRIM(IFNULL(pii_j.barcode_no, ''))
            )";
        } else {
            $sib_link = 'pii_x.invoice_id = pii_j.invoice_id';
        }

        return "
    LEFT JOIN tbl_purchase_invoice_items pij ON pij.id = (
        SELECT pii.id
        FROM tbl_purchase_invoice_items pii
        WHERE pii.status = 1
        AND {$match}
        {$cat_filter}
        ORDER BY {$order}
        LIMIT 1
    )
    LEFT JOIN tbl_purchase_invoices pi_pij ON pi_pij.id = pij.invoice_id
    {$loc_join}
    LEFT JOIN (
        SELECT
            pii_j.id AS anchor_pii_id,
            SUM(CASE
                WHEN LOWER(TRIM(IFNULL(pii_x.diamond_category, ''))) IN ('diamonds', 'diamond')
                    OR (LOWER(TRIM(IFNULL(pii_x.diamond_category, ''))) LIKE '%diamond%'
                        AND LOWER(TRIM(IFNULL(pii_x.diamond_category, ''))) NOT LIKE '%jewel%')
                THEN COALESCE(pii_x.stone_weight, 0)
                WHEN LOWER(TRIM(IFNULL(pii_x.diamond_category, ''))) IN ('gemstones', 'gemstone')
                    OR LOWER(TRIM(IFNULL(pii_x.diamond_category, ''))) LIKE '%gem%'
                THEN COALESCE(pii_x.stone_weight, 0)
                ELSE 0
            END) AS pi_agg_stone_wt,
            SUM(CASE
                WHEN LOWER(TRIM(IFNULL(pii_x.diamond_category, ''))) IN ('diamonds', 'diamond', 'gemstones', 'gemstone')
                    OR LOWER(TRIM(IFNULL(pii_x.diamond_category, ''))) LIKE '%diamond%'
                    OR LOWER(TRIM(IFNULL(pii_x.diamond_category, ''))) LIKE '%gem%'
                THEN COALESCE(CAST(NULLIF(TRIM(pii_x.carat), '') AS DECIMAL(14,4)), 0)
                ELSE 0
            END) AS pi_agg_carat,
            SUM(CASE
                WHEN LOWER(TRIM(IFNULL(pii_x.diamond_category, ''))) NOT LIKE '%jewel%'
                    AND TRIM(IFNULL(pii_x.diamond_category, '')) <> ''
                THEN COALESCE(pii_x.stone_amount, 0)
                ELSE 0
            END) AS pi_agg_stone_amt,
            SUM(CASE
                WHEN LOWER(TRIM(IFNULL(pii_x.diamond_category, ''))) NOT LIKE '%jewel%'
                    AND TRIM(IFNULL(pii_x.diamond_category, '')) <> ''
                THEN COALESCE(pii_x.diamond_amount, 0)
                ELSE 0
            END) AS pi_agg_diamond_amt
        FROM tbl_purchase_invoice_items pii_j
        INNER JOIN tbl_purchase_invoice_items pii_x
            ON pii_x.invoice_id = pii_j.invoice_id
            AND pii_x.status = 1
            AND pii_x.id <> pii_j.id
            AND {$sib_link}
        WHERE pii_j.status = 1
        GROUP BY pii_j.id
    ) pi_sib ON pi_sib.anchor_pii_id = pij.id
        ";
    }
}

if (!function_exists('dass_pi_coalesce_num')) {
    function dass_pi_coalesce_num(string $sj_expr, string $pij_expr, string $fallback_expr = 'NULL'): string
    {
        return "COALESCE(
            NULLIF({$sj_expr}, 0),
            NULLIF({$pij_expr}, 0),
            NULLIF({$fallback_expr}, 0)
        )";
    }
}

if (!function_exists('dass_pi_coalesce_str')) {
    function dass_pi_coalesce_str(string $sj_expr, string $pij_expr, string $fallback_expr = "''"): string
    {
        return "COALESCE(
            NULLIF(TRIM({$sj_expr}), ''),
            NULLIF(TRIM({$pij_expr}), ''),
            NULLIF(TRIM({$fallback_expr}), '')
        )";
    }
}

if (!function_exists('dass_pi_stock_select_overrides')) {
    /**
     * SELECT expressions that prefer purchase-invoice values over sparse stock_journal audit rows.
     *
     * @return array<string, string> alias => SQL expression
     */
    function dass_pi_stock_select_overrides(string $tab, bool $has_loc_join = true): array
    {
        $tab = function_exists('dass_stock_normalize_tab') ? dass_stock_normalize_tab($tab) : 'jewellery';

        $agg_stone = 'COALESCE(pi_sib.pi_agg_stone_wt, pij.stone_weight, sj.stone_weight)';
        $agg_carat = ($tab === 'jewellery')
            ? 'COALESCE(NULLIF(pi_sib.pi_agg_carat, 0), CAST(NULLIF(TRIM(pij.carat), \'\') AS DECIMAL(14,4)), CAST(NULLIF(TRIM(pc.carat), \'\') AS DECIMAL(14,4)), sj.sj_karat)'
            : 'COALESCE(CAST(NULLIF(TRIM(pij.carat), \'\') AS DECIMAL(14,4)), CAST(NULLIF(TRIM(pc.carat), \'\') AS DECIMAL(14,4)), sj.sj_karat)';

        $loc_expr = $has_loc_join ? 'pij_loc.name' : "''";

        return [
            'product_name' => dass_pi_coalesce_str('p.name', 'pij.product_name'),
            'supplier_name' => dass_pi_coalesce_str('pi.supplier_name', 'pi_pij.supplier_name'),
            'invoice_no' => dass_pi_coalesce_str('sj.invoice_no', 'pi_pij.invoice_no'),
            'huid_no' => dass_pi_coalesce_str('sj.huid_no', 'pij.huid'),
            'design_no' => dass_pi_coalesce_str('sj.design_no', 'pij.design_no'),
            'sj_location' => dass_pi_coalesce_str('sj.sj_location', $loc_expr),
            'sj_calculation' => dass_pi_coalesce_str('sj.sj_calculation', 'pij.calculation_type'),
            'sj_gross_weight' => dass_pi_coalesce_num('sj.gross_weight', 'pij.gross_weight', 's.opening_weight'),
            'sj_less_weight' => dass_pi_coalesce_num('sj.less_weight', 'pij.less_weight'),
            'sj_net_weight' => dass_pi_coalesce_num('sj.net_weight', 'pij.net_weight'),
            'sj_purity_weight' => dass_pi_coalesce_num('sj.purity_weight', 'pij.purity_weight'),
            'sj_pure_weight' => dass_pi_coalesce_num('sj.pure_weight', 'pij.pure_weight'),
            'sj_quantity' => dass_pi_coalesce_num('sj.sj_quantity', 'pij.quantity', 'bal.bal_qty'),
            'sj_karat' => dass_pi_coalesce_str('sj.sj_karat', 'pij.carat', 'pc.carat'),
            'sj_rate' => dass_pi_coalesce_num('sj.sj_rate', 'pij.rate'),
            'metal_rate_disp' => dass_pi_coalesce_num('sj.sj_rate', 'pij.metal_rate', 'pij.rate'),
            'purchase_amount' => dass_pi_coalesce_num('sj.purchase_amount', 'pij.purchase_amount', 'pij.net_amount'),
            'sale_amount' => dass_pi_coalesce_num('sj.sale_amount', 'pij.sale_amount'),
            'making_amount' => dass_pi_coalesce_num('sj.making_amount', 'pij.making_amount'),
            'making_rate' => dass_pi_coalesce_num('sj.making_rate', 'pij.making_rate'),
            'minimum_price' => dass_pi_coalesce_num('sj.minimum_price', 'pij.min_price'),
            'stone_weight' => $agg_stone,
            'stone_amount' => dass_pi_coalesce_num('sj.stone_amount', 'pij.stone_amount', 'pi_sib.pi_agg_stone_amt'),
            'diamond_amount' => dass_pi_coalesce_num('sj.diamond_amount', 'pij.diamond_amount', 'pi_sib.pi_agg_diamond_amt'),
            'net_amount' => dass_pi_coalesce_num('sj.net_amount', 'pij.net_amount'),
            'net_amt_with_tax' => dass_pi_coalesce_num('sj.net_amt_with_tax', 'pij.net_amt_with_tax'),
            'tax_amount' => dass_pi_coalesce_num('sj.tax_amount', 'pij.tax'),
            'other_amount' => dass_pi_coalesce_num('sj.other_amount', 'pij.other_amount'),
            'setting_charge' => dass_pi_coalesce_num('sj.setting_charge', 'pij.setting_charge'),
            'gold_loss_1' => dass_pi_coalesce_num('sj.gold_loss_1', 'pij.gold_loss_wt', 'sj.wastage_wt'),
            'gold_loss_2' => dass_pi_coalesce_num('sj.gold_loss_2', 'pij.gold_loss_value'),
            'wastage_wt' => dass_pi_coalesce_num('sj.wastage_wt', 'pij.wastage_wt'),
            'discount_per' => dass_pi_coalesce_num('sj.discount_per', 'pij.discount_per'),
            'hallmark_amount' => dass_pi_coalesce_num('sj.hallmark_amount', 'pij.hallmark_amount'),
            'metal_value' => dass_pi_coalesce_num('sj.metal_value', 'pij.metal_value', 'pij.amount'),
            'pc_carat' => $agg_carat,
        ];
    }
}
