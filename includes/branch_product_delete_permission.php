<?php
/**
 * Branch-wise product delete permission (tbl_branches.allow_product_delete).
 * Requires config.php (conn_master, getRecordMaster, getList, $conn) and branch_credentials.php.
 */
if (!function_exists('getRecordMaster')) {
    require_once __DIR__ . '/../config.php';
}
require_once __DIR__ . '/branch_credentials.php';

/**
 * Ensure tbl_branches.allow_product_delete exists (TINYINT default 0).
 */
function auragold_ensure_branches_allow_product_delete_column(mysqli $conn_master) {
    $q = @mysqli_query($conn_master, "SHOW COLUMNS FROM tbl_branches LIKE 'allow_product_delete'");
    if ($q && mysqli_num_rows($q) > 0) {
        mysqli_free_result($q);
        return true;
    }
    return (bool) @mysqli_query(
        $conn_master,
        'ALTER TABLE tbl_branches ADD COLUMN allow_product_delete TINYINT NOT NULL DEFAULT 0'
    );
}

/**
 * Map MySQL schema name to tbl_branches.id (registry).
 */
function auragold_branch_id_from_database_name($db) {
    $db = trim((string) $db);
    if ($db === '') {
        return 0;
    }
    $rows = getListMaster('SELECT * FROM tbl_branches');
    foreach ($rows as $row) {
        $cr = auragold_branch_row_db_credentials($row);
        $dn = trim((string) ($cr['db_name'] ?? ''));
        if ($dn === '' && defined('DB_NAME')) {
            $dn = trim((string) DB_NAME);
        }
        if ($dn !== '' && strcasecmp($dn, $db) === 0) {
            return (int) $row['id'];
        }
    }
    return 0;
}

/**
 * Delete permission follows the branch row for the **current MySQL database** (working context),
 * not only the login account. Sub-branches require allow_product_delete = 1; main branch row always allows.
 * (tbl_users "admin" in auragold_branch1 must respect Branch 1's checkbox.)
 */
function auragold_product_delete_allowed_for_working_context() {
    global $conn_master;
    if (!$conn_master) {
        return false;
    }
    auragold_ensure_branches_allow_product_delete_column($conn_master);
    $wid = auragold_resolve_working_branch_id_for_product_delete();
    if ($wid <= 0) {
        return false;
    }
    $row = getRecordMaster(
        'SELECT main_branch_id, allow_product_delete FROM tbl_branches WHERE id = ' . $wid . ' LIMIT 1'
    );
    if (!$row) {
        return false;
    }
    if ((int) ($row['main_branch_id'] ?? 0) === 0) {
        return true;
    }
    return ((int) ($row['allow_product_delete'] ?? 0) === 1);
}

/**
 * @deprecated Use auragold_product_delete_allowed_for_working_context(); kept for compatibility.
 */
function auragold_product_delete_is_allowed_by_session() {
    return auragold_product_delete_allowed_for_working_context();
}

/**
 * Resolve working branch id: prefer actual mysqli DATABASE() so permission matches $conn (not stale session).
 */
function auragold_resolve_working_branch_id_for_product_delete() {
    global $conn;
    $db = '';
    if (isset($conn) && $conn instanceof mysqli) {
        $r = @mysqli_query($conn, 'SELECT DATABASE() AS d');
        if ($r && ($row = mysqli_fetch_assoc($r))) {
            mysqli_free_result($r);
            $db = trim((string) ($row['d'] ?? ''));
        }
    }
    if ($db !== '') {
        $fromDb = auragold_branch_id_from_database_name($db);
        if ($fromDb > 0) {
            return $fromDb;
        }
    }
    $wid = (int) ($_SESSION['working_branch_id'] ?? $_SESSION['branch_id'] ?? 0);
    if ($wid > 0) {
        return $wid;
    }
    if ($db === '' && defined('DB_NAME')) {
        $db = trim((string) DB_NAME);
    }
    if ($db === '') {
        return 0;
    }
    return auragold_branch_id_from_database_name($db);
}

/**
 * True if product is assigned to the given branch (characteristics or tbl_product_branches).
 */
function auragold_product_linked_to_branch(mysqli $conn, $product_id, $branch_id) {
    $product_id = (int) $product_id;
    $branch_id  = (int) $branch_id;
    if ($product_id <= 0 || $branch_id <= 0) {
        return false;
    }
    $tb = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_product_branches'");
    if ($tb && mysqli_num_rows($tb) > 0) {
        mysqli_free_result($tb);
        $q = mysqli_query(
            $conn,
            "SELECT 1 FROM tbl_product_branches WHERE product_id = $product_id AND branch_id = $branch_id LIMIT 1"
        );
        if ($q && mysqli_num_rows($q) > 0) {
            mysqli_free_result($q);
            return true;
        }
        if ($q) {
            mysqli_free_result($q);
        }
    } elseif ($tb) {
        mysqli_free_result($tb);
    }
    $q2 = mysqli_query(
        $conn,
        "SELECT 1 FROM tbl_product_characteristics WHERE product_id = $product_id AND status = 1 AND branch_id = $branch_id LIMIT 1"
    );
    if ($q2 && mysqli_num_rows($q2) > 0) {
        mysqli_free_result($q2);
        return true;
    }
    if ($q2) {
        mysqli_free_result($q2);
    }
    // Legacy rows with no branch set (single-branch / old data)
    $q3 = mysqli_query(
        $conn,
        "SELECT 1 FROM tbl_product_characteristics WHERE product_id = $product_id AND status = 1 AND (branch_id IS NULL OR branch_id = 0) LIMIT 1"
    );
    if ($q3 && mysqli_num_rows($q3) > 0) {
        mysqli_free_result($q3);
        $q4 = mysqli_query(
            $conn,
            "SELECT 1 FROM tbl_product_characteristics WHERE product_id = $product_id AND status = 1 AND branch_id > 0 AND branch_id != $branch_id LIMIT 1"
        );
        $other = $q4 && mysqli_num_rows($q4) > 0;
        if ($q4) {
            mysqli_free_result($q4);
        }
        return !$other;
    }
    if ($q3) {
        mysqli_free_result($q3);
    }
    return false;
}
