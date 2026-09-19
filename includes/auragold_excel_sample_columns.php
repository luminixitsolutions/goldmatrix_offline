<?php
/**
 * Product modal Excel sample: column layout per metal tab (Gold/Silver vs Diamond & Stones).
 * Matches applyProductModalColumnVisibilityForTab — metal tabs hide diamond-group columns.
 */
if (!function_exists('auragold_excel_sample_header_rows')) {
    /**
     * Full sample header row (label => groups). A header is kept when any group matches the active tab.
     *
     * @return array<int, array{0: string, 1: array<int, string>}>
     */
    function auragold_excel_sample_header_rows(): array
    {
        return [
            ['Images', ['always']],
            ['Action', ['skip']],
            ['Column Groups', ['skip']],
            ['Basic Information', ['skip']],
            ['Id', ['basic']],
            ['RFIDCode', ['basic']],
            ['Voucher Type', ['diamond']],
            ['Photo', ['basic']],
            ['Barcode', ['basic']],
            ['Design No', ['basic']],
            ['HUID No', ['basic']],
            ['Category', ['diamond']],
            ['Calculation', ['basic']],
            ['Product', ['basic']],
            ['Location', ['basic']],
            ['Diamond group', ['skip']],
            ['Pkt. Wt.', ['diamond']],
            ['PKt. Less Wt.', ['diamond']],
            ['Gross Wt.', ['diamond']],
            ['Carat', ['diamond', 'metal_karat']],
            ['D.Weight', ['diamond']],
            ['Net Wt.', ['diamond']],
            ['Quantity', ['diamond', 'metal_qty']],
            ['Rate', ['diamond', 'metal_rate_col']],
            ['Amount', ['diamond', 'metal_amount']],
            ['Metal group', ['skip']],
            ['Metal Qty', ['metal']],
            ['Weight', ['metal']],
            ['Carat', ['metal_karat']],
            ['Purity %', ['metal']],
            ['Purity Wt', ['metal']],
            ['Loss Wt.', ['metal']],
            ['Loss Wt. Per', ['metal']],
            ['Loss Value', ['metal']],
            ['Wastage Per', ['metal']],
            ['Wastage Wt', ['metal']],
            ['Metal Rate', ['metal']],
            ['Metal Value', ['metal']],
            ['Metal Cost', ['metal']],
            ['Request & Final Wt.', ['skip']],
            ['Requested Purity', ['reqfinal']],
            ['Requested', ['reqfinal']],
            ['Setting Charge', ['diamond', 'reqfinal']],
            ['Final Wt.', ['reqfinal']],
            ['Alloy Wt.', ['reqfinal']],
            ['Discount (group)', ['skip']],
            ['Discount Type', ['discount']],
            ['Discount Per.', ['discount']],
            ['Discount Amount', ['discount']],
            ['Discount', ['discount']],
            ['Making (group)', ['skip']],
            ['Making Type', ['making']],
            ['Making Rate', ['making']],
            ['Making Discount Amt.', ['making']],
            ['Making Amount', ['making']],
            ['Making Actual Value', ['making']],
            ['Making Cost', ['making']],
            ['Minimum', ['making']],
            ['Minimum Price', ['making']],
            ['Minimum Code', ['making']],
            ['Stone group', ['skip']],
            ['Stone Charge Type', ['stone']],
            ['Stone Rate', ['stone']],
            ['Stone Amount', ['stone']],
            ['Stone Cost', ['stone']],
            ['Diamond Amount', ['stone']],
            ['Amounts', ['skip']],
            ['Purchase Amount', ['amounts']],
            ['Sale Percentages', ['amounts']],
            ['Sale Amount', ['amounts']],
            ['Sale Amount With Tax', ['amounts']],
            ['Net Amt', ['amounts']],
            ['Tax Type', ['amounts']],
            ['Tax %', ['amounts']],
            ['Tax', ['amounts']],
            ['Other Charge (group)', ['skip']],
            ['Other Charge Type', ['other']],
            ['Other Weight', ['other']],
            ['Other Rate', ['other']],
            ['Other Info', ['other']],
            ['Other Amount', ['other']],
            ['Certificate & spec', ['skip']],
            ['Certificate Amount', ['diamond_spec']],
            ['Certificate No.', ['diamond_spec']],
            ['Certificate Link', ['diamond_spec']],
            ['Video Link', ['diamond_spec']],
            ['Cut', ['diamond_spec']],
            ['Color', ['diamond_spec']],
            ['Seive', ['diamond_spec']],
            ['Size', ['diamond_spec']],
            ['Shape', ['diamond_spec']],
            ['Clarity', ['diamond_spec']],
            ['Unit Price', ['diamond_spec']],
            ['Hallmark', ['hallmark']],
            ['Hallmark Amount', ['hallmark']],
            ['Hallmark Rate', ['hallmark']],
            ['Net Amt+Tax / Reverse', ['skip']],
            ['Net Amt+Tax', ['netrev']],
            ['Reverse', ['netrev']],
            ['Product Name', ['footer']],
            ['Product ID', ['footer']],
            ['Characteristic ID', ['footer']],
        ];
    }
}

if (!function_exists('auragold_excel_sample_tab_shows_diamond_columns')) {
    function auragold_excel_sample_tab_shows_diamond_columns(int $metal_id): bool
    {
        if ($metal_id <= 0) {
            return true;
        }
        global $conn, $conn_master;
        $dbc = (isset($conn) && $conn instanceof mysqli) ? $conn : $conn_master;
        if ($dbc instanceof mysqli && function_exists('auragold_is_diamond_and_stones_metal_id')
            && auragold_is_diamond_and_stones_metal_id($dbc, $metal_id)) {
            return true;
        }
        $m = getRecord('SELECT display_name FROM tbl_metal WHERE id = ' . (int) $metal_id . ' LIMIT 1');
        if (!$m) {
            return false;
        }
        $dn = trim((string) ($m['display_name'] ?? ''));

        return function_exists('auragold_metal_display_name_is_loose_diamond_tab')
            && auragold_metal_display_name_is_loose_diamond_tab($dn);
    }
}

if (!function_exists('auragold_excel_sample_groups_for_tab')) {
    /**
     * @return array<int, string>
     */
    function auragold_excel_sample_groups_for_tab(int $metal_id): array
    {
        if ($metal_id <= 0 || auragold_excel_sample_tab_shows_diamond_columns($metal_id)) {
            return [
                'always', 'basic', 'diamond', 'diamond_spec', 'metal', 'metal_karat', 'metal_qty', 'metal_rate_col', 'metal_amount',
                'reqfinal', 'discount', 'making', 'stone', 'amounts', 'other', 'hallmark', 'netrev', 'footer',
            ];
        }

        return [
            'always', 'basic', 'metal', 'metal_karat', 'metal_qty', 'metal_rate_col', 'metal_amount',
            'reqfinal', 'discount', 'making', 'stone', 'amounts', 'other', 'hallmark', 'netrev', 'footer',
        ];
    }
}

if (!function_exists('auragold_excel_sample_headers_for_metal_tab')) {
    /**
     * @return array<int, string>
     */
    function auragold_excel_sample_headers_for_metal_tab(int $metal_id): array
    {
        $allowed = array_flip(auragold_excel_sample_groups_for_tab($metal_id));
        $out = [];
        $caratSeen = 0;

        foreach (auragold_excel_sample_header_rows() as $row) {
            $label = (string) $row[0];
            $groups = (array) $row[1];
            if (in_array('skip', $groups, true)) {
                continue;
            }
            $keep = false;
            foreach ($groups as $g) {
                if (isset($allowed[$g])) {
                    $keep = true;
                    break;
                }
            }
            if (!$keep) {
                continue;
            }
            // Metal tab: one Carat column (karat). Diamond tab: keep both (diamond carat + karat).
            if ($label === 'Carat') {
                $caratSeen++;
                if ($metal_id > 0 && !auragold_excel_sample_tab_shows_diamond_columns($metal_id) && $caratSeen < 2) {
                    continue;
                }
            }
            $out[] = $label;
        }

        return $out;
    }
}

if (!function_exists('auragold_excel_metal_display_name_for_id')) {
    function auragold_excel_metal_display_name_for_id(int $metal_id): string
    {
        if ($metal_id <= 0) {
            return '';
        }
        $m = getRecord('SELECT display_name, system_name FROM tbl_metal WHERE id = ' . (int) $metal_id . ' LIMIT 1');
        if (!$m) {
            return '';
        }
        $dn = trim((string) ($m['display_name'] ?? ''));
        if ($dn !== '') {
            return $dn;
        }

        return trim((string) ($m['system_name'] ?? ''));
    }
}

if (!function_exists('auragold_excel_metal_type_for_extra_fields')) {
    /** Map tbl_metal row to tbl_extra_fields.metal_type (Gold, Silver, Diamond & Stones, …). */
    function auragold_excel_metal_type_for_extra_fields(int $metal_id): string
    {
        $dn = auragold_excel_metal_display_name_for_id($metal_id);
        if ($dn === '') {
            return 'Gold';
        }
        if (!function_exists('auragold_extra_field_normalize_metal')) {
            require_once __DIR__ . '/auragold_extra_fields_schema.php';
        }

        return auragold_extra_field_normalize_metal($dn);
    }
}

if (!function_exists('auragold_excel_sample_extra_field_defs')) {
    /**
     * Active extra-field definitions for a metal tab (for sample Excel + import).
     *
     * @return array<int, array<string, mixed>>
     */
    function auragold_excel_sample_extra_field_defs($conn, int $metal_id, int $branch_id = 0): array
    {
        if (!$conn instanceof mysqli || $metal_id <= 0) {
            return [];
        }
        if (!function_exists('auragold_get_extra_fields')) {
            require_once __DIR__ . '/auragold_extra_fields_schema.php';
        }
        if ($branch_id <= 0 && function_exists('auragold_settings_branch_id')) {
            $branch_id = (int) auragold_settings_branch_id();
        }
        $metal_type = auragold_excel_metal_type_for_extra_fields($metal_id);
        $defs = [];
        foreach (auragold_get_extra_fields($conn, $branch_id, $metal_type) as $row) {
            if ((int) ($row['status'] ?? 0) !== 1) {
                continue;
            }
            $label = trim((string) ($row['display_name'] ?? ''));
            if ($label === '') {
                continue;
            }
            $defs[] = $row;
        }

        return $defs;
    }
}

if (!function_exists('auragold_excel_sample_extra_field_labels')) {
    /** @return array<int, string> column labels for sample sheet */
    function auragold_excel_sample_extra_field_labels($conn, int $metal_id, int $branch_id = 0): array
    {
        $out = [];
        foreach (auragold_excel_sample_extra_field_defs($conn, $metal_id, $branch_id) as $def) {
            $out[] = trim((string) ($def['display_name'] ?? ''));
        }

        return $out;
    }
}

if (!function_exists('auragold_excel_sample_merge_extra_field_headers')) {
    /**
     * Insert extra-field column labels before footer columns (Product Name / IDs).
     *
     * @param array<int, string> $baseHeaders
     * @param array<int, string> $extraLabels
     * @return array<int, string>
     */
    function auragold_excel_sample_merge_extra_field_headers(array $baseHeaders, array $extraLabels): array
    {
        if ($extraLabels === []) {
            return $baseHeaders;
        }
        $idx = array_search('Product Name', $baseHeaders, true);
        if ($idx === false) {
            return array_merge($baseHeaders, $extraLabels);
        }

        return array_merge(
            array_slice($baseHeaders, 0, $idx),
            $extraLabels,
            array_slice($baseHeaders, $idx)
        );
    }
}

if (!function_exists('auragold_excel_sample_headers_for_metal_tab_with_extras')) {
    /**
     * @return array<int, string>
     */
    function auragold_excel_sample_headers_for_metal_tab_with_extras($conn, int $metal_id, int $branch_id = 0): array
    {
        $base = auragold_excel_sample_headers_for_metal_tab($metal_id);
        if (!$conn instanceof mysqli || $metal_id <= 0) {
            return $base;
        }
        $extra = auragold_excel_sample_extra_field_labels($conn, $metal_id, $branch_id);

        return auragold_excel_sample_merge_extra_field_headers($base, $extra);
    }
}
