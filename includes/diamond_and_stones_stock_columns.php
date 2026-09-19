<?php

/**
 * Column definitions for diamond-and-stones.php (per tab).
 *
 * @return array<string, array<string, string>> tab_key => [ column_key => label ]
 */
function dass_stock_columns_for_tab(string $tab): array
{
    $common = [
        'imageUrls' => 'imageUrls',
        'barcode' => 'Barcode No',
        'barcoded_date' => 'Barcoded Date',
        'rfid' => 'RFID',
        'purchase_amount' => 'Purchase Amount',
        'item_code' => 'Item Code',
        'calculation_type' => 'Calculation Type',
        'product_name' => 'Product Name',
        'gross_wt' => 'Gross Wt',
        'less_wt' => 'Less Wt',
        'minimum_price' => 'Minimum Price',
        'qty' => 'Qty',
        'rate' => 'Rate',
        'metal_amount' => 'Metal Amount',
        'sale_amount' => 'Sale Amount',
        'tag_amount' => 'Tag Amount',
        'location' => 'Location',
        'branch_name' => 'Branch Name',
        'voucher_type' => 'Voucher Type',
        'invoice_no' => 'Invoice No.',
        'supplier_name' => 'Supplier Name',
        'category' => 'Category',
        'style' => 'Style',
        'clarity' => 'Clarity',
        'color' => 'Color',
        'shape' => 'Shape',
        'size' => 'Size',
        'seive_size' => 'Seive Size',
        'making_rate' => 'Making Rate',
        'making_amount' => 'Making Amount',
        'certificate_amount' => 'Certificate Amount',
        'markup_amount' => 'Markup Amount',
        'net_wt' => 'Net Wt',
        'net_amount' => 'Net Amount',
        'net_amount_with_tax' => 'Net Amount With Tax',
        'tax_amount' => 'Tax Amount',
        'other_amount' => 'Other Amount',
        'metal_purity_wt' => 'Metal Purity Wt',
        'metal_loss_wt' => 'Metal Loss Wt',
        'metal_loss_value' => 'Metal Loss Value',
        'setting_charge' => 'Setting Charge',
        'setting_charge_amount' => 'Setting Charge Amount',
        'comment' => 'Comment',
        'article' => 'Article',
        'certificate_no' => 'Certificate No',
        'video_link' => 'Video Link',
        'certificate_link' => 'Certificate Link',
        'cut' => 'Cut',
        'account_no' => 'Account No.',
    ];

    $jewellery_tail = [
        'metal_karat' => 'Metal Karat',
        'huid_no' => 'HUID No.',
        'metal_rate' => 'Metal Rate',
        'description' => 'Description',
        'design_no' => 'Design No',
        'product_size' => 'Product Size',
        'metal_color' => 'Metal Color',
        'customer_name' => 'Customer Name',
        'diamond_wt' => 'Diamond Wt',
        'diamond_ct' => 'Diamond Ct',
        't_dia_stone_carat' => 'T. Dia/Stone Carat',
        't_dia_stone_weight' => 'T. Dia/Stone Weight',
        'stone_wt' => 'Stone Wt',
        'stone_ct' => 'Stone Ct',
        'markup_per' => 'Markup Per',
        'action' => 'action',
    ];

    $diamond_gem_tail = [
        'carat' => 'Carat',
        'action' => 'action',
    ];

    $tab = strtolower(trim($tab));
    if ($tab === 'diamond' || $tab === 'diamonds') {
        return $common + $diamond_gem_tail;
    }
    if ($tab === 'gemstone' || $tab === 'gemstones') {
        return $common + ['ratti' => 'Ratti'] + $diamond_gem_tail;
    }

    return $common + $jewellery_tail;
}

/**
 * @return string Normalized tab key: jewellery|diamond|gemstone
 */
function dass_stock_normalize_tab(?string $tab): string
{
    $t = strtolower(trim((string) $tab));
    if ($t === 'diamond' || $t === 'diamonds') {
        return 'diamond';
    }
    if ($t === 'gemstone' || $t === 'gemstones') {
        return 'gemstone';
    }
    return 'jewellery';
}

/**
 * Diamond category value for SQL filter (tbl_product_characteristics.diamond_category).
 */
function dass_stock_diamond_category_for_tab(string $tab): string
{
    $tab = dass_stock_normalize_tab($tab);
    if ($tab === 'diamond') {
        return 'Diamonds';
    }
    if ($tab === 'gemstone') {
        return 'GemStones';
    }
    return 'Jewellery';
}

/** @return array<string, array{label: string, keys: string[]}> */
function dass_stock_column_group_defs(array $gas_columns): array
{
    $all_keys = array_keys($gas_columns);
    $pick = static function (array $keys) use ($all_keys): array {
        return array_values(array_filter($keys, static function ($k) use ($all_keys) {
            return in_array($k, $all_keys, true);
        }));
    };

    return [
        'media' => [
            'label' => 'Media &amp; status',
            'keys' => $pick(['imageUrls']),
        ],
        'ids' => [
            'label' => 'Barcode &amp; product',
            'keys' => $pick(['barcode', 'barcoded_date', 'rfid', 'item_code', 'calculation_type', 'product_name', 'article', 'huid_no', 'design_no']),
        ],
        'weights' => [
            'label' => 'Weights &amp; qty',
            'keys' => $pick(['gross_wt', 'less_wt', 'net_wt', 'qty', 'carat', 'metal_purity_wt', 'metal_loss_wt', 'diamond_wt', 'diamond_ct', 'stone_wt', 'stone_ct', 't_dia_stone_carat', 't_dia_stone_weight', 'ratti']),
        ],
        'amounts' => [
            'label' => 'Rates &amp; amounts',
            'keys' => $pick(['rate', 'metal_rate', 'minimum_price', 'purchase_amount', 'metal_amount', 'sale_amount', 'tag_amount', 'making_rate', 'making_amount', 'certificate_amount', 'markup_amount', 'markup_per', 'net_amount', 'net_amount_with_tax', 'tax_amount', 'other_amount', 'metal_loss_value', 'setting_charge', 'setting_charge_amount']),
        ],
        'attrs' => [
            'label' => 'Attributes',
            'keys' => $pick(['category', 'style', 'clarity', 'color', 'shape', 'size', 'seive_size', 'cut', 'metal_karat', 'metal_color', 'product_size']),
        ],
        'doc' => [
            'label' => 'Document &amp; links',
            'keys' => $pick(['location', 'branch_name', 'voucher_type', 'invoice_no', 'supplier_name', 'customer_name', 'account_no', 'certificate_no', 'video_link', 'certificate_link', 'description', 'comment']),
        ],
        'other' => [
            'label' => 'Other',
            'keys' => $pick(['action']),
        ],
    ];
}

/** Numeric columns that sum in footer. */
function dass_stock_numeric_total_keys(): array
{
    return [
        'gross_wt', 'less_wt', 'net_wt', 'qty', 'purchase_amount', 'metal_amount', 'sale_amount', 'tag_amount',
        'making_amount', 'certificate_amount', 'markup_amount', 'net_amount', 'net_amount_with_tax', 'tax_amount',
        'other_amount', 'metal_purity_wt', 'metal_loss_wt', 'metal_loss_value', 'setting_charge', 'setting_charge_amount',
        'diamond_wt', 'diamond_ct', 'stone_wt', 'stone_ct', 't_dia_stone_carat', 't_dia_stone_weight', 'carat', 'ratti',
    ];
}
