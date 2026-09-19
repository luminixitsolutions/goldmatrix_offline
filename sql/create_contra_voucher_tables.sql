-- Create Contra Voucher Tables
-- Run this SQL script to create the necessary tables for contra vouchers
-- Contra voucher: transfer between bank/cash accounts (Deposit/Dr or Withdrawal/Cr)

-- Table: tbl_contra_vouchers
CREATE TABLE IF NOT EXISTS `tbl_contra_vouchers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `voucher_no` varchar(50) NOT NULL,
  `voucher_date` date NOT NULL,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `comment` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `voucher_no` (`voucher_no`),
  KEY `voucher_date` (`voucher_date`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tbl_contra_voucher_items
CREATE TABLE IF NOT EXISTS `tbl_contra_voucher_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `voucher_id` int(11) NOT NULL,
  `bank_cash_ac` varchar(100) NOT NULL COMMENT 'Bank or Cash account name',
  `ref_no` varchar(100) DEFAULT NULL,
  `ref_date` date DEFAULT NULL,
  `transaction_type` varchar(20) NOT NULL DEFAULT 'withdrawal' COMMENT 'deposit or withdrawal',
  `amount` decimal(15,2) DEFAULT 0.00,
  `comment` text DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `voucher_id` (`voucher_id`),
  CONSTRAINT `fk_contra_voucher_items_voucher` FOREIGN KEY (`voucher_id`) REFERENCES `tbl_contra_vouchers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
