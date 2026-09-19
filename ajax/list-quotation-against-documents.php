<?php
/**
 * List source documents for Sales Quotation "Against Of" (per customer / supplier).
 * GET: type, customer_id (required), q (optional), exclude_quotation_id (optional, for Sale Quotation),
 *      exclude_invoice_id (optional; Sale Invoice list + Sale Order list: ignore this invoice when excluding “already used” orders).
 */
require_once __DIR__ . '/../includes/session_init.php';
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

try {
$type = isset($_GET['type']) ? trim((string) $_GET['type']) : '';
$customer_id = isset($_GET['customer_id']) ? (int) $_GET['customer_id'] : 0;
$search_term = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
$exclude_quotation_id = isset($_GET['exclude_quotation_id']) ? (int) $_GET['exclude_quotation_id'] : 0;
/** When picking "Against Of" on Sale Invoice, exclude the invoice being edited from the list. */
$exclude_invoice_id = isset($_GET['exclude_invoice_id']) ? (int) $_GET['exclude_invoice_id'] : 0;
$q_esc = $search_term !== '' ? mysqli_real_escape_string($conn, $search_term) : '';

if ($customer_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Please select a customer first', 'orders' => []]);
    exit;
}

$allowed = [
    'Consignment In', 'Consignment Out', 'Delivery Note', 'Repair Order', 'Sale Order',
    'Sale Invoice', 'Sale Quotation', 'Purchase Quotation', 'Purchase Order',
    'Old Jewellery Scrap Invoice',
    'Task / Event',
];
if (!in_array($type, $allowed, true)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid type', 'orders' => []]);
    exit;
}

$results = [];

function sq_quotation_format_rows(array $rows): array
{
    $out = [];
    foreach ($rows as $order) {
        $docDate = $order['doc_date'] ?? '';
        $dueDate = $order['due_date'] ?? '';
        $out[] = [
            'id' => (int) ($order['id'] ?? 0),
            'order_no' => $order['doc_no'] ?? '',
            'customer_name' => $order['customer_name'] ?? '',
            'customer_id' => (int) ($order['customer_id'] ?? 0),
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
    return $out;
}

if ($type === 'Task / Event') {
    $results = [];
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
        $results = sq_quotation_format_rows(getList($sql) ?: []);
    } elseif ($t) {
        mysqli_free_result($t);
    }
    echo json_encode([
        'status' => 'success',
        'orders' => $results,
    ]);
    exit;
}

$cid = $customer_id;

if ($type === 'Consignment In') {
    $t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_consignment_in'");
    if ($t && mysqli_num_rows($t) > 0) {
        mysqli_free_result($t);
        $where = "o.customer_id = $cid AND (o.status IS NULL OR o.status = '' OR LOWER(TRIM(o.status)) NOT IN ('deleted','cancelled'))";
        if ($q_esc !== '') {
            $where .= " AND o.consignment_no LIKE '%$q_esc%'";
        }
        $sql = "
            SELECT o.id, o.consignment_no AS doc_no, o.customer_name, o.customer_id,
                o.consignment_date AS doc_date, NULL AS due_date, COALESCE(o.grand_total,0) AS grand_total,
                0 AS paid_amt, o.currency,
                c.mobile_no AS contact_no,
                (SELECT ci.product_name FROM tbl_consignment_in_items ci WHERE ci.consignment_id = o.id ORDER BY ci.id LIMIT 1) AS first_item,
                (SELECT COALESCE(SUM(ci2.gross_weight),0) FROM tbl_consignment_in_items ci2 WHERE ci2.consignment_id = o.id) AS gross_wt
            FROM tbl_consignment_in o
            LEFT JOIN tbl_customers c ON c.id = o.customer_id AND c.status = 1
            WHERE $where
            ORDER BY o.id DESC LIMIT 50
        ";
        $results = sq_quotation_format_rows(getList($sql) ?: []);
    } elseif ($t) {
        mysqli_free_result($t);
    }
} elseif ($type === 'Consignment Out') {
    $t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_consignment_out'");
    if ($t && mysqli_num_rows($t) > 0) {
        mysqli_free_result($t);
        $where = "o.customer_id = $cid AND (o.status IS NULL OR o.status = '' OR LOWER(TRIM(o.status)) NOT IN ('deleted','cancelled'))";
        if ($q_esc !== '') {
            $where .= " AND o.consignment_no LIKE '%$q_esc%'";
        }
        $sql = "
            SELECT o.id, o.consignment_no AS doc_no, o.customer_name, o.customer_id,
                o.consignment_date AS doc_date, NULL AS due_date, COALESCE(o.grand_total,0) AS grand_total,
                0 AS paid_amt, o.currency,
                c.mobile_no AS contact_no,
                (SELECT ci.product_name FROM tbl_consignment_out_items ci WHERE ci.consignment_id = o.id ORDER BY ci.id LIMIT 1) AS first_item,
                (SELECT COALESCE(SUM(ci2.gross_weight),0) FROM tbl_consignment_out_items ci2 WHERE ci2.consignment_id = o.id) AS gross_wt
            FROM tbl_consignment_out o
            LEFT JOIN tbl_customers c ON c.id = o.customer_id AND c.status = 1
            WHERE $where
            ORDER BY o.id DESC LIMIT 50
        ";
        $results = sq_quotation_format_rows(getList($sql) ?: []);
    } elseif ($t) {
        mysqli_free_result($t);
    }
} elseif ($type === 'Delivery Note') {
    $t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_delivery_notes'");
    if ($t && mysqli_num_rows($t) > 0) {
        mysqli_free_result($t);
        $where = "o.customer_id = $cid AND (o.status IS NULL OR o.status = '' OR LOWER(TRIM(o.status)) NOT IN ('deleted','cancelled'))";
        if ($q_esc !== '') {
            $where .= " AND o.delivery_no LIKE '%$q_esc%'";
        }
        $sql = "
            SELECT o.id, o.delivery_no AS doc_no, o.customer_name, o.customer_id,
                o.delivery_date AS doc_date, NULL AS due_date, COALESCE(o.grand_total,0) AS grand_total,
                0 AS paid_amt, o.currency,
                c.mobile_no AS contact_no,
                (SELECT dni.product_name FROM tbl_delivery_note_items dni WHERE dni.delivery_note_id = o.id ORDER BY dni.id LIMIT 1) AS first_item,
                (SELECT COALESCE(SUM(dni2.gross_weight),0) FROM tbl_delivery_note_items dni2 WHERE dni2.delivery_note_id = o.id) AS gross_wt
            FROM tbl_delivery_notes o
            LEFT JOIN tbl_customers c ON c.id = o.customer_id AND c.status = 1
            WHERE $where
            ORDER BY o.id DESC LIMIT 50
        ";
        $results = sq_quotation_format_rows(getList($sql) ?: []);
    } elseif ($t) {
        mysqli_free_result($t);
    }
} elseif ($type === 'Repair Order') {
    $where = "o.customer_id = $cid AND (o.status IS NULL OR o.status = '' OR LOWER(TRIM(o.status)) NOT IN ('deleted','cancelled'))";
    if ($q_esc !== '') {
        $where .= " AND o.order_no LIKE '%$q_esc%'";
    }
    $sql = "
        SELECT o.id, o.order_no AS doc_no, o.customer_name, o.customer_id,
            o.order_date AS doc_date, NULL AS due_date, COALESCE(o.grand_total,0) AS grand_total,
            COALESCE(o.paid_amt,0) AS paid_amt, o.currency,
            c.mobile_no AS contact_no,
            (SELECT ri.product_name FROM tbl_repair_order_items ri WHERE ri.order_id = o.id ORDER BY ri.id LIMIT 1) AS first_item,
            (SELECT COALESCE(SUM(ri2.gross_weight),0) FROM tbl_repair_order_items ri2 WHERE ri2.order_id = o.id) AS gross_wt
        FROM tbl_repair_orders o
        LEFT JOIN tbl_customers c ON c.id = o.customer_id AND c.status = 1
        WHERE $where
        ORDER BY o.id DESC LIMIT 50
    ";
    $results = sq_quotation_format_rows(getList($sql) ?: []);
} elseif ($type === 'Sale Order') {
    if (function_exists('auragold_ensure_sale_invoice_item_source_so_id')) {
        auragold_ensure_sale_invoice_item_source_so_id($conn);
    }
    if (function_exists('auragold_ensure_sale_invoice_against_id')) {
        auragold_ensure_sale_invoice_against_id($conn);
    }
    $where = "o.customer_id = $cid AND (o.status IS NULL OR o.status = '' OR LOWER(TRIM(o.status)) NOT IN ('deleted','cancelled'))";
    if ($q_esc !== '') {
        $where .= " AND o.order_no LIKE '%$q_esc%'";
    }
    $sql = "
        SELECT o.id, o.order_no AS doc_no, o.customer_name, o.customer_id,
            o.order_date AS doc_date, o.due_date, COALESCE(o.grand_total,0) AS grand_total,
            COALESCE(o.paid_amt,0) AS paid_amt, o.currency,
            c.mobile_no AS contact_no,
            (SELECT soi.product_name FROM tbl_sale_order_items soi WHERE soi.order_id = o.id ORDER BY soi.id LIMIT 1) AS first_item,
            (SELECT COALESCE(SUM(soi2.gross_weight),0) FROM tbl_sale_order_items soi2 WHERE soi2.order_id = o.id) AS gross_wt
        FROM tbl_sale_orders o
        LEFT JOIN tbl_customers c ON c.id = o.customer_id AND c.status = 1
        WHERE $where
        ORDER BY o.id DESC LIMIT 50
    ";
    $raw_rows = getList($sql) ?: [];
    if (function_exists('auragold_sale_order_has_pending_invoice_items') && !empty($raw_rows)) {
        $raw_rows = array_values(array_filter($raw_rows, function ($row) use ($conn, $exclude_invoice_id) {
            return auragold_sale_order_has_pending_invoice_items($conn, (int) ($row['id'] ?? 0), $exclude_invoice_id);
        }));
    }
    $results = sq_quotation_format_rows($raw_rows);
} elseif ($type === 'Sale Invoice') {
    $where = "o.customer_id = $cid AND (o.status IS NULL OR o.status = '' OR LOWER(TRIM(o.status)) NOT IN ('deleted','cancelled'))";
    if ($exclude_invoice_id > 0) {
        $where .= ' AND o.id != ' . (int) $exclude_invoice_id;
    }
    if ($q_esc !== '') {
        $where .= " AND o.invoice_no LIKE '%$q_esc%'";
    }
    if (function_exists('auragold_sale_invoices_branch_where_sql')) {
        $where .= auragold_sale_invoices_branch_where_sql($conn, 'o');
    }
    $sql = "
        SELECT o.id, o.invoice_no AS doc_no, o.customer_name, o.customer_id,
            o.invoice_date AS doc_date, o.due_date, COALESCE(o.grand_total,0) AS grand_total,
            COALESCE(o.paid_amt,0) AS paid_amt, o.currency,
            c.mobile_no AS contact_no,
            (SELECT si.product_name FROM tbl_sale_invoice_items si WHERE si.invoice_id = o.id ORDER BY si.id LIMIT 1) AS first_item,
            (SELECT COALESCE(SUM(si2.gross_weight),0) FROM tbl_sale_invoice_items si2 WHERE si2.invoice_id = o.id) AS gross_wt
        FROM tbl_sale_invoices o
        LEFT JOIN tbl_customers c ON c.id = o.customer_id AND c.status = 1
        WHERE $where
        ORDER BY o.id DESC LIMIT 50
    ";
    $results = sq_quotation_format_rows(getList($sql) ?: []);
} elseif ($type === 'Sale Quotation') {
    $where = "o.customer_id = $cid AND (o.status IS NULL OR o.status = '' OR LOWER(TRIM(o.status)) NOT IN ('deleted','cancelled'))";
    if ($exclude_quotation_id > 0) {
        $where .= " AND o.id != " . (int) $exclude_quotation_id;
    }
    if ($q_esc !== '') {
        $where .= " AND o.quotation_no LIKE '%$q_esc%'";
    }
    if (function_exists('auragold_sale_quotations_branch_where_sql')) {
        $where .= auragold_sale_quotations_branch_where_sql($conn, 'o');
    }
    $sql = "
        SELECT o.id, o.quotation_no AS doc_no, o.customer_name, o.customer_id,
            o.quotation_date AS doc_date, o.due_date, COALESCE(o.grand_total,0) AS grand_total,
            COALESCE(o.paid_amt,0) AS paid_amt, o.currency,
            c.mobile_no AS contact_no,
            (SELECT sqi.product_name FROM tbl_sale_quotation_items sqi WHERE sqi.quotation_id = o.id ORDER BY sqi.id LIMIT 1) AS first_item,
            (SELECT COALESCE(SUM(sqi2.gross_weight),0) FROM tbl_sale_quotation_items sqi2 WHERE sqi2.quotation_id = o.id) AS gross_wt
        FROM tbl_sale_quotations o
        LEFT JOIN tbl_customers c ON c.id = o.customer_id AND c.status = 1
        WHERE $where
        ORDER BY o.id DESC LIMIT 50
    ";
    $results = sq_quotation_format_rows(getList($sql) ?: []);
} elseif ($type === 'Purchase Quotation') {
    $where = "o.supplier_id = $cid AND (o.status IS NULL OR o.status = '' OR LOWER(TRIM(o.status)) NOT IN ('deleted','cancelled'))";
    if ($exclude_quotation_id > 0) {
        $where .= ' AND o.id != ' . (int) $exclude_quotation_id;
    }
    if ($q_esc !== '') {
        $where .= " AND o.quotation_no LIKE '%$q_esc%'";
    }
    if (function_exists('auragold_sql_and_branch_scope')) {
        $where .= auragold_sql_and_branch_scope($conn, 'tbl_purchase_quotations', 'o');
    }
    $sql = "
        SELECT o.id, o.quotation_no AS doc_no, o.supplier_name AS customer_name, o.supplier_id AS customer_id,
            o.quotation_date AS doc_date, NULL AS due_date, COALESCE(o.grand_total,0) AS grand_total,
            0 AS paid_amt, o.currency,
            c.mobile_no AS contact_no,
            (SELECT pqi.product_name FROM tbl_purchase_quotation_items pqi WHERE pqi.quotation_id = o.id ORDER BY pqi.id LIMIT 1) AS first_item,
            (SELECT COALESCE(SUM(pqi2.gross_weight),0) FROM tbl_purchase_quotation_items pqi2 WHERE pqi2.quotation_id = o.id) AS gross_wt
        FROM tbl_purchase_quotations o
        LEFT JOIN tbl_customers c ON c.id = o.supplier_id AND c.status = 1
        WHERE $where
        ORDER BY o.id DESC LIMIT 50
    ";
    $results = sq_quotation_format_rows(getList($sql) ?: []);
} elseif ($type === 'Purchase Order') {
    $t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_purchase_orders'");
    if ($t && mysqli_num_rows($t) > 0) {
        mysqli_free_result($t);
        // tbl_purchase_orders uses customer_id / customer_name (supplier party), not supplier_id
        $where = "o.customer_id = $cid AND (o.status IS NULL OR o.status = '' OR LOWER(TRIM(o.status)) NOT IN ('deleted','cancelled'))";
        if ($q_esc !== '') {
            $where .= " AND o.order_no LIKE '%$q_esc%'";
        }
        if (function_exists('auragold_sql_and_branch_scope')) {
            $where .= auragold_sql_and_branch_scope($conn, 'tbl_purchase_orders', 'o');
        }
        $sql = "
            SELECT o.id, o.order_no AS doc_no, o.customer_name AS customer_name, o.customer_id AS customer_id,
                o.order_date AS doc_date, NULL AS due_date, COALESCE(o.grand_total,0) AS grand_total,
                COALESCE(o.paid_amt,0) AS paid_amt, o.currency,
                c.mobile_no AS contact_no,
                (SELECT poi.product_name FROM tbl_purchase_order_items poi WHERE poi.order_id = o.id ORDER BY poi.id LIMIT 1) AS first_item,
                (SELECT COALESCE(SUM(poi2.gross_weight),0) FROM tbl_purchase_order_items poi2 WHERE poi2.order_id = o.id) AS gross_wt
            FROM tbl_purchase_orders o
            LEFT JOIN tbl_customers c ON c.id = o.customer_id AND c.status = 1
            WHERE $where
            ORDER BY o.id DESC LIMIT 50
        ";
        $results = sq_quotation_format_rows(getList($sql) ?: []);
    } elseif ($t) {
        mysqli_free_result($t);
    }
} elseif ($type === 'Old Jewellery Scrap Invoice') {
    $t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_old_jewelry_scrap_invoices'");
    if ($t && mysqli_num_rows($t) > 0) {
        mysqli_free_result($t);
        $where = "o.customer_id = $cid AND (o.status IS NULL OR o.status = '' OR LOWER(TRIM(o.status)) NOT IN ('deleted','cancelled'))";
        if ($exclude_invoice_id > 0) {
            $where .= ' AND o.id != ' . (int) $exclude_invoice_id;
        }
        if ($q_esc !== '') {
            $where .= " AND o.invoice_no LIKE '%$q_esc%'";
        }
        $sql = "
            SELECT o.id, o.invoice_no AS doc_no, o.customer_name, o.customer_id,
                o.invoice_date AS doc_date, o.due_date, COALESCE(o.grand_total,0) AS grand_total,
                COALESCE(o.paid_amt,0) AS paid_amt, o.currency,
                c.mobile_no AS contact_no,
                (SELECT sii.description FROM tbl_old_jewelry_scrap_invoice_items sii WHERE sii.invoice_id = o.id AND (sii.status IS NULL OR sii.status = 1) ORDER BY sii.id LIMIT 1) AS first_item,
                (SELECT COALESCE(SUM(sii2.gross_wt),0) FROM tbl_old_jewelry_scrap_invoice_items sii2 WHERE sii2.invoice_id = o.id AND (sii2.status IS NULL OR sii2.status = 1)) AS gross_wt
            FROM tbl_old_jewelry_scrap_invoices o
            LEFT JOIN tbl_customers c ON c.id = o.customer_id AND c.status = 1
            WHERE $where
            ORDER BY o.id DESC LIMIT 50
        ";
        $results = sq_quotation_format_rows(getList($sql) ?: []);
    } elseif ($t) {
        mysqli_free_result($t);
    }
}

echo json_encode([
    'status' => 'success',
    'orders' => $results,
]);
} catch (Throwable $e) {
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage(),
        'orders'  => [],
    ]);
}
