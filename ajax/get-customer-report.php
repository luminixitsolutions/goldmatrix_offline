<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

// Get filters
$search = isset($_GET['search']) ? esc($_GET['search']) : '';
$customer_type_id = isset($_GET['customer_type_id']) ? (int)$_GET['customer_type_id'] : 0;
$country_id = isset($_GET['country_id']) ? (int)$_GET['country_id'] : 0;
$nationality_id = isset($_GET['nationality_id']) ? (int)$_GET['nationality_id'] : 0;
$has_aml = isset($_GET['has_aml']) ? esc($_GET['has_aml']) : '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$offset = ($page - 1) * $per_page;

// Sorting
$sort_column = isset($_GET['sort']) ? esc($_GET['sort']) : 'c.name';
$sort_order = isset($_GET['order']) && strtolower($_GET['order']) == 'desc' ? 'DESC' : 'ASC';

// Build WHERE clause
$where_clause = "c.status = 1";

if (!empty($search)) {
    $where_clause .= " AND (
        c.name LIKE '%$search%' 
        OR c.alternate_name LIKE '%$search%'
        OR c.mobile_no LIKE '%$search%'
        OR c.mail_id LIKE '%$search%'
        OR c.identity_no LIKE '%$search%'
        OR c.national_id LIKE '%$search%'
        OR c.trade_no LIKE '%$search%'
    )";
}
if ($customer_type_id > 0) {
    $where_clause .= " AND c.customer_type_id = $customer_type_id";
}
if ($country_id > 0) {
    $where_clause .= " AND c.country_id = $country_id";
}
if ($nationality_id > 0) {
    $where_clause .= " AND c.nationality_id = $nationality_id";
}
if ($has_aml !== '') {
    $where_clause .= " AND c.aml = " . (int)$has_aml;
}

// Main query to get customer data
$query = "
    SELECT 
        c.id,
        c.name,
        CONCAT(c.mobile_country_code, ' ', c.mobile_no) as contact,
        c.mail_id as email_id,
        c.identity_no,
        c.national_id,
        c.trade_no,
        c.special_day,
        c.date1 as dob,
        c.registration_no as registration,
        COALESCE(ct.name, '') as customer_type,
        COALESCE(co.name, '') as country,
        COALESCE(n.name, '') as nationality,
        CONCAT(COALESCE(c.billing_address1, ''), ' ', COALESCE(c.billing_address2, '')) as billing_address,
        c.billing_state as state,
        '' as nominee,
        c.notes as info,
        CASE WHEN c.aml = 1 THEN 'Yes' ELSE 'No' END as aml
    FROM tbl_customers c
    LEFT JOIN tbl_customer_types ct ON c.customer_type_id = ct.id
    LEFT JOIN tbl_countries co ON c.country_id = co.id
    LEFT JOIN tbl_nationalities n ON c.nationality_id = n.id
    WHERE $where_clause
    ORDER BY $sort_column $sort_order
    LIMIT $per_page OFFSET $offset
";

// Count query
$count_query = "
    SELECT COUNT(*) as total
    FROM tbl_customers c
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
            'id' => $row['id'],
            'name' => $row['name'],
            'contact' => trim($row['contact']),
            'email_id' => $row['email_id'],
            'identity_no' => $row['identity_no'],
            'national_id' => $row['national_id'],
            'trade_no' => $row['trade_no'],
            'special_day' => $row['special_day'],
            'dob' => $row['dob'],
            'registration' => $row['registration'],
            'customer_type' => $row['customer_type'],
            'country' => $row['country'],
            'nationality' => $row['nationality'],
            'billing_address' => trim($row['billing_address']),
            'state' => $row['state'],
            'nominee' => $row['nominee'],
            'info' => $row['info'],
            'aml' => $row['aml']
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
