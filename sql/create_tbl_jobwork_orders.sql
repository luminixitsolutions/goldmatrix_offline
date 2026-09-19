-- Job Work Order master and items (against Sale Order)
-- JWO number: JWO-1, JWO-2 (set from id after insert)

-- Master
CREATE TABLE IF NOT EXISTS `tbl_jobwork_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jobwork_no` varchar(50) NOT NULL DEFAULT '',
  `sale_order_id` int(11) NOT NULL,
  `sale_order_no` varchar(50) NOT NULL DEFAULT '',
  `customer_name` varchar(255) DEFAULT NULL,
  `order_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `grand_total` decimal(15,2) DEFAULT 0.00,
  `status` varchar(30) DEFAULT 'draft',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sale_order_id` (`sale_order_id`),
  KEY `jobwork_no` (`jobwork_no`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Items (mirror sale order items for product list)
CREATE TABLE IF NOT EXISTS `tbl_jobwork_order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jobwork_order_id` int(11) NOT NULL,
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
  `status` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `jobwork_order_id` (`jobwork_order_id`),
  KEY `product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
