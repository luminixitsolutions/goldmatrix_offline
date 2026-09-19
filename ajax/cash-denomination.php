<?php
session_start();
require_once "../config.php";
header('Content-Type: application/json');

$user   = $_SESSION['Admin']['id'] ?? 0;
$action = $_POST['action'] ?? '';
$table  = 'tbl_cash_denomination';

if($action === "add"){
    $type     = esc($_POST['type']);
    $amount   = esc($_POST['amount']);
    $currency = esc($_POST['currency']);
    $bid      = auragold_master_branch_id_for_writes($conn, $table);

    mysqli_query($conn,"
        INSERT INTO tbl_cash_denomination (type, amount, currency, branch_id, created_by)
        VALUES ('$type','$amount','$currency','$bid','$user')
    ");

    echo json_encode([
        "status"=>"success",
        "id"=>mysqli_insert_id($conn)
    ]);
    exit;
}

if($action === "update"){
    $id       = intval($_POST['id']);
    $type     = esc($_POST['type']);
    $amount   = esc($_POST['amount']);
    $currency = esc($_POST['currency']);

    if (!auragold_master_can_mutate_row($conn, $table, $id)) {
        echo json_encode(["status"=>"error","message"=>"Access denied for this branch"]);
        exit;
    }

    mysqli_query($conn,"
        UPDATE tbl_cash_denomination
        SET type='$type',
            amount='$amount',
            currency='$currency',
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
        UPDATE tbl_cash_denomination
        SET status=0,
            modified_by='$user'
        WHERE id='$id'
    ");

    echo json_encode(["status"=>"success"]);
    exit;
}

echo json_encode(["status"=>"error"]);
