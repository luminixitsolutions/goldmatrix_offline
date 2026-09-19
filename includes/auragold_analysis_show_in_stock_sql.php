<?php
/**
 * SQL for stock analysis: include rows only when Product Opening "Show In Stock" is on
 * (tbl_product_branch_settings.is_stock_item = 1 for product_id + stock.branch_id).
 * If no row exists in that table for the pair, fall back to legacy tbl_products.is_stock_item
 * (defaults to 1 for NULL so old data still appears until saved from Product Opening).
 */
if (!function_exists('auragold_sql_show_in_stock_for_stock_table')) {
    function auragold_sql_show_in_stock_for_stock_table(string $stockAlias = 's', string $productAlias = 'p'): string {
        $stockAlias   = preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $stockAlias) ? $stockAlias : 's';
        $productAlias = preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $productAlias) ? $productAlias : 'p';
        return '('
            . 'EXISTS(SELECT 1 FROM tbl_product_branch_settings auragold_pbs_si '
            . "WHERE auragold_pbs_si.product_id = {$stockAlias}.product_id AND auragold_pbs_si.branch_id = {$stockAlias}.branch_id AND auragold_pbs_si.is_stock_item = 1) "
            . "OR (NOT EXISTS(SELECT 1 FROM tbl_product_branch_settings auragold_pbs_si0 WHERE auragold_pbs_si0.product_id = {$stockAlias}.product_id AND auragold_pbs_si0.branch_id = {$stockAlias}.branch_id) "
            . "AND COALESCE({$productAlias}.is_stock_item, 1) = 1)"
        . ')';
    }
}
if (!function_exists('auragold_sql_show_in_stock_for_product_and_stock_subquery')) {
    function auragold_sql_show_in_stock_for_product_and_stock_subquery(string $stockAlias = 's0', string $productAlias = 'p'): string {
        $stockAlias   = preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $stockAlias) ? $stockAlias : 's0';
        $productAlias = preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $productAlias) ? $productAlias : 'p';
        return '('
            . 'EXISTS(SELECT 1 FROM tbl_product_branch_settings pbs_s '
            . "WHERE pbs_s.product_id = {$stockAlias}.product_id AND pbs_s.branch_id = {$stockAlias}.branch_id AND pbs_s.is_stock_item = 1) "
            . "OR (NOT EXISTS(SELECT 1 FROM tbl_product_branch_settings pbs_s0 WHERE pbs_s0.product_id = {$stockAlias}.product_id AND pbs_s0.branch_id = {$stockAlias}.branch_id) "
            . "AND COALESCE({$productAlias}.is_stock_item, 1) = 1)"
        . ')';
    }
}

if (!function_exists('auragold_sql_gsa_product_marked_show_in_stock')) {
    /**
     * True when Product Opening "Show In Stock" is on for a rolled-up (product_id, branch_id) pair.
     */
    function auragold_sql_gsa_product_marked_show_in_stock(string $grpAlias = 'stock_grp'): string {
        $grpAlias = preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $grpAlias) ? $grpAlias : 'stock_grp';
        return '('
            . 'EXISTS(SELECT 1 FROM tbl_product_branch_settings gsa_pbs_vis '
            . "WHERE gsa_pbs_vis.product_id = {$grpAlias}.product_id AND gsa_pbs_vis.branch_id = {$grpAlias}.branch_id AND gsa_pbs_vis.is_stock_item = 1) "
            . 'OR (NOT EXISTS(SELECT 1 FROM tbl_product_branch_settings gsa_pbs_vis0 '
            . "WHERE gsa_pbs_vis0.product_id = {$grpAlias}.product_id AND gsa_pbs_vis0.branch_id = {$grpAlias}.branch_id) "
            . "AND EXISTS(SELECT 1 FROM tbl_products gsa_p_vis WHERE gsa_p_vis.id = {$grpAlias}.product_id AND COALESCE(gsa_p_vis.is_stock_item, 1) = 1))"
        . ')';
    }
}

if (!function_exists('auragold_sql_gsa_current_stock_row_visible')) {
    /**
     * Current Stock tab: show rows with movement OR products marked Show In Stock (incl. no-barcode / zero opening).
     */
    function auragold_sql_gsa_current_stock_row_visible(string $grpAlias = 'stock_grp'): string {
        $grpAlias = preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $grpAlias) ? $grpAlias : 'stock_grp';
        return '('
            . "COALESCE({$grpAlias}.inward_receipt_qty_sum, 0) > 0.0001 "
            . "OR COALESCE({$grpAlias}.outward_qty_sum, 0) > 0.0001 "
            . "OR COALESCE({$grpAlias}.inward_receipt_weight_sum, 0) > 0.0001 "
            . "OR COALESCE({$grpAlias}.outward_weight_sum, 0) > 0.0001 "
            . "OR ABS(COALESCE({$grpAlias}.inward_receipt_weight_sum, 0) - COALESCE({$grpAlias}.outward_weight_sum, 0)) > 0.0001 "
            . 'OR ' . auragold_sql_gsa_product_marked_show_in_stock($grpAlias)
        . ')';
    }
}
