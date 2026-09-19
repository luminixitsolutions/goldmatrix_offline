<?php
session_start();
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/includes/session_login_type.php';
require_once dirname(__DIR__) . '/includes/branch_working_context.php';
require_once dirname(__DIR__) . '/includes/branch_product_delete_permission.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['Admin'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

if (!auragold_session_is_admin_login_type()) {
    echo json_encode(['status' => 'error', 'message' => 'Only admin users can change this setting.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$allow = isset($_POST['allow_product_delete']) ? (int) $_POST['allow_product_delete'] : -1;
if ($id <= 0 || ($allow !== 0 && $allow !== 1)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
    exit;
}

if (!$conn_master) {
    echo json_encode(['status' => 'error', 'message' => 'Registry connection unavailable']);
    exit;
}

auragold_ensure_branches_allow_product_delete_column($conn_master);

$target = getRecordMaster('SELECT id, main_branch_id FROM tbl_branches WHERE id = ' . $id . ' LIMIT 1');
if (!$target) {
    echo json_encode(['status' => 'error', 'message' => 'Branch not found']);
    exit;
}
if ((int) $target['main_branch_id'] === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Main branch does not use this flag.']);
    exit;
}

$scope_main = auragold_session_restrict_sub_branch_ops_main_id();
if ($scope_main > 0 && (int) $target['main_branch_id'] !== $scope_main) {
    echo json_encode(['status' => 'error', 'message' => 'You can only change sub-branches under your main branch.']);
    exit;
}

$ok = mysqli_query(
    $conn_master,
    'UPDATE tbl_branches SET allow_product_delete = ' . $allow . ' WHERE id = ' . $id . ' LIMIT 1'
);
if (!$ok) {
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
    exit;
}

echo json_encode(['status' => 'ok']);
