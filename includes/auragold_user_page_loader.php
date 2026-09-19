<?php
/**
 * User preference for the full-screen brand page loader.
 * Stored on tbl_users.page_loader; cached in $_SESSION['Admin']['page_loader'].
 * Values: on|off (default on).
 */

function auragold_normalize_page_loader($value): string
{
    $v = strtolower(trim((string) $value));
    return ($v === 'off' || $v === '0' || $v === 'false' || $v === 'no') ? 'off' : 'on';
}

function auragold_ensure_tbl_users_page_loader_column($conn): void
{
    if (!$conn instanceof mysqli) {
        return;
    }
    static $done = [];
    $key = spl_object_hash($conn);
    if (!empty($done[$key])) {
        return;
    }
    $c = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_users LIKE 'page_loader'");
    if ($c && mysqli_num_rows($c) === 0) {
        @mysqli_query(
            $conn,
            "ALTER TABLE tbl_users ADD COLUMN page_loader VARCHAR(10) NOT NULL DEFAULT 'on'
             COMMENT 'Brand splash loader: on|off'"
        );
    }
    if ($c) {
        mysqli_free_result($c);
    }
    $done[$key] = true;
}

function auragold_user_page_loader_db_link()
{
    global $conn, $conn_master;
    if (isset($conn) && $conn instanceof mysqli) {
        return $conn;
    }
    if (isset($conn_master) && $conn_master instanceof mysqli) {
        return $conn_master;
    }
    return null;
}

function auragold_get_user_page_loader(?int $userId = null): string
{
    if (!empty($_SESSION['Admin']) && is_array($_SESSION['Admin'])) {
        foreach ($_SESSION['Admin'] as $k => $v) {
            if (strcasecmp((string) $k, 'page_loader') === 0 && trim((string) $v) !== '') {
                return auragold_normalize_page_loader($v);
            }
        }
    }

    if ($userId === null || $userId <= 0) {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
    }
    if ($userId <= 0) {
        return 'on';
    }

    $link = auragold_user_page_loader_db_link();
    if (!$link instanceof mysqli) {
        return 'on';
    }
    auragold_ensure_tbl_users_page_loader_column($link);

    $sql = 'SELECT page_loader FROM tbl_users WHERE id = ' . (int) $userId . ' LIMIT 1';
    $row = function_exists('getRecord') ? getRecord($sql) : null;
    if ((!$row || !is_array($row)) && function_exists('getRecordMaster')) {
        $row = getRecordMaster($sql);
    }
    $pref = auragold_normalize_page_loader($row['page_loader'] ?? 'on');

    if (!empty($_SESSION['Admin']) && is_array($_SESSION['Admin'])) {
        $_SESSION['Admin']['page_loader'] = $pref;
    }

    return $pref;
}

function auragold_user_page_loader_enabled(?int $userId = null): bool
{
    return auragold_get_user_page_loader($userId) === 'on';
}

function auragold_sync_user_page_loader_in_session(string $pref): void
{
    $pref = auragold_normalize_page_loader($pref);
    if (!empty($_SESSION['Admin']) && is_array($_SESSION['Admin'])) {
        $_SESSION['Admin']['page_loader'] = $pref;
    }
}
