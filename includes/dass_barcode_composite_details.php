<?php

/**
 * Composite purchase-line details for a Diamond & Stones stock barcode
 * (Jewellery + Diamonds + GemStones lines added together on purchase invoice).
 */

require_once __DIR__ . '/diamond_barcode_cursor.php';
require_once __DIR__ . '/dass_purchase_invoice_stock_sql.php';

if (!function_exists('dass_detail_normalize_category')) {
    function dass_detail_normalize_category(?string $cat): string
    {
        $c = trim((string) $cat);
        if ($c === '') {
            return 'Other';
        }
        $lc = strtolower($c);
        if (strpos($lc, 'jewel') !== false) {
            return 'Jewellery';
        }
        if (strpos($lc, 'gem') !== false) {
            return 'GemStones';
        }
        if (strpos($lc, 'diamond') !== false) {
            return 'Diamonds';
        }
        return $c;
    }
}

if (!function_exists('dass_detail_category_sort_key')) {
    function dass_detail_category_sort_key(string $cat): int
    {
        static $order = ['Jewellery' => 1, 'Diamonds' => 2, 'GemStones' => 3];
        return $order[$cat] ?? 99;
    }
}

if (!function_exists('dass_detail_fmt_value')) {
    function dass_detail_fmt_value(string $key, $val): string
    {
        if ($val === null || $val === '') {
            return '';
        }
        $money = [
            'purchase_amount', 'sale_amount', 'net_amount', 'net_amt_with_tax', 'making_amount',
            'stone_amount', 'diamond_amount', 'tax', 'amount', 'metal_value', 'rate', 'metal_rate',
            'making_rate', 'stone_rate', 'min_price', 'setting_charge',
        ];
        $wt = [
            'gross_weight', 'less_weight', 'net_weight', 'final_weight', 'stone_weight',
            'purity_weight', 'wastage_wt', 'metal_weight', 'gold_loss_wt',
        ];
        if (in_array($key, $money, true) && is_numeric($val)) {
            return number_format((float) $val, 2, '.', '');
        }
        if (in_array($key, $wt, true) && is_numeric($val)) {
            return number_format((float) $val, 3, '.', '');
        }
        if ($key === 'purity' && is_numeric($val)) {
            return number_format((float) $val, 2, '.', '');
        }
        if ($key === 'quantity' && is_numeric($val)) {
            return number_format((float) $val, 2, '.', '');
        }
        return trim((string) $val);
    }
}

if (!function_exists('dass_detail_field_defs')) {
    /** @return array<string, string> */
    function dass_detail_field_defs(): array
    {
        return [
            'diamond_category' => 'Diamond Category',
            'product_name' => 'Product Name',
            'barcode' => 'Barcode',
            'design_no' => 'Design No',
            'huid' => 'HUID No',
            'calculation_type' => 'Calculation',
            'gross_weight' => 'Gross Wt',
            'less_weight' => 'Less Wt',
            'net_weight' => 'Net Wt',
            'final_weight' => 'Final Wt',
            'purity' => 'Purity',
            'purity_weight' => 'Purity Wt',
            'carat' => 'Carat',
            'quantity' => 'Qty',
            'rate' => 'Rate',
            'metal_rate' => 'Metal Rate',
            'purchase_amount' => 'Purchase Amount',
            'sale_amount' => 'Sale Amount',
            'net_amount' => 'Net Amount',
            'net_amt_with_tax' => 'Net Amt + Tax',
            'making_amount' => 'Making Amount',
            'making_rate' => 'Making Rate',
            'stone_weight' => 'Stone Wt',
            'stone_rate' => 'Stone Rate',
            'stone_amount' => 'Stone Amount',
            'diamond_amount' => 'Diamond Amount',
            'tax' => 'Tax',
            'wastage_wt' => 'Wastage Wt',
            'wastage_per' => 'Wastage %',
            'gold_loss_wt' => 'Gold Loss Wt',
            'setting_charge' => 'Setting Charge',
            'min_price' => 'Minimum Price',
            'location_name' => 'Location',
            'invoice_no' => 'Invoice No',
            'invoice_date' => 'Invoice Date',
            'supplier_name' => 'Supplier',
        ];
    }
}

if (!function_exists('dass_pii_has_merge_group_index_column')) {
    function dass_pii_has_merge_group_index_column(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        global $conn;
        $cached = false;
        if (!($conn instanceof mysqli)) {
            return false;
        }
        $r = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_invoice_items LIKE 'merge_group_index'");
        if ($r && mysqli_num_rows($r) > 0) {
            $cached = true;
        }
        if ($r) {
            mysqli_free_result($r);
        }
        return $cached;
    }
}

if (!function_exists('dass_fetch_purchase_lines_for_barcode')) {
    /**
     * @return list<array<string, mixed>>
     */
    function dass_fetch_purchase_lines_for_barcode(mysqli $conn, string $barcode): array
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            return [];
        }
        $bc_esc = mysqli_real_escape_string($conn, $barcode);
        $match = auragold_pii_sql_barcode_or_tag_match('pii', $bc_esc);

        $anchor = getRecord("
            SELECT pii.id, pii.invoice_id, pii.merge_group_index, pii.barcode_no
            FROM tbl_purchase_invoice_items pii
            WHERE pii.status = 1 AND $match
            ORDER BY pii.id DESC
            LIMIT 1
        ");
        if (!$anchor) {
            return [];
        }

        $invoice_id = (int) ($anchor['invoice_id'] ?? 0);
        if ($invoice_id <= 0) {
            return [];
        }

        $loc_join = dass_pi_tbl_exists($conn, 'tbl_locations')
            ? 'LEFT JOIN tbl_locations loc ON loc.id = pii.location_id'
            : '';
        $loc_select = dass_pi_tbl_exists($conn, 'tbl_locations') ? 'loc.name AS location_name' : "'' AS location_name";

        $items = [];
        $has_mgi = dass_pii_has_merge_group_index_column();
        $mgi = isset($anchor['merge_group_index']) ? $anchor['merge_group_index'] : null;

        if ($has_mgi && $mgi !== null && $mgi !== '') {
            $mg = (int) $mgi;
            $items = getList("
                SELECT pii.*,
                       COALESCE(NULLIF(TRIM(p.name), ''), NULLIF(TRIM(pii.product_name), ''), '') AS product_display_name,
                       pi.invoice_no, pi.invoice_date, pi.supplier_name,
                       $loc_select
                FROM tbl_purchase_invoice_items pii
                LEFT JOIN tbl_products p ON p.id = pii.product_id
                LEFT JOIN tbl_purchase_invoices pi ON pi.id = pii.invoice_id
                $loc_join
                WHERE pii.invoice_id = $invoice_id
                AND pii.status = 1
                AND pii.merge_group_index = $mg
                ORDER BY pii.id ASC
            ");
        }

        if (!is_array($items) || count($items) < 2) {
            $items = getList("
                SELECT pii.*,
                       COALESCE(NULLIF(TRIM(p.name), ''), NULLIF(TRIM(pii.product_name), ''), '') AS product_display_name,
                       pi.invoice_no, pi.invoice_date, pi.supplier_name,
                       $loc_select
                FROM tbl_purchase_invoice_items pii
                LEFT JOIN tbl_products p ON p.id = pii.product_id
                LEFT JOIN tbl_purchase_invoices pi ON pi.id = pii.invoice_id
                $loc_join
                WHERE pii.invoice_id = $invoice_id
                AND pii.status = 1
                AND $match
                ORDER BY pii.id ASC
            ");
        }

        if ((!is_array($items) || count($items) < 2) && auragold_pii_has_barcode_no_column()) {
            $tag = trim((string) ($anchor['barcode_no'] ?? ''));
            if ($tag === '') {
                $tag = $barcode;
            }
            $tag_esc = mysqli_real_escape_string($conn, $tag);
            $tag_match = auragold_pii_sql_barcode_or_tag_match('pii', $tag_esc);
            $items = getList("
                SELECT pii.*,
                       COALESCE(NULLIF(TRIM(p.name), ''), NULLIF(TRIM(pii.product_name), ''), '') AS product_display_name,
                       pi.invoice_no, pi.invoice_date, pi.supplier_name,
                       $loc_select
                FROM tbl_purchase_invoice_items pii
                LEFT JOIN tbl_products p ON p.id = pii.product_id
                LEFT JOIN tbl_purchase_invoices pi ON pi.id = pii.invoice_id
                $loc_join
                WHERE pii.invoice_id = $invoice_id
                AND pii.status = 1
                AND $tag_match
                ORDER BY pii.id ASC
            ");
        }

        return is_array($items) ? $items : [];
    }
}

if (!function_exists('dass_map_pii_row_to_detail_fields')) {
    /**
     * @param array<string, mixed> $row
     * @return list<array{key: string, label: string, value: string}>
     */
    function dass_map_pii_row_to_detail_fields(array $row): array
    {
        $defs = dass_detail_field_defs();
        $mapped = $row;
        if (empty($mapped['product_name']) && !empty($row['product_display_name'])) {
            $mapped['product_name'] = $row['product_display_name'];
        }
        if (empty($mapped['diamond_category']) && !empty($row['category_id'])) {
            $mapped['diamond_category'] = '';
        }
        $out = [];
        foreach ($defs as $key => $label) {
            $raw = $mapped[$key] ?? null;
            $val = dass_detail_fmt_value($key, $raw);
            if ($val === '') {
                continue;
            }
            $out[] = ['key' => $key, 'label' => $label, 'value' => $val];
        }
        return $out;
    }
}

if (!function_exists('dass_journal_image_urls_for_barcode')) {
    /**
     * @return list<string> public URLs
     */
    function dass_journal_image_urls_for_barcode(mysqli $conn, string $barcode): array
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            return [];
        }
        $chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_stock_journal_images'");
        if (!$chk || mysqli_num_rows($chk) === 0) {
            if ($chk) {
                mysqli_free_result($chk);
            }
            return [];
        }
        mysqli_free_result($chk);
        $esc = mysqli_real_escape_string($conn, $barcode);
        $rows = getList("
            SELECT image_path FROM tbl_stock_journal_images
            WHERE TRIM(barcode_no) = TRIM('$esc')
            ORDER BY id ASC
        ");
        $urls = [];
        foreach ($rows ?: [] as $r) {
            $p = trim((string) ($r['image_path'] ?? ''));
            if ($p === '') {
                continue;
            }
            $urls[] = function_exists('auragold_uploads_public_url') ? auragold_uploads_public_url($p) : $p;
        }
        return $urls;
    }
}

if (!function_exists('dass_fetch_stock_fallback_detail')) {
    /**
     * @return list<array<string, mixed>>
     */
    function dass_fetch_stock_fallback_detail(mysqli $conn, string $barcode): array
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            return [];
        }
        $esc = mysqli_real_escape_string($conn, $barcode);
        $row = getRecord("
            SELECT s.barcode, s.final_weight, s.opening_weight, s.opening_purity, s.rate, s.value,
                   s.current_qty, p.name AS product_name, p.description AS product_description,
                   pc.diamond_category, pc.carat, pc.cut, pc.clarity, pc.color, pc.shape, pc.size,
                   b.name AS branch_name
            FROM tbl_stock s
            LEFT JOIN tbl_products p ON p.id = s.product_id
            LEFT JOIN tbl_product_characteristics pc ON pc.id = s.product_characteristic_id
            LEFT JOIN tbl_branches b ON b.id = s.branch_id
            WHERE TRIM(s.barcode) = TRIM('$esc') AND s.status = 1
            ORDER BY s.id DESC
            LIMIT 1
        ");
        if (!$row) {
            return [];
        }
        $fields = [];
        $pairs = [
            'product_name' => 'Product Name',
            'barcode' => 'Barcode',
            'diamond_category' => 'Category',
            'carat' => 'Carat',
            'cut' => 'Cut',
            'clarity' => 'Clarity',
            'color' => 'Color',
            'shape' => 'Shape',
            'size' => 'Size',
            'final_weight' => 'Final Wt',
            'opening_weight' => 'Gross Wt',
            'opening_purity' => 'Purity',
            'rate' => 'Rate',
            'value' => 'Amount',
            'current_qty' => 'Qty',
            'branch_name' => 'Branch',
            'product_description' => 'Description',
        ];
        foreach ($pairs as $k => $label) {
            $v = dass_detail_fmt_value($k, $row[$k] ?? null);
            if ($v !== '') {
                $fields[] = ['key' => $k, 'label' => $label, 'value' => $v];
            }
        }
        $cat = dass_detail_normalize_category($row['diamond_category'] ?? 'Other');
        return [[
            'category' => $cat,
            'line_index' => 1,
            'fields' => $fields,
        ]];
    }
}

if (!function_exists('dass_build_barcode_composite_details_payload')) {
    /**
     * @return array<string, mixed>
     */
    function dass_build_barcode_composite_details_payload(mysqli $conn, string $barcode): array
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            return ['status' => 'error', 'message' => 'Barcode is required'];
        }

        $lines = dass_fetch_purchase_lines_for_barcode($conn, $barcode);
        $sections = [];
        $section_map = [];

        if (!empty($lines)) {
            foreach ($lines as $i => $line) {
                $cat_raw = trim((string) ($line['diamond_category'] ?? ''));
                $cat = dass_detail_normalize_category($cat_raw);
                $fields = dass_map_pii_row_to_detail_fields($line);
                if (empty($fields)) {
                    continue;
                }
                if (!isset($section_map[$cat])) {
                    $section_map[$cat] = count($sections);
                    $sections[] = [
                        'category' => $cat,
                        'lines' => [],
                    ];
                }
                $sections[$section_map[$cat]]['lines'][] = [
                    'line_index' => $i + 1,
                    'purchase_item_id' => (int) ($line['id'] ?? 0),
                    'fields' => $fields,
                ];
            }
        }

        if (empty($sections)) {
            $fallback = dass_fetch_stock_fallback_detail($conn, $barcode);
            foreach ($fallback as $fb) {
                $sections[] = [
                    'category' => $fb['category'],
                    'lines' => [[
                        'line_index' => 1,
                        'fields' => $fb['fields'],
                    ]],
                ];
            }
        }

        usort($sections, static function ($a, $b) {
            return dass_detail_category_sort_key((string) ($a['category'] ?? ''))
                <=> dass_detail_category_sort_key((string) ($b['category'] ?? ''));
        });

        $invoice_no = '';
        $invoice_date = '';
        $supplier_name = '';
        if (!empty($lines[0])) {
            $invoice_no = trim((string) ($lines[0]['invoice_no'] ?? ''));
            $invoice_date = trim((string) ($lines[0]['invoice_date'] ?? ''));
            $supplier_name = trim((string) ($lines[0]['supplier_name'] ?? ''));
        }

        return [
            'status' => 'success',
            'barcode' => $barcode,
            'invoice_no' => $invoice_no,
            'invoice_date' => $invoice_date,
            'supplier_name' => $supplier_name,
            'image_urls' => dass_journal_image_urls_for_barcode($conn, $barcode),
            'sections' => $sections,
        ];
    }
}
