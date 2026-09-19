<?php

require_once __DIR__ . '/gold_silver_stock_list_fetch.php';

/**
 * @param array<string, mixed> $ctx from gas_build_stock_list_table_context + extra_field_defs, has_journal_images
 */
function gas_stock_list_render_ctx(array $ctx, array $extra_field_defs, bool $has_journal_images): array
{
    return [
        'columns' => $ctx['columns'],
        'col_meta' => $ctx['col_meta'],
        'extra_field_defs' => $extra_field_defs,
        'has_journal_images' => $has_journal_images,
        'no_image_src_esc' => $ctx['no_image_src_esc'],
        'thumb_onerror_attr' => $ctx['thumb_onerror_attr'],
        'visible_count' => $ctx['visible_count'],
    ];
}

/**
 * @param array<string, float> $gas_grand
 */
function gas_stock_list_accumulate_row_totals(array &$gas_grand, float &$wastage_per_sum, int &$wastage_per_cnt, array $r): void
{
    $gw = $r['sj_gross_weight'];
    if ($gw === null || $gw === '') {
        $gw = $r['opening_weight'] ?? null;
    }
    $nw = $r['sj_net_weight'] ?? null;
    $wt = $r['current_weight'];
    if ($wt === null || (float) $wt <= 0) {
        $wt = $r['final_weight'] ?? null;
    }
    $pw = $r['sj_purity_weight'];
    if ($pw === null || $pw === '') {
        $pw = $r['sj_pure_weight'] ?? null;
    }
    $nw_for_purity = $nw;
    if ($nw_for_purity === null || $nw_for_purity === '' || (float) $nw_for_purity <= 0) {
        $nw_for_purity = $wt;
    }
    $voucher_disp = isset($r['voucher_type']) ? trim((string) $r['voucher_type']) : '';
    $op_raw = $r['opening_purity'] ?? null;
    if ($voucher_disp === 'product_opening' && $op_raw !== null && $op_raw !== '' && is_numeric($op_raw) && is_numeric($nw_for_purity) && (float) $nw_for_purity > 0) {
        $opc = (float) $op_raw;
        $p_eff = ($opc > 1) ? ($opc / 100.0) : $opc;
        if ($p_eff > 0 && $p_eff <= 1.001) {
            $pw_exp = (float) $nw_for_purity * $p_eff;
            if ($pw_exp > 0.0001) {
                $pw_f = ($pw !== null && $pw !== '' && is_numeric($pw)) ? (float) $pw : -1.0;
                if ($pw === null || $pw === '' || $pw_exp > $pw_f * 1.5 + 0.0001) {
                    $pw = $pw_exp;
                }
            }
        }
    }

    $gas_grand['weight'] += is_numeric($wt) ? (float) $wt : 0.0;
    $gas_grand['gross_wt'] += is_numeric($gw) ? (float) $gw : 0.0;
    $gas_grand['purity_wt'] += is_numeric($pw) ? (float) $pw : 0.0;
    $gas_grand['qty'] += is_numeric($r['current_qty'] ?? null) ? (float) $r['current_qty'] : 0.0;
    $gas_grand['stone_wt'] += is_numeric($r['stone_weight'] ?? null) ? (float) $r['stone_weight'] : 0.0;
    $gas_grand['net_wt'] += is_numeric($nw) ? (float) $nw : 0.0;
    $gas_grand['wastage_wt'] += is_numeric($r['wastage_wt'] ?? null) ? (float) $r['wastage_wt'] : 0.0;
    $wpp_row = $r['wastage_per'] ?? null;
    if ($wpp_row !== null && $wpp_row !== '' && is_numeric($wpp_row)) {
        $wastage_per_sum += (float) $wpp_row;
        $wastage_per_cnt++;
    }
    $gas_grand['metal_cost'] += is_numeric($r['metal_cost'] ?? null) ? (float) $r['metal_cost'] : 0.0;
    $gas_grand['making_cost'] += is_numeric($r['making_cost'] ?? null) ? (float) $r['making_cost'] : 0.0;
    $gas_grand['making_charge_amt'] += is_numeric($r['making_amount'] ?? null) ? (float) $r['making_amount'] : 0.0;
    $gas_grand['stone_cost'] += is_numeric($r['stone_cost'] ?? null) ? (float) $r['stone_cost'] : 0.0;
    $gas_grand['purchase_amount'] += is_numeric($r['purchase_amount'] ?? null) ? (float) $r['purchase_amount'] : 0.0;
    $gas_grand['metal_value'] += is_numeric($r['metal_value'] ?? null) ? (float) $r['metal_value'] : 0.0;
    $gas_grand['stone_amt'] += is_numeric($r['stone_amount'] ?? null) ? (float) $r['stone_amount'] : 0.0;
}

/**
 * @param array<int, array<string, mixed>> $rows
 * @param array<string, mixed> $render_ctx
 */
function gas_stock_list_render_tbody_rows(array $rows, array $render_ctx, string $load_error = ''): string
{
    global $SiteUrl;

    $gas_columns = $render_ctx['columns'];
    $gas_col_meta = $render_ctx['col_meta'];
    $gas_extra_field_defs = $render_ctx['extra_field_defs'];
    $gas_has_journal_images = !empty($render_ctx['has_journal_images']);
    $gas_no_image_src_esc = $render_ctx['no_image_src_esc'];
    $gas_thumb_onerror_attr = $render_ctx['thumb_onerror_attr'];
    $gas_visible_count = (int) ($render_ctx['visible_count'] ?? 1);

    ob_start();
    foreach ($rows as $r) {
        $gas_row_barcode = trim((string) ($r['barcode'] ?? ''));
        $gw = $r['sj_gross_weight'];
        if ($gw === null || $gw === '') {
            $gw = $r['opening_weight'] ?? null;
        }
        $nw = $r['sj_net_weight'] ?? null;
        $wt = $r['current_weight'];
        if ($wt === null || (float) $wt <= 0) {
            $wt = $r['final_weight'] ?? null;
        }
        $pw = $r['sj_purity_weight'];
        if ($pw === null || $pw === '') {
            $pw = $r['sj_pure_weight'] ?? null;
        }
        $nw_for_purity = $nw;
        if ($nw_for_purity === null || $nw_for_purity === '' || (float) $nw_for_purity <= 0) {
            $nw_for_purity = $wt;
        }
        $voucher_disp = isset($r['voucher_type']) ? trim((string) $r['voucher_type']) : '';
        $op_raw = $r['opening_purity'] ?? null;
        if ($voucher_disp === 'product_opening' && $op_raw !== null && $op_raw !== '' && is_numeric($op_raw) && is_numeric($nw_for_purity) && (float) $nw_for_purity > 0) {
            $opc = (float) $op_raw;
            $p_eff = ($opc > 1) ? ($opc / 100.0) : $opc;
            if ($p_eff > 0 && $p_eff <= 1.001) {
                $pw_exp = (float) $nw_for_purity * $p_eff;
                if ($pw_exp > 0.0001) {
                    $pw_f = ($pw !== null && $pw !== '' && is_numeric($pw)) ? (float) $pw : -1.0;
                    if ($pw === null || $pw === '' || $pw_exp > $pw_f * 1.5 + 0.0001) {
                        $pw = $pw_exp;
                    }
                }
            }
        }
        $carat = $r['pc_carat'] ?? '';
        if ($carat === null || $carat === '') {
            $carat = $r['sj_karat'] ?? '';
        }
        $sjst = isset($r['sj_status']) ? trim((string) $r['sj_status']) : '';
        $sst = isset($r['stock_status']) ? (int) $r['stock_status'] : 0;
        $active_disp = ($sst === 1 ? '1' : '0');
        if ($sjst !== '') {
            $active_disp .= ' / ' . $sjst;
        }
        $barcoded = $r['sj_created_at'] ?? $r['stock_created_at'] ?? '';
        if ($barcoded !== '' && $barcoded !== null) {
            $barcoded = substr((string) $barcoded, 0, 19);
        }
        $imgs_raw = trim((string) ($r['image_urls'] ?? ''));
        $img_cell = '';
        if ($imgs_raw !== '') {
            $first = explode(',', $imgs_raw)[0];
            $first = trim($first);
            if ($first !== '') {
                if (preg_match('#^https?://#i', $first) || strpos($first, '/') === 0) {
                    $src = $first;
                } else {
                    $src = gas_public_url_for_stored_path($first, $SiteUrl ?? null);
                }
                if (trim($src) !== '') {
                    $img_cell = '<img class="gas-thumb" src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '" alt="" loading="lazy"' . $gas_thumb_onerror_attr . '>';
                }
            }
        }
        if ($img_cell === '') {
            $img_cell = '<img class="gas-thumb gas-thumb-placeholder" src="' . $gas_no_image_src_esc . '" alt="" loading="lazy">';
        }
        if ($gas_has_journal_images && $gas_row_barcode !== '') {
            $img_cell = '<button type="button" class="gas-img-open-btn" data-barcode="' . htmlspecialchars($gas_row_barcode, ENT_QUOTES, 'UTF-8') . '" title="Add or manage images">' . $img_cell . '</button>';
        }
        $info = trim((string) ($r['info_text'] ?? ''));

        $gas_row_cells = [
            'imageUrls' => $img_cell,
            'info' => htmlspecialchars($info, ENT_QUOTES, 'UTF-8'),
            'huid' => htmlspecialchars((string) ($r['huid_no'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'barcode' => htmlspecialchars((string) ($r['barcode'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'product_name' => htmlspecialchars((string) ($r['product_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'location' => htmlspecialchars((string) ($r['sj_location'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'weight' => htmlspecialchars(gas_fmt_num($wt, 3), ENT_QUOTES, 'UTF-8'),
            'gross_wt' => htmlspecialchars(gas_fmt_num($gw, 3), ENT_QUOTES, 'UTF-8'),
            'purity_wt' => htmlspecialchars(gas_fmt_num($pw, 3), ENT_QUOTES, 'UTF-8'),
            'qty' => htmlspecialchars(gas_fmt_num($r['current_qty'] ?? null, 2), ENT_QUOTES, 'UTF-8'),
            'carat' => htmlspecialchars((string) $carat, ENT_QUOTES, 'UTF-8'),
            'active' => htmlspecialchars($active_disp, ENT_QUOTES, 'UTF-8'),
            'voucher_type' => htmlspecialchars((string) ($r['voucher_type'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'invoice_no' => htmlspecialchars((string) ($r['invoice_no'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'supplier_name' => htmlspecialchars((string) ($r['supplier_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'category' => htmlspecialchars((string) ($r['category_display'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'article' => htmlspecialchars((string) ($r['article'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'metal_cost' => htmlspecialchars(gas_fmt_money($r['metal_cost'] ?? null), ENT_QUOTES, 'UTF-8'),
            'making_cost' => htmlspecialchars(gas_fmt_money($r['making_cost'] ?? null), ENT_QUOTES, 'UTF-8'),
            'stone_wt' => htmlspecialchars(gas_fmt_num($r['stone_weight'] ?? null, 3), ENT_QUOTES, 'UTF-8'),
            'net_wt' => htmlspecialchars(gas_fmt_num($nw, 3), ENT_QUOTES, 'UTF-8'),
            'barcoded_date' => htmlspecialchars((string) $barcoded, ENT_QUOTES, 'UTF-8'),
            'making_charge_amt' => htmlspecialchars(gas_fmt_money($r['making_amount'] ?? null), ENT_QUOTES, 'UTF-8'),
            'stone_cost' => htmlspecialchars(gas_fmt_money($r['stone_cost'] ?? null), ENT_QUOTES, 'UTF-8'),
            'purchase_amount' => htmlspecialchars(gas_fmt_money($r['purchase_amount'] ?? null), ENT_QUOTES, 'UTF-8'),
            'making_type' => htmlspecialchars((string) ($r['making_type'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'metal_value' => htmlspecialchars(gas_fmt_money($r['metal_value'] ?? null), ENT_QUOTES, 'UTF-8'),
            'stone_rate' => htmlspecialchars(gas_fmt_money($r['stone_rate'] ?? null), ENT_QUOTES, 'UTF-8'),
            'stone_charge_type' => htmlspecialchars((string) ($r['stone_charge_type'] ?? ''), ENT_QUOTES, 'UTF-8'),
            'stone_amt' => htmlspecialchars(gas_fmt_money($r['stone_amount'] ?? null), ENT_QUOTES, 'UTF-8'),
            'making_charge_rate' => htmlspecialchars(gas_fmt_money($r['making_rate'] ?? null), ENT_QUOTES, 'UTF-8'),
            'wastage_wt' => htmlspecialchars(gas_fmt_num($r['wastage_wt'] ?? null, 3), ENT_QUOTES, 'UTF-8'),
            'wastage_per' => htmlspecialchars(gas_fmt_num($r['wastage_per'] ?? null, 2), ENT_QUOTES, 'UTF-8'),
        ];
        $ef_vals = is_array($r['extra_field_values'] ?? null) ? $r['extra_field_values'] : [];
        foreach ($gas_extra_field_defs as $ef_key => $ef_meta) {
            $ef_id = (int) ($ef_meta['id'] ?? 0);
            $ef_raw = '';
            if ($ef_id > 0) {
                if (isset($ef_vals[(string) $ef_id])) {
                    $ef_raw = (string) $ef_vals[(string) $ef_id];
                } elseif (isset($ef_vals[$ef_id])) {
                    $ef_raw = (string) $ef_vals[$ef_id];
                }
            }
            $gas_row_cells[$ef_key] = htmlspecialchars($ef_raw, ENT_QUOTES, 'UTF-8');
        }
        echo '<tr data-gas-barcode="' . htmlspecialchars($gas_row_barcode, ENT_QUOTES, 'UTF-8') . '">';
        foreach ($gas_columns as $gck => $_glab) {
            $gm = $gas_col_meta[$gck];
            $hcls = !empty($gm['hidden']) ? ' gas-col-hidden' : '';
            $wsty = ($gm['width'] !== null) ? ' style="min-width:' . (int) $gm['width'] . 'px;width:' . (int) $gm['width'] . 'px;max-width:560px;"' : '';
            $inner = $gas_row_cells[$gck] ?? '';
            echo '<td data-gas-col="' . htmlspecialchars($gck, ENT_QUOTES, 'UTF-8') . '" class="gas-td-cell' . $hcls . '"' . $wsty . '>' . $inner . '</td>';
        }
        echo "</tr>\n";
    }
    if (count($rows) === 0 && $load_error === '') {
        echo '<tr><td colspan="' . (int) max(1, $gas_visible_count) . '" class="text-center text-muted py-4 gas-empty-row" data-gas-empty="1">No rows to show.</td></tr>';
    }

    return (string) ob_get_clean();
}

/**
 * @param array<string, float> $gas_grand
 * @param array<string, mixed> $render_ctx
 */
function gas_stock_list_render_tfoot_cells(array $gas_grand, ?float $gas_wastage_per_avg, array $render_ctx, bool $loading = false, string $total_label = 'Grand Total'): string
{
    $gas_columns = $render_ctx['columns'];
    $gas_col_meta = $render_ctx['col_meta'];

    ob_start();
    foreach ($gas_columns as $ck => $clab) {
        $cls = 'gas-tfoot-cell';
        $inner = '';
        if ($loading) {
            if ($ck === 'imageUrls') {
                $inner = $total_label;
                $cls .= ' gas-tfoot-label';
            } elseif (in_array($ck, ['weight', 'gross_wt', 'purity_wt', 'qty', 'stone_wt', 'net_wt', 'wastage_wt', 'wastage_per', 'metal_cost', 'making_cost', 'making_charge_amt', 'stone_cost', 'purchase_amount', 'metal_value', 'stone_amt'], true)) {
                $inner = '…';
                $cls .= ' gas-tfoot-num gas-tfoot-loading';
            } elseif (in_array($ck, ['stone_rate', 'making_charge_rate'], true)) {
                $inner = '—';
                $cls .= ' gas-tfoot-muted';
            } else {
                $cls .= ' gas-tfoot-muted';
                $inner = '';
            }
        } elseif ($ck === 'imageUrls') {
            $inner = $total_label;
            $cls .= ' gas-tfoot-label';
        } elseif ($ck === 'weight') {
            $inner = gas_fmt_num($gas_grand['weight'], 3);
            $cls .= ' gas-tfoot-num';
        } elseif ($ck === 'gross_wt') {
            $inner = gas_fmt_num($gas_grand['gross_wt'], 3);
            $cls .= ' gas-tfoot-num';
        } elseif ($ck === 'purity_wt') {
            $inner = gas_fmt_num($gas_grand['purity_wt'], 3);
            $cls .= ' gas-tfoot-num';
        } elseif ($ck === 'qty') {
            $inner = gas_fmt_num($gas_grand['qty'], 2);
            $cls .= ' gas-tfoot-num';
        } elseif ($ck === 'stone_wt') {
            $inner = gas_fmt_num($gas_grand['stone_wt'], 3);
            $cls .= ' gas-tfoot-num';
        } elseif ($ck === 'net_wt') {
            $inner = gas_fmt_num($gas_grand['net_wt'], 3);
            $cls .= ' gas-tfoot-num';
        } elseif ($ck === 'wastage_wt') {
            $inner = gas_fmt_num($gas_grand['wastage_wt'], 3);
            $cls .= ' gas-tfoot-num';
        } elseif ($ck === 'wastage_per') {
            $inner = $gas_wastage_per_avg !== null ? gas_fmt_num($gas_wastage_per_avg, 2) : '';
            $cls .= $inner !== '' ? ' gas-tfoot-num' : ' gas-tfoot-muted';
        } elseif ($ck === 'metal_cost') {
            $inner = gas_fmt_money($gas_grand['metal_cost']);
            $cls .= ' gas-tfoot-num';
        } elseif ($ck === 'making_cost') {
            $inner = gas_fmt_money($gas_grand['making_cost']);
            $cls .= ' gas-tfoot-num';
        } elseif ($ck === 'making_charge_amt') {
            $inner = gas_fmt_money($gas_grand['making_charge_amt']);
            $cls .= ' gas-tfoot-num';
        } elseif ($ck === 'stone_cost') {
            $inner = gas_fmt_money($gas_grand['stone_cost']);
            $cls .= ' gas-tfoot-num';
        } elseif ($ck === 'purchase_amount') {
            $inner = gas_fmt_money($gas_grand['purchase_amount']);
            $cls .= ' gas-tfoot-num';
        } elseif ($ck === 'metal_value') {
            $inner = gas_fmt_money($gas_grand['metal_value']);
            $cls .= ' gas-tfoot-num';
        } elseif ($ck === 'stone_amt') {
            $inner = gas_fmt_money($gas_grand['stone_amt']);
            $cls .= ' gas-tfoot-num';
        } elseif (in_array($ck, ['stone_rate', 'making_charge_rate'], true)) {
            $inner = '—';
            $cls .= ' gas-tfoot-muted';
        } else {
            $cls .= ' gas-tfoot-muted';
            $inner = '';
        }
        $gm = $gas_col_meta[$ck];
        $hcls = !empty($gm['hidden']) ? ' gas-col-hidden' : '';
        $wsty = ($gm['width'] !== null) ? ' style="min-width:' . (int) $gm['width'] . 'px;width:' . (int) $gm['width'] . 'px;max-width:560px;"' : '';
        echo '<td data-gas-col="' . htmlspecialchars($ck, ENT_QUOTES, 'UTF-8') . '" class="' . htmlspecialchars($cls . $hcls, ENT_QUOTES, 'UTF-8') . '"' . $wsty . '>' . htmlspecialchars($inner, ENT_QUOTES, 'UTF-8') . '</td>';
    }

    return (string) ob_get_clean();
}
