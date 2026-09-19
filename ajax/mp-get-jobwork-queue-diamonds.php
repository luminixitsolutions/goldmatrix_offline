<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once dirname(__DIR__) . '/includes/mp-jobwork-queue-diamond-stock.php';

header('Content-Type: application/json; charset=utf-8');

$authed = (isset($_SESSION['Admin']['id']) && (int) $_SESSION['Admin']['id'] > 0)
    || (isset($_SESSION['user_id']) && (int) $_SESSION['user_id'] > 0);
if (!$authed) {
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}

$jobwork_order_id = isset($_GET['jobwork_order_id']) ? (int) $_GET['jobwork_order_id'] : 0;
$item_id = isset($_GET['item_id']) ? (int) $_GET['item_id'] : 0;
if ($jobwork_order_id < 1) {
    echo json_encode(['ok' => false, 'message' => 'Invalid job work order']);
    exit;
}

$rows = mp_jwq_list_diamond_stock_issues($conn, $jobwork_order_id, $item_id);
if ($item_id > 0 && (!is_array($rows) || count($rows) === 0)) {
    $rows = mp_jwq_list_diamond_stock_issues($conn, $jobwork_order_id, 0);
}
$items = [];
if (is_array($rows)) {
    foreach ($rows as $r) {
        if (!is_array($r)) {
            continue;
        }
        $rowSource = 'issue';
        if (!empty($r['_line_fallback'])) {
            $rowSource = 'line_fallback';
        } else {
            $sid = (int) ($r['stock_id'] ?? 0);
            $bc = trim((string) ($r['barcode'] ?? ''));
            if ($sid < 1 || $bc === '') {
                $rowSource = 'line_fallback';
            }
        }
        $items[] = [
            'id' => (int) ($r['id'] ?? 0),
            'jobwork_order_id' => (int) ($r['jobwork_order_id'] ?? 0),
            'jobwork_order_item_id' => (int) ($r['jobwork_order_item_id'] ?? 0),
            'stock_id' => (int) ($r['stock_id'] ?? 0),
            'barcode' => (string) ($r['barcode'] ?? ''),
            'product_name' => (string) ($r['product_name'] ?? ''),
            'diamond_category' => (string) ($r['diamond_category'] ?? ''),
            'weight' => (float) ($r['weight'] ?? $r['weight_out'] ?? 0),
            'qty' => (float) ($r['qty'] ?? $r['qty_out'] ?? 0),
            'weight_out' => (float) ($r['weight_out'] ?? $r['weight'] ?? 0),
            'qty_out' => (float) ($r['qty_out'] ?? $r['qty'] ?? 0),
            'from_dept_name' => (string) ($r['from_dept_name'] ?? ''),
            'to_dept_name' => (string) ($r['to_dept_name'] ?? ''),
            'from_user_name' => (string) ($r['from_user_name'] ?? ''),
            'to_user_name' => (string) ($r['to_user_name'] ?? ''),
            'added_by_dept_id' => (int) ($r['added_by_dept_id'] ?? 0),
            'added_by_user_id' => (int) ($r['added_by_user_id'] ?? 0),
            'added_by_dept_name' => (string) ($r['added_by_dept_name'] ?? ''),
            'added_by_user_name' => (string) ($r['added_by_user_name'] ?? ''),
            'created_at' => (string) ($r['created_at'] ?? ''),
            'row_source' => $rowSource,
        ];
    }
}
echo json_encode(['ok' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
