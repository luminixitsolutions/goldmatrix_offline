<?php
/** Dashboard hero image columns on tbl_metal (same semantics as tbl_carat). */
if (!function_exists('auragold_ensure_tbl_metal_dashboard_images')) {
    function auragold_ensure_tbl_metal_dashboard_images($conn): void
    {
        if (!$conn || !function_exists('auragold_tbl_has_column')) {
            return;
        }
        $t = 'tbl_metal';
        if (!auragold_tbl_has_column($conn, $t, 'dashboard_image_path')) {
            @mysqli_query(
                $conn,
                "ALTER TABLE `{$t}` ADD COLUMN `dashboard_image_path` VARCHAR(512) NULL DEFAULT NULL COMMENT 'Relative to admin/' AFTER `system_name`"
            );
        }
        if (!auragold_tbl_has_column($conn, $t, 'dashboard_image_url')) {
            @mysqli_query(
                $conn,
                "ALTER TABLE `{$t}` ADD COLUMN `dashboard_image_url` VARCHAR(1024) NULL DEFAULT NULL AFTER `dashboard_image_path`"
            );
        }
        if (!auragold_tbl_has_column($conn, $t, 'show_on_dashboard')) {
            @mysqli_query(
                $conn,
                "ALTER TABLE `{$t}` ADD COLUMN `show_on_dashboard` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Live rates dashboard card + tabs' AFTER `dashboard_image_url`"
            );
        }
    }
}
