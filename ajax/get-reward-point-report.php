<?php

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_require_login.php';
require_once __DIR__ . '/../includes/auragold_reward_point_report_data.php';

auragold_require_login_or_exit();

header('Content-Type: application/json; charset=utf-8');

$search = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
$from_date = isset($_GET['from_date']) ? trim((string) esc($_GET['from_date'])) : '';
$to_date = isset($_GET['to_date']) ? trim((string) esc($_GET['to_date'])) : '';

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}

$raw_per = isset($_GET['per_page']) ? $_GET['per_page'] : '25';
$unlimited_view = ($raw_per === 'all' || $raw_per === '0');
if ($unlimited_view) {
    $per_page = PHP_INT_MAX;
} else {
    $per_page = max(1, min(500, (int) $raw_per));
}

$offset = 0;
if (!$unlimited_view && $per_page < PHP_INT_MAX - 1000) {
    $offset = ($page - 1) * $per_page;
}

$sort = isset($_GET['sort']) ? preg_replace('/[^a-z0-9_]/', '', (string) $_GET['sort']) : 'invoice_date';

$order_get = isset($_GET['order']) ? strtolower(trim((string) $_GET['order'])) : 'desc';
$order_sql = ($order_get === 'desc') ? 'desc' : 'asc';

$params = [
    'from_date'       => $from_date,
    'to_date'         => $to_date,
    'search'          => $search,
    'sort'            => $sort,
    'order'           => $order_sql,
    'unlimited'       => $unlimited_view,
    'max_limit_cap'   => $unlimited_view ? 100000 : 0,
    'limit'           => $per_page >= PHP_INT_MAX - 1000 ? 500 : (int) $per_page,
    'offset'          => $offset,
];

$result = auragold_reward_point_report_fetch($conn, $params);

if (!empty($result['error'])) {
    echo json_encode([
        'status'  => 'error',
        'message' => (string) $result['error'],
    ]);
    exit;
}

$total = (int) $result['total'];
$effective_per = $unlimited_view ? $total : (int) $per_page;
$total_pages = $total > 0 && !$unlimited_view && $effective_per > 0 ? (int) ceil($total / $effective_per) : 1;
if ($total_pages < 1) {
    $total_pages = 1;
}

echo json_encode([
    'status' => 'success',
    'data'   => $result['rows'],
    'pagination' => [
        'current_page' => $page,
        'per_page'     => $unlimited_view ? 0 : (int) $per_page,
        'total'        => $total,
        'total_pages'  => $total_pages,
    ],
    'meta' => [
        'from_date' => $result['from_date'],
        'to_date'   => $result['to_date'],
        'sort'      => $result['sort'],
        'order'     => $result['order'],
    ],
]);
