<?php

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_voucher_diamond_stock.php';

header('Content-Type: application/json; charset=utf-8');

$authed = (isset($_SESSION['Admin']['id']) && (int) $_SESSION['Admin']['id'] > 0)
    || (isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0);
if (!$authed) {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized', 'items' => []]);
    exit;
}

$order_id = isset($_GET['order_id']) ? (int) $_GET['order_id'] : (isset($_GET['id']) ? (int) $_GET['id'] : 0);
if ($order_id < 1) {
    echo json_encode(['ok' => false, 'message' => 'Invalid order id', 'items' => []]);
    exit;
}

$items = auragold_voucher_list_diamond_issue_rows_for_kind($conn, 'sale_order', $order_id);

echo json_encode(['ok' => true, 'items' => $items]);
