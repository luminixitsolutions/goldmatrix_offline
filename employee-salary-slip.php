<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auragold_employee_management_menu.php';
require_once __DIR__ . '/includes/auragold_employee_management_schema.php';

if (empty($_SESSION['Admin']) && empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
if (!auragold_employee_management_can_view_page('employee_salary')) {
    header('Location: dashboard.php');
    exit;
}

$payrollId = (int) ($_GET['id'] ?? 0);
$branchId = auragold_em_resolve_branch_id();
auragold_em_ensure_tables($conn);
auragold_em_sync_users_to_employees($conn, $branchId);

$payroll = $payrollId > 0 ? getRecord(
    "SELECT p.*, e.employee_code, e.first_name, e.last_name, e.email, e.phone,
            d.name AS department_name, g.name AS designation_name
     FROM tbl_employee_payroll p
     INNER JOIN tbl_employees e ON e.id = p.employee_id
     LEFT JOIN tbl_employee_departments d ON d.id = e.department_id
     LEFT JOIN tbl_employee_designations g ON g.id = e.designation_id
     WHERE p.id = $payrollId
       AND p.branch_id = " . (int) $branchId . "
       AND p.record_status = 1
     LIMIT 1"
) : null;

if (!$payroll) {
    http_response_code(404);
    exit('Salary slip not found.');
}

$access = auragold_em_assert_employee_access(
    $conn,
    $branchId,
    (int) ($payroll['employee_id'] ?? 0)
);
if (empty($access['ok'])) {
    http_response_code(403);
    exit('You are not allowed to view this salary slip.');
}

$branch = function_exists('getRecordMaster')
    ? getRecordMaster('SELECT name FROM tbl_branches WHERE id = ' . (int) $branchId . ' LIMIT 1')
    : null;
$branchName = trim((string) ($branch['name'] ?? ''));
$employeeName = trim((string) ($payroll['first_name'] ?? '') . ' ' . (string) ($payroll['last_name'] ?? ''));
$monthRaw = (string) ($payroll['payroll_month'] ?? '');
$monthTime = preg_match('/^\d{4}-\d{2}$/', $monthRaw) ? strtotime($monthRaw . '-01') : false;
$monthLabel = $monthTime ? date('F Y', $monthTime) : $monthRaw;
$basic = (float) ($payroll['basic_salary'] ?? 0);
$allowances = (float) ($payroll['allowances'] ?? 0);
$deductions = (float) ($payroll['deductions'] ?? 0);
$gross = $basic + $allowances;
$net = (float) ($payroll['net_salary'] ?? ($gross - $deductions));

$h = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
$money = static function ($value): string {
    return number_format((float) $value, 2, '.', ',');
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Salary Slip — <?php echo $h($employeeName); ?> — <?php echo $h($monthLabel); ?></title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef2f7; color: #172033; font-family: Arial, sans-serif; }
        .actions { width: 820px; max-width: calc(100% - 30px); margin: 20px auto 10px; text-align: right; }
        .actions button { border: 0; border-radius: 6px; background: #112f55; color: #fff; padding: 10px 18px; font-weight: 700; cursor: pointer; }
        .slip { width: 820px; max-width: calc(100% - 30px); margin: 0 auto 24px; background: #fff; border: 1px solid #ccd5e0; padding: 30px; }
        .header { display: flex; justify-content: space-between; gap: 20px; padding-bottom: 18px; border-bottom: 2px solid #112f55; }
        .brand { font-size: 25px; font-weight: 800; color: #b58b08; }
        .sub { color: #64748b; font-size: 12px; margin-top: 5px; }
        .title { text-align: right; }
        .title h1 { margin: 0; color: #112f55; font-size: 24px; }
        .title div { margin-top: 7px; font-weight: 700; }
        .info { display: grid; grid-template-columns: 1fr 1fr; border: 1px solid #d9e0e8; margin-top: 22px; }
        .info div { padding: 10px 12px; border-bottom: 1px solid #e5e9ef; }
        .info div:nth-child(odd) { border-right: 1px solid #e5e9ef; }
        .label { display: inline-block; min-width: 120px; color: #64748b; font-size: 12px; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; margin-top: 22px; }
        th, td { padding: 11px 12px; border: 1px solid #d9e0e8; text-align: left; }
        th { background: #112f55; color: #fff; font-size: 12px; text-transform: uppercase; }
        td.amount { text-align: right; font-variant-numeric: tabular-nums; }
        tr.net td { background: #f4f7fb; font-size: 16px; font-weight: 800; color: #112f55; }
        .footer { margin-top: 55px; display: flex; justify-content: space-between; gap: 30px; }
        .signature { width: 220px; padding-top: 8px; border-top: 1px solid #637083; text-align: center; font-size: 12px; }
        .note { margin-top: 25px; color: #64748b; font-size: 11px; text-align: center; }
        @media print {
            @page { size: A4; margin: 12mm; }
            body { background: #fff; }
            .actions { display: none; }
            .slip { width: 100%; max-width: none; margin: 0; border: 0; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="actions"><button type="button" onclick="window.print()">Print Salary Slip</button></div>
    <main class="slip">
        <div class="header">
            <div>
                <div class="brand"><?php echo $h(auragold_app_name()); ?></div>
                <div class="sub"><?php echo $h($branchName !== '' ? $branchName : ('Branch #' . $branchId)); ?></div>
            </div>
            <div class="title">
                <h1>Salary Slip</h1>
                <div><?php echo $h($monthLabel); ?></div>
            </div>
        </div>

        <section class="info">
            <div><span class="label">Employee</span><?php echo $h($employeeName); ?></div>
            <div><span class="label">Employee Code</span><?php echo $h($payroll['employee_code'] ?? '—'); ?></div>
            <div><span class="label">Department</span><?php echo $h(($payroll['department_name'] ?? '') ?: '—'); ?></div>
            <div><span class="label">Designation</span><?php echo $h(($payroll['designation_name'] ?? '') ?: '—'); ?></div>
            <div><span class="label">Payment Date</span><?php echo $h(!empty($payroll['payment_date']) ? date('d M Y', strtotime((string) $payroll['payment_date'])) : '—'); ?></div>
            <div><span class="label">Status</span><?php echo $h($payroll['status'] ?? 'Draft'); ?></div>
        </section>

        <table>
            <thead><tr><th>Description</th><th style="text-align:right;">Amount</th></tr></thead>
            <tbody>
                <tr><td>Basic Salary</td><td class="amount"><?php echo $money($basic); ?></td></tr>
                <tr><td>Allowances</td><td class="amount"><?php echo $money($allowances); ?></td></tr>
                <tr><td><strong>Gross Salary</strong></td><td class="amount"><strong><?php echo $money($gross); ?></strong></td></tr>
                <tr><td>Deductions (including approved advances)</td><td class="amount"><?php echo $money($deductions); ?></td></tr>
                <tr class="net"><td>Net Salary</td><td class="amount"><?php echo $money($net); ?></td></tr>
            </tbody>
        </table>

        <div class="footer">
            <div class="signature">Employee Signature</div>
            <div class="signature">Authorized Signature</div>
        </div>
        <div class="note">This is a computer-generated salary slip.</div>
    </main>
</body>
</html>
