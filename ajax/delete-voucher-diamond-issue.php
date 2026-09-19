<?php

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_voucher_diamond_stock.php';

header('Content-Type: application/json; charset=utf-8');

$authed = (isset($_SESSION['Admin']['id']) && (int) $_SESSION['Admin']['id'] > 0)
    || (isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0);
if (!$authed) {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'message' => 'Invalid request']);
    exit;
}

$raw = file_get_contents('php://input');
$data = [];
if ($raw !== '' && $raw !== false) {
    $data = json_decode($raw, true);
}
if (!is_array($data)) {
    $data = [];
}

$kind = isset($data['voucher_kind']) ? strtolower(trim((string) $data['voucher_kind'])) : '';
$vid = isset($data['voucher_id']) ? (int) $data['voucher_id'] : 0;
$issue_id = isset($data['issue_id']) ? (int) $data['issue_id'] : 0;

if ($vid < 1 || $issue_id < 1 || !auragold_voucher_diamond_kind_valid($kind)) {
    echo json_encode(['ok' => false, 'message' => 'Invalid parameters']);
    exit;
}

$tx_ok = true;
$tx_err = '';

if (function_exists('mysqli_begin_transaction')) {
    @mysqli_begin_transaction($conn);
} else {
    @mysqli_autocommit($conn, false);
}

$ok = auragold_voucher_remove_diamond_issue($conn, $kind, $vid, $issue_id, $tx_ok, $tx_err);

if ($tx_ok && $ok) {
    if (function_exists('mysqli_commit')) {
        mysqli_commit($conn);
    } else {
        mysqli_query($conn, 'COMMIT');
    }
    echo json_encode(['ok' => true, 'message' => 'Diamond allocation removed.']);
    exit;
}

if (function_exists('mysqli_rollback')) {
    mysqli_rollback($conn);
} else {
    mysqli_query($conn, 'ROLLBACK');
}
echo json_encode(['ok' => false, 'message' => $tx_err !== '' ? $tx_err : 'Could not remove allocation.']);
