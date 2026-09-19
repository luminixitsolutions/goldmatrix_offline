<?php
session_start();
require_once "../config.php";
header('Content-Type: application/json');

$user   = $_SESSION['Admin']['id'] ?? 0;
$action = $_POST['action'] ?? '';
$table  = 'tbl_customer_advance_policy';

if($action === "add"){
    $name    = esc($_POST['policy_name']);
    $days    = esc($_POST['days_duration']);
    $percent = esc($_POST['min_gold_percent']);
    $bid     = auragold_master_branch_id_for_writes($conn, $table);

    mysqli_query($conn,"
        INSERT INTO tbl_customer_advance_policy
        (policy_name, days_duration, min_gold_percent, branch_id, created_by)
        VALUES ('$name','$days','$percent','$bid','$user')
    ");

    echo json_encode([
        "status"=>"success",
        "id"=>mysqli_insert_id($conn)
    ]);
    exit;
}

if($action === "update"){
    $id      = intval($_POST['id']);
    $name    = esc($_POST['policy_name']);
    $days    = esc($_POST['days_duration']);
    $percent = esc($_POST['min_gold_percent']);

    if (!auragold_master_can_mutate_row($conn, $table, $id)) {
        echo json_encode(["status"=>"error","message"=>"Access denied for this branch"]);
        exit;
    }

    mysqli_query($conn,"
        UPDATE tbl_customer_advance_policy
        SET policy_name='$name',
            days_duration='$days',
            min_gold_percent='$percent',
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
        UPDATE tbl_customer_advance_policy
        SET status=0,
            modified_by='$user'
        WHERE id='$id'
    ");

    echo json_encode(["status"=>"success"]);
    exit;
}

echo json_encode(["status"=>"error"]);
