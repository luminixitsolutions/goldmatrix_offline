<?php
/**
 * Search job work orders for Jobwork Queue page (queue no, job no, customer, sale order, id).
 */
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$q = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
if (strlen($q) < 1) {
    echo json_encode(['ok' => true, 'items' => []]);
    exit;
}

$tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_jobwork_orders'");
if (!$tbl || mysqli_num_rows($tbl) === 0) {
    if ($tbl) {
        mysqli_free_result($tbl);
    }
    echo json_encode(['ok' => true, 'items' => []]);
    exit;
}
mysqli_free_result($tbl);

$esc = mysqli_real_escape_string($conn, $q);
$like_inner = str_replace(['%', '_'], ['\\%', '\\_'], $esc);
$likepat = mysqli_real_escape_string($conn, '%' . $like_inner . '%');

$has_jq = false;
$cq = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_orders LIKE 'jobwork_queue_no'");
if ($cq && mysqli_num_rows($cq) > 0) {
    $has_jq = true;
}
if ($cq) {
    mysqli_free_result($cq);
}

$has_mfg = @mysqli_query($conn, "SHOW COLUMNS FROM tbl_jobwork_orders LIKE 'manufacturing_time_seconds'");
$mfg_ok = ($has_mfg && mysqli_num_rows($has_mfg) > 0);
if ($has_mfg) {
    mysqli_free_result($has_mfg);
}

$parts = [
    "j.jobwork_no LIKE '$likepat'",
    "j.customer_name LIKE '$likepat'",
    "j.sale_order_no LIKE '$likepat'",
    "CAST(j.id AS CHAR) = '" . $esc . "'",
];
if ($has_jq) {
    $parts[] = "j.jobwork_queue_no LIKE '$likepat'";
}

$where = '(' . implode(' OR ', $parts) . ')';

$mfg_sel = $mfg_ok ? 'COALESCE(j.manufacturing_time_seconds,0)' : '0';
$jq_sel = $has_jq ? 'IFNULL(j.jobwork_queue_no,\'\')' : "''";

$sql = "
SELECT j.id, j.jobwork_no, j.sale_order_no, j.customer_name, j.department_id, d.dept_name,
    j.department_user_id, c.name AS worker_name,
    $mfg_sel AS manufacturing_time_seconds,
    $jq_sel AS jobwork_queue_no,
    (SELECT ji.product_name FROM tbl_jobwork_order_items ji WHERE ji.jobwork_order_id = j.id ORDER BY ji.id ASC LIMIT 1) AS first_product
FROM tbl_jobwork_orders j
LEFT JOIN tbl_departments d ON j.department_id = d.id
LEFT JOIN tbl_customers c ON j.department_user_id = c.id
WHERE $where
ORDER BY j.id DESC
LIMIT 30
";

$items = function_exists('getList') ? @getList($sql) : [];
if (!is_array($items)) {
    $items = [];
}

if ($has_jq && function_exists('ensureJobworkQueueNoForOrder')) {
    foreach ($items as &$row) {
        $jid = (int)($row['id'] ?? 0);
        if ($jid > 0) {
            $qn = ensureJobworkQueueNoForOrder($conn, $jid);
            if ($qn !== null && $qn !== '') {
                $row['jobwork_queue_no'] = $qn;
            }
        }
    }
    unset($row);
}

$out = [];
foreach ($items as $row) {
    $out[] = [
        'id' => (int)($row['id'] ?? 0),
        'jobwork_no' => trim((string)($row['jobwork_no'] ?? '')),
        'sale_order_no' => trim((string)($row['sale_order_no'] ?? '')),
        'customer_name' => trim((string)($row['customer_name'] ?? '')),
        'department_id' => (int)($row['department_id'] ?? 0),
        'dept_name' => trim((string)($row['dept_name'] ?? '')),
        'department_user_id' => (int)($row['department_user_id'] ?? 0),
        'worker_name' => trim((string)($row['worker_name'] ?? '')),
        'manufacturing_time_seconds' => (int)($row['manufacturing_time_seconds'] ?? 0),
        'jobwork_queue_no' => trim((string)($row['jobwork_queue_no'] ?? '')),
        'first_product' => trim((string)($row['first_product'] ?? '')),
    ];
}

echo json_encode(['ok' => true, 'items' => $out], JSON_UNESCAPED_UNICODE);
