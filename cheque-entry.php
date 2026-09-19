<?php

session_start();
require_once __DIR__ . '/config.php';

if (empty($_SESSION['Admin'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/includes/auragold_cheque_entry_schema.php';

auragold_ensure_branch_id_on_settings_tables($conn);
$settings_branch_id = auragold_settings_branch_id();
auragold_ensure_tbl_cheque_entry($conn);

$ce_fy_start = '';
$ce_fy_end = '';
if (!empty($_SESSION['financial_year']) && is_array($_SESSION['financial_year'])) {
    $ce_fy_start = auragold_cheque_entry_parse_date_filter($_SESSION['financial_year']['start_date'] ?? '');
    $ce_fy_end = auragold_cheque_entry_parse_date_filter($_SESSION['financial_year']['end_date'] ?? '');
}

$branch_name_default = '';
if ($settings_branch_id > 0) {
    $br = getRecord('SELECT name FROM tbl_branches WHERE id = ' . (int) $settings_branch_id . ' LIMIT 1');
    if ($br && !empty($br['name'])) {
        $branch_name_default = trim((string) $br['name']);
    }
}
$ce_login_branch_name = $branch_name_default !== '' ? $branch_name_default : 'Main Branch';

$ce_initial_filters = [];
if ($ce_fy_start !== '') {
    $ce_initial_filters['cheque_date_from'] = $ce_fy_start;
}
if ($ce_fy_end !== '') {
    $ce_initial_filters['cheque_date_to'] = $ce_fy_end;
}
$ce_initial_filters['branch_name'] = $ce_login_branch_name;

$cheque_entries = auragold_get_cheque_entries($conn, $settings_branch_id, '', 500, 0, $ce_initial_filters);
$next_pdc_no = auragold_cheque_entry_next_pdc_no($conn, $settings_branch_id);
$list_total_amount = 0.0;
foreach ($cheque_entries as $ce_row) {
    $list_total_amount += (float) ($ce_row['amount'] ?? 0);
}

$pdc_status_options = ['', 'Bounced', 'Cleared', 'InProgress'];
$pdc_voucher_types = ['PDC Receivable', 'PDC Payable', 'PDC Clearance'];

$ce_filter_options = auragold_cheque_entry_filter_options($conn, $settings_branch_id);
$ce_filter_banks = $ce_filter_options['banks'];
$ce_filter_ledgers = $ce_filter_options['ledgers'];

?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Cheque Entry - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
    <?php include 'header-script.php'; ?>
    <style>
        html, body { height: 100%; background: #f4f6fb; }
        .layout-content { min-height: calc(100vh - 60px); }
        .cheque-entry-page { padding: 0 12px 20px; }
        .ce-header-card {
            background: #11294b;
            color: #fff;
            border: none;
            border-radius: 0;
            margin: 0 -12px 12px;
        }
        .ce-header-card .card-body { padding: 10px 16px; }
        .ce-title { margin: 0; font-size: 1rem; font-weight: 700; letter-spacing: 0.02em; }
        .ce-subtitle { margin: 0; font-size: 0.75rem; opacity: 0.85; }
        .ce-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .ce-toolbar-left { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .ce-toolbar-right { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-left: auto; }
        .ce-search-wrap { position: relative; width: 260px; max-width: 100%; }
        .ce-search-wrap i {
            position: absolute; left: 10px; top: 50%; transform: translateY(-50%);
            color: #64748b; pointer-events: none; font-size: 14px;
        }
        .ce-search-wrap input {
            width: 100%; height: 36px; padding: 6px 10px 6px 34px;
            border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;
        }
        .ce-btn {
            border: none; border-radius: 6px; padding: 7px 14px; font-size: 12px;
            font-weight: 600; cursor: pointer; white-space: nowrap;
        }
        .ce-btn-primary { background: #11294b; color: #fff; }
        .ce-btn-light { background: #fff; color: #334155; border: 1px solid #cbd5e1; }
        .ce-table-wrap {
            background: #fff;
            border: 1px solid #dbeafe;
            border-radius: 0;
            overflow: auto;
            max-height: calc(100vh - 240px);
        }
        .ce-table {
            width: 100%;
            min-width: 2200px;
            border-collapse: collapse;
            font-size: 12px;
        }
        .ce-table thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #f1f5f9;
            color: #334155;
            font-weight: 700;
            padding: 10px 8px;
            border: 1px solid #dbeafe;
            white-space: nowrap;
            vertical-align: middle;
        }
        .ce-table tbody td {
            padding: 8px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
            white-space: nowrap;
            max-width: 180px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .ce-table tbody tr:nth-child(even) { background: #fafbfc; }
        .ce-table tbody tr:hover { background: #f0f7ff; }
        .ce-table .ce-num { text-align: right; font-variant-numeric: tabular-nums; }
        .ce-table .ce-link { color: #2563eb; font-weight: 600; text-decoration: none; }
        .ce-table .ce-link:hover { text-decoration: underline; }
        .ce-actions a { color: #11294b; margin-right: 6px; }
        .ce-actions a.ce-del { color: #dc2626; }
        .ce-empty { padding: 40px 16px; text-align: center; color: #94a3b8; }
        .ce-footer {
            display: flex; flex-wrap: wrap; gap: 12px; align-items: center;
            justify-content: space-between; padding: 10px 4px 0; font-size: 12px; color: #475569;
        }
        .ce-footer-total { font-weight: 700; color: #11294b; }
        #chequeEntryModal .modal-dialog { max-width: 920px; }
        #chequeEntryModal .modal-header { background: #f8fafc; }
        #chequeEntryModal .btn-save-ce { background: #11294b; border-color: #11294b; }
        #chequeEntryModal .ce-field-readonly,
        #chequeEntryModal .ce-field-readonly:disabled {
            background: #f1f5f9 !important;
            color: #475569;
            cursor: not-allowed;
        }
        .ce-badge {
            display: inline-block; padding: 2px 8px; border-radius: 999px;
            font-size: 11px; font-weight: 600;
        }
        .ce-badge-pending { background: #fef3c7; color: #92400e; }
        .ce-badge-cleared { background: #dcfce7; color: #166534; }
        .ce-badge-bounced { background: #fee2e2; color: #991b1b; }
        .ce-badge-inprogress { background: #dbeafe; color: #1e40af; }
        .ce-filter-btn {
            position: relative;
            width: 36px;
            height: 36px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .ce-filter-btn .ce-filter-count {
            position: absolute;
            top: -4px;
            right: -4px;
            min-width: 16px;
            height: 16px;
            padding: 0 4px;
            border-radius: 999px;
            background: #ef4444;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            line-height: 16px;
            text-align: center;
            display: none;
        }
        .ce-filter-btn.has-active-filters .ce-filter-count { display: inline-block; }
        .ce-icon-btn {
            width: 36px;
            height: 36px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .ce-icon-btn .feather {
            font-size: 16px;
            line-height: 1;
            color: #334155;
        }
        .ce-icon-btn:hover .feather { color: #11294b; }
        #ceAdvanceFilterModal .modal-content {
            border: none;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 16px 40px rgba(15, 23, 42, 0.18);
        }
        #ceAdvanceFilterModal .ce-af-header {
            background: #11294b;
            color: #fff;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        #ceAdvanceFilterModal .ce-af-header h5 {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: #fff;
        }
        #ceAdvanceFilterModal .ce-af-header .close {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #fff;
            opacity: 0.9;
            text-shadow: none;
        }
        #ceAdvanceFilterModal .modal-body { padding: 18px 20px 8px; }
        .ce-af-row {
            display: grid;
            grid-template-columns: 130px minmax(0, 1fr);
            gap: 12px;
            align-items: center;
            margin-bottom: 14px;
        }
        .ce-af-row label {
            margin: 0;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
        }
        .ce-af-range {
            display: flex;
            align-items: center;
            gap: 8px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 6px 8px;
            background: #fff;
        }
        .ce-af-range input[type="date"] {
            border: none;
            flex: 1 1 0;
            min-width: 0;
            font-size: 12px;
            padding: 2px 4px;
            background: transparent;
        }
        .ce-af-range input[type="date"]:focus { outline: none; }
        .ce-af-range-sep { color: #64748b; font-size: 12px; white-space: nowrap; }
        .ce-af-range-reset {
            border: none;
            background: transparent;
            color: #64748b;
            padding: 0 2px;
            cursor: pointer;
        }
        .ce-af-row .form-control { font-size: 13px; height: 38px; border-radius: 8px; }
        .ce-af-split {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        #ceAdvanceFilterModal .modal-footer {
            border-top: none;
            justify-content: center;
            gap: 12px;
            padding: 8px 20px 20px;
        }
        .ce-af-btn-apply {
            min-width: 120px;
            border: 2px solid #7c3aed;
            color: #7c3aed;
            background: #fff;
            border-radius: 8px;
            font-weight: 700;
            padding: 8px 18px;
        }
        .ce-af-btn-apply:hover { background: #f5f3ff; color: #6d28d9; }
        .ce-af-btn-clear {
            min-width: 120px;
            border: 2px solid #f472b6;
            color: #db2777;
            background: #fff;
            border-radius: 8px;
            font-weight: 700;
            padding: 8px 18px;
        }
        .ce-af-btn-clear:hover { background: #fdf2f8; color: #be185d; }
        #chequeEntryTable {
            table-layout: fixed;
            min-width: 2200px;
        }
        #chequeEntryTable thead th[data-column]:not([data-column="actions"]) {
            position: relative;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #chequeEntryTable thead th[data-column]:not([data-column="actions"]) .pv-th-inner {
            display: flex;
            align-items: center;
            gap: 2px;
            min-width: 0;
            width: 100%;
        }
        #chequeEntryTable thead th[data-column] .pv-th-inner .pv-th-text {
            flex: 1 1 0;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        #chequeEntryTable .pv-col-drag-h {
            flex: 0 0 auto;
            cursor: grab;
            color: #94a3b8;
            padding: 0 2px;
            line-height: 1;
        }
        #chequeEntryTable .pv-col-drag-h .feather.icon-move { font-size: 11px; }
        #chequeEntryTable thead .pv-col-drag-h .feather.icon-move { opacity: 0.7; }
        #chequeEntryTable .pv-col-drag-h:active { cursor: grabbing; }
        #chequeEntryTable thead th[data-column] { vertical-align: middle; }
        #chequeEntryTable .pv-col-resizer {
            position: absolute;
            top: 0;
            right: 0;
            width: 6px;
            height: 100%;
            cursor: col-resize;
            z-index: 3;
            user-select: none;
        }
        #chequeEntryTable .pv-col-resizer:hover { background: rgba(37, 99, 235, 0.25); }
        #chequeEntryTable tbody td[data-column]:not([data-column="actions"]) {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        #chequeEntryTable td[data-column="actions"],
        #chequeEntryTable th[data-column="actions"] { width: 88px; min-width: 72px; }
        .ce-columns-modal.filter-modal {
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 10000;
            display: none;
            align-items: center;
            justify-content: center;
        }
        .ce-columns-modal.filter-modal.active { display: flex !important; }
        .ce-columns-modal .filter-modal-content {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow: hidden;
        }
        #ceColumnsList .column-item {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            margin-bottom: 4px;
            border-radius: 4px;
            cursor: pointer;
        }
        #ceColumnsList .column-item:hover { background: #f8fafc; }
        #ceColumnsList .column-item input[type="checkbox"] {
            margin-right: 10px;
            width: 16px;
            height: 16px;
        }
        #ceColumnsList .column-item label {
            margin: 0;
            font-size: 0.85rem;
            color: #334155;
            flex: 1;
            cursor: pointer;
        }
    </style>
</head>
<body>
<div class="layout-wrapper layout-2">
    <div class="layout-inner">
        <div id="layout-sidenav" class="layout-sidenav sidenav sidenav-vertical bg-white logo-dark" aria-hidden="true"></div>
        <div class="layout-container" style="margin-left: 260px;">
            <nav class="layout-navbar navbar navbar-expand-lg align-items-lg-center bg-dark container-p-x" id="layout-navbar" aria-hidden="true"></nav>
            <div class="layout-content">
                <div class="container-fluid flex-grow-1 cheque-entry-page" style="padding-top: 0;">
                    <?php include 'sidebar.php'; ?>

                    <div class="card ce-header-card">
                        <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div>
                                <h1 class="ce-title">Payment List</h1>
                                <p class="ce-subtitle">PDC / Cheque Entry</p>
                            </div>
                        </div>
                    </div>

                    <div class="ce-toolbar">
                        <div class="ce-toolbar-left">
                            <strong style="color:#11294b;font-size:14px;">Cheque Entry</strong>
                        </div>
                        <div class="ce-toolbar-right">
                            <div class="ce-search-wrap">
                                <i class="feather icon-search"></i>
                                <input type="search" id="ceSearch" placeholder="Search…" autocomplete="off" aria-label="Search cheque entries">
                            </div>
                            <button type="button" class="ce-btn ce-btn-light ce-filter-btn ce-icon-btn" id="ceFilterBtn" title="Advance Filter" aria-label="Advance Filter">
                                <i class="feather icon-filter"></i>
                                <span class="ce-filter-count" id="ceFilterCount">0</span>
                            </button>
                            <button type="button" class="ce-btn ce-btn-light ce-icon-btn" id="ceColumnsBtn" title="Show / Hide Columns" aria-label="Column Settings">
                                <i class="feather icon-settings"></i>
                            </button>
                            <button type="button" class="ce-btn ce-btn-light ce-icon-btn" id="ceRefreshBtn" title="Refresh" aria-label="Refresh">
                                <i class="feather icon-refresh-cw"></i>
                            </button>
                            <button type="button" class="ce-btn ce-btn-light" id="ceExportBtn" title="Export to Excel">
                                <i class="feather icon-download"></i> Export
                            </button>
                        </div>
                    </div>

                    <div class="ce-table-wrap">
                        <table class="ce-table" id="chequeEntryTable">
                            <thead>
                                <tr>
                                    <th data-column="sr-no"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Sr.No</th>
                                    <th data-column="pdc-no"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>PDC No.</th>
                                    <th data-column="account-no"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Account No</th>
                                    <th data-column="account-ledger"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Account Ledger</th>
                                    <th data-column="bank-name"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Bank Name.</th>
                                    <th data-column="cheque-no"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Cheque No.</th>
                                    <th data-column="cheque-date"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Cheque Date</th>
                                    <th data-column="pay-date"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Pay Dt.</th>
                                    <th data-column="amount"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Amount</th>
                                    <th data-column="branch-name"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Branch Name</th>
                                    <th data-column="status"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Status</th>
                                    <th data-column="bounced-cleared-date"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Bounced/Cleared Date</th>
                                    <th data-column="against-voucher-no"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Against Voucher No.</th>
                                    <th data-column="against-voucher-type"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Against Voucher Type</th>
                                    <th data-column="nsf-fees"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>NSF Fees</th>
                                    <th data-column="recoverable"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Recoverable</th>
                                    <th data-column="invoice-date"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Invoice Date</th>
                                    <th data-column="reference-voucher-type"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Refrence Voucher Type</th>
                                    <th data-column="ref-invoice-no"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Ref Invoice No.</th>
                                    <th data-column="pdc-voucher-type"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>PDC VoucherType</th>
                                    <th data-column="actions">Action</th>
                                </tr>
                            </thead>
                            <tbody id="chequeEntryBody">
                                <?php if ($cheque_entries === []): ?>
                                    <tr class="ce-empty-row"><td colspan="21" class="ce-empty">No cheque entries yet. Save a voucher with cheque payment to create entries.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($cheque_entries as $i => $row): ?>
                                        <?php
                                        $status = trim((string) ($row['status'] ?? ''));
                                        $badge = '';
                                        if (strcasecmp($status, 'Cleared') === 0) {
                                            $badge = 'ce-badge-cleared';
                                        } elseif (strcasecmp($status, 'Bounced') === 0) {
                                            $badge = 'ce-badge-bounced';
                                        } elseif (strcasecmp($status, 'InProgress') === 0) {
                                            $badge = 'ce-badge-inprogress';
                                        } elseif (strcasecmp($status, 'Pending') === 0) {
                                            $badge = 'ce-badge-pending';
                                        }
                                        ?>
                                        <tr data-entry="<?php echo htmlspecialchars(json_encode($row, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>">
                                            <td data-column="sr-no"><?php echo (int) ($i + 1); ?></td>
                                            <td data-column="pdc-no"><a href="javascript:void(0)" class="ce-link ce-edit-link"><?php echo htmlspecialchars($row['pdc_no']); ?></a></td>
                                            <td data-column="account-no"><?php echo htmlspecialchars($row['account_no']); ?></td>
                                            <td data-column="account-ledger" title="<?php echo htmlspecialchars($row['account_ledger']); ?>"><?php echo htmlspecialchars($row['account_ledger']); ?></td>
                                            <td data-column="bank-name"><?php echo htmlspecialchars($row['bank_name']); ?></td>
                                            <td data-column="cheque-no"><?php echo htmlspecialchars($row['cheque_no']); ?></td>
                                            <td data-column="cheque-date"><?php echo htmlspecialchars($row['cheque_date_fmt']); ?></td>
                                            <td data-column="pay-date"><?php echo htmlspecialchars($row['pay_date_fmt']); ?></td>
                                            <td data-column="amount" class="ce-num"><?php echo number_format((float) $row['amount'], 2); ?></td>
                                            <td data-column="branch-name"><?php echo htmlspecialchars($row['branch_name']); ?></td>
                                            <td data-column="status"><?php if ($badge !== ''): ?><span class="ce-badge <?php echo $badge; ?>"><?php echo htmlspecialchars($status); ?></span><?php endif; ?></td>
                                            <td data-column="bounced-cleared-date"><?php echo htmlspecialchars($row['bounced_cleared_date_fmt']); ?></td>
                                            <td data-column="against-voucher-no"><?php echo htmlspecialchars($row['against_voucher_no']); ?></td>
                                            <td data-column="against-voucher-type"><?php echo htmlspecialchars($row['against_voucher_type']); ?></td>
                                            <td data-column="nsf-fees" class="ce-num"><?php echo number_format((float) $row['nsf_fees'], 2); ?></td>
                                            <td data-column="recoverable"><?php echo (int) $row['recoverable'] === 1 ? 'Yes' : 'No'; ?></td>
                                            <td data-column="invoice-date"><?php echo htmlspecialchars($row['invoice_date_fmt']); ?></td>
                                            <td data-column="reference-voucher-type"><?php echo htmlspecialchars($row['reference_voucher_type']); ?></td>
                                            <td data-column="ref-invoice-no"><?php echo htmlspecialchars($row['ref_invoice_no']); ?></td>
                                            <td data-column="pdc-voucher-type"><?php echo htmlspecialchars($row['pdc_voucher_type']); ?></td>
                                            <td data-column="actions" class="ce-actions">
                                                <a href="javascript:void(0)" class="ce-edit" title="Edit"><i class="feather icon-edit"></i></a>
                                                <a href="javascript:void(0)" class="ce-del" title="Delete"><i class="feather icon-trash-2"></i></a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="ce-footer">
                        <span id="ceShowingText">Showing <?php echo count($cheque_entries); ?> entr<?php echo count($cheque_entries) === 1 ? 'y' : 'ies'; ?></span>
                        <span class="ce-footer-total">Total Amount: <?php echo number_format($list_total_amount, 2); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="ceAdvanceFilterModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 560px;">
        <div class="modal-content">
            <div class="ce-af-header">
                <h5>Advance Filter</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="ce-af-row">
                    <label for="ceFilterChequeFrom">Cheque Date</label>
                    <div class="ce-af-range">
                        <input type="date" id="ceFilterChequeFrom" value="<?php echo htmlspecialchars($ce_fy_start, ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="ce-af-range-sep">-</span>
                        <input type="date" id="ceFilterChequeTo" value="<?php echo htmlspecialchars($ce_fy_end, ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="button" class="ce-af-range-reset" data-ce-range="cheque" title="Clear dates"><i class="feather icon-rotate-ccw"></i></button>
                    </div>
                </div>
                <div class="ce-af-row">
                    <label for="ceFilterPayFrom">Pay Date</label>
                    <div class="ce-af-range">
                        <input type="date" id="ceFilterPayFrom">
                        <span class="ce-af-range-sep">-</span>
                        <input type="date" id="ceFilterPayTo">
                        <button type="button" class="ce-af-range-reset" data-ce-range="pay" title="Clear dates"><i class="feather icon-rotate-ccw"></i></button>
                    </div>
                </div>
                <div class="ce-af-row">
                    <label for="ceFilterBranch">Branch</label>
                    <select class="form-control" id="ceFilterBranch">
                        <option value="<?php echo htmlspecialchars($ce_login_branch_name, ENT_QUOTES, 'UTF-8'); ?>" selected><?php echo htmlspecialchars($ce_login_branch_name); ?></option>
                    </select>
                </div>
                <div class="ce-af-row">
                    <label for="ceFilterLedger">Ledger</label>
                    <select class="form-control" id="ceFilterLedger">
                        <option value="">Select Ledger</option>
                        <?php foreach ($ce_filter_ledgers as $ledger): ?>
                            <option value="<?php echo htmlspecialchars($ledger, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($ledger); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ce-af-row">
                    <label for="ceFilterPdcType">PDC Voucher Type</label>
                    <select class="form-control" id="ceFilterPdcType">
                        <option value="">Select PDC Voucher Type</option>
                        <?php foreach ($pdc_voucher_types as $vt): ?>
                            <option value="<?php echo htmlspecialchars($vt, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($vt); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ce-af-row">
                    <label for="ceFilterBank">Bank Name</label>
                    <select class="form-control" id="ceFilterBank">
                        <option value="">Select Bank Name</option>
                        <?php foreach ($ce_filter_banks as $bank): ?>
                            <option value="<?php echo htmlspecialchars($bank, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($bank); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ce-af-row">
                    <label for="ceFilterStatus">Status</label>
                    <select class="form-control" id="ceFilterStatus">
                        <option value="">Select Status</option>
                        <?php foreach ($pdc_status_options as $opt): ?>
                            <?php if ($opt === '') { continue; } ?>
                            <option value="<?php echo htmlspecialchars($opt, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($opt); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ce-af-row">
                    <label>Invoice No. &amp; Cheque No.</label>
                    <div class="ce-af-split">
                        <input type="text" class="form-control" id="ceFilterInvoiceNo" placeholder="Invoice No.">
                        <input type="text" class="form-control" id="ceFilterChequeNo" placeholder="Cheque No.">
                    </div>
                </div>
                <div class="ce-af-row">
                    <label for="ceFilterAccountNo">Account No</label>
                    <input type="text" class="form-control" id="ceFilterAccountNo" placeholder="Account No">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="ce-af-btn-apply" id="ceApplyFilterBtn">Apply Filter</button>
                <button type="button" class="ce-af-btn-clear" id="ceClearFilterBtn">Clear Filter</button>
            </div>
        </div>
    </div>
</div>

<div id="ceColumnsModal" class="ce-columns-modal filter-modal">
    <div class="filter-modal-content">
        <div style="background:#11294b;color:#fff;padding:12px 16px;display:flex;justify-content:space-between;align-items:center;border-radius:8px 8px 0 0;">
            <h5 style="margin:0;font-size:0.95rem;font-weight:600;"><i class="feather icon-settings"></i> Columns</h5>
            <div style="display:flex;gap:8px;">
                <button type="button" onclick="refreshCeColumns()" title="Reset columns" style="background:none;border:none;color:#fff;font-size:16px;cursor:pointer;padding:4px;">
                    <i class="feather icon-refresh-cw"></i>
                </button>
                <button type="button" onclick="closeCeColumnsModal()" style="background:none;border:none;color:#fff;font-size:20px;cursor:pointer;padding:0;width:24px;height:24px;">&times;</button>
            </div>
        </div>
        <div style="padding:16px;">
            <input type="text" id="ceColumnSearch" class="form-control" placeholder="Search columns" onkeyup="filterCeColumns()" style="margin-bottom:12px;font-size:0.85rem;height:32px;">
            <div id="ceColumnsList" style="max-height:400px;overflow-y:auto;border:1px solid #e2e8f0;border-radius:4px;padding:8px;"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="chequeEntryModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="chequeEntryModalTitle">Cheque Entry</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="ceId" value="">
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label for="cePdcNo">PDC No.</label>
                        <input type="text" class="form-control form-control-sm ce-field-readonly" id="cePdcNo" value="<?php echo htmlspecialchars($next_pdc_no, ENT_QUOTES, 'UTF-8'); ?>" readonly>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="ceAccountNo">Account No</label>
                        <input type="text" class="form-control form-control-sm ce-field-readonly" id="ceAccountNo" readonly>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="ceAccountLedger">Account Ledger <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm ce-field-readonly" id="ceAccountLedger" required readonly>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="ceBankName">Bank Name.</label>
                        <input type="text" class="form-control form-control-sm ce-field-readonly" id="ceBankName" readonly>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="ceChequeNo">Cheque No.</label>
                        <input type="text" class="form-control form-control-sm ce-field-readonly" id="ceChequeNo" readonly>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="ceChequeDate">Cheque Date</label>
                        <input type="date" class="form-control form-control-sm ce-field-readonly" id="ceChequeDate" readonly>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="cePayDate">Pay Dt.</label>
                        <input type="date" class="form-control form-control-sm ce-field-readonly" id="cePayDate" readonly>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="ceAmount">Amount</label>
                        <input type="number" class="form-control form-control-sm ce-field-readonly" id="ceAmount" min="0" step="0.01" value="0" readonly>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="ceBranchName">Branch Name</label>
                        <input type="text" class="form-control form-control-sm ce-field-readonly" id="ceBranchName" value="<?php echo htmlspecialchars($branch_name_default, ENT_QUOTES, 'UTF-8'); ?>" readonly>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="ceStatus">Status</label>
                        <select class="form-control form-control-sm" id="ceStatus">
                            <?php foreach ($pdc_status_options as $opt): ?>
                                <option value="<?php echo htmlspecialchars($opt, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $opt === '' ? '&nbsp;' : htmlspecialchars($opt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="ceBouncedClearedDate">Bounced/Cleared Date</label>
                        <input type="date" class="form-control form-control-sm" id="ceBouncedClearedDate">
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="ceAgainstVoucherNo">Against Voucher No.</label>
                        <input type="text" class="form-control form-control-sm ce-field-readonly" id="ceAgainstVoucherNo" readonly>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="ceAgainstVoucherType">Against Voucher Type</label>
                        <input type="text" class="form-control form-control-sm ce-field-readonly" id="ceAgainstVoucherType" readonly>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="ceNsfFees">NSF Fees</label>
                        <input type="number" class="form-control form-control-sm" id="ceNsfFees" min="0" step="0.01" value="0">
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="ceRecoverable">Recoverable</label>
                        <select class="form-control form-control-sm" id="ceRecoverable">
                            <option value="0">No</option>
                            <option value="1">Yes</option>
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="ceInvoiceDate">Invoice Date</label>
                        <input type="date" class="form-control form-control-sm ce-field-readonly" id="ceInvoiceDate" readonly>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="ceReferenceVoucherType">Refrence Voucher Type</label>
                        <input type="text" class="form-control form-control-sm ce-field-readonly" id="ceReferenceVoucherType" readonly>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="ceRefInvoiceNo">Ref Invoice No.</label>
                        <input type="text" class="form-control form-control-sm ce-field-readonly" id="ceRefInvoiceNo" readonly>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="cePdcVoucherType">PDC VoucherType</label>
                        <select class="form-control form-control-sm ce-field-readonly" id="cePdcVoucherType" disabled>
                            <option value="">Select</option>
                            <?php foreach ($pdc_voucher_types as $vt): ?>
                                <option value="<?php echo htmlspecialchars($vt, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($vt); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary btn-sm btn-save-ce" id="ceSaveBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<script src="assets/libs/popper/popper.js"></script>
<script src="assets/js/bootstrap.js"></script>
<input type="hidden" id="settingsBranchId" value="<?php echo (int) $settings_branch_id; ?>">
<script>
(function () {
    var $ = jQuery;
    var modal = $('#chequeEntryModal');
    var filterModal = $('#ceAdvanceFilterModal');
    var tbody = $('#chequeEntryBody');
    var settingsBranchId = $('#settingsBranchId').val() || '';
    var nextPdcNo = <?php echo json_encode($next_pdc_no, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var branchDefault = <?php echo json_encode($ce_login_branch_name, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var activeFilters = <?php echo json_encode($ce_initial_filters, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
    var searchTimer;

    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        var d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }

    function fmtMoney(n) {
        var x = parseFloat(n);
        if (isNaN(x)) x = 0;
        return x.toFixed(2);
    }

    function statusBadge(status) {
        var s = String(status || '').trim();
        if (!s) return '';
        var cls = '';
        var low = s.toLowerCase();
        if (low === 'cleared') cls = 'ce-badge-cleared';
        else if (low === 'bounced') cls = 'ce-badge-bounced';
        else if (low === 'inprogress') cls = 'ce-badge-inprogress';
        else if (low === 'pending') cls = 'ce-badge-pending';
        if (!cls) return escapeHtml(s);
        return '<span class="ce-badge ' + cls + '">' + escapeHtml(s) + '</span>';
    }

    var ceLockedFieldSelectors = [
        '#cePdcNo', '#ceAccountNo', '#ceAccountLedger', '#ceBankName', '#ceChequeNo',
        '#ceChequeDate', '#cePayDate', '#ceAmount', '#ceBranchName', '#ceAgainstVoucherNo',
        '#ceAgainstVoucherType', '#ceInvoiceDate', '#ceReferenceVoucherType', '#ceRefInvoiceNo', '#cePdcVoucherType'
    ];

    function setFormLocked(locked) {
        ceLockedFieldSelectors.forEach(function (sel) {
            var $el = $(sel);
            if (!$el.length) return;
            if ($el.is('select')) {
                $el.prop('disabled', !!locked);
            } else {
                $el.prop('readonly', !!locked);
            }
            $el.toggleClass('ce-field-readonly', !!locked);
        });
    }

    function isoToInputDate(v) {
        v = String(v || '').trim();
        if (!v || v === '0000-00-00') return '';
        return v.length >= 10 ? v.substring(0, 10) : v;
    }

    function fillForm(entry) {
        entry = entry || {};
        $('#ceId').val(entry.id || '');
        $('#cePdcNo').val(entry.pdc_no || nextPdcNo);
        $('#ceAccountNo').val(entry.account_no || '');
        $('#ceAccountLedger').val(entry.account_ledger || '');
        $('#ceBankName').val(entry.bank_name || '');
        $('#ceChequeNo').val(entry.cheque_no || '');
        $('#ceChequeDate').val(isoToInputDate(entry.cheque_date));
        $('#cePayDate').val(isoToInputDate(entry.pay_date));
        $('#ceAmount').val(entry.amount != null ? entry.amount : '0');
        $('#ceBranchName').val(entry.branch_name || branchDefault);
        $('#ceStatus').val(entry.status || '');
        $('#ceBouncedClearedDate').val(isoToInputDate(entry.bounced_cleared_date));
        $('#ceAgainstVoucherNo').val(entry.against_voucher_no || '');
        $('#ceAgainstVoucherType').val(entry.against_voucher_type || '');
        $('#ceNsfFees').val(entry.nsf_fees != null ? entry.nsf_fees : '0');
        $('#ceRecoverable').val(String(parseInt(entry.recoverable, 10) === 1 ? 1 : 0));
        $('#ceInvoiceDate').val(isoToInputDate(entry.invoice_date));
        $('#ceReferenceVoucherType').val(entry.reference_voucher_type || '');
        $('#ceRefInvoiceNo').val(entry.ref_invoice_no || '');
        $('#cePdcVoucherType').val(entry.pdc_voucher_type || '');
        setFormLocked(!!(entry.id));
    }

    function resetForm() {
        fillForm({});
        $('#ceId').val('');
        $('#cePdcNo').val(nextPdcNo);
        $('#chequeEntryModalTitle').text('Cheque Entry');
        setFormLocked(false);
    }

    function readFormPayload() {
        var id = $.trim($('#ceId').val());
        if (id) {
            return {
                action: 'save',
                id: id,
                limited_update: 1,
                settings_branch_id: settingsBranchId,
                status: $('#ceStatus').val(),
                bounced_cleared_date: $('#ceBouncedClearedDate').val(),
                nsf_fees: $('#ceNsfFees').val(),
                recoverable: $('#ceRecoverable').val()
            };
        }
        return {
            action: 'save',
            id: '',
            settings_branch_id: settingsBranchId,
            pdc_no: $('#cePdcNo').val(),
            account_no: $('#ceAccountNo').val(),
            account_ledger: $('#ceAccountLedger').val(),
            bank_name: $('#ceBankName').val(),
            cheque_no: $('#ceChequeNo').val(),
            cheque_date: $('#ceChequeDate').val(),
            pay_date: $('#cePayDate').val(),
            amount: $('#ceAmount').val(),
            branch_name: $('#ceBranchName').val(),
            status: $('#ceStatus').val(),
            bounced_cleared_date: $('#ceBouncedClearedDate').val(),
            against_voucher_no: $('#ceAgainstVoucherNo').val(),
            against_voucher_type: $('#ceAgainstVoucherType').val(),
            nsf_fees: $('#ceNsfFees').val(),
            recoverable: $('#ceRecoverable').val(),
            invoice_date: $('#ceInvoiceDate').val(),
            reference_voucher_type: $('#ceReferenceVoucherType').val(),
            ref_invoice_no: $('#ceRefInvoiceNo').val(),
            pdc_voucher_type: $('#cePdcVoucherType').val()
        };
    }

    function attrJson(obj) {
        return String(JSON.stringify(obj))
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;');
    }

    function parseRowEntry($tr) {
        var raw = $tr.attr('data-entry');
        if (!raw) return null;
        try {
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    }

    function buildRowHtml(entry, sr) {
        return '<tr data-entry="' + attrJson(entry) + '">'
            + '<td data-column="sr-no">' + sr + '</td>'
            + '<td data-column="pdc-no"><a href="javascript:void(0)" class="ce-link ce-edit-link">' + escapeHtml(entry.pdc_no) + '</a></td>'
            + '<td data-column="account-no">' + escapeHtml(entry.account_no) + '</td>'
            + '<td data-column="account-ledger" title="' + escapeHtml(entry.account_ledger) + '">' + escapeHtml(entry.account_ledger) + '</td>'
            + '<td data-column="bank-name">' + escapeHtml(entry.bank_name) + '</td>'
            + '<td data-column="cheque-no">' + escapeHtml(entry.cheque_no) + '</td>'
            + '<td data-column="cheque-date">' + escapeHtml(entry.cheque_date_fmt || '') + '</td>'
            + '<td data-column="pay-date">' + escapeHtml(entry.pay_date_fmt || '') + '</td>'
            + '<td data-column="amount" class="ce-num">' + fmtMoney(entry.amount) + '</td>'
            + '<td data-column="branch-name">' + escapeHtml(entry.branch_name) + '</td>'
            + '<td data-column="status">' + statusBadge(entry.status) + '</td>'
            + '<td data-column="bounced-cleared-date">' + escapeHtml(entry.bounced_cleared_date_fmt || '') + '</td>'
            + '<td data-column="against-voucher-no">' + escapeHtml(entry.against_voucher_no) + '</td>'
            + '<td data-column="against-voucher-type">' + escapeHtml(entry.against_voucher_type) + '</td>'
            + '<td data-column="nsf-fees" class="ce-num">' + fmtMoney(entry.nsf_fees) + '</td>'
            + '<td data-column="recoverable">' + (parseInt(entry.recoverable, 10) === 1 ? 'Yes' : 'No') + '</td>'
            + '<td data-column="invoice-date">' + escapeHtml(entry.invoice_date_fmt || '') + '</td>'
            + '<td data-column="reference-voucher-type">' + escapeHtml(entry.reference_voucher_type) + '</td>'
            + '<td data-column="ref-invoice-no">' + escapeHtml(entry.ref_invoice_no) + '</td>'
            + '<td data-column="pdc-voucher-type">' + escapeHtml(entry.pdc_voucher_type) + '</td>'
            + '<td data-column="actions" class="ce-actions">'
            + '<a href="javascript:void(0)" class="ce-edit" title="Edit"><i class="feather icon-edit"></i></a> '
            + '<a href="javascript:void(0)" class="ce-del" title="Delete"><i class="feather icon-trash-2"></i></a>'
            + '</td></tr>';
    }

    function updateFooter() {
        var rows = tbody.find('tr:not(.ce-empty-row):visible');
        var total = 0;
        rows.each(function () {
            var e = parseRowEntry($(this));
            if (e) total += parseFloat(e.amount) || 0;
        });
        var n = rows.length;
        $('#ceShowingText').text('Showing ' + n + ' entr' + (n === 1 ? 'y' : 'ies'));
        $('.ce-footer-total').text('Total Amount: ' + fmtMoney(total));
    }

    function renderEntries(entries) {
        if (!entries || !entries.length) {
            tbody.html('<tr class="ce-empty-row"><td colspan="21" class="ce-empty">No cheque entries found.</td></tr>');
            updateFooter();
            return;
        }
        var html = '';
        entries.forEach(function (entry, idx) {
            html += buildRowHtml(entry, idx + 1);
        });
        tbody.html(html);
        if (window.ChequeEntryGrid && typeof window.ChequeEntryGrid.refreshAfterDataLoad === 'function') {
            window.ChequeEntryGrid.refreshAfterDataLoad();
        }
        updateFooter();
    }

    function reloadList() {
        $.getJSON('ajax/cheque-entry.php', $.extend({
            action: 'list',
            q: $('#ceSearch').val().trim(),
            settings_branch_id: settingsBranchId
        }, activeFilters)).done(function (res) {
            if (!res || !res.success) {
                alert((res && res.message) ? res.message : 'Could not load cheque entries.');
                return;
            }
            if (res.next_pdc_no) nextPdcNo = res.next_pdc_no;
            renderEntries(res.entries || []);
            if (typeof res.total_amount !== 'undefined') {
                $('.ce-footer-total').text('Total Amount: ' + fmtMoney(res.total_amount));
            }
        }).fail(function () {
            alert('Could not load cheque entries.');
        });
    }

    function readFiltersFromForm() {
        var f = {};
        var map = {
            cheque_date_from: '#ceFilterChequeFrom',
            cheque_date_to: '#ceFilterChequeTo',
            pay_date_from: '#ceFilterPayFrom',
            pay_date_to: '#ceFilterPayTo',
            branch_name: '#ceFilterBranch',
            account_ledger: '#ceFilterLedger',
            pdc_voucher_type: '#ceFilterPdcType',
            bank_name: '#ceFilterBank',
            status: '#ceFilterStatus',
            ref_invoice_no: '#ceFilterInvoiceNo',
            cheque_no: '#ceFilterChequeNo',
            account_no: '#ceFilterAccountNo'
        };
        Object.keys(map).forEach(function (key) {
            var val = $.trim($(map[key]).val());
            if (val !== '') f[key] = val;
        });
        return f;
    }

    function syncFilterFormFromActive() {
        $('#ceFilterChequeFrom').val(activeFilters.cheque_date_from || '');
        $('#ceFilterChequeTo').val(activeFilters.cheque_date_to || '');
        $('#ceFilterPayFrom').val(activeFilters.pay_date_from || '');
        $('#ceFilterPayTo').val(activeFilters.pay_date_to || '');
        $('#ceFilterBranch').val(activeFilters.branch_name || branchDefault);
        $('#ceFilterLedger').val(activeFilters.account_ledger || '');
        $('#ceFilterPdcType').val(activeFilters.pdc_voucher_type || '');
        $('#ceFilterBank').val(activeFilters.bank_name || '');
        $('#ceFilterStatus').val(activeFilters.status || '');
        $('#ceFilterInvoiceNo').val(activeFilters.ref_invoice_no || '');
        $('#ceFilterChequeNo').val(activeFilters.cheque_no || '');
        $('#ceFilterAccountNo').val(activeFilters.account_no || '');
    }

    function countActiveFilters(filters) {
        return Object.keys(filters || {}).filter(function (k) {
            return String(filters[k] || '').trim() !== '';
        }).length;
    }

    function updateFilterBadge() {
        var n = countActiveFilters(activeFilters);
        $('#ceFilterCount').text(n);
        $('#ceFilterBtn').toggleClass('has-active-filters', n > 0);
    }

    function clearFilterFormFields() {
        $('#ceFilterChequeFrom, #ceFilterChequeTo, #ceFilterPayFrom, #ceFilterPayTo').val('');
        $('#ceFilterBranch').val(branchDefault);
        $('#ceFilterLedger, #ceFilterPdcType, #ceFilterBank, #ceFilterStatus').val('');
        $('#ceFilterInvoiceNo, #ceFilterChequeNo, #ceFilterAccountNo').val('');
    }

    $('#ceFilterBtn').on('click', function () {
        syncFilterFormFromActive();
        filterModal.modal('show');
    });

    $('#ceApplyFilterBtn').on('click', function () {
        activeFilters = readFiltersFromForm();
        updateFilterBadge();
        filterModal.modal('hide');
        reloadList();
    });

    $('#ceClearFilterBtn').on('click', function () {
        clearFilterFormFields();
        activeFilters = {};
        updateFilterBadge();
        filterModal.modal('hide');
        reloadList();
    });

    $('.ce-af-range-reset').on('click', function () {
        var kind = $(this).data('ce-range');
        if (kind === 'cheque') {
            $('#ceFilterChequeFrom, #ceFilterChequeTo').val('');
        } else if (kind === 'pay') {
            $('#ceFilterPayFrom, #ceFilterPayTo').val('');
        }
    });

    function openEditFromRow($tr) {
        var entry = parseRowEntry($tr);
        if (!entry) return;
        fillForm(entry);
        $('#chequeEntryModalTitle').text('Cheque Entry');
        setFormLocked(true);
        modal.modal('show');
    }

    $('#ceSaveBtn').on('click', function () {
        var id = $.trim($('#ceId').val());
        if (!id) {
            alert('Open an existing cheque entry to update status.');
            return;
        }
        $.post('ajax/cheque-entry.php', readFormPayload()).done(function (res) {
            if (!res || !res.success) {
                alert((res && res.message) ? res.message : 'Save failed.');
                return;
            }
            modal.modal('hide');
            if (res.message && /clearance|posted/i.test(String(res.message))) {
                alert(res.message);
            }
            reloadList();
        }).fail(function () {
            alert('Save failed.');
        });
    });

    tbody.on('click', '.ce-edit, .ce-edit-link', function () {
        openEditFromRow($(this).closest('tr'));
    });

    tbody.on('click', '.ce-del', function () {
        var $tr = $(this).closest('tr');
        var entry = parseRowEntry($tr);
        if (!entry || !confirm('Delete this cheque entry?')) return;
        $.post('ajax/cheque-entry.php', {
            action: 'delete',
            id: entry.id,
            settings_branch_id: settingsBranchId
        }).done(function (res) {
            if (!res || !res.success) {
                alert((res && res.message) ? res.message : 'Delete failed.');
                return;
            }
            reloadList();
        }).fail(function () {
            alert('Delete failed.');
        });
    });

    $('#ceRefreshBtn').on('click', function () {
        reloadList();
    });

    $('#ceSearch').on('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () {
            reloadList();
        }, 300);
    });

    $('#ceExportBtn').on('click', function () {
        var columns = (window.ChequeEntryGrid && window.ChequeEntryGrid.getExportColumns)
            ? window.ChequeEntryGrid.getExportColumns()
            : [];
        var payload = {
            columns: columns,
            search: $('#ceSearch').val().trim(),
            settings_branch_id: settingsBranchId,
            filters: activeFilters
        };
        fetch('ajax/export-cheque-entry-excel.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        }).then(function (res) {
            if (!res.ok) {
                return res.text().then(function (t) { throw new Error(t || 'Export failed'); });
            }
            var cd = res.headers.get('Content-Disposition') || '';
            var match = cd.match(/filename="([^"]+)"/i);
            var filename = match ? match[1] : ('Cheque_Entry_' + new Date().toISOString().slice(0, 10) + '.xlsx');
            return res.blob().then(function (blob) {
                var a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                setTimeout(function () {
                    URL.revokeObjectURL(a.href);
                    a.remove();
                }, 200);
            });
        }).catch(function (err) {
            alert(err && err.message ? err.message : 'Export failed.');
        });
    });

    $('#ceColumnsBtn').on('click', function () {
        if (window.ChequeEntryGrid && window.ChequeEntryGrid.openColumnsModal) {
            window.ChequeEntryGrid.openColumnsModal();
        }
    });

    updateFooter();
    syncFilterFormFromActive();
    updateFilterBadge();

    // Deep-link from Account Ledger View (cheque-entry.php?id=…)
    (function openFromQueryId() {
        try {
            var params = new URLSearchParams(window.location.search || '');
            var qid = parseInt(params.get('id') || '0', 10) || 0;
            if (qid <= 0) return;
            $.getJSON('ajax/cheque-entry.php', {
                action: 'get',
                id: qid,
                settings_branch_id: settingsBranchId
            }).done(function (res) {
                if (res && res.success && res.entry) {
                    fillForm(res.entry);
                    $('#chequeEntryModalTitle').text('Cheque Entry');
                    setFormLocked(true);
                    modal.modal('show');
                }
            });
        } catch (e) {}
    })();
})();
</script>
<script src="assets/libs/sortablejs/sortable.js"></script>
<script src="js/cheque-entry-grid.js"></script>
<script>
jQuery(function () {
    if (window.ChequeEntryGrid && typeof window.ChequeEntryGrid.init === 'function') {
        window.ChequeEntryGrid.init();
    }
});
</script>
</body>
</html>
