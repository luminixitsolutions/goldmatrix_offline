<?php
/**
 * Apply $_SESSION working_* from tbl_branches (after login or switch).
 * working_db stores database, user, password (and db_name/db_user/db_pass keys); config.php switches $conn when set.
 * Requires config.php (getRecordMaster, DB_*) and branch_credentials.php.
 */
require_once __DIR__ . '/branch_credentials.php';
require_once __DIR__ . '/subdomain_branch.php';
require_once __DIR__ . '/branch_create_db_after_save.php';

/**
 * For tbl_branches logins: registry main row id this account belongs to (main row or parent main of a sub).
 * 0 = not a branch credential session or unknown.
 */
function auragold_branch_login_scope_main_id() {
    $login_source = isset($_SESSION['login_source']) ? (string) $_SESSION['login_source'] : '';
    if ($login_source !== 'branch') {
        return 0;
    }
    $acc_bid = (int) ($_SESSION['Admin']['id'] ?? 0);
    if ($acc_bid <= 0) {
        return 0;
    }
    $me = getRecordMaster('SELECT id, main_branch_id FROM tbl_branches WHERE id = ' . $acc_bid . ' LIMIT 1');
    if (!$me) {
        return 0;
    }
    $mid = (int) ($me['main_branch_id'] ?? 0);
    return $mid === 0 ? (int) $me['id'] : $mid;
}

function auragold_can_user_open_branch_row(array $row) {
    $login_source = isset($_SESSION['login_source']) ? (string) $_SESSION['login_source'] : '';
    $row_main_id  = (int) ($row['main_branch_id'] ?? 0);
    $row_id       = (int) ($row['id'] ?? 0);

    if ($login_source !== 'branch') {
        return true;
    }

    $account_bid = (int) ($_SESSION['Admin']['id'] ?? 0);
    if ($account_bid <= 0) {
        return true;
    }

    $acc = getRecordMaster('SELECT id, main_branch_id FROM tbl_branches WHERE id = ' . $account_bid . ' LIMIT 1');
    if (!$acc) {
        return true;
    }

    $acc_main          = (int) ($acc['main_branch_id'] ?? 0);
    $acc_is_main_row   = ($acc_main === 0);
    $acc_scope_main_id = $acc_is_main_row ? $account_bid : $acc_main;

    if ($row_main_id === 0) {
        return $row_id === $acc_scope_main_id;
    }
    return $row_main_id === $acc_scope_main_id;
}

/**
 * @param int $branchRowId 0 = clear working context (use registry / DB_NAME only)
 * @return array{ok:bool,message:string}
 */
function auragold_apply_branch_working_context($branchRowId) {
    $branchRowId = (int) $branchRowId;
    if ($branchRowId <= 0) {
        unset($_SESSION['working_db'], $_SESSION['working_branch_id'], $_SESSION['working_branch_name']);
        $_SESSION['db_name'] = defined('DB_NAME') ? (string) DB_NAME : '';
        return ['ok' => true, 'message' => ''];
    }

    $row = getRecordMaster('SELECT * FROM tbl_branches WHERE id = ' . $branchRowId . ' LIMIT 1');
    if (!$row) {
        return ['ok' => false, 'message' => 'Branch not found. You are signed in using the main database.'];
    }
    if (!auragold_can_user_open_branch_row($row)) {
        return ['ok' => false, 'message' => 'You cannot open that branch with this account. You are signed in using the main database.'];
    }

    $row_id = (int) ($row['id'] ?? 0);
    $name   = trim((string) ($row['name'] ?? ''));
    if ($name === '') {
        $name = 'Branch #' . $row_id;
    }

    global $conn_master;
    $registry = ($conn_master instanceof mysqli) ? $conn_master : null;
    $creds    = $registry
        ? auragold_resolve_branch_operational_credentials($row, $registry)
        : auragold_branch_row_db_credentials($row);
    $db_name = $creds['db_name'];
    $db_user = $creds['db_user'];
    $db_pass = $creds['db_pass'];

    if ($db_name === '') {
        unset($_SESSION['working_db']);
        $_SESSION['working_branch_id']   = $row_id;
        $_SESSION['working_branch_name'] = $name;
        $_SESSION['db_name']             = defined('DB_NAME') ? (string) DB_NAME : '';
        return ['ok' => true, 'message' => ''];
    }

    $_SESSION['working_branch_id']   = $row_id;
    $_SESSION['working_branch_name'] = $name;

    $test = auragold_mysqli_connect_branch_or_registry(DB_HOST, $db_name, $db_user, $db_pass);
    if (!$test) {
        global $conn;
        if (function_exists('auragold_recover_branch_session_from_live_conn')) {
            $recovered = auragold_recover_branch_session_from_live_conn($row_id, $name);
            if (!empty($recovered['ok'])) {
                return $recovered;
            }
        }
        unset($_SESSION['working_db'], $_SESSION['working_branch_id'], $_SESSION['working_branch_name'], $_SESSION['db_name']);
        $err = mysqli_connect_error();
        $hint = (stripos((string) $err, 'Unknown database') !== false)
            ? 'Create the database (e.g. “Create DB & tables” on Branches) or pick Main on login.'
            : 'Check db_users / db_password for this branch.';
        return [
            'ok'      => false,
            'message' => 'Could not open branch database “' . $db_name . '”. ' . $hint . ' You are signed in using the main database.',
        ];
    }
    mysqli_close($test);

    // Production cPanel: branch schemas use tbl_branches db_users — never store registry DB_USER when a dedicated user exists.
    $useDedicatedBranchUser = (defined('AURAGOLD_PROJECT') && (string) AURAGOLD_PROJECT === 'prod' && trim((string) $db_user) !== '');

    $sessionUser = (string) DB_USER;
    $sessionPass = (string) DB_PASS;
    if ($useDedicatedBranchUser) {
        $sessionUser = $db_user;
        $sessionPass = $db_pass;
    } else {
        // Session must store credentials that actually work (main/registry account when per-branch MySQL user was never created).
        // With PHP 8.1+ mysqli, connect failures can throw (mysqli_sql_exception). @ no longer swallows that—probe with try/catch.
        $regProbe = null;
        if (function_exists('auragold_mysqli_connect_operational') && (string) $db_name !== '') {
            $regProbe = auragold_mysqli_connect_operational(
                (string) DB_HOST,
                $sessionUser,
                $sessionPass,
                (string) $db_name
            );
        } else {
            try {
                $regProbe = @mysqli_connect((string) DB_HOST, $sessionUser, $sessionPass, (string) $db_name);
            } catch (Throwable $e) {
                $regProbe = null;
            }
        }
        if ($regProbe) {
            mysqli_close($regProbe);
        } else {
            $sessionUser = $db_user !== '' ? $db_user : (string) DB_USER;
            $sessionPass = $db_user !== '' ? $db_pass : (string) DB_PASS;
        }
    }

    $_SESSION['working_db'] = [
        'database' => $db_name,
        'user'     => $sessionUser,
        'password' => $sessionPass,
        'db_name'  => $db_name,
        'db_user'  => $sessionUser,
        'db_pass'  => $sessionPass,
    ];
    $_SESSION['db_name'] = $db_name;

    return ['ok' => true, 'message' => ''];
}

/**
 * Operational MySQL schema name from an active mysqli link (usually global $conn).
 */
if (!function_exists('auragold_operational_db_name_from_conn')) {
    function auragold_operational_db_name_from_conn($link = null): string {
        if (!$link instanceof mysqli) {
            global $conn;
            $link = (isset($conn) && $conn instanceof mysqli) ? $conn : null;
        }
        if (!$link instanceof mysqli) {
            return '';
        }
        $rd = @mysqli_query($link, 'SELECT DATABASE() AS d');
        if (!$rd) {
            return '';
        }
        $rw = mysqli_fetch_assoc($rd);
        mysqli_free_result($rd);
        return trim((string) ($rw['d'] ?? ''));
    }
}

/**
 * Write $_SESSION['working_db'] from the database $conn is already using (no credential re-probe).
 */
if (!function_exists('auragold_patch_session_working_db')) {
    function auragold_patch_session_working_db(string $database, string $user = '', string $password = ''): void {
        $database = trim($database);
        if ($database === '' || session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        if ($user === '') {
            $user = defined('DB_USER') ? (string) DB_USER : '';
        }
        if ($password === '' && !empty($_SESSION['working_db']) && is_array($_SESSION['working_db'])) {
            $password = (string) ($_SESSION['working_db']['password'] ?? $_SESSION['working_db']['db_pass'] ?? '');
        }
        if ($password === '') {
            $password = defined('DB_PASS') ? (string) DB_PASS : '';
        }
        $_SESSION['working_db'] = [
            'database' => $database,
            'user'     => $user,
            'password' => $password,
            'db_name'  => $database,
            'db_user'  => $user,
            'db_pass'  => $password,
        ];
        $_SESSION['db_name'] = $database;
    }
}

/**
 * When registry credentials fail but global $conn is already on a branch schema, keep the session.
 *
 * @return array{ok:bool,message:string}
 */
if (!function_exists('auragold_recover_branch_session_from_live_conn')) {
    function auragold_recover_branch_session_from_live_conn(int $fallbackBranchId = 0, string $fallbackName = ''): array {
        global $conn;
        if (!($conn instanceof mysqli)) {
            return ['ok' => false, 'message' => ''];
        }
        $connDb = auragold_operational_db_name_from_conn($conn);
        if ($connDb === '') {
            return ['ok' => false, 'message' => ''];
        }
        if (!function_exists('auragold_registry_tbl_branches_row_by_db_name')) {
            require_once __DIR__ . '/branch_credentials.php';
        }
        $row = function_exists('auragold_registry_tbl_branches_row_by_db_name')
            ? auragold_registry_tbl_branches_row_by_db_name($connDb)
            : null;
        if (!$row && $fallbackBranchId > 0 && function_exists('auragold_registry_tbl_branches_row_by_id')) {
            $row = auragold_registry_tbl_branches_row_by_id($fallbackBranchId);
        }
        $rowId = is_array($row) ? (int) ($row['id'] ?? 0) : 0;
        $rowName = is_array($row) ? trim((string) ($row['name'] ?? '')) : '';
        if ($rowName === '' && $fallbackName !== '') {
            $rowName = $fallbackName;
        }
        if ($rowId <= 0 && $fallbackBranchId > 0) {
            $rowId = $fallbackBranchId;
        }
        if ($rowId <= 0) {
            return ['ok' => false, 'message' => ''];
        }
        if ($rowName === '') {
            $rowName = 'Branch #' . $rowId;
        }
        auragold_patch_session_working_db($connDb);
        $_SESSION['working_branch_id']   = $rowId;
        $_SESSION['working_branch_name'] = $rowName;
        if ((int) ($_SESSION['branch_id'] ?? 0) <= 0) {
            $_SESSION['branch_id'] = $rowId;
        }
        if (!empty($row) && is_array($row)) {
            $GLOBALS['auragold_active_branch_row'] = $row;
        }
        return ['ok' => true, 'message' => ''];
    }
}

/**
 * Clear per-request branch caches so header can re-resolve from live $conn.
 */
if (!function_exists('auragold_reset_branch_context_caches')) {
    function auragold_reset_branch_context_caches(): void {
        unset($GLOBALS['auragold_active_branch_row']);
        if (function_exists('auragold_bind_conn_to_working_session')) {
            auragold_bind_conn_to_working_session();
        }
        if (function_exists('auragold_hydrate_session_branch_display_from_registry')) {
            auragold_hydrate_session_branch_display_from_registry(true);
        }
        if (function_exists('auragold_hydrate_session_financial_year_from_conn')) {
            auragold_hydrate_session_financial_year_from_conn();
        }
    }
}

/**
 * Active operational DB for this request (source of truth for branch identity).
 * Priority: DATABASE() on bound $conn → session working_db → session db_name → DB_NAME.
 */
if (!function_exists('auragold_resolve_active_operational_db_name')) {
    function auragold_resolve_active_operational_db_name(): string {
        if (function_exists('auragold_bind_conn_to_working_session')) {
            auragold_bind_conn_to_working_session();
        }

        $db = auragold_operational_db_name_from_conn();
        if ($db === '' && !empty($_SESSION['working_db']) && is_array($_SESSION['working_db'])) {
            $db = trim((string) ($_SESSION['working_db']['database'] ?? $_SESSION['working_db']['db_name'] ?? ''));
        }
        if ($db === '' && trim((string) ($_SESSION['db_name'] ?? '')) !== '') {
            $db = trim((string) $_SESSION['db_name']);
        }
        if ($db === '' && defined('DB_NAME')) {
            $db = trim((string) DB_NAME);
        }

        return $db;
    }
}

/**
 * Canonical tbl_branches row for the active operational database / session branch ids.
 */
if (!function_exists('auragold_resolve_active_branch_row')) {
    function auragold_resolve_active_branch_row(): ?array {
        if (!empty($GLOBALS['auragold_active_branch_row']) && is_array($GLOBALS['auragold_active_branch_row'])) {
            return $GLOBALS['auragold_active_branch_row'];
        }
        if (function_exists('auragold_hydrate_session_branch_display_from_registry')) {
            auragold_hydrate_session_branch_display_from_registry();
        }
        if (!empty($GLOBALS['auragold_active_branch_row']) && is_array($GLOBALS['auragold_active_branch_row'])) {
            return $GLOBALS['auragold_active_branch_row'];
        }
        return null;
    }
}

/**
 * Validate and synchronize $_SESSION branch identity with the active operational DB.
 * Never keeps a stale working_branch_name just because it is non-empty.
 */
if (!function_exists('auragold_hydrate_session_branch_display_from_registry')) {
    function auragold_hydrate_session_branch_display_from_registry(bool $force = false): void {
        static $hydrated = false;
        if ($hydrated && !$force) {
            return;
        }
        $hydrated = true;

        if (session_status() !== PHP_SESSION_ACTIVE || (int) ($_SESSION['user_id'] ?? 0) <= 0) {
            $GLOBALS['auragold_active_branch_row'] = null;
            return;
        }

        if (!function_exists('auragold_registry_tbl_branches_row_by_db_name')) {
            require_once __DIR__ . '/branch_credentials.php';
        }

        if (function_exists('auragold_bind_conn_to_working_session')) {
            auragold_bind_conn_to_working_session();
        }

        $explicitBranch = (int) ($_SESSION['auragold_login_branch_id'] ?? 0);
        if ($explicitBranch <= 0) {
            $explicitBranch = (int) ($_SESSION['working_branch_id'] ?? 0);
        }

        $activeDb = auragold_operational_db_name_from_conn();
        if ($activeDb === '' && !empty($_SESSION['working_db']) && is_array($_SESSION['working_db'])) {
            $activeDb = trim((string) ($_SESSION['working_db']['database'] ?? $_SESSION['working_db']['db_name'] ?? ''));
        }
        if ($activeDb === '' && trim((string) ($_SESSION['db_name'] ?? '')) !== '') {
            $activeDb = trim((string) $_SESSION['db_name']);
        }

        $row = null;
        if ($explicitBranch > 0 && function_exists('auragold_registry_tbl_branches_row_by_id')) {
            $row = auragold_registry_tbl_branches_row_by_id($explicitBranch);
        }
        if (!$row && $activeDb !== '' && function_exists('auragold_registry_tbl_branches_row_by_db_name')) {
            $row = auragold_registry_tbl_branches_row_by_db_name($activeDb);
        }
        if (!$row) {
            $bid = (int) ($_SESSION['working_branch_id'] ?? 0);
            if ($bid <= 0) {
                $bid = (int) ($_SESSION['branch_id'] ?? 0);
            }
            if ($bid <= 0) {
                $bid = (int) ($_SESSION['auragold_login_branch_id'] ?? 0);
            }
            if ($bid > 0 && function_exists('auragold_registry_tbl_branches_row_by_id')) {
                $row = auragold_registry_tbl_branches_row_by_id($bid);
            }
        }

        if (!is_array($row) || (int) ($row['id'] ?? 0) <= 0) {
            if (function_exists('auragold_recover_branch_session_from_live_conn')) {
                $recovered = auragold_recover_branch_session_from_live_conn(
                    (int) ($_SESSION['working_branch_id'] ?? $_SESSION['branch_id'] ?? 0),
                    trim((string) ($_SESSION['working_branch_name'] ?? ''))
                );
                if (!empty($recovered['ok']) && !empty($GLOBALS['auragold_active_branch_row'])) {
                    return;
                }
            }
            $GLOBALS['auragold_active_branch_row'] = null;
            return;
        }

        $prevWorkingId   = (int) ($_SESSION['working_branch_id'] ?? 0);
        $prevWorkingName = trim((string) ($_SESSION['working_branch_name'] ?? ''));
        $prevBranchId    = (int) ($_SESSION['branch_id'] ?? 0);

        $rowId   = (int) ($row['id'] ?? 0);
        $rowName = trim((string) ($row['name'] ?? ''));
        if ($rowName === '') {
            $rowName = 'Branch #' . $rowId;
        }
        $rowDb = trim((string) ($row['db_name'] ?? ''));
        if ($activeDb === '' && $rowDb !== '') {
            $activeDb = $rowDb;
        }

        $_SESSION['working_branch_id']   = $rowId;
        $_SESSION['working_branch_name'] = $rowName;
        if ($activeDb !== '') {
            $_SESSION['db_name'] = $activeDb;
        }

        $connDb = auragold_operational_db_name_from_conn();
        $targetDb = $connDb !== '' ? $connDb : $activeDb;

        if ($targetDb !== '' && $connDb !== '' && strcasecmp($connDb, $targetDb) === 0) {
            auragold_patch_session_working_db($targetDb);
        } else {
            $sessionWorkingDb = '';
            if (!empty($_SESSION['working_db']) && is_array($_SESSION['working_db'])) {
                $sessionWorkingDb = trim((string) ($_SESSION['working_db']['database'] ?? $_SESSION['working_db']['db_name'] ?? ''));
            }
            $needsWorkingDbRebuild = ($sessionWorkingDb === '' || ($targetDb !== '' && strcasecmp($sessionWorkingDb, $targetDb) !== 0));
            if ($needsWorkingDbRebuild && function_exists('auragold_apply_branch_working_context')) {
                $apply = auragold_apply_branch_working_context($rowId);
                if (empty($apply['ok']) && function_exists('auragold_recover_branch_session_from_live_conn')) {
                    auragold_recover_branch_session_from_live_conn($rowId, $rowName);
                } elseif (function_exists('auragold_bind_conn_to_working_session')) {
                    auragold_bind_conn_to_working_session();
                }
            } elseif ($targetDb !== '' && !empty($_SESSION['working_db']) && is_array($_SESSION['working_db'])) {
                $_SESSION['working_db']['database'] = $targetDb;
                $_SESSION['working_db']['db_name']  = $targetDb;
            } elseif ((empty($_SESSION['working_db']) || !is_array($_SESSION['working_db'])) && $targetDb !== '') {
                auragold_patch_session_working_db($targetDb);
            }
        }

        if ($prevBranchId <= 0 || $prevBranchId === $prevWorkingId || strcasecmp($prevWorkingName, $rowName) !== 0) {
            $_SESSION['branch_id'] = $rowId;
        }

        $GLOBALS['auragold_active_branch_row'] = $row;
    }
}

/**
 * Shared voucher-page bootstrap: restore working DB, bind $conn, sync branch + FY.
 */
if (!function_exists('auragold_voucher_page_init')) {
    function auragold_voucher_page_init(): void {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;
        if (function_exists('auragold_ensure_operational_session_context')) {
            auragold_ensure_operational_session_context();
        }
    }
}

/**
 * Load active FY into session from the operational DB when the header pill would otherwise be empty
 * (common for superadmin logins that skip POST financial_year_id at sign-in).
 */
if (!function_exists('auragold_hydrate_session_financial_year_from_conn')) {
    function auragold_hydrate_session_financial_year_from_conn(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $fyHelper = __DIR__ . '/login_financial_years_helper.php';
        if (!is_file($fyHelper)) {
            return;
        }
        require_once $fyHelper;
        if (function_exists('auragold_session_financial_year_short_label')
            && auragold_session_financial_year_short_label() !== '') {
            return;
        }

        $links = [];
        $seen  = [];
        $push  = static function (?mysqli $link, bool $close) use (&$links, &$seen): void {
            if (!$link instanceof mysqli) {
                return;
            }
            $key = spl_object_hash($link);
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $links[] = [$link, $close];
        };

        global $conn;
        $push(isset($conn) && $conn instanceof mysqli ? $conn : null, false);

        if (function_exists('auragold_login_open_mysqli_for_working_session')) {
            [$wconn, $wclose] = auragold_login_open_mysqli_for_working_session();
            $push($wconn instanceof mysqli ? $wconn : null, (bool) $wclose);
        }

        if (!function_exists('auragold_fetch_active_financial_year_row_from_link')) {
            return;
        }

        foreach ($links as [$link, $closeLink]) {
            $t = @mysqli_query($link, "SHOW TABLES LIKE 'tbl_accounting_financial_years'");
            if (!$t || mysqli_num_rows($t) === 0) {
                if ($t) {
                    mysqli_free_result($t);
                }
                if ($closeLink) {
                    @mysqli_close($link);
                }
                continue;
            }
            mysqli_free_result($t);

            $row = auragold_fetch_active_financial_year_row_from_link($link);
            if (is_array($row) && !empty($row['id']) && function_exists('auragold_store_financial_year_in_session')) {
                auragold_store_financial_year_in_session($row);
                if ($closeLink) {
                    @mysqli_close($link);
                }
                return;
            }
            if ($closeLink) {
                @mysqli_close($link);
            }
        }
    }
}

/**
 * Restore working_db + $conn + header labels (branch name, FY) for voucher pages after config.php.
 */
if (!function_exists('auragold_ensure_operational_session_context')) {
    function auragold_ensure_operational_session_context(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        if ((int) ($_SESSION['user_id'] ?? 0) <= 0) {
            return;
        }

        if (function_exists('auragold_bind_conn_to_working_session')) {
            auragold_bind_conn_to_working_session();
        }
        if (function_exists('auragold_hydrate_session_branch_display_from_registry')) {
            auragold_hydrate_session_branch_display_from_registry();
        }

        if ((empty($_SESSION['working_db']) || !is_array($_SESSION['working_db']))
            && function_exists('auragold_apply_branch_working_context')) {
            $bid = (int) ($_SESSION['working_branch_id'] ?? 0);
            if ($bid <= 0) {
                $bid = (int) ($_SESSION['branch_id'] ?? 0);
            }
            if ($bid <= 0) {
                $bid = (int) ($_SESSION['auragold_login_branch_id'] ?? 0);
            }
            if ($bid > 0) {
                auragold_apply_branch_working_context($bid);
            }
            if (function_exists('auragold_bind_conn_to_working_session')) {
                auragold_bind_conn_to_working_session();
            }
            if (function_exists('auragold_hydrate_session_branch_display_from_registry')) {
                auragold_hydrate_session_branch_display_from_registry(true);
            }
        }

        if (function_exists('auragold_hydrate_session_financial_year_from_conn')) {
            auragold_hydrate_session_financial_year_from_conn();
        }
    }
}

/**
 * Working branch id for master lists (tax, metal, location) on voucher pages.
 */
if (!function_exists('auragold_resolve_working_branch_id_for_page')) {
    function auragold_resolve_working_branch_id_for_page(): int {
        static $cached = null;
        if ($cached !== null) {
            return (int) $cached;
        }

        if (function_exists('auragold_resolve_active_branch_row')) {
            $row = auragold_resolve_active_branch_row();
            if (is_array($row) && (int) ($row['id'] ?? 0) > 0) {
                $cached = (int) $row['id'];
                return (int) $cached;
            }
        }

        $id = function_exists('auragold_effective_branch_id') ? (int) auragold_effective_branch_id() : 0;
        if ($id <= 0) {
            $id = (int) ($_SESSION['working_branch_id'] ?? 0);
        }
        if ($id <= 0) {
            $id = (int) ($_SESSION['branch_id'] ?? 0);
        }
        if ($id > 0 && function_exists('auragold_normalize_branch_scope_for_working_db')) {
            $id = (int) auragold_normalize_branch_scope_for_working_db($id);
        }
        $cached = $id > 0 ? $id : 0;
        return (int) $cached;
    }
}

/**
 * Branch-scoped SQL suffix for master tables on the current voucher page.
 */
if (!function_exists('auragold_master_list_sql_for_working_branch')) {
    function auragold_master_list_sql_for_working_branch($conn, string $table, int $branchId = 0): string {
        if ($branchId <= 0 && function_exists('auragold_resolve_working_branch_id_for_page')) {
            $branchId = auragold_resolve_working_branch_id_for_page();
        }
        if ($branchId > 0 && function_exists('auragold_master_list_sql_for_branch_id')) {
            return auragold_master_list_sql_for_branch_id($conn, $table, $branchId);
        }
        if (function_exists('auragold_master_list_sql_suffix')) {
            return auragold_master_list_sql_suffix($conn, $table);
        }
        return '';
    }
}

/**
 * Rebind global $conn to $_SESSION['working_db'] in the current request.
 * config.php only switches $conn on a new request; login_submit must call this
 * after auragold_apply_branch_working_context() before reading tbl_users prefs.
 *
 * @return bool true when $conn now matches working_db (or already did)
 */
function auragold_bind_conn_to_working_session(): bool
{
    global $conn, $conn_master;

    if (empty($_SESSION['working_db']) || !is_array($_SESSION['working_db'])) {
        return isset($conn) && $conn instanceof mysqli;
    }

    $wdb    = $_SESSION['working_db'];
    $dbname = trim((string) ($wdb['database'] ?? $wdb['db_name'] ?? ''));
    if ($dbname === '' || !defined('DB_HOST')) {
        return isset($conn) && $conn instanceof mysqli;
    }

    $dbuser = trim((string) ($wdb['user'] ?? $wdb['db_user'] ?? $wdb['db_users'] ?? ''));
    $dbpass = (string) ($wdb['password'] ?? $wdb['db_pass'] ?? $wdb['db_password'] ?? '');
    if ($dbuser === '') {
        $dbuser = defined('DB_USER') ? (string) DB_USER : '';
        $dbpass = defined('DB_PASS') ? (string) DB_PASS : '';
    }

    $cur = '';
    if (isset($conn) && $conn instanceof mysqli) {
        $dbRes = @mysqli_query($conn, 'SELECT DATABASE() AS d');
        if ($dbRes && ($dbRow = mysqli_fetch_assoc($dbRes))) {
            $cur = trim((string) ($dbRow['d'] ?? ''));
        }
        if ($dbRes) {
            mysqli_free_result($dbRes);
        }
        if ($cur !== '' && strcasecmp($cur, $dbname) === 0) {
            if (function_exists('auragold_apply_mysql_session_timezone') && function_exists('auragold_branch_timezone')) {
                auragold_apply_mysql_session_timezone($conn, auragold_branch_timezone());
            }
            return true;
        }
    }

    $explicitBranch = (int) ($_SESSION['auragold_login_branch_id'] ?? 0);
    if ($explicitBranch <= 0) {
        $explicitBranch = (int) ($_SESSION['working_branch_id'] ?? 0);
    }

    $new = null;
    if (function_exists('auragold_mysqli_connect_branch_or_registry')) {
        $new = auragold_mysqli_connect_branch_or_registry((string) DB_HOST, $dbname, $dbuser, $dbpass);
    }
    if (!$new instanceof mysqli && function_exists('auragold_mysqli_connect_operational')) {
        $new = auragold_mysqli_connect_operational((string) DB_HOST, $dbuser, $dbpass, $dbname);
    }
    if (!$new instanceof mysqli) {
        try {
            $new = @mysqli_connect((string) DB_HOST, $dbuser, $dbpass, $dbname);
        } catch (Throwable $e) {
            $new = null;
        }
    }

    if (!$new instanceof mysqli && $explicitBranch > 0 && function_exists('auragold_registry_tbl_branches_row_by_id')) {
        $row = auragold_registry_tbl_branches_row_by_id($explicitBranch);
        if (is_array($row) && function_exists('auragold_resolve_branch_operational_credentials')) {
            $registry = ($conn_master instanceof mysqli) ? $conn_master : null;
            $creds    = $registry
                ? auragold_resolve_branch_operational_credentials($row, $registry)
                : auragold_branch_row_db_credentials($row);
            $retryDb   = trim((string) ($creds['db_name'] ?? ''));
            $retryUser = trim((string) ($creds['db_user'] ?? ''));
            $retryPass = (string) ($creds['db_pass'] ?? '');
            if ($retryDb !== '') {
                if (function_exists('auragold_mysqli_connect_branch_or_registry')) {
                    $new = auragold_mysqli_connect_branch_or_registry((string) DB_HOST, $retryDb, $retryUser, $retryPass);
                }
                if (!$new instanceof mysqli) {
                    try {
                        $new = @mysqli_connect((string) DB_HOST, $retryUser !== '' ? $retryUser : $dbuser, $retryUser !== '' ? $retryPass : $dbpass, $retryDb);
                    } catch (Throwable $e) {
                        $new = null;
                    }
                }
                if ($new instanceof mysqli && function_exists('auragold_patch_session_working_db')) {
                    auragold_patch_session_working_db(
                        $retryDb,
                        $retryUser !== '' ? $retryUser : $dbuser,
                        $retryUser !== '' ? $retryPass : $dbpass
                    );
                    $dbname = $retryDb;
                }
            }
        }
    }

    if ($new instanceof mysqli) {
        mysqli_set_charset($new, 'utf8mb4');
        if (isset($conn) && $conn instanceof mysqli && $conn !== $conn_master && $conn !== $new) {
            @mysqli_close($conn);
        }
        $conn = $new;
        if (function_exists('auragold_apply_mysql_session_timezone') && function_exists('auragold_branch_timezone')) {
            auragold_apply_mysql_session_timezone($conn, auragold_branch_timezone());
        }
        return true;
    }

    // Host-based browsing only: adopt the live $conn schema when session DB cannot be opened.
    if ($cur !== '' && $explicitBranch <= 0 && function_exists('auragold_patch_session_working_db')) {
        auragold_patch_session_working_db($cur, $dbuser, $dbpass);
        if (isset($conn) && $conn instanceof mysqli
            && function_exists('auragold_apply_mysql_session_timezone')
            && function_exists('auragold_branch_timezone')) {
            auragold_apply_mysql_session_timezone($conn, auragold_branch_timezone());
        }
        return true;
    }

    return isset($conn) && $conn instanceof mysqli;
}

/**
 * Registry main row id (tbl_branches.id with main_branch_id = 0) for the current session.
 * Uses branch-credential scope first, then resolves tbl_users / working context from effective branch row.
 *
 * @return int >0 main id, or 0 if nothing can be resolved
 */
if (!function_exists('auragold_session_resolved_registry_main_id_for_branch_list')) {
    function auragold_session_resolved_registry_main_id_for_branch_list(): int {
        $bScope = auragold_branch_login_scope_main_id();
        if ($bScope > 0) {
            return $bScope;
        }
        require_once __DIR__ . '/auragold_branch_data_scope.php';
        $eff = (int) auragold_effective_branch_id();
        if ($eff <= 0) {
            return auragold_registry_main_branch_id_for_login();
        }
        $row = getRecordMaster('SELECT id, main_branch_id FROM tbl_branches WHERE id = ' . $eff . ' LIMIT 1');
        if (!$row) {
            return auragold_registry_main_branch_id_for_login();
        }
        $mb = (int) ($row['main_branch_id'] ?? 0);
        return $mb === 0 ? (int) $row['id'] : $mb;
    }
}

/**
 * Map session working_db (MySQL database name) to the registry top-level main id (tbl_branches).
 * Used so Branches list shows only that main row + its sub-branches while in branch DB context.
 *
 * @return int >0 main row id, or 0
 */
if (!function_exists('auragold_registry_main_id_from_session_working_db')) {
    function auragold_registry_main_id_from_session_working_db(): int {
        if (empty($_SESSION['working_db']) || !is_array($_SESSION['working_db'])) {
            return 0;
        }
        $dbname = trim((string) ($_SESSION['working_db']['database'] ?? $_SESSION['working_db']['db_name'] ?? ''));
        if ($dbname === '' || !function_exists('getRecordMaster') || !function_exists('esc')) {
            return 0;
        }
        $row = getRecordMaster(
            "SELECT id, main_branch_id FROM tbl_branches WHERE LOWER(TRIM(db_name)) = LOWER('" . esc($dbname) . "') LIMIT 1"
        );
        if (!$row) {
            return 0;
        }
        $rowId = (int) ($row['id'] ?? 0);
        if ($rowId <= 0) {
            return 0;
        }
        $parentMain = (int) ($row['main_branch_id'] ?? 0);
        return $parentMain === 0 ? $rowId : $parentMain;
    }
}

/**
 * Branches page: 0 = show every main and sub (superadmin at registry / no branch context). Otherwise one main + subs.
 */
if (!function_exists('auragold_branches_page_list_scope_main_id')) {
    function auragold_branches_page_list_scope_main_id(): int {
        $fromWorking = auragold_registry_main_id_from_session_working_db();
        if ($fromWorking > 0) {
            return $fromWorking;
        }
        if (function_exists('auragold_session_is_superadmin') && auragold_session_is_superadmin()) {
            return 0;
        }
        return auragold_session_resolved_registry_main_id_for_branch_list();
    }
}

/**
 * Non-superadmin: registry main id that sub-branch actions must belong to. Superadmin: 0 (no restriction).
 */
if (!function_exists('auragold_session_restrict_sub_branch_ops_main_id')) {
    function auragold_session_restrict_sub_branch_ops_main_id(): int {
        if (function_exists('auragold_session_is_superadmin') && auragold_session_is_superadmin()) {
            return 0;
        }
        $b = auragold_branch_login_scope_main_id();
        if ($b > 0) {
            return $b;
        }
        return auragold_session_resolved_registry_main_id_for_branch_list();
    }
}

/**
 * Sub-branches share a login portal with their main: after logout, ?branch_entry= should be the
 * registry main row (main_branch_id = 0), not the sub-branch id.
 *
 * @param int $registryBranchId tbl_branches.id for current context
 * @return int Main branch id for login URL, or $registryBranchId if already main / unknown
 */
if (!function_exists('auragold_registry_main_branch_id_for_logout_entry')) {
    function auragold_registry_main_branch_id_for_logout_entry(int $registryBranchId): int {
        $registryBranchId = (int) $registryBranchId;
        if ($registryBranchId <= 0 || !function_exists('getRecordMaster')) {
            return $registryBranchId;
        }
        $row = getRecordMaster(
            'SELECT id, IFNULL(main_branch_id, 0) AS mb FROM tbl_branches WHERE id = ' . $registryBranchId . ' LIMIT 1'
        );
        if (!$row || empty($row['id'])) {
            return $registryBranchId;
        }
        $parentMain = (int) ($row['mb'] ?? 0);
        return $parentMain > 0 ? $parentMain : (int) $row['id'];
    }
}

/**
 * Registry tbl_branches.id for post-logout redirects (?branch_entry=… / portal folder).
 * Prefers the DB actually in use (working_db or mysqli) over branch_id, which may still point at main
 * when the operational connection came from the HTTP host (subdomain) without working_db.
 * Sub-branch sessions resolve to the parent main id for branch_entry (shared login entry).
 *
 * @return int
 */
if (!function_exists('auragold_resolve_logout_branch_entry_id')) {
    function auragold_resolve_logout_branch_entry_id(): int {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return 0;
        }
        $raw = 0;
        $wdb = $_SESSION['working_db'] ?? null;
        if (is_array($wdb)) {
            $dbname = trim((string) ($wdb['database'] ?? $wdb['db_name'] ?? ''));
            if ($dbname !== '' && function_exists('getRecordMaster') && function_exists('esc')) {
                $row = getRecordMaster(
                    "SELECT id FROM tbl_branches WHERE LOWER(TRIM(db_name)) = LOWER('" . esc($dbname) . "') LIMIT 1"
                );
                if ($row && !empty($row['id'])) {
                    $raw = (int) $row['id'];
                }
            }
        }
        if ($raw <= 0 && function_exists('auragold_effective_branch_id')) {
            $e = (int) auragold_effective_branch_id();
            if ($e > 0) {
                $raw = $e;
            }
        }
        if ($raw <= 0) {
            global $conn;
            if (isset($conn) && $conn instanceof mysqli) {
                $rs = @mysqli_query($conn, 'SELECT DATABASE() AS db');
                if ($rs && ($dbRow = mysqli_fetch_assoc($rs))) {
                    mysqli_free_result($rs);
                    $dbname = trim((string) ($dbRow['db'] ?? ''));
                    if ($dbname !== '' && function_exists('getRecordMaster') && function_exists('esc')) {
                        $map = getRecordMaster(
                            "SELECT id FROM tbl_branches WHERE LOWER(TRIM(db_name)) = LOWER('" . esc($dbname) . "') LIMIT 1"
                        );
                        if ($map && !empty($map['id'])) {
                            $raw = (int) $map['id'];
                        }
                    }
                }
            }
        }
        if ($raw <= 0) {
            $raw = (int) ($_SESSION['working_branch_id'] ?? $_SESSION['branch_id'] ?? 0);
        }
        return auragold_registry_main_branch_id_for_logout_entry($raw);
    }
}

/**
 * Short initials for branch avatar chips in the profile dropdown.
 */
if (!function_exists('auragold_branch_name_initials')) {
    function auragold_branch_name_initials(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '?';
        }
        if (preg_match('/^([A-Za-z]+)\s*(\d+)$/u', $name, $m)) {
            return strtoupper(mb_substr($m[1], 0, 1) . $m[2]);
        }
        $parts = preg_split('/\s+/u', $name) ?: [];
        if (count($parts) >= 2) {
            return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
        }
        return strtoupper(mb_substr($name, 0, min(2, mb_strlen($name))));
    }
}

/**
 * Flat list of switchable branches for the header profile dropdown (scoped like Branches page).
 *
 * @return list<array{id:int,name:string,initials:string,is_main:bool,is_current:bool}>
 */
if (!function_exists('auragold_profile_dropdown_switchable_branches')) {
    function auragold_profile_dropdown_switchable_branches(): array
    {
        if (!function_exists('getListMaster')) {
            return [];
        }
        $listScopeMain = function_exists('auragold_branches_page_list_scope_main_id')
            ? (int) auragold_branches_page_list_scope_main_id()
            : 0;
        $hiddenUserSql = "LOWER(TRIM(IFNULL(username,''))) <> 'superbranch'";
        $cols = 'id, name, status, main_branch_id';

        if ($listScopeMain > 0) {
            $mains = getListMaster(
                'SELECT ' . $cols . ' FROM tbl_branches WHERE main_branch_id = 0 AND id = ' . (int) $listScopeMain
                . ' ORDER BY id ASC'
            );
            $subs = getListMaster(
                'SELECT ' . $cols . ' FROM tbl_branches WHERE main_branch_id = ' . (int) $listScopeMain
                . ' AND ' . $hiddenUserSql . ' ORDER BY id ASC'
            );
        } else {
            $mains = getListMaster(
                'SELECT ' . $cols . ' FROM tbl_branches WHERE main_branch_id = 0 AND ' . $hiddenUserSql
                . ' ORDER BY id ASC'
            );
            $subs = getListMaster(
                'SELECT ' . $cols . ' FROM tbl_branches b WHERE b.main_branch_id > 0 AND ' . $hiddenUserSql
                . ' AND EXISTS (SELECT 1 FROM tbl_branches m WHERE m.id = b.main_branch_id AND IFNULL(m.main_branch_id, 0) = 0)'
                . ' ORDER BY b.main_branch_id ASC, b.id ASC'
            );
        }
        if (!is_array($mains)) {
            $mains = [];
        }
        if (!is_array($subs)) {
            $subs = [];
        }

        $subsByMain = [];
        foreach ($subs as $s) {
            if (!is_array($s)) {
                continue;
            }
            $mid = (int) ($s['main_branch_id'] ?? 0);
            if ($mid <= 0) {
                continue;
            }
            if (!isset($subsByMain[$mid])) {
                $subsByMain[$mid] = [];
            }
            $subsByMain[$mid][] = $s;
        }

        $currentId = (int) ($_SESSION['auragold_login_branch_id'] ?? 0);
        if ($currentId <= 0) {
            $currentId = (int) ($_SESSION['working_branch_id'] ?? 0);
        }
        if ($currentId <= 0) {
            $currentId = (int) ($_SESSION['branch_id'] ?? 0);
        }

        $out = [];
        $push = static function (array $row, bool $isMain) use (&$out, $currentId): void {
            if ((int) ($row['status'] ?? 0) !== 1) {
                return;
            }
            if (function_exists('auragold_can_user_open_branch_row') && !auragold_can_user_open_branch_row($row)) {
                return;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                return;
            }
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                $name = 'Branch #' . $id;
            }
            $out[] = [
                'id'         => $id,
                'name'       => $name,
                'initials'   => auragold_branch_name_initials($name),
                'is_main'    => $isMain,
                'is_current' => $id === $currentId,
            ];
        };

        foreach ($mains as $main) {
            if (!is_array($main)) {
                continue;
            }
            $push($main, true);
            $mid = (int) ($main['id'] ?? 0);
            foreach ($subsByMain[$mid] ?? [] as $sub) {
                if (is_array($sub)) {
                    $push($sub, false);
                }
            }
        }

        return $out;
    }
}
