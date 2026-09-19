<?php
session_start();
require_once "../config.php";

header('Content-Type: application/json');

$exclude_ids = [];
if (isset($_POST['exclude_ids']) && is_array($_POST['exclude_ids'])) {
    $exclude_ids = array_map('intval', $_POST['exclude_ids']);
}

// Build WHERE clause
$where_clause = "status = 1";
if (!empty($exclude_ids)) {
    $exclude_ids_str = implode(',', $exclude_ids);
    $where_clause .= " AND id NOT IN ($exclude_ids_str)";
}

// Fetch branches
$branches = getListMaster("SELECT id, name, code FROM tbl_branches WHERE $where_clause ORDER BY name ASC");

echo json_encode([
    'status' => 'success',
    'branches' => $branches
]);

