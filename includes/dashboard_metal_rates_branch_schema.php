<?php
/**
 * Adds branch_id to dashboard metal rate tables (per-branch gold / silver / diamond sheets).
 */
if (!function_exists('auragold_ensure_dashboard_metal_rates_branch_columns')) {
    function auragold_ensure_dashboard_metal_rates_branch_columns($conn): bool
    {
        if (!$conn instanceof mysqli) {
            return false;
        }
        static $done = [];
        $key = spl_object_hash($conn);
        if (!empty($done[$key])) {
            return true;
        }

        if (!function_exists('auragold_tbl_has_column')) {
            require_once __DIR__ . '/auragold_branch_data_scope.php';
        }

        $tmeta = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_dashboard_metal_meta'");
        $trates = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_dashboard_metal_rates'");
        if (!$tmeta || mysqli_num_rows($tmeta) === 0 || !$trates || mysqli_num_rows($trates) === 0) {
            if ($tmeta) {
                mysqli_free_result($tmeta);
            }
            if ($trates) {
                mysqli_free_result($trates);
            }
            $done[$key] = true;
            return false;
        }
        mysqli_free_result($tmeta);
        mysqli_free_result($trates);

        if (!auragold_tbl_has_column($conn, 'tbl_dashboard_metal_rates', 'branch_id')) {
            @mysqli_query($conn, 'ALTER TABLE tbl_dashboard_metal_rates ADD COLUMN branch_id INT NOT NULL DEFAULT 0 AFTER id');
            @mysqli_query($conn, 'ALTER TABLE tbl_dashboard_metal_rates DROP INDEX uk_metal_carat');
            @mysqli_query(
                $conn,
                'ALTER TABLE tbl_dashboard_metal_rates ADD UNIQUE KEY uk_branch_metal_carat (branch_id, metal, carat_label)'
            );
        }

        if (!auragold_tbl_has_column($conn, 'tbl_dashboard_metal_meta', 'branch_id')) {
            @mysqli_query($conn, 'ALTER TABLE tbl_dashboard_metal_meta ADD COLUMN branch_id INT NOT NULL DEFAULT 0 AFTER metal');
            @mysqli_query($conn, 'ALTER TABLE tbl_dashboard_metal_meta DROP PRIMARY KEY');
            @mysqli_query($conn, 'ALTER TABLE tbl_dashboard_metal_meta ADD PRIMARY KEY (metal, branch_id)');
        }

        $thist = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_dashboard_metal_rate_history'");
        if ($thist && mysqli_num_rows($thist) > 0) {
            mysqli_free_result($thist);
            if (!auragold_tbl_has_column($conn, 'tbl_dashboard_metal_rate_history', 'branch_id')) {
                @mysqli_query(
                    $conn,
                    'ALTER TABLE tbl_dashboard_metal_rate_history ADD COLUMN branch_id INT NOT NULL DEFAULT 0 AFTER id'
                );
                @mysqli_query(
                    $conn,
                    'ALTER TABLE tbl_dashboard_metal_rate_history ADD KEY idx_branch_metal_time (branch_id, metal, recorded_at)'
                );
            }
        } elseif ($thist) {
            mysqli_free_result($thist);
        }

        $done[$key] = true;
        return true;
    }
}
