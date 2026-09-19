<?php

/**
 * Diamond & Stone analysis — paginated data load (shared by page + AJAX).
 * Requires diamond_stone_analysis_roll_up_include.php loaded first ($stock_roll_up_sql).
 */

if (!function_exists('auragold_dsa_fetch_table_data')) {
    /**
     * @return array{
     *   stock_data: array<int, array<string, mixed>>,
     *   total_stock: int,
     *   total_pages: int,
     *   offset: int,
     *   totals: array<string, mixed>
     * }
     */
    function auragold_dsa_fetch_table_data(mysqli $conn, string $active_tab, int $page, int $per_page, array $options = []): array
    {
        global $stock_roll_up_sql;

        $include_totals = !array_key_exists('include_totals', $options) || !empty($options['include_totals']);
        $skip_rows = !empty($options['skip_rows']);

        $page = max(1, $page);
        $per_page = max(10, min(100, $per_page));
        $offset = ($page - 1) * $per_page;

        $stock_data = [];
        $total_stock = 0;
        $total_pages = 1;
        $totals = [];

        if ($active_tab === 'current-stock') {
            $visible_sql = auragold_sql_gsa_current_stock_row_visible('stock_grp');
            $count_sql = "
                SELECT COUNT(*) AS total FROM (
                    SELECT 1
                    FROM ($stock_roll_up_sql) stock_grp
                    WHERE $visible_sql
                ) AS grp";

            if (!$skip_rows) {
                $stock_query = "
                    SELECT 
                        stock_grp.*,
                        (COALESCE(stock_grp.inward_receipt_qty_sum, 0) - COALESCE(stock_grp.outward_qty_sum, 0)) AS display_qty,
                        (COALESCE(stock_grp.inward_receipt_pure_sum, 0) - COALESCE(stock_grp.outward_pure_sum, 0)) AS display_pure_weight,
                        (COALESCE(stock_grp.inward_receipt_weight_sum, 0) - COALESCE(stock_grp.outward_weight_sum, 0)) AS display_gross_weight
                    FROM ($stock_roll_up_sql) stock_grp
                    WHERE $visible_sql
                    ORDER BY stock_grp.product_name ASC, stock_grp.product_id DESC
                    LIMIT $per_page OFFSET $offset";
                $stock_data = getList($stock_query);
                if (!is_array($stock_data)) {
                    $stock_data = [];
                }
            }

            if ($include_totals) {
                $totals_query = "
                    SELECT 
                        SUM(display_qty) as total_qty,
                        SUM(display_gross_weight) as total_gross_weight,
                        SUM(display_pure_weight) as total_pure_weight,
                        SUM(display_gross_weight) as total_net_weight,
                        SUM(row_diamond_ct) as total_carat,
                        SUM(row_diamond_ct) as total_diamond_ct,
                        SUM(0) as total_stone_weight,
                        SUM(0) as total_stone_ct,
                        SUM(value) as total_purchase_amount
                    FROM (
                        SELECT 
                            stock_grp.*,
                            (COALESCE(stock_grp.inward_receipt_qty_sum, 0) - COALESCE(stock_grp.outward_qty_sum, 0)) AS display_qty,
                            (COALESCE(stock_grp.inward_receipt_pure_sum, 0) - COALESCE(stock_grp.outward_pure_sum, 0)) AS display_pure_weight,
                            (COALESCE(stock_grp.inward_receipt_weight_sum, 0) - COALESCE(stock_grp.outward_weight_sum, 0)) AS display_gross_weight,
                            (CASE 
                                WHEN COALESCE(stock_grp.carat, 0) > 0 THEN COALESCE(stock_grp.carat, 0)
                                WHEN ABS(COALESCE(stock_grp.inward_receipt_pure_sum, 0) - COALESCE(stock_grp.outward_pure_sum, 0)) > 0.0001 
                                THEN ABS(COALESCE(stock_grp.inward_receipt_pure_sum, 0) - COALESCE(stock_grp.outward_pure_sum, 0)) / 0.2 
                                ELSE 0 
                            END) AS row_diamond_ct
                        FROM ($stock_roll_up_sql) stock_grp
                        WHERE $visible_sql
                    ) as display_totals";
                $totals = getRecord($totals_query);
                if (!is_array($totals)) {
                    $totals = [];
                }
            }
        } else {
            $count_sql = "
                SELECT COUNT(*) AS total FROM (
                    SELECT 1 FROM ($stock_roll_up_sql) stock_grp
                ) AS grp";

            if (!$skip_rows) {
                $stock_query = "
                    SELECT stock_grp.*
                    FROM ($stock_roll_up_sql) stock_grp
                    ORDER BY stock_grp.product_name ASC, stock_grp.product_id DESC
                    LIMIT $per_page OFFSET $offset";
                $stock_data = getList($stock_query);
                if (!is_array($stock_data)) {
                    $stock_data = [];
                }
            }

            if ($include_totals) {
                $totals_query = "
                    SELECT 
                        SUM(COALESCE(stock_grp.sd_gross_opening, 0)) AS t_sd_gross_opening,
                        SUM(COALESCE(stock_grp.sd_gross_in, 0)) AS t_sd_gross_in,
                        SUM(COALESCE(stock_grp.sd_gross_out, 0)) AS t_sd_gross_out,
                        SUM(COALESCE(stock_grp.sd_gross_opening, 0) + COALESCE(stock_grp.sd_gross_in, 0) - COALESCE(stock_grp.sd_gross_out, 0)) AS t_sd_gross_closing,
                        SUM(COALESCE(stock_grp.sd_pure_opening, 0)) AS t_sd_pure_opening,
                        SUM(COALESCE(stock_grp.sd_pure_in, 0)) AS t_sd_pure_in,
                        SUM(COALESCE(stock_grp.sd_pure_out, 0)) AS t_sd_pure_out,
                        SUM(COALESCE(stock_grp.sd_pure_opening, 0) + COALESCE(stock_grp.sd_pure_in, 0) - COALESCE(stock_grp.sd_pure_out, 0)) AS t_sd_pure_closing,
                        SUM(COALESCE(stock_grp.sd_pcs_opening, 0)) AS t_sd_pcs_opening,
                        SUM(COALESCE(stock_grp.sd_pcs_in, 0)) AS t_sd_pcs_in,
                        SUM(COALESCE(stock_grp.sd_pcs_out, 0)) AS t_sd_pcs_out,
                        SUM(COALESCE(stock_grp.sd_pcs_opening, 0) + COALESCE(stock_grp.sd_pcs_in, 0) - COALESCE(stock_grp.sd_pcs_out, 0)) AS t_sd_pcs_closing
                    FROM ($stock_roll_up_sql) stock_grp";
                $totals = getRecord($totals_query);
                if (!is_array($totals)) {
                    $totals = [];
                }
            }
        }

        $total_stock_record = getRecord($count_sql);
        $total_stock = $total_stock_record ? (int) ($total_stock_record['total'] ?? 0) : 0;
        $total_pages = $total_stock > 0 ? (int) ceil($total_stock / $per_page) : 1;

        return [
            'stock_data' => $stock_data,
            'total_stock' => $total_stock,
            'total_pages' => max(1, $total_pages),
            'offset' => $offset,
            'totals' => $totals,
        ];
    }
}
