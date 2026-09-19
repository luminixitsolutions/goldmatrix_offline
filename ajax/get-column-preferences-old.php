<?php
session_start();
require_once "../config.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

$user_id = isset($_SESSION['Admin']['id']) ? (int)$_SESSION['Admin']['id'] : (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0);
$page_name = isset($_POST['page_name']) ? esc($_POST['page_name']) : 'product-opening';

if ($user_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

$page_name_esc = mysqli_real_escape_string($conn, $page_name);

// Check if tab_key column exists for tab-wise preferences
$has_tab_key = false;
$res = mysqli_query($conn, "SHOW COLUMNS FROM tbl_user_column_preferences LIKE 'tab_key'");
if ($res && mysqli_num_rows($res) > 0) {
    $has_tab_key = true;
}

if ($has_tab_key) {
    $rows = getList("
        SELECT column_key, column_order, is_visible, COALESCE(tab_key,'') as tab_key 
        FROM tbl_user_column_preferences 
        WHERE user_id = $user_id AND page_name = '$page_name_esc'
        ORDER BY tab_key ASC, column_order ASC
    ");
    $preferences = [];
    $by_tab = [];
    foreach ($rows as $r) {
        $preferences[] = ['column_key' => $r['column_key'], 'column_order' => (int)$r['column_order'], 'is_visible' => (int)$r['is_visible']];
        $tk = $r['tab_key'] === null || $r['tab_key'] === '' ? '' : (string)$r['tab_key'];
        if (!isset($by_tab[$tk])) $by_tab[$tk] = [];
        $by_tab[$tk][$r['column_key']] = (int)$r['is_visible'];
    }
    echo json_encode(['status' => 'success', 'preferences' => $preferences, 'by_tab' => $by_tab]);
} else {
    $preferences = getList("
        SELECT column_key, column_order, is_visible 
        FROM tbl_user_column_preferences 
        WHERE user_id = $user_id AND page_name = '$page_name_esc'
        ORDER BY column_order ASC
    ");
    echo json_encode(['status' => 'success', 'preferences' => $preferences]);
}

