<?php
/**
 * Open per-branch MySQL connections for login password checks (tbl_users lives in each branch database).
 */
require_once __DIR__ . '/branch_create_db_after_save.php';
require_once __DIR__ . '/branch_credentials.php';
require_once __DIR__ . '/branch_tbl_branches_ip_subdomain.php';
if (!function_exists('auragold_resolve_branch_operational_credentials')) {
    require_once __DIR__ . '/subdomain_branch.php';
}

if (!function_exists('auragold_open_mysqli_for_login_branch_id')) {
    /**
     * Connection used to validate tbl_users for the Branch dropdown value (0 = default app DB / “Main”).
     *
     * @return array{0:?mysqli,1:bool} mysqli instance and whether the caller must mysqli_close() it
     */
    function auragold_open_mysqli_for_login_branch_id(int $login_branch_id): array {
        $login_branch_id = (int) $login_branch_id;
        if (!defined('DB_HOST')) {
            return [null, false];
        }
        $host = (string) DB_HOST;

        if ($login_branch_id <= 0) {
            if (!defined('DB_NAME') || (string) DB_NAME === '') {
                return [null, false];
            }
            $db   = (string) DB_NAME;
            $user = defined('DB_USER') ? (string) DB_USER : '';
            $pass = defined('DB_PASS') ? (string) DB_PASS : '';
            $link = @mysqli_connect($host, $user, $pass, $db);
            if (!$link) {
                return [null, false];
            }
            mysqli_set_charset($link, 'utf8mb4');
            return [$link, true];
        }

        $row = function_exists('auragold_registry_tbl_branches_row_by_id')
            ? auragold_registry_tbl_branches_row_by_id($login_branch_id)
            : (function_exists('getRecordMaster') ? getRecordMaster('SELECT * FROM tbl_branches WHERE id = ' . $login_branch_id . ' LIMIT 1') : null);
        if (!$row) {
            return [null, false];
        }
        global $conn_master;
        $regMeta = function_exists('auragold_registry_mysqli') ? auragold_registry_mysqli() : null;
        $creds = (($regMeta instanceof mysqli || $conn_master instanceof mysqli) && function_exists('auragold_resolve_branch_operational_credentials'))
            ? auragold_resolve_branch_operational_credentials($row, ($regMeta instanceof mysqli) ? $regMeta : $conn_master)
            : auragold_branch_row_db_credentials($row);
        $db_name = $creds['db_name'];
        if ($db_name === '') {
            if (!defined('DB_NAME') || (string) DB_NAME === '') {
                return [null, false];
            }
            $db   = (string) DB_NAME;
            $user = defined('DB_USER') ? (string) DB_USER : '';
            $pass = defined('DB_PASS') ? (string) DB_PASS : '';
            $link = @mysqli_connect($host, $user, $pass, $db);
            if (!$link) {
                return [null, false];
            }
            mysqli_set_charset($link, 'utf8mb4');
            return [$link, true];
        }

        $db_user = $creds['db_user'] !== '' ? $creds['db_user'] : (defined('DB_USER') ? (string) DB_USER : '');
        $db_pass = $creds['db_user'] !== '' ? $creds['db_pass'] : (defined('DB_PASS') ? (string) DB_PASS : '');

        $link = auragold_mysqli_connect_branch_or_registry($host, $db_name, $db_user, $db_pass);
        if (!$link) {
            return [null, false];
        }
        mysqli_set_charset($link, 'utf8mb4');
        return [$link, true];
    }
}

if (!function_exists('auragold_verify_user_on_mysqli')) {
    /**
     * @param string $username_raw Trimmed username from the form (not pre-escaped)
     */
    function auragold_verify_user_on_mysqli(mysqli $link, string $username_raw, string $password_plain): ?array {
        $password_plain = trim((string) $password_plain);
        $username_raw   = trim((string) $username_raw);
        if ($username_raw === '' || $password_plain === '') {
            return null;
        }
        $e = mysqli_real_escape_string($link, $username_raw);
        $res = mysqli_query(
            $link,
            "SELECT * FROM tbl_users WHERE LOWER(TRIM(Username)) = LOWER(TRIM('$e')) LIMIT 1"
        );
        if (!$res || mysqli_num_rows($res) < 1) {
            if ($res) {
                mysqli_free_result($res);
            }
            return null;
        }
        $user = mysqli_fetch_assoc($res);
        mysqli_free_result($res);
        if (!$user || !function_exists('auragold_user_active') || !auragold_user_active($user)) {
            return null;
        }
        if (!function_exists('auragold_row_password_field')) {
            return null;
        }
        $stored = auragold_row_password_field($user);
        if ($stored === '' && !empty($user['Password'])) {
            $stored = trim((string) $user['Password']);
        }
        if ($stored === '' || !hash_equals($stored, $password_plain)) {
            return null;
        }
        return $user;
    }
}

if (!function_exists('auragold_verify_user_credentials_for_login_branch')) {
    /**
     * Validate tbl_users in the database that matches the selected branch (login_branch_id).
     *
     * @param string $username_raw Trimmed username (not esc() output)
     */
    function auragold_verify_user_credentials_for_login_branch(string $username_raw, string $password_plain, int $login_branch_id): ?array {
        [$link, $close] = auragold_open_mysqli_for_login_branch_id($login_branch_id);
        if (!$link) {
            return null;
        }
        $user = auragold_verify_user_on_mysqli($link, $username_raw, $password_plain);
        if ($close) {
            mysqli_close($link);
        }
        return $user;
    }
}

if (!function_exists('auragold_registry_main_db_name_for_login_label')) {
    /**
     * Operational db_name for login_branch_id 0 (“Main”): use the matching registry main row’s db_name,
     * not DB_NAME alone (bootstrap is often the registry schema e.g. auragold, while the main shop DB is auragold_main_branch).
     *
     * @param string $option_label Branch label from the dropdown (may include “(default app DB)” for superadmin row).
     */
    function auragold_registry_main_db_name_for_login_label(string $option_label): string {
        $label = trim($option_label);
        $label = preg_replace('/\s*\(default app DB\)\s*$/iu', '', $label);
        $label = trim($label);
        global $conn_master;
        $reg = function_exists('auragold_registry_mysqli') ? auragold_registry_mysqli() : null;
        $mr  = null;
        if ($reg) {
            if ($label !== '') {
                $e  = mysqli_real_escape_string($reg, $label);
                $rs = mysqli_query(
                    $reg,
                    'SELECT * FROM tbl_branches WHERE IFNULL(main_branch_id, 0) = 0 AND LOWER(TRIM(name)) = LOWER(\'' . $e . '\') LIMIT 1'
                );
                if ($rs && mysqli_num_rows($rs) > 0) {
                    $mr = mysqli_fetch_assoc($rs);
                }
                if ($rs) {
                    mysqli_free_result($rs);
                }
            }
            if (!$mr) {
                $rs = mysqli_query($reg, 'SELECT * FROM tbl_branches WHERE IFNULL(main_branch_id, 0) = 0 ORDER BY id ASC LIMIT 1');
                if ($rs && mysqli_num_rows($rs) > 0) {
                    $mr = mysqli_fetch_assoc($rs);
                }
                if ($rs) {
                    mysqli_free_result($rs);
                }
            }
        } elseif (function_exists('getRecordMaster')) {
            if ($label !== '' && function_exists('esc')) {
                $mr = getRecordMaster(
                    "SELECT * FROM tbl_branches WHERE IFNULL(main_branch_id, 0) = 0 AND LOWER(TRIM(name)) = LOWER('" . esc($label) . "') LIMIT 1"
                );
            }
            if (!$mr) {
                $mr = getRecordMaster('SELECT * FROM tbl_branches WHERE IFNULL(main_branch_id, 0) = 0 ORDER BY id ASC LIMIT 1');
            }
        }
        if (!$mr) {
            return defined('DB_NAME') ? (string) DB_NAME : '';
        }
        $regForResolve = ($reg instanceof mysqli) ? $reg : ($conn_master instanceof mysqli ? $conn_master : null);
        if ($regForResolve instanceof mysqli && function_exists('auragold_resolve_branch_operational_credentials')) {
            $c = auragold_resolve_branch_operational_credentials($mr, $regForResolve);
            $d = trim((string) ($c['db_name'] ?? ''));
        } else {
            $d = trim((string) (auragold_branch_row_db_credentials($mr)['db_name'] ?? ''));
        }
        if ($d !== '') {
            return $d;
        }
        return defined('DB_NAME') ? (string) DB_NAME : '';
    }
}

if (!function_exists('auragold_login_expected_db_name_for_branch_id')) {
    /**
     * Operational db_name for login validation and the login_db_name hidden field — must match
     * auragold_resolve_branch_operational_credentials (sub-branches often have empty db_name in registry).
     *
     * @param int $login_branch_id 0 = first registry “main” row (same rules as Main dropdown option)
     */
    function auragold_login_expected_db_name_for_branch_id(int $login_branch_id): string {
        $login_branch_id = (int) $login_branch_id;
        if ($login_branch_id <= 0) {
            if (!function_exists('auragold_registry_main_db_name_for_login_label')) {
                return defined('DB_NAME') ? trim((string) DB_NAME) : '';
            }
            $ml = 'Main';
            $reg = function_exists('auragold_registry_mysqli') ? auragold_registry_mysqli() : null;
            if ($reg) {
                $rs = mysqli_query($reg, 'SELECT name FROM tbl_branches WHERE IFNULL(main_branch_id, 0) = 0 ORDER BY id ASC LIMIT 1');
                if ($rs && mysqli_num_rows($rs) > 0) {
                    $m = mysqli_fetch_assoc($rs);
                    if ($m && trim((string) ($m['name'] ?? '')) !== '') {
                        $ml = trim((string) $m['name']);
                    }
                }
                if ($rs) {
                    mysqli_free_result($rs);
                }
            } elseif (function_exists('getRecordMaster')) {
                $m = getRecordMaster(
                    'SELECT name FROM tbl_branches WHERE IFNULL(main_branch_id, 0) = 0 ORDER BY id ASC LIMIT 1'
                );
                if ($m && trim((string) ($m['name'] ?? '')) !== '') {
                    $ml = trim((string) $m['name']);
                }
            }
            return trim((string) auragold_registry_main_db_name_for_login_label($ml . ' (default app DB)'));
        }
        $br = function_exists('auragold_registry_tbl_branches_row_by_id')
            ? auragold_registry_tbl_branches_row_by_id($login_branch_id)
            : (function_exists('getRecordMaster') ? getRecordMaster('SELECT * FROM tbl_branches WHERE id = ' . $login_branch_id . ' LIMIT 1') : null);
        if (!$br) {
            return '';
        }
        global $conn_master;
        $regMeta = function_exists('auragold_registry_mysqli') ? auragold_registry_mysqli() : null;
        $c = (($regMeta instanceof mysqli || $conn_master instanceof mysqli) && function_exists('auragold_resolve_branch_operational_credentials'))
            ? auragold_resolve_branch_operational_credentials($br, ($regMeta instanceof mysqli) ? $regMeta : $conn_master)
            : auragold_branch_row_db_credentials($br);
        $expected = trim((string) ($c['db_name'] ?? ''));
        // Never fall back to DB_NAME for a real branch id: that is the bootstrap/default schema (often "auragold")
        // and mislabels e.g. Mumbai when resolve returns empty or the row still points at the registry DB only.
        if ($expected === '') {
            $raw = trim((string) (auragold_branch_row_db_credentials($br)['db_name'] ?? ''));
            if ($raw !== '') {
                $expected = $raw;
            }
        }
        if ($expected === '') {
            $probe = ($regMeta instanceof mysqli) ? $regMeta : ($conn_master instanceof mysqli ? $conn_master : null);
            if ($probe instanceof mysqli && function_exists('auragold_derived_operational_db_name_candidates_from_branch_row')) {
                foreach (auragold_derived_operational_db_name_candidates_from_branch_row($br) as $cand) {
                    if ($cand !== '' && function_exists('auragold_mysql_schema_name_exists_on_server')
                        && auragold_mysql_schema_name_exists_on_server($probe, $cand)) {
                        $expected = $cand;
                        break;
                    }
                }
            }
        }
        return $expected;
    }
}

if (!function_exists('auragold_login_branch_options_add_db_name')) {
    /**
     * @param list<array{id:int,label:string}> $options
     * @return list<array{id:int,label:string,db_name:string}>
     */
    function auragold_login_branch_options_add_db_name(array $options): array {
        $out = [];
        foreach ($options as $o) {
            $id = (int) ($o['id'] ?? 0);
            $out[] = [
                'id'      => $id,
                'label'   => (string) ($o['label'] ?? ''),
                'db_name' => function_exists('auragold_login_expected_db_name_for_branch_id')
                    ? auragold_login_expected_db_name_for_branch_id($id)
                    : '',
            ];
        }
        return $out;
    }
}

if (!function_exists('auragold_login_superadmin_discovery_branch_options')) {
    /**
     * Full branch list for superadmin login (pick operational DB: default or any tbl_branches row).
     *
     * @return list<array{id:int,label:string,db_name:string}>
     */
    function auragold_login_superadmin_discovery_branch_options(): array {
        $opts         = [];
        $mainLabel    = 'Main';
        $firstMainId  = 0;
        $reg          = function_exists('auragold_registry_mysqli') ? auragold_registry_mysqli() : null;
        if ($reg) {
            $rs = mysqli_query($reg, 'SELECT id, name FROM tbl_branches WHERE IFNULL(main_branch_id, 0) = 0 ORDER BY id ASC LIMIT 1');
            if ($rs && mysqli_num_rows($rs) > 0) {
                $m = mysqli_fetch_assoc($rs);
                if ($m) {
                    $firstMainId = (int) ($m['id'] ?? 0);
                    if (trim((string) ($m['name'] ?? '')) !== '') {
                        $mainLabel = trim((string) $m['name']);
                    }
                }
            }
            if ($rs) {
                mysqli_free_result($rs);
            }
        } elseif (function_exists('getRecordMaster')) {
            $m = getRecordMaster(
                'SELECT id, name FROM tbl_branches WHERE IFNULL(main_branch_id, 0) = 0 ORDER BY id ASC LIMIT 1'
            );
            if ($m) {
                $firstMainId = (int) ($m['id'] ?? 0);
                if (trim((string) ($m['name'] ?? '')) !== '') {
                    $mainLabel = trim((string) $m['name']);
                }
            }
        }
        $opts[] = [
            'id'    => 0,
            'label' => $mainLabel . ' (default app DB)',
        ];
        $rows = function_exists('auragold_registry_list_tbl_branches_ordered')
            ? auragold_registry_list_tbl_branches_ordered()
            : (function_exists('getListMaster') ? getListMaster(
                'SELECT * FROM tbl_branches ORDER BY IFNULL(main_branch_id, 0) ASC, id ASC'
            ) : []);
        if (!is_array($rows)) {
            return auragold_login_branch_options_add_db_name($opts);
        }
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $rowMain = (int) ($row['main_branch_id'] ?? 0);
            if ($rowMain === 0 && $firstMainId > 0 && $id === $firstMainId) {
                // Same branch as the synthetic “Main / default” row (id=0) — do not list twice
                // (see auragold_login_build_branch_options_for_user: skip first main in additional mains).
                continue;
            }
            $nm = trim((string) ($row['name'] ?? ''));
            if ($nm === '') {
                $nm = 'Branch #' . $id;
            }
            $opts[] = [
                'id'    => $id,
                'label' => $nm,
            ];
        }
        return auragold_login_branch_options_add_db_name($opts);
    }
}

if (!function_exists('auragold_login_branch_options_from_operational_tbl_branches')) {
    /**
     * Branch dropdown rows from tbl_branches inside the operational schema for a registry branch id
     * (e.g. registry id 33 → auragold_mumbai_branch … SELECT * FROM tbl_branches there).
     * Used when ?branch_entry= so the list matches that database’s replica, not the full central registry.
     *
     * @return list<array{id:int,label:string,db_name:string}>
     */
    function auragold_login_branch_options_from_operational_tbl_branches(int $registryBranchId): array {
        $registryBranchId = (int) $registryBranchId;
        if ($registryBranchId <= 0) {
            return [];
        }
        [$lnk, $cls] = auragold_open_mysqli_for_login_branch_id($registryBranchId);
        if (!$lnk) {
            return [];
        }
        $sql = 'SELECT * FROM tbl_branches ORDER BY IFNULL(main_branch_id, 0) ASC, id ASC';
        $res = mysqli_query($lnk, $sql);
        $out = [];
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                if (function_exists('auragold_tbl_branch_row_is_active') && !auragold_tbl_branch_row_is_active($row)) {
                    continue;
                }
                $id = (int) ($row['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $nm = trim((string) ($row['name'] ?? ''));
                if ($nm === '') {
                    $nm = 'Branch #' . $id;
                }
                $dbn = function_exists('auragold_login_expected_db_name_for_branch_id')
                    ? auragold_login_expected_db_name_for_branch_id($id)
                    : trim((string) (auragold_branch_row_db_credentials($row)['db_name'] ?? ''));
                $out[] = [
                    'id'      => $id,
                    'label'   => $nm,
                    'db_name' => $dbn,
                ];
            }
            mysqli_free_result($res);
        }
        if ($cls) {
            mysqli_close($lnk);
        }
        return $out;
    }
}

if (!function_exists('auragold_login_filter_branch_options_to_user_scope')) {
    /**
     * Keep portal/operational options that the user is allowed to pick (same ids as registry-based options).
     *
     * @param list<array{id:int,label:string,db_name:string}> $candidates
     * @return list<array{id:int,label:string,db_name:string}>
     */
    function auragold_login_filter_branch_options_to_user_scope(array $candidates, array $user): array {
        if ($candidates === [] || !function_exists('auragold_login_build_branch_options_for_user')) {
            return $candidates;
        }
        $allowed = auragold_login_build_branch_options_for_user($user);
        $allow   = [];
        foreach ($allowed as $a) {
            $allow[(int) ($a['id'] ?? 0)] = true;
        }
        $out = [];
        foreach ($candidates as $c) {
            $cid = (int) ($c['id'] ?? 0);
            if ($cid > 0 && !empty($allow[$cid])) {
                $out[] = $c;
            }
        }
        return $out;
    }
}

if (!function_exists('auragold_login_user_url_string_variants_for_match')) {
    /**
     * Normalized strings to compare to tbl_branches.subdomain_url / ip_address.
     *
     * @return list<string>
     */
    function auragold_login_user_url_string_variants_for_match(string $raw): array {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        $out   = [strtolower($raw)];
        $st    = auragold_branch_ip_and_subdomain_for_storage($raw);
        if (empty($st['rejected'])) {
            if (trim($st['subdomain_url'] ?? '') !== '') {
                $out[] = strtolower($st['subdomain_url']);
            }
            if (trim($st['ip_address'] ?? '') !== '') {
                $out[] = strtolower($st['ip_address']);
            }
        }
        if (preg_match('#^https?://#i', $raw)) {
            $h = parse_url($raw, PHP_URL_HOST);
            if (is_string($h) && $h !== '') {
                $out[] = strtolower($h);
            }
        } else {
            $h = auragold_normalize_branch_host_for_storage($raw);
            if ($h !== '') {
                $out[] = strtolower($h);
                $out[] = 'https://' . strtolower($h);
                $out[] = 'http://' . strtolower($h);
            }
        }
        $out = array_filter(array_map('strval', array_unique($out)));
        return array_values($out);
    }
}

if (!function_exists('auragold_login_registry_row_url_string_variants_for_match')) {
    /**
     * @return list<string>
     */
    function auragold_login_registry_row_url_string_variants_for_match(array $row): array {
        $out = [];
        foreach (['subdomain_url', 'ip_address'] as $k) {
            $c = trim((string) ($row[$k] ?? ''));
            if ($c === '') {
                continue;
            }
            $out[] = strtolower($c);
            if (preg_match('#^https?://#i', $c)) {
                $h = parse_url($c, PHP_URL_HOST);
                if (is_string($h) && $h !== '') {
                    $out[] = strtolower($h);
                }
            } else {
                $h = auragold_normalize_branch_host_for_storage($c);
                if ($h !== '' && function_exists('auragold_branch_host_label_plausible') && auragold_branch_host_label_plausible($h)) {
                    $out[] = strtolower($h);
                }
            }
        }
        $out = array_filter(array_map('strval', array_unique($out)));
        return array_values($out);
    }
}

if (!function_exists('auragold_login_user_and_registry_url_strings_match')) {
    function auragold_login_user_and_registry_url_strings_match(array $userVariants, array $rowVariants): bool {
        foreach ($userVariants as $u) {
            foreach ($rowVariants as $r) {
                if ($u === '' || $r === '') {
                    continue;
                }
                if ($u === $r) {
                    return true;
                }
                $ul = strlen($u);
                $rl = strlen($r);
                if ($ul < $rl && 0 === strncmp($r, $u, $ul)) {
                    return true;
                }
                if ($rl < $ul && 0 === strncmp($u, $r, $rl)) {
                    return true;
                }
            }
        }
        foreach ($userVariants as $u) {
            if (!preg_match('#^https?://#i', $u)) {
                continue;
            }
            $hUser = parse_url($u, PHP_URL_HOST);
            if (!is_string($hUser) || $hUser === '') {
                continue;
            }
            $hUser = strtolower($hUser);
            foreach ($rowVariants as $r) {
                if (!preg_match('#^https?://#i', $r) && strcasecmp($r, $hUser) === 0) {
                    return true;
                }
                if (preg_match('#^https?://#i', $r)) {
                    $hRow = parse_url($r, PHP_URL_HOST);
                    if (is_string($hRow) && $hRow !== '' && strcasecmp($hUser, $hRow) === 0) {
                        return true;
                    }
                }
            }
        }
        $collectHostLike = static function (array $vars): array {
            $h = [];
            foreach ($vars as $v) {
                if ($v === '') {
                    continue;
                }
                if (strpos($v, '://') !== false) {
                    $p = parse_url($v, PHP_URL_HOST);
                    if (is_string($p) && $p !== '') {
                        $h[] = strtolower($p);
                    }
                } else {
                    $h[] = strtolower($v);
                }
            }
            return array_values(array_unique($h));
        };
        foreach ($collectHostLike($userVariants) as $hu) {
            foreach ($collectHostLike($rowVariants) as $hr) {
                if ($hu !== '' && $hr !== '' && strcasecmp($hu, $hr) === 0) {
                    return true;
                }
            }
        }
        return false;
    }
}

if (!function_exists('auragold_login_host_label_from_target_url')) {
    /**
     * First label of the host in a login “IP address / URL” field (e.g. ngp.goldmatrixsoft.com → ngp).
     */
    function auragold_login_host_label_from_target_url(string $raw): string {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $raw)) {
            $raw = 'https://' . $raw;
        }
        $host = parse_url($raw, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            $host = auragold_normalize_branch_host_for_storage(trim((string) preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', trim($raw))));
        }
        $host = strtolower(trim((string) $host));
        if ($host === '' || strpos($host, '.') === false) {
            return $host;
        }
        return strtolower((string) explode('.', $host, 2)[0]);
    }
}

if (!function_exists('auragold_login_branch_option_for_registry_id')) {
    /**
     * One branch dropdown row for a central registry tbl_branches id.
     *
     * @return array{id:int,label:string,db_name:string}
     */
    function auragold_login_branch_option_for_registry_id(int $branchId, ?array $row = null): array {
        $branchId = (int) $branchId;
        if (!$row && function_exists('auragold_registry_tbl_branches_row_by_id')) {
            $row = auragold_registry_tbl_branches_row_by_id($branchId);
        }
        $dbn = function_exists('auragold_login_expected_db_name_for_branch_id')
            ? trim((string) auragold_login_expected_db_name_for_branch_id($branchId))
            : '';
        if ($dbn === '' && $row) {
            $dbn = trim((string) (auragold_branch_row_db_credentials($row)['db_name'] ?? ''));
        }
        if ($dbn === '' && defined('DB_NAME')) {
            $dbn = (string) DB_NAME;
        }
        $nm = $row ? trim((string) ($row['name'] ?? '')) : '';
        if ($nm === '') {
            $nm = 'Branch #' . $branchId;
        }
        return [
            'id'      => $branchId,
            'label'   => $nm,
            'db_name' => $dbn,
        ];
    }
}

if (!function_exists('auragold_login_url_scoped_branch_options')) {
    /**
     * Branch list for URL-scoped login: exactly the registry row tied to the server address field.
     *
     * @return list<array{id:int,label:string,db_name:string}>
     */
    function auragold_login_url_scoped_branch_options(int $registryBranchId, ?array $prefRow = null): array {
        $registryBranchId = (int) $registryBranchId;
        if ($registryBranchId <= 0) {
            return [];
        }
        return [auragold_login_branch_option_for_registry_id($registryBranchId, $prefRow)];
    }
}

if (!function_exists('auragold_registry_branch_id_for_login_target_url')) {
    /**
     * Resolve optional “IP address / URL” field to a central registry tbl_branches id (ip_address / subdomain_url / code).
     * Prefers an exact host match, then branch code = subdomain label (e.g. ngp.* → code NGP), then main rows.
     */
    function auragold_registry_branch_id_for_login_target_url(string $raw): int {
        $raw = trim($raw);
        if ($raw === '') {
            return 0;
        }
        if (function_exists('auragold_ensure_branches_ip_subdomain_columns_on_registry') && function_exists('auragold_registry_mysqli')) {
            $regL = auragold_registry_mysqli();
            if ($regL) {
                auragold_ensure_branches_ip_subdomain_columns_on_registry($regL);
            }
        }
        $uVars = auragold_login_user_url_string_variants_for_match($raw);
        if ($uVars === []) {
            return 0;
        }
        $userHost = '';
        foreach ($uVars as $u) {
            if (preg_match('#^https?://#i', $u)) {
                $h = parse_url($u, PHP_URL_HOST);
                if (is_string($h) && $h !== '') {
                    $userHost = strtolower($h);
                    break;
                }
            }
        }
        if ($userHost === '' && function_exists('auragold_login_host_label_from_target_url')) {
            $labelOnly = auragold_login_host_label_from_target_url($raw);
            if ($labelOnly !== '' && strpos($labelOnly, '.') !== false) {
                $userHost = strtolower($labelOnly);
            } elseif ($labelOnly !== '') {
                $base = defined('AURAGOLD_BRANCH_SUBDOMAIN_BASE_HOST') ? trim((string) AURAGOLD_BRANCH_SUBDOMAIN_BASE_HOST) : '';
                if ($base !== '') {
                    $userHost = strtolower($labelOnly . '.' . $base);
                }
            }
        }
        $hostLabel = function_exists('auragold_login_host_label_from_target_url')
            ? auragold_login_host_label_from_target_url($raw)
            : '';

        $rows = function_exists('auragold_registry_list_tbl_branches_ordered')
            ? auragold_registry_list_tbl_branches_ordered()
            : (function_exists('getListMaster') ? getListMaster('SELECT * FROM tbl_branches ORDER BY IFNULL(main_branch_id, 0) ASC, id ASC') : []);
        if (!is_array($rows) || $rows === []) {
            return 0;
        }
        $candidates = [];
        foreach ($rows as $row) {
            if (empty($row)) {
                continue;
            }
            if (function_exists('auragold_tbl_branch_row_is_active') && !auragold_tbl_branch_row_is_active($row)) {
                continue;
            }
            $rVars     = auragold_login_registry_row_url_string_variants_for_match($row);
            $matchUrl  = ($rVars !== [] && auragold_login_user_and_registry_url_strings_match($uVars, $rVars));
            $matchCode = false;
            if (!$matchUrl && $hostLabel !== '') {
                $code = trim((string) ($row['code'] ?? ''));
                if ($code !== '' && strcasecmp($code, $hostLabel) === 0) {
                    $matchCode = true;
                }
            }
            if (!$matchUrl && !$matchCode) {
                continue;
            }
            $row['_auragold_login_match_url']  = $matchUrl;
            $row['_auragold_login_match_code'] = $matchCode;
            $candidates[] = $row;
        }
        if ($candidates === []) {
            return 0;
        }
        usort(
            $candidates,
            static function (array $a, array $b) use ($userHost): int {
                $exactHost = static function (array $row) use ($userHost): bool {
                    if ($userHost === '') {
                        return false;
                    }
                    foreach (auragold_login_registry_row_url_string_variants_for_match($row) as $r) {
                        if (preg_match('#^https?://#i', $r)) {
                            $h = parse_url($r, PHP_URL_HOST);
                            if (is_string($h) && $h !== '' && strcasecmp($h, $userHost) === 0) {
                                return true;
                            }
                        } elseif (strcasecmp($r, $userHost) === 0) {
                            return true;
                        }
                    }
                    return false;
                };
                $score = static function (array $row) use ($exactHost): int {
                    if ($exactHost($row)) {
                        return 0;
                    }
                    if (!empty($row['_auragold_login_match_code'])) {
                        return 1;
                    }
                    if (!empty($row['_auragold_login_match_url'])) {
                        return 2;
                    }
                    return 3;
                };
                $sa = $score($a);
                $sb = $score($b);
                if ($sa !== $sb) {
                    return $sa <=> $sb;
                }
                $ma = (int) ($a['main_branch_id'] ?? 0) === 0 ? 1 : 0;
                $mb = (int) ($b['main_branch_id'] ?? 0) === 0 ? 1 : 0;
                if ($ma !== $mb) {
                    return $mb <=> $ma;
                }
                return (int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0);
            }
        );
        return (int) ($candidates[0]['id'] ?? 0);
    }
}

if (!function_exists('auragold_discover_branch_logins_for_credentials')) {
    /**
     * For AJAX: find branch dropdown options after password check.
     *
     * @param int  $prefer_branch_id   If &gt; 0 (e.g. portal ?branch_entry=32), verify this branch DB first — fixes login when the same username exists on default DB with mismatched branch_labels.
     * @param string $login_target_url When set and resolvable in registry (ip / subdomain), scope discovery to that row’s operational DB; dropdown lists tbl_branches in that database only, not the full central registry.
     *                                 When set and credentials fail on that database, return failure (no fallback to the central superadmin “all shops” list).
     *
     * @return array{success:bool,message?:string,is_superadmin?:bool,branches?:list<array{id:int,label:string,db_name:string}>}
     */
    function auragold_discover_branch_logins_for_credentials(
        string $username_raw,
        string $password_plain,
        int $prefer_branch_id = 0,
        string $login_target_url = ''
    ): array {
        $username_raw = trim($username_raw);
        $password_plain = trim((string) $password_plain);
        if ($username_raw === '' || $password_plain === '') {
            return ['success' => false, 'message' => 'Invalid username or password'];
        }

        if (!function_exists('auragold_verify_user_on_mysqli') || !function_exists('auragold_open_mysqli_for_login_branch_id')) {
            return ['success' => false, 'message' => 'Login helpers not loaded'];
        }

        $login_target_url = trim($login_target_url);
        if (strlen($login_target_url) > 500) {
            $login_target_url = '';
        }

        require_once __DIR__ . '/auragold_superadmin.php';

        $url_scope_id = 0;
        if ($login_target_url !== '' && function_exists('auragold_registry_branch_id_for_login_target_url')) {
            $url_scope_id = auragold_registry_branch_id_for_login_target_url($login_target_url);
        }

        $prefer_in      = (int) $prefer_branch_id;
        $prefer_branch_id = $url_scope_id > 0 ? $url_scope_id : $prefer_in;
        $url_gated         = $url_scope_id > 0;

        if ($login_target_url !== '' && $url_scope_id <= 0) {
            $localDev = defined('AURAGOLD_PROJECT') && (string) AURAGOLD_PROJECT === 'local';
            if (!$localDev) {
                return [
                    'success' => false,
                    'message' => 'This server address is not assigned to any branch. Set subdomain URL or IP in Branch settings.',
                ];
            }
        }

        // 0) Portal / deep link: verify chosen branch first (tbl_users often only in that schema, e.g. auragold_main_branch)
        if ($prefer_branch_id > 0) {
            $prefRow = function_exists('auragold_registry_tbl_branches_row_by_id')
                ? auragold_registry_tbl_branches_row_by_id($prefer_branch_id)
                : (function_exists('getRecordMaster') ? getRecordMaster('SELECT id, name, db_name, db_users, db_password FROM tbl_branches WHERE id = ' . $prefer_branch_id . ' LIMIT 1') : null);
            if ($prefRow) {
                [$lnP, $clP] = auragold_open_mysqli_for_login_branch_id($prefer_branch_id);
                if ($lnP) {
                    $uPref = auragold_verify_user_on_mysqli($lnP, $username_raw, $password_plain);
                    if ($clP) {
                        mysqli_close($lnP);
                    }
                    if ($uPref) {
                        if ($url_gated && function_exists('auragold_login_url_scoped_branch_options')) {
                            $isSa = function_exists('auragold_user_row_is_superadmin') && auragold_user_row_is_superadmin($uPref);
                            return [
                                'success'       => true,
                                'is_superadmin' => $isSa,
                                'branches'      => auragold_login_url_scoped_branch_options($prefer_branch_id, $prefRow),
                            ];
                        }

                        $portalBranches = function_exists('auragold_login_branch_options_from_operational_tbl_branches')
                            ? auragold_login_branch_options_from_operational_tbl_branches($prefer_branch_id)
                            : [];
                        $usePortal = is_array($portalBranches) && $portalBranches !== [];

                        if (function_exists('auragold_user_row_is_superadmin') && auragold_user_row_is_superadmin($uPref)) {
                            if ($usePortal) {
                                return [
                                    'success'       => true,
                                    'is_superadmin' => true,
                                    'branches'      => $portalBranches,
                                ];
                            }
                            if ($url_gated) {
                                $nm  = trim((string) ($prefRow['name'] ?? ''));
                                if ($nm === '') {
                                    $nm = 'Branch #' . (int) $prefer_branch_id;
                                }
                                return [
                                    'success'       => true,
                                    'is_superadmin' => true,
                                    'branches'      => auragold_login_branch_options_add_db_name(
                                        [
                                            [
                                                'id'    => (int) $prefer_branch_id,
                                                'label' => $nm,
                                            ],
                                        ]
                                    ),
                                ];
                            }
                            if (!auragold_superadmin_allowed_login_portal_ok($login_target_url)) {
                                return [
                                    'success' => false,
                                    'message' => 'Superadmin sign-in must use the main GoldMatrix portal URL (main.goldmatrixsoft.com) or the GM portal URL (gm.goldmatrixsoft.com) in the server address field.',
                                ];
                            }
                            return [
                                'success'       => true,
                                'is_superadmin' => true,
                                'branches'      => auragold_superadmin_discovery_branches_for_login_target($login_target_url, $url_scope_id),
                            ];
                        }

                        if ($usePortal && function_exists('auragold_login_filter_branch_options_to_user_scope')) {
                            $scoped = auragold_login_filter_branch_options_to_user_scope($portalBranches, $uPref);
                            if ($scoped !== []) {
                                return [
                                    'success'       => true,
                                    'is_superadmin' => false,
                                    'branches'      => $scoped,
                                ];
                            }
                        }

                        if (function_exists('auragold_login_build_branch_options_for_user')
                            && function_exists('auragold_login_branch_options_add_db_name')) {
                            $opts = auragold_login_build_branch_options_for_user($uPref);
                            if ($opts !== []) {
                                return [
                                    'success'       => true,
                                    'is_superadmin' => false,
                                    'branches'      => auragold_login_branch_options_add_db_name($opts),
                                ];
                            }
                        }

                        if ($usePortal) {
                            return [
                                'success'       => true,
                                'is_superadmin' => false,
                                'branches'      => $portalBranches,
                            ];
                        }

                        $dbn = function_exists('auragold_login_expected_db_name_for_branch_id')
                            ? auragold_login_expected_db_name_for_branch_id($prefer_branch_id)
                            : trim((string) (auragold_branch_row_db_credentials($prefRow)['db_name'] ?? ''));
                        if ($dbn === '') {
                            $dbn = defined('DB_NAME') ? (string) DB_NAME : '';
                        }
                        $nm = trim((string) ($prefRow['name'] ?? ''));
                        if ($nm === '') {
                            $nm = 'Branch #' . $prefer_branch_id;
                        }
                        return [
                            'success'       => true,
                            'is_superadmin' => false,
                            'branches'      => [
                                [
                                    'id'      => $prefer_branch_id,
                                    'label'   => $nm,
                                    'db_name' => $dbn,
                                ],
                            ],
                        ];
                    }
                }
            }
        }

        if ($url_gated) {
            $localDev = defined('AURAGOLD_PROJECT') && (string) AURAGOLD_PROJECT === 'local';
            if (!$localDev) {
                return ['success' => false, 'message' => 'Invalid username or password'];
            }
            // Local: a production URL (e.g. gm.*) often resolves to tbl_branches row whose db_name
            // is not the developer’s registry copy — tbl_users may live in AURAGOLD_REGISTRY_DB only.
            // Fall through and try default DB + branch scan like an unscoped login.
        }

        if ($login_target_url !== '' && !(defined('AURAGOLD_PROJECT') && (string) AURAGOLD_PROJECT === 'local')) {
            return ['success' => false, 'message' => 'Invalid username or password'];
        }

        // 1) Default application database (same as DB_NAME — “Main” / first bootstrap schema)
        [$link0, $close0] = auragold_open_mysqli_for_login_branch_id(0);
        $user0            = null;
        if ($link0) {
            $user0 = auragold_verify_user_on_mysqli($link0, $username_raw, $password_plain);
            if ($close0) {
                mysqli_close($link0);
            }
        }

        if (!$user0 && defined('AURAGOLD_PROJECT') && (string) AURAGOLD_PROJECT === 'local') {
            $regL = function_exists('auragold_registry_mysqli') ? auragold_registry_mysqli() : null;
            if ($regL instanceof mysqli) {
                // Bootstrap (registry) connection: branch DB_USER from tbl_branches may not see tbl_users,
                // or DB_NAME may differ from where developers loaded data in phpMyAdmin (AURAGOLD_REGISTRY_DB).
                $user0 = auragold_verify_user_on_mysqli($regL, $username_raw, $password_plain);
            }
        }

        if ($user0) {
            if (function_exists('auragold_user_row_is_superadmin') && auragold_user_row_is_superadmin($user0)) {
                if (!auragold_superadmin_allowed_login_portal_ok($login_target_url)) {
                    return [
                        'success' => false,
                        'message' => 'Superadmin sign-in must use the main GoldMatrix portal URL (main.goldmatrixsoft.com) or the GM portal URL (gm.goldmatrixsoft.com) in the server address field.',
                    ];
                }
                return [
                    'success'       => true,
                    'is_superadmin' => true,
                    'branches'      => auragold_superadmin_discovery_branches_for_login_target($login_target_url, $url_scope_id),
                ];
            }
            if (!function_exists('auragold_login_build_branch_options_for_user')) {
                return ['success' => false, 'message' => 'Configuration error'];
            }
            $opts = auragold_login_build_branch_options_for_user($user0);
            if ($opts !== []) {
                return [
                    'success'       => true,
                    'is_superadmin' => false,
                    'branches'      => auragold_login_branch_options_add_db_name($opts),
                ];
            }
            // branch_labels on default tbl_users do not match registry names — fall through and scan branch DBs
        }

        // 2) Scan every dedicated branch schema in the registry
        $rows = function_exists('auragold_registry_list_tbl_branches_with_db_name')
            ? auragold_registry_list_tbl_branches_with_db_name()
            : (function_exists('getListMaster') ? getListMaster(
                'SELECT id, name, db_name, db_users, db_password FROM tbl_branches '
                . "WHERE TRIM(IFNULL(db_name,'')) <> '' ORDER BY id ASC"
            ) : []);
        if (!is_array($rows)) {
            $rows = [];
        }
        if (!is_array($rows) || $rows === []) {
            return ['success' => false, 'message' => 'Invalid username or password'];
        }

        $found = [];
        foreach ($rows as $r) {
            $bid = (int) ($r['id'] ?? 0);
            if ($bid <= 0) {
                continue;
            }
            $dbn = trim((string) ($r['db_name'] ?? ''));
            if ($dbn === '') {
                continue;
            }
            [$lnk, $cls] = auragold_open_mysqli_for_login_branch_id($bid);
            if (!$lnk) {
                continue;
            }
            $urow = auragold_verify_user_on_mysqli($lnk, $username_raw, $password_plain);
            if ($cls) {
                mysqli_close($lnk);
            }
            if (!$urow) {
                continue;
            }
            if (function_exists('auragold_user_row_is_superadmin') && auragold_user_row_is_superadmin($urow)) {
                if (!auragold_superadmin_allowed_login_portal_ok($login_target_url)) {
                    return [
                        'success' => false,
                        'message' => 'Superadmin sign-in must use the main GoldMatrix portal URL (main.goldmatrixsoft.com) or the GM portal URL (gm.goldmatrixsoft.com) in the server address field.',
                    ];
                }
                return [
                    'success'       => true,
                    'is_superadmin' => true,
                    'branches'      => auragold_superadmin_discovery_branches_for_login_target($login_target_url, $url_scope_id),
                ];
            }
            $nm = trim((string) ($r['name'] ?? ''));
            if ($nm === '') {
                $nm = 'Branch #' . $bid;
            }
            $found[] = [
                'id'      => $bid,
                'label'   => $nm,
                'db_name' => $dbn,
            ];
        }

        if ($found === []) {
            return ['success' => false, 'message' => 'Invalid username or password'];
        }

        return [
            'success'       => true,
            'is_superadmin' => false,
            'branches'      => $found,
        ];
    }
}
