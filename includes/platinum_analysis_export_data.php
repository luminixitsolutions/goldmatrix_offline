<?php

/**
 * Platinum analysis — fetch rows + footer totals for Excel/PDF (same SQL as platinum-analysis.php).
 */

/** @return array<string, mixed> */
function auragold_platinum_export_row_col(array $row, string $name): ?float
{
    foreach ($row as $k => $v) {
        if (strcasecmp((string) $k, $name) === 0) {
            return ($v === null || $v === '') ? null : (float) $v;
        }
    }
    return null;
}

function auragold_platinum_export_tab(): string
{
    $t = isset($_GET['tab']) ? (string) $_GET['tab'] : 'current-stock';

    return $t === 'stock-details' ? 'stock-details' : 'current-stock';
}

/** @return array<int, string> */
function auragold_platinum_analysis_export_headers(): array
{
    return [
        'Product Name',
        'Metal',
        'Qty',
        'Gross Weight',
        'Pure/D.Wt.',
        'Article',
        'Branch Name',
        'Net Wt',
        'Stone Wt',
        'Purchase Amount',
    ];
}

/**
 * @return array<int, string|float|int>
 */
function auragold_platinum_analysis_export_flat_row(array $stock, string $tab): array
{
    $qty_for_display = (float) (auragold_platinum_export_row_col($stock, 'available_qty') ?? 0);
    $purchase_metal_weight = (float) ($stock['purchase_metal_weight'] ?? 0);
    $opening_purity = (float) ($stock['opening_purity'] ?? 0);
    if ($tab === 'current-stock') {
        $gross_weight = (float) (auragold_platinum_export_row_col($stock, 'display_gross_weight') ?? 0);
        $pure_weight = (float) (auragold_platinum_export_row_col($stock, 'display_pure_weight') ?? 0);
        if ($gross_weight == 0 && $purchase_metal_weight > 0 && abs($pure_weight) < 0.0001) {
            $gross_weight = $purchase_metal_weight;
        }
    } else {
        $gross_weight = (float) ($stock['display_gross_weight'] ?? $stock['stock_net_weight'] ?? 0);
        if ($gross_weight == 0 && $purchase_metal_weight > 0) {
            $gross_weight = $purchase_metal_weight;
        }
        $pure_weight = $gross_weight * ($opening_purity <= 1 ? $opening_purity : $opening_purity / 100);
    }
    $net_weight = $gross_weight;
    $stone_weight = 0.0;
    $purchase_amount = (float) ($stock['value'] ?? 0);

    return [
        trim((string) ($stock['product_name'] ?? '')) ?: 'N/A',
        trim((string) ($stock['metal_name'] ?? '')) ?: 'N/A',
        (int) round($qty_for_display, 0),
        round($gross_weight, 3),
        round($pure_weight, 3),
        trim((string) ($stock['article'] ?? '')),
        trim((string) ($stock['branch_name'] ?? '')) ?: 'N/A',
        round($net_weight, 3),
        round($stone_weight, 3),
        round($purchase_amount, 2),
    ];
}

/**
 * @return array{rows: array<int, array<string, mixed>>, totals: array<string, mixed>, error: string, tab: string}
 */
function auragold_platinum_analysis_export_fetch(): array
{
    require_once __DIR__ . '/platinum_analysis_roll_up_include.php';

    $tab = auragold_platinum_export_tab();

    if ($tab === 'current-stock') {
        $stock_query = "
        SELECT 
            stock_grp.*,
            stock_grp.available_qty AS display_qty,
            (stock_grp.inward_pure_sum - stock_grp.outward_pure_sum) AS display_pure_weight,
            (stock_grp.inward_gross_sum - stock_grp.outward_weight_sum) AS display_gross_weight
        FROM (
            $stock_inner_sql
        ) stock_grp
        ORDER BY stock_grp.product_name ASC, stock_grp.product_id DESC
    ";

        $totals_query = "
        SELECT 
            SUM(display_qty) as total_qty,
            SUM(production_qty) as total_production_qty,
            SUM(sale_invoice_qty) as total_sale_invoice_qty,
            SUM(display_qty) as total_available_qty,
            SUM(display_gross_weight) as total_gross_weight,
            SUM(display_pure_weight) as total_pure_weight,
            SUM(display_gross_weight) as total_net_weight,
            SUM(0) as total_stone_weight,
            SUM(value) as total_purchase_amount
        FROM (
            SELECT 
                stock_grp.*,
                stock_grp.available_qty AS display_qty,
                (stock_grp.inward_pure_sum - stock_grp.outward_pure_sum) AS display_pure_weight,
                (stock_grp.inward_gross_sum - stock_grp.outward_weight_sum) AS display_gross_weight
            FROM (
                $stock_inner_sql
            ) stock_grp
        ) as display_totals
    ";
    } else {
        $stock_query = "
        SELECT 
            stock_grp.*,
            stock_grp.available_qty AS display_qty,
            (CASE 
                WHEN stock_grp.outward_weight_sum >= 0.0001 THEN stock_grp.stock_net_weight
                WHEN stock_grp.production_weight > 0.0001 AND stock_grp.outward_weight_sum < 0.0001
                     AND COALESCE(stock_grp.purchase_metal_weight, 0) > 0.0001
                     AND ABS(stock_grp.stock_net_weight + stock_grp.production_weight - stock_grp.purchase_metal_weight) < 0.05
                THEN stock_grp.stock_net_weight
                WHEN stock_grp.production_weight > 0.0001 AND stock_grp.outward_weight_sum < 0.0001
                THEN stock_grp.stock_net_weight - stock_grp.production_weight
                ELSE stock_grp.stock_net_weight
            END) AS display_gross_weight
        FROM (
            $stock_inner_sql
        ) stock_grp
        ORDER BY stock_grp.product_name ASC, stock_grp.product_id DESC
    ";

        $totals_query = "
        SELECT 
            SUM(display_qty) as total_qty,
            SUM(production_qty) as total_production_qty,
            SUM(sale_invoice_qty) as total_sale_invoice_qty,
            SUM(display_qty) as total_available_qty,
            SUM(display_gross_weight) as total_gross_weight,
            SUM(display_gross_weight * (CASE WHEN opening_purity <= 1 THEN opening_purity ELSE opening_purity / 100 END)) as total_pure_weight,
            SUM(display_gross_weight) as total_net_weight,
            SUM(0) as total_stone_weight,
            SUM(value) as total_purchase_amount
        FROM (
            SELECT 
                stock_grp.*,
                stock_grp.available_qty AS display_qty,
                (CASE 
                    WHEN stock_grp.outward_weight_sum >= 0.0001 THEN stock_grp.stock_net_weight
                    WHEN stock_grp.production_weight > 0.0001 AND stock_grp.outward_weight_sum < 0.0001
                         AND COALESCE(stock_grp.purchase_metal_weight, 0) > 0.0001
                         AND ABS(stock_grp.stock_net_weight + stock_grp.production_weight - stock_grp.purchase_metal_weight) < 0.05
                    THEN stock_grp.stock_net_weight
                    WHEN stock_grp.production_weight > 0.0001 AND stock_grp.outward_weight_sum < 0.0001
                    THEN stock_grp.stock_net_weight - stock_grp.production_weight
                    ELSE stock_grp.stock_net_weight
                END) AS display_gross_weight
            FROM (
                $stock_inner_sql
            ) stock_grp
        ) as display_totals
    ";
    }

    $rows = getList($stock_query . ' LIMIT 15000');
    if (!is_array($rows)) {
        $rows = [];
    }
    $totals = getRecord($totals_query);
    if (!is_array($totals)) {
        $totals = [];
    }

    return ['rows' => $rows, 'totals' => $totals, 'error' => '', 'tab' => $tab];
}
