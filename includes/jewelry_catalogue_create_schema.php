<?php

/**
 * Ensure tbl_jewelry_catalogue exists (jewellery catalog create/edit).
 */
if (!function_exists('auragold_ensure_jewelry_catalogue_table')) {
    function auragold_ensure_jewelry_catalogue_table(mysqli $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $sqlFile = dirname(__DIR__) . '/sql/create_tbl_jewelry_catalogue.sql';
        if (!is_file($sqlFile)) {
            return;
        }
        $sql = (string) file_get_contents($sqlFile);
        if ($sql === '') {
            return;
        }
        @mysqli_multi_query($conn, $sql);
        while (@mysqli_more_results($conn)) {
            @mysqli_next_result($conn);
        }
        $done = true;
        auragold_ensure_jewelry_catalogue_publish_column($conn);
    }
}

if (!function_exists('auragold_ensure_jewelry_catalogue_publish_column')) {
    function auragold_ensure_jewelry_catalogue_publish_column(mysqli $conn): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        if (!function_exists('auragold_tbl_has_column')) {
            require_once __DIR__ . '/auragold_branch_data_scope.php';
        }
        if (!function_exists('auragold_tbl_has_column') || !auragold_tbl_has_column($conn, 'tbl_jewelry_catalogue', 'publish_on_website')) {
            @mysqli_query(
                $conn,
                'ALTER TABLE tbl_jewelry_catalogue ADD COLUMN publish_on_website tinyint(1) NOT NULL DEFAULT 0 AFTER status'
            );
        }
        $done = true;
    }
}
