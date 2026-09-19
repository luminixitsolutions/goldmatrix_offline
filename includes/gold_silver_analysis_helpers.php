<?php

if (!function_exists('stock_analysis_row_col')) {
    function stock_analysis_row_col(array $row, string $name): ?float
    {
        foreach ($row as $k => $v) {
            if (strcasecmp((string) $k, $name) === 0) {
                return ($v === null || $v === '') ? null : (float) $v;
            }
        }
        return null;
    }
}

if (!function_exists('stock_analysis_row_string')) {
    function stock_analysis_row_string(array $row, string $name): string
    {
        foreach ($row as $k => $v) {
            if (strcasecmp((string) $k, $name) === 0) {
                return $v === null || $v === '' ? '' : (string) $v;
            }
        }
        return '';
    }
}

if (!function_exists('gsa_format_qty_cell')) {
    function gsa_format_qty_cell($n, int $decimals): string
    {
        $x = (float) $n;
        if (!is_finite($x)) {
            $x = 0.0;
        }
        if ($x < 0) {
            return '(' . number_format(abs($x), $decimals, '.', '') . ')';
        }
        return number_format($x, $decimals, '.', '');
    }
}

if (!function_exists('gsa_cs_attach_image_url_map')) {
    /**
     * @param array<int, array<string, mixed>> $stock_data
     * @return array<string, string>
     */
    function gsa_cs_attach_image_url_map($conn, array $stock_data): array
    {
        $map = [];
        if (!($conn instanceof mysqli) || $stock_data === []) {
            return $map;
        }
        $chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_stock_journal_images'");
        $ok = $chk && mysqli_num_rows($chk) > 0;
        if ($chk) {
            mysqli_free_result($chk);
        }
        if (!$ok) {
            return $map;
        }

        $pid_set = [];
        $bid_set = [];
        foreach ($stock_data as $row) {
            $pid = (int) ($row['product_id'] ?? 0);
            $bid = (int) ($row['branch_id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            $pid_set[$pid] = true;
            if ($bid > 0) {
                $bid_set[$bid] = true;
            }
        }
        if ($pid_set === []) {
            return $map;
        }

        $pid_sql = implode(',', array_map('intval', array_keys($pid_set)));
        $bid_sql = $bid_set !== []
            ? (' AND s.branch_id IN (' . implode(',', array_map('intval', array_keys($bid_set))) . ')')
            : '';

        $sql = "
        SELECT s.product_id, s.branch_id, s.metal_id,
               SUBSTRING_INDEX(GROUP_CONCAT(imgs.image_path ORDER BY imgs.id ASC SEPARATOR ','), ',', 1) AS image_path
        FROM tbl_stock s
        INNER JOIN tbl_stock_journal_images imgs
            ON TRIM(imgs.barcode_no) <> ''
            AND TRIM(imgs.barcode_no) = TRIM(COALESCE(s.barcode, ''))
        WHERE s.status = 1
          AND s.product_id IN ($pid_sql)
          $bid_sql
          AND s.barcode IS NOT NULL AND TRIM(s.barcode) <> ''
        GROUP BY s.product_id, s.branch_id, s.metal_id
    ";
        $rows = getList($sql);
        if (!is_array($rows)) {
            return $map;
        }
        foreach ($rows as $r) {
            $path = trim((string) ($r['image_path'] ?? ''));
            if ($path === '') {
                continue;
            }
            $url = function_exists('auragold_uploads_public_url')
                ? auragold_uploads_public_url($path)
                : $path;
            if ($url === '') {
                continue;
            }
            $key = (int) ($r['product_id'] ?? 0) . ':' . (int) ($r['branch_id'] ?? 0) . ':' . (int) ($r['metal_id'] ?? 0);
            $map[$key] = $url;
        }
        return $map;
    }
}
