-- Assign inventory barcodes to a sales person (Assign Inventory to Sales Team screen)
CREATE TABLE IF NOT EXISTS `tbl_sales_team_inventory_assign` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `sales_person` varchar(255) NOT NULL,
  `barcode_no` varchar(128) NOT NULL,
  `row_json` longtext DEFAULT NULL COMMENT 'Full grid row JSON for round-trip',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_barcode_global` (`barcode_no`(100)),
  KEY `idx_sales_person` (`sales_person`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
