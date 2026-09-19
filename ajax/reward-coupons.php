<?php
/**
 * AJAX: Reward coupons — list / save / delete (filtered list).
 */
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_require_login.php';
auragold_require_login_or_exit();
require_once __DIR__ . '/../includes/auragold_branch_data_scope.php';
require_once __DIR__ . '/../includes/auragold_reward_coupons.php';

header('Content-Type: application/json; charset=utf-8');

$branchId = auragold_settings_branch_id();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid method']);
    exit;
}

$postBranch = isset($_POST['settings_branch_id']) ? (int) $_POST['settings_branch_id'] : 0;
if ($branchId <= 0 && $postBranch > 0 && function_exists('auragold_settings_branch_id_valid') && auragold_settings_branch_id_valid($postBranch)) {
    $branchId = $postBranch;
}

auragold_ensure_reward_coupons_table($conn);

if ($branchId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid branch context.']);
    exit;
}

$action = isset($_POST['action']) ? trim((string) $_POST['action']) : '';

if ($action === 'list') {
    $filters = [
        'f_date_from'   => isset($_POST['f_date_from']) ? trim((string) $_POST['f_date_from']) : '',
        'f_date_to'     => isset($_POST['f_date_to']) ? trim((string) $_POST['f_date_to']) : '',
        'f_code'        => isset($_POST['f_code']) ? trim((string) $_POST['f_code']) : '',
        'f_active_only' => !empty($_POST['f_active_only']) ? 1 : 0,
    ];
    $page = max(1, (int) ($_POST['page'] ?? 1));
    $pageSize = max(1, min(100, (int) ($_POST['page_size'] ?? 25)));

    $total = auragold_reward_coupons_count_filtered($conn, $branchId, $filters);
    $rows = auragold_reward_coupons_fetch_page($conn, $branchId, $filters, $page, $pageSize);

    foreach ($rows as &$r) {
        $r['id'] = (int) ($r['id'] ?? 0);
        $r['coupon_value'] = isset($r['coupon_value']) ? (string) $r['coupon_value'] : '0';
        $r['is_active'] = !empty($r['is_active']) ? 1 : 0;
        $r['expiry_date'] = isset($r['expiry_date']) ? (string) $r['expiry_date'] : '';
    }
    unset($r);

    echo json_encode([
        'status'          => 'ok',
        'rows'            => $rows,
        'total'           => $total,
        'page'            => $page,
        'page_size'       => $pageSize,
        'total_pages'     => $pageSize > 0 ? max(1, (int) ceil($total / $pageSize)) : 1,
        'filters_applied' => auragold_reward_coupons_filters_applied_count($filters),
    ]);
    exit;
}

if ($action === 'get') {
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $row = auragold_reward_coupons_get_branch_row($conn, $branchId, $id);
    if (!$row) {
        echo json_encode(['status' => 'error', 'message' => 'Coupon not found.']);
        exit;
    }
    echo json_encode([
        'status' => 'ok',
        'row'    => [
            'id'            => (int) $row['id'],
            'coupon_name'   => (string) ($row['coupon_name'] ?? ''),
            'coupon_code'   => (string) ($row['coupon_code'] ?? ''),
            'coupon_value'  => (string) ($row['coupon_value'] ?? '0'),
            'expiry_date'   => ($row['expiry_date'] !== '' && $row['expiry_date'] !== null) ? (string) $row['expiry_date'] : '',
            'is_active'     => !empty($row['is_active']) ? 1 : 0,
        ],
    ]);
    exit;
}

if ($action === 'save') {
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    $name = isset($_POST['coupon_name']) ? trim((string) $_POST['coupon_name']) : '';
    $code = isset($_POST['coupon_code']) ? trim((string) $_POST['coupon_code']) : '';
    $valRaw = isset($_POST['coupon_value']) ? trim((string) $_POST['coupon_value']) : '';
    $expRaw = isset($_POST['expiry_date']) ? trim((string) $_POST['expiry_date']) : '';
    $active = !empty($_POST['is_active']) ? 1 : 0;

    if ($name === '') {
        echo json_encode(['status' => 'error', 'message' => 'Coupons Name is required.']);
        exit;
    }
    if ($code === '') {
        echo json_encode(['status' => 'error', 'message' => 'Coupon Code is required.']);
        exit;
    }
    if (!preg_match('/^[A-Za-z0-9_-]{1,80}$/', $code)) {
        echo json_encode(['status' => 'error', 'message' => 'Coupon Code may use letters, digits, hyphen and underscore only.']);
        exit;
    }
    if ($valRaw === '' || !is_numeric($valRaw)) {
        echo json_encode(['status' => 'error', 'message' => 'Value must be a number.']);
        exit;
    }
    $val = mysqli_real_escape_string($conn, (string) round((float) $valRaw, 4));

    $expirySql = 'NULL';
    if ($expRaw !== '') {
        $try = DateTime::createFromFormat('Y-m-d', $expRaw);
        if (!$try) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid expiry date.']);
            exit;
        }
        $expirySql = "'" . mysqli_real_escape_string($conn, $try->format('Y-m-d')) . "'";
    }

    $ename = mysqli_real_escape_string($conn, $name);
    $ecode = mysqli_real_escape_string($conn, $code);

    $dup = @getRecord('SELECT id FROM tbl_auragold_reward_coupons WHERE branch_id = ' . (int) $branchId . " AND coupon_code = '{$ecode}' AND id <> " . (int) $id . ' LIMIT 1');
    if ($dup) {
        echo json_encode(['status' => 'error', 'message' => 'This Coupon Code already exists for this branch.']);
        exit;
    }

    if ($id > 0) {
        $exist = auragold_reward_coupons_get_branch_row($conn, $branchId, $id);
        if (!$exist) {
            echo json_encode(['status' => 'error', 'message' => 'Cannot update: coupon not found.']);
            exit;
        }
        $ok = @mysqli_query(
            $conn,
            "UPDATE tbl_auragold_reward_coupons SET coupon_name = '{$ename}', coupon_code = '{$ecode}', coupon_value = {$val}, expiry_date = {$expirySql}, is_active = {$active} WHERE branch_id = " . (int) $branchId . ' AND id = ' . (int) $id . ' LIMIT 1'
        );
    } else {
        $ok = @mysqli_query(
            $conn,
            'INSERT INTO tbl_auragold_reward_coupons (branch_id, coupon_name, coupon_code, coupon_value, expiry_date, is_active) VALUES (' . (int) $branchId . ", '{$ename}', '{$ecode}', {$val}, {$expirySql}, {$active})"
        );
        if ($ok) {
            $id = (int) mysqli_insert_id($conn);
        }
    }

    if (!$ok) {
        echo json_encode(['status' => 'error', 'message' => 'Save failed.']);
        exit;
    }

    echo json_encode(['status' => 'ok', 'message' => 'Coupon saved.', 'id' => $id]);
    exit;
}

if ($action === 'delete') {
    $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid id.']);
        exit;
    }
    $ok = @mysqli_query($conn, 'DELETE FROM tbl_auragold_reward_coupons WHERE branch_id = ' . (int) $branchId . ' AND id = ' . (int) $id . ' LIMIT 1');
    echo json_encode(['status' => $ok ? 'ok' : 'error', 'message' => $ok ? 'Coupon deleted.' : 'Delete failed.']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action']);

/**
 * @param array{f_date_from:string,f_date_to:string,f_code:string,f_active_only:int} $f
 */
function auragold_reward_coupons_filters_applied_count(array $f): int
{
    $n = 0;
    if (trim((string) ($f['f_date_from'] ?? '')) !== '') {
        ++$n;
    }
    if (trim((string) ($f['f_date_to'] ?? '')) !== '') {
        ++$n;
    }
    if (trim((string) ($f['f_code'] ?? '')) !== '') {
        ++$n;
    }
    if (!empty($f['f_active_only'])) {
        ++$n;
    }

    return $n;
}
