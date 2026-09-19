<?php
/**
 * Delete a Manufacturing queue row: weight adjustment or queue activity log line.
 */
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'message' => 'Invalid request']);
    exit;
}

$kind = isset($_POST['row_kind']) ? strtolower(trim((string)$_POST['row_kind'])) : '';
$sid = isset($_POST['source_id']) ? (int)$_POST['source_id'] : 0;

if ($sid < 1) {
    echo json_encode(['ok' => false, 'message' => 'Invalid row']);
    exit;
}

if ($kind === 'weight') {
    $chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_weight_adjustments'");
    if (!$chk || mysqli_num_rows($chk) === 0) {
        if ($chk) {
            mysqli_free_result($chk);
        }
        echo json_encode(['ok' => false, 'message' => 'Table not found']);
        exit;
    }
    mysqli_free_result($chk);
    $ok = @mysqli_query($conn, 'DELETE FROM tbl_jobwork_weight_adjustments WHERE id = ' . $sid . ' LIMIT 1');
    if ($ok && mysqli_affected_rows($conn) > 0) {
        echo json_encode(['ok' => true, 'message' => 'Deleted.']);
        exit;
    }
    echo json_encode(['ok' => false, 'message' => 'Could not delete.']);
    exit;
}

if ($kind === 'activity') {
    $chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_queue_activity'");
    if (!$chk || mysqli_num_rows($chk) === 0) {
        if ($chk) {
            mysqli_free_result($chk);
        }
        echo json_encode(['ok' => false, 'message' => 'Table not found']);
        exit;
    }
    mysqli_free_result($chk);
    $ok = @mysqli_query($conn, 'DELETE FROM tbl_jobwork_queue_activity WHERE id = ' . $sid . ' LIMIT 1');
    if ($ok && mysqli_affected_rows($conn) > 0) {
        echo json_encode(['ok' => true, 'message' => 'Deleted.']);
        exit;
    }
    echo json_encode(['ok' => false, 'message' => 'Could not delete.']);
    exit;
}

echo json_encode(['ok' => false, 'message' => 'Invalid row type']);
