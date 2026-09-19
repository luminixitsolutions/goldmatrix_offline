<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$department_id = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;

if ($department_id < 1) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid department', 'users' => []]);
    exit;
}

$map_tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_department_user_map'");
$cust_tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_customers'");
if (!$map_tbl || mysqli_num_rows($map_tbl) === 0 || !$cust_tbl || mysqli_num_rows($cust_tbl) === 0) {
    if ($map_tbl) {
        mysqli_free_result($map_tbl);
    }
    if ($cust_tbl) {
        mysqli_free_result($cust_tbl);
    }
    echo json_encode(['status' => 'success', 'users' => []]);
    exit;
}
mysqli_free_result($map_tbl);
mysqli_free_result($cust_tbl);

$sales_person_type_id = 0;
$st = @mysqli_query($conn, "SELECT id FROM tbl_customer_types WHERE (LOWER(TRIM(name)) = 'sales person' OR LOWER(REPLACE(TRIM(name), ' ', '')) = 'salesperson') AND status = 1 LIMIT 1");
if ($st && mysqli_num_rows($st) > 0) {
    $str = mysqli_fetch_assoc($st);
    $sales_person_type_id = (int)$str['id'];
    mysqli_free_result($st);
} elseif ($st) {
    mysqli_free_result($st);
}

if ($sales_person_type_id <= 0) {
    echo json_encode(['status' => 'success', 'users' => []]);
    exit;
}

$users = getList(
    "SELECT c.id, c.name AS user_name FROM tbl_customers c "
    . "INNER JOIN tbl_department_user_map m ON c.id = m.user_id AND m.status = 1 "
    . "WHERE m.department_id = $department_id AND c.status = 1 AND c.customer_type_id = $sales_person_type_id "
    . "ORDER BY c.name ASC"
);

if (!is_array($users)) {
    $users = [];
}

echo json_encode(['status' => 'success', 'users' => $users]);
