<?php
session_start();
require_once 'config.php';
require_once __DIR__ . '/includes/account_ledger_list_data.php';

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = isset($_GET['per_page']) ? max(10, min(100, (int)$_GET['per_page'])) : 25;
$offset = ($page - 1) * $per_page;

$listData = auragold_account_ledger_fetch_rows($conn, $_GET);
$filters = $listData['filters'];
$filter_group = $filters['filter_group'];
$filter_search = $filters['filter_search'];
$filter_branch = $filters['filter_branch'];
$pagination_branch_extra = $filters['pagination_branch_extra'];
$ledger_has_branch = $listData['ledger_has_branch'];
$account_ledger_branches = $listData['account_ledger_branches'];
$branch_id_to_label = $listData['branch_id_to_label'];

$active_filters = 0;
if ($filter_group !== '') {
    $active_filters++;
}
if ($filter_search !== '') {
    $active_filters++;
}
if ($filter_branch > 0) {
    $active_filters++;
}

$all_filtered = $listData['rows'];
$grand_total = (float) $listData['grand_total'];
$total_records = count($all_filtered);
$total_pages = $total_records > 0 ? ceil($total_records / $per_page) : 1;
$ledger_rows = array_slice($all_filtered, $offset, $per_page);
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Account Ledger - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?> Software</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
<?php include 'header-script.php';?>
</head>

<style>
html, body {
    overflow-x: hidden !important;
    height: 100vh;
    background: #f4f6fb;
    /* font-family: 'Segoe UI', Arial, sans-serif; */
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
    padding: 0;
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
    position: relative;
}

.page-header-actions .btn-icon:hover {
    background: rgba(255,255,255,0.3);
}

.page-header-actions .badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #dc2626;
    color: #fff;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    font-size: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
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
    padding: 4px 10px;
    color: #64748b;
    text-decoration: none;
    border-bottom: 2px solid #c5a864;
    transition: all 0.2s;
    font-weight: 500;
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

/* Toolbar */
.toolbar {
    background: #fff;
    padding: 12px 20px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.toolbar-left {
    display: flex;
    gap: 10px;
    align-items: center;
}

.toolbar-right {
    display: flex;
    gap: 10px;
    align-items: center;
}

.btn-filter {
    background: #fff;
    border: 1px solid #e2e8f0;
    color: #64748b;
    padding: 6px 12px;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
}

.btn-filter:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
}

.btn-export {
    background: #11294b;
    border: none;
    color: #fff;
    padding: 6px 12px;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
}

.btn-export:hover {
    background: #4a2b7c;
}

.btn-import {
    background: #fff;
    border: 1px solid #11294b;
    color: #11294b;
    padding: 6px 12px;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
}

.btn-import:hover {
    background: #f8fafc;
}

.import-modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.import-modal.active {
    display: flex;
}

.import-modal-content {
    background: #fff;
    border-radius: 8px;
    width: 90%;
    max-width: 520px;
    overflow: hidden;
}

.import-modal-header {
    background: #11294b;
    color: #fff;
    padding: 14px 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.import-modal-body {
    padding: 18px;
}

.import-hint {
    font-size: 12px;
    color: #64748b;
    margin-bottom: 12px;
    line-height: 1.5;
}

/* Table Container */
.table-container {
    flex: 1;
    overflow: auto;
    background: #fff;
    margin: 4px;
    border-radius: 8px 8px 0 0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.table {
    width: 100%;
    margin: 0;
    font-size: 12px;
}

.table thead th {
    background: #f1edff !important;
    font-weight: 600;
    color: #4d5673;
    padding: 12px;
    border-bottom: 2px solid #e2e8f0;
    position: sticky;
    top: 0;
    z-index: 10;
}

.table tbody td {
    padding: 12px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: middle;
}

.table tbody tr:hover {
    background: #f8fafc;
}

.table tbody tr.total-row {
    background: #f1edff;
    font-weight: 600;
}

.table tbody tr.total-row td {
    border-top: 2px solid #e2e8f0;
    border-bottom: 2px solid #e2e8f0;
}

.btn-view-all {
    background: #11294b;
    color: #fff;
    border: none;
    padding: 4px 12px;
    border-radius: 4px;
    font-size: 12px;
    cursor: pointer;
    font-weight: 500;
}

.btn-view-all:hover {
    background: #4a2b7c;
}

.crdr-badge {
    display: inline-block;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
}

.crdr-badge.dr {
    background: #fee2e2;
    color: #dc2626;
}

.crdr-badge.cr {
    background: #dbeafe;
    color: #2563eb;
}

/* Total Row in Footer */
.table-footer-total {
    background: #f1edff;
    font-weight: 600;
    border-top: 2px solid #e2e8f0;
}

.table-footer-total td {
    padding: 12px;
    border-bottom: 2px solid #e2e8f0;
}

/* Pagination */
.pagination-container {
    background: #fff;
    padding: 12px 20px;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 0 20px 20px 20px;
    border-radius: 0 0 8px 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.pagination-right {
    display: flex;
    gap: 10px;
    align-items: center;
}

.per-page-dropdown {
    position: relative;
}

.per-page-dropdown select {
    padding: 6px 30px 6px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    font-size: 12px;
    color: #64748b;
    background: #fff;
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%2364748b' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    padding-right: 35px;
}

.per-page-dropdown select:hover {
    border-color: #cbd5e1;
}

.pagination-info {
    color: #64748b;
    font-size: 12px;
}

.pagination {
    display: flex;
    gap: 5px;
    align-items: center;
}

.pagination .page-link {
    padding: 6px 12px;
    border: 1px solid #e2e8f0;
    color: #64748b;
    text-decoration: none;
    border-radius: 4px;
    font-size: 12px;
}

.pagination .page-link:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
}

.pagination .page-link.active {
    background: #11294b;
    color: #fff;
    border-color: #11294b;
}

.pagination .page-link.disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Filter Modal */
.filter-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.filter-modal.active {
    display: flex;
}

.filter-modal-content {
    background: #fff;
    border-radius: 8px;
    padding: 0;
    width: 90%;
    max-width: 800px;
    max-height: 90vh;
    overflow: auto;
}

.filter-modal-header {
    background: #11294b;
    color: #fff;
    padding: 15px 20px;
    border-radius: 8px 8px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0;
    border-bottom: none;
}

.filter-modal-header h5 {
    margin: 0;
    color: #fff;
    font-weight: 600;
}

.filter-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    color: #fff;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.filter-modal-close:hover {
    color: #f0f0f0;
}

.filter-modal-body {
    padding: 20px;
}

.filter-form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 15px;
}

.filter-form-group.full-width {
    grid-column: 1 / -1;
}

.date-range-input {
    position: relative;
}

.date-range-input input {
    padding-right: 60px;
}

.date-range-icons {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    display: flex;
    gap: 5px;
}

.date-range-icons i {
    color: #64748b;
    cursor: pointer;
    font-size: 16px;
}

.date-range-icons i:hover {
    color: #11294b;
}


.filter-form-group {
    margin-bottom: 15px;
}

.filter-form-group label {
    display: block;
    margin-bottom: 5px;
    color: #ffffff;
    font-weight: 500;
    font-size: 12px;
}

.filter-form-group input,
.filter-form-group select {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 12px;
}

.filter-ledger-ac-wrap {
    position: relative;
}
.filter-ledger-ac-panel {
    position: absolute;
    left: 0;
    right: 0;
    top: calc(100% + 2px);
    z-index: 40;
    max-height: 220px;
    overflow-y: auto;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
}
.filter-ledger-ac-panel[hidden] {
    display: none !important;
}
.filter-ledger-ac-item {
    display: block;
    width: 100%;
    text-align: left;
    border: 0;
    background: transparent;
    padding: 8px 12px;
    font-size: 12px;
    color: #11294b;
    cursor: pointer;
}
.filter-ledger-ac-item:hover,
.filter-ledger-ac-item.is-active {
    background: rgba(197, 168, 100, 0.22);
}
.filter-ledger-ac-empty,
.filter-ledger-ac-loading {
    padding: 10px 12px;
    font-size: 12px;
    color: #64748b;
}

.filter-modal-footer {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #e2e8f0;
}

.btn-cancel {
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    color: #64748b;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
}

.btn-apply {
    background: linear-gradient(135deg, #11294b 0%, #7c5ba8 100%);
    border: none;
    color: #fff;
    padding: 8px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
}

.btn-apply:hover {
    background: linear-gradient(135deg, #4a2b7c 0%, #6c4b98 100%);
}

.btn-clear {
    background: #fff;
    border: 1px solid #ec4899;
    color: #ec4899;
    padding: 8px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
}

.btn-clear:hover {
    background: #fdf2f8;
}

/* Transaction list (Jewelstep-style cards) */
.transaction-list-container {
    margin: 0 20px 0 20px;
    padding: 0;
}
.transaction-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 16px 0;
}
.transaction-card {
    display: flex;
    align-items: stretch;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 16px 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    transition: box-shadow 0.2s;
}
.transaction-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}
.transaction-card-left {
    min-width: 180px;
    padding-right: 20px;
    border-right: 1px solid #e2e8f0;
}
.voucher-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.02em;
    margin-bottom: 8px;
}
.voucher-purchase_invoice { background: #dbeafe; color: #1e40af; }
.voucher-sale_invoice { background: #d1fae5; color: #065f46; }
.voucher-sale_return { background: #fef3c7; color: #92400e; }
.voucher-purchase_return { background: #fce7f3; color: #9d174d; }
.voucher-sale_quotation { background: #e0e7ff; color: #3730a3; }
.voucher-purchase_quotation { background: #f3e8ff; color: #6b21a8; }
.voucher-sale_fixing_direct { background: #fef9c3; color: #854d0e; }
.transaction-card-left .voucher-no { font-size: 11px; color: #64748b; margin-bottom: 2px; }
.transaction-card-left .voucher-no strong { color: #1e293b; }
.transaction-card-left .branch-name { font-size: 12px; color: #94a3b8; }
.transaction-card-center {
    flex: 1;
    padding: 0 24px;
    min-width: 160px;
}
.transaction-card-center .party-name { font-weight: 600; color: #1e293b; margin-bottom: 6px; font-size: 12px; }
.transaction-card-center .party-meta { font-size: 12px; color: #94a3b8; margin-bottom: 2px; }
.transaction-card-center .party-meta i { margin-right: 6px; font-size: 12px; }
.transaction-card-right {
    text-align: right;
    min-width: 200px;
}
.transaction-card-right .company-ref { font-size: 12px; color: #64748b; margin-bottom: 4px; }
.transaction-card-right .trans-date { font-size: 11px; color: #ffffff; margin-bottom: 8px; }
.trans-amount-row { margin-bottom: 10px; }
.transaction-card-right .trans-amount { display: block; font-size: 12px; color: #64748b; }
.transaction-card-right .amount-value { color: #2563eb; font-size: 16px; }
.transaction-card-right .trans-balance { display: block; font-size: 12px; color: #64748b; }
.transaction-card-right .trans-balance strong { color: #1e293b; }
.transaction-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 8px; }
.action-icon {
    width: 32px; height: 32px;
    display: inline-flex; align-items: center; justify-content: center;
    border: 1px solid #e2e8f0; border-radius: 6px;
    color: #64748b; text-decoration: none;
    transition: all 0.2s;
}
.action-icon:hover { background: #f1f5f9; color: #11294b; border-color: #c4b5fd; }
.action-icon.btn-delete-transaction { border: none; cursor: pointer; background: transparent; font-size: inherit; }
.action-icon.btn-delete-transaction:hover { background: #fef2f2; color: #dc2626; border-color: #fecaca; }
.no-transactions { text-align: center; padding: 48px 20px; color: #64748b; font-size: 15px; }
</style>

<body>
<?php include 'sidebar.php'; ?>

<div class="layout-content">
<div class="container-fluid flex-grow-1" style="padding-top:0;padding-bottom:0;">

<!-- Page Header -->
<div class="page-header-bar">
    <span>Account Ledger</span>
    <div class="page-header-actions"></div>
</div>

<!-- Toolbar -->
<div class="toolbar">
    <div class="toolbar-left">
        <div class="dropdown">
            <button class="btn btn-sm btn-primary dropdown-toggle" type="button" id="addLedgerDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="background:#11294b;border-color:#11294b;">
                <i class="feather icon-plus"></i> Add
            </button>
            <div class="dropdown-menu" aria-labelledby="addLedgerDropdown">
                <a class="dropdown-item" href="ledger-opening.php"><i class="feather icon-user"></i> New Ledger</a>
                <a class="dropdown-item" href="#" onclick="alert('Add Group – integrate with your form'); return false;"><i class="feather icon-folder"></i> New Group</a>
            </div>
        </div>
        <button type="button" class="btn-filter" id="btnFilter" title="Filter">
            <i class="feather icon-filter"></i> Filter
            <?php if ($active_filters > 0): ?>
            <span class="badge"><?php echo $active_filters; ?></span>
            <?php endif; ?>
        </button>
        <button type="button" class="btn-icon" title="Columns / Display" style="background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0;">
            <i class="feather icon-sliders"></i>
        </button>
        <div class="dropdown">
            <button class="btn-export dropdown-toggle" type="button" id="exportDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" style="display:inline-flex;">
                <i class="feather icon-download"></i> Export
            </button>
            <div class="dropdown-menu" aria-labelledby="exportDropdown">
                <a class="dropdown-item" href="#" onclick="exportTable('csv'); return false;">CSV</a>
                <a class="dropdown-item" href="#" onclick="exportTable('excel'); return false;">Excel</a>
                <a class="dropdown-item" href="#" onclick="exportTable('pdf'); return false;">PDF</a>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="ajax/download-account-ledger-excel-sample.php" title="Download sample Excel with opening balance + metal columns and Sundry Debtors dropdown">
                    <i class="feather icon-download mr-1"></i> Sample Excel
                </a>
            </div>
        </div>
        <button type="button" class="btn-import" id="btnImportLedger" title="Import from Excel">
            <i class="feather icon-upload"></i> Import
        </button>
        <a class="btn-import" href="ajax/download-account-ledger-excel-sample.php" style="text-decoration:none;" title="Download sample Excel for import (opening balances + metals)">
            <i class="feather icon-file-text"></i> Template
        </a>
        <button type="button" class="btn-icon" title="Settings" style="background:#f1f5f9;color:#64748b;border:1px solid #e2e8f0;">
            <i class="feather icon-settings"></i>
        </button>
    </div>
    <div class="toolbar-right"></div>
</div>

<!-- Table Container -->
<div class="table-container">
    <table class="table" id="accountLedgerTable">
        <thead>
            <tr>
                <th>Sr No</th>
                <th>Ledger</th>
                <th>Contact</th>
                <th>Group</th>
                <th>Branch Name</th>
                <th>Opening Balance</th>
                <th>Cr/Dr</th>
                <th style="width:60px;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $sr = $offset + 1;
            foreach ($ledger_rows as $row):
                $ob_display = number_format((float)$row['opening_balance'], 3, '.', '');
            ?>
            <tr>
                <td><?php echo $sr++; ?></td>
                <td><?php echo htmlspecialchars($row['ledger_name']); ?></td>
                <td><?php echo htmlspecialchars($row['contact']); ?></td>
                <td><?php echo htmlspecialchars($row['group_name']); ?></td>
                <td><?php echo htmlspecialchars($row['branch_name']); ?></td>
                <td><?php echo $ob_display; ?></td>
                <td><span class="crdr-badge <?php echo $row['crdr'] === 'Dr' ? 'dr' : 'cr'; ?>"><?php echo $row['crdr']; ?></span></td>
                <td>
                    <?php if (!empty($row['customer_id'])): ?>
                    <a href="ledger-opening.php?id=<?php echo (int) $row['customer_id']; ?><?php echo $filter_branch > 0 ? '&branch_id=' . (int) $filter_branch : ''; ?>" class="btn btn-sm p-0 border-0 text-primary mr-1" title="Edit">
                        <i class="feather icon-edit-2" style="font-size:14px;"></i>
                    </a>
                    <?php endif; ?>
                    <?php if (empty($row['is_fixed'])): ?>
                    <button type="button" class="btn btn-sm p-0 border-0 text-danger btn-delete-ledger" title="Delete" data-ledger="<?php echo htmlspecialchars($row['ledger_name']); ?>" data-customer-id="<?php echo (int) ($row['customer_id'] ?? 0); ?>">
                        <i class="feather icon-trash-2" style="font-size:14px;"></i>
                    </button>
                    <?php else: ?>
                    <span class="text-muted" title="Fixed system ledger — cannot delete" style="font-size:11px;cursor:default;">Fixed</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($ledger_rows)): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">No ledger accounts found.</td></tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr class="table-footer-total total-row">
                <td colspan="5" class="text-right font-weight-bold">Grand Total</td>
                <td class="font-weight-bold"><?php echo number_format(abs($grand_total), 3, '.', ''); ?></td>
                <td><span class="crdr-badge <?php echo $grand_total >= 0 ? 'dr' : 'cr'; ?>"><?php echo $grand_total >= 0 ? 'Dr' : 'Cr'; ?></span></td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>

<!-- Pagination -->
<div class="pagination-container">
    <div class="pagination-info">
        Showing <?php echo $total_records === 0 ? 0 : $offset + 1; ?> to <?php echo min($offset + count($ledger_rows), $total_records); ?> of <?php echo $total_records; ?> entries
    </div>
    <div class="pagination-right">
        <div class="per-page-dropdown">
            <select id="perPageSelect" onchange="changePerPage(this.value)">
                <option value="10" <?php echo $per_page === 10 ? 'selected' : ''; ?>>10</option>
                <option value="25" <?php echo $per_page === 25 ? 'selected' : ''; ?>>25</option>
                <option value="50" <?php echo $per_page === 50 ? 'selected' : ''; ?>>50</option>
                <option value="100" <?php echo $per_page === 100 ? 'selected' : ''; ?>>100</option>
            </select>
            <span style="margin-left:6px;font-size:12px;color:#64748b;">Items</span>
        </div>
        <nav class="pagination">
            <?php
            $q = $_GET;
            unset($q['page']);
            $q = array_merge($q, $pagination_branch_extra);
            $base_q = http_build_query($q);
            $base_url = 'account-ledger.php' . ($base_q ? '?' . $base_q . '&' : '?');
            $page_param = 'page=';
            ?>
            <a class="page-link <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="<?php echo $page <= 1 ? '#' : $base_url . $page_param . '1'; ?>">&laquo;</a>
            <a class="page-link <?php echo $page <= 1 ? 'disabled' : ''; ?>" href="<?php echo $page <= 1 ? '#' : $base_url . $page_param . ($page - 1); ?>">&lsaquo;</a>
            <?php
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);
            for ($i = $start; $i <= $end; $i++):
            ?>
            <a class="page-link <?php echo $i === $page ? 'active' : ''; ?>" href="<?php echo $base_url . $page_param . $i; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
            <a class="page-link <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" href="<?php echo $page >= $total_pages ? '#' : $base_url . $page_param . ($page + 1); ?>">&rsaquo;</a>
            <a class="page-link <?php echo $page >= $total_pages ? 'disabled' : ''; ?>" href="<?php echo $page >= $total_pages ? '#' : $base_url . $page_param . $total_pages; ?>">&raquo;</a>
        </nav>
    </div>
</div>

</div>
</div>

<!-- Filter Modal -->
<div class="filter-modal" id="filterModal">
    <div class="filter-modal-content">
        <div class="filter-modal-header">
            <h5>Filter Ledgers</h5>
            <button type="button" class="filter-modal-close" id="filterModalClose">&times;</button>
        </div>
        <div class="filter-modal-body">
            <form method="get" action="account-ledger.php" id="filterForm">
                <input type="hidden" name="per_page" value="<?php echo (int)$per_page; ?>">
                <div class="filter-form-row">
                    <div class="filter-form-group">
                        <label style="color:#1e293b;">Search Ledger</label>
                        <div class="filter-ledger-ac-wrap">
                            <input type="text" name="search" id="filterLedgerSearch" class="form-control" value="<?php echo htmlspecialchars($filter_search); ?>" placeholder="Type to search ledger…" autocomplete="off" aria-autocomplete="list" aria-expanded="false" aria-controls="filterLedgerList" role="combobox">
                            <div class="filter-ledger-ac-panel" id="filterLedgerPanel" hidden>
                                <div id="filterLedgerList" role="listbox"></div>
                            </div>
                        </div>
                    </div>
                    <div class="filter-form-group">
                        <label style="color:#1e293b;">Group</label>
                        <select name="group" class="form-control">
                            <option value="">All Groups</option>
                            <?php
                            $filter_groups = ['Cash-in Hand', 'Primary', 'Sundry Debtors', 'Sundry Creditors', 'Loans & Advances(Asset)', 'Indirect Expenses', 'Service Account', 'Current Liabilities', 'Current Assets', 'Indirect Income', 'Bank Accounts', 'Sales', 'Purchase'];
                            foreach ($filter_groups as $gname):
                            ?>
                            <option value="<?php echo htmlspecialchars($gname); ?>" <?php echo $filter_group === $gname ? 'selected' : ''; ?>><?php echo htmlspecialchars($gname); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-form-group">
                        <label style="color:#1e293b;">Branch (opening)</label>
                        <select name="branch_id" class="form-control">
                            <option value="0" <?php echo $filter_branch === 0 ? 'selected' : ''; ?>>All branches</option>
                            <?php foreach ($account_ledger_branches as $abr): ?>
                                <?php $abid = (int) ($abr['id'] ?? 0); ?>
                                <option value="<?php echo $abid; ?>" <?php echo ($filter_branch === $abid) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) ($abr['name'] ?? '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="filter-modal-footer">
                    <button type="button" class="btn-cancel" id="filterCancel">Cancel</button>
                    <div style="display:flex;gap:8px;">
                        <button type="button" class="btn-clear" onclick="window.location.href='account-ledger.php';">Clear</button>
                        <button type="submit" class="btn-apply">Apply</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Import Modal -->
<div class="import-modal" id="importModal">
    <div class="import-modal-content">
        <div class="import-modal-header">
            <strong>Import Account Ledgers</strong>
            <button type="button" class="filter-modal-close" id="importModalClose">&times;</button>
        </div>
        <div class="import-modal-body">
            <p class="import-hint">
                Upload Excel (.xlsx / .xls) using the
                <a href="ajax/download-account-ledger-excel-sample.php">Sample Excel</a>
                template. Columns match <strong>Ledger Opening</strong>:
                <strong>Ledger</strong>, Contact, <strong>Sundry Debtors</strong>, Branch Name, Opening Balance, Cr/Dr,
                and metal opening columns (Gold / Silver / Platinum / Diamond Opening gm + Cr/Dr).
                Existing ledgers are updated; new names create a ledger with opening balance.
                System ledgers (Cash, Bank Account, Sales Account, etc.) are marked <strong>Fixed</strong> and cannot be deleted.
            </p>
            <form id="importLedgerForm" enctype="multipart/form-data">
                <div class="filter-form-group">
                    <label style="color:#1e293b;">Excel file</label>
                    <input type="file" name="excel_file" id="importLedgerFile" class="form-control" accept=".xlsx,.xls" required>
                </div>
                <?php if ($filter_branch > 0): ?>
                <input type="hidden" name="branch_id" value="<?php echo (int) $filter_branch; ?>">
                <?php endif; ?>
                <div class="filter-modal-footer" style="margin-top:16px;padding-top:0;border-top:none;">
                    <button type="button" class="btn-cancel" id="importCancel">Cancel</button>
                    <button type="submit" class="btn-apply" id="importSubmitBtn">Upload &amp; Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('btnFilter').onclick = function() { document.getElementById('filterModal').classList.add('active'); };
document.getElementById('filterModalClose').onclick = function() { document.getElementById('filterModal').classList.remove('active'); };
document.getElementById('filterCancel').onclick = function() { document.getElementById('filterModal').classList.remove('active'); };
document.getElementById('filterModal').onclick = function(e) { if (e.target === this) this.classList.remove('active'); };

(function () {
    var inp = document.getElementById('filterLedgerSearch');
    var panel = document.getElementById('filterLedgerPanel');
    var list = document.getElementById('filterLedgerList');
    if (!inp || !panel || !list) return;

    var timer = null;
    var abortCtrl = null;
    var activeIdx = -1;

    function hidePanel() {
        panel.hidden = true;
        inp.setAttribute('aria-expanded', 'false');
        activeIdx = -1;
    }

    function showPanel() {
        panel.hidden = false;
        inp.setAttribute('aria-expanded', 'true');
    }

    function setActive(idx) {
        var items = list.querySelectorAll('.filter-ledger-ac-item');
        activeIdx = idx;
        items.forEach(function (el, i) {
            el.classList.toggle('is-active', i === idx);
        });
        if (idx >= 0 && items[idx] && typeof items[idx].scrollIntoView === 'function') {
            items[idx].scrollIntoView({ block: 'nearest' });
        }
    }

    function render(rows) {
        list.innerHTML = '';
        activeIdx = -1;
        if (!rows || !rows.length) {
            list.innerHTML = '<div class="filter-ledger-ac-empty">No ledger found</div>';
            showPanel();
            return;
        }
        rows.forEach(function (row) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'filter-ledger-ac-item';
            btn.setAttribute('role', 'option');
            btn.textContent = row.display_text || row.name || '';
            btn.addEventListener('mousedown', function (e) {
                e.preventDefault();
                inp.value = row.name || '';
                hidePanel();
            });
            list.appendChild(btn);
        });
        showPanel();
    }

    function fetchSuggestions(q) {
        if (abortCtrl) {
            try { abortCtrl.abort(); } catch (e) {}
        }
        if ((q || '').trim().length < 1) {
            hidePanel();
            return;
        }
        list.innerHTML = '<div class="filter-ledger-ac-loading">Searching…</div>';
        showPanel();
        abortCtrl = (typeof AbortController !== 'undefined') ? new AbortController() : null;
        var opts = { credentials: 'same-origin' };
        if (abortCtrl) opts.signal = abortCtrl.signal;
        fetch('ajax/search-ledger-accounts.php?q=' + encodeURIComponent(q.trim()), opts)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.status === 'success' && Array.isArray(data.ledgers)) {
                    render(data.ledgers);
                } else {
                    render([]);
                }
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') return;
                render([]);
            });
    }

    inp.addEventListener('input', function () {
        clearTimeout(timer);
        var v = inp.value || '';
        if (v.trim().length < 1) {
            hidePanel();
            return;
        }
        timer = setTimeout(function () { fetchSuggestions(v); }, 250);
    });

    inp.addEventListener('keydown', function (e) {
        var items = list.querySelectorAll('.filter-ledger-ac-item');
        if (e.key === 'Escape') {
            hidePanel();
            return;
        }
        if (panel.hidden || !items.length) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActive(activeIdx < items.length - 1 ? activeIdx + 1 : 0);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActive(activeIdx > 0 ? activeIdx - 1 : items.length - 1);
        } else if (e.key === 'Enter' && activeIdx >= 0 && items[activeIdx]) {
            e.preventDefault();
            items[activeIdx].dispatchEvent(new Event('mousedown'));
        }
    });

    inp.addEventListener('blur', function () {
        setTimeout(hidePanel, 150);
    });

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.filter-ledger-ac-wrap')) {
            hidePanel();
        }
    });
})();

function changePerPage(val) {
    var u = new URL(window.location.href);
    u.searchParams.set('per_page', val);
    u.searchParams.set('page', '1');
    window.location.href = u.toString();
}

function exportTable(format) {
    var cur = new URL(window.location.href);
    var params = new URLSearchParams();
    cur.searchParams.forEach(function(val, key) {
        if (key !== 'page' && key !== 'per_page') {
            params.set(key, val);
        }
    });
    params.set('format', format);
    window.location.href = 'ajax/export-account-ledger-list.php?' + params.toString();
}

function openImportModal() {
    document.getElementById('importModal').classList.add('active');
}
function closeImportModal() {
    document.getElementById('importModal').classList.remove('active');
}

document.getElementById('btnImportLedger').onclick = openImportModal;
document.getElementById('importModalClose').onclick = closeImportModal;
document.getElementById('importCancel').onclick = closeImportModal;
document.getElementById('importModal').onclick = function(e) {
    if (e.target === this) closeImportModal();
};

document.getElementById('importLedgerForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var fileInput = document.getElementById('importLedgerFile');
    if (!fileInput.files || !fileInput.files[0]) {
        alert('Please choose an Excel file.');
        return;
    }
    var btn = document.getElementById('importSubmitBtn');
    var original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = 'Importing...';
    var fd = new FormData(this);
    fetch('ajax/import-account-ledger-excel.php', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin'
    })
    .then(function(res) {
        return res.text().then(function(text) {
            if (!text || !String(text).trim()) {
                throw new Error('Server returned an empty response. Please try again.');
            }
            try {
                return JSON.parse(text);
            } catch (parseErr) {
                throw new Error('Invalid server response. ' + String(text).slice(0, 200));
            }
        });
    })
    .then(function(data) {
        if (data.status === 'success') {
            alert(data.message || 'Import completed.');
            window.location.reload();
        } else {
            var msg = data.message || 'Import failed.';
            if (data.errors && data.errors.length) {
                msg += '\n\n' + data.errors.slice(0, 5).join('\n');
            }
            alert(msg);
        }
    })
    .catch(function(err) {
        alert('Import failed: ' + (err && err.message ? err.message : String(err)));
    })
    .finally(function() {
        btn.disabled = false;
        btn.innerHTML = original;
    });
});

document.querySelectorAll('.btn-delete-ledger').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var name = this.getAttribute('data-ledger') || '';
        var customerId = parseInt(this.getAttribute('data-customer-id') || '0', 10) || 0;
        if (!confirm('Delete ledger “‘ + name + ’”? This may affect existing transactions.')) {
            return;
        }
        var fd = new FormData();
        fd.append('ledger', name);
        if (customerId > 0) fd.append('customer_id', String(customerId));
        fetch('ajax/delete-account-ledger.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data && data.status === 'success') {
                    window.location.reload();
                    return;
                }
                alert((data && data.message) ? data.message : 'Delete failed');
            })
            .catch(function(err) {
                alert('Delete failed: ' + (err && err.message ? err.message : String(err)));
            });
    });
});
</script>

<?php include 'footer-script.php'; ?>
</body>
</html>

