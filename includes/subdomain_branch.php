<?php
/**
 * HTTP host → tbl_branches.code, operational DB credentials, and mysqli helpers.
 * Uses $_SERVER['HTTP_HOST'] (first label, e.g. pune.example.com → PUNE; pune.localhost → PUNE).
 * Unknown / missing branch row → tries code MAIN. Empty db_name on sub-branch uses parent credentials.
 * Requires branch_credentials.php (auragold_branch_row_db_credentials).
 */
require_once __DIR__ . '/branch_credentials.php';

if (!function_exists('auragold_get_subdomain')) {
    /**
     * First label of HTTP_HOST, uppercased (e.g. brn.domain.com → BRN).
     * localhost / 127.* / ::1 / single-label host → MAIN. www → MAIN.
     */
    function auragold_get_subdomain(): string {
        if (PHP_SAPI === 'cli') {
            return 'MAIN';
        }
        $host = isset($_SERVER['HTTP_HOST']) ? strtolower(trim((string) $_SERVER['HTTP_HOST'])) : '';
        if ($host === '' || $host === 'localhost' || (strlen($host) >= 4 && substr($host, 0, 4) === '127.') || $host === '[::1]') {
            return 'MAIN';
        }
        if (strpos($host, ':') !== false) {
            $host = explode(':', $host, 2)[0];
        }
        $parts = explode('.', $host);
        if (count($parts) < 2) {
            return 'MAIN';
        }
        if ($parts[0] === 'www') {
            return 'MAIN';
        }
        return strtoupper($parts[0]);
    }
}

if (!function_exists('auragold_get_branch_by_code')) {
    /**
     * @return array<string,mixed>|null Full row or null if not found / inactive.
     */
    function auragold_get_branch_by_code(mysqli $registryConn, string $code): ?array {
        $code = trim($code);
        if ($code === '') {
            return null;
        }
        $sql = 'SELECT * FROM tbl_branches WHERE UPPER(TRIM(IFNULL(`code`, ""))) = UPPER(TRIM(?)) AND `status` = 1 LIMIT 1';
        if (method_exists('mysqli_stmt', 'get_result')) {
            $st  = @$registryConn->prepare($sql);
            if (!$st) {
                return null;
            }
            $st->bind_param('s', $code);
            if (!$st->execute()) {
                $st->close();
                return null;
            }
            $res = $st->get_result();
            $row = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
            if ($res) {
                mysqli_free_result($res);
            }
            $st->close();
            return is_array($row) ? $row : null;
        }
        $e  = mysqli_real_escape_string($registryConn, $code);
        $sq = "SELECT * FROM tbl_branches WHERE UPPER(TRIM(IFNULL(`code`, ''))) = UPPER(TRIM('{$e}')) AND `status` = 1 LIMIT 1";
        $r  = @mysqli_query($registryConn, $sq);
        $row = ($r && mysqli_num_rows($r) > 0) ? mysqli_fetch_assoc($r) : null;
        if ($r) {
            mysqli_free_result($r);
        }
        return is_array($row) ? $row : null;
    }
}

if (!function_exists('auragold_mysql_schema_name_exists_on_server')) {
    /**
     * True if the server has a database with this name. Uses information_schema (no CONNECT to target DB).
     * Prefer this over mysqli_connect(..., $dbName) — avoids false negatives when the schema exists but
     * the app user lacks per-schema CONNECT, and avoids PHP 8+ exceptions on missing DB.
     */
    function auragold_mysql_schema_name_exists_on_server(mysqli $mysqli, string $dbName): bool {
        $dbName = trim($dbName);
        if ($dbName === '') {
            return false;
        }
        $e   = mysqli_real_escape_string($mysqli, $dbName);
        $sql = "SELECT 1 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '" . $e . "' LIMIT 1";
        $r   = @mysqli_query($mysqli, $sql);
        if ($r && mysqli_fetch_row($r)) {
            mysqli_free_result($r);
            return true;
        }
        if ($r) {
            mysqli_free_result($r);
        }
        return false;
    }
}

if (!function_exists('auragold_mysql_database_schema_exists')) {
    /**
     * True if MySQL has a schema we can open with registry credentials (local dev pattern).
     * @deprecated Prefer auragold_mysql_schema_name_exists_on_server when a registry mysqli is available.
     */
    function auragold_mysql_database_schema_exists(string $dbName): bool {
        $dbName = trim($dbName);
        if ($dbName === '' || !defined('DB_HOST')) {
            return false;
        }
        $u = defined('DB_USER') ? (string) DB_USER : '';
        $p = defined('DB_PASS') ? (string) DB_PASS : '';
        try {
            $lnk = mysqli_connect((string) DB_HOST, $u, $p, $dbName);
        } catch (Throwable $e) {
            // PHP 8+ throws mysqli_sql_exception for unknown database; probe must return false, not 500.
            return false;
        }
        if ($lnk) {
            mysqli_close($lnk);
            return true;
        }
        return false;
    }
}

if (!function_exists('auragold_derived_operational_db_name_candidates_from_branch_row')) {
    /**
     * Possible DB names from branch display name (same rules as allocate_unique_branch_db_credentials),
     * plus auragold_{slug}_branch when the slug has no _branch suffix (e.g. name "Mumbai" → auragold_mumbai
     * and auragold_mumbai_branch).
     *
     * @return list<string>
     */
    function auragold_derived_operational_db_name_candidates_from_branch_row(array $branch): array {
        require_once __DIR__ . '/branch_db_auto_credentials.php';
        $name = trim((string) ($branch['name'] ?? ''));
        if ($name === '' || !function_exists('auragold_branch_slug_from_display_name')) {
            return [];
        }
        $prefix = defined('AURAGOLD_DB_PREFIX') ? (string) AURAGOLD_DB_PREFIX : 'auragold_';
        if ($prefix !== '' && substr($prefix, -1) !== '_') {
            $prefix .= '_';
        }
        $slug = auragold_branch_slug_from_display_name($name);
        if ($slug === '') {
            return [];
        }
        $primary = $prefix . $slug;
        if (strlen($primary) > 64) {
            $primary = substr($primary, 0, 64);
        }
        $out = [$primary];
        if (substr($slug, -7) !== '_branch' && strlen($primary) + 7 <= 64) {
            $alt = $prefix . $slug . '_branch';
            if (strlen($alt) > 64) {
                $alt = substr($alt, 0, 64);
            }
            if ($alt !== $primary) {
                $out[] = $alt;
            }
        }
        return $out;
    }
}

if (!function_exists('auragold_derived_operational_db_name_from_branch_row')) {
    /**
     * Same pattern as auragold_allocate_unique_branch_db_credentials (AURAGOLD_DB_PREFIX + slug from name).
     * Used when registry db_name is empty or wrongly set to the bootstrap registry schema only.
     */
    function auragold_derived_operational_db_name_from_branch_row(array $branch): string {
        $c = auragold_derived_operational_db_name_candidates_from_branch_row($branch);
        return $c[0] ?? '';
    }
}

if (!function_exists('auragold_resolve_branch_operational_credentials')) {
    /**
     * Effective db_name / db_users / db_password for a tbl_branches row.
     * Registry rows sometimes omit db_name on sub-branches while the replica inside the parent DB lists it
     * (e.g. Pune → auragold_pune); we read that copy before falling back to the parent (shared DB).
     *
     * @return array{db_name:string,db_user:string,db_pass:string}
     */
    function auragold_resolve_branch_operational_credentials(array $branch, mysqli $registryConn): array {
        $rowId = (int) ($branch['id'] ?? 0);
        if ($rowId > 0 && function_exists('auragold_registry_tbl_branches_row_by_id')) {
            $fresh = auragold_registry_tbl_branches_row_by_id($rowId);
            if ($fresh) {
                $branch = $fresh;
            }
        }
        $metaConn = (function_exists('auragold_registry_mysqli') && auragold_registry_mysqli() instanceof mysqli)
            ? auragold_registry_mysqli()
            : $registryConn;

        $creds  = auragold_branch_row_db_credentials($branch);
        $mainId = (int) ($branch['main_branch_id'] ?? 0);
        $rowId  = (int) ($branch['id'] ?? 0);

        $dbnRaw = trim((string) ($creds['db_name'] ?? ''));
        $registryDb = defined('AURAGOLD_REGISTRY_DB') ? trim((string) AURAGOLD_REGISTRY_DB) : 'auragold';
        $registryLike = ($registryDb !== '' && $dbnRaw !== '' && strcasecmp($dbnRaw, $registryDb) === 0);

        // Prefer auragold_{slug} when that schema exists (fixes rows where db_name was left as bootstrap DB only).
        if ($rowId > 0 && ($dbnRaw === '' || $registryLike)) {
            foreach (auragold_derived_operational_db_name_candidates_from_branch_row($branch) as $derived) {
                if ($derived === '' || !auragold_mysql_schema_name_exists_on_server($metaConn, $derived)) {
                    continue;
                }
                $du = trim((string) ($creds['db_user'] ?? ''));
                $dp = (string) ($creds['db_pass'] ?? '');
                if ($du === '' && defined('DB_USER')) {
                    $du = (string) DB_USER;
                    $dp = defined('DB_PASS') ? (string) DB_PASS : '';
                }
                return [
                    'db_name' => $derived,
                    'db_user' => $du,
                    'db_pass' => $dp,
                ];
            }
        }

        $dbn = $dbnRaw;
        if ($dbn !== '') {
            return [
                'db_name' => $dbn,
                'db_user' => trim((string) ($creds['db_user'] ?? '')),
                'db_pass' => (string) ($creds['db_pass'] ?? ''),
            ];
        }

        $parent = null;
        if ($mainId > 0) {
            $mid = (int) $mainId;
            if (method_exists('mysqli_stmt', 'get_result')) {
                $st = @$metaConn->prepare('SELECT * FROM tbl_branches WHERE id = ? LIMIT 1');
                if ($st) {
                    $st->bind_param('i', $mid);
                    if ($st->execute()) {
                        $res = $st->get_result();
                        $parent = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
                        if ($res) {
                            mysqli_free_result($res);
                        }
                    }
                    $st->close();
                }
            } else {
                $q = @mysqli_query($metaConn, 'SELECT * FROM tbl_branches WHERE id = ' . $mid . ' LIMIT 1');
                $parent = ($q && mysqli_num_rows($q) > 0) ? mysqli_fetch_assoc($q) : null;
                if ($q) {
                    mysqli_free_result($q);
                }
            }
        }

        if ($mainId > 0 && $rowId > 0 && is_array($parent)) {
            $pC  = auragold_branch_row_db_credentials($parent);
            $pdb = trim((string) ($pC['db_name'] ?? ''));
            if ($pdb !== '' && defined('DB_HOST')) {
                $pu = $pC['db_user'] !== '' ? $pC['db_user'] : (defined('DB_USER') ? (string) DB_USER : '');
                $pp = $pC['db_user'] !== '' ? $pC['db_pass'] : (defined('DB_PASS') ? (string) DB_PASS : '');
                $lnk = function_exists('auragold_mysqli_connect_operational')
                    ? auragold_mysqli_connect_operational((string) DB_HOST, (string) $pu, (string) $pp, $pdb)
                    : null;
                if (!$lnk) {
                    try {
                        $lnk = @mysqli_connect((string) DB_HOST, (string) $pu, (string) $pp, (string) $pdb);
                    } catch (Throwable $e) {
                        $lnk = null;
                    }
                }
                if ($lnk) {
                    mysqli_set_charset($lnk, 'utf8mb4');
                    $r2 = mysqli_query($lnk, 'SELECT * FROM tbl_branches WHERE id = ' . $rowId . ' LIMIT 1');
                    $childReplica = ($r2 && mysqli_num_rows($r2) > 0) ? mysqli_fetch_assoc($r2) : null;
                    if ($r2) {
                        mysqli_free_result($r2);
                    }
                    mysqli_close($lnk);
                    if (is_array($childReplica)) {
                        $op = auragold_branch_row_db_credentials($childReplica);
                        $opDb = trim((string) ($op['db_name'] ?? ''));
                        if ($opDb !== '') {
                            $opUser = trim((string) ($op['db_user'] ?? ''));
                            $opPass = (string) ($op['db_pass'] ?? '');
                            $pUser  = trim((string) ($pC['db_user'] ?? ''));
                            $pPass  = (string) ($pC['db_pass'] ?? '');
                            return [
                                'db_name' => $opDb,
                                'db_user' => $opUser !== '' ? $opUser : $pUser,
                                'db_pass' => $opUser !== '' ? $opPass : $pPass,
                            ];
                        }
                    }
                }
            }
        }

        if ($mainId > 0 && is_array($parent)) {
            $creds = auragold_branch_row_db_credentials($parent);
        }

        return [
            'db_name' => trim((string) ($creds['db_name'] ?? '')),
            'db_user' => trim((string) ($creds['db_user'] ?? '')),
            'db_pass' => (string) ($creds['db_pass'] ?? ''),
        ];
    }
}

if (!function_exists('auragold_mysqli_connect_operational')) {
    function auragold_mysqli_connect_operational(string $host, string $user, string $pass, string $dbname): ?mysqli {
        $dbname = trim($dbname);
        if ($dbname === '') {
            return null;
        }
        $link = mysqli_init();
        if (!$link) {
            return null;
        }
        @mysqli_options($link, MYSQLI_OPT_CONNECT_TIMEOUT, 5);
        try {
            if (!@mysqli_real_connect($link, $host, $user, $pass, $dbname)) {
                @mysqli_close($link);
                return null;
            }
        } catch (Throwable $e) {
            @mysqli_close($link);
            return null;
        }
        return $link;
    }
}

if (!function_exists('auragold_connect_operational_for_branch_row')) {
    /**
     * Open operational mysqli for a registry tbl_branches row; optionally sync session branch labels.
     */
    function auragold_connect_operational_for_branch_row(
        array $branch,
        mysqli $registryConn,
        string $dbHost,
        string $registryDbUser,
        string $registryDbPass,
        bool $syncSessionBranchLabels = true
    ): ?mysqli {
        $eff = auragold_resolve_branch_operational_credentials($branch, $registryConn);
        $dbn = $eff['db_name'];
        $dbu = $eff['db_user'] !== '' ? $eff['db_user'] : $registryDbUser;
        $dbp = $eff['db_user'] !== '' ? $eff['db_pass'] : $registryDbPass;

        if ($dbn === '') {
            return null;
        }

        $conn = auragold_mysqli_connect_operational($dbHost, $dbu, $dbp, $dbn);
        if (!$conn && $dbu !== $registryDbUser) {
            $conn = auragold_mysqli_connect_operational($dbHost, $registryDbUser, $registryDbPass, $dbn);
        }
        if (!$conn) {
            return null;
        }

        if ($syncSessionBranchLabels
            && PHP_SAPI !== 'cli'
            && function_exists('session_status')
            && session_status() === PHP_SESSION_ACTIVE) {
            $hbid = (int) ($branch['id'] ?? 0);
            if ($hbid > 0) {
                $_SESSION['working_branch_id']   = $hbid;
                $hnm = trim((string) ($branch['name'] ?? ''));
                $_SESSION['working_branch_name'] = $hnm !== '' ? $hnm : ('Branch #' . $hbid);
            }
        }
        return $conn;
    }
}

if (!function_exists('auragold_connect_operational_for_login_target_url')) {
    /**
     * Connect using the login “IP address / server URL” field (session or argument).
     * Skips super/main and GM portal URLs — those are not branch endpoints.
     */
    function auragold_connect_operational_for_login_target_url(
        mysqli $registryConn,
        string $dbHost,
        string $registryDbUser,
        string $registryDbPass,
        ?string $loginTargetUrl = null
    ): ?mysqli {
        $raw = $loginTargetUrl;
        if ($raw === null || trim((string) $raw) === '') {
            if (PHP_SAPI === 'cli'
                || !function_exists('session_status')
                || session_status() !== PHP_SESSION_ACTIVE) {
                return null;
            }
            $raw = isset($_SESSION['login_target_url']) ? (string) $_SESSION['login_target_url'] : '';
        }
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        if (!function_exists('auragold_super_portal_login_target_ok')) {
            require_once __DIR__ . '/auragold_superadmin.php';
        }
        if (auragold_super_portal_login_target_ok($raw) || auragold_gm_portal_login_target_ok($raw)) {
            return null;
        }

        if (!function_exists('auragold_registry_branch_id_for_login_target_url')) {
            require_once __DIR__ . '/login_credential_connections.php';
        }
        $bid = auragold_registry_branch_id_for_login_target_url($raw);
        if ($bid <= 0) {
            return null;
        }

        $branch = function_exists('auragold_registry_tbl_branches_row_by_id')
            ? auragold_registry_tbl_branches_row_by_id($bid)
            : null;
        if (!$branch && function_exists('getRecordMaster')) {
            $branch = getRecordMaster('SELECT * FROM tbl_branches WHERE id = ' . (int) $bid . ' LIMIT 1');
        }
        if (!$branch) {
            return null;
        }

        return auragold_connect_operational_for_branch_row(
            $branch,
            $registryConn,
            $dbHost,
            $registryDbUser,
            $registryDbPass,
            true
        );
    }
}

if (!function_exists('auragold_connect_operational_for_host_branch')) {
    /**
     * Connect to operational DB for current HTTP host (subdomain → tbl_branches), after MAIN fallback.
     * Returns null to keep using $conn_master (registry / same DB).
     */
    function auragold_connect_operational_for_host_branch(
        mysqli $registryConn,
        string $dbHost,
        string $registryDbUser,
        string $registryDbPass
    ): ?mysqli {
        $code   = auragold_get_subdomain();
        $branch = auragold_get_branch_by_code($registryConn, $code);
        if (!$branch) {
            $branch = auragold_get_branch_by_code($registryConn, 'MAIN');
        }
        if (!$branch) {
            error_log('AuraGold: no tbl_branches row for host code "' . $code . '" and no MAIN fallback.');
            return null;
        }

        $conn = auragold_connect_operational_for_branch_row(
            $branch,
            $registryConn,
            $dbHost,
            $registryDbUser,
            $registryDbPass,
            true
        );
        if (!$conn) {
            $eff = auragold_resolve_branch_operational_credentials($branch, $registryConn);
            $dbn = $eff['db_name'];
            $err = mysqli_connect_error();
            error_log('AuraGold: branch DB connection failed (code ' . $code . ', db ' . $dbn . '): ' . $err);
            if (PHP_SAPI !== 'cli') {
                die('Branch Database Not Configured');
            }
            return null;
        }
        return $conn;
    }
}

/** @deprecated Prefer auragold_get_subdomain() */
function getSubdomain(): string {
    return auragold_get_subdomain();
}

/**
 * @return array<string,mixed>|null
 */
function getBranchByCode($code) {
    global $conn_master;
    if (!isset($conn_master) || !($conn_master instanceof mysqli)) {
        return null;
    }
    return auragold_get_branch_by_code($conn_master, (string) $code);
}
