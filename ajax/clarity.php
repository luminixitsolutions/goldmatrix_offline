<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$user   = $_SESSION['Admin']['id'] ?? 0;
$action = $_POST['action'] ?? '';
$table  = 'tbl_clarity';

if ($action === 'add') {
    $name = esc($_POST['name'] ?? '');
    $bid  = auragold_master_branch_id_for_writes($conn, $table);
    mysqli_query($conn, "INSERT INTO tbl_clarity (name, branch_id, created_by) VALUES ('$name','$bid','$user')");
    echo json_encode(['status' => 'success', 'id' => mysqli_insert_id($conn), 'name' => $name]);
    exit;
}

if ($action === 'update') {
    $id = (int) ($_POST['id'] ?? 0);
    $name = esc($_POST['name'] ?? '');
    if (!auragold_master_can_mutate_row($conn, $table, $id)) {
        echo json_encode(['status' => 'error', 'message' => 'Access denied for this branch']);
        exit;
    }
    mysqli_query($conn, "UPDATE tbl_clarity SET name='$name',modified_by='$user' WHERE id='$id'");
    echo json_encode(['status' => 'success', 'id' => $id, 'name' => $name]);
    exit;
}

if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    if (!auragold_master_can_mutate_row($conn, $table, $id)) {
        echo json_encode(['status' => 'error', 'message' => 'Access denied for this branch']);
        exit;
    }
    mysqli_query($conn, "UPDATE tbl_clarity SET status=0 WHERE id='$id'");
    echo json_encode(['status' => 'success']);
    exit;
}

echo json_encode(['status' => 'error']);
