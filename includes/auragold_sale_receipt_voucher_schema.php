<?php

/**
 * Sale receipt voucher header + payment lines (auto from Sale / POS Sale Invoice).
 * Idempotent: CREATE TABLE IF NOT EXISTS on the operational branch $conn.
 */
if (!function_exists('auragold_ensure_tbl_sale_receipt_vouchers')) {
    function auragold_ensure_tbl_sale_receipt_vouchers($conn): void
    {
        if (!$conn || !($conn instanceof mysqli)) {
            return;
        }
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        @mysqli_query(
            $conn,
            'CREATE TABLE IF NOT EXISTS `tbl_sale_receipt_vouchers` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `branch_id` int(11) DEFAULT NULL COMMENT \'FK tbl_branches.id\',
              `voucher_no` varchar(50) NOT NULL,
              `customer_id` int(11) DEFAULT NULL,
              `customer_name` varchar(255) NOT NULL,
              `sale_invoice_no` varchar(100) NOT NULL COMMENT \'Sale/POS invoice no\',
              `against` varchar(100) DEFAULT \'Sale Invoice\',
              `sales_person` varchar(255) DEFAULT NULL,
              `currency` varchar(10) DEFAULT \'AED\',
              `voucher_date` date NOT NULL,
              `fixing_type` varchar(50) DEFAULT \'Standard\',
              `previous_balance` decimal(15,2) DEFAULT 0.00,
              `previous_gold` decimal(10,3) DEFAULT 0.000,
              `previous_silver` decimal(10,3) DEFAULT 0.000,
              `total_amount` decimal(15,2) DEFAULT 0.00,
              `total_gold` decimal(10,3) DEFAULT 0.000,
              `total_silver` decimal(10,3) DEFAULT 0.000,
              `comment` text DEFAULT NULL,
              `status` varchar(20) DEFAULT \'saved\',
              `created_by` int(11) DEFAULT NULL,
              `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
              `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_srv_voucher_no` (`voucher_no`),
              KEY `idx_srv_sale_invoice_no` (`sale_invoice_no`),
              KEY `idx_srv_voucher_date` (`voucher_date`),
              KEY `idx_srv_customer_id` (`customer_id`),
              KEY `idx_srv_branch_id` (`branch_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        @mysqli_query(
            $conn,
            'CREATE TABLE IF NOT EXISTS `tbl_sale_receipt_voucher_items` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `sale_receipt_voucher_id` int(11) NOT NULL,
              `payment_type` varchar(50) DEFAULT NULL,
              `diamond_category` varchar(100) DEFAULT NULL,
              `transaction_no` varchar(100) DEFAULT NULL,
              `deposit_into` varchar(100) DEFAULT NULL,
              `product_id` int(11) DEFAULT NULL,
              `cheque_date` date DEFAULT NULL,
              `weight` decimal(10,3) DEFAULT 0.000,
              `metal_id` int(11) DEFAULT NULL,
              `quantity` decimal(10,2) DEFAULT 0.00,
              `purity_carat` varchar(50) DEFAULT NULL,
              `purity_wt` decimal(10,3) DEFAULT 0.000,
              `amount` decimal(15,2) DEFAULT 0.00,
              `previous_balance_amount` decimal(15,2) DEFAULT 0.00,
              `status` tinyint(1) DEFAULT 1,
              `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_srvi_voucher_id` (`sale_receipt_voucher_id`),
              KEY `idx_srvi_metal_id` (`metal_id`),
              KEY `idx_srvi_product_id` (`product_id`),
              CONSTRAINT `fk_srvi_sale_receipt_voucher` FOREIGN KEY (`sale_receipt_voucher_id`) REFERENCES `tbl_sale_receipt_vouchers` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}
