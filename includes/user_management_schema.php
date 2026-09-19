<?php

/**
 * Optional columns on registry `tbl_users` for User Management UI (role, branches label, 2FA flag).
 */
function auragold_um_ensure_column($conn, $table, $column, $add_sql_fragment)
{
    if (!$conn || !($conn instanceof mysqli)) {
        return;
    }
    $t = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
    $c = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $column);
    if ($t === '' || $c === '') {
        return;
    }
    $q = mysqli_query($conn, "SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
    if ($q && mysqli_num_rows($q) === 0) {
        mysqli_query($conn, "ALTER TABLE `{$t}` ADD COLUMN {$add_sql_fragment}");
    }
}

function auragold_ensure_user_management_columns($conn)
{
    if (!$conn || !($conn instanceof mysqli)) {
        return;
    }
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    auragold_um_ensure_column(
        $conn,
        'tbl_users',
        'user_role',
        "`user_role` VARCHAR(64) NULL DEFAULT 'Admin' AFTER `Status`"
    );
    auragold_um_ensure_column(
        $conn,
        'tbl_users',
        'branch_labels',
        "`branch_labels` VARCHAR(500) NULL DEFAULT NULL AFTER `user_role`"
    );
    auragold_um_ensure_column(
        $conn,
        'tbl_users',
        'two_factor_enabled',
        "`two_factor_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `branch_labels`"
    );
    auragold_um_ensure_column(
        $conn,
        'tbl_users',
        'user_branch_ids',
        "`user_branch_ids` VARCHAR(500) NULL DEFAULT NULL AFTER `two_factor_enabled`"
    );
    auragold_um_ensure_column(
        $conn,
        'tbl_users',
        'monthly_salary',
        "`monthly_salary` DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER `user_branch_ids`"
    );
    auragold_um_ensure_column(
        $conn,
        'tbl_users',
        'department_id',
        "`department_id` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `monthly_salary`"
    );
    auragold_um_ensure_column(
        $conn,
        'tbl_users',
        'designation_id',
        "`designation_id` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `department_id`"
    );
}

/**
 * @return int[]
 */
function auragold_um_parse_branch_ids_string($raw)
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return [];
    }
    $out = [];
    foreach (preg_split('/\s*,\s*/', $raw, -1, PREG_SPLIT_NO_EMPTY) as $p) {
        $n = (int) $p;
        if ($n > 0) {
            $out[$n] = $n;
        }
    }
    return array_values($out);
}

/**
 * Comma-separated branch ids for storage on tbl_users.user_branch_ids.
 */
function auragold_um_normalize_branch_ids_list(array $ids)
{
    $out = [];
    foreach ($ids as $x) {
        $n = (int) $x;
        if ($n > 0) {
            $out[$n] = $n;
        }
    }
    sort($out, SORT_NUMERIC);
    return implode(',', $out);
}

/**
 * Main branch rows with active sub-branches for the User Management picker.
 * Scoped like branches.php: one main + its subs when not superadmin at registry.
 *
 * @return array<int, array{main: array{id:int,name:string}, subs: array<int, array{id:int,name:string}>}>
 */
function auragold_um_branch_picker_groups($conn, $conn_master)
{
    $brConn = $conn_master;
    $useMaster = true;
    if ($conn && $conn instanceof mysqli) {
        $tb = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_branches'");
        if ($tb && mysqli_num_rows($tb) > 0) {
            $brConn = $conn;
            $useMaster = false;
        }
        if ($tb) {
            mysqli_free_result($tb);
        }
    }
    if (!$brConn || !($brConn instanceof mysqli)) {
        return [];
    }

    $fetchList = static function ($sql) use ($useMaster, $brConn) {
        if ($useMaster && function_exists('getListMaster')) {
            $rows = getListMaster($sql);
        } elseif (function_exists('getList')) {
            $rows = getList($sql);
        } else {
            $rows = [];
        }

        return is_array($rows) ? $rows : [];
    };

    if (!function_exists('auragold_branches_page_list_scope_main_id')) {
        require_once __DIR__ . '/branch_working_context.php';
    }

    $scopeMain = auragold_branches_page_list_scope_main_id();
    $statusSql = "(status = 1 OR status = '1')";
    $hiddenUserSql = "LOWER(TRIM(IFNULL(username,''))) <> 'superbranch'";

    if ($scopeMain > 0) {
        $allMains = $fetchList(
            'SELECT id, name FROM tbl_branches WHERE main_branch_id = 0 AND id = ' . (int) $scopeMain
            . ' AND ' . $statusSql . ' ORDER BY id ASC'
        );
        $allSubs = $fetchList(
            'SELECT id, name, main_branch_id FROM tbl_branches WHERE main_branch_id = ' . (int) $scopeMain
            . ' AND ' . $statusSql . ' AND ' . $hiddenUserSql . ' ORDER BY id ASC'
        );
    } else {
        $allMains = $fetchList(
            'SELECT id, name FROM tbl_branches WHERE main_branch_id = 0 AND ' . $statusSql
            . ' AND ' . $hiddenUserSql . ' ORDER BY id ASC'
        );
        $allSubs = $fetchList(
            'SELECT id, name, main_branch_id FROM tbl_branches b WHERE b.main_branch_id > 0 '
            . 'AND ' . $statusSql . ' AND LOWER(TRIM(IFNULL(b.username,\'\'))) <> \'superbranch\' '
            . 'AND EXISTS (SELECT 1 FROM tbl_branches m WHERE m.id = b.main_branch_id AND IFNULL(m.main_branch_id, 0) = 0) '
            . 'ORDER BY b.main_branch_id ASC, b.id ASC'
        );
    }

    $subsByMain = [];
    foreach ($allSubs as $sub) {
        $mid = (int) ($sub['main_branch_id'] ?? 0);
        if ($mid <= 0) {
            continue;
        }
        if (!isset($subsByMain[$mid])) {
            $subsByMain[$mid] = [];
        }
        $subsByMain[$mid][] = $sub;
    }

    $groups = [];
    foreach ($allMains as $main) {
        $mainId = (int) ($main['id'] ?? 0);
        $mainName = trim((string) ($main['name'] ?? ''));
        if ($mainId <= 0 || $mainName === '') {
            continue;
        }

        $subs = [];
        foreach ($subsByMain[$mainId] ?? [] as $sub) {
            $subId = (int) ($sub['id'] ?? 0);
            $subName = trim((string) ($sub['name'] ?? ''));
            if ($subId <= 0 || $subName === '') {
                continue;
            }
            $subs[] = ['id' => $subId, 'name' => $subName];
        }

        $groups[] = [
            'main' => ['id' => $mainId, 'name' => $mainName],
            'subs' => $subs,
        ];
    }

    if (empty($groups)) {
        if (!function_exists('auragold_registry_main_branch_id_for_login')) {
            require_once __DIR__ . '/auragold_branch_data_scope.php';
        }
        $fallbackMainId = auragold_registry_main_branch_id_for_login();
        if ($fallbackMainId > 0) {
            $groups[] = [
                'main' => ['id' => $fallbackMainId, 'name' => 'Main Branch'],
                'subs' => [],
            ];
        }
    }

    return $groups;
}

/**
 * Resolve display string for User Management "Branch" column.
 *
 * @param mysqli $conn Master/registry connection.
 * @param array  $row   tbl_users row.
 */
function auragold_um_display_branch_names_for_user_row($conn, array $row)
{
    $ids = auragold_um_parse_branch_ids_string((string) ($row['user_branch_ids'] ?? ''));
    if (!empty($ids) && $conn && $conn instanceof mysqli) {
        $in = implode(',', array_map('intval', $ids));
        $names = [];
        $res = mysqli_query($conn, 'SELECT id, name FROM tbl_branches WHERE id IN (' . $in . ') ORDER BY id ASC');
        if ($res) {
            $byId = [];
            while ($r = mysqli_fetch_assoc($res)) {
                $byId[(int) ($r['id'] ?? 0)] = trim((string) ($r['name'] ?? ''));
            }
            foreach ($ids as $id) {
                $nm = $byId[$id] ?? '';
                if ($nm !== '') {
                    $names[] = $nm;
                }
            }
        }
        if (!empty($names)) {
            return implode(', ', $names);
        }
    }
    $legacy = trim((string) ($row['branch_labels'] ?? ''));
    return $legacy !== '' ? $legacy : 'Main Branch';
}

/**
 * Sub-branch sessions should only see/manage users assigned to that branch; main/registry branch rows see everyone.
 *
 * @return array{id:int,name:string}|null null = no restriction (main context)
 */
function auragold_um_user_management_scope_sub_branch($conn_master)
{
    if (!$conn_master || !($conn_master instanceof mysqli)) {
        return null;
    }
    if (!function_exists('auragold_effective_branch_id')) {
        require_once __DIR__ . '/auragold_branch_data_scope.php';
    }
    $eff = (int) auragold_effective_branch_id();
    if ($eff <= 0) {
        return null;
    }
    $br = getRecordMaster('SELECT id, name, main_branch_id FROM tbl_branches WHERE id = ' . $eff . ' LIMIT 1');
    if (!$br) {
        return null;
    }
    if ((int) ($br['main_branch_id'] ?? 0) === 0) {
        return null;
    }
    return [
        'id'   => (int) ($br['id'] ?? 0),
        'name' => trim((string) ($br['name'] ?? '')),
    ];
}

/**
 * Whether a tbl_users row may be viewed or changed under the current branch scope.
 */
function auragold_um_user_row_in_management_scope($conn_master, array $userRow)
{
    $scope = auragold_um_user_management_scope_sub_branch($conn_master);
    if ($scope === null) {
        return true;
    }
    $bid = (int) $scope['id'];
    $myUid = (int) ($_SESSION['user_id'] ?? 0);
    if ($myUid > 0 && (int) ($userRow['id'] ?? 0) === $myUid) {
        return true;
    }
    foreach (auragold_um_parse_branch_ids_string((string) ($userRow['user_branch_ids'] ?? '')) as $x) {
        if ((int) $x === $bid) {
            return true;
        }
    }
    return false;
}

/**
 * When the session has a login/working branch (effective id &gt; 0), permission-management is locked to that branch only
 * (no Default / other branches, only users assigned to this branch).
 *
 * @return int 0 = all-locations HQ context — may edit Default and any branch
 */
function auragold_um_permission_locked_branch_id($conn_master)
{
    if (!$conn_master || !($conn_master instanceof mysqli)) {
        return 0;
    }
    if (!function_exists('auragold_effective_branch_id')) {
        require_once __DIR__ . '/auragold_branch_data_scope.php';
    }
    $eff = (int) auragold_effective_branch_id();

    return $eff > 0 ? $eff : 0;
}

/**
 * Extra AND for permission-management user list: assigned to locked branch via user_branch_ids.
 */
function auragold_um_sql_users_permission_page_and($conn_master, $tableAlias = '')
{
    $lock = auragold_um_permission_locked_branch_id($conn_master);
    if ($lock <= 0) {
        return '';
    }

    return auragold_um_sql_users_branch_id_in_list($lock, $tableAlias);
}

/**
 * Whether this tbl_users row may have permissions edited for the current session (branch-locked or HQ).
 */
function auragold_um_user_row_allowed_for_permission_page($conn_master, array $userRow)
{
    if (!auragold_um_user_row_in_management_scope($conn_master, $userRow)) {
        return false;
    }
    $lock = auragold_um_permission_locked_branch_id($conn_master);
    if ($lock <= 0) {
        return true;
    }
    $myUid = (int) ($_SESSION['user_id'] ?? 0);
    if ($myUid > 0 && (int) ($userRow['id'] ?? 0) === $myUid) {
        return true;
    }
    foreach (auragold_um_parse_branch_ids_string((string) ($userRow['user_branch_ids'] ?? '')) as $x) {
        if ((int) $x === $lock) {
            return true;
        }
    }

    return false;
}

/**
 * SQL AND fragment for tbl_users (empty string = no extra filter).
 *
 * @param string $tableAlias e.g. "u" for tbl_users u
 */
function auragold_um_sql_users_scope_and($conn_master, $tableAlias = '')
{
    $scope = auragold_um_user_management_scope_sub_branch($conn_master);
    if ($scope === null) {
        return '';
    }
    $bid = (int) $scope['id'];
    $p   = $tableAlias !== '' ? $tableAlias . '.' : '';

    $col = $p . 'user_branch_ids';
    // Sub-branch panel: only users who have this branch id in user_branch_ids (comma list).
    // Legacy branch_labels text matching was removed — it let parent-only or ambiguous rows appear
    // under a sub-branch when labels mentioned the sub name without a consistent id list.
    // Do not add "OR id = logged-in user" here: a main-branch-only user logging in with a sub-branch
    // context would still match themselves and appear in this list despite not being assigned to that sub-branch.
    $matchIds = '(FIND_IN_SET(\'' . $bid . '\', REPLACE(IFNULL(' . $col . ',\'\'), \' \', \'\')) > 0)';

    return ' AND (' . $matchIds . ')';
}

/**
 * SQL AND fragment: tbl_users.user_branch_ids contains a specific branch id (explicit list, not session scope).
 *
 * @param string $tableAlias e.g. "u" for tbl_users u
 */
function auragold_um_sql_users_branch_id_in_list($branchId, $tableAlias = '')
{
    $bid = (int) $branchId;
    if ($bid <= 0) {
        return '';
    }
    $p = $tableAlias !== '' ? $tableAlias . '.' : '';
    $col = $p . 'user_branch_ids';
    return ' AND (FIND_IN_SET(\'' . $bid . '\', REPLACE(IFNULL(' . $col . ',\'\'), \' \', \'\')) > 0)';
}

/**
 * Run a SELECT on the operational mysqli (tbl_users lives on $conn after branch login).
 *
 * @return list<array<string,mixed>>
 */
function auragold_um_mysqli_select_all($mysqli, $sql)
{
    if (!$mysqli || !($mysqli instanceof mysqli)) {
        return [];
    }
    $rows = [];
    $res  = @mysqli_query($mysqli, $sql);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $rows[] = $row;
        }
    }
    return $rows;
}

/**
 * Active tbl_users rows assigned to a branch via user_branch_ids (operational DB).
 *
 * @return list<array<string,mixed>>
 */
function auragold_list_tbl_users_for_branch_assignment($conn_master, $branchId)
{
    global $conn;
    $dbc = (isset($conn) && $conn instanceof mysqli) ? $conn : $conn_master;
    if (!$dbc || !($dbc instanceof mysqli)) {
        return [];
    }
    $bid = (int) $branchId;
    if ($bid <= 0) {
        return [];
    }
    auragold_ensure_user_management_columns($dbc);
    $sql = "SELECT id, Fname, Lname, Username FROM tbl_users WHERE Status = '1'"
        . auragold_um_sql_users_branch_id_in_list($bid)
        . ' ORDER BY Fname ASC, Lname ASC, Username ASC';

    return auragold_um_mysqli_select_all($dbc, $sql);
}

/**
 * Display names for users assigned to a branch (Assign Inventory, etc.).
 *
 * @return list<string>
 */
function auragold_sales_person_names_for_branch_id($conn_master, $branchId)
{
    $rows = auragold_list_tbl_users_for_branch_assignment($conn_master, $branchId);
    $out  = [];
    foreach ($rows as $u) {
        $fn = trim((string) ($u['Fname'] ?? ''));
        $ln = trim((string) ($u['Lname'] ?? ''));
        $disp = trim($fn . ' ' . $ln);
        if ($disp === '') {
            $disp = trim((string) ($u['Username'] ?? ''));
        }
        if ($disp === '') {
            continue;
        }
        $out[] = $disp;
    }
    return array_values(array_unique($out));
}

/**
 * Active tbl_users rows for Sales Person dropdowns (operational DB; branch-scoped at sub-branches).
 *
 * @return list<array<string,mixed>>
 */
function auragold_list_tbl_users_for_sales_person_dropdown($conn_master)
{
    global $conn;
    $dbc = (isset($conn) && $conn instanceof mysqli) ? $conn : $conn_master;
    if (!$dbc || !($dbc instanceof mysqli)) {
        return [];
    }
    auragold_ensure_user_management_columns($dbc);
    $sql = "SELECT id, Fname, Lname, Username FROM tbl_users WHERE Status = '1'"
        . auragold_um_sql_users_scope_and($conn_master)
        . ' ORDER BY Fname ASC, Lname ASC, Username ASC';

    return auragold_um_mysqli_select_all($dbc, $sql);
}

/**
 * Display names for Sales Person / purchase person dropdown options (same format as legacy sale-invoice.php).
 *
 * @return list<string>
 */
function auragold_sales_person_user_display_names($conn_master)
{
    $rows = auragold_list_tbl_users_for_sales_person_dropdown($conn_master);
    $out  = [];
    foreach ($rows as $u) {
        $fn = trim((string) ($u['Fname'] ?? ''));
        $ln = trim((string) ($u['Lname'] ?? ''));
        $disp = trim($fn . ' ' . $ln);
        if ($disp === '') {
            $disp = trim((string) ($u['Username'] ?? ''));
        }
        if ($disp === '') {
            continue;
        }
        $out[] = $disp;
    }
    return array_values(array_unique($out));
}

/**
 * Candidate labels for matching the logged-in user to a Sales Person <select> option.
 * Order: full name, username, first name, session name.
 *
 * @return list<string>
 */
function auragold_logged_in_sales_person_match_candidates()
{
    $out = [];
    $admin = (isset($_SESSION['Admin']) && is_array($_SESSION['Admin'])) ? $_SESSION['Admin'] : [];
    $fn = trim((string) ($admin['Fname'] ?? $admin['fname'] ?? ''));
    $ln = trim((string) ($admin['Lname'] ?? $admin['lname'] ?? ''));
    $full = trim($fn . ' ' . $ln);
    $user = trim((string) ($admin['Username'] ?? $admin['username'] ?? ''));
    $sessName = trim((string) ($_SESSION['name'] ?? ''));

    foreach ([$full, $user, $fn, $sessName] as $c) {
        $c = trim((string) $c);
        if ($c === '') {
            continue;
        }
        $dupe = false;
        foreach ($out as $ex) {
            if (strcasecmp($ex, $c) === 0) {
                $dupe = true;
                break;
            }
        }
        if (!$dupe) {
            $out[] = $c;
        }
    }
    return $out;
}

/**
 * Pick logged-in user display name if it appears in $sales_person_users; otherwise ''.
 *
 * @param list<string> $sales_person_users
 */
function auragold_default_sales_person_from_login(array $sales_person_users): string
{
    $cands = auragold_logged_in_sales_person_match_candidates();
    if ($cands === [] || $sales_person_users === []) {
        return '';
    }
    foreach ($cands as $cand) {
        foreach ($sales_person_users as $sp) {
            if (strcasecmp(trim((string) $sp), $cand) === 0) {
                return trim((string) $sp);
            }
        }
    }
    return '';
}
