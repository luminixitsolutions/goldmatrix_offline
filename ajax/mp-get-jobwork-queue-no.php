<?php
/**
 * Return Jobwork Queue number for an order (Bill Series via ensureJobworkQueueNoForOrder).
 */
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$jobwork_order_id = isset($_GET['jobwork_order_id']) ? (int)$_GET['jobwork_order_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
if ($jobwork_order_id < 1) {
    echo json_encode(['ok' => false, 'message' => 'jobwork_order_id required']);
    exit;
}

$tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_orders'");
if (!$tbl || mysqli_num_rows($tbl) === 0) {
    if ($tbl) {
        mysqli_free_result($tbl);
    }
    echo json_encode(['ok' => false, 'message' => 'Job work orders table not found']);
    exit;
}
mysqli_free_result($tbl);

$queue_no = '';
if (function_exists('ensureJobworkQueueNoForOrder')) {
    $q = ensureJobworkQueueNoForOrder($conn, $jobwork_order_id);
    $queue_no = ($q !== null && $q !== '') ? trim((string)$q) : '';
}

if ($queue_no === '' && function_exists('getRecord')) {
    $row = getRecord('SELECT jobwork_queue_no FROM tbl_jobwork_orders WHERE id = ' . (int)$jobwork_order_id . ' LIMIT 1');
    if ($row && isset($row['jobwork_queue_no'])) {
        $queue_no = trim((string)$row['jobwork_queue_no']);
    }
}

echo json_encode([
    'ok' => true,
    'jobwork_queue_no' => $queue_no,
]);
