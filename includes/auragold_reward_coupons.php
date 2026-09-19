<?php
/**
 * Reward coupons master (branch-scoped) — Set Software → Reward Point → Coupons tab.
 */

if (!function_exists('auragold_ensure_reward_coupons_table')) {
    function auragold_ensure_reward_coupons_table($conn): bool
    {
        if (!$conn instanceof mysqli) {
            return false;
        }
        @mysqli_query(
            $conn,
            "CREATE TABLE IF NOT EXISTS `tbl_auragold_reward_coupons` (
              `id` INT NOT NULL AUTO_INCREMENT,
              `branch_id` INT NOT NULL,
              `coupon_name` VARCHAR(200) NOT NULL DEFAULT '',
              `coupon_code` VARCHAR(80) NOT NULL DEFAULT '',
              `coupon_value` DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
              `expiry_date` DATE NULL DEFAULT NULL,
              `is_active` TINYINT(1) NOT NULL DEFAULT 1,
              `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_coupon_branch` (`branch_id`),
              KEY `idx_coupon_branch_code` (`branch_id`, `coupon_code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_auragold_reward_coupons'");
        return $chk && mysqli_num_rows($chk) > 0;
    }
}

if (!function_exists('auragold_reward_coupons_count_filtered')) {
    /**
     * @param array{f_date_from:string,f_date_to:string,f_code:string,f_active_only:int} $f
     */
    function auragold_reward_coupons_count_filtered($conn, int $branchId, array $f): int
    {
        if (!$conn instanceof mysqli || $branchId <= 0 || !auragold_ensure_reward_coupons_table($conn)) {
            return 0;
        }
        $where = ' WHERE branch_id = ' . (int) $branchId;
        list($clause) = auragold_reward_coupons_filter_sql_clause($conn, $f);

        $r = @getRecord("SELECT COUNT(*) AS cnt FROM tbl_auragold_reward_coupons {$where}{$clause}");

        return ($r && isset($r['cnt'])) ? (int) $r['cnt'] : 0;
    }
}

/**
 * @param array{f_date_from:string,f_date_to:string,f_code:string,f_active_only:int} $f
 * @return array{0:string,1:string} SQL AND clause fragment (starts with AND...), ESCAPED LIKE pattern token for binding note
 */
if (!function_exists('auragold_reward_coupons_filter_sql_clause')) {
    function auragold_reward_coupons_filter_sql_clause($conn, array $f): array
    {
        $clause = '';
        $code = isset($f['f_code']) ? trim((string) $f['f_code']) : '';
        if ($code !== '') {
            $e = mysqli_real_escape_string($conn, $code);
            $clause .= " AND coupon_code LIKE '%{$e}%' ";
        }
        if (!empty($f['f_active_only'])) {
            $clause .= ' AND is_active = 1 ';
        }
        $df = isset($f['f_date_from']) ? trim((string) $f['f_date_from']) : '';
        $dt = isset($f['f_date_to']) ? trim((string) $f['f_date_to']) : '';
        if ($df !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $df)) {
            $clause .= " AND (expiry_date IS NOT NULL AND expiry_date >= '" . mysqli_real_escape_string($conn, $df) . "') ";
        }
        if ($dt !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dt)) {
            $clause .= " AND (expiry_date IS NOT NULL AND expiry_date <= '" . mysqli_real_escape_string($conn, $dt) . "') ";
        }

        return [$clause];
    }
}

if (!function_exists('auragold_reward_coupons_fetch_page')) {
    /**
     * @param array{f_date_from:string,f_date_to:string,f_code:string,f_active_only:int} $f
     * @return list<array<string,mixed>>
     */
    function auragold_reward_coupons_fetch_page($conn, int $branchId, array $f, int $page, int $pageSize): array
    {
        if (!$conn instanceof mysqli || $branchId <= 0 || !auragold_ensure_reward_coupons_table($conn)) {
            return [];
        }
        $page = max(1, $page);
        $pageSize = max(1, min(100, $pageSize));
        $off = ($page - 1) * $pageSize;
        $where = ' WHERE branch_id = ' . (int) $branchId;
        list($clause) = auragold_reward_coupons_filter_sql_clause($conn, $f);

        $sql = "SELECT id, coupon_name, coupon_code, coupon_value, expiry_date, is_active, created_at, updated_at
                FROM tbl_auragold_reward_coupons {$where}{$clause}
                ORDER BY id DESC
                LIMIT " . (int) $off . ',' . (int) $pageSize;

        $rows = getList($sql);

        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('auragold_reward_coupons_get_branch_row')) {
    function auragold_reward_coupons_get_branch_row($conn, int $branchId, int $id): ?array
    {
        if (!$conn instanceof mysqli || $branchId <= 0 || $id <= 0) {
            return null;
        }
        auragold_ensure_reward_coupons_table($conn);
        $row = @getRecord('SELECT id, coupon_name, coupon_code, coupon_value, expiry_date, is_active FROM tbl_auragold_reward_coupons WHERE branch_id = ' . (int) $branchId . ' AND id = ' . (int) $id . ' LIMIT 1');

        return is_array($row) ? $row : null;
    }
}
