<?php
/**
 * Sale/repair order line photos → voucher embed items (group_image for product list).
 */

if (!function_exists('auragold_voucher_resolve_image_url')) {
    function auragold_voucher_resolve_image_url(string $path, string $base_url): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        if (function_exists('auragold_uploads_public_url')) {
            $resolved = auragold_uploads_public_url($path);
            if ($resolved !== '') {
                return $resolved;
            }
        }
        $base = rtrim((string) $base_url, '/');
        $rel = function_exists('auragold_uploads_public_rel')
            ? auragold_uploads_public_rel(ltrim($path, '/'))
            : ltrim($path, '/');

        return ($base === '' ? '' : $base . '/') . $rel;
    }
}

if (!function_exists('auragold_voucher_parse_image_paths')) {
    /**
     * @return list<string> absolute or site-root-relative URLs
     */
    function auragold_voucher_parse_image_paths(string $raw, string $base_url): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        $paths = [];
        $dec = @json_decode($raw, true);
        if (is_array($dec)) {
            if (!empty($dec['images']) && is_array($dec['images'])) {
                foreach ($dec['images'] as $u) {
                    if ($u !== '' && $u !== null) {
                        $paths[] = (string) $u;
                    }
                }
            }
            if ($paths === [] && !empty($dec['primary'])) {
                $paths[] = (string) $dec['primary'];
            }
            if ($paths === [] && isset($dec[0])) {
                foreach ($dec as $u) {
                    if (is_string($u) && trim($u) !== '') {
                        $paths[] = $u;
                    }
                }
            }
        } elseif (preg_match('#^https?://#i', $raw) || preg_match('#^(?:admin/)?uploads/#i', $raw)) {
            $paths[] = $raw;
        }

        $urls = [];
        foreach ($paths as $p) {
            $u = auragold_voucher_resolve_image_url((string) $p, $base_url);
            if ($u !== '') {
                $urls[] = $u;
            }
        }

        return array_values(array_unique($urls));
    }
}

if (!function_exists('auragold_voucher_images_raw_to_group_image')) {
    function auragold_voucher_images_raw_to_group_image(string $raw, string $base_url): string
    {
        $urls = auragold_voucher_parse_image_paths($raw, $base_url);
        if ($urls === []) {
            return '';
        }

        return json_encode(['primary' => $urls[0], 'images' => $urls]);
    }
}

if (!function_exists('auragold_voucher_apply_catalogue_images_to_items')) {
    /**
     * Fill group_image from jewelry catalogue when line has design_no but no saved photos.
     *
     * @param array<int, array<string, mixed>> $items
     */
    function auragold_voucher_apply_catalogue_images_to_items($conn, array &$items, string $base_url): void
    {
        if (!$conn || $items === []) {
            return;
        }
        if (!function_exists('auragold_jewelry_catalogue_find_by_design_no')) {
            $inc = __DIR__ . '/jewelry_catalogue_create_include.php';
            if (is_file($inc)) {
                require_once $inc;
            }
        }
        if (!function_exists('auragold_jewelry_catalogue_find_by_design_no')
            || !function_exists('auragold_jewelry_catalogue_normalize_db_row')
            || !function_exists('auragold_jewelry_catalogue_images_to_group_image')) {
            return;
        }
        $siteUrl = rtrim($base_url, '/');
        foreach ($items as &$it) {
            if (!empty($it['group_image'])) {
                continue;
            }
            $dn = trim((string) ($it['design_no'] ?? ''));
            if ($dn === '') {
                continue;
            }
            $row = auragold_jewelry_catalogue_find_by_design_no($conn, $dn);
            if (!$row) {
                continue;
            }
            $catalogue = auragold_jewelry_catalogue_normalize_db_row($row);
            $gi = auragold_jewelry_catalogue_images_to_group_image($catalogue['images'] ?? [], $siteUrl);
            if ($gi !== '') {
                $it['group_image'] = $gi;
            }
        }
        unset($it);
    }
}

if (!function_exists('auragold_voucher_apply_line_images_to_items')) {
  /**
   * @param array<int, array<string, mixed>> $items
   */
    function auragold_voucher_apply_line_images_to_items(array &$items, string $base_url): void
    {
        foreach ($items as &$it) {
            if (!empty($it['group_image'])) {
                continue;
            }
            $gi = auragold_voucher_images_raw_to_group_image((string) ($it['images'] ?? ''), $base_url);
            if ($gi !== '') {
                $it['group_image'] = $gi;
            }
        }
        unset($it);
    }
}

if (!function_exists('auragold_voucher_embed_items_apply_all_images')) {
    /**
     * Line images column, then jewellery catalogue by design no.
     *
     * @param array<int, array<string, mixed>> $items
     */
    function auragold_voucher_embed_items_apply_all_images($conn, array &$items, string $base_url): void
    {
        auragold_voucher_apply_line_images_to_items($items, $base_url);
        auragold_voucher_apply_catalogue_images_to_items($conn, $items, $base_url);
    }
}

if (!function_exists('auragold_voucher_merge_linked_order_line_images')) {
    /**
     * Material issue/receive lines do not store photos; copy from sale/repair order lines.
     *
     * @param array<int, array<string, mixed>> $items
     */
    function auragold_voucher_merge_linked_order_line_images($conn, array &$items, int $order_id, bool $from_repair = false): void
    {
        if (!$conn || $order_id <= 0 || $items === []) {
            return;
        }

        $needs = false;
        foreach ($items as $it) {
            if (!empty($it['images']) || !empty($it['group_image'])) {
                continue;
            }
            if (isset($it['material_issue_id']) || isset($it['material_receive_id'])
                || isset($it['repair_material_issue_id']) || isset($it['repair_material_receive_id'])) {
                $needs = true;
                break;
            }
        }
        if (!$needs) {
            return;
        }

        $tbl = $from_repair ? 'tbl_repair_order_items' : 'tbl_sale_order_items';
        $tchk = @mysqli_query($conn, "SHOW TABLES LIKE '$tbl'");
        if (!$tchk || mysqli_num_rows($tchk) === 0) {
            if ($tchk) {
                mysqli_free_result($tchk);
            }
            return;
        }
        mysqli_free_result($tchk);

        $icol = @mysqli_query($conn, "SHOW COLUMNS FROM `$tbl` LIKE 'images'");
        $has_images = ($icol && mysqli_num_rows($icol) > 0);
        if ($icol) {
            mysqli_free_result($icol);
        }
        if (!$has_images) {
            return;
        }

        $oid = (int) $order_id;
        $lines = getList(
            "SELECT product_id, product_characteristic_id, barcode, design_no, images
             FROM `$tbl`
             WHERE order_id = $oid AND images IS NOT NULL AND TRIM(images) <> ''"
        );
        if (!is_array($lines) || $lines === []) {
            return;
        }

        $by_pc = [];
        $by_bc = [];
        $by_dn = [];
        foreach ($lines as $ln) {
            $img = trim((string) ($ln['images'] ?? ''));
            if ($img === '') {
                continue;
            }
            $pid = (int) ($ln['product_id'] ?? 0);
            $cid = (int) ($ln['product_characteristic_id'] ?? 0);
            if ($pid > 0) {
                $k = $pid . ':' . $cid;
                if (!isset($by_pc[$k])) {
                    $by_pc[$k] = $img;
                }
            }
            $bc = trim((string) ($ln['barcode'] ?? ''));
            if ($bc !== '' && !isset($by_bc[$bc])) {
                $by_bc[$bc] = $img;
            }
            $dn = trim((string) ($ln['design_no'] ?? ''));
            if ($dn !== '' && !isset($by_dn[$dn])) {
                $by_dn[$dn] = $img;
            }
        }

        $single_line_img = (count($lines) === 1) ? trim((string) ($lines[0]['images'] ?? '')) : '';

        foreach ($items as &$it) {
            if (!empty($it['images']) || !empty($it['group_image'])) {
                continue;
            }
            if (!isset($it['material_issue_id']) && !isset($it['material_receive_id'])
                && !isset($it['repair_material_issue_id']) && !isset($it['repair_material_receive_id'])) {
                continue;
            }

            $img = null;
            $pid = (int) ($it['product_id'] ?? 0);
            $cid = (int) ($it['product_characteristic_id'] ?? 0);
            if ($pid > 0) {
                $k = $pid . ':' . $cid;
                if (isset($by_pc[$k])) {
                    $img = $by_pc[$k];
                }
            }
            if ($img === null) {
                $bc = trim((string) ($it['barcode'] ?? ''));
                if ($bc !== '' && isset($by_bc[$bc])) {
                    $img = $by_bc[$bc];
                }
            }
            if ($img === null) {
                $dn = trim((string) ($it['design_no'] ?? ''));
                if ($dn !== '' && isset($by_dn[$dn])) {
                    $img = $by_dn[$dn];
                }
            }
            if ($img === null && $single_line_img !== '' && count($items) === 1) {
                $img = $single_line_img;
            }
            if ($img !== null && $img !== '') {
                $it['images'] = $img;
            }
        }
        unset($it);
    }
}

if (!function_exists('auragold_voucher_embed_items_apply_images')) {
    /**
     * @param array<int, array<string, mixed>> $items
     */
    function auragold_voucher_embed_items_apply_images($conn, array &$items, string $base_url, int $linked_order_id = 0, bool $from_repair = false): void
    {
        if ($linked_order_id > 0) {
            auragold_voucher_merge_linked_order_line_images($conn, $items, $linked_order_id, $from_repair);
        }
        auragold_voucher_apply_line_images_to_items($items, $base_url);
    }
}
