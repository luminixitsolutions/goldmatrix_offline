<?php

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_voucher_stone_stock.php';

header('Content-Type: application/json; charset=utf-8');

$authed = (isset($_SESSION['Admin']['id']) && (int) $_SESSION['Admin']['id'] > 0)
    || (isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0);
if (!$authed) {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized', 'items' => []]);
    exit;
}

$kind = isset($_GET['voucher_kind']) ? strtolower(trim((string) $_GET['voucher_kind'])) : '';
$vid = isset($_GET['voucher_id']) ? (int) $_GET['voucher_id'] : (isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0);

if ($vid < 1 || !auragold_voucher_diamond_kind_valid($kind)) {
    echo json_encode(['ok' => false, 'message' => 'Invalid parameters', 'items' => []]);
    exit;
}

$items = auragold_voucher_list_stone_issue_rows_for_kind($conn, $kind, $vid);

echo json_encode(['ok' => true, 'items' => $items]);
