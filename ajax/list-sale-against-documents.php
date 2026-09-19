<?php
/**
 * List Sale Invoices, Sale Quotations, or Task / Event records (Against Of modal on Sale Return / Sale Order).
 * GET: type = Sale Invoice | Sale Quotation | Task / Event, customer_id (required), q (optional filter)
 */
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$type = isset($_GET['type']) ? trim((string)$_GET['type']) : '';
$customer_id = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$search_term = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$q_esc = $search_term !== '' ? mysqli_real_escape_string($conn, $search_term) : '';
$exclude_return_id = isset($_GET['exclude_return_id']) ? (int)$_GET['exclude_return_id'] : 0;

if (function_exists('auragold_ensure_sale_return_item_source_against_id')) {
    auragold_ensure_sale_return_item_source_against_id($conn);
}

if ($customer_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Please select a customer first', 'orders' => []]);
    exit;
}

if ($type !== 'Sale Invoice' && $type !== 'Sale Quotation' && $type !== 'Task / Event') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid type', 'orders' => []]);
    exit;
}

$results = [];

if ($type === 'Task / Event') {
    $t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_tasks'");
    if ($t && mysqli_num_rows($t) > 0) {
        mysqli_free_result($t);
        $where = '1=1';
        if ($q_esc !== '') {
            $where .= " AND (t.title LIKE '%$q_esc%' OR IFNULL(t.description,'') LIKE '%$q_esc%')";
        }
        $sql = "
            SELECT t.id,
                IFNULL(NULLIF(TRIM(t.title), ''), CONCAT('Task #', t.id)) AS doc_no,
                IFNULL(c.name, '') AS customer_name,
                $customer_id AS customer_id,
                t.created_at AS doc_date,
                NULL AS due_date,
                0 AS grand_total,
                0 AS paid_amt,
                'AED' AS currency,
                IFNULL(c.mobile_no, '') AS contact_no,
                IFNULL(NULLIF(TRIM(t.description), ''), t.title) AS first_item,
                0 AS gross_wt
            FROM tbl_tasks t
            LEFT JOIN tbl_customers c ON c.id = $customer_id AND c.status = 1
            WHERE $where
            ORDER BY t.id DESC
            LIMIT 50
        ";
        $rows = getList($sql) ?: [];
        foreach ($rows as $order) {
            $docDate = $order['doc_date'] ?? '';
            $dueDate = $order['due_date'] ?? '';
            $results[] = [
                'id' => (int)($order['id'] ?? 0),
                'order_no' => $order['doc_no'] ?? '',
                'customer_name' => $order['customer_name'] ?? '',
                'customer_id' => (int)($order['customer_id'] ?? 0),
                'order_date' => $docDate,
                'due_date' => $dueDate,
                'grand_total' => $order['grand_total'] ?? 0,
                'paid_amt' => $order['paid_amt'] ?? 0,
                'contact_no' => $order['contact_no'] ?? '',
                'first_item' => $order['first_item'] ?? '',
                'gross_wt' => $order['gross_wt'] ?? 0,
                'currency' => $order['currency'] ?? 'AED',
                'formatted_date' => !empty($docDate) ? date('d-m-Y', strtotime($docDate)) : '',
                'formatted_due_date' => !empty($dueDate) ? date('d-m-Y', strtotime($dueDate)) : '',
            ];
        }
    } elseif ($t) {
        mysqli_free_result($t);
    }
} elseif ($type === 'Sale Invoice') {
    $pend_si = function_exists('auragold_sale_return_pending_invoice_item_predicate_sql')
        ? auragold_sale_return_pending_invoice_item_predicate_sql($exclude_return_id, 'si')
        : '1=1';
    $where = "o.customer_id = $customer_id AND (o.status IS NULL OR o.status = '' OR LOWER(TRIM(o.status)) NOT IN ('deleted','cancelled'))";
    if ($q_esc !== '') {
        $where .= " AND o.invoice_no LIKE '%$q_esc%'";
    }
    $where .= " AND EXISTS (
        SELECT 1 FROM tbl_sale_invoice_items si
        WHERE si.invoice_id = o.id AND ($pend_si)
    )";
    if (function_exists('auragold_sale_invoices_branch_where_sql')) {
        $where .= auragold_sale_invoices_branch_where_sql($conn, 'o');
    }
    $fi_pred = function_exists('auragold_sale_return_pending_invoice_item_predicate_sql')
        ? auragold_sale_return_pending_invoice_item_predicate_sql($exclude_return_id, 'si1')
        : '1=1';
    $gw_pred = function_exists('auragold_sale_return_pending_invoice_item_predicate_sql')
        ? auragold_sale_return_pending_invoice_item_predicate_sql($exclude_return_id, 'si2')
        : '1=1';
    $sql = "
        SELECT 
            o.id,
            o.invoice_no AS doc_no,
            o.customer_name,
            o.customer_id,
            o.invoice_date AS doc_date,
            o.due_date,
            o.grand_total,
            o.paid_amt,
            o.currency,
            c.mobile_no AS contact_no,
            (SELECT si1.product_name FROM tbl_sale_invoice_items si1 WHERE si1.invoice_id = o.id AND ($fi_pred) ORDER BY si1.id LIMIT 1) AS first_item,
            (SELECT COALESCE(SUM(si2.gross_weight), 0) FROM tbl_sale_invoice_items si2 WHERE si2.invoice_id = o.id AND ($gw_pred)) AS gross_wt
        FROM tbl_sale_invoices o
        LEFT JOIN tbl_customers c ON c.id = o.customer_id AND c.status = 1
        WHERE $where
        ORDER BY o.id DESC
        LIMIT 50
    ";
    $rows = getList($sql);
    foreach ($rows as $order) {
        $docDate = $order['doc_date'] ?? '';
        $dueDate = $order['due_date'] ?? '';
        $results[] = [
            'id' => (int)($order['id'] ?? 0),
            'order_no' => $order['doc_no'] ?? '',
            'customer_name' => $order['customer_name'] ?? '',
            'customer_id' => (int)($order['customer_id'] ?? 0),
            'order_date' => $docDate,
            'due_date' => $dueDate,
            'grand_total' => $order['grand_total'] ?? 0,
            'paid_amt' => $order['paid_amt'] ?? 0,
            'contact_no' => $order['contact_no'] ?? '',
            'first_item' => $order['first_item'] ?? '',
            'gross_wt' => $order['gross_wt'] ?? 0,
            'currency' => $order['currency'] ?? 'AED',
            'formatted_date' => !empty($docDate) ? date('d-m-Y', strtotime($docDate)) : '',
            'formatted_due_date' => !empty($dueDate) ? date('d-m-Y', strtotime($dueDate)) : '',
        ];
    }
} else {
    $pend_sqi = function_exists('auragold_sale_return_pending_quotation_item_predicate_sql')
        ? auragold_sale_return_pending_quotation_item_predicate_sql($exclude_return_id, 'sqi')
        : '1=1';
    $where = "o.customer_id = $customer_id AND (o.status IS NULL OR o.status = '' OR LOWER(TRIM(o.status)) NOT IN ('deleted','cancelled'))";
    if ($q_esc !== '') {
        $where .= " AND o.quotation_no LIKE '%$q_esc%'";
    }
    $where .= " AND EXISTS (
        SELECT 1 FROM tbl_sale_quotation_items sqi
        WHERE sqi.quotation_id = o.id AND ($pend_sqi)
    )";
    if (function_exists('auragold_sale_quotations_branch_where_sql')) {
        $where .= auragold_sale_quotations_branch_where_sql($conn, 'o');
    }
    $fiq_pred = function_exists('auragold_sale_return_pending_quotation_item_predicate_sql')
        ? auragold_sale_return_pending_quotation_item_predicate_sql($exclude_return_id, 'sqi1')
        : '1=1';
    $gwq_pred = function_exists('auragold_sale_return_pending_quotation_item_predicate_sql')
        ? auragold_sale_return_pending_quotation_item_predicate_sql($exclude_return_id, 'sqi2')
        : '1=1';
    $sql = "
        SELECT 
            o.id,
            o.quotation_no AS doc_no,
            o.customer_name,
            o.customer_id,
            o.quotation_date AS doc_date,
            o.due_date,
            o.grand_total,
            o.paid_amt,
            o.currency,
            c.mobile_no AS contact_no,
            (SELECT sqi1.product_name FROM tbl_sale_quotation_items sqi1 WHERE sqi1.quotation_id = o.id AND ($fiq_pred) ORDER BY sqi1.id LIMIT 1) AS first_item,
            (SELECT COALESCE(SUM(sqi2.gross_weight), 0) FROM tbl_sale_quotation_items sqi2 WHERE sqi2.quotation_id = o.id AND ($gwq_pred)) AS gross_wt
        FROM tbl_sale_quotations o
        LEFT JOIN tbl_customers c ON c.id = o.customer_id AND c.status = 1
        WHERE $where
        ORDER BY o.id DESC
        LIMIT 50
    ";
    $rows = getList($sql);
    foreach ($rows as $order) {
        $docDate = $order['doc_date'] ?? '';
        $dueDate = $order['due_date'] ?? '';
        $results[] = [
            'id' => (int)($order['id'] ?? 0),
            'order_no' => $order['doc_no'] ?? '',
            'customer_name' => $order['customer_name'] ?? '',
            'customer_id' => (int)($order['customer_id'] ?? 0),
            'order_date' => $docDate,
            'due_date' => $dueDate,
            'grand_total' => $order['grand_total'] ?? 0,
            'paid_amt' => $order['paid_amt'] ?? 0,
            'contact_no' => $order['contact_no'] ?? '',
            'first_item' => $order['first_item'] ?? '',
            'gross_wt' => $order['gross_wt'] ?? 0,
            'currency' => $order['currency'] ?? 'AED',
            'formatted_date' => !empty($docDate) ? date('d-m-Y', strtotime($docDate)) : '',
            'formatted_due_date' => !empty($dueDate) ? date('d-m-Y', strtotime($dueDate)) : '',
        ];
    }
}

echo json_encode([
    'status' => 'success',
    'orders' => $results,
]);
