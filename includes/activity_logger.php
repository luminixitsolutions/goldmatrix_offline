<?php
/**
 * User activity log: sessions (login/logout/IP) + event trail (page views & CRUD).
 */

if (!function_exists('auragold_activity_operational_conn')) {
    /**
     * Prefer branch working_db connection for writes (login may set working_db after config boot).
     *
     * @param mysqli|null $fallback
     * @return mysqli|null
     */
    function auragold_activity_operational_conn($fallback = null)
    {
        if (function_exists('session_status')
            && session_status() === PHP_SESSION_ACTIVE
            && !empty($_SESSION['working_db'])
            && is_array($_SESSION['working_db'])
        ) {
            $wdb = $_SESSION['working_db'];
            $dbname = trim((string) ($wdb['database'] ?? $wdb['db_name'] ?? ''));
            if ($dbname !== '') {
                $dbuser = trim((string) ($wdb['user'] ?? $wdb['db_user'] ?? $wdb['db_users'] ?? ''));
                $dbpass = (string) ($wdb['password'] ?? $wdb['db_pass'] ?? $wdb['db_password'] ?? '');
                if ($dbuser === '') {
                    $dbuser = defined('DB_USER') ? (string) DB_USER : '';
                    $dbpass = defined('DB_PASS') ? (string) DB_PASS : '';
                }
                $host = defined('DB_HOST') ? (string) DB_HOST : 'localhost';
                $c = null;
                if (function_exists('auragold_mysqli_connect_operational')) {
                    $c = auragold_mysqli_connect_operational($host, $dbuser, $dbpass, $dbname);
                } else {
                    try {
                        $c = @mysqli_connect($host, $dbuser, $dbpass, $dbname);
                    } catch (Throwable $e) {
                        $c = null;
                    }
                }
                if ($c) {
                    mysqli_set_charset($c, 'utf8mb4');
                    return $c;
                }
            }
        }
        if ($fallback instanceof mysqli) {
            return $fallback;
        }
        if (isset($GLOBALS['conn']) && $GLOBALS['conn'] instanceof mysqli) {
            return $GLOBALS['conn'];
        }
        return null;
    }
}

if (!function_exists('auragold_ensure_user_activity_tables')) {
    /**
     * @param mysqli|null $conn
     */
    function auragold_ensure_user_activity_tables($conn): bool
    {
        if (!$conn || !($conn instanceof mysqli)) {
            return false;
        }
        static $done = [];
        $key = (string) @mysqli_get_server_info($conn) . '|' . (string) (@mysqli_query($conn, 'SELECT DATABASE()') ? '' : '');
        $dbRes = @mysqli_query($conn, 'SELECT DATABASE() AS d');
        $dbName = '';
        if ($dbRes && ($row = mysqli_fetch_assoc($dbRes))) {
            $dbName = (string) ($row['d'] ?? '');
            mysqli_free_result($dbRes);
        }
        $cacheKey = $dbName !== '' ? $dbName : spl_object_hash($conn);
        if (!empty($done[$cacheKey])) {
            return true;
        }

        $sqlSessions = "CREATE TABLE IF NOT EXISTS `tbl_user_activity_sessions` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `php_session_id` varchar(128) NOT NULL DEFAULT '',
          `user_id` int unsigned NOT NULL DEFAULT 0,
          `username` varchar(120) NOT NULL DEFAULT '',
          `user_name` varchar(191) NOT NULL DEFAULT '',
          `branch_id` int unsigned NOT NULL DEFAULT 0,
          `ip_address` varchar(64) NOT NULL DEFAULT '',
          `user_agent` varchar(500) NOT NULL DEFAULT '',
          `login_at` datetime NOT NULL,
          `logout_at` datetime DEFAULT NULL,
          `logout_reason` varchar(32) DEFAULT NULL,
          `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_uas_user_login` (`user_id`, `login_at`),
          KEY `idx_uas_php_sess` (`php_session_id`),
          KEY `idx_uas_login` (`login_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $sqlLogs = "CREATE TABLE IF NOT EXISTS `tbl_user_activity_logs` (
          `id` bigint unsigned NOT NULL AUTO_INCREMENT,
          `session_id` bigint unsigned DEFAULT NULL,
          `user_id` int unsigned NOT NULL DEFAULT 0,
          `username` varchar(120) NOT NULL DEFAULT '',
          `user_name` varchar(191) NOT NULL DEFAULT '',
          `branch_id` int unsigned NOT NULL DEFAULT 0,
          `ip_address` varchar(64) NOT NULL DEFAULT '',
          `action` varchar(32) NOT NULL DEFAULT 'other',
          `page` varchar(191) NOT NULL DEFAULT '',
          `entity_type` varchar(100) DEFAULT NULL,
          `entity_id` varchar(64) DEFAULT NULL,
          `description` varchar(500) NOT NULL DEFAULT '',
          `request_uri` varchar(500) NOT NULL DEFAULT '',
          `request_method` varchar(10) NOT NULL DEFAULT '',
          `meta_json` text,
          `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_ual_user_created` (`user_id`, `created_at`),
          KEY `idx_ual_session` (`session_id`),
          KEY `idx_ual_action` (`action`, `created_at`),
          KEY `idx_ual_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!@mysqli_query($conn, $sqlSessions) || !@mysqli_query($conn, $sqlLogs)) {
            return false;
        }
        $done[$cacheKey] = true;
        return true;
    }
}

if (!function_exists('auragold_activity_client_ip')) {
    function auragold_activity_client_ip(): string
    {
        $candidates = [
            (string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''),
            (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''),
            (string) ($_SERVER['HTTP_X_REAL_IP'] ?? ''),
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        ];
        foreach ($candidates as $raw) {
            $raw = trim($raw);
            if ($raw === '') {
                continue;
            }
            if (strpos($raw, ',') !== false) {
                $raw = trim(explode(',', $raw)[0]);
            }
            if (filter_var($raw, FILTER_VALIDATE_IP)) {
                return substr($raw, 0, 64);
            }
        }
        return '';
    }
}

if (!function_exists('auragold_activity_current_user')) {
    /**
     * @return array{user_id:int,username:string,user_name:string,branch_id:int}
     */
    function auragold_activity_current_user(): array
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $username = '';
        $userName = trim((string) ($_SESSION['name'] ?? ''));
        if (!empty($_SESSION['Admin']) && is_array($_SESSION['Admin'])) {
            $admin = $_SESSION['Admin'];
            if ($userId <= 0) {
                $userId = (int) ($admin['id'] ?? $admin['Id'] ?? 0);
            }
            $username = trim((string) ($admin['Username'] ?? $admin['username'] ?? ''));
            if ($userName === '') {
                $fn = trim((string) ($admin['Fname'] ?? $admin['fname'] ?? ''));
                $ln = trim((string) ($admin['Lname'] ?? $admin['lname'] ?? ''));
                $userName = trim($fn . ' ' . $ln);
            }
        }
        $branchId = (int) ($_SESSION['working_branch_id'] ?? $_SESSION['branch_id'] ?? $_SESSION['auragold_login_branch_id'] ?? 0);
        return [
            'user_id' => $userId,
            'username' => $username,
            'user_name' => $userName !== '' ? $userName : $username,
            'branch_id' => $branchId,
        ];
    }
}

if (!function_exists('auragold_activity_insert_log')) {
    /**
     * @param mysqli|null $conn
     * @param array<string,mixed> $opts
     */
    function auragold_activity_insert_log($conn, array $opts): bool
    {
        try {
            if (!$conn || !($conn instanceof mysqli)) {
                return false;
            }
            if (!auragold_ensure_user_activity_tables($conn)) {
                return false;
            }

            $user = auragold_activity_current_user();
            $userId = isset($opts['user_id']) ? (int) $opts['user_id'] : $user['user_id'];
            $username = isset($opts['username']) ? (string) $opts['username'] : $user['username'];
            $userName = isset($opts['user_name']) ? (string) $opts['user_name'] : $user['user_name'];
            $branchId = isset($opts['branch_id']) ? (int) $opts['branch_id'] : $user['branch_id'];
            $ip = isset($opts['ip_address']) ? (string) $opts['ip_address'] : auragold_activity_client_ip();
            $action = strtolower(trim((string) ($opts['action'] ?? 'other')));
            if ($action === '') {
                $action = 'other';
            }
            $page = substr(trim((string) ($opts['page'] ?? '')), 0, 191);
            $entityType = isset($opts['entity_type']) ? substr(trim((string) $opts['entity_type']), 0, 100) : null;
            $entityId = isset($opts['entity_id']) ? substr(trim((string) $opts['entity_id']), 0, 64) : null;
            $description = substr(trim((string) ($opts['description'] ?? '')), 0, 500);
            $requestUri = substr(trim((string) ($opts['request_uri'] ?? ($_SERVER['REQUEST_URI'] ?? ''))), 0, 500);
            $requestMethod = substr(strtoupper(trim((string) ($opts['request_method'] ?? ($_SERVER['REQUEST_METHOD'] ?? '')))), 0, 10);
            $sessionPk = isset($opts['session_id']) ? (int) $opts['session_id'] : (int) ($_SESSION['auragold_activity_session_id'] ?? 0);
            if ($sessionPk <= 0) {
                $sessionPk = null;
            }
            $metaJson = null;
            if (isset($opts['meta']) && is_array($opts['meta'])) {
                $metaJson = json_encode($opts['meta'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if ($metaJson !== false && strlen($metaJson) > 65000) {
                    $metaJson = substr($metaJson, 0, 65000);
                }
            }

            $entityTypeBind = ($entityType !== null && $entityType !== '') ? $entityType : '';
            $entityIdBind = ($entityId !== null && $entityId !== '') ? $entityId : '';
            $metaBind = ($metaJson !== null && $metaJson !== '') ? $metaJson : '';

            $prev = error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR);
            try {
                if ($sessionPk !== null && $sessionPk > 0) {
                    $stmt = mysqli_prepare(
                        $conn,
                        'INSERT INTO tbl_user_activity_logs
                        (session_id, user_id, username, user_name, branch_id, ip_address, action, page, entity_type, entity_id, description, request_uri, request_method, meta_json, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                    );
                    if (!$stmt) {
                        return false;
                    }
                    mysqli_stmt_bind_param(
                        $stmt,
                        'iississsssssss',
                        $sessionPk,
                        $userId,
                        $username,
                        $userName,
                        $branchId,
                        $ip,
                        $action,
                        $page,
                        $entityTypeBind,
                        $entityIdBind,
                        $description,
                        $requestUri,
                        $requestMethod,
                        $metaBind
                    );
                } else {
                    $stmt = mysqli_prepare(
                        $conn,
                        'INSERT INTO tbl_user_activity_logs
                        (session_id, user_id, username, user_name, branch_id, ip_address, action, page, entity_type, entity_id, description, request_uri, request_method, meta_json, created_at)
                        VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                    );
                    if (!$stmt) {
                        return false;
                    }
                    mysqli_stmt_bind_param(
                        $stmt,
                        'ississsssssss',
                        $userId,
                        $username,
                        $userName,
                        $branchId,
                        $ip,
                        $action,
                        $page,
                        $entityTypeBind,
                        $entityIdBind,
                        $description,
                        $requestUri,
                        $requestMethod,
                        $metaBind
                    );
                }
                $ok = mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                return (bool) $ok;
            } finally {
                error_reporting($prev);
            }
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('auragold_activity_log_login')) {
    /**
     * Start a tracked login session and write a login event.
     *
     * @param mysqli|null $conn
     */
    function auragold_activity_log_login($conn): void
    {
        try {
            if (!$conn || !($conn instanceof mysqli)) {
                return;
            }
            if (!auragold_ensure_user_activity_tables($conn)) {
                return;
            }
            $user = auragold_activity_current_user();
            if ($user['user_id'] <= 0 && $user['username'] === '') {
                return;
            }
            $phpSid = session_id() ?: '';
            $ip = auragold_activity_client_ip();
            $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);

            $stmt = mysqli_prepare(
                $conn,
                'INSERT INTO tbl_user_activity_sessions
            (php_session_id, user_id, username, user_name, branch_id, ip_address, user_agent, login_at, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
            );
            if (!$stmt) {
                return;
            }
            mysqli_stmt_bind_param(
                $stmt,
                'sississ',
                $phpSid,
                $user['user_id'],
                $user['username'],
                $user['user_name'],
                $user['branch_id'],
                $ip,
                $ua
            );
            if (!mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                return;
            }
            $sessionPk = (int) mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);
            if ($sessionPk > 0) {
                $_SESSION['auragold_activity_session_id'] = $sessionPk;
            }
            auragold_activity_insert_log($conn, [
                'session_id' => $sessionPk,
                'action' => 'login',
                'page' => 'login',
                'description' => 'User logged in',
                'ip_address' => $ip,
            ]);
        } catch (Throwable $e) {
            if (function_exists('error_log')) {
                @error_log('auragold_activity_log_login: ' . $e->getMessage());
            }
        }
    }
}

if (!function_exists('auragold_activity_log_logout')) {
    /**
     * @param mysqli|null $conn
     */
    function auragold_activity_log_logout($conn, string $reason = 'logout'): void
    {
        if (!$conn || !($conn instanceof mysqli)) {
            return;
        }
        if (!auragold_ensure_user_activity_tables($conn)) {
            return;
        }
        $reason = preg_replace('/[^a-z0-9_\-]/i', '', $reason) ?: 'logout';
        $sessionPk = (int) ($_SESSION['auragold_activity_session_id'] ?? 0);
        $phpSid = session_id() ?: '';
        $user = auragold_activity_current_user();
        $ip = auragold_activity_client_ip();

        if ($sessionPk > 0) {
            $escReason = mysqli_real_escape_string($conn, $reason);
            @mysqli_query(
                $conn,
                'UPDATE tbl_user_activity_sessions SET logout_at = NOW(), logout_reason = \'' . $escReason . '\'
                 WHERE id = ' . (int) $sessionPk . ' AND logout_at IS NULL'
            );
        } elseif ($phpSid !== '') {
            $escSid = mysqli_real_escape_string($conn, $phpSid);
            $escReason = mysqli_real_escape_string($conn, $reason);
            @mysqli_query(
                $conn,
                'UPDATE tbl_user_activity_sessions SET logout_at = NOW(), logout_reason = \'' . $escReason . '\'
                 WHERE php_session_id = \'' . $escSid . '\' AND logout_at IS NULL
                 ORDER BY id DESC LIMIT 1'
            );
            $r = @mysqli_query(
                $conn,
                'SELECT id FROM tbl_user_activity_sessions WHERE php_session_id = \'' . $escSid . '\' ORDER BY id DESC LIMIT 1'
            );
            if ($r && ($row = mysqli_fetch_assoc($r))) {
                $sessionPk = (int) ($row['id'] ?? 0);
            }
            if ($r) {
                mysqli_free_result($r);
            }
        }

        $desc = $reason === 'timeout' ? 'Session closed due to inactivity' : ($reason === 'forced' ? 'Forced logout' : 'User logged out');
        auragold_activity_insert_log($conn, [
            'session_id' => $sessionPk > 0 ? $sessionPk : null,
            'user_id' => $user['user_id'],
            'username' => $user['username'],
            'user_name' => $user['user_name'],
            'branch_id' => $user['branch_id'],
            'action' => 'logout',
            'page' => 'logout',
            'description' => $desc,
            'ip_address' => $ip,
            'meta' => ['reason' => $reason],
        ]);
    }
}

if (!function_exists('auragold_activity_log_event')) {
    /**
     * Public helper for explicit CRUD logging from ajax endpoints.
     *
     * @param mysqli|null         $conn
     * @param string              $action  create|update|delete|view|page_view|other
     * @param array<string,mixed> $opts
     */
    function auragold_activity_log_event($conn, string $action, array $opts = []): void
    {
        if (!$conn || !($conn instanceof mysqli)) {
            return;
        }
        $opts['action'] = strtolower(trim($action));
        auragold_activity_insert_log($conn, $opts);
    }
}

if (!function_exists('auragold_activity_infer_ajax_action')) {
    /**
     * @return array{action:string,entity_type:string,entity_id:string,description:string}|null
     */
    function auragold_activity_infer_ajax_action(string $basename, string $method): ?array
    {
        $base = strtolower($basename);
        $method = strtoupper($method);

        $skip = [
            'notifications-feed.php',
            'login_check.php',
            'currency-exchange-rate.php',
            'dashboard-gold-analytics.php',
            'get-dashboard-metal-rates.php',
            'fetch-dashboard-rates.php',
        ];
        if (in_array($base, $skip, true)) {
            return null;
        }

        $entityType = preg_replace('/\.(php)$/i', '', $basename) ?: $basename;
        $entityType = preg_replace('/^(get-|save-|delete-|list-|fetch-|mp-)/i', '', $entityType) ?: $entityType;
        $entityType = substr(str_replace(['-', '_'], ' ', $entityType), 0, 100);

        $entityId = '';
        foreach (['id', 'Id', 'ID', 'jwo_id', 'record_id', 'voucher_id', 'order_id', 'invoice_id'] as $k) {
            if (isset($_GET[$k]) && (string) $_GET[$k] !== '') {
                $entityId = substr((string) $_GET[$k], 0, 64);
                break;
            }
            if (isset($_POST[$k]) && (string) $_POST[$k] !== '') {
                $entityId = substr((string) $_POST[$k], 0, 64);
                break;
            }
        }

        if ($method === 'GET') {
            if (strpos($base, 'get-') === 0 || strpos($base, 'fetch-') === 0) {
                if ($entityId === '') {
                    return null; // list/poll without id — skip noise
                }
                return [
                    'action' => 'view',
                    'entity_type' => $entityType,
                    'entity_id' => $entityId,
                    'description' => 'Viewed ' . $entityType . ' #' . $entityId,
                ];
            }
            return null;
        }

        // POST/PUT/DELETE
        if (strpos($base, 'delete') !== false || strpos($base, 'remove') !== false) {
            return [
                'action' => 'delete',
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'description' => 'Deleted ' . $entityType . ($entityId !== '' ? ' #' . $entityId : ''),
            ];
        }
        if (strpos($base, 'save') !== false || strpos($base, 'create') !== false || strpos($base, 'add') !== false || strpos($base, 'insert') !== false || strpos($base, 'update') !== false) {
            $isUpdate = $entityId !== '' && $entityId !== '0';
            // Heuristic: presence of id often means update
            $action = (strpos($base, 'update') !== false || $isUpdate) ? 'update' : 'create';
            if (strpos($base, 'create') !== false || strpos($base, 'add') !== false || strpos($base, 'insert') !== false) {
                if (!$isUpdate) {
                    $action = 'create';
                }
            }
            $verb = $action === 'create' ? 'Added' : 'Updated';
            return [
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'description' => $verb . ' ' . $entityType . ($entityId !== '' ? ' #' . $entityId : ''),
            ];
        }

        return [
            'action' => 'other',
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => 'API call: ' . $basename,
        ];
    }
}

if (!function_exists('auragold_activity_page_title_from_script')) {
    function auragold_activity_page_title_from_script(string $script): string
    {
        $base = basename($script);
        $name = preg_replace('/\.php$/i', '', $base) ?: $base;
        $name = str_replace(['-', '_'], ' ', $name);
        return ucwords($name);
    }
}

if (!function_exists('auragold_activity_bootstrap_request_tracking')) {
    /**
     * Auto page-view + AJAX action tracking for authenticated requests.
     *
     * @param mysqli|null $conn
     */
    function auragold_activity_bootstrap_request_tracking($conn): void
    {
        if (PHP_SAPI === 'cli' || !$conn || !($conn instanceof mysqli)) {
            return;
        }
        if (!function_exists('auragold_is_logged_in_session') || !auragold_is_logged_in_session()) {
            return;
        }

        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
        $base = strtolower(basename($script));
        if ($base === '') {
            return;
        }

        $skipPages = [
            'index.php',
            'login_submit.php',
            'logout.php',
            'config.php',
            'header-script.php',
            'footer-script.php',
            'sidebar.php',
        ];
        if (in_array($base, $skipPages, true)) {
            return;
        }

        $isAjax = function_exists('auragold_session_is_request_ajax') && auragold_session_is_request_ajax();
        $inAjaxDir = (strpos(str_replace('\\', '/', $script), '/ajax/') !== false)
            || (strpos(str_replace('\\', '/', $script), '/api/') !== false);

        if ($isAjax || $inAjaxDir) {
            $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
            $inferred = auragold_activity_infer_ajax_action($base, $method);
            if ($inferred === null) {
                return;
            }
            // Log immediately (before JSON output). Never use shutdown — warnings after JSON break AJAX clients.
            try {
                auragold_activity_insert_log($conn, [
                    'action' => $inferred['action'],
                    'page' => $base,
                    'entity_type' => $inferred['entity_type'],
                    'entity_id' => $inferred['entity_id'],
                    'description' => $inferred['description'],
                ]);
            } catch (Throwable $e) {
                // never break the main request
            }
            return;
        }

        // HTML page view — debounce same page within 20s
        $pageKey = $base;
        $now = time();
        $lastMap = isset($_SESSION['auragold_activity_page_hits']) && is_array($_SESSION['auragold_activity_page_hits'])
            ? $_SESSION['auragold_activity_page_hits']
            : [];
        $last = (int) ($lastMap[$pageKey] ?? 0);
        if ($last > 0 && ($now - $last) < 20) {
            return;
        }
        $lastMap[$pageKey] = $now;
        // Keep map small
        if (count($lastMap) > 80) {
            asort($lastMap);
            $lastMap = array_slice($lastMap, -40, null, true);
        }
        $_SESSION['auragold_activity_page_hits'] = $lastMap;

        $title = auragold_activity_page_title_from_script($base);
        try {
            auragold_activity_insert_log($conn, [
                'action' => 'page_view',
                'page' => $base,
                'description' => 'Visited ' . $title,
            ]);
        } catch (Throwable $e) {
            // ignore
        }
    }
}
