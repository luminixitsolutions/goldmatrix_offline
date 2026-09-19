<?php
/**
 * User menu layout preference (horizontal strip vs vertical sidebar).
 * Stored on tbl_users.menu_style; cached in $_SESSION['Admin']['menu_style'].
 */

function auragold_normalize_menu_style($value): string
{
    $v = strtolower(trim((string) $value));
    return ($v === 'vertical') ? 'vertical' : 'horizontal';
}

function auragold_ensure_tbl_users_menu_style_column($conn): void
{
    if (!$conn instanceof mysqli) {
        return;
    }
    static $done = [];
    $key = spl_object_hash($conn);
    if (!empty($done[$key])) {
        return;
    }
    $c = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_users LIKE 'menu_style'");
    if ($c && mysqli_num_rows($c) === 0) {
        @mysqli_query(
            $conn,
            "ALTER TABLE tbl_users ADD COLUMN menu_style VARCHAR(20) NOT NULL DEFAULT 'horizontal'
             COMMENT 'Main nav layout: horizontal|vertical'"
        );
    }
    if ($c) {
        mysqli_free_result($c);
    }
    $done[$key] = true;
}

function auragold_user_menu_style_db_link()
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

function auragold_get_user_menu_style(?int $userId = null): string
{
    if (!empty($_SESSION['Admin']) && is_array($_SESSION['Admin'])) {
        foreach ($_SESSION['Admin'] as $k => $v) {
            if (strcasecmp((string) $k, 'menu_style') === 0 && trim((string) $v) !== '') {
                return auragold_normalize_menu_style($v);
            }
        }
    }

    if ($userId === null || $userId <= 0) {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
    }
    if ($userId <= 0) {
        return 'horizontal';
    }

    $link = auragold_user_menu_style_db_link();
    if (!$link instanceof mysqli) {
        return 'horizontal';
    }
    auragold_ensure_tbl_users_menu_style_column($link);

    $sql = 'SELECT menu_style FROM tbl_users WHERE id = ' . (int) $userId . ' LIMIT 1';
    $row = function_exists('getRecord') ? getRecord($sql) : null;
    if ((!$row || !is_array($row)) && function_exists('getRecordMaster')) {
        $row = getRecordMaster($sql);
    }
    $style = auragold_normalize_menu_style($row['menu_style'] ?? 'horizontal');

    if (!empty($_SESSION['Admin']) && is_array($_SESSION['Admin'])) {
        $_SESSION['Admin']['menu_style'] = $style;
    }

    return $style;
}

function auragold_sync_user_menu_style_in_session(string $style): void
{
    $style = auragold_normalize_menu_style($style);
    if (!empty($_SESSION['Admin']) && is_array($_SESSION['Admin'])) {
        $_SESSION['Admin']['menu_style'] = $style;
    }
}
