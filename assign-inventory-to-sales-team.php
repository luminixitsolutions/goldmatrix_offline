<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auragold_branch_data_scope.php';
require_once __DIR__ . '/includes/user_management_schema.php';

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    header('Location: index.php');
    exit;
}

$aits_branches = [];
if (function_exists('getListMaster')) {
    $aits_branches = @getListMaster("SELECT id, name FROM tbl_branches WHERE status = 1 ORDER BY name ASC");
}
if (!is_array($aits_branches)) {
    $aits_branches = [];
}
$aits_default_branch_id = 0;
if (!empty($_SESSION['working_branch_id'])) {
    $aits_default_branch_id = (int) $_SESSION['working_branch_id'];
} elseif (!empty($_SESSION['branch_id'])) {
    $aits_default_branch_id = (int) $_SESSION['branch_id'];
} elseif (function_exists('auragold_effective_branch_id')) {
    $aits_default_branch_id = (int) auragold_effective_branch_id();
}
if ($aits_default_branch_id > 0 && !empty($conn_master) && function_exists('getRecordMaster')) {
    $aits_has_def = false;
    foreach ($aits_branches as $ab) {
        if ((int) ($ab['id'] ?? 0) === $aits_default_branch_id) {
            $aits_has_def = true;
            break;
        }
    }
    if (!$aits_has_def) {
        $brx = getRecordMaster('SELECT id, name FROM tbl_branches WHERE id = ' . (int) $aits_default_branch_id . ' LIMIT 1');
        if ($brx && !empty($brx['id'])) {
            $aits_branches[] = [
                'id'   => (int) $brx['id'],
                'name' => trim((string) ($brx['name'] ?? ('Branch #' . (int) $brx['id']))),
            ];
        }
    }
}

$aits_branch_locked = ($aits_default_branch_id > 0);
$aits_branch_display_name = '';
if ($aits_branch_locked) {
    foreach ($aits_branches as $ab) {
        if ((int) ($ab['id'] ?? 0) === $aits_default_branch_id) {
            $aits_branch_display_name = trim((string) ($ab['name'] ?? ''));
            break;
        }
    }
    if ($aits_branch_display_name === '' && !empty($conn_master) && function_exists('getRecordMaster')) {
        $rn = getRecordMaster('SELECT name FROM tbl_branches WHERE id = ' . (int) $aits_default_branch_id . ' LIMIT 1');
        if ($rn) {
            $aits_branch_display_name = trim((string) ($rn['name'] ?? ''));
        }
    }
    if ($aits_branch_display_name === '') {
        $aits_branch_display_name = 'Branch #' . (int) $aits_default_branch_id;
    }
}

$sales_person_users = [];
if (!empty($conn_master) && $aits_default_branch_id > 0) {
    auragold_ensure_user_management_columns($conn_master);
    $sales_person_users = auragold_sales_person_names_for_branch_id($conn_master, $aits_default_branch_id);
}
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Assign Inventory to Sales Team — <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
    <link href="https://unpkg.com/tabulator-tables@6.3.0/dist/css/tabulator.min.css" rel="stylesheet">
<?php include __DIR__ . '/header-script.php'; ?>
<style>
    /* Navy + gold (logo) — layout matches reference: twin panels, light surfaces, gold/navy accents */
    :root {
        --aits-navy: #11294b;
        --aits-navy-mid: #1a3c63;
        --aits-navy-deep: #0a1a2e;
        --aits-gold: #c9a227;
        --aits-gold-bright: #d4af37;
        --aits-gold-soft: #f5edd6;
        --aits-accent: var(--aits-navy-mid);
        --aits-accent-soft: var(--aits-gold-soft);
        --aits-border: #d8dce3;
        --aits-page-bg: #e8eaef;
        --aits-panel-bg: #ffffff;
        --aits-toolbar-bg: #f7f8fa;
        --aits-tabulator-header-h: 42px;
    }
    .aits-branch-readonly {
        min-width: 180px;
        background: #f1f5f9 !important;
        color: #334155;
        cursor: default;
        white-space: nowrap;
        border: 1px solid var(--aits-border);
    }
    .aits-wrap {
        padding: 16px 18px 24px;
        background: var(--aits-page-bg);
        min-height: calc(100vh - 120px);
    }
    .aits-top {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 1px solid var(--aits-border);
        background: transparent;
    }
    .aits-tabs .nav-link {
        border-radius: 8px 8px 0 0;
        color: #64748b;
        font-weight: 700;
        font-size: 12px;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        padding: 10px 16px;
        border: none;
        border-bottom: 3px solid transparent;
        background: transparent;
    }
    .aits-tabs .nav-link:hover {
        color: var(--aits-navy);
    }
    .aits-tabs .nav-link.active {
        color: var(--aits-navy-mid);
        border-bottom-color: var(--aits-gold-bright);
        background: transparent;
    }
    .aits-tabs .nav-link:not(.active) {
        border-bottom-color: transparent;
    }
    .aits-actions {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    .aits-btn-outline-navy {
        border: 1px solid var(--aits-navy);
        color: var(--aits-navy);
        background: #fff;
        border-radius: 8px;
        padding: 6px 18px;
        font-weight: 600;
        font-size: 13px;
    }
    .aits-btn-outline-navy:hover {
        background: rgba(17, 41, 75, 0.06);
        border-color: var(--aits-navy-mid);
        color: var(--aits-navy-mid);
    }
    .aits-btn-save {
        border: 1px solid rgba(17, 41, 75, 0.25);
        background: linear-gradient(180deg, #fdfbf6 0%, #e8ecf1 100%);
        color: var(--aits-navy);
        border-radius: 8px;
        padding: 6px 22px;
        font-weight: 600;
        font-size: 13px;
    }
    .aits-btn-save:hover {
        background: linear-gradient(180deg, #fff 0%, var(--aits-gold-soft) 100%);
        border-color: var(--aits-gold);
    }
    .aits-panel {
        background: var(--aits-panel-bg);
        border: 1px solid var(--aits-border);
        border-radius: 10px;
        box-shadow: 0 1px 4px rgba(17, 41, 75, 0.07);
        display: flex;
        flex-direction: column;
        min-height: min(520px, calc(100vh - 220px));
    }
    .aits-panel-head {
        padding: 12px 14px;
        background: var(--aits-toolbar-bg);
        border-bottom: 1px solid var(--aits-border);
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 12px 16px;
    }
    .aits-panel-head label {
        margin: 0 0 4px;
        font-size: 12px;
        color: var(--aits-navy);
        font-weight: 600;
        letter-spacing: 0.02em;
    }
    .aits-toolbar-row {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 12px 16px;
        width: 100%;
    }
    .aits-field-group {
        display: flex;
        flex-direction: column;
        gap: 0;
    }
    .aits-field-group--grow {
        flex: 1 1 200px;
        min-width: 160px;
        max-width: 100%;
    }
    .aits-field-group select,
    .aits-field-group .form-control {
        border-radius: 6px;
        border: 1px solid #cfd4dc;
        font-size: 13px;
    }
    .aits-field-group select:focus,
    .aits-field-group .form-control:focus {
        border-color: var(--aits-gold-bright);
        box-shadow: 0 0 0 2px rgba(201, 162, 39, 0.2);
    }
    .aits-search-wrap {
        position: relative;
        width: 100%;
    }
    .aits-search-wrap i {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--aits-navy-mid);
        opacity: 0.65;
        pointer-events: none;
        font-size: 15px;
    }
    .aits-search-wrap input {
        padding-left: 10px;
        padding-right: 32px;
        border-radius: 6px;
        border: 1px solid #cfd4dc;
        font-size: 13px;
        width: 100%;
    }
    .aits-col-gear {
        position: relative;
    }
    .aits-col-gear--header-float {
        margin: 0;
        align-self: stretch;
        display: flex;
        align-items: center;
    }
    .aits-col-gear .btn-link {
        color: var(--aits-gold) !important;
        padding: 4px 8px;
    }
    .aits-col-gear .btn-link:hover {
        color: var(--aits-gold-bright) !important;
        background: var(--aits-gold-soft) !important;
        border-radius: 6px;
    }
    .aits-columns-dd {
        position: absolute;
        top: 100%;
        right: 0;
        z-index: 1080;
        min-width: 260px;
        max-width: 300px;
        background: #fff;
        border: 1px solid var(--aits-border);
        border-radius: 10px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
        display: none;
        margin-top: 4px;
    }
    .aits-columns-dd.show { display: block; }
    .aits-columns-dd h6 {
        margin: 0;
        padding: 10px 12px;
        font-size: 13px;
        font-weight: 700;
        border-bottom: 1px solid var(--aits-border);
        color: #1e293b;
    }
    .aits-columns-dd .aits-cp-search {
        padding: 8px 10px;
        border-bottom: 1px solid var(--aits-border);
    }
    .aits-columns-dd .aits-cp-search input {
        width: 100%;
        font-size: 12px;
        padding: 6px 8px;
        border-radius: 6px;
        border: 1px solid var(--aits-border);
    }
    .aits-columns-dd .aits-cp-list {
        max-height: 240px;
        overflow-y: auto;
        padding: 6px 0;
    }
    .aits-columns-dd .aits-cp-item {
        padding: 4px 12px;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        color: #334155;
    }
    .aits-columns-dd .aits-cp-item:hover { background: #f8fafc; }
    .aits-columns-dd .aits-cp-item.d-none { display: none !important; }
    .aits-grid-host {
        flex: 1;
        min-height: 360px;
        position: relative;
        background: #fff;
    }
    /* Gear sits on the same row as the table header (reference UI) */
    .aits-grid-host.has-gear-float .aits-table-header-gear {
        position: absolute;
        top: 0;
        right: 0;
        z-index: 16;
        height: var(--aits-tabulator-header-h);
        min-width: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 8px;
        background: var(--aits-navy);
        border-bottom: 2px solid var(--aits-gold-bright);
        border-left: 1px solid rgba(255, 255, 255, 0.2);
        box-sizing: border-box;
    }
    .aits-grid-host.has-gear-float .tabulator .tabulator-header {
        padding-right: 44px !important;
    }
    .aits-table-header-gear .btn-link {
        color: var(--aits-gold-bright) !important;
        padding: 4px 6px !important;
    }
    .aits-table-header-gear .btn-link:hover {
        color: #fff !important;
        background: rgba(212, 175, 55, 0.22) !important;
        border-radius: 6px;
    }
    .aits-grid-host .tabulator {
        font-size: 12px;
        border: none;
        background: #fff;
    }
    .aits-grid-host .tabulator .tabulator-row.tabulator-row-even {
        background: #fafbfc;
    }
    .aits-img-thumb {
        width: 40px;
        height: 40px;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid var(--aits-border);
        vertical-align: middle;
        background: var(--aits-accent-soft);
    }
    .aits-grid-foot {
        padding: 8px 14px;
        background: linear-gradient(180deg, #f0f0f5 0%, #e8ecf1 100%);
        border-top: 1px solid var(--aits-border);
        font-size: 12px;
        font-weight: 600;
        color: var(--aits-navy);
        display: flex;
        justify-content: flex-start;
        align-items: center;
        gap: 10px;
    }
    .aits-grid-foot::before {
        content: "";
        width: 4px;
        height: 14px;
        border-radius: 2px;
        background: linear-gradient(180deg, var(--aits-gold-bright), var(--aits-navy));
        flex-shrink: 0;
    }
    .aits-split-row {
        align-items: stretch;
    }
    .aits-split-row > [class*="col-"] {
        display: flex;
        flex-direction: column;
    }
    .aits-split-row .aits-panel { flex: 1 1 50%; width: 100%; }
    .aits-barcode-wrap .input-group .form-control {
        border-radius: 6px 0 0 6px;
        border: 1px solid #cfd4dc;
        font-size: 13px;
    }
    .aits-barcode-wrap .input-group-text {
        background: #fff;
        border: 1px solid #cfd4dc;
        border-left: 0;
        border-radius: 0 6px 6px 0;
        color: var(--aits-navy-mid);
    }
    .aits-barcode-wrap .input-group-text i {
        opacity: 0.75;
    }
    /* Scrollbars: gold thumb (reference had light purple — we use navy/gold) */
    .aits-grid-host .tabulator-tableholder::-webkit-scrollbar,
    .aits-columns-dd .aits-cp-list::-webkit-scrollbar {
        height: 8px;
        width: 8px;
    }
    .aits-grid-host .tabulator-tableholder::-webkit-scrollbar-track {
        background: #e8eaef;
        border-radius: 4px;
    }
    .aits-grid-host .tabulator-tableholder::-webkit-scrollbar-thumb {
        background: linear-gradient(180deg, var(--aits-gold-bright), var(--aits-gold));
        border-radius: 4px;
    }
    .aits-grid-host .tabulator-tableholder::-webkit-scrollbar-thumb:hover {
        background: var(--aits-navy-mid);
    }
    .aits-grid-host .tabulator-tableholder {
        scrollbar-color: var(--aits-gold-bright) #e8eaef;
        scrollbar-width: thin;
    }
    .aits-page-title {
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--aits-navy);
        margin: 0 0 8px;
        letter-spacing: 0.02em;
    }
    .tabulator .tabulator-row .tabulator-cell.tabulator-row-handle {
        cursor: grab;
    }
    .tabulator .tabulator-row .tabulator-cell.tabulator-row-handle:active {
        cursor: grabbing;
    }

    /* Tabulator: navy header, white labels — sort arrows hidden; drag icon = column reorder */
    .aits-grid-host .tabulator .tabulator-header {
        background: var(--aits-navy) !important;
        border-bottom: 2px solid var(--aits-gold-bright) !important;
        font-weight: 700;
        color: #fff !important;
    }
    .aits-grid-host .tabulator .tabulator-header .tabulator-col {
        border-right: 1px solid rgba(255, 255, 255, 0.22) !important;
        background: transparent !important;
    }
    .aits-grid-host .tabulator .tabulator-header .tabulator-col .tabulator-col-content {
        color: #fff !important;
        display: flex;
        align-items: center;
        gap: 2px;
    }
    .aits-grid-host .tabulator .tabulator-header .tabulator-col.aits-col-movable-hint .tabulator-col-content {
        flex-wrap: nowrap;
    }
    .aits-grid-host .tabulator .tabulator-header .tabulator-col .tabulator-col-content .tabulator-col-title-holder {
        color: #fff !important;
    }
    /* Feather icon-move — gold on navy (column drag handle for movableColumns) */
    .aits-grid-host .tabulator .tabulator-header .aits-col-drag-handle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-right: 6px;
        flex-shrink: 0;
        line-height: 1;
        user-select: none;
        pointer-events: auto;
        color: var(--aits-gold-bright);
    }
    .aits-grid-host .tabulator .tabulator-header .aits-col-drag-handle .feather {
        width: 15px;
        height: 15px;
        stroke-width: 2.25px;
    }
    .aits-grid-host .tabulator .tabulator-header .aits-col-drag-title {
        display: inline-flex;
        align-items: center;
        gap: 0;
        white-space: nowrap;
    }
    .aits-grid-host .tabulator .tabulator-header .aits-col-drag-label {
        font-weight: 700;
    }
    .aits-grid-host .tabulator .tabulator-header .tabulator-col.tabulator-row-header .tabulator-col-content input[type="checkbox"] {
        filter: brightness(0) invert(1);
    }
    /* Drop slot while column reorder (Tabulator: tabulator-col-placeholder) */
    .aits-grid-host .tabulator .tabulator-header .tabulator-col.tabulator-col-placeholder {
        background: #fff !important;
        border: 2px solid var(--aits-gold-bright) !important;
        box-sizing: border-box;
    }
    /* Hide Tabulator’s floating header clone; label follows cursor via .aits-col-drag-ghost */
    .aits-grid-host .tabulator .tabulator-header .tabulator-col.tabulator-moving {
        opacity: 0 !important;
        visibility: hidden !important;
    }
    /* Floating badge: column name at cursor during header drag */
    .aits-col-drag-ghost {
        position: fixed;
        z-index: 100050;
        left: 0;
        top: 0;
        max-width: min(420px, 90vw);
        padding: 8px 14px;
        border-radius: 8px;
        background: var(--aits-navy);
        color: #fff !important;
        font-weight: 700;
        font-size: 13px;
        line-height: 1.25;
        pointer-events: none;
        box-shadow: 0 4px 14px rgba(0, 0, 0, 0.28);
        opacity: 0;
        visibility: hidden;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .aits-col-drag-ghost.aits-col-drag-ghost--visible {
        opacity: 1;
        visibility: visible;
    }
    /* Hide sort triangles — reorder uses drag icon (::before) + movableColumns */
    .aits-grid-host .tabulator .tabulator-arrow {
        display: none !important;
    }
    /* Column resize handle on navy header */
    .aits-grid-host .tabulator .tabulator-header .tabulator-col .tabulator-col-resize-handle {
        background: transparent;
    }
    .aits-grid-host .tabulator .tabulator-header .tabulator-col .tabulator-col-resize-handle:hover {
        background: rgba(212, 175, 55, 0.45);
    }
    .aits-grid-host .tabulator .tabulator-row .tabulator-cell {
        border-right: 1px solid #e8ecf2;
    }
    .aits-grid-host .tabulator .tabulator-tableholder .tabulator-placeholder {
        color: #64748b;
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

<div class="aits-wrap">
    <h1 class="aits-page-title">Assign Inventory to Sales Team</h1>
    <div class="aits-top">
        <ul class="nav nav-tabs aits-tabs border-0">
            <li class="nav-item">
                <span class="nav-link active">Assigns Inventory</span>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="rfid-barcode-scan.php">RFID / Barcode Scans</a>
            </li>
        </ul>
        <div class="aits-actions">
            <button type="button" class="aits-btn-outline-navy" id="aitsBtnUnassign" title="Move selected rows from the left grid back to the right">UnAssign</button>
            <button type="button" class="aits-btn-save" id="aitsBtnSave">Save</button>
        </div>
    </div>

    <div class="row aits-split-row">
        <div class="col-12 col-lg-6 mb-3 mb-lg-0">
            <div class="aits-panel">
                <div class="aits-panel-head">
                    <div class="aits-toolbar-row">
                        <div class="aits-field-group">
                            <label for="aitsBranch">Branch<?php echo $aits_branch_locked ? ' <span style="font-weight:400;color:#64748b;">(login)</span>' : ''; ?></label>
                            <?php if ($aits_branch_locked): ?>
                                <input type="hidden" id="aitsBranch" value="<?php echo (int) $aits_default_branch_id; ?>">
                                <div class="form-control form-control-sm aits-branch-readonly" id="aitsBranchReadonly" title="Assignments and stock use your login branch only."><?php echo htmlspecialchars($aits_branch_display_name, ENT_QUOTES, 'UTF-8'); ?></div>
                            <?php else: ?>
                                <select class="form-control form-control-sm" id="aitsBranch" style="min-width:180px;">
                                    <option value="">— Select branch —</option>
                                    <?php foreach ($aits_branches as $br): ?>
                                        <option value="<?php echo (int) ($br['id'] ?? 0); ?>"<?php echo ((int) ($br['id'] ?? 0) === $aits_default_branch_id) ? ' selected' : ''; ?>><?php echo htmlspecialchars((string) ($br['name'] ?? '')); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                        <div class="aits-field-group">
                            <label for="aitsSalesPerson">Sale Person</label>
                            <select class="form-control form-control-sm" id="aitsSalesPerson" style="min-width:200px;">
                                <option value="">— Select —</option>
                                <?php foreach ($sales_person_users as $sp): ?>
                                    <option value="<?php echo htmlspecialchars($sp); ?>"><?php echo htmlspecialchars($sp); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="aits-field-group aits-field-group--grow">
                            <label for="aitsSearchAssigned">Search</label>
                            <div class="aits-search-wrap">
                                <input type="search" class="form-control form-control-sm" id="aitsSearchAssigned" placeholder="Search" autocomplete="off" aria-label="Search assigned inventory">
                                <i class="feather icon-search" aria-hidden="true"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="aits-grid-host has-gear-float">
                    <div class="aits-table-header-gear">
                        <div class="aits-col-gear aits-col-gear--header-float">
                            <button type="button" class="btn btn-sm btn-link" id="aitsColBtnAssigned" aria-expanded="false" title="Columns"><i class="feather icon-settings" style="font-size:18px;"></i></button>
                            <div class="aits-columns-dd" id="aitsColDdAssigned" aria-hidden="true">
                                <h6>Columns</h6>
                                <div class="aits-cp-search"><input type="search" id="aitsColFilterAssigned" placeholder="Search" autocomplete="off"></div>
                                <div class="aits-cp-list" id="aitsColListAssigned"></div>
                            </div>
                        </div>
                    </div>
                    <div id="aitsGridAssigned"></div>
                </div>
                <div class="aits-grid-foot">
                    <span id="aitsCountAssigned">0 rows</span>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="aits-panel">
                <div class="aits-panel-head">
                    <div class="aits-toolbar-row">
                        <div class="aits-field-group aits-field-group--grow aits-barcode-wrap">
                            <label for="aitsBarcodeIn">Available stock — Barcode<?php echo $aits_branch_locked ? ' <span style="font-weight:400;color:#64748b;">(this branch)</span>' : ''; ?></label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control" id="aitsBarcodeIn" placeholder="Scan or enter barcode to add" autocomplete="off">
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-secondary" id="aitsBtnRefreshStock" title="Reload barcode stock list from inventory">Refresh stock</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="aits-grid-host has-gear-float">
                    <div class="aits-table-header-gear">
                        <div class="aits-col-gear aits-col-gear--header-float">
                            <button type="button" class="btn btn-sm btn-link" id="aitsColBtnPool" aria-expanded="false" title="Columns"><i class="feather icon-settings" style="font-size:18px;"></i></button>
                            <div class="aits-columns-dd" id="aitsColDdPool" aria-hidden="true">
                                <h6>Columns</h6>
                                <div class="aits-cp-search"><input type="search" id="aitsColFilterPool" placeholder="Search" autocomplete="off"></div>
                                <div class="aits-cp-list" id="aitsColListPool"></div>
                            </div>
                        </div>
                    </div>
                    <div id="aitsGridPool"></div>
                </div>
                <div class="aits-grid-foot">
                    <span id="aitsCountPool">0 rows</span>
                </div>
            </div>
        </div>
    </div>
</div>

                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/tabulator-tables@6.3.0/dist/js/tabulator.min.js"></script>
<script>
(function () {
    window.AITS_LOCKED_BRANCH_ID = <?php echo $aits_branch_locked ? (int) $aits_default_branch_id : 0; ?>;
    var POOL_SEL = '#aitsGridPool';
    var ASSIGNED_SEL = '#aitsGridAssigned';

    function colMoneyMutator(value) {
        if (value === '' || value === null || value === undefined) return '';
        var n = parseFloat(String(value).replace(/,/g, ''));
        return isNaN(n) ? value : n;
    }

    function fmtMoneyCell(cell) {
        var v = cell.getValue();
        if (v === '' || v === null || v === undefined) return '';
        var n = parseFloat(v);
        if (isNaN(n)) return String(v);
        return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function imgFormatter(cell) {
        var u = cell.getValue();
        if (!u || String(u).trim() === '') {
            return '<span class="text-muted">—</span>';
        }
        var s = String(u).trim();
        var urls = s.indexOf(',') >= 0 ? s.split(',') : [s];
        var first = urls[0].trim();
        if (!first) return '<span class="text-muted">—</span>';
        return '<img class="aits-img-thumb" src="' + first.replace(/"/g, '&quot;') + '" alt="" loading="lazy" onerror="this.style.display=\'none\'">';
    }

    function activeFormatter(cell) {
        var v = cell.getValue();
        if (v === true || v === 1 || v === '1' || String(v).toLowerCase() === 'yes') return 'Yes';
        if (v === false || v === 0 || v === '0' || String(v).toLowerCase() === 'no') return 'No';
        return v === null || v === undefined ? '' : String(v);
    }

    var rowHeaderSel = {
        formatter: 'rowSelection',
        titleFormatter: 'rowSelection',
        headerSort: false,
        resizable: false,
        frozen: true,
        width: 44,
        minWidth: 44,
        hozAlign: 'center',
        headerHozAlign: 'center'
    };

    function aitsEscapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/"/g, '&quot;');
    }

    /** Plain label for column picker / search */
    function aitsHeaderTitlePlain(def) {
        var t = def.title || def.field || '';
        if (typeof t !== 'string') return String(t);
        if (t.indexOf('<') === -1) return t;
        return t.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
    }

    /**
     * Feather icon-move for column reorder (movableColumns); drag from header / icon area.
     */
    function aitsDragTitleFormatterFactory(plainLabel) {
        return function aitsDragTitleFormatter() {
            var wrap = document.createElement('span');
            wrap.className = 'aits-col-drag-title';
            var handle = document.createElement('span');
            handle.className = 'aits-col-drag-handle';
            handle.setAttribute('title', 'Drag to reorder columns');
            var icon = document.createElement('i');
            icon.className = 'feather icon-move';
            icon.setAttribute('aria-hidden', 'true');
            handle.appendChild(icon);
            var lab = document.createElement('span');
            lab.className = 'aits-col-drag-label';
            lab.textContent = plainLabel;
            wrap.appendChild(handle);
            wrap.appendChild(lab);
            return wrap;
        };
    }

    var baseColumns = [
        {
            formatter: 'handle',
            rowHandle: true,
            headerSort: false,
            frozen: true,
            width: 36,
            minWidth: 36,
            resizable: false
        },
        { title: 'Barcode No', field: 'barcode_no', minWidth: 110, headerFilter: false },
        { title: 'RFIDCode', field: 'rfid_code', minWidth: 100 },
        { title: 'Product Name', field: 'product_name', minWidth: 140 },
        { title: 'imageUrls', field: 'imageUrls', minWidth: 90, formatter: imgFormatter, hozAlign: 'center' },
        { title: 'Amount', field: 'amount', hozAlign: 'right', minWidth: 90, mutator: colMoneyMutator, formatter: fmtMoneyCell },
        { title: 'Description', field: 'description', minWidth: 120 },
        { title: 'Design No', field: 'design_no', minWidth: 90 },
        { title: 'Gross Wt', field: 'gross_wt', hozAlign: 'right', minWidth: 80 },
        { title: 'Final Wt.', field: 'final_wt', hozAlign: 'right', minWidth: 80 },
        { title: 'Invoice No.', field: 'invoice_no', minWidth: 100 },
        { title: 'Metal Value', field: 'metal_value', hozAlign: 'right', minWidth: 100, mutator: colMoneyMutator, formatter: fmtMoneyCell },
        { title: 'Net Amount', field: 'net_amount', hozAlign: 'right', minWidth: 100, mutator: colMoneyMutator, formatter: fmtMoneyCell },
        { title: 'Net Amount With Tax', field: 'net_amount_with_tax', hozAlign: 'right', minWidth: 130, mutator: colMoneyMutator, formatter: fmtMoneyCell },
        { title: 'Quantity', field: 'quantity', hozAlign: 'right', minWidth: 72 },
        { title: 'Tax Amount', field: 'tax_amount', hozAlign: 'right', minWidth: 90, mutator: colMoneyMutator, formatter: fmtMoneyCell },
        { title: 'active', field: 'active', hozAlign: 'center', minWidth: 70, formatter: activeFormatter }
    ].map(function (c) {
        if (c.field) {
            var plain = c.title;
            return Object.assign({}, c, {
                title: plain,
                titleFormatter: aitsDragTitleFormatterFactory(plain),
                headerClass: 'aits-col-movable-hint'
            });
        }
        return c;
    });

    var gridH = Math.max(320, window.innerHeight - 280);

    var columnDefaults = {
        resizable: true,
        minWidth: 60,
        headerSort: false
    };

    var persistenceCols = {
        columns: true
    };

    /** Reapply after Tabulator persistence merges (restored layout can drop custom header formatters) */
    var aitsDragTitleByField = {};
    baseColumns.forEach(function (c) {
        if (c.field && c.title) {
            aitsDragTitleByField[c.field] = aitsDragTitleFormatterFactory(c.title);
        }
    });

    function reapplyDragHeaderFormatters(table) {
        try {
            table.getColumns().forEach(function (col) {
                var f = col.getField();
                if (f && aitsDragTitleByField[f]) {
                    col.updateDefinition({ titleFormatter: aitsDragTitleByField[f] });
                }
            });
        } catch (e) {}
    }

    var tablePool = new Tabulator(POOL_SEL, {
        layout: 'fitData',
        responsiveLayout: false,
        resizableColumns: true,
        movableColumns: true,
        movableRows: true,
        movableRowsConnectedTables: ASSIGNED_SEL,
        /** Remove row from this grid after a successful drop on the connected table (default leaves a duplicate). */
        movableRowsSender: 'delete',
        selectableRows: true,
        rowHeader: rowHeaderSel,
        height: gridH,
        placeholder: 'No Rows To Show',
        clipboard: true,
        columnDefaults: columnDefaults,
        persistence: persistenceCols,
        persistenceMode: 'local',
        persistenceID: 'aits_assign_pool_v5',
        data: [],
        columns: baseColumns.map(function (c) { return Object.assign({}, c); })
    });

    var tableAssigned = new Tabulator(ASSIGNED_SEL, {
        layout: 'fitData',
        responsiveLayout: false,
        resizableColumns: true,
        movableColumns: true,
        movableRows: true,
        movableRowsConnectedTables: POOL_SEL,
        movableRowsSender: 'delete',
        selectableRows: true,
        rowHeader: rowHeaderSel,
        height: gridH,
        placeholder: 'No Rows To Show',
        clipboard: true,
        columnDefaults: columnDefaults,
        persistence: persistenceCols,
        persistenceMode: 'local',
        persistenceID: 'aits_assign_assigned_v5',
        data: [],
        columns: baseColumns.map(function (c) { return Object.assign({}, c); })
    });

    function updateCounts() {
        var nP = tablePool.getDataCount();
        var nA = tableAssigned.getDataCount();
        document.getElementById('aitsCountPool').textContent = String(nP);
        document.getElementById('aitsCountAssigned').textContent = String(nA);
    }

    function syncGearHeaderHeight(table) {
        function apply() {
            try {
                var el = table.element.querySelector('.tabulator-header');
                var host = table.element.closest('.aits-grid-host');
                if (el && host) {
                    host.style.setProperty('--aits-tabulator-header-h', el.offsetHeight + 'px');
                }
            } catch (e) {}
        }
        table.on('tableBuilt', apply);
        table.on('columnResized', apply);
        table.on('columnMoved', apply);
        setTimeout(apply, 50);
    }

    syncGearHeaderHeight(tablePool);
    syncGearHeaderHeight(tableAssigned);

    [tablePool, tableAssigned].forEach(function (tbl) {
        tbl.on('tableBuilt', function () {
            reapplyDragHeaderFormatters(tbl);
            setTimeout(function () { reapplyDragHeaderFormatters(tbl); }, 50);
            setTimeout(function () { reapplyDragHeaderFormatters(tbl); }, 250);
        });
        tbl.on('columnMoved', function () {
            reapplyDragHeaderFormatters(tbl);
        });
    });

    /** Navy pill following the cursor while a column header is being reordered */
    function installAitsColumnDragGhost() {
        if (document.getElementById('aitsColDragGhost')) return;
        var ghost = document.createElement('div');
        ghost.id = 'aitsColDragGhost';
        ghost.className = 'aits-col-drag-ghost';
        ghost.setAttribute('aria-hidden', 'true');
        document.body.appendChild(ghost);

        function hideGhost() {
            ghost.classList.remove('aits-col-drag-ghost--visible');
            ghost.textContent = '';
        }

        function labelFromMovingEl(moving) {
            var lab = moving.querySelector('.aits-col-drag-label');
            if (lab && lab.textContent) return lab.textContent.trim();
            var holder = moving.querySelector('.tabulator-col-title');
            if (holder) return holder.textContent.replace(/\s+/g, ' ').trim();
            return '';
        }

        function updateGhost(e) {
            var moving = document.querySelector('.aits-grid-host .tabulator-header .tabulator-col.tabulator-moving');
            if (!moving) {
                hideGhost();
                return;
            }
            ghost.textContent = labelFromMovingEl(moving);
            var cx = e.clientX;
            var cy = e.clientY;
            if (e.touches && e.touches[0]) {
                cx = e.touches[0].clientX;
                cy = e.touches[0].clientY;
            }
            ghost.style.left = (cx + 14) + 'px';
            ghost.style.top = (cy + 14) + 'px';
            ghost.classList.add('aits-col-drag-ghost--visible');
        }

        document.addEventListener('mousemove', updateGhost);
        document.addEventListener('touchmove', updateGhost, { passive: true });
        document.addEventListener('mouseup', hideGhost);
        document.addEventListener('touchend', hideGhost, { passive: true });
        document.addEventListener('pointercancel', hideGhost);
    }

    installAitsColumnDragGhost();

    tablePool.on('dataLoaded', updateCounts);
    tablePool.on('rowAdded', updateCounts);
    tablePool.on('rowDeleted', updateCounts);
    tablePool.on('rowMoved', updateCounts);
    tableAssigned.on('dataLoaded', updateCounts);
    tableAssigned.on('rowAdded', updateCounts);
    tableAssigned.on('rowDeleted', updateCounts);
    tableAssigned.on('rowMoved', updateCounts);

    /** When multiple pool rows are selected, dragging one moves all selected to Assigned (and reverse for UnAssign). */
    var aitsBulkPoolSelection = null;
    var aitsBulkAssignedSelection = null;

    function aitsBarcodeKeyFromData(d) {
        return String(d && d.barcode_no != null ? d.barcode_no : '').trim();
    }

    /** Tabulator may pass a wrapper; compare DOM element or reference. */
    function aitsTabInstancesEqual(a, b) {
        if (!a || !b) {
            return false;
        }
        if (a === b) {
            return true;
        }
        try {
            var ae = a.element;
            var be = b.element;
            if (ae && be && ae === be) {
                return true;
            }
        } catch (e) {}
        return false;
    }

    /** Union getSelectedRows() + isSelected() walk — selection APIs differ during drag. */
    function aitsSnapshotPoolBulkSelection() {
        try {
            var byBc = {};
            try {
                tablePool.getSelectedRows().forEach(function (r) {
                    var d = r.getData();
                    var bc = aitsBarcodeKeyFromData(d);
                    if (bc) {
                        byBc[bc] = Object.assign({}, d);
                    }
                });
            } catch (e0) {}
            try {
                tablePool.getRows().forEach(function (r) {
                    var ok = false;
                    try {
                        ok = typeof r.isSelected === 'function' && r.isSelected();
                    } catch (e1) {
                        ok = false;
                    }
                    if (!ok) {
                        try {
                            var el = r.getElement();
                            if (el && el.classList && el.classList.contains('tabulator-selected')) {
                                ok = true;
                            }
                        } catch (e1b) {}
                    }
                    if (!ok) {
                        return;
                    }
                    var d = r.getData();
                    var bc = aitsBarcodeKeyFromData(d);
                    if (bc) {
                        byBc[bc] = Object.assign({}, d);
                    }
                });
            } catch (e2) {}
            var list = Object.keys(byBc).map(function (k) {
                return byBc[k];
            });
            if (list.length <= 1) {
                aitsBulkPoolSelection = null;
                return;
            }
            aitsBulkPoolSelection = list;
        } catch (e) {
            aitsBulkPoolSelection = null;
        }
    }

    function aitsSnapshotAssignedBulkSelection() {
        try {
            var byBc = {};
            try {
                tableAssigned.getSelectedRows().forEach(function (r) {
                    var d = r.getData();
                    var bc = aitsBarcodeKeyFromData(d);
                    if (bc) {
                        byBc[bc] = Object.assign({}, d);
                    }
                });
            } catch (e0) {}
            try {
                tableAssigned.getRows().forEach(function (r) {
                    var ok = false;
                    try {
                        ok = typeof r.isSelected === 'function' && r.isSelected();
                    } catch (e1) {
                        ok = false;
                    }
                    if (!ok) {
                        try {
                            var el = r.getElement();
                            if (el && el.classList && el.classList.contains('tabulator-selected')) {
                                ok = true;
                            }
                        } catch (e1b) {}
                    }
                    if (!ok) {
                        return;
                    }
                    var d = r.getData();
                    var bc = aitsBarcodeKeyFromData(d);
                    if (bc) {
                        byBc[bc] = Object.assign({}, d);
                    }
                });
            } catch (e2) {}
            var list = Object.keys(byBc).map(function (k) {
                return byBc[k];
            });
            if (list.length <= 1) {
                aitsBulkAssignedSelection = null;
                return;
            }
            aitsBulkAssignedSelection = list;
        } catch (e) {
            aitsBulkAssignedSelection = null;
        }
    }

    /** Capture multi-select *before* Tabulator row-drag mutates selection (SendingStart often sees only 1 row). */
    function aitsBindBulkPointerSnapshot(table, snapshotFn) {
        function bindEl() {
            var el = table.element;
            if (!el || el._aitsBulkPointerBound) {
                return;
            }
            el._aitsBulkPointerBound = true;
            el.addEventListener(
                'pointerdown',
                function () {
                    snapshotFn();
                },
                true
            );
        }
        table.on('tableBuilt', bindEl);
        setTimeout(bindEl, 0);
    }

    aitsBindBulkPointerSnapshot(tablePool, aitsSnapshotPoolBulkSelection);
    aitsBindBulkPointerSnapshot(tableAssigned, aitsSnapshotAssignedBulkSelection);

    /** Earliest possible snapshot (before Tabulator row-drag handlers run). */
    if (!window._aitsBulkDocPointerBound) {
        window._aitsBulkDocPointerBound = true;
        document.addEventListener(
            'pointerdown',
            function (ev) {
                try {
                    var pe = tablePool.element;
                    var ae = tableAssigned.element;
                    if (pe && pe.contains(ev.target)) {
                        aitsSnapshotPoolBulkSelection();
                    } else if (ae && ae.contains(ev.target)) {
                        aitsSnapshotAssignedBulkSelection();
                    }
                } catch (e) {}
            },
            true
        );
    }

    tablePool.on('movableRowsSendingStart', function () {
        aitsSnapshotPoolBulkSelection();
    });

    tablePool.on('rowMoving', function () {
        aitsSnapshotPoolBulkSelection();
    });

    tableAssigned.on('movableRowsSendingStart', function () {
        aitsSnapshotAssignedBulkSelection();
    });

    tableAssigned.on('rowMoving', function () {
        aitsSnapshotAssignedBulkSelection();
    });

    function aitsCompleteBulkPoolToAssigned(fromRow) {
        if (!aitsBulkPoolSelection || aitsBulkPoolSelection.length <= 1) {
            aitsBulkPoolSelection = null;
            return;
        }
        var sentBc = aitsBarcodeKeyFromData(fromRow.getData());
        var list = aitsBulkPoolSelection.slice();
        aitsBulkPoolSelection = null;
        list.forEach(function (d) {
            var bc = aitsBarcodeKeyFromData(d);
            if (!bc || bc === sentBc) {
                return;
            }
            var owner = aitsReservedBarcodeToOwner[bc];
            if (owner) {
                return;
            }
            var poolRows = tablePool.getRows();
            var still = poolRows.find(function (pr) {
                return aitsBarcodeKeyFromData(pr.getData()) === bc;
            });
            if (!still) {
                return;
            }
            var data = Object.assign({}, still.getData());
            tableAssigned.addRow(data, true);
            still.delete();
        });
        try {
            tablePool.deselectRow();
        } catch (e3) {}
        updateCounts();
    }

    function aitsCompleteBulkAssignedToPool(fromRow) {
        if (!aitsBulkAssignedSelection || aitsBulkAssignedSelection.length <= 1) {
            aitsBulkAssignedSelection = null;
            return;
        }
        var sentBc = aitsBarcodeKeyFromData(fromRow.getData());
        var list = aitsBulkAssignedSelection.slice();
        aitsBulkAssignedSelection = null;
        list.forEach(function (d) {
            var bc = aitsBarcodeKeyFromData(d);
            if (!bc || bc === sentBc) {
                return;
            }
            var asRows = tableAssigned.getRows();
            var still = asRows.find(function (ar) {
                return aitsBarcodeKeyFromData(ar.getData()) === bc;
            });
            if (!still) {
                return;
            }
            var data = Object.assign({}, still.getData());
            tablePool.addRow(data, true);
            still.delete();
        });
        try {
            tableAssigned.deselectRow();
        } catch (e3) {}
        updateCounts();
    }

    /** Receiving table: first row just arrived from pool; move the rest of the multi-select. */
    tableAssigned.on('movableRowsReceived', function (fromRow, toRow, fromTable) {
        try {
            if (fromTable && !aitsTabInstancesEqual(fromTable, tablePool)) {
                return;
            }
            aitsCompleteBulkPoolToAssigned(fromRow);
        } catch (e) {
            aitsBulkPoolSelection = null;
        }
    });

    /** Fallback if Received is not emitted (some Tabulator builds): sender sees successful drop on Assigned. */
    tablePool.on('movableRowsSent', function (fromRow, toRow, toTable) {
        try {
            if (toTable && !aitsTabInstancesEqual(toTable, tableAssigned)) {
                return;
            }
            if (!aitsBulkPoolSelection || aitsBulkPoolSelection.length <= 1) {
                return;
            }
            aitsCompleteBulkPoolToAssigned(fromRow);
        } catch (e) {
            aitsBulkPoolSelection = null;
        }
    });

    tablePool.on('movableRowsReceived', function (fromRow, toRow, fromTable) {
        try {
            if (fromTable && !aitsTabInstancesEqual(fromTable, tableAssigned)) {
                return;
            }
            aitsCompleteBulkAssignedToPool(fromRow);
        } catch (e) {
            aitsBulkAssignedSelection = null;
        }
    });

    tableAssigned.on('movableRowsSent', function (fromRow, toRow, toTable) {
        try {
            if (toTable && !aitsTabInstancesEqual(toTable, tablePool)) {
                return;
            }
            if (!aitsBulkAssignedSelection || aitsBulkAssignedSelection.length <= 1) {
                return;
            }
            aitsCompleteBulkAssignedToPool(fromRow);
        } catch (e) {
            aitsBulkAssignedSelection = null;
        }
    });

    function aitsStockUrl() {
        try {
            return new URL('ajax/rfid-available-stock.php', window.location.href).href;
        } catch (e) {
            return 'ajax/rfid-available-stock.php';
        }
    }

    function aitsBranchId() {
        if (typeof window.AITS_LOCKED_BRANCH_ID === 'number' && window.AITS_LOCKED_BRANCH_ID > 0) {
            return window.AITS_LOCKED_BRANCH_ID;
        }
        var el = document.getElementById('aitsBranch');
        var v = el ? parseInt(String(el.value || '').trim(), 10) : 0;
        return isNaN(v) ? 0 : v;
    }

    function aitsSalesPersonsByBranchUrl(bid) {
        try {
            var u = new URL('ajax/assign-inventory-sales-persons-by-branch.php', window.location.href);
            u.searchParams.set('branch_id', String(bid));
            return u.href;
        } catch (e) {
            return 'ajax/assign-inventory-sales-persons-by-branch.php?branch_id=' + encodeURIComponent(String(bid));
        }
    }

    function aitsSetSalesPersonOptions(names, previous) {
        var sel = document.getElementById('aitsSalesPerson');
        if (!sel) return;
        var prev = previous != null ? String(previous).trim() : '';
        sel.innerHTML = '';
        var o0 = document.createElement('option');
        o0.value = '';
        o0.textContent = '— Select —';
        sel.appendChild(o0);
        (names || []).forEach(function (nm) {
            var o = document.createElement('option');
            o.value = nm;
            o.textContent = nm;
            sel.appendChild(o);
        });
        if (prev && names && names.indexOf(prev) >= 0) {
            sel.value = prev;
        } else {
            sel.value = '';
        }
    }

    function aitsReloadSalesPersonOptionsForBranch(branchId, done) {
        var sel = document.getElementById('aitsSalesPerson');
        var prev = sel ? String(sel.value || '').trim() : '';
        if (!branchId || branchId <= 0) {
            aitsSetSalesPersonOptions([], '');
            if (typeof done === 'function') done();
            return;
        }
        fetch(aitsSalesPersonsByBranchUrl(branchId), { credentials: 'same-origin' })
            .then(function (r) {
                return r.json();
            })
            .then(function (j) {
                var names = j && j.success && Array.isArray(j.names) ? j.names : [];
                aitsSetSalesPersonOptions(names, prev);
                if (typeof done === 'function') done();
            })
            .catch(function () {
                aitsSetSalesPersonOptions([], '');
                if (typeof done === 'function') done();
            });
    }

    function aitsMapRfidStockToPoolRow(r) {
        var art = r.article != null ? String(r.article) : '';
        var br = r.branch != null ? String(r.branch) : '';
        var desc = art;
        if (br) {
            desc = art ? art + ' · ' + br : br;
        }
        return {
            barcode_no: r.barcode != null ? String(r.barcode) : '',
            rfid_code: r.rfid_code != null ? String(r.rfid_code) : '',
            product_name: r.product_name != null ? String(r.product_name) : '',
            imageUrls: '',
            amount: '',
            description: desc,
            design_no: '',
            gross_wt: r.gross_wt,
            final_wt: r.final_wt,
            invoice_no: r.invoice_no != null ? String(r.invoice_no) : '',
            metal_value: '',
            net_amount: '',
            net_amount_with_tax: '',
            quantity: r.qty,
            tax_amount: '',
            active: 'Yes'
        };
    }

    function aitsAssignedBarcodeSet() {
        var set = {};
        try {
            tableAssigned.getData().forEach(function (d) {
                var b = String(d.barcode_no || '').trim();
                if (b) set[b] = true;
            });
        } catch (e) {}
        return set;
    }

    /** barcode_no -> sale person name (assigned to someone else; do not show in pool / manual add). */
    var aitsReservedBarcodeToOwner = {};

    function aitsGlobalReservedUrl(sp) {
        try {
            var u = new URL('ajax/assign-inventory-globally-assigned-barcodes.php', window.location.href);
            if (sp) u.searchParams.set('sales_person', sp);
            var bid = aitsBranchId();
            if (bid > 0) u.searchParams.set('branch_id', String(bid));
            return u.href;
        } catch (e) {
            var q = [];
            if (sp) q.push('sales_person=' + encodeURIComponent(sp));
            var b = aitsBranchId();
            if (b > 0) q.push('branch_id=' + encodeURIComponent(String(b)));
            return 'ajax/assign-inventory-globally-assigned-barcodes.php' + (q.length ? '?' + q.join('&') : '');
        }
    }

    function loadReservedBarcodesForSalesPerson(sp, done) {
        fetch(aitsGlobalReservedUrl(sp), { credentials: 'same-origin' })
            .then(function (res) {
                return res.json();
            })
            .then(function (j) {
                aitsReservedBarcodeToOwner = j && j.map && typeof j.map === 'object' ? j.map : {};
                if (typeof done === 'function') done();
            })
            .catch(function () {
                aitsReservedBarcodeToOwner = {};
                if (typeof done === 'function') done();
            });
    }

    function loadPoolStockFromServer() {
        var btn = document.getElementById('aitsBtnRefreshStock');
        var spSel = document.getElementById('aitsSalesPerson');
        var sp = spSel ? String(spSel.value || '').trim() : '';
        var bid = aitsBranchId();
        if (bid <= 0) {
            if (btn) btn.disabled = false;
            try {
                tablePool.setData([]);
            } catch (e) {}
            updateCounts();
            return;
        }
        if (btn) btn.disabled = true;
        loadReservedBarcodesForSalesPerson(sp, function () {
            var stockUrl = aitsStockUrl();
            try {
                var u = new URL(stockUrl);
                u.searchParams.set('branch_id', String(bid));
                stockUrl = u.href;
            } catch (e) {
                stockUrl = stockUrl + (stockUrl.indexOf('?') >= 0 ? '&' : '?') + 'branch_id=' + encodeURIComponent(String(bid));
            }
            fetch(stockUrl, { credentials: 'same-origin' })
                .then(function (res) {
                    return res.json();
                })
                .then(function (j) {
                    if (!j || !j.success) {
                        alert((j && j.message) ? j.message : 'Could not load stock.');
                        return;
                    }
                    var assignedBc = aitsAssignedBarcodeSet();
                    var rows = (j.rows || []).filter(function (r) {
                        var b = String(r.barcode || '').trim();
                        return b && !assignedBc[b] && !aitsReservedBarcodeToOwner[b];
                    }).map(aitsMapRfidStockToPoolRow);
                    tablePool.setData(rows);
                })
                .catch(function () {
                    alert('Could not load stock. Check your connection or login.');
                })
                .finally(function () {
                    if (btn) btn.disabled = false;
                });
        });
    }

    var aitsStockLoadedOnce = false;
    function loadPoolStockOnce() {
        if (aitsStockLoadedOnce) return;
        aitsStockLoadedOnce = true;
        loadPoolStockFromServer();
    }

    tablePool.on('tableBuilt', loadPoolStockOnce);

    var btnRefreshStock = document.getElementById('aitsBtnRefreshStock');
    if (btnRefreshStock) {
        btnRefreshStock.addEventListener('click', function () {
            aitsStockLoadedOnce = true;
            loadPoolStockFromServer();
        });
    }

    function aitsLoadAssignedUrl(sp) {
        try {
            var u = new URL('ajax/assign-inventory-sales-team-load.php', window.location.href);
            u.searchParams.set('sales_person', sp);
            u.searchParams.set('branch_id', String(aitsBranchId()));
            return u.href;
        } catch (e) {
            return 'ajax/assign-inventory-sales-team-load.php?sales_person=' + encodeURIComponent(sp) + '&branch_id=' + encodeURIComponent(String(aitsBranchId()));
        }
    }

    function aitsSaveAssignUrl() {
        try {
            return new URL('ajax/assign-inventory-sales-team-save.php', window.location.href).href;
        } catch (e) {
            return 'ajax/assign-inventory-sales-team-save.php';
        }
    }

    /** Load server-saved rows for the selected sale person into the left grid, then call done(). */
    function loadAssignedForSalesPerson(sp, done) {
        if (!sp || aitsBranchId() <= 0) {
            tableAssigned.clearData();
            updateCounts();
            if (typeof done === 'function') done();
            return;
        }
        fetch(aitsLoadAssignedUrl(sp), { credentials: 'same-origin' })
            .then(function (r) {
                return r.json();
            })
            .then(function (j) {
                if (j && j.success && Array.isArray(j.rows)) {
                    tableAssigned.setData(j.rows);
                } else {
                    tableAssigned.clearData();
                }
                updateCounts();
                if (typeof done === 'function') done();
            })
            .catch(function () {
                tableAssigned.clearData();
                updateCounts();
                if (typeof done === 'function') done();
            });
    }

    function buildColumnPicker(table, listEl, filterInput) {
        listEl.innerHTML = '';
        table.getColumns().forEach(function (col) {
            var def = col.getDefinition();
            if (def.rowHandle) return;
            var field = def.field || def.title;
            var title = aitsHeaderTitlePlain(def) || field;
            var wrap = document.createElement('div');
            wrap.className = 'aits-cp-item';
            wrap.setAttribute('data-label', String(title).toLowerCase());
            var id = 'aits_cp_' + field + '_' + Math.random().toString(36).slice(2);
            var chk = document.createElement('input');
            chk.type = 'checkbox';
            chk.id = id;
            chk.checked = col.isVisible();
            chk.addEventListener('change', function () {
                if (chk.checked) col.show(); else col.hide();
            });
            var lab = document.createElement('label');
            lab.htmlFor = id;
            lab.textContent = title;
            wrap.appendChild(chk);
            wrap.appendChild(lab);
            listEl.appendChild(wrap);
        });
        filterInput.value = '';
        filterInput.oninput = function () {
            var q = filterInput.value.trim().toLowerCase();
            listEl.querySelectorAll('.aits-cp-item').forEach(function (el) {
                var lab = el.getAttribute('data-label') || '';
                el.classList.toggle('d-none', q !== '' && lab.indexOf(q) === -1);
            });
        };
    }

    function wireColPicker(btnId, ddId, listId, filterId, table) {
        var btn = document.getElementById(btnId);
        var dd = document.getElementById(ddId);
        var list = document.getElementById(listId);
        var filter = document.getElementById(filterId);
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var willOpen = !dd.classList.contains('show');
            document.querySelectorAll('.aits-columns-dd').forEach(function (d) { d.classList.remove('show'); });
            document.querySelectorAll('.aits-col-gear .btn-link').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
            if (willOpen) {
                buildColumnPicker(table, list, filter);
                dd.classList.add('show');
                btn.setAttribute('aria-expanded', 'true');
            }
        });
    }

    document.addEventListener('click', function () {
        document.querySelectorAll('.aits-columns-dd').forEach(function (d) { d.classList.remove('show'); });
        document.querySelectorAll('.aits-col-gear .btn-link').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
    });

    wireColPicker('aitsColBtnPool', 'aitsColDdPool', 'aitsColListPool', 'aitsColFilterPool', tablePool);
    wireColPicker('aitsColBtnAssigned', 'aitsColDdAssigned', 'aitsColListAssigned', 'aitsColFilterAssigned', tableAssigned);

    document.getElementById('aitsColDdPool').addEventListener('click', function (e) { e.stopPropagation(); });
    document.getElementById('aitsColDdAssigned').addEventListener('click', function (e) { e.stopPropagation(); });

    function applyAssignedFilter() {
        var term = document.getElementById('aitsSearchAssigned').value.trim().toLowerCase();
        if (!term) {
            tableAssigned.clearFilter(true);
            return;
        }
        tableAssigned.setFilter(function (data) {
            var blob = Object.keys(data).map(function (k) {
                var v = data[k];
                return v === null || v === undefined ? '' : String(v);
            }).join(' ').toLowerCase();
            return blob.indexOf(term) !== -1;
        });
    }

    document.getElementById('aitsSearchAssigned').addEventListener('input', applyAssignedFilter);

    document.getElementById('aitsBtnUnassign').addEventListener('click', function () {
        var rows = tableAssigned.getSelectedRows();
        if (!rows.length) {
            alert('Select one or more rows on the left (assigned) grid to unassign.');
            return;
        }
        rows.forEach(function (row) {
            var d = row.getData();
            tablePool.addRow(d, true);
            row.delete();
        });
        tableAssigned.deselectRow();
        updateCounts();
    });

    document.getElementById('aitsBtnSave').addEventListener('click', function () {
        var btn = this;
        var sp = document.getElementById('aitsSalesPerson').value;
        if (!sp) {
            alert('Select a sale person before saving.');
            return;
        }
        var assigned = tableAssigned.getData();
        if (!assigned.length) {
            if (!confirm('No rows in the sales person grid. Save anyway to clear saved assignments for this sale person?')) return;
        }
        btn.disabled = true;
        var br = aitsBranchId();
        if (br <= 0) {
            alert('Select a branch before saving.');
            btn.disabled = false;
            return;
        }
        fetch(aitsSaveAssignUrl(), {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ sales_person: sp, branch_id: br, rows: assigned })
        })
            .then(function (r) {
                return r.json();
            })
            .then(function (j) {
                if (!j || !j.success) {
                    alert((j && j.message) ? j.message : 'Save failed.');
                    btn.disabled = false;
                    return;
                }
                var n = j.saved_count != null ? j.saved_count : assigned.length;
                alert('Saved. ' + n + ' barcode(s) assigned to ' + sp + '.');
                // Reload this page after save so grids reflect saved assignments.
                var next = 'assign-inventory-to-sales-team.php';
                try {
                    var u = new URL(next, window.location.href);
                    if (sp) u.searchParams.set('sales_person', sp);
                    if (br > 0) u.searchParams.set('branch_id', String(br));
                    window.location.href = u.pathname + u.search;
                } catch (e) {
                    window.location.href = next + (sp ? ('?sales_person=' + encodeURIComponent(sp)) : '');
                }
            })
            .catch(function () {
                alert('Could not save. Check your connection or login.');
                btn.disabled = false;
            });
    });

    document.getElementById('aitsSalesPerson').addEventListener('change', function () {
        var sp = this.value;
        loadAssignedForSalesPerson(sp, function () {
            loadPoolStockFromServer();
        });
    });

    var aitsBranchEl = document.getElementById('aitsBranch');
    if (aitsBranchEl && aitsBranchEl.tagName === 'SELECT' && !(typeof window.AITS_LOCKED_BRANCH_ID === 'number' && window.AITS_LOCKED_BRANCH_ID > 0)) {
        aitsBranchEl.addEventListener('change', function () {
            var bid = aitsBranchId();
            aitsReloadSalesPersonOptionsForBranch(bid, function () {
                var sp = document.getElementById('aitsSalesPerson')
                    ? String(document.getElementById('aitsSalesPerson').value || '').trim()
                    : '';
                loadAssignedForSalesPerson(sp, function () {
                    loadPoolStockFromServer();
                });
            });
        });
    }

    function addBarcodeRow(code) {
        code = String(code || '').trim();
        if (!code) return;
        if (aitsBranchId() <= 0) {
            alert('Select a branch before adding a barcode.');
            return;
        }
        var other = aitsReservedBarcodeToOwner[code];
        if (other) {
            alert('Barcode ' + code + ' is already assigned to ' + other + '.');
            return;
        }
        var exists = tablePool.getRows().some(function (r) {
            return String(r.getData().barcode_no || '').trim() === code;
        });
        if (exists) {
            return;
        }
        tablePool.addRow({
            barcode_no: code,
            rfid_code: '',
            product_name: '',
            imageUrls: '',
            amount: '',
            description: '',
            design_no: '',
            gross_wt: '',
            final_wt: '',
            invoice_no: '',
            metal_value: '',
            net_amount: '',
            net_amount_with_tax: '',
            quantity: 1,
            tax_amount: '',
            active: 'Yes'
        }, true);
    }

    document.getElementById('aitsBarcodeIn').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            addBarcodeRow(this.value);
            this.value = '';
            this.focus();
        }
    });

    window.addEventListener('resize', function () {
        var h = Math.max(320, window.innerHeight - 280);
        tablePool.setHeight(h);
        tableAssigned.setHeight(h);
    });

    updateCounts();

    // After save reload: restore sale person from ?sales_person= and load their assignments.
    (function aitsRestoreFromQuery() {
        try {
            var params = new URLSearchParams(window.location.search || '');
            var spQ = String(params.get('sales_person') || '').trim();
            if (!spQ) return;
            var sel = document.getElementById('aitsSalesPerson');
            if (!sel) return;
            var found = false;
            for (var i = 0; i < sel.options.length; i++) {
                if (String(sel.options[i].value || '') === spQ) {
                    found = true;
                    break;
                }
            }
            if (!found) {
                var opt = document.createElement('option');
                opt.value = spQ;
                opt.textContent = spQ;
                sel.appendChild(opt);
            }
            sel.value = spQ;
            loadAssignedForSalesPerson(spQ, function () {
                loadPoolStockFromServer();
            });
        } catch (e) {}
    })();
})();
</script>
</body>
</html>
