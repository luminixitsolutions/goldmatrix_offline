-- Append-only history for dashboard rate charts (auto-created by PHP if missing).
-- Optional: run manually if you prefer to provision tables ahead of time.

CREATE TABLE IF NOT EXISTS `tbl_dashboard_metal_rate_history` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `metal` varchar(24) NOT NULL DEFAULT '',
  `carat_label` varchar(64) NOT NULL DEFAULT '',
  `rate` decimal(18,6) NOT NULL DEFAULT 0.000000,
  `recorded_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_metal_time` (`metal`, `recorded_at`),
  KEY `idx_metal_carat_time` (`metal`, `carat_label`, `recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
