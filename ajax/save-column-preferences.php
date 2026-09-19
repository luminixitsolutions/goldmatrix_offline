<?php
session_start();
require_once "../config.php";

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit;
}

$user_id = isset($_SESSION['Admin']['id']) ? (int)$_SESSION['Admin']['id'] : 0;

if ($user_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit;
}

$page_name = 'product-opening';
$column_definitions = isset($_POST['column_definitions']) ? json_decode($_POST['column_definitions'], true) : [];
$column_order = isset($_POST['column_order']) ? json_decode($_POST['column_order'], true) : [];

mysqli_begin_transaction($conn);

try {
    // If column_order is provided, update order
    if (!empty($column_order) && is_array($column_order)) {
        foreach ($column_order as $index => $colKey) {
            $col = null;
            if (!empty($column_definitions) && is_array($column_definitions)) {
                $col = array_filter($column_definitions, function($c) use ($colKey) {
                    return isset($c['key']) && $c['key'] === $colKey;
                });
                $col = !empty($col) ? reset($col) : null;
            }
            
            $is_visible = ($col && isset($col['visible'])) ? ($col['visible'] ? 1 : 0) : 1;
            $colKey_escaped = mysqli_real_escape_string($conn, $colKey);
            $index = (int)$index;
            $is_visible = (int)$is_visible;
            
            $sql = "
                INSERT INTO tbl_user_column_preferences 
                (user_id, page_name, column_key, column_order, is_visible, created_at)
                VALUES 
                ($user_id, '$page_name', '$colKey_escaped', $index, $is_visible, NOW())
                ON DUPLICATE KEY UPDATE 
                column_order = $index,
                is_visible = $is_visible,
                updated_at = NOW()
            ";
            
            if (!mysqli_query($conn, $sql)) {
                throw new Exception("Column preference save failed for column '$colKey': " . mysqli_error($conn));
            }
        }
    }
    
    // If column_definitions is provided but column_order is not, update visibility and order from definitions
    // This handles cases where only visibility is changed without reordering
    if (empty($column_order) && !empty($column_definitions) && is_array($column_definitions)) {
        foreach ($column_definitions as $col) {
            if (!isset($col['key'])) continue;
            
            $colKey = mysqli_real_escape_string($conn, $col['key']);
            $is_visible = isset($col['visible']) ? ($col['visible'] ? 1 : 0) : 1;
            $col_order = isset($col['order']) ? (int)$col['order'] : 0;
            $is_visible = (int)$is_visible;
            
            $sql = "
                INSERT INTO tbl_user_column_preferences 
                (user_id, page_name, column_key, column_order, is_visible, created_at)
                VALUES 
                ($user_id, '$page_name', '$colKey', $col_order, $is_visible, NOW())
                ON DUPLICATE KEY UPDATE 
                column_order = $col_order,
                is_visible = $is_visible,
                updated_at = NOW()
            ";
            
            if (!mysqli_query($conn, $sql)) {
                throw new Exception("Column preference save failed for column '$colKey': " . mysqli_error($conn));
            }
        }
    }

    mysqli_commit($conn);

    echo json_encode([
        'status' => 'success',
        'message' => 'Column preferences saved successfully'
    ]);

} catch (Exception $e) {
    mysqli_rollback($conn);
    
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

