-- Employee Management module tables (also auto-created on first page load via auragold_em_ensure_tables)

CREATE TABLE IF NOT EXISTS `tbl_employee_departments` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `branch_id` int NOT NULL DEFAULT 0,
    `name` varchar(120) NOT NULL DEFAULT '',
    `status` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_em_dept_branch` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_employee_designations` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `branch_id` int NOT NULL DEFAULT 0,
    `name` varchar(120) NOT NULL DEFAULT '',
    `status` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_em_desig_branch` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_employee_shifts` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `branch_id` int NOT NULL DEFAULT 0,
    `name` varchar(120) NOT NULL DEFAULT '',
    `start_time` time DEFAULT NULL,
    `end_time` time DEFAULT NULL,
    `status` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_em_shift_branch` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_employee_leave_types` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `branch_id` int NOT NULL DEFAULT 0,
    `name` varchar(120) NOT NULL DEFAULT '',
    `days_per_year` decimal(6,2) NOT NULL DEFAULT 0.00,
    `status` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_em_leave_type_branch` (`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_employees` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `branch_id` int NOT NULL DEFAULT 0,
    `employee_code` varchar(50) NOT NULL DEFAULT '',
    `first_name` varchar(100) NOT NULL DEFAULT '',
    `last_name` varchar(100) NOT NULL DEFAULT '',
    `email` varchar(150) NOT NULL DEFAULT '',
    `phone` varchar(30) NOT NULL DEFAULT '',
    `department_id` int unsigned NOT NULL DEFAULT 0,
    `designation_id` int unsigned NOT NULL DEFAULT 0,
    `shift_id` int unsigned NOT NULL DEFAULT 0,
    `joining_date` date DEFAULT NULL,
    `basic_salary` decimal(14,2) NOT NULL DEFAULT 0.00,
    `address` text,
    `notes` text,
    `status` varchar(20) NOT NULL DEFAULT 'Active',
    `record_status` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_em_emp_branch` (`branch_id`),
    KEY `idx_em_emp_code` (`employee_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_employee_documents` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `branch_id` int NOT NULL DEFAULT 0,
    `employee_id` int unsigned NOT NULL DEFAULT 0,
    `doc_type` varchar(80) NOT NULL DEFAULT '',
    `doc_title` varchar(200) NOT NULL DEFAULT '',
    `file_name` varchar(255) NOT NULL DEFAULT '',
    `file_path` varchar(500) NOT NULL DEFAULT '',
    `expiry_date` date DEFAULT NULL,
    `notes` text,
    `record_status` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_em_doc_emp` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_employee_attendance` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `branch_id` int NOT NULL DEFAULT 0,
    `employee_id` int unsigned NOT NULL DEFAULT 0,
    `attendance_date` date NOT NULL,
    `status` varchar(30) NOT NULL DEFAULT 'Present',
    `check_in` time DEFAULT NULL,
    `check_out` time DEFAULT NULL,
    `notes` varchar(255) NOT NULL DEFAULT '',
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_em_att_emp_date` (`employee_id`,`attendance_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_employee_leave` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `branch_id` int NOT NULL DEFAULT 0,
    `employee_id` int unsigned NOT NULL DEFAULT 0,
    `leave_type_id` int unsigned NOT NULL DEFAULT 0,
    `from_date` date NOT NULL,
    `to_date` date NOT NULL,
    `days` decimal(6,2) NOT NULL DEFAULT 0.00,
    `reason` text,
    `status` varchar(30) NOT NULL DEFAULT 'Pending',
    `approved_by` varchar(120) NOT NULL DEFAULT '',
    `approved_at` datetime DEFAULT NULL,
    `record_status` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_employee_payroll` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `branch_id` int NOT NULL DEFAULT 0,
    `employee_id` int unsigned NOT NULL DEFAULT 0,
    `payroll_month` varchar(7) NOT NULL DEFAULT '',
    `basic_salary` decimal(14,2) NOT NULL DEFAULT 0.00,
    `allowances` decimal(14,2) NOT NULL DEFAULT 0.00,
    `deductions` decimal(14,2) NOT NULL DEFAULT 0.00,
    `net_salary` decimal(14,2) NOT NULL DEFAULT 0.00,
    `payment_date` date DEFAULT NULL,
    `status` varchar(30) NOT NULL DEFAULT 'Draft',
    `notes` varchar(255) NOT NULL DEFAULT '',
    `record_status` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_employee_tasks` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `branch_id` int NOT NULL DEFAULT 0,
    `employee_id` int unsigned NOT NULL DEFAULT 0,
    `title` varchar(200) NOT NULL DEFAULT '',
    `description` text,
    `priority` varchar(20) NOT NULL DEFAULT 'Medium',
    `status` varchar(30) NOT NULL DEFAULT 'Open',
    `due_date` date DEFAULT NULL,
    `completed_at` datetime DEFAULT NULL,
    `record_status` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tbl_employee_performance` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `branch_id` int NOT NULL DEFAULT 0,
    `employee_id` int unsigned NOT NULL DEFAULT 0,
    `review_period` varchar(80) NOT NULL DEFAULT '',
    `review_date` date DEFAULT NULL,
    `rating` decimal(3,1) NOT NULL DEFAULT 0.0,
    `kpi_score` decimal(6,2) NOT NULL DEFAULT 0.00,
    `strengths` text,
    `improvements` text,
    `reviewer_name` varchar(120) NOT NULL DEFAULT '',
    `status` varchar(30) NOT NULL DEFAULT 'Draft',
    `record_status` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
