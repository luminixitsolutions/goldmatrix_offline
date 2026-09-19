<?php

/**
 * Diamond & Stone analysis — fetch rows + footer totals for Excel/PDF export.
 */

/** @return array<string, mixed> */
function auragold_dsa_export_row_col(array $row, string $name): ?float
{
    foreach ($row as $k => $v) {
        if (strcasecmp((string) $k, $name) === 0) {
            return ($v === null || $v === '') ? null : (float) $v;
        }
    }
    return null;
}

function auragold_dsa_export_tab(): string
{
    $t = isset($_GET['tab']) ? (string) $_GET['tab'] : 'current-stock';

    return $t === 'stock-details' ? 'stock-details' : 'current-stock';
}

/** @return array<int, string> */
function auragold_dsa_analysis_export_headers(): array
{
    $tab = auragold_dsa_export_tab();
    if ($tab === 'stock-details') {
        return [
            'Product',
            'Metal',
            'Article',
            'Location',
            'Gross Opening',
            'Gross Wt. In',
            'Gross Wt. Out',
            'Gross Closing',
            'Diamond Wt. Opening',
            'Diamond Wt. In',
            'Diamond Wt. Out',
            'Diamond Wt. Closing',
            'Pcs Opening',
            'Pcs In',
            'Pcs Out',
            'Pcs Closing',
        ];
    }

    return [
        'Product Name',
        'Qty',
        'Gross Weight',
        'Carat',
        'Article',
        'Metal',
        'Diamond Wt.',
        'Diamond Ct',
        'Stone Wt.',
        'Stone Ct.',
        'Net Wt.',
        'Purchase Amount',
    ];
}

/**
 * @return array<int, string|float|int>
 */
function auragold_dsa_analysis_export_flat_row(array $stock, string $tab): array
{
    if ($tab === 'stock-details') {
        $sd_go = (float) (auragold_dsa_export_row_col($stock, 'sd_gross_opening') ?? 0);
        $sd_gi = (float) (auragold_dsa_export_row_col($stock, 'sd_gross_in') ?? 0);
        $sd_gout = (float) (auragold_dsa_export_row_col($stock, 'sd_gross_out') ?? 0);
        $sd_gc = $sd_go + $sd_gi - $sd_gout;
        $sd_po = (float) (auragold_dsa_export_row_col($stock, 'sd_pure_opening') ?? 0);
        $sd_pi = (float) (auragold_dsa_export_row_col($stock, 'sd_pure_in') ?? 0);
        $sd_pout = (float) (auragold_dsa_export_row_col($stock, 'sd_pure_out') ?? 0);
        $sd_pc = $sd_po + $sd_pi - $sd_pout;
        $sd_qo = (float) (auragold_dsa_export_row_col($stock, 'sd_pcs_opening') ?? 0);
        $sd_qi = (float) (auragold_dsa_export_row_col($stock, 'sd_pcs_in') ?? 0);
        $sd_qout = (float) (auragold_dsa_export_row_col($stock, 'sd_pcs_out') ?? 0);
        $sd_qc = $sd_qo + $sd_qi - $sd_qout;
        $loc = '';
        foreach ($stock as $k => $v) {
            if (strcasecmp((string) $k, 'location_name') === 0) {
                $loc = $v === null || $v === '' ? '' : (string) $v;
                break;
            }
        }

        return [
            trim((string) ($stock['product_name'] ?? '')) ?: 'N/A',
            trim((string) ($stock['metal_name'] ?? '')) ?: 'N/A',
            trim((string) ($stock['article'] ?? '')),
            $loc,
            round($sd_go, 3),
            round($sd_gi, 3),
            round($sd_gout, 3),
            round($sd_gc, 3),
            round($sd_po, 3),
            round($sd_pi, 3),
            round($sd_pout, 3),
            round($sd_pc, 3),
            round($sd_qo, 0),
            round($sd_qi, 0),
            round($sd_qout, 0),
            round($sd_qc, 0),
        ];
    }

    $qty_for_display = (float) (auragold_dsa_export_row_col($stock, 'display_qty') ?? 0);
    $purchase_metal_weight = (float) ($stock['purchase_metal_weight'] ?? 0);
    $opening_purity = (float) ($stock['opening_purity'] ?? 0);
    if ($tab === 'current-stock') {
        $gross_weight = (float) (auragold_dsa_export_row_col($stock, 'display_gross_weight') ?? 0);
        $pure_weight = (float) (auragold_dsa_export_row_col($stock, 'display_pure_weight') ?? 0);
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
    $stone_ct_val = 0.0;
    $purchase_amount = (float) ($stock['value'] ?? 0);
    $carat_raw = auragold_dsa_export_row_col($stock, 'carat');
    $carat_val = (float) (($carat_raw !== null) ? $carat_raw : ($stock['carat'] ?? 0));
    $diamond_wt = $pure_weight;
    $diamond_ct = $carat_val > 0 ? $carat_val : (($pure_weight > 0) ? ($pure_weight / 0.2) : 0);
    $carat_display_val = $diamond_ct;

    return [
        trim((string) ($stock['product_name'] ?? '')) ?: 'N/A',
        (int) round($qty_for_display, 0),
        round($gross_weight, 3),
        round($carat_display_val, 3),
        trim((string) ($stock['article'] ?? '')),
        trim((string) ($stock['metal_name'] ?? '')) ?: 'N/A',
        round($diamond_wt, 3),
        round($diamond_ct, 3),
        round($stone_weight, 3),
        round($stone_ct_val, 3),
        round($net_weight, 3),
        round($purchase_amount, 2),
    ];
}

/**
 * @return array{rows: array<int, array<string, mixed>>, totals: array<string, mixed>, error: string, tab: string}
 */
function auragold_dsa_analysis_export_fetch(): array
{
    require_once __DIR__ . '/diamond_stone_analysis_roll_up_include.php';

    $tab = auragold_dsa_export_tab();

    if ($tab === 'current-stock') {
        $visible_sql = auragold_sql_gsa_current_stock_row_visible('stock_grp');
        $stock_query = "
        SELECT 
            stock_grp.*,
            (COALESCE(stock_grp.inward_receipt_qty_sum, 0) - COALESCE(stock_grp.outward_qty_sum, 0)) AS display_qty,
            (COALESCE(stock_grp.inward_receipt_pure_sum, 0) - COALESCE(stock_grp.outward_pure_sum, 0)) AS display_pure_weight,
            (COALESCE(stock_grp.inward_receipt_weight_sum, 0) - COALESCE(stock_grp.outward_weight_sum, 0)) AS display_gross_weight
        FROM (
            $stock_roll_up_sql
        ) stock_grp
        WHERE $visible_sql
        ORDER BY stock_grp.product_name ASC, stock_grp.product_id DESC
    ";

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
            FROM (
                $stock_roll_up_sql
            ) stock_grp
            WHERE $visible_sql
        ) as display_totals
    ";
    } else {
        $stock_query = "
        SELECT stock_grp.*
        FROM (
            $stock_roll_up_sql
        ) stock_grp
        ORDER BY stock_grp.product_name ASC, stock_grp.product_id DESC
    ";

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
        FROM (
            $stock_roll_up_sql
        ) stock_grp
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
