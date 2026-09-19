-- Create Customer/Supplier Ledger Tables
-- Run this SQL script to create the necessary tables for customer ledger management

-- Table: tbl_customer_ledger
CREATE TABLE IF NOT EXISTS `tbl_customer_ledger` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `transaction_type` varchar(50) NOT NULL COMMENT 'sale_order, purchase_invoice, payment, receipt, advance, return',
  `transaction_id` int(11) DEFAULT NULL COMMENT 'ID of related transaction (order_id, invoice_id, etc.)',
  `transaction_no` varchar(100) DEFAULT NULL COMMENT 'Order/Invoice number',
  `transaction_date` date NOT NULL,
  `debit_amount` decimal(15,2) DEFAULT 0.00 COMMENT 'Amount customer owes (sale orders, purchases)',
  `credit_amount` decimal(15,2) DEFAULT 0.00 COMMENT 'Amount customer paid (payments, receipts)',
  `debit_gold` decimal(10,3) DEFAULT 0.000 COMMENT 'Gold weight customer owes',
  `credit_gold` decimal(10,3) DEFAULT 0.000 COMMENT 'Gold weight customer paid',
  `debit_silver` decimal(10,3) DEFAULT 0.000 COMMENT 'Silver weight customer owes',
  `credit_silver` decimal(10,3) DEFAULT 0.000 COMMENT 'Silver weight customer paid',
  `balance_amount` decimal(15,2) DEFAULT 0.00 COMMENT 'Running balance amount',
  `balance_gold` decimal(10,3) DEFAULT 0.000 COMMENT 'Running balance gold',
  `balance_silver` decimal(10,3) DEFAULT 0.000 COMMENT 'Running balance silver',
  `description` text DEFAULT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `transaction_type` (`transaction_type`),
  KEY `transaction_id` (`transaction_id`),
  KEY `transaction_date` (`transaction_date`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tbl_customer_balance (Summary table for quick balance lookup)
CREATE TABLE IF NOT EXISTS `tbl_customer_balance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `balance_amount` decimal(15,2) DEFAULT 0.00,
  `balance_gold` decimal(10,3) DEFAULT 0.000,
  `balance_silver` decimal(10,3) DEFAULT 0.000,
  `last_transaction_date` date DEFAULT NULL,
  `last_updated` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `customer_id` (`customer_id`),
  KEY `balance_amount` (`balance_amount`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

