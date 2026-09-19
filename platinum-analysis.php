<?php 
session_start();
require_once 'config.php';

/** mysqli_fetch_assoc column names can vary in casing; use for Current Stock weight columns */
function stock_analysis_row_col(array $row, string $name): ?float {
    foreach ($row as $k => $v) {
        if (strcasecmp((string) $k, $name) === 0) {
            return ($v === null || $v === '') ? null : (float) $v;
        }
    }
    return null;
}

// Get active tab
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'current-stock';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$offset = ($page - 1) * $per_page;

require_once __DIR__ . '/includes/platinum_analysis_roll_up_include.php';

if ($active_tab == 'current-stock') {
    $stock_query = "
        SELECT 
            stock_grp.*,
            stock_grp.available_qty AS display_qty,
            (stock_grp.inward_pure_sum - stock_grp.outward_pure_sum) AS display_pure_weight,
            (stock_grp.inward_gross_sum - stock_grp.outward_weight_sum) AS display_gross_weight
        FROM (
            $stock_inner_sql
        ) stock_grp
        ORDER BY stock_grp.product_name ASC, stock_grp.product_id DESC
    ";
    
    $total_stock_record = getRecord("
        SELECT COUNT(*) as total FROM (
            SELECT 1
            $stock_inner_from
        ) AS grp
    ");
    $total_stock = $total_stock_record ? (int)$total_stock_record['total'] : 0;
    $total_pages = $total_stock > 0 ? ceil($total_stock / $per_page) : 1;
    
    $stock_data = getList($stock_query . " LIMIT $per_page OFFSET $offset");
  
    // Footer totals: sum of remaining gross/pure per row (same formulas as main query)
    $totals_query = "
        SELECT 
            SUM(display_qty) as total_qty,
            SUM(production_qty) as total_production_qty,
            SUM(sale_invoice_qty) as total_sale_invoice_qty,
            SUM(display_qty) as total_available_qty,
            SUM(display_gross_weight) as total_gross_weight,
            SUM(display_pure_weight) as total_pure_weight,
            SUM(display_gross_weight) as total_net_weight,
            SUM(0) as total_stone_weight,
            SUM(value) as total_purchase_amount
        FROM (
            SELECT 
                stock_grp.*,
                stock_grp.available_qty AS display_qty,
                (stock_grp.inward_pure_sum - stock_grp.outward_pure_sum) AS display_pure_weight,
                (stock_grp.inward_gross_sum - stock_grp.outward_weight_sum) AS display_gross_weight
            FROM (
                $stock_inner_sql
            ) stock_grp
        ) as display_totals
    ";
   
    $totals = getRecord($totals_query);
} 

else {
    // Stock Details tab – same data rules as Current Stock
    $stock_query = "
        SELECT 
            stock_grp.*,
            stock_grp.available_qty AS display_qty,
            (CASE 
                WHEN stock_grp.outward_weight_sum >= 0.0001 THEN stock_grp.stock_net_weight
                WHEN stock_grp.production_weight > 0.0001 AND stock_grp.outward_weight_sum < 0.0001
                     AND COALESCE(stock_grp.purchase_metal_weight, 0) > 0.0001
                     AND ABS(stock_grp.stock_net_weight + stock_grp.production_weight - stock_grp.purchase_metal_weight) < 0.05
                THEN stock_grp.stock_net_weight
                WHEN stock_grp.production_weight > 0.0001 AND stock_grp.outward_weight_sum < 0.0001
                THEN stock_grp.stock_net_weight - stock_grp.production_weight
                ELSE stock_grp.stock_net_weight
            END) AS display_gross_weight
        FROM (
            $stock_inner_sql
        ) stock_grp
        ORDER BY stock_grp.product_name ASC, stock_grp.product_id DESC
    ";
    
    $total_stock_record = getRecord("
        SELECT COUNT(*) as total FROM (
            SELECT 1
            $stock_inner_from
        ) AS grp
    ");
    $total_stock = $total_stock_record ? (int)$total_stock_record['total'] : 0;
    $total_pages = $total_stock > 0 ? ceil($total_stock / $per_page) : 1;
    
    $stock_data = getList($stock_query . " LIMIT $per_page OFFSET $offset");
    
    $totals_query = "
        SELECT 
            SUM(display_qty) as total_qty,
            SUM(production_qty) as total_production_qty,
            SUM(sale_invoice_qty) as total_sale_invoice_qty,
            SUM(display_qty) as total_available_qty,
            SUM(display_gross_weight) as total_gross_weight,
            SUM(display_gross_weight * (CASE WHEN opening_purity <= 1 THEN opening_purity ELSE opening_purity / 100 END)) as total_pure_weight,
            SUM(display_gross_weight) as total_net_weight,
            SUM(0) as total_stone_weight,
            SUM(value) as total_purchase_amount
        FROM (
            SELECT 
                stock_grp.*,
                stock_grp.available_qty AS display_qty,
                (CASE 
                    WHEN stock_grp.outward_weight_sum >= 0.0001 THEN stock_grp.stock_net_weight
                    WHEN stock_grp.production_weight > 0.0001 AND stock_grp.outward_weight_sum < 0.0001
                         AND COALESCE(stock_grp.purchase_metal_weight, 0) > 0.0001
                         AND ABS(stock_grp.stock_net_weight + stock_grp.production_weight - stock_grp.purchase_metal_weight) < 0.05
                    THEN stock_grp.stock_net_weight
                    WHEN stock_grp.production_weight > 0.0001 AND stock_grp.outward_weight_sum < 0.0001
                    THEN stock_grp.stock_net_weight - stock_grp.production_weight
                    ELSE stock_grp.stock_net_weight
                END) AS display_gross_weight
            FROM (
                $stock_inner_sql
            ) stock_grp
        ) as display_totals
    ";
    $totals = getRecord($totals_query);
}

// Get branches and metals for filters (metal list limited to this page scope)
$branches = getListMaster("SELECT id, name FROM tbl_branches WHERE status = 1 ORDER BY name ASC");
$metals = $scope_metals;

$pta_export_query = isset($_SERVER['QUERY_STRING']) ? (string) $_SERVER['QUERY_STRING'] : '';

?>
<!DOCTYPE html>

<html lang="en" class="default-style">

<head>
    <title>Platinum Analysis - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?> Software</title>

    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <meta name="description" content="Platinum Analysis - Current Stock" />
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

/* Page Header */
.page-header-bar {
    background: #11294b;
    color: #fff;
    padding: 12px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 600;
    font-size: 12px;
}

.page-header-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

.page-header-actions .btn-icon {
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

.page-header-actions .btn-icon:hover {
    background: rgba(255,255,255,0.3);
}

.page-header-actions .dropdown-menu {
    z-index: 2000;
    min-width: 11rem;
}

/* Tabs */
.tabs-container {
    background: #fff;
    border-bottom: 2px solid #e2e8f0;
    padding: 0 20px;
}

.tabs-list {
    display: flex;
    gap: 0;
    margin: 0;
    padding: 0;
    list-style: none;
}

.tabs-list li {
    margin: 0;
}

.tab-link {
    display: block;
    padding: 12px 20px;
    color: #64748b;
    text-decoration: none;
    border-bottom: 3px solid transparent;
    font-weight: 500;
    transition: all 0.2s;
    cursor: pointer;
}

.tab-link:hover {
    color: #11294b;
    background: #f8fafc;
}

.tab-link.active {
    color: #11294b;
    border-bottom-color: #11294b;
    font-weight: 600;
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
                                        
                                        <!-- Page Header -->
                                        <div class="page-header-bar">
                                            <div>Platinum Analysis / Current Stock</div>
                                            <div class="page-header-actions">
                                                <button class="btn-icon" title="Filter"><i class="feather icon-filter"></i></button>
                                                <button class="btn-icon" title="Expand/Collapse"><i class="feather icon-maximize-2"></i></button>
                                                <div class="dropdown">
                                                    <button class="btn-icon" type="button" title="Export" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><i class="feather icon-download"></i></button>
                                                    <div class="dropdown-menu dropdown-menu-right">
                                                        <a class="dropdown-item" href="#" id="ptaExportExcel">Export to Excel</a>
                                                        <a class="dropdown-item" href="#" id="ptaExportPdf">Export to PDF</a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Tabs -->
                                        <div class="tabs-container">
                                            <ul class="tabs-list">
                                                <li>
                                                    <a href="?tab=current-stock<?= $search_term ? '&search='.urlencode($search_term) : '' ?><?= $branch_filter ? '&branch='.$branch_filter : '' ?><?= $metal_filter ? '&metal='.$metal_filter : '' ?>" 
                                                       class="tab-link <?= $active_tab == 'current-stock' ? 'active' : '' ?>">
                                                        Current Stock
                                                    </a>
                                                </li>
                                                <li>
                                                    <a href="?tab=stock-details<?= $search_term ? '&search='.urlencode($search_term) : '' ?><?= $branch_filter ? '&branch='.$branch_filter : '' ?><?= $metal_filter ? '&metal='.$metal_filter : '' ?>" 
                                                       class="tab-link <?= $active_tab == 'stock-details' ? 'active' : '' ?>">
                                                        Stock Details
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>

                                        <!-- Filter Section -->
                                        <div class="filter-section">
                                            <div class="d-flex align-items-center">
                                                <label>Search:</label>
                                                <input type="text" class="form-control" placeholder="Search products..." 
                                                       value="<?= htmlspecialchars($search_term) ?>" 
                                                       id="searchInput" style="width: 250px;">
                                            </div>
                                            <div class="d-flex align-items-center">
                                                <label>Branch:</label>
                                                <select class="form-control" id="branchFilter" style="width: 180px;">
                                                    <option value="0">All Branches</option>
                                                    <?php foreach($branches as $branch): ?>
                                                        <option value="<?= $branch['id'] ?>" <?= $branch_filter == $branch['id'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($branch['name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
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
                                                <table class="table stock-table">
                                                    <thead>
                                                        <tr>
                                                            <th style="width: 120px;">Action</th>
                                                            <th class="sortable" style="min-width: 150px;">
                                                                Product Name
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" style="min-width: 100px;">
                                                                Metal
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" style="min-width: 80px;">
                                                                Qty
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" style="min-width: 120px;">
                                                                Gross Weight
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" style="min-width: 120px;">
                                                                Pure/D.Wt.
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" style="min-width: 100px;">
                                                                Article
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" style="min-width: 120px;">
                                                                Branch Name
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" style="min-width: 100px;">
                                                                Net Wt
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" style="min-width: 100px;">
                                                                Stone Wt
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                            <th class="sortable" style="min-width: 130px;">
                                                                Purchase Amount
                                                                <span class="sort-arrows">
                                                                    <i class="feather icon-chevron-up"></i>
                                                                    <i class="feather icon-chevron-down"></i>
                                                                </span>
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php 
                                                        if (!empty($stock_data)) {
                                                            foreach($stock_data as $stock) {
                                                                // Current Stock: gross/pure from SQL display_gross_weight & display_pure_weight (mysqli keys may differ in case).
                                                                $qty_for_display = (float)(stock_analysis_row_col($stock, 'available_qty') ?? 0);
                                                                $purchase_metal_weight = (float)($stock['purchase_metal_weight'] ?? 0);
                                                                $opening_purity = (float)($stock['opening_purity'] ?? 0);
                                                                if ($active_tab == 'current-stock') {
                                                                    $gross_weight = (float)(stock_analysis_row_col($stock, 'display_gross_weight') ?? 0);
                                                                    $pure_weight = (float)(stock_analysis_row_col($stock, 'display_pure_weight') ?? 0);
                                                                    if ($gross_weight == 0 && $purchase_metal_weight > 0 && abs($pure_weight) < 0.0001) {
                                                                        $gross_weight = $purchase_metal_weight;
                                                                    }
                                                                } else {
                                                                    $gross_weight = (float)($stock['display_gross_weight'] ?? $stock['stock_net_weight'] ?? 0);
                                                                    if ($gross_weight == 0 && $purchase_metal_weight > 0) {
                                                                        $gross_weight = $purchase_metal_weight;
                                                                    }
                                                                    $pure_weight = $gross_weight * ($opening_purity <= 1 ? $opening_purity : $opening_purity / 100);
                                                                }
                                                                $net_weight = $gross_weight;
                                                                $stone_weight = 0.000;
                                                                $purchase_amount = (float)($stock['value'] ?? 0);
                                                                
                                                                $qty_class = $qty_for_display < 0 ? 'negative' : '';
                                                                $gross_class = $gross_weight < 0 ? 'negative' : '';
                                                                $pure_class = $pure_weight < 0 ? 'negative' : '';
                                                                $net_class = $net_weight < 0 ? 'negative' : '';
                                                                
                                                                $qty_display = $qty_for_display < 0 ? '('.abs($qty_for_display).')' : number_format($qty_for_display, 0);
                                                                
                                                                $gross_display = $gross_weight < 0 ? '('.number_format(abs($gross_weight), 3).')' : number_format($gross_weight, 3);
                                                                $pure_display = $pure_weight < 0 ? '('.number_format(abs($pure_weight), 3).')' : number_format($pure_weight, 3);
                                                                $net_display = $net_weight < 0 ? '('.number_format(abs($net_weight), 3).')' : number_format($net_weight, 3);
                                                                
                                                                echo '<tr>';
                                                                echo '<td><button class="view-history-btn" data-stock-id="0" data-product-id="'.$stock['product_id'].'" data-characteristic-id="'.($stock['product_characteristic_id'] ?: 0).'">View History</button></td>';
                                                                echo '<td>'.htmlspecialchars($stock['product_name'] ?: 'N/A').'</td>';
                                                                echo '<td>'.htmlspecialchars($stock['metal_name'] ?: 'N/A').'</td>';
                                                                echo '<td class="'.$qty_class.'">'.$qty_display.'</td>';
                                                                echo '<td class="'.$gross_class.'">'.$gross_display.'</td>';
                                                                echo '<td class="'.$pure_class.'">'.$pure_display.'</td>';
                                                                echo '<td>'.htmlspecialchars($stock['article'] ?: '').'</td>';
                                                                echo '<td>'.htmlspecialchars($stock['branch_name'] ?: 'N/A').'</td>';
                                                                echo '<td class="'.$net_class.'">'.$net_display.'</td>';
                                                                echo '<td>'.number_format($stone_weight, 3).'</td>';
                                                                echo '<td>'.number_format($purchase_amount, 2).'</td>';
                                                                echo '</tr>';
                                                            }
                                                        } else {
                                                            echo '<tr><td colspan="11" class="text-center text-muted" style="padding: 40px;">No stock data found</td></tr>';
                                                        }
                                                        ?>
                                                    </tbody>
                                                </table>
                                            </div>

                                            <!-- Table Footer -->
                                            <div class="table-footer">
                                                <div class="table-footer-info">
                                                    Showing <?= $offset + 1 ?> to <?= min($offset + $per_page, $total_stock) ?> of <?= $total_stock ?> entries
                                                </div>
                                                
                                                <div class="table-footer-totals">
                                                    <div class="total-item">
                                                        <span class="total-label">Qty</span>
                                                        <span class="total-value"><?= number_format($totals['total_qty'] ?: 0, 0) ?></span>
                                                    </div>
                                                    <div class="total-item">
                                                        <span class="total-label">Gross Weight</span>
                                                        <span class="total-value"><?= number_format($totals['total_gross_weight'] ?: 0, 3) ?></span>
                                                    </div>
                                                    <div class="total-item">
                                                        <span class="total-label">Pure/D.Wt.</span>
                                                        <span class="total-value"><?= number_format($totals['total_pure_weight'] ?: 0, 3) ?></span>
                                                    </div>
                                                    <div class="total-item">
                                                        <span class="total-label">Net Wt</span>
                                                        <span class="total-value"><?= number_format($totals['total_net_weight'] ?: 0, 3) ?></span>
                                                    </div>
                                                    <div class="total-item">
                                                        <span class="total-label">Stone Wt</span>
                                                        <span class="total-value"><?= number_format($totals['total_stone_weight'] ?: 0, 3) ?></span>
                                                    </div>
                                                    <div class="total-item">
                                                        <span class="total-label">Purchase Amount</span>
                                                        <span class="total-value"><?= number_format($totals['total_purchase_amount'] ?: 0, 2) ?></span>
                                                    </div>
                                                </div>

                                                <div class="pagination-controls">
                                                    <select class="show-all-dropdown" id="perPageSelect">
                                                        <option value="10" <?= $per_page == 10 ? 'selected' : '' ?>>10</option>
                                                        <option value="25" <?= $per_page == 25 ? 'selected' : '' ?>>25</option>
                                                        <option value="50" <?= $per_page == 50 ? 'selected' : '' ?>>50</option>
                                                        <option value="100" <?= $per_page == 100 ? 'selected' : '' ?>>100</option>
                                                    </select>
                                                    <button type="button" class="page-btn" data-page="1" <?= $page == 1 ? 'disabled' : '' ?>>
                                                        <i class="feather icon-chevrons-left"></i>
                                                    </button>
                                                    <button type="button" class="page-btn" data-page="<?= max(1, $page - 1) ?>" <?= $page == 1 ? 'disabled' : '' ?>>
                                                        <i class="feather icon-chevron-left"></i>
                                                    </button>
                                                    <?php
                                                    $start_page = max(1, $page - 2);
                                                    $end_page = min($total_pages, $page + 2);
                                                    for ($i = $start_page; $i <= $end_page; $i++) {
                                                        $active = ($i == $page) ? 'active' : '';
                                                        echo '<button class="page-btn '.$active.'" data-page="'.$i.'">'.$i.'</button>';
                                                    }
                                                    ?>
                                                    <button type="button" class="page-btn" data-page="<?= min($total_pages, $page + 1) ?>" <?= $page >= $total_pages ? 'disabled' : '' ?>>
                                                        <i class="feather icon-chevron-right"></i>
                                                    </button>
                                                    <button type="button" class="page-btn" data-page="<?= $total_pages ?>" <?= $page >= $total_pages ? 'disabled' : '' ?>>
                                                        <i class="feather icon-chevrons-right"></i>
                                                    </button>
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
    // Search functionality
    $('#searchInput').on('keypress', function(e) {
        if (e.which === 13) {
            applyFilters();
        }
    });

    // Filter changes
    $('#branchFilter, #metalFilter').on('change', function() {
        applyFilters();
    });

    // Per page change
    $('#perPageSelect').on('change', function() {
        applyFilters();
    });

    // Pagination
    $('.page-btn').on('click', function() {
        const page = $(this).data('page');
        if (page && !$(this).is(':disabled')) {
            const url = new URL(window.location.href);
            url.searchParams.set('page', page);
            window.location.href = url.toString();
        }
    });

    function applyFilters() {
        const url = new URL(window.location.href);
        const search = $('#searchInput').val();
        const branch = $('#branchFilter').val();
        const metal = $('#metalFilter').val();
        const perPage = $('#perPageSelect').val();
        
        if (search) {
            url.searchParams.set('search', search);
        } else {
            url.searchParams.delete('search');
        }
        
        if (branch && branch != '0') {
            url.searchParams.set('branch', branch);
        } else {
            url.searchParams.delete('branch');
        }
        
        if (metal && metal != '0') {
            url.searchParams.set('metal', metal);
        } else {
            url.searchParams.delete('metal');
        }
        
        url.searchParams.set('per_page', perPage);
        url.searchParams.set('page', 1);
        
        window.location.href = url.toString();
    }

    var ptaExportQs = <?php echo json_encode($pta_export_query, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    $('#ptaExportExcel').on('click', function (e) {
        e.preventDefault();
        window.location.href = 'ajax/export-platinum-analysis-excel.php' + (ptaExportQs ? ('?' + ptaExportQs) : '');
    });
    $('#ptaExportPdf').on('click', function (e) {
        e.preventDefault();
        window.location.href = 'ajax/export-platinum-analysis-pdf.php' + (ptaExportQs ? ('?' + ptaExportQs) : '');
    });

    // View History button
    $('.view-history-btn').on('click', function() {
        const stockId = $(this).data('stock-id');
        const productId = $(this).data('product-id');
        const characteristicId = $(this).data('characteristic-id');
        // Navigate to stock history page
        let url = 'stock-history.php?stock_id=' + stockId;
        if (productId) url += '&product_id=' + productId;
        if (characteristicId) url += '&characteristic_id=' + characteristicId;
        window.location.href = url;
    });
});
</script>

</body>
</html>

