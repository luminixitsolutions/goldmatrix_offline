-- Partial sale invoice against Sale Order: header link + line mapping
-- Idempotent: creates tables if missing, adds columns if missing. Safe to re-run.

-- ---------------------------------------------------------------------------
-- Base tables (no-op when already exist)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tbl_sale_invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `against_of` varchar(100) DEFAULT NULL,
  `against_id` int(11) DEFAULT NULL COMMENT 'Sale Order PK when against_of = Sale Order',
  `currency` varchar(10) DEFAULT 'AED',
  `ref_no` varchar(100) DEFAULT NULL,
  `sales_person` varchar(255) DEFAULT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `layaways_id` int(11) DEFAULT NULL,
  `fixing_type` varchar(50) DEFAULT 'Standard',
  `previous_balance` decimal(15,2) DEFAULT 0.00,
  `previous_gold` decimal(15,2) DEFAULT 0.00,
  `previous_silver` decimal(15,2) DEFAULT 0.00,
  `subtotal` decimal(15,2) DEFAULT 0.00,
  `additional_amt` decimal(15,2) DEFAULT 0.00,
  `net_total` decimal(15,2) DEFAULT 0.00,
  `reward_points` decimal(15,2) DEFAULT 0.00,
  `coupon_code` varchar(50) DEFAULT NULL,
  `coupon_discount` decimal(15,2) DEFAULT 0.00,
  `discount_amt` decimal(15,2) DEFAULT 0.00,
  `redeem_points` decimal(15,2) DEFAULT 0.00,
  `grand_total` decimal(15,2) DEFAULT 0.00,
  `advance_payment` decimal(15,2) DEFAULT 0.00,
  `metal_amt` decimal(15,2) DEFAULT 0.00,
  `round_off` decimal(15,2) DEFAULT 0.00,
  `paid_amt` decimal(15,2) DEFAULT 0.00,
  `balance_amt` decimal(15,2) DEFAULT 0.00,
  `group_name` varchar(255) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_no` (`invoice_no`),
  KEY `customer_id` (`customer_id`),
  KEY `invoice_date` (`invoice_date`),
  KEY `status` (`status`),
  KEY `idx_si_against_id` (`against_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_sale_invoice_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `source_sale_order_item_id` int(11) DEFAULT NULL COMMENT 'tbl_sale_order_items.id when invoiced from SO',
  `product_id` int(11) NOT NULL,
  `product_characteristic_id` int(11) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `product_name` varchar(255) NOT NULL,
  `carat` varchar(50) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 1.00,
  `gross_weight` decimal(10,3) DEFAULT 0.000,
  `less_weight` decimal(10,3) DEFAULT 0.000,
  `purity` decimal(10,2) DEFAULT 0.00,
  `purity_weight` decimal(10,3) DEFAULT 0.000,
  `final_weight` decimal(10,3) DEFAULT 0.000,
  `net_weight` decimal(10,3) DEFAULT 0.000,
  `pure_weight` decimal(10,3) DEFAULT 0.000,
  `rate` decimal(15,2) DEFAULT 0.00,
  `making_amount` decimal(15,2) DEFAULT 0.00,
  `amount` decimal(15,2) DEFAULT 0.00,
  `tax_amount` decimal(15,2) DEFAULT 0.00,
  `net_amount` decimal(15,2) DEFAULT 0.00,
  `net_amt_with_tax` decimal(15,2) DEFAULT 0.00,
  `design_no` varchar(100) DEFAULT NULL,
  `location_id` int(11) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `invoice_id` (`invoice_id`),
  KEY `product_id` (`product_id`),
  KEY `product_characteristic_id` (`product_characteristic_id`),
  KEY `barcode` (`barcode`),
  KEY `idx_sii_source_so_item_id` (`source_sale_order_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_sale_invoice_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `payment_type` varchar(50) NOT NULL,
  `deposit_into` varchar(100) DEFAULT NULL,
  `transaction_no` varchar(100) DEFAULT NULL,
  `cheque_date` date DEFAULT NULL,
  `purity_carat` varchar(50) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `previous_balance_amount` decimal(15,2) DEFAULT 0.00,
  `current_order_amount` decimal(15,2) DEFAULT 0.00,
  `diamond_category` varchar(100) DEFAULT NULL,
  `quantity` decimal(10,2) DEFAULT 0.00,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `invoice_id` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Existing installs: add new columns only when missing
-- ---------------------------------------------------------------------------
SET @auragold_db := DATABASE();

SET @auragold_sql := (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = @auragold_db AND TABLE_NAME = 'tbl_sale_invoices'
    )
    AND NOT EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @auragold_db AND TABLE_NAME = 'tbl_sale_invoices' AND COLUMN_NAME = 'against_id'
    ),
    'ALTER TABLE `tbl_sale_invoices` ADD COLUMN `against_id` INT(11) NULL DEFAULT NULL COMMENT ''Sale Order PK when against_of = Sale Order'' AFTER `against_of`, ADD KEY `idx_si_against_id` (`against_id`)',
    'SELECT 1'
  )
);
PREPARE auragold_stmt FROM @auragold_sql;
EXECUTE auragold_stmt;
DEALLOCATE PREPARE auragold_stmt;

SET @auragold_sql := (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = @auragold_db AND TABLE_NAME = 'tbl_sale_invoice_items'
    )
    AND NOT EXISTS (
      SELECT 1 FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @auragold_db AND TABLE_NAME = 'tbl_sale_invoice_items' AND COLUMN_NAME = 'source_sale_order_item_id'
    ),
    'ALTER TABLE `tbl_sale_invoice_items` ADD COLUMN `source_sale_order_item_id` INT(11) NULL DEFAULT NULL COMMENT ''tbl_sale_order_items.id when invoiced from SO'' AFTER `invoice_id`, ADD KEY `idx_sii_source_so_item_id` (`source_sale_order_item_id`)',
    'SELECT 1'
  )
);
PREPARE auragold_stmt FROM @auragold_sql;
EXECUTE auragold_stmt;
DEALLOCATE PREPARE auragold_stmt;
