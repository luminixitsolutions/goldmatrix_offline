<?php

/**
 * Diamond & Stone analysis — roll-up SQL and filters (same GET keys as diamond-stone-analysis.php).
 */
require_once __DIR__ . '/auragold_analysis_show_in_stock_sql.php';

$search_raw = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
$search_term = $search_raw !== '' ? esc($search_raw) : '';
$branch_filter = isset($_GET['branch']) ? (int) $_GET['branch'] : 0;
$metal_filter = isset($_GET['metal']) ? (int) $_GET['metal'] : 0;

$dsa_effective_branch_id = function_exists('auragold_effective_branch_id') ? (int) auragold_effective_branch_id() : 0;
if ($branch_filter <= 0 && $dsa_effective_branch_id > 0 && !isset($_GET['branch'])) {
    $branch_filter = $dsa_effective_branch_id;
}

// Dropdown + query scope: login main branch and its sub-branches only (not other company mains).
$dsa_tree_root_id = function_exists('auragold_branch_stock_transfer_tree_root_id')
    ? (int) auragold_branch_stock_transfer_tree_root_id()
    : (function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0);

$dsa_sub_branch_login = false;
$dsa_sub_branch_id = 0;
$dsa_login_br = null;
if ($dsa_effective_branch_id > 0 && function_exists('getRecordMaster')) {
    $dsa_login_br = getRecordMaster(
        'SELECT id, name, IFNULL(main_branch_id, 0) AS mb FROM tbl_branches WHERE id = '
        . (int) $dsa_effective_branch_id . ' AND status = 1 LIMIT 1'
    );
}
if ($dsa_effective_branch_id > 0) {
    $dsa_is_registry_sub = ($dsa_login_br && (int) ($dsa_login_br['mb'] ?? 0) > 0);
    $dsa_is_tree_sub = ($dsa_tree_root_id > 0 && $dsa_effective_branch_id !== $dsa_tree_root_id);
    if ($dsa_is_registry_sub || $dsa_is_tree_sub) {
        $dsa_sub_branch_login = true;
        $dsa_sub_branch_id = $dsa_effective_branch_id;
    }
}

$branches = function_exists('auragold_filter_branches_for_login_scope')
    ? auragold_filter_branches_for_login_scope()
    : [];
if (!is_array($branches)) {
    $branches = [];
}
if ($dsa_sub_branch_login && $dsa_sub_branch_id > 0) {
    $dsa_sub_branch_name = $dsa_login_br ? trim((string) ($dsa_login_br['name'] ?? '')) : '';
    if ($dsa_sub_branch_name === '' && !empty($_SESSION['working_branch_name'])) {
        $dsa_sub_branch_name = trim((string) $_SESSION['working_branch_name']);
    }
    $branches = [[
        'id'   => $dsa_sub_branch_id,
        'name' => $dsa_sub_branch_name !== '' ? $dsa_sub_branch_name : ('Branch #' . $dsa_sub_branch_id),
    ]];
    $branch_filter = $dsa_sub_branch_id;
}
$dsa_allowed_branch_ids = array_values(array_filter(array_map('intval', array_column($branches, 'id'))));
if ($branch_filter > 0 && !in_array($branch_filter, $dsa_allowed_branch_ids, true)) {
    $branch_filter = 0;
}

$scope_metals = getList("SELECT id, display_name AS name FROM tbl_metal WHERE status = 1 AND display_name = 'Diamond & Stones' ORDER BY display_name ASC");
$scope_metal_ids = array_map('intval', array_column($scope_metals ?: [], 'id'));
if (empty($scope_metal_ids)) {
    $scope_metal_ids = [4];
}
if (empty($scope_metals) && !empty($scope_metal_ids)) {
    $scope_metals = getList("SELECT id, display_name AS name FROM tbl_metal WHERE status = 1 AND id IN (" . implode(',', $scope_metal_ids) . ") ORDER BY display_name ASC");
}

$where_clause = "s.status = 1 AND s.stock_type IN ('opening', 'purchase', 'stock_journal', 'outward', 'balance', 'inward', 'sale_return')";
if ($search_term != '') {
    $where_clause .= " AND (p.name LIKE '%$search_term%' OR p.article LIKE '%$search_term%' OR p.alternate_name LIKE '%$search_term%')";
}
if ($branch_filter > 0) {
    $where_clause .= " AND s.branch_id = $branch_filter";
} elseif (!empty($dsa_allowed_branch_ids)) {
    // All Branches: stay within login main + sub-branches only.
    $where_clause .= ' AND s.branch_id IN (' . implode(',', $dsa_allowed_branch_ids) . ')';
}
$where_clause .= " AND s.metal_id IN (" . implode(',', $scope_metal_ids) . ")";
if ($metal_filter > 0 && in_array($metal_filter, $scope_metal_ids, true)) {
    $where_clause .= " AND s.metal_id = $metal_filter";
}
$where_clause .= ' AND ' . auragold_sql_show_in_stock_for_stock_table('s', 'p');

$dsa_in_stock_types = "'opening','purchase','stock_journal','balance','inward','sale_return'";
$dsa_purity_factor = '(CASE WHEN COALESCE(s.opening_purity, 0) <= 1 THEN COALESCE(s.opening_purity, 0) ELSE COALESCE(s.opening_purity, 0) / 100 END)';
$dsa_mov_qty = 'COALESCE(NULLIF(s.opening_qty, 0), NULLIF(s.current_qty, 0), 0)';
$dsa_mov_wt = 'COALESCE(NULLIF(s.opening_weight, 0), NULLIF(s.current_weight, 0), 0)';
$dsa_mov_wt_abs = 'ABS(' . $dsa_mov_wt . ')';
$dsa_mov_qty_abs = 'ABS(' . $dsa_mov_qty . ')';

$dsa_join_location = '';
$dsa_loc_sql = "MAX('') AS location_name";
$dsa_has_loc_column = false;
$dsa_loc_chk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_product_characteristics LIKE 'location_id'");
$dsa_loc_tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_location'");
$dsa_has_loc_table = ($dsa_loc_tbl && mysqli_num_rows($dsa_loc_tbl) > 0);
if ($dsa_loc_tbl) {
    mysqli_free_result($dsa_loc_tbl);
}
if ($dsa_loc_chk && mysqli_num_rows($dsa_loc_chk) > 0 && $dsa_has_loc_table) {
    $dsa_has_loc_column = true;
    $dsa_join_location = "\n    LEFT JOIN tbl_location loc_pc ON loc_pc.id = pc.location_id";
    $dsa_loc_sql = 'MAX(loc_pc.name) AS location_name';
}
if ($dsa_loc_chk) {
    mysqli_free_result($dsa_loc_chk);
}

$stock_inner_from = "
    FROM tbl_stock s
    LEFT JOIN tbl_products p ON s.product_id = p.id
    LEFT JOIN tbl_metal m ON s.metal_id = m.id
    LEFT JOIN tbl_branches b ON s.branch_id = b.id
    LEFT JOIN tbl_product_characteristics pc
        ON s.product_characteristic_id = pc.id
    $dsa_join_location
    WHERE $where_clause
    GROUP BY s.product_id, s.branch_id, s.metal_id, s.product_characteristic_id
";
$stock_inner_select = "
    SELECT 
        s.product_id,
        s.product_characteristic_id,
        s.branch_id,
        s.metal_id,
        MAX(p.name) as product_name,
        MAX(p.article) as article,
        MAX(p.alternate_name) as alternate_name,
        MAX(m.display_name) as metal_name,
        MAX(b.name) as branch_name,
        $dsa_loc_sql,
        MAX(pc.hsn) as hsn,
        MAX(pc.sku_code) as sku_code,
        MAX(pc.making_on) as making_on,
        MAX(pc.diamond_category) as diamond_category,
        MAX(pc.carat) as carat,
        SUM(
            CASE 
                WHEN s.stock_type IN ($dsa_in_stock_types)
                THEN $dsa_mov_qty
                ELSE 0
            END
        ) as purchase_qty,
        COALESCE((
            SELECT SUM(COALESCE(pii3.metal_weight, pii3.gross_weight, 0))
            FROM tbl_purchase_invoice_items pii3
            INNER JOIN tbl_product_characteristics pc3 ON pc3.id = pii3.product_characteristic_id AND pc3.product_id = s.product_id AND pc3.branch_id = s.branch_id AND pc3.metal_id = s.metal_id AND pc3.status = 1
            WHERE pii3.product_id = s.product_id AND pii3.status = 1
        ), 0) as purchase_metal_weight,
        COALESCE((
            SELECT SUM(sj.quantity)
            FROM tbl_stock_journal sj
            WHERE sj.status = 'active'
            AND (
                EXISTS (
                    SELECT 1 FROM tbl_purchase_invoice_items pii2
                    INNER JOIN tbl_product_characteristics pc2 ON pc2.id = pii2.product_characteristic_id
                    WHERE pii2.id = sj.item_id AND pii2.status = 1
                    AND pc2.product_id = s.product_id AND pc2.branch_id = s.branch_id AND pc2.metal_id = s.metal_id AND pc2.status = 1
                )
                OR EXISTS (
                    SELECT 1 FROM tbl_product_characteristics pc2
                    WHERE pc2.id = sj.product_characteristic_id
                    AND pc2.product_id = s.product_id AND pc2.branch_id = s.branch_id AND pc2.metal_id = s.metal_id AND pc2.status = 1
                )
            )
        ), 0) as production_qty,
        COALESCE((
            SELECT SUM(COALESCE(sj.gross_weight, sj.net_weight, 0))
            FROM tbl_stock_journal sj
            WHERE sj.status = 'active'
            AND (
                EXISTS (
                    SELECT 1 FROM tbl_purchase_invoice_items pii2
                    INNER JOIN tbl_product_characteristics pc2 ON pc2.id = pii2.product_characteristic_id
                    WHERE pii2.id = sj.item_id AND pii2.status = 1
                    AND pc2.product_id = s.product_id AND pc2.branch_id = s.branch_id AND pc2.metal_id = s.metal_id AND pc2.status = 1
                )
                OR EXISTS (
                    SELECT 1 FROM tbl_product_characteristics pc2
                    WHERE pc2.id = sj.product_characteristic_id
                    AND pc2.product_id = s.product_id AND pc2.branch_id = s.branch_id AND pc2.metal_id = s.metal_id AND pc2.status = 1
                )
            )
        ), 0) as production_weight,
        COALESCE((
            SELECT SUM(sii.quantity)
            FROM tbl_sale_invoice_items sii
            INNER JOIN tbl_sale_invoices si ON sii.invoice_id = si.id
            INNER JOIN tbl_product_characteristics pc4 ON pc4.id = sii.product_characteristic_id AND pc4.product_id = s.product_id AND pc4.branch_id = s.branch_id AND pc4.metal_id = s.metal_id AND pc4.status = 1
            WHERE sii.product_id = s.product_id AND sii.status = 1 AND si.status != 'cancelled'
        ), 0) as sale_invoice_qty,
        SUM(CASE WHEN s.stock_type IN ($dsa_in_stock_types) THEN $dsa_mov_wt ELSE 0 END) as inward_gross_sum,
        SUM(CASE WHEN s.stock_type IN ($dsa_in_stock_types) THEN $dsa_mov_wt * $dsa_purity_factor ELSE 0 END) as inward_pure_sum,
        SUM(CASE WHEN s.stock_type = 'outward' THEN $dsa_mov_wt_abs * $dsa_purity_factor ELSE 0 END) as outward_pure_sum,
        SUM(
            CASE 
                WHEN s.stock_type IN ($dsa_in_stock_types)
                THEN $dsa_mov_qty
                ELSE 0
            END
        ) as available_qty,
        (SUM(CASE WHEN s.stock_type IN ($dsa_in_stock_types) THEN $dsa_mov_wt ELSE 0 END) - SUM(CASE WHEN s.stock_type = 'outward' THEN $dsa_mov_wt_abs ELSE 0 END)) as stock_net_weight,
        SUM(CASE WHEN s.stock_type = 'outward' THEN $dsa_mov_wt_abs ELSE 0 END) as outward_weight_sum,
        SUM(CASE WHEN s.stock_type IN ($dsa_in_stock_types) THEN $dsa_mov_qty ELSE 0 END) as inward_receipt_qty_sum,
        SUM(CASE WHEN s.stock_type IN ($dsa_in_stock_types) THEN $dsa_mov_wt ELSE 0 END) as inward_receipt_weight_sum,
        SUM(CASE WHEN s.stock_type IN ($dsa_in_stock_types) THEN $dsa_mov_wt * $dsa_purity_factor ELSE 0 END) as inward_receipt_pure_sum,
        SUM(CASE WHEN s.stock_type = 'outward' THEN $dsa_mov_qty_abs ELSE 0 END) as outward_qty_sum,
        SUM(s.opening_weight) as opening_weight,
        CASE WHEN SUM(CASE WHEN s.stock_type IN ($dsa_in_stock_types) THEN $dsa_mov_wt ELSE 0 END) > 0 THEN SUM(CASE WHEN s.stock_type IN ($dsa_in_stock_types) THEN $dsa_mov_wt * COALESCE(s.opening_purity, 0) ELSE 0 END) / SUM(CASE WHEN s.stock_type IN ($dsa_in_stock_types) THEN $dsa_mov_wt ELSE 0 END) ELSE MAX(s.opening_purity) END as opening_purity,
        SUM(CASE WHEN s.stock_type = 'opening' THEN $dsa_mov_wt ELSE 0 END) AS sd_gross_opening,
        SUM(CASE WHEN s.stock_type IN ('purchase','stock_journal','balance','inward','sale_return') THEN $dsa_mov_wt ELSE 0 END) AS sd_gross_in,
        SUM(CASE WHEN s.stock_type = 'outward' THEN $dsa_mov_wt_abs ELSE 0 END) AS sd_gross_out,
        SUM(CASE WHEN s.stock_type = 'opening' THEN $dsa_mov_wt * $dsa_purity_factor ELSE 0 END) AS sd_pure_opening,
        SUM(CASE WHEN s.stock_type IN ('purchase','stock_journal','balance','inward','sale_return') THEN $dsa_mov_wt * $dsa_purity_factor ELSE 0 END) AS sd_pure_in,
        SUM(CASE WHEN s.stock_type = 'outward' THEN $dsa_mov_wt_abs * $dsa_purity_factor ELSE 0 END) AS sd_pure_out,
        SUM(CASE WHEN s.stock_type = 'opening' THEN $dsa_mov_qty ELSE 0 END) AS sd_pcs_opening,
        SUM(CASE WHEN s.stock_type IN ('purchase','stock_journal','balance','inward','sale_return') THEN $dsa_mov_qty ELSE 0 END) AS sd_pcs_in,
        SUM(CASE WHEN s.stock_type = 'outward' THEN $dsa_mov_qty_abs ELSE 0 END) AS sd_pcs_out,
        SUM(s.value) as value,
        MAX(s.final_weight) as final_weight,
        MAX(s.rate) as rate
";
$stock_inner_sql = $stock_inner_select . $stock_inner_from;

$stock_roll_up_sql = "
    SELECT 
        product_id,
        branch_id,
        metal_id,
        MAX(product_characteristic_id) as product_characteristic_id,
        MAX(product_name) as product_name,
        MAX(article) as article,
        MAX(alternate_name) as alternate_name,
        MAX(metal_name) as metal_name,
        MAX(branch_name) as branch_name,
        MAX(location_name) as location_name,
        MAX(hsn) as hsn,
        MAX(sku_code) as sku_code,
        MAX(making_on) as making_on,
        MAX(diamond_category) as diamond_category,
        MAX(carat) as carat,
        SUM(purchase_qty) as purchase_qty,
        MAX(purchase_metal_weight) as purchase_metal_weight,
        MAX(production_qty) as production_qty,
        MAX(production_weight) as production_weight,
        MAX(sale_invoice_qty) as sale_invoice_qty,
        SUM(inward_gross_sum) as inward_gross_sum,
        SUM(inward_pure_sum) as inward_pure_sum,
        SUM(outward_pure_sum) as outward_pure_sum,
        SUM(available_qty) as available_qty,
        SUM(stock_net_weight) as stock_net_weight,
        SUM(outward_weight_sum) as outward_weight_sum,
        SUM(inward_receipt_qty_sum) as inward_receipt_qty_sum,
        SUM(inward_receipt_weight_sum) as inward_receipt_weight_sum,
        SUM(inward_receipt_pure_sum) as inward_receipt_pure_sum,
        SUM(outward_qty_sum) as outward_qty_sum,
        SUM(sd_gross_opening) AS sd_gross_opening,
        SUM(sd_gross_in) AS sd_gross_in,
        SUM(sd_gross_out) AS sd_gross_out,
        SUM(sd_pure_opening) AS sd_pure_opening,
        SUM(sd_pure_in) AS sd_pure_in,
        SUM(sd_pure_out) AS sd_pure_out,
        SUM(sd_pcs_opening) AS sd_pcs_opening,
        SUM(sd_pcs_in) AS sd_pcs_in,
        SUM(sd_pcs_out) AS sd_pcs_out,
        SUM(opening_weight) as opening_weight,
        MAX(opening_purity) as opening_purity,
        SUM(value) as value,
        MAX(final_weight) as final_weight,
        MAX(rate) as rate
    FROM (
        $stock_inner_sql
    ) tmp
    GROUP BY product_id, branch_id, metal_id
";

$metals = $scope_metals;
