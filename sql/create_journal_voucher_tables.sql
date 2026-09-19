-- Create Journal Voucher Tables
-- Run this SQL script to create the necessary tables for journal vouchers
-- Journal voucher: multi-line entries with Branch, Account Ledger, Cr/Dr, Against, Ref, Amount, Metal, Purity Wt

-- Table: tbl_journal_vouchers
CREATE TABLE IF NOT EXISTS `tbl_journal_vouchers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `voucher_no` varchar(50) NOT NULL,
  `voucher_date` date NOT NULL,
  `comment` text DEFAULT NULL,
  `credit_wt` decimal(15,4) DEFAULT 0.0000,
  `debit_wt` decimal(15,4) DEFAULT 0.0000,
  `debit_total` decimal(15,2) DEFAULT 0.00,
  `credit_total` decimal(15,2) DEFAULT 0.00,
  `status` varchar(20) DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `voucher_no` (`voucher_no`),
  KEY `voucher_date` (`voucher_date`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tbl_journal_voucher_items
CREATE TABLE IF NOT EXISTS `tbl_journal_voucher_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `voucher_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL COMMENT 'FK to tbl_branches',
  `branch_name` varchar(100) DEFAULT NULL COMMENT 'Denormalized branch name',
  `account_ledger` varchar(200) NOT NULL COMMENT 'Account ledger name',
  `cr_dr` varchar(10) NOT NULL DEFAULT 'Dr' COMMENT 'Cr or Dr',
  `against` varchar(200) DEFAULT NULL,
  `ref_no` varchar(100) DEFAULT NULL,
  `ref_date` date DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT 0.00,
  `metal` varchar(50) DEFAULT NULL,
  `purity_wt` decimal(15,4) DEFAULT 0.0000,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `voucher_id` (`voucher_id`),
  KEY `branch_id` (`branch_id`),
  CONSTRAINT `fk_journal_voucher_items_voucher` FOREIGN KEY (`voucher_id`) REFERENCES `tbl_journal_vouchers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
