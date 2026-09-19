-- Dashboard metal rates (gold / silver / diamond), carat-wise. Run once.
-- Used by admin/dashboard.php and ajax/save-dashboard-rates.php

CREATE TABLE IF NOT EXISTS `tbl_dashboard_metal_meta` (
  `metal` varchar(20) NOT NULL COMMENT 'gold, silver, diamond',
  `branch_id` int(11) NOT NULL DEFAULT 0 COMMENT '0 = shared default',
  `source_url` varchar(512) NOT NULL DEFAULT '',
  `ounce_rate` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`metal`,`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_dashboard_metal_rates` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL DEFAULT 0 COMMENT '0 = shared default',
  `metal` varchar(20) NOT NULL COMMENT 'gold, silver, diamond',
  `carat_label` varchar(64) NOT NULL COMMENT 'e.g. 24K, 999, 0.30 ct',
  `rate` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `sell_premium` decimal(18,6) DEFAULT NULL,
  `conversion_rate` decimal(18,8) NOT NULL DEFAULT 1.00000000,
  `sort_order` smallint(6) NOT NULL DEFAULT 0,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_branch_metal_carat` (`branch_id`,`metal`,`carat_label`),
  KEY `idx_metal_sort` (`metal`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
