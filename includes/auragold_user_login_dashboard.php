<?php
/**
 * User preference for the page opened immediately after login.
 * Stored on tbl_users.login_dashboard; cached in $_SESSION['Admin']['login_dashboard'].
 */

require_once __DIR__ . '/auragold_sidebar_nav_permissions.php';

/**
 * @return array<string, array{label:string, href:string}>
 */
function auragold_login_dashboard_options(): array
{
    return [
        'retailer'      => ['label' => 'Retailer', 'href' => 'dashboard-retailer.php'],
        'wholesaler'    => ['label' => 'Wholesaler', 'href' => 'dashboard-wholesaler.php'],
        'manufacturing' => ['label' => 'Manufacturing / Job worker', 'href' => 'dashboard-manufacturing.php'],
        'sales_person'  => ['label' => 'Sales person', 'href' => 'dashboard-sales-person.php'],
        'gold_rates'    => ['label' => 'Gold rates', 'href' => 'dashboard.php'],
        'stock'         => ['label' => 'Stock', 'href' => 'dashboard-stock.php'],
    ];
}

function auragold_normalize_login_dashboard($value): string
{
    $v = strtolower(trim((string) $value));
    $options = auragold_login_dashboard_options();

    return isset($options[$v]) ? $v : '';
}

function auragold_login_dashboard_href(string $key): string
{
    $key = auragold_normalize_login_dashboard($key);
    if ($key === '') {
        return 'dashboard.php';
    }
    $options = auragold_login_dashboard_options();

    return $options[$key]['href'] ?? 'dashboard.php';
}

function auragold_ensure_tbl_users_login_dashboard_column($conn): void
{
    if (!$conn instanceof mysqli) {
        return;
    }
    static $done = [];
    $key = spl_object_hash($conn);
    if (!empty($done[$key])) {
        return;
    }
    try {
        $c = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_users LIKE 'login_dashboard'");
        if ($c && mysqli_num_rows($c) === 0) {
            @mysqli_query(
                $conn,
                "ALTER TABLE tbl_users ADD COLUMN login_dashboard VARCHAR(40) NOT NULL DEFAULT ''
                 COMMENT 'Post-login dashboard: retailer|wholesaler|manufacturing|sales_person|gold_rates|stock'"
            );
        }
        if ($c) {
            mysqli_free_result($c);
        }
    } catch (Throwable $e) {
        // PHP 8.1+ mysqli may throw; login must not fail if column migration is unavailable.
    }
    $done[$key] = true;
}

function auragold_user_login_dashboard_db_link()
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

/**
 * Username from $_SESSION['Admin'] (case-insensitive key).
 */
function auragold_session_admin_username(): string
{
    if (empty($_SESSION['Admin']) || !is_array($_SESSION['Admin'])) {
        return '';
    }
    foreach ($_SESSION['Admin'] as $k => $v) {
        if (strcasecmp((string) $k, 'Username') === 0 || strcasecmp((string) $k, 'username') === 0) {
            return trim((string) $v);
        }
    }

    return '';
}

/**
 * Read login_dashboard from a specific mysqli (prefer username — ids differ across DBs).
 */
function auragold_fetch_login_dashboard_from_link(mysqli $link, int $userId, string $username): ?string
{
    auragold_ensure_tbl_users_login_dashboard_column($link);

    $row = null;
    if ($username !== '') {
        $e = mysqli_real_escape_string($link, $username);
        $res = @mysqli_query(
            $link,
            "SELECT login_dashboard FROM tbl_users WHERE LOWER(TRIM(Username)) = LOWER(TRIM('$e')) LIMIT 1"
        );
        if ($res && mysqli_num_rows($res) > 0) {
            $row = mysqli_fetch_assoc($res);
        }
        if ($res) {
            mysqli_free_result($res);
        }
    }
    if ((!$row || !is_array($row)) && $userId > 0) {
        $res = @mysqli_query(
            $link,
            'SELECT login_dashboard FROM tbl_users WHERE id = ' . (int) $userId . ' LIMIT 1'
        );
        if ($res && mysqli_num_rows($res) > 0) {
            $row = mysqli_fetch_assoc($res);
        }
        if ($res) {
            mysqli_free_result($res);
        }
    }
    if (!$row || !is_array($row)) {
        return null;
    }
    $raw = '';
    foreach ($row as $rk => $rv) {
        if (strcasecmp((string) $rk, 'login_dashboard') === 0) {
            $raw = (string) $rv;
            break;
        }
    }

    return auragold_normalize_login_dashboard($raw);
}

function auragold_get_user_login_dashboard(?int $userId = null): string
{
    // Only trust a non-empty session value. Login may load Admin from a credential DB
    // while My Profile saves to the host/working operational DB.
    if (!empty($_SESSION['Admin']) && is_array($_SESSION['Admin'])) {
        foreach ($_SESSION['Admin'] as $k => $v) {
            if (strcasecmp((string) $k, 'login_dashboard') === 0 && trim((string) $v) !== '') {
                return auragold_normalize_login_dashboard($v);
            }
        }
    }

    if ($userId === null || $userId <= 0) {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
    }
    $username = auragold_session_admin_username();
    if ($userId <= 0 && $username === '') {
        return '';
    }

    if (function_exists('auragold_bind_conn_to_working_session')) {
        auragold_bind_conn_to_working_session();
    }

    $links = [];
    $link = auragold_user_login_dashboard_db_link();
    if ($link instanceof mysqli) {
        $links[] = $link;
    }
    // Fresh link from working_db when global $conn may still be pre-login.
    if (!empty($_SESSION['working_db']) && is_array($_SESSION['working_db']) && defined('DB_HOST')) {
        $wdb    = $_SESSION['working_db'];
        $dbname = trim((string) ($wdb['database'] ?? $wdb['db_name'] ?? ''));
        $dbuser = trim((string) ($wdb['user'] ?? $wdb['db_user'] ?? ''));
        $dbpass = (string) ($wdb['password'] ?? $wdb['db_pass'] ?? '');
        if ($dbuser === '') {
            $dbuser = defined('DB_USER') ? (string) DB_USER : '';
            $dbpass = defined('DB_PASS') ? (string) DB_PASS : '';
        }
        if ($dbname !== '') {
            $fresh = null;
            if (function_exists('auragold_mysqli_connect_operational')) {
                $fresh = auragold_mysqli_connect_operational((string) DB_HOST, $dbuser, $dbpass, $dbname);
            }
            if (!$fresh instanceof mysqli) {
                $fresh = @mysqli_connect((string) DB_HOST, $dbuser, $dbpass, $dbname);
            }
            if ($fresh instanceof mysqli) {
                mysqli_set_charset($fresh, 'utf8mb4');
                $links[] = $fresh;
            }
        }
    }
    // Login branch / session branch operational schema (Main id 0 → registry main db_name).
    if (defined('DB_HOST')) {
        if (!function_exists('auragold_login_expected_db_name_for_branch_id')) {
            $credFile = __DIR__ . '/login_credential_connections.php';
            if (is_file($credFile)) {
                require_once $credFile;
            }
        }
    }
    if (defined('DB_HOST') && function_exists('auragold_login_expected_db_name_for_branch_id')) {
        $lb = (int) ($_SESSION['auragold_login_branch_id'] ?? -1);
        $bid = (int) ($_SESSION['branch_id'] ?? $_SESSION['working_branch_id'] ?? 0);
        $lookupId = $lb > 0 ? $lb : ($bid > 0 ? $bid : 0);
        $opDb = trim((string) auragold_login_expected_db_name_for_branch_id($lookupId));
        if ($opDb !== '') {
            $already = false;
            foreach ($links as $existing) {
                if (!$existing instanceof mysqli) {
                    continue;
                }
                $dbRes = @mysqli_query($existing, 'SELECT DATABASE() AS d');
                $cur = '';
                if ($dbRes && ($dbRow = mysqli_fetch_assoc($dbRes))) {
                    $cur = trim((string) ($dbRow['d'] ?? ''));
                }
                if ($dbRes) {
                    mysqli_free_result($dbRes);
                }
                if ($cur !== '' && strcasecmp($cur, $opDb) === 0) {
                    $already = true;
                    break;
                }
            }
            if (!$already) {
                $dbuser = defined('DB_USER') ? (string) DB_USER : '';
                $dbpass = defined('DB_PASS') ? (string) DB_PASS : '';
                $fresh = null;
                if (function_exists('auragold_mysqli_connect_operational')) {
                    $fresh = auragold_mysqli_connect_operational((string) DB_HOST, $dbuser, $dbpass, $opDb);
                }
                if (!$fresh instanceof mysqli) {
                    try {
                        $fresh = @mysqli_connect((string) DB_HOST, $dbuser, $dbpass, $opDb);
                    } catch (Throwable $e) {
                        $fresh = null;
                    }
                }
                if ($fresh instanceof mysqli) {
                    mysqli_set_charset($fresh, 'utf8mb4');
                    $links[] = $fresh;
                }
            }
        }
    }

    $dashboard = '';
    $found     = false;
    $extraClose = [];
    foreach ($links as $idx => $tryLink) {
        if (!$tryLink instanceof mysqli) {
            continue;
        }
        if ($idx > 0 && $tryLink !== $link) {
            $extraClose[] = $tryLink;
        }
        $got = auragold_fetch_login_dashboard_from_link($tryLink, $userId, $username);
        if ($got === null) {
            continue;
        }
        $found     = true;
        $dashboard = $got;
        if ($dashboard !== '') {
            break;
        }
    }
    foreach ($extraClose as $c) {
        if ($c instanceof mysqli && $c !== $link) {
            @mysqli_close($c);
        }
    }

    if (!$found && function_exists('getRecordMaster') && ($userId > 0 || $username !== '')) {
        if ($username !== '') {
            $e = function_exists('esc') ? esc($username) : addslashes($username);
            $row = getRecordMaster(
                "SELECT login_dashboard FROM tbl_users WHERE LOWER(TRIM(Username)) = LOWER(TRIM('$e')) LIMIT 1"
            );
        } else {
            $row = getRecordMaster(
                'SELECT login_dashboard FROM tbl_users WHERE id = ' . (int) $userId . ' LIMIT 1'
            );
        }
        if (is_array($row)) {
            $raw = '';
            foreach ($row as $rk => $rv) {
                if (strcasecmp((string) $rk, 'login_dashboard') === 0) {
                    $raw = (string) $rv;
                    break;
                }
            }
            $dashboard = auragold_normalize_login_dashboard($raw);
            $found     = true;
        }
    }

    if (!empty($_SESSION['Admin']) && is_array($_SESSION['Admin'])) {
        $_SESSION['Admin']['login_dashboard'] = $dashboard;
    }

    return $dashboard;
}

function auragold_sync_user_login_dashboard_in_session(string $dashboard): void
{
    $dashboard = auragold_normalize_login_dashboard($dashboard);
    if (!empty($_SESSION['Admin']) && is_array($_SESSION['Admin'])) {
        $_SESSION['Admin']['login_dashboard'] = $dashboard;
    }
}

/**
 * Resolve redirect target after login.
 * Honors the saved preference; do not demote to gold-rates when nav grants are incomplete
 * at login time (My Profile already filtered selectable dashboards).
 */
function auragold_resolve_post_login_redirect_url(?int $userId = null): string
{
    return auragold_login_dashboard_href(auragold_get_user_login_dashboard($userId));
}

/**
 * Dashboard options the current user may choose in My Profile.
 *
 * @return array<string, string> key => label
 */
function auragold_login_dashboard_select_options_for_user(): array
{
    $out = ['' => 'Default (Gold rates dashboard)'];
    foreach (auragold_login_dashboard_options() as $key => $meta) {
        $href = (string) ($meta['href'] ?? '');
        if ($href !== '' && function_exists('auragold_nav_show_php_href') && !auragold_nav_show_php_href($href)) {
            continue;
        }
        $out[$key] = (string) ($meta['label'] ?? $key);
    }

    return $out;
}
