-- Inward stock: one row per stock journal inward entry
CREATE TABLE IF NOT EXISTS `tbl_inward_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stock_journal_id` int(11) DEFAULT NULL COMMENT 'Reference to tbl_stock_journal.id',
  `product_id` int(11) NOT NULL,
  `product_characteristic_id` int(11) DEFAULT NULL,
  `barcode_no` varchar(100) DEFAULT NULL,
  `branch_id` int(11) DEFAULT 1,
  `metal_id` int(11) DEFAULT NULL,
  `qty` decimal(10,2) DEFAULT 1.00,
  `weight` decimal(10,3) DEFAULT 0.000,
  `rate` decimal(15,2) DEFAULT 0.00,
  `value` decimal(15,2) DEFAULT 0.00,
  `stock_type` varchar(50) DEFAULT 'purchase',
  `transaction_date` date DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `stock_journal_id` (`stock_journal_id`),
  KEY `transaction_date` (`transaction_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Outward stock: one row per OPENING (auto from stock journal) or other outward
CREATE TABLE IF NOT EXISTS `tbl_outward_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stock_journal_id` int(11) DEFAULT NULL COMMENT 'Reference to tbl_stock_journal.id',
  `product_id` int(11) NOT NULL,
  `product_characteristic_id` int(11) DEFAULT NULL,
  `barcode_no` varchar(100) DEFAULT NULL,
  `qty` decimal(10,2) DEFAULT 0.00,
  `weight` decimal(10,3) DEFAULT 0.000,
  `stock_type` varchar(50) DEFAULT 'OPENING',
  `reference` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `stock_journal_id` (`stock_journal_id`),
  KEY `stock_type` (`stock_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
