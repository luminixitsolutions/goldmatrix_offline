<?php
/**
 * Ensures tbl_sales_team_inventory_assign exists and has branch_id (assignments are per sales person + branch).
 */
function auragold_ensure_sales_team_inventory_assign_schema(mysqli $conn) {
    $create = "CREATE TABLE IF NOT EXISTS `tbl_sales_team_inventory_assign` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `sales_person` varchar(255) NOT NULL,
  `branch_id` int unsigned NOT NULL DEFAULT 1,
  `barcode_no` varchar(128) NOT NULL,
  `row_json` longtext DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_barcode_global` (`barcode_no`(100)),
  KEY `idx_sales_person` (`sales_person`(191)),
  KEY `idx_sp_branch` (`sales_person`(100), `branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    @mysqli_query($conn, $create);

    $chk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sales_team_inventory_assign LIKE 'branch_id'");
    if ($chk && mysqli_num_rows($chk) === 0) {
        @mysqli_query(
            $conn,
            "ALTER TABLE tbl_sales_team_inventory_assign ADD COLUMN branch_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER sales_person"
        );
    }
    if ($chk) {
        mysqli_free_result($chk);
    }
}
