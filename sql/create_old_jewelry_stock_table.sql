-- Separate stock manage table for Old Jewelry - Stock In
-- Data saved here does NOT change old-jewelry-scrap-invoice saved data

CREATE TABLE IF NOT EXISTS `tbl_old_jewelry_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source_invoice_id` int(11) NOT NULL,
  `source_item_id` int(11) NOT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `invoice_no` varchar(50) NOT NULL,
  `voucher_type` varchar(100) DEFAULT 'Old Jewelry - Scrap',
  `metal` varchar(100) DEFAULT NULL,
  `product` varchar(500) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `final_wt` decimal(15,4) DEFAULT 0.0000,
  `gross_wt` decimal(15,4) DEFAULT 0.0000,
  `purity` decimal(10,2) DEFAULT 0.00,
  `branch_id` int(11) DEFAULT NULL,
  `less_wt` decimal(15,4) DEFAULT 0.0000,
  `net_wt` decimal(15,4) DEFAULT 0.0000,
  `amount` decimal(15,2) DEFAULT 0.00,
  `category` varchar(100) DEFAULT NULL,
  `against_invoice_no` varchar(100) DEFAULT NULL,
  `against_voucher` varchar(100) DEFAULT NULL,
  `group_name` varchar(255) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 1.00,
  `rate` decimal(15,2) DEFAULT 0.00,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `source_invoice_id` (`source_invoice_id`),
  KEY `source_item_id` (`source_item_id`),
  KEY `branch_id` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
