<?php

/**
 * Diamond & Stone analysis — table row HTML fragments for AJAX.
 */

require_once __DIR__ . '/gold_silver_analysis_helpers.php';
require_once __DIR__ . '/gold_silver_analysis_render.php';

if (!function_exists('dsa_compute_current_stock_metrics')) {
    /**
     * @return array{
     *   qty: float,
     *   gross_weight: float,
     *   pure_weight: float,
     *   net_weight: float,
     *   carat_display: float,
     *   diamond_ct: float,
     *   stone_weight: float,
     *   stone_ct: float,
     *   purchase_amount: float
     * }
     */
    function dsa_compute_current_stock_metrics(array $stock): array
    {
        $qty = (float) (stock_analysis_row_col($stock, 'display_qty') ?? 0);
        $gross_weight = (float) (stock_analysis_row_col($stock, 'display_gross_weight') ?? 0);
        $pure_weight = (float) (stock_analysis_row_col($stock, 'display_pure_weight') ?? 0);
        $purchase_metal_weight = (float) ($stock['purchase_metal_weight'] ?? 0);
        if ($gross_weight == 0 && $purchase_metal_weight > 0 && abs($pure_weight) < 0.0001) {
            $gross_weight = $purchase_metal_weight;
        }
        $net_weight = $gross_weight;
        $carat_raw = stock_analysis_row_col($stock, 'carat');
        $carat_val = (float) (($carat_raw !== null) ? $carat_raw : ($stock['carat'] ?? 0));
        $diamond_ct = $carat_val > 0 ? $carat_val : (($pure_weight > 0) ? ($pure_weight / 0.2) : 0);

        return [
            'qty' => $qty,
            'gross_weight' => $gross_weight,
            'pure_weight' => $pure_weight,
            'net_weight' => $net_weight,
            'carat_display' => $diamond_ct,
            'diamond_ct' => $diamond_ct,
            'stone_weight' => 0.0,
            'stone_ct' => 0.0,
            'purchase_amount' => (float) ($stock['value'] ?? 0),
        ];
    }
}

if (!function_exists('dsa_render_current_stock_tbody')) {
    function dsa_render_current_stock_tbody(array $stock_data): string
    {
        ob_start();
        if (!empty($stock_data)) {
            foreach ($stock_data as $stock) {
                $m = dsa_compute_current_stock_metrics($stock);
                echo '<tr>';
                echo '<td data-column="action"><button type="button" class="view-history-btn" data-stock-id="0" data-branch-id="' . (int) ($stock['branch_id'] ?? 0) . '" data-product-id="' . (int) ($stock['product_id'] ?? 0) . '" data-characteristic-id="' . (int) ($stock['product_characteristic_id'] ?? 0) . '">View History</button></td>';
                echo '<td data-column="product_name">' . htmlspecialchars($stock['product_name'] ?: 'N/A', ENT_QUOTES, 'UTF-8') . '</td>';
                echo '<td data-column="qty" class="' . ($m['qty'] < 0 ? 'negative' : '') . '">' . gsa_format_qty_cell($m['qty'], 0) . '</td>';
                echo '<td data-column="gross_weight" class="' . ($m['gross_weight'] < 0 ? 'negative' : '') . '">' . gsa_format_qty_cell($m['gross_weight'], 3) . '</td>';
                echo '<td data-column="carat" class="' . ($m['carat_display'] < 0 ? 'negative' : '') . '">' . gsa_format_qty_cell($m['carat_display'], 3) . '</td>';
                echo '<td data-column="article">' . htmlspecialchars($stock['article'] ?: '', ENT_QUOTES, 'UTF-8') . '</td>';
                echo '<td data-column="metal">' . htmlspecialchars($stock['metal_name'] ?: 'N/A', ENT_QUOTES, 'UTF-8') . '</td>';
                echo '<td data-column="diamond_wt" class="' . ($m['pure_weight'] < 0 ? 'negative' : '') . '">' . gsa_format_qty_cell($m['pure_weight'], 3) . '</td>';
                echo '<td data-column="diamond_ct" class="' . ($m['diamond_ct'] < 0 ? 'negative' : '') . '">' . gsa_format_qty_cell($m['diamond_ct'], 3) . '</td>';
                echo '<td data-column="stone_wt">' . gsa_format_qty_cell($m['stone_weight'], 3) . '</td>';
                echo '<td data-column="stone_ct">' . gsa_format_qty_cell($m['stone_ct'], 3) . '</td>';
                echo '<td data-column="net_wt" class="' . ($m['net_weight'] < 0 ? 'negative' : '') . '">' . gsa_format_qty_cell($m['net_weight'], 3) . '</td>';
                echo '<td data-column="purchase_amount">' . number_format($m['purchase_amount'], 2) . '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="13" class="text-center text-muted" style="padding: 40px;">No stock data found</td></tr>';
        }

        return (string) ob_get_clean();
    }
}

if (!function_exists('dsa_render_stock_details_tbody')) {
    function dsa_render_stock_details_tbody(array $stock_data): string
    {
        return gsa_render_stock_details_tbody($stock_data);
    }
}

if (!function_exists('dsa_render_stock_details_tfoot_cells')) {
    function dsa_render_stock_details_tfoot_cells(array $totals): string
    {
        return gsa_render_stock_details_tfoot_cells($totals);
    }
}
