-- Create Advance Payment Tables
-- Run this SQL script to create the necessary tables for advance payments

-- Table: tbl_advance_payments
CREATE TABLE IF NOT EXISTS `tbl_advance_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `voucher_no` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `ref_no` varchar(100) DEFAULT NULL,
  `receipt_no` varchar(100) DEFAULT NULL,
  `voucher_type` varchar(50) DEFAULT NULL,
  `against` varchar(100) DEFAULT NULL,
  `sales_person` varchar(255) DEFAULT NULL,
  `against_of` varchar(100) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'USD',
  `voucher_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `layaways_id` int(11) DEFAULT NULL,
  `fixing_type` varchar(50) DEFAULT 'Standard',
  `previous_balance` decimal(15,2) DEFAULT 0.00,
  `previous_gold` decimal(10,3) DEFAULT 0.000,
  `previous_silver` decimal(10,3) DEFAULT 0.000,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `total_gold` decimal(10,3) DEFAULT 0.000,
  `total_silver` decimal(10,3) DEFAULT 0.000,
  `comment` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `voucher_no` (`voucher_no`),
  KEY `customer_id` (`customer_id`),
  KEY `voucher_date` (`voucher_date`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tbl_advance_payment_items
CREATE TABLE IF NOT EXISTS `tbl_advance_payment_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `voucher_id` int(11) NOT NULL,
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
  KEY `voucher_id` (`voucher_id`),
  KEY `product_id` (`product_id`),
  KEY `metal_id` (`metal_id`),
  CONSTRAINT `fk_advance_payment_items_voucher` FOREIGN KEY (`voucher_id`) REFERENCES `tbl_advance_payments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
