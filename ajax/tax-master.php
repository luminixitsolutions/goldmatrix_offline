<?php
session_start();
require_once __DIR__ . "/../config.php";

header('Content-Type: application/json');

$user_id = $_SESSION['Admin']['id'] ?? 0;
$action  = $_POST['action'] ?? '';
$table   = 'tbl_tax_master';

function tax_master_normalize_gst_supply_scope($raw) {
    $v = esc($raw ?? '');
    if ($v === 'out_of_state') {
        return 'out_of_state';
    }
    return 'local_state';
}

/* ================= ADD ================= */
if ($action === 'add') {
    $name = esc($_POST['name'] ?? '');
    $default_value = isset($_POST['default_value']) ? (float)$_POST['default_value'] : 0;
    $default_calculation_mode = esc($_POST['default_calculation_mode'] ?? 'Product Amount');
    $gst_supply_scope = tax_master_normalize_gst_supply_scope($_POST['gst_supply_scope'] ?? 'local_state');
    $sort_order = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;

    if ($name === '') {
        echo json_encode(["status" => "error", "message" => "Tax name is required"]);
        exit;
    }

    $bid = auragold_master_branch_id_for_writes($conn, $table);

    $sql = "INSERT INTO tbl_tax_master (name, default_value, default_calculation_mode, gst_supply_scope, sort_order, branch_id, status, created_at)
            VALUES ('$name', '$default_value', '$default_calculation_mode', '$gst_supply_scope', '$sort_order', '$bid', 1, NOW())";
    if (!mysqli_query($conn, $sql)) {
        echo json_encode(["status" => "error", "message" => mysqli_error($conn)]);
        exit;
    }

    echo json_encode([
        "status" => "success",
        "id" => mysqli_insert_id($conn),
        "name" => $name,
        "default_value" => $default_value,
        "default_calculation_mode" => $default_calculation_mode,
        "gst_supply_scope" => $gst_supply_scope,
        "sort_order" => $sort_order
    ]);
    exit;
}

/* ================= UPDATE ================= */
if ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    $name = esc($_POST['name'] ?? '');
    $default_value = isset($_POST['default_value']) ? (float)$_POST['default_value'] : 0;
    $default_calculation_mode = esc($_POST['default_calculation_mode'] ?? 'Product Amount');
    $gst_supply_scope = tax_master_normalize_gst_supply_scope($_POST['gst_supply_scope'] ?? 'local_state');
    $sort_order = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;

    if ($id <= 0 || $name === '') {
        echo json_encode(["status" => "error", "message" => "Invalid data"]);
        exit;
    }

    if (!auragold_master_can_mutate_row($conn, $table, $id)) {
        echo json_encode(["status" => "error", "message" => "Access denied for this branch"]);
        exit;
    }

    $sql = "UPDATE tbl_tax_master
            SET name = '$name', default_value = '$default_value', default_calculation_mode = '$default_calculation_mode', gst_supply_scope = '$gst_supply_scope', sort_order = '$sort_order', updated_at = NOW()
            WHERE id = '$id'";
    if (!mysqli_query($conn, $sql)) {
        echo json_encode(["status" => "error", "message" => mysqli_error($conn)]);
        exit;
    }

    echo json_encode([
        "status" => "success",
        "id" => $id,
        "name" => $name,
        "default_value" => $default_value,
        "default_calculation_mode" => $default_calculation_mode,
        "gst_supply_scope" => $gst_supply_scope,
        "sort_order" => $sort_order
    ]);
    exit;
}

/* ================= DELETE ================= */
if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(["status" => "error", "message" => "Invalid ID"]);
        exit;
    }
    if (!auragold_master_can_mutate_row($conn, $table, $id)) {
        echo json_encode(["status" => "error", "message" => "Access denied for this branch"]);
        exit;
    }
    mysqli_query($conn, "UPDATE tbl_tax_master SET status = 0, updated_at = NOW() WHERE id = '$id'");
    echo json_encode(["status" => "success"]);
    exit;
}

echo json_encode(["status" => "error", "message" => "Invalid action"]);
