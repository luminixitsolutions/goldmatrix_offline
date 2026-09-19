<?php
session_start();
require_once '../config.php';
if (is_file(__DIR__ . '/../includes/auragold_sale_order_jobwork_lock.php')) {
    require_once __DIR__ . '/../includes/auragold_sale_order_jobwork_lock.php';
}

header('Content-Type: application/json');

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

if ($order_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid order ID']);
    exit;
}

// Get order details from tbl_sale_orders
$order = getRecord("SELECT * FROM tbl_sale_orders WHERE id = $order_id");

if (!$order) {
    echo json_encode(['status' => 'error', 'message' => 'Order not found']);
    exit;
}

// Ensure order_no, order_date, order_id for form compatibility
$order['order_no'] = $order['order_no'] ?? '';
$order['order_date'] = $order['order_date'] ?? '';
$order['order_id'] = $order['id'] ?? 0;

// Get order items from tbl_sale_order_items
$items = getList("SELECT * FROM tbl_sale_order_items WHERE order_id = $order_id ORDER BY id ASC");

// Map item column names for compatibility with populateOrderForm / addProductRowToSelectionTable
foreach ($items as &$item) {
    $item['barcode_no'] = $item['barcode_no'] ?? $item['barcode'] ?? '';
    $item['product_characteristic_id'] = $item['product_characteristic_id'] ?? $item['characteristic_id'] ?? null;
    $item['tax_amount'] = $item['tax_amount'] ?? $item['tax'] ?? 0;
}

// Get order payments from tbl_sale_order_payments
$payments = getList("SELECT * FROM tbl_sale_order_payments WHERE order_id = $order_id ORDER BY id ASC");
require_once __DIR__ . '/../includes/auragold_payment_details_merge.php';
auragold_merge_payment_details_into_payments($payments);
require_once __DIR__ . '/../includes/auragold_metal_exchange_stock.php';
if (function_exists('auragold_sale_order_filter_display_payments')) {
    auragold_sale_order_filter_display_payments($conn, $order_id, $payments);
} elseif (function_exists('auragold_sale_order_filter_customer_payments')) {
    auragold_sale_order_filter_customer_payments($payments);
}

$diamond_issues = [];
$tDiamondIss = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_order_diamond_stock_issue'");
if ($tDiamondIss && mysqli_num_rows($tDiamondIss) > 0) {
    mysqli_free_result($tDiamondIss);
    $tmp_di = getList(
        'SELECT id, order_id, stock_id, barcode, product_name, diamond_category, weight, qty, created_at '
        . "FROM tbl_sale_order_diamond_stock_issue WHERE order_id = $order_id ORDER BY id ASC"
    );
    $diamond_issues = is_array($tmp_di) ? $tmp_di : [];
} elseif ($tDiamondIss) {
    mysqli_free_result($tDiamondIss);
}
$order['diamond_issues'] = $diamond_issues;

$stone_issues = [];
$tStoneIss = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_order_stone_stock_issue'");
if ($tStoneIss && mysqli_num_rows($tStoneIss) > 0) {
    mysqli_free_result($tStoneIss);
    $tmp_si = getList(
        'SELECT id, order_id, stock_id, barcode, product_name, stone_category, weight, qty, created_at '
        . "FROM tbl_sale_order_stone_stock_issue WHERE order_id = $order_id ORDER BY id ASC"
    );
    $stone_issues = is_array($tmp_si) ? $tmp_si : [];
} elseif ($tStoneIss) {
    mysqli_free_result($tStoneIss);
}
$order['stone_issues'] = $stone_issues;

$order['jobwork_order_blocks_save'] = false;
$order['jobwork_order_save_blocked_tip'] = '';
$order['linked_jobwork_orders'] = [];
if (function_exists('auragold_sale_order_has_linked_jobwork_order') && auragold_sale_order_has_linked_jobwork_order($conn, $order_id)) {
    $order['jobwork_order_blocks_save'] = true;
    if (function_exists('auragold_sale_order_jobwork_save_blocked_tip')) {
        $order['jobwork_order_save_blocked_tip'] = auragold_sale_order_jobwork_save_blocked_tip($conn, $order_id);
    }
    if (function_exists('auragold_sale_order_linked_jobwork_orders')) {
        $order['linked_jobwork_orders'] = auragold_sale_order_linked_jobwork_orders($conn, $order_id);
    }
}

echo json_encode([
    'status' => 'success',
    'order' => $order,
    'items' => $items,
    'payments' => $payments
]);
?>
