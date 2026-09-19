-- Metal Group and Diamond Group detail tables for product/sale invoice item level data.
-- Can be linked to product_id (product master) or sale_invoice_item_id (sale invoice item).
-- Run in phpMyAdmin or MySQL client.

-- Metal Group details
CREATE TABLE IF NOT EXISTS `tbl_product_metal_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NULL DEFAULT NULL COMMENT 'Optional: link to product master',
  `sale_invoice_item_id` int(11) NULL DEFAULT NULL COMMENT 'Optional: link to sale invoice item',
  `metal_group` varchar(100) NULL DEFAULT NULL,
  `weight` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `carat` decimal(10,4) NULL DEFAULT NULL,
  `purity_percent` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `purity_weight` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `rate` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `amount` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `loss_weight` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `loss_percent` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `loss_value` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `wastage_percent` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `wastage_weight` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_sale_invoice_item_id` (`sale_invoice_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Metal group details: Weight, Carat, Purity %, Purity Wt, Rate, Amount, Loss, Wastage';

-- Diamond Group details
CREATE TABLE IF NOT EXISTS `tbl_product_diamond_details` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NULL DEFAULT NULL COMMENT 'Optional: link to product master',
  `sale_invoice_item_id` int(11) NULL DEFAULT NULL COMMENT 'Optional: link to sale invoice item',
  `pkt_weight` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `pkt_less_weight` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `gross_weight` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `carat` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `diamond_weight` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `net_weight` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `quantity` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `rate` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `fc_amount` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `metal_value` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product_id` (`product_id`),
  KEY `idx_sale_invoice_item_id` (`sale_invoice_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Diamond group details: Pkt Wt, Gross Wt, Carat, D.Weight, Net Wt, Quantity, Rate, FC Amount, Metal Value';
