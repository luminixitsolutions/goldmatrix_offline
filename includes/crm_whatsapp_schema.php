<?php

/**
 * Ensures CRM WhatsApp campaign tables exist on the working DB ($conn).
 */
function auragold_ensure_crm_whatsapp_tables($conn)
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
        "CREATE TABLE IF NOT EXISTS `tbl_crm_whatsapp_campaigns` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `caption` VARCHAR(500) DEFAULT NULL,
            `customer_name` VARCHAR(255) NULL DEFAULT NULL,
            `contact_no` VARCHAR(64) NULL DEFAULT NULL,
            `message_body` MEDIUMTEXT NULL,
            `status` TINYINT UNSIGNED NOT NULL DEFAULT 1,
            `branch_id` INT UNSIGNED DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            `created_by` INT UNSIGNED DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_crm_wa_branch` (`branch_id`),
            KEY `idx_crm_wa_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    auragold_crm_ensure_column(
        $conn,
        'tbl_crm_whatsapp_campaigns',
        'customer_name',
        '`customer_name` VARCHAR(255) NULL DEFAULT NULL AFTER `caption`'
    );
    auragold_crm_ensure_column(
        $conn,
        'tbl_crm_whatsapp_campaigns',
        'contact_no',
        '`contact_no` VARCHAR(64) NULL DEFAULT NULL AFTER `customer_name`'
    );

    mysqli_query(
        $conn,
        "CREATE TABLE IF NOT EXISTS `tbl_crm_whatsapp_campaign_images` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `campaign_id` INT UNSIGNED NOT NULL,
            `image_path` VARCHAR(512) NOT NULL,
            `sort_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_crm_wa_img_campaign` (`campaign_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

/**
 * Adds a column to $table if it does not exist (for DBs created before the column existed).
 */
function auragold_crm_ensure_column($conn, $table, $column, $add_sql_fragment)
{
    if (!$conn || !($conn instanceof mysqli)) {
        return;
    }
    $t = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
    $c = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $column);
    if ($t === '' || $c === '') {
        return;
    }
    $q = mysqli_query($conn, "SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
    if ($q && mysqli_num_rows($q) === 0) {
        mysqli_query($conn, "ALTER TABLE `{$t}` ADD COLUMN {$add_sql_fragment}");
    }
}
