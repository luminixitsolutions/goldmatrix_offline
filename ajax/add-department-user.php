<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$department_id = isset($_POST['department_id']) ? (int)$_POST['department_id'] : 0;
$user_name = isset($_POST['user_name']) ? trim((string)$_POST['user_name']) : '';

if ($department_id < 1) {
    echo json_encode(['status' => 'error', 'message' => 'Please select a department']);
    exit;
}
if ($user_name === '') {
    echo json_encode(['status' => 'error', 'message' => 'Name is required']);
    exit;
}

$map_tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_department_user_map'");
$du_tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_department_users'");
if (!$map_tbl || mysqli_num_rows($map_tbl) === 0 || !$du_tbl || mysqli_num_rows($du_tbl) === 0) {
    if ($map_tbl) {
        mysqli_free_result($map_tbl);
    }
    if ($du_tbl) {
        mysqli_free_result($du_tbl);
    }
    echo json_encode(['status' => 'error', 'message' => 'Run admin/sql/create_tbl_department_users.sql']);
    exit;
}
mysqli_free_result($map_tbl);
mysqli_free_result($du_tbl);

$dept_ok = getRecord("SELECT id FROM tbl_departments WHERE id = $department_id AND status = 1 LIMIT 1");
if (!$dept_ok) {
    echo json_encode(['status' => 'error', 'message' => 'Department not found']);
    exit;
}

$user_esc = mysqli_real_escape_string($conn, $user_name);
$existing = getRecord("SELECT id FROM tbl_department_users WHERE user_name = '$user_esc' AND status = 1 LIMIT 1");
$user_id = 0;
if ($existing && !empty($existing['id'])) {
    $user_id = (int)$existing['id'];
} else {
    $ins = "INSERT INTO tbl_department_users (user_name, status, created_at, updated_at) VALUES ('$user_esc', 1, NOW(), NOW())";
    if (!mysqli_query($conn, $ins)) {
        echo json_encode(['status' => 'error', 'message' => 'Could not create user: ' . mysqli_error($conn)]);
        exit;
    }
    $user_id = (int)mysqli_insert_id($conn);
}

$map_row = getRecord("SELECT id, status FROM tbl_department_user_map WHERE department_id = $department_id AND user_id = $user_id LIMIT 1");
if ($map_row && !empty($map_row['id'])) {
    mysqli_query($conn, "UPDATE tbl_department_user_map SET status = 1, updated_at = NOW() WHERE id = " . (int)$map_row['id']);
} else {
    mysqli_query(
        $conn,
        "INSERT INTO tbl_department_user_map (department_id, user_id, status, created_at, updated_at) VALUES ($department_id, $user_id, 1, NOW(), NOW())"
    );
}

echo json_encode([
    'status' => 'success',
    'user' => ['id' => $user_id, 'user_name' => $user_name],
]);
