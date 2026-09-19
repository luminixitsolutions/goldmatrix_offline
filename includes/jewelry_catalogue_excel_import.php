<?php
/**
 * Bulk Excel import for jewellery catalogue (tbl_jewelry_catalogue).
 */

require_once __DIR__ . '/product_opening_excel_import.php';
require_once __DIR__ . '/jewelry_catalogue_create_include.php';

if (!function_exists('auragold_jcat_excel_map_headers')) {
    /**
     * @param array<int,string> $headers 1-based column index => header label
     * @return array<string,int>
     */
    function auragold_jcat_excel_map_headers(array $headers): array
    {
        $aliases = [
            'metal' => ['metal', 'metal_name', 'metal_type'],
            'product' => ['product', 'product_name', 'item', 'item_name'],
            'category' => ['category', 'category_name', 'product_category'],
            'title' => ['title', 'catalogue_title', 'design_title', 'name'],
            'short_desc' => ['short_desc', 'short_description', 'shortdescription', 'short_desc_'],
            'full_desc' => ['full_desc', 'full_description', 'description', 'long_desc', 'details'],
            'barcode' => ['barcode', 'barcode_no', 'rfid'],
            'design_no' => ['design_no', 'design_number', 'design', 'designno'],
            'sku' => ['sku', 'sku_code', 'item_code'],
            'weight' => ['weight', 'gross_weight', 'gross_wt', 'net_weight', 'net_wt'],
            'amount' => ['amount', 'price', 'value', 'rate', 'purchase_amount'],
            'image_url' => ['image_url', 'image', 'image_path', 'photo', 'picture'],
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

if (!function_exists('auragold_jcat_excel_resolve_metal_id')) {
    function auragold_jcat_excel_resolve_metal_id(mysqli $conn, string $label): int
    {
        $label = trim($label);
        if ($label === '') {
            return 0;
        }
        if (ctype_digit($label)) {
            $id = (int) $label;
            $row = getRecord('SELECT id FROM tbl_metal WHERE status = 1 AND id = ' . $id . ' LIMIT 1');

            return $row ? (int) ($row['id'] ?? 0) : 0;
        }
        $esc = esc($label);
        $row = getRecord(
            "SELECT id FROM tbl_metal WHERE status = 1
             AND (TRIM(display_name) = TRIM('$esc') OR TRIM(system_name) = TRIM('$esc'))
             LIMIT 1"
        );
        if ($row) {
            return (int) ($row['id'] ?? 0);
        }
        $row = getRecord(
            "SELECT id FROM tbl_metal WHERE status = 1
             AND (LOWER(TRIM(display_name)) LIKE CONCAT(LOWER(TRIM('$esc')), '%')
                  OR LOWER(TRIM(system_name)) LIKE CONCAT(LOWER(TRIM('$esc')), '%'))
             ORDER BY CHAR_LENGTH(display_name) ASC LIMIT 1"
        );

        return $row ? (int) ($row['id'] ?? 0) : 0;
    }
}

if (!function_exists('auragold_jcat_excel_resolve_product_id')) {
    function auragold_jcat_excel_resolve_product_id(mysqli $conn, string $label): int
    {
        $label = trim($label);
        if ($label === '') {
            return 0;
        }
        if (ctype_digit($label)) {
            $id = (int) $label;
            $row = getRecord('SELECT id FROM tbl_products WHERE status = 1 AND id = ' . $id . ' LIMIT 1');

            return $row ? (int) ($row['id'] ?? 0) : 0;
        }
        $esc = esc($label);
        $row = getRecord("SELECT id FROM tbl_products WHERE status = 1 AND TRIM(name) = TRIM('$esc') LIMIT 1");
        if ($row) {
            return (int) ($row['id'] ?? 0);
        }
        $row = getRecord(
            "SELECT id FROM tbl_products WHERE status = 1 AND LOWER(TRIM(name)) LIKE CONCAT(LOWER(TRIM('$esc')), '%')
             ORDER BY CHAR_LENGTH(name) ASC LIMIT 1"
        );

        return $row ? (int) ($row['id'] ?? 0) : 0;
    }
}

if (!function_exists('auragold_jcat_excel_cell_val')) {
    function auragold_jcat_excel_cell_val(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, array $map, string $field, int $row): string
    {
        if (empty($map[$field])) {
            return '';
        }

        return auragold_po_excel_cell_s($sheet, (int) $map[$field], $row);
    }
}

if (!function_exists('auragold_jcat_excel_parse_images')) {
    /**
     * @return list<array{url:string,path:string}>
     */
    function auragold_jcat_excel_parse_images(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        $parts = preg_split('/[,|;]+/', $raw) ?: [];
        $out = [];
        foreach ($parts as $part) {
            $url = trim((string) $part);
            if ($url === '') {
                continue;
            }
            $out[] = ['url' => $url, 'path' => ''];
        }

        return $out;
    }
}

if (!function_exists('auragold_jcat_excel_parse_rows')) {
    /**
     * @return array{rows: list<array<string,mixed>>, errors: list<string>}
     */
    function auragold_jcat_excel_parse_rows(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet,
        mysqli $conn,
        int $highestRow,
        int $highestCol
    ): array {
        $headers = [];
        for ($c = 1; $c <= $highestCol; $c++) {
            $headers[$c] = auragold_po_excel_cell_s($sheet, $c, 1);
        }
        $map = auragold_jcat_excel_map_headers($headers);
        if (empty($map['title']) && empty($map['product'])) {
            throw new RuntimeException('Header row must include Title or Product column.');
        }
        if (empty($map['weight'])) {
            throw new RuntimeException('Header row must include a Weight column.');
        }
        if (empty($map['amount'])) {
            throw new RuntimeException('Header row must include an Amount column.');
        }

        $rows = [];
        $errors = [];
        $maxRows = 5000;
        $dataRows = 0;

        for ($r = 2; $r <= $highestRow; $r++) {
            $metalRaw = auragold_jcat_excel_cell_val($sheet, $map, 'metal', $r);
            $productRaw = auragold_jcat_excel_cell_val($sheet, $map, 'product', $r);
            $categoryRaw = auragold_jcat_excel_cell_val($sheet, $map, 'category', $r);
            $title = auragold_jcat_excel_cell_val($sheet, $map, 'title', $r);
            $shortDesc = auragold_jcat_excel_cell_val($sheet, $map, 'short_desc', $r);
            $fullDesc = auragold_jcat_excel_cell_val($sheet, $map, 'full_desc', $r);
            $barcode = auragold_jcat_excel_cell_val($sheet, $map, 'barcode', $r);
            $designNo = auragold_jcat_excel_cell_val($sheet, $map, 'design_no', $r);
            $sku = auragold_jcat_excel_cell_val($sheet, $map, 'sku', $r);
            $imageRaw = auragold_jcat_excel_cell_val($sheet, $map, 'image_url', $r);

            $weightCol = (int) ($map['weight'] ?? 0);
            $amountCol = (int) ($map['amount'] ?? 0);
            $weight = $weightCol > 0 ? auragold_po_excel_cell_f($sheet, $weightCol, $r) : 0.0;
            $amount = $amountCol > 0 ? auragold_po_excel_cell_f($sheet, $amountCol, $r) : 0.0;

            $allBlank = ($title === '' && $productRaw === '' && $designNo === '' && $barcode === ''
                && $weight <= 0.00001 && $amount <= 0.00001 && $metalRaw === '');
            if ($allBlank) {
                continue;
            }

            $dataRows++;
            if ($dataRows > $maxRows) {
                $errors[] = 'Stopped at row ' . $r . ': maximum ' . $maxRows . ' catalogue rows per upload.';
                break;
            }

            if ($title === '' && $productRaw !== '') {
                $title = $productRaw;
            }
            if ($title === '' && $designNo !== '') {
                $title = $designNo;
            }
            if ($title === '') {
                $errors[] = 'Row ' . $r . ': Title is required (or fill Product / Design No).';
                continue;
            }
            if ($shortDesc === '') {
                $shortDesc = $title;
            }
            if ($weight <= 0.00001) {
                $errors[] = 'Row ' . $r . ' (' . $title . '): Weight is required.';
                continue;
            }
            if ($amount <= 0.00001) {
                $errors[] = 'Row ' . $r . ' (' . $title . '): Amount is required.';
                continue;
            }

            $metalId = auragold_jcat_excel_resolve_metal_id($conn, $metalRaw);
            if ($metalRaw !== '' && $metalId <= 0) {
                $errors[] = 'Row ' . $r . ' (' . $title . '): Unknown metal "' . $metalRaw . '".';
                continue;
            }
            $productId = auragold_jcat_excel_resolve_product_id($conn, $productRaw);
            if ($productRaw !== '' && $productId <= 0) {
                $errors[] = 'Row ' . $r . ' (' . $title . '): Unknown product "' . $productRaw . '".';
                continue;
            }
            $categoryId = auragold_po_excel_resolve_category_id($conn, $categoryRaw);
            if ($categoryRaw !== '' && $categoryId <= 0) {
                $errors[] = 'Row ' . $r . ' (' . $title . '): Unknown category "' . $categoryRaw . '".';
                continue;
            }

            $rows[] = [
                'metal_id' => $metalId,
                'product_id' => $productId,
                'category_id' => $categoryId,
                'title' => $title,
                'short_desc' => $shortDesc,
                'full_desc' => $fullDesc,
                'barcode' => $barcode,
                'design_no' => $designNo,
                'sku' => $sku,
                'weight' => $weight,
                'amount' => $amount,
                'images' => auragold_jcat_excel_parse_images($imageRaw),
                'bom' => [],
                '_row' => $r,
            ];
        }

        return ['rows' => $rows, 'errors' => $errors];
    }
}

if (!function_exists('auragold_jcat_excel_import_rows')) {
    /**
     * @param list<array<string,mixed>> $rows
     * @return array{imported:int,failed:int,errors:list<string>,message:string}
     */
    function auragold_jcat_excel_import_rows(mysqli $conn, array $rows): array
    {
        auragold_ensure_jewelry_catalogue_table($conn);

        $imported = 0;
        $failed = 0;
        $errors = [];

        foreach ($rows as $row) {
            $rowNum = (int) ($row['_row'] ?? 0);
            unset($row['_row']);
            $label = trim((string) ($row['title'] ?? ''));
            $result = auragold_jewelry_catalogue_save($conn, $row);
            if (!empty($result['success'])) {
                $imported++;
                continue;
            }
            $failed++;
            $msg = trim((string) ($result['message'] ?? 'Could not save catalogue row.'));
            $errors[] = ($rowNum > 0 ? 'Row ' . $rowNum : 'Item') . ($label !== '' ? ' (' . $label . ')' : '') . ': ' . $msg;
        }

        $message = $imported > 0
            ? 'Imported ' . $imported . ' catalogue design(s).'
            : 'No catalogue designs were imported.';
        if ($failed > 0) {
            $message .= ' ' . $failed . ' row(s) failed.';
        }

        return [
            'imported' => $imported,
            'failed' => $failed,
            'errors' => $errors,
            'message' => $message,
        ];
    }
}
