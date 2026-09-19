<?php
/**
 * Ensures tbl_branches has jewellery shop profile columns (registry DB).
 * Call with $conn_master after config.php.
 */
function auragold_ensure_tbl_branches_profile_columns($conn) {
    if (!$conn instanceof mysqli) {
        return;
    }
    static $done = [];
    $key = spl_object_hash($conn);
    if (!empty($done[$key])) {
        return;
    }

    $defs = [
        'address'            => 'TEXT NULL',
        'phone'              => 'VARCHAR(50) NULL DEFAULT NULL',
        'landline'           => 'VARCHAR(50) NULL DEFAULT NULL',
        'email'              => 'VARCHAR(255) NULL DEFAULT NULL',
        'gst_no'             => 'VARCHAR(50) NULL DEFAULT NULL',
        'pan_no'             => 'VARCHAR(25) NULL DEFAULT NULL',
        'business_license_no' => 'VARCHAR(100) NULL DEFAULT NULL',
        'authorized_person'  => 'VARCHAR(150) NULL DEFAULT NULL',
        'bank_name'          => 'VARCHAR(150) NULL DEFAULT NULL',
        'bank_account_no'    => 'VARCHAR(64) NULL DEFAULT NULL',
        'bank_ifsc'          => 'VARCHAR(20) NULL DEFAULT NULL',
        'bank_branch'        => 'VARCHAR(150) NULL DEFAULT NULL',
        'location_area'      => 'VARCHAR(255) NULL DEFAULT NULL',
        'logo_path'          => 'VARCHAR(500) NULL DEFAULT NULL',
        'invoice_terms'      => 'TEXT NULL',
        'website'            => 'VARCHAR(255) NULL DEFAULT NULL',
        'cr_no'              => 'VARCHAR(64) NULL DEFAULT NULL',
        'instagram'          => 'VARCHAR(120) NULL DEFAULT NULL',
        'profile_country_id' => 'INT NULL DEFAULT NULL',
        'profile_state_id'   => 'INT NULL DEFAULT NULL',
        'profile_city_id'    => 'INT NULL DEFAULT NULL',
        'profile_phone_country_code' => 'VARCHAR(10) NULL DEFAULT NULL',
        'profile_base_currency_id'   => 'INT NULL DEFAULT NULL',
        /** Postal PIN for e-Way bill / GST (India); prefer this over legacy zip_code when both exist */
        'pincode'                    => 'VARCHAR(10) NULL DEFAULT NULL',
        /** IANA timezone for branch local date/time (e.g. Asia/Kolkata, Asia/Dubai) */
        'timezone'                   => "VARCHAR(64) NULL DEFAULT 'Asia/Kolkata'",
    ];

    foreach ($defs as $col => $def) {
        $c = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_branches LIKE '" . mysqli_real_escape_string($conn, $col) . "'");
        if ($c && mysqli_num_rows($c) === 0) {
            @mysqli_query($conn, 'ALTER TABLE tbl_branches ADD COLUMN `' . $col . '` ' . $def);
        }
    }

    // Existing rows: empty timezone only (never overwrite a saved value).
    $cTz = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_branches LIKE 'timezone'");
    if ($cTz && mysqli_num_rows($cTz) > 0) {
        $res = @mysqli_query(
            $conn,
            "SELECT id, profile_phone_country_code FROM tbl_branches
             WHERE timezone IS NULL OR TRIM(IFNULL(timezone, '')) = ''"
        );
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $id = (int) ($row['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $guess = function_exists('auragold_timezone_guess_from_phone_code')
                    ? auragold_timezone_guess_from_phone_code($row['profile_phone_country_code'] ?? '')
                    : 'Asia/Kolkata';
                if ($guess === '') {
                    $guess = 'Asia/Kolkata';
                }
                $gEsc = mysqli_real_escape_string($conn, $guess);
                @mysqli_query(
                    $conn,
                    "UPDATE tbl_branches SET timezone = '{$gEsc}'
                     WHERE id = {$id} AND (timezone IS NULL OR TRIM(IFNULL(timezone, '')) = '') LIMIT 1"
                );
            }
            mysqli_free_result($res);
        }
    }
    if ($cTz) {
        mysqli_free_result($cTz);
    }

    $done[$key] = true;
}

/**
 * Ensures tbl_users has profile_photo (relative path under admin/, e.g. uploads/user_profiles/1_xxx.jpg).
 */
function auragold_ensure_tbl_users_profile_photo_column($conn) {
    if (!$conn instanceof mysqli) {
        return;
    }
    static $doneUser = [];
    $key = spl_object_hash($conn);
    if (!empty($doneUser[$key])) {
        return;
    }
    $c = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_users LIKE 'profile_photo'");
    if ($c && mysqli_num_rows($c) === 0) {
        @mysqli_query(
            $conn,
            "ALTER TABLE tbl_users ADD COLUMN profile_photo VARCHAR(500) NULL DEFAULT NULL COMMENT 'User avatar path under admin/'"
        );
    }
    if ($c) {
        mysqli_free_result($c);
    }
    $doneUser[$key] = true;
}

// Access token helpers live in includes/auragold_access_token.php (also bootstrapped from config.php).
if (is_file(__DIR__ . '/auragold_access_token.php')) {
    require_once __DIR__ . '/auragold_access_token.php';
}

/**
 * Branch row id this profile page edits: branch login = own row; admin user = working branch.
 * For tbl_users logins, branch chosen at login sets $_SESSION['branch_id']; working DB may also set
 * $_SESSION['working_branch_id']. If neither is set (e.g. Main at login), use first registry main row
 * so My Profile can load/save tbl_branches shop details like the reference UI.
 */
function auragold_my_profile_target_branch_id(): int {
    $src = isset($_SESSION['login_source']) ? strtolower(trim((string) $_SESSION['login_source'])) : '';
    if ($src === '' && !empty($_SESSION['Admin']) && is_array($_SESSION['Admin'])) {
        $src = 'user';
    }
    if ($src === 'branch') {
        $id = (int) ($_SESSION['user_id'] ?? 0);
        return $id > 0 ? $id : 0;
    }
    if ($src === 'user') {
        $wid = (int) ($_SESSION['working_branch_id'] ?? 0);
        if ($wid <= 0) {
            $wid = (int) ($_SESSION['branch_id'] ?? 0);
        }
        if ($wid <= 0 && !empty($_SESSION['working_db']) && is_array($_SESSION['working_db'])) {
            $wdb = trim((string) ($_SESSION['working_db']['database'] ?? $_SESSION['working_db']['db_name'] ?? ''));
            if ($wdb !== '' && function_exists('auragold_registry_mysqli') && function_exists('esc')) {
                $reg = auragold_registry_mysqli();
                if ($reg instanceof mysqli) {
                    $res = mysqli_query(
                        $reg,
                        "SELECT id FROM tbl_branches WHERE LOWER(TRIM(IFNULL(db_name, ''))) = LOWER('" . esc($wdb) . "') LIMIT 1"
                    );
                    if ($res && mysqli_num_rows($res) > 0) {
                        $found = mysqli_fetch_assoc($res);
                        mysqli_free_result($res);
                        if (is_array($found) && (int) ($found['id'] ?? 0) > 0) {
                            $wid = (int) $found['id'];
                        }
                    } elseif ($res) {
                        mysqli_free_result($res);
                    }
                }
            }
        }
        if ($wid <= 0 && function_exists('getRecordMaster')) {
            $main = @getRecordMaster(
                'SELECT id FROM tbl_branches WHERE IFNULL(main_branch_id, 0) = 0 ORDER BY id ASC LIMIT 1'
            );
            if (is_array($main) && (int) ($main['id'] ?? 0) > 0) {
                $wid = (int) $main['id'];
            }
        }
        return $wid > 0 ? $wid : 0;
    }
    return 0;
}

/**
 * Branch row used for default country/state/city when opening "new customer" ledger modal.
 * Must match {@see auragold_my_profile_target_branch_id()} so defaults match My Profile.
 */
function auragold_default_branch_id_for_ledger_defaults(): int
{
    return auragold_my_profile_target_branch_id();
}
