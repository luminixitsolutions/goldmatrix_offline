<?php

declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_require_login.php';
require_once __DIR__ . '/../includes/auragold_ageing_report_data.php';

auragold_require_login_or_exit();

header('Content-Type: application/json; charset=utf-8');

$tab = isset($_GET['tab']) ? strtolower(trim((string) $_GET['tab'])) : 'ledger';
if ($tab !== 'ledger' && $tab !== 'stock') {
    $tab = 'ledger';
}

$aging_date = isset($_GET['aging_date']) ? trim((string) $_GET['aging_date']) : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $aging_date)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid aging_date']);
    exit;
}

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

if ($tab === 'stock') {
    $search = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
    $product_id = isset($_GET['stock_product_id']) ? (int) $_GET['stock_product_id'] : 0;

    $result = auragold_ageing_stock_fetch($conn, [
        'aging_date' => $aging_date,
        'product_id' => $product_id,
        'search' => $search,
        'page' => $page,
        'per_page' => $unlimited_view ? -1 : $per_page,
        'unlimited' => $unlimited_view,
    ]);
} else {
    $search = isset($_GET['search']) ? trim((string) $_GET['search']) : '';
    $pr_type = isset($_GET['pr_type']) ? trim((string) $_GET['pr_type']) : 'receivable';
    $vl_wise = isset($_GET['vl_wise']) ? trim((string) $_GET['vl_wise']) : 'voucher';
    $ledger_customer_id = isset($_GET['ledger_customer_id']) ? (int) $_GET['ledger_customer_id'] : 0;
    $account_ledger = isset($_GET['account_ledger']) ? trim((string) $_GET['account_ledger']) : '';

    $result = auragold_ageing_ledger_fetch($conn, [
        'aging_date' => $aging_date,
        'pr_type' => $pr_type,
        'vl_wise' => $vl_wise,
        'ledger_customer_id' => $ledger_customer_id,
        'account_ledger' => $account_ledger,
        'search' => $search,
        'page' => $page,
        'per_page' => $unlimited_view ? -1 : $per_page,
        'unlimited' => $unlimited_view,
    ]);
}

if (!empty($result['error'])) {
    echo json_encode([
        'status' => 'error',
        'message' => (string) $result['error'],
    ]);
    exit;
}

$total = (int) ($result['total'] ?? 0);
$effective_per = $unlimited_view ? $total : (int) $per_page;
$total_pages = $total > 0 && !$unlimited_view && $effective_per > 0 ? (int) ceil($total / $effective_per) : 1;
if ($total_pages < 1) {
    $total_pages = 1;
}

echo json_encode([
    'status' => 'success',
    'tab' => $tab,
    'data' => $result['rows'] ?? [],
    'totals' => $result['totals'] ?? [],
    'pagination' => [
        'current_page' => $page,
        'per_page' => $unlimited_view ? 0 : (int) $per_page,
        'total' => $total,
        'total_pages' => $total_pages,
    ],
    'meta' => [
        'aging_date' => $aging_date,
    ],
]);
