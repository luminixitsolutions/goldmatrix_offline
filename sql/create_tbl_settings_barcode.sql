-- Barcode settings for AuraGold (used by generateNextBarcode: product opening, purchase invoice, stock journal)
-- Run once to create tbl_settings and default barcode config.

CREATE TABLE IF NOT EXISTS `tbl_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `barcode_prefix` varchar(50) DEFAULT 'RG' COMMENT 'Prefix for generated barcodes (e.g. RG00012)',
  `barcode_digit_length` int(11) DEFAULT 5 COMMENT 'Number of digits after prefix (e.g. 5 => RG00012)',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default row (run once; skip if you already have settings)
INSERT IGNORE INTO `tbl_settings` (`id`, `barcode_prefix`, `barcode_digit_length`) VALUES (1, 'RG', 5);
