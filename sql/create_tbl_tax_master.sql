-- Tax Master: taxes defined here are shown on Product Opening page; user can enable/override value per product.
-- Run this script once. Product Opening will list these taxes; product save stores selections in tbl_product_tax.

CREATE TABLE IF NOT EXISTS `tbl_tax_master` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT 'Tax name (e.g. VAT, TAX BAH)',
  `default_value` decimal(10,2) DEFAULT 0.00 COMMENT 'Default % or value shown on product opening',
  `default_calculation_mode` varchar(100) DEFAULT 'Product Amount' COMMENT 'Default calculation mode name',
  `gst_supply_scope` varchar(32) NOT NULL DEFAULT 'local_state' COMMENT 'local_state=intra (CGST+SGST); out_of_state=inter (IGST)',
  `sort_order` int(11) DEFAULT 0,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed default taxes so product opening shows them until user adds more in Masters
INSERT IGNORE INTO `tbl_tax_master` (`name`, `default_value`, `default_calculation_mode`, `gst_supply_scope`, `sort_order`, `status`) VALUES
('VAT', 5.00, 'Product Amount', 'local_state', 1, 1),
('TAX BAH', 10.00, 'Product Amount', 'local_state', 2, 1);
