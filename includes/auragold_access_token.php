<?php
/**
 * Fixed per-user API access token helpers.
 * Loaded from config.php so any authenticated page creates the token once if missing.
 */

if (!function_exists('auragold_ensure_tbl_users_access_token_column')) {
    /**
     * Ensures tbl_users.access_token exists.
     *
     * @param mysqli $conn
     */
    function auragold_ensure_tbl_users_access_token_column($conn): void
    {
        if (!$conn instanceof mysqli) {
            return;
        }
        static $doneTok = [];
        $key = spl_object_hash($conn);
        if (!empty($doneTok[$key])) {
            return;
        }
        $c = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_users LIKE 'access_token'");
        if ($c && mysqli_num_rows($c) === 0) {
            @mysqli_query(
                $conn,
                "ALTER TABLE tbl_users ADD COLUMN access_token VARCHAR(64) NULL DEFAULT NULL
                 COMMENT 'Fixed user access token; created once, never rotated here'"
            );
        }
        if ($c) {
            mysqli_free_result($c);
        }
        $doneTok[$key] = true;
    }
}

if (!function_exists('auragold_ensure_user_access_token')) {
    /**
     * Return existing access_token, or create one once if empty. Never overwrites a set token.
     *
     * @param mysqli $conn
     * @return string Token value (may be empty if user row missing / DB update failed)
     */
    function auragold_ensure_user_access_token($conn, int $userId): string
    {
        if (!$conn instanceof mysqli || $userId <= 0) {
            return '';
        }
        auragold_ensure_tbl_users_access_token_column($conn);

        $uid = (int) $userId;
        $rs  = @mysqli_query($conn, 'SELECT access_token FROM tbl_users WHERE id = ' . $uid . ' LIMIT 1');
        if (!$rs) {
            return '';
        }
        $row = mysqli_fetch_assoc($rs);
        mysqli_free_result($rs);
        if (!is_array($row)) {
            return '';
        }

        $existing = trim((string) ($row['access_token'] ?? ''));
        if ($existing !== '') {
            return $existing;
        }

        try {
            $token = bin2hex(random_bytes(32));
        } catch (Throwable $e) {
            $token = hash('sha256', uniqid((string) $uid, true) . microtime(true));
        }

        $esc = mysqli_real_escape_string($conn, $token);

        // Only set when still empty — keeps the token fixed after first create.
        @mysqli_query(
            $conn,
            "UPDATE tbl_users SET access_token = '" . $esc . "'
             WHERE id = " . $uid . "
               AND (access_token IS NULL OR TRIM(access_token) = '')
             LIMIT 1"
        );

        $rs2 = @mysqli_query($conn, 'SELECT access_token FROM tbl_users WHERE id = ' . $uid . ' LIMIT 1');
        if ($rs2) {
            $row2 = mysqli_fetch_assoc($rs2);
            mysqli_free_result($rs2);
            return trim((string) ($row2['access_token'] ?? ''));
        }

        return $token;
    }
}

if (!function_exists('auragold_get_session_access_token')) {
    /**
     * Cached token from session (after bootstrap), or empty string.
     */
    function auragold_get_session_access_token(): string
    {
        if (!function_exists('session_status') || session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }
        if (!empty($_SESSION['Admin']) && is_array($_SESSION['Admin'])) {
            $t = trim((string) ($_SESSION['Admin']['access_token'] ?? ''));
            if ($t !== '') {
                return $t;
            }
        }
        return trim((string) ($_SESSION['access_token'] ?? ''));
    }
}

if (!function_exists('auragold_bootstrap_session_access_token')) {
    /**
     * On any authenticated request: create access_token if missing, cache in session.
     * Safe to call repeatedly (once per request via static).
     *
     * @return string The user's fixed access token, or ''
     */
    function auragold_bootstrap_session_access_token(): string
    {
        static $done = false;
        static $cached = '';

        if ($done) {
            return $cached;
        }
        $done = true;

        if (PHP_SAPI === 'cli') {
            return '';
        }
        if (!function_exists('session_status') || session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }

        $uid = (int) ($_SESSION['user_id'] ?? 0);
        if ($uid <= 0 || empty($_SESSION['Admin']) || !is_array($_SESSION['Admin'])) {
            return '';
        }

        // Branch-portal logins use branch id as user_id — skip tbl_users token there.
        $src = strtolower(trim((string) ($_SESSION['login_source'] ?? '')));
        if ($src === 'branch') {
            return '';
        }

        // Fast path: already on session from an earlier request.
        $existing = auragold_get_session_access_token();
        if ($existing !== '') {
            $cached = $existing;
            return $cached;
        }

        global $conn, $conn_master;

        $links = [];
        if (isset($conn) && $conn instanceof mysqli) {
            $links[] = $conn;
        }
        if (isset($conn_master) && $conn_master instanceof mysqli && (!isset($conn) || $conn_master !== $conn)) {
            $links[] = $conn_master;
        }
        if (!empty($GLOBALS['auragold_registry_mysqli']) && $GLOBALS['auragold_registry_mysqli'] instanceof mysqli) {
            $reg = $GLOBALS['auragold_registry_mysqli'];
            $dup = false;
            foreach ($links as $l) {
                if ($l === $reg) {
                    $dup = true;
                    break;
                }
            }
            if (!$dup) {
                $links[] = $reg;
            }
        }

        $token = '';
        foreach ($links as $link) {
            $token = auragold_ensure_user_access_token($link, $uid);
            if ($token !== '') {
                break;
            }
        }

        if ($token !== '') {
            $_SESSION['access_token'] = $token;
            $_SESSION['Admin']['access_token'] = $token;
            $cached = $token;
        }

        return $cached;
    }
}

if (!function_exists('auragold_ensure_tbl_branches_access_token_column')) {
    /**
     * Ensures tbl_branches.access_token exists (fixed token per login shop / main branch).
     *
     * @param mysqli $conn
     */
    function auragold_ensure_tbl_branches_access_token_column($conn): void
    {
        if (!$conn instanceof mysqli) {
            return;
        }
        static $doneTok = [];
        $key = spl_object_hash($conn);
        if (!empty($doneTok[$key])) {
            return;
        }
        try {
            $c = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_branches LIKE 'access_token'");
            if ($c && mysqli_num_rows($c) === 0) {
                @mysqli_query(
                    $conn,
                    "ALTER TABLE tbl_branches ADD COLUMN access_token VARCHAR(64) NULL DEFAULT NULL
                     COMMENT 'Fixed shop access token; created once, never rotated here'"
                );
            }
            if ($c) {
                mysqli_free_result($c);
            }
        } catch (Throwable $e) {
            // ignore — lack of ALTER privilege or missing table
        }
        $doneTok[$key] = true;
    }
}

if (!function_exists('auragold_ensure_shop_access_token')) {
    /**
     * Return existing shop access_token, or create one once if empty. Never overwrites a set token.
     *
     * @param mysqli $conn Registry / clone-source connection with tbl_branches
     * @return string
     */
    function auragold_ensure_shop_access_token($conn, int $shopId): string
    {
        if (!$conn instanceof mysqli || $shopId <= 0) {
            return '';
        }
        auragold_ensure_tbl_branches_access_token_column($conn);

        $sid = (int) $shopId;
        $rs  = @mysqli_query(
            $conn,
            'SELECT access_token FROM tbl_branches WHERE id = ' . $sid . ' AND IFNULL(main_branch_id, 0) = 0 LIMIT 1'
        );
        if (!$rs) {
            return '';
        }
        $row = mysqli_fetch_assoc($rs);
        mysqli_free_result($rs);
        if (!is_array($row)) {
            return '';
        }

        $existing = trim((string) ($row['access_token'] ?? ''));
        if ($existing !== '') {
            return $existing;
        }

        try {
            $token = bin2hex(random_bytes(32));
        } catch (Throwable $e) {
            $token = hash('sha256', uniqid('shop' . $sid, true) . microtime(true));
        }

        $esc = mysqli_real_escape_string($conn, $token);
        @mysqli_query(
            $conn,
            "UPDATE tbl_branches SET access_token = '" . $esc . "'
             WHERE id = " . $sid . "
               AND IFNULL(main_branch_id, 0) = 0
               AND (access_token IS NULL OR TRIM(access_token) = '')
             LIMIT 1"
        );

        $rs2 = @mysqli_query($conn, 'SELECT access_token FROM tbl_branches WHERE id = ' . $sid . ' LIMIT 1');
        if ($rs2) {
            $row2 = mysqli_fetch_assoc($rs2);
            mysqli_free_result($rs2);
            return trim((string) ($row2['access_token'] ?? ''));
        }

        return $token;
    }
}

if (!function_exists('auragold_bootstrap_session_shop_access_token')) {
    /**
     * Ensure + return the login shop (main branch) access_token for the current session.
     * Used by My Profile, CRM menu, and matches /api/shops.php tokens.
     */
    function auragold_bootstrap_session_shop_access_token(): string
    {
        static $done = false;
        static $cached = '';
        if ($done) {
            return $cached;
        }
        $done = true;

        if (PHP_SAPI === 'cli') {
            return '';
        }
        if (!function_exists('session_status') || session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }

        $cachedSession = trim((string) ($_SESSION['shop_access_token'] ?? ''));
        if ($cachedSession !== '') {
            $cached = $cachedSession;
            return $cached;
        }

        $shopId = 0;
        if (function_exists('auragold_my_profile_target_branch_id')) {
            $shopId = (int) auragold_my_profile_target_branch_id();
        }
        if ($shopId <= 0) {
            $shopId = (int) ($_SESSION['working_branch_id'] ?? $_SESSION['branch_id'] ?? 0);
        }
        if ($shopId <= 0) {
            return '';
        }

        $meta = null;
        if (function_exists('auragold_api_connect_clone_source')) {
            $meta = auragold_api_connect_clone_source();
        }
        if (!$meta instanceof mysqli && !empty($GLOBALS['auragold_registry_mysqli'])
            && $GLOBALS['auragold_registry_mysqli'] instanceof mysqli) {
            $meta = $GLOBALS['auragold_registry_mysqli'];
        }
        global $conn_master;
        if (!$meta instanceof mysqli && isset($conn_master) && $conn_master instanceof mysqli) {
            $meta = $conn_master;
        }
        if (!$meta instanceof mysqli) {
            return '';
        }

        $mainId = $shopId;
        try {
            $rs = @mysqli_query(
                $meta,
                'SELECT id, main_branch_id FROM tbl_branches WHERE id = ' . (int) $shopId . ' LIMIT 1'
            );
            if ($rs && ($row = mysqli_fetch_assoc($rs))) {
                $mb = (int) ($row['main_branch_id'] ?? 0);
                if ($mb > 0) {
                    $mainId = $mb;
                }
                mysqli_free_result($rs);
            } elseif ($rs) {
                mysqli_free_result($rs);
            }
        } catch (Throwable $e) {
            // keep $mainId
        }

        $token = auragold_ensure_shop_access_token($meta, $mainId);
        if (function_exists('auragold_api_close_meta_link')) {
            auragold_api_close_meta_link($meta);
        }

        if ($token !== '') {
            $_SESSION['shop_access_token'] = $token;
            if (!empty($_SESSION['Admin']) && is_array($_SESSION['Admin'])) {
                $_SESSION['Admin']['shop_access_token'] = $token;
            }
            $cached = $token;
        }

        return $cached;
    }
}
