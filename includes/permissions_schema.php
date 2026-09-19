<?php

/**
 * User-wise permission grants (menu + per-page view/add/update/delete).
 * Optional `branch_id`: 0 = default / fallback for all branches; &gt;0 = overrides when session effective branch matches.
 */
function auragold_ensure_user_permissions_table($conn)
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
        "CREATE TABLE IF NOT EXISTS `tbl_user_permission_grants` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT NOT NULL,
            `branch_id` INT NOT NULL DEFAULT 0,
            `perm_key` VARCHAR(160) NOT NULL,
            `granted` TINYINT(1) NOT NULL DEFAULT 0,
            `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_user_branch_perm` (`user_id`, `branch_id`, `perm_key`),
            KEY `idx_user` (`user_id`),
            KEY `idx_user_branch` (`user_id`, `branch_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    auragold_permission_grants_migrate_branch_column($conn);
}

/**
 * Older installs: table without branch_id and unique (user_id, perm_key) only.
 */
function auragold_permission_grants_migrate_branch_column($conn)
{
    if (!$conn || !($conn instanceof mysqli)) {
        return;
    }
    static $migrated = false;
    if ($migrated) {
        return;
    }
    $migrated = true;

    $q = mysqli_query($conn, "SHOW COLUMNS FROM `tbl_user_permission_grants` LIKE 'branch_id'");
    if ($q && mysqli_num_rows($q) > 0) {
        return;
    }

    mysqli_query($conn, 'ALTER TABLE `tbl_user_permission_grants` ADD COLUMN `branch_id` INT NOT NULL DEFAULT 0 AFTER `user_id`');

    $idxRes = mysqli_query($conn, "SHOW INDEX FROM `tbl_user_permission_grants` WHERE Key_name = 'uk_user_perm'");
    if ($idxRes && mysqli_num_rows($idxRes) > 0) {
        mysqli_query($conn, 'ALTER TABLE `tbl_user_permission_grants` DROP INDEX `uk_user_perm`');
    }
    mysqli_query(
        $conn,
        'ALTER TABLE `tbl_user_permission_grants` ADD UNIQUE KEY `uk_user_branch_perm` (`user_id`, `branch_id`, `perm_key`)'
    );
    mysqli_query(
        $conn,
        'ALTER TABLE `tbl_user_permission_grants` ADD KEY `idx_user_branch` (`user_id`, `branch_id`)'
    );
}
