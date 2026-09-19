-- Jobwork Invoice header (links to Job Work Order; invoice_no from Bill Series voucher "Jobwork Invoice")
CREATE TABLE IF NOT EXISTS `tbl_jobwork_invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(50) NOT NULL DEFAULT '',
  `jobwork_order_id` int(11) DEFAULT NULL,
  `repair_jobwork_order_id` int(11) DEFAULT NULL,
  `sale_order_id` int(11) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `grand_total` decimal(15,2) DEFAULT 0.00,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_jwo_id` (`jobwork_order_id`),
  UNIQUE KEY `uniq_repair_jwo` (`repair_jobwork_order_id`),
  KEY `invoice_no` (`invoice_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
