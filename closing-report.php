<?php
session_start();
require_once __DIR__ . '/config.php';

$adminId = isset($_SESSION['Admin']['id']) ? (int) $_SESSION['Admin']['id'] : (isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0);

/** Masters — advance filter (same pattern as manufacturing-process.php) */
$departments = [];
$tblDept = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_departments'");
if ($tblDept && mysqli_num_rows($tblDept) > 0) {
    mysqli_free_result($tblDept);
    $departments = function_exists('getList') ? @getList("SELECT id, dept_name FROM tbl_departments WHERE status = 1 ORDER BY dept_name ASC") : [];
}
if (!is_array($departments)) {
    $departments = [];
}

$job_worker_type_id = 0;
$jw_result = @mysqli_query($conn, "SELECT id FROM tbl_customer_types WHERE LOWER(name) = 'job worker' AND status = 1 LIMIT 1");
if ($jw_result && mysqli_num_rows($jw_result) > 0) {
    $jw_row = mysqli_fetch_assoc($jw_result);
    $job_worker_type_id = (int) $jw_row['id'];
}
if ($jw_result) {
    mysqli_free_result($jw_result);
}

$department_users = [];
foreach ($departments as $dept) {
    $dept_id = (int) $dept['id'];
    $department_users[$dept_id] = [];
    $map_tbl = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_department_user_map'");
    if ($map_tbl && mysqli_num_rows($map_tbl) > 0) {
        mysqli_free_result($map_tbl);
        $users_query = "
            SELECT c.id, c.name 
            FROM tbl_customers c
            INNER JOIN tbl_department_user_map dum ON c.id = dum.user_id AND dum.status = 1
            WHERE dum.department_id = $dept_id 
            AND c.status = 1
            " . ($job_worker_type_id > 0 ? "AND c.customer_type_id = $job_worker_type_id" : '') . '
            ORDER BY c.name ASC
        ';
        $users_result = @mysqli_query($conn, $users_query);
        if ($users_result) {
            while ($user_row = mysqli_fetch_assoc($users_result)) {
                $department_users[$dept_id][] = $user_row;
            }
            mysqli_free_result($users_result);
        }
    } elseif ($map_tbl) {
        mysqli_free_result($map_tbl);
    }
}

$branches = function_exists('getListMaster')
    ? @getListMaster("SELECT id, name FROM tbl_branches WHERE status = 1 ORDER BY name ASC")
    : (function_exists('getList') ? @getList("SELECT id, name FROM tbl_branches WHERE status = 1 ORDER BY name ASC") : []);
if (!is_array($branches)) {
    $branches = [];
}

$f_date_from = isset($_GET['f_date_from']) ? trim((string) $_GET['f_date_from']) : '';
$f_date_to = isset($_GET['f_date_to']) ? trim((string) $_GET['f_date_to']) : '';
$f_branch_id = isset($_GET['f_branch_id']) ? (int) $_GET['f_branch_id'] : 0;
$f_department_id = isset($_GET['f_department_id']) ? (int) $_GET['f_department_id'] : 0;
$f_user_id = isset($_GET['f_user_id']) ? (int) $_GET['f_user_id'] : 0;

if ($f_date_from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_date_from)) {
    $f_date_from = '';
}
if ($f_date_to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_date_to)) {
    $f_date_to = '';
}

$filter_active_count = 0;
if ($f_date_from !== '') {
    $filter_active_count++;
}
if ($f_date_to !== '') {
    $filter_active_count++;
}
if ($f_branch_id > 0) {
    $filter_active_count++;
}
if ($f_department_id > 0) {
    $filter_active_count++;
}
if ($f_user_id > 0) {
    $filter_active_count++;
}

$report_rows = [];
$chk = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_manufacturing_closing'");
if ($chk && mysqli_num_rows($chk) > 0) {
    mysqli_free_result($chk);
    $branchJoin = '';
    $branchSel = ', NULL AS branch_name';
    $tb = @mysqli_query($conn, "SHOW TABLES LIKE 'tbl_branches'");
    if ($tb && mysqli_num_rows($tb) > 0) {
        mysqli_free_result($tb);
        $branchJoin = ' LEFT JOIN tbl_branches b ON b.id = c.branch_id';
        $branchSel = ', b.name AS branch_name';
    } elseif ($tb) {
        mysqli_free_result($tb);
    }

    $where = ['1=1'];
    if ($f_date_from !== '') {
        $where[] = "c.closing_date >= '" . mysqli_real_escape_string($conn, $f_date_from) . "'";
    }
    if ($f_date_to !== '') {
        $where[] = "c.closing_date <= '" . mysqli_real_escape_string($conn, $f_date_to) . "'";
    }
    if ($f_branch_id > 0) {
        $where[] = 'c.branch_id = ' . $f_branch_id;
    }
    if ($f_department_id > 0) {
        $where[] = 'c.department_id = ' . $f_department_id;
    }
    if ($f_user_id > 0) {
        $where[] = 'c.department_user_id = ' . $f_user_id;
    }
    $whereSql = implode(' AND ', $where);

    $sql = "
    SELECT c.*, d.dept_name AS department_name, u.name AS user_name {$branchSel}
    FROM tbl_manufacturing_closing c
    LEFT JOIN tbl_departments d ON d.id = c.department_id
    LEFT JOIN tbl_customers u ON u.id = c.department_user_id
    {$branchJoin}
    WHERE {$whereSql}
    ORDER BY c.closing_date DESC, c.id DESC
    LIMIT 500
    ";
    $report_rows = function_exists('getList') ? @getList($sql) : [];
    if (!is_array($report_rows)) {
        $report_rows = [];
    }
}

$cr_columns = [
    ['key' => 'department_name', 'label' => 'Department'],
    ['key' => 'user_name', 'label' => 'User'],
    ['key' => 'closing_date', 'label' => 'Closing Date'],
    ['key' => 'loss_wt', 'label' => 'Loss Wt'],
    ['key' => 'action', 'label' => 'action'],
    ['key' => 'branch_name', 'label' => 'Branch'],
    ['key' => 'gold_rate', 'label' => 'Gold Rate'],
    ['key' => 'gold_loss_value', 'label' => 'Gold Loss Value'],
    ['key' => 'inward_wt', 'label' => 'Inward Wt'],
    ['key' => 'outward_wt', 'label' => 'Outward Wt'],
    ['key' => 'recovery_wt', 'label' => 'Recovery Wt'],
    ['key' => 'closing_wt', 'label' => 'Closing Wt'],
    ['key' => 'production_wt', 'label' => 'Production Wt'],
    ['key' => 'work_done_kg', 'label' => 'Work Done(KG)'],
    ['key' => 'avg_loss_per_kg', 'label' => 'Avg Loss / KG'],
    ['key' => 'difference_loss', 'label' => 'Difference Loss'],
    ['key' => 'final_loss', 'label' => 'Final Loss'],
    ['key' => 'loss_percent', 'label' => 'Loss %'],
    ['key' => 'closed_jobs', 'label' => 'Closed Jobs'],
    ['key' => 'processed_jobs', 'label' => 'Processed Jobs'],
    ['key' => 'total_jobs', 'label' => 'Total Jobs'],
    ['key' => 'metal_weight', 'label' => 'Metal Weight'],
];

function cr_fmt_num($v, $dec = 3) {
    if ($v === null || $v === '') {
        return '—';
    }
    $f = (float) $v;
    if (!is_finite($f)) {
        return '—';
    }
    return number_format($f, $dec, '.', '');
}

function cr_fmt_int($v) {
    if ($v === null || $v === '') {
        return '—';
    }
    return (string) (int) $v;
}

function cr_cell_value(array $row, $key) {
    switch ($key) {
        case 'department_name':
            return trim((string) ($row['department_name'] ?? '')) !== '' ? trim((string) $row['department_name']) : '—';
        case 'user_name':
            return trim((string) ($row['user_name'] ?? '')) !== '' ? trim((string) $row['user_name']) : '—';
        case 'closing_date':
            $d = $row['closing_date'] ?? '';
            if ($d === '' || $d === null) {
                return '—';
            }
            $t = strtotime((string) $d);
            return $t ? date('d-m-Y', $t) : '—';
        case 'action':
            $did = (int) ($row['department_id'] ?? 0);
            $uid = (int) ($row['department_user_id'] ?? 0);
            $href = 'manufacturing-process.php?department_id=' . $did . '&user_id=' . $uid;
            return '<a class="cr-link" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">Open</a>';
        case 'branch_name':
            return trim((string) ($row['branch_name'] ?? '')) !== '' ? trim((string) $row['branch_name']) : '—';
        case 'closed_jobs':
        case 'processed_jobs':
        case 'total_jobs':
            return cr_fmt_int($row[$key] ?? null);
        case 'loss_percent':
        case 'gold_rate':
        case 'gold_loss_value':
            return cr_fmt_num($row[$key] ?? null, 2);
        default:
            return cr_fmt_num($row[$key] ?? null, 3);
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Closing Report - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php include __DIR__ . '/header-script.php'; ?>
    <link rel="stylesheet" href="assets/css/mfg-pages-mobile.css">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <style>
        :root {
            --cr-navy: #11294b;
            --cr-gold: #c9a24a;
            --cr-gold-light: #e8d48a;
            --cr-gold-pale: #faf6eb;
        }
        .cr-wrap { padding: 12px 14px 24px; max-width: 100%; }
        .cr-head {
            display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;
            margin-bottom: 12px;
        }
        .cr-head h1 { font-size: 1.25rem; font-weight: 700; color: var(--cr-navy); margin: 0; }
        .cr-toolbar { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .cr-toolbar .btn { border-radius: 8px; font-weight: 600; }
        .cr-table-scroll {
            overflow: auto;
            border: 1px solid #d6dbea;
            border-radius: 10px;
            background: #fff;
            max-height: calc(100vh - 200px);
        }
        .cr-table {
            border-collapse: collapse;
            width: max-content;
            min-width: 100%;
            font-size: 12px;
            table-layout: fixed;
        }
        .cr-table thead th {
            background: linear-gradient(180deg, var(--cr-navy) 0%, #1a3a5c 100%);
            color: var(--cr-gold-light);
            font-weight: 700;
            padding: 8px 10px;
            border-right: 1px solid rgba(255,255,255,0.12);
            border-bottom: 1px solid #0a1f36;
            white-space: nowrap;
            position: relative;
            min-width: 72px;
            user-select: none;
        }
        .cr-table thead th .cr-th-inner { display: flex; align-items: center; gap: 4px; }
        .cr-table thead th .cr-drag-hint {
            opacity: 0.85;
            cursor: grab;
            display: inline-flex;
            align-items: center;
        }
        .cr-table thead th .cr-drag-hint i.feather {
            width: 14px;
            height: 14px;
        }
        .cr-table tbody td {
            padding: 6px 10px;
            border-right: 1px solid #e8ecf2;
            border-bottom: 1px solid #e8ecf2;
            color: #1e293b;
        }
        .cr-table tbody tr:nth-child(even) td { background: #fafbfc; }
        .cr-resize-handle {
            position: absolute;
            right: 0;
            top: 0;
            width: 6px;
            height: 100%;
            cursor: col-resize;
            z-index: 2;
        }
        .cr-link { color: var(--cr-navy); font-weight: 600; text-decoration: underline; }
        .cr-table tfoot { border-top: 2px solid #cbd5e1; }
        .cr-foot-row td {
            font-weight: 700;
            background: #eef2f6 !important;
            color: #1e293b !important;
            border-top: 1px solid #cbd5e1;
            padding: 10px 10px !important;
        }
        .cr-foot-row td:first-child { text-align: left; color: var(--cr-navy) !important; }
        @media print {
            .sidebar, .layout-navbar, .layout-footer, .layout-overlay, .sidenav, .layout-sidenav,
            .cr-head .cr-toolbar, .cr-help-hint, .no-print { display: none !important; }
            .cr-head { display: block !important; margin-bottom: 10px !important; }
            .cr-head h1 { font-size: 18px !important; color: #000 !important; }
            .layout-content { margin: 0 !important; padding: 8px !important; height: auto !important; overflow: visible !important; }
            .cr-wrap { padding: 0 !important; }
            .cr-table-scroll {
                max-height: none !important;
                overflow: visible !important;
                border: none !important;
                box-shadow: none !important;
            }
            .cr-table { font-size: 11px; }
            body { background: #fff !important; }
        }
        .cr-columns-panel {
            position: absolute;
            right: 0;
            top: 100%;
            margin-top: 6px;
            width: 280px;
            max-height: 400px;
            background: #fff;
            border: 1px solid #d6dbea;
            border-radius: 10px;
            box-shadow: 0 10px 28px rgba(17,41,75,0.15);
            z-index: 2000;
            display: none;
            flex-direction: column;
        }
        .cr-columns-panel.show { display: flex; }
        .cr-columns-panel-header {
            padding: 8px 10px;
            border-bottom: 1px solid #e8ecf2;
            font-weight: 700;
            color: var(--cr-navy);
            display: flex; justify-content: space-between; align-items: center;
        }
        .cr-columns-list { overflow: auto; padding: 8px; }
        .cr-columns-list label { display: flex; align-items: center; gap: 8px; padding: 4px 0; font-size: 13px; cursor: pointer; }
        .cr-toolbar-wrap { position: relative; }
        .cr-filter-btn-wrap { position: relative; display: inline-block; }
        .cr-filter-badge {
            position: absolute;
            top: -7px;
            right: -8px;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            border-radius: 999px;
            background: #ef4444;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            line-height: 18px;
            text-align: center;
            box-shadow: 0 1px 2px rgba(0,0,0,0.15);
        }
        /* Advance filter modal — layout from advance-filter-global.css; accent like reference */
        #crAdvanceFilterOverlay.filter-modal-overlay { z-index: 1500; }
        .cr-adv-filter-modal.filter-modal {
            width: min(520px, calc(100vw - 32px));
            border: 1px solid #a78bfa;
            box-shadow: 0 16px 40px rgba(91, 33, 182, 0.18);
        }
        .cr-adv-filter-modal .filter-modal-head {
            border-bottom: 1px solid #ddd6fe;
            background: linear-gradient(180deg, #faf5ff 0%, #f3e8ff 100%);
            color: #5b21b6;
        }
        .cr-adv-filter-modal .filter-modal-close { color: #7c3aed; }
        .cr-adv-filter-modal .filter-modal-close:hover { background: rgba(124, 58, 237, 0.1); }
        .cr-date-reset-btn {
            width: 32px;
            height: 32px;
            border: 1px solid #c4b5fd;
            border-radius: 8px;
            background: #fff;
            color: #6d28d9;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            padding: 0;
            flex-shrink: 0;
        }
        .cr-date-reset-btn:hover { background: #f5f3ff; }
        .cr-empty { text-align: center; padding: 32px; color: #64748b; font-weight: 600; }
    </style>
</head>
<body class="mfg-page closing-report-page">
<?php include __DIR__ . '/sidebar.php'; ?>
<div class="layout-content">
    <div class="cr-wrap container-fluid flex-grow-1">
        <div class="cr-head">
            <h1>Closing Report</h1>
            <div class="cr-toolbar">
                <span class="cr-filter-btn-wrap">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="crBtnFilter" title="Advance Filter" aria-label="Advance Filter">
                        <i class="feather icon-filter"></i>
                    </button>
                    <?php if ($filter_active_count > 0): ?>
                    <span class="cr-filter-badge" id="crFilterBadge" title="Active filters"><?php echo (int) $filter_active_count; ?></span>
                    <?php endif; ?>
                </span>
                <button type="button" class="btn btn-outline-primary btn-sm" id="crBtnRefresh" title="Refresh"><i class="feather icon-refresh-cw"></i></button>
                <div class="dropdown">
                    <button type="button" class="btn btn-sm dropdown-toggle" id="crBtnExport" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="border:1px solid var(--cr-navy);color:var(--cr-navy);background:var(--cr-gold-pale);font-weight:600;">
                        Export <i class="feather icon-chevron-down" style="width:14px;height:14px;vertical-align:middle;"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-right" aria-labelledby="crBtnExport">
                        <a class="dropdown-item" href="#" id="crExportExcel"><i class="feather icon-download" style="width:16px;height:16px;"></i> Excel (CSV)</a>
                        <a class="dropdown-item" href="#" id="crExportPdf"><i class="feather icon-file-text" style="width:16px;height:16px;"></i> PDF (Print)</a>
                    </div>
                </div>
                <div class="cr-toolbar-wrap">
                    <button type="button" class="btn btn-sm" style="border:1px solid var(--cr-navy);color:var(--cr-navy);background:var(--cr-gold-pale);" id="crBtnColumns"><i class="feather icon-settings"></i> Columns</button>
                    <div class="cr-columns-panel" id="crColumnsPanel">
                        <div class="cr-columns-panel-header">
                            <span>Columns</span>
                            <button type="button" class="close" id="crColumnsClose" aria-label="Close">&times;</button>
                        </div>
                        <div class="cr-columns-list" id="crColumnsList"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="cr-table-scroll" id="crPrintArea">
            <table class="cr-table" id="crTable">
                <thead>
                    <tr id="crHeaderRow">
                        <?php foreach ($cr_columns as $col): ?>
                        <th data-col="<?php echo htmlspecialchars($col['key']); ?>" data-heading="<?php echo htmlspecialchars($col['label'], ENT_QUOTES, 'UTF-8'); ?>" data-label="<?php echo htmlspecialchars(strtolower($col['label'])); ?>" style="width:110px;">
                            <span class="cr-th-inner"><span class="cr-drag-hint" title="Drag to reorder"><i class="feather icon-move"></i></span><?php echo htmlspecialchars($col['label']); ?></span>
                            <span class="cr-resize-handle" title="Resize"></span>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody id="crTableBody">
                    <?php if (empty($report_rows)): ?>
                    <tr><td colspan="<?php echo count($cr_columns); ?>" class="cr-empty">No Rows To Show</td></tr>
                    <?php else: ?>
                    <?php foreach ($report_rows as $row): ?>
                    <tr>
                        <?php foreach ($cr_columns as $col): ?>
                        <td data-col="<?php echo htmlspecialchars($col['key']); ?>"><?php echo $col['key'] === 'action' ? cr_cell_value($row, $col['key']) : htmlspecialchars(cr_cell_value($row, $col['key']), ENT_QUOTES, 'UTF-8'); ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <tfoot id="crTableFoot">
                    <tr class="cr-foot-row" id="crFooterRow">
                        <?php foreach ($cr_columns as $col): ?>
                        <td data-col="<?php echo htmlspecialchars($col['key']); ?>"><?php echo $col['key'] === 'department_name' ? 'Grand Total' : ($col['key'] === 'action' ? '—' : ($col['key'] === 'user_name' || $col['key'] === 'closing_date' || $col['key'] === 'branch_name' ? '—' : '0')); ?></td>
                        <?php endforeach; ?>
                    </tr>
                </tfoot>
            </table>
        </div>
        <p class="text-muted small mt-2 mb-0 cr-help-hint no-print">Drag column headers to reorder. Drag the right edge of a header to resize. Preferences are saved in this browser.</p>
    </div>
</div>

<div class="filter-modal-overlay" id="crAdvanceFilterOverlay" aria-hidden="true">
    <div class="filter-modal cr-adv-filter-modal" role="dialog" aria-modal="true" aria-labelledby="crAdvFilterTitle">
        <div class="filter-modal-head">
            <span id="crAdvFilterTitle">Advance Filter</span>
            <button type="button" class="filter-modal-close" id="crFilterCloseBtn" aria-label="Close">&times;</button>
        </div>
        <form class="filter-modal-body" id="crAdvanceFilterForm" method="get" action="closing-report.php">
            <div class="filter-grid">
                <div class="filter-field filter-field-full">
                    <label for="crFilterDateFrom">Closing Date</label>
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                        <div class="date-range-inputs" style="flex:1;min-width:200px;">
                            <input type="date" name="f_date_from" id="crFilterDateFrom" value="<?php echo htmlspecialchars($f_date_from, ENT_QUOTES, 'UTF-8'); ?>">
                            <span class="date-range-sep">to</span>
                            <input type="date" name="f_date_to" id="crFilterDateTo" value="<?php echo htmlspecialchars($f_date_to, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <button type="button" class="cr-date-reset-btn" id="crFilterDateReset" title="Clear dates" aria-label="Clear closing dates">
                            <i class="feather icon-refresh-cw" style="width:16px;height:16px;"></i>
                        </button>
                    </div>
                </div>
                <div class="filter-field">
                    <label for="crFilterBranch">Branch</label>
                    <select name="f_branch_id" id="crFilterBranch" class="form-control form-control-sm">
                        <option value="">Select Branch</option>
                        <?php foreach ($branches as $br): ?>
                        <option value="<?php echo (int) $br['id']; ?>"<?php echo $f_branch_id === (int) $br['id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars($br['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-field">
                    <label for="crFilterDept">Department</label>
                    <select name="f_department_id" id="crFilterDept" class="form-control form-control-sm">
                        <option value="">Select Dept Name</option>
                        <?php foreach ($departments as $d): ?>
                        <option value="<?php echo (int) $d['id']; ?>"<?php echo $f_department_id === (int) $d['id'] ? ' selected' : ''; ?>><?php echo htmlspecialchars($d['dept_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-field filter-field-full">
                    <label for="crFilterUser">User</label>
                    <select name="f_user_id" id="crFilterUser" class="form-control form-control-sm">
                        <option value="">Select User</option>
                        <?php
                        if ($f_department_id > 0 && !empty($department_users[$f_department_id])) {
                            foreach ($department_users[$f_department_id] as $uu) {
                                $uid = (int) ($uu['id'] ?? 0);
                                $un = trim((string) ($uu['name'] ?? ''));
                                $sel = ($f_user_id === $uid) ? ' selected' : '';
                                echo '<option value="' . $uid . '"' . $sel . '>' . htmlspecialchars($un, ENT_QUOTES, 'UTF-8') . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div class="filter-modal-foot">
                <button type="submit" class="btn-filter-apply">Apply Filter</button>
                <button type="button" class="btn-filter-clear" id="crFilterClearBtn">Clear Filter</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var STORAGE_ORDER = 'closing_report_column_order_v1';
    var STORAGE_WIDTHS = 'closing_report_column_widths_v1';
    var STORAGE_HIDDEN = 'closing_report_column_hidden_v1';

    var table = document.getElementById('crTable');
    var headerRow = document.getElementById('crHeaderRow');
    var tbody = document.getElementById('crTableBody');
    var footerRow = document.getElementById('crFooterRow');
    if (!table || !headerRow) return;

    var defaultOrder = <?php echo json_encode(array_column($cr_columns, 'key')); ?>;

    function loadJson(key, fallback) {
        try {
            var r = localStorage.getItem(key);
            return r ? JSON.parse(r) : fallback;
        } catch (e) { return fallback; }
    }

    function saveJson(key, val) {
        try { localStorage.setItem(key, JSON.stringify(val)); } catch (e) {}
    }

    function applyColumnOrder(order) {
        if (!Array.isArray(order) || order.length < 1) return;
        var ths = Array.prototype.slice.call(headerRow.querySelectorAll('th[data-col]'));
        var map = {};
        ths.forEach(function (th) { map[th.getAttribute('data-col')] = th; });
        order.forEach(function (key) {
            var th = map[key];
            if (th) headerRow.appendChild(th);
        });
        var rows = tbody.querySelectorAll('tr');
        rows.forEach(function (tr) {
            if (tr.querySelector('.cr-empty')) return;
            var tds = {};
            tr.querySelectorAll('td[data-col]').forEach(function (td) {
                tds[td.getAttribute('data-col')] = td;
            });
            order.forEach(function (key) {
                var td = tds[key];
                if (td) tr.appendChild(td);
            });
        });
        if (footerRow) {
            var ftds = {};
            footerRow.querySelectorAll('td[data-col]').forEach(function (td) {
                ftds[td.getAttribute('data-col')] = td;
            });
            order.forEach(function (key) {
                var td = ftds[key];
                if (td) footerRow.appendChild(td);
            });
        }
    }

    function applyWidths(widths) {
        if (!widths || typeof widths !== 'object') return;
        headerRow.querySelectorAll('th[data-col]').forEach(function (th) {
            var k = th.getAttribute('data-col');
            if (widths[k] != null && widths[k] > 20) {
                th.style.width = widths[k] + 'px';
                var col = th.cellIndex;
                tbody.querySelectorAll('tr').forEach(function (tr) {
                    var c = tr.children[col];
                    if (c) c.style.width = widths[k] + 'px';
                });
                if (footerRow) {
                    var fc = footerRow.children[col];
                    if (fc) fc.style.width = widths[k] + 'px';
                }
            }
        });
    }

    function applyHidden(hidden) {
        if (!Array.isArray(hidden)) return;
        table.querySelectorAll('th[data-col], td[data-col]').forEach(function (el) {
            var k = el.getAttribute('data-col');
            if (hidden.indexOf(k) >= 0) el.classList.add('col-hidden');
            else el.classList.remove('col-hidden');
        });
    }

    var order = loadJson(STORAGE_ORDER, defaultOrder);
    if (order && order.length) {
        applyColumnOrder(order);
    }
    applyWidths(loadJson(STORAGE_WIDTHS, {}));
    applyHidden(loadJson(STORAGE_HIDDEN, []));

    if (typeof Sortable !== 'undefined') {
        Sortable.create(headerRow, {
            animation: 150,
            handle: '.cr-th-inner',
            draggable: 'th',
            filter: '.cr-resize-handle',
            preventOnFilter: false,
            onEnd: function () {
                var keys = Array.prototype.map.call(headerRow.querySelectorAll('th[data-col]'), function (th) {
                    return th.getAttribute('data-col');
                });
                saveJson(STORAGE_ORDER, keys);
            }
        });
    }

    var resizing = null;
    headerRow.querySelectorAll('.cr-resize-handle').forEach(function (handle) {
        handle.addEventListener('mousedown', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (e.stopImmediatePropagation) e.stopImmediatePropagation();
            var th = handle.closest('th');
            if (!th) return;
            var startX = e.pageX;
            var startW = th.offsetWidth;
            resizing = { th: th, startX: startX, startW: startW };
        });
    });
    document.addEventListener('mousemove', function (e) {
        if (!resizing) return;
        var dx = e.pageX - resizing.startX;
        var nw = Math.max(48, resizing.startW + dx);
        resizing.th.style.width = nw + 'px';
        var col = resizing.th.cellIndex;
        tbody.querySelectorAll('tr').forEach(function (tr) {
            var c = tr.children[col];
            if (c) c.style.width = nw + 'px';
        });
        if (footerRow) {
            var fc = footerRow.children[col];
            if (fc) fc.style.width = nw + 'px';
        }
    });
    document.addEventListener('mouseup', function () {
        if (!resizing) return;
        var widths = loadJson(STORAGE_WIDTHS, {});
        var k = resizing.th.getAttribute('data-col');
        widths[k] = resizing.th.offsetWidth;
        saveJson(STORAGE_WIDTHS, widths);
        resizing = null;
    });

    function recalcFooter() {
        var fdDept = footerRow.querySelector('td[data-col="department_name"]');
        if (fdDept) {
            fdDept.textContent = 'Grand Total';
        }
        ['user_name', 'closing_date', 'branch_name'].forEach(function (k) {
            var fd = footerRow.querySelector('td[data-col="' + k + '"]');
            if (fd) {
                fd.textContent = '—';
            }
        });
        var fdAct = footerRow.querySelector('td[data-col="action"]');
        if (fdAct) {
            fdAct.textContent = '—';
        }

        var numericKeys = <?php echo json_encode([
            'loss_wt', 'gold_rate', 'gold_loss_value', 'inward_wt', 'outward_wt', 'recovery_wt', 'closing_wt', 'production_wt',
            'work_done_kg', 'avg_loss_per_kg', 'difference_loss', 'final_loss', 'loss_percent',
            'closed_jobs', 'processed_jobs', 'total_jobs', 'metal_weight',
        ]); ?>;
        var sums = {};
        numericKeys.forEach(function (k) { sums[k] = 0; });
        tbody.querySelectorAll('tr').forEach(function (tr) {
            if (tr.querySelector('.cr-empty')) return;
            numericKeys.forEach(function (k) {
                var td = tr.querySelector('td[data-col="' + k + '"]');
                if (!td) return;
                var t = (td.textContent || '').replace(/,/g, '').trim();
                if (t === '' || t === '—') return;
                var n = parseFloat(t);
                if (isFinite(n)) sums[k] += n;
            });
        });
        numericKeys.forEach(function (k) {
            var fd = footerRow.querySelector('td[data-col="' + k + '"]');
            if (fd) {
                var dec = (k.indexOf('jobs') >= 0 || k === 'closed_jobs' || k === 'processed_jobs' || k === 'total_jobs') ? 0 : (k === 'gold_rate' || k === 'gold_loss_value' || k === 'loss_percent' ? 2 : 3);
                if (k === 'closed_jobs' || k === 'processed_jobs' || k === 'total_jobs') {
                    fd.textContent = String(Math.round(sums[k]));
                } else {
                    fd.textContent = sums[k].toFixed(dec);
                }
            }
        });
    }
    recalcFooter();

    function escapeCsv(val) {
        var s = String(val != null ? val : '').replace(/\r\n/g, '\n');
        if (/[",\n]/.test(s)) {
            return '"' + s.replace(/"/g, '""') + '"';
        }
        return s;
    }

    function getVisibleColKeys() {
        var keys = [];
        headerRow.querySelectorAll('th[data-col]').forEach(function (th) {
            if (th.classList.contains('col-hidden')) return;
            keys.push(th.getAttribute('data-col'));
        });
        return keys;
    }

    function exportClosingExcel() {
        var keys = getVisibleColKeys();
        if (!keys.length) return;
        var lines = [];
        var headerLine = keys.map(function (k) {
            var th = headerRow.querySelector('th[data-col="' + k + '"]');
            var h = th ? (th.getAttribute('data-heading') || '').trim() : '';
            if (!h && th) {
                h = th.textContent.replace(/\s+/g, ' ').trim();
            }
            return escapeCsv(h || k);
        });
        lines.push(headerLine.join(','));

        tbody.querySelectorAll('tr').forEach(function (tr) {
            if (tr.querySelector('.cr-empty')) return;
            var row = keys.map(function (k) {
                var td = tr.querySelector('td[data-col="' + k + '"]');
                return escapeCsv(td ? String(td.textContent || '').trim() : '');
            });
            lines.push(row.join(','));
        });

        var foot = keys.map(function (k) {
            var td = footerRow.querySelector('td[data-col="' + k + '"]');
            return escapeCsv(td ? String(td.textContent || '').trim() : '');
        });
        lines.push(foot.join(','));

        var blob = new Blob(['\uFEFF' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'closing-report-' + new Date().toISOString().slice(0, 10) + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(a.href);
    }

    var colPanel = document.getElementById('crColumnsPanel');
    var colList = document.getElementById('crColumnsList');
    var colDefs = <?php echo json_encode($cr_columns, JSON_UNESCAPED_UNICODE); ?>;

    function buildColumnPanel() {
        if (!colList) return;
        colList.innerHTML = '';
        var hidden = loadJson(STORAGE_HIDDEN, []);
        colDefs.forEach(function (c) {
            var lab = document.createElement('label');
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.checked = hidden.indexOf(c.key) < 0;
            cb.setAttribute('data-col', c.key);
            lab.appendChild(cb);
            lab.appendChild(document.createTextNode(' ' + c.label));
            colList.appendChild(lab);
        });
    }
    buildColumnPanel();
    if (colList) {
        colList.addEventListener('change', function (e) {
            if (e.target.type !== 'checkbox') return;
            var hidden = [];
            colList.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
                if (!cb.checked) hidden.push(cb.getAttribute('data-col'));
            });
            saveJson(STORAGE_HIDDEN, hidden);
            applyHidden(hidden);
            recalcFooter();
        });
    }
    document.getElementById('crBtnColumns').addEventListener('click', function (e) {
        e.stopPropagation();
        colPanel.classList.toggle('show');
    });
    document.getElementById('crColumnsClose').addEventListener('click', function () {
        colPanel.classList.remove('show');
    });
    document.addEventListener('click', function (e) {
        if (colPanel && !colPanel.contains(e.target) && e.target.id !== 'crBtnColumns' && !e.target.closest('#crBtnColumns')) {
            colPanel.classList.remove('show');
        }
    });

    document.getElementById('crBtnRefresh').addEventListener('click', function () {
        window.location.reload();
    });

    var ex = document.getElementById('crExportExcel');
    var ep = document.getElementById('crExportPdf');
    if (ex) {
        ex.addEventListener('click', function (e) {
            e.preventDefault();
            exportClosingExcel();
        });
    }
    if (ep) {
        ep.addEventListener('click', function (e) {
            e.preventDefault();
            window.print();
        });
    }
})();
</script>
<script>
(function () {
    var crDeptUsers = <?php echo json_encode($department_users, JSON_UNESCAPED_UNICODE); ?>;
    var overlay = document.getElementById('crAdvanceFilterOverlay');
    var btnOpen = document.getElementById('crBtnFilter');
    var btnClose = document.getElementById('crFilterCloseBtn');
    var btnClear = document.getElementById('crFilterClearBtn');
    var btnDateReset = document.getElementById('crFilterDateReset');
    var selDept = document.getElementById('crFilterDept');
    var selUser = document.getElementById('crFilterUser');
    var df = document.getElementById('crFilterDateFrom');
    var dt = document.getElementById('crFilterDateTo');

    function openFilter() {
        if (!overlay) return;
        overlay.classList.add('show');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }
    function closeFilter() {
        if (!overlay) return;
        overlay.classList.remove('show');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function syncUserOptions() {
        if (!selDept || !selUser) return;
        var d = parseInt(selDept.value, 10) || 0;
        var keep = selUser.value;
        selUser.innerHTML = '<option value="">Select User</option>';
        if (d > 0 && crDeptUsers && crDeptUsers[d]) {
            crDeptUsers[d].forEach(function (u) {
                var o = document.createElement('option');
                o.value = String(u.id);
                o.textContent = u.name || '';
                if (String(u.id) === keep) {
                    o.selected = true;
                }
                selUser.appendChild(o);
            });
        }
    }

    if (btnOpen) {
        btnOpen.addEventListener('click', function (e) {
            e.preventDefault();
            openFilter();
        });
    }
    if (btnClose) {
        btnClose.addEventListener('click', closeFilter);
    }
    if (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) closeFilter();
        });
    }
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape' || !overlay || !overlay.classList.contains('show')) return;
        closeFilter();
    });
    if (selDept) {
        selDept.addEventListener('change', syncUserOptions);
    }
    if (btnClear) {
        btnClear.addEventListener('click', function () {
            window.location.href = 'closing-report.php';
        });
    }
    if (btnDateReset) {
        btnDateReset.addEventListener('click', function () {
            if (df) df.value = '';
            if (dt) dt.value = '';
        });
    }
})();
</script>
<style>.col-hidden { display: none !important; }</style>
</body>
</html>
