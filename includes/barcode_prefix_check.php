<?php
/**
 * Barcode prefix validation in tbl_product_characteristics across the current main branch’s
 * MySQL databases only (main + sub-branches in tbl_branches), not unrelated branches.
 * Load after config.php (getListMaster, DB_*, $conn) and branch_credentials.php.
 */
if (!function_exists('getListMaster')) {
    require_once __DIR__ . '/../config.php';
}
require_once __DIR__ . '/branch_credentials.php';

/**
 * Distinct MySQL schema names used by branch rows + main bootstrap DB (same idea as product sync).
 *
 * @return list<string>
 */
function auragold_list_distinct_branch_database_names() {
    global $conn_master;
    if (!$conn_master) {
        return [];
    }
    $seen = [];
    $out  = [];
    $rows = getListMaster('SELECT * FROM tbl_branches');
    foreach ($rows as $row) {
        if (!auragold_tbl_branch_row_is_active($row)) {
            continue;
        }
        $cr = auragold_branch_row_db_credentials($row);
        $dn = trim((string) ($cr['db_name'] ?? ''));
        if ($dn === '') {
            continue;
        }
        $k = strtolower($dn);
        if (isset($seen[$k])) {
            continue;
        }
        $seen[$k] = true;
        $out[] = $dn;
    }
    if (defined('DB_NAME')) {
        $dn = trim((string) DB_NAME);
        if ($dn !== '') {
            $k = strtolower($dn);
            if (!isset($seen[$k])) {
                $seen[$k] = true;
                $out[] = $dn;
            }
        }
    }
    return $out;
}

/**
 * @return string Resolved schema name or empty
 */
function auragold_resolve_branch_database_name_from_id($branch_id) {
    $branch_id = (int) $branch_id;
    if ($branch_id <= 0) {
        return '';
    }
    $row = getRecordMaster('SELECT * FROM tbl_branches WHERE id = ' . $branch_id . ' LIMIT 1');
    if (!$row) {
        return '';
    }
    $cr = auragold_branch_row_db_credentials($row);
    $db = trim((string) ($cr['db_name'] ?? ''));
    if ($db === '' && defined('DB_NAME')) {
        $db = trim((string) DB_NAME);
    }
    return $db;
}

/**
 * Registry “main” row id for a branch: main row uses id, sub-branch points to its main_branch_id.
 * Same idea as auragold_branch_login_scope_main_id (without requiring branch-credential session).
 */
function auragold_barcode_prefix_registry_main_id_for_branch(int $branch_id): int {
    $branch_id = (int) $branch_id;
    if ($branch_id <= 0) {
        return 0;
    }
    $row = getRecordMaster('SELECT id, main_branch_id FROM tbl_branches WHERE id = ' . $branch_id . ' LIMIT 1');
    if (!$row) {
        return 0;
    }
    $mb = (int) ($row['main_branch_id'] ?? 0);
    return $mb === 0 ? (int) ($row['id'] ?? 0) : $mb;
}

/**
 * Distinct MySQL schema names for the main branch row and its sub-branches only (not other mains).
 *
 * @return list<string>
 */
function auragold_list_distinct_branch_database_names_in_main_group(int $registry_main_id) {
    $registry_main_id = (int) $registry_main_id;
    if ($registry_main_id <= 0) {
        return [];
    }
    if (!function_exists('getListMaster')) {
        return [];
    }
    $rows = getListMaster(
        'SELECT * FROM tbl_branches WHERE id = ' . $registry_main_id
        . ' OR IFNULL(main_branch_id, 0) = ' . $registry_main_id
    );
    $seen = [];
    $out  = [];
    foreach ($rows as $row) {
        if (function_exists('auragold_tbl_branch_row_is_active') && !auragold_tbl_branch_row_is_active($row)) {
            continue;
        }
        $cr = auragold_branch_row_db_credentials($row);
        $dn = trim((string) ($cr['db_name'] ?? ''));
        if ($dn === '') {
            continue;
        }
        $k = strtolower($dn);
        if (isset($seen[$k])) {
            continue;
        }
        $seen[$k] = true;
        $out[] = $dn;
    }
    return $out;
}

/**
 * Connect to a branch schema using credentials from tbl_branches when present, else bootstrap DB_* .
 */
function auragold_mysqli_connect_to_branch_database($dbName) {
    $dbName = trim((string) $dbName);
    if ($dbName === '') {
        return null;
    }
    $attempt = static function ($host, $user, $pass, $schema) {
        try {
            $link = @mysqli_connect($host, $user, $pass, $schema);
            if (!$link) {
                return null;
            }
            mysqli_set_charset($link, 'utf8mb4');
            return $link;
        } catch (Throwable $e) {
            return null;
        }
    };
    $rows = getListMaster('SELECT * FROM tbl_branches');
    foreach ($rows as $row) {
        if (!auragold_tbl_branch_row_is_active($row)) {
            continue;
        }
        $cr = auragold_branch_row_db_credentials($row);
        if (strcasecmp(trim((string) ($cr['db_name'] ?? '')), $dbName) !== 0) {
            continue;
        }
        $dbuser = $cr['db_user'] !== '' ? $cr['db_user'] : DB_USER;
        $dbpass = (string) $cr['db_pass'];
        return $attempt(DB_HOST, $dbuser, $dbpass, $dbName);
    }
    return $attempt(DB_HOST, DB_USER, DB_PASS, $dbName);
}

/**
 * @return bool True if prefix is used by another active characteristic row
 */
function auragold_barcode_prefix_exists_in_connection(mysqli $link, $prefix, $exclude_product_id) {
    $prefix = trim((string) $prefix);
    if ($prefix === '') {
        return false;
    }
    try {
        $col = @mysqli_query($link, "SHOW COLUMNS FROM tbl_product_characteristics LIKE 'barcode_prefix'");
        if (!$col || mysqli_num_rows($col) === 0) {
            if ($col) {
                mysqli_free_result($col);
            }
            return false;
        }
        mysqli_free_result($col);
        $pe = mysqli_real_escape_string($link, $prefix);
        $eid = (int) $exclude_product_id;
        $sql = "SELECT id FROM tbl_product_characteristics WHERE status = 1 AND TRIM(barcode_prefix) = '$pe' AND product_id != $eid LIMIT 1";
        $q = mysqli_query($link, $sql);
        $found = $q && mysqli_num_rows($q) > 0;
        if ($q) {
            mysqli_free_result($q);
        }
        return $found;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Validate barcode prefix: same main + sub-branch DBs only, then the active connection schema.
 *
 * @param int $current_branch_id Login / form branch (used if mysqli DATABASE() is empty)
 * @param int $exclude_product_id Exclude this product_id when editing (0 for new product)
 *
 * @return array{ok: bool, type?: string} ok false + type other_branch | current_branch
 */
function checkBarcodePrefix($prefix, $current_branch_id, $exclude_product_id = 0) {
    global $conn;
    $prefix = trim((string) $prefix);
    if ($prefix === '') {
        return ['ok' => true];
    }
    $exclude_product_id = (int) $exclude_product_id;
    $currentDb = '';
    if (isset($conn) && $conn instanceof mysqli) {
        $r = @mysqli_query($conn, 'SELECT DATABASE() AS d');
        if ($r && ($row = mysqli_fetch_assoc($r))) {
            mysqli_free_result($r);
            $currentDb = trim((string) ($row['d'] ?? ''));
        }
    }
    if ($currentDb === '') {
        $currentDb = auragold_resolve_branch_database_name_from_id((int) $current_branch_id);
    }
    if ($currentDb === '' && defined('DB_NAME')) {
        $currentDb = trim((string) DB_NAME);
    }
    if ($currentDb === '') {
        return ['ok' => false, 'type' => 'current_branch'];
    }

    $mainId = auragold_barcode_prefix_registry_main_id_for_branch((int) $current_branch_id);
    $peerDbs = auragold_list_distinct_branch_database_names_in_main_group($mainId);
    if (empty($peerDbs)) {
        $peerDbs = [$currentDb];
    }

    foreach ($peerDbs as $dbName) {
        if (strcasecmp(trim((string) $dbName), $currentDb) === 0) {
            continue;
        }
        $link = auragold_mysqli_connect_to_branch_database($dbName);
        if (!$link) {
            continue;
        }
        $exists = auragold_barcode_prefix_exists_in_connection($link, $prefix, $exclude_product_id);
        mysqli_close($link);
        if ($exists) {
            return ['ok' => false, 'type' => 'other_branch'];
        }
    }

    $existsCurrent = false;
    $usedConn = false;
    if (isset($conn) && $conn instanceof mysqli) {
        $rdb = @mysqli_query($conn, 'SELECT DATABASE() AS d');
        $dbFromConn = '';
        if ($rdb && ($row = mysqli_fetch_assoc($rdb))) {
            mysqli_free_result($rdb);
            $dbFromConn = trim((string) ($row['d'] ?? ''));
        }
        if ($dbFromConn === '' || strcasecmp($dbFromConn, $currentDb) === 0) {
            $existsCurrent = auragold_barcode_prefix_exists_in_connection($conn, $prefix, $exclude_product_id);
            $usedConn = true;
        }
    }
    if (!$usedConn) {
        $curLink = auragold_mysqli_connect_to_branch_database($currentDb);
        if (!$curLink) {
            return ['ok' => false, 'type' => 'current_branch'];
        }
        $existsCurrent = auragold_barcode_prefix_exists_in_connection($curLink, $prefix, $exclude_product_id);
        mysqli_close($curLink);
    }
    if ($existsCurrent) {
        return ['ok' => false, 'type' => 'current_branch'];
    }
    return ['ok' => true];
}
