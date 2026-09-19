<?php
/**
 * Superadmin: usernames listed in AURAGOLD_SUPERADMIN_USERNAMES (config.php).
 */

/**
 * Central super-portal URL(s): full global branch discovery and default-main (login_branch_id 0)
 * superadmin / template "superbranch" flows must use this host in the login "IP address / server URL" field.
 * Non-production also allows localhost for development.
 */
if (!function_exists('auragold_login_target_url_host')) {
    function auragold_login_target_url_host(?string $login_target_url): string {
        $raw = trim((string) $login_target_url);
        if ($raw === '') {
            return '';
        }
        if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $raw)) {
            $raw = 'https://' . $raw;
        }
        $host = parse_url($raw, PHP_URL_HOST);
        if ($host === false || $host === null || $host === '') {
            return '';
        }
        return strtolower(trim((string) $host));
    }
}

if (!function_exists('auragold_super_portal_login_target_ok')) {
    function auragold_super_portal_login_target_ok(?string $login_target_url): bool {
        $host = auragold_login_target_url_host($login_target_url);
        if ($host === '') {
            return false;
        }
        $allowed = ['main.goldmatrixsoftware.com', 'main.goldmatrixsoft.com'];
        if (defined('AURAGOLD_PROJECT') && AURAGOLD_PROJECT !== 'prod') {
            $allowed[] = 'localhost';
            $allowed[] = '127.0.0.1';
        }
        return in_array($host, $allowed, true);
    }
}

/**
 * GM main-branch portal: superadmin may create new main branches when logging in with this host
 * in the login "IP address / server URL" field (e.g. http://gm.goldmatrixsoft.com).
 */
if (!function_exists('auragold_gm_portal_login_target_ok')) {
    function auragold_gm_portal_login_target_ok(?string $login_target_url): bool {
        $host = auragold_login_target_url_host($login_target_url);
        if ($host === '') {
            return false;
        }
        $allowed = ['gm.goldmatrixsoft.com'];
        if (defined('AURAGOLD_PROJECT') && AURAGOLD_PROJECT !== 'prod') {
            $allowed[] = 'localhost';
            $allowed[] = '127.0.0.1';
        }
        return in_array($host, $allowed, true);
    }
}

if (!function_exists('auragold_superadmin_allowed_login_portal_ok')) {
    function auragold_superadmin_allowed_login_portal_ok(?string $login_target_url): bool {
        return auragold_super_portal_login_target_ok($login_target_url)
            || auragold_gm_portal_login_target_ok($login_target_url);
    }
}

if (!function_exists('auragold_superadmin_discovery_branches_for_login_target')) {
    /**
     * Branch dropdown options after superadmin credential verify.
     *
     * @return list<array{id:int,label:string,db_name?:string}>
     */
    function auragold_superadmin_discovery_branches_for_login_target(string $login_target_url, int $url_scope_id = 0): array {
        if (auragold_super_portal_login_target_ok($login_target_url)
            && function_exists('auragold_login_superadmin_discovery_branch_options')) {
            return auragold_login_superadmin_discovery_branch_options();
        }
        if (auragold_gm_portal_login_target_ok($login_target_url) && $url_scope_id > 0) {
            $prefRow = function_exists('auragold_registry_tbl_branches_row_by_id')
                ? auragold_registry_tbl_branches_row_by_id($url_scope_id)
                : (function_exists('getRecordMaster')
                    ? getRecordMaster('SELECT id, name, db_name FROM tbl_branches WHERE id = ' . (int) $url_scope_id . ' LIMIT 1')
                    : null);
            if ($prefRow) {
                $nm = trim((string) ($prefRow['name'] ?? ''));
                if ($nm === '') {
                    $nm = 'Branch #' . (int) $url_scope_id;
                }
                $opt = [
                    [
                        'id'    => (int) $url_scope_id,
                        'label' => $nm,
                    ],
                ];
                return function_exists('auragold_login_branch_options_add_db_name')
                    ? auragold_login_branch_options_add_db_name($opt)
                    : $opt;
            }
        }
        if (auragold_gm_portal_login_target_ok($login_target_url)
            && function_exists('auragold_login_superadmin_discovery_branch_options')) {
            return auragold_login_superadmin_discovery_branch_options();
        }
        return [];
    }
}

if (!function_exists('auragold_session_may_create_main_branch')) {
    /**
     * Show "Create Main Branch" when logged in as superadmin from the GM portal URL.
     * Password is verified at login; session flag is set in login_submit.php on success.
     */
    function auragold_session_may_create_main_branch(): bool {
        if (empty($_SESSION['Admin']) || !is_array($_SESSION['Admin'])) {
            return false;
        }
        if (!function_exists('auragold_session_is_admin_login_type') || !auragold_session_is_admin_login_type()) {
            return false;
        }
        if (!auragold_session_is_superadmin()) {
            return false;
        }
        if (!empty($_SESSION['auragold_may_create_main_branch'])) {
            return true;
        }
        $target = isset($_SESSION['login_target_url']) ? (string) $_SESSION['login_target_url'] : '';
        return auragold_gm_portal_login_target_ok($target);
    }
}

if (!function_exists('auragold_session_is_superadmin')) {
    function auragold_session_is_superadmin(): bool {
        if (empty($_SESSION['Admin']) || !is_array($_SESSION['Admin'])) {
            return false;
        }
        $u = trim((string) ($_SESSION['Admin']['Username'] ?? $_SESSION['Admin']['username'] ?? ''));
        if ($u === '') {
            return false;
        }
        if (!defined('AURAGOLD_SUPERADMIN_USERNAMES')) {
            return false;
        }
        $list = array_map('trim', explode(',', (string) AURAGOLD_SUPERADMIN_USERNAMES));
        foreach ($list as $entry) {
            if ($entry !== '' && strcasecmp($u, $entry) === 0) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('auragold_username_is_superadmin')) {
    function auragold_username_is_superadmin(?string $username): bool {
        $u = trim((string) $username);
        if ($u === '' || !defined('AURAGOLD_SUPERADMIN_USERNAMES')) {
            return false;
        }
        $list = array_map('trim', explode(',', (string) AURAGOLD_SUPERADMIN_USERNAMES));
        foreach ($list as $entry) {
            if ($entry !== '' && strcasecmp($u, $entry) === 0) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('auragold_user_row_is_superadmin')) {
    function auragold_user_row_is_superadmin(?array $user): bool {
        if (empty($user) || !is_array($user)) {
            return false;
        }
        $u = '';
        foreach ($user as $k => $v) {
            if (strcasecmp((string) $k, 'Username') === 0 || strcasecmp((string) $k, 'username') === 0) {
                $u = trim((string) $v);
                break;
            }
        }
        return auragold_username_is_superadmin($u);
    }
}
