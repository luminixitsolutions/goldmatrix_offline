<?php
/**
 * Manufacturing Process — remove job from current department: delete latest transfer
 * activity for this department and move job back to the previous department.
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
if ($id < 1) {
    echo json_encode(['ok' => false, 'message' => 'Invalid job work order']);
    exit;
}

$jwo = function_exists('getRecord')
    ? getRecord('SELECT id, sale_order_id, department_id, department_user_id, jobwork_queue_no FROM tbl_jobwork_orders WHERE id = ' . $id . ' LIMIT 1')
    : null;
if (!$jwo) {
    echo json_encode(['ok' => false, 'message' => 'Job work order not found']);
    exit;
}

$cur_dept = (int) ($jwo['department_id'] ?? 0);
if ($cur_dept < 1) {
    echo json_encode(['ok' => false, 'message' => 'Job is not assigned to a department.']);
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

$last = function_exists('getRecord')
    ? getRecord(
        "SELECT * FROM `{$act_tbl}` WHERE jobwork_order_id = {$id}"
        . ' AND to_dept_id = ' . $cur_dept
        . " AND LOWER(TRIM(IFNULL(activity_action,''))) = 'department_transfer'"
        . ' ORDER BY id DESC LIMIT 1'
    )
    : null;

if (!$last || empty($last['id'])) {
    $last = function_exists('getRecord')
        ? getRecord(
            "SELECT * FROM `{$act_tbl}` WHERE jobwork_order_id = {$id}"
            . " AND LOWER(TRIM(IFNULL(activity_action,''))) = 'department_transfer'"
            . ' ORDER BY id DESC LIMIT 1'
        )
        : null;
}

if (!$last || empty($last['id'])) {
    /* Job is still in its first department (no transfer to undo): remove it from the manufacturing
       floor instead — return issued diamonds to stock, drop the queue activity, clear the department. */
    mysqli_begin_transaction($conn);
    $uq_ok = true;
    $uq_err = '';

    if (function_exists('mp_jwq_remove_diamond_issues_for_jobwork')) {
        mp_jwq_ensure_diamond_issue_table($conn);
        $issue_tbl = mp_jwq_diamond_issue_table_name();
        $issue_rows = function_exists('getList')
            ? getList("SELECT id AS issue_id, stock_id, barcode FROM `{$issue_tbl}` WHERE jobwork_order_id = {$id}")
            : [];
        if (is_array($issue_rows) && $issue_rows !== []) {
            mp_jwq_remove_diamond_issues_for_jobwork($conn, $id, $issue_rows, $uq_ok, $uq_err);
        }
    }

    if ($uq_ok) {
        if (!@mysqli_query($conn, 'DELETE FROM `' . $act_tbl . '` WHERE jobwork_order_id = ' . $id)) {
            $uq_ok = false;
            $uq_err = 'Could not remove queue activity records. DB: ' . mysqli_error($conn);
        }
    }

    if ($uq_ok) {
        $parts = ['department_id = NULL'];
        $cu = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_orders LIKE 'department_user_id'");
        if ($cu && mysqli_num_rows($cu) > 0) {
            $parts[] = 'department_user_id = NULL';
        }
        if ($cu) {
            mysqli_free_result($cu);
        }
        if (!@mysqli_query($conn, 'UPDATE tbl_jobwork_orders SET ' . implode(', ', $parts) . ' WHERE id = ' . $id . ' LIMIT 1')) {
            $uq_ok = false;
            $uq_err = 'Could not remove job from the manufacturing floor. DB: ' . mysqli_error($conn);
        }
    }

    if ($uq_ok) {
        $soid = (int) ($jwo['sale_order_id'] ?? 0);
        if ($soid > 0) {
            $cd_so = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_orders LIKE 'department_id'");
            if ($cd_so && mysqli_num_rows($cd_so) > 0) {
                mysqli_free_result($cd_so);
                @mysqli_query($conn, 'UPDATE tbl_sale_orders SET department_id = NULL WHERE id = ' . $soid);
            } elseif ($cd_so) {
                mysqli_free_result($cd_so);
            }
        }
    }

    if ($uq_ok) {
        mysqli_commit($conn);
        echo json_encode([
            'ok' => true,
            'removed_from_queue' => true,
            'message' => 'Job removed from the manufacturing floor. Issued diamonds were returned to stock.',
            'jobwork_order_id' => $id,
        ], JSON_UNESCAPED_UNICODE);
    } else {
        mysqli_rollback($conn);
        echo json_encode(['ok' => false, 'message' => $uq_err !== '' ? $uq_err : 'Could not remove job from the manufacturing floor.']);
    }
    exit;
}

$prev_dept = (int) ($last['from_dept_id'] ?? 0);
$prev_user = (int) ($last['from_user_id'] ?? 0);
if ($prev_dept < 1) {
    echo json_encode(['ok' => false, 'message' => 'Cannot undo: previous department is not recorded for this transfer.']);
    exit;
}

$last_id = (int) $last['id'];
$removed_to_dept = (int) ($last['to_dept_id'] ?? 0);
$transfer_at = (string) ($last['created_at'] ?? '');

$prev_qn = '';
$prev_act = function_exists('getRecord')
    ? getRecord(
        "SELECT jobwork_queue_no FROM `{$act_tbl}` WHERE jobwork_order_id = {$id} AND id < {$last_id} ORDER BY id DESC LIMIT 1"
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
if ($removed_to_dept > 0) {
    $rd = function_exists('getRecord') ? getRecord('SELECT dept_name FROM tbl_departments WHERE id = ' . $removed_to_dept . ' LIMIT 1') : null;
    if ($rd && isset($rd['dept_name'])) {
        $removed_dept_name = trim((string) $rd['dept_name']);
    }
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

if ($tx_ok) {
    if (!@mysqli_query($conn, 'DELETE FROM `' . $act_tbl . '` WHERE id = ' . $last_id . ' AND jobwork_order_id = ' . $id . ' LIMIT 1')) {
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
        'message' => 'Job moved back to ' . ($prev_dept_name !== '' ? $prev_dept_name : 'previous department') . '.',
        'jobwork_order_id' => $id,
        'previous_department_id' => $prev_dept,
        'previous_department_name' => $prev_dept_name,
        'previous_user_id' => $prev_user,
        'previous_user_name' => $prev_user_name,
        'removed_department_id' => $removed_to_dept,
        'removed_department_name' => $removed_dept_name,
        'jobwork_queue_no' => $prev_qn,
    ], JSON_UNESCAPED_UNICODE);
} else {
    mysqli_rollback($conn);
    echo json_encode(['ok' => false, 'message' => $tx_err !== '' ? $tx_err : 'Could not revert department transfer.']);
}
