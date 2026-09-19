<?php
/**
 * Field formatting for product opening / Add Product modal (same as product-opening.php top helpers).
 */
if (!function_exists('format_decimal_display')) {
    function format_decimal_display($val) {
        if ($val === '' || $val === null) {
            return '';
        }
        $v = trim((string) $val);
        if ($v === '') {
            return '';
        }
        if (!is_numeric($v)) {
            return htmlspecialchars($v);
        }
        $f = (float) $v;
        if (stripos($v, 'e') !== false) {
            return rtrim(rtrim(number_format($f, 6, '.', ''), '0'), '.') ?: '0';
        }
        if (strpos($v, '.') === false) {
            return $v;
        }
        $trimmed = rtrim(rtrim($v, '0'), '.');

        return $trimmed === '' || $trimmed === '-' ? '0' : $trimmed;
    }
}

if (!function_exists('auragold_format_weight_list')) {
    /**
     * Stock Journal list weights: keep up to 6 decimals (no 3-dp rounding) with thousands separators.
     */
    function auragold_format_weight_list($val): string
    {
        if ($val === '' || $val === null) {
            return '0';
        }
        $raw = format_decimal_display($val);
        if ($raw === '' || $raw === null) {
            return '0';
        }
        if (!is_numeric($raw)) {
            return '0';
        }
        $neg = ($raw[0] === '-');
        $abs = ltrim($raw, '+-');
        $parts = explode('.', $abs, 2);
        $intPart = $parts[0] === '' ? '0' : $parts[0];
        $decPart = $parts[1] ?? '';
        if (strlen($decPart) > 6) {
            $decPart = rtrim(substr($decPart, 0, 6), '0');
        }
        $intFmt = ctype_digit($intPart) ? number_format((int) $intPart, 0, '.', ',') : $intPart;
        $out = $intFmt . ($decPart !== '' ? '.' . $decPart : '');

        return ($neg && $out !== '0' ? '-' : '') . $out;
    }
}

if (!function_exists('opening_purity_field_value')) {
    function opening_purity_field_value($metal_display_name, $char_data) {
        if ($char_data && array_key_exists('opening_purity', $char_data) && $char_data['opening_purity'] !== null && $char_data['opening_purity'] !== '') {
            return format_decimal_display($char_data['opening_purity']);
        }
        $n = trim((string) $metal_display_name);
        if (in_array($n, ['Gold', 'Silver', 'Platinum'], true)) {
            return '1';
        }

        return '';
    }
}

if (!function_exists('opening_barcode_prefix_default')) {
    function opening_barcode_prefix_default($metal_display_name, $metal_id = 0) {
        $branch_id = 0;
        if (function_exists('auragold_effective_branch_id')) {
            $branch_id = (int) auragold_effective_branch_id();
        }
        if ($branch_id <= 0 && function_exists('auragold_settings_branch_id')) {
            $branch_id = (int) auragold_settings_branch_id();
        }
        $metal_id = (int) $metal_id;
        if ($branch_id > 0 && $metal_id > 0) {
            if (!function_exists('auragold_branch_metal_barcode_prefix_row')) {
                require_once __DIR__ . '/auragold_barcode_prefix_settings.php';
            }
            $row = auragold_branch_metal_barcode_prefix_row($branch_id, $metal_id);
            if ($row && trim((string) ($row['prefix'] ?? '')) !== '') {
                return trim((string) $row['prefix']);
            }
        }

        $n = trim((string) $metal_display_name);
        $defaults = [
            'Gold' => 'GD',
            'Silver' => 'SV',
            'Diamond & Stones' => 'DM',
        ];

        return $defaults[$n] ?? 'RN';
    }
}

if (!function_exists('opening_barcode_prefix_value')) {
    function opening_barcode_prefix_value($metal_display_name, $char_data, $metal_id = 0) {
        if ($char_data && array_key_exists('barcode_prefix', $char_data)) {
            $saved = trim((string) $char_data['barcode_prefix']);
            if ($saved !== '') {
                return $saved;
            }
        }

        return opening_barcode_prefix_default($metal_display_name, $metal_id);
    }
}
