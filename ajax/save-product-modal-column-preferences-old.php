<?php
session_start();
require_once __DIR__ . "/../config.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

$user_id = isset($_SESSION['Admin']['id']) ? (int)$_SESSION['Admin']['id'] : (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0);
if ($user_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

$page_name = isset($_POST['page_name']) ? trim($_POST['page_name']) : 'purchase-invoice-product-modal';
$tab_key = isset($_POST['tab_key']) ? trim($_POST['tab_key']) : '';
$preferences = isset($_POST['preferences']) ? json_decode($_POST['preferences'], true) : null;

if (!is_array($preferences)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid preferences']);
    exit;
}

$page_name_esc = mysqli_real_escape_string($conn, $page_name);
$tab_key_esc = mysqli_real_escape_string($conn, $tab_key);

// Check if tab_key column exists
$has_tab_key = false;
$res = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_user_column_preferences LIKE 'tab_key'");
if ($res && mysqli_num_rows($res) > 0) {
    $has_tab_key = true;
}

if (!$has_tab_key) {
    echo json_encode(['status' => 'success', 'message' => 'Tab column not in DB; run add_tab_key_to_user_column_preferences.sql']);
    exit;
}

mysqli_begin_transaction($conn);
try {
    $order = 0;
    foreach ($preferences as $column_key => $is_visible) {
        $col_esc = mysqli_real_escape_string($conn, $column_key);
        $vis = (int)(is_bool($is_visible) ? $is_visible : ($is_visible === 1 || $is_visible === '1' || $is_visible === true));
        $sql = "INSERT INTO tbl_user_column_preferences 
                (user_id, page_name, tab_key, column_key, column_order, is_visible, created_at) 
                VALUES ($user_id, '$page_name_esc', '$tab_key_esc', '$col_esc', $order, $vis, NOW())
                ON DUPLICATE KEY UPDATE is_visible = $vis, column_order = $order";
        if (!mysqli_query($conn, $sql)) {
            throw new Exception('Save failed: ' . mysqli_error($conn));
        }
        $order++;
    }
    mysqli_commit($conn);
    echo json_encode(['status' => 'success', 'message' => 'Column preferences saved']);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
