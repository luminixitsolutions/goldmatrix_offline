-- Job Work Orders: one record per sale order when user creates job work from sale-order-process
CREATE TABLE IF NOT EXISTS `tbl_job_work_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_work_no` varchar(50) NOT NULL,
  `sale_order_id` int(11) NOT NULL,
  `sale_order_no` varchar(50) NOT NULL,
  `status` varchar(30) DEFAULT 'draft',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `job_work_no` (`job_work_no`),
  KEY `sale_order_id` (`sale_order_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
