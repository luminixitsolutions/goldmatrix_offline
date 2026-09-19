<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$adminId = isset($_SESSION['Admin']['id']) ? (int) $_SESSION['Admin']['id'] : (isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0);
if ($adminId < 1) {
    echo json_encode(['ok' => false, 'rows' => [], 'message' => 'Please log in.']);
    exit;
}

$chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_manufacturing_closing'");
if (!$chk || mysqli_num_rows($chk) === 0) {
    if ($chk) {
        mysqli_free_result($chk);
    }
    echo json_encode(['ok' => true, 'rows' => []]);
    exit;
}
mysqli_free_result($chk);

$branchJoin = '';
$branchSel = ', NULL AS branch_name';
$tb = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_branches'");
if ($tb && mysqli_num_rows($tb) > 0) {
    mysqli_free_result($tb);
    $branchJoin = ' LEFT JOIN tbl_branches b ON b.id = c.branch_id';
    $branchSel = ', b.name AS branch_name';
}

$sql = "
SELECT c.id, c.department_id, c.department_user_id, c.branch_id, c.closing_date,
    c.loss_wt, c.gold_rate, c.gold_loss_value, c.purity_per, c.purity_wt, c.work_done_kg, c.avg_loss_per_kg,
    c.inward_wt, c.outward_wt, c.recovery_wt, c.closing_wt, c.production_wt,
    c.difference_loss, c.final_loss, c.loss_percent,
    c.closed_jobs, c.processed_jobs, c.total_jobs, c.metal_weight, c.created_at,
    d.dept_name AS department_name,
    u.name AS user_name
    {$branchSel}
FROM tbl_manufacturing_closing c
LEFT JOIN tbl_departments d ON d.id = c.department_id
LEFT JOIN tbl_customers u ON u.id = c.department_user_id
{$branchJoin}
ORDER BY c.closing_date DESC, c.id DESC
LIMIT 500
";

$rows = function_exists('getList') ? @getList($sql) : [];
if (!is_array($rows)) {
    $rows = [];
}

echo json_encode(['ok' => true, 'rows' => $rows], JSON_UNESCAPED_UNICODE);
