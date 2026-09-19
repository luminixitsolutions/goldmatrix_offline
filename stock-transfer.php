<?php
require_once __DIR__ . '/includes/session_init.php';
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    header('Location: index.php');
    exit;
}

$st_tree_root_id = function_exists('auragold_branch_stock_transfer_tree_root_id')
    ? (int) auragold_branch_stock_transfer_tree_root_id()
    : (function_exists('auragold_settings_main_branch_id') ? (int) auragold_settings_main_branch_id() : 0);
if ($st_tree_root_id > 0) {
    $branches = getListMaster(
        'SELECT id, name, code FROM tbl_branches WHERE status = 1 AND (id = ' . $st_tree_root_id
        . ' OR IFNULL(main_branch_id, 0) = ' . $st_tree_root_id . ') ORDER BY name ASC'
    );
} else {
    $branches = getListMaster("SELECT id, name, code FROM tbl_branches WHERE status = 1 ORDER BY name ASC");
}
if (!is_array($branches)) {
    $branches = [];
}

$default_branch_id = 0;
if (!empty($_SESSION['working_branch_id'])) {
    $default_branch_id = (int) $_SESSION['working_branch_id'];
} elseif (!empty($_SESSION['branch_id'])) {
    $default_branch_id = (int) $_SESSION['branch_id'];
}
if ($default_branch_id > 0 && !empty($branches)) {
    $in_branch_list = false;
    foreach ($branches as $b) {
        if ((int) ($b['id'] ?? 0) === $default_branch_id) {
            $in_branch_list = true;
            break;
        }
    }
    if (!$in_branch_list) {
        $default_branch_id = 0;
        if ($st_tree_root_id > 0) {
            foreach ($branches as $b) {
                if ((int) ($b['id'] ?? 0) === $st_tree_root_id) {
                    $default_branch_id = $st_tree_root_id;
                    break;
                }
            }
        }
        if ($default_branch_id <= 0 && isset($branches[0]['id'])) {
            $default_branch_id = (int) $branches[0]['id'];
        }
    }
}

$today_ymd = date('Y-m-d');
$active_mysql_db = defined('DB_NAME') ? (string) DB_NAME : '';

$st_metals = [];
if (function_exists('getList')) {
    $st_metals_sql = "SELECT id, COALESCE(NULLIF(TRIM(display_name), ''), system_name, '') AS name FROM tbl_metal WHERE status = 1";
    if (function_exists('auragold_master_list_sql_suffix') && !empty($conn)) {
        $st_metals_sql .= ' ' . auragold_master_list_sql_suffix($conn, 'tbl_metal');
    }
    $st_metals_sql .= ' ORDER BY display_name ASC, id ASC';
    $st_metals = getList($st_metals_sql);
    if (!is_array($st_metals)) {
        $st_metals = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Stock Transfer — <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
<?php include __DIR__ . '/header-script.php'; ?>
<style>
    .st-wrap {
        --st-accent: #7c6fd6;
        --st-accent-soft: #ece9ff;
        --st-border: #e8e6f2;
    }
    .st-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 14px;
    }
    .st-toolbar-left, .st-toolbar-right {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 10px;
    }
    .st-date-wrap {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .st-date-wrap label {
        margin: 0;
        font-size: 13px;
        color: #4a5568;
    }
    .st-panel {
        background: #fff;
        border: 1px solid var(--st-border);
        border-radius: 12px;
        box-shadow: 0 4px 18px rgba(80, 72, 140, 0.06);
        min-height: 420px;
        display: flex;
        flex-direction: column;
    }
    .st-panel-head {
        padding: 12px 14px;
        border-bottom: 1px solid var(--st-border);
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 10px;
    }
    .st-panel-head .form-group {
        margin-bottom: 0;
    }
    .st-panel-title {
        font-weight: 650;
        font-size: 14px;
        color: #1d2c4f;
        margin-right: 8px;
    }
    .st-table-wrap {
        flex: 1;
        overflow: auto;
        max-height: calc(100vh - 280px);
    }
    .st-table {
        font-size: 13px;
        margin-bottom: 0;
    }
    /* Navy header + white text (overrides theme / Bootstrap .table rules) */
    .st-wrap .st-table thead th {
        background: #1a2d4a !important;
        color: #fff !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.15) !important;
        border-top: none !important;
        white-space: nowrap;
        font-weight: 600;
        vertical-align: middle;
        padding-top: 10px;
        padding-bottom: 10px;
    }
    .st-wrap .st-table thead th,
    .st-wrap .st-table thead th a {
        color: #fff !important;
    }
    .st-wrap .st-table thead th input[type="checkbox"] {
        filter: brightness(0) invert(1);
        cursor: pointer;
    }
    .st-wrap .st-table {
        border-collapse: collapse;
    }
    .st-wrap .st-table th,
    .st-wrap .st-table td {
        border: 1px solid #b8c0cc;
        vertical-align: middle;
    }
    .st-wrap .st-table thead th {
        border-color: rgba(255, 255, 255, 0.35);
    }
    .st-wrap .st-table tbody tr:hover td {
        background: #faf9ff;
    }
    .st-thumb {
        width: 36px;
        height: 36px;
        border-radius: 6px;
        background: var(--st-accent-soft);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--st-accent);
        font-size: 16px;
    }
    .st-footer-total {
        display: flex;
        justify-content: flex-end;
        padding: 10px 14px;
        border-top: 1px solid var(--st-border);
        font-weight: 600;
        background: #faf9ff;
    }
    .st-btn-outline-accent {
        border-color: var(--st-accent);
        color: var(--st-accent);
        background: #fff;
    }
    .st-btn-outline-accent:hover {
        background: var(--st-accent-soft);
        color: #5a4fc4;
    }
    .st-empty {
        text-align: center;
        padding: 48px 16px;
        color: #8892a6;
        font-size: 14px;
    }
    .st-filter-badge {
        position: relative;
    }
    .st-filter-badge .badge {
        position: absolute;
        top: -6px;
        right: -6px;
        font-size: 10px;
    }
    .st-filter-bar {
        padding: 8px 12px 10px;
        background: #fff;
    }
    .st-filter-input {
        border: none !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        padding-left: 2px;
        padding-right: 2px;
        background: transparent;
        border-bottom: 2px solid #ff9800 !important;
    }
    .st-filter-input:focus {
        outline: none;
        box-shadow: none !important;
        border-bottom-color: #f57c00 !important;
        background: transparent;
    }
    .st-filter-input::placeholder {
        color: #9ca3af;
    }
    .st-filter-bar.st-filter-bar-with-cols {
        display: flex;
        align-items: stretch;
        flex-wrap: wrap;
        gap: 4px;
    }
    .st-filter-bar.st-filter-bar-with-cols .st-filter-input {
        flex: 1 1 120px;
        min-width: 120px;
    }
    .st-col-settings-wrap {
        position: relative;
        flex-shrink: 0;
        align-self: center;
    }
    .st-col-settings-btn {
        color: #64748b !important;
        padding: 6px 8px !important;
        line-height: 1;
    }
    .st-col-settings-btn:hover {
        color: var(--st-accent) !important;
        background: var(--st-accent-soft) !important;
        border-radius: 6px;
    }
    .st-wrap .columns-dropdown {
        position: absolute;
        top: 100%;
        right: 0;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 1050;
        min-width: 260px;
        max-width: 320px;
        display: none;
        margin-top: 6px;
    }
    .st-wrap .columns-dropdown.show {
        display: block;
    }
    .st-wrap .columns-dropdown-header {
        padding: 10px 14px;
        border-bottom: 1px solid #e2e8f0;
        font-weight: 600;
        font-size: 0.85rem;
        color: #1d2c4f;
    }
    .st-wrap .columns-dropdown-search {
        padding: 8px 12px;
        border-bottom: 1px solid #e2e8f0;
    }
    .st-wrap .columns-dropdown-search input {
        width: 100%;
        padding: 6px 10px;
        border: 1px solid #e2e8f0;
        border-radius: 4px;
        font-size: 0.8rem;
    }
    .st-wrap .columns-dropdown-list {
        max-height: 280px;
        overflow-y: auto;
        padding: 6px 0;
    }
    .st-wrap .columns-dropdown-item {
        padding: 6px 14px;
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
    }
    .st-wrap .columns-dropdown-item:hover {
        background: #f8fafc;
    }
    .st-wrap .columns-dropdown-item input[type="checkbox"] {
        width: 16px;
        height: 16px;
        cursor: pointer;
    }
    .st-wrap .columns-dropdown-item label {
        margin: 0;
        cursor: pointer;
        font-size: 0.8rem;
        color: #334155;
        flex: 1;
    }
    .st-col-order-wrap {
        padding: 8px 12px 10px;
        border-bottom: 1px solid #e2e8f0;
        max-height: 200px;
        overflow-y: auto;
    }
    .st-col-order-wrap .st-col-order-title {
        font-size: 0.72rem;
        font-weight: 600;
        color: #64748b;
        margin-bottom: 6px;
    }
    .st-col-order-list {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .st-col-order-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6px 8px;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        background: #f8fafc;
        font-size: 0.78rem;
        color: #334155;
        cursor: grab;
        user-select: none;
    }
    .st-col-order-item:active {
        cursor: grabbing;
    }
    .st-col-order-item.st-col-order-dragging {
        opacity: 0.55;
    }
    .st-col-order-item .feather {
        flex-shrink: 0;
        color: #94a3b8;
    }
    .layout-content .container-fluid { padding-bottom: 12px; }
    /* Two blocks side by side (source | destination), like reference UI */
    .st-stock-transfer-row {
        align-items: stretch;
    }
    .st-stock-transfer-row > [class*="col-"] {
        display: flex;
        flex-direction: column;
    }
    .st-stock-transfer-row .st-panel {
        flex: 1 1 auto;
        width: 100%;
        min-height: min(520px, 70vh);
    }
    .st-table-wrap .st-table-wide {
        min-width: 2800px;
    }
    .st-img-cell img {
        width: 40px;
        height: 40px;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid var(--st-border);
    }
    .st-text-clip {
        max-width: 220px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .st-cell-num {
        font-variant-numeric: tabular-nums;
    }
    .st-source-row {
        cursor: grab;
    }
    .st-source-row:active {
        cursor: grabbing;
    }
    .st-source-row.st-dragging {
        opacity: 0.45;
    }
    .st-drag-handle {
        cursor: grab;
        user-select: none;
        width: 40px;
        text-align: center;
    }
    .st-drag-handle:active {
        cursor: grabbing;
    }
    #stDestDropZone.st-drop-active {
        outline: 2px dashed var(--st-accent);
        outline-offset: -2px;
        background: var(--st-accent-soft);
        border-radius: 8px;
    }
    #stLooseModal.st-loose-modal {
        z-index: 1060;
    }
    .st-loose-backdrop {
        z-index: 1055;
    }
    .st-loose-modal .modal-header {
        border-bottom: 1px solid var(--st-border);
        background: linear-gradient(180deg, #faf9ff 0%, #fff 100%);
    }
    .st-loose-modal .modal-title {
        font-size: 16px;
        font-weight: 650;
        color: #1d2c4f;
    }
    .st-loose-modal .form-group label {
        font-size: 12px;
        color: #4a5568;
        margin-bottom: 4px;
    }
    .st-loose-balance {
        display: flex;
        align-items: baseline;
        gap: 8px;
        padding: 10px 12px;
        background: var(--st-accent-soft);
        border: 1px solid var(--st-border);
        border-radius: 8px;
        margin-bottom: 12px;
    }
    .st-loose-balance .st-loose-balance-label {
        font-size: 12px;
        color: #5a6478;
    }
    .st-loose-balance .st-loose-balance-val {
        font-size: 18px;
        font-weight: 700;
        color: #1d2c4f;
        font-variant-numeric: tabular-nums;
    }
    .st-loose-balance .st-loose-balance-unit {
        font-size: 12px;
        color: #7c6fd6;
        font-weight: 600;
    }
    #stAdvFilterModal.st-adv-filter-modal {
        z-index: 1060;
    }
    .st-adv-filter-backdrop {
        z-index: 1055;
    }
    .st-adv-filter-modal .modal-header {
        border-bottom: 1px solid var(--st-border);
        background: linear-gradient(180deg, #faf9ff 0%, #fff 100%);
    }
    .st-adv-filter-modal .modal-title {
        font-size: 16px;
        font-weight: 650;
        color: #1d2c4f;
    }
    .st-adv-filter-modal .form-group label {
        font-size: 12px;
        color: #4a5568;
        margin-bottom: 4px;
        font-weight: 600;
    }
    .st-adv-filter-modal .modal-body {
        max-height: min(70vh, 520px);
        overflow-y: auto;
    }
    .st-adv-filter-modal .st-adv-actions .btn {
        min-width: 110px;
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

<div class="st-wrap p-3">
    <div class="st-toolbar">
        <div class="st-toolbar-left">
            <div class="st-date-wrap">
                <label for="stTransferDate">Date</label>
                <input type="date" class="form-control form-control-sm" id="stTransferDate" value="<?php echo htmlspecialchars($today_ymd); ?>" style="width:160px;">
                <button type="button" class="btn btn-sm btn-light border" id="stDateRefresh" title="Today"><i class="feather icon-refresh-cw"></i></button>
            </div>
        </div>
        <div class="st-toolbar-right">
            <button type="button" class="btn btn-sm st-btn-outline-accent" id="stBtnLoose" title="Transfer weight from untagged / loose stock">Transfer Loose Items</button>
            <button type="button" class="btn btn-sm btn-light border st-filter-badge" id="stBtnFilter" title="Filter"><i class="feather icon-filter"></i><span class="badge badge-danger" id="stFilterCount" style="display:none;">0</span></button>
            <button type="button" class="btn btn-sm btn-light border" id="stBtnRefreshAll" title="Reload source list"><i class="feather icon-refresh-cw"></i></button>
            <button type="button" class="btn btn-sm btn-secondary" id="stBtnSave" disabled>Save</button>
            <button type="button" class="btn btn-sm btn-outline-primary" id="stBtnPrintBc" title="Print barcode for items in transfer list">Print Barcode</button>
            <a href="stock-transfer-history.php" class="btn btn-sm btn-light border" title="View past transfers"><i class="feather icon-list"></i> History</a>
            <a href="stock-receive-history.php" class="btn btn-sm btn-light border" title="Stock received at branch after transfer"><i class="feather icon-download"></i> Receive</a>
        </div>
    </div>

    <div class="row st-stock-transfer-row">
        <div class="col-12 col-lg-6 mb-3">
            <div class="st-panel">
                <div class="st-panel-head">
                    <span class="st-panel-title">Source branch</span>
                    <div class="form-group">
                        <label class="small text-muted mb-0">Branch</label>
                        <select class="form-control form-control-sm" id="stFromBranch" style="min-width:180px;">
                            <option value="">— Select —</option>
                            <?php foreach ($branches as $b): ?>
                                <?php $bid = (int) ($b['id'] ?? 0); ?>
                                <option value="<?php echo $bid; ?>"<?php echo ($default_branch_id > 0 && $default_branch_id === $bid) ? ' selected' : ''; ?>><?php echo htmlspecialchars($b['name'] ?? ''); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="button" class="btn btn-sm btn-primary" id="stApplySource" title="Loads lines with on-hand qty. or weight &gt; 0 at the selected branch (non-outward)">Apply</button>
                    <div class="flex-grow-1"></div>
                    <div class="form-group" style="min-width:200px;">
                        <label class="small text-muted mb-0">Barcode</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" id="stBarcodeIn" placeholder="Scan or enter" autocomplete="off">
                            <div class="input-group-append">
                                <span class="input-group-text bg-white"><i class="feather icon-maximize-2" style="font-size:14px;"></i></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="st-filter-bar border-bottom st-filter-bar-with-cols">
                    <input type="text" class="form-control form-control-sm st-filter-input" id="stSourceFilter" placeholder="Filter by name / barcode / article…" autocomplete="off">
                    <div class="st-col-settings-wrap">
                        <button type="button" class="btn btn-sm btn-link st-col-settings-btn" id="stSourceColBtn" title="Show / hide columns" aria-haspopup="true" aria-expanded="false"><i class="feather icon-settings" style="font-size:18px;"></i></button>
                        <div class="columns-dropdown" id="stSourceColDropdown" aria-hidden="true">
                            <div class="columns-dropdown-header">Columns</div>
                            <div class="columns-dropdown-search">
                                <input type="text" id="stSourceColSearch" placeholder="Search columns…" autocomplete="off">
                            </div>
                            <div class="st-col-order-wrap">
                                <div class="st-col-order-title">Column order (drag)</div>
                                <div class="st-col-order-list" id="stSourceColOrderList"></div>
                            </div>
                            <div class="columns-dropdown-list" id="stSourceColList"></div>
                        </div>
                    </div>
                </div>
                <div class="st-table-wrap">
                    <table class="table table-sm table-bordered st-table st-table-wide" id="stTableSource">
                        <thead>
                            <tr>
                                <th data-col="st_cb" style="width:36px;"><input type="checkbox" id="stSourceSelectAll" title="Select all"></th>
                                <th data-col="st_drag" style="width:40px;"></th>
                                <th data-col="st_img" style="width:48px;">Img</th>
                                <th data-col="net_amt" class="text-right">Net Amt</th>
                                <th data-col="date">Date</th>
                                <th data-col="view" class="text-center">View</th>
                                <th data-col="barcode">Barcode...</th>
                                <th data-col="product_name" style="min-width:140px;">Product Name</th>
                                <th data-col="rfid">RFID</th>
                                <th data-col="location">Location</th>
                                <th data-col="against_invoice">Against Invoice No</th>
                                <th data-col="type_of_voucher">Type Of Voucher</th>
                                <th data-col="voucher_type">Voucher Type</th>
                                <th data-col="invoice">Invoice</th>
                                <th data-col="branch">Branch</th>
                                <th data-col="qty" class="text-right">Qty.</th>
                                <th data-col="gross_wt" class="text-right">Gross Wt</th>
                                <th data-col="purity" class="text-right">Pu</th>
                                <th data-col="pure_wt" class="text-right">Pure Wt.</th>
                                <th data-col="requested_qty" class="text-right">Requested Qty</th>
                                <th data-col="requested_wt" class="text-right">Requested Wt</th>
                                <th data-col="stone_wt" class="text-right">Stone Wt</th>
                                <th data-col="diamond_wt" class="text-right">Diamond Wt</th>
                                <th data-col="less_wt" class="text-right">Less Wt.</th>
                                <th data-col="purity_wt" class="text-right">Purity Wt</th>
                                <th data-col="wastage_per" class="text-right">Wastage Per.</th>
                                <th data-col="wastage_wt" class="text-right">Wastage Wt.</th>
                                <th data-col="net_wt" class="text-right">Net Wt</th>
                                <th data-col="alloy_wt" class="text-right">Alloy Wt.</th>
                                <th data-col="final_wt" class="text-right">Final Wt</th>
                                <th data-col="standard_wt" class="text-right">Standard Wt</th>
                                <th data-col="actual_wt" class="text-right">Actual Wt</th>
                                <th data-col="national_wt" class="text-right">National Wt</th>
                                <th data-col="name">Name</th>
                                <th data-col="making_rate" class="text-right">Making Rate</th>
                                <th data-col="amt" class="text-right">Amt</th>
                                <th data-col="making_amt" class="text-right">Making Amt</th>
                                <th data-col="amount" class="text-right">Amount</th>
                                <th data-col="hui_code">HUI Code</th>
                                <th data-col="packet_wt" class="text-right">Packet Wt</th>
                                <th data-col="packet_length" class="text-right">Packet L.</th>
                                <th data-col="rate" class="text-right">Rate</th>
                                <th data-col="hallmark1">Hallmark 1</th>
                                <th data-col="hallmark2">Hallmark 2</th>
                                <th data-col="net_amt_with_tax" class="text-right">Net Amt W/Tax</th>
                                <th data-col="tax_amt" class="text-right">Tax Amt</th>
                                <th data-col="discount_per" class="text-right">Discount %</th>
                                <th data-col="discount_amt" class="text-right">Discount Amt</th>
                                <th data-col="metal_value" class="text-right">Metal Val.</th>
                                <th data-col="purchase" class="text-right">Purchase</th>
                            </tr>
                        </thead>
                        <tbody id="stTableSourceBody">
                            <tr><td colspan="50" class="st-empty">Select branch and Apply to load stock.</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="st-footer-total">
                    <span>Amount: <span id="stSourceTotal">0.00</span></span>
                </div>
            </div>
        </div>
        <div class="col-12 col-lg-6 mb-3">
            <div class="st-panel">
                <div class="st-panel-head">
                    <span class="st-panel-title">Destination branch</span>
                    <div class="form-group">
                        <label class="small text-muted mb-0">Branch</label>
                        <select class="form-control form-control-sm" id="stToBranch" style="min-width:200px;">
                            <option value="">— Select —</option>
                            <?php foreach ($branches as $b): ?>
                                <option value="<?php echo (int) $b['id']; ?>"><?php echo htmlspecialchars($b['name'] ?? ''); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="stAddSelected" title="Move selected rows here, or drag rows from the left table into the grid below"><i class="feather icon-arrow-right"></i> Add selected</button>
                    <button type="button" class="btn btn-sm btn-light border" id="stClearDest" title="Clear transfer list">Clear</button>
                </div>
                <div class="st-filter-bar border-bottom st-filter-bar-with-cols">
                    <input type="text" class="form-control form-control-sm st-filter-input" id="stDestFilter" placeholder="Filter by name / barcode / article…" autocomplete="off">
                    <div class="st-col-settings-wrap">
                        <button type="button" class="btn btn-sm btn-link st-col-settings-btn" id="stDestColBtn" title="Show / hide columns" aria-haspopup="true" aria-expanded="false"><i class="feather icon-settings" style="font-size:18px;"></i></button>
                        <div class="columns-dropdown" id="stDestColDropdown" aria-hidden="true">
                            <div class="columns-dropdown-header">Columns</div>
                            <div class="columns-dropdown-search">
                                <input type="text" id="stDestColSearch" placeholder="Search columns…" autocomplete="off">
                            </div>
                            <div class="st-col-order-wrap">
                                <div class="st-col-order-title">Column order (drag)</div>
                                <div class="st-col-order-list" id="stDestColOrderList"></div>
                            </div>
                            <div class="columns-dropdown-list" id="stDestColList"></div>
                        </div>
                    </div>
                </div>
                <div class="st-table-wrap" id="stDestDropZone" title="Drop rows here to add to transfer list">
                    <table class="table table-sm table-bordered st-table st-table-wide" id="stTableDest">
                        <thead>
                            <tr>
                                <th data-col="st_cb" style="width:36px;"><input type="checkbox" id="stDestSelectAll"></th>
                                <th data-col="net_amt" class="text-right">Net Amt</th>
                                <th data-col="date">Date</th>
                                <th data-col="view" class="text-center">View</th>
                                <th data-col="barcode">Barcode...</th>
                                <th data-col="product_name" style="min-width:140px;">Product Name</th>
                                <th data-col="rfid">RFID</th>
                                <th data-col="location">Location</th>
                                <th data-col="against_invoice">Against Invoice No</th>
                                <th data-col="type_of_voucher">Type Of Voucher</th>
                                <th data-col="voucher_type">Voucher Type</th>
                                <th data-col="invoice">Invoice</th>
                                <th data-col="branch">Branch</th>
                                <th data-col="qty" class="text-right">Qty.</th>
                                <th data-col="gross_wt" class="text-right">Gross Wt</th>
                                <th data-col="purity" class="text-right">Pu</th>
                                <th data-col="pure_wt" class="text-right">Pure Wt.</th>
                                <th data-col="requested_qty" class="text-right">Requested Qty</th>
                                <th data-col="requested_wt" class="text-right">Requested Wt</th>
                                <th data-col="stone_wt" class="text-right">Stone Wt</th>
                                <th data-col="diamond_wt" class="text-right">Diamond Wt</th>
                                <th data-col="less_wt" class="text-right">Less Wt.</th>
                                <th data-col="purity_wt" class="text-right">Purity Wt</th>
                                <th data-col="wastage_per" class="text-right">Wastage Per.</th>
                                <th data-col="wastage_wt" class="text-right">Wastage Wt.</th>
                                <th data-col="net_wt" class="text-right">Net Wt</th>
                                <th data-col="alloy_wt" class="text-right">Alloy Wt.</th>
                                <th data-col="final_wt" class="text-right">Final Wt</th>
                                <th data-col="standard_wt" class="text-right">Standard Wt</th>
                                <th data-col="actual_wt" class="text-right">Actual Wt</th>
                                <th data-col="national_wt" class="text-right">National Wt</th>
                                <th data-col="name">Name</th>
                                <th data-col="making_rate" class="text-right">Making Rate</th>
                                <th data-col="amt" class="text-right">Amt</th>
                                <th data-col="making_amt" class="text-right">Making Amt</th>
                                <th data-col="amount" class="text-right">Amount</th>
                                <th data-col="hui_code">HUI Code</th>
                                <th data-col="packet_wt" class="text-right">Packet Wt</th>
                                <th data-col="packet_length" class="text-right">Packet L.</th>
                                <th data-col="rate" class="text-right">Rate</th>
                                <th data-col="hallmark1">Hallmark 1</th>
                                <th data-col="hallmark2">Hallmark 2</th>
                                <th data-col="net_amt_with_tax" class="text-right">Net Amt W/Tax</th>
                                <th data-col="tax_amt" class="text-right">Tax Amt</th>
                                <th data-col="discount_per" class="text-right">Discount %</th>
                                <th data-col="discount_amt" class="text-right">Discount Amt</th>
                                <th data-col="metal_value" class="text-right">Metal Val.</th>
                                <th data-col="purchase" class="text-right">Purchase</th>
                                <th data-col="st_remove" style="width:72px;"></th>
                            </tr>
                        </thead>
                        <tbody id="stTableDestBody">
                            <tr><td colspan="49" class="st-empty">No Rows To Show</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="st-footer-total">
                    <span>Amount: <span id="stDestTotal">0.00</span></span>
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

<!-- Advance Filter modal -->
<div class="modal fade st-adv-filter-modal" id="stAdvFilterModal" tabindex="-1" role="dialog" aria-labelledby="stAdvFilterTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width:480px;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="stAdvFilterTitle">Advance Filter</h5>
                <button type="button" class="close" id="stAdvFilterCloseBtn" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="stAdvBranch">Branch</label>
                    <select class="form-control form-control-sm" id="stAdvBranch">
                        <option value="">— Select —</option>
                        <?php foreach ($branches as $b): ?>
                            <?php $bid = (int) ($b['id'] ?? 0); if ($bid <= 0) continue; ?>
                            <option value="<?php echo $bid; ?>"><?php echo htmlspecialchars($b['name'] ?? ''); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="stAdvMetal">Metal</label>
                    <select class="form-control form-control-sm" id="stAdvMetal">
                        <option value="">Select Metal</option>
                        <?php foreach ($st_metals as $m): ?>
                            <?php $mid = (int) ($m['id'] ?? 0); if ($mid <= 0) continue; ?>
                            <option value="<?php echo htmlspecialchars($m['name'] ?? ''); ?>" data-metal-id="<?php echo $mid; ?>"><?php echo htmlspecialchars($m['name'] ?? ''); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="stAdvProduct">Product Name</label>
                    <select class="form-control form-control-sm" id="stAdvProduct">
                        <option value="">Select Product Name</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="stAdvArticle">Article</label>
                    <select class="form-control form-control-sm" id="stAdvArticle">
                        <option value="">Select Article</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="stAdvBarcode">Barcode No</label>
                    <input type="text" class="form-control form-control-sm" id="stAdvBarcode" placeholder="Barcode No" autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="stAdvDesign">Design No</label>
                    <input type="text" class="form-control form-control-sm" id="stAdvDesign" placeholder="Design No" autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="stAdvInvoice">Invoice No.</label>
                    <input type="text" class="form-control form-control-sm" id="stAdvInvoice" placeholder="Invoice No." autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="stAdvGrossWt">Gross wt.</label>
                    <input type="number" class="form-control form-control-sm" id="stAdvGrossWt" min="0" step="0.001" placeholder="Gross wt." autocomplete="off">
                </div>
                <div class="custom-control custom-checkbox mb-0">
                    <input type="checkbox" class="custom-control-input" id="stAdvZeroGross">
                    <label class="custom-control-label" for="stAdvZeroGross" style="font-size:13px;">Display barcodes with 0 Gross Wt.</label>
                </div>
            </div>
            <div class="modal-footer st-adv-actions flex-wrap justify-content-end">
                <button type="button" class="btn btn-sm btn-outline-primary" id="stAdvApplyTransfer">Apply &amp; Transfer</button>
                <button type="button" class="btn btn-sm btn-primary" id="stAdvApplyFilter">Apply Filter</button>
                <button type="button" class="btn btn-sm btn-outline-danger" id="stAdvClearFilter">Clear Filter</button>
            </div>
        </div>
    </div>
</div>

<!-- Transfer Loose Items modal -->
<div class="modal fade st-loose-modal" id="stLooseModal" tabindex="-1" role="dialog" aria-labelledby="stLooseModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="stLooseModalTitle">Transfer Loose Items</h5>
                <button type="button" class="close" id="stLooseCloseBtn" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="stLooseBarcode">Barcode No</label>
                    <input type="text" class="form-control form-control-sm" id="stLooseBarcode" placeholder="Scan or type barcode, then press Enter" autocomplete="off">
                    <small class="text-muted">Enter barcode to auto-fill metal, product, and balance stock.</small>
                </div>
                <div class="form-group">
                    <label for="stLooseMetal">Metal</label>
                    <select class="form-control form-control-sm" id="stLooseMetal">
                        <option value="">— Select metal —</option>
                        <?php foreach ($st_metals as $m): ?>
                            <?php $mid = (int) ($m['id'] ?? 0); if ($mid <= 0) continue; ?>
                            <option value="<?php echo $mid; ?>"><?php echo htmlspecialchars($m['name'] ?? ''); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="stLooseProduct">Product</label>
                    <select class="form-control form-control-sm" id="stLooseProduct" disabled>
                        <option value="">— Select metal first —</option>
                    </select>
                </div>
                <div class="st-loose-balance" id="stLooseBalanceBox" aria-live="polite">
                    <span class="st-loose-balance-label">Balance stock</span>
                    <span class="st-loose-balance-val" id="stLooseBalanceWt">0.000</span>
                    <span class="st-loose-balance-unit">Wt</span>
                    <span class="st-loose-balance-label ml-2" id="stLooseBalanceQtyWrap">Qty <strong id="stLooseBalanceQty">0</strong></span>
                </div>
                <div class="row">
                    <div class="col-6">
                        <div class="form-group mb-0">
                            <label for="stLooseTransferWt">Transfer Wt</label>
                            <input type="number" class="form-control form-control-sm" id="stLooseTransferWt" min="0" step="0.001" placeholder="0.000" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-group mb-0">
                            <label for="stLooseTransferQty">Transfer Qty</label>
                            <input type="number" class="form-control form-control-sm" id="stLooseTransferQty" min="0" step="0.001" placeholder="0" autocomplete="off">
                        </div>
                    </div>
                </div>
                <small class="text-muted d-block mt-2">Adds weight and quantity to the destination transfer list. Click toolbar Save to deduct stock and create outward entry.</small>
                <div class="alert alert-danger py-2 px-3 mt-3 mb-0" id="stLooseFormError" style="display:none;font-size:13px;" role="alert"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-light border" id="stLooseCancelBtn">Cancel</button>
                <button type="button" class="btn btn-sm btn-primary" id="stLooseSaveBtn">Add to Transfer</button>
            </div>
        </div>
    </div>
</div>

<script src="assets/libs/bootstrap-sweetalert/bootstrap-sweetalert.js"></script>
<script>
(function () {
    var todayYmd = <?php echo json_encode($today_ymd); ?>;
    var defaultBranchId = <?php echo (int) $default_branch_id; ?>;
    var stLooseBalanceWt = 0;
    var stLooseBalanceQty = 0;
    var stLooseProductsCache = [];
    var stLooseScannedBarcode = '';
    var stLooseBarcodeLookupBusy = false;

    function stSwalOrAlert(opts) {
        if (typeof swal === 'function') {
            swal(opts);
        } else {
            alert((opts.title ? opts.title + '\n\n' : '') + (opts.text || ''));
        }
    }

    function stShowMsg(title, text, type) {
        stSwalOrAlert({
            title: title || (type === 'error' ? 'Error' : 'Notice'),
            text: text || '',
            type: type || 'warning',
            confirmButtonText: 'OK'
        });
    }

    var sourceRows = [];
    var destRows = [];
    var filterText = '';
    var filterTextDest = '';
    var advFilter = {
        branch_id: '',
        metal: '',
        product: '',
        article: '',
        barcode: '',
        design: '',
        invoice: '',
        gross_wt: '',
        zero_gross: false
    };

    function fmtMoney(n) {
        var x = parseFloat(n) || 0;
        return x.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function fmtOptNum(n) {
        if (n === null || n === undefined || n === '') return '—';
        var x = parseFloat(n);
        if (isNaN(x)) return '—';
        return String(x);
    }

    function fmtMoneyDash(v) {
        if (v === null || v === undefined || v === '') return '—';
        return fmtMoney(v);
    }

    function fmtTextDash(s) {
        if (s === null || s === undefined || String(s).trim() === '') return '—';
        return String(s);
    }

    function firstImageSrc(raw) {
        if (!raw) return '';
        var s = String(raw).trim();
        if (!s) return '';
        if (s.charAt(0) === '[') {
            try {
                var arr = JSON.parse(s);
                if (Array.isArray(arr) && arr[0]) return String(arr[0]).trim();
            } catch (e) {}
        }
        var part = s.split(',')[0].trim().replace(/^["']|["']$/g, '');
        if (part.indexOf('http') === 0 || part.indexOf('/') === 0) return part;
        if (part) return part;
        return '';
    }

    function thumbHtml(imageUrls) {
        var src = firstImageSrc(imageUrls);
        if (src) {
            return '<div class="st-img-cell"><img src="' + escapeHtml(src) + '" alt=""></div>';
        }
        return '<div class="st-thumb"><i class="feather icon-image"></i></div>';
    }

    function fmtDateDmY(d) {
        if (d === null || d === undefined || d === '') return '—';
        var s = String(d);
        if (s.indexOf(' ') >= 0) s = s.split(' ')[0];
        var p = s.split('-');
        if (p.length === 3) return p[2] + '/' + p[1] + '/' + p[0];
        return s;
    }

    function fmtNumFixed(n, minF, maxF) {
        if (n === null || n === undefined || n === '') {
            n = 0;
        }
        var x = parseFloat(n);
        if (isNaN(x)) {
            return '—';
        }
        return x.toLocaleString(undefined, { minimumFractionDigits: minF, maximumFractionDigits: maxF });
    }

    function viewCellInner(r) {
        var iid = parseInt(r.invoice_id, 10) || 0;
        if (iid > 0) {
            return '<a href="purchase-invoice.php?id=' + iid + '" class="st-view-link" target="_blank" rel="noopener" draggable="false">View</a>';
        }
        return '<span class="text-muted">-</span>';
    }

    /** Each data column td; order is applied by rowDataCellsHtml(which). */
    function stBuildDataCellsParts(r) {
        var invNo = r.against_invoice_no != null ? String(r.against_invoice_no) : '';
        var vt = r.type_of_voucher != null ? String(r.type_of_voucher) : '';
        var vtype = r.voucher_type != null ? String(r.voucher_type) : '';
        return {
            net_amt: '<td data-col="net_amt" class="text-right st-cell-num">' + fmtMoney(r.net_amt != null ? r.net_amt : 0) + '</td>',
            date: '<td data-col="date">' + escapeHtml(fmtDateDmY(r.transaction_date)) + '</td>',
            view: '<td data-col="view" class="text-center">' + viewCellInner(r) + '</td>',
            barcode: '<td data-col="barcode">' + escapeHtml(fmtTextDash(r.barcode)) + '</td>',
            product_name: '<td data-col="product_name" class="st-text-clip" style="max-width:220px;" title="' + escapeHtml(String(r.product_name != null ? r.product_name : '')) + '">' + escapeHtml(fmtTextDash(r.product_name)) + '</td>',
            rfid: '<td data-col="rfid">' + escapeHtml(fmtTextDash(r.rfid)) + '</td>',
            location: '<td data-col="location">' + escapeHtml(fmtTextDash(r.location)) + '</td>',
            against_invoice: '<td data-col="against_invoice">' + escapeHtml(fmtTextDash(r.against_invoice_no)) + '</td>',
            type_of_voucher: '<td data-col="type_of_voucher">' + escapeHtml(fmtTextDash(vt)) + '</td>',
            voucher_type: '<td data-col="voucher_type">' + escapeHtml(fmtTextDash(vtype)) + '</td>',
            invoice: '<td data-col="invoice">' + escapeHtml(fmtTextDash(invNo)) + '</td>',
            branch: '<td data-col="branch">' + escapeHtml(fmtTextDash(r.branch_name)) + '</td>',
            qty: '<td data-col="qty" class="text-right st-cell-num">' + fmtNumFixed(r.qty, 0, 0) + '</td>',
            gross_wt: '<td data-col="gross_wt" class="text-right st-cell-num">' + fmtNumFixed(r.gross_wt, 3, 3) + '</td>',
            purity: '<td data-col="purity" class="text-right st-cell-num">' + fmtNumFixed(r.purity, 2, 2) + '</td>',
            pure_wt: '<td data-col="pure_wt" class="text-right st-cell-num">' + fmtNumFixed(r.pure_wt, 3, 3) + '</td>',
            requested_qty: '<td data-col="requested_qty" class="text-right st-cell-num">' + fmtNumFixed(r.requested_qty, 3, 3) + '</td>',
            requested_wt: '<td data-col="requested_wt" class="text-right st-cell-num">' + fmtNumFixed(r.requested_wt, 2, 2) + '</td>',
            stone_wt: '<td data-col="stone_wt" class="text-right st-cell-num">' + fmtNumFixed(r.stone_wt, 2, 2) + '</td>',
            diamond_wt: '<td data-col="diamond_wt" class="text-right st-cell-num">' + fmtNumFixed(r.diamond_wt, 2, 2) + '</td>',
            less_wt: '<td data-col="less_wt" class="text-right st-cell-num">' + fmtNumFixed(r.less_wt, 3, 3) + '</td>',
            purity_wt: '<td data-col="purity_wt" class="text-right st-cell-num">' + fmtNumFixed(r.purity_wt, 3, 3) + '</td>',
            wastage_per: '<td data-col="wastage_per" class="text-right st-cell-num">' + fmtNumFixed(r.wastage_per, 2, 2) + '</td>',
            wastage_wt: '<td data-col="wastage_wt" class="text-right st-cell-num">' + fmtNumFixed(r.wastage_wt, 3, 3) + '</td>',
            net_wt: '<td data-col="net_wt" class="text-right st-cell-num">' + fmtNumFixed(r.net_wt, 3, 3) + '</td>',
            alloy_wt: '<td data-col="alloy_wt" class="text-right st-cell-num">' + fmtNumFixed(r.alloy_wt, 3, 3) + '</td>',
            final_wt: '<td data-col="final_wt" class="text-right st-cell-num">' + fmtNumFixed(r.final_wt, 3, 3) + '</td>',
            standard_wt: '<td data-col="standard_wt" class="text-right st-cell-num">' + fmtNumFixed(r.standard_wt, 3, 3) + '</td>',
            actual_wt: '<td data-col="actual_wt" class="text-right st-cell-num">' + fmtNumFixed(r.actual_wt, 3, 3) + '</td>',
            national_wt: '<td data-col="national_wt" class="text-right st-cell-num">' + fmtNumFixed(r.national_wt, 3, 3) + '</td>',
            name: '<td data-col="name">' + escapeHtml(fmtTextDash(r.name)) + '</td>',
            making_rate: '<td data-col="making_rate" class="text-right st-cell-num">' + fmtNumFixed(r.making_rate, 2, 2) + '</td>',
            amt: '<td data-col="amt" class="text-right st-cell-num">' + fmtNumFixed(r.amt, 2, 2) + '</td>',
            making_amt: '<td data-col="making_amt" class="text-right st-cell-num">' + fmtNumFixed(r.making_amt, 2, 2) + '</td>',
            amount: '<td data-col="amount" class="text-right st-cell-num">' + fmtNumFixed(r.amount, 2, 2) + '</td>',
            hui_code: '<td data-col="hui_code">' + escapeHtml(fmtTextDash(r.hui_code)) + '</td>',
            packet_wt: '<td data-col="packet_wt" class="text-right st-cell-num">' + fmtNumFixed(r.packet_wt, 3, 3) + '</td>',
            packet_length: '<td data-col="packet_length" class="text-right st-cell-num">' + fmtNumFixed(r.packet_length, 3, 3) + '</td>',
            rate: '<td data-col="rate" class="text-right st-cell-num">' + fmtNumFixed(r.rate, 2, 2) + '</td>',
            hallmark1: '<td data-col="hallmark1">' + escapeHtml(fmtTextDash(r.hallmark1)) + '</td>',
            hallmark2: '<td data-col="hallmark2">' + escapeHtml(fmtTextDash(r.hallmark2)) + '</td>',
            net_amt_with_tax: '<td data-col="net_amt_with_tax" class="text-right st-cell-num">' + fmtNumFixed(r.net_amt_with_tax, 2, 2) + '</td>',
            tax_amt: '<td data-col="tax_amt" class="text-right st-cell-num">' + fmtNumFixed(r.tax_amt, 2, 2) + '</td>',
            discount_per: '<td data-col="discount_per" class="text-right st-cell-num">' + fmtNumFixed(r.discount_per, 2, 2) + '</td>',
            discount_amt: '<td data-col="discount_amt" class="text-right st-cell-num">' + fmtNumFixed(r.discount_amt, 2, 2) + '</td>',
            metal_value: '<td data-col="metal_value" class="text-right st-cell-num">' + fmtNumFixed(r.metal_value, 2, 2) + '</td>',
            purchase: '<td data-col="purchase" class="text-right st-cell-num">' + fmtNumFixed(r.purchase, 2, 2) + '</td>'
        };
    }

    function rowDataCellsHtml(r, which) {
        var parts = stBuildDataCellsParts(r);
        var order = stGetDataColOrder(which);
        var h = '';
        order.forEach(function (key) {
            if (parts[key]) {
                h += parts[key];
            }
        });
        return h;
    }

    function stAdvFilterActiveCount() {
        var n = 0;
        if (advFilter.branch_id) n++;
        if (advFilter.metal) n++;
        if (advFilter.product) n++;
        if (advFilter.article) n++;
        if (advFilter.barcode) n++;
        if (advFilter.design) n++;
        if (advFilter.invoice) n++;
        if (advFilter.gross_wt !== '' && advFilter.gross_wt != null && !isNaN(parseFloat(advFilter.gross_wt))) n++;
        if (advFilter.zero_gross) n++;
        return n;
    }

    function stUpdateFilterBadge() {
        var fc = document.getElementById('stFilterCount');
        if (!fc) return;
        var n = stAdvFilterActiveCount() + (filterText ? 1 : 0);
        if (n > 0) {
            fc.style.display = 'inline';
            fc.textContent = String(n);
        } else {
            fc.style.display = 'none';
            fc.textContent = '0';
        }
    }

    function rowMatchesAdvFilter(r) {
        if (stAdvFilterActiveCount() <= 0) return true;
        if (advFilter.branch_id) {
            var rb = String(r.branch_id != null ? r.branch_id : '');
            if (rb && rb !== String(advFilter.branch_id)) return false;
        }
        if (advFilter.metal) {
            var mn = String(r.metal_name != null ? r.metal_name : '').toLowerCase();
            if (mn !== String(advFilter.metal).toLowerCase()) return false;
        }
        if (advFilter.product) {
            var pn = String(r.product_name != null ? r.product_name : '').toLowerCase();
            if (pn !== String(advFilter.product).toLowerCase()) return false;
        }
        if (advFilter.article) {
            var art = String(r.article != null ? r.article : '').toLowerCase();
            if (art !== String(advFilter.article).toLowerCase()) return false;
        }
        if (advFilter.barcode) {
            var bc = String(r.barcode != null ? r.barcode : '').toLowerCase();
            if (bc.indexOf(String(advFilter.barcode).toLowerCase()) < 0) return false;
        }
        if (advFilter.design) {
            var dn = String(r.design_no != null ? r.design_no : (r.sku_code != null ? r.sku_code : '')).toLowerCase();
            if (dn.indexOf(String(advFilter.design).toLowerCase()) < 0) return false;
        }
        if (advFilter.invoice) {
            var inv = String(
                r.against_invoice_no != null ? r.against_invoice_no
                    : (r.against_invoice != null ? r.against_invoice : (r.invoice != null ? r.invoice : ''))
            ).toLowerCase();
            if (inv.indexOf(String(advFilter.invoice).toLowerCase()) < 0) return false;
        }
        var gwt = parseFloat(r.gross_wt != null ? r.gross_wt : (r.net_wt != null ? r.net_wt : 0)) || 0;
        if (!advFilter.zero_gross && gwt <= 0) return false;
        if (advFilter.gross_wt !== '' && advFilter.gross_wt != null) {
            var want = parseFloat(advFilter.gross_wt);
            if (!isNaN(want) && Math.abs(gwt - want) > 0.0005) return false;
        }
        return true;
    }

    function rowMatchesFilter(r) {
        if (!rowMatchesAdvFilter(r)) return false;
        if (!filterText) return true;
        var t = filterText.toLowerCase();
        try {
            return JSON.stringify(r).toLowerCase().indexOf(t) >= 0;
        } catch (e) {
            return true;
        }
    }

    function rowMatchesDestFilter(r) {
        if (!filterTextDest) return true;
        var t = filterTextDest.toLowerCase();
        try {
            return JSON.stringify(r).toLowerCase().indexOf(t) >= 0;
        } catch (e) {
            return true;
        }
    }

    var stDataColDefs = [
        ['net_amt', 'Net Amt'], ['date', 'Date'], ['view', 'View'], ['barcode', 'Barcode'], ['product_name', 'Product Name'],
        ['rfid', 'RFID'], ['location', 'Location'], ['against_invoice', 'Against Invoice No'], ['type_of_voucher', 'Type Of Voucher'],
        ['voucher_type', 'Voucher Type'], ['invoice', 'Invoice'], ['branch', 'Branch'], ['qty', 'Qty.'], ['gross_wt', 'Gross Wt'],
        ['purity', 'Pu'], ['pure_wt', 'Pure Wt.'], ['requested_qty', 'Requested Qty'], ['requested_wt', 'Requested Wt'],
        ['stone_wt', 'Stone Wt'], ['diamond_wt', 'Diamond Wt'], ['less_wt', 'Less Wt.'], ['purity_wt', 'Purity Wt'],
        ['wastage_per', 'Wastage Per.'], ['wastage_wt', 'Wastage Wt.'], ['net_wt', 'Net Wt'], ['alloy_wt', 'Alloy Wt.'],
        ['final_wt', 'Final Wt'], ['standard_wt', 'Standard Wt'], ['actual_wt', 'Actual Wt'], ['national_wt', 'National Wt'],
        ['name', 'Name'], ['making_rate', 'Making Rate'], ['amt', 'Amt'], ['making_amt', 'Making Amt'], ['amount', 'Amount'],
        ['hui_code', 'HUI Code'], ['packet_wt', 'Packet Wt'], ['packet_length', 'Packet L.'], ['rate', 'Rate'],
        ['hallmark1', 'Hallmark 1'], ['hallmark2', 'Hallmark 2'], ['net_amt_with_tax', 'Net Amt W/Tax'], ['tax_amt', 'Tax Amt'],
        ['discount_per', 'Discount %'], ['discount_amt', 'Discount Amt'], ['metal_value', 'Metal Val.'], ['purchase', 'Purchase']
    ];

    function stDefaultDataColKeys() {
        return stDataColDefs.map(function (x) {
            return x[0];
        });
    }

    function stLabelForDataCol(key) {
        for (var i = 0; i < stDataColDefs.length; i++) {
            if (stDataColDefs[i][0] === key) {
                return stDataColDefs[i][1];
            }
        }
        return key;
    }

    function stGetDataColOrder(which) {
        var def = stDefaultDataColKeys();
        var lsKey = which === 'source' ? 'auragold_st_transfer_col_order_src' : 'auragold_st_transfer_col_order_dest';
        try {
            var raw = localStorage.getItem(lsKey);
            if (raw) {
                var arr = JSON.parse(raw);
                if (Array.isArray(arr)) {
                    var seen = {};
                    var out = [];
                    arr.forEach(function (k) {
                        if (def.indexOf(k) >= 0 && !seen[k]) {
                            seen[k] = true;
                            out.push(k);
                        }
                    });
                    def.forEach(function (k) {
                        if (!seen[k]) {
                            seen[k] = true;
                            out.push(k);
                        }
                    });
                    return out;
                }
            }
        } catch (e) {}
        return def.slice();
    }

    function stSaveDataColOrder(which, orderArr) {
        try {
            var lsKey = which === 'source' ? 'auragold_st_transfer_col_order_src' : 'auragold_st_transfer_col_order_dest';
            localStorage.setItem(lsKey, JSON.stringify(orderArr));
        } catch (e) {}
    }

    function stRefreshColOrderList(which) {
        var id = which === 'source' ? 'stSourceColOrderList' : 'stDestColOrderList';
        var el = document.getElementById(id);
        if (!el) {
            return;
        }
        var order = stGetDataColOrder(which);
        var html = '';
        order.forEach(function (key) {
            html += '<div class="st-col-order-item" draggable="true" data-st-order-key="' + escapeHtml(key) + '" title="' + escapeHtml(stLabelForDataCol(key)) + '">' +
                '<i class="feather icon-menu" aria-hidden="true"></i><span>' + escapeHtml(stLabelForDataCol(key)) + '</span></div>';
        });
        el.innerHTML = html;
    }

    function stApplyColumnOrder(which) {
        var tableId = which === 'source' ? 'stTableSource' : 'stTableDest';
        var table = document.getElementById(tableId);
        if (!table) {
            return;
        }
        var theadRow = table.querySelector('thead tr');
        if (!theadRow) {
            return;
        }
        var prefix = which === 'source' ? ['st_cb', 'st_drag', 'st_img'] : ['st_cb'];
        var suffix = which === 'source' ? [] : ['st_remove'];
        var order = stGetDataColOrder(which);
        var byCol = {};
        theadRow.querySelectorAll('th[data-col]').forEach(function (th) {
            byCol[th.getAttribute('data-col')] = th;
        });
        prefix.concat(order).concat(suffix).forEach(function (k) {
            var th = byCol[k];
            if (th) {
                theadRow.appendChild(th);
            }
        });
        stApplyColumnVisibility(which);
    }

    function stBindColOrderDnD(which) {
        var id = which === 'source' ? 'stSourceColOrderList' : 'stDestColOrderList';
        var listEl = document.getElementById(id);
        if (!listEl || listEl.getAttribute('data-st-dnd-bound') === '1') {
            return;
        }
        listEl.setAttribute('data-st-dnd-bound', '1');
        var dragKey = null;
        listEl.addEventListener('dragstart', function (e) {
            var item = e.target.closest('.st-col-order-item');
            if (!item || !listEl.contains(item)) {
                return;
            }
            dragKey = item.getAttribute('data-st-order-key');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', dragKey || '');
            item.classList.add('st-col-order-dragging');
        });
        listEl.addEventListener('dragend', function () {
            listEl.querySelectorAll('.st-col-order-dragging').forEach(function (el) {
                el.classList.remove('st-col-order-dragging');
            });
            dragKey = null;
        });
        listEl.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        });
        listEl.addEventListener('drop', function (e) {
            e.preventDefault();
            var target = e.target.closest('.st-col-order-item');
            var fromKey = e.dataTransfer.getData('text/plain') || dragKey;
            if (!target || !fromKey || !listEl.contains(target)) {
                return;
            }
            var toKey = target.getAttribute('data-st-order-key');
            if (!toKey || fromKey === toKey) {
                return;
            }
            var order = stGetDataColOrder(which).slice();
            var fi = order.indexOf(fromKey);
            var ti = order.indexOf(toKey);
            if (fi < 0 || ti < 0) {
                return;
            }
            order.splice(fi, 1);
            if (fi < ti) {
                ti--;
            }
            order.splice(ti, 0, fromKey);
            stSaveDataColOrder(which, order);
            stRefreshColOrderList(which);
            stApplyColumnOrder(which);
            renderSource();
            renderDest();
        });
    }

    var stColStateSource = {};
    var stColStateDest = {};

    function stMergeColState(which, keys) {
        var out = {};
        keys.forEach(function (k) { out[k] = true; });
        try {
            var raw = localStorage.getItem(which === 'source' ? 'auragold_st_transfer_cols_src' : 'auragold_st_transfer_cols_dest');
            if (raw) {
                var o = JSON.parse(raw);
                if (o && typeof o === 'object') {
                    keys.forEach(function (k) {
                        if (Object.prototype.hasOwnProperty.call(o, k)) {
                            out[k] = !!o[k];
                        }
                    });
                }
            }
        } catch (e) {}
        return out;
    }

    function stSaveColState(which) {
        try {
            var obj = which === 'source' ? stColStateSource : stColStateDest;
            localStorage.setItem(
                which === 'source' ? 'auragold_st_transfer_cols_src' : 'auragold_st_transfer_cols_dest',
                JSON.stringify(obj)
            );
        } catch (e) {}
    }

    function stApplyColumnVisibility(which) {
        var tableId = which === 'source' ? 'stTableSource' : 'stTableDest';
        var table = document.getElementById(tableId);
        if (!table) return;
        var state = which === 'source' ? stColStateSource : stColStateDest;
        Object.keys(state).forEach(function (key) {
            var visible = state[key] !== false;
            var dis = visible ? '' : 'none';
            table.querySelectorAll('thead th[data-col="' + key + '"], tbody td[data-col="' + key + '"]').forEach(function (el) {
                el.style.display = dis;
            });
        });
    }

    function stInitColumnSettings() {
        var srcKeys = ['st_cb', 'st_drag', 'st_img'].concat(stDataColDefs.map(function (x) { return x[0]; }));
        var destKeys = ['st_cb'].concat(stDataColDefs.map(function (x) { return x[0]; })).concat(['st_remove']);

        stColStateSource = stMergeColState('source', srcKeys);
        stColStateDest = stMergeColState('dest', destKeys);

        function buildList(listEl, which, defs) {
            listEl.innerHTML = '';
            defs.forEach(function (d) {
                var key = d[0];
                var label = d[1];
                var id = 'st_col_' + which + '_' + key;
                var checked = (which === 'source' ? stColStateSource : stColStateDest)[key] !== false;
                var div = document.createElement('div');
                div.className = 'columns-dropdown-item';
                div.innerHTML = '<input type="checkbox" id="' + id + '" data-st-col="' + key + '" data-st-which="' + which + '"' + (checked ? ' checked' : '') + '>' +
                    '<label for="' + id + '">' + label + '</label>';
                listEl.appendChild(div);
            });
        }

        var srcDefs = [['st_cb', 'Select all'], ['st_drag', 'Drag handle'], ['st_img', 'Image']].concat(stDataColDefs);
        buildList(document.getElementById('stSourceColList'), 'source', srcDefs);

        var destDefs = [['st_cb', 'Select all']].concat(stDataColDefs).concat([['st_remove', 'Remove']]);
        buildList(document.getElementById('stDestColList'), 'dest', destDefs);

        function bindSearch(inputId, listId) {
            document.getElementById(inputId).addEventListener('input', function () {
                var term = (this.value || '').toLowerCase();
                document.querySelectorAll('#' + listId + ' .columns-dropdown-item').forEach(function (row) {
                    var lab = row.querySelector('label');
                    var t = lab ? lab.textContent.toLowerCase() : '';
                    row.style.display = t.indexOf(term) >= 0 ? '' : 'none';
                });
            });
        }
        bindSearch('stSourceColSearch', 'stSourceColList');
        bindSearch('stDestColSearch', 'stDestColList');

        document.getElementById('stSourceColList').addEventListener('change', function (e) {
            var inp = e.target.closest('input[data-st-col]');
            if (!inp) return;
            var key = inp.getAttribute('data-st-col');
            stColStateSource[key] = inp.checked;
            stSaveColState('source');
            stApplyColumnVisibility('source');
        });
        document.getElementById('stDestColList').addEventListener('change', function (e) {
            var inp = e.target.closest('input[data-st-col]');
            if (!inp) return;
            var key = inp.getAttribute('data-st-col');
            stColStateDest[key] = inp.checked;
            stSaveColState('dest');
            stApplyColumnVisibility('dest');
        });

        function toggleDropdown(btnId, dropId, otherDropId) {
            document.getElementById(btnId).addEventListener('click', function (e) {
                e.stopPropagation();
                var d = document.getElementById(dropId);
                var od = document.getElementById(otherDropId);
                var open = !d.classList.contains('show');
                d.classList.toggle('show', open);
                if (od) od.classList.remove('show');
                this.setAttribute('aria-expanded', open ? 'true' : 'false');
                d.setAttribute('aria-hidden', open ? 'false' : 'true');
            });
        }
        toggleDropdown('stSourceColBtn', 'stSourceColDropdown', 'stDestColDropdown');
        toggleDropdown('stDestColBtn', 'stDestColDropdown', 'stSourceColDropdown');

        document.addEventListener('click', function (e) {
            if (e.target.closest('.st-col-settings-wrap')) return;
            document.getElementById('stSourceColDropdown').classList.remove('show');
            document.getElementById('stDestColDropdown').classList.remove('show');
            document.getElementById('stSourceColBtn').setAttribute('aria-expanded', 'false');
            document.getElementById('stDestColBtn').setAttribute('aria-expanded', 'false');
        });

        stApplyColumnVisibility('source');
        stApplyColumnVisibility('dest');

        stRefreshColOrderList('source');
        stRefreshColOrderList('dest');
        stBindColOrderDnD('source');
        stBindColOrderDnD('dest');
        stApplyColumnOrder('source');
        stApplyColumnOrder('dest');
    }

    function renderSource() {
        var tb = document.getElementById('stTableSourceBody');
        var total = 0;
        var html = '';
        sourceRows.forEach(function (r) {
            if (!rowMatchesFilter(r)) return;
            total += parseFloat(r.net_amt != null ? r.net_amt : r.amount) || 0;
            html += '<tr draggable="true" class="st-source-row" data-id="' + r.id + '">';
            html += '<td data-col="st_cb"><input type="checkbox" class="st-source-cb" value="' + r.id + '" draggable="false"></td>';
            html += '<td data-col="st_drag" class="st-drag-handle text-muted" title="Drag row to destination"><i class="feather icon-menu" style="font-size:14px;" aria-hidden="true"></i></td>';
            html += '<td data-col="st_img">' + thumbHtml(r.image_urls) + '</td>';
            html += rowDataCellsHtml(r, 'source');
            html += '</tr>';
        });
        if (!html) {
            html = '<tr><td colspan="50" class="st-empty">' +
                (sourceRows.length ? 'No rows match filter.' : 'No stock for this branch with available inward quantity (opening / purchase). Use the source branch that matches Stock History → Inward for that barcode.') + '</td></tr>';
        }
        tb.innerHTML = html;
        document.getElementById('stSourceTotal').textContent = fmtMoney(total);
        stApplyColumnVisibility('source');
        stUpdateFilterBadge();
    }

    function renderDest() {
        var tb = document.getElementById('stTableDestBody');
        var total = 0;
        var html = '';
        destRows.forEach(function (r, idx) {
            if (!rowMatchesDestFilter(r)) return;
            total += parseFloat(r.net_amt != null ? r.net_amt : r.amount) || 0;
            html += '<tr data-id="' + r.id + '">';
            html += '<td data-col="st_cb"><input type="checkbox" class="st-dest-cb" value="' + r.id + '"></td>';
            html += rowDataCellsHtml(r, 'dest');
            html += '<td data-col="st_remove" class="text-center"><button type="button" class="btn btn-xs btn-link text-danger p-1 st-remove" data-idx="' + idx + '" title="Remove" aria-label="Remove"><i class="feather icon-trash-2" style="font-size:16px;"></i></button></td>';
            html += '</tr>';
        });
        if (!html) {
            html = '<tr><td colspan="49" class="st-empty">' +
                (destRows.length ? 'No rows match filter.' : 'No Rows To Show') + '</td></tr>';
        }
        tb.innerHTML = html;
        document.getElementById('stDestTotal').textContent = fmtMoney(total);
        stApplyColumnVisibility('dest');

        var saveBtn = document.getElementById('stBtnSave');
        var toBr = document.getElementById('stToBranch').value;
        saveBtn.disabled = !(destRows.length && toBr);
    }

    function escapeHtml(s) {
        if (s == null) return '';
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function destHas(id) {
        return destRows.some(function (r) { return String(r.id) === String(id); });
    }

    function sourceById(id) {
        for (var i = 0; i < sourceRows.length; i++) {
            if (String(sourceRows[i].id) === String(id)) return sourceRows[i];
        }
        return null;
    }

    function removeSourceRowById(stockId) {
        var sid = String(stockId);
        for (var i = 0; i < sourceRows.length; i++) {
            if (String(sourceRows[i].id) === sid) {
                sourceRows.splice(i, 1);
                return true;
            }
        }
        return false;
    }

    function loadSourceList(keepDest) {
        var bid = document.getElementById('stFromBranch').value;
        if (!bid) {
            stShowMsg('Source branch', 'Select a source branch from the list, then click Apply.', 'warning');
            return;
        }
        var url = new URL('ajax/stock-transfer-list.php', window.location.href);
        url.searchParams.set('branch_id', bid);
        fetch(url.toString(), { credentials: 'same-origin' })
            .then(function (r) {
                return r.text().then(function (text) {
                    var data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        throw new Error(
                            (r.status !== 200 ? 'HTTP ' + r.status + '. ' : '') +
                            (text ? text.slice(0, 400) : 'Empty response from server.')
                        );
                    }
                    if (!r.ok) {
                        throw new Error(data && data.message ? data.message : ('HTTP ' + r.status));
                    }
                    return data;
                });
            })
            .then(function (data) {
                if (!data.success) {
                    stShowMsg('Could not load stock', data.message || 'Failed to load stock.', 'error');
                    return;
                }
                sourceRows = data.rows || [];
                if (!keepDest) {
                    destRows = [];
                }
                filterTextDest = '';
                var destF = document.getElementById('stDestFilter');
                if (destF) destF.value = '';
                renderSource();
                renderDest();
            })
            .catch(function (err) {
                stShowMsg('Could not load stock', err && err.message ? err.message : 'Network error loading stock.', 'error');
            });
    }

    function addToDestByStockId(stockId) {
        if (destHas(stockId)) return;
        var row = sourceById(stockId);
        if (!row) return;
        removeSourceRowById(stockId);
        destRows.push(row);
        renderSource();
        renderDest();
    }

    function initStockDragDrop() {
        var srcBody = document.getElementById('stTableSourceBody');
        var destZone = document.getElementById('stDestDropZone');
        if (!srcBody || !destZone) return;

        srcBody.addEventListener('dragstart', function (e) {
            var tr = e.target.closest('tr.st-source-row[data-id]');
            if (!tr) return;
            var id = tr.getAttribute('data-id');
            if (!id) return;
            e.dataTransfer.setData('application/x-auragold-stock-id', id);
            e.dataTransfer.setData('text/plain', id);
            e.dataTransfer.effectAllowed = 'move';
            tr.classList.add('st-dragging');
        });

        srcBody.addEventListener('dragend', function (e) {
            var tr = e.target.closest('tr.st-source-row');
            if (tr) tr.classList.remove('st-dragging');
        });

        destZone.addEventListener('dragenter', function (e) {
            e.preventDefault();
            destZone.classList.add('st-drop-active');
        });

        destZone.addEventListener('dragleave', function (e) {
            var rel = e.relatedTarget;
            if (!rel || !destZone.contains(rel)) {
                destZone.classList.remove('st-drop-active');
            }
        });

        destZone.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        });

        destZone.addEventListener('drop', function (e) {
            e.preventDefault();
            destZone.classList.remove('st-drop-active');
            var id = e.dataTransfer.getData('application/x-auragold-stock-id') || e.dataTransfer.getData('text/plain');
            if (!id) return;
            addToDestByStockId(String(id).trim());
        });

        document.addEventListener('dragend', function () {
            destZone.classList.remove('st-drop-active');
        });
    }

    document.getElementById('stApplySource').addEventListener('click', loadSourceList);
    document.getElementById('stBtnRefreshAll').addEventListener('click', loadSourceList);

    document.getElementById('stDateRefresh').addEventListener('click', function () {
        document.getElementById('stTransferDate').value = todayYmd;
    });

    document.getElementById('stSourceFilter').addEventListener('input', function () {
        filterText = (this.value || '').trim();
        renderSource();
    });

    document.getElementById('stDestFilter').addEventListener('input', function () {
        filterTextDest = (this.value || '').trim();
        renderDest();
    });

    document.getElementById('stSourceSelectAll').addEventListener('change', function () {
        var on = this.checked;
        document.querySelectorAll('.st-source-cb').forEach(function (cb) { cb.checked = on; });
    });

    document.getElementById('stDestSelectAll').addEventListener('change', function () {
        var on = this.checked;
        document.querySelectorAll('.st-dest-cb').forEach(function (cb) { cb.checked = on; });
    });

    document.getElementById('stAddSelected').addEventListener('click', function () {
        document.querySelectorAll('.st-source-cb:checked').forEach(function (cb) {
            addToDestByStockId(cb.value);
        });
        document.querySelectorAll('.st-source-cb').forEach(function (cb) { cb.checked = false; });
        document.getElementById('stSourceSelectAll').checked = false;
    });

    document.getElementById('stTableDestBody').addEventListener('click', function (e) {
        var btn = e.target.closest('.st-remove');
        if (!btn) return;
        var idx = parseInt(btn.getAttribute('data-idx'), 10);
        if (!isNaN(idx)) {
            var back = destRows[idx];
            destRows.splice(idx, 1);
            if (back) {
                sourceRows.push(back);
            }
            renderSource();
            renderDest();
        }
    });

    document.getElementById('stClearDest').addEventListener('click', function () {
        destRows.forEach(function (r) {
            sourceRows.push(r);
        });
        destRows = [];
        filterTextDest = '';
        var destF = document.getElementById('stDestFilter');
        if (destF) destF.value = '';
        renderSource();
        renderDest();
    });

    document.getElementById('stToBranch').addEventListener('change', renderDest);

    document.getElementById('stBarcodeIn').addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        var bc = (this.value || '').trim();
        if (!bc) return;
        var bid = document.getElementById('stFromBranch').value;
        if (!bid) {
            stShowMsg('Source branch', 'Select a source branch before scanning a barcode.', 'warning');
            return;
        }
        var burl = new URL('ajax/stock-transfer-barcode.php', window.location.href);
        burl.searchParams.set('branch_id', bid);
        burl.searchParams.set('barcode', bc);
        fetch(burl.toString(), { credentials: 'same-origin' })
            .then(function (r) {
                return r.text().then(function (text) {
                    var data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        throw new Error(text ? text.slice(0, 400) : 'Bad response');
                    }
                    return data;
                });
            })
            .then(function (data) {
                if (!data.success) {
                    stShowMsg('Barcode', data.message || 'Barcode not found.', 'error');
                    return;
                }
                var row = data.row;
                var found = false;
                for (var i = 0; i < sourceRows.length; i++) {
                    if (String(sourceRows[i].id) === String(row.id)) { found = true; break; }
                }
                if (!found) {
                    sourceRows.unshift(row);
                    renderSource();
                }
                addToDestByStockId(row.id);
                document.getElementById('stBarcodeIn').value = '';
            })
            .catch(function (err) {
                stShowMsg('Barcode', err && err.message ? err.message : 'Network error.', 'error');
            });
    });

    document.getElementById('stBtnSave').addEventListener('click', function () {
        var fromB = document.getElementById('stFromBranch').value;
        var toB = document.getElementById('stToBranch').value;
        var dt = document.getElementById('stTransferDate').value;
        if (!fromB || !toB) {
            stShowMsg('Branches', 'Select both source and destination branch.', 'warning');
            return;
        }
        if (fromB === toB) {
            stShowMsg('Branches', 'Source and destination must be different branches.', 'warning');
            return;
        }
        if (!destRows.length) {
            stShowMsg('Transfer list', 'Add one or more items to the transfer list before saving.', 'warning');
            return;
        }
        var taggedRows = destRows.filter(function (r) { return !r.is_loose; });
        var loosePending = destRows.filter(function (r) { return r.is_loose && !r.loose_transferred; });
        if (!taggedRows.length && !loosePending.length) {
            stShowMsg('Transfer list', 'No pending items to transfer. Loose items already saved stay listed for reference — clear them or add new items.', 'info');
            return;
        }
        if (!confirm('Transfer ' + (taggedRows.length + loosePending.length) + ' item(s) under one invoice?')) return;

        var saveBtn = document.getElementById('stBtnSave');
        saveBtn.disabled = true;

        var ids = taggedRows.map(function (r) { return r.id; });
        var loosePayload = loosePending.map(function (L) {
            return {
                product_id: parseInt(L.product_id, 10) || 0,
                metal_id: parseInt(L.metal_id, 10) || 0,
                characteristic_id: parseInt(L.characteristic_id, 10) || 0,
                transfer_wt: parseFloat(L.move_wt || L.gross_wt) || 0,
                transfer_qty: parseFloat(L.qty || L.requested_qty) || 0
            };
        });

        fetch('ajax/stock-transfer-save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                from_branch_id: parseInt(fromB, 10),
                to_branch_id: parseInt(toB, 10),
                transfer_date: dt || todayYmd,
                stock_ids: ids,
                loose_items: loosePayload
            })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                saveBtn.disabled = false;
                if (!data || !data.success) {
                    stSwalOrAlert({
                        title: 'Transfer failed',
                        text: (data && data.message) ? data.message : 'Save failed.',
                        type: 'error',
                        confirmButtonText: 'OK'
                    });
                    return;
                }
                var msg = data.message || 'Saved.';
                if (data.invoice_no) {
                    msg = 'Invoice ' + data.invoice_no + '\n\n' + msg;
                }
                stSwalOrAlert({
                    title: 'Transfer saved',
                    text: msg,
                    type: 'success',
                    confirmButtonText: 'OK'
                });
                destRows = [];
                renderDest();
                loadSourceList();
            })
            .catch(function () {
                saveBtn.disabled = false;
                stSwalOrAlert({
                    title: 'Network error',
                    text: 'Could not reach the server. Try again.',
                    type: 'error',
                    confirmButtonText: 'OK'
                });
            });
    });

    /** Self-contained open/close — this page does not load Bootstrap modal JS. */
    function stLooseCleanupBackdrop() {
        document.querySelectorAll('.modal-backdrop, .st-loose-backdrop').forEach(function (el) {
            if (el.parentNode) el.parentNode.removeChild(el);
        });
        document.body.classList.remove('modal-open');
        document.body.style.paddingRight = '';
        document.body.style.overflow = '';
        var modal = document.getElementById('stLooseModal');
        if (modal) {
            modal.classList.remove('show');
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
            modal.removeAttribute('aria-modal');
        }
    }

    function stLooseShowModal() {
        stLooseCleanupBackdrop();
        var modal = document.getElementById('stLooseModal');
        if (!modal) return;
        var backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show st-loose-backdrop';
        backdrop.id = 'stLooseBackdrop';
        document.body.appendChild(backdrop);
        document.body.classList.add('modal-open');
        modal.style.display = 'block';
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        modal.setAttribute('aria-modal', 'true');
        // Click outside dialog closes
        backdrop.addEventListener('click', function () {
            stLooseHideModal();
        });
    }

    /** Hide loose modal fully, then run callback (avoids black screen with SweetAlert). */
    function stLooseHideModal(thenFn) {
        stLooseCleanupBackdrop();
        if (typeof thenFn === 'function') {
            setTimeout(thenFn, 50);
        }
    }

    function stLooseShowInlineErr(msg) {
        var el = document.getElementById('stLooseFormError');
        if (!el) return;
        if (msg) {
            el.textContent = msg;
            el.style.display = 'block';
        } else {
            el.textContent = '';
            el.style.display = 'none';
        }
    }

    function stLooseResetForm() {
        var metalEl = document.getElementById('stLooseMetal');
        var prodEl = document.getElementById('stLooseProduct');
        var wtEl = document.getElementById('stLooseTransferWt');
        var qtyEl = document.getElementById('stLooseTransferQty');
        var bcEl = document.getElementById('stLooseBarcode');
        if (metalEl) metalEl.value = '';
        if (prodEl) {
            prodEl.innerHTML = '<option value="">— Select metal first —</option>';
            prodEl.disabled = true;
        }
        if (wtEl) wtEl.value = '';
        if (qtyEl) qtyEl.value = '';
        if (bcEl) bcEl.value = '';
        stLooseBalanceWt = 0;
        stLooseBalanceQty = 0;
        stLooseProductsCache = [];
        stLooseScannedBarcode = '';
        var balWt = document.getElementById('stLooseBalanceWt');
        var balQty = document.getElementById('stLooseBalanceQty');
        if (balWt) balWt.textContent = '0.000';
        if (balQty) balQty.textContent = '0';
        stLooseShowInlineErr('');
    }

    function stLooseSetBalance(wt, qty) {
        stLooseBalanceWt = parseFloat(wt) || 0;
        stLooseBalanceQty = parseFloat(qty) || 0;
        var balWt = document.getElementById('stLooseBalanceWt');
        var balQty = document.getElementById('stLooseBalanceQty');
        if (balWt) balWt.textContent = stLooseBalanceWt.toFixed(3);
        if (balQty) balQty.textContent = stLooseBalanceQty.toFixed(2);
    }

    function stLooseLoadBalance() {
        var fromB = document.getElementById('stFromBranch').value;
        var metalId = parseInt(document.getElementById('stLooseMetal').value, 10) || 0;
        var prodEl = document.getElementById('stLooseProduct');
        var productId = parseInt(prodEl.value, 10) || 0;
        var charId = 0;
        var opt = prodEl.options[prodEl.selectedIndex];
        if (opt && opt.getAttribute('data-char-id')) {
            charId = parseInt(opt.getAttribute('data-char-id'), 10) || 0;
        }
        if (!fromB || metalId <= 0 || productId <= 0) {
            stLooseSetBalance(0, 0);
            return;
        }
        stLooseSetBalance(0, 0);
        fetch('ajax/stock-transfer-loose-save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'balance',
                from_branch_id: parseInt(fromB, 10),
                metal_id: metalId,
                product_id: productId,
                characteristic_id: charId
            })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.success) {
                    stLooseSetBalance(0, 0);
                    return;
                }
                stLooseSetBalance(data.display_gross_weight, data.display_qty);
            })
            .catch(function () {
                stLooseSetBalance(0, 0);
            });
    }

    function stLooseSelectProduct(productId, charId) {
        var prodEl = document.getElementById('stLooseProduct');
        if (!prodEl || productId <= 0) return false;
        var pid = String(productId);
        var found = false;
        for (var i = 0; i < prodEl.options.length; i++) {
            if (String(prodEl.options[i].value) === pid) {
                prodEl.selectedIndex = i;
                found = true;
                break;
            }
        }
        if (!found) {
            var opt = document.createElement('option');
            opt.value = pid;
            opt.setAttribute('data-char-id', String(charId || ''));
            opt.textContent = 'Product #' + pid;
            prodEl.appendChild(opt);
            prodEl.value = pid;
            found = true;
        }
        if (charId > 0) {
            var sel = prodEl.options[prodEl.selectedIndex];
            if (sel) sel.setAttribute('data-char-id', String(charId));
        }
        return found;
    }

    function stLooseLoadProducts(metalId, opts) {
        opts = opts || {};
        var prodEl = document.getElementById('stLooseProduct');
        prodEl.innerHTML = '<option value="">Loading…</option>';
        prodEl.disabled = true;
        stLooseSetBalance(0, 0);
        if (!metalId) {
            prodEl.innerHTML = '<option value="">— Select metal first —</option>';
            return Promise.resolve([]);
        }
        return fetch('ajax/get-products-by-metal.php?metal_id=' + encodeURIComponent(metalId))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var products = (data && data.success && Array.isArray(data.products)) ? data.products : [];
                if (opts.useAllProducts) {
                    stLooseProductsCache = products;
                } else {
                    var loose = products.filter(function (p) {
                        return !String(p.barcode || '').trim();
                    });
                    stLooseProductsCache = loose.length ? loose : products;
                }
                prodEl.innerHTML = '<option value="">— Select product —</option>';
                stLooseProductsCache.forEach(function (p) {
                    var opt = document.createElement('option');
                    opt.value = String(p.id || '');
                    opt.setAttribute('data-char-id', String(p.characteristic_id || ''));
                    var label = (p.name || 'Product') + (p.sku_code ? ' (' + p.sku_code + ')' : '');
                    opt.textContent = label;
                    prodEl.appendChild(opt);
                });
                prodEl.disabled = false;
                if (!stLooseProductsCache.length) {
                    prodEl.innerHTML = '<option value="">No products for this metal</option>';
                    prodEl.disabled = true;
                }
                if (opts.selectProductId) {
                    stLooseSelectProduct(parseInt(opts.selectProductId, 10) || 0, parseInt(opts.selectCharId, 10) || 0);
                }
                return stLooseProductsCache;
            })
            .catch(function () {
                prodEl.innerHTML = '<option value="">Failed to load products</option>';
                prodEl.disabled = true;
                return [];
            });
    }

    function stLooseLookupBarcode() {
        var bcEl = document.getElementById('stLooseBarcode');
        var bc = bcEl ? String(bcEl.value || '').trim() : '';
        stLooseShowInlineErr('');
        if (!bc) return;
        var fromB = document.getElementById('stFromBranch').value;
        if (!fromB) {
            stLooseShowInlineErr('Select a source branch before scanning barcode.');
            return;
        }
        if (stLooseBarcodeLookupBusy) return;
        stLooseBarcodeLookupBusy = true;
        var burl = new URL('ajax/stock-transfer-barcode.php', window.location.href);
        burl.searchParams.set('branch_id', fromB);
        burl.searchParams.set('barcode', bc);
        fetch(burl.toString(), { credentials: 'same-origin' })
            .then(function (r) {
                return r.text().then(function (text) {
                    var data;
                    try { data = JSON.parse(text); } catch (e) { throw new Error(text ? text.slice(0, 400) : 'Bad response'); }
                    return data;
                });
            })
            .then(function (data) {
                stLooseBarcodeLookupBusy = false;
                if (!data || !data.success || !data.row) {
                    stLooseShowInlineErr((data && data.message) ? data.message : 'Barcode not found at source branch.');
                    return;
                }
                var row = data.row;
                var metalId = parseInt(row.metal_id, 10) || 0;
                var productId = parseInt(row.product_id, 10) || 0;
                var charId = parseInt(row.product_characteristic_id || row.characteristic_id, 10) || 0;
                if (metalId <= 0 || productId <= 0) {
                    stLooseShowInlineErr('Barcode found but metal/product could not be resolved.');
                    return;
                }
                stLooseScannedBarcode = bc;
                var metalEl = document.getElementById('stLooseMetal');
                if (metalEl) metalEl.value = String(metalId);
                stLooseLoadProducts(metalId, {
                    useAllProducts: true,
                    selectProductId: productId,
                    selectCharId: charId
                }).then(function () {
                    stLooseLoadBalance();
                });
            })
            .catch(function (err) {
                stLooseBarcodeLookupBusy = false;
                stLooseShowInlineErr(err && err.message ? err.message : 'Barcode lookup failed.');
            });
    }

    document.getElementById('stBtnLoose').addEventListener('click', function () {
        var fromB = document.getElementById('stFromBranch').value;
        var toB = document.getElementById('stToBranch').value;
        if (!fromB) {
            stShowMsg('Source branch', 'Select a source branch and click Apply first.', 'warning');
            return;
        }
        if (!toB) {
            stShowMsg('Destination branch', 'Select a destination branch before transferring loose items.', 'warning');
            return;
        }
        if (fromB === toB) {
            stShowMsg('Branches', 'Source and destination must be different branches.', 'warning');
            return;
        }
        stLooseResetForm();
        stLooseShowModal();
        setTimeout(function () {
            var bcFocus = document.getElementById('stLooseBarcode');
            if (bcFocus) bcFocus.focus();
        }, 200);
    });

    var stLooseCancelBtn = document.getElementById('stLooseCancelBtn');
    var stLooseCloseBtn = document.getElementById('stLooseCloseBtn');
    if (stLooseCancelBtn) {
        stLooseCancelBtn.addEventListener('click', function (e) {
            e.preventDefault();
            stLooseHideModal();
        });
    }
    if (stLooseCloseBtn) {
        stLooseCloseBtn.addEventListener('click', function (e) {
            e.preventDefault();
            stLooseHideModal();
        });
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            var modal = document.getElementById('stLooseModal');
            if (modal && modal.classList.contains('show')) {
                stLooseHideModal();
            }
        }
    });

    document.getElementById('stLooseMetal').addEventListener('change', function () {
        var metalId = parseInt(this.value, 10) || 0;
        stLooseScannedBarcode = '';
        stLooseLoadProducts(metalId);
    });

    document.getElementById('stLooseProduct').addEventListener('change', function () {
        stLooseLoadBalance();
    });

    var stLooseBarcodeEl = document.getElementById('stLooseBarcode');
    if (stLooseBarcodeEl) {
        stLooseBarcodeEl.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            stLooseLookupBarcode();
        });
        stLooseBarcodeEl.addEventListener('blur', function () {
            var bc = String(this.value || '').trim();
            if (bc && bc !== stLooseScannedBarcode) {
                stLooseLookupBarcode();
            }
        });
    }

    document.getElementById('stLooseSaveBtn').addEventListener('click', function () {
        var fromB = document.getElementById('stFromBranch').value;
        var toB = document.getElementById('stToBranch').value;
        var dt = document.getElementById('stTransferDate').value;
        var metalId = parseInt(document.getElementById('stLooseMetal').value, 10) || 0;
        var metalEl = document.getElementById('stLooseMetal');
        var metalName = '';
        if (metalEl && metalEl.selectedIndex >= 0) {
            metalName = (metalEl.options[metalEl.selectedIndex].textContent || '').trim();
        }
        var prodEl = document.getElementById('stLooseProduct');
        var productId = parseInt(prodEl.value, 10) || 0;
        var charId = 0;
        var productName = '';
        var opt = prodEl.options[prodEl.selectedIndex];
        if (opt) {
            if (opt.getAttribute('data-char-id')) {
                charId = parseInt(opt.getAttribute('data-char-id'), 10) || 0;
            }
            productName = (opt.textContent || '').trim();
        }
        var transferWt = parseFloat(document.getElementById('stLooseTransferWt').value) || 0;
        var transferQty = parseFloat(document.getElementById('stLooseTransferQty').value) || 0;
        var barcodeVal = String((document.getElementById('stLooseBarcode') || {}).value || stLooseScannedBarcode || '').trim();

        stLooseShowInlineErr('');

        if (!fromB || !toB) {
            stLooseShowInlineErr('Select both source and destination branch.');
            return;
        }
        if (fromB === toB) {
            stLooseShowInlineErr('Source and destination must be different branches.');
            return;
        }
        if (metalId <= 0) {
            stLooseShowInlineErr('Select a metal.');
            return;
        }
        if (productId <= 0) {
            stLooseShowInlineErr('Select a product.');
            return;
        }
        if (transferWt <= 0) {
            stLooseShowInlineErr('Enter a transfer weight greater than zero.');
            return;
        }
        if (transferQty <= 0) {
            stLooseShowInlineErr('Enter a transfer quantity greater than zero.');
            return;
        }
        if (stLooseBalanceWt > 0 && transferWt > stLooseBalanceWt + 0.0001) {
            stLooseShowInlineErr('Transfer weight cannot exceed balance stock (' + stLooseBalanceWt.toFixed(3) + ').');
            return;
        }
        if (stLooseBalanceQty > 0 && transferQty > stLooseBalanceQty + 0.0001) {
            stLooseShowInlineErr('Transfer quantity cannot exceed balance qty (' + stLooseBalanceQty.toFixed(2) + ').');
            return;
        }

        var looseId = 'loose-' + Date.now() + '-' + productId;
        var toBranchName = '';
        var toEl = document.getElementById('stToBranch');
        if (toEl && toEl.selectedIndex >= 0) {
            toBranchName = (toEl.options[toEl.selectedIndex].textContent || '').trim();
        }
        var row = {
            id: looseId,
            is_loose: true,
            loose_transferred: false,
            product_id: productId,
            characteristic_id: charId,
            metal_id: metalId,
            metal_name: metalName,
            product_name: productName || 'Loose item',
            barcode: barcodeVal,
            move_wt: transferWt,
            gross_wt: transferWt,
            net_wt: transferWt,
            final_wt: transferWt,
            pure_wt: transferWt,
            qty: transferQty,
            requested_wt: transferWt,
            requested_qty: transferQty,
            date: dt || todayYmd,
            branch: toBranchName,
            voucher_type: 'Loose Transfer',
            type_of_voucher: 'Stock Transfer',
            against_invoice: barcodeVal ? ('Loose / ' + barcodeVal) : 'Loose',
            net_amt: 0,
            amount: 0
        };
        destRows.push(row);
        renderDest();
        stLooseHideModal(function () {
            stShowMsg(
                'Added to transfer',
                productName + ' — ' + transferWt.toFixed(3) + ' wt, qty ' + transferQty + ' added to destination list. Click Save on the toolbar to complete the transfer.',
                'success'
            );
        });
    });

    function stAdvCleanupBackdrop() {
        document.querySelectorAll('.modal-backdrop, .st-adv-filter-backdrop').forEach(function (el) {
            if (el.parentNode) el.parentNode.removeChild(el);
        });
        document.body.classList.remove('modal-open');
        document.body.style.paddingRight = '';
        document.body.style.overflow = '';
        var modal = document.getElementById('stAdvFilterModal');
        if (modal) {
            modal.classList.remove('show');
            modal.style.display = 'none';
            modal.setAttribute('aria-hidden', 'true');
            modal.removeAttribute('aria-modal');
        }
    }

    function stAdvShowModal() {
        stAdvCleanupBackdrop();
        var modal = document.getElementById('stAdvFilterModal');
        if (!modal) return;
        var fromB = document.getElementById('stFromBranch');
        var advB = document.getElementById('stAdvBranch');
        if (advB && fromB && fromB.value) {
            advB.value = fromB.value;
        }
        stAdvPopulateProductArticleOptions();
        stAdvFillFormFromState();
        var backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show st-adv-filter-backdrop';
        backdrop.id = 'stAdvFilterBackdrop';
        document.body.appendChild(backdrop);
        document.body.classList.add('modal-open');
        modal.style.display = 'block';
        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        modal.setAttribute('aria-modal', 'true');
        backdrop.addEventListener('click', function () { stAdvHideModal(); });
    }

    function stAdvHideModal() {
        stAdvCleanupBackdrop();
    }

    function stAdvUniqueSorted(values) {
        var map = {};
        values.forEach(function (v) {
            var s = String(v || '').trim();
            if (s) map[s] = true;
        });
        return Object.keys(map).sort(function (a, b) {
            return a.localeCompare(b, undefined, { sensitivity: 'base' });
        });
    }

    function stAdvPopulateProductArticleOptions() {
        var prodEl = document.getElementById('stAdvProduct');
        var artEl = document.getElementById('stAdvArticle');
        if (!prodEl || !artEl) return;
        var metalHint = (document.getElementById('stAdvMetal').value || '').toLowerCase();
        var products = [];
        var articles = [];
        sourceRows.forEach(function (r) {
            if (metalHint) {
                var mn = String(r.metal_name || '').toLowerCase();
                if (mn !== metalHint) return;
            }
            if (r.product_name) products.push(r.product_name);
            if (r.article) articles.push(r.article);
        });
        var curP = prodEl.value;
        var curA = artEl.value;
        prodEl.innerHTML = '<option value="">Select Product Name</option>';
        stAdvUniqueSorted(products).forEach(function (p) {
            var o = document.createElement('option');
            o.value = p;
            o.textContent = p;
            prodEl.appendChild(o);
        });
        artEl.innerHTML = '<option value="">Select Article</option>';
        stAdvUniqueSorted(articles).forEach(function (a) {
            var o = document.createElement('option');
            o.value = a;
            o.textContent = a;
            artEl.appendChild(o);
        });
        if (curP) prodEl.value = curP;
        if (curA) artEl.value = curA;
    }

    function stAdvFillFormFromState() {
        var set = function (id, val) {
            var el = document.getElementById(id);
            if (el) el.value = val != null ? val : '';
        };
        set('stAdvBranch', advFilter.branch_id);
        set('stAdvMetal', advFilter.metal);
        set('stAdvProduct', advFilter.product);
        set('stAdvArticle', advFilter.article);
        set('stAdvBarcode', advFilter.barcode);
        set('stAdvDesign', advFilter.design);
        set('stAdvInvoice', advFilter.invoice);
        set('stAdvGrossWt', advFilter.gross_wt);
        var z = document.getElementById('stAdvZeroGross');
        if (z) z.checked = !!advFilter.zero_gross;
    }

    function stAdvReadFormToState() {
        advFilter.branch_id = (document.getElementById('stAdvBranch').value || '').trim();
        advFilter.metal = (document.getElementById('stAdvMetal').value || '').trim();
        advFilter.product = (document.getElementById('stAdvProduct').value || '').trim();
        advFilter.article = (document.getElementById('stAdvArticle').value || '').trim();
        advFilter.barcode = (document.getElementById('stAdvBarcode').value || '').trim();
        advFilter.design = (document.getElementById('stAdvDesign').value || '').trim();
        advFilter.invoice = (document.getElementById('stAdvInvoice').value || '').trim();
        advFilter.gross_wt = (document.getElementById('stAdvGrossWt').value || '').trim();
        advFilter.zero_gross = !!document.getElementById('stAdvZeroGross').checked;
    }

    function stAdvClearState() {
        advFilter = {
            branch_id: '',
            metal: '',
            product: '',
            article: '',
            barcode: '',
            design: '',
            invoice: '',
            gross_wt: '',
            zero_gross: false
        };
        stAdvFillFormFromState();
    }

    document.getElementById('stBtnFilter').addEventListener('click', function () {
        stAdvShowModal();
    });

    var stAdvCloseBtn = document.getElementById('stAdvFilterCloseBtn');
    if (stAdvCloseBtn) {
        stAdvCloseBtn.addEventListener('click', function (e) {
            e.preventDefault();
            stAdvHideModal();
        });
    }
    document.getElementById('stAdvMetal').addEventListener('change', function () {
        stAdvPopulateProductArticleOptions();
    });
    document.getElementById('stAdvApplyFilter').addEventListener('click', function () {
        stAdvReadFormToState();
        // If branch filter differs from current source, switch source and reload then re-apply
        var fromB = document.getElementById('stFromBranch');
        if (advFilter.branch_id && fromB && String(fromB.value) !== String(advFilter.branch_id)) {
            fromB.value = advFilter.branch_id;
            stAdvHideModal();
            loadSourceList(true); // re-render applies advFilter when rows arrive
            return;
        }
        renderSource();
        stUpdateFilterBadge();
        stAdvHideModal();
    });
    document.getElementById('stAdvClearFilter').addEventListener('click', function () {
        stAdvClearState();
        document.getElementById('stSourceFilter').value = '';
        filterText = '';
        renderSource();
        stUpdateFilterBadge();
        stAdvHideModal();
    });
    document.getElementById('stAdvApplyTransfer').addEventListener('click', function () {
        stAdvReadFormToState();
        renderSource();
        stUpdateFilterBadge();
        var added = 0;
        sourceRows.forEach(function (r) {
            if (!rowMatchesFilter(r)) return;
            if (destHas(r.id)) return;
            addToDestByStockId(r.id);
            added++;
        });
        stAdvHideModal();
        if (added <= 0) {
            stShowMsg('Apply & Transfer', 'No matching source rows to add (or they are already in the destination list).', 'info');
        } else {
            stShowMsg('Apply & Transfer', added + ' item(s) added to the destination transfer list.', 'success');
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            var m = document.getElementById('stAdvFilterModal');
            if (m && m.classList.contains('show')) {
                stAdvHideModal();
            }
        }
    });

    document.getElementById('stBtnPrintBc').addEventListener('click', function () {
        if (!destRows.length) {
            stShowMsg('Print barcode', 'Add items to the transfer list first.', 'warning');
            return;
        }
        window.print();
    });

    document.addEventListener('DOMContentLoaded', function () {
        stInitColumnSettings();
        initStockDragDrop();
        if (defaultBranchId > 0) {
            loadSourceList();
        }
    });
})();
</script>
</body>
</html>
