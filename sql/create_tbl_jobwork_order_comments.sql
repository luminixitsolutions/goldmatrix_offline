-- Threaded log of comments on a job work order (Manufacturing Process Comments modal)
CREATE TABLE IF NOT EXISTS `tbl_jobwork_order_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `jobwork_order_id` int(11) NOT NULL,
  `comment_text` varchar(2000) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by_user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `jobwork_order_id` (`jobwork_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
