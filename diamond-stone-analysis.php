<?php 
session_start();
require_once 'config.php';
require_once __DIR__ . '/includes/auragold_analysis_show_in_stock_sql.php';
require_once __DIR__ . '/includes/gold_silver_analysis_helpers.php';

/** mysqli_fetch_assoc column names can vary in casing; use for Current Stock weight columns */
if (!function_exists('stock_analysis_row_col')) {
    function stock_analysis_row_col(array $row, string $name): ?float {
        foreach ($row as $k => $v) {
            if (strcasecmp((string) $k, $name) === 0) {
                return ($v === null || $v === '') ? null : (float) $v;
            }
        }
        return null;
    }
}

// Get active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'current-stock';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
if ($per_page < 10) {
    $per_page = 10;
} elseif ($per_page > 100) {
    $per_page = 100;
}
$offset = ($page - 1) * $per_page;

require_once __DIR__ . '/includes/diamond_stone_analysis_roll_up_include.php';

// Branches for filters: login main + sub-branches (set in diamond_stone_analysis_roll_up_include.php)
if (!isset($branches) || !is_array($branches)) {
    $branches = [];
}
$metals = $scope_metals;

$dsa_base_q = [];
if ($search_raw !== '') {
    $dsa_base_q['search'] = $search_raw;
}
if (isset($_GET['branch'])) {
    $dsa_base_q['branch'] = (int) $_GET['branch'];
} elseif ($branch_filter > 0) {
    $dsa_base_q['branch'] = $branch_filter;
}
if ($metal_filter > 0) {
    $dsa_base_q['metal'] = $metal_filter;
}
if ($per_page != 20) {
    $dsa_base_q['per_page'] = $per_page;
}
$dsa_href_stock = 'diamond-stone-analysis.php?' . http_build_query(array_merge($dsa_base_q, ['tab' => 'current-stock']));
$dsa_href_details = 'diamond-stone-analysis.php?' . http_build_query(array_merge($dsa_base_q, ['tab' => 'stock-details']));
$dsa_clear_href = 'diamond-stone-analysis.php?' . http_build_query(['tab' => $active_tab]);
$dsa_export_query = isset($_SERVER['QUERY_STRING']) ? (string) $_SERVER['QUERY_STRING'] : '';

?>
<!DOCTYPE html>

<html lang="en" class="default-style">

<head>
    <title>Diamond &amp; Stone Analysis - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?> Software</title>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <meta name="description" content="Diamond &amp; Stone Analysis - Current Stock" />
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
<?php include 'header-script.php';?>
</head>

<style>
html, body{
    overflow-x: hidden !important;
    overflow-y: hidden !important;
    height: 100vh;
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
}

/* ===== PAGE STYLING ===== */
.stock-analysis-wrapper {
    display: flex;
    flex-direction: column;
    height: 100%;
    overflow: hidden;
}

/* Tab bar action icons (right side) */
.tabs-bar-actions {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-shrink: 0;
    margin-left: auto;
}

.tabs-bar-actions .btn-icon {
    background: rgba(255,255,255,0.2);
    border: none;
    color: #fff;
    width: 32px;
    height: 32px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
}

.tabs-bar-actions .btn-icon:hover {
    background: rgba(255,255,255,0.3);
}

.tabs-bar-actions .dropdown-menu {
    z-index: 2000;
    min-width: 11rem;
}

#diamondTableSettingsDropdown {
    z-index: 1050;
    max-height: 70vh;
    overflow-y: auto;
}
#diamondTableSettingsDropdown .form-check {
    margin-bottom: 0.35rem;
    font-size: 0.8rem;
}
#diamondTableSettingsDropdown .form-check-label {
    cursor: pointer;
    user-select: none;
}

#diamondTableSettingsSearch {
    font-size: 0.8rem;
}

#diamondTableSettingsItems .diamond-table-settings-item.hidden {
    display: none !important;
}

.stock-table th.hidden,
.stock-table td.hidden {
    display: none !important;
}

.table-footer-totals .total-item.hidden {
    display: none !important;
}

/* Tabs */
.tabs-container {
    background: #11294b;
    border-bottom: 2px solid #e2e8f0;
    padding: 0 12px 0 20px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.tabs-list {
    display: flex;
    gap: 0;
    margin: 0;
    padding: 0;
    list-style: none;
    flex: 1;
    min-width: 0;
}

.tabs-list li {
    margin: 0;
}

.tab-link {
    display: block;
    padding: 12px 20px;
    color: rgba(255,255,255,0.75);
    text-decoration: none;
    border-bottom: 3px solid transparent;
    font-weight: 500;
    transition: all 0.2s;
    cursor: pointer;
}

.tab-link:hover {
    color: #fff;
    background: rgba(255,255,255,0.08);
}

.tab-link.active {
    color: #11294b;
    background: #c5a864;
    border-bottom-color: transparent;
    font-weight: 600;
}

.tab-link.active:hover {
    color: #11294b;
    background: #d4b87a;
}

/* Table Container */
.table-container {
    flex: 1;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    background: #fff;
}

.table-wrapper {
    flex: 1;
    overflow: auto;
    padding: 20px;
}

/* Table Styling */
.stock-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.stock-table thead {
    background: #f8fafc;
    position: sticky;
    top: 0;
    z-index: 10;
}

.stock-table th {
    padding: 12px 10px;
    text-align: left;
    font-weight: 600;
    color: #ffffff;
    border-bottom: 2px solid #e2e8f0;
    white-space: nowrap;
    font-size: 0.8rem;
}

.stock-table th.sortable {
    cursor: pointer;
    user-select: none;
}

.stock-table th.sortable:hover {
    background: #f1f5f9;
}

.stock-table th .sort-arrows {
    display: inline-flex;
    flex-direction: column;
    margin-left: 4px;
    vertical-align: middle;
    font-size: 0.7rem;
    opacity: 0.5;
}

.stock-table th.sortable:hover .sort-arrows {
    opacity: 1;
}

.stock-table tbody tr {
    border-bottom: 1px solid #e2e8f0;
    transition: background 0.2s;
}

.stock-table tbody tr:hover {
    background: #f8fafc;
}

.stock-table td {
    padding: 12px 10px;
    color: #000;
    vertical-align: middle;
}

.stock-table td.negative {
    color: #dc2626;
    font-weight: 500;
}

.stock-table .view-history-btn {
    background: #11294b;
    color: #fff;
    border: none;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 0.75rem;
    cursor: pointer;
    transition: all 0.2s;
}

.stock-table .view-history-btn:hover {
    background: #4a2f70;
}

.stock-table.dsa-details-table {
    min-width: 1200px;
    border-collapse: separate;
    border-spacing: 0;
}

.stock-table.dsa-details-table th,
.stock-table.dsa-details-table td {
    border: 1px solid #e2e8f0;
    vertical-align: middle;
}

.stock-table.dsa-details-table thead {
    position: sticky;
    top: 0;
    z-index: 2;
}

.stock-table.dsa-details-table thead th {
    background: #f8fafc;
    font-size: 0.75rem;
    font-weight: 600;
    text-align: center;
    white-space: nowrap;
}

.stock-table.dsa-details-table thead th.dsa-text-cell {
    text-align: left;
}

.stock-table.dsa-details-table thead th.dsa-group-head {
    background: #eef2ff;
    color: #3730a3;
}

.stock-table.dsa-details-table .dsa-subhead {
    font-size: 0.6875rem;
    font-weight: 500;
    color: #64748b;
    background: #f1f5f9;
}

.stock-table.dsa-details-table tbody td {
    font-size: 0.8125rem;
    text-align: right;
}

.stock-table.dsa-details-table tbody td.dsa-text-cell {
    text-align: left;
}

.stock-table.dsa-details-table tfoot td {
    background: #f8fafc;
    font-weight: 700;
    text-align: right;
    border-top: 2px solid #cbd5e1;
}

.stock-table.dsa-details-table tfoot td.dsa-text-cell {
    text-align: left;
}

.stock-table.dsa-details-table tbody td.negative,
.stock-table.dsa-details-table tfoot td.negative {
    color: #dc2626;
}

/* Table Footer */
.table-footer {
    background: #f8fafc;
    border-top: 2px solid #e2e8f0;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.table-footer-info {
    color: #64748b;
    font-size: 0.875rem;
}

.table-footer-totals {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.table-footer-totals .total-item {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

.table-footer-totals .total-label {
    font-size: 0.75rem;
    color: #64748b;
    margin-bottom: 2px;
}

.table-footer-totals .total-value {
    font-size: 0.875rem;
    font-weight: 600;
    color: #ffffff;
}

.pagination-controls {
    display: flex;
    gap: 5px;
    align-items: center;
}

.pagination-controls .page-btn {
    background: #fff;
    border: 1px solid #e2e8f0;
    color: #64748b;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.875rem;
    transition: all 0.2s;
}

.pagination-controls .page-btn:hover:not(:disabled) {
    background: #f8fafc;
    border-color: #cbd5e1;
}

.pagination-controls .page-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.pagination-controls .page-btn.active {
    background: #11294b;
    color: #fff;
    border-color: #11294b;
}

.pagination-controls .show-all-dropdown {
    padding: 6px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    font-size: 0.875rem;
    color: #64748b;
    background: #fff;
}

/* Filter Section */
.filter-section {
    background: #fff;
    padding: 15px 20px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
}

.filter-section .form-control {
    height: 36px;
    font-size: 0.875rem;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    padding: 6px 12px;
}

.filter-section label {
    font-size: 0.875rem;
    color: #000;
    font-weight: 500;
    margin-bottom: 0;
    margin-right: 8px;
}

</style>

<body>
<!-- [ Preloader ] Start -->
<div class="page-loader">
    <div class="bg-primary"></div>
</div>
<!-- [ Preloader ] End -->

<!-- [ Layout wrapper ] Start -->
<div class="layout-wrapper layout-2">
    <div class="layout-inner">
        <!-- [ Layout sidenav ] Start -->
        <div id="layout-sidenav" class="layout-sidenav sidenav sidenav-vertical bg-white logo-dark">
            <!-- Brand demo -->
            <div class="app-brand demo">
                <span class="app-brand-logo demo">
                    <img src="assets/img/logo.png" alt="Brand Logo" class="img-fluid">
                </span>
                <a href="index-2.html" class="app-brand-text demo sidenav-text font-weight-normal ml-2">Empire</a>
                <a href="javascript:" class="layout-sidenav-toggle sidenav-link text-large ml-auto">
                    <i class="ion ion-md-menu align-middle"></i>
                </a>
            </div>
            <div class="sidenav-divider mt-0"></div>

            <!-- Links -->
            <ul class="sidenav-inner py-1">
                <li class="sidenav-item active">
                    <a href="billing-sales-invoice.html" class="sidenav-link">
                        <i class="sidenav-icon feather icon-file-text"></i>
                        <div>Sales Invoice</div>
                    </a>
                </li>
            </ul>
        </div>
        <!-- [ Layout sidenav ] End -->

        <!-- [ Layout container ] Start -->
        <div class="layout-container">
            <!-- [ Layout navbar ( Header ) ] Start -->
            <nav class="layout-navbar navbar navbar-expand-lg align-items-lg-center bg-dark container-p-x" id="layout-navbar">
                <a href="index-2.html" class="navbar-brand app-brand demo d-lg-none py-0 mr-4">
                    <span class="app-brand-logo demo">
                        <img src="assets/img/logo-dark.png" alt="Brand Logo" class="img-fluid">
                    </span>
                    <span class="app-brand-text demo font-weight-normal ml-2">Empire</span>
                </a>

                <div class="layout-sidenav-toggle navbar-nav d-lg-none align-items-lg-center mr-auto">
                    <a class="nav-item nav-link px-0 mr-lg-4" href="javascript:">
                        <i class="ion ion-md-menu text-large align-middle"></i>
                    </a>
                </div>

                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#layout-navbar-collapse">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="navbar-collapse collapse" id="layout-navbar-collapse">
                    <div class="navbar-nav align-items-lg-center ml-auto">
                        <div class="demo-navbar-notifications nav-item dropdown mr-lg-3">
                            <a class="nav-link dropdown-toggle hide-arrow" href="#" data-toggle="dropdown">
                                <i class="feather icon-bell navbar-icon align-middle"></i>
                                <span class="badge badge-danger badge-dot indicator"></span>
                            </a>
                        </div>
                        <div class="demo-navbar-user nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown">
                                <span class="d-inline-flex flex-lg-row-reverse align-items-center align-middle">
                                    <img src="assets/img/avatars/1.png" alt class="d-block ui-w-30 rounded-circle">
                                    <span class="px-1 mr-lg-2 ml-2 ml-lg-0">SUPER ADMIN</span>
                                </span>
                            </a>
                        </div>
                    </div>
                </div>
            </nav>
            <!-- [ Layout navbar ( Header ) ] End -->

            <!-- [ Layout content ] Start -->
            <div class="layout-content">
                <!-- [ content ] Start -->
                <div class="container-fluid flex-grow-1" style="padding-top: 0; padding-bottom: 0;">
                    <?php include 'sidebar.php';?>

                    <div class="row">
                        <div class="col-lg-12">
                            <div class="card mb-4" style="height: calc(100vh - 120px); display: flex; flex-direction: column; overflow: hidden;">
                                <div class="card-body" style="padding: 0; display: flex; flex-direction: column; overflow: hidden;">

                                    <div class="stock-analysis-wrapper">
                                        
                                        <!-- Tabs + actions (icons on the right) -->
                                        <div class="tabs-container">
                                            <ul class="tabs-list">
                                                <li>
                                                    <a href="<?= htmlspecialchars($dsa_href_stock, ENT_QUOTES, 'UTF-8') ?>"
                                                       class="tab-link <?= $active_tab == 'current-stock' ? 'active' : '' ?>">
                                                        Current Stock
                                                    </a>
                                                </li>
                                                <li>
                                                    <a href="<?= htmlspecialchars($dsa_href_details, ENT_QUOTES, 'UTF-8') ?>"
                                                       class="tab-link <?= $active_tab == 'stock-details' ? 'active' : '' ?>">
                                                        Stock Details
                                                    </a>
                                                </li>
                                            </ul>
                                            <div class="tabs-bar-actions">
                                                <?php if ($active_tab == 'current-stock'): ?>
                                                <div class="dropdown">
                                                    <button type="button" class="btn-icon" id="diamondTableSettingsBtn" title="Column settings"><i class="feather icon-settings"></i></button>
                                                    <div class="dropdown-menu dropdown-menu-right p-3 shadow-sm" id="diamondTableSettingsDropdown">
                                                        <div class="font-weight-bold mb-2" style="font-size: 11px; color: #64748b;">Show columns</div>
                                                        <input type="text" class="form-control form-control-sm mb-2" id="diamondTableSettingsSearch" placeholder="Search columns..." autocomplete="off">
                                                        <div id="diamondTableSettingsItems">
                                                            <div class="diamond-table-settings-item"><label class="form-check mb-0"><input type="checkbox" class="form-check-input" data-column="product_name" checked> <span>Product Name</span></label></div>
                                                            <div class="diamond-table-settings-item"><label class="form-check mb-0"><input type="checkbox" class="form-check-input" data-column="qty" checked> <span>Qty</span></label></div>
                                                            <div class="diamond-table-settings-item"><label class="form-check mb-0"><input type="checkbox" class="form-check-input" data-column="gross_weight" checked> <span>Gross Weight</span></label></div>
                                                            <div class="diamond-table-settings-item"><label class="form-check mb-0"><input type="checkbox" class="form-check-input" data-column="carat" checked> <span>Carat</span></label></div>
                                                            <div class="diamond-table-settings-item"><label class="form-check mb-0"><input type="checkbox" class="form-check-input" data-column="article" checked> <span>Article</span></label></div>
                                                            <div class="diamond-table-settings-item"><label class="form-check mb-0"><input type="checkbox" class="form-check-input" data-column="metal" checked> <span>Metal</span></label></div>
                                                            <div class="diamond-table-settings-item"><label class="form-check mb-0"><input type="checkbox" class="form-check-input" data-column="diamond_wt" checked> <span>Diamond Wt.</span></label></div>
                                                            <div class="diamond-table-settings-item"><label class="form-check mb-0"><input type="checkbox" class="form-check-input" data-column="diamond_ct" checked> <span>Diamond Ct</span></label></div>
                                                            <div class="diamond-table-settings-item"><label class="form-check mb-0"><input type="checkbox" class="form-check-input" data-column="stone_wt" checked> <span>Stone Wt.</span></label></div>
                                                            <div class="diamond-table-settings-item"><label class="form-check mb-0"><input type="checkbox" class="form-check-input" data-column="stone_ct" checked> <span>Stone Ct.</span></label></div>
                                                            <div class="diamond-table-settings-item"><label class="form-check mb-0"><input type="checkbox" class="form-check-input" data-column="net_wt" checked> <span>Net Wt.</span></label></div>
                                                            <div class="diamond-table-settings-item"><label class="form-check mb-0"><input type="checkbox" class="form-check-input" data-column="purchase_amount" checked> <span>Purchase Amount</span></label></div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                                <button type="button" class="btn-icon" title="Expand/Collapse"><i class="feather icon-maximize-2"></i></button>
                                                <div class="dropdown">
                                                    <button type="button" class="btn-icon" title="Export" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="feather icon-download"></i></button>
                                                    <div class="dropdown-menu dropdown-menu-right">
                                                        <a class="dropdown-item" href="#" id="dsaExportExcel">Export to Excel</a>
                                                        <a class="dropdown-item" href="#" id="dsaExportPdf">Export to PDF</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Filter Section -->
                                        <div class="filter-section">
                                            <div class="d-flex align-items-center">
                                                <label>Search:</label>
                                                <input type="text" class="form-control" placeholder="Search products..." 
                                                       value="<?= htmlspecialchars($search_raw, ENT_QUOTES, 'UTF-8') ?>" 
                                                       id="searchInput" style="width: 250px;">
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <label>Branch:</label>
                                                <?php if (!empty($dsa_sub_branch_login) && count($branches) === 1): ?>
                                                <select class="form-control" id="branchFilter" style="width: 180px;" disabled>
                                                    <option value="<?= (int) $branches[0]['id'] ?>" selected>
                                                        <?= htmlspecialchars($branches[0]['name']) ?>
                                                    </option>
                                                </select>
                                                <?php else: ?>
                                                <select class="form-control" id="branchFilter" style="width: 180px;">
                                                    <option value="0">All Branches</option>
                                                    <?php foreach($branches as $branch): ?>
                                                        <option value="<?= $branch['id'] ?>" <?= $branch_filter == $branch['id'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($branch['name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <?php endif; ?>
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <label>Metal:</label>
                                                <select class="form-control" id="metalFilter" style="width: 150px;">
                                                    <option value="0">All Metals</option>
                                                    <?php foreach($metals as $metal): ?>
                                                        <option value="<?= $metal['id'] ?>" <?= $metal_filter == $metal['id'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($metal['name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>

                                        <!-- Table Container -->
                                        <div class="table-container">
                                            <div class="table-wrapper">
                                                <?php if ($active_tab == 'current-stock'): ?>
                                                <table class="table stock-table" id="dsaCurrentStockTable">
                                                    <thead>
                                                        <tr>
                                                            <th style="width: 120px;" data-column="action">Action</th>
                                                            <th class="sortable" style="min-width: 150px;" data-column="product_name">Product Name</th>
                                                            <th class="sortable" style="min-width: 80px;" data-column="qty">Qty</th>
                                                            <th class="sortable" style="min-width: 120px;" data-column="gross_weight">Gross Weight</th>
                                                            <th class="sortable" style="min-width: 90px;" data-column="carat">Carat</th>
                                                            <th class="sortable" style="min-width: 100px;" data-column="article">Article</th>
                                                            <th class="sortable" style="min-width: 100px;" data-column="metal">Metal</th>
                                                            <th class="sortable" style="min-width: 110px;" data-column="diamond_wt">Diamond Wt.</th>
                                                            <th class="sortable" style="min-width: 100px;" data-column="diamond_ct">Diamond Ct</th>
                                                            <th class="sortable" style="min-width: 100px;" data-column="stone_wt">Stone Wt.</th>
                                                            <th class="sortable" style="min-width: 100px;" data-column="stone_ct">Stone Ct.</th>
                                                            <th class="sortable" style="min-width: 100px;" data-column="net_wt">Net Wt.</th>
                                                            <th class="sortable" style="min-width: 130px;" data-column="purchase_amount">Purchase Amount</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="dsaTableBody">
                                                        <tr><td colspan="13" class="text-center text-muted" style="padding: 40px;">Loading stock data…</td></tr>
                                                    </tbody>
                                                </table>
                                                <?php else: ?>
                                                <table class="table stock-table dsa-details-table" id="dsaDetailsTable">
                                                    <thead>
                                                        <tr>
                                                            <th rowspan="2" data-dsa-key="product" class="sortable dsa-text-cell" style="min-width: 140px;">Product</th>
                                                            <th rowspan="2" data-dsa-key="metal" class="sortable dsa-text-cell" style="min-width: 90px;">Metal</th>
                                                            <th rowspan="2" data-dsa-key="article" class="sortable dsa-text-cell" style="min-width: 100px;">Article</th>
                                                            <th rowspan="2" data-dsa-key="location" class="sortable dsa-text-cell" style="min-width: 100px;">Location</th>
                                                            <th colspan="4" data-dsa-key="gross" class="dsa-group-head">Gross Wt.</th>
                                                            <th colspan="4" data-dsa-key="pure" class="dsa-group-head">Diamond Wt.</th>
                                                            <th colspan="4" data-dsa-key="pcs" class="dsa-group-head">Pcs</th>
                                                        </tr>
                                                        <tr>
                                                            <th data-dsa-key="gross" class="dsa-subhead">Opening</th>
                                                            <th data-dsa-key="gross" class="dsa-subhead">Wt. In</th>
                                                            <th data-dsa-key="gross" class="dsa-subhead">Wt. Out</th>
                                                            <th data-dsa-key="gross" class="dsa-subhead">Closing</th>
                                                            <th data-dsa-key="pure" class="dsa-subhead">Opening</th>
                                                            <th data-dsa-key="pure" class="dsa-subhead">Wt. In</th>
                                                            <th data-dsa-key="pure" class="dsa-subhead">Wt. Out</th>
                                                            <th data-dsa-key="pure" class="dsa-subhead">Closing</th>
                                                            <th data-dsa-key="pcs" class="dsa-subhead">Opening</th>
                                                            <th data-dsa-key="pcs" class="dsa-subhead">Wt. In</th>
                                                            <th data-dsa-key="pcs" class="dsa-subhead">Wt. Out</th>
                                                            <th data-dsa-key="pcs" class="dsa-subhead">Closing</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="dsaTableBody">
                                                        <tr><td colspan="16" class="text-center text-muted" style="padding: 40px;">Loading stock data…</td></tr>
                                                    </tbody>
                                                    <tfoot>
                                                        <tr class="dsa-details-tfoot-row" id="dsaDetailsTfootRow">
                                                            <td colspan="4" class="dsa-text-cell dsa-total-merge" style="font-weight: 700;">Total</td>
                                                            <td data-dsa-key="gross" class="dsa-tfoot-val">—</td>
                                                            <td data-dsa-key="gross" class="dsa-tfoot-val">—</td>
                                                            <td data-dsa-key="gross" class="dsa-tfoot-val">—</td>
                                                            <td data-dsa-key="gross" class="dsa-tfoot-val">—</td>
                                                            <td data-dsa-key="pure" class="dsa-tfoot-val">—</td>
                                                            <td data-dsa-key="pure" class="dsa-tfoot-val">—</td>
                                                            <td data-dsa-key="pure" class="dsa-tfoot-val">—</td>
                                                            <td data-dsa-key="pure" class="dsa-tfoot-val">—</td>
                                                            <td data-dsa-key="pcs" class="dsa-tfoot-val">—</td>
                                                            <td data-dsa-key="pcs" class="dsa-tfoot-val">—</td>
                                                            <td data-dsa-key="pcs" class="dsa-tfoot-val">—</td>
                                                            <td data-dsa-key="pcs" class="dsa-tfoot-val">—</td>
                                                        </tr>
                                                    </tfoot>
                                                </table>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Table Footer -->
                                            <div class="table-footer">
                                                <div class="table-footer-info" id="dsaFooterInfo">Loading…</div>
                                                <?php if ($active_tab == 'current-stock'): ?>
                                                <div class="table-footer-totals" id="diamondStockFooterTotals">
                                                    <div class="total-item" data-total-column="qty">
                                                        <span class="total-label">Qty</span>
                                                        <span class="total-value" id="dsaTotalQty">—</span>
                                                    </div>
                                                    <div class="total-item" data-total-column="gross_weight">
                                                        <span class="total-label">Gross Weight</span>
                                                        <span class="total-value" id="dsaTotalGross">—</span>
                                                    </div>
                                                    <div class="total-item" data-total-column="carat">
                                                        <span class="total-label">Carat</span>
                                                        <span class="total-value" id="dsaTotalCarat">—</span>
                                                    </div>
                                                    <div class="total-item" data-total-column="diamond_wt">
                                                        <span class="total-label">Diamond Wt.</span>
                                                        <span class="total-value" id="dsaTotalDiamondWt">—</span>
                                                    </div>
                                                    <div class="total-item" data-total-column="diamond_ct">
                                                        <span class="total-label">Diamond Ct</span>
                                                        <span class="total-value" id="dsaTotalDiamondCt">—</span>
                                                    </div>
                                                    <div class="total-item" data-total-column="stone_wt">
                                                        <span class="total-label">Stone Wt.</span>
                                                        <span class="total-value" id="dsaTotalStoneWt">—</span>
                                                    </div>
                                                    <div class="total-item" data-total-column="stone_ct">
                                                        <span class="total-label">Stone Ct.</span>
                                                        <span class="total-value" id="dsaTotalStoneCt">—</span>
                                                    </div>
                                                    <div class="total-item" data-total-column="net_wt">
                                                        <span class="total-label">Net Wt.</span>
                                                        <span class="total-value" id="dsaTotalNet">—</span>
                                                    </div>
                                                    <div class="total-item" data-total-column="purchase_amount">
                                                        <span class="total-label">Purchase Amount</span>
                                                        <span class="total-value" id="dsaTotalPurchase">—</span>
                                                    </div>
                                                </div>
                                                <?php endif; ?>

                                                <div class="pagination-controls" id="dsaPaginationNav">
                                                    <select class="show-all-dropdown" id="perPageSelect" title="Page size">
                                                        <option value="20" <?= $per_page == 20 ? 'selected' : '' ?>>20</option>
                                                        <option value="10" <?= $per_page == 10 ? 'selected' : '' ?>>10</option>
                                                        <option value="25" <?= $per_page == 25 ? 'selected' : '' ?>>25</option>
                                                        <option value="50" <?= $per_page == 50 ? 'selected' : '' ?>>50</option>
                                                        <option value="100" <?= $per_page == 100 ? 'selected' : '' ?>>100</option>
                                                    </select>
                                                    <span class="dsa-page-btns" id="dsaPageBtns"></span>
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
            <!-- [ Layout content ] End -->
        </div>
        <!-- [ Layout container ] End -->
    </div>
</div>
<!-- [ Layout wrapper ] End -->

<?php include 'footer-script.php';?>

<script>
$(document).ready(function() {
    $('#searchInput').on('keypress', function(e) {
        if (e.which === 13) {
            applyFilters();
        }
    });

    $('#branchFilter, #metalFilter').on('change', function() {
        applyFilters();
    });

    $('#perPageSelect').on('change', function() {
        dsaState.perPage = parseInt($(this).val(), 10) || 20;
        dsaState.page = 1;
        dsaLoadTable(true);
    });

    var dsaState = {
        page: <?= (int) $page ?>,
        perPage: <?= (int) $per_page ?>,
        tab: <?= json_encode($active_tab) ?>,
        loading: false
    };

    function dsaBuildApiUrl(extra) {
        var params = new URLSearchParams(window.location.search);
        params.set('tab', dsaState.tab);
        params.set('page', String(dsaState.page));
        params.set('per_page', String(dsaState.perPage));
        if (extra) {
            Object.keys(extra).forEach(function (k) {
                params.set(k, String(extra[k]));
            });
        }
        return 'ajax/get-diamond-stone-analysis-data.php?' + params.toString();
    }

    function dsaPushUrl() {
        var url = new URL(window.location.href);
        url.searchParams.set('tab', dsaState.tab);
        if (dsaState.page > 1) {
            url.searchParams.set('page', String(dsaState.page));
        } else {
            url.searchParams.delete('page');
        }
        if (dsaState.perPage !== 20) {
            url.searchParams.set('per_page', String(dsaState.perPage));
        } else {
            url.searchParams.delete('per_page');
        }
        if (window.history && window.history.replaceState) {
            window.history.replaceState(null, '', url.toString());
        }
    }

    function dsaFmtQty(n, decimals) {
        var x = parseFloat(n);
        if (!isFinite(x)) x = 0;
        if (x < 0) {
            return '(' + Math.abs(x).toFixed(decimals) + ')';
        }
        return x.toFixed(decimals);
    }

    function dsaRenderPagination(pg) {
        var nav = document.getElementById('dsaPageBtns');
        if (!nav) return;
        var cur = pg.page || 1;
        var totalPages = Math.max(1, pg.total_pages || 1);
        nav.innerHTML = '';

        function addBtn(html, pageNum, disabled, active) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'page-btn' + (active ? ' active' : '');
            btn.innerHTML = html;
            if (disabled) {
                btn.disabled = true;
            } else {
                btn.setAttribute('data-page', String(pageNum));
            }
            nav.appendChild(btn);
        }

        addBtn('<i class="feather icon-chevrons-left"></i>', 1, cur <= 1, false);
        addBtn('<i class="feather icon-chevron-left"></i>', cur - 1, cur <= 1, false);
        var start = Math.max(1, cur - 2);
        var end = Math.min(totalPages, cur + 2);
        for (var i = start; i <= end; i++) {
            addBtn(String(i), i, false, i === cur);
        }
        addBtn('<i class="feather icon-chevron-right"></i>', cur + 1, cur >= totalPages, false);
        addBtn('<i class="feather icon-chevrons-right"></i>', totalPages, cur >= totalPages, false);
    }

    function dsaApplyTotals(totals, tfootHtml) {
        if (!totals) return;
        if (dsaState.tab === 'current-stock') {
            var el;
            el = document.getElementById('dsaTotalQty');
            if (el) el.textContent = dsaFmtQty(totals.total_qty || 0, 0);
            el = document.getElementById('dsaTotalGross');
            if (el) el.textContent = dsaFmtQty(totals.total_gross_weight || 0, 3);
            el = document.getElementById('dsaTotalCarat');
            if (el) el.textContent = dsaFmtQty(totals.total_carat || 0, 3);
            el = document.getElementById('dsaTotalDiamondWt');
            if (el) el.textContent = dsaFmtQty(totals.total_pure_weight || 0, 3);
            el = document.getElementById('dsaTotalDiamondCt');
            if (el) el.textContent = dsaFmtQty(totals.total_diamond_ct || 0, 3);
            el = document.getElementById('dsaTotalStoneWt');
            if (el) el.textContent = dsaFmtQty(totals.total_stone_weight || 0, 3);
            el = document.getElementById('dsaTotalStoneCt');
            if (el) el.textContent = dsaFmtQty(totals.total_stone_ct || 0, 3);
            el = document.getElementById('dsaTotalNet');
            if (el) el.textContent = dsaFmtQty(totals.total_net_weight || 0, 3);
            el = document.getElementById('dsaTotalPurchase');
            if (el) el.textContent = Number(totals.total_purchase_amount || 0).toFixed(2);
        } else if (tfootHtml) {
            var row = document.getElementById('dsaDetailsTfootRow');
            if (row) {
                var wrap = document.createElement('tr');
                wrap.innerHTML = '<td></td>' + tfootHtml;
                while (row.cells.length > 1) {
                    row.deleteCell(1);
                }
                for (var c = 1; c < wrap.cells.length; c++) {
                    row.appendChild(wrap.cells[c].cloneNode(true));
                }
            }
        }
    }

    function dsaLoadSummary() {
        fetch(dsaBuildApiUrl({ summary: 1 }), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.status === 'success') {
                    dsaApplyTotals(data.totals, data.tfoot_cells_html || '');
                }
            })
            .catch(function () {});
    }

    function dsaLoadTable(loadSummary) {
        if (dsaState.loading) return;
        dsaState.loading = true;
        var tbody = document.getElementById('dsaTableBody');
        var colspan = dsaState.tab === 'current-stock' ? 13 : 16;
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="' + colspan + '" class="text-center text-muted" style="padding:40px;">Loading stock data…</td></tr>';
        }
        var info = document.getElementById('dsaFooterInfo');
        if (info) info.textContent = 'Loading…';
        dsaPushUrl();

        fetch(dsaBuildApiUrl(), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                dsaState.loading = false;
                if (data.status !== 'success') {
                    if (tbody) {
                        tbody.innerHTML = '<tr><td colspan="' + colspan + '" class="text-center text-muted" style="padding:40px;">' + (data.message || 'Load failed') + '</td></tr>';
                    }
                    return;
                }
                if (tbody) tbody.innerHTML = data.tbody_html || '';
                var pg = data.pagination || {};
                if (info) {
                    if ((pg.total || 0) > 0) {
                        info.textContent = 'Showing ' + pg.range_from + ' to ' + pg.range_to + ' of ' + pg.total + ' entries';
                    } else {
                        info.textContent = 'No entries';
                    }
                }
                dsaRenderPagination(pg);
                if (loadSummary !== false) {
                    dsaLoadSummary();
                }
            })
            .catch(function () {
                dsaState.loading = false;
                if (tbody) {
                    tbody.innerHTML = '<tr><td colspan="' + colspan + '" class="text-center text-muted" style="padding:40px;">Could not load data</td></tr>';
                }
            });
    }

    document.getElementById('dsaPageBtns').addEventListener('click', function (e) {
        var btn = e.target.closest('.page-btn');
        if (!btn || btn.disabled) return;
        var p = parseInt(btn.getAttribute('data-page'), 10);
        if (!p || p === dsaState.page) return;
        dsaState.page = p;
        dsaLoadTable(true);
    });

    function applyFilters() {
        var url = new URL(window.location.href);
        var search = ($('#searchInput').val() || '').trim();
        var branch = $('#branchFilter').val();
        var metal = $('#metalFilter').val();

        if (search) {
            url.searchParams.set('search', search);
        } else {
            url.searchParams.delete('search');
        }

        if (branch && branch !== '0') {
            url.searchParams.set('branch', branch);
        } else {
            url.searchParams.delete('branch');
        }

        if (metal && metal !== '0') {
            url.searchParams.set('metal', metal);
        } else {
            url.searchParams.delete('metal');
        }

        url.searchParams.set('per_page', String(dsaState.perPage));
        url.searchParams.set('page', '1');
        window.location.href = url.toString();
    }

    var dsaExportQs = <?php echo json_encode($dsa_export_query, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    $('#dsaExportExcel').on('click', function (e) {
        e.preventDefault();
        window.location.href = 'ajax/export-diamond-stone-analysis-excel.php' + (dsaExportQs ? ('?' + dsaExportQs) : '');
    });
    $('#dsaExportPdf').on('click', function (e) {
        e.preventDefault();
        window.location.href = 'ajax/export-diamond-stone-analysis-pdf.php' + (dsaExportQs ? ('?' + dsaExportQs) : '');
    });

    $(document).on('click', '.view-history-btn', function() {
        var stockId = $(this).data('stock-id');
        var productId = $(this).data('product-id');
        var characteristicId = $(this).data('characteristic-id');
        var branchId = $(this).data('branch-id');
        var url = 'diamond-stock-history.php?stock_id=' + stockId;
        if (productId) url += '&product_id=' + productId;
        if (characteristicId) url += '&characteristic_id=' + characteristicId;
        if (branchId) url += '&branch_id=' + branchId;
        window.location.href = url;
    });

    (function() {
        var STORAGE_KEY = 'auragold_diamond_stone_analysis_columns';
        var settingsBtn = document.getElementById('diamondTableSettingsBtn');
        var settingsDropdown = document.getElementById('diamondTableSettingsDropdown');
        if (!settingsBtn || !settingsDropdown) return;
        var checkboxes = settingsDropdown.querySelectorAll('input[type="checkbox"][data-column]');

        function applyColumnVisibility() {
            checkboxes.forEach(function(cb) {
                var col = cb.getAttribute('data-column');
                var show = cb.checked;
                document.querySelectorAll('#dsaCurrentStockTable th[data-column="' + col + '"]').forEach(function(el) {
                    el.classList.toggle('hidden', !show);
                });
                document.querySelectorAll('#dsaCurrentStockTable td[data-column="' + col + '"]').forEach(function(el) {
                    el.classList.toggle('hidden', !show);
                });
                var foot = document.querySelector('#diamondStockFooterTotals .total-item[data-total-column="' + col + '"]');
                if (foot) foot.classList.toggle('hidden', !show);
            });
        }

        function loadState() {
            try {
                var raw = localStorage.getItem(STORAGE_KEY);
                if (!raw) return;
                var state = JSON.parse(raw);
                checkboxes.forEach(function(cb) {
                    var col = cb.getAttribute('data-column');
                    if (col && typeof state[col] === 'boolean') cb.checked = state[col];
                });
            } catch (e) {}
        }

        function saveState() {
            var state = {};
            checkboxes.forEach(function(cb) {
                var col = cb.getAttribute('data-column');
                if (col) state[col] = cb.checked;
            });
            try { localStorage.setItem(STORAGE_KEY, JSON.stringify(state)); } catch (e) {}
        }

        loadState();
        applyColumnVisibility();

        settingsBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            settingsDropdown.classList.toggle('show');
        });
        document.addEventListener('click', function(e) {
            if (!settingsBtn.contains(e.target) && !settingsDropdown.contains(e.target)) {
                settingsDropdown.classList.remove('show');
            }
        });
        checkboxes.forEach(function(cb) {
            cb.addEventListener('change', function() {
                applyColumnVisibility();
                saveState();
            });
        });
    })();

    dsaLoadTable(true);
});
</script>

</body>
</html>

