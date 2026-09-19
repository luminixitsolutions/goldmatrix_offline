<?php
session_start();
require_once "../config.php";
header('Content-Type: application/json');

$user   = $_SESSION['Admin']['id'] ?? 0;
$action = $_POST['action'] ?? '';
$table  = 'tbl_document_types';

if (!function_exists('auragold_ensure_tbl_document_types')) {
    require_once __DIR__ . '/../includes/document_types_schema.php';
}
auragold_ensure_tbl_document_types($conn);

if ($action === 'add') {
    $name = esc(trim((string) ($_POST['name'] ?? '')));
    if ($name === '') {
        echo json_encode(['status' => 'error', 'message' => 'Name is required']);
        exit;
    }
    $bid  = auragold_master_branch_id_for_writes($conn, $table);
    mysqli_query(
        $conn,
        "INSERT INTO tbl_document_types (name, branch_id, created_by)
         VALUES ('$name', '$bid', '$user')"
    );
    echo json_encode(['status' => 'success', 'id' => mysqli_insert_id($conn)]);
    exit;
}

if ($action === 'update') {
    $id   = (int) $_POST['id'];
    $name = esc(trim((string) ($_POST['name'] ?? '')));
    if ($id <= 0 || $name === '') {
        echo json_encode(['status' => 'error', 'message' => 'Invalid data']);
        exit;
    }
    if (!auragold_master_can_mutate_row($conn, $table, $id)) {
        echo json_encode(['status' => 'error', 'message' => 'Access denied for this branch']);
        exit;
    }
    mysqli_query(
        $conn,
        "UPDATE tbl_document_types SET name='$name', modified_by='$user' WHERE id='$id'"
    );
    echo json_encode(['status' => 'success', 'id' => $id]);
    exit;
}

if ($action === 'delete') {
    $id = (int) $_POST['id'];
    if ($id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid id']);
        exit;
    }
    if (!auragold_master_can_mutate_row($conn, $table, $id)) {
        echo json_encode(['status' => 'error', 'message' => 'Access denied for this branch']);
        exit;
    }
    mysqli_query(
        $conn,
        "UPDATE tbl_document_types SET status=0, modified_by='$user' WHERE id='$id'"
    );
    echo json_encode(['status' => 'success']);
    exit;
}

echo json_encode(['status' => 'error']);
