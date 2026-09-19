-- Log add / reduce weight entries from Manufacturing Process and Jobwork Queue
CREATE TABLE IF NOT EXISTS `tbl_jobwork_weight_adjustments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jobwork_order_id` int(11) NOT NULL,
  `adjustment_type` enum('add','reduce') NOT NULL DEFAULT 'reduce',
  `weight_grams` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `remark` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by_user_id` int(11) DEFAULT NULL,
  `source_department_id` int(11) DEFAULT NULL,
  `source_user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `jobwork_order_id` (`jobwork_order_id`),
  KEY `adjustment_type` (`adjustment_type`),
  KEY `source_department_id` (`source_department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
