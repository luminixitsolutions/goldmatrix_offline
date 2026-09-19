-- Create Income Invoice Tables
-- Income No: INI-1, INI-2, ... | With Tax | Name | Against Of | Fixing Type: Standard | Hedging
--
-- Tables are also auto-created at runtime via:
--   includes/auragold_income_invoice_schema.php  →  auragold_ensure_income_invoice_tables($conn)
-- Run this file manually only if you prefer direct SQL (phpMyAdmin / mysql CLI).
-- Table: tbl_incomes
CREATE TABLE IF NOT EXISTS `tbl_incomes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `income_no` varchar(50) NOT NULL,
  `with_tax` tinyint(1) DEFAULT 1,
  `ledger_id` int(11) DEFAULT NULL,
  `ledger_name` varchar(255) NOT NULL,
  `against_of` varchar(255) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'AED',
  `exchange_rate` decimal(15,6) DEFAULT 1.000000,
  `income_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `ref_no` varchar(100) DEFAULT NULL,
  `sales_person` varchar(255) DEFAULT NULL,
  `layaways` varchar(100) DEFAULT NULL,
  `fixing_type` varchar(50) DEFAULT 'Standard',
  `previous_balance` decimal(15,2) DEFAULT 0.00,
  `previous_gold` decimal(15,2) DEFAULT 0.00,
  `previous_silver` decimal(15,2) DEFAULT 0.00,
  `subtotal` decimal(15,2) DEFAULT 0.00,
  `net_total` decimal(15,2) DEFAULT 0.00,
  `discount_percent` decimal(10,2) DEFAULT 0.00,
  `discount_amt` decimal(15,2) DEFAULT 0.00,
  `grand_total` decimal(15,2) DEFAULT 0.00,
  `round_off` decimal(15,2) DEFAULT 0.00,
  `paid_amt` decimal(15,2) DEFAULT 0.00,
  `balance_amt` decimal(15,2) DEFAULT 0.00,
  `comment` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `income_no` (`income_no`),
  KEY `ledger_id` (`ledger_id`),
  KEY `income_date` (`income_date`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tbl_income_items (Category, Description, Amount, Tax, Tax With Amount)
CREATE TABLE IF NOT EXISTS `tbl_income_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `income_id` int(11) NOT NULL,
  `category` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(15,2) DEFAULT 0.00,
  `tax_rate` decimal(10,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `tax_with_amount` decimal(15,2) DEFAULT 0.00,
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `income_id` (`income_id`),
  CONSTRAINT `fk_income_items_income` FOREIGN KEY (`income_id`) REFERENCES `tbl_incomes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tbl_income_receipts (Receipt / Payment Type, Diamond Category, Transaction No., Deposit Into, Cheque Dt., Amount, Card No.)
CREATE TABLE IF NOT EXISTS `tbl_income_receipts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `income_id` int(11) NOT NULL,
  `payment_type` varchar(50) NOT NULL,
  `deposit_into` varchar(100) DEFAULT NULL,
  `diamond_category` varchar(100) DEFAULT NULL,
  `transaction_no` varchar(100) DEFAULT NULL,
  `transfer_from` varchar(255) DEFAULT NULL,
  `cheque_date` date DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `card_no` varchar(50) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `income_id` (`income_id`),
  CONSTRAINT `fk_income_receipts_income` FOREIGN KEY (`income_id`) REFERENCES `tbl_incomes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
