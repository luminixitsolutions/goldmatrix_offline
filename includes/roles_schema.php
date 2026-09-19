<?php

/**
 * Registry table for Administration → Roles (role name, active, account ledger flag).
 */
function auragold_ensure_roles_table($conn)
{
    if (!$conn || !($conn instanceof mysqli)) {
        return;
    }
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    mysqli_query(
        $conn,
        "CREATE TABLE IF NOT EXISTS `tbl_roles` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `role_name` VARCHAR(128) NOT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `account_ledger_assigned` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_tbl_roles_name` (`role_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $cntRes = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM tbl_roles');
    if ($cntRes && ($cntRow = mysqli_fetch_assoc($cntRes)) && (int) ($cntRow['c'] ?? 0) === 0) {
        mysqli_query(
            $conn,
            "INSERT INTO `tbl_roles` (`role_name`, `is_active`, `account_ledger_assigned`) VALUES
            ('Admin', 1, 0),
            ('Sales Person', 1, 0),
            ('Branch Manager', 1, 0)"
        );
    }
}
