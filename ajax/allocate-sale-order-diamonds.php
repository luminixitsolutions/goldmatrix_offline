<?php

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_sale_order_diamond_stock.php';

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

$raw = isset($_POST['payload']) ? $_POST['payload'] : file_get_contents('php://input');
$data = null;
if (is_string($raw)) {
    $data = json_decode($raw, true);
}
if (!is_array($data)) {
    $data = $_POST;
}

$order_id = isset($data['order_id']) ? (int) $data['order_id'] : 0;
$lines_in = isset($data['lines']) && is_array($data['lines']) ? $data['lines'] : [];

if ($order_id < 1) {
    echo json_encode(['ok' => false, 'message' => 'Save the sale order first, then allocate diamonds.']);
    exit;
}

$order = function_exists('getRecord')
    ? getRecord('SELECT id, order_no, order_date FROM tbl_sale_orders WHERE id = ' . $order_id . ' LIMIT 1')
    : null;
if (!$order || empty($order['id'])) {
    echo json_encode(['ok' => false, 'message' => 'Sale order not found.']);
    exit;
}

$order_no = trim((string) ($order['order_no'] ?? ''));
$order_date = trim((string) ($order['order_date'] ?? ''));
if ($order_no === '') {
    $order_no = 'SO-' . $order_id;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $order_date)) {
    $order_date = date('Y-m-d');
}

$lines = [];
foreach ($lines_in as $ln) {
    if (!is_array($ln)) {
        continue;
    }
    $sid = (int) ($ln['stock_id'] ?? 0);
    $qty = isset($ln['allocate_qty']) ? (float) $ln['allocate_qty'] : (isset($ln['qty']) ? (float) $ln['qty'] : 0);
    $wt = isset($ln['allocate_weight']) ? (float) $ln['allocate_weight'] : (isset($ln['weight']) ? (float) $ln['weight'] : 0);
    if ($sid < 1) {
        continue;
    }
    if ($qty <= 0 && $wt <= 0) {
        continue;
    }
    $lines[] = [
        'stock_id' => $sid,
        'barcode' => isset($ln['barcode']) ? trim((string) $ln['barcode']) : '',
        'qty' => $qty,
        'weight' => $wt,
        'product_name' => isset($ln['product_name']) ? trim((string) $ln['product_name']) : '',
        'diamond_category' => isset($ln['diamond_category']) ? trim((string) $ln['diamond_category']) : '',
    ];
}

if ($lines === []) {
    echo json_encode(['ok' => false, 'message' => 'Select at least one row with allocate quantity or weight.']);
    exit;
}

mysqli_begin_transaction($conn);
$tx_ok = true;
$tx_err = '';
try {
    $stats = auragold_sale_order_apply_diamond_allocations($conn, $order_id, $lines, $order_no, $order_date, $tx_ok, $tx_err);
    if (!$tx_ok) {
        mysqli_rollback($conn);
        echo json_encode(['ok' => false, 'message' => $tx_err ?: 'Allocation failed']);
        exit;
    }
    mysqli_commit($conn);
    echo json_encode([
        'ok' => true,
        'message' => $stats['saved'] > 0
            ? ('Allocated ' . (int) $stats['saved'] . ' diamond line(s). Stock updated.')
            : 'Nothing allocated.',
        'saved' => (int) $stats['saved'],
    ]);
} catch (Throwable $e) {
    mysqli_rollback($conn);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
