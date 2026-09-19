<?php
/**
 * Login: tbl_users live in the database for the selected branch (see login_credential_connections.php).
 * Registry ($conn_master) is used for tbl_branches lookups; password checks use that branch’s MySQL schema.
 *
 * @param string $username_raw   Trimmed username from the form (not esc() output).
 * @param string $password_plain Plain password from POST (trimmed).
 * @param int    $login_branch_id Selected branch (0 = default app DB / “Main”, same as DB_NAME).
 * @return array{success:bool,message:string}
 */
require_once __DIR__ . '/branch_credentials.php';
require_once __DIR__ . '/auragold_superadmin.php';
require_once __DIR__ . '/login_credential_connections.php';
function auragold_row_password_field(array $row) {
    foreach ($row as $k => $v) {
        if (strcasecmp((string) $k, 'password') === 0) {
            if ($v === null) {
                return '';
            }
            return trim((string) $v);
        }
    }
    return '';
}

function auragold_user_active(array $userRow) {
    $hasStatus = false;
    foreach ($userRow as $k => $v) {
        if (strcasecmp((string) $k, 'status') !== 0) {
            continue;
        }
        $hasStatus = true;
        if ($v === null || $v === '') {
            return false;
        }
        $s = is_string($v) ? trim(strtolower($v)) : (string) $v;
        if ($s === '1' || $s === 'true' || $s === 'yes' || $s === 'active') {
            return true;
        }
        if (is_numeric($v) && (int) $v === 1) {
            return true;
        }
        return false;
    }
    return !$hasStatus;
}

/**
 * Branch Login page (index.php): role follows selected branch in the form, not working DB after switch.
 * login_branch_id 0 = Main / default DB → admin menu; &gt; 0 = sub selected → restricted menu.
 * tbl_users logins are always admin. Call only after successful auragold_attempt_login from index flow.
 */
function auragold_apply_login_page_user_role($login_branch_id) {
    $login_branch_id = (int) $login_branch_id;
    $src             = isset($_SESSION['login_source']) ? (string) $_SESSION['login_source'] : '';
    if ($src === 'user') {
        $_SESSION['user_role'] = 'admin';
        return;
    }
    if ($src === 'branch') {
        $_SESSION['user_role'] = ($login_branch_id === 0) ? 'admin' : 'branch';
    }
}

/**
 * @return list<string>
 */
function auragold_login_parse_branch_label_strings($raw) {
    if ($raw === null || $raw === '') {
        return [];
    }
    $parts = preg_split('/[,|]/', (string) $raw);
    if (!is_array($parts)) {
        return [];
    }
    $out = [];
    foreach ($parts as $p) {
        $t = trim((string) $p);
        if ($t !== '') {
            $out[] = $t;
        }
    }
    return $out;
}

/**
 * Sub-branches under registry mains (same rules as index.php branch dropdown).
 *
 * @return list<array{id:int,name:string}>
 */
function auragold_login_collect_registry_sub_branches() {
    $out   = [];
    $mains = getListMaster(
        'SELECT id, name, code FROM tbl_branches WHERE IFNULL(main_branch_id, 0) = 0 ORDER BY id ASC'
    );
    if (!is_array($mains)) {
        return $out;
    }
    foreach ($mains as $main) {
        $mid = (int) ($main['id'] ?? 0);
        if ($mid <= 0) {
            continue;
        }
        $lb_subs_raw = getListMaster(
            'SELECT * FROM tbl_branches WHERE main_branch_id = ' . $mid . ' ORDER BY id ASC'
        );
        if (!is_array($lb_subs_raw)) {
            $lb_subs_raw = [];
        }
        $lb_subs = array_values(array_filter($lb_subs_raw, 'auragold_tbl_branch_row_is_active'));
        foreach ($lb_subs as $sub) {
            $sid = (int) ($sub['id'] ?? 0);
            if ($sid <= 0) {
                continue;
            }
            $nm = trim((string) ($sub['name'] ?? ''));
            if ($nm === '') {
                $nm = 'Branch #' . $sid;
            }
            $out[] = ['id' => $sid, 'name' => $nm];
        }
    }
    return $out;
}

/**
 * Registry “main” rows (for matching branch_labels to main / login value 0).
 *
 * @return list<array{id:int,name:string}>
 */
function auragold_login_collect_registry_main_branches() {
    $rows = getListMaster(
        'SELECT id, name FROM tbl_branches WHERE IFNULL(main_branch_id, 0) = 0 ORDER BY id ASC'
    );
    if (!is_array($rows)) {
        return [];
    }
    $out = [];
    foreach ($rows as $r) {
        $id = (int) ($r['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $nm = trim((string) ($r['name'] ?? ''));
        if ($nm === '') {
            $nm = 'Main #' . $id;
        }
        $out[] = ['id' => $id, 'name' => $nm];
    }
    return $out;
}

/**
 * Branch dropdown filter for tbl_users (branch_labels NULL/empty = all branches).
 *
 * @return array{scope:string,allow_main:bool,sub_ids:int[]}
 */
function auragold_login_branch_filter_for_user_row(?array $user) {
    $labelsRaw = '';
    if (is_array($user)) {
        foreach ($user as $k => $v) {
            if (strcasecmp((string) $k, 'branch_labels') === 0) {
                $labelsRaw = trim((string) $v);
                break;
            }
        }
    }
    $labels = auragold_login_parse_branch_label_strings($labelsRaw);

    $idsFromUser = [];
    if (is_array($user)) {
        if (!function_exists('auragold_um_parse_branch_ids_string')) {
            require_once __DIR__ . '/user_management_schema.php';
        }
        foreach ($user as $k => $v) {
            if (strcasecmp((string) $k, 'user_branch_ids') === 0) {
                foreach (auragold_um_parse_branch_ids_string(trim((string) $v)) as $bid) {
                    $idsFromUser[$bid] = $bid;
                }
                break;
            }
        }
    }

    if ($labels === [] && $idsFromUser === []) {
        return ['scope' => 'all', 'allow_main' => true, 'sub_ids' => []];
    }

    if ($labels === [] && $idsFromUser !== []) {
        $allow_main = false;
        $sub_ids    = array_values($idsFromUser);
        foreach ($sub_ids as $bid) {
            $br = getRecordMaster('SELECT main_branch_id FROM tbl_branches WHERE id = ' . (int) $bid . ' LIMIT 1');
            if ($br && (int) ($br['main_branch_id'] ?? 0) === 0) {
                $allow_main = true;
                break;
            }
        }
        return [
            'scope'      => 'filter',
            'allow_main' => $allow_main,
            'sub_ids'    => $sub_ids,
        ];
    }

    $norm = [];
    foreach ($labels as $lb) {
        $norm[strtolower($lb)] = true;
    }

    $allow_main = false;
    foreach (auragold_login_collect_registry_main_branches() as $m) {
        if (!empty($norm[strtolower($m['name'])])) {
            $allow_main = true;
            break;
        }
    }

    $sub_ids = [];
    foreach (auragold_login_collect_registry_sub_branches() as $s) {
        if (!empty($norm[strtolower($s['name'])])) {
            $sub_ids[] = (int) $s['id'];
        }
    }
    foreach ($idsFromUser as $bid) {
        $sub_ids[] = (int) $bid;
    }
    $sub_ids = array_values(array_unique($sub_ids));

    foreach ($idsFromUser as $bid) {
        $br = getRecordMaster('SELECT main_branch_id FROM tbl_branches WHERE id = ' . (int) $bid . ' LIMIT 1');
        if ($br && (int) ($br['main_branch_id'] ?? 0) === 0) {
            $allow_main = true;
            break;
        }
    }

    return [
        'scope'      => 'filter',
        'allow_main' => $allow_main,
        'sub_ids'    => $sub_ids,
    ];
}

/**
 * Label for login_branch_id = 0 (“Main” / default app DB).
 * Must be the first registry main row’s name only: id=0 always resolves to that row’s operational DB
 * (see auragold_login_expected_db_name_for_branch_id(0)). Using user_branch_ids / branch_labels here caused
 * the same display name as another main (e.g. “Mumbai Branch” twice with different db_name in parentheses).
 *
 * @param array{scope:string,allow_main:bool,sub_ids:int[]} $filter
 */
function auragold_login_resolve_main_dropdown_label(array $user, array $filter): string {
    $mains = auragold_login_collect_registry_main_branches();
    if (!empty($mains[0]['name'])) {
        return trim((string) $mains[0]['name']);
    }
    return 'Main';
}

/**
 * After password check for tbl_users: ensure login branch matches branch_labels.
 *
 * @return array{ok:bool,message:string}
 */
/**
 * Validate username + password only (no branch check). Used by login AJAX before branch list is shown.
 *
 * @return array|null Active user row on success, null on failure.
 */
function auragold_verify_user_credentials($username_raw, $password_plain) {
    global $conn;
    $password_plain = trim((string) $password_plain);
    $username_raw   = trim((string) $username_raw);
    if ($username_raw === '' || $password_plain === '') {
        return null;
    }
    if (!$conn || !($conn instanceof mysqli)) {
        return null;
    }
    return auragold_verify_user_on_mysqli($conn, $username_raw, $password_plain);
}

/**
 * Options for the login branch dropdown after credentials are verified (matches assigned branch_labels).
 *
 * @return list<array{id:int,label:string}>
 */
function auragold_login_build_branch_options_for_user(array $user) {
    $filter     = auragold_login_branch_filter_for_user_row($user);
    $main_label = auragold_login_resolve_main_dropdown_label($user, $filter);

    $all_subs = auragold_login_collect_registry_sub_branches();
    $out      = [];

    if ($filter['scope'] === 'all') {
        $out[] = ['id' => 0, 'label' => $main_label];
        // login_branch_id=0 maps to the first registry main; list every other main row so multi-HQ setups
        // (e.g. Mumbai id 33, main_branch_id=0) appear and resolve db_name from tbl_branches — not DB_NAME.
        $mains = auragold_login_collect_registry_main_branches();
        $firstMainId = isset($mains[0]['id']) ? (int) $mains[0]['id'] : 0;
        foreach ($mains as $mx) {
            $mid = (int) ($mx['id'] ?? 0);
            if ($mid <= 0 || ($firstMainId > 0 && $mid === $firstMainId)) {
                continue;
            }
            $nm = trim((string) ($mx['name'] ?? ''));
            if ($nm === '') {
                $nm = 'Main #' . $mid;
            }
            $out[] = ['id' => $mid, 'label' => $nm];
        }
        foreach ($all_subs as $s) {
            $sid = (int) ($s['id'] ?? 0);
            if ($sid <= 0) {
                continue;
            }
            $nm = trim((string) ($s['name'] ?? ''));
            if ($nm === '') {
                $nm = 'Branch #' . $sid;
            }
            $out[] = ['id' => $sid, 'label' => $nm];
        }
        return $out;
    }

    if (!empty($filter['allow_main'])) {
        $out[] = ['id' => 0, 'label' => $main_label];
    }

    $allow = [];
    foreach ($filter['sub_ids'] ?? [] as $sid) {
        $allow[(int) $sid] = true;
    }
    foreach ($all_subs as $s) {
        $sid = (int) ($s['id'] ?? 0);
        if ($sid <= 0 || empty($allow[$sid])) {
            continue;
        }
        $nm = trim((string) ($s['name'] ?? ''));
        if ($nm === '') {
            $nm = 'Branch #' . $sid;
        }
        $out[] = ['id' => $sid, 'label' => $nm];
    }
    // user_branch_ids may reference a main row (main_branch_id=0); those never appear in all_subs.
    $seen = [];
    foreach ($out as $o) {
        $seen[(int) ($o['id'] ?? 0)] = true;
    }
    foreach ($filter['sub_ids'] ?? [] as $bid) {
        $bid = (int) $bid;
        if ($bid <= 0 || empty($allow[$bid]) || !empty($seen[$bid])) {
            continue;
        }
        $brow = function_exists('auragold_registry_tbl_branches_row_by_id')
            ? auragold_registry_tbl_branches_row_by_id($bid)
            : null;
        if (!$brow && function_exists('getRecordMaster')) {
            $brow = getRecordMaster('SELECT * FROM tbl_branches WHERE id = ' . $bid . ' LIMIT 1');
        }
        if (!$brow || (int) ($brow['main_branch_id'] ?? 0) !== 0) {
            continue;
        }
        $nm = trim((string) ($brow['name'] ?? ''));
        if ($nm === '') {
            $nm = 'Branch #' . $bid;
        }
        $out[] = ['id' => $bid, 'label' => $nm];
        $seen[$bid] = true;
    }

    return $out;
}

function auragold_validate_user_login_branch_choice($login_branch_id, array $user) {
    if (auragold_user_row_is_superadmin($user)) {
        return ['ok' => true, 'message' => ''];
    }
    $login_branch_id = (int) $login_branch_id;

    if (!function_exists('auragold_um_parse_branch_ids_string')) {
        require_once __DIR__ . '/user_management_schema.php';
    }
    $explicitIds = auragold_um_parse_branch_ids_string(
        (function () use ($user) {
            foreach ($user as $k => $v) {
                if (strcasecmp((string) $k, 'user_branch_ids') === 0) {
                    return (string) $v;
                }
            }
            return '';
        })()
    );
    if ($login_branch_id > 0 && $explicitIds !== [] && in_array($login_branch_id, $explicitIds, true)) {
        return ['ok' => true, 'message' => ''];
    }
    if ($login_branch_id === 0 && $explicitIds !== []) {
        foreach ($explicitIds as $mid) {
            $r = getRecordMaster('SELECT main_branch_id FROM tbl_branches WHERE id = ' . (int) $mid . ' LIMIT 1');
            if ($r && (int) ($r['main_branch_id'] ?? 0) === 0) {
                return ['ok' => true, 'message' => ''];
            }
        }
    }

    $filter = auragold_login_branch_filter_for_user_row($user);
    if ($filter['scope'] === 'all') {
        return ['ok' => true, 'message' => ''];
    }

    if ($login_branch_id === 0) {
        if (!empty($filter['allow_main'])) {
            return ['ok' => true, 'message' => ''];
        }
        return ['ok' => false, 'message' => 'Select the branch you are assigned to.'];
    }

    if ($filter['sub_ids'] !== [] && !in_array($login_branch_id, $filter['sub_ids'], true)) {
        return ['ok' => false, 'message' => 'That branch is not assigned to your login.'];
    }

    if (!empty($filter['sub_ids']) && in_array($login_branch_id, $filter['sub_ids'], true)) {
        return ['ok' => true, 'message' => ''];
    }

    $row = getRecordMaster('SELECT id, name FROM tbl_branches WHERE id = ' . $login_branch_id . ' LIMIT 1');
    if (!$row) {
        return ['ok' => false, 'message' => 'Invalid branch selection.'];
    }
    $name = trim((string) ($row['name'] ?? ''));
    $labs = auragold_login_parse_branch_label_strings(
        (function () use ($user) {
            foreach ($user as $k => $v) {
                if (strcasecmp((string) $k, 'branch_labels') === 0) {
                    return (string) $v;
                }
            }
            return '';
        })()
    );
    foreach ($labs as $lb) {
        if (strcasecmp($name, $lb) === 0) {
            return ['ok' => true, 'message' => ''];
        }
    }

    return ['ok' => false, 'message' => 'That branch is not assigned to your login.'];
}

/**
 * Same rules as the login branch dropdown, but the target is tbl_branches.id.
 * Registry “main” rows use login value 0 in validation, not the main row’s numeric id.
 *
 * @return array{ok:bool,message:string}
 */
function auragold_validate_user_branch_switch_target(int $branchRowId, array $user) {
    $branchRowId = (int) $branchRowId;
    if ($branchRowId <= 0 || !function_exists('getRecordMaster')) {
        return ['ok' => false, 'message' => 'Invalid branch.'];
    }
    $row = getRecordMaster('SELECT id, main_branch_id FROM tbl_branches WHERE id = ' . $branchRowId . ' LIMIT 1');
    if (!$row) {
        return ['ok' => false, 'message' => 'Branch not found.'];
    }
    $isMain     = (int) ($row['main_branch_id'] ?? 0) === 0;
    $loginEquiv = $isMain ? 0 : $branchRowId;

    return auragold_validate_user_login_branch_choice($loginEquiv, $user);
}

function auragold_attempt_login($username_raw, $password_plain, $login_branch_id = 0) {
    $login_branch_id = (int) $login_branch_id;
    $username_raw     = trim((string) $username_raw);
    $password_plain   = trim((string) $password_plain);
    if ($username_raw === '' || $password_plain === '') {
        return ['success' => false, 'message' => 'Username & Password are required'];
    }

    $user = auragold_verify_user_credentials_for_login_branch($username_raw, $password_plain, $login_branch_id);
    if (!$user) {
        return ['success' => false, 'message' => 'Invalid username or password'];
    }

    $branchOk = auragold_validate_user_login_branch_choice($login_branch_id, $user);
    if (empty($branchOk['ok'])) {
        return [
            'success' => false,
            'message' => $branchOk['message'] !== '' ? $branchOk['message'] : 'Invalid branch for this user.',
        ];
    }

    $_SESSION['Admin'] = $user;
    $uid = 0;
    foreach ($user as $k => $v) {
        if (strcasecmp((string) $k, 'id') === 0) {
            $uid = (int) $v;
            break;
        }
    }
    $_SESSION['user_id']        = $uid;
    $fn = $user['Fname'] ?? $user['fname'] ?? '';
    $ln = $user['Lname'] ?? $user['lname'] ?? '';
    $_SESSION['name']           = trim($fn . ' ' . $ln);
    $_SESSION['login_source']   = 'user';
    $_SESSION['login_type']     = 'admin';
    $_SESSION['branch_is_main'] = 1;
    unset($_SESSION['branch_id']);
    unset($_SESSION['working_db'], $_SESSION['working_branch_id'], $_SESSION['working_branch_name']);

    return ['success' => true, 'message' => 'Login success'];
}
