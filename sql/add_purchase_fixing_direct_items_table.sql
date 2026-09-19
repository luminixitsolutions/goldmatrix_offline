-- Run if you already have tbl_purchase_fixing_direct but no items table yet.
-- If FOREIGN KEY fails (engine/version), create the table without the CONSTRAINT line.

CREATE TABLE IF NOT EXISTS `tbl_purchase_fixing_direct_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fixing_id` int(11) NOT NULL COMMENT 'tbl_purchase_fixing_direct.id',
  `metal_id` int(11) DEFAULT NULL,
  `gross_wt` decimal(10,3) DEFAULT 0.000,
  `purity_wt` decimal(10,3) DEFAULT 0.000,
  `rate` decimal(15,2) DEFAULT 0.00,
  `amount` decimal(15,2) DEFAULT 0.00,
  `purity` decimal(10,2) DEFAULT 1.00,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fixing_id` (`fixing_id`),
  KEY `idx_metal_id` (`metal_id`),
  CONSTRAINT `fk_purchase_fixing_items_header` FOREIGN KEY (`fixing_id`) REFERENCES `tbl_purchase_fixing_direct` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
