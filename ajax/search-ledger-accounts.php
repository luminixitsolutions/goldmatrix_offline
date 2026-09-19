<?php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../includes/auragold_require_login.php';
auragold_require_login_or_exit();

header('Content-Type: application/json');

$search_term = isset($_GET['q']) ? esc($_GET['q']) : '';
$fetch_all = isset($_GET['all']) && (string) $_GET['all'] === '1';
$list_limit = $fetch_all ? 5000 : (strlen($search_term) < 1 ? 50 : 30);

// Unique ledger names from tbl_customer_ledger (+ stable customer_id for filters)
if (strlen($search_term) < 1) {
    $query = "
        SELECT customer_name AS name, MAX(customer_id) AS customer_id
        FROM tbl_customer_ledger
        WHERE status = 1
        AND customer_name IS NOT NULL
        AND customer_name != ''
        GROUP BY customer_name
        ORDER BY customer_name ASC
        LIMIT $list_limit
    ";
} else {
    $query = "
        SELECT customer_name AS name, MAX(customer_id) AS customer_id
        FROM tbl_customer_ledger
        WHERE status = 1
        AND customer_name IS NOT NULL
        AND customer_name != ''
        AND customer_name LIKE '%$search_term%'
        GROUP BY customer_name
        ORDER BY
            CASE
                WHEN customer_name LIKE '$search_term%' THEN 0
                ELSE 1
            END,
            customer_name ASC
        LIMIT $list_limit
    ";
}

$ledgers = getList($query);

$results = [];
foreach ($ledgers as $ledger) {
    $name = $ledger['name'];
    $cid  = isset($ledger['customer_id']) ? (int) $ledger['customer_id'] : 0;
    $mobile_no = '';

    $customer = getRecord("SELECT id, mobile_no FROM tbl_customers WHERE name = '" . esc($name) . "' AND status = 1 LIMIT 1");
    if ($customer) {
        if (!empty($customer['mobile_no'])) {
            $mobile_no = $customer['mobile_no'];
        }
        if ($cid <= 0 && !empty($customer['id'])) {
            $cid = (int) $customer['id'];
        }
    }

    $results[] = [
        'id' => $cid,
        'name' => $name,
        'mobile_no' => $mobile_no,
        'display_text' => $name . ($mobile_no ? ' - ' . $mobile_no : ''),
    ];
}

// Also search by mobile number in tbl_customers if search term looks like a number
if (strlen($search_term) >= 1 && preg_match('/[0-9]/', $search_term)) {
    $mobileQuery = "
        SELECT DISTINCT c.id AS customer_id, c.name, c.mobile_no
        FROM tbl_customers c
        INNER JOIN tbl_customer_ledger l ON c.name = l.customer_name
        WHERE c.status = 1
        AND l.status = 1
        AND c.mobile_no LIKE '%$search_term%'
        ORDER BY c.name ASC
        LIMIT 20
    ";
    $mobileResults = getList($mobileQuery);

    $existingNames = array_column($results, 'name');
    foreach ($mobileResults as $mr) {
        if (!in_array($mr['name'], $existingNames, true)) {
            $results[] = [
                'id' => isset($mr['customer_id']) ? (int) $mr['customer_id'] : 0,
                'name' => $mr['name'],
                'mobile_no' => $mr['mobile_no'] ?? '',
                'display_text' => $mr['name'] . (!empty($mr['mobile_no']) ? ' - ' . $mr['mobile_no'] : ''),
            ];
        }
    }
}

echo json_encode([
    'status' => 'success',
    'ledgers' => $results
]);
?>
