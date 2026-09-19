<?php

/**
 * Employee Management — tables, seed defaults, CRUD helpers.
 */

if (!function_exists('auragold_em_resolve_branch_id')) {
    function auragold_em_resolve_branch_id(?int $explicit = null): int
    {
        if ($explicit !== null && $explicit > 0) {
            if (!function_exists('auragold_settings_branch_id_valid') || auragold_settings_branch_id_valid($explicit)) {
                return $explicit;
            }
        }
        return function_exists('auragold_settings_branch_id') ? (int) auragold_settings_branch_id() : 0;
    }
}

if (!function_exists('auragold_em_esc')) {
    function auragold_em_esc($conn, $value): string
    {
        return mysqli_real_escape_string($conn, (string) $value);
    }
}

if (!function_exists('auragold_em_ensure_tables')) {
    function auragold_em_ensure_tables($conn): bool
    {
        if (!$conn instanceof mysqli) {
            return false;
        }

        $queries = [
            "CREATE TABLE IF NOT EXISTS `tbl_employee_departments` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `branch_id` int NOT NULL DEFAULT 0,
                `name` varchar(120) NOT NULL DEFAULT '',
                `status` tinyint(1) NOT NULL DEFAULT 1,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_em_dept_branch` (`branch_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `tbl_employee_designations` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `branch_id` int NOT NULL DEFAULT 0,
                `department_id` int unsigned NOT NULL DEFAULT 0,
                `name` varchar(120) NOT NULL DEFAULT '',
                `status` tinyint(1) NOT NULL DEFAULT 1,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_em_desig_branch` (`branch_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `tbl_employee_shifts` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `branch_id` int NOT NULL DEFAULT 0,
                `name` varchar(120) NOT NULL DEFAULT '',
                `start_time` time DEFAULT NULL,
                `end_time` time DEFAULT NULL,
                `status` tinyint(1) NOT NULL DEFAULT 1,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_em_shift_branch` (`branch_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `tbl_employee_leave_types` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `branch_id` int NOT NULL DEFAULT 0,
                `name` varchar(120) NOT NULL DEFAULT '',
                `days_per_year` decimal(6,2) NOT NULL DEFAULT 0.00,
                `status` tinyint(1) NOT NULL DEFAULT 1,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_em_leave_type_branch` (`branch_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `tbl_employees` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `branch_id` int NOT NULL DEFAULT 0,
                `user_id` int unsigned NOT NULL DEFAULT 0,
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
                KEY `idx_em_emp_user` (`user_id`),
                KEY `idx_em_emp_code` (`employee_code`),
                KEY `idx_em_emp_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `tbl_employee_documents` (
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
                KEY `idx_em_doc_emp` (`employee_id`),
                KEY `idx_em_doc_branch` (`branch_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `tbl_employee_attendance` (
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
                UNIQUE KEY `uq_em_att_emp_date` (`employee_id`,`attendance_date`),
                KEY `idx_em_att_branch` (`branch_id`),
                KEY `idx_em_att_date` (`attendance_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `tbl_employee_leave` (
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
                PRIMARY KEY (`id`),
                KEY `idx_em_leave_emp` (`employee_id`),
                KEY `idx_em_leave_branch` (`branch_id`),
                KEY `idx_em_leave_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `tbl_employee_payroll` (
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
                PRIMARY KEY (`id`),
                KEY `idx_em_pay_emp` (`employee_id`),
                KEY `idx_em_pay_branch` (`branch_id`),
                KEY `idx_em_pay_month` (`payroll_month`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `tbl_employee_tasks` (
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
                PRIMARY KEY (`id`),
                KEY `idx_em_task_emp` (`employee_id`),
                KEY `idx_em_task_branch` (`branch_id`),
                KEY `idx_em_task_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `tbl_employee_performance` (
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
                PRIMARY KEY (`id`),
                KEY `idx_em_perf_emp` (`employee_id`),
                KEY `idx_em_perf_branch` (`branch_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `tbl_employee_advances` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `branch_id` int NOT NULL DEFAULT 0,
                `employee_id` int unsigned NOT NULL DEFAULT 0,
                `advance_date` date NOT NULL,
                `amount` decimal(14,2) NOT NULL DEFAULT 0.00,
                `approved_amount` decimal(14,2) DEFAULT NULL,
                `recovered` decimal(14,2) NOT NULL DEFAULT 0.00,
                `status` varchar(30) NOT NULL DEFAULT 'Pending',
                `payroll_month` varchar(7) NOT NULL DEFAULT '',
                `requested_by` varchar(120) NOT NULL DEFAULT '',
                `approved_by` varchar(120) NOT NULL DEFAULT '',
                `approved_at` datetime DEFAULT NULL,
                `notes` text,
                `record_status` tinyint(1) NOT NULL DEFAULT 1,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_em_adv_emp` (`employee_id`),
                KEY `idx_em_adv_branch` (`branch_id`),
                KEY `idx_em_adv_status` (`status`),
                KEY `idx_em_adv_month` (`payroll_month`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `tbl_expense_categories` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL DEFAULT '',
                `type` varchar(100) DEFAULT NULL,
                `sort_order` int(11) DEFAULT 0,
                `status` tinyint(1) DEFAULT 1,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `name` (`name`),
                KEY `status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS `tbl_employee_expenses` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `branch_id` int NOT NULL DEFAULT 0,
                `employee_id` int unsigned NOT NULL DEFAULT 0,
                `category_id` int unsigned NOT NULL DEFAULT 0,
                `category_name` varchar(255) NOT NULL DEFAULT '',
                `expense_date` date NOT NULL,
                `amount` decimal(14,2) NOT NULL DEFAULT 0.00,
                `payment_mode` varchar(50) NOT NULL DEFAULT 'Cash',
                `description` text,
                `status` varchar(30) NOT NULL DEFAULT 'Paid',
                `created_by` varchar(120) NOT NULL DEFAULT '',
                `record_status` tinyint(1) NOT NULL DEFAULT 1,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_em_exp_branch` (`branch_id`),
                KEY `idx_em_exp_emp` (`employee_id`),
                KEY `idx_em_exp_cat` (`category_id`),
                KEY `idx_em_exp_date` (`expense_date`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($queries as $sql) {
            if (!@mysqli_query($conn, $sql)) {
                return false;
            }
        }

        auragold_em_ensure_attendance_punch_schema($conn);
        auragold_em_ensure_employee_user_link_schema($conn);
        auragold_em_ensure_advance_approved_amount_schema($conn);
        auragold_em_ensure_designation_department_schema($conn);
        auragold_em_ensure_payroll_detail_schema($conn);
        auragold_em_seed_expense_categories($conn);

        return true;
    }
}

if (!function_exists('auragold_em_ensure_payroll_detail_schema')) {
    function auragold_em_ensure_payroll_detail_schema($conn): void
    {
        if (!$conn instanceof mysqli) {
            return;
        }
        $chk = @mysqli_query($conn, "SHOW COLUMNS FROM `tbl_employee_payroll` LIKE 'payroll_detail_json'");
        if ($chk && mysqli_num_rows($chk) === 0) {
            @mysqli_query(
                $conn,
                'ALTER TABLE `tbl_employee_payroll` ADD COLUMN `payroll_detail_json` mediumtext NULL AFTER `notes`'
            );
        }
        if ($chk) {
            mysqli_free_result($chk);
        }
    }
}

if (!function_exists('auragold_em_payroll_detail_defaults')) {
    /** @return array<string, mixed> */
    function auragold_em_payroll_detail_defaults(): array
    {
        return [
            'no_of_days' => 0,
            'present_days' => 0,
            'absent_days' => 0,
            'monthly_salary' => 0.0,
            'gross_salary' => 0.0,
            'hra' => 0.0,
            'da' => 0.0,
            'conveyance' => 0.0,
            'professional_tax' => 0.0,
            'pf' => 0.0,
            'esic' => 0.0,
            'tds' => 0.0,
            'advance_salary' => 0.0,
            'other_deduction' => 0.0,
            'uan_no' => '',
            'esic_no' => '',
            'salary_arrears' => 0.0,
        ];
    }
}

if (!function_exists('auragold_em_payroll_detail_decode')) {
    /** @return array<string, mixed> */
    function auragold_em_payroll_detail_decode($json): array
    {
        $defaults = auragold_em_payroll_detail_defaults();
        if (!is_string($json) || trim($json) === '') {
            return $defaults;
        }
        $decoded = json_decode($json, true);

        return is_array($decoded) ? array_merge($defaults, $decoded) : $defaults;
    }
}

if (!function_exists('auragold_em_payroll_detail_from_request')) {
    /** @return array<string, mixed> */
    function auragold_em_payroll_detail_from_request(array $data): array
    {
        $d = auragold_em_payroll_detail_defaults();
        $floatKeys = [
            'no_of_days', 'present_days', 'absent_days', 'monthly_salary', 'gross_salary',
            'hra', 'da', 'conveyance', 'professional_tax', 'pf', 'esic', 'tds',
            'advance_salary', 'other_deduction', 'salary_arrears',
        ];
        foreach ($floatKeys as $k) {
            $d[$k] = (float) ($data[$k] ?? 0);
        }
        $d['uan_no'] = trim((string) ($data['uan_no'] ?? ''));
        $d['esic_no'] = trim((string) ($data['esic_no'] ?? ''));

        return $d;
    }
}

if (!function_exists('auragold_em_payroll_month_from_parts')) {
    function auragold_em_payroll_month_from_parts(string $monthPart, string $yearPart): string
    {
        $year = preg_replace('/[^0-9]/', '', $yearPart);
        $mon = preg_replace('/[^0-9]/', '', $monthPart);
        if (strlen($year) !== 4) {
            $year = date('Y');
        }
        $monInt = (int) $mon;
        if ($monInt < 1 || $monInt > 12) {
            $monInt = (int) date('n');
        }

        return sprintf('%04d-%02d', (int) $year, $monInt);
    }
}

if (!function_exists('auragold_em_payroll_calc_context')) {
    /**
     * Attendance + advance summary for payroll form (present/absent, prorated gross, advance).
     *
     * @return array<string, mixed>
     */
    function auragold_em_payroll_calc_context($conn, int $branch_id, int $employee_id, string $month, float $monthlySalary = 0.0): array
    {
        $month = trim($month);
        $defaults = [
            'no_of_days' => 0,
            'present_days' => 0,
            'absent_days' => 0,
            'monthly_salary' => $monthlySalary,
            'daily_rate' => 0.0,
            'gross_salary' => 0.0,
            'basic_salary' => 0.0,
            'advance_salary' => 0.0,
        ];
        if ($employee_id <= 0 || !preg_match('/^\d{4}-\d{2}$/', $month)) {
            return $defaults;
        }

        auragold_em_ensure_tables($conn);
        $monthStart = $month . '-01';
        $noOfDays = (int) date('t', strtotime($monthStart));
        $monthEnd = date('Y-m-t', strtotime($monthStart));
        $today = date('Y-m-d');
        $countEnd = $monthEnd;
        if ($today < $monthEnd) {
            $countEnd = $today;
        }

        $joinYmd = '';
        if ($monthlySalary <= 0) {
            $empRow = auragold_em_get_employee_by_id($conn, $employee_id, $branch_id);
            $monthlySalary = (float) ($empRow['basic_salary'] ?? 0);
        } else {
            $empRow = auragold_em_get_employee_by_id($conn, $employee_id, $branch_id);
        }
        if (is_array($empRow) && !empty($empRow['joining_date'])) {
            $joinYmd = date('Y-m-d', strtotime((string) $empRow['joining_date']));
        }

        $present = 0;
        $absent = 0;
        if ($countEnd >= $monthStart) {
            $fromEsc = auragold_em_esc($conn, $monthStart);
            $toEsc = auragold_em_esc($conn, $countEnd);
            $bs = auragold_em_branch_sql($branch_id, 'a');
            $rows = getList(
                "SELECT a.attendance_date, a.status
                 FROM tbl_employee_attendance a
                 WHERE a.employee_id = " . (int) $employee_id . "
                   AND a.attendance_date BETWEEN '$fromEsc' AND '$toEsc'
                   $bs"
            );
            $attByDate = [];
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $d = (string) ($row['attendance_date'] ?? '');
                    if ($d !== '') {
                        $attByDate[$d] = $row;
                    }
                }
            }

            $cur = strtotime($monthStart);
            $endTs = strtotime($countEnd);
            while ($cur !== false && $cur <= $endTs) {
                $ymd = date('Y-m-d', $cur);
                if ($joinYmd !== '' && $ymd < $joinYmd) {
                    $cur = strtotime('+1 day', $cur);
                    continue;
                }
                $attRow = $attByDate[$ymd] ?? null;
                if ($attRow) {
                    $st = strtolower(trim((string) ($attRow['status'] ?? '')));
                    if (strpos($st, 'present') !== false || $st === 'half day') {
                        $present++;
                    } else {
                        $absent++;
                    }
                } else {
                    $absent++;
                }
                $cur = strtotime('+1 day', $cur);
            }
        }

        $dailyRate = $noOfDays > 0 ? ($monthlySalary / $noOfDays) : 0.0;
        $grossSalary = round($dailyRate * $present, 2);

        $advInfo = auragold_em_approved_advances_for_month($conn, $branch_id, $month, $employee_id);
        $advanceSalary = (float) ($advInfo['by_employee'][$employee_id]['amount'] ?? 0);

        return [
            'no_of_days' => $noOfDays,
            'present_days' => $present,
            'absent_days' => $absent,
            'monthly_salary' => $monthlySalary,
            'daily_rate' => round($dailyRate, 2),
            'gross_salary' => $grossSalary,
            'basic_salary' => $grossSalary,
            'advance_salary' => $advanceSalary,
        ];
    }
}

if (!function_exists('auragold_em_advance_limit_info')) {
    /**
     * Max advance = 40% of (monthly_salary / days_in_month) × present_days.
     *
     * @return array<string, mixed>
     */
    function auragold_em_advance_limit_info($conn, int $branch_id, int $employee_id, string $advanceDate = '', int $excludeAdvanceId = 0): array
    {
        if ($advanceDate === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $advanceDate)) {
            $advanceDate = date('Y-m-d');
        }
        $month = substr($advanceDate, 0, 7);
        $calc = auragold_em_payroll_calc_context($conn, $branch_id, $employee_id, $month);
        $monthlySalary = (float) ($calc['monthly_salary'] ?? 0);
        $noOfDays = (int) ($calc['no_of_days'] ?? 0);
        $presentDays = (int) ($calc['present_days'] ?? 0);
        $perDay = $noOfDays > 0 ? ($monthlySalary / $noOfDays) : 0.0;
        $earned = round($perDay * $presentDays, 2);
        $maxTotal = round($earned * 0.40, 2);

        $monthEsc = auragold_em_esc($conn, $month);
        $bs = auragold_em_branch_sql($branch_id);
        $excludeSql = $excludeAdvanceId > 0 ? (' AND id <> ' . (int) $excludeAdvanceId) : '';
        $usedRow = getRecord(
            "SELECT COALESCE(SUM(
                CASE
                    WHEN status = 'Approved' THEN COALESCE(approved_amount, amount)
                    ELSE amount
                END
             ), 0) AS used_amount
             FROM tbl_employee_advances
             WHERE record_status = 1
               AND employee_id = " . (int) $employee_id . "
               AND status IN ('Pending', 'Approved')
               AND (
                    payroll_month = '$monthEsc'
                    OR (payroll_month = '' AND advance_date >= '$monthEsc-01' AND advance_date <= '" . auragold_em_esc($conn, date('Y-m-t', strtotime($month . '-01'))) . "')
               )
               $bs
               $excludeSql"
        );
        $usedAmount = (float) ($usedRow['used_amount'] ?? 0);
        $maxAllowed = max(0, round($maxTotal - $usedAmount, 2));

        return [
            'month' => $month,
            'monthly_salary' => $monthlySalary,
            'no_of_days' => $noOfDays,
            'present_days' => $presentDays,
            'per_day_salary' => round($perDay, 2),
            'earned_salary' => $earned,
            'max_percent' => 40,
            'max_advance_total' => $maxTotal,
            'used_advance' => $usedAmount,
            'max_advance' => $maxAllowed,
        ];
    }
}

if (!function_exists('auragold_em_validate_advance_amount')) {
    function auragold_em_validate_advance_amount($conn, int $branch_id, int $employee_id, float $amount, string $advanceDate = '', int $excludeAdvanceId = 0): array
    {
        if ($amount <= 0) {
            return ['ok' => false, 'message' => 'Advance amount must be greater than zero.'];
        }
        $info = auragold_em_advance_limit_info($conn, $branch_id, $employee_id, $advanceDate, $excludeAdvanceId);
        $max = (float) ($info['max_advance'] ?? 0);
        $maxTotal = (float) ($info['max_advance_total'] ?? 0);
        if ($max <= 0 && (int) ($info['present_days'] ?? 0) <= 0) {
            return [
                'ok' => false,
                'message' => 'No present attendance found for this month. Advance request is not allowed.',
            ];
        }
        if ($max <= 0 && $maxTotal > 0) {
            return [
                'ok' => false,
                'message' => 'Advance limit for this month is already used (40% cap: '
                    . number_format($maxTotal, 2) . ').',
            ];
        }
        if ($amount > $max + 0.009) {
            return [
                'ok' => false,
                'message' => 'Advance cannot exceed '
                    . number_format($max, 2)
                    . ' (40% cap: '
                    . number_format($maxTotal, 2)
                    . ' for '
                    . (int) ($info['present_days'] ?? 0)
                    . ' present day(s)).',
            ];
        }

        return ['ok' => true, 'message' => '', 'info' => $info];
    }
}

if (!function_exists('auragold_em_ensure_designation_department_schema')) {
    function auragold_em_ensure_designation_department_schema($conn): void
    {
        if (!$conn instanceof mysqli) {
            return;
        }
        $chk = @mysqli_query($conn, "SHOW COLUMNS FROM `tbl_employee_designations` LIKE 'department_id'");
        if ($chk && mysqli_num_rows($chk) === 0) {
            @mysqli_query(
                $conn,
                'ALTER TABLE `tbl_employee_designations` ADD COLUMN `department_id` int unsigned NOT NULL DEFAULT 0 AFTER `branch_id`'
            );
        }
        if ($chk) {
            mysqli_free_result($chk);
        }
        $idx = @mysqli_query($conn, "SHOW INDEX FROM `tbl_employee_designations` WHERE Key_name = 'idx_em_desig_department'");
        if ($idx && mysqli_num_rows($idx) === 0) {
            @mysqli_query(
                $conn,
                'ALTER TABLE `tbl_employee_designations` ADD KEY `idx_em_desig_department` (`department_id`)'
            );
        }
        if ($idx) {
            mysqli_free_result($idx);
        }
    }
}

if (!function_exists('auragold_em_ensure_advance_approved_amount_schema')) {
    function auragold_em_ensure_advance_approved_amount_schema($conn): void
    {
        if (!$conn instanceof mysqli) {
            return;
        }
        $chk = @mysqli_query($conn, "SHOW COLUMNS FROM `tbl_employee_advances` LIKE 'approved_amount'");
        if ($chk && mysqli_num_rows($chk) === 0) {
            @mysqli_query(
                $conn,
                'ALTER TABLE `tbl_employee_advances` ADD COLUMN `approved_amount` decimal(14,2) DEFAULT NULL AFTER `amount`'
            );
        }
        if ($chk) {
            mysqli_free_result($chk);
        }
    }
}

if (!function_exists('auragold_em_ensure_employee_user_link_schema')) {
    function auragold_em_ensure_employee_user_link_schema($conn): void
    {
        if (!$conn instanceof mysqli) {
            return;
        }
        $chk = @mysqli_query($conn, "SHOW COLUMNS FROM `tbl_employees` LIKE 'user_id'");
        if ($chk && mysqli_num_rows($chk) === 0) {
            @mysqli_query($conn, 'ALTER TABLE `tbl_employees` ADD COLUMN `user_id` int unsigned NOT NULL DEFAULT 0 AFTER `branch_id`');
        }
        if ($chk) {
            mysqli_free_result($chk);
        }
        $idx = @mysqli_query($conn, "SHOW INDEX FROM `tbl_employees` WHERE Key_name = 'idx_em_emp_user'");
        if ($idx && mysqli_num_rows($idx) === 0) {
            @mysqli_query($conn, 'ALTER TABLE `tbl_employees` ADD KEY `idx_em_emp_user` (`user_id`)');
        }
        if ($idx) {
            mysqli_free_result($idx);
        }
    }
}

if (!function_exists('auragold_em_user_belongs_to_branch')) {
    function auragold_em_user_belongs_to_branch(array $user, int $branch_id): bool
    {
        if ($branch_id <= 0) {
            return true;
        }
        if (!function_exists('auragold_um_parse_branch_ids_string')) {
            require_once __DIR__ . '/user_management_schema.php';
        }
        $ids = auragold_um_parse_branch_ids_string($user['user_branch_ids'] ?? '');
        if ($ids === []) {
            return true;
        }
        return in_array($branch_id, $ids, true);
    }
}

if (!function_exists('auragold_em_sync_users_to_employees')) {
    /**
     * Mirror active Settings → Users into tbl_employees for attendance/payroll modules.
     */
    function auragold_em_sync_users_to_employees($conn, int $branch_id): int
    {
        if (!$conn instanceof mysqli || $branch_id <= 0) {
            return 0;
        }
        auragold_em_ensure_tables($conn);
        auragold_em_ensure_employee_user_link_schema($conn);
        if (!function_exists('auragold_ensure_user_management_columns')) {
            require_once __DIR__ . '/user_management_schema.php';
        }
        auragold_ensure_user_management_columns($conn);

        $users = getList(
            "SELECT id, Fname, Lname, EmailId, Phone, Status, user_role, user_branch_ids,
                    monthly_salary, department_id, designation_id
             FROM tbl_users
             WHERE Status = '1'
             ORDER BY id ASC"
        );
        if (!is_array($users)) {
            return 0;
        }

        $synced = 0;
        foreach ($users as $user) {
            if (!auragold_em_user_belongs_to_branch($user, $branch_id)) {
                continue;
            }
            $uid = (int) ($user['id'] ?? 0);
            if ($uid <= 0) {
                continue;
            }
            $first = trim((string) ($user['Fname'] ?? ''));
            $last = trim((string) ($user['Lname'] ?? ''));
            if ($first === '' && $last === '') {
                $first = 'User';
                $last = (string) $uid;
            }
            $email = trim((string) ($user['EmailId'] ?? ''));
            $phone = trim((string) ($user['Phone'] ?? ''));
            $role = trim((string) ($user['user_role'] ?? ''));
            $notes = $role !== '' ? 'User role: ' . $role : '';
            $salary = (float) ($user['monthly_salary'] ?? 0);
            $departmentId = (int) ($user['department_id'] ?? 0);
            $designationId = (int) ($user['designation_id'] ?? 0);
            if ($salary < 0) {
                $salary = 0;
            }

            $existing = getRecord(
                'SELECT id, employee_code FROM tbl_employees
                 WHERE user_id = ' . $uid . ' AND record_status = 1'
                . auragold_em_branch_sql($branch_id)
                . ' LIMIT 1'
            );

            if ($existing) {
                $eid = (int) ($existing['id'] ?? 0);
                $sql = 'UPDATE tbl_employees SET '
                    . "first_name = '" . auragold_em_esc($conn, $first) . "', "
                    . "last_name = '" . auragold_em_esc($conn, $last) . "', "
                    . "email = '" . auragold_em_esc($conn, $email) . "', "
                    . "phone = '" . auragold_em_esc($conn, $phone) . "', "
                    . 'department_id = ' . $departmentId . ', '
                    . 'designation_id = ' . $designationId . ', '
                    . 'basic_salary = ' . number_format($salary, 2, '.', '') . ', '
                    . "status = 'Active', "
                    . 'notes = IF(notes = \'\' OR notes IS NULL, \''
                    . auragold_em_esc($conn, $notes) . '\', notes) '
                    . "WHERE id = $eid AND branch_id = " . (int) $branch_id . ' LIMIT 1';
            } else {
                $code = 'USR' . str_pad((string) $uid, 4, '0', STR_PAD_LEFT);
                $dupCode = getRecord(
                    "SELECT id FROM tbl_employees WHERE employee_code = '" . auragold_em_esc($conn, $code) . "'"
                    . auragold_em_branch_sql($branch_id)
                    . ' LIMIT 1'
                );
                if ($dupCode) {
                    $code = auragold_em_next_employee_code($conn, $branch_id);
                }
                $sql = 'INSERT INTO tbl_employees
                    (branch_id, user_id, employee_code, first_name, last_name, email, phone, department_id, designation_id, basic_salary, status, notes, record_status, joining_date)
                    VALUES ('
                    . (int) $branch_id . ', '
                    . $uid . ", '"
                    . auragold_em_esc($conn, $code) . "', '"
                    . auragold_em_esc($conn, $first) . "', '"
                    . auragold_em_esc($conn, $last) . "', '"
                    . auragold_em_esc($conn, $email) . "', '"
                    . auragold_em_esc($conn, $phone) . "', "
                    . $departmentId . ', '
                    . $designationId . ', '
                    . number_format($salary, 2, '.', '') . ", 'Active', '"
                    . auragold_em_esc($conn, $notes) . "', 1, CURDATE())";
            }

            if (@mysqli_query($conn, $sql)) {
                // Repair draft payroll rows created before the user's configured
                // monthly salary was mirrored into tbl_employees.
                if ($salary > 0) {
                    $salarySql = number_format($salary, 2, '.', '');
                    @mysqli_query(
                        $conn,
                        "UPDATE tbl_employee_payroll
                         SET basic_salary = $salarySql,
                             net_salary = $salarySql + allowances - deductions
                         WHERE employee_id = " . (int) ($existing['id'] ?? mysqli_insert_id($conn)) . "
                           AND branch_id = " . (int) $branch_id . "
                           AND record_status = 1
                           AND status = 'Draft'
                           AND basic_salary = 0"
                    );
                }
                $synced++;
            }
        }

        return $synced;
    }
}

if (!function_exists('auragold_em_sync_user_to_employee_branches')) {
    function auragold_em_sync_user_to_employee_branches($conn, int $user_id): void
    {
        if (!$conn instanceof mysqli || $user_id <= 0) {
            return;
        }
        if (!function_exists('auragold_ensure_user_management_columns')) {
            require_once __DIR__ . '/user_management_schema.php';
        }
        auragold_ensure_user_management_columns($conn);
        $user = getRecord('SELECT * FROM tbl_users WHERE id = ' . $user_id . ' LIMIT 1');
        if (!$user) {
            return;
        }
        $branchIds = auragold_um_parse_branch_ids_string($user['user_branch_ids'] ?? '');
        if ($branchIds === []) {
            $branchIds = [auragold_em_resolve_branch_id()];
        }
        foreach ($branchIds as $bid) {
            auragold_em_sync_users_to_employees($conn, (int) $bid);
        }
    }
}

if (!function_exists('auragold_em_session_user_id')) {
    function auragold_em_session_user_id(): int
    {
        $uid = (int) ($_SESSION['user_id'] ?? 0);
        if ($uid > 0) {
            return $uid;
        }
        if (!empty($_SESSION['Admin']) && is_array($_SESSION['Admin'])) {
            foreach ($_SESSION['Admin'] as $k => $v) {
                if (strcasecmp((string) $k, 'id') === 0) {
                    return (int) $v;
                }
            }
        }
        return 0;
    }
}

if (!function_exists('auragold_em_is_admin_manager')) {
    /**
     * HR/admin: can manage all employees. Non-admin users only see/act on their own employee row.
     */
    function auragold_em_is_admin_manager(): bool
    {
        if (!function_exists('auragold_session_is_superadmin')) {
            require_once __DIR__ . '/auragold_superadmin.php';
        }
        if (function_exists('auragold_session_is_superadmin') && auragold_session_is_superadmin()) {
            return true;
        }

        $src = isset($_SESSION['login_source']) ? (string) $_SESSION['login_source'] : '';
        // Branch login (main) acts as full admin for employee management.
        if ($src === 'branch') {
            if (!function_exists('auragold_session_user_role')) {
                require_once __DIR__ . '/session_login_type.php';
            }
            return auragold_session_user_role() === 'admin';
        }

        // tbl_users: use the Users → Role value (Admin / HR / etc.).
        $role = '';
        if (!empty($_SESSION['Admin']) && is_array($_SESSION['Admin'])) {
            foreach (['user_role', 'UserRole', 'role', 'Role'] as $key) {
                if (isset($_SESSION['Admin'][$key]) && trim((string) $_SESSION['Admin'][$key]) !== '') {
                    $role = trim((string) $_SESSION['Admin'][$key]);
                    break;
                }
            }
        }
        if ($role === '') {
            // Default column is Admin when unset historically.
            return true;
        }
        $n = strtolower($role);
        if (in_array($n, ['admin', 'administrator', 'super admin', 'superadmin', 'hr', 'hr admin', 'hr manager', 'owner', 'manager'], true)) {
            return true;
        }
        if (strpos($n, 'admin') !== false || strpos($n, 'hr') !== false) {
            return true;
        }
        return false;
    }
}

if (!function_exists('auragold_em_current_employee_id')) {
    function auragold_em_current_employee_id($conn, int $branch_id = 0): int
    {
        $uid = auragold_em_session_user_id();
        if ($uid <= 0 || !($conn instanceof mysqli)) {
            return 0;
        }
        if ($branch_id <= 0) {
            $branch_id = auragold_em_resolve_branch_id();
        }
        auragold_em_ensure_tables($conn);
        auragold_em_ensure_employee_user_link_schema($conn);
        // Ensure this login has an employee mirror row.
        auragold_em_sync_users_to_employees($conn, $branch_id);
        $row = getRecord(
            'SELECT id FROM tbl_employees
             WHERE user_id = ' . (int) $uid . '
               AND record_status = 1'
            . auragold_em_branch_sql($branch_id)
            . ' LIMIT 1'
        );
        return (int) ($row['id'] ?? 0);
    }
}

if (!function_exists('auragold_em_access_scope')) {
    /**
     * @return array{is_admin:bool,employee_id:int}
     * employee_id > 0 means restrict all lists/actions to that employee.
     */
    function auragold_em_access_scope($conn, int $branch_id = 0): array
    {
        if ($branch_id <= 0) {
            $branch_id = auragold_em_resolve_branch_id();
        }
        if (auragold_em_is_admin_manager()) {
            return ['is_admin' => true, 'employee_id' => 0];
        }
        return [
            'is_admin' => false,
            'employee_id' => auragold_em_current_employee_id($conn, $branch_id),
        ];
    }
}

if (!function_exists('auragold_em_assert_employee_access')) {
    /**
     * @return array{ok:bool,message:string,employee_id:int}
     */
    function auragold_em_assert_employee_access($conn, int $branch_id, int $employee_id): array
    {
        $scope = auragold_em_access_scope($conn, $branch_id);
        if (!empty($scope['is_admin'])) {
            if ($employee_id <= 0) {
                return ['ok' => false, 'message' => 'Employee is required.', 'employee_id' => 0];
            }
            return ['ok' => true, 'message' => '', 'employee_id' => $employee_id];
        }
        $mine = (int) ($scope['employee_id'] ?? 0);
        if ($mine <= 0) {
            return ['ok' => false, 'message' => 'No employee profile is linked to your login.', 'employee_id' => 0];
        }
        // Force self even if another id was posted.
        if ($employee_id > 0 && $employee_id !== $mine) {
            return ['ok' => false, 'message' => 'You can only access your own employee records.', 'employee_id' => $mine];
        }
        return ['ok' => true, 'message' => '', 'employee_id' => $mine];
    }
}

if (!function_exists('auragold_em_scoped_employees')) {
    function auragold_em_scoped_employees($conn, int $branch_id, string $status = 'Active'): array
    {
        $all = auragold_em_get_employees($conn, $branch_id, $status);
        $scope = auragold_em_access_scope($conn, $branch_id);
        if (!empty($scope['is_admin'])) {
            return $all;
        }
        $mine = (int) ($scope['employee_id'] ?? 0);
        if ($mine <= 0) {
            return [];
        }
        $out = [];
        foreach ($all as $emp) {
            if ((int) ($emp['id'] ?? 0) === $mine) {
                $out[] = $emp;
            }
        }
        return $out;
    }
}

if (!function_exists('auragold_em_ensure_attendance_punch_schema')) {
    function auragold_em_ensure_attendance_punch_schema($conn): void
    {
        if (!$conn instanceof mysqli) {
            return;
        }
        $cols = [
            'punch_in_at'  => "ALTER TABLE `tbl_employee_attendance` ADD COLUMN `punch_in_at` datetime DEFAULT NULL AFTER `check_out`",
            'punch_out_at' => "ALTER TABLE `tbl_employee_attendance` ADD COLUMN `punch_out_at` datetime DEFAULT NULL AFTER `punch_in_at`",
        ];
        foreach ($cols as $col => $sql) {
            $chk = @mysqli_query($conn, "SHOW COLUMNS FROM `tbl_employee_attendance` LIKE '$col'");
            if ($chk && mysqli_num_rows($chk) === 0) {
                @mysqli_query($conn, $sql);
            }
            if ($chk) {
                mysqli_free_result($chk);
            }
        }
        $idx = @mysqli_query($conn, "SHOW INDEX FROM `tbl_employee_attendance` WHERE Key_name = 'uq_em_att_emp_date'");
        if ($idx && mysqli_num_rows($idx) > 0) {
            @mysqli_query($conn, 'ALTER TABLE `tbl_employee_attendance` DROP INDEX `uq_em_att_emp_date`');
        }
        if ($idx) {
            mysqli_free_result($idx);
        }
        $idx2 = @mysqli_query($conn, "SHOW INDEX FROM `tbl_employee_attendance` WHERE Key_name = 'idx_em_att_open'");
        if ($idx2 && mysqli_num_rows($idx2) === 0) {
            @mysqli_query($conn, 'ALTER TABLE `tbl_employee_attendance` ADD KEY `idx_em_att_open` (`employee_id`, `punch_out_at`)');
        }
        if ($idx2) {
            mysqli_free_result($idx2);
        }
    }
}

if (!function_exists('auragold_em_attendance_stale_hours')) {
    function auragold_em_attendance_stale_hours(): int
    {
        return 15;
    }
}

if (!function_exists('auragold_em_format_datetime')) {
    function auragold_em_format_datetime($value): string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '0000-00-00 00:00:00') {
            return '—';
        }
        $ts = strtotime($value);
        return $ts ? date('d M Y H:i:s', $ts) : $value;
    }
}

if (!function_exists('auragold_em_sync_attendance_time_columns')) {
    function auragold_em_sync_attendance_time_columns(array $row): array
    {
        if (!empty($row['punch_in_at'])) {
            $ts = strtotime((string) $row['punch_in_at']);
            if ($ts) {
                $row['check_in'] = date('H:i:s', $ts);
            }
        }
        if (!empty($row['punch_out_at'])) {
            $ts = strtotime((string) $row['punch_out_at']);
            if ($ts) {
                $row['check_out'] = date('H:i:s', $ts);
            }
        }
        return $row;
    }
}

if (!function_exists('auragold_em_close_stale_open_punches')) {
    /**
     * Punch in without punch out for 15+ hours → mark Absent so employee can punch in again today.
     */
    function auragold_em_close_stale_open_punches($conn, int $branch_id): int
    {
        auragold_em_ensure_attendance_punch_schema($conn);
        $hrs = auragold_em_attendance_stale_hours();
        $bs = auragold_em_branch_sql($branch_id);
        $note = auragold_em_esc($conn, 'Auto absent: no punch out within ' . $hrs . ' hours.');
        // status <> 'Absent' keeps this from re-appending the note on every page
        // load; LEFT(..., 255) guards the varchar(255) notes column either way.
        $sql = "UPDATE tbl_employee_attendance
                SET status = 'Absent',
                    notes = LEFT(IF(notes = '' OR notes IS NULL, '$note', CONCAT(notes, ' ', '$note')), 255)
                WHERE punch_in_at IS NOT NULL
                  AND punch_out_at IS NULL
                  AND status <> 'Absent'
                  AND punch_in_at <= DATE_SUB(NOW(), INTERVAL $hrs HOUR)
                  $bs";
        try {
            @mysqli_query($conn, $sql);
        } catch (mysqli_sql_exception $e) {
            error_log('auragold_em_close_stale_open_punches: ' . $e->getMessage());
            return 0;
        }
        return (int) mysqli_affected_rows($conn);
    }
}

if (!function_exists('auragold_em_get_open_punch')) {
    function auragold_em_get_open_punch($conn, int $employee_id, int $branch_id): ?array
    {
        if ($employee_id <= 0) {
            return null;
        }
        auragold_em_close_stale_open_punches($conn, $branch_id);
        $hrs = auragold_em_attendance_stale_hours();
        $row = getRecord(
            "SELECT * FROM tbl_employee_attendance
             WHERE employee_id = $employee_id
               AND punch_in_at IS NOT NULL
               AND punch_out_at IS NULL
               AND punch_in_at > DATE_SUB(NOW(), INTERVAL $hrs HOUR)"
            . auragold_em_branch_sql($branch_id)
            . ' ORDER BY punch_in_at DESC LIMIT 1'
        );
        return is_array($row) ? auragold_em_sync_attendance_time_columns($row) : null;
    }
}

if (!function_exists('auragold_em_has_completed_punch_today')) {
    function auragold_em_has_completed_punch_today($conn, int $employee_id, int $branch_id, string $date = ''): bool
    {
        if ($employee_id <= 0 || !$conn instanceof mysqli) {
            return false;
        }
        if ($date === '') {
            $date = date('Y-m-d');
        }
        $dateEsc = auragold_em_esc($conn, $date);
        $row = getRecord(
            "SELECT id FROM tbl_employee_attendance
             WHERE employee_id = $employee_id
               AND punch_in_at IS NOT NULL
               AND punch_out_at IS NOT NULL
               AND (
                   attendance_date = '$dateEsc'
                   OR DATE(punch_in_at) = '$dateEsc'
                   OR DATE(punch_out_at) = '$dateEsc'
               )"
            . auragold_em_branch_sql($branch_id)
            . ' LIMIT 1'
        );

        return is_array($row) && (int) ($row['id'] ?? 0) > 0;
    }
}

if (!function_exists('auragold_em_punch_in')) {
    function auragold_em_punch_in($conn, int $branch_id, int $employee_id, string $attendance_date = ''): array
    {
        auragold_em_ensure_attendance_punch_schema($conn);
        if ($employee_id <= 0) {
            return ['ok' => false, 'message' => 'Invalid employee.'];
        }
        $access = auragold_em_assert_employee_access($conn, $branch_id, $employee_id);
        if (empty($access['ok'])) {
            return ['ok' => false, 'message' => $access['message']];
        }
        $employee_id = (int) $access['employee_id'];
        if ($attendance_date === '') {
            $attendance_date = date('Y-m-d');
        }
        if ($attendance_date !== date('Y-m-d')) {
            return ['ok' => false, 'message' => 'Punch in is only allowed for today (' . date('d M Y') . ').'];
        }
        auragold_em_close_stale_open_punches($conn, $branch_id);
        $open = auragold_em_get_open_punch($conn, $employee_id, $branch_id);
        if ($open) {
            return ['ok' => false, 'message' => 'Already punched in at ' . auragold_em_format_datetime($open['punch_in_at'] ?? '') . '. Please punch out first.'];
        }
        if (auragold_em_has_completed_punch_today($conn, $employee_id, $branch_id, $attendance_date)) {
            return ['ok' => false, 'message' => 'Attendance already completed for today. You can punch in again tomorrow.'];
        }
        $emp = auragold_em_get_employee_by_id($conn, $employee_id, $branch_id);
        if (!$emp) {
            return ['ok' => false, 'message' => 'Employee not found.'];
        }
        $dateEsc = auragold_em_esc($conn, $attendance_date);
        $sql = "INSERT INTO tbl_employee_attendance
                (branch_id, employee_id, attendance_date, status, check_in, punch_in_at, notes)
                VALUES (" . (int) $branch_id . ", $employee_id, '$dateEsc', 'Present', CURTIME(), NOW(), '')";
        if (!@mysqli_query($conn, $sql)) {
            return ['ok' => false, 'message' => 'Could not record punch in.'];
        }
        return [
            'ok' => true,
            'message' => 'Punch in recorded at ' . date('d M Y H:i:s') . '.',
            'id' => (int) mysqli_insert_id($conn),
            'punch_in_at' => date('Y-m-d H:i:s'),
        ];
    }
}

if (!function_exists('auragold_em_punch_out')) {
    function auragold_em_punch_out($conn, int $branch_id, int $employee_id): array
    {
        auragold_em_ensure_attendance_punch_schema($conn);
        if ($employee_id <= 0) {
            return ['ok' => false, 'message' => 'Invalid employee.'];
        }
        $access = auragold_em_assert_employee_access($conn, $branch_id, $employee_id);
        if (empty($access['ok'])) {
            return ['ok' => false, 'message' => $access['message']];
        }
        $employee_id = (int) $access['employee_id'];
        auragold_em_close_stale_open_punches($conn, $branch_id);
        $open = auragold_em_get_open_punch($conn, $employee_id, $branch_id);
        if (!$open) {
            return ['ok' => false, 'message' => 'No active punch in found. Punch in first or wait — open punches older than ' . auragold_em_attendance_stale_hours() . ' hours are marked absent.'];
        }
        $id = (int) ($open['id'] ?? 0);
        $sql = "UPDATE tbl_employee_attendance
                SET punch_out_at = NOW(),
                    check_out = CURTIME(),
                    status = 'Present'
                WHERE id = $id AND branch_id = " . (int) $branch_id . ' LIMIT 1';
        if (!@mysqli_query($conn, $sql)) {
            return ['ok' => false, 'message' => 'Could not record punch out.'];
        }
        return [
            'ok' => true,
            'message' => 'Punch out recorded at ' . date('d M Y H:i:s') . '.',
            'punch_out_at' => date('Y-m-d H:i:s'),
        ];
    }
}

if (!function_exists('auragold_em_attendance_duration')) {
    function auragold_em_attendance_duration(?string $in, ?string $out): string
    {
        if (!$in || !$out) {
            return '—';
        }
        $a = strtotime($in);
        $b = strtotime($out);
        if (!$a || !$b || $b < $a) {
            return '—';
        }
        $mins = (int) round(($b - $a) / 60);
        $h = intdiv($mins, 60);
        $m = $mins % 60;
        return $h . 'h ' . str_pad((string) $m, 2, '0', STR_PAD_LEFT) . 'm';
    }
}

if (!function_exists('auragold_em_get_attendance_board')) {
    /**
     * All active employees with day-wise punch status (supports 24h / night shifts).
     *
     * @return array<int, array<string, mixed>>
     */
    function auragold_em_get_attendance_board($conn, int $branch_id, string $view_date = '', int $employee_id = 0): array
    {
        auragold_em_ensure_attendance_punch_schema($conn);
        auragold_em_close_stale_open_punches($conn, $branch_id);
        if ($view_date === '') {
            $view_date = date('Y-m-d');
        }
        $today = date('Y-m-d');
        $isToday = ($view_date === $today);
        $viewEsc = auragold_em_esc($conn, $view_date);
        $bs = auragold_em_branch_sql($branch_id, 'a');

        $employees = auragold_em_get_employees($conn, $branch_id, 'Active');
        if ($employee_id > 0) {
            $employees = array_values(array_filter($employees, static function ($emp) use ($employee_id) {
                return (int) ($emp['id'] ?? 0) === $employee_id;
            }));
        }
        $rows = getList(
            "SELECT a.*, s.name AS shift_name
             FROM tbl_employee_attendance a
             LEFT JOIN tbl_employees e ON e.id = a.employee_id
             LEFT JOIN tbl_employee_shifts s ON s.id = e.shift_id
             WHERE (
                a.attendance_date = '$viewEsc'
                OR DATE(a.punch_in_at) = '$viewEsc'
                OR DATE(a.punch_out_at) = '$viewEsc'
             ) $bs
             ORDER BY a.punch_in_at DESC, a.id DESC"
        );
        if (!is_array($rows)) {
            $rows = [];
        }

        $byEmp = [];
        foreach ($rows as $r) {
            $r = auragold_em_sync_attendance_time_columns($r);
            $eid = (int) ($r['employee_id'] ?? 0);
            if ($eid > 0 && !isset($byEmp[$eid])) {
                $byEmp[$eid] = $r;
            }
        }

        $board = [];
        foreach ($employees as $emp) {
            $eid = (int) ($emp['id'] ?? 0);
            $open = auragold_em_get_open_punch($conn, $eid, $branch_id);
            $day = $byEmp[$eid] ?? null;

            if ($open && $isToday) {
                $openId = (int) ($open['id'] ?? 0);
                $dayId = $day ? (int) ($day['id'] ?? 0) : 0;
                if (!$day || $dayId === $openId || ($openId > 0 && $dayId !== $openId)) {
                    $day = $open;
                }
            }

            $status = $day['status'] ?? '—';
            if ($open && $isToday) {
                $status = 'Present (In)';
            } elseif (!$day) {
                $status = '—';
            }

            $pin = $day['punch_in_at'] ?? ($day && !empty($day['check_in']) ? ($view_date . ' ' . $day['check_in']) : '');
            $pout = $day['punch_out_at'] ?? ($day && !empty($day['check_out']) && empty($day['punch_in_at']) ? ($view_date . ' ' . $day['check_out']) : '');

            if ($open && empty($day['punch_out_at'])) {
                $pin = $open['punch_in_at'] ?? $pin;
                $pout = '';
            }

            $completedToday = $isToday && auragold_em_has_completed_punch_today($conn, $eid, $branch_id, $today);
            $canPunchIn = $isToday && !$open && !$completedToday;
            $canPunchOut = $isToday && (bool) $open;

            $board[] = [
                'employee_id' => $eid,
                'employee_code' => $emp['employee_code'] ?? '',
                'name' => auragold_em_employee_name($emp),
                'department_name' => $emp['department_name'] ?? '—',
                'shift_name' => trim((string) ($emp['shift_name'] ?? '')) !== '' ? trim((string) $emp['shift_name']) : '—',
                'attendance_id' => $day ? (int) ($day['id'] ?? 0) : 0,
                'status' => $status,
                'punch_in_at' => $pin,
                'punch_out_at' => $pout,
                'punch_in_display' => auragold_em_format_datetime($pin),
                'punch_out_display' => auragold_em_format_datetime($pout),
                'duration' => auragold_em_attendance_duration($pin ?: null, $pout ?: null),
                'open_punch' => (bool) $open,
                'can_punch_in' => $canPunchIn,
                'can_punch_out' => $canPunchOut,
                'is_today' => $isToday,
            ];
        }

        return $board;
    }
}

if (!function_exists('auragold_em_seed_defaults')) {
    function auragold_em_seed_defaults($conn, int $branch_id): void
    {
        if (!$conn instanceof mysqli || $branch_id <= 0) {
            return;
        }
        auragold_em_ensure_tables($conn);
        $bid = (int) $branch_id;

        $dept = getRecord("SELECT COUNT(*) AS c FROM tbl_employee_departments WHERE branch_id = $bid");
        if ((int) ($dept['c'] ?? 0) === 0) {
            $defaults = ['Sales', 'Accounts', 'Production', 'Administration'];
            foreach ($defaults as $name) {
                $n = auragold_em_esc($conn, $name);
                @mysqli_query($conn, "INSERT INTO tbl_employee_departments (branch_id, name, status) VALUES ($bid, '$n', 1)");
            }
        }

        $des = getRecord("SELECT COUNT(*) AS c FROM tbl_employee_designations WHERE branch_id = $bid");
        if ((int) ($des['c'] ?? 0) === 0) {
            $defaults = ['Manager', 'Executive', 'Accountant', 'Sales Person', 'Job Worker'];
            foreach ($defaults as $name) {
                $n = auragold_em_esc($conn, $name);
                @mysqli_query($conn, "INSERT INTO tbl_employee_designations (branch_id, name, status) VALUES ($bid, '$n', 1)");
            }
        }

        $sh = getRecord("SELECT COUNT(*) AS c FROM tbl_employee_shifts WHERE branch_id = $bid");
        if ((int) ($sh['c'] ?? 0) === 0) {
            @mysqli_query($conn, "INSERT INTO tbl_employee_shifts (branch_id, name, start_time, end_time, status) VALUES ($bid, 'General Shift', '09:00:00', '18:00:00', 1)");
            @mysqli_query($conn, "INSERT INTO tbl_employee_shifts (branch_id, name, start_time, end_time, status) VALUES ($bid, 'Morning Shift', '08:00:00', '14:00:00', 1)");
        }

        $lt = getRecord("SELECT COUNT(*) AS c FROM tbl_employee_leave_types WHERE branch_id = $bid");
        if ((int) ($lt['c'] ?? 0) === 0) {
            $defaults = [
                ['Casual Leave', 12],
                ['Sick Leave', 10],
                ['Earned Leave', 15],
            ];
            foreach ($defaults as $row) {
                $n = auragold_em_esc($conn, $row[0]);
                $d = (float) $row[1];
                @mysqli_query($conn, "INSERT INTO tbl_employee_leave_types (branch_id, name, days_per_year, status) VALUES ($bid, '$n', $d, 1)");
            }
        }
    }
}

if (!function_exists('auragold_em_next_employee_code')) {
    function auragold_em_next_employee_code($conn, int $branch_id): string
    {
        auragold_em_ensure_tables($conn);
        $bid = max(0, $branch_id);
        $row = getRecord("SELECT employee_code FROM tbl_employees WHERE branch_id = $bid ORDER BY id DESC LIMIT 1");
        $num = 1;
        if ($row && !empty($row['employee_code']) && preg_match('/(\d+)$/', (string) $row['employee_code'], $m)) {
            $num = (int) $m[1] + 1;
        }
        return 'EMP' . str_pad((string) $num, 4, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('auragold_em_employee_name')) {
    function auragold_em_employee_name(array $row): string
    {
        $name = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
        return $name !== '' ? $name : trim((string) ($row['employee_code'] ?? 'Employee'));
    }
}

if (!function_exists('auragold_em_format_date')) {
    function auragold_em_format_date($value): string
    {
        $value = trim((string) $value);
        if ($value === '' || $value === '0000-00-00') {
            return '—';
        }
        $ts = strtotime($value);
        return $ts ? date('d M Y', $ts) : $value;
    }
}

if (!function_exists('auragold_em_format_money')) {
    function auragold_em_format_money($value): string
    {
        return number_format((float) $value, 2, '.', ',');
    }
}

if (!function_exists('auragold_em_branch_sql')) {
    function auragold_em_branch_sql(int $branch_id, string $alias = ''): string
    {
        if ($branch_id <= 0) {
            return '';
        }
        $col = ($alias !== '') ? $alias . '.branch_id' : 'branch_id';
        return ' AND ' . $col . ' = ' . (int) $branch_id;
    }
}

if (!function_exists('auragold_em_get_employees')) {
    function auragold_em_get_employees($conn, int $branch_id, string $status = ''): array
    {
        auragold_em_ensure_tables($conn);
        $sql = "SELECT e.*, d.name AS department_name, g.name AS designation_name, s.name AS shift_name
                FROM tbl_employees e
                LEFT JOIN tbl_employee_departments d ON d.id = e.department_id
                LEFT JOIN tbl_employee_designations g ON g.id = e.designation_id
                LEFT JOIN tbl_employee_shifts s ON s.id = e.shift_id
                WHERE e.record_status = 1" . auragold_em_branch_sql($branch_id, 'e');
        if ($status !== '') {
            $sql .= " AND e.status = '" . auragold_em_esc($conn, $status) . "'";
        }
        $sql .= ' ORDER BY e.first_name ASC, e.last_name ASC, e.id ASC';
        $rows = getList($sql);
        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('auragold_em_get_employee_by_id')) {
    function auragold_em_get_employee_by_id($conn, int $id, int $branch_id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $sql = "SELECT e.*, d.name AS department_name, g.name AS designation_name, s.name AS shift_name
                FROM tbl_employees e
                LEFT JOIN tbl_employee_departments d ON d.id = e.department_id
                LEFT JOIN tbl_employee_designations g ON g.id = e.designation_id
                LEFT JOIN tbl_employee_shifts s ON s.id = e.shift_id
                WHERE e.id = $id AND e.record_status = 1" . auragold_em_branch_sql($branch_id, 'e') . ' LIMIT 1';
        $row = getRecord($sql);
        return is_array($row) ? $row : null;
    }
}

if (!function_exists('auragold_em_get_master_list')) {
    function auragold_em_get_master_list($conn, string $table, int $branch_id): array
    {
        auragold_em_ensure_tables($conn);
        $allowed = [
            'tbl_employee_departments',
            'tbl_employee_designations',
            'tbl_employee_shifts',
            'tbl_employee_leave_types',
        ];
        if (!in_array($table, $allowed, true)) {
            return [];
        }
        $rows = getList("SELECT * FROM $table WHERE status = 1" . auragold_em_branch_sql($branch_id) . ' ORDER BY name ASC');
        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('auragold_em_dashboard_stats')) {
    function auragold_em_dashboard_stats($conn, int $branch_id, int $employee_id = 0): array
    {
        auragold_em_ensure_tables($conn);
        $bs = auragold_em_branch_sql($branch_id);
        $emp = $employee_id > 0 ? (' AND employee_id = ' . (int) $employee_id) : '';
        $today = date('Y-m-d');
        $monthStart = date('Y-m-01');

        if ($employee_id > 0) {
            $total = ['c' => 1];
        } else {
            $total = getRecord("SELECT COUNT(*) AS c FROM tbl_employees WHERE record_status = 1 AND status = 'Active' $bs");
        }
        $hrs = auragold_em_attendance_stale_hours();
        $present = getRecord("SELECT COUNT(DISTINCT employee_id) AS c FROM tbl_employee_attendance WHERE (
                (attendance_date = '$today' AND status = 'Present' AND punch_out_at IS NOT NULL)
                OR (punch_in_at IS NOT NULL AND punch_out_at IS NULL AND punch_in_at > DATE_SUB(NOW(), INTERVAL $hrs HOUR))
            ) $bs $emp");
        $onLeave = getRecord("SELECT COUNT(*) AS c FROM tbl_employee_leave WHERE record_status = 1 AND status = 'Approved' AND from_date <= '$today' AND to_date >= '$today' $bs $emp");
        $pendingLeave = getRecord("SELECT COUNT(*) AS c FROM tbl_employee_leave WHERE record_status = 1 AND status = 'Pending' $bs $emp");
        $openTasks = getRecord("SELECT COUNT(*) AS c FROM tbl_employee_tasks WHERE record_status = 1 AND status IN ('Open','In Progress') $bs $emp");
        $payrollMonth = getRecord("SELECT COALESCE(SUM(net_salary),0) AS s FROM tbl_employee_payroll WHERE record_status = 1 AND payroll_month = '" . date('Y-m') . "' $bs $emp");

        return [
            'total_employees' => (int) ($total['c'] ?? 0),
            'present_today' => (int) ($present['c'] ?? 0),
            'on_leave_today' => (int) ($onLeave['c'] ?? 0),
            'pending_leave' => (int) ($pendingLeave['c'] ?? 0),
            'open_tasks' => (int) ($openTasks['c'] ?? 0),
            'payroll_month_total' => (float) ($payrollMonth['s'] ?? 0),
            'month_attendance' => (int) (getRecord("SELECT COUNT(*) AS c FROM tbl_employee_attendance WHERE attendance_date >= '$monthStart' $bs $emp")['c'] ?? 0),
        ];
    }
}

if (!function_exists('auragold_em_save_employee')) {
    function auragold_em_save_employee($conn, int $branch_id, array $data): array
    {
        auragold_em_ensure_tables($conn);
        $id = (int) ($data['id'] ?? 0);
        $code = trim((string) ($data['employee_code'] ?? ''));
        if ($code === '') {
            $code = auragold_em_next_employee_code($conn, $branch_id);
        }
        $fields = [
            'employee_code' => $code,
            'first_name' => trim((string) ($data['first_name'] ?? '')),
            'last_name' => trim((string) ($data['last_name'] ?? '')),
            'email' => trim((string) ($data['email'] ?? '')),
            'phone' => trim((string) ($data['phone'] ?? '')),
            'department_id' => (int) ($data['department_id'] ?? 0),
            'designation_id' => (int) ($data['designation_id'] ?? 0),
            'shift_id' => (int) ($data['shift_id'] ?? 0),
            'joining_date' => trim((string) ($data['joining_date'] ?? '')),
            'basic_salary' => (float) ($data['basic_salary'] ?? 0),
            'address' => trim((string) ($data['address'] ?? '')),
            'notes' => trim((string) ($data['notes'] ?? '')),
            'status' => trim((string) ($data['status'] ?? 'Active')) ?: 'Active',
        ];
        if ($fields['first_name'] === '') {
            return ['ok' => false, 'message' => 'First name is required.'];
        }
        if ($fields['joining_date'] === '') {
            $fields['joining_date'] = null;
        }

        $setParts = [];
        foreach ($fields as $k => $v) {
            if ($k === 'joining_date' && $v === null) {
                $setParts[] = "`joining_date` = NULL";
            } else {
                $setParts[] = '`' . $k . "` = '" . auragold_em_esc($conn, $v) . "'";
            }
        }

        if ($id > 0) {
            $sql = 'UPDATE tbl_employees SET ' . implode(', ', $setParts) . " WHERE id = $id AND branch_id = " . (int) $branch_id . ' LIMIT 1';
        } else {
            $sql = 'INSERT INTO tbl_employees SET branch_id = ' . (int) $branch_id . ', ' . implode(', ', $setParts) . ', record_status = 1';
        }
        if (!@mysqli_query($conn, $sql)) {
            return ['ok' => false, 'message' => 'Could not save employee.'];
        }
        return ['ok' => true, 'message' => 'Employee saved.', 'id' => $id > 0 ? $id : (int) mysqli_insert_id($conn)];
    }
}

if (!function_exists('auragold_em_delete_employee')) {
    function auragold_em_delete_employee($conn, int $id, int $branch_id): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'message' => 'Invalid employee.'];
        }
        $ok = @mysqli_query($conn, 'UPDATE tbl_employees SET record_status = 0 WHERE id = ' . $id . ' AND branch_id = ' . (int) $branch_id . ' LIMIT 1');
        return ['ok' => (bool) $ok, 'message' => $ok ? 'Employee removed.' : 'Could not remove employee.'];
    }
}

if (!function_exists('auragold_em_save_master_row')) {
    function auragold_em_save_master_row($conn, string $table, int $branch_id, array $data): array
    {
        $map = [
            'department' => 'tbl_employee_departments',
            'designation' => 'tbl_employee_designations',
            'shift' => 'tbl_employee_shifts',
            'leave_type' => 'tbl_employee_leave_types',
        ];
        $key = trim((string) ($data['master_type'] ?? ''));
        if (!isset($map[$key])) {
            return ['ok' => false, 'message' => 'Invalid master type.'];
        }
        $tbl = $map[$key];
        auragold_em_ensure_tables($conn);
        $id = (int) ($data['id'] ?? 0);
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            return ['ok' => false, 'message' => 'Name is required.'];
        }
        $nameEsc = auragold_em_esc($conn, $name);
        if ($key === 'shift') {
            $st = trim((string) ($data['start_time'] ?? ''));
            $et = trim((string) ($data['end_time'] ?? ''));
            $stSql = $st !== '' ? "'" . auragold_em_esc($conn, $st) . "'" : 'NULL';
            $etSql = $et !== '' ? "'" . auragold_em_esc($conn, $et) . "'" : 'NULL';
            if ($id > 0) {
                $sql = "UPDATE $tbl SET name = '$nameEsc', start_time = $stSql, end_time = $etSql WHERE id = $id AND branch_id = " . (int) $branch_id;
            } else {
                $sql = "INSERT INTO $tbl (branch_id, name, start_time, end_time, status) VALUES (" . (int) $branch_id . ", '$nameEsc', $stSql, $etSql, 1)";
            }
        } elseif ($key === 'leave_type') {
            $days = (float) ($data['days_per_year'] ?? 0);
            if ($id > 0) {
                $sql = "UPDATE $tbl SET name = '$nameEsc', days_per_year = $days WHERE id = $id AND branch_id = " . (int) $branch_id;
            } else {
                $sql = "INSERT INTO $tbl (branch_id, name, days_per_year, status) VALUES (" . (int) $branch_id . ", '$nameEsc', $days, 1)";
            }
        } else {
            if ($id > 0) {
                $sql = "UPDATE $tbl SET name = '$nameEsc' WHERE id = $id AND branch_id = " . (int) $branch_id;
            } else {
                $sql = "INSERT INTO $tbl (branch_id, name, status) VALUES (" . (int) $branch_id . ", '$nameEsc', 1)";
            }
        }
        if (!@mysqli_query($conn, $sql)) {
            return ['ok' => false, 'message' => 'Could not save.'];
        }
        return ['ok' => true, 'message' => 'Saved.', 'id' => $id > 0 ? $id : (int) mysqli_insert_id($conn)];
    }
}

if (!function_exists('auragold_em_delete_master_row')) {
    function auragold_em_delete_master_row($conn, string $tableKey, int $id, int $branch_id): array
    {
        $map = [
            'department' => 'tbl_employee_departments',
            'designation' => 'tbl_employee_designations',
            'shift' => 'tbl_employee_shifts',
            'leave_type' => 'tbl_employee_leave_types',
        ];
        if (!isset($map[$tableKey]) || $id <= 0) {
            return ['ok' => false, 'message' => 'Invalid request.'];
        }
        $tbl = $map[$tableKey];
        $ok = @mysqli_query($conn, "UPDATE $tbl SET status = 0 WHERE id = $id AND branch_id = " . (int) $branch_id . ' LIMIT 1');
        return ['ok' => (bool) $ok, 'message' => $ok ? 'Removed.' : 'Could not remove.'];
    }
}

if (!function_exists('auragold_em_save_document')) {
    function auragold_em_save_document($conn, int $branch_id, array $data): array
    {
        auragold_em_ensure_tables($conn);
        $id = (int) ($data['id'] ?? 0);
        $employee_id = (int) ($data['employee_id'] ?? 0);
        if ($employee_id <= 0) {
            return ['ok' => false, 'message' => 'Select an employee.'];
        }
        $access = auragold_em_assert_employee_access($conn, $branch_id, $employee_id);
        if (empty($access['ok'])) {
            return ['ok' => false, 'message' => $access['message']];
        }
        $employee_id = (int) $access['employee_id'];
        $doc_type = trim((string) ($data['doc_type'] ?? ''));
        $doc_title = trim((string) ($data['doc_title'] ?? ''));
        if ($doc_title === '') {
            return ['ok' => false, 'message' => 'Document title is required.'];
        }
        $expiry = trim((string) ($data['expiry_date'] ?? ''));
        $expirySql = ($expiry !== '') ? "'" . auragold_em_esc($conn, $expiry) . "'" : 'NULL';
        $fields = [
            'employee_id' => $employee_id,
            'doc_type' => auragold_em_esc($conn, $doc_type),
            'doc_title' => auragold_em_esc($conn, $doc_title),
            'file_name' => auragold_em_esc($conn, (string) ($data['file_name'] ?? '')),
            'file_path' => auragold_em_esc($conn, (string) ($data['file_path'] ?? '')),
            'notes' => auragold_em_esc($conn, (string) ($data['notes'] ?? '')),
        ];
        if ($id > 0) {
            $sql = "UPDATE tbl_employee_documents SET employee_id = {$fields['employee_id']}, doc_type = '{$fields['doc_type']}', doc_title = '{$fields['doc_title']}', file_name = '{$fields['file_name']}', file_path = '{$fields['file_path']}', expiry_date = $expirySql, notes = '{$fields['notes']}' WHERE id = $id AND branch_id = " . (int) $branch_id;
        } else {
            $sql = "INSERT INTO tbl_employee_documents (branch_id, employee_id, doc_type, doc_title, file_name, file_path, expiry_date, notes, record_status) VALUES (" . (int) $branch_id . ", {$fields['employee_id']}, '{$fields['doc_type']}', '{$fields['doc_title']}', '{$fields['file_name']}', '{$fields['file_path']}', $expirySql, '{$fields['notes']}', 1)";
        }
        if (!@mysqli_query($conn, $sql)) {
            return ['ok' => false, 'message' => 'Could not save document.'];
        }
        return ['ok' => true, 'message' => 'Document saved.', 'id' => $id > 0 ? $id : (int) mysqli_insert_id($conn)];
    }
}

if (!function_exists('auragold_em_get_documents')) {
    function auragold_em_get_documents($conn, int $branch_id, int $employee_id = 0): array
    {
        auragold_em_ensure_tables($conn);
        $sql = "SELECT d.*, e.first_name, e.last_name, e.employee_code
                FROM tbl_employee_documents d
                LEFT JOIN tbl_employees e ON e.id = d.employee_id
                WHERE d.record_status = 1" . auragold_em_branch_sql($branch_id, 'd');
        if ($employee_id > 0) {
            $sql .= ' AND d.employee_id = ' . (int) $employee_id;
        }
        $sql .= ' ORDER BY d.id DESC';
        $rows = getList($sql);
        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('auragold_em_delete_row')) {
    function auragold_em_delete_row($conn, string $table, int $id, int $branch_id): array
    {
        $allowed = [
            'documents' => 'tbl_employee_documents',
            'leave' => 'tbl_employee_leave',
            'payroll' => 'tbl_employee_payroll',
            'tasks' => 'tbl_employee_tasks',
            'performance' => 'tbl_employee_performance',
            'advances' => 'tbl_employee_advances',
            'expenses' => 'tbl_employee_expenses',
        ];
        if (!isset($allowed[$table]) || $id <= 0) {
            return ['ok' => false, 'message' => 'Invalid request.'];
        }
        $tbl = $allowed[$table];
        $ok = @mysqli_query($conn, "UPDATE $tbl SET record_status = 0 WHERE id = $id AND branch_id = " . (int) $branch_id . ' LIMIT 1');
        return ['ok' => (bool) $ok, 'message' => $ok ? 'Removed.' : 'Could not remove.'];
    }
}

if (!function_exists('auragold_em_save_attendance')) {
    function auragold_em_save_attendance($conn, int $branch_id, array $data): array
    {
        auragold_em_ensure_tables($conn);
        $employee_id = (int) ($data['employee_id'] ?? 0);
        $date = trim((string) ($data['attendance_date'] ?? date('Y-m-d')));
        if ($employee_id <= 0 || $date === '') {
            return ['ok' => false, 'message' => 'Employee and date are required.'];
        }
        $access = auragold_em_assert_employee_access($conn, $branch_id, $employee_id);
        if (empty($access['ok'])) {
            return ['ok' => false, 'message' => $access['message']];
        }
        $employee_id = (int) $access['employee_id'];
        $status = trim((string) ($data['status'] ?? 'Present')) ?: 'Present';
        $check_in = trim((string) ($data['check_in'] ?? ''));
        $check_out = trim((string) ($data['check_out'] ?? ''));
        $notes = auragold_em_esc($conn, (string) ($data['notes'] ?? ''));
        $ci = $check_in !== '' ? "'" . auragold_em_esc($conn, $check_in) . "'" : 'NULL';
        $co = $check_out !== '' ? "'" . auragold_em_esc($conn, $check_out) . "'" : 'NULL';
        $existing = getRecord("SELECT id FROM tbl_employee_attendance WHERE employee_id = $employee_id AND attendance_date = '" . auragold_em_esc($conn, $date) . "' LIMIT 1");
        if ($existing) {
            $id = (int) $existing['id'];
            $sql = "UPDATE tbl_employee_attendance SET status = '" . auragold_em_esc($conn, $status) . "', check_in = $ci, check_out = $co, notes = '$notes' WHERE id = $id AND branch_id = " . (int) $branch_id;
        } else {
            $sql = "INSERT INTO tbl_employee_attendance (branch_id, employee_id, attendance_date, status, check_in, check_out, notes) VALUES (" . (int) $branch_id . ", $employee_id, '" . auragold_em_esc($conn, $date) . "', '" . auragold_em_esc($conn, $status) . "', $ci, $co, '$notes')";
        }
        if (!@mysqli_query($conn, $sql)) {
            return ['ok' => false, 'message' => 'Could not save attendance.'];
        }
        return ['ok' => true, 'message' => 'Attendance saved.'];
    }
}

if (!function_exists('auragold_em_get_attendance')) {
    function auragold_em_get_attendance($conn, int $branch_id, string $date = '', int $employee_id = 0): array
    {
        auragold_em_ensure_tables($conn);
        if ($date === '') {
            $date = date('Y-m-d');
        }
        $sql = "SELECT a.*, e.first_name, e.last_name, e.employee_code
                FROM tbl_employee_attendance a
                LEFT JOIN tbl_employees e ON e.id = a.employee_id
                WHERE (
                    a.attendance_date = '" . auragold_em_esc($conn, $date) . "'
                    OR DATE(a.punch_in_at) = '" . auragold_em_esc($conn, $date) . "'
                    OR DATE(a.punch_out_at) = '" . auragold_em_esc($conn, $date) . "'
                )" . auragold_em_branch_sql($branch_id, 'a');
        if ($employee_id > 0) {
            $sql .= ' AND a.employee_id = ' . (int) $employee_id;
        }
        $sql .= ' ORDER BY e.first_name ASC';
        $rows = getList($sql);
        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('auragold_em_save_leave')) {
    function auragold_em_save_leave($conn, int $branch_id, array $data): array
    {
        auragold_em_ensure_tables($conn);
        $id = (int) ($data['id'] ?? 0);
        $employee_id = (int) ($data['employee_id'] ?? 0);
        $leave_type_id = (int) ($data['leave_type_id'] ?? 0);
        $from = trim((string) ($data['from_date'] ?? ''));
        $to = trim((string) ($data['to_date'] ?? ''));
        if ($employee_id <= 0 || $from === '' || $to === '') {
            return ['ok' => false, 'message' => 'Employee and leave dates are required.'];
        }
        $access = auragold_em_assert_employee_access($conn, $branch_id, $employee_id);
        if (empty($access['ok'])) {
            return ['ok' => false, 'message' => $access['message']];
        }
        $employee_id = (int) $access['employee_id'];
        $days = (float) ($data['days'] ?? 0);
        if ($days <= 0) {
            $days = max(1, (int) ((strtotime($to) - strtotime($from)) / 86400) + 1);
        }
        $reason = auragold_em_esc($conn, (string) ($data['reason'] ?? ''));
        $status = trim((string) ($data['status'] ?? 'Pending')) ?: 'Pending';
        if ($id > 0) {
            $sql = "UPDATE tbl_employee_leave SET employee_id = $employee_id, leave_type_id = $leave_type_id, from_date = '" . auragold_em_esc($conn, $from) . "', to_date = '" . auragold_em_esc($conn, $to) . "', days = $days, reason = '$reason', status = '" . auragold_em_esc($conn, $status) . "' WHERE id = $id AND branch_id = " . (int) $branch_id;
        } else {
            $sql = "INSERT INTO tbl_employee_leave (branch_id, employee_id, leave_type_id, from_date, to_date, days, reason, status, record_status) VALUES (" . (int) $branch_id . ", $employee_id, $leave_type_id, '" . auragold_em_esc($conn, $from) . "', '" . auragold_em_esc($conn, $to) . "', $days, '$reason', '$status', 1)";
        }
        if (!@mysqli_query($conn, $sql)) {
            return ['ok' => false, 'message' => 'Could not save leave request.'];
        }
        return ['ok' => true, 'message' => 'Leave request saved.', 'id' => $id > 0 ? $id : (int) mysqli_insert_id($conn)];
    }
}

if (!function_exists('auragold_em_update_leave_status')) {
    function auragold_em_update_leave_status($conn, int $id, int $branch_id, string $status, string $approved_by = ''): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'message' => 'Invalid leave request.'];
        }
        if (!auragold_em_is_admin_manager()) {
            return ['ok' => false, 'message' => 'Only admin can approve or reject leave requests.'];
        }
        $st = auragold_em_esc($conn, $status);
        $by = auragold_em_esc($conn, $approved_by);
        $sql = "UPDATE tbl_employee_leave SET status = '$st', approved_by = '$by', approved_at = NOW() WHERE id = $id AND branch_id = " . (int) $branch_id . ' LIMIT 1';
        $ok = @mysqli_query($conn, $sql);
        return ['ok' => (bool) $ok, 'message' => $ok ? 'Leave status updated.' : 'Could not update leave.'];
    }
}

if (!function_exists('auragold_em_get_leave_requests')) {
    function auragold_em_get_leave_requests($conn, int $branch_id, string $status = '', int $employee_id = 0): array
    {
        auragold_em_ensure_tables($conn);
        $sql = "SELECT l.*, e.first_name, e.last_name, e.employee_code, t.name AS leave_type_name
                FROM tbl_employee_leave l
                LEFT JOIN tbl_employees e ON e.id = l.employee_id
                LEFT JOIN tbl_employee_leave_types t ON t.id = l.leave_type_id
                WHERE l.record_status = 1" . auragold_em_branch_sql($branch_id, 'l');
        if ($status !== '') {
            $sql .= " AND l.status = '" . auragold_em_esc($conn, $status) . "'";
        }
        if ($employee_id > 0) {
            $sql .= ' AND l.employee_id = ' . (int) $employee_id;
        }
        $sql .= ' ORDER BY l.id DESC';
        $rows = getList($sql);
        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('auragold_em_save_payroll')) {
    function auragold_em_save_payroll($conn, int $branch_id, array $data): array
    {
        auragold_em_ensure_tables($conn);
        $id = (int) ($data['id'] ?? 0);
        $employee_id = (int) ($data['employee_id'] ?? 0);
        $monthPart = trim((string) ($data['payroll_month_part'] ?? ''));
        $yearPart = trim((string) ($data['payroll_year'] ?? ''));
        $month = trim((string) ($data['payroll_month'] ?? date('Y-m')));
        if ($monthPart !== '' && $yearPart !== '') {
            $month = auragold_em_payroll_month_from_parts($monthPart, $yearPart);
        }
        if ($employee_id <= 0 || $month === '') {
            return ['ok' => false, 'message' => 'Employee and payroll month are required.'];
        }
        $access = auragold_em_assert_employee_access($conn, $branch_id, $employee_id);
        if (empty($access['ok'])) {
            return ['ok' => false, 'message' => $access['message']];
        }
        // Non-admin users may view own payroll but not create/edit payroll rows.
        if (!auragold_em_is_admin_manager()) {
            return ['ok' => false, 'message' => 'Only admin can create or edit payroll records.'];
        }
        $employee_id = (int) $access['employee_id'];
        $detail = auragold_em_payroll_detail_from_request($data);
        $basic = (float) ($data['basic_salary'] ?? 0);
        $hra = (float) ($detail['hra'] ?? 0);
        $da = (float) ($detail['da'] ?? 0);
        $conveyance = (float) ($detail['conveyance'] ?? 0);
        $salaryArrears = (float) ($detail['salary_arrears'] ?? 0);
        $allow = $hra + $da + $conveyance + $salaryArrears;
        $ded = (float) ($detail['professional_tax'] ?? 0)
            + (float) ($detail['pf'] ?? 0)
            + (float) ($detail['esic'] ?? 0)
            + (float) ($detail['tds'] ?? 0)
            + (float) ($detail['advance_salary'] ?? 0)
            + (float) ($detail['other_deduction'] ?? 0);
        $net = (float) ($data['final_net_salary'] ?? 0);
        if ($net <= 0) {
            $net = $basic + $allow - $ded;
        }
        $detailJson = auragold_em_esc($conn, json_encode($detail, JSON_UNESCAPED_UNICODE));
        $payDate = trim((string) ($data['payment_date'] ?? ''));
        $payDateSql = $payDate !== '' ? "'" . auragold_em_esc($conn, $payDate) . "'" : 'NULL';
        $status = trim((string) ($data['status'] ?? 'Draft')) ?: 'Draft';
        $notes = auragold_em_esc($conn, (string) ($data['notes'] ?? ''));
        if ($id > 0) {
            $existing = getRecord(
                "SELECT id, status FROM tbl_employee_payroll
                 WHERE id = $id
                   AND branch_id = " . (int) $branch_id . "
                   AND record_status = 1
                 LIMIT 1"
            );
            if (!$existing) {
                return ['ok' => false, 'message' => 'Payroll record not found.'];
            }
            if (strcasecmp((string) ($existing['status'] ?? ''), 'Draft') !== 0) {
                return ['ok' => false, 'message' => 'Only draft payroll records can be edited.'];
            }
            $sql = "UPDATE tbl_employee_payroll SET employee_id = $employee_id, payroll_month = '" . auragold_em_esc($conn, $month) . "', basic_salary = $basic, allowances = $allow, deductions = $ded, net_salary = $net, payment_date = $payDateSql, status = '" . auragold_em_esc($conn, $status) . "', notes = '$notes', payroll_detail_json = '$detailJson' WHERE id = $id AND branch_id = " . (int) $branch_id;
        } else {
            $sql = "INSERT INTO tbl_employee_payroll (branch_id, employee_id, payroll_month, basic_salary, allowances, deductions, net_salary, payment_date, status, notes, payroll_detail_json, record_status) VALUES (" . (int) $branch_id . ", $employee_id, '" . auragold_em_esc($conn, $month) . "', $basic, $allow, $ded, $net, $payDateSql, '" . auragold_em_esc($conn, $status) . "', '$notes', '$detailJson', 1)";
        }
        if (!@mysqli_query($conn, $sql)) {
            return ['ok' => false, 'message' => 'Could not save payroll.'];
        }
        return ['ok' => true, 'message' => 'Payroll saved.', 'id' => $id > 0 ? $id : (int) mysqli_insert_id($conn)];
    }
}

if (!function_exists('auragold_em_apply_advance_to_payroll_month')) {
    /**
     * Add approved advance amount into this month's payroll deductions (create row if needed).
     */
    function auragold_em_apply_advance_to_payroll_month($conn, int $branch_id, int $employee_id, float $amount, string $month): array
    {
        if ($employee_id <= 0 || $amount <= 0 || $month === '') {
            return ['ok' => false, 'message' => 'Invalid payroll advance link.'];
        }
        auragold_em_ensure_tables($conn);
        $monthEsc = auragold_em_esc($conn, $month);
        $row = getRecord(
            "SELECT * FROM tbl_employee_payroll
             WHERE employee_id = $employee_id
               AND payroll_month = '$monthEsc'
               AND record_status = 1
               AND branch_id = " . (int) $branch_id . '
             LIMIT 1'
        );
        $emp = getRecord(
            "SELECT basic_salary FROM tbl_employees
             WHERE id = $employee_id AND record_status = 1
             LIMIT 1"
        );
        $basic = (float) ($emp['basic_salary'] ?? 0);
        if ($row) {
            $allow = (float) ($row['allowances'] ?? 0);
            $ded = (float) ($row['deductions'] ?? 0) + $amount;
            $basicUse = (float) ($row['basic_salary'] ?? $basic);
            $net = $basicUse + $allow - $ded;
            $note = trim((string) ($row['notes'] ?? ''));
            $extra = 'Advance recovery ' . number_format($amount, 2);
            if ($note === '') {
                $note = $extra;
            } elseif (stripos($note, 'Advance recovery') === false) {
                $note .= '; ' . $extra;
            }
            return auragold_em_save_payroll($conn, $branch_id, [
                'id' => (int) $row['id'],
                'employee_id' => $employee_id,
                'payroll_month' => $month,
                'basic_salary' => $basicUse,
                'allowances' => $allow,
                'deductions' => $ded,
                'net_salary' => $net,
                'payment_date' => (string) ($row['payment_date'] ?? ''),
                'status' => (string) ($row['status'] ?? 'Draft'),
                'notes' => $note,
            ]);
        }
        $net = $basic - $amount;
        return auragold_em_save_payroll($conn, $branch_id, [
            'employee_id' => $employee_id,
            'payroll_month' => $month,
            'basic_salary' => $basic,
            'allowances' => 0,
            'deductions' => $amount,
            'net_salary' => $net,
            'status' => 'Draft',
            'notes' => 'Advance recovery ' . number_format($amount, 2),
        ]);
    }
}

if (!function_exists('auragold_em_save_advance')) {
    function auragold_em_save_advance($conn, int $branch_id, array $data): array
    {
        auragold_em_ensure_tables($conn);
        $id = (int) ($data['id'] ?? 0);
        $employee_id = (int) ($data['employee_id'] ?? 0);
        $advance_date = trim((string) ($data['advance_date'] ?? date('Y-m-d')));
        $amount = (float) ($data['amount'] ?? 0);
        $notes = auragold_em_esc($conn, (string) ($data['notes'] ?? ''));
        $requested_by = auragold_em_esc($conn, (string) ($data['requested_by'] ?? ''));
        if ($employee_id <= 0) {
            return ['ok' => false, 'message' => 'Employee is required.'];
        }
        $access = auragold_em_assert_employee_access($conn, $branch_id, $employee_id);
        if (empty($access['ok'])) {
            return ['ok' => false, 'message' => $access['message']];
        }
        $employee_id = (int) $access['employee_id'];
        if ($advance_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $advance_date)) {
            return ['ok' => false, 'message' => 'Valid advance date is required.'];
        }
        if ($amount <= 0) {
            return ['ok' => false, 'message' => 'Advance amount must be greater than zero.'];
        }
        $limitCheck = auragold_em_validate_advance_amount(
            $conn,
            $branch_id,
            $employee_id,
            $amount,
            $advance_date,
            $id
        );
        if (empty($limitCheck['ok'])) {
            return ['ok' => false, 'message' => $limitCheck['message']];
        }

        // New requests always start as Pending for admin approval.
        if ($id > 0) {
            $existing = getRecord(
                "SELECT id, status, employee_id FROM tbl_employee_advances
                 WHERE id = $id AND branch_id = " . (int) $branch_id . " AND record_status = 1 LIMIT 1"
            );
            if (!$existing) {
                return ['ok' => false, 'message' => 'Advance request not found.'];
            }
            if (strcasecmp((string) ($existing['status'] ?? ''), 'Pending') !== 0) {
                return ['ok' => false, 'message' => 'Only pending advance requests can be edited.'];
            }
            if (!auragold_em_is_admin_manager()) {
                $mine = auragold_em_current_employee_id($conn, $branch_id);
                if ($mine <= 0 || $mine !== (int) ($existing['employee_id'] ?? 0)) {
                    return ['ok' => false, 'message' => 'You can only edit your own pending advance requests.'];
                }
            }
            $sql = "UPDATE tbl_employee_advances
                    SET employee_id = $employee_id,
                        advance_date = '" . auragold_em_esc($conn, $advance_date) . "',
                        amount = $amount,
                        notes = '$notes'
                    WHERE id = $id AND branch_id = " . (int) $branch_id . ' LIMIT 1';
        } else {
            $sql = "INSERT INTO tbl_employee_advances
                    (branch_id, employee_id, advance_date, amount, recovered, status, requested_by, notes, record_status)
                    VALUES (
                        " . (int) $branch_id . ",
                        $employee_id,
                        '" . auragold_em_esc($conn, $advance_date) . "',
                        $amount,
                        0,
                        'Pending',
                        '$requested_by',
                        '$notes',
                        1
                    )";
        }
        if (!@mysqli_query($conn, $sql)) {
            return ['ok' => false, 'message' => 'Could not save advance request.'];
        }
        return [
            'ok' => true,
            'message' => $id > 0 ? 'Advance request updated.' : 'Advance request submitted. Waiting for admin approval.',
            'id' => $id > 0 ? $id : (int) mysqli_insert_id($conn),
        ];
    }
}

if (!function_exists('auragold_em_seed_expense_categories')) {
    function auragold_em_seed_expense_categories($conn): void
    {
        if (!$conn instanceof mysqli) {
            return;
        }
        $cnt = getRecord('SELECT COUNT(*) AS c FROM tbl_expense_categories');
        if ((int) ($cnt['c'] ?? 0) > 0) {
            return;
        }
        $defaults = [
            ['Travel', 'Indirect Expenses', 1],
            ['Food & Refreshment', 'Indirect Expenses', 2],
            ['Staff Welfare', 'Indirect Expenses', 3],
            ['Conveyance', 'Indirect Expenses', 4],
            ['Office Expense', 'Indirect Expenses', 5],
            ['Miscellaneous', 'Indirect Expenses', 6],
        ];
        foreach ($defaults as $i => $row) {
            $name = auragold_em_esc($conn, $row[0]);
            $type = auragold_em_esc($conn, $row[1]);
            $ord = (int) $row[2];
            @mysqli_query(
                $conn,
                "INSERT INTO tbl_expense_categories (name, type, sort_order, status) VALUES ('$name', '$type', $ord, 1)"
            );
        }
    }
}

if (!function_exists('auragold_em_get_expense_categories')) {
    function auragold_em_get_expense_categories($conn, bool $activeOnly = true): array
    {
        auragold_em_ensure_tables($conn);
        $where = $activeOnly ? ' WHERE status = 1' : '';
        $rows = getList("SELECT * FROM tbl_expense_categories$where ORDER BY sort_order ASC, name ASC, id ASC");
        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('auragold_em_save_expense_category')) {
    function auragold_em_save_expense_category($conn, array $data): array
    {
        auragold_em_ensure_tables($conn);
        $id = (int) ($data['id'] ?? 0);
        $name = trim((string) ($data['name'] ?? ''));
        $type = trim((string) ($data['type'] ?? ''));
        $sort_order = (int) ($data['sort_order'] ?? 0);
        $status = isset($data['status']) ? (int) $data['status'] : 1;
        if ($name === '') {
            return ['ok' => false, 'message' => 'Category name is required.'];
        }
        $nameEsc = auragold_em_esc($conn, $name);
        $typeEsc = auragold_em_esc($conn, $type);
        $dupSql = "SELECT id FROM tbl_expense_categories WHERE name = '$nameEsc' AND id <> $id LIMIT 1";
        $dup = getRecord($dupSql);
        if ($dup) {
            return ['ok' => false, 'message' => 'A category with this name already exists.'];
        }
        if ($id > 0) {
            $sql = "UPDATE tbl_expense_categories
                    SET name = '$nameEsc',
                        type = " . ($type !== '' ? "'$typeEsc'" : 'NULL') . ",
                        sort_order = $sort_order,
                        status = $status
                    WHERE id = $id LIMIT 1";
        } else {
            if ($sort_order <= 0) {
                $max = getRecord('SELECT COALESCE(MAX(sort_order),0) AS m FROM tbl_expense_categories');
                $sort_order = (int) ($max['m'] ?? 0) + 1;
            }
            $sql = "INSERT INTO tbl_expense_categories (name, type, sort_order, status)
                    VALUES (
                        '$nameEsc',
                        " . ($type !== '' ? "'$typeEsc'" : 'NULL') . ",
                        $sort_order,
                        $status
                    )";
        }
        if (!@mysqli_query($conn, $sql)) {
            return ['ok' => false, 'message' => 'Could not save expense category.'];
        }
        return [
            'ok' => true,
            'message' => $id > 0 ? 'Expense category updated.' : 'Expense category added.',
            'id' => $id > 0 ? $id : (int) mysqli_insert_id($conn),
        ];
    }
}

if (!function_exists('auragold_em_delete_expense_category')) {
    function auragold_em_delete_expense_category($conn, int $id): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'message' => 'Invalid category.'];
        }
        auragold_em_ensure_tables($conn);
        $ok = @mysqli_query($conn, 'UPDATE tbl_expense_categories SET status = 0 WHERE id = ' . $id . ' LIMIT 1');
        return ['ok' => (bool) $ok, 'message' => $ok ? 'Category deactivated.' : 'Could not remove category.'];
    }
}

if (!function_exists('auragold_em_get_expenses')) {
    function auragold_em_get_expenses($conn, int $branch_id, int $employee_id = 0, int $category_id = 0): array
    {
        auragold_em_ensure_tables($conn);
        $sql = "SELECT x.*, e.first_name, e.last_name, e.employee_code
                FROM tbl_employee_expenses x
                LEFT JOIN tbl_employees e ON e.id = x.employee_id
                WHERE x.record_status = 1" . auragold_em_branch_sql($branch_id, 'x');
        if ($employee_id > 0) {
            $sql .= ' AND x.employee_id = ' . (int) $employee_id;
        }
        if ($category_id > 0) {
            $sql .= ' AND x.category_id = ' . (int) $category_id;
        }
        $sql .= ' ORDER BY x.expense_date DESC, x.id DESC';
        $rows = getList($sql);
        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('auragold_em_save_expense')) {
    function auragold_em_save_expense($conn, int $branch_id, array $data): array
    {
        auragold_em_ensure_tables($conn);
        $id = (int) ($data['id'] ?? 0);
        $againstEmployee = !empty($data['against_employee']) && (string) $data['against_employee'] !== '0';
        $employee_id = (int) ($data['employee_id'] ?? 0);
        $category_id = (int) ($data['category_id'] ?? 0);
        $expense_date = trim((string) ($data['expense_date'] ?? date('Y-m-d')));
        $amount = (float) ($data['amount'] ?? 0);
        $payment_mode = trim((string) ($data['payment_mode'] ?? 'Cash')) ?: 'Cash';
        $description = auragold_em_esc($conn, (string) ($data['description'] ?? ''));
        $status = trim((string) ($data['status'] ?? 'Paid')) ?: 'Paid';
        $created_by = auragold_em_esc($conn, (string) ($data['created_by'] ?? ''));

        if (!$againstEmployee) {
            $employee_id = 0;
        } else {
            if ($employee_id <= 0) {
                return ['ok' => false, 'message' => 'Select employee when expense is against an employee.'];
            }
            $access = auragold_em_assert_employee_access($conn, $branch_id, $employee_id);
            if (empty($access['ok'])) {
                return ['ok' => false, 'message' => $access['message']];
            }
            $employee_id = (int) $access['employee_id'];
        }

        if ($category_id <= 0) {
            return ['ok' => false, 'message' => 'Expense category is required.'];
        }
        $cat = getRecord('SELECT id, name FROM tbl_expense_categories WHERE id = ' . $category_id . ' AND status = 1 LIMIT 1');
        if (!$cat) {
            return ['ok' => false, 'message' => 'Selected category is invalid or inactive.'];
        }
        $category_name = auragold_em_esc($conn, (string) ($cat['name'] ?? ''));

        if ($expense_date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expense_date)) {
            return ['ok' => false, 'message' => 'Valid expense date is required.'];
        }
        if ($amount <= 0) {
            return ['ok' => false, 'message' => 'Expense amount must be greater than zero.'];
        }

        $payment_mode_esc = auragold_em_esc($conn, $payment_mode);
        $status_esc = auragold_em_esc($conn, $status);
        $date_esc = auragold_em_esc($conn, $expense_date);

        if ($id > 0) {
            $existing = getRecord(
                'SELECT id FROM tbl_employee_expenses
                 WHERE id = ' . $id . ' AND branch_id = ' . (int) $branch_id . ' AND record_status = 1 LIMIT 1'
            );
            if (!$existing) {
                return ['ok' => false, 'message' => 'Expense not found.'];
            }
            $sql = "UPDATE tbl_employee_expenses
                    SET employee_id = $employee_id,
                        category_id = $category_id,
                        category_name = '$category_name',
                        expense_date = '$date_esc',
                        amount = $amount,
                        payment_mode = '$payment_mode_esc',
                        description = '$description',
                        status = '$status_esc'
                    WHERE id = $id AND branch_id = " . (int) $branch_id . ' LIMIT 1';
        } else {
            $sql = "INSERT INTO tbl_employee_expenses
                    (branch_id, employee_id, category_id, category_name, expense_date, amount, payment_mode, description, status, created_by, record_status)
                    VALUES (
                        " . (int) $branch_id . ",
                        $employee_id,
                        $category_id,
                        '$category_name',
                        '$date_esc',
                        $amount,
                        '$payment_mode_esc',
                        '$description',
                        '$status_esc',
                        '$created_by',
                        1
                    )";
        }
        if (!@mysqli_query($conn, $sql)) {
            return ['ok' => false, 'message' => 'Could not save expense.'];
        }
        return [
            'ok' => true,
            'message' => $id > 0 ? 'Expense updated.' : 'Expense saved.',
            'id' => $id > 0 ? $id : (int) mysqli_insert_id($conn),
        ];
    }
}

if (!function_exists('auragold_em_update_advance_status')) {
    function auragold_em_update_advance_status(
        $conn,
        int $id,
        int $branch_id,
        string $status,
        string $approved_by = '',
        ?float $approved_amount = null
    ): array
    {
        if ($id <= 0) {
            return ['ok' => false, 'message' => 'Invalid advance request.'];
        }
        if (!auragold_em_is_admin_manager()) {
            return ['ok' => false, 'message' => 'Only admin can approve or reject advance requests.'];
        }
        auragold_em_ensure_tables($conn);
        $row = getRecord(
            "SELECT * FROM tbl_employee_advances
             WHERE id = $id AND branch_id = " . (int) $branch_id . " AND record_status = 1 LIMIT 1"
        );
        if (!$row) {
            return ['ok' => false, 'message' => 'Advance request not found.'];
        }
        if (strcasecmp((string) ($row['status'] ?? ''), 'Pending') !== 0) {
            return ['ok' => false, 'message' => 'Only pending requests can be approved or rejected.'];
        }

        $statusNorm = trim($status);
        if (!in_array($statusNorm, ['Approved', 'Rejected'], true)) {
            return ['ok' => false, 'message' => 'Invalid status.'];
        }

        $by = auragold_em_esc($conn, $approved_by);
        $payrollMonth = '';
        $requestedAmount = (float) ($row['amount'] ?? 0);
        $approvedAmount = null;
        if ($statusNorm === 'Approved') {
            $approvedAmount = $approved_amount ?? $requestedAmount;
            if ($approvedAmount <= 0) {
                return ['ok' => false, 'message' => 'Approved amount must be greater than zero.'];
            }
            if ($approvedAmount > $requestedAmount) {
                return ['ok' => false, 'message' => 'Approved amount cannot exceed the requested amount.'];
            }

            // Add into this month's salary/payroll record.
            $payrollMonth = date('Y-m');
            mysqli_begin_transaction($conn);
            $apply = auragold_em_apply_advance_to_payroll_month(
                $conn,
                $branch_id,
                (int) ($row['employee_id'] ?? 0),
                $approvedAmount,
                $payrollMonth
            );
            if (empty($apply['ok'])) {
                mysqli_rollback($conn);
                return ['ok' => false, 'message' => $apply['message'] ?? 'Could not add advance to this month payroll.'];
            }
        }

        $monthSql = $payrollMonth !== '' ? "'" . auragold_em_esc($conn, $payrollMonth) . "'" : "''";
        $approvedAmountSql = $approvedAmount !== null
            ? number_format($approvedAmount, 2, '.', '')
            : 'NULL';
        $sql = "UPDATE tbl_employee_advances
                SET status = '" . auragold_em_esc($conn, $statusNorm) . "',
                    approved_amount = $approvedAmountSql,
                    approved_by = '$by',
                    approved_at = NOW(),
                    payroll_month = $monthSql
                WHERE id = $id AND branch_id = " . (int) $branch_id . ' LIMIT 1';
        if (!@mysqli_query($conn, $sql)) {
            if ($statusNorm === 'Approved') {
                mysqli_rollback($conn);
            }
            return ['ok' => false, 'message' => 'Could not update advance status.'];
        }
        if ($statusNorm === 'Approved') {
            mysqli_commit($conn);
        }
        $msg = $statusNorm === 'Approved'
            ? 'Advance of ' . number_format((float) $approvedAmount, 2)
                . ' approved and added to ' . $payrollMonth . ' payroll deductions.'
            : 'Advance request rejected.';
        return [
            'ok' => true,
            'message' => $msg,
            'payroll_month' => $payrollMonth,
            'approved_amount' => $approvedAmount,
        ];
    }
}

if (!function_exists('auragold_em_get_advances')) {
    function auragold_em_get_advances($conn, int $branch_id, string $status = '', int $employee_id = 0): array
    {
        auragold_em_ensure_tables($conn);
        $sql = "SELECT a.*, e.first_name, e.last_name, e.employee_code,
                       COALESCE(e.basic_salary, 0) AS monthly_salary
                FROM tbl_employee_advances a
                LEFT JOIN tbl_employees e ON e.id = a.employee_id
                WHERE a.record_status = 1" . auragold_em_branch_sql($branch_id, 'a');
        if ($status !== '') {
            $sql .= " AND a.status = '" . auragold_em_esc($conn, $status) . "'";
        }
        if ($employee_id > 0) {
            $sql .= ' AND a.employee_id = ' . (int) $employee_id;
        }
        $sql .= ' ORDER BY a.id DESC';
        $rows = getList($sql);
        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('auragold_em_approved_advances_for_month')) {
    /**
     * Approved advances linked to a payroll month (set on approve).
     * @return array{count:int,amount:float,by_employee:array<int,array{count:int,amount:float}>}
     */
    function auragold_em_approved_advances_for_month($conn, int $branch_id, string $month, int $employee_id = 0): array
    {
        $out = ['count' => 0, 'amount' => 0.0, 'by_employee' => []];
        $month = trim($month);
        if ($month === '' || !preg_match('/^\d{4}-\d{2}$/', $month)) {
            return $out;
        }
        auragold_em_ensure_tables($conn);
        $sql = "SELECT employee_id, COALESCE(approved_amount, amount) AS amount
                FROM tbl_employee_advances
                WHERE record_status = 1
                  AND status = 'Approved'
                  AND payroll_month = '" . auragold_em_esc($conn, $month) . "'"
            . auragold_em_branch_sql($branch_id);
        if ($employee_id > 0) {
            $sql .= ' AND employee_id = ' . (int) $employee_id;
        }
        $rows = getList($sql);
        if (!is_array($rows)) {
            return $out;
        }
        foreach ($rows as $row) {
            $eid = (int) ($row['employee_id'] ?? 0);
            $amt = (float) ($row['amount'] ?? 0);
            $out['count']++;
            $out['amount'] += $amt;
            if ($eid <= 0) {
                continue;
            }
            if (!isset($out['by_employee'][$eid])) {
                $out['by_employee'][$eid] = ['count' => 0, 'amount' => 0.0];
            }
            $out['by_employee'][$eid]['count']++;
            $out['by_employee'][$eid]['amount'] += $amt;
        }
        return $out;
    }
}

if (!function_exists('auragold_em_get_payroll')) {
    function auragold_em_get_payroll($conn, int $branch_id, string $month = '', int $employee_id = 0): array
    {
        auragold_em_ensure_tables($conn);
        $sql = "SELECT p.*, e.first_name, e.last_name, e.employee_code
                FROM tbl_employee_payroll p
                LEFT JOIN tbl_employees e ON e.id = p.employee_id
                WHERE p.record_status = 1" . auragold_em_branch_sql($branch_id, 'p');
        if ($month !== '') {
            $sql .= " AND p.payroll_month = '" . auragold_em_esc($conn, $month) . "'";
        }
        if ($employee_id > 0) {
            $sql .= ' AND p.employee_id = ' . (int) $employee_id;
        }
        $sql .= ' ORDER BY p.payroll_month DESC, e.first_name ASC';
        $rows = getList($sql);
        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('auragold_em_save_task')) {
    function auragold_em_save_task($conn, int $branch_id, array $data): array
    {
        auragold_em_ensure_tables($conn);
        $id = (int) ($data['id'] ?? 0);
        $employee_id = (int) ($data['employee_id'] ?? 0);
        $title = trim((string) ($data['title'] ?? ''));
        if ($employee_id <= 0 || $title === '') {
            return ['ok' => false, 'message' => 'Employee and task title are required.'];
        }
        $access = auragold_em_assert_employee_access($conn, $branch_id, $employee_id);
        if (empty($access['ok'])) {
            return ['ok' => false, 'message' => $access['message']];
        }
        $employee_id = (int) $access['employee_id'];
        $description = auragold_em_esc($conn, (string) ($data['description'] ?? ''));
        $priority = trim((string) ($data['priority'] ?? 'Medium')) ?: 'Medium';
        $status = trim((string) ($data['status'] ?? 'Open')) ?: 'Open';
        $due = trim((string) ($data['due_date'] ?? ''));
        $dueSql = $due !== '' ? "'" . auragold_em_esc($conn, $due) . "'" : 'NULL';
        $completedSql = ($status === 'Completed') ? 'NOW()' : 'NULL';
        if ($id > 0) {
            $sql = "UPDATE tbl_employee_tasks SET employee_id = $employee_id, title = '" . auragold_em_esc($conn, $title) . "', description = '$description', priority = '" . auragold_em_esc($conn, $priority) . "', status = '" . auragold_em_esc($conn, $status) . "', due_date = $dueSql, completed_at = $completedSql WHERE id = $id AND branch_id = " . (int) $branch_id;
        } else {
            $sql = "INSERT INTO tbl_employee_tasks (branch_id, employee_id, title, description, priority, status, due_date, record_status) VALUES (" . (int) $branch_id . ", $employee_id, '" . auragold_em_esc($conn, $title) . "', '$description', '" . auragold_em_esc($conn, $priority) . "', '" . auragold_em_esc($conn, $status) . "', $dueSql, 1)";
        }
        if (!@mysqli_query($conn, $sql)) {
            return ['ok' => false, 'message' => 'Could not save task.'];
        }
        return ['ok' => true, 'message' => 'Task saved.', 'id' => $id > 0 ? $id : (int) mysqli_insert_id($conn)];
    }
}

if (!function_exists('auragold_em_get_tasks')) {
    function auragold_em_get_tasks($conn, int $branch_id, string $status = '', int $employee_id = 0): array
    {
        auragold_em_ensure_tables($conn);
        $sql = "SELECT t.*, e.first_name, e.last_name, e.employee_code
                FROM tbl_employee_tasks t
                LEFT JOIN tbl_employees e ON e.id = t.employee_id
                WHERE t.record_status = 1" . auragold_em_branch_sql($branch_id, 't');
        if ($status !== '') {
            $sql .= " AND t.status = '" . auragold_em_esc($conn, $status) . "'";
        }
        if ($employee_id > 0) {
            $sql .= ' AND t.employee_id = ' . (int) $employee_id;
        }
        $sql .= ' ORDER BY t.due_date ASC, t.id DESC';
        $rows = getList($sql);
        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('auragold_em_save_performance')) {
    function auragold_em_save_performance($conn, int $branch_id, array $data): array
    {
        auragold_em_ensure_tables($conn);
        $id = (int) ($data['id'] ?? 0);
        $employee_id = (int) ($data['employee_id'] ?? 0);
        if ($employee_id <= 0) {
            return ['ok' => false, 'message' => 'Select an employee.'];
        }
        $access = auragold_em_assert_employee_access($conn, $branch_id, $employee_id);
        if (empty($access['ok'])) {
            return ['ok' => false, 'message' => $access['message']];
        }
        $employee_id = (int) $access['employee_id'];
        $period = auragold_em_esc($conn, (string) ($data['review_period'] ?? ''));
        $reviewDate = trim((string) ($data['review_date'] ?? ''));
        $reviewDateSql = $reviewDate !== '' ? "'" . auragold_em_esc($conn, $reviewDate) . "'" : 'NULL';
        $rating = (float) ($data['rating'] ?? 0);
        $kpi = (float) ($data['kpi_score'] ?? 0);
        $strengths = auragold_em_esc($conn, (string) ($data['strengths'] ?? ''));
        $improvements = auragold_em_esc($conn, (string) ($data['improvements'] ?? ''));
        $reviewer = auragold_em_esc($conn, (string) ($data['reviewer_name'] ?? ''));
        $status = trim((string) ($data['status'] ?? 'Draft')) ?: 'Draft';
        if ($id > 0) {
            $sql = "UPDATE tbl_employee_performance SET employee_id = $employee_id, review_period = '$period', review_date = $reviewDateSql, rating = $rating, kpi_score = $kpi, strengths = '$strengths', improvements = '$improvements', reviewer_name = '$reviewer', status = '" . auragold_em_esc($conn, $status) . "' WHERE id = $id AND branch_id = " . (int) $branch_id;
        } else {
            $sql = "INSERT INTO tbl_employee_performance (branch_id, employee_id, review_period, review_date, rating, kpi_score, strengths, improvements, reviewer_name, status, record_status) VALUES (" . (int) $branch_id . ", $employee_id, '$period', $reviewDateSql, $rating, $kpi, '$strengths', '$improvements', '$reviewer', '" . auragold_em_esc($conn, $status) . "', 1)";
        }
        if (!@mysqli_query($conn, $sql)) {
            return ['ok' => false, 'message' => 'Could not save performance review.'];
        }
        return ['ok' => true, 'message' => 'Performance review saved.', 'id' => $id > 0 ? $id : (int) mysqli_insert_id($conn)];
    }
}

if (!function_exists('auragold_em_get_performance')) {
    function auragold_em_get_performance($conn, int $branch_id, int $employee_id = 0): array
    {
        auragold_em_ensure_tables($conn);
        $sql = "SELECT p.*, e.first_name, e.last_name, e.employee_code
            FROM tbl_employee_performance p
            LEFT JOIN tbl_employees e ON e.id = p.employee_id
            WHERE p.record_status = 1" . auragold_em_branch_sql($branch_id, 'p');
        if ($employee_id > 0) {
            $sql .= ' AND p.employee_id = ' . (int) $employee_id;
        }
        $sql .= ' ORDER BY p.review_date DESC, p.id DESC';
        $rows = getList($sql);
        return is_array($rows) ? $rows : [];
    }
}

if (!function_exists('auragold_em_get_reports')) {
    function auragold_em_get_reports($conn, int $branch_id, string $from = '', string $to = '', int $employee_id = 0): array
    {
        auragold_em_ensure_tables($conn);
        if ($from === '') {
            $from = date('Y-m-01');
        }
        if ($to === '') {
            $to = date('Y-m-d');
        }
        $fromEsc = auragold_em_esc($conn, $from);
        $toEsc = auragold_em_esc($conn, $to);
        $monthFrom = substr($fromEsc, 0, 7);
        $monthTo = substr($toEsc, 0, 7);
        $empFilter = $employee_id > 0 ? (' AND employee_id = ' . (int) $employee_id) : '';
        $empFilterA = $employee_id > 0 ? (' AND a.employee_id = ' . (int) $employee_id) : '';
        $empFilterL = $employee_id > 0 ? (' AND l.employee_id = ' . (int) $employee_id) : '';
        $empFilterP = $employee_id > 0 ? (' AND p.employee_id = ' . (int) $employee_id) : '';
        $empFilterT = $employee_id > 0 ? (' AND t.employee_id = ' . (int) $employee_id) : '';
        $empFilterR = $employee_id > 0 ? (' AND r.employee_id = ' . (int) $employee_id) : '';

        $bs = auragold_em_branch_sql($branch_id);
        $bsA = auragold_em_branch_sql($branch_id, 'a');
        $bsL = auragold_em_branch_sql($branch_id, 'l');
        $bsP = auragold_em_branch_sql($branch_id, 'p');
        $bsT = auragold_em_branch_sql($branch_id, 't');
        $bsR = auragold_em_branch_sql($branch_id, 'r');

        $attendanceSummary = getList(
            "SELECT status, COUNT(*) AS c
             FROM tbl_employee_attendance
             WHERE attendance_date BETWEEN '$fromEsc' AND '$toEsc' $bs $empFilter
             GROUP BY status ORDER BY status"
        );

        $attendanceByEmployee = getList(
            "SELECT a.employee_id, e.first_name, e.last_name, e.employee_code,
                    SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) AS present_days,
                    SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) AS absent_days,
                    SUM(CASE WHEN a.status = 'Half Day' THEN 1 ELSE 0 END) AS half_days,
                    SUM(CASE WHEN a.status NOT IN ('Present','Absent','Half Day') THEN 1 ELSE 0 END) AS other_days,
                    COUNT(*) AS total_days
             FROM tbl_employee_attendance a
             LEFT JOIN tbl_employees e ON e.id = a.employee_id
             WHERE a.attendance_date BETWEEN '$fromEsc' AND '$toEsc' $bsA $empFilterA
             GROUP BY a.employee_id, e.first_name, e.last_name, e.employee_code
             ORDER BY e.first_name ASC, e.last_name ASC"
        );

        $attendanceRows = getList(
            "SELECT a.*, e.first_name, e.last_name, e.employee_code
             FROM tbl_employee_attendance a
             LEFT JOIN tbl_employees e ON e.id = a.employee_id
             WHERE a.attendance_date BETWEEN '$fromEsc' AND '$toEsc' $bsA $empFilterA
             ORDER BY a.attendance_date DESC, e.first_name ASC
             LIMIT 2000"
        );

        $leaveSummary = getList(
            "SELECT status, COUNT(*) AS c
             FROM tbl_employee_leave
             WHERE record_status = 1 AND from_date <= '$toEsc' AND to_date >= '$fromEsc' $bs $empFilter
             GROUP BY status ORDER BY status"
        );

        $leaveRows = getList(
            "SELECT l.*, e.first_name, e.last_name, e.employee_code, lt.name AS leave_type_name
             FROM tbl_employee_leave l
             LEFT JOIN tbl_employees e ON e.id = l.employee_id
             LEFT JOIN tbl_employee_leave_types lt ON lt.id = l.leave_type_id
             WHERE l.record_status = 1 AND l.from_date <= '$toEsc' AND l.to_date >= '$fromEsc' $bsL $empFilterL
             ORDER BY l.from_date DESC, e.first_name ASC
             LIMIT 2000"
        );

        $payrollSummary = getRecord(
            "SELECT COUNT(*) AS c, COALESCE(SUM(net_salary),0) AS total,
                    COALESCE(SUM(basic_salary),0) AS basic_total,
                    COALESCE(SUM(allowances),0) AS allow_total,
                    COALESCE(SUM(deductions),0) AS ded_total
             FROM tbl_employee_payroll
             WHERE record_status = 1
               AND payroll_month >= '$monthFrom'
               AND payroll_month <= '$monthTo'
               $bs $empFilter"
        );

        $payrollRows = getList(
            "SELECT p.*, e.first_name, e.last_name, e.employee_code
             FROM tbl_employee_payroll p
             LEFT JOIN tbl_employees e ON e.id = p.employee_id
             WHERE p.record_status = 1
               AND p.payroll_month >= '$monthFrom'
               AND p.payroll_month <= '$monthTo'
               $bsP $empFilterP
             ORDER BY p.payroll_month DESC, e.first_name ASC
             LIMIT 2000"
        );

        $taskSummary = getList(
            "SELECT status, COUNT(*) AS c
             FROM tbl_employee_tasks
             WHERE record_status = 1 $bs $empFilter
             GROUP BY status ORDER BY status"
        );

        $taskRows = getList(
            "SELECT t.*, e.first_name, e.last_name, e.employee_code
             FROM tbl_employee_tasks t
             LEFT JOIN tbl_employees e ON e.id = t.employee_id
             WHERE t.record_status = 1 $bsT $empFilterT
             ORDER BY t.id DESC
             LIMIT 2000"
        );

        $perfAvg = getRecord(
            "SELECT COALESCE(AVG(rating),0) AS avg_rating, COUNT(*) AS c
             FROM tbl_employee_performance
             WHERE record_status = 1
               AND (review_date IS NULL OR review_date BETWEEN '$fromEsc' AND '$toEsc')
               $bs $empFilter"
        );

        $performanceRows = getList(
            "SELECT r.*, e.first_name, e.last_name, e.employee_code
             FROM tbl_employee_performance r
             LEFT JOIN tbl_employees e ON e.id = r.employee_id
             WHERE r.record_status = 1
               AND (r.review_date IS NULL OR r.review_date BETWEEN '$fromEsc' AND '$toEsc')
               $bsR $empFilterR
             ORDER BY r.review_date DESC, e.first_name ASC
             LIMIT 2000"
        );

        return [
            'from' => $from,
            'to' => $to,
            'employee_id' => $employee_id,
            'attendance_summary' => is_array($attendanceSummary) ? $attendanceSummary : [],
            'attendance_by_employee' => is_array($attendanceByEmployee) ? $attendanceByEmployee : [],
            'attendance_rows' => is_array($attendanceRows) ? $attendanceRows : [],
            'leave_summary' => is_array($leaveSummary) ? $leaveSummary : [],
            'leave_rows' => is_array($leaveRows) ? $leaveRows : [],
            'payroll_count' => (int) ($payrollSummary['c'] ?? 0),
            'payroll_total' => (float) ($payrollSummary['total'] ?? 0),
            'payroll_basic_total' => (float) ($payrollSummary['basic_total'] ?? 0),
            'payroll_allow_total' => (float) ($payrollSummary['allow_total'] ?? 0),
            'payroll_ded_total' => (float) ($payrollSummary['ded_total'] ?? 0),
            'payroll_rows' => is_array($payrollRows) ? $payrollRows : [],
            'task_summary' => is_array($taskSummary) ? $taskSummary : [],
            'task_rows' => is_array($taskRows) ? $taskRows : [],
            'avg_rating' => (float) ($perfAvg['avg_rating'] ?? 0),
            'performance_reviews' => (int) ($perfAvg['c'] ?? 0),
            'performance_rows' => is_array($performanceRows) ? $performanceRows : [],
        ];
    }
}

if (!function_exists('auragold_em_attendance_report_day_code')) {
    /**
     * Map attendance row + calendar rules to register code: P, A, or blank.
     */
    function auragold_em_attendance_report_day_code(string $dateYmd, ?array $attRow, string $todayYmd, string $joinYmd = ''): string
    {
        if ($dateYmd === '') {
            return '';
        }
        if ($joinYmd !== '' && $dateYmd < $joinYmd) {
            return '';
        }
        if ($dateYmd > $todayYmd) {
            return '';
        }
        if (!$attRow) {
            return 'A';
        }
        $st = strtolower(trim((string) ($attRow['status'] ?? '')));
        if (strpos($st, 'present') !== false || $st === 'half day') {
            return 'P';
        }
        if ($st === 'absent') {
            return 'A';
        }

        return 'A';
    }
}

if (!function_exists('auragold_em_attendance_report_dates')) {
    /**
     * @return array<int, array{ymd: string, label: string, dow: int}>
     */
    function auragold_em_attendance_report_dates(string $from, string $to, int $maxDays = 62): array
    {
        $fromTs = strtotime($from);
        $toTs = strtotime($to);
        if ($fromTs === false || $toTs === false || $fromTs > $toTs) {
            return [];
        }
        $dates = [];
        $cur = $fromTs;
        while ($cur <= $toTs) {
            if (count($dates) >= $maxDays) {
                break;
            }
            $ymd = date('Y-m-d', $cur);
            $dates[] = [
                'ymd' => $ymd,
                'label' => date('d/m', $cur),
                'dow' => (int) date('w', $cur),
            ];
            $cur = strtotime('+1 day', $cur);
        }

        return $dates;
    }
}

if (!function_exists('auragold_em_get_attendance_datewise_report')) {
    /**
     * Date-wise attendance register (employee rows × day columns).
     *
     * @param array<string, mixed> $filters from, to, employee_id, department_id, status
     * @return array<string, mixed>
     */
    function auragold_em_get_attendance_datewise_report($conn, int $branch_id, array $filters = []): array
    {
        auragold_em_ensure_tables($conn);
        auragold_em_ensure_attendance_punch_schema($conn);

        $from = trim((string) ($filters['from'] ?? ''));
        $to = trim((string) ($filters['to'] ?? ''));
        if ($from === '') {
            $from = date('Y-m-01');
        }
        if ($to === '') {
            $to = date('Y-m-d');
        }
        if (strtotime($from) > strtotime($to)) {
            $tmp = $from;
            $from = $to;
            $to = $tmp;
        }

        $employeeId = (int) ($filters['employee_id'] ?? 0);
        $departmentId = (int) ($filters['department_id'] ?? 0);
        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '' && !in_array($status, ['Active', 'Inactive'], true)) {
            $status = '';
        }

        $dates = auragold_em_attendance_report_dates($from, $to);
        $rangeDays = max(0, (int) ((strtotime($to) - strtotime($from)) / 86400) + 1);
        $truncated = $rangeDays > count($dates) && count($dates) >= 62;

        $employees = auragold_em_get_employees($conn, $branch_id, $status);
        if ($employeeId > 0) {
            $employees = array_values(array_filter($employees, static function ($emp) use ($employeeId) {
                return (int) ($emp['id'] ?? 0) === $employeeId;
            }));
        }
        if ($departmentId > 0) {
            $employees = array_values(array_filter($employees, static function ($emp) use ($departmentId) {
                return (int) ($emp['department_id'] ?? 0) === $departmentId;
            }));
        }

        $attMap = [];
        if (!empty($dates) && !empty($employees)) {
            $fromEsc = auragold_em_esc($conn, $from);
            $toEsc = auragold_em_esc($conn, $to);
            $bs = auragold_em_branch_sql($branch_id, 'a');
            $empIds = array_map(static function ($emp) {
                return (int) ($emp['id'] ?? 0);
            }, $employees);
            $empIds = array_values(array_filter($empIds));
            if (!empty($empIds)) {
                $idList = implode(',', $empIds);
                $rows = getList(
                    "SELECT a.*
                     FROM tbl_employee_attendance a
                     WHERE a.attendance_date BETWEEN '$fromEsc' AND '$toEsc'
                       AND a.employee_id IN ($idList)
                       $bs
                     ORDER BY a.attendance_date ASC, a.id ASC"
                );
                if (is_array($rows)) {
                    foreach ($rows as $row) {
                        $row = auragold_em_sync_attendance_time_columns($row);
                        $eid = (int) ($row['employee_id'] ?? 0);
                        $d = (string) ($row['attendance_date'] ?? '');
                        if ($eid > 0 && $d !== '') {
                            $attMap[$eid][$d] = $row;
                        }
                    }
                }
            }
        }

        $today = date('Y-m-d');
        $reportRows = [];
        foreach ($employees as $emp) {
            $eid = (int) ($emp['id'] ?? 0);
            if ($eid <= 0) {
                continue;
            }
            $joinYmd = !empty($emp['joining_date']) ? date('Y-m-d', strtotime((string) $emp['joining_date'])) : '';
            $cells = [];
            foreach ($dates as $day) {
                $ymd = (string) ($day['ymd'] ?? '');
                $attRow = $attMap[$eid][$ymd] ?? null;
                $cells[$ymd] = auragold_em_attendance_report_day_code($ymd, $attRow, $today, $joinYmd);
            }
            $reportRows[] = [
                'employee_id' => $eid,
                'employee_code' => (string) ($emp['employee_code'] ?? ''),
                'employee_name' => auragold_em_employee_name($emp),
                'department_name' => trim((string) ($emp['department_name'] ?? '')) !== '' ? trim((string) $emp['department_name']) : '—',
                'manager_name' => trim((string) ($emp['designation_name'] ?? '')) !== '' ? trim((string) $emp['designation_name']) : '—',
                'monthly_salary' => (float) ($emp['basic_salary'] ?? 0),
                'cells' => $cells,
            ];
        }

        return [
            'from' => $from,
            'to' => $to,
            'dates' => $dates,
            'rows' => $reportRows,
            'truncated' => $truncated,
            'range_days' => $rangeDays,
        ];
    }
}

if (!function_exists('auragold_em_export_attendance_datewise_csv')) {
    /**
     * @param array<string, mixed> $query GET params
     */
    function auragold_em_export_attendance_datewise_csv($conn, array $em, array $query): void
    {
        $branch_id = (int) ($em['branch_id'] ?? 0);
        $isAdmin = !empty($em['is_em_admin']);
        $myEmployeeId = (int) ($em['my_employee_id'] ?? 0);
        $employeeId = isset($query['employee_id']) ? (int) $query['employee_id'] : 0;
        if (!$isAdmin) {
            $employeeId = $myEmployeeId;
        }

        $report = auragold_em_get_attendance_datewise_report($conn, $branch_id, [
            'from' => preg_replace('/[^0-9-]/', '', (string) ($query['from'] ?? '')),
            'to' => preg_replace('/[^0-9-]/', '', (string) ($query['to'] ?? '')),
            'employee_id' => $employeeId,
            'department_id' => (int) ($query['department_id'] ?? 0),
            'status' => trim((string) ($query['status'] ?? '')),
        ]);

        $filename = 'attendance-report-' . ($report['from'] ?? 'from') . '-to-' . ($report['to'] ?? 'to') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo "\xEF\xBB\xBF";

        $out = fopen('php://output', 'w');
        if (!$out) {
            exit;
        }

        $header = ['Employee', 'Code', 'Department', 'Designation', 'Monthly Salary'];
        foreach ($report['dates'] as $day) {
            $header[] = (string) ($day['label'] ?? '');
        }
        fputcsv($out, $header);

        foreach ($report['rows'] as $row) {
            $line = [
                $row['employee_name'] ?? '',
                $row['employee_code'] ?? '',
                $row['department_name'] ?? '',
                $row['manager_name'] ?? '',
                number_format((float) ($row['monthly_salary'] ?? 0), 2, '.', ''),
            ];
            foreach ($report['dates'] as $day) {
                $ymd = (string) ($day['ymd'] ?? '');
                $line[] = (string) (($row['cells'][$ymd] ?? ''));
            }
            fputcsv($out, $line);
        }
        fclose($out);
    }
}

if (!function_exists('auragold_em_bootstrap_page')) {
    function auragold_em_bootstrap_page($conn): array
    {
        if (function_exists('auragold_ensure_branch_id_on_settings_tables')) {
            auragold_ensure_branch_id_on_settings_tables($conn);
        }
        auragold_em_ensure_tables($conn);
        $branch_id = auragold_em_resolve_branch_id();
        auragold_em_seed_defaults($conn, $branch_id);
        auragold_em_sync_users_to_employees($conn, $branch_id);
        $scope = auragold_em_access_scope($conn, $branch_id);
        return [
            'branch_id' => $branch_id,
            'is_em_admin' => !empty($scope['is_admin']),
            'my_employee_id' => (int) ($scope['employee_id'] ?? 0),
            'employees' => auragold_em_scoped_employees($conn, $branch_id, 'Active'),
            'departments' => auragold_em_get_master_list($conn, 'tbl_employee_departments', $branch_id),
            'designations' => auragold_em_get_master_list($conn, 'tbl_employee_designations', $branch_id),
            'shifts' => auragold_em_get_master_list($conn, 'tbl_employee_shifts', $branch_id),
            'leave_types' => auragold_em_get_master_list($conn, 'tbl_employee_leave_types', $branch_id),
            'next_employee_code' => auragold_em_next_employee_code($conn, $branch_id),
        ];
    }
}
