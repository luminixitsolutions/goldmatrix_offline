<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/session_login_type.php';
require_once __DIR__ . '/includes/login_authenticate.php';
require_once __DIR__ . '/includes/branch_profile_schema.php';
require_once __DIR__ . '/includes/branch_working_context.php';
require_once __DIR__ . '/includes/international-dial-codes.php';
require_once __DIR__ . '/includes/location-helpers.php';
require_once __DIR__ . '/includes/auragold_user_menu_preferences.php';
require_once __DIR__ . '/includes/auragold_user_login_dashboard.php';
require_once __DIR__ . '/includes/auragold_user_page_loader.php';
require_once __DIR__ . '/includes/auragold_api_shop_connection.php';
require_once __DIR__ . '/includes/auragold_access_token.php';

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0 || empty($_SESSION['Admin'])) {
    header('Location: index.php');
    exit;
}

auragold_ensure_tbl_branches_profile_columns($conn_master);
$auragold_profile_user_link = (isset($conn) && $conn instanceof mysqli) ? $conn : $conn_master;
auragold_ensure_tbl_users_profile_photo_column($auragold_profile_user_link);
auragold_ensure_tbl_users_menu_style_column($auragold_profile_user_link);
auragold_ensure_tbl_users_login_dashboard_column($auragold_profile_user_link);
auragold_ensure_tbl_users_page_loader_column($auragold_profile_user_link);

$uid = (int) $_SESSION['user_id'];
$userRow = getRecord('SELECT * FROM tbl_users WHERE id = ' . $uid . ' LIMIT 1');
if ((!$userRow || !is_array($userRow)) && function_exists('getRecordMaster')) {
    $userRow = getRecordMaster('SELECT * FROM tbl_users WHERE id = ' . $uid . ' LIMIT 1');
}
if (!$userRow || !is_array($userRow)) {
    header('Location: index.php');
    exit;
}

$targetBid = auragold_my_profile_target_branch_id();

// Shop access token (same as /api/shops.php) — used by CRM + customers API.
// Kept separate from the per-user tbl_users.access_token so the two never mix.
$shopAccessToken = '';
if (function_exists('auragold_bootstrap_session_shop_access_token')) {
    $shopAccessToken = auragold_bootstrap_session_shop_access_token();
}
if ($shopAccessToken === '') {
    $shopAccessToken = trim((string) ($_SESSION['shop_access_token'] ?? $_SESSION['Admin']['shop_access_token'] ?? ''));
}
if ($shopAccessToken === '' && $targetBid > 0 && function_exists('auragold_ensure_shop_access_token')) {
    // Sub-branches share the main branch token, same as /api/shops.php.
    $mainBidRow = getRecordMaster('SELECT main_branch_id FROM tbl_branches WHERE id = ' . (int) $targetBid . ' LIMIT 1');
    $mainBid    = is_array($mainBidRow) ? (int) ($mainBidRow['main_branch_id'] ?? 0) : 0;
    $shopAccessToken = auragold_ensure_shop_access_token($conn_master, $mainBid > 0 ? $mainBid : $targetBid);
}

$branch_profile_hint = '';
$branchRow           = null;

if ($targetBid > 0) {
    $branchRow = getRecordMaster('SELECT * FROM tbl_branches WHERE id = ' . $targetBid . ' LIMIT 1');
    if (!$branchRow) {
        $branch_profile_hint = 'Branch record was not found. Open Branches to pick a valid branch for shop settings.';
    } elseif (!auragold_can_user_open_branch_row($branchRow)) {
        $branch_profile_hint = 'You are not allowed to edit this branch profile.';
        $branchRow           = null;
    }
} else {
    $branch_profile_hint = 'To edit shop name, GST, logo and bank details, open Branches and set a working branch, or select a branch at login.';
}

$toast = null;
if (!empty($_SESSION['auragold_toast']) && is_array($_SESSION['auragold_toast'])) {
    $toast = $_SESSION['auragold_toast'];
    unset($_SESSION['auragold_toast']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['profile_form'] ?? '') === 'user') {
    $fail = function (string $msg) {
        $_SESSION['auragold_toast'] = ['type' => 'danger', 'message' => $msg];
        header('Location: my-profile.php');
        exit;
    };

    $fname = trim((string) ($_POST['Fname'] ?? ''));
    $lname = trim((string) ($_POST['Lname'] ?? ''));
    $phone = trim((string) ($_POST['Phone'] ?? ''));
    $email = trim((string) ($_POST['EmailId'] ?? ''));
    $menu_style = auragold_normalize_menu_style($_POST['menu_style'] ?? 'horizontal');
    $page_loader = auragold_normalize_page_loader($_POST['page_loader'] ?? 'on');
    $login_dashboard = auragold_normalize_login_dashboard($_POST['login_dashboard'] ?? '');
    $allowed_login_dashboards = auragold_login_dashboard_select_options_for_user();
    if (!array_key_exists($login_dashboard, $allowed_login_dashboards)) {
        $fail('Invalid after-login dashboard selection.');
    }

    if (strlen($fname) > 100 || strlen($lname) > 100) {
        $fail('First or last name is too long.');
    }
    if (strlen($phone) > 20) {
        $fail('Phone is too long.');
    }
    if (strlen($email) > 100) {
        $fail('Email is too long.');
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fail('Please enter a valid email address.');
    }

    $user_photo_path = '';
    foreach ($userRow as $uk => $uv) {
        if (strcasecmp((string) $uk, 'profile_photo') === 0) {
            $user_photo_path = trim((string) $uv);
            break;
        }
    }

    if (!empty($_POST['remove_user_photo']) && (string) $_POST['remove_user_photo'] === '1') {
        if ($user_photo_path !== '' && is_file(__DIR__ . '/' . $user_photo_path)) {
            @unlink(__DIR__ . '/' . $user_photo_path);
        }
        $user_photo_path = '';
    }

    if (!empty($_FILES['user_profile_photo']['name'])
        && (int) ($_FILES['user_profile_photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $err = (int) ($_FILES['user_profile_photo']['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            $fail('Profile photo upload failed. Try a smaller image.');
        }
        $tmp = $_FILES['user_profile_photo']['tmp_name'] ?? '';
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            $fail('Invalid profile photo upload.');
        }
        $fi = @getimagesize($tmp);
        if ($fi === false) {
            $fail('Profile photo must be a valid image (JPG, PNG, GIF, or WebP).');
        }
        $allowed = [
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG  => 'png',
            IMAGETYPE_GIF  => 'gif',
            IMAGETYPE_WEBP => 'webp',
        ];
        $itype = (int) ($fi[2] ?? 0);
        if (!isset($allowed[$itype])) {
            $fail('Profile photo must be JPG, PNG, GIF, or WebP.');
        }
        $maxBytes = 2 * 1024 * 1024;
        $size = (int) ($_FILES['user_profile_photo']['size'] ?? 0);
        if ($size <= 0 || $size > $maxBytes) {
            $fail('Profile photo must be 2 MB or smaller.');
        }
        $uploadDir = __DIR__ . '/uploads/user_profiles';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }
        if ($user_photo_path !== '' && is_file(__DIR__ . '/' . $user_photo_path)) {
            @unlink(__DIR__ . '/' . $user_photo_path);
        }
        $ext            = $allowed[$itype];
        $photo_basename = (int) $uid . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dest           = $uploadDir . '/' . $photo_basename;
        if (!@move_uploaded_file($tmp, $dest)) {
            $fail('Could not save profile photo.');
        }
        $user_photo_path = 'uploads/user_profiles/' . $photo_basename;
    }

    $photoSql = ($user_photo_path === '') ? 'NULL' : "'" . esc($user_photo_path) . "'";

    $sqlUser = "
        UPDATE tbl_users SET
            Fname = '" . esc($fname) . "',
            Lname = '" . esc($lname) . "',
            Phone = " . ($phone === '' ? 'NULL' : "'" . esc($phone) . "'") . ",
            EmailId = " . ($email === '' ? 'NULL' : "'" . esc($email) . "'") . ",
            menu_style = '" . esc($menu_style) . "',
            page_loader = '" . esc($page_loader) . "',
            login_dashboard = '" . esc($login_dashboard) . "',
            profile_photo = " . $photoSql . ",
            ModifiedBy = " . (int) $uid . ",
            ModifiedDate = NOW()
        WHERE id = " . (int) $uid . "
        LIMIT 1
    ";
    $okUser = mysqli_query($auragold_profile_user_link, $sqlUser);
    if (!$okUser) {
        $fail('Could not save your profile. Please try again.');
    }

    $userRow = getRecord('SELECT * FROM tbl_users WHERE id = ' . $uid . ' LIMIT 1');
    if ((!$userRow || !is_array($userRow)) && function_exists('getRecordMaster')) {
        $userRow = getRecordMaster('SELECT * FROM tbl_users WHERE id = ' . $uid . ' LIMIT 1');
    }
    $_SESSION['name'] = trim($fname . ' ' . $lname);
    if (!empty($_SESSION['Admin']) && is_array($_SESSION['Admin'])) {
        $_SESSION['Admin']['Fname'] = $fname;
        $_SESSION['Admin']['Lname'] = $lname;
        $_SESSION['Admin']['Phone'] = $phone;
        $_SESSION['Admin']['EmailId'] = $email;
        $_SESSION['Admin']['profile_photo'] = $user_photo_path;
        $_SESSION['Admin']['menu_style'] = $menu_style;
        $_SESSION['Admin']['page_loader'] = $page_loader;
        $_SESSION['Admin']['login_dashboard'] = $login_dashboard;
    }
    auragold_sync_user_menu_style_in_session($menu_style);
    auragold_sync_user_page_loader_in_session($page_loader);
    auragold_sync_user_login_dashboard_in_session($login_dashboard);

    // Keep /api/shops.php in sync when branch contact fields were never filled in shop profile.
    if ($targetBid > 0 && isset($conn_master) && $conn_master instanceof mysqli) {
        $mainBid = $targetBid;
        $mainBidRow = getRecordMaster('SELECT main_branch_id FROM tbl_branches WHERE id = ' . (int) $targetBid . ' LIMIT 1');
        if (is_array($mainBidRow) && (int) ($mainBidRow['main_branch_id'] ?? 0) > 0) {
            $mainBid = (int) $mainBidRow['main_branch_id'];
        }
        $branchContact = getRecordMaster(
            'SELECT email, phone FROM tbl_branches WHERE id = ' . (int) $mainBid . ' LIMIT 1'
        );
        if (is_array($branchContact)) {
            $sync = [];
            if ($email !== '' && trim((string) ($branchContact['email'] ?? '')) === '') {
                $sync[] = 'email = ' . ($email === '' ? 'NULL' : "'" . esc($email) . "'");
            }
            if ($phone !== '' && trim((string) ($branchContact['phone'] ?? '')) === '') {
                $sync[] = 'phone = ' . ($phone === '' ? 'NULL' : "'" . esc($phone) . "'");
            }
            if (!empty($sync)) {
                @mysqli_query(
                    $conn_master,
                    'UPDATE tbl_branches SET ' . implode(', ', $sync) . ' WHERE id = ' . (int) $mainBid . ' LIMIT 1'
                );
            }
        }
    }

    $_SESSION['auragold_toast'] = ['type' => 'success', 'message' => 'Your profile was saved.'];
    header('Location: my-profile.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['profile_form'] ?? '') === 'branch' && $branchRow) {
    $fail = function (string $msg) {
        $_SESSION['auragold_toast'] = ['type' => 'danger', 'message' => $msg];
        header('Location: my-profile.php');
        exit;
    };

    $name = trim((string) ($_POST['shop_name'] ?? ''));
    if ($name === '') {
        $fail('Shop name is required.');
    }
    if (strlen($name) > 255) {
        $fail('Shop name is too long.');
    }

    $address           = trim((string) ($_POST['address'] ?? ''));
    $phone             = trim((string) ($_POST['phone'] ?? ''));
    $landline          = trim((string) ($_POST['landline'] ?? ''));
    $email             = trim((string) ($_POST['email'] ?? ''));
    $gst_no            = trim((string) ($_POST['gst_no'] ?? ''));
    $pan_no            = trim((string) ($_POST['pan_no'] ?? ''));
    $business_license_no = trim((string) ($_POST['business_license_no'] ?? ''));
    $authorized_person = trim((string) ($_POST['authorized_person'] ?? ''));
    $location_area     = trim((string) ($_POST['location_area'] ?? ''));
    $website           = trim((string) ($_POST['website'] ?? ''));
    $cr_no             = trim((string) ($_POST['cr_no'] ?? ''));
    $instagram         = trim((string) ($_POST['instagram'] ?? ''));
    $bank_name         = trim((string) ($_POST['bank_name'] ?? ''));
    $bank_account_no   = trim((string) ($_POST['bank_account_no'] ?? ''));
    $bank_ifsc         = trim((string) ($_POST['bank_ifsc'] ?? ''));
    $bank_branch       = trim((string) ($_POST['bank_branch'] ?? ''));
    $invoice_terms     = trim((string) ($_POST['invoice_terms'] ?? ''));

    if (strlen($business_license_no) > 100) {
        $fail('Business license number is too long.');
    }
    if (strlen($website) > 255) {
        $fail('Website is too long.');
    }
    if (strlen($cr_no) > 64) {
        $fail('CR No. is too long.');
    }
    if (strlen($instagram) > 120) {
        $fail('Instagram handle is too long.');
    }
    if (strlen($landline) > 50) {
        $fail('Landline number is too long.');
    }

    $profile_country_id = (int) ($_POST['profile_country_id'] ?? 0);
    $profile_state_id   = (int) ($_POST['profile_state_id'] ?? 0);
    $profile_city_id    = (int) ($_POST['profile_city_id'] ?? 0);
    $profile_phone_country_code = trim((string) ($_POST['profile_phone_country_code'] ?? ''));
    if ($profile_phone_country_code === '') {
        $fail('Country code is required.');
    }
    if (strlen($profile_phone_country_code) > 10 || !preg_match('/^\d{1,10}$/', $profile_phone_country_code)) {
        $fail('Invalid phone country code.');
    }
    if ($profile_country_id <= 0) {
        $fail('Country is required.');
    }
    if ($profile_state_id <= 0) {
        $fail('State is required.');
    }
    if ($profile_city_id <= 0) {
        $fail('City is required.');
    }

    // Validate country / state / city relationship
    if (!empty($conn)) {
        require_once __DIR__ . '/includes/location-helpers.php';
        if (function_exists('auragold_bootstrap_location_data')) {
            auragold_bootstrap_location_data($conn);
        }
        $cr = getRecord('SELECT id FROM tbl_countries WHERE id = ' . (int) $profile_country_id . ' AND status = 1 LIMIT 1');
        if (!$cr) {
            $fail('Selected country is invalid.');
        }
        $sr = getRecord(
            'SELECT id, country_id FROM tbl_states WHERE id = ' . (int) $profile_state_id . ' AND status = 1 LIMIT 1'
        );
        if (!$sr || (int) ($sr['country_id'] ?? 0) !== $profile_country_id) {
            $fail('Selected state is invalid or does not belong to the selected country.');
        }
        $cir = getRecord(
            'SELECT id, state_id, name FROM tbl_cities WHERE id = ' . (int) $profile_city_id . ' AND status = 1 LIMIT 1'
        );
        if (!$cir || (int) ($cir['state_id'] ?? 0) !== $profile_state_id) {
            $fail('Selected city is invalid or does not belong to the selected state.');
        }
        $cityName = trim((string) ($cir['name'] ?? ''));
        if ($cityName !== '' && $location_area === '') {
            $location_area = $cityName;
        }
    }

    $profile_base_currency_id = (int) ($_POST['profile_base_currency_id'] ?? 0);
    if ($profile_base_currency_id < 0) {
        $profile_base_currency_id = 0;
    }
    if ($profile_base_currency_id > 0) {
        $curSql = 'SELECT id FROM tbl_currency WHERE status = 1 AND id = ' . (int) $profile_base_currency_id;
        if (isset($conn) && $conn instanceof mysqli && function_exists('auragold_tbl_has_column')
            && auragold_tbl_has_column($conn, 'tbl_currency', 'branch_id') && (int) $targetBid > 0) {
            $curSql .= ' AND branch_id = ' . (int) $targetBid;
        }
        $curSql .= ' LIMIT 1';
        $curOk = getList($curSql);
        if (!is_array($curOk) || !isset($curOk[0])) {
            $fail('Invalid base currency.');
        }
    }

    $timezone = function_exists('auragold_timezone_normalize')
        ? auragold_timezone_normalize(trim((string) ($_POST['timezone'] ?? '')))
        : 'Asia/Kolkata';
    if (function_exists('auragold_timezone_options')) {
        $tzOpts = auragold_timezone_options();
        if (!isset($tzOpts[$timezone])) {
            // Allow any valid IANA id even if not in curated list
            try {
                new DateTimeZone($timezone);
            } catch (Exception $e) {
                $fail('Invalid timezone.');
            }
        }
    }

    $logo_path = trim((string) ($branchRow['logo_path'] ?? ''));
    if (!empty($_POST['remove_logo']) && $_POST['remove_logo'] === '1') {
        if ($logo_path !== '' && is_file(__DIR__ . '/' . $logo_path)) {
            @unlink(__DIR__ . '/' . $logo_path);
        }
        $logo_path = '';
    }

    if (!empty($_FILES['logo_file']['name']) && (int) ($_FILES['logo_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $err = (int) ($_FILES['logo_file']['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            $fail('Logo upload failed. Please try a smaller image.');
        }
        $tmp = $_FILES['logo_file']['tmp_name'] ?? '';
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            $fail('Invalid logo upload.');
        }
        $fi = @getimagesize($tmp);
        if ($fi === false) {
            $fail('Logo must be a valid image (JPG, PNG, GIF, or WebP).');
        }
        $allowed = [
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG  => 'png',
            IMAGETYPE_GIF  => 'gif',
            IMAGETYPE_WEBP => 'webp',
        ];
        $itype = (int) ($fi[2] ?? 0);
        if (!isset($allowed[$itype])) {
            $fail('Logo must be JPG, PNG, GIF, or WebP.');
        }
        $maxBytes = 2 * 1024 * 1024;
        $size = (int) ($_FILES['logo_file']['size'] ?? 0);
        if ($size <= 0 || $size > $maxBytes) {
            $fail('Logo must be 2 MB or smaller.');
        }

        $uploadDir = __DIR__ . '/uploads/branch_logos';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0755, true);
        }
        if ($logo_path !== '' && is_file(__DIR__ . '/' . $logo_path)) {
            @unlink(__DIR__ . '/' . $logo_path);
        }
        $ext   = $allowed[$itype];
        $fname = $targetBid . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dest  = $uploadDir . '/' . $fname;
        if (!@move_uploaded_file($tmp, $dest)) {
            $fail('Could not save logo file.');
        }
        $logo_path = 'uploads/branch_logos/' . $fname;
    }

    $sql = "
        UPDATE tbl_branches SET
            name = '" . esc($name) . "',
            address = " . ($address === '' ? 'NULL' : "'" . esc($address) . "'") . ",
            phone = " . ($phone === '' ? 'NULL' : "'" . esc($phone) . "'") . ",
            landline = " . ($landline === '' ? 'NULL' : "'" . esc($landline) . "'") . ",
            email = " . ($email === '' ? 'NULL' : "'" . esc($email) . "'") . ",
            gst_no = " . ($gst_no === '' ? 'NULL' : "'" . esc($gst_no) . "'") . ",
            pan_no = " . ($pan_no === '' ? 'NULL' : "'" . esc($pan_no) . "'") . ",
            business_license_no = " . ($business_license_no === '' ? 'NULL' : "'" . esc($business_license_no) . "'") . ",
            authorized_person = " . ($authorized_person === '' ? 'NULL' : "'" . esc($authorized_person) . "'") . ",
            location_area = " . ($location_area === '' ? 'NULL' : "'" . esc($location_area) . "'") . ",
            website = " . ($website === '' ? 'NULL' : "'" . esc($website) . "'") . ",
            cr_no = " . ($cr_no === '' ? 'NULL' : "'" . esc($cr_no) . "'") . ",
            instagram = " . ($instagram === '' ? 'NULL' : "'" . esc($instagram) . "'") . ",
            bank_name = " . ($bank_name === '' ? 'NULL' : "'" . esc($bank_name) . "'") . ",
            bank_account_no = " . ($bank_account_no === '' ? 'NULL' : "'" . esc($bank_account_no) . "'") . ",
            bank_ifsc = " . ($bank_ifsc === '' ? 'NULL' : "'" . esc($bank_ifsc) . "'") . ",
            bank_branch = " . ($bank_branch === '' ? 'NULL' : "'" . esc($bank_branch) . "'") . ",
            invoice_terms = " . ($invoice_terms === '' ? 'NULL' : "'" . esc($invoice_terms) . "'") . ",
            logo_path = " . ($logo_path === '' ? 'NULL' : "'" . esc($logo_path) . "'") . ",
            profile_country_id = " . ($profile_country_id > 0 ? (int) $profile_country_id : 'NULL') . ",
            profile_state_id = " . ($profile_state_id > 0 ? (int) $profile_state_id : 'NULL') . ",
            profile_city_id = " . ($profile_city_id > 0 ? (int) $profile_city_id : 'NULL') . ",
            profile_phone_country_code = '" . esc($profile_phone_country_code) . "',
            profile_base_currency_id = " . ($profile_base_currency_id > 0 ? (int) $profile_base_currency_id : 'NULL') . ",
            timezone = '" . esc($timezone) . "'
        WHERE id = " . (int) $targetBid . " LIMIT 1
    ";

    $ok = mysqli_query($conn_master, $sql);
    if (!$ok) {
        $fail('Could not save profile. Check database columns (run sql/alter_tbl_branches_profile.sql if needed).');
    }

    if (!empty($_SESSION['working_branch_id']) && (int) $_SESSION['working_branch_id'] === $targetBid) {
        $_SESSION['working_branch_name'] = $name;
    }
    // Refresh session TZ when editing the active branch
    $effBid = function_exists('auragold_effective_branch_id') ? (int) auragold_effective_branch_id() : 0;
    if ($effBid === (int) $targetBid || (int) ($_SESSION['branch_id'] ?? 0) === (int) $targetBid) {
        $_SESSION['auragold_timezone'] = $timezone;
        if (function_exists('date_default_timezone_set')) {
            @date_default_timezone_set($timezone);
        }
    }

    $_SESSION['auragold_toast'] = ['type' => 'success', 'message' => 'Shop profile saved.'];
    header('Location: my-profile.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $userRow = getRecord('SELECT * FROM tbl_users WHERE id = ' . $uid . ' LIMIT 1');
    if ((!$userRow || !is_array($userRow)) && function_exists('getRecordMaster')) {
        $userRow = getRecordMaster('SELECT * FROM tbl_users WHERE id = ' . $uid . ' LIMIT 1');
    }
    if ($branchRow) {
        $branchRow = getRecordMaster('SELECT * FROM tbl_branches WHERE id = ' . $targetBid . ' LIMIT 1');
    }
}

$DASHBOARD_PAGE_TITLE = 'My Profile';
$DASHBOARD_EXTRA_CSS   = <<<'CSS'
<style>
    :root { --mp-navy: #11294b; --mp-navy-dark: #0d1f38; }
    .mp-wrap { max-width: 920px; margin: 0 auto; }
    .mp-card {
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 8px 22px rgba(0,0,0,.07);
        border: 1px solid #ece9ff;
        padding: 22px 26px 26px;
        margin-bottom: 18px;
    }
    .mp-card h2 {
        font-size: 15px;
        font-weight: 700;
        color: var(--mp-navy);
        margin: 0 0 16px;
        padding-bottom: 10px;
        border-bottom: 1px solid #e2e8f0;
    }
    .mp-card label { font-weight: 600; color: #334155; font-size: 13px; margin-bottom: 6px; }
    .mp-card .form-control, .mp-card textarea {
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        font-size: 14px;
    }
    .mp-card textarea { min-height: 100px; resize: vertical; }
    .mp-btn-save {
        background: linear-gradient(135deg, var(--mp-navy) 0%, var(--mp-navy-dark) 100%);
        border: none;
        color: #fff;
        font-weight: 600;
        padding: 10px 24px;
        border-radius: 8px;
    }
    .mp-btn-save:hover { color: #fff; opacity: .95; }
    .mp-logo-preview {
        max-height: 72px;
        max-width: 220px;
        object-fit: contain;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 6px;
        background: #fafafa;
    }
    .mp-hint { font-size: 12px; color: #64748b; margin-top: 4px; }
</style>
CSS;

require __DIR__ . '/includes/dashboard_shell_top.php';
?>

<div class="mp-wrap">
    <h1 class="dash-page-title">My Profile</h1>
  <br>

    <?php if ($toast): ?>
        <div class="alert alert-<?php echo $toast['type'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert" style="font-size:14px;">
            <?php echo htmlspecialchars((string) ($toast['message'] ?? '')); ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>
    <?php endif; ?>

    <?php
    $ur = $userRow;
    $uFname = trim((string) ($ur['Fname'] ?? $ur['fname'] ?? ''));
    $uLname = trim((string) ($ur['Lname'] ?? $ur['lname'] ?? ''));
    $uUser  = trim((string) ($ur['Username'] ?? $ur['username'] ?? ''));
    $uPhone = trim((string) ($ur['Phone'] ?? $ur['phone'] ?? ''));
    $uEmail = trim((string) ($ur['EmailId'] ?? $ur['email'] ?? $ur['Email'] ?? ''));
    $uRole  = trim((string) ($ur['user_role'] ?? ''));
    if ($uRole === '') {
        $uRole = '—';
    }
    $uPhotoPath = trim((string) ($ur['profile_photo'] ?? ''));
    $uPhotoUrl  = '';
    if ($uPhotoPath !== '' && is_file(__DIR__ . '/' . $uPhotoPath)) {
        $uPhotoUrl = $uPhotoPath . '?v=' . (int) @filemtime(__DIR__ . '/' . $uPhotoPath);
    }
    $uMenuStyle = auragold_normalize_menu_style($ur['menu_style'] ?? auragold_get_user_menu_style($uid));
    $uPageLoader = auragold_normalize_page_loader($ur['page_loader'] ?? auragold_get_user_page_loader($uid));
    // Prefer helper (username + operational DB); empty string on $ur must not hide a saved value.
    $uLoginDashboard = auragold_get_user_login_dashboard($uid);
    if ($uLoginDashboard === '') {
        $uLoginDashboard = auragold_normalize_login_dashboard($ur['login_dashboard'] ?? '');
    }
    $uLoginDashboardOptions = auragold_login_dashboard_select_options_for_user();
    $uAccessToken = trim((string) $shopAccessToken);
    ?>
        <form method="post" action="my-profile.php" enctype="multipart/form-data" autocomplete="on" class="mb-3">
            <input type="hidden" name="profile_form" value="user">
            <div class="mp-card">
                <h2>Your account</h2>
                <div class="form-group mb-3">
                    <label>Profile photo</label>
                    <div class="d-flex align-items-center flex-wrap" style="gap:12px;">
                        <?php if ($uPhotoUrl !== ''): ?>
                            <img src="<?php echo htmlspecialchars($uPhotoUrl); ?>" alt="" class="mp-user-photo-preview" width="80" height="80" style="object-fit:cover;border-radius:50%;border:2px solid #e2e8f0;">
                        <?php else: ?>
                            <div class="mp-user-photo-placeholder" style="width:80px;height:80px;border-radius:50%;background:#e2e8f0;display:flex;align-items:center;justify-content:center;color:#94a3b8;font-size:12px;">No photo</div>
                        <?php endif; ?>
                        <div class="flex-grow-1" style="min-width:200px;">
                            <input type="file" class="form-control-file" id="user_profile_photo" name="user_profile_photo" accept="image/jpeg,image/png,image/gif,image/webp">
                            <div class="mp-hint">JPG, PNG, GIF or WebP, up to 2 MB. Shown in the header profile icon.</div>
                            <?php if ($uPhotoUrl !== ''): ?>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" id="remove_user_photo" name="remove_user_photo" value="1">
                                    <label class="form-check-label" for="remove_user_photo">Remove current photo</label>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6 mb-3">
                        <label for="mp_Fname">First name</label>
                        <input type="text" class="form-control" id="mp_Fname" name="Fname" maxlength="100"
                               value="<?php echo htmlspecialchars($uFname); ?>">
                    </div>
                    <div class="form-group col-md-6 mb-3">
                        <label for="mp_Lname">Last name</label>
                        <input type="text" class="form-control" id="mp_Lname" name="Lname" maxlength="100"
                               value="<?php echo htmlspecialchars($uLname); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6 mb-3">
                        <label for="mp_Username">Login username</label>
                        <input type="text" class="form-control" id="mp_Username" maxlength="100"
                               value="<?php echo htmlspecialchars($uUser); ?>" readonly style="background:#f8fafc;">
                    </div>
                    <div class="form-group col-md-6 mb-3">
                        <label for="mp_role">Role</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($uRole); ?>" readonly style="background:#f8fafc;">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6 mb-3">
                        <label for="mp_Phone">Phone</label>
                        <input type="text" class="form-control" id="mp_Phone" name="Phone" maxlength="20"
                               value="<?php echo htmlspecialchars($uPhone); ?>">
                    </div>
                    <div class="form-group col-md-6 mb-3">
                        <label for="mp_EmailId">Email</label>
                        <input type="email" class="form-control" id="mp_EmailId" name="EmailId" maxlength="100"
                               value="<?php echo htmlspecialchars($uEmail); ?>">
                    </div>
                </div>
                <div class="form-group mb-3">
                    <label>Menu style</label>
                    <div class="d-flex flex-wrap" style="gap:16px;">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="menu_style" id="mp_menu_horizontal" value="horizontal"<?php echo $uMenuStyle === 'horizontal' ? ' checked' : ''; ?>>
                            <label class="form-check-label" for="mp_menu_horizontal">Horizontal (top bar)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="menu_style" id="mp_menu_vertical" value="vertical"<?php echo $uMenuStyle === 'vertical' ? ' checked' : ''; ?>>
                            <label class="form-check-label" for="mp_menu_vertical">Vertical (left sidebar)</label>
                        </div>
                    </div>
                    <div class="mp-hint">Choose how the main navigation appears. Vertical mode opens submenus below each item (like Region in Set Software). Use the tab on the menu edge to hide or show the sidebar.</div>
                </div>
                <div class="form-group mb-3">
                    <label>Page loader</label>
                    <div class="d-flex flex-wrap" style="gap:16px;">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="page_loader" id="mp_page_loader_on" value="on"<?php echo $uPageLoader === 'on' ? ' checked' : ''; ?>>
                            <label class="form-check-label" for="mp_page_loader_on">On</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="page_loader" id="mp_page_loader_off" value="off"<?php echo $uPageLoader === 'off' ? ' checked' : ''; ?>>
                            <label class="form-check-label" for="mp_page_loader_off">Off</label>
                        </div>
                    </div>
                    <div class="mp-hint">When On, the GoldMatrix splash screen appears while pages load. When Off, pages open without the loader.</div>
                </div>
                <div class="form-group mb-3">
                    <label for="mp_login_dashboard">After login, open</label>
                    <select class="form-control" id="mp_login_dashboard" name="login_dashboard" style="max-width:420px;">
                        <?php foreach ($uLoginDashboardOptions as $ldKey => $ldLabel): ?>
                            <option value="<?php echo htmlspecialchars($ldKey); ?>"<?php echo $uLoginDashboard === $ldKey ? ' selected' : ''; ?>>
                                <?php echo htmlspecialchars($ldLabel); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="mp-hint">Choose which dashboard page opens right after you sign in. Only dashboards you can access are listed.</div>
                </div>
                <div class="form-group mb-3">
                    <label for="mp_access_token">Shop access token</label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="mp_access_token" maxlength="64"
                               value="<?php echo htmlspecialchars($uAccessToken); ?>"
                               readonly style="background:#f8fafc;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:13px;">
                        <?php if ($uAccessToken !== ''): ?>
                        <div class="input-group-append">
                            <button type="button" class="btn btn-outline-secondary" id="mp_copy_access_token" title="Copy token" style="border-radius:0 8px 8px 0;">Copy</button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="mp-hint">Shop token for CRM and APIs (same as /api/shops.php). Created once and not changed when you save your profile.</div>
                </div>
                <button type="submit" class="btn mp-btn-save">Save profile</button>
            </div>
        </form>
        <script>
        (function () {
            var btn = document.getElementById('mp_copy_access_token');
            var inp = document.getElementById('mp_access_token');
            if (!btn || !inp) return;
            btn.addEventListener('click', function () {
                var val = inp.value || '';
                if (!val) return;
                var done = function () {
                    var prev = btn.textContent;
                    btn.textContent = 'Copied';
                    setTimeout(function () { btn.textContent = prev; }, 1500);
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(val).then(done).catch(function () {
                        inp.select();
                        try { document.execCommand('copy'); done(); } catch (e) {}
                    });
                } else {
                    inp.select();
                    try { document.execCommand('copy'); done(); } catch (e) {}
                }
            });
        })();
        </script>

    <?php if ($branch_profile_hint !== '' && !$branchRow): ?>
        <div class="mp-card">
            <div class="alert alert-info mb-0" style="font-size:14px;"><?php echo htmlspecialchars($branch_profile_hint); ?></div>
            <a href="branches.php" class="btn btn-outline-primary mt-3" style="border-radius:8px;">Open Branches</a>
        </div>
    <?php endif; ?>

    <?php if ($branchRow): ?>
        <?php
        $r = $branchRow;
        if ($conn && function_exists('auragold_bootstrap_location_data')) {
            @auragold_bootstrap_location_data($conn);
        }
        $mp_countries = getList('SELECT id, name FROM tbl_countries WHERE status = 1 ORDER BY name ASC');
        if (!is_array($mp_countries)) {
            $mp_countries = [];
        }
        $mp_currencies = [];
        $mp_branch_for_currency = (int) $targetBid;
        if (isset($conn) && $conn instanceof mysqli && function_exists('auragold_tbl_has_column')
            && auragold_tbl_has_column($conn, 'tbl_currency', 'branch_id')) {
            $mpWhereBranch = '';
            if ($mp_branch_for_currency > 0) {
                $mpWhereBranch = ' AND c.branch_id = ' . $mp_branch_for_currency;
            }
            $mp_currencies = getList(
                'SELECT c.id, c.name, c.symbol, c.is_base, c.branch_id, b.name AS branch_name '
                . 'FROM tbl_currency c '
                . 'LEFT JOIN tbl_branches b ON b.id = c.branch_id '
                . 'WHERE c.status = 1' . $mpWhereBranch
                . ' ORDER BY IFNULL(b.name, \'\') ASC, c.is_base DESC, c.name ASC'
            );
        } else {
            $mpSuffix = '';
            if ($mp_branch_for_currency > 0 && function_exists('auragold_master_list_sql_for_branch_id')) {
                $mpSuffix = auragold_master_list_sql_for_branch_id($conn, 'tbl_currency', $mp_branch_for_currency);
            }
            $mp_currencies = getList(
                'SELECT id, name, symbol, is_base FROM tbl_currency WHERE status = 1' . $mpSuffix . ' ORDER BY is_base DESC, name ASC'
            );
        }
        if (!is_array($mp_currencies)) {
            $mp_currencies = [];
        }
        $mp_saved_cur_id = (int) ($r['profile_base_currency_id'] ?? 0);
        $mp_pcc = trim((string) ($r['profile_phone_country_code'] ?? ''));
        if ($mp_pcc === '') {
            $mp_pcc = '971';
        }
        $logoUrl = '';
        $lp = trim((string) ($r['logo_path'] ?? ''));
        if ($lp !== '' && is_file(__DIR__ . '/' . $lp)) {
            $logoUrl = $lp . '?v=' . (int) @filemtime(__DIR__ . '/' . $lp);
        }
        ?>
        <form method="post" action="my-profile.php" enctype="multipart/form-data" autocomplete="off">
            <input type="hidden" name="profile_form" value="branch">
            <div class="mp-card">
                <h2>Shop details</h2>
                <div class="form-row">
                    <div class="form-group col-md-6 mb-3">
                        <label for="shop_name">Shop name</label>
                        <input type="text" class="form-control" id="shop_name" name="shop_name" required maxlength="255"
                               value="<?php echo htmlspecialchars((string) ($r['name'] ?? '')); ?>">
                    </div>
                    <div class="form-group col-md-6 mb-3">
                        <label for="location_area">City / area</label>
                        <input type="text" class="form-control" id="location_area" name="location_area" maxlength="255"
                               value="<?php echo htmlspecialchars((string) ($r['location_area'] ?? '')); ?>"
                               placeholder="e.g. Mumbai, Andheri">
                    </div>
                </div>
                <div class="form-group mb-3">
                    <label for="address">Address</label>
                    <textarea class="form-control" id="address" name="address" rows="3" maxlength="65535"
                              placeholder="Full postal address"><?php echo htmlspecialchars((string) ($r['address'] ?? '')); ?></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-3 mb-3">
                        <label for="mpPhoneCountryCode">Country code <span style="color:#b91c1c">*</span> / Phone</label>
                        <div class="input-group">
                            <select class="form-control" id="mpPhoneCountryCode" name="profile_phone_country_code" required aria-required="true" style="max-width:96px;font-size:0.85rem;padding:0.4rem 0.5rem;height:38px;border-radius:8px 0 0 8px;">
                                <?php auragold_render_dial_code_select($mp_pcc); ?>
                            </select>
                            <input type="text" class="form-control" id="phone" name="phone" maxlength="50"
                                   value="<?php echo htmlspecialchars((string) ($r['phone'] ?? '')); ?>"
                                   style="border-radius:0 8px 8px 0;">
                        </div>
                    </div>
                    <div class="form-group col-md-3 mb-3">
                        <label for="landline">Landline</label>
                        <input type="text" class="form-control" id="landline" name="landline" maxlength="50"
                               value="<?php echo htmlspecialchars((string) ($r['landline'] ?? '')); ?>"
                               placeholder="Landline number">
                    </div>
                    <div class="form-group col-md-3 mb-3">
                        <label for="email">Email</label>
                        <input type="email" class="form-control" id="email" name="email" maxlength="255"
                               value="<?php echo htmlspecialchars((string) ($r['email'] ?? '')); ?>">
                    </div>
                    <div class="form-group col-md-3 mb-3">
                        <label for="website">Website</label>
                        <input type="text" class="form-control" id="website" name="website" maxlength="255"
                               value="<?php echo htmlspecialchars((string) ($r['website'] ?? '')); ?>"
                               placeholder="www.example.com">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4 mb-3">
                        <label for="cr_no">CR No.</label>
                        <input type="text" class="form-control" id="cr_no" name="cr_no" maxlength="64"
                               value="<?php echo htmlspecialchars((string) ($r['cr_no'] ?? '')); ?>"
                               placeholder="Commercial registration number">
                    </div>
                    <div class="form-group col-md-4 mb-3">
                        <label for="instagram">Instagram</label>
                        <input type="text" class="form-control" id="instagram" name="instagram" maxlength="120"
                               value="<?php echo htmlspecialchars((string) ($r['instagram'] ?? '')); ?>"
                               placeholder="your.handle">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4 mb-3">
                        <label for="mpCountry">Country <span style="color:#b91c1c">*</span></label>
                        <select class="form-control" id="mpCountry" name="profile_country_id" required aria-required="true"
                                data-initial-state-id="<?php echo (int) ($r['profile_state_id'] ?? 0); ?>"
                                data-initial-city-id="<?php echo (int) ($r['profile_city_id'] ?? 0); ?>">
                            <option value="">Select Country</option>
                            <?php foreach ($mp_countries as $ctr) {
                                $sel = ((int) ($r['profile_country_id'] ?? 0) === (int) ($ctr['id'] ?? 0)) ? ' selected' : '';
                                echo '<option value="' . (int) $ctr['id'] . '"' . $sel . '>' . htmlspecialchars((string) ($ctr['name'] ?? '')) . '</option>';
                            } ?>
                        </select>
                    </div>
                    <div class="form-group col-md-4 mb-3">
                        <label for="mpState">State <span style="color:#b91c1c">*</span></label>
                        <select class="form-control" id="mpState" name="profile_state_id" required aria-required="true">
                            <option value="">Select State</option>
                        </select>
                    </div>
                    <div class="form-group col-md-4 mb-3">
                        <label for="mpCity">City <span style="color:#b91c1c">*</span></label>
                        <select class="form-control" id="mpCity" name="profile_city_id" required aria-required="true">
                            <option value="">Select City</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4 mb-3">
                        <label for="gst_no">GST number</label>
                        <input type="text" class="form-control" id="gst_no" name="gst_no" maxlength="50"
                               value="<?php echo htmlspecialchars((string) ($r['gst_no'] ?? '')); ?>">
                    </div>
                    <div class="form-group col-md-4 mb-3">
                        <label for="pan_no">PAN</label>
                        <input type="text" class="form-control" id="pan_no" name="pan_no" maxlength="25"
                               value="<?php echo htmlspecialchars((string) ($r['pan_no'] ?? '')); ?>">
                    </div>
                    <div class="form-group col-md-4 mb-3">
                        <label for="authorized_person">Authorized person</label>
                        <input type="text" class="form-control" id="authorized_person" name="authorized_person" maxlength="150"
                               value="<?php echo htmlspecialchars((string) ($r['authorized_person'] ?? '')); ?>"
                               placeholder="Proprietor / manager name">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6 mb-3">
                        <label for="business_license_no">Business License No</label>
                        <input type="text" class="form-control" id="business_license_no" name="business_license_no" maxlength="100"
                               value="<?php echo htmlspecialchars((string) ($r['business_license_no'] ?? '')); ?>">
                    </div>
                    <div class="form-group col-md-6 mb-3">
                        <label for="profile_base_currency_id">Dashboard base currency</label>
                        <select class="form-control" id="profile_base_currency_id" name="profile_base_currency_id" style="border-radius:8px;">
                            <option value="0"<?php echo $mp_saved_cur_id <= 0 ? ' selected' : ''; ?>>System default (master base currency)</option>
                            <?php foreach ($mp_currencies as $mc) {
                                $cid = (int) ($mc['id'] ?? 0);
                                if ($cid <= 0) {
                                    continue;
                                }
                                $cname = trim((string) ($mc['name'] ?? ''));
                                $csym = trim((string) ($mc['symbol'] ?? ''));
                                $isBase = !empty($mc['is_base']);
                                $optLabel = $cname !== '' ? $cname : $csym;
                                if ($optLabel === '') {
                                    continue;
                                }
                                if ($csym !== '' && $csym !== $cname) {
                                    $optLabel .= ' (' . $csym . ')';
                                }
                                $branchLabel = trim((string) ($mc['branch_name'] ?? ''));
                                $curBranchId = (int) ($mc['branch_id'] ?? 0);
                                if ($branchLabel === '' && $curBranchId > 0) {
                                    $branchLabel = 'Branch #' . $curBranchId;
                                }
                                $scopeBid = (int) ($mp_branch_for_currency ?? 0);
                                if ($scopeBid > 0 && $curBranchId === $scopeBid) {
                                    if ($isBase) {
                                        $optLabel .= ' (base)';
                                    }
                                } elseif ($branchLabel !== '') {
                                    $optLabel .= ' — ' . $branchLabel;
                                    if ($isBase) {
                                        $optLabel .= ' (base)';
                                    }
                                } elseif ($isBase) {
                                    $optLabel .= ' — system base';
                                }
                                $sel = ($mp_saved_cur_id === $cid) ? ' selected' : '';
                                echo '<option value="' . $cid . '"' . $sel . '>' . htmlspecialchars($optLabel) . '</option>';
                            } ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6 mb-3">
                        <label for="timezone">Timezone <span style="color:#b91c1c">*</span></label>
                        <?php
                        $mp_tz = trim((string) ($r['timezone'] ?? ''));
                        if ($mp_tz === '' && function_exists('auragold_timezone_guess_from_phone_code')) {
                            $mp_tz = auragold_timezone_guess_from_phone_code($mp_pcc);
                        }
                        if (function_exists('auragold_timezone_normalize')) {
                            $mp_tz = auragold_timezone_normalize($mp_tz);
                        } elseif ($mp_tz === '') {
                            $mp_tz = 'Asia/Kolkata';
                        }
                        $mp_tz_opts = function_exists('auragold_timezone_options') ? auragold_timezone_options() : ['Asia/Kolkata' => 'India (Asia/Kolkata)'];
                        if (!isset($mp_tz_opts[$mp_tz])) {
                            $mp_tz_opts = [$mp_tz => $mp_tz] + $mp_tz_opts;
                        }
                        ?>
                        <select class="form-control" id="timezone" name="timezone" required aria-required="true" style="border-radius:8px;">
                            <?php foreach ($mp_tz_opts as $tzVal => $tzLabel): ?>
                            <option value="<?php echo htmlspecialchars($tzVal); ?>"<?php echo $mp_tz === $tzVal ? ' selected' : ''; ?>><?php echo htmlspecialchars($tzLabel); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Transaction date &amp; time use this timezone (not the India server clock).</small>
                    </div>
                </div>
            </div>

            <div class="mp-card">
                <h2>Bank details</h2>
                <div class="form-row">
                    <div class="form-group col-md-6 mb-3">
                        <label for="bank_name">Bank name</label>
                        <input type="text" class="form-control" id="bank_name" name="bank_name" maxlength="150"
                               value="<?php echo htmlspecialchars((string) ($r['bank_name'] ?? '')); ?>">
                    </div>
                    <div class="form-group col-md-6 mb-3">
                        <label for="bank_branch">Bank branch</label>
                        <input type="text" class="form-control" id="bank_branch" name="bank_branch" maxlength="150"
                               value="<?php echo htmlspecialchars((string) ($r['bank_branch'] ?? '')); ?>">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-6 mb-3">
                        <label for="bank_account_no">Account number</label>
                        <input type="text" class="form-control" id="bank_account_no" name="bank_account_no" maxlength="64"
                               value="<?php echo htmlspecialchars((string) ($r['bank_account_no'] ?? '')); ?>"
                               autocomplete="off">
                    </div>
                    <div class="form-group col-md-6 mb-3">
                        <label for="bank_ifsc">IFSC</label>
                        <input type="text" class="form-control" id="bank_ifsc" name="bank_ifsc" maxlength="20"
                               value="<?php echo htmlspecialchars((string) ($r['bank_ifsc'] ?? '')); ?>">
                    </div>
                </div>
            </div>

            <div class="mp-card">
                <h2>Invoice &amp; branding</h2>
                <div class="form-group mb-3">
                    <label for="invoice_terms">Terms &amp; conditions (invoices)</label>
                    <textarea class="form-control" id="invoice_terms" name="invoice_terms" rows="5" maxlength="65535"
                              placeholder="Default terms to print on invoices"><?php echo htmlspecialchars((string) ($r['invoice_terms'] ?? '')); ?></textarea>
                </div>
                <div class="form-group mb-2">
                    <label for="logo_file">Shop logo</label>
                    <input type="file" class="form-control-file" id="logo_file" name="logo_file" accept="image/jpeg,image/png,image/gif,image/webp">
                    <div class="mp-hint">JPG, PNG, GIF or WebP, up to 2 MB. Used for invoices and printouts if your templates read from branch profile.</div>
                </div>
                <?php if ($logoUrl !== ''): ?>
                    <div class="mb-2">
                        <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Logo" class="mp-logo-preview">
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="remove_logo" name="remove_logo" value="1">
                        <label class="form-check-label" for="remove_logo">Remove current logo</label>
                    </div>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn mp-btn-save">Save profile</button>
            <a href="dashboard.php" class="btn btn-light ml-2" style="border-radius:8px;">Cancel</a>
        </form>
        <script>
        (function () {
            function fetchJson(url) {
                return fetch(url, { credentials: 'same-origin' }).then(function (r) { return r.json(); });
            }
            function fillStates(rows, selectedId) {
                var sel = document.getElementById('mpState');
                var citySel = document.getElementById('mpCity');
                if (!sel) return;
                sel.innerHTML = '<option value="">Select State</option>';
                if (citySel) citySel.innerHTML = '<option value="">Select City</option>';
                (rows || []).forEach(function (row) {
                    var o = document.createElement('option');
                    o.value = String(row.id);
                    o.textContent = row.name;
                    sel.appendChild(o);
                });
                if (selectedId) {
                    sel.value = String(selectedId);
                    if (sel.value !== String(selectedId)) {
                        sel.value = '';
                    }
                }
            }
            function fillCities(rows, selectedId) {
                var sel = document.getElementById('mpCity');
                if (!sel) return;
                sel.innerHTML = '<option value="">Select City</option>';
                (rows || []).forEach(function (row) {
                    var o = document.createElement('option');
                    o.value = String(row.id);
                    o.textContent = row.name;
                    sel.appendChild(o);
                });
                if (selectedId) {
                    sel.value = String(selectedId);
                    if (sel.value !== String(selectedId)) {
                        sel.value = '';
                    }
                }
            }
            function loadStates(selectedStateId, selectedCityId) {
                var c = document.getElementById('mpCountry');
                if (!c || !c.value) {
                    fillStates([], '');
                    fillCities([], '');
                    return Promise.resolve();
                }
                return fetchJson('ajax/get-states-by-country.php?country_id=' + encodeURIComponent(c.value))
                    .then(function (data) {
                        var rows = (data && data.states) ? data.states : [];
                        fillStates(rows, selectedStateId || '');
                        var st = document.getElementById('mpState');
                        if (!st || !st.value) {
                            fillCities([], '');
                            return;
                        }
                        return fetchJson('ajax/get-cities-by-state.php?state_id=' + encodeURIComponent(st.value))
                            .then(function (d2) {
                                var cr = (d2 && d2.cities) ? d2.cities : [];
                                fillCities(cr, selectedCityId || '');
                            });
                    })
                    .catch(function () {
                        fillStates([], '');
                        fillCities([], '');
                    });
            }
            function init() {
                var country = document.getElementById('mpCountry');
                var state = document.getElementById('mpState');
                if (!country || !state) return;
                var initSid = country.getAttribute('data-initial-state-id') || '';
                var initCid = country.getAttribute('data-initial-city-id') || '';
                if (country.value) {
                    loadStates(initSid, initCid);
                }
                country.addEventListener('change', function () {
                    loadStates('', '');
                });
                state.addEventListener('change', function () {
                    var st = document.getElementById('mpState');
                    if (!st || !st.value) {
                        fillCities([], '');
                        return;
                    }
                    fetchJson('ajax/get-cities-by-state.php?state_id=' + encodeURIComponent(st.value))
                        .then(function (d2) {
                            var cr = (d2 && d2.cities) ? d2.cities : [];
                            fillCities(cr, '');
                        })
                        .catch(function () { fillCities([], ''); });
                });
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();
        </script>
    <?php endif; ?>
</div>

<?php
require __DIR__ . '/includes/dashboard_shell_bottom.php';
