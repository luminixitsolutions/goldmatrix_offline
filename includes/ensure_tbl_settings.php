<?php
/**
 * Ensures tbl_settings exists (barcode row + branch_password_hash) for Add Branch APIs.
 * Safe to call on every request; creates table/column only when missing.
 */
if (!function_exists('auragold_ensure_tbl_settings_branch_password')) {
    /** @return bool true if table is usable */
    function auragold_ensure_tbl_settings_branch_password($link) {
        if (!$link) {
            return false;
        }

        $r = @mysqli_query($link, "SHOW TABLES LIKE 'tbl_settings'");
        $exists = $r && mysqli_num_rows($r) > 0;
        if ($r) {
            mysqli_free_result($r);
        }

        if (!$exists) {
            $sql = "CREATE TABLE IF NOT EXISTS `tbl_settings` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `barcode_prefix` varchar(50) DEFAULT 'RG' COMMENT 'Prefix for generated barcodes',
              `barcode_digit_length` int(11) DEFAULT 5 COMMENT 'Digits after prefix',
              `branch_password_hash` varchar(255) DEFAULT NULL COMMENT 'bcrypt for + Branch modal',
              `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
              `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            if (!@mysqli_query($link, $sql)) {
                return false;
            }
        } else {
            $c = @mysqli_query($link, "SHOW COLUMNS FROM `tbl_settings` LIKE 'branch_password_hash'");
            $hasCol = $c && mysqli_num_rows($c) > 0;
            if ($c) {
                mysqli_free_result($c);
            }
            if (!$hasCol) {
                @mysqli_query(
                    $link,
                    "ALTER TABLE `tbl_settings` ADD COLUMN `branch_password_hash` varchar(255) DEFAULT NULL COMMENT 'bcrypt for + Branch modal'"
                );
            }
        }

        @mysqli_query(
            $link,
            "INSERT IGNORE INTO `tbl_settings` (`id`, `barcode_prefix`, `barcode_digit_length`) VALUES (1, 'RG', 5)"
        );

        // Default gate password: Admin@123 (change in production)
        $defaultHash = '$2y$10$7Ib8O3CuNPbi.5DSslAwL.4jEau/91VEAazYAisqFltOEhHcSyztq';
        @mysqli_query(
            $link,
            "UPDATE `tbl_settings` SET `branch_password_hash` = '" . mysqli_real_escape_string($link, $defaultHash) . "' WHERE (`branch_password_hash` IS NULL OR `branch_password_hash` = '') LIMIT 1"
        );

        return true;
    }
}
