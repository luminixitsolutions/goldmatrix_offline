<?php
session_start();
require_once __DIR__ . '/config.php';

$rfid_branches = [];
$rfid_metals = [];
$rfid_products = [];
$rfid_articles = [];
$rfid_categories = [];
$rfid_voucher_types = [];
$rfid_carat = [];
if (function_exists('getList')) {
    $rfid_branches = getList('SELECT id, name FROM tbl_branches WHERE status = 1 ORDER BY name ASC');
    if (!is_array($rfid_branches)) {
        $rfid_branches = [];
    }
    $rfid_metals = getList('SELECT id, display_name AS name FROM tbl_metal WHERE status = 1 ORDER BY display_name ASC');
    if (!is_array($rfid_metals)) {
        $rfid_metals = [];
    }
    $rfid_products = getList('SELECT id, name, article FROM tbl_products WHERE status = 1 ORDER BY name ASC LIMIT 5000');
    if (!is_array($rfid_products)) {
        $rfid_products = [];
    }
    $rfid_articles = getList("SELECT DISTINCT TRIM(article) AS article FROM tbl_products WHERE status = 1 AND article IS NOT NULL AND TRIM(article) != '' ORDER BY article ASC LIMIT 2000");
    if (!is_array($rfid_articles)) {
        $rfid_articles = [];
    }
    $rfid_categories = getList('SELECT id, name FROM tbl_categories WHERE status = 1 ORDER BY name ASC');
    if (!is_array($rfid_categories)) {
        $rfid_categories = [];
    }
    $rfid_voucher_types = getList('SELECT id, name FROM tbl_voucher_types WHERE status = 1 ORDER BY name ASC');
    if (!is_array($rfid_voucher_types)) {
        $rfid_voucher_types = [];
    }
    $rfid_carat = getList('SELECT id, name FROM tbl_carat WHERE status = 1 ORDER BY name ASC');
    if (!is_array($rfid_carat)) {
        $rfid_carat = [];
    }
}

$rfid_avail_col_map = [
    'isScanned' => 'isScanned',
    'branch' => 'Branch',
    'carat' => 'Carat',
    'action' => 'Action',
    'metal' => 'Metal',
    'product_code' => 'Product Code',
    'article' => 'Article',
    'rfid_code' => 'RFID Code',
    'barcode' => 'Barcode',
    'qty' => 'Qty',
    'location' => 'Location',
    'gross_wt' => 'Gross Wt',
    'purity_wt' => 'Purity Wt',
    'net_wt' => 'Net Wt',
    'final_wt' => 'Final Wt',
    'voucher_type' => 'Voucher Type',
    'invoice_no' => 'Invoice No',
];
$rfid_scanned_col_map = [
    'active' => 'active',
    'scan_date' => 'Scan Date',
    'scan_time' => 'Scan Time',
    'branch' => 'Branch',
    'product_code' => 'Product Code',
    'article' => 'Article',
    'location' => 'Location',
    'rfid_code' => 'RFID Code',
    'barcode' => 'Barcode',
    'qty' => 'Qty',
    'metal' => 'Metal',
    'gross_wt' => 'Gross Wt',
    'purity_wt' => 'Purity Wt',
    'net_wt' => 'Net Wt.',
    'final_wt' => 'Final Wt.',
    'voucher_type' => 'Voucher Type',
];
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>RFID / Barcode Scan - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
<?php include __DIR__ . '/header-script.php'; ?>
</head>
<style>
    :root {
        --rfid-navy: #11294b;
        --rfid-navy-mid: #1a3c63;
        --rfid-navy-soft: #244f7a;
        --rfid-gold: #c9a227;
        --rfid-gold-bright: #d4af37;
        --rfid-gold-dark: #8b6914;
        --rfid-gold-pale: #faf6eb;
        --rfid-gold-wash: #f0e6cc;
        --rfid-cream: #fffcf7;
    }
    body {
        margin: 0;
        padding: 0;
        overflow-x: hidden;
        overflow-y: hidden;
        height: 100vh;
        background: linear-gradient(145deg, var(--rfid-cream) 0%, #eef2f7 55%, var(--rfid-gold-pale) 100%);
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

    .rfid-scan-wrap { padding: 12px 14px 8px; }
    .rfid-toolbar {
        display: flex;
        flex-wrap: nowrap;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 12px;
        min-height: 40px;
    }
    .rfid-toolbar-left {
        display: flex;
        align-items: center;
        gap: 10px;
        flex: 1 1 auto;
        min-width: 0;
    }
    .rfid-toolbar-left label {
        margin: 0;
        flex-shrink: 0;
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--rfid-navy);
        white-space: nowrap;
    }
    .rfid-barcode-input-wrap {
        position: relative;
        flex: 1 1 auto;
        min-width: 180px;
        max-width: 400px;
        width: 100%;
    }
    .rfid-barcode-input-wrap .form-control {
        height: 36px;
        border-radius: 8px;
        border: 1px solid #d8dce6;
        padding-right: 38px;
        font-size: 0.88rem;
    }
    .rfid-barcode-input-wrap .form-control:focus {
        border-color: var(--rfid-navy-mid);
        box-shadow: 0 0 0 2px rgba(17, 41, 75, 0.12);
    }
    .rfid-barcode-input-wrap .rfid-barcode-icon {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--rfid-gold-dark);
        font-size: 1.05rem;
        pointer-events: none;
    }
    .rfid-toolbar-right {
        display: flex;
        align-items: center;
        flex-wrap: nowrap;
        gap: 8px 10px;
        flex-shrink: 0;
    }
    .rfid-summary-stats {
        display: flex;
        align-items: center;
        flex-wrap: nowrap;
        gap: 6px 14px;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--rfid-navy);
        white-space: nowrap;
    }
    .rfid-summary-stats strong {
        font-weight: 700;
        color: var(--rfid-gold-dark);
    }
    .rfid-session-hint {
        font-size: 0.72rem;
        color: #64748b;
        margin: -2px 0 10px 0;
        line-height: 1.4;
        max-width: 720px;
    }
    @media (max-width: 1100px) {
        .rfid-toolbar {
            flex-wrap: wrap;
        }
        .rfid-toolbar-right {
            flex-wrap: wrap;
            justify-content: flex-end;
            width: 100%;
        }
        .rfid-summary-stats {
            flex: 1 1 auto;
            min-width: 0;
            white-space: normal;
        }
    }
    .rfid-icon-btn {
        width: 36px;
        height: 36px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        border: 1px solid #e2e6ef;
        background: #fff;
        color: #5c5c7a !important;
        transition: background 0.15s, color 0.15s, border-color 0.15s;
    }
    .rfid-icon-btn:hover {
        background: var(--rfid-gold-pale);
        color: var(--rfid-navy) !important;
        border-color: var(--rfid-gold);
    }
    .rfid-filter-btn { position: relative; }
    .rfid-filter-btn .badge {
        position: absolute;
        top: -5px;
        right: -5px;
        font-size: 0.6rem;
        padding: 2px 5px;
    }
    .btn-rfid-reset {
        border: 1px solid var(--rfid-navy-mid);
        color: var(--rfid-navy) !important;
        background: #fff;
        font-weight: 600;
        font-size: 0.78rem;
        padding: 6px 12px;
        border-radius: 8px;
        white-space: nowrap;
    }
    .btn-rfid-reset:hover {
        background: var(--rfid-gold-pale);
        border-color: var(--rfid-gold);
        color: var(--rfid-navy) !important;
    }
    .btn-rfid-export {
        border: 1px solid #c5b896;
        background: linear-gradient(180deg, #fffef9 0%, var(--rfid-gold-pale) 100%);
        color: var(--rfid-navy) !important;
        font-weight: 600;
        font-size: 0.78rem;
        padding: 6px 12px;
        border-radius: 8px;
        white-space: nowrap;
    }
    .btn-rfid-export:hover {
        background: var(--rfid-gold-wash);
        border-color: var(--rfid-gold);
        color: var(--rfid-navy) !important;
    }

    .rfid-grid-card {
        border: 1px solid #c5b896;
        border-radius: 10px;
        background: #fff;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
        overflow: hidden;
        flex: 1 1 auto;
        width: 100%;
        display: flex;
        flex-direction: column;
        height: calc(100vh - 248px);
        max-height: calc(100vh - 248px);
        min-height: 420px;
    }
    .rfid-grid-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 12px;
        border-bottom: 2px solid var(--rfid-gold);
        background: #11294b;
        position: relative;
        flex-shrink: 0;
    }
    .rfid-grid-card-title {
        font-size: 0.88rem;
        font-weight: 700;
        color: var(--rfid-gold-bright);
        margin: 0;
    }
    .rfid-grid-card-header .btn-link {
        color: rgba(255, 255, 255, 0.75);
        padding: 2px 6px;
        line-height: 1;
    }
    .rfid-grid-card-header .btn-link:hover { color: var(--rfid-gold-bright); }

    .rfid-data-table {
        margin: 0;
        font-size: 0.8rem;
    }
    .rfid-data-table thead th,
    .rfid-data-table.table thead th,
    .rfid-data-table.table-bordered thead th {
        background-color: #11294b !important;
        background-image: none !important;
        color: var(--rfid-gold-pale);
        font-weight: 600;
        font-size: 0.72rem;
        text-transform: none;
        letter-spacing: 0.01em;
        border-color: rgba(201, 162, 39, 0.35) !important;
        padding: 8px 10px;
        white-space: nowrap;
        vertical-align: middle;
        position: sticky;
        top: 0;
        z-index: 3;
        box-shadow: 0 1px 0 rgba(201, 162, 39, 0.35);
    }
    .rfid-data-table thead th .th-sort {
        display: inline-flex;
        flex-direction: column;
        margin-left: 4px;
        vertical-align: middle;
        opacity: 0.65;
        line-height: 0.5;
        font-size: 0.55rem;
        color: var(--rfid-gold);
    }
    .rfid-data-table tbody td {
        padding: 8px 10px;
        border-color: #e8dfc8;
        color: #1e293b;
        vertical-align: middle;
    }
    .rfid-data-table tbody tr:hover td {
        background: var(--rfid-gold-pale);
    }
    .rfid-data-table .btn-outline-primary {
        color: var(--rfid-navy-mid);
        border-color: var(--rfid-navy-soft);
        font-size: 0.72rem;
    }
    .rfid-data-table .btn-outline-primary:disabled {
        opacity: 0.45;
    }
    .rfid-table-scroll {
        flex: 1 1 auto;
        min-height: 0;
        overflow-x: auto;
        overflow-y: auto;
        -webkit-overflow-scrolling: touch;
    }
    .rfid-table-scroll::-webkit-scrollbar {
        width: 10px;
        height: 10px;
    }
    .rfid-table-scroll::-webkit-scrollbar-thumb {
        background: #94a3b8;
        border-radius: 8px;
        border: 2px solid #f8fafc;
    }
    .rfid-table-scroll::-webkit-scrollbar-track {
        background: #f1f5f9;
    }
    .rfid-table-empty {
        text-align: center;
        color: var(--rfid-navy-soft);
        opacity: 0.75;
        padding: 48px 16px !important;
        font-size: 0.88rem;
        background: var(--rfid-cream);
    }
    .rfid-table-footer {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        padding: 8px 10px;
        border-top: 1px solid var(--rfid-gold-wash);
        background: var(--rfid-gold-pale);
        font-size: 0.72rem;
        color: var(--rfid-navy-mid);
        flex-shrink: 0;
    }
    .rfid-table-footer .pagination-mini .btn {
        padding: 2px 8px;
        font-size: 0.75rem;
        line-height: 1.3;
        border-color: #b8a06a;
        color: var(--rfid-navy-mid);
    }
    .rfid-table-footer .pagination-mini .btn:not(:disabled):hover {
        background: var(--rfid-gold-pale);
        border-color: var(--rfid-gold);
        color: var(--rfid-navy);
    }

    .rfid-row-split {
        margin-left: -8px;
        margin-right: -8px;
        align-items: stretch;
    }
    .rfid-row-split > [class*="col-"] {
        padding-left: 8px;
        padding-right: 8px;
        display: flex;
        flex-direction: column;
    }
    @media (max-width: 991.98px) {
        .rfid-grid-card {
            height: 420px;
            max-height: 420px;
        }
    }

    .rfid-advance-filter-dialog {
        max-width: 920px;
        width: calc(100% - 1rem);
        margin: 0.35rem auto;
    }
    .rfid-filter-modal-content {
        border-radius: 10px;
        border: 1px solid #c5b896;
        border-top: 4px solid var(--rfid-gold);
        overflow: hidden;
        box-shadow: 0 12px 40px rgba(17, 41, 75, 0.2);
    }
    .rfid-filter-modal-header {
        display: flex;
        align-items: center;
        justify-content: center;
        position: relative;
        padding: 10px 40px 10px 16px;
        border-bottom: 1px solid var(--rfid-gold-wash);
        background: linear-gradient(180deg, var(--rfid-gold-pale) 0%, #fff 100%);
    }
    .rfid-filter-modal-header .modal-title {
        margin: 0;
        font-size: 1.02rem;
        font-weight: 700;
        color: var(--rfid-navy);
        text-align: center;
        flex: 1;
    }
    .rfid-filter-modal-header .close {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        padding: 4px 8px;
        margin: 0;
        opacity: 0.55;
        font-size: 1.5rem;
        font-weight: 400;
        color: #6b7280;
    }
    .rfid-filter-modal-header .close:hover { opacity: 1; color: #374151; }
    .rfid-filter-modal-body {
        padding: 10px 20px 6px;
        max-height: min(78vh, 640px);
        overflow-y: auto;
    }
    @media (min-height: 720px) {
        .rfid-filter-modal-body {
            max-height: none;
            overflow-y: visible;
        }
    }
    .rfid-filter-form .rfid-filter-row {
        display: flex;
        align-items: center;
        margin-bottom: 5px;
        gap: 0;
    }
    .rfid-filter-form .rfid-filter-label {
        flex: 0 0 30%;
        max-width: 220px;
        padding: 2px 12px 2px 0;
        margin: 0;
        font-size: 0.8rem;
        font-weight: 600;
        color: var(--rfid-navy-mid);
        text-align: right;
        line-height: 1.25;
    }
    .rfid-filter-form .rfid-filter-field {
        flex: 1 1 70%;
        min-width: 0;
    }
    .rfid-filter-form .rfid-filter-field .form-control {
        border-radius: 6px;
        border: 1px solid #d1d5db;
        font-size: 0.82rem;
        height: 32px;
        padding: 4px 10px;
        line-height: 1.35;
    }
    .rfid-filter-form .rfid-filter-field select.form-control {
        padding-right: 28px;
        background-position: right 8px center;
    }
    .rfid-filter-form .rfid-filter-field .form-control:focus {
        border-color: var(--rfid-navy-mid);
        box-shadow: 0 0 0 2px rgba(17, 41, 75, 0.1);
    }
    .rfid-filter-form .rfid-filter-split {
        margin-left: -6px;
        margin-right: -6px;
        margin-bottom: 0;
    }
    .rfid-filter-form .rfid-filter-split > [class*="col-"] {
        padding-left: 6px;
        padding-right: 6px;
    }
    .rfid-filter-form .rfid-filter-split .rfid-filter-label {
        flex: 0 0 36%;
        max-width: 120px;
        font-size: 0.78rem;
        padding-right: 8px;
    }
    .rfid-filter-form .rfid-filter-split .rfid-filter-field {
        flex: 1 1 64%;
    }
    @media (max-width: 575.98px) {
        .rfid-filter-form .rfid-filter-row {
            flex-direction: column;
            align-items: stretch;
            margin-bottom: 8px;
        }
        .rfid-filter-form .rfid-filter-label {
            flex: none;
            max-width: none;
            text-align: left;
            padding: 0 0 4px 0;
        }
        .rfid-filter-form .rfid-filter-field { flex: none; width: 100%; }
        .rfid-filter-form .rfid-filter-split .rfid-filter-label { max-width: none; }
    }
    .rfid-filter-modal-footer {
        border-top: 1px solid #f3f4f6;
        justify-content: center;
        gap: 12px;
        padding: 10px 16px 14px;
        flex-wrap: wrap;
    }
    .btn-rfid-apply-filter {
        border: 2px solid var(--rfid-navy);
        color: var(--rfid-navy) !important;
        background: #fff;
        font-weight: 600;
        font-size: 0.88rem;
        padding: 8px 22px;
        border-radius: 8px;
    }
    .btn-rfid-apply-filter:hover {
        background: var(--rfid-navy);
        color: var(--rfid-gold-bright) !important;
    }
    .btn-rfid-clear-filter {
        border: 2px solid var(--rfid-gold);
        color: var(--rfid-gold-dark) !important;
        background: #fff;
        font-weight: 600;
        font-size: 0.88rem;
        padding: 8px 22px;
        border-radius: 8px;
    }
    .btn-rfid-clear-filter:hover {
        background: var(--rfid-gold-pale);
        color: var(--rfid-navy) !important;
    }
    .modal-backdrop.show { opacity: 0.45; }

    .rfid-col-picker-wrap { position: relative; flex-shrink: 0; }
    .rfid-col-picker-toggle { color: rgba(255, 255, 255, 0.8) !important; }
    .rfid-col-picker-toggle:hover, .rfid-col-picker-toggle[aria-expanded="true"] { color: var(--rfid-gold-bright) !important; }
    .rfid-col-picker-panel {
        position: absolute;
        top: 100%;
        right: 0;
        margin-top: 6px;
        width: min(320px, 92vw);
        max-height: 420px;
        display: flex;
        flex-direction: column;
        background: var(--rfid-cream);
        border-radius: 10px;
        box-shadow: 0 8px 28px rgba(17, 41, 75, 0.18), 0 0 1px rgba(201, 162, 39, 0.4);
        z-index: 1080;
        border: 1px solid #c5b896;
        overflow: hidden;
    }
    .rfid-col-picker-panel.d-none { display: none !important; }
    .rfid-col-picker-head {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px 8px;
        padding: 8px 10px;
        background: linear-gradient(90deg, var(--rfid-navy) 0%, var(--rfid-navy-mid) 100%);
        border-bottom: 1px solid var(--rfid-gold);
    }
    .rfid-col-picker-head .rfid-cp-title {
        flex: 1 1 auto;
        text-align: center;
        font-weight: 700;
        font-size: 0.82rem;
        color: var(--rfid-gold-bright);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        min-width: 0;
    }
    .rfid-col-picker-head .rfid-cp-icon {
        width: 32px;
        height: 32px;
        border: none;
        background: transparent;
        color: rgba(255, 255, 255, 0.85);
        border-radius: 6px;
        cursor: pointer;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .rfid-col-picker-head .rfid-cp-icon:hover { color: var(--rfid-navy); background: var(--rfid-gold-bright); }
    .rfid-col-picker-search {
        padding: 8px 10px 6px;
        background: var(--rfid-gold-pale);
    }
    .rfid-col-picker-search input {
        width: 100%;
        border: 1px solid #d8dce6;
        border-radius: 8px;
        padding: 6px 10px;
        font-size: 0.82rem;
    }
    .rfid-col-picker-search input:focus {
        outline: none;
        border-color: var(--rfid-navy-mid);
        box-shadow: 0 0 0 2px rgba(17, 41, 75, 0.08);
    }
    .rfid-col-picker-list {
        overflow-y: auto;
        max-height: 260px;
        padding: 4px 8px 10px;
        scrollbar-width: thin;
        scrollbar-color: var(--rfid-gold) var(--rfid-gold-pale);
    }
    .rfid-col-picker-list::-webkit-scrollbar { width: 8px; }
    .rfid-col-picker-list::-webkit-scrollbar-track { background: var(--rfid-gold-pale); border-radius: 4px; }
    .rfid-col-picker-list::-webkit-scrollbar-thumb { background: var(--rfid-gold); border-radius: 4px; }
    .rfid-col-picker-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6px 4px;
        font-size: 0.82rem;
        color: #374151;
    }
    .rfid-col-picker-item.d-none { display: none !important; }
    .rfid-col-picker-item input[type="checkbox"] {
        width: 16px;
        height: 16px;
        accent-color: var(--rfid-gold-dark);
        cursor: pointer;
        flex-shrink: 0;
    }
    .rfid-col-picker-item label {
        margin: 0;
        cursor: pointer;
        flex: 1;
        line-height: 1.25;
        font-weight: 500;
    }
    .rfid-data-table th[data-rfid-col].rfid-col-hidden,
    .rfid-data-table td[data-rfid-col].rfid-col-hidden { display: none !important; }
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
                    <?php include __DIR__ . '/sidebar.php'; ?>

                    <div class="rfid-scan-wrap">
                        <div class="rfid-toolbar">
                            <div class="rfid-toolbar-left">
                                <label for="rfidBarcodeInput">Barcode</label>
                                <div class="rfid-barcode-input-wrap">
                                    <input type="text" class="form-control" id="rfidBarcodeInput" name="barcode" placeholder="Scan or enter barcode / RFID" autocomplete="off">
                                    <span class="rfid-barcode-icon" aria-hidden="true"><i class="fas fa-barcode"></i></span>
                                </div>
                            </div>
                            <div class="rfid-toolbar-right">
                                <div class="rfid-summary-stats" id="rfidSummaryLine" aria-live="polite">
                                    <span>Unknown tag : <strong id="rfidSummaryUnknown">0</strong></span>
                                    <span>Total Wt : <strong id="rfidSummaryWt">0</strong></span>
                                    <span>Qty : <strong id="rfidSummaryQty">0</strong></span>
                                </div>
                                <button type="button" class="rfid-icon-btn rfid-filter-btn" id="rfidBtnFilter" title="Advance filter" data-toggle="modal" data-target="#rfidAdvanceFilterModal">
                                    <i class="feather icon-filter"></i>
                                    <span class="badge badge-danger d-none" id="rfidFilterBadge">0</span>
                                </button>
                                <button type="button" class="rfid-icon-btn" id="rfidBtnRefresh" title="Refresh">
                                    <i class="feather icon-refresh-cw"></i>
                                </button>
                                <div class="dropdown d-inline-block">
                                    <button type="button" class="btn btn-rfid-export dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        Export
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-right shadow-sm">
                                        <a class="dropdown-item" href="#" id="rfidExportExcel">Export to Excel</a>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-rfid-reset" id="rfidBtnReset">Reset RFID</button>
                            </div>
                        </div>
                        <p class="rfid-session-hint" id="rfidSessionHint">Pending items are listed under <strong>Available Stock</strong>. After you scan, they move to <strong>Scanned Stock</strong> and stay saved in this browser until you click <strong>Reset RFID</strong> or change advance filters.</p>

                        <div class="row rfid-row-split align-items-stretch">
                            <div class="col-lg-6 mb-3 mb-lg-0 d-flex flex-column">
                                <div class="rfid-grid-card">
                                    <div class="rfid-grid-card-header">
                                        <h2 class="rfid-grid-card-title" id="rfidAvailCardTitle">Available Stock (Total Wt : 0, Qty : 0)</h2>
                                        <div class="rfid-col-picker-wrap">
                                            <button type="button" class="btn btn-link btn-sm p-0 rfid-col-picker-toggle" data-rfid-picker="available" title="Columns" aria-expanded="false" aria-controls="rfidColPickerAvailable" id="rfidColPickerBtnAvailable"><i class="feather icon-settings"></i></button>
                                            <div class="rfid-col-picker-panel d-none" id="rfidColPickerAvailable" data-rfid-picker-panel="available" aria-hidden="true">
                                                <div class="rfid-col-picker-head">
                                                    <span class="rfid-cp-title"><i class="feather icon-settings"></i> Columns</span>
                                                    <button type="button" class="rfid-cp-icon rfid-cp-reset-cols" title="Show all columns" data-rfid-picker-reset="available"><i class="feather icon-refresh-cw"></i></button>
                                                    <button type="button" class="rfid-cp-icon rfid-cp-close" title="Close" data-rfid-picker-close><i class="feather icon-x"></i></button>
                                                </div>
                                                <div class="rfid-col-picker-search">
                                                    <input type="search" class="rfid-col-picker-filter" placeholder="Search" data-rfid-picker-filter="available" autocomplete="off" aria-label="Search columns">
                                                </div>
                                                <div class="rfid-col-picker-list" data-rfid-picker-list="available">
                                                    <?php foreach ($rfid_avail_col_map as $rfid_ck => $rfid_clab) {
                                                        $id = 'rfid_col_avail_' . preg_replace('/[^a-z0-9_]/i', '_', $rfid_ck);
                                                        echo '<div class="rfid-col-picker-item" data-rfid-col-label="' . htmlspecialchars(strtolower((string) $rfid_clab), ENT_QUOTES, 'UTF-8') . '">';
                                                        echo '<input type="checkbox" id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '" class="rfid-col-chk" data-rfid-table="available" data-rfid-col-key="' . htmlspecialchars($rfid_ck, ENT_QUOTES, 'UTF-8') . '" checked>';
                                                        echo '<label for="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars((string) $rfid_clab, ENT_QUOTES, 'UTF-8') . '</label>';
                                                        echo '</div>';
                                                    } ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="rfid-table-scroll table-responsive">
                                        <table class="table table-sm table-bordered mb-0 rfid-data-table" id="rfidTableAvailable">
                                            <thead>
                                                <tr>
                                                    <?php foreach ($rfid_avail_col_map as $rfid_ck => $rfid_clab) {
                                                        echo '<th data-rfid-col="' . htmlspecialchars($rfid_ck, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars((string) $rfid_clab, ENT_QUOTES, 'UTF-8') . ' <span class="th-sort"><i class="feather icon-chevron-up"></i><i class="feather icon-chevron-down"></i></span></th>';
                                                    } ?>
                                                </tr>
                                            </thead>
                                            <tbody id="rfidAvailableBody">
                                                <tr><td colspan="<?php echo count($rfid_avail_col_map); ?>" class="rfid-table-empty">No Rows To Show</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="rfid-table-footer">
                                        <span class="text-truncate" style="max-width: 45%;">Scroll for more rows / columns</span>
                                        <div class="d-flex align-items-center pagination-mini" style="gap: 4px;">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="First">&laquo;</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Previous">&lsaquo;</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Next">&rsaquo;</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Last">&raquo;</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6 d-flex flex-column">
                                <div class="rfid-grid-card">
                                    <div class="rfid-grid-card-header">
                                        <h2 class="rfid-grid-card-title" id="rfidScanCardTitle">Scanned Stock (Total Wt : 0, Qty : 0)</h2>
                                        <div class="rfid-col-picker-wrap">
                                            <button type="button" class="btn btn-link btn-sm p-0 rfid-col-picker-toggle" data-rfid-picker="scanned" title="Columns" aria-expanded="false" aria-controls="rfidColPickerScanned" id="rfidColPickerBtnScanned"><i class="feather icon-settings"></i></button>
                                            <div class="rfid-col-picker-panel d-none" id="rfidColPickerScanned" data-rfid-picker-panel="scanned" aria-hidden="true">
                                                <div class="rfid-col-picker-head">
                                                    <span class="rfid-cp-title"><i class="feather icon-settings"></i> Columns</span>
                                                    <button type="button" class="rfid-cp-icon rfid-cp-reset-cols" title="Show all columns" data-rfid-picker-reset="scanned"><i class="feather icon-refresh-cw"></i></button>
                                                    <button type="button" class="rfid-cp-icon rfid-cp-close" title="Close" data-rfid-picker-close><i class="feather icon-x"></i></button>
                                                </div>
                                                <div class="rfid-col-picker-search">
                                                    <input type="search" class="rfid-col-picker-filter" placeholder="Search" data-rfid-picker-filter="scanned" autocomplete="off" aria-label="Search columns">
                                                </div>
                                                <div class="rfid-col-picker-list" data-rfid-picker-list="scanned">
                                                    <?php foreach ($rfid_scanned_col_map as $rfid_ck => $rfid_clab) {
                                                        $id = 'rfid_col_scan_' . preg_replace('/[^a-z0-9_]/i', '_', $rfid_ck);
                                                        echo '<div class="rfid-col-picker-item" data-rfid-col-label="' . htmlspecialchars(strtolower((string) $rfid_clab), ENT_QUOTES, 'UTF-8') . '">';
                                                        echo '<input type="checkbox" id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '" class="rfid-col-chk" data-rfid-table="scanned" data-rfid-col-key="' . htmlspecialchars($rfid_ck, ENT_QUOTES, 'UTF-8') . '" checked>';
                                                        echo '<label for="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars((string) $rfid_clab, ENT_QUOTES, 'UTF-8') . '</label>';
                                                        echo '</div>';
                                                    } ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="rfid-table-scroll table-responsive">
                                        <table class="table table-sm table-bordered mb-0 rfid-data-table" id="rfidTableScanned">
                                            <thead>
                                                <tr>
                                                    <?php foreach ($rfid_scanned_col_map as $rfid_ck => $rfid_clab) {
                                                        echo '<th data-rfid-col="' . htmlspecialchars($rfid_ck, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars((string) $rfid_clab, ENT_QUOTES, 'UTF-8') . ' <span class="th-sort"><i class="feather icon-chevron-up"></i><i class="feather icon-chevron-down"></i></span></th>';
                                                    } ?>
                                                </tr>
                                            </thead>
                                            <tbody id="rfidScannedBody">
                                                <tr><td colspan="<?php echo count($rfid_scanned_col_map); ?>" class="rfid-table-empty">No Rows To Show</td></tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="rfid-table-footer">
                                        <span class="text-truncate" style="max-width: 45%;">Scroll for more rows / columns</span>
                                        <div class="d-flex align-items-center pagination-mini" style="gap: 4px;">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="First">&laquo;</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Previous">&lsaquo;</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Next">&rsaquo;</button>
                                            <button type="button" class="btn btn-sm btn-outline-secondary" disabled title="Last">&raquo;</button>
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

<div class="modal fade" id="rfidAdvanceFilterModal" tabindex="-1" role="dialog" aria-labelledby="rfidAdvanceFilterModalLabel" aria-hidden="true" data-backdrop="true">
    <div class="modal-dialog modal-dialog-centered rfid-advance-filter-dialog" role="document">
        <div class="modal-content rfid-filter-modal-content">
            <div class="modal-header rfid-filter-modal-header">
                <h5 class="modal-title" id="rfidAdvanceFilterModalLabel">Advance Filter</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body rfid-filter-modal-body">
                <form id="rfidAdvanceFilterForm" class="rfid-filter-form" autocomplete="off" onsubmit="return false;">
                    <div class="rfid-filter-row">
                        <label class="rfid-filter-label" for="rfidFBranch">Branch</label>
                        <div class="rfid-filter-field">
                            <select class="form-control" id="rfidFBranch" name="branch_id">
                                <option value="">All branches</option>
                                <?php foreach ($rfid_branches as $br) {
                                    $bid = (int) ($br['id'] ?? 0);
                                    $bname = htmlspecialchars((string) ($br['name'] ?? ''), ENT_QUOTES, 'UTF-8');
                                    echo '<option value="' . $bid . '">' . $bname . '</option>';
                                } ?>
                            </select>
                        </div>
                    </div>
                    <div class="rfid-filter-row">
                        <label class="rfid-filter-label" for="rfidFMetal">Metal</label>
                        <div class="rfid-filter-field">
                            <select class="form-control" id="rfidFMetal" name="metal_id">
                                <option value="">Select Metal</option>
                                <?php foreach ($rfid_metals as $m) {
                                    $mid = (int) ($m['id'] ?? 0);
                                    $mn = htmlspecialchars((string) ($m['name'] ?? ''), ENT_QUOTES, 'UTF-8');
                                    echo '<option value="' . $mid . '">' . $mn . '</option>';
                                } ?>
                            </select>
                        </div>
                    </div>
                    <div class="rfid-filter-row">
                        <label class="rfid-filter-label" for="rfidFProduct">Product</label>
                        <div class="rfid-filter-field">
                            <select class="form-control" id="rfidFProduct" name="product_id">
                                <option value="">Select Product</option>
                                <?php foreach ($rfid_products as $pr) {
                                    $pid = (int) ($pr['id'] ?? 0);
                                    $pn = htmlspecialchars((string) ($pr['name'] ?? ''), ENT_QUOTES, 'UTF-8');
                                    echo '<option value="' . $pid . '">' . $pn . '</option>';
                                } ?>
                            </select>
                        </div>
                    </div>
                    <div class="rfid-filter-row">
                        <label class="rfid-filter-label" for="rfidFArticle">Article</label>
                        <div class="rfid-filter-field">
                            <select class="form-control" id="rfidFArticle" name="article">
                                <option value="">Select Article</option>
                                <?php foreach ($rfid_articles as $ar) {
                                    $art = trim((string) ($ar['article'] ?? ''));
                                    if ($art === '') {
                                        continue;
                                    }
                                    $art_esc = htmlspecialchars($art, ENT_QUOTES, 'UTF-8');
                                    echo '<option value="' . $art_esc . '">' . $art_esc . '</option>';
                                } ?>
                            </select>
                        </div>
                    </div>
                    <div class="rfid-filter-row">
                        <label class="rfid-filter-label" for="rfidFCategory">Category</label>
                        <div class="rfid-filter-field">
                            <select class="form-control" id="rfidFCategory" name="category_id">
                                <option value="">Select Category</option>
                                <?php foreach ($rfid_categories as $cat) {
                                    $cid = (int) ($cat['id'] ?? 0);
                                    $cn = htmlspecialchars((string) ($cat['name'] ?? ''), ENT_QUOTES, 'UTF-8');
                                    echo '<option value="' . $cid . '">' . $cn . '</option>';
                                } ?>
                            </select>
                        </div>
                    </div>
                    <div class="rfid-filter-row">
                        <label class="rfid-filter-label" for="rfidFVoucher">Voucher Type</label>
                        <div class="rfid-filter-field">
                            <select class="form-control" id="rfidFVoucher" name="voucher_type">
                                <option value="">Select Voucher Type</option>
                                <?php foreach ($rfid_voucher_types as $vt) {
                                    $vn = trim((string) ($vt['name'] ?? ''));
                                    if ($vn === '') {
                                        continue;
                                    }
                                    $vn_esc = htmlspecialchars($vn, ENT_QUOTES, 'UTF-8');
                                    echo '<option value="' . $vn_esc . '">' . $vn_esc . '</option>';
                                } ?>
                            </select>
                        </div>
                    </div>
                    <div class="rfid-filter-row">
                        <label class="rfid-filter-label" for="rfidFKarat">Karat</label>
                        <div class="rfid-filter-field">
                            <select class="form-control" id="rfidFKarat" name="karat_id">
                                <option value="">Select Karat</option>
                                <?php foreach ($rfid_carat as $cr) {
                                    $kid = (int) ($cr['id'] ?? 0);
                                    $kn = htmlspecialchars((string) ($cr['name'] ?? ''), ENT_QUOTES, 'UTF-8');
                                    echo '<option value="' . $kid . '">' . $kn . '</option>';
                                } ?>
                            </select>
                        </div>
                    </div>
                    <div class="rfid-filter-row">
                        <label class="rfid-filter-label" for="rfidFAssign">Inventory Assignment</label>
                        <div class="rfid-filter-field">
                            <select class="form-control" id="rfidFAssign" name="inventory_assignment">
                                <option value="all" selected>All</option>
                                <option value="assigned">Assigned</option>
                                <option value="unassigned">Unassigned</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row rfid-filter-split">
                        <div class="col-md-6">
                            <div class="rfid-filter-row">
                                <label class="rfid-filter-label" for="rfidFGroup">Group Name</label>
                                <div class="rfid-filter-field">
                                    <input type="text" class="form-control" id="rfidFGroup" name="group_name" placeholder="">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="rfid-filter-row">
                                <label class="rfid-filter-label" for="rfidFBarcodeNo">Barcode No</label>
                                <div class="rfid-filter-field">
                                    <input type="text" class="form-control" id="rfidFBarcodeNo" name="barcode_no" placeholder="">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-row rfid-filter-split">
                        <div class="col-md-6">
                            <div class="rfid-filter-row">
                                <label class="rfid-filter-label" for="rfidFInvoice">Invoice No.</label>
                                <div class="rfid-filter-field">
                                    <input type="text" class="form-control" id="rfidFInvoice" name="invoice_no" placeholder="">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="rfid-filter-row">
                                <label class="rfid-filter-label" for="rfidFRfid">RFID Code</label>
                                <div class="rfid-filter-field">
                                    <input type="text" class="form-control" id="rfidFRfid" name="rfid_code" placeholder="">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-row rfid-filter-split">
                        <div class="col-md-6">
                            <div class="rfid-filter-row">
                                <label class="rfid-filter-label" for="rfidFGross">Gross Wt</label>
                                <div class="rfid-filter-field">
                                    <input type="text" class="form-control" id="rfidFGross" name="gross_wt" placeholder="">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 d-none d-md-block"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer rfid-filter-modal-footer border-0">
                <button type="button" class="btn btn-rfid-apply-filter" id="rfidBtnApplyFilter">Apply Filter</button>
                <button type="button" class="btn btn-rfid-clear-filter" id="rfidBtnClearFilter">Clear Filter</button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/footer-script.php'; ?>
<script src="assets/js/product-modal-multi-barcode.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/product-modal-multi-barcode.js'); ?>"></script>
<script>
(function () {
    window.AURAGOLD_WORKING_BRANCH_ID = <?php echo (int) (function_exists('auragold_effective_branch_id') ? auragold_effective_branch_id() : 0); ?>;
    var RFID_AVAIL_KEYS = <?php echo json_encode(array_keys($rfid_avail_col_map)); ?>;
    var RFID_SCAN_KEYS = <?php echo json_encode(array_keys($rfid_scanned_col_map)); ?>;
    var LS_HIDDEN = { available: 'auragold_rfid_cols_hidden_available', scanned: 'auragold_rfid_cols_hidden_scanned' };
    var LS_RFID_SCAN = 'auragold_rfid_scan_session_v1';

    var input = document.getElementById('rfidBarcodeInput');
    var availBody = document.getElementById('rfidAvailableBody');
    var scanBody = document.getElementById('rfidScannedBody');
    var availTitle = document.getElementById('rfidAvailCardTitle');
    var scanTitle = document.getElementById('rfidScanCardTitle');
    var elUnknown = document.getElementById('rfidSummaryUnknown');

    /** Last full list from server (before moving rows to scanned). */
    var rfidServerRowsAll = [];
    /** Rows moved to Scanned Stock this session. */
    var rfidScannedRows = [];
    var rfidScannedKeySet = new Set();
    var rfidUnknownScanCount = 0;
    var rfidScanInFlight = false;
    var rfidDocScanBuffer = '';

    function rfidSanitizeBarcodeInput(el) {
        if (!el) {
            return '';
        }
        var raw = String(el.value || '');
        var cleaned = typeof auragoldNormalizeScannedBarcode === 'function'
            ? auragoldNormalizeScannedBarcode(raw)
            : raw.replace(/[\x00-\x1F\x7F]/g, '').trim();
        if (cleaned !== raw) {
            el.value = cleaned;
        }
        return cleaned;
    }

    function rfidResolveAndProcessScan(raw) {
        if (rfidScanInFlight) {
            return;
        }
        var scanned = typeof auragoldNormalizeScannedBarcode === 'function'
            ? auragoldNormalizeScannedBarcode(raw)
            : String(raw != null ? raw : '').trim();
        if (!scanned) {
            return;
        }
        rfidScanInFlight = true;
        var opts = {
            branch_id: (typeof window.AURAGOLD_WORKING_BRANCH_ID !== 'undefined') ? window.AURAGOLD_WORKING_BRANCH_ID : 0,
            metal_id: ''
        };
        function finish(lookup) {
            lookup = String(lookup || scanned).trim();
            if (input && lookup) {
                input.value = lookup;
            }
            rfidProcessScan(lookup);
            rfidScanInFlight = false;
            rfidDocScanBuffer = '';
            if (input) {
                input.value = '';
                try {
                    input.focus();
                } catch (e) { /* ignore */ }
            }
        }
        if (typeof auragoldRunResolvedBarcodeFetch === 'function') {
            auragoldRunResolvedBarcodeFetch(scanned, opts, input, finish);
        } else {
            finish(scanned);
        }
    }

    function rfidBindBarcodeScanner() {
        if (!input) {
            return;
        }
        var blurTimer = null;
        var inputTimer = null;
        var docBufferTimer = null;
        var rapidKeyCount = 0;
        var rapidKeyResetTimer = null;

        function clearScanTimers() {
            if (blurTimer) {
                clearTimeout(blurTimer);
                blurTimer = null;
            }
            if (inputTimer) {
                clearTimeout(inputTimer);
                inputTimer = null;
            }
        }

        function scheduleFromInput(fromBlur, delayMs) {
            clearScanTimers();
            setTimeout(function () {
                var v = rfidSanitizeBarcodeInput(input);
                if (v) {
                    rfidResolveAndProcessScan(v);
                }
            }, delayMs || 0);
        }

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.keyCode === 13) {
                if (rfidSanitizeBarcodeInput(input)) {
                    e.preventDefault();
                    clearScanTimers();
                    scheduleFromInput(false, 40);
                }
                return;
            }
            if (e.key === 'Tab' && !e.shiftKey) {
                if (rfidSanitizeBarcodeInput(input)) {
                    e.preventDefault();
                    clearScanTimers();
                    scheduleFromInput(false, 40);
                }
                return;
            }
            if (e.key && e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
                rapidKeyCount += 1;
                if (rapidKeyResetTimer) {
                    clearTimeout(rapidKeyResetTimer);
                }
                rapidKeyResetTimer = setTimeout(function () {
                    rapidKeyResetTimer = null;
                    rapidKeyCount = 0;
                }, 400);
            }
        });

        input.addEventListener('input', function () {
            rfidDocScanBuffer = '';
            var cleaned = rfidSanitizeBarcodeInput(input);
            if (!cleaned) {
                clearScanTimers();
                return;
            }
            clearScanTimers();
            inputTimer = setTimeout(function () {
                inputTimer = null;
                if (!rfidSanitizeBarcodeInput(input)) {
                    return;
                }
                if (rapidKeyCount >= 4) {
                    rapidKeyCount = 0;
                    rfidResolveAndProcessScan(cleaned);
                }
            }, 90);
        });

        input.addEventListener('blur', function () {
            if (blurTimer) {
                clearTimeout(blurTimer);
            }
            blurTimer = setTimeout(function () {
                blurTimer = null;
                var v = rfidSanitizeBarcodeInput(input);
                if (v) {
                    rfidResolveAndProcessScan(v);
                }
            }, 150);
        });

        /* USB wedge often types before input has focus — capture on document when not in another field. */
        document.addEventListener('keydown', function (e) {
            var ae = document.activeElement;
            if (ae === input) {
                return;
            }
            if (ae && (ae.tagName === 'INPUT' || ae.tagName === 'TEXTAREA' || ae.tagName === 'SELECT' || ae.isContentEditable)) {
                return;
            }
            if (e.ctrlKey || e.metaKey || e.altKey) {
                return;
            }
            if (e.key === 'Enter' || e.keyCode === 13) {
                if (rfidDocScanBuffer.length >= 2) {
                    e.preventDefault();
                    input.value = rfidDocScanBuffer;
                    rfidResolveAndProcessScan(rfidDocScanBuffer);
                    rfidDocScanBuffer = '';
                }
                return;
            }
            if (e.key && e.key.length === 1) {
                rfidDocScanBuffer += e.key;
                input.value = rfidDocScanBuffer;
                if (docBufferTimer) {
                    clearTimeout(docBufferTimer);
                }
                docBufferTimer = setTimeout(function () {
                    docBufferTimer = null;
                    if (rfidDocScanBuffer.length >= 4) {
                        rfidResolveAndProcessScan(rfidDocScanBuffer);
                        rfidDocScanBuffer = '';
                    }
                }, 120);
            }
        }, true);

        var wrap = document.querySelector('.rfid-scan-wrap');
        if (wrap) {
            wrap.addEventListener('mousedown', function (e) {
                if (e.target.closest('.modal') || e.target.closest('button.dropdown-toggle') || e.target.closest('a')) {
                    return;
                }
                if (e.target === input || (input.contains && input.contains(e.target))) {
                    return;
                }
                setTimeout(function () {
                    try {
                        input.focus();
                    } catch (err) { /* ignore */ }
                }, 0);
            });
        }

        if (typeof jQuery !== 'undefined') {
            jQuery('#rfidAdvanceFilterModal').on('hidden.bs.modal', function () {
                setTimeout(function () {
                    try {
                        input.focus();
                    } catch (err) { /* ignore */ }
                }, 120);
            });
        }

        try {
            input.focus();
        } catch (e) { /* ignore */ }
    }

    if (input) {
        rfidBindBarcodeScanner();
    }
    var btnReset = document.getElementById('rfidBtnReset');
    if (btnReset && input) {
        btnReset.addEventListener('click', function () {
            if (window.confirm('Reset RFID scan session?')) {
                rfidScannedRows = [];
                rfidScannedKeySet = new Set();
                rfidUnknownScanCount = 0;
                input.value = '';
                if (elUnknown) elUnknown.textContent = '0';
                try {
                    localStorage.removeItem(LS_RFID_SCAN);
                } catch (e) { /* ignore */ }
                rfidRenderAvailableRows(rfidGetAvailableRows());
                rfidRenderScannedRows(rfidScannedRows);
                rfidUpdateStockTitlesAndToolbar();
                input.focus();
            }
        });
    }
    var filterForm = document.getElementById('rfidAdvanceFilterForm');
    var filterBadge = document.getElementById('rfidFilterBadge');
    var tableAvail = document.getElementById('rfidTableAvailable');
    var tableScan = document.getElementById('rfidTableScanned');

    function rfidFilterFingerprint() {
        if (!filterForm) return '';
        var fd = new FormData(filterForm);
        var pairs = [];
        fd.forEach(function (val, key) {
            pairs.push(encodeURIComponent(key) + '=' + encodeURIComponent(String(val)));
        });
        pairs.sort();
        return pairs.join('&');
    }

    function rfidPersistScanSession() {
        try {
            if (!filterForm) return;
            var keys = [];
            rfidScannedKeySet.forEach(function (k) { keys.push(k); });
            keys.sort();
            localStorage.setItem(LS_RFID_SCAN, JSON.stringify({
                v: 1,
                fp: rfidFilterFingerprint(),
                keys: keys,
                rows: rfidScannedRows,
                unknown: rfidUnknownScanCount
            }));
        } catch (e) { /* quota / private mode */ }
    }

    function rfidRestoreScanSessionAfterLoad() {
        try {
            var raw = localStorage.getItem(LS_RFID_SCAN);
            if (!raw) return;
            var o = JSON.parse(raw);
            if (!o || o.v !== 1) return;
            var curFp = rfidFilterFingerprint();
            if (o.fp !== curFp) {
                localStorage.removeItem(LS_RFID_SCAN);
                rfidScannedRows = [];
                rfidScannedKeySet = new Set();
                rfidUnknownScanCount = 0;
                if (elUnknown) elUnknown.textContent = '0';
                return;
            }
            var rowsIn = Array.isArray(o.rows) ? o.rows : [];
            var seen = {};
            rfidScannedRows = [];
            rfidScannedKeySet = new Set();
            rowsIn.forEach(function (r) {
                if (!r || r.barcode == null) return;
                var k = rfidRowKey({ barcode: r.barcode, branch: r.branch });
                if (seen[k]) return;
                seen[k] = true;
                rfidScannedRows.push(r);
                rfidScannedKeySet.add(k);
            });
            rfidUnknownScanCount = typeof o.unknown === 'number' ? o.unknown : 0;
            if (elUnknown) elUnknown.textContent = String(rfidUnknownScanCount);
        } catch (e) {
            try {
                localStorage.removeItem(LS_RFID_SCAN);
            } catch (e2) { /* ignore */ }
        }
    }

    function rfidNowStamp() {
        var d = new Date();
        var pad = function (n) { return n < 10 ? '0' + n : String(n); };
        return {
            scan_date: pad(d.getDate()) + '-' + pad(d.getMonth() + 1) + '-' + d.getFullYear(),
            scan_time: pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':' + pad(d.getSeconds())
        };
    }

    function rfidSaveScanLogToServer(payload) {
        if (!payload || !payload.scan_code) return;
        var url = (function () {
            try {
                return new URL('ajax/save-rfid-barcode-scan-log.php', window.location.href).href;
            } catch (e) {
                return 'ajax/save-rfid-barcode-scan-log.php';
            }
        })();
        fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify(payload)
        }).then(function (res) { return res.json(); }).then(function (data) {
            if (!data || !data.success) return;
            if (data.scan_date || data.scan_time) {
                for (var i = rfidScannedRows.length - 1; i >= 0; i--) {
                    var r = rfidScannedRows[i];
                    if (!r) continue;
                    var matchCode = rfidNormCode(payload.scan_code);
                    if (rfidNormCode(r.barcode) === matchCode || rfidNormCode(r.rfid_code) === matchCode) {
                        if (data.scan_date) r.scan_date = data.scan_date;
                        if (data.scan_time) r.scan_time = data.scan_time;
                        rfidRenderScannedRows(rfidScannedRows);
                        rfidPersistScanSession();
                        break;
                    }
                }
            }
        }).catch(function () { /* non-blocking */ });
    }

    function rfidRowKey(r) {
        var b = String(r.barcode != null ? r.barcode : '').trim().toLowerCase();
        var br = String(r.branch != null ? r.branch : '').trim().toLowerCase();
        return b + '\t' + br;
    }

    function rfidNormCode(s) {
        return String(s != null ? s : '').trim().toLowerCase();
    }

    function rfidGetAvailableRows() {
        return rfidServerRowsAll.filter(function (r) {
            return !rfidScannedKeySet.has(rfidRowKey(r));
        });
    }

    function rfidSumRowsQtyWt(rows) {
        var q = 0;
        var w = 0;
        rows.forEach(function (r) {
            var fq = parseFloat(r.qty);
            if (!isNaN(fq)) q += fq;
            var fw = parseFloat(r.final_wt);
            if (!isNaN(fw)) w += fw;
        });
        return { qty: q, final_wt: w };
    }

    function rfidAvailRowToScanned(r) {
        var stamp = rfidNowStamp();
        return {
            active: 'Yes',
            scan_date: stamp.scan_date,
            scan_time: stamp.scan_time,
            branch: r.branch != null ? String(r.branch) : '',
            product_code: r.product_code != null ? String(r.product_code) : '',
            product_name: r.product_name != null ? String(r.product_name) : '',
            article: r.article != null ? String(r.article) : '',
            location: r.location != null ? String(r.location) : '',
            rfid_code: r.rfid_code != null ? String(r.rfid_code) : '',
            barcode: r.barcode != null ? String(r.barcode) : '',
            qty: r.qty,
            metal: r.metal != null ? String(r.metal) : '',
            gross_wt: r.gross_wt,
            purity_wt: r.purity_wt,
            net_wt: r.net_wt,
            final_wt: r.final_wt,
            voucher_type: r.voucher_type != null ? String(r.voucher_type) : '',
            invoice_no: r.invoice_no != null ? String(r.invoice_no) : ''
        };
    }

    function rfidFindInAvailableByCode(code) {
        var c = rfidNormCode(code);
        if (!c) return null;
        var rows = rfidGetAvailableRows();
        for (var i = 0; i < rows.length; i++) {
            var r = rows[i];
            if (rfidNormCode(r.barcode) === c) return r;
            if (rfidNormCode(r.rfid_code) === c) return r;
        }
        /* 82×38 labels: match last 4 digits when full prefix was not resolved. */
        var digits = String(code != null ? code : '').replace(/\D/g, '');
        if (/^\d{1,4}$/.test(digits)) {
            var padded = digits.padStart(4, '0');
            for (var j = 0; j < rows.length; j++) {
                var r2 = rows[j];
                var bc = rfidNormCode(r2.barcode);
                if (bc && bc.slice(-4) === padded) {
                    return r2;
                }
            }
        }
        return null;
    }

    function rfidFindInScannedByCode(code) {
        var c = rfidNormCode(code);
        if (!c) return null;
        for (var i = 0; i < rfidScannedRows.length; i++) {
            var r = rfidScannedRows[i];
            if (rfidNormCode(r.barcode) === c) return r;
            if (rfidNormCode(r.rfid_code) === c) return r;
        }
        var digits = String(code != null ? code : '').replace(/\D/g, '');
        if (/^\d{1,4}$/.test(digits)) {
            var padded = digits.padStart(4, '0');
            for (var j = 0; j < rfidScannedRows.length; j++) {
                var r2 = rfidScannedRows[j];
                var bc = rfidNormCode(r2.barcode);
                if (bc && bc.slice(-4) === padded) {
                    return r2;
                }
            }
        }
        return null;
    }

    function rfidUpdateStockTitlesAndToolbar() {
        var availRows = rfidGetAvailableRows();
        var scanSum = rfidSumRowsQtyWt(rfidScannedRows);
        var availSum = rfidSumRowsQtyWt(availRows);
        if (availTitle) {
            availTitle.textContent = 'Available Stock (Total Wt : ' + rfidFmtNum(availSum.final_wt) + ', Qty : ' + rfidFmtNum(availSum.qty) + ')';
        }
        if (scanTitle) {
            scanTitle.textContent = 'Scanned Stock (Total Wt : ' + rfidFmtNum(scanSum.final_wt) + ', Qty : ' + rfidFmtNum(scanSum.qty) + ')';
        }
        var sw = document.getElementById('rfidSummaryWt');
        var sq = document.getElementById('rfidSummaryQty');
        if (sw) sw.textContent = rfidFmtNum(availSum.final_wt) !== '' ? rfidFmtNum(availSum.final_wt) : '0';
        if (sq) sq.textContent = rfidFmtNum(availSum.qty) !== '' ? rfidFmtNum(availSum.qty) : '0';
    }

    function rfidProcessScan(raw) {
        if (!input) return;
        var code = typeof auragoldNormalizeScannedBarcode === 'function'
            ? auragoldNormalizeScannedBarcode(raw)
            : String(raw != null ? raw : '').trim();
        if (!code) {
            input.focus();
            return;
        }
        if (rfidFindInScannedByCode(code)) {
            input.focus();
            return;
        }
        var row = rfidFindInAvailableByCode(code);
        if (!row) {
            rfidUnknownScanCount += 1;
            if (elUnknown) elUnknown.textContent = String(rfidUnknownScanCount);
            rfidSaveScanLogToServer({ scan_code: code, scan_status: 'unknown' });
            rfidPersistScanSession();
            input.focus();
            return;
        }
        var k = rfidRowKey(row);
        if (rfidScannedKeySet.has(k)) {
            input.focus();
            return;
        }
        rfidScannedKeySet.add(k);
        var scannedRow = rfidAvailRowToScanned(row);
        rfidScannedRows.push(scannedRow);
        rfidSaveScanLogToServer({
            scan_code: code,
            scan_status: 'matched',
            barcode: scannedRow.barcode,
            rfid_code: scannedRow.rfid_code,
            product_code: scannedRow.product_code,
            product_name: scannedRow.product_name,
            article: scannedRow.article,
            metal_name: scannedRow.metal,
            branch_name: scannedRow.branch,
            location: scannedRow.location,
            qty: scannedRow.qty,
            gross_wt: scannedRow.gross_wt,
            net_wt: scannedRow.net_wt,
            final_wt: scannedRow.final_wt,
            voucher_type: scannedRow.voucher_type,
            invoice_no: scannedRow.invoice_no
        });
        rfidRenderAvailableRows(rfidGetAvailableRows());
        rfidRenderScannedRows(rfidScannedRows);
        rfidUpdateStockTitlesAndToolbar();
        rfidPersistScanSession();
        input.focus();
    }

    function rfidEsc(s) {
        if (s == null || s === '') return '';
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function rfidFmtNum(v) {
        if (v === null || v === undefined || v === '') return '';
        var n = parseFloat(v);
        if (isNaN(n)) return rfidEsc(v);
        return (Math.round(n * 10000) / 10000).toString();
    }

    function rfidVisibleColCount(which) {
        var tbl = which === 'available' ? tableAvail : tableScan;
        if (!tbl) return 0;
        return tbl.querySelectorAll('thead th[data-rfid-col]:not(.rfid-col-hidden)').length;
    }

    function rfidGetHiddenSet(which) {
        try {
            var raw = localStorage.getItem(LS_HIDDEN[which]);
            if (!raw) return [];
            var a = JSON.parse(raw);
            return Array.isArray(a) ? a : [];
        } catch (e) {
            return [];
        }
    }

    function rfidSetHiddenSet(which, keys) {
        try {
            localStorage.setItem(LS_HIDDEN[which], JSON.stringify(keys));
        } catch (e) { /* ignore */ }
    }

    function rfidApplyColumnVisibility(which) {
        var tbl = which === 'available' ? tableAvail : tableScan;
        if (!tbl) return;
        var hidden = rfidGetHiddenSet(which);
        var hiddenObj = {};
        hidden.forEach(function (k) { hiddenObj[k] = true; });
        tbl.querySelectorAll('thead th[data-rfid-col], tbody td[data-rfid-col]').forEach(function (el) {
            var k = el.getAttribute('data-rfid-col');
            if (!k) return;
            if (hiddenObj[k]) el.classList.add('rfid-col-hidden');
            else el.classList.remove('rfid-col-hidden');
        });
        tbl.querySelectorAll('tbody tr > td[colspan]').forEach(function (td) {
            var n = rfidVisibleColCount(which);
            td.colSpan = Math.max(1, n);
        });
        document.querySelectorAll('.rfid-col-chk[data-rfid-table="' + which + '"]').forEach(function (chk) {
            var key = chk.getAttribute('data-rfid-col-key');
            chk.checked = !hiddenObj[key];
        });
    }

    function rfidSyncCheckboxesFromStorage(which) {
        var hidden = rfidGetHiddenSet(which);
        var hiddenObj = {};
        hidden.forEach(function (k) { hiddenObj[k] = true; });
        document.querySelectorAll('.rfid-col-chk[data-rfid-table="' + which + '"]').forEach(function (chk) {
            var key = chk.getAttribute('data-rfid-col-key');
            chk.checked = !hiddenObj[key];
        });
    }

    function rfidCloseAllColPickers() {
        document.querySelectorAll('.rfid-col-picker-panel').forEach(function (p) {
            p.classList.add('d-none');
            p.setAttribute('aria-hidden', 'true');
        });
        document.querySelectorAll('.rfid-col-picker-toggle').forEach(function (b) {
            b.setAttribute('aria-expanded', 'false');
        });
    }

    function rfidToggleColPicker(which) {
        var panel = document.querySelector('[data-rfid-picker-panel="' + which + '"]');
        var btn = document.querySelector('.rfid-col-picker-toggle[data-rfid-picker="' + which + '"]');
        if (!panel) return;
        var open = panel.classList.contains('d-none');
        rfidCloseAllColPickers();
        if (open) {
            panel.classList.remove('d-none');
            panel.setAttribute('aria-hidden', 'false');
            if (btn) btn.setAttribute('aria-expanded', 'true');
            var inp = panel.querySelector('.rfid-col-picker-filter');
            if (inp) {
                inp.value = '';
                inp.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }
    }

    document.querySelectorAll('.rfid-col-picker-toggle').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var which = btn.getAttribute('data-rfid-picker');
            var panel = document.querySelector('[data-rfid-picker-panel="' + which + '"]');
            if (panel && !panel.classList.contains('d-none')) {
                rfidCloseAllColPickers();
            } else {
                rfidToggleColPicker(which);
            }
        });
    });

    document.querySelectorAll('[data-rfid-picker-close]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            rfidCloseAllColPickers();
        });
    });

    document.addEventListener('click', function () {
        rfidCloseAllColPickers();
    });
    document.querySelectorAll('.rfid-col-picker-wrap').forEach(function (w) {
        w.addEventListener('click', function (e) { e.stopPropagation(); });
    });

    document.querySelectorAll('.rfid-col-picker-filter').forEach(function (inp) {
        inp.addEventListener('click', function (e) { e.stopPropagation(); });
        inp.addEventListener('input', function () {
            var which = inp.getAttribute('data-rfid-picker-filter');
            var panel = document.querySelector('[data-rfid-picker-panel="' + which + '"]');
            if (!panel) return;
            var q = (inp.value || '').trim().toLowerCase();
            panel.querySelectorAll('.rfid-col-picker-item').forEach(function (row) {
                var lab = row.getAttribute('data-rfid-col-label') || '';
                if (!q || lab.indexOf(q) !== -1) row.classList.remove('d-none');
                else row.classList.add('d-none');
            });
        });
    });

    document.querySelectorAll('.rfid-col-chk').forEach(function (chk) {
        chk.addEventListener('click', function (e) { e.stopPropagation(); });
        chk.addEventListener('change', function () {
            var which = chk.getAttribute('data-rfid-table');
            var key = chk.getAttribute('data-rfid-col-key');
            var keys = which === 'available' ? RFID_AVAIL_KEYS : RFID_SCAN_KEYS;
            var hidden = [];
            document.querySelectorAll('.rfid-col-chk[data-rfid-table="' + which + '"]').forEach(function (c) {
                if (!c.checked) hidden.push(c.getAttribute('data-rfid-col-key'));
            });
            if (hidden.length >= keys.length) {
                chk.checked = true;
                return;
            }
            rfidSetHiddenSet(which, hidden);
            rfidApplyColumnVisibility(which);
        });
    });

    document.querySelectorAll('[data-rfid-picker-reset]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var which = btn.getAttribute('data-rfid-picker-reset');
            rfidSetHiddenSet(which, []);
            document.querySelectorAll('.rfid-col-chk[data-rfid-table="' + which + '"]').forEach(function (c) { c.checked = true; });
            rfidApplyColumnVisibility(which);
        });
    });

    function rfidCountActiveFilters() {
        if (!filterForm) return 0;
        var n = 0;
        var fd = new FormData(filterForm);
        fd.forEach(function (val, key) {
            if (key === 'inventory_assignment') {
                if (val && val !== 'all') n++;
                return;
            }
            if (val != null && String(val).trim() !== '') n++;
        });
        return n;
    }

    function rfidUpdateFilterBadge() {
        if (!filterBadge) return;
        var c = rfidCountActiveFilters();
        filterBadge.textContent = String(c);
        if (c > 0) filterBadge.classList.remove('d-none');
        else filterBadge.classList.add('d-none');
    }

    function rfidRenderAvailableRows(rows) {
        if (!availBody) return;
        availBody.innerHTML = '';
        var span = Math.max(1, rfidVisibleColCount('available'));
        if (!rows || !rows.length) {
            var tr = document.createElement('tr');
            tr.innerHTML = '<td colspan="' + span + '" class="rfid-table-empty">No Rows To Show</td>';
            availBody.appendChild(tr);
            rfidApplyColumnVisibility('available');
            return;
        }
        var byKey = {
            isScanned: function (r) { return rfidEsc(r.isScanned); },
            branch: function (r) { return rfidEsc(r.branch); },
            carat: function (r) { return rfidEsc(r.carat); },
            action: function () { return '<button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" disabled title="Action">—</button>'; },
            metal: function (r) { return rfidEsc(r.metal); },
            product_code: function (r) { return rfidEsc(r.product_code); },
            article: function (r) { return rfidEsc(r.article); },
            rfid_code: function (r) { return rfidEsc(r.rfid_code); },
            barcode: function (r) { return rfidEsc(r.barcode); },
            qty: function (r) { return rfidFmtNum(r.qty); },
            location: function (r) { return rfidEsc(r.location); },
            gross_wt: function (r) { return rfidFmtNum(r.gross_wt); },
            purity_wt: function (r) { return rfidFmtNum(r.purity_wt); },
            net_wt: function (r) { return rfidFmtNum(r.net_wt); },
            final_wt: function (r) { return rfidFmtNum(r.final_wt); },
            voucher_type: function (r) { return rfidEsc(r.voucher_type); },
            invoice_no: function (r) { return rfidEsc(r.invoice_no); }
        };
        rows.forEach(function (r) {
            var tr = document.createElement('tr');
            var html = RFID_AVAIL_KEYS.map(function (key) {
                var fn = byKey[key];
                var inner = fn ? fn(r) : '';
                return '<td data-rfid-col="' + rfidEsc(key) + '">' + inner + '</td>';
            }).join('');
            tr.innerHTML = html;
            availBody.appendChild(tr);
        });
        rfidApplyColumnVisibility('available');
    }

    function rfidRenderScannedRows(rows) {
        if (!scanBody) return;
        scanBody.innerHTML = '';
        var span = Math.max(1, rfidVisibleColCount('scanned'));
        if (!rows || !rows.length) {
            var tr0 = document.createElement('tr');
            tr0.innerHTML = '<td colspan="' + span + '" class="rfid-table-empty">No Rows To Show</td>';
            scanBody.appendChild(tr0);
            rfidApplyColumnVisibility('scanned');
            return;
        }
        var byKeyScan = {
            active: function (r) { return rfidEsc(r.active); },
            scan_date: function (r) { return rfidEsc(r.scan_date); },
            scan_time: function (r) { return rfidEsc(r.scan_time); },
            branch: function (r) { return rfidEsc(r.branch); },
            product_code: function (r) { return rfidEsc(r.product_code); },
            article: function (r) { return rfidEsc(r.article); },
            location: function (r) { return rfidEsc(r.location); },
            rfid_code: function (r) { return rfidEsc(r.rfid_code); },
            barcode: function (r) { return rfidEsc(r.barcode); },
            qty: function (r) { return rfidFmtNum(r.qty); },
            metal: function (r) { return rfidEsc(r.metal); },
            gross_wt: function (r) { return rfidFmtNum(r.gross_wt); },
            purity_wt: function (r) { return rfidFmtNum(r.purity_wt); },
            net_wt: function (r) { return rfidFmtNum(r.net_wt); },
            final_wt: function (r) { return rfidFmtNum(r.final_wt); },
            voucher_type: function (r) { return rfidEsc(r.voucher_type); }
        };
        rows.forEach(function (r) {
            var tr = document.createElement('tr');
            var html = RFID_SCAN_KEYS.map(function (key) {
                var fn = byKeyScan[key];
                var inner = fn ? fn(r) : '';
                return '<td data-rfid-col="' + rfidEsc(key) + '">' + inner + '</td>';
            }).join('');
            tr.innerHTML = html;
            scanBody.appendChild(tr);
        });
        rfidApplyColumnVisibility('scanned');
    }

    function rfidLoadAvailableStock() {
        if (!filterForm || !availBody) return;
        var span = Math.max(1, rfidVisibleColCount('available'));
        availBody.innerHTML = '<tr><td colspan="' + span + '" class="rfid-table-empty text-info">Loading…</td></tr>';
        var params = new URLSearchParams(new FormData(filterForm));
        var stockAjaxUrl = (function () {
            try {
                return new URL('ajax/rfid-available-stock.php', window.location.href).href;
            } catch (e) {
                return 'ajax/rfid-available-stock.php';
            }
        })();
        fetch(stockAjaxUrl, {
            method: 'POST',
            body: params,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            return res.text().then(function (text) {
                var t = text.replace(/^\uFEFF/, '').trim();
                if (!t) {
                    throw new Error('Empty response (HTTP ' + res.status + ')');
                }
                try {
                    return JSON.parse(t);
                } catch (e) {
                    var hint = t.length > 160 ? t.slice(0, 160) + '…' : t;
                    throw new Error('Invalid JSON (HTTP ' + res.status + '): ' + hint.replace(/\s+/g, ' '));
                }
            });
        }).then(function (data) {
            if (!data || !data.success) {
                span = Math.max(1, rfidVisibleColCount('available'));
                var errMsg = (data && data.message) ? String(data.message) : 'Failed to load stock';
                availBody.innerHTML = '<tr><td colspan="' + span + '" class="rfid-table-empty text-danger">' + errMsg.replace(/</g, '&lt;') + '</td></tr>';
                return;
            }
            rfidServerRowsAll = data.rows || [];
            rfidRestoreScanSessionAfterLoad();
            rfidRenderAvailableRows(rfidGetAvailableRows());
            rfidRenderScannedRows(rfidScannedRows);
            rfidUpdateStockTitlesAndToolbar();
            if (input) {
                try {
                    input.focus();
                } catch (e) { /* ignore */ }
            }
        }).catch(function (err) {
            span = Math.max(1, rfidVisibleColCount('available'));
            var msg = (err && err.message) ? String(err.message) : 'Network error';
            availBody.innerHTML = '<tr><td colspan="' + span + '" class="rfid-table-empty text-danger">' + msg.replace(/</g, '&lt;') + '</td></tr>';
        });
    }

    document.getElementById('rfidBtnApplyFilter') && document.getElementById('rfidBtnApplyFilter').addEventListener('click', function () {
        rfidUpdateFilterBadge();
        rfidLoadAvailableStock();
        if (window.jQuery) {
            jQuery('#rfidAdvanceFilterModal').modal('hide');
        }
    });

    document.getElementById('rfidBtnClearFilter') && document.getElementById('rfidBtnClearFilter').addEventListener('click', function () {
        if (!filterForm) return;
        filterForm.reset();
        var assign = document.getElementById('rfidFAssign');
        if (assign) assign.value = 'all';
        rfidUpdateFilterBadge();
        rfidLoadAvailableStock();
    });

    document.getElementById('rfidBtnRefresh') && document.getElementById('rfidBtnRefresh').addEventListener('click', function () {
        rfidLoadAvailableStock();
    });

    rfidSyncCheckboxesFromStorage('available');
    rfidSyncCheckboxesFromStorage('scanned');
    rfidApplyColumnVisibility('available');
    rfidApplyColumnVisibility('scanned');

    if (filterForm) {
        rfidUpdateFilterBadge();
        rfidLoadAvailableStock();
    }

    function rfidExportExcelDownload() {
        var exportUrl = (function () {
            try {
                return new URL('ajax/export-rfid-barcode-scan-excel.php', window.location.href).href;
            } catch (e) {
                return 'ajax/export-rfid-barcode-scan-excel.php';
            }
        })();
        var fd = new FormData();
        if (filterForm) {
            new FormData(filterForm).forEach(function (val, key) {
                fd.append(key, val);
            });
        }
        fd.append('available_json', JSON.stringify(rfidGetAvailableRows()));
        fd.append('scanned_json', JSON.stringify(rfidScannedRows.slice()));
        fd.append('unknown_count', String(rfidUnknownScanCount));
        fetch(exportUrl, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function (res) {
            if (!res.ok) {
                return res.text().then(function (t) {
                    throw new Error(t || ('Export failed (HTTP ' + res.status + ')'));
                });
            }
            var disp = res.headers.get('Content-Disposition') || '';
            var m = /filename=\"?([^\";]+)\"?/i.exec(disp);
            var fname = (m && m[1]) ? m[1] : ('RFID_Barcode_Scan_' + new Date().toISOString().slice(0, 10) + '.xlsx');
            return res.blob().then(function (blob) {
                return { blob: blob, fname: fname };
            });
        }).then(function (o) {
            var a = document.createElement('a');
            var u = URL.createObjectURL(o.blob);
            a.href = u;
            a.download = o.fname;
            document.body.appendChild(a);
            a.click();
            setTimeout(function () {
                URL.revokeObjectURL(u);
                a.remove();
            }, 200);
        }).catch(function (err) {
            var msg = (err && err.message) ? String(err.message) : 'Export failed';
            window.alert(msg.replace(/<[^>]+>/g, '').slice(0, 500));
        });
    }

    var btnExportExcel = document.getElementById('rfidExportExcel');
    if (btnExportExcel) {
        btnExportExcel.addEventListener('click', function (e) {
            e.preventDefault();
            rfidExportExcelDownload();
        });
    }
})();
</script>
</body>
</html>
