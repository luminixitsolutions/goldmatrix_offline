<?php
session_start();
require_once "../config.php";

header('Content-Type: application/json');

$user_id = $_SESSION['Admin']['id'] ?? 0;
$action  = $_POST['action'] ?? '';
$table   = 'tbl_unit';

/* ========== ADD ========== */
if ($action === 'add') {

    $name   = esc($_POST['name']);
    $formal = esc($_POST['formal_name']);

    if($name=='' || $formal==''){
        echo json_encode(["status"=>"error","message"=>"All fields required"]);
        exit;
    }

    $bid = auragold_master_branch_id_for_writes($conn, $table);

    mysqli_query($conn,"
        INSERT INTO tbl_unit (name, formal_name, branch_id, created_by)
        VALUES ('$name','$formal','$bid','$user_id')
    ");

    echo json_encode([
        "status"=>"success",
        "id"=>mysqli_insert_id($conn),
        "name"=>$name,
        "formal_name"=>$formal
    ]);
    exit;
}

/* ========== UPDATE ========== */
if ($action === 'update') {

    $id     = intval($_POST['id']);
    $name   = esc($_POST['name']);
    $formal = esc($_POST['formal_name']);

    if (!auragold_master_can_mutate_row($conn, $table, $id)) {
        echo json_encode(["status"=>"error","message"=>"Access denied for this branch"]);
        exit;
    }

    mysqli_query($conn,"
        UPDATE tbl_unit
        SET name='$name',
            formal_name='$formal',
            modified_by='$user_id'
        WHERE id='$id'
    ");

    echo json_encode([
        "status"=>"success",
        "id"=>$id,
        "name"=>$name,
        "formal_name"=>$formal
    ]);
    exit;
}

/* ========== DELETE (SOFT) ========== */
if ($action === 'delete') {

    $id = intval($_POST['id']);

    if (!auragold_master_can_mutate_row($conn, $table, $id)) {
        echo json_encode(["status"=>"error","message"=>"Access denied for this branch"]);
        exit;
    }

    mysqli_query($conn,"
        UPDATE tbl_unit
        SET status=0, modified_by='$user_id'
        WHERE id='$id'
    ");

    echo json_encode(["status"=>"success"]);
    exit;
}

echo json_encode(["status"=>"error","message"=>"Invalid action"]);
