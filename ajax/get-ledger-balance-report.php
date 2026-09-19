<?php

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_require_login.php';
require_once __DIR__ . '/../includes/ensure_customer_ledger_branch_column.php';
require_once __DIR__ . '/../includes/auragold_ledger_balance_report_data.php';

auragold_require_login_or_exit();
auragold_ensure_customer_ledger_branch_column($conn);

header('Content-Type: application/json');

$search = isset($_GET['search']) ? esc($_GET['search']) : '';
$from_date = isset($_GET['from_date']) ? trim((string) esc($_GET['from_date'])) : '';
$to_date = isset($_GET['to_date']) ? trim((string) esc($_GET['to_date'])) : '';
$customers_only = isset($_GET['customers_only']) && ($_GET['customers_only'] === '1' || $_GET['customers_only'] === 'true');

$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}

$raw_per = isset($_GET['per_page']) ? $_GET['per_page'] : 10;
if ($raw_per === 'all' || $raw_per === '-1') {
    $per_page = PHP_INT_MAX;
} else {
    $per_page = max(1, (int) $raw_per);
}

$offset = 0;
if ($per_page < PHP_INT_MAX - 1000) {
    $offset = ($page - 1) * $per_page;
}

try {
    $packed = auragold_ledger_balance_report_collect($conn, [
        'search' => $search,
        'from_date' => $from_date,
        'to_date' => $to_date,
        'customers_only' => $customers_only,
        'branch_id' => isset($_GET['branch_id']) ? (int) $_GET['branch_id'] : 0,
    ]);
    $ledger_data = $packed['rows'];
    $total_balance_amount = (float) ($packed['totals']['total_balance_amount'] ?? 0);
    $total_balance = (float) ($packed['totals']['total_balance'] ?? 0);

    $total = count($ledger_data);
    if ($per_page >= PHP_INT_MAX - 1000) {
        $pagination_per_page_display = $total > 0 ? $total : 1;
        $total_pages = 1;
        $paginated_data = $ledger_data;
    } else {
        $pagination_per_page_display = $per_page;
        $total_pages = $total > 0 ? (int) ceil($total / $per_page) : 1;
        $paginated_data = array_slice($ledger_data, $offset, $per_page);
    }

    echo json_encode([
        'status' => 'success',
        'data' => $paginated_data,
        'totals' => [
            'total_balance_amount' => $total_balance_amount,
            'total_balance' => $total_balance,
        ],
        'pagination' => [
            'current_page' => $page,
            'per_page' => $pagination_per_page_display,
            'total' => $total,
            'total_pages' => $total_pages,
        ],
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}
