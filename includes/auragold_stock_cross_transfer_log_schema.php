<?php
/**
 * Source-DB audit log for inter-database stock transfers (tbl_branches.db_name).
 *
 * @return bool true if table exists or was created
 */
function auragold_ensure_stock_cross_transfer_log_table(mysqli $conn): bool {
    $sql = "CREATE TABLE IF NOT EXISTS `tbl_stock_cross_transfer_log` (
      `id` bigint NOT NULL AUTO_INCREMENT,
      `source_branch_id` int NOT NULL,
      `destination_branch_id` int NOT NULL,
      `source_db` varchar(191) NOT NULL DEFAULT '',
      `destination_db` varchar(191) NOT NULL DEFAULT '',
      `barcode` varchar(100) DEFAULT NULL,
      `stock_id` int NOT NULL COMMENT 'Source tbl_stock.id at time of transfer (cleared inward line)',
      `outward_stock_id` int DEFAULT NULL COMMENT 'Source tbl_stock.id of outward row (links history)',
      `destination_stock_id` bigint DEFAULT NULL COMMENT 'New tbl_stock.id on destination DB when applicable',
      `move_qty` decimal(15,4) DEFAULT NULL,
      `move_wt` decimal(15,4) DEFAULT NULL,
      `transfer_date` date DEFAULT NULL,
      `created_by` int DEFAULT NULL,
      `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `status` varchar(32) NOT NULL DEFAULT 'completed',
      PRIMARY KEY (`id`),
      KEY `idx_src_stock` (`source_db`,`stock_id`),
      KEY `idx_outward_stock` (`outward_stock_id`),
      KEY `idx_dest_bc` (`destination_db`,`destination_branch_id`,`barcode`(32)),
      KEY `idx_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    if (!mysqli_query($conn, $sql)) {
        return false;
    }
    $chk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_stock_cross_transfer_log LIKE 'outward_stock_id'");
    $hasOut = ($chk && mysqli_num_rows($chk) > 0);
    if ($chk) {
        mysqli_free_result($chk);
    }
    if (!$hasOut) {
        @mysqli_query(
            $conn,
            "ALTER TABLE tbl_stock_cross_transfer_log ADD COLUMN outward_stock_id INT NULL DEFAULT NULL "
            . "COMMENT 'Source tbl_stock outward id' AFTER stock_id"
        );
        @mysqli_query($conn, 'ALTER TABLE tbl_stock_cross_transfer_log ADD KEY idx_outward_stock (outward_stock_id)');
    }
    return true;
}
