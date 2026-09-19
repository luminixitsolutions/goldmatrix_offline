<?php
// Suppress warnings and start output buffering to ensure clean JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);
ob_start();

session_start();
require_once '../config.php';

// Clear any output that might have been generated
ob_clean();

header('Content-Type: application/json');

try {
    $supplier_name = isset($_GET['supplier_name']) ? esc($_GET['supplier_name']) : '';
    $supplier_id = isset($_GET['supplier_id']) ? (int)$_GET['supplier_id'] : 0;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;

    $where_clause = "WHERE pq.status != 'deleted'";
    if ($supplier_id > 0) {
        $where_clause .= " AND pq.supplier_id = " . (int)$supplier_id;
    }
    if (!empty($supplier_name)) {
        $where_clause .= " AND pq.supplier_name LIKE '%$supplier_name%'";
    }
    if (auragold_tbl_has_column($conn, 'tbl_purchase_quotations', 'branch_id')) {
        $where_clause .= auragold_sql_and_branch_scope($conn, 'tbl_purchase_quotations', 'pq');
    }

    // Check if table exists
    $table_check = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_purchase_quotations'");
    if (!$table_check || mysqli_num_rows($table_check) == 0) {
        throw new Exception('Table tbl_purchase_quotations does not exist. Please run the SQL script to create it.');
    }
    if ($table_check) {
        mysqli_free_result($table_check);
    }
    
    // Check if suppliers table exists
    $suppliers_table_exists = false;
    $suppliers_check = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_suppliers'");
    if ($suppliers_check && mysqli_num_rows($suppliers_check) > 0) {
        $suppliers_table_exists = true;
    }
    if ($suppliers_check) {
        mysqli_free_result($suppliers_check);
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
    
    // Check if due_date column exists
    $has_due_date = false;
    $check_column = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_quotations LIKE 'due_date'");
    if ($check_column && mysqli_num_rows($check_column) > 0) {
        $has_due_date = true;
    }
    if ($check_column) {
        mysqli_free_result($check_column);
    }
    
    // Build query with conditional due_date
    $due_date_select = $has_due_date ? "COALESCE(pq.due_date, NULL) as due_date," : "NULL as due_date,";
    
    // Build contact_no select based on whether suppliers table exists
    if ($suppliers_table_exists) {
        $contact_no_select = "CONCAT(COALESCE(s.mobile_country_code, ''), ' ', COALESCE(s.mobile_no, '')) as contact_no";
        $supplier_join = "LEFT JOIN tbl_suppliers s ON pq.supplier_id = s.id";
    } else {
        $contact_no_select = "'' as contact_no";
        $supplier_join = "";
    }
    
    // Build item count and totals based on whether tables exist
    if ($items_table_exists) {
        $item_count_select = "(SELECT COUNT(*) FROM tbl_purchase_quotation_items WHERE quotation_id = pq.id) as item_count";
        $gross_wt_select = "(SELECT COALESCE(SUM(gross_weight), 0) FROM tbl_purchase_quotation_items WHERE quotation_id = pq.id) as total_gross_wt";
    } else {
        $item_count_select = "0 as item_count";
        $gross_wt_select = "0.000 as total_gross_wt";
    }
    
    if ($payments_table_exists) {
        $paid_amt_select = "(SELECT COALESCE(SUM(amount), 0) FROM tbl_purchase_quotation_payments WHERE quotation_id = pq.id) as total_paid_amt";
    } else {
        $paid_amt_select = "0.00 as total_paid_amt";
    }
    
    // Get quotations list with item count, gross weight, and supplier details
    $query = "
        SELECT 
            pq.id,
            pq.quotation_no,
            pq.supplier_name,
            pq.quotation_date,
            $due_date_select
            pq.grand_total,
            pq.paid_amt,
            pq.balance_amt,
            pq.status,
            $item_count_select,
            $gross_wt_select,
            $paid_amt_select,
            $contact_no_select
        FROM tbl_purchase_quotations pq
        $supplier_join
        $where_clause
        ORDER BY pq.id DESC 
        LIMIT $limit
    ";
    
    // Execute query with error handling
    $result = @mysqli_query($conn, $query);
    if (!$result) {
        $error = mysqli_error($conn);
        throw new Exception('Database query failed: ' . $error);
    }
    
    $quotations = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $quotations[] = $row;
        }
        mysqli_free_result($result);
    }

    $response = [
        'status' => 'success',
        'quotations' => $quotations,
        'count' => count($quotations)
    ];
    
    ob_clean();
    echo json_encode($response);
    ob_end_flush();
    exit;
    
} catch (Exception $e) {
    error_log('Error in get-purchase-quotations-list.php: ' . $e->getMessage());
    $response = [
        'status' => 'error',
        'message' => 'Error loading quotations: ' . $e->getMessage(),
        'quotations' => [],
        'count' => 0
    ];
    
    ob_clean();
    echo json_encode($response);
    ob_end_flush();
    exit;
} catch (Error $e) {
    error_log('Fatal error in get-purchase-quotations-list.php: ' . $e->getMessage());
    $response = [
        'status' => 'error',
        'message' => 'Fatal error: ' . $e->getMessage(),
        'quotations' => [],
        'count' => 0
    ];
    
    ob_clean();
    echo json_encode($response);
    ob_end_flush();
    exit;
}
?>
