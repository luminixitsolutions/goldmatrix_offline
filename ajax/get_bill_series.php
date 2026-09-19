<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['Admin']['id'])) {
    echo json_encode(['status' => false, 'message' => 'Unauthorized']);
    exit;
}

// If tbl_bill_series does not exist yet, return empty list
$tableCheck = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_bill_series'");
if (!$tableCheck || mysqli_num_rows($tableCheck) === 0) {
    echo json_encode(['status' => true, 'data' => []]);
    exit;
}

auragold_ensure_branch_id_on_settings_tables($conn);
$settings_bid = auragold_settings_branch_id();
$has_bs_branch = auragold_tbl_has_column($conn, 'tbl_bill_series', 'branch_id');
$scope_sql = ($has_bs_branch && $settings_bid > 0) ? (' AND bs.branch_id = ' . (int) $settings_bid) : '';

$list = getList("
    SELECT bs.id, bs.voucher_type_id, bs.branch_id, bs.prefix, bs.suffix,
           bs.start_count, bs.status,
           vt.name AS voucher_type_name
    FROM tbl_bill_series bs
    LEFT JOIN tbl_voucher_types vt ON vt.id = bs.voucher_type_id AND vt.status = 1
    WHERE bs.status = 1 $scope_sql
    ORDER BY bs.id ASC
");

$out = [];
foreach ($list as $row) {
    $voucher_type_id = (int)($row['voucher_type_id'] ?? 0);
    $count = countBillsForVoucherType($conn, $voucher_type_id);
    $row['is_locked'] = ($count > 0);
    $out[] = $row;
}
if (function_exists('auragold_enrich_rows_branch_name_from_registry') && count($out) > 0) {
    auragold_enrich_rows_branch_name_from_registry($out);
}

echo json_encode([
    'status' => true,
    'data'   => $out
]);
