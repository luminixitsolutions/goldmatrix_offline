-- Repair Job Work Order tables (against Repair Order)
-- Jobwork No format: RJWO-1, RJWO-2 (set from id after insert)

-- Master
CREATE TABLE IF NOT EXISTS `tbl_repair_jobwork_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jobwork_no` varchar(50) NOT NULL DEFAULT '',
  `repair_order_id` int(11) NOT NULL,
  `repair_order_no` varchar(50) NOT NULL DEFAULT '',
  `customer_name` varchar(255) DEFAULT NULL,
  `order_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `grand_total` decimal(15,2) DEFAULT 0.00,
  `status` varchar(30) DEFAULT 'draft',
  `department_id` int(11) DEFAULT NULL,
  `department_user_id` int(11) DEFAULT NULL,
  `priority` varchar(30) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `repair_order_id` (`repair_order_id`),
  KEY `jobwork_no` (`jobwork_no`),
  KEY `status` (`status`),
  CONSTRAINT `fk_repair_jobwork_orders_repair` FOREIGN KEY (`repair_order_id`) REFERENCES `tbl_repair_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Items (mirror repair order items: product details, qty, weight, description)
CREATE TABLE IF NOT EXISTS `tbl_repair_jobwork_order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `repair_jobwork_order_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `product_characteristic_id` int(11) DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `product_name` varchar(255) DEFAULT NULL,
  `design_no` varchar(100) DEFAULT NULL,
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
  `description` text DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `repair_jobwork_order_id` (`repair_jobwork_order_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `fk_repair_jobwork_order_items_order` FOREIGN KEY (`repair_jobwork_order_id`) REFERENCES `tbl_repair_jobwork_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
