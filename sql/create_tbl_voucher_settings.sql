-- Voucher Setting: one row per branch + metal. Used by voucher-setting.php.
-- Keys are also applied automatically via includes/auragold_voucher_settings_schema.php on page load.

CREATE TABLE IF NOT EXISTS `tbl_voucher_settings` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NULL DEFAULT NULL COMMENT 'FK tbl_branches.id',
  `metal_wise` varchar(80) NOT NULL COMMENT 'Gold, Silver, Platinum, Diamond & Stones, Imitation Or Watches, Other Or Services',
  `minimum_amount_column` varchar(50) NOT NULL DEFAULT 'Amount',
  `reverse_calculation_result_column` varchar(50) NOT NULL DEFAULT 'MakingRate',
  `default_discount_type` varchar(50) NOT NULL DEFAULT 'Fix',
  `default_calculation_type` varchar(50) NOT NULL DEFAULT 'Fix',
  `stock_availability_check_by` varchar(50) NOT NULL DEFAULT 'Carat',
  `wastage_wt_calculation` varchar(50) NOT NULL DEFAULT 'GoldWt' COMMENT 'GoldWt|FinalWt',
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_branch_metal` (`branch_id`, `metal_wise`),
  KEY `idx_updated` (`updated_at`),
  KEY `idx_voucher_settings_branch` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
