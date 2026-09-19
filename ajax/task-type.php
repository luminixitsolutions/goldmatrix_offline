<?php
session_start();
require_once "../config.php";
header('Content-Type: application/json');

$user   = $_SESSION['Admin']['id'] ?? 0;
$action = $_POST['action'] ?? '';
$table  = 'tbl_task_type';

if($action === "add"){
    $name   = esc($_POST['name']);
    $usedIn = esc($_POST['used_in']);
    $bid    = auragold_master_branch_id_for_writes($conn, $table);

    mysqli_query($conn,"
        INSERT INTO tbl_task_type (name, used_in, branch_id, created_by)
        VALUES ('$name','$usedIn','$bid','$user')
    ");

    echo json_encode([
        "status"=>"success",
        "id"=>mysqli_insert_id($conn)
    ]);
    exit;
}

if($action === "update"){
    $id     = intval($_POST['id']);
    $name   = esc($_POST['name']);
    $usedIn = esc($_POST['used_in']);

    if (!auragold_master_can_mutate_row($conn, $table, $id)) {
        echo json_encode(["status"=>"error","message"=>"Access denied for this branch"]);
        exit;
    }

    mysqli_query($conn,"
        UPDATE tbl_task_type
        SET name='$name',
            used_in='$usedIn',
            modified_by='$user'
        WHERE id='$id'
    ");

    echo json_encode([
        "status"=>"success",
        "id"=>$id
    ]);
    exit;
}

if($action === "delete"){
    $id = intval($_POST['id']);

    if (!auragold_master_can_mutate_row($conn, $table, $id)) {
        echo json_encode(["status"=>"error","message"=>"Access denied for this branch"]);
        exit;
    }

    mysqli_query($conn,"
        UPDATE tbl_task_type
        SET status=0, modified_by='$user'
        WHERE id='$id'
    ");

    echo json_encode(["status"=>"success"]);
    exit;
}

echo json_encode(["status"=>"error"]);
