<?php

/**
 * Auto-create / upgrade department module tables (department.php).
 * Tables: tbl_departments, tbl_department_users, tbl_department_user_map
 */

if (!function_exists('auragold_department_run_sql_file')) {
    function auragold_department_run_sql_file(mysqli $conn, string $path): void
    {
        if (!is_file($path)) {
            return;
        }
        $sql = (string) file_get_contents($path);
        if ($sql === '') {
            return;
        }
        @mysqli_multi_query($conn, $sql);
        while (@mysqli_more_results($conn)) {
            @mysqli_next_result($conn);
        }
    }
}

if (!function_exists('auragold_department_table_exists')) {
    function auragold_department_table_exists(mysqli $conn, string $table): bool
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($table === '') {
            return false;
        }
        $r = @mysqli_query($conn, "SHOW TABLES LIKE '$table'");
        $ok = ($r && mysqli_num_rows($r) > 0);
        if ($r) {
            mysqli_free_result($r);
        }
        return $ok;
    }
}

if (!function_exists('auragold_department_has_index')) {
    function auragold_department_has_index(mysqli $conn, string $table, string $indexName): bool
    {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $indexName = preg_replace('/[^a-zA-Z0-9_]/', '', $indexName);
        if ($table === '' || $indexName === '') {
            return false;
        }
        $r = @mysqli_query($conn, "SHOW INDEX FROM `$table` WHERE Key_name = '$indexName'");
        $ok = ($r && mysqli_num_rows($r) > 0);
        if ($r) {
            mysqli_free_result($r);
        }
        return $ok;
    }
}

if (!function_exists('auragold_ensure_department_table_columns')) {
    function auragold_ensure_department_table_columns(mysqli $conn): void
    {
        if (!function_exists('auragold_tbl_has_column')) {
            require_once __DIR__ . '/auragold_branch_data_scope.php';
        }
        if (!auragold_department_table_exists($conn, 'tbl_departments')) {
            return;
        }

        $add = static function (string $column, string $definition, ?string $after = null) use ($conn): void {
            if (auragold_tbl_has_column($conn, 'tbl_departments', $column)) {
                return;
            }
            $sql = 'ALTER TABLE tbl_departments ADD COLUMN `' . $column . '` ' . $definition;
            if ($after !== null && $after !== '') {
                $sql .= ' AFTER `' . preg_replace('/[^a-zA-Z0-9_]/', '', $after) . '`';
            }
            @mysqli_query($conn, $sql);
        };

        $add('department_type', "varchar(40) NOT NULL DEFAULT 'Wt. Wise'", 'short_code');
        $add('process_type', "varchar(40) NOT NULL DEFAULT 'Manufacturing Inhouse'", 'department_type');
        $add('auto_loss', 'tinyint(1) NOT NULL DEFAULT 1', 'process_type');
        $add('auto_profit', 'tinyint(1) NOT NULL DEFAULT 1', 'auto_loss');
        $add('calculate_stock', 'tinyint(1) NOT NULL DEFAULT 0', 'auto_profit');
        $add('progress_percent', 'decimal(8,2) DEFAULT NULL', 'calculate_stock');
        $add('exclude_jobcard_summary', 'tinyint(1) NOT NULL DEFAULT 0', 'progress_percent');
        $add('status', 'tinyint(1) NOT NULL DEFAULT 1', 'exclude_jobcard_summary');
        $add('created_at', 'datetime NOT NULL DEFAULT current_timestamp()', 'status');
        $add('updated_at', 'datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()', 'created_at');

        if (!auragold_department_has_index($conn, 'tbl_departments', 'idx_department_process_type')) {
            @mysqli_query($conn, 'ALTER TABLE tbl_departments ADD KEY idx_department_process_type (process_type)');
        }
    }
}

if (!function_exists('auragold_migrate_department_process_type')) {
    function auragold_migrate_department_process_type(mysqli $conn): void
    {
        if (!auragold_department_table_exists($conn, 'tbl_departments')) {
            return;
        }
        if (!function_exists('auragold_tbl_has_column')) {
            require_once __DIR__ . '/auragold_branch_data_scope.php';
        }
        if (!auragold_tbl_has_column($conn, 'tbl_departments', 'process_type')) {
            return;
        }

        @mysqli_query(
            $conn,
            "UPDATE tbl_departments SET process_type = 'Manufacturing Inhouse', updated_at = NOW()"
            . " WHERE process_type IN ('Manufacturing', 'Melting', 'Testing')"
        );

        @mysqli_query(
            $conn,
            "ALTER TABLE tbl_departments MODIFY COLUMN process_type varchar(40) NOT NULL"
            . " DEFAULT 'Manufacturing Inhouse'"
            . " COMMENT 'Manufacturing Inhouse | Manufacturing Outsource'"
        );
    }
}

if (!function_exists('auragold_ensure_department_schema')) {
    function auragold_ensure_department_schema(mysqli $conn): void
    {
        static $done = false;
        if ($done || !$conn instanceof mysqli) {
            return;
        }

        $sqlDir = dirname(__DIR__) . '/sql';
        auragold_department_run_sql_file($conn, $sqlDir . '/create_tbl_departments.sql');
        auragold_department_run_sql_file($conn, $sqlDir . '/create_tbl_department_users.sql');
        auragold_ensure_department_table_columns($conn);
        auragold_migrate_department_process_type($conn);

        $done = true;
    }
}

if (!function_exists('auragold_department_sql_in_ids')) {
    /** @param int[] $ids */
    function auragold_department_sql_in_ids(array $ids): string
    {
        $clean = [];
        foreach ($ids as $id) {
            $n = (int) $id;
            if ($n > 0) {
                $clean[$n] = $n;
            }
        }
        if ($clean === []) {
            return '0';
        }
        return implode(',', array_values($clean));
    }
}

if (!function_exists('auragold_get_outsource_departments')) {
    /**
     * Departments with Process = Manufacturing Outsource (department.php).
     *
     * @return list<array<string,mixed>>
     */
    function auragold_get_outsource_departments(mysqli $conn): array
    {
        auragold_ensure_department_schema($conn);
        if (!auragold_department_table_exists($conn, 'tbl_departments')) {
            return [];
        }
        if (!function_exists('auragold_tbl_has_column')) {
            require_once __DIR__ . '/auragold_branch_data_scope.php';
        }
        if (!auragold_tbl_has_column($conn, 'tbl_departments', 'process_type')) {
            return [];
        }
        if (!function_exists('getList')) {
            return [];
        }
        $list = getList(
            "SELECT id, dept_name, process_type, auto_loss FROM tbl_departments"
            . " WHERE status = 1 AND process_type = 'Manufacturing Outsource'"
            . ' ORDER BY dept_name ASC, id ASC'
        );
        return is_array($list) ? $list : [];
    }
}

if (!function_exists('auragold_get_inhouse_departments')) {
    /**
     * Departments with Process = Manufacturing Inhouse (legacy Manufacturing/Melting/Testing included).
     *
     * @return list<array<string,mixed>>
     */
    function auragold_get_inhouse_departments(mysqli $conn): array
    {
        auragold_ensure_department_schema($conn);
        if (!auragold_department_table_exists($conn, 'tbl_departments')) {
            return [];
        }
        if (!function_exists('auragold_tbl_has_column')) {
            require_once __DIR__ . '/auragold_branch_data_scope.php';
        }
        if (!auragold_tbl_has_column($conn, 'tbl_departments', 'process_type')) {
            if (!function_exists('getList')) {
                return [];
            }
            $list = getList(
                'SELECT id, dept_name, auto_loss FROM tbl_departments'
                . ' WHERE status = 1 ORDER BY dept_name ASC, id ASC'
            );
            return is_array($list) ? $list : [];
        }
        if (!function_exists('getList')) {
            return [];
        }
        $list = getList(
            "SELECT id, dept_name, process_type, auto_loss FROM tbl_departments"
            . " WHERE status = 1 AND (process_type = 'Manufacturing Inhouse'"
            . " OR process_type IN ('Manufacturing', 'Melting', 'Testing'))"
            . ' ORDER BY dept_name ASC, id ASC'
        );
        return is_array($list) ? $list : [];
    }
}

if (!function_exists('auragold_inhouse_department_ids')) {
    /** @return int[] */
    function auragold_inhouse_department_ids(mysqli $conn): array
    {
        $ids = [];
        foreach (auragold_get_inhouse_departments($conn) as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return $ids;
    }
}

if (!function_exists('auragold_outsource_department_ids')) {
    /** @return int[] */
    function auragold_outsource_department_ids(mysqli $conn): array
    {
        $ids = [];
        foreach (auragold_get_outsource_departments($conn) as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        return $ids;
    }
}
