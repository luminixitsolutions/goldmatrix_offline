-- Links sale/purchase invoices to against-invoice fixing data (Hedging / against PI or SI).
-- Auto-created by save-sale-invoice.php if missing; run manually if preferred.

CREATE TABLE IF NOT EXISTS `invoice_fixing_mapping` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `source_type` varchar(32) NOT NULL COMMENT 'sale_invoice, purchase_invoice',
  `source_transaction_id` int(11) NOT NULL,
  `source_invoice_no` varchar(64) DEFAULT NULL,
  `against_invoice_type` varchar(32) DEFAULT NULL COMMENT 'purchase_invoice, sale_invoice',
  `against_invoice_id` int(11) DEFAULT NULL,
  `against_invoice_no` varchar(64) DEFAULT NULL,
  `fixing_type` varchar(32) DEFAULT 'Hedging',
  `metal_type` varchar(16) DEFAULT NULL,
  `fixing_weight` decimal(18,3) DEFAULT 0.000,
  `fixing_rate` decimal(18,4) DEFAULT 0.0000,
  `fixing_amount` decimal(18,2) DEFAULT 0.00,
  `status` tinyint(4) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_source` (`source_type`,`source_transaction_id`),
  KEY `idx_against` (`against_invoice_type`,`against_invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
