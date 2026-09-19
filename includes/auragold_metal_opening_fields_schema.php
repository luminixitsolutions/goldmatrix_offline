<?php
/** Product Opening label + sort order on tbl_metal. */
if (!function_exists('auragold_ensure_tbl_metal_opening_fields')) {
    function auragold_ensure_tbl_metal_opening_fields($conn): void
    {
        if (!$conn || !function_exists('auragold_tbl_has_column')) {
            return;
        }
        $t = 'tbl_metal';
        if (!auragold_tbl_has_column($conn, $t, 'product_opening_name')) {
            @mysqli_query(
                $conn,
                "ALTER TABLE `{$t}` ADD COLUMN `product_opening_name` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Label on Product Opening metal tabs' AFTER `system_name`"
            );
        }
        if (!auragold_tbl_has_column($conn, $t, 'order_no')) {
            @mysqli_query(
                $conn,
                "ALTER TABLE `{$t}` ADD COLUMN `order_no` INT NOT NULL DEFAULT 0 COMMENT 'Sort order for Product Opening tabs' AFTER `product_opening_name`"
            );
        }
        auragold_ensure_opening_stock_weight_precision($conn);
    }
}

if (!function_exists('auragold_ensure_opening_stock_weight_precision')) {
    /**
     * Opening stock weights need 6 decimal places (e.g. 10.257859).
     * Older columns were DECIMAL(15,4) and rounded to 10.2579.
     */
    function auragold_ensure_opening_stock_weight_precision($conn): void
    {
        if (!$conn instanceof mysqli) {
            return;
        }
        $targets = [
            ['tbl_product_characteristics', 'opening_weight'],
            ['tbl_product_characteristics', 'final_weight'],
            ['tbl_stock', 'opening_weight'],
            ['tbl_stock', 'current_weight'],
            ['tbl_stock_journal', 'gross_weight'],
            ['tbl_stock_journal', 'net_weight'],
            ['tbl_stock_journal', 'less_weight'],
            ['tbl_stock_journal', 'purity_weight'],
            ['tbl_stock_journal', 'pure_weight'],
            ['tbl_stock_journal', 'final_weight'],
        ];
        foreach ($targets as $pair) {
            $table = $pair[0];
            $col = $pair[1];
            $chk = @mysqli_query($conn, 'SHOW COLUMNS FROM `' . $table . '` LIKE \'' . $col . '\'');
            if (!$chk || mysqli_num_rows($chk) === 0) {
                if ($chk) {
                    mysqli_free_result($chk);
                }
                continue;
            }
            $info = mysqli_fetch_assoc($chk);
            mysqli_free_result($chk);
            $type = strtolower((string) ($info['Type'] ?? ''));
            $scale = 0;
            if (preg_match('/decimal\s*\(\s*\d+\s*,\s*(\d+)\s*\)/', $type, $m)) {
                $scale = (int) $m[1];
            }
            if ($scale >= 6) {
                continue;
            }
            $defaultSql = 'DEFAULT NULL';
            if (strtoupper((string) ($info['Null'] ?? 'YES')) === 'NO') {
                $defaultSql = 'NOT NULL DEFAULT 0.000000';
            } elseif ($info['Default'] !== null && $info['Default'] !== '') {
                $defaultSql = 'NULL DEFAULT 0.000000';
            }
            @mysqli_query(
                $conn,
                'ALTER TABLE `' . $table . '` MODIFY COLUMN `' . $col . '` DECIMAL(18,6) ' . $defaultSql
            );
        }
    }
}

if (!function_exists('auragold_metal_product_opening_label')) {
    function auragold_metal_product_opening_label(array $row): string
    {
        $po = trim((string) ($row['product_opening_name'] ?? ''));
        if ($po !== '') {
            return $po;
        }
        return trim((string) ($row['display_name'] ?? ''));
    }
}
