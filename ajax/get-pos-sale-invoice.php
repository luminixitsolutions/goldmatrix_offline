<?php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../includes/auragold_branch_data_scope.php';
require_once __DIR__ . '/../includes/auragold-gst.php';

header('Content-Type: application/json');

$invoice_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($invoice_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid invoice ID']);
    exit;
}

// Get invoice details from tbl_pos_sale_invoices
$invoice = getRecord("SELECT * FROM tbl_pos_sale_invoices WHERE id = $invoice_id");

if (!$invoice) {
    echo json_encode(['status' => 'error', 'message' => 'Invoice not found']);
    exit;
}

if (!auragold_branch_can_access_sale_invoice_row($invoice)) {
    echo json_encode(['status' => 'error', 'message' => 'You do not have access to this invoice (another branch).']);
    exit;
}

// Map invoice fields to order fields for compatibility with JavaScript
$order = $invoice;
$customer_billing_state = '';
$cid = (int) ($invoice['customer_id'] ?? 0);
if ($cid > 0) {
    $cr = getRecord("SELECT billing_state FROM tbl_customers WHERE id = $cid LIMIT 1");
    if ($cr && isset($cr['billing_state'])) {
        $customer_billing_state = trim((string) $cr['billing_state']);
    }
}
$order['customer_billing_state'] = $customer_billing_state;
$order['order_no'] = $invoice['invoice_no'];
$order['order_date'] = $invoice['invoice_date'];
$order['order_id'] = $invoice['id'];
$order['purchase_fixing_blocks_save'] = false;
if (!empty($invoice['invoice_no']) && function_exists('auragold_si_has_active_purchase_fixing')) {
    $order['purchase_fixing_blocks_save'] = auragold_si_has_active_purchase_fixing(trim((string) $invoice['invoice_no']));
}

// Get invoice items
$items = getList("SELECT * FROM tbl_pos_sale_invoice_items WHERE invoice_id = $invoice_id ORDER BY id ASC");
$gst_tax_branch = (int) ($invoice['branch_id'] ?? 0);
if ($gst_tax_branch <= 0) {
    if (!empty($_SESSION['working_branch_id'])) {
        $gst_tax_branch = (int) $_SESSION['working_branch_id'];
    } elseif (!empty($_SESSION['branch_id'])) {
        $gst_tax_branch = (int) $_SESSION['branch_id'];
    }
}
$gst_tax_scope_id = $gst_tax_branch > 0 ? $gst_tax_branch : 0;

if (is_array($items)) {
    foreach ($items as &$it) {
        $pid = (int) ($it['product_id'] ?? 0);
        if ($pid > 0) {
            $g = auragold_product_gst_percent_by_supply_scope($conn, $pid, $gst_tax_scope_id);
            $it['gst_local_percent'] = $g['local'];
            $it['gst_interstate_percent'] = $g['interstate'];
            $it['gst_invoice_slab_percent'] = auragold_product_gst_invoice_slab_percent_from_scopes($g);
        }
    }
    unset($it);
}

// Get invoice payments
$payments = getList("SELECT * FROM tbl_pos_sale_invoice_payments WHERE invoice_id = $invoice_id ORDER BY id ASC");
require_once __DIR__ . '/../includes/auragold_payment_details_merge.php';
auragold_merge_payment_details_into_payments($payments);

$fixing_mapping = null;
$mf = @mysqli_query($conn, "SHOW TABLES LIKE 'invoice_fixing_mapping'");
if ($mf && mysqli_num_rows($mf) > 0) {
    mysqli_free_result($mf);
    $fixing_mapping = getRecord("SELECT * FROM invoice_fixing_mapping WHERE source_type = 'pos_sale_invoice' AND source_transaction_id = $invoice_id AND status = 1 ORDER BY id DESC LIMIT 1");
} elseif ($mf) {
    mysqli_free_result($mf);
}

echo json_encode([
    'status' => 'success',
    'order' => $order,
    'items' => $items,
    'payments' => $payments,
    'fixing_mapping' => $fixing_mapping
]);
