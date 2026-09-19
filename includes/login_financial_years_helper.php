<?php
/**
 * Financial year list for login (per branch DB) and validation after branch context is applied.
 */
require_once __DIR__ . '/branch_credentials.php';
require_once __DIR__ . '/subdomain_branch.php';
require_once __DIR__ . '/auragold_superadmin.php';

/**
 * Older branch DBs may lack tbl_accounting_financial_years.is_active.
 */
function auragold_financial_years_link_has_is_active(mysqli $link): bool {
    static $cache = [];
    $dbRes = @mysqli_query($link, 'SELECT DATABASE() AS d');
    $dbName = '';
    if ($dbRes && ($row = mysqli_fetch_assoc($dbRes))) {
        $dbName = (string) ($row['d'] ?? '');
    }
    if ($dbRes) {
        mysqli_free_result($dbRes);
    }
    $key = $dbName !== '' ? $dbName : spl_object_hash($link);
    if (array_key_exists($key, $cache)) {
        return (bool) $cache[$key];
    }
    $c = @mysqli_query($link, "SHOW COLUMNS FROM tbl_accounting_financial_years LIKE 'is_active'");
    $has = ($c && mysqli_num_rows($c) > 0);
    if ($c) {
        mysqli_free_result($c);
    }
    $cache[$key] = $has;
    return $has;
}

/**
 * @return list<array{id:int,start_date:string,end_date:string,is_active:int}>
 */
function auragold_fetch_financial_years_for_branch_login($branchRowId) {
    global $conn_master, $conn;

    $branchRowId = (int) $branchRowId;
    $useConn   = null;
    $closeConn = false;

    if ($branchRowId <= 0) {
        $useConn = (isset($conn) && $conn instanceof mysqli) ? $conn : $conn_master;
    } else {
        $row = function_exists('auragold_registry_tbl_branches_row_by_id')
            ? auragold_registry_tbl_branches_row_by_id($branchRowId)
            : (function_exists('getRecordMaster') ? getRecordMaster('SELECT * FROM tbl_branches WHERE id = ' . $branchRowId . ' LIMIT 1') : null);
        if (!$row) {
            return [];
        }
        $regMeta = function_exists('auragold_registry_mysqli') ? auragold_registry_mysqli() : null;
        $creds   = (($regMeta instanceof mysqli || $conn_master instanceof mysqli) && function_exists('auragold_resolve_branch_operational_credentials'))
            ? auragold_resolve_branch_operational_credentials($row, ($regMeta instanceof mysqli) ? $regMeta : $conn_master)
            : auragold_branch_row_db_credentials($row);
        $db_name = trim((string) ($creds['db_name'] ?? ''));
        if ($db_name === '') {
            $useConn = (isset($conn) && $conn instanceof mysqli) ? $conn : $conn_master;
        } else {
            $u = $creds['db_user'] !== '' ? $creds['db_user'] : DB_USER;
            $p = $creds['db_user'] !== '' ? $creds['db_pass'] : DB_PASS;
            $useConn = function_exists('auragold_mysqli_connect_operational')
                ? auragold_mysqli_connect_operational((string) DB_HOST, (string) $u, (string) $p, $db_name)
                : @mysqli_connect(DB_HOST, $u, $p, $db_name);
            if (!$useConn) {
                return [];
            }
            mysqli_set_charset($useConn, 'utf8mb4');
            $closeConn = true;
        }
    }

    if (!$useConn || !($useConn instanceof mysqli)) {
        return [];
    }

    $t = @mysqli_query($useConn, "SHOW TABLES LIKE 'tbl_accounting_financial_years'");
    if (!$t || mysqli_num_rows($t) === 0) {
        if ($t) {
            mysqli_free_result($t);
        }
        if ($closeConn) {
            mysqli_close($useConn);
        }
        return [];
    }
    mysqli_free_result($t);

    $hasIsActive = auragold_financial_years_link_has_is_active($useConn);
    $sql = $hasIsActive
        ? 'SELECT id, start_date, end_date, is_active FROM tbl_accounting_financial_years WHERE status = 1 AND IFNULL(is_active, 0) = 1 ORDER BY start_date ASC, id ASC'
        : 'SELECT id, start_date, end_date FROM tbl_accounting_financial_years WHERE status = 1 ORDER BY start_date ASC, id ASC';
    $data = [];
    $q    = @mysqli_query($useConn, $sql);
    if ($q) {
        while ($r = mysqli_fetch_assoc($q)) {
            $data[] = [
                'id'          => (int) $r['id'],
                'start_date'  => (string) ($r['start_date'] ?? ''),
                'end_date'    => (string) ($r['end_date'] ?? ''),
                'is_active'   => $hasIsActive ? (int) ($r['is_active'] ?? 0) : 1,
            ];
        }
        mysqli_free_result($q);
    }
    if ($closeConn) {
        mysqli_close($useConn);
    }
    return $data;
}

/**
 * Pick a default financial year for login (first is_active, else first row) when the user does not select one
 * (e.g. superbranch one-click login).
 */
function auragold_login_default_financial_year_id_for_branch(int $loginBranchId): int {
    $loginBranchId = (int) $loginBranchId;
    $years         = auragold_fetch_financial_years_for_branch_login($loginBranchId);
    if ($years === []) {
        return 0;
    }
    foreach ($years as $y) {
        if ((int) ($y['is_active'] ?? 0) === 1) {
            return (int) ($y['id'] ?? 0);
        }
    }
    return (int) ($years[0]['id'] ?? 0);
}

/**
 * Current active financial year row from an open mysqli (branch or main DB).
 *
 * @return ?array{id:int,start_date:string,end_date:string,is_active:int}
 */
function auragold_fetch_active_financial_year_row_from_link(mysqli $link): ?array {
    $hasIsActive = auragold_financial_years_link_has_is_active($link);
    $queries = $hasIsActive
        ? [
            'SELECT id, start_date, end_date, is_active FROM tbl_accounting_financial_years '
            . 'WHERE status = 1 AND IFNULL(is_active, 0) = 1 '
            . 'ORDER BY start_date DESC, id DESC LIMIT 1',
            'SELECT id, start_date, end_date, is_active FROM tbl_accounting_financial_years '
            . 'WHERE status = 1 '
            . 'ORDER BY start_date DESC, id DESC LIMIT 1',
        ]
        : [
            'SELECT id, start_date, end_date FROM tbl_accounting_financial_years '
            . 'WHERE status = 1 '
            . 'ORDER BY start_date DESC, id DESC LIMIT 1',
        ];
    foreach ($queries as $sql) {
        $q = @mysqli_query($link, $sql);
        if (!$q || mysqli_num_rows($q) === 0) {
            if ($q) {
                mysqli_free_result($q);
            }
            continue;
        }
        $r = mysqli_fetch_assoc($q);
        mysqli_free_result($q);
        if (!$r || empty($r['id'])) {
            continue;
        }
        return [
            'id'          => (int) $r['id'],
            'start_date'  => (string) ($r['start_date'] ?? ''),
            'end_date'    => (string) ($r['end_date'] ?? ''),
            'is_active'   => $hasIsActive ? (int) ($r['is_active'] ?? 0) : 1,
        ];
    }
    return null;
}

function auragold_financial_year_compute_short_label(array $row): string {
    foreach (['short_label', 'year_name', 'name', 'label', 'financial_year'] as $key) {
        if (!empty($row[$key])) {
            $val = trim((string) $row[$key]);
            if ($val !== '') {
                return $val;
            }
        }
    }
    $fyh_s = trim((string) ($row['start_date'] ?? ''));
    $fyh_e = trim((string) ($row['end_date'] ?? ''));
    if ($fyh_s === '' || $fyh_e === '') {
        return '';
    }
    $fyh_ts = strtotime($fyh_s);
    $fyh_te = strtotime($fyh_e);
    if ($fyh_ts === false || $fyh_te === false) {
        return '';
    }
    $hy1 = (int) date('Y', $fyh_ts);
    $hy2 = (int) date('Y', $fyh_te);
    return $hy1 . '-' . substr((string) $hy2, -2);
}

function auragold_store_financial_year_in_session(array $row): void {
    $_SESSION['financial_year_id'] = (int) $row['id'];
    $_SESSION['financial_year']    = [
        'id'          => (int) $row['id'],
        'start_date'  => (string) ($row['start_date'] ?? ''),
        'end_date'    => (string) ($row['end_date'] ?? ''),
        'is_active'   => (int) ($row['is_active'] ?? 0),
        'short_label' => auragold_financial_year_compute_short_label($row),
    ];
}

/**
 * Resolve a financial year row by id, or fall back to the current active year
 * (e.g. after Accounting Masters switches which year is active).
 *
 * @return ?array{id:int,start_date:string,end_date:string,is_active:int}
 */
function auragold_resolve_financial_year_row_for_login(mysqli $link, int $fyId): ?array {
    $hasIsActive = auragold_financial_years_link_has_is_active($link);
    if ($fyId > 0) {
        $fyIdEsc = (int) $fyId;
        $sql = $hasIsActive
            ? "SELECT id, start_date, end_date, is_active FROM tbl_accounting_financial_years "
            . "WHERE id = $fyIdEsc AND status = 1 AND IFNULL(is_active, 0) = 1 LIMIT 1"
            : "SELECT id, start_date, end_date FROM tbl_accounting_financial_years "
            . "WHERE id = $fyIdEsc AND status = 1 LIMIT 1";
        $q = @mysqli_query($link, $sql);
        if ($q && mysqli_num_rows($q) > 0) {
            $r = mysqli_fetch_assoc($q);
            mysqli_free_result($q);
            if ($r) {
                return [
                    'id'          => (int) $r['id'],
                    'start_date'  => (string) ($r['start_date'] ?? ''),
                    'end_date'    => (string) ($r['end_date'] ?? ''),
                    'is_active'   => $hasIsActive ? (int) ($r['is_active'] ?? 0) : 1,
                ];
            }
        } elseif ($q) {
            mysqli_free_result($q);
        }
    }
    return auragold_fetch_active_financial_year_row_from_link($link);
}

/**
 * FY label shown in the header pill (e.g. 2026-27). Empty when the session has no usable start/end range.
 * Must match sidebar.php so “FY not showing” equals server-side invalid session.
 */
function auragold_session_financial_year_short_label(): string {
    if (empty($_SESSION['financial_year']) || !is_array($_SESSION['financial_year'])) {
        return '';
    }
    $fyh = $_SESSION['financial_year'];
    if (!empty($fyh['short_label'])) {
        return trim((string) $fyh['short_label']);
    }
    return auragold_financial_year_compute_short_label($fyh);
}

/**
 * FY label for header: session first, then hydrate from operational DB (live HTTPS / superadmin).
 */
function auragold_header_financial_year_short_label(): string {
    $label = auragold_session_financial_year_short_label();
    if ($label !== '') {
        return $label;
    }
    if (function_exists('auragold_hydrate_session_financial_year_from_conn')) {
        auragold_hydrate_session_financial_year_from_conn();
    }
    return auragold_session_financial_year_short_label();
}

/**
 * Open mysqli to the DB that matches current $_SESSION['working_db'] (after branch context), or main registry.
 * Caller must mysqli_close() if second element is true.
 *
 * @return array{0:?mysqli,1:bool}
 */
function auragold_login_open_mysqli_for_working_session() {
    global $conn_master;

    if (!empty($_SESSION['working_db']) && is_array($_SESSION['working_db'])) {
        $w       = $_SESSION['working_db'];
        $dbname  = trim((string) ($w['database'] ?? $w['db_name'] ?? ''));
        $dbuser  = trim((string) ($w['user'] ?? $w['db_user'] ?? ''));
        $dbpass  = (string) ($w['password'] ?? $w['db_pass'] ?? '');
        if ($dbname !== '') {
            if ($dbuser === '') {
                $dbuser = defined('DB_USER') ? (string) DB_USER : '';
                $dbpass = defined('DB_PASS') ? (string) DB_PASS : '';
            }
            if (function_exists('auragold_mysqli_connect_operational') && defined('DB_HOST')) {
                $c = auragold_mysqli_connect_operational(
                    (string) DB_HOST,
                    (string) $dbuser,
                    (string) $dbpass,
                    $dbname
                );
                if ($c) {
                    mysqli_set_charset($c, 'utf8mb4');
                    return [$c, true];
                }
                return [null, false];
            }
            $c = mysqli_init();
            if ($c) {
                @mysqli_options($c, MYSQLI_OPT_CONNECT_TIMEOUT, 5);
                try {
                    if (!@mysqli_real_connect($c, (string) DB_HOST, $dbuser, $dbpass, $dbname)) {
                        mysqli_close($c);
                        return [null, false];
                    }
                } catch (Throwable $e) {
                    @mysqli_close($c);
                    return [null, false];
                }
                mysqli_set_charset($c, 'utf8mb4');
                return [$c, true];
            }
            return [null, false];
        }
    }

    return [$conn_master, false];
}

/**
 * Validate POST financial_year_id against working DB; set $_SESSION['financial_year'] on success.
 * If the FY table is missing, login continues without FY (runtime enforcement skips too — legacy DBs).
 * If the table exists, there must be at least one status=1 row with is_active=1, and the user must pick one.
 *
 * @return array{ok:bool,message:string}
 */
function auragold_financial_year_login_validate_and_store() {
    unset($_SESSION['financial_year'], $_SESSION['financial_year_id']);

    if (auragold_session_is_superadmin()) {
        // Superadmin login form often has no FY dropdown — still load active FY for header / vouchers.
        try {
            [$link, $closeLink] = auragold_login_open_mysqli_for_working_session();
        } catch (Throwable $e) {
            $link = null;
            $closeLink = false;
        }
        if ($link instanceof mysqli) {
            $t = @mysqli_query($link, "SHOW TABLES LIKE 'tbl_accounting_financial_years'");
            if ($t && mysqli_num_rows($t) > 0) {
                mysqli_free_result($t);
                $r = auragold_fetch_active_financial_year_row_from_link($link);
                if ($r) {
                    auragold_store_financial_year_in_session($r);
                }
            } elseif ($t) {
                mysqli_free_result($t);
            }
            if ($closeLink) {
                mysqli_close($link);
            }
        }
        return ['ok' => true, 'message' => ''];
    }

    $fyId = (int) ($_POST['financial_year_id'] ?? 0);

    try {
        [$link, $closeLink] = auragold_login_open_mysqli_for_working_session();
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => 'Could not open database to verify financial year.'];
    }
    if (!$link || !($link instanceof mysqli)) {
        return ['ok' => false, 'message' => 'Could not open database to verify financial year.'];
    }

    $t = @mysqli_query($link, "SHOW TABLES LIKE 'tbl_accounting_financial_years'");
    if (!$t || mysqli_num_rows($t) === 0) {
        if ($t) {
            mysqli_free_result($t);
        }
        if ($closeLink) {
            mysqli_close($link);
        }
        return ['ok' => true, 'message' => ''];
    }
    mysqli_free_result($t);

    $hasIsActive = auragold_financial_years_link_has_is_active($link);
    $aggSql = $hasIsActive
        ? 'SELECT COUNT(*) AS n_all, '
        . 'COALESCE(SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END), 0) AS n_any, '
        . 'COALESCE(SUM(CASE WHEN status = 1 AND IFNULL(is_active, 0) = 1 THEN 1 ELSE 0 END), 0) AS n_active '
        . 'FROM tbl_accounting_financial_years'
        : 'SELECT COUNT(*) AS n_all, '
        . 'COALESCE(SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END), 0) AS n_any, '
        . 'COALESCE(SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END), 0) AS n_active '
        . 'FROM tbl_accounting_financial_years';
    $aggRes = @mysqli_query($link, $aggSql);
    if (!$aggRes) {
        if ($closeLink) {
            mysqli_close($link);
        }
        return ['ok' => false, 'message' => 'Could not verify financial years.'];
    }
    $agg = mysqli_fetch_assoc($aggRes);
    mysqli_free_result($aggRes);
    if (!$agg) {
        if ($closeLink) {
            mysqli_close($link);
        }
        return ['ok' => false, 'message' => 'Could not verify financial years.'];
    }

    $nAll    = (int) ($agg['n_all'] ?? 0);
    $nAny    = (int) ($agg['n_any'] ?? 0);
    $nActive = (int) ($agg['n_active'] ?? 0);

    if ($nAll <= 0) {
        if ($closeLink) {
            mysqli_close($link);
        }
        return ['ok' => false, 'message' => 'No financial year has been set up. Add one in Accounting Masters (Financial Years), then sign in again.'];
    }
    if ($nAny <= 0) {
        if ($closeLink) {
            mysqli_close($link);
        }
        return ['ok' => false, 'message' => 'No financial year is available. Restore a year in Accounting Masters, then sign in again.'];
    }
    if ($nActive <= 0) {
        if ($closeLink) {
            mysqli_close($link);
        }
        return ['ok' => false, 'message' => 'No active financial year. Mark the current year as active in Accounting Masters, then sign in again.'];
    }

    if ($fyId <= 0) {
        $r = auragold_fetch_active_financial_year_row_from_link($link);
        if ($closeLink) {
            mysqli_close($link);
        }
        if (!$r) {
            return ['ok' => false, 'message' => 'Please select a financial year.'];
        }
        auragold_store_financial_year_in_session($r);
        return ['ok' => true, 'message' => ''];
    }

    $r = auragold_resolve_financial_year_row_for_login($link, $fyId);
    if ($closeLink) {
        mysqli_close($link);
    }

    if (!$r) {
        return ['ok' => false, 'message' => 'No active financial year. Mark the current year as active in Accounting Masters, then sign in again.'];
    }

    auragold_store_financial_year_in_session($r);

    return ['ok' => true, 'message' => ''];
}

/**
 * Destroy session and redirect to login (shared by FY failure, branch-context failure, and runtime health checks).
 * AJAX requests get JSON 401 + redirect URL (same shape as idle timeout in session_init.php).
 */
function auragold_login_abort_to_index($message) {
    $branchEntry = 0;
    if (session_status() === PHP_SESSION_ACTIVE) {
        $branchEntry = (int) ($_SESSION['working_branch_id'] ?? $_SESSION['branch_id'] ?? 0);
    }
    $redirect = 'index.php?login_error=' . rawurlencode((string) $message);
    if ($branchEntry > 0) {
        $redirect .= '&branch_entry=' . $branchEntry;
    }
    $isAjax = function_exists('auragold_session_is_request_ajax') && auragold_session_is_request_ajax();

    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        header('HTTP/1.1 401 Unauthorized');
        echo json_encode([
            'session_expired' => true,
            'message'         => (string) $message,
            'redirect'        => $redirect,
        ]);
        exit;
    }

    header('Location: ' . $redirect);
    exit;
}

/**
 * Each HTTP request: if logged in, ensure tbl_users row is still active and financial year is still active in DB.
 * Missing / inactive user or inactive FY → session cleared and redirect to login (registry + branch DB flow).
 */
function auragold_enforce_session_operational_health() {
    if (PHP_SAPI === 'cli') {
        return;
    }
    if (!function_exists('session_status') || session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }
    $healthKey = 'auragold_sess_health_checked_at';
    if (!empty($_SESSION[$healthKey]) && (time() - (int) $_SESSION[$healthKey]) < 90) {
        return;
    }
    $_SESSION[$healthKey] = time();

    $uid = (int) ($_SESSION['user_id'] ?? 0);
    if ($uid <= 0) {
        return;
    }

    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? ''));
    $skip   = [
        'login_submit.php',
        'logout.php',
        'login_check.php',
        'login_verify_credentials.php',
        'login_financial_years.php',
    ];
    if (in_array($script, $skip, true)) {
        return;
    }

    global $conn;
    if (!$conn || !($conn instanceof mysqli)) {
        return;
    }

    require_once __DIR__ . '/login_authenticate.php';

    $userRow = function_exists('getRecord') ? getRecord('SELECT * FROM tbl_users WHERE id = ' . $uid . ' LIMIT 1') : null;
    if (!$userRow || !auragold_user_active($userRow)) {
        auragold_login_abort_to_index('Your account session is no longer active. Please sign in again.');
    }

    if (function_exists('auragold_session_is_superadmin') && auragold_session_is_superadmin()) {
        return;
    }

    $t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_accounting_financial_years'");
    if (!$t || mysqli_num_rows($t) === 0) {
        if ($t) {
            mysqli_free_result($t);
        }
        return;
    }
    mysqli_free_result($t);

    $aggRes = mysqli_query(
        $conn,
        'SELECT COUNT(*) AS n_all, '
        . 'COALESCE(SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END), 0) AS n_any, '
        . 'COALESCE(SUM(CASE WHEN status = 1 AND IFNULL(is_active, 0) = 1 THEN 1 ELSE 0 END), 0) AS n_active '
        . 'FROM tbl_accounting_financial_years'
    );
    if (!$aggRes) {
        return;
    }
    $agg = mysqli_fetch_assoc($aggRes);
    mysqli_free_result($aggRes);
    if (!$agg) {
        return;
    }

    $nAll    = (int) ($agg['n_all'] ?? 0);
    $nAny    = (int) ($agg['n_any'] ?? 0);
    $nActive = (int) ($agg['n_active'] ?? 0);

    if ($nAll <= 0) {
        auragold_login_abort_to_index('No financial year has been set up. Please sign in after an administrator adds one in Accounting Masters.');
    }
    if ($nAny <= 0) {
        auragold_login_abort_to_index('No financial year is available. Please sign in after the administrator restores a financial year.');
    }
    if ($nActive <= 0) {
        auragold_login_abort_to_index('No active financial year. Please sign in after the administrator marks the current year as active in Accounting Masters.');
    }

    $fyId = (int) ($_SESSION['financial_year_id'] ?? 0);
    if ($fyId <= 0 && !empty($_SESSION['financial_year']) && is_array($_SESSION['financial_year'])) {
        $fyId = (int) ($_SESSION['financial_year']['id'] ?? 0);
    }

    $fyRow = auragold_resolve_financial_year_row_for_login($conn, $fyId);
    if (!$fyRow) {
        auragold_login_abort_to_index('No active financial year. Please sign in after the administrator marks the current year as active in Accounting Masters.');
    }

    auragold_store_financial_year_in_session($fyRow);

    if (auragold_session_financial_year_short_label() === '') {
        auragold_login_abort_to_index('Financial year could not be loaded for this session. Please sign in again.');
    }
}

/**
 * Login succeeded but FY validation failed — clear session and return to login page.
 */
function auragold_login_abort_after_failed_financial_year($message) {
    auragold_login_abort_to_index($message);
}
