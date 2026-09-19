<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';
require_once __DIR__ . '/includes/department_report_build.php';

/** @var string $dept_report_scope 'insource' | 'outsource' — set by outsource-department-report.php before include */
if (!isset($dept_report_scope) || !is_string($dept_report_scope)) {
    $dept_report_scope = 'insource';
}
$dept_report_scope = strtolower(trim($dept_report_scope)) === 'outsource' ? 'outsource' : 'insource';
$dept_report_is_outsource = ($dept_report_scope === 'outsource');
$dept_report_page = $dept_report_is_outsource ? 'outsource-department-report.php' : 'department-report.php';
$dept_report_title = $dept_report_is_outsource ? 'Outsource Department Report' : 'Insource Department Report';
$dept_report_heading = 'Department Report';
$dept_report_badge = $dept_report_is_outsource ? 'Outsource' : 'Insource';
$dept_report_tabs_aria = $dept_report_is_outsource ? 'Outsource departments' : 'Insource departments';
$dept_report_empty = $dept_report_is_outsource
    ? 'No outsource departments found. Add Manufacturing Outsource departments to view this report.'
    : 'No inhouse departments found. Add Manufacturing Inhouse departments to view this report.';

$report_columns = auragold_department_report_columns();

$default_date = isset($_GET['report_date']) ? trim((string) $_GET['report_date']) : date('d-m-Y');
if (!preg_match('/^\d{2}-\d{2}-\d{4}$/', $default_date)) {
    $default_date = date('d-m-Y');
}

$departments = $dept_report_is_outsource
    ? auragold_department_report_load_outsource_departments($conn)
    : auragold_department_report_load_inhouse_departments($conn);

$active_dept_id = isset($_GET['dept']) ? (int) $_GET['dept'] : 0;
$valid_dept_ids = [];
foreach ($departments as $d) {
    $did = (int) ($d['id'] ?? 0);
    if ($did > 0) {
        $valid_dept_ids[] = $did;
    }
}
if ($active_dept_id > 0 && !in_array($active_dept_id, $valid_dept_ids, true)) {
    $active_dept_id = 0;
}
if ($active_dept_id <= 0 && $valid_dept_ids !== []) {
    $active_dept_id = (int) $valid_dept_ids[0];
}

$department_tables = auragold_department_report_build_tables($conn, $departments, $report_columns, $default_date);
if ($active_dept_id <= 0 && $department_tables !== []) {
    $active_dept_id = (int) ($department_tables[0]['id'] ?? 0);
}

?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title><?php echo htmlspecialchars($dept_report_title, ENT_QUOTES, 'UTF-8'); ?> - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?> Software</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <?php include 'header-script.php'; ?>
    <link rel="stylesheet" href="assets/css/mfg-pages-mobile.css">
</head>

<style>
:root {
    --dr-navy: #11294b;
    --dr-navy-dark: #0a1f36;
    --dr-navy-mid: #1a3a5c;
    --dr-gold: #c9a227;
    --dr-gold-soft: #f5ecd8;
    --dr-border: #d4c4a8;
    --dr-muted: #64748b;
    --dr-bg: #f4f6f9;
}
html, body {
    height: 100vh;
    overflow-x: hidden !important;
    overflow-y: hidden !important;
    background: var(--dr-bg);
}
.layout-content {
    height: calc(100vh - 60px);
    overflow: hidden;
}
.container-fluid {
    height: 100%;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    padding: 8px 10px 10px !important;
}
.dept-report-shell {
    height: 100%;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    background: #fff;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 2px 10px rgba(17, 41, 75, 0.06);
}
.dept-report-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    min-height: 44px;
    padding: 8px 12px;
    background: linear-gradient(180deg, #fdf8f0 0%, #f5ecd8 100%);
    border-bottom: 2px solid var(--dr-gold);
}
.dept-report-heading {
    margin: 0;
    font-size: 1rem;
    font-weight: 700;
    color: var(--dr-navy);
}
.dept-report-heading span {
    display: inline-block;
    margin-left: 8px;
    padding: 2px 10px;
    border-radius: 999px;
    background: var(--dr-navy);
    color: #fff;
    font-size: 0.7rem;
    font-weight: 600;
    letter-spacing: 0.03em;
    vertical-align: middle;
}
.dept-report-toolbar {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
}
.dept-report-toolbar form {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin: 0;
}
.toolbar-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--dr-navy);
}
.date-input-wrap {
    display: inline-flex;
    align-items: center;
    border: 1px solid var(--dr-border);
    background: #fff;
    border-radius: 8px;
    padding: 0 8px;
    height: 32px;
}
.date-input-wrap input {
    border: 0;
    outline: 0;
    font-size: 13px;
    font-weight: 600;
    color: var(--dr-navy);
    width: 96px;
    background: transparent;
}
.date-input-wrap .feather {
    width: 15px;
    height: 15px;
    color: var(--dr-gold);
}
.btn-mini {
    height: 32px;
    border: 1px solid var(--dr-navy);
    color: #fff;
    background: linear-gradient(180deg, var(--dr-navy-mid) 0%, var(--dr-navy) 100%);
    border-radius: 8px;
    padding: 0 14px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}
.btn-mini:hover {
    background: var(--dr-navy-dark);
    color: #fff;
}
.btn-mini.btn-outline {
    background: #fff;
    color: var(--dr-navy);
    border-color: var(--dr-border);
}
.btn-mini.btn-outline:hover {
    background: var(--dr-gold-soft);
    border-color: var(--dr-gold);
    color: var(--dr-navy);
}
.btn-icon-mini {
    width: 32px;
    height: 32px;
    border: 1px solid var(--dr-border);
    background: #fff;
    color: var(--dr-navy);
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
}
.btn-icon-mini:hover {
    background: var(--dr-gold-soft);
    border-color: var(--dr-gold);
}
.export-dropdown {
    position: relative;
}
.export-dropdown .dropdown-menu {
    min-width: 140px;
    font-size: 13px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 8px 20px rgba(17, 41, 75, 0.12);
}
.export-dropdown .dropdown-item {
    padding: 6px 12px;
    font-weight: 600;
    color: var(--dr-navy);
}
.export-dropdown .dropdown-item:hover {
    background: var(--dr-gold-soft);
    color: var(--dr-navy);
}
.dept-report-tabs-bar {
    display: flex;
    align-items: stretch;
    gap: 0;
    padding: 0 10px;
    border-bottom: 1px solid #e2e8f0;
    background: #fafbfd;
    overflow-x: auto;
    flex: 0 0 auto;
}
.dept-report-tabs {
    display: inline-flex;
    align-items: stretch;
    gap: 0;
    min-width: max-content;
}
.dept-tab-btn {
    appearance: none;
    border: 0;
    border-bottom: 3px solid transparent;
    background: transparent;
    color: var(--dr-muted);
    font-size: 0.82rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    padding: 12px 16px;
    cursor: pointer;
    white-space: nowrap;
    transition: color 0.15s, border-color 0.15s, background 0.15s;
}
.dept-tab-btn:hover {
    color: var(--dr-navy);
    background: #fff;
}
.dept-tab-btn.active {
    color: var(--dr-navy);
    border-bottom-color: var(--dr-gold);
    background: #fff;
}
.dept-report-scroll {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 12px 12px 16px;
    background: var(--dr-bg);
}
.dept-panel {
    display: none;
}
.dept-panel.active {
    display: block;
}
.dept-panel-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin: 0 0 10px;
}
.dept-panel-title h2 {
    margin: 0;
    font-size: 0.95rem;
    font-weight: 800;
    letter-spacing: 0.04em;
    color: var(--dr-navy);
}
.dept-panel-badge {
    font-size: 0.7rem;
    font-weight: 700;
    color: var(--dr-navy);
    background: var(--dr-gold-soft);
    border: 1px solid var(--dr-border);
    border-radius: 999px;
    padding: 3px 10px;
}
.table-outer {
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 1px 4px rgba(17, 41, 75, 0.04);
}
.table-wrap {
    position: relative;
    overflow-x: auto;
    overflow-y: visible;
    max-width: 100%;
}
.dept-report-table {
    width: max-content;
    min-width: 100%;
    border-collapse: collapse;
    margin: 0;
    font-size: 12px;
}
.dept-report-table thead th {
    background: var(--dr-navy);
    border-right: 1px solid rgba(255,255,255,0.12);
    border-bottom: 2px solid var(--dr-gold);
    color: #fff;
    font-weight: 700;
    padding: 8px 12px;
    white-space: nowrap;
    text-align: right;
}
.dept-report-table thead th:first-child {
    text-align: left;
    min-width: 120px;
}
.dept-report-table tbody td {
    border-right: 1px solid #eef2f7;
    border-bottom: 1px solid #eef2f7;
    padding: 7px 12px;
    color: #334155;
    text-align: right;
    white-space: nowrap;
}
.dept-report-table tbody tr:nth-child(even) td {
    background: #f8fafc;
}
.dept-report-table tbody tr:hover td {
    background: #fdf8f0;
}
.dept-report-table tbody td:first-child {
    text-align: left;
    font-weight: 600;
    color: var(--dr-navy);
}
.dept-report-table tfoot td {
    border-right: 1px solid #e2e8f0;
    border-top: 2px solid var(--dr-gold);
    background: var(--dr-gold-soft);
    padding: 8px 12px;
    font-weight: 800;
    color: var(--dr-navy);
    text-align: right;
    white-space: nowrap;
}
.dept-report-table tfoot td:first-child {
    text-align: left;
}
.dept-empty {
    padding: 36px 16px;
    text-align: center;
    color: var(--dr-muted);
    font-size: 0.9rem;
    background: #fff;
    border: 1px dashed #d4c4a8;
    border-radius: 10px;
}
@media print {
    html, body, .layout-content, .container-fluid { height: auto !important; overflow: visible !important; }
    .dept-report-toolbar, .dept-report-tabs-bar, .layout-navbar, .sidenav { display: none !important; }
    .dept-panel { display: block !important; page-break-inside: avoid; margin-bottom: 18px; }
}
</style>

<body class="mfg-page dept-report-page">
<?php include 'sidebar.php'; ?>

<div class="layout-content">
    <div class="container-fluid flex-grow-1" style="padding-top:0;padding-bottom:0;">
        <div class="dept-report-shell">
            <div class="dept-report-header">
                <h1 class="dept-report-heading"><?php echo htmlspecialchars($dept_report_heading, ENT_QUOTES, 'UTF-8'); ?> <span><?php echo htmlspecialchars($dept_report_badge, ENT_QUOTES, 'UTF-8'); ?></span></h1>
                <div class="dept-report-toolbar">
                    <form method="get" action="<?php echo htmlspecialchars($dept_report_page, ENT_QUOTES, 'UTF-8'); ?>" id="deptReportFilterForm">
                        <input type="hidden" name="dept" id="deptReportActiveDept" value="<?php echo (int) $active_dept_id; ?>">
                        <span class="toolbar-label">Date</span>
                        <span class="date-input-wrap">
                            <input type="text" name="report_date" id="reportDateInput" value="<?php echo htmlspecialchars($default_date, ENT_QUOTES, 'UTF-8'); ?>" placeholder="dd-mm-yyyy" autocomplete="off" pattern="\d{2}-\d{2}-\d{4}" title="dd-mm-yyyy">
                            <i class="feather icon-calendar"></i>
                        </span>
                        <button type="submit" class="btn-mini">Apply</button>
                    </form>

                    <div class="dropdown export-dropdown">
                        <button class="btn-mini btn-outline dropdown-toggle" type="button" data-toggle="dropdown" aria-expanded="false">
                            Export <i class="feather icon-chevron-down" style="width:14px;height:14px;"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right">
                            <a class="dropdown-item" href="#" onclick="window.print(); return false;">Print / PDF</a>
                            <a class="dropdown-item" href="#" id="deptReportExportExcel" onclick="return exportDeptReportExcel(false);">Excel (this department)</a>
                            <a class="dropdown-item" href="#" onclick="return exportDeptReportExcel(true);">Excel (all departments)</a>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($department_tables !== []): ?>
            <div class="dept-report-tabs-bar" role="tablist" aria-label="<?php echo htmlspecialchars($dept_report_tabs_aria, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="dept-report-tabs">
                    <?php foreach ($department_tables as $i => $block):
                        $bid = (int) ($block['id'] ?? 0);
                        $isActive = ($bid === (int) $active_dept_id) || ($active_dept_id <= 0 && $i === 0);
                    ?>
                        <button type="button"
                            class="dept-tab-btn<?php echo $isActive ? ' active' : ''; ?>"
                            role="tab"
                            id="deptTabBtn_<?php echo $bid; ?>"
                            data-dept-id="<?php echo $bid; ?>"
                            aria-selected="<?php echo $isActive ? 'true' : 'false'; ?>"
                            aria-controls="deptPanel_<?php echo $bid; ?>">
                            <?php echo htmlspecialchars($block['title'], ENT_QUOTES, 'UTF-8'); ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="dept-report-scroll">
                <?php if ($department_tables === []): ?>
                    <div class="dept-empty"><?php echo htmlspecialchars($dept_report_empty, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php else: ?>
                    <?php foreach ($department_tables as $i => $block):
                        $bid = (int) ($block['id'] ?? 0);
                        $isActive = ($bid === (int) $active_dept_id) || ($active_dept_id <= 0 && $i === 0);
                        $rowCount = is_array($block['rows'] ?? null) ? count($block['rows']) : 0;
                    ?>
                        <section class="dept-panel<?php echo $isActive ? ' active' : ''; ?>"
                            id="deptPanel_<?php echo $bid; ?>"
                            role="tabpanel"
                            aria-labelledby="deptTabBtn_<?php echo $bid; ?>"
                            data-dept-id="<?php echo $bid; ?>"
                            <?php echo $isActive ? '' : 'hidden'; ?>>
                            <div class="dept-panel-title">
                                <h2><?php echo htmlspecialchars($block['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
                                <span class="dept-panel-badge"><?php echo (int) $rowCount; ?> row<?php echo $rowCount === 1 ? '' : 's'; ?></span>
                            </div>
                            <div class="table-outer">
                                <div class="table-wrap">
                                    <table class="dept-report-table">
                                        <thead>
                                            <tr>
                                                <th scope="col">User / Worker</th>
                                                <?php foreach ($report_columns as $col): ?>
                                                    <th scope="col"><?php echo htmlspecialchars($col['label'], ENT_QUOTES, 'UTF-8'); ?></th>
                                                <?php endforeach; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($block['rows'] as $row): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <?php foreach ($report_columns as $col): ?>
                                                        <td><?php echo htmlspecialchars(auragold_format_dept_report_num((float) ($row[$col['key']] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td>Sum:</td>
                                                <?php foreach ($report_columns as $col): ?>
                                                    <td><?php echo htmlspecialchars(auragold_format_dept_report_num((float) ($block['sums'][$col['key']] ?? 0)), ENT_QUOTES, 'UTF-8'); ?></td>
                                                <?php endforeach; ?>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </section>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'footer-script.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('reportDateInput');
    if (input && typeof $ !== 'undefined' && $.fn.datepicker) {
        $(input).datepicker({
            format: 'dd-mm-yyyy',
            autoclose: true,
            todayHighlight: true
        }).on('changeDate', function () {
            $(this).datepicker('hide');
        });
    }

    var tabBtns = document.querySelectorAll('.dept-tab-btn');
    var panels = document.querySelectorAll('.dept-panel');
    var hiddenDept = document.getElementById('deptReportActiveDept');

    function activateDept(deptId) {
        var id = String(deptId || '');
        tabBtns.forEach(function (btn) {
            var on = btn.getAttribute('data-dept-id') === id;
            btn.classList.toggle('active', on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        panels.forEach(function (panel) {
            var on = panel.getAttribute('data-dept-id') === id;
            panel.classList.toggle('active', on);
            if (on) panel.removeAttribute('hidden');
            else panel.setAttribute('hidden', 'hidden');
        });
        if (hiddenDept) hiddenDept.value = id;
        try {
            var url = new URL(window.location.href);
            url.searchParams.set('dept', id);
            window.history.replaceState({}, '', url.toString());
        } catch (e) {}
    }

    tabBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            activateDept(btn.getAttribute('data-dept-id'));
        });
    });
});

function exportDeptReportExcel(allDepts) {
    var dateEl = document.getElementById('reportDateInput');
    var deptEl = document.getElementById('deptReportActiveDept');
    var reportDate = dateEl ? String(dateEl.value || '').trim() : '';
    var dept = deptEl ? String(deptEl.value || '').trim() : '';
    var qs = new URLSearchParams();
    qs.set('scope', <?php echo json_encode($dept_report_scope, JSON_UNESCAPED_SLASHES); ?>);
    if (reportDate) qs.set('report_date', reportDate);
    if (allDepts) {
        qs.set('all', '1');
    } else if (dept) {
        qs.set('dept', dept);
    }
    window.location.href = 'ajax/export-department-report-excel.php?' + qs.toString();
    return false;
}
</script>
</body>
</html>
