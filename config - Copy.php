<?php
$project = "local"; // for production set to "prod"
// Must match cPanel’s MySQL prefix for that account, e.g. "goldmatrix_" (same leading part as cPanel’s DB/user names).
$db_prefix = "goldmatrix_";
// Set to your base domain (host only) to auto-fill branch “IP/URL” with https://{name-slug}.HOST, e.g. "goldmatrixsoft.com"
$auragold_branch_subdomain_base_host = 'goldmatrixsoft.com';
// Branch schema clone (production): main/template database and a MySQL user that can read it. Used when cloning a new branch DB; often goldmatrix_main + that DB’s user. Leave empty to use the main app DB (DB_NAME) and app credentials (DB_USER/DB_PASS).
$auragold_schema_clone_source_db  = '';
$auragold_clone_source_mysql_user = '';
$auragold_clone_source_mysql_pass = '';
// Production only: cPanel UAPI (create_database / create_user / set_privileges) — ignored on local.
$cpanelUser = '';
$apiToken     = '';
$domain       = '';
if (isset($project) && (string) $project === 'prod') {
    // $cpanelUser: cPanel username; $domain: host only, no https — UAPI base is https://{domain}:2083
    $cpanelUser = 'goldmatrix';
    $apiToken   = 'JVK8M5WY2RUDKS0S0J897I78AN9IBUL1'; // rotate in cPanel if this file was ever exposed
    $domain     = 'goldmatrixsoft.com';
    // Optional: MySQL user that can read the main/template DB; branch DB clone then uses cPanel’s new branch user (set password in $auragold_clone_source_mysql_pass)
    // $auragold_schema_clone_source_db   = 'goldmatrix_main';
    // $auragold_clone_source_mysql_user  = 'goldmatrix_main';
    // $auragold_clone_source_mysql_pass   = 'your_main_db_password';
    $auragold_branch_subdomain_base_host = 'goldmatrixsoft.com';
}
/** Branch DB naming: local uses root + empty password in tbl_branches; prod uses prefixed MySQL user + random password. */
if (!defined('AURAGOLD_PROJECT')) {
    define('AURAGOLD_PROJECT', isset($project) && (string) $project === 'prod' ? 'prod' : 'local');
}
if (!defined('AURAGOLD_DB_PREFIX')) {
    define('AURAGOLD_DB_PREFIX', isset($db_prefix) ? (string) $db_prefix : 'auragold_');
}
/**
 * Google Cloud Translation API (optional): full language list + automatic UI translation for all menus using auragold_t().
 * Create a key: Google Cloud Console → APIs → Cloud Translation API. Leave empty to use the bundled language list; translate needs the key.
 */
$auragold_google_translate_api_key = '';
$__auragold_gt_env = getenv('AURAGOLD_GOOGLE_TRANSLATE_API_KEY');
if ($__auragold_gt_env !== false && trim((string) $__auragold_gt_env) !== '') {
    $auragold_google_translate_api_key = trim((string) $__auragold_gt_env);
}
if (!defined('AURAGOLD_GOOGLE_TRANSLATE_API_KEY')) {
    define('AURAGOLD_GOOGLE_TRANSLATE_API_KEY', (string) $auragold_google_translate_api_key);
}
if (!defined('AURAGOLD_BRANCH_SUBDOMAIN_BASE_HOST')) {
    define(
        'AURAGOLD_BRANCH_SUBDOMAIN_BASE_HOST',
        isset($auragold_branch_subdomain_base_host) ? trim((string) $auragold_branch_subdomain_base_host) : ''
    );
}
if (!defined('AURAGOLD_BRANCH_URL_USE_HTTPS')) {
    define('AURAGOLD_BRANCH_URL_USE_HTTPS', true);
}
/**
 * When true, new branch DBs get only a small set of tables (faster; see branch_database_provision).
 * When false, every base table in the registry is cloned (recommended).
 */
if (!defined('AURAGOLD_BRANCH_MINIMAL_SCHEMA')) {
    define('AURAGOLD_BRANCH_MINIMAL_SCHEMA', false);
}
/** Comma-separated tbl_users usernames that may create new main branches and see the full branch list when branch-scoped. */
$auragold_superadmin_usernames = 'superadmin';
if (!defined('AURAGOLD_SUPERADMIN_USERNAMES')) {
    define('AURAGOLD_SUPERADMIN_USERNAMES', isset($auragold_superadmin_usernames) ? trim((string) $auragold_superadmin_usernames) : 'superadmin');
}
require_once __DIR__ . '/includes/remote_license_gate.php';

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

require_once __DIR__ . '/includes/branch_credentials.php';
require_once __DIR__ . '/includes/subdomain_branch.php';

/**
 * Bootstrap: connect here first (env or defaults) — database that contains tbl_branches.
 * Main row (main_branch_id = 0) supplies default DB_NAME / DB_USER / DB_PASS for $conn_master.
 *
 * Env (optional):
 *   DB_HOST, AURAGOLD_REGISTRY_DB, AURAGOLD_BOOTSTRAP_USER, AURAGOLD_BOOTSTRAP_PASS
 */
$__db_host     = getenv('DB_HOST') ?: 'localhost';
$__registry_db = getenv('AURAGOLD_REGISTRY_DB') ?: 'auragold';
$__boot_user   = getenv('AURAGOLD_BOOTSTRAP_USER') ?: 'root';
$__boot_pass   = getenv('AURAGOLD_BOOTSTRAP_PASS');
if ($__boot_pass === false) {
    $__boot_pass = '';
}

$__db_name = $__registry_db;
$__db_user = $__boot_user;
$__db_pass = $__boot_pass;

$__bootstrapConn = @mysqli_connect($__db_host, $__boot_user, $__boot_pass, $__registry_db);
if ($__bootstrapConn) {
    mysqli_set_charset($__bootstrapConn, 'utf8mb4');
    $__res = mysqli_query(
        $__bootstrapConn,
        'SELECT * FROM tbl_branches WHERE IFNULL(main_branch_id, 0) = 0 ORDER BY id ASC LIMIT 1'
    );
    if ($__res && mysqli_num_rows($__res) > 0) {
        $__main = mysqli_fetch_assoc($__res);
        $__cr   = auragold_branch_row_db_credentials($__main);
        if ($__cr['db_name'] !== '') {
            $__db_name = $__cr['db_name'];
        }
        if ($__cr['db_user'] !== '') {
            $__db_user = $__cr['db_user'];
            $__db_pass = $__cr['db_pass'];
        }
    }
    mysqli_close($__bootstrapConn);
}

define('DB_HOST', $__db_host);
define('DB_USER', $__db_user);
define('DB_PASS', $__db_pass);

$Proj_Title = "Gold Matrix";
$SiteUrl = "http://localhost/goldmatrix/";

/**
 * Normalize a stored upload path to the URL segment under $SiteUrl (no "admin/" prefix).
 * Handles legacy values such as admin/uploads/...
 */
function auragold_uploads_public_rel(string $path): string {
    $r = ltrim(str_replace('\\', '/', trim($path)), '/');
    if ($r === '') {
        return '';
    }
    if (stripos($r, 'admin/uploads/') === 0) {
        return substr($r, 6);
    }
    if (stripos($r, 'admin/') === 0) {
        return substr($r, 6);
    }
    return $r;
}

/**
 * Full public URL for a stored upload path (uses global $SiteUrl). http(s) URLs unchanged.
 */
function auragold_uploads_public_url(string $path): string {
    global $SiteUrl;
    $path = trim($path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    $rel = auragold_uploads_public_rel($path);
    if ($rel === '') {
        return '';
    }
    $base = isset($SiteUrl) ? rtrim((string) $SiteUrl, '/') : '';
    if ($base !== '') {
        return $base . '/' . $rel;
    }
    return '/' . $rel;
}

if (!defined('COMPANY_TRN')) define('COMPANY_TRN', '100436638900003');
/** Legal name on EMI / investment print vouchers (optional; falls back to $Proj_Title). */
if (!defined('COMPANY_LEGAL_NAME')) define('COMPANY_LEGAL_NAME', '');

// Old Jewelry Stock In: barcode prefix and number of digits (e.g. "B" + 8 digits => B00000001)
define('OLD_JEWELRY_STOCK_BARCODE_PREFIX', 'B');
define('OLD_JEWELRY_STOCK_BARCODE_DIGITS', 8);

date_default_timezone_set("Asia/Kolkata");

// Master connection: registry (tbl_branches, central metadata). tbl_users logins use $conn (branch DB).
$__effective_db = $__db_name;
$conn_master    = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, $__effective_db);

if (!$conn_master
    && ($__db_user !== $__boot_user || $__db_pass !== $__boot_pass || $__db_name !== $__registry_db)) {
    $conn_master = @mysqli_connect($__db_host, $__boot_user, $__boot_pass, $__registry_db);
    if ($conn_master) {
        $__effective_db = $__registry_db;
    }
}

if ($conn_master) {
    mysqli_set_charset($conn_master, 'utf8mb4');
    $__tbl = mysqli_query($conn_master, "SHOW TABLES LIKE 'tbl_branches'");
    if (!$__tbl || mysqli_num_rows($__tbl) === 0) {
        mysqli_close($conn_master);
        $__effective_db = $__registry_db;
        $conn_master    = @mysqli_connect($__db_host, $__boot_user, $__boot_pass, $__effective_db);
        if (!$conn_master) {
            $conn_master = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, $__effective_db);
        }
        if ($conn_master) {
            mysqli_set_charset($conn_master, 'utf8mb4');
        }
    }
}

if (!$conn_master) {
    die('Database Connection Failed : ' . mysqli_connect_error());
}

if (!defined('AURAGOLD_REGISTRY_DB')) {
    define('AURAGOLD_REGISTRY_DB', $__registry_db);
}
/** Optional: full MySQL name of the template database (e.g. goldmatrix_main) for branch schema clone. */
if (!defined('AURAGOLD_SCHEMA_CLONE_SOURCE_DB')) {
    define('AURAGOLD_SCHEMA_CLONE_SOURCE_DB', isset($auragold_schema_clone_source_db) ? (string) $auragold_schema_clone_source_db : '');
}
/**
 * Optional: MySQL user and password that can read the template DB (AURAGOLD_SCHEMA_CLONE_SOURCE_DB / main).
 * If both empty, branch provisioning uses DB_USER/DB_PASS (single connection; that user must see source + target).
 * If set with branch credentials in opts, provisioning uses two connections: source = read main, target = new branch user (cPanel).
 */
if (!defined('AURAGOLD_CLONE_SOURCE_USER')) {
    define('AURAGOLD_CLONE_SOURCE_USER', isset($auragold_clone_source_mysql_user) ? (string) $auragold_clone_source_mysql_user : '');
}
if (!defined('AURAGOLD_CLONE_SOURCE_PASS')) {
    define('AURAGOLD_CLONE_SOURCE_PASS', isset($auragold_clone_source_mysql_pass) ? (string) $auragold_clone_source_mysql_pass : '');
}
define('DB_NAME', $__effective_db);

/**
 * Central registry schema (AURAGOLD_REGISTRY_DB): canonical tbl_branches rows (db_name, hierarchy).
 * $conn_master often points at the first main branch *operational* DB; tbl_branches there can be a stale replica.
 */
$GLOBALS['auragold_registry_mysqli'] = null;
$__aur_reg = @mysqli_connect($__db_host, $__boot_user, $__boot_pass, (string) AURAGOLD_REGISTRY_DB);
if ($__aur_reg) {
    mysqli_set_charset($__aur_reg, 'utf8mb4');
    $GLOBALS['auragold_registry_mysqli'] = $__aur_reg;
}

// Session must be active before reading working_db (some legacy files included config first).
$__auragold_session_init = __DIR__ . '/includes/session_init.php';
if (function_exists('session_status') && PHP_SAPI !== 'cli') {
    if (session_status() === PHP_SESSION_NONE) {
        if (is_file($__auragold_session_init)) {
            require_once $__auragold_session_init;
        } else {
            @session_start();
        }
    } elseif (session_status() === PHP_SESSION_ACTIVE && is_file($__auragold_session_init)) {
        // Legacy scripts call session_start() before config; load idle timeout + sliding refresh.
        require_once $__auragold_session_init;
    }
}

// Working connection: branch DB from tbl_branches (session), else same as master.
// After login, $_SESSION['working_db'] and $_SESSION['db_name'] are set in branch_working_context.php
// from the selected branch row (tbl_branches.db_name). Login passwords are checked against that schema
// (see login_credential_connections.php + login_submit.php).
$conn = $conn_master;
if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE
    && !empty($_SESSION['working_db']) && is_array($_SESSION['working_db'])) {
    $wdb    = $_SESSION['working_db'];
    $dbname = trim((string) ($wdb['database'] ?? $wdb['db_name'] ?? ''));
    if ($dbname !== '') {
        $dbuser = trim((string) ($wdb['user'] ?? $wdb['db_user'] ?? $wdb['db_users'] ?? ''));
        $dbpass = (string) ($wdb['password'] ?? $wdb['db_pass'] ?? $wdb['db_password'] ?? '');
        if ($dbuser === '') {
            $dbuser = DB_USER;
            $dbpass = DB_PASS;
        }
        // Avoid hanging AJAX (e.g. permission save) when branch host/credentials are slow or unreachable.
        $conn_branch = null;
        if (function_exists('mysqli_init')) {
            $conn_branch = mysqli_init();
            if ($conn_branch) {
                @mysqli_options($conn_branch, MYSQLI_OPT_CONNECT_TIMEOUT, 5);
                @mysqli_real_connect($conn_branch, DB_HOST, $dbuser, $dbpass, $dbname);
                if (mysqli_connect_errno()) {
                    mysqli_close($conn_branch);
                    $conn_branch = null;
                }
            }
        }
        if (!$conn_branch) {
            $conn_branch = @mysqli_connect(DB_HOST, $dbuser, $dbpass, $dbname);
        }
        if ($conn_branch) {
            mysqli_set_charset($conn_branch, "utf8mb4");
            $conn = $conn_branch;
        } else {
            // Session had a branch database selected; do not fall back to registry/host main DB in this request.
            // (Previously, unsetting working_db and requiring user_id>0 let the host-branch block below run and
            // reattach $conn to the main/auragold-side operational DB while the session could still look "active".)
            $hadBranchDb = ($dbname !== '');
            $branchEntry  = (int) ($_SESSION['working_branch_id'] ?? $_SESSION['branch_id'] ?? 0);
            unset($_SESSION['working_db'], $_SESSION['working_branch_id'], $_SESSION['working_branch_name'], $_SESSION['branch_id']);
            if ($hadBranchDb && function_exists('auragold_session_force_logout_redirect')) {
                auragold_session_force_logout_redirect(
                    'Could not connect to branch database. Please sign in again.',
                    $branchEntry
                );
            }
        }
    }
}

// Logged in as a specific tbl_branches row (> 0) but working_db missing — do not attach host/subdomain "MAIN" DB.
require_once __DIR__ . '/includes/auragold_require_login.php';
if (PHP_SAPI !== 'cli'
    && function_exists('session_status')
    && session_status() === PHP_SESSION_ACTIVE
    && function_exists('auragold_is_logged_in_session')
    && auragold_is_logged_in_session()
    && empty($_SESSION['working_db'])) {
    $lbChoice = (int) ($_SESSION['auragold_login_branch_id'] ?? -1);
    if ($lbChoice > 0 && function_exists('auragold_session_force_logout_redirect')) {
        $be = (int) ($_SESSION['branch_id'] ?? $_SESSION['working_branch_id'] ?? 0);
        auragold_session_force_logout_redirect(
            'Branch session ended or the branch database is unreachable. Please sign in again.',
            $be > 0 ? $be : $lbChoice
        );
    }
}

// Default operational DB from HTTP host (subdomain → tbl_branches.code) when session has no branch DB selected
if (PHP_SAPI !== 'cli'
    && function_exists('session_status')
    && session_status() === PHP_SESSION_ACTIVE
    && empty($_SESSION['working_db'])
    && function_exists('auragold_connect_operational_for_host_branch')) {
    $hostConn = auragold_connect_operational_for_host_branch($conn_master, DB_HOST, DB_USER, DB_PASS);
    if ($hostConn instanceof mysqli) {
        mysqli_set_charset($hostConn, 'utf8mb4');
        $conn = $hostConn;
    }
}

if (isset($conn) && $conn instanceof mysqli) {
    $___abs = __DIR__ . '/includes/auragold_product_branch_local_schema.php';
    if (is_file($___abs)) {
        require_once $___abs;
        if (function_exists('auragold_ensure_tbl_product_branches_is_active')) {
            auragold_ensure_tbl_product_branches_is_active($conn);
        }
    }
    $___nat = __DIR__ . '/includes/nationalities_bootstrap.php';
    if (is_file($___nat)) {
        require_once $___nat;
        if (function_exists('auragold_ensure_tbl_nationalities_seeded')) {
            auragold_ensure_tbl_nationalities_seeded($conn);
        }
    }
    $___doctype = __DIR__ . '/includes/document_types_schema.php';
    if (is_file($___doctype)) {
        require_once $___doctype;
        if (function_exists('auragold_ensure_tbl_document_types')) {
            auragold_ensure_tbl_document_types($conn);
        }
    }
    $___srv = __DIR__ . '/includes/auragold_sale_receipt_voucher_schema.php';
    if (is_file($___srv)) {
        require_once $___srv;
        if (function_exists('auragold_ensure_tbl_sale_receipt_vouchers')) {
            auragold_ensure_tbl_sale_receipt_vouchers($conn);
        }
    }
}

function getList($sql){
    global $conn;
    $data = [];
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $data[] = $row;
        }
    }
    return $data;
}

function getRecord($sql){
    global $conn;
    $res = mysqli_query($conn, $sql);
    return ($res && mysqli_num_rows($res) > 0) ? mysqli_fetch_assoc($res) : null;
}

/** Registry / central DB (tbl_branches, user-management when HQ uses registry; not for login password checks). */
function getListMaster($sql){
    global $conn_master;
    $data = [];
    $res = mysqli_query($conn_master, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $data[] = $row;
        }
    }
    return $data;
}

function getRecordMaster($sql){
    global $conn_master;
    $res = mysqli_query($conn_master, $sql);
    return ($res && mysqli_num_rows($res) > 0) ? mysqli_fetch_assoc($res) : null;
}

if (PHP_SAPI !== 'cli' && function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
    require_once __DIR__ . '/includes/login_financial_years_helper.php';
    if (function_exists('auragold_enforce_session_operational_health')) {
        auragold_enforce_session_operational_health();
    }
}

require_once __DIR__ . '/includes/auragold_branch_data_scope.php';

if (is_file(__DIR__ . '/includes/auragold_i18n.php')) {
    require_once __DIR__ . '/includes/auragold_i18n.php';
    if (isset($conn) && $conn instanceof mysqli) {
        auragold_bootstrap_i18n($conn);
    }
}

if (PHP_SAPI !== 'cli' && function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
    require_once __DIR__ . '/includes/auragold_minimal_nav_gate.php';
}

function getRecords($sql){
    return getList($sql);
}

function getRow($sql){
    global $conn;
    $res = mysqli_query($conn, $sql);
    return ($res) ? mysqli_num_rows($res) : 0;
}

/** Always escape against master charset (stable for registry SQL strings). */
function esc($str){
    global $conn, $conn_master;
    $link = (isset($conn) && $conn instanceof mysqli) ? $conn : $conn_master;
    if (!($link instanceof mysqli)) {
        return trim((string) $str);
    }

    return mysqli_real_escape_string($link, trim((string) $str));
}

/**
 * Partial sale return: link tbl_sale_return_items to source line (tbl_sale_invoice_items.id / tbl_sale_quotation_items.id).
 */
function auragold_ensure_sale_return_item_source_against_id($conn) {
    static $done = [];
    $key = is_object($conn) ? spl_object_hash($conn) : 'default';
    if (!empty($done[$key])) {
        return;
    }
    $done[$key] = true;
    $c = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_return_items LIKE 'source_against_item_id'");
    if (!$c) {
        return;
    }
    if (mysqli_num_rows($c) === 0) {
        @mysqli_query($conn, "ALTER TABLE tbl_sale_return_items ADD COLUMN source_against_item_id INT(11) NULL DEFAULT NULL AFTER return_id");
    }
    mysqli_free_result($c);
}

/**
 * SQL predicate (use with alias si = tbl_sale_invoice_items): line not yet returned against this invoice.
 *
 * @param int $exclude_return_id When editing a return, ignore its rows so its lines stay "available".
 */
function auragold_sale_return_pending_invoice_item_predicate_sql($exclude_return_id, $lineAlias = 'si') {
    $a = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $lineAlias);
    if ($a === '') {
        $a = 'si';
    }
    $ex = (int) $exclude_return_id;
    $exSql = ($ex > 0) ? ' AND sr.id != ' . $ex . ' ' : '';
    return "NOT EXISTS (
      SELECT 1 FROM tbl_sale_return_items sri
      INNER JOIN tbl_sale_returns sr ON sr.id = sri.return_id
      WHERE sr.against_id = {$a}.invoice_id
        AND (TRIM(COALESCE(sr.against_type,'')) = 'Sale Invoice' OR TRIM(COALESCE(sr.against_of,'')) = 'Sale Invoice')
        $exSql
        AND (
          (sri.source_against_item_id IS NOT NULL AND sri.source_against_item_id > 0 AND sri.source_against_item_id = {$a}.id)
          OR (
            (sri.source_against_item_id IS NULL OR sri.source_against_item_id = 0)
            AND TRIM(COALESCE({$a}.barcode,'')) <> ''
            AND TRIM(COALESCE({$a}.barcode,'')) = TRIM(COALESCE(sri.barcode,''))
          )
        )
    )";
}

/**
 * Same for sale quotation lines (default alias sqi = tbl_sale_quotation_items).
 */
function auragold_sale_return_pending_quotation_item_predicate_sql($exclude_return_id, $lineAlias = 'sqi') {
    $a = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $lineAlias);
    if ($a === '') {
        $a = 'sqi';
    }
    $ex = (int) $exclude_return_id;
    $exSql = ($ex > 0) ? ' AND sr.id != ' . $ex . ' ' : '';
    return "NOT EXISTS (
      SELECT 1 FROM tbl_sale_return_items sri
      INNER JOIN tbl_sale_returns sr ON sr.id = sri.return_id
      WHERE sr.against_id = {$a}.quotation_id
        AND (TRIM(COALESCE(sr.against_type,'')) = 'Sale Quotation' OR TRIM(COALESCE(sr.against_of,'')) = 'Sale Quotation')
        $exSql
        AND (
          (sri.source_against_item_id IS NOT NULL AND sri.source_against_item_id > 0 AND sri.source_against_item_id = {$a}.id)
          OR (
            (sri.source_against_item_id IS NULL OR sri.source_against_item_id = 0)
            AND TRIM(COALESCE({$a}.barcode,'')) <> ''
            AND TRIM(COALESCE({$a}.barcode,'')) = TRIM(COALESCE(sri.barcode,''))
          )
        )
    )";
}

/**
 * Account ledger "Against Ledger" on Cash/Bank lines when paying a party:
 * e.g. RK Jewellers(Bank - 200.00Dr), RK Jewellers(UPI - 800.00Dr)
 */
function accountledger_against_party_payment_label($party_name, $payment_type_raw, $line_amount) {
    $party_name = trim((string) $party_name);
    $pt = strtolower(trim((string) $payment_type_raw));
    $map = [
        'cash' => 'Cash',
        'bank' => 'Bank',
        'upi' => 'UPI',
        'cheque' => 'Cheque',
        'check' => 'Cheque',
        'card' => 'Card',
        'metal' => 'Metal',
        'scrap' => 'Scrap',
    ];
    $label = isset($map[$pt]) ? $map[$pt] : ($pt !== '' ? ucfirst($pt) : 'Payment');
    $amt = number_format(abs((float) $line_amount), 2, '.', '');
    return $party_name . '(' . $label . ' - ' . $amt . 'Dr)';
}

/** Extract purchase invoice number from tbl_sale_fixing_direct.against_of (e.g. PI-6, PRI2). */
function auragold_pi_invoice_from_sfd_against_of($against_of) {
    $ao = trim((string)$against_of);
    if ($ao === '') {
        return '';
    }
    if (preg_match('/\b(PI-\d+)\b/i', $ao, $m)) {
        return $m[1];
    }
    if (preg_match('/\b(PRI\d+)\b/i', $ao, $m)) {
        return $m[1];
    }
    return '';
}

/** Invoice numbers (uppercase) that still have a non-deleted Sale Fixing Direct row. */
function auragold_pi_invoice_nos_with_active_sale_fixing() {
    global $conn;
    $out = [];
    $check = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_fixing_direct'");
    if (!$check || mysqli_num_rows($check) == 0) {
        if ($check) {
            mysqli_free_result($check);
        }
        return $out;
    }
    mysqli_free_result($check);
    $has_status = false;
    $col = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_fixing_direct LIKE 'status'");
    if ($col && mysqli_num_rows($col) > 0) {
        $has_status = true;
    }
    if ($col) {
        mysqli_free_result($col);
    }
    $sql = $has_status
        ? 'SELECT against_of, status FROM tbl_sale_fixing_direct'
        : 'SELECT against_of FROM tbl_sale_fixing_direct';
    $rows = getList($sql);
    foreach ($rows as $row) {
        if ($has_status && isset($row['status']) && strtolower(trim((string)$row['status'])) === 'deleted') {
            continue;
        }
        $pi = auragold_pi_invoice_from_sfd_against_of($row['against_of'] ?? '');
        if ($pi !== '') {
            $out[strtoupper($pi)] = true;
        }
    }
    return $out;
}

/** Whether a purchase invoice still has an active Sale Fixing linked (delete PI only after SFD removed). */
function auragold_pi_has_active_sale_fixing($invoice_no) {
    $map = auragold_pi_invoice_nos_with_active_sale_fixing();
    return !empty($map[strtoupper(trim((string)$invoice_no))]);
}

/** Sale invoice number from tbl_purchase_fixing_direct.against_of (e.g. "Fixing of SPK15" → "SPK15"). */
function auragold_si_invoice_from_pfd_against_of($against_of) {
    $ao = trim((string) $against_of);
    if ($ao === '') {
        return '';
    }
    if (preg_match('/Fixing of\s+(\S+)/i', $ao, $m)) {
        return trim($m[1]);
    }
    return '';
}

/** Invoice numbers (uppercase) that still have a non-deleted Purchase Fixing Direct row (delete SI only after PFD removed). */
function auragold_si_invoice_nos_with_active_purchase_fixing() {
    global $conn;
    $out = [];
    $check = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_purchase_fixing_direct'");
    if (!$check || mysqli_num_rows($check) == 0) {
        if ($check) {
            mysqli_free_result($check);
        }
        return $out;
    }
    mysqli_free_result($check);

    $has_status = false;
    $st = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_fixing_direct LIKE 'status'");
    if ($st && mysqli_num_rows($st) > 0) {
        $has_status = true;
    }
    if ($st) {
        mysqli_free_result($st);
    }

    $has_sale_si_col = false;
    $sc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_fixing_direct LIKE 'sale_invoice_no'");
    if ($sc && mysqli_num_rows($sc) > 0) {
        $has_sale_si_col = true;
    }
    if ($sc) {
        mysqli_free_result($sc);
    }

    $fields = $has_sale_si_col ? 'sale_invoice_no, against_of' : 'against_of';
    if ($has_status) {
        $fields .= ', status';
    }
    $rows = getList("SELECT $fields FROM tbl_purchase_fixing_direct");
    if (!is_array($rows)) {
        return $out;
    }
    foreach ($rows as $row) {
        if ($has_status && isset($row['status']) && strtolower(trim((string) $row['status'])) === 'deleted') {
            continue;
        }
        $si = '';
        if ($has_sale_si_col && trim((string) ($row['sale_invoice_no'] ?? '')) !== '') {
            $si = trim((string) $row['sale_invoice_no']);
        }
        if ($si === '') {
            $si = auragold_si_invoice_from_pfd_against_of($row['against_of'] ?? '');
        }
        if ($si !== '') {
            $out[strtoupper($si)] = true;
        }
    }
    return $out;
}

/** Whether a sale invoice still has an active Purchase Fixing linked (edit/delete SI only after PFD removed). */
function auragold_si_has_active_purchase_fixing($invoice_no) {
    global $conn;
    $inv = trim((string) $invoice_no);
    if ($inv === '') {
        return false;
    }
    $tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_purchase_fixing_direct'");
    if (!$tbl || mysqli_num_rows($tbl) == 0) {
        if ($tbl) {
            mysqli_free_result($tbl);
        }
        return false;
    }
    mysqli_free_result($tbl);

    $esc = mysqli_real_escape_string($conn, $inv);
    $ao_exact = mysqli_real_escape_string($conn, 'Fixing of ' . $inv);

    $status_sql = '';
    $stc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_fixing_direct LIKE 'status'");
    if ($stc && mysqli_num_rows($stc) > 0) {
        $status_sql = " AND (status IS NULL OR LOWER(TRIM(status)) <> 'deleted')";
    }
    if ($stc) {
        mysqli_free_result($stc);
    }

    $parts = [
        "LOWER(TRIM(COALESCE(against_of,''))) = LOWER('$ao_exact')",
        "against_of LIKE 'Fixing of $esc%'",
    ];

    $scc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_fixing_direct LIKE 'sale_invoice_no'");
    if ($scc && mysqli_num_rows($scc) > 0) {
        $parts[] = "TRIM(COALESCE(sale_invoice_no,'')) = '$esc'";
        $parts[] = "LOWER(TRIM(COALESCE(sale_invoice_no,''))) = LOWER('$esc')";
    }
    if ($scc) {
        mysqli_free_result($scc);
    }

    $sql = "SELECT id FROM tbl_purchase_fixing_direct WHERE (" . implode(' OR ', $parts) . ")" . $status_sql . " LIMIT 1";
    $row = getRecord($sql);
    return $row !== null;
}

/**
 * Remove Purchase Fixing Direct voucher(s) for a sale invoice number, including tbl_purchase_fixing_direct_items rows.
 */
function auragold_delete_purchase_fixing_direct_for_sale_invoice($conn, $invoice_no) {
    $inv = trim((string) $invoice_no);
    if ($inv === '') {
        return;
    }
    $inv_esc_rm = mysqli_real_escape_string($conn, $inv);
    $ao_exact = mysqli_real_escape_string($conn, 'Fixing of ' . $inv);

    $pf_tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_purchase_fixing_direct'");
    if (!$pf_tbl || mysqli_num_rows($pf_tbl) == 0) {
        if ($pf_tbl) {
            mysqli_free_result($pf_tbl);
        }
        return;
    }
    mysqli_free_result($pf_tbl);

    $pit = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_purchase_fixing_direct_items'");
    $has_items = ($pit && mysqli_num_rows($pit) > 0);
    if ($pit) {
        mysqli_free_result($pit);
    }

    $sc = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_fixing_direct LIKE 'sale_invoice_no'");
    $has_si = ($sc && mysqli_num_rows($sc) > 0);
    if ($sc) {
        mysqli_free_result($sc);
    }

    if ($has_items) {
        if ($has_si) {
            @mysqli_query($conn, "DELETE pfi FROM tbl_purchase_fixing_direct_items pfi INNER JOIN tbl_purchase_fixing_direct pf ON pfi.fixing_id = pf.id WHERE pf.sale_invoice_no = '$inv_esc_rm' OR pf.against_of = '$ao_exact' OR pf.against_of LIKE 'Fixing of $inv_esc_rm%'");
        } else {
            @mysqli_query($conn, "DELETE pfi FROM tbl_purchase_fixing_direct_items pfi INNER JOIN tbl_purchase_fixing_direct pf ON pfi.fixing_id = pf.id WHERE pf.against_of = '$ao_exact' OR pf.against_of LIKE 'Fixing of $inv_esc_rm%'");
        }
    }
    if ($has_si) {
        @mysqli_query($conn, "DELETE FROM tbl_purchase_fixing_direct WHERE sale_invoice_no = '$inv_esc_rm' OR against_of = '$ao_exact' OR against_of LIKE 'Fixing of $inv_esc_rm%'");
    } else {
        @mysqli_query($conn, "DELETE FROM tbl_purchase_fixing_direct WHERE against_of = '$ao_exact' OR against_of LIKE 'Fixing of $inv_esc_rm%'");
    }
}

/**
 * Count bills/transactions that use the given voucher_type_id.
 * If count > 0, the Bill Series for this voucher type is locked (no edit/delete).
 * Checks: tbl_purchase_invoice_items.voucher_type, tbl_stock_journal.voucher_type,
 * and any other tables that store voucher_type (id as string).
 *
 * @param mysqli $conn
 * @param int $voucher_type_id
 * @return int Total count across all bill tables
 */
function countBillsForVoucherType($conn, $voucher_type_id) {
    $id = (int) $voucher_type_id;
    if ($id <= 0) return 0;
    $id_esc = mysqli_real_escape_string($conn, (string)$id);
    $total = 0;
    // Purchase invoice items: voucher_type stores voucher type id (string)
    $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM tbl_purchase_invoice_items WHERE voucher_type = '$id_esc' AND active = 1");
    if ($r && $row = mysqli_fetch_assoc($r)) $total += (int)$row['c'];
    // Stock journal: voucher_type
    $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM tbl_stock_journal WHERE voucher_type = '$id_esc' AND status = 'active'");
    if ($r && $row = mysqli_fetch_assoc($r)) $total += (int)$row['c'];
    // Sale invoices: lock "Sales Invoice" bill series when any sale invoice exists
    $svt = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(name)) = 'sales invoice' LIMIT 1");
    if ($svt && (int)$svt['id'] === $id) {
        $saleWhere = '';
        $col_check = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_invoices LIKE 'invoice_type'");
        if ($col_check && mysqli_num_rows($col_check) > 0) {
            mysqli_free_result($col_check);
            $saleWhere = " WHERE (invoice_type = 'sale' OR invoice_type IS NULL)";
        } elseif ($col_check) {
            mysqli_free_result($col_check);
        }
        $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM tbl_sale_invoices" . $saleWhere);
        if ($r && $row = mysqli_fetch_assoc($r)) {
            $total += (int)$row['c'];
        }
    } else {
        $cols = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_invoices LIKE 'voucher_type_id'");
        if ($cols && mysqli_num_rows($cols) > 0) {
            $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM tbl_sale_invoices WHERE voucher_type_id = $id");
            if ($r && $row = mysqli_fetch_assoc($r)) {
                $total += (int)$row['c'];
            }
        }
        if ($cols) {
            mysqli_free_result($cols);
        }
    }
    // Job Work Order: lock series when any row exists in tbl_jobwork_orders
    $jwoVt = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND (LOWER(TRIM(name)) = 'jobwork order' OR LOWER(TRIM(name)) = 'job work order') ORDER BY id ASC LIMIT 1");
    if ($jwoVt && (int)$jwoVt['id'] === $id) {
        $jt = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_orders'");
        if ($jt && mysqli_num_rows($jt) > 0) {
            mysqli_free_result($jt);
            $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM tbl_jobwork_orders");
            if ($r && $row = mysqli_fetch_assoc($r)) {
                $total += (int)$row['c'];
            }
        } elseif ($jt) {
            mysqli_free_result($jt);
        }
    }
    // Material Issue: lock series when any row exists in material issue tables
    $miVt = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(name)) = 'material issue' ORDER BY id ASC LIMIT 1");
    if ($miVt && (int)$miVt['id'] === $id) {
        $mt = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_material_issues'");
        if ($mt && mysqli_num_rows($mt) > 0) {
            mysqli_free_result($mt);
            $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM tbl_material_issues");
            if ($r && $row = mysqli_fetch_assoc($r)) {
                $total += (int)$row['c'];
            }
        } elseif ($mt) {
            mysqli_free_result($mt);
        }
        $mtr = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_repair_material_issues'");
        if ($mtr && mysqli_num_rows($mtr) > 0) {
            mysqli_free_result($mtr);
            $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM tbl_repair_material_issues");
            if ($r && $row = mysqli_fetch_assoc($r)) {
                $total += (int)$row['c'];
            }
        } elseif ($mtr) {
            mysqli_free_result($mtr);
        }
    }
    // Material Receive: lock series when any row exists in material receive tables
    $mrVt = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(name)) = 'material receive' ORDER BY id ASC LIMIT 1");
    if ($mrVt && (int)$mrVt['id'] === $id) {
        $mrx = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_material_receives'");
        if ($mrx && mysqli_num_rows($mrx) > 0) {
            mysqli_free_result($mrx);
            $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM tbl_material_receives");
            if ($r && $row = mysqli_fetch_assoc($r)) {
                $total += (int)$row['c'];
            }
        } elseif ($mrx) {
            mysqli_free_result($mrx);
        }
        $mrr = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_repair_material_receives'");
        if ($mrr && mysqli_num_rows($mrr) > 0) {
            mysqli_free_result($mrr);
            $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM tbl_repair_material_receives");
            if ($r && $row = mysqli_fetch_assoc($r)) {
                $total += (int)$row['c'];
            }
        } elseif ($mrr) {
            mysqli_free_result($mrr);
        }
    }
    // Consignment In: lock series when any row exists in tbl_consignment_in
    $ciVt = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(name)) = 'consignment in' ORDER BY id ASC LIMIT 1");
    if ($ciVt && (int)$ciVt['id'] === $id) {
        $cit = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_consignment_in'");
        if ($cit && mysqli_num_rows($cit) > 0) {
            mysqli_free_result($cit);
            $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM tbl_consignment_in");
            if ($r && $row = mysqli_fetch_assoc($r)) {
                $total += (int)$row['c'];
            }
        } elseif ($cit) {
            mysqli_free_result($cit);
        }
    }
    // Payment Voucher: lock series when any row exists
    $pvVt = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(name)) = 'payment voucher' LIMIT 1");
    if ($pvVt && (int)$pvVt['id'] === $id) {
        $pvt = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_payment_vouchers'");
        if ($pvt && mysqli_num_rows($pvt) > 0) {
            mysqli_free_result($pvt);
            $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM tbl_payment_vouchers");
            if ($r && $row = mysqli_fetch_assoc($r)) {
                $total += (int)$row['c'];
            }
        } elseif ($pvt) {
            mysqli_free_result($pvt);
        }
    }
    // Receipt Voucher (manual RV- series; excludes sale-auto lines identified by voucher_type Sale Invoice Payment)
    $rcvVt = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(name)) = 'receipt voucher' LIMIT 1");
    if ($rcvVt && (int)$rcvVt['id'] === $id) {
        $rvt = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_receipt_vouchers'");
        if ($rvt && mysqli_num_rows($rvt) > 0) {
            mysqli_free_result($rvt);
            $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM tbl_receipt_vouchers WHERE COALESCE(voucher_type,'') <> 'Sale Invoice Payment'");
            if ($r && $row = mysqli_fetch_assoc($r)) {
                $total += (int)$row['c'];
            }
        } elseif ($rvt) {
            mysqli_free_result($rvt);
        }
    }
    // Sale Receipt Voucher (tbl_sale_receipt_vouchers — auto from sale / POS invoice)
    $srvVt = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(name)) = 'sale receipt voucher' LIMIT 1");
    if ($srvVt && (int)$srvVt['id'] === $id) {
        $srvt = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_receipt_vouchers'");
        if ($srvt && mysqli_num_rows($srvt) > 0) {
            mysqli_free_result($srvt);
            $r = mysqli_query($conn, 'SELECT COUNT(*) AS c FROM tbl_sale_receipt_vouchers');
            if ($r && $row = mysqli_fetch_assoc($r)) {
                $total += (int)$row['c'];
            }
        } elseif ($srvt) {
            mysqli_free_result($srvt);
        }
    }
    // Advance Payment
    $advVt = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(name)) = 'advance payment' LIMIT 1");
    if (!$advVt || empty($advVt['id'])) {
        $advVt = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(name)) = 'advance' LIMIT 1");
    }
    if ($advVt && (int)$advVt['id'] === $id) {
        $apt = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_advance_payments'");
        if ($apt && mysqli_num_rows($apt) > 0) {
            mysqli_free_result($apt);
            $r = mysqli_query($conn, "SELECT COUNT(*) AS c FROM tbl_advance_payments");
            if ($r && $row = mysqli_fetch_assoc($r)) {
                $total += (int)$row['c'];
            }
        } elseif ($apt) {
            mysqli_free_result($apt);
        }
    }
    return $total;
}

/**
 * Bill series config for Sale Invoice (tbl_bill_series row for voucher type "Sales Invoice").
 * If no row or table missing, returns legacy SI- / start 1 (same as old behavior).
 *
 * @return array{prefix:string,suffix:string,start_count:int,from_series_table:bool,voucher_type_id?:int}
 */
function getSaleInvoiceBillSeriesConfig($conn) {
    $legacy = ['prefix' => 'SI-', 'suffix' => '', 'start_count' => 1, 'from_series_table' => false];
    $vtId = 0;
    $r = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(name)) = 'sales invoice' LIMIT 1");
    if ($r && !empty($r['id'])) {
        $vtId = (int)$r['id'];
    }
    if ($vtId <= 0) {
        $r2 = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(COALESCE(type_of_voucher,''))) = 'sales invoice' LIMIT 1");
        if ($r2 && !empty($r2['id'])) {
            $vtId = (int)$r2['id'];
        }
    }
    $tableCheck = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_bill_series'");
    if (!$tableCheck || mysqli_num_rows($tableCheck) === 0) {
        if ($tableCheck) {
            mysqli_free_result($tableCheck);
        }
        return $legacy;
    }
    mysqli_free_result($tableCheck);
    if ($vtId <= 0) {
        return $legacy;
    }
    $series = auragold_bill_series_row_for_voucher_type($conn, $vtId);
    if (!$series || trim((string)($series['prefix'] ?? '')) === '') {
        return $legacy;
    }
    return [
        'prefix' => (string)$series['prefix'],
        'suffix' => (string)($series['suffix'] ?? ''),
        'start_count' => (int)($series['start_count'] ?? 0),
        'from_series_table' => true,
        'voucher_type_id' => $vtId,
    ];
}

/**
 * Next sale invoice number: prefix + next numeric + suffix (e.g. SP10, SP11 from prefix SP, start 10).
 * Uses max(existing serial matching pattern across tbl_sale_invoices and tbl_pos_sale_invoices, start_count) + 1;
 * legacy SI-1, SI-2 when no series. POS invoices share this series and sequence.
 */
function getNextSaleInvoiceNo($conn) {
    $cfg = getSaleInvoiceBillSeriesConfig($conn);
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $start = (int)($cfg['start_count'] ?? 0);
    $startEff = max(1, $start);

    $si_invoice_type_where = '';
    $col_check = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_invoices LIKE 'invoice_type'");
    if ($col_check && mysqli_num_rows($col_check) > 0) {
        mysqli_free_result($col_check);
        $si_invoice_type_where = " AND (invoice_type = 'sale' OR invoice_type IS NULL)";
    } elseif ($col_check) {
        mysqli_free_result($col_check);
    }

    $pos_invoice_type_where = '';
    $col_check_pos = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_pos_sale_invoices LIKE 'invoice_type'");
    if ($col_check_pos && mysqli_num_rows($col_check_pos) > 0) {
        mysqli_free_result($col_check_pos);
        $pos_invoice_type_where = " AND (invoice_type = 'sale' OR invoice_type IS NULL)";
    } elseif ($col_check_pos) {
        mysqli_free_result($col_check_pos);
    }

    $prefix_esc = mysqli_real_escape_string($conn, $prefix);
    $rows = getList("SELECT invoice_no FROM tbl_sale_invoices WHERE invoice_no LIKE '$prefix_esc%'" . $si_invoice_type_where);
    if (!is_array($rows)) {
        $rows = [];
    }

    $tableCheckPos = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_pos_sale_invoices'");
    if ($tableCheckPos && mysqli_num_rows($tableCheckPos) > 0) {
        mysqli_free_result($tableCheckPos);
        $rows_pos = getList("SELECT invoice_no FROM tbl_pos_sale_invoices WHERE invoice_no LIKE '$prefix_esc%'" . $pos_invoice_type_where);
        if (is_array($rows_pos) && $rows_pos !== []) {
            $rows = array_merge($rows, $rows_pos);
        }
    } elseif ($tableCheckPos) {
        mysqli_free_result($tableCheckPos);
    }

    $maxNum = 0;
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
    foreach ($rows as $row) {
        $inv = (string)($row['invoice_no'] ?? '');
        if (preg_match($regex, $inv, $m)) {
            $maxNum = max($maxNum, (int)$m[1]);
        }
    }
    $nextNum = max($maxNum + 1, $startEff);
    return $prefix . $nextNum . $suffix;
}

/**
 * Increment a sale invoice number that matches current bill series pattern (collision / race handling).
 */
function bumpSaleInvoiceNo($conn, $invoice_no, array $cfg) {
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
    if (preg_match($regex, (string)$invoice_no, $m)) {
        return $prefix . ((int)$m[1] + 1) . $suffix;
    }
    return getNextSaleInvoiceNo($conn);
}

/**
 * POS sale invoices use the same bill series as Sales Invoice (see getSaleInvoiceBillSeriesConfig).
 *
 * @return array{prefix:string,suffix:string,start_count:int,from_series_table:bool,voucher_type_id?:int}
 */
function getPosSaleInvoiceBillSeriesConfig($conn) {
    return getSaleInvoiceBillSeriesConfig($conn);
}

/**
 * Next POS invoice number — same prefix/sequence as Sales Invoice (getNextSaleInvoiceNo).
 */
function getNextPosSaleInvoiceNo($conn) {
    return getNextSaleInvoiceNo($conn);
}

/**
 * Increment a POS sale invoice number (same pattern rules as bumpSaleInvoiceNo).
 */
function bumpPosSaleInvoiceNo($conn, $invoice_no, array $cfg) {
    return bumpSaleInvoiceNo($conn, $invoice_no, $cfg);
}

/**
 * Bill series for Old Jewelry / Old Jewellery Scrap Invoice (tbl_bill_series + matching tbl_voucher_types row).
 * Legacy default: OJB-1, OJB-2 when no series row.
 *
 * @return array{prefix:string,suffix:string,start_count:int,from_series_table:bool,voucher_type_id?:int}
 */
function getOldJewelryScrapInvoiceBillSeriesConfig($conn) {
    $legacy = ['prefix' => 'OJB-', 'suffix' => '', 'start_count' => 1, 'from_series_table' => false];
    $vtId = 0;
    foreach (['old jewelry scrap invoice', 'old jewellery scrap invoice', 'old jewelry scrap', 'old jewellery scrap'] as $nm) {
        $esc = mysqli_real_escape_string($conn, $nm);
        $r = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(name)) = '$esc' LIMIT 1");
        if ($r && !empty($r['id'])) {
            $vtId = (int) $r['id'];
            break;
        }
    }
    if ($vtId <= 0) {
        foreach (['old jewelry scrap invoice', 'old jewellery scrap invoice', 'old jewelry scrap', 'old jewellery scrap'] as $nm) {
            $esc = mysqli_real_escape_string($conn, $nm);
            $r2 = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(COALESCE(type_of_voucher,''))) = '$esc' LIMIT 1");
            if ($r2 && !empty($r2['id'])) {
                $vtId = (int) $r2['id'];
                break;
            }
        }
    }
    $tableCheck = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_bill_series'");
    if (!$tableCheck || mysqli_num_rows($tableCheck) === 0) {
        if ($tableCheck) {
            mysqli_free_result($tableCheck);
        }
        return $legacy;
    }
    mysqli_free_result($tableCheck);
    if ($vtId <= 0) {
        return $legacy;
    }
    $series = auragold_bill_series_row_for_voucher_type($conn, $vtId);
    if (!$series || trim((string) ($series['prefix'] ?? '')) === '') {
        return $legacy;
    }
    return [
        'prefix' => (string) $series['prefix'],
        'suffix' => (string) ($series['suffix'] ?? ''),
        'start_count' => (int) ($series['start_count'] ?? 0),
        'from_series_table' => true,
        'voucher_type_id' => $vtId,
    ];
}

/**
 * Next old jewelry scrap invoice number from bill series (prefix + number + suffix) or legacy OJB-1, OJB-2.
 */
function getNextOldJewelryScrapInvoiceNo($conn) {
    $cfg = getOldJewelryScrapInvoiceBillSeriesConfig($conn);
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $start = (int) ($cfg['start_count'] ?? 0);
    $startEff = max(1, $start);

    $tableCheck = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_scrap_invoices'");
    if (!$tableCheck || mysqli_num_rows($tableCheck) === 0) {
        if ($tableCheck) {
            mysqli_free_result($tableCheck);
        }
        return $prefix . $startEff . $suffix;
    }
    mysqli_free_result($tableCheck);

    $prefix_esc = mysqli_real_escape_string($conn, $prefix);
    $rows = getList("SELECT invoice_no FROM tbl_old_jewelry_scrap_invoices WHERE invoice_no LIKE '$prefix_esc%'");

    $maxNum = 0;
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
    foreach ($rows as $row) {
        $inv = (string) ($row['invoice_no'] ?? '');
        if (preg_match($regex, $inv, $m)) {
            $maxNum = max($maxNum, (int) $m[1]);
        }
    }
    $nextNum = max($maxNum + 1, $startEff);
    return $prefix . $nextNum . $suffix;
}

/**
 * Bump scrap invoice no when duplicate (same pattern as bumpSaleInvoiceNo).
 */
function bumpOldJewelryScrapInvoiceNo($conn, $invoice_no, array $cfg) {
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
    if (preg_match($regex, (string) $invoice_no, $m)) {
        return $prefix . ((int) $m[1] + 1) . $suffix;
    }
    return getNextOldJewelryScrapInvoiceNo($conn);
}

/**
 * Bill series config for Purchase Invoice (tbl_bill_series row for voucher type "Purchase Invoice").
 * If no row or table missing, returns legacy PI- / start 1.
 *
 * @return array{prefix:string,suffix:string,start_count:int,from_series_table:bool,voucher_type_id?:int}
 */
function getPurchaseInvoiceBillSeriesConfig($conn) {
    $legacy = ['prefix' => 'PI-', 'suffix' => '', 'start_count' => 1, 'from_series_table' => false];
    $vtId = 0;
    $r = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(name)) = 'purchase invoice' LIMIT 1");
    if ($r && !empty($r['id'])) {
        $vtId = (int)$r['id'];
    }
    if ($vtId <= 0) {
        $r2 = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(COALESCE(type_of_voucher,''))) = 'purchase invoice' LIMIT 1");
        if ($r2 && !empty($r2['id'])) {
            $vtId = (int)$r2['id'];
        }
    }
    $tableCheck = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_bill_series'");
    if (!$tableCheck || mysqli_num_rows($tableCheck) === 0) {
        if ($tableCheck) {
            mysqli_free_result($tableCheck);
        }
        return $legacy;
    }
    mysqli_free_result($tableCheck);
    if ($vtId <= 0) {
        return $legacy;
    }
    $series = auragold_bill_series_row_for_voucher_type($conn, $vtId);
    if (!$series || trim((string)($series['prefix'] ?? '')) === '') {
        return $legacy;
    }
    return [
        'prefix' => (string)$series['prefix'],
        'suffix' => (string)($series['suffix'] ?? ''),
        'start_count' => (int)($series['start_count'] ?? 0),
        'from_series_table' => true,
        'voucher_type_id' => $vtId,
    ];
}

/**
 * Next purchase invoice number from bill series (prefix + next number + suffix) or legacy PI-1, PI-2.
 */
function getNextPurchaseInvoiceNo($conn) {
    $cfg = getPurchaseInvoiceBillSeriesConfig($conn);
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $start = (int)($cfg['start_count'] ?? 0);
    $startEff = max(1, $start);

    $prefix_esc = mysqli_real_escape_string($conn, $prefix);
    $rows = getList("SELECT invoice_no FROM tbl_purchase_invoices WHERE invoice_no LIKE '$prefix_esc%'");

    $maxNum = 0;
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
    foreach ($rows as $row) {
        $inv = (string)($row['invoice_no'] ?? '');
        if (preg_match($regex, $inv, $m)) {
            $maxNum = max($maxNum, (int)$m[1]);
        }
    }
    $nextNum = max($maxNum + 1, $startEff);
    return $prefix . $nextNum . $suffix;
}

/**
 * Increment a purchase invoice number matching current bill series (collision handling).
 */
function bumpPurchaseInvoiceNo($conn, $invoice_no, array $cfg) {
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
    if (preg_match($regex, (string)$invoice_no, $m)) {
        return $prefix . ((int)$m[1] + 1) . $suffix;
    }
    return getNextPurchaseInvoiceNo($conn);
}

/**
 * Bill series config for Consignment In (tbl_bill_series row for voucher type "Consignment In").
 * If no row or table missing, returns legacy CI- / start 1.
 *
 * @return array{prefix:string,suffix:string,start_count:int,from_series_table:bool,voucher_type_id?:int}
 */
function getConsignmentInBillSeriesConfig($conn) {
    $legacy = ['prefix' => 'CI-', 'suffix' => '', 'start_count' => 1, 'from_series_table' => false];
    $vtId = 0;
    $r = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(name)) = 'consignment in' LIMIT 1");
    if ($r && !empty($r['id'])) {
        $vtId = (int)$r['id'];
    }
    if ($vtId <= 0) {
        $r2 = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(COALESCE(type_of_voucher,''))) = 'consignment in' LIMIT 1");
        if ($r2 && !empty($r2['id'])) {
            $vtId = (int)$r2['id'];
        }
    }
    $tableCheck = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_bill_series'");
    if (!$tableCheck || mysqli_num_rows($tableCheck) === 0) {
        if ($tableCheck) {
            mysqli_free_result($tableCheck);
        }
        return $legacy;
    }
    mysqli_free_result($tableCheck);
    if ($vtId <= 0) {
        return $legacy;
    }
    $series = auragold_bill_series_row_for_voucher_type($conn, $vtId);
    if (!$series || trim((string)($series['prefix'] ?? '')) === '') {
        return $legacy;
    }
    return [
        'prefix' => (string)$series['prefix'],
        'suffix' => (string)($series['suffix'] ?? ''),
        'start_count' => (int)($series['start_count'] ?? 0),
        'from_series_table' => true,
        'voucher_type_id' => $vtId,
    ];
}

/**
 * Next Consignment In number from bill series (prefix + next number + suffix) or legacy CI-1, CI-2.
 */
function getNextConsignmentInNo($conn) {
    $cfg = getConsignmentInBillSeriesConfig($conn);
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $start = (int)($cfg['start_count'] ?? 0);
    $startEff = max(1, $start);

    $prefix_esc = mysqli_real_escape_string($conn, $prefix);
    $rows = getList("SELECT consignment_no FROM tbl_consignment_in WHERE consignment_no LIKE '$prefix_esc%'");

    $maxNum = 0;
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
    foreach ($rows as $row) {
        $cno = (string)($row['consignment_no'] ?? '');
        if (preg_match($regex, $cno, $m)) {
            $maxNum = max($maxNum, (int)$m[1]);
        }
    }
    $nextNum = max($maxNum + 1, $startEff);
    return $prefix . $nextNum . $suffix;
}

/**
 * Increment a consignment in number matching current bill series (collision handling).
 */
function bumpConsignmentInNo($conn, $consignment_no, array $cfg) {
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
    if (preg_match($regex, (string)$consignment_no, $m)) {
        return $prefix . ((int)$m[1] + 1) . $suffix;
    }
    return getNextConsignmentInNo($conn);
}

/**
 * Bill series config for Consignment Out (tbl_bill_series row for voucher type "Consignment Out").
 * If no row or table missing, returns legacy CO- / start 1.
 *
 * @return array{prefix:string,suffix:string,start_count:int,from_series_table:bool,voucher_type_id?:int}
 */
function getConsignmentOutBillSeriesConfig($conn) {
    $legacy = ['prefix' => 'CO-', 'suffix' => '', 'start_count' => 1, 'from_series_table' => false];
    $vtId = 0;
    $r = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(name)) = 'consignment out' LIMIT 1");
    if ($r && !empty($r['id'])) {
        $vtId = (int)$r['id'];
    }
    if ($vtId <= 0) {
        $r2 = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(COALESCE(type_of_voucher,''))) = 'consignment out' LIMIT 1");
        if ($r2 && !empty($r2['id'])) {
            $vtId = (int)$r2['id'];
        }
    }
    $tableCheck = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_bill_series'");
    if (!$tableCheck || mysqli_num_rows($tableCheck) === 0) {
        if ($tableCheck) {
            mysqli_free_result($tableCheck);
        }
        return $legacy;
    }
    mysqli_free_result($tableCheck);
    if ($vtId <= 0) {
        return $legacy;
    }
    $series = auragold_bill_series_row_for_voucher_type($conn, $vtId);
    if (!$series || trim((string)($series['prefix'] ?? '')) === '') {
        return $legacy;
    }
    return [
        'prefix' => (string)$series['prefix'],
        'suffix' => (string)($series['suffix'] ?? ''),
        'start_count' => (int)($series['start_count'] ?? 0),
        'from_series_table' => true,
        'voucher_type_id' => $vtId,
    ];
}

/**
 * Next Consignment Out number from bill series (prefix + next number + suffix) or legacy CO-1, CO-2.
 */
function getNextConsignmentOutNo($conn) {
    $cfg = getConsignmentOutBillSeriesConfig($conn);
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $start = (int)($cfg['start_count'] ?? 0);
    $startEff = max(1, $start);

    $prefix_esc = mysqli_real_escape_string($conn, $prefix);
    $rows = getList("SELECT consignment_no FROM tbl_consignment_out WHERE consignment_no LIKE '$prefix_esc%'");

    $maxNum = 0;
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
    foreach ($rows as $row) {
        $cno = (string)($row['consignment_no'] ?? '');
        if (preg_match($regex, $cno, $m)) {
            $maxNum = max($maxNum, (int)$m[1]);
        }
    }
    $nextNum = max($maxNum + 1, $startEff);
    return $prefix . $nextNum . $suffix;
}

/**
 * Increment a consignment out number matching current bill series (collision handling).
 */
function bumpConsignmentOutNo($conn, $consignment_no, array $cfg) {
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
    if (preg_match($regex, (string)$consignment_no, $m)) {
        return $prefix . ((int)$m[1] + 1) . $suffix;
    }
    return getNextConsignmentOutNo($conn);
}

/**
 * Bill series config for Sales Quotation (tbl_bill_series row for voucher type "Sales Quotation").
 * If no row or table missing, returns legacy SQ- / start 1.
 *
 * @return array{prefix:string,suffix:string,start_count:int,from_series_table:bool,voucher_type_id?:int}
 */
function getSalesQuotationBillSeriesConfig($conn) {
    $legacy = ['prefix' => 'SQ-', 'suffix' => '', 'start_count' => 1, 'from_series_table' => false];
    $vtId = 0;
    $r = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(name)) = 'sales quotation' LIMIT 1");
    if ($r && !empty($r['id'])) {
        $vtId = (int)$r['id'];
    }
    if ($vtId <= 0) {
        $r2 = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(COALESCE(type_of_voucher,''))) = 'sales quotation' LIMIT 1");
        if ($r2 && !empty($r2['id'])) {
            $vtId = (int)$r2['id'];
        }
    }
    $tableCheck = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_bill_series'");
    if (!$tableCheck || mysqli_num_rows($tableCheck) === 0) {
        if ($tableCheck) {
            mysqli_free_result($tableCheck);
        }
        return $legacy;
    }
    mysqli_free_result($tableCheck);
    if ($vtId <= 0) {
        return $legacy;
    }
    $series = auragold_bill_series_row_for_voucher_type($conn, $vtId);
    if (!$series || trim((string)($series['prefix'] ?? '')) === '') {
        return $legacy;
    }
    return [
        'prefix' => (string)$series['prefix'],
        'suffix' => (string)($series['suffix'] ?? ''),
        'start_count' => (int)($series['start_count'] ?? 0),
        'from_series_table' => true,
        'voucher_type_id' => $vtId,
    ];
}

/**
 * Next sales quotation number from bill series (prefix + number + suffix) or legacy SQ-1, SQ-2.
 */
function getNextSalesQuotationNo($conn) {
    $cfg = getSalesQuotationBillSeriesConfig($conn);
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $start = (int)($cfg['start_count'] ?? 0);
    $startEff = max(1, $start);

    $prefix_esc = mysqli_real_escape_string($conn, $prefix);
    $rows = getList("SELECT quotation_no FROM tbl_sale_quotations WHERE quotation_no LIKE '$prefix_esc%'");

    $maxNum = 0;
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
    foreach ($rows as $row) {
        $qno = (string)($row['quotation_no'] ?? '');
        if (preg_match($regex, $qno, $m)) {
            $maxNum = max($maxNum, (int)$m[1]);
        }
    }
    $nextNum = max($maxNum + 1, $startEff);
    return $prefix . $nextNum . $suffix;
}

/**
 * Increment a sales quotation number matching current bill series (collision handling).
 */
function bumpSalesQuotationNo($conn, $quotation_no, array $cfg) {
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
    if (preg_match($regex, (string)$quotation_no, $m)) {
        return $prefix . ((int)$m[1] + 1) . $suffix;
    }
    return getNextSalesQuotationNo($conn);
}

/**
 * Bill series config for Sales Order (tbl_bill_series row for voucher type "Sales Order").
 * If no row or table missing, returns legacy SO- / start 1.
 *
 * @return array{prefix:string,suffix:string,start_count:int,from_series_table:bool,voucher_type_id?:int}
 */
function getSalesOrderBillSeriesConfig($conn) {
    $legacy = ['prefix' => 'SO-', 'suffix' => '', 'start_count' => 1, 'from_series_table' => false];
    $vtId = 0;
    $r = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(name)) = 'sales order' LIMIT 1");
    if ($r && !empty($r['id'])) {
        $vtId = (int)$r['id'];
    }
    if ($vtId <= 0) {
        $r2 = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(COALESCE(type_of_voucher,''))) = 'sales order' LIMIT 1");
        if ($r2 && !empty($r2['id'])) {
            $vtId = (int)$r2['id'];
        }
    }
    $tableCheck = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_bill_series'");
    if (!$tableCheck || mysqli_num_rows($tableCheck) === 0) {
        if ($tableCheck) {
            mysqli_free_result($tableCheck);
        }
        return $legacy;
    }
    mysqli_free_result($tableCheck);
    if ($vtId <= 0) {
        return $legacy;
    }
    $series = auragold_bill_series_row_for_voucher_type($conn, $vtId);
    if (!$series || trim((string)($series['prefix'] ?? '')) === '') {
        return $legacy;
    }
    return [
        'prefix' => (string)$series['prefix'],
        'suffix' => (string)($series['suffix'] ?? ''),
        'start_count' => (int)($series['start_count'] ?? 0),
        'from_series_table' => true,
        'voucher_type_id' => $vtId,
    ];
}

/**
 * Next sale order number from bill series (prefix + number + suffix) or legacy SO-1, SO-2.
 */
function getNextSaleOrderNo($conn) {
    $cfg = getSalesOrderBillSeriesConfig($conn);
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $start = (int)($cfg['start_count'] ?? 0);
    $startEff = max(1, $start);

    $prefix_esc = mysqli_real_escape_string($conn, $prefix);
    $rows = getList("SELECT order_no FROM tbl_sale_orders WHERE order_no LIKE '$prefix_esc%'");

    $maxNum = 0;
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
    foreach ($rows as $row) {
        $ono = (string)($row['order_no'] ?? '');
        if (preg_match($regex, $ono, $m)) {
            $maxNum = max($maxNum, (int)$m[1]);
        }
    }
    $nextNum = max($maxNum + 1, $startEff);
    return $prefix . $nextNum . $suffix;
}

/**
 * Increment a sale order number matching current bill series (collision handling).
 */
function bumpSaleOrderNo($conn, $order_no, array $cfg) {
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
    if (preg_match($regex, (string)$order_no, $m)) {
        return $prefix . ((int)$m[1] + 1) . $suffix;
    }
    return getNextSaleOrderNo($conn);
}

/**
 * Bill series config for Repair Order (tbl_bill_series row for voucher type "Repair Order"; see bill-series.php).
 * Legacy default: RO-1, RO-2 when no series row exists.
 *
 * @return array{prefix:string,suffix:string,start_count:int,from_series_table:bool,voucher_type_id?:int}
 */
function getRepairOrderBillSeriesConfig($conn) {
    $legacy = ['prefix' => 'RO-', 'suffix' => '', 'start_count' => 1, 'from_series_table' => false];
    $vtId = 0;
    $r = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(name)) = 'repair order' LIMIT 1");
    if ($r && !empty($r['id'])) {
        $vtId = (int)$r['id'];
    }
    if ($vtId <= 0) {
        $r2 = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(COALESCE(type_of_voucher,''))) = 'repair order' LIMIT 1");
        if ($r2 && !empty($r2['id'])) {
            $vtId = (int)$r2['id'];
        }
    }
    $tableCheck = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_bill_series'");
    if (!$tableCheck || mysqli_num_rows($tableCheck) === 0) {
        if ($tableCheck) {
            mysqli_free_result($tableCheck);
        }
        return $legacy;
    }
    mysqli_free_result($tableCheck);
    if ($vtId <= 0) {
        return $legacy;
    }
    $series = auragold_bill_series_row_for_voucher_type($conn, $vtId);
    if (!$series || trim((string)($series['prefix'] ?? '')) === '') {
        return $legacy;
    }
    return [
        'prefix' => (string)$series['prefix'],
        'suffix' => (string)($series['suffix'] ?? ''),
        'start_count' => (int)($series['start_count'] ?? 0),
        'from_series_table' => true,
        'voucher_type_id' => $vtId,
    ];
}

/**
 * Next repair order number from bill series (prefix + number + suffix) or legacy RO-1, RO-2.
 */
function getNextRepairOrderNo($conn) {
    $cfg = getRepairOrderBillSeriesConfig($conn);
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $start = (int)($cfg['start_count'] ?? 0);
    $startEff = max(1, $start);

    $prefix_esc = mysqli_real_escape_string($conn, $prefix);
    $rows = getList("SELECT order_no FROM tbl_repair_orders WHERE order_no LIKE '$prefix_esc%'");

    $maxNum = 0;
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
    foreach ($rows as $row) {
        $ono = (string)($row['order_no'] ?? '');
        if (preg_match($regex, $ono, $m)) {
            $maxNum = max($maxNum, (int)$m[1]);
        }
    }
    $nextNum = max($maxNum + 1, $startEff);
    return $prefix . $nextNum . $suffix;
}

/**
 * Increment a repair order number matching current bill series (collision handling).
 */
function bumpRepairOrderNo($conn, $order_no, array $cfg) {
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
    if (preg_match($regex, (string)$order_no, $m)) {
        return $prefix . ((int)$m[1] + 1) . $suffix;
    }
    return getNextRepairOrderNo($conn);
}

/**
 * Bill series config for Job Work Order (tbl_bill_series row for voucher type "Jobwork Order" / "Job Work Order").
 * If no row or table missing, returns legacy JWO- / start 1.
 *
 * @return array{prefix:string,suffix:string,start_count:int,from_series_table:bool,voucher_type_id?:int}
 */
function getJobworkOrderBillSeriesConfig($conn) {
    $legacy = ['prefix' => 'JWO-', 'suffix' => '', 'start_count' => 1, 'from_series_table' => false];
    $vtId = 0;
    $r = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND (LOWER(TRIM(name)) = 'jobwork order' OR LOWER(TRIM(name)) = 'job work order') ORDER BY id ASC LIMIT 1");
    if ($r && !empty($r['id'])) {
        $vtId = (int)$r['id'];
    }
    if ($vtId <= 0) {
        $r2 = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND (LOWER(TRIM(COALESCE(type_of_voucher,''))) = 'jobwork order' OR LOWER(TRIM(COALESCE(type_of_voucher,''))) = 'job work order') ORDER BY id ASC LIMIT 1");
        if ($r2 && !empty($r2['id'])) {
            $vtId = (int)$r2['id'];
        }
    }
    $tableCheck = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_bill_series'");
    if (!$tableCheck || mysqli_num_rows($tableCheck) === 0) {
        if ($tableCheck) {
            mysqli_free_result($tableCheck);
        }
        return $legacy;
    }
    mysqli_free_result($tableCheck);
    if ($vtId <= 0) {
        return $legacy;
    }
    $series = auragold_bill_series_row_for_voucher_type($conn, $vtId);
    if (!$series || trim((string)($series['prefix'] ?? '')) === '') {
        return $legacy;
    }
    return [
        'prefix' => (string)$series['prefix'],
        'suffix' => (string)($series['suffix'] ?? ''),
        'start_count' => (int)($series['start_count'] ?? 0),
        'from_series_table' => true,
        'voucher_type_id' => $vtId,
    ];
}

/**
 * Next job work order number from bill series (prefix + number + suffix) or legacy JWO-1, JWO-2.
 */
function getNextJobworkOrderNo($conn) {
    $cfg = getJobworkOrderBillSeriesConfig($conn);
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $start = (int)($cfg['start_count'] ?? 0);
    $startEff = max(1, $start);

    $prefix_esc = mysqli_real_escape_string($conn, $prefix);
    $rows = getList("SELECT jobwork_no FROM tbl_jobwork_orders WHERE jobwork_no LIKE '$prefix_esc%'");

    $maxNum = 0;
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
    foreach ($rows as $row) {
        $jno = (string)($row['jobwork_no'] ?? '');
        if (preg_match($regex, $jno, $m)) {
            $maxNum = max($maxNum, (int)$m[1]);
        }
    }
    $nextNum = max($maxNum + 1, $startEff);
    return $prefix . $nextNum . $suffix;
}

/**
 * Increment a job work order number matching current bill series (collision handling).
 */
function bumpJobworkOrderNo($conn, $jobwork_no, array $cfg) {
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
    if (preg_match($regex, (string)$jobwork_no, $m)) {
        return $prefix . ((int)$m[1] + 1) . $suffix;
    }
    return getNextJobworkOrderNo($conn);
}

/**
 * Bill series config for Material Issue (tbl_bill_series row for voucher type "Material Issue").
 *
 * @return array{prefix:string,suffix:string,start_count:int,from_series_table:bool,voucher_type_id?:int}
 */
function getMaterialIssueBillSeriesConfig($conn) {
    $legacy = ['prefix' => 'MI-', 'suffix' => '', 'start_count' => 1, 'from_series_table' => false];
    $vtId = 0;
    $r = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(name)) = 'material issue' ORDER BY id ASC LIMIT 1");
    if ($r && !empty($r['id'])) {
        $vtId = (int)$r['id'];
    }
    if ($vtId <= 0) {
        $r2 = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(COALESCE(type_of_voucher,''))) = 'material issue' ORDER BY id ASC LIMIT 1");
        if ($r2 && !empty($r2['id'])) {
            $vtId = (int)$r2['id'];
        }
    }
    $tableCheck = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_bill_series'");
    if (!$tableCheck || mysqli_num_rows($tableCheck) === 0) {
        if ($tableCheck) {
            mysqli_free_result($tableCheck);
        }
        return $legacy;
    }
    mysqli_free_result($tableCheck);
    if ($vtId <= 0) {
        return $legacy;
    }
    $series = auragold_bill_series_row_for_voucher_type($conn, $vtId);
    if (!$series || trim((string)($series['prefix'] ?? '')) === '') {
        return $legacy;
    }
    return [
        'prefix' => (string)$series['prefix'],
        'suffix' => (string)($series['suffix'] ?? ''),
        'start_count' => (int)($series['start_count'] ?? 0),
        'from_series_table' => true,
        'voucher_type_id' => $vtId,
    ];
}

/**
 * Next material issue number from bill series. Scans tbl_material_issues and tbl_repair_material_issues for unified sequence.
 */
function getNextMaterialIssueNo($conn) {
    $cfg = getMaterialIssueBillSeriesConfig($conn);
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $start = (int)($cfg['start_count'] ?? 0);
    $startEff = max(1, $start);

    $prefix_esc = mysqli_real_escape_string($conn, $prefix);
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
    $maxNum = 0;

    $tm = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_material_issues'");
    if ($tm && mysqli_num_rows($tm) > 0) {
        mysqli_free_result($tm);
        $rows = getList("SELECT material_issue_no FROM tbl_material_issues WHERE material_issue_no LIKE '$prefix_esc%'");
        foreach ($rows as $row) {
            $ino = (string)($row['material_issue_no'] ?? '');
            if (preg_match($regex, $ino, $m)) {
                $maxNum = max($maxNum, (int)$m[1]);
            }
        }
    } elseif ($tm) {
        mysqli_free_result($tm);
    }

    $tr = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_repair_material_issues'");
    if ($tr && mysqli_num_rows($tr) > 0) {
        mysqli_free_result($tr);
        $rows2 = getList("SELECT material_issue_no FROM tbl_repair_material_issues WHERE material_issue_no LIKE '$prefix_esc%'");
        foreach ($rows2 as $row) {
            $ino = (string)($row['material_issue_no'] ?? '');
            if (preg_match($regex, $ino, $m)) {
                $maxNum = max($maxNum, (int)$m[1]);
            }
        }
    } elseif ($tr) {
        mysqli_free_result($tr);
    }

    $nextNum = max($maxNum + 1, $startEff);
    return $prefix . $nextNum . $suffix;
}

/**
 * Increment a material issue number on collision (same pattern as bumpJobworkOrderNo).
 */
function bumpMaterialIssueNo($conn, $material_issue_no, array $cfg) {
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
    if (preg_match($regex, (string)$material_issue_no, $m)) {
        return $prefix . ((int)$m[1] + 1) . $suffix;
    }
    return getNextMaterialIssueNo($conn);
}

/**
 * Bill series config for Material Receive (voucher type "Material Receive").
 *
 * @return array{prefix:string,suffix:string,start_count:int,from_series_table:bool,voucher_type_id?:int}
 */
function getMaterialReceiveBillSeriesConfig($conn) {
    $legacy = ['prefix' => 'MR-', 'suffix' => '', 'start_count' => 1, 'from_series_table' => false];
    $vtId = 0;
    $r = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(name)) = 'material receive' ORDER BY id ASC LIMIT 1");
    if ($r && !empty($r['id'])) {
        $vtId = (int)$r['id'];
    }
    if ($vtId <= 0) {
        $r2 = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(COALESCE(type_of_voucher,''))) = 'material receive' ORDER BY id ASC LIMIT 1");
        if ($r2 && !empty($r2['id'])) {
            $vtId = (int)$r2['id'];
        }
    }
    $tableCheck = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_bill_series'");
    if (!$tableCheck || mysqli_num_rows($tableCheck) === 0) {
        if ($tableCheck) {
            mysqli_free_result($tableCheck);
        }
        return $legacy;
    }
    mysqli_free_result($tableCheck);
    if ($vtId <= 0) {
        return $legacy;
    }
    $series = auragold_bill_series_row_for_voucher_type($conn, $vtId);
    if (!$series || trim((string)($series['prefix'] ?? '')) === '') {
        return $legacy;
    }
    return [
        'prefix' => (string)$series['prefix'],
        'suffix' => (string)($series['suffix'] ?? ''),
        'start_count' => (int)($series['start_count'] ?? 0),
        'from_series_table' => true,
        'voucher_type_id' => $vtId,
    ];
}

/**
 * Next material receive number. Scans tbl_material_receives and tbl_repair_material_receives.
 */
function getNextMaterialReceiveNo($conn) {
    $cfg = getMaterialReceiveBillSeriesConfig($conn);
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $start = (int)($cfg['start_count'] ?? 0);
    $startEff = max(1, $start);

    $prefix_esc = mysqli_real_escape_string($conn, $prefix);
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
    $maxNum = 0;

    $tm = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_material_receives'");
    if ($tm && mysqli_num_rows($tm) > 0) {
        mysqli_free_result($tm);
        $rows = getList("SELECT material_receive_no FROM tbl_material_receives WHERE material_receive_no LIKE '$prefix_esc%'");
        foreach ($rows as $row) {
            $ino = (string)($row['material_receive_no'] ?? '');
            if (preg_match($regex, $ino, $m)) {
                $maxNum = max($maxNum, (int)$m[1]);
            }
        }
    } elseif ($tm) {
        mysqli_free_result($tm);
    }

    $tr = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_repair_material_receives'");
    if ($tr && mysqli_num_rows($tr) > 0) {
        mysqli_free_result($tr);
        $rows2 = getList("SELECT material_receive_no FROM tbl_repair_material_receives WHERE material_receive_no LIKE '$prefix_esc%'");
        foreach ($rows2 as $row) {
            $ino = (string)($row['material_receive_no'] ?? '');
            if (preg_match($regex, $ino, $m)) {
                $maxNum = max($maxNum, (int)$m[1]);
            }
        }
    } elseif ($tr) {
        mysqli_free_result($tr);
    }

    $nextNum = max($maxNum + 1, $startEff);
    return $prefix . $nextNum . $suffix;
}

function bumpMaterialReceiveNo($conn, $material_receive_no, array $cfg) {
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
    if (preg_match($regex, (string)$material_receive_no, $m)) {
        return $prefix . ((int)$m[1] + 1) . $suffix;
    }
    return getNextMaterialReceiveNo($conn);
}

/**
 * Bill series config for Jobwork Invoice (tbl_bill_series row for voucher type "Jobwork Invoice").
 * Legacy default: JWI-1, JWI-2.
 *
 * @return array{prefix:string,suffix:string,start_count:int,from_series_table:bool,voucher_type_id?:int}
 */
function getJobworkInvoiceBillSeriesConfig($conn) {
    $legacy = ['prefix' => 'JWI-', 'suffix' => '', 'start_count' => 1, 'from_series_table' => false];
    $vtId = 0;
    $r = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(name)) = 'jobwork invoice' LIMIT 1");
    if ($r && !empty($r['id'])) {
        $vtId = (int)$r['id'];
    }
    if ($vtId <= 0) {
        $r2 = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(COALESCE(type_of_voucher,''))) = 'jobwork invoice' LIMIT 1");
        if ($r2 && !empty($r2['id'])) {
            $vtId = (int)$r2['id'];
        }
    }
    $tableCheck = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_bill_series'");
    if (!$tableCheck || mysqli_num_rows($tableCheck) === 0) {
        if ($tableCheck) {
            mysqli_free_result($tableCheck);
        }
        return $legacy;
    }
    mysqli_free_result($tableCheck);
    if ($vtId <= 0) {
        return $legacy;
    }
    $series = auragold_bill_series_row_for_voucher_type($conn, $vtId);
    if (!$series || trim((string)($series['prefix'] ?? '')) === '') {
        return $legacy;
    }
    return [
        'prefix' => (string)$series['prefix'],
        'suffix' => (string)($series['suffix'] ?? ''),
        'start_count' => (int)($series['start_count'] ?? 0),
        'from_series_table' => true,
        'voucher_type_id' => $vtId,
    ];
}

/**
 * Next Jobwork Invoice number from tbl_jobwork_invoices + bill series, or legacy JWI-1.
 */
function getNextJobworkInvoiceNo($conn) {
    $cfg = getJobworkInvoiceBillSeriesConfig($conn);
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $start = (int)($cfg['start_count'] ?? 0);
    $startEff = max(1, $start);

    $prefix_esc = mysqli_real_escape_string($conn, $prefix);
    $rows = [];
    $tblInv = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_invoices'");
    if ($tblInv && mysqli_num_rows($tblInv) > 0) {
        mysqli_free_result($tblInv);
        $rows = getList("SELECT invoice_no FROM tbl_jobwork_invoices WHERE invoice_no LIKE '$prefix_esc%'");
    } elseif ($tblInv) {
        mysqli_free_result($tblInv);
    }

    $maxNum = 0;
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
    foreach ($rows as $row) {
        $ino = (string)($row['invoice_no'] ?? '');
        if (preg_match($regex, $ino, $m)) {
            $maxNum = max($maxNum, (int)$m[1]);
        }
    }
    $nextNum = max($maxNum + 1, $startEff);
    return $prefix . $nextNum . $suffix;
}

/**
 * Increment a Jobwork Invoice number matching current bill series pattern.
 */
function bumpJobworkInvoiceNo($conn, $invoice_no, array $cfg) {
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
    if (preg_match($regex, (string)$invoice_no, $m)) {
        return $prefix . ((int)$m[1] + 1) . $suffix;
    }
    return getNextJobworkInvoiceNo($conn);
}

/**
 * Bill series config for Jobwork Queue (Manufacturing screen — tbl_jobwork_orders.jobwork_queue_no).
 * Configure prefix/suffix in Bill Series for voucher type "Jobwork Queue" (bill-series.php).
 * Legacy default: JWQ-1, JWQ-2.
 *
 * @return array{prefix:string,suffix:string,start_count:int,from_series_table:bool,voucher_type_id?:int}
 */
function getJobworkQueueBillSeriesConfig($conn) {
    $legacy = ['prefix' => 'JWQ-', 'suffix' => '', 'start_count' => 1, 'from_series_table' => false];
    $vtId = 0;
    // Match bill-series.php / tbl_voucher_types: tolerate "Jobwork Queue", "Job Work Queue", spacing variants
    $r = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND (
        LOWER(TRIM(name)) = 'jobwork queue'
        OR LOWER(TRIM(name)) = 'job work queue'
        OR REPLACE(REPLACE(LOWER(TRIM(name)), ' ', ''), '-', '') = 'jobworkqueue'
    ) ORDER BY id ASC LIMIT 1");
    if ($r && !empty($r['id'])) {
        $vtId = (int)$r['id'];
    }
    if ($vtId <= 0) {
        $r2 = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND (
            LOWER(TRIM(COALESCE(type_of_voucher,''))) = 'jobwork queue'
            OR LOWER(TRIM(COALESCE(type_of_voucher,''))) = 'job work queue'
            OR REPLACE(REPLACE(LOWER(TRIM(COALESCE(type_of_voucher,''))), ' ', ''), '-', '') = 'jobworkqueue'
        ) ORDER BY id ASC LIMIT 1");
        if ($r2 && !empty($r2['id'])) {
            $vtId = (int)$r2['id'];
        }
    }
    $tableCheck = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_bill_series'");
    if (!$tableCheck || mysqli_num_rows($tableCheck) === 0) {
        if ($tableCheck) {
            mysqli_free_result($tableCheck);
        }
        return $legacy;
    }
    mysqli_free_result($tableCheck);
    if ($vtId <= 0) {
        return $legacy;
    }
    $series = auragold_bill_series_row_for_voucher_type($conn, $vtId);
    if (!$series || trim((string)($series['prefix'] ?? '')) === '') {
        return $legacy;
    }
    return [
        'prefix' => (string)$series['prefix'],
        'suffix' => (string)($series['suffix'] ?? ''),
        'start_count' => (int)($series['start_count'] ?? 0),
        'from_series_table' => true,
        'voucher_type_id' => $vtId,
    ];
}

/**
 * Next Jobwork Queue number from bill series + tbl_jobwork_orders.jobwork_queue_no, or legacy JWQ-1.
 */
function getNextJobworkQueueNo($conn) {
    $cfg = getJobworkQueueBillSeriesConfig($conn);
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $start = (int)($cfg['start_count'] ?? 0);
    $startEff = max(1, $start);

    $col = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_orders LIKE 'jobwork_queue_no'");
    if (!$col || mysqli_num_rows($col) === 0) {
        if ($col) {
            mysqli_free_result($col);
        }
        return $prefix . $startEff . $suffix;
    }
    mysqli_free_result($col);

    $prefix_esc = mysqli_real_escape_string($conn, $prefix);
    $rows = getList("SELECT jobwork_queue_no FROM tbl_jobwork_orders WHERE TRIM(IFNULL(jobwork_queue_no,'')) != '' AND jobwork_queue_no LIKE '$prefix_esc%'");

    $maxNum = 0;
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
    foreach ($rows as $row) {
        $qno = (string)($row['jobwork_queue_no'] ?? '');
        if (preg_match($regex, $qno, $m)) {
            $maxNum = max($maxNum, (int)$m[1]);
        }
    }
    $atbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_queue_activity'");
    if ($atbl && mysqli_num_rows($atbl) > 0) {
        mysqli_free_result($atbl);
        $acol = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_queue_activity LIKE 'jobwork_queue_no'");
        if ($acol && mysqli_num_rows($acol) > 0) {
            mysqli_free_result($acol);
            $arows = getList("SELECT jobwork_queue_no FROM tbl_jobwork_queue_activity WHERE TRIM(IFNULL(jobwork_queue_no,'')) != '' AND jobwork_queue_no LIKE '$prefix_esc%'");
            if (is_array($arows)) {
                foreach ($arows as $row) {
                    $qno = (string)($row['jobwork_queue_no'] ?? '');
                    if (preg_match($regex, $qno, $m)) {
                        $maxNum = max($maxNum, (int)$m[1]);
                    }
                }
            }
        } elseif ($acol) {
            mysqli_free_result($acol);
        }
    } elseif ($atbl) {
        mysqli_free_result($atbl);
    }
    $nextNum = max($maxNum + 1, $startEff);
    return $prefix . $nextNum . $suffix;
}

/**
 * Increment a Jobwork Queue number matching current bill series pattern.
 */
function bumpJobworkQueueNo($conn, $jobwork_queue_no, array $cfg) {
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
    if (preg_match($regex, (string)$jobwork_queue_no, $m)) {
        return $prefix . ((int)$m[1] + 1) . $suffix;
    }
    return getNextJobworkQueueNo($conn);
}

/**
 * Ensure tbl_jobwork_orders.jobwork_queue_no is set for this row (Bill Series — Jobwork Queue).
 * Returns the queue number string or null if order missing.
 */
function ensureJobworkQueueNoForOrder($conn, $jobwork_order_id) {
    $jobwork_order_id = (int)$jobwork_order_id;
    if ($jobwork_order_id < 1) {
        return null;
    }
    $col = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_orders LIKE 'jobwork_queue_no'");
    if (!$col || mysqli_num_rows($col) === 0) {
        if ($col) {
            mysqli_free_result($col);
        }
        return null;
    }
    mysqli_free_result($col);

    $row = getRecord("SELECT id, jobwork_queue_no FROM tbl_jobwork_orders WHERE id = $jobwork_order_id LIMIT 1");
    if (!$row || empty($row['id'])) {
        return null;
    }
    $existing = trim((string)($row['jobwork_queue_no'] ?? ''));
    if ($existing !== '') {
        return $existing;
    }
    $next = getNextJobworkQueueNo($conn);
    $esc = mysqli_real_escape_string($conn, $next);
    if (!mysqli_query($conn, "UPDATE tbl_jobwork_orders SET jobwork_queue_no = '$esc' WHERE id = $jobwork_order_id")) {
        return null;
    }
    return $next;
}

/**
 * Bill series config for Purchase Quotation (tbl_bill_series + voucher type "Purchase Quotation").
 * Legacy default: PQ-1, PQ-2.
 *
 * @return array{prefix:string,suffix:string,start_count:int,from_series_table:bool,voucher_type_id?:int}
 */
function getPurchaseQuotationBillSeriesConfig($conn) {
    $legacy = ['prefix' => 'PQ-', 'suffix' => '', 'start_count' => 1, 'from_series_table' => false];
    $vtId = 0;
    $r = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(name)) = 'purchase quotation' LIMIT 1");
    if ($r && !empty($r['id'])) {
        $vtId = (int)$r['id'];
    }
    if ($vtId <= 0) {
        $r2 = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(COALESCE(type_of_voucher,''))) = 'purchase quotation' LIMIT 1");
        if ($r2 && !empty($r2['id'])) {
            $vtId = (int)$r2['id'];
        }
    }
    $tableCheck = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_bill_series'");
    if (!$tableCheck || mysqli_num_rows($tableCheck) === 0) {
        if ($tableCheck) {
            mysqli_free_result($tableCheck);
        }
        return $legacy;
    }
    mysqli_free_result($tableCheck);
    if ($vtId <= 0) {
        return $legacy;
    }
    $series = auragold_bill_series_row_for_voucher_type($conn, $vtId);
    if (!$series || trim((string)($series['prefix'] ?? '')) === '') {
        return $legacy;
    }
    return [
        'prefix' => (string)$series['prefix'],
        'suffix' => (string)($series['suffix'] ?? ''),
        'start_count' => (int)($series['start_count'] ?? 0),
        'from_series_table' => true,
        'voucher_type_id' => $vtId,
    ];
}

/**
 * Next purchase quotation number from bill series (tbl_purchase_quotations).
 */
function getNextPurchaseQuotationNo($conn) {
    $cfg = getPurchaseQuotationBillSeriesConfig($conn);
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $start = (int)($cfg['start_count'] ?? 0);
    $startEff = max(1, $start);

    $prefix_esc = mysqli_real_escape_string($conn, $prefix);
    $rows = getList("SELECT quotation_no FROM tbl_purchase_quotations WHERE quotation_no LIKE '$prefix_esc%'");

    $maxNum = 0;
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
    foreach ($rows as $row) {
        $qno = (string)($row['quotation_no'] ?? '');
        if (preg_match($regex, $qno, $m)) {
            $maxNum = max($maxNum, (int)$m[1]);
        }
    }
    $nextNum = max($maxNum + 1, $startEff);
    return $prefix . $nextNum . $suffix;
}

function bumpPurchaseQuotationNo($conn, $quotation_no, array $cfg) {
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
    if (preg_match($regex, (string)$quotation_no, $m)) {
        return $prefix . ((int)$m[1] + 1) . $suffix;
    }
    return getNextPurchaseQuotationNo($conn);
}

/**
 * Bill series config for Sales Return (tbl_bill_series row for voucher type "Sales Return").
 * If no row or table missing, returns legacy SR- / start 1.
 *
 * @return array{prefix:string,suffix:string,start_count:int,from_series_table:bool,voucher_type_id?:int}
 */
function getSalesReturnBillSeriesConfig($conn) {
    $legacy = ['prefix' => 'SR-', 'suffix' => '', 'start_count' => 1, 'from_series_table' => false];
    $vtId = 0;
    $r = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(name)) = 'sales return' LIMIT 1");
    if ($r && !empty($r['id'])) {
        $vtId = (int)$r['id'];
    }
    if ($vtId <= 0) {
        $r2 = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(COALESCE(type_of_voucher,''))) = 'sales return' LIMIT 1");
        if ($r2 && !empty($r2['id'])) {
            $vtId = (int)$r2['id'];
        }
    }
    $tableCheck = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_bill_series'");
    if (!$tableCheck || mysqli_num_rows($tableCheck) === 0) {
        if ($tableCheck) {
            mysqli_free_result($tableCheck);
        }
        return $legacy;
    }
    mysqli_free_result($tableCheck);
    if ($vtId <= 0) {
        return $legacy;
    }
    $series = auragold_bill_series_row_for_voucher_type($conn, $vtId);
    if (!$series || trim((string)($series['prefix'] ?? '')) === '') {
        return $legacy;
    }
    return [
        'prefix' => (string)$series['prefix'],
        'suffix' => (string)($series['suffix'] ?? ''),
        'start_count' => (int)($series['start_count'] ?? 0),
        'from_series_table' => true,
        'voucher_type_id' => $vtId,
    ];
}

/**
 * Next sales return number from bill series (e.g. SR1, SR2) — no zero-padding; matches Bill Series prefix + numeric count.
 */
function getNextSaleReturnNo($conn) {
    $cfg = getSalesReturnBillSeriesConfig($conn);
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $start = (int)($cfg['start_count'] ?? 0);
    $startEff = max(1, $start);

    $prefix_esc = mysqli_real_escape_string($conn, $prefix);
    $rows = getList("SELECT return_no FROM tbl_sale_returns WHERE return_no LIKE '$prefix_esc%'");

    $maxNum = 0;
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
    foreach ($rows as $row) {
        $rno = (string)($row['return_no'] ?? '');
        if (preg_match($regex, $rno, $m)) {
            $maxNum = max($maxNum, (int)$m[1]);
        }
    }
    $nextNum = max($maxNum + 1, $startEff);
    return $prefix . $nextNum . $suffix;
}

/**
 * Increment a sales return number matching current bill series (collision handling).
 */
function bumpSaleReturnNo($conn, $return_no, array $cfg) {
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
    if (preg_match($regex, (string)$return_no, $m)) {
        return $prefix . ((int)$m[1] + 1) . $suffix;
    }
    return getNextSaleReturnNo($conn);
}

/**
 * Bill series config for Purchase Return (tbl_bill_series row for voucher type "Purchase Return").
 * If no row or table missing, returns legacy PR- / start 1.
 *
 * @return array{prefix:string,suffix:string,start_count:int,from_series_table:bool,voucher_type_id?:int}
 */
function getPurchaseReturnBillSeriesConfig($conn) {
    $legacy = ['prefix' => 'PR-', 'suffix' => '', 'start_count' => 1, 'from_series_table' => false];
    $vtId = 0;
    $r = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(name)) = 'purchase return' LIMIT 1");
    if ($r && !empty($r['id'])) {
        $vtId = (int)$r['id'];
    }
    if ($vtId <= 0) {
        $r2 = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(COALESCE(type_of_voucher,''))) = 'purchase return' LIMIT 1");
        if ($r2 && !empty($r2['id'])) {
            $vtId = (int)$r2['id'];
        }
    }
    $tableCheck = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_bill_series'");
    if (!$tableCheck || mysqli_num_rows($tableCheck) === 0) {
        if ($tableCheck) {
            mysqli_free_result($tableCheck);
        }
        return $legacy;
    }
    mysqli_free_result($tableCheck);
    if ($vtId <= 0) {
        return $legacy;
    }
    $series = auragold_bill_series_row_for_voucher_type($conn, $vtId);
    if (!$series || trim((string)($series['prefix'] ?? '')) === '') {
        return $legacy;
    }
    return [
        'prefix' => (string)$series['prefix'],
        'suffix' => (string)($series['suffix'] ?? ''),
        'start_count' => (int)($series['start_count'] ?? 0),
        'from_series_table' => true,
        'voucher_type_id' => $vtId,
    ];
}

/**
 * Next purchase return number from bill series or legacy PR-1, PR-2.
 */
function getNextPurchaseReturnNo($conn) {
    $cfg = getPurchaseReturnBillSeriesConfig($conn);
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $start = (int)($cfg['start_count'] ?? 0);
    $startEff = max(1, $start);

    $prefix_esc = mysqli_real_escape_string($conn, $prefix);
    $rows = getList("SELECT return_no FROM tbl_purchase_returns WHERE return_no LIKE '$prefix_esc%'");

    $maxNum = 0;
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
    foreach ($rows as $row) {
        $rno = (string)($row['return_no'] ?? '');
        if (preg_match($regex, $rno, $m)) {
            $maxNum = max($maxNum, (int)$m[1]);
        }
    }
    $nextNum = max($maxNum + 1, $startEff);
    return $prefix . $nextNum . $suffix;
}

/**
 * Increment a purchase return number matching current bill series (collision handling).
 */
function bumpPurchaseReturnNo($conn, $return_no, array $cfg) {
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
    if (preg_match($regex, (string)$return_no, $m)) {
        return $prefix . ((int)$m[1] + 1) . $suffix;
    }
    return getNextPurchaseReturnNo($conn);
}

/**
 * Bill series config for Payment Voucher (tbl_bill_series + voucher type "Payment Voucher").
 * Legacy default: PV-1, PV-2.
 *
 * @return array{prefix:string,suffix:string,start_count:int,from_series_table:bool,voucher_type_id?:int}
 */
function getPaymentVoucherBillSeriesConfig($conn) {
    $legacy = ['prefix' => 'PV-', 'suffix' => '', 'start_count' => 1, 'from_series_table' => false];
    $vtId = 0;
    $r = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(name)) = 'payment voucher' LIMIT 1");
    if ($r && !empty($r['id'])) {
        $vtId = (int)$r['id'];
    }
    if ($vtId <= 0) {
        $r2 = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(COALESCE(type_of_voucher,''))) = 'payment voucher' LIMIT 1");
        if ($r2 && !empty($r2['id'])) {
            $vtId = (int)$r2['id'];
        }
    }
    $tableCheck = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_bill_series'");
    if (!$tableCheck || mysqli_num_rows($tableCheck) === 0) {
        if ($tableCheck) {
            mysqli_free_result($tableCheck);
        }
        return $legacy;
    }
    mysqli_free_result($tableCheck);
    if ($vtId <= 0) {
        return $legacy;
    }
    $series = auragold_bill_series_row_for_voucher_type($conn, $vtId);
    if (!$series || trim((string)($series['prefix'] ?? '')) === '') {
        return $legacy;
    }
    return [
        'prefix' => (string)$series['prefix'],
        'suffix' => (string)($series['suffix'] ?? ''),
        'start_count' => (int)($series['start_count'] ?? 0),
        'from_series_table' => true,
        'voucher_type_id' => $vtId,
    ];
}

/**
 * Next payment voucher number from bill series (prefix + number + suffix) or legacy PV-1, PV-2.
 */
function getNextPaymentVoucherNo($conn) {
    $cfg = getPaymentVoucherBillSeriesConfig($conn);
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $start = (int)($cfg['start_count'] ?? 0);
    $startEff = max(1, $start);

    $tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_payment_vouchers'");
    if (!$tbl || mysqli_num_rows($tbl) === 0) {
        if ($tbl) {
            mysqli_free_result($tbl);
        }
        return $prefix . $startEff . $suffix;
    }
    mysqli_free_result($tbl);

    $prefix_esc = mysqli_real_escape_string($conn, $prefix);
    $rows = getList("SELECT voucher_no FROM tbl_payment_vouchers WHERE voucher_no LIKE '$prefix_esc%'");

    $maxNum = 0;
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
    foreach ($rows as $row) {
        $vno = (string)($row['voucher_no'] ?? '');
        if (preg_match($regex, $vno, $m)) {
            $maxNum = max($maxNum, (int)$m[1]);
        }
    }
    $nextNum = max($maxNum + 1, $startEff);
    return $prefix . $nextNum . $suffix;
}

function bumpPaymentVoucherNo($conn, $voucher_no, array $cfg) {
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
    if (preg_match($regex, (string)$voucher_no, $m)) {
        return $prefix . ((int)$m[1] + 1) . $suffix;
    }
    return getNextPaymentVoucherNo($conn);
}

/**
 * Bill series config for Receipt Voucher (tbl_bill_series + voucher type "Receipt Voucher").
 * Legacy default: RV-1, RV-2.
 *
 * @return array{prefix:string,suffix:string,start_count:int,from_series_table:bool,voucher_type_id?:int}
 */
function getReceiptVoucherBillSeriesConfig($conn) {
    $legacy = ['prefix' => 'RV-', 'suffix' => '', 'start_count' => 1, 'from_series_table' => false];
    $vtId = 0;
    $r = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(name)) = 'receipt voucher' LIMIT 1");
    if ($r && !empty($r['id'])) {
        $vtId = (int)$r['id'];
    }
    if ($vtId <= 0) {
        $r2 = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(COALESCE(type_of_voucher,''))) = 'receipt voucher' LIMIT 1");
        if ($r2 && !empty($r2['id'])) {
            $vtId = (int)$r2['id'];
        }
    }
    $tableCheck = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_bill_series'");
    if (!$tableCheck || mysqli_num_rows($tableCheck) === 0) {
        if ($tableCheck) {
            mysqli_free_result($tableCheck);
        }
        return $legacy;
    }
    mysqli_free_result($tableCheck);
    if ($vtId <= 0) {
        return $legacy;
    }
    $series = auragold_bill_series_row_for_voucher_type($conn, $vtId);
    if (!$series || trim((string)($series['prefix'] ?? '')) === '') {
        return $legacy;
    }
    return [
        'prefix' => (string)$series['prefix'],
        'suffix' => (string)($series['suffix'] ?? ''),
        'start_count' => (int)($series['start_count'] ?? 0),
        'from_series_table' => true,
        'voucher_type_id' => $vtId,
    ];
}

/**
 * Next receipt voucher number from bill series or legacy RV-1, RV-2.
 */
function getNextReceiptVoucherNo($conn) {
    $cfg = getReceiptVoucherBillSeriesConfig($conn);
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $start = (int)($cfg['start_count'] ?? 0);
    $startEff = max(1, $start);

    $tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_receipt_vouchers'");
    if (!$tbl || mysqli_num_rows($tbl) === 0) {
        if ($tbl) {
            mysqli_free_result($tbl);
        }
        return $prefix . $startEff . $suffix;
    }
    mysqli_free_result($tbl);

    $prefix_esc = mysqli_real_escape_string($conn, $prefix);
    $rows = getList("SELECT voucher_no FROM tbl_receipt_vouchers WHERE voucher_no LIKE '$prefix_esc%'");

    $maxNum = 0;
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
    foreach ($rows as $row) {
        $vno = (string)($row['voucher_no'] ?? '');
        if (preg_match($regex, $vno, $m)) {
            $maxNum = max($maxNum, (int)$m[1]);
        }
    }
    $nextNum = max($maxNum + 1, $startEff);
    return $prefix . $nextNum . $suffix;
}

function bumpReceiptVoucherNo($conn, $voucher_no, array $cfg) {
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
    if (preg_match($regex, (string)$voucher_no, $m)) {
        return $prefix . ((int)$m[1] + 1) . $suffix;
    }
    return getNextReceiptVoucherNo($conn);
}

/**
 * Bill series for auto receipt from Sale / POS invoice (voucher type "Sale Receipt Voucher").
 * Independent of manual Receipt Voucher (RV-) series. Legacy default: SRV-1, SRV-2.
 *
 * @return array{prefix:string,suffix:string,start_count:int,from_series_table:bool,voucher_type_id?:int}
 */
function getSaleReceiptVoucherBillSeriesConfig($conn) {
    $legacy = ['prefix' => 'SRV-', 'suffix' => '', 'start_count' => 1, 'from_series_table' => false];
    $vtId = 0;
    $r = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(name)) = 'sale receipt voucher' LIMIT 1");
    if ($r && !empty($r['id'])) {
        $vtId = (int)$r['id'];
    }
    if ($vtId <= 0) {
        $r2 = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(COALESCE(type_of_voucher,''))) = 'sale receipt voucher' LIMIT 1");
        if ($r2 && !empty($r2['id'])) {
            $vtId = (int)$r2['id'];
        }
    }
    $tableCheck = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_bill_series'");
    if (!$tableCheck || mysqli_num_rows($tableCheck) === 0) {
        if ($tableCheck) {
            mysqli_free_result($tableCheck);
        }
        return $legacy;
    }
    mysqli_free_result($tableCheck);
    if ($vtId <= 0) {
        return $legacy;
    }
    if (!function_exists('auragold_bill_series_row_for_voucher_type')) {
        return $legacy;
    }
    $series = auragold_bill_series_row_for_voucher_type($conn, $vtId);
    if (!$series || trim((string)($series['prefix'] ?? '')) === '') {
        return $legacy;
    }
    return [
        'prefix' => (string)$series['prefix'],
        'suffix' => (string)($series['suffix'] ?? ''),
        'start_count' => (int)($series['start_count'] ?? 0),
        'from_series_table' => true,
        'voucher_type_id' => $vtId,
    ];
}

/**
 * Next number for auto sale-invoice receipt rows (tbl_sale_receipt_vouchers) — uses Sale Receipt Voucher bill series only.
 * Also considers legacy rows in tbl_receipt_vouchers (Sale Invoice Payment) with the same prefix/suffix pattern.
 */
function getNextSaleReceiptVoucherNo($conn) {
    $cfg = getSaleReceiptVoucherBillSeriesConfig($conn);
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $start = (int)($cfg['start_count'] ?? 0);
    $startEff = max(1, $start);

    $prefix_esc = mysqli_real_escape_string($conn, $prefix);
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';

    $maxNum = 0;

    $tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_receipt_vouchers'");
    if ($tbl && mysqli_num_rows($tbl) > 0) {
        mysqli_free_result($tbl);
        $rows = getList("SELECT voucher_no FROM tbl_sale_receipt_vouchers WHERE voucher_no LIKE '$prefix_esc%'");
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $vno = (string)($row['voucher_no'] ?? '');
                if (preg_match($regex, $vno, $m)) {
                    $maxNum = max($maxNum, (int)$m[1]);
                }
            }
        }
    } elseif ($tbl) {
        mysqli_free_result($tbl);
    }

    $tbl2 = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_receipt_vouchers'");
    if ($tbl2 && mysqli_num_rows($tbl2) > 0) {
        mysqli_free_result($tbl2);
        $rows2 = getList("SELECT voucher_no FROM tbl_receipt_vouchers WHERE voucher_no LIKE '$prefix_esc%' AND voucher_type = 'Sale Invoice Payment'");
        if (is_array($rows2)) {
            foreach ($rows2 as $row) {
                $vno = (string)($row['voucher_no'] ?? '');
                if (preg_match($regex, $vno, $m)) {
                    $maxNum = max($maxNum, (int)$m[1]);
                }
            }
        }
    } elseif ($tbl2) {
        mysqli_free_result($tbl2);
    }

    $nextNum = max($maxNum + 1, $startEff);
    return $prefix . $nextNum . $suffix;
}

/**
 * Bill series config for Advance Payment (tbl_bill_series + voucher type "Advance Payment" or "Advance").
 * Legacy default: AP-1, AP-2.
 *
 * @return array{prefix:string,suffix:string,start_count:int,from_series_table:bool,voucher_type_id?:int}
 */
function getAdvancePaymentBillSeriesConfig($conn) {
    $legacy = ['prefix' => 'AP-', 'suffix' => '', 'start_count' => 1, 'from_series_table' => false];
    $vtId = 0;
    $r = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(name)) = 'advance payment' LIMIT 1");
    if ($r && !empty($r['id'])) {
        $vtId = (int)$r['id'];
    }
    if ($vtId <= 0) {
        $r = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(name)) = 'advance' LIMIT 1");
        if ($r && !empty($r['id'])) {
            $vtId = (int)$r['id'];
        }
    }
    if ($vtId <= 0) {
        $r2 = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(COALESCE(type_of_voucher,''))) = 'advance payment' LIMIT 1");
        if ($r2 && !empty($r2['id'])) {
            $vtId = (int)$r2['id'];
        }
    }
    if ($vtId <= 0) {
        $r2 = getRecord("SELECT id FROM tbl_voucher_types WHERE status = 1 AND LOWER(TRIM(COALESCE(type_of_voucher,''))) = 'advance' LIMIT 1");
        if ($r2 && !empty($r2['id'])) {
            $vtId = (int)$r2['id'];
        }
    }
    $tableCheck = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_bill_series'");
    if (!$tableCheck || mysqli_num_rows($tableCheck) === 0) {
        if ($tableCheck) {
            mysqli_free_result($tableCheck);
        }
        return $legacy;
    }
    mysqli_free_result($tableCheck);
    if ($vtId <= 0) {
        return $legacy;
    }
    $series = auragold_bill_series_row_for_voucher_type($conn, $vtId);
    if (!$series || trim((string)($series['prefix'] ?? '')) === '') {
        return $legacy;
    }
    return [
        'prefix' => (string)$series['prefix'],
        'suffix' => (string)($series['suffix'] ?? ''),
        'start_count' => (int)($series['start_count'] ?? 0),
        'from_series_table' => true,
        'voucher_type_id' => $vtId,
    ];
}

/**
 * Next advance payment voucher number from bill series or legacy AP-1, AP-2.
 */
function getNextAdvancePaymentNo($conn) {
    $cfg = getAdvancePaymentBillSeriesConfig($conn);
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $start = (int)($cfg['start_count'] ?? 0);
    $startEff = max(1, $start);

    $tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_advance_payments'");
    if (!$tbl || mysqli_num_rows($tbl) === 0) {
        if ($tbl) {
            mysqli_free_result($tbl);
        }
        return $prefix . $startEff . $suffix;
    }
    mysqli_free_result($tbl);

    $prefix_esc = mysqli_real_escape_string($conn, $prefix);
    $rows = getList("SELECT voucher_no FROM tbl_advance_payments WHERE voucher_no LIKE '$prefix_esc%'");

    $maxNum = 0;
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
    foreach ($rows as $row) {
        $vno = (string)($row['voucher_no'] ?? '');
        if (preg_match($regex, $vno, $m)) {
            $maxNum = max($maxNum, (int)$m[1]);
        }
    }
    $nextNum = max($maxNum + 1, $startEff);
    return $prefix . $nextNum . $suffix;
}

function bumpAdvancePaymentNo($conn, $voucher_no, array $cfg) {
    $prefix = $cfg['prefix'];
    $suffix = $cfg['suffix'];
    $regex = '/^' . preg_quote($prefix, '/') . '(\d+)' . preg_quote($suffix, '/') . '$/';
    if (preg_match($regex, (string)$voucher_no, $m)) {
        return $prefix . ((int)$m[1] + 1) . $suffix;
    }
    return getNextAdvancePaymentNo($conn);
}

/**
 * Generate next barcode: prefix + zero-padded serial number, globally unique per prefix, starting from 1.
 * E.g. prefix RN, 5 digits: RN00001, RN00002 ... RN00010, RN00100, RN00101.
 * Fetches last barcode from tbl_product_characteristics, tbl_stock, tbl_stock_journal, tbl_purchase_invoice_items; extracts numeric part; increment; zero-pad; concatenate.
 * If no previous barcode for prefix, returns prefix + 00001.
 * Optional $used_barcodes: array of barcodes already used in current session (e.g. Product List) so next is unique across DB + session.
 *
 * @param mysqli $conn
 * @param string $prefix  Barcode prefix (e.g. RN, B)
 * @param int    $digit   Number of digits for zero-padding (e.g. 5 => 00001)
 * @param array  $used_barcodes  Optional barcodes already in use this session (same prefix considered for max)
 * @return string  New barcode (e.g. RN00001). Avoids duplicate by using max+1.
 */
function auragold_barcode_scan_tables_for_max() {
    // Placeholder __LIKE__ is replaced with a single quoted literal, e.g. 'K%'. Do not wrap __LIKE__ in quotes in the template.
    return [
        ['table' => 'tbl_product_characteristics', 'where_tpl' => "status = 1 AND barcode IS NOT NULL AND barcode != '' AND barcode LIKE __LIKE__", 'col' => 'barcode'],
        ['table' => 'tbl_stock', 'where_tpl' => "barcode IS NOT NULL AND barcode != '' AND barcode LIKE __LIKE__", 'col' => 'barcode'],
        ['table' => 'tbl_stock_journal', 'where_tpl' => "barcode IS NOT NULL AND barcode != '' AND barcode LIKE __LIKE__ AND status = 'active'", 'col' => 'barcode'],
        ['table' => 'tbl_purchase_invoice_items', 'where_tpl' => "barcode IS NOT NULL AND barcode != '' AND barcode LIKE __LIKE__ AND active = 1", 'col' => 'barcode'],
        ['table' => 'tbl_sale_invoice_items', 'where_tpl' => "barcode IS NOT NULL AND barcode != '' AND barcode LIKE __LIKE__", 'col' => 'barcode'],
        ['table' => 'tbl_sale_order_items', 'where_tpl' => "barcode IS NOT NULL AND barcode != '' AND barcode LIKE __LIKE__", 'col' => 'barcode'],
        ['table' => 'tbl_sale_quotation_items', 'where_tpl' => "barcode IS NOT NULL AND barcode != '' AND barcode LIKE __LIKE__", 'col' => 'barcode'],
        ['table' => 'tbl_sale_return_items', 'where_tpl' => "barcode IS NOT NULL AND barcode != '' AND barcode LIKE __LIKE__", 'col' => 'barcode'],
        ['table' => 'tbl_purchase_quotation_items', 'where_tpl' => "barcode IS NOT NULL AND barcode != '' AND barcode LIKE __LIKE__", 'col' => 'barcode'],
        ['table' => 'tbl_purchase_return_items', 'where_tpl' => "barcode IS NOT NULL AND barcode != '' AND barcode LIKE __LIKE__", 'col' => 'barcode'],
        ['table' => 'tbl_repair_order_items', 'where_tpl' => "barcode IS NOT NULL AND barcode != '' AND barcode LIKE __LIKE__", 'col' => 'barcode'],
        ['table' => 'tbl_old_jewelry_scrap_invoice_items', 'where_tpl' => "barcode IS NOT NULL AND barcode != '' AND barcode LIKE __LIKE__", 'col' => 'barcode'],
        ['table' => 'tbl_old_jewelry_stock', 'where_tpl' => "barcode IS NOT NULL AND barcode != '' AND barcode LIKE __LIKE__", 'col' => 'barcode'],
    ];
}

/**
 * True if barcode string is already used anywhere in inventory / item tables (exact match).
 *
 * @param int $exclude_old_jewelry_scrap_item_id If >0, ignore this tbl_old_jewelry_scrap_invoice_items.id (same line may keep its barcode until stock creates a new one).
 */
function auragold_barcode_exists_in_system($conn, $barcode, $exclude_old_jewelry_scrap_item_id = 0) {
    $barcode = trim((string) $barcode);
    if ($barcode === '') {
        return false;
    }
    $b = mysqli_real_escape_string($conn, $barcode);
    $ex_oj = (int) $exclude_old_jewelry_scrap_item_id;
    $oj_scrap_where = "barcode = '$b'";
    if ($ex_oj > 0) {
        $oj_scrap_where .= ' AND id != ' . $ex_oj;
    }
    $checks = [
        ['tbl_product_characteristics', "status = 1 AND barcode = '$b'"],
        ['tbl_stock', "barcode = '$b'"],
        ['tbl_stock_journal', "barcode = '$b' AND status = 'active'"],
        ['tbl_purchase_invoice_items', "barcode = '$b' AND active = 1"],
        ['tbl_sale_invoice_items', "barcode = '$b'"],
        ['tbl_sale_order_items', "barcode = '$b'"],
        ['tbl_sale_quotation_items', "barcode = '$b'"],
        ['tbl_sale_return_items', "barcode = '$b'"],
        ['tbl_purchase_quotation_items', "barcode = '$b'"],
        ['tbl_purchase_return_items', "barcode = '$b'"],
        ['tbl_repair_order_items', "barcode = '$b'"],
        ['tbl_old_jewelry_scrap_invoice_items', $oj_scrap_where],
        ['tbl_old_jewelry_stock', "barcode = '$b'"],
    ];
    foreach ($checks as $pair) {
        $tbl = $pair[0];
        $where = $pair[1];
        $tc = @mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $tbl) . "'");
        if (!$tc || mysqli_num_rows($tc) === 0) {
            if ($tc) {
                mysqli_free_result($tc);
            }
            continue;
        }
        mysqli_free_result($tc);
        $cc = @mysqli_query($conn, "SHOW COLUMNS FROM `$tbl` LIKE 'barcode'");
        if (!$cc || mysqli_num_rows($cc) === 0) {
            if ($cc) {
                mysqli_free_result($cc);
            }
            continue;
        }
        mysqli_free_result($cc);
        if (getRecord("SELECT 1 FROM `$tbl` WHERE $where LIMIT 1")) {
            return true;
        }
    }
    return false;
}

function generateBarcode($conn, $prefix, $digit, $used_barcodes = []) {
    $prefix = trim((string)$prefix);
    if ($prefix === '') $prefix = 'RN';
    $digit = (int)$digit;
    if ($digit < 1) $digit = 5;

    $prefix_esc = mysqli_real_escape_string($conn, $prefix);
    // One quoted LIKE pattern (prefix is already escaped; % is wildcard outside escaping rules for MySQL string literals).
    $like_pattern_sql = "'" . $prefix_esc . "%'";

    $max_num = 0;

    $tables = [];
    foreach (auragold_barcode_scan_tables_for_max() as $t) {
        $tables[] = [
            'table' => $t['table'],
            'where' => str_replace('__LIKE__', $like_pattern_sql, $t['where_tpl']),
            'col' => $t['col'],
        ];
    }
    foreach ($tables as $t) {
        $chk = @mysqli_query($conn, "SHOW COLUMNS FROM {$t['table']} LIKE '{$t['col']}'");
        if (!$chk || mysqli_num_rows($chk) === 0) continue;
        mysqli_free_result($chk);
        $rows = getList("SELECT {$t['col']} AS barcode FROM {$t['table']} WHERE {$t['where']}");
        foreach ($rows as $row) {
            $lb = trim($row['barcode'] ?? '');
            if ($lb !== '' && strpos($lb, $prefix) === 0) {
                $np = substr($lb, strlen($prefix));
                if (preg_match('/^[0-9]+$/', $np)) $max_num = max($max_num, (int)$np);
            }
        }
    }
    if (!empty($used_barcodes)) {
        foreach ($used_barcodes as $ub) {
            $ub = trim((string)$ub);
            if ($ub !== '' && strpos($ub, $prefix) === 0) {
                $np = substr($ub, strlen($prefix));
                if (preg_match('/^[0-9]+$/', $np)) $max_num = max($max_num, (int)$np);
            }
        }
    }

    $next_num = $max_num + 1;
    $maxAttempts = 5000;
    $attempts = 0;
    while ($attempts < $maxAttempts) {
        $barcode = $prefix . str_pad((string)$next_num, $digit, '0', STR_PAD_LEFT);
        if (!auragold_barcode_exists_in_system($conn, $barcode)) {
            return $barcode;
        }
        $next_num++;
        $attempts++;
    }
    throw new Exception('Could not allocate a unique barcode after ' . $maxAttempts . ' attempts.');
}

/**
 * Generate next unique barcode in TP + 5-digit format (e.g. TP00001, TP00002).
 * Uses same sequence for product opening and purchase invoice so barcodes never repeat.
 * Product opening = TP00001, next purchase invoice item = TP00002, then TP00003, etc.
 *
 * @param mysqli $conn
 * @param array  $used_barcodes  Optional barcodes already assigned in current request (e.g. same invoice)
 * @return string  Next barcode (e.g. TP00002)
 */
function getNextTPBarcode($conn, $used_barcodes = []) {
    return generateBarcode($conn, 'TP', 5, $used_barcodes);
}

/** Next barcode: RN + 5 digits (same global sequence as product opening / invoices). */
function getNextRNBarcode($conn, $used_barcodes = []) {
    return generateBarcode($conn, 'RN', 5, $used_barcodes);
}

/**
 * Generate next barcode: uses generateBarcode with override or tbl_settings.
 *
 * @param mysqli $conn
 * @param int $product_id (kept for API compatibility; sequence is per prefix)
 * @param string|null $override_prefix
 * @param int|null $override_digit_length
 * @return string
 */
function generateNextBarcode($conn, $product_id, $override_prefix = null, $override_digit_length = null) {
    $prefix = 'RN';
    $digit_length = 5;
    if ($override_prefix !== null && $override_prefix !== '' && (int)$override_digit_length > 0) {
        $prefix = trim($override_prefix);
        if ($prefix === '') $prefix = 'RN';
        $digit_length = (int)$override_digit_length;
        if ($digit_length < 1) $digit_length = 5;
        return generateBarcode($conn, $prefix, $digit_length);
    }
    $tbl_exists = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_settings'");
    if ($tbl_exists && mysqli_num_rows($tbl_exists) > 0) {
        mysqli_free_result($tbl_exists);
        $cols = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_settings WHERE Field IN ('barcode_prefix','barcode_digit_length')");
        $has_prefix = false;
        $has_length = false;
        if ($cols) {
            while ($c = mysqli_fetch_assoc($cols)) {
                if (($c['Field'] ?? '') === 'barcode_prefix') $has_prefix = true;
                if (($c['Field'] ?? '') === 'barcode_digit_length') $has_length = true;
            }
            mysqli_free_result($cols);
        }
        if ($has_prefix && $has_length) {
            $row = getRecord("SELECT barcode_prefix, barcode_digit_length FROM tbl_settings LIMIT 1");
            if ($row) {
                $prefix = trim($row['barcode_prefix'] ?? 'RN');
                if ($prefix === '') $prefix = 'RN';
                $digit_length = (int)($row['barcode_digit_length'] ?? 5);
                if ($digit_length < 1) $digit_length = 5;
            }
        }
    }
    return generateBarcode($conn, $prefix, $digit_length);
}

/**
 * Generate next barcode from an existing barcode (e.g. GB00002 -> GB00003).
 * Extracts prefix and numeric part, increments, and ensures uniqueness in tbl_stock / tbl_product_characteristics.
 *
 * @param mysqli $conn
 * @param string $old_barcode  Existing barcode (e.g. GB00002)
 * @return string  New unique barcode (e.g. GB00003)
 */
function generateNextBarcodeFromOld($conn, $old_barcode) {
    $old_barcode = trim((string)$old_barcode);
    if ($old_barcode === '') {
        return generateNextBarcode($conn, 0);
    }
    if (preg_match('/^(.+?)([0-9]+)$/', $old_barcode, $m)) {
        $prefix = $m[1];
        $num = (int)$m[2];
        $digit = strlen($m[2]);
        $next_num = $num + 1;
        $new_barcode = $prefix . str_pad((string)$next_num, $digit, '0', STR_PAD_LEFT);
        $new_barcode_esc = mysqli_real_escape_string($conn, $new_barcode);
        while (getRecord("SELECT 1 FROM tbl_stock WHERE barcode = '$new_barcode_esc' LIMIT 1")
            || getRecord("SELECT 1 FROM tbl_product_characteristics WHERE status = 1 AND barcode = '$new_barcode_esc' LIMIT 1")) {
            $next_num++;
            $new_barcode = $prefix . str_pad((string)$next_num, $digit, '0', STR_PAD_LEFT);
            $new_barcode_esc = mysqli_real_escape_string($conn, $new_barcode);
        }
        return $new_barcode;
    }
    return generateNextBarcode($conn, 0);
}

function auragold_barcode_label_standard_presets() {
    return ['100x18', '100x25', '100x48', '100x80', '64x25', '81x12', '120x50', '82x38_2box', '250x120', 'zebra-zpl'];
}

/** DB lookup key: one row per metal + label size (custom sizes use custom_WxH). */
function auragold_barcode_label_storage_preset($preset, $labelWidthMm = null, $labelHeightMm = null) {
    $p = trim((string) $preset);
    if ($p === '120x50') {
        return '120x50';
    }
    if ($p === '82x38_2box' || $p === '82x38-2box') {
        return '82x38_2box';
    }
    if ($p !== '' && $p !== 'custom' && in_array($p, auragold_barcode_label_standard_presets(), true)) {
        return $p;
    }
    if (preg_match('/^custom_\d+x\d+$/i', $p)) {
        return strtolower($p);
    }
    $w = max(10, (int) round((float) ($labelWidthMm ?? 100)));
    $h = max(10, (int) round((float) ($labelHeightMm ?? 18)));
    return 'custom_' . $w . 'x' . $h;
}

/** Map storage preset from DB back to Label Size dropdown value. */
function auragold_barcode_label_ui_preset($storagePreset, $labelWidthMm = null, $labelHeightMm = null) {
    $p = trim((string) $storagePreset);
    if (preg_match('/^custom_\d+x\d+$/i', $p)) {
        return 'custom';
    }
    $ui = array_merge(auragold_barcode_label_standard_presets(), ['custom']);
    if (in_array($p, $ui, true)) {
        return $p;
    }
    if ($p === 'custom' || $p === '') {
        return 'custom';
    }
    $dim = (int) round((float) ($labelWidthMm ?? 100)) . 'x' . (int) round((float) ($labelHeightMm ?? 18));
    if (in_array($dim, $ui, true)) {
        return $dim;
    }
    return 'custom';
}

function auragold_barcode_settings_cache_key($metalType, $storagePreset) {
    return trim((string) $metalType) . '::' . trim((string) $storagePreset);
}

/** Width/height in mm from storage preset (120x50, custom_100x25, …). */
function auragold_barcode_label_mm_from_storage_preset($storagePreset, $fallbackW = 100, $fallbackH = 18) {
    $p = trim((string) $storagePreset);
    if (preg_match('/^custom_(\d+(?:\.\d+)?)x(\d+(?:\.\d+)?)$/i', $p, $m)) {
        return [(float) $m[1], (float) $m[2]];
    }
    $p = str_replace(' ', '', strtolower($p));
    if ($p === '82x38_2box' || $p === '82x38-2box') {
        return [82.0, 38.0];
    }
    if (preg_match('/^(\d+(?:\.\d+)?)x(\d+(?:\.\d+)?)$/', $p, $m)) {
        return [(float) $m[1], (float) $m[2]];
    }
    return [(float) $fallbackW, (float) $fallbackH];
}

/** Fix rows where label_size_preset=120x50 but label_width_mm/height_mm were saved as stale custom values. */
function auragold_barcode_settings_normalize_label_mm($row) {
    if (!is_array($row) || empty($row)) {
        return $row;
    }
    [$w, $h] = auragold_barcode_label_mm_from_storage_preset(
        $row['label_size_preset'] ?? '100x18',
        $row['label_width_mm'] ?? 100,
        $row['label_height_mm'] ?? 18
    );
    $row['label_width_mm'] = $w;
    $row['label_height_mm'] = $h;
    return $row;
}

/**
 * Load barcode print settings: metal + optional label preset, with mm synced to preset name.
 *
 * @param string|null $metalType
 * @param string|null $labelPresetHint
 * @return array|null
 */
function getBarcodeSettingsForPrint($metalType = null, $labelPresetHint = null) {
    $metal = ($metalType !== null && trim((string) $metalType) !== '') ? trim((string) $metalType) : '';
    $labelHint = ($labelPresetHint !== null && trim((string) $labelPresetHint) !== '') ? trim((string) $labelPresetHint) : '';
    $row = null;
    if ($metal !== '') {
        if ($labelHint !== '') {
            $row = getBarcodeSettings($metal, $labelHint);
        }
        if (!$row) {
            $row = getBarcodeSettings($metal);
        }
    }
    if (!$row) {
        $row = getBarcodeSettings();
    }
    if ($row) {
        $row = auragold_barcode_settings_normalize_label_mm($row);
    }
    return $row;
}

/**
 * Fetch the latest barcode printing settings (for label size, font, show/hide options, print copies).
 * Returns associative array or null if table/row missing.
 *
 * @param string|null $metalType        Display name e.g. Gold, Silver.
 * @param string|null $labelSizePreset  UI or storage preset (100x18, custom, custom_100x80, …).
 * @param float|null  $labelWidthMm     Used when preset is custom / 120x50.
 * @param float|null  $labelHeightMm
 * @return array|null  Keys: id, label_size_preset, label_width_mm, label_height_mm, font_size,
 *                     show_product_name, show_price, show_barcode_number, print_copies,
 *                     barcode_bar_width, barcode_bar_height (when columns exist), metal_type, design_layout
 */
function getBarcodeSettings($metalType = null, $labelSizePreset = null, $labelWidthMm = null, $labelHeightMm = null) {
    global $conn;
    $table = 'tbl_barcode_settings';
    $exists = @mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if (!$exists || mysqli_num_rows($exists) === 0) {
        if ($exists) mysqli_free_result($exists);
        return null;
    }
    mysqli_free_result($exists);
    auragold_ensure_branch_id_on_settings_tables($conn);
    $bid = auragold_settings_branch_id();
    $hasBranch = auragold_tbl_has_column($conn, $table, 'branch_id');
    $branchSql = ($hasBranch && $bid > 0) ? (' WHERE branch_id = ' . (int) $bid) : '';
    $colsBase = "id, label_size_preset, label_width_mm, label_height_mm, font_size, show_product_name, show_price, show_barcode_number, print_copies";
    $splitShowCols = [
        'show_product_name_barcode', 'show_product_name_qr',
        'show_price_barcode', 'show_price_qr',
        'show_barcode_number_barcode', 'show_barcode_number_qr',
    ];
    foreach ($splitShowCols as $sc) {
        if (auragold_tbl_has_column($conn, $table, $sc)) {
            $colsBase .= ', `' . $sc . '`';
        }
    }
    $chkBw = @mysqli_query($conn, "SHOW COLUMNS FROM $table LIKE 'barcode_bar_width'");
    $hasBarcodeDims = ($chkBw && mysqli_num_rows($chkBw) > 0);
    if ($chkBw) {
        mysqli_free_result($chkBw);
    }
    if ($hasBarcodeDims) {
        $colsBase .= ", barcode_bar_width, barcode_bar_height";
    }
    $colsBase .= ", metal_type";
    $row = null;
    $metalFilter = ($metalType !== null && trim((string) $metalType) !== '') ? trim((string) $metalType) : '';
    $storagePreset = null;
    if ($labelSizePreset !== null && trim((string) $labelSizePreset) !== '') {
        $storagePreset = auragold_barcode_label_storage_preset($labelSizePreset, $labelWidthMm, $labelHeightMm);
    }
    if ($metalFilter !== '') {
        $mtEsc = mysqli_real_escape_string($conn, $metalFilter);
        if ($storagePreset !== null && $storagePreset !== '') {
            $psEsc = mysqli_real_escape_string($conn, $storagePreset);
            $metalWhere = ($branchSql !== '' ? $branchSql . ' AND' : ' WHERE') . " metal_type = '$mtEsc' AND label_size_preset = '$psEsc'";
            $row = getRecord("SELECT $colsBase FROM $table $metalWhere ORDER BY id DESC LIMIT 1");
            if (empty($row) && strpos($storagePreset, 'custom_') === 0) {
                $legacyWhere = ($branchSql !== '' ? $branchSql . ' AND' : ' WHERE') . " metal_type = '$mtEsc' AND label_size_preset = 'custom'";
                $row = getRecord("SELECT $colsBase FROM $table $legacyWhere ORDER BY id DESC LIMIT 1");
            }
        } elseif (empty($row)) {
            $metalWhere = ($branchSql !== '' ? $branchSql . ' AND' : ' WHERE') . " metal_type = '$mtEsc'";
            $row = getRecord("SELECT $colsBase FROM $table $metalWhere ORDER BY updated_at DESC, id DESC LIMIT 1");
        }
    } else {
        $row = getRecord("SELECT $colsBase FROM $table $branchSql ORDER BY id DESC LIMIT 1");
        if (!$row && $hasBranch && $bid > 0) {
            $row = getRecord("SELECT $colsBase FROM $table WHERE (branch_id IS NULL OR branch_id = 0) ORDER BY id DESC LIMIT 1");
        }
        if (!$row && $branchSql !== '') {
            $row = getRecord("SELECT $colsBase FROM $table ORDER BY id DESC LIMIT 1");
        }
    }
    if ($row && !empty($row['id'])) {
        $rowId = (int) $row['id'];
        $chk = @mysqli_query($conn, "SHOW COLUMNS FROM $table LIKE 'design_layout'");
        if ($chk && mysqli_num_rows($chk) > 0) {
            mysqli_free_result($chk);
            $r2 = getRecord("SELECT design_layout FROM $table WHERE id = $rowId LIMIT 1");
            $row['design_layout'] = $r2 ? $r2['design_layout'] : null;
        } else {
            if ($chk) {
                mysqli_free_result($chk);
            }
            $row['design_layout'] = null;
        }
        $chkQr = @mysqli_query($conn, "SHOW COLUMNS FROM $table LIKE 'design_layout_qr'");
        if ($chkQr && mysqli_num_rows($chkQr) > 0) {
            mysqli_free_result($chkQr);
            $r3 = getRecord("SELECT design_layout_qr, default_print_code_type FROM $table WHERE id = $rowId LIMIT 1");
            if ($r3) {
                $row['design_layout_qr'] = $r3['design_layout_qr'] ?? null;
                $row['default_print_code_type'] = isset($r3['default_print_code_type']) && $r3['default_print_code_type'] === 'qr' ? 'qr' : 'barcode';
            } else {
                $row['design_layout_qr'] = null;
                $row['default_print_code_type'] = 'barcode';
            }
        } else {
            if ($chkQr) {
                mysqli_free_result($chkQr);
            }
            $row['design_layout_qr'] = null;
            $row['default_print_code_type'] = 'barcode';
        }
    }
    if ($row) {
        $legPn = (int)($row['show_product_name'] ?? 1);
        $legPr = (int)($row['show_price'] ?? 1);
        $legBn = (int)($row['show_barcode_number'] ?? 1);
        if (!isset($row['show_product_name_barcode'])) {
            $row['show_product_name_barcode'] = $legPn;
        } else {
            $row['show_product_name_barcode'] = (int)$row['show_product_name_barcode'];
        }
        if (!isset($row['show_product_name_qr'])) {
            $row['show_product_name_qr'] = $legPn;
        } else {
            $row['show_product_name_qr'] = (int)$row['show_product_name_qr'];
        }
        if (!isset($row['show_price_barcode'])) {
            $row['show_price_barcode'] = $legPr;
        } else {
            $row['show_price_barcode'] = (int)$row['show_price_barcode'];
        }
        if (!isset($row['show_price_qr'])) {
            $row['show_price_qr'] = $legPr;
        } else {
            $row['show_price_qr'] = (int)$row['show_price_qr'];
        }
        if (!isset($row['show_barcode_number_barcode'])) {
            $row['show_barcode_number_barcode'] = $legBn;
        } else {
            $row['show_barcode_number_barcode'] = (int)$row['show_barcode_number_barcode'];
        }
        if (!isset($row['show_barcode_number_qr'])) {
            $row['show_barcode_number_qr'] = $legBn;
        } else {
            $row['show_barcode_number_qr'] = (int)$row['show_barcode_number_qr'];
        }
    }
    if ($row) {
        $row = auragold_barcode_settings_normalize_label_mm($row);
    }
    return $row;
}

/**
 * All saved barcode rows for branch, keyed "Metal::label_size_preset" (storage preset).
 *
 * @return array<string, array> designer snapshots for JS cache
 */
function getBarcodeSettingsCacheMap() {
    global $conn;
    $table = 'tbl_barcode_settings';
    $exists = @mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if (!$exists || mysqli_num_rows($exists) === 0) {
        if ($exists) {
            mysqli_free_result($exists);
        }
        return [];
    }
    mysqli_free_result($exists);
    auragold_ensure_branch_id_on_settings_tables($conn);
    $bid = auragold_settings_branch_id();
    $hasBranch = auragold_tbl_has_column($conn, $table, 'branch_id');
    $branchSql = ($hasBranch && $bid > 0) ? (' WHERE branch_id = ' . (int) $bid) : '';
    $listWhere = ($branchSql !== '' ? $branchSql . ' AND' : ' WHERE') . " metal_type IS NOT NULL AND metal_type != '' AND label_size_preset IS NOT NULL AND label_size_preset != ''";
    $rows = getList("SELECT id, metal_type, label_size_preset FROM $table $listWhere ORDER BY metal_type ASC, label_size_preset ASC");
    if (!is_array($rows)) {
        return [];
    }
    $cache = [];
    foreach ($rows as $r) {
        $mt = trim((string) ($r['metal_type'] ?? ''));
        $ps = trim((string) ($r['label_size_preset'] ?? ''));
        if ($mt === '' || $ps === '') {
            continue;
        }
        $full = getBarcodeSettings($mt, $ps);
        $snap = auragold_barcode_settings_designer_snapshot($full);
        if (!$snap) {
            continue;
        }
        $snap['label_size_storage_preset'] = $ps;
        $snap['label_size_ui_preset'] = auragold_barcode_label_ui_preset($ps, $snap['label_width_mm'], $snap['label_height_mm']);
        $snap['cache_key'] = auragold_barcode_settings_cache_key($mt, $ps);
        $cache[$snap['cache_key']] = $snap;
    }
    return $cache;
}

/** @deprecated Use getBarcodeSettingsCacheMap() */
function getBarcodeSettingsByMetalMap() {
    return getBarcodeSettingsCacheMap();
}

/**
 * Normalized payload for barcode designer JS (per-metal cache / AJAX load).
 *
 * @param array|null $bs Row from getBarcodeSettings()
 * @return array|null
 */
function auragold_barcode_settings_designer_snapshot($bs) {
    if (!is_array($bs) || empty($bs)) {
        return null;
    }
    $dl = isset($bs['design_layout']) ? trim((string) $bs['design_layout']) : '';
    $dlq = isset($bs['design_layout_qr']) ? trim((string) $bs['design_layout_qr']) : '';
    $dl_dec = ($dl !== '') ? @json_decode($dl, true) : [];
    if (!is_array($dl_dec)) {
        $dl_dec = [];
    }
    if ($dl !== '' && empty($dl_dec)) {
        $try = @json_decode(stripslashes($dl), true);
        if (is_array($try)) {
            $dl_dec = $try;
        }
    }
    $dlq_dec = ($dlq !== '') ? @json_decode($dlq, true) : [];
    if (!is_array($dlq_dec)) {
        $dlq_dec = [];
    }
    if ($dlq !== '' && empty($dlq_dec)) {
        $tryq = @json_decode(stripslashes($dlq), true);
        if (is_array($tryq)) {
            $dlq_dec = $tryq;
        }
    }
    $dpt = (isset($bs['default_print_code_type']) && $bs['default_print_code_type'] === 'qr') ? 'qr' : 'barcode';
    $dl_js = !empty($dl_dec) ? json_encode($dl_dec, JSON_UNESCAPED_UNICODE) : ($dl !== '' ? $dl : '{}');
    $dlq_js = !empty($dlq_dec) ? json_encode($dlq_dec, JSON_UNESCAPED_UNICODE) : ($dlq !== '' ? $dlq : '{}');
    $leg_pn = (int) ($bs['show_product_name'] ?? 1);
    $leg_pr = (int) ($bs['show_price'] ?? 1);
    $leg_bn = (int) ($bs['show_barcode_number'] ?? 1);
    $storagePreset = trim((string) ($bs['label_size_preset'] ?? '100x18'));
    $uiPreset = auragold_barcode_label_ui_preset($storagePreset, $bs['label_width_mm'] ?? 100, $bs['label_height_mm'] ?? 18);
    return [
        'metal_type' => trim((string) ($bs['metal_type'] ?? '')),
        'label_size_preset' => $storagePreset,
        'label_size_storage_preset' => $storagePreset,
        'label_size_ui_preset' => $uiPreset,
        'label_width_mm' => (float) ($bs['label_width_mm'] ?? 100),
        'label_height_mm' => (float) ($bs['label_height_mm'] ?? 18),
        'font_size' => (int) ($bs['font_size'] ?? 12),
        'print_copies' => (int) ($bs['print_copies'] ?? 1),
        'default_print_code_type' => $dpt,
        'show_product_name_barcode' => (int) ($bs['show_product_name_barcode'] ?? $leg_pn),
        'show_product_name_qr' => (int) ($bs['show_product_name_qr'] ?? $leg_pn),
        'show_price_barcode' => (int) ($bs['show_price_barcode'] ?? $leg_pr),
        'show_price_qr' => (int) ($bs['show_price_qr'] ?? $leg_pr),
        'show_barcode_number_barcode' => (int) ($bs['show_barcode_number_barcode'] ?? $leg_bn),
        'show_barcode_number_qr' => (int) ($bs['show_barcode_number_qr'] ?? $leg_bn),
        'barcode_bar_width' => (int) ($bs['barcode_bar_width'] ?? 2),
        'barcode_bar_height' => (int) ($bs['barcode_bar_height'] ?? 28),
        'qr_width' => (int) ($dlq_dec['qr_width'] ?? $dl_dec['qr_width'] ?? 60),
        'qr_height' => (int) ($dlq_dec['qr_height'] ?? $dl_dec['qr_height'] ?? 60),
        'label_pad_top' => (int) ($dl_dec['label_pad_top'] ?? $dlq_dec['label_pad_top'] ?? 0),
        'label_pad_right' => (int) ($dl_dec['label_pad_right'] ?? $dlq_dec['label_pad_right'] ?? 0),
        'label_pad_bottom' => (int) ($dl_dec['label_pad_bottom'] ?? $dlq_dec['label_pad_bottom'] ?? 0),
        'label_pad_left' => (int) ($dl_dec['label_pad_left'] ?? $dlq_dec['label_pad_left'] ?? 0),
        'design_layout_barcode' => $dl_js,
        'design_layout_qr' => $dlq_js,
    ];
}

/** Default voucher setting row (one per metal) */
function getVoucherSettingsDefaults() {
    return [
        'minimum_amount_column' => 'Amount',
        'reverse_calculation_result_column' => 'MakingRate',
        'default_discount_type' => 'Fix',
        'default_calculation_type' => 'Fix',
        'stock_availability_check_by' => 'Carat',
        'wastage_wt_calculation' => 'GoldWt',
    ];
}

/** Metal options for voucher setting (order of tabs) */
function getVoucherSettingMetals() {
    return ['Gold', 'Silver', 'Platinum', 'Diamond & Stones', 'Imitation Or Watches', 'Other Or Services'];
}

/**
 * Fetch voucher settings from tbl_voucher_settings: one row per metal.
 * Returns associative array keyed by metal_wise: ['Gold' => [...], 'Silver' => [...], ...].
 * Each value has keys: minimum_amount_column, reverse_calculation_result_column, default_discount_type, default_calculation_type, stock_availability_check_by, wastage_wt_calculation.
 * Missing metals get default values.
 */
function getVoucherSettings($branch_id = null) {
    global $conn;
    $table = 'tbl_voucher_settings';
    $metals = getVoucherSettingMetals();
    $defaults = getVoucherSettingsDefaults();
    $out = [];
    foreach ($metals as $m) {
        $out[$m] = $defaults;
    }
    require_once __DIR__ . '/includes/auragold_voucher_settings_schema.php';
    auragold_ensure_tbl_voucher_settings($conn);
    $exists = @mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if (!$exists || mysqli_num_rows($exists) === 0) {
        if ($exists) {
            mysqli_free_result($exists);
        }
        return $out;
    }
    mysqli_free_result($exists);

    $bid = ($branch_id !== null && (int) $branch_id > 0)
        ? (int) $branch_id
        : auragold_resolve_voucher_settings_branch_id(null);
    if ($bid <= 0 || !auragold_tbl_has_column($conn, $table, 'branch_id')) {
        return $out;
    }

    $hasWastageCol = auragold_tbl_has_column($conn, $table, 'wastage_wt_calculation');
    $stmt = mysqli_prepare($conn, "SELECT * FROM `{$table}` WHERE branch_id = ? ORDER BY metal_wise ASC, id ASC");
    if (!$stmt) {
        return $out;
    }
    mysqli_stmt_bind_param($stmt, 'i', $bid);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $m = trim((string) ($r['metal_wise'] ?? ''));
            if ($m === '' || !isset($out[$m])) {
                continue;
            }
            $out[$m] = auragold_voucher_settings_row_from_db($r, $defaults, $hasWastageCol);
        }
        mysqli_free_result($res);
    }
    mysqli_stmt_close($stmt);
    return $out;
}

/**
 * Fetch voucher settings for a single metal (e.g. for use in sale invoice by metal).
 * Returns same structure as one element of getVoucherSettings().
 */
function getVoucherSettingsForMetal($metal_wise) {
    $all = getVoucherSettings();
    $metals = getVoucherSettingMetals();
    $key = in_array($metal_wise, $metals, true) ? $metal_wise : 'Gold';
    return $all[$key];
}

/**
 * Fetch Sale Invoice print settings from tbl_invoice_print_settings.
 * Returns associative array: setting_key => value (decoded JSON for sale_invoice_columns, 1/0 for toggles, string for layout_type).
 * If table does not exist, returns defaults.
 *
 * @return array  Keys: sale_invoice_columns (array), header_company_logo, ...
 */
function getInvoicePrintSettings() {
    return getInvoicePrintSettingsForDocument('default');
}

/** Valid document/setting types for print settings */
function getInvoicePrintSettingTypes() {
    return [
        'default',
        'sale_invoice',
        'purchase_invoice',
        'sale_order',
        'purchase_order',
        'purchase_quotation',
        'sale_quotation',
        'sale_return',
        'purchase_return',
        'sale_fixing_direct',
        'payment_voucher',
        'receipt_voucher',
        'advance_payment',
        'metal_to_amount',
        'amount_to_metal',
    ];
}

/**
 * Load print settings for a given setting type. If none found for type, returns default type settings.
 * @param string $setting_type  One of: default, sale_invoice, purchase_invoice, sale_order, purchase_quotation, sale_quotation, sale_return, purchase_return
 * @return array  Same structure as getInvoicePrintSettingsDefaults()
 */
function getInvoicePrintSettingsByType($setting_type) {
    global $conn;
    $table = 'tbl_invoice_print_settings';
    $exists = @mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if (!$exists || mysqli_num_rows($exists) === 0) {
        if ($exists) mysqli_free_result($exists);
        return getInvoicePrintSettingsDefaults();
    }
    mysqli_free_result($exists);
    auragold_ensure_branch_id_on_settings_tables($conn);
    $bid = auragold_settings_branch_id();
    $has_branch = auragold_tbl_has_column($conn, $table, 'branch_id');
    $branchSql = ($has_branch && $bid > 0) ? (' AND branch_id = ' . (int) $bid) : '';
    $has_type = false;
    $col = @mysqli_query($conn, "SHOW COLUMNS FROM $table LIKE 'setting_type'");
    if ($col && mysqli_num_rows($col) > 0) {
        $has_type = true;
        mysqli_free_result($col);
    }
    $out = getInvoicePrintSettingsDefaults();
    if ($has_type) {
        $st = mysqli_real_escape_string($conn, $setting_type);
        $rows = getList("SELECT setting_key, setting_value FROM $table WHERE setting_type = '$st' $branchSql");
        if (empty($rows) && $setting_type !== 'default') {
            return getInvoicePrintSettingsByType('default');
        }
    } else {
        $rows = getList("SELECT setting_key, setting_value FROM $table WHERE 1=1 $branchSql");
    }
    foreach ($rows as $r) {
        $k = $r['setting_key'] ?? '';
        $v = $r['setting_value'] ?? '';
        if ($k === 'sale_invoice_columns') {
            $dec = @json_decode($v, true);
            $out[$k] = is_array($dec) ? $dec : $out[$k];
        } elseif (in_array($k, ['column_header_labels', 'summary_label_overrides'], true)) {
            $dec = @json_decode($v, true);
            $out[$k] = is_array($dec) ? $dec : $out[$k];
        } elseif ($k === 'summary_row_order') {
            $dec = @json_decode($v, true);
            $out[$k] = is_array($dec) ? array_values($dec) : $out[$k];
        } elseif ($k === 't6_column_labels') {
            $dec = @json_decode($v, true);
            $out[$k] = is_array($dec) ? $dec : $out[$k];
        } elseif (in_array($k, ['t6_show_item_vertical_lines', 't6_show_currency_on_amounts'], true)) {
            $out[$k] = ($v === '1' || $v === 1) ? '1' : '0';
        } elseif (in_array($k, ['header_company_logo','header_company_name','header_gst_number','header_phone','header_invoice_title','header_section_enabled','footer_terms_conditions','footer_authorized_signature','footer_thank_you_message','footer_show_banner'], true)) {
            $out[$k] = ($v === '1' || $v === 1) ? '1' : '0';
        } elseif ($k === 'layout_type') {
            $out[$k] = normalizeInvoicePrintLayoutType($v);
        } elseif ($k === 'page_orientation') {
            $out[$k] = normalizeInvoicePrintPageOrientation($v);
        } else {
            $out[$k] = $v;
        }
    }
    return $out;
}

/**
 * Get print settings for a document type. Falls back to default if document type has no settings.
 * @param string $document_type  e.g. sale_invoice, purchase_invoice, sale_order, purchase_quotation, sale_quotation, sale_return, purchase_return
 * @return array
 */
function getInvoicePrintSettingsForDocument($document_type) {
    $valid = getInvoicePrintSettingTypes();
    if (!in_array($document_type, $valid, true)) {
        $document_type = 'default';
    }
    return getInvoicePrintSettingsByType($document_type);
}

/**
 * Default values for invoice print settings (when table missing or key missing).
 */
function getInvoicePrintSettingsDefaults() {
    return [
        'sale_invoice_columns' => ['sr_no','item_name','design_no','huid','category','gross_weight','less_weight','net_weight','purity_karat','rate','making_charge','diamond_amount','stone_amount','discount','amount'],
        'header_company_logo' => '1',
        'header_company_name' => '1',
        'header_gst_number' => '1',
        'header_phone' => '1',
        'header_invoice_title' => '1',
        'footer_terms_conditions' => '1',
        'footer_authorized_signature' => '1',
        'footer_thank_you_message' => '1',
        'layout_type' => 'A4',
        'company_logo_path' => '',
        'company_name' => '',
        'company_address' => '',
        'company_gst' => '',
        'company_phone' => '',
        'company_email' => '',
        'invoice_title' => '',
        'terms_conditions' => '',
        'authorized_signature' => '',
        'thank_you_message' => '',
        'invoice_secondary_language' => '',
        'advertise_banner_path' => '',
        'footer_show_banner' => '0',
        'design_template' => 'template_1',
        'invoice_template' => 'template_classic',
        'page_orientation' => 'portrait',
        'header_section_enabled' => '1',
        'print_padding_top_mm' => '0',
        'column_header_labels' => [],
        'summary_label_overrides' => [],
        'summary_row_order' => ['total', 'advance_amount', 'total_before_vat', 'vat_5_label', 'total_including_vat', 'less_scrap', 'balance_amount'],
        'company_pan' => '',
        't6_show_item_vertical_lines' => '0',
        't6_show_currency_on_amounts' => '0',
        't6_rates_banner_format' => '',
        't6_min_item_rows' => '12',
        't6_label_gold_total' => 'Total Gold:',
        't6_label_silver_total' => 'Total Silver:',
        't6_label_total_before_gst' => 'Total Value before GST',
        't6_label_cgst' => 'CGST @ {pct} %',
        't6_label_sgst' => 'SGST @ {pct} %',
        't6_label_total_with_gst' => 'Total Value with GST',
        't6_label_bank_transfer' => 'BANK TRANSFER',
        't6_label_cash' => 'Cash',
        't6_label_last_balance' => 'Last Amount Balance',
        't6_label_current_balance' => 'Current Amount Balance',
        't6_balance_suffix' => ' Dr',
        't6_column_labels' => [],
        'custom_print_css' => '',
    ];
}

/** Keys for Template 6 item table column title overrides (invoice print settings JSON `t6_column_labels`). */
function getInvoicePrintTemplate6ColumnLabelKeys() {
    return ['sno', 'tag_no', 'item', 'hsn', 'gross_wt', 'net_wt', 'dia_wt', 'cst_wt', 'amt', 'tot_amt'];
}

/**
 * Normalized options for Template 6 (Formal B&W) from merged print settings.
 *
 * @param array $print_settings Merged settings (defaults + DB), same as passed to template_6.php
 * @return array{show_item_vertical_lines:bool,show_currency_on_amounts:bool,rates_banner_format:string,column_labels:array,label_*:string,min_item_rows:int,...}
 */
function getInvoicePrintTemplate6Options(array $print_settings) {
    $base = getInvoicePrintSettingsDefaults();
    $s = array_merge($base, $print_settings);
    $colRaw = $s['t6_column_labels'] ?? [];
    if (is_string($colRaw)) {
        $colRaw = @json_decode($colRaw, true) ?: [];
    }
    if (!is_array($colRaw)) {
        $colRaw = [];
    }
    $defaultCols = [
        'sno' => 'SNo', 'tag_no' => 'TagNo', 'item' => 'Item', 'hsn' => 'HSNCode',
        'gross_wt' => 'GrossWt', 'net_wt' => 'NetWt', 'dia_wt' => 'DiaWt', 'cst_wt' => 'CstWt',
        'amt' => 'Amt', 'tot_amt' => 'TotAmt',
    ];
    $column_labels = [];
    foreach ($defaultCols as $k => $def) {
        $column_labels[$k] = (isset($colRaw[$k]) && is_string($colRaw[$k]) && trim($colRaw[$k]) !== '') ? trim($colRaw[$k]) : $def;
    }
    $minRows = (int) ($s['t6_min_item_rows'] ?? 12);
    if ($minRows < 1) {
        $minRows = 1;
    }
    if ($minRows > 40) {
        $minRows = 40;
    }
    $t6l = static function (array $ss, string $key, string $default) {
        $v = trim((string) ($ss[$key] ?? ''));

        return $v !== '' ? $v : $default;
    };
    return [
        'show_item_vertical_lines' => ($s['t6_show_item_vertical_lines'] ?? '0') === '1',
        'show_currency_on_amounts' => ($s['t6_show_currency_on_amounts'] ?? '0') === '1',
        'rates_banner_format' => trim((string) ($s['t6_rates_banner_format'] ?? '')),
        'column_labels' => $column_labels,
        'label_gold_total' => $t6l($s, 't6_label_gold_total', 'Total Gold:'),
        'label_silver_total' => $t6l($s, 't6_label_silver_total', 'Total Silver:'),
        'label_total_before_gst' => $t6l($s, 't6_label_total_before_gst', 'Total Value before GST'),
        'label_cgst' => $t6l($s, 't6_label_cgst', 'CGST @ {pct} %'),
        'label_sgst' => $t6l($s, 't6_label_sgst', 'SGST @ {pct} %'),
        'label_total_with_gst' => $t6l($s, 't6_label_total_with_gst', 'Total Value with GST'),
        'label_bank_transfer' => $t6l($s, 't6_label_bank_transfer', 'BANK TRANSFER'),
        'label_cash' => $t6l($s, 't6_label_cash', 'Cash'),
        'label_last_balance' => $t6l($s, 't6_label_last_balance', 'Last Amount Balance'),
        'label_current_balance' => $t6l($s, 't6_label_current_balance', 'Current Amount Balance'),
        'balance_suffix' => $t6l($s, 't6_balance_suffix', ' Dr'),
        'min_item_rows' => $minRows,
    ];
}

/**
 * Emit optional per-document custom print CSS from settings (admin-controlled).
 */
function invoicePrintEmitCustomCss(array $print_settings) {
    $raw = isset($print_settings['custom_print_css']) ? trim((string) $print_settings['custom_print_css']) : '';
    if ($raw === '') {
        return;
    }
    $raw = str_ireplace(['</style', '<?php'], ['<\/style', ''], $raw);
    echo "\n<style id=\"invoice-custom-print-css\">\n" . $raw . "\n</style>\n";
}

/** Summary block row keys (print totals section) — order configurable per document type. */
function getInvoicePrintSummaryRowKeys() {
    return ['total', 'advance_amount', 'total_before_vat', 'vat_5_label', 'total_including_vat', 'less_scrap', 'balance_amount'];
}

/**
 * Normalized order of summary rows for invoice templates.
 * @param array $print_settings
 * @return string[]
 */
function getInvoicePrintSummaryRowOrder($print_settings) {
    $default = getInvoicePrintSummaryRowKeys();
    if (!is_array($print_settings)) {
        return $default;
    }
    $raw = $print_settings['summary_row_order'] ?? null;
    if (is_string($raw)) {
        $raw = @json_decode($raw, true);
    }
    if (!is_array($raw)) {
        return $default;
    }
    $raw = array_values(array_filter($raw, function ($k) use ($default) {
        return in_array($k, $default, true);
    }));
    foreach ($default as $k) {
        if (!in_array($k, $raw, true)) {
            $raw[] = $k;
        }
    }
    return $raw;
}

/**
 * Apply optional per-column header text from print settings (overrides translated defaults).
 * @param array $col_labels  key => label
 * @param array $print_settings
 * @return array
 */
function mergeInvoicePrintColumnLabels(array $col_labels, array $print_settings) {
    $over = $print_settings['column_header_labels'] ?? [];
    if (is_string($over)) {
        $over = @json_decode($over, true) ?: [];
    }
    if (!is_array($over)) {
        return $col_labels;
    }
    foreach ($over as $k => $v) {
        if (isset($col_labels[$k]) && is_string($v) && trim($v) !== '') {
            $col_labels[$k] = trim($v);
        }
    }
    return $col_labels;
}

/**
 * Override summary line labels (TOTAL, VAT, etc.) from print settings when set.
 * @param array $t  merged language strings used on print
 * @param array $print_settings
 * @return array
 */
function applyInvoicePrintSummaryLabelOverrides(array $t, array $print_settings) {
    $over = $print_settings['summary_label_overrides'] ?? [];
    if (is_string($over)) {
        $over = @json_decode($over, true) ?: [];
    }
    if (!is_array($over)) {
        return $t;
    }
    foreach (getInvoicePrintSummaryRowKeys() as $k) {
        if (!empty($over[$k]) && is_string($over[$k]) && trim($over[$k]) !== '') {
            $t[$k] = trim($over[$k]);
        }
    }
    return $t;
}

/**
 * Optional body padding-top for print pages (millimetres).
 * @param array $print_settings
 * @return string  e.g. ' style="padding-top: 12mm"' or empty
 */
function invoicePrintBodyPaddingAttr(array $print_settings) {
    $mm = isset($print_settings['print_padding_top_mm']) ? trim((string)$print_settings['print_padding_top_mm']) : '';
    if ($mm === '' || !is_numeric($mm)) {
        return '';
    }
    $n = (float)$mm;
    if ($n <= 0) {
        return '';
    }
    if ($n > 80) {
        $n = 80;
    }
    return ' style="padding-top: ' . $n . 'mm"';
}

/**
 * Default document heading hint per settings / document type (when invoice title field is left blank).
 * @param string $setting_type
 * @return string
 */
function getInvoicePrintDefaultDocumentTitle($setting_type) {
    $map = [
        'default' => 'TAX INVOICE',
        'sale_invoice' => 'TAX INVOICE',
        'purchase_invoice' => 'PURCHASE INVOICE',
        'sale_order' => 'SALE ORDER',
        'purchase_order' => 'PURCHASE ORDER',
        'purchase_quotation' => 'PURCHASE QUOTATION',
        'sale_quotation' => 'SALE QUOTATION',
        'sale_return' => 'SALE RETURN',
        'purchase_return' => 'PURCHASE RETURN',
        'sale_fixing_direct' => 'SALE FIXING',
        'payment_voucher' => 'PAYMENT VOUCHER',
        'receipt_voucher' => 'RECEIPT VOUCHER',
        'advance_payment' => 'ADVANCE PAYMENT',
        'metal_to_amount' => 'METAL TO AMOUNT',
        'amount_to_metal' => 'AMOUNT TO METAL',
    ];
    return $map[$setting_type] ?? 'INVOICE';
}

/** Allowed paper sizes stored in `layout_type`. */
function getInvoicePrintLayoutTypeValues() {
    return ['A4', 'A5', 'Thermal 80mm', 'Letter'];
}

/** Allowed values for `page_orientation`. */
function getInvoicePrintPageOrientationValues() {
    return ['portrait', 'landscape'];
}

/** Narrow receipt layout (thermal printer). */
function invoicePrintIsThermal($layout_type) {
    $t = trim((string)$layout_type);
    return $t === 'Thermal 80mm' || $t === 'Thermal';
}

/** Normalize layout_type from DB or form (legacy aliases). */
function normalizeInvoicePrintLayoutType($v) {
    $v = trim((string)$v);
    if ($v === 'Thermal') {
        $v = 'Thermal 80mm';
    }
    if (in_array($v, getInvoicePrintLayoutTypeValues(), true)) {
        return $v;
    }
    return 'A4';
}

/** Normalize page orientation. */
function normalizeInvoicePrintPageOrientation($v) {
    $v = strtolower(trim((string)$v));
    return in_array($v, getInvoicePrintPageOrientationValues(), true) ? $v : 'portrait';
}

/**
 * Screen + print CSS for paper size and orientation (invoice .invoice wrapper + @page).
 */
function getInvoicePrintLayoutInlineCss($layout_type, $page_orientation = 'portrait') {
    $layout_type = normalizeInvoicePrintLayoutType($layout_type);
    $page_orientation = normalizeInvoicePrintPageOrientation($page_orientation);
    if (invoicePrintIsThermal($layout_type)) {
        return '.invoice { max-width: 80mm !important; } @media print { @page { size: 80mm auto; margin: 4mm; } }';
    }
    $land = ($page_orientation === 'landscape');
    $map = [
        'A4' => ['w' => '210mm', 'h' => '297mm'],
        'A5' => ['w' => '148mm', 'h' => '210mm'],
        'Letter' => ['w' => '216mm', 'h' => '279mm'],
    ];
    $d = $map[$layout_type] ?? $map['A4'];
    $pw = $land ? $d['h'] : $d['w'];
    $ph = $land ? $d['w'] : $d['h'];
    $pageSize = $pw . ' ' . $ph;
    return '.invoice { max-width: ' . $pw . ' !important; } @media print { @page { size: ' . $pageSize . '; margin: 10mm; } }';
}

/**
 * Available invoice structure templates (different layout/structure, not just color).
 * Used for dropdown and to resolve which template file to include.
 * @return array [ 'template_classic' => 'Template 1 – Classic Table Layout', ... ]
 */
function getInvoicePrintStructureTemplates() {
    return [
        'template_classic'   => 'Template 1 – Classic Table Layout',
        'template_modern'    => 'Template 2 – Modern Compact Layout',
        'template_jewellery'  => 'Template 3 – Detailed Jewellery Layout',
        'template_thermal'    => 'Template 4 – Thermal Minimal Layout',
        'template_premium'    => 'Template 5 – Premium Retail Layout',
        'template_6'          => 'Template 6 – Formal B&W Retail Layout',
    ];
}

/**
 * Get the invoice template key for a document type (which structure file to load).
 * Falls back to default settings if document type has none. Validates against getInvoicePrintStructureTemplates().
 * @param string $document_type sale_invoice, purchase_invoice, sale_order, etc.
 * @return string e.g. template_classic
 */
function getInvoiceTemplateForDocument($document_type) {
    $settings = function_exists('getInvoicePrintSettingsForDocument') ? getInvoicePrintSettingsForDocument($document_type) : [];
    $list = getInvoicePrintStructureTemplates();
    $key = isset($settings['invoice_template']) ? trim((string)$settings['invoice_template']) : 'template_classic';
    return array_key_exists($key, $list) ? $key : 'template_classic';
}

/**
 * Available design templates for invoice print (id, name, preview colors for settings page).
 * @return array[] List of [ 'id' => 'template_1', 'name' => '...', 'header_bg' => '#...', 'accent' => '#...' ]
 */
function getInvoicePrintDesignTemplates() {
    return [
        [ 'id' => 'template_1', 'name' => 'Classic Blue & Gold', 'desc' => 'Dark blue header, gold title bar', 'header_bg' => 'linear-gradient(135deg, #1a365d 0%, #2c5282 100%)', 'accent' => '#d4af37', 'badge_bg' => 'linear-gradient(135deg, #d4af37 0%, #c9a227 100%)', 'table_bg' => 'linear-gradient(135deg, #2c5282 0%, #1a365d 100%)' ],
        [ 'id' => 'template_2', 'name' => 'Green Professional', 'desc' => 'Fresh green, rounded style', 'header_bg' => 'linear-gradient(135deg, #0d5c2e 0%, #1a7a3e 100%)', 'accent' => '#22c55e', 'badge_bg' => 'linear-gradient(135deg, #22c55e 0%, #16a34a 100%)', 'table_bg' => 'linear-gradient(135deg, #16a34a 0%, #0d5c2e 100%)' ],
        [ 'id' => 'template_3', 'name' => 'Elegant Dark & Red', 'desc' => 'Navy header, red accent', 'header_bg' => 'linear-gradient(135deg, #1a1a2e 0%, #16213e 100%)', 'accent' => '#e94560', 'badge_bg' => 'linear-gradient(135deg, #e94560 0%, #c73e54 100%)', 'table_bg' => 'linear-gradient(135deg, #16213e 0%, #1a1a2e 100%)' ],
        [ 'id' => 'template_4', 'name' => 'Minimal Light', 'desc' => 'Soft grey, clean lines', 'header_bg' => 'linear-gradient(135deg, #4a5568 0%, #718096 100%)', 'accent' => '#cbd5e0', 'badge_bg' => 'linear-gradient(135deg, #cbd5e0 0%, #94a3b8 100%)', 'table_bg' => 'linear-gradient(135deg, #64748b 0%, #475569 100%)' ],
        [ 'id' => 'template_5', 'name' => 'Gold Luxury', 'desc' => 'Amber & gold premium', 'header_bg' => 'linear-gradient(135deg, #78350f 0%, #b45309 100%)', 'accent' => '#fcd34d', 'badge_bg' => 'linear-gradient(135deg, #fcd34d 0%, #d4af37 100%)', 'table_bg' => 'linear-gradient(135deg, #b45309 0%, #92400e 100%)' ],
        [ 'id' => 'template_6', 'name' => 'Jewellery B&W Formal', 'desc' => 'Black border, plain grid table, Naveen-style retail invoice', 'header_bg' => 'linear-gradient(180deg, #ffffff 0%, #e8e8e8 100%)', 'accent' => '#000000', 'badge_bg' => '#ffffff', 'table_bg' => 'linear-gradient(180deg, #f5f5f5 0%, #e0e0e0 100%)' ],
    ];
}

/**
 * CSS overrides for a given design template (for print layout).
 * @param string $template_id template_1 .. template_6
 * @return string CSS string targeting .invoice.template_X
 */
function getInvoicePrintTemplateCss($template_id) {
    $templates = [
        'template_1' => [
            'header' => 'linear-gradient(135deg, #1a365d 0%, #2c5282 50%, #1a365d 100%)',
            'badge' => 'linear-gradient(135deg, #d4af37 0%, #c9a227 100%)',
            'badge_color' => '#1a365d',
            'th' => 'linear-gradient(135deg, #2c5282 0%, #1a365d 100%)',
            'highlight' => 'linear-gradient(135deg, #1a365d 0%, #2c5282 100%)',
            'signature' => '#1a365d',
            'gold_rates' => 'linear-gradient(90deg, #d4af37 0%, #f4e4a6 25%, #d4af37 50%, #f4e4a6 75%, #d4af37 100%)',
            'customer_border' => '#2c5282',
            'extra' => '',
        ],
        'template_2' => [
            'header' => 'linear-gradient(135deg, #0d5c2e 0%, #1a7a3e 50%, #0d5c2e 100%)',
            'badge' => 'linear-gradient(135deg, #22c55e 0%, #16a34a 100%)',
            'badge_color' => '#fff',
            'th' => 'linear-gradient(135deg, #16a34a 0%, #0d5c2e 100%)',
            'highlight' => 'linear-gradient(135deg, #0d5c2e 0%, #16a34a 100%)',
            'signature' => '#0d5c2e',
            'gold_rates' => 'linear-gradient(90deg, #22c55e 0%, #86efac 50%, #22c55e 100%)',
            'customer_border' => '#16a34a',
            'extra' => '.invoice.template_2 .inv-header { border-radius: 0 0 16px 16px; } .invoice.template_2 .inv-tax-badge { border-radius: 12px; margin: 0 12px; } .invoice.template_2 .inv-table-wrap { border-radius: 12px; overflow: hidden; } ',
        ],
        'template_3' => [
            'header' => 'linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #1a1a2e 100%)',
            'badge' => 'linear-gradient(135deg, #e94560 0%, #c73e54 100%)',
            'badge_color' => '#fff',
            'th' => 'linear-gradient(135deg, #16213e 0%, #1a1a2e 100%)',
            'highlight' => 'linear-gradient(135deg, #1a1a2e 0%, #e94560 100%)',
            'signature' => '#1a1a2e',
            'gold_rates' => 'linear-gradient(90deg, #1a1a2e 0%, #e94560 50%, #1a1a2e 100%)',
            'customer_border' => '#e94560',
            'extra' => '.invoice.template_3 .inv-tax-badge { border-left: 4px solid #e94560; letter-spacing: 3px; } .invoice.template_3 .inv-table-wrap th { border-bottom: 2px solid #e94560; } ',
        ],
        'template_4' => [
            'header' => 'linear-gradient(135deg, #4a5568 0%, #718096 50%, #4a5568 100%)',
            'badge' => 'linear-gradient(135deg, #cbd5e0 0%, #94a3b8 100%)',
            'badge_color' => '#1a202c',
            'th' => 'linear-gradient(135deg, #64748b 0%, #475569 100%)',
            'highlight' => 'linear-gradient(135deg, #475569 0%, #64748b 100%)',
            'signature' => '#334155',
            'gold_rates' => 'linear-gradient(90deg, #e2e8f0 0%, #cbd5e0 50%, #e2e8f0 100%)',
            'customer_border' => '#94a3b8',
            'extra' => '.invoice.template_4 .inv-header { box-shadow: 0 2px 8px rgba(0,0,0,0.1); } .invoice.template_4 .inv-tax-badge { font-weight: 600; letter-spacing: 1px; } .invoice.template_4 .inv-table-wrap { border: 1px solid #e2e8f0; } .invoice.template_4 .gold-rates span { color: #475569; } ',
        ],
        'template_5' => [
            'header' => 'linear-gradient(135deg, #78350f 0%, #b45309 50%, #78350f 100%)',
            'badge' => 'linear-gradient(135deg, #fcd34d 0%, #d4af37 100%)',
            'badge_color' => '#78350f',
            'th' => 'linear-gradient(135deg, #b45309 0%, #92400e 100%)',
            'highlight' => 'linear-gradient(135deg, #92400e 0%, #b45309 100%)',
            'signature' => '#78350f',
            'gold_rates' => 'linear-gradient(90deg, #fcd34d 0%, #fef3c7 25%, #fcd34d 50%, #fef3c7 75%, #fcd34d 100%)',
            'customer_border' => '#b45309',
            'extra' => '.invoice.template_5 .inv-tax-badge { box-shadow: 0 2px 12px rgba(180,83,9,0.3); } .invoice.template_5 .inv-table-wrap th { color: #fef3c7; } .invoice.template_5 .gold-rates { border: 2px solid #d4af37; } .invoice.template_5 .inv-customer { border-left-color: #b45309 !important; } ',
        ],
        'template_6' => [
            'header' => '#ffffff',
            'badge' => '#ffffff',
            'badge_color' => '#000000',
            'th' => '#f2f2f2',
            'highlight' => '#000000',
            'signature' => '#000000',
            'gold_rates' => '#ffffff',
            'customer_border' => '#000000',
            'extra' => '.invoice.inv-naveen { font-family: Arial, sans-serif !important; font-size: 13px !important; color: #000 !important; border-radius: 0 !important; box-shadow: none !important; max-width: 100% !important; margin: 0 auto !important; background: transparent !important; padding: 0 !important; border: none !important; } '
                . '.invoice.inv-naveen .bill-container { width: 100%; max-width: 1000px; margin: auto; border: 1px solid #000; padding: 10px; box-sizing: border-box; background: #fff; } '
                . '.invoice.inv-naveen .header, .invoice.inv-naveen .summary, .invoice.inv-naveen .footer { width: 100%; margin-bottom: 10px; } '
                . '.invoice.inv-naveen .box { width: 100%; margin-bottom: 10px; border: 1px solid #000; padding: 8px; box-sizing: border-box; } '
                . '.invoice.inv-naveen .box .t6-rate-in-box { width: 100%; clear: both; } '
                . '.invoice.inv-naveen .flex { display: flex; justify-content: space-between; align-items: flex-start; } '
                . '.invoice.inv-naveen .summary table { width: 100%; border-collapse: collapse; margin-top: 5px; } '
                . '.invoice.inv-naveen .summary table, .invoice.inv-naveen .summary th, .invoice.inv-naveen .summary td { border: 1px solid #000; } '
                . '.invoice.inv-naveen .summary th, .invoice.inv-naveen .summary td { padding: 5px; text-align: left; vertical-align: top; } '
                . '.invoice.inv-naveen .t6-items-table { width: 100%; border-collapse: collapse; margin-top: 5px; border: 1px solid #000; } '
                . '.invoice.inv-naveen .t6-items-table th, .invoice.inv-naveen .t6-items-table td { border-top: 1px solid #000; border-bottom: 1px solid #000; border-left: none; border-right: none; padding: 5px; text-align: left; vertical-align: top; } '
                . '.invoice.inv-naveen .t6-items-table thead th:first-child, .invoice.inv-naveen .t6-items-table tbody td:first-child { border-left: 1px solid #000; } '
                . '.invoice.inv-naveen .t6-items-table thead th:last-child { border-right: 1px solid #000; } '
                . '.invoice.inv-naveen .t6-items-table tbody tr:not(.t6-pad-row) td:last-child { border-right: 1px solid #000; } '
                . '.invoice.inv-naveen .t6-items-table thead th { color: #000 !important; background: #f2f2f2 !important; background-image: none !important; text-transform: none !important; letter-spacing: normal !important; font-weight: 700 !important; font-size: 12px !important; } '
                . '.invoice.inv-naveen .t6-items-table tbody tr.t6-last-data-row td { border-bottom: none !important; } '
                . '.invoice.inv-naveen .t6-items-table tbody tr.t6-pad-row td { border: none !important; background: #fff !important; height: 18px; } '
                . '.invoice.inv-naveen .t6-items-table tbody tr.t6-pad-row td:first-child { border-left: 1px solid #000 !important; } '
                . '.invoice.inv-naveen .t6-items-table tbody tr.t6-pad-row td:last-child { border-right: 1px solid #000 !important; } '
                . '.invoice.inv-naveen .t6-items-table tbody tr.t6-pad-row:last-child td { border-bottom: 1px solid #000 !important; } '
                . '.invoice.inv-naveen.t6-show-item-vertical-lines .t6-items-table th, .invoice.inv-naveen.t6-show-item-vertical-lines .t6-items-table td { border: 1px solid #000 !important; } '
                . '.invoice.inv-naveen .right { text-align: right; } '
                . '.invoice.inv-naveen .bold { font-weight: bold; } '
                . '.invoice.inv-naveen .small { font-size: 12px; } '
                . '.invoice.inv-naveen .terms { font-size: 12px; line-height: 18px; color: #000 !important; display: block !important; visibility: visible !important; margin-top: 10px; margin-bottom: 10px; } '
                . '@media print { .invoice.inv-naveen .bill-container { box-shadow: none !important; } } ',
        ],
    ];
    $t = $templates[$template_id] ?? $templates['template_1'];
    $class = preg_replace('/[^a-z0-9_]/', '_', $template_id);
    $css = ".invoice.{$class} .inv-header { background: {$t['header']} !important; } " .
        ".invoice.{$class} .inv-tax-badge { background: {$t['badge']} !important; color: {$t['badge_color']} !important; } " .
        ".invoice.{$class} .inv-table-wrap th { background: {$t['th']} !important; } " .
        ".invoice.{$class} .inv-summary .summary-row.highlight { background: {$t['highlight']} !important; } " .
        ".invoice.{$class} .inv-signature-line { border-top-color: {$t['signature']} !important; } " .
        ".invoice.{$class} .inv-trn { background: rgba(255,255,255,0.15); } " .
        ".invoice.{$class} .gold-rates { background: {$t['gold_rates']} !important; } " .
        ".invoice.{$class} .inv-customer { border-left-color: {$t['customer_border']} !important; } " .
        ($t['extra'] ?? '');
    return $css;
}

/**
 * Return allowed Sale Invoice print languages: always ['en'], plus one optional (hi, mr, ar) if set.
 * @return array List of language codes, e.g. ['en'] or ['en', 'hi']
 */
function getInvoicePrintAllowedLanguages($document_type = null) {
    $settings = $document_type && function_exists('getInvoicePrintSettingsForDocument')
        ? getInvoicePrintSettingsForDocument($document_type)
        : (function_exists('getInvoicePrintSettings') ? getInvoicePrintSettings() : getInvoicePrintSettingsDefaults());
    $allowed = ['en'];
    $sec = isset($settings['invoice_secondary_language']) ? trim((string)$settings['invoice_secondary_language']) : '';
    if (in_array($sec, ['hi', 'mr', 'ar'], true)) {
        $allowed[] = $sec;
    }
    return $allowed;
}

/**
 * Save a single invoice print setting (insert or update by setting_key, optionally by setting_type).
 * @param string $setting_key
 * @param mixed  $setting_value
 * @param string $setting_type  default, sale_invoice, purchase_invoice, sale_order, purchase_order, purchase_quotation, sale_quotation, sale_return, purchase_return, sale_fixing_direct, payment_voucher, receipt_voucher, advance_payment
 */
function saveInvoicePrintSetting($setting_key, $setting_value, $setting_type = 'default') {
    global $conn;
    $table = 'tbl_invoice_print_settings';
    auragold_ensure_branch_id_on_settings_tables($conn);
    $bid = auragold_settings_branch_id();
    $has_branch = auragold_tbl_has_column($conn, $table, 'branch_id');
    $branchSql = ($has_branch && $bid > 0) ? (' AND branch_id = ' . (int) $bid) : '';
    $branchInsert = ($has_branch && $bid > 0) ? (int) $bid : null;
    $k = mysqli_real_escape_string($conn, (string)$setting_key);
    $v = is_array($setting_value) || is_object($setting_value) ? json_encode($setting_value) : (string)$setting_value;
    $v = mysqli_real_escape_string($conn, $v);
    $st = mysqli_real_escape_string($conn, $setting_type);
    $col = @mysqli_query($conn, "SHOW COLUMNS FROM $table LIKE 'setting_type'");
    $has_type = $col && mysqli_num_rows($col) > 0;
    if ($col) mysqli_free_result($col);
    if ($has_type) {
        $exists = getRecord("SELECT id FROM $table WHERE setting_type = '$st' AND setting_key = '$k' $branchSql LIMIT 1");
        if ($exists) {
            return mysqli_query($conn, "UPDATE $table SET setting_value = '$v', updated_at = NOW() WHERE setting_type = '$st' AND setting_key = '$k' $branchSql");
        }
        if ($branchInsert !== null) {
            return mysqli_query($conn, "INSERT INTO $table (branch_id, setting_type, setting_key, setting_value, updated_at) VALUES ($branchInsert, '$st', '$k', '$v', NOW())");
        }
        return mysqli_query($conn, "INSERT INTO $table (setting_type, setting_key, setting_value, updated_at) VALUES ('$st', '$k', '$v', NOW())");
    }
    $exists = getRecord("SELECT id FROM $table WHERE setting_key = '$k' $branchSql LIMIT 1");
    if ($exists) {
        return mysqli_query($conn, "UPDATE $table SET setting_value = '$v', updated_at = NOW() WHERE setting_key = '$k' $branchSql");
    }
    if ($branchInsert !== null) {
        return mysqli_query($conn, "INSERT INTO $table (branch_id, setting_key, setting_value, updated_at) VALUES ($branchInsert, '$k', '$v', NOW())");
    }
    return mysqli_query($conn, "INSERT INTO $table (setting_key, setting_value, updated_at) VALUES ('$k', '$v', NOW())");
}

/**
 * Fetch row data for a barcode for use on the barcode print label.
 * Tries tbl_stock_journal first, then tbl_product_characteristics.
 * Returns a flat array with keys matching toolbox field names (BarcodeNo, ActualPurity, product_name, etc.).
 *
 * @param string $barcode
 * @return array  Keys like BarcodeNo, ActualPurity, product_name, NetAmount, Rate, GrossWt, NetWt, FinalWt, PureWt, etc.
 */
function getBarcodePrintData($barcode) {
    global $conn;
    $barcode = trim((string)$barcode);
    if ($barcode === '') return [];
    $esc = mysqli_real_escape_string($conn, $barcode);
    $row = getRecord("
        SELECT sj.*, p.name AS product_name, p.article, m.display_name AS metal_name,
               pc.opening_purity AS pc_purity, pc.rate AS pc_rate
        FROM tbl_stock_journal sj
        LEFT JOIN tbl_products p ON sj.product_id = p.id
        LEFT JOIN tbl_metal m ON sj.metal_id = m.id
        LEFT JOIN tbl_product_characteristics pc ON sj.product_characteristic_id = pc.id
        WHERE sj.barcode = '$esc' AND sj.status = 'active'
        ORDER BY sj.id DESC LIMIT 1
    ");
    if (!$row) {
        $row = getRecord("
            SELECT pc.*, p.name AS product_name, p.article, m.display_name AS metal_name
            FROM tbl_product_characteristics pc
            LEFT JOIN tbl_products p ON pc.product_id = p.id
            LEFT JOIN tbl_metal m ON pc.metal_id = m.id
            WHERE pc.barcode = '$esc' AND pc.status = 1
            ORDER BY pc.id DESC LIMIT 1
        ");
        if ($row) {
            $row['barcode'] = $row['barcode'] ?? $barcode;
            $row['purity'] = $row['opening_purity'] ?? $row['purity'] ?? 0;
            $row['rate'] = $row['rate'] ?? 0;
            $row['amount'] = isset($row['value']) ? $row['value'] : ($row['amount'] ?? 0);
            $row['net_amount'] = $row['net_amount'] ?? $row['amount'] ?? 0;
        }
    }
    if (!$row) {
        $row = getRecord("
            SELECT s.*, p.name AS product_name, p.article, m.display_name AS metal_name
            FROM tbl_stock s
            LEFT JOIN tbl_products p ON s.product_id = p.id
            LEFT JOIN tbl_metal m ON s.metal_id = m.id
            WHERE s.barcode = '$esc' AND s.status = 1
            ORDER BY s.id DESC LIMIT 1
        ");
        if ($row) {
            $row['barcode'] = $row['barcode'] ?? $barcode;
            $row['purity'] = $row['opening_purity'] ?? 0;
            $row['final_weight'] = $row['final_weight'] ?? $row['current_weight'] ?? $row['opening_weight'] ?? 0;
            $row['rate'] = $row['rate'] ?? 0;
            $row['amount'] = $row['value'] ?? 0;
            $row['net_amount'] = $row['value'] ?? 0;
        }
    }
    if (!$row) return ['BarcodeNo' => $barcode];
    $r = $row;
    $purity_val = null;
    if (isset($r['purity']) && $r['purity'] !== '' && $r['purity'] !== null) $purity_val = $r['purity'];
    elseif (isset($r['pc_purity']) && $r['pc_purity'] !== '' && $r['pc_purity'] !== null) $purity_val = $r['pc_purity'];
    elseif (isset($r['opening_purity']) && $r['opening_purity'] !== '' && $r['opening_purity'] !== null) $purity_val = $r['opening_purity'];
    $purity_display = $purity_val !== null ? (is_numeric($purity_val) ? round((float)$purity_val, 2) : $purity_val) : '';
    $gross_wt = isset($r['gross_weight']) ? (float)$r['gross_weight'] : (isset($r['gross_wt']) ? (float)$r['gross_wt'] : (isset($r['opening_weight']) ? (float)$r['opening_weight'] : ''));
    $net_wt   = isset($r['net_weight']) ? (float)$r['net_weight'] : (isset($r['net_wt']) ? (float)$r['net_wt'] : '');
    $final_wt = isset($r['final_weight']) ? (float)$r['final_weight'] : (isset($r['final_wt']) ? (float)$r['final_wt'] : (isset($r['current_weight']) ? (float)$r['current_weight'] : (isset($r['opening_weight']) ? (float)$r['opening_weight'] : '')));
    $pure_wt  = isset($r['pure_weight']) ? (float)$r['pure_weight'] : (isset($r['pure_wt']) ? (float)$r['pure_wt'] : '');
    $purity_wt = isset($r['purity_weight']) ? (float)$r['purity_weight'] : (isset($r['purity_wt']) ? (float)$r['purity_wt'] : (isset($r['pure_weight']) ? (float)$r['pure_weight'] : (isset($r['pure_wt']) ? (float)$r['pure_wt'] : '')));
    $less_wt   = isset($r['less_weight']) ? (float)$r['less_weight'] : (isset($r['less_wt']) ? (float)$r['less_wt'] : '');
    $out = [
        'BarcodeNo' => $r['barcode'] ?? $barcode,
        'ActualPurity' => $purity_display,
        'Purity' => $purity_display,
        'product_name' => $r['product_name'] ?? '',
        'ProductName' => $r['product_name'] ?? '',
        'NetAmount' => isset($r['net_amount']) ? number_format((float)$r['net_amount'], 2) : (isset($r['net_amt_with_tax']) ? number_format((float)$r['net_amt_with_tax'], 2) : ''),
        'Amount' => isset($r['amount']) ? number_format((float)$r['amount'], 2) : '',
        'Rate' => isset($r['rate']) ? number_format((float)$r['rate'], 2) : '',
        'GrossWt' => $gross_wt !== '' ? $gross_wt : '',
        'NetWt' => $net_wt !== '' ? $net_wt : '',
        'FinalWt' => $final_wt !== '' ? $final_wt : '',
        'PureWt' => $pure_wt !== '' ? $pure_wt : '',
        'PurityWt' => $purity_wt !== '' ? $purity_wt : '',
        'LessWt' => $less_wt !== '' ? $less_wt : '',
        'metal_name' => $r['metal_name'] ?? '',
        'MetalName' => $r['metal_name'] ?? '',
        'MakingAmount' => isset($r['making_amount']) ? number_format((float)$r['making_amount'], 2) : '',
        'DesignNo' => $r['design_no'] ?? $r['code'] ?? '',
        'Comment' => $r['comment'] ?? '',
        'GroupName' => $r['group_name'] ?? '',
    ];
    foreach ($row as $k => $v) {
        if (!array_key_exists($k, $out) && $v !== null && $v !== '') $out[$k] = $v;
    }
    return $out;
}

/**
 * Normalize design_layout coordinates: Set Software stores mm; legacy values may be canvas px (large vs label).
 *
 * @param float|string $raw
 */
function auragold_barcode_design_coord_to_mm($raw, float $label_limit_mm, float $px_to_mm): float {
    $v = (float) $raw;
    if ($v < 0) {
        return 0.0;
    }
    if ($v > $label_limit_mm * 1.25 && $v > 25.0) {
        return round($v * $px_to_mm, 2);
    }
    return round($v, 2);
}

/**
 * Render one barcode label from design_layout JSON. Same logic for preview and print.
 * design_layout: array of items, each with type (barcode_image|qr_image|text), left/top in mm,
 * and for barcode_image / qr_image: width/height in mm; for text: field, font, font_size, prefix, suffix.
 * Linear layout: every item uses saved left/top (px→mm via auragold_barcode_design_coord_to_mm). No auto-placed barcode or footer text.
 *
 * @param array $productData  Keys: barcode, BarcodeNo, ActualPurity, product_name, price, etc.
 * @param array $settings    Keys: label_width_mm, label_height_mm, design_layout (array), font_size (int)
 * @return string  HTML for one label inner (barcode-label-inner content)
 */
function renderBarcodeLayout($productData, $settings) {
    $label_width_mm  = (float)($settings['label_width_mm'] ?? 100);
    $label_height_mm = (float)($settings['label_height_mm'] ?? 50);
    $font_size       = (int)($settings['font_size'] ?? 12);
    $px_to_mm        = isset($settings['px_to_mm']) ? (float) $settings['px_to_mm'] : 0.264583;
    $barcode1_left_mm = array_key_exists('barcode1_left_mm', $settings) ? (float) $settings['barcode1_left_mm'] : null;
    $barcode1_top_mm  = array_key_exists('barcode1_top_mm', $settings) ? (float) $settings['barcode1_top_mm'] : null;
    $render_as       = (isset($settings['render_code_as']) && $settings['render_code_as'] === 'qr') ? 'qr' : 'barcode';
    $layout          = $settings['design_layout'] ?? [];
    if (!is_array($layout)) {
        $layout = @json_decode($layout, true);
        if (!is_array($layout)) {
            $layout = [];
        }
    }
    $barcode = isset($productData['barcode']) ? $productData['barcode'] : '';
    $html    = '';
    $design_left_inset_mm = isset($settings['design_left_inset_mm']) ? max(0.0, (float) $settings['design_left_inset_mm']) : 0.0;

    foreach ($layout as $el) {
        if (!is_array($el)) {
            continue;
        }
        $type = isset($el['type']) ? trim((string) $el['type']) : '';
        if ($type !== '' && strcasecmp($type, 'qr_image') === 0) {
            $type = 'qr_image';
        }
        if ($type === '' && isset($el['field']) && trim((string) $el['field']) !== '') {
            $type = 'text';
        }
        if ($type === '') {
            continue;
        }

        if ($type === 'barcode_image' || $type === 'qr_image') {
            $use_qr_graphics = ($type === 'qr_image' || $render_as === 'qr');
            $left = auragold_barcode_design_coord_to_mm($el['left'] ?? 0, $label_width_mm, $px_to_mm);
            $top  = auragold_barcode_design_coord_to_mm($el['top'] ?? 0, $label_height_mm, $px_to_mm);
            if ($use_qr_graphics) {
                if ($left < 0 || $left >= $label_width_mm) {
                    $left = 0;
                }
                if ($top < 0 || $top >= $label_height_mm) {
                    $top = 0;
                }
                $left_mm = $barcode1_left_mm !== null ? auragold_barcode_design_coord_to_mm($barcode1_left_mm, $label_width_mm, $px_to_mm) : $left;
                $top_mm  = $barcode1_top_mm !== null ? auragold_barcode_design_coord_to_mm($barcode1_top_mm, $label_height_mm, $px_to_mm) : $top;
            } else {
                $left_mm = $left + $design_left_inset_mm;
                $top_mm  = $top;
            }
            $w = isset($el['width']) ? (float) $el['width'] : ($use_qr_graphics ? min(35, $label_width_mm * 0.35) : 30.0);
            $h = isset($el['height']) ? (float) $el['height'] : ($use_qr_graphics ? min(12, $label_height_mm * 0.6) : 10.0);
            if ($w <= 0) {
                $w = 10;
            }
            if ($h <= 0) {
                $h = 5;
            }
            if ($use_qr_graphics) {
                $side_mm = min($w, $h);
                $w = $side_mm;
                $height_mm = round($side_mm, 2);
            } else {
                $height_mm = round($h, 2);
            }
            if ($use_qr_graphics) {
                $left_mm += $design_left_inset_mm;
                if ($left_mm + $w > $label_width_mm) {
                    $left_mm = max(0.0, $label_width_mm - $w);
                }
            }
            if ($use_qr_graphics) {
                $code_box_style = 'position:absolute;left:' . round($left_mm, 2) . 'mm;top:' . $top_mm . 'mm;width:' . round($w, 2) . 'mm;height:' . $height_mm . 'mm;margin:0;padding:0;overflow:hidden;box-sizing:border-box;z-index:2;';
                $html .= '<div class="barcode-svg-wrap barcode-svg-wrap--qr" style="' . $code_box_style . '">';
                $html .= '<div class="qr-print-host" data-barcode="' . htmlspecialchars($barcode) . '" style="width:100%;height:100%;"></div>';
                $html .= '</div>';
            } else {
                $code_box_style = 'position:absolute;left:' . round($left_mm, 2) . 'mm;top:' . round($top_mm, 2) . 'mm;width:' . round($w, 2) . 'mm;height:' . round($height_mm, 2) . 'mm;margin:0;padding:0;overflow:hidden;box-sizing:border-box;z-index:2;';
                $html .= '<div class="barcode-print-wrap" style="' . $code_box_style . '">';
                $svgClass = 'barcode-svg';
                if (!empty($settings['barcode_svg_class'])) {
                    $svgClass .= ' ' . trim((string) $settings['barcode_svg_class']);
                }
                $svgIdAttr = !empty($settings['barcode_svg_id'])
                    ? (' id="' . htmlspecialchars((string) $settings['barcode_svg_id'], ENT_QUOTES, 'UTF-8') . '"')
                    : '';
                $html .= '<svg class="' . htmlspecialchars($svgClass, ENT_QUOTES, 'UTF-8') . '" data-barcode="' . htmlspecialchars($barcode) . '"' . $svgIdAttr . '></svg>';
                $html .= '</div>';
            }
            continue;
        }

        if ($type === 'text') {
            $field = isset($el['field']) ? trim((string) $el['field']) : '';
            if ($field === '') {
                continue;
            }
            $left_mm = auragold_barcode_design_coord_to_mm($el['left'] ?? 0, $label_width_mm, $px_to_mm) + $design_left_inset_mm;
            $top_mm  = auragold_barcode_design_coord_to_mm($el['top'] ?? 0, $label_height_mm, $px_to_mm);
            $fs_el   = isset($el['font_size']) ? (int) $el['font_size'] : $font_size;
            $style_pos = sprintf('left:%smm;top:%smm;font-size:%dpx;', round($left_mm, 2), round($top_mm, 2), (int) $fs_el);

            $val = null;
            if (strcasecmp($field, 'ProductName') === 0 || strcasecmp($field, 'product_name') === 0) {
                $val = $productData['ProductName'] ?? $productData['product_name'] ?? '';
            } elseif (strcasecmp($field, 'Barcode') === 0 || strcasecmp($field, 'BarcodeNo') === 0) {
                $val = $productData['BarcodeNo'] ?? $productData['Barcode'] ?? $barcode;
            } else {
                $val = $productData[$field] ?? '';
            }
            if ($val === null) {
                $val = '';
            }
            if ((string) $val === '' && strcasecmp($field, 'MetalRate') === 0) {
                $val = $productData['MetalRate'] ?? $productData['Rate'] ?? $productData['metal_rate'] ?? '';
            }
            if ((string) $val === '' && $field !== '') {
                if (in_array($field, ['GrossWt', 'Gross Wt.'], true) && (isset($productData['gross_weight']) || isset($productData['gross_wt']))) {
                    $val = $productData['gross_weight'] ?? $productData['gross_wt'] ?? '';
                } elseif (in_array($field, ['PurityWt', 'Purity Wt.', 'PureWt'], true) && (isset($productData['purity_weight']) || isset($productData['pure_weight']) || isset($productData['purity_wt']) || isset($productData['pure_wt']))) {
                    $val = $productData['purity_weight'] ?? $productData['pure_weight'] ?? $productData['purity_wt'] ?? $productData['pure_wt'] ?? '';
                } elseif (in_array($field, ['NetWt', 'Net Wt.'], true) && (isset($productData['net_weight']) || isset($productData['net_wt']))) {
                    $val = $productData['net_weight'] ?? $productData['net_wt'] ?? '';
                }
            }
            $numDecimals = isset($el['number_of_decimal']) ? (int) $el['number_of_decimal'] : (is_numeric($val) ? 3 : null);
            if ($numDecimals !== null && is_numeric($val)) {
                $val = number_format((float) $val, $numDecimals, '.', '');
            }
            if ((string) $val === '') {
                $val = '';
            }
            $prefix = isset($el['prefix']) ? trim((string) $el['prefix']) : '';
            $suffix = isset($el['suffix']) ? trim((string) $el['suffix']) : '';
            $isFieldNamePrefix = (strcasecmp($prefix, $field) === 0);
            $isFieldNameSuffix = (strcasecmp($suffix, $field) === 0);
            $display = (string) $val;
            if ($prefix !== '' && !$isFieldNamePrefix) {
                $display = $prefix . ' ' . $display;
            }
            if ($suffix !== '' && !$isFieldNameSuffix) {
                $display = $display . ' ' . $suffix;
            }
            $display = trim($display);
            if ($display === '') {
                $display = (string) $val;
            }
            $font = isset($el['font']) ? $el['font'] : 'Arial';
            $pad_top = isset($el['pad_top']) ? max(0, min(200, (int) $el['pad_top'])) : 0;
            $pad_right = isset($el['pad_right']) ? max(0, min(200, (int) $el['pad_right'])) : 0;
            $pad_bottom = isset($el['pad_bottom']) ? max(0, min(200, (int) $el['pad_bottom'])) : 0;
            $pad_left = isset($el['pad_left']) ? max(0, min(200, (int) $el['pad_left'])) : 0;
            $pad_css = sprintf('padding:%dpx %dpx %dpx %dpx !important;', $pad_top, $pad_right, $pad_bottom, $pad_left);
            $html .= '<div class="design-field" style="position:absolute;' . $style_pos . 'font-family:' . htmlspecialchars($font) . ';margin:0;box-sizing:border-box;' . $pad_css . 'z-index:0;line-height:1;color:#1e293b;">';
            $html .= htmlspecialchars($display);
            $html .= '</div>';
        }
    }

    return $html;
}

/** Recovered 82×38 two-box sticker helpers (from conversation transcript). */

/** True when label preset is 82 mm × 38 mm sticker with two barcode boxes. */
function auragold_is_82x38_2box_sticker($preset): bool {
    $p = str_replace(' ', '', strtolower(trim((string) $preset)));
    return ($p === '82x38_2box' || $p === '82x38-2box');
}

/** Fixed inner print box for 82×38 two-box sticker (2 cm × 2.5 cm). */
function auragold_82x38_box_size_mm(): array {
    return ['width' => 20.0, 'height' => 25.0];
}

/** Default geometry for 82×38 mm two-box sticker (mm). */
function auragold_82x38_2box_defaults(): array {
    $box = auragold_82x38_box_size_mm();
    return [
        'sticker_w'                => 82.0,
        'sticker_h'                => 38.0,
        'box_width_mm'             => (float) $box['width'],
        'box_height_mm'            => (float) $box['height'],
        'box1_left_mm'             => 2.0,
        'box1_top_mm'              => 13.0,
        'box2_left_mm'             => 58.0,
        'box2_top_mm'              => 2.0,
        'barcode_width_mm'         => 18.0,
        'barcode_height_mm'        => 7.0,
        'box1_barcode_left_mm'     => 2.0,
        'box1_barcode_top_mm'      => 3.0,
        'box2_barcode_left_mm'     => 2.0,
        'box2_barcode_top_mm'      => 3.0,
        'barcode_left_mm'          => 2.0,
        'barcode_top_mm'           => 3.0,
        'barcode_no_font_size'     => 7.0,
        'barcode_no_margin_top_mm' => 1.0,
    ];
}

/** Resolved box layout for 82×38 two-box sticker from saved design_layout JSON. */
function auragold_82x38_2box_layout(array $snapshot = []): array {
    $def = auragold_82x38_2box_defaults();
    $boxFixed = auragold_82x38_box_size_mm();
    $boxW = (float) $boxFixed['width'];
    $boxH = (float) $boxFixed['height'];
    $stickerW = (float) $def['sticker_w'];
    $stickerH = (float) $def['sticker_h'];
    $b1L = (float) ($snapshot['box1_left_mm'] ?? ($snapshot['box1']['left_mm'] ?? $def['box1_left_mm']));
    $b1T = (float) ($snapshot['box1_top_mm'] ?? ($snapshot['box1']['top_mm'] ?? $def['box1_top_mm']));
    $b2T = (float) ($snapshot['box2_top_mm'] ?? ($snapshot['box2']['top_mm'] ?? $def['box2_top_mm']));
    if (isset($snapshot['box2_left_mm']) && $snapshot['box2_left_mm'] !== '') {
        $b2L = (float) $snapshot['box2_left_mm'];
    } elseif (isset($snapshot['box2']['left_mm']) && $snapshot['box2']['left_mm'] !== '') {
        $b2L = (float) $snapshot['box2']['left_mm'];
    } elseif (isset($snapshot['box2_right_mm']) && $snapshot['box2_right_mm'] !== '') {
        $b2L = $stickerW - (float) $snapshot['box2_right_mm'] - $boxW;
    } else {
        $b2L = (float) $def['box2_left_mm'];
    }
    $b1L = max(0.0, min($stickerW - $boxW, $b1L));
    $b1T = max(0.0, min($stickerH - $boxH, $b1T));
    $b2L = max(0.0, min($stickerW - $boxW, $b2L));
    $b2T = max(0.0, min($stickerH - $boxH, $b2T));
    $sharedBarW = (float) ($snapshot['barcode_width_mm'] ?? $snapshot['box_barcode_width_mm'] ?? $def['barcode_width_mm']);
    $sharedBarH = (float) ($snapshot['barcode_height_mm'] ?? $snapshot['box_barcode_height_mm'] ?? $def['barcode_height_mm']);
    $box1BarW = (float) ($snapshot['box1_barcode_width_mm'] ?? $sharedBarW);
    $box1BarH = (float) ($snapshot['box1_barcode_height_mm'] ?? $sharedBarH);
    $box2BarW = (float) ($snapshot['box2_barcode_width_mm'] ?? $sharedBarW);
    $box2BarH = (float) ($snapshot['box2_barcode_height_mm'] ?? $sharedBarH);
    $box1BarLeft = (float) ($snapshot['box1_barcode_left_mm'] ?? ($snapshot['barcode1']['left_mm'] ?? ($snapshot['barcode_left_mm'] ?? $def['box1_barcode_left_mm'])));
    $box1BarTop = (float) ($snapshot['box1_barcode_top_mm'] ?? ($snapshot['barcode1']['top_mm'] ?? ($snapshot['barcode_top_mm'] ?? $def['box1_barcode_top_mm'])));
    $box2BarLeft = (float) ($snapshot['box2_barcode_left_mm'] ?? ($snapshot['barcode2']['left_mm'] ?? ($snapshot['barcode_left_mm'] ?? $def['box2_barcode_left_mm'])));
    $box2BarTop = (float) ($snapshot['box2_barcode_top_mm'] ?? ($snapshot['barcode2']['top_mm'] ?? ($snapshot['barcode_top_mm'] ?? $def['box2_barcode_top_mm'])));
    $numFont = (float) ($snapshot['barcode_no_font_size'] ?? $snapshot['barcode_number_font_pt'] ?? $def['barcode_no_font_size']);
    $numMargin = (float) ($snapshot['barcode_no_margin_top_mm'] ?? $snapshot['barcode_number_gap_mm'] ?? $def['barcode_no_margin_top_mm']);
    $box1BarW = max(4.0, min($boxW, $box1BarW));
    $box1BarH = max(3.0, min($boxH - 2.0, $box1BarH));
    $box2BarW = max(4.0, min($boxW, $box2BarW));
    $box2BarH = max(3.0, min($boxH - 2.0, $box2BarH));
    $box1BarLeft = max(0.0, min($boxW - $box1BarW, $box1BarLeft));
    $box1BarTop = max(0.0, min($boxH - $box1BarH - 3.0, $box1BarTop));
    $box2BarLeft = max(0.0, min($boxW - $box2BarW, $box2BarLeft));
    $box2BarTop = max(0.0, min($boxH - $box2BarH - 3.0, $box2BarTop));
    $numFont = max(5.0, min(24.0, $numFont));
    $numMargin = max(0.0, min(10.0, $numMargin));
    return [
        'sticker_w'                  => $stickerW,
        'sticker_h'                  => $stickerH,
        'box_width_mm'               => round($boxW, 2),
        'box_height_mm'              => round($boxH, 2),
        'box1'                       => ['left' => round($b1L, 2), 'top' => round($b1T, 2)],
        'box2'                       => ['left' => round($b2L, 2), 'top' => round($b2T, 2)],
        'box2_left_mm'               => round($b2L, 2),
        'box1_barcode_width_mm'      => round($box1BarW, 2),
        'box1_barcode_height_mm'     => round($box1BarH, 2),
        'box2_barcode_width_mm'      => round($box2BarW, 2),
        'box2_barcode_height_mm'     => round($box2BarH, 2),
        'box1_barcode_left_mm'       => round($box1BarLeft, 2),
        'box1_barcode_top_mm'        => round($box1BarTop, 2),
        'box2_barcode_left_mm'       => round($box2BarLeft, 2),
        'box2_barcode_top_mm'        => round($box2BarTop, 2),
        'barcode_width_mm'           => round($box1BarW, 2),
        'barcode_height_mm'          => round($box1BarH, 2),
        'barcode_left_mm'            => round($box1BarLeft, 2),
        'barcode_top_mm'             => round($box1BarTop, 2),
        'barcode_no_font_size'       => round($numFont, 2),
        'barcode_no_margin_top_mm'   => round($numMargin, 2),
    ];
}

function auragold_82x38_sticker_css_vars(array $layout): string {
    return '--box-width:' . $layout['box_width_mm'] . 'mm;'
        . '--box-height:' . $layout['box_height_mm'] . 'mm;'
        . '--barcode-width:' . $layout['barcode_width_mm'] . 'mm;'
        . '--barcode-height:' . $layout['barcode_height_mm'] . 'mm;'
        . '--barcode-left:' . $layout['barcode_left_mm'] . 'mm;'
        . '--barcode-top:' . $layout['barcode_top_mm'] . 'mm;'
        . '--number-font:' . $layout['barcode_no_font_size'] . 'px;'
        . '--number-margin-top:' . $layout['barcode_no_margin_top_mm'] . 'mm;';
}

function auragold_82x38_box_barcode_item(array $snapshot, int $boxNum): ?array {
    $layout = auragold_82x38_2box_layout($snapshot);
    $key = 'barcode' . (int) $boxNum;
    if (isset($snapshot[$key]) && is_array($snapshot[$key]) && isset($snapshot[$key]['left_mm'])) {
        return [
            'type'   => 'barcode_image',
            'left'   => (float) $snapshot[$key]['left_mm'],
            'top'    => (float) ($snapshot[$key]['top_mm'] ?? 0),
            'width'  => (float) ($snapshot[$key]['width_mm'] ?? ($boxNum === 2 ? $layout['box2_barcode_width_mm'] : $layout['box1_barcode_width_mm'])),
            'height' => (float) ($snapshot[$key]['height_mm'] ?? ($boxNum === 2 ? $layout['box2_barcode_height_mm'] : $layout['box1_barcode_height_mm'])),
        ];
    }
    return null;
}

function auragold_82x38_box_design_items(array $snapshot, int $boxNum): array {
    if ($boxNum === 2) {
        if (isset($snapshot['box2']['items']) && is_array($snapshot['box2']['items'])) {
            return $snapshot['box2']['items'];
        }
        if (isset($snapshot['items2']) && is_array($snapshot['items2'])) {
            return $snapshot['items2'];
        }
        if (isset($snapshot['fields2']) && is_array($snapshot['fields2'])) {
            return $snapshot['fields2'];
        }
        return [];
    }
    if (isset($snapshot['box1']['items']) && is_array($snapshot['box1']['items'])) {
        return $snapshot['box1']['items'];
    }
    if (isset($snapshot['items']) && is_array($snapshot['items'])) {
        return $snapshot['items'];
    }
    if (isset($snapshot['fields']) && is_array($snapshot['fields'])) {
        return $snapshot['fields'];
    }
    return [];
}

function auragold_82x38_default_box_design_items(array $snapshot, int $boxNum = 1): array {
    $layout = auragold_82x38_2box_layout($snapshot);
    if ($boxNum === 2) {
        $barLeft = (float) ($layout['box2_barcode_left_mm'] ?? $layout['barcode_left_mm']);
        $barTop = (float) ($layout['box2_barcode_top_mm'] ?? $layout['barcode_top_mm']);
        $barW = (float) ($layout['box2_barcode_width_mm'] ?? $layout['barcode_width_mm']);
        $barH = (float) ($layout['box2_barcode_height_mm'] ?? $layout['barcode_height_mm']);
    } else {
        $barLeft = (float) ($layout['box1_barcode_left_mm'] ?? $layout['barcode_left_mm']);
        $barTop = (float) ($layout['box1_barcode_top_mm'] ?? $layout['barcode_top_mm']);
        $barW = (float) ($layout['box1_barcode_width_mm'] ?? $layout['barcode_width_mm']);
        $barH = (float) ($layout['box1_barcode_height_mm'] ?? $layout['barcode_height_mm']);
    }
    return [
        [
            'type'   => 'barcode_image',
            'left'   => $barLeft,
            'top'    => $barTop,
            'width'  => $barW,
            'height' => $barH,
        ],
    ];
}

function render82x38DesignStickerLabel(array $print_item, array $settings, array $decoded_snapshot = [], int $page_index = 0): string {
    $snapshot = is_array($decoded_snapshot) ? $decoded_snapshot : [];
    $layout = auragold_82x38_2box_layout($snapshot);
    $boxW = (float) $layout['box_width_mm'];
    $boxH = (float) $layout['box_height_mm'];
    $idSuffix = ($page_index > 0) ? ('_' . (int) $page_index) : '';
    $html = '<div class="sticker-page" style="width:82mm;height:38mm;position:relative;overflow:hidden;">';
    $pairs = [
        1 => ['pos' => $layout['box1'], 'item' => $print_item['box1'] ?? null],
        2 => ['pos' => $layout['box2'], 'item' => $print_item['box2'] ?? null],
    ];
    foreach ($pairs as $num => $pair) {
        if (!is_array($pair['item']) || empty($pair['item']['barcode'])) {
            continue;
        }
        $productData = array_merge($pair['item']['row'] ?? [], ['barcode' => $pair['item']['barcode']]);
        $boxDesign = auragold_82x38_box_design_items($snapshot, (int) $num);
        $barcodeItem = auragold_82x38_box_barcode_item($snapshot, (int) $num);
        if ($barcodeItem !== null) {
            $extra = [];
            foreach ($boxDesign as $el) {
                if (!is_array($el) || ($el['type'] ?? '') === 'barcode_image') {
                    continue;
                }
                $extra[] = $el;
            }
            $boxDesign = array_merge([$barcodeItem], $extra);
        } elseif (empty($boxDesign)) {
            $boxDesign = auragold_82x38_default_box_design_items($snapshot, (int) $num);
        }
        $boxSettings = array_merge($settings, [
            'label_width_mm'    => $boxW,
            'label_height_mm'   => $boxH,
            'design_layout'     => $boxDesign,
            'barcode_svg_class' => 'barcode-svg-box' . (int) $num,
            'barcode_svg_id'    => 'barcodeSvgBox' . (int) $num . $idSuffix,
        ]);
        $inner = renderBarcodeLayout($productData, $boxSettings);
        if (strpos($inner, 'barcode-svg') === false) {
            $def = auragold_82x38_default_box_design_items($snapshot, (int) $num);
            $inner = renderBarcodeLayout($productData, array_merge($boxSettings, ['design_layout' => $def]));
        }
        $boxStyle = 'left:' . $pair['pos']['left'] . 'mm;top:' . $pair['pos']['top'] . 'mm;'
            . 'width:20mm;height:25mm;';
        $html .= '<div class="barcode-box barcode-box--' . (int) $num . '" style="' . htmlspecialchars($boxStyle, ENT_QUOTES, 'UTF-8') . '">';
        $html .= '<div class="barcode-box-inner" style="position:relative;width:100%;height:100%;overflow:hidden;box-sizing:border-box;">';
        $html .= $inner;
        $html .= '</div></div>';
    }
    $html .= '</div>';
    return $html;
}

function render82x38DoubleStickerLabel(array $print_item, array $settings, array $decoded_snapshot = [], int $page_index = 0): string {
    return render82x38DesignStickerLabel($print_item, $settings, $decoded_snapshot, $page_index);
}

/**
 * True when barcode print uses two tags on one 120×50 mm sticker.
 */
function auragold_is_120x50_double_barcode(array $settings, $decoded_snapshot = null): bool {
    $preset = isset($settings['label_size_preset']) ? trim((string) $settings['label_size_preset']) : '';
    if ($preset === '120x50') {
        return true;
    }
    if (is_array($decoded_snapshot) && !empty($decoded_snapshot['double_barcode_120x50'])) {
        return true;
    }
    $w = (float) ($settings['label_width_mm'] ?? 0);
    $h = (float) ($settings['label_height_mm'] ?? 0);
    return abs($w - 120) < 0.15 && abs($h - 50) < 0.15
        && is_array($decoded_snapshot)
        && (!empty($decoded_snapshot['items2']) || isset($decoded_snapshot['barcode2_left']));
}

/**
 * White printable area X origin for left/right tag on a 120×50 sticker (mm from sheet left).
 */
function auragold_120x50_tag_white_origin_mm(string $side, array $snapshot = []): float {
    $half = (float) ($snapshot['dual_tag_half_width_mm'] ?? 59.0);
    $gap = (float) ($snapshot['dual_tag_gap_mm'] ?? 2.0);
    $strip = 6.0;
    if ($side === 'left') {
        return $strip;
    }
    return $half + $gap + $strip;
}

/** User target barcode size (mm); scaled down to fit each 120×50 quadrant. */
function auragold_120x50_target_barcode_mm(): array {
    return ['width' => 83.0, 'height' => 37.0];
}

/** Physical print box per quadrant on 120×50 butterfly tags (2 cm × 2.5 cm). */
function auragold_120x50_quadrant_box_mm(array $snapshot = []): array {
    return [
        'width'  => (float) ($snapshot['dual_quadrant_width_mm'] ?? 20.0),
        'height' => (float) ($snapshot['dual_quadrant_height_mm'] ?? 25.0),
    ];
}

/**
 * Max barcode graphic size (mm) inside one 2×2.5 cm print box.
 *
 * @return array{width:float,height:float}
 */
function auragold_120x50_quadrant_barcode_mm(array $snapshot = []): array {
    $box = auragold_120x50_quadrant_box_mm($snapshot);
    $margin = 1.0;
    $textMm = 3.5;
    return [
        'width'  => round(max(8.0, $box['width'] - ($margin * 2)), 2),
        'height' => round(max(5.0, $box['height'] - $margin - $textMm), 2),
    ];
}

/**
 * Saved barcode_image element for left (tag 1) or right (tag 2) from design_layout JSON.
 *
 * @return array<string,mixed>|null
 */
function auragold_120x50_snapshot_barcode_el(array $snapshot, string $side): ?array {
    $lists = [];
    if ($side === 'left') {
        if (!empty($snapshot['items']) && is_array($snapshot['items'])) {
            $lists[] = $snapshot['items'];
        }
        if (!empty($snapshot['fields']) && is_array($snapshot['fields'])) {
            $lists[] = $snapshot['fields'];
        }
    } else {
        if (!empty($snapshot['items2']) && is_array($snapshot['items2'])) {
            $lists[] = $snapshot['items2'];
        }
        if (!empty($snapshot['fields2']) && is_array($snapshot['fields2'])) {
            $lists[] = $snapshot['fields2'];
        }
    }
    foreach ($lists as $arr) {
        foreach ($arr as $el) {
            if (is_array($el) && ($el['type'] ?? '') === 'barcode_image') {
                return $el;
            }
        }
    }
    return null;
}

/**
 * Barcode image size from saved layout items for one tag side.
 *
 * @return array{width:float,height:float}
 */
function auragold_120x50_barcode_size_from_snapshot(array $snapshot, string $side): array {
    $fit = auragold_120x50_quadrant_barcode_mm($snapshot);
    $size = ['width' => $fit['width'], 'height' => $fit['height']];
    $el = auragold_120x50_snapshot_barcode_el($snapshot, $side);
    if (!$el) {
        return $size;
    }
    $savedW = (float) ($el['width'] ?? 0);
    $savedH = (float) ($el['height'] ?? 0);
    // Older saves stored JsBarcode natural width (~15mm), not designer display width.
    if ($savedW > 0 && $savedW < ($fit['width'] * 0.75)) {
        $savedW = $fit['width'];
    }
    if (isset($el['display_width_mm']) && (float) $el['display_width_mm'] > 0) {
        $savedW = max($savedW, (float) $el['display_width_mm']);
    }
    if ($savedW > 0) {
        $size['width'] = max(8.0, min($fit['width'], round($savedW, 2)));
    }
    if ($savedH > 0) {
        $size['height'] = max(5.0, min($fit['height'], round($savedH, 2)));
    }
    return $size;
}

/**
 * Default vertical position (mm) for diagonal 120×50 layout.
 */
function auragold_120x50_default_quadrant_top_mm(string $side, float $height, float $margin = 3.0): float {
    $stickerH = 50.0;
    $foldY = $stickerH / 2.0;
    $textMm = 4.5;
    if ($side === 'left') {
        $minTop = $foldY + 1.0;
        return max($minTop, $stickerH - $height - $textMm - $margin);
    }
    return $margin;
}

/**
 * Keep barcode in correct half: left pocket = bottom-left, right pocket = top-right.
 */
function auragold_120x50_normalize_quadrant_top_mm(string $side, float $top, float $height, float $margin = 3.0): float {
    $stickerH = 50.0;
    $foldY = $stickerH / 2.0;
    $textMm = 4.5;
    if ($side === 'left') {
        if ($top >= $foldY - 0.5) {
            return max($foldY + 1.0, min($stickerH - $height - $textMm - $margin, $top));
        }
        return auragold_120x50_default_quadrant_top_mm('left', $height, $margin);
    }
    if ($top < $foldY - 0.5) {
        return max($margin, min($foldY - $height - 1.0, $top));
    }
    return auragold_120x50_default_quadrant_top_mm('right', $height, $margin);
}

/**
 * Physical tag geometry on a 120×50 butterfly sticker (mm).
 *
 * @return array{sticker_w:float,sticker_h:float,half_w:float,gap:float,strip_mm:float,handle_mm:float,fold_y:float,left_white_x:float,left_white_w:float,right_white_x:float,right_white_w:float}
 */
function auragold_120x50_tag_layout_mm(array $snapshot = []): array {
    $stickerW = 120.0;
    $stickerH = 50.0;
    $gap = (float) ($snapshot['dual_tag_gap_mm'] ?? 2.0);
    $half = (float) ($snapshot['dual_tag_half_width_mm'] ?? 0.0);
    if ($half <= 0) {
        $half = ($stickerW - $gap) / 2.0;
    }
    $strip = 6.0;
    $handle = 10.0;
    $whiteW = max(8.0, $half - $strip - $handle);
    $rightTagX = $half + $gap;
    return [
        'sticker_w'     => $stickerW,
        'sticker_h'     => $stickerH,
        'half_w'        => $half,
        'gap'           => $gap,
        'strip_mm'      => $strip,
        'handle_mm'     => $handle,
        'fold_y'        => $stickerH / 2.0,
        'left_white_x'  => $strip,
        'left_white_w'  => $whiteW,
        'right_white_x' => $rightTagX + $strip,
        'right_white_w' => $whiteW,
    ];
}

/**
 * Sticker origin (mm) for one 2×2.5 cm print box on 120×50.
 * Horizontally centered in each tag; vertically in the bottom half so the
 * horizontal fold line (sticker center) sits above the print box.
 *
 * @return array{left:float,top:float}
 */
function auragold_120x50_quadrant_origin_mm(string $side, array $snapshot = []): array {
    $layout = auragold_120x50_tag_layout_mm($snapshot);
    $box = auragold_120x50_quadrant_box_mm($snapshot);
    $boxW = (float) $box['width'];
    $boxH = (float) $box['height'];
    $stickerH = (float) $layout['sticker_h'];
    $foldY = (float) $layout['fold_y'];
    $top = round(max($foldY, $stickerH - $boxH), 2);
    if ($side === 'left') {
        $whiteW = (float) $layout['left_white_w'];
        $left = (float) $layout['left_white_x'] + max(0.0, ($whiteW - $boxW) / 2.0);
        return ['left' => round($left, 2), 'top' => $top];
    }
    $whiteW = (float) $layout['right_white_w'];
    $left = (float) $layout['right_white_x'] + max(0.0, ($whiteW - $boxW) / 2.0);
    return ['left' => round($left, 2), 'top' => $top];
}

/**
 * Fixed mm pockets for 120×50 butterfly label — one centered box per tag.
 *
 * @return array{top:float,width:float,height:float,left:float,barcode_width:float,barcode_height:float,barcode_left:float,barcode_top:float,anchor:string}
 */
function auragold_120x50_pocket_mm(string $side, array $snapshot = []): array {
    $box = auragold_120x50_quadrant_box_mm($snapshot);
    $origin = auragold_120x50_quadrant_origin_mm($side, $snapshot);
    $barcodeSize = auragold_120x50_barcode_size_from_snapshot($snapshot, $side);
    $el = auragold_120x50_snapshot_barcode_el($snapshot, $side);

    $insetLeft = max(0.0, ((float) $box['width'] - $barcodeSize['width']) / 2.0);
    $insetTop = max(0.0, ((float) $box['height'] - $barcodeSize['height']) / 2.0);
    if ($el) {
        $insetLeft = max(0.0, min(max(0.0, $box['width'] - $barcodeSize['width']), (float) ($el['left'] ?? $insetLeft)));
        $insetTop = max(0.0, min(max(0.0, $box['height'] - $barcodeSize['height']), (float) ($el['top'] ?? $insetTop)));
    }

    return [
        'anchor'         => 'left',
        'left'           => round($origin['left'], 2),
        'top'            => round($origin['top'], 2),
        'width'          => round($box['width'], 2),
        'height'         => round($box['height'], 2),
        'barcode_width'  => round($barcodeSize['width'], 2),
        'barcode_height' => round($barcodeSize['height'], 2),
        'barcode_left'   => round($insetLeft, 2),
        'barcode_top'    => round($insetTop, 2),
    ];
}

/**
 * One fixed-position copy (right or left pocket) on a 120×50 jewelry sticker.
 */
function render120x50FixedCopy(string $code, string $side, bool $show_barcode_number, array $snapshot = []): string {
    $copyClass = ($side === 'right') ? 'barcode-copy-right' : 'barcode-copy-left';
    $pocket = auragold_120x50_pocket_mm($side, $snapshot);
    $boxW = (float) ($pocket['width'] ?? 20);
    $boxH = (float) ($pocket['height'] ?? 25);
    $graphicW = (float) ($pocket['barcode_width'] ?? $boxW);
    $graphicH = (float) ($pocket['barcode_height'] ?? min(12.0, $boxH * 0.55));
    $style = 'top:' . round($pocket['top'], 2) . 'mm;width:' . round($boxW, 2) . 'mm;height:' . round($boxH, 2) . 'mm;';
    $style .= 'left:' . round($pocket['left'], 2) . 'mm;right:auto;';
    $graphicWmm = round(max(8.0, min($boxW, $graphicW)), 2);
    $graphicHmm = round(max(5.0, min($boxH, $graphicH)), 2);
    $graphicStyle = 'position:absolute;left:0;top:0;width:100%;height:100%;overflow:hidden;box-sizing:border-box;'
        . 'display:flex;align-items:center;justify-content:center;';
    $html = '<div class="' . htmlspecialchars($copyClass, ENT_QUOTES, 'UTF-8') . ' barcode-print-box-120x50" style="' . htmlspecialchars($style, ENT_QUOTES, 'UTF-8') . '" title="Print box 2 cm × 2.5 cm">';
    $html .= '<span class="barcode-print-box-label no-print">2 cm × 2.5 cm</span>';
    $html .= '<div class="barcode-120x50-graphic" style="' . htmlspecialchars($graphicStyle, ENT_QUOTES, 'UTF-8') . '" data-graphic-w-mm="' . $graphicWmm . '" data-graphic-h-mm="' . $graphicHmm . '">';
    $html .= '<svg class="barcode-svg barcode-svg--120x50" data-barcode="' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '" data-pocket-width-mm="' . $graphicWmm . '" data-pocket-height-mm="' . $graphicHmm . '"></svg>';
    $html .= '</div>';
    if ($show_barcode_number) {
        $html .= '<div class="barcode-copy-text">' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</div>';
    }
    $html .= '</div>';
    return $html;
}

/** @deprecated Layout slice; not used for 120×50 fixed print. */
function auragold_120x50_side_layout(array $layout, array $decoded_snapshot, string $side): array {
    $layout = is_array($layout) ? $layout : [];
    $out = [];
    foreach ($layout as $el) {
        if (!is_array($el)) {
            continue;
        }
        $type = isset($el['type']) ? trim((string) $el['type']) : '';
        if ($type === 'barcode_image' || $type === 'qr_image') {
            continue;
        }
        if ($type === 'text' || (isset($el['field']) && trim((string) $el['field']) !== '')) {
            $out[] = $el;
        }
    }
    $barcode_el = null;
    foreach ($layout as $el) {
        if (is_array($el) && isset($el['type']) && $el['type'] === 'barcode_image') {
            $barcode_el = $el;
            break;
        }
    }
    if (!$barcode_el) {
        if ($side === 'right' && isset($decoded_snapshot['barcode2_left'], $decoded_snapshot['barcode2_top'])) {
            $barcode_el = [
                'type' => 'barcode_image',
                'left' => (float) $decoded_snapshot['barcode2_left'],
                'top' => (float) $decoded_snapshot['barcode2_top'],
                'width' => 20,
                'height' => 8,
            ];
        } elseif ($side === 'left' && isset($decoded_snapshot['barcode1_left'], $decoded_snapshot['barcode1_top'])) {
            $barcode_el = [
                'type' => 'barcode_image',
                'left' => (float) $decoded_snapshot['barcode1_left'],
                'top' => (float) $decoded_snapshot['barcode1_top'],
                'width' => 20,
                'height' => 8,
            ];
        } else {
            $barcode_el = ['type' => 'barcode_image', 'left' => 0.5, 'top' => 0.5, 'width' => 18, 'height' => 8];
        }
    }
    array_unshift($out, $barcode_el);
    return $out;
}

/**
 * One 120x50 sticker: different barcode in left and right pockets (pair from print list).
 *
 * @param array $print_item ['left' => ['barcode','row'], 'right' => ['barcode','row']] or legacy single ['barcode','row']
 */
function render120x50DoubleStickerLabel(array $print_item, array $settings, array $decoded_snapshot = []): string {
    $snapshot = is_array($decoded_snapshot) ? $decoded_snapshot : [];

    $left_code = '';
    $right_code = '';
    if (isset($print_item['left']) && is_array($print_item['left'])) {
        $left_code = trim((string) ($print_item['left']['barcode'] ?? ''));
    }
    if (isset($print_item['right']) && is_array($print_item['right'])) {
        $right_code = trim((string) ($print_item['right']['barcode'] ?? ''));
    }
    if ($left_code === '' && $right_code === '') {
        $left_code = trim((string) ($print_item['barcode'] ?? ''));
    }
    if ($left_code === '' && $right_code === '') {
        return '';
    }

    $sticker_w = (float) ($settings['label_width_mm'] ?? 120);
    $sticker_h = (float) ($settings['label_height_mm'] ?? 50);
    $show_number = isset($settings['show_barcode_number']) && (int) $settings['show_barcode_number'] === 1;

    $screen_w_cm = 8.2;
    $screen_h_cm = 3.8;
    $html = '<div class="barcode-sticker-measure-wrap">';
    $html .= '<div class="full-sticker" data-screen-w-cm="' . $screen_w_cm . '" data-screen-h-cm="' . $screen_h_cm . '" data-sheet-w-mm="' . round($sticker_w, 2) . '" data-sheet-h-mm="' . round($sticker_h, 2) . '">';
    $html .= '<div class="full-sticker-inner" style="width:' . round($sticker_w, 2) . 'mm;height:' . round($sticker_h, 2) . 'mm;">';
    // Print order: right tag first, then left tag (side by side on one row)
    if ($right_code !== '') {
        $html .= render120x50FixedCopy($right_code, 'right', $show_number, $snapshot);
    }
    if ($left_code !== '') {
        $html .= render120x50FixedCopy($left_code, 'left', $show_number, $snapshot);
    }
    $html .= '</div></div>';
    $html .= '<div class="barcode-dim-ruler barcode-dim-ruler--width no-print" aria-hidden="true">'
        . '<span class="barcode-dim-line barcode-dim-line--h"></span>'
        . '<span class="barcode-dim-width-val">W = 8.2 cm</span></div>';
    $html .= '<div class="barcode-dim-ruler barcode-dim-ruler--height no-print" aria-hidden="true">'
        . '<span class="barcode-dim-line barcode-dim-line--v"></span>'
        . '<span class="barcode-dim-height-val">H = 3.8 cm</span></div>';
    $html .= '</div>';
    return $html;
}

/** @deprecated */
function renderBarcodeLayout120x50Double(array $productData, array $settings, array $decoded_snapshot = []): string {
    return render120x50DoubleStickerLabel(
        ['barcode' => $productData['barcode'] ?? '', 'row' => $productData],
        $settings,
        $decoded_snapshot
    );
}
