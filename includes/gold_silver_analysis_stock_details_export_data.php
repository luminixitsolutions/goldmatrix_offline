<?php

/**
 * Stock Details tab — fetch all rows + footer totals for export (same SQL as gold-silver-analysis.php).
 * Depends on $_GET (branch, metal, adv_*, search) and includes/gold_silver_analysis_roll_up_include.php already defining $stock_roll_up_sql.
 */

/** @return array<string, mixed> */
function auragold_gsa_stock_details_row_col(array $row, string $name): ?float
{
    foreach ($row as $k => $v) {
        if (strcasecmp((string) $k, $name) === 0) {
            return ($v === null || $v === '') ? null : (float) $v;
        }
    }
    return null;
}

function auragold_gsa_stock_details_row_string(array $row, string $name): string
{
    foreach ($row as $k => $v) {
        if (strcasecmp((string) $k, $name) === 0) {
            return $v === null || $v === '' ? '' : (string) $v;
        }
    }
    return '';
}

/**
 * @return array{rows: array<int, array<string, mixed>>, totals: array<string, mixed>, error: string}
 */
function auragold_gsa_stock_details_export_fetch(mysqli $conn): array
{
    require __DIR__ . '/gold_silver_analysis_roll_up_include.php';

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

    $rows = getList($stock_query . ' LIMIT 15000');
    if (!is_array($rows)) {
        $rows = [];
    }
    $totals = getRecord($totals_query);
    if (!is_array($totals)) {
        $totals = [];
    }

    return ['rows' => $rows, 'totals' => $totals, 'error' => ''];
}

/** @return array<int, string|float|int> */
function auragold_gsa_stock_details_export_flat_row(array $stock): array
{
    $sd_go = (float) (auragold_gsa_stock_details_row_col($stock, 'sd_gross_opening') ?? 0);
    $sd_gi = (float) (auragold_gsa_stock_details_row_col($stock, 'sd_gross_in') ?? 0);
    $sd_gout = (float) (auragold_gsa_stock_details_row_col($stock, 'sd_gross_out') ?? 0);
    $sd_gc = $sd_go + $sd_gi - $sd_gout;
    $sd_po = (float) (auragold_gsa_stock_details_row_col($stock, 'sd_pure_opening') ?? 0);
    $sd_pi = (float) (auragold_gsa_stock_details_row_col($stock, 'sd_pure_in') ?? 0);
    $sd_pout = (float) (auragold_gsa_stock_details_row_col($stock, 'sd_pure_out') ?? 0);
    $sd_pc = $sd_po + $sd_pi - $sd_pout;
    $sd_qo = (float) (auragold_gsa_stock_details_row_col($stock, 'sd_pcs_opening') ?? 0);
    $sd_qi = (float) (auragold_gsa_stock_details_row_col($stock, 'sd_pcs_in') ?? 0);
    $sd_qout = (float) (auragold_gsa_stock_details_row_col($stock, 'sd_pcs_out') ?? 0);
    $sd_qc = $sd_qo + $sd_qi - $sd_qout;
    $loc = auragold_gsa_stock_details_row_string($stock, 'location_name');

    return [
        trim((string) ($stock['product_name'] ?? '')),
        trim((string) ($stock['metal_name'] ?? '')),
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
        (int) round($sd_qo, 0),
        (int) round($sd_qi, 0),
        (int) round($sd_qout, 0),
        (int) round($sd_qc, 0),
    ];
}

/** @return array<int, string> */
function auragold_gsa_stock_details_export_headers(): array
{
    return [
        'Product', 'Metal', 'Article', 'Location',
        'Gross Opening', 'Gross Wt. In', 'Gross Wt. Out', 'Gross Closing',
        'Pure Opening', 'Pure Wt. In', 'Pure Wt. Out', 'Pure Closing',
        'Pcs Opening', 'Pcs In', 'Pcs Out', 'Pcs Closing',
    ];
}
