<?php

require_once __DIR__ . '/permission_definitions.php';

/**
 * Load grant map for a tbl_users id from master DB (single branch scope).
 *
 * @param int $branchId 0 = default / all-branch fallback rows.
 *
 * @return array<string, int> perm_key => 0|1
 */
function auragold_permission_grants_map_for_user_branch($conn, $userId, $branchId = 0)
{
    $userId   = (int) $userId;
    $branchId = (int) $branchId;
    if ($userId <= 0 || !$conn || !($conn instanceof mysqli)) {
        return [];
    }
    $rows = [];
    $res  = mysqli_query(
        $conn,
        'SELECT perm_key, granted FROM tbl_user_permission_grants WHERE user_id = ' . $userId . ' AND branch_id = ' . $branchId
    );
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $k = (string) ($row['perm_key'] ?? '');
            if ($k !== '') {
                $rows[$k] = (int) ($row['granted'] ?? 0) ? 1 : 0;
            }
        }
    }
    return $rows;
}

/**
 * @deprecated Use auragold_permission_grants_map_for_user_branch($conn, $userId, 0).
 *
 * @return array<string, int> perm_key => 0|1
 */
function auragold_permission_grants_map_for_user($conn, $userId)
{
    return auragold_permission_grants_map_for_user_branch($conn, $userId, 0);
}

/**
 * Effective grant map for permission checks: uses rows for the session’s effective branch when present, else branch_id 0.
 *
 * @return array<string, int>|null null = legacy “no rows” allow-all
 */
function auragold_permission_effective_map_for_session_user($conn, $userId)
{
    $userId = (int) $userId;
    if ($userId <= 0 || !$conn || !($conn instanceof mysqli)) {
        return [];
    }
    if (!function_exists('auragold_effective_branch_id')) {
        require_once __DIR__ . '/auragold_branch_data_scope.php';
    }
    $eff = (int) auragold_effective_branch_id();

    $cntB = auragold_permission_grants_count_for_user_branch($conn, $userId, $eff);
    if ($eff > 0 && $cntB > 0) {
        return auragold_permission_grants_map_for_user_branch($conn, $userId, $eff);
    }
    return auragold_permission_grants_map_for_user_branch($conn, $userId, 0);
}

/**
 * Clear cached permission map (call after grants are saved).
 */
function auragold_permission_invalidate_session_cache(?int $userId = null): void
{
    if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION['auragold_perm_runtime_v1'], $_SESSION['auragold_perm_sig_v1']);
    }
    $GLOBALS['auragold_perm_runtime_state'] = null;
}

/**
 * Cheap change signature for a user's grant rows ("count:maxid").
 * Saves delete + re-insert rows, so MAX(id) changes on every save.
 */
function auragold_permission_grants_signature($conn, $userId)
{
    static $sigCache = [];
    $userId = (int) $userId;
    if ($userId <= 0 || !$conn || !($conn instanceof mysqli)) {
        return '0:0';
    }
    $connKey = spl_object_hash($conn);
    if (isset($sigCache[$connKey][$userId])) {
        return $sigCache[$connKey][$userId];
    }
    if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
        $sessKey = 'auragold_perm_sig_v1';
        if (!empty($_SESSION[$sessKey]) && is_array($_SESSION[$sessKey])) {
            $cached = $_SESSION[$sessKey];
            if ((int) ($cached['user_id'] ?? 0) === $userId
                && (time() - (int) ($cached['t'] ?? 0)) < 120) {
                $sig = (string) ($cached['sig'] ?? '0:0');
                $sigCache[$connKey][$userId] = $sig;
                return $sig;
            }
        }
    }
    $res = mysqli_query(
        $conn,
        'SELECT COUNT(*) AS c, IFNULL(MAX(id), 0) AS m FROM tbl_user_permission_grants WHERE user_id = ' . $userId
    );
    $sig = '0:0';
    if ($res && ($r = mysqli_fetch_assoc($res))) {
        $sig = (int) ($r['c'] ?? 0) . ':' . (int) ($r['m'] ?? 0);
    }
    $sigCache[$connKey][$userId] = $sig;
    if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['auragold_perm_sig_v1'] = [
            'user_id' => $userId,
            'sig'     => $sig,
            't'       => time(),
        ];
    }
    return $sig;
}

/**
 * Load permission state once per request (and cache in session between requests).
 *
 * @return array{legacy_allow_all:bool,map:array<string,int>,user_id:int,branch_id:int}
 */
function auragold_permission_runtime_state()
{
    if (isset($GLOBALS['auragold_perm_runtime_state']) && is_array($GLOBALS['auragold_perm_runtime_state'])) {
        return $GLOBALS['auragold_perm_runtime_state'];
    }

    $default = [
        'legacy_allow_all' => true,
        'map'              => [],
        'user_id'          => 0,
        'branch_id'        => 0,
    ];

    if (!function_exists('auragold_session_is_admin_login_type')) {
        require_once __DIR__ . '/session_login_type.php';
    }
    $src = isset($_SESSION['login_source']) ? (string) $_SESSION['login_source'] : '';
    if ($src !== 'user') {
        $GLOBALS['auragold_perm_runtime_state'] = $default;
        return $default;
    }
    $uid = (int) ($_SESSION['user_id'] ?? 0);
    if ($uid <= 0) {
        $GLOBALS['auragold_perm_runtime_state'] = $default;
        return $default;
    }
    global $conn;
    if (empty($conn) || !($conn instanceof mysqli)) {
        $GLOBALS['auragold_perm_runtime_state'] = $default;
        return $default;
    }

    if (!function_exists('auragold_effective_branch_id')) {
        require_once __DIR__ . '/auragold_branch_data_scope.php';
    }
    $eff = (int) auragold_effective_branch_id();

    if (!function_exists('auragold_ensure_user_permissions_table')) {
        require_once __DIR__ . '/permissions_schema.php';
    }
    auragold_ensure_user_permissions_table($conn);

    // Signature check makes admin permission saves take effect on the user's
    // next page load without re-login (session cache alone went stale).
    $sig = auragold_permission_grants_signature($conn, $uid);

    $sessKey = 'auragold_perm_runtime_v1';
    if (!empty($_SESSION[$sessKey]) && is_array($_SESSION[$sessKey])) {
        $cached = $_SESSION[$sessKey];
        if ((int) ($cached['user_id'] ?? 0) === $uid
            && (int) ($cached['branch_id'] ?? 0) === $eff
            && (string) ($cached['sig'] ?? '') === $sig) {
            $GLOBALS['auragold_perm_runtime_state'] = $cached;
            return $cached;
        }
    }

    if ($sig === '0:0') {
        $state = $default;
        $state['user_id']   = $uid;
        $state['branch_id'] = $eff;
        $state['sig']       = $sig;
        $_SESSION[$sessKey] = $state;
        $GLOBALS['auragold_perm_runtime_state'] = $state;
        return $state;
    }

    $map = auragold_permission_effective_map_for_session_user($conn, $uid);
    $state = [
        'legacy_allow_all' => false,
        'map'              => is_array($map) ? $map : [],
        'user_id'          => $uid,
        'branch_id'        => $eff,
        'sig'              => $sig,
    ];
    $_SESSION[$sessKey] = $state;
    $GLOBALS['auragold_perm_runtime_state'] = $state;
    return $state;
}

/**
 * Whether the logged-in tbl_users account may perform $permKey.
 * Branch / non-tbl_users sessions: always true (not user-wise restricted here).
 * If the user has no rows in tbl_user_permission_grants: allow all (legacy).
 * If any row exists: missing key = denied; explicit 1 = allow, 0 = deny.
 */
function auragold_user_can($permKey)
{
    $state = auragold_permission_runtime_state();
    if (!empty($state['legacy_allow_all'])) {
        return true;
    }

    $key = trim((string) $permKey);
    if ($key === '') {
        return false;
    }
    $map = $state['map'] ?? [];
    if (!array_key_exists($key, $map)) {
        return false;
    }
    return !empty($map[$key]);
}

/**
 * @return int
 */
function auragold_permission_grants_count_for_user_branch($conn, $userId, $branchId)
{
    $userId   = (int) $userId;
    $branchId = (int) $branchId;
    if ($userId <= 0 || !$conn || !($conn instanceof mysqli)) {
        return 0;
    }
    $res = mysqli_query(
        $conn,
        'SELECT COUNT(*) AS c FROM tbl_user_permission_grants WHERE user_id = ' . $userId . ' AND branch_id = ' . $branchId
    );
    if ($res && ($r = mysqli_fetch_assoc($res))) {
        return (int) ($r['c'] ?? 0);
    }
    return 0;
}
