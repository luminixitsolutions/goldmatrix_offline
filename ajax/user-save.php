<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/session_login_type.php';
require_once __DIR__ . '/../includes/user_management_schema.php';
require_once __DIR__ . '/../includes/auragold_employee_management_schema.php';
require_once __DIR__ . '/../includes/roles_schema.php';

if (empty($_SESSION['Admin']) || !auragold_session_is_admin_login_type()) {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

auragold_ensure_user_management_columns($conn);
auragold_ensure_roles_table($conn_master);
$employeeBranchId = auragold_em_resolve_branch_id();
auragold_em_ensure_tables($conn);
auragold_em_seed_defaults($conn, $employeeBranchId);

$raw = file_get_contents('php://input');
$in  = json_decode($raw, true);
if (!is_array($in)) {
    $in = $_POST;
}

$id = isset($in['id']) ? (int) $in['id'] : 0;
$is_update = $id > 0;

$full_name = isset($in['full_name']) ? trim((string) $in['full_name']) : '';
$mail      = isset($in['mail_id']) ? trim((string) $in['mail_id']) : '';
$cc        = isset($in['phone_cc']) ? trim((string) $in['phone_cc']) : '+91';
$phone_num = isset($in['phone']) ? preg_replace('/\s+/', '', (string) $in['phone']) : '';
$role      = isset($in['user_role']) ? trim((string) $in['user_role']) : 'Admin';
$active    = !empty($in['active']);
$username  = isset($in['username']) ? trim((string) $in['username']) : '';
$monthly_salary = isset($in['monthly_salary']) ? (float) $in['monthly_salary'] : 0.0;
$department_id = isset($in['department_id']) ? max(0, (int) $in['department_id']) : 0;
$designation_id = isset($in['designation_id']) ? max(0, (int) $in['designation_id']) : 0;
if ($monthly_salary < 0) {
    $monthly_salary = 0.0;
}

if ($department_id > 0) {
    $departmentOk = getRecord(
        'SELECT id FROM tbl_employee_departments WHERE id = ' . $department_id . ' AND status = 1 LIMIT 1'
    );
    if (!$departmentOk) {
        echo json_encode(['ok' => false, 'message' => 'Selected department is not available.']);
        exit;
    }
}
if ($designation_id > 0) {
    $designationOk = getRecord(
        'SELECT id, department_id FROM tbl_employee_designations WHERE id = ' . $designation_id . ' AND status = 1 LIMIT 1'
    );
    if (!$designationOk) {
        echo json_encode(['ok' => false, 'message' => 'Selected designation is not available.']);
        exit;
    }
    if ($department_id <= 0 || (int) ($designationOk['department_id'] ?? 0) !== $department_id) {
        echo json_encode(['ok' => false, 'message' => 'Selected designation does not belong to the selected department.']);
        exit;
    }
}

if ($role === '') {
    echo json_encode(['ok' => false, 'message' => 'Role is required.']);
    exit;
}
$rn_esc = esc($role);
$roleOk = getRecordMaster("SELECT id FROM tbl_roles WHERE role_name = '$rn_esc' AND is_active = 1 LIMIT 1");
if (!$roleOk) {
    echo json_encode(['ok' => false, 'message' => 'Selected role is not available. Add/activate it under Roles first.']);
    exit;
}

$branch_ids_in = [];
if (isset($in['branch_ids']) && is_array($in['branch_ids'])) {
    foreach ($in['branch_ids'] as $x) {
        $branch_ids_in[] = (int) $x;
    }
}
$ids_norm = auragold_um_normalize_branch_ids_list($branch_ids_in);
$listInts = auragold_um_parse_branch_ids_string($ids_norm);
$branches = '';
if (!empty($listInts)) {
    $inList = implode(',', array_map('intval', $listInts));
    $names  = [];
    $brConn = $conn_master;
    if (isset($conn) && $conn instanceof mysqli) {
        $tb = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_branches'");
        if ($tb && mysqli_num_rows($tb) > 0) {
            $brConn = $conn;
        }
        if ($tb) {
            mysqli_free_result($tb);
        }
    }
    $bnRes  = mysqli_query($brConn, 'SELECT id, name FROM tbl_branches WHERE id IN (' . $inList . ') ORDER BY id ASC');
    if ($bnRes) {
        $byId = [];
        while ($r = mysqli_fetch_assoc($bnRes)) {
            $byId[(int) ($r['id'] ?? 0)] = trim((string) ($r['name'] ?? ''));
        }
        foreach ($listInts as $bid) {
            if (!isset($byId[$bid]) || $byId[$bid] === '') {
                echo json_encode(['ok' => false, 'message' => 'Invalid branch selected (id ' . (int) $bid . ').']);
                exit;
            }
            $names[] = $byId[$bid];
        }
    } else {
        echo json_encode(['ok' => false, 'message' => 'Could not validate branches.']);
        exit;
    }
    if (count($names) !== count($listInts)) {
        echo json_encode(['ok' => false, 'message' => 'Invalid branch selection.']);
        exit;
    }
    $branches = implode(', ', $names);
}
$password  = isset($in['password']) ? (string) $in['password'] : '';
$confirm = isset($in['confirm_password']) ? (string) $in['confirm_password'] : '';

$missing = [];
if ($full_name === '') {
    $missing[] = 'full name';
}
if ($mail === '') {
    $missing[] = 'mail ID';
}
if ($phone_num === '') {
    $missing[] = 'phone number';
}
if ($username === '') {
    $missing[] = 'username';
}
if ($branches === '' || $ids_norm === '') {
    $missing[] = 'at least one branch';
}
if ($missing) {
    echo json_encode(['ok' => false, 'message' => 'Please fill: ' . implode(', ', $missing) . '.']);
    exit;
}

$scope = auragold_um_user_management_scope_sub_branch($conn_master);
if ($scope !== null) {
    $scopeBid = (int) $scope['id'];
    if (!in_array($scopeBid, $listInts, true)) {
        echo json_encode(['ok' => false, 'message' => 'Include your current branch in Branch assignment (required when managing users from a sub-branch).']);
        exit;
    }
}

if ($is_update) {
    if ($password !== '' || $confirm !== '') {
        if ($password !== $confirm) {
            echo json_encode(['ok' => false, 'message' => 'Password and confirm password do not match.']);
            exit;
        }
        if (strlen($password) > 50) {
            echo json_encode(['ok' => false, 'message' => 'Password must be at most 50 characters.']);
            exit;
        }
    }
} else {
    if ($password === '') {
        echo json_encode(['ok' => false, 'message' => 'Please enter a password for the new user.']);
        exit;
    }
    if ($password !== $confirm) {
        echo json_encode(['ok' => false, 'message' => 'Password and confirm password do not match.']);
        exit;
    }
    if (strlen($password) > 50) {
        echo json_encode(['ok' => false, 'message' => 'Password must be at most 50 characters.']);
        exit;
    }
}

$parts = preg_split('/\s+/', $full_name, 2, PREG_SPLIT_NO_EMPTY);
$fname = $parts[0] ?? $full_name;
$lname = isset($parts[1]) ? trim($parts[1]) : '';

$phone_full = trim($cc . ' ' . $phone_num);
$status     = $active ? '1' : '0';

$uid = (int) ($_SESSION['user_id'] ?? 0);
if ($uid < 0) {
    $uid = 0;
}

$u_esc = esc($username);
if ($is_update) {
    $dup = getRecord("SELECT id FROM tbl_users WHERE Username = '$u_esc' AND id != $id LIMIT 1");
} else {
    $dup = getRecord("SELECT id FROM tbl_users WHERE Username = '$u_esc' LIMIT 1");
}
if ($dup) {
    echo json_encode(['ok' => false, 'message' => 'Username already exists.']);
    exit;
}

$fn_esc = esc($fname);
$ln_esc = esc($lname);
$em_esc = esc($mail);
$ph_esc = esc($phone_full);
$st_esc = esc($status);
$rl_esc = esc($role);
$bl_esc = esc($branches);
$ub_esc = esc($ids_norm);
$sal_sql = number_format($monthly_salary, 2, '.', '');

if ($is_update) {
    $exists = getRecord("SELECT * FROM tbl_users WHERE id = $id LIMIT 1");
    if (!$exists) {
        echo json_encode(['ok' => false, 'message' => 'User not found.']);
        exit;
    }
    if (!auragold_um_user_row_in_management_scope($conn_master, $exists)) {
        echo json_encode(['ok' => false, 'message' => 'You cannot edit this user from your branch context.']);
        exit;
    }
    $mod = (int) ($_SESSION['user_id'] ?? 0);
    $set_parts = [
        "Fname='$fn_esc'",
        "Lname='$ln_esc'",
        "Username='$u_esc'",
        "Phone='$ph_esc'",
        "EmailId='$em_esc'",
        "Status='$st_esc'",
        "user_role='$rl_esc'",
        "branch_labels='$bl_esc'",
        "user_branch_ids='$ub_esc'",
        "monthly_salary=$sal_sql",
        "department_id=$department_id",
        "designation_id=$designation_id",
        "ModifiedBy=$mod",
    ];
    if ($password !== '') {
        $pw_esc = esc($password);
        $set_parts[] = "Password='$pw_esc'";
    }
    $sql = 'UPDATE tbl_users SET ' . implode(', ', $set_parts) . " WHERE id=$id LIMIT 1";
    if (!mysqli_query($conn, $sql)) {
        echo json_encode(['ok' => false, 'message' => 'Could not update user: ' . mysqli_error($conn)]);
        exit;
    }
    auragold_em_sync_user_to_employee_branches($conn, $id);
    echo json_encode(['ok' => true, 'message' => 'User updated.']);
    exit;
}

$pw_esc = esc($password);
$sql = "
    INSERT INTO tbl_users (Fname, Lname, Username, Phone, EmailId, Password, Status,
        CreatedBy, ModifiedBy, user_role, branch_labels, user_branch_ids, two_factor_enabled,
        monthly_salary, department_id, designation_id)
    VALUES ('$fn_esc', '$ln_esc', '$u_esc', '$ph_esc', '$em_esc', '$pw_esc', '$st_esc',
        $uid, $uid, '$rl_esc', '$bl_esc', '$ub_esc', 0, $sal_sql, $department_id, $designation_id)
";

if (!mysqli_query($conn, $sql)) {
    echo json_encode(['ok' => false, 'message' => 'Could not save user: ' . mysqli_error($conn)]);
    exit;
}

$newUserId = (int) mysqli_insert_id($conn);
if ($newUserId > 0) {
    auragold_em_sync_user_to_employee_branches($conn, $newUserId);
}

echo json_encode(['ok' => true, 'message' => 'User saved.']);
