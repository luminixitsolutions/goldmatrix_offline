<?php

/**
 * CRM Contact & Groups: group list + membership (working DB).
 */
function auragold_ensure_crm_contact_groups_tables($conn)
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
        "CREATE TABLE IF NOT EXISTS `tbl_crm_contact_groups` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) NOT NULL,
            `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_crm_cg_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    mysqli_query(
        $conn,
        "CREATE TABLE IF NOT EXISTS `tbl_crm_contact_group_members` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `group_id` INT UNSIGNED NOT NULL,
            `customer_id` INT UNSIGNED NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_crm_cg_member` (`group_id`, `customer_id`),
            KEY `idx_crm_cg_gid` (`group_id`),
            KEY `idx_crm_cg_cust` (`customer_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}
