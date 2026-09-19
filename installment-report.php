<?php
session_start();
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Installment Report - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
<?php include 'header-script.php'; ?>
</head>
<style>
    body {
        margin: 0;
        padding: 0;
        overflow-x: hidden;
        overflow-y: hidden;
        height: 100vh;
        background: linear-gradient(135deg, #f5f7fa 0%, #eeeeee 100%);
        font-family: Roboto, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    }
    html { height: 100vh; overflow: hidden; }
    .layout-wrapper { height: 100vh; overflow: hidden; }
    .layout-content {
        height: calc(100vh - 60px);
        overflow-y: auto;
        margin: 0 !important;
        padding: 0 !important;
    }
    .layout-container { margin-left: 260px; }
    @media (max-width: 991.98px) {
        .layout-container { margin-left: 0; }
    }
    .if-card {
        border-radius: 12px;
        border: 1px solid #e6e8f0;
        background: #fff;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        margin-bottom: 12px;
    }
    .if-card .card-body { padding: 10px 12px; }
    .if-subtabs .nav-link {
        background: #f3f4f9;
        border-radius: 8px;
        margin-right: 8px;
        color: #5c5c7a !important;
        font-weight: 600;
        padding: 8px 16px;
        border: none;
    }
    .if-subtabs .nav-link.active {
        background: #7b6cff;
        color: #fff !important;
    }
    .if-report-split { min-height: 360px; }
    .if-report-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        margin-bottom: 10px;
    }
    .if-report-toolbar-actions {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
    }
    .if-report-icon-btn {
        padding: 4px 10px;
        line-height: 1.2;
        border-radius: 6px;
        color: #5c5c7a !important;
        border-color: #e6e8f0 !important;
    }
    .if-report-icon-btn:hover {
        background: #f3f4f9 !important;
        color: #7b6cff !important;
        border-color: #d4d2e8 !important;
    }
    .if-report-filter-btn { position: relative; }
    .if-report-filter-btn .if-report-filter-badge {
        position: absolute;
        top: -6px;
        right: -6px;
        font-size: 0.6rem;
        padding: 2px 5px;
    }
    .if-report-party-card .table-responsive { max-height: 420px; overflow-y: auto; }
    .if-report-party-table thead th {
        font-size: 11px;
        font-weight: 600;
        color: #374151;
        background: #f3f4f6 !important;
        border-color: #e6e8f0 !important;
        padding: 6px 8px;
        white-space: nowrap;
    }
    .if-report-party-table td {
        font-size: 12px;
        padding: 6px 8px;
        vertical-align: middle;
        border-color: #e6e8f0;
    }
    .if-report-party-search td { padding: 6px 8px; background: #fafafa; }
    .if-report-party-row:hover td { background: #f8f9ff; }
    .if-report-party-row--active td {
        background: #ede9fe !important;
        font-weight: 600;
        color: #5b4cdb;
    }
    .if-report-main-scroll {
        overflow-x: auto;
        max-height: 520px;
        overflow-y: auto;
    }
    .if-report-main-table thead th {
        font-size: 11px;
        font-weight: 600;
        color: #374151;
        background: #f3f4f6 !important;
        border-color: #e6e8f0 !important;
        padding: 6px 8px;
        white-space: nowrap;
    }
    .if-report-main-table tbody td {
        font-size: 12px;
        padding: 6px 8px;
        border-color: #e6e8f0;
        color: #334155;
        vertical-align: middle;
    }
    .if-report-main-table tbody tr[data-fund-id] { cursor: pointer; }
    .if-report-main-table tbody tr[data-fund-id]:hover { background: #f8f9ff; }
    .if-report-main-table tbody tr[data-fund-id].if-report-row--selected {
        background: #eef2ff !important;
        outline: 1px solid #c7d2fe;
        outline-offset: -1px;
    }
    .if-report-main-table td.if-report-link-cell {
        color: #4b49ac;
        font-weight: 600;
    }
    .if-report-main-table td.if-report-link-cell:hover { text-decoration: underline; }
    .if-report-detail-card {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
        overflow: hidden;
    }
    .if-report-detail-inner { display: flex; flex-wrap: wrap; align-items: stretch; }
    .if-report-detail-profile {
        padding: 18px 22px;
        border-right: 1px solid #e6e8f0;
        align-items: center;
        gap: 16px;
        flex: 0 0 auto;
        min-width: 220px;
        max-width: 100%;
    }
    .if-report-detail-avatar {
        width: 72px;
        height: 72px;
        border-radius: 50%;
        background: linear-gradient(145deg, #3b82f6 0%, #1d4ed8 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        color: #fff;
        font-size: 2rem;
    }
    .if-report-detail-name { font-size: 1.05rem; font-weight: 700; color: #4b49ac; margin-bottom: 4px; }
    .if-report-detail-sub { font-size: 0.8rem; color: #64748b; margin-bottom: 2px; }
    .if-report-detail-phone { font-size: 0.8rem; color: #334155; }
    .if-report-detail-phone i { font-size: 0.85rem; vertical-align: middle; margin-right: 6px; color: #22c55e; }
    .if-report-detail-grid-wrap { flex: 1 1 280px; min-width: 0; }
    .if-report-detail-grid { padding: 14px 18px 10px; }
    .if-report-detail-grid .if-report-dg-item { margin-bottom: 10px; }
    .if-report-detail-grid .if-report-dg-label {
        font-size: 0.68rem;
        font-weight: 600;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.02em;
        margin-bottom: 2px;
    }
    .if-report-detail-grid .if-report-dg-value {
        font-size: 0.82rem;
        font-weight: 700;
        color: #4b49ac;
        word-break: break-word;
    }
    .if-report-detail-grid .if-report-dg-value.if-report-dg-empty { color: #94a3b8; font-weight: 400; }
    .if-report-detail-card-footer {
        border-top: 1px solid #f1f5f9;
        padding-top: 10px !important;
        margin-top: 0;
    }
    .if-report-party-footer, .if-report-main-footer {
        font-size: 0.72rem;
        color: #64748b;
    }
    .if-ir-page-toolbar {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 12px;
    }
    @media (max-width: 991.98px) {
        .if-report-detail-profile {
            border-right: none;
            border-bottom: 1px solid #e6e8f0;
            width: 100%;
        }
    }
</style>
<body>
<div class="layout-wrapper layout-2">
    <div class="layout-inner">
        <div id="layout-sidenav" class="layout-sidenav sidenav sidenav-vertical bg-white logo-dark">
            <div class="app-brand demo">
                <span class="app-brand-logo demo">
                    <img src="assets/img/logo.png" alt="Brand Logo" class="img-fluid">
                </span>
                <a href="index.php" class="app-brand-text demo sidenav-text font-weight-normal ml-2">AuraGold</a>
                <a href="javascript:" class="layout-sidenav-toggle sidenav-link text-large ml-auto">
                    <i class="ion ion-md-menu align-middle"></i>
                </a>
            </div>
            <div class="sidenav-divider mt-0"></div>
            <ul class="sidenav-inner py-1">
                <li class="sidenav-item">
                    <a href="dashboard.php" class="sidenav-link">
                        <i class="sidenav-icon feather icon-home"></i>
                        <div>Dashboard</div>
                    </a>
                </li>
            </ul>
        </div>

        <div class="layout-container">
            <nav class="layout-navbar navbar navbar-expand-lg align-items-lg-center bg-dark container-p-x" id="layout-navbar">
                <a href="index.php" class="navbar-brand app-brand demo d-lg-none py-0 mr-4">
                    <span class="app-brand-logo demo"><img src="assets/img/logo-dark.png" alt="" class="img-fluid"></span>
                    <span class="app-brand-text demo font-weight-normal ml-2">AuraGold</span>
                </a>
                <div class="layout-sidenav-toggle navbar-nav d-lg-none align-items-lg-center mr-auto">
                    <a class="nav-item nav-link px-0 mr-lg-4" href="javascript:"><i class="ion ion-md-menu text-large align-middle"></i></a>
                </div>
            </nav>

            <div class="layout-content">
                <div class="container-fluid flex-grow-1" style="padding-top: 0; padding-bottom: 0;">
                    <?php include 'sidebar.php'; ?>

                    <div class="if-ir-page-toolbar px-1">
                        <ul class="nav nav-tabs if-subtabs mb-0" id="ifIrPageTabs">
                            <li class="nav-item">
                                <a class="nav-link" href="investment-fund.php">Installment Entry</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link active" href="installment-report.php">Installment Report</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="investment-fund.php?tab=layaways">Layaways Report</a>
                            </li>
                        </ul>
                        <div class="if-report-toolbar mb-0">
                            <div class="if-report-toolbar-actions">
                                <button type="button" class="btn btn-sm btn-outline-secondary if-report-icon-btn if-report-filter-btn" id="ifReportBtnFilter" title="Filter">
                                    <i class="feather icon-filter"></i>
                                    <span class="badge badge-danger if-report-filter-badge d-none" id="ifReportFilterBadge">0</span>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary if-report-icon-btn" id="ifReportBtnRefresh" title="Refresh">
                                    <i class="feather icon-refresh-cw"></i>
                                </button>
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Export</button>
                                    <div class="dropdown-menu dropdown-menu-right">
                                        <a class="dropdown-item" href="#" id="ifReportExportCsv">Export CSV</a>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-secondary if-report-icon-btn" id="ifReportBtnColumns" title="Column settings">
                                    <i class="feather icon-settings"></i>
                                </button>
                                <input type="search" class="form-control form-control-sm" id="ifReportSearchMain" placeholder="Search…" autocomplete="off" style="min-width: 160px; max-width: 220px;">
                            </div>
                        </div>
                    </div>

                    <div class="row if-report-split mx-0">
                        <div class="col-lg-3 pr-lg-2 mb-3 mb-lg-0">
                            <div class="if-card if-report-party-card mb-0 h-100">
                                <div class="card-body p-0 d-flex flex-column" style="min-height: 320px;">
                                    <div class="table-responsive flex-grow-1">
                                        <table class="table table-sm table-bordered mb-0 if-report-party-table">
                                            <thead>
                                                <tr>
                                                    <th>Party</th>
                                                    <th class="text-right">No. Of Schemes</th>
                                                </tr>
                                                <tr class="if-report-party-search">
                                                    <td><input type="text" class="form-control form-control-sm" id="ifReportPartyFilter" placeholder="Search" autocomplete="off"></td>
                                                    <td></td>
                                                </tr>
                                            </thead>
                                            <tbody id="ifReportPartyBody"></tbody>
                                        </table>
                                    </div>
                                    <div class="border-top px-2 py-2 if-report-party-footer d-flex flex-wrap justify-content-between align-items-center" style="gap: 8px;">
                                        <span id="ifReportPartyFooter">Showing 0 of 0 entries</span>
                                        <div class="d-flex align-items-center flex-wrap" style="gap: 6px;">
                                            <select class="form-control form-control-sm" id="ifIrPartyPageSize" style="width: auto; max-width: 130px; font-size: 0.72rem;" title="Items per page (UI only)">
                                                <option value="25" selected>Show 25 Items</option>
                                                <option value="50">Show 50 Items</option>
                                                <option value="100">Show 100 Items</option>
                                            </select>
                                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" disabled title="Pagination — use full list">&laquo;</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" disabled>&lsaquo;</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" disabled>&rsaquo;</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" disabled>&raquo;</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-9 pl-lg-2">
                            <div id="ifReportDetailCard" class="if-report-detail-card d-none mb-2">
                                <div class="if-report-detail-inner">
                                    <div class="d-flex if-report-detail-profile">
                                        <div class="if-report-detail-avatar"><i class="feather icon-user"></i></div>
                                        <div>
                                            <div class="if-report-detail-name" id="ifReportDvCustomerName">—</div>
                                            <div class="if-report-detail-sub" id="ifReportDvLocation">—</div>
                                            <div class="if-report-detail-phone"><i class="feather icon-phone"></i><span id="ifReportDvPhone">—</span></div>
                                        </div>
                                    </div>
                                    <div class="if-report-detail-grid-wrap">
                                        <div class="row if-report-detail-grid mx-0">
                                            <div class="col-md-4 col-6 if-report-dg-item">
                                                <div class="if-report-dg-label">Scheme Name</div>
                                                <div class="if-report-dg-value" id="ifReportDvSchemeName">—</div>
                                            </div>
                                            <div class="col-md-4 col-6 if-report-dg-item">
                                                <div class="if-report-dg-label">Joining Dt.</div>
                                                <div class="if-report-dg-value" id="ifReportDvJoiningDt">—</div>
                                            </div>
                                            <div class="col-md-4 col-6 if-report-dg-item">
                                                <div class="if-report-dg-label">Maturity Dt.</div>
                                                <div class="if-report-dg-value" id="ifReportDvMaturityDt">—</div>
                                            </div>
                                            <div class="col-md-4 col-6 if-report-dg-item">
                                                <div class="if-report-dg-label">Amount</div>
                                                <div class="if-report-dg-value" id="ifReportDvAmount">—</div>
                                            </div>
                                            <div class="col-md-4 col-6 if-report-dg-item">
                                                <div class="if-report-dg-label">Installment Type</div>
                                                <div class="if-report-dg-value" id="ifReportDvInstType">—</div>
                                            </div>
                                            <div class="col-md-4 col-6 if-report-dg-item">
                                                <div class="if-report-dg-label">Duration</div>
                                                <div class="if-report-dg-value" id="ifReportDvDuration">—</div>
                                            </div>
                                            <div class="col-md-4 col-6 if-report-dg-item">
                                                <div class="if-report-dg-label">Redemption On</div>
                                                <div class="if-report-dg-value" id="ifReportDvRedemption">—</div>
                                            </div>
                                            <div class="col-md-4 col-6 if-report-dg-item">
                                                <div class="if-report-dg-label">Advanced Payment</div>
                                                <div class="if-report-dg-value if-report-dg-empty" id="ifReportDvAdvancedPayment">—</div>
                                            </div>
                                            <div class="col-md-4 col-6 if-report-dg-item">
                                                <div class="if-report-dg-label">Nominee Name</div>
                                                <div class="if-report-dg-value if-report-dg-empty" id="ifReportDvNominee">—</div>
                                            </div>
                                            <div class="col-md-4 col-6 if-report-dg-item">
                                                <div class="if-report-dg-label">Email</div>
                                                <div class="if-report-dg-value if-report-dg-empty" id="ifReportDvEmail">—</div>
                                            </div>
                                            <div class="col-md-4 col-6 if-report-dg-item">
                                                <div class="if-report-dg-label">Contact No</div>
                                                <div class="if-report-dg-value if-report-dg-empty" id="ifReportDvContactNo">—</div>
                                            </div>
                                            <div class="col-md-4 col-6 if-report-dg-item">
                                                <div class="if-report-dg-label">Relation Type</div>
                                                <div class="if-report-dg-value if-report-dg-empty" id="ifReportDvRelationType">—</div>
                                            </div>
                                            <div class="col-md-4 col-6 if-report-dg-item">
                                                <div class="if-report-dg-label">National Id</div>
                                                <div class="if-report-dg-value if-report-dg-empty" id="ifReportDvNationalId">—</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="if-report-detail-card-footer px-3 pb-3">
                                    <button type="button" class="btn btn-sm btn-primary" id="ifReportBtnOpenEntry">Open installment entry</button>
                                </div>
                            </div>
                            <div class="if-card mb-0">
                                <div class="card-body p-2">
                                    <div class="table-responsive if-report-main-scroll">
                                        <table class="table table-sm table-bordered mb-0 if-report-main-table">
                                            <thead>
                                                <tr>
                                                    <th>Customer</th>
                                                    <th>Mobile</th>
                                                    <th>Email</th>
                                                    <th>Scheme Name</th>
                                                    <th>Sale Person</th>
                                                    <th>Inst. Type</th>
                                                    <th title="Redemption">Redem…</th>
                                                    <th title="Joining date">Join…</th>
                                                    <th title="Maturity date">Matur…</th>
                                                    <th>Fund No</th>
                                                    <th title="Paid installments">Paid</th>
                                                    <th class="text-right">Inst. Amt</th>
                                                </tr>
                                            </thead>
                                            <tbody id="ifReportMainBody">
                                                <tr><td colspan="12" class="text-center text-muted py-4">No Rows To Show</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="d-flex flex-wrap justify-content-between align-items-center px-1 pt-2 if-report-main-footer" style="gap: 8px;">
                                        <span id="ifReportMainFooter">Showing 0 to 0 of 0 entries</span>
                                        <div class="d-flex align-items-center flex-wrap" style="gap: 6px;">
                                            <select class="form-control form-control-sm" id="ifIrMainPageSize" style="width: auto; max-width: 130px; font-size: 0.72rem;" title="UI placeholder">
                                                <option value="9999" selected>Show All Items</option>
                                                <option value="25">25</option>
                                                <option value="50">50</option>
                                            </select>
                                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" disabled>&laquo;</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" disabled>&lsaquo;</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" disabled>&rsaquo;</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" disabled>&raquo;</button>
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
</div>

<?php include 'footer-script.php'; ?>
<script src="assets/js/installment-report-page.js"></script>
</body>
</html>
