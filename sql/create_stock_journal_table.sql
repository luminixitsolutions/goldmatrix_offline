-- Create Stock Journal Table
-- Run this SQL script to create the table for stock journal entries

-- Table: tbl_stock_journal
CREATE TABLE IF NOT EXISTS `tbl_stock_journal` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sj_invoice_no` varchar(50) NOT NULL COMMENT 'Stock Journal invoice number (SJ-1, SJ-2, etc.)',
  `item_id` int(11) NOT NULL COMMENT 'Reference to tbl_purchase_invoice_items.id',
  `invoice_id` int(11) NOT NULL COMMENT 'Reference to tbl_purchase_invoices.id',
  `invoice_no` varchar(50) DEFAULT NULL COMMENT 'Purchase invoice number for reference',
  `sj_date` date NOT NULL COMMENT 'Stock journal date',
  `barcode` varchar(100) DEFAULT NULL,
  `code` varchar(100) DEFAULT NULL,
  
  -- Product Information
  `product_id` int(11) DEFAULT NULL,
  `product_characteristic_id` int(11) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  
  -- Metal Information
  `metal_id` int(11) DEFAULT NULL,
  `metal_type` varchar(50) DEFAULT NULL COMMENT 'gold, silver, diamond, loose',
  
  -- Weight and Quantity Information
  `quantity` decimal(10,2) DEFAULT 1.00,
  `gross_weight` decimal(10,3) DEFAULT 0.000,
  `less_weight` decimal(10,3) DEFAULT 0.000,
  `net_weight` decimal(10,3) DEFAULT 0.000,
  `purity` decimal(10,2) DEFAULT 0.00,
  `purity_weight` decimal(10,3) DEFAULT 0.000,
  `pure_weight` decimal(10,3) DEFAULT 0.000,
  `final_weight` decimal(10,3) DEFAULT 0.000,
  
  -- Financial Information
  `rate` decimal(15,2) DEFAULT 0.00,
  `amount` decimal(15,2) DEFAULT 0.00,
  `making_amount` decimal(15,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) DEFAULT 0.00,
  `net_amt_with_tax` decimal(15,2) DEFAULT 0.00,
  
  -- Additional Fields
  `group_name` varchar(255) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  
  -- Status and Audit Fields
  `status` varchar(20) DEFAULT 'active' COMMENT 'active, completed, cancelled',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `sj_invoice_no` (`sj_invoice_no`),
  KEY `item_id` (`item_id`),
  KEY `invoice_id` (`invoice_id`),
  KEY `product_id` (`product_id`),
  KEY `metal_id` (`metal_id`),
  KEY `sj_date` (`sj_date`),
  KEY `status` (`status`),
  CONSTRAINT `fk_stock_journal_item` FOREIGN KEY (`item_id`) REFERENCES `tbl_purchase_invoice_items` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_stock_journal_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `tbl_purchase_invoices` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

