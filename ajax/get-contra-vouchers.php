<?php
session_start();
require_once '../config.php';

header('Content-Type: application/json');

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$limit = isset($_GET['limit']) ? min(100, max(1, (int) $_GET['limit'])) : 10;
$offset = ($page - 1) * $limit;
$search = isset($_GET['q']) ? trim((string) $_GET['q']) : '';

$t = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_contra_vouchers'");
if (!$t || mysqli_num_rows($t) === 0) {
    echo json_encode(['status' => 'success', 'vouchers' => [], 'total' => 0, 'page' => $page, 'limit' => $limit]);
    exit;
}

$where = '1=1';
if ($search !== '') {
    $esc = mysqli_real_escape_string($conn, $search);
    $where .= " AND (voucher_no LIKE '%$esc%' OR comment LIKE '%$esc%')";
}

$countRow = getRecord("SELECT COUNT(*) AS cnt FROM tbl_contra_vouchers WHERE $where");
$total = (int) ($countRow['cnt'] ?? 0);

$vouchers = getList("
    SELECT id, voucher_no, voucher_date, total_amount, comment, status, created_at
    FROM tbl_contra_vouchers
    WHERE $where
    ORDER BY id DESC
    LIMIT $limit OFFSET $offset
");

echo json_encode([
    'status' => 'success',
    'vouchers' => is_array($vouchers) ? $vouchers : [],
    'total' => $total,
    'page' => $page,
    'limit' => $limit,
]);
