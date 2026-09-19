<?php
/**
 * Sale invoice print column catalog — mirrors product line grid columns (sale invoice Add Item modal).
 */
if (!function_exists('auragold_invoice_print_sale_column_key_from_data_column')) {
    /**
     * Map product modal data-column (kebab-case) to invoice print setting key (snake_case).
     */
    function auragold_invoice_print_sale_column_key_from_data_column($data_column) {
        static $overrides = [
            'design-no' => 'design_no',
            'gross-wt' => 'gross_weight',
            'less-wt' => 'less_weight',
            'net-wt' => 'net_weight',
            'final-wt' => 'final_weight',
            'pure-wt' => 'pure_weight',
            'purity-wt' => 'purity_weight',
            'alloy-wt' => 'alloy_wt',
            'pkt-wt' => 'pkt_wt',
            'pkt-less-wt' => 'pkt_less_wt',
            'making-amount' => 'making_charge',
            'making-charge' => 'making_charge',
            'diamond-amount' => 'diamond_amount',
            'stone-amount' => 'stone_amount',
            'stone-weight' => 'stone_weight',
            'diamond-carat' => 'diamond_carat',
            'metal-qty' => 'metal_qty',
            'metal-weight' => 'metal_weight',
            'metal-rate' => 'metal_rate',
            'metal-value' => 'metal_value',
            'metal-cost' => 'metal_cost',
            'gold-loss1' => 'gold_loss1',
            'gold-loss2' => 'gold_loss2',
            'metal-loss-value' => 'metal_loss_value',
            'wastage-per' => 'wastage_per',
            'wastage-wt' => 'wastage_wt',
            'requested-purity' => 'requested_purity',
            'setting-charge' => 'setting_charge',
            'discount-amount' => 'discount_amount',
            'making-rate' => 'making_rate',
            'making-cost' => 'making_cost',
            'stone-rate' => 'stone_rate',
            'stone-cost' => 'stone_cost',
            'purchase-amount' => 'purchase_amount',
            'sale-amount' => 'sale_amount',
            'sale-amount-with' => 'sale_amount_with',
            'net-amt' => 'net_amt',
            'net-amt-tax' => 'net_amt_with_tax',
            'tax-type' => 'tax_type',
            'tax-percent' => 'tax_percent',
            'other-amount' => 'other_amount',
            'hallmark-amount' => 'hallmark_amount',
            'hallmark-rate' => 'hallmark_rate',
            'voucher-type' => 'voucher_type',
            'product-category' => 'product_category',
            'item-code' => 'item_code',
            'short-code' => 'short_code',
            'sale-percent' => 'sale_percent',
            'product' => 'item_name',
            'huid' => 'huid',
            'category' => 'category',
            'minimum-code' => 'minimum_code',
            'min-price' => 'min_price',
            'other-charge-type' => 'other_charge_type',
            'other-weight' => 'other_weight',
            'other-rate' => 'other_rate',
            'other-info' => 'other_info',
            'stone-charge-type' => 'stone_charge_type',
            'making-discount-amt' => 'making_discount_amt',
            'making-actual-value' => 'making_actual_value',
            'discount-type' => 'discount_type',
            'discount-per' => 'discount_per',
            'platinum-weight' => 'platinum_weight',
            'platinum-karat' => 'platinum_karat',
            'platinum-purity' => 'platinum_purity',
            'platinum-purity-wt' => 'platinum_purity_wt',
            'platinum-rate' => 'platinum_rate',
            'platinum-wastage-per' => 'platinum_wastage_per',
            'platinum-wastage-wt' => 'platinum_wastage_wt',
            'platinum-amount' => 'platinum_amount',
            'fc-amount' => 'fc_amount',
            'diamond-line-metal-value' => 'diamond_line_metal_value',
            'rapnet-valuation' => 'rapnet_valuation',
            'mark-up-amount' => 'mark_up_amount',
            'mark-up-per' => 'mark_up_per',
            'certificate-amount' => 'certificate_amount',
            'certificate-no' => 'certificate_no',
            'certificate-link' => 'certificate_link',
            'video-link' => 'video_link',
            'seive-size' => 'seive_size',
            'unit-price' => 'unit_price',
        ];
        $dc = trim((string) $data_column);
        if ($dc === '') {
            return '';
        }
        if (isset($overrides[$dc])) {
            return $overrides[$dc];
        }
        return str_replace('-', '_', $dc);
    }
}

if (!function_exists('getInvoicePrintSaleInvoiceColumnGroupLabels')) {
    function getInvoicePrintSaleInvoiceColumnGroupLabels() {
        return [
            'basic' => 'Basic',
            'diamond' => 'Diamond',
            'metal' => 'Metal',
            'reqfinal' => 'Request & Final Wt.',
            'disc' => 'Discount',
            'making' => 'Making',
            'stone' => 'Stone',
            'amt' => 'Amounts',
            'other' => 'Other Charge',
            'hall' => 'Hallmark',
            'netrev' => 'Net Amt+Tax',
            'platinum' => 'Platinum',
            'cert' => 'Certificate / Spec',
            'extra' => 'Extra Fields',
        ];
    }
}

if (!function_exists('auragold_get_invoice_print_extra_field_column_defs')) {
    /**
     * Active user-defined extra fields (Set Software → Extra Fields) as invoice print columns.
     *
     * @return array<int, array{key:string,label:string,group:string,metal_type:string}>
     */
    function auragold_get_invoice_print_extra_field_column_defs($conn, int $branch_id): array {
        if (!$conn instanceof mysqli) {
            return [];
        }
        if (!function_exists('auragold_get_extra_fields')) {
            require_once dirname(__FILE__) . '/auragold_extra_fields_schema.php';
        }

        $seen = [];
        $defs = [];
        foreach (auragold_extra_field_metals() as $metal) {
            foreach (auragold_get_extra_fields($conn, $branch_id, $metal) as $row) {
                if ((int) ($row['status'] ?? 0) !== 1) {
                    continue;
                }
                $id = (int) ($row['id'] ?? 0);
                if ($id <= 0 || isset($seen[$id])) {
                    continue;
                }
                $seen[$id] = true;
                $label = trim((string) ($row['display_name'] ?? ''));
                if ($label === '') {
                    $label = 'Extra Field ' . $id;
                }
                $defs[] = [
                    'key' => auragold_barcode_extra_field_key($id),
                    'label' => $label,
                    'group' => 'extra',
                    'metal_type' => (string) ($row['metal_type'] ?? ''),
                ];
            }
        }

        return $defs;
    }
}

if (!function_exists('auragold_merge_invoice_print_extra_field_columns')) {
    /** @param array<string, array{label:string, group:string}> $catalog */
    function auragold_merge_invoice_print_extra_field_columns(array &$catalog, $conn, int $branch_id): void {
        foreach (auragold_get_invoice_print_extra_field_column_defs($conn, $branch_id) as $def) {
            $key = $def['key'];
            if (!isset($catalog[$key])) {
                $catalog[$key] = [
                    'label' => $def['label'],
                    'group' => $def['group'],
                ];
            }
        }
    }
}

if (!function_exists('auragold_invoice_print_extra_field_column_labels')) {
    /** @return array<string, string> */
    function auragold_invoice_print_extra_field_column_labels($conn, int $branch_id): array {
        $out = [];
        foreach (auragold_get_invoice_print_extra_field_column_defs($conn, $branch_id) as $def) {
            $out[$def['key']] = $def['label'];
        }
        return $out;
    }
}

if (!function_exists('auragold_invoice_print_extra_field_values_from_item')) {
    /**
     * @return array<string, string> Keys ExtraField_{id}
     */
    function auragold_invoice_print_extra_field_values_from_item(array $item, $conn = null): array {
        if (!function_exists('auragold_barcode_parse_extra_fields_json_map')) {
            require_once dirname(__FILE__) . '/auragold_extra_fields_schema.php';
        }

        $map = [];
        if (!empty($item['extra_fields_json'])) {
            $map = array_merge($map, auragold_barcode_parse_extra_fields_json_map($item['extra_fields_json']));
        }
        if (!empty($item['extra_fields']) && is_array($item['extra_fields'])) {
            $map = array_merge($map, auragold_barcode_parse_extra_fields_json_map($item['extra_fields']));
        }
        if ($conn instanceof mysqli) {
            $barcode = trim((string) ($item['barcode'] ?? ''));
            $sj_id = (int) ($item['stock_journal_item_id'] ?? $item['sj_item_id'] ?? 0);
            if ($barcode !== '' && function_exists('auragold_barcode_fetch_extra_field_values_for_barcode')) {
                $map = array_merge($map, auragold_barcode_fetch_extra_field_values_for_barcode($conn, $barcode, $sj_id));
            }
        }

        $out = [];
        foreach ($map as $key => $val) {
            if (preg_match('/^ExtraField_(\d+)$/i', (string) $key, $m)) {
                $out['ExtraField_' . $m[1]] = (string) $val;
            }
        }

        return $out;
    }
}

if (!function_exists('getInvoicePrintSaleInvoiceColumnCatalog')) {
    /**
     * @return array<string, array{label:string, group:string}>
     */
    function getInvoicePrintSaleInvoiceColumnCatalog() {
        static $catalog = null;
        if ($catalog !== null) {
            return $catalog;
        }

        $catalog = [
            'sr_no' => ['label' => 'Sr No', 'group' => 'basic'],
        ];

        $legacy_labels = [
            'item_name' => 'Item Name',
            'design_no' => 'Design No',
            'huid' => 'HUID',
            'category' => 'Category',
            'gross_weight' => 'Gross Weight',
            'less_weight' => 'Less Weight',
            'net_weight' => 'Net Weight',
            'purity_karat' => 'Purity / Karat',
            'rate' => 'Rate',
            'making_charge' => 'Making Charge',
            'diamond_amount' => 'Diamond Amount',
            'stone_amount' => 'Stone Amount',
            'discount' => 'Discount',
            'amount' => 'Amount',
        ];
        foreach ($legacy_labels as $k => $lab) {
            $catalog[$k] = ['label' => $lab, 'group' => 'basic'];
        }

        $skip_data_columns = [
            'reverse', 'photo', 'images', 'actions', 'checkbox', 'metal-group',
            'platinum-group', 'making-group', 'discount-group',
        ];

        $product_list_table_columns = [];
        $product_list_table_group_labels = [];
        $pl_file = dirname(__FILE__) . '/product-list-table-columns-data.php';
        if (is_file($pl_file)) {
            require $pl_file;
        }
        foreach ($product_list_table_columns as $col) {
            if (!is_array($col) || count($col) < 3) {
                continue;
            }
            $data_col = (string) $col[0];
            if (in_array($data_col, $skip_data_columns, true)) {
                continue;
            }
            $print_key = auragold_invoice_print_sale_column_key_from_data_column($data_col);
            if ($print_key === '' || $print_key === 'sr_no') {
                continue;
            }
            $catalog[$print_key] = [
                'label' => (string) $col[1],
                'group' => (string) $col[2],
            ];
        }

        $extra_modal_columns = [
            ['short-code', 'Short Code', 'basic'],
            ['item-code', 'Item Code', 'basic'],
            ['product-category', 'Product Category', 'basic'],
            ['sale-percent', 'Sale Percentages', 'amt'],
            ['fc-amount', 'FC Amount', 'diamond'],
            ['diamond-line-metal-value', 'Diamond Line Metal Value', 'diamond'],
            ['rapnet-valuation', 'RapNet Valuation', 'diamond'],
            ['mark-up-amount', 'Mark Up Amount', 'diamond'],
            ['mark-up-per', 'Mark Up %', 'diamond'],
            ['platinum-weight', 'Platinum Weight', 'platinum'],
            ['platinum-karat', 'Platinum Karat', 'platinum'],
            ['platinum-purity', 'Platinum Purity %', 'platinum'],
            ['platinum-purity-wt', 'Platinum Purity Wt', 'platinum'],
            ['platinum-rate', 'Platinum Rate', 'platinum'],
            ['platinum-wastage-per', 'Platinum Wastage %', 'platinum'],
            ['platinum-wastage-wt', 'Platinum Wastage Wt', 'platinum'],
            ['platinum-amount', 'Platinum Amount', 'platinum'],
            ['certificate-amount', 'Certificate Amount', 'cert'],
            ['certificate-no', 'Certificate No.', 'cert'],
            ['certificate-link', 'Certificate Link', 'cert'],
            ['video-link', 'Video Link', 'cert'],
            ['cut', 'Cut', 'cert'],
            ['color', 'Color', 'cert'],
            ['seive-size', 'Seive Size', 'cert'],
            ['size', 'Size', 'cert'],
            ['shape', 'Shape', 'cert'],
            ['clarity', 'Clarity', 'cert'],
            ['unit-price', 'Unit Price', 'cert'],
            ['quantity', 'Qty', 'metal'],
            ['tax', 'Tax', 'amt'],
            ['net_amt_with_tax', 'Net Amt+Tax', 'netrev'],
        ];
        foreach ($extra_modal_columns as $col) {
            $print_key = auragold_invoice_print_sale_column_key_from_data_column($col[0]);
            if ($print_key === '') {
                $print_key = $col[0];
            }
            if (!isset($catalog[$print_key])) {
                $catalog[$print_key] = ['label' => (string) $col[1], 'group' => (string) $col[2]];
            }
        }

        foreach ($legacy_labels as $k => $lab) {
            $catalog[$k]['label'] = $lab;
        }

        return $catalog;
    }
}

if (!function_exists('getInvoicePrintSaleInvoiceColumnLabels')) {
    /** @return array<string, string> */
    function getInvoicePrintSaleInvoiceColumnLabels() {
        $out = [];
        foreach (getInvoicePrintSaleInvoiceColumnCatalog() as $key => $meta) {
            $out[$key] = $meta['label'];
        }
        return $out;
    }
}

if (!function_exists('getInvoicePrintSaleInvoiceColumnKeys')) {
    /** @return string[] */
    function getInvoicePrintSaleInvoiceColumnKeys() {
        return array_keys(getInvoicePrintSaleInvoiceColumnCatalog());
    }
}

if (!function_exists('getInvoicePrintSaleInvoiceNumericColumns')) {
    /** @return string[] */
    function getInvoicePrintSaleInvoiceNumericColumns() {
        return [
            'gross_weight', 'less_weight', 'net_weight', 'final_weight', 'pure_weight', 'purity_weight',
            'alloy_wt', 'pkt_wt', 'pkt_less_wt', 'stone_weight', 'diamond_carat', 'quantity', 'metal_qty',
            'metal_weight', 'gold_loss1', 'gold_loss2', 'metal_loss_value', 'wastage_per', 'wastage_wt',
            'rate', 'metal_rate', 'purity', 'purity_karat', 'carat', 'making_charge', 'making_rate',
            'making_amount', 'making_cost', 'making_discount_amt', 'making_actual_value',
            'diamond_amount', 'stone_amount', 'stone_rate', 'stone_cost', 'metal_value', 'metal_cost',
            'discount', 'discount_per', 'discount_amount', 'amount', 'purchase_amount', 'sale_amount',
            'sale_amount_with', 'sale_percent', 'net_amt', 'tax', 'tax_percent', 'net_amt_with_tax',
            'other_amount', 'other_weight', 'other_rate', 'hallmark_amount', 'hallmark_rate',
            'setting_charge', 'fc_amount', 'diamond_line_metal_value', 'rapnet_valuation',
            'mark_up_amount', 'mark_up_per', 'platinum_weight', 'platinum_purity_wt', 'platinum_rate',
            'platinum_wastage_per', 'platinum_wastage_wt', 'platinum_amount', 'certificate_amount',
            'unit_price', 'min_price',
        ];
    }
}

if (!function_exists('formatInvoicePrintSaleItemColumnValue')) {
    function formatInvoicePrintSaleItemColumnValue($col_key, $raw) {
        if ($raw === null || $raw === '') {
            return '';
        }
        if (!is_numeric($raw)) {
            return (string) $raw;
        }
        $n = (float) $raw;
        $weight_cols = [
            'gross_weight', 'less_weight', 'net_weight', 'final_weight', 'pure_weight', 'purity_weight',
            'alloy_wt', 'pkt_wt', 'pkt_less_wt', 'stone_weight', 'diamond_carat', 'metal_weight',
            'gold_loss1', 'gold_loss2', 'metal_loss_value', 'wastage_wt', 'platinum_weight', 'platinum_purity_wt',
            'platinum_wastage_wt', 'other_weight',
        ];
        $qty_cols = ['quantity', 'metal_qty'];
        $pct_cols = ['purity', 'purity_karat', 'wastage_per', 'discount_per', 'tax_percent', 'sale_percent',
            'mark_up_per', 'platinum_wastage_per', 'platinum_purity'];
        if (in_array($col_key, $weight_cols, true)) {
            return number_format($n, 3, '.', '');
        }
        if (in_array($col_key, $qty_cols, true)) {
            if (abs($n - round($n)) < 0.0001) {
                return (string) (int) round($n);
            }
            return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
        }
        if (in_array($col_key, $pct_cols, true)) {
            return rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
        }
        return number_format($n, 2, '.', '');
    }
}

if (!function_exists('auragold_resolve_invoice_print_short_code')) {
    /**
     * Resolve Short Code for invoice print: line fields → barcode/stock → product → customer account.
     *
     * @param array $item
     * @param array $context  Optional: customer_identity_no
     */
    function auragold_resolve_invoice_print_short_code(array $item, array $context = []) {
        foreach (['short_code', 'item_code', 'sj_item_code', 'sku_code'] as $k) {
            if (!empty($item[$k]) && trim((string) $item[$k]) !== '') {
                return trim((string) $item[$k]);
            }
        }
        $barcode = trim((string) ($item['barcode'] ?? ''));
        if ($barcode !== '' && function_exists('getBarcodePrintData')) {
            try {
                $bd = getBarcodePrintData($barcode);
            } catch (Throwable $e) {
                $bd = [];
            }
            if (is_array($bd)) {
                foreach (['short_code', 'ShortCode', 'item_code', 'ItemCode', 'sku_code'] as $bk) {
                    if (!empty($bd[$bk]) && trim((string) $bd[$bk]) !== '') {
                        return trim((string) $bd[$bk]);
                    }
                }
            }
        }
        foreach (['article', 'product_short_code', 'category_short_code', 'vendor_identity_no', 'supplier_identity_no'] as $pk) {
            if (!empty($item[$pk]) && trim((string) $item[$pk]) !== '') {
                return trim((string) $item[$pk]);
            }
        }
        $custIdentity = trim((string) ($context['customer_identity_no'] ?? ''));
        if ($custIdentity !== '') {
            return $custIdentity;
        }
        return '';
    }
}

if (!function_exists('buildInvoicePrintItemRowFromSaleItem')) {
    /**
     * Build one printable item row (all catalog keys) from a sale invoice item array.
     *
     * @param array $item
     * @param int   $sr
     * @param array $context  Optional print context (e.g. customer_identity_no)
     * @return array<string, string>
     */
    function buildInvoicePrintItemRowFromSaleItem(array $item, $sr = 1, array $context = []) {
        $pname = !empty($item['product_name']) ? $item['product_name'] : ('Product #' . ($item['product_id'] ?? ''));
        $design_no = !empty($item['design_no']) ? $item['design_no'] : (!empty($item['barcode']) ? $item['barcode'] : '');
        $gross = (float) ($item['gross_weight'] ?? $item['gross_wt'] ?? 0);
        $less_wt = (float) ($item['less_weight'] ?? $item['less_wt'] ?? 0);
        $stone_wt = (float) ($item['stone_weight'] ?? 0);
        $net_wt = (float) ($item['net_weight'] ?? $item['final_weight'] ?? $item['final_wt'] ?? 0);
        $final_wt = (float) ($item['final_weight'] ?? $item['final_wt'] ?? $net_wt);
        $rate = (float) ($item['rate'] ?? 0);
        $making_amt = (float) ($item['making_amount'] ?? $item['making'] ?? 0);
        $diamond_amt = (float) ($item['diamond_amount'] ?? $item['diamond_value'] ?? 0);
        $stone_amt = (float) ($item['stone_amount'] ?? $item['stone_charges'] ?? 0);
        $item_discount = (float) ($item['discount'] ?? 0);
        $net_amt = (float) ($item['net_amount'] ?? $item['amount'] ?? 0);
        $tax_item = (float) ($item['tax_amount'] ?? $item['tax'] ?? 0);
        $total_item = (float) ($item['net_amt_with_tax'] ?? ($net_amt + $tax_item));
        $sale_amount_val = (float) ($item['sale_amount'] ?? 0);
        if ($sale_amount_val <= 0) {
            $sale_amount_val = $net_amt;
        }
        $sale_amount_with_val = (float) ($item['sale_amount_with'] ?? 0);
        if ($sale_amount_with_val <= 0) {
            $sale_amount_with_val = $total_item;
        }
        $purity_raw = $item['purity'] ?? $item['carat'] ?? '';
        $purity_karat = $purity_raw;
        if (is_numeric($purity_karat)) {
            $purity_karat = number_format((float) $purity_karat, 2);
        }
        $category_name = $item['category_name'] ?? $item['diamond_category'] ?? $item['category'] ?? '';
        $huid = $item['huid_no'] ?? $item['huid'] ?? $item['barcode'] ?? '';
        $resolved_short_code = auragold_resolve_invoice_print_short_code($item, $context);

        $raw = [
            'sr_no' => $sr,
            'item_name' => $pname,
            'design_no' => $design_no,
            'huid' => $huid,
            'category' => $category_name,
            'barcode' => $item['barcode'] ?? '',
            'rfid' => $item['rfid_code'] ?? $item['rfid'] ?? '',
            'voucher_type' => $item['voucher_type'] ?? $item['style'] ?? '',
            'item_code' => $item['item_code'] ?? ($resolved_short_code !== '' ? $resolved_short_code : ''),
            'short_code' => $resolved_short_code,
            'location' => $item['location'] ?? '',
            'calculation' => $item['calculation_type'] ?? $item['calculation'] ?? '',
            'product_category' => $item['product_category'] ?? '',
            'gross_weight' => $gross,
            'less_weight' => $less_wt,
            'stone_weight' => $stone_wt,
            'diamond_carat' => $item['diamond_carat'] ?? $stone_wt,
            'net_weight' => $net_wt,
            'final_weight' => $final_wt,
            'pure_weight' => $item['pure_weight'] ?? $item['pure_wt'] ?? $item['purity_weight'] ?? 0,
            'purity_weight' => $item['purity_weight'] ?? $item['pure_weight'] ?? $item['pure_wt'] ?? 0,
            'alloy_wt' => $item['alloy_wt'] ?? 0,
            'pkt_wt' => $item['pkt_wt'] ?? 0,
            'pkt_less_wt' => $item['pkt_less_wt'] ?? 0,
            'quantity' => $item['quantity'] ?? 1,
            'metal_qty' => $item['metal_qty'] ?? $item['quantity'] ?? 1,
            'metal_weight' => $item['metal_weight'] ?? 0,
            'carat' => $item['carat'] ?? '',
            'purity' => $item['purity'] ?? '',
            'purity_karat' => $purity_karat,
            'rate' => $rate,
            'metal_rate' => $item['metal_rate'] ?? $rate,
            'metal_value' => $item['metal_value'] ?? 0,
            'metal_cost' => $item['metal_cost'] ?? 0,
            'gold_loss1' => $item['gold_loss1'] ?? $item['gold_loss_1'] ?? 0,
            'gold_loss2' => $item['gold_loss2'] ?? $item['gold_loss_2'] ?? 0,
            'metal_loss_value' => $item['metal_loss_value'] ?? 0,
            'wastage_per' => $item['wastage_per'] ?? 0,
            'wastage_wt' => $item['wastage_wt'] ?? 0,
            'requested_purity' => $item['requested_purity'] ?? '',
            'requested' => $item['requested'] ?? '',
            'setting_charge' => $item['setting_charge'] ?? 0,
            'making_charge' => $making_amt,
            'making_amount' => $making_amt,
            'making_rate' => $item['making_rate'] ?? 0,
            'making_cost' => $item['making_cost'] ?? 0,
            'making_discount_amt' => $item['making_discount_amt'] ?? 0,
            'making_actual_value' => $item['making_actual_value'] ?? 0,
            'diamond_amount' => $diamond_amt,
            'stone_amount' => $stone_amt,
            'stone_rate' => $item['stone_rate'] ?? 0,
            'stone_cost' => $item['stone_cost'] ?? 0,
            'stone_charge_type' => $item['stone_charge_type'] ?? '',
            'discount' => $item_discount,
            'discount_type' => $item['discount_type'] ?? '',
            'discount_per' => $item['discount_per'] ?? 0,
            'discount_amount' => $item['discount_amount'] ?? 0,
            'amount' => $total_item,
            'purchase_amount' => $item['purchase_amount'] ?? 0,
            'sale_amount' => $sale_amount_val,
            'sale_amount_with' => $sale_amount_with_val,
            'sale_percent' => $item['sale_percent'] ?? 0,
            'net_amt' => $net_amt,
            'tax_type' => $item['tax_type'] ?? '',
            'tax_percent' => $item['tax_percent'] ?? 0,
            'tax' => $tax_item,
            'net_amt_with_tax' => $total_item,
            'other_charge_type' => $item['other_charge_type'] ?? '',
            'other_weight' => $item['other_weight'] ?? 0,
            'other_rate' => $item['other_rate'] ?? 0,
            'other_info' => $item['other_info'] ?? '',
            'other_amount' => $item['other_amount'] ?? 0,
            'hallmark_amount' => $item['hallmark_amount'] ?? 0,
            'hallmark_rate' => $item['hallmark_rate'] ?? 0,
            'min_price' => $item['min_price'] ?? $item['minimum_price'] ?? 0,
            'minimum' => $item['minimum'] ?? $item['minimum_code'] ?? '',
            'minimum_code' => $item['minimum_code'] ?? '',
            'fc_amount' => $item['fc_amount'] ?? 0,
            'diamond_line_metal_value' => $item['diamond_line_metal_value'] ?? 0,
            'rapnet_valuation' => $item['rapnet_valuation'] ?? 0,
            'mark_up_amount' => $item['mark_up_amount'] ?? 0,
            'mark_up_per' => $item['mark_up_per'] ?? 0,
            'platinum_weight' => $item['platinum_weight'] ?? 0,
            'platinum_karat' => $item['platinum_karat'] ?? '',
            'platinum_purity' => $item['platinum_purity'] ?? '',
            'platinum_purity_wt' => $item['platinum_purity_wt'] ?? 0,
            'platinum_rate' => $item['platinum_rate'] ?? 0,
            'platinum_wastage_per' => $item['platinum_wastage_per'] ?? 0,
            'platinum_wastage_wt' => $item['platinum_wastage_wt'] ?? 0,
            'platinum_amount' => $item['platinum_amount'] ?? 0,
        ];

        global $conn;
        $extra_field_vals = function_exists('auragold_invoice_print_extra_field_values_from_item')
            ? auragold_invoice_print_extra_field_values_from_item($item, isset($conn) ? $conn : null)
            : [];
        $raw = array_merge($raw, $extra_field_vals);

        $row = [];
        foreach (array_keys(getInvoicePrintSaleInvoiceColumnCatalog()) as $col_key) {
            if ($col_key === 'sr_no') {
                $row[$col_key] = (string) (int) $sr;
                continue;
            }
            if (!array_key_exists($col_key, $raw)) {
                $row[$col_key] = '';
                continue;
            }
            $row[$col_key] = formatInvoicePrintSaleItemColumnValue($col_key, $raw[$col_key]);
        }
        foreach ($extra_field_vals as $ef_key => $ef_val) {
            $row[$ef_key] = (string) $ef_val;
        }
        return $row;
    }
}
