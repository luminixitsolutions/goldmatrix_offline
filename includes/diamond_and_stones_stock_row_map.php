<?php

/**
 * Map stock list row → table cell HTML/text for diamond-and-stones.php
 */

/**
 * @param array<string, mixed> $r
 * @param array<string, string> $columns
 * @param array{img_cell?: string, barcoded?: string} $ctx
 * @return array<string, string>
 */
function dass_build_row_cells(array $r, array $columns, array $ctx = []): array
{
    $gw = $r['sj_gross_weight'] ?? null;
    if ($gw === null || $gw === '') {
        $gw = $r['opening_weight'] ?? null;
    }
    $lw = $r['sj_less_weight'] ?? null;
    $nw = $r['sj_net_weight'] ?? null;
    $wt = $r['current_weight'] ?? null;
    if ($wt === null || (is_numeric($wt) && (float) $wt <= 0)) {
        $wt = $r['final_weight'] ?? null;
    }
    $pw = $r['sj_purity_weight'] ?? null;
    if ($pw === null || $pw === '') {
        $pw = $r['sj_pure_weight'] ?? null;
    }
    $carat = $r['pc_carat'] ?? '';
    if ($carat === null || $carat === '') {
        $carat = $r['sj_karat'] ?? '';
    }
    $item_code = trim((string) ($r['sj_item_code'] ?? ''));
    if ($item_code === '') {
        $item_code = trim((string) ($r['pc_sku_code'] ?? ''));
    }
    $calc = trim((string) ($r['sj_calculation'] ?? ''));
    $rate = $r['metal_rate_disp'] ?? $r['sj_rate'] ?? $r['metal_value'] ?? null;
    $metal_amt = $r['metal_value'] ?? $r['sj_amount'] ?? null;
    $stone_wt = $r['stone_weight'] ?? null;
    $diamond_amt = $r['diamond_amount'] ?? null;

    $esc = static function ($v): string {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    };
    $num = static function ($v, $dec = 3) use ($esc): string {
        return $esc(gas_fmt_num($v, $dec));
    };
    $money = static function ($v) use ($esc): string {
        return $esc(gas_fmt_money($v));
    };

    $all = [
        'imageUrls' => (string) ($ctx['img_cell'] ?? ''),
        'barcode' => $esc($r['barcode'] ?? ''),
        'barcoded_date' => $esc($ctx['barcoded'] ?? ''),
        'rfid' => $esc($r['rfid_code'] ?? ''),
        'purchase_amount' => $money($r['purchase_amount'] ?? null),
        'item_code' => $esc($item_code),
        'calculation_type' => $esc($calc),
        'product_name' => $esc($r['product_name'] ?? ''),
        'gross_wt' => $num($gw, 3),
        'less_wt' => $num($lw, 3),
        'minimum_price' => $money($r['minimum_price'] ?? null),
        'qty' => $num($r['current_qty'] ?? null, 2),
        'rate' => $money($rate),
        'metal_amount' => $money($metal_amt),
        'sale_amount' => $money($r['sale_amount'] ?? null),
        'tag_amount' => '',
        'location' => $esc($r['sj_location'] ?? ''),
        'branch_name' => $esc($r['branch_name'] ?? ''),
        'voucher_type' => $esc($r['voucher_type'] ?? ''),
        'invoice_no' => $esc($r['invoice_no'] ?? ''),
        'supplier_name' => $esc($r['supplier_name'] ?? ''),
        'category' => $esc($r['category_display'] ?? $r['pc_diamond_category'] ?? ''),
        'style' => $esc($r['pc_style_code'] ?? ''),
        'clarity' => $esc($r['pc_clarity'] ?? ''),
        'color' => $esc($r['pc_color'] ?? ''),
        'shape' => $esc($r['pc_shape'] ?? ''),
        'size' => $esc($r['pc_size'] ?? ''),
        'seive_size' => $esc($r['pc_sieve'] ?? ''),
        'making_rate' => $money($r['making_rate'] ?? null),
        'making_amount' => $money($r['making_amount'] ?? null),
        'certificate_amount' => $money($r['hallmark_amount'] ?? null),
        'markup_amount' => $money($diamond_amt),
        'net_wt' => $num($nw, 3),
        'net_amount' => $money($r['net_amount'] ?? null),
        'net_amount_with_tax' => $money($r['net_amt_with_tax'] ?? null),
        'tax_amount' => $money($r['tax_amount'] ?? null),
        'other_amount' => $money($r['other_amount'] ?? null),
        'metal_purity_wt' => $num($pw, 3),
        'metal_loss_wt' => $num($r['gold_loss_1'] ?? $r['wastage_wt'] ?? null, 3),
        'metal_loss_value' => $money($r['gold_loss_2'] ?? null),
        'setting_charge' => $num($r['setting_charge'] ?? null, 3),
        'setting_charge_amount' => $money($r['setting_charge'] ?? null),
        'comment' => $esc($r['sj_comment'] ?? ''),
        'article' => $esc($r['article'] ?? ''),
        'certificate_no' => '',
        'video_link' => '',
        'certificate_link' => '',
        'cut' => $esc($r['pc_cut'] ?? ''),
        'account_no' => '',
        'metal_karat' => $esc($r['sj_karat'] ?? ''),
        'huid_no' => $esc($r['huid_no'] ?? ''),
        'metal_rate' => $money($r['metal_rate_disp'] ?? $rate),
        'description' => $esc($r['product_description'] ?? ''),
        'design_no' => $esc($r['design_no'] ?? ''),
        'product_size' => $esc($r['pc_size'] ?? ''),
        'metal_color' => '',
        'customer_name' => '',
        'diamond_wt' => $num($stone_wt, 3),
        'diamond_ct' => $num($carat, 3),
        't_dia_stone_carat' => $num($carat, 3),
        't_dia_stone_weight' => $num($stone_wt, 3),
        'stone_wt' => $num($stone_wt, 3),
        'stone_ct' => $num($carat, 3),
        'markup_per' => $num($r['discount_per'] ?? null, 2),
        'carat' => $esc($carat),
        'ratti' => '',
        'action' => '<button type="button" class="btn btn-sm btn-outline-secondary dass-row-view-btn" data-barcode="' . $esc($r['barcode'] ?? '') . '" title="View details"><i class="feather icon-eye"></i></button>',
    ];

    $out = [];
    foreach (array_keys($columns) as $ck) {
        $out[$ck] = $all[$ck] ?? '';
    }
    return $out;
}

/**
 * @param array<string, float> $grand
 * @param array<string, string> $columns
 */
function dass_accumulate_grand(array &$grand, array $r): void
{
    $add = static function ($key, $val) use (&$grand) {
        if ($val === null || $val === '' || !is_numeric($val)) {
            return;
        }
        if (!isset($grand[$key])) {
            $grand[$key] = 0.0;
        }
        $grand[$key] += (float) $val;
    };

    $gw = $r['sj_gross_weight'] ?? $r['opening_weight'] ?? null;
    $nw = $r['sj_net_weight'] ?? null;
    $pw = $r['sj_purity_weight'] ?? $r['sj_pure_weight'] ?? null;

    $add('gross_wt', $gw);
    $add('less_wt', $r['sj_less_weight'] ?? null);
    $add('net_wt', $nw);
    $add('qty', $r['current_qty'] ?? null);
    $add('purchase_amount', $r['purchase_amount'] ?? null);
    $add('metal_amount', $r['metal_value'] ?? null);
    $add('sale_amount', $r['sale_amount'] ?? null);
    $add('making_amount', $r['making_amount'] ?? null);
    $add('certificate_amount', $r['hallmark_amount'] ?? null);
    $add('markup_amount', $r['diamond_amount'] ?? null);
    $add('net_amount', $r['net_amount'] ?? null);
    $add('net_amount_with_tax', $r['net_amt_with_tax'] ?? null);
    $add('tax_amount', $r['tax_amount'] ?? null);
    $add('other_amount', $r['other_amount'] ?? null);
    $add('metal_purity_wt', $pw);
    $add('metal_loss_wt', $r['gold_loss_1'] ?? $r['wastage_wt'] ?? null);
    $add('metal_loss_value', $r['gold_loss_2'] ?? null);
    $add('setting_charge_amount', $r['setting_charge'] ?? null);
    $add('diamond_wt', $r['stone_weight'] ?? null);
    $add('stone_wt', $r['stone_weight'] ?? null);
    $carat = $r['pc_carat'] ?? $r['sj_karat'] ?? null;
    $add('diamond_ct', $carat);
    $add('stone_ct', $carat);
    $add('t_dia_stone_carat', $carat);
    $add('t_dia_stone_weight', $r['stone_weight'] ?? null);
    $add('carat', $carat);
}

/**
 * @param array<string, float> $grand
 * @param array<string, string> $columns
 */
function dass_footer_cell(string $ck, array $grand, array $columns): array
{
    $money_keys = ['purchase_amount', 'metal_amount', 'sale_amount', 'tag_amount', 'making_amount', 'certificate_amount', 'markup_amount', 'net_amount', 'net_amount_with_tax', 'tax_amount', 'other_amount', 'metal_loss_value', 'setting_charge_amount'];
    $num3 = ['gross_wt', 'less_wt', 'net_wt', 'metal_purity_wt', 'metal_loss_wt', 'diamond_wt', 'stone_wt', 't_dia_stone_weight', 'setting_charge'];
    $num2 = ['qty'];
    $num_carat = ['diamond_ct', 'stone_ct', 't_dia_stone_carat', 'carat', 'ratti'];

    if ($ck === 'imageUrls') {
        return ['inner' => 'Grand Total', 'cls' => 'gas-tfoot-label'];
    }
    if (!isset($grand[$ck])) {
        return ['inner' => '', 'cls' => 'gas-tfoot-muted'];
    }
    $v = $grand[$ck];
    if (in_array($ck, $money_keys, true)) {
        return ['inner' => gas_fmt_money($v), 'cls' => 'gas-tfoot-num'];
    }
    if (in_array($ck, $num3, true)) {
        return ['inner' => gas_fmt_num($v, 3), 'cls' => 'gas-tfoot-num'];
    }
    if (in_array($ck, $num2, true)) {
        return ['inner' => gas_fmt_num($v, 2), 'cls' => 'gas-tfoot-num'];
    }
    if (in_array($ck, $num_carat, true)) {
        return ['inner' => gas_fmt_num($v, 3), 'cls' => 'gas-tfoot-num'];
    }
    return ['inner' => '', 'cls' => 'gas-tfoot-muted'];
}
