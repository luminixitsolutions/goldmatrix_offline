<?php
/**
 * Minimal manufacturing slip (JobWork queue print) — matches Jewelsteps-style layout.
 */
session_start();
require_once __DIR__ . '/config.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);

$jwo_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$autoprint = isset($_GET['autoprint']) && $_GET['autoprint'] === '1';

if ($jwo_id <= 0) {
    die('Invalid Job Work Order ID');
}

$jwo = function_exists('getRecord') ? getRecord('SELECT * FROM tbl_jobwork_orders WHERE id = ' . $jwo_id . ' LIMIT 1') : null;
if (!$jwo) {
    die('Job Work Order not found');
}

$items = function_exists('getList') ? getList('SELECT * FROM tbl_jobwork_order_items WHERE jobwork_order_id = ' . $jwo_id . ' ORDER BY id ASC') : [];
if (!is_array($items)) {
    $items = [];
}

$sum_wt = 0.0;
$sum_qty = 0.0;
foreach ($items as $it) {
    $sum_wt += (float)($it['final_weight'] ?? 0);
    $sum_qty += (float)($it['quantity'] ?? 0);
}

$design_no = '';
$remark_line = '';
if (!empty($items[0])) {
    $design_no = trim((string)($items[0]['design_no'] ?? ''));
    $remark_line = trim((string)($items[0]['product_name'] ?? ''));
}
$jobwork_no = trim((string)($jwo['jobwork_no'] ?? ''));
if ($jobwork_no === '') {
    $jobwork_no = 'JWO-' . $jwo_id;
}
if ($design_no === '') {
    $design_no = $jobwork_no;
}
if ($remark_line === '') {
    $remark_line = trim((string)($jwo['customer_name'] ?? ''));
}

$dept_name = '';
$worker_name = '';
$did = isset($jwo['department_id']) ? (int)$jwo['department_id'] : 0;
$wid = isset($jwo['department_user_id']) ? (int)$jwo['department_user_id'] : 0;
if ($did > 0 && function_exists('getRecord')) {
    $dr = getRecord('SELECT dept_name FROM tbl_departments WHERE id = ' . $did . ' LIMIT 1');
    if ($dr && isset($dr['dept_name'])) {
        $dept_name = trim((string)$dr['dept_name']);
    }
}
if ($wid > 0 && function_exists('getRecord')) {
    $wr = getRecord('SELECT name FROM tbl_customers WHERE id = ' . $wid . ' LIMIT 1');
    if ($wr && isset($wr['name'])) {
        $worker_name = trim((string)$wr['name']);
    }
}

$dept_u = $dept_name !== '' ? strtoupper($dept_name) : '—';
$worker_u = $worker_name !== '' ? strtoupper($worker_name) : '—';

$queue_no = trim((string)($jwo['jobwork_queue_no'] ?? ''));
$sale_order_id = (int)($jwo['sale_order_id'] ?? 0);
if ($queue_no !== '') {
    $sub_line = $queue_no . '/' . $sale_order_id . '/' . $jwo_id;
} else {
    $sub_line = 'JWQ-' . $jwo_id . '/' . $sale_order_id . '/' . $jwo_id;
}

$print_dt = date('Y-m-d h:i A');
$company = isset($Proj_Title) ? (string)$Proj_Title : 'Aura Gold';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($jobwork_no); ?> — Slip</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: Roboto, 'Segoe UI', Tahoma, Arial, sans-serif;
            margin: 0;
            padding: 24px;
            color: #111;
            background: #fff;
        }
        .slip-wrap {
            max-width: 420px;
            margin: 0 auto;
        }
        .slip-title {
            text-align: center;
            font-weight: 700;
            font-size: 1.05rem;
            margin: 0 0 6px 0;
        }
        .slip-sub {
            text-align: center;
            font-size: 0.82rem;
            color: #333;
            margin: 0 0 16px 0;
        }
        .slip-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        .slip-table td {
            border: 1px solid #000;
            padding: 8px 10px;
            vertical-align: top;
        }
        .slip-table td:first-child {
            width: 38%;
            font-weight: 600;
        }
        .slip-table td.slip-val-design {
            text-align: right;
        }
        .slip-table .wt-val {
            font-weight: 700;
        }
        .slip-created {
            margin-top: 12px;
            font-size: 0.78rem;
            color: #333;
        }
        @media print {
            body { padding: 12px; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <div class="slip-wrap">
        <h1 class="slip-title"><?php echo htmlspecialchars($jobwork_no); ?> ==&gt; <?php echo htmlspecialchars($dept_u); ?> (<?php echo htmlspecialchars($worker_u); ?>)</h1>
        <p class="slip-sub"><?php echo htmlspecialchars($sub_line); ?></p>
        <table class="slip-table">
            <tr>
                <td>Date</td>
                <td><?php echo htmlspecialchars($print_dt); ?></td>
            </tr>
            <tr>
                <td>Weight</td>
                <td class="wt-val"><?php echo number_format($sum_wt, 3, '.', ''); ?></td>
            </tr>
            <tr>
                <td>Qty</td>
                <td><?php echo $sum_qty > 0 ? htmlspecialchars(number_format($sum_qty, 2, '.', '')) : ''; ?></td>
            </tr>
            <tr>
                <td>Design No</td>
                <td class="slip-val-design"><?php echo htmlspecialchars($design_no); ?></td>
            </tr>
            <tr>
                <td>Remark</td>
                <td><?php echo htmlspecialchars($remark_line); ?></td>
            </tr>
        </table>
        <p class="slip-created">CreatedBy: <?php echo htmlspecialchars($company); ?></p>
    </div>
    <?php if ($autoprint): ?>
    <script>
        window.addEventListener('load', function () {
            setTimeout(function () { window.print(); }, 250);
        });
    </script>
    <?php endif; ?>
</body>
</html>
