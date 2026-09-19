<?php
session_start();
require_once "../config.php";

header('Content-Type: application/json');

$user_id = $_SESSION['Admin']['id'] ?? 0;
$action  = $_POST['action'] ?? '';
$table   = 'tbl_unit_conversion';

/* ========= ADD ========= */
if($action === 'add'){

    $name = esc($_POST['name']);
    $unit = intval($_POST['unit_id']);
    $rate = esc($_POST['conversion_rate']);
    $qty  = esc($_POST['quantity']);
    $bid  = auragold_master_branch_id_for_writes($conn, $table);

    mysqli_query($conn,"
        INSERT INTO tbl_unit_conversion
        (name, unit_id, conversion_rate, quantity, branch_id, created_by)
        VALUES
        ('$name','$unit','$rate','$qty','$bid','$user_id')
    ");

    echo json_encode([
        "status"=>"success",
        "id"=>mysqli_insert_id($conn),
        "name"=>$name,
        "unit_id"=>$unit,
        "conversion_rate"=>$rate,
        "quantity"=>$qty
    ]);
    exit;
}

/* ========= UPDATE ========= */
if($action === 'update'){

    $id   = intval($_POST['id']);
    $name = esc($_POST['name']);
    $unit = intval($_POST['unit_id']);
    $rate = esc($_POST['conversion_rate']);
    $qty  = esc($_POST['quantity']);

    if (!auragold_master_can_mutate_row($conn, $table, $id)) {
        echo json_encode(["status"=>"error","message"=>"Access denied for this branch"]);
        exit;
    }

    mysqli_query($conn,"
        UPDATE tbl_unit_conversion
        SET name='$name',
            unit_id='$unit',
            conversion_rate='$rate',
            quantity='$qty',
            modified_by='$user_id'
        WHERE id='$id'
    ");

    echo json_encode([
        "status"=>"success",
        "id"=>$id,
        "name"=>$name,
        "unit_id"=>$unit,
        "conversion_rate"=>$rate,
        "quantity"=>$qty
    ]);
    exit;
}

/* ========= DELETE ========= */
if($action === 'delete'){

    $id = intval($_POST['id']);

    if (!auragold_master_can_mutate_row($conn, $table, $id)) {
        echo json_encode(["status"=>"error","message"=>"Access denied for this branch"]);
        exit;
    }

    mysqli_query($conn,"
        UPDATE tbl_unit_conversion
        SET status=0, modified_by='$user_id'
        WHERE id='$id'
    ");

    echo json_encode(["status"=>"success"]);
    exit;
}

echo json_encode(["status"=>"error","message"=>"Invalid action"]);
