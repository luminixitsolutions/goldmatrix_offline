-- Create Day Reports Table
-- Run this SQL script to create the table for storing day reports

CREATE TABLE IF NOT EXISTS `tbl_day_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `report_date` date NOT NULL,
  `opening_amount` decimal(15,2) DEFAULT 0.00,
  `expected_amount` decimal(15,2) DEFAULT 0.00,
  `online_cheque_payment` decimal(15,2) DEFAULT 0.00,
  `closing_cash` decimal(15,2) DEFAULT 0.00,
  `cash_denomination` decimal(15,3) DEFAULT 0.000,
  `difference` decimal(15,2) DEFAULT 0.00,
  `report_data` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `report_date` (`report_date`),
  KEY `idx_report_date` (`report_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
