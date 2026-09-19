<?php 
session_start();
require_once 'config.php';
require_once __DIR__ . '/includes/auragold_party_select2.php';
require_once __DIR__ . '/includes/user_management_schema.php';

// Load master data
$metals = getList("SELECT id, display_name, system_name FROM tbl_metal WHERE status = 1 " . auragold_master_list_sql_suffix($conn, 'tbl_metal') . " ORDER BY id ASC");
require_once __DIR__ . '/includes/auragold_voucher_runtime_settings.php';
$auragold_voucher_runtime_client = auragold_voucher_runtime_bootstrap($conn, $metals, 'Receipt Voucher');
$branches = getListMaster("SELECT id, name, code FROM tbl_branches WHERE status = 1 ORDER BY name ASC");
$products = getList("SELECT id, name FROM tbl_products WHERE status = 1 ORDER BY name ASC LIMIT 100");

$currencies = function_exists('auragold_load_master_currencies')
    ? auragold_load_master_currencies($conn)
    : [];
if (!is_array($currencies)) {
    $currencies = [];
}
require_once __DIR__ . '/includes/auragold_voucher_currency_boot.php';


// Get voucher types
$voucher_types = getList("SELECT id, name FROM tbl_voucher_types WHERE status = 1 ORDER BY name ASC");
if (empty($voucher_types)) {
    $voucher_types = [
        ['id' => 1, 'name' => 'Advance'],
        ['id' => 2, 'name' => 'Payment'],
        ['id' => 3, 'name' => 'Receipt']
    ];
}

// Get payment types
$payment_types = ['Cash', 'Bank', 'Cheque', 'UPI', 'Card', 'Metal'];

// Ledger groups for customer creation modal
$ledger_groups = [
    ['id' => 1, 'name' => 'Sundry Debtors'],
    ['id' => 2, 'name' => 'Sundry Creditors'],
    ['id' => 3, 'name' => 'Bank Accounts'],
];

// Sundry options for customer creation modal
$sundry_options = [
    ['id' => 1, 'name' => 'Sundry Debtors'],
    ['id' => 2, 'name' => 'Sundry Creditors'],
];

// Sales Person dropdown — branch-scoped tbl_users (same as payment-voucher / sale-invoice)
$sales_person_users = auragold_sales_person_user_display_names($conn_master);

// Next voucher no.: Bill Series for Receipt Voucher (branch-wise tbl_bill_series), else legacy RV-1, RV-2
$next_voucher_no = function_exists('getNextReceiptVoucherNo') ? getNextReceiptVoucherNo($conn) : 'RV-1';

// Load voucher for editing if ID provided
$edit_voucher_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$edit_voucher = null;
$edit_items = [];

$auragold_voucher_ds_kind = 'receipt_voucher';
$auragold_voucher_ds_db_id = (int) ($edit_voucher_id ?? 0);

if ($edit_voucher_id > 0) {
    $edit_voucher = getRecord("SELECT * FROM tbl_receipt_vouchers WHERE id = $edit_voucher_id");
    if ($edit_voucher) {
        $edit_items = getList("SELECT * FROM tbl_receipt_voucher_items WHERE voucher_id = $edit_voucher_id");
        $next_voucher_no = $edit_voucher['voucher_no'];
    }
}

$rv_sp_selected = '';
if (!empty($edit_voucher) && is_array($edit_voucher)) {
    $rv_sp_selected = trim((string)($edit_voucher['sales_person'] ?? ''));
} else {
    $rv_sp_selected = trim((string)($_SESSION['user_name'] ?? ''));
}
$rv_sp_in_list = false;
if ($rv_sp_selected !== '') {
    foreach ($sales_person_users as $_spn) {
        if (strcasecmp($rv_sp_selected, $_spn) === 0) {
            $rv_sp_in_list = true;
            break;
        }
    }
}

$rv_voucher_date_val = date('Y-m-d');
$rv_due_date_val = '';
if (!empty($edit_voucher) && is_array($edit_voucher)) {
    if (!empty($edit_voucher['voucher_date'])) {
        $rv_voucher_date_val = substr($edit_voucher['voucher_date'], 0, 10);
    }
    $rv_due_date_val = auragold_due_date_value($edit_voucher['due_date'] ?? null);
}
$rv_fixing_type = (!empty($edit_voucher) && is_array($edit_voucher))
    ? trim((string)($edit_voucher['fixing_type'] ?? 'Standard'))
    : 'Standard';
if ($rv_fixing_type !== 'Standard' && $rv_fixing_type !== 'Hedging') {
    $rv_fixing_type = 'Standard';
}

// Get list of saved vouchers for dropdown
$saved_vouchers = getList("SELECT id, voucher_no, customer_name, voucher_date, total_amount FROM tbl_receipt_vouchers ORDER BY id DESC LIMIT 50");
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Receipt Voucher - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?> Software</title>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <link rel="icon" type="image/jpeg" href="favicon.jpeg">
<?php include 'header-script.php';?>
</head>
<style>
    /* Full screen view - Compact */
    body {
        margin: 0;
        padding: 0;
        overflow-x: hidden;
        overflow-y: hidden;
        height: 100vh;
        background: linear-gradient(135deg, #f5f7fa 0%, #eeeeee 100%);
        font-family: Roboto, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    }
    
    html {
        height: 100vh;
        overflow: hidden;
    }
    
    .layout-wrapper {
        height: 100vh;
        overflow: hidden;
        background: linear-gradient(135deg, #f5f7fa 0%, #eeeeee 100%);
    }
    
    .layout-content {
        height: calc(100vh - 60px);
        overflow-y: auto;
        background: linear-gradient(135deg, #f5f7fa 0%, #eeeeee 100%);
        padding-bottom: 60px;
    }
    
    /* Layout adjustments */
    .layout-container {
        margin-left: 260px;
    }
    
    @media (max-width: 991.98px) {
        .layout-container {
            margin-left: 0;
        }
    }
    
    /* Full width content */
    .layout-content {
        margin: 0 !important;
        padding: 0 !important;
    }
    
    
    
    .row {
        margin-left: 0;
        margin-right: 0;
        padding-top: 0;
    }
    
    .row > [class*="col-"] {
        padding-left: 15px;
        padding-right: 15px;
    }
    
    .card {
        margin-left: 0;
        margin-right: 0;
        border: 1px solid #e2e8f0;
        border-radius: 4px;
        background: #fff;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-bottom: 10px;
    }
    
    .card-body {
        padding: 10px 12px;
    }
    
    /* Billing card + form: match sale-invoice.php / payment-voucher.php */
    .receipt-voucher-page .pv-billing-card.card {
        border: 1px solid #d4af37;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
        margin-bottom: 1rem;
        background: #fff;
    }
    .receipt-voucher-page .pv-billing-card .card-body {
        padding: 1rem 1.25rem;
    }
    .receipt-voucher-page .billing-form .form-group {
        margin-bottom: 3px;
        display: flex;
        align-items: center;
    }
    .receipt-voucher-page .billing-form label {
        font-weight: 600;
        font-size: 11px;
        margin-bottom: 0;
        margin-right: 3px;
        color: #000000;
        letter-spacing: 0.01em;
        flex-shrink: 0;
        text-transform: uppercase;
    }
    .receipt-voucher-page .billing-form .form-control,
    .receipt-voucher-page .billing-form .form-control-sm {
        font-size: 0.8rem;
        padding: 0.5rem 0.75rem;
        height: calc(1.5em + 1rem + 2px);
        border-radius: 6px;
        transition: all 0.2s ease;
        background: #ffffff;
        flex: 1;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    }
    .receipt-voucher-page .billing-form .form-control:focus,
    .receipt-voucher-page .billing-form .form-control-sm:focus {
        outline: none;
        background: #ffffff;
    }
    .receipt-voucher-page .billing-form .form-control-sm {
        font-size: 0.75rem;
        padding: 0.35rem 0.5rem;
        height: calc(1.5em + 0.7rem + 2px);
        border-radius: 4px;
    }
    .receipt-voucher-page .billing-form .form-group > div:not(.input-group):not(.d-flex) {
        flex: 1;
    }
    .receipt-voucher-page .billing-form .input-group {
        flex: 1;
    }
    .receipt-voucher-page .billing-form .form-group .d-flex {
        flex: 1;
    }
    .receipt-voucher-page .billing-form select.form-control,
    .receipt-voucher-page .billing-form select.form-control-sm {
        background-position: right 0.5rem center;
        background-repeat: no-repeat;
        background-size: 1.5em 1.5em;
        padding-right: 2.5rem;
    }
    
    /* Table styles */
    .table {
        font-size: 0.75rem;
        margin-bottom: 0;
    }
    
    /* Receipt / payment list: column drag + resize + ellipsis (same pattern as payment-voucher.php) */
    #receiptTable,
    #paymentListTable {
        table-layout: fixed;
        width: max-content;
        min-width: 100%;
        border-collapse: collapse;
    }
    #receiptTable.table-bordered thead th,
    #receiptTable.table-bordered tbody td,
    #receiptTable.table-bordered tfoot td,
    #paymentListTable.table-bordered thead th,
    #paymentListTable.table-bordered tbody td {
        border-left: none !important;
    }
    #receiptTable.table-bordered thead th:not(:last-child),
    #receiptTable.table-bordered tbody td:not(:last-child),
    #receiptTable.table-bordered tfoot td:not(:last-child),
    #paymentListTable.table-bordered thead th:not(:last-child),
    #paymentListTable.table-bordered tbody td:not(:last-child) {
        border-right: 1px solid #dee2e6 !important;
    }
    #receiptTable thead th[data-column]:not([data-column="actions"]),
    #paymentListTable thead th[data-column]:not([data-column="actions"]) {
        vertical-align: middle;
        position: relative;
        min-width: 48px;
        box-sizing: border-box;
    }
    #receiptTable thead th[data-column]:not([data-column="actions"]) .pv-th-inner,
    #paymentListTable thead th[data-column]:not([data-column="actions"]) .pv-th-inner {
        display: flex;
        align-items: center;
        gap: 4px;
        width: 100%;
        min-width: 0;
        min-height: 22px;
        box-sizing: border-box;
    }
    #receiptTable thead th[data-column] .pv-th-inner .pv-th-text,
    #paymentListTable thead th[data-column] .pv-th-inner .pv-th-text {
        flex: 1 1 auto;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    #receiptTable .pv-col-drag-h,
    #paymentListTable .pv-col-drag-h {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-right: 4px;
        padding: 0 2px;
        cursor: grab;
        user-select: none;
        vertical-align: middle;
        line-height: 0;
    }
    #receiptTable .pv-col-drag-h .feather.icon-move,
    #paymentListTable .pv-col-drag-h .feather.icon-move {
        width: 14px;
        height: 14px;
        font-size: 14px;
        line-height: 1;
        flex-shrink: 0;
        pointer-events: none;
    }
    #receiptTable thead .pv-col-drag-h .feather.icon-move,
    #paymentListTable thead .pv-col-drag-h .feather.icon-move {
        color: #c5a864;
    }
    #receiptTable .pv-col-drag-h:active,
    #paymentListTable .pv-col-drag-h:active {
        cursor: grabbing;
    }
    /* Receipt / payment-list grids: match assign-inventory Tabulator header (navy + white text) */
    #receiptTable thead th[data-column],
    #paymentListTable thead th[data-column] {
        background: #11294b !important;
        color: #fff !important;
        border-color: rgba(255, 255, 255, 0.15);
    }
    /* Column resize: narrow hit zone; gold only on hover */
    #receiptTable .pv-col-resizer,
    #paymentListTable .pv-col-resizer {
        position: absolute;
        top: 0;
        right: 0;
        width: 5px;
        min-width: 5px;
        max-width: 5px;
        cursor: ew-resize;
        user-select: none;
        z-index: 3;
        height: 100%;
        background: transparent !important;
        box-shadow: none;
    }
    #receiptTable .pv-col-resizer:hover,
    #paymentListTable .pv-col-resizer:hover {
        background: rgba(212, 175, 55, 0.45) !important;
    }
    #receiptTable tbody td[data-column]:not([data-column="actions"]),
    #receiptTable tfoot td[data-column]:not([data-column="actions"]),
    #paymentListTable tbody td[data-column]:not([data-column="actions"]) {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        max-width: 100%;
        vertical-align: middle;
    }
    #receiptTable td[data-column] input:not([type="hidden"]),
    #receiptTable td[data-column] select,
    #receiptTable td[data-column] textarea,
    #paymentListTable td[data-column] input:not([type="hidden"]),
    #paymentListTable td[data-column] select {
        min-width: 0;
        max-width: 100%;
        box-sizing: border-box;
    }
    #receiptTable td[data-column="actions"],
    #receiptTable th[data-column="actions"],
    #paymentListTable td[data-column="actions"],
    #paymentListTable th[data-column="actions"] {
        width: 72px;
        min-width: 72px;
        max-width: 120px;
        overflow: visible;
        white-space: nowrap;
    }
    
    .table thead th {
        background: #f8fafc;
        border-bottom: 2px solid #e2e8f0;
        font-weight: 600;
        color: #ffffff;
        padding: 6px 5px;
        white-space: nowrap;
        font-size: 0.75rem;
    }
    
    #paymentListTableBody tr[data-voucher-id]:hover {
        background-color: #e8f0fe !important;
    }
    #paymentListTableBody tr[data-voucher-id].editing-row {
        background-color: #e3f2fd !important;
    }
    .table tbody td {
        padding: 5px;
        vertical-align: middle;
        border-bottom: 1px solid #e2e8f0;
        font-size: 0.75rem;
    }
    
    .table tfoot td {
        background: #f8fafc;
        font-weight: 600;
        padding: 6px 5px;
        font-size: 0.75rem;
    }
    
    .table .form-control-sm {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
        height: 26px;
    }
    
    /* Button styles */
    .btn-sm {
        padding: 0.3rem 0.6rem;
        font-size: 0.75rem;
        border-radius: 3px;
    }
    
    /* Previous Balance — match purchase-invoice.php summary-section */
    .card h6.transaction-history-heading {
        font-size: 0.8rem;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 8px;
    }
    .pv-rv-prev-balance.summary-section {
        margin-bottom: 6px;
        padding-bottom: 6px;
        border-bottom: 1px solid #e2e8f0;
    }
    .pv-rv-prev-balance.summary-section h6 {
        font-size: 12px;
        margin-bottom: 0.75rem;
        font-weight: 700;
        color: #11294b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #e2e8f0;
        position: relative;
    }
    .pv-rv-prev-balance.summary-section h6::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        width: 40px;
        height: 2px;
        background: linear-gradient(90deg, #c5a864 0%, transparent 100%);
    }
    .pv-rv-prev-balance .summary-row {
        display: flex;
        justify-content: space-between;
        font-size: 0.8rem;
        padding: 2px;
        align-items: center;
    }
    .pv-rv-prev-balance .summary-label {
        font-weight: 600;
        color: #000000;
        font-size: 11px;
        text-transform: uppercase;
        white-space: nowrap;
    }
    .pv-rv-prev-balance .summary-value {
        font-weight: 700;
        color: #1e293b;
        font-size: 0.85rem;
    }
    .pv-rv-prev-balance .summary-section .form-control-sm {
        width: 90px;
        text-align: right;
        font-size: 0.75rem;
        height: 28px;
    }
    
    /* Nav tabs (scoped to main .row only — unscoped .nav-tabs breaks top-navbar Transaction label) */
    .layout-content .row .nav-tabs {
        border-bottom: 2px solid #e2e8f0;
        margin-bottom: 8px;
    }
    
    .layout-content .row .nav-tabs .nav-link {
        border: none;
        border-bottom: 2px solid transparent;
        color: #64748b;
        padding: 6px 12px;
        font-weight: 500;
        font-size: 0.8rem;
    }
    
    .layout-content .row .nav-tabs .nav-link.active {
        color: #11294b;
        border-bottom-color: #11294b;
        background: transparent;
    }
    
    .layout-content .row .nav-tabs .nav-link:hover {
        border-bottom-color: #cbd5e1;
        color: #11294b;
    }
    
    /* Compact row spacing */
    .row > [class*="col-"] {
        padding-left: 8px;
        padding-right: 8px;
    }
    
    /* Compact container */
    
    
    /* Payment icons compact */
    .payment-icons {
        gap: 6px !important;
        margin-bottom: 8px !important;
    }
    
    .payment-icon {
        width: 45px !important;
        height: 45px !important;
        font-size: 0.9rem !important;
    }
    
    /* Comment textarea compact */
    textarea.form-control {
        font-size: 0.75rem;
        padding: 0.4rem 0.6rem;
        min-height: 50px;
    }
    
    /* Customer Creation Modal Styles */
    .add-customer-icon {
        transition: all 0.3s ease;
    }
    
    .add-customer-icon:hover {
        color: #764ba2 !important;
        transform: translateY(-50%) scale(1.1);
    }
    
    /* Customer Suggestions Dropdown */
    #customerSuggestions {
        font-family: inherit;
    }
    .customer-suggestion-item:hover {
        background: #f8fafc !important;
    }
    .customer-suggestion-item.focused {
        background: #f1f5f9 !important;
    }
    .billing-form #customerName.form-control-sm:focus {
        border-color: #e2e8f0;
        border-bottom: 2px solid #f97316;
        box-shadow: none;
    }
    
    #customerCreationModal .form-group {
        margin-bottom: 0.75rem;
    }
    
    #customerCreationModal .form-group label {
        font-weight: 500;
        color: #334155;
        margin-bottom: 0.25rem;
        font-size: 0.8rem;
        display: block;
    }
    
    #customerCreationModal .form-control {
        border: 1px solid #e2e8f0;
        border-radius: 4px;
        padding: 0.4rem 0.75rem;
        font-size: 0.85rem;
        height: 32px;
        line-height: 1.5;
    }
    
    #customerCreationModal .form-control:focus {
        border-color: #c5a864;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        outline: none;
    }
    
    #customerCreationModal .input-group {
        position: relative;
    }
    
    #customerCreationModal .input-group .form-control {
        padding-left: 30px;
        height: 32px;
    }
    
    #customerCreationModal .input-group i {
        position: absolute;
        left: 8px;
        top: 50%;
        transform: translateY(-50%);
        z-index: 10;
        color: #94a3b8;
        font-size: 0.9rem;
    }
    
    #customerCreationModal .input-group-append .input-group-text {
        padding: 0.4rem 0.5rem;
        height: 32px;
        border: 1px solid #e2e8f0;
        border-left: none;
        background: #f8fafc;
    }
    
    #customerCreationModal .input-group-append .input-group-text i {
        font-size: 0.85rem;
        color: #64748b;
    }
    
    #customerCreationModal .nav-tabs {
        border-bottom: 2px solid #e2e8f0;
        margin-bottom: 1rem;
    }
    
    #customerCreationModal .nav-tabs .nav-link {
        color: #64748b;
        border: none;
        border-bottom: 2px solid transparent;
        padding: 0.5rem 1rem;
        font-size: 0.85rem;
        font-weight: 500;
    }
    
    #customerCreationModal .nav-tabs .nav-link.active {
        color: #c5a864;
        border-bottom-color: #c5a864;
        font-weight: 600;
    }
    
    #customerCreationModal .nav-tabs .nav-link:hover {
        border-bottom-color: #cbd5e1;
        color: #ffffff;
    }
    
    #customerCreationModal .form-check {
        margin-bottom: 0;
    }
    
    #customerCreationModal .form-check-label {
        font-size: 0.8rem;
        color: #334155;
        margin-left: 0.25rem;
    }
    
    #customerCreationModal .form-check-input {
        width: 1rem;
        height: 1rem;
        margin-top: 0.15rem;
    }
    
    /* Share Holders Table Styles */
    #customerCreationModal #shareHoldersTable {
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        overflow: hidden;
    }
    
    #customerCreationModal #shareHoldersTable thead th {
        white-space: nowrap;
    }
    
    #customerCreationModal #shareHoldersTable tbody tr:hover {
        background: #f8fafc;
    }
    
    #customerCreationModal #shareHoldersTable tbody td {
        vertical-align: middle;
    }
    
    #customerCreationModal #shareHolderDocumentUpload:hover {
        border-color: #c5a864;
        background: #f1f5f9;
    }
    
    #customerCreationModal .share-holder-file-item {
        transition: all 0.2s ease;
    }
    
    #customerCreationModal .share-holder-file-item:hover {
        background: #f1f5f9 !important;
    }
    
    /* Item Type Tax Table Styles */
    #customerCreationModal .item-tax-table {
        width: 100%;
        border-collapse: collapse;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        overflow: hidden;
        font-size: 0.85rem;
    }
    
    #customerCreationModal .item-tax-table thead {
        background: #11294b;
        color: #fff;
    }
    
    #customerCreationModal .item-tax-table thead th {
        padding: 0.6rem 1rem;
        text-align: left;
        font-weight: 600;
        font-size: 0.85rem;
        border-right: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    #customerCreationModal .item-tax-table thead th:last-child {
        border-right: none;
    }
    
    #customerCreationModal .item-tax-table tbody tr {
        border-bottom: 1px solid #e2e8f0;
    }
    
    #customerCreationModal .item-tax-table tbody tr:last-child {
        border-bottom: none;
    }
    
    #customerCreationModal .item-tax-table tbody tr:hover {
        background: #f8fafc;
    }
    
    #customerCreationModal .item-tax-table tbody td {
        padding: 0.6rem 1rem;
        font-size: 0.85rem;
        color: #334155;
        vertical-align: middle;
    }
    
    #customerCreationModal .item-tax-table tbody td:first-child {
        font-weight: 500;
        color: #1e293b;
    }
    
    #customerCreationModal .item-tax-table tbody td select {
        width: 100%;
        padding: 0.4rem 0.6rem;
        border: 1px solid #e2e8f0;
        border-radius: 4px;
        font-size: 0.85rem;
        background: #fff;
        cursor: pointer;
        height: 32px;
    }
    
    #customerCreationModal .item-tax-table tbody td select:focus {
        border-color: #c5a864;
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        outline: none;
    }
    
    /* Modal Right Side */
    .modal.fade.right .modal-dialog {
        transition: transform 0.3s ease-out;
        transform: translateX(100%);
    }
    
    .modal.fade.right.show .modal-dialog {
        transform: translateX(0);
    }
    
    .modal-dialog-right {
        position: fixed;
        right: 0;
        top: 0;
        margin: 0;
    }
</style>
<body>
    <div class="layout-wrapper layout-2">
        <div class="layout-inner">
            <!-- [ Layout sidenav ] Start -->
            <div id="layout-sidenav" class="layout-sidenav sidenav sidenav-vertical bg-white logo-dark">
                <!-- Brand demo -->
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
                <!-- Links -->
                <ul class="sidenav-inner py-1">
                    <li class="sidenav-item">
                        <a href="dashboard.php" class="sidenav-link">
                            <i class="sidenav-icon feather icon-home"></i>
                            <div>Dashboard</div>
                        </a>
                    </li>
                </ul>
            </div>
            <!-- [ Layout sidenav ] End -->

            <!-- [ Layout container ] Start -->
            <div class="layout-container">
                <!-- [ Layout navbar ( Header ) ] Start -->
                <nav class="layout-navbar navbar navbar-expand-lg align-items-lg-center bg-dark container-p-x" id="layout-navbar">
                    <a href="index.php" class="navbar-brand app-brand demo d-lg-none py-0 mr-4">
                        <span class="app-brand-logo demo">
                            <img src="assets/img/logo-dark.png" alt="Brand Logo" class="img-fluid">
                        </span>
                        <span class="app-brand-text demo font-weight-normal ml-2">AuraGold</span>
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
                    <div class="container-fluid flex-grow-1 receipt-voucher-page" style="padding-top: 0; padding-bottom: 0;">
                        <?php include 'sidebar.php';?>

                        <div class="row" style="margin-left: 0; margin-right: 0;">
                            <!-- Main Content Area -->
                            <div class="col-lg-9">
                                <!-- Page Header -->
                                <div class="card mb-1" style="background: #11294b; color: #fff; border: none; margin-left: 0; margin-right: 0;">
                                    <div class="card-body" style="padding: 6px 12px;">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <div>
                                                <h5 style="margin: 0; font-weight: 600; font-size: 0.9rem;">Receipt Voucher No: <span id="voucherNoDisplay"><?php echo htmlspecialchars($next_voucher_no); ?></span></h5>
                                            </div>
                                            <div style="display: flex; gap: 6px;">
                                                <button type="button" class="btn btn-sm" onclick="resetVoucher()" style="background: rgba(255,255,255,0.2); border: none; color: #fff; padding: 0.25rem 0.5rem; font-size: 0.7rem;">New +</button>
                                                <button type="button" class="btn btn-sm" onclick="saveVoucher()" style="background: rgba(255,255,255,0.2); border: none; color: #fff; padding: 0.25rem 0.5rem; font-size: 0.7rem;">Save</button>
                                                <button type="button" class="btn btn-sm" style="background: rgba(255,255,255,0.2); border: none; color: #fff; padding: 0.25rem 0.5rem; font-size: 0.7rem;">+ Import</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Voucher Details Form (layout matches payment-voucher / sale-invoice billing-form) -->
                                <div class="card mb-4 pv-billing-card" style="margin-left: 0; margin-right: 0;">
                                    <div class="card-body billing-form">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label>Search Receipt Voucher</label>
                                                    <div style="position: relative;">
                                                        <input type="text" id="searchVoucherInput" class="form-control form-control-sm" placeholder="Search by voucher no or customer..." autocomplete="off" style="padding-right: 35px;">
                                                        <i class="feather icon-search" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 12px; pointer-events: none;"></i>
                                                        <div id="searchVoucherDropdown" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 4px; max-height: 280px; overflow-y: auto; z-index: 1050; box-shadow: 0 4px 12px rgba(0,0,0,0.15); margin-top: 2px;"></div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label>Name *</label>
                                                    <div style="display:flex;align-items:stretch;gap:4px;"><div class="auragold-party-select2-wrap"><select class="form-control form-control-sm" id="customerId" name="customer_id" required><option value="">Select customer...</option></select><input type="hidden" id="customerName" name="customer_name" value=""></div><button type="button" class="btn btn-sm btn-outline-secondary p-0" id="addCustomerBtn" title="Add / Edit Customer" style="width:32px;min-width:32px;line-height:1;align-self:stretch;"><i class="feather icon-plus"></i></button></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label>Against Of</label>
                                                    <div class="d-flex align-items-stretch" style="gap: 4px;">
                                                        <select class="form-control form-control-sm" id="againstOf" style="flex: 1; min-width: 0;">
                                                            <option value="">Select option</option>
                                                            <option value="Sale Order">Sale Order</option>
                                                            <option value="Sale Invoice">Sale Invoice</option>
                                                        </select>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="againstOfPickDocBtn" title="Choose source document" style="flex-shrink: 0; padding-left: 0.45rem; padding-right: 0.45rem;"><i class="feather icon-list"></i></button>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label>Currency</label>
                                                    <?php
                                                        $selected_currency = (!empty($edit_order) && is_array($edit_order)) ? ($edit_order['currency'] ?? '') : '';
                                                        include __DIR__ . '/includes/auragold_voucher_currency_combo.php';
                                                        ?>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label>Ref No.</label>
                                                    <input type="text" class="form-control form-control-sm" id="refNo" placeholder="Reference number">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label>Date</label>
                                                    <input type="date" class="form-control form-control-sm" id="voucherDate" value="<?php echo htmlspecialchars($rv_voucher_date_val); ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label>Due Date</label>
                                                    <input type="date" class="form-control form-control-sm" id="dueDate" value="<?php echo htmlspecialchars($rv_due_date_val); ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label>Sales Person</label>
                                                    <select class="form-control form-control-sm" id="salesPerson" data-placeholder="Sales Person">
                                                        <option value="">Select</option>
                                                        <?php foreach ($sales_person_users as $sp_name): ?>
                                                        <option value="<?php echo htmlspecialchars($sp_name); ?>"<?php echo ($rv_sp_selected !== '' && strcasecmp($rv_sp_selected, $sp_name) === 0) ? ' selected' : ''; ?>><?php echo htmlspecialchars($sp_name); ?></option>
                                                        <?php endforeach; ?>
                                                        <?php if ($rv_sp_selected !== '' && !$rv_sp_in_list): ?>
                                                        <option value="<?php echo htmlspecialchars($rv_sp_selected); ?>" selected><?php echo htmlspecialchars($rv_sp_selected); ?></option>
                                                        <?php endif; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label>Fixing Type</label>
                                                    <select class="form-control form-control-sm" id="fixingType">
                                                        <option value="Standard"<?php echo ($rv_fixing_type === 'Standard') ? ' selected' : ''; ?>>Standard</option>
                                                        <option value="Hedging"<?php echo ($rv_fixing_type === 'Hedging') ? ' selected' : ''; ?>>Hedging</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label>Receipt No.</label>
                                                    <input type="text" class="form-control form-control-sm" id="receiptNo" placeholder="Receipt No">
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label>Voucher Type</label>
                                                    <select class="form-control form-control-sm" id="voucherType">
                                                        <option value="">Select</option>
                                                        <?php foreach($voucher_types as $vt): ?>
                                                        <option value="<?php echo htmlspecialchars($vt['name']); ?>"><?php echo htmlspecialchars($vt['name']); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label>Against</label>
                                                    <select class="form-control form-control-sm" id="against">
                                                        <option value="">Select</option>
                                                        <option value="Against Advance">Against Advance</option>
                                                        <option value="Sale Order">Sale Order</option>
                                                        <option value="Sale Invoice">Sale Invoice</option>
                                                        <option value="Purchase Order">Purchase Order</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label>Layaways</label>
                                                    <select class="form-control form-control-sm" id="layaways">
                                                        <option value="">Select Layaways</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Receipt Section -->
                                <div class="card mb-1" style="margin-left: 0; margin-right: 0;">
                                    <div class="card-body" style="padding: 8px 12px;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                            <ul class="nav nav-tabs" style="border-bottom: 2px solid #e2e8f0; margin-bottom: 0; flex: 1;">
                                                <li class="nav-item">
                                                    <a class="nav-link active" data-toggle="tab" href="#receiptTab">Receipt</a>
                                                </li>
                                            </ul>
                                            <button type="button" class="btn btn-sm" onclick="openReceiptColumnsModal()" style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 0.25rem 0.5rem; font-size: 0.7rem; margin-left: 8px;" title="Column Settings">
                                                <i class="feather icon-settings" style="font-size: 0.8rem;"></i>
                                            </button>
                                        </div>
                                        <div class="tab-content">
                                            <div id="receiptTab" class="tab-pane fade show active">
                                                <!-- Payment Method Icons -->
                                                <div class="payment-icons" style="display: flex; gap: 10px; margin-bottom: 15px; flex-wrap: wrap;">
                                                    <div class="payment-icon payment-cash" title="Cash" style="cursor: pointer; transition: all 0.3s ease;">
                                                        <img src="icons/cash.jpeg" alt="Cash" style="width: 45px; height: 45px;">
                                                    </div>
                                                    <div class="payment-icon payment-bank" title="Bank" style="cursor: pointer; transition: all 0.3s ease;">
                                                        <img src="icons/bank.jpeg" alt="Bank" style="width: 45px; height: 45px;">
                                                    </div>
                                                    <div class="payment-icon payment-cheque" title="Cheque" style="cursor: pointer; transition: all 0.3s ease;">
                                                        <img src="icons/cheque.jpeg" alt="Cheque" style="width: 45px; height: 45px;">
                                                    </div>
                                                    <div class="payment-icon payment-mobile" title="UPI/Mobile Payment" style="cursor: pointer; transition: all 0.3s ease;">
                                                        <img src="icons/upi.jpeg" alt="UPI/Mobile Payment" style="width: 45px; height: 45px;">
                                                    </div>
                                                    <div class="payment-icon payment-card" title="Card" style="cursor: pointer; transition: all 0.3s ease;">
                                                        <img src="icons/card.jpeg" alt="Card" style="width: 45px; height: 45px;">
                                                    </div>
                                                    <div class="payment-icon payment-exchange" title="Metal Exchange" style="cursor: pointer; transition: all 0.3s ease;">
                                                        <img src="icons/metal.jpeg" alt="Metal Exchange" style="width: 45px; height: 45px;">
                                                    </div>
                                                    <div class="payment-icon payment-jewelry" title="Scrap Payment" style="cursor: pointer; transition: all 0.3s ease;">
                                                        <img src="icons/scrap.jpeg" alt="Scrap Payment" style="width: 45px; height: 45px;">
                                                    </div>
                                                    <div class="payment-icon payment-diamond" title="Diamond" style="cursor: pointer; transition: all 0.3s ease;">
                                                        <img src="icons/diamond.jpeg" alt="Diamond" style="width: 45px; height: 45px;">
                                                    </div>
                                                    <div class="payment-icon payment-stone" title="Stone" style="cursor: pointer; transition: all 0.3s ease;">
                                                        <img src="icons/stone.jpeg" alt="Stone" style="width: 45px; height: 45px;">
                                                    </div>
                                                </div>
<?php require __DIR__ . '/includes/voucher_diamond_stone_panels.php'; ?>
                                                <div class="table-responsive" style="padding-top: 6px;">
                                                    <table class="table table-bordered table-sm" id="receiptTable" style="margin-bottom: 0; font-size: 0.75rem;">
                                                        <thead>
                                                            <tr>
                                                                <th data-column="payment-type"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Payment Type</th>
                                                                <th data-column="diamond-category"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Diamond Category</th>
                                                                <th data-column="transaction-no"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Transaction No.</th>
                                                                <th data-column="transfer-from"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Transfer From</th>
                                                                <th data-column="deposit-into"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Deposit Into</th>
                                                                <th data-column="product"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Product</th>
                                                                <th data-column="cheque-dt"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Cheque Dt.</th>
                                                                <th data-column="weight"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Weight</th>
                                                                <th data-column="metal"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Metal</th>
                                                                <th data-column="quantity"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Quantity</th>
                                                                <th data-column="purity-carat"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Purity / Carat</th>
                                                                <th data-column="purity-wt"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Purity Wt</th>
                                                                <th data-column="rate"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Rate</th>
                                                                <th data-column="amount"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Amount</th>
                                                                <th data-column="item-code"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Item Code</th>
                                                                <th data-column="barcode-no"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Barcode No.</th>
                                                                <th data-column="card-no"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Card No.</th>
                                                                <th data-column="actions" style="width: 80px;">Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="receiptTableBody">
                                                            <tr class="no-payment-row">
                                                                <td colspan="18" class="text-center text-muted py-2" style="font-size: 0.75rem;">No payment entries</td>
                                                            </tr>
                                                        </tbody>
                                                        <tfoot id="receiptTableFooter" style="display: none;">
                                                            <tr style="background: #f8fafc; font-weight: 600;">
                                                                <td data-column="payment-type"></td>
                                                                <td data-column="diamond-category"></td>
                                                                <td data-column="transaction-no"></td>
                                                                <td data-column="transfer-from"></td>
                                                                <td data-column="deposit-into"></td>
                                                                <td data-column="product"></td>
                                                                <td data-column="cheque-dt"></td>
                                                                <td data-column="weight"></td>
                                                                <td data-column="metal"></td>
                                                                <td data-column="quantity" style="text-align: right; color: #11294b;"><span id="receiptTotalQuantity">0.00</span></td>
                                                                <td data-column="purity-carat"></td>
                                                                <td data-column="purity-wt"></td>
                                                                <td data-column="rate"></td>
                                                                <td data-column="amount" style="text-align: right; color: #11294b; font-weight: 700;">Total: <span id="receiptTotalAmount">0.00</span></td>
                                                                <td data-column="item-code"></td>
                                                                <td data-column="barcode-no"></td>
                                                                <td data-column="card-no"></td>
                                                                <td data-column="actions"></td>
                                                            </tr>
                                                        </tfoot>
                                                    </table>
                                                </div>
                                                <div style="margin-top: 8px;">
                                                    <label style="font-size: 0.75rem; margin-bottom: 3px;">Enter Comment</label>
                                                    <textarea class="form-control form-control-sm" id="comment" rows="2" placeholder="Enter comment here..." style="font-size: 0.75rem; padding: 0.4rem 0.6rem; min-height: 45px;"></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Payment List Section -->
                                <div class="card mb-1" style="margin-left: 0; margin-right: 0;">
                                    <div class="card-body" style="padding: 8px 12px;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                            <h6 style="margin: 0; font-weight: 600; font-size: 0.8rem;">Receipt List</h6>
                                            <div style="display: flex; gap: 6px; align-items: center;">
                                                <button type="button" id="paymentListFilterBtn" class="btn btn-sm" title="Advance Filter" style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 0.25rem 0.5rem; font-size: 0.7rem;">
                                                    <i class="feather icon-filter" style="font-size: 0.8rem;"></i>
                                                </button>
                                                <div class="voucher-list-export-wrap">
                                                    <button type="button" id="paymentListExportBtn" class="btn btn-sm" style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 0.25rem 0.5rem; font-size: 0.7rem;">Export <i class="feather icon-chevron-down" style="font-size: 0.65rem;"></i></button>
                                                    <div id="paymentListExportMenu" class="voucher-list-export-menu">
                                                        <button type="button" class="js-voucher-list-export-excel">Export in Excel</button>
                                                        <button type="button" class="js-voucher-list-export-pdf">Export in PDF</button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="payment-list-table-wrap">
                                            <table class="table table-bordered table-sm" id="paymentListTable" style="margin-bottom: 0;">
                                                <thead style="background: #f8fafc;">
                                                    <tr>
                                                        <th data-column="sr-no"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Sr.No.</th>
                                                        <th data-column="sales-person"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Sales Person</th>
                                                        <th data-column="voucher-date"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Date</th>
                                                        <th data-column="ledger-name"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Ledger Name</th>
                                                        <th data-column="invoice-no"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Invoice...</th>
                                                        <th data-column="branch-name"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Branch Name</th>
                                                        <th data-column="ref-no"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Ref No.</th>
                                                        <th data-column="against-voucher"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Against Voucher...</th>
                                                        <th data-column="against-vo"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Against Vo...</th>
                                                        <th data-column="total-amt"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Total A...</th>
                                                        <th data-column="total-wt"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Total W...</th>
                                                        <th data-column="cash"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Cash</th>
                                                        <th data-column="bank"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Bank</th>
                                                        <th data-column="cheque"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Cheque</th>
                                                        <th data-column="upi"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>UPI</th>
                                                        <th data-column="card"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Card</th>
                                                        <th data-column="metal"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Metal</th>
                                                        <th data-column="actions" style="width: 50px;">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="paymentListTableBody">
                                                    <tr class="no-rows">
                                                        <td colspan="18" class="text-center text-muted py-4">No Rows To Show</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="payment-list-pagination" id="paymentListPagination">
                                            <span class="payment-list-page-info">Loading…</span>
                                            <div style="display: flex; align-items: center; gap: 8px;">
                                                <label style="margin: 0;">Rows:</label>
                                                <select class="payment-list-page-size">
                                                    <option value="5">5</option>
                                                    <option value="10" selected>10</option>
                                                    <option value="20">20</option>
                                                </select>
                                                <button type="button" class="btn btn-sm btn-outline-secondary payment-list-page-prev">Previous</button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary payment-list-page-next">Next</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Sidebar -->
                            <div class="col-lg-3">
                                <!-- Previous Balance (shared: includes/previous-balance-panel.php + js/previous-balance-common.js) -->
                                <div class="card mb-1" style="margin-left: 0; margin-right: 0;">
                                    <div class="card-body pv-rv-prev-balance" style="padding: 8px 12px;">
                                        <?php include __DIR__ . '/includes/previous-balance-panel.php'; ?>
                                    </div>
                                </div>
                                
                                <!-- Transaction History Section -->
                                <div class="card mb-1" style="margin-left: 0; margin-right: 0;">
                                    <div class="card-body" style="padding: 8px 12px;">
                                        <h6 class="transaction-history-heading" style="border-bottom: 2px solid #fbbf24; padding-bottom: 4px;">Transaction History</h6>
                                        <div id="transactionHistoryContainer" style="max-height: 400px; overflow-y: auto;">
                                            <div class="text-center text-muted py-3" style="font-size: 0.75rem;" id="noHistoryMessage">
                                                Select a customer to view history
                                            </div>
                                            <table class="table table-sm" id="transactionHistoryTable" style="display: none; font-size: 0.7rem; margin-bottom: 0;">
                                                <thead>
                                                    <tr>
                                                        <th style="padding: 4px; font-size: 0.7rem;">Date</th>
                                                        <th style="padding: 4px; font-size: 0.7rem;">Type</th>
                                                        <th style="padding: 4px; font-size: 0.7rem;">No.</th>
                                                        <th style="padding: 4px; font-size: 0.7rem; text-align: right;">Amount</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="transactionHistoryBody">
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- [ content ] End -->
                </div>
                <!-- [ Layout content ] End -->
            </div>
            <!-- [ Layout container ] End -->
        </div>
    </div>
    <!-- / Layout wrapper -->

    <input type="hidden" id="voucherId" value="<?php echo $edit_voucher_id; ?>">
    <input type="hidden" id="voucherNo" value="<?php echo htmlspecialchars($next_voucher_no); ?>">

    <!-- Payment Modals -->
    <!-- Cash Payment Modal -->
    <div class="modal fade" id="cashPaymentModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header" style="background: #11294b; color: #fff; border: none;">
                    <h5 class="modal-title">Cash Payment</h5>
                    <button type="button" class="close" data-dismiss="modal" style="color: #fff;">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Deposit Into</label>
                        <select class="form-control" id="cashDepositInto">
                            <option value="Cash">Cash</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Amount</label>
                        <input type="text" class="form-control" id="cashAmount" value="0.00" step="0.01">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" style="border: 1px solid #ec4899; color: #ec4899; background: #fff;" data-dismiss="modal">Clear</button>
                    <button type="button" class="btn" style="background: #11294b; color: #fff; border: none;" onclick="saveReceiptPayment('cash')">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bank Payment Modal -->
    <div class="modal fade" id="bankPaymentModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header" style="background: #11294b; color: #fff; border: none;">
                    <h5 class="modal-title">Bank Payment</h5>
                    <button type="button" class="close" data-dismiss="modal" style="color: #fff;">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Deposit Into</label>
                        <select class="form-control" id="bankDepositInto">
                            <option value="">Select Bank</option>
                            <option value="KOTAK MAHINDRA BANK">KOTAK MAHINDRA BANK</option>
                            <option value="HDFC BANK">HDFC BANK</option>
                            <option value="ICICI BANK">ICICI BANK</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Trans No.</label>
                        <input type="text" class="form-control" id="bankTransNo" placeholder="Transaction Number">
                    </div>
                    <div class="form-group">
                        <label>Amount</label>
                        <input type="text" class="form-control" id="bankAmount" value="0.00" step="0.01">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" style="border: 1px solid #ec4899; color: #ec4899; background: #fff;" data-dismiss="modal">Clear</button>
                    <button type="button" class="btn" style="background: #11294b; color: #fff; border: none;" onclick="saveReceiptPayment('bank')">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Cheque Payment Modal -->
    <div class="modal fade" id="chequePaymentModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header" style="background: #11294b; color: #fff; border: none;">
                    <h5 class="modal-title">Cheque Payment</h5>
                    <button type="button" class="close" data-dismiss="modal" style="color: #fff;">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Deposit Into</label>
                        <select class="form-control" id="chequeDepositInto">
                            <option value="">Select Bank</option>
                            <option value="KOTAK MAHINDRA BANK">KOTAK MAHINDRA BANK</option>
                            <option value="HDFC BANK">HDFC BANK</option>
                            <option value="ICICI BANK">ICICI BANK</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Trans No.</label>
                        <input type="text" class="form-control" id="chequeTransNo" placeholder="Transaction Number">
                    </div>
                    <div class="form-group">
                        <label>Amount</label>
                        <input type="text" class="form-control" id="chequeAmount" value="0.00" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Cheque Dt.</label>
                        <input type="date" class="form-control" id="chequeDate" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" style="border: 1px solid #ec4899; color: #ec4899; background: #fff;" data-dismiss="modal">Clear</button>
                    <button type="button" class="btn" style="background: #11294b; color: #fff; border: none;" onclick="saveReceiptPayment('cheque')">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- UPI Payment Modal -->
    <div class="modal fade" id="upiPaymentModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header" style="background: #11294b; color: #fff; border: none;">
                    <h5 class="modal-title">UPI Payment</h5>
                    <button type="button" class="close" data-dismiss="modal" style="color: #fff;">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Deposit Into</label>
                        <select class="form-control" id="upiDepositInto">
                            <option value="">Select Account</option>
                            <option value="UPI">UPI</option>
                            <option value="PhonePe">PhonePe</option>
                            <option value="Google Pay">Google Pay</option>
                            <option value="Paytm">Paytm</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Trans No.</label>
                        <input type="text" class="form-control" id="upiTransNo" placeholder="Transaction Number">
                    </div>
                    <div class="form-group">
                        <label>Amount</label>
                        <input type="text" class="form-control" id="upiAmount" value="0.00" step="0.01">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" style="border: 1px solid #ec4899; color: #ec4899; background: #fff;" data-dismiss="modal">Clear</button>
                    <button type="button" class="btn" style="background: #11294b; color: #fff; border: none;" onclick="saveReceiptPayment('upi')">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Card Payment Modal -->
    <div class="modal fade" id="cardPaymentModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header" style="background: #11294b; color: #fff; border: none;">
                    <h5 class="modal-title">Card Payment</h5>
                    <button type="button" class="close" data-dismiss="modal" style="color: #fff;">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Deposit Into</label>
                        <select class="form-control" id="cardDepositInto">
                            <option value="">Select Account</option>
                            <option value="Credit Card">Credit Card</option>
                            <option value="Debit Card">Debit Card</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Trans No.</label>
                        <input type="text" class="form-control" id="cardTransNo" placeholder="Transaction Number">
                    </div>
                    <div class="form-group">
                        <label>Card No.</label>
                        <input type="text" class="form-control" id="cardNumber" placeholder="Card Number">
                    </div>
                    <div class="form-group">
                        <label>Amount</label>
                        <input type="text" class="form-control" id="cardAmount" value="0.00" step="0.01">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" style="border: 1px solid #ec4899; color: #ec4899; background: #fff;" data-dismiss="modal">Clear</button>
                    <button type="button" class="btn" style="background: #11294b; color: #fff; border: none;" onclick="saveReceiptPayment('card')">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Metal Exchange Payment Modal (same layout as payment voucher) -->
    <div class="modal fade" id="metalExchangeModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header" style="background: #11294b; color: #fff; border: none;">
                    <h5 class="modal-title">M. Exch. Payment</h5>
                    <button type="button" class="close" data-dismiss="modal" style="color: #fff;">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row" id="metalExchangeFormRow">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Metal Type</label>
                                <select class="form-control" id="metalExchangeMetal" tabindex="1">
                                    <option value="">Select Metal</option>
                                    <?php foreach($metals as $metal): ?>
                                    <option value="<?php echo $metal['id']; ?>"><?php echo htmlspecialchars($metal['display_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Gross Wt</label>
                                <input type="text" class="form-control" id="metalExchangeWeight" value="0" step="0.001" tabindex="4" onchange="calculateMetalExchange()" oninput="calculateMetalExchange()" onkeyup="calculateMetalExchange()">
                            </div>
                            <div class="form-group">
                                <label>Net Wt.</label>
                                <input type="text" class="form-control" id="metalExchangeNetWt" value="0" step="0.001" tabindex="7" onchange="calculateMetalExchange('netWt')" oninput="calculateMetalExchange('netWt')" onkeyup="calculateMetalExchange('netWt')">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Product</label>
                                <select class="form-control" id="metalExchangeProduct" tabindex="2">
                                    <option value="">Type product name to search...</option>
                                    <?php foreach($products as $product): ?>
                                    <option value="<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Purity / Karat</label>
                                <input type="text" class="form-control" id="metalExchangePurity" placeholder="Purity / Karat" value="1" tabindex="5" onchange="calculateMetalExchange()" oninput="calculateMetalExchange()" onkeyup="calculateMetalExchange()">
                            </div>
                            <div class="form-group">
                                <label>Rate</label>
                                <input type="text" class="form-control" id="metalExchangeRate" value="0" step="0.01" tabindex="8" onchange="calculateMetalExchange('rate')" oninput="calculateMetalExchange('rate')" onkeyup="calculateMetalExchange('rate')">
                            </div>
                            <div class="form-group">
                                <label>Amount</label>
                                <input type="text" class="form-control" id="metalExchangeAmount" value="0.00" step="0.01" tabindex="10" style="text-align: left;" onchange="calculateMetalExchange('amount')" oninput="calculateMetalExchange('amount')" onkeyup="calculateMetalExchange('amount')">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Quantity</label>
                                <input type="text" class="form-control" id="metalExchangeQty" value="1" step="0.01" tabindex="3" onchange="calculateMetalExchange()">
                            </div>
                            <div class="form-group">
                                <label>Purity Wt.</label>
                                <input type="text" class="form-control" id="metalExchangePurityWt" value="0" step="0.001" tabindex="6" readonly style="background: #f8fafc;">
                            </div>
                            <div class="form-group">
                                <label>Item Code</label>
                                <input type="text" class="form-control" id="metalExchangeItemCode" placeholder="Item Code" tabindex="9">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" style="border: 1px solid #ec4899; color: #ec4899; background: #fff;" onclick="clearPaymentModal('metal-exchange'); $('#metalExchangeModal').modal('hide');">CLEAR</button>
                    <button type="button" class="btn" style="background: #11294b; color: #fff; border: none;" onclick="saveReceiptPayment('metal-exchange')">SAVE</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scrap Payment Modal (same layout as payment voucher) -->
    <div class="modal fade" id="scrapPaymentModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header" style="background: #11294b; color: #fff; border: none;">
                    <h5 class="modal-title">Scrap Payment</h5>
                    <button type="button" class="close" data-dismiss="modal" style="color: #fff;">
                        <span>&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Metal Type</label>
                                <select class="form-control" id="scrapMetal" tabindex="1">
                                    <option value="">Select Metal</option>
                                    <?php foreach($metals as $metal): ?>
                                    <option value="<?php echo $metal['id']; ?>"><?php echo htmlspecialchars($metal['display_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Gross Wt</label>
                                <input type="text" class="form-control" id="scrapGrossWt" value="0" step="0.001" tabindex="4" onchange="calculateScrap()" oninput="calculateScrap()">
                            </div>
                            <div class="form-group">
                                <label>Net Wt.</label>
                                <input type="text" class="form-control" id="scrapNetWt" value="0" step="0.001" tabindex="7" readonly style="background: #f8fafc;">
                            </div>
                            <div class="form-group">
                                <label>Rate</label>
                                <input type="text" class="form-control" id="scrapRate" value="0" step="0.01" tabindex="10" onchange="calculateScrap()" oninput="calculateScrap()">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Product</label>
                                <select class="form-control" id="scrapProduct" tabindex="2">
                                    <option value="">Type product name to search...</option>
                                    <?php foreach($products as $product): ?>
                                    <option value="<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Less Wt.</label>
                                <input type="text" class="form-control" id="scrapLessWt" value="0" step="0.001" tabindex="5" onchange="calculateScrap()" oninput="calculateScrap()">
                            </div>
                            <div class="form-group">
                                <label>Purity / Karat</label>
                                <input type="text" class="form-control" id="scrapPurity" value="1" step="0.01" tabindex="8" onchange="calculateScrap()" oninput="calculateScrap()">
                            </div>
                            <div class="form-group">
                                <label>Amount</label>
                                <input type="text" class="form-control" id="scrapAmount" value="0.00" step="0.01" tabindex="11" onchange="calculateScrapAmount()" oninput="calculateScrapAmount()">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Quantity</label>
                                <input type="text" class="form-control" id="scrapQty" value="1" step="0.01" tabindex="3">
                            </div>
                            <div class="form-group">
                                <label>Stone Wt.</label>
                                <input type="text" class="form-control" id="scrapStoneWt" value="0" step="0.001" tabindex="6" onchange="calculateScrap()" oninput="calculateScrap()">
                            </div>
                            <div class="form-group">
                                <label>Purity Wt.</label>
                                <input type="text" class="form-control" id="scrapPurityWt" value="0" step="0.001" tabindex="9" readonly style="background: #f8fafc;">
                            </div>
                            <div class="form-group">
                                <label>Item Code</label>
                                <input type="text" class="form-control" id="scrapItemCode" placeholder="Item Code" tabindex="12">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" style="border: 1px solid #ec4899; color: #ec4899; background: #fff;" onclick="clearPaymentModal('scrap'); $('#scrapPaymentModal').modal('hide');">CLEAR</button>
                    <button type="button" class="btn" style="background: #11294b; color: #fff; border: none;" onclick="saveReceiptPayment('scrap')">SAVE</button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/customer-ledger-modal.php'; ?>

    <!-- Core scripts -->
    <?php auragold_echo_party_select2_styles(); ?>
<?php include 'footer-script.php';?>
<?php include __DIR__ . '/includes/auragold_voucher_currency_assets.php'; ?>
<?php auragold_echo_party_select2_scripts(); ?>
    <?php require __DIR__ . '/includes/voucher_diamond_stone_assets.php'; ?>
    <?php include __DIR__ . '/includes/auragold_voucher_runtime_scripts.php'; ?>
    <script src="assets/libs/sortablejs/sortable.js"></script>
    <script src="js/customer-ledger-address.js"></script>
    <script>window.PB_AUTO_INIT = false;</script>
    <script src="js/previous-balance-common.js"></script>
    <script src="assets/js/voucher-list-filter-export.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/voucher-list-filter-export.js'); ?>"></script>
    
    <script>
    // Master data for dropdowns
    const nationalities = <?php 
        $nationalities_js = getList("SELECT id, name FROM tbl_nationalities WHERE status = 1 ORDER BY name ASC");
        echo json_encode($nationalities_js ?: []); 
    ?>;
    
    let receiptRowIndex = 0;
    let currentEditingReceiptRowId = null;
    let currentPaymentType = null;

    // Payment icon click handlers - Use jQuery like purchase-invoice.php
    $(document).ready(function() {
        // Set up click handlers for payment icons
        $('.payment-icon').on('click', function() {
            const paymentType = $(this).hasClass('payment-cash') ? 'cash' :
                               $(this).hasClass('payment-bank') ? 'bank' :
                               $(this).hasClass('payment-cheque') ? 'cheque' :
                               $(this).hasClass('payment-mobile') ? 'upi' :
                               $(this).hasClass('payment-card') ? 'card' :
                               $(this).hasClass('payment-exchange') ? 'metal-exchange' :
                               $(this).hasClass('payment-jewelry') ? 'scrap' :
                               'other';
            console.log('Payment icon clicked:', paymentType);
            openPaymentModal(paymentType);
        });

        // Hover effects for payment icons
        $('.payment-icon').hover(
            function() {
                $(this).css({
                    'background': '#11294b',
                    'border-color': '#c5a864',
                    'color': 'white',
                    'transform': 'translateY(-2px) scale(1.05)',
                    'box-shadow': '0 4px 12px #c5a864'
                });
            },
            function() {
                $(this).css({
                    'background': '',
                    'border-color': '#e2e8f0',
                    'color': '#11294b',
                    'transform': '',
                    'box-shadow': ''
                });
            }
        );
    });

    // Open payment modal based on type
    function openPaymentModal(type) {
        currentPaymentType = type;
        console.log('Opening payment modal:', type);
        const modalMap = {
            'cash': '#cashPaymentModal',
            'bank': '#bankPaymentModal',
            'cheque': '#chequePaymentModal',
            'upi': '#upiPaymentModal',
            'card': '#cardPaymentModal',
            'metal-exchange': '#metalExchangeModal',
            'scrap': '#scrapPaymentModal'
        };
        
        const modalId = modalMap[type];
        if (modalId) {
            const modal = document.querySelector(modalId);
            console.log('Modal element:', modal);
            if (modal) {
                // Use jQuery/Bootstrap modal
                if (typeof $ !== 'undefined' && $.fn.modal) {
                    $(modalId).modal('show');
                    if (type === 'scrap') {
                        $(modalId).one('shown.bs.modal', function() {
                            setTimeout(function() {
                                if (typeof window.calculateScrap === 'function') {
                                    window.calculateScrap();
                                }
                            }, 100);
                        });
                    }
                } else if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    const bsModal = new bootstrap.Modal(modal);
                    bsModal.show();
                    if (type === 'scrap') {
                        modal.addEventListener('shown.bs.modal', function() {
                            setTimeout(function() {
                                if (typeof window.calculateScrap === 'function') {
                                    window.calculateScrap();
                                }
                            }, 100);
                        }, { once: true });
                    }
                } else {
                    // Fallback: manual show
                    modal.classList.add('show');
                    modal.style.display = 'block';
                    modal.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('modal-open');
                    const backdrop = document.createElement('div');
                    backdrop.className = 'modal-backdrop fade show';
                    backdrop.id = 'modalBackdrop';
                    document.body.appendChild(backdrop);
                    if (type === 'scrap') {
                        setTimeout(function() {
                            if (typeof window.calculateScrap === 'function') {
                                window.calculateScrap();
                            }
                        }, 100);
                    }
                }
            } else {
                console.error('Modal not found:', modalId);
            }
        }
    }

    // Save payment to receipt table
    function saveReceiptPayment(type) {
        let paymentData = {};
        
        if (type === 'cash') {
            const amount = parseFloat(document.getElementById('cashAmount').value || 0);
            if (amount < 0) {
                alert('Amount cannot be negative');
                return;
            }
            paymentData = {
                payment_type: 'Cash',
                deposit_into: document.getElementById('cashDepositInto') ? document.getElementById('cashDepositInto').value : '',
                transaction_no: '',
                transfer_from: '',
                cheque_date: '',
                amount: amount,
                diamond_category: '',
                quantity: 0,
                purity_carat: '',
                product: '',
                product_id: '',
                weight: 0,
                metal: '',
                metal_id: '',
                purity_weight: 0,
                rate: 0,
                item_code: '',
                barcode_no: '',
                card_no: ''
            };
        } else if (type === 'bank') {
            const amount = parseFloat(document.getElementById('bankAmount').value || 0);
            if (amount < 0) {
                alert('Amount cannot be negative');
                return;
            }
            paymentData = {
                payment_type: 'Bank',
                deposit_into: document.getElementById('bankDepositInto').value,
                transaction_no: document.getElementById('bankTransNo').value,
                transfer_from: '',
                cheque_date: '',
                amount: amount,
                diamond_category: '',
                quantity: 0,
                purity_carat: '',
                product: '',
                product_id: '',
                weight: 0,
                metal: '',
                metal_id: '',
                purity_weight: 0,
                rate: 0,
                item_code: '',
                barcode_no: '',
                card_no: ''
            };
        } else if (type === 'cheque') {
            const amount = parseFloat(document.getElementById('chequeAmount').value || 0);
            if (amount < 0) {
                alert('Amount cannot be negative');
                return;
            }
            paymentData = {
                payment_type: 'Cheque',
                deposit_into: document.getElementById('chequeDepositInto').value,
                transaction_no: document.getElementById('chequeTransNo').value,
                transfer_from: '',
                cheque_date: document.getElementById('chequeDate').value,
                amount: amount,
                diamond_category: '',
                quantity: 0,
                purity_carat: '',
                product: '',
                product_id: '',
                weight: 0,
                metal: '',
                metal_id: '',
                purity_weight: 0,
                rate: 0,
                item_code: '',
                barcode_no: '',
                card_no: ''
            };
        } else if (type === 'upi') {
            const amount = parseFloat(document.getElementById('upiAmount').value || 0);
            if (amount < 0) {
                alert('Amount cannot be negative');
                return;
            }
            paymentData = {
                payment_type: 'UPI',
                deposit_into: document.getElementById('upiDepositInto').value,
                transaction_no: document.getElementById('upiTransNo').value,
                transfer_from: '',
                cheque_date: '',
                amount: amount,
                diamond_category: '',
                quantity: 0,
                purity_carat: '',
                product: '',
                product_id: '',
                weight: 0,
                metal: '',
                metal_id: '',
                purity_weight: 0,
                rate: 0,
                item_code: '',
                barcode_no: '',
                card_no: ''
            };
        } else if (type === 'card') {
            const amount = parseFloat(document.getElementById('cardAmount').value || 0);
            if (amount < 0) {
                alert('Amount cannot be negative');
                return;
            }
            const cardNo = document.getElementById('cardNumber') ? document.getElementById('cardNumber').value : '';
            paymentData = {
                payment_type: 'Card',
                deposit_into: document.getElementById('cardDepositInto').value,
                transaction_no: document.getElementById('cardTransNo').value,
                transfer_from: '',
                cheque_date: '',
                amount: amount,
                diamond_category: '',
                quantity: 0,
                purity_carat: '',
                product: '',
                product_id: '',
                weight: 0,
                metal: '',
                metal_id: '',
                purity_weight: 0,
                rate: 0,
                item_code: '',
                barcode_no: '',
                card_no: cardNo
            };
        } else if (type === 'metal-exchange') {
            var el = function(id) { return document.getElementById(id); };
            const amount = parseFloat((el('metalExchangeAmount') && el('metalExchangeAmount').value) || 0);
            const quantity = parseFloat((el('metalExchangeQty') && el('metalExchangeQty').value) || 0);
            const metalSelect = el('metalExchangeMetal');
            const productSelect = el('metalExchangeProduct');
            const metalText = (metalSelect && metalSelect.options[metalSelect.selectedIndex]) ? metalSelect.options[metalSelect.selectedIndex].text : '';
            const productText = (productSelect && productSelect.options[productSelect.selectedIndex]) ? productSelect.options[productSelect.selectedIndex].text : '';
            paymentData = {
                payment_type: 'Metal',
                deposit_into: '',
                transaction_no: '',
                transfer_from: '',
                cheque_date: '',
                amount: amount,
                diamond_category: '',
                quantity: quantity,
                purity_carat: (el('metalExchangePurity') && el('metalExchangePurity').value) || '',
                rate: (el('metalExchangeRate') && el('metalExchangeRate').value) || '',
                item_code: (el('metalExchangeItemCode') && el('metalExchangeItemCode').value) || '',
                gross_weight: (el('metalExchangeWeight') && el('metalExchangeWeight').value) || '',
                net_weight: (el('metalExchangeNetWt') && el('metalExchangeNetWt').value) || '',
                purity_weight: (el('metalExchangePurityWt') && el('metalExchangePurityWt').value) || '',
                metal: metalText,
                metal_id: metalSelect ? metalSelect.value : '',
                product: productText,
                product_id: productSelect ? productSelect.value : '',
                weight: (el('metalExchangeWeight') && el('metalExchangeWeight').value) || '',
                barcode_no: '',
                card_no: ''
            };
        } else if (type === 'scrap') {
            const amount = parseFloat(document.getElementById('scrapAmount').value || 0);
            const quantity = parseFloat(document.getElementById('scrapQty').value || 0);
            const metalSelect = document.getElementById('scrapMetal');
            const productSelect = document.getElementById('scrapProduct');
            const metalText = metalSelect && metalSelect.options[metalSelect.selectedIndex] ? metalSelect.options[metalSelect.selectedIndex].text : '';
            const productText = productSelect && productSelect.options[productSelect.selectedIndex] ? productSelect.options[productSelect.selectedIndex].text : '';
            paymentData = {
                payment_type: 'Scrap',
                deposit_into: '',
                transaction_no: '',
                cheque_date: '',
                amount: amount,
                diamond_category: '',
                quantity: quantity,
                purity_carat: document.getElementById('scrapPurity').value,
                purity_weight: document.getElementById('scrapPurityWt').value,
                rate: document.getElementById('scrapRate').value,
                item_code: document.getElementById('scrapItemCode').value,
                gross_weight: document.getElementById('scrapGrossWt').value,
                weight: document.getElementById('scrapGrossWt').value,
                metal: metalText,
                metal_id: metalSelect ? metalSelect.value : '',
                product: productText,
                product_id: productSelect ? productSelect.value : ''
            };
        }

        // Check if editing existing payment
        if (window.currentEditingPaymentId) {
            const oldRow = document.getElementById(window.currentEditingPaymentId);
            if (oldRow) {
                oldRow.remove();
            }
            window.currentEditingPaymentId = null;
        }

        var modalMap = {
            'cash': '#cashPaymentModal',
            'bank': '#bankPaymentModal',
            'cheque': '#chequePaymentModal',
            'upi': '#upiPaymentModal',
            'card': '#cardPaymentModal',
            'metal-exchange': '#metalExchangeModal',
            'scrap': '#scrapPaymentModal'
        };
        var modalId = modalMap[type];
        try {
            addReceiptRowFromPayment(paymentData);
        } finally {
            clearPaymentModal(type);
            if (typeof window.auragoldClosePaymentModal === 'function') {
                window.auragoldClosePaymentModal(type);
            } else if (modalId && typeof $ !== 'undefined' && $.fn.modal) {
                $(modalId).modal('hide');
            }
        }
    }

    // Add receipt row from payment data - Match purchase-invoice.php structure
    function addReceiptRowFromPayment(paymentData) {
        const tbody = document.getElementById('receiptTableBody');
        const noPaymentRow = tbody.querySelector('.no-payment-row');
        if (noPaymentRow) {
            noPaymentRow.remove();
        }
        
        receiptRowIndex++;
        const paymentId = 'receipt-payment-' + receiptRowIndex;
        const row = document.createElement('tr');
        row.id = paymentId;
        row.setAttribute('data-payment-id', paymentId);
        
        const paymentTypeLabel = paymentData.payment_type === 'Cash' ? 'Cash' :
                                paymentData.payment_type === 'Bank' ? 'Bank' :
                                paymentData.payment_type === 'Cheque' ? 'Cheque' :
                                paymentData.payment_type === 'UPI' ? 'UPI' :
                                paymentData.payment_type === 'Card' ? 'Card' :
                                paymentData.payment_type === 'Metal' ? 'M. Exch.' :
                                paymentData.payment_type === 'Scrap' ? 'Scrap' : paymentData.payment_type;
        
        row.innerHTML = `
            <td data-column="payment-type">${paymentTypeLabel}</td>
            <td data-column="diamond-category">${paymentData.diamond_category || ''}</td>
            <td data-column="transaction-no">${paymentData.transaction_no || ''}</td>
            <td data-column="transfer-from">${paymentData.transfer_from || ''}</td>
            <td data-column="deposit-into">${paymentData.deposit_into || ''}</td>
            <td data-column="product">${paymentData.product || ''}</td>
            <td data-column="cheque-dt">${paymentData.cheque_date || ''}</td>
            <td data-column="weight" style="text-align: right;">${parseFloat(paymentData.weight || paymentData.gross_weight || 0).toFixed(3)}</td>
            <td data-column="metal">${paymentData.metal || ''}</td>
            <td data-column="quantity" style="text-align: right;">${parseFloat(paymentData.quantity || 0).toFixed(2)}</td>
            <td data-column="purity-carat">${paymentData.purity_carat || ''}</td>
            <td data-column="purity-wt" style="text-align: right;">${parseFloat(paymentData.purity_weight || 0).toFixed(3)}</td>
            <td data-column="rate" style="text-align: right;">${parseFloat(paymentData.rate || 0).toFixed(2)}</td>
            <td data-column="amount" data-payment-amount style="text-align: right; font-weight: 600;">${parseFloat(paymentData.amount || 0).toFixed(2)}</td>
            <td data-column="item-code">${paymentData.item_code || ''}</td>
            <td data-column="barcode-no">${paymentData.barcode_no || ''}</td>
            <td data-column="card-no">${paymentData.card_no || ''}</td>
            <td data-column="actions">
                <div class="action-btns">
                    <button type="button" class="btn-edit" onclick="editReceiptPayment('${paymentId}')" title="Edit">
                        <i class="feather icon-edit-2"></i>
                    </button>
                    <button type="button" class="btn-delete" onclick="deleteReceiptPayment('${paymentId}')" title="Delete">
                        <i class="feather icon-trash-2"></i>
                    </button>
                </div>
            </td>
        `;
        
        // Store all payment data as data attributes for editing
        row.setAttribute('data-payment-type', paymentData.payment_type || '');
        row.setAttribute('data-metal-id', paymentData.metal_id || '');
        row.setAttribute('data-product-id', paymentData.product_id || '');
        row.setAttribute('data-amount', paymentData.amount || 0);
        row.setAttribute('data-quantity', paymentData.quantity || 0);
        row.setAttribute('data-weight', paymentData.weight || paymentData.gross_weight || 0);
        row.setAttribute('data-purity-weight', paymentData.purity_weight || 0);
        row.setAttribute('data-rate', paymentData.rate || 0);
        row.setAttribute('data-purity-carat', paymentData.purity_carat || '');
        row.setAttribute('data-item-code', paymentData.item_code || '');
        row.setAttribute('data-barcode-no', paymentData.barcode_no || '');
        row.setAttribute('data-card-no', paymentData.card_no || '');
        row.setAttribute('data-deposit-into', paymentData.deposit_into || '');
        row.setAttribute('data-transaction-no', paymentData.transaction_no || '');
        row.setAttribute('data-transfer-from', paymentData.transfer_from || '');
        row.setAttribute('data-diamond-category', paymentData.diamond_category || '');
        
        tbody.appendChild(row);
        if (typeof window.pvReorderRowCells === 'function') {
            const keys = window.pvGetColumnOrderFromThead ? window.pvGetColumnOrderFromThead(document.querySelector('#receiptTable thead tr')) : null;
            if (keys && keys.length) window.pvReorderRowCells(row, keys);
        }
        
        // Show footer
        const footer = document.getElementById('receiptTableFooter');
        if (footer) footer.style.display = '';
        
        updateReceiptTotal();
    }
    
    // Delete receipt payment
    function deleteReceiptPayment(paymentId) {
        if (confirm('Are you sure you want to delete this payment?')) {
            const row = document.getElementById(paymentId);
            if (row) {
                row.remove();
                const tbody = document.getElementById('receiptTableBody');
                const rows = tbody.querySelectorAll('tr:not(.no-payment-row)');
                if (rows.length === 0) {
                    tbody.innerHTML = '<tr class="no-payment-row"><td colspan="18" class="text-center text-muted py-3">No payment entries</td></tr>';
                    const footer = document.getElementById('receiptTableFooter');
                    if (footer) footer.style.display = 'none';
                    if (typeof applyReceiptColumnVisibility === 'function') applyReceiptColumnVisibility();
                }
                updateReceiptTotal();
            }
        }
    }
    
    // Ensure Deposit Into select has option for value (so edit mode shows saved value)
    function ensureDepositIntoOption(selectId, value) {
        if (!value) return;
        const sel = document.getElementById(selectId);
        if (!sel) return;
        const found = Array.prototype.some.call(sel.options, function(o) { return o.value === value; });
        if (!found) {
            const opt = new Option(value, value);
            sel.appendChild(opt);
        }
        sel.value = value;
    }

    // Edit receipt payment
    function editReceiptPayment(paymentId) {
        const row = document.getElementById(paymentId);
        if (!row) return;
        
        const paymentType = row.querySelector('td:first-child').textContent.trim();
        let type = 'cash';
        if (paymentType === 'Bank') type = 'bank';
        else if (paymentType === 'Cheque') type = 'cheque';
        else if (paymentType === 'UPI') type = 'upi';
        else if (paymentType === 'Card') type = 'card';
        else if (paymentType === 'M. Exch.') type = 'metal-exchange';
        else if (paymentType === 'Scrap') type = 'scrap';
        
        // Store editing payment ID
        window.currentEditingPaymentId = paymentId;
        
        // Read from data attributes (reliable) or fallback to column order: 1=type, 2=diamond-cat, 3=transNo, 4=transferFrom, 5=depositInto, 6=product, 7=chequeDt, 8=weight, 9=metal, 10=qty, 11=purityCarat, 12=purityWt
        const depositInto = (row.getAttribute('data-deposit-into') || row.querySelector('td:nth-child(5)').textContent || '').trim();
        const transactionNo = (row.getAttribute('data-transaction-no') || row.querySelector('td:nth-child(3)').textContent || '').trim();
        const chequeDate = (row.querySelector('td:nth-child(7)') ? row.querySelector('td:nth-child(7)').textContent : '').trim();
        const purityCarat = (row.getAttribute('data-purity-carat') || (row.querySelector('td:nth-child(11)') ? row.querySelector('td:nth-child(11)').textContent : '') || '').trim();
        const amount = parseFloat(row.querySelector('[data-payment-amount]').textContent.replace(/,/g, '') || 0);
        const diamondCategory = (row.getAttribute('data-diamond-category') || row.querySelector('td:nth-child(2)').textContent || '').trim();
        const quantity = parseFloat((row.getAttribute('data-quantity') || (row.querySelector('td:nth-child(10)') ? row.querySelector('td:nth-child(10)').textContent : '0')).replace(/,/g, '') || 0);
        
        // Populate modal fields based on type; ensure Deposit Into option exists so saved value shows
        if (type === 'cash') {
            ensureDepositIntoOption('cashDepositInto', depositInto);
            document.getElementById('cashAmount').value = amount.toFixed(2);
        } else if (type === 'bank') {
            ensureDepositIntoOption('bankDepositInto', depositInto);
            document.getElementById('bankTransNo').value = transactionNo;
            document.getElementById('bankAmount').value = amount.toFixed(2);
        } else if (type === 'cheque') {
            ensureDepositIntoOption('chequeDepositInto', depositInto);
            document.getElementById('chequeTransNo').value = transactionNo;
            document.getElementById('chequeAmount').value = amount.toFixed(2);
            document.getElementById('chequeDate').value = chequeDate || '<?php echo date('Y-m-d'); ?>';
        } else if (type === 'upi') {
            ensureDepositIntoOption('upiDepositInto', depositInto);
            document.getElementById('upiTransNo').value = transactionNo;
            document.getElementById('upiAmount').value = amount.toFixed(2);
        } else if (type === 'card') {
            ensureDepositIntoOption('cardDepositInto', depositInto);
            document.getElementById('cardTransNo').value = transactionNo;
            document.getElementById('cardAmount').value = amount.toFixed(2);
        } else if (type === 'metal-exchange') {
            const weight = parseFloat(row.getAttribute('data-weight') || 0) || 0;
            const purityWt = parseFloat(row.getAttribute('data-purity-weight') || 0) || 0;
            const rate = parseFloat(row.getAttribute('data-rate') || 0) || 0;
            const metalId = row.getAttribute('data-metal-id') || '';
            const productId = row.getAttribute('data-product-id') || '';
            if (document.getElementById('metalExchangeMetal')) document.getElementById('metalExchangeMetal').value = metalId;
            if (document.getElementById('metalExchangeProduct')) document.getElementById('metalExchangeProduct').value = productId;
            if (document.getElementById('metalExchangeQty')) document.getElementById('metalExchangeQty').value = quantity;
            if (document.getElementById('metalExchangeWeight')) document.getElementById('metalExchangeWeight').value = weight;
            if (document.getElementById('metalExchangeNetWt')) document.getElementById('metalExchangeNetWt').value = purityWt;
            if (document.getElementById('metalExchangePurity')) document.getElementById('metalExchangePurity').value = purityCarat || '1';
            if (document.getElementById('metalExchangePurityWt')) document.getElementById('metalExchangePurityWt').value = purityWt;
            if (document.getElementById('metalExchangeRate')) document.getElementById('metalExchangeRate').value = rate;
            if (document.getElementById('metalExchangeAmount')) document.getElementById('metalExchangeAmount').value = amount.toFixed(2);
            if (document.getElementById('metalExchangeItemCode')) document.getElementById('metalExchangeItemCode').value = (row.getAttribute('data-item-code') || '').trim();
        } else if (type === 'scrap') {
            const weight = parseFloat(row.getAttribute('data-weight') || 0) || 0;
            const purityWt = parseFloat(row.getAttribute('data-purity-weight') || 0) || 0;
            const rate = parseFloat(row.getAttribute('data-rate') || 0) || 0;
            const metalId = row.getAttribute('data-metal-id') || '';
            const productId = row.getAttribute('data-product-id') || '';
            if (document.getElementById('scrapMetal')) document.getElementById('scrapMetal').value = metalId;
            if (document.getElementById('scrapProduct')) document.getElementById('scrapProduct').value = productId;
            if (document.getElementById('scrapQty')) document.getElementById('scrapQty').value = quantity;
            if (document.getElementById('scrapGrossWt')) document.getElementById('scrapGrossWt').value = weight;
            if (document.getElementById('scrapPurity')) document.getElementById('scrapPurity').value = (purityCarat !== undefined && purityCarat !== '') ? purityCarat : '1';
            if (document.getElementById('scrapPurityWt')) document.getElementById('scrapPurityWt').value = purityWt;
            if (document.getElementById('scrapRate')) document.getElementById('scrapRate').value = rate;
            if (document.getElementById('scrapAmount')) document.getElementById('scrapAmount').value = amount.toFixed(2);
            if (document.getElementById('scrapItemCode')) document.getElementById('scrapItemCode').value = (row.getAttribute('data-item-code') || '').trim();
            setTimeout(function() {
                if (typeof window.calculateScrap === 'function') {
                    window.calculateScrap();
                }
            }, 150);
        }
        
        openPaymentModal(type);
    }

    // Clear payment modal
    function clearPaymentModal(type) {
        if (type === 'cash') {
            document.getElementById('cashAmount').value = '0.00';
        } else if (type === 'bank') {
            document.getElementById('bankDepositInto').value = '';
            document.getElementById('bankTransNo').value = '';
            document.getElementById('bankAmount').value = '0.00';
        } else if (type === 'cheque') {
            document.getElementById('chequeDepositInto').value = '';
            document.getElementById('chequeTransNo').value = '';
            document.getElementById('chequeAmount').value = '0.00';
            document.getElementById('chequeDate').value = '<?php echo date('Y-m-d'); ?>';
        } else if (type === 'upi') {
            document.getElementById('upiDepositInto').value = '';
            document.getElementById('upiTransNo').value = '';
            document.getElementById('upiAmount').value = '0.00';
        } else if (type === 'card') {
            document.getElementById('cardDepositInto').value = '';
            document.getElementById('cardTransNo').value = '';
            document.getElementById('cardNumber').value = '';
            document.getElementById('cardAmount').value = '0.00';
        } else if (type === 'metal-exchange') {
            if (document.getElementById('metalExchangeMetal')) document.getElementById('metalExchangeMetal').value = '';
            if (document.getElementById('metalExchangeProduct')) document.getElementById('metalExchangeProduct').value = '';
            if (document.getElementById('metalExchangeQty')) document.getElementById('metalExchangeQty').value = '1';
            if (document.getElementById('metalExchangeWeight')) document.getElementById('metalExchangeWeight').value = '0';
            if (document.getElementById('metalExchangeNetWt')) document.getElementById('metalExchangeNetWt').value = '0';
            if (document.getElementById('metalExchangePurity')) document.getElementById('metalExchangePurity').value = '1';
            if (document.getElementById('metalExchangePurityWt')) document.getElementById('metalExchangePurityWt').value = '0';
            if (document.getElementById('metalExchangeRate')) document.getElementById('metalExchangeRate').value = '0';
            if (document.getElementById('metalExchangeAmount')) document.getElementById('metalExchangeAmount').value = '0.00';
            if (document.getElementById('metalExchangeItemCode')) document.getElementById('metalExchangeItemCode').value = '';
            if (typeof metalExchangeFiles !== 'undefined') metalExchangeFiles = [];
        } else if (type === 'scrap') {
            if (document.getElementById('scrapMetal')) document.getElementById('scrapMetal').value = '';
            if (document.getElementById('scrapProduct')) document.getElementById('scrapProduct').value = '';
            if (document.getElementById('scrapQty')) document.getElementById('scrapQty').value = '1';
            if (document.getElementById('scrapGrossWt')) document.getElementById('scrapGrossWt').value = '0';
            if (document.getElementById('scrapLessWt')) document.getElementById('scrapLessWt').value = '0';
            if (document.getElementById('scrapNetWt')) document.getElementById('scrapNetWt').value = '0';
            if (document.getElementById('scrapStoneWt')) document.getElementById('scrapStoneWt').value = '0';
            if (document.getElementById('scrapPurity')) document.getElementById('scrapPurity').value = '1';
            if (document.getElementById('scrapPurityWt')) document.getElementById('scrapPurityWt').value = '0';
            if (document.getElementById('scrapRate')) document.getElementById('scrapRate').value = '0';
            if (document.getElementById('scrapAmount')) document.getElementById('scrapAmount').value = '0.00';
            if (document.getElementById('scrapItemCode')) document.getElementById('scrapItemCode').value = '';
        }
        window.currentEditingPaymentId = null;
    }

    // Calculate metal exchange (same as payment voucher: Rate/Amount bidirectional, Net Wt / Purity Wt)
    window.calculateMetalExchange = function calculateMetalExchange(source) {
        try {
            const weightEl = document.getElementById('metalExchangeWeight');
            const netWtEl = document.getElementById('metalExchangeNetWt');
            const purityEl = document.getElementById('metalExchangePurity');
            const rateEl = document.getElementById('metalExchangeRate');
            const purityWtEl = document.getElementById('metalExchangePurityWt');
            const amountEl = document.getElementById('metalExchangeAmount');
            if (!weightEl || !purityEl || !rateEl || !purityWtEl || !amountEl) return;
            const grossWt = parseFloat(weightEl.value || 0);
            const netWtInput = parseFloat(netWtEl && netWtEl.value ? netWtEl.value : 0);
            const purityCarat = purityEl.value.trim();
            const purity = parseFloat(purityCarat.replace(/[^0-9.]/g, '')) || 0;
            let purityWt = 0;
            if (source === 'netWt' && netWtInput > 0) {
                purityWt = netWtInput;
            } else if (purityCarat && grossWt > 0 && purity > 0) {
                purityWt = grossWt * purity;
                if (netWtEl) netWtEl.value = parseFloat(purityWt.toFixed(3));
            } else if (netWtInput > 0) {
                purityWt = netWtInput;
            }
            purityWtEl.value = parseFloat(purityWt.toFixed(3));
            const rateInput = parseFloat(rateEl.value || 0);
            const amountInput = parseFloat(amountEl.value || 0);
            if (source === 'amount' && purityWt > 0 && amountInput > 0) {
                rateEl.value = parseFloat((amountInput / purityWt).toFixed(2));
            } else {
                amountEl.value = parseFloat((purityWt * rateInput).toFixed(2));
            }
        } catch (e) { console.error('calculateMetalExchange', e); }
    };

    window.calculateScrap = function calculateScrap() {
        const gross = parseFloat(document.getElementById('scrapGrossWt').value || 0);
        const less = parseFloat(document.getElementById('scrapLessWt').value || 0);
        const stone = parseFloat(document.getElementById('scrapStoneWt').value || 0);
        const purityVal = parseFloat(document.getElementById('scrapPurity').value || 0);
        const rate = parseFloat(document.getElementById('scrapRate').value || 0);
        const netWt = Math.max(0, gross - less - stone);
        const netWtEl = document.getElementById('scrapNetWt');
        if (netWtEl) netWtEl.value = netWt.toFixed(3);
        const purityFactor = (purityVal > 0 && purityVal <= 1) ? purityVal : (purityVal / 100);
        const purityWt = netWt * (purityFactor || 0);
        const purityWtEl = document.getElementById('scrapPurityWt');
        if (purityWtEl) purityWtEl.value = purityWt.toFixed(3);
        const amount = purityWt * rate;
        const amountEl = document.getElementById('scrapAmount');
        if (amountEl) amountEl.value = amount.toFixed(2);
    };
    window.calculateScrapAmount = function calculateScrapAmount() {
        const amount = parseFloat(document.getElementById('scrapAmount').value || 0);
        const purityWt = parseFloat(document.getElementById('scrapPurityWt').value || 0);
        const rateEl = document.getElementById('scrapRate');
        if (rateEl && purityWt > 0 && amount > 0) rateEl.value = (amount / purityWt).toFixed(2);
    };
    
    // Metal Exchange Files Management
    let metalExchangeFiles = [];
    
    // Handle Metal Exchange File Drop
    function handleMetalExchangeFileDrop(event) {
        event.preventDefault();
        const uploadArea = document.getElementById('metalExchangeDocumentUpload');
        if (uploadArea) {
            uploadArea.style.borderColor = '#cbd5e1';
        }
        
        const files = event.dataTransfer.files;
        handleMetalExchangeFiles(files);
    }
    
    // Handle Metal Exchange File Select
    function handleMetalExchangeFileSelect(input) {
        const files = input.files;
        handleMetalExchangeFiles(files);
    }
    
    // Process Metal Exchange Files
    function handleMetalExchangeFiles(files) {
        const fileList = document.getElementById('metalExchangeFileList');
        if (!fileList) return;
        
        Array.from(files).forEach(file => {
            if (!file.type.match('image.*')) {
                alert('Please select only image files.');
                return;
            }
            
            if (file.size > 5 * 1024 * 1024) {
                alert('File size should be less than 5MB. ' + file.name + ' is too large.');
                return;
            }
            
            metalExchangeFiles.push(file);
            
            const fileItem = document.createElement('div');
            fileItem.className = 'metal-exchange-file-item';
            fileItem.style.cssText = 'display: flex; align-items: center; justify-content: space-between; padding: 0.75rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; margin-bottom: 0.5rem;';
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = fileItem.querySelector('img');
                if (img) {
                    img.src = e.target.result;
                }
            };
            reader.readAsDataURL(file);
            
            fileItem.innerHTML = `
                <div style="display: flex; align-items: center; gap: 0.75rem; flex: 1;">
                    <div style="width: 50px; height: 50px; border-radius: 4px; overflow: hidden; background: #e2e8f0; display: flex; align-items: center; justify-content: center;">
                        <img src="" alt="Preview" style="width: 100%; height: 100%; object-fit: cover; display: none;" onload="this.style.display='block'; this.parentElement.querySelector('i').style.display='none';">
                        <i class="feather icon-image" style="color: #94a3b8; font-size: 1.5rem;"></i>
                    </div>
                    <div style="flex: 1;">
                        <div style="font-size: 0.85rem; color: #334155; font-weight: 500;">${file.name}</div>
                        <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.25rem;">${(file.size / 1024).toFixed(2)} KB</div>
                    </div>
                </div>
                <button type="button" onclick="removeMetalExchangeFile(this)" style="background: transparent; border: none; color: #ef4444; cursor: pointer; padding: 0.5rem; border-radius: 4px; transition: background 0.2s;" onmouseover="this.style.background='#fee2e2';" onmouseout="this.style.background='transparent';">
                    <i class="feather icon-x" style="font-size: 12px;"></i>
                </button>
            `;
            fileList.appendChild(fileItem);
        });
    }
    
    // Remove Metal Exchange File
    function removeMetalExchangeFile(button) {
        const fileItem = button.closest('.metal-exchange-file-item');
        if (fileItem) {
            const fileName = fileItem.querySelector('div > div').textContent.trim();
            metalExchangeFiles = metalExchangeFiles.filter(file => file.name !== fileName);
            fileItem.remove();
        }
    }

    // Escape HTML helper
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text ? text.replace(/[&<>"']/g, m => map[m]) : '';
    }
    function escAttr(text) {
        if (text === undefined || text === null) return '';
        return String(text).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    }

    // Customer search functionality
    $(document).ready(function() {
        let customerSearchTimeout;
        $('#customerName').on('input', function() {
            clearTimeout(customerSearchTimeout);
            const searchTerm = $(this).val().trim();
            if (searchTerm.length < 2) {
                $('#customerSuggestions').hide().empty();
                $('#customerId').val('');
                return;
            }
            customerSearchTimeout = setTimeout(function() {
                searchCustomers(searchTerm);
            }, 300);
        });

        $('#customerName').on('blur', function() {
            setTimeout(function() {
                $('#customerSuggestions').hide();
            }, 200);
        });

        $(document).on('click', function(e) {
            if (!$(e.target).closest('#customerName, #customerSuggestions, #addCustomerBtn').length) {
                $('#customerSuggestions').hide();
            }
        });

        $(document).on('click', '#customerSuggestions .customer-suggestion-item', function() {
            const customerId = $(this).data('customerId');
            const customerName = $(this).data('customerName');
            if (customerId == null || customerName == null) return;
            $('#customerName').val(customerName);
            $('#customerId').val(customerId);
            $('#customerSuggestions').hide();
            loadCustomerBalance();
        });

        // Handle keyboard navigation and Enter: select suggestion or open Ledger Details modal with name pre-filled
        $(document).on('keydown', '#customerName', function(e) {
            const suggestionsDiv = $('#customerSuggestions');
            const visibleItems = suggestionsDiv.find('.customer-suggestion-item:visible');
            const customerNameValue = $(this).val().trim();

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (visibleItems.length === 0) return;
                const currentFocused = suggestionsDiv.find('.customer-suggestion-item.focused');
                if (currentFocused.length === 0) {
                    visibleItems.first().addClass('focused').css('background', '#f8fafc');
                } else {
                    const next = currentFocused.next('.customer-suggestion-item:visible');
                    if (next.length) {
                        currentFocused.removeClass('focused').css('background', '');
                        next.addClass('focused').css('background', '#f8fafc');
                    }
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (visibleItems.length === 0) return;
                const currentFocused = suggestionsDiv.find('.customer-suggestion-item.focused');
                if (currentFocused.length > 0) {
                    const prev = currentFocused.prev('.customer-suggestion-item:visible');
                    if (prev.length) {
                        currentFocused.removeClass('focused').css('background', '');
                        prev.addClass('focused').css('background', '#f8fafc');
                    } else {
                        currentFocused.removeClass('focused').css('background', '');
                    }
                }
            } else if (e.key === 'Enter') {
                e.preventDefault();
                const focused = suggestionsDiv.find('.customer-suggestion-item.focused');
                if (focused.length) {
                    focused.trigger('click');
                } else if (customerNameValue.length > 0) {
                    suggestionsDiv.hide();
                    $('#customerCreationModal').modal('show');
                    setTimeout(function() {
                        const ledgerNameField = $('#ledgerName');
                        if (ledgerNameField.length) {
                            ledgerNameField.val(customerNameValue);
                            if (typeof handleNameInput === 'function') {
                                handleNameInput(ledgerNameField[0]);
                            }
                            ledgerNameField.focus();
                        }
                    }, 300);
                }
            } else if (e.key === 'Escape') {
                suggestionsDiv.hide();
            }
        });

        // Load customer balance when customer is selected
        $('#customerName').on('change', function() {
            loadCustomerBalance();
        });

        // Handle Add Customer Icon Click
        $(document).on('click', '#addCustomerBtn, .add-customer-icon', function(e) {
            e.stopPropagation();
            e.preventDefault();
            console.log('Add customer button clicked');
            $('#customerCreationModal').modal('show');
        });
    });

    function searchCustomers(term) {
        $.ajax({
            url: 'ajax/search-customers.php',
            method: 'GET',
            data: { q: term, branch_id: <?php echo (int) (function_exists('auragold_effective_branch_id') ? auragold_effective_branch_id() : 0); ?> },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success' && response.customers && response.customers.length > 0) {
                    let html = '<div class="customer-suggestions-header" style="padding: 0.5rem; font-size: 0.85rem; color: #64748b; border-bottom: 1px solid #e2e8f0; font-weight: 600;">Select Customer:</div>';
                    response.customers.forEach(function(customer) {
                        const nameH = escapeHtml(customer.name || '');
                        const altH = (customer.alternate_name || '').trim() ? ('<div style="font-size: 0.8rem; color: #64748b; margin-top: 0.25rem;">' + escapeHtml(customer.alternate_name) + '</div>') : '';
                        const phoneH = (customer.mobile_no || '').trim() ? ('<div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.15rem;"><i class="feather icon-phone" style="font-size: 0.7rem; color: #c5a864; vertical-align: middle; margin-right: 4px;"></i><span style="color: #64748b;">' + escapeHtml(customer.mobile_no) + '</span></div>') : '';
                        html += '<div class="customer-suggestion-item" data-customer-id="' + escAttr(String(customer.id)) + '" data-customer-name="' + escAttr(customer.name) + '" style="padding: 0.75rem; cursor: pointer; border-bottom: 1px solid #f1f5f9; transition: background 0.2s;" onmouseover="this.style.background=\'#f8fafc\'" onmouseout="this.style.background=\'#fff\'">'
                            + '<div style="font-weight: 600; color: #1e293b; font-size: 0.9rem;">' + nameH + '</div>' + altH + phoneH + '</div>';
                    });
                    $('#customerSuggestions').html(html).show();
                } else {
                    $('#customerSuggestions').hide().empty();
                }
            },
            error: function() {
                $('#customerSuggestions').hide().empty();
            }
        });
    }
    
    // Customer Creation Modal Functions
    // Preview Ledger Photo
    function previewLedgerPhoto(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('ledgerPhotoPreview').style.display = 'block';
                document.getElementById('ledgerPhotoImg').src = e.target.result;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    // Handle Name input - auto-populate First Name and Last Name
    function handleNameInput(input) {
        const nameValue = input.value;
        const capitalCheckbox = document.getElementById('ledgerNameCapital');
        
        // If Ledger Name Capital is checked, convert to uppercase
        if (capitalCheckbox && capitalCheckbox.checked) {
            input.value = nameValue.toUpperCase();
        }
        
        // Split name and populate First Name and Last Name
        const nameParts = nameValue.trim().split(/\s+/);
        const firstNameField = document.getElementById('ledgerFirstName');
        const lastNameField = document.getElementById('ledgerLastName');
        
        if (nameParts.length > 0) {
            // First part goes to First Name
            if (firstNameField) {
                firstNameField.value = nameParts[0];
            }
            
            // Last part goes to Last Name (if there are multiple parts)
            if (nameParts.length > 1 && lastNameField) {
                lastNameField.value = nameParts[nameParts.length - 1];
            } else if (nameParts.length === 1 && lastNameField) {
                // If only one part, clear last name
                lastNameField.value = '';
            }
        }
    }
    
    // Share Holders Management
    let shareHolderRowIndex = 0;
    let shareHoldersData = [];
    let shareHolderFiles = [];
    
    // Add Share Holder Row
    function addShareHolderRow() {
        shareHolderRowIndex++;
        const tbody = document.getElementById('shareHoldersTableBody');
        if (!tbody) {
            console.error('Share Holders table body not found');
            alert('Share Holders table not found. Please refresh the page.');
            return;
        }
        
        const row = document.createElement('tr');
        row.id = 'shareHolderRow_' + shareHolderRowIndex;
        row.setAttribute('data-row-index', shareHolderRowIndex);
        
        // Build nationality options from JavaScript array
        let nationalityOptions = '<option value="">Select Nationality</option>';
        if (typeof nationalities !== 'undefined' && Array.isArray(nationalities)) {
            nationalities.forEach(function(nationality) {
                nationalityOptions += `<option value="${nationality.id}">${nationality.name}</option>`;
            });
        }
        
        row.innerHTML = `
            <td>
                <input type="text" class="form-control" name="share_holders[${shareHolderRowIndex}][name]" placeholder="Enter name" style="font-size: 0.85rem; padding: 0.4rem 0.6rem; height: 32px; border: 1px solid #e2e8f0;">
            </td>
            <td>
                <select class="form-control" name="share_holders[${shareHolderRowIndex}][nationality_id]" style="font-size: 0.85rem; padding: 0.4rem 0.6rem; height: 32px; border: 1px solid #e2e8f0;">
                    ${nationalityOptions}
                </select>
            </td>
            <td>
                <input type="text" class="form-control" name="share_holders[${shareHolderRowIndex}][share_percentage]" placeholder="0.00" step="0.01" min="0" max="100" style="font-size: 0.85rem; padding: 0.4rem 0.6rem; height: 32px; border: 1px solid #e2e8f0; text-align: right;">
            </td>
            <td style="text-align: center;">
                <button type="button" class="btn btn-sm delete-share-holder" onclick="deleteShareHolderRow(${shareHolderRowIndex})" style="background: transparent; border: none; color: #ef4444; padding: 0.25rem; cursor: pointer;">
                    <i class="feather icon-trash-2" style="font-size: 0.9rem;"></i>
                </button>
            </td>
        `;
        
        tbody.appendChild(row);
        shareHoldersData.push({
            row_index: shareHolderRowIndex,
            name: '',
            nationality_id: '',
            share_percentage: ''
        });
    }
    
    // Delete Share Holder Row
    function deleteShareHolderRow(rowIndex) {
        if (confirm('Are you sure you want to delete this share holder?')) {
            const row = document.getElementById('shareHolderRow_' + rowIndex);
            if (row) {
                row.remove();
                shareHoldersData = shareHoldersData.filter(item => item.row_index !== rowIndex);
            }
        }
    }
    
    // Sort Share Holders Table
    function sortShareHoldersTable(columnIndex) {
        const tbody = document.getElementById('shareHoldersTableBody');
        if (!tbody) return;
        
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        rows.sort((a, b) => {
            let aVal, bVal;
            if (columnIndex === 0) {
                // Name column
                aVal = a.querySelector('input[type="text"]')?.value || '';
                bVal = b.querySelector('input[type="text"]')?.value || '';
            } else if (columnIndex === 1) {
                // Nationality column
                aVal = a.querySelector('select')?.selectedOptions[0]?.text || '';
                bVal = b.querySelector('select')?.selectedOptions[0]?.text || '';
            } else if (columnIndex === 2) {
                // Share Per. column
                aVal = parseFloat(a.querySelector('input[name*="share_percentage"]')?.value || 0);
                bVal = parseFloat(b.querySelector('input[name*="share_percentage"]')?.value || 0);
            }
            
            if (typeof aVal === 'string') {
                return aVal.localeCompare(bVal);
            } else {
                return aVal - bVal;
            }
        });
        
        rows.forEach(row => tbody.appendChild(row));
    }
    
    // Handle Share Holder File Drop
    function handleShareHolderFileDrop(event) {
        event.preventDefault();
        const uploadArea = document.getElementById('shareHolderDocumentUpload');
        if (uploadArea) {
            uploadArea.style.borderColor = '#cbd5e1';
        }
        
        const files = event.dataTransfer.files;
        handleShareHolderFiles(files);
    }
    
    // Handle Share Holder File Select
    function handleShareHolderFileSelect(input) {
        const files = input.files;
        handleShareHolderFiles(files);
    }
    
    // Process Share Holder Files
    function handleShareHolderFiles(files) {
        const fileList = document.getElementById('shareHolderFileList');
        if (!fileList) return;
        
        Array.from(files).forEach(file => {
            shareHolderFiles.push(file);
            
            const fileItem = document.createElement('div');
            fileItem.className = 'share-holder-file-item';
            fileItem.style.cssText = 'display: flex; align-items: center; justify-content: space-between; padding: 0.5rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; margin-bottom: 0.5rem;';
            fileItem.innerHTML = `
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <i class="feather icon-file" style="color: #c5a864;"></i>
                    <span style="font-size: 0.85rem; color: #334155;">${file.name}</span>
                    <span style="font-size: 0.75rem; color: #94a3b8;">(${(file.size / 1024).toFixed(2)} KB)</span>
                </div>
                <button type="button" onclick="removeShareHolderFile(this)" style="background: transparent; border: none; color: #ef4444; cursor: pointer; padding: 0.25rem;">
                    <i class="feather icon-x" style="font-size: 0.9rem;"></i>
                </button>
            `;
            fileList.appendChild(fileItem);
        });
    }
    
    // Remove Share Holder File
    function removeShareHolderFile(button) {
        const fileItem = button.closest('.share-holder-file-item');
        if (fileItem) {
            const fileName = fileItem.querySelector('span').textContent.trim();
            shareHolderFiles = shareHolderFiles.filter(file => file.name !== fileName);
            fileItem.remove();
        }
    }
    
    function clearCustomerForm() {
        document.getElementById('customerCreationForm').reset();
        document.getElementById('ledgerPhotoPreview').style.display = 'none';
        document.getElementById('ledgerPhotoInput').value = '';
        // Clear share holders table
        const shareHoldersBody = document.getElementById('shareHoldersTableBody');
        if (shareHoldersBody) {
            shareHoldersBody.innerHTML = '';
        }
        shareHolderRowIndex = 0;
        shareHoldersData = [];
        // Clear file list
        const fileList = document.getElementById('shareHolderFileList');
        if (fileList) {
            fileList.innerHTML = '';
        }
        shareHolderFiles = [];
    }
    
    function saveCustomer() {
        const form = document.getElementById('customerCreationForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        const ledgerIdEl = document.getElementById('ledgerCustomerId');
        const isNewCustomer = !ledgerIdEl || !String(ledgerIdEl.value || '').trim();
        const customerTypeEl = document.getElementById('customerType');
        if (isNewCustomer && customerTypeEl && !String(customerTypeEl.value || '').trim()) {
            alert('Customer type is required');
            customerTypeEl.focus();
            return;
        }
        
        const formData = new FormData(form);
        
        // Show loading
        const saveBtn = event.target;
        const originalText = saveBtn.innerHTML;
        saveBtn.innerHTML = '<i class="feather icon-loader spin"></i> Saving...';
        saveBtn.disabled = true;
        
        fetch('customer-save.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('JSON parse error:', text);
                    throw new Error('Invalid JSON response from server');
                }
            });
        })
        .then(data => {
            if (data.status === 'success' || data.success === true) {
                alert(data.message || 'Customer created successfully!');
                
                // Close the customer creation modal
                $('#customerCreationModal').modal('hide');
                
                // Select saved party in NAME dropdown (Select2)
                if (typeof window.auragoldApplySavedCustomerToPartyDropdown === 'function') {
                    window.auragoldApplySavedCustomerToPartyDropdown(data);
                }
                
                // Clear the form
                clearCustomerForm();
            } else {
                alert('Error: ' + (data.message || 'Failed to create customer'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error saving customer: ' + error.message);
        })
        .finally(() => {
            saveBtn.innerHTML = originalText;
            saveBtn.disabled = false;
        });
    }
    
    // Handle Ledger Name Capital checkbox
    $(document).ready(function() {
        $(document).on('change', '#ledgerNameCapital', function() {
            const nameField = document.getElementById('ledgerName');
            if (nameField && this.checked) {
                // Convert current name to uppercase
                nameField.value = nameField.value.toUpperCase();
            }
        });
        
        // Also handle when typing in name field if checkbox is checked
        $(document).on('input', '#ledgerName', function() {
            const capitalCheckbox = document.getElementById('ledgerNameCapital');
            if (capitalCheckbox && capitalCheckbox.checked) {
                this.value = this.value.toUpperCase();
            }
        });
        
        // Add Share Holder Row
        $(document).on('click', '#addShareHolderBtn', function() {
            addShareHolderRow();
        });

        var usePreviousBalanceCheck = document.getElementById('usePreviousBalanceCheck');
        var previousBalanceUseAmountRow = document.getElementById('previousBalanceUseAmountRow');
        var previousBalanceUseAmountInput = document.getElementById('previousBalanceUseAmount');
        if (usePreviousBalanceCheck) {
            usePreviousBalanceCheck.addEventListener('change', function() {
                if (previousBalanceUseAmountRow) {
                    if (this.checked) previousBalanceUseAmountRow.classList.add('is-open');
                    else previousBalanceUseAmountRow.classList.remove('is-open');
                }
                if (!this.checked && previousBalanceUseAmountInput) {
                    previousBalanceUseAmountInput.value = '0.00';
                }
            });
        }
    });

    /** Previous balance formatting: window.formatVoucherPreviousBalance* from js/previous-balance-common.js */
    function resetVoucherPreviousBalanceDisplay() {
        if (typeof PrevBalanceUI !== 'undefined' && PrevBalanceUI.clearPanel) {
            PrevBalanceUI.clearPanel();
        }
    }
    function getVoucherPreviousBalanceForSave() {
        function numFromSpan(id, dataAttr, decimals, fallbackStr) {
            var el = document.getElementById(id);
            if (!el) return fallbackStr;
            var raw = el.getAttribute(dataAttr);
            if (raw !== null && raw !== '') {
                return parseFloat(raw).toFixed(decimals);
            }
            var t = (el.textContent || '').replace(/[()]/g, '').trim();
            var n = parseFloat(t);
            return (isNaN(n) ? 0 : n).toFixed(decimals);
        }
        return {
            previous_balance: numFromSpan('previousBalanceAmount', 'data-original-balance', 2, '0.00'),
            previous_gold: numFromSpan('previousBalanceGold', 'data-original-gold', 3, '0.000'),
            previous_silver: numFromSpan('previousBalanceSilver', 'data-original-silver', 3, '0.000'),
            previous_diamond: numFromSpan('previousBalanceDiamond', 'data-original-diamond', 3, '0.000'),
            previous_gemstone: numFromSpan('previousBalanceGemstone', 'data-original-gemstone', 3, '0.000')
        };
    }

    function loadCustomerBalance() {
        const customerId = $('#customerId').val();
        const customerName = $('#customerName').val();
        if (!customerId && !customerName) {
            resetVoucherPreviousBalanceDisplay();
            var _pbVe = document.getElementById('previousBalancePanelLoader');
            if (_pbVe) {
                _pbVe.classList.remove('pb-is-loading');
                _pbVe.setAttribute('aria-hidden', 'true');
            }
            $('#transactionHistoryTable').hide();
            $('#noHistoryMessage').show().text('Select a customer to view history');
            return;
        }

        if (typeof PrevBalanceUI !== 'undefined') {
            PrevBalanceUI.load({
                partyNameSelector: '#customerName',
                partyIdSelector: '#customerId',
                balanceType: 'customer',
                ledgerClBalance: true,
                purchaseLedgerPrevBalance: false,
                onBeforeLoad: function () {
                    var el = document.getElementById('previousBalancePanelLoader');
                    if (el) {
                        el.classList.add('pb-is-loading');
                        el.setAttribute('aria-hidden', 'false');
                    }
                },
                onAfterLoadAlways: function () {
                    var el = document.getElementById('previousBalancePanelLoader');
                    if (el) {
                        el.classList.remove('pb-is-loading');
                        el.setAttribute('aria-hidden', 'true');
                    }
                }
            });
        }

        // Load transaction history
        $.ajax({
            url: 'ajax/get-customer-transaction-history.php',
            method: 'GET',
            data: { 
                customer_id: customerId || 0,
                customer_name: customerName || '',
                limit: 20
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success' && response.transactions && response.transactions.length > 0) {
                    const tbody = $('#transactionHistoryBody');
                    tbody.empty();
                    
                    response.transactions.forEach(function(transaction) {
                        const date = new Date(transaction.transaction_date).toLocaleDateString('en-GB');
                        const type = transaction.transaction_type || '';
                        const transNo = transaction.transaction_no || '-';
                        const debit = parseFloat(transaction.debit_amount || 0);
                        const credit = parseFloat(transaction.credit_amount || 0);
                        const balance = parseFloat(transaction.balance_amount || 0);
                        
                        let amountDisplay = '';
                        if (debit > 0) {
                            amountDisplay = '<span style="color: #dc2626;">-' + debit.toFixed(2) + '</span>';
                        } else if (credit > 0) {
                            amountDisplay = '<span style="color: #16a34a;">+' + credit.toFixed(2) + '</span>';
                        } else {
                            amountDisplay = '0.00';
                        }
                        
                        const row = `
                            <tr style="border-bottom: 1px solid #e2e8f0;">
                                <td style="padding: 4px; font-size: 0.7rem;">${date}</td>
                                <td style="padding: 4px; font-size: 0.7rem;">${type.replace('_', ' ')}</td>
                                <td style="padding: 4px; font-size: 0.7rem;">${transNo}</td>
                                <td style="padding: 4px; font-size: 0.7rem; text-align: right;">${amountDisplay}</td>
                            </tr>
                        `;
                        tbody.append(row);
                    });
                    
                    $('#noHistoryMessage').hide();
                    $('#transactionHistoryTable').show();
                } else {
                    $('#transactionHistoryTable').hide();
                    $('#noHistoryMessage').show().text('No transaction history found');
                }
            },
            error: function() {
                $('#transactionHistoryTable').hide();
                $('#noHistoryMessage').show().text('Error loading transaction history');
            }
        });
    }

    function addReceiptRow() {
        // Open cash payment modal by default when clicking Add Row
        openPaymentModal('cash');
    }
    
    function addEmptyReceiptRow() {
        receiptRowIndex++;
        const rowId = 'receipt-row-' + receiptRowIndex;
        const $tbody = $('#receiptTableBody');
        $tbody.find('.no-rows').remove();

        const row = `
            <tr id="${rowId}" data-row-index="${receiptRowIndex}">
                <td data-column="payment-type">
                    <select class="form-control form-control-sm payment-type" onchange="calculateReceiptRow('${rowId}')">
                        <option value="">Select</option>
                        <option value="Cash">Cash</option>
                        <option value="Bank">Bank</option>
                        <option value="Cheque">Cheque</option>
                        <option value="UPI">UPI</option>
                        <option value="Card">Card</option>
                        <option value="Metal">Metal</option>
                    </select>
                </td>
                <td data-column="diamond-category">
                    <input type="text" class="form-control form-control-sm diamond-category" placeholder="Diamond Category">
                </td>
                <td data-column="transaction-no">
                    <input type="text" class="form-control form-control-sm transaction-no" placeholder="Transaction No.">
                </td>
                <td data-column="transfer-from">
                    <input type="text" class="form-control form-control-sm" placeholder="Transfer From">
                </td>
                <td data-column="deposit-into">
                    <input type="text" class="form-control form-control-sm deposit-into" placeholder="Deposit Into">
                </td>
                <td data-column="product">
                    <select class="form-control form-control-sm product-select">
                        <option value="">Select Product</option>
                        <?php foreach($products as $product): ?>
                        <option value="<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td data-column="cheque-dt">
                    <input type="date" class="form-control form-control-sm cheque-date">
                </td>
                <td data-column="weight">
                    <input type="text" class="form-control form-control-sm weight" step="0.001" placeholder="0.000" onchange="calculateReceiptRow('${rowId}')">
                </td>
                <td data-column="metal">
                    <select class="form-control form-control-sm metal-select">
                        <option value="">Select Metal</option>
                        <?php foreach($metals as $metal): ?>
                        <option value="<?php echo $metal['id']; ?>"><?php echo htmlspecialchars($metal['display_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td data-column="quantity">
                    <input type="text" class="form-control form-control-sm quantity" step="0.01" placeholder="0.00" onchange="calculateReceiptRow('${rowId}')">
                </td>
                <td data-column="purity-carat">
                    <input type="text" class="form-control form-control-sm purity-carat" placeholder="Purity / Carat" onchange="calculateReceiptRow('${rowId}')">
                </td>
                <td data-column="purity-wt">
                    <input type="text" class="form-control form-control-sm purity-wt" step="0.001" placeholder="0.000" readonly style="background: #f8fafc;">
                </td>
                <td data-column="rate">
                    <input type="text" class="form-control form-control-sm" step="0.01" placeholder="Rate" onchange="calculateReceiptRow('${rowId}')">
                </td>
                <td data-column="amount">
                    <input type="text" class="form-control form-control-sm" step="0.01" placeholder="Amount" data-payment-amount>
                </td>
                <td data-column="item-code">
                    <input type="text" class="form-control form-control-sm" placeholder="Item Code">
                </td>
                <td data-column="barcode-no">
                    <input type="text" class="form-control form-control-sm" placeholder="Barcode No.">
                </td>
                <td data-column="card-no">
                    <input type="text" class="form-control form-control-sm" placeholder="Card No.">
                </td>
                <td data-column="actions">
                    <div class="action-btns">
                        <button type="button" class="btn btn-sm btn-edit" onclick="editReceiptPayment('${rowId}')" title="Edit"><i class="feather icon-edit-2"></i></button>
                        <button type="button" class="btn btn-sm btn-danger" onclick="deleteReceiptRow('${rowId}')" title="Delete"><i class="feather icon-trash-2"></i></button>
                    </div>
                </td>
            </tr>
        `;
        $tbody.append(row);
        if (typeof window.pvReorderRowCells === 'function') {
            const keys = window.pvGetColumnOrderFromThead ? window.pvGetColumnOrderFromThead(document.querySelector('#receiptTable thead tr')) : null;
            if (keys && keys.length) window.pvReorderRowCells(document.getElementById(rowId), keys);
        }
        updateReceiptTotal();
        const footer = document.getElementById('receiptTableFooter');
        if (footer) footer.style.display = '';
    }

    function calculateReceiptRow(rowId) {
        const $row = $('#' + rowId);
        const weight = parseFloat($row.find('.weight').val() || 0);
        const purityCarat = $row.find('.purity-carat').val();
        
        // Calculate purity weight (simplified - you may need more complex logic)
        let purityWt = 0;
        if (purityCarat) {
            const purity = parseFloat(purityCarat.replace(/[^0-9.]/g, '')) || 0;
            if (purity > 0 && purity <= 100) {
                purityWt = (weight * purity / 100).toFixed(3);
            } else if (purity > 100) {
                // Assume it's carat value, convert to percentage
                purityWt = (weight * purity / 100).toFixed(3);
            }
        }
        $row.find('.purity-wt').val(purityWt);
        updateReceiptTotal();
    }

    function deleteReceiptRow(rowId) {
        deleteReceiptPayment(rowId);
    }

    function updateReceiptTotal() {
        const rows = document.querySelectorAll('#receiptTableBody tr:not(.no-payment-row)');
        let totalAmount = 0;
        let totalQuantity = 0;
        
        rows.forEach(function(row) {
            var amtEl = row.querySelector('[data-payment-amount]');
            var amt = amtEl ? parseFloat((amtEl.textContent || amtEl.value || '').replace(/,/g, '') || 0) : 0;
            var qtyTd = row.querySelector('td[data-column="quantity"]');
            var qty = qtyTd ? parseFloat((qtyTd.textContent || '').replace(/,/g, '') || 0) : 0;
            if (!qty && qtyTd && qtyTd.querySelector('input')) {
                qty = parseFloat(qtyTd.querySelector('input').value || 0) || 0;
            }
            totalAmount += amt;
            totalQuantity += qty;
        });
        
        const footer = document.getElementById('receiptTableFooter');
        if (footer) {
            document.getElementById('receiptTotalAmount').textContent = totalAmount.toFixed(2);
            document.getElementById('receiptTotalQuantity').textContent = totalQuantity.toFixed(2);
        }
    }

    function resetVoucher() {
        if (confirm('Are you sure you want to start a new entry? All unsaved data will be lost.')) {
            // Refresh page and go to receipt-voucher.php without ?id= so we get a fresh form (exit edit mode)
            window.location.href = 'receipt-voucher.php';
        }
    }

    /** After save, stay on a fresh new voucher (same page, not transaction report). */
    function buildTransactionReportUrlAfterVoucherSave() {
        return 'receipt-voucher.php';
    }

    function saveVoucher() {
        var prevBalSave = getVoucherPreviousBalanceForSave();
        const voucherData = {
            voucher_id: $('#voucherId').val() || 0,
            voucher_no: $('#voucherNo').val(),
            customer_id: $('#customerId').val(),
            customer_name: $('#customerName').val(),
            ref_no: $('#refNo').val(),
            receipt_no: $('#receiptNo').val(),
            voucher_type: $('#voucherType').val(),
            against: $('#against').val(),
            sales_person: $('#salesPerson').val(),
            against_of: $('#againstOf').val(),
            currency: $('#currency').val(),
            currency_rate: $('#currencyRate').val() || '1',
            exchange_rate: $('#currencyRate').val() || '1',
            voucher_date: $('#voucherDate').val(),
            due_date: (typeof window.auragoldGetDueDateValue === 'function' ? window.auragoldGetDueDateValue() : $('#dueDate').val()),
            layaways_id: $('#layaways').val(),
            fixing_type: $('#fixingType').val(),
            previous_balance: prevBalSave.previous_balance,
            previous_gold: prevBalSave.previous_gold,
            previous_silver: prevBalSave.previous_silver,
            comment: $('#comment').val(),
            items: []
        };

        function rvTdTextByColumn($row, colKey) {
            const $td = $row.find('td[data-column="' + colKey + '"]');
            if (!$td.length) return '';
            const $inp = $td.find('input,select').first();
            if ($inp.length) return ($inp.val() || '').toString().trim();
            return ($td.text() || '').trim();
        }
        $('#receiptTableBody tr').each(function() {
            if (!$(this).hasClass('no-payment-row') && !$(this).hasClass('no-rows')) {
                const $row = $(this);
                let paymentType = ($row.attr('data-payment-type') || '').trim();
                const paymentTypeText = rvTdTextByColumn($row, 'payment-type') || $row.find('td[data-column="payment-type"]').text().trim();
                if (!paymentType) {
                    paymentType = 'Cash';
                    if (paymentTypeText === 'Bank') paymentType = 'Bank';
                    else if (paymentTypeText === 'Cheque') paymentType = 'Cheque';
                    else if (paymentTypeText === 'UPI') paymentType = 'UPI';
                    else if (paymentTypeText === 'Card') paymentType = 'Card';
                    else if (paymentTypeText === 'M. Exch.') paymentType = 'Metal';
                    else if (paymentTypeText === 'Scrap') paymentType = 'Scrap';
                }
                let amount = parseFloat(($row.find('[data-payment-amount]').text() || $row.find('[data-payment-amount]').val() || '').replace(/,/g, '') || 0) || 0;
                if (!amount) amount = parseFloat($row.attr('data-amount') || 0) || 0;
                const item = {
                    payment_type: paymentType,
                    diamond_category: $row.attr('data-diamond-category') || rvTdTextByColumn($row, 'diamond-category'),
                    transaction_no: $row.attr('data-transaction-no') || rvTdTextByColumn($row, 'transaction-no'),
                    transfer_from: $row.attr('data-transfer-from') || rvTdTextByColumn($row, 'transfer-from'),
                    deposit_into: $row.attr('data-deposit-into') || rvTdTextByColumn($row, 'deposit-into'),
                    product: rvTdTextByColumn($row, 'product'),
                    product_id: $row.attr('data-product-id') || '',
                    cheque_date: rvTdTextByColumn($row, 'cheque-dt'),
                    weight: parseFloat($row.attr('data-weight') || (rvTdTextByColumn($row, 'weight') || '').replace(/,/g, '') || 0) || 0,
                    metal: rvTdTextByColumn($row, 'metal'),
                    metal_id: $row.attr('data-metal-id') || '',
                    quantity: parseFloat($row.attr('data-quantity') || (rvTdTextByColumn($row, 'quantity') || '').replace(/,/g, '') || 0) || 0,
                    purity_carat: $row.attr('data-purity-carat') || rvTdTextByColumn($row, 'purity-carat'),
                    purity_wt: parseFloat($row.attr('data-purity-weight') || (rvTdTextByColumn($row, 'purity-wt') || '').replace(/,/g, '') || 0) || 0,
                    rate: parseFloat($row.attr('data-rate') || (rvTdTextByColumn($row, 'rate') || '').replace(/,/g, '') || 0) || 0,
                    amount: amount,
                    item_code: $row.attr('data-item-code') || rvTdTextByColumn($row, 'item-code'),
                    barcode_no: $row.attr('data-barcode-no') || rvTdTextByColumn($row, 'barcode-no'),
                    card_no: $row.attr('data-card-no') || rvTdTextByColumn($row, 'card-no')
                };
                voucherData.items.push(item);
            }
        });

        // Receipt voucher: amount and metal wt are ADDED (summation). Total amount = sum of all item amounts; gold/silver from Metal & Scrap items.
        let totalAmount = 0;
        let totalGold = 0;
        let totalSilver = 0;
        voucherData.items.forEach(function(item) {
            totalAmount += parseFloat(item.amount || 0);
            const metalId = parseInt(item.metal_id, 10);
            if (item.payment_type === 'Metal' || item.payment_type === 'Scrap') {
                if (metalId === 1) totalGold += parseFloat(item.purity_wt || 0);
                else if (metalId === 2) totalSilver += parseFloat(item.purity_wt || 0);
            }
        });
        voucherData.total_amount = totalAmount;
        voucherData.total_gold = totalGold;
        voucherData.total_silver = totalSilver;

        if (typeof window.auragoldVoucherDiamondStoneAppendPendingToOrderData === 'function') {
            window.auragoldVoucherDiamondStoneAppendPendingToOrderData(voucherData);
        }

        // Validation
        if (!voucherData.customer_name) {
            alert('Please select a customer');
            return;
        }

        $.ajax({
            url: 'ajax/save-receipt-voucher.php',
            method: 'POST',
            data: voucherData,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    if (typeof window.auragoldVoucherDiamondStoneOnSaveSuccess === 'function') {
                        window.auragoldVoucherDiamondStoneOnSaveSuccess(response.voucher_id);
                    }
                    const voucherId = parseInt(response.voucher_id || voucherData.voucher_id || 0, 10) || 0;
                    // After save, always land on a blank form (no ?id=) — print modal closes to same page
                    const reportUrl = buildTransactionReportUrlAfterVoucherSave();
                    if (voucherId > 0 && typeof window.showPrintReceiptVoucherModal === 'function') {
                        window.pendingReceiptVoucherRedirectUrl = reportUrl;
                        setTimeout(function() {
                            window.showPrintReceiptVoucherModal(voucherId);
                        }, 100);
                    } else {
                        alert(response.message || 'Voucher saved successfully!');
                        window.location.href = reportUrl;
                    }
                } else {
                    alert(response.message || 'Error saving voucher');
                }
            },
            error: function() {
                alert('Error saving voucher. Please try again.');
            }
        });
    }

    // Load edit data if editing
    <?php if ($edit_voucher): ?>
    <?php
    require_once __DIR__ . '/includes/auragold_voucher_diamond_stock.php';
    require_once __DIR__ . '/includes/auragold_voucher_stone_stock.php';
    $rv_edit_di = auragold_voucher_list_diamond_issue_rows_for_kind($conn, 'receipt_voucher', (int) ($edit_voucher['id'] ?? 0));
    $rv_edit_si = auragold_voucher_list_stone_issue_rows_for_kind($conn, 'receipt_voucher', (int) ($edit_voucher['id'] ?? 0));
    ?>
    $(document).ready(function() {
        if (typeof setAuragoldPartyValue === 'function') {
            setAuragoldPartyValue(<?php echo json_encode((string)($edit_voucher['customer_id'] ?? '')); ?>, <?php echo json_encode($edit_voucher['customer_name'] ?? ''); ?>);
        } else {
            $('#customerId').val('<?php echo $edit_voucher['customer_id'] ?? ''; ?>');
            $('#customerName').val(<?php echo json_encode($edit_voucher['customer_name'] ?? ''); ?>);
        }
        $('#refNo').val('<?php echo htmlspecialchars($edit_voucher['ref_no'] ?? ''); ?>');
        $('#voucherType').val('<?php echo htmlspecialchars($edit_voucher['voucher_type'] ?? ''); ?>');
        $('#against').val('<?php echo htmlspecialchars($edit_voucher['against'] ?? ''); ?>');
        $('#salesPerson').val('<?php echo htmlspecialchars($edit_voucher['sales_person'] ?? ''); ?>');
        $('#againstOf').val('<?php echo htmlspecialchars($edit_voucher['against_of'] ?? ''); ?>');
        $('#currency').val('<?php echo htmlspecialchars($edit_voucher['currency'] ?? 'USD'); ?>');
        (function () {
            var er = <?php echo json_encode((string)($edit_voucher['exchange_rate'] ?? $edit_voucher['currency_rate'] ?? '')); ?>;
            if (typeof window.auragoldRestoreVoucherCurrencyRate === 'function') {
                window.auragoldRestoreVoucherCurrencyRate({
                    currency: <?php echo json_encode((string)($edit_voucher['currency'] ?? '')); ?>,
                    exchange_rate: er,
                    currency_rate: er
                });
            } else if (er !== '' && $('#currencyRate').length) {
                $('#currencyRate').val(er);
                $('#currencyRate')[0].dataset.userEdited = '1';
            }
        })();
        $('#voucherDate').val('<?php echo $edit_voucher['voucher_date'] ?? date('Y-m-d'); ?>');
        $('#dueDate').val(<?php echo json_encode(auragold_due_date_value($edit_voucher['due_date'] ?? null)); ?>);
        $('#fixingType').val('<?php echo htmlspecialchars($edit_voucher['fixing_type'] ?? 'Standard'); ?>');
        // Load previous balance and history for edit mode
        if ($('#customerId').val() || $('#customerName').val()) {
            loadCustomerBalance();
        } else {
            formatVoucherPreviousBalanceAmount(document.getElementById('previousBalanceAmount'), parseFloat('<?php echo (float)($edit_voucher['previous_balance'] ?? 0); ?>') || 0);
            formatVoucherPreviousBalanceMetal(document.getElementById('previousBalanceGold'), parseFloat('<?php echo (float)($edit_voucher['previous_gold'] ?? 0); ?>') || 0, 3, 'data-original-gold');
            formatVoucherPreviousBalanceMetal(document.getElementById('previousBalanceSilver'), parseFloat('<?php echo (float)($edit_voucher['previous_silver'] ?? 0); ?>') || 0, 3, 'data-original-silver');
            formatVoucherPreviousBalanceMetal(document.getElementById('previousBalanceDiamond'), parseFloat('<?php echo (float)($edit_voucher['previous_diamond'] ?? 0); ?>') || 0, 3, 'data-original-diamond');
            formatVoucherPreviousBalanceMetal(document.getElementById('previousBalanceGemstone'), parseFloat('<?php echo (float)($edit_voucher['previous_gemstone'] ?? 0); ?>') || 0, 3, 'data-original-gemstone');
        }
        $('#comment').val('<?php echo htmlspecialchars($edit_voucher['comment'] ?? ''); ?>');

        if (typeof window.auragoldVoucherDiamondStonePopulateFromOrder === 'function') {
            window.auragoldVoucherDiamondStonePopulateFromOrder({
                id: <?php echo (int) ($edit_voucher['id'] ?? 0); ?>,
                diamond_issues: <?php echo json_encode($rv_edit_di ?: [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
                stone_issues: <?php echo json_encode($rv_edit_si ?: [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
            });
        }

        // Remove "No payment entries" row and load saved items as full 18-column rows (all columns visible)
        <?php if (!empty($edit_items)): ?>
        $('#receiptTableBody .no-payment-row').remove();
        $('#receiptTableFooter').show();
        receiptRowIndex = 0;
        <?php
        $product_names = [];
        $metal_names = [];
        foreach ($products as $p) { $product_names[$p['id']] = addslashes($p['name']); }
        foreach ($metals as $m) { $metal_names[$m['id']] = addslashes($m['display_name']); }
        foreach ($edit_items as $item):
            $pid = (int)($item['product_id'] ?? 0);
            $mid = (int)($item['metal_id'] ?? 0);
            $product_name = isset($product_names[$pid]) ? $product_names[$pid] : '';
            $metal_name = isset($metal_names[$mid]) ? $metal_names[$mid] : '';
            $payment_type = addslashes($item['payment_type'] ?? '');
            $diamond_cat = addslashes($item['diamond_category'] ?? '');
            $txn_no = addslashes($item['transaction_no'] ?? '');
            $deposit_into = addslashes($item['deposit_into'] ?? '');
            $cheque_date = addslashes($item['cheque_date'] ?? '');
            $weight = (float)($item['weight'] ?? 0);
            $quantity = (float)($item['quantity'] ?? 0);
            $purity_carat = addslashes($item['purity_carat'] ?? '');
            $purity_wt = (float)($item['purity_wt'] ?? 0);
            $amount = (float)($item['amount'] ?? 0);
        ?>
        addReceiptRowFromPayment({
            payment_type: '<?php echo $payment_type; ?>',
            diamond_category: '<?php echo $diamond_cat; ?>',
            transaction_no: '<?php echo $txn_no; ?>',
            transfer_from: '',
            deposit_into: '<?php echo $deposit_into; ?>',
            product: '<?php echo $product_name; ?>',
            product_id: '<?php echo $pid; ?>',
            cheque_date: '<?php echo $cheque_date; ?>',
            weight: <?php echo $weight; ?>,
            metal: '<?php echo $metal_name; ?>',
            metal_id: '<?php echo $mid; ?>',
            quantity: <?php echo $quantity; ?>,
            purity_carat: '<?php echo $purity_carat; ?>',
            purity_weight: <?php echo $purity_wt; ?>,
            rate: 0,
            amount: <?php echo $amount; ?>,
            item_code: '',
            barcode_no: '',
            card_no: ''
        });
        <?php endforeach; ?>
        updateReceiptTotal();
        <?php endif; ?>
    });
    <?php endif; ?>
    
    // Load voucher into Payment List table
    function loadVoucherIntoPaymentList(voucherId) {
        $.ajax({
            url: 'ajax/get-receipt-voucher.php',
            method: 'GET',
            data: { id: voucherId },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success' && response.voucher) {
                    addVoucherToPaymentList(response.voucher);
                }
            },
            error: function() {
                console.error('Error loading voucher');
            }
        });
    }
    
    // Add voucher row to Payment List table
    function addVoucherToPaymentList(voucher, rowNum) {
        const tbody = $('#paymentListTableBody');
        const noRows = tbody.find('.no-rows');
        
        // Check if voucher already exists in table
        if (tbody.find(`tr[data-voucher-id="${voucher.id}"]`).length > 0) {
            return; // Already exists, don't add again
        }
        
        if (noRows.length > 0) {
            noRows.remove();
        }
        
        // Calculate payment type amounts from items
        let cash = 0, bank = 0, cheque = 0, upi = 0, card = 0, metal = 0;
        if (voucher.items && Array.isArray(voucher.items)) {
            voucher.items.forEach(function(item) {
                const amount = parseFloat(item.amount || item.purity_wt || 0);
                if (item.payment_type === 'Cash') cash += amount;
                else if (item.payment_type === 'Bank') bank += amount;
                else if (item.payment_type === 'Cheque') cheque += amount;
                else if (item.payment_type === 'UPI') upi += amount;
                else if (item.payment_type === 'Card') card += amount;
                else if (item.payment_type === 'Metal') metal += amount;
            });
        }
        
        if (rowNum == null) {
            const existingRows = tbody.find('tr[data-voucher-id]').length;
            rowNum = existingRows + 1;
        }
        const row = `
            <tr data-voucher-id="${voucher.id}" class="payment-list-row" style="cursor: pointer;" title="Click to edit">
                <td data-column="sr-no">${rowNum}</td>
                <td data-column="sales-person">${voucher.sales_person || ''}</td>
                <td data-column="voucher-date">${voucher.voucher_date || ''}</td>
                <td data-column="ledger-name">${voucher.customer_name || ''}</td>
                <td data-column="invoice-no">${voucher.voucher_no || ''}</td>
                <td data-column="branch-name">${voucher.branch_name || ''}</td>
                <td data-column="ref-no">${voucher.ref_no || ''}</td>
                <td data-column="against-voucher">${voucher.against || ''}</td>
                <td data-column="against-vo">${voucher.against_of || ''}</td>
                <td data-column="total-amt">${parseFloat(voucher.total_amount || 0).toFixed(2)}</td>
                <td data-column="total-wt">${parseFloat(voucher.total_gold || 0).toFixed(3)}</td>
                <td data-column="cash">${cash.toFixed(2)}</td>
                <td data-column="bank">${bank.toFixed(2)}</td>
                <td data-column="cheque">${cheque.toFixed(2)}</td>
                <td data-column="upi">${upi.toFixed(2)}</td>
                <td data-column="card">${card.toFixed(2)}</td>
                <td data-column="metal">${metal.toFixed(3)}</td>
                <td data-column="actions" class="text-center"><button type="button" class="btn btn-sm btn-danger payment-list-delete-btn" data-voucher-id="${voucher.id}" title="Delete voucher"><i class="feather icon-trash-2"></i></button></td>
            </tr>
        `;
        
        tbody.append(row);
        if (typeof window.pvReorderRowCells === 'function') {
            var tr = tbody.find('tr[data-voucher-id="' + voucher.id + '"]')[0];
            var keys = window.pvGetColumnOrderFromThead ? window.pvGetColumnOrderFromThead(document.querySelector('#paymentListTable thead tr')) : null;
            if (tr && keys && keys.length) window.pvReorderRowCells(tr, keys);
        }
    }

    function openVoucherForEdit(voucherId) {
        if (voucherId) {
            window.location.href = 'receipt-voucher.php?id=' + voucherId;
        }
    }

    let searchVoucherTimeout;
    $('#searchVoucherInput').on('input', function() {
        const q = $(this).val().trim();
        clearTimeout(searchVoucherTimeout);
        if (q.length < 1) {
            $('#searchVoucherDropdown').hide().empty();
            return;
        }
        searchVoucherTimeout = setTimeout(function() {
            $.ajax({
                url: 'ajax/search-receipt-vouchers.php',
                method: 'GET',
                data: { q: q, limit: 25 },
                dataType: 'json',
                success: function(response) {
                    const dd = $('#searchVoucherDropdown');
                    dd.empty();
                    if (response.status === 'success' && response.vouchers && response.vouchers.length > 0) {
                        response.vouchers.forEach(function(v) {
                            const row = $('<div class="search-voucher-item" style="padding: 6px 10px; cursor: pointer; font-size: 0.75rem; border-bottom: 1px solid #f1f5f9;" data-id="' + v.id + '"></div>');
                            row.html('<strong>' + (v.voucher_no || '') + '</strong> &ndash; ' + (v.customer_name || '') + ' <span style="color: #64748b;">' + (v.voucher_date || '') + ' | ' + parseFloat(v.total_amount || 0).toFixed(2) + '</span>');
                            row.on('click', function() {
                                openVoucherForEdit(v.id);
                            });
                            dd.append(row);
                        });
                        dd.show();
                    } else {
                        dd.append('<div style="padding: 8px 10px; font-size: 0.75rem; color: #64748b;">No vouchers found</div>');
                        dd.show();
                    }
                }
            });
        }, 300);
    });
    $('#searchVoucherInput').on('blur', function() {
        setTimeout(function() { $('#searchVoucherDropdown').hide(); }, 200);
    });

    $('#againstOfPickDocBtn').on('click', function() {
        var val = $('#againstOf').val();
        if (!val) {
            alert('Select an Against Of type first.');
            return;
        }
        var cid = document.getElementById('customerId') ? String(document.getElementById('customerId').value || '').trim() : '';
        if (!cid) {
            alert('Please select a customer from the list first, then choose the source document.');
            return;
        }
        /* Hook: document picker modal when implemented (see sale-invoice.php). */
    });
    
    // Payment List: delete voucher (stop propagation so row click doesn't fire)
    $(document).on('click', '#paymentListTableBody .payment-list-delete-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const voucherId = $(this).data('voucher-id');
        if (!voucherId || !confirm('Are you sure you want to delete this receipt voucher? This cannot be undone.')) return;
        const $btn = $(this);
        $btn.prop('disabled', true);
        $.ajax({
            url: 'ajax/delete-receipt-voucher.php',
            method: 'POST',
            data: { id: voucherId },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    if (typeof response.message === 'string') alert(response.message);
                    window.location.reload();
                } else {
                    alert(response.message || 'Failed to delete voucher');
                    $btn.prop('disabled', false);
                }
            },
            error: function() {
                alert('Error deleting voucher');
                $btn.prop('disabled', false);
            }
        });
    });

    // Click on payment list row: open voucher in edit mode
    $(document).on('click', '#paymentListTableBody tr[data-voucher-id]', function(e) {
        if ($(e.target).closest('.payment-list-delete-btn').length) return;
        openVoucherForEdit($(this).attr('data-voucher-id'));
    });
    
    var paymentListPager = null;

    function loadPaymentListVouchers(filters, page) {
        if (paymentListPager) {
            paymentListPager.load(filters, page);
        }
    }

    // Load existing vouchers on page load
    $(document).ready(function() {
        if (typeof initPaymentListPagination === 'function') {
            paymentListPager = initPaymentListPagination({
                getUrl: 'ajax/get-receipt-vouchers.php',
                pageSize: 10,
                onSuccess: function(response, state) {
                    const tbody = $('#paymentListTableBody');
                    tbody.empty();
                    if (response.status === 'success' && response.vouchers && response.vouchers.length > 0) {
                        var startNum = ((state.page - 1) * state.pageSize) + 1;
                        response.vouchers.forEach(function(voucher, i) {
                            addVoucherToPaymentList(voucher, startNum + i);
                        });
                    } else {
                        tbody.html('<tr class="no-rows"><td colspan="18" class="text-center text-muted py-4">No Rows To Show</td></tr>');
                    }
                    <?php if ($edit_voucher_id > 0): ?>
                    $('#paymentListTableBody tr[data-voucher-id="<?php echo (int)$edit_voucher_id; ?>"]').addClass('editing-row');
                    <?php endif; ?>
                },
                onError: function(xhr, status, error) {
                    console.error('Error loading vouchers:', error);
                    console.error('Response:', xhr.responseText);
                }
            });
            paymentListPager.load({}, 1);
        }

        if (typeof initVoucherListFilterExport === 'function') {
            initVoucherListFilterExport({
                onApplyFilter: function(filters) {
                    loadPaymentListVouchers(filters, 1);
                },
                exportExcelUrl: 'ajax/export-voucher-list-excel.php?kind=receipt',
                exportPdfUrl: 'ajax/export-voucher-list-pdf.php?kind=receipt'
            });
        }
    });
    </script>

    <?php include __DIR__ . '/includes/voucher-list-advance-filter-modal.php'; ?>

    <!-- Receipt Columns Settings Modal -->
    <div id="receiptColumnsModal" class="filter-modal" style="display: none;">
        <div class="filter-modal-content" style="max-width: 500px;">
            <div class="filter-modal-header" style="background: #11294b; color: #fff; padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; border-radius: 8px 8px 0 0;">
                <h5 style="margin: 0; font-size: 0.95rem; font-weight: 600;"><i class="feather icon-settings"></i> Columns</h5>
                <div style="display: flex; gap: 8px;">
                    <button onclick="refreshReceiptColumns()" title="Refresh" style="background: none; border: none; color: #fff; font-size: 16px; cursor: pointer; padding: 4px;">
                        <i class="feather icon-refresh-cw"></i>
                    </button>
                    <button onclick="closeReceiptColumnsModal()" style="background: none; border: none; color: #fff; font-size: 20px; cursor: pointer; padding: 0; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;">&times;</button>
                </div>
            </div>
            <div class="filter-modal-body" style="padding: 16px;">
                <div style="margin-bottom: 12px;">
                    <input type="text" id="receiptColumnSearch" class="form-control" placeholder="Search" onkeyup="filterReceiptColumns()" style="padding: 6px 12px; font-size: 0.85rem; height: 32px; border: 1px solid #e2e8f0; border-radius: 4px;">
                </div>
                <div id="receiptColumnsList" style="max-height: 400px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px;">
                    <!-- Columns will be populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>

    <style>
    .filter-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .filter-modal.active,
    .filter-modal[style*="display: block"] {
        display: flex !important;
    }
    .filter-modal-content {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        max-width: 500px;
        width: 90%;
        max-height: 90vh;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
    .filter-modal-body {
        padding: 16px;
        overflow-y: auto;
    }
    #receiptColumnsList .column-item {
        display: flex;
        align-items: center;
        padding: 8px 12px;
        margin-bottom: 4px;
        border-radius: 4px;
        cursor: pointer;
        transition: background 0.2s;
    }
    #receiptColumnsList .column-item:hover {
        background: #f8fafc;
    }
    #receiptColumnsList .column-item input[type="checkbox"] {
        margin-right: 10px;
        cursor: pointer;
        width: 16px;
        height: 16px;
    }
    #receiptColumnsList .column-item label {
        margin: 0;
        cursor: pointer;
        font-size: 0.85rem;
        color: #334155;
        flex: 1;
    }
    </style>

    <script>
    // Receipt Table Column Definitions
    const receiptColumnDefinitions = [
        { key: 'payment-type', label: 'Payment Type' },
        { key: 'diamond-category', label: 'Diamond Category' },
        { key: 'transaction-no', label: 'Transaction No.' },
        { key: 'transfer-from', label: 'Transfer From' },
        { key: 'deposit-into', label: 'Deposit Into' },
        { key: 'product', label: 'Product' },
        { key: 'cheque-dt', label: 'Cheque Dt.' },
        { key: 'weight', label: 'Weight' },
        { key: 'metal', label: 'Metal' },
        { key: 'quantity', label: 'Quantity' },
        { key: 'purity-carat', label: 'Purity / Carat' },
        { key: 'purity-wt', label: 'Purity Wt' },
        { key: 'rate', label: 'Rate' },
        { key: 'amount', label: 'Amount' },
        { key: 'item-code', label: 'Item Code' },
        { key: 'barcode-no', label: 'Barcode No.' },
        { key: 'card-no', label: 'Card No.' },
        { key: 'actions', label: 'Actions' }
    ];

    const paymentListColumnDefinitions = [
        { key: 'sr-no', label: 'Sr.No.' },
        { key: 'sales-person', label: 'Sales Person' },
        { key: 'voucher-date', label: 'Date' },
        { key: 'ledger-name', label: 'Ledger Name' },
        { key: 'invoice-no', label: 'Invoice' },
        { key: 'branch-name', label: 'Branch Name' },
        { key: 'ref-no', label: 'Ref No.' },
        { key: 'against-voucher', label: 'Against Voucher' },
        { key: 'against-vo', label: 'Against Vo' },
        { key: 'total-amt', label: 'Total Amt' },
        { key: 'total-wt', label: 'Total Wt' },
        { key: 'cash', label: 'Cash' },
        { key: 'bank', label: 'Bank' },
        { key: 'cheque', label: 'Cheque' },
        { key: 'upi', label: 'UPI' },
        { key: 'card', label: 'Card' },
        { key: 'metal', label: 'Metal' },
        { key: 'actions', label: 'Actions' }
    ];

    const PV_PAGE_NAME = 'receipt-voucher';
    const PV_TAB_RECEIPT = 'receipt';
    const PV_TAB_PAYMENT_LIST = 'payment_list';
    const PV_DEFAULT_WIDTHS_RECEIPT = {
        'payment-type': 118, 'diamond-category': 148, 'transaction-no': 132, 'transfer-from': 128, 'deposit-into': 128,
        'product': 152, 'cheque-dt': 108, 'weight': 90, 'metal': 96, 'quantity': 94, 'purity-carat': 132, 'purity-wt': 102,
        'rate': 84, 'amount': 102, 'item-code': 112, 'barcode-no': 128, 'card-no': 108, 'actions': 92
    };
    const PV_DEFAULT_WIDTHS_PAYMENT_LIST = {
        'sr-no': 56, 'sales-person': 138, 'voucher-date': 104, 'ledger-name': 168, 'invoice-no': 128, 'branch-name': 132,
        'ref-no': 102, 'against-voucher': 168, 'against-vo': 136, 'total-amt': 108, 'total-wt': 102,
        'cash': 88, 'bank': 88, 'cheque': 92, 'upi': 80, 'card': 80, 'metal': 96, 'actions': 94
    };
    function pvMergeSavedWidthsWithDefaults(saved, defaultMap) {
        var out = {};
        var corrected = false;
        Object.keys(defaultMap).forEach(function (k) {
            var defW = defaultMap[k];
            var sw = saved && saved[k] != null ? parseInt(saved[k], 10) : 0;
            if (sw >= 60 && sw <= 2000) out[k] = sw;
            else {
                out[k] = defW;
                if (saved && saved[k] != null) corrected = true;
            }
        });
        return { widths: out, corrected: corrected };
    }
    let pvReceiptSortable = null;
    let pvPaymentListSortable = null;
    let pvSaveOrderTimer = null;
    function pvDefaultKeysFromDefs(defs) { return defs.map(function (c) { return c.key; }); }
    function pvMergeOrderWithDefaults(savedOrder, defaultKeys) {
        var seen = Object.create(null);
        var out = [];
        (savedOrder || []).forEach(function (k) {
            if (!k || defaultKeys.indexOf(k) === -1 || seen[k]) return;
            seen[k] = true;
            out.push(k);
        });
        defaultKeys.forEach(function (k) { if (!seen[k]) out.push(k); });
        var ai = out.indexOf('actions');
        if (ai >= 0 && ai !== out.length - 1) { out.splice(ai, 1); out.push('actions'); }
        return out;
    }
    function pvGetColumnOrderFromThead(tr) {
        if (!tr) return [];
        var keys = [];
        tr.querySelectorAll('th[data-column]').forEach(function (th) {
            var k = th.getAttribute('data-column');
            if (k) keys.push(k);
        });
        return keys;
    }
    function pvReorderRowCells(row, orderedKeys) {
        if (!row || !orderedKeys || !orderedKeys.length) return;
        var map = Object.create(null);
        row.querySelectorAll('td[data-column]').forEach(function (td) {
            var k = td.getAttribute('data-column');
            if (k) map[k] = td;
        });
        orderedKeys.forEach(function (k) { if (map[k]) row.appendChild(map[k]); });
    }
    function pvReorderTableColumns(tableId, orderedKeys) {
        var table = document.getElementById(tableId);
        if (!table || !orderedKeys || !orderedKeys.length) return;
        var thr = table.querySelector('thead tr');
        if (!thr) return;
        var hmap = Object.create(null);
        thr.querySelectorAll('th[data-column]').forEach(function (th) {
            var k = th.getAttribute('data-column');
            if (k) hmap[k] = th;
        });
        orderedKeys.forEach(function (k) { if (hmap[k]) thr.appendChild(hmap[k]); });
        var tbody = table.querySelector('tbody');
        if (tbody) {
            tbody.querySelectorAll('tr').forEach(function (row) {
                if (row.classList.contains('no-payment-row') || row.classList.contains('no-rows')) return;
                pvReorderRowCells(row, orderedKeys);
            });
        }
        var tfoot = table.querySelector('tfoot');
        if (tfoot) {
            var fr = tfoot.querySelector('tr');
            if (fr) pvReorderRowCells(fr, orderedKeys);
        }
    }
    function pvSaveColumnOrderToServer(tabKey, orderedKeys) {
        if (!orderedKeys || !orderedKeys.length) return;
        try { localStorage.setItem('pv-col-order:' + PV_PAGE_NAME + ':' + tabKey, JSON.stringify(orderedKeys)); } catch (e1) {}
        if (pvSaveOrderTimer) clearTimeout(pvSaveOrderTimer);
        pvSaveOrderTimer = setTimeout(function () {
            $.ajax({
                url: 'ajax/save-product-modal-column-preferences.php',
                method: 'POST',
                data: { page_name: PV_PAGE_NAME, tab_key: tabKey, order_keys: JSON.stringify(orderedKeys) },
                dataType: 'json'
            });
        }, 400);
    }
    function pvLoadColumnOrder(tabKey, defs, callback) {
        var fallback = function () {
            try {
                var raw = localStorage.getItem('pv-col-order:' + PV_PAGE_NAME + ':' + tabKey);
                if (raw) {
                    var o = JSON.parse(raw);
                    if (Array.isArray(o) && o.length) {
                        callback(pvMergeOrderWithDefaults(o, pvDefaultKeysFromDefs(defs)));
                        return;
                    }
                }
            } catch (e2) {}
            callback(pvDefaultKeysFromDefs(defs));
        };
        $.ajax({
            url: 'ajax/get-column-preferences.php',
            method: 'POST',
            data: { page_name: PV_PAGE_NAME, tab_key: tabKey },
            dataType: 'json',
            success: function (res) {
                if (res && res.status === 'success' && res.preferences && res.preferences.length) {
                    var prefs = res.preferences.slice().sort(function (a, b) {
                        return (parseInt(a.column_order, 10) || 0) - (parseInt(b.column_order, 10) || 0);
                    });
                    var keys = prefs.map(function (p) { return p.column_key; }).filter(Boolean);
                    if (keys.length) {
                        callback(pvMergeOrderWithDefaults(keys, pvDefaultKeysFromDefs(defs)));
                        return;
                    }
                }
                fallback();
            },
            error: function () { fallback(); }
        });
    }
    function pvInitReceiptColumnDrag() {
        var tr = document.querySelector('#receiptTable thead tr');
        if (!tr || typeof Sortable === 'undefined') return;
        if (pvReceiptSortable) { try { pvReceiptSortable.destroy(); } catch (eD) {} pvReceiptSortable = null; }
        pvReceiptSortable = Sortable.create(tr, {
            animation: 150,
            handle: '.pv-col-drag-h',
            draggable: 'th',
            filter: '[data-column="actions"]',
            preventOnFilter: false,
            onEnd: function () {
                var keys = pvGetColumnOrderFromThead(tr);
                document.querySelectorAll('#receiptTable tbody tr:not(.no-payment-row)').forEach(function (row) { pvReorderRowCells(row, keys); });
                var fr = document.querySelector('#receiptTableFooter tr');
                if (fr) pvReorderRowCells(fr, keys);
                applyReceiptColumnVisibility();
                pvSaveColumnOrderToServer(PV_TAB_RECEIPT, keys);
            }
        });
    }
    function pvInitPaymentListColumnDrag() {
        var tr = document.querySelector('#paymentListTable thead tr');
        if (!tr || typeof Sortable === 'undefined') return;
        if (pvPaymentListSortable) { try { pvPaymentListSortable.destroy(); } catch (eD2) {} pvPaymentListSortable = null; }
        pvPaymentListSortable = Sortable.create(tr, {
            animation: 150,
            handle: '.pv-col-drag-h',
            draggable: 'th',
            filter: '[data-column="actions"]',
            preventOnFilter: false,
            onEnd: function () {
                var keys = pvGetColumnOrderFromThead(tr);
                document.querySelectorAll('#paymentListTableBody tr[data-voucher-id]').forEach(function (row) { pvReorderRowCells(row, keys); });
                pvSaveColumnOrderToServer(PV_TAB_PAYMENT_LIST, keys);
            }
        });
    }
    window.pvGetColumnOrderFromThead = pvGetColumnOrderFromThead;
    window.pvReorderRowCells = pvReorderRowCells;
    var pvSaveWidthsTimer = null;
    function pvWidthsStorageKey(tabKey) { return 'pv-col-widths:' + PV_PAGE_NAME + ':' + tabKey; }
    function pvLoadColumnWidths(tabKey) {
        try {
            var raw = localStorage.getItem(pvWidthsStorageKey(tabKey));
            if (!raw) return null;
            var o = JSON.parse(raw);
            return o && typeof o === 'object' ? o : null;
        } catch (eW) { return null; }
    }
    function pvSaveColumnWidths(tabKey, widths) {
        try { localStorage.setItem(pvWidthsStorageKey(tabKey), JSON.stringify(widths)); } catch (eS) {}
    }
    function pvDebouncedSaveColumnWidths(tabKey, tableId) {
        if (pvSaveWidthsTimer) clearTimeout(pvSaveWidthsTimer);
        pvSaveWidthsTimer = setTimeout(function () {
            pvSaveColumnWidths(tabKey, pvCollectWidthsFromTable(tableId));
        }, 350);
    }
    function pvSetColumnWidthPx(table, colKey, px) {
        if (!table || !colKey) return;
        var n = colKey === 'actions' ? Math.max(72, Math.min(160, Math.round(px))) : Math.max(48, Math.min(900, Math.round(px)));
        table.querySelectorAll('th[data-column="' + colKey + '"], td[data-column="' + colKey + '"]').forEach(function (cell) {
            cell.style.width = n + 'px';
            cell.style.minWidth = n + 'px';
            cell.style.maxWidth = n + 'px';
        });
    }
    function pvApplyColumnWidths(tableId, widths) {
        var table = document.getElementById(tableId);
        if (!table || !widths || typeof widths !== 'object') return;
        Object.keys(widths).forEach(function (key) {
            var px = parseInt(widths[key], 10);
            if (key === 'actions') { if (px >= 60) pvSetColumnWidthPx(table, key, px); return; }
            if (!px || px < 48) return;
            pvSetColumnWidthPx(table, key, px);
        });
    }
    function pvCollectWidthsFromTable(tableId) {
        var table = document.getElementById(tableId);
        var out = {};
        if (!table) return out;
        table.querySelectorAll('thead th[data-column]').forEach(function (th) {
            var k = th.getAttribute('data-column');
            if (!k) return;
            var w = th.offsetWidth;
            if (k === 'actions') { if (w >= 60) out[k] = w; }
            else if (w >= 48) out[k] = w;
        });
        return out;
    }
    function pvWrapThLabelText(tableId) {
        var table = document.getElementById(tableId);
        if (!table) return;
        table.querySelectorAll('thead th[data-column]').forEach(function (th) {
            var k = th.getAttribute('data-column');
            if (k === 'actions' || th.querySelector('.pv-th-inner')) return;
            var drag = th.querySelector('.pv-col-drag-h');
            var res = th.querySelector('.pv-col-resizer');
            var existingText = th.querySelector('.pv-th-text');
            var inner = document.createElement('span');
            inner.className = 'pv-th-inner';
            if (existingText) {
                if (drag) th.removeChild(drag);
                th.removeChild(existingText);
                if (drag) inner.appendChild(drag);
                inner.appendChild(existingText);
            } else {
                var textWrap = document.createElement('span');
                textWrap.className = 'pv-th-text';
                var toMove = [];
                Array.from(th.childNodes).forEach(function (n) { if (n !== drag && n !== res) toMove.push(n); });
                toMove.forEach(function (n) { textWrap.appendChild(n); });
                if (drag) th.removeChild(drag);
                if (drag) inner.appendChild(drag);
                inner.appendChild(textWrap);
            }
            if (res) th.insertBefore(inner, res);
            else th.appendChild(inner);
        });
    }
    function pvRemoveColumnResizers(tableId) {
        var table = document.getElementById(tableId);
        if (!table) return;
        table.querySelectorAll('thead .pv-col-resizer').forEach(function (el) { el.remove(); });
    }
    function pvInstallColumnResizers(tableId, tabKey) {
        var table = document.getElementById(tableId);
        if (!table) return;
        pvRemoveColumnResizers(tableId);
        table.querySelectorAll('thead th[data-column]').forEach(function (th) {
            var col = th.getAttribute('data-column');
            if (!col || col === 'actions') return;
            var grip = document.createElement('span');
            grip.className = 'pv-col-resizer';
            grip.setAttribute('title', 'Drag to resize column');
            grip.setAttribute('aria-hidden', 'true');
            th.appendChild(grip);
            grip.addEventListener('mousedown', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var startX = e.pageX;
                var startW = th.offsetWidth;
                function onMove(e2) { pvSetColumnWidthPx(table, col, startW + (e2.pageX - startX)); }
                function onUp() {
                    document.removeEventListener('mousemove', onMove);
                    document.removeEventListener('mouseup', onUp);
                    document.body.style.cursor = '';
                    pvDebouncedSaveColumnWidths(tabKey, tableId);
                }
                document.body.style.cursor = 'col-resize';
                document.addEventListener('mousemove', onMove);
                document.addEventListener('mouseup', onUp);
            });
        });
    }

    const RECEIPT_LINE_COLUMNS_STORAGE_KEY = <?php echo json_encode('auragold_voucher_line_columns_' . pathinfo(__FILE__, PATHINFO_FILENAME)); ?>;

    // Get column preferences from localStorage
    function getReceiptColumnPreferences() {
        const saved = localStorage.getItem(RECEIPT_LINE_COLUMNS_STORAGE_KEY);
        if (saved) {
            return JSON.parse(saved);
        }
        // Default: all columns visible
        const defaults = {};
        receiptColumnDefinitions.forEach(col => {
            defaults[col.key] = true;
        });
        return defaults;
    }

    // Save column preferences to localStorage
    function saveReceiptColumnPreferences(prefs) {
        localStorage.setItem(RECEIPT_LINE_COLUMNS_STORAGE_KEY, JSON.stringify(prefs));
    }

    // Open columns modal
    function openReceiptColumnsModal() {
        renderReceiptColumnsList();
        const modal = document.getElementById('receiptColumnsModal');
        if (modal) {
            modal.style.display = 'flex';
            modal.classList.add('active');
        }
    }

    // Close columns modal
    function closeReceiptColumnsModal() {
        const modal = document.getElementById('receiptColumnsModal');
        if (modal) {
            modal.style.display = 'none';
            modal.classList.remove('active');
        }
    }

    // Refresh columns (reset to defaults)
    function refreshReceiptColumns() {
        const defaults = {};
        receiptColumnDefinitions.forEach(col => {
            defaults[col.key] = true;
        });
        saveReceiptColumnPreferences(defaults);
        applyReceiptColumnVisibility();
        renderReceiptColumnsList();
    }

    // Render columns list in modal
    function renderReceiptColumnsList() {
        const columnsList = document.getElementById('receiptColumnsList');
        if (!columnsList) return;
        
        const columnPrefs = getReceiptColumnPreferences();
        columnsList.innerHTML = '';
        
        receiptColumnDefinitions.forEach(col => {
            const item = document.createElement('div');
            item.className = 'column-item';
            const isChecked = columnPrefs[col.key] !== false; // Default to true
            item.innerHTML = `
                <input type="checkbox" id="receipt_col_${col.key}" ${isChecked ? 'checked' : ''} onchange="toggleReceiptColumn('${col.key}', this.checked)">
                <label for="receipt_col_${col.key}">${col.label}</label>
            `;
            columnsList.appendChild(item);
        });
    }

    // Filter columns in modal
    function filterReceiptColumns() {
        const search = document.getElementById('receiptColumnSearch').value.toLowerCase();
        const items = document.querySelectorAll('#receiptColumnsList .column-item');
        
        items.forEach(item => {
            const label = item.querySelector('label').textContent.toLowerCase();
            item.style.display = label.includes(search) ? 'flex' : 'none';
        });
    }

    // Toggle column visibility
    function toggleReceiptColumn(key, visible) {
        const columnPrefs = getReceiptColumnPreferences();
        columnPrefs[key] = visible;
        saveReceiptColumnPreferences(columnPrefs);
        applyReceiptColumnVisibility();
    }

    // Apply column visibility to table
    function applyReceiptColumnVisibility() {
        const columnPrefs = getReceiptColumnPreferences();
        
        receiptColumnDefinitions.forEach(col => {
            const isVisible = columnPrefs[col.key] !== false;
            const selector = `[data-column="${col.key}"]`;
            const headers = document.querySelectorAll(`#receiptTable th${selector}`);
            const cells = document.querySelectorAll(`#receiptTable td${selector}`);
            
            headers.forEach(header => {
                if (isVisible) {
                    header.style.display = '';
                } else {
                    header.style.display = 'none';
                }
            });
            
            cells.forEach(cell => {
                if (isVisible) {
                    cell.style.display = '';
                } else {
                    cell.style.display = 'none';
                }
            });
        });
        
        // Update colspan for empty state row
        const emptyRow = document.querySelector('#receiptTableBody .no-payment-row');
        if (emptyRow) {
            const visibleColumns = receiptColumnDefinitions.filter(col => columnPrefs[col.key] !== false).length;
            emptyRow.querySelector('td').setAttribute('colspan', visibleColumns);
        }
        
        document.querySelectorAll('#receiptTableFooter td[data-column]').forEach(function(td) {
            const k = td.getAttribute('data-column');
            if (!k) return;
            const isVisible = columnPrefs[k] !== false;
            td.style.display = isVisible ? '' : 'none';
        });
    }

    // Close modal when clicking outside
    document.addEventListener('click', function(e) {
        const modal = document.getElementById('receiptColumnsModal');
        if (modal && e.target === modal) {
            closeReceiptColumnsModal();
        }
    });

    $(document).on('mouseenter', '#receiptTable thead th[data-column], #paymentListTable thead th[data-column]', function () {
        var th = this;
        var k = th.getAttribute('data-column');
        if (k === 'actions') return;
        var pt = th.querySelector('.pv-th-text');
        if (pt && pt.scrollWidth > pt.clientWidth) {
            th.title = (pt.textContent || '').replace(/\s+/g, ' ').trim();
        } else {
            th.removeAttribute('title');
        }
    });
    $(document).on('mouseenter', '#receiptTable tbody td[data-column], #receiptTable tfoot td[data-column], #paymentListTable tbody td[data-column]', function () {
        var el = this;
        var k = el.getAttribute('data-column');
        if (k === 'actions') return;
        var inp = el.querySelector('input:not([type="hidden"]),select,textarea');
        if (inp) {
            if (inp.tagName === 'SELECT') {
                var opt = inp.options[inp.selectedIndex];
                el.title = opt ? (opt.text || '').trim() : '';
            } else {
                var v = (inp.value != null ? String(inp.value) : '').trim();
                el.title = (inp.scrollWidth > inp.clientWidth || v.length > 80) ? v : '';
            }
            return;
        }
        if (el.querySelector('.action-btns,.payment-list-delete-btn')) {
            el.removeAttribute('title');
            return;
        }
        var t = (el.textContent || '').replace(/\s+/g, ' ').trim();
        if (t && el.scrollWidth > el.clientWidth) el.title = t;
        else el.removeAttribute('title');
    });

    $(document).ready(function() {
        pvLoadColumnOrder(PV_TAB_RECEIPT, receiptColumnDefinitions, function (keys) {
            pvReorderTableColumns('receiptTable', keys);
            applyReceiptColumnVisibility();
            pvWrapThLabelText('receiptTable');
            var rwMerged = pvMergeSavedWidthsWithDefaults(pvLoadColumnWidths(PV_TAB_RECEIPT), PV_DEFAULT_WIDTHS_RECEIPT);
            pvApplyColumnWidths('receiptTable', rwMerged.widths);
            if (rwMerged.corrected) pvSaveColumnWidths(PV_TAB_RECEIPT, rwMerged.widths);
            pvInitReceiptColumnDrag();
            pvInstallColumnResizers('receiptTable', PV_TAB_RECEIPT);
        });
        pvLoadColumnOrder(PV_TAB_PAYMENT_LIST, paymentListColumnDefinitions, function (keys) {
            pvReorderTableColumns('paymentListTable', keys);
            pvWrapThLabelText('paymentListTable');
            var pwMerged = pvMergeSavedWidthsWithDefaults(pvLoadColumnWidths(PV_TAB_PAYMENT_LIST), PV_DEFAULT_WIDTHS_PAYMENT_LIST);
            pvApplyColumnWidths('paymentListTable', pwMerged.widths);
            if (pwMerged.corrected) pvSaveColumnWidths(PV_TAB_PAYMENT_LIST, pwMerged.widths);
            pvInitPaymentListColumnDrag();
            pvInstallColumnResizers('paymentListTable', PV_TAB_PAYMENT_LIST);
        });
    });
    </script>

<!-- Print Receipt Voucher (same flow as payment voucher) -->
<div id="printReceiptVoucherModal" class="print-invoice-modal" style="display: none;">
    <div class="print-invoice-modal-content">
        <button type="button" class="print-invoice-modal-close" onclick="closePrintReceiptVoucherModal()">&times;</button>
        <div class="print-invoice-modal-icon">
            <div class="receipt-icon-wrapper">
                <div class="receipt-paper">
                    <div class="receipt-lines">
                        <div class="receipt-line"></div>
                        <div class="receipt-line"></div>
                        <div class="receipt-line"></div>
                    </div>
                    <div class="receipt-dollar">$</div>
                    <div class="receipt-checkmark">✓</div>
                </div>
            </div>
        </div>
        <h3 class="print-invoice-modal-title">Print bill</h3>
        <p class="print-invoice-modal-message">Do you want to print receipt voucher?</p>
        <div class="print-invoice-modal-buttons">
            <button type="button" class="print-invoice-btn-yes" onclick="confirmPrintReceiptVoucher()">Print</button>
            <button type="button" class="print-invoice-btn-no" onclick="closePrintReceiptVoucherModal()">No</button>
        </div>
    </div>
</div>

<style>
.print-invoice-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
}
.print-invoice-modal-content {
    background: #fff;
    border-radius: 16px;
    padding: 40px 30px 30px;
    max-width: 400px;
    width: 90%;
    text-align: center;
    position: relative;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
}
.print-invoice-modal-close {
    position: absolute;
    top: 15px;
    right: 15px;
    background: none;
    border: none;
    font-size: 24px;
    color: #9CA3AF;
    cursor: pointer;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.2s;
}
.print-invoice-modal-close:hover {
    background: #F3F4F6;
    color: #6B7280;
}
.print-invoice-modal-icon {
    margin-bottom: 25px;
    display: flex;
    justify-content: center;
}
.receipt-icon-wrapper {
    width: 100px;
    height: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.receipt-paper {
    width: 70px;
    height: 90px;
    background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
    border-radius: 8px;
    position: relative;
    box-shadow: 0 4px 12px rgba(14, 165, 233, 0.25);
}
.receipt-lines {
    padding: 14px 10px 0;
}
.receipt-line {
    height: 4px;
    background: rgba(14, 165, 233, 0.35);
    border-radius: 2px;
    margin-bottom: 6px;
}
.receipt-dollar {
    position: absolute;
    bottom: 12px;
    left: 12px;
    font-size: 22px;
    font-weight: 700;
    color: #0284c7;
}
.receipt-checkmark {
    position: absolute;
    bottom: 10px;
    right: 10px;
    width: 28px;
    height: 28px;
    background: #22c55e;
    color: #fff;
    border-radius: 50%;
    font-size: 16px;
    line-height: 28px;
}
.print-invoice-modal-title {
    margin: 0 0 10px;
    font-size: 1.35rem;
    color: #11294b;
}
.print-invoice-modal-message {
    margin: 0 0 24px;
    color: #64748b;
    font-size: 0.95rem;
}
.print-invoice-modal-buttons {
    display: flex;
    gap: 12px;
    justify-content: center;
}
.print-invoice-btn-yes,
.print-invoice-btn-no {
    padding: 12px 28px;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    border: none;
    transition: transform 0.15s, box-shadow 0.15s;
}
.print-invoice-btn-yes {
    background: #11294b;
    color: #fff;
}
.print-invoice-btn-yes:hover {
    background: #1e3a5f;
    transform: translateY(-1px);
}
.print-invoice-btn-no {
    background: #FCE7F3;
    color: #EC4899;
}
.print-invoice-btn-no:hover {
    background: #FBCFE8;
    transform: translateY(-1px);
}
</style>

<script>
(function() {
    let savedReceiptVoucherId = null;

    function showPrintReceiptVoucherModal(voucherId) {
        savedReceiptVoucherId = voucherId;
        setTimeout(function() {
            const modal = document.getElementById('printReceiptVoucherModal');
            if (modal) {
                modal.style.display = 'flex';
                modal.style.zIndex = '10000';
            } else if (confirm('Receipt voucher saved. Do you want to print?')) {
                window.open('receipt-voucher-print.php?id=' + voucherId, '_blank', 'width=1200,height=800');
                if (window.pendingReceiptVoucherRedirectUrl) {
                    window.location.href = window.pendingReceiptVoucherRedirectUrl;
                    window.pendingReceiptVoucherRedirectUrl = null;
                }
            }
        }, 200);
    }

    function closePrintReceiptVoucherModal() {
        const modal = document.getElementById('printReceiptVoucherModal');
        if (modal) {
            modal.style.display = 'none';
        }
        savedReceiptVoucherId = null;
        if (window.pendingReceiptVoucherRedirectUrl) {
            window.location.href = window.pendingReceiptVoucherRedirectUrl;
            window.pendingReceiptVoucherRedirectUrl = null;
        }
    }

    function confirmPrintReceiptVoucher() {
        if (savedReceiptVoucherId) {
            window.open('receipt-voucher-print.php?id=' + savedReceiptVoucherId, '_blank', 'width=1200,height=800');
        }
        closePrintReceiptVoucherModal();
    }

    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('printReceiptVoucherModal');
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closePrintReceiptVoucherModal();
                }
            });
        }
    });

    window.showPrintReceiptVoucherModal = showPrintReceiptVoucherModal;
    window.closePrintReceiptVoucherModal = closePrintReceiptVoucherModal;
    window.confirmPrintReceiptVoucher = confirmPrintReceiptVoucher;
})();
</script>
</body>
</html>
