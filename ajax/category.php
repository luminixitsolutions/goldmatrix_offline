<?php
session_start();
require_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

if (!function_exists('auragold_ensure_tbl_categories_columns')) {
    /**
     * Extend legacy tbl_categories (id, name, status, created_at) for product modal Add Category form.
     */
    function auragold_ensure_tbl_categories_columns($conn): void
    {
        if (!$conn instanceof mysqli || !function_exists('auragold_tbl_has_column')) {
            return;
        }
        $columns = [
            'short_code' => "VARCHAR(100) NULL DEFAULT ''",
            'parent_id' => 'INT(11) NOT NULL DEFAULT 0',
            'min_qty' => 'DECIMAL(18,3) NOT NULL DEFAULT 0',
            'max_qty' => 'DECIMAL(18,3) NOT NULL DEFAULT 0',
            'min_wt' => 'DECIMAL(18,3) NOT NULL DEFAULT 0',
            'max_wt' => 'DECIMAL(18,3) NOT NULL DEFAULT 0',
            'created_by' => 'INT(11) NULL DEFAULT NULL',
            'updated_at' => 'DATETIME NULL DEFAULT NULL',
        ];
        foreach ($columns as $col => $def) {
            if (!auragold_tbl_has_column($conn, 'tbl_categories', $col)) {
                @mysqli_query($conn, 'ALTER TABLE tbl_categories ADD COLUMN `' . $col . '` ' . $def);
            }
        }
    }
}

auragold_ensure_tbl_categories_columns($conn);

$user   = (int) ($_SESSION['Admin']['id'] ?? 0);
$action = $_POST['action'] ?? ($_GET['action'] ?? '');

function auragold_category_json_response(array $payload): void
{
    echo json_encode($payload);
    exit;
}

try {
    if ($action === 'add') {
        $name = esc($_POST['name'] ?? '');
        $short_code = esc($_POST['short_code'] ?? '');
        $parent_id = isset($_POST['parent_id']) ? (int) $_POST['parent_id'] : 0;
        $min_qty = isset($_POST['min_qty']) ? (float) $_POST['min_qty'] : 0;
        $max_qty = isset($_POST['max_qty']) ? (float) $_POST['max_qty'] : 0;
        $min_wt = isset($_POST['min_wt']) ? (float) $_POST['min_wt'] : 0;
        $max_wt = isset($_POST['max_wt']) ? (float) $_POST['max_wt'] : 0;
        $is_active = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 1;
        if ($is_active !== 0) {
            $is_active = 1;
        }

        if ($name === '') {
            auragold_category_json_response([
                'status' => 'error',
                'message' => 'Category name is required',
            ]);
        }

        $hasExtended = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_categories', 'short_code');
        if ($hasExtended) {
            $sql = "INSERT INTO tbl_categories (name, short_code, parent_id, min_qty, max_qty, min_wt, max_wt, status, created_by, created_at)
                    VALUES ('$name', '$short_code', $parent_id, $min_qty, $max_qty, $min_wt, $max_wt, $is_active, $user, NOW())";
        } else {
            $sql = "INSERT INTO tbl_categories (name, status, created_at)
                    VALUES ('$name', $is_active, NOW())";
        }

        if (!mysqli_query($conn, $sql)) {
            auragold_category_json_response([
                'status' => 'error',
                'message' => 'Error: ' . mysqli_error($conn),
            ]);
        }

        auragold_category_json_response([
            'status' => 'success',
            'message' => 'Category added successfully',
            'id' => (int) mysqli_insert_id($conn),
            'name' => $name,
        ]);
    }

    if ($action === 'update') {
        $id   = (int) ($_POST['id'] ?? 0);
        $name = esc($_POST['name'] ?? '');
        $short_code = esc($_POST['short_code'] ?? '');
        $parent_id = isset($_POST['parent_id']) ? (int) $_POST['parent_id'] : 0;
        $min_qty = isset($_POST['min_qty']) ? (float) $_POST['min_qty'] : 0;
        $max_qty = isset($_POST['max_qty']) ? (float) $_POST['max_qty'] : 0;
        $min_wt = isset($_POST['min_wt']) ? (float) $_POST['min_wt'] : 0;
        $max_wt = isset($_POST['max_wt']) ? (float) $_POST['max_wt'] : 0;
        $is_active = isset($_POST['is_active']) ? (int) $_POST['is_active'] : 0;
        $is_active = $is_active ? 1 : 0;

        if ($name === '' || $id <= 0) {
            auragold_category_json_response([
                'status' => 'error',
                'message' => 'Invalid data',
            ]);
        }

        $hasExtended = function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_categories', 'short_code');
        if ($hasExtended) {
            $sql = "UPDATE tbl_categories
                    SET name = '$name', short_code = '$short_code', parent_id = $parent_id,
                        min_qty = $min_qty, max_qty = $max_qty, min_wt = $min_wt, max_wt = $max_wt,
                        status = $is_active, updated_at = NOW()
                    WHERE id = $id";
        } else {
            $sql = "UPDATE tbl_categories SET name = '$name', status = $is_active WHERE id = $id";
        }

        if (!mysqli_query($conn, $sql)) {
            auragold_category_json_response([
                'status' => 'error',
                'message' => 'Error: ' . mysqli_error($conn),
            ]);
        }

        auragold_category_json_response([
            'status' => 'success',
            'message' => 'Category updated successfully',
        ]);
    }

    if ($action === 'list' || $action === '') {
        $select = 'id, name';
        if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_categories', 'short_code')) {
            $select .= ', short_code';
        }
        $categories = getList("SELECT $select FROM tbl_categories WHERE status = 1 ORDER BY name ASC");
        if (!is_array($categories)) {
            $categories = [];
        }
        auragold_category_json_response([
            'status' => 'success',
            'categories' => $categories,
        ]);
    }

    auragold_category_json_response([
        'status' => 'error',
        'message' => 'Invalid action',
    ]);
} catch (Throwable $e) {
    auragold_category_json_response([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}
