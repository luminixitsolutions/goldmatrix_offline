<?php
/**
 * Shared helpers for job work order list pages (images, sale/repair line photos).
 */

if (!function_exists('auragold_jwo_status_display_value')) {
    /** Canonical JWO status for UI dropdown (default Processing when empty/draft). */
    function auragold_jwo_status_display_value($raw_status, string $default = 'Processing'): string
    {
        $opts = ['Hold', 'Not Initiate', 'Processing', 'Completed', 'Rejected'];
        $raw = trim((string) $raw_status);
        if ($raw === '' || strcasecmp($raw, 'draft') === 0) {
            return $default;
        }
        foreach ($opts as $opt) {
            if (strcasecmp($raw, $opt) === 0) {
                return $opt;
            }
        }

        return $default;
    }
}

if (!function_exists('auragold_jwo_status_canonical_value')) {
    /** Normalize POST/status column to a known option (falls back to Processing). */
    function auragold_jwo_status_canonical_value($raw_status): string
    {
        return auragold_jwo_status_display_value($raw_status, 'Processing');
    }
}

if (!function_exists('jwm_format_spent_time')) {
    function jwm_format_spent_time($seconds) {
        $sec = (int) $seconds;
        if ($sec <= 0) {
            return '—';
        }
        $h = (int) floor($sec / 3600);
        $m = (int) floor(($sec % 3600) / 60);
        $s = $sec % 60;
        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }
}

if (!function_exists('jwm_parse_image_urls')) {
    function jwm_parse_image_urls($jsonOrRaw, $baseUrl) {
        $out = [];
        $raw = trim((string) $jsonOrRaw);
        if ($raw === '') {
            return $out;
        }
        $dec = @json_decode($raw, true);
        if (is_array($dec)) {
            if (!empty($dec['images']) && is_array($dec['images'])) {
                foreach ($dec['images'] as $u) {
                    if ($u !== '' && $u !== null) {
                        $out[] = (string) $u;
                    }
                }
            }
            if (empty($out) && !empty($dec['primary'])) {
                $out[] = (string) $dec['primary'];
            }
            if (empty($out) && isset($dec[0])) {
                foreach ($dec as $u) {
                    if (is_string($u) && $u !== '') {
                        $out[] = $u;
                    }
                }
            }
        } elseif (preg_match('#^https?://#i', $raw) || preg_match('#^(?:admin/)?uploads/#i', $raw)) {
            $out[] = $raw;
        }
        $base = rtrim((string) $baseUrl, '/');
        $prefix = ($base === '' ? '' : $base . '/');
        foreach ($out as &$u) {
            $u = trim((string) $u);
            if ($u === '') {
                continue;
            }
            if (preg_match('#^https?://#i', $u)) {
                continue;
            }
            if (function_exists('auragold_uploads_public_url')) {
                $resolved = auragold_uploads_public_url($u);
                if ($resolved !== '') {
                    $u = $resolved;
                    continue;
                }
            }
            $u = $prefix . auragold_uploads_public_rel(ltrim($u, '/'));
        }
        unset($u);
        $out = array_values(array_filter($out, function ($u) {
            return trim((string) $u) !== '';
        }));
        return array_slice(array_unique($out), 0, 6);
    }
}

if (!function_exists('jwm_row_image_urls')) {
    function jwm_row_image_urls(array $row, $baseUrl = '') {
        foreach (['ji_images', 'sale_item_images', 'repair_item_images', 'queue_images'] as $key) {
            $imgs = jwm_parse_image_urls($row[$key] ?? '', $baseUrl);
            if (!empty($imgs)) {
                return $imgs;
            }
        }
        return [];
    }
}

if (!function_exists('jwm_sql_sale_order_item_images')) {
    function jwm_sql_sale_order_item_images($tag_expr, $has_soi_images, $has_ji_product_id) {
        if (!$has_soi_images) {
            return 'NULL AS sale_item_images';
        }
        $img_nonempty = "soi.images IS NOT NULL AND TRIM(soi.images) <> ''";
        $so_scope = 'soi.order_id = j.sale_order_id AND j.sale_order_id > 0';
        $fallback = "(SELECT soi.images FROM tbl_sale_order_items soi WHERE {$so_scope} AND {$img_nonempty} ORDER BY soi.id ASC LIMIT 1)";
        if ($has_ji_product_id) {
            $matched = "(SELECT soi.images FROM tbl_sale_order_items soi WHERE {$so_scope} AND {$img_nonempty} AND (
            (ji.product_id IS NOT NULL AND ji.product_id > 0 AND soi.product_id = ji.product_id)
            OR (
                (ji.product_id IS NULL OR ji.product_id = 0)
                AND LENGTH(TRIM(IFNULL(soi.barcode,''))) > 0
                AND TRIM(IFNULL(soi.barcode,'')) COLLATE utf8mb4_unicode_ci = TRIM(IFNULL({$tag_expr},'')) COLLATE utf8mb4_unicode_ci
            )
        ) ORDER BY soi.id ASC LIMIT 1)";
        } else {
            $matched = "(SELECT soi.images FROM tbl_sale_order_items soi WHERE {$so_scope} AND {$img_nonempty} AND LENGTH(TRIM(IFNULL(soi.barcode,''))) > 0 AND TRIM(IFNULL(soi.barcode,'')) COLLATE utf8mb4_unicode_ci = TRIM(IFNULL({$tag_expr},'')) COLLATE utf8mb4_unicode_ci ORDER BY soi.id ASC LIMIT 1)";
        }
        return "COALESCE({$matched}, {$fallback}) AS sale_item_images";
    }
}

if (!function_exists('jwm_sql_repair_order_item_images')) {
    function jwm_sql_repair_order_item_images($tag_expr, $has_roi_images, $has_rji_product_id) {
        if (!$has_roi_images) {
            return 'NULL AS repair_item_images';
        }
        $img_nonempty = "roi.images IS NOT NULL AND TRIM(roi.images) <> ''";
        $ro_scope = 'roi.order_id = rj.repair_order_id AND rj.repair_order_id > 0';
        $fallback = "(SELECT roi.images FROM tbl_repair_order_items roi WHERE {$ro_scope} AND {$img_nonempty} ORDER BY roi.id ASC LIMIT 1)";
        if ($has_rji_product_id) {
            $matched = "(SELECT roi.images FROM tbl_repair_order_items roi WHERE {$ro_scope} AND {$img_nonempty} AND (
            (rji.product_id IS NOT NULL AND rji.product_id > 0 AND roi.product_id = rji.product_id)
            OR (
                (rji.product_id IS NULL OR rji.product_id = 0)
                AND LENGTH(TRIM(IFNULL(roi.barcode,''))) > 0
                AND TRIM(IFNULL(roi.barcode,'')) COLLATE utf8mb4_unicode_ci = TRIM(IFNULL({$tag_expr},'')) COLLATE utf8mb4_unicode_ci
            )
        ) ORDER BY roi.id ASC LIMIT 1)";
        } else {
            $matched = "(SELECT roi.images FROM tbl_repair_order_items roi WHERE {$ro_scope} AND {$img_nonempty} AND LENGTH(TRIM(IFNULL(roi.barcode,''))) > 0 AND TRIM(IFNULL(roi.barcode,'')) COLLATE utf8mb4_unicode_ci = TRIM(IFNULL({$tag_expr},'')) COLLATE utf8mb4_unicode_ci ORDER BY roi.id ASC LIMIT 1)";
        }
        return "COALESCE({$matched}, {$fallback}) AS repair_item_images";
    }
}

if (!function_exists('jwm_mat_doc_numbers')) {
    /** @param array<int,array<string,mixed>> $docs */
    function jwm_mat_doc_numbers(array $docs, $no_key) {
        if (empty($docs)) {
            return '';
        }
        $nums = [];
        foreach ($docs as $d) {
            $n = trim((string) ($d[$no_key] ?? ''));
            if ($n !== '') {
                $nums[] = $n;
            }
        }
        return implode(', ', array_unique($nums));
    }
}
