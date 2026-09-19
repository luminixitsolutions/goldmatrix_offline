<?php
session_start();
require_once '../config.php';
require_once __DIR__ . '/../includes/auragold_voucher_list_query.php';

header('Content-Type: application/json');

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$limit = isset($_GET['limit']) ? min(100, max(1, (int) $_GET['limit'])) : 10;
$offset = ($page - 1) * $limit;
$filters = auragold_voucher_list_filter_from_request();
$where = auragold_voucher_list_build_where($conn, 'rv', $filters);

$branchJoin = '';
$branchSelect = "'' AS branch_name";
if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_receipt_vouchers', 'branch_id')) {
    $branchSelect = 'COALESCE(b.name, \'\') AS branch_name';
    $branchJoin = ' LEFT JOIN tbl_branches b ON b.id = rv.branch_id';
}

$countRow = getRecord("SELECT COUNT(*) AS cnt FROM tbl_receipt_vouchers rv WHERE $where");
$total = (int) ($countRow['cnt'] ?? 0);

$vouchers = getList("
    SELECT rv.*, $branchSelect,
           (SELECT COUNT(*) FROM tbl_receipt_voucher_items WHERE voucher_id = rv.id) AS item_count
    FROM tbl_receipt_vouchers rv
    $branchJoin
    WHERE $where
    ORDER BY rv.id DESC
    LIMIT $limit OFFSET $offset
");

if (is_array($vouchers)) {
    foreach ($vouchers as &$voucher) {
        $vid = (int) ($voucher['id'] ?? 0);
        $items = getList("SELECT * FROM tbl_receipt_voucher_items WHERE voucher_id = $vid ORDER BY id ASC");
        $voucher['items'] = is_array($items) ? $items : [];
    }
    unset($voucher);
}

echo json_encode([
    'status' => 'success',
    'vouchers' => is_array($vouchers) ? $vouchers : [],
    'total' => $total,
    'page' => $page,
    'limit' => $limit,
]);
