<?php
session_start();
require_once "../config.php";
header('Content-Type: application/json');

$user = $_SESSION['Admin']['id'] ?? 0;
$action = $_POST['action'] ?? '';
$table  = 'tbl_currency_exchange_rate';

if ($action === "add") {

    $cid  = intval($_POST['currency_id']);
    $rate = esc($_POST['rate']);
    $desc = esc($_POST['description']);
    $rowStatus = isset($_POST['status']) ? ((int) $_POST['status'] === 1 ? 1 : 0) : 1;
    $bid  = auragold_master_branch_id_for_writes($conn, $table);

    mysqli_query($conn, "
        INSERT INTO tbl_currency_exchange_rate
        (currency_id, rate, description, status, branch_id, created_by)
        VALUES ('$cid','$rate','$desc','$rowStatus','$bid','$user')
    ");

    echo json_encode([
        "status" => "success",
        "id" => mysqli_insert_id($conn),
        "row_status" => $rowStatus
    ]);
    exit;
}

if ($action === "update") {

    $id   = intval($_POST['id']);
    $cid  = intval($_POST['currency_id']);
    $rate = esc($_POST['rate']);
    $desc = esc($_POST['description']);
    $rowStatus = isset($_POST['status']) ? ((int) $_POST['status'] === 1 ? 1 : 0) : 1;

    if (!auragold_master_can_mutate_row($conn, $table, $id)) {
        echo json_encode(["status" => "error", "message" => "Access denied for this branch"]);
        exit;
    }

    mysqli_query($conn, "
        UPDATE tbl_currency_exchange_rate
        SET currency_id='$cid',
            rate='$rate',
            description='$desc',
            status='$rowStatus',
            modified_by='$user'
        WHERE id='$id'
    ");

    echo json_encode([
        "status" => "success",
        "id" => $id,
        "row_status" => $rowStatus
    ]);
    exit;
}

if ($action === "delete") {

    $id = intval($_POST['id']);

    if (!auragold_master_can_mutate_row($conn, $table, $id)) {
        echo json_encode(["status" => "error", "message" => "Access denied for this branch"]);
        exit;
    }

    mysqli_query($conn, "
        UPDATE tbl_currency_exchange_rate
        SET status=0, modified_by='$user'
        WHERE id='$id'
    ");

    echo json_encode(["status" => "success"]);
    exit;
}

echo json_encode(["status" => "error", "message" => "Invalid action"]);
