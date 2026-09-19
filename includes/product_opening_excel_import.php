<?php
/**
 * Bulk Excel import helpers for product-opening.php (product master + characteristics).
 */

if (!function_exists('auragold_po_excel_normalize_header')) {
    function auragold_po_excel_normalize_header(string $h): string
    {
        $h = strtolower(trim($h));
        $h = preg_replace('/\s+/', '_', $h);
        $h = preg_replace('/[^a-z0-9_]/', '', $h);

        return $h;
    }
}

if (!function_exists('auragold_po_excel_map_headers')) {
    /**
     * @param array<int,string> $headers 1-based column index => header label
     * @return array<string,int>
     */
    function auragold_po_excel_map_headers(array $headers): array
    {
        $aliases = [
            'product_name' => ['product_name', 'product', 'name', 'item_name', 'item'],
            'article' => ['article', 'art_no', 'artno'],
            'alternate_name' => ['alternate_name', 'alt_name', 'alternate'],
            'category' => ['category', 'product_category', 'category_name'],
            'show_in_stock' => ['show_in_stock', 'stock_item', 'is_stock_item', 'showinstock'],
            'metal' => ['metal', 'metal_name', 'metal_type'],
            'hsn' => ['hsn', 'hsn_code'],
            'unit' => ['unit', 'unit_name'],
            'sku_code' => ['sku_code', 'sku', 'item_code'],
            'making_on' => ['making_on', 'making'],
            'diamond_category' => ['diamond_category', 'diamond_cat'],
            'location' => ['location', 'location_name'],
            'karat' => ['karat', 'carat', 'kt'],
            'discount' => ['discount'],
            'purity_sale' => ['purity_sale', 'purity'],
            'purity_purchase' => ['purity_purchase'],
            'wastage_sale' => ['wastage_sale', 'wastage'],
            'wastage_purchase' => ['wastage_purchase'],
            'wt_per_piece' => ['wt_per_piece', 'weight_per_piece', 'wt_piece'],
            'opening_weight' => ['opening_weight', 'opening_wt', 'gross_weight', 'gross_wt', 'weight'],
            'opening_purity' => ['opening_purity', 'opening_purity_pct'],
            'opening_qty' => ['opening_qty', 'opening_quantity', 'qty', 'quantity', 'metal_qty'],
            'rate' => ['rate', 'metal_rate'],
            'barcode_prefix' => ['barcode_prefix', 'prefix'],
            'barcode_digits' => ['barcode_digits', 'digits'],
            'barcode' => ['barcode', 'barcode_no'],
            'serialized_barcode' => ['serialized_barcode', 'serialized'],
            'cut' => ['cut'],
            'shape' => ['shape'],
            'color' => ['color'],
            'clarity' => ['clarity'],
            'sieve' => ['sieve'],
            'size' => ['size'],
            'style_code' => ['style_code', 'style', 'design_no'],
        ];
        $map = [];
        foreach ($headers as $colIdx => $raw) {
            $n = auragold_po_excel_normalize_header((string) $raw);
            if ($n === '') {
                continue;
            }
            foreach ($aliases as $field => $list) {
                if (in_array($n, $list, true) && !isset($map[$field])) {
                    $map[$field] = (int) $colIdx;
                    break;
                }
            }
        }

        return $map;
    }
}

if (!function_exists('auragold_po_excel_cell_s')) {
    function auragold_po_excel_cell_s(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $col, int $row): string
    {
        if ($col < 1) {
            return '';
        }
        $addr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $row;
        $v = $sheet->getCell($addr)->getValue();
        if ($v instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText) {
            $v = $v->__toString();
        }

        return trim(preg_replace('/\s+/', ' ', (string) $v));
    }
}

if (!function_exists('auragold_po_excel_cell_f')) {
    function auragold_po_excel_cell_f(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $col, int $row): float
    {
        if ($col < 1) {
            return 0.0;
        }
        $addr = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . $row;
        try {
            $v = $sheet->getCell($addr)->getCalculatedValue();
        } catch (Throwable $e) {
            $v = $sheet->getCell($addr)->getValue();
        }
        if ($v === null || $v === '') {
            return 0.0;
        }
        if (is_string($v)) {
            $v = str_replace([',', ' '], ['', ''], $v);
        }
        if (!is_numeric($v)) {
            return 0.0;
        }
        $f = (float) $v;

        return (is_nan($f) || is_infinite($f)) ? 0.0 : $f;
    }
}

if (!function_exists('auragold_po_excel_yes_no')) {
    function auragold_po_excel_yes_no(string $v, bool $default = false): bool
    {
        $v = strtolower(trim($v));
        if ($v === '') {
            return $default;
        }
        if (in_array($v, ['1', 'yes', 'y', 'true', 'on', 'checked'], true)) {
            return true;
        }
        if (in_array($v, ['0', 'no', 'n', 'false', 'off'], true)) {
            return false;
        }

        return $default;
    }
}

if (!function_exists('auragold_po_excel_resolve_category_id')) {
    function auragold_po_excel_resolve_category_id(mysqli $conn, string $label): int
    {
        $label = trim($label);
        if ($label === '') {
            return 0;
        }
        if (ctype_digit($label)) {
            return (int) $label;
        }
        $esc = esc($label);
        $row = getRecord("SELECT id FROM tbl_categories WHERE status = 1 AND TRIM(name) = TRIM('$esc') LIMIT 1");
        if ($row) {
            return (int) ($row['id'] ?? 0);
        }
        $row = getRecord("SELECT id FROM tbl_categories WHERE status = 1 AND TRIM(name) LIKE CONCAT(TRIM('$esc'), '%') ORDER BY CHAR_LENGTH(name) ASC LIMIT 1");

        return $row ? (int) ($row['id'] ?? 0) : 0;
    }
}

if (!function_exists('auragold_po_excel_resolve_metal_name')) {
    function auragold_po_excel_resolve_metal_name(mysqli $conn, string $label): string
    {
        $label = trim($label);
        if ($label === '') {
            return '';
        }
        $esc = esc($label);
        $row = getRecord("SELECT display_name FROM tbl_metal WHERE status = 1 AND TRIM(display_name) = TRIM('$esc') LIMIT 1");
        if ($row && trim((string) ($row['display_name'] ?? '')) !== '') {
            return trim((string) $row['display_name']);
        }
        $row = getRecord("SELECT display_name FROM tbl_metal WHERE status = 1 AND LOWER(TRIM(display_name)) LIKE CONCAT(LOWER(TRIM('$esc')), '%') ORDER BY CHAR_LENGTH(display_name) ASC LIMIT 1");

        return ($row && trim((string) ($row['display_name'] ?? '')) !== '') ? trim((string) $row['display_name']) : '';
    }
}

if (!function_exists('auragold_po_excel_resolve_unit_id')) {
    function auragold_po_excel_resolve_unit_id(mysqli $conn, string $label): int
    {
        $label = trim($label);
        if ($label === '') {
            return 0;
        }
        if (ctype_digit($label)) {
            return (int) $label;
        }
        $esc = esc($label);
        $suffix = function_exists('auragold_master_list_sql_suffix') ? auragold_master_list_sql_suffix($conn, 'tbl_unit') : '';
        $row = getRecord("SELECT id FROM tbl_unit WHERE status = 1 AND TRIM(name) = TRIM('$esc') $suffix LIMIT 1");

        return $row ? (int) ($row['id'] ?? 0) : 0;
    }
}

if (!function_exists('auragold_po_excel_resolve_location_id')) {
    function auragold_po_excel_resolve_location_id(mysqli $conn, string $label): int
    {
        $label = trim($label);
        if ($label === '') {
            return 0;
        }
        if (ctype_digit($label)) {
            return (int) $label;
        }
        $esc = esc($label);
        $suffix = function_exists('auragold_master_list_sql_suffix') ? auragold_master_list_sql_suffix($conn, 'tbl_location') : '';
        $row = getRecord("SELECT id FROM tbl_location WHERE status = 1 AND TRIM(name) = TRIM('$esc') $suffix LIMIT 1");

        return $row ? (int) ($row['id'] ?? 0) : 0;
    }
}

if (!function_exists('auragold_po_excel_default_barcode_prefix')) {
    function auragold_po_excel_default_barcode_prefix(string $metalName): string
    {
        $n = trim($metalName);
        $defaults = [
            'Gold' => 'GD',
            'Silver' => 'SV',
            'Diamond & Stones' => 'DM',
        ];

        return $defaults[$n] ?? 'RN';
    }
}

if (!function_exists('auragold_po_excel_default_opening_purity')) {
    function auragold_po_excel_default_opening_purity(string $metalName): string
    {
        $n = trim($metalName);
        if (in_array($n, ['Gold', 'Silver', 'Platinum'], true)) {
            return '1';
        }

        return '0';
    }
}

if (!function_exists('auragold_po_excel_parse_rows')) {
    /**
     * @return array{groups: array<string,array>, skipped: array<int,array>, errors: array<int,string>}
     */
    function auragold_po_excel_parse_rows(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, mysqli $conn, int $highestRow, int $highestCol): array
    {
        $headers = [];
        for ($c = 1; $c <= $highestCol; $c++) {
            $headers[$c] = auragold_po_excel_cell_s($sheet, $c, 1);
        }
        $map = auragold_po_excel_map_headers($headers);
        if (empty($map['product_name'])) {
            throw new RuntimeException('Header row must include a Product Name column.');
        }
        if (empty($map['metal'])) {
            throw new RuntimeException('Header row must include a Metal column.');
        }

        $groups = [];
        $skipped = [];
        $errors = [];

        for ($r = 2; $r <= $highestRow; $r++) {
            $get = static function (string $key) use ($map, $sheet, $r): string {
                if (empty($map[$key])) {
                    return '';
                }

                return auragold_po_excel_cell_s($sheet, (int) $map[$key], $r);
            };

            $productName = $get('product_name');
            $metalRaw = $get('metal');
            if ($productName === '' && $metalRaw === '') {
                continue;
            }
            if ($productName === '') {
                $skipped[] = ['row' => $r, 'reason' => 'Product Name is empty'];
                continue;
            }
            if ($metalRaw === '') {
                $skipped[] = ['row' => $r, 'reason' => 'Metal is empty for product "' . $productName . '"'];
                continue;
            }
            $metalName = auragold_po_excel_resolve_metal_name($conn, $metalRaw);
            if ($metalName === '') {
                $skipped[] = ['row' => $r, 'reason' => 'Unknown metal "' . $metalRaw . '" on row ' . $r];
                continue;
            }

            $openingWeight = !empty($map['opening_weight']) ? auragold_po_excel_cell_f($sheet, (int) $map['opening_weight'], $r) : 0.0;
            $openingQty = !empty($map['opening_qty']) ? auragold_po_excel_cell_f($sheet, (int) $map['opening_qty'], $r) : 0.0;
            $openingPurityRaw = $get('opening_purity');
            $openingPurity = $openingPurityRaw !== '' ? $openingPurityRaw : auragold_po_excel_default_opening_purity($metalName);
            $rate = !empty($map['rate']) ? auragold_po_excel_cell_f($sheet, (int) $map['rate'], $r) : 0.0;
            $finalWt = $openingWeight > 0 ? (string) $openingWeight : '0';
            $value = ($openingWeight > 0 && $rate > 0) ? (string) round($openingWeight * $rate, 4) : '0';
            $barcodePrefix = $get('barcode_prefix');
            if ($barcodePrefix === '') {
                $barcodePrefix = auragold_po_excel_default_barcode_prefix($metalName);
            }
            $barcodeDigits = !empty($map['barcode_digits']) ? (int) auragold_po_excel_cell_f($sheet, (int) $map['barcode_digits'], $r) : 5;
            if ($barcodeDigits < 1) {
                $barcodeDigits = 5;
            }

            $metalRow = [
                'is_selected' => '1',
                'metal' => $metalName,
                'hsn' => $get('hsn') !== '' ? $get('hsn') : '7113',
                'unit_id' => auragold_po_excel_resolve_unit_id($conn, $get('unit')),
                'sku_code' => $get('sku_code'),
                'making_on' => $get('making_on') !== '' ? $get('making_on') : 'Gross Wt',
                'diamond_category' => $get('diamond_category'),
                'location_id' => auragold_po_excel_resolve_location_id($conn, $get('location')),
                'carat' => $get('karat'),
                'discount' => $get('discount') !== '' ? $get('discount') : '0',
                'purity_sale' => $get('purity_sale'),
                'wastage_sale' => $get('wastage_sale'),
                'wastage_purchase' => $get('wastage_purchase'),
                'wt_per_piece' => $get('wt_per_piece'),
                'opening_weight' => $openingWeight > 0 ? (string) $openingWeight : '0',
                'opening_purity' => $openingPurity,
                'opening_qty' => $openingQty > 0 ? (string) $openingQty : '0',
                'final_weight' => $finalWt,
                'rate' => $rate > 0 ? (string) $rate : '0',
                'value' => $value,
                'barcode_digits' => (string) $barcodeDigits,
                'barcode_prefix' => $barcodePrefix,
                'barcode' => $get('barcode'),
                'cut' => $get('cut'),
                'shape' => $get('shape'),
                'color' => $get('color'),
                'clarity' => $get('clarity'),
                'sieve' => $get('sieve'),
                'size' => $get('size'),
                'style_code' => $get('style_code'),
            ];
            if (auragold_po_excel_yes_no($get('purity_purchase'))) {
                $metalRow['purity_purchase'] = '1';
            }
            if (auragold_po_excel_yes_no($get('serialized_barcode'))) {
                $metalRow['serialized_barcode'] = '1';
            }

            if (!isset($groups[$productName])) {
                $groups[$productName] = [
                    'master' => [
                        'name' => $productName,
                        'article' => $get('article'),
                        'alternate_name' => $get('alternate_name'),
                        'category_id' => auragold_po_excel_resolve_category_id($conn, $get('category')),
                        'is_stock_item' => auragold_po_excel_yes_no($get('show_in_stock'), true),
                    ],
                    'metals' => [],
                    'source_rows' => [],
                ];
            } else {
                if ($get('article') !== '' && $groups[$productName]['master']['article'] === '') {
                    $groups[$productName]['master']['article'] = $get('article');
                }
                if ($get('alternate_name') !== '' && $groups[$productName]['master']['alternate_name'] === '') {
                    $groups[$productName]['master']['alternate_name'] = $get('alternate_name');
                }
                if ($get('category') !== '' && (int) ($groups[$productName]['master']['category_id'] ?? 0) === 0) {
                    $groups[$productName]['master']['category_id'] = auragold_po_excel_resolve_category_id($conn, $get('category'));
                }
            }

            $dupMetal = false;
            foreach ($groups[$productName]['metals'] as $existing) {
                if (($existing['metal'] ?? '') === $metalName) {
                    $dupMetal = true;
                    break;
                }
            }
            if ($dupMetal) {
                $errors[] = 'Row ' . $r . ': duplicate Metal "' . $metalName . '" for product "' . $productName . '" in Excel.';
                continue;
            }

            $groups[$productName]['metals'][] = $metalRow;
            $groups[$productName]['source_rows'][] = $r;
        }

        return ['groups' => $groups, 'skipped' => $skipped, 'errors' => $errors];
    }
}

if (!function_exists('auragold_po_excel_build_post')) {
    /**
     * @param array<string,mixed> $group
     * @param array<int,int> $branchIds
     * @return array<string,mixed>
     */
    function auragold_po_excel_build_post(array $group, array $branchIds): array
    {
        $master = $group['master'] ?? [];
        $post = [
            'name' => (string) ($master['name'] ?? ''),
            'alternate_name' => (string) ($master['alternate_name'] ?? ''),
            'article' => (string) ($master['article'] ?? ''),
            'category_id' => (int) ($master['category_id'] ?? 0),
            'branch_ids' => array_values(array_filter(array_map('intval', $branchIds))),
            'row' => [],
        ];
        if (!empty($master['is_stock_item'])) {
            $post['is_stock_item'] = '1';
        }
        $i = 0;
        foreach ($group['metals'] ?? [] as $metalRow) {
            $post['row'][$i] = $metalRow;
            $i++;
        }

        return $post;
    }
}

if (!function_exists('auragold_po_excel_import_groups')) {
    /**
     * @param array<string,array> $groups
     * @return array{imported:int,skipped:int,errors:array<int,string>,product_ids:array<int,int>}
     */
    function auragold_po_excel_import_groups(mysqli $conn, array $groups, array $branchIds): array
    {
        require_once __DIR__ . '/product_opening_save_core.php';

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $productIds = [];

        foreach ($groups as $productName => $group) {
            $nameEsc = esc((string) $productName);
            $existing = getRecord("SELECT id FROM tbl_products WHERE name = '$nameEsc' AND status = 1 LIMIT 1");
            if ($existing) {
                $skipped++;
                $errors[] = 'Skipped "' . $productName . '": product name already exists (ID ' . (int) ($existing['id'] ?? 0) . ').';
                continue;
            }
            if (empty($group['metals'])) {
                $skipped++;
                $errors[] = 'Skipped "' . $productName . '": no valid metal rows.';
                continue;
            }

            $post = auragold_po_excel_build_post($group, $branchIds);
            mysqli_begin_transaction($conn);
            try {
                $result = auragold_product_opening_save($conn, $post, []);
                mysqli_commit($conn);
                $imported++;
                $productIds[] = (int) ($result['product_id'] ?? 0);
            } catch (Throwable $e) {
                mysqli_rollback($conn);
                $skipped++;
                $errors[] = 'Failed "' . $productName . '": ' . $e->getMessage();
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'product_ids' => $productIds,
        ];
    }
}
