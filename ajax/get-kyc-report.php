<?php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../includes/customer_nominee_schema.php';
if (isset($conn) && $conn instanceof mysqli) {
    auragold_ensure_customer_nominee_column($conn);
}

header('Content-Type: application/json');

$search = isset($_GET['search']) ? esc($_GET['search']) : '';
$customer_type_id = isset($_GET['customer_type_id']) ? (int) $_GET['customer_type_id'] : 0;
$country_id = isset($_GET['country_id']) ? (int) $_GET['country_id'] : 0;
$nationality_id = isset($_GET['nationality_id']) ? (int) $_GET['nationality_id'] : 0;
$has_aml = isset($_GET['has_aml']) ? esc($_GET['has_aml']) : '';

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 25;
if ($per_page < 1) {
    $per_page = 25;
}
$offset = ($page - 1) * $per_page;

$sort_key = isset($_GET['sort']) ? preg_replace('/[^a-z0-9_]/', '', $_GET['sort']) : 'name';
$sort_order = isset($_GET['order']) && strtolower($_GET['order']) === 'desc' ? 'DESC' : 'ASC';

$sort_map = [
    'name'             => 'c.name',
    'account_no'       => 'c.id',
    'first_name'       => 'c.first_name',
    'last_name'        => 'c.last_name',
    'contact'          => 'c.mobile_no',
    'email_id'         => 'c.mail_id',
    'identity_no'      => 'c.identity_no',
    'national_id'      => 'c.national_id',
    'trade_no'         => 'c.trade_no',
    'special_day'      => 'c.special_day',
    'dob'              => 'c.date1',
    'registration'     => 'c.registration_no',
    'customer_type'    => 'ct.name',
    'country'          => 'co.name',
    'nationality'      => 'n.name',
    'billing_address'  => 'c.billing_address1',
    'state'            => 'c.billing_state',
    'nominee'          => 'nominee_sort',
    'aml'              => 'c.aml',
    'info'             => 'c.notes',
];

$order_col = $sort_map['name'];
if ($sort_key !== '' && isset($sort_map[$sort_key])) {
    $order_col = $sort_map[$sort_key];
}

$where_clause = 'c.status = 1';

if ($search !== '') {
    $where_clause .= " AND (
        c.name LIKE '%$search%'
        OR c.alternate_name LIKE '%$search%'
        OR c.first_name LIKE '%$search%'
        OR c.last_name LIKE '%$search%'
        OR c.mobile_no LIKE '%$search%'
        OR c.mail_id LIKE '%$search%'
        OR c.identity_no LIKE '%$search%'
        OR c.national_id LIKE '%$search%'
        OR c.trade_no LIKE '%$search%'
        OR CAST(c.id AS CHAR) LIKE '%$search%'
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
    $where_clause .= ' AND c.aml = ' . (int) $has_aml;
}

$nominee_expr = "NULLIF(TRIM(COALESCE(
    JSON_UNQUOTE(JSON_EXTRACT(c.nominee_data, '\$[0].name')),
    JSON_UNQUOTE(JSON_EXTRACT(c.share_holders_data, '\$[0].name')),
    '')), '')";

$query = "
    SELECT
        c.id,
        c.name,
        c.first_name,
        c.last_name,
        CONCAT(TRIM(COALESCE(c.mobile_country_code, '')), ' ', TRIM(COALESCE(c.mobile_no, ''))) AS contact,
        c.mail_id AS email_id,
        c.identity_no,
        c.national_id,
        c.trade_no,
        c.special_day,
        c.date1 AS dob,
        c.registration_no,
        c.registration_date,
        COALESCE(ct.name, '') AS customer_type,
        COALESCE(co.name, '') AS country,
        COALESCE(n.name, '') AS nationality,
        TRIM(CONCAT(COALESCE(c.billing_address1, ''), ' ', COALESCE(c.billing_address2, ''))) AS billing_address,
        c.billing_state AS state,
        $nominee_expr AS nominee,
        $nominee_expr AS nominee_sort,
        c.notes AS info,
        CASE WHEN c.aml = 1 THEN 'Yes' ELSE 'No' END AS aml
    FROM tbl_customers c
    LEFT JOIN tbl_customer_types ct ON c.customer_type_id = ct.id
    LEFT JOIN tbl_countries co ON c.country_id = co.id
    LEFT JOIN tbl_nationalities n ON c.nationality_id = n.id
    WHERE $where_clause
    ORDER BY $order_col $sort_order
    LIMIT $per_page OFFSET $offset
";

$count_query = "
    SELECT COUNT(*) AS total
    FROM tbl_customers c
    WHERE $where_clause
";

try {
    $data = getList($query);
    $count_result = getRecord($count_query);
    $total = (int) ($count_result['total'] ?? 0);
    $total_pages = $total > 0 ? (int) ceil($total / $per_page) : 1;

    $formatted = [];
    foreach ($data as $row) {
        $reg_parts = array_filter([
            trim((string) ($row['registration_no'] ?? '')),
            !empty($row['registration_date']) ? $row['registration_date'] : '',
        ]);
        $formatted[] = [
            'id'             => $row['id'],
            'account_no'   => (string) ($row['id'] ?? ''),
            'name'           => $row['name'] ?? '',
            'first_name'     => $row['first_name'] ?? '',
            'last_name'      => $row['last_name'] ?? '',
            'contact'        => trim(preg_replace('/\s+/', ' ', (string) ($row['contact'] ?? ''))),
            'email_id'       => $row['email_id'] ?? '',
            'identity_no'    => $row['identity_no'] ?? '',
            'national_id'    => $row['national_id'] ?? '',
            'trade_no'       => $row['trade_no'] ?? '',
            'special_day'    => $row['special_day'] ?? '',
            'dob'            => $row['dob'] ?? '',
            'registration'   => implode(' | ', $reg_parts),
            'customer_type'  => $row['customer_type'] ?? '',
            'country'        => $row['country'] ?? '',
            'nationality'    => $row['nationality'] ?? '',
            'billing_address'=> $row['billing_address'] ?? '',
            'state'          => $row['state'] ?? '',
            'nominee'        => $row['nominee'] ?? '',
            'aml'            => $row['aml'] ?? '',
            'info'           => $row['info'] ?? '',
        ];
    }

    echo json_encode([
        'status'     => 'success',
        'data'       => $formatted,
        'pagination' => [
            'current_page' => $page,
            'per_page'     => $per_page,
            'total'             => $total,
            'total_pages'       => $total_pages,
        ],
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage(),
    ]);
}
