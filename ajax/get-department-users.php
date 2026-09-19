<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_department_schema.php';
auragold_ensure_department_schema($conn);

header('Content-Type: application/json');

$department_id = isset($_GET['department_id']) ? (int)$_GET['department_id'] : 0;
$all_ledgers = isset($_GET['all_ledgers']) && (string)$_GET['all_ledgers'] === '1';

$cust_tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_customers'");
if (!$cust_tbl || mysqli_num_rows($cust_tbl) === 0) {
    if ($cust_tbl) {
        mysqli_free_result($cust_tbl);
    }
    echo json_encode(['status' => 'success', 'users' => [], 'message' => 'Customers table missing']);
    exit;
}
mysqli_free_result($cust_tbl);

// Explicit request: all ledger accounts (when Department is not selected on Job Work Order)
if ($all_ledgers) {
    $users = getList(
        "SELECT c.id, c.name AS user_name FROM tbl_customers c WHERE c.status = 1 ORDER BY c.name ASC LIMIT 8000"
    );
    if (!is_array($users)) {
        $users = [];
    }
    echo json_encode(['status' => 'success', 'users' => $users, 'scope' => 'all_ledgers']);
    exit;
}

if ($department_id < 1) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid department', 'users' => []]);
    exit;
}

$map_tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_department_user_map'");
if (!$map_tbl || mysqli_num_rows($map_tbl) === 0) {
    if ($map_tbl) {
        mysqli_free_result($map_tbl);
    }
    echo json_encode(['status' => 'success', 'users' => [], 'message' => 'Department user map missing']);
    exit;
}
mysqli_free_result($map_tbl);

$job_worker_type_id = 0;
$jw_result = @mysqli_query($conn, "SELECT id FROM tbl_customer_types WHERE LOWER(name) = 'job worker' AND status = 1 LIMIT 1");
if ($jw_result && mysqli_num_rows($jw_result) > 0) {
    $jw_row = mysqli_fetch_assoc($jw_result);
    $job_worker_type_id = (int)$jw_row['id'];
    mysqli_free_result($jw_result);
} elseif ($jw_result) {
    mysqli_free_result($jw_result);
}

// Job Worker customers assigned to this department (user_id in map = tbl_customers.id), same as manufacturing-process.php
$type_sql = ($job_worker_type_id > 0) ? " AND c.customer_type_id = $job_worker_type_id" : '';
$users = getList(
    "SELECT c.id, c.name AS user_name FROM tbl_customers c "
    . "INNER JOIN tbl_department_user_map m ON c.id = m.user_id AND m.status = 1 "
    . "WHERE m.department_id = $department_id AND c.status = 1"
    . $type_sql
    . " ORDER BY c.name ASC"
);

if (!is_array($users)) {
    $users = [];
}

echo json_encode(['status' => 'success', 'users' => $users]);
