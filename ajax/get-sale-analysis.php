<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

// Get filters
$date_range = isset($_GET['date_range']) ? esc($_GET['date_range']) : '';
$branch_id = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : 0;
$ledger_name = isset($_GET['ledger_name']) ? esc($_GET['ledger_name']) : '';
$sales_person = isset($_GET['sales_person']) ? esc($_GET['sales_person']) : '';
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
$layaways_status = isset($_GET['layaways_status']) ? esc($_GET['layaways_status']) : '';

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
$sort_column = isset($_GET['sort']) ? esc($_GET['sort']) : 'si.invoice_date';
$sort_order = isset($_GET['order']) && strtolower($_GET['order']) == 'desc' ? 'DESC' : 'ASC';

// Build WHERE clause
$where_clause = "si.status != 'cancelled' AND sii.status = 1";

if (!empty($from_date)) {
    $where_clause .= " AND si.invoice_date >= '$from_date'";
}
if (!empty($to_date)) {
    $where_clause .= " AND si.invoice_date <= '$to_date'";
}
if ($branch_id > 0) {
    // Note: Branch filtering might need to be adjusted based on your schema
    // $where_clause .= " AND si.branch_id = $branch_id";
}
if (!empty($ledger_name)) {
    $where_clause .= " AND si.customer_name = '$ledger_name'";
}
if (!empty($sales_person)) {
    $where_clause .= " AND si.sales_person = '$sales_person'";
}
if (!empty($voucher_type)) {
    // Voucher type filtering - adjust based on your needs
}
if (!empty($metal_type)) {
    $where_clause .= " AND p.metal_id = $metal_type";
}
if ($product_id > 0) {
    $where_clause .= " AND sii.product_id = $product_id";
}
if ($category_id > 0) {
    $where_clause .= " AND p.category_id = $category_id";
}
if ($carat_id > 0) {
    $where_clause .= " AND pc.carat_id = $carat_id";
}
if (!empty($currency)) {
    $where_clause .= " AND si.currency = '$currency'";
}
if ($above_amount > 0) {
    $where_clause .= " AND si.grand_total >= $above_amount";
}
if (!empty($barcode_no)) {
    $where_clause .= " AND sii.barcode LIKE '%$barcode_no%'";
}
if (!empty($invoice_no)) {
    $where_clause .= " AND si.invoice_no LIKE '%$invoice_no%'";
}
if (!empty($gross_wt)) {
    $where_clause .= " AND sii.gross_weight >= " . (float)$gross_wt;
}
if (!empty($ledger_type)) {
    // Ledger type filtering - adjust based on your needs
}
if (!empty($comment)) {
    $where_clause .= " AND si.comment LIKE '%$comment%'";
}
if (!empty($layaways_status)) {
    // Layaways status filtering - adjust based on your needs
}

// Main query to get sale invoice items with all related data
// Use subqueries for payment aggregation to avoid GROUP BY issues
$query = "
    SELECT 
        si.id as invoice_id,
        si.invoice_no,
        si.customer_name as ledger_name,
        si.customer_name as party,
        si.sales_person,
        si.invoice_date as date,
        '' as branch, -- Branch will need to be added if available in schema
        sii.barcode,
        sii.quantity as pcs,
        c.name as category,
        sii.product_name as product,
        sii.gross_weight as gross_wt,
        sii.final_weight as final_wt,
        si.metal_amt,
        sii.making_amount as making_amt,
        sii.amount,
        sii.net_amount as sales_amt,
        sii.tax_amount,
        0.00 as making_cost, -- Calculate if needed
        0.00 as cost_price, -- Calculate if needed
        0.00 as profit, -- Calculate: sales_amt - cost_price
        si.grand_total,
        si.discount_amt as discount,
        -- Payment amounts by type (using subqueries)
        COALESCE((SELECT SUM(sip2.current_order_amount) FROM tbl_sale_invoice_payments sip2 WHERE sip2.invoice_id = si.id AND sip2.payment_type = 'cash' AND sip2.status = 1), 0) as cash,
        COALESCE((SELECT SUM(sip2.current_order_amount) FROM tbl_sale_invoice_payments sip2 WHERE sip2.invoice_id = si.id AND sip2.payment_type = 'bank' AND sip2.status = 1), 0) as bank,
        COALESCE((SELECT SUM(sip2.current_order_amount) FROM tbl_sale_invoice_payments sip2 WHERE sip2.invoice_id = si.id AND sip2.payment_type = 'cheque' AND sip2.status = 1), 0) as cheque,
        COALESCE((SELECT SUM(sip2.current_order_amount) FROM tbl_sale_invoice_payments sip2 WHERE sip2.invoice_id = si.id AND sip2.payment_type = 'upi' AND sip2.status = 1), 0) as upi,
        COALESCE((SELECT SUM(sip2.current_order_amount) FROM tbl_sale_invoice_payments sip2 WHERE sip2.invoice_id = si.id AND sip2.payment_type = 'card' AND sip2.status = 1), 0) as card,
        COALESCE((SELECT SUM(sip2.current_order_amount) FROM tbl_sale_invoice_payments sip2 WHERE sip2.invoice_id = si.id AND sip2.payment_type = 'metal_exchange' AND sip2.status = 1), 0) as metal_exch_amt,
        COALESCE((SELECT SUM(sip2.quantity) FROM tbl_sale_invoice_payments sip2 WHERE sip2.invoice_id = si.id AND sip2.payment_type = 'metal_exchange' AND sip2.status = 1), 0) as metal_exch_wt,
        COALESCE((SELECT SUM(sip2.current_order_amount) FROM tbl_sale_invoice_payments sip2 WHERE sip2.invoice_id = si.id AND sip2.payment_type = 'scrap' AND sip2.status = 1), 0) as old_jew_amt,
        COALESCE((SELECT SUM(sip2.quantity) FROM tbl_sale_invoice_payments sip2 WHERE sip2.invoice_id = si.id AND sip2.payment_type = 'scrap' AND sip2.status = 1), 0) as old_jew_wt,
        '' as huid_no, -- HUID No. if available
        si.balance_amt,
        si.comment,
        si.currency,
        '' as layaways_status, -- Layaways status if available
        si.advance_payment,
        si.round_off as round_off_value,
        COALESCE((SELECT SUM(sip2.previous_balance_amount) FROM tbl_sale_invoice_payments sip2 WHERE sip2.invoice_id = si.id AND sip2.status = 1), 0) as from_previous_balance_amount,
        0.00 as return_amount, -- Return amount if available
        si.additional_amt as additional_amount,
        0.00 as customer_advance_amount, -- Calculate from advance vouchers if needed
        0.00 as fund_transfer_amount, -- Fund transfer if available
        0.00 as sale_order_advance_payment -- Sale order advance if available
    FROM tbl_sale_invoices si
    INNER JOIN tbl_sale_invoice_items sii ON si.id = sii.invoice_id
    LEFT JOIN tbl_products p ON sii.product_id = p.id
    LEFT JOIN tbl_categories c ON p.category_id = c.id
    LEFT JOIN tbl_product_characteristics pc ON sii.product_characteristic_id = pc.id
    WHERE $where_clause
    ORDER BY $sort_column $sort_order
    LIMIT $per_page OFFSET $offset
";

// Count query
$count_query = "
    SELECT COUNT(DISTINCT CONCAT(si.id, '-', sii.id)) as total
    FROM tbl_sale_invoices si
    INNER JOIN tbl_sale_invoice_items sii ON si.id = sii.invoice_id
    LEFT JOIN tbl_products p ON sii.product_id = p.id
    LEFT JOIN tbl_categories c ON p.category_id = c.id
    LEFT JOIN tbl_product_characteristics pc ON sii.product_characteristic_id = pc.id
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
            'ledger_name' => $row['ledger_name'],
            'party' => $row['party'],
            'sales_person' => $row['sales_person'],
            'date' => $row['date'],
            'branch' => $row['branch'],
            'barcode' => $row['barcode'],
            'pcs' => number_format((float)$row['pcs'], 2, '.', ''),
            'category' => $row['category'],
            'product' => $row['product'],
            'gross_wt' => number_format((float)$row['gross_wt'], 3, '.', ''),
            'final_wt' => number_format((float)$row['final_wt'], 3, '.', ''),
            'metal_amt' => number_format((float)$row['metal_amt'], 2, '.', ''),
            'making_amt' => number_format((float)$row['making_amt'], 2, '.', ''),
            'amount' => number_format((float)$row['amount'], 2, '.', ''),
            'sales_amt' => number_format((float)$row['sales_amt'], 2, '.', ''),
            'tax_amount' => number_format((float)$row['tax_amount'], 2, '.', ''),
            'making_cost' => number_format((float)$row['making_cost'], 2, '.', ''),
            'cost_price' => number_format((float)$row['cost_price'], 2, '.', ''),
            'profit' => number_format((float)$row['profit'], 2, '.', ''),
            'grand_total' => number_format((float)$row['grand_total'], 2, '.', ''),
            'discount' => number_format((float)$row['discount'], 2, '.', ''),
            'cash' => number_format((float)$row['cash'], 2, '.', ''),
            'bank' => number_format((float)$row['bank'], 2, '.', ''),
            'cheque' => number_format((float)$row['cheque'], 2, '.', ''),
            'upi' => number_format((float)$row['upi'], 2, '.', ''),
            'card' => number_format((float)$row['card'], 2, '.', ''),
            'metal_exch_amt' => number_format((float)$row['metal_exch_amt'], 2, '.', ''),
            'metal_exch_wt' => number_format((float)$row['metal_exch_wt'], 3, '.', ''),
            'old_jew_amt' => number_format((float)$row['old_jew_amt'], 2, '.', ''),
            'old_jew_wt' => number_format((float)$row['old_jew_wt'], 3, '.', ''),
            'huid_no' => $row['huid_no'],
            'balance_amt' => number_format((float)$row['balance_amt'], 2, '.', ''),
            'comment' => $row['comment'],
            'currency' => $row['currency'],
            'layaways_status' => $row['layaways_status'],
            'advance_payment' => number_format((float)$row['advance_payment'], 2, '.', ''),
            'round_off_value' => number_format((float)$row['round_off_value'], 2, '.', ''),
            'from_previous_balance_amount' => number_format((float)$row['from_previous_balance_amount'], 2, '.', ''),
            'return_amount' => number_format((float)$row['return_amount'], 2, '.', ''),
            'additional_amount' => number_format((float)$row['additional_amount'], 2, '.', ''),
            'customer_advance_amount' => number_format((float)$row['customer_advance_amount'], 2, '.', ''),
            'fund_transfer_amount' => number_format((float)$row['fund_transfer_amount'], 2, '.', ''),
            'sale_order_advance_payment' => number_format((float)$row['sale_order_advance_payment'], 2, '.', '')
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
