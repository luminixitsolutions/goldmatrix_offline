<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$user   = $_SESSION['Admin']['id'] ?? 0;
$action = $_POST['action'] ?? '';
$table  = 'tbl_currency';

/** When setting is_base=1, clear other base flags (per branch when in a branch session; else legacy global clear). */
function auragold_currency_reset_base_for_branch(mysqli $conn, string $table): void {
    auragold_ensure_table_branch_id_column($conn, $table);
    if (!auragold_tbl_has_column($conn, $table, 'branch_id')) {
        mysqli_query($conn, 'UPDATE tbl_currency SET is_base=0');

        return;
    }
    $eff = auragold_effective_branch_id();
    if ($eff > 0) {
        mysqli_query($conn, 'UPDATE tbl_currency SET is_base=0 WHERE branch_id = ' . (int) $eff);
    } else {
        mysqli_query($conn, 'UPDATE tbl_currency SET is_base=0');
    }
}

if ($action === 'add') {

    if ((int) ($_POST['is_base'] ?? 0) === 1) {
        auragold_currency_reset_base_for_branch($conn, $table);
    }

    $name = esc($_POST['name']);
    $dec  = intval($_POST['decimal_places']);
    $sym  = esc($_POST['symbol']);
    $desc = esc($_POST['description']);
    $base = intval($_POST['is_base']);
    $bid  = auragold_master_branch_id_for_writes($conn, $table);

    mysqli_query($conn, "
        INSERT INTO tbl_currency
        (name,decimal_places,symbol,description,is_base,branch_id,created_by)
        VALUES('$name','$dec','$sym','$desc','$base','$bid','$user')
    ");

    echo json_encode([
        'status' => 'success',
        'id'     => mysqli_insert_id($conn),
    ]);
    exit;
}

if ($action === 'update') {

    $id = intval($_POST['id']);
    if (!auragold_master_can_mutate_row($conn, $table, $id)) {
        echo json_encode(['status' => 'error', 'message' => 'Access denied for this branch']);
        exit;
    }

    if ((int) ($_POST['is_base'] ?? 0) === 1) {
        auragold_currency_reset_base_for_branch($conn, $table);
    }

    $name = esc($_POST['name']);
    $dec  = intval($_POST['decimal_places']);
    $sym  = esc($_POST['symbol']);
    $desc = esc($_POST['description']);
    $base = intval($_POST['is_base']);

    mysqli_query($conn, "
        UPDATE tbl_currency SET
        name='$name',
        decimal_places='$dec',
        symbol='$sym',
        description='$desc',
        is_base='$base',
        modified_by='$user'
        WHERE id='$id'
    ");

    echo json_encode([
        'status' => 'success',
        'id'     => $id,
    ]);
    exit;
}

if ($action === 'delete') {
    $id = intval($_POST['id']);
    if (!auragold_master_can_mutate_row($conn, $table, $id)) {
        echo json_encode(['status' => 'error', 'message' => 'Access denied for this branch']);
        exit;
    }
    mysqli_query($conn, "UPDATE tbl_currency SET status=0 WHERE id='$id'");
    echo json_encode(['status' => 'success']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
