<?php
session_start();
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['user_id']) || (int) $_SESSION['user_id'] <= 0) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Job Card Print - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?> Software</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <?php include 'header-script.php'; ?>
    <link rel="stylesheet" href="assets/css/mfg-pages-mobile.css">
</head>

<style>
:root {
    --gm-navy: #11294b;
    --gm-gold-pale: #faf6eb;
}

html, body {
    height: 100vh;
    overflow-x: hidden !important;
    background: #f4f6f9;
}

body.job-card-print-page .layout-content {
    height: calc(100vh - 60px);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    min-height: 0;
}
body.job-card-print-page .layout-content > .container-fluid {
    flex: 1 1 auto;
    min-height: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

body.job-card-print-page .jcp-page-wrap {
    padding: 12px 14px 80px;
}

.head-setting-btn {
    border: 0;
    background: transparent;
    color: #1a3a5c;
    padding: 0;
    width: 20px;
    height: 20px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.columns-panel {
    position: fixed;
    top: 0;
    left: 0;
    width: 250px;
    background: linear-gradient(180deg, #fff 0%, var(--gm-gold-pale) 100%);
    border: 1px solid rgba(17, 41, 75, 0.15);
    border-radius: 6px;
    z-index: 1200;
    display: none;
    box-shadow: 0 6px 20px rgba(31, 41, 55, 0.18);
}
.columns-panel.show {
    display: block;
}
.columns-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 8px;
    border-bottom: 1px solid #ccd4e4;
    font-size: 12px;
    font-weight: 700;
    color: #4c5a7a;
}
.columns-panel-header .icons {
    display: inline-flex;
    gap: 5px;
    align-items: center;
}
.columns-panel-header .icons .tag {
    font-size: 10px;
    border: 1px solid #c7d1e5;
    background: #fff;
    padding: 1px 4px;
    border-radius: 3px;
}
.columns-panel-close {
    border: 0;
    background: transparent;
    color: #7786a8;
    font-size: 16px;
    line-height: 1;
    padding: 0 2px;
}
.columns-search {
    padding: 6px 8px 4px;
}
.columns-search input {
    width: 100%;
    height: 24px;
    border: 1px solid #c8d0e2;
    border-radius: 5px;
    padding: 0 8px;
    font-size: 12px;
}
.columns-list {
    max-height: 220px;
    overflow: auto;
    padding: 2px 8px 8px;
}
.columns-list label {
    display: flex;
    align-items: center;
    gap: 7px;
    margin: 0;
    padding: 3px 0;
    font-size: 13px;
    color: #2f3d5b;
    font-weight: 500;
}
.columns-list input[type="checkbox"] {
    width: 14px;
    height: 14px;
}

/* Job card print UI (same as manufacturing-process drawer) */
.mp-jcp-backdrop {
    display: none !important;
}
/* Allow column popovers to paint past drawer padding; .mp-jcp-drawer uses overflow:hidden for radius */
#mpJobCardPrintDrawer {
    overflow: visible;
}
.mp-jcp-drawer {
    position: relative !important;
    top: auto !important;
    right: auto !important;
    width: 100% !important;
    max-width: 1180px;
    margin: 0 auto;
    flex: 1 1 auto;
    min-height: 0;
    max-height: 100%;
    height: auto !important;
    background: #f8fafc;
    z-index: 1;
    box-shadow: 0 4px 24px rgba(15, 23, 42, 0.08);
    transform: none !important;
    transition: none;
    display: flex;
    flex-direction: column;
    font-size: 13px;
    color: #1e293b;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    overflow: hidden;
}
.mp-jcp-drawer.open {
    transform: none !important;
}
.mp-jcp-drawer-head {
    flex: 0 0 auto;
    background: #fff;
    border-bottom: 1px solid #e2e8f0;
    padding: 14px 18px 12px;
}
.mp-jcp-drawer-head-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
}
.mp-jcp-drawer-head-top h2 {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 700;
    color: #0f172a;
}
.mp-jcp-drawer-close {
    display: none !important;
}
.mp-jcp-drawer-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
}
.mp-jcp-drawer-toolbar label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    color: #334155;
    margin: 0;
}
.mp-jcp-drawer-toolbar input[type="text"] {
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 6px 10px;
    min-width: 160px;
    font-size: 13px;
}
.mp-jcp-btn-load {
    background: #0f172a;
    color: #fff;
    border: none;
    border-radius: 6px;
    padding: 7px 16px;
    font-weight: 600;
    cursor: pointer;
    font-size: 13px;
}
.mp-jcp-btn-load:hover {
    filter: brightness(1.08);
}
.mp-jcp-btn-print {
    background: linear-gradient(180deg, #4f46e5 0%, #4338ca 100%);
    color: #fff;
    border: none;
    border-radius: 6px;
    padding: 7px 18px;
    font-weight: 600;
    cursor: pointer;
    font-size: 13px;
}
.mp-jcp-btn-print:hover {
    filter: brightness(1.05);
}
.mp-jcp-btn-export {
    background: #fff;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    padding: 7px 14px;
    font-weight: 600;
    cursor: pointer;
    font-size: 13px;
    color: #334155;
}
.mp-jcp-btn-export:hover {
    background: #f8fafc;
}
.mp-jcp-drawer-body {
    flex: 1 1 auto;
    min-height: 0;
    overflow: hidden;
    padding: 16px 18px 48px;
    display: flex;
    flex-direction: column;
}
.mp-jcp-print-grid {
    flex: 1 1 auto;
    min-height: 0;
    display: grid;
    grid-template-columns: minmax(220px, 260px) minmax(0, 1fr);
    gap: 18px;
    align-items: stretch;
}
@media (max-width: 900px) {
    .mp-jcp-print-grid {
        grid-template-columns: 1fr;
    }
}
.mp-jcp-side {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 16px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
    min-height: 0;
    overflow-y: auto;
}
.mp-jcp-side h3 {
    margin: 0 0 12px;
    font-size: 1.05rem;
    font-weight: 700;
    color: #0f172a;
}
.mp-jcp-side-dl {
    margin: 0 0 14px;
    font-size: 13px;
}
.mp-jcp-side-dl div {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    padding: 4px 0;
    border-bottom: 1px dashed #e2e8f0;
}
.mp-jcp-side-dl span:first-child {
    color: #64748b;
    font-weight: 600;
}
.mp-jcp-side-dl span:last-child {
    font-weight: 600;
    color: #0f172a;
    text-align: right;
}
.mp-jcp-barcode-wrap {
    text-align: center;
    margin: 12px 0;
    padding: 10px 8px;
    background: #fafafa;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}
.mp-jcp-barcode-wrap .mp-jcp-barcode-label {
    font-weight: 700;
    font-size: 14px;
    letter-spacing: 0.02em;
    margin-bottom: 6px;
    color: #312e81;
}
.mp-jcp-barcode-wrap svg {
    max-width: 100%;
    height: auto;
}
.mp-jcp-time-box {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 12px;
    padding: 12px;
    border: 1px solid #fbcfe8;
    border-radius: 8px;
    background: #fdf2f8;
    font-weight: 700;
    color: #9d174d;
}
.mp-jcp-time-box i {
    font-size: 22px;
    opacity: 0.85;
}
.mp-jcp-images {
    margin-top: 16px;
    padding-top: 12px;
    border-top: 1px solid #e2e8f0;
}
.mp-jcp-images strong {
    display: block;
    margin-bottom: 8px;
    color: #334155;
}
.mp-jcp-images img {
    max-width: 100%;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}
.mp-jcp-images .mp-jcp-img-empty {
    color: #94a3b8;
    font-style: italic;
    font-size: 12px;
}
.mp-jcp-main {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 16px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
    min-width: 0;
    min-height: 0;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.mp-jcp-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}
.mp-jcp-table th,
.mp-jcp-table td {
    border: 1px solid #e2e8f0;
    padding: 8px 7px;
    text-align: left;
    vertical-align: middle;
}
.mp-jcp-table th {
    background: #f1f5f9;
    font-weight: 700;
    color: #334155;
    white-space: nowrap;
}
.mp-jcp-table td.num,
.mp-jcp-table th.num {
    text-align: right;
}
.mp-jcp-table tbody tr:nth-child(even) {
    background: #fafbfc;
}
.mp-jcp-table tfoot td {
    font-weight: 700;
    background: #eef2ff;
}
.mp-jcp-table .mp-jcp-desc-link {
    color: #2563eb;
    font-weight: 600;
    cursor: default;
}
.mp-jcp-table .mp-jcp-dept-flow-txt {
    color: #4c1d95;
    font-weight: 600;
    white-space: nowrap;
}
.mp-jcp-summary-table tfoot td {
    background: #ecfdf5;
}
.mp-jcp-table th.col-hidden,
.mp-jcp-table td.col-hidden {
    display: none;
}
.mp-jcp-table-block {
    position: relative;
    margin-bottom: 0;
    flex: 1 1 0;
    min-height: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}
#mpJobCardPrintDrawer .mp-jcp-main > .mp-jcp-table-block:first-of-type {
    flex: 3 1 0;
}
#mpJobCardPrintDrawer .mp-jcp-main > .mp-jcp-table-block:last-of-type {
    flex: 2 1 0;
}
.mp-jcp-section-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 8px;
    flex: 0 0 auto;
    position: relative;
    z-index: 4;
}
.mp-jcp-section-head h3 {
    margin: 0;
}
.mp-jcp-table-scroll {
    flex: 1 1 auto;
    min-height: 0;
    max-height: none;
    overflow: auto;
    overscroll-behavior: contain;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
    -webkit-overflow-scrolling: touch;
    padding-bottom: 48px;
}
.mp-jcp-table-scroll--summary {
    flex: 1 1 0;
    min-height: 120px;
}
#mpJobCardPrintDrawer .table-responsive.mp-jcp-table-scroll {
    overflow: auto !important;
}
.mp-jcp-table-scroll .mp-jcp-table {
    width: max-content;
    min-width: 100%;
    margin-bottom: 0;
    table-layout: auto;
}
.mp-jcp-table-scroll .mp-jcp-table thead th {
    position: sticky;
    top: 0;
    z-index: 2;
    box-shadow: 0 1px 0 #e2e8f0;
}
#mpJobCardPrintDrawer .columns-panel.mp-jcp-columns-popover {
    width: min(280px, calc(100vw - 24px));
    max-width: 280px;
    border-radius: 8px;
    z-index: 1080;
    box-shadow: 0 8px 28px rgba(15, 23, 42, 0.18);
}
#mpJobCardPrintDrawer .columns-panel.mp-jcp-columns-popover .columns-list {
    max-height: min(280px, 42vh);
    overflow-x: hidden;
    overflow-y: auto;
}
/* tfoot not sticky: sticky bottom pinned totals mid-scroll; keep totals after all tbody rows */
.mp-jcp-print-sheet {
    display: none;
    position: absolute;
    left: -99999px;
    top: 0;
    width: 210mm;
    max-width: 100%;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 11px;
    color: #000;
    background: #fff;
}
.mp-jcp-print-sheet .jcp-doc {
    padding: 0;
}
.mp-jcp-print-sheet .jcp-h1 {
    font-size: 16px;
    font-weight: 700;
    text-align: center;
    margin: 0 0 10px;
    letter-spacing: 0.02em;
}
.mp-jcp-print-sheet table.jcp-head {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    margin-bottom: 0;
}
.mp-jcp-print-sheet table.jcp-head td,
.mp-jcp-print-sheet table.jcp-data td,
.mp-jcp-print-sheet table.jcp-data th {
    border: 1px solid #000;
    padding: 6px 8px;
    vertical-align: middle;
}
.mp-jcp-print-sheet table.jcp-head .jcp-photo {
    width: 22%;
    height: 110px;
    text-align: center;
    vertical-align: middle;
    background: #fafafa;
}
.mp-jcp-print-sheet table.jcp-head .jcp-photo img {
    max-width: 100%;
    max-height: 100px;
    object-fit: contain;
}
.mp-jcp-print-sheet table.jcp-head .jcp-lbl {
    width: 14%;
    font-weight: 700;
    background: #f8fafc;
}
.mp-jcp-print-sheet table.jcp-head .jcp-bc {
    width: 24%;
    text-align: center;
    vertical-align: middle;
}
.mp-jcp-print-sheet table.jcp-head .jcp-tag-num {
    font-size: 15px;
    font-weight: 700;
    margin-bottom: 6px;
}
.mp-jcp-print-sheet table.jcp-head .jcp-bc svg {
    max-width: 100%;
    height: 44px;
}
.mp-jcp-print-sheet .jcp-desc-bar td {
    font-weight: 600;
}
.mp-jcp-print-sheet .jcp-thumbs {
    display: flex;
    gap: 8px;
    margin: 10px 0 12px;
}
.mp-jcp-print-sheet .jcp-thumbs .jcp-thumb {
    flex: 1;
    min-height: 72px;
    border: 1px solid #000;
    background: #fff;
}
.mp-jcp-print-sheet table.jcp-data {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 12px;
}
.mp-jcp-print-sheet table.jcp-data thead th {
    background: #d9edf7;
    font-weight: 700;
    text-align: left;
    font-size: 10px;
}
.mp-jcp-print-sheet table.jcp-data .num {
    text-align: right;
}
.mp-jcp-print-sheet table.jcp-data td.jcp-flow {
    color: #4c1d95;
    font-weight: 600;
    white-space: normal;
    word-break: break-word;
    max-width: 220px;
}
.mp-jcp-print-sheet .jcp-sigs {
    display: table;
    width: 100%;
    margin-top: 20px;
    table-layout: fixed;
}
.mp-jcp-print-sheet .jcp-sigs > div {
    display: table-cell;
    text-align: center;
    padding: 8px 12px;
    font-size: 11px;
}
.mp-jcp-print-sheet .jcp-sigs .jcp-line {
    border-bottom: 1px solid #000;
    margin: 24px 8px 4px;
    min-height: 1px;
}
@media print {
    @page {
        margin: 10mm;
        size: A4;
    }
    body * {
        visibility: hidden !important;
    }
    #mpJcpPrintSheet,
    #mpJcpPrintSheet * {
        visibility: visible !important;
    }
    #mpJcpPrintSheet {
        display: block !important;
        position: absolute;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        background: #fff !important;
    }
    .mp-jcp-drawer,
    .layout-content,
    .sidebar,
    .sidenav,
    .layout-navbar,
    .layout-footer {
        display: none !important;
    }
}
</style>

<body class="default-style mfg-page job-card-print-page">
<?php include 'sidebar.php'; ?>

<div class="layout-content">
    <div class="container-fluid flex-grow-1 jcp-page-wrap">
        <div class="mp-jcp-drawer open" id="mpJobCardPrintDrawer" role="main" aria-labelledby="mpJcpTitle">
            <div class="mp-jcp-drawer-head">
                <div class="mp-jcp-drawer-head-top">
                    <h2 id="mpJcpTitle">Job Card Print</h2>
                </div>
                <div class="mp-jcp-drawer-toolbar">
                    <label>Tag No <input type="text" id="mpJcpTagInput" placeholder="Tag No" autocomplete="off"></label>
                    <button type="button" class="mp-jcp-btn-load" id="mpJcpLoadTagBtn">Load</button>
                    <button type="button" class="mp-jcp-btn-print" id="mpJcpPrintBtn">Print</button>
                    <button type="button" class="mp-jcp-btn-export" id="mpJcpExportBtn">Export Excel</button>
                </div>
            </div>
            <div class="mp-jcp-drawer-body" id="mpJcpPrintRoot">
                <div class="mp-jcp-print-grid">
                    <div class="mp-jcp-side">
                        <h3 id="mpJcpCustomerName">—</h3>
                        <div class="mp-jcp-side-dl">
                            <div><span>Date</span><span id="mpJcpOrderDate">—</span></div>
                            <div><span>Due Date</span><span id="mpJcpDueDate">—</span></div>
                            <div><span>Reference No.</span><span id="mpJcpRefNo">—</span></div>
                        </div>
                        <div class="mp-jcp-barcode-wrap" id="mpJcpBarcodeBlock">
                            <div class="mp-jcp-barcode-label" id="mpJcpBarcodeText">—</div>
                            <svg id="mpJcpBarcodeSvg" xmlns="http://www.w3.org/2000/svg"></svg>
                        </div>
                        <div class="mp-jcp-time-box">
                            <i class="feather icon-clock"></i>
                            <div><div style="font-size:11px;font-weight:600;opacity:.9;">Total Time Spent</div><div id="mpJcpTimeSpent">0H 00M 00S</div></div>
                        </div>
                        <div class="mp-jcp-images">
                            <strong>Images</strong>
                            <div id="mpJcpImagesMount"><span class="mp-jcp-img-empty">No Images To Display!</span></div>
                        </div>
                    </div>
                    <div class="mp-jcp-main">
                        <div class="mp-jcp-table-block">
                            <div class="mp-jcp-section-head">
                                <h3>Job Queue History</h3>
                                <button type="button" class="head-setting-btn mp-jcp-history-cols-toggle" id="mpJcpHistoryColsToggle" title="Columns" aria-expanded="false" aria-controls="mpJcpHistoryColumnsPanel">
                                    <i class="feather icon-settings mini-gear"></i>
                                </button>
                                <div class="columns-panel mp-jcp-columns-popover" id="mpJcpHistoryColumnsPanel" role="dialog" aria-label="Job queue history columns">
                                    <div class="columns-panel-header">
                                        <span class="icons"><span class="tag">X</span><span class="tag">P</span><i class="feather icon-settings"></i> Columns</span>
                                        <button type="button" class="columns-panel-close" data-close-panel="mpJcpHistoryColumnsPanel" aria-label="Close">&times;</button>
                                    </div>
                                    <div class="columns-search">
                                        <input type="text" id="mpJcpHistoryColumnsSearch" placeholder="Search" autocomplete="off">
                                    </div>
                                    <div class="columns-list" id="mpJcpHistoryColumnsList"></div>
                                </div>
                            </div>
                            <div class="table-responsive mp-jcp-table-scroll">
                                <table class="mp-jcp-table" id="mpJcpHistoryTable">
                                    <thead id="mpJcpHistoryHead"></thead>
                                    <tbody id="mpJcpHistoryBody"></tbody>
                                    <tfoot id="mpJcpHistoryFoot"></tfoot>
                                </table>
                            </div>
                        </div>
                        <div class="mp-jcp-table-block">
                            <div class="mp-jcp-section-head">
                                <h3>Summary</h3>
                                <button type="button" class="head-setting-btn mp-jcp-summary-cols-toggle" id="mpJcpSummaryColsToggle" title="Columns" aria-expanded="false" aria-controls="mpJcpSummaryColumnsPanel">
                                    <i class="feather icon-settings mini-gear"></i>
                                </button>
                                <div class="columns-panel mp-jcp-columns-popover" id="mpJcpSummaryColumnsPanel" role="dialog" aria-label="Summary columns">
                                    <div class="columns-panel-header">
                                        <span class="icons"><span class="tag">X</span><span class="tag">P</span><i class="feather icon-settings"></i> Columns</span>
                                        <button type="button" class="columns-panel-close" data-close-panel="mpJcpSummaryColumnsPanel" aria-label="Close">&times;</button>
                                    </div>
                                    <div class="columns-search">
                                        <input type="text" id="mpJcpSummaryColumnsSearch" placeholder="Search" autocomplete="off">
                                    </div>
                                    <div class="columns-list" id="mpJcpSummaryColumnsList"></div>
                                </div>
                            </div>
                            <div class="table-responsive mp-jcp-table-scroll mp-jcp-table-scroll--summary">
                                <table class="mp-jcp-table mp-jcp-summary-table" id="mpJcpSummaryTable">
                                    <thead id="mpJcpSummaryHead"></thead>
                                    <tbody id="mpJcpSummaryBody"></tbody>
                                    <tfoot id="mpJcpSummaryFoot"></tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="mpJcpPrintSheet" class="mp-jcp-print-sheet" aria-hidden="true"></div>
    </div>
</div>

<script>
window.MP_JCP_HISTORY_COLUMNS = [
    { key: 'active', label: 'active' },
    { key: 'date', label: 'Date' },
    { key: 'sr_no', label: 'Sr No' },
    { key: 'description', label: 'Description' },
    { key: 'qty', label: 'Qty' },
    { key: 'gross_wt', label: 'Gross Wt' },
    { key: 'other_wt', label: 'Other Wt' },
    { key: 'loss_wt', label: 'Loss Wt' },
    { key: 'profit_wt', label: 'Profit Wt' },
    { key: 'gold_wt', label: 'Gold Wt' },
    { key: 'diamond_wt', label: 'Diamond Wt' },
    { key: 'spent_time', label: 'Spent Time' },
    { key: 'price', label: 'Price' },
    { key: 'metal_wt', label: 'Metal Wt' },
    { key: 'dept_flow', label: 'Department Flow' },
    { key: 'changed_wt', label: 'Changed Weight' },
    { key: 'is_add_weight', label: 'Is Add Weight' },
    { key: 'is_return_weight', label: 'Is Return Weight' }
];
window.MP_JCP_SUMMARY_COLUMNS = [
    { key: 'department', label: 'Department' },
    { key: 'issue_wt', label: 'Issue Weight' },
    { key: 'return_wt', label: 'Return Weight' },
    { key: 'actual_loss', label: 'Actual Loss' },
    { key: 'spent_time', label: 'Spent Time' }
];
</script>

<?php include 'footer-script.php'; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.5/JsBarcode.all.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="assets/js/job-card-print-page.js"></script>
</body>
</html>
