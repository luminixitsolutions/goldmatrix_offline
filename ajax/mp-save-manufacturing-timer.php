<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$id = isset($_POST['jobwork_order_id']) ? (int)$_POST['jobwork_order_id'] : 0;
$seconds = isset($_POST['seconds']) ? (int)$_POST['seconds'] : -1;

if ($id <= 0 || $seconds < 0) {
    echo json_encode(['ok' => false, 'message' => 'Invalid input']);
    exit;
}
if ($seconds > 999999999) {
    $seconds = 999999999;
}

$chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_orders'");
if (!$chk || mysqli_num_rows($chk) === 0) {
    if ($chk) {
        mysqli_free_result($chk);
    }
    echo json_encode(['ok' => false, 'message' => 'Table missing']);
    exit;
}
mysqli_free_result($chk);

$colq = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_orders LIKE 'manufacturing_time_seconds'");
if (!$colq || mysqli_num_rows($colq) === 0) {
    if ($colq) {
        mysqli_free_result($colq);
    }
    @mysqli_query($conn, "ALTER TABLE tbl_jobwork_orders ADD COLUMN manufacturing_time_seconds INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Cumulative manufacturing time (seconds)'");
    $colq2 = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_orders LIKE 'manufacturing_time_seconds'");
    if (!$colq2 || mysqli_num_rows($colq2) === 0) {
        if ($colq2) {
            mysqli_free_result($colq2);
        }
        echo json_encode(['ok' => false, 'message' => 'Could not add manufacturing_time_seconds column. Run admin/sql/alter_tbl_jobwork_orders_manufacturing_time_seconds.sql']);
        exit;
    }
    mysqli_free_result($colq2);
} else {
    mysqli_free_result($colq);
}

$stmt = mysqli_prepare($conn, 'UPDATE tbl_jobwork_orders SET manufacturing_time_seconds = ? WHERE id = ? LIMIT 1');
if (!$stmt) {
    echo json_encode(['ok' => false, 'message' => 'Prepare failed']);
    exit;
}
mysqli_stmt_bind_param($stmt, 'ii', $seconds, $id);
$ok = mysqli_stmt_execute($stmt);
$affected = mysqli_stmt_affected_rows($stmt);
mysqli_stmt_close($stmt);

if (!$ok) {
    echo json_encode(['ok' => false, 'message' => 'Update failed']);
    exit;
}

echo json_encode(['ok' => true, 'seconds' => $seconds, 'affected' => $affected]);