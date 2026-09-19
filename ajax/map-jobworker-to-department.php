<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$department_id = isset($_POST['department_id']) ? (int)$_POST['department_id'] : 0;
$customer_id = isset($_POST['customer_id']) ? (int)$_POST['customer_id'] : 0;

if ($department_id < 1 || $customer_id < 1) {
    echo json_encode(['status' => 'error', 'message' => 'Department and customer required']);
    exit;
}

$map_tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_department_user_map'");
if (!$map_tbl || mysqli_num_rows($map_tbl) === 0) {
    if ($map_tbl) {
        mysqli_free_result($map_tbl);
    }
    echo json_encode(['status' => 'error', 'message' => 'tbl_department_user_map not found']);
    exit;
}
mysqli_free_result($map_tbl);

$cust = getRecord("SELECT id FROM tbl_customers WHERE id = $customer_id AND status = 1 LIMIT 1");
if (!$cust) {
    echo json_encode(['status' => 'error', 'message' => 'Customer not found']);
    exit;
}

$dept = getRecord("SELECT id FROM tbl_departments WHERE id = $department_id AND status = 1 LIMIT 1");
if (!$dept) {
    echo json_encode(['status' => 'error', 'message' => 'Department not found']);
    exit;
}

$existing_map = getRecord("SELECT id, status FROM tbl_department_user_map WHERE department_id = $department_id AND user_id = $customer_id LIMIT 1");
if ($existing_map && !empty($existing_map['id'])) {
    mysqli_query($conn, "UPDATE tbl_department_user_map SET status = 1, updated_at = NOW() WHERE id = " . (int)$existing_map['id']);
} else {
    mysqli_query(
        $conn,
        "INSERT INTO tbl_department_user_map (department_id, user_id, status, created_at, updated_at) VALUES ($department_id, $customer_id, 1, NOW(), NOW())"
    );
}

echo json_encode(['status' => 'success', 'department_id' => $department_id, 'customer_id' => $customer_id]);
