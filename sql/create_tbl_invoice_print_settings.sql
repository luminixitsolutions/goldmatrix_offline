-- Invoice Print Settings (Sale Invoice layout: columns, header, footer, layout type)
-- Run once to create the table. No changes to existing invoice tables.

CREATE TABLE IF NOT EXISTS `tbl_invoice_print_settings` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `setting_type` varchar(50) NOT NULL DEFAULT 'default' COMMENT 'default, sale_invoice, purchase_invoice, sale_order, purchase_quotation, sale_quotation, sale_return, purchase_return',
  `setting_key` varchar(100) NOT NULL COMMENT 'e.g. sale_invoice_columns, header_company_logo, layout_type',
  `setting_value` text DEFAULT NULL COMMENT 'JSON or 1/0 for toggles',
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_type_key` (`setting_type`, `setting_key`),
  KEY `idx_setting_type` (`setting_type`),
  KEY `idx_updated` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default type: visible columns and other settings (run once; for existing table without setting_type run alter_tbl_invoice_print_settings_add_setting_type.sql first)
INSERT IGNORE INTO `tbl_invoice_print_settings` (`setting_type`, `setting_key`, `setting_value`, `updated_at`) VALUES
('default', 'sale_invoice_columns', '["sr_no","item_name","design_no","huid","category","gross_weight","less_weight","net_weight","purity_karat","rate","making_charge","diamond_amount","stone_amount","discount","amount"]', NOW()),
('default', 'header_company_logo', '1', NOW()),
('default', 'header_company_name', '1', NOW()),
('default', 'header_gst_number', '1', NOW()),
('default', 'header_phone', '1', NOW()),
('default', 'header_invoice_title', '1', NOW()),
('default', 'footer_terms_conditions', '1', NOW()),
('default', 'footer_authorized_signature', '1', NOW()),
('default', 'footer_thank_you_message', '1', NOW()),
('default', 'layout_type', 'A4', NOW()),
('default', 'invoice_secondary_language', '', NOW()),
('default', 'advertise_banner_path', '', NOW()),
('default', 'footer_show_banner', '0', NOW());
