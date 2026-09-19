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
    $customer_name = isset($_GET['customer_name']) ? esc($_GET['customer_name']) : '';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;

    $where_clause = "WHERE sq.status != 'deleted'";
    if (!empty($customer_name)) {
        $where_clause .= " AND sq.customer_name LIKE '%$customer_name%'";
    }
    if (auragold_tbl_has_column($conn, 'tbl_sale_quotations', 'branch_id')) {
        $where_clause .= auragold_sql_and_branch_scope($conn, 'tbl_sale_quotations', 'sq');
    }

    // Check if table exists
    $table_check = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sale_quotations'");
    if (!$table_check || mysqli_num_rows($table_check) == 0) {
        throw new Exception('Table tbl_sale_quotations does not exist. Please run the SQL script to create it.');
    }
    if ($table_check) {
        mysqli_free_result($table_check);
    }
    
    // Check if due_date column exists
    $has_due_date = false;
    $check_column = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_sale_quotations LIKE 'due_date'");
    if ($check_column && mysqli_num_rows($check_column) > 0) {
        $has_due_date = true;
    }
    if ($check_column) {
        mysqli_free_result($check_column);
    }
    
    // Build query with conditional due_date
    $due_date_select = $has_due_date ? "COALESCE(sq.due_date, NULL) as due_date," : "NULL as due_date,";
    
    // Get quotations list with item count, gross weight, and customer details
    $query = "
        SELECT 
            sq.id,
            sq.quotation_no,
            sq.customer_name,
            sq.quotation_date,
            $due_date_select
            sq.grand_total,
            sq.paid_amt,
            sq.balance_amt,
            sq.status,
            (SELECT COUNT(*) FROM tbl_sale_quotation_items WHERE quotation_id = sq.id) as item_count,
            (SELECT COALESCE(SUM(gross_weight), 0) FROM tbl_sale_quotation_items WHERE quotation_id = sq.id) as total_gross_wt,
            (SELECT COALESCE(SUM(amount), 0) FROM tbl_sale_quotation_payments WHERE quotation_id = sq.id) as total_paid_amt,
            CONCAT(COALESCE(c.mobile_country_code, ''), ' ', COALESCE(c.mobile_no, '')) as contact_no
        FROM tbl_sale_quotations sq
        LEFT JOIN tbl_customers c ON sq.customer_id = c.id
        $where_clause
        ORDER BY sq.id DESC 
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
    error_log('Error in get-sale-quotations-list.php: ' . $e->getMessage());
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
    error_log('Fatal error in get-sale-quotations-list.php: ' . $e->getMessage());
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
