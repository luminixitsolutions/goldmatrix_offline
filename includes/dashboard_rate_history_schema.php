<?php
/**
 * Append-only history for dashboard metal rates (charts / yesterday vs today).
 * Rates are stored in base currency (same as tbl_dashboard_metal_rates.rate).
 */

function auragold_ensure_tbl_dashboard_metal_rate_history($conn)
{
    if (!$conn || !is_object($conn)) {
        return;
    }
    static $done = [];
    $key = spl_object_hash($conn);
    if (!empty($done[$key])) {
        return;
    }

    if (!function_exists('auragold_tbl_has_column')) {
        require_once __DIR__ . '/auragold_branch_data_scope.php';
    }

    $t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_dashboard_metal_rate_history'");
    $table_exists = $t && mysqli_num_rows($t) > 0;
    if ($t) {
        mysqli_free_result($t);
    }

    if (!$table_exists) {
        $sql = "
            CREATE TABLE IF NOT EXISTS `tbl_dashboard_metal_rate_history` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `branch_id` int(11) NOT NULL DEFAULT 0,
                `metal` varchar(24) NOT NULL DEFAULT '',
                `carat_label` varchar(64) NOT NULL DEFAULT '',
                `rate` decimal(18,6) NOT NULL DEFAULT 0.000000,
                `recorded_at` datetime NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_branch_metal_time` (`branch_id`, `metal`, `recorded_at`),
                KEY `idx_metal_time` (`metal`, `recorded_at`),
                KEY `idx_metal_carat_time` (`metal`, `carat_label`, `recorded_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
        @mysqli_query($conn, $sql);
    }

    if ($table_exists && !auragold_tbl_has_column($conn, 'tbl_dashboard_metal_rate_history', 'branch_id')) {
        @mysqli_query(
            $conn,
            'ALTER TABLE tbl_dashboard_metal_rate_history ADD COLUMN branch_id INT NOT NULL DEFAULT 0 AFTER id'
        );
        @mysqli_query(
            $conn,
            'ALTER TABLE tbl_dashboard_metal_rate_history ADD KEY idx_branch_metal_time (branch_id, metal, recorded_at)'
        );
    }

    $done[$key] = true;
}

/**
 * @param array<int,array{carat:string,rate:mixed}> $rows_in
 */
function auragold_dashboard_append_metal_rate_history($conn, string $metal, array $rows_in, int $branch_id = 0): void
{
    if (!$conn || $metal === '' || !count($rows_in)) {
        return;
    }
    auragold_ensure_tbl_dashboard_metal_rate_history($conn);

    $m = mysqli_real_escape_string($conn, $metal);
    $bid = max(0, (int) $branch_id);
    $now = date('Y-m-d H:i:s');
    $has_branch = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_dashboard_metal_rate_history', 'branch_id');

    foreach ($rows_in as $row) {
        if (!is_array($row)) {
            continue;
        }
        $carat = isset($row['carat']) ? trim((string) $row['carat']) : '';
        if ($carat === '') {
            continue;
        }
        $rate_val = (float) str_replace([',', ' '], '', (string) ($row['rate'] ?? '0'));
        $c = mysqli_real_escape_string($conn, $carat);
        if ($has_branch) {
            $sql = "
                INSERT INTO tbl_dashboard_metal_rate_history (branch_id, metal, carat_label, rate, recorded_at)
                VALUES ($bid, '$m', '$c', $rate_val, '$now')
            ";
        } else {
            $sql = "
                INSERT INTO tbl_dashboard_metal_rate_history (metal, carat_label, rate, recorded_at)
                VALUES ('$m', '$c', $rate_val, '$now')
            ";
        }
        @mysqli_query($conn, $sql);
    }
}
