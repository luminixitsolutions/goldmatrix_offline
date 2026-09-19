<?php
/**
 * Adds optional dashboard image fields on tbl_carat (upload path + external URL).
 */
if (!function_exists('auragold_ensure_tbl_carat_dashboard_images')) {
    function auragold_ensure_tbl_carat_dashboard_images($conn): void
    {
        if (!$conn || !function_exists('auragold_tbl_has_column')) {
            return;
        }
        $t = 'tbl_carat';
        if (!auragold_tbl_has_column($conn, $t, 'dashboard_image_path')) {
            @mysqli_query(
                $conn,
                "ALTER TABLE `{$t}` ADD COLUMN `dashboard_image_path` VARCHAR(512) NULL DEFAULT NULL COMMENT 'Relative to admin/, e.g. uploads/metal-dashboard/x.jpg' AFTER `description`"
            );
        }
        if (!auragold_tbl_has_column($conn, $t, 'dashboard_image_url')) {
            @mysqli_query(
                $conn,
                "ALTER TABLE `{$t}` ADD COLUMN `dashboard_image_url` VARCHAR(1024) NULL DEFAULT NULL COMMENT 'External image URL (optional)' AFTER `dashboard_image_path`"
            );
        }
    }
}
