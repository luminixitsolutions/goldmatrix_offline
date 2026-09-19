<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$results = [];

$tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_delivery_notes'");
if ($tbl && mysqli_num_rows($tbl) > 0) {
    mysqli_free_result($tbl);
    $tbl_items = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_delivery_note_items'");
    $has_items = ($tbl_items && mysqli_num_rows($tbl_items) > 0);
    if ($tbl_items) mysqli_free_result($tbl_items);

    $search_term = isset($_GET['q']) ? trim(esc($_GET['q'])) : '';
    $query = "
        SELECT 
            o.id, 
            o.delivery_no AS order_no,
            o.customer_name,
            o.customer_id,
            o.delivery_date AS order_date,
            o.grand_total,
            o.currency,
            c.mobile_no AS contact_no
    ";
    if ($has_items) {
        $query .= ",
            (SELECT dni.product_name FROM tbl_delivery_note_items dni WHERE dni.delivery_note_id = o.id ORDER BY dni.id LIMIT 1) AS first_item,
            (SELECT COALESCE(SUM(dni.gross_weight), 0) FROM tbl_delivery_note_items dni WHERE dni.delivery_note_id = o.id) AS gross_wt
        ";
    } else {
        $query .= ", '' AS first_item, 0 AS gross_wt ";
    }
    $query .= ", NULL AS due_date, 0 AS paid_amt ";
    $query .= " FROM tbl_delivery_notes o
    ";
    $has_due = false;
    $has_paid = false;
    $cols = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_delivery_notes LIKE 'due_date'");
    if ($cols && mysqli_num_rows($cols) > 0) { $has_due = true; }
    if ($cols) mysqli_free_result($cols);
    $cols = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_delivery_notes LIKE 'paid_amt'");
    if ($cols && mysqli_num_rows($cols) > 0) { $has_paid = true; }
    if ($cols) mysqli_free_result($cols);
    if ($has_due) { $query = str_replace('NULL AS due_date', 'o.due_date', $query); }
    if ($has_paid) { $query = str_replace('0 AS paid_amt ', 'o.paid_amt ', $query); }
    $query .= "
        LEFT JOIN tbl_customers c ON c.id = o.customer_id AND c.status = 1
    ";
    if ($search_term !== '') {
        $term = '%' . mysqli_real_escape_string($conn, $search_term) . '%';
        $query .= " WHERE (o.customer_name LIKE '$term' OR o.delivery_no LIKE '$term') ";
    }
    $query .= " ORDER BY o.id DESC LIMIT 20";
    $result = @mysqli_query($conn, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $results[] = [
                'id' => $row['id'],
                'order_no' => $row['order_no'] ?? '',
                'customer_name' => $row['customer_name'] ?? '',
                'customer_id' => $row['customer_id'] ?? 0,
                'order_date' => $row['order_date'] ?? '',
                'due_date' => $row['due_date'] ?? '',
                'grand_total' => $row['grand_total'] ?? 0,
                'paid_amt' => $row['paid_amt'] ?? 0,
                'contact_no' => $row['contact_no'] ?? '',
                'first_item' => $row['first_item'] ?? '',
                'gross_wt' => $row['gross_wt'] ?? 0,
                'currency' => $row['currency'] ?? 'AED',
                'formatted_date' => !empty($row['order_date']) ? date('d-m-Y', strtotime($row['order_date'])) : '',
                'formatted_due_date' => !empty($row['due_date']) ? date('d-m-Y', strtotime($row['due_date'])) : ''
            ];
        }
        mysqli_free_result($result);
    }
} else {
    if ($tbl) mysqli_free_result($tbl);
}

echo json_encode([
    'status' => 'success',
    'orders' => $results
]);
?>
