-- =============================================================================
-- tbl_purchase_fixing_direct
-- Created automatically when a Sale Invoice is saved with Fixing Type = Hedging.
-- Voucher numbers: PFD-1, PFD-2, ... | against_of = "Fixing of <sale_invoice_no>"
-- Run once against database `auragold` (or your DB name).
-- =============================================================================

CREATE TABLE IF NOT EXISTS `tbl_purchase_fixing_direct` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(50) NOT NULL COMMENT 'PFD voucher no',
  `ref_no` varchar(100) DEFAULT NULL COMMENT 'Same as invoice_no for reports',
  `supplier_id` int(11) DEFAULT NULL,
  `supplier_name` varchar(255) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `sale_invoice_no` varchar(64) DEFAULT NULL COMMENT 'Linked sale invoice (SPK14, SI-1, etc.)',
  `against_of` varchar(255) DEFAULT NULL COMMENT 'e.g. Fixing of SPK14',
  `currency` varchar(10) DEFAULT 'AED',
  `invoice_date` date NOT NULL,
  `fixing_date` date DEFAULT NULL,
  `fixing_type` varchar(50) DEFAULT 'Hedging',
  `subtotal` decimal(15,2) DEFAULT 0.00,
  `net_total` decimal(15,2) DEFAULT 0.00,
  `grand_total` decimal(15,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `paid_amt` decimal(15,2) DEFAULT 0.00,
  `balance_amt` decimal(15,2) DEFAULT 0.00,
  `status` varchar(20) DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_no` (`invoice_no`),
  KEY `ref_no` (`ref_no`),
  KEY `invoice_date` (`invoice_date`),
  KEY `fixing_date` (`fixing_date`),
  KEY `idx_sale_invoice_no` (`sale_invoice_no`),
  KEY `idx_against_of` (`against_of`(64))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Line items (same shape as tbl_sale_fixing_direct_items) — metal rows per sale line for hedging / audit
CREATE TABLE IF NOT EXISTS `tbl_purchase_fixing_direct_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fixing_id` int(11) NOT NULL COMMENT 'tbl_purchase_fixing_direct.id',
  `metal_id` int(11) DEFAULT NULL,
  `gross_wt` decimal(10,3) DEFAULT 0.000,
  `purity_wt` decimal(10,3) DEFAULT 0.000,
  `rate` decimal(15,2) DEFAULT 0.00,
  `amount` decimal(15,2) DEFAULT 0.00,
  `purity` decimal(10,2) DEFAULT 1.00,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_fixing_id` (`fixing_id`),
  KEY `idx_metal_id` (`metal_id`),
  CONSTRAINT `fk_purchase_fixing_items_header` FOREIGN KEY (`fixing_id`) REFERENCES `tbl_purchase_fixing_direct` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
