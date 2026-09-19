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
    $current_return_id = isset($_GET['current_return_id']) ? (int)$_GET['current_return_id'] : 0;

    $where_clause = "WHERE pi.status != 'deleted'";
    if ($supplier_id > 0) {
        $where_clause .= " AND pi.supplier_id = " . (int)$supplier_id;
    }
    if (!empty($supplier_name)) {
        $where_clause .= " AND pi.supplier_name LIKE '%$supplier_name%'";
    }
    if (function_exists('auragold_sql_and_branch_scope') && function_exists('auragold_tbl_has_column')
        && auragold_tbl_has_column($conn, 'tbl_purchase_invoices', 'branch_id')) {
        $where_clause .= auragold_sql_and_branch_scope($conn, 'tbl_purchase_invoices', 'pi');
    }

    // Hide purchase invoices that are fully returned (no returnable qty left), for purchase-return picker.
    // Optional current_return_id: when editing that return, its lines count as "add-back" so the linked invoice stays visible.
    $returnable_filter_sql = '';
    $chk_pr = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_returns LIKE 'against_id'");
    $has_pr_against = ($chk_pr && mysqli_num_rows($chk_pr) > 0);
    if ($chk_pr) {
        mysqli_free_result($chk_pr);
    }
    if ($has_pr_against) {
        $cur_id = (int)$current_return_id;
        $returnable_filter_sql = "
        AND EXISTS (
            SELECT 1
            FROM (
                SELECT pii.product_id, pii.product_characteristic_id, SUM(pii.quantity) AS q_inv
                FROM tbl_purchase_invoice_items pii
                WHERE pii.invoice_id = pi.id
                GROUP BY pii.product_id, pii.product_characteristic_id
            ) invl
            WHERE invl.q_inv + COALESCE((
                SELECT SUM(pri.quantity)
                FROM tbl_purchase_return_items pri
                WHERE pri.return_id = $cur_id
                  AND pri.product_id = invl.product_id
                  AND (pri.product_characteristic_id <=> invl.product_characteristic_id)
            ), 0) > COALESCE((
                SELECT SUM(pri.quantity)
                FROM tbl_purchase_return_items pri
                INNER JOIN tbl_purchase_returns pr ON pr.id = pri.return_id
                WHERE pr.against_id = pi.id
                  AND pr.against_id IS NOT NULL
                  AND pr.against_id > 0
                  AND (
                      LOWER(TRIM(IFNULL(pr.against_type, ''))) LIKE '%invoice%'
                      OR LOWER(TRIM(IFNULL(pr.against_of, ''))) LIKE '%invoice%'
                  )
                  AND (pr.status IS NULL OR LOWER(TRIM(pr.status)) NOT IN ('deleted', 'cancelled', 'void'))
                  AND pri.product_id = invl.product_id
                  AND (pri.product_characteristic_id <=> invl.product_characteristic_id)
            ), 0) + 0.000001
        )
        ";
    }

    // Check if table exists
    $table_check = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_purchase_invoices'");
    if (!$table_check || mysqli_num_rows($table_check) == 0) {
        throw new Exception('Table tbl_purchase_invoices does not exist. Please run the SQL script to create it.');
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
    
    // Check if due_date column exists
    $has_due_date = false;
    $check_column = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_purchase_invoices LIKE 'due_date'");
    if ($check_column && mysqli_num_rows($check_column) > 0) {
        $has_due_date = true;
    }
    if ($check_column) {
        mysqli_free_result($check_column);
    }
    
    // Build query with conditional due_date
    $due_date_select = $has_due_date ? "COALESCE(pi.due_date, NULL) as due_date," : "NULL as due_date,";
    
    // Build contact_no select based on whether suppliers table exists
    if ($suppliers_table_exists) {
        $contact_no_select = "CONCAT(COALESCE(s.mobile_country_code, ''), ' ', COALESCE(s.mobile_no, '')) as contact_no";
        $supplier_join = "LEFT JOIN tbl_suppliers s ON pi.supplier_id = s.id";
    } else {
        $contact_no_select = "'' as contact_no";
        $supplier_join = "";
    }

    // Get invoices list with item count, gross weight, and supplier details
    $query = "
        SELECT 
            pi.id,
            pi.invoice_no,
            pi.supplier_name,
            pi.invoice_date,
            $due_date_select
            pi.grand_total,
            pi.paid_amt,
            pi.balance_amt,
            pi.status,
            (SELECT COUNT(*) FROM tbl_purchase_invoice_items WHERE invoice_id = pi.id) as item_count,
            (SELECT COALESCE(SUM(gross_weight), 0) FROM tbl_purchase_invoice_items WHERE invoice_id = pi.id) as total_gross_wt,
            (SELECT COALESCE(SUM(amount), 0) FROM tbl_purchase_invoice_payments WHERE invoice_id = pi.id) as total_paid_amt,
            $contact_no_select
        FROM tbl_purchase_invoices pi
        $supplier_join
        $where_clause
        $returnable_filter_sql
        ORDER BY pi.id DESC 
        LIMIT $limit
    ";
    
    // Execute query with error handling
    $result = @mysqli_query($conn, $query);
    if (!$result) {
        $error = mysqli_error($conn);
        throw new Exception('Database query failed: ' . $error);
    }
    
    $invoices = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $invoices[] = $row;
        }
        mysqli_free_result($result);
    }

    $response = [
        'status' => 'success',
        'invoices' => $invoices,
        'count' => count($invoices)
    ];
    
    ob_clean();
    echo json_encode($response);
    ob_end_flush();
    exit;
    
} catch (Exception $e) {
    error_log('Error in get-purchase-invoices-list.php: ' . $e->getMessage());
    $response = [
        'status' => 'error',
        'message' => 'Error loading invoices: ' . $e->getMessage(),
        'invoices' => [],
        'count' => 0
    ];
    
    ob_clean();
    echo json_encode($response);
    ob_end_flush();
    exit;
} catch (Error $e) {
    error_log('Fatal error in get-purchase-invoices-list.php: ' . $e->getMessage());
    $response = [
        'status' => 'error',
        'message' => 'Fatal error: ' . $e->getMessage(),
        'invoices' => [],
        'count' => 0
    ];
    
    ob_clean();
    echo json_encode($response);
    ob_end_flush();
    exit;
}
?>
