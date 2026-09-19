-- Investment / Layaways Fund — scheme master (Create Scheme modal)
-- Run once against your branch/working database (same DB as tbl_customers, etc.)

CREATE TABLE IF NOT EXISTS `tbl_investment_schemes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `scheme_name` varchar(255) NOT NULL,
  `redemption_on` varchar(50) DEFAULT NULL,
  `carat_id` int(11) DEFAULT NULL,
  `carat_label` varchar(255) DEFAULT NULL,
  `duration_value` int(11) NOT NULL DEFAULT 12,
  `duration_unit` varchar(20) NOT NULL DEFAULT 'Month',
  `installment_type` varchar(50) DEFAULT NULL,
  `installment_amt` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `minimum_amt_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `minimum_amt` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `bonus_rows` longtext DEFAULT NULL COMMENT 'JSON array; reserved for future use',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_active` (`active`),
  KEY `idx_scheme_name` (`scheme_name`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
