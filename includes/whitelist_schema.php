<?php

/**
 * IP whitelist entries + global IP access control toggle (registry DB).
 */
function auragold_ensure_whitelist_tables($conn)
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
        "CREATE TABLE IF NOT EXISTS `tbl_ip_access_settings` (
            `id` TINYINT UNSIGNED NOT NULL DEFAULT 1,
            `ip_access_control_enabled` TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    mysqli_query($conn, "INSERT IGNORE INTO `tbl_ip_access_settings` (`id`, `ip_access_control_enabled`) VALUES (1, 0)");

    mysqli_query(
        $conn,
        "CREATE TABLE IF NOT EXISTS `tbl_ip_whitelist` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `entity_value` VARCHAR(255) NOT NULL,
            `entry_type` VARCHAR(32) NOT NULL DEFAULT 'IP',
            `notes` TEXT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_whitelist_entity_type` (`entity_value`(191), `entry_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

/**
 * @return bool
 */
function auragold_ip_access_control_enabled($conn)
{
    if (!$conn || !($conn instanceof mysqli)) {
        return false;
    }
    auragold_ensure_whitelist_tables($conn);
    $r = mysqli_query($conn, 'SELECT ip_access_control_enabled FROM tbl_ip_access_settings WHERE id = 1 LIMIT 1');
    if ($r && ($row = mysqli_fetch_assoc($r))) {
        return !empty($row['ip_access_control_enabled']);
    }
    return false;
}

/**
 * Client IP (first hop). Extend later for X-Forwarded-For behind a trusted proxy.
 */
function auragold_client_ip()
{
    return trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
}

/**
 * When IP access control is enabled: allow if client IP matches any active whitelist pattern.
 * Patterns may use * (e.g. 192.168.*.*). Call from login after DB is available.
 */
function auragold_login_ip_allowed($conn)
{
    if (!$conn || !($conn instanceof mysqli)) {
        return true;
    }
    auragold_ensure_whitelist_tables($conn);
    if (!auragold_ip_access_control_enabled($conn)) {
        return true;
    }
    $ip = auragold_client_ip();
    if ($ip === '') {
        return false;
    }
    $rows = [];
    $res  = mysqli_query($conn, "SELECT entity_value FROM tbl_ip_whitelist WHERE entry_type = 'IP' AND is_active = 1");
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $rows[] = $row;
        }
    }
    foreach ($rows as $row) {
        $pat = trim((string) ($row['entity_value'] ?? ''));
        if ($pat === '') {
            continue;
        }
        if (strcasecmp($pat, $ip) === 0) {
            return true;
        }
        if (strpos($pat, '*') !== false && function_exists('fnmatch') && @fnmatch($pat, $ip)) {
            return true;
        }
    }
    return false;
}
