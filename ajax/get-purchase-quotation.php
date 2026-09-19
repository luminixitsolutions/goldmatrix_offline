<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$quotation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
// When loading the purchase quotation screen for edit, return all saved lines (do not drop fully-converted rows).
$for_edit = isset($_GET['for_edit']) && (string) $_GET['for_edit'] === '1';

if ($quotation_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid quotation ID']);
    exit;
}

// Get quotation details
$quotation = getRecord("SELECT * FROM tbl_purchase_quotations WHERE id = $quotation_id");

if (!$quotation) {
    echo json_encode(['status' => 'error', 'message' => 'Quotation not found']);
    exit;
}

try {
    auragold_branch_require_document_access($conn, 'tbl_purchase_quotations', $quotation_id);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}

// Check if quotation items table exists
$items_table_exists = false;
$items_check = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_purchase_quotation_items'");
if ($items_check && mysqli_num_rows($items_check) > 0) {
    $items_table_exists = true;
}
if ($items_check) {
    mysqli_free_result($items_check);
}

// Check if quotation payments table exists
$payments_table_exists = false;
$payments_check = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_purchase_quotation_payments'");
if ($payments_check && mysqli_num_rows($payments_check) > 0) {
    $payments_table_exists = true;
}
if ($payments_check) {
    mysqli_free_result($payments_check);
}

// Get quotation items
$items = [];
if ($items_table_exists) {
    $items = getList("SELECT * FROM tbl_purchase_quotation_items WHERE quotation_id = $quotation_id ORDER BY id ASC");
    $has_pending_col = false;
    $chk = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_quotation_items LIKE 'pending_qty'");
    if ($chk && mysqli_num_rows($chk) > 0) {
        $has_pending_col = true;
    }
    if ($chk) {
        mysqli_free_result($chk);
    }
    foreach ($items as &$it) {
        $q = (float)($it['quantity'] ?? 0);
        $ret = (float)($it['returned_qty'] ?? 0);
        if ($has_pending_col && isset($it['pending_qty']) && $it['pending_qty'] !== null && $it['pending_qty'] !== '') {
            $it['pending_qty'] = (float)$it['pending_qty'];
        } else {
            $it['pending_qty'] = max(0, $q - $ret);
        }
        $it['returnable_qty'] = (float)$it['pending_qty'];
    }
    unset($it);
    if (!$for_edit) {
        $items = array_values(array_filter($items, function ($row) {
            $p = (float)($row['pending_qty'] ?? 0);
            return $p > 0.000001;
        }));
    }
}

// Get quotation payments
$payments = [];
if ($payments_table_exists) {
    $payments = getList("SELECT * FROM tbl_purchase_quotation_payments WHERE quotation_id = $quotation_id ORDER BY id ASC");
    require_once __DIR__ . '/../includes/auragold_payment_details_merge.php';
    auragold_merge_payment_details_into_payments($payments);
}

// Map quotation to "order" for populateOrderForm (order_no, customer_name for supplier, etc.)
$order = $quotation;
$order['order_no'] = $quotation['quotation_no'] ?? '';
$order['quotation_no'] = $quotation['quotation_no'] ?? '';
$order['order_date'] = $quotation['quotation_date'] ?? '';
$order['order_id'] = $quotation['id'];
$order['against_type'] = $quotation['against_type'] ?? '';
$order['against_id'] = isset($quotation['against_id']) ? (int)$quotation['against_id'] : 0;
$order['customer_name'] = $quotation['supplier_name'] ?? $quotation['customer_name'] ?? '';
$order['customer_id'] = isset($quotation['supplier_id']) ? (int)$quotation['supplier_id'] : (int)($quotation['customer_id'] ?? 0);
$order['sales_person'] = $quotation['purchase_person'] ?? $quotation['sales_person'] ?? '';
$order['invoice_no'] = $quotation['quotation_no'] ?? '';
$order['invoice_date'] = $quotation['quotation_date'] ?? '';

echo json_encode([
    'status' => 'success',
    'quotation' => $quotation,
    'order' => $order,
    'items' => $items,
    'payments' => $payments
]);
?>
