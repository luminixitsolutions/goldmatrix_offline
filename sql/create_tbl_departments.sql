-- =============================================================================
-- Department master (department.php)
-- Process options: Manufacturing Inhouse | Manufacturing Outsource
-- Type options:    Wt. Wise | Against of Weight
-- =============================================================================

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
