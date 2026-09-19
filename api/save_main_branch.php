<?php
/**
 * Create a new main branch (main_branch_id = 0) with a dedicated database — superadmin only, JSON response.
 */
session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/session_login_type.php';
require_once dirname(__DIR__) . '/includes/branch_working_context.php';
require_once dirname(__DIR__) . '/includes/ensure_tbl_settings.php';
require_once dirname(__DIR__) . '/includes/location-helpers.php';
require_once dirname(__DIR__) . '/includes/auragold_seed_branch_bill_series.php';
require_once dirname(__DIR__) . '/includes/branch_create_db_after_save.php';
require_once dirname(__DIR__) . '/includes/branch_db_auto_credentials.php';
require_once dirname(__DIR__) . '/includes/auragold_seed_main_branch_tbl_users.php';
require_once dirname(__DIR__) . '/includes/branch_portal_folder_provision.php';
require_once dirname(__DIR__) . '/includes/auragold_seed_branch_metal_and_customer_types.php';
require_once dirname(__DIR__) . '/includes/branch_tbl_branches_ip_subdomain.php';
require_once dirname(__DIR__) . '/includes/branch_profile_schema.php';
require_once dirname(__DIR__) . '/includes/branch_panel_password.php';

header('Content-Type: application/json; charset=utf-8');

if (function_exists('set_time_limit')) {
    @set_time_limit(600);
}
@ini_set('max_execution_time', '600');
@ini_set('memory_limit', '512M');

if (!function_exists('auragold_save_branch_json_encode')) {
    /**
     * @param mixed $payload
     */
    function auragold_save_branch_json_encode($payload): string {
        $flags = JSON_UNESCAPED_UNICODE;
        if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
            $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
        }
        $json = json_encode($payload, $flags);
        if ($json !== false) {
            return $json;
        }
        $fallback = [
            'ok'      => !empty($payload['ok']),
            'message' => 'Branch operation finished but the server could not encode the full response. Check PHP error log.',
            'branch_id' => isset($payload['branch_id']) ? (int) $payload['branch_id'] : 0,
        ];
        return json_encode($fallback, $flags) ?: '{"ok":false,"message":"JSON encode failed"}';
    }
}

if (!function_exists('auragold_branch_table_has_column')) {
    function auragold_branch_table_has_column($link, $table, $column) {
        $table  = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $column);
        if ($table === '' || $column === '') {
            return false;
        }
        $r = @mysqli_query($link, "SHOW COLUMNS FROM `$table` LIKE '$column'");
        $ok = $r && mysqli_num_rows($r) > 0;
        if ($r) {
            mysqli_free_result($r);
        }
        return $ok;
    }
}

if (empty($_SESSION['Admin'])) {
    echo auragold_save_branch_json_encode(['ok' => false, 'message' => 'Not logged in']);
    exit;
}

if (!auragold_session_is_admin_login_type()) {
    echo auragold_save_branch_json_encode(['ok' => false, 'message' => 'Only admin users can add branches.']);
    exit;
}

if (!auragold_session_may_create_main_branch()) {
    echo auragold_save_branch_json_encode(['ok' => false, 'message' => 'Only superadmin logged in from the GM portal (gm.goldmatrixsoft.com) can create a new main branch.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo auragold_save_branch_json_encode(['ok' => false, 'message' => 'Invalid request method']);
    exit;
}

$password = isset($_REQUEST['password']) ? trim((string) $_REQUEST['password']) : '';
if ($password === '') {
    echo auragold_save_branch_json_encode(['ok' => false, 'message' => 'Session password missing. Close the form and verify again.']);
    exit;
}

if (!auragold_ensure_tbl_settings_branch_password($conn_master)) {
    echo auragold_save_branch_json_encode(['ok' => false, 'message' => 'Could not create or update tbl_settings. Check DB permissions or run admin/sql/branch_add_secure.sql']);
    exit;
}

$srow = getRecordMaster('SELECT branch_password_hash FROM tbl_settings ORDER BY id ASC LIMIT 1');
$hash = $srow && isset($srow['branch_password_hash']) ? trim((string) $srow['branch_password_hash']) : '';
if ($hash === '' || !password_verify($password, $hash)) {
    echo auragold_save_branch_json_encode(['ok' => false, 'message' => 'Invalid Password']);
    exit;
}

try {

if (!empty($conn_master) && function_exists('auragold_ensure_branches_ip_subdomain_columns_on_registry')) {
    auragold_ensure_branches_ip_subdomain_columns_on_registry($conn_master);
}
if (!empty($conn_master)) {
    auragold_ensure_tbl_branches_panel_password($conn_master);
}

$branch_name = isset($_REQUEST['branch_name']) ? trim((string) $_REQUEST['branch_name']) : '';
if ($branch_name === '') {
    echo auragold_save_branch_json_encode(['ok' => false, 'message' => 'Branch name is required']);
    exit;
}

$contact1 = isset($_REQUEST['contact1']) ? trim((string) $_REQUEST['contact1']) : '';
$contact2 = isset($_REQUEST['contact2']) ? trim((string) $_REQUEST['contact2']) : '';
$mail     = isset($_REQUEST['mail_id']) ? trim((string) $_REQUEST['mail_id']) : '';
$digits   = isset($_REQUEST['no_of_digits']) ? (int) $_REQUEST['no_of_digits'] : 0;
$prefix   = isset($_REQUEST['barcode_prefix']) ? trim((string) $_REQUEST['barcode_prefix']) : '';
$address = isset($_REQUEST['address']) ? trim((string) $_REQUEST['address']) : '';
$zip     = isset($_REQUEST['zip_code']) ? trim((string) $_REQUEST['zip_code']) : '';
$__branch_ip_raw = isset($_REQUEST['branch_ip_host']) ? trim((string) $_REQUEST['branch_ip_host']) : '';
if ($__branch_ip_raw === '') {
    echo auragold_save_branch_json_encode(['ok' => false, 'message' => 'IP address is required. Enter a valid IP, hostname, or full URL (http/https).']);
    exit;
}
$__host = function_exists('auragold_branch_ip_and_subdomain_for_storage')
    ? auragold_branch_ip_and_subdomain_for_storage($__branch_ip_raw)
    : ['ip_address' => '', 'subdomain_url' => ''];
if (!empty($__host['rejected'])) {
    echo auragold_save_branch_json_encode(['ok' => false, 'message' => (string) $__host['rejected']]);
    exit;
}
$ip_address   = (string) ($__host['ip_address'] ?? '');
$subdomain_url = (string) ($__host['subdomain_url'] ?? '');
unset($__host);

$country_id_req = isset($_REQUEST['country_id']) ? (int) $_REQUEST['country_id'] : 0;
$state_id_req   = isset($_REQUEST['state_id']) ? (int) $_REQUEST['state_id'] : 0;
$city_id_req    = isset($_REQUEST['city_id']) ? (int) $_REQUEST['city_id'] : 0;
$phone_country_code = isset($_REQUEST['profile_phone_country_code'])
    ? trim((string) $_REQUEST['profile_phone_country_code'])
    : '';
$timezone_req = isset($_REQUEST['timezone']) ? trim((string) $_REQUEST['timezone']) : '';
if ($timezone_req === '' && function_exists('auragold_timezone_guess_from_phone_code')) {
    $timezone_req = auragold_timezone_guess_from_phone_code($phone_country_code);
}
$timezone_req = function_exists('auragold_timezone_normalize')
    ? auragold_timezone_normalize($timezone_req)
    : 'Asia/Kolkata';

$country = '';
$state   = '';
$city    = '';

if (!empty($conn)) {
    auragold_bootstrap_location_data($conn);
}

if ($phone_country_code === '') {
    echo auragold_save_branch_json_encode(['ok' => false, 'message' => 'Country code is required.']);
    exit;
}
if (strlen($phone_country_code) > 10 || !preg_match('/^\d{1,10}$/', $phone_country_code)) {
    echo auragold_save_branch_json_encode(['ok' => false, 'message' => 'Invalid country code.']);
    exit;
}

if ($country_id_req <= 0) {
    echo auragold_save_branch_json_encode(['ok' => false, 'message' => 'Country is required.']);
    exit;
}
if ($state_id_req <= 0) {
    echo auragold_save_branch_json_encode(['ok' => false, 'message' => 'State is required.']);
    exit;
}
if ($city_id_req <= 0) {
    echo auragold_save_branch_json_encode(['ok' => false, 'message' => 'City is required.']);
    exit;
}

if ($country_id_req > 0 && $conn) {
    $cr = getRecord("SELECT id, name FROM tbl_countries WHERE id = $country_id_req AND status = 1 LIMIT 1");
    if ($cr && isset($cr['name'])) {
        $country = trim((string) $cr['name']);
    }
}
if ($country === '') {
    echo auragold_save_branch_json_encode(['ok' => false, 'message' => 'Selected country is invalid.']);
    exit;
}

if ($state_id_req > 0 && $conn) {
    $sr = getRecord("SELECT id, name, country_id FROM tbl_states WHERE id = $state_id_req AND status = 1 LIMIT 1");
    if ($sr && isset($sr['name'])) {
        $stCid = (int) ($sr['country_id'] ?? 0);
        if ($stCid !== $country_id_req) {
            echo auragold_save_branch_json_encode(['ok' => false, 'message' => 'Selected state does not belong to the selected country.']);
            exit;
        }
        $state = trim((string) $sr['name']);
    }
}
if ($state === '') {
    echo auragold_save_branch_json_encode(['ok' => false, 'message' => 'Selected state is invalid.']);
    exit;
}

if ($city_id_req > 0 && $conn) {
    $cir = getRecord("SELECT id, name, state_id FROM tbl_cities WHERE id = $city_id_req AND status = 1 LIMIT 1");
    if ($cir && isset($cir['name'])) {
        $ciSid = (int) ($cir['state_id'] ?? 0);
        if ($ciSid !== $state_id_req) {
            echo auragold_save_branch_json_encode(['ok' => false, 'message' => 'Selected city does not belong to the selected state.']);
            exit;
        }
        $city = trim((string) $cir['name']);
    }
}
if ($city === '') {
    echo auragold_save_branch_json_encode(['ok' => false, 'message' => 'Selected city is invalid.']);
    exit;
}
$active = isset($_REQUEST['active']) ? (string) $_REQUEST['active'] : '0';
$status = ($active === '1' || $active === 'true' || $active === 'on') ? 1 : 0;

if ($mail !== '' && !filter_var($mail, FILTER_VALIDATE_EMAIL)) {
    echo auragold_save_branch_json_encode(['ok' => false, 'message' => 'Invalid email address']);
    exit;
}

if ($digits < 1 || $digits > 32) {
    echo auragold_save_branch_json_encode(['ok' => false, 'message' => 'Number of digits must be between 1 and 32']);
    exit;
}

$main_branch_id = 0;

$code = $prefix !== '' ? strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $prefix), 0, 20)) : '';
if ($code === '') {
    $code = null;
}

$creds        = auragold_allocate_unique_branch_db_credentials($conn_master, $branch_name);
$db_name_req  = $creds['db_name'];
$db_users_req = $creds['db_users'];
$db_pass_req  = $creds['db_password'];

$name_esc     = mysqli_real_escape_string($conn_master, $branch_name);
$contact1_esc = mysqli_real_escape_string($conn_master, $contact1);
$contact2_esc = mysqli_real_escape_string($conn_master, $contact2);
$mail_esc     = mysqli_real_escape_string($conn_master, $mail);
$prefix_esc   = mysqli_real_escape_string($conn_master, $prefix);
$address_esc  = mysqli_real_escape_string($conn_master, $address);
$country_esc  = mysqli_real_escape_string($conn_master, $country);
$state_esc    = mysqli_real_escape_string($conn_master, $state);
$zip_esc        = mysqli_real_escape_string($conn_master, $zip);
$ip_esc         = mysqli_real_escape_string($conn_master, $ip_address);
$subdomain_esc  = mysqli_real_escape_string($conn_master, $subdomain_url);
$code_sql     = $code !== null ? "'" . mysqli_real_escape_string($conn_master, $code) . "'" : 'NULL';

$db_name_sql  = "'" . mysqli_real_escape_string($conn_master, $db_name_req) . "'";
$db_users_sql = "'" . mysqli_real_escape_string($conn_master, $db_users_req) . "'";
$db_pass_sql  = "'" . mysqli_real_escape_string($conn_master, $db_pass_req) . "'";

if (!empty($conn_master) && function_exists('auragold_ensure_tbl_branches_profile_columns')) {
    auragold_ensure_tbl_branches_profile_columns($conn_master);
}

$cols = ['name', 'code', 'db_name', 'db_users', 'db_password', 'main_branch_id', 'status', 'created_at'];
$vals = ["'$name_esc'", $code_sql, $db_name_sql, $db_users_sql, $db_pass_sql, (int) $main_branch_id, (int) $status, 'NOW()'];

if (auragold_branch_table_has_column($conn_master, 'tbl_branches', 'phone')) {
    $cols[] = 'phone';
    $vals[] = $contact1 !== '' ? "'$contact1_esc'" : 'NULL';
}
if (auragold_branch_table_has_column($conn_master, 'tbl_branches', 'phone2')) {
    $cols[] = 'phone2';
    $vals[] = $contact2 !== '' ? "'$contact2_esc'" : 'NULL';
}
if (auragold_branch_table_has_column($conn_master, 'tbl_branches', 'email')) {
    $cols[] = 'email';
    $vals[] = $mail !== '' ? "'$mail_esc'" : 'NULL';
}
if (auragold_branch_table_has_column($conn_master, 'tbl_branches', 'address')) {
    $cols[] = 'address';
    $vals[] = $address !== '' ? "'$address_esc'" : 'NULL';
}
if (auragold_branch_table_has_column($conn_master, 'tbl_branches', 'country')) {
    $cols[] = 'country';
    $vals[] = $country !== '' ? "'$country_esc'" : 'NULL';
}
if (auragold_branch_table_has_column($conn_master, 'tbl_branches', 'state')) {
    $cols[] = 'state';
    $vals[] = $state !== '' ? "'$state_esc'" : 'NULL';
}
if (auragold_branch_table_has_column($conn_master, 'tbl_branches', 'zip_code')) {
    $cols[] = 'zip_code';
    $vals[] = $zip !== '' ? "'$zip_esc'" : 'NULL';
}
if (auragold_branch_table_has_column($conn_master, 'tbl_branches', 'barcode_num_digits')) {
    $cols[] = 'barcode_num_digits';
    $vals[] = (string) (int) $digits;
}
if (auragold_branch_table_has_column($conn_master, 'tbl_branches', 'branch_barcode_prefix')) {
    $cols[] = 'branch_barcode_prefix';
    $vals[] = $prefix !== '' ? "'$prefix_esc'" : 'NULL';
}
if (auragold_branch_table_has_column($conn_master, 'tbl_branches', 'ip_address')) {
    $cols[] = 'ip_address';
    $vals[] = $ip_address !== '' ? "'$ip_esc'" : 'NULL';
}
if (auragold_branch_table_has_column($conn_master, 'tbl_branches', 'subdomain_url')) {
    $cols[] = 'subdomain_url';
    $vals[] = $subdomain_url !== '' ? "'$subdomain_esc'" : 'NULL';
}
if (auragold_branch_table_has_column($conn_master, 'tbl_branches', 'profile_country_id')) {
    $cols[] = 'profile_country_id';
    $vals[] = (string) (int) $country_id_req;
}
if (auragold_branch_table_has_column($conn_master, 'tbl_branches', 'profile_state_id')) {
    $cols[] = 'profile_state_id';
    $vals[] = (string) (int) $state_id_req;
}
if (auragold_branch_table_has_column($conn_master, 'tbl_branches', 'profile_city_id')) {
    $cols[] = 'profile_city_id';
    $vals[] = (string) (int) $city_id_req;
}
if (auragold_branch_table_has_column($conn_master, 'tbl_branches', 'profile_phone_country_code')) {
    $cols[] = 'profile_phone_country_code';
    $vals[] = "'" . mysqli_real_escape_string($conn_master, $phone_country_code) . "'";
}
if (auragold_branch_table_has_column($conn_master, 'tbl_branches', 'timezone')) {
    $cols[] = 'timezone';
    $vals[] = "'" . mysqli_real_escape_string($conn_master, $timezone_req) . "'";
}
if (auragold_branch_table_has_column($conn_master, 'tbl_branches', 'location_area') && $city !== '') {
    $cols[] = 'location_area';
    $vals[] = "'" . mysqli_real_escape_string($conn_master, $city) . "'";
}
if (auragold_branch_table_has_column($conn_master, 'tbl_branches', 'panel_password_hash')) {
    $cols[] = 'panel_password_hash';
    $vals[] = "'" . mysqli_real_escape_string($conn_master, auragold_branch_panel_password_default_hash()) . "'";
}

$sql = 'INSERT INTO tbl_branches (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';

if (!mysqli_query($conn_master, $sql)) {
    echo auragold_save_branch_json_encode(['ok' => false, 'message' => 'Could not save branch: ' . mysqli_error($conn_master)]);
    exit;
}

$newId = (int) mysqli_insert_id($conn_master);

$provisionOpts = [
    'omit_master_table_names' => ['tbl_users', 'tbl_tax_master', 'tbl_carat'],
];

$dbProv                  = ['ok' => true, 'skipped' => true, 'message' => ''];
$dedicatedProvisioned    = false;
if ($newId > 0) {
    $dbProv               = auragold_after_branch_insert_create_db_and_schema(
        $conn_master,
        $db_name_req,
        $db_users_req,
        $db_pass_req,
        $newId,
        $provisionOpts
    );
    $dedicatedProvisioned = !empty($dbProv['provisioned']);
    if (empty($dbProv['ok'])) {
        echo auragold_save_branch_json_encode([
            'ok'        => false,
            'message'   => $dbProv['message'] ?? 'Branch database setup failed.',
            'branch_id' => $newId,
        ]);
        exit;
    }
}

if ($newId > 0
    && $db_name_req !== ''
    && defined('DB_NAME')
    && strcasecmp($db_name_req, (string) DB_NAME) !== 0
    && function_exists('auragold_count_tables_in_schema')) {
    $tcCheck = auragold_count_tables_in_schema($conn_master, $db_name_req);
    if ($tcCheck === 0) {
        if (!function_exists('auragold_provision_branch_database')) {
            require_once dirname(__DIR__) . '/includes/branch_database_provision.php';
        }
        $schemaSrc = function_exists('auragold_branch_schema_clone_source_db')
            ? auragold_branch_schema_clone_source_db()
            : (string) DB_NAME;
        if ($schemaSrc === '') {
            $schemaSrc = (string) DB_NAME;
        }
        $retryProv = auragold_provision_branch_database(
            $db_name_req,
            $schemaSrc,
            $newId,
            array_merge(
                [
                    'seed_master'         => true,
                    'minimal_schema'      => defined('AURAGOLD_BRANCH_MINIMAL_SCHEMA') && AURAGOLD_BRANCH_MINIMAL_SCHEMA,
                    'branch_mysql_user'   => $db_users_req,
                    'branch_mysql_pass'   => $db_pass_req,
                ],
                $provisionOpts
            )
        );
        if (!empty($retryProv['ok'])) {
            $dbProv = [
                'ok'          => true,
                'message'     => $retryProv['message'] ?? 'Branch database provisioned from registry.',
                'provisioned' => true,
                'skipped'     => false,
            ];
            $dedicatedProvisioned = true;
        } else {
            error_log('AuraGold save_main_branch: empty branch DB after first pass; retry failed: ' . ($retryProv['message'] ?? ''));
            $dbProv['ok']        = false;
            $dbProv['provisioned'] = false;
            $dbProv['message'] = trim((string) ($dbProv['message'] ?? '') . ' ' . ($retryProv['message'] ?? 'Provisioning retry failed.'));
        }
    }
}

if ($newId > 0 && function_exists('auragold_seed_bill_series_for_new_branch') && function_exists('auragold_mysqli_connect_branch_or_registry')) {
    $dConn = auragold_mysqli_connect_branch_or_registry(
        defined('DB_HOST') ? DB_HOST : 'localhost',
        $db_name_req,
        $db_users_req,
        $db_pass_req
    );
    if ($dConn) {
        mysqli_set_charset($dConn, 'utf8mb4');
        $prevConn        = $GLOBALS['conn'] ?? null;
        $GLOBALS['conn'] = $dConn;
        auragold_seed_bill_series_for_new_branch($dConn, $newId, (int) ($_SESSION['Admin']['id'] ?? 0));
        $GLOBALS['conn'] = $prevConn;
        mysqli_close($dConn);
    } else {
        error_log('AuraGold save_main_branch: could not connect for bill series seed: ' . mysqli_connect_error());
    }
}

$userSeed = ['ok' => true, 'message' => ''];
if ($newId > 0 && function_exists('auragold_mysqli_connect_branch_or_registry')) {
    $dConn = auragold_mysqli_connect_branch_or_registry(
        defined('DB_HOST') ? DB_HOST : 'localhost',
        $db_name_req,
        $db_users_req,
        $db_pass_req
    );
    if ($dConn) {
        mysqli_set_charset($dConn, 'utf8mb4');
        $userSeed = auragold_main_branch_reset_tbl_users_default_admin(
            $conn_master,
            $dConn,
            (int) $newId,
            [
                'branch_name' => $branch_name,
                'contact1'    => $contact1,
                'contact2'    => $contact2,
                'mail'        => $mail,
                'address'     => $address,
                'country'     => $country,
                'status'      => $status,
            ]
        );
        mysqli_close($dConn);
        if (empty($userSeed['ok'])) {
            error_log('AuraGold save_main_branch: default admin seed failed: ' . ($userSeed['message'] ?? ''));
        }
    } else {
        $userSeed = ['ok' => false, 'message' => 'Could not connect to the new branch database to create the default admin user.'];
        error_log('AuraGold save_main_branch: could not connect for tbl_users seed: ' . mysqli_connect_error());
    }
}

if ($newId > 0
    && $db_name_req !== ''
    && defined('DB_NAME')
    && strcasecmp($db_name_req, (string) DB_NAME) !== 0
    && function_exists('auragold_seed_metal_and_customer_types_for_new_branch')
    && function_exists('auragold_mysqli_connect_branch_or_registry')) {
    $mConn = auragold_mysqli_connect_branch_or_registry(
        defined('DB_HOST') ? DB_HOST : 'localhost',
        $db_name_req,
        $db_users_req,
        $db_pass_req
    );
    if ($mConn) {
        mysqli_set_charset($mConn, 'utf8mb4');
        auragold_seed_metal_and_customer_types_for_new_branch($mConn, (int) $newId);
        mysqli_close($mConn);
    } else {
        error_log('AuraGold save_main_branch: could not connect for metal / customer type seed: ' . mysqli_connect_error());
    }
}

$baseMsg = 'Main branch created with a dedicated database.';
if (!empty($dbProv['skipped'])) {
    $baseMsg .= ' ' . ($dbProv['message'] ?? '');
} elseif (!empty($dbProv['ok']) && $dedicatedProvisioned) {
    $baseMsg .= ' ' . ($dbProv['message'] ?? '');
} elseif (!empty($dbProv['ok'])) {
    $baseMsg .= ' ' . ($dbProv['message'] ?? '');
} else {
    $baseMsg .= ' Warning: ' . ($dbProv['message'] ?? 'Database setup issue.');
}

if ($newId > 0 && empty($userSeed['ok'])) {
    $baseMsg .= ' ' . ($userSeed['message'] ?? 'Could not create the default admin user in the new database.');
}

$portalProv = ['ok' => true, 'message' => '', 'url_hint' => ''];
if ($newId > 0 && function_exists('auragold_branch_portal_provision')) {
    $portalProv = auragold_branch_portal_provision($conn_master, (int) $newId);
    if (!empty($portalProv['ok']) && !empty($portalProv['url_hint'])) {
        $baseMsg .= ' Portal: ' . ($portalProv['url_hint'] ?? '');
    } elseif (empty($portalProv['ok'])) {
        error_log('AuraGold save_main_branch: portal folder: ' . ($portalProv['message'] ?? ''));
        $baseMsg .= ' (Portal folder was not created: ' . ($portalProv['message'] ?? 'error') . ')';
    }
}

$out = [
    'ok'        => true,
    'message'   => $baseMsg,
    'branch_id' => $newId,
    'created_db' => [
        'db_name'     => $creds['db_name'],
        'db_users'    => $creds['db_users'],
        'db_password' => $creds['db_password'],
    ],
    'db_setup'  => [
        'ok'          => !empty($dbProv['ok']),
        'skipped'     => !empty($dbProv['skipped']),
        'provisioned' => $dedicatedProvisioned,
        'detail'      => $dbProv['message'] ?? '',
    ],
    'user_seeded' => !empty($userSeed['ok']),
    'portal'      => [
        'ok'       => !empty($portalProv['ok']),
        'message'  => $portalProv['message'] ?? '',
        'path'     => $portalProv['path'] ?? '',
        'slug'     => $portalProv['slug'] ?? '',
        'url_hint' => $portalProv['url_hint'] ?? '',
    ],
];
echo auragold_save_branch_json_encode($out);

} catch (Throwable $e) {
    error_log(
        'AuraGold save_main_branch: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()
        . "\n" . $e->getTraceAsString()
    );
    http_response_code(500);
    echo auragold_save_branch_json_encode([
        'ok'      => false,
        'message' => $e->getMessage(),
    ]);
}
