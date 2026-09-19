<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auragold_branch_data_scope.php';

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    header('Location: index.php');
    exit;
}

$aii_branches = [];
if (function_exists('getListMaster')) {
    $aii_branches = @getListMaster("SELECT id, name FROM tbl_branches WHERE status = 1 ORDER BY name ASC");
}
if (!is_array($aii_branches)) {
    $aii_branches = [];
}
$aii_default_branch_id = 0;
if (!empty($_SESSION['working_branch_id'])) {
    $aii_default_branch_id = (int) $_SESSION['working_branch_id'];
} elseif (!empty($_SESSION['branch_id'])) {
    $aii_default_branch_id = (int) $_SESSION['branch_id'];
} elseif (function_exists('auragold_effective_branch_id')) {
    $aii_default_branch_id = (int) auragold_effective_branch_id();
}
if ($aii_default_branch_id > 0 && !empty($conn_master) && function_exists('getRecordMaster')) {
    $aii_has_def = false;
    foreach ($aii_branches as $ab) {
        if ((int) ($ab['id'] ?? 0) === $aii_default_branch_id) {
            $aii_has_def = true;
            break;
        }
    }
    if (!$aii_has_def) {
        $brx = getRecordMaster('SELECT id, name FROM tbl_branches WHERE id = ' . (int) $aii_default_branch_id . ' LIMIT 1');
        if ($brx && !empty($brx['id'])) {
            $aii_branches[] = [
                'id'   => (int) $brx['id'],
                'name' => trim((string) ($brx['name'] ?? ('Branch #' . (int) $brx['id']))),
            ];
        }
    }
}

$aii_branch_locked = ($aii_default_branch_id > 0);
$aii_branch_display_name = '';
if ($aii_branch_locked) {
    foreach ($aii_branches as $ab) {
        if ((int) ($ab['id'] ?? 0) === $aii_default_branch_id) {
            $aii_branch_display_name = trim((string) ($ab['name'] ?? ''));
            break;
        }
    }
    if ($aii_branch_display_name === '' && !empty($conn_master) && function_exists('getRecordMaster')) {
        $rn = getRecordMaster('SELECT name FROM tbl_branches WHERE id = ' . (int) $aii_default_branch_id . ' LIMIT 1');
        if ($rn) {
            $aii_branch_display_name = trim((string) ($rn['name'] ?? ''));
        }
    }
    if ($aii_branch_display_name === '') {
        $aii_branch_display_name = 'Branch #' . (int) $aii_default_branch_id;
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Assign Inventory Items — <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
    <link href="https://unpkg.com/tabulator-tables@6.3.0/dist/css/tabulator.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/libs/bootstrap-daterangepicker/bootstrap-daterangepicker.css">
<?php include __DIR__ . '/header-script.php'; ?>
<style>
    /* Navy + gold (logo) — aligned with Assign Inventory To Sales Team */
    :root {
        --aii-navy: #11294b;
        --aii-navy-mid: #1a3c63;
        --aii-gold: #c9a227;
        --aii-gold-bright: #d4af37;
        --aii-gold-soft: #f5edd6;
        --aii-border: #d8dce3;
        --aii-filter-purple: #6d4ba8;
        --aii-filter-purple-soft: #f3edfc;
    }
    .aii-page {
        padding: 12px 14px 20px;
        background: #e8eaef;
        min-height: calc(100vh - 100px);
    }
    .aii-page-title {
        color: var(--aii-navy);
        letter-spacing: 0.02em;
    }
    .aii-breadcrumb-pill {
        display: inline-block;
        padding: 6px 14px;
        border-radius: 8px;
        background: linear-gradient(180deg, #fff 0%, var(--aii-gold-soft) 100%);
        border: 1px solid var(--aii-gold-bright);
        color: var(--aii-navy-mid);
        font-weight: 700;
        font-size: 13px;
    }
    .aii-side-card, .aii-main-card {
        background: #fff;
        border: 1px solid var(--aii-border);
        border-radius: 10px;
        box-shadow: 0 1px 3px rgba(17, 41, 75, 0.06);
    }
    .aii-side-head {
        padding: 10px 12px;
        font-weight: 700;
        font-size: 14px;
        color: var(--aii-navy);
        border-bottom: 1px solid var(--aii-border);
    }
    .aii-side-filters {
        padding: 10px 12px;
        display: grid;
        gap: 8px;
    }
    .aii-side-filters label {
        margin: 0;
        font-size: 11px;
        font-weight: 600;
        color: #64748b;
    }
    .aii-side-filters input {
        width: 100%;
        font-size: 12px;
        padding: 4px 8px;
        border-radius: 6px;
        border: 1px solid #cfd4dc;
    }
    .aii-side-filters input:focus {
        border-color: var(--aii-gold-bright);
        box-shadow: 0 0 0 2px rgba(212, 175, 55, 0.2);
        outline: none;
    }
    .aii-branch-readonly {
        background: #f1f5f9 !important;
        color: #334155;
        cursor: default;
        white-space: nowrap;
    }
    .aii-sp-table-wrap {
        max-height: calc(100vh - 280px);
        overflow: auto;
    }
    .aii-sp-table {
        width: 100%;
        font-size: 12px;
        border-collapse: collapse;
    }
    .aii-sp-table th, .aii-sp-table td {
        padding: 8px 10px;
        border-bottom: 1px solid #e8ecf2;
        text-align: left;
    }
    .aii-sp-table th {
        background: #f7f8fa;
        font-weight: 700;
        color: var(--aii-navy);
        position: sticky;
        top: 0;
        z-index: 1;
    }
    .aii-sp-table tbody tr {
        cursor: pointer;
    }
    .aii-sp-table tbody tr:hover {
        background: rgba(245, 237, 214, 0.45);
    }
    .aii-sp-table tbody tr.aii-sp-active {
        background: var(--aii-gold-soft);
        outline: 1px solid var(--aii-gold-bright);
        box-shadow: inset 0 -2px 0 var(--aii-gold-bright);
    }
    .aii-main-head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 10px 12px;
        border-bottom: 1px solid var(--aii-border);
    }
    .aii-tabs .nav-link {
        border-radius: 8px 8px 0 0;
        color: #64748b;
        font-weight: 700;
        font-size: 12px;
        padding: 8px 14px;
    }
    .aii-tabs .nav-link:hover {
        color: var(--aii-navy);
    }
    .aii-tabs .nav-link.active {
        color: var(--aii-navy-mid);
        border-bottom: 3px solid var(--aii-gold-bright);
    }
    .aii-meta {
        font-size: 13px;
        color: #475569;
        padding: 8px 12px;
    }
    .aii-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        padding: 8px 12px;
        background: #f7f8fa;
        border-bottom: 1px solid var(--aii-border);
    }
    .aii-icon-btn {
        position: relative;
        border: 1px solid rgba(17, 41, 75, 0.22);
        background: #fff;
        border-radius: 8px;
        width: 40px;
        height: 40px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: var(--aii-navy);
        cursor: pointer;
    }
    .aii-icon-btn:hover {
        background: var(--aii-gold-soft);
        border-color: var(--aii-gold-bright);
        color: var(--aii-navy-mid);
    }
    .aii-badge {
        position: absolute;
        top: -4px;
        right: -4px;
        min-width: 18px;
        height: 18px;
        padding: 0 5px;
        border-radius: 9px;
        background: #e11d48;
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        line-height: 18px;
        text-align: center;
    }
    .aii-grid-host {
        padding: 0 8px 12px;
    }
    .aii-grid-host .tabulator .tabulator-header {
        background: var(--aii-navy) !important;
        color: #fff !important;
        font-weight: 700;
        border-bottom: 2px solid var(--aii-gold-bright) !important;
    }
    .aii-grid-host .tabulator .tabulator-header .tabulator-col {
        border-right: 1px solid rgba(255, 255, 255, 0.22) !important;
        background: transparent !important;
    }
    .aii-grid-host .tabulator .tabulator-header .tabulator-col .tabulator-col-content {
        color: #fff !important;
        display: flex;
        align-items: center;
        gap: 2px;
    }
    .aii-grid-host .tabulator .tabulator-header .tabulator-col.aii-col-movable-hint .tabulator-col-content {
        flex-wrap: nowrap;
    }
    /* Feather icon-move — gold on navy (column drag handle) */
    .aii-grid-host .tabulator .tabulator-header .aii-col-drag-handle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-right: 6px;
        flex-shrink: 0;
        line-height: 1;
        user-select: none;
        pointer-events: auto;
        color: var(--aii-gold-bright);
    }
    .aii-grid-host .tabulator .tabulator-header .aii-col-drag-handle .feather {
        width: 15px;
        height: 15px;
        stroke-width: 2.25px;
    }
    .aii-grid-host .tabulator .tabulator-header .aii-col-drag-title {
        display: inline-flex;
        align-items: center;
        gap: 0;
        white-space: nowrap;
    }
    .aii-grid-host .tabulator .tabulator-header .aii-col-drag-label {
        font-weight: 700;
        color: #fff !important;
    }
    .aii-grid-host .tabulator .tabulator-header .tabulator-arrow {
        display: none !important;
    }
    .aii-grid-host .tabulator .tabulator-header .tabulator-col .tabulator-col-resize-handle {
        background: transparent;
    }
    .aii-grid-host .tabulator .tabulator-header .tabulator-col .tabulator-col-resize-handle:hover {
        background: rgba(212, 175, 55, 0.45);
    }
    .aii-grid-host .tabulator .tabulator-tableholder .tabulator-placeholder {
        color: #64748b;
        padding: 20px;
    }
    .aii-filter-modal-head {
        border: 2px solid var(--aii-gold-bright);
        border-radius: 10px 10px 0 0;
        background: linear-gradient(180deg, #fff 0%, var(--aii-gold-soft) 100%);
        color: var(--aii-navy);
    }
    /* Advance Filter modal — purple accent (reference UI) */
    .aii-filter-modal.filter-modal {
        width: min(640px, calc(100vw - 32px));
        background: #fff;
        border: 2px solid var(--aii-filter-purple);
        border-radius: 14px;
        box-shadow: 0 18px 40px rgba(45, 30, 90, 0.18);
        overflow: hidden;
    }
    .aii-filter-modal .filter-modal-head.aii-filter-modal-head {
        height: auto;
        min-height: 52px;
        padding: 10px 40px 12px;
        background: #fff;
        border-bottom: none;
        display: block;
        text-align: center;
    }
    .aii-filter-modal .aii-filter-title-pill {
        display: inline-block;
        padding: 8px 28px;
        border: 2px solid var(--aii-filter-purple);
        border-radius: 10px;
        color: var(--aii-filter-purple);
        font-size: 17px;
        font-weight: 700;
        background: linear-gradient(180deg, #fff 0%, var(--aii-filter-purple-soft) 100%);
    }
    .aii-filter-modal .filter-modal-close {
        top: 10px;
        right: 12px;
        color: #94a3b8;
    }
    .aii-filter-modal .filter-modal-body {
        padding: 16px 18px 18px;
        border-top: 1px solid #e8e0f4;
    }
    .aii-filter-modal .filter-grid {
        gap: 14px 18px;
    }
    .aii-filter-modal .filter-field label {
        color: #334155;
        font-weight: 700;
        font-size: 13px;
    }
    .aii-filter-modal .filter-field input:not([type="checkbox"]),
    .aii-filter-modal .filter-field select {
        border-radius: 8px;
        border-color: #c4c9d4;
        height: 38px;
    }
    .aii-range-input-wrap {
        position: relative;
        display: flex;
        align-items: center;
        gap: 6px;
        width: 100%;
    }
    .aii-range-input-wrap .aii-daterange-input {
        flex: 1;
        min-width: 0;
        cursor: pointer;
        background: #fff;
        padding-right: 56px;
    }
    .aii-range-input-wrap .aii-range-clear {
        position: absolute;
        right: 30px;
        top: 50%;
        transform: translateY(-50%);
        width: 26px;
        height: 26px;
        border: 0;
        border-radius: 50%;
        background: #f1f5f9;
        color: #64748b;
        padding: 0;
        line-height: 1;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .aii-range-input-wrap .aii-range-clear:hover {
        background: #e2e8f0;
        color: var(--aii-navy);
    }
    .aii-range-input-wrap .aii-range-cal {
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
        color: #94a3b8;
        pointer-events: none;
        font-size: 16px;
    }
    .aii-btn-apply {
        border: 2px solid var(--aii-filter-purple);
        color: var(--aii-filter-purple);
        background: #fff;
        border-radius: 8px;
        padding: 8px 22px;
        font-weight: 700;
        font-size: 14px;
    }
    .aii-btn-apply:hover {
        background: var(--aii-filter-purple-soft);
        color: #5a3d8a;
    }
    .aii-btn-clear {
        border: 2px solid #e879a8;
        color: #db2777;
        background: #fff;
        border-radius: 8px;
        padding: 8px 22px;
        font-weight: 700;
        font-size: 14px;
    }
    .aii-btn-clear:hover {
        background: #fdf2f8;
        border-color: #db2777;
        color: #be185d;
    }
    .aii-btn-export {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        border: 1px solid rgba(17, 41, 75, 0.28);
        background: #fff;
        color: var(--aii-navy);
        font-weight: 700;
        font-size: 13px;
        padding: 8px 14px;
        border-radius: 8px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
    }
    .aii-btn-export:hover,
    .aii-btn-export:focus {
        background: var(--aii-gold-soft);
        border-color: var(--aii-gold-bright);
        color: var(--aii-navy);
    }
    .aii-btn-export .feather {
        width: 16px;
        height: 16px;
    }
    .aii-meta a {
        color: var(--aii-navy-mid);
        font-weight: 600;
        text-decoration: underline;
        text-decoration-color: var(--aii-gold-bright);
    }
    .aii-meta a:hover {
        color: var(--aii-navy);
    }
    .aii-toolbar .btn-outline-secondary {
        border-color: rgba(17, 41, 75, 0.3);
        color: var(--aii-navy-mid);
        font-weight: 600;
        font-size: 13px;
    }
    .aii-toolbar .btn-outline-secondary:hover,
    .aii-toolbar .btn-outline-secondary:focus {
        background: var(--aii-gold-soft);
        border-color: var(--aii-gold-bright);
        color: var(--aii-navy);
    }
    .aii-grid-host .tabulator .tabulator-footer {
        background: #f7f8fa;
        border-top: 1px solid var(--aii-border);
    }
    .aii-grid-host .tabulator .tabulator-page.active {
        background: var(--aii-navy) !important;
        color: #fff !important;
        border-color: var(--aii-navy) !important;
    }
    .filter-modal-body .form-control:focus {
        border-color: var(--aii-gold-bright);
        box-shadow: 0 0 0 2px rgba(212, 175, 55, 0.2);
    }
    /* Date range picker popup above Advance Filter overlay (z-index 1400) */
    body > .daterangepicker {
        z-index: 1510 !important;
    }
</style>
</head>
<body>
<div class="layout-wrapper layout-2">
    <div class="layout-inner">
        <div id="layout-sidenav" class="layout-sidenav sidenav sidenav-vertical bg-white logo-dark" aria-hidden="true"></div>
        <div class="layout-container">
            <nav class="layout-navbar navbar navbar-expand-lg align-items-lg-center bg-dark container-p-x" id="layout-navbar" aria-hidden="true"></nav>
            <div class="layout-content">
                <div class="container-fluid flex-grow-1" style="padding-top:0;padding-bottom:0;">
<?php include __DIR__ . '/sidebar.php'; ?>

<div class="aii-page">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h1 class="h5 mb-0 font-weight-bold aii-page-title">Utilities</h1>
        <span class="aii-breadcrumb-pill">Assign Inventory Items</span>
    </div>

    <div class="row">
        <div class="col-lg-3 mb-3 mb-lg-0">
            <div class="aii-side-card">
                <div class="aii-side-head">Sales Person List</div>
                <div class="aii-side-filters">
                    <div>
                        <label for="aiiSpSearch">Sales Person</label>
                        <input type="search" id="aiiSpSearch" placeholder="Search" autocomplete="off">
                    </div>
                    <div>
                        <label for="aiiSpCountSearch">No. of Codes</label>
                        <input type="search" id="aiiSpCountSearch" placeholder="Filter by count" autocomplete="off">
                    </div>
                    <div>
                        <label for="aiiBranchSelect">Branch<?php echo $aii_branch_locked ? ' <span style="font-weight:400;color:#64748b;">(login)</span>' : ''; ?></label>
                        <?php if ($aii_branch_locked): ?>
                            <input type="hidden" id="aiiBranchSelect" value="<?php echo (int) $aii_default_branch_id; ?>">
                            <div class="form-control aii-branch-readonly" style="font-size:12px;padding:4px 8px;border:1px solid var(--aii-border);" title="This screen uses your login branch."><?php echo htmlspecialchars($aii_branch_display_name, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php else: ?>
                            <select id="aiiBranchSelect" class="form-control" style="font-size:12px;padding:4px 8px;">
                                <option value="">— Select —</option>
                                <?php foreach ($aii_branches as $br): ?>
                                    <option value="<?php echo (int) ($br['id'] ?? 0); ?>"<?php echo ((int) ($br['id'] ?? 0) === $aii_default_branch_id) ? ' selected' : ''; ?>><?php echo htmlspecialchars((string) ($br['name'] ?? '')); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="aii-sp-table-wrap">
                    <table class="aii-sp-table">
                        <thead>
                            <tr>
                                <th>Sales Person</th>
                                <th style="width:88px;">No. of Codes</th>
                            </tr>
                        </thead>
                        <tbody id="aiiSpBody"></tbody>
                        <tfoot>
                            <tr style="font-weight:700;background:#f7f8fa;">
                                <td>Total</td>
                                <td id="aiiSpTotalCount">0</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="p-2 text-muted small border-top" id="aiiSpFooter">Showing 0 entries</div>
            </div>
        </div>
        <div class="col-lg-9">
            <div class="aii-main-card">
                <div class="aii-main-head">
                    <ul class="nav nav-tabs aii-tabs border-0 mb-0">
                        <li class="nav-item">
                            <span class="nav-link active">Assigns Inventory</span>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="rfid-barcode-scan.php">RFID / Barcode Scans</a>
                        </li>
                    </ul>
                </div>
                <div class="aii-meta">
                    <span class="mr-3">Total: <strong id="aiiMetaTotal">0</strong></span>
                    <span class="mr-3">Contact: <strong id="aiiMetaContact">NA</strong></span>
                    <span class="mr-3">Email: <strong id="aiiMetaEmail">NA</strong></span>
                    <span class="text-muted small">Assign or change lines in <a href="assign-inventory-to-sales-team.php">Assign Inventory To Sales Team</a>.</span>
                </div>
                <div class="aii-toolbar">
                    <button type="button" class="aii-icon-btn" id="aiiBtnOpenFilter" title="Advance Filter" aria-label="Advance Filter">
                        <i class="feather icon-filter" style="font-size:20px;"></i>
                        <span class="aii-badge" id="aiiFilterBadge" style="display:none;">0</span>
                    </button>
                    <button type="button" class="aii-icon-btn" id="aiiBtnRefresh" title="Refresh" aria-label="Refresh">
                        <i class="feather icon-refresh-cw" style="font-size:18px;"></i>
                    </button>
                    <div class="dropdown">
                        <button type="button" class="btn aii-btn-export dropdown-toggle" data-toggle="dropdown" aria-expanded="false" id="aiiExportDropdownBtn">
                            Export
                            <i class="feather icon-chevron-down" aria-hidden="true"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="aiiExportDropdownBtn">
                            <a class="dropdown-item" href="#" id="aiiExportCsv"><i class="feather icon-file-text mr-2" style="font-size:14px;vertical-align:middle;"></i>Export as CSV</a>
                        </div>
                    </div>
                </div>
                <div class="aii-grid-host">
                    <div id="aiiGrid"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Advance Filter Modal -->
<div class="filter-modal-overlay" id="aiiFilterOverlay" aria-hidden="true">
    <div class="filter-modal aii-filter-modal" role="dialog" aria-labelledby="aiiFilterTitle">
        <div class="filter-modal-head aii-filter-modal-head">
            <span class="aii-filter-title-pill" id="aiiFilterTitle">Advance Filter</span>
            <button type="button" class="filter-modal-close" id="aiiFilterClose" aria-label="Close">&times;</button>
        </div>
        <div class="filter-modal-body">
            <div class="filter-grid">
                <div class="filter-field">
                    <label for="aiiDateRangeDisplay">Date Range</label>
                    <div class="aii-range-input-wrap">
                        <input type="text" id="aiiDateRangeDisplay" class="form-control form-control-sm aii-daterange-input" readonly placeholder="dd-mm-yyyy - dd-mm-yyyy" autocomplete="off">
                        <input type="hidden" id="aiiDateFrom" value="">
                        <input type="hidden" id="aiiDateTo" value="">
                        <button type="button" class="aii-range-clear" id="aiiDateRangeClear" title="Clear" aria-label="Clear date range">&times;</button>
                        <i class="feather icon-calendar aii-range-cal" aria-hidden="true"></i>
                    </div>
                </div>
                <div class="filter-field">
                    <label for="aiiEstRangeDisplay">Est. Return Date</label>
                    <div class="aii-range-input-wrap">
                        <input type="text" id="aiiEstRangeDisplay" class="form-control form-control-sm aii-daterange-input" readonly placeholder="dd-mm-yyyy - dd-mm-yyyy" autocomplete="off">
                        <input type="hidden" id="aiiEstFrom" value="">
                        <input type="hidden" id="aiiEstTo" value="">
                        <button type="button" class="aii-range-clear" id="aiiEstRangeClear" title="Clear" aria-label="Clear est. return range">&times;</button>
                        <i class="feather icon-calendar aii-range-cal" aria-hidden="true"></i>
                    </div>
                </div>
                <div class="filter-field">
                    <label for="aiiFilterType">Select Type</label>
                    <select id="aiiFilterType" class="form-control form-control-sm">
                        <option value="all">All</option>
                        <option value="assign" selected>Assign Inventory</option>
                        <option value="unassign">Un-Assign Inventory</option>
                    </select>
                </div>
                <div class="filter-field">
                    <label for="aiiInvoiceNo">Invoice No.</label>
                    <input type="text" id="aiiInvoiceNo" class="form-control form-control-sm" placeholder="Invoice No." autocomplete="off">
                </div>
                <div class="filter-field" style="grid-column: 1;">
                    <label for="aiiBarcodeNo">Barcode No.</label>
                    <input type="text" id="aiiBarcodeNo" class="form-control form-control-sm" placeholder="Barcode No." autocomplete="off">
                </div>
            </div>
            <div class="text-center mt-4 pt-2" style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap;border-top:1px solid #ede9f7;">
                <button type="button" class="aii-btn-apply" id="aiiBtnApplyFilter">Apply Filter</button>
                <button type="button" class="aii-btn-clear" id="aiiBtnClearFilter">Clear Filter</button>
            </div>
        </div>
    </div>
</div>

                </div>
            </div>
        </div>
    </div>
</div>

<script src="assets/libs/moment/moment.js"></script>
<script src="assets/libs/bootstrap-daterangepicker/bootstrap-daterangepicker.js"></script>
<script src="https://unpkg.com/tabulator-tables@6.3.0/dist/js/tabulator.min.js"></script>
<script>
(function () {
    window.AII_LOCKED_BRANCH_ID = <?php echo $aii_branch_locked ? (int) $aii_default_branch_id : 0; ?>;
    var GRID_ID = '#aiiGrid';
    /** Per-user localStorage key for column order / width (Tabulator persistence) */
    var AII_PERSIST_ID = 'aii_assign_inventory_items_v1_u<?php echo (int)($_SESSION['user_id'] ?? 0); ?>';
    var selectedSalesPerson = '';
    var aiiSpCache = { rows: [], total: 0 };
    var filterState = {
        date_from: '',
        date_to: '',
        est_date_from: '',
        est_date_to: '',
        filter_type: 'assign',
        barcode: '',
        invoice_no: '',
        branch_id: <?php echo (int) $aii_default_branch_id; ?>
    };

    function aiiBranchId() {
        if (typeof window.AII_LOCKED_BRANCH_ID === 'number' && window.AII_LOCKED_BRANCH_ID > 0) {
            return window.AII_LOCKED_BRANCH_ID;
        }
        var el = document.getElementById('aiiBranchSelect');
        var v = el ? parseInt(String(el.value || '').trim(), 10) : 0;
        return isNaN(v) ? 0 : v;
    }

    function aiiUrl(name) {
        try {
            return new URL('ajax/' + name, window.location.href).href;
        } catch (e) {
            return 'ajax/' + name;
        }
    }

    function aiiSyncRangeDisplayFromState() {
        if (typeof moment === 'undefined') return;
        var df = document.getElementById('aiiDateFrom').value;
        var dt = document.getElementById('aiiDateTo').value;
        var ef = document.getElementById('aiiEstFrom').value;
        var et = document.getElementById('aiiEstTo').value;
        var dr = document.getElementById('aiiDateRangeDisplay');
        var er = document.getElementById('aiiEstRangeDisplay');
        if (df && dt) {
            dr.value = moment(df, 'YYYY-MM-DD').format('DD-MM-YYYY') + ' - ' + moment(dt, 'YYYY-MM-DD').format('DD-MM-YYYY');
        } else {
            dr.value = '';
        }
        if (ef && et) {
            er.value = moment(ef, 'YYYY-MM-DD').format('DD-MM-YYYY') + ' - ' + moment(et, 'YYYY-MM-DD').format('DD-MM-YYYY');
        } else {
            er.value = '';
        }
    }

    function aiiInitDateRangePickers() {
        if (typeof jQuery === 'undefined' || !jQuery.fn.daterangepicker || typeof moment === 'undefined') return;
        var $ = jQuery;
        function bind(displayId, fromId, toId) {
            var $disp = $('#' + displayId);
            if ($disp.data('daterangepicker')) {
                $disp.data('daterangepicker').remove();
            }
            var fromVal = document.getElementById(fromId).value;
            var toVal = document.getElementById(toId).value;
            var opts = {
                autoApply: true,
                autoUpdateInput: false,
                showDropdowns: true,
                locale: {
                    format: 'DD-MM-YYYY',
                    separator: ' - ',
                    applyLabel: 'Apply',
                    cancelLabel: 'Clear',
                    firstDay: 1
                },
                opens: 'center',
                drops: 'down'
            };
            if (fromVal && toVal) {
                opts.startDate = moment(fromVal, 'YYYY-MM-DD');
                opts.endDate = moment(toVal, 'YYYY-MM-DD');
            }
            $disp.daterangepicker(opts);
            $disp.off('apply.daterangepicker cancel.daterangepicker');
            $disp.on('apply.daterangepicker', function (ev, picker) {
                document.getElementById(fromId).value = picker.startDate.format('YYYY-MM-DD');
                document.getElementById(toId).value = picker.endDate.format('YYYY-MM-DD');
                $(this).val(picker.startDate.format('DD-MM-YYYY') + ' - ' + picker.endDate.format('DD-MM-YYYY'));
            });
            $disp.on('cancel.daterangepicker', function () {
                $(this).val('');
                document.getElementById(fromId).value = '';
                document.getElementById(toId).value = '';
            });
        }
        bind('aiiDateRangeDisplay', 'aiiDateFrom', 'aiiDateTo');
        bind('aiiEstRangeDisplay', 'aiiEstFrom', 'aiiEstTo');
    }

    function aiiCountActiveFilters() {
        var n = 0;
        if (filterState.date_from || filterState.date_to) n++;
        if (filterState.est_date_from || filterState.est_date_to) n++;
        if (filterState.filter_type && filterState.filter_type !== 'assign') n++;
        if (filterState.barcode) n++;
        if (filterState.invoice_no) n++;
        return n;
    }

    function aiiUpdateFilterBadge() {
        var el = document.getElementById('aiiFilterBadge');
        var c = aiiCountActiveFilters();
        if (c > 0) {
            el.textContent = String(c);
            el.style.display = '';
        } else {
            el.style.display = 'none';
        }
    }

    function aiiReadFilterFormIntoState() {
        filterState.date_from = document.getElementById('aiiDateFrom').value || '';
        filterState.date_to = document.getElementById('aiiDateTo').value || '';
        filterState.est_date_from = document.getElementById('aiiEstFrom').value || '';
        filterState.est_date_to = document.getElementById('aiiEstTo').value || '';
        filterState.filter_type = document.getElementById('aiiFilterType').value || 'assign';
        filterState.barcode = (document.getElementById('aiiBarcodeNo').value || '').trim();
        filterState.invoice_no = (document.getElementById('aiiInvoiceNo').value || '').trim();
        filterState.branch_id = aiiBranchId();
    }

    function aiiWriteStateToFilterForm() {
        document.getElementById('aiiDateFrom').value = filterState.date_from;
        document.getElementById('aiiDateTo').value = filterState.date_to;
        document.getElementById('aiiEstFrom').value = filterState.est_date_from;
        document.getElementById('aiiEstTo').value = filterState.est_date_to;
        document.getElementById('aiiFilterType').value = filterState.filter_type || 'assign';
        document.getElementById('aiiBarcodeNo').value = filterState.barcode;
        document.getElementById('aiiInvoiceNo').value = filterState.invoice_no;
        aiiSyncRangeDisplayFromState();
        aiiInitDateRangePickers();
    }

    function aiiClearFilters() {
        filterState = {
            date_from: '',
            date_to: '',
            est_date_from: '',
            est_date_to: '',
            filter_type: 'assign',
            barcode: '',
            invoice_no: '',
            branch_id: aiiBranchId()
        };
        aiiWriteStateToFilterForm();
        aiiUpdateFilterBadge();
    }

    function aiiDragTitleFormatterFactory(plainLabel) {
        return function aiiDragTitleFormatter() {
            var wrap = document.createElement('span');
            wrap.className = 'aii-col-drag-title';
            var handle = document.createElement('span');
            handle.className = 'aii-col-drag-handle';
            handle.setAttribute('title', 'Drag to reorder columns');
            var icon = document.createElement('i');
            icon.className = 'feather icon-move';
            icon.setAttribute('aria-hidden', 'true');
            handle.appendChild(icon);
            var lab = document.createElement('span');
            lab.className = 'aii-col-drag-label';
            lab.textContent = plainLabel;
            wrap.appendChild(handle);
            wrap.appendChild(lab);
            return wrap;
        };
    }

    var aiiBaseColumns = [
        { title: 'Invoice No.', field: 'invoice_no', minWidth: 110 },
        { title: 'Voucher', field: 'voucher_type', minWidth: 100 },
        { title: 'Description', field: 'description', minWidth: 160 },
        { title: 'Date', field: 'assign_date', minWidth: 100 },
        { title: 'Est. Return', field: 'est_return_date', minWidth: 100 },
        { title: 'Qty', field: 'qty', hozAlign: 'right', minWidth: 72 },
        { title: 'Carat', field: 'carat', minWidth: 72 },
        { title: 'Final Wt', field: 'final_wt', hozAlign: 'right', minWidth: 88 },
        { title: 'Amount', field: 'amount', hozAlign: 'right', minWidth: 90 },
        { title: 'Net Amount', field: 'net_amount', hozAlign: 'right', minWidth: 100 },
        { title: 'Barcode', field: 'barcode_no', minWidth: 110 },
        { title: 'Item Code', field: 'item_code', minWidth: 100 }
    ].map(function (c) {
        var plain = c.title;
        return Object.assign({}, c, {
            title: plain,
            titleFormatter: aiiDragTitleFormatterFactory(plain),
            headerClass: 'aii-col-movable-hint',
            headerSort: false
        });
    });

    var aiiColumns = [
        {
            field: '_aii_rowselect',
            formatter: 'rowSelection',
            titleFormatter: 'rowSelection',
            headerSort: false,
            frozen: true,
            width: 44,
            minWidth: 44,
            resizable: false,
            hozAlign: 'center',
            headerHozAlign: 'center'
        }
    ].concat(aiiBaseColumns);

    /** Reapply after persistence restores layout (stored titles can replace custom header formatters) */
    var aiiDragTitleByField = {};
    aiiBaseColumns.forEach(function (c) {
        if (c.field) {
            var plainTitle = typeof c.title === 'string' ? c.title : String(c.title);
            aiiDragTitleByField[c.field] = aiiDragTitleFormatterFactory(plainTitle);
        }
    });

    function reapplyAiiDragHeaderFormatters(tbl) {
        try {
            tbl.getColumns().forEach(function (col) {
                var f = col.getField();
                if (f && aiiDragTitleByField[f]) {
                    col.updateDefinition({ titleFormatter: aiiDragTitleByField[f] });
                }
            });
        } catch (e) {}
    }

    var table = new Tabulator(GRID_ID, {
        layout: 'fitData',
        height: Math.max(360, window.innerHeight - 360),
        placeholder: 'No Rows To Show',
        selectableRows: true,
        resizableColumns: true,
        movableColumns: true,
        persistence: { columns: true },
        persistenceMode: 'local',
        persistenceID: AII_PERSIST_ID,
        pagination: 'local',
        paginationSize: 25,
        paginationSizeSelector: [25, 50, 100, true],
        columnDefaults: {
            resizable: true,
            minWidth: 60,
            headerSort: false
        },
        columns: aiiColumns
    });

    table.on('tableBuilt', function () {
        reapplyAiiDragHeaderFormatters(table);
        setTimeout(function () { reapplyAiiDragHeaderFormatters(table); }, 50);
        setTimeout(function () { reapplyAiiDragHeaderFormatters(table); }, 250);
    });
    table.on('columnMoved', function () {
        reapplyAiiDragHeaderFormatters(table);
    });

    function aiiLoadGrid() {
        aiiReadFilterFormIntoState();
        var u = new URL(aiiUrl('assign-inventory-items-data.php'));
        u.searchParams.set('sales_person', selectedSalesPerson);
        u.searchParams.set('filter_type', filterState.filter_type);
        if (filterState.date_from) u.searchParams.set('date_from', filterState.date_from);
        if (filterState.date_to) u.searchParams.set('date_to', filterState.date_to);
        if (filterState.est_date_from) u.searchParams.set('est_date_from', filterState.est_date_from);
        if (filterState.est_date_to) u.searchParams.set('est_date_to', filterState.est_date_to);
        if (filterState.barcode) u.searchParams.set('barcode', filterState.barcode);
        if (filterState.invoice_no) u.searchParams.set('invoice_no', filterState.invoice_no);
        u.searchParams.set('branch_id', String(aiiBranchId()));

        fetch(u.href, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j || !j.success) {
                    table.setData([]);
                    document.getElementById('aiiMetaTotal').textContent = '0';
                    return;
                }
                var rows = j.rows || [];
                table.setData(rows);
                document.getElementById('aiiMetaTotal').textContent = String(rows.length);
            })
            .catch(function () {
                table.setData([]);
                document.getElementById('aiiMetaTotal').textContent = '0';
            });
    }

    function aiiRenderSpList(rows, totalAssigned) {
        var tbody = document.getElementById('aiiSpBody');
        var qName = (document.getElementById('aiiSpSearch').value || '').trim().toLowerCase();
        var qCnt = (document.getElementById('aiiSpCountSearch').value || '').trim();
        tbody.innerHTML = '';
        var shown = 0;
        rows.forEach(function (r) {
            var name = r.sales_person || '';
            var cnt = r.assigned_count != null ? r.assigned_count : 0;
            if (qName && name.toLowerCase().indexOf(qName) === -1) return;
            if (qCnt !== '' && String(cnt).indexOf(qCnt) === -1) return;
            var tr = document.createElement('tr');
            tr.dataset.sp = name;
            if (name === selectedSalesPerson) tr.classList.add('aii-sp-active');
            tr.innerHTML = '<td>' + name.replace(/</g, '&lt;') + '</td><td>' + cnt + '</td>';
            tr.addEventListener('click', function () {
                selectedSalesPerson = name;
                tbody.querySelectorAll('tr').forEach(function (x) { x.classList.remove('aii-sp-active'); });
                tr.classList.add('aii-sp-active');
                aiiLoadGrid();
            });
            tbody.appendChild(tr);
            shown++;
        });
        document.getElementById('aiiSpTotalCount').textContent = String(totalAssigned != null ? totalAssigned : 0);
        document.getElementById('aiiSpFooter').textContent = 'Showing ' + shown + ' entr' + (shown === 1 ? 'y' : 'ies');
    }

    function aiiLoadSpSidebar() {
        var su = new URL(aiiUrl('assign-inventory-items-sales-persons.php'));
        su.searchParams.set('branch_id', String(aiiBranchId()));
        fetch(su.href, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                if (!j || !j.success || !Array.isArray(j.rows)) return;
                aiiSpCache.rows = j.rows;
                aiiSpCache.total = j.total_assigned_lines != null ? j.total_assigned_lines : 0;
                aiiRenderSpList(aiiSpCache.rows, aiiSpCache.total);
            });
    }

    document.getElementById('aiiSpSearch').addEventListener('input', function () {
        aiiRenderSpList(aiiSpCache.rows, aiiSpCache.total);
    });
    document.getElementById('aiiSpCountSearch').addEventListener('input', function () {
        aiiRenderSpList(aiiSpCache.rows, aiiSpCache.total);
    });

    var aiiBranchSelectEl = document.getElementById('aiiBranchSelect');
    if (aiiBranchSelectEl && aiiBranchSelectEl.tagName === 'SELECT' && !(typeof window.AII_LOCKED_BRANCH_ID === 'number' && window.AII_LOCKED_BRANCH_ID > 0)) {
        aiiBranchSelectEl.addEventListener('change', function () {
            filterState.branch_id = aiiBranchId();
            aiiLoadSpSidebar();
            aiiLoadGrid();
        });
    }

    document.getElementById('aiiBtnRefresh').addEventListener('click', function () {
        aiiLoadSpSidebar();
        aiiLoadGrid();
    });

    document.getElementById('aiiDateRangeClear').addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        document.getElementById('aiiDateFrom').value = '';
        document.getElementById('aiiDateTo').value = '';
        document.getElementById('aiiDateRangeDisplay').value = '';
        aiiInitDateRangePickers();
    });
    document.getElementById('aiiEstRangeClear').addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        document.getElementById('aiiEstFrom').value = '';
        document.getElementById('aiiEstTo').value = '';
        document.getElementById('aiiEstRangeDisplay').value = '';
        aiiInitDateRangePickers();
    });

    document.getElementById('aiiBtnOpenFilter').addEventListener('click', function () {
        aiiWriteStateToFilterForm();
        document.getElementById('aiiFilterOverlay').classList.add('show');
    });
    document.getElementById('aiiFilterClose').addEventListener('click', function () {
        document.getElementById('aiiFilterOverlay').classList.remove('show');
    });
    document.getElementById('aiiFilterOverlay').addEventListener('click', function (e) {
        if (e.target === this) this.classList.remove('show');
    });
    document.getElementById('aiiBtnApplyFilter').addEventListener('click', function () {
        aiiReadFilterFormIntoState();
        aiiUpdateFilterBadge();
        document.getElementById('aiiFilterOverlay').classList.remove('show');
        aiiLoadGrid();
    });
    document.getElementById('aiiBtnClearFilter').addEventListener('click', function () {
        aiiClearFilters();
        aiiLoadGrid();
    });

    document.getElementById('aiiExportCsv').addEventListener('click', function (e) {
        e.preventDefault();
        table.download('csv', 'assign-inventory-items.csv');
    });

    window.addEventListener('resize', function () {
        table.setHeight(Math.max(360, window.innerHeight - 360));
    });

    aiiLoadSpSidebar();
    aiiClearFilters();
    aiiWriteStateToFilterForm();
    aiiUpdateFilterBadge();
    aiiLoadGrid();
})();
</script>
</body>
</html>
