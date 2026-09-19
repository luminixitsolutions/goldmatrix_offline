<?php

if (!function_exists('auragold_ensure_tbl_barcode_scan_log')) {
    /**
     * RFID / barcode scan audit log (rfid-barcode-scan.php + barcode-scan-report.php).
     */
    function auragold_ensure_tbl_barcode_scan_log($conn): bool
    {
        if (!$conn || !($conn instanceof mysqli)) {
            return false;
        }
        static $done = false;
        if ($done) {
            return true;
        }
        $sql = "CREATE TABLE IF NOT EXISTS `tbl_barcode_scan_log` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `branch_id` int unsigned DEFAULT NULL,
            `user_id` int unsigned DEFAULT NULL,
            `user_name` varchar(191) DEFAULT NULL,
            `scan_code` varchar(128) NOT NULL DEFAULT '',
            `scan_status` enum('matched','unknown') NOT NULL DEFAULT 'matched',
            `barcode` varchar(128) DEFAULT NULL,
            `rfid_code` varchar(128) DEFAULT NULL,
            `product_code` varchar(64) DEFAULT NULL,
            `product_name` varchar(255) DEFAULT NULL,
            `article` varchar(255) DEFAULT NULL,
            `metal_name` varchar(128) DEFAULT NULL,
            `branch_name` varchar(128) DEFAULT NULL,
            `location` varchar(255) DEFAULT NULL,
            `qty` decimal(18,4) DEFAULT NULL,
            `gross_wt` decimal(18,4) DEFAULT NULL,
            `net_wt` decimal(18,4) DEFAULT NULL,
            `final_wt` decimal(18,4) DEFAULT NULL,
            `voucher_type` varchar(128) DEFAULT NULL,
            `invoice_no` varchar(128) DEFAULT NULL,
            `scan_date` date NOT NULL,
            `scan_time` time NOT NULL,
            `scanned_at` datetime NOT NULL,
            `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_bsl_scan_date` (`scan_date`),
            KEY `idx_bsl_barcode` (`barcode`),
            KEY `idx_bsl_scanned_at` (`scanned_at`),
            KEY `idx_bsl_branch_id` (`branch_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $ok = (bool) @mysqli_query($conn, $sql);
        if ($ok) {
            $done = true;
        }
        return $ok;
    }
}
