<?php
/**
 * Manufacturing Process — reverse a specific department transfer (LIFO).
 * Deletes the activity row, restores job to previous dept/user, rolls back weights/diamonds.
 */
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/mp-jobwork-queue-diamond-stock.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'message' => 'Invalid request']);
    exit;
}

$id = isset($_POST['jobwork_order_id']) ? (int) $_POST['jobwork_order_id'] : 0;
$activity_id = isset($_POST['activity_id']) ? (int) $_POST['activity_id'] : 0;

if ($id < 1 || $activity_id < 1) {
    echo json_encode(['ok' => false, 'message' => 'Invalid job work order or transfer.']);
    exit;
}

$blocked_msg = 'This transfer cannot be reversed because the stock has already moved to another department. Please reverse the latest transfer first.';

$jwo = function_exists('getRecord')
    ? getRecord('SELECT id, sale_order_id, department_id, department_user_id, jobwork_queue_no FROM tbl_jobwork_orders WHERE id = ' . $id . ' LIMIT 1')
    : null;
if (!$jwo) {
    echo json_encode(['ok' => false, 'message' => 'Job work order not found']);
    exit;
}

$act_tbl = 'tbl_jobwork_queue_activity';
$chk = @mysqli_query($conn, "SHOW TABLES LIKE '" . mysqli_real_escape_string($conn, $act_tbl) . "'");
if (!$chk || mysqli_num_rows($chk) === 0) {
    if ($chk) {
        mysqli_free_result($chk);
    }
    echo json_encode(['ok' => false, 'message' => 'Queue activity table not found.']);
    exit;
}
mysqli_free_result($chk);

$act = function_exists('getRecord')
    ? getRecord("SELECT * FROM `{$act_tbl}` WHERE id = {$activity_id} AND jobwork_order_id = {$id} LIMIT 1")
    : null;
if (!$act || empty($act['id'])) {
    echo json_encode(['ok' => false, 'message' => 'Transfer record not found.']);
    exit;
}

$act_action = strtolower(trim((string) ($act['activity_action'] ?? '')));
if ($act_action !== 'department_transfer') {
    echo json_encode(['ok' => false, 'message' => 'Only department transfers can be reversed from this screen.']);
    exit;
}

$latest = function_exists('getRecord')
    ? getRecord(
        "SELECT id FROM `{$act_tbl}` WHERE jobwork_order_id = {$id}"
        . " AND LOWER(TRIM(IFNULL(activity_action,''))) = 'department_transfer'"
        . ' ORDER BY id DESC LIMIT 1'
    )
    : null;
if (!$latest || (int) ($latest['id'] ?? 0) !== $activity_id) {
    echo json_encode(['ok' => false, 'message' => $blocked_msg]);
    exit;
}

$to_dept = (int) ($act['to_dept_id'] ?? 0);
$cur_dept = (int) ($jwo['department_id'] ?? 0);
if ($to_dept < 1 || $cur_dept !== $to_dept) {
    echo json_encode(['ok' => false, 'message' => $blocked_msg]);
    exit;
}

$prev_dept = (int) ($act['from_dept_id'] ?? 0);
$prev_user = (int) ($act['from_user_id'] ?? 0);
if ($prev_dept < 1) {
    echo json_encode(['ok' => false, 'message' => 'Cannot undo: previous department is not recorded for this transfer.']);
    exit;
}

$transfer_at = (string) ($act['created_at'] ?? '');

$prev_qn = '';
$prev_act = function_exists('getRecord')
    ? getRecord(
        "SELECT jobwork_queue_no, total_wt_after, total_qty_after, metal_wt_after, loss_wt_after"
        . " FROM `{$act_tbl}` WHERE jobwork_order_id = {$id} AND id < {$activity_id} ORDER BY id DESC LIMIT 1"
    )
    : null;
if ($prev_act && trim((string) ($prev_act['jobwork_queue_no'] ?? '')) !== '') {
    $prev_qn = trim((string) $prev_act['jobwork_queue_no']);
} else {
    $prev_qn = trim((string) ($jwo['jobwork_queue_no'] ?? ''));
}

$prev_dept_name = '';
$prev_user_name = '';
$removed_dept_name = '';
$pd = function_exists('getRecord') ? getRecord('SELECT dept_name FROM tbl_departments WHERE id = ' . $prev_dept . ' LIMIT 1') : null;
if ($pd && isset($pd['dept_name'])) {
    $prev_dept_name = trim((string) $pd['dept_name']);
}
if ($prev_user > 0) {
    $pu = function_exists('getRecord') ? getRecord('SELECT name FROM tbl_customers WHERE id = ' . $prev_user . ' LIMIT 1') : null;
    if ($pu && isset($pu['name'])) {
        $prev_user_name = trim((string) $pu['name']);
    }
}
if ($to_dept > 0) {
    $rd = function_exists('getRecord') ? getRecord('SELECT dept_name FROM tbl_departments WHERE id = ' . $to_dept . ' LIMIT 1') : null;
    if ($rd && isset($rd['dept_name'])) {
        $removed_dept_name = trim((string) $rd['dept_name']);
    }
}

/**
 * Restore line weights from the activity snapshot before this transfer.
 */
function mp_mfg_restore_weights_from_prev_activity($conn, $jobwork_order_id, $prev_act)
{
    if (!$prev_act || !is_array($prev_act)) {
        return true;
    }
    $tw = isset($prev_act['total_wt_after']) ? (float) $prev_act['total_wt_after'] : null;
    $mw = isset($prev_act['metal_wt_after']) ? (float) $prev_act['metal_wt_after'] : null;
    $lw = isset($prev_act['loss_wt_after']) ? (float) $prev_act['loss_wt_after'] : null;
    if ($tw === null && $mw === null && $lw === null) {
        return true;
    }

    $ji_cols = [];
    $jic = @mysqli_query($conn, 'SHOW COLUMNS FROM tbl_jobwork_order_items');
    if ($jic) {
        while ($col = mysqli_fetch_assoc($jic)) {
            $ji_cols[$col['Field']] = true;
        }
        mysqli_free_result($jic);
    }

    $items = function_exists('getList')
        ? getList('SELECT id FROM tbl_jobwork_order_items WHERE jobwork_order_id = ' . (int) $jobwork_order_id . ' ORDER BY id ASC LIMIT 1')
        : [];
    if (!is_array($items) || $items === []) {
        return true;
    }
    $item_id = (int) ($items[0]['id'] ?? 0);
    if ($item_id < 1) {
        return true;
    }

    $sets = [];
    if ($tw !== null && is_finite($tw) && !empty($ji_cols['final_weight'])) {
        $sets[] = 'final_weight = ' . round($tw, 4);
    }
    if ($mw !== null && is_finite($mw) && !empty($ji_cols['net_weight'])) {
        $sets[] = 'net_weight = ' . round($mw, 4);
    }
    if ($lw !== null && is_finite($lw)) {
        if (!empty($ji_cols['gold_loss_1'])) {
            $sets[] = 'gold_loss_1 = ' . round($lw, 4);
        } elseif (!empty($ji_cols['loss_wt'])) {
            $sets[] = 'loss_wt = ' . round($lw, 4);
        }
    }
    if ($sets === []) {
        return true;
    }

    return (bool) @mysqli_query(
        $conn,
        'UPDATE tbl_jobwork_order_items SET ' . implode(', ', $sets)
        . ' WHERE id = ' . $item_id . ' AND jobwork_order_id = ' . (int) $jobwork_order_id . ' LIMIT 1'
    );
}

mysqli_begin_transaction($conn);
$tx_ok = true;
$tx_err = '';

if ($transfer_at !== '' && function_exists('mp_jwq_remove_diamond_issues_for_jobwork')) {
    mp_jwq_ensure_diamond_issue_table($conn);
    $issue_tbl = mp_jwq_diamond_issue_table_name();
    $ts_esc = mysqli_real_escape_string($conn, $transfer_at);
    $issue_rows = function_exists('getList')
        ? getList(
            "SELECT id AS issue_id, stock_id, barcode FROM `{$issue_tbl}`"
            . " WHERE jobwork_order_id = {$id} AND created_at >= '{$ts_esc}'"
        )
        : [];
    if (is_array($issue_rows) && $issue_rows !== []) {
        mp_jwq_remove_diamond_issues_for_jobwork($conn, $id, $issue_rows, $tx_ok, $tx_err);
    }
}

$wchk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_weight_adjustments'");
$has_weight = ($wchk && mysqli_num_rows($wchk) > 0);
if ($wchk) {
    mysqli_free_result($wchk);
}
if ($tx_ok && $has_weight && $transfer_at !== '') {
    $ts_esc = mysqli_real_escape_string($conn, $transfer_at);
    $auto_loss_rows = function_exists('getList')
        ? getList(
            "SELECT id FROM tbl_jobwork_weight_adjustments WHERE jobwork_order_id = {$id}"
            . " AND adjustment_type = 'reduce'"
            . " AND (remark LIKE '%auto loss%' OR remark LIKE '%Auto loss%')"
            . " AND created_at >= DATE_SUB('{$ts_esc}', INTERVAL 60 SECOND)"
            . " AND created_at <= DATE_ADD('{$ts_esc}', INTERVAL 60 SECOND)"
        )
        : [];
    if (is_array($auto_loss_rows)) {
        foreach ($auto_loss_rows as $wr) {
            $wid = (int) ($wr['id'] ?? 0);
            if ($wid < 1) {
                continue;
            }
            if (!@mysqli_query($conn, 'DELETE FROM tbl_jobwork_weight_adjustments WHERE id = ' . $wid . ' AND jobwork_order_id = ' . $id . ' LIMIT 1')) {
                $tx_ok = false;
                $tx_err = 'Could not remove auto loss weight entry. DB: ' . mysqli_error($conn);
                break;
            }
        }
    }
}

if ($tx_ok && !mp_mfg_restore_weights_from_prev_activity($conn, $id, $prev_act)) {
    $tx_ok = false;
    $tx_err = 'Could not restore job work weights after reversing transfer. DB: ' . mysqli_error($conn);
}

if ($tx_ok) {
    if (!@mysqli_query($conn, 'DELETE FROM `' . $act_tbl . '` WHERE id = ' . $activity_id . ' AND jobwork_order_id = ' . $id . ' LIMIT 1')) {
        $tx_ok = false;
        $tx_err = 'Could not remove department transfer record. DB: ' . mysqli_error($conn);
    }
}

if ($tx_ok) {
    $qn_esc = mysqli_real_escape_string($conn, $prev_qn);
    $parts = [
        'department_id = ' . $prev_dept,
        "jobwork_queue_no = '" . $qn_esc . "'",
    ];
    $cu = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_orders LIKE 'department_user_id'");
    $has_user = ($cu && mysqli_num_rows($cu) > 0);
    if ($cu) {
        mysqli_free_result($cu);
    }
    if ($has_user) {
        $parts[] = $prev_user > 0 ? ('department_user_id = ' . $prev_user) : 'department_user_id = NULL';
    }
    $upd = 'UPDATE tbl_jobwork_orders SET ' . implode(', ', $parts) . ' WHERE id = ' . $id . ' LIMIT 1';
    if (!@mysqli_query($conn, $upd)) {
        $tx_ok = false;
        $tx_err = 'Could not move job to previous department. DB: ' . mysqli_error($conn);
    }
}

if ($tx_ok) {
    $soid = (int) ($jwo['sale_order_id'] ?? 0);
    if ($soid > 0) {
        $cd_so = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_orders LIKE 'department_id'");
        if ($cd_so && mysqli_num_rows($cd_so) > 0) {
            mysqli_free_result($cd_so);
            @mysqli_query($conn, 'UPDATE tbl_sale_orders SET department_id = ' . $prev_dept . ' WHERE id = ' . $soid);
        } elseif ($cd_so) {
            mysqli_free_result($cd_so);
        }
    }
}

if ($tx_ok) {
    mysqli_commit($conn);
    echo json_encode([
        'ok' => true,
        'message' => 'Transfer reversed. Job returned to ' . ($prev_dept_name !== '' ? $prev_dept_name : 'previous department') . '.',
        'jobwork_order_id' => $id,
        'activity_id' => $activity_id,
        'previous_department_id' => $prev_dept,
        'previous_department_name' => $prev_dept_name,
        'previous_user_id' => $prev_user,
        'previous_user_name' => $prev_user_name,
        'removed_department_id' => $to_dept,
        'removed_department_name' => $removed_dept_name,
        'jobwork_queue_no' => $prev_qn,
    ], JSON_UNESCAPED_UNICODE);
} else {
    mysqli_rollback($conn);
    echo json_encode(['ok' => false, 'message' => $tx_err !== '' ? $tx_err : 'Could not reverse department transfer.']);
}
