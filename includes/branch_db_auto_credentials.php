<?php
/**
 * Auto-generate auragold_{slug} database name / MySQL user label and random password for new branches.
 * Connections may fall back to registry DB_USER/DB_PASS if the dedicated MySQL user does not exist yet.
 */
require_once __DIR__ . '/branch_create_db_after_save.php';

if (!function_exists('auragold_branch_prod_alnum_password')) {
    /**
     * Random MySQL password for production branches (A–Z, a–z, 0–9), cPanel-friendly.
     */
    function auragold_branch_prod_alnum_password(int $length = 20): string {
        if ($length < 12) {
            $length = 12;
        }
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $l     = strlen($chars);
        $out   = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $chars[random_int(0, $l - 1)];
        }
        return $out;
    }
}

if (!function_exists('auragold_branch_slug_from_display_name')) {
    function auragold_branch_slug_from_display_name(string $name): string {
        $s = strtolower(trim($name));
        $s = preg_replace('/[^a-z0-9]+/', '_', $s);
        $s = trim($s, '_');
        if ($s === '') {
            $s = 'branch';
        }
        if (strlen($s) > 50) {
            $s = substr($s, 0, 50);
        }
        $s = trim($s, '_');
        return $s !== '' ? $s : 'branch';
    }
}

if (!function_exists('auragold_allocate_unique_branch_db_credentials')) {
    /**
     * Unique db_name (≤64 chars). Stored credentials depend on AURAGOLD_PROJECT / AURAGOLD_DB_PREFIX (config.php):
     * - local: db_name = prefix+slug, db_users = root, db_password = '' (app connects with main DB account).
     * - prod:  db_name = prefix+slug, db_users = same pattern truncated to 32 chars, random A–Z/a–z/0–9 password.
     *
     * @return array{db_name:string,db_users:string,db_password:string}
     */
    function auragold_allocate_unique_branch_db_credentials(mysqli $conn_master, string $branch_name): array {
        $prefix = defined('AURAGOLD_DB_PREFIX') ? (string) AURAGOLD_DB_PREFIX : 'auragold_';
        if ($prefix !== '' && substr($prefix, -1) !== '_') {
            $prefix .= '_';
        }
        $isProd = defined('AURAGOLD_PROJECT') && AURAGOLD_PROJECT === 'prod';

        $slug = auragold_branch_slug_from_display_name($branch_name);
        $base = $prefix . $slug;
        if (strlen($base) > 64) {
            $base = substr($base, 0, 64);
        }

        $candidate = $base;
        for ($n = 0; $n < 500; $n++) {
            if ($n > 0) {
                $suffix = '_' . $n;
                $candidate = substr($base, 0, max(1, 64 - strlen($suffix))) . $suffix;
            }
            if (!auragold_branch_mysql_identifier_ok($candidate)) {
                $candidate = rtrim($prefix, '_') . '_b_' . substr(bin2hex(random_bytes(8)), 0, 16);
                $candidate = substr($candidate, 0, 64);
            }
            $esc = mysqli_real_escape_string($conn_master, $candidate);
            $dup = getRecordMaster("SELECT id FROM tbl_branches WHERE db_name = '$esc' LIMIT 1");
            if (!$dup) {
                $dbName = $candidate;
                if ($isProd) {
                    $userRaw = $dbName;
                    if (strlen($userRaw) > 32) {
                        $userRaw = substr($userRaw, 0, 32);
                    }
                    if (!auragold_branch_mysql_identifier_ok($userRaw)) {
                        $userRaw = 'ag_' . substr(bin2hex(random_bytes(10)), 0, 28);
                    }
                    $dbPass = function_exists('auragold_branch_prod_alnum_password')
                        ? auragold_branch_prod_alnum_password(20) : bin2hex(random_bytes(10));
                } else {
                    $userRaw = 'root';
                    $dbPass  = '';
                }

                return [
                    'db_name'     => $dbName,
                    'db_users'    => $userRaw,
                    'db_password' => $dbPass,
                ];
            }
        }

        $dbName = rtrim($prefix, '_') . '_' . bin2hex(random_bytes(10));
        $dbName = substr($dbName, 0, 64);

        if ($isProd) {
            $userRaw = substr($dbName, 0, 32);
            if (!auragold_branch_mysql_identifier_ok($userRaw)) {
                $userRaw = 'ag_' . substr(bin2hex(random_bytes(10)), 0, 28);
            }
            $dbPass = function_exists('auragold_branch_prod_alnum_password')
                ? auragold_branch_prod_alnum_password(20) : bin2hex(random_bytes(10));
        } else {
            $userRaw = 'root';
            $dbPass  = '';
        }

        return [
            'db_name'     => $dbName,
            'db_users'    => $userRaw,
            'db_password' => $dbPass,
        ];
    }
}

if (!function_exists('auragold_mysqli_drop_database_safe')) {
    /**
     * DROP DATABASE without throwing (PHP 8.1+ mysqli_sql_exception).
     */
    function auragold_mysqli_drop_database_safe(mysqli $link, string $dbName): array {
        if (!auragold_branch_mysql_identifier_ok($dbName)) {
            return ['ok' => false, 'message' => 'Invalid database name.'];
        }
        $q = '`' . str_replace('`', '``', $dbName) . '`';
        try {
            if (@mysqli_query($link, 'DROP DATABASE IF EXISTS ' . $q)) {
                return ['ok' => true, 'message' => 'Database `' . $dbName . '` dropped.'];
            }
            $err = mysqli_error($link);
            if ($err === '' && mysqli_errno($link) === 0) {
                return ['ok' => true, 'message' => 'Database `' . $dbName . '` dropped or absent.'];
            }
            return ['ok' => false, 'message' => $err !== '' ? $err : 'DROP DATABASE failed.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}

if (!function_exists('auragold_drop_branch_database_if_configured')) {
    /**
     * Remove a dedicated branch schema (not the registry DB).
     * Production: cPanel UAPI first (app MySQL user cannot DROP other branch DBs).
     * Local: mysqli DROP via registry/bootstrap connection, then $conn_master.
     *
     * @return array{ok:bool,skipped?:bool,message:string,method?:string,user_drop?:array}
     */
    function auragold_drop_branch_database_if_configured(
        mysqli $conn_master,
        ?string $dbName,
        ?string $branchDbUser = null,
        ?string $branchDbPass = null
    ): array {
        $dbName = trim((string) $dbName);
        if ($dbName === '' || !auragold_branch_mysql_identifier_ok($dbName)) {
            return ['ok' => true, 'skipped' => true, 'message' => ''];
        }
        if (defined('DB_NAME') && strcasecmp($dbName, (string) DB_NAME) === 0) {
            error_log('AuraGold: refuse DROP DATABASE same as registry: ' . $dbName);
            return ['ok' => true, 'skipped' => true, 'message' => ''];
        }
        if (defined('AURAGOLD_REGISTRY_DB') && strcasecmp($dbName, (string) AURAGOLD_REGISTRY_DB) === 0) {
            return ['ok' => true, 'skipped' => true, 'message' => ''];
        }

        $isProd = defined('AURAGOLD_PROJECT') && AURAGOLD_PROJECT === 'prod';

        if ($isProd) {
            if (!function_exists('auragold_cpanel_uapi_mysql_delete_database')) {
                require_once __DIR__ . '/cpanel_mysql_create_database.php';
            }
            $cpDrop = auragold_cpanel_uapi_mysql_delete_database($dbName);
            if (!empty($cpDrop['ok'])) {
                $userDrop = null;
                $branchDbUser = trim((string) $branchDbUser);
                if ($branchDbUser !== '' && function_exists('auragold_cpanel_uapi_mysql_delete_user')) {
                    $userDrop = auragold_cpanel_uapi_mysql_delete_user($branchDbUser);
                    if (empty($userDrop['ok']) && empty($userDrop['skipped'])) {
                        error_log('AuraGold: branch MySQL user cleanup failed: ' . ($userDrop['message'] ?? ''));
                    }
                }
                error_log('AuraGold: dropped branch database `' . $dbName . '` via cPanel UAPI');
                $out = [
                    'ok'      => true,
                    'message' => $cpDrop['message'] ?? ('Database `' . $dbName . '` deleted via cPanel.'),
                    'method'  => 'cpanel_uapi',
                ];
                if (is_array($userDrop)) {
                    $out['user_drop'] = $userDrop;
                }
                return $out;
            }
            if (empty($cpDrop['skipped'])) {
                error_log('AuraGold: cPanel delete_database failed for `' . $dbName . '`: ' . ($cpDrop['message'] ?? ''));
                return [
                    'ok'      => false,
                    'message' => $cpDrop['message'] ?? 'cPanel could not delete the branch database.',
                    'method'  => 'cpanel_uapi',
                ];
            }
        }

        $attempts = [];
        if (function_exists('auragold_registry_mysqli')) {
            $reg = auragold_registry_mysqli();
            if ($reg instanceof mysqli) {
                $attempts[] = ['link' => $reg, 'label' => 'registry'];
            }
        }
        $attempts[] = ['link' => $conn_master, 'label' => 'master'];

        $lastErr = '';
        foreach ($attempts as $item) {
            $link = $item['link'] ?? null;
            if (!$link instanceof mysqli) {
                continue;
            }
            $drop = auragold_mysqli_drop_database_safe($link, $dbName);
            if (!empty($drop['ok'])) {
                error_log('AuraGold: dropped branch database `' . $dbName . '` via ' . ($item['label'] ?? 'mysqli'));
                return [
                    'ok'      => true,
                    'message' => $drop['message'] ?? ('Database `' . $dbName . '` dropped.'),
                    'method'  => (string) ($item['label'] ?? 'mysqli'),
                ];
            }
            $lastErr = trim((string) ($drop['message'] ?? ''));
            if ($lastErr !== '') {
                error_log('AuraGold: DROP DATABASE via ' . ($item['label'] ?? 'mysqli') . ' failed for `' . $dbName . '`: ' . $lastErr);
            }
        }

        if ($isProd) {
            return [
                'ok'      => false,
                'message' => 'Could not delete branch database `' . $dbName
                    . '`. The application MySQL user does not have DROP privilege; configure cPanel API in config.php.',
            ];
        }

        return ['ok' => false, 'message' => $lastErr !== '' ? $lastErr : 'DROP DATABASE failed.'];
    }
}

if (!function_exists('auragold_branch_uses_dedicated_database')) {
    /**
     * True when branch has its own MySQL schema (prod); DROP DATABASE removes app data.
     */
    function auragold_branch_uses_dedicated_database(?string $dbName): bool {
        $dbName = trim((string) $dbName);
        if ($dbName === '' || !function_exists('auragold_branch_mysql_identifier_ok') || !auragold_branch_mysql_identifier_ok($dbName)) {
            return false;
        }
        if (defined('AURAGOLD_REGISTRY_DB') && strcasecmp($dbName, (string) AURAGOLD_REGISTRY_DB) === 0) {
            return false;
        }
        if (defined('DB_NAME') && strcasecmp($dbName, (string) DB_NAME) === 0) {
            return false;
        }
        return true;
    }
}
