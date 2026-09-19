<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auragold_require_login.php';
auragold_require_login_or_exit();
require_once __DIR__ . '/../includes/ensure_metal_amount_conversion.php';

header('Content-Type: application/json; charset=utf-8');

$cid = isset($_GET['customer_id']) ? (int) $_GET['customer_id'] : 0;
$dir = isset($_GET['direction']) ? strtolower(trim((string) $_GET['direction'])) : '';
if ($cid <= 0) {
    echo json_encode(['status' => 'ok', 'rows' => []]);
    exit;
}

auragold_ensure_metal_amount_conversion_table($conn);
$dands = "direction IN ('metal_to_amount','amount_to_metal')";
if (in_array($dir, ['metal_to_amount', 'amount_to_metal'], true)) {
    $dands = "direction = '" . esc($dir) . "'";
}

$mac_cols = 'id, trans_no, trans_date, direction, metal_type, metal_weight, rate, amount, comment, created_at';
if (function_exists('auragold_tbl_has_column') && auragold_tbl_has_column($conn, 'tbl_metal_amount_conversions', 'against_item_label')) {
    $mac_cols .= ', against_item_label';
}
$rows = getList("
    SELECT $mac_cols
    FROM tbl_metal_amount_conversions
    WHERE status = 1 AND customer_id = " . (int) $cid . " AND " . $dands . "
    ORDER BY id DESC
    LIMIT 200
");
if (!is_array($rows)) {
    $rows = [];
}

echo json_encode(['status' => 'ok', 'rows' => $rows]);
