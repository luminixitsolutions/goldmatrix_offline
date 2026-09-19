-- Metal ↔ Amount conversion log (used by Utilities → Metal To Amount / Amount To Metal)
CREATE TABLE IF NOT EXISTS `tbl_metal_amount_conversions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) UNSIGNED NULL DEFAULT NULL,
  `customer_id` int(11) NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `direction` enum('metal_to_amount','amount_to_metal') NOT NULL,
  `metal_type` varchar(32) NOT NULL,
  `metal_weight` decimal(16,4) NOT NULL DEFAULT 0.0000,
  `rate` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `amount` decimal(16,2) NOT NULL DEFAULT 0.00,
  `trans_date` datetime NOT NULL,
  `trans_no` varchar(64) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `customer_id` (`customer_id`),
  KEY `direction` (`direction`),
  KEY `trans_date` (`trans_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional: diamond movement on customer ledger (run if diamond conversion should affect tbl_customer_ledger)
-- See admin/includes/ensure_metal_amount_conversion.php (auto-applied on first use)
