<?php 
session_start();
require_once 'config.php';
require_once __DIR__ . '/includes/auragold_party_select2.php';
require_once __DIR__ . '/includes/user_management_schema.php';

// Load master data
$metals = getList("SELECT id, display_name, system_name FROM tbl_metal WHERE status = 1 " . auragold_master_list_sql_suffix($conn, 'tbl_metal') . " ORDER BY id ASC");
require_once __DIR__ . '/includes/auragold_voucher_runtime_settings.php';
$auragold_voucher_runtime_client = auragold_voucher_runtime_bootstrap($conn, $metals, 'Advance Payment');
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

// Next voucher no.: Bill Series for Advance Payment (branch-wise tbl_bill_series), else legacy AP-1, AP-2
$next_voucher_no = function_exists('getNextAdvancePaymentNo') ? getNextAdvancePaymentNo($conn) : 'AP-1';

// Load voucher for editing if ID provided
$edit_voucher_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$edit_voucher = null;
$edit_items = [];

$auragold_voucher_ds_kind = 'advance_payment';
$auragold_voucher_ds_db_id = (int) ($edit_voucher_id ?? 0);

if ($edit_voucher_id > 0) {
    $edit_voucher = getRecord("SELECT * FROM tbl_advance_payments WHERE id = $edit_voucher_id");
    if ($edit_voucher) {
        $edit_items = getList("SELECT * FROM tbl_advance_payment_items WHERE voucher_id = $edit_voucher_id");
        $next_voucher_no = $edit_voucher['voucher_no'];
    }
}

$ap_voucher_date_val = date('Y-m-d');
$ap_due_date_val = '';
$ap_sales_person_default = trim((string)($_SESSION['user_name'] ?? 'SUPER ADMIN'));
if (!empty($edit_voucher) && is_array($edit_voucher)) {
    if (!empty($edit_voucher['voucher_date'])) {
        $ap_voucher_date_val = substr($edit_voucher['voucher_date'], 0, 10);
    }
    $ap_due_date_val = auragold_due_date_value($edit_voucher['due_date'] ?? null);
    if (trim((string)($edit_voucher['sales_person'] ?? '')) !== '') {
        $ap_sales_person_default = trim((string)$edit_voucher['sales_person']);
    }
}
$ap_fixing_type = (!empty($edit_voucher) && is_array($edit_voucher))
    ? trim((string)($edit_voucher['fixing_type'] ?? 'Standard'))
    : 'Standard';
if ($ap_fixing_type !== 'Standard' && $ap_fixing_type !== 'Hedging') {
    $ap_fixing_type = 'Standard';
}

$ap_sales_person_options = auragold_sales_person_user_display_names($conn_master);
$ap_sp_in_list = false;
if ($ap_sales_person_default !== '') {
    foreach ($ap_sales_person_options as $__ap_sp) {
        if (strcasecmp($ap_sales_person_default, $__ap_sp) === 0) {
            $ap_sp_in_list = true;
            break;
        }
    }
}

// Get list of saved vouchers for dropdown
$saved_vouchers = getList("SELECT id, voucher_no, customer_name, voucher_date, total_amount FROM tbl_advance_payments ORDER BY id DESC LIMIT 50");
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Advance Payment - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?> Software</title>
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
    .advance-payment-page .pv-billing-card.card {
        border: 1px solid #d4af37;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
        margin-bottom: 1rem;
        background: #fff;
    }
    .advance-payment-page .pv-billing-card .card-body {
        padding: 1rem 1.25rem;
    }
    .advance-payment-page .billing-form .form-group {
        margin-bottom: 3px;
        display: flex;
        align-items: center;
    }
    .advance-payment-page .billing-form label {
        font-weight: 600;
        font-size: 11px;
        margin-bottom: 0;
        margin-right: 3px;
        color: #000000;
        letter-spacing: 0.01em;
        flex-shrink: 0;
        text-transform: uppercase;
    }
    .advance-payment-page .billing-form .form-control,
    .advance-payment-page .billing-form .form-control-sm {
        font-size: 0.8rem;
        padding: 0.5rem 0.75rem;
        height: calc(1.5em + 1rem + 2px);
        border-radius: 6px;
        transition: all 0.2s ease;
        background: #ffffff;
        flex: 1;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
    }
    .advance-payment-page .billing-form .form-control:focus,
    .advance-payment-page .billing-form .form-control-sm:focus {
        outline: none;
        background: #ffffff;
    }
    .advance-payment-page .billing-form .form-control-sm {
        font-size: 0.75rem;
        padding: 0.35rem 0.5rem;
        height: calc(1.5em + 0.7rem + 2px);
        border-radius: 4px;
    }
    .advance-payment-page .billing-form .form-group > div:not(.input-group):not(.d-flex) {
        flex: 1;
    }
    .advance-payment-page .billing-form .input-group {
        flex: 1;
    }
    .advance-payment-page .billing-form .form-group .d-flex {
        flex: 1;
    }
    .advance-payment-page .billing-form select.form-control,
    .advance-payment-page .billing-form select.form-control-sm {
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
        width: 120px;
        min-width: 100px;
        max-width: 200px;
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
    
    /* Previous Balance section */
    .card h6 {
        font-size: 0.8rem;
        font-weight: 600;
        color: #1e293b;
        margin-bottom: 8px;
    }
    .pv-ap-prev-balance .previous-balance-panel .d-flex.gap-2.mt-2 { display: none !important; }
    
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
                    <div class="container-fluid flex-grow-1 advance-payment-page" style="padding-top: 0; padding-bottom: 0;">
                        <?php include 'sidebar.php';?>

                        <div class="row" style="margin-left: 0; margin-right: 0;">
                            <!-- Main Content Area -->
                            <div class="col-lg-9">
                                <!-- Page Header -->
                                <div class="card mb-1" style="background: #11294b; color: #fff; border: none; margin-left: 0; margin-right: 0;">
                                    <div class="card-body" style="padding: 6px 12px;">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <div>
                                                <h5 style="margin: 0; font-weight: 600; font-size: 0.9rem;">Advance Payment No: <span id="voucherNoDisplay"><?php echo htmlspecialchars($next_voucher_no); ?></span></h5>
                                            </div>
                                            <div style="display: flex; gap: 6px;">
                                                <button type="button" class="btn btn-sm" onclick="resetVoucher()" style="background: rgba(255,255,255,0.2); border: none; color: #fff; padding: 0.25rem 0.5rem; font-size: 0.7rem;">New +</button>
                                                <button type="button" class="btn btn-sm" onclick="saveVoucher()" style="background: rgba(255,255,255,0.2); border: none; color: #fff; padding: 0.25rem 0.5rem; font-size: 0.7rem;">Save</button>
                                                <?php if ($edit_voucher_id > 0): ?>
                                                <a href="advance-payment-print.php?id=<?php echo (int)$edit_voucher_id; ?>" target="_blank" rel="noopener" class="btn btn-sm" style="background: rgba(255,255,255,0.2); border: none; color: #fff; padding: 0.25rem 0.5rem; font-size: 0.7rem;" title="Print"><i class="feather icon-printer" style="font-size: 0.75rem;"></i> Print</a>
                                                <?php endif; ?>
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
                                                    <label>Search Advance Payment</label>
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
                                                    <div style="display:flex;align-items:stretch;gap:4px;"><div class="auragold-party-select2-wrap"><select class="form-control form-control-sm" id="customerId" name="customer_id" required><option value="">Select customer...</option></select><input type="hidden" id="customerName" name="customer_name" value=""><input type="hidden" id="customerBillingState" name="customer_billing_state" value=""></div><button type="button" class="btn btn-sm btn-outline-secondary p-0" id="addCustomerBtn" title="Add / Edit Customer" style="width:32px;min-width:32px;line-height:1;align-self:stretch;"><i class="feather icon-plus"></i></button></div>
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
                                                    <input type="date" class="form-control form-control-sm" id="voucherDate" value="<?php echo htmlspecialchars($ap_voucher_date_val); ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label>Due Date</label>
                                                    <input type="date" class="form-control form-control-sm" id="dueDate" value="<?php echo htmlspecialchars($ap_due_date_val); ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label>Sales Person</label>
                                                    <select class="form-control form-control-sm" id="salesPerson" data-placeholder="Sales Person">
                                                        <option value="">Select</option>
                                                        <?php foreach ($ap_sales_person_options as $sp_name): ?>
                                                        <option value="<?php echo htmlspecialchars($sp_name, ENT_QUOTES, 'UTF-8'); ?>"<?php echo ($ap_sales_person_default !== '' && strcasecmp($ap_sales_person_default, $sp_name) === 0) ? ' selected' : ''; ?>><?php echo htmlspecialchars($sp_name, ENT_QUOTES, 'UTF-8'); ?></option>
                                                        <?php endforeach; ?>
                                                        <?php if ($ap_sales_person_default !== '' && !$ap_sp_in_list): ?>
                                                        <option value="<?php echo htmlspecialchars($ap_sales_person_default, ENT_QUOTES, 'UTF-8'); ?>" selected><?php echo htmlspecialchars($ap_sales_person_default, ENT_QUOTES, 'UTF-8'); ?></option>
                                                        <?php endif; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label>Fixing Type</label>
                                                    <select class="form-control form-control-sm" id="fixingType">
                                                        <option value="Standard"<?php echo ($ap_fixing_type === 'Standard') ? ' selected' : ''; ?>>Standard</option>
                                                        <option value="Hedging"<?php echo ($ap_fixing_type === 'Hedging') ? ' selected' : ''; ?>>Hedging</option>
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
                                        <ul class="nav nav-tabs" style="border-bottom: 2px solid #e2e8f0; margin-bottom: 6px;">
                                            <li class="nav-item">
                                                <a class="nav-link active" data-toggle="tab" href="#receiptTab">Receipt</a>
                                            </li>
                                        </ul>
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
                                                    <table class="table table-bordered table-sm" id="receiptTable" style="margin-bottom: 0;">
                                                        <thead>
                                                            <tr>
                                                                <th data-column="payment-type"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Payment Type</th>
                                                                <th data-column="deposit-into"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Deposit Into</th>
                                                                <th data-column="transaction-no"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Transaction No.</th>
                                                                <th data-column="cheque-dt"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Cheque Dt.</th>
                                                                <th data-column="purity-carat"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Purity / Carat</th>
                                                                <th data-column="amount"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Amount</th>
                                                                <th data-column="diamond-category"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Diamond Categ...</th>
                                                                <th data-column="quantity"><span class="pv-col-drag-h" title="Drag to reorder"><i class="feather icon-move"></i></span>Quantity</th>
                                                                <th data-column="actions" style="width: 80px;">Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="receiptTableBody">
                                                            <tr class="no-payment-row">
                                                                <td colspan="9" class="text-center text-muted py-2" style="font-size: 0.75rem;">No payment entries</td>
                                                            </tr>
                                                        </tbody>
                                                        <tfoot id="receiptTableFooter" style="display: none;">
                                                            <tr style="background: #f8fafc; font-weight: 600;">
                                                                <td data-column="payment-type"></td>
                                                                <td data-column="deposit-into"></td>
                                                                <td data-column="transaction-no"></td>
                                                                <td data-column="cheque-dt"></td>
                                                                <td data-column="purity-carat"></td>
                                                                <td data-column="amount" style="text-align: right; color: #11294b; font-weight: 700;">Total: <span id="receiptTotalAmount">0.00</span></td>
                                                                <td data-column="diamond-category"></td>
                                                                <td data-column="quantity" style="text-align: right; color: #11294b;"><span id="receiptTotalQuantity">0.00</span></td>
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
                                            <h6 style="margin: 0; font-weight: 600; font-size: 0.8rem;">Payment List</h6>
                                            <div style="display: flex; gap: 6px;">
                                                <button type="button" class="btn btn-sm" style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 0.25rem 0.5rem; font-size: 0.7rem;">
                                                    <i class="feather icon-filter" style="font-size: 0.8rem;"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm" style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 0.25rem 0.5rem; font-size: 0.7rem;">Export</button>
                                            </div>
                                        </div>
                                        <div style="overflow-x: auto;">
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
                                                        <th data-column="actions">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="paymentListTableBody">
                                                    <tr class="no-rows">
                                                        <td colspan="18" class="text-center text-muted py-4">No Rows To Show</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Sidebar -->
                            <div class="col-lg-3">
                                <!-- Previous Balance (shared: includes/previous-balance-panel.php + js/previous-balance-common.js) -->
                                <div class="card mb-1" style="margin-left: 0; margin-right: 0;">
                                    <div class="card-body pv-ap-prev-balance" style="padding: 8px 12px;">
                                        <?php
                                        $pb_show_use_amount_row = false;
                                        include __DIR__ . '/includes/previous-balance-panel.php';
                                        ?>
                                    </div>
                                </div>
                                
                                <!-- Transaction History Section -->
                                <div class="card mb-1" style="margin-left: 0; margin-right: 0;">
                                    <div class="card-body" style="padding: 8px 12px;">
                                        <h6 style="margin-bottom: 8px; font-weight: 600; color: #1e293b; font-size: 0.8rem; border-bottom: 2px solid #fbbf24; padding-bottom: 4px;">Transaction History</h6>
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

    <!-- Metal Exchange Payment Modal -->
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
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Metal</label>
                                <select class="form-control" id="metalExchangeMetal">
                                    <option value="">Select Metal</option>
                                    <?php foreach($metals as $metal): ?>
                                    <option value="<?php echo $metal['id']; ?>"><?php echo htmlspecialchars($metal['display_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Product</label>
                                <select class="form-control" id="metalExchangeProduct">
                                    <option value="">Select Product</option>
                                    <?php foreach($products as $product): ?>
                                    <option value="<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Quantity</label>
                                <input type="text" class="form-control" id="metalExchangeQty" value="1" step="0.01" onchange="calculateMetalExchange()">
                            </div>
                            <div class="form-group">
                                <label>Purity / Carat</label>
                                <input type="text" class="form-control" id="metalExchangePurity" placeholder="Purity / Carat" onchange="calculateMetalExchange()">
                            </div>
                            <div class="form-group">
                                <label>Weight</label>
                                <input type="text" class="form-control" id="metalExchangeWeight" value="0" step="0.001" onchange="calculateMetalExchange()">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Purity Wt.</label>
                                <input type="text" class="form-control" id="metalExchangePurityWt" value="0" step="0.001" readonly style="background: #f8fafc;">
                            </div>
                            <div class="form-group">
                                <label>Amount</label>
                                <input type="text" class="form-control" id="metalExchangeAmount" value="0.00" step="0.01" readonly style="background: #f8fafc;">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" style="border: 1px solid #ec4899; color: #ec4899; background: #fff;" data-dismiss="modal">Clear</button>
                    <button type="button" class="btn" style="background: #11294b; color: #fff; border: none;" onclick="saveReceiptPayment('metal-exchange')">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scrap Payment Modal -->
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
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Product</label>
                                <select class="form-control" id="scrapProduct">
                                    <option value="">Select Product</option>
                                    <?php foreach($products as $product): ?>
                                    <option value="<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Quantity</label>
                                <input type="text" class="form-control" id="scrapQty" value="1" step="0.01" onchange="calculateScrap()">
                            </div>
                            <div class="form-group">
                                <label>Weight</label>
                                <input type="text" class="form-control" id="scrapWeight" value="0" step="0.001" onchange="calculateScrap()">
                            </div>
                            <div class="form-group">
                                <label>Purity / Carat</label>
                                <input type="text" class="form-control" id="scrapPurity" placeholder="Purity / Carat" onchange="calculateScrap()">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Purity Wt.</label>
                                <input type="text" class="form-control" id="scrapPurityWt" value="0" step="0.001" readonly style="background: #f8fafc;">
                            </div>
                            <div class="form-group">
                                <label>Amount</label>
                                <input type="text" class="form-control" id="scrapAmount" value="0.00" step="0.01" readonly style="background: #f8fafc;">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" style="border: 1px solid #ec4899; color: #ec4899; background: #fff;" data-dismiss="modal">Clear</button>
                    <button type="button" class="btn" style="background: #11294b; color: #fff; border: none;" onclick="saveReceiptPayment('scrap')">Save</button>
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
    <script>
    window.PB_PAGE_CONFIG = {
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
        onAfterLoad: function () {},
        onAfterLoadAlways: function () {
            var el = document.getElementById('previousBalancePanelLoader');
            if (el) {
                el.classList.remove('pb-is-loading');
                el.setAttribute('aria-hidden', 'true');
            }
        },
        onAfterClear: function () {},
        skipIfEditMode: false,
        skipAutoLoad: function () { return false; }
    };
    </script>
    <script src="js/previous-balance-common.js"></script>
    
    <script>
    // Master data for dropdowns
    const nationalities = <?php 
        $nationalities_js = getList("SELECT id, name FROM tbl_nationalities WHERE status = 1 ORDER BY name ASC");
        echo json_encode($nationalities_js ?: []); 
    ?>;
    
    let receiptRowIndex = 0;
    let currentEditingReceiptRowId = null;
    let currentPaymentType = null;

    const receiptColumnDefinitions = [
        { key: 'payment-type', label: 'Payment Type' },
        { key: 'deposit-into', label: 'Deposit Into' },
        { key: 'transaction-no', label: 'Transaction No.' },
        { key: 'cheque-dt', label: 'Cheque Dt.' },
        { key: 'purity-carat', label: 'Purity / Carat' },
        { key: 'amount', label: 'Amount' },
        { key: 'diamond-category', label: 'Diamond Category' },
        { key: 'quantity', label: 'Quantity' },
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
    const PV_PAGE_NAME = 'advance-payment';
    const PV_TAB_RECEIPT = 'receipt';
    const PV_TAB_PAYMENT_LIST = 'payment_list';
    const PV_DEFAULT_WIDTHS_RECEIPT = {
        'payment-type': 108, 'deposit-into': 132, 'transaction-no': 124, 'cheque-dt': 100, 'purity-carat': 120,
        'amount': 104, 'diamond-category': 128, 'quantity': 96, 'actions': 120
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
    function getReceiptColumnPreferences() {
        var defaults = {};
        receiptColumnDefinitions.forEach(function (col) { defaults[col.key] = true; });
        return defaults;
    }
    function applyReceiptColumnVisibility() {
        var columnPrefs = getReceiptColumnPreferences();
        var emptyRow = document.querySelector('#receiptTableBody .no-payment-row');
        if (emptyRow) {
            var visibleColumns = receiptColumnDefinitions.filter(function (col) { return columnPrefs[col.key] !== false; }).length;
            var td0 = emptyRow.querySelector('td');
            if (td0) td0.setAttribute('colspan', visibleColumns);
        }
        document.querySelectorAll('#receiptTableFooter td[data-column]').forEach(function (td) {
            var k = td.getAttribute('data-column');
            if (!k) return;
            td.style.display = columnPrefs[k] !== false ? '' : 'none';
        });
    }
    function apRvTdText(row, key) {
        var td = row.querySelector('td[data-column="' + key + '"]');
        if (!td) return '';
        return (td.textContent || '').replace(/\s+/g, ' ').trim();
    }

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

    $(document).ready(function () {
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
                } else if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    const bsModal = new bootstrap.Modal(modal);
                    bsModal.show();
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
            if (amount < 0 || !isFinite(amount)) {
                alert('Please enter a valid amount');
                return;
            }
            paymentData = {
                payment_type: 'Cash',
                deposit_into: document.getElementById('cashDepositInto').value,
                transaction_no: '',
                cheque_date: '',
                amount: amount,
                diamond_category: '',
                quantity: 0,
                purity_carat: ''
            };
        } else if (type === 'bank') {
            const amount = parseFloat(document.getElementById('bankAmount').value || 0);
            if (amount < 0 || !isFinite(amount)) {
                alert('Please enter a valid amount');
                return;
            }
            paymentData = {
                payment_type: 'Bank',
                deposit_into: document.getElementById('bankDepositInto').value,
                transaction_no: document.getElementById('bankTransNo').value,
                cheque_date: '',
                amount: amount,
                diamond_category: '',
                quantity: 0,
                purity_carat: ''
            };
        } else if (type === 'cheque') {
            const amount = parseFloat(document.getElementById('chequeAmount').value || 0);
            if (amount < 0 || !isFinite(amount)) {
                alert('Please enter a valid amount');
                return;
            }
            paymentData = {
                payment_type: 'Cheque',
                deposit_into: document.getElementById('chequeDepositInto').value,
                transaction_no: document.getElementById('chequeTransNo').value,
                cheque_date: document.getElementById('chequeDate').value,
                amount: amount,
                diamond_category: '',
                quantity: 0,
                purity_carat: ''
            };
        } else if (type === 'upi') {
            const amount = parseFloat(document.getElementById('upiAmount').value || 0);
            if (amount < 0 || !isFinite(amount)) {
                alert('Please enter a valid amount');
                return;
            }
            paymentData = {
                payment_type: 'UPI',
                deposit_into: document.getElementById('upiDepositInto').value,
                transaction_no: document.getElementById('upiTransNo').value,
                cheque_date: '',
                amount: amount,
                diamond_category: '',
                quantity: 0,
                purity_carat: ''
            };
        } else if (type === 'card') {
            const amount = parseFloat(document.getElementById('cardAmount').value || 0);
            if (amount < 0 || !isFinite(amount)) {
                alert('Please enter a valid amount');
                return;
            }
            paymentData = {
                payment_type: 'Card',
                deposit_into: document.getElementById('cardDepositInto').value,
                transaction_no: document.getElementById('cardTransNo').value,
                cheque_date: '',
                amount: amount,
                diamond_category: '',
                quantity: 0,
                purity_carat: ''
            };
        } else if (type === 'metal-exchange') {
            const amount = parseFloat(document.getElementById('metalExchangeAmount').value || 0);
            const quantity = parseFloat(document.getElementById('metalExchangeQty').value || 0);
            paymentData = {
                payment_type: 'Metal',
                deposit_into: '',
                transaction_no: '',
                cheque_date: '',
                amount: amount,
                diamond_category: '',
                quantity: quantity,
                purity_carat: document.getElementById('metalExchangePurity').value
            };
        } else if (type === 'scrap') {
            const amount = parseFloat(document.getElementById('scrapAmount').value || 0);
            const quantity = parseFloat(document.getElementById('scrapQty').value || 0);
            paymentData = {
                payment_type: 'Scrap',
                deposit_into: '',
                transaction_no: '',
                cheque_date: '',
                amount: amount,
                diamond_category: '',
                quantity: quantity,
                purity_carat: document.getElementById('scrapPurity').value
            };
        }

        // Check if editing existing payment
        if (window.currentEditingPaymentId) {
            // Delete old row
            const oldRow = document.getElementById(window.currentEditingPaymentId);
            if (oldRow) {
                oldRow.remove();
            }
            window.currentEditingPaymentId = null;
        }

        // Add row to receipt table
        addReceiptRowFromPayment(paymentData);
        
        clearPaymentModal(type);
        if (typeof window.auragoldClosePaymentModal === 'function') {
            window.auragoldClosePaymentModal(type);
        } else {
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
            if (modalId && typeof $ !== 'undefined' && $.fn.modal) {
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
            <td data-column="deposit-into">${paymentData.deposit_into || ''}</td>
            <td data-column="transaction-no">${paymentData.transaction_no || ''}</td>
            <td data-column="cheque-dt">${paymentData.cheque_date || ''}</td>
            <td data-column="purity-carat">${paymentData.purity_carat || ''}</td>
            <td data-column="amount" style="text-align: right; font-weight: 600;"><span data-payment-amount>${parseFloat(paymentData.amount || 0).toFixed(2)}</span></td>
            <td data-column="diamond-category">${paymentData.diamond_category || ''}</td>
            <td data-column="quantity" style="text-align: right;">${parseFloat(paymentData.quantity || 0).toFixed(2)}</td>
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
        
        tbody.appendChild(row);
        var ordKeys = typeof pvGetColumnOrderFromThead === 'function' ? pvGetColumnOrderFromThead(document.querySelector('#receiptTable thead tr')) : null;
        if (ordKeys && ordKeys.length && typeof pvReorderRowCells === 'function') pvReorderRowCells(row, ordKeys);
        
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
                    tbody.innerHTML = '<tr class="no-payment-row"><td colspan="9" class="text-center text-muted py-3">No payment entries</td></tr>';
                    const footer = document.getElementById('receiptTableFooter');
                    if (footer) footer.style.display = 'none';
                    if (typeof applyReceiptColumnVisibility === 'function') applyReceiptColumnVisibility();
                }
                updateReceiptTotal();
            }
        }
    }
    
    // Edit receipt payment
    function editReceiptPayment(paymentId) {
        const row = document.getElementById(paymentId);
        if (!row) return;
        
        const paymentType = apRvTdText(row, 'payment-type');
        let type = 'cash';
        if (paymentType === 'Bank') type = 'bank';
        else if (paymentType === 'Cheque') type = 'cheque';
        else if (paymentType === 'UPI') type = 'upi';
        else if (paymentType === 'Card') type = 'card';
        else if (paymentType === 'M. Exch.') type = 'metal-exchange';
        else if (paymentType === 'Scrap') type = 'scrap';
        
        // Store editing payment ID
        window.currentEditingPaymentId = paymentId;
        
        // Populate modal with existing data
        const depositInto = apRvTdText(row, 'deposit-into');
        const transactionNo = apRvTdText(row, 'transaction-no');
        const chequeDate = apRvTdText(row, 'cheque-dt');
        const purityCarat = apRvTdText(row, 'purity-carat');
        const amountEl = row.querySelector('[data-payment-amount]');
        const amount = parseFloat(amountEl ? amountEl.textContent.replace(/,/g, '') : 0);
        const diamondCategory = apRvTdText(row, 'diamond-category');
        const quantity = parseFloat(apRvTdText(row, 'quantity').replace(/,/g, '') || 0);
        
        // Populate modal fields based on type
        if (type === 'cash') {
            $('#cashDepositInto').val(depositInto);
            $('#cashAmount').val(amount.toFixed(2));
        } else if (type === 'bank') {
            $('#bankDepositInto').val(depositInto);
            $('#bankTransNo').val(transactionNo);
            $('#bankAmount').val(amount.toFixed(2));
        } else if (type === 'cheque') {
            $('#chequeDepositInto').val(depositInto);
            $('#chequeTransNo').val(transactionNo);
            $('#chequeAmount').val(amount.toFixed(2));
            $('#chequeDate').val(chequeDate);
        } else if (type === 'upi') {
            $('#upiDepositInto').val(depositInto);
            $('#upiTransNo').val(transactionNo);
            $('#upiAmount').val(amount.toFixed(2));
        } else if (type === 'card') {
            $('#cardDepositInto').val(depositInto);
            $('#cardTransNo').val(transactionNo);
            $('#cardAmount').val(amount.toFixed(2));
        } else if (type === 'metal-exchange') {
            $('#metalExchangePurity').val(purityCarat);
            $('#metalExchangeQty').val(quantity.toFixed(2));
            $('#metalExchangeAmount').val(amount.toFixed(2));
        } else if (type === 'scrap') {
            $('#scrapPurity').val(purityCarat);
            $('#scrapQty').val(quantity.toFixed(2));
            $('#scrapAmount').val(amount.toFixed(2));
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
            document.getElementById('metalExchangeMetal').value = '';
            document.getElementById('metalExchangeProduct').value = '';
            document.getElementById('metalExchangeQty').value = '1';
            document.getElementById('metalExchangePurity').value = '';
            document.getElementById('metalExchangeWeight').value = '0';
            document.getElementById('metalExchangePurityWt').value = '0';
            document.getElementById('metalExchangeAmount').value = '0.00';
        } else if (type === 'scrap') {
            document.getElementById('scrapProduct').value = '';
            document.getElementById('scrapQty').value = '1';
            document.getElementById('scrapWeight').value = '0';
            document.getElementById('scrapPurity').value = '';
            document.getElementById('scrapPurityWt').value = '0';
            document.getElementById('scrapAmount').value = '0.00';
        }
        window.currentEditingPaymentId = null;
    }

    // Calculate metal exchange
    function calculateMetalExchange() {
        const weight = parseFloat($('#metalExchangeWeight').val() || 0);
        const purityCarat = $('#metalExchangePurity').val();
        
        let purityWt = 0;
        if (purityCarat) {
            const purity = parseFloat(purityCarat.replace(/[^0-9.]/g, '')) || 0;
            if (purity > 0 && purity <= 100) {
                purityWt = (weight * purity / 100).toFixed(3);
            } else if (purity > 100) {
                purityWt = (weight * purity / 100).toFixed(3);
            }
        }
        $('#metalExchangePurityWt').val(purityWt);
        
        // Calculate amount (you may need to add rate calculation)
        $('#metalExchangeAmount').val(parseFloat(purityWt || 0).toFixed(2));
    }

    // Calculate scrap
    function calculateScrap() {
        const weight = parseFloat($('#scrapWeight').val() || 0);
        const purityCarat = $('#scrapPurity').val();
        
        let purityWt = 0;
        if (purityCarat) {
            const purity = parseFloat(purityCarat.replace(/[^0-9.]/g, '')) || 0;
            if (purity > 0 && purity <= 100) {
                purityWt = (weight * purity / 100).toFixed(3);
            } else if (purity > 100) {
                purityWt = (weight * purity / 100).toFixed(3);
            }
        }
        $('#scrapPurityWt').val(purityWt);
        
        // Calculate amount (you may need to add rate calculation)
        $('#scrapAmount').val(parseFloat(purityWt || 0).toFixed(2));
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
            const $t = $(this);
            if ($t.hasClass('add-new-customer-option')) {
                const q = ($('#customerName').val() || '').trim();
                if (q.length >= 2) {
                    openNewCustomerModal(q);
                }
                return;
            }
            const customerId = $t.data('customerId');
            const customerName = $t.data('customerName');
            if (customerId == null || customerName == null) return;
            $('#customerName').val(customerName);
            $('#customerId').val(customerId);
            $('#customerSuggestions').hide();
            loadCustomerBalance();
        });

        // Keyboard navigation, Enter: pick row, add-new, or open new-customer with typed name
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
                    openNewCustomerModal(customerNameValue);
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
                    const termH = escapeHtml(term);
                    let html = '<div class="customer-suggestion-item add-new-customer-option" style="padding: 10px 12px; cursor: pointer; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); margin: 0.25rem;" onmouseover="this.style.background=\'#f1f5f9\'" onmouseout="this.style.background=\'#f8fafc\'"><i class="feather icon-user-plus" style="color: #c5a864; margin-right: 8px; vertical-align: middle;"></i><span style="font-weight: 600; color: #c5a864;">Add New Customer: &ldquo;' + termH + '&rdquo;</span></div>';
                    $('#customerSuggestions').html(html).show();
                }
            },
            error: function() {
                $('#customerSuggestions').hide().empty();
            }
        });
    }
    
    // Open new customer modal and pre-fill the name
    function openNewCustomerModal(customerName) {
        $('#customerSuggestions').hide();
        // Pre-fill the name in the modal
        if (customerName && document.getElementById('ledgerName')) {
            document.getElementById('ledgerName').value = customerName;
            // Trigger the handleNameInput to populate First Name and Last Name
            handleNameInput(document.getElementById('ledgerName'));
        }
        $('#customerCreationModal').modal('show');
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
    });

    /** Read previous balance from shared panel (spans + data-original-*), same idea as payment-voucher.php */
    function getAdvancePaymentPreviousBalanceForSave() {
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
            previous_silver: numFromSpan('previousBalanceSilver', 'data-original-silver', 3, '0.000')
        };
    }

    function loadCustomerBalance() {
        const customerId = $('#customerId').val();
        const customerName = $('#customerName').val();
        if (!customerId && !customerName) {
            if (typeof PrevBalanceUI !== 'undefined' && PrevBalanceUI.clearPanel) {
                PrevBalanceUI.clearPanel();
            }
            var _pbAp = document.getElementById('previousBalancePanelLoader');
            if (_pbAp) {
                _pbAp.classList.remove('pb-is-loading');
                _pbAp.setAttribute('aria-hidden', 'true');
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
        addReceiptRow();
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
            const amtEl = row.querySelector('[data-payment-amount]');
            const amt = parseFloat(amtEl ? amtEl.textContent.replace(/,/g, '') : 0);
            const qtyTd = row.querySelector('td[data-column="quantity"]');
            const qty = parseFloat(qtyTd ? qtyTd.textContent.replace(/,/g, '') : 0);
            totalAmount += amt;
            totalQuantity += qty;
        });
        
        const footer = document.getElementById('receiptTableFooter');
        if (footer) {
            var ta = document.getElementById('receiptTotalAmount');
            var tq = document.getElementById('receiptTotalQuantity');
            if (ta) ta.textContent = totalAmount.toFixed(2);
            if (tq) tq.textContent = totalQuantity.toFixed(2);
        }
    }

    function resetVoucher() {
        if (confirm('Are you sure you want to reset the form? All unsaved data will be lost.')) {
            window.location.href = 'advance-payment.php';
        }
    }

    /** After save, stay on a fresh new voucher (same page, not transaction report). */
    function buildTransactionReportUrlAfterVoucherSave() {
        return 'advance-payment.php';
    }

    function saveVoucher() {
        const _pbSave = getAdvancePaymentPreviousBalanceForSave();
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
            previous_balance: _pbSave.previous_balance,
            previous_gold: _pbSave.previous_gold,
            previous_silver: _pbSave.previous_silver,
            comment: $('#comment').val(),
            items: []
        };

// Collect receipt items
        $('#receiptTableBody tr').each(function() {
            if (!$(this).hasClass('no-payment-row') && !$(this).hasClass('no-rows')) {
                const row = this;
                const paymentTypeText = apRvTdText(row, 'payment-type');
                let paymentType = 'Cash';
                if (paymentTypeText === 'Bank') paymentType = 'Bank';
                else if (paymentTypeText === 'Cheque') paymentType = 'Cheque';
                else if (paymentTypeText === 'UPI') paymentType = 'UPI';
                else if (paymentTypeText === 'Card') paymentType = 'Card';
                else if (paymentTypeText === 'M. Exch.') paymentType = 'Metal';
                else if (paymentTypeText === 'Scrap') paymentType = 'Scrap';
                
                const amountEl = row.querySelector('[data-payment-amount]');
                const amountText = amountEl ? amountEl.textContent.replace(/,/g, '') : '0';
                const amount = parseFloat(amountText) || 0;
                
                const item = {
                    payment_type: paymentType,
                    diamond_category: apRvTdText(row, 'diamond-category'),
                    transaction_no: apRvTdText(row, 'transaction-no'),
                    deposit_into: apRvTdText(row, 'deposit-into'),
                    product_id: '',
                    cheque_date: apRvTdText(row, 'cheque-dt'),
                    weight: 0,
                    metal_id: '',
                    quantity: parseFloat(apRvTdText(row, 'quantity').replace(/,/g, '') || 0),
                    purity_carat: apRvTdText(row, 'purity-carat'),
                    purity_wt: amount,
                    amount: amount
                };
                voucherData.items.push(item);
            }
        });

        // Calculate totals
        let totalAmount = 0;
        let totalGold = 0;
        let totalSilver = 0;
        voucherData.items.forEach(function(item) {
            if (item.payment_type === 'Metal') {
                const metalId = parseInt(item.metal_id);
                // Assuming metal ID 1 is Gold, 2 is Silver (adjust based on your data)
                if (metalId === 1) {
                    totalGold += parseFloat(item.purity_wt || 0);
                } else if (metalId === 2) {
                    totalSilver += parseFloat(item.purity_wt || 0);
                }
            } else {
                totalAmount += parseFloat(item.purity_wt || 0);
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
            url: 'ajax/save-advance-payment.php',
            method: 'POST',
            data: voucherData,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    if (typeof window.auragoldVoucherDiamondStoneOnSaveSuccess === 'function') {
                        window.auragoldVoucherDiamondStoneOnSaveSuccess(response.voucher_id);
                    }
                    const voucherId = parseInt(response.voucher_id || 0, 10) || 0;
                    const nextUrl = buildTransactionReportUrlAfterVoucherSave();
                    if (voucherId > 0 && typeof window.showPrintAdvancePaymentModal === 'function') {
                        window.pendingAdvancePaymentRedirectUrl = nextUrl;
                        setTimeout(function() {
                            window.showPrintAdvancePaymentModal(voucherId);
                        }, 100);
                    } else {
                        alert(response.message || 'Voucher saved successfully!');
                        window.location.href = nextUrl;
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
    $ap_edit_di = auragold_voucher_list_diamond_issue_rows_for_kind($conn, 'advance_payment', (int) ($edit_voucher['id'] ?? 0));
    $ap_edit_si = auragold_voucher_list_stone_issue_rows_for_kind($conn, 'advance_payment', (int) ($edit_voucher['id'] ?? 0));
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
            if (typeof formatSalePreviousBalanceAmount === 'function') {
                formatSalePreviousBalanceAmount(document.getElementById('previousBalanceAmount'), parseFloat('<?php echo (float)($edit_voucher['previous_balance'] ?? 0); ?>'));
                formatSalePreviousBalanceMetal(document.getElementById('previousBalanceGold'), parseFloat('<?php echo (float)($edit_voucher['previous_gold'] ?? 0); ?>'), 3, 'data-original-gold');
                formatSalePreviousBalanceMetal(document.getElementById('previousBalanceSilver'), parseFloat('<?php echo (float)($edit_voucher['previous_silver'] ?? 0); ?>'), 3, 'data-original-silver');
                formatSalePreviousBalanceMetal(document.getElementById('previousBalanceDiamond'), parseFloat('0'), 3, 'data-original-diamond');
                formatSalePreviousBalanceMetal(document.getElementById('previousBalanceGemstone'), parseFloat('0'), 3, 'data-original-gemstone');
            }
        }
        $('#comment').val('<?php echo htmlspecialchars($edit_voucher['comment'] ?? ''); ?>');

        if (typeof window.auragoldVoucherDiamondStonePopulateFromOrder === 'function') {
            window.auragoldVoucherDiamondStonePopulateFromOrder({
                id: <?php echo (int) ($edit_voucher['id'] ?? 0); ?>,
                diamond_issues: <?php echo json_encode($ap_edit_di ?: [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
                stone_issues: <?php echo json_encode($ap_edit_si ?: [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
            });
        }

        // Load receipt items (same row layout as addReceiptRowFromPayment)
        <?php foreach ($edit_items as $item):
            $cd = '';
            if (!empty($item['cheque_date'])) {
                $cd = substr((string)$item['cheque_date'], 0, 10);
            }
            $pd = [
                'payment_type' => $item['payment_type'] ?? 'Cash',
                'deposit_into' => $item['deposit_into'] ?? '',
                'transaction_no' => $item['transaction_no'] ?? '',
                'cheque_date' => $cd,
                'amount' => (float)($item['amount'] ?? 0),
                'diamond_category' => $item['diamond_category'] ?? '',
                'quantity' => (float)($item['quantity'] ?? 0),
                'purity_carat' => $item['purity_carat'] ?? '',
            ];
        ?>
        addReceiptRowFromPayment(<?php echo json_encode($pd); ?>);
        <?php endforeach; ?>
        updateReceiptTotal();
    });
    <?php endif; ?>
    
    // Load voucher into Payment List table
    function loadVoucherIntoPaymentList(voucherId) {
        $.ajax({
            url: 'ajax/get-advance-payment.php',
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
    function addVoucherToPaymentList(voucher) {
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
        
        // Calculate row number based on existing voucher rows only
        const existingRows = tbody.find('tr[data-voucher-id]').length;
        const rowNum = existingRows + 1;
        const row = `
            <tr data-voucher-id="${voucher.id}">
                <td data-column="sr-no">${rowNum}</td>
                <td data-column="sales-person">${voucher.sales_person || ''}</td>
                <td data-column="voucher-date">${voucher.voucher_date || ''}</td>
                <td data-column="ledger-name">${voucher.customer_name || ''}</td>
                <td data-column="invoice-no">${voucher.voucher_no || ''}</td>
                <td data-column="branch-name"></td>
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
                <td data-column="actions" class="text-nowrap">
                    <a href="advance-payment.php?id=${voucher.id}" class="btn btn-sm btn-outline-primary" title="Edit" style="padding: 0.2rem 0.4rem; font-size: 0.7rem;"><i class="feather icon-edit-2" style="font-size: 0.75rem;"></i></a>
                    <a href="advance-payment-print.php?id=${voucher.id}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" title="Print" style="padding: 0.2rem 0.4rem; font-size: 0.7rem;"><i class="feather icon-printer" style="font-size: 0.75rem;"></i></a>
                    <button type="button" class="btn btn-sm btn-outline-danger" title="Delete" onclick="deleteAdvanceVoucher(${voucher.id}, '${(voucher.voucher_no || '').replace(/'/g, "\\'")}')" style="padding: 0.2rem 0.4rem; font-size: 0.7rem;"><i class="feather icon-trash-2" style="font-size: 0.75rem;"></i></button>
                </td>
            </tr>
        `;
        
        tbody.append(row);
        var plTr = tbody.children('tr[data-voucher-id="' + voucher.id + '"]')[0];
        if (plTr && typeof pvGetColumnOrderFromThead === 'function' && typeof pvReorderRowCells === 'function') {
            var plKeys = pvGetColumnOrderFromThead(document.querySelector('#paymentListTable thead tr'));
            if (plKeys && plKeys.length) pvReorderRowCells(plTr, plKeys);
        }
    }

    function openVoucherForEdit(voucherId) {
        if (voucherId) {
            window.location.href = 'advance-payment.php?id=' + voucherId;
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
                url: 'ajax/search-advance-payments.php',
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
    
    function deleteAdvanceVoucher(id, voucherNo) {
        if (!confirm('Are you sure you want to delete advance voucher ' + (voucherNo || id) + '? This cannot be undone.')) {
            return;
        }
        $.ajax({
            url: 'ajax/delete-advance-payment.php',
            method: 'POST',
            data: { id: id },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message || 'Voucher deleted successfully.');
                    // Reload page to start fresh (same as after Save / New)
                    window.location.replace('advance-payment.php');
                } else {
                    alert(response.message || 'Failed to delete voucher');
                }
            },
            error: function(xhr) {
                var msg = 'Error deleting voucher.';
                try {
                    var r = JSON.parse(xhr.responseText);
                    if (r.message) msg = r.message;
                } catch (e) {}
                alert(msg);
            }
        });
    }
    
    // Load existing vouchers on page load
    $(document).ready(function() {
        // Load all vouchers into Payment List
        $.ajax({
            url: 'ajax/get-advance-payments.php',
            method: 'GET',
            data: { limit: 100 },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success' && response.vouchers && response.vouchers.length > 0) {
                    response.vouchers.forEach(function(voucher) {
                        addVoucherToPaymentList(voucher);
                    });
                } else {
                    // Keep the "No Rows To Show" message if no vouchers
                    const tbody = $('#paymentListTableBody');
                    if (tbody.find('tr[data-voucher-id]').length === 0) {
                        if (tbody.find('.no-rows').length === 0) {
                            tbody.html('<tr class="no-rows"><td colspan="18" class="text-center text-muted py-4">No Rows To Show</td></tr>');
                        }
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading vouchers:', error);
                console.error('Response:', xhr.responseText);
            }
        });
        
        <?php if ($edit_voucher_id > 0): ?>
        // If editing, ensure this voucher is highlighted or shown
        // (It will already be in the list from the above call)
        <?php endif; ?>
    });
    </script>

<!-- Print advance payment (same flow as receipt voucher) -->
<div id="printAdvancePaymentModal" class="print-invoice-modal" style="display: none;">
    <div class="print-invoice-modal-content ap-print-modal-content">
        <button type="button" class="print-invoice-modal-close" onclick="closePrintAdvancePaymentModal()">&times;</button>
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
        <p class="print-invoice-modal-message">Do you want to print advance payment?</p>
        <div class="print-invoice-modal-buttons">
            <button type="button" class="print-invoice-btn-yes" onclick="confirmPrintAdvancePayment()">Print</button>
            <button type="button" class="print-invoice-btn-no" onclick="closePrintAdvancePaymentModal()">No</button>
        </div>
    </div>
</div>

<style>
.ap-print-modal-content { position: relative; }
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
.receipt-lines { padding: 14px 10px 0; }
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
    var savedAdvancePaymentId = null;

    function showPrintAdvancePaymentModal(voucherId) {
        savedAdvancePaymentId = voucherId;
        setTimeout(function() {
            var modal = document.getElementById('printAdvancePaymentModal');
            if (modal) {
                modal.style.display = 'flex';
                modal.style.zIndex = '10000';
            } else if (confirm('Advance payment saved. Do you want to print?')) {
                window.open('advance-payment-print.php?id=' + voucherId, '_blank', 'width=1200,height=800');
                if (window.pendingAdvancePaymentRedirectUrl) {
                    window.location.href = window.pendingAdvancePaymentRedirectUrl;
                    window.pendingAdvancePaymentRedirectUrl = null;
                }
            }
        }, 200);
    }

    function closePrintAdvancePaymentModal() {
        var modal = document.getElementById('printAdvancePaymentModal');
        if (modal) {
            modal.style.display = 'none';
        }
        savedAdvancePaymentId = null;
        if (window.pendingAdvancePaymentRedirectUrl) {
            window.location.href = window.pendingAdvancePaymentRedirectUrl;
            window.pendingAdvancePaymentRedirectUrl = null;
        }
    }

    function confirmPrintAdvancePayment() {
        if (savedAdvancePaymentId) {
            window.open('advance-payment-print.php?id=' + savedAdvancePaymentId, '_blank', 'width=1200,height=800');
        }
        closePrintAdvancePaymentModal();
    }

    document.addEventListener('DOMContentLoaded', function() {
        var modal = document.getElementById('printAdvancePaymentModal');
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closePrintAdvancePaymentModal();
                }
            });
        }
    });

    window.showPrintAdvancePaymentModal = showPrintAdvancePaymentModal;
    window.closePrintAdvancePaymentModal = closePrintAdvancePaymentModal;
    window.confirmPrintAdvancePayment = confirmPrintAdvancePayment;
})();
</script>
</body>
</html>
