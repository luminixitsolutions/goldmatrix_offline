<?php
/**
 * Shared DB helpers for public shop APIs (shops / customers).
 *
 * Branch metadata (tbl_branches + access_token) uses the canonical registry DB when possible.
 */

if (!function_exists('auragold_api_clone_source_credentials')) {
    /**
     * @return array{host:string,db:string,user:string,pass:string}
     */
    function auragold_api_clone_source_credentials(): array
    {
        $host = defined('DB_HOST') ? (string) DB_HOST : 'localhost';

        $db = '';
        if (defined('AURAGOLD_SCHEMA_CLONE_SOURCE_DB')) {
            $db = trim((string) AURAGOLD_SCHEMA_CLONE_SOURCE_DB);
        } elseif (isset($GLOBALS['auragold_schema_clone_source_db'])) {
            $db = trim((string) $GLOBALS['auragold_schema_clone_source_db']);
        }

        $user = '';
        if (defined('AURAGOLD_CLONE_SOURCE_USER')) {
            $user = trim((string) AURAGOLD_CLONE_SOURCE_USER);
        } elseif (isset($GLOBALS['auragold_clone_source_mysql_user'])) {
            $user = trim((string) $GLOBALS['auragold_clone_source_mysql_user']);
        }

        $pass = '';
        if (defined('AURAGOLD_CLONE_SOURCE_PASS')) {
            $pass = (string) AURAGOLD_CLONE_SOURCE_PASS;
        } elseif (isset($GLOBALS['auragold_clone_source_mysql_pass'])) {
            $pass = (string) $GLOBALS['auragold_clone_source_mysql_pass'];
        }

        if ($db === '') {
            if (function_exists('auragold_branch_schema_clone_source_db')) {
                $db = trim((string) auragold_branch_schema_clone_source_db());
            }
            if ($db === '' && defined('AURAGOLD_REGISTRY_DB')) {
                $db = trim((string) AURAGOLD_REGISTRY_DB);
            }
            if ($db === '' && defined('DB_NAME')) {
                $db = trim((string) DB_NAME);
            }
        }
        if ($user === '') {
            $user = defined('DB_USER') ? (string) DB_USER : 'root';
            $pass = defined('DB_PASS') ? (string) DB_PASS : '';
        }

        return [
            'host' => $host,
            'db'   => $db,
            'user' => $user,
            'pass' => $pass,
        ];
    }
}

if (!function_exists('auragold_api_registry_credentials')) {
    /**
     * @return array{host:string,db:string,user:string,pass:string}
     */
    function auragold_api_registry_credentials(): array
    {
        $host = getenv('DB_HOST') ?: (defined('DB_HOST') ? (string) DB_HOST : 'localhost');
        $db   = defined('AURAGOLD_REGISTRY_DB') ? trim((string) AURAGOLD_REGISTRY_DB) : 'auragold';
        $user = getenv('AURAGOLD_BOOTSTRAP_USER');
        if ($user === false || trim((string) $user) === '') {
            $user = 'root';
        } else {
            $user = trim((string) $user);
        }
        $pass = getenv('AURAGOLD_BOOTSTRAP_PASS');
        if ($pass === false) {
            $pass = '';
        }

        return [
            'host' => (string) $host,
            'db'   => $db,
            'user' => $user,
            'pass' => (string) $pass,
        ];
    }
}

if (!function_exists('auragold_api_mysqli_connect_creds')) {
    /**
     * @param array{host:string,db:string,user:string,pass:string} $c
     */
    function auragold_api_mysqli_connect_creds(array $c): ?mysqli
    {
        if (trim((string) ($c['db'] ?? '')) === '' || trim((string) ($c['user'] ?? '')) === '') {
            return null;
        }
        try {
            $link = @mysqli_connect(
                (string) $c['host'],
                (string) $c['user'],
                (string) $c['pass'],
                (string) $c['db']
            );
        } catch (Throwable $e) {
            return null;
        }
        if (!$link) {
            return null;
        }
        @mysqli_set_charset($link, 'utf8mb4');
        return $link;
    }
}

if (!function_exists('auragold_api_mysqli_query')) {
    /**
     * mysqli_query that never throws (PHP 8+ mysqli exceptions → null).
     *
     * @return mysqli_result|bool|null
     */
    function auragold_api_mysqli_query($link, string $sql)
    {
        if (!$link instanceof mysqli) {
            return null;
        }
        try {
            return @mysqli_query($link, $sql);
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('auragold_api_connect_clone_source')) {
    /**
     * Connection for shop list / shop access_token (canonical tbl_branches).
     */
    function auragold_api_connect_clone_source(): ?mysqli
    {
        $clone = auragold_api_clone_source_credentials();
        $cloneDb = trim((string) ($clone['db'] ?? ''));
        $explicitClone = defined('AURAGOLD_SCHEMA_CLONE_SOURCE_DB')
            && trim((string) AURAGOLD_SCHEMA_CLONE_SOURCE_DB) !== '';
        if ($explicitClone && $cloneDb !== '') {
            $link = auragold_api_mysqli_connect_creds($clone);
            if ($link) {
                return $link;
            }
        }

        $link = auragold_api_mysqli_connect_creds(auragold_api_registry_credentials());
        if ($link) {
            return $link;
        }

        if (!empty($GLOBALS['auragold_registry_mysqli']) && $GLOBALS['auragold_registry_mysqli'] instanceof mysqli) {
            return $GLOBALS['auragold_registry_mysqli'];
        }

        return auragold_api_mysqli_connect_creds($clone);
    }
}

if (!function_exists('auragold_api_is_shared_registry_link')) {
    function auragold_api_is_shared_registry_link($link): bool
    {
        return $link instanceof mysqli
            && !empty($GLOBALS['auragold_registry_mysqli'])
            && $GLOBALS['auragold_registry_mysqli'] === $link;
    }
}

if (!function_exists('auragold_api_close_meta_link')) {
    function auragold_api_close_meta_link($link): void
    {
        if (!$link instanceof mysqli || auragold_api_is_shared_registry_link($link)) {
            return;
        }
        try {
            @mysqli_close($link);
        } catch (Throwable $e) {
            // ignore
        }
    }
}

if (!function_exists('auragold_api_branches_has_access_token_column')) {
    function auragold_api_branches_has_access_token_column($link): bool
    {
        if (!$link instanceof mysqli) {
            return false;
        }
        $rs = auragold_api_mysqli_query($link, "SHOW COLUMNS FROM tbl_branches LIKE 'access_token'");
        if (!$rs) {
            return false;
        }
        $ok = mysqli_num_rows($rs) > 0;
        mysqli_free_result($rs);
        return $ok;
    }
}

if (!function_exists('auragold_api_find_branch_by_access_token')) {
    /**
     * Find main-branch row by access_token. Safe on PHP 8 mysqli exceptions.
     *
     * @return array<string,mixed>|null
     */
    function auragold_api_find_branch_by_access_token(string $accessToken): ?array
    {
        $accessToken = trim($accessToken);
        if ($accessToken === '') {
            return null;
        }

        $owned = [];
        $candidates = [];

        $push = static function ($link, bool $isOwned) use (&$candidates, &$owned): void {
            if (!$link instanceof mysqli) {
                return;
            }
            foreach ($candidates as $c) {
                if ($c === $link) {
                    return;
                }
            }
            $candidates[] = $link;
            if ($isOwned) {
                $owned[] = $link;
            }
        };

        try {
            $reg = auragold_api_connect_clone_source();
            $push($reg, $reg instanceof mysqli && !auragold_api_is_shared_registry_link($reg));

            $regOnly = auragold_api_mysqli_connect_creds(auragold_api_registry_credentials());
            $push($regOnly, true);

            global $conn_master, $conn;
            $push(isset($conn_master) && $conn_master instanceof mysqli ? $conn_master : null, false);
            $push(isset($conn) && $conn instanceof mysqli ? $conn : null, false);

            $cloneLink = auragold_api_mysqli_connect_creds(auragold_api_clone_source_credentials());
            $push($cloneLink, true);

            $branch = null;
            foreach ($candidates as $link) {
                if (function_exists('auragold_ensure_tbl_branches_access_token_column')) {
                    try {
                        auragold_ensure_tbl_branches_access_token_column($link);
                    } catch (Throwable $e) {
                        // continue — may already exist or lack ALTER privilege
                    }
                }
                if (!auragold_api_branches_has_access_token_column($link)) {
                    continue;
                }

                $esc = mysqli_real_escape_string($link, $accessToken);
                $sql = "SELECT * FROM tbl_branches
                        WHERE access_token = '{$esc}'
                          AND IFNULL(main_branch_id, 0) = 0
                        LIMIT 1";
                $rs = auragold_api_mysqli_query($link, $sql);
                if ($rs && mysqli_num_rows($rs) > 0) {
                    $branch = mysqli_fetch_assoc($rs);
                    mysqli_free_result($rs);
                    if (is_array($branch)) {
                        break;
                    }
                } elseif ($rs) {
                    mysqli_free_result($rs);
                }

                $sql2 = "SELECT * FROM tbl_branches
                         WHERE access_token = '{$esc}'
                         ORDER BY IFNULL(main_branch_id, 0) ASC, id ASC
                         LIMIT 1";
                $rs2 = auragold_api_mysqli_query($link, $sql2);
                if ($rs2 && mysqli_num_rows($rs2) > 0) {
                    $branch = mysqli_fetch_assoc($rs2);
                    mysqli_free_result($rs2);
                    if (is_array($branch)) {
                        break;
                    }
                } elseif ($rs2) {
                    mysqli_free_result($rs2);
                }
            }
        } catch (Throwable $e) {
            $branch = null;
        }

        foreach ($owned as $link) {
            auragold_api_close_meta_link($link);
        }

        return is_array($branch) ? $branch : null;
    }
}

if (!function_exists('auragold_api_connect_shop_database_from_branch_row')) {
    /**
     * @param array<string,mixed> $branch
     * @return array{ok:bool,link:?mysqli,branch:?array,message:string}
     */
    function auragold_api_connect_shop_database_from_branch_row(array $branch): array
    {
        if (function_exists('auragold_branch_row_db_credentials')) {
            $cred = auragold_branch_row_db_credentials($branch);
        } else {
            $cred = [
                'db_name' => trim((string) ($branch['db_name'] ?? '')),
                'db_user' => trim((string) ($branch['db_users'] ?? $branch['db_user'] ?? '')),
                'db_pass' => (string) ($branch['db_password'] ?? $branch['db_pass'] ?? ''),
            ];
        }

        $host = defined('DB_HOST') ? (string) DB_HOST : 'localhost';
        $dbName = trim((string) ($cred['db_name'] ?? ''));
        $dbUser = trim((string) ($cred['db_user'] ?? ''));
        $dbPass = (string) ($cred['db_pass'] ?? '');

        if ($dbUser === '') {
            $regCreds = auragold_api_registry_credentials();
            $dbUser = $regCreds['user'];
            $dbPass = $regCreds['pass'];
            if ($dbUser === '') {
                $fallback = auragold_api_clone_source_credentials();
                $dbUser = $fallback['user'];
                $dbPass = $fallback['pass'];
            }
        }

        if ($dbName === '' || $dbUser === '') {
            return [
                'ok'      => false,
                'link'    => null,
                'branch'  => $branch,
                'message' => 'Shop database credentials are missing for this shop.',
            ];
        }

        $link = null;
        try {
            $link = @mysqli_connect($host, $dbUser, $dbPass, $dbName);
        } catch (Throwable $e) {
            $link = null;
        }
        if (!$link) {
            $regCreds = auragold_api_registry_credentials();
            if ($regCreds['user'] !== '' && $regCreds['user'] !== $dbUser) {
                try {
                    $link = @mysqli_connect($host, $regCreds['user'], $regCreds['pass'], $dbName);
                } catch (Throwable $e) {
                    $link = null;
                }
            }
        }
        if (!$link) {
            return [
                'ok'      => false,
                'link'    => null,
                'branch'  => $branch,
                'message' => 'Could not connect to shop database (' . $dbName . ').',
            ];
        }
        @mysqli_set_charset($link, 'utf8mb4');

        return ['ok' => true, 'link' => $link, 'branch' => $branch, 'message' => ''];
    }
}

if (!function_exists('auragold_api_connect_shop_database')) {
    /**
     * @return array{ok:bool,link:?mysqli,branch:?array,message:string}
     */
    function auragold_api_connect_shop_database(int $shopId): array
    {
        $shopId = (int) $shopId;
        if ($shopId <= 0) {
            return ['ok' => false, 'link' => null, 'branch' => null, 'message' => 'shop_id is required.'];
        }

        $reg = auragold_api_connect_clone_source();
        if (!$reg) {
            return ['ok' => false, 'link' => null, 'branch' => null, 'message' => 'Could not connect to shop registry database.'];
        }

        $sql = 'SELECT * FROM tbl_branches WHERE id = ' . $shopId . ' AND IFNULL(main_branch_id, 0) = 0 LIMIT 1';
        $rs  = auragold_api_mysqli_query($reg, $sql);
        $branch = ($rs && mysqli_num_rows($rs) > 0) ? mysqli_fetch_assoc($rs) : null;
        if ($rs) {
            mysqli_free_result($rs);
        }
        auragold_api_close_meta_link($reg);

        if (!is_array($branch)) {
            return ['ok' => false, 'link' => null, 'branch' => null, 'message' => 'Shop not found (must be a main branch).'];
        }

        return auragold_api_connect_shop_database_from_branch_row($branch);
    }
}

if (!function_exists('auragold_api_connect_shop_database_by_access_token')) {
    /**
     * Resolve shop DB by tbl_branches.access_token (from /api/shops.php).
     *
     * User / My Profile tokens are NOT accepted by default: cloned shop DBs often share the
     * same tbl_users.access_token, which incorrectly always mapped to the first shop.
     *
     * @param bool $allowUserToken Fallback to tbl_users.access_token (ambiguous in multi-shop; off by default)
     * @return array{ok:bool,link:?mysqli,branch:?array,message:string}
     */
    function auragold_api_connect_shop_database_by_access_token(string $accessToken, bool $allowUserToken = false): array
    {
        $accessToken = trim($accessToken);
        if ($accessToken === '') {
            return ['ok' => false, 'link' => null, 'branch' => null, 'message' => 'Shop access_token is required.'];
        }

        try {
            $branch = auragold_api_find_branch_by_access_token($accessToken);
            if (is_array($branch)) {
                return auragold_api_connect_shop_database_from_branch_row($branch);
            }

            if ($allowUserToken) {
                $viaUser = auragold_api_find_shop_by_user_access_token($accessToken);
                if (is_array($viaUser) && !empty($viaUser['branch']) && is_array($viaUser['branch'])) {
                    return auragold_api_connect_shop_database_from_branch_row($viaUser['branch']);
                }
            }
        } catch (Throwable $e) {
            return [
                'ok'      => false,
                'link'    => null,
                'branch'  => null,
                'message' => 'Shop lookup failed.',
            ];
        }

        return [
            'ok'      => false,
            'link'    => null,
            'branch'  => null,
            'message' => 'Invalid shop access token. Use the access_token from /api/shops.php for that shop (not a user/My Profile token).',
        ];
    }
}

if (!function_exists('auragold_api_find_shop_by_user_access_token')) {
    /**
     * Map My Profile / tbl_users.access_token to a main shop row.
     *
     * @return array{branch:array,user:array}|null
     */
    function auragold_api_find_shop_by_user_access_token(string $accessToken): ?array
    {
        $accessToken = trim($accessToken);
        if ($accessToken === '') {
            return null;
        }

        $meta = auragold_api_connect_clone_source();
        if (!$meta instanceof mysqli) {
            $meta = auragold_api_mysqli_connect_creds(auragold_api_registry_credentials());
        }
        if (!$meta instanceof mysqli) {
            return null;
        }

        if (function_exists('auragold_ensure_tbl_users_access_token_column')) {
            try {
                auragold_ensure_tbl_users_access_token_column($meta);
            } catch (Throwable $e) {
            }
        }

        $esc = mysqli_real_escape_string($meta, $accessToken);
        $shops = [];
        $rsShops = auragold_api_mysqli_query(
            $meta,
            'SELECT * FROM tbl_branches WHERE IFNULL(main_branch_id, 0) = 0 ORDER BY id ASC'
        );
        if ($rsShops) {
            while ($row = mysqli_fetch_assoc($rsShops)) {
                $shops[] = $row;
            }
            mysqli_free_result($rsShops);
        }

        $rsUser = auragold_api_mysqli_query(
            $meta,
            "SELECT * FROM tbl_users WHERE access_token = '{$esc}' LIMIT 1"
        );
        $registryUser = ($rsUser && mysqli_num_rows($rsUser) > 0) ? mysqli_fetch_assoc($rsUser) : null;
        if ($rsUser) {
            mysqli_free_result($rsUser);
        }

        $matchedShops = [];
        foreach ($shops as $shop) {
            $connShop = auragold_api_connect_shop_database_from_branch_row($shop);
            if (empty($connShop['ok']) || !($connShop['link'] instanceof mysqli)) {
                continue;
            }
            $link = $connShop['link'];
            if (function_exists('auragold_ensure_tbl_users_access_token_column')) {
                try {
                    auragold_ensure_tbl_users_access_token_column($link);
                } catch (Throwable $e) {
                }
            }
            $rs = auragold_api_mysqli_query(
                $link,
                "SELECT id, Username, EmailId, access_token FROM tbl_users WHERE access_token = '{$esc}' LIMIT 1"
            );
            $user = ($rs && mysqli_num_rows($rs) > 0) ? mysqli_fetch_assoc($rs) : null;
            if ($rs) {
                mysqli_free_result($rs);
            }
            try {
                @mysqli_close($link);
            } catch (Throwable $e) {
            }
            if (is_array($user)) {
                $matchedShops[] = ['branch' => $shop, 'user' => $user];
            }
        }

        // Unique shop match only — cloned DBs often share the same user access_token.
        if (count($matchedShops) === 1) {
            auragold_api_close_meta_link($meta);
            return $matchedShops[0];
        }
        if (count($matchedShops) > 1) {
            auragold_api_close_meta_link($meta);
            return null;
        }

        // Registry user only — require an explicit branch mapping (never default to first shop).
        if (is_array($registryUser) && !empty($shops)) {
            $pick = null;
            $ids = trim((string) ($registryUser['user_branch_ids'] ?? ''));
            if ($ids !== '') {
                $parts = preg_split('/\s*,\s*/', $ids);
                foreach ($parts as $p) {
                    $bid = (int) $p;
                    if ($bid <= 0) {
                        continue;
                    }
                    foreach ($shops as $shop) {
                        if ((int) ($shop['id'] ?? 0) === $bid) {
                            $pick = $shop;
                            break 2;
                        }
                    }
                    $rsB = auragold_api_mysqli_query(
                        $meta,
                        'SELECT id, main_branch_id FROM tbl_branches WHERE id = ' . $bid . ' LIMIT 1'
                    );
                    if ($rsB && ($br = mysqli_fetch_assoc($rsB))) {
                        $mainId = (int) ($br['main_branch_id'] ?? 0);
                        if ($mainId <= 0) {
                            $mainId = (int) ($br['id'] ?? 0);
                        }
                        foreach ($shops as $shop) {
                            if ((int) ($shop['id'] ?? 0) === $mainId) {
                                $pick = $shop;
                                mysqli_free_result($rsB);
                                break 2;
                            }
                        }
                    }
                    if ($rsB) {
                        mysqli_free_result($rsB);
                    }
                }
            }
            auragold_api_close_meta_link($meta);
            if (is_array($pick)) {
                return ['branch' => $pick, 'user' => $registryUser];
            }
            return null;
        }

        auragold_api_close_meta_link($meta);
        return null;
    }
}

if (!function_exists('auragold_api_shop_admin_contact_from_db')) {
    /**
     * Default admin contact from a shop's operational database (My Profile fields).
     *
     * @param array<string,mixed> $branch
     * @return array{email:string,phone:string}
     */
    function auragold_api_shop_admin_contact_from_db(array $branch): array
    {
        $out = ['email' => '', 'phone' => ''];
        $connShop = auragold_api_connect_shop_database_from_branch_row($branch);
        if (empty($connShop['ok']) || !($connShop['link'] instanceof mysqli)) {
            return $out;
        }
        $link = $connShop['link'];
        $rs   = auragold_api_mysqli_query(
            $link,
            "SELECT EmailId, Phone FROM tbl_users
             WHERE LOWER(TRIM(Username)) = 'admin'
               AND IFNULL(Status, 1) = 1
             ORDER BY id ASC
             LIMIT 1"
        );
        if ($rs && mysqli_num_rows($rs) > 0) {
            $u = mysqli_fetch_assoc($rs);
            if (is_array($u)) {
                $out['email'] = trim((string) ($u['EmailId'] ?? ''));
                $out['phone'] = trim((string) ($u['Phone'] ?? ''));
            }
            mysqli_free_result($rs);
        } elseif ($rs) {
            mysqli_free_result($rs);
        }
        try {
            @mysqli_close($link);
        } catch (Throwable $e) {
        }

        return $out;
    }
}

if (!function_exists('auragold_api_resolve_shop_list_contact')) {
    /**
     * Shop list contact: tbl_branches first, then default admin user in the shop DB.
     *
     * @param array<string,mixed> $branchRow
     * @return array{email:string,phone:string}
     */
    function auragold_api_resolve_shop_list_contact(array $branchRow): array
    {
        $email = trim((string) ($branchRow['email'] ?? ''));
        $phone = trim((string) ($branchRow['phone'] ?? ''));
        if ($email !== '' && $phone !== '') {
            return ['email' => $email, 'phone' => $phone];
        }

        $admin = auragold_api_shop_admin_contact_from_db($branchRow);
        if ($email === '' && $admin['email'] !== '') {
            $email = $admin['email'];
        }
        if ($phone === '' && $admin['phone'] !== '') {
            $phone = $admin['phone'];
        }

        return ['email' => $email, 'phone' => $phone];
    }
}
