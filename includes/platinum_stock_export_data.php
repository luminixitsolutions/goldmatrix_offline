<?php

/**
 * Platinum stock — Excel export row shaping + fetch (same display rules as platinum-stock.php).
 */
require_once __DIR__ . '/platinum_stock_gas_url_helpers.php';

/** @return list<string> */
function auragold_platinum_stock_export_column_keys(): array
{
    return [
        'imageUrls',
        'info',
        'huid',
        'barcode',
        'product_name',
        'location',
        'weight',
        'gross_wt',
        'purity_wt',
        'qty',
        'carat',
        'active',
        'voucher_type',
        'invoice_no',
        'supplier_name',
        'category',
        'article',
        'metal_cost',
        'making_cost',
        'stone_wt',
        'net_wt',
        'barcoded_date',
        'making_charge_amt',
        'stone_cost',
        'purchase_amount',
        'making_type',
        'metal_value',
        'stone_rate',
        'stone_charge_type',
        'stone_amt',
        'making_charge_rate',
        'wastage_wt',
        'wastage_per',
    ];
}

/** @return array<string, string> */
function auragold_platinum_stock_export_column_label_map(): array
{
    return [
        'imageUrls' => 'Primary image URL',
        'info' => 'Info',
        'huid' => 'HUID No.',
        'barcode' => 'Barcode No',
        'product_name' => 'Product Name',
        'location' => 'Location',
        'weight' => 'Weight',
        'gross_wt' => 'Gross Wt',
        'purity_wt' => 'Purity Wt',
        'qty' => 'Qty',
        'carat' => 'Carat',
        'active' => 'active',
        'voucher_type' => 'Voucher Type',
        'invoice_no' => 'Invoice No.',
        'supplier_name' => 'Supplier Name',
        'category' => 'Category',
        'article' => 'Article',
        'metal_cost' => 'Metal Cost',
        'making_cost' => 'Making Cost',
        'stone_wt' => 'Stone Wt',
        'net_wt' => 'Net Wt',
        'barcoded_date' => 'Barcoded Date',
        'making_charge_amt' => 'Making Charge Amt.',
        'stone_cost' => 'Stone Cost',
        'purchase_amount' => 'Purchase Amount',
        'making_type' => 'Making Type',
        'metal_value' => 'Metal Value',
        'stone_rate' => 'Stone Rate',
        'stone_charge_type' => 'Stone Charge Type',
        'stone_amt' => 'Stone Amt.',
        'making_charge_rate' => 'Making Charge Rate',
        'wastage_wt' => 'Wastage Wt',
        'wastage_per' => 'Wastage Per.',
    ];
}

/** @return list<string> */
function auragold_platinum_stock_export_header_titles(): array
{
    $map = auragold_platinum_stock_export_column_label_map();
    $titles = [];
    foreach (auragold_platinum_stock_export_column_keys() as $k) {
        $titles[] = $map[$k] ?? $k;
    }

    return $titles;
}

function auragold_platinum_stock_export_first_image_url(array $r, $siteUrl): string
{
    $imgs_raw = trim((string) ($r['image_urls'] ?? ''));
    if ($imgs_raw === '') {
        return '';
    }
    $first = explode(',', $imgs_raw)[0];
    $first = trim($first);
    if ($first === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $first) || strpos($first, '/') === 0) {
        return $first;
    }
    if (!function_exists('gas_public_url_for_stored_path')) {
        return $first;
    }

    return gas_public_url_for_stored_path($first, $siteUrl);
}

/**
 * One row as column_key => scalar (numbers as float/int where appropriate for Excel).
 *
 * @return array<string, string|float|int|null>
 */
function auragold_platinum_stock_export_row_scalars(array $r, $siteUrl): array
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
    $info = trim((string) ($r['info_text'] ?? ''));

    $num = static function ($v): ?float {
        if ($v === null || $v === '') {
            return null;
        }
        if (!is_numeric($v)) {
            return null;
        }
        $x = (float) $v;

        return is_finite($x) ? $x : null;
    };

    $money = static function ($v) use ($num): ?float {
        $x = $num($v);
        if ($x === null) {
            return null;
        }

        return round($x, 2);
    };

    return [
        'imageUrls' => auragold_platinum_stock_export_first_image_url($r, $siteUrl),
        'info' => $info,
        'huid' => trim((string) ($r['huid_no'] ?? '')),
        'barcode' => trim((string) ($r['barcode'] ?? '')),
        'product_name' => trim((string) ($r['product_name'] ?? '')),
        'location' => trim((string) ($r['sj_location'] ?? '')),
        'weight' => $num($wt),
        'gross_wt' => $num($gw),
        'purity_wt' => $num($pw),
        'qty' => $num($r['current_qty'] ?? null),
        'carat' => trim((string) $carat),
        'active' => $active_disp,
        'voucher_type' => trim((string) ($r['voucher_type'] ?? '')),
        'invoice_no' => trim((string) ($r['invoice_no'] ?? '')),
        'supplier_name' => trim((string) ($r['supplier_name'] ?? '')),
        'category' => trim((string) ($r['category_display'] ?? '')),
        'article' => trim((string) ($r['article'] ?? '')),
        'metal_cost' => $money($r['metal_cost'] ?? null),
        'making_cost' => $money($r['making_cost'] ?? null),
        'stone_wt' => $num($r['stone_weight'] ?? null),
        'net_wt' => $num($nw),
        'barcoded_date' => (string) $barcoded,
        'making_charge_amt' => $money($r['making_amount'] ?? null),
        'stone_cost' => $money($r['stone_cost'] ?? null),
        'purchase_amount' => $money($r['purchase_amount'] ?? null),
        'making_type' => trim((string) ($r['making_type'] ?? '')),
        'metal_value' => $money($r['metal_value'] ?? null),
        'stone_rate' => $money($r['stone_rate'] ?? null),
        'stone_charge_type' => trim((string) ($r['stone_charge_type'] ?? '')),
        'stone_amt' => $money($r['stone_amount'] ?? null),
        'making_charge_rate' => $money($r['making_rate'] ?? null),
        'wastage_wt' => $num($r['wastage_wt'] ?? null),
        'wastage_per' => $num($r['wastage_per'] ?? null),
    ];
}

/** @return array<string, float> */
function auragold_platinum_stock_export_grand_initial(): array
{
    return [
        'weight' => 0.0,
        'gross_wt' => 0.0,
        'purity_wt' => 0.0,
        'qty' => 0.0,
        'stone_wt' => 0.0,
        'net_wt' => 0.0,
        'wastage_wt' => 0.0,
        'metal_cost' => 0.0,
        'making_cost' => 0.0,
        'making_charge_amt' => 0.0,
        'stone_cost' => 0.0,
        'purchase_amount' => 0.0,
        'metal_value' => 0.0,
        'stone_amt' => 0.0,
    ];
}

/**
 * @param array<string, float> $grand
 * @return array{grand: array<string, float>, wastage_per_sum: float, wastage_per_cnt: int}
 */
function auragold_platinum_stock_export_accumulate_grand(array $grand, float $wps, int $wpc, array $scalars): array
{
    $g = $grand;
    $add = static function (string $k, $v) use (&$g): void {
        if (isset($g[$k]) && $v !== null && is_numeric($v)) {
            $g[$k] += (float) $v;
        }
    };
    $add('weight', $scalars['weight'] ?? null);
    $add('gross_wt', $scalars['gross_wt'] ?? null);
    $add('purity_wt', $scalars['purity_wt'] ?? null);
    $add('qty', $scalars['qty'] ?? null);
    $add('stone_wt', $scalars['stone_wt'] ?? null);
    $add('net_wt', $scalars['net_wt'] ?? null);
    $add('wastage_wt', $scalars['wastage_wt'] ?? null);
    $add('metal_cost', $scalars['metal_cost'] ?? null);
    $add('making_cost', $scalars['making_cost'] ?? null);
    $add('making_charge_amt', $scalars['making_charge_amt'] ?? null);
    $add('stone_cost', $scalars['stone_cost'] ?? null);
    $add('purchase_amount', $scalars['purchase_amount'] ?? null);
    $add('metal_value', $scalars['metal_value'] ?? null);
    $add('stone_amt', $scalars['stone_amt'] ?? null);
    $wp = $scalars['wastage_per'] ?? null;
    if ($wp !== null && $wp !== '' && is_numeric($wp)) {
        $wps += (float) $wp;
        ++$wpc;
    }

    return ['grand' => $g, 'wastage_per_sum' => $wps, 'wastage_per_cnt' => $wpc];
}

/** @return array{rows: array<int, array<string, mixed>>, error: string} */
function auragold_platinum_stock_export_fetch_rows(mysqli $conn, int $limit = 15000): array
{
    require_once __DIR__ . '/platinum_stock_list_sql_include.php';
    if (!$has_stock) {
        return ['rows' => [], 'error' => 'Stock table not found.'];
    }
    $limit = max(1, min(50000, $limit));
    $sql = $platinum_stock_list_sql . ' LIMIT ' . $limit;
    $rows = [];
    $res = mysqli_query($conn, $sql);
    if (!$res) {
        return ['rows' => [], 'error' => 'Could not load stock: ' . mysqli_error($conn)];
    }
    while ($r = mysqli_fetch_assoc($res)) {
        $rows[] = $r;
    }
    mysqli_free_result($res);

    return ['rows' => $rows, 'error' => ''];
}

/** @return list<string> */
function auragold_platinum_stock_export_numeric_keys(): array
{
    return [
        'weight', 'gross_wt', 'purity_wt', 'qty', 'stone_wt', 'net_wt',
        'metal_cost', 'making_cost', 'making_charge_amt', 'stone_cost', 'purchase_amount',
        'metal_value', 'stone_rate', 'stone_amt', 'making_charge_rate',
        'wastage_wt', 'wastage_per',
    ];
}
