<?php
/**
 * Single-row current stock (same rules as gold-silver-analysis.php → Current Stock tab).
 * Aggregates tbl_stock by product_id + branch_id + metal_id.
 *
 * @param mysqli $conn
 * @return array<string,mixed>|null
 */
function auragold_get_current_stock_balance_row($conn, $product_id, $branch_id, $metal_id) {
    $product_id = (int) $product_id;
    $branch_id = (int) $branch_id;
    $metal_id = (int) $metal_id;
    if ($product_id <= 0 || $branch_id <= 0 || $metal_id <= 0) {
        return null;
    }

    $where_clause = "s.status = 1 AND s.stock_type IN ('opening', 'purchase', 'inward', 'balance', 'stock_journal', 'sale_return', 'outward')";
    $where_clause .= " AND s.product_id = $product_id AND s.branch_id = $branch_id AND s.metal_id = $metal_id";

    $stock_inner_from = "
    FROM tbl_stock s
    LEFT JOIN tbl_products p ON s.product_id = p.id
    LEFT JOIN tbl_metal m ON s.metal_id = m.id
    LEFT JOIN tbl_branches b ON s.branch_id = b.id
    LEFT JOIN tbl_product_characteristics pc
        ON s.product_characteristic_id = pc.id
    WHERE $where_clause
    GROUP BY s.product_id, s.branch_id, s.metal_id
";

    $stock_inner_select = "
    SELECT 
        s.product_id,
        MAX(s.product_characteristic_id) as product_characteristic_id,
        s.branch_id,
        s.metal_id,
        MAX(p.name) as product_name,
        MAX(m.display_name) as metal_name,
        MAX(b.name) as branch_name,
        COALESCE((
            SELECT SUM(COALESCE(pii3.metal_qty, pii3.quantity))
            FROM tbl_purchase_invoice_items pii3
            INNER JOIN tbl_product_characteristics pc3 ON pc3.id = pii3.product_characteristic_id AND pc3.product_id = s.product_id AND pc3.branch_id = s.branch_id AND pc3.metal_id = s.metal_id AND pc3.status = 1
            WHERE pii3.product_id = s.product_id AND pii3.status = 1
        ), 0) + SUM(CASE WHEN s.stock_type = 'opening' THEN COALESCE(s.opening_qty, s.current_qty, 0) ELSE 0 END) as purchase_qty,
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
        SUM(CASE WHEN s.stock_type IN ('opening','purchase','inward','balance','stock_journal','sale_return') THEN COALESCE(s.opening_weight, s.current_weight, 0) ELSE 0 END) as inward_gross_sum,
        SUM(CASE WHEN s.stock_type IN ('opening','purchase','inward','balance','stock_journal','sale_return') THEN COALESCE(s.opening_weight, s.current_weight, 0) * (CASE WHEN COALESCE(s.opening_purity, 0) <= 1 THEN COALESCE(s.opening_purity, 0) ELSE COALESCE(s.opening_purity, 0) / 100 END) ELSE 0 END) as inward_pure_sum,
        SUM(CASE WHEN s.stock_type = 'outward' THEN COALESCE(s.opening_weight, s.current_weight, 0) * (CASE WHEN COALESCE(s.opening_purity, 0) <= 1 THEN COALESCE(s.opening_purity, 0) ELSE COALESCE(s.opening_purity, 0) / 100 END) ELSE 0 END) as outward_pure_sum,
        (SUM(CASE WHEN s.stock_type IN ('opening','purchase','inward','balance','stock_journal','sale_return') THEN COALESCE(s.current_qty, 0) ELSE 0 END) - SUM(CASE WHEN s.stock_type = 'outward' THEN COALESCE(s.current_qty, 0) ELSE 0 END)) as available_qty,
        (SUM(CASE WHEN s.stock_type IN ('opening','purchase','inward','balance','stock_journal','sale_return') THEN COALESCE(s.opening_weight, s.current_weight, 0) ELSE 0 END) - SUM(CASE WHEN s.stock_type = 'outward' THEN COALESCE(s.opening_weight, s.current_weight, 0) ELSE 0 END)) as stock_net_weight,
        SUM(CASE WHEN s.stock_type = 'outward' THEN COALESCE(s.opening_weight, s.current_weight, 0) ELSE 0 END) as outward_weight_sum,
        CASE WHEN SUM(CASE WHEN s.stock_type IN ('opening','purchase','inward','balance','stock_journal','sale_return') THEN COALESCE(s.opening_weight, s.current_weight, 0) ELSE 0 END) > 0 THEN SUM(CASE WHEN s.stock_type IN ('opening','purchase','inward','balance','stock_journal','sale_return') THEN COALESCE(s.opening_weight, s.current_weight, 0) * COALESCE(s.opening_purity, 0) ELSE 0 END) / SUM(CASE WHEN s.stock_type IN ('opening','purchase','inward','balance','stock_journal','sale_return') THEN COALESCE(s.opening_weight, s.current_weight, 0) ELSE 0 END) ELSE MAX(s.opening_purity) END as opening_purity,
        SUM(s.value) as value
";

    $stock_inner_sql = $stock_inner_select . $stock_inner_from;

    $sql = "
        SELECT 
            stock_grp.*,
            (stock_grp.available_qty + stock_grp.production_qty) AS display_qty,
            (stock_grp.inward_pure_sum - stock_grp.outward_pure_sum) AS display_pure_weight,
            (stock_grp.inward_gross_sum - stock_grp.outward_weight_sum) AS display_gross_weight
        FROM (
            $stock_inner_sql
        ) stock_grp
        LIMIT 1
    ";

    $row = getRecord($sql);
    if (!$row || !is_array($row)) {
        return [
            'product_id' => $product_id,
            'branch_id' => $branch_id,
            'metal_id' => $metal_id,
            'branch_name' => '',
            'display_qty' => 0.0,
            'display_gross_weight' => 0.0,
            'display_pure_weight' => 0.0,
            'purchase_metal_weight' => 0.0,
            'opening_purity' => 0.0,
        ];
    }

    $purchase_metal_weight = (float) ($row['purchase_metal_weight'] ?? 0);
    $opening_purity = (float) ($row['opening_purity'] ?? 0);
    $gross = isset($row['display_gross_weight']) ? (float) $row['display_gross_weight'] : 0.0;
    $pure = isset($row['display_pure_weight']) ? (float) $row['display_pure_weight'] : 0.0;

    // Match gold-silver-analysis.php current-stock row fallback for display
    if ($gross == 0 && $purchase_metal_weight > 0 && abs($pure) < 0.0001) {
        $gross = $purchase_metal_weight;
    }

    return [
        'product_id' => $product_id,
        'branch_id' => $branch_id,
        'metal_id' => $metal_id,
        'branch_name' => (string) ($row['branch_name'] ?? ''),
        'display_qty' => (float) ($row['display_qty'] ?? (($row['available_qty'] ?? 0) + ($row['production_qty'] ?? 0))),
        'display_gross_weight' => $gross,
        'display_pure_weight' => $pure,
        'purchase_metal_weight' => $purchase_metal_weight,
        'opening_purity' => $opening_purity,
    ];
}

/**
 * Resolve branch_id for purchase invoice line using characteristic and/or location.
 */
function auragold_resolve_branch_id_for_stock_balance($conn, $product_id, $metal_id, $characteristic_id = 0, $location_id = 0) {
    $product_id = (int) $product_id;
    $metal_id = (int) $metal_id;
    $characteristic_id = (int) $characteristic_id;
    $location_id = (int) $location_id;
    if ($product_id <= 0 || $metal_id <= 0) {
        return 0;
    }
    if ($characteristic_id > 0) {
        $r = getRecord("SELECT branch_id, metal_id FROM tbl_product_characteristics WHERE id = $characteristic_id AND product_id = $product_id AND status = 1 LIMIT 1");
        if ($r && !empty($r['branch_id'])) {
            return (int) $r['branch_id'];
        }
    }
    if ($location_id > 0) {
        $r = getRecord("SELECT branch_id FROM tbl_product_characteristics WHERE product_id = $product_id AND metal_id = $metal_id AND location_id = $location_id AND status = 1 ORDER BY id ASC LIMIT 1");
        if ($r && !empty($r['branch_id'])) {
            return (int) $r['branch_id'];
        }
    }
    $r = getRecord("SELECT branch_id FROM tbl_product_characteristics WHERE product_id = $product_id AND metal_id = $metal_id AND status = 1 ORDER BY id ASC LIMIT 1");
    if ($r && !empty($r['branch_id'])) {
        return (int) $r['branch_id'];
    }
    return 0;
}
