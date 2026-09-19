<?php
session_start();
require_once "../config.php";
header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$table  = 'tbl_customer_types';

$normalize_code = static function (string $raw): string {
    $s = strtoupper(trim($raw));
    $s = preg_replace('/\s+/', '_', $s);
    $s = preg_replace('/[^A-Z0-9_]/', '', $s);
    return $s;
};

if($action === "add"){
    $name = esc($_POST['name'] ?? '');
    $code = $normalize_code((string) ($_POST['code'] ?? ''));
    if ($name === '' || $code === '') {
        echo json_encode(["status"=>"error","message"=>"Name and a valid code are required."]);
        exit;
    }
    $bid  = auragold_master_branch_id_for_writes($conn, $table);
    $suf  = auragold_master_list_sql_suffix($conn, $table);
    $dup  = getRecord("SELECT id FROM `tbl_customer_types` WHERE status=1 AND LOWER(`code`)=LOWER('".esc($code)."') ".$suf." LIMIT 1");
    if ($dup) {
        echo json_encode(["status"=>"error","message"=>"A customer type with this code already exists."]);
        exit;
    }

    $sort = (int) ($_POST['sort_order'] ?? 0);
    if ($sort < 1) {
        $m = getRecord("SELECT MAX(sort_order) as m FROM `tbl_customer_types` WHERE status=1 " . $suf);
        $sort = (int) (($m['m'] ?? 0)) + 1;
    }

    $branchSql = "";
    if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, $table, 'branch_id')) {
        $branchSql = ",`branch_id`";
    }
    $branchVal = "";
    if (strpos($branchSql, 'branch_id') !== false) {
        $branchVal = ",'" . (int) $bid . "'";
    }

    $tSql = "";
    $tVal = "";
    if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, $table, 'created_at')) {
        $tSql = ",`created_at`";
        $tVal = ",NOW()";
    }
    if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, $table, 'updated_at')) {
        $tSql .= ",`updated_at`";
        $tVal .= ",NOW()";
    }

    mysqli_query($conn,"
        INSERT INTO `tbl_customer_types` (name, code, status, sort_order".$branchSql.$tSql.")
        VALUES ('$name','$code',1," . (int) $sort . $branchVal . $tVal . ")
    ");
    if (mysqli_error($conn) !== '') {
        echo json_encode(["status"=>"error","message"=>"Save failed."]);
        exit;
    }
    $newId = (int) mysqli_insert_id($conn);
    echo json_encode([
        "status"=>"success",
        "id"=> $newId > 0 ? $newId : 0
    ]);
    exit;
}

if($action === "update"){
    $id   = intval($_POST['id'] ?? 0);
    $name = esc($_POST['name'] ?? '');
    $code = $normalize_code((string) ($_POST['code'] ?? ''));
    if ($id <= 0 || $name === '' || $code === '') {
        echo json_encode(["status"=>"error","message"=>"Name and a valid code are required."]);
        exit;
    }

    if (!auragold_master_can_mutate_row($conn, $table, $id)) {
        echo json_encode(["status"=>"error","message"=>"Access denied for this branch"]);
        exit;
    }
    $suf = auragold_master_list_sql_suffix($conn, $table);
    $dup  = getRecord("SELECT id FROM `tbl_customer_types` WHERE status=1 AND LOWER(`code`)=LOWER('".esc($code)."') AND id <> ".$id." ".$suf." LIMIT 1");
    if ($dup) {
        echo json_encode(["status"=>"error","message"=>"A customer type with this code already exists."]);
        exit;
    }

    $sort = (int) ($_POST['sort_order'] ?? 0);
    if ($sort < 0) {
        $sort = 0;
    }
    $upd = "UPDATE `tbl_customer_types`
        SET `name`='$name',
            `code`='".esc($code)."',
            `sort_order`='".$sort."'
        WHERE id='".$id."'";
    if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, $table, 'updated_at')) {
        $upd = "UPDATE `tbl_customer_types`
        SET `name`='$name',
            `code`='".esc($code)."',
            `sort_order`='".$sort."',
            `updated_at`=NOW()
        WHERE id='".$id."'";
    }

    mysqli_query($conn, $upd);
    if (mysqli_error($conn) !== '') {
        echo json_encode(["status"=>"error","message"=>"Update failed."]);
        exit;
    }
    echo json_encode([
        "status"=>"success",
        "id"=>$id
    ]);
    exit;
}

if($action === "delete"){
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(["status"=>"error","message"=>"Invalid id"]);
        exit;
    }

    if (!auragold_master_can_mutate_row($conn, $table, $id)) {
        echo json_encode(["status"=>"error","message"=>"Access denied for this branch"]);
        exit;
    }
    if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_customers', 'customer_type_id')) {
        $use = getRecord("SELECT 1 as x FROM `tbl_customers` WHERE `customer_type_id`=" . (int) $id . " LIMIT 1");
        if ($use) {
            echo json_encode(["status"=>"error","message"=>"This type is linked to one or more customers. Reassign them first."]);
            exit;
        }
    }

    $set = "status=0";
    if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, $table, 'updated_at')) {
        $set = "status=0, `updated_at`=NOW()";
    }
    mysqli_query($conn,"
        UPDATE `tbl_customer_types` SET $set
        WHERE id='".$id."'
    ");
    if (mysqli_error($conn) !== '') {
        echo json_encode(["status"=>"error","message"=>"Delete failed."]);
        exit;
    }
    echo json_encode(["status"=>"success"]);
    exit;
}

echo json_encode(["status"=>"error","message"=>"Invalid request"]);
