<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

// Get filters
$date_range = isset($_GET['date_range']) ? esc($_GET['date_range']) : '';
$branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;
$ledger_name = isset($_GET['ledger_name']) ? esc($_GET['ledger_name']) : '';
$purchase_person = isset($_GET['purchase_person']) ? esc($_GET['purchase_person']) : '';
$voucher_type = isset($_GET['voucher_type']) ? esc($_GET['voucher_type']) : '';
$metal_type = isset($_GET['metal_type']) ? esc($_GET['metal_type']) : '';
$product_id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$category_id = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$carat_id = isset($_GET['carat_id']) ? (int)$_GET['carat_id'] : 0;
$currency = isset($_GET['currency']) ? esc($_GET['currency']) : '';
$above_amount = isset($_GET['above_amount']) ? (float)$_GET['above_amount'] : 0;
$barcode_no = isset($_GET['barcode_no']) ? esc($_GET['barcode_no']) : '';
$invoice_no = isset($_GET['invoice_no']) ? esc($_GET['invoice_no']) : '';
$gross_wt = isset($_GET['gross_wt']) ? esc($_GET['gross_wt']) : '';
$ledger_type = isset($_GET['ledger_type']) ? esc($_GET['ledger_type']) : '';
$comment = isset($_GET['comment']) ? esc($_GET['comment']) : '';

// Parse date range
$from_date = '';
$to_date = '';
if (!empty($date_range)) {
    $dates = explode(' - ', $date_range);
    if (count($dates) == 2) {
        // Convert DD-MM-YYYY to YYYY-MM-DD
        $from_date_parts = explode('-', trim($dates[0]));
        $to_date_parts = explode('-', trim($dates[1]));
        if (count($from_date_parts) == 3 && count($to_date_parts) == 3) {
            $from_date = $from_date_parts[2] . '-' . $from_date_parts[1] . '-' . $from_date_parts[0];
            $to_date = $to_date_parts[2] . '-' . $to_date_parts[1] . '-' . $to_date_parts[0];
        }
    }
} else {
    $from_date = isset($_GET['from_date']) ? esc($_GET['from_date']) : '';
    $to_date = isset($_GET['to_date']) ? esc($_GET['to_date']) : '';
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$offset = ($page - 1) * $per_page;

// Sorting
$sort_column = isset($_GET['sort']) ? esc($_GET['sort']) : 'pi.invoice_date';
$sort_order = isset($_GET['order']) && strtolower($_GET['order']) == 'desc' ? 'DESC' : 'ASC';

// Build WHERE clause
$where_clause = "pi.status != 'cancelled' AND pii.status = 1";

if (!empty($from_date)) {
    $where_clause .= " AND pi.invoice_date >= '$from_date'";
}
if (!empty($to_date)) {
    $where_clause .= " AND pi.invoice_date <= '$to_date'";
}
if ($branch_id > 0) {
    // Branch filtering not available - purchase invoices don't have branch_id
    // $where_clause .= " AND pi.branch_id = $branch_id";
}
if (!empty($ledger_name)) {
    $where_clause .= " AND pi.supplier_name = '$ledger_name'";
}
if (!empty($purchase_person)) {
    $where_clause .= " AND pi.purchase_person = '$purchase_person'";
}
if (!empty($voucher_type)) {
    // Voucher type filtering - adjust based on your needs
}
if (!empty($metal_type)) {
    $where_clause .= " AND p.metal_id = $metal_type";
}
if ($product_id > 0) {
    $where_clause .= " AND pii.product_id = $product_id";
}
if ($category_id > 0) {
    $where_clause .= " AND p.category_id = $category_id";
}
if ($carat_id > 0) {
    $where_clause .= " AND pc.carat_id = $carat_id";
}
if (!empty($currency)) {
    $where_clause .= " AND pi.currency = '$currency'";
}
if ($above_amount > 0) {
    $where_clause .= " AND pi.grand_total >= $above_amount";
}
if (!empty($barcode_no)) {
    $where_clause .= " AND pii.barcode LIKE '%$barcode_no%'";
}
if (!empty($invoice_no)) {
    $where_clause .= " AND pi.invoice_no LIKE '%$invoice_no%'";
}
if (!empty($gross_wt)) {
    $where_clause .= " AND pii.gross_weight >= " . (float)$gross_wt;
}
if (!empty($ledger_type)) {
    // Ledger type filtering - adjust based on your needs
}
if (!empty($comment)) {
    $where_clause .= " AND pi.comment LIKE '%$comment%'";
}

// Main query to get purchase invoice items with all related data
$query = "
    SELECT 
        pi.id as invoice_id,
        pi.invoice_no,
        pi.supplier_name as ledger_name,
        pi.invoice_date as date,
        '' as branch,
        pii.barcode,
        pii.product_name as product,
        '' as location,
        pii.gross_weight as gross_wt,
        pii.final_weight as final_wt,
        pii.quantity as pcs,
        0.000 as stone_wt,
        pi.metal_amt,
        pii.making_amount as making_amt,
        0.00 as stone_amt,
        pii.amount as purchase_amt,
        pi.grand_total,
        pi.discount_amt as discount,
        -- Payment amounts by type (using subqueries)
        COALESCE((SELECT SUM(pip2.amount) FROM tbl_purchase_invoice_payments pip2 WHERE pip2.invoice_id = pi.id AND pip2.payment_type = 'cash' AND pip2.status = 1), 0) as cash,
        COALESCE((SELECT SUM(pip2.amount) FROM tbl_purchase_invoice_payments pip2 WHERE pip2.invoice_id = pi.id AND pip2.payment_type = 'bank' AND pip2.status = 1), 0) as bank,
        COALESCE((SELECT SUM(pip2.amount) FROM tbl_purchase_invoice_payments pip2 WHERE pip2.invoice_id = pi.id AND pip2.payment_type = 'cheque' AND pip2.status = 1), 0) as cheque,
        COALESCE((SELECT SUM(pip2.amount) FROM tbl_purchase_invoice_payments pip2 WHERE pip2.invoice_id = pi.id AND pip2.payment_type = 'upi' AND pip2.status = 1), 0) as upi,
        COALESCE((SELECT SUM(pip2.amount) FROM tbl_purchase_invoice_payments pip2 WHERE pip2.invoice_id = pi.id AND pip2.payment_type = 'card' AND pip2.status = 1), 0) as card,
        COALESCE((SELECT SUM(pip2.amount) FROM tbl_purchase_invoice_payments pip2 WHERE pip2.invoice_id = pi.id AND pip2.payment_type = 'metal_exchange' AND pip2.status = 1), 0) as metal_exch_amt,
        COALESCE((SELECT SUM(pip2.quantity) FROM tbl_purchase_invoice_payments pip2 WHERE pip2.invoice_id = pi.id AND pip2.payment_type = 'metal_exchange' AND pip2.status = 1), 0) as metal_exch_wt,
        COALESCE((SELECT SUM(pip2.amount) FROM tbl_purchase_invoice_payments pip2 WHERE pip2.invoice_id = pi.id AND pip2.payment_type = 'scrap' AND pip2.status = 1), 0) as old_jew_amt,
        COALESCE((SELECT SUM(pip2.quantity) FROM tbl_purchase_invoice_payments pip2 WHERE pip2.invoice_id = pi.id AND pip2.payment_type = 'scrap' AND pip2.status = 1), 0) as old_jew_wt,
        pi.balance_amt,
        pi.comment,
        pi.currency,
        c.name as category,
        pi.round_off as round_off_value
    FROM tbl_purchase_invoices pi
    INNER JOIN tbl_purchase_invoice_items pii ON pi.id = pii.invoice_id
    LEFT JOIN tbl_products p ON pii.product_id = p.id
    LEFT JOIN tbl_categories c ON p.category_id = c.id
    LEFT JOIN tbl_product_characteristics pc ON pii.product_characteristic_id = pc.id
    WHERE $where_clause
    ORDER BY $sort_column $sort_order
    LIMIT $per_page OFFSET $offset
";

// Count query
$count_query = "
    SELECT COUNT(DISTINCT CONCAT(pi.id, '-', pii.id)) as total
    FROM tbl_purchase_invoices pi
    INNER JOIN tbl_purchase_invoice_items pii ON pi.id = pii.invoice_id
    LEFT JOIN tbl_products p ON pii.product_id = p.id
    LEFT JOIN tbl_categories c ON p.category_id = c.id
    LEFT JOIN tbl_product_characteristics pc ON pii.product_characteristic_id = pc.id
    WHERE $where_clause
";

try {
    $data = getList($query);
    $count_result = getRecord($count_query);
    $total = (int)($count_result['total'] ?? 0);
    $total_pages = $total > 0 ? ceil($total / $per_page) : 1;
    
    // Format the data
    $formatted_data = [];
    foreach ($data as $row) {
        $formatted_data[] = [
            'invoice_id' => $row['invoice_id'],
            'invoice_no' => $row['invoice_no'],
            'branch' => $row['branch'],
            'date' => $row['date'],
            'barcode' => $row['barcode'],
            'product' => $row['product'],
            'location' => $row['location'],
            'gross_wt' => number_format((float)$row['gross_wt'], 3, '.', ''),
            'final_wt' => number_format((float)$row['final_wt'], 3, '.', ''),
            'pcs' => number_format((float)$row['pcs'], 2, '.', ''),
            'stone_wt' => number_format((float)$row['stone_wt'], 3, '.', ''),
            'metal_amt' => number_format((float)$row['metal_amt'], 2, '.', ''),
            'making_amt' => number_format((float)$row['making_amt'], 2, '.', ''),
            'stone_amt' => number_format((float)$row['stone_amt'], 2, '.', ''),
            'purchase_amt' => number_format((float)$row['purchase_amt'], 2, '.', ''),
            'ledger_name' => $row['ledger_name'],
            'grand_total' => number_format((float)$row['grand_total'], 2, '.', ''),
            'discount' => number_format((float)$row['discount'], 2, '.', ''),
            'cash' => number_format((float)$row['cash'], 2, '.', ''),
            'bank' => number_format((float)$row['bank'], 2, '.', ''),
            'cheque' => number_format((float)$row['cheque'], 2, '.', ''),
            'upi' => number_format((float)$row['upi'], 2, '.', ''),
            'round_off_value' => number_format((float)$row['round_off_value'], 2, '.', ''),
            'card' => number_format((float)$row['card'], 2, '.', ''),
            'metal_exch_amt' => number_format((float)$row['metal_exch_amt'], 2, '.', ''),
            'metal_exch_wt' => number_format((float)$row['metal_exch_wt'], 3, '.', ''),
            'old_jew_amt' => number_format((float)$row['old_jew_amt'], 2, '.', ''),
            'old_jew_wt' => number_format((float)$row['old_jew_wt'], 3, '.', ''),
            'balance_amt' => number_format((float)$row['balance_amt'], 2, '.', ''),
            'comment' => $row['comment'],
            'currency' => $row['currency'],
            'category' => $row['category']
        ];
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => $formatted_data,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'total_pages' => $total_pages
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
