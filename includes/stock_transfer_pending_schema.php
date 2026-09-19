<?php
/**
 * In-transit stock after transfer Save; Receive posts into tbl_stock.
 */
function auragold_ensure_stock_transfer_pending_table(mysqli $conn) {
    $sql = "CREATE TABLE IF NOT EXISTS `tbl_stock_transfer_pending` (
      `id` int NOT NULL AUTO_INCREMENT,
      `from_branch_id` int NOT NULL,
      `to_branch_id` int NOT NULL,
      `product_id` int NOT NULL,
      `product_characteristic_id` int DEFAULT NULL,
      `barcode` varchar(100) DEFAULT NULL,
      `metal_id` int DEFAULT NULL,
      `opening_purity` decimal(15,4) DEFAULT NULL,
      `move_qty` decimal(15,4) NOT NULL,
      `move_wt` decimal(15,4) NOT NULL,
      `rate` decimal(15,4) DEFAULT NULL,
      `value` decimal(15,4) DEFAULT NULL,
      `transfer_date` date DEFAULT NULL,
      `source_stock_id` int DEFAULT NULL,
      `outward_stock_id` int DEFAULT NULL,
      `status` varchar(20) NOT NULL DEFAULT 'pending',
      `received_stock_id` int DEFAULT NULL,
      `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
      `received_at` datetime DEFAULT NULL,
      PRIMARY KEY (`id`),
      KEY `idx_status_to` (`status`,`to_branch_id`),
      KEY `idx_outward` (`outward_stock_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    return (bool) mysqli_query($conn, $sql);
}

if (!function_exists('auragold_stock_transfer_central_mysqli')) {
    /**
     * MySQL connection where tbl_stock and tbl_stock_transfer_pending live together (branch_id on stock).
     * When the user has opened a branch database (working_db), tbl_stock for that tenant is on $conn — not
     * necessarily on $conn_master (bootstrap first-main DB can point at a different schema), so we prefer $conn.
     * Otherwise falls back to $conn_master for single-DB installs with no working_db.
     *
     * @throws RuntimeException
     */
    function auragold_stock_transfer_central_mysqli(): mysqli {
        if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE
            && !empty($_SESSION['working_db']) && is_array($_SESSION['working_db'])) {
            $dbname = trim((string) ($_SESSION['working_db']['database'] ?? $_SESSION['working_db']['db_name'] ?? ''));
            if ($dbname !== '' && !empty($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
                return $GLOBALS['conn'];
            }
        }
        if (!empty($GLOBALS['conn_master']) && $GLOBALS['conn_master'] instanceof mysqli) {
            return $GLOBALS['conn_master'];
        }
        if (!empty($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
            return $GLOBALS['conn'];
        }
        throw new RuntimeException('No mysqli connection for stock transfer');
    }
}
