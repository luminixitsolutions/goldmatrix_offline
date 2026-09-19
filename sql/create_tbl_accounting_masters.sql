-- Accounting Masters: calculation / rounding defaults + financial years.
-- Run once on the branch database (same DB as other masters).

CREATE TABLE IF NOT EXISTS `tbl_accounting_master_modes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT 'Display name for Modes dropdown',
  `code` varchar(50) DEFAULT NULL COMMENT 'Stable code for integrations',
  `sort_order` int(11) DEFAULT 0,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Modes: inventory / valuation method (same labels as product costing UI)
INSERT INTO `tbl_accounting_master_modes` (`id`, `name`, `code`, `sort_order`, `status`) VALUES
(1, 'Last Purchase Rate', 'last_purchase_rate', 1, 1),
(2, 'FIFO', 'fifo', 2, 1),
(3, 'Average Cost', 'average_cost', 3, 1),
(4, 'Low Cost', 'low_cost', 4, 1),
(5, 'High Cost', 'high_cost', 5, 1)
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `code` = VALUES(`code`),
  `sort_order` = VALUES(`sort_order`),
  `status` = VALUES(`status`);

CREATE TABLE IF NOT EXISTS `tbl_accounting_calculation_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mode_id` int(11) NOT NULL DEFAULT 0 COMMENT '0 = not chosen; else FK tbl_accounting_master_modes.id',
  `amount_decimal` tinyint(4) NOT NULL DEFAULT 2,
  `amount_round` tinyint(1) NOT NULL DEFAULT 1,
  `weight_decimal` tinyint(4) NOT NULL DEFAULT 3,
  `weight_round` tinyint(1) NOT NULL DEFAULT 1,
  `percent_decimal` tinyint(4) NOT NULL DEFAULT 3,
  `percent_round` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `mode_id` (`mode_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `tbl_accounting_calculation_settings` (`id`, `mode_id`, `amount_decimal`, `amount_round`, `weight_decimal`, `weight_round`, `percent_decimal`, `percent_round`) VALUES
(1, 0, 2, 1, 3, 1, 3, 1);

CREATE TABLE IF NOT EXISTS `tbl_accounting_financial_years` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Current FY (radio)',
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `idx_fy_range` (`start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
