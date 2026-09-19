<?php

/**
 * Purchase invoice line photos → tbl_stock_journal_images (keyed by stock barcode).
 * Diamond & Stones / Gold & Silver stock lists read images from that table by barcode.
 */

if (!function_exists('auragold_pi_ensure_stock_journal_images_table')) {
    function auragold_pi_ensure_stock_journal_images_table(mysqli $conn): bool
    {
        static $done = null;
        if ($done !== null) {
            return $done;
        }
        $create = "CREATE TABLE IF NOT EXISTS `tbl_stock_journal_images` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `item_id` int(11) NOT NULL DEFAULT 0,
            `barcode_no` varchar(100) NOT NULL DEFAULT '',
            `image_path` varchar(500) NOT NULL DEFAULT '',
            `created_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_barcode_no` (`barcode_no`),
            KEY `idx_item_id` (`item_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $done = (bool) @mysqli_query($conn, $create);
        return $done;
    }
}

if (!function_exists('auragold_pi_group_image_collect_sources')) {
    /**
     * @return array{urls: list<string>, primary_index: int}
     */
    function auragold_pi_group_image_collect_sources($group_image): array
    {
        $out = ['urls' => [], 'primary_index' => 0];
        if ($group_image === null || $group_image === '') {
            return $out;
        }
        $trimmed = is_string($group_image) ? trim($group_image) : '';
        if ($trimmed === '' || $trimmed === '{}' || strtolower($trimmed) === 'null') {
            return $out;
        }
        if ($trimmed[0] === '{') {
            $dec = @json_decode($trimmed, true);
            if (is_array($dec)) {
                if (!empty($dec['images']) && is_array($dec['images'])) {
                    foreach ($dec['images'] as $u) {
                        $s = trim((string) $u);
                        if ($s !== '') {
                            $out['urls'][] = $s;
                        }
                    }
                    $p = isset($dec['primary']) ? (string) $dec['primary'] : '';
                    if ($p !== '') {
                        $idx = array_search($p, $out['urls'], true);
                        $out['primary_index'] = ($idx !== false) ? (int) $idx : 0;
                    }
                } elseif (!empty($dec['primary'])) {
                    $out['urls'] = [trim((string) $dec['primary'])];
                }
            }
            return $out;
        }
        if (preg_match('/^data:image\//', $trimmed) || preg_match('#^https?://#i', $trimmed) || stripos($trimmed, 'uploads/') !== false) {
            $out['urls'] = [$trimmed];
        }
        return $out;
    }
}

if (!function_exists('auragold_pi_image_source_to_relative_path')) {
    function auragold_pi_image_source_to_relative_path(string $src): string
    {
        $src = trim($src);
        if ($src === '' || preg_match('/^data:image\//', $src)) {
            return '';
        }
        if (preg_match('#^https?://#i', $src)) {
            $path = parse_url($src, PHP_URL_PATH);
            if (!is_string($path) || $path === '') {
                return '';
            }
            $path = ltrim(str_replace('\\', '/', $path), '/');
            $pos = stripos($path, 'uploads/');
            return ($pos !== false) ? substr($path, $pos) : '';
        }
        $r = ltrim(str_replace('\\', '/', $src), '/');
        if (function_exists('auragold_uploads_public_rel')) {
            $r = auragold_uploads_public_rel($r);
        } elseif (stripos($r, 'admin/uploads/') === 0) {
            $r = substr($r, 6);
        }
        if (stripos($r, 'uploads/') === 0) {
            return $r;
        }
        $pos = stripos($r, 'uploads/');
        return ($pos !== false) ? substr($r, $pos) : '';
    }
}

if (!function_exists('auragold_pi_save_data_url_image')) {
    function auragold_pi_save_data_url_image(string $data_url, int $invoice_id, int $item_id, int $index): string
    {
        if (!preg_match('/^data:image\/(\w+);base64,(.+)$/s', trim($data_url), $m)) {
            return '';
        }
        $ext = strtolower($m[1]);
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }
        $safe_ext = in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true) ? $ext : 'png';
        $root = dirname(__DIR__);
        $upload_base = $root . '/uploads/stock_journal';
        if (!is_dir($upload_base)) {
            @mkdir($root . '/uploads', 0755, true);
            @mkdir($upload_base, 0755, true);
        }
        $filename = 'pi_' . (int) $invoice_id . '_' . (int) $item_id . '_' . (int) $index . '.' . $safe_ext;
        $full = $upload_base . '/' . $filename;
        $b64 = preg_replace('/\s+/', '', $m[2]);
        $bin = @base64_decode($b64, true);
        if ($bin === false || @file_put_contents($full, $bin) === false) {
            return '';
        }
        return 'uploads/stock_journal/' . $filename;
    }
}

if (!function_exists('auragold_purchase_invoice_process_group_image')) {
    /**
     * Normalize PI group_image to stored relative paths under uploads/.
     *
     * @return string JSON {"primary":"uploads/...","images":["..."]} or ''
     */
    function auragold_purchase_invoice_process_group_image($group_image, int $invoice_id, int $item_id): string
    {
        if ($invoice_id <= 0 || $item_id <= 0) {
            return '';
        }
        $collected = auragold_pi_group_image_collect_sources($group_image);
        if (empty($collected['urls'])) {
            return '';
        }
        $paths = [];
        foreach ($collected['urls'] as $i => $src) {
            $src = trim((string) $src);
            if ($src === '') {
                continue;
            }
            if (preg_match('/^data:image\//', $src)) {
                $rel = auragold_pi_save_data_url_image($src, $invoice_id, $item_id, $i);
            } else {
                $rel = auragold_pi_image_source_to_relative_path($src);
            }
            if ($rel !== '') {
                $paths[] = $rel;
            }
        }
        if (empty($paths)) {
            return '';
        }
        $pi = (int) $collected['primary_index'];
        if ($pi < 0 || $pi >= count($paths)) {
            $pi = 0;
        }
        return json_encode(['primary' => $paths[$pi], 'images' => $paths], JSON_UNESCAPED_SLASHES);
    }
}

if (!function_exists('auragold_sync_purchase_barcode_images_to_journal')) {
    /**
     * Replace journal images for a barcode with paths from processed group_image JSON.
     * When paths JSON is empty, existing journal images are left unchanged.
     */
    function auragold_sync_purchase_barcode_images_to_journal(mysqli $conn, string $barcode, string $paths_json, int $item_id = 0): bool
    {
        $barcode = trim($barcode);
        if ($barcode === '' || trim($paths_json) === '') {
            return false;
        }
        if (!auragold_pi_ensure_stock_journal_images_table($conn)) {
            return false;
        }
        $dec = @json_decode($paths_json, true);
        if (!is_array($dec) || empty($dec['images']) || !is_array($dec['images'])) {
            return false;
        }
        $paths = [];
        foreach ($dec['images'] as $p) {
            $p = trim((string) $p);
            if ($p !== '') {
                $paths[] = $p;
            }
        }
        if (empty($paths)) {
            return false;
        }
        $esc = mysqli_real_escape_string($conn, $barcode);
        if (!@mysqli_query($conn, "DELETE FROM tbl_stock_journal_images WHERE TRIM(barcode_no) = TRIM('$esc')")) {
            return false;
        }
        $iid = max(0, (int) $item_id);
        foreach ($paths as $rel) {
            $path_esc = mysqli_real_escape_string($conn, $rel);
            @mysqli_query(
                $conn,
                "INSERT INTO tbl_stock_journal_images (item_id, barcode_no, image_path, created_at) VALUES ($iid, '$esc', '$path_esc', NOW())"
            );
        }
        return true;
    }
}
