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

// Check if product is used on an *active* document. Soft-deleted purchase invoices
// (and their leftover items / stock-journal audit rows) must not block delete.
$used_in = [];

$tbl_exists = static function ($conn, $table): bool {
    $table = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $table);
    if ($table === '') {
        return false;
    }
    $r = @mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    $ok = ($r && mysqli_num_rows($r) > 0);
    if ($r) {
        mysqli_free_result($r);
    }
    return $ok;
};

$inactive_status_sql = static function ($conn, $table, $alias) {
    if (function_exists('auragold_doc_series_active_sql')) {
        return auragold_doc_series_active_sql($conn, $table, $alias);
    }
    $a = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $alias);
    $col = ($a !== '' ? $a . '.' : '') . 'status';
    return " AND (LOWER(TRIM(CAST(IFNULL($col, '') AS CHAR))) NOT IN ('deleted','cancelled','canceled','void'))";
};

$check_docs = [
    ['items' => 'tbl_sale_invoice_items', 'parent' => 'tbl_sale_invoices', 'fk' => 'invoice_id', 'label' => 'Sale Invoice'],
    ['items' => 'tbl_purchase_invoice_items', 'parent' => 'tbl_purchase_invoices', 'fk' => 'invoice_id', 'label' => 'Purchase Invoice'],
    ['items' => 'tbl_repair_invoice_items', 'parent' => 'tbl_repair_invoices', 'fk' => 'repair_invoice_id', 'label' => 'Repair Invoice'],
    ['items' => 'tbl_credit_note_items', 'parent' => 'tbl_credit_notes', 'fk' => 'credit_note_id', 'label' => 'Credit Note'],
    ['items' => 'tbl_debit_note_items', 'parent' => 'tbl_debit_notes', 'fk' => 'debit_note_id', 'label' => 'Debit Note'],
    ['items' => 'tbl_receipt_voucher_items', 'parent' => 'tbl_receipt_vouchers', 'fk' => 'voucher_id', 'label' => 'Receipt Voucher'],
    ['items' => 'tbl_sale_receipt_voucher_items', 'parent' => 'tbl_sale_receipt_vouchers', 'fk' => 'voucher_id', 'label' => 'Sale Receipt Voucher'],
    ['items' => 'tbl_payment_voucher_items', 'parent' => 'tbl_payment_vouchers', 'fk' => 'voucher_id', 'label' => 'Payment Voucher'],
    ['items' => 'tbl_purchase_quotation_items', 'parent' => 'tbl_purchase_quotations', 'fk' => 'quotation_id', 'label' => 'Purchase Quotation'],
    ['items' => 'tbl_sale_quotation_items', 'parent' => 'tbl_sale_quotations', 'fk' => 'quotation_id', 'label' => 'Sale Quotation'],
    ['items' => 'tbl_purchase_return_items', 'parent' => 'tbl_purchase_returns', 'fk' => 'return_id', 'label' => 'Purchase Return'],
    ['items' => 'tbl_sale_return_items', 'parent' => 'tbl_sale_returns', 'fk' => 'return_id', 'label' => 'Sale Return'],
    ['items' => 'tbl_advance_payment_items', 'parent' => 'tbl_advance_payments', 'fk' => 'voucher_id', 'label' => 'Advance Payment'],
];

foreach ($check_docs as $doc) {
    $items_table = $doc['items'];
    $parent_table = $doc['parent'];
    $fk = $doc['fk'];
    if (!$tbl_exists($conn, $items_table)) {
        continue;
    }
    $has_product = function_exists('auragold_tbl_has_column')
        ? auragold_tbl_has_column($conn, $items_table, 'product_id')
        : true;
    if (!$has_product) {
        continue;
    }
    if ($tbl_exists($conn, $parent_table)
        && (!function_exists('auragold_tbl_has_column') || auragold_tbl_has_column($conn, $items_table, $fk))) {
        $active = $inactive_status_sql($conn, $parent_table, 'p');
        $row = getRecord("
            SELECT 1
            FROM `$items_table` i
            INNER JOIN `$parent_table` p ON p.id = i.`$fk`
            WHERE i.product_id = $product_id
            $active
            LIMIT 1
        ");
    } else {
        $row = getRecord("SELECT 1 FROM `$items_table` WHERE product_id = $product_id LIMIT 1");
    }
    if ($row) {
        $used_in[] = $doc['label'];
    }
}

if ($tbl_exists($conn, 'tbl_stock_journal')) {
    $sj_active = '';
    if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_stock_journal', 'status')) {
        $sj_active = " AND (LOWER(TRIM(CAST(IFNULL(sj.status, '') AS CHAR))) NOT IN ('deleted','cancelled','canceled','void','0'))";
    }
    $sj_not_audit = '';
    if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_stock_journal', 'comment')) {
        $sj_not_audit = " AND (sj.comment IS NULL OR sj.comment NOT LIKE 'auragold_doc|%')";
    }
    $pi_join = '';
    $pi_active = '';
    if ($tbl_exists($conn, 'tbl_purchase_invoices') && $tbl_exists($conn, 'tbl_purchase_invoice_items')) {
        $sj_inv_expr = 'pii.invoice_id';
        if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_stock_journal', 'invoice_id')) {
            $sj_inv_expr = 'COALESCE(NULLIF(sj.invoice_id, 0), pii.invoice_id)';
        }
        $pi_join = " LEFT JOIN tbl_purchase_invoice_items pii ON pii.id = sj.item_id
                     LEFT JOIN tbl_purchase_invoices pi ON pi.id = $sj_inv_expr";
        // Standalone / product-opening rows have no PI parent. Deleted PI rows must not block delete.
        $pi_active = " AND (pi.id IS NULL OR LOWER(TRIM(CAST(IFNULL(pi.status, '') AS CHAR))) NOT IN ('deleted','cancelled','canceled','void'))";
    }
    $sj_row = getRecord("
        SELECT 1
        FROM tbl_stock_journal sj
        $pi_join
        WHERE sj.product_id = $product_id
        $sj_active
        $sj_not_audit
        $pi_active
        LIMIT 1
    ");
    if ($sj_row) {
        $used_in[] = 'Stock Journal';
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
