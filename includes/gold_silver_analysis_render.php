<?php

/**
 * Gold/Silver analysis — table row HTML fragments for AJAX.
 */

if (!function_exists('gsa_render_current_stock_tbody')) {
    function gsa_render_current_stock_tbody(array $stock_data, array $gsa_cs_attach_image_map = []): string
    {
        ob_start();
        if (!empty($stock_data)) {
            foreach ($stock_data as $stock) {
                $qty = (float) (stock_analysis_row_col($stock, 'display_qty') ?? 0);
                $gross_weight = (float) (stock_analysis_row_col($stock, 'display_gross_weight') ?? 0);
                $pure_weight = (float) (stock_analysis_row_col($stock, 'display_pure_weight') ?? 0);
                $net_weight = (float) (stock_analysis_row_col($stock, 'stock_net_weight') ?? 0);
                if ($net_weight == 0.0) {
                    $net_weight = $gross_weight;
                }
                $stone_weight = max(0.0, round($gross_weight - $net_weight, 3));
                $purchase_amount = (float) ($stock['value'] ?? 0);
                $img_key = (int) ($stock['product_id'] ?? 0) . ':' . (int) ($stock['branch_id'] ?? 0) . ':' . (int) ($stock['metal_id'] ?? 0);
                $img_url = isset($gsa_cs_attach_image_map[$img_key]) ? (string) $gsa_cs_attach_image_map[$img_key] : '';
                if ($img_url !== '') {
                    $img_esc = htmlspecialchars($img_url, ENT_QUOTES, 'UTF-8');
                    $img_cell = '<span class="gsa-attach-img-cell"><a href="' . $img_esc . '" target="_blank" rel="noopener" class="gsa-attach-img-link" title="Open attachment"><img src="' . $img_esc . '" alt="" class="gsa-attach-thumb" loading="lazy"></a></span>';
                } else {
                    $img_cell = '<span class="gsa-attach-img-cell"><span class="gsa-attach-placeholder" title="No image"><i class="feather icon-image"></i></span></span>';
                }

                echo '<tr>';
                echo '<td data-col="action"><button type="button" class="view-history-btn" data-stock-id="0" data-branch-id="' . (int) ($stock['branch_id'] ?? 0) . '" data-product-id="' . (int) ($stock['product_id'] ?? 0) . '" data-characteristic-id="0">View History</button></td>';
                echo '<td data-col="attach_image" class="text-center">' . $img_cell . '</td>';
                echo '<td data-col="product_name">' . htmlspecialchars($stock['product_name'] ?: 'N/A', ENT_QUOTES, 'UTF-8') . '</td>';
                echo '<td data-col="metal">' . htmlspecialchars($stock['metal_name'] ?: 'N/A', ENT_QUOTES, 'UTF-8') . '</td>';
                echo '<td data-col="qty" class="' . ($qty < 0 ? 'negative' : '') . '">' . gsa_format_qty_cell($qty, 0) . '</td>';
                echo '<td data-col="gross_weight" class="' . ($gross_weight < 0 ? 'negative' : '') . '">' . gsa_format_qty_cell($gross_weight, 3) . '</td>';
                echo '<td data-col="pure_weight" class="' . ($pure_weight < 0 ? 'negative' : '') . '">' . gsa_format_qty_cell($pure_weight, 3) . '</td>';
                echo '<td data-col="article">' . htmlspecialchars($stock['article'] ?: '', ENT_QUOTES, 'UTF-8') . '</td>';
                echo '<td data-col="branch_name">' . htmlspecialchars($stock['branch_name'] ?: 'N/A', ENT_QUOTES, 'UTF-8') . '</td>';
                echo '<td data-col="net_wt" class="' . ($net_weight < 0 ? 'negative' : '') . '">' . gsa_format_qty_cell($net_weight, 3) . '</td>';
                echo '<td data-col="stone_wt">' . gsa_format_qty_cell($stone_weight, 3) . '</td>';
                echo '<td data-col="purchase_amount">' . number_format($purchase_amount, 2) . '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="12" class="text-center text-muted" style="padding: 40px;">No stock data found</td></tr>';
        }

        return (string) ob_get_clean();
    }
}

if (!function_exists('gsa_render_stock_details_tbody')) {
    function gsa_render_stock_details_tbody(array $stock_data): string
    {
        ob_start();
        if (!empty($stock_data)) {
            foreach ($stock_data as $stock) {
                $sd_go = (float) (stock_analysis_row_col($stock, 'sd_gross_opening') ?? 0);
                $sd_gi = (float) (stock_analysis_row_col($stock, 'sd_gross_in') ?? 0);
                $sd_gout = (float) (stock_analysis_row_col($stock, 'sd_gross_out') ?? 0);
                $sd_gc = $sd_go + $sd_gi - $sd_gout;
                $sd_po = (float) (stock_analysis_row_col($stock, 'sd_pure_opening') ?? 0);
                $sd_pi = (float) (stock_analysis_row_col($stock, 'sd_pure_in') ?? 0);
                $sd_pout = (float) (stock_analysis_row_col($stock, 'sd_pure_out') ?? 0);
                $sd_pc = $sd_po + $sd_pi - $sd_pout;
                $sd_qo = (float) (stock_analysis_row_col($stock, 'sd_pcs_opening') ?? 0);
                $sd_qi = (float) (stock_analysis_row_col($stock, 'sd_pcs_in') ?? 0);
                $sd_qout = (float) (stock_analysis_row_col($stock, 'sd_pcs_out') ?? 0);
                $sd_qc = $sd_qo + $sd_qi - $sd_qout;
                $loc_name = stock_analysis_row_string($stock, 'location_name');
                echo '<tr>';
                echo '<td data-gsa-key="product" class="gsa-text-cell">' . htmlspecialchars($stock['product_name'] ?: 'N/A', ENT_QUOTES, 'UTF-8') . '</td>';
                echo '<td data-gsa-key="metal" class="gsa-text-cell">' . htmlspecialchars($stock['metal_name'] ?: 'N/A', ENT_QUOTES, 'UTF-8') . '</td>';
                echo '<td data-gsa-key="article" class="gsa-text-cell">' . htmlspecialchars($stock['article'] ?: '', ENT_QUOTES, 'UTF-8') . '</td>';
                echo '<td data-gsa-key="location" class="gsa-text-cell">' . htmlspecialchars($loc_name !== '' ? $loc_name : '', ENT_QUOTES, 'UTF-8') . '</td>';
                foreach ([$sd_go, $sd_gi, $sd_gout, $sd_gc] as $v) {
                    $cl = $v < 0 ? 'negative' : '';
                    echo '<td data-gsa-key="gross" class="' . $cl . '">' . gsa_format_qty_cell($v, 3) . '</td>';
                }
                foreach ([$sd_po, $sd_pi, $sd_pout, $sd_pc] as $v) {
                    $cl = $v < 0 ? 'negative' : '';
                    echo '<td data-gsa-key="pure" class="' . $cl . '">' . gsa_format_qty_cell($v, 3) . '</td>';
                }
                foreach ([$sd_qo, $sd_qi, $sd_qout, $sd_qc] as $v) {
                    $cl = $v < 0 ? 'negative' : '';
                    echo '<td data-gsa-key="pcs" class="' . $cl . '">' . gsa_format_qty_cell($v, 0) . '</td>';
                }
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="16" class="text-center text-muted" style="padding: 40px;">No stock data found</td></tr>';
        }

        return (string) ob_get_clean();
    }
}

if (!function_exists('gsa_render_stock_details_tfoot_cells')) {
    function gsa_render_stock_details_tfoot_cells(array $totals): string
    {
        $tt = is_array($totals) ? $totals : [];
        $tg = static function ($k) use ($tt) {
            $k = (string) $k;
            foreach ($tt as $tk => $tv) {
                if (strcasecmp((string) $tk, $k) === 0) {
                    return (float) $tv;
                }
            }
            return 0.0;
        };
        ob_start();
        ?>
        <td data-gsa-key="gross"><?php echo gsa_format_qty_cell($tg('t_sd_gross_opening'), 3); ?></td>
        <td data-gsa-key="gross"><?php echo gsa_format_qty_cell($tg('t_sd_gross_in'), 3); ?></td>
        <td data-gsa-key="gross"><?php echo gsa_format_qty_cell($tg('t_sd_gross_out'), 3); ?></td>
        <td data-gsa-key="gross"><?php echo gsa_format_qty_cell($tg('t_sd_gross_closing'), 3); ?></td>
        <td data-gsa-key="pure"><?php echo gsa_format_qty_cell($tg('t_sd_pure_opening'), 3); ?></td>
        <td data-gsa-key="pure"><?php echo gsa_format_qty_cell($tg('t_sd_pure_in'), 3); ?></td>
        <td data-gsa-key="pure"><?php echo gsa_format_qty_cell($tg('t_sd_pure_out'), 3); ?></td>
        <td data-gsa-key="pure"><?php echo gsa_format_qty_cell($tg('t_sd_pure_closing'), 3); ?></td>
        <td data-gsa-key="pcs"><?php echo gsa_format_qty_cell($tg('t_sd_pcs_opening'), 0); ?></td>
        <td data-gsa-key="pcs"><?php echo gsa_format_qty_cell($tg('t_sd_pcs_in'), 0); ?></td>
        <td data-gsa-key="pcs"><?php echo gsa_format_qty_cell($tg('t_sd_pcs_out'), 0); ?></td>
        <td data-gsa-key="pcs"><?php echo gsa_format_qty_cell($tg('t_sd_pcs_closing'), 0); ?></td>
        <?php
        return (string) ob_get_clean();
    }
}
