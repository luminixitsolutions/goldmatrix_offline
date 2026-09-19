<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$limit = isset($_GET['limit']) ? min(100, max(10, (int)$_GET['limit'])) : 50;

$table_exists = false;
$t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_expense_categories'");
if ($t && mysqli_num_rows($t) > 0) {
    $table_exists = true;
}

$categories = [];
if ($table_exists) {
    $where = " status = 1 ";
    if ($search !== '') {
        $search_esc = mysqli_real_escape_string($conn, $search);
        $where .= " AND ( name LIKE '%$search_esc%' OR type LIKE '%$search_esc%' ) ";
    }
    $sql = "SELECT id, name, type, sort_order FROM tbl_expense_categories WHERE $where ORDER BY sort_order ASC, name ASC LIMIT $limit";
    $categories = getList($sql);
}

$result = [];
foreach ($categories as $row) {
    $display = $row['name'];
    if (!empty($row['type'])) {
        $display .= ' (' . $row['type'] . ')';
    }
    $result[] = [
        'id' => (int)$row['id'],
        'name' => $row['name'],
        'type' => $row['type'] ?? '',
        'display_text' => $display
    ];
}

echo json_encode(['status' => 'success', 'categories' => $result]);
