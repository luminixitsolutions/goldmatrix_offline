<?php 
session_start();
require_once 'config.php';

// Load Metals for category tabs
$metals = getList("SELECT id, display_name, system_name FROM tbl_metal WHERE status = 1 ORDER BY id ASC");

// Load Carat master data
$carats = getList("SELECT id, name, purity, description FROM tbl_carat WHERE status = 1 ORDER BY id ASC");

// Load Location master data
$locations = getList("SELECT id, name FROM tbl_location WHERE status = 1 ORDER BY id ASC");

// Load master data for product creation modal
$categories = getList("SELECT id, name FROM tbl_categories WHERE status = 1 ORDER BY name ASC");
$branches = getListMaster("SELECT id, name, code FROM tbl_branches WHERE status = 1 ORDER BY name ASC");
$calculation_modes = getList("SELECT id, name, code FROM tbl_calculation_modes WHERE status = 1 ORDER BY sort_order ASC, name ASC");

// Standard ledger groups for dropdown
$ledger_groups = [
    ['id' => 1, 'name' => 'Sundry Debtors'],
    ['id' => 2, 'name' => 'Sundry Creditors'],
    ['id' => 3, 'name' => 'Bank Accounts'],
    ['id' => 4, 'name' => 'Cash'],
    ['id' => 5, 'name' => 'Sales'],
    ['id' => 6, 'name' => 'Purchase'],
    ['id' => 7, 'name' => 'Expenses'],
    ['id' => 8, 'name' => 'Income'],
    ['id' => 9, 'name' => 'Capital'],
    ['id' => 10, 'name' => 'Loans & Advances'],
    ['id' => 11, 'name' => 'Fixed Assets'],
    ['id' => 12, 'name' => 'Current Assets'],
    ['id' => 13, 'name' => 'Current Liabilities'],
    ['id' => 14, 'name' => 'Investment'],
];

// Sundry Debtors/Creditors options
$sundry_options = [
    ['id' => 1, 'name' => 'Sundry Debtors'],
    ['id' => 2, 'name' => 'Sundry Creditors'],
];

// Get next order number
$last_order = getRecord("SELECT order_no FROM tbl_sale_orders ORDER BY id DESC LIMIT 1");
$next_order_no = 'SO-1';
if ($last_order && $last_order['order_no']) {
    $last_num = (int)str_replace('SO-', '', $last_order['order_no']);
    $next_order_no = 'SO-' . ($last_num + 1);
}

// Load order for editing if ID provided
$edit_order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$edit_order = null;
$edit_items = [];
$edit_payments = [];

if ($edit_order_id > 0) {
    $edit_order = getRecord("SELECT * FROM tbl_sale_orders WHERE id = $edit_order_id");
    if ($edit_order) {
        $edit_items = getList("SELECT * FROM tbl_sale_order_items WHERE order_id = $edit_order_id");
        $edit_payments = getList("SELECT * FROM tbl_sale_order_payments WHERE order_id = $edit_order_id");
        // Update next_order_no to current order number
        $next_order_no = $edit_order['order_no'];
    }
}

// Get list of saved orders for dropdown
$saved_orders = getList("SELECT id, order_no, customer_name, order_date, grand_total FROM tbl_sale_orders ORDER BY id DESC LIMIT 50");
// Debug: Check if orders exist
if (empty($saved_orders)) {
    // Try to check if table exists and has any records
    $check_table = getRecord("SELECT COUNT(*) as total FROM tbl_sale_orders");
    if ($check_table && $check_table['total'] > 0) {
        // Table has records but query returned empty - might be a query issue
        error_log("Sale orders table has " . $check_table['total'] . " records but query returned empty");
    }
}
?>
<!DOCTYPE html>

<html lang="en" class="default-style">

<head>
    <title>Gold Matrix - Advance Software for Smart Jewellers</title>

<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">

<meta name="description" content="Gold Matrix is an advanced jewellery management software designed for smart jewellers. Manage billing, inventory, karigar accounts, gold rates, stock tracking, CRM, reports, and financial operations with precision and ease." />

<meta name="keywords" content="Jewellery Software, Gold Billing Software, Jewellery Management System, Gold Shop Software, Jewellery Inventory Software, Karigar Management, Gold Rate Management, Retail Jewellery Software, Jewellery ERP, Smart Jewellers Software" />

<meta name="author" content="Gold Matrix Software Team" />
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
    
    /* Hide sidebar */
    #layout-sidenav {
        display: none !important;
    }
    .layout-container {
        margin-left: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
    }
    .layout-sidenav-toggle {
        display: none !important;
    }
    
    /* Hide top navbar */
    #layout-navbar {
        display: none !important;
    }
    
    /* Full width content */
    .layout-content {
        margin: 0 !important;
        padding: 0 !important;
    }
    
    
    
    .row {
        margin-left: 0px;
        margin-right: -15px;
        padding-top: 5px;
    }
    
    .row > [class*="col-"] {
        padding-left: 15px;
        padding-right: 15px;
    }
    
    .card {
        margin-left: -15px;
        margin-right: -15px;
        border-left: none;
        border-right: none;
        border-radius: 0;
    }
    
    .card-body {
        padding-left: 15px;
        padding-right: 15px;
    }
    
    /* Ensure full width for main content */
    .layout-wrapper {
        width: 100% !important;
        max-width: 100% !important;
    }
    
    .layout-inner {
        width: 100% !important;
        max-width: 100% !important;
    }
    
    .top-navbar {
        background: #11294b;
        
        padding: 5px 5px 0px 5px;
        margin: 0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    border-radius: 10px;
    }
    
    .top-navbar .nav {
        padding-left: 15px;
        padding-right: 15px;
    }
    
    .company-header {
        padding-left: 15px !important;
        padding-right: 15px !important;
    }
    .top-navbar .nav-item {
        position: relative !important;
    }
    .top-navbar .nav-link {
        color: #546e7a;
        padding: 10px 10px;
        font-weight: 500;
        font-size: 12px;
        border-bottom: 3px solid transparent;
        border-radius: 6px 6px 0 0;
        transition: all 0.2s ease;
        position: relative;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-right: 0.25rem;
    }
    .top-navbar .nav-link i {
        font-size: 15px;
    }
    .top-navbar .nav-link:hover {
        color: #c5a864;
        background: rgba(17, 41, 75, 0.08);
    }
    .top-navbar .nav-link.active {
        color: #11294b;
        background: #a68a4a;
        border-bottom-color: #c5a864;
        font-weight: 600;
        box-shadow: 0 2px 4px rgba(197, 168, 100, 0.3);
    }
    /* Dropdown Menu Styles */
    .top-navbar .nav-item > .dropdown-menu {
        position: absolute !important;
        top: 100% !important;
        left: 0 !important;
        min-width: 220px;
        background: #ffffff !important;
        border: 1px solid #e0e0e0 !important;
        border-radius: 6px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
        padding: 0.5rem 0 !important;
        margin-top: 0px !important;
        opacity: 0 !important;
        visibility: hidden !important;
        transform: translateY(-10px);
        transition: all 0.3s ease !important;
        z-index: 1000 !important;
        list-style: none !important;
        margin-left: 0 !important;
        display: block !important;
        pointer-events: none;
    }
    /* Mega Menu Styles */
    
    .top-navbar .nav-item.mega-menu-item:hover > .mega-menu,
    .mega-menu:hover {
        opacity: 1 !important;
        visibility: visible !important;
        transform: translateY(0) !important;
        pointer-events: auto;
    }
    .mega-menu-content {
        display: flex;
        gap: 2rem;
        flex-wrap: wrap;
    }
    .mega-menu-column {
        flex: 1;
        min-width: 160px;
    }
    .mega-menu-title {
        font-size: 0.75rem;
        font-weight: 700;
        color: #11294b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 0.75rem;
        padding-bottom: 0.5rem;
        border-bottom: 2px solid #c5a864;
    }
    .mega-menu-list {
        list-style: none !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    .mega-menu-list li {
        margin: 0 !important;
        padding: 0 !important;
    }
    .mega-menu-list .dropdown-item {
        display: block !important;
        padding: 0.5rem 0.75rem !important;
        color: #000000 !important;
        font-size: 0.85rem !important;
        text-decoration: none !important;
        transition: all 0.2s ease;
        border-radius: 4px;
        white-space: nowrap;
        background: transparent !important;
        margin-bottom: 0.25rem;
    }
    .mega-menu-list .dropdown-item:hover {
        background: rgba(17, 41, 75, 0.08) !important;
        color: #11294b !important;
        padding-left: 1rem;
    }
    .mega-menu-list .dropdown-item i {
        margin-right: 0.5rem;
        width: 16px;
        text-align: center;
        font-size: 0.9rem;
    }
    .top-navbar .nav-item:hover > .dropdown-menu {
        pointer-events: auto;
    }
    .top-navbar .nav-item:hover > .dropdown-menu,
    .top-navbar .dropdown-menu:hover {
        opacity: 1 !important;
        visibility: visible !important;
        transform: translateY(0) !important;
        display: block !important;
    }
    .top-navbar .nav-tabs {
        border-bottom: none !important;
    }
    .top-navbar .nav-tabs .nav-item {
        margin-bottom: 0 !important;
    }
    .top-navbar .dropdown-menu li {
        list-style: none !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    .top-navbar .dropdown-item {
        display: block !important;
        padding: 5px 10px !important;
        color: #000000 !important;
        font-size: 0.85rem !important;
        text-decoration: none !important;
        transition: all 0.2s ease;
        border-bottom: none !important;
        white-space: nowrap;
        background: transparent !important;
    }
    .top-navbar .dropdown-item:hover {
        background: rgba(17, 41, 75, 0.1) !important;
        color: #11294b !important;
    }
    .top-navbar .dropdown-item i {
        margin-right: 0.5rem;
        width: 16px;
        text-align: center;
        font-size: 0.9rem;
    }
    .top-navbar .dropdown-divider {
        height: 1px !important;
        margin: 0.5rem 0 !important;
        overflow: hidden;
        background-color: #e0e0e0 !important;
        border: none !important;
        padding: 0 !important;
        display: block !important;
    }
    .top-navbar-right {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding-right: 15px;
    }
    .top-navbar-right .btn-icon {
        width: 36px;
        height: 36px;
        border: none;
        background: transparent;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s ease;
        color: #666;
        font-size: 1.1rem;
    }
    .top-navbar-right .btn-icon:hover {
        background: #eeeeee;
        color: #333;
    }
    .badge-notification {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #f44336;
        color: white;
        border-radius: 10px;
        padding: 2px 6px;
        font-size: 0.7rem;
        font-weight: 600;
    }
    .invoice-header {
        background: #11294b;
        color: white;
        padding: 8px;
        border-radius: 0;
      
        margin-bottom: 0.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 4px 12px rgba(17, 41, 75, 0.3);
        position: relative;
        overflow: hidden;
    }
    .invoice-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        background: linear-gradient(90deg, #c5a864 0%, rgba(197, 168, 100, 0.5) 100%);
    }
    .invoice-header h5 {
        font-size: 0.95rem;
        margin: 0;
        font-weight: 600;
    }
    .invoice-header-actions {
        display: flex;
        gap: 0.5rem;
    }
    .btn-new-invoice {
        background: rgba(255, 255, 255, 0.15);
        color: white;
        border: 1px solid rgba(197, 168, 100, 0.5);
        padding: 0.5rem 1.25rem;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.3s ease;
        backdrop-filter: blur(10px);
    }
    .btn-new-invoice:hover {
        background: rgba(255, 255, 255, 0.25);
        border-color: #c5a864;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    }
    .btn-save-invoice {
        background: linear-gradient(135deg, #c5a864 0%, #a68a4a 100%);
        color: white;
        border: none;
        padding: 0.5rem 1.25rem;
        border-radius: 6px;
        font-weight: 600;
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(197, 168, 100, 0.4);
    }
    .btn-save-invoice:hover {
        background: linear-gradient(135deg, #a68a4a 0%, #8b6f3a 100%);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(197, 168, 100, 0.5);
    }
    .billing-form .form-group {
        margin-bottom: 3px;
        display: flex;
        align-items: center;
    }
    .billing-form label {
        font-weight: 600;
        font-size: 11px;
        margin-bottom: 0;
        margin-right: 3px;
        color: #000000;
        letter-spacing: 0.01em;
        /* min-width: 90px; */
        flex-shrink: 0;
        /* text-align: right; */
text-transform: uppercase;
    }
    .billing-form .form-control,
    .billing-form .form-control-sm {
        font-size: 0.8rem;
        padding: 0.5rem 0.75rem;
        height: calc(1.5em + 1rem + 2px);
       
        border-radius: 6px;
        transition: all 0.2s ease;
        background: #ffffff;
        flex: 1;
        box-shadow: 0 1px 2px rgba(0,0,0,0.03);
    }
    .billing-form .form-control:focus,
    .billing-form .form-control-sm:focus {
        /* border-color: #c5a864;
        box-shadow: 0 0 0 3px rgba(197, 168, 100, 0.2), 0 2px 4px rgba(0,0,0,0.05); */
        outline: none;
        background: #ffffff;
    }
    .billing-form .form-control-sm {
        font-size: 0.75rem;
        padding: 0.35rem 0.5rem;
        height: calc(1.5em + 0.7rem + 2px);
    }
    .billing-form .form-group > div:not(.input-group):not(.d-flex) {
        flex: 1;
    }
    .billing-form .input-group {
        flex: 1;
    }
    .billing-form .form-group .d-flex {
        flex: 1;
    }
    .billing-form select.form-control,
    .billing-form select.form-control-sm {
        background-position: right 0.5rem center;
        background-repeat: no-repeat;
        background-size: 1.5em 1.5em;
        padding-right: 2.5rem;
    }
    .product-entry {
        background: linear-gradient(to bottom, #ffffff 0%, #f8fafc 100%);
        padding: 1.25rem;
        border-radius: 10px;
        border: 1.5px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        transition: all 0.3s ease;
    }
    .product-entry:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        border-color: #c5a864;
    }
    .product-entry .form-group {
        display: flex;
        align-items: center;
        margin-bottom: 0.4rem;
    }
    .product-entry label {
        font-weight: 600;
        font-size: 11px;
        margin-bottom: 0;
        margin-right: 0.5rem;
        color: #ffffff;
       
        flex-shrink: 0;
        
    }
    .add-item-link {
        text-align: center;
        margin-top: 0px;
        font-size: 0.85rem;
        color: #c5a864;
        font-weight: 600;
        cursor: pointer;
        padding: 0.75rem;
        border-radius: 8px;
        transition: all 0.3s ease;
        display: inline-block;
        width: 100%;
        border: 1.5px dashed #c5a864;
        background: rgba(197, 168, 100, 0.05);
    }
    .add-item-link:hover {
        background: linear-gradient(135deg, rgba(197, 168, 100, 0.15) 0%, rgba(197, 168, 100, 0.1) 100%);
        color: #8b6f3a;
        text-decoration: none;
        transform: translateY(-2px);
        border-color: #a68a4a;
        box-shadow: 0 4px 8px rgba(197, 168, 100, 0.2);
    }
    .summary-panel {
        background: linear-gradient(to bottom, #ffffff 0%, #f8fafc 100%);
        padding: 1rem;
        border-radius: 10px;
        position: sticky;
        top: 10px;
        font-size: 0.8rem;
        border: 1.5px solid #e2e8f0;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
    }
    .summary-panel:hover {
        box-shadow: 0 6px 16px rgba(0,0,0,0.12);
    }
    .summary-section {
        margin-bottom: 6px;
        padding-bottom: 6px;
        border-bottom: 1px solid #e2e8f0;
    }
    .summary-section:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }
    .summary-section h6 {
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
    .summary-section h6::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        width: 40px;
        height: 2px;
        background: linear-gradient(90deg, #c5a864 0%, transparent 100%);
    }
    .summary-row {
        display: flex;
        justify-content: space-between;
        
        font-size: 0.8rem;
        padding: 2px;
        align-items: center;
    }
    .summary-label {
    font-weight: 600;
    color: #000000;
    font-size: 11px;
    text-transform: uppercase;
}
    .summary-value {
        font-weight: 700;
        color: #1e293b;
        font-size: 0.85rem;
    }
    .payment-icons {
        display: flex;
        margin-bottom: 0.5rem;
        flex-wrap: wrap;
        align-items: center;
    }
    .payment-icons .payment-icon {
        margin-right: 0.3rem;
        margin-bottom: 0;
    }
    .payment-icon {
        width: 45px;
        height: 45px;
        border: 1.5px solid #e2e8f0;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 1.1rem;
        background: linear-gradient(to bottom, #ffffff 0%, #f8fafc 100%);
        color: #11294b;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        position: relative;
        overflow: hidden;
    }
    .payment-icon::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: linear-gradient(135deg, transparent 0%, rgba(255,255,255,0.3) 100%);
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    .payment-icon:hover::before {
        opacity: 1;
    }
    /* All payment icons have same base styling, hover to gold like button icons */
    .payment-cash:hover,
    .payment-bank:hover,
    .payment-cheque:hover,
    .payment-mobile:hover,
    .payment-card:hover,
    .payment-exchange:hover,
    .payment-jewelry:hover,
    .payment-diamond:hover,
    .payment-stone:hover,
    .payment-other:hover {
        background: #11294b;
        border-color: #c5a864;
        color: white;
        transform: translateY(-2px) scale(1.05);
        box-shadow: 0 4px 12px #c5a864;
    }
    .payment-icon.active {
        transform: scale(1.1);
        box-shadow: 0 6px 16px rgba(0,0,0,0.25);
        border-width: 3px;
    }
    .product-table {
        margin-bottom: 0.5rem;
        font-size: 0.75rem;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        border: 1px solid #e2e8f0;
    }
    .product-table thead th {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        font-weight: 700;
        font-size: 11px;
        padding: 8px;
        border-bottom: 2px solid #c5a864;
        color: #fff;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        position: relative;
    }
    .product-table thead th::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 2px;
        background: linear-gradient(90deg, transparent 0%, #c5a864 50%, transparent 100%);
    }
    .product-table tbody td {
        padding: 0.5rem;
        vertical-align: middle;
        font-size: 0.75rem;
        border-bottom: 1px solid #f1f5f9;
        color: #334155;
    }
    .product-table tbody tr:hover {
        background: linear-gradient(to right, rgba(197, 168, 100, 0.05) 0%, rgba(197, 168, 100, 0.02) 100%);
        transform: scale(1.001);
        transition: all 0.2s ease;
    }
    .table-sm th,
    .table-sm td {
        padding: 0.4rem 0.5rem;
        font-size: 0.75rem;
    }
    .table-bordered {
        border: 1px solid #e2e8f0;
    }
    .table-bordered th,
    .table-bordered td {
        border: 1px solid #e2e8f0;
    }
    .btn-purple {
        background: linear-gradient(135deg, #c5a864 0%, #a68a4a 100%);
        color: white;
        border: none;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 2px 6px rgba(197, 168, 100, 0.3);
    }
    .btn-purple:hover {
        background: linear-gradient(135deg, #a68a4a 0%, #8b6f3a 100%);
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(197, 168, 100, 0.4);
    }
    .btn-primary {
        background: #1a3a5c;
        border: none;
        font-weight: 600;
        transition: all 0.3s ease;
        box-shadow: 0 2px 6px rgba(33, 150, 243, 0.3);
    }
    .btn-primary:hover {
        background: linear-gradient(135deg, #1976d2 0%, #1565c0 100%);
        transform: translateY(-1px);
        box-shadow: 0 4px 10px rgba(33, 150, 243, 0.4);
    }
    .company-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 4px;
        background-color: #F8F6F1;
        border-radius: 0;
    }
    .company-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .company-logo {
        width: 42px;
        height: 42px;
        
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        color: #fff;
        font-weight: bold;
        
        transition: all 0.3s ease;
    }
    .company-logo:hover {
        transform: translateY(-2px) scale(1.05);
        box-shadow: 0 6px 16px rgba(197, 168, 100, 0.5);
    }
    .company-info h4 {
        margin: 0;
        color: #ffffff;
        font-weight: 700;
        font-size: 1.25rem;
        letter-spacing: 0.02em;
    }
    .company-info small {
        color: #fff;
        font-size: 0.8rem;
        font-weight: 500;
        opacity: 0.8;
    }
    .user-info {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        font-size: 0.85rem;
    }
    .user-info .btn-icon {
        width: 40px;
        height: 40px;
        
        background: linear-gradient(to bottom, #ffffff 0%, #f8fafc 100%);
        border: 1.5px solid #e2e8f0;
        transition: all 0.3s ease;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    .user-info .btn-icon:hover {
        background: linear-gradient(135deg, #c5a864 0%, #a68a4a 100%);
        border-color: #c5a864;
        color: white;
        transform: translateY(-2px) scale(1.05);
        box-shadow: 0 4px 12px rgba(197, 168, 100, 0.4);
    }
    .user-info i {
        font-size: 1.1rem !important;
        color: #11294b;
        transition: color 0.2s ease;
    }
    .user-info i:hover {
        color: #c5a864;
    }
    .pos-btn {
        background: linear-gradient(135deg, #c5a864 0%, #a68a4a 100%);
        color: white;
        border: none;
        padding: 0.5rem 1.5rem;
        border-radius: 10px;
        font-weight: 700;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(197, 168, 100, 0.4);
        letter-spacing: 0.5px;
        text-transform: uppercase;
    }
    .pos-btn:hover {
        background: linear-gradient(135deg, #a68a4a 0%, #8b6f3a 100%);
        transform: translateY(-2px) scale(1.02);
        box-shadow: 0 6px 16px rgba(197, 168, 100, 0.5);
    }
    .user-info .dropdown span {
        color: #fff;
        font-weight: 600;
        padding: 0.4rem 0.75rem;
        border-radius: 6px;
        transition: all 0.2s ease;
    }
    .user-info .dropdown span:hover {
        background: rgba(17, 41, 75, 0.1);
        color: #fff;
    }
    .user-info img {
        border: 2px solid #e0e0e0;
        transition: all 0.2s ease;
        cursor: pointer;
    }
    .user-info img:hover {
        border-color: #c5a864;
        box-shadow: 0 2px 4px rgba(197, 168, 100, 0.2);
    }
    .badge-notification {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #f44336;
        color: white;
        border-radius: 10px;
        padding: 2px 6px;
        font-size: 0.7rem;
        font-weight: 600;
        box-shadow: 0 2px 4px rgba(244, 67, 54, 0.3);
    }
    .user-info .dropdown span {
        color: #fff;
        font-weight: 600;
        padding: 0.4rem 0.75rem;
        border-radius: 6px;
        transition: all 0.2s ease;
    }
    .user-info .dropdown span:hover {
        background: rgba(17, 41, 75, 0.1);
        color: #c5a864;
    }
    .user-info img {
        border: 2px solid #e0e0e0;
        transition: all 0.2s ease;
    }
    .user-info img:hover {
        border-color: #c5a864;
        box-shadow: 0 2px 4px rgba(197, 168, 100, 0.2);
    }
    /* User Dropdown Menu Styles */
    .user-dropdown {
        position: relative;
    }
    .user-dropdown-menu {
        position: absolute !important;
        top: 100% !important;
        right: 0 !important;
        left: auto !important;
        min-width: 220px;
        background: #ffffff !important;
        border: 1px solid #e0e0e0 !important;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
        padding: 0.5rem 0 !important;
        margin-top: 8px !important;
        opacity: 0 !important;
        visibility: hidden !important;
        transform: translateY(-10px);
        transition: all 0.3s ease !important;
        z-index: 1000 !important;
        list-style: none !important;
        margin-left: 0 !important;
        display: block !important;
        pointer-events: none;
    }
    .user-dropdown.show .user-dropdown-menu {
        opacity: 1 !important;
        visibility: visible !important;
        transform: translateY(0) !important;
        display: block !important;
        pointer-events: auto;
    }
    .user-dropdown-menu li {
        list-style: none !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    .user-dropdown-menu .dropdown-item {
        display: block !important;
        padding: 0.6rem 1.25rem !important;
        color: #000000 !important;
        font-size: 0.85rem !important;
        text-decoration: none !important;
        transition: all 0.2s ease;
        border-bottom: none !important;
        white-space: nowrap;
        background: transparent !important;
    }
    .user-dropdown-menu .dropdown-item:hover {
        background: rgba(17, 41, 75, 0.1) !important;
        color: #11294b !important;
    }
    .user-dropdown-menu .dropdown-item i {
        margin-right: 0.5rem;
        width: 16px;
        text-align: center;
        font-size: 0.9rem;
    }
    .user-dropdown-menu .dropdown-divider {
        height: 1px !important;
        margin: 0.5rem 0 !important;
        overflow: hidden;
        background-color: #e0e0e0 !important;
        border: none !important;
        padding: 0 !important;
        display: block !important;
    }
    .card {
        margin-bottom: 0.75rem !important;
        border: 1.5px solid #e2e8f0;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        background: #ffffff;
        transition: all 0.3s ease;
        overflow: visible;
    }
    .card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        border-color: #c5a864;
    }
    .card-body {
        padding: 5px 5px 5px 5px;
    }
    .mb-4 {
        margin-bottom: 0.5rem !important;
    }
    .mb-3 {
        margin-bottom: 0.5rem !important;
    }
    .mb-2 {
        margin-bottom: 0.3rem !important;
    }
    h6 {
        font-size: 0.85rem;
        margin-bottom: 0.5rem;
    }
    .input-group-sm > .input-group-append > .input-group-text,
    .input-group-sm > .input-group-prepend > .input-group-text {
        padding: 0.25rem 0.4rem;
        font-size: 0.75rem;
    }
    .input-group-sm > .form-control {
        padding: 0.25rem 0.4rem;
        font-size: 0.75rem;
    }
    .layout-footer {
        display: block !important;
        position: fixed !important;
        bottom: 0 !important;
        left: 0 !important;
        right: 0 !important;
        width: 100% !important;
        z-index: 1000 !important;
        margin: 0 !important;
        box-shadow: 0 -4px 12px rgba(0,0,0,0.1) !important;
        backdrop-filter: blur(10px);
    }
    .layout-footer .container-fluid {
        padding-left: 15px !important;
        padding-right: 15px !important;
    }
    .layout-footer i {
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .layout-footer i:hover {
        color: #c5a864 !important;
        transform: scale(1.1);
    }
    .container-fluid {
        padding-top: 0 !important;
        padding-bottom: 0 !important;
    }
    .btn-sm {
        padding: 0.35rem 0.75rem;
        font-size: 0.75rem;
        line-height: 1.5;
        border-radius: 6px;
        font-weight: 600;
    }
    .form-check-input {
        width: 1.1em;
        height: 1.1em;
        margin-top: 0.15em;
        border: 2px solid #cbd5e1;
        border-radius: 4px;
    }
    .form-check-input:checked {
        background-color: #c5a864;
        border-color: #c5a864;
    }
    .form-check-label {
        font-size: 0.75rem;
        color: #ffffff;
        font-weight: 500;
        margin-left: 0.5rem;
    }
    .input-group-text {
        background: linear-gradient(to bottom, #f8fafc 0%, #f1f5f9 100%);
        border: 1.5px solid #e2e8f0;
        color: #64748b;
        transition: all 0.2s ease;
    }
    .input-group-text:hover {
        background: linear-gradient(to bottom, #f1f5f9 0%, #e2e8f0 100%);
        border-color: #c5a864;
        color: #11294b;
    }
    .input-group-sm > .form-control {
        border-right: none;
    }
    .input-group-sm > .input-group-append > .input-group-text {
        border-left: none;
    }
    /* Grand Total Highlight */
    .summary-value[style*="color: #7b1fa2"] {
        color: #c5a864 !important;
        font-size: 1.1rem !important;
        font-weight: 800 !important;
    }
    /* Enhanced form inputs */
    input[type="date"] {
        position: relative;
    }
    input[type="date"]::-webkit-calendar-picker-indicator {
        cursor: pointer;
        opacity: 0.6;
        transition: opacity 0.2s ease;
    }
    input[type="date"]::-webkit-calendar-picker-indicator:hover {
        opacity: 1;
    }
    /* Better scrollbar */
    .layout-content::-webkit-scrollbar {
        width: 8px;
    }
    .layout-content::-webkit-scrollbar-track {
        background: #f1f5f9;
    }
    .layout-content::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }
    .layout-content::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
    /* Empty state styling */
    .text-muted {
        color: #94a3b8 !important;
        font-style: italic;
    }
    /* Enhanced focus states */
    .form-control:focus,
    .form-control-sm:focus,
    select:focus {
        /* border-color: #c5a864;
        box-shadow: 0 0 0 3px rgba(197, 168, 100, 0.2); */
    }
    /* Better spacing for summary inputs */
    .summary-section .form-control-sm {
        /* border: 1px solid #d0d0d0; */
        border-radius: 4px;
    }
    .summary-section .form-control-sm:focus {
        /* border-color: #c5a864;
        box-shadow: 0 0 0 3px rgba(197, 168, 100, 0.2); */
    }
    .billing-form .form-control,
    .billing-form .form-control-sm {
        
        border-radius: 4px;
    }
    .billing-form .form-control:focus,
    .billing-form .form-control-sm:focus {
        /* border-color: #c5a864;
        box-shadow: 0 0 0 3px rgba(197, 168, 100, 0.2); */
    }
    /* Table Settings Button */
    .table-header-wrapper {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.5rem;
        padding: 0 0.5rem;
    }
    .table-settings-btn {
        background: #11294b;
        border: 1px solid #e0e0e0;
        border-radius: 6px;
        padding: 0.4rem 0.75rem;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.8rem;
        color: #ffffff;
        font-weight: 500;
    }
    .table-settings-btn:hover {
        background: #eeeeee;
        border-color: #c5a864;
        color: #11294b;
    }
    .table-settings-btn i {
        font-size: 12px;
    }
    .table-settings-dropdown {
        position: absolute;
        right: 0;
        top: 100%;
        margin-top: 0.5rem;
        background: #ffffff;
        border: 1.5px solid #e2e8f0;
        border-radius: 10px;
        box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        padding: 1rem;
        min-width: 240px;
        max-width: 300px;
        max-height: 450px;
        overflow-y: auto;
        overflow-x: hidden;
        z-index: 10001;
        display: none;
    }
    .table-settings-dropdown::-webkit-scrollbar {
        width: 6px;
    }
    .table-settings-dropdown::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 3px;
    }
    .table-settings-dropdown::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 3px;
    }
    .table-settings-dropdown::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
    .table-settings-dropdown.show {
        display: block;
    }
    .table-settings-dropdown h6 {
        margin: 0 0 0.75rem 0;
        font-size: 0.85rem;
        font-weight: 700;
        color: #11294b;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .table-settings-item {
        display: flex;
        align-items: center;
        /* padding: 0.5rem 0; */
        cursor: pointer;
        transition: all 0.2s ease;
    }
    .table-settings-item:hover {
        background: rgba(17, 41, 75, 0.05);
        padding-left: 0.5rem;
    }
    .table-settings-item input[type="checkbox"] {
        margin-right: 0.75rem;
        cursor: pointer;
    }
    .table-settings-item label {
        margin: 0;
        cursor: pointer;
        font-size: 0.85rem;
        color: #000;
        font-weight: 500;
        flex: 1;
    }
    .table-settings-wrapper {
        position: relative;
        z-index: 10000;
    }
    /* Ensure dropdown is visible outside card boundaries */
    .card-body {
        position: relative;
        border-radius: 10px;
        border: 1px solid #c5a864;
    }
    .table-header-wrapper {
        position: relative;
        z-index: 1;
    }
    /* Make the card containing table settings allow overflow */
    .card.mb-4:last-of-type,
    .card:has(.table-settings-wrapper) {
        overflow: visible !important;
    }
    .card-body:has(.table-settings-wrapper) {
        overflow: visible !important;
    }
    .product-table th[data-column],
    .product-table td[data-column] {
        display: table-cell;
    }
    .product-table th[data-column].hidden,
    .product-table td[data-column].hidden {
        display: none;
    }
    /* Column Drag and Drop Styles */
    .product-table thead th {
        position: relative;
        user-select: none;
    }
    .product-table thead th.draggable-column {
        cursor: move;
        transition: all 0.2s ease;
    }
    .product-table thead th.draggable-column:hover {
        background: rgba(197, 168, 100, 0.1);
    }
    .product-table thead th.dragging-column {
        /* opacity: 0.5; */
        background: rgba(197, 168, 100, 0.2) !important;
        border: 2px dashed #c5a864;
        color:#11294b;
    }
    .product-table thead th.drag-over-column {
        border-left: 2px solid #c5a864;
        background: rgba(197, 168, 100, 0.15) !important;
    }
    .product-table thead th.drag-over-column-right {
        border-right: 2px solid #c5a864;
        background: rgba(197, 168, 100, 0.15) !important;
    }
    .column-drag-handle {
        position: absolute;
        left: 4px;
        top: 50%;
        transform: translateY(-50%);
        cursor: grab;
        color: #94a3b8;
        font-size: 0.85rem;
        opacity: 0;
        transition: opacity 0.2s ease;
        pointer-events: none;
    }
    .product-table thead th.draggable-column:hover .column-drag-handle {
        opacity: 1;
    }
    .column-drag-handle:active {
        cursor: grabbing;
    }
    .product-table tbody tr.no-drag {
        cursor: default;
    }
    
    /* Category Tabs */
    .product-category-tabs {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1rem;
        border-bottom: 2px solid #e2e8f0;
        padding-bottom: 0.5rem;
        flex-wrap: wrap;
    }
    .category-tab-btn {
        padding: 0.5rem 1.25rem;
        border: none;
        background: #F8F6F1;
        color: #11294b;
        font-weight: 600;
        font-size: 13px;
        cursor: pointer;
        border-radius: 6px 6px 6px 6px;
        transition: all 0.3s ease;
        position: relative;
        border-bottom: 3px solid #a68a4a;
    }
    .category-tab-btn:hover {
        color: #11294b;
        background: rgba(17, 41, 75, 0.05);
    }
    .category-tab-btn.active {
        color: #11294b;
        background: #c5a864;
        border-bottom-color: #a68a4a;
        box-shadow: 0 2px 4px rgba(197, 168, 100, 0.3);
    }
    
    /* Product Selection Modal */
    #productSelectionModal .modal-content {
        border-radius: 10px;
        border: none;
        box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        max-height: 95vh;
        display: flex;
        flex-direction: column;
    }
    #productSelectionModal .modal-header {
        border-radius: 10px 10px 0 0;
        flex-shrink: 0;
    }
    #productSelectionModal .modal-body {
        overflow-y: auto;
        max-height: calc(95vh - 120px);
        padding: 1.5rem;
    }
    #productSelectionModal .modal-body::-webkit-scrollbar {
        width: 8px;
    }
    #productSelectionModal .modal-body::-webkit-scrollbar-track {
        background: #f1f5f9;
    }
    #productSelectionModal .modal-body::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }
    #productSelectionModal .modal-body::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
    #productSelectionModal .form-group label {
        font-size: 12px;
        font-weight: 600;
        color: #000;
        margin-bottom: 0.25rem;
    }
    #productSelectionModal .form-control-sm {
        font-size: 0.75rem;
        padding: 0.35rem 0.5rem;
        height: calc(1.5em + 0.7rem + 2px);
    }
    #productListTable th.hidden,
    #productListTable td.hidden {
        display: none !important;
    }
    #productListTable th,
    #productListTable td {
        white-space: nowrap;
        padding: 0.5rem 0.4rem;
        vertical-align: middle;
    }
    /* Sticky columns on the right - Net Amt+Tax, Reverse, Actions */
    #productListTable th[data-column="actions"],
    #productListTable td[data-column="actions"] {
        position: sticky;
        right: 0;
        background: #fff;
        z-index: 9;
        box-shadow: -2px 0 4px rgba(0,0,0,0.1);
    }
    #productListTable thead th[data-column="actions"] {
        background: #f8fafc;
        z-index: 10;
    }
    #productListTable tbody tr:hover td[data-column="actions"] {
        background: #f8fafc;
    }
    #productListTable th[data-column="reverse"],
    #productListTable td[data-column="reverse"] {
        position: sticky;
        right: 80px; /* Width of actions column */
        background: #fff;
        z-index: 8;
        box-shadow: -2px 0 4px rgba(0,0,0,0.1);
    }
    #productListTable thead th[data-column="reverse"] {
        background: #f8fafc;
        z-index: 9;
    }
    #productListTable tbody tr:hover td[data-column="reverse"] {
        background: #f8fafc;
    }
    #productListTable th[data-column="net-amt-tax"],
    #productListTable td[data-column="net-amt-tax"] {
        position: sticky;
        right: 160px; /* Width of actions (80px) + reverse (80px) */
        background: #fff;
        z-index: 7;
        box-shadow: -2px 0 4px rgba(0,0,0,0.1);
    }
    #productListTable thead th[data-column="net-amt-tax"] {
        background: #f8fafc;
        z-index: 8;
    }
    #productListTable tbody tr:hover td[data-column="net-amt-tax"] {
        background: #f8fafc;
    }
    #productListTable thead th {
        background: #f8fafc;
        font-weight: 700;
        font-size: 0.7rem;
        color: #fff;
        border-bottom: 2px solid #c5a864;
    }
    #productListTable tbody tr:hover {
        background: #f8fafc;
    }
    /* Ensure sticky columns work in tbody cells */
    #productListTable tbody td[data-column="actions"] {
        position: sticky;
        right: 0;
        background: #fff;
        z-index: 9;
        box-shadow: -2px 0 4px rgba(0,0,0,0.1);
    }
    #productListTable tbody td[data-column="reverse"] {
        position: sticky;
        right: 80px;
        background: #fff;
        z-index: 8;
        box-shadow: -2px 0 4px rgba(0,0,0,0.1);
    }
    #productListTable tbody td[data-column="net-amt-tax"] {
        position: sticky;
        right: 160px;
        background: #fff;
        z-index: 7;
        box-shadow: -2px 0 4px rgba(0,0,0,0.1);
    }
    #productListTable tbody tr:hover td[data-column="actions"],
    #productListTable tbody tr:hover td[data-column="reverse"],
    #productListTable tbody tr:hover td[data-column="net-amt-tax"] {
        background: #f8fafc;
    }
    #productListTable tbody tr.selected {
        background: #fff3cd !important;
    }
    #productListTable tbody tr.selected:hover {
        background: #ffe69c !important;
    }
    #productListTable input.form-control-sm,
    #productListTable select.form-control-sm {
        border: 1px solid #e2e8f0;
        padding: 0.25rem 0.4rem;
        font-size: 0.7rem;
    }
    #productListTable input.form-control-sm:focus,
    #productListTable select.form-control-sm:focus {
        border-color: #c5a864;
        outline: none;
        box-shadow: 0 0 0 2px rgba(197, 168, 100, 0.2);
    }
    #productListTable tbody tr.product-row.selected {
        background-color: #fff3cd !important;
    }
    #productListTable tbody tr.product-row:hover {
        background-color: #f8f9fa;
    }
    #productListTable .product-checkbox {
        cursor: pointer;
    }
    #productListTable tbody tr {
        cursor: pointer;
        transition: all 0.2s ease;
    }
    #productListTable tbody tr:hover {
        background: rgba(197, 168, 100, 0.1);
    }
    #productListTable tbody tr.selected {
        background: rgba(17, 41, 75, 0.1);
        border-left: 3px solid #c5a864;
    }
    
    /* Product Select Cell */
    .product-select-cell {
        position: relative;
        transition: all 0.2s ease;
    }
    .product-select-cell:hover {
        background: rgba(17, 41, 75, 0.05);
        border-radius: 4px;
    }
    .product-select-cell .product-name-display {
        display: inline-block;
    }
    
    /* Making Column Sub-headers */
    .product-table thead th[data-column="making"] {
        text-align: center;
        vertical-align: top;
        padding-top: 0.5rem;
    }
    .product-table thead th[data-column="making"] > div {
        display: flex;
        flex-direction: column;
        gap: 2px;
        font-size: 0.7rem;
        margin-top: 0.25rem;
    }
    
    /* Editable field styles */
    .product-table .editable-field {
        transition: all 0.2s ease;
    }
    .product-table .editable-field:focus {
        outline: none;
        background: #fff !important;
        border: 1px solid #11294b !important;
        box-shadow: 0 0 0 2px rgba(17, 41, 75, 0.1);
    }
    
    /* Product Table Row Actions */
    .product-table tbody td .action-btns {
        display: flex;
        gap: 0.25rem;
        justify-content: center;
    }
    .product-table tbody td .action-btns button {
        padding: 0.25rem 0.5rem;
        border: none;
        background: transparent;
        cursor: pointer;
        border-radius: 4px;
        transition: all 0.2s ease;
    }
    .product-table tbody td .action-btns button:hover {
        background: #f1f5f9;
    }
    .product-table tbody td .action-btns .btn-edit {
        color: #2196F3;
    }
    .product-table tbody td .action-btns .btn-delete {
        color: #f44336;
    }
    
    /* Input fields in table */
    .product-table tbody td input,
    .product-table tbody td select {
        width: 100%;
        border: 1px solid #e2e8f0;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
        font-size: 0.75rem;
    }
    .product-table tbody td input:focus,
    .product-table tbody td select:focus {
        outline: none;
        border-color: #c5a864;
        box-shadow: 0 0 0 2px rgba(197, 168, 100, 0.2);
    }
    /* Right Side Modal Styles */
    .modal.fade.right .modal-dialog {
        transform: translateX(100%);
        transition: transform 0.3s ease-out;
    }
    .modal.fade.right.show .modal-dialog {
        transform: translateX(0);
    }
    .modal-dialog-right {
        position: fixed;
        right: 0;
        top: 0;
        margin: 0;
        height: 100vh;
    }
    .modal.fade.right {
        padding-right: 0 !important;
    }
    .modal.fade.right .modal-backdrop {
        background-color: rgba(0, 0, 0, 0.5);
    }
    .add-product-icon:hover {
        color: #c5a864;
        transform: scale(1.2);
        transition: all 0.2s;
    }
    
    /* Product Creation Modal Styles - Matching product-opening.php */
    #productCreationModal .sec-title {
        font-weight: 700;
        font-size: 0.9rem;
        color: #11294b;
        margin: 0 0 12px 0;
        padding-bottom: 6px;
    }
    
    #productCreationModal .form-row-custom {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin-bottom: 12px;
    }
    
    #productCreationModal .form-row-custom .form-group {
        margin-bottom: 0;
    }
    
    #productCreationModal .form-row-custom label {
        font-size: 0.8rem;
        font-weight: 600;
        color: #ffffff;
        margin-bottom: 4px;
        display: block;
    }
    
    #productCreationModal .form-row-custom .form-control,
    #productCreationModal .form-row-custom select {
        height: 32px;
        font-size: 0.8rem;
        padding: 6px 10px;
        border: 1px solid #e2e8f0;
        border-radius: 4px;
    }
    
    #productCreationModal .select-with-add {
        position: relative;
    }
    
    #productCreationModal .select-with-add .add-icon {
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: #c5a864;
        font-size: 0.9rem;
    }
    
    #productCreationModal .branch-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        align-items: center;
        min-height: 32px;
        padding: 4px;
        border: 1px solid #e2e8f0;
        border-radius: 4px;
        background: #fff;
    }
    
    #productCreationModal .branch-tag {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 8px;
        background: #e0e7ff;
        color: #4338ca;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 500;
    }
    
    #productCreationModal .branch-tag .remove-tag {
        cursor: pointer;
        color: #ef4444;
        font-weight: bold;
        font-size: 12px;
        line-height: 1;
    }
    
    #productCreationModal .branch-tag .remove-tag:hover {
        color: #dc2626;
    }
    
    #productCreationModal .add-branch-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        border: 1px dashed #cbd5e1;
        border-radius: 4px;
        cursor: pointer;
        color: #c5a864;
    }
    
    #productCreationModal .add-branch-btn:hover {
        border-color: #c5a864;
        background: #f0f4ff;
    }
    
    #productCreationModal .checkbox-custom {
        display: flex;
        align-items: center;
        margin-top: 24px;
    }
    
    #productCreationModal .checkbox-custom label {
        display: flex;
        align-items: center;
        gap: 6px;
        margin: 0;
        font-size: 0.8rem;
        font-weight: 500;
        color: #ffffff;
        cursor: pointer;
    }
    
    #productCreationModal .tax-table-wrapper {
        min-width: 350px;
    }
    
    #productCreationModal .tax-table-wrapper table th {
        font-size: 0.75rem;
        font-weight: 700;
        color: #ffffff;
        background: #f8fafc;
        padding: 8px 6px;
        border: 1px solid #e2e8f0;
    }
    
    #productCreationModal .tax-table-wrapper table td {
        padding: 8px 6px;
        border: 1px solid #e2e8f0;
        font-size: 0.8rem;
        vertical-align: middle;
    }
    
    #productCreationModal .tax-table-wrapper table td:first-child {
        display: flex;
        align-items: center;
        gap: 6px;
    }
    
    #productCreationModal .pc-table {
        font-size: 0.75rem;
        margin-bottom: 0;
    }
    
    #productCreationModal .pc-table th {
        background: #f8fafc;
        font-weight: 600;
        color: #ffffff;
        padding: 8px 6px;
        border: 1px solid #e2e8f0;
        white-space: nowrap;
    }
    
    #productCreationModal .pc-table td {
        padding: 6px;
        border: 1px solid #e2e8f0;
        vertical-align: middle;
    }
    
    #productCreationModal .pc-table input.form-control-sm {
        height: 28px;
        font-size: 0.75rem;
        padding: 4px 8px;
        border: 1px solid #e2e8f0;
        width: 100%;
        min-width: 100px;
        max-width: none;
    }
    
    #productCreationModal .pc-table tbody td {
        min-width: 100px;
    }
    
    #productCreationModal .pc-table tbody td[data-col="hsn"] {
        min-width: 80px;
    }
    
    #productCreationModal .pc-table tbody td[data-col="sku"] {
        min-width: 120px;
    }
    
    #productCreationModal .pc-table tbody td[data-col="making"] {
        min-width: 100px;
    }
    
    #productCreationModal .pc-table tbody td[data-col="diamond"] {
        min-width: 100px;
    }
    
    #productCreationModal .pc-table tbody td[data-col="qty"] {
        min-width: 60px;
    }
    
    #productCreationModal .pc-table tbody td[data-col="digits"],
    #productCreationModal .pc-table tbody td[data-col="prefix"] {
        min-width: 100px;
    }
    
    #productCreationModal .pc-table tbody td[data-col="stylecode"] {
        min-width: 100px;
    }
    
    #productCreationModal .pc-scroll {
        overflow-x: auto !important;
        overflow-y: auto !important;
    }
    
    #productCreationModal .pc-table {
        width: max-content;
        min-width: 100%;
        white-space: nowrap;
    }
    
    #productCreationModal .pc-table thead th {
        position: sticky;
        top: 0;
        background: #f7f8ff;
        z-index: 5;
        font-size: 0.75rem;
        font-weight: 700;
        color: #ffffff;
        padding: 8px 10px;
        border: 1px solid #e2e8f0;
        text-align: center;
        vertical-align: middle;
        white-space: nowrap;
    }
    
    #productCreationModal .pc-table tbody td {
        font-size: 0.8rem;
        padding: 6px 8px;
        border: 1px solid #e2e8f0;
        vertical-align: middle;
        white-space: nowrap;
    }
    
    #productCreationModal .columns-dropdown {
        position: absolute;
        top: 100%;
        right: 0;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 1000;
        min-width: 250px;
        max-width: 300px;
        display: none;
        margin-top: 8px;
    }
    
    #productCreationModal .columns-dropdown.show {
        display: block !important;
    }
    
    #productCreationModal .columns-dropdown-header {
        padding: 12px 16px;
        border-bottom: 1px solid #e2e8f0;
        font-weight: 600;
        font-size: 0.85rem;
        color: #ffffff;
    }
    
    #productCreationModal .columns-dropdown-search {
        padding: 8px 16px;
        border-bottom: 1px solid #e2e8f0;
    }
    
    #productCreationModal .columns-dropdown-search input {
        width: 100%;
        padding: 6px 10px;
        border: 1px solid #e2e8f0;
        border-radius: 4px;
        font-size: 0.8rem;
    }
    
    #productCreationModal .columns-dropdown-list {
        max-height: 300px;
        overflow-y: auto;
        padding: 8px 0;
    }
    
    #productCreationModal .columns-dropdown-item {
        padding: 8px 16px;
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        transition: background 0.2s;
    }
    
    #productCreationModal .columns-dropdown-item:hover {
        background: #f8fafc;
    }
    
    #productCreationModal .columns-dropdown-item input[type="checkbox"] {
        width: 16px;
        height: 16px;
        cursor: pointer;
    }
    
    #productCreationModal .columns-dropdown-item label {
        margin: 0;
        cursor: pointer;
        font-size: 0.8rem;
        color: #ffffff;
        flex: 1;
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
</style>
</head>

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
                    <!-- Company Header -->
                    <?php include 'sidebar.php';?>

                    <!-- Top Navigation Tabs -->
                   

                    <div class="row">
                        <!-- Main Content Area -->
                        <div class="col-lg-12" >
                            <!-- Transaction Details Form -->
                            

                            <!-- Product Entry Section -->
                            <div class="card mb-4">
                                <div class="card-body product-entry" style="padding: 1.5rem;">
                                    <!-- Category Tabs -->
                                    <div class="product-category-tabs" style="display: flex; gap: 0.5rem; margin-bottom: 1rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">
                                        <?php 
                                        $first_metal = true;
                                        foreach($metals as $metal): 
                                            $tab_class = $first_metal ? 'active' : '';
                                            $tab_id = 'modal-tab-' . strtolower(str_replace([' ', '&'], ['-', ''], $metal['display_name']));
                                        ?>
                                        <button type="button" class="category-tab-btn <?php echo $tab_class; ?>" data-metal-id="<?php echo $metal['id']; ?>" data-metal-name="<?php echo htmlspecialchars($metal['display_name']); ?>" id="<?php echo $tab_id; ?>">
                                            <?php echo htmlspecialchars($metal['display_name']); ?>
                                        </button>
                                        <?php 
                                        $first_metal = false;
                                        endforeach; 
                                        ?>
                                    </div>
                                    
                                    <!-- Item Entry Fields Above Table -->
                                    <div class="row mb-3" style="background: transparent; padding: 0px; border-radius: 0px;">
                                        <div class="col-md-2">
                                            <div class="form-group mb-2">
                                                <label>Barcode</label>
                                                <div class="input-group input-group-sm">
                                                    <input type="text" class="form-control form-control-sm" id="modalProductBarcode" placeholder="Scan or enter">
                                                    <div class="input-group-append">
                                                        <span class="input-group-text" style="background: #f8fafc;"><i class="feather icon-image"></i></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group mb-2">
                                                <label>Code</label>
                                                <input type="text" class="form-control form-control-sm" id="modalProductCode" placeholder="Code">
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group mb-2">
                                                <label>Des. No.</label>
                                                <div class="input-group input-group-sm">
                                                    <input type="text" class="form-control form-control-sm" id="modalProductDesignNo" placeholder="Design number">
                                                    <div class="input-group-append">
                                                        <span class="input-group-text" style="background: #f8fafc;"><i class="feather icon-chevron-down"></i></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-1">
                                            <div class="form-group mb-2">
                                                <label>&nbsp;</label>
                                                <div class="input-group input-group-sm">
                                                    <input type="text" class="form-control form-control-sm" id="modalProductQty" value="1" min="1" step="0.01">
                                                    <div class="input-group-append">
                                                        <button class="btn btn-sm" type="button" style="background: #f8fafc; border: 1px solid #e2e8f0;"><i class="feather icon-refresh-cw"></i></button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <div class="form-group mb-2">
                                                <label>&nbsp;</label>
                                                <div class="d-flex" style="gap: 0.5rem; align-items: center;">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="modalMetalUnfix">
                                                        <label class="form-check-label" for="modalMetalUnfix" style="font-size: 0.75rem;">Metal Unfix</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" id="modalUnfix">
                                                        <label class="form-check-label" for="modalUnfix" style="font-size: 0.75rem;">UnFix</label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Column Visibility Settings -->
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 style="margin: 0; font-size: 0.9rem; font-weight: 700; color: #1e293b;">Product Selection</h6>
                                        <div class="d-flex align-items-center" style="gap: 0.5rem;">
                                            <button class="table-settings-btn" id="addProductRowBtn" style="background: #c5a864; color: #fff; border: none; padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer; font-size: 0.85rem; display: flex; align-items: center; gap: 0.25rem;">
                                                <i class="feather icon-plus"></i> Add Product
                                            </button>
                                            <div class="table-settings-wrapper">
                                                <button class="table-settings-btn" id="modalTableSettingsBtn">
                                                    <i class="feather icon-settings"></i> Show/Hide Columns
                                                </button>
                                            <div class="table-settings-dropdown" id="modalTableSettingsDropdown">
                                                <h6>Show/Hide Columns</h6>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-checkbox" data-column="checkbox" checked>
                                                    <label for="modal-col-checkbox">Select</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-name" data-column="name" checked>
                                                    <label for="modal-col-name">Name</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-short-code" data-column="short-code" checked>
                                                    <label for="modal-col-short-code">Short Code</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-rfid" data-column="rfid" checked>
                                                    <label for="modal-col-rfid">RFIDCode</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-voucher-type" data-column="voucher-type" checked>
                                                    <label for="modal-col-voucher-type">Voucher Type</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-barcode" data-column="barcode" checked>
                                                    <label for="modal-col-barcode">Barcode</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-design-no" data-column="design-no" checked>
                                                    <label for="modal-col-design-no">Design No</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-huid" data-column="huid" checked>
                                                    <label for="modal-col-huid">HUID No</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-category" data-column="category" checked>
                                                    <label for="modal-col-category">Category</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-calculation" data-column="calculation" checked>
                                                    <label for="modal-col-calculation">Calculation</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-location" data-column="location" checked>
                                                    <label for="modal-col-location">Location</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-quantity" data-column="quantity" checked>
                                                    <label for="modal-col-quantity">Quantity</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-carat" data-column="carat" checked>
                                                    <label for="modal-col-carat">Carat</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-pkt-wt" data-column="pkt-wt" checked>
                                                    <label for="modal-col-pkt-wt">Pkt. Wt.</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-pkt-less-wt" data-column="pkt-less-wt" checked>
                                                    <label for="modal-col-pkt-less-wt">Pkt. Less Wt.</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-requested-purity" data-column="requested-purity" checked>
                                                    <label for="modal-col-requested-purity">Requested Purity</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-requested" data-column="requested" checked>
                                                    <label for="modal-col-requested">Requested</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-gross-wt" data-column="gross-wt" checked>
                                                    <label for="modal-col-gross-wt">Gross Wt.</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-less-wt" data-column="less-wt" checked>
                                                    <label for="modal-col-less-wt">Less Wt.</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-gold-loss1" data-column="gold-loss1" checked>
                                                    <label for="modal-col-gold-loss1">Gold Loss 1</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-gold-loss2" data-column="gold-loss2" checked>
                                                    <label for="modal-col-gold-loss2">Gold Loss 2</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-setting-charge" data-column="setting-charge" checked>
                                                    <label for="modal-col-setting-charge">Setting Charge</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-net-wt" data-column="net-wt" checked>
                                                    <label for="modal-col-net-wt">Net Wt.</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-purity" data-column="purity" checked>
                                                    <label for="modal-col-purity">Purity</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-purity-wt" data-column="purity-wt" checked>
                                                    <label for="modal-col-purity-wt">Purity Wt.</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-wastage-per" data-column="wastage-per" checked>
                                                    <label for="modal-col-wastage-per">Wastage Per.</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-wastage-wt" data-column="wastage-wt" checked>
                                                    <label for="modal-col-wastage-wt">Wastage Wt.</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-final-wt" data-column="final-wt" checked>
                                                    <label for="modal-col-final-wt">Final Wt.</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-alloy-wt" data-column="alloy-wt" checked>
                                                    <label for="modal-col-alloy-wt">Alloy Wt.</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-rate" data-column="rate" checked>
                                                    <label for="modal-col-rate">Rate</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-metal-value" data-column="metal-value" checked>
                                                    <label for="modal-col-metal-value">Metal Value</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-metal-cost" data-column="metal-cost" checked>
                                                    <label for="modal-col-metal-cost">Metal Cost</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-amount" data-column="amount" checked>
                                                    <label for="modal-col-amount">Amount</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-discount-type" data-column="discount-type" checked>
                                                    <label for="modal-col-discount-type">Discount Type</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-discount-per" data-column="discount-per" checked>
                                                    <label for="modal-col-discount-per">Discount Per.</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-discount-amount" data-column="discount-amount" checked>
                                                    <label for="modal-col-discount-amount">Discount Amount</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-discount" data-column="discount" checked>
                                                    <label for="modal-col-discount">Discount</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-making-type" data-column="making-type" checked>
                                                    <label for="modal-col-making-type">Making Type</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-making-rate" data-column="making-rate" checked>
                                                    <label for="modal-col-making-rate">Making Rate</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-making-amount" data-column="making-amount" checked>
                                                    <label for="modal-col-making-amount">Making Amount</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-making-cost" data-column="making-cost" checked>
                                                    <label for="modal-col-making-cost">Making Cost</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-min-price" data-column="min-price" checked>
                                                    <label for="modal-col-min-price">Minimum Price</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-stone-charge-type" data-column="stone-charge-type" checked>
                                                    <label for="modal-col-stone-charge-type">Stone Charge Type</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-stone-weight" data-column="stone-weight" checked>
                                                    <label for="modal-col-stone-weight">Stone Weight</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-stone-rate" data-column="stone-rate" checked>
                                                    <label for="modal-col-stone-rate">Stone Rate</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-stone-amount" data-column="stone-amount" checked>
                                                    <label for="modal-col-stone-amount">Stone Amount</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-stone-cost" data-column="stone-cost" checked>
                                                    <label for="modal-col-stone-cost">Stone Cost</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-diamond-amount" data-column="diamond-amount" checked>
                                                    <label for="modal-col-diamond-amount">Diamond Amount</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-purchase-amount" data-column="purchase-amount" checked>
                                                    <label for="modal-col-purchase-amount">Purchase Amount</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-sale-amount" data-column="sale-amount" checked>
                                                    <label for="modal-col-sale-amount">Sale Amount</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-net-amt" data-column="net-amt" checked>
                                                    <label for="modal-col-net-amt">Net Amt</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-tax" data-column="tax" checked>
                                                    <label for="modal-col-tax">Tax</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-other-charge-type" data-column="other-charge-type" checked>
                                                    <label for="modal-col-other-charge-type">Other Charge Type</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-other-weight" data-column="other-weight" checked>
                                                    <label for="modal-col-other-weight">Other Weight</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-other-rate" data-column="other-rate" checked>
                                                    <label for="modal-col-other-rate">Other Rate</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-other-info" data-column="other-info" checked>
                                                    <label for="modal-col-other-info">Other Info</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-other-amount" data-column="other-amount" checked>
                                                    <label for="modal-col-other-amount">Other Amount</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-hallmark-amount" data-column="hallmark-amount" checked>
                                                    <label for="modal-col-hallmark-amount">Hallmark Amount</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-hallmark-rate" data-column="hallmark-rate" checked>
                                                    <label for="modal-col-hallmark-rate">Hallmark Rate</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-net-amt-tax" data-column="net-amt-tax" checked>
                                                    <label for="modal-col-net-amt-tax">Net Amt+Tax</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-reverse" data-column="reverse" checked>
                                                    <label for="modal-col-reverse">Reverse</label>
                                                </div>
                                            </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Product List Table with All Options - Horizontally Scrollable -->
                                    <div style="overflow-x: auto; overflow-y: auto; max-height: 500px; border: 1px solid #e2e8f0; border-radius: 6px;">
                                        <table class="table table-bordered table-sm mb-0" id="productListTable" style="min-width: 4000px; font-size: 0.75rem;">
                                            <thead style="position: sticky; top: 0; background: #f8fafc; z-index: 10;">
                                                <tr>
                                                    <th data-column="checkbox" style="min-width: 50px; position: sticky; left: 0; background: #f8fafc; z-index: 11;">
                                                        <input type="checkbox" id="selectAllProducts" title="Select All">
                                                    </th>
                                                    <th data-column="id" style="min-width: 60px;">Id</th>
                                                    <th data-column="rfid" style="min-width: 100px;">RFIDCode</th>
                                                    <th data-column="voucher-type" style="min-width: 120px;">voucherTypeld</th>
                                                    <th data-column="barcode" style="min-width: 120px;">Barcode No.</th>
                                                    <th data-column="design-no" style="min-width: 100px;">Design No</th>
                                                    <th data-column="huid" style="min-width: 100px;">HUID No.</th>
                                                    <th data-column="category" style="min-width: 120px;">Category <i class="feather icon-plus add-category-icon" style="font-size: 0.7rem; cursor: pointer;" title="Add New Category"></i></th>
                                                    <th data-column="calculation" style="min-width: 140px;">Calculation ...</th>
                                                    <th data-column="product" style="min-width: 120px;">Product* <i class="feather icon-plus add-product-icon" style="font-size: 0.7rem; cursor: pointer;" title="Add New Product"></i></th>
                                                    <th data-column="location" style="min-width: 120px;">Location <i class="feather icon-plus" style="font-size: 0.7rem; cursor: pointer;"></i></th>
                                                    <th data-column="quantity" style="min-width: 80px;">Quantity</th>
                                                    <th data-column="carat" style="min-width: 80px;">Carat <i class="feather icon-plus" style="font-size: 0.7rem; cursor: pointer;"></i></th>
                                                    <th data-column="pkt-wt" style="min-width: 80px;">Pkt. Wt.</th>
                                                    <th data-column="pkt-less-wt" style="min-width: 100px;">PKt. Less Wt.</th>
                                                    <th data-column="requested-purity" style="min-width: 120px;">Requested Pu...</th>
                                                    <th data-column="requested" style="min-width: 100px;">Requested...</th>
                                                    <th data-column="gross-wt" style="min-width: 80px;">Gross Wt.</th>
                                                    <th data-column="less-wt" style="min-width: 80px;">Less Wt.</th>
                                                    <th data-column="gold-loss1" style="min-width: 100px;">Gold Loss ...</th>
                                                    <th data-column="gold-loss2" style="min-width: 100px;">Gold Loss ...</th>
                                                    <th data-column="setting-charge" style="min-width: 110px;">Setting Ch...</th>
                                                    <th data-column="net-wt" style="min-width: 80px;">Net Wt.</th>
                                                    <th data-column="purity" style="min-width: 80px;">Purity</th>
                                                    <th data-column="purity-wt" style="min-width: 90px;">Purity Wt.</th>
                                                    <th data-column="wastage-per" style="min-width: 100px;">Wastage Per.</th>
                                                    <th data-column="wastage-wt" style="min-width: 100px;">Wastage Wt.</th>
                                                    <th data-column="final-wt" style="min-width: 80px;">Final Wt.</th>
                                                    <th data-column="alloy-wt" style="min-width: 80px;">Alloy Wt.</th>
                                                    <th data-column="rate" style="min-width: 80px;">Rate*</th>
                                                    <th data-column="metal-value" style="min-width: 100px;">Metal Value</th>
                                                    <th data-column="metal-cost" style="min-width: 100px;">Metal Cost</th>
                                                    <th data-column="amount" style="min-width: 100px;">Amount</th>
                                                    <th data-column="discount-type" style="min-width: 100px;">Type</th>
                                                    <th data-column="discount-per" style="min-width: 80px;">Per.</th>
                                                    <th data-column="discount-amount" style="min-width: 100px;">Amount</th>
                                                    <th data-column="discount" style="min-width: 100px;">Discount</th>
                                                    <th data-column="discount-type2" style="min-width: 100px;">Type</th>
                                                    <th data-column="discount-per2" style="min-width: 80px;">Per.</th>
                                                    <th data-column="discount-amount2" style="min-width: 100px;">Amount</th>
                                                    <th data-column="discounted-amt" style="min-width: 120px;">Discounted Amt.</th>
                                                    <th data-column="discounted-per" style="min-width: 120px;">Discounted Per.</th>
                                                    <th data-column="making-type" style="min-width: 100px;">Type</th>
                                                    <th data-column="making-rate" style="min-width: 100px;">Rate</th>
                                                    <th data-column="making-discount-amt" style="min-width: 130px;">Discount Amount</th>
                                                    <th data-column="making-amount" style="min-width: 100px;">Amount</th>
                                                    <th data-column="making-actual-value" style="min-width: 120px;">Actual Value</th>
                                                    <th data-column="making-cost" style="min-width: 110px;">Making Cost</th>
                                                    <th data-column="min-price" style="min-width: 100px;">Minimum Price</th>
                                                    <th data-column="minimum" style="min-width: 100px;">Minimum ...</th>
                                                    <th data-column="stone-charge-type" style="min-width: 100px;">Type</th>
                                                    <th data-column="stone-weight" style="min-width: 110px;">Stone Weight</th>
                                                    <th data-column="stone-rate" style="min-width: 100px;">Stone Rate</th>
                                                    <th data-column="stone-amount" style="min-width: 120px;">Stone Amount</th>
                                                    <th data-column="stone-cost" style="min-width: 100px;">Stone Cost</th>
                                                    <th data-column="diamond-amount" style="min-width: 120px;">Diamond Amount</th>
                                                    <th data-column="purchase-amount" style="min-width: 130px;">Purchase Amount</th>
                                                    <th data-column="sale-amount" style="min-width: 110px;">Sale Amount</th>
                                                    <th data-column="sale-amount-with" style="min-width: 130px;">Sale Amount Wi...</th>
                                                    <th data-column="net-amt" style="min-width: 100px;">Net Amt</th>
                                                    <th data-column="tax" style="min-width: 80px;">Tax</th>
                                                    <th data-column="other-charge-type" style="min-width: 100px;">Type</th>
                                                    <th data-column="other-weight" style="min-width: 110px;">Other Weight</th>
                                                    <th data-column="other-rate" style="min-width: 100px;">Other Rate</th>
                                                    <th data-column="other-info" style="min-width: 100px;">Other Info</th>
                                                    <th data-column="other-amount" style="min-width: 120px;">Other Amount</th>
                                                    <th data-column="hallmark-amount" style="min-width: 130px;">Hallmark A...</th>
                                                    <th data-column="hallmark-rate" style="min-width: 120px;">HallMark Rate</th>
                                                    <th data-column="net-amt-tax" style="min-width: 120px; position: sticky; right: 160px; background: #f8fafc; z-index: 8;">Net Amt+Tax</th>
                                                    <th data-column="reverse" style="min-width: 80px; position: sticky; right: 80px; background: #f8fafc; z-index: 9;">Reverse</th>
                                                    <th data-column="actions" style="min-width: 80px; text-align: center; position: sticky; right: 0; background: #f8fafc; z-index: 10;">
                                                        Action
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody id="productListBody">
                                                <tr>
                                                    <td colspan="70" class="text-center text-muted py-4">Click "Add Product" button to add products for billing...</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <!-- Bottom Section -->
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <div class="form-group mb-2">
                                                <label>Group Name</label>
                                                <input type="text" class="form-control form-control-sm" id="modalGroupName" placeholder="Group Name">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group mb-2">
                                                <label>Comment</label>
                                                <input type="text" class="form-control form-control-sm" id="modalComment" placeholder="Comment">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="text-right mt-3">
                                        <button type="button" class="btn btn-purple btn-sm" id="modalAddBtn">
                                            <i class="feather icon-plus"></i> Add (Shift + A)
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Product List Table -->
                            <div class="card mb-4" style="overflow: visible !important;">
                                <div class="card-body" style="overflow: visible !important;">
                                    <div class="table-header-wrapper">
                                        <h6 style="margin: 0; font-size: 0.9rem; font-weight: 700; color: #1e293b;">Product List</h6>
                                        <div class="table-settings-wrapper" style="display: flex; gap: 8px; align-items: center;">
                                            <button class="btn btn-sm btn-purple" onclick="printMultipleBarcodes()" title="Print All Barcodes" style="padding: 6px 12px; font-size: 0.75rem;">
                                                <i class="feather icon-printer"></i> Print Barcodes
                                            </button>
                                            <button class="table-settings-btn" id="tableSettingsBtn">
                                                <i class="feather icon-settings"></i>
                                            </button>
                                            <div class="table-settings-dropdown" id="tableSettingsDropdown">
                                                <h6>Show/Hide Columns</h6>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="col-barcode" data-column="barcode" checked>
                                                    <label for="col-barcode">Barcode</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="col-description" data-column="description" checked>
                                                    <label for="col-description">Description</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="col-quantity" data-column="quantity" checked>
                                                    <label for="col-quantity">Quantity</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="col-gross-wt" data-column="gross-wt" checked>
                                                    <label for="col-gross-wt">Gross Wt.</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="col-less-wt" data-column="less-wt" checked>
                                                    <label for="col-less-wt">Less Wt.</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="col-purity" data-column="purity" checked>
                                                    <label for="col-purity">Purity</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="col-final-wt" data-column="final-wt" checked>
                                                    <label for="col-final-wt">Final Wt.</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="col-net-wt" data-column="net-wt" checked>
                                                    <label for="col-net-wt">Net Wt.</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="col-pure-wt" data-column="pure-wt" checked>
                                                    <label for="col-pure-wt">Pure Wt</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="col-making" data-column="making" checked>
                                                    <label for="col-making">Making</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="col-design-no" data-column="design-no" checked>
                                                    <label for="col-design-no">Design No.</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="col-tax" data-column="tax" checked>
                                                    <label for="col-tax">Tax</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="col-amount" data-column="amount" checked>
                                                    <label for="col-amount">Amount</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="col-net-amt" data-column="net-amt" checked>
                                                    <label for="col-net-amt">Net Amt</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="col-net-amt-tax" data-column="net-amt-tax" checked>
                                                    <label for="col-net-amt-tax">Net Amt With Tax</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="col-stone-charges" data-column="stone-charges" checked>
                                                    <label for="col-stone-charges">Stone Charges</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="col-other-charges" data-column="other-charges" checked>
                                                    <label for="col-other-charges">Other Charges</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="col-diamond-value" data-column="diamond-value" checked>
                                                    <label for="col-diamond-value">Diamond Value</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="col-gemstone-value" data-column="gemstone-value" checked>
                                                    <label for="col-gemstone-value">Gemstone Value</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="table-responsive">
                                        <table class="table table-bordered product-table">
                                            <thead>
                                                <tr>
                                                    <th style="width: 50px; text-align: center;" data-column="print-barcode">
                                                        <i class="feather icon-printer" style="font-size: 0.9rem;"></i>
                                                    </th>
                                                    <th class="draggable-column" data-column="barcode" draggable="true">
                                                        Barcode
                                                    </th>
                                                    <th class="draggable-column" data-column="description" draggable="true">
                                                        Description
                                                    </th>
                                                    <th class="draggable-column" data-column="quantity" draggable="true">
                                                        Quantity
                                                    </th>
                                                    <th class="draggable-column" data-column="gross-wt" draggable="true">
                                                        Gross Wt.
                                                    </th>
                                                    <th class="draggable-column" data-column="less-wt" draggable="true">
                                                        Less Wt.
                                                    </th>
                                                    <th class="draggable-column" data-column="purity" draggable="true">
                                                        Purity
                                                    </th>
                                                    <th class="draggable-column" data-column="final-wt" draggable="true">
                                                        Final Wt.
                                                    </th>
                                                    <th class="draggable-column" data-column="net-wt" draggable="true">
                                                        Net Wt.
                                                    </th>
                                                    <th class="draggable-column" data-column="pure-wt" draggable="true">
                                                        Pure Wt
                                                    </th>
                                                    <th class="draggable-column" data-column="making" draggable="true">
                                                        Making
                                                    </th>
                                                    <th class="draggable-column" data-column="design-no" draggable="true">
                                                        Design No.
                                                    </th>
                                                    <th class="draggable-column" data-column="stone-charges" draggable="true">
                                                        Stone Charges
                                                    </th>
                                                    <th class="draggable-column" data-column="other-charges" draggable="true">
                                                        Other Charges
                                                    </th>
                                                    <th class="draggable-column" data-column="diamond-value" draggable="true">
                                                        Diamond Value
                                                    </th>
                                                    <th class="draggable-column" data-column="gemstone-value" draggable="true">
                                                        Gemstone Value
                                                    </th>
                                                    <th class="draggable-column" data-column="rate" draggable="true">
                                                        Rate
                                                    </th>
                                                    <th class="draggable-column" data-column="metal-value" draggable="true">
                                                        Metal Value
                                                    </th>
                                                    <th class="draggable-column" data-column="discount" draggable="true">
                                                        Discount
                                                    </th>
                                                    <th class="draggable-column" data-column="making-amount" draggable="true">
                                                        Making Amount
                                                    </th>
                                                    <th class="draggable-column" data-column="stone-amount" draggable="true">
                                                        Stone Amount
                                                    </th>
                                                    <th class="draggable-column" data-column="other-amount" draggable="true">
                                                        Other Amount
                                                    </th>
                                                    <th class="draggable-column" data-column="diamond-amount" draggable="true">
                                                        Diamond Amount
                                                    </th>
                                                    <th class="draggable-column" data-column="purchase-amount" draggable="true">
                                                        Purchase Amount
                                                    </th>
                                                    <th class="draggable-column" data-column="sale-amount" draggable="true">
                                                        Sale Amount
                                                    </th>
                                                    <th class="draggable-column" data-column="sale-amount-with" draggable="true">
                                                        Sale Amount With
                                                    </th>
                                                    <th class="draggable-column" data-column="reverse" draggable="true">
                                                        Reverse
                                                    </th>
                                                    <th class="draggable-column" data-column="tax" draggable="true">
                                                        Tax
                                                    </th>
                                                    <th class="draggable-column" data-column="amount" draggable="true">
                                                        Amount
                                                    </th>
                                                    <th class="draggable-column" data-column="net-amt" draggable="true">
                                                        Net Amt
                                                    </th>
                                                    <th class="draggable-column" data-column="net-amt-tax" draggable="true">
                                                        Net Amt With Tax
                                                    </th>
                                                    <th style="width: 80px; text-align: center;">
                                                        <i class="feather icon-settings" style="cursor: pointer;"></i>
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody id="productTableBody">
                                                <tr class="no-drag">
                                                    <td colspan="32" class="text-center text-muted py-4" id="emptyRowCell">No Rows To Show</td>
                                                </tr>
                                            </tbody>
                                            <tfoot id="productTableFooter" style="display: none;">
                                                <tr style="background: #f8fafc; font-weight: 600;">
                                                    <td></td>
                                                    <td colspan="1" style="text-align: right; color: #11294b;">Grand Total:</td>
                                                    <td id="footerQuantity" style="text-align: right; color: #11294b;">0.00</td>
                                                    <td id="footerGrossWt" style="text-align: right; color: #11294b;">0.0</td>
                                                    <td id="footerLessWt" style="text-align: right; color: #11294b;">0.0</td>
                                                    <td id="footerPurity" style="text-align: right; color: #11294b;">0.0</td>
                                                    <td id="footerFinalWt" style="text-align: right; color: #11294b;">0.0</td>
                                                    <td id="footerNetWt" style="text-align: right; color: #11294b;">0.0</td>
                                                    <td id="footerPureWt" style="text-align: right; color: #11294b;">0.000</td>
                                                    <td id="footerMaking" style="text-align: right; color: #11294b;">0</td>
                                                    <td></td>
                                                    <td id="footerStoneCharges" style="text-align: right; color: #11294b;">0.00</td>
                                                    <td id="footerOtherCharges" style="text-align: right; color: #11294b;">0.00</td>
                                                    <td id="footerDiamondValue" style="text-align: right; color: #11294b;">0.00</td>
                                                    <td id="footerGemstoneValue" style="text-align: right; color: #11294b;">0.00</td>
                                                    <td id="footerRate" style="text-align: right; color: #11294b;">0.00</td>
                                                    <td id="footerMetalValue" style="text-align: right; color: #11294b;">0.00</td>
                                                    <td id="footerDiscount" style="text-align: right; color: #11294b;">0.00</td>
                                                    <td id="footerMakingAmount" style="text-align: right; color: #11294b;">0.00</td>
                                                    <td id="footerStoneAmount" style="text-align: right; color: #11294b;">0.00</td>
                                                    <td id="footerOtherAmount" style="text-align: right; color: #11294b;">0.00</td>
                                                    <td id="footerDiamondAmount" style="text-align: right; color: #11294b;">0.00</td>
                                                    <td id="footerPurchaseAmount" style="text-align: right; color: #11294b;">0.00</td>
                                                    <td id="footerSaleAmount" style="text-align: right; color: #11294b;">0.00</td>
                                                    <td id="footerSaleAmountWith" style="text-align: right; color: #11294b;">0.00</td>
                                                    <td id="footerReverse" style="text-align: right; color: #11294b;">0.00</td>
                                                    <td id="footerTax" style="text-align: right; color: #11294b;">0</td>
                                                    <td id="footerAmount" style="text-align: right; color: #11294b; font-weight: 700;">0.00</td>
                                                    <td id="footerNetAmt" style="text-align: right; color: #11294b;">0.00</td>
                                                    <td id="footerNetAmtTax" style="text-align: right; color: #11294b;">0.00</td>
                                                    <td></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                    
                                    <!-- Group Name and Comment -->
                                    <!-- <div class="row mt-2">
                                        <div class="col-md-6">
                                            <div class="form-group mb-2">
                                                <label>Group Name</label>
                                                <input type="text" class="form-control form-control-sm" id="groupName" placeholder="Group Name">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group mb-2">
                                                <label>Comment</label>
                                                <input type="text" class="form-control form-control-sm" id="orderComment" placeholder="Comment">
                                            </div>
                                        </div>
                                    </div> -->
                                    
                                    <!-- Save Button -->
                                    <div class="text-right mt-3 mb-3">
                                        <button type="button" class="btn btn-purple btn-sm" id="saveStockJournalBtn" style="padding: 0.5rem 2rem; font-weight: 600;">
                                            <i class="feather icon-save"></i> Save Stock Journal
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Payment Details Section -->
                            
                        </div>

                        <!-- Summary Panel -->
                        
                    </div>
                </div>
                <!-- [ content ] End -->

                <!-- [ Layout footer ] Start -->
                <nav class="layout-footer footer footer-light" style="padding: 0.75rem 15px; background: linear-gradient(to bottom, #ffffff 0%, #f8fafc 100%); border-top: 1.5px solid #e2e8f0;">
                    <div class="container-fluid d-flex flex-wrap justify-content-between align-items-center" style="padding-left: 0; padding-right: 0;">
                        <div>
                            <span style="color: #666; font-size: 0.85rem; font-weight: 500;">Premium Version: 1.0.0</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <span style="color: #666; font-size: 0.85rem;">07/12/2025</span>
                            <i class="feather icon-user" style="color: #666; font-size: 12px;"></i>
                            <i class="feather icon-settings" style="color: #666; font-size: 12px;"></i>
                        </div>
                    </div>
                </nav>
                <!-- [ Layout footer ] End -->
            </div>
            <!-- [ Layout content ] End -->
        </div>
        <!-- [ Layout container ] End -->
    </div>
    <!-- Overlay -->
    <div class="layout-overlay layout-sidenav-toggle"></div>
</div>
<!-- / Layout wrapper -->

<!-- Product Selection Modal -->
<div class="modal fade" id="productSelectionModal" tabindex="-1" role="dialog" aria-labelledby="productSelectionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document" style="max-width: 95%;">
        <div class="modal-content">
            <!-- <div class="modal-header" style="background: #ffffff; border-bottom: 2px solid #e2e8f0; padding: 1rem;">
                <div style="width: 100%; position: relative;">
                    <input type="text" class="form-control form-control-lg" id="modalProductSearchInput" placeholder="Enter your item" style="border: 2px solid #c5a864; border-radius: 6px; padding-right: 40px;">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: #64748b; font-size: 1.5rem;">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            </div> -->
            <div class="modal-body" style="padding: 1.5rem;">
                <!-- Category Tabs -->
                <div class="product-category-tabs" style="display: flex; gap: 0.5rem; margin-bottom: 1rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0.5rem;">
                    <?php 
                    $first_metal = true;
                    foreach($metals as $metal): 
                        $tab_class = $first_metal ? 'active' : '';
                        $tab_id = 'modal-tab-' . strtolower(str_replace([' ', '&'], ['-', ''], $metal['display_name']));
                    ?>
                    <button type="button" class="category-tab-btn <?php echo $tab_class; ?>" data-metal-id="<?php echo $metal['id']; ?>" data-metal-name="<?php echo htmlspecialchars($metal['display_name']); ?>" id="<?php echo $tab_id; ?>">
                        <?php echo htmlspecialchars($metal['display_name']); ?>
                    </button>
                    <?php 
                    $first_metal = false;
                    endforeach; 
                    ?>
                    <!-- <button type="button" class="btn btn-sm btn-purple ml-auto" style="margin-left: auto;">
                        + More >>
                    </button> -->
                </div>
                
                <!-- Item Entry Fields Above Table -->
                <div class="row mb-3" style="background: transparent; padding: 0px; border-radius: 0px;">
                    <div class="col-md-2">
                        <div class="form-group mb-2">
                            <label>Barcode</label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control form-control-sm" id="modalProductBarcode" placeholder="Scan or enter">
                                <div class="input-group-append">
                                    <span class="input-group-text" style="background: #f8fafc;"><i class="feather icon-image"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group mb-2">
                            <label>Code</label>
                            <input type="text" class="form-control form-control-sm" id="modalProductCode" placeholder="Code">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group mb-2">
                            <label>Des. No.</label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control form-control-sm" id="modalProductDesignNo" placeholder="Design number">
                                <div class="input-group-append">
                                    <span class="input-group-text" style="background: #f8fafc;"><i class="feather icon-chevron-down"></i></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-1">
                        <div class="form-group mb-2">
                            <label>&nbsp;</label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control form-control-sm" id="modalProductQty" value="1" min="1" step="0.01">
                                <div class="input-group-append">
                                    <button class="btn btn-sm" type="button" style="background: #f8fafc; border: 1px solid #e2e8f0;"><i class="feather icon-refresh-cw"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group mb-2">
                            <label>&nbsp;</label>
                            <div class="d-flex" style="gap: 0.5rem; align-items: center;">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="modalMetalUnfix">
                                    <label class="form-check-label" for="modalMetalUnfix" style="font-size: 0.75rem;">Metal Unfix</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="modalUnfix">
                                    <label class="form-check-label" for="modalUnfix" style="font-size: 0.75rem;">UnFix</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- <div class="col-md-3">
                        <div class="form-group mb-2">
                            <label>&nbsp;</label>
                            <button class="btn btn-sm btn-purple" type="button" style="width: 100%;">
                                + More >>
                            </button>
                        </div>
                    </div> -->
                </div>
                
                <!-- Column Visibility Settings -->
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 style="margin: 0; font-size: 0.9rem; font-weight: 700; color: #1e293b;">Product Selection</h6>
                    <div class="d-flex align-items-center" style="gap: 0.5rem;">
                        <button class="table-settings-btn" id="addProductRowBtn" style="background: #c5a864; color: #fff; border: none; padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer; font-size: 0.85rem; display: flex; align-items: center; gap: 0.25rem;">
                            <i class="feather icon-plus"></i> Add Product
                        </button>
                        <div class="table-settings-wrapper">
                            <button class="table-settings-btn" id="modalTableSettingsBtn">
                                <i class="feather icon-settings"></i> Show/Hide Columns
                            </button>
                        <div class="table-settings-dropdown" id="modalTableSettingsDropdown">
                            <h6>Show/Hide Columns</h6>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-checkbox" data-column="checkbox" checked>
                                <label for="modal-col-checkbox">Select</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-name" data-column="name" checked>
                                <label for="modal-col-name">Name</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-short-code" data-column="short-code" checked>
                                <label for="modal-col-short-code">Short Code</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-rfid" data-column="rfid" checked>
                                <label for="modal-col-rfid">RFIDCode</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-voucher-type" data-column="voucher-type" checked>
                                <label for="modal-col-voucher-type">Voucher Type</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-barcode" data-column="barcode" checked>
                                <label for="modal-col-barcode">Barcode</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-design-no" data-column="design-no" checked>
                                <label for="modal-col-design-no">Design No</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-huid" data-column="huid" checked>
                                <label for="modal-col-huid">HUID No</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-category" data-column="category" checked>
                                <label for="modal-col-category">Category</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-calculation" data-column="calculation" checked>
                                <label for="modal-col-calculation">Calculation</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-location" data-column="location" checked>
                                <label for="modal-col-location">Location</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-quantity" data-column="quantity" checked>
                                <label for="modal-col-quantity">Quantity</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-carat" data-column="carat" checked>
                                <label for="modal-col-carat">Carat</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-pkt-wt" data-column="pkt-wt" checked>
                                <label for="modal-col-pkt-wt">Pkt. Wt.</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-pkt-less-wt" data-column="pkt-less-wt" checked>
                                <label for="modal-col-pkt-less-wt">Pkt. Less Wt.</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-requested-purity" data-column="requested-purity" checked>
                                <label for="modal-col-requested-purity">Requested Purity</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-requested" data-column="requested" checked>
                                <label for="modal-col-requested">Requested</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-gross-wt" data-column="gross-wt" checked>
                                <label for="modal-col-gross-wt">Gross Wt.</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-less-wt" data-column="less-wt" checked>
                                <label for="modal-col-less-wt">Less Wt.</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-gold-loss1" data-column="gold-loss1" checked>
                                <label for="modal-col-gold-loss1">Gold Loss 1</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-gold-loss2" data-column="gold-loss2" checked>
                                <label for="modal-col-gold-loss2">Gold Loss 2</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-setting-charge" data-column="setting-charge" checked>
                                <label for="modal-col-setting-charge">Setting Charge</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-net-wt" data-column="net-wt" checked>
                                <label for="modal-col-net-wt">Net Wt.</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-purity" data-column="purity" checked>
                                <label for="modal-col-purity">Purity</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-purity-wt" data-column="purity-wt" checked>
                                <label for="modal-col-purity-wt">Purity Wt.</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-wastage-per" data-column="wastage-per" checked>
                                <label for="modal-col-wastage-per">Wastage Per.</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-wastage-wt" data-column="wastage-wt" checked>
                                <label for="modal-col-wastage-wt">Wastage Wt.</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-final-wt" data-column="final-wt" checked>
                                <label for="modal-col-final-wt">Final Wt.</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-alloy-wt" data-column="alloy-wt" checked>
                                <label for="modal-col-alloy-wt">Alloy Wt.</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-rate" data-column="rate" checked>
                                <label for="modal-col-rate">Rate</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-metal-value" data-column="metal-value" checked>
                                <label for="modal-col-metal-value">Metal Value</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-metal-cost" data-column="metal-cost" checked>
                                <label for="modal-col-metal-cost">Metal Cost</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-amount" data-column="amount" checked>
                                <label for="modal-col-amount">Amount</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-discount-type" data-column="discount-type" checked>
                                <label for="modal-col-discount-type">Discount Type</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-discount-per" data-column="discount-per" checked>
                                <label for="modal-col-discount-per">Discount Per.</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-discount-amount" data-column="discount-amount" checked>
                                <label for="modal-col-discount-amount">Discount Amount</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-discount" data-column="discount" checked>
                                <label for="modal-col-discount">Discount</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-making-type" data-column="making-type" checked>
                                <label for="modal-col-making-type">Making Type</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-making-rate" data-column="making-rate" checked>
                                <label for="modal-col-making-rate">Making Rate</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-making-amount" data-column="making-amount" checked>
                                <label for="modal-col-making-amount">Making Amount</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-making-cost" data-column="making-cost" checked>
                                <label for="modal-col-making-cost">Making Cost</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-min-price" data-column="min-price" checked>
                                <label for="modal-col-min-price">Minimum Price</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-stone-charge-type" data-column="stone-charge-type" checked>
                                <label for="modal-col-stone-charge-type">Stone Charge Type</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-stone-weight" data-column="stone-weight" checked>
                                <label for="modal-col-stone-weight">Stone Weight</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-stone-rate" data-column="stone-rate" checked>
                                <label for="modal-col-stone-rate">Stone Rate</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-stone-amount" data-column="stone-amount" checked>
                                <label for="modal-col-stone-amount">Stone Amount</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-stone-cost" data-column="stone-cost" checked>
                                <label for="modal-col-stone-cost">Stone Cost</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-diamond-amount" data-column="diamond-amount" checked>
                                <label for="modal-col-diamond-amount">Diamond Amount</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-purchase-amount" data-column="purchase-amount" checked>
                                <label for="modal-col-purchase-amount">Purchase Amount</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-sale-amount" data-column="sale-amount" checked>
                                <label for="modal-col-sale-amount">Sale Amount</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-net-amt" data-column="net-amt" checked>
                                <label for="modal-col-net-amt">Net Amt</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-tax" data-column="tax" checked>
                                <label for="modal-col-tax">Tax</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-other-charge-type" data-column="other-charge-type" checked>
                                <label for="modal-col-other-charge-type">Other Charge Type</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-other-weight" data-column="other-weight" checked>
                                <label for="modal-col-other-weight">Other Weight</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-other-rate" data-column="other-rate" checked>
                                <label for="modal-col-other-rate">Other Rate</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-other-info" data-column="other-info" checked>
                                <label for="modal-col-other-info">Other Info</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-other-amount" data-column="other-amount" checked>
                                <label for="modal-col-other-amount">Other Amount</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-hallmark-amount" data-column="hallmark-amount" checked>
                                <label for="modal-col-hallmark-amount">Hallmark Amount</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-hallmark-rate" data-column="hallmark-rate" checked>
                                <label for="modal-col-hallmark-rate">Hallmark Rate</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-net-amt-tax" data-column="net-amt-tax" checked>
                                <label for="modal-col-net-amt-tax">Net Amt+Tax</label>
                            </div>
                            <div class="table-settings-item">
                                <input type="checkbox" id="modal-col-reverse" data-column="reverse" checked>
                                <label for="modal-col-reverse">Reverse</label>
                            </div>
                        </div>
                        </div>
                    </div>
                </div>
                
                <!-- Product List Table with All Options - Horizontally Scrollable -->
                <div style="overflow-x: auto; overflow-y: auto; max-height: 500px; border: 1px solid #e2e8f0; border-radius: 6px;">
                    <table class="table table-bordered table-sm mb-0" id="productListTable" style="min-width: 4000px; font-size: 0.75rem;">
                        <thead style="position: sticky; top: 0; background: #f8fafc; z-index: 10;">
                            <tr>
                                <th data-column="checkbox" style="min-width: 50px; position: sticky; left: 0; background: #f8fafc; z-index: 11;">
                                    <input type="checkbox" id="selectAllProducts" title="Select All">
                                </th>
                                <th data-column="id" style="min-width: 60px;">Id</th>
                                <th data-column="rfid" style="min-width: 100px;">RFIDCode</th>
                                <th data-column="voucher-type" style="min-width: 120px;">voucherTypeld</th>
                                <th data-column="barcode" style="min-width: 120px;">Barcode No.</th>
                                <th data-column="design-no" style="min-width: 100px;">Design No</th>
                                <th data-column="huid" style="min-width: 100px;">HUID No.</th>
                                <th data-column="category" style="min-width: 120px;">Category <i class="feather icon-plus add-category-icon" style="font-size: 0.7rem; cursor: pointer;" title="Add New Category"></i></th>
                                <th data-column="calculation" style="min-width: 140px;">Calculation ...</th>
                                <th data-column="product" style="min-width: 120px;">Product* <i class="feather icon-plus add-product-icon" style="font-size: 0.7rem; cursor: pointer;" title="Add New Product"></i></th>
                                <th data-column="location" style="min-width: 120px;">Location <i class="feather icon-plus" style="font-size: 0.7rem; cursor: pointer;"></i></th>
                                <th data-column="quantity" style="min-width: 80px;">Quantity</th>
                                <th data-column="carat" style="min-width: 80px;">Carat <i class="feather icon-plus" style="font-size: 0.7rem; cursor: pointer;"></i></th>
                                <th data-column="pkt-wt" style="min-width: 80px;">Pkt. Wt.</th>
                                <th data-column="pkt-less-wt" style="min-width: 100px;">PKt. Less Wt.</th>
                                <th data-column="requested-purity" style="min-width: 120px;">Requested Pu...</th>
                                <th data-column="requested" style="min-width: 100px;">Requested...</th>
                                <th data-column="gross-wt" style="min-width: 80px;">Gross Wt.</th>
                                <th data-column="less-wt" style="min-width: 80px;">Less Wt.</th>
                                <th data-column="gold-loss1" style="min-width: 100px;">Gold Loss ...</th>
                                <th data-column="gold-loss2" style="min-width: 100px;">Gold Loss ...</th>
                                <th data-column="setting-charge" style="min-width: 110px;">Setting Ch...</th>
                                <th data-column="net-wt" style="min-width: 80px;">Net Wt.</th>
                                <th data-column="purity" style="min-width: 80px;">Purity</th>
                                <th data-column="purity-wt" style="min-width: 90px;">Purity Wt.</th>
                                <th data-column="wastage-per" style="min-width: 100px;">Wastage Per.</th>
                                <th data-column="wastage-wt" style="min-width: 100px;">Wastage Wt.</th>
                                <th data-column="final-wt" style="min-width: 80px;">Final Wt.</th>
                                <th data-column="alloy-wt" style="min-width: 80px;">Alloy Wt.</th>
                                <th data-column="rate" style="min-width: 80px;">Rate*</th>
                                <th data-column="metal-value" style="min-width: 100px;">Metal Value</th>
                                <th data-column="metal-cost" style="min-width: 100px;">Metal Cost</th>
                                <th data-column="amount" style="min-width: 100px;">Amount</th>
                                <th data-column="discount-type" style="min-width: 100px;">Type</th>
                                <th data-column="discount-per" style="min-width: 80px;">Per.</th>
                                <th data-column="discount-amount" style="min-width: 100px;">Amount</th>
                                <th data-column="discount" style="min-width: 100px;">Discount</th>
                                <th data-column="discount-type2" style="min-width: 100px;">Type</th>
                                <th data-column="discount-per2" style="min-width: 80px;">Per.</th>
                                <th data-column="discount-amount2" style="min-width: 100px;">Amount</th>
                                <th data-column="discounted-amt" style="min-width: 120px;">Discounted Amt.</th>
                                <th data-column="discounted-per" style="min-width: 120px;">Discounted Per.</th>
                                <th data-column="making-type" style="min-width: 100px;">Type</th>
                                <th data-column="making-rate" style="min-width: 100px;">Rate</th>
                                <th data-column="making-discount-amt" style="min-width: 130px;">Discount Amount</th>
                                <th data-column="making-amount" style="min-width: 100px;">Amount</th>
                                <th data-column="making-actual-value" style="min-width: 120px;">Actual Value</th>
                                <th data-column="making-cost" style="min-width: 110px;">Making Cost</th>
                                <th data-column="min-price" style="min-width: 100px;">Minimum Price</th>
                                <th data-column="minimum" style="min-width: 100px;">Minimum ...</th>
                                <th data-column="stone-charge-type" style="min-width: 100px;">Type</th>
                                <th data-column="stone-weight" style="min-width: 110px;">Stone Weight</th>
                                <th data-column="stone-rate" style="min-width: 100px;">Stone Rate</th>
                                <th data-column="stone-amount" style="min-width: 120px;">Stone Amount</th>
                                <th data-column="stone-cost" style="min-width: 100px;">Stone Cost</th>
                                <th data-column="diamond-amount" style="min-width: 120px;">Diamond Amount</th>
                                <th data-column="purchase-amount" style="min-width: 130px;">Purchase Amount</th>
                                <th data-column="sale-amount" style="min-width: 110px;">Sale Amount</th>
                                <th data-column="sale-amount-with" style="min-width: 130px;">Sale Amount Wi...</th>
                                <th data-column="net-amt" style="min-width: 100px;">Net Amt</th>
                                <th data-column="tax" style="min-width: 80px;">Tax</th>
                                <th data-column="other-charge-type" style="min-width: 100px;">Type</th>
                                <th data-column="other-weight" style="min-width: 110px;">Other Weight</th>
                                <th data-column="other-rate" style="min-width: 100px;">Other Rate</th>
                                <th data-column="other-info" style="min-width: 100px;">Other Info</th>
                                <th data-column="other-amount" style="min-width: 120px;">Other Amount</th>
                                <th data-column="hallmark-amount" style="min-width: 130px;">Hallmark A...</th>
                                <th data-column="hallmark-rate" style="min-width: 120px;">HallMark Rate</th>
                                <th data-column="net-amt-tax" style="min-width: 120px; position: sticky; right: 160px; background: #f8fafc; z-index: 8;">Net Amt+Tax</th>
                                <th data-column="reverse" style="min-width: 80px; position: sticky; right: 80px; background: #f8fafc; z-index: 9;">Reverse</th>
                                <th data-column="actions" style="min-width: 80px; text-align: center; position: sticky; right: 0; background: #f8fafc; z-index: 10;">
                                    Action
                                </th>
                            </tr>
                        </thead>
                        <tbody id="productListBody">
                            <tr>
                                <td colspan="70" class="text-center text-muted py-4">Click "Add Product" button to add products for billing...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <!-- Bottom Section -->
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="form-group mb-2">
                            <label>Group Name</label>
                            <input type="text" class="form-control form-control-sm" id="modalGroupName" placeholder="Group Name">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-2">
                            <label>Comment</label>
                            <input type="text" class="form-control form-control-sm" id="modalComment" placeholder="Comment">
                        </div>
                    </div>
                </div>
                
                <div class="text-right mt-3">
                    <button type="button" class="btn btn-purple btn-sm" id="modalAddBtn">
                        <i class="feather icon-plus"></i> Add (Shift + A)
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Right Side Product Creation Modal -->
<div class="modal fade right" id="productCreationModal" tabindex="-1" role="dialog" aria-labelledby="productCreationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-right modal-xl" role="document" style="max-width: 90%; width: 90%; margin: 0; height: 100vh;">
        <div class="modal-content" style="height: 100vh; border-radius: 0; border: none;">
            <div class="modal-header" style="background: #11294b; color: #fff; border: none; padding: 1rem 1.5rem;">
                <h5 class="modal-title" id="productCreationModalLabel">Add Product</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff; opacity: 1;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding: 0; height: calc(100vh - 60px); overflow-y: auto;">
                <form id="productCreationForm" method="post" action="product-save.php" style="height: 100%;">
                    <div style="display: flex; flex-direction: column; height: 100%;">
                        <!-- Top Section: Product Details + Tax -->
                        <div style="display: flex; gap: 1rem; padding: 1.5rem; border-bottom: 1px solid #e2e8f0;">
                            <!-- Product Details Form -->
                            <div class="card-box" style="flex: 1; padding: 1rem;">
                                <div class="d-flex justify-content-end mb-2">
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="clearProductForm()" style="margin-right: 0.5rem;">Clear</button>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="saveProduct()" style="margin-right: 0.5rem; background: #11294b; border: none;">Save</button>
                                    <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                                </div>
                                <p class="sec-title">Product Details</p>
                                
                                <div class="form-row-custom">
                                    <div class="form-group">
                                        <label>Name *</label>
                                        <input name="name" id="productName" class="form-control" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Alternate Name</label>
                                        <input name="alternate_name" id="productAlternateName" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label>Article</label>
                                        <input name="article" id="productArticle" class="form-control">
                                    </div>
                                </div>
                                
                                <div class="form-row-custom">
                                    <div class="form-group">
                                        <label>Category *</label>
                                        <div class="select-with-add">
                                            <select name="category_id" id="productCategory" class="form-control" required>
                                                <option value="">Select Category</option>
                                                <?php 
                                                foreach($categories as $cat) {
                                                    echo '<option value="'.$cat['id'].'">'.htmlspecialchars($cat['name']).'</option>';
                                                }
                                                ?>
                                            </select>
                                            <i class="feather icon-plus add-icon" title="Add Category"></i>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Branch *</label>
                                        <div class="select-with-add">
                                            <div class="branch-tags" id="branchTagsContainer">
                                                <?php 
                                                if (!empty($branches)) {
                                                    foreach($branches as $branch) {
                                                        $branch_name = htmlspecialchars($branch['name']);
                                                        echo '<span class="branch-tag" data-branch-id="'.$branch['id'].'">'.$branch_name.' <span class="remove-tag">×</span></span>';
                                                        echo '<input type="hidden" name="branch_ids[]" value="'.$branch['id'].'">';
                                                    }
                                                } else {
                                                    echo '<span class="text-muted" style="font-size: 0.8rem;">No branches available</span>';
                                                }
                                                ?>
                                                <span class="add-branch-btn"><i class="feather icon-plus"></i></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group checkbox-custom">
                                        <label><input type="checkbox" name="is_stock_item" id="productShowInStock" value="1" checked> Show In Stock</label>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Tax Box -->
                            <div class="card-box tax-table-wrapper" style="width: 400px; padding: 1rem;">
                                <p class="sec-title">Tax</p>
                                <table class="table table-sm table-bordered mb-0">
                                    <thead>
                                        <tr>
                                            <th>Tax</th>
                                            <th>Value</th>
                                            <th>Calculation Mo...</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><input type="checkbox" name="vat" id="productVAT" value="1"></td>
                                            <td><input type="text" name="vat_value" class="form-control form-control-sm" value="5" step="0.01" style="width: 47px;"></td>
                                            <td>
                                                <select name="vat_calculation_mode" class="form-control form-control-sm" style="font-size: 0.75rem;">
                                                    <?php 
                                                    foreach($calculation_modes as $mode) {
                                                        $selected = ($mode['name'] == 'Product Amount') ? 'selected' : '';
                                                        echo '<option value="'.htmlspecialchars($mode['name']).'" '.$selected.'>'.$mode['name'].'</option>';
                                                    }
                                                    ?>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><input type="checkbox" name="tax_bah" id="productTAXBAH" value="1"></td>
                                            <td><input type="text" name="tax_bah_value" class="form-control form-control-sm" value="10" step="0.01" style="width: 47px;"></td>
                                            <td>
                                                <select name="tax_bah_calculation_mode" class="form-control form-control-sm" style="font-size: 0.75rem;">
                                                    <?php 
                                                    foreach($calculation_modes as $mode) {
                                                        $selected = ($mode['name'] == 'Product Amount') ? 'selected' : '';
                                                        echo '<option value="'.htmlspecialchars($mode['name']).'" '.$selected.'>'.$mode['name'].'</option>';
                                                    }
                                                    ?>
                                                </select>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Bottom Section: Product Characteristics Table -->
                        <div class="card-box" style="flex: 1; display: flex; flex-direction: column; min-height: 0; overflow: hidden; padding: 1.5rem;">
                            <div class="pc-table-header-actions" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                                <p class="sec-title mb-0">Product Characteristics (MAIN BRANCH)</p>
                                <div style="position: relative;">
                                    <i class="feather icon-settings gear-icon" id="productModalColumnSettingsBtn" title="Column Settings" style="cursor: pointer; font-size: 1.2rem; color: #c5a864;"></i>
                                    <div class="columns-dropdown" id="productModalColumnsDropdown" style="position: absolute; right: 0; top: 100%; margin-top: 8px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1000; min-width: 250px; max-width: 300px; display: none;">
                                        <div class="columns-dropdown-header" style="padding: 12px; border-bottom: 1px solid #e2e8f0; font-weight: 600; color: #ffffff;">Columns</div>
                                        <div class="columns-dropdown-search" style="padding: 8px 12px; border-bottom: 1px solid #e2e8f0;">
                                            <input type="text" id="productModalColumnSearch" placeholder="Search columns..." style="width: 100%; padding: 6px 8px; border: 1px solid #e2e8f0; border-radius: 4px; font-size: 0.8rem;">
                                        </div>
                                        <div class="columns-dropdown-list" id="productModalColumnsList" style="max-height: 300px; overflow-y: auto; padding: 8px;">
                                            <!-- Will be populated by JavaScript -->
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="pc-wrapper" style="flex: 1; min-height: 0; display: flex; flex-direction: column; overflow: hidden;">
                                <div class="pc-scroll" style="flex: 1; min-height: 0; overflow-x: auto !important; overflow-y: auto !important; width: 100%;">
                                    <table class="table table-sm table-bordered pc-table" style="width: max-content; min-width: 100%; white-space: nowrap; margin-bottom: 0;">
                                        <thead id="pcTableHead">
                                            <tr id="headerRow1">
                                                <th rowspan="2" class="draggable" data-col="check">✔</th>
                                                <th rowspan="2" class="draggable" data-col="metal">Metal</th>
                                                <th rowspan="2" class="draggable" data-col="serialized">Serialized Barcode</th>
                                                <th rowspan="2" class="draggable" data-col="hsn">HSN *</th>
                                                <th rowspan="2" class="draggable" data-col="sku">SKU/Product C...</th>
                                                <th rowspan="2" class="draggable" data-col="making">Making on *</th>
                                                <th rowspan="2" class="draggable" data-col="diamond">Diamond Cat...</th>
                                                <th rowspan="2" class="draggable carat-header" data-col="carat">Carat</th>
                                                <th rowspan="2" class="draggable" data-col="discount">Discount</th>
                                                <th colspan="6" class="text-center" data-col="opening">Opening</th>
                                                <th colspan="2" class="text-center" data-col="barcode">Barcode</th>
                                                <th colspan="7" class="text-center" data-col="styles">Basic Styles</th>
                                            </tr>
                                            <tr id="headerRow2">
                                                <th class="draggable" data-col="weight">Weight</th>
                                                <th class="draggable" data-col="purity">Purity/K</th>
                                                <th class="draggable" data-col="qty">Qty</th>
                                                <th class="draggable" data-col="finalwt">Final Wt.</th>
                                                <th class="draggable" data-col="rate">Rate</th>
                                                <th class="draggable" data-col="value">Value</th>
                                                <th class="draggable" data-col="digits">No of Digits</th>
                                                <th class="draggable" data-col="prefix">Barcode Prefix</th>
                                                <th class="draggable" data-col="cut">Cut</th>
                                                <th class="draggable" data-col="shape">Shape</th>
                                                <th class="draggable" data-col="color">Color</th>
                                                <th class="draggable" data-col="clarity">Clarity</th>
                                                <th class="draggable" data-col="sieve">Sieve</th>
                                                <th class="draggable" data-col="size">Size</th>
                                                <th class="draggable" data-col="stylecode">Style Code</th>
                                            </tr>
                                        </thead>
                                        <tbody id="productCharacteristicsBody">
                                            <?php
                                            // Load metals with HSN codes
                                            $metals_list = getList("SELECT id, display_name, hsn_code FROM tbl_metal WHERE status = 1 ORDER BY id ASC");
                                            $hsn_codes = [];
                                            foreach($metals_list as $metal) {
                                                $hsn_codes[$metal['display_name']] = $metal['hsn_code'] ?: '7113'; // Default HSN code if not set
                                            }
                                            
                                            // Default HSN codes based on metal type
                                            $default_hsn = [
                                                'Gold' => '7113',
                                                'Silver' => '7113',
                                                'Platinum' => '999',
                                                'Diamond & Stones' => '7105',
                                                'Imitation Or Watches' => '7117',
                                                'Other Or Services' => '7113'
                                            ];
                                            
                                            $i = 0;
                                            foreach($metals_list as $metal) {
                                                $metal_name = $metal['display_name'];
                                                $hsn_code = isset($hsn_codes[$metal_name]) ? $hsn_codes[$metal_name] : (isset($default_hsn[$metal_name]) ? $default_hsn[$metal_name] : '7113');
                                                $diamond_category = ($metal_name == "Diamond & Stones") ? 'Jewellery' : '';
                                            ?>
                                            <tr>
                                                <td data-col="check"><input type="checkbox" name="row[<?=$i?>][is_selected]" value="1"></td>
                                                <td data-col="metal">
                                                    <input type="hidden" name="row[<?=$i?>][metal]" value="<?= htmlspecialchars($metal_name) ?>">
                                                    <input type="hidden" name="row[<?=$i?>][metal_id]" value="<?= $metal['id'] ?>">
                                                    <?= htmlspecialchars($metal_name) ?>
                                                </td>
                                                <td data-col="serialized"><input type="checkbox" name="row[<?=$i?>][serialized_barcode]" value="1"></td>
                                                <td data-col="hsn"><input name="row[<?=$i?>][hsn]" class="form-control form-control-sm" value="<?= $hsn_code ?>"></td>
                                                <td data-col="sku"><input name="row[<?=$i?>][sku_code]" class="form-control form-control-sm" value=""></td>
                                                <td data-col="making"><input name="row[<?=$i?>][making_on]" value="Gross Wt" class="form-control form-control-sm"></td>
                                                <td data-col="diamond"><input name="row[<?=$i?>][diamond_category]" class="form-control form-control-sm" value="<?= $diamond_category ?>"></td>
                                                <td data-col="carat"><input name="row[<?=$i?>][carat]" class="form-control form-control-sm" value=""></td>
                                                <td data-col="discount"><input name="row[<?=$i?>][discount]" class="form-control form-control-sm" value=""></td>
                                                <td data-col="weight"><input name="row[<?=$i?>][opening_weight]" class="form-control form-control-sm" value=""></td>
                                                <td data-col="purity"><input name="row[<?=$i?>][opening_purity]" class="form-control form-control-sm" value=""></td>
                                                <td data-col="qty"><input name="row[<?=$i?>][opening_qty]" class="form-control form-control-sm" value=""></td>
                                                <td data-col="finalwt"><input name="row[<?=$i?>][final_weight]" class="form-control form-control-sm" value=""></td>
                                                <td data-col="rate"><input name="row[<?=$i?>][rate]" class="form-control form-control-sm" value=""></td>
                                                <td data-col="value"><input name="row[<?=$i?>][value]" class="form-control form-control-sm" value=""></td>
                                                <td data-col="digits"><input name="row[<?=$i?>][barcode_digits]" class="form-control form-control-sm" value=""></td>
                                                <td data-col="prefix"><input name="row[<?=$i?>][barcode_prefix]" value="B" class="form-control form-control-sm"></td>
                                                <td data-col="cut"><input name="row[<?=$i?>][cut]" class="form-control form-control-sm" value=""></td>
                                                <td data-col="shape"><input name="row[<?=$i?>][shape]" class="form-control form-control-sm" value=""></td>
                                                <td data-col="color"><input name="row[<?=$i?>][color]" class="form-control form-control-sm" value=""></td>
                                                <td data-col="clarity"><input name="row[<?=$i?>][clarity]" class="form-control form-control-sm" value=""></td>
                                                <td data-col="sieve"><input name="row[<?=$i?>][sieve]" class="form-control form-control-sm" value=""></td>
                                                <td data-col="size"><input name="row[<?=$i?>][size]" class="form-control form-control-sm" value=""></td>
                                                <td data-col="stylecode"><input name="row[<?=$i?>][style_code]" class="form-control form-control-sm" value=""></td>
                                            </tr>
                                            <?php $i++; } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

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
                <div class="form-group">
                    <label>Previous Balance Amount</label>
                    <input type="text" class="form-control" id="cashPreviousBalanceAmount" value="0.00" step="0.01" placeholder="Amount to pay towards previous balance">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="border: 1px solid #ec4899; color: #ec4899; background: #fff;" data-dismiss="modal">Clear</button>
                <button type="button" class="btn" style="background: #11294b; color: #fff; border: none;" onclick="savePayment('cash')">Save</button>
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
                <div class="form-group">
                    <label>Previous Balance Amount</label>
                    <input type="text" class="form-control" id="bankPreviousBalanceAmount" value="0.00" step="0.01" placeholder="Amount to pay towards previous balance">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="border: 1px solid #ec4899; color: #ec4899; background: #fff;" data-dismiss="modal">Clear</button>
                <button type="button" class="btn" style="background: #11294b; color: #fff; border: none;" onclick="savePayment('bank')">Save</button>
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
                    <label>Previous Balance Amount</label>
                    <input type="text" class="form-control" id="chequePreviousBalanceAmount" value="0.00" step="0.01" placeholder="Amount to pay towards previous balance">
                </div>
                <div class="form-group">
                    <label>Cheque Dt.</label>
                    <div class="input-group">
                        <input type="date" class="form-control" id="chequeDate" value="<?php echo date('Y-m-d'); ?>">
                        <div class="input-group-append">
                            <button class="btn btn-sm" type="button" onclick="document.getElementById('chequeDate').value = '<?php echo date('Y-m-d'); ?>'" style="background: #f8fafc; border: 1px solid #e2e8f0;">
                                <i class="feather icon-refresh-cw"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="border: 1px solid #ec4899; color: #ec4899; background: #fff;" data-dismiss="modal">Clear</button>
                <button type="button" class="btn" style="background: #11294b; color: #fff; border: none;" onclick="savePayment('cheque')">Save</button>
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
                <div class="form-group">
                    <label>Previous Balance Amount</label>
                    <input type="text" class="form-control" id="upiPreviousBalanceAmount" value="0.00" step="0.01" placeholder="Amount to pay towards previous balance">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="border: 1px solid #ec4899; color: #ec4899; background: #fff;" data-dismiss="modal">Clear</button>
                <button type="button" class="btn" style="background: #11294b; color: #fff; border: none;" onclick="savePayment('upi')">Save</button>
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

<!-- Category Creation Modal -->
<div class="modal fade" id="categoryCreationModal" tabindex="-1" role="dialog" aria-labelledby="categoryCreationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 600px;">
        <div class="modal-content">
            <div class="modal-header" style="background: #11294b; color: #fff; border: none;">
                <h5 class="modal-title" id="categoryCreationModalLabel">Add Category</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff; opacity: 1;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding: 1.5rem;">
                <form id="categoryCreationForm">
                    <div class="form-group">
                        <label>Name *</label>
                        <input type="text" class="form-control" id="categoryName" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>Short Code</label>
                        <input type="text" class="form-control" id="categoryShortCode" name="short_code" maxlength="10">
                    </div>
                    <div class="form-group">
                        <label>Parent Category</label>
                        <select class="form-control" id="categoryParentId" name="parent_id">
                            <option value="0">None</option>
                            <?php
                            if (!empty($categories)) {
                                foreach ($categories as $cat) {
                                    echo '<option value="' . $cat['id'] . '">' . htmlspecialchars($cat['name']) . '</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Min. Qty.</label>
                                <input type="text" class="form-control" id="categoryMinQty" name="min_qty" step="0.01" value="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Max. Qty.</label>
                                <input type="text" class="form-control" id="categoryMaxQty" name="max_qty" step="0.01" value="0">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Min. Wt.</label>
                                <input type="text" class="form-control" id="categoryMinWt" name="min_wt" step="0.001" value="0">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Max. Wt.</label>
                                <input type="text" class="form-control" id="categoryMaxWt" name="max_wt" step="0.001" value="0">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="categoryIsActive" name="is_active" checked>
                            <label class="form-check-label" for="categoryIsActive">Active</label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #e2e8f0; padding: 1rem 1.5rem;">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="saveCategory()" style="background: #11294b; border: none;">Save</button>
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
                <div class="form-group">
                    <label>Previous Balance Amount</label>
                    <input type="text" class="form-control" id="cardPreviousBalanceAmount" value="0.00" step="0.01" placeholder="Amount to pay towards previous balance">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="border: 1px solid #ec4899; color: #ec4899; background: #fff;" data-dismiss="modal">Clear</button>
                <button type="button" class="btn" style="background: #11294b; color: #fff; border: none;" onclick="savePayment('card')">Save</button>
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
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Quantity</label>
                            <input type="text" class="form-control" id="metalExchangeQty" value="1" step="0.01">
                        </div>
                        <div class="form-group">
                            <label>Purity / Carat</label>
                            <input type="text" class="form-control" id="metalExchangePurity" value="1" step="0.01">
                        </div>
                        <div class="form-group">
                            <label>Rate</label>
                            <input type="text" class="form-control" id="metalExchangeRate" value="0" step="0.01">
                        </div>
                        <div class="form-group">
                            <label>Item Code</label>
                            <input type="text" class="form-control" id="metalExchangeItemCode" placeholder="Item Code">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Gross Wt</label>
                            <input type="text" class="form-control" id="metalExchangeGrossWt" value="0" step="0.001">
                        </div>
                        <div class="form-group">
                            <label>Purity Wt.</label>
                            <input type="text" class="form-control" id="metalExchangePurityWt" value="0" step="0.001" readonly>
                        </div>
                        <div class="form-group">
                            <label>Amount</label>
                            <input type="text" class="form-control" id="metalExchangeAmount" value="0.00" step="0.01" readonly>
                        </div>
                        <div class="form-group">
                            <label>Previous Balance Amount</label>
                            <input type="text" class="form-control" id="metalExchangePreviousBalanceAmount" value="0.00" step="0.01" placeholder="Amount to pay towards previous balance">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="border: 1px solid #ec4899; color: #ec4899; background: #fff;" data-dismiss="modal">Clear</button>
                <button type="button" class="btn" style="background: #11294b; color: #fff; border: none;" onclick="savePayment('metal-exchange')">Save</button>
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
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Quantity</label>
                            <input type="text" class="form-control" id="scrapQty" value="1" step="0.01">
                        </div>
                        <div class="form-group">
                            <label>Less Wt.</label>
                            <input type="text" class="form-control" id="scrapLessWt" value="0" step="0.001">
                        </div>
                        <div class="form-group">
                            <label>Purity / Carat</label>
                            <input type="text" class="form-control" id="scrapPurity" value="1" step="0.01">
                        </div>
                        <div class="form-group">
                            <label>Rate</label>
                            <input type="text" class="form-control" id="scrapRate" value="0" step="0.01">
                        </div>
                        <div class="form-group">
                            <label>Item Code</label>
                            <input type="text" class="form-control" id="scrapItemCode" placeholder="Item Code">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Gross Wt</label>
                            <input type="text" class="form-control" id="scrapGrossWt" value="0" step="0.001">
                        </div>
                        <div class="form-group">
                            <label>Net Wt.</label>
                            <input type="text" class="form-control" id="scrapNetWt" value="0" step="0.001" readonly>
                        </div>
                        <div class="form-group">
                            <label>Purity Wt.</label>
                            <input type="text" class="form-control" id="scrapPurityWt" value="0" step="0.001" readonly>
                        </div>
                        <div class="form-group">
                            <label>Amount</label>
                            <input type="text" class="form-control" id="scrapAmount" value="0.00" step="0.01" readonly>
                        </div>
                        <div class="form-group">
                            <label>Previous Balance Amount</label>
                            <input type="text" class="form-control" id="scrapPreviousBalanceAmount" value="0.00" step="0.01" placeholder="Amount to pay towards previous balance">
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" style="border: 1px solid #ec4899; color: #ec4899; background: #fff;" data-dismiss="modal">Clear</button>
                <button type="button" class="btn" style="background: #11294b; color: #fff; border: none;" onclick="savePayment('scrap')">Save</button>
            </div>
        </div>
    </div>
</div>

<?php include 'footer-script.php';?>


<script>
    // Master data for dropdowns
    const carats = <?php echo json_encode($carats); ?>;
    const locations = <?php echo json_encode($locations); ?>;
    const categories = <?php echo json_encode($categories); ?>;
    const nationalities = <?php 
        $nationalities_js = getList("SELECT id, name FROM tbl_nationalities WHERE status = 1 ORDER BY name ASC");
        echo json_encode($nationalities_js); 
    ?>;
    
    // Event delegation for modal table calculations (handles dynamically added rows)
    // This ensures calculations work even if rows are added after page load
    document.addEventListener('input', function(e) {
        if (e.target.closest('#productListBody')) {
            const row = e.target.closest('.product-row');
            if (row) {
                // Trigger calculation for any input field change
                calculateModalRowNetWeight(row);
            }
        }
    });
    
    document.addEventListener('change', function(e) {
        if (e.target.closest('#productListBody')) {
            const row = e.target.closest('.product-row');
            if (row) {
                // Trigger calculation for any select or input field change
                calculateModalRowNetWeight(row);
            }
        }
    });
    
    // Event delegation for product field clicks (handles dynamically added rows)
    document.addEventListener('click', function(e) {
        if (e.target.closest('#productListBody')) {
            const productInput = e.target.closest('[data-column="product"] input');
            if (productInput) {
                const row = e.target.closest('.product-row');
                if (row) {
                    e.stopPropagation();
                    openProductSearchModal(row);
                }
            }
        }
    });
    
    // Helper function to populate select dropdown
    function populateSelect(selectElement, data, valueField, textField, placeholder = 'Select') {
        selectElement.innerHTML = `<option value="">${placeholder}</option>`;
        if (data && Array.isArray(data)) {
            data.forEach(function(item) {
                const option = document.createElement('option');
                option.value = item[valueField];
                option.textContent = item[textField] || item[valueField];
                if (item.purity) {
                    option.setAttribute('data-purity', item.purity);
                }
                selectElement.appendChild(option);
            });
        }
    }
    
    // Global variables
    let currentMetalId = null;
    let currentMetalName = '';
    let productTableRowIndex = 0;
    
    // Handle Add Product Icon Click
    $(document).ready(function() {
        $(document).on('click', '.add-product-icon', function(e) {
            e.stopPropagation();
            $('#productCreationModal').modal('show');
            // Initialize column dropdown when modal opens
            setTimeout(function() {
                initProductModalColumnDropdown();
            }, 300);
        });
        
        // Handle Add Customer Icon Click
        $(document).on('click', '#addCustomerBtn, .add-customer-icon', function(e) {
            e.stopPropagation();
            e.preventDefault();
            $('#customerCreationModal').modal('show');
        });
        
        // Handle Add Category Icon Click
        $(document).on('click', '.add-category-icon', function(e) {
            e.stopPropagation();
            e.preventDefault();
            $('#categoryCreationModal').modal('show');
            // Load parent categories
            loadParentCategories();
        });
        
        // Customer Autocomplete/Suggestions
        let customerSearchTimeout;
        let selectedCustomerId = null;
        
        $(document).on('input', '#customerName', function() {
            const searchTerm = $(this).val().trim();
            const suggestionsDiv = $('#customerSuggestions');
            
            // Clear previous timeout
            clearTimeout(customerSearchTimeout);
            
            // Hide suggestions if search term is too short
            if (searchTerm.length < 2) {
                suggestionsDiv.hide();
                $('#customerId').val('');
                selectedCustomerId = null;
                return;
            }
            
            // Debounce search
            customerSearchTimeout = setTimeout(function() {
                $.ajax({
                    url: 'ajax/search-customers.php',
                    method: 'GET',
                    data: { q: searchTerm },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success' && response.customers && response.customers.length > 0) {
                            let html = '<div style="padding: 0.5rem; font-size: 0.85rem; color: #64748b; border-bottom: 1px solid #e2e8f0; font-weight: 600;">Select Customer:</div>';
                            
                            response.customers.forEach(function(customer) {
                                html += `
                                    <div class="customer-suggestion-item" 
                                         data-customer-id="${customer.id}" 
                                         data-customer-name="${customer.name}"
                                         style="padding: 0.75rem; cursor: pointer; border-bottom: 1px solid #f1f5f9; transition: background 0.2s;"
                                         onmouseover="this.style.background='#f8fafc'" 
                                         onmouseout="this.style.background='#fff'">
                                        <div style="font-weight: 600; color: #1e293b; font-size: 0.9rem;">${customer.name}</div>
                                        ${customer.alternate_name ? '<div style="font-size: 0.8rem; color: #64748b; margin-top: 0.25rem;">' + customer.alternate_name + '</div>' : ''}
                                        ${customer.mobile_no ? '<div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.15rem;"><i class="feather icon-phone" style="font-size: 0.7rem;"></i> ' + customer.mobile_no + '</div>' : ''}
                                    </div>
                                `;
                            });
                            
                            suggestionsDiv.html(html).show();
                        } else {
                            suggestionsDiv.hide();
                        }
                    },
                    error: function() {
                        suggestionsDiv.hide();
                    }
                });
            }, 300);
        });
        
        // Handle customer selection from suggestions
        $(document).on('click', '.customer-suggestion-item', function() {
            const customerId = $(this).data('customer-id');
            const customerName = $(this).data('customer-name');
            
            $('#customerName').val(customerName);
            $('#customerId').val(customerId);
            selectedCustomerId = customerId;
            $('#customerSuggestions').hide();
            
            // Load customer balance when customer is selected (with small delay to ensure DOM is updated)
            setTimeout(function() {
                if (typeof loadCustomerBalance === 'function') {
                    loadCustomerBalance();
                }
            }, 100);
        });
        
        // Load customer balance when customer name changes (blur/change events)
        const customerNameField = document.getElementById('customerName');
        if (customerNameField) {
            customerNameField.addEventListener('blur', function() {
                if (typeof loadCustomerBalance === 'function') {
                    loadCustomerBalance();
                }
            });
            customerNameField.addEventListener('change', function() {
                if (typeof loadCustomerBalance === 'function') {
                    loadCustomerBalance();
                }
            });
        }
        
        // Hide suggestions when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#customerName, #customerSuggestions, #addCustomerBtn').length) {
                $('#customerSuggestions').hide();
            }
        });
        
        // Handle keyboard navigation in suggestions
        $(document).on('keydown', '#customerName', function(e) {
            const suggestionsDiv = $('#customerSuggestions');
            const visibleItems = suggestionsDiv.find('.customer-suggestion-item:visible');
            
            if (visibleItems.length === 0) return;
            
            const currentFocused = suggestionsDiv.find('.customer-suggestion-item.focused');
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
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
                    focused.click();
                }
            } else if (e.key === 'Escape') {
                suggestionsDiv.hide();
            }
        });
        
        // Handle Add Share Holder Button Click
        $(document).on('click', '#addShareHolderBtn', function(e) {
            e.preventDefault();
            addShareHolderRow();
        });
        
        // Handle branch tag removal
        $(document).on('click', '.remove-tag', function(e) {
            e.stopPropagation();
            const tag = $(this).closest('.branch-tag');
            const branchId = tag.data('branch-id');
            tag.next('input[type="hidden"]').remove();
            tag.remove();
        });
        
        // Handle Product Modal Column Settings Button
        $(document).on('click', '#productModalColumnSettingsBtn', function(e) {
            e.stopPropagation();
            e.preventDefault();
            const dropdown = $('#productModalColumnsDropdown');
            dropdown.toggleClass('show');
            if (dropdown.hasClass('show') && $('#productModalColumnsList').children().length === 0) {
                initProductModalColumnDropdown();
            }
        });
        
        // Close dropdown when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#productModalColumnSettingsBtn').length && 
                !$(e.target).closest('#productModalColumnsDropdown').length) {
                $('#productModalColumnsDropdown').removeClass('show');
            }
        });
        
        // Initialize column dropdown when modal is shown
        $('#productCreationModal').on('shown.bs.modal', function() {
            initProductModalColumnDropdown();
        });
    });
    
    // Initialize Product Modal Column Dropdown
    function initProductModalColumnDropdown() {
        const columnsList = $('#productModalColumnsList');
        columnsList.empty();
        
        const columnDefinitions = [
            { key: 'metal', label: 'Metal', visible: true },
            { key: 'serialized', label: 'Serialized Barcode', visible: true },
            { key: 'hsn', label: 'HSN', visible: true },
            { key: 'sku', label: 'SKU/Product Code', visible: true },
            { key: 'making', label: 'Making on', visible: true },
            { key: 'diamond', label: 'Diamond Category', visible: true },
            { key: 'carat', label: 'Carat', visible: true },
            { key: 'discount', label: 'Discount', visible: true },
            { key: 'weight', label: 'Weight', visible: true },
            { key: 'purity', label: 'Purity/K', visible: true },
            { key: 'qty', label: 'Qty', visible: true },
            { key: 'finalwt', label: 'Final Wt.', visible: true },
            { key: 'rate', label: 'Rate', visible: true },
            { key: 'value', label: 'Value', visible: true },
            { key: 'digits', label: 'No of Digits', visible: true },
            { key: 'prefix', label: 'Barcode Prefix', visible: true },
            { key: 'cut', label: 'Cut', visible: true },
            { key: 'shape', label: 'Shape', visible: true },
            { key: 'color', label: 'Color', visible: true },
            { key: 'clarity', label: 'Clarity', visible: true },
            { key: 'sieve', label: 'Sieve', visible: true },
            { key: 'size', label: 'Size', visible: true },
            { key: 'stylecode', label: 'Style Code', visible: true }
        ];
        
        columnDefinitions.forEach(col => {
            const item = $(`
                <div class="columns-dropdown-item">
                    <input type="checkbox" id="productModal_col_${col.key}" data-col="${col.key}" ${col.visible ? 'checked' : ''}>
                    <label for="productModal_col_${col.key}">${col.label}</label>
                </div>
            `);
            columnsList.append(item);
        });
        
        // Column search
        $('#productModalColumnSearch').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();
            $('.columns-dropdown-item').each(function() {
                const label = $(this).find('label').text().toLowerCase();
                $(this).toggle(label.includes(searchTerm));
            });
        });
        
        // Toggle column visibility
        $(document).off('change', '#productModalColumnsList input[type="checkbox"]').on('change', '#productModalColumnsList input[type="checkbox"]', function() {
            const colKey = $(this).data('col');
            const isVisible = $(this).is(':checked');
            toggleProductModalColumnVisibility(colKey, isVisible);
        });
    }
    
    // Toggle column visibility in Product Modal
    function toggleProductModalColumnVisibility(colKey, isVisible) {
        $('#productCreationModal').find(`th[data-col="${colKey}"], td[data-col="${colKey}"]`).each(function() {
            if (isVisible) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    }
    
    // Function to clear product form
    function clearProductForm() {
        document.getElementById('productCreationForm').reset();
        
        // Reset modal title
        document.getElementById('productCreationModalLabel').textContent = 'Add Product';
        
        // Remove edit product ID if exists
        const editProductIdInput = document.getElementById('editProductId');
        if (editProductIdInput) {
            editProductIdInput.remove();
        }
        
        // Clear current editing row ID
        window.currentEditingRowId = null;
        
        // Reset branch tags to all branches
        const branchContainer = document.getElementById('branchTagsContainer');
        branchContainer.innerHTML = '';
        <?php 
        if (!empty($branches)) {
            foreach($branches as $branch) {
                $branch_name = htmlspecialchars($branch['name']);
                echo "branchContainer.innerHTML += '<span class=\"branch-tag\" data-branch-id=\"{$branch['id']}\">{$branch_name} <span class=\"remove-tag\">×</span></span>';";
                echo "branchContainer.innerHTML += '<input type=\"hidden\" name=\"branch_ids[]\" value=\"{$branch['id']}\">';";
            }
        }
        ?>
        branchContainer.innerHTML += '<span class="add-branch-btn"><i class="feather icon-plus"></i></span>';
    }
    
    // Function to save product
    function saveProduct() {
        const form = document.getElementById('productCreationForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        const formData = new FormData(form);
        
        // Show loading
        const saveBtn = event.target;
        const originalText = saveBtn.innerHTML;
        saveBtn.innerHTML = '<i class="feather icon-loader spin"></i> Saving...';
        saveBtn.disabled = true;
        
        fetch('product-save.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            // Check if response is ok
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            // Try to parse as JSON
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
            // Check for success status
            if (data.status === 'success' || data.success === true) {
                // Show success message (without "Error:" prefix)
                const isEdit = document.getElementById('editProductId') && document.getElementById('editProductId').value;
                alert(data.message || (isEdit ? 'Product updated successfully!' : 'Product created successfully!'));
                
                // If editing a row in the product list, update it
                if (window.currentEditingRowId) {
                    const rowId = window.currentEditingRowId;
                    // Reload product details and update the row
                    const row = document.getElementById(rowId);
                    if (row) {
                        const productId = row.getAttribute('data-product-id');
                        const characteristicId = row.getAttribute('data-characteristic-id');
                        if (productId) {
                            // Fetch updated product details
                            const url = 'ajax/get-product-details.php?product_id=' + productId + (characteristicId ? '&characteristic_id=' + characteristicId : '');
                            fetch(url)
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success && data.product) {
                                        // Update the product name in the row
                                        const descCell = row.querySelector('[data-column="description"] a');
                                        if (descCell) {
                                            descCell.textContent = data.product.name || '';
                                        }
                                    }
                                });
                        }
                    }
                    window.currentEditingRowId = null;
                }
                
                // Close the product creation modal
                $('#productCreationModal').modal('hide');
                
                // Clear the form
                clearProductForm();
                
                // Don't refresh any product lists - just save and close
            } else {
                // Show error message
                alert('Error: ' + (data.message || 'Failed to save product'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error saving product: ' + error.message);
        })
        .finally(() => {
            saveBtn.innerHTML = originalText;
            saveBtn.disabled = false;
        });
    }
    
    // Utility function to escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    // Customer Creation Modal Functions
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
    });
    
    // Share Holders Management
    let shareHolderRowIndex = 0;
    let shareHoldersData = [];
    let shareHolderFiles = [];
    
    // Add Share Holder Row
    function addShareHolderRow() {
        console.log('addShareHolderRow called');
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
        
        console.log('Share holder row added:', shareHolderRowIndex);
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
    
    // Category Management Functions
    function saveCategory() {
        const name = document.getElementById('categoryName').value.trim();
        if (!name) {
            alert('Category name is required');
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('name', name);
        formData.append('short_code', document.getElementById('categoryShortCode').value.trim());
        formData.append('parent_id', document.getElementById('categoryParentId').value);
        formData.append('min_qty', document.getElementById('categoryMinQty').value || 0);
        formData.append('max_qty', document.getElementById('categoryMaxQty').value || 0);
        formData.append('min_wt', document.getElementById('categoryMinWt').value || 0);
        formData.append('max_wt', document.getElementById('categoryMaxWt').value || 0);
        formData.append('is_active', document.getElementById('categoryIsActive').checked ? 1 : 0);
        
        // Show loading
        const saveBtn = event.target;
        const originalText = saveBtn.innerHTML;
        saveBtn.innerHTML = '<i class="feather icon-loader spin"></i> Saving...';
        saveBtn.disabled = true;
        
        fetch('ajax/category.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                alert(data.message || 'Category added successfully!');
                
                // Close modal
                $('#categoryCreationModal').modal('hide');
                
                // Clear form
                clearCategoryForm();
                
                // Update all category dropdowns
                updateCategoryDropdowns(data.id, data.name);
            } else {
                alert('Error: ' + (data.message || 'Failed to create category'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error saving category: ' + error.message);
        })
        .finally(() => {
            saveBtn.innerHTML = originalText;
            saveBtn.disabled = false;
        });
    }
    
    function clearCategoryForm() {
        document.getElementById('categoryCreationForm').reset();
        document.getElementById('categoryIsActive').checked = true;
    }
    
    function loadParentCategories() {
        fetch('ajax/category.php?action=list')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success' && data.categories) {
                const parentSelect = document.getElementById('categoryParentId');
                if (parentSelect) {
                    // Keep the "None" option
                    const noneOption = parentSelect.querySelector('option[value="0"]');
                    parentSelect.innerHTML = '';
                    if (noneOption) {
                        parentSelect.appendChild(noneOption);
                    } else {
                        const opt = document.createElement('option');
                        opt.value = '0';
                        opt.textContent = 'None';
                        parentSelect.appendChild(opt);
                    }
                    
                    // Add categories
                    data.categories.forEach(function(cat) {
                        const opt = document.createElement('option');
                        opt.value = cat.id;
                        opt.textContent = cat.name;
                        parentSelect.appendChild(opt);
                    });
                }
            }
        })
        .catch(error => {
            console.error('Error loading parent categories:', error);
        });
    }
    
    function updateCategoryDropdowns(categoryId, categoryName) {
        // Update all category dropdowns in the product selection table
        const categorySelects = document.querySelectorAll('.category-select');
        categorySelects.forEach(function(select) {
            // Check if option already exists
            let exists = false;
            for (let i = 0; i < select.options.length; i++) {
                if (select.options[i].value == categoryId) {
                    exists = true;
                    break;
                }
            }
            
            if (!exists) {
                const option = document.createElement('option');
                option.value = categoryId;
                option.textContent = categoryName;
                select.appendChild(option);
            }
        });
        
        // Also update parent category dropdown in modal
        const parentSelect = document.getElementById('categoryParentId');
        if (parentSelect) {
            const option = document.createElement('option');
            option.value = categoryId;
            option.textContent = categoryName;
            parentSelect.appendChild(option);
        }
    }
    
    // Initialize category tabs (only in modal)
    function initCategoryTabs() {
        document.querySelectorAll('.category-tab-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                // Remove active class from all tabs
                document.querySelectorAll('.category-tab-btn').forEach(function(b) {
                    b.classList.remove('active');
                });
                // Add active class to clicked tab
                this.classList.add('active');
                currentMetalId = this.getAttribute('data-metal-id');
                currentMetalName = this.getAttribute('data-metal-name');
                // Don't load products automatically - user will add products manually using "Add Product" button
                // Products will only be added when user clicks "Add Product" button
            });
        });
        
        // Set initial metal (first tab)
        const firstTab = document.querySelector('.category-tab-btn.active');
        if (firstTab) {
            currentMetalId = firstTab.getAttribute('data-metal-id');
            currentMetalName = firstTab.getAttribute('data-metal-name');
        }
    }
    
    // Open product selection modal
    function openProductModal() {
        try {
            console.log('openProductModal called');
            
            // Show modal first, then initialize
            const modal = document.getElementById('productSelectionModal');
            if (!modal) {
                console.error('Modal element not found');
                alert('Modal not found. Please refresh the page.');
                return;
            }
            
            // Show modal using jQuery or Bootstrap
            if (typeof jQuery !== 'undefined' && jQuery.fn.modal) {
                console.log('Opening modal with jQuery');
                jQuery('#productSelectionModal').modal('show');
            } else {
                console.log('Opening modal with fallback method');
                // Fallback if jQuery/Bootstrap not available
                modal.style.display = 'block';
                modal.classList.add('show');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('modal-open');
                // Add backdrop
                let backdrop = document.getElementById('modalBackdrop');
                if (!backdrop) {
                    backdrop = document.createElement('div');
                    backdrop.className = 'modal-backdrop fade show';
                    backdrop.id = 'modalBackdrop';
                    document.body.appendChild(backdrop);
                }
            }
            
            // Initialize tabs after modal is shown
            try {
                initCategoryTabs();
            } catch(e) {
                console.log('Error initializing category tabs:', e);
            }
            
            // Clear search input
            const searchInput = document.getElementById('modalProductSearchInput');
            if (searchInput) searchInput.value = '';
            
            // Clear all modal fields (only fields that exist)
            try {
                clearModalFields();
            } catch(e) {
                console.log('Error clearing modal fields:', e);
            }
            
            // Don't load products automatically - user will add products manually using "Add Product" button
            // Clear the table body to show blank state
            const tbody = document.getElementById('productListBody');
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="70" class="text-center text-muted py-4">Click "Add Product" button to add products for billing...</td></tr>';
            }
        } catch(error) {
            console.error('Error opening product modal:', error);
            alert('Error opening product selection: ' + error.message);
        }
    }
    
    // Add empty row when clicking Add Item button
    function addEmptyRow() {
        const tbody = document.getElementById('productTableBody');
        const emptyRow = tbody.querySelector('.no-drag');
        if (emptyRow) {
            emptyRow.remove();
        }
        
        productTableRowIndex++;
        const rowId = 'product-row-' + productTableRowIndex;
        
        const row = document.createElement('tr');
        row.id = rowId;
        row.setAttribute('data-product-id', '');
        row.setAttribute('data-characteristic-id', '');
        
        row.innerHTML = `
            <td data-column="print-barcode" style="text-align: center; width: 50px;">
                <i class="feather icon-printer" style="cursor: pointer; font-size: 0.9rem; color: #c5a864;" onclick="printBarcodeFromRow(this)" title="Print Barcode"></i>
            </td>
            <td data-column="barcode" style="text-align: center; position: relative;">
                <div class="image-placeholder" style="width: 30px; height: 30px; background: #f1f5f9; border-radius: 4px; display: flex; align-items: center; justify-content: center; margin: 0 auto; cursor: pointer;" onclick="printBarcodeFromRow(this)" title="Click to print barcode">
                    <i class="feather icon-image" style="font-size: 0.9rem; color: #94a3b8;"></i>
                </div>
            </td>
            <td data-column="description" class="product-select-cell" style="cursor: pointer; color: #11294b; position: relative;">
                <a href="javascript:void(0)" style="color: #94a3b8; font-style: italic; text-decoration: underline;">Click to select product</a>
            </td>
            <td data-column="quantity" style="text-align: center; color: #11294b;">1.00</td>
            <td data-column="gross-wt" style="text-align: center; color: #11294b;">0.0</td>
            <td data-column="final-wt" style="text-align: center; color: #11294b;">0.0</td>
            <td data-column="net-wt" style="text-align: center; color: #11294b;">0.0</td>
            <td data-column="pure-wt" style="text-align: center; color: #11294b;">0.0</td>
            <td data-column="making" style="text-align: center; color: #11294b;">0</td>
            <td data-column="design-no" style="text-align: center; color: #11294b;">0</td>
            <td data-column="tax" style="text-align: center; color: #11294b;">0</td>
            <td data-column="amount" style="text-align: center; font-weight: 600; color: #11294b;">0.00</td>
            <td data-column="net-amt" style="text-align: center; color: #11294b;">0.00</td>
            <td data-column="net-amt-tax" style="text-align: center; color: #11294b;">0.00</td>
            <td data-column="stone-charges" style="text-align: center; color: #11294b;">0.00</td>
            <td data-column="other-charges" style="text-align: center; color: #11294b;">0.00</td>
            <td data-column="diamond-value" style="text-align: center; color: #11294b;">0.00</td>
            <td data-column="gemstone-value" style="text-align: center; color: #11294b;">0.00</td>
            <td>
                <div class="action-btns">
                    <button type="button" class="btn-edit" onclick="editProductRow('${rowId}')" title="Edit">
                        <i class="feather icon-refresh-cw"></i>
                    </button>
                    <button type="button" class="btn-delete" onclick="deleteProductRow('${rowId}')" title="Delete">
                        <i class="feather icon-trash-2"></i>
                    </button>
                </div>
            </td>
        `;
        
        tbody.appendChild(row);
        
        // Add click handler to Description column
        const descriptionCell = row.querySelector('[data-column="description"]');
        if (descriptionCell) {
            descriptionCell.addEventListener('click', function() {
                openProductModalForRow(rowId);
            });
        }
        
        // Add event listeners for calculations
        addRowCalculationListeners(row);
    }
    
    // Add empty product row to modal table
    // Generate unique random barcode
    function generateUniqueBarcode() {
        // Get all existing barcodes from both Product Selection table and Product List table
        const existingBarcodes = new Set();
        
        // Check Product Selection table (productListBody)
        const productSelectionBarcodes = document.querySelectorAll('#productListBody [data-column="barcode"] input');
        productSelectionBarcodes.forEach(input => {
            if (input.value && input.value.trim() !== '') {
                existingBarcodes.add(input.value.trim());
            }
        });
        
        // Check Product List table (productTableBody)
        const productListBarcodes = document.querySelectorAll('#productTableBody [data-column="barcode"]');
        productListBarcodes.forEach(cell => {
            const barcodeText = cell.textContent.trim();
            if (barcodeText && barcodeText !== '') {
                existingBarcodes.add(barcodeText);
            }
        });
        
        // Generate random barcode: B followed by 8 random digits
        let barcode;
        let attempts = 0;
        const maxAttempts = 100;
        
        do {
            // Generate B + 8 random digits
            const randomNum = Math.floor(10000000 + Math.random() * 90000000); // 8-digit number between 10000000 and 99999999
            barcode = 'B' + randomNum.toString();
            attempts++;
            
            // If we've tried too many times, add timestamp to ensure uniqueness
            if (attempts >= maxAttempts) {
                const timestamp = Date.now().toString().slice(-6); // Last 6 digits of timestamp
                barcode = 'B' + timestamp + Math.floor(Math.random() * 100).toString().padStart(2, '0');
                break;
            }
        } while (existingBarcodes.has(barcode));
        
        return barcode;
    }
    
    function addEmptyProductRow() {
        const tbody = document.getElementById('productListBody');
        if (!tbody) return;
        
        // Remove the "no products" message if it exists
        const emptyRow = tbody.querySelector('tr:not(.product-row)');
        if (emptyRow) {
            emptyRow.remove();
        }
        
        // Generate unique barcode for this product
        const uniqueBarcode = generateUniqueBarcode();
        
        // Create a new empty product row
        const row = document.createElement('tr');
        row.className = 'product-row';
        row.setAttribute('data-product-id', '');
        row.setAttribute('data-characteristic-id', '');
        
        // Generate the row HTML with all columns (empty values)
        row.innerHTML = `
            <td data-column="checkbox" style="text-align: center; position: sticky; left: 0; background: #fff; z-index: 1;">
                <input type="checkbox" class="product-checkbox" data-product-id="" data-characteristic-id="">
            </td>
            <td data-column="id"></td>
            <td data-column="rfid"><input type="text" class="form-control form-control-sm" value="" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="voucher-type"><input type="text" class="form-control form-control-sm" value="" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="barcode" style="position: relative;">
                <div style="display: flex; align-items: center; gap: 5px;">
                    <input type="text" class="form-control form-control-sm barcode-input" value="${uniqueBarcode}" style="width: 100px; font-size: 0.7rem;" onclick="printBarcode('${uniqueBarcode}', event)" readonly>
                    <i class="feather icon-printer barcode-print-icon" style="cursor: pointer; font-size: 0.9rem; color: #c5a864; flex-shrink: 0;" onclick="printBarcode('${uniqueBarcode}', event)" title="Print Barcode"></i>
                </div>
            </td>
            <td data-column="design-no"><input type="text" class="form-control form-control-sm" value="" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="huid"><input type="text" class="form-control form-control-sm" value="" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="category"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="">Select</option></select></td>
            <td data-column="calculation"><select class="form-control form-control-sm" style="width: 120px; font-size: 0.7rem;"><option value="Weight X Rate" selected>Weight X Rate</option><option value="Rate X Gross Wt">Rate X Gross Wt</option><option value="Rate X Purity Wt">Rate X Purity Wt</option><option value="Rate X Net Wt">Rate X Net Wt</option><option value="Rate X Final Wt">Rate X Final Wt</option><option value="Fix">Fix</option><option value="Stone Charge">Stone Charge</option></select></td>
            <td data-column="product"><input type="text" class="form-control form-control-sm" value="" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="location"><select class="form-control form-control-sm location-select" style="width: 100px; font-size: 0.7rem;"><option value="">Select</option></select></td>
            <td data-column="quantity"><input type="text" class="form-control form-control-sm" value="1" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="carat"><select class="form-control form-control-sm carat-select" style="width: 80px; font-size: 0.7rem;"><option value="">Select</option></select></td>
            <td data-column="pkt-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="pkt-less-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="requested-purity"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 120px; font-size: 0.7rem;"></td>
            <td data-column="requested"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="gross-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="less-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="gold-loss1"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="gold-loss2"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="setting-charge"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 110px; font-size: 0.7rem;"></td>
            <td data-column="net-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" readonly style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="purity"><input type="text" class="form-control form-control-sm" value="1" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="purity-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" readonly style="width: 90px; font-size: 0.7rem;"></td>
            <td data-column="wastage-per"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="wastage-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="final-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="alloy-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="rate"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="metal-value"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="metal-cost"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem; color: #7b1fa2;"></td>
            <td data-column="amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="discount-type"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="On Amount" selected>On Amount</option><option value="On Percentage">On Percentage</option></select></td>
            <td data-column="discount-per"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="discount-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="discount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="discount-type2"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="On Amount" selected>On Amount</option><option value="On Percentage">On Percentage</option></select></td>
            <td data-column="discount-per2"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="discount-amount2"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="discounted-amt"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
            <td data-column="discounted-per"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 120px; font-size: 0.7rem;"></td>
            <td data-column="making-type"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="Fix" selected>Fix</option><option value="Percentage">Percentage</option></select></td>
            <td data-column="making-rate"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="making-discount-amt"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 130px; font-size: 0.7rem;"></td>
            <td data-column="making-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="making-actual-value"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
            <td data-column="making-cost"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 110px; font-size: 0.7rem;"></td>
            <td data-column="min-price"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="minimum"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="stone-charge-type"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="Fix" selected>Fix</option><option value="Per Gram">Per Gram</option></select></td>
            <td data-column="stone-weight"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 110px; font-size: 0.7rem;"></td>
            <td data-column="stone-rate"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="stone-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
            <td data-column="stone-cost"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="diamond-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 120px; font-size: 0.7rem;"></td>
            <td data-column="purchase-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 130px; font-size: 0.7rem;"></td>
                        <td data-column="sale-percent"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;" placeholder="%" title="Sale % on Purchase Amount"></td>            <td data-column="sale-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 110px; font-size: 0.7rem;"></td>
            <td data-column="sale-amount-with"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 130px; font-size: 0.7rem;"></td>
            <td data-column="net-amt"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="tax"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="other-charge-type"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="Fix" selected>Fix</option><option value="Percentage">Percentage</option></select></td>
            <td data-column="other-weight"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 110px; font-size: 0.7rem;"></td>
            <td data-column="other-rate"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="other-info"><input type="text" class="form-control form-control-sm" value="" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="other-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
            <td data-column="hallmark-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 130px; font-size: 0.7rem;"></td>
            <td data-column="hallmark-rate"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 120px; font-size: 0.7rem;"></td>
            <td data-column="net-amt-tax"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
            <td data-column="reverse"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="actions" style="text-align: center;">
                <i class="feather icon-edit" style="cursor: pointer; font-size: 0.8rem; color: #c5a864; margin-right: 8px;" onclick="editProductRowInTable(this)" title="Edit Row"></i>
                <i class="feather icon-trash-2" style="cursor: pointer; font-size: 0.8rem; color: #ef4444;" onclick="deleteProductRowFromModal(this)" title="Delete Row"></i>
            </td>
        `;
        
        // Append row to tbody
        tbody.appendChild(row);
        
        // Populate dropdowns
        const caratSelect = row.querySelector('.carat-select');
        if (caratSelect) {
            populateSelect(caratSelect, carats, 'id', 'name', 'Select Carat');
        }
        
        const locationSelect = row.querySelector('.location-select');
        if (locationSelect) {
            populateSelect(locationSelect, locations, 'id', 'name', 'Select Location');
        }
        
        // Populate category dropdown
        const categorySelect = row.querySelector('[data-column="category"] select');
        if (categorySelect && typeof categories !== 'undefined') {
            populateSelect(categorySelect, categories, 'id', 'name', 'Select Category');
            categorySelect.classList.add('category-select');
        }
        
        // Add calculation type change listener
        const calculationSelect = row.querySelector('[data-column="calculation"] select');
        if (calculationSelect) {
            calculationSelect.addEventListener('change', function() {
                calculateModalRowNetWeight(row);
            });
        }
        
        // Add checkbox click handler
        const checkbox = row.querySelector('.product-checkbox');
        if (checkbox) {
            checkbox.addEventListener('change', function() {
                updateRowSelection(row, this.checked);
            });
        }
        
        // Add row double-click handler to edit row
        row.addEventListener('dblclick', function(e) {
            // Don't edit if clicking on checkbox, action buttons, or any input/select/textarea elements
            if (e.target.type === 'checkbox' || 
                e.target.closest('[data-column="actions"]') ||
                e.target.tagName === 'INPUT' ||
                e.target.tagName === 'SELECT' ||
                e.target.tagName === 'TEXTAREA' ||
                e.target.closest('input') ||
                e.target.closest('select') ||
                e.target.closest('textarea')) {
                return;
            }
            editProductRowInTable(row);
        });
        
        // Add row click handler (but not on product field)
        row.addEventListener('click', function(e) {
            // Don't toggle if clicking on product field, checkbox, or action buttons
            if (e.target.closest('[data-column="product"]') || e.target.type === 'checkbox' || e.target.closest('[data-column="actions"]')) {
                if (e.target.closest('[data-column="product"]')) {
                    // Open product search modal
                    openProductSearchModal(row);
                }
                return;
            }
            checkbox.checked = !checkbox.checked;
            updateRowSelection(row, checkbox.checked);
        });
        row.style.cursor = 'pointer';
        
        // Add calculation listeners
        addModalRowCalculationListeners(row);
        
        // Add click handler to Product field
        const productInput = row.querySelector('[data-column="product"] input');
        if (productInput) {
            productInput.addEventListener('click', function(e) {
                e.stopPropagation();
                openProductSearchModal(row);
            });
            productInput.style.cursor = 'pointer';
            productInput.readOnly = true; // Make it read-only so user must select from modal
        }
        
        // Calculate initial values
        calculateModalRowNetWeight(row);
        
        function updateRowSelection(row, isSelected) {
            if (isSelected) {
                row.classList.add('selected');
                row.style.backgroundColor = '#fff3cd';
            } else {
                row.classList.remove('selected');
                row.style.backgroundColor = '';
            }
        }
    }
    
    // Open product search modal for selecting a product
    let currentProductRow = null;
    let productJustSaved = false; // Flag to track if product was just saved
    function openProductSearchModal(row) {
        currentProductRow = row;
        
        // Create modal HTML
        const modalHtml = `
            <div id="productSearchModal" style="
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: center;
            ">
                <div style="
                    background: white;
                    border-radius: 8px;
                    padding: 20px;
                    max-width: 600px;
                    width: 90%;
                    max-height: 80vh;
                    overflow: hidden;
                    display: flex;
                    flex-direction: column;
                ">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-shrink: 0;">
                        <h5 style="margin: 0;">Search and Select Product</h5>
                        <button id="closeProductSearchModal" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">×</button>
                    </div>
                    <div style="margin-bottom: 15px; flex-shrink: 0;">
                        <input type="text" id="productSearchInput" placeholder="Search by name, article, or SKU..." 
                               class="form-control" style="width: 100%; padding: 0.5rem;">
                    </div>
                    <div id="productSearchResults" style="
                        flex: 1;
                        overflow-y: auto;
                        border: 1px solid #e2e8f0;
                        border-radius: 4px;
                        padding: 10px;
                    ">
                        <div class="text-muted text-center" style="padding: 20px;">Type to search products...</div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        const existingModal = document.getElementById('productSearchModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Add modal to body
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Focus on search input
        const searchInput = document.getElementById('productSearchInput');
        if (searchInput) {
            searchInput.focus();
        }
        
        // Close modal handlers
        document.getElementById('closeProductSearchModal').addEventListener('click', closeProductSearchModal);
        document.getElementById('productSearchModal').addEventListener('click', function(e) {
            if (e.target.id === 'productSearchModal') {
                closeProductSearchModal();
            }
        });
        
        // Search functionality
        let searchTimeout;
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const searchTerm = this.value.trim();
                
                searchTimeout = setTimeout(function() {
                    if (searchTerm.length >= 2) {
                        searchProducts(searchTerm);
                    } else if (searchTerm.length === 0) {
                        document.getElementById('productSearchResults').innerHTML = '<div class="text-muted text-center" style="padding: 20px;">Type to search products...</div>';
                    }
                }, 300);
            });
        }
        
        // Load initial products (empty search)
        searchProducts('');
    }
    
    // Search products via AJAX
    function searchProducts(searchTerm) {
        const resultsDiv = document.getElementById('productSearchResults');
        resultsDiv.innerHTML = '<div class="text-muted text-center" style="padding: 20px;">Searching...</div>';
        
        const url = 'ajax/search-products.php?search=' + encodeURIComponent(searchTerm) + '&limit=50';
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.products && data.products.length > 0) {
                    let html = '<div style="display: flex; flex-direction: column; gap: 8px;">';
                    data.products.forEach(function(product) {
                        const productName = product.name + (product.metal_name ? ' - ' + product.metal_name : '');
                        const displayText = productName + (product.article ? ' (' + product.article + ')' : '');
                        html += `
                            <div class="product-search-item" 
                                 data-product-id="${product.id}" 
                                 data-characteristic-id="${product.characteristic_id || ''}"
                                 style="
                                     padding: 12px;
                                     border: 1px solid #e2e8f0;
                                     border-radius: 4px;
                                     cursor: pointer;
                                     transition: all 0.2s;
                                     background: #fff;
                                 "
                                 onmouseover="this.style.background='#f8fafc'; this.style.borderColor='#c5a864';"
                                 onmouseout="this.style.background='#fff'; this.style.borderColor='#e2e8f0';"
                                 onclick="selectProductFromSearch(${JSON.stringify(product).replace(/"/g, '&quot;')})">
                                <div style="font-weight: 600; color: #1e293b; font-size: 0.9rem;">${escapeHtml(displayText)}</div>
                                ${product.sku_code ? '<div style="font-size: 0.8rem; color: #64748b; margin-top: 4px;">SKU: ' + escapeHtml(product.sku_code) + '</div>' : ''}
                                ${product.opening_weight ? '<div style="font-size: 0.75rem; color: #94a3b8; margin-top: 2px;">Weight: ' + product.opening_weight + ' | Purity: ' + (product.opening_purity || 1) + '</div>' : ''}
                            </div>
                        `;
                    });
                    html += '</div>';
                    resultsDiv.innerHTML = html;
                } else {
                    resultsDiv.innerHTML = '<div class="text-muted text-center" style="padding: 20px;">No products found</div>';
                }
            })
            .catch(error => {
                console.error('Error searching products:', error);
                resultsDiv.innerHTML = '<div class="text-danger text-center" style="padding: 20px;">Error searching products</div>';
            });
    }
    
    // Select product from search results
    function selectProductFromSearch(product) {
        if (!currentProductRow) return;
        
        // Populate row with product data
        populateRowWithProduct(currentProductRow, product);
        
        // Close modal
        closeProductSearchModal();
    }
    
    // Populate row with product data
    function populateRowWithProduct(row, product) {
        // Update product name
        const productInput = row.querySelector('[data-column="product"] input');
        if (productInput) {
            const productName = product.name + (product.metal_name ? ' - ' + product.metal_name : '');
            productInput.value = productName;
        }
        
        // Update row data attributes
        row.setAttribute('data-product-id', product.id || '');
        row.setAttribute('data-characteristic-id', product.characteristic_id || '');
        
        // Update checkbox
        const checkbox = row.querySelector('.product-checkbox');
        if (checkbox) {
            checkbox.setAttribute('data-product-id', product.id || '');
            checkbox.setAttribute('data-characteristic-id', product.characteristic_id || '');
        }
        
        // Update ID column
        const idCell = row.querySelector('[data-column="id"]');
        if (idCell) {
            idCell.textContent = product.id || '';
        }
        
        // Generate and set barcode if empty
        const barcodeInput = row.querySelector('[data-column="barcode"] input');
        if (barcodeInput) {
            if (!barcodeInput.value || barcodeInput.value.trim() === '') {
                barcodeInput.value = generateUniqueBarcode();
            }
        }
        
        // Update Design No
        const designNoInput = row.querySelector('[data-column="design-no"] input');
        if (designNoInput && product.article) {
            designNoInput.value = product.article;
        }
        
        // Update Gross Weight
        const grossWtInput = row.querySelector('[data-column="gross-wt"] input');
        if (grossWtInput && product.opening_weight) {
            grossWtInput.value = product.opening_weight;
        }
        
        // Update Purity
        const purityInput = row.querySelector('[data-column="purity"] input');
        if (purityInput && product.opening_purity) {
            purityInput.value = product.opening_purity;
        }
        
        // Update Final Weight
        const finalWtInput = row.querySelector('[data-column="final-wt"] input');
        if (finalWtInput && product.final_weight) {
            finalWtInput.value = product.final_weight;
        } else if (finalWtInput && product.opening_weight) {
            finalWtInput.value = product.opening_weight;
        }
        
        // Update Rate
        const rateInput = row.querySelector('[data-column="rate"] input');
        if (rateInput && product.rate) {
            rateInput.value = product.rate;
        }
        
        // Trigger calculation to update all calculated fields
        calculateModalRowNetWeight(row);
    }
    
    // Close product search modal
    function closeProductSearchModal() {
        const modal = document.getElementById('productSearchModal');
        if (modal) {
            modal.remove();
        }
        currentProductRow = null;
    }
    
    // Delete product row from modal table
    function deleteProductRowFromModal(iconElement) {
        if (!iconElement) return;
        
        const row = iconElement.closest('.product-row');
        if (!row) return;
        
        // Confirm deletion
        if (confirm('Are you sure you want to delete this row?')) {
            row.remove();
            
            // Check if table is now empty
            const tbody = document.getElementById('productListBody');
            if (tbody && tbody.querySelectorAll('.product-row').length === 0) {
                tbody.innerHTML = '<tr><td colspan="70" class="text-center text-muted py-4">Click "Add Product" button to add products for billing...</td></tr>';
            }
        }
    }
    
    // Load products by metal
    function loadProducts(metalId, search = '') {
        const tbody = document.getElementById('productListBody');
        tbody.innerHTML = '<tr><td colspan="70" class="text-center text-muted py-4">Loading products...</td></tr>';
        
        // Use jQuery if available, otherwise use fetch
        if (typeof jQuery !== 'undefined' && jQuery.ajax) {
            jQuery.ajax({
            url: 'ajax/get-products-by-metal.php',
            type: 'GET',
            data: { metal_id: metalId, search: search },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.products.length > 0) {
                    let html = '';
                    response.products.forEach(function(product) {
                        const productName = product.name + (product.metal_name ? ' - ' + product.metal_name : '');
                        html += `
                            <tr class="product-row" data-product-id="${product.id}" data-characteristic-id="${product.characteristic_id || ''}">
                                <td data-column="checkbox" style="text-align: center; position: sticky; left: 0; background: #fff; z-index: 1;">
                                    <input type="checkbox" class="product-checkbox" data-product-id="${product.id}" data-characteristic-id="${product.characteristic_id || ''}">
                                </td>
                                <td data-column="id">${product.id || ''}</td>
                                <td data-column="rfid"><input type="text" class="form-control form-control-sm" value="" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="voucher-type"><input type="text" class="form-control form-control-sm" value="" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="barcode" style="position: relative;">
                                    <div style="display: flex; align-items: center; gap: 5px;">
                                        <input type="text" class="form-control form-control-sm barcode-input" value="" style="width: 100px; font-size: 0.7rem;" onclick="printBarcode(this.value, event)" readonly>
                                        <i class="feather icon-printer barcode-print-icon" style="cursor: pointer; font-size: 0.9rem; color: #c5a864; flex-shrink: 0;" onclick="printBarcode(this.previousElementSibling.value, event)" title="Print Barcode"></i>
                                    </div>
                                </td>
                                <td data-column="design-no"><input type="text" class="form-control form-control-sm" value="${escapeHtml(product.article || '')}" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="huid"><input type="text" class="form-control form-control-sm" value="" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="category"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="">Select</option></select></td>
                                <td data-column="calculation"><select class="form-control form-control-sm" style="width: 120px; font-size: 0.7rem;"><option value="Weight X Rate" selected>Weight X Rate</option><option value="Rate X Gross Wt">Rate X Gross Wt</option><option value="Rate X Purity Wt">Rate X Purity Wt</option><option value="Rate X Net Wt">Rate X Net Wt</option><option value="Rate X Final Wt">Rate X Final Wt</option><option value="Fix">Fix</option><option value="Stone Charge">Stone Charge</option></select></td>
                                <td data-column="product"><input type="text" class="form-control form-control-sm" value="${escapeHtml(productName)}" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="location"><select class="form-control form-control-sm location-select" style="width: 100px; font-size: 0.7rem;"><option value="">Select</option></select></td>
                                <td data-column="quantity"><input type="text" class="form-control form-control-sm" value="1" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
                                <td data-column="carat"><select class="form-control form-control-sm carat-select" style="width: 80px; font-size: 0.7rem;"><option value="">Select</option></select></td>
                                <td data-column="pkt-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
                                <td data-column="pkt-less-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="requested-purity"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 120px; font-size: 0.7rem;"></td>
                                <td data-column="requested"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="gross-wt"><input type="text" class="form-control form-control-sm" value="${product.opening_weight || 0}" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
                                <td data-column="less-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
                                <td data-column="gold-loss1"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="gold-loss2"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="setting-charge"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 110px; font-size: 0.7rem;"></td>
                                <td data-column="net-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" readonly style="width: 80px; font-size: 0.7rem;"></td>
                                <td data-column="purity"><input type="text" class="form-control form-control-sm" value="${product.opening_purity || 1}" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
                                <td data-column="purity-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" readonly style="width: 90px; font-size: 0.7rem;"></td>
                                <td data-column="wastage-per"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="wastage-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="final-wt"><input type="text" class="form-control form-control-sm" value="${product.final_weight || product.opening_weight || 0}" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
                                <td data-column="alloy-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
                                <td data-column="rate"><input type="text" class="form-control form-control-sm" value="${product.rate || 0}" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
                                <td data-column="metal-value"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="metal-cost"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem; color: #7b1fa2;"></td>
                                <td data-column="amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="discount-type"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="On Amount" selected>On Amount</option><option value="On Percentage">On Percentage</option></select></td>
                                <td data-column="discount-per"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
                                <td data-column="discount-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="discount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="discount-type2"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="On Amount" selected>On Amount</option><option value="On Percentage">On Percentage</option></select></td>
                                <td data-column="discount-per2"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
                                <td data-column="discount-amount2"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="discounted-amt"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
                                <td data-column="discounted-per"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 120px; font-size: 0.7rem;"></td>
                                <td data-column="making-type"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="Fix" selected>Fix</option><option value="Percentage">Percentage</option></select></td>
                                <td data-column="making-rate"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="making-discount-amt"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 130px; font-size: 0.7rem;"></td>
                                <td data-column="making-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="making-actual-value"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
                                <td data-column="making-cost"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 110px; font-size: 0.7rem;"></td>
                                <td data-column="min-price"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="minimum"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="stone-charge-type"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="Fix" selected>Fix</option><option value="Per Gram">Per Gram</option></select></td>
                                <td data-column="stone-weight"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 110px; font-size: 0.7rem;"></td>
                                <td data-column="stone-rate"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="stone-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
                                <td data-column="stone-cost"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="diamond-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 120px; font-size: 0.7rem;"></td>
                                <td data-column="purchase-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 130px; font-size: 0.7rem;"></td>
                                            <td data-column="sale-percent"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;" placeholder="%" title="Sale % on Purchase Amount"></td>            <td data-column="sale-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 110px; font-size: 0.7rem;"></td>
                                <td data-column="sale-amount-with"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 130px; font-size: 0.7rem;"></td>
                                <td data-column="net-amt"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="tax"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
                                <td data-column="other-charge-type"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="Fix" selected>Fix</option><option value="Percentage">Percentage</option></select></td>
                                <td data-column="other-weight"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 110px; font-size: 0.7rem;"></td>
                                <td data-column="other-rate"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="other-info"><input type="text" class="form-control form-control-sm" value="" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="other-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
                                <td data-column="hallmark-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 130px; font-size: 0.7rem;"></td>
                                <td data-column="hallmark-rate"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 120px; font-size: 0.7rem;"></td>
                                <td data-column="net-amt-tax"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
                                <td data-column="reverse"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
                                <td data-column="actions" style="text-align: center;">
                <i class="feather icon-edit" style="cursor: pointer; font-size: 0.8rem; color: #c5a864; margin-right: 8px;" onclick="editProductRowInTable(this)" title="Edit Row"></i>
                                    <i class="feather icon-trash-2" style="cursor: pointer; font-size: 0.8rem; color: #ef4444;" onclick="deleteProductRowFromModal(this)" title="Delete Row"></i>
                                </td>
                            </tr>
                        `;
                    });
                    tbody.innerHTML = html;
                    
                    // Populate carat and location dropdowns
                    tbody.querySelectorAll('.carat-select').forEach(function(select) {
                        populateSelect(select, carats, 'id', 'name', 'Select Carat');
                    });
                    
                    tbody.querySelectorAll('.location-select').forEach(function(select) {
                        populateSelect(select, locations, 'id', 'name', 'Select Location');
                    });
                    
                    // Populate category dropdowns
                    tbody.querySelectorAll('[data-column="category"] select').forEach(function(select) {
                        populateSelect(select, categories, 'id', 'name', 'Select Category');
                        select.classList.add('category-select');
                    });
                    
                    // Add calculation type change listeners
                    tbody.querySelectorAll('[data-column="calculation"] select').forEach(function(select) {
                        select.addEventListener('change', function() {
                            const row = select.closest('tr');
                            if (row) {
                                calculateModalRowNetWeight(row);
                            }
                        });
                    });
                    
                    // Add click handler to product rows (toggle checkbox on row click)
                    tbody.querySelectorAll('.product-row').forEach(function(row) {
                        const checkbox = row.querySelector('.product-checkbox');
                        
                        // Click on row toggles checkbox
                        row.addEventListener('click', function(e) {
                            // Don't toggle if clicking directly on checkbox
                            if (e.target.type !== 'checkbox') {
                                checkbox.checked = !checkbox.checked;
                                updateRowSelection(row, checkbox.checked);
                            } else {
                                updateRowSelection(row, checkbox.checked);
                            }
                        });
                        
                        // Checkbox change handler
                        checkbox.addEventListener('change', function() {
                            updateRowSelection(row, this.checked);
                        });
                        
                        // Add hover effect
                        row.style.cursor = 'pointer';
                        
                        // Add calculation listeners for this row
                        addModalRowCalculationListeners(row);
                        
                        // Calculate initial values
                        calculateModalRowNetWeight(row);
                    });
                    
                    // Select All checkbox handler
                    const selectAllCheckbox = document.getElementById('selectAllProducts');
                    if (selectAllCheckbox) {
                        selectAllCheckbox.addEventListener('change', function() {
                            const checkboxes = tbody.querySelectorAll('.product-checkbox');
                            checkboxes.forEach(function(cb) {
                                cb.checked = this.checked;
                                const row = cb.closest('.product-row');
                                if (row) {
                                    updateRowSelection(row, cb.checked);
                                }
                            }.bind(this));
                        });
                    }
                    
                    function updateRowSelection(row, isSelected) {
                        if (isSelected) {
                            row.classList.add('selected');
                            row.style.backgroundColor = '#fff3cd';
                        } else {
                            row.classList.remove('selected');
                            row.style.backgroundColor = '';
                        }
                    }
                } else {
                    tbody.innerHTML = '<tr><td colspan="70" class="text-center text-muted py-4">No products found</td></tr>';
                }
            },
            error: function() {
                tbody.innerHTML = '<tr><td colspan="70" class="text-center text-danger py-4">Error loading products</td></tr>';
            }
        });
        } else {
            // Fallback using fetch API
            const url = 'ajax/get-products-by-metal.php?metal_id=' + metalId + (search ? '&search=' + encodeURIComponent(search) : '');
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.products.length > 0) {
                        let html = '';
                        data.products.forEach(function(product) {
                            const productName = product.name + (product.metal_name ? ' - ' + product.metal_name : '');
                            html += `
                                <tr class="product-row" data-product-id="${product.id}" data-characteristic-id="${product.characteristic_id || ''}">
                                    <td data-column="checkbox" style="text-align: center; position: sticky; left: 0; background: #fff; z-index: 1;">
                                        <input type="checkbox" class="product-checkbox" data-product-id="${product.id}" data-characteristic-id="${product.characteristic_id || ''}">
                                    </td>
                                    <td data-column="id">${product.id || ''}</td>
                                    <td data-column="rfid"><input type="text" class="form-control form-control-sm" value="" style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="voucher-type"><input type="text" class="form-control form-control-sm" value="" style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="barcode" style="position: relative;">
                                    <div style="display: flex; align-items: center; gap: 5px;">
                                        <input type="text" class="form-control form-control-sm barcode-input" value="" style="width: 100px; font-size: 0.7rem;" onclick="printBarcode(this.value, event)" readonly>
                                        <i class="feather icon-printer barcode-print-icon" style="cursor: pointer; font-size: 0.9rem; color: #c5a864; flex-shrink: 0;" onclick="printBarcode(this.previousElementSibling.value, event)" title="Print Barcode"></i>
                                    </div>
                                </td>
                                    <td data-column="design-no"><input type="text" class="form-control form-control-sm" value="${escapeHtml(product.article || '')}" style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="huid"><input type="text" class="form-control form-control-sm" value="" style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="category"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="">Select</option></select></td>
                                    <td data-column="calculation"><select class="form-control form-control-sm" style="width: 120px; font-size: 0.7rem;"><option value="Weight X Rate" selected>Weight X Rate</option><option value="Rate X Gross Wt">Rate X Gross Wt</option><option value="Rate X Purity Wt">Rate X Purity Wt</option><option value="Rate X Net Wt">Rate X Net Wt</option><option value="Rate X Final Wt">Rate X Final Wt</option><option value="Fix">Fix</option><option value="Stone Charge">Stone Charge</option></select></td>
                                    <td data-column="product"><input type="text" class="form-control form-control-sm" value="${escapeHtml(productName)}" style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="location"><select class="form-control form-control-sm location-select" style="width: 100px; font-size: 0.7rem;"><option value="">Select</option></select></td>
                                    <td data-column="quantity"><input type="text" class="form-control form-control-sm" value="1" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
                                    <td data-column="carat"><select class="form-control form-control-sm carat-select" style="width: 80px; font-size: 0.7rem;"><option value="">Select</option></select></td>
                                    <td data-column="pkt-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
                                    <td data-column="pkt-less-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="requested-purity"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 120px; font-size: 0.7rem;"></td>
                                    <td data-column="requested"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="gross-wt"><input type="text" class="form-control form-control-sm" value="${product.opening_weight || 0}" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
                                    <td data-column="less-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
                                    <td data-column="gold-loss1"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="gold-loss2"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="setting-charge"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 110px; font-size: 0.7rem;"></td>
                                    <td data-column="net-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" readonly style="width: 80px; font-size: 0.7rem;"></td>
                                    <td data-column="purity"><input type="text" class="form-control form-control-sm" value="${product.opening_purity || 1}" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
                                    <td data-column="purity-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" readonly style="width: 90px; font-size: 0.7rem;"></td>
                                    <td data-column="wastage-per"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="wastage-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="final-wt"><input type="text" class="form-control form-control-sm" value="${product.final_weight || product.opening_weight || 0}" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
                                    <td data-column="alloy-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
                                    <td data-column="rate"><input type="text" class="form-control form-control-sm" value="${product.rate || 0}" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
                                    <td data-column="metal-value"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="metal-cost"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem; color: #7b1fa2;"></td>
                                    <td data-column="amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="discount-type"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="On Amount" selected>On Amount</option><option value="On Percentage">On Percentage</option></select></td>
                                    <td data-column="discount-per"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
                                    <td data-column="discount-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="discount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="discount-type2"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="On Amount" selected>On Amount</option><option value="On Percentage">On Percentage</option></select></td>
                                    <td data-column="discount-per2"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
                                    <td data-column="discount-amount2"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="discounted-amt"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
                                    <td data-column="discounted-per"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 120px; font-size: 0.7rem;"></td>
                                    <td data-column="making-type"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="Fix" selected>Fix</option><option value="Percentage">Percentage</option></select></td>
                                    <td data-column="making-rate"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="making-discount-amt"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 130px; font-size: 0.7rem;"></td>
                                    <td data-column="making-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="making-actual-value"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
                                    <td data-column="making-cost"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 110px; font-size: 0.7rem;"></td>
                                    <td data-column="min-price"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="minimum"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="stone-charge-type"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="Fix" selected>Fix</option><option value="Per Gram">Per Gram</option></select></td>
                                    <td data-column="stone-weight"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 110px; font-size: 0.7rem;"></td>
                                    <td data-column="stone-rate"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="stone-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
                                    <td data-column="stone-cost"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="diamond-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 120px; font-size: 0.7rem;"></td>
                                    <td data-column="purchase-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 130px; font-size: 0.7rem;"></td>
                                                <td data-column="sale-percent"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;" placeholder="%" title="Sale % on Purchase Amount"></td>            <td data-column="sale-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 110px; font-size: 0.7rem;"></td>
                                    <td data-column="sale-amount-with"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 130px; font-size: 0.7rem;"></td>
                                    <td data-column="net-amt"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="tax"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
                                    <td data-column="other-charge-type"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="Fix" selected>Fix</option><option value="Percentage">Percentage</option></select></td>
                                    <td data-column="other-weight"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 110px; font-size: 0.7rem;"></td>
                                    <td data-column="other-rate"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="other-info"><input type="text" class="form-control form-control-sm" value="" style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="other-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
                                    <td data-column="hallmark-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 130px; font-size: 0.7rem;"></td>
                                    <td data-column="hallmark-rate"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 120px; font-size: 0.7rem;"></td>
                                    <td data-column="net-amt-tax"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
                                    <td data-column="reverse"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
                                    <td data-column="actions" style="text-align: center;">
                                        <i class="feather icon-settings" style="cursor: pointer; font-size: 0.8rem;"></i>
                                    </td>
                                </tr>
                            `;
                        });
                        tbody.innerHTML = html;
                        
                        // Populate carat and location dropdowns
                        tbody.querySelectorAll('.carat-select').forEach(function(select) {
                            populateSelect(select, carats, 'id', 'name', 'Select Carat');
                        });
                        
                        tbody.querySelectorAll('.location-select').forEach(function(select) {
                            populateSelect(select, locations, 'id', 'name', 'Select Location');
                        });
                        
                        // Add click handler and calculation listeners to product rows
                        tbody.querySelectorAll('.product-row').forEach(function(row) {
                            const checkbox = row.querySelector('.product-checkbox');
                            
                            // Click on row toggles checkbox (but not on product field)
                            row.addEventListener('click', function(e) {
                                // Don't toggle if clicking on product field or checkbox
                                if (e.target.closest('[data-column="product"]') || e.target.type === 'checkbox') {
                                    if (e.target.closest('[data-column="product"]')) {
                                        // Open product search modal
                                        openProductSearchModal(row);
                                    }
                                    return;
                                }
                                checkbox.checked = !checkbox.checked;
                                updateRowSelection(row, checkbox.checked);
                            });
                            
                            // Checkbox change handler
                            checkbox.addEventListener('change', function() {
                                updateRowSelection(row, this.checked);
                            });
                            
                            // Add hover effect
                            row.style.cursor = 'pointer';
                            
                            // Add click handler to Product field
                            const productInput = row.querySelector('[data-column="product"] input');
                            if (productInput) {
                                productInput.addEventListener('click', function(e) {
                                    e.stopPropagation();
                                    openProductSearchModal(row);
                                });
                                productInput.style.cursor = 'pointer';
                                productInput.readOnly = true; // Make it read-only so user must select from modal
                            }
                            
                            // Populate category dropdown if not already populated
                            const categorySelect = row.querySelector('[data-column="category"] select');
                            if (categorySelect && !categorySelect.classList.contains('category-select')) {
                                populateSelect(categorySelect, categories, 'id', 'name', 'Select Category');
                                categorySelect.classList.add('category-select');
                            }
                            
                            // Add calculation type change listener
                            const calculationSelect = row.querySelector('[data-column="calculation"] select');
                            if (calculationSelect) {
                                calculationSelect.addEventListener('change', function() {
                                    calculateModalRowNetWeight(row);
                                });
                            }
                            
                            // Add calculation listeners for this row
                            addModalRowCalculationListeners(row);
                            
                            // Calculate initial values
                            calculateModalRowNetWeight(row);
                        });
                        
                        // Select All checkbox handler
                        const selectAllCheckbox = document.getElementById('selectAllProducts');
                        if (selectAllCheckbox) {
                            selectAllCheckbox.addEventListener('change', function() {
                                const checkboxes = tbody.querySelectorAll('.product-checkbox');
                                checkboxes.forEach(function(cb) {
                                    cb.checked = this.checked;
                                    const row = cb.closest('.product-row');
                                    if (row) {
                                        updateRowSelection(row, cb.checked);
                                    }
                                }.bind(this));
                            });
                        }
                        
                        function updateRowSelection(row, isSelected) {
                            if (isSelected) {
                                row.classList.add('selected');
                                row.style.backgroundColor = '#fff3cd';
                            } else {
                                row.classList.remove('selected');
                                row.style.backgroundColor = '';
                            }
                        }
                    } else {
                        tbody.innerHTML = '<tr><td colspan="65" class="text-center text-muted py-4">No products found</td></tr>';
                    }
                })
                .catch(error => {
                    tbody.innerHTML = '<tr><td colspan="65" class="text-center text-danger py-4">Error loading products</td></tr>';
                });
        }
    }
    
    // Add Product Row Button Event Listener
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize category tabs on page load
        initCategoryTabs();
        
        const addProductRowBtn = document.getElementById('addProductRowBtn');
        if (addProductRowBtn) {
            addProductRowBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                addEmptyProductRow();
            });
        }
    });
    
    // Also use event delegation for dynamically added button
    $(document).on('click', '#addProductRowBtn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        addEmptyProductRow();
    });
    
    // Modal Table Column Visibility Toggle
    (function() {
        const settingsBtn = document.getElementById('modalTableSettingsBtn');
        const settingsDropdown = document.getElementById('modalTableSettingsDropdown');
        if (!settingsBtn || !settingsDropdown) return;
        
        const checkboxes = settingsDropdown.querySelectorAll('input[type="checkbox"]');
        
        // Toggle dropdown on button click
        settingsBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            settingsDropdown.classList.toggle('show');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!settingsBtn.contains(e.target) && !settingsDropdown.contains(e.target)) {
                settingsDropdown.classList.remove('show');
            }
        });

        // Handle column visibility
        checkboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                const columnName = this.getAttribute('data-column');
                const isVisible = this.checked;
                
                // Toggle visibility for all th and td elements with this column
                const headers = document.querySelectorAll(`#productListTable th[data-column="${columnName}"]`);
                const cells = document.querySelectorAll(`#productListTable td[data-column="${columnName}"]`);
                
                headers.forEach(function(header) {
                    if (isVisible) {
                        header.classList.remove('hidden');
                        header.style.display = '';
                    } else {
                        header.classList.add('hidden');
                        header.style.display = 'none';
                    }
                });
                
                cells.forEach(function(cell) {
                    if (isVisible) {
                        cell.classList.remove('hidden');
                        cell.style.display = '';
                    } else {
                        cell.classList.add('hidden');
                        cell.style.display = 'none';
                    }
                });
            });
        });
    })();
    
    // Select product and add to table
    function selectProduct(row, closeModal = false) {
        const productId = row.getAttribute('data-product-id');
        const characteristicId = row.getAttribute('data-characteristic-id');
        
        // Extract ALL calculated values directly from the modal row
        const getValue = function(column, isNumber = true) {
            const cell = row.querySelector(`[data-column="${column}"]`);
            if (!cell) return isNumber ? 0 : '';
            const input = cell.querySelector('input');
            const select = cell.querySelector('select');
            if (input) {
                return isNumber ? (parseFloat(input.value) || 0) : input.value;
            } else if (select) {
                return isNumber ? (parseFloat(select.value) || 0) : select.value;
            } else {
                return isNumber ? (parseFloat(cell.textContent.trim()) || 0) : cell.textContent.trim();
            }
        };
        
        // Extract all values from modal row
        const modalRowData = {
            product_id: productId,
            characteristic_id: characteristicId,
            product_name: getValue('product', false),
            barcode: getValue('barcode', false),
            quantity: getValue('quantity'),
            gross_wt: getValue('gross-wt'),
            less_wt: getValue('less-wt'),
            purity: getValue('purity'),
            final_wt: getValue('final-wt'),
            net_wt: getValue('net-wt'),
            pure_wt: getValue('purity-wt'),
            rate: getValue('rate'),
            metal_value: getValue('metal-value'),
            amount: getValue('amount'),
            discount: getValue('discount'),
            making_amount: getValue('making-amount'),
            stone_amount: getValue('stone-amount'),
            other_amount: getValue('other-amount'),
            diamond_amount: getValue('diamond-amount'),
            tax: getValue('tax'),
            net_amt: getValue('net-amt'),
            net_amt_tax: getValue('net-amt-tax'),
            purchase_amount: getValue('purchase-amount'),
            sale_amount: getValue('sale-amount'),
            sale_amount_with: getValue('sale-amount-with'),
            reverse: getValue('reverse'),
            design_no: getValue('design-no', false)
        };
        
        console.log('Extracted modal row data:', modalRowData);
        
                    if (currentEditingRowId) {
            // Update existing row with modal data
                        console.log('Updating row:', currentEditingRowId);
            updateProductListRowFromModalRow(currentEditingRowId, row);
                        if (closeModal) {
                            hideProductModal();
                        }
            currentEditingRowId = null;
                    } else {
            // Add new row with modal data
            console.log('Adding new row from modal data');
            addProductToTableFromModalRow(modalRowData);
                        if (closeModal) {
                            hideProductModal();
                        }
                    }
        
                    updateSummaryPanel();
    }
    
    // Add product to table from modal row data (with all calculated values)
    function addProductToTableFromModalRow(modalRowData) {
        console.log('addProductToTableFromModalRow called with data:', modalRowData);
        
        const tbody = document.getElementById('productTableBody');
        if (!tbody) {
            console.error('productTableBody not found');
            return;
        }
        
        const emptyRow = tbody.querySelector('.no-drag');
        if (emptyRow) {
            emptyRow.remove();
        }
        
        productTableRowIndex++;
        const rowId = 'product-row-' + productTableRowIndex;
        
        const row = document.createElement('tr');
        row.id = rowId;
        row.setAttribute('data-product-id', modalRowData.product_id || '');
        row.setAttribute('data-characteristic-id', modalRowData.characteristic_id || '');
        row.setAttribute('data-purity', parseFloat(modalRowData.purity || 0));
        row.setAttribute('data-rate', parseFloat(modalRowData.rate || 0));
        
        try {
            // Always generate a NEW unique barcode for Product List table
            // This ensures each instance of a product gets a different barcode, even if it's the same product
            const barcode = generateUniqueBarcode();
            
            // Store barcode in row data attribute for easy retrieval
            row.setAttribute('data-barcode', barcode);
            
            row.innerHTML = `
                <td data-column="print-barcode" style="text-align: center; width: 50px;">
                    <i class="feather icon-printer" style="cursor: pointer; font-size: 0.9rem; color: #c5a864;" onclick="printBarcode('${escapeHtml(barcode)}', event)" title="Print Barcode"></i>
                </td>
                <td data-column="barcode" style="text-align: center; color: #11294b; font-weight: 600; cursor: pointer;" onclick="printBarcode('${escapeHtml(barcode)}', event)" title="Click to print barcode">
                    ${escapeHtml(barcode)}
                </td>
                <td data-column="description" class="product-select-cell" style="cursor: pointer; color: #11294b; position: relative;">
                    <a href="javascript:void(0)" style="color: #11294b; text-decoration: underline;">${escapeHtml(modalRowData.product_name || '')}</a>
                </td>
                <td data-column="quantity" style="text-align: right;">
                    <input type="text" class="form-control form-control-sm editable-field" data-field="quantity" value="${parseFloat(modalRowData.quantity || 1).toFixed(2)}" step="0.01" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">
                </td>
                <td data-column="gross-wt" style="text-align: right;">
                    <input type="text" class="form-control form-control-sm editable-field" data-field="gross_wt" value="${parseFloat(modalRowData.gross_wt || 0).toFixed(3)}" step="0.001" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">
                </td>
                <td data-column="less-wt" style="text-align: right;">
                    <input type="text" class="form-control form-control-sm editable-field" data-field="less_wt" value="${parseFloat(modalRowData.less_wt || 0).toFixed(3)}" step="0.001" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">
                </td>
                <td data-column="purity" style="text-align: right;">
                    <input type="text" class="form-control form-control-sm editable-field" data-field="purity" value="${parseFloat(modalRowData.purity || 0).toFixed(2)}" step="0.01" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">
                </td>
                <td data-column="final-wt" style="text-align: right;">
                    <input type="text" class="form-control form-control-sm editable-field" data-field="final_wt" value="${parseFloat(modalRowData.final_wt || 0).toFixed(3)}" step="0.001" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">
                </td>
                <td data-column="net-wt" style="text-align: right; color: #11294b;">${parseFloat(modalRowData.net_wt || 0).toFixed(3)}</td>
                <td data-column="pure-wt" style="text-align: right; color: #11294b;">${parseFloat(modalRowData.pure_wt || 0).toFixed(3)}</td>
                <td data-column="making" style="text-align: right;">
                    <input type="text" class="form-control form-control-sm editable-field" data-field="making" value="${parseFloat(modalRowData.making_amount || 0).toFixed(2)}" step="0.01" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">
                </td>
                <td data-column="design-no" style="text-align: right;">
                    <input type="text" class="form-control form-control-sm editable-field" data-field="design_no" value="${escapeHtml(modalRowData.design_no || '')}" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">
                </td>
                <td data-column="stone-charges" style="text-align: right;">
                    <input type="text" class="form-control form-control-sm editable-field" data-field="stone_charges" value="${parseFloat(modalRowData.stone_amount || 0).toFixed(2)}" step="0.01" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">
                </td>
                <td data-column="other-charges" style="text-align: right;">
                    <input type="text" class="form-control form-control-sm editable-field" data-field="other_charges" value="${parseFloat(modalRowData.other_amount || 0).toFixed(2)}" step="0.01" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">
                </td>
                <td data-column="diamond-value" style="text-align: right;">
                    <input type="text" class="form-control form-control-sm editable-field" data-field="diamond_value" value="${parseFloat(modalRowData.diamond_amount || 0).toFixed(2)}" step="0.01" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">
                </td>
                <td data-column="gemstone-value" style="text-align: right;">
                    <input type="text" class="form-control form-control-sm editable-field" data-field="gemstone_value" value="0.00" step="0.01" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">
                </td>
                <td data-column="rate" style="text-align: right; color: #11294b;">${parseFloat(modalRowData.rate || 0).toFixed(2)}</td>
                <td data-column="metal-value" style="text-align: right; color: #11294b;">${parseFloat(modalRowData.metal_value || 0).toFixed(2)}</td>
                <td data-column="discount" style="text-align: right; color: #11294b;">${parseFloat(modalRowData.discount || 0).toFixed(2)}</td>
                <td data-column="making-amount" style="text-align: right; color: #11294b;">${parseFloat(modalRowData.making_amount || 0).toFixed(2)}</td>
                <td data-column="stone-amount" style="text-align: right; color: #11294b;">${parseFloat(modalRowData.stone_amount || 0).toFixed(2)}</td>
                <td data-column="other-amount" style="text-align: right; color: #11294b;">${parseFloat(modalRowData.other_amount || 0).toFixed(2)}</td>
                <td data-column="diamond-amount" style="text-align: right; color: #11294b;">${parseFloat(modalRowData.diamond_amount || 0).toFixed(2)}</td>
                <td data-column="purchase-amount" style="text-align: right; color: #11294b;">${parseFloat(modalRowData.purchase_amount || 0).toFixed(2)}</td>
                <td data-column="sale-amount" style="text-align: right; color: #11294b;">${parseFloat(modalRowData.sale_amount || 0).toFixed(2)}</td>
                <td data-column="sale-amount-with" style="text-align: right; color: #11294b;">${parseFloat(modalRowData.sale_amount_with || 0).toFixed(2)}</td>
                <td data-column="reverse" style="text-align: right; color: #11294b;">${parseFloat(modalRowData.reverse || 0).toFixed(2)}</td>
                <td data-column="tax" style="text-align: right;">
                    <input type="text" class="form-control form-control-sm editable-field" data-field="tax" value="${parseFloat(modalRowData.tax || 0).toFixed(2)}" step="0.01" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">
                </td>
                <td data-column="amount" style="text-align: right; font-weight: 600; color: #11294b;">${parseFloat(modalRowData.amount || 0).toFixed(2)}</td>
                <td data-column="net-amt" style="text-align: right; color: #11294b;">${parseFloat(modalRowData.net_amt || 0).toFixed(2)}</td>
                <td data-column="net-amt-tax" style="text-align: right; color: #11294b;">${parseFloat(modalRowData.net_amt_tax || 0).toFixed(2)}</td>
                <td>
                    <div class="action-btns">
                        <button type="button" class="btn-edit" onclick="editProductRow('${rowId}')" title="Edit">
                            <i class="feather icon-refresh-cw"></i>
                        </button>
                        <button type="button" class="btn-delete" onclick="deleteProductRow('${rowId}')" title="Delete">
                            <i class="feather icon-trash-2"></i>
                        </button>
                    </div>
                </td>
            `;
            
            tbody.appendChild(row);
            console.log('Row added to table from modal:', rowId);
            
            // Add event listeners for calculations
            addRowCalculationListeners(row);
            
            // Update summary
            updateSummaryRow();
                        updateSummaryPanel();
            
        } catch (error) {
            console.error('Error adding product to table from modal:', error);
            alert('Error adding product: ' + error.message);
        }
    }
    
    // Add product to table (legacy function - kept for backward compatibility)
    function addProductToTable(product) {
        console.log('addProductToTable called with product:', product);
        
        const tbody = document.getElementById('productTableBody');
        if (!tbody) {
            console.error('productTableBody not found');
            return;
        }
        
        const emptyRow = tbody.querySelector('.no-drag');
        if (emptyRow) {
            emptyRow.remove();
        }
        
        productTableRowIndex++;
        const rowId = 'product-row-' + productTableRowIndex;
        
        // Get quantity from modal or default to 1
        const quantityInput = document.getElementById('modalProductQty');
        const quantity = quantityInput ? parseFloat(quantityInput.value) || 1 : 1;
        
        const grossWt = parseFloat(product.opening_weight) || 0;
        let purity = parseFloat(product.opening_purity) || 0;
        // Handle purity format: if purity > 1, assume it's percentage (e.g., 75 = 0.75)
        if (purity > 1) {
            purity = purity / 100;
        }
        const lessWt = 0; // Default less weight
        const netWt = grossWt - lessWt; // Net Wt = Gross Wt - Less Wt
        const purityWtValue = netWt * purity; // Purity Wt = Net Wt × Purity
        const purityWt = purityWtValue.toFixed(3);
        const finalWt = parseFloat(product.final_weight) || grossWt;
        const rate = parseFloat(product.rate) || 0;
        const metalValue = (parseFloat(purityWt) * rate);
        const makingAmount = 0;
        const amount = metalValue + makingAmount;
        const taxAmt = 0;
        const netAmtWithTax = amount + taxAmt;
        
        const row = document.createElement('tr');
        row.id = rowId;
        row.setAttribute('data-product-id', product.id);
        row.setAttribute('data-characteristic-id', product.characteristic_id || '');
        // Store original product values in data attributes for calculations
        row.setAttribute('data-purity', purity);
        row.setAttribute('data-rate', rate);
        
        const pureWt = purityWt; // Pure Wt = Purity Wt
        const making = 0;
        const designNo = product.article || '0';
        const tax = 0;
        const netAmt = amount; // Net Amt = Amount
        const stoneCharges = 0;
        const otherCharges = 0;
        const diamondValue = 0;
        const gemstoneValue = 0;
        
        try {
            row.innerHTML = `
                <td data-column="print-barcode" style="text-align: center; width: 50px;">
                    <i class="feather icon-printer" style="cursor: pointer; font-size: 0.9rem; color: #c5a864;" onclick="printBarcodeFromRow(this)" title="Print Barcode"></i>
                </td>
                <td data-column="barcode" style="text-align: center;">
                    <div class="image-placeholder" style="width: 30px; height: 30px; background: #f1f5f9; border-radius: 4px; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                        <i class="feather icon-image" style="font-size: 0.9rem; color: #94a3b8;"></i>
                    </div>
                </td>
                <td data-column="description" class="product-select-cell" style="cursor: pointer; color: #11294b; position: relative;">
                    <a href="javascript:void(0)" style="color: #11294b; text-decoration: underline;">${escapeHtml(product.name || '')}</a>
                </td>
                <td data-column="quantity" style="text-align: right;">
                    <input type="text" class="form-control form-control-sm editable-field" data-field="quantity" value="${quantity.toFixed(2)}" step="0.01" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">
                </td>
                <td data-column="gross-wt" style="text-align: right;">
                    <input type="text" class="form-control form-control-sm editable-field" data-field="gross_wt" value="${grossWt.toFixed(3)}" step="0.001" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">
                </td>
                <td data-column="less-wt" style="text-align: right;">
                    <input type="text" class="form-control form-control-sm editable-field" data-field="less_wt" value="0" step="0.001" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">
                </td>
                <td data-column="purity" style="text-align: right;">
                    <input type="text" class="form-control form-control-sm editable-field" data-field="purity" value="${purity.toFixed(2)}" step="0.01" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">
                </td>
                <td data-column="final-wt" style="text-align: right;">
                    <input type="text" class="form-control form-control-sm editable-field" data-field="final_wt" value="${finalWt.toFixed(1)}" step="0.1" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">
                </td>
                <td data-column="net-wt" style="text-align: right; color: #11294b;">${netWt.toFixed(3)}</td>
                <td data-column="pure-wt" style="text-align: right; color: #11294b;">${pureWt}</td>
                <td data-column="making" style="text-align: right;">
                    <input type="text" class="form-control form-control-sm editable-field" data-field="making" value="${making}" step="0.01" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">
                </td>
                <td data-column="design-no" style="text-align: right;">
                    <input type="text" class="form-control form-control-sm editable-field" data-field="design_no" value="${designNo}" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">
                </td>
                <td data-column="stone-charges" style="text-align: right;">
                    <input type="text" class="form-control form-control-sm editable-field" data-field="stone_charges" value="${stoneCharges.toFixed(2)}" step="0.01" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">
                </td>
                <td data-column="other-charges" style="text-align: right;">
                    <input type="text" class="form-control form-control-sm editable-field" data-field="other_charges" value="${otherCharges.toFixed(2)}" step="0.01" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">
                </td>
                <td data-column="diamond-value" style="text-align: right;">
                    <input type="text" class="form-control form-control-sm editable-field" data-field="diamond_value" value="${diamondValue.toFixed(2)}" step="0.01" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">
                </td>
                <td data-column="gemstone-value" style="text-align: right;">
                    <input type="text" class="form-control form-control-sm editable-field" data-field="gemstone_value" value="${gemstoneValue.toFixed(2)}" step="0.01" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">
                </td>
                <td data-column="rate" style="text-align: right; color: #11294b;">${rate.toFixed(2)}</td>
                <td data-column="metal-value" style="text-align: right; color: #11294b;">${metalValue.toFixed(2)}</td>
                <td data-column="discount" style="text-align: right; color: #11294b;">0.00</td>
                <td data-column="making-amount" style="text-align: right; color: #11294b;">${makingAmount.toFixed(2)}</td>
                <td data-column="stone-amount" style="text-align: right; color: #11294b;">${stoneCharges.toFixed(2)}</td>
                <td data-column="other-amount" style="text-align: right; color: #11294b;">${otherCharges.toFixed(2)}</td>
                <td data-column="diamond-amount" style="text-align: right; color: #11294b;">${diamondValue.toFixed(2)}</td>
                <td data-column="purchase-amount" style="text-align: right; color: #11294b;">${netAmt.toFixed(2)}</td>
                <td data-column="sale-amount" style="text-align: right; color: #11294b;">${netAmt.toFixed(2)}</td>
                <td data-column="sale-amount-with" style="text-align: right; color: #11294b;">${netAmtWithTax.toFixed(2)}</td>
                <td data-column="reverse" style="text-align: right; color: #11294b;">0.00</td>
                <td data-column="tax" style="text-align: right;">
                    <input type="text" class="form-control form-control-sm editable-field" data-field="tax" value="${tax}" step="0.01" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">
                </td>
                <td data-column="amount" style="text-align: right; font-weight: 600; color: #11294b;">${amount.toFixed(2)}</td>
                <td data-column="net-amt" style="text-align: right; color: #11294b;">${netAmt.toFixed(2)}</td>
                <td data-column="net-amt-tax" style="text-align: right; color: #11294b;">${netAmtWithTax.toFixed(2)}</td>
                <td>
                    <div class="action-btns">
                        <button type="button" class="btn-edit" onclick="editProductRow('${rowId}')" title="Edit">
                            <i class="feather icon-refresh-cw"></i>
                        </button>
                        <button type="button" class="btn-delete" onclick="deleteProductRow('${rowId}')" title="Delete">
                            <i class="feather icon-trash-2"></i>
                        </button>
                    </div>
                </td>
            `;
            
            tbody.appendChild(row);
            console.log('Row added to table:', rowId);
            
            // Add event listeners for calculations
            addRowCalculationListeners(row);
            
            // Calculate immediately after adding row
            calculateRowAmounts(row);
            
            // Add click handler to Description column - Open edit modal
            const descriptionCell = row.querySelector('[data-column="description"]');
            if (descriptionCell) {
                descriptionCell.addEventListener('click', function(e) {
                    // Don't trigger if clicking on the link itself (if it has one)
                    if (e.target.tagName !== 'A') {
                        editProductRow(rowId);
                    }
                });
            }
            
            // Update summary
            updateSummaryRow();
            updateSummaryPanel();
            
            // Clear modal input fields (if modal is still open)
            clearModalFields();
        } catch (error) {
            console.error('Error adding product to table:', error);
            alert('Error adding product: ' + error.message);
        }
    }
    
    // Open product modal for a specific row
    let currentEditingRowId = null;
    function openProductModalForRow(rowId) {
        if (!rowId) {
            console.error('No rowId provided to openProductModalForRow');
            return;
        }
        currentEditingRowId = rowId;
        console.log('Opening modal for row:', rowId);
        openProductModal();
    }
    
    // Hide product modal
    function hideProductModal() {
        console.log('hideProductModal called');
        const modal = document.getElementById('productSelectionModal');
        if (modal) {
            // Method 1: Try jQuery modal hide first (most reliable for Bootstrap modals)
            if (typeof jQuery !== 'undefined' && jQuery.fn.modal) {
                try {
                    jQuery('#productSelectionModal').modal('hide');
                    console.log('Modal hidden via jQuery');
                } catch(e) {
                    console.log('jQuery modal hide error:', e);
                }
            }
            
            // Method 2: Force hide using direct DOM manipulation
            modal.style.display = 'none';
            modal.classList.remove('show');
            modal.classList.remove('fade');
            modal.setAttribute('aria-hidden', 'true');
            modal.setAttribute('aria-modal', 'false');
            modal.removeAttribute('role');
            
            // Method 3: Remove body classes and styles
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
            
            // Method 4: Remove all backdrop elements
            const backdrops = document.querySelectorAll('.modal-backdrop');
            backdrops.forEach(function(backdrop) {
                backdrop.remove();
            });
            
            // Method 5: Force remove any remaining backdrop styles
            const bodyClasses = document.body.className.split(' ');
            const filteredClasses = bodyClasses.filter(cls => cls !== 'modal-open');
            document.body.className = filteredClasses.join(' ');
            
            console.log('Modal should be hidden now');
        }
        
        // Clear search and selection
        const searchInput = document.getElementById('modalProductSearchInput');
        if (searchInput) searchInput.value = '';
        const selectedRow = document.querySelector('#productListBody .product-row.selected');
        if (selectedRow) selectedRow.classList.remove('selected');
        
        // Clear current editing row ID
        currentEditingRowId = null;
    }
    
    // Update existing row with product data
    function updateRowWithProduct(rowId, product) {
        const row = document.getElementById(rowId);
        if (!row) return;
        
        // Get quantity from existing row input or default to 1
        const quantityInput = row.querySelector('[data-field="quantity"]');
        const quantity = quantityInput ? parseFloat(quantityInput.value) || 1 : 1;
        
        const grossWt = parseFloat(product.opening_weight) || 0;
        let purity = parseFloat(product.opening_purity) || 0;
        // Handle purity format: if purity > 1, assume it's percentage (e.g., 75 = 0.75)
        if (purity > 1) {
            purity = purity / 100;
        }
        const lessWt = 0; // Default less weight
        const netWt = grossWt - lessWt; // Net Wt = Gross Wt - Less Wt
        const purityWt = (netWt * purity).toFixed(3); // Purity Wt = Net Wt × Purity
        const finalWt = parseFloat(product.final_weight) || grossWt;
        const rate = parseFloat(product.rate) || 0;
        const metalValue = (parseFloat(purityWt) * rate);
        const amount = metalValue;
        
        // Update row data attributes (store purity and rate for calculations)
        row.setAttribute('data-product-id', product.id);
        row.setAttribute('data-characteristic-id', product.characteristic_id || '');
        row.setAttribute('data-purity', purity);
        row.setAttribute('data-rate', rate);
        
        // Update cells - add print icon column if it doesn't exist
        let printCell = row.querySelector('[data-column="print-barcode"]');
        if (!printCell) {
            // Insert print icon cell before barcode cell
            const barcodeCell = row.querySelector('[data-column="barcode"]');
            if (barcodeCell) {
                printCell = document.createElement('td');
                printCell.setAttribute('data-column', 'print-barcode');
                printCell.style.cssText = 'text-align: center; width: 50px;';
                barcodeCell.parentNode.insertBefore(printCell, barcodeCell);
            }
        }
        
        // Update print icon
        if (printCell) {
            const barcode = row.getAttribute('data-barcode') || '';
            printCell.innerHTML = `<i class="feather icon-printer" style="cursor: pointer; font-size: 0.9rem; color: #c5a864;" onclick="printBarcode('${escapeHtml(barcode)}', event)" title="Print Barcode"></i>`;
        }
        
        const barcodeCell = row.querySelector('[data-column="barcode"]');
        if (barcodeCell) {
            const barcode = row.getAttribute('data-barcode') || '';
            barcodeCell.innerHTML = `
                <div class="image-placeholder" style="width: 30px; height: 30px; background: #f1f5f9; border-radius: 4px; display: flex; align-items: center; justify-content: center; margin: 0 auto; cursor: pointer;" onclick="printBarcode('${escapeHtml(barcode)}', event)" title="Click to print barcode">
                    <i class="feather icon-image" style="font-size: 0.9rem; color: #94a3b8;"></i>
                </div>
            `;
        }
        
        const descriptionCell = row.querySelector('[data-column="description"]');
        if (descriptionCell) {
            descriptionCell.innerHTML = `<a href="javascript:void(0)" style="color: #11294b; text-decoration: underline;">${escapeHtml(product.name || '')}</a>`;
            descriptionCell.style.cursor = 'pointer';
            descriptionCell.addEventListener('click', function() {
                openProductModalForRow(rowId);
            });
        }
        
        // Update editable fields
        if (quantityInput) quantityInput.value = quantity.toFixed(2);
        
        const grossWtInput = row.querySelector('[data-field="gross_wt"]');
        if (grossWtInput) grossWtInput.value = grossWt.toFixed(3);
        
        const lessWtInput = row.querySelector('[data-field="less_wt"]');
        if (lessWtInput) lessWtInput.value = '0';
        
        const purityInput = row.querySelector('[data-field="purity"]');
        if (purityInput) purityInput.value = purity.toFixed(2);
        
        const finalWtInput = row.querySelector('[data-field="final_wt"]');
        if (finalWtInput) finalWtInput.value = finalWt.toFixed(1);
        
        const makingInput = row.querySelector('[data-field="making"]');
        if (makingInput) makingInput.value = '0';
        
        const designNoInput = row.querySelector('[data-field="design_no"]');
        if (designNoInput) designNoInput.value = product.article || '0';
        
        const taxInput = row.querySelector('[data-field="tax"]');
        if (taxInput) taxInput.value = '0';
        
        const stoneChargesInput = row.querySelector('[data-field="stone_charges"]');
        if (stoneChargesInput) stoneChargesInput.value = '0.00';
        
        const otherChargesInput = row.querySelector('[data-field="other_charges"]');
        if (otherChargesInput) otherChargesInput.value = '0.00';
        
        const diamondValueInput = row.querySelector('[data-field="diamond_value"]');
        if (diamondValueInput) diamondValueInput.value = '0.00';
        
        const gemstoneValueInput = row.querySelector('[data-field="gemstone_value"]');
        if (gemstoneValueInput) gemstoneValueInput.value = '0.00';
        
        // Update row data attributes (store purity and rate for calculations)
        row.setAttribute('data-purity', purity);
        row.setAttribute('data-rate', rate);
        
        // Trigger calculation to update all calculated fields
        calculateRowAmounts(row);
        
        // Re-add calculation listeners (in case row was recreated)
        addRowCalculationListeners(row);
        
        // Update summary
        updateSummaryRow();
        updateSummaryPanel();
    }
    
    // Update Product List table row with data from Product Selection modal row
    function updateProductListRowFromModalRow(productListRowId, modalRow) {
        const productListRow = document.getElementById(productListRowId);
        if (!productListRow || !modalRow) {
            console.error('Row not found');
            return;
        }
        
        // Extract all data from the Product Selection modal row
        const getValue = function(column, isNumber = true) {
            const cell = modalRow.querySelector(`[data-column="${column}"]`);
            if (!cell) return isNumber ? 0 : '';
            const input = cell.querySelector('input');
            const select = cell.querySelector('select');
            if (input) {
                return isNumber ? (parseFloat(input.value) || 0) : input.value;
            } else if (select) {
                return isNumber ? (parseFloat(select.value) || 0) : select.value;
            } else {
                return isNumber ? (parseFloat(cell.textContent.trim()) || 0) : cell.textContent.trim();
            }
        };
        
        const rowData = {
            product_id: modalRow.getAttribute('data-product-id') || '',
            characteristic_id: modalRow.getAttribute('data-characteristic-id') || '',
            product_name: getValue('product', false),
            quantity: getValue('quantity'),
            gross_wt: getValue('gross-wt'),
            less_wt: getValue('less-wt'),
            purity: getValue('purity'),
            final_wt: getValue('final-wt'),
            net_wt: getValue('net-wt'),
            pure_wt: getValue('purity-wt'),
            making: getValue('making-amount'),
            design_no: getValue('design-no', false),
            tax: getValue('tax'),
            amount: getValue('amount'),
            net_amt: getValue('net-amt'),
            net_amt_tax: getValue('net-amt-tax'),
            stone_charges: getValue('stone-amount'),
            other_charges: getValue('other-amount'),
            diamond_value: getValue('diamond-amount'),
            gemstone_value: 0, // Not in modal
            rate: getValue('rate'),
            category_id: getValue('category'),
            location_id: getValue('location'),
            carat_id: getValue('carat')
        };
        
        // Update Product List row data attributes
        productListRow.setAttribute('data-product-id', rowData.product_id);
        productListRow.setAttribute('data-characteristic-id', rowData.characteristic_id);
        productListRow.setAttribute('data-purity', rowData.purity);
        productListRow.setAttribute('data-rate', rowData.rate);
        
        // Update description cell
        const descriptionCell = productListRow.querySelector('[data-column="description"]');
        if (descriptionCell) {
            descriptionCell.innerHTML = `<a href="javascript:void(0)" style="color: #11294b; text-decoration: underline;">${escapeHtml(rowData.product_name || '')}</a>`;
        }
        
        // Update all editable fields
        const updateField = function(fieldName, value, isNumber = true) {
            const input = productListRow.querySelector(`[data-field="${fieldName}"]`);
            if (input) {
                if (isNumber) {
                    input.value = parseFloat(value).toFixed(fieldName.includes('_wt') || fieldName === 'purity' ? 3 : fieldName === 'quantity' ? 2 : 0);
                } else {
                    input.value = value;
                }
            }
        };
        
        updateField('quantity', rowData.quantity);
        updateField('gross_wt', rowData.gross_wt);
        updateField('less_wt', rowData.less_wt);
        updateField('purity', rowData.purity);
        updateField('final_wt', rowData.final_wt);
        updateField('making', rowData.making);
        updateField('design_no', rowData.design_no, false);
        updateField('tax', rowData.tax);
        updateField('stone_charges', rowData.stone_charges);
        updateField('other_charges', rowData.other_charges);
        updateField('diamond_value', rowData.diamond_value);
        
        // Update data attributes for calculations
        productListRow.setAttribute('data-purity', rowData.purity);
        productListRow.setAttribute('data-rate', rowData.rate);
        
        // Trigger calculation to update all dependent fields (this will update all calculated columns)
        calculateRowAmounts(productListRow);
        
        // Update summary
        updateSummaryRow();
        updateSummaryPanel();
    }
    
    // Add calculation listeners to row
    function addRowCalculationListeners(row) {
        const editableFields = row.querySelectorAll('.editable-field');
        editableFields.forEach(function(field) {
            field.addEventListener('input', function() {
                calculateRowAmounts(row);
            });
            field.addEventListener('change', function() {
                calculateRowAmounts(row);
            });
            // Add focus/blur styling
            field.addEventListener('focus', function() {
                this.style.background = '#fff';
                this.style.border = '1px solid #11294b';
            });
            field.addEventListener('blur', function() {
                this.style.background = 'transparent';
                this.style.border = 'none';
            });
        });
        
        // Explicitly add listeners for calculation-critical fields
        const grossWtField = row.querySelector('[data-field="gross_wt"]');
        const lessWtField = row.querySelector('[data-field="less_wt"]');
        const purityField = row.querySelector('[data-field="purity"]');
        
        if (grossWtField) {
            grossWtField.addEventListener('input', function() { calculateRowAmounts(row); });
            grossWtField.addEventListener('change', function() { calculateRowAmounts(row); });
        }
        if (lessWtField) {
            lessWtField.addEventListener('input', function() { calculateRowAmounts(row); });
            lessWtField.addEventListener('change', function() { calculateRowAmounts(row); });
        }
        if (purityField) {
            purityField.addEventListener('input', function() { calculateRowAmounts(row); });
            purityField.addEventListener('change', function() { calculateRowAmounts(row); });
        }
    }
    
    // Calculate row amounts - Using same comprehensive logic as modal
    function calculateRowAmounts(row) {
        // Get editable field values
        const grossWt = parseFloat(row.querySelector('[data-field="gross_wt"]')?.value) || 0;
        const lessWt = parseFloat(row.querySelector('[data-field="less_wt"]')?.value) || 0;
        const purityInput = row.querySelector('[data-field="purity"]');
        let purity = parseFloat(purityInput?.value) || 0;
        const finalWt = parseFloat(row.querySelector('[data-field="final_wt"]')?.value) || grossWt;
        const making = parseFloat(row.querySelector('[data-field="making"]')?.value) || 0;
        const tax = parseFloat(row.querySelector('[data-field="tax"]')?.value) || 0;
        const stoneCharges = parseFloat(row.querySelector('[data-field="stone_charges"]')?.value) || 0;
        const otherCharges = parseFloat(row.querySelector('[data-field="other_charges"]')?.value) || 0;
        const diamondValue = parseFloat(row.querySelector('[data-field="diamond_value"]')?.value) || 0;
        const gemstoneValue = parseFloat(row.querySelector('[data-field="gemstone_value"]')?.value) || 0;
        
        // If purity is not in input field, get from data attribute
        if (!purity || purity === 0) {
            purity = parseFloat(row.getAttribute('data-purity')) || 0;
        }
        
        // Handle purity format: if purity > 1, assume it's percentage (e.g., 75 = 0.75)
        if (purity > 1) {
            purity = purity / 100;
        }
        
        // Update data attribute with current purity value
        row.setAttribute('data-purity', purity);
        
        // Get rate from data attribute
        const rate = parseFloat(row.getAttribute('data-rate')) || 0;
        
        // Calculate Net Wt = Gross Wt - Less Wt
        const netWt = grossWt - lessWt;
        
        // Calculate Pure Wt (Purity Wt) = Net Wt × Purity
        const pureWt = netWt * purity;
        
        // Calculate Metal Value = Pure Wt × Rate (or based on calculation type if stored)
        const metalValue = pureWt * rate;
        
        // Calculate Amount = Metal Value + Making + Stone Charges + Other Charges + Diamond Value + Gemstone Value
        const amount = metalValue + making + stoneCharges + otherCharges + diamondValue + gemstoneValue;
        
        // Net Amt = Amount
        const netAmt = amount;
        
        // Net Amt With Tax = Amount + Tax
        const netAmtWithTax = amount + tax;
        
        // Purchase Amount = Net Amount
        const purchaseAmount = netAmt;
        
        // Sale Amount = Net Amount
        const saleAmount = netAmt;
        
        // Sale Amount With = Net Amount With Tax
        const saleAmountWith = netAmtWithTax;
        
        // Update calculated fields (read-only cells)
        const netWtCell = row.querySelector('[data-column="net-wt"]');
        if (netWtCell) netWtCell.textContent = netWt.toFixed(3);
        
        const pureWtCell = row.querySelector('[data-column="pure-wt"]');
        if (pureWtCell) pureWtCell.textContent = pureWt.toFixed(3);
        
        const rateCell = row.querySelector('[data-column="rate"]');
        if (rateCell) rateCell.textContent = rate.toFixed(2);
        
        const metalValueCell = row.querySelector('[data-column="metal-value"]');
        if (metalValueCell) metalValueCell.textContent = metalValue.toFixed(2);
        
        const amountCell = row.querySelector('[data-column="amount"]');
        if (amountCell) amountCell.textContent = amount.toFixed(2);
        
        const netAmtCell = row.querySelector('[data-column="net-amt"]');
        if (netAmtCell) netAmtCell.textContent = netAmt.toFixed(2);
        
        const netAmtTaxCell = row.querySelector('[data-column="net-amt-tax"]');
        if (netAmtTaxCell) netAmtTaxCell.textContent = netAmtWithTax.toFixed(2);
        
        const makingAmountCell = row.querySelector('[data-column="making-amount"]');
        if (makingAmountCell) makingAmountCell.textContent = making.toFixed(2);
        
        const stoneAmountCell = row.querySelector('[data-column="stone-amount"]');
        if (stoneAmountCell) stoneAmountCell.textContent = stoneCharges.toFixed(2);
        
        const otherAmountCell = row.querySelector('[data-column="other-amount"]');
        if (otherAmountCell) otherAmountCell.textContent = otherCharges.toFixed(2);
        
        const diamondAmountCell = row.querySelector('[data-column="diamond-amount"]');
        if (diamondAmountCell) diamondAmountCell.textContent = diamondValue.toFixed(2);
        
        const purchaseAmountCell = row.querySelector('[data-column="purchase-amount"]');
        if (purchaseAmountCell) purchaseAmountCell.textContent = purchaseAmount.toFixed(2);
        
        const saleAmountCell = row.querySelector('[data-column="sale-amount"]');
        if (saleAmountCell) saleAmountCell.textContent = saleAmount.toFixed(2);
        
        const saleAmountWithCell = row.querySelector('[data-column="sale-amount-with"]');
        if (saleAmountWithCell) saleAmountWithCell.textContent = saleAmountWith.toFixed(2);
        
        // Update summary
        updateSummaryRow();
        updateSummaryPanel();
    }
    
    // Add calculation listeners for modal product rows
    function addModalRowCalculationListeners(row) {
        // Helper function to add listeners
        function addListeners(input, callback) {
            if (input) {
                input.addEventListener('input', callback);
                input.addEventListener('change', callback);
            }
        }
        
        function addSelectListeners(select, callback) {
            if (select) {
                select.addEventListener('change', callback);
            }
        }
        
        // Get all calculation-related input fields
        const grossWtInput = row.querySelector('[data-column="gross-wt"] input');
        const lessWtInput = row.querySelector('[data-column="less-wt"] input');
        const purityInput = row.querySelector('[data-column="purity"] input');
        const wastagePerInput = row.querySelector('[data-column="wastage-per"] input');
        const rateInput = row.querySelector('[data-column="rate"] input');
        const amountInput = row.querySelector('[data-column="amount"] input');
        const netAmtInput = row.querySelector('[data-column="net-amt"] input');
        
        // Discount fields
        const discountTypeSelect = row.querySelector('[data-column="discount-type"] select');
        const discountPerInput = row.querySelector('[data-column="discount-per"] input');
        const discountType2Select = row.querySelector('[data-column="discount-type2"] select');
        const discountPer2Input = row.querySelector('[data-column="discount-per2"] input');
        
        // Making fields
        const makingTypeSelect = row.querySelector('[data-column="making-type"] select');
        const makingRateInput = row.querySelector('[data-column="making-rate"] input');
        const makingDiscountAmtInput = row.querySelector('[data-column="making-discount-amt"] input');
        
        // Stone fields
        const stoneWeightInput = row.querySelector('[data-column="stone-weight"] input');
        const stoneRateInput = row.querySelector('[data-column="stone-rate"] input');
        
        // Other fields
        const otherWeightInput = row.querySelector('[data-column="other-weight"] input');
        const otherRateInput = row.querySelector('[data-column="other-rate"] input');
        
        // Diamond amount
        const diamondAmountInput = row.querySelector('[data-column="diamond-amount"] input');
        
        // Add event listeners for all calculation fields
        addListeners(grossWtInput, function() { calculateModalRowNetWeight(row); });
        addListeners(lessWtInput, function() { calculateModalRowNetWeight(row); });
        addListeners(purityInput, function() { calculateModalRowNetWeight(row); });
        addListeners(wastagePerInput, function() { calculateModalRowNetWeight(row); });
        addListeners(rateInput, function() { calculateModalRowNetWeight(row); });
        addListeners(amountInput, function() { calculateModalRowNetWeight(row); });
        addListeners(netAmtInput, function() { calculateModalRowNetWeight(row); });
        
        // Discount listeners
        addSelectListeners(discountTypeSelect, function() { calculateModalRowNetWeight(row); });
        addListeners(discountPerInput, function() { calculateModalRowNetWeight(row); });
        addSelectListeners(discountType2Select, function() { calculateModalRowNetWeight(row); });
        addListeners(discountPer2Input, function() { calculateModalRowNetWeight(row); });
        
        // Making listeners
        addSelectListeners(makingTypeSelect, function() { calculateModalRowNetWeight(row); });
        addListeners(makingRateInput, function() { calculateModalRowNetWeight(row); });
        addListeners(makingDiscountAmtInput, function() { calculateModalRowNetWeight(row); });
        
        // Stone listeners
        addSelectListeners(stoneChargeTypeSelect, function() { calculateModalRowNetWeight(row); });
        addListeners(stoneWeightInput, function() { calculateModalRowNetWeight(row); });
        addListeners(stoneRateInput, function() { calculateModalRowNetWeight(row); });
        
        // Other listeners
        addListeners(otherWeightInput, function() { calculateModalRowNetWeight(row); });
        addListeners(otherRateInput, function() { calculateModalRowNetWeight(row); });
        
        // Diamond amount listener
        addListeners(diamondAmountInput, function() { calculateModalRowNetWeight(row); });
    }
    
    // Calculate ALL values for modal product rows - COMPREHENSIVE CALCULATION FUNCTION
    // Formulas:
    // 1. Net Weight = Gross Weight - Less Weight
    // 2. Purity Weight = Net Weight × Purity
    // 3. Wastage Weight = Net Weight × (Wastage % / 100)
    // 4. Final Weight = Net Weight + Wastage Weight
    // 5. Alloy Weight = Gross Weight - Net Weight
    // 6. Metal Value = Gold Rate × Gross Weight
    // 7. Discount Amount = (Discount Type: On Amount = Discount Per, On Percentage = Amount × Discount Per / 100)
    // 8. Making Amount = (Making Type: Fix = Making Rate, Percentage = Base Amount × Making Rate / 100)
    // 9. Stone Amount = Stone Weight × Stone Rate
    // 10. Other Amount = Other Weight × Other Rate
    // 11. Amount = Metal Value + Making Amount + Stone Amount + Other Amount - Discounts
    // 12. Net Amount = Amount (or calculated from components)
    // 13. Tax = 5% of Net Amount (auto-calculate)
    // 14. Net Amount + Tax = Tax + Net Amount
    function calculateModalRowNetWeight(row) {
        // Handle jQuery objects
        if (row && row.jquery) {
            row = row[0];
        }
        
        if (!row || !row.querySelector) {
            return;
        }
        
        // Get all input fields
        const grossWtInput = row.querySelector('[data-column="gross-wt"] input');
        const lessWtInput = row.querySelector('[data-column="less-wt"] input');
        const purityInput = row.querySelector('[data-column="purity"] input');
        const netWtInput = row.querySelector('[data-column="net-wt"] input');
        const purityWtInput = row.querySelector('[data-column="purity-wt"] input');
        const wastagePerInput = row.querySelector('[data-column="wastage-per"] input');
        const wastageWtInput = row.querySelector('[data-column="wastage-wt"] input');
        const finalWtInput = row.querySelector('[data-column="final-wt"] input');
        const alloyWtInput = row.querySelector('[data-column="alloy-wt"] input');
        const rateInput = row.querySelector('[data-column="rate"] input');
        const metalValueInput = row.querySelector('[data-column="metal-value"] input');
        const amountInput = row.querySelector('[data-column="amount"] input');
        const netAmtInput = row.querySelector('[data-column="net-amt"] input');
        const taxInput = row.querySelector('[data-column="tax"] input');
        const netAmtTaxInput = row.querySelector('[data-column="net-amt-tax"] input');
        
        // Discount fields
        const discountTypeSelect = row.querySelector('[data-column="discount-type"] select');
        const discountPerInput = row.querySelector('[data-column="discount-per"] input');
        const discountAmountInput = row.querySelector('[data-column="discount-amount"] input');
        const discountInput = row.querySelector('[data-column="discount"] input');
        const discountType2Select = row.querySelector('[data-column="discount-type2"] select');
        const discountPer2Input = row.querySelector('[data-column="discount-per2"] input');
        const discountAmount2Input = row.querySelector('[data-column="discount-amount2"] input');
        const discountedAmtInput = row.querySelector('[data-column="discounted-amt"] input');
        
        // Making fields
        const makingTypeSelect = row.querySelector('[data-column="making-type"] select');
        const makingRateInput = row.querySelector('[data-column="making-rate"] input');
        const makingDiscountAmtInput = row.querySelector('[data-column="making-discount-amt"] input');
        const makingAmountInput = row.querySelector('[data-column="making-amount"] input');
        
        // Stone fields
        const stoneChargeTypeSelect = row.querySelector('[data-column="stone-charge-type"] select');
        const stoneWeightInput = row.querySelector('[data-column="stone-weight"] input');
        const stoneRateInput = row.querySelector('[data-column="stone-rate"] input');
        const stoneAmountInput = row.querySelector('[data-column="stone-amount"] input');
        
        // Other fields
        const otherChargeTypeSelect = row.querySelector('[data-column="other-charge-type"] select');
        const otherWeightInput = row.querySelector('[data-column="other-weight"] input');
        const otherRateInput = row.querySelector('[data-column="other-rate"] input');
        const otherAmountInput = row.querySelector('[data-column="other-amount"] input');
        
        // Diamond and Purchase/Sale Amount fields
        const diamondAmountInput = row.querySelector('[data-column="diamond-amount"] input');
        const purchaseAmountInput = row.querySelector('[data-column="purchase-amount"] input');
        const saleAmountInput = row.querySelector('[data-column="sale-amount"] input');
        const saleAmountWithInput = row.querySelector('[data-column="sale-amount-with"] input');
        
        if (!grossWtInput || !lessWtInput || !purityInput || !netWtInput) {
            return;
        }
        
        // Parse basic values
        const grossWt = parseFloat(grossWtInput.value) || 0;
        const lessWt = parseFloat(lessWtInput.value) || 0;
        let purity = parseFloat(purityInput.value) || 0;
        const wastagePer = parseFloat(wastagePerInput?.value) || 0;
        const goldRate = parseFloat(rateInput?.value) || 0;
        
        // Handle purity format: if purity > 1, assume it's percentage (e.g., 75 = 0.75)
        if (purity > 1) {
            purity = purity / 100;
        }
        
        // ========== WEIGHT CALCULATIONS ==========
        // 1. Net Weight = Gross Weight - Less Weight
        const netWt = grossWt - lessWt;
        if (netWtInput) netWtInput.value = netWt.toFixed(3);
        
        // 2. Purity Weight = Net Weight × Purity
        const purityWt = netWt * purity;
        if (purityWtInput) purityWtInput.value = purityWt.toFixed(3);
        
        // 3. Wastage Weight = Net Weight × (Wastage % / 100)
        const wastageWt = netWt * (wastagePer / 100);
        if (wastageWtInput) wastageWtInput.value = wastageWt.toFixed(3);
        
        // 4. Final Weight = Net Weight + Wastage Weight
        const finalWt = netWt + wastageWt;
        if (finalWtInput) finalWtInput.value = finalWt.toFixed(3);
        
        // 5. Alloy Weight = Gross Weight - Net Weight
        const alloyWt = grossWt - netWt;
        if (alloyWtInput) alloyWtInput.value = alloyWt.toFixed(3);
        
        // ========== METAL VALUE CALCULATION ==========
        // Get calculation type and category
        const calculationSelect = row.querySelector('[data-column="calculation"] select');
        const categorySelect = row.querySelector('[data-column="category"] select');
        const calculationType = calculationSelect ? calculationSelect.value : 'Weight X Rate';
        const categoryId = categorySelect ? categorySelect.value : '';
        
        // Calculate Metal Value based on calculation type
        let metalValue = 0;
        if (calculationType === 'Fix') {
            // For Fix type, use the rate directly as the amount
            metalValue = goldRate;
        } else if (calculationType === 'Stone Charge') {
            // Stone Charge: Use Stone Amount (Stone Weight × Stone Rate)
            const stoneWeight = parseFloat(stoneWeightInput?.value) || 0;
            const stoneRate = parseFloat(stoneRateInput?.value) || 0;
            const stoneAmount = stoneWeight * stoneRate;
            metalValue = stoneAmount;
            // Update stone amount field
            if (stoneAmountInput) stoneAmountInput.value = stoneAmount.toFixed(2);
        } else if (calculationType === 'Weight X Rate') {
            // Weight X Rate: Rate × Weight (using final weight or gross weight)
            const finalWt = parseFloat(finalWtInput?.value) || netWt;
            metalValue = goldRate * finalWt;
        } else if (calculationType === 'Rate X Gross Wt') {
            // Rate X Gross Wt: Rate × Gross Weight
            metalValue = goldRate * grossWt;
        } else if (calculationType === 'Rate X Purity Wt') {
            // Rate X Purity Wt: Rate × Purity Weight
            metalValue = goldRate * purityWt;
        } else if (calculationType === 'Rate X Net Wt') {
            // Rate X Net Wt: Rate × Net Weight
            metalValue = goldRate * netWt;
        } else if (calculationType === 'Rate X Final Wt') {
            // Rate X Final Wt: Rate × Final Weight
            const finalWt = parseFloat(finalWtInput?.value) || netWt;
            metalValue = goldRate * finalWt;
        } else {
            // Default: Weight X Rate
            const finalWt = parseFloat(finalWtInput?.value) || netWt;
            metalValue = goldRate * finalWt;
        }
        
        if (metalValueInput) metalValueInput.value = metalValue.toFixed(2);
        
        // ========== DISCOUNT CALCULATIONS ==========
        // 7. First Discount
        let discount1 = 0;
        if (discountTypeSelect && discountPerInput) {
            const discountType = discountTypeSelect.value || 'On Amount';
            const discountPer = parseFloat(discountPerInput.value) || 0;
            
            if (discountType === 'On Amount') {
                discount1 = discountPer;
            } else if (discountType === 'On Percentage') {
                discount1 = metalValue * (discountPer / 100);
            }
            
            if (discountAmountInput) discountAmountInput.value = discount1.toFixed(2);
            if (discountInput) discountInput.value = discount1.toFixed(2);
        }
        
        // 8. Second Discount
        let discount2 = 0;
        if (discountType2Select && discountPer2Input) {
            const discountType2 = discountType2Select.value || 'On Amount';
            const discountPer2 = parseFloat(discountPer2Input.value) || 0;
            
            if (discountType2 === 'On Amount') {
                discount2 = discountPer2;
            } else if (discountType2 === 'On Percentage') {
                discount2 = metalValue * (discountPer2 / 100);
            }
            
            if (discountAmount2Input) discountAmount2Input.value = discount2.toFixed(2);
        }
        
        const totalDiscount = discount1 + discount2;
        if (discountedAmtInput) discountedAmtInput.value = totalDiscount.toFixed(2);
        
        // ========== MAKING CALCULATION ==========
        // 9. Making Amount
        let makingAmount = 0;
        if (makingTypeSelect && makingRateInput) {
            const makingType = makingTypeSelect.value || 'Fix';
            const makingRate = parseFloat(makingRateInput.value) || 0;
            const makingDiscountAmt = parseFloat(makingDiscountAmtInput?.value) || 0;
            
            if (makingType === 'Fix') {
                makingAmount = makingRate;
            } else if (makingType === 'Percentage') {
                makingAmount = metalValue * (makingRate / 100);
            }
            
            // Apply making discount
            makingAmount = makingAmount - makingDiscountAmt;
            if (makingAmount < 0) makingAmount = 0;
            
            if (makingAmountInput) makingAmountInput.value = makingAmount.toFixed(2);
        }
        
        // ========== STONE CALCULATION ==========
        // 10. Stone Amount calculation based on stone charge type
        let stoneAmount = 0;
        if (stoneChargeTypeSelect && stoneRateInput) {
            const stoneChargeType = stoneChargeTypeSelect.value || 'Fix';
            const stoneRate = parseFloat(stoneRateInput.value) || 0;
            
            if (stoneChargeType === 'Fix') {
                // Fix: Use stone rate directly as the amount (don't multiply by weight)
                stoneAmount = stoneRate;
            } else if (stoneChargeType === 'Per Gram') {
                // Per Gram: Calculate as Stone Weight × Stone Rate
                const stoneWeight = parseFloat(stoneWeightInput?.value) || 0;
            stoneAmount = stoneWeight * stoneRate;
            }
            
            if (stoneAmountInput) stoneAmountInput.value = stoneAmount.toFixed(2);
        }
        
        // If calculation type is "Stone Charge", update metal value to use stone amount
        if (calculationType === 'Stone Charge') {
            metalValue = stoneAmount;
            if (metalValueInput) metalValueInput.value = metalValue.toFixed(2);
        }
        
        // ========== OTHER AMOUNT CALCULATION ==========
        // 11. Other Amount = Other Weight × Other Rate
        let otherAmount = 0;
        if (otherWeightInput && otherRateInput) {
            const otherWeight = parseFloat(otherWeightInput.value) || 0;
            const otherRate = parseFloat(otherRateInput.value) || 0;
            otherAmount = otherWeight * otherRate;
            
            if (otherAmountInput) otherAmountInput.value = otherAmount.toFixed(2);
        }
        
        // ========== AMOUNT CALCULATION ==========
        // 12. Amount = Metal Value + Making Amount + Stone Amount + Other Amount - Total Discount
        // Note: If calculation type is "Stone Charge", metalValue already contains stoneAmount, so don't add it twice
        let calculatedAmount = metalValue + makingAmount + (calculationType === 'Stone Charge' ? 0 : stoneAmount) + otherAmount - totalDiscount;
        if (calculatedAmount < 0) calculatedAmount = 0;
        
        if (amountInput) amountInput.value = calculatedAmount.toFixed(2);
        
        // ========== NET AMOUNT ==========
        // Net Amount = Amount (or use diamond amount if provided)
        const diamondAmount = parseFloat(diamondAmountInput?.value) || 0;
        let netAmt = calculatedAmount + diamondAmount;
        
        if (netAmtInput) netAmtInput.value = netAmt.toFixed(2);
        
        // ========== PURCHASE AMOUNT ==========
        // Purchase Amount = Net Amount (or can be calculated differently)
        if (purchaseAmountInput) purchaseAmountInput.value = netAmt.toFixed(2);
        
        // ========== SALE AMOUNT ==========
        // Sale Amount = Net Amount (or can be calculated with markup)
        if (saleAmountInput) saleAmountInput.value = netAmt.toFixed(2);
        if (saleAmountWithInput) saleAmountWithInput.value = netAmt.toFixed(2);
        
        // ========== TAX CALCULATION ==========
        // 13. Tax = 5% of Net Amount (auto-calculate)
        const tax = netAmt * 0.05; // 5% tax
        if (taxInput) taxInput.value = tax.toFixed(2);
        
        // ========== NET AMOUNT + TAX ==========
        // 14. Net Amount + Tax = Tax + Net Amount
        const netAmtTax = tax + netAmt;
        if (netAmtTaxInput) netAmtTaxInput.value = netAmtTax.toFixed(2);
    }
    
    // Update summary row in table footer (removed - no footer in this design)
    function updateSummaryRow() {
        // Summary calculations moved to updateSummaryPanel
    }
    
    // Load customer previous balance
    function loadCustomerBalance() {
        const customerNameEl = document.getElementById('customerName');
        const customerIdEl = document.getElementById('customerId');
        
        if (!customerNameEl) {
            console.error('Customer name field not found');
            return;
        }
        
        const customerName = customerNameEl.value.trim();
        const customerId = customerIdEl ? customerIdEl.value.trim() : '';
        
        if (!customerName) {
            // Reset previous balance if no customer
            const prevBalanceAmtEl = document.getElementById('previousBalanceAmount');
            const prevBalanceGoldEl = document.getElementById('previousBalanceGold');
            const prevBalanceSilverEl = document.getElementById('previousBalanceSilver');
            
            if (prevBalanceAmtEl) prevBalanceAmtEl.textContent = '0';
            if (prevBalanceGoldEl) prevBalanceGoldEl.textContent = '0';
            if (prevBalanceSilverEl) prevBalanceSilverEl.textContent = '0';
            
            if (typeof updateSummaryPanel === 'function') {
                updateSummaryPanel();
            }
            return;
        }
        
        // Fetch customer balance
        let url = 'ajax/get-customer-balance.php?';
        if (customerId && customerId !== '') {
            url += 'customer_id=' + encodeURIComponent(customerId);
        } else {
            url += 'customer_name=' + encodeURIComponent(customerName);
        }
        
        console.log('Loading customer balance:', { customerId, customerName, url });
        
        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                console.log('Customer balance response:', data);
                
                const prevBalanceAmtEl = document.getElementById('previousBalanceAmount');
                const prevBalanceGoldEl = document.getElementById('previousBalanceGold');
                const prevBalanceSilverEl = document.getElementById('previousBalanceSilver');
                
                if (data.status === 'success' && data.balance) {
                    const amount = Math.abs(parseFloat(data.balance.amount) || 0);
                    const gold = Math.abs(parseFloat(data.balance.gold) || 0);
                    const silver = Math.abs(parseFloat(data.balance.silver) || 0);
                    
                    if (prevBalanceAmtEl) {
                        prevBalanceAmtEl.textContent = amount.toFixed(2);
                        // Store original balance for calculations
                        prevBalanceAmtEl.setAttribute('data-original-balance', amount.toFixed(2));
                    }
                    if (prevBalanceGoldEl) prevBalanceGoldEl.textContent = gold.toFixed(3);
                    if (prevBalanceSilverEl) prevBalanceSilverEl.textContent = silver.toFixed(3);
                } else {
                    if (prevBalanceAmtEl) {
                        prevBalanceAmtEl.textContent = '0';
                        prevBalanceAmtEl.setAttribute('data-original-balance', '0');
                    }
                    if (prevBalanceGoldEl) prevBalanceGoldEl.textContent = '0';
                    if (prevBalanceSilverEl) prevBalanceSilverEl.textContent = '0';
                }
                
                // Update summary panel with new balance
                if (typeof updateSummaryPanel === 'function') {
                    updateSummaryPanel();
                }
            })
            .catch(error => {
                console.error('Error loading customer balance:', error);
                
                const prevBalanceAmtEl = document.getElementById('previousBalanceAmount');
                const prevBalanceGoldEl = document.getElementById('previousBalanceGold');
                const prevBalanceSilverEl = document.getElementById('previousBalanceSilver');
                
                if (prevBalanceAmtEl) prevBalanceAmtEl.textContent = '0';
                if (prevBalanceGoldEl) prevBalanceGoldEl.textContent = '0';
                if (prevBalanceSilverEl) prevBalanceSilverEl.textContent = '0';
                
                if (typeof updateSummaryPanel === 'function') {
                    updateSummaryPanel();
                }
            });
    }
    
    // Update summary panel (right sidebar)
    function updateSummaryPanel() {
        const tbody = document.getElementById('productTableBody');
        const rows = tbody.querySelectorAll('tr:not(.no-drag)');
        
        let totalAmount = 0;
        let totalQuantity = 0;
        let totalGrossWt = 0;
        let totalFinalWt = 0;
        let totalNetWt = 0;
        let totalPureWt = 0;
        let totalMaking = 0;
        let totalTax = 0;
        let totalNetAmt = 0;
        let totalNetAmtTax = 0;
        let totalStoneCharges = 0;
        let totalOtherCharges = 0;
        let totalDiamondValue = 0;
        let totalGemstoneValue = 0;
        let totalRate = 0;
        let totalMetalValue = 0;
        let totalDiscount = 0;
        let totalMakingAmount = 0;
        let totalStoneAmount = 0;
        let totalOtherAmount = 0;
        let totalDiamondAmount = 0;
        let totalPurchaseAmount = 0;
        let totalSaleAmount = 0;
        let totalSaleAmountWith = 0;
        let totalReverse = 0;
        
        rows.forEach(function(row) {
            const qty = parseFloat(row.querySelector('[data-field="quantity"]')?.value || row.querySelector('[data-column="quantity"]')?.textContent) || 0;
            const grossWt = parseFloat(row.querySelector('[data-field="gross_wt"]')?.value || row.querySelector('[data-column="gross-wt"]')?.textContent) || 0;
            const lessWt = parseFloat(row.querySelector('[data-field="less_wt"]')?.value || row.querySelector('[data-column="less-wt"]')?.textContent) || 0;
            const purity = parseFloat(row.querySelector('[data-field="purity"]')?.value || row.querySelector('[data-column="purity"]')?.textContent) || 0;
            const finalWt = parseFloat(row.querySelector('[data-field="final_wt"]')?.value || row.querySelector('[data-column="final-wt"]')?.textContent) || 0;
            const netWt = parseFloat(row.querySelector('[data-column="net-wt"]')?.textContent) || 0;
            const pureWt = parseFloat(row.querySelector('[data-column="pure-wt"]')?.textContent) || 0;
            const making = parseFloat(row.querySelector('[data-field="making"]')?.value || row.querySelector('[data-column="making"]')?.textContent) || 0;
            const tax = parseFloat(row.querySelector('[data-field="tax"]')?.value || row.querySelector('[data-column="tax"]')?.textContent) || 0;
            // Get values from textContent or input value (handle both cases)
            const amountEl = row.querySelector('[data-column="amount"]');
            const amount = parseFloat(amountEl?.textContent || amountEl?.querySelector('input')?.value || 0) || 0;
            
            const netAmtEl = row.querySelector('[data-column="net-amt"]');
            const netAmt = parseFloat(netAmtEl?.textContent || netAmtEl?.querySelector('input')?.value || 0) || 0;
            
            const netAmtTaxEl = row.querySelector('[data-column="net-amt-tax"]');
            const netAmtTax = parseFloat(netAmtTaxEl?.textContent || netAmtTaxEl?.querySelector('input')?.value || 0) || 0;
            const stoneCharges = parseFloat(row.querySelector('[data-field="stone_charges"]')?.value || row.querySelector('[data-column="stone-charges"]')?.textContent) || 0;
            const otherCharges = parseFloat(row.querySelector('[data-field="other_charges"]')?.value || row.querySelector('[data-column="other-charges"]')?.textContent) || 0;
            const diamondValue = parseFloat(row.querySelector('[data-field="diamond_value"]')?.value || row.querySelector('[data-column="diamond-value"]')?.textContent) || 0;
            const gemstoneValue = parseFloat(row.querySelector('[data-field="gemstone_value"]')?.value || row.querySelector('[data-column="gemstone-value"]')?.textContent) || 0;
            const rate = parseFloat(row.querySelector('[data-column="rate"]')?.textContent) || 0;
            const metalValue = parseFloat(row.querySelector('[data-column="metal-value"]')?.textContent) || 0;
            const discount = parseFloat(row.querySelector('[data-column="discount"]')?.textContent) || 0;
            const makingAmount = parseFloat(row.querySelector('[data-column="making-amount"]')?.textContent) || 0;
            const stoneAmount = parseFloat(row.querySelector('[data-column="stone-amount"]')?.textContent) || 0;
            const otherAmount = parseFloat(row.querySelector('[data-column="other-amount"]')?.textContent) || 0;
            const diamondAmount = parseFloat(row.querySelector('[data-column="diamond-amount"]')?.textContent) || 0;
            const purchaseAmount = parseFloat(row.querySelector('[data-column="purchase-amount"]')?.textContent) || 0;
            const saleAmount = parseFloat(row.querySelector('[data-column="sale-amount"]')?.textContent) || 0;
            const saleAmountWith = parseFloat(row.querySelector('[data-column="sale-amount-with"]')?.textContent) || 0;
            const reverse = parseFloat(row.querySelector('[data-column="reverse"]')?.textContent) || 0;
            
            totalQuantity += qty;
            totalGrossWt += grossWt;
            totalFinalWt += finalWt;
            totalNetWt += netWt;
            
            // Update footer totals for less weight and purity (if needed)
            const footerLessWt = document.getElementById('footerLessWt');
            if (footerLessWt) {
                const totalLessWt = Array.from(rows).reduce((sum, r) => {
                    return sum + (parseFloat(r.querySelector('[data-field="less_wt"]')?.value || 0) || 0);
                }, 0);
                footerLessWt.textContent = totalLessWt.toFixed(3);
            }
            totalPureWt += parseFloat(pureWt);
            totalMaking += making;
            totalTax += tax;
            totalAmount += amount;
            totalNetAmt += netAmt;
            totalNetAmtTax += netAmtTax;
            totalStoneCharges += stoneCharges;
            totalOtherCharges += otherCharges;
            totalDiamondValue += diamondValue;
            totalGemstoneValue += gemstoneValue;
            totalRate += rate;
            totalMetalValue += metalValue;
            totalDiscount += discount;
            totalMakingAmount += makingAmount;
            totalStoneAmount += stoneAmount;
            totalOtherAmount += otherAmount;
            totalDiamondAmount += diamondAmount;
            totalPurchaseAmount += purchaseAmount;
            totalSaleAmount += saleAmount;
            totalSaleAmountWith += saleAmountWith;
            totalReverse += reverse;
        });
        
        // Update grand total footer
        const footer = document.getElementById('productTableFooter');
        if (footer && rows.length > 0) {
            footer.style.display = '';
            document.getElementById('footerQuantity').textContent = totalQuantity.toFixed(2);
            document.getElementById('footerGrossWt').textContent = totalGrossWt.toFixed(1);
            document.getElementById('footerFinalWt').textContent = totalFinalWt.toFixed(1);
            document.getElementById('footerNetWt').textContent = totalNetWt.toFixed(1);
            document.getElementById('footerPureWt').textContent = totalPureWt.toFixed(3);
            document.getElementById('footerMaking').textContent = totalMaking;
            document.getElementById('footerTax').textContent = totalTax;
            document.getElementById('footerAmount').textContent = totalAmount.toFixed(2);
            document.getElementById('footerNetAmt').textContent = totalNetAmt.toFixed(2);
            document.getElementById('footerNetAmtTax').textContent = totalNetAmtTax.toFixed(2);
            document.getElementById('footerStoneCharges').textContent = totalStoneCharges.toFixed(2);
            document.getElementById('footerOtherCharges').textContent = totalOtherCharges.toFixed(2);
            document.getElementById('footerDiamondValue').textContent = totalDiamondValue.toFixed(2);
            document.getElementById('footerGemstoneValue').textContent = totalGemstoneValue.toFixed(2);
            document.getElementById('footerRate').textContent = totalRate.toFixed(2);
            document.getElementById('footerMetalValue').textContent = totalMetalValue.toFixed(2);
            document.getElementById('footerDiscount').textContent = totalDiscount.toFixed(2);
            document.getElementById('footerMakingAmount').textContent = totalMakingAmount.toFixed(2);
            document.getElementById('footerStoneAmount').textContent = totalStoneAmount.toFixed(2);
            document.getElementById('footerOtherAmount').textContent = totalOtherAmount.toFixed(2);
            document.getElementById('footerDiamondAmount').textContent = totalDiamondAmount.toFixed(2);
            document.getElementById('footerPurchaseAmount').textContent = totalPurchaseAmount.toFixed(2);
            document.getElementById('footerSaleAmount').textContent = totalSaleAmount.toFixed(2);
            document.getElementById('footerSaleAmountWith').textContent = totalSaleAmountWith.toFixed(2);
            document.getElementById('footerReverse').textContent = totalReverse.toFixed(2);
        } else if (footer) {
            footer.style.display = 'none';
        }
        
        // Update summary values
        const summaryTotal = document.getElementById('summaryTotal');
        if (summaryTotal) summaryTotal.textContent = totalNetAmtTax.toFixed(2);
        
        const summaryGrandTotal = document.getElementById('summaryGrandTotal');
        if (summaryGrandTotal) summaryGrandTotal.textContent = totalNetAmtTax.toFixed(2);
        
        // Calculate paid amounts from payment table
        const paymentRows = document.querySelectorAll('#paymentTableBody tr:not(.no-payment-row)');
        let paidAmt = 0; // Total paid (current order + previous balance)
        let paidCurrentOrderAmt = 0; // Paid for current order only
        let paidPreviousBalanceAmt = 0; // Paid for previous balance only
        
        paymentRows.forEach(function(row) {
            const totalAmt = parseFloat(row.querySelector('[data-payment-amount]')?.textContent || 0);
            const prevBalAmt = parseFloat(row.getAttribute('data-previous-balance-amount') || 0);
            // Get current order amount from data attribute, or calculate it
            let currentOrderAmt = parseFloat(row.getAttribute('data-current-order-amount') || 0);
            // If not set, calculate: total - previous balance
            if (currentOrderAmt === 0 && totalAmt > prevBalAmt) {
                currentOrderAmt = totalAmt - prevBalAmt;
            }
            
            paidAmt += totalAmt; // Total payment includes both current order and previous balance
            paidCurrentOrderAmt += currentOrderAmt;
            paidPreviousBalanceAmt += prevBalAmt;
        });
        
        const summaryPaidAmt = document.getElementById('summaryPaidAmt');
        if (summaryPaidAmt) summaryPaidAmt.textContent = paidAmt.toFixed(2);
        
        // Get original previous balance (from database/initial load)
        // Always use data-original-balance if it exists, otherwise use textContent and store it
        const previousBalanceEl = document.getElementById('previousBalanceAmount');
        let originalPreviousBalance = 0;
        
        if (previousBalanceEl) {
            const storedOriginal = previousBalanceEl.getAttribute('data-original-balance');
            if (storedOriginal && parseFloat(storedOriginal) > 0) {
                // Use stored original balance
                originalPreviousBalance = parseFloat(storedOriginal);
            } else {
                // First time - use textContent as original and store it
                // But only if textContent is greater than 0 (to avoid using 0 as original)
                const textContentValue = parseFloat(previousBalanceEl.textContent || 0);
                if (textContentValue > 0) {
                    originalPreviousBalance = textContentValue;
                    previousBalanceEl.setAttribute('data-original-balance', originalPreviousBalance.toFixed(2));
                } else {
                    // If both are 0 or missing, try to get from order data or keep as 0
                    originalPreviousBalance = 0;
                }
            }
        }
        
        // Calculate remaining previous balance (original - paid towards previous balance)
        const remainingPreviousBalance = Math.max(0, originalPreviousBalance - paidPreviousBalanceAmt);
        
        // Update displayed previous balance to show remaining balance
        if (previousBalanceEl) {
            previousBalanceEl.textContent = remainingPreviousBalance.toFixed(2);
        }
        
        // Balance Amt = Remaining Previous Balance + Grand Total (Net Amt+Tax) - Paid Current Order Amount
        const balanceAmt = remainingPreviousBalance + totalNetAmtTax - paidCurrentOrderAmt;
        const summaryBalanceAmt = document.getElementById('summaryBalanceAmt');
        if (summaryBalanceAmt) {
            summaryBalanceAmt.textContent = balanceAmt.toFixed(2);
        }
    }
    
    // Delete product row
    function deleteProductRow(rowId) {
        if (confirm('Are you sure you want to remove this product?')) {
            const row = document.getElementById(rowId);
            if (row) {
                row.remove();
                checkEmptyTable();
                // Update summary panel to recalculate totals after deletion
                if (typeof updateSummaryPanel === 'function') {
                    updateSummaryPanel();
                }
            }
        }
    }
    
    // Edit product row - Open product creation modal in edit mode
    function editProductRow(rowId) {
        const row = document.getElementById(rowId);
        if (!row) {
            alert('Product row not found');
            return;
        }
        
        // Get product ID and characteristic ID from row
        const productId = row.getAttribute('data-product-id');
        const characteristicId = row.getAttribute('data-characteristic-id');
        
        if (!productId) {
            alert('Product ID not found');
            return;
        }
        
        // Extract all data from the Product List table row
        const rowData = {
            product_id: productId,
            characteristic_id: characteristicId || '',
            quantity: parseFloat(row.querySelector('[data-field="quantity"]')?.value || row.querySelector('[data-column="quantity"]')?.textContent?.trim() || 1),
            gross_wt: parseFloat(row.querySelector('[data-field="gross_wt"]')?.value || row.querySelector('[data-column="gross-wt"]')?.textContent?.trim() || 0),
            less_wt: parseFloat(row.querySelector('[data-field="less_wt"]')?.value || row.querySelector('[data-column="less-wt"]')?.textContent?.trim() || 0),
            purity: parseFloat(row.querySelector('[data-field="purity"]')?.value || row.querySelector('[data-column="purity"]')?.textContent?.trim() || 1),
            final_wt: parseFloat(row.querySelector('[data-field="final_wt"]')?.value || row.querySelector('[data-column="final-wt"]')?.textContent?.trim() || 0),
            making: parseFloat(row.querySelector('[data-field="making"]')?.value || row.querySelector('[data-column="making"]')?.textContent?.trim() || 0),
            design_no: row.querySelector('[data-field="design_no"]')?.value || row.querySelector('[data-column="design-no"]')?.textContent?.trim() || '',
            tax: parseFloat(row.querySelector('[data-field="tax"]')?.value || row.querySelector('[data-column="tax"]')?.textContent?.trim() || 0),
            amount: parseFloat(row.querySelector('[data-column="amount"]')?.textContent?.trim() || 0),
            net_amt: parseFloat(row.querySelector('[data-column="net-amt"]')?.textContent?.trim() || 0),
            net_amt_tax: parseFloat(row.querySelector('[data-column="net-amt-tax"]')?.textContent?.trim() || 0),
            stone_charges: parseFloat(row.querySelector('[data-field="stone_charges"]')?.value || row.querySelector('[data-column="stone-charges"]')?.textContent?.trim() || 0),
            other_charges: parseFloat(row.querySelector('[data-field="other_charges"]')?.value || row.querySelector('[data-column="other-charges"]')?.textContent?.trim() || 0),
            diamond_value: parseFloat(row.querySelector('[data-field="diamond_value"]')?.value || row.querySelector('[data-column="diamond-value"]')?.textContent?.trim() || 0),
            gemstone_value: parseFloat(row.querySelector('[data-field="gemstone_value"]')?.value || row.querySelector('[data-column="gemstone-value"]')?.textContent?.trim() || 0),
            product_name: row.querySelector('[data-column="description"] a')?.textContent?.trim() || row.querySelector('[data-column="description"]')?.textContent?.trim() || ''
        };
        
        // Fetch product details to get full product information
        const url = 'ajax/get-product-details.php?product_id=' + productId + (characteristicId ? '&characteristic_id=' + characteristicId : '');
        
        fetch(url)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.product) {
                    // Open Product Selection modal (Add Item popup)
                    openProductModal();
                    
                    // Store row ID for updating after save
                    window.currentEditingRowId = rowId;
                    
                    // Wait for modal to be fully shown, then add row with data
                    setTimeout(function() {
                        // Clear the product list body
                        const productListBody = document.getElementById('productListBody');
                        if (productListBody) {
                            productListBody.innerHTML = '';
                        }
                        
                        // Create item object from row data and product data
                        const item = {
                            product_id: rowData.product_id,
                            product_characteristic_id: rowData.characteristic_id,
                            product_name: rowData.product_name || data.product.name,
                            quantity: rowData.quantity,
                            gross_weight: rowData.gross_wt,
                            less_weight: rowData.less_wt,
                            purity: rowData.purity,
                            final_weight: rowData.final_wt,
                            making_amount: rowData.making,
                            design_no: rowData.design_no,
                            tax_amount: rowData.tax,
                            amount: rowData.amount,
                            net_amount: rowData.net_amt,
                            net_amount_tax: rowData.net_amt_tax,
                            stone_amount: rowData.stone_charges,
                            other_amount: rowData.other_charges,
                            diamond_amount: rowData.diamond_value,
                            // Add other fields with defaults
                            rate: data.product.rate || 0,
                            calculation_mode: 'Weight X Rate',
                            category_id: data.product.category_id || '',
                            location_id: '',
                            carat_id: ''
                        };
                        
                        // Create product object
                        const product = {
                            id: data.product.id,
                            name: data.product.name,
                            characteristic_id: data.product.characteristic_id || '',
                            opening_weight: rowData.gross_wt,
                            opening_purity: rowData.purity,
                            final_weight: rowData.final_wt,
                            rate: data.product.rate || 0,
                            value: rowData.amount,
                            article: rowData.design_no
                        };
                        
                        // Add row to Product Selection table
                        if (typeof addProductRowToSelectionTable === 'function') {
                            addProductRowToSelectionTable(item, product);
                            
                            // Select the row checkbox
                            const addedRow = productListBody.querySelector('.product-row');
                            if (addedRow) {
                                const checkbox = addedRow.querySelector('.product-checkbox');
                                if (checkbox) {
                                    checkbox.checked = true;
                                    addedRow.classList.add('selected');
                                    addedRow.style.backgroundColor = '#fff3cd';
                                }
                            }
                        } else {
                            console.error('addProductRowToSelectionTable function not found');
                        }
                    }, 500);
                    
                } else {
                    alert('Error loading product details: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error fetching product details:', error);
                alert('Error loading product details');
            });
    }
    
    // Check if table is empty
    function checkEmptyTable() {
        const tbody = document.getElementById('productTableBody');
        const rows = tbody.querySelectorAll('tr:not(.no-drag)');
        if (rows.length === 0) {
            tbody.innerHTML = '<tr class="no-drag"><td colspan="18" class="text-center text-muted py-4" id="emptyRowCell">No Rows To Show</td></tr>';
            updateSummaryPanel();
        }
    }
    
    // Add Item button/link click - Use event delegation to ensure it works
    $(document).ready(function() {
        // Use jQuery event delegation for better reliability
        $(document).on('click', '#addItemBtn, #addItemBtn a', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Add Item button/link clicked');
            // Just open modal without creating rows
            currentEditingRowId = null; // Clear editing state so it adds new row
            openProductModal();
        });
        
        // Also attach native event listener as backup
        const addItemBtn = document.getElementById('addItemBtn');
        if (addItemBtn) {
            console.log('Add Item button found, attaching native event listener');
            addItemBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Add Item button clicked (native)');
                // Just open modal without creating rows
                currentEditingRowId = null; // Clear editing state so it adds new row
                openProductModal();
            });
            // Also handle link inside
            const addItemLink = addItemBtn.querySelector('a');
            if (addItemLink) {
                addItemLink.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('Add Item link clicked (native)');
                    // Just open modal without creating rows
                    currentEditingRowId = null; // Clear editing state so it adds new row
                    openProductModal();
                });
            }
        } else {
            console.warn('Add Item button not found on page load, will use event delegation');
        }
    });
    
    // Keyboard shortcut for Add Item
    document.addEventListener('keydown', function(e) {
        if (e.shiftKey && e.key === 'Q') {
            e.preventDefault();
            // Just open modal without creating rows
            currentEditingRowId = null; // Clear editing state so it adds new row
            openProductModal();
        }
    });
    
    // Product search in modal
    const modalProductSearchInput = document.getElementById('modalProductSearchInput');
    if (modalProductSearchInput) {
        let searchTimeout;
        modalProductSearchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const search = this.value;
            searchTimeout = setTimeout(function() {
                if (currentMetalId) {
                    loadProducts(currentMetalId, search);
                }
            }, 300); // Debounce search
        });
    }
    
    // Modal Add Button - Add all selected products to table
    const modalAddBtn = document.getElementById('modalAddBtn');
    if (modalAddBtn) {
        modalAddBtn.addEventListener('click', function() {
            const selectedCheckboxes = document.querySelectorAll('#productListBody .product-checkbox:checked');
            if (selectedCheckboxes.length === 0) {
                alert('Please select at least one product from the list');
                return;
            }
            
            // Check if we're in edit mode
            if (currentEditingRowId) {
                // Edit mode: Extract data from the selected Product Selection modal row and update the Product List table row
                const selectedRow = selectedCheckboxes[0].closest('.product-row');
                if (selectedRow) {
                    updateProductListRowFromModalRow(currentEditingRowId, selectedRow);
                    
                    // Clear selection
                    selectedCheckboxes.forEach(function(cb) {
                        cb.checked = false;
                        const row = cb.closest('.product-row');
                        if (row) {
                            row.classList.remove('selected');
                            row.style.backgroundColor = '';
                        }
                    });
                    const selectAllCheckbox = document.getElementById('selectAllProducts');
                    if (selectAllCheckbox) selectAllCheckbox.checked = false;
                    
                    // Close modal
                    hideProductModal();
                    
                    // Clear editing state
                    currentEditingRowId = null;
                    
                    // Update summary
                    updateSummaryPanel();
                    return;
                }
            }
            
            // Add mode: Add each selected product sequentially
            const selectedRows = Array.from(selectedCheckboxes).map(function(cb) {
                return cb.closest('.product-row');
            }).filter(function(row) { return row !== null; });
            
            if (selectedRows.length === 0) return;
            
            // Process products one by one
            let index = 0;
            function addNextProduct() {
                if (index >= selectedRows.length) {
                    // All products added, close modal and uncheck all
                    selectedCheckboxes.forEach(function(cb) {
                        cb.checked = false;
                        const row = cb.closest('.product-row');
                        if (row) {
                            row.classList.remove('selected');
                            row.style.backgroundColor = '';
                        }
                    });
                    const selectAllCheckbox = document.getElementById('selectAllProducts');
                    if (selectAllCheckbox) selectAllCheckbox.checked = false;
                    hideProductModal();
                    updateSummaryPanel();
                    return;
                }
                
                const row = selectedRows[index];
                selectProduct(row, false); // Don't close modal yet
                index++;
                
                // Add next product after a short delay to allow AJAX to complete
                setTimeout(addNextProduct, 150);
            }
            
            addNextProduct();
        });
    }
    
    // Keyboard shortcut for Add in modal (Shift + A)
    document.addEventListener('keydown', function(e) {
        const modal = document.getElementById('productSelectionModal');
        if (modal && (modal.classList.contains('show') || modal.style.display === 'block')) {
            if (e.shiftKey && e.key === 'A') {
                e.preventDefault();
                // Trigger the Add button click to use the same logic
                if (modalAddBtn) {
                    modalAddBtn.click();
                }
            }
        }
    });
    
    // Clear all modal fields
    function clearModalFields() {
        // Clear only the fields that exist above the table
        const fields = {
            'modalProductBarcode': '',
            'modalProductCode': '',
            'modalProductDesignNo': '',
            'modalProductQty': '1',
            'modalMetalUnfix': false,
            'modalUnfix': false,
            'modalGroupName': '',
            'modalComment': ''
        };
        
        Object.keys(fields).forEach(function(fieldId) {
            const field = document.getElementById(fieldId);
            if (field) {
                if (field.type === 'checkbox') {
                    field.checked = fields[fieldId];
                } else {
                    field.value = fields[fieldId];
                }
            }
        });
    }

    // Table Settings - Column Visibility Toggle
    (function() {
        const settingsBtn = document.getElementById('tableSettingsBtn');
        const settingsDropdown = document.getElementById('tableSettingsDropdown');
        const checkboxes = settingsDropdown.querySelectorAll('input[type="checkbox"]');
        
        // Toggle dropdown on button click
        settingsBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            settingsDropdown.classList.toggle('show');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!settingsBtn.contains(e.target) && !settingsDropdown.contains(e.target)) {
                settingsDropdown.classList.remove('show');
            }
        });

        // Handle column visibility
        checkboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                const columnName = this.getAttribute('data-column');
                const isVisible = this.checked;
                
                // Toggle visibility for all th and td elements with this column
                const headers = document.querySelectorAll(`.product-table th[data-column="${columnName}"]`);
                const cells = document.querySelectorAll(`.product-table td[data-column="${columnName}"]`);
                
                headers.forEach(function(header) {
                    if (isVisible) {
                        header.classList.remove('hidden');
                    } else {
                        header.classList.add('hidden');
                    }
                });
                
                cells.forEach(function(cell) {
                    if (isVisible) {
                        cell.classList.remove('hidden');
                    } else {
                        cell.classList.add('hidden');
                    }
                });

                // Update colspan for empty state row
                const emptyRowCell = document.getElementById('emptyRowCell');
                if (emptyRowCell) {
                    const visibleColumns = Array.from(checkboxes).filter(cb => cb.checked).length;
                    // Add 1 for drag handle column (always visible)
                    emptyRowCell.setAttribute('colspan', visibleColumns + 1);
                }
            });
        });
    })();

    // Column Drag and Drop Functionality
    (function() {
        const table = document.querySelector('.product-table');
        if (!table) return;
        
        const thead = table.querySelector('thead tr');
        const tbody = document.getElementById('productTableBody');
        if (!thead || !tbody) return;
        
        let draggedColumn = null;
        let draggedColumnIndex = null;
        let dragOverColumn = null;
        let dragOverPosition = null;

        // Get all draggable column headers
        function getDraggableColumns() {
            return thead.querySelectorAll('th.draggable-column');
        }

        // Get column index from header
        function getColumnIndex(th) {
            return Array.from(thead.children).indexOf(th);
        }

        // Reorder columns in all rows
        function reorderColumns(dragIndex, dropIndex) {
            const allRows = [thead, ...Array.from(tbody.querySelectorAll('tr'))];
            
            allRows.forEach(row => {
                const cells = Array.from(row.children);
                const draggedCell = cells[dragIndex];
                
                if (draggedCell) {
                    cells.splice(dragIndex, 1);
                    cells.splice(dropIndex, 0, draggedCell);
                    
                    // Reorder in DOM
                    cells.forEach(cell => row.appendChild(cell));
                }
            });
        }

        // Initialize column drag and drop
        function initColumnDragAndDrop() {
            const columns = getDraggableColumns();
            
            columns.forEach(th => {
                th.addEventListener('dragstart', function(e) {
                    draggedColumn = th;
                    draggedColumnIndex = getColumnIndex(th);
                    th.classList.add('dragging-column');
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', ''); // Required for Firefox
                    e.stopPropagation();
                }, false);

                th.addEventListener('dragend', function(e) {
                    if (draggedColumn) {
                        draggedColumn.classList.remove('dragging-column');
                    }
                    // Remove all drag-over classes
                    getDraggableColumns().forEach(col => {
                        col.classList.remove('drag-over-column', 'drag-over-column-right');
                    });
                    draggedColumn = null;
                    draggedColumnIndex = null;
                    dragOverColumn = null;
                    dragOverPosition = null;
                }, false);

                th.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    e.dataTransfer.dropEffect = 'move';

                    if (!draggedColumn || th === draggedColumn) {
                        return;
                    }

                    // Remove previous drag-over classes
                    getDraggableColumns().forEach(col => {
                        col.classList.remove('drag-over-column', 'drag-over-column-right');
                    });

                    // Calculate position (left or right half of column)
                    const rect = th.getBoundingClientRect();
                    const mouseX = e.clientX;
                    const colMiddle = rect.left + rect.width / 2;

                    if (mouseX < colMiddle) {
                        th.classList.add('drag-over-column');
                        dragOverColumn = th;
                        dragOverPosition = 'left';
                    } else {
                        th.classList.add('drag-over-column-right');
                        dragOverColumn = th;
                        dragOverPosition = 'right';
                    }
                }, false);

                th.addEventListener('drop', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    if (!draggedColumn || !dragOverColumn || dragOverColumn === draggedColumn) {
                        return;
                    }

                    const dropIndex = getColumnIndex(dragOverColumn);
                    const dragIndex = draggedColumnIndex;

                    // Remove drag-over classes
                    getDraggableColumns().forEach(col => {
                        col.classList.remove('drag-over-column', 'drag-over-column-right');
                    });

                    // Calculate final drop position
                    let finalDropIndex = dropIndex;
                    if (dragOverPosition === 'right' && dragIndex < dropIndex) {
                        finalDropIndex = dropIndex + 1;
                    } else if (dragOverPosition === 'left' && dragIndex > dropIndex) {
                        finalDropIndex = dropIndex;
                    } else if (dragOverPosition === 'right' && dragIndex > dropIndex) {
                        finalDropIndex = dropIndex + 1;
                    } else {
                        finalDropIndex = dropIndex;
                    }

                    // Reorder columns
                    reorderColumns(dragIndex, finalDropIndex);
                    
                    // Reset
                    draggedColumn = null;
                    draggedColumnIndex = null;
                    dragOverColumn = null;
                    dragOverPosition = null;
                }, false);
            });
        }

        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                initColumnDragAndDrop();
            });
        } else {
            initColumnDragAndDrop();
        }

        // Add sample rows for testing
        setTimeout(function() {
            const hasRealRows = tbody.querySelectorAll('tr:not(.no-drag)').length > 0;
            if (!hasRealRows) {
                const testRows = [
                               ];
                
                const emptyRow = tbody.querySelector('.no-drag');
                if (emptyRow) {
                    emptyRow.remove();
                }
                
                testRows.forEach((rowData) => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td></td>
                        <td data-column="barcode">${rowData[0]}</td>
                        <td data-column="description">${rowData[1]}</td>
                        <td data-column="quantity">${rowData[2]}</td>
                        <td data-column="gross-wt">${rowData[3]}</td>
                        <td data-column="final-wt">${rowData[4]}</td>
                        <td data-column="net-wt">${rowData[5]}</td>
                        <td data-column="pure-wt">${rowData[6]}</td>
                        <td data-column="making">${rowData[7]}</td>
                        <td data-column="tax">${rowData[8]}</td>
                        <td data-column="amount">${rowData[9]}</td>
                        <td data-column="net">${rowData[10]}</td>
                    `;
                    tbody.appendChild(row);
                });
            }
        }, 500);
    })();

    // Fullscreen Toggle Functionality
    (function() {
        const fullscreenBtn = document.getElementById('fullscreenBtn');
        const fullscreenIcon = fullscreenBtn.querySelector('i');
        
        if (!fullscreenBtn) return;

        // Function to toggle fullscreen
        function toggleFullscreen() {
            if (!document.fullscreenElement && !document.webkitFullscreenElement && 
                !document.mozFullScreenElement && !document.msFullscreenElement) {
                // Enter fullscreen
                if (document.documentElement.requestFullscreen) {
                    document.documentElement.requestFullscreen();
                } else if (document.documentElement.webkitRequestFullscreen) {
                    document.documentElement.webkitRequestFullscreen();
                } else if (document.documentElement.mozRequestFullScreen) {
                    document.documentElement.mozRequestFullScreen();
                } else if (document.documentElement.msRequestFullscreen) {
                    document.documentElement.msRequestFullscreen();
                }
            } else {
                // Exit fullscreen
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                } else if (document.mozCancelFullScreen) {
                    document.mozCancelFullScreen();
                } else if (document.msExitFullscreen) {
                    document.msExitFullscreen();
                }
            }
        }

        // Update icon based on fullscreen state
        function updateFullscreenIcon() {
            const isFullscreen = !!(document.fullscreenElement || document.webkitFullscreenElement || 
                                   document.mozFullScreenElement || document.msFullscreenElement);
            
            if (isFullscreen) {
                fullscreenIcon.className = 'feather icon-minimize-2';
                fullscreenBtn.title = 'Exit Fullscreen';
            } else {
                fullscreenIcon.className = 'feather icon-maximize-2';
                fullscreenBtn.title = 'Fullscreen';
            }
        }

        // Add click event listener
        fullscreenBtn.addEventListener('click', function(e) {
            e.preventDefault();
            toggleFullscreen();
        });

        // Listen for fullscreen changes
        document.addEventListener('fullscreenchange', updateFullscreenIcon);
        document.addEventListener('webkitfullscreenchange', updateFullscreenIcon);
        document.addEventListener('mozfullscreenchange', updateFullscreenIcon);
        document.addEventListener('MSFullscreenChange', updateFullscreenIcon);

        // Also handle ESC key to exit fullscreen (browser default, but update icon)
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                setTimeout(updateFullscreenIcon, 100);
            }
        });
    })();

    // User Dropdown Menu Toggle
    (function() {
        const userDropdownToggle = document.getElementById('userDropdownToggle');
        const userDropdown = document.querySelector('.user-dropdown');
        const userDropdownMenu = document.getElementById('userDropdownMenu');
        
        if (!userDropdownToggle || !userDropdown || !userDropdownMenu) return;

        // Toggle dropdown on click
        userDropdownToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdown.classList.toggle('show');
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!userDropdown.contains(e.target)) {
                userDropdown.classList.remove('show');
            }
        });

        // Close dropdown when clicking on a menu item
        const dropdownItems = userDropdownMenu.querySelectorAll('.dropdown-item');
        dropdownItems.forEach(function(item) {
            item.addEventListener('click', function(e) {
                // Allow the link to work, but close dropdown after a short delay
                setTimeout(function() {
                    userDropdown.classList.remove('show');
                }, 100);
            });
        });

        // Close dropdown on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && userDropdown.classList.contains('show')) {
                userDropdown.classList.remove('show');
            }
        });
    })();
    
    // ================== SAVE ORDER FUNCTIONALITY ==================
    
    // Save order to database
    function saveOrder() {
        // Validate required fields
        const customerName = document.getElementById('customerName')?.value.trim();
        if (!customerName) {
            alert('Please enter customer name');
            document.getElementById('customerName')?.focus();
            return;
        }
        
        // Get current order number from display
        const currentOrderNoText = document.getElementById('currentOrderNo')?.textContent || '<?php echo $next_order_no; ?>';
        
        // Get order ID from URL or current order
        const urlParams = new URLSearchParams(window.location.search);
        const urlOrderId = urlParams.get('id');
        const currentOrderId = urlOrderId ? parseInt(urlOrderId) : <?php echo $edit_order_id; ?>;
        
        // Get customer ID
        const customerId = document.getElementById('customerId')?.value || '';
        
        // Collect billing form data
        const orderData = {
            order_no: currentOrderNoText,
            order_id: currentOrderId,
            customer_id: customerId,
            customer_name: customerName,
            against_of: document.getElementById('againstOf')?.value || '',
            currency: document.getElementById('currency')?.value || 'AED',
            ref_no: document.getElementById('refNo')?.value || '',
            sales_person: document.getElementById('salesPerson')?.value || '',
            order_date: document.getElementById('orderDate')?.value || '<?php echo date('Y-m-d'); ?>',
            due_date: document.getElementById('dueDate')?.value || '',
            layaways: document.getElementById('layaways')?.value || '',
            fixing_type: document.getElementById('fixingType')?.value || 'Standard',
            group_name: document.getElementById('groupName')?.value || '',
            comment: document.getElementById('orderComment')?.value || ''
        };
        
        // Collect summary values
        const summaryTotal = parseFloat(document.getElementById('summaryTotal')?.textContent || 0);
        const summaryGrandTotal = parseFloat(document.getElementById('summaryGrandTotal')?.textContent || 0);
        const summaryPaidAmt = parseFloat(document.getElementById('summaryPaidAmt')?.textContent || 0);
        const summaryBalanceAmt = parseFloat(document.getElementById('summaryBalanceAmt')?.textContent || 0);
        const summaryMetalAmt = parseFloat(document.getElementById('summaryMetalAmt')?.textContent || 0);
        const roundOffValue = parseFloat(document.getElementById('roundOffValue')?.value || 0);
        const roundOffChecked = document.getElementById('roundOff')?.checked || false;
        
        // Save original previous balance (not the remaining balance)
        const previousBalanceEl = document.getElementById('previousBalanceAmount');
        const originalPreviousBalance = parseFloat(previousBalanceEl?.getAttribute('data-original-balance') || previousBalanceEl?.textContent || 0);
        orderData.previous_balance = originalPreviousBalance; // Save original, not remaining
        orderData.previous_gold = parseFloat(document.getElementById('previousBalanceGold')?.textContent || 0);
        orderData.previous_silver = parseFloat(document.getElementById('previousBalanceSilver')?.textContent || 0);
        orderData.subtotal = summaryTotal;
        orderData.additional_amt = 0;
        orderData.net_total = summaryTotal;
        orderData.reward_points = 0;
        orderData.coupon_code = '';
        orderData.coupon_discount = 0;
        orderData.discount_amt = 0;
        orderData.redeem_points = 0;
        orderData.grand_total = summaryGrandTotal;
        orderData.advance_payment = 0;
        orderData.metal_amt = summaryMetalAmt;
        orderData.round_off = roundOffChecked ? roundOffValue : 0;
        orderData.paid_amt = summaryPaidAmt;
        orderData.balance_amt = summaryBalanceAmt;
        
        // Collect product items
        const items = [];
        const productRows = document.querySelectorAll('#productTableBody tr:not(.no-drag)');
        productRows.forEach(function(row) {
            const productId = row.getAttribute('data-product-id');
            const characteristicId = row.getAttribute('data-characteristic-id');
            
            if (productId) {
                const productName = row.querySelector('[data-column="description"] a')?.textContent || '';
                const quantity = parseFloat(row.querySelector('[data-field="quantity"]')?.value || row.querySelector('[data-column="quantity"]')?.textContent) || 0;
                const grossWeight = parseFloat(row.querySelector('[data-field="gross_wt"]')?.value || row.querySelector('[data-column="gross-wt"]')?.textContent) || 0;
                const lessWeight = parseFloat(row.querySelector('[data-field="less_wt"]')?.value || row.querySelector('[data-column="less-wt"]')?.textContent) || 0;
                const finalWeight = parseFloat(row.querySelector('[data-field="final_wt"]')?.value || row.querySelector('[data-column="final-wt"]')?.textContent) || 0;
                const netWeight = parseFloat(row.querySelector('[data-column="net-wt"]')?.textContent) || 0;
                const pureWeight = parseFloat(row.querySelector('[data-column="pure-wt"]')?.textContent) || 0;
                const purity = parseFloat(row.getAttribute('data-purity')) || parseFloat(row.querySelector('[data-field="purity"]')?.value || 0);
                const rate = parseFloat(row.getAttribute('data-rate')) || parseFloat(row.querySelector('[data-column="rate"]')?.textContent || 0);
                const making = parseFloat(row.querySelector('[data-field="making"]')?.value || row.querySelector('[data-column="making"]')?.textContent) || 0;
                const designNo = row.querySelector('[data-field="design_no"]')?.value || row.querySelector('[data-column="design-no"]')?.textContent || '';
                const tax = parseFloat(row.querySelector('[data-field="tax"]')?.value || row.querySelector('[data-column="tax"]')?.textContent) || 0;
                const amount = parseFloat(row.querySelector('[data-column="amount"]')?.textContent) || 0;
                const netAmount = parseFloat(row.querySelector('[data-column="net-amt"]')?.textContent) || 0;
                const netAmtWithTax = parseFloat(row.querySelector('[data-column="net-amt-tax"]')?.textContent) || 0;
                const stoneCharges = parseFloat(row.querySelector('[data-field="stone_charges"]')?.value || row.querySelector('[data-column="stone-charges"]')?.textContent) || 0;
                const otherCharges = parseFloat(row.querySelector('[data-field="other_charges"]')?.value || row.querySelector('[data-column="other-charges"]')?.textContent) || 0;
                const diamondValue = parseFloat(row.querySelector('[data-field="diamond_value"]')?.value || row.querySelector('[data-column="diamond-value"]')?.textContent) || 0;
                const gemstoneValue = parseFloat(row.querySelector('[data-field="gemstone_value"]')?.value || row.querySelector('[data-column="gemstone-value"]')?.textContent) || 0;
                const metalValue = parseFloat(row.querySelector('[data-column="metal-value"]')?.textContent) || 0;
                const discount = parseFloat(row.querySelector('[data-column="discount"]')?.textContent) || 0;
                const makingAmount = parseFloat(row.querySelector('[data-column="making-amount"]')?.textContent) || 0;
                const stoneAmount = parseFloat(row.querySelector('[data-column="stone-amount"]')?.textContent) || 0;
                const otherAmount = parseFloat(row.querySelector('[data-column="other-amount"]')?.textContent) || 0;
                const diamondAmount = parseFloat(row.querySelector('[data-column="diamond-amount"]')?.textContent) || 0;
                const purchaseAmount = parseFloat(row.querySelector('[data-column="purchase-amount"]')?.textContent) || 0;
                const saleAmount = parseFloat(row.querySelector('[data-column="sale-amount"]')?.textContent) || 0;
                const saleAmountWith = parseFloat(row.querySelector('[data-column="sale-amount-with"]')?.textContent) || 0;
                const reverse = parseFloat(row.querySelector('[data-column="reverse"]')?.textContent) || 0;
                
                items.push({
                    product_id: productId,
                    characteristic_id: characteristicId,
                    barcode: '',
                    product_name: productName,
                    carat: '',
                    quantity: quantity,
                    gross_weight: grossWeight,
                    less_weight: lessWeight,
                    purity: purity,
                    purity_weight: pureWeight,
                    final_weight: finalWeight,
                    net_weight: netWeight,
                    pure_weight: pureWeight,
                    rate: rate,
                    making: making,
                    making_amount: makingAmount,
                    design_no: designNo,
                    tax: tax,
                    amount: amount,
                    net_amount: netAmount,
                    net_amt_with_tax: netAmtWithTax,
                    stone_charges: stoneCharges,
                    stone_amount: stoneAmount,
                    other_charges: otherCharges,
                    other_amount: otherAmount,
                    diamond_value: diamondValue,
                    diamond_amount: diamondAmount,
                    gemstone_value: gemstoneValue,
                    metal_value: metalValue,
                    discount: discount,
                    purchase_amount: purchaseAmount,
                    sale_amount: saleAmount,
                    sale_amount_with: saleAmountWith,
                    reverse: reverse
                });
            }
        });
        
        orderData.items = items;
        
        // Collect payments
        const paymentRows = document.querySelectorAll('#paymentTableBody tr:not(.no-payment-row)');
        const paymentData = [];
        paymentRows.forEach(function(row) {
            const paymentType = row.querySelector('td:first-child')?.textContent || '';
            const depositInto = row.querySelector('td:nth-child(2)')?.textContent || '';
            const transactionNo = row.querySelector('td:nth-child(3)')?.textContent || '';
            const chequeDate = row.querySelector('td:nth-child(4)')?.textContent || '';
            const purityCarat = row.querySelector('td:nth-child(5)')?.textContent || '';
            const amount = parseFloat(row.querySelector('[data-payment-amount]')?.textContent || 0);
            const diamondCategory = row.querySelector('td:nth-child(7)')?.textContent || '';
            const quantity = parseFloat(row.querySelector('td:nth-child(8)')?.textContent || 0);
            
            if (amount > 0) {
                const previousBalanceAmount = parseFloat(row.getAttribute('data-previous-balance-amount') || 0);
                const currentOrderAmount = parseFloat(row.getAttribute('data-current-order-amount') || amount);
                
                paymentData.push({
                    payment_type: paymentType,
                    deposit_into: depositInto,
                    transaction_no: transactionNo,
                    cheque_date: chequeDate || null,
                    purity_carat: purityCarat,
                    amount: amount, // Total amount (current + previous balance)
                    previous_balance_amount: previousBalanceAmount,
                    current_order_amount: currentOrderAmount,
                    diamond_category: diamondCategory,
                    quantity: quantity
                });
            }
        });
        
        orderData.payments = paymentData;
        
        // Show loading and prevent double submission
        const saveBtn = document.querySelector('.btn-save-invoice, .btn-save-order');
        if (saveBtn && saveBtn.disabled) {
            return; // Already saving, prevent double submission
        }
        const originalText = saveBtn?.textContent || 'Save';
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';
        }
        
        // Convert arrays to JSON strings for POST
        const postData = {};
        Object.keys(orderData).forEach(key => {
            if (key === 'items' || key === 'payments') {
                postData[key] = JSON.stringify(orderData[key]);
            } else {
                postData[key] = orderData[key];
            }
        });
        
        // Send to server using jQuery if available, otherwise fetch
        if (typeof jQuery !== 'undefined' && jQuery.ajax) {
            jQuery.ajax({
                url: 'ajax/save-sale-order.php',
                type: 'POST',
                data: postData,
                dataType: 'json',
                success: function(response) {
                    if (saveBtn) {
                        saveBtn.disabled = false;
                        saveBtn.textContent = originalText;
                    }
                    
                    if (response.status === 'success') {
                        alert('Order saved successfully! Order No: ' + response.order_no);
                        if (response.order_id) {
                            window.location.href = 'sale-order.php?id=' + response.order_id;
                        }
                    } else {
                        alert('Error: ' + (response.message || 'Failed to save order'));
                    }
                },
                error: function(xhr, status, error) {
                    if (saveBtn) {
                        saveBtn.disabled = false;
                        saveBtn.textContent = originalText;
                    }
                    alert('Error saving order: ' + error);
                    console.error('Save order error:', xhr.responseText);
                }
            });
        } else {
            // Fallback using fetch
            const formData = new FormData();
            Object.keys(postData).forEach(key => {
                formData.append(key, postData[key]);
            });
            
            fetch('ajax/save-sale-order.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.textContent = originalText;
                }
                
                if (data.status === 'success') {
                    alert('Order saved successfully! Order No: ' + data.order_no);
                    if (data.order_id) {
                        window.location.href = 'sale-order.php?id=' + data.order_id;
                    }
                } else {
                    alert('Error: ' + (data.message || 'Failed to save order'));
                }
            })
            .catch(error => {
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.textContent = originalText;
                }
                alert('Error saving order: ' + error);
                console.error('Save order error:', error);
            });
        }
    }
    
    // Reset order form
    function resetOrder() {
        if (confirm('Are you sure you want to create a new order? All unsaved data will be lost.')) {
            window.location.href = 'sale-order.php';
        }
    }
    
    // Add event listeners to Save buttons
    const saveButtons = document.querySelectorAll('.btn-save-invoice, .btn-save-order');
    saveButtons.forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            // Prevent double submission
            if (btn.disabled) {
                return false;
            }
            saveOrder();
        });
    });
    
    // ================== LOAD SAVED ORDER FUNCTIONALITY ==================
    
    // Load order from dropdown selection
    function loadOrderFromDropdown(orderId) {
        if (!orderId || orderId === '') {
            return;
        }
        
        // Get the selected option to get the order number
        const selectDropdown = document.getElementById('selectSavedOrder');
        if (!selectDropdown) return;
        
        const selectedOption = selectDropdown.options[selectDropdown.selectedIndex];
        const orderNo = selectedOption.getAttribute('data-order-no') || '';
        
        // Update the order number display
        if (document.getElementById('currentOrderNo')) {
            document.getElementById('currentOrderNo').textContent = orderNo;
        }
        
        // Load the order
        loadOrder(orderId);
    }
    
    // Load order data
    function loadOrder(orderId) {
        if (!orderId) return;
        
        // Show loading
        const loadingMsg = document.createElement('div');
        loadingMsg.id = 'orderLoadingMsg';
        loadingMsg.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); z-index: 10000;';
        loadingMsg.innerHTML = '<p>Loading order...</p>';
        document.body.appendChild(loadingMsg);
        
        // Fetch order data
        if (typeof jQuery !== 'undefined' && jQuery.ajax) {
            jQuery.ajax({
                url: 'ajax/get-sale-order.php',
                type: 'GET',
                data: { order_id: orderId },
                dataType: 'json',
                success: function(response) {
                    const msg = document.getElementById('orderLoadingMsg');
                    if (msg) document.body.removeChild(msg);
                    if (response.status === 'success') {
                        populateOrderForm(response.order, response.items, response.payments);
                        // Update URL without reload
                        window.history.pushState({}, '', 'sale-order.php?id=' + orderId);
                    } else {
                        alert('Error loading order: ' + (response.message || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    const msg = document.getElementById('orderLoadingMsg');
                    if (msg) document.body.removeChild(msg);
                    alert('Error loading order: ' + error);
                    console.error('Load order error:', xhr.responseText);
                }
            });
        } else {
            fetch('ajax/get-sale-order.php?order_id=' + orderId)
                .then(response => response.json())
                .then(data => {
                    const msg = document.getElementById('orderLoadingMsg');
                    if (msg) document.body.removeChild(msg);
                    if (data.status === 'success') {
                        populateOrderForm(data.order, data.items, data.payments);
                        window.history.pushState({}, '', 'sale-order.php?id=' + orderId);
                    } else {
                        alert('Error loading order: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    const msg = document.getElementById('orderLoadingMsg');
                    if (msg) document.body.removeChild(msg);
                    alert('Error loading order: ' + error);
                    console.error('Load order error:', error);
                });
        }
    }
    
    // Add product row to Product Selection table with saved item data
    function addProductRowToSelectionTable(item, product) {
        const tbody = document.getElementById('productListBody');
        if (!tbody) {
            console.error('productListBody not found! Make sure Product Selection modal is open or table exists.');
            return;
        }
        
        console.log('Adding row to productListBody. Item:', item, 'Product:', product);
        
        // Remove the "no products" message if it exists
        const emptyRow = tbody.querySelector('tr:not(.product-row)');
        if (emptyRow) {
            emptyRow.remove();
        }
        
        // Create a new product row
        const row = document.createElement('tr');
        row.className = 'product-row';
        row.setAttribute('data-product-id', item.product_id || '');
        row.setAttribute('data-characteristic-id', item.product_characteristic_id || '');
        
        // Generate the row HTML with all columns (populated with saved values)
        row.innerHTML = `
            <td data-column="checkbox" style="text-align: center; position: sticky; left: 0; background: #fff; z-index: 1;">
                <input type="checkbox" class="product-checkbox" data-product-id="${item.product_id || ''}" data-characteristic-id="${item.product_characteristic_id || ''}">
            </td>
            <td data-column="id">${item.product_id || ''}</td>
            <td data-column="rfid"><input type="text" class="form-control form-control-sm" value="${escapeHtml(item.rfid_code || '')}" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="voucher-type"><input type="text" class="form-control form-control-sm" value="${escapeHtml(item.voucher_type_id || '')}" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="barcode"><input type="text" class="form-control form-control-sm" value="${escapeHtml(item.barcode_no || '')}" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="design-no"><input type="text" class="form-control form-control-sm" value="${escapeHtml(item.design_no || '')}" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="huid"><input type="text" class="form-control form-control-sm" value="${escapeHtml(item.huid_no || '')}" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="category"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="">Select</option></select></td>
            <td data-column="calculation"><select class="form-control form-control-sm" style="width: 120px; font-size: 0.7rem;"><option value="Weight X Rate" ${(item.calculation || 'Weight X Rate') === 'Weight X Rate' ? 'selected' : ''}>Weight X Rate</option><option value="Rate X Gross Wt" ${item.calculation === 'Rate X Gross Wt' ? 'selected' : ''}>Rate X Gross Wt</option><option value="Rate X Purity Wt" ${item.calculation === 'Rate X Purity Wt' ? 'selected' : ''}>Rate X Purity Wt</option><option value="Rate X Net Wt" ${item.calculation === 'Rate X Net Wt' ? 'selected' : ''}>Rate X Net Wt</option><option value="Rate X Final Wt" ${item.calculation === 'Rate X Final Wt' ? 'selected' : ''}>Rate X Final Wt</option><option value="Fix" ${item.calculation === 'Fix' ? 'selected' : ''}>Fix</option><option value="Stone Charge" ${item.calculation === 'Stone Charge' ? 'selected' : ''}>Stone Charge</option></select></td>
            <td data-column="product"><input type="text" class="form-control form-control-sm" value="${escapeHtml(product.name || '')}" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="location"><select class="form-control form-control-sm location-select" style="width: 100px; font-size: 0.7rem;"><option value="">Select</option></select></td>
            <td data-column="quantity"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.quantity || 1).toFixed(2)}" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="carat"><select class="form-control form-control-sm carat-select" style="width: 80px; font-size: 0.7rem;"><option value="">Select</option></select></td>
            <td data-column="pkt-wt"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.pkt_weight || 0).toFixed(3)}" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="pkt-less-wt"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.pkt_less_weight || 0).toFixed(3)}" step="0.001" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="requested-purity"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.requested_purity || 0).toFixed(2)}" step="0.01" style="width: 120px; font-size: 0.7rem;"></td>
            <td data-column="requested"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.requested || 0).toFixed(3)}" step="0.001" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="gross-wt"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.gross_weight || 0).toFixed(3)}" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="less-wt"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.less_weight || 0).toFixed(3)}" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="gold-loss1"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.gold_loss1 || 0).toFixed(3)}" step="0.001" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="gold-loss2"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.gold_loss2 || 0).toFixed(2)}" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="setting-charge"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.setting_charge || 0).toFixed(2)}" step="0.01" style="width: 110px; font-size: 0.7rem;"></td>
            <td data-column="net-wt"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.net_weight || 0).toFixed(3)}" step="0.001" readonly style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="purity"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.purity || 1).toFixed(2)}" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="purity-wt"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.purity_weight || 0).toFixed(3)}" step="0.001" readonly style="width: 90px; font-size: 0.7rem;"></td>
            <td data-column="wastage-per"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.wastage_per || 0).toFixed(2)}" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="wastage-wt"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.wastage_weight || 0).toFixed(3)}" step="0.001" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="final-wt"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.final_weight || 0).toFixed(3)}" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="alloy-wt"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.alloy_weight || 0).toFixed(3)}" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="rate"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.rate || 0).toFixed(2)}" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="metal-value"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.metal_value || 0).toFixed(2)}" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="metal-cost"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.metal_cost || 0).toFixed(2)}" step="0.01" readonly style="width: 100px; font-size: 0.7rem; color: #7b1fa2;"></td>
            <td data-column="amount"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.amount || 0).toFixed(2)}" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="discount-type"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="On Amount" ${(item.discount_type || 'On Amount') === 'On Amount' ? 'selected' : ''}>On Amount</option><option value="On Percentage" ${item.discount_type === 'On Percentage' ? 'selected' : ''}>On Percentage</option></select></td>
            <td data-column="discount-per"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.discount_per || 0).toFixed(2)}" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="discount-amount"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.discount_amount || 0).toFixed(2)}" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="discount"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.discount || 0).toFixed(2)}" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="discount-type2"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="On Amount" ${(item.discount_type2 || 'On Amount') === 'On Amount' ? 'selected' : ''}>On Amount</option><option value="On Percentage" ${item.discount_type2 === 'On Percentage' ? 'selected' : ''}>On Percentage</option></select></td>
            <td data-column="discount-per2"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.discount_per2 || 0).toFixed(2)}" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="discount-amount2"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.discount_amount2 || 0).toFixed(2)}" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="discounted-amt"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.discounted_amount || 0).toFixed(2)}" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
            <td data-column="discounted-per"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.discounted_per || 0).toFixed(2)}" step="0.01" style="width: 120px; font-size: 0.7rem;"></td>
            <td data-column="making-type"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="Fix" ${(item.making_type || 'Fix') === 'Fix' ? 'selected' : ''}>Fix</option><option value="Percentage" ${item.making_type === 'Percentage' ? 'selected' : ''}>Percentage</option></select></td>
            <td data-column="making-rate"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.making_rate || 0).toFixed(2)}" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="making-discount-amt"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.making_discount_amount || 0).toFixed(2)}" step="0.01" style="width: 130px; font-size: 0.7rem;"></td>
            <td data-column="making-amount"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.making_amount || 0).toFixed(2)}" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="making-actual-value"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.making_actual_value || 0).toFixed(2)}" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
            <td data-column="making-cost"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.making_cost || 0).toFixed(2)}" step="0.01" readonly style="width: 110px; font-size: 0.7rem;"></td>
            <td data-column="min-price"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.min_price || 0).toFixed(2)}" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="minimum"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.minimum || 0).toFixed(2)}" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="stone-charge-type"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="Fix" ${(item.stone_charge_type || 'Fix') === 'Fix' ? 'selected' : ''}>Fix</option><option value="Per Gram" ${item.stone_charge_type === 'Per Gram' ? 'selected' : ''}>Per Gram</option></select></td>
            <td data-column="stone-weight"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.stone_weight || 0).toFixed(3)}" step="0.001" style="width: 110px; font-size: 0.7rem;"></td>
            <td data-column="stone-rate"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.stone_rate || 0).toFixed(2)}" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="stone-amount"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.stone_amount || 0).toFixed(2)}" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
            <td data-column="stone-cost"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.stone_cost || 0).toFixed(2)}" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="diamond-amount"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.diamond_amount || 0).toFixed(2)}" step="0.01" style="width: 120px; font-size: 0.7rem;"></td>
            <td data-column="purchase-amount"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.purchase_amount || 0).toFixed(2)}" step="0.01" readonly style="width: 130px; font-size: 0.7rem;"></td>
                        <td data-column="sale-percent"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;" placeholder="%" title="Sale % on Purchase Amount"></td>            <td data-column="sale-amount"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.sale_amount || 0).toFixed(2)}" step="0.01" readonly style="width: 110px; font-size: 0.7rem;"></td>
            <td data-column="sale-amount-with"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.sale_amount_with || 0).toFixed(2)}" step="0.01" readonly style="width: 130px; font-size: 0.7rem;"></td>
            <td data-column="net-amt"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.net_amount || 0).toFixed(2)}" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="tax"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.tax_amount || 0).toFixed(2)}" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="other-charge-type"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="Fix" ${(item.other_charge_type || 'Fix') === 'Fix' ? 'selected' : ''}>Fix</option><option value="Percentage" ${item.other_charge_type === 'Percentage' ? 'selected' : ''}>Percentage</option></select></td>
            <td data-column="other-weight"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.other_weight || 0).toFixed(3)}" step="0.001" style="width: 110px; font-size: 0.7rem;"></td>
            <td data-column="other-rate"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.other_rate || 0).toFixed(2)}" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="other-info"><input type="text" class="form-control form-control-sm" value="${escapeHtml(item.other_info || '')}" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="other-amount"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.other_amount || 0).toFixed(2)}" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
            <td data-column="hallmark-amount"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.hallmark_amount || 0).toFixed(2)}" step="0.01" style="width: 130px; font-size: 0.7rem;"></td>
            <td data-column="hallmark-rate"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.hallmark_rate || 0).toFixed(2)}" step="0.01" style="width: 120px; font-size: 0.7rem;"></td>
            <td data-column="net-amt-tax"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.net_amount_tax || 0).toFixed(2)}" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
            <td data-column="reverse"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.reverse || 0).toFixed(2)}" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="actions" style="text-align: center;">
                <i class="feather icon-edit" style="cursor: pointer; font-size: 0.8rem; color: #c5a864; margin-right: 8px;" onclick="editProductRowInTable(this)" title="Edit Row"></i>
                <i class="feather icon-trash-2" style="cursor: pointer; font-size: 0.8rem; color: #ef4444;" onclick="deleteProductRowFromModal(this)" title="Delete Row"></i>
            </td>
        `;
        
        // Append row to tbody
        tbody.appendChild(row);
        console.log('Row appended to productListBody. Total rows now:', tbody.querySelectorAll('tr').length);
        
        // Populate dropdowns (location, carat, etc.)
        const caratSelect = row.querySelector('.carat-select');
        if (caratSelect && typeof populateSelect === 'function') {
            populateSelect(caratSelect, carats, 'id', 'name', 'Select Carat');
            if (item.carat_id) {
                caratSelect.value = item.carat_id;
            }
        }
        
        const locationSelect = row.querySelector('.location-select');
        if (locationSelect && typeof populateSelect === 'function') {
            populateSelect(locationSelect, locations, 'id', 'name', 'Select Location');
            if (item.location_id) {
                locationSelect.value = item.location_id;
            }
        }
        
        // Add event handlers (same as addEmptyProductRow)
        const checkbox = row.querySelector('.product-checkbox');
        if (checkbox) {
            checkbox.addEventListener('change', function() {
                updateRowSelection(row, this.checked);
            });
        }
        
        // Add row double-click handler to edit row
        row.addEventListener('dblclick', function(e) {
            // Don't edit if clicking on checkbox, action buttons, or any input/select/textarea elements
            if (e.target.type === 'checkbox' || 
                e.target.closest('[data-column="actions"]') ||
                e.target.tagName === 'INPUT' ||
                e.target.tagName === 'SELECT' ||
                e.target.tagName === 'TEXTAREA' ||
                e.target.closest('input') ||
                e.target.closest('select') ||
                e.target.closest('textarea')) {
                return;
            }
            editProductRowInTable(row);
        });
        
        // Add row click handler
        row.addEventListener('click', function(e) {
            if (e.target.closest('[data-column="product"]') || e.target.type === 'checkbox' || e.target.closest('[data-column="actions"]')) {
                if (e.target.closest('[data-column="product"]')) {
                    openProductSearchModal(row);
                }
                return;
            }
            checkbox.checked = !checkbox.checked;
            updateRowSelection(row, checkbox.checked);
        });
        row.style.cursor = 'pointer';
        
        // Add calculation listeners
        if (typeof addModalRowCalculationListeners === 'function') {
            addModalRowCalculationListeners(row);
        }
        
        // Add click handler to Product field
        const productInput = row.querySelector('[data-column="product"] input');
        if (productInput) {
            productInput.addEventListener('click', function(e) {
                e.stopPropagation();
                openProductSearchModal(row);
            });
            productInput.style.cursor = 'pointer';
            productInput.readOnly = true;
        }
        
        // Calculate initial values
        if (typeof calculateModalRowNetWeight === 'function') {
            calculateModalRowNetWeight(row);
        }
        
        function updateRowSelection(row, isSelected) {
            if (isSelected) {
                row.classList.add('selected');
                row.style.backgroundColor = '#fff3cd';
            } else {
                row.classList.remove('selected');
                row.style.backgroundColor = '';
            }
        }
    }
    
    // Populate form with order data
    function populateOrderForm(order, items, payments) {
        console.log('populateOrderForm called with:', { order, items, payments });
        
        // Update order number
        if (document.getElementById('currentOrderNo')) {
            document.getElementById('currentOrderNo').textContent = order.order_no;
        }
        
        // Update the dropdown selection
        const selectDropdown = document.getElementById('selectSavedOrder');
        if (selectDropdown) {
            const options = selectDropdown.options;
            for (let i = 0; i < options.length; i++) {
                if (options[i].value == order.id) {
                    selectDropdown.selectedIndex = i;
                    break;
                }
            }
        }
        
        // Populate billing form
        if (document.getElementById('customerName')) {
            document.getElementById('customerName').value = order.customer_name || '';
        }
        if (document.getElementById('customerId')) {
            document.getElementById('customerId').value = order.customer_id || '';
        }
        
        // Load customer balance after setting customer name
        if (typeof loadCustomerBalance === 'function') {
            setTimeout(function() {
                loadCustomerBalance();
            }, 200);
        }
        
        if (document.getElementById('againstOf')) {
            document.getElementById('againstOf').value = order.against_of || '';
        }
        if (document.getElementById('currency')) {
            document.getElementById('currency').value = order.currency || 'AED';
        }
        if (document.getElementById('refNo')) {
            document.getElementById('refNo').value = order.ref_no || '';
        }
        if (document.getElementById('salesPerson')) {
            document.getElementById('salesPerson').value = order.sales_person || '';
        }
        if (document.getElementById('orderDate')) {
            document.getElementById('orderDate').value = order.order_date || '';
        }
        if (document.getElementById('dueDate')) {
            document.getElementById('dueDate').value = order.due_date || '';
        }
        if (document.getElementById('layaways')) {
            document.getElementById('layaways').value = order.layaways_id || '';
        }
        if (document.getElementById('fixingType')) {
            document.getElementById('fixingType').value = order.fixing_type || 'Standard';
        }
        if (document.getElementById('groupName')) {
            document.getElementById('groupName').value = order.group_name || '';
        }
        if (document.getElementById('orderComment')) {
            document.getElementById('orderComment').value = order.comment || '';
        }
        
        // Clear existing products from Product List table
        const productTableBody = document.getElementById('productTableBody');
        if (productTableBody) {
            productTableBody.innerHTML = '';
        }
        
        // Clear existing products from Product Selection table (productListBody)
        const productListBody = document.getElementById('productListBody');
        if (productListBody) {
            // Remove empty message if exists
            const emptyRow = productListBody.querySelector('tr:not(.product-row)');
            if (emptyRow) {
                emptyRow.remove();
            }
            // Clear all existing rows
            productListBody.innerHTML = '';
        }
        
        // Debug: Log items
        console.log('Loading items into Product Selection table:', items);
        
        // Add products to Product Selection table (productListBody) - for editing
        if (items && items.length > 0) {
            console.log('Found ' + items.length + ' items to load');
            items.forEach(function(item, index) {
                console.log('Loading item ' + (index + 1) + ':', item);
                
                // Create product object from saved item
                const product = {
                    id: item.product_id || item.id,
                    name: item.product_name || item.name || '',
                    characteristic_id: item.product_characteristic_id || item.characteristic_id || '',
                    opening_weight: item.gross_weight || item.opening_weight || item.gross_wt || 0,
                    opening_purity: item.purity || item.opening_purity || 1,
                    final_weight: item.final_weight || item.opening_weight || item.final_wt || 0,
                    rate: item.rate || 0,
                    value: item.amount || item.value || 0,
                    article: item.design_no || item.article || ''
                };
                
                // Add product row to Product Selection table
                try {
                    if (typeof addProductRowToSelectionTable === 'function') {
                        addProductRowToSelectionTable(item, product);
                        console.log('Item ' + (index + 1) + ' added successfully to Product Selection table');
                    } else {
                        console.error('addProductRowToSelectionTable function not found');
                    }
                } catch (error) {
                    console.error('Error adding item ' + (index + 1) + ':', error);
                }
                
                // Also add to Product List table (for display)
                if (typeof addProductToTable === 'function') {
                addProductToTable(product);
                }
                
                // Update Product List table row with saved values
                const rows = productTableBody.querySelectorAll('tr:not(.no-drag)');
                const lastRow = rows[rows.length - 1];
                if (lastRow) {
                    // Update data attributes
                    lastRow.setAttribute('data-product-id', item.product_id || '');
                    lastRow.setAttribute('data-characteristic-id', item.product_characteristic_id || '');
                    lastRow.setAttribute('data-purity', parseFloat(item.purity || 0));
                    lastRow.setAttribute('data-rate', parseFloat(item.rate || 0));
                    
                    // Update editable fields
                    const quantityInput = lastRow.querySelector('[data-field="quantity"]');
                    if (quantityInput) quantityInput.value = parseFloat(item.quantity || 1).toFixed(2);
                    
                    const grossWtInput = lastRow.querySelector('[data-field="gross_wt"]');
                    if (grossWtInput) grossWtInput.value = parseFloat(item.gross_weight || 0).toFixed(3);
                    
                    const lessWtInput = lastRow.querySelector('[data-field="less_wt"]');
                    if (lessWtInput) lessWtInput.value = parseFloat(item.less_weight || 0).toFixed(3);
                    
                    const purityInput = lastRow.querySelector('[data-field="purity"]');
                    if (purityInput) purityInput.value = parseFloat(item.purity || 0).toFixed(2);
                    
                    const finalWtInput = lastRow.querySelector('[data-field="final_wt"]');
                    if (finalWtInput) finalWtInput.value = parseFloat(item.final_weight || 0).toFixed(3);
                    
                    const makingInput = lastRow.querySelector('[data-field="making"]');
                    if (makingInput) makingInput.value = parseFloat(item.making_amount || 0).toFixed(2);
                    
                    const designNoInput = lastRow.querySelector('[data-field="design_no"]');
                    if (designNoInput) designNoInput.value = item.design_no || '';
                    
                    const taxInput = lastRow.querySelector('[data-field="tax"]');
                    if (taxInput) taxInput.value = parseFloat(item.tax_amount || 0).toFixed(2);
                    
                    const stoneChargesInput = lastRow.querySelector('[data-field="stone_charges"]');
                    if (stoneChargesInput) stoneChargesInput.value = parseFloat(item.stone_amount || 0).toFixed(2);
                    
                    const otherChargesInput = lastRow.querySelector('[data-field="other_charges"]');
                    if (otherChargesInput) otherChargesInput.value = parseFloat(item.other_amount || 0).toFixed(2);
                    
                    const diamondValueInput = lastRow.querySelector('[data-field="diamond_value"]');
                    if (diamondValueInput) diamondValueInput.value = parseFloat(item.diamond_amount || 0).toFixed(2);
                    
                    // Update calculated display columns with saved values (don't recalculate, use saved values)
                    const netAmtCell = lastRow.querySelector('[data-column="net-amt"]');
                    if (netAmtCell) netAmtCell.textContent = parseFloat(item.net_amount || 0).toFixed(2);
                    
                    const netAmtTaxCell = lastRow.querySelector('[data-column="net-amt-tax"]');
                    if (netAmtTaxCell) {
                        // Handle both textContent and input cases
                        if (netAmtTaxCell.querySelector('input')) {
                            netAmtTaxCell.querySelector('input').value = parseFloat(item.net_amt_with_tax || 0).toFixed(2);
                        } else {
                            netAmtTaxCell.textContent = parseFloat(item.net_amt_with_tax || 0).toFixed(2);
                        }
                    }
                    
                    const amountCell = lastRow.querySelector('[data-column="amount"]');
                    if (amountCell) amountCell.textContent = parseFloat(item.amount || 0).toFixed(2);
                    
                    // Recalculate row to update other dependent fields (but preserve saved net amounts)
                    calculateRowAmounts(lastRow);
                    
                    // Restore saved net amounts after recalculation (in case calculateRowAmounts changed them)
                    if (netAmtCell) netAmtCell.textContent = parseFloat(item.net_amount || 0).toFixed(2);
                    if (netAmtTaxCell) {
                        if (netAmtTaxCell.querySelector('input')) {
                            netAmtTaxCell.querySelector('input').value = parseFloat(item.net_amt_with_tax || 0).toFixed(2);
                        } else {
                            netAmtTaxCell.textContent = parseFloat(item.net_amt_with_tax || 0).toFixed(2);
                        }
                    }
                    if (amountCell) amountCell.textContent = parseFloat(item.amount || 0).toFixed(2);
                }
            });
        } else {
            // Show empty message
            if (productTableBody) {
                productTableBody.innerHTML = '<tr class="no-drag"><td colspan="32" class="text-center text-muted py-4" id="emptyRowCell">No Rows To Show</td></tr>';
            }
            if (productListBody) {
                productListBody.innerHTML = '<tr><td colspan="70" class="text-center text-muted py-4">Click "Add Product" button to add products for billing...</td></tr>';
            }
        }
        
        // Clear existing payments
        const paymentTableBody = document.getElementById('paymentTableBody');
        if (paymentTableBody) {
            paymentTableBody.innerHTML = '';
        }
        
        // Add payments to table
        if (payments && payments.length > 0) {
            payments.forEach(function(payment) {
                // Map payment type from database to modal type
                let paymentType = payment.payment_type.toLowerCase();
                if (paymentType.includes('cash')) paymentType = 'cash';
                else if (paymentType.includes('bank')) paymentType = 'bank';
                else if (paymentType.includes('cheque')) paymentType = 'cheque';
                else if (paymentType.includes('upi') || paymentType.includes('mobile')) paymentType = 'upi';
                else if (paymentType.includes('card')) paymentType = 'card';
                else if (paymentType.includes('metal') || paymentType.includes('exch')) paymentType = 'metal-exchange';
                else if (paymentType.includes('scrap')) paymentType = 'scrap';
                else paymentType = 'other';
                
                // Calculate previous balance amount and current order amount
                // payment.amount from database is the TOTAL amount (current order + previous balance)
                const totalAmount = parseFloat(payment.amount || 0);
                const previousBalanceAmount = parseFloat(payment.previous_balance_amount || 0);
                // Current order amount = total - previous balance
                const currentOrderAmount = totalAmount - previousBalanceAmount;
                
                const paymentData = {
                    id: 'payment-' + payment.id,
                    type: paymentType,
                    deposit_into: payment.deposit_into || '',
                    transaction_no: payment.transaction_no || '',
                    cheque_date: payment.cheque_date || '',
                    purity_carat: payment.purity_carat || '',
                    amount: currentOrderAmount, // Current order amount only (for display in addPaymentToTable)
                    previous_balance_amount: previousBalanceAmount,
                    current_order_amount: currentOrderAmount,
                    diamond_category: payment.diamond_category || '',
                    quantity: parseFloat(payment.quantity || 0)
                };
                
                addPaymentToTable(paymentData);
            });
        } else {
            // Show empty message
            if (paymentTableBody) {
                paymentTableBody.innerHTML = '<tr class="no-payment-row"><td colspan="9" class="text-center text-muted py-3">No payment entries</td></tr>';
            }
        }
        
        // Set original previous balance from order and store it FIRST (before loading payments)
        const previousBalanceEl = document.getElementById('previousBalanceAmount');
        if (previousBalanceEl) {
            const originalPrevBalance = parseFloat(order.previous_balance || 0);
            previousBalanceEl.setAttribute('data-original-balance', originalPrevBalance.toFixed(2));
            // Set initial display value to original (will be updated by updateSummaryPanel after payments load)
            previousBalanceEl.textContent = originalPrevBalance.toFixed(2);
        }
        
        // Update round off and metal amount from saved values
        if (document.getElementById('summaryMetalAmt')) {
            document.getElementById('summaryMetalAmt').textContent = parseFloat(order.metal_amt || 0).toFixed(2);
        }
        if (document.getElementById('summaryRoundOff')) {
            document.getElementById('summaryRoundOff').value = parseFloat(order.round_off || 0).toFixed(2);
        }
        
        // Recalculate summary from actual table values (not from saved order data)
        // This ensures totals match what's actually displayed in the table
        updateSummaryPanel();
        updatePaymentTotals();
        
        // Hide dropdown
        const orderDropdownWrapper = document.getElementById('orderDropdownWrapper');
        if (orderDropdownWrapper) {
            orderDropdownWrapper.style.display = 'none';
        }
        const orderSearchInput = document.getElementById('orderSearchInput');
        if (orderSearchInput) {
            orderSearchInput.value = '';
        }
    }
    
    // Load order on page load if edit_order_id is set
    <?php if ($edit_order_id > 0 && $edit_order): ?>
    $(document).ready(function() {
        // Populate form with existing order data
        const order = <?php echo json_encode($edit_order); ?>;
        const items = <?php echo json_encode($edit_items); ?>;
        const payments = <?php echo json_encode($edit_payments); ?>;
        
        console.log('Edit mode - Loading order:', order);
        console.log('Edit mode - Items to load:', items);
        console.log('Edit mode - Items count:', items ? items.length : 0);
        
        // Wait a bit to ensure DOM is fully ready
        setTimeout(function() {
        populateOrderForm(order, items, payments);
        }, 500);
        
        // Also load items when Product Selection modal is opened (in case modal wasn't ready when page loaded)
        $('#productSelectionModal').on('shown.bs.modal', function() {
            console.log('Product Selection modal opened, checking if items need to be loaded');
            const productListBody = document.getElementById('productListBody');
            if (productListBody) {
                const existingRows = productListBody.querySelectorAll('tr.product-row');
                console.log('Existing rows in modal:', existingRows.length);
                if (existingRows.length === 0 && items && items.length > 0) {
                    console.log('No items in table, reloading from saved items');
                    // Clear empty message
                    productListBody.innerHTML = '';
                    // Reload items into Product Selection table
                    items.forEach(function(item, index) {
                        const product = {
                            id: item.product_id || item.id,
                            name: item.product_name || item.name || '',
                            characteristic_id: item.product_characteristic_id || item.characteristic_id || '',
                            opening_weight: item.gross_weight || item.opening_weight || item.gross_wt || 0,
                            opening_purity: item.purity || item.opening_purity || 1,
                            final_weight: item.final_weight || item.opening_weight || item.final_wt || 0,
                            rate: item.rate || 0,
                            value: item.amount || item.value || 0,
                            article: item.design_no || item.article || ''
                        };
                        if (typeof addProductRowToSelectionTable === 'function') {
                            addProductRowToSelectionTable(item, product);
                        }
                    });
                }
            }
        });
    });
    <?php endif; ?>
    
    // ================== PAYMENT FUNCTIONALITY ==================
    
    let paymentRowIndex = 0;
    let payments = [];
    
    // Payment icon click handlers
    document.querySelectorAll('.payment-icon').forEach(function(icon) {
        icon.addEventListener('click', function() {
            const paymentType = this.classList.contains('payment-cash') ? 'cash' :
                               this.classList.contains('payment-bank') ? 'bank' :
                               this.classList.contains('payment-cheque') ? 'cheque' :
                               this.classList.contains('payment-mobile') ? 'upi' :
                               this.classList.contains('payment-card') ? 'card' :
                               this.classList.contains('payment-exchange') ? 'metal-exchange' :
                               this.classList.contains('payment-jewelry') ? 'scrap' :
                               'other';
            openPaymentModal(paymentType);
        });
    });
    
    // Open payment modal based on type
    function openPaymentModal(type) {
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
            // Calculate remaining amount
            const grandTotalEl = document.getElementById('summaryGrandTotal');
            const grandTotal = grandTotalEl ? parseFloat(grandTotalEl.textContent.replace(/,/g, '')) || 0 : 0;
            
            // Calculate already paid amount
            const paymentRows = document.querySelectorAll('#paymentTableBody tr:not(.no-payment-row)');
            let paidAmt = 0;
            paymentRows.forEach(function(row) {
                const amt = parseFloat(row.querySelector('[data-payment-amount]')?.textContent.replace(/,/g, '') || 0);
                paidAmt += amt;
            });
            
            const remainingAmt = Math.max(0, grandTotal - paidAmt); // Ensure it's not negative
            const amountToShow = remainingAmt > 0 ? remainingAmt.toFixed(2) : '0.00';
            
            // Set amount in modal based on type
            if (type === 'cash') {
                const cashAmountEl = document.getElementById('cashAmount');
                if (cashAmountEl) cashAmountEl.value = amountToShow;
            } else if (type === 'bank') {
                const bankAmountEl = document.getElementById('bankAmount');
                if (bankAmountEl) bankAmountEl.value = amountToShow;
            } else if (type === 'cheque') {
                const chequeAmountEl = document.getElementById('chequeAmount');
                if (chequeAmountEl) chequeAmountEl.value = amountToShow;
            } else if (type === 'upi') {
                const upiAmountEl = document.getElementById('upiAmount');
                if (upiAmountEl) upiAmountEl.value = amountToShow;
            } else if (type === 'card') {
                const cardAmountEl = document.getElementById('cardAmount');
                if (cardAmountEl) cardAmountEl.value = amountToShow;
            } else if (type === 'metal-exchange') {
                const metalExchangeAmountEl = document.getElementById('metalExchangeAmount');
                if (metalExchangeAmountEl) metalExchangeAmountEl.value = amountToShow;
            } else if (type === 'scrap') {
                const scrapAmountEl = document.getElementById('scrapAmount');
                if (scrapAmountEl) scrapAmountEl.value = amountToShow;
            }
            
            $(modalId).modal('show');
        }
    }
    
    // Save payment
    function savePayment(type) {
        let paymentData = {
            type: type,
            deposit_into: '',
            transaction_no: '',
            cheque_date: '',
            card_no: '',
            amount: 0,
            previous_balance_amount: 0,
            purity_carat: '',
            quantity: 0,
            diamond_category: '',
            q_more: ''
        };
        
        if (type === 'cash') {
            paymentData.deposit_into = document.getElementById('cashDepositInto').value;
            paymentData.amount = parseFloat(document.getElementById('cashAmount').value) || 0;
            paymentData.previous_balance_amount = parseFloat(document.getElementById('cashPreviousBalanceAmount').value) || 0;
        } else if (type === 'bank') {
            paymentData.deposit_into = document.getElementById('bankDepositInto').value;
            paymentData.transaction_no = document.getElementById('bankTransNo').value;
            paymentData.amount = parseFloat(document.getElementById('bankAmount').value) || 0;
            paymentData.previous_balance_amount = parseFloat(document.getElementById('bankPreviousBalanceAmount').value) || 0;
        } else if (type === 'cheque') {
            paymentData.deposit_into = document.getElementById('chequeDepositInto').value;
            paymentData.transaction_no = document.getElementById('chequeTransNo').value;
            paymentData.cheque_date = document.getElementById('chequeDate').value;
            paymentData.amount = parseFloat(document.getElementById('chequeAmount').value) || 0;
            paymentData.previous_balance_amount = parseFloat(document.getElementById('chequePreviousBalanceAmount').value) || 0;
        } else if (type === 'upi') {
            paymentData.deposit_into = document.getElementById('upiDepositInto').value;
            paymentData.transaction_no = document.getElementById('upiTransNo').value;
            paymentData.amount = parseFloat(document.getElementById('upiAmount').value) || 0;
            paymentData.previous_balance_amount = parseFloat(document.getElementById('upiPreviousBalanceAmount').value) || 0;
        } else if (type === 'card') {
            paymentData.deposit_into = document.getElementById('cardDepositInto').value;
            paymentData.transaction_no = document.getElementById('cardTransNo').value;
            paymentData.card_no = document.getElementById('cardNumber').value;
            paymentData.amount = parseFloat(document.getElementById('cardAmount').value) || 0;
            paymentData.previous_balance_amount = parseFloat(document.getElementById('cardPreviousBalanceAmount').value) || 0;
        } else if (type === 'metal-exchange') {
            paymentData.deposit_into = 'Metal Exchange';
            paymentData.purity_carat = document.getElementById('metalExchangePurity').value;
            paymentData.quantity = parseFloat(document.getElementById('metalExchangeQty').value) || 0;
            paymentData.amount = parseFloat(document.getElementById('metalExchangeAmount').value) || 0;
            paymentData.previous_balance_amount = parseFloat(document.getElementById('metalExchangePreviousBalanceAmount').value) || 0;
        } else if (type === 'scrap') {
            paymentData.deposit_into = 'Scrap';
            paymentData.purity_carat = document.getElementById('scrapPurity').value;
            paymentData.quantity = parseFloat(document.getElementById('scrapQty').value) || 0;
            paymentData.amount = parseFloat(document.getElementById('scrapAmount').value) || 0;
            paymentData.previous_balance_amount = parseFloat(document.getElementById('scrapPreviousBalanceAmount').value) || 0;
        }
        
        // Calculate total payment (current order + previous balance)
        const totalPaymentAmount = paymentData.amount + paymentData.previous_balance_amount;
        
        if (totalPaymentAmount <= 0) {
            alert('Please enter a valid amount');
            return;
        }
        
        // Validate: Check if payment amount exceeds remaining balance (only for current order amount, not previous balance)
        const grandTotalEl = document.getElementById('summaryGrandTotal');
        const grandTotal = grandTotalEl ? parseFloat(grandTotalEl.textContent.replace(/,/g, '')) || 0 : 0;
        
        // Calculate already paid amount for current order only
        const paymentRows = document.querySelectorAll('#paymentTableBody tr:not(.no-payment-row)');
        let paidAmt = 0;
        let paidPreviousBalanceAmt = 0;
        paymentRows.forEach(function(row) {
            const amt = parseFloat(row.querySelector('[data-payment-amount]')?.textContent.replace(/,/g, '') || 0);
            const prevBalAmt = parseFloat(row.getAttribute('data-previous-balance-amount') || 0);
            paidAmt += (amt - prevBalAmt); // Only count current order payments, not previous balance
            paidPreviousBalanceAmt += prevBalAmt;
        });
        
        const remainingAmt = grandTotal - paidAmt;
        
        // Validate current order payment amount (previous balance can be any amount)
        if (paymentData.amount > remainingAmt) {
            alert('Payment amount for current order (' + paymentData.amount.toFixed(2) + ') cannot exceed remaining balance (' + remainingAmt.toFixed(2) + ')');
            return;
        }
        
        // Get original previous balance and calculate remaining
        const originalPreviousBalance = parseFloat(document.getElementById('previousBalanceAmount')?.getAttribute('data-original-balance') || document.getElementById('previousBalanceAmount')?.textContent || 0);
        const remainingPreviousBalance = originalPreviousBalance - paidPreviousBalanceAmt;
        
        // Validate previous balance payment doesn't exceed remaining previous balance
        if (paymentData.previous_balance_amount > remainingPreviousBalance) {
            alert('Previous balance payment amount (' + paymentData.previous_balance_amount.toFixed(2) + ') cannot exceed remaining previous balance (' + remainingPreviousBalance.toFixed(2) + ')');
            return;
        }
        
        // Add payment to array
        paymentRowIndex++;
        paymentData.id = 'payment-' + paymentRowIndex;
        payments.push(paymentData);
        
        // Add to table
        addPaymentToTable(paymentData);
        
        // Close modal
        $('.modal').modal('hide');
        
        // Clear modal fields
        clearPaymentModal(type);
        
        // Update payment totals
        updatePaymentTotals();
        updateSummaryPanel();
    }
    
    // Add payment to table
    function addPaymentToTable(payment) {
        const tbody = document.getElementById('paymentTableBody');
        const noPaymentRow = tbody.querySelector('.no-payment-row');
        if (noPaymentRow) {
            noPaymentRow.remove();
        }
        
        const row = document.createElement('tr');
        row.id = payment.id;
        row.setAttribute('data-payment-id', payment.id);
        
        const paymentTypeLabel = payment.type === 'cash' ? 'Cash' :
                                payment.type === 'bank' ? 'Bank' :
                                payment.type === 'cheque' ? 'Cheque' :
                                payment.type === 'upi' ? 'UPI' :
                                payment.type === 'card' ? 'Card' :
                                payment.type === 'metal-exchange' ? 'M. Exch.' :
                                payment.type === 'scrap' ? 'Scrap' : 'Other';
        
        // Calculate total payment amount (current order + previous balance)
        const previousBalanceAmount = parseFloat(payment.previous_balance_amount || 0);
        const totalPaymentAmount = parseFloat(payment.amount || 0) + previousBalanceAmount;
        
        // Store previous balance amount as data attribute
        row.setAttribute('data-previous-balance-amount', previousBalanceAmount.toFixed(2));
        row.setAttribute('data-current-order-amount', parseFloat(payment.amount || 0).toFixed(2));
        
        row.innerHTML = `
            <td>${paymentTypeLabel}</td>
            <td>${payment.deposit_into || ''}</td>
            <td>${payment.transaction_no || ''}</td>
            <td>${payment.cheque_date || ''}</td>
            <td>${payment.purity_carat || ''}</td>
            <td data-payment-amount style="text-align: right; font-weight: 600;" title="Current: ${parseFloat(payment.amount || 0).toFixed(2)}, Previous Balance: ${previousBalanceAmount.toFixed(2)}">${totalPaymentAmount.toFixed(2)}</td>
            <td>${payment.diamond_category || ''}</td>
            <td style="text-align: right;">${payment.quantity || '0.00'}</td>
            <td>
                <div class="action-btns">
                    <button type="button" class="btn-edit" onclick="editPayment('${payment.id}')" title="Edit">
                        <i class="feather icon-edit-2"></i>
                    </button>
                    <button type="button" class="btn-delete" onclick="deletePayment('${payment.id}')" title="Delete">
                        <i class="feather icon-trash-2"></i>
                    </button>
                </div>
            </td>
        `;
        
        tbody.appendChild(row);
        
        // Show footer
        const footer = document.getElementById('paymentTableFooter');
        if (footer) footer.style.display = '';
    }
    
    // Delete payment
    function deletePayment(paymentId) {
        if (confirm('Are you sure you want to delete this payment?')) {
            const row = document.getElementById(paymentId);
            if (row) row.remove();
            
            payments = payments.filter(p => p.id !== paymentId);
            
            // Check if table is empty
            const tbody = document.getElementById('paymentTableBody');
            const rows = tbody.querySelectorAll('tr:not(.no-payment-row)');
            if (rows.length === 0) {
                tbody.innerHTML = '<tr class="no-payment-row"><td colspan="9" class="text-center text-muted py-3">No payment entries</td></tr>';
                const footer = document.getElementById('paymentTableFooter');
                if (footer) footer.style.display = 'none';
            }
            
            updatePaymentTotals();
            updateSummaryPanel();
        }
    }
    
    // Edit payment (placeholder)
    function editPayment(paymentId) {
        const payment = payments.find(p => p.id === paymentId);
        if (payment) {
            openPaymentModal(payment.type);
            // TODO: Populate modal with payment data
        }
    }
    
    // Update payment totals
    function updatePaymentTotals() {
        const rows = document.querySelectorAll('#paymentTableBody tr:not(.no-payment-row)');
        let totalAmount = 0;
        let totalQuantity = 0;
        
        rows.forEach(function(row) {
            const amt = parseFloat(row.querySelector('[data-payment-amount]')?.textContent || 0);
            const qty = parseFloat(row.querySelector('td:nth-child(8)')?.textContent || 0);
            totalAmount += amt;
            totalQuantity += qty;
        });
        
        document.getElementById('paymentTotalAmount').textContent = totalAmount.toFixed(2);
        document.getElementById('paymentTotalQuantity').textContent = totalQuantity.toFixed(2);
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
            document.getElementById('metalExchangePurity').value = '1';
            document.getElementById('metalExchangeRate').value = '0';
            document.getElementById('metalExchangeItemCode').value = '';
            document.getElementById('metalExchangeGrossWt').value = '0';
            document.getElementById('metalExchangePurityWt').value = '0';
            document.getElementById('metalExchangeAmount').value = '0.00';
        } else if (type === 'scrap') {
            document.getElementById('scrapProduct').value = '';
            document.getElementById('scrapQty').value = '1';
            document.getElementById('scrapLessWt').value = '0';
            document.getElementById('scrapPurity').value = '1';
            document.getElementById('scrapRate').value = '0';
            document.getElementById('scrapItemCode').value = '';
            document.getElementById('scrapGrossWt').value = '0';
            document.getElementById('scrapNetWt').value = '0';
            document.getElementById('scrapPurityWt').value = '0';
            document.getElementById('scrapAmount').value = '0.00';
        }
    }
    
    // Edit product row in Product Selection table - Show all row data in modal
    function editProductRowInTable(rowElement) {
        const row = rowElement.closest ? rowElement.closest('tr') : rowElement;
        if (!row || !row.classList.contains('product-row')) {
            return;
        }
        
        // Extract all data from the row
        const rowData = {};
        const columns = ['id', 'rfid', 'voucher-type', 'barcode', 'design-no', 'huid', 'category', 
                        'calculation', 'product', 'location', 'quantity', 'carat', 'pkt-wt', 'pkt-less-wt',
                        'requested-purity', 'requested', 'gross-wt', 'less-wt', 'gold-loss1', 'gold-loss2',
                        'setting-charge', 'net-wt', 'purity', 'purity-wt', 'wastage-per', 'wastage-wt',
                        'final-wt', 'alloy-wt', 'rate', 'metal-value', 'metal-cost', 'amount',
                        'discount-type', 'discount-per', 'discount-amount', 'discount', 'discount-type2',
                        'discount-per2', 'discount-amount2', 'discounted-amt', 'discounted-per',
                        'making-type', 'making-rate', 'making-discount-amt', 'making-amount',
                        'making-actual-value', 'making-cost', 'min-price', 'minimum',
                        'stone-charge-type', 'stone-weight', 'stone-rate', 'stone-amount', 'stone-cost',
                        'diamond-amount', 'purchase-amount', 'sale-percent', 'sale-amount', 'sale-amount-with',
                        'net-amt', 'tax', 'other-charge-type', 'other-weight', 'other-rate',
                        'other-info', 'other-amount', 'hallmark-amount', 'hallmark-rate',
                        'net-amt-tax', 'reverse'];
        
        columns.forEach(column => {
            const cell = row.querySelector(`[data-column="${column}"]`);
            if (cell) {
                const input = cell.querySelector('input');
                const select = cell.querySelector('select');
                if (input) {
                    rowData[column] = input.value;
                } else if (select) {
                    rowData[column] = select.value;
                } else {
                    rowData[column] = cell.textContent.trim();
                }
            }
        });
        
        // Store row reference
        window.currentEditingRow = row;
        
        // Create and show edit modal
        showEditRowModal(rowData);
    }
    
    // Show edit modal with row data
    function showEditRowModal(rowData) {
        // Create modal HTML
        const modalHtml = `
            <div id="editRowModal" style="
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: center;
            ">
                <div style="
                    background: white;
                    border-radius: 8px;
                    padding: 20px;
                    max-width: 90%;
                    width: 1200px;
                    max-height: 90vh;
                    overflow: hidden;
                    display: flex;
                    flex-direction: column;
                ">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-shrink: 0;">
                        <h5 style="margin: 0; color: #1e293b;">Edit Product Row</h5>
                        <button id="closeEditRowModal" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">×</button>
                    </div>
                    <div style="flex: 1; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 4px; padding: 15px;">
                        <div id="editRowForm" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
                            <!-- Form fields will be generated here -->
                        </div>
                    </div>
                    <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; flex-shrink: 0; padding-top: 15px; border-top: 1px solid #e2e8f0;">
                        <button type="button" class="btn btn-secondary" onclick="closeEditRowModal()">Cancel</button>
                        <button type="button" class="btn btn-primary" onclick="saveEditedRow()" style="background: #11294b; border: none;">Save Changes</button>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        const existingModal = document.getElementById('editRowModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Add modal to body
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Generate form fields
        const formDiv = document.getElementById('editRowForm');
        const fieldLabels = {
            'id': 'ID',
            'rfid': 'RFID Code',
            'voucher-type': 'Voucher Type',
            'barcode': 'Barcode No.',
            'design-no': 'Design No',
            'huid': 'HUID No.',
            'category': 'Category',
            'calculation': 'Calculation',
            'product': 'Product',
            'location': 'Location',
            'quantity': 'Quantity',
            'carat': 'Carat',
            'pkt-wt': 'Pkt. Wt.',
            'pkt-less-wt': 'Pkt. Less Wt.',
            'requested-purity': 'Requested Purity',
            'requested': 'Requested',
            'gross-wt': 'Gross Wt.',
            'less-wt': 'Less Wt.',
            'gold-loss1': 'Gold Loss 1',
            'gold-loss2': 'Gold Loss 2',
            'setting-charge': 'Setting Charge',
            'net-wt': 'Net Wt.',
            'purity': 'Purity',
            'purity-wt': 'Purity Wt.',
            'wastage-per': 'Wastage %',
            'wastage-wt': 'Wastage Wt.',
            'final-wt': 'Final Wt.',
            'alloy-wt': 'Alloy Wt.',
            'rate': 'Rate',
            'metal-value': 'Metal Value',
            'metal-cost': 'Metal Cost',
            'amount': 'Amount',
            'discount-type': 'Discount Type',
            'discount-per': 'Discount %',
            'discount-amount': 'Discount Amount',
            'discount': 'Discount',
            'discount-type2': 'Discount Type 2',
            'discount-per2': 'Discount % 2',
            'discount-amount2': 'Discount Amount 2',
            'discounted-amt': 'Discounted Amt.',
            'discounted-per': 'Discounted %',
            'making-type': 'Making Type',
            'making-rate': 'Making Rate',
            'making-discount-amt': 'Making Discount Amt.',
            'making-amount': 'Making Amount',
            'making-actual-value': 'Making Actual Value',
            'making-cost': 'Making Cost',
            'min-price': 'Min Price',
            'minimum': 'Minimum',
            'stone-charge-type': 'Stone Charge Type',
            'stone-weight': 'Stone Weight',
            'stone-rate': 'Stone Rate',
            'stone-amount': 'Stone Amount',
            'stone-cost': 'Stone Cost',
            'diamond-amount': 'Diamond Amount',
            'purchase-amount': 'Purchase Amount',
            'sale-percent': 'Sale Percentages',
            'sale-amount': 'Sale Amount',
            'sale-amount-with': 'Sale Amount With',
            'net-amt': 'Net Amt',
            'tax': 'Tax',
            'other-charge-type': 'Other Charge Type',
            'other-weight': 'Other Weight',
            'other-rate': 'Other Rate',
            'other-info': 'Other Info',
            'other-amount': 'Other Amount',
            'hallmark-amount': 'Hallmark Amount',
            'hallmark-rate': 'Hallmark Rate',
            'net-amt-tax': 'Net Amt + Tax',
            'reverse': 'Reverse'
        };
        
        Object.keys(rowData).forEach(key => {
            if (fieldLabels[key]) {
                const fieldHtml = `
                    <div class="form-group">
                        <label style="font-size: 0.85rem; font-weight: 500; color: #ffffff; margin-bottom: 5px;">${fieldLabels[key]}</label>
                        <input type="text" class="form-control form-control-sm" id="edit_${key}" value="${escapeHtml(rowData[key] || '')}" style="font-size: 0.85rem;">
                    </div>
                `;
                formDiv.insertAdjacentHTML('beforeend', fieldHtml);
            }
        });
        
        // Close modal handlers
        document.getElementById('closeEditRowModal').addEventListener('click', closeEditRowModal);
        document.getElementById('editRowModal').addEventListener('click', function(e) {
            if (e.target.id === 'editRowModal') {
                closeEditRowModal();
            }
        });
    }
    
    // Save edited row data
    function saveEditedRow() {
        if (!window.currentEditingRow) {
            alert('No row selected for editing');
            return;
        }
        
        const row = window.currentEditingRow;
        const formDiv = document.getElementById('editRowForm');
        const inputs = formDiv.querySelectorAll('input');
        
        // Update row with new values
        inputs.forEach(input => {
            const fieldName = input.id.replace('edit_', '');
            const cell = row.querySelector(`[data-column="${fieldName}"]`);
            if (cell) {
                const cellInput = cell.querySelector('input');
                const cellSelect = cell.querySelector('select');
                if (cellInput) {
                    cellInput.value = input.value;
                    // Trigger change event to recalculate
                    cellInput.dispatchEvent(new Event('input', { bubbles: true }));
                } else if (cellSelect) {
                    cellSelect.value = input.value;
                    cellSelect.dispatchEvent(new Event('change', { bubbles: true }));
                } else {
                    cell.textContent = input.value;
                }
            }
        });
        
        // Recalculate row amounts
        if (typeof calculateModalRowNetWeight === 'function') {
            calculateModalRowNetWeight(row);
        }
        
        // Close modal
        closeEditRowModal();
        
        alert('Row updated successfully!');
    }
    
    // Close edit row modal
    function closeEditRowModal() {
        const modal = document.getElementById('editRowModal');
        if (modal) {
            modal.remove();
        }
        window.currentEditingRow = null;
    }
    
    // Save Stock Journal
    function saveStockJournal() {
        const tbody = document.getElementById('productTableBody');
        if (!tbody) {
            alert('Product table not found');
            return;
        }
        
        const productRows = tbody.querySelectorAll('tr.product-row, tr[id^="product-row-"]');
        if (productRows.length === 0) {
            alert('Please add at least one product before saving');
            return;
        }
        
        const products = [];
        productRows.forEach(row => {
            // Skip if it's the empty row message
            if (row.classList.contains('no-drag')) {
                return;
            }
            
            const getValue = function(column, isNumber = true) {
                const cell = row.querySelector(`[data-column="${column}"]`);
                if (!cell) return isNumber ? 0 : '';
                const input = cell.querySelector('input');
                const select = cell.querySelector('select');
                if (input) {
                    return isNumber ? (parseFloat(input.value) || 0) : input.value;
                } else if (select) {
                    return isNumber ? (parseFloat(select.value) || 0) : select.value;
                } else {
                    const text = cell.textContent.trim();
                    return isNumber ? (parseFloat(text) || 0) : text;
                }
            };
            
            // Get barcode - it's displayed as text, not input
            const barcodeCell = row.querySelector('[data-column="barcode"]');
            const barcode = barcodeCell ? barcodeCell.textContent.trim() : '';
            
            // Item / short code (saved to tbl_stock_journal.code)
            const codeCell = row.querySelector('[data-column="code"]') || row.querySelector('[data-column="item-code"]') || row.querySelector('[data-column="short-code"]');
            const code = codeCell ? (codeCell.querySelector('input') ? codeCell.querySelector('input').value : codeCell.textContent.trim()) : '';
            
            const product = {
                product_id: row.getAttribute('data-product-id') || '',
                characteristic_id: row.getAttribute('data-characteristic-id') || '',
                barcode: barcode,
                code: code,
                product_name: getValue('description', false),
                quantity: getValue('quantity'),
                gross_weight: getValue('gross-wt'),
                less_weight: getValue('less-wt'),
                purity: getValue('purity'),
                final_weight: getValue('final-wt'),
                net_weight: getValue('net-wt'),
                pure_weight: getValue('pure-wt'),
                making_amount: getValue('making-amount'),
                stone_charges: getValue('stone-charges'),
                other_charges: getValue('other-charges'),
                diamond_value: getValue('diamond-value'),
                gemstone_value: getValue('gemstone-value'),
                rate: getValue('rate'),
                metal_value: getValue('metal-value'),
                discount: getValue('discount'),
                stone_amount: getValue('stone-amount'),
                other_amount: getValue('other-amount'),
                diamond_amount: getValue('diamond-amount'),
                purchase_amount: getValue('purchase-amount'),
                sale_amount: getValue('sale-amount'),
                sale_amount_with: getValue('sale-amount-with'),
                reverse: getValue('reverse'),
                tax_amount: getValue('tax'),
                amount: getValue('amount'),
                net_amount: getValue('net-amt'),
                net_amt_tax: getValue('net-amt-tax'),
                design_no: getValue('design-no', false)
            };
            
            products.push(product);
        });
        
        if (products.length === 0) {
            alert('No products to save');
            return;
        }
        
        // Get date from header
        const orderDate = document.getElementById('orderDate');
        const journalDate = orderDate ? orderDate.value : new Date().toISOString().split('T')[0];
        
        // Get group name and comment
        const groupName = document.getElementById('modalGroupName') ? document.getElementById('modalGroupName').value : '';
        const comment = document.getElementById('modalComment') ? document.getElementById('modalComment').value : '';
        
        // Get item_id from URL parameter if available
        const urlParams = new URLSearchParams(window.location.search);
        const itemId = urlParams.get('item_id') ? parseInt(urlParams.get('item_id')) : 0;
        
        // Show loading
        const saveBtn = document.getElementById('saveStockJournalBtn');
        const originalText = saveBtn.innerHTML;
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="feather icon-loader"></i> Saving...';
        
        // Send AJAX request
        fetch('ajax/save-stock-journal.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                date: journalDate,
                item_id: itemId,
                products: products,
                group_name: groupName,
                comment: comment
            })
        })
        .then(response => response.json())
        .then(data => {
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalText;
            
            if (data.status === 'success') {
                alert('Stock Journal saved successfully!');
                // Optionally reload or clear the form
                window.location.reload();
            } else {
                alert('Error: ' + (data.message || 'Failed to save stock journal'));
            }
        })
        .catch(error => {
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalText;
            console.error('Error:', error);
            alert('Error saving stock journal: ' + error.message);
        });
    }
    
    // Add event listener for save button
    document.addEventListener('DOMContentLoaded', function() {
        const saveBtn = document.getElementById('saveStockJournalBtn');
        if (saveBtn) {
            saveBtn.addEventListener('click', saveStockJournal);
        }
        
        // Make all barcode inputs clickable
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('barcode-input') || e.target.closest('.barcode-input')) {
                const input = e.target.classList.contains('barcode-input') ? e.target : e.target.closest('.barcode-input');
                const barcode = input.value.trim();
                if (barcode) {
                    printBarcode(barcode, e);
                }
            }
        });
    });
    
    // Function to print barcode
    function printBarcode(barcode, event) {
        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }
        
        if (!barcode || barcode.trim() === '') {
            alert('No barcode to print');
            return;
        }
        
        var metalType = '';
        if (event && event.target) {
            var row = event.target.closest('tr');
            if (row) {
                metalType = (row.getAttribute('data-metal-name') || '').trim();
                if (!metalType && row.getAttribute('data-metal-id')) {
                    var tab = document.querySelector('.category-tab-btn[data-metal-id="' + String(row.getAttribute('data-metal-id')).replace(/"/g, '') + '"]');
                    if (tab) metalType = (tab.getAttribute('data-metal-name') || '').trim();
                }
            }
        }
        
        var printUrl = 'barcode-print.php?barcode=' + encodeURIComponent(barcode.trim());
        if (metalType) {
            printUrl += '&metal_type=' + encodeURIComponent(metalType);
        }
        window.open(printUrl, '_blank', 'width=800,height=600');
    }
    
    // Function to print barcode from row (gets barcode from the row)
    function printBarcodeFromRow(element) {
        const row = element.closest('tr');
        if (!row) {
            alert('Row not found');
            return;
        }
        
        // First check data-barcode attribute (most reliable)
        let barcode = row.getAttribute('data-barcode') || '';
        
        // If not in data attribute, try to find barcode in the barcode column
        if (!barcode) {
            const barcodeCell = row.querySelector('[data-column="barcode"]');
            if (barcodeCell) {
                // Check if there's an input field
                const barcodeInput = barcodeCell.querySelector('input');
                if (barcodeInput) {
                    barcode = barcodeInput.value.trim();
                } else {
                    // Check if there's text content (barcode displayed as text)
                    let barcodeText = barcodeCell.textContent.trim();
                    // Remove any icon text or placeholder text
                    barcodeText = barcodeText.replace(/icon-image|icon-printer|Click to select product|No barcode|Click to print barcode/gi, '').trim();
                    if (barcodeText && barcodeText.length > 0) {
                        var compact = barcodeText.replace(/\s+/g, '');
                        if (barcodeText.match(/^B\d+/) || /^[A-Za-z0-9._-]+$/.test(compact)) {
                            barcode = compact;
                        }
                    }
                }
            }
        }
        
        if (!barcode || barcode === '') {
            alert('No barcode found for this product');
            return;
        }
        
        // Open barcode print page
        const printUrl = 'barcode-print.php?barcode=' + encodeURIComponent(barcode);
        window.open(printUrl, '_blank', 'width=800,height=600');
    }
    
    // Function to print multiple barcodes
    function printMultipleBarcodes() {
        const barcodeInputs = document.querySelectorAll('#productListBody .barcode-input, #productTableBody [data-column="barcode"] input.barcode-input');
        const barcodes = [];
        
        barcodeInputs.forEach(function(input) {
            const barcode = input.value.trim();
            if (barcode && barcode !== '') {
                barcodes.push(barcode);
            }
        });
        
        // Also check for barcodes in productTableBody (main table)
        const productTableBarcodes = document.querySelectorAll('#productTableBody [data-column="barcode"]');
        productTableBarcodes.forEach(function(cell) {
            const barcodeText = cell.textContent.trim();
            if (barcodeText && barcodeText !== '' && !barcodeText.includes('icon-image')) {
                barcodes.push(barcodeText);
            }
        });
        
        if (barcodes.length === 0) {
            alert('No barcodes found to print');
            return;
        }
        
        // Remove duplicates
        const uniqueBarcodes = [...new Set(barcodes)];
        
        // Open barcode print page with all barcodes
        const printUrl = 'barcode-print.php?barcodes=' + encodeURIComponent(uniqueBarcodes.join(','));
        window.open(printUrl, '_blank', 'width=1200,height=800');
    }
</script>
</body>

</html>




