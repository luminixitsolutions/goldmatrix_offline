<?php
/**
 * Insert one line on tbl_jobwork_order_items for Jobwork Queue "Add row".
 * If jobwork_order_id is missing or 0, creates a draft Job Work Order (sale_order_id = 0) first.
 */
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'message' => 'Invalid request']);
    exit;
}

$jobwork_order_id = isset($_POST['jobwork_order_id']) ? (int)$_POST['jobwork_order_id'] : 0;
$created_jobwork_order = false;
$draft = null;

$chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_order_items'");
if (!$chk || mysqli_num_rows($chk) === 0) {
    if ($chk) {
        mysqli_free_result($chk);
    }
    echo json_encode(['ok' => false, 'message' => 'Job work order items table not found']);
    exit;
}
mysqli_free_result($chk);

$chk_m = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_orders'");
if (!$chk_m || mysqli_num_rows($chk_m) === 0) {
    if ($chk_m) {
        mysqli_free_result($chk_m);
    }
    echo json_encode(['ok' => false, 'message' => 'Job work orders table not found']);
    exit;
}
mysqli_free_result($chk_m);

/**
 * @return array{0:?array,1:?string} [ row with id, jobwork_no, jobwork_queue_no, error ]
 */
function jwqCreateDraftJobworkOrderForQueue($conn) {
    if (!function_exists('getNextJobworkOrderNo')) {
        return [null, 'Job work order numbering is not configured'];
    }
    $cfg_jwo = function_exists('getJobworkOrderBillSeriesConfig')
        ? getJobworkOrderBillSeriesConfig($conn)
        : ['prefix' => 'JWO-', 'suffix' => '', 'start_count' => 1, 'from_series_table' => false];
    $jobwork_no = getNextJobworkOrderNo($conn);
    $jobwork_no_esc = mysqli_real_escape_string($conn, $jobwork_no);
    $jwo_active = function_exists('auragold_doc_series_active_sql') ? auragold_doc_series_active_sql($conn, 'tbl_jobwork_orders') : '';
    $existing_no = function_exists('getRecord') ? getRecord("SELECT id FROM tbl_jobwork_orders WHERE jobwork_no = '$jobwork_no_esc'" . $jwo_active) : null;
    $guard_no = 0;
    while ($existing_no && !empty($existing_no['id']) && $guard_no < 5000) {
        $jobwork_no = function_exists('bumpJobworkOrderNo') ? bumpJobworkOrderNo($conn, $jobwork_no, $cfg_jwo) : ($jobwork_no . '-1');
        $jobwork_no_esc = mysqli_real_escape_string($conn, $jobwork_no);
        $existing_no = getRecord("SELECT id FROM tbl_jobwork_orders WHERE jobwork_no = '$jobwork_no_esc'" . $jwo_active);
        $guard_no++;
    }
    $status_esc = mysqli_real_escape_string($conn, 'Processing');
    // sale_order_id = 0: queue draft until linked to a sale order from Job Work Order screen
    $sql = "INSERT INTO tbl_jobwork_orders (jobwork_no, sale_order_id, sale_order_no, customer_name, order_date, due_date, grand_total, status, created_at) VALUES ('$jobwork_no_esc', 0, '', NULL, NULL, NULL, 0, '$status_esc', NOW())";
    if (!@mysqli_query($conn, $sql)) {
        return [null, 'Could not create job work order: ' . mysqli_error($conn)];
    }
    $new_id = (int)mysqli_insert_id($conn);
    $queue_no = '';
    if (function_exists('ensureJobworkQueueNoForOrder')) {
        $qn = ensureJobworkQueueNoForOrder($conn, $new_id);
        if (is_string($qn)) {
            $queue_no = trim($qn);
        }
    }
    return [['id' => $new_id, 'jobwork_no' => $jobwork_no, 'jobwork_queue_no' => $queue_no], null];
}

if ($jobwork_order_id < 1) {
    [$draft, $err] = jwqCreateDraftJobworkOrderForQueue($conn);
    if ($err || !$draft || empty($draft['id'])) {
        echo json_encode(['ok' => false, 'message' => $err ?: 'Could not start a new job work order']);
        exit;
    }
    $jobwork_order_id = (int)$draft['id'];
    $created_jobwork_order = true;
}

$jwo = function_exists('getRecord') ? getRecord('SELECT id, jobwork_no FROM tbl_jobwork_orders WHERE id = ' . $jobwork_order_id . ' LIMIT 1') : null;
if (!$jwo || empty($jwo['id'])) {
    echo json_encode(['ok' => false, 'message' => 'Job work order not found']);
    exit;
}

$sql = 'INSERT INTO tbl_jobwork_order_items (jobwork_order_id, product_id, product_characteristic_id, barcode, product_name, design_no, carat, quantity, gross_weight, less_weight, purity, purity_weight, final_weight, net_weight, pure_weight, rate, making_amount, amount, tax_amount, net_amount, net_amt_with_tax, status, created_at) VALUES ('
    . (int)$jobwork_order_id . ', NULL, NULL, NULL, \'\', NULL, NULL, 1, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 1, NOW())';

if (!@mysqli_query($conn, $sql)) {
    echo json_encode(['ok' => false, 'message' => 'Could not add line: ' . mysqli_error($conn)]);
    exit;
}

$new_id = (int)mysqli_insert_id($conn);
$item = function_exists('getRecord') ? getRecord('SELECT * FROM tbl_jobwork_order_items WHERE id = ' . $new_id . ' LIMIT 1') : null;

if (!$item || empty($item['id'])) {
    echo json_encode(['ok' => false, 'message' => 'Line created but could not reload it']);
    exit;
}

$queue_out = '';
if ($created_jobwork_order && is_array($draft) && isset($draft['jobwork_queue_no'])) {
    $queue_out = trim((string)$draft['jobwork_queue_no']);
}
if ($queue_out === '' && function_exists('ensureJobworkQueueNoForOrder')) {
    $qn = ensureJobworkQueueNoForOrder($conn, $jobwork_order_id);
    if (is_string($qn) && $qn !== '') {
        $queue_out = trim($qn);
    }
}
if ($queue_out === '' && function_exists('getRecord')) {
    $jr = getRecord('SELECT jobwork_queue_no FROM tbl_jobwork_orders WHERE id = ' . (int)$jobwork_order_id . ' LIMIT 1');
    if ($jr && isset($jr['jobwork_queue_no'])) {
        $queue_out = trim((string)$jr['jobwork_queue_no']);
    }
}

echo json_encode([
    'ok' => true,
    'item' => $item,
    'message' => 'Row added',
    'created_jobwork_order' => $created_jobwork_order,
    'jobwork_order_id' => $jobwork_order_id,
    'jobwork_no' => trim((string)($jwo['jobwork_no'] ?? '')),
    'jobwork_queue_no' => $queue_out,
]);
