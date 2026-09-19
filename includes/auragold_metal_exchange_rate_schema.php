<?php
/**
 * Ensure tbl_metal_exchange_rate exists (daily metal rates by currency / unit).
 */

if (!function_exists('auragold_ensure_metal_exchange_rate_table')) {
    function auragold_ensure_metal_exchange_rate_table($conn): bool
    {
        if (!($conn instanceof mysqli)) {
            return false;
        }
        static $done = false;
        if ($done) {
            return true;
        }
        $chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_metal_exchange_rate'");
        if ($chk && mysqli_num_rows($chk) > 0) {
            mysqli_free_result($chk);
            $done = true;
            return true;
        }
        if ($chk) {
            mysqli_free_result($chk);
        }
        $ok = @mysqli_query($conn, "
            CREATE TABLE IF NOT EXISTS `tbl_metal_exchange_rate` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `branch_id` int(11) DEFAULT NULL COMMENT 'FK tbl_branches.id',
                `rate_date` date NOT NULL,
                `metal_id` int(11) NOT NULL,
                `ounce_rate` decimal(18,6) NOT NULL DEFAULT 0.000000,
                `unit_id` int(11) DEFAULT NULL,
                `unit_conversion_rate` decimal(18,6) NOT NULL DEFAULT 31.103500,
                `currency_id` int(11) NOT NULL,
                `rate_at` decimal(18,6) NOT NULL DEFAULT 1.000000,
                `metal_rate_per_gram` decimal(18,6) NOT NULL DEFAULT 0.000000,
                `description` varchar(150) DEFAULT NULL,
                `status` tinyint(4) NOT NULL DEFAULT 1,
                `created_by` int(11) DEFAULT NULL,
                `modified_by` int(11) DEFAULT NULL,
                `created_at` timestamp NULL DEFAULT current_timestamp(),
                `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
                PRIMARY KEY (`id`),
                KEY `idx_mer_branch` (`branch_id`),
                KEY `idx_mer_date` (`rate_date`),
                KEY `idx_mer_metal` (`metal_id`),
                KEY `idx_mer_currency` (`currency_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        if ($ok && function_exists('auragold_ensure_branch_id_on_settings_tables')) {
            auragold_ensure_branch_id_on_settings_tables($conn);
        }
        $done = (bool) $ok;
        return $done;
    }
}

if (!function_exists('auragold_calc_metal_rate_per_gram')) {
    function auragold_calc_metal_rate_per_gram(float $ounceRate, float $unitConv, float $rateAt, float $currencyRate): float
    {
        if ($ounceRate <= 0 || $unitConv <= 0) {
            return 0.0;
        }
        if ($rateAt <= 0) {
            $rateAt = 1.0;
        }
        if ($currencyRate <= 0) {
            $currencyRate = 1.0;
        }
        return ($ounceRate / $unitConv) * $rateAt * $currencyRate;
    }
}
