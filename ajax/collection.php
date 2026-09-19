<?php
session_start();
require_once "../config.php";

header('Content-Type: application/json');

$user_id = $_SESSION['Admin']['id'] ?? 0;
$action  = $_POST['action'] ?? '';
$table   = 'tbl_collection';

/* ================= ADD ================= */
if ($action === 'add') {

    $name = esc($_POST['name'] ?? '');
    $desc = esc($_POST['description'] ?? '');

    if ($name == '') {
        echo json_encode(["status"=>"error","message"=>"Collection name required"]);
        exit;
    }

    $bid = auragold_master_branch_id_for_writes($conn, $table);

    mysqli_query($conn,"
        INSERT INTO tbl_collection (name, description, branch_id, created_by)
        VALUES ('$name','$desc','$bid','$user_id')
    ");

    echo json_encode([
        "status"=>"success",
        "id"=>mysqli_insert_id($conn),
        "name"=>$name,
        "description"=>$desc
    ]);
    exit;
}

/* ================= UPDATE ================= */
if ($action === 'update') {

    $id   = intval($_POST['id']);
    $name = esc($_POST['name'] ?? '');
    $desc = esc($_POST['description'] ?? '');

    if ($id == 0 || $name == '') {
        echo json_encode(["status"=>"error","message"=>"Invalid data"]);
        exit;
    }

    if (!auragold_master_can_mutate_row($conn, $table, $id)) {
        echo json_encode(["status"=>"error","message"=>"Access denied for this branch"]);
        exit;
    }

    mysqli_query($conn,"
        UPDATE tbl_collection
        SET name='$name',
            description='$desc',
            modified_by='$user_id'
        WHERE id='$id'
    ");

    echo json_encode([
        "status"=>"success",
        "id"=>$id,
        "name"=>$name,
        "description"=>$desc
    ]);
    exit;
}

/* ================= DELETE ================= */
if ($action === 'delete') {

    $id = intval($_POST['id']);

    if (!auragold_master_can_mutate_row($conn, $table, $id)) {
        echo json_encode(["status"=>"error","message"=>"Access denied for this branch"]);
        exit;
    }

    mysqli_query($conn,"
        UPDATE tbl_collection
        SET status=0, modified_by='$user_id'
        WHERE id='$id'
    ");

    echo json_encode(["status"=>"success"]);
    exit;
}

echo json_encode(["status"=>"error","message"=>"Invalid action"]);
