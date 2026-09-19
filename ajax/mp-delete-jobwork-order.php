<?php
/**
 * Manufacturing Process — delete a job work order (items + related rows, then master).
 */
session_start();
require_once __DIR__ . '/../config.php';
if (is_file(__DIR__ . '/../includes/auragold_sale_order_jobwork_lock.php')) {
    require_once __DIR__ . '/../includes/auragold_sale_order_jobwork_lock.php';
}

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

if (!function_exists('auragold_delete_jobwork_order_by_id')) {
    echo json_encode(['ok' => false, 'message' => 'Delete helper not available']);
    exit;
}

$result = auragold_delete_jobwork_order_by_id($conn, $id);
if (!empty($result['ok'])) {
    echo json_encode(['ok' => true, 'message' => $result['message'] ?? 'Deleted.', 'id' => $id]);
} else {
    echo json_encode(['ok' => false, 'message' => $result['message'] ?? 'Could not delete job work order.']);
}
