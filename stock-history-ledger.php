<?php
/**
 * Global stock movement ledger (from tbl_stock_journal + product / jobwork context).
 * Open via stock-history.php?ledger=1 or this file directly.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($conn) || !($conn instanceof mysqli)) {
    require_once __DIR__ . '/config.php';
}

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    if (!isset($_SESSION['Admin']['id']) || (int) $_SESSION['Admin']['id'] <= 0) {
        header('Location: index.php');
        exit;
    }
}

require_once __DIR__ . '/includes/stock_history_ledger_fetch.php';

$__shl = auragold_stock_history_ledger_fetch($conn, $_GET);
$rows = $__shl['rows'];
$err = $__shl['err'];
$tot_qty = $__shl['tot_qty'];
$tot_gross = $__shl['tot_gross'];
$tot_pure = $__shl['tot_pure'];
$filter_count = $__shl['filter_count'];
$adv_branch = $__shl['adv_branch'];
$adv_category = $__shl['adv_category'];
$adv_barcode = $__shl['adv_barcode'];
$adv_rfid = $__shl['adv_rfid'];
$adv_date_from = $__shl['adv_date_from'];
$adv_date_to = $__shl['adv_date_to'];
$adv_metal = $__shl['adv_metal'];
$adv_product = $__shl['adv_product'];
$adv_article = $__shl['adv_article'];
$adv_voucher_type = $__shl['adv_voucher_type'];
$adv_against_voucher = $__shl['adv_against_voucher'];
$adv_invoice_no = $__shl['adv_invoice_no'];
$adv_gross_wt = $__shl['adv_gross_wt'];
$adv_against_invoice_no = $__shl['adv_against_invoice_no'];
unset($__shl);

$filter_branches = function_exists('auragold_filter_branches_for_login_scope')
    ? auragold_filter_branches_for_login_scope()
    : getList("SELECT id, name FROM tbl_branches WHERE status = 1 ORDER BY name ASC");
if (!is_array($filter_branches)) {
    $filter_branches = [];
}
$adv_branch = function_exists('auragold_sanitize_adv_branch_for_login_scope')
    ? auragold_sanitize_adv_branch_for_login_scope($adv_branch)
    : $adv_branch;
$filter_categories = getList("SELECT id, name FROM tbl_categories WHERE status = 1 ORDER BY name ASC");
if (!is_array($filter_categories)) {
    $filter_categories = [];
}

$filter_metals = getList("SELECT id, display_name AS name FROM tbl_metal WHERE status = 1 ORDER BY display_name ASC");
if (!is_array($filter_metals)) {
    $filter_metals = [];
}
$filter_products = getList("SELECT id, name FROM tbl_products WHERE status = 1 AND COALESCE(is_stock_item, 1) = 1 ORDER BY name ASC LIMIT 4000");
if (!is_array($filter_products)) {
    $filter_products = [];
}

$voucher_type_opts = [];
$vtq = @mysqli_query($conn, "SELECT DISTINCT TRIM(voucher_type) AS v FROM tbl_stock_journal WHERE status = 'active' AND voucher_type IS NOT NULL AND TRIM(voucher_type) <> '' ORDER BY v ASC LIMIT 200");
if ($vtq) {
    while ($vr = mysqli_fetch_assoc($vtq)) {
        $vv = trim((string) ($vr['v'] ?? ''));
        if ($vv !== '') {
            $voucher_type_opts[] = $vv;
        }
    }
    mysqli_free_result($vtq);
}

$shl_clear_url = !empty($_GET['ledger']) ? 'stock-history.php?ledger=1' : 'stock-history-ledger.php';

?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Stock History — <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
<?php include __DIR__ . '/header-script.php'; ?>
<style>
    /* GoldMatrix logo palette: navy “Matrix” + gold “Gold” */
    :root {
        --shl-navy: #1a2b4a;
        --shl-navy-mid: #243a5c;
        --shl-navy-deep: #121f36;
        --shl-gold: #d4af37;
        --shl-gold-mid: #c9a227;
        --shl-gold-pale: #fdf6e8;
    }
    /* Advance filter modal on this page: same navy/gold tokens */
    #shlAdvFilterOverlay.filter-modal-overlay {
        --af-navy: var(--shl-navy);
        --af-navy-mid: var(--shl-navy-mid);
        --af-gold: var(--shl-gold);
        --af-gold-pale: var(--shl-gold-pale);
        --gm-navy: var(--shl-navy);
        --gm-navy-mid: var(--shl-navy-mid);
        --gm-gold: var(--shl-gold);
        --gm-gold-pale: var(--shl-gold-pale);
    }
    #shlAdvFilterOverlay .filter-modal-head {
        border-bottom: 2px solid rgba(212, 175, 55, 0.45);
    }
    #shlAdvFilterOverlay .btn-filter-clear:hover {
        background: rgba(212, 175, 55, 0.2);
    }
    .shl-banner {
        background: linear-gradient(120deg, var(--shl-navy-deep) 0%, var(--shl-navy) 45%, var(--shl-navy-mid) 100%);
        color: #fff;
        padding: 10px 18px;
        border-radius: 10px;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 8px;
        border: 1px solid rgba(212, 175, 55, 0.45);
        box-shadow: 0 4px 18px rgba(26, 43, 74, 0.22);
    }
    .shl-banner h5 { margin: 0; font-weight: 650; letter-spacing: 0.02em; }
    .shl-banner .small { color: rgba(255, 248, 230, 0.9); }
    .shl-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 10px;
    }
    .shl-toolbar-left { flex: 1 1 220px; max-width: 420px; position: relative; }
    .shl-toolbar-left .form-control { padding-left: 38px; border-radius: 8px; border-color: #c8d0e2; }
    .shl-toolbar-left .shl-toolbar-ico {
        position: absolute;
        left: 11px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 17px;
        color: var(--shl-navy);
        opacity: 0.75;
        pointer-events: none;
    }
    .shl-toolbar-right { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; position: relative; z-index: 20; }
    .shl-toolbar-right .dropdown-menu { z-index: 2000; min-width: 11rem; }
    .shl-btn-icon {
        background: var(--shl-gold-pale);
        border: 1px solid rgba(212, 175, 55, 0.55);
        color: var(--shl-navy);
        width: 38px;
        height: 38px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        padding: 0;
        position: relative;
    }
    .shl-btn-icon .feather { font-size: 18px; line-height: 1; }
    .shl-btn-icon:hover { background: rgba(212, 175, 55, 0.2); border-color: var(--shl-gold); color: var(--shl-navy); }
    .shl-filter-badge {
        position: absolute;
        top: -5px;
        right: -5px;
        min-width: 18px;
        height: 18px;
        padding: 0 5px;
        border-radius: 9px;
        background: #c45c5c;
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        line-height: 18px;
        text-align: center;
    }
    .shl-btn-export {
        border: 1px solid var(--shl-gold-mid) !important;
        color: var(--shl-navy-deep) !important;
        background: linear-gradient(180deg, #f0d875 0%, var(--shl-gold) 100%) !important;
        font-weight: 700;
        box-shadow: 0 2px 6px rgba(26, 43, 74, 0.12);
    }
    .shl-btn-export:hover,
    .shl-btn-export:focus {
        background: linear-gradient(180deg, #f5e08a 0%, #e6c34a 100%) !important;
        border-color: var(--shl-gold) !important;
        color: var(--shl-navy-deep) !important;
    }
    .shl-table-wrap {
        overflow: auto;
        background: #fff;
        border: 1px solid #d8dee9;
        border-radius: 10px;
        max-height: calc(100vh - 200px);
    }
    .shl-table-wrap table { margin-bottom: 0; min-width: 1400px; }
    .shl-table-wrap thead th {
        background: linear-gradient(180deg, var(--shl-navy-mid) 0%, var(--shl-navy) 100%) !important;
        color: #fff !important;
        border-color: rgba(212, 175, 55, 0.28) !important;
        white-space: nowrap;
        font-size: 12px;
        vertical-align: middle;
        position: sticky;
        top: 0;
        z-index: 2;
        box-shadow: inset 0 -2px 0 var(--shl-gold);
    }
    .shl-table-wrap tbody td { font-size: 13px; vertical-align: middle; border-color: #eef0f6; }
    .shl-table-wrap tbody td.shl-metric {
        background: rgba(212, 175, 55, 0.12) !important;
        font-weight: 600;
        color: var(--shl-navy);
    }
    .shl-link { color: #1e4a78; font-weight: 600; }
    .shl-link:hover { text-decoration: underline; color: var(--shl-navy); }
    .shl-gear-wrap { position: relative; }
    .shl-columns-panel {
        display: none;
        position: absolute;
        right: 0;
        top: 100%;
        margin-top: 6px;
        width: 280px;
        max-height: 360px;
        background: #fff;
        border: 1px solid #c8d0e2;
        border-radius: 10px;
        box-shadow: 0 10px 30px rgba(26, 43, 74, 0.18);
        z-index: 1200;
        padding: 10px;
    }
    .shl-columns-panel.open { display: block; }
    .shl-columns-panel h6 { font-size: 13px; margin: 0 0 8px 0; color: var(--shl-navy); }
    .shl-col-search { width: 100%; border: 1px solid #c8d0e2; border-radius: 6px; padding: 6px 8px; font-size: 12px; margin-bottom: 8px; }
    .shl-col-list { max-height: 260px; overflow: auto; font-size: 12px; }
    .shl-col-list label { display: flex; align-items: center; gap: 8px; padding: 4px 2px; margin: 0; cursor: pointer; }
    .shl-col-list input { margin: 0; accent-color: var(--shl-navy); }
    .shl-footer-total td { font-weight: 700; background: var(--shl-gold-pale) !important; border-top: 2px solid rgba(212, 175, 55, 0.55); color: var(--shl-navy); }
    #shlAdvFilterOverlay.filter-modal-overlay { z-index: 1350; }
    /* Column drag (same idea as Region masters) */
    .shl-table-wrap thead th.shl-th-reorder { position: relative; padding-left: 2rem; }
    .shl-table-wrap thead th.shl-th-reorder .shl-th-drag {
        position: absolute;
        left: 8px;
        top: 50%;
        transform: translateY(-50%);
        display: inline-block;
        box-sizing: border-box;
        width: 1.35rem;
        height: 1.35rem;
        /* No box — icon only; gold stroke so it reads on navy header */
        background-color: transparent;
        border: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23d4af37' stroke-width='2.25' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='5 9 2 12 5 15'/%3E%3Cpolyline points='9 5 12 2 15 5'/%3E%3Cpolyline points='15 19 12 22 9 19'/%3E%3Cpolyline points='19 9 22 12 19 15'/%3E%3Cline x1='2' y1='12' x2='22' y2='12'/%3E%3Cline x1='12' y1='2' x2='12' y2='22'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: center;
        background-size: 14px 14px;
        cursor: grab;
        flex-shrink: 0;
    }
    .shl-table-wrap thead th.shl-th-reorder .shl-th-drag:active { cursor: grabbing; }
    .shl-sortable-ghost { opacity: 0.45; }
    .shl-sortable-chosen { background: rgba(212, 175, 55, 0.28) !important; }
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

<div class="container-fluid py-3">
    <div class="shl-banner">
        <h5>Stock History</h5>
        <span class="small">Movements from stock journal (incl. Jobwork Invoice stock-in)</span>
    </div>

    <div class="shl-toolbar">
        <div class="shl-toolbar-left">
            <i class="feather icon-search shl-toolbar-ico" aria-hidden="true"></i>
            <input type="search" class="form-control form-control-sm" id="shlSearch" placeholder="Search table…" autocomplete="off">
        </div>
        <div class="shl-toolbar-right">
            <button type="button" class="shl-btn-icon" id="shlFilterBtn" title="Advance filter" aria-label="Advance filter">
                <i class="feather icon-filter"></i>
                <?php if ($filter_count > 0): ?><span class="shl-filter-badge"><?php echo (int) $filter_count; ?></span><?php endif; ?>
            </button>
            <button type="button" class="shl-btn-icon" id="shlRefreshBtn" title="Refresh" onclick="location.reload();"><i class="feather icon-refresh-cw"></i></button>
            <div class="dropdown">
                <button class="btn btn-sm dropdown-toggle shl-btn-export" type="button" data-toggle="dropdown" aria-expanded="false">Export</button>
                <div class="dropdown-menu dropdown-menu-right">
                    <a class="dropdown-item" href="#" id="shlExportExcel"><i class="feather icon-file-text text-success mr-2"></i>Excel</a>
                    <a class="dropdown-item" href="#" id="shlExportPdf"><i class="feather icon-file text-danger mr-2"></i>PDF</a>
                </div>
            </div>
            <div class="shl-gear-wrap">
                <button type="button" class="shl-btn-icon" id="shlGearBtn" title="Columns" aria-label="Columns"><i class="feather icon-settings"></i></button>
                <div class="shl-columns-panel" id="shlColumnsPanel">
                    <h6>Columns</h6>
                    <input type="search" class="shl-col-search" id="shlColumnSearch" placeholder="Search" autocomplete="off">
                    <div class="shl-col-list" id="shlColumnsList"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="filter-modal-overlay" id="shlAdvFilterOverlay" aria-hidden="true">
        <div class="filter-modal" role="dialog" aria-modal="true" aria-labelledby="shlAdvFilterTitle">
            <form method="get" action="" id="shlAdvFilterForm">
                <?php if (isset($_GET['ledger'])): ?><input type="hidden" name="ledger" value="1"><?php endif; ?>
                <div class="filter-modal-head" id="shlAdvFilterTitle">
                    Advance Filter
                    <button type="button" class="filter-modal-close" id="shlAdvFilterClose" aria-label="Close">&times;</button>
                </div>
                <div class="filter-modal-body">
                    <div class="filter-grid">
                        <div class="filter-field filter-field-full">
                            <label>Date Range</label>
                            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;width:100%;">
                                <div class="date-range-inputs" style="flex:1;min-width:240px;">
                                    <input type="date" name="adv_date_from" id="shlAdvDateFrom" value="<?php echo htmlspecialchars($adv_date_from); ?>">
                                    <span class="date-range-sep">–</span>
                                    <input type="date" name="adv_date_to" id="shlAdvDateTo" value="<?php echo htmlspecialchars($adv_date_to); ?>">
                                </div>
                                <button type="button" class="shl-btn-icon" style="width:34px;height:34px;flex-shrink:0;" id="shlAdvDateReset" title="Clear dates" aria-label="Clear dates"><i class="feather icon-refresh-ccw"></i></button>
                            </div>
                        </div>
                        <div class="filter-field">
                            <label>Barcode No.</label>
                            <input type="text" name="adv_barcode" value="<?php echo htmlspecialchars($adv_barcode); ?>" placeholder="Contains…" autocomplete="off">
                        </div>
                        <div class="filter-field">
                            <label>RFID</label>
                            <input type="text" name="adv_rfid" value="<?php echo htmlspecialchars($adv_rfid); ?>" placeholder="Contains…" autocomplete="off">
                        </div>
                        <div class="filter-field">
                            <label>Branch</label>
                            <select name="adv_branch">
                                <option value="0">All</option>
                                <?php foreach ($filter_branches as $fb): ?>
                                    <option value="<?php echo (int) $fb['id']; ?>" <?php echo $adv_branch === (int) $fb['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($fb['name'] ?? ''); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-field">
                            <label>Metal</label>
                            <select name="adv_metal">
                                <option value="0">All</option>
                                <?php foreach ($filter_metals as $fm): ?>
                                    <option value="<?php echo (int) $fm['id']; ?>" <?php echo $adv_metal === (int) $fm['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($fm['name'] ?? ''); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-field filter-field-full">
                            <label>Product</label>
                            <div class="mp-ms" data-mp-ms data-mp-label="Select Product">
                                <button type="button" class="mp-ms-btn" aria-expanded="false">Select Product</button>
                                <div class="mp-ms-panel">
                                    <label class="mp-ms-all"><input type="checkbox" class="mp-ms-check-all"> Select All</label>
                                    <input type="search" class="mp-ms-search" placeholder="Search" autocomplete="off">
                                    <div class="mp-ms-list">
                                        <?php foreach ($filter_products as $fp): ?>
                                            <label class="mp-ms-opt">
                                                <input type="checkbox" name="adv_product[]" value="<?php echo (int) $fp['id']; ?>" <?php echo in_array((int) $fp['id'], $adv_product, true) ? 'checked' : ''; ?>>
                                                <span><?php echo htmlspecialchars($fp['name'] ?? ''); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="filter-field">
                            <label>Article</label>
                            <input type="text" name="adv_article" value="<?php echo htmlspecialchars($adv_article); ?>" placeholder="Contains…" autocomplete="off">
                        </div>
                        <div class="filter-field">
                            <label>Category</label>
                            <select name="adv_category">
                                <option value="0">All</option>
                                <?php foreach ($filter_categories as $fc): ?>
                                    <option value="<?php echo (int) $fc['id']; ?>" <?php echo $adv_category === (int) $fc['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($fc['name'] ?? ''); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-field">
                            <label>Voucher Type</label>
                            <select name="adv_voucher_type">
                                <option value="">All</option>
                                <?php foreach ($voucher_type_opts as $vto): ?>
                                    <option value="<?php echo htmlspecialchars($vto, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $adv_voucher_type === $vto ? 'selected' : ''; ?>><?php echo htmlspecialchars($vto); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-field">
                            <label>Against Voucher Type</label>
                            <input type="text" name="adv_against_voucher" value="<?php echo htmlspecialchars($adv_against_voucher); ?>" placeholder="Jobwork no…" autocomplete="off">
                        </div>
                        <div class="filter-field">
                            <label>Invoice No.</label>
                            <input type="text" name="adv_invoice_no" value="<?php echo htmlspecialchars($adv_invoice_no); ?>" placeholder="Contains…" autocomplete="off">
                        </div>
                        <div class="filter-field">
                            <label>Gross Wt</label>
                            <input type="text" name="adv_gross_wt" value="<?php echo htmlspecialchars($adv_gross_wt); ?>" placeholder="Exact match" autocomplete="off">
                        </div>
                        <div class="filter-field">
                            <label>Against Invoice No.</label>
                            <input type="text" name="adv_against_invoice_no" value="<?php echo htmlspecialchars($adv_against_invoice_no); ?>" placeholder="Sale order no…" autocomplete="off">
                        </div>
                    </div>
                </div>
                <div class="filter-modal-foot">
                    <button type="submit" class="btn-filter-apply">Apply Filter</button>
                    <a href="<?php echo htmlspecialchars($shl_clear_url); ?>" class="btn-filter-clear" style="display:inline-flex;align-items:center;justify-content:center;text-decoration:none;">Clear Filter</a>
                </div>
            </form>
        </div>
    </div>

    <?php if ($err !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div>
    <?php endif; ?>

    <div class="shl-table-wrap">
        <table class="table table-sm table-bordered table-hover" id="shlTable">
            <thead>
                <tr>
                    <th data-col="date" class="shl-th-reorder"><span class="shl-th-drag" title="Drag to reorder columns"></span>Date</th>
                    <th data-col="barcode" class="shl-th-reorder"><span class="shl-th-drag" title="Drag to reorder columns"></span>Barcode No</th>
                    <th data-col="rfid" class="shl-th-reorder"><span class="shl-th-drag" title="Drag to reorder columns"></span>RFID</th>
                    <th data-col="against_invoice" class="shl-th-reorder"><span class="shl-th-drag" title="Drag to reorder columns"></span>Against Invoice No</th>
                    <th data-col="voucher_type" class="shl-th-reorder"><span class="shl-th-drag" title="Drag to reorder columns"></span>Voucher Type</th>
                    <th data-col="location" class="shl-th-reorder"><span class="shl-th-drag" title="Drag to reorder columns"></span>Location</th>
                    <th data-col="invoice_no" class="shl-th-reorder"><span class="shl-th-drag" title="Drag to reorder columns"></span>Invoice No.</th>
                    <th data-col="against_voucher" class="shl-th-reorder"><span class="shl-th-drag" title="Drag to reorder columns"></span>Against Voucher Type</th>
                    <th data-col="branch" class="shl-th-reorder"><span class="shl-th-drag" title="Drag to reorder columns"></span>Branch</th>
                    <th data-col="qty" class="shl-metric shl-th-reorder"><span class="shl-th-drag" title="Drag to reorder columns"></span>Qty.</th>
                    <th data-col="gross_wt" class="shl-metric shl-th-reorder"><span class="shl-th-drag" title="Drag to reorder columns"></span>Gross Wt</th>
                    <th data-col="pure_wt" class="shl-metric shl-th-reorder"><span class="shl-th-drag" title="Drag to reorder columns"></span>Pure Wt.</th>
                    <th data-col="product_name" class="shl-th-reorder"><span class="shl-th-drag" title="Drag to reorder columns"></span>Product Name</th>
                    <th data-col="metal" class="shl-th-reorder"><span class="shl-th-drag" title="Drag to reorder columns"></span>Metal</th>
                    <th data-col="category" class="shl-th-reorder"><span class="shl-th-drag" title="Drag to reorder columns"></span>Category</th>
                    <th data-col="article" class="shl-th-reorder"><span class="shl-th-drag" title="Drag to reorder columns"></span>Article</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row):
                    $d = !empty($row['sj_date']) ? $row['sj_date'] : '';
                    $dShow = $d ? date('d-m-Y', strtotime($d)) : '';
                    $soId = (int) ($row['sale_order_id'] ?? 0);
                    $jwoId = (int) ($row['jobwork_order_id'] ?? 0);
                    $son = trim((string) ($row['sale_order_no'] ?? ''));
                    $docInv = trim((string) ($row['doc_invoice_no'] ?? ''));
                    $jwn = trim((string) ($row['jobwork_no'] ?? ''));

                    $againstInvHtml = $son !== ''
                        ? ($soId > 0 ? '<a class="shl-link" href="sale-order.php?id=' . $soId . '">' . htmlspecialchars($son) . '</a>' : htmlspecialchars($son))
                        : '—';

                    if ($docInv === '') {
                        $invoiceHtml = '—';
                    } elseif ($soId > 0) {
                        $invoiceHtml = '<a class="shl-link" href="jobwork-invoice.php?sale_order_id=' . $soId . '">' . htmlspecialchars($docInv) . '</a>';
                    } elseif ($jwoId > 0) {
                        $invoiceHtml = '<a class="shl-link" href="jobwork-invoice.php?id=' . $jwoId . '">' . htmlspecialchars($docInv) . '</a>';
                    } else {
                        $invoiceHtml = htmlspecialchars($docInv);
                    }

                    $againstVoucherHtml = $jwn !== '' && $jwoId > 0
                        ? '<a class="shl-link" href="jobwork-order.php?id=' . $jwoId . '">' . htmlspecialchars($jwn) . '</a>'
                        : '—';
                    $voucherTypeDisplay = auragold_stock_history_ledger_voucher_display((string) ($row['voucher_type'] ?? ''));
                ?>
                    <tr>
                        <td data-col="date"><?php echo htmlspecialchars($dShow); ?></td>
                        <td data-col="barcode"><?php echo htmlspecialchars((string) ($row['barcode'] ?? '')); ?></td>
                        <td data-col="rfid"><?php echo htmlspecialchars((string) ($row['rfid'] ?? '')); ?></td>
                        <td data-col="against_invoice"><?php echo $againstInvHtml; ?></td>
                        <td data-col="voucher_type"><?php echo htmlspecialchars($voucherTypeDisplay); ?></td>
                        <td data-col="location"><?php echo htmlspecialchars((string) ($row['location'] ?? '')); ?></td>
                        <td data-col="invoice_no"><?php echo $invoiceHtml; ?></td>
                        <td data-col="against_voucher"><?php echo $againstVoucherHtml; ?></td>
                        <td data-col="branch"><?php echo htmlspecialchars((string) ($row['branch_name'] ?? '')); ?></td>
                        <td data-col="qty" class="shl-metric text-right"><?php echo number_format((float) ($row['qty'] ?? 0), 0); ?></td>
                        <td data-col="gross_wt" class="shl-metric text-right"><?php echo number_format((float) ($row['gross_wt'] ?? 0), 3); ?></td>
                        <td data-col="pure_wt" class="shl-metric text-right"><?php echo number_format((float) ($row['pure_wt'] ?? 0), 3); ?></td>
                        <td data-col="product_name"><?php echo htmlspecialchars((string) ($row['product_name'] ?? '')); ?></td>
                        <td data-col="metal"><?php echo htmlspecialchars((string) ($row['metal_name'] ?? '')); ?></td>
                        <td data-col="category"><?php echo htmlspecialchars((string) ($row['category_name'] ?? '')); ?></td>
                        <td data-col="article"><?php echo htmlspecialchars((string) ($row['article'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="shl-footer-total">
                <tr>
                    <td data-col="date"></td>
                    <td data-col="barcode"></td>
                    <td data-col="rfid"></td>
                    <td data-col="against_invoice"></td>
                    <td data-col="voucher_type"></td>
                    <td data-col="location"></td>
                    <td data-col="invoice_no"></td>
                    <td data-col="against_voucher"></td>
                    <td data-col="branch"></td>
                    <td data-col="qty" class="shl-metric text-right"><?php echo number_format($tot_qty, 0); ?></td>
                    <td data-col="gross_wt" class="shl-metric text-right"><?php echo number_format($tot_gross, 3); ?></td>
                    <td data-col="pure_wt" class="shl-metric text-right"><?php echo number_format($tot_pure, 3); ?></td>
                    <td data-col="product_name" class="text-right"><strong>Total</strong></td>
                    <td data-col="metal"></td>
                    <td data-col="category"></td>
                    <td data-col="article"></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <p class="text-muted small mt-2 mb-0"><?php echo count($rows); ?> rows (max 5,000). Jobwork lines are tagged in stock journal for traceability.</p>
</div>

                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer-script.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function () {
    function mpMsUpdateLabel(wrap) {
        var btn = wrap.querySelector('.mp-ms-btn');
        var list = wrap.querySelector('.mp-ms-list');
        var ph = wrap.getAttribute('data-mp-label') || 'Select';
        if (!btn || !list) return;
        var opts = list.querySelectorAll('input[type="checkbox"]');
        var checked = list.querySelectorAll('input[type="checkbox"]:checked');
        var n = checked.length;
        var total = opts.length;
        if (n === 0) {
            btn.textContent = ph;
        } else if (total && n === total) {
            btn.textContent = ph + ' (all)';
        } else {
            btn.textContent = ph + ' (' + n + ')';
        }
    }

    function initMpMultiSelectDropdowns(root) {
        root = root || document;
        root.querySelectorAll('[data-mp-ms]').forEach(function (wrap) {
            if (wrap._mpMsInit) return;
            wrap._mpMsInit = true;
            var btn = wrap.querySelector('.mp-ms-btn');
            var panel = wrap.querySelector('.mp-ms-panel');
            var search = wrap.querySelector('.mp-ms-search');
            var list = wrap.querySelector('.mp-ms-list');
            var allCb = wrap.querySelector('.mp-ms-check-all');

            function syncAll() {
                var opts = list.querySelectorAll('input[type="checkbox"]');
                var checked = list.querySelectorAll('input[type="checkbox"]:checked');
                if (allCb) {
                    allCb.indeterminate = checked.length > 0 && checked.length < opts.length;
                    allCb.checked = opts.length > 0 && checked.length === opts.length;
                }
                mpMsUpdateLabel(wrap);
            }

            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var wasOpen = panel.classList.contains('is-open');
                document.querySelectorAll('.mp-ms-panel.is-open').forEach(function (p) {
                    p.classList.remove('is-open');
                });
                document.querySelectorAll('.mp-ms-btn').forEach(function (b) {
                    b.setAttribute('aria-expanded', 'false');
                });
                if (!wasOpen) {
                    panel.classList.add('is-open');
                    btn.setAttribute('aria-expanded', 'true');
                }
            });

            if (allCb) {
                allCb.addEventListener('change', function () {
                    var v = allCb.checked;
                    list.querySelectorAll('.mp-ms-opt').forEach(function (lab) {
                        if (lab.style.display === 'none') return;
                        var cb = lab.querySelector('input[type="checkbox"]');
                        if (cb) cb.checked = v;
                    });
                    syncAll();
                });
            }
            list.addEventListener('change', function (e) {
                if (e.target && e.target.type === 'checkbox' && e.target !== allCb) syncAll();
            });

            if (search) {
                search.addEventListener('input', function () {
                    var q = (search.value || '').toLowerCase().trim();
                    list.querySelectorAll('.mp-ms-opt').forEach(function (lab) {
                        var t = (lab.textContent || '').toLowerCase();
                        lab.style.display = !q || t.indexOf(q) !== -1 ? '' : 'none';
                    });
                });
            }
            syncAll();
        });

        if (!document._shlMpMsDocClick) {
            document._shlMpMsDocClick = true;
            document.addEventListener('click', function (e) {
                if (e.target.closest && e.target.closest('.mp-ms')) return;
                document.querySelectorAll('.mp-ms-panel.is-open').forEach(function (p) {
                    p.classList.remove('is-open');
                });
                document.querySelectorAll('.mp-ms-btn').forEach(function (b) {
                    b.setAttribute('aria-expanded', 'false');
                });
            });
        }
    }

    function openAdvFilterModal() {
        var overlay = document.getElementById('shlAdvFilterOverlay');
        var form = document.getElementById('shlAdvFilterForm');
        if (!overlay) return;
        overlay.classList.add('show');
        overlay.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        if (form && !form._shlMpMsInit) {
            form._shlMpMsInit = true;
            initMpMultiSelectDropdowns(form);
        }
    }

    function closeAdvFilterModal() {
        var overlay = document.getElementById('shlAdvFilterOverlay');
        if (!overlay) return;
        overlay.classList.remove('show');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        document.querySelectorAll('.mp-ms-panel.is-open').forEach(function (p) {
            p.classList.remove('is-open');
        });
    }

    var COLS = [
        { key: 'date', label: 'Date' },
        { key: 'barcode', label: 'Barcode No' },
        { key: 'rfid', label: 'RFID' },
        { key: 'against_invoice', label: 'Against Invoice No' },
        { key: 'voucher_type', label: 'Voucher Type' },
        { key: 'location', label: 'Location' },
        { key: 'invoice_no', label: 'Invoice No.' },
        { key: 'against_voucher', label: 'Against Voucher Type' },
        { key: 'branch', label: 'Branch' },
        { key: 'qty', label: 'Qty.' },
        { key: 'gross_wt', label: 'Gross Wt' },
        { key: 'pure_wt', label: 'Pure Wt.' },
        { key: 'product_name', label: 'Product Name' },
        { key: 'metal', label: 'Metal' },
        { key: 'category', label: 'Category' },
        { key: 'article', label: 'Article' }
    ];
    var LS_KEY = 'auragold_stock_history_ledger_cols';
    var ORDER_LS = 'auragold_stock_history_ledger_col_order';

    function getHeaderOrder() {
        return Array.prototype.map.call(document.querySelectorAll('#shlTable thead th[data-col]'), function (th) {
            return th.getAttribute('data-col');
        });
    }

    function syncBodyFootToHeaderOrder() {
        var order = getHeaderOrder();
        document.querySelectorAll('#shlTable tbody tr, #shlTable tfoot tr').forEach(function (tr) {
            var byCol = {};
            tr.querySelectorAll('td[data-col]').forEach(function (td) {
                byCol[td.getAttribute('data-col')] = td;
            });
            order.forEach(function (k) {
                if (byCol[k]) {
                    tr.appendChild(byCol[k]);
                }
            });
        });
    }

    function applySavedColumnOrder() {
        try {
            var j = localStorage.getItem(ORDER_LS);
            if (!j) return;
            var order = JSON.parse(j);
            if (!Array.isArray(order) || order.length !== COLS.length) return;
            var need = {};
            COLS.forEach(function (c) { need[c.key] = 0; });
            order.forEach(function (k) {
                if (Object.prototype.hasOwnProperty.call(need, k)) need[k]++;
            });
            var ok = true;
            COLS.forEach(function (c) {
                if (need[c.key] !== 1) ok = false;
            });
            if (!ok) return;
            var theadRow = document.querySelector('#shlTable thead tr');
            if (!theadRow) return;
            var byCol = {};
            theadRow.querySelectorAll('th[data-col]').forEach(function (th) {
                byCol[th.getAttribute('data-col')] = th;
            });
            order.forEach(function (k) {
                if (byCol[k]) theadRow.appendChild(byCol[k]);
            });
            syncBodyFootToHeaderOrder();
        } catch (e) {}
    }

    function saveColumnOrder() {
        try {
            localStorage.setItem(ORDER_LS, JSON.stringify(getHeaderOrder()));
        } catch (e) {}
    }

    function loadHidden() {
        try {
            var j = localStorage.getItem(LS_KEY);
            if (!j) return {};
            return JSON.parse(j) || {};
        } catch (e) { return {}; }
    }
    function saveHidden(map) {
        try { localStorage.setItem(LS_KEY, JSON.stringify(map)); } catch (e) {}
    }

    function applyVisibility() {
        var hidden = loadHidden();
        document.querySelectorAll('#shlTable [data-col]').forEach(function (el) {
            var k = el.getAttribute('data-col');
            if (!k) return;
            if (hidden[k]) {
                el.style.display = 'none';
            } else {
                el.style.display = '';
            }
        });
    }

    function buildColumnMenu() {
        var hidden = loadHidden();
        var wrap = document.getElementById('shlColumnsList');
        if (!wrap) return;
        wrap.innerHTML = '';
        COLS.forEach(function (c) {
            var id = 'shl_col_' + c.key;
            var lab = document.createElement('label');
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.checked = !hidden[c.key];
            cb.dataset.col = c.key;
            var sp = document.createElement('span');
            sp.textContent = c.label;
            lab.appendChild(cb);
            lab.appendChild(sp);
            wrap.appendChild(lab);
            cb.addEventListener('change', function () {
                var h = loadHidden();
                h[c.key] = !cb.checked;
                saveHidden(h);
                applyVisibility();
            });
        });
    }

    document.getElementById('shlGearBtn').addEventListener('click', function (e) {
        e.stopPropagation();
        var p = document.getElementById('shlColumnsPanel');
        p.classList.toggle('open');
    });
    document.addEventListener('click', function () {
        var p = document.getElementById('shlColumnsPanel');
        if (p) p.classList.remove('open');
    });
    document.getElementById('shlColumnsPanel').addEventListener('click', function (e) { e.stopPropagation(); });

    document.getElementById('shlColumnSearch').addEventListener('input', function () {
        var q = (this.value || '').toLowerCase();
        document.querySelectorAll('#shlColumnsList label').forEach(function (lab) {
            var t = (lab.textContent || '').toLowerCase();
            lab.style.display = !q || t.indexOf(q) !== -1 ? '' : 'none';
        });
    });

    document.getElementById('shlFilterBtn').addEventListener('click', function (e) {
        e.stopPropagation();
        openAdvFilterModal();
    });
    var advOverlay = document.getElementById('shlAdvFilterOverlay');
    var advClose = document.getElementById('shlAdvFilterClose');
    if (advOverlay) {
        advOverlay.addEventListener('click', function (e) {
            if (e.target === advOverlay) closeAdvFilterModal();
        });
    }
    if (advClose) {
        advClose.addEventListener('click', closeAdvFilterModal);
    }
    var advForm = document.getElementById('shlAdvFilterForm');
    if (advForm) {
        advForm.addEventListener('submit', function () {
            closeAdvFilterModal();
        });
    }
    var dateReset = document.getElementById('shlAdvDateReset');
    if (dateReset) {
        dateReset.addEventListener('click', function () {
            var a = document.getElementById('shlAdvDateFrom');
            var b = document.getElementById('shlAdvDateTo');
            if (a) a.value = '';
            if (b) b.value = '';
        });
    }

    document.getElementById('shlSearch').addEventListener('input', function () {
        var q = (this.value || '').toLowerCase().trim();
        document.querySelectorAll('#shlTable tbody tr').forEach(function (tr) {
            var txt = tr.textContent.toLowerCase();
            tr.style.display = !q || txt.indexOf(q) !== -1 ? '' : 'none';
        });
    });

    function shlExportQueryString() {
        var qs = window.location.search || '';
        if (!qs || qs === '?') {
            return '?ledger=1';
        }
        if (qs.indexOf('ledger=') === -1) {
            return qs + (qs.charAt(qs.length - 1) === '&' ? '' : '&') + 'ledger=1';
        }
        return qs;
    }

    document.getElementById('shlExportExcel').addEventListener('click', function (e) {
        e.preventDefault();
        window.location.href = 'ajax/export-stock-history-ledger-excel.php' + shlExportQueryString();
    });
    document.getElementById('shlExportPdf').addEventListener('click', function (e) {
        e.preventDefault();
        window.location.href = 'ajax/export-stock-history-ledger-pdf.php' + shlExportQueryString();
    });

    applySavedColumnOrder();
    buildColumnMenu();
    applyVisibility();

    var shlTheadRow = document.querySelector('#shlTable thead tr');
    if (shlTheadRow && typeof Sortable !== 'undefined') {
        Sortable.create(shlTheadRow, {
            animation: 150,
            handle: '.shl-th-drag',
            draggable: 'th',
            ghostClass: 'shl-sortable-ghost',
            chosenClass: 'shl-sortable-chosen',
            onEnd: function () {
                syncBodyFootToHeaderOrder();
                saveColumnOrder();
            }
        });
    }
})();
</script>
</body>
</html>
