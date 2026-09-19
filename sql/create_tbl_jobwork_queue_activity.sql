-- One row per successful Jobwork Queue modal Save (transfer) from Manufacturing / Jobwork Queue
CREATE TABLE IF NOT EXISTS `tbl_jobwork_queue_activity` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jobwork_order_id` int(11) NOT NULL,
  `jobwork_queue_no` varchar(50) NOT NULL DEFAULT '',
  `to_dept_id` int(11) DEFAULT NULL,
  `to_user_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `jobwork_order_id` (`jobwork_order_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
