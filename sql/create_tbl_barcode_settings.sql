-- Barcode printing settings (single row or latest used for print layout)
-- Run this once to create the table.

CREATE TABLE IF NOT EXISTS `tbl_barcode_settings` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `label_size_preset` varchar(50) NOT NULL DEFAULT '100x18' COMMENT 'e.g. 100x18, 100x25, custom',
  `label_width_mm` decimal(10,2) NOT NULL DEFAULT 100.00,
  `label_height_mm` decimal(10,2) NOT NULL DEFAULT 18.00,
  `font_size` int(11) NOT NULL DEFAULT 12 COMMENT 'Label text font size in px',
  `show_product_name` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=show, 0=hide (legacy mirror of default print mode)',
  `show_price` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=show, 0=hide (legacy mirror of default print mode)',
  `show_barcode_number` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=show, 0=hide (legacy mirror of default print mode)',
  `show_product_name_barcode` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=show product name on barcode layout',
  `show_product_name_qr` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=show product name on QR layout',
  `show_price_barcode` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=show price on barcode layout',
  `show_price_qr` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=show price on QR layout',
  `show_barcode_number_barcode` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=show barcode no. on barcode layout',
  `show_barcode_number_qr` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=show barcode no. on QR layout',
  `print_copies` int(11) NOT NULL DEFAULT 1 COMMENT 'Number of copies per label',
  `barcode_bar_width` tinyint(4) NOT NULL DEFAULT 2 COMMENT 'JsBarcode module width 1-10',
  `barcode_bar_height` smallint(6) NOT NULL DEFAULT 28 COMMENT 'JsBarcode module height px',
  `metal_type` varchar(50) DEFAULT NULL COMMENT 'e.g. Gold, Silver, Platinum',
  `is_default_print` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = default print layout for this metal_type',
  `design_layout` LONGTEXT DEFAULT NULL COMMENT 'JSON: barcode label design',
  `design_layout_qr` LONGTEXT DEFAULT NULL COMMENT 'JSON label design for QR mode',
  `default_print_code_type` varchar(10) NOT NULL DEFAULT 'barcode' COMMENT 'barcode|qr — used when print URL has no code= param',
  `preview_image` varchar(255) DEFAULT NULL,
  `branch_id` int(11) unsigned DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_updated` (`updated_at`),
  KEY `idx_metal_label` (`metal_type`, `label_size_preset`),
  KEY `idx_branch` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default row so getBarcodeSettings() always has a record (run once)
INSERT IGNORE INTO `tbl_barcode_settings` (
  `id`, `label_size_preset`, `label_width_mm`, `label_height_mm`,
  `font_size`, `show_product_name`, `show_price`, `show_barcode_number`,
  `show_product_name_barcode`, `show_product_name_qr`, `show_price_barcode`, `show_price_qr`,
  `show_barcode_number_barcode`, `show_barcode_number_qr`, `print_copies`,
  `barcode_bar_width`, `barcode_bar_height`, `metal_type`, `is_default_print`,
  `design_layout`, `design_layout_qr`, `default_print_code_type`,
  `created_at`, `updated_at`
) VALUES (
  1, '100x18', 100.00, 18.00,
  12, 1, 1, 1,
  1, 1, 1, 1, 1, 1, 1,
  2, 28, NULL, 0,
  NULL, NULL, 'barcode',
  NOW(), NOW()
);

-- Existing DBs: also run sql/alter_tbl_barcode_settings_qr_layout.sql (or rely on
-- auragold_ensure_barcode_settings_qr_columns() which auto-adds these columns).
