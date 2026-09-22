<?php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../includes/auragold_require_login.php';
auragold_require_login_or_exit();

header('Content-Type: application/json');

$search_term = isset($_GET['q']) ? esc($_GET['q']) : '';
$format_select2 = isset($_GET['format']) && (string) $_GET['format'] === 'select2';
$fetch_all = isset($_GET['all']) && (string) $_GET['all'] === '1';
$term_len = strlen($search_term);
$list_limit = $fetch_all ? 5000 : ($term_len < 1 ? 100 : 40);

$name_ci = static function (string $expr): string {
    return 'CONVERT(' . $expr . ' USING utf8mb4) COLLATE utf8mb4_unicode_ci';
};
$ledger_name_ci = $name_ci('customer_name');
$customer_name_ci = $name_ci('TRIM(name)');

$ledger_names_sql = "
    SELECT DISTINCT {$ledger_name_ci} AS ledger_name
    FROM tbl_customer_ledger
    WHERE status = 1 AND TRIM(customer_name) <> ''
    UNION
    SELECT DISTINCT {$customer_name_ci} AS ledger_name
    FROM tbl_customers
    WHERE status = 1 AND TRIM(name) <> ''
";

$where = '1=1';
if ($term_len >= 1) {
    $where .= " AND dn.ledger_name LIKE '%$search_term%'";
}

$order_sql = 'dn.ledger_name ASC';
if ($term_len >= 1) {
    $order_sql = "
        CASE WHEN dn.ledger_name LIKE '$search_term%' THEN 0 ELSE 1 END,
        dn.ledger_name ASC
    ";
}

$query = "
    SELECT dn.ledger_name AS name
    FROM (
        {$ledger_names_sql}
    ) dn
    WHERE {$where}
    ORDER BY {$order_sql}
    LIMIT {$list_limit}
";

$ledgers = getList($query);
if (!is_array($ledgers)) {
    $ledgers = [];
}

$results = [];
$seen_names = [];

foreach ($ledgers as $ledger) {
    $name = trim((string) ($ledger['name'] ?? ''));
    if ($name === '') {
        continue;
    }
    $key = mb_strtolower($name);
    if (isset($seen_names[$key])) {
        continue;
    }
    $seen_names[$key] = true;

    $cid = 0;
    $mobile_no = '';
    $name_esc = mysqli_real_escape_string($conn, $name);
    $customer = getRecord("SELECT id, mobile_no FROM tbl_customers WHERE TRIM(name) = '" . $name_esc . "' AND status = 1 LIMIT 1");
    if ($customer) {
        $cid = (int) ($customer['id'] ?? 0);
        $mobile_no = trim((string) ($customer['mobile_no'] ?? ''));
    }

    $results[] = [
        'id' => $cid,
        'name' => $name,
        'mobile_no' => $mobile_no,
        'display_text' => $name . ($mobile_no !== '' ? ' - ' . $mobile_no : ''),
    ];
}

// Also search by mobile number in tbl_customers (same as account ledger search)
if ($term_len >= 1 && preg_match('/[0-9]/', $search_term)) {
    $mobileResults = getList("
        SELECT DISTINCT c.id AS customer_id, c.name, c.mobile_no
        FROM tbl_customers c
        WHERE c.status = 1
        AND c.mobile_no LIKE '%$search_term%'
        AND (
            EXISTS (
                SELECT 1 FROM tbl_customer_ledger l
                WHERE l.status = 1 AND l.customer_name = c.name
            )
            OR TRIM(c.name) <> ''
        )
        ORDER BY c.name ASC
        LIMIT 20
    ");
    if (is_array($mobileResults)) {
        foreach ($mobileResults as $mr) {
            $name = trim((string) ($mr['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $key = mb_strtolower($name);
            if (isset($seen_names[$key])) {
                continue;
            }
            $seen_names[$key] = true;
            $mobile_no = trim((string) ($mr['mobile_no'] ?? ''));
            $results[] = [
                'id' => (int) ($mr['customer_id'] ?? 0),
                'name' => $name,
                'mobile_no' => $mobile_no,
                'display_text' => $name . ($mobile_no !== '' ? ' - ' . $mobile_no : ''),
            ];
        }
    }
}

$out = [
    'status' => 'success',
    'ledgers' => $results,
];

if ($format_select2) {
    $select2 = [];
    foreach ($results as $row) {
        $name = (string) ($row['name'] ?? '');
        $cid = (int) ($row['id'] ?? 0);
        $mobile_no = (string) ($row['mobile_no'] ?? '');
        $text = $name;
        if ($mobile_no !== '') {
            $text .= ($text !== '' ? ' — ' : '') . $mobile_no;
        }
        // Use ledger name as id when no customer master row (Cash, Bank Account, etc.)
        $select_id = $cid > 0 ? (string) $cid : $name;
        $select2[] = [
            'id' => $select_id,
            'text' => $text !== '' ? $text : $name,
            'name' => $name,
            'mobile_no' => $mobile_no,
            'customer_id' => $cid,
        ];
    }
    $out['results'] = $select2;
    $out['pagination'] = ['more' => count($results) >= $list_limit];
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
