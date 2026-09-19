-- =============================================================================
-- GoldMatrix — Department module schema
-- Run in phpMyAdmin / MySQL client against your GoldMatrix database.
--
-- Tables:
--   tbl_departments          — department master (department.php)
--   tbl_department_users     — department user names
--   tbl_department_user_map  — users assigned to each department
--
-- Process options: Manufacturing Inhouse | Manufacturing Outsource
-- Type options:    Wt. Wise | Against of Weight
-- =============================================================================

-- -----------------------------------------------------------------------------
-- tbl_departments
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tbl_departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dept_name` varchar(120) NOT NULL COMMENT 'Display name',
  `short_code` varchar(40) NOT NULL COMMENT 'Unique department code',
  `department_type` varchar(40) NOT NULL DEFAULT 'Wt. Wise' COMMENT 'Wt. Wise | Against of Weight',
  `process_type` varchar(40) NOT NULL DEFAULT 'Manufacturing Inhouse' COMMENT 'Manufacturing Inhouse | Manufacturing Outsource',
  `auto_loss` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=On, 0=Off — auto loss on transfer',
  `auto_profit` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=On, 0=Off',
  `calculate_stock` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=include in stock calculation',
  `progress_percent` decimal(8,2) DEFAULT NULL COMMENT 'Progress In % (optional)',
  `exclude_jobcard_summary` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=exclude from job card summary',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=active, 0=inactive',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_department_short_code` (`short_code`),
  KEY `idx_department_status` (`status`),
  KEY `idx_department_process_type` (`process_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- -----------------------------------------------------------------------------
-- tbl_department_users
-- -----------------------------------------------------------------------------
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

-- -----------------------------------------------------------------------------
-- tbl_department_user_map
-- -----------------------------------------------------------------------------
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

-- -----------------------------------------------------------------------------
-- Upgrade existing data (legacy process labels)
-- -----------------------------------------------------------------------------
UPDATE `tbl_departments`
SET `process_type` = 'Manufacturing Inhouse',
    `updated_at` = NOW()
WHERE `process_type` IN ('Manufacturing', 'Melting', 'Testing');

ALTER TABLE `tbl_departments`
  MODIFY COLUMN `process_type` varchar(40) NOT NULL DEFAULT 'Manufacturing Inhouse'
    COMMENT 'Manufacturing Inhouse | Manufacturing Outsource';
