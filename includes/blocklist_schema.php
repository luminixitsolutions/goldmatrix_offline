<?php

require_once __DIR__ . '/whitelist_schema.php';

/**
 * Blocked login attempts / IP–user blocks (registry DB).
 */
function auragold_ensure_blocklist_table($conn)
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
        "CREATE TABLE IF NOT EXISTS `tbl_login_blocklist` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `ip_address` VARCHAR(64) NOT NULL,
            `username` VARCHAR(128) NULL DEFAULT NULL,
            `user_id` INT NULL DEFAULT NULL,
            `attempt_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `last_attempt_at` DATETIME NULL DEFAULT NULL,
            `blocked_until` DATETIME NULL DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_ip` (`ip_address`),
            KEY `idx_blocked_until` (`blocked_until`),
            KEY `idx_user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

/**
 * True if login should be denied from blocklist (active row for this IP).
 * $attemptedUsername: value from login form; matches IP-wide blocks (empty username on row) or same user.
 */
function auragold_login_ip_blocklisted($conn, $attemptedUsername = '')
{
    if (!$conn || !($conn instanceof mysqli)) {
        return false;
    }
    auragold_ensure_blocklist_table($conn);
    $ip = auragold_client_ip();
    if ($ip === '') {
        return false;
    }
    $ip_esc = esc($ip);
    $u_esc  = esc(trim((string) $attemptedUsername));
    $now    = esc(date('Y-m-d H:i:s'));

    $sql = "
        SELECT id FROM tbl_login_blocklist
        WHERE ip_address = '$ip_esc'
          AND (blocked_until IS NULL OR blocked_until > '$now')
          AND (
            username IS NULL OR TRIM(username) = ''
            OR username = '$u_esc'
          )
        LIMIT 1
    ";

    $r = mysqli_query($conn, $sql);
    return $r && mysqli_num_rows($r) > 0;
}
