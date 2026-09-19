-- Department user master
CREATE TABLE IF NOT EXISTS `tbl_department_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_name` varchar(120) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_department_user_name` (`user_name`),
  KEY `idx_department_user_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Department-wise assigned users
CREATE TABLE IF NOT EXISTS `tbl_department_user_map` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_department_user_map` (`department_id`,`user_id`),
  KEY `idx_department_map_department` (`department_id`),
  KEY `idx_department_map_user` (`user_id`),
  KEY `idx_department_map_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
