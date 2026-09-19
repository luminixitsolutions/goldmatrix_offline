-- Metal rate source URLs for dashboard Rate Conversions dropdown.
-- Also created automatically by includes/auragold_metal_rate_urls.php on first use.
CREATE TABLE IF NOT EXISTS `tbl_metal_rate_urls` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` int DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `url` varchar(500) NOT NULL DEFAULT '',
  `label` varchar(255) NOT NULL DEFAULT '',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=active, 0=inactive',
  `sort_order` int NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_metal_rate_urls_branch` (`branch_id`),
  KEY `idx_metal_rate_urls_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
