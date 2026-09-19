<?php 
session_start();
require_once 'config.php';

// Load master data
$metals = getList("SELECT id, display_name, system_name FROM tbl_metal WHERE status = 1 ORDER BY id ASC");
$branches = getListMaster("SELECT id, name, code FROM tbl_branches WHERE status = 1 ORDER BY name ASC");
$products = getList("SELECT id, name FROM tbl_products WHERE status = 1 ORDER BY name ASC LIMIT 100");

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

// Get next voucher number
$last_voucher = getRecord("SELECT voucher_no FROM tbl_customer_advance_vouchers ORDER BY id DESC LIMIT 1");
$next_voucher_no = 'CA-1';
if ($last_voucher && $last_voucher['voucher_no']) {
    $last_num = (int)str_replace('CA-', '', $last_voucher['voucher_no']);
    $next_voucher_no = 'CA-' . ($last_num + 1);
}

// Load voucher for editing if ID provided
$edit_voucher_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$edit_voucher = null;
$edit_items = [];

if ($edit_voucher_id > 0) {
    $edit_voucher = getRecord("SELECT * FROM tbl_customer_advance_vouchers WHERE id = $edit_voucher_id");
    if ($edit_voucher) {
        $edit_items = getList("SELECT * FROM tbl_customer_advance_voucher_items WHERE voucher_id = $edit_voucher_id");
        $next_voucher_no = $edit_voucher['voucher_no'];
    }
}

// Get list of saved vouchers for dropdown
$saved_vouchers = getList("SELECT id, voucher_no, customer_name, voucher_date, total_amount FROM tbl_customer_advance_vouchers ORDER BY id DESC LIMIT 50");
?>
<!DOCTYPE html>
<html lang="en" class="default-style">
<head>
    <title>Customer Advance Voucher - <?php echo htmlspecialchars(auragold_app_name(), ENT_QUOTES, 'UTF-8'); ?> Software</title>
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
    
    /* Billing form styles */
    .billing-form .form-group {
        margin-bottom: 8px;
    }
    
    .billing-form label {
        font-size: 0.75rem;
        font-weight: 500;
        color: #000000;
        margin-bottom: 3px;
        display: block;
    }
    
    .billing-form .form-control-sm {
        font-size: 0.8rem;
        padding: 0.3rem 0.6rem;
        height: 30px;
        border: 1px solid #e2e8f0;
        border-radius: 3px;
        line-height: 1.4;
    }
    
    .billing-form .form-control-sm:focus {
        border-color: #c5a864;
        box-shadow: 0 0 0 0.15rem rgba(102, 126, 234, 0.25);
    }
    
    /* Table styles */
    .table {
        font-size: 0.75rem;
        margin-bottom: 0;
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
    
    /* Nav tabs */
    .nav-tabs {
        border-bottom: 2px solid #e2e8f0;
        margin-bottom: 8px;
    }
    
    .nav-tabs .nav-link {
        border: none;
        border-bottom: 2px solid transparent;
        color: #64748b;
        padding: 6px 12px;
        font-weight: 500;
        font-size: 0.8rem;
    }
    
    .nav-tabs .nav-link.active {
        color: #11294b;
        border-bottom-color: #11294b;
        background: transparent;
    }
    
    .nav-tabs .nav-link:hover {
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
                    <div class="container-fluid flex-grow-1" style="padding-top: 0; padding-bottom: 0;">
                        <?php include 'sidebar.php';?>

                        <div class="row" style="margin-left: 0; margin-right: 0;">
                            <!-- Main Content Area -->
                            <div class="col-lg-9">
                                <!-- Page Header -->
                                <div class="card mb-1" style="background: #11294b; color: #fff; border: none; margin-left: 0; margin-right: 0;">
                                    <div class="card-body" style="padding: 6px 12px;">
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <div>
                                                <h5 style="margin: 0; font-weight: 600; font-size: 0.9rem;">Customer Advance No: <span id="voucherNoDisplay"><?php echo htmlspecialchars($next_voucher_no); ?></span></h5>
                                            </div>
                                            <div style="display: flex; gap: 6px;">
                                                <button type="button" class="btn btn-sm" onclick="resetVoucher()" style="background: rgba(255,255,255,0.2); border: none; color: #fff; padding: 0.25rem 0.5rem; font-size: 0.7rem;">New +</button>
                                                <button type="button" class="btn btn-sm" onclick="saveVoucher()" style="background: rgba(255,255,255,0.2); border: none; color: #fff; padding: 0.25rem 0.5rem; font-size: 0.7rem;">Save</button>
                                                <button type="button" class="btn btn-sm" style="background: rgba(255,255,255,0.2); border: none; color: #fff; padding: 0.25rem 0.5rem; font-size: 0.7rem;">+ Import</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Voucher Details Form -->
                                <div class="card mb-1" style="margin-left: 0; margin-right: 0;">
                                    <div class="card-body billing-form" style="padding: 8px 12px;">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label>Name *</label>
                                                    <div style="position: relative;">
                                                        <input type="text" class="form-control form-control-sm" id="customerName" placeholder="Enter customer name" required style="padding-right: 35px;" autocomplete="off">
                                                        <input type="hidden" id="customerId" name="customer_id" value="">
                                                        <i class="feather icon-plus add-customer-icon" id="addCustomerBtn" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #c5a864; font-size: 12px; z-index: 10; pointer-events: auto;" title="Add New Customer"></i>
                                                        <div id="customerSuggestions" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 4px; max-height: 300px; overflow-y: auto; z-index: 1000; box-shadow: 0 4px 12px rgba(0,0,0,0.15); margin-top: 2px;"></div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label>Ref No.</label>
                                                    <input type="text" class="form-control form-control-sm" id="refNo" placeholder="Ref No">
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
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label>Sales Person</label>
                                                    <input type="text" class="form-control form-control-sm" id="salesPerson" placeholder="Sales Person" value="<?php echo htmlspecialchars($_SESSION['user_name'] ?? 'SUPER ADMIN'); ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label>Against Of</label>
                                                    <select class="form-control form-control-sm" id="againstOf">
                                                        <option value="">Select</option>
                                                        <option value="Sale Order">Sale Order</option>
                                                        <option value="Sale Invoice">Sale Invoice</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label>Currency</label>
                                                    <select class="form-control form-control-sm" id="currency">
                                                        <option value="AED">AED</option>
                                                        <option value="USD" selected>USD</option>
                                                        <option value="EUR">EUR</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label>Rate</label>
                                                    <input type="number" class="form-control form-control-sm" id="currencyRate" value="1" step="0.01">
                                                </div>
                                            </div>
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label>Date</label>
                                                    <input type="date" class="form-control form-control-sm" id="voucherDate" value="<?php echo date('Y-m-d'); ?>">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-2">
                                                <div class="form-group">
                                                    <label>Due Date</label>
                                                    <input type="date" class="form-control form-control-sm" id="dueDate" value="<?php echo date('Y-m-d'); ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label>Layaways</label>
                                                    <select class="form-control form-control-sm" id="layaways">
                                                        <option value="">Select Layaways</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label>Fixing Type</label>
                                                    <select class="form-control form-control-sm" id="fixingType">
                                                        <option value="Standard" selected>Standard</option>
                                                        <option value="Hedging">Hedging</option>
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
                                                </div>
                                                <div class="table-responsive" style="padding-top: 6px;">
                                                    <table class="table table-bordered table-sm" id="receiptTable" style="margin-bottom: 0;">
                                                        <thead>
                                                            <tr>
                                                                <th>Payment Type</th>
                                                                <th>Deposit Into</th>
                                                                <th>Transaction No.</th>
                                                                <th>Cheque Dt.</th>
                                                                <th>Purity / Carat</th>
                                                                <th>Amount</th>
                                                                <th>Diamond Categ...</th>
                                                                <th>Quantity</th>
                                                                <th style="width: 80px;">Actions</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="receiptTableBody">
                                                            <tr class="no-payment-row">
                                                                <td colspan="9" class="text-center text-muted py-2" style="font-size: 0.75rem;">No payment entries</td>
                                                            </tr>
                                                        </tbody>
                                                        <tfoot id="receiptTableFooter" style="display: none;">
                                                            <tr style="background: #f8fafc; font-weight: 600;">
                                                                <td colspan="5" style="text-align: right; color: #11294b;">Total:</td>
                                                                <td id="receiptTotalAmount" style="text-align: right; color: #11294b; font-weight: 700;">0.00</td>
                                                                <td></td>
                                                                <td id="receiptTotalQuantity" style="text-align: right; color: #11294b;">0.00</td>
                                                                <td></td>
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
                                                        <th>Sr.No.</th>
                                                        <th>Sales Person</th>
                                                        <th>Date</th>
                                                        <th>Ledger Name</th>
                                                        <th>Invoice...</th>
                                                        <th>Branch Name</th>
                                                        <th>Ref No.</th>
                                                        <th>Against Voucher...</th>
                                                        <th>Against Vo...</th>
                                                        <th>Total A...</th>
                                                        <th>Total W...</th>
                                                        <th>Cash</th>
                                                        <th>Bank</th>
                                                        <th>Cheque</th>
                                                        <th>UPI</th>
                                                        <th>Card</th>
                                                        <th>Metal</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="paymentListTableBody">
                                                    <tr class="no-rows">
                                                        <td colspan="17" class="text-center text-muted py-4">No Rows To Show</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Right Sidebar -->
                            <div class="col-lg-3">
                                <!-- Previous Balance Section -->
                                <div class="card mb-1" style="margin-left: 0; margin-right: 0;">
                                    <div class="card-body" style="padding: 8px 12px;">
                                        <h6 style="margin-bottom: 8px; font-weight: 600; color: #1e293b; font-size: 0.8rem; border-bottom: 2px solid #fbbf24; padding-bottom: 4px;">Previous Balance</h6>
                                        <div class="form-group" style="margin-bottom: 6px;">
                                            <label style="font-size: 0.75rem; margin-bottom: 2px;">Amount</label>
                                            <input type="number" class="form-control form-control-sm" id="previousBalanceAmount" value="0.00" step="0.01" readonly style="background: #f8fafc; height: 28px; font-size: 0.75rem;">
                                        </div>
                                        <div class="form-group" style="margin-bottom: 6px;">
                                            <label style="font-size: 0.75rem; margin-bottom: 2px;">Gold</label>
                                            <input type="number" class="form-control form-control-sm" id="previousBalanceGold" value="0.000" step="0.001" readonly style="background: #f8fafc; height: 28px; font-size: 0.75rem;">
                                        </div>
                                        <div class="form-group" style="margin-bottom: 0;">
                                            <label style="font-size: 0.75rem; margin-bottom: 2px;">Silver</label>
                                            <input type="number" class="form-control form-control-sm" id="previousBalanceSilver" value="0.000" step="0.001" readonly style="background: #f8fafc; height: 28px; font-size: 0.75rem;">
                                        </div>
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
                        <input type="number" class="form-control" id="cashAmount" value="0.00" step="0.01">
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
                        <input type="number" class="form-control" id="bankAmount" value="0.00" step="0.01">
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
                        <input type="number" class="form-control" id="chequeAmount" value="0.00" step="0.01">
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
                        <input type="number" class="form-control" id="upiAmount" value="0.00" step="0.01">
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
                        <input type="number" class="form-control" id="cardAmount" value="0.00" step="0.01">
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
                                <input type="number" class="form-control" id="metalExchangeQty" value="1" step="0.01" onchange="calculateMetalExchange()">
                            </div>
                            <div class="form-group">
                                <label>Purity / Carat</label>
                                <input type="text" class="form-control" id="metalExchangePurity" placeholder="Purity / Carat" onchange="calculateMetalExchange()">
                            </div>
                            <div class="form-group">
                                <label>Weight</label>
                                <input type="number" class="form-control" id="metalExchangeWeight" value="0" step="0.001" onchange="calculateMetalExchange()">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Purity Wt.</label>
                                <input type="number" class="form-control" id="metalExchangePurityWt" value="0" step="0.001" readonly style="background: #f8fafc;">
                            </div>
                            <div class="form-group">
                                <label>Amount</label>
                                <input type="number" class="form-control" id="metalExchangeAmount" value="0.00" step="0.01" readonly style="background: #f8fafc;">
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
                                <input type="number" class="form-control" id="scrapQty" value="1" step="0.01" onchange="calculateScrap()">
                            </div>
                            <div class="form-group">
                                <label>Weight</label>
                                <input type="number" class="form-control" id="scrapWeight" value="0" step="0.001" onchange="calculateScrap()">
                            </div>
                            <div class="form-group">
                                <label>Purity / Carat</label>
                                <input type="text" class="form-control" id="scrapPurity" placeholder="Purity / Carat" onchange="calculateScrap()">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Purity Wt.</label>
                                <input type="number" class="form-control" id="scrapPurityWt" value="0" step="0.001" readonly style="background: #f8fafc;">
                            </div>
                            <div class="form-group">
                                <label>Amount</label>
                                <input type="number" class="form-control" id="scrapAmount" value="0.00" step="0.01" readonly style="background: #f8fafc;">
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

    <!-- Right Side Customer Creation Modal -->
    <div class="modal fade right" id="customerCreationModal" tabindex="-1" role="dialog" aria-labelledby="customerCreationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-right modal-xl" role="document" style="max-width: 90%; width: 90%; margin: 0; height: 100vh;">
            <div class="modal-content" style="height: 100vh; border-radius: 0; border: none;">
                <div class="modal-header" style="background: #11294b; color: #fff; border: none; padding: 1rem 1.5rem;">
                    <h5 class="modal-title" id="customerCreationModalLabel">Ledger Details</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff; opacity: 1;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" style="padding: 0; height: calc(100vh - 60px); overflow-y: auto; background: #f8fafc;">
                    <form id="customerCreationForm" method="post" style="height: 100%;" enctype="multipart/form-data">
                        <div style="padding: 1.5rem; max-width: 1400px; margin: 0 auto;">
                            <!-- Top Action Buttons -->
                            <div class="d-flex justify-content-end mb-2">
                                <button type="button" class="btn btn-secondary btn-sm" onclick="clearCustomerForm()" style="margin-right: 0.5rem; padding: 0.4rem 0.75rem; font-size: 0.85rem;">Clear</button>
                                <button type="button" class="btn btn-primary btn-sm" onclick="saveCustomer()" style="margin-right: 0.5rem; background: #11294b; border: none; padding: 0.4rem 0.75rem; font-size: 0.85rem;">Save</button>
                                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal" style="padding: 0.4rem 0.75rem; font-size: 0.85rem;">Close</button>
                            </div>

                            <!-- Ledger Photo and Basic Info -->
                            <div class="row mb-3">
                                <div class="col-md-2">
                                    <div style="text-align: center;">
                                        <div style="width: 120px; height: 120px; border-radius: 50%; background: #f1f5f9; border: 2px dashed #cbd5e1; display: flex; align-items: center; justify-content: center; margin: 0 auto; position: relative; cursor: pointer;" onclick="document.getElementById('ledgerPhotoInput').click();">
                                            <i class="feather icon-camera" style="font-size: 1.5rem; color: #94a3b8;"></i>
                                            <input type="file" id="ledgerPhotoInput" name="ledger_photo" accept="image/*" style="display: none;" onchange="previewLedgerPhoto(this);">
                                        </div>
                                        <div id="ledgerPhotoPreview" style="display: none; width: 120px; height: 120px; border-radius: 50%; margin: 0 auto; overflow: hidden; border: 2px solid #c5a864;">
                                            <img id="ledgerPhotoImg" src="" style="width: 100%; height: 100%; object-fit: cover;">
                                        </div>
                                        <div class="form-check mt-2" style="text-align: center;">
                                            <input class="form-check-input" type="checkbox" id="ledgerNameCapital" name="ledger_name_capital" style="width: 0.9rem; height: 0.9rem;">
                                            <label class="form-check-label" for="ledgerNameCapital" style="font-size: 0.75rem;">Ledger Name Capital</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-9">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Name *</label>
                                                <input type="text" class="form-control" id="ledgerName" name="name" required oninput="handleNameInput(this)">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Alternate Name</label>
                                                <input type="text" class="form-control" id="ledgerAlternateName" name="alternate_name">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>First Name</label>
                                                <input type="text" class="form-control" id="ledgerFirstName" name="first_name">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Last Name</label>
                                                <input type="text" class="form-control" id="ledgerLastName" name="last_name">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Mobile No</label>
                                                <div class="input-group">
                                                    <select class="form-control" id="mobileCountryCode" name="mobile_country_code" style="max-width: 70px; font-size: 0.85rem; padding: 0.4rem 0.5rem; height: 32px;">
                                                        <option value="971" selected>971</option>
                                                        <option value="1">1</option>
                                                        <option value="91">91</option>
                                                    </select>
                                                    <input type="text" class="form-control" id="ledgerMobileNo" name="mobile_no" placeholder="Mobile No">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Phone No</label>
                                                <div class="input-group">
                                                    <i class="feather icon-phone" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); z-index: 10; color: #94a3b8;"></i>
                                                    <input type="text" class="form-control" id="ledgerPhoneNo" name="phone_no" placeholder="Phone No" style="padding-left: 35px;">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Mail ID</label>
                                                <div class="input-group">
                                                    <i class="feather icon-mail" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); z-index: 10; color: #94a3b8;"></i>
                                                    <input type="email" class="form-control" id="ledgerMailId" name="mail_id" placeholder="Email" style="padding-left: 35px;">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Short Code / Identity No</label>
                                                <input type="text" class="form-control" id="ledgerIdentityNo" name="identity_no">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>National Id</label>
                                                <div class="input-group">
                                                    <i class="feather icon-credit-card" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); z-index: 10; color: #94a3b8;"></i>
                                                    <input type="text" class="form-control" id="ledgerNationalId" name="national_id" style="padding-left: 35px;">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Trade No</label>
                                                <div class="input-group">
                                                    <i class="feather icon-briefcase" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); z-index: 10; color: #94a3b8;"></i>
                                                    <input type="text" class="form-control" id="ledgerTradeNo" name="trade_no" style="padding-left: 35px;">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Identity Issue Date</label>
                                                <div class="input-group">
                                                    <input type="date" class="form-control" id="identityIssueDate" name="identity_issue_date">
                                                    <div class="input-group-append">
                                                        <span class="input-group-text" style="padding: 0.4rem 0.5rem; height: 32px; border: 1px solid #e2e8f0; border-left: none; background: #f8fafc;"><i class="feather icon-calendar" style="font-size: 0.85rem;"></i></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Identity Expiry Date</label>
                                                <div class="input-group">
                                                    <input type="date" class="form-control" id="identityExpiryDate" name="identity_expiry_date">
                                                    <div class="input-group-append">
                                                        <span class="input-group-text" style="padding: 0.4rem 0.5rem; height: 32px; border: 1px solid #e2e8f0; border-left: none; background: #f8fafc;"><i class="feather icon-calendar" style="font-size: 0.85rem;"></i></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Special Day</label>
                                                <div class="input-group">
                                                    <input type="date" class="form-control" id="specialDay" name="special_day">
                                                    <div class="input-group-append">
                                                        <span class="input-group-text" style="padding: 0.4rem 0.5rem; height: 32px; border: 1px solid #e2e8f0; border-left: none; background: #f8fafc;"><i class="feather icon-calendar" style="font-size: 0.85rem;"></i></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Customer Type</label>
                                                <div class="input-group">
                                                    <i class="feather icon-users" style="position: absolute; left: 8px; top: 50%; transform: translateY(-50%); z-index: 10; color: #94a3b8; font-size: 0.9rem;"></i>
                                                    <select class="form-control" id="customerType" name="customer_type_id">
                                                        <option value="">Select Customer Type</option>
                                                        <?php 
                                                        $customer_types = getList("SELECT id, name FROM tbl_customer_types WHERE status = 1 ORDER BY name ASC");
                                                        foreach($customer_types as $type) {
                                                            echo '<option value="'.$type['id'].'">'.htmlspecialchars($type['name']).'</option>';
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Registration No</label>
                                                <input type="text" class="form-control" id="registrationNo" name="registration_no">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Registration Date</label>
                                                <div class="input-group">
                                                    <input type="date" class="form-control" id="registrationDate" name="registration_date">
                                                    <div class="input-group-append">
                                                        <span class="input-group-text" style="padding: 0.4rem 0.5rem; height: 32px; border: 1px solid #e2e8f0; border-left: none; background: #f8fafc;"><i class="feather icon-calendar" style="font-size: 0.85rem;"></i></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Nationality</label>
                                                <div class="input-group">
                                                    <i class="feather icon-flag" style="position: absolute; left: 8px; top: 50%; transform: translateY(-50%); z-index: 10; color: #94a3b8; font-size: 0.9rem;"></i>
                                                    <select class="form-control" id="nationality" name="nationality_id">
                                                        <option value="">Select Nationality</option>
                                                        <?php 
                                                        $nationalities = getList("SELECT id, name FROM tbl_nationalities WHERE status = 1 ORDER BY name ASC");
                                                        foreach($nationalities as $nationality) {
                                                            echo '<option value="'.$nationality['id'].'">'.htmlspecialchars($nationality['name']).'</option>';
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Country</label>
                                                <div class="input-group">
                                                    <i class="feather icon-flag" style="position: absolute; left: 8px; top: 50%; transform: translateY(-50%); z-index: 10; color: #94a3b8; font-size: 0.9rem;"></i>
                                                    <select class="form-control" id="country" name="country_id">
                                                        <option value="">Select Country</option>
                                                        <?php 
                                                        $countries = getList("SELECT id, name FROM tbl_countries WHERE status = 1 ORDER BY name ASC");
                                                        foreach($countries as $country) {
                                                            echo '<option value="'.$country['id'].'">'.htmlspecialchars($country['name']).'</option>';
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Select Group</label>
                                                <div class="input-group">
                                                    <i class="feather icon-users" style="position: absolute; left: 8px; top: 50%; transform: translateY(-50%); z-index: 10; color: #94a3b8; font-size: 0.9rem;"></i>
                                                    <select class="form-control" id="ledgerGroup" name="group_id">
                                                        <option value="">Select Group</option>
                                                        <?php 
                                                        foreach($ledger_groups as $group) {
                                                            echo '<option value="'.$group['id'].'">'.htmlspecialchars($group['name']).'</option>';
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label>Sundry Debtors *</label>
                                                <div class="input-group">
                                                    <i class="feather icon-users" style="position: absolute; left: 8px; top: 50%; transform: translateY(-50%); z-index: 10; color: #94a3b8; font-size: 0.9rem;"></i>
                                                    <select class="form-control" id="ledgerSundryDebtors" name="sundry_debtors_id" required>
                                                        <option value="">Select</option>
                                                        <?php 
                                                        foreach($sundry_options as $option) {
                                                            echo '<option value="'.$option['id'].'">'.htmlspecialchars($option['name']).'</option>';
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" id="ledgerKYC" name="kyc">
                                                    <label class="form-check-label" for="ledgerKYC">KYC</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" id="ledgerAML" name="aml">
                                                    <label class="form-check-label" for="ledgerAML">AML</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <label class="form-check-label" style="margin-right: 10px;">Bill to Bill:</label>
                                                    <input class="form-check-input" type="radio" id="billToBillYes" name="bill_to_bill" value="1">
                                                    <label class="form-check-label" for="billToBillYes" style="margin-right: 15px;">Yes</label>
                                                    <input class="form-check-input" type="radio" id="billToBillNo" name="bill_to_bill" value="0" checked>
                                                    <label class="form-check-label" for="billToBillNo">No</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Address and Bank Details Tabs -->
                            <ul class="nav nav-tabs mb-3" id="ledgerTabs" role="tablist">
                                <li class="nav-item">
                                    <a class="nav-link active" id="billing-tab" data-toggle="tab" href="#billing-address" role="tab">Billing Address</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="shipping-tab" data-toggle="tab" href="#shipping-address" role="tab">Shipping Address</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="item-type-tax-tab" data-toggle="tab" href="#item-type-tax" role="tab">Item Type Tax</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="share-holders-tab" data-toggle="tab" href="#share-holders" role="tab">Share Holders</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="notes-tab" data-toggle="tab" href="#notes" role="tab">Notes</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" id="nominee-tab" data-toggle="tab" href="#nominee" role="tab">Nominee</a>
                                </li>
                                <li class="nav-item">
                                </li>
                            </ul>

                            <div class="tab-content" id="ledgerTabContent">
                                <!-- Billing Address Tab -->
                                <div class="tab-pane fade show active" id="billing-address" role="tabpanel">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Address 1</label>
                                                <input type="text" class="form-control" id="billingAddress1" name="billing_address1">
                                            </div>
                                            <div class="form-group">
                                                <label>Address 2</label>
                                                <input type="text" class="form-control" id="billingAddress2" name="billing_address2">
                                            </div>
                                            <div class="form-group">
                                                <label>Country *</label>
                                                <select class="form-control" id="billingCountry" name="billing_country" required>
                                                    <option value="">Select Country</option>
                                                    <option value="United Arab Emirates" selected>United Arab Emirates</option>
                                                    <option value="India">India</option>
                                                    <option value="USA">USA</option>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label>State *</label>
                                                <select class="form-control" id="billingState" name="billing_state" required>
                                                    <option value="">Select State</option>
                                                    <option value="Sharjah Emirate" selected>Sharjah Emirate</option>
                                                    <option value="Dubai">Dubai</option>
                                                    <option value="Abu Dhabi">Abu Dhabi</option>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label>Zip Code</label>
                                                <input type="text" class="form-control" id="billingZipCode" name="billing_zip_code">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Bank Details</label>
                                            </div>
                                            <div class="form-group">
                                                <label>Acc.No.</label>
                                                <input type="text" class="form-control" id="bankAccountNo" name="bank_account_no" placeholder="Account No">
                                            </div>
                                            <div class="form-group">
                                                <label>Name</label>
                                                <input type="text" class="form-control" id="bankName" name="bank_name" placeholder="Bank Name">
                                            </div>
                                            <div class="form-group">
                                                <label>IFSC Code</label>
                                                <input type="text" class="form-control" id="bankIfscCode" name="bank_ifsc_code" placeholder="IFSC Code">
                                            </div>
                                            <div class="form-group">
                                                <label>Branch</label>
                                                <input type="text" class="form-control" id="bankBranch" name="bank_branch">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Other Tabs (Placeholder) -->
                                <div class="tab-pane fade" id="shipping-address" role="tabpanel">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Address 1</label>
                                                <input type="text" class="form-control" id="shippingAddress1" name="shipping_address1">
                                            </div>
                                            <div class="form-group">
                                                <label>Address 2</label>
                                                <input type="text" class="form-control" id="shippingAddress2" name="shipping_address2">
                                            </div>
                                            <div class="form-group">
                                                <label>Country *</label>
                                                <select class="form-control" id="shippingCountry" name="shipping_country">
                                                    <option value="">Select Country</option>
                                                    <option value="United Arab Emirates">United Arab Emirates</option>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label>State *</label>
                                                <select class="form-control" id="shippingState" name="shipping_state">
                                                    <option value="">Select State</option>
                                                </select>
                                            </div>
                                            <div class="form-group">
                                                <label>Zip Code</label>
                                                <input type="text" class="form-control" id="shippingZipCode" name="shipping_zip_code">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="item-type-tax" role="tabpanel">
                                    <div style="background: #fff; padding: 1.5rem; border-radius: 8px; border: 1px solid #e2e8f0;">
                                        <table class="item-tax-table">
                                            <thead>
                                                <tr>
                                                    <th style="width: 40%;">Item Name</th>
                                                    <th style="width: 30%;">Default Input Type</th>
                                                    <th style="width: 30%;">Default Output Type</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>AMOUNT</td>
                                                    <td>
                                                        <select name="item_tax[AMOUNT][input_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <select name="item_tax[AMOUNT][output_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>Gold</td>
                                                    <td>
                                                        <select name="item_tax[Gold][input_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <select name="item_tax[Gold][output_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>GOLD - MAKING</td>
                                                    <td>
                                                        <select name="item_tax[GOLD_MAKING][input_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <select name="item_tax[GOLD_MAKING][output_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>Silver</td>
                                                    <td>
                                                        <select name="item_tax[Silver][input_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <select name="item_tax[Silver][output_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>SILVER - MAKING</td>
                                                    <td>
                                                        <select name="item_tax[SILVER_MAKING][input_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <select name="item_tax[SILVER_MAKING][output_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>Diamond & Stones</td>
                                                    <td>
                                                        <select name="item_tax[Diamond_Stones][input_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <select name="item_tax[Diamond_Stones][output_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>Imitation Or Watches</td>
                                                    <td>
                                                        <select name="item_tax[Imitation_Watches][input_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <select name="item_tax[Imitation_Watches][output_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>LOOSE - DIAMOND</td>
                                                    <td>
                                                        <select name="item_tax[LOOSE_DIAMOND][input_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <select name="item_tax[LOOSE_DIAMOND][output_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>CERTIFIED - DIAMOND</td>
                                                    <td>
                                                        <select name="item_tax[CERTIFIED_DIAMOND][input_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <select name="item_tax[CERTIFIED_DIAMOND][output_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td>Other Or Services</td>
                                                    <td>
                                                        <select name="item_tax[Other_Services][input_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <select name="item_tax[Other_Services][output_type]" class="form-control">
                                                            <option value="">...</option>
                                                            <option value="VAT">VAT</option>
                                                            <option value="TAX BAH">TAX BAH</option>
                                                        </select>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="share-holders" role="tabpanel">
                                    <div style="background: #fff; padding: 1.5rem; border-radius: 8px; border: 1px solid #e2e8f0;">
                                        <!-- Share Holders Table -->
                                        <div style="margin-bottom: 1.5rem;">
                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                                <h6 style="margin: 0; font-size: 0.95rem; font-weight: 600; color: #1e293b;">Share Holders</h6>
                                                <div style="display: flex; gap: 0.5rem;">
                                                    <button type="button" class="btn btn-sm" id="addShareHolderBtn" style="background: #c5a864; color: #fff; border: none; padding: 0.4rem 0.75rem; border-radius: 4px; cursor: pointer;">
                                                        <i class="feather icon-plus" style="font-size: 0.85rem;"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm" style="background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; padding: 0.4rem 0.75rem; border-radius: 4px; cursor: pointer;">
                                                        <i class="feather icon-settings" style="font-size: 0.85rem;"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            
                                            <div style="overflow-x: auto;">
                                                <table class="table" id="shareHoldersTable" style="margin-bottom: 0; font-size: 0.85rem;">
                                                    <thead style="background: #11294b; color: #fff;">
                                                        <tr>
                                                            <th style="padding: 0.6rem 1rem; font-weight: 600; font-size: 0.85rem; border: none; cursor: pointer; user-select: none;" onclick="sortShareHoldersTable(0)">
                                                                Name
                                                                <i class="feather icon-arrow-up" style="font-size: 0.7rem; margin-left: 0.25rem;"></i>
                                                                <i class="feather icon-arrow-down" style="font-size: 0.7rem; margin-left: 0.1rem;"></i>
                                                            </th>
                                                            <th style="padding: 0.6rem 1rem; font-weight: 600; font-size: 0.85rem; border: none; cursor: pointer; user-select: none;" onclick="sortShareHoldersTable(1)">
                                                                Nationality
                                                                <i class="feather icon-arrow-up" style="font-size: 0.7rem; margin-left: 0.25rem;"></i>
                                                                <i class="feather icon-arrow-down" style="font-size: 0.7rem; margin-left: 0.1rem;"></i>
                                                            </th>
                                                            <th style="padding: 0.6rem 1rem; font-weight: 600; font-size: 0.85rem; border: none; cursor: pointer; user-select: none;" onclick="sortShareHoldersTable(2)">
                                                                Share Per.
                                                                <i class="feather icon-arrow-up" style="font-size: 0.7rem; margin-left: 0.25rem;"></i>
                                                                <i class="feather icon-arrow-down" style="font-size: 0.7rem; margin-left: 0.1rem;"></i>
                                                            </th>
                                                            <th style="padding: 0.6rem 1rem; font-weight: 600; font-size: 0.85rem; border: none; width: 60px; text-align: center;">Action</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="shareHoldersTableBody">
                                                        <!-- Rows will be added dynamically -->
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        
                                        <!-- Upload Document Section -->
                                        <div style="margin-top: 1.5rem;">
                                            <h6 style="margin: 0 0 1rem 0; font-size: 0.95rem; font-weight: 600; color: #1e293b;">Upload Document</h6>
                                            <div id="shareHolderDocumentUpload" style="border: 2px dashed #cbd5e1; border-radius: 8px; padding: 2rem; text-align: center; background: #f8fafc; cursor: pointer; transition: all 0.3s ease;" 
                                                 ondrop="handleShareHolderFileDrop(event)" 
                                                 ondragover="event.preventDefault(); this.style.borderColor = '#c5a864';" 
                                                 ondragleave="this.style.borderColor = '#cbd5e1';"
                                                 onclick="document.getElementById('shareHolderFileInput').click();">
                                                <input type="file" id="shareHolderFileInput" name="share_holder_documents[]" multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="display: none;" onchange="handleShareHolderFileSelect(this);">
                                                <i class="feather icon-upload-cloud" style="font-size: 2.5rem; color: #c5a864; margin-bottom: 0.5rem;"></i>
                                                <p style="margin: 0.5rem 0 0 0; color: #64748b; font-size: 0.85rem;">Drop files here or click to upload.</p>
                                            </div>
                                            <div id="shareHolderFileList" style="margin-top: 1rem;"></div>
                                        </div>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="notes" role="tabpanel">
                                    <div class="form-group">
                                        <label>Notes</label>
                                        <textarea class="form-control" id="ledgerNotes" name="notes" rows="5"></textarea>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="nominee" role="tabpanel">
                                    <p>Nominee content goes here</p>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Core scripts -->
    <?php include 'footer-script.php';?>
    
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
            if (amount <= 0) {
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
            if (amount <= 0) {
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
            if (amount <= 0) {
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
            if (amount <= 0) {
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
            if (amount <= 0) {
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
            <td>${paymentTypeLabel}</td>
            <td>${paymentData.deposit_into || ''}</td>
            <td>${paymentData.transaction_no || ''}</td>
            <td>${paymentData.cheque_date || ''}</td>
            <td>${paymentData.purity_carat || ''}</td>
            <td data-payment-amount style="text-align: right; font-weight: 600;">${parseFloat(paymentData.amount || 0).toFixed(2)}</td>
            <td>${paymentData.diamond_category || ''}</td>
            <td style="text-align: right;">${parseFloat(paymentData.quantity || 0).toFixed(2)}</td>
            <td>
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
                }
                updateReceiptTotal();
            }
        }
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
        
        // Populate modal with existing data
        const depositInto = row.querySelector('td:nth-child(2)').textContent.trim();
        const transactionNo = row.querySelector('td:nth-child(3)').textContent.trim();
        const chequeDate = row.querySelector('td:nth-child(4)').textContent.trim();
        const purityCarat = row.querySelector('td:nth-child(5)').textContent.trim();
        const amount = parseFloat(row.querySelector('[data-payment-amount]').textContent.replace(/,/g, '') || 0);
        const diamondCategory = row.querySelector('td:nth-child(7)').textContent.trim();
        const quantity = parseFloat(row.querySelector('td:nth-child(8)').textContent.replace(/,/g, '') || 0);
        
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

    // Customer search functionality
    $(document).ready(function() {
        let customerSearchTimeout;
        $('#customerName').on('input', function() {
            clearTimeout(customerSearchTimeout);
            const searchTerm = $(this).val();
            if (searchTerm.length < 2) {
                $('#customerSuggestions').hide().empty();
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
            data: { q: term },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success' && response.customers.length > 0) {
                    let html = '';
                    response.customers.forEach(function(customer) {
                        html += '<div class="customer-suggestion-item" style="padding: 8px 12px; cursor: pointer; border-bottom: 1px solid #e2e8f0;" onclick="selectCustomer(' + customer.id + ', \'' + customer.name.replace(/'/g, "\\'") + '\')">';
                        html += '<strong>' + customer.display_text + '</strong>';
                        if (customer.mail_id) {
                            html += '<br><small class="text-muted">' + customer.mail_id + '</small>';
                        }
                        html += '</div>';
                    });
                    $('#customerSuggestions').html(html).show();
                } else {
                    $('#customerSuggestions').hide().empty();
                }
            }
        });
    }

    function selectCustomer(id, name) {
        $('#customerId').val(id);
        $('#customerName').val(name);
        $('#customerSuggestions').hide();
        loadCustomerBalance();
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
                <input type="number" class="form-control" name="share_holders[${shareHolderRowIndex}][share_percentage]" placeholder="0.00" step="0.01" min="0" max="100" style="font-size: 0.85rem; padding: 0.4rem 0.6rem; height: 32px; border: 1px solid #e2e8f0; text-align: right;">
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
                aVal = parseFloat(a.querySelector('input[type="number"]')?.value || 0);
                bVal = parseFloat(b.querySelector('input[type="number"]')?.value || 0);
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
                
                // Update the customer name field in the main form
                if (data.customer_name && document.getElementById('customerName')) {
                    document.getElementById('customerName').value = data.customer_name;
                    if (data.customer_id) {
                        document.getElementById('customerId').value = data.customer_id;
                    }
                    loadCustomerBalance();
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

    function loadCustomerBalance() {
        const customerId = $('#customerId').val();
        const customerName = $('#customerName').val();
        if (!customerId && !customerName) {
            // Reset previous balance and history if no customer
            $('#previousBalanceAmount').val('0.00');
            $('#previousBalanceGold').val('0.000');
            $('#previousBalanceSilver').val('0.000');
            $('#transactionHistoryTable').hide();
            $('#noHistoryMessage').show().text('Select a customer to view history');
            return;
        }

        // Load balance
        $.ajax({
            url: 'ajax/get-customer-balance.php',
            method: 'GET',
            data: { 
                customer_id: customerId || 0,
                customer_name: customerName || ''
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    // Use original_balance (ledger running balance) so Previous Balance matches ledger report
                    var bal = response.original_balance || response.balance;
                    $('#previousBalanceAmount').val(parseFloat(bal.amount || 0).toFixed(2));
                    $('#previousBalanceGold').val(parseFloat(bal.gold || 0).toFixed(3));
                    $('#previousBalanceSilver').val(parseFloat(bal.silver || 0).toFixed(3));
                }
            }
        });

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
                <td>
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
                <td>
                    <input type="text" class="form-control form-control-sm diamond-category" placeholder="Diamond Category">
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm transaction-no" placeholder="Transaction No.">
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm deposit-into" placeholder="Deposit Into">
                </td>
                <td>
                    <select class="form-control form-control-sm product-select">
                        <option value="">Select Product</option>
                        <?php foreach($products as $product): ?>
                        <option value="<?php echo $product['id']; ?>"><?php echo htmlspecialchars($product['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <input type="date" class="form-control form-control-sm cheque-date">
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm weight" step="0.001" placeholder="0.000" onchange="calculateReceiptRow('${rowId}')">
                </td>
                <td>
                    <select class="form-control form-control-sm metal-select">
                        <option value="">Select Metal</option>
                        <?php foreach($metals as $metal): ?>
                        <option value="<?php echo $metal['id']; ?>"><?php echo htmlspecialchars($metal['display_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm quantity" step="0.01" placeholder="0.00" onchange="calculateReceiptRow('${rowId}')">
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm purity-carat" placeholder="Purity / Carat" onchange="calculateReceiptRow('${rowId}')">
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm purity-wt" step="0.001" placeholder="0.000" readonly style="background: #f8fafc;">
                </td>
                <td>
                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteReceiptRow('${rowId}')">
                        <i class="feather icon-trash-2"></i>
                    </button>
                </td>
            </tr>
        `;
        $tbody.append(row);
        updateReceiptTotal();
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
            const amt = parseFloat(row.querySelector('[data-payment-amount]')?.textContent.replace(/,/g, '') || 0);
            const qty = parseFloat(row.querySelector('td:nth-child(8)')?.textContent.replace(/,/g, '') || 0);
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
        if (confirm('Are you sure you want to reset the form? All unsaved data will be lost.')) {
            location.reload();
        }
    }

    function saveVoucher() {
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
            currency_rate: $('#currencyRate').val(),
            voucher_date: $('#voucherDate').val(),
            due_date: $('#dueDate').val(),
            layaways_id: $('#layaways').val(),
            fixing_type: $('#fixingType').val(),
            previous_balance: $('#previousBalanceAmount').val(),
            previous_gold: $('#previousBalanceGold').val(),
            previous_silver: $('#previousBalanceSilver').val(),
            comment: $('#comment').val(),
            items: []
        };

        // Collect receipt items
        $('#receiptTableBody tr').each(function() {
            if (!$(this).hasClass('no-payment-row') && !$(this).hasClass('no-rows')) {
                const $row = $(this);
                const paymentTypeText = $row.find('td:first-child').text().trim();
                let paymentType = 'Cash';
                if (paymentTypeText === 'Bank') paymentType = 'Bank';
                else if (paymentTypeText === 'Cheque') paymentType = 'Cheque';
                else if (paymentTypeText === 'UPI') paymentType = 'UPI';
                else if (paymentTypeText === 'Card') paymentType = 'Card';
                else if (paymentTypeText === 'M. Exch.') paymentType = 'Metal';
                else if (paymentTypeText === 'Scrap') paymentType = 'Scrap';
                
                const amountText = $row.find('[data-payment-amount]').text().replace(/,/g, '') || '0';
                const amount = parseFloat(amountText) || 0;
                
                const item = {
                    payment_type: paymentType,
                    diamond_category: $row.find('td').eq(6).text().trim(),
                    transaction_no: $row.find('td').eq(2).text().trim(),
                    deposit_into: $row.find('td').eq(1).text().trim(),
                    product_id: '',
                    cheque_date: $row.find('td').eq(3).text().trim(),
                    weight: 0,
                    metal_id: '',
                    quantity: parseFloat($row.find('td').eq(7).text().replace(/,/g, '') || 0),
                    purity_carat: $row.find('td').eq(4).text().trim(),
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

        // Validation
        if (!voucherData.customer_name) {
            alert('Please select a customer');
            return;
        }

        $.ajax({
            url: 'ajax/save-customer-advance-voucher.php',
            method: 'POST',
            data: voucherData,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message || 'Voucher saved successfully!');
                    // Add saved voucher to Payment List table
                    if (response.voucher_id) {
                        loadVoucherIntoPaymentList(response.voucher_id);
                        // Update voucher ID in hidden field
                        $('#voucherId').val(response.voucher_id);
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
    $(document).ready(function() {
        $('#customerId').val('<?php echo $edit_voucher['customer_id'] ?? ''; ?>');
        $('#customerName').val('<?php echo htmlspecialchars($edit_voucher['customer_name'] ?? ''); ?>');
        $('#refNo').val('<?php echo htmlspecialchars($edit_voucher['ref_no'] ?? ''); ?>');
        $('#voucherType').val('<?php echo htmlspecialchars($edit_voucher['voucher_type'] ?? ''); ?>');
        $('#against').val('<?php echo htmlspecialchars($edit_voucher['against'] ?? ''); ?>');
        $('#salesPerson').val('<?php echo htmlspecialchars($edit_voucher['sales_person'] ?? ''); ?>');
        $('#againstOf').val('<?php echo htmlspecialchars($edit_voucher['against_of'] ?? ''); ?>');
        $('#currency').val('<?php echo htmlspecialchars($edit_voucher['currency'] ?? 'USD'); ?>');
        $('#voucherDate').val('<?php echo $edit_voucher['voucher_date'] ?? date('Y-m-d'); ?>');
        $('#dueDate').val('<?php echo $edit_voucher['due_date'] ?? date('Y-m-d'); ?>');
        $('#fixingType').val('<?php echo htmlspecialchars($edit_voucher['fixing_type'] ?? 'Standard'); ?>');
        // Load previous balance and history for edit mode
        if ($('#customerId').val() || $('#customerName').val()) {
            loadCustomerBalance();
        } else {
            // If no customer selected, use saved values
            $('#previousBalanceAmount').val('<?php echo $edit_voucher['previous_balance'] ?? 0; ?>');
            $('#previousBalanceGold').val('<?php echo $edit_voucher['previous_gold'] ?? 0; ?>');
            $('#previousBalanceSilver').val('<?php echo $edit_voucher['previous_silver'] ?? 0; ?>');
        }
        $('#comment').val('<?php echo htmlspecialchars($edit_voucher['comment'] ?? ''); ?>');

        // Load receipt items
        <?php foreach($edit_items as $item): ?>
        receiptRowIndex++;
        const rowId = 'receipt-row-' + receiptRowIndex;
        $('#receiptTableBody .no-rows').remove();
        const row = $('<tr id="' + rowId + '" data-row-index="' + receiptRowIndex + '">' +
            '<td><select class="form-control form-control-sm payment-type" onchange="calculateReceiptRow(\'' + rowId + '\')">' +
            '<option value="">Select</option><option value="Cash">Cash</option><option value="Bank">Bank</option>' +
            '<option value="Cheque">Cheque</option><option value="UPI">UPI</option><option value="Card">Card</option><option value="Metal">Metal</option>' +
            '</select></td>' +
            '<td><input type="text" class="form-control form-control-sm diamond-category" value="<?php echo htmlspecialchars($item['diamond_category'] ?? ''); ?>"></td>' +
            '<td><input type="text" class="form-control form-control-sm transaction-no" value="<?php echo htmlspecialchars($item['transaction_no'] ?? ''); ?>"></td>' +
            '<td><input type="text" class="form-control form-control-sm deposit-into" value="<?php echo htmlspecialchars($item['deposit_into'] ?? ''); ?>"></td>' +
            '<td><select class="form-control form-control-sm product-select"><option value="">Select Product</option></select></td>' +
            '<td><input type="date" class="form-control form-control-sm cheque-date" value="<?php echo $item['cheque_date'] ?? ''; ?>"></td>' +
            '<td><input type="number" class="form-control form-control-sm weight" step="0.001" value="<?php echo $item['weight'] ?? 0; ?>" onchange="calculateReceiptRow(\'' + rowId + '\')"></td>' +
            '<td><select class="form-control form-control-sm metal-select"><option value="">Select Metal</option></select></td>' +
            '<td><input type="number" class="form-control form-control-sm quantity" step="0.01" value="<?php echo $item['quantity'] ?? 0; ?>" onchange="calculateReceiptRow(\'' + rowId + '\')"></td>' +
            '<td><input type="text" class="form-control form-control-sm purity-carat" value="<?php echo htmlspecialchars($item['purity_carat'] ?? ''); ?>" onchange="calculateReceiptRow(\'' + rowId + '\')"></td>' +
            '<td><input type="number" class="form-control form-control-sm purity-wt" step="0.001" value="<?php echo $item['purity_wt'] ?? 0; ?>" readonly style="background: #f8fafc;"></td>' +
            '<td><button type="button" class="btn btn-sm btn-danger" onclick="deleteReceiptRow(\'' + rowId + '\')"><i class="feather icon-trash-2"></i></button></td>' +
            '</tr>');
        row.find('.payment-type').val('<?php echo htmlspecialchars($item['payment_type'] ?? ''); ?>');
        row.find('.product-select').val('<?php echo $item['product_id'] ?? ''; ?>');
        row.find('.metal-select').val('<?php echo $item['metal_id'] ?? ''; ?>');
        $('#receiptTableBody').append(row);
        <?php endforeach; ?>
        updateReceiptTotal();
    });
    <?php endif; ?>
    
    // Load voucher into Payment List table
    function loadVoucherIntoPaymentList(voucherId) {
        $.ajax({
            url: 'ajax/get-customer-advance-voucher.php',
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
                <td>${rowNum}</td>
                <td>${voucher.sales_person || ''}</td>
                <td>${voucher.voucher_date || ''}</td>
                <td>${voucher.customer_name || ''}</td>
                <td>${voucher.voucher_no || ''}</td>
                <td></td>
                <td>${voucher.ref_no || ''}</td>
                <td>${voucher.against || ''}</td>
                <td>${voucher.against_of || ''}</td>
                <td>${parseFloat(voucher.total_amount || 0).toFixed(2)}</td>
                <td>${parseFloat(voucher.total_gold || 0).toFixed(3)}</td>
                <td>${cash.toFixed(2)}</td>
                <td>${bank.toFixed(2)}</td>
                <td>${cheque.toFixed(2)}</td>
                <td>${upi.toFixed(2)}</td>
                <td>${card.toFixed(2)}</td>
                <td>${metal.toFixed(3)}</td>
            </tr>
        `;
        
        tbody.append(row);
    }
    
    // Load existing vouchers on page load
    $(document).ready(function() {
        // Load all vouchers into Payment List
        $.ajax({
            url: 'ajax/get-customer-advance-vouchers.php',
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
                            tbody.html('<tr class="no-rows"><td colspan="17" class="text-center text-muted py-4">No Rows To Show</td></tr>');
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
</body>
</html>
