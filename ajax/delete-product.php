<?php
session_start();
require_once '../config.php';
require_once dirname(__DIR__) . '/includes/branch_product_delete_permission.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => false, 'message' => 'Invalid Request']);
    exit;
}

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    echo json_encode(['status' => false, 'message' => 'Session expired. Please login again.']);
    exit;
}

$product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;

if ($product_id <= 0) {
    echo json_encode(['status' => false, 'message' => 'Invalid product ID']);
    exit;
}

if (!$conn_master) {
    echo json_encode(['status' => false, 'message' => 'Delete permission denied for this branch']);
    exit;
}

auragold_ensure_branches_allow_product_delete_column($conn_master);

if (!auragold_product_delete_allowed_for_working_context()) {
    echo json_encode([
        'status'  => false,
        'message' => 'Delete permission denied for this branch',
    ]);
    exit;
}

$working_branch_id = auragold_resolve_working_branch_id_for_product_delete();
if ($working_branch_id <= 0) {
    echo json_encode([
        'status'  => false,
        'message' => 'Delete permission denied for this branch',
    ]);
    exit;
}

if (!auragold_product_linked_to_branch($conn, $product_id, $working_branch_id)) {
    echo json_encode([
        'status'  => false,
        'message' => 'Product does not belong to this branch.',
    ]);
    exit;
}

// Check if product is used in sale invoice, purchase invoice, repair invoice, etc.
$used_in = [];

$check_tables = [
    'tbl_sale_invoice_items'       => 'Sale Invoice',
    'tbl_purchase_invoice_items'   => 'Purchase Invoice',
    'tbl_repair_invoice_items'     => 'Repair Invoice',
    'tbl_credit_note_items'        => 'Credit Note',
    'tbl_debit_note_items'         => 'Debit Note',
    'tbl_receipt_voucher_items'    => 'Receipt Voucher',
    'tbl_sale_receipt_voucher_items' => 'Sale Receipt Voucher',
    'tbl_payment_voucher_items'    => 'Payment Voucher',
    'tbl_stock_journal'            => 'Stock Journal',
    'tbl_purchase_quotation_items' => 'Purchase Quotation',
    'tbl_sale_quotation_items'     => 'Sale Quotation',
    'tbl_purchase_return_items'    => 'Purchase Return',
    'tbl_sale_return_items'        => 'Sale Return',
    'tbl_advance_payment_items'    => 'Advance Payment',
];

foreach ($check_tables as $table => $label) {
    $exists = @mysqli_query($conn, "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = '$table' LIMIT 1");
    if (!$exists || mysqli_num_rows($exists) == 0) {
        continue;
    }
    $row = getRecord("SELECT 1 FROM $table WHERE product_id = $product_id LIMIT 1");
    if ($row) {
        $used_in[] = $label;
    }
}

if (!empty($used_in)) {
    echo json_encode([
        'status'  => false,
        'message' => 'Product already used. Cannot delete – used in: ' . implode(', ', $used_in),
    ]);
    exit;
}

mysqli_begin_transaction($conn);

try {
    $sql_stock = "DELETE FROM tbl_stock WHERE product_id = $product_id";
    mysqli_query($conn, $sql_stock);

    $sql_char = "UPDATE tbl_product_characteristics SET status = 0 WHERE product_id = $product_id";
    if (!mysqli_query($conn, $sql_char)) {
        throw new Exception('Product characteristics update failed: ' . mysqli_error($conn));
    }

    $sql_tax = "DELETE FROM tbl_product_tax WHERE product_id = $product_id";
    mysqli_query($conn, $sql_tax);

    $sql_branches = "DELETE FROM tbl_product_branches WHERE product_id = $product_id";
    mysqli_query($conn, $sql_branches);

    $sql = "UPDATE tbl_products SET status = 0, updated_at = NOW() WHERE id = $product_id";
    if (!mysqli_query($conn, $sql)) {
        throw new Exception('Product delete failed: ' . mysqli_error($conn));
    }

    mysqli_commit($conn);

    echo json_encode([
        'status'  => true,
        'message' => 'Product deleted successfully',
    ]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode([
        'status'  => false,
        'message' => $e->getMessage(),
    ]);
}
