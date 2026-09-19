<?php
/**
 * Sale invoice metal tabs use branch-scoped tbl_metal rows (and dedupe by display_name).
 * Product opening may save pc.metal_id pointing at another row with the same display_name.
 * Match characteristics to the active tab by display_name so search / lists stay consistent.
 */
if (!function_exists('auragold_sql_pc_metal_matches_tab_metal')) {
    function auragold_sql_pc_metal_matches_tab_metal(int $metal_id): string {
        if ($metal_id <= 0) {
            return '';
        }
        $mtab = getRecord('SELECT display_name FROM tbl_metal WHERE id = ' . (int) $metal_id . ' LIMIT 1');
        $dn = $mtab ? trim((string) ($mtab['display_name'] ?? '')) : '';
        if ($dn === '') {
            return ' AND pc.metal_id = ' . (int) $metal_id;
        }
        $dn_esc = esc($dn);
        return " AND pc.metal_id IN (SELECT id FROM tbl_metal WHERE status = 1 AND LOWER(TRIM(display_name)) = LOWER(TRIM('$dn_esc')))";
    }
}

/** Loose / Certified Diamond & Stones tab (not the main Diamond & Stones tab). */
if (!function_exists('auragold_metal_display_name_is_loose_diamond_tab')) {
    function auragold_metal_display_name_is_loose_diamond_tab(string $display_name): bool
    {
        $n = strtolower(trim(preg_replace('/\s+/', ' ', $display_name)));
        if ($n === '' || $n === 'diamond & stones') {
            return false;
        }
        if (strpos($n, 'diamond') === false) {
            return false;
        }

        return (bool) preg_match('/\b(loose|loos|certified)\b/', $n);
    }
}

/**
 * SQL filter for pc.diamond_category (UI "Diamond"/"Stone" map to legacy Diamonds/GemStones values).
 */
if (!function_exists('auragold_sql_pc_diamond_category_filter')) {
    function auragold_sql_pc_diamond_category_filter(string $diamond_category): string
    {
        $diamond_category = trim($diamond_category);
        if ($diamond_category === '') {
            return '';
        }
        if (in_array($diamond_category, ['Jewellery', 'Diamonds', 'GemStones'], true)) {
            return " AND pc.diamond_category = '" . esc($diamond_category) . "'";
        }
        if ($diamond_category === 'Diamond') {
            return " AND LOWER(TRIM(pc.diamond_category)) IN ('diamond', 'diamonds')";
        }
        if ($diamond_category === 'Stone') {
            return " AND LOWER(TRIM(pc.diamond_category)) IN ('stone', 'stones', 'gemstones', 'gem stones')";
        }

        return '';
    }
}

/**
 * Sale-order / job work Add Diamond modal: Diamonds + GemStones only (exclude Jewellery).
 */
if (!function_exists('auragold_sql_pc_diamond_and_stone_stock_filter')) {
    function auragold_sql_pc_diamond_and_stone_stock_filter(): string
    {
        return " AND (
            pc.diamond_category IN ('Diamonds', 'GemStones')
            OR LOWER(TRIM(pc.diamond_category)) IN ('diamond', 'diamonds', 'stone', 'stones', 'gemstones', 'gem stones')
        )";
    }
}

/**
 * Product lists for sale modal: loose diamond tab also includes Diamond & Stones metal products.
 */
if (!function_exists('auragold_sql_pc_metal_for_product_list')) {
    function auragold_sql_pc_metal_for_product_list(int $metal_id): string
    {
        if ($metal_id <= 0) {
            return '';
        }
        $mtab = getRecord('SELECT display_name FROM tbl_metal WHERE id = ' . (int) $metal_id . ' LIMIT 1');
        $dn = $mtab ? trim((string) ($mtab['display_name'] ?? '')) : '';
        if ($dn === '') {
            return ' AND pc.metal_id = ' . (int) $metal_id;
        }
        if (auragold_metal_display_name_is_loose_diamond_tab($dn)) {
            $dn_esc = esc($dn);
            $ds_esc = esc('Diamond & Stones');

            return " AND pc.metal_id IN (SELECT id FROM tbl_metal WHERE status = 1 AND (LOWER(TRIM(display_name)) = LOWER(TRIM('$dn_esc')) OR LOWER(TRIM(display_name)) = LOWER(TRIM('$ds_esc'))))";
        }

        return auragold_sql_pc_metal_matches_tab_metal($metal_id);
    }
}

/**
 * Normalize Diamond & Stones tab category from invoice / modal values.
 */
if (!function_exists('auragold_normalize_diamond_tab_category')) {
    function auragold_normalize_diamond_tab_category(string $raw): string
    {
        $raw = trim($raw);
        if (in_array($raw, ['Jewellery', 'Diamonds', 'GemStones'], true)) {
            return $raw;
        }
        $l = strtolower($raw);
        if ($l === 'jewellery' || $l === 'jewelry') {
            return 'Jewellery';
        }
        if ($l === 'diamond' || $l === 'diamonds') {
            return 'Diamonds';
        }
        if (in_array($l, ['gemstone', 'gemstones', 'gem stones', 'stone', 'stones'], true)) {
            return 'GemStones';
        }

        return '';
    }
}

if (!function_exists('auragold_metal_id_for_display_name')) {
    function auragold_metal_id_for_display_name(mysqli $conn, string $display_name): int
    {
        $dn = esc(trim($display_name));
        if ($dn === '') {
            return 0;
        }
        $r = getRecord("SELECT id FROM tbl_metal WHERE status = 1 AND LOWER(TRIM(display_name)) = LOWER(TRIM('$dn')) ORDER BY id ASC LIMIT 1");

        return $r ? (int) ($r['id'] ?? 0) : 0;
    }
}

if (!function_exists('auragold_is_diamond_and_stones_metal_id')) {
    function auragold_is_diamond_and_stones_metal_id(mysqli $conn, int $metal_id): bool
    {
        if ($metal_id <= 0) {
            return false;
        }
        $m = getRecord('SELECT display_name FROM tbl_metal WHERE id = ' . (int) $metal_id . ' LIMIT 1');

        return $m && strtolower(trim((string) ($m['display_name'] ?? ''))) === 'diamond & stones';
    }
}

/**
 * Match stock to Jewellery / Diamonds / GemStones tab via product characteristic (and purchase line fallback).
 *
 * @return array{characteristic_id: int, metal_id: int, diamond_category: string}
 */
if (!function_exists('auragold_resolve_characteristic_for_diamond_category_purchase')) {
    function auragold_resolve_characteristic_for_diamond_category_purchase(
        mysqli $conn,
        int $product_id,
        int $characteristic_id,
        int $branch_id,
        int $metal_id,
        string $diamond_category_raw
    ): array {
        $dc = auragold_normalize_diamond_tab_category($diamond_category_raw);
        $out = [
            'characteristic_id' => $characteristic_id,
            'metal_id' => $metal_id,
            'diamond_category' => $dc,
        ];
        if ($product_id <= 0 || $dc === '') {
            return $out;
        }

        $ds_metal_id = auragold_metal_id_for_display_name($conn, 'Diamond & Stones');
        if ($ds_metal_id <= 0) {
            return $out;
        }

        if (!auragold_is_diamond_and_stones_metal_id($conn, $metal_id)) {
            $metal_id = $ds_metal_id;
            $out['metal_id'] = $metal_id;
        }

        if (!function_exists('auragold_tbl_has_column') || !auragold_tbl_has_column($conn, 'tbl_product_characteristics', 'diamond_category')) {
            return $out;
        }

        $dc_esc = esc($dc);
        $branch_sql = $branch_id > 0 ? ' AND pc.branch_id = ' . (int) $branch_id : '';
        $metal_sql = auragold_sql_pc_metal_matches_tab_metal($ds_metal_id);

        $match = getRecord("
            SELECT pc.id
            FROM tbl_product_characteristics pc
            WHERE pc.product_id = " . (int) $product_id . "
              AND pc.status = 1
              AND TRIM(COALESCE(pc.diamond_category, '')) = '$dc_esc'
              $metal_sql
              $branch_sql
            ORDER BY pc.id ASC
            LIMIT 1
        ");
        if ($match && (int) ($match['id'] ?? 0) > 0) {
            $out['characteristic_id'] = (int) $match['id'];
            $out['metal_id'] = $ds_metal_id;

            return $out;
        }

        if ($characteristic_id > 0) {
            @mysqli_query(
                $conn,
                "UPDATE tbl_product_characteristics
                 SET diamond_category = '$dc_esc'
                 WHERE id = " . (int) $characteristic_id . ' AND product_id = ' . (int) $product_id
            );
            $out['metal_id'] = $ds_metal_id;

            return $out;
        }

        return $out;
    }
}

/**
 * Diamond & Stones stock list: pc.diamond_category or latest purchase invoice line for same barcode.
 */
if (!function_exists('auragold_sql_diamond_stones_stock_category_filter')) {
    function auragold_sql_diamond_stones_stock_category_filter(string $diamond_category): string
    {
        $diamond_category = trim($diamond_category);
        if ($diamond_category === '') {
            return '';
        }
        if (!in_array($diamond_category, ['Jewellery', 'Diamonds', 'GemStones'], true)) {
            return function_exists('auragold_sql_pc_diamond_category_filter')
                ? auragold_sql_pc_diamond_category_filter($diamond_category)
                : '';
        }

        $c_esc = esc($diamond_category);
        $pc_match = "TRIM(COALESCE(pc.diamond_category, '')) = '$c_esc'";

        static $pii_has_dc = null;
        if ($pii_has_dc === null) {
            $pii_has_dc = false;
            global $conn, $conn_master;
            $dbc = (isset($conn) && $conn instanceof mysqli) ? $conn : $conn_master;
            if ($dbc instanceof mysqli) {
                $r = @mysqli_query($dbc, "SHOW COLUMNS FROM tbl_purchase_invoice_items LIKE 'diamond_category'");
                $pii_has_dc = ($r && mysqli_num_rows($r) > 0);
                if ($r) {
                    mysqli_free_result($r);
                }
            }
        }

        if (!$pii_has_dc) {
            return " AND $pc_match";
        }

        $bc_eq = "(TRIM(COALESCE(pii.barcode, '')) COLLATE utf8mb4_general_ci) = (TRIM(COALESCE(s.barcode, '')) COLLATE utf8mb4_general_ci)";

        return " AND (
            $pc_match
            OR EXISTS (
                SELECT 1
                FROM tbl_purchase_invoice_items pii
                INNER JOIN tbl_purchase_invoices pi ON pi.id = pii.invoice_id
                    AND (pi.status IS NULL OR TRIM(pi.status) = '' OR LOWER(TRIM(pi.status)) NOT IN ('cancelled','void','canceled','deleted'))
                WHERE pii.status = 1
                  AND pii.product_id = s.product_id
                  AND TRIM(COALESCE(pii.barcode, '')) <> ''
                  AND $bc_eq
                  AND TRIM(COALESCE(pii.diamond_category, '')) = '$c_esc'
                  AND (
                      pii.product_characteristic_id = s.product_characteristic_id
                      OR pii.product_characteristic_id IS NULL
                      OR s.product_characteristic_id IS NULL
                  )
            )
        )";
    }
}
