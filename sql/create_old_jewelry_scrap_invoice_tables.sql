-- Old Jewelry - Scrap Invoice Tables
-- Run this SQL to create tables for scrap invoices (OJB-1, OJB-2, etc.)
-- Stock from these invoices is shown in old-jewellery.php

-- Header: tbl_old_jewelry_scrap_invoices
CREATE TABLE IF NOT EXISTS `tbl_old_jewelry_scrap_invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `against_of` varchar(100) DEFAULT NULL,
  `currency` varchar(10) DEFAULT 'USD',
  `currency_rate` decimal(18,6) DEFAULT 1.000000,
  `ref_no` varchar(100) DEFAULT NULL,
  `sales_person` varchar(255) DEFAULT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `layaways_id` int(11) DEFAULT NULL,
  `fixing_type` varchar(50) DEFAULT 'Standard',
  `barcode` varchar(100) DEFAULT NULL,
  `ounce_rate` decimal(15,4) DEFAULT 0.0000,
  `previous_balance_amt` decimal(15,2) DEFAULT 0.00,
  `previous_balance_gold` decimal(15,4) DEFAULT 0.0000,
  `previous_balance_silver` decimal(15,4) DEFAULT 0.0000,
  `subtotal` decimal(15,2) DEFAULT 0.00,
  `additional_amt` decimal(15,2) DEFAULT 0.00,
  `net_total` decimal(15,2) DEFAULT 0.00,
  `discount_amt` decimal(15,2) DEFAULT 0.00,
  `grand_total` decimal(15,2) DEFAULT 0.00,
  `advance_payment` decimal(15,2) DEFAULT 0.00,
  `metal_amt` decimal(15,2) DEFAULT 0.00,
  `round_off` decimal(15,2) DEFAULT 0.00,
  `round_off_apply` tinyint(1) DEFAULT 0,
  `paid_amt` decimal(15,2) DEFAULT 0.00,
  `balance_amt` decimal(15,2) DEFAULT 0.00,
  `comment` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_no` (`invoice_no`),
  KEY `invoice_date` (`invoice_date`),
  KEY `customer_id` (`customer_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Items: tbl_old_jewelry_scrap_invoice_items
CREATE TABLE IF NOT EXISTS `tbl_old_jewelry_scrap_invoice_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `gross_wt` decimal(15,4) DEFAULT 0.0000,
  `final_wt` decimal(15,4) DEFAULT 0.0000,
  `net_wt` decimal(15,4) DEFAULT 0.0000,
  `pure_wt` decimal(15,4) DEFAULT 0.0000,
  `making` decimal(15,2) DEFAULT 0.00,
  `tax` decimal(15,2) DEFAULT 0.00,
  `amount` decimal(15,2) DEFAULT 0.00,
  `net_amt` decimal(15,2) DEFAULT 0.00,
  `quantity` decimal(10,2) DEFAULT 1.00,
  `net_amt_wt` decimal(15,4) DEFAULT 0.0000,
  `diamond_wt` decimal(15,4) DEFAULT 0.0000,
  `gemstone_wt` decimal(15,4) DEFAULT 0.0000,
  `purity` decimal(10,2) DEFAULT 0.00,
  `rate` decimal(15,2) DEFAULT 0.00,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `invoice_id` (`invoice_id`),
  CONSTRAINT `fk_scrap_invoice_items_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `tbl_old_jewelry_scrap_invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payments: tbl_old_jewelry_scrap_invoice_payments
CREATE TABLE IF NOT EXISTS `tbl_old_jewelry_scrap_invoice_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `payment_type` varchar(50) NOT NULL,
  `deposit_into` varchar(100) DEFAULT NULL,
  `transaction_no` varchar(100) DEFAULT NULL,
  `cheque_date` date DEFAULT NULL,
  `purity_carat` varchar(50) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `diamond_category` varchar(100) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 0.00,
  `card_no` varchar(100) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `invoice_id` (`invoice_id`),
  CONSTRAINT `fk_scrap_invoice_payments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `tbl_old_jewelry_scrap_invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
