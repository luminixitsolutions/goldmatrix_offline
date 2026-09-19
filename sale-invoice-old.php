<?php 
session_start();
require_once 'config.php';

// Load Metals for category tabs
$metals = getList("SELECT id, display_name, system_name FROM tbl_metal WHERE status = 1 ORDER BY id ASC");

// Load Karat master data
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
    // ['id' => 1, 'name' => 'Sundry Debtors'],
    ['id' => 2, 'name' => 'Sundry Creditors'],
];

// Bank accounts from Ledger Opening (sundry_debtors_id = 29 = Bank Account type) - exclude UPI/wallet names like PhonePe, Gpay, Paytm
$bank_accounts_raw = getList("SELECT id, name FROM tbl_customers WHERE sundry_debtors_id = 29 AND status = 1 AND TRIM(IFNULL(name,'')) != '' ORDER BY name ASC");
$bank_accounts = [];
$exclude_names = ['phonepe', 'phonepay', 'gpay', 'google pay', 'paytm', 'upi', '0.00', '0'];
if (is_array($bank_accounts_raw)) {
    foreach ($bank_accounts_raw as $b) {
        $n = trim(strtolower($b['name'] ?? ''));
        if ($n === '' || in_array($n, $exclude_names) || preg_match('/^[0-9.]+$/', $n)) continue;
        $bank_accounts[] = $b;
    }
}

// Get next invoice number (Sale Invoice = SI-)
$last_invoice = getRecord("SELECT invoice_no FROM tbl_sale_invoices ORDER BY id DESC LIMIT 1");
$next_order_no = 'SI-1';
if ($last_invoice && $last_invoice['invoice_no']) {
    $last_num = (int)str_replace('SI-', '', $last_invoice['invoice_no']);
    $next_order_no = 'SI-' . ($last_num + 1);
}

// Load invoice for editing if ID provided (tbl_sale_invoices)
$edit_order_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$edit_order = null;
$edit_items = [];
$edit_payments = [];

if (!empty($edit_order_id)) {
    $edit_order = getRecord("SELECT * FROM tbl_sale_invoices WHERE id = " . intval($edit_order_id));
    if ($edit_order) {
        // Include metal_id from product_characteristics so edit modal opens on correct tab (Diamond & Stones etc.)
        $edit_items = getList("
            SELECT i.*, COALESCE(pc.metal_id,
                (SELECT metal_id FROM tbl_product_characteristics WHERE product_id = i.product_id AND status = 1 LIMIT 1)
            ) as metal_id
            FROM tbl_sale_invoice_items i
            LEFT JOIN tbl_product_characteristics pc ON pc.id = i.product_characteristic_id AND pc.product_id = i.product_id
            WHERE i.invoice_id = " . intval($edit_order_id));
        $edit_payments = getList("SELECT * FROM tbl_sale_invoice_payments WHERE invoice_id = " . intval($edit_order_id));
        $next_order_no = $edit_order['invoice_no'] ?? '';
        // Normalize so JS form gets expected keys (order_no, against_of, etc.)
        if (!isset($edit_order['order_no'])) {
            $edit_order['order_no'] = $edit_order['invoice_no'] ?? '';
        }
        if (!array_key_exists('against_of', $edit_order)) {
            $edit_order['against_of'] = '';
        }
        $edit_order['against_of'] = $edit_order['against_of'] ?? '';
        $edit_order['supplier_name'] = $edit_order['customer_name'] ?? $edit_order['supplier_name'] ?? '';
        $edit_order['supplier_id'] = $edit_order['customer_id'] ?? $edit_order['supplier_id'] ?? 0;
        $edit_order['invoice_date'] = $edit_order['invoice_date'] ?? '';
        $edit_order['purchase_person'] = $edit_order['sales_person'] ?? $edit_order['purchase_person'] ?? '';
    }
}
//// Optional: set to true to debug edit load (prints $edit_order_id and getRecord result)
// if (defined('PI_EDIT_DEBUG') && PI_EDIT_DEBUG) { echo '<pre>edit_order_id=' . var_export($edit_order_id, true) . "\ngetRecord result=" . print_r($edit_order, true) . '</pre>'; }

// Allowed Sale Invoice print languages (English + optional one from Invoice Print Settings)
$invoice_print_allowed_languages = function_exists('getInvoicePrintAllowedLanguages') ? getInvoicePrintAllowedLanguages() : ['en'];
$invoice_lang_labels = ['en' => 'English', 'hi' => 'Hindi', 'mr' => 'Marathi', 'ar' => 'Arabic'];
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
    /* Prevent Product List table from disturbing layout - scroll inside left column, sidebar stays fixed */
    .invoice-content-row > .col-lg-8 {
        min-width: 0;
        overflow: hidden;
    }
    .invoice-content-row .product-list-card {
        overflow: hidden;
    }
    .invoice-content-row .product-list-card-body {
        overflow: hidden;
    }
    .invoice-content-row .product-list-table-responsive {
        overflow-x: auto !important;
        max-width: 100%;
        -webkit-overflow-scrolling: touch;
    }
    .invoice-content-row > .col-lg-4 {
        min-width: 280px;
        flex-shrink: 0;
    }
    .summary-panel .summary-label {
        white-space: nowrap;
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
        /* border-radius: 50%; */
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
    
    /* Sticky columns on the right - Settings, Net Amt With Tax, Net Amt */
    /* Column order from right: Settings (0), Net Amt With Tax (80px), Net Amt (180px) */
    
    /* Settings column (last column) - rightmost */
    .product-table thead th:last-child,
    .product-table tbody td:last-child {
        position: sticky;
        right: 0;
        background: #fff;
        z-index: 9;
        box-shadow: -2px 0 4px rgba(0,0,0,0.1);
        min-width: 80px;
        width: 80px;
    }
    .product-table thead th:last-child {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        z-index: 10;
    }
    .product-table tbody tr:hover td:last-child {
        background: linear-gradient(to right, rgba(197, 168, 100, 0.05) 0%, rgba(197, 168, 100, 0.02) 100%);
    }
    
    /* Net Amt With Tax - second from right */
    .product-table th[data-column="net-amt-tax"],
    .product-table td[data-column="net-amt-tax"] {
        position: sticky;
        right: 80px; /* Width of settings column */
        background: #fff;
        z-index: 7;
        box-shadow: -2px 0 4px rgba(0,0,0,0.1);
        min-width: 100px;
        width: 100px;
    }
    .product-table thead th[data-column="net-amt-tax"] {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        z-index: 8;
    }
    .product-table tbody tr:hover td[data-column="net-amt-tax"] {
        background: linear-gradient(to right, rgba(197, 168, 100, 0.05) 0%, rgba(197, 168, 100, 0.02) 100%);
    }
    
    /* Net Amt - third from right */
    .product-table th[data-column="net-amt"],
    .product-table td[data-column="net-amt"] {
        position: sticky;
        right: 180px; /* Width of settings (80px) + net-amt-tax (100px) */
        background: #fff;
        z-index: 7;
        box-shadow: -2px 0 4px rgba(0,0,0,0.1);
        min-width: 100px;
        width: 100px;
    }
    .product-table thead th[data-column="net-amt"] {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
        z-index: 8;
    }
    .product-table tbody tr:hover td[data-column="net-amt"] {
        background: linear-gradient(to right, rgba(197, 168, 100, 0.05) 0%, rgba(197, 168, 100, 0.02) 100%);
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
    .table-settings-search {
        margin-bottom: 0.75rem;
        padding-bottom: 0.75rem;
        border-bottom: 1px solid #e2e8f0;
    }
    .table-settings-search input {
        width: 100%;
        padding: 0.5rem 0.75rem;
        border: 1px solid #cbd5e1;
        border-radius: 4px;
        font-size: 0.85rem;
        color: #1e293b;
        background: #fff;
        transition: all 0.2s ease;
    }
    .table-settings-search input:focus {
        outline: none;
        border-color: #c5a864;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    .table-settings-search input::placeholder {
        color: #94a3b8;
    }
    .table-settings-item.hidden {
        display: none !important;
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
        cursor: grab;
        transition: all 0.2s ease;
    }
    .product-table thead th.draggable-column:active {
        cursor: grabbing;
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
    
    /* Product Selection Modal - fixed width so table scrollbars work; modal body scrolls vertically */
    #productSelectionModal .modal-dialog {
        width: 95% !important;
        max-width: 95% !important;
        margin-left: auto !important;
        margin-right: auto !important;
        height: auto !important;
        min-height: 0 !important;
    }
    #productSelectionModal .modal-content {
        border-radius: 10px;
        border: none;
        box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        max-height: 95vh !important;
        height: auto !important;
        min-height: 0 !important;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    #productSelectionModal .modal-header {
        border-radius: 10px 10px 0 0;
        flex-shrink: 0;
    }
    #productSelectionModal .modal-body {
        flex: 1 1 auto !important;
        min-height: 0 !important;
        overflow-y: auto !important;
        overflow-x: auto !important;
        max-height: calc(95vh - 120px) !important;
        padding: 1rem 1.5rem 10px 1.5rem !important;
        -webkit-overflow-scrolling: touch;
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
    /* Product Selection: table in a scrollable box so horizontal and vertical scrollbars show when needed */
    #productSelectionModal .table-responsive,
    #productSelectionModal #productListTableScrollWrapper {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        max-height: min(420px, 45vh) !important;
        overflow-x: auto !important;
        overflow-y: auto !important;
        -webkit-overflow-scrolling: touch;
    }
    #productSelectionModal #productListTableOuter {
        display: block !important;
        width: 100% !important;
        max-width: 100% !important;
        margin-bottom: 0 !important;
    }
    /* Metal tab: same shrink-to-table, with compact max-height */
    #productSelectionModal.product-modal-metal-tab #productListTableOuter {
        width: auto !important;
        max-width: 100% !important;
    }
    #productSelectionModal.product-modal-metal-tab #productListTableScrollWrapper,
    #productSelectionModal.product-modal-metal-tab .table-responsive {
        max-height: 260px !important;
        width: auto !important;
        max-width: 100% !important;
        overflow-x: auto !important;
        overflow-y: auto !important;
    }
    #productSelectionModal.product-modal-metal-tab .modal-body {
        display: block !important;
        padding: 1rem 1.5rem 10px 1.5rem !important;
        max-height: 85vh !important;
        min-height: 0 !important;
        flex: 0 0 auto !important;
        height: auto !important;
    }
    #productSelectionModal.product-modal-metal-tab .row.mt-3 {
        margin-top: 0.35rem !important;
    }
    #productSelectionModal.product-modal-metal-tab .text-right.mt-3 {
        margin-top: 0.35rem !important;
    }
    #productSelectionModal.product-modal-metal-tab .form-group.mb-2 {
        margin-bottom: 0.35rem !important;
    }
    /* No gap below ADD button: last section in modal-body has no bottom margin */
    #productSelectionModal .modal-body > .text-right.mt-3:last-of-type,
    #productSelectionModal .modal-body > .d-flex.text-right:last-of-type {
        margin-bottom: 0 !important;
    }
    #productSelectionModal .modal-body > *:last-child {
        margin-bottom: 0 !important;
    }
    /* Product Selection table: width max-content so table controls width; min-width 100% for narrow containers */
    #productSelectionModal .table-responsive table,
    #productSelectionModal #productListTable.product-list-table-fit,
    #productSelectionModal #productListTable {
        width: max-content !important;
        min-width: 100% !important;
        table-layout: auto !important;
        border-collapse: collapse;
    }
    /* Prevent blank column: last column (Action) has no trailing space */
    #productSelectionModal #productListTable td[data-column="actions"],
    #productSelectionModal #productListTable th[data-column="actions"] {
        padding-right: 0.5rem !important;
        border-right: 1px solid #dee2e6;
    }
    /* Hidden columns: zero width but keep column in table so group headers (Other Charge, Hallmark, Net Amt+Tax/Reverse) stay aligned */
    #productSelectionModal #productListTable th.hidden,
    #productSelectionModal #productListTable td.hidden {
        width: 0 !important;
        min-width: 0 !important;
        max-width: 0 !important;
        padding: 0 !important;
        border-width: 0 !important;
        overflow: hidden !important;
        visibility: collapse !important;
    }
    /* Ensure inputs/selects inside hidden columns are not visible */
    #productSelectionModal #productListTable td.hidden input,
    #productSelectionModal #productListTable td.hidden select,
    #productSelectionModal #productListTable th.hidden input,
    #productSelectionModal #productListTable th.hidden select {
        display: none !important;
    }
    #productSelectionModal #productListTable th,
    #productSelectionModal #productListTable td {
        white-space: nowrap;
        padding: 0.5rem 0.4rem;
        vertical-align: middle;
    }
    /* Right columns: Net Amt+Tax, Reverse, Action - sticky so they stay visible when scrolling horizontally */
    #productSelectionModal #productListTable th[data-column="actions"],
    #productSelectionModal #productListTable td[data-column="actions"] {
        min-width: 80px !important;
        width: 80px !important;
        max-width: 80px !important;
        background: #fff !important;
        position: sticky !important;
        right: 0 !important;
        z-index: 2;
        box-shadow: -2px 0 6px rgba(0,0,0,0.06);
    }
    #productSelectionModal #productListTable thead th[data-column="actions"] {
        background: #a68a4a !important;
        z-index: 4;
    }
    #productSelectionModal #productListTable thead tr:first-child th[data-column="actions"] {
        position: sticky !important;
        right: 0 !important;
        z-index: 4;
        box-shadow: -2px 0 6px rgba(0,0,0,0.08);
    }
    #productSelectionModal #productListTable tbody tr:hover td[data-column="actions"] {
        background: #f8fafc !important;
    }
    #productSelectionModal #productListTable th[data-column="reverse"],
    #productSelectionModal #productListTable td[data-column="reverse"] {
        min-width: 80px !important;
        width: 80px !important;
        max-width: 80px !important;
        background: #fff !important;
        position: sticky !important;
        right: 80px !important;
        z-index: 2;
        box-shadow: -2px 0 6px rgba(0,0,0,0.06);
    }
    #productSelectionModal #productListTable thead th[data-column="reverse"] {
        background: #a68a4a !important;
        z-index: 3;
    }
    #productSelectionModal #productListTable thead tr:first-child th[data-group="net-reverse"] {
        position: sticky !important;
        right: 80px !important;
        min-width: 200px !important;
        width: 200px !important;
        z-index: 3;
        box-shadow: -2px 0 6px rgba(0,0,0,0.08);
    }
    #productSelectionModal #productListTable tbody tr:hover td[data-column="reverse"] {
        background: #f8fafc !important;
    }
    #productSelectionModal #productListTable th[data-column="net-amt-tax"],
    #productSelectionModal #productListTable td[data-column="net-amt-tax"] {
        min-width: 120px !important;
        width: 120px !important;
        max-width: 120px !important;
        background: #fff !important;
        position: sticky !important;
        right: 160px !important;
        z-index: 2;
        box-shadow: -2px 0 6px rgba(0,0,0,0.06);
    }
    #productSelectionModal #productListTable thead th[data-column="net-amt-tax"] {
        background: #a68a4a !important;
        z-index: 3;
    }
    #productSelectionModal #productListTable tbody tr:hover td[data-column="net-amt-tax"] {
        background: #f8fafc !important;
    }
    #productSelectionModal #productListTable thead th {
        background: #f8fafc;
        font-weight: 700;
        font-size: 0.7rem;
        color: #fff;
        border: 1px solid #c5a864;
    }
    #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column] {
        cursor: grab;
    }
    #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column]:active {
        cursor: grabbing;
    }
    #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column].modal-col-dragging {
        background: rgba(197, 168, 100, 0.25) !important;
        border: 2px dashed #c5a864;
    }
    #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column].modal-col-drag-over-left {
        border-left: 2px solid #c5a864;
        background: rgba(197, 168, 100, 0.15) !important;
    }
    #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column].modal-col-drag-over-right {
        border-right: 2px solid #c5a864;
        background: rgba(197, 168, 100, 0.15) !important;
    }
    #productSelectionModal #productListTable thead tr:first-child th[data-group].modal-group-dragging {
        background: rgba(197, 168, 100, 0.35) !important;
        border: 2px dashed #c5a864;
    }
    #productSelectionModal #productListTable thead tr:first-child th[data-group].modal-group-drag-over-left {
        border-left: 3px solid #c5a864;
        background: rgba(197, 168, 100, 0.2) !important;
    }
    #productSelectionModal #productListTable thead tr:first-child th[data-group].modal-group-drag-over-right {
        border-right: 3px solid #c5a864;
        background: rgba(197, 168, 100, 0.2) !important;
    }
    #productSelectionModal #productListTable tbody tr:hover {
        background: #f8fafc;
    }
    #productSelectionModal #productListTable tbody tr.selected {
        background: #fff3cd !important;
    }
    #productSelectionModal #productListTable tbody tr.selected:hover {
        background: #ffe69c !important;
    }
    #productSelectionModal #productListTable input.form-control-sm,
    #productSelectionModal #productListTable select.form-control-sm {
        border: 1px solid #e2e8f0;
        padding: 0.25rem 0.4rem;
        font-size: 0.7rem;
    }
    #productSelectionModal #productListTable input.form-control-sm:focus,
    #productSelectionModal #productListTable select.form-control-sm:focus {
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
                   

                    <div class="row invoice-content-row">
                        <!-- Main Content Area -->
                        <div class="col-lg-8" >
                            <!-- Transaction Details Form -->
                            <div class="card mb-4">
                                <div class="card-body billing-form">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label>Search Sale Invoice</label>
                                                    <div style="position: relative;">
                                                        <input type="text" class="form-control form-control-sm" id="searchSaleInvoice" placeholder="Search by customer name or invoice number..." autocomplete="off" style="padding-right: 35px;">
                                                        <i class="feather icon-search" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 12px; pointer-events: none;"></i>
                                                        <div id="saleInvoiceSuggestions" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 4px; max-height: 300px; overflow-y: auto; z-index: 1000; box-shadow: 0 4px 12px rgba(0,0,0,0.15); margin-top: 2px;"></div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label>Name *</label>
                                                    <div style="position: relative;">
                                                        <input type="text" class="form-control form-control-sm" id="customerName" placeholder="Enter customer name" required style="padding-right: 35px;" autocomplete="off">
                                                        <input type="hidden" id="customerId" name="customer_id" value="">
                                                        <i class="feather icon-plus add-customer-icon" id="addCustomerBtn" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #c5a864; font-size: 1.1rem; z-index: 10; pointer-events: auto;" title="Add New Customer"></i>
                                                        <div id="customerSuggestions" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 4px; max-height: 300px; overflow-y: auto; z-index: 1000; box-shadow: 0 4px 12px rgba(0,0,0,0.15); margin-top: 2px;"></div>
                                                    </div>
                                                </div>
                                            </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label>Against Of</label>
                                                <select class="form-control form-control-sm" id="againstOf">
                                                    <option value="">Select option</option>
                                                    <option value="Purchase Quotation">Purchase Quotation</option>
                                                    <option value="Purchase Order">Purchase Order</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Currency</label>
                                                <select class="form-control form-control-sm" id="currency">
                                                    <option value="AED" selected>AED</option>
                                                    <option value="USD">USD</option>
                                                    <option value="EUR">EUR</option>
                                                </select>
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
                                                <input type="date" class="form-control form-control-sm" id="orderDate" value="<?php echo date('Y-m-d'); ?>">
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
                                        <!-- Hedging details: shown only when Fixing Type = Hedging -->
                                        <!-- <div class="col-md-6" id="hedgingSection" style="display: none;">
                                            <div class="form-group">
                                                <label>Hedge Contract Ref.</label>
                                                <input type="text" class="form-control form-control-sm" id="hedgeContractRef" placeholder="Contract reference">
                                            </div>
                                        </div>
                                        <div class="col-md-6" id="hedgingSectionDate" style="display: none;">
                                            <div class="form-group">
                                                <label>Hedge / Locked Rate Date</label>
                                                <input type="date" class="form-control form-control-sm" id="hedgeDate" placeholder="">
                                            </div>
                                        </div> -->
                                        <!-- <div class="col-md-3">
                                            <div class="form-group" style="justify-content: flex-end;">
                                                <label style="min-width: 0; margin-right: 0.5rem;">&nbsp;</label>
                                                <div style="display: flex; gap: 0.5rem;">
                                                    <button type="button" class="btn btn-primary btn-sm" onclick="resetOrder()">New +</button>
                                                    <button type="button" class="btn btn-purple btn-sm" onclick="saveOrder()">Save</button>
                                                </div>
                                            </div>
                                        </div> -->
                                    </div>
                                </div>
                            </div>

                            <!-- Product Entry Section -->
                            <div class="card mb-4">
                                <div class="card-body product-entry">
                                    <div class="add-item-link" id="addItemBtn" style="cursor: pointer;" onclick="if(typeof window.openProductModal==='function'){window.openProductModal();}return false;">
                                        <a href="javascript:void(0)" onclick="event.preventDefault();event.stopPropagation();if(typeof window.openProductModal==='function'){window.openProductModal();}return false;"><i class="feather icon-plus"></i> Add Item (Shift + Q)</a>
                                    </div>
                                </div>
                            </div>

                            <!-- Product List Table -->
                            <div class="card mb-4 product-list-card">
                                <div class="card-body product-list-card-body">
                                    <div class="table-header-wrapper">
                                        <h6 style="margin: 0; font-size: 0.9rem; font-weight: 700; color: #1e293b;">Product List <span class="text-muted" style="font-size: 0.75rem; font-weight: normal;">— Drag column headers to reorder; order is saved</span></h6>
                                        <div class="table-settings-wrapper">
                                            <button class="table-settings-btn" id="tableSettingsBtn">
                                                <i class="feather icon-settings"></i>
                                            </button>
                                            <div class="table-settings-dropdown" id="tableSettingsDropdown">
                                                <h6>Show/Hide Columns</h6>
                                                <div class="table-settings-search">
                                                    <input type="text" id="tableSettingsSearch" placeholder="Search columns..." autocomplete="off">
                                                </div>
                                                <?php
                                                $productListColumns = [
                                                    ['product','Name'],['short-code','Short Code'],['rfid','RFIDCode'],['voucher-type','Voucher Type'],['photo','Photo'],['barcode','Barcode'],['design-no','Design No'],['huid','HUID No'],['category','Category'],['calculation','Calculation'],['location','Location'],
                                                    ['quantity','Quantity'],['carat','Karat'],['pkt-wt','Pkt. Wt.'],['pkt-less-wt','Pkt. Less Wt.'],['requested-purity','Requested Purity'],['requested','Requested'],['gross-wt','Gross Wt.'],['less-wt','Less Wt.'],['gold-loss1','Gold Loss 1'],['gold-loss2','Gold Loss 2'],['setting-charge','Setting Charge'],['net-wt','Net Wt.'],['purity','Purity'],['purity-wt','Purity Wt.'],['wastage-per','Wastage Per.'],['wastage-wt','Wastage Wt.'],['final-wt','Final Wt.'],['alloy-wt','Alloy Wt.'],
                                                    ['rate','Rate'],['metal-value','Metal Value'],['metal-cost','Metal Cost'],['amount','Amount'],
                                                    ['metal-qty','Metal Qty'],['metal-weight','Weight'],
                                                    ['discount-type','Discount Type'],['discount-per','Discount Per.'],['discount-amount','Discount Amount'],['discount','Discount'],
                                                    ['making-type','Making Type'],['making-rate','Making Rate'],['making-amount','Making Amount'],['making-cost','Making Cost'],['min-price','Minimum Price'],
                                                    ['stone-charge-type','Stone Charge Type'],['stone-weight','Stone Weight'],['stone-rate','Stone Rate'],['stone-amount','Stone Amount'],['stone-cost','Stone Cost'],['diamond-amount','Diamond Amount'],
                                                    ['purchase-amount','Purchase Amount'],['sale-amount','Sale Amount'],['sale-amount-with','Sale Amount With'],['net-amt','Net Amt'],['tax-type','Tax Type'],['tax-percent','Tax %'],['tax','Tax'],
                                                    ['other-charge-type','Other Charge Type'],['other-weight','Other Weight'],['other-rate','Other Rate'],['other-info','Other Info'],['other-amount','Other Amount'],
                                                    ['hallmark-amount','Hallmark Amount'],['hallmark-rate','Hallmark Rate'],['net-amt-tax','Net Amt+Tax'],['reverse','Reverse']
                                                ];
                                                foreach ($productListColumns as $col): ?>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="col-<?php echo $col[0]; ?>" data-column="<?php echo $col[0]; ?>" checked>
                                                    <label for="col-<?php echo $col[0]; ?>"><?php echo htmlspecialchars($col[1]); ?></label>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="table-responsive product-list-table-responsive">
                                        <table class="table table-bordered product-table">
                                            <thead>
                                                <tr>
                                                    <?php foreach ($productListColumns as $col): ?>
                                                    <th class="draggable-column" data-column="<?php echo $col[0]; ?>"<?php echo $col[0] === 'photo' ? ' style="min-width: 70px;"' : ''; ?> title="Drag to reorder columns"><?php echo htmlspecialchars($col[1]); ?></th>
                                                    <?php endforeach; ?>
                                                    <th style="width: 80px; text-align: center;">
                                                        <i class="feather icon-settings" style="cursor: pointer;"></i>
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody id="productTableBody">
                                                <tr class="no-drag">
                                                    <td colspan="65" class="text-center text-muted py-4" id="emptyRowCell">No Rows To Show</td>
                                                </tr>
                                            </tbody>
                                            <tfoot id="productTableFooter" style="display: none;">
                                                <tr style="background: #f8fafc; font-weight: 600;">
                                                    <?php
                                                    $footerIdByCol = ['quantity'=>'footerQuantity','gross-wt'=>'footerGrossWt','less-wt'=>'footerLessWt','purity'=>'footerPurity','final-wt'=>'footerFinalWt','net-wt'=>'footerNetWt','purity-wt'=>'footerPureWt','making-amount'=>'footerMakingAmount','stone-amount'=>'footerStoneCharges','other-amount'=>'footerOtherCharges','diamond-amount'=>'footerDiamondValue','rate'=>'footerRate','metal-value'=>'footerMetalValue','discount'=>'footerDiscount','making-amount'=>'footerMakingAmount','stone-amount'=>'footerStoneAmount','other-amount'=>'footerOtherAmount','diamond-amount'=>'footerDiamondAmount','purchase-amount'=>'footerPurchaseAmount','sale-amount'=>'footerSaleAmount','sale-amount-with'=>'footerSaleAmountWith','reverse'=>'footerReverse','tax'=>'footerTax','amount'=>'footerAmount','net-amt'=>'footerNetAmt','net-amt-tax'=>'footerNetAmtTax'];
                                                    foreach ($productListColumns as $col):
                                                        $fid = isset($footerIdByCol[$col[0]]) ? $footerIdByCol[$col[0]] : '';
                                                        $isFirst = ($col[0] === 'product');
                                                    ?>
                                                    <td <?php echo $fid ? 'id="'.$fid.'"' : ''; ?> data-column="<?php echo $col[0]; ?>" style="text-align: right; color: #11294b;"><?php echo $isFirst ? 'Grand Total:' : ($fid ? '0.00' : ''); ?></td>
                                                    <?php endforeach; ?>
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
                                </div>
                            </div>

                            <!-- Payment Details Section -->
                            <div class="card mb-4">
                                <div class="card-body">
                                    <div class="row align-items-end">
                                        <div class="col-md-5">
                                            <div class="form-group mb-2 billing-form">
                                                
                                                <input type="text" class="form-control form-control-sm" placeholder="Receipt number">
                                            </div>
                                        </div>
                                        <div class="col-md-7">
                                    <!-- Payment Method Icons -->
                                            <div class="payment-icons" style="margin-bottom: 0;">
                                                <div class="payment-icon payment-cash" title="Cash">
                                                    <img src="icons/cash.jpeg" alt="Cash" style="width: 45px; height: 45px;">
                                        </div>
                                                <div class="payment-icon payment-bank" title="Bank">
                                                    <img src="icons/bank.jpeg" alt="Bank"  style="width: 45px; height: 45px;">
                                        </div>
                                                <div class="payment-icon payment-cheque" title="Cheque">
                                            <img src="icons/cheque.jpeg" alt="Cheque" style="width: 45px; height: 45px;">
                                        </div>
                                                <div class="payment-icon payment-mobile" title="UPI/Mobile Payment">
                                            <img src="icons/upi.jpeg" alt="UPI/Mobile Payment" style="width: 45px; height: 45px;">
                                        </div>
                                                <div class="payment-icon payment-card" title="Card">
                                            <img src="icons/card.jpeg" alt="Card" style="width: 45px; height: 45px;">
                                        </div>
                                                <div class="payment-icon payment-exchange" title="Metal Exchange">
                                                    <img src="icons/metal.jpeg" alt="Metal Exchange" style="width: 45px; height: 45px;">
                                        </div>
                                                <div class="payment-icon payment-jewelry" title="Scrap Payment">
                                                    <img src="icons/scrap.jpeg" alt="Scrap Payment" style="width: 45px; height: 45px;">
                                        </div>
                                                <div class="payment-icon payment-diamond" title="Diamond">
                                                    <img src="icons/diamond.jpeg" alt="Diamond" style="width: 45px; height: 45px;">
                                        </div>
                                                <div class="payment-icon payment-stone" title="Stone">
                                            <img src="icons/stone.jpeg" alt="Stone" style="width: 45px; height: 45px;">
                                        </div>
                                        <div class="payment-icon payment-other" title="Other">
                                            <img src="icons/old.jpeg" alt="Other" style="width: 45px; height: 45px;">
                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="table-responsive" style="padding-top: 10px;">
                                        <table class="table table-bordered table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Payment Type</th>
                                                    <th>Deposit Into</th>
                                                    <th>Transaction No.</th>
                                                    <th>Cheque Dt.</th>
                                                    <th>Purity / Karat</th>
                                                    <th>Amount</th>
                                                    <th>Diamond Categ...</th>
                                                    <th>Quantity</th>
                                                    <th style="width: 80px;">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="paymentTableBody">
                                                <tr class="no-payment-row">
                                                    <td colspan="9" class="text-center text-muted py-3">No payment entries</td>
                                                </tr>
                                            </tbody>
                                            <tfoot id="paymentTableFooter" style="display: none;">
                                                <tr style="background: #f8fafc; font-weight: 600;">
                                                    <td colspan="5" style="text-align: right; color: #11294b;">Total:</td>
                                                    <td id="paymentTotalAmount" style="text-align: right; color: #11294b; font-weight: 700;">0.00</td>
                                                    <td></td>
                                                    <td id="paymentTotalQuantity" style="text-align: right; color: #11294b;">0.00</td>
                                                    <td></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                    
                                    <!-- Comments: add multiple with date/time, edit, delete -->
                                    <div class="mt-3">
                                        <label class="mb-2" style="font-size: 0.85rem; color: #475569;">Comments</label>
                                        <div class="input-group input-group-sm mb-2">
                                            <input type="text" class="form-control" id="paymentComment" placeholder="Enter Comment">
                                            <div class="input-group-append">
                                                <button type="button" class="btn btn-sm" id="paymentCommentAddBtn" style="background: #11294b; color: #fff; border: none;" title="Add comment">
                                                    <i class="feather icon-plus"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <input type="hidden" id="paymentCommentsData" name="payment_comments" value="">
                                        <div id="paymentCommentsList" class="border rounded p-2" style="min-height: 40px; max-height: 200px; overflow-y: auto; background: #f8fafc; font-size: 0.8rem;"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Summary Panel -->
                        <div class="col-lg-4" >
                            <div class="summary-panel" >
                                <!-- Sales Order Header -->
                                <div class="invoice-header" style="margin: -1rem -1rem 1rem -1rem; border-radius: 10px 10px 0 0; display: flex; align-items: center; justify-content: space-between;">
                                    <div style="display: flex; align-items: center; gap: 0.5rem; flex: 1;">
                                        <i class="feather icon-printer" id="printInvoiceIcon" style="font-size: 12px; color: #94a3b8; opacity: 0.5; cursor: not-allowed; pointer-events: none;" title="Save invoice first to print"></i>
                                        <h5 class="mb-0" style="font-size: 0.9rem;">Sale Invoice No: <span id="currentOrderNo"><?php echo htmlspecialchars($next_order_no); ?></span></h5>
                                    </div>
                                    <div class="invoice-header-actions">
                                        <button class="btn-new-invoice btn-sm" onclick="resetOrder()">New +</button>
                                        <button class="btn-save-invoice btn-sm" onclick="saveOrder()">Save</button>
                                    </div>
                                </div>
                                <!-- Previous Balance -->
                                <div class="summary-section">
                                    <h6 class="mb-3">Previous Balance</h6>
                                    <div class="summary-row">
                                        <span class="summary-label">Amount</span>
                                        <span class="summary-value" id="previousBalanceAmount">0</span>
                                    </div>
                                    <div class="summary-row">
                                        <span class="summary-label">Gold</span>
                                        <span class="summary-value" id="previousBalanceGold">0</span>
                                    </div>
                                    <div class="summary-row">
                                        <span class="summary-label">Silver</span>
                                        <span class="summary-value" id="previousBalanceSilver">0</span>
                                    </div>
                                    <div class="summary-row">
                                        <span class="summary-label">Diamond</span>
                                        <span class="summary-value" id="previousBalanceDiamond">0</span>
                                    </div>
                                    <div class="summary-row">
                                        <span class="summary-label">Gemstone</span>
                                        <span class="summary-value" id="previousBalanceGemstone">0</span>
                                    </div>
                                    <div class="summary-row" style="margin-top: 0.5rem; align-items: center;">
                                        <label class="mb-0 d-flex align-items-center" style="cursor: pointer;">
                                            <input type="checkbox" id="usePreviousBalanceCheck" value="1" style="margin-right: 6px;">
                                            <span class="summary-label mb-0">Use previous balance</span>
                                        </label>
                                    </div>
                                    <div class="summary-row" id="previousBalanceUseAmountRow" style="display: none;">
                                        <span class="summary-label">Amount to use</span>
                                        <input type="text" class="form-control form-control-sm" id="previousBalanceUseAmount" value="0.00" step="0.01" min="0" placeholder="0.00" style="width: 90px; text-align: right;">
                                    </div>
                                    <div style="margin-top: 0.5rem; overflow-x: auto;">
                                        <div style="min-width: 200px; height: 4px; background: #e2e8f0; border-radius: 2px;"></div>
                                    </div>
                                </div>

                                <!-- Totals -->
                                <div class="summary-section">
                                    <h6 class="mb-3">Total</h6>
                                    <div class="summary-row">
                                        <span class="summary-label">Total</span>
                                        <span class="summary-value" id="summaryTotal" style="color: #dc2626;">0.00</span>
                                    </div>
                                    <div class="summary-row">
                                        <span class="summary-label">
                                            <a href="javascript:void(0)" onclick="showAdditionalAmtModal()" style="color: #11294b; text-decoration: underline;">Additional Amt</a>
                                        </span>
                                        <span class="summary-value" id="summaryAdditionalAmt">0.00</span>
                                    </div>
                                    <div class="summary-row">
                                        <span class="summary-label">Net Total</span>
                                        <span class="summary-value" id="summaryNetTotal">0.00</span>
                                    </div>
                                    <div class="summary-row">
                                        <span class="summary-label">Discount</span>
                                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                                            <div style="position: relative; display: inline-block;">
                                                <input type="text" class="form-control form-control-sm" id="discountPercent" value="0" step="0.01" style="width: 70px; text-align: right; padding-right: 20px;">
                                                <span style="position: absolute; right: 6px; top: 50%; transform: translateY(-50%); font-size: 0.75rem; color: #64748b; pointer-events: none;">%</span>
                                            </div>
                                            <input type="text" class="form-control form-control-sm" id="discountAmount" value="0.00" step="0.01" style="width: 80px; text-align: right;">
                                        </div>
                                    </div>
                                    <div class="summary-row">
                                        <span class="summary-label">Grand Total</span>
                                        <span class="summary-value" id="summaryGrandTotal" style="color: #dc2626; font-weight: 700;">0.00</span>
                                    </div>
                                    <div class="summary-row">
                                        <span class="summary-label">
                                            Advance Payment
                                            <a href="javascript:void(0)" onclick="showAdvancePaymentModal()" style="color: #11294b; text-decoration: underline; font-size: 0.75rem; margin-left: 0.5rem;">(show details)</a>
                                        </span>
                                        <span class="summary-value" id="advancePaymentAmount">0.00</span>
                                    </div>
                                    <div id="advancePaymentDetails" style="display: none; margin-left: 1rem; margin-top: 0.5rem; font-size: 0.85rem;">
                                        <div class="summary-row" style="margin-bottom: 0.25rem;">
                                            <span class="summary-label">
                                                <a href="javascript:void(0)" style="color: #11294b; text-decoration: underline;">From Order</a>
                                            </span>
                                            <span class="summary-value" id="advanceFromOrder">0</span>
                                        </div>
                                        <div class="summary-row" style="margin-bottom: 0.25rem;">
                                            <span class="summary-label">
                                                <a href="javascript:void(0)" style="color: #11294b; text-decoration: underline;">From Fund</a>
                                            </span>
                                            <span class="summary-value" id="advanceFromFund">0</span>
                                        </div>
                                        <div class="summary-row" style="margin-bottom: 0.25rem;">
                                            <span class="summary-label">
                                                <a href="javascript:void(0)" style="color: #11294b; text-decoration: underline;">From Customer Advance</a>
                                            </span>
                                            <span class="summary-value" id="advanceFromCustomerAdvance">0</span>
                                        </div>
                                        <div class="summary-row" style="margin-bottom: 0.25rem;">
                                            <span class="summary-label">
                                                <a href="javascript:void(0)" style="color: #11294b; text-decoration: underline;">From Return</a>
                                            </span>
                                            <span class="summary-value" id="advanceFromReturn">0</span>
                                        </div>
                                        <div class="summary-row" style="margin-bottom: 0.25rem;">
                                            <span class="summary-label">
                                                <a href="javascript:void(0)" style="color: #11294b; text-decoration: underline;">From Previous Balance</a>
                                            </span>
                                            <span class="summary-value" id="advanceFromPreviousBalance">0</span>
                                        </div>
                                    </div>
                                    <div class="summary-row">
                                        <span class="summary-label">Metal Amt</span>
                                        <span class="summary-value" id="summaryMetalAmt">0.00</span>
                                    </div>
                                    <div class="form-group mb-0">
                                        <div class="d-flex align-items-center">
                                            <input type="checkbox" class="mr-2" id="roundOff">
                                            <label for="roundOff" class="mb-0 mr-2">Round Off</label>
                                            <input type="text" class="form-control form-control-sm" id="roundOffValue" style="width: 100px; margin-left: auto; text-align: right;" value="0.00" step="0.01">
                                        </div>
                                    </div>
                                    <div class="summary-row mt-2">
                                        <span class="summary-label">Return Invoice</span>
                                        <span class="summary-value" id="summaryReturnInvoice">0</span>
                                    </div>
                                    <div class="summary-row">
                                        <span class="summary-label">Paid Amt</span>
                                        <span class="summary-value" id="summaryPaidAmt" style="color: #c5a864; font-weight: 700;">0.00</span>
                                    </div>
                                    <div class="summary-row">
                                        <span class="summary-label">Balance Amt</span>
                                        <span class="summary-value" id="summaryBalanceAmt" style="color: #c5a864; font-weight: 700;">0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>
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
<?php include 'includes/common-modal.php'; ?>

<?php include 'footer-script.php';?>

<script src="assets/js/product-modal-add-item-common.js"></script>

<script>
    // Runs after footer-script.php (jQuery, Bootstrap). EDIT_ORDER_DATA and master data are valid JSON.
    // Master data for dropdowns (safe fallbacks so no invalid/empty JSON)
    const carats = <?php echo json_encode(isset($carats) && is_array($carats) ? $carats : []); ?>;
    const locations = <?php echo json_encode(isset($locations) && is_array($locations) ? $locations : []); ?>;
    const categories = <?php echo json_encode(isset($categories) && is_array($categories) ? $categories : []); ?>;
    const metals = <?php echo json_encode(isset($metals) && is_array($metals) ? $metals : []); ?>;
    const nationalities = <?php
        $nationalities_js = getList("SELECT id, name FROM tbl_nationalities WHERE status = 1 ORDER BY name ASC");
        echo json_encode(is_array($nationalities_js ?? null) ? $nationalities_js : []);
    ?>;
    
    // Edit mode: skip deferred loadCustomerBalance so it doesn't overwrite saved Grand Total / Paid / Balance
    window.isPurchaseInvoiceEditMode = <?php echo (!empty($edit_order_id) && $edit_order_id > 0) ? 'true' : 'false'; ?>;
    
    <?php
    // Embed edit order/items/payments so form populates on page load (no AJAX dependency for direct ?id= load)
    // Always output valid JSON: object or null (never empty or broken)
    $embed_order = null;
    $embed_items = [];
    $embed_payments = [];
    if (!empty($edit_order_id) && $edit_order_id > 0 && !empty($edit_order) && is_array($edit_order)) {
        $embed_order = [
            'id' => (int)($edit_order['id'] ?? 0),
            'invoice_no' => $edit_order['invoice_no'] ?? '',
            'order_no' => $edit_order['invoice_no'] ?? '',
            'supplier_id' => (int)($edit_order['supplier_id'] ?? 0),
            'customer_id' => (int)($edit_order['supplier_id'] ?? 0),
            'supplier_name' => $edit_order['supplier_name'] ?? '',
            'customer_name' => $edit_order['supplier_name'] ?? '',
            'against_of' => $edit_order['against_of'] ?? '',
            'currency' => $edit_order['currency'] ?? 'AED',
            'ref_no' => $edit_order['ref_no'] ?? '',
            'purchase_person' => $edit_order['purchase_person'] ?? '',
            'sales_person' => $edit_order['sales_person'] ?? $edit_order['purchase_person'] ?? '',
            'invoice_date' => $edit_order['invoice_date'] ?? '',
            'order_date' => $edit_order['invoice_date'] ?? '',
            'due_date' => $edit_order['due_date'] ?? '',
            'layaways_id' => $edit_order['layaways_id'] ?? '',
            'fixing_type' => $edit_order['fixing_type'] ?? 'Standard',
            'previous_balance' => (float)($edit_order['previous_balance'] ?? 0),
            'previous_gold' => (float)($edit_order['previous_gold'] ?? 0),
            'previous_silver' => (float)($edit_order['previous_silver'] ?? 0),
            'previous_diamond' => (float)($edit_order['previous_diamond'] ?? 0),
            'previous_gemstone' => (float)($edit_order['previous_gemstone'] ?? 0),
            'subtotal' => (float)($edit_order['subtotal'] ?? 0),
            'net_total' => (float)($edit_order['net_total'] ?? $edit_order['subtotal'] ?? 0),
            'grand_total' => (float)($edit_order['grand_total'] ?? 0),
            'paid_amt' => (float)($edit_order['paid_amt'] ?? 0),
            'balance_amt' => (float)($edit_order['balance_amt'] ?? 0),
            'metal_amt' => (float)($edit_order['metal_amt'] ?? 0),
            'round_off' => (float)($edit_order['round_off'] ?? 0),
            'previous_balance_used_amt' => (float)($edit_order['previous_balance_used_amt'] ?? 0),
            'discount_amt' => (float)($edit_order['discount_amt'] ?? 0),
            'discount_percent' => (float)($edit_order['discount_percent'] ?? 0),
            'comment' => $edit_order['comment'] ?? '',
            'payment_comments' => $edit_order['payment_comments'] ?? '[]',
        ];
        $embed_items = is_array($edit_items) ? $edit_items : [];
        $embed_payments = is_array($edit_payments) ? $edit_payments : [];
        // Convert saved image paths (images column) to full URLs for group_image so UI can display them
        $base_url = (isset($SiteUrl) ? rtrim((string) $SiteUrl, '/') . '/' : '');
        foreach ($embed_items as &$emb_it) {
            if (!empty($emb_it['images'])) {
                $dec = @json_decode($emb_it['images'], true);
                if ($dec && !empty($dec['images']) && is_array($dec['images'])) {
                    $urls = array_map(function ($p) use ($base_url) { return $base_url . auragold_uploads_public_rel(ltrim((string) $p, '/')); }, $dec['images']);
                    $primary = isset($dec['primary']) ? $dec['primary'] : $dec['images'][0];
                    $primary_url = $base_url . auragold_uploads_public_rel(ltrim((string) $primary, '/'));
                    $emb_it['group_image'] = json_encode(['primary' => $primary_url, 'images' => $urls]);
                }
            }
        }
        unset($emb_it);
    }
    $edit_data_obj = $embed_order !== null ? ['order' => $embed_order, 'items' => $embed_items, 'payments' => $embed_payments] : null;
    $json_flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
    $edit_data_json = $edit_data_obj !== null ? json_encode($edit_data_obj, $json_flags) : 'null';
    if ($edit_data_json === false) { $edit_data_json = 'null'; }
    echo "window.EDIT_ORDER_DATA = " . $edit_data_json . ";\n";
    ?>
    // Use constants from product-modal-add-item-common.js
    const DIAMOND_TAB_VISIBLE_COLUMNS = window.DIAMOND_TAB_VISIBLE_COLUMNS || [];
    const DIAMOND_CALCULATION_OPTIONS = window.DIAMOND_CALCULATION_OPTIONS || [];
    const FULL_CALCULATION_OPTIONS = window.FULL_CALCULATION_OPTIONS || [];
    const JEWELLERY_CALCULATION_OPTIONS = window.JEWELLERY_CALCULATION_OPTIONS || [];
    // Callback for after row amounts calculated (summary update)
    window.afterRowAmountsCalculated = function(row) {
        if (typeof updateSummaryRow === 'function') updateSummaryRow();
        if (typeof updateSummaryPanel === 'function') updateSummaryPanel();
    };
    
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
    window.populateSelect = populateSelect;
    
    // Global variables
    let currentMetalId = null;
    let currentMetalName = '';
    let productTableRowIndex = 0;
    const PRODUCT_MODAL_COLUMNS_PAGE = 'sale-invoice-product-modal';
    // Constants from product-modal-add-item-common.js (already aliased at top)
    const METAL_GROUP_VISIBLE_COLUMNS = window.METAL_GROUP_VISIBLE_COLUMNS || [];
    const METAL_GROUP_PANEL_COLUMNS = window.METAL_GROUP_PANEL_COLUMNS || [];
    const DIAMOND_TAB_HEADER_LABELS = window.DIAMOND_TAB_HEADER_LABELS || {};
    const DIAMOND_CATEGORY_OPTIONS = window.DIAMOND_CATEGORY_OPTIONS || [];
    const DIAMOND_CATEGORY_PLACEHOLDER = window.DIAMOND_CATEGORY_PLACEHOLDER || 'Select Diamond Category';
    // Show/Hide Columns panel: when Diamond & Stones tab, show exactly these columns in this order (label as-is)
    const DIAMOND_TAB_PANEL_COLUMNS = [
        { dataColumn: 'checkbox', label: 'active' },
        { dataColumn: 'id', label: 'Id' },
        { dataColumn: 'rfid', label: 'RFIDCode' },
        { dataColumn: 'short-code', label: 'Item Code' },
        { dataColumn: 'voucher-type', label: 'Style' },
        { dataColumn: 'huid', label: 'HUID No.' },
        { dataColumn: 'barcode', label: 'Barcode No.' },
        { dataColumn: 'design-no', label: 'Design No' },
        { dataColumn: 'location', label: 'Location' },
        { dataColumn: 'category', label: 'Diamond Category' },
        { dataColumn: 'category', label: 'Category' },
        { dataColumn: 'calculation', label: 'Calculation Type' },
        { dataColumn: 'product', label: 'Product' },
        { dataColumn: 'pkt-wt', label: 'Pkt. Wt.' },
        { dataColumn: 'pkt-less-wt', label: 'PKt. Less Wt.' },
        { dataColumn: 'gross-wt', label: 'Gross Wt.' },
        { dataColumn: 'carat', label: 'Metal Carat' },
        { dataColumn: 'stone-weight', label: 'Diamond Carat' },
        { dataColumn: 'less-wt', label: 'D.Weight' },
        { dataColumn: 'net-wt', label: 'Net Wt.' },
        { dataColumn: 'quantity', label: 'Quantity' },
        { dataColumn: 'rate', label: 'Rate' },
        { dataColumn: 'fc-amount', label: 'FC Amount' },
        { dataColumn: 'metal-value', label: 'Metal Value' },
        { dataColumn: 'rapnet-valuation', label: 'RapNet Valuation' },
        { dataColumn: 'setting-charge', label: 'Setting Charge' },
        { dataColumn: 'stone-amount', label: 'Setting Charge Amount' },
        { dataColumn: 'mark-up-amount', label: 'Mark Up Amount' },
        { dataColumn: 'mark-up-per', label: 'Mark Up Per' },
        { dataColumn: 'amount', label: 'Amount' },
        { dataColumn: 'metal-group', label: 'Metal (group)' },
        { dataColumn: 'metal-weight', label: 'Weight' },
        { dataColumn: 'carat', label: 'Carat' },
        { dataColumn: 'purity', label: 'Purity %' },
        { dataColumn: 'purity-wt', label: 'Purity Wt' },
        { dataColumn: 'rate', label: 'Rate' },
        { dataColumn: 'amount', label: 'Amount' },
        { dataColumn: 'gold-loss1', label: 'Loss Wt.' },
        { dataColumn: 'gold-loss2', label: 'Loss Wt. Per' },
        { dataColumn: 'metal-loss-value', label: 'Loss Value' },
        { dataColumn: 'wastage-per', label: 'Wastage Per' },
        { dataColumn: 'wastage-wt', label: 'Wastage Wt' },
        { dataColumn: 'final-wt', label: 'Final Wt.' },
        { dataColumn: 'platinum-group', label: 'Platinum (group)' },
        { dataColumn: 'platinum-weight', label: 'Platinum (group) - Weight' },
        { dataColumn: 'platinum-carat', label: 'Platinum (group) - Carat' },
        { dataColumn: 'platinum-purity', label: 'Platinum (group) - Purity %' },
        { dataColumn: 'platinum-purity-wt', label: 'Platinum (group) - Purity Wt' },
        { dataColumn: 'platinum-rate', label: 'Platinum (group) - Rate' },
        { dataColumn: 'platinum-wastage-per', label: 'Platinum (group) - Wastage Per' },
        { dataColumn: 'platinum-wastage-wt', label: 'Platinum (group) - Wastage Wt' },
        { dataColumn: 'platinum-amount', label: 'Platinum (group) - Amount' },
        { dataColumn: 'min-price', label: 'Minimum Price' },
        { dataColumn: 'minimum', label: 'Minimum Price Code' },
        { dataColumn: 'making-group', label: 'Making (group)' },
        { dataColumn: 'making-type', label: 'Making (group) - Type' },
        { dataColumn: 'making-rate', label: 'Making (group) - Rate' },
        { dataColumn: 'making-amount', label: 'Making (group) - Amount' },
        { dataColumn: 'making-actual-value', label: 'Making (group) - Actual Value' },
        { dataColumn: 'certificate-amount', label: 'Certificate Amount' },
        { dataColumn: 'purchase-amount', label: 'Purchase Amount' },
        { dataColumn: 'sale-amount', label: 'Sale Amount' },
        { dataColumn: 'sale-amount-with', label: 'Sale Amount With Tax' },
        { dataColumn: 'other-amount', label: 'Other Amount' },
        { dataColumn: 'certificate-no', label: 'Certificate No.' },
        { dataColumn: 'certificate-link', label: 'Certificate Link' },
        { dataColumn: 'video-link', label: 'Video Link' },
        { dataColumn: 'cut', label: 'Cut' },
        { dataColumn: 'color', label: 'Color' },
        { dataColumn: 'seive-size', label: 'SeiveSize' },
        { dataColumn: 'size', label: 'Size' },
        { dataColumn: 'shape', label: 'Shape' },
        { dataColumn: 'clarity', label: 'Clarity' },
        { dataColumn: 'discount-group', label: 'Discount (group)' },
        { dataColumn: 'discount-type', label: 'Discount (group) - Type' },
        { dataColumn: 'discount-per', label: 'Discount (group) - Per.' },
        { dataColumn: 'discount-amount', label: 'Discount (group) - Amount' },
        { dataColumn: 'discounted-amt', label: 'Discount (group) - Discounted Amt.' },
        { dataColumn: 'discounted-per', label: 'Discount (group) - Discounted Per.' },
        { dataColumn: 'net-amt', label: 'Net Amt' },
        { dataColumn: 'tax', label: 'Tax' },
        { dataColumn: 'net-amt-tax', label: 'Net Amt+Tax' },
        { dataColumn: 'hallmark-amount', label: 'Hallmark Amount' },
        { dataColumn: 'hallmark-rate', label: 'HallMark Rate' },
        { dataColumn: 'reverse-rate', label: 'Reverse Rate' },
        { dataColumn: 'reverse', label: 'Reverse' },
        { dataColumn: 'actions', label: 'action' }
    ];
    window.productModalColumnVisibilityByTab = window.productModalColumnVisibilityByTab || {};
    window.productModalOriginalHeaderHtml = window.productModalOriginalHeaderHtml || {};
    window.productModalOriginalSettingsDropdownContent = window.productModalOriginalSettingsDropdownContent || null;
    let productModalColumnSaveTimeout = null;
    
    // Add product to Product Selection table (productListBody) by barcode
    // Use same row structure as Add Product: add empty row then populate so column order never mismatches
    function addProductToProductSelectionTable(product) {
        const tbody = document.getElementById('productListBody');
        if (!tbody) {
            console.error('productListBody not found');
            return;
        }
        const emptyRow = tbody.querySelector('tr:not(.product-row)');
        if (emptyRow) emptyRow.remove();
        if (typeof addEmptyProductRow !== 'function') {
            console.error('addEmptyProductRow not found');
            return;
        }
        addEmptyProductRow();
        const row = tbody.querySelector('.product-row:last-of-type');
        if (!row) return;
        if (typeof populateRowWithProduct === 'function') populateRowWithProduct(row, product);
        if (typeof updateJewelleryDiamondCaratFromDiamondAndGemstone === 'function') updateJewelleryDiamondCaratFromDiamondAndGemstone();
    }
    
    // Fetch product by barcode and add to Product Selection table
    function fetchProductByBarcodeAndAdd(barcode) {
        if (!barcode || barcode.trim() === '') {
            return;
        }
        
        const barcodeInput = document.getElementById('modalProductBarcode');
        if (barcodeInput) {
            barcodeInput.style.borderColor = '#c5a864';
        }
        
        // Fetch product by barcode
        fetch('ajax/get-product-by-barcode.php?barcode=' + encodeURIComponent(barcode.trim()))
            .then(response => response.json())
            .then(data => {
                if (data.success && data.product) {
                    // Add product to Product Selection table (productListBody)
                    addProductToProductSelectionTable(data.product);
                    
                    // Clear barcode input
                    if (barcodeInput) {
                        barcodeInput.value = '';
                        barcodeInput.style.borderColor = '';
                        barcodeInput.focus(); // Keep focus for quick scanning
                    }
                } else {
                    alert(data.message || 'Product with barcode "' + barcode + '" not found');
                    if (barcodeInput) {
                        barcodeInput.style.borderColor = '#ef4444';
                        setTimeout(() => {
                            barcodeInput.style.borderColor = '';
                        }, 2000);
                    }
                }
            })
            .catch(error => {
                console.error('Error fetching product by barcode:', error);
                alert('Error fetching product by barcode: ' + error.message);
                if (barcodeInput) {
                    barcodeInput.style.borderColor = '#ef4444';
                    setTimeout(() => {
                        barcodeInput.style.borderColor = '';
                    }, 2000);
                }
            });
    }
    
    // Handle Add Product Icon Click
    $(document).ready(function() {
        // Barcode input - Tab key handler to add product by barcode to Product Selection table
        $(document).on('keydown', '#modalProductBarcode', function(e) {
            if (e.key === 'Tab' && !e.shiftKey) {
                const barcode = $(this).val().trim();
                if (barcode) {
                    e.preventDefault();
                    e.stopPropagation();
                    console.log('Tab pressed on barcode field, barcode:', barcode);
                    fetchProductByBarcodeAndAdd(barcode);
                    // Keep focus on barcode field after adding (for quick scanning)
                    setTimeout(() => {
                        $(this).focus();
                    }, 100);
                }
            }
        });
        
        $(document).on('click', '.add-product-icon', function(e) {
            e.stopPropagation();
            $('#productCreationModal').modal('show');
            // Initialize column dropdown when modal opens
            setTimeout(function() {
                initProductModalColumnDropdown();
            }, 300);
        });
        
        // Handle Add/Edit Customer Icon Click
        // If a customer is selected, open in EDIT mode and prefill data.
        // Otherwise open in ADD mode (blank form).
        $(document).on('click', '#addCustomerBtn, .add-customer-icon', function(e) {
            e.stopPropagation();
            e.preventDefault();
            const cid = parseInt($('#customerId').val() || selectedCustomerId || 0, 10) || 0;
            if (cid > 0) {
                openCustomerModalForEdit(cid);
            } else {
                openCustomerModalForAdd();
            }
        });
        
        // Handle Add Category Icon Click
        $(document).on('click', '.add-category-icon', function(e) {
            e.stopPropagation();
            e.preventDefault();
            $('#categoryCreationModal').modal('show');
            // Load parent categories
            loadParentCategories();
        });
        
        // Hedging: show/hide hedging fields when Fixing Type changes
        function toggleHedgingSection() {
            const fixingType = document.getElementById('fixingType');
            const show = fixingType && fixingType.value === 'Hedging';
            const display = show ? 'block' : 'none';
            const hedgingSection = document.getElementById('hedgingSection');
            const hedgingSectionDate = document.getElementById('hedgingSectionDate');
            if (hedgingSection) hedgingSection.style.display = display;
            if (hedgingSectionDate) hedgingSectionDate.style.display = display;
        }
        $(document).on('change', '#fixingType', toggleHedgingSection);
        $(document).ready(function() { toggleHedgingSection(); });
        
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
        
        // Hide suggestions when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#customerName, #customerSuggestions, #addCustomerBtn').length) {
                $('#customerSuggestions').hide();
            }
        });
        
        // ================== PURCHASE INVOICE SEARCH FUNCTIONALITY ==================
        let saleInvoiceSearchTimeout;
        const saleInvoiceSearchInput = $('#searchSaleInvoice');
        const saleInvoiceSuggestions = $('#saleInvoiceSuggestions');
        
        saleInvoiceSearchInput.on('input', function() {
            const searchTerm = $(this).val().trim();
            
            clearTimeout(saleInvoiceSearchTimeout);
            
            if (searchTerm.length < 2) {
                saleInvoiceSuggestions.hide();
                return;
            }
            
            saleInvoiceSearchTimeout = setTimeout(function() {
                $.ajax({
                    url: 'ajax/search-sale-invoices.php',
                    type: 'GET',
                    data: { q: searchTerm },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success' && response.invoices && response.invoices.length > 0) {
                            let html = '';
                            response.invoices.forEach(function(invoice) {
                                html += `
                                    <div class="invoice-suggestion-item" 
                                         data-invoice-id="${invoice.id}" 
                                         data-invoice-no="${invoice.invoice_no}"
                                         style="padding: 0.75rem; cursor: pointer; border-bottom: 1px solid #f1f5f9; transition: background 0.2s;"
                                         onmouseover="this.style.background='#f8fafc'" 
                                         onmouseout="this.style.background='#fff'">
                                        <div style="font-weight: 600; color: #1e293b; font-size: 0.9rem;">${invoice.invoice_no}</div>
                                        <div style="font-size: 0.85rem; color: #64748b; margin-top: 0.25rem;">${invoice.customer_name || invoice.supplier_name || ''}</div>
                                        ${invoice.formatted_date ? '<div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.15rem;"><i class="feather icon-calendar" style="font-size: 0.7rem;"></i> ' + invoice.formatted_date + '</div>' : ''}
                                        ${invoice.grand_total ? '<div style="font-size: 0.75rem; color: #10b981; margin-top: 0.15rem; font-weight: 500;"><i class="feather icon-dollar-sign" style="font-size: 0.7rem;"></i> ' + invoice.currency + ' ' + parseFloat(invoice.grand_total).toFixed(2) + '</div>' : ''}
                                    </div>
                                `;
                            });
                            
                            saleInvoiceSuggestions.html(html).show();
                        } else {
                            saleInvoiceSuggestions.html('<div style="padding: 0.75rem; color: #94a3b8; text-align: center;">No sale invoices found</div>').show();
                        }
                    },
                    error: function() {
                        saleInvoiceSuggestions.hide();
                    }
                });
            }, 300);
        });
        
        // Handle invoice selection from suggestions
        $(document).on('click', '.invoice-suggestion-item', function() {
            const invoiceId = $(this).data('invoice-id');
            const invoiceNo = $(this).data('invoice-no');
            
            // Clear search input
            saleInvoiceSearchInput.val('');
            saleInvoiceSuggestions.hide();
            
            // Load the invoice
            if (invoiceId) {
                loadOrder(invoiceId);
            }
        });
        
        // Hide suggestions when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#searchSaleInvoice, #saleInvoiceSuggestions').length) {
                saleInvoiceSuggestions.hide();
            }
        });
        
        // Handle keyboard navigation in suggestions
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
                
                // If there's a focused suggestion, select it
                if (focused.length) {
                    focused.click();
                } 
                // If no focused suggestion but there's text in the name field, open customer registration modal
                else if (customerNameValue.length > 0) {
                    // Hide suggestions
                    suggestionsDiv.hide();
                    
                    // Open customer creation modal
                    $('#customerCreationModal').modal('show');
                    
                    // Pre-fill the name field in the modal
                    setTimeout(function() {
                        const ledgerNameField = $('#ledgerName');
                        if (ledgerNameField.length) {
                            ledgerNameField.val(customerNameValue);
                            // Trigger the handleNameInput function if it exists
                            if (typeof handleNameInput === 'function') {
                                handleNameInput(ledgerNameField[0]);
                            }
                            // Focus on the next field or keep focus on name
                            ledgerNameField.focus();
                        }
                    }, 300);
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
            { key: 'carat', label: 'Karat', visible: true },
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
        currentEditingRowId = null;
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
                                        const descCell = row.querySelector('[data-column="product"] a');
                                        if (descCell) {
                                            descCell.textContent = data.product.name || '';
                                        }
                                        // Update Tax % from product VAT if the row has a tax-percent input
                                        const taxPercentInput = row.querySelector('[data-column="tax-percent"] input');
                                        if (taxPercentInput) {
                                            var taxPct = (data.product.total_tax_percent != null && data.product.total_tax_percent !== '') ? data.product.total_tax_percent : ((data.product.vat_value != null && data.product.vat_value !== '') ? data.product.vat_value : '');
                                            if (taxPct !== '') taxPercentInput.value = taxPct;
                                        }
                                    }
                                });
                        }
                    }
                    currentEditingRowId = null;
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
    
    // Product List columns (same order as Product Selection / thead)
    var PRODUCT_LIST_COLUMNS = ['product','short-code','rfid','voucher-type','photo','barcode','design-no','huid','category','calculation','location','quantity','carat','pkt-wt','pkt-less-wt','requested-purity','requested','gross-wt','less-wt','gold-loss1','gold-loss2','setting-charge','net-wt','purity','purity-wt','wastage-per','wastage-wt','final-wt','alloy-wt','rate','metal-value','metal-cost','amount','metal-qty','metal-weight','discount-type','discount-per','discount-amount','discount','making-type','making-rate','making-amount','making-cost','min-price','stone-charge-type','stone-weight','stone-rate','stone-amount','stone-cost','diamond-amount','purchase-amount','sale-amount','sale-amount-with','net-amt','tax-type','tax-percent','tax','other-charge-type','other-weight','other-rate','other-info','other-amount','hallmark-amount','hallmark-rate','net-amt-tax','reverse'];
    
    function getProductListRowCells(data, opts) {
        opts = opts || {};
        var groupImage = opts.groupImage || '';
        function val(k) { return data[k] != null && data[k] !== '' ? data[k] : ''; }
        function num(k, def) { def = def || 0; var n = parseFloat(data[k]); return isNaN(n) ? def : n; }
        function fmtNum(v, d) { d = d === undefined ? 2 : d; return (parseFloat(v) || 0).toFixed(d); }
        function cell(col, content, style, cls) {
            style = style || 'text-align: right; color: #11294b;';
            if (col === 'product') { style = 'cursor: pointer; color: #11294b;'; cls = (cls || '') + ' product-select-cell'; }
            if (col === 'photo') style = 'text-align: center; vertical-align: middle;';
            if (col === 'barcode') style = 'text-align: center;';
            return '<td data-column="' + col + '"' + (cls ? ' class="' + cls.trim() + '"' : '') + ' style="' + style + '">' + content + '</td>';
        }
        var cells = [];
        var colToKey = { 'product': 'product_name', 'short-code': 'short_code', 'voucher-type': 'voucher_type', 'design-no': 'design_no', 'pkt-wt': 'pkt_wt', 'pkt-less-wt': 'pkt_less_wt', 'requested-purity': 'requested_purity', 'gross-wt': 'gross_wt', 'less-wt': 'less_wt', 'gold-loss1': 'gold_loss1', 'gold-loss2': 'gold_loss2', 'setting-charge': 'setting_charge', 'net-wt': 'net_wt', 'purity-wt': 'pure_wt', 'wastage-per': 'wastage_per', 'wastage-wt': 'wastage_wt', 'final-wt': 'final_wt', 'alloy-wt': 'alloy_wt', 'metal-value': 'metal_value', 'metal-cost': 'metal_cost', 'discount-type': 'discount_type', 'discount-per': 'discount_per', 'discount-amount': 'discount_amount', 'making-type': 'making_type', 'making-rate': 'making_rate', 'making-amount': 'making_amount', 'making-cost': 'making_cost', 'min-price': 'min_price', 'stone-charge-type': 'stone_charge_type', 'stone-weight': 'stone_weight', 'stone-rate': 'stone_rate', 'stone-amount': 'stone_amount', 'stone-cost': 'stone_cost', 'diamond-amount': 'diamond_amount', 'purchase-amount': 'purchase_amount', 'sale-amount': 'sale_amount', 'sale-amount-with': 'sale_amount_with', 'net-amt': 'net_amt', 'tax-type': 'tax_type', 'tax-percent': 'tax_percent', 'other-charge-type': 'other_charge_type', 'other-weight': 'other_weight', 'other-rate': 'other_rate', 'other-info': 'other_info', 'other-amount': 'other_amount', 'hallmark-amount': 'hallmark_amount', 'hallmark-rate': 'hallmark_rate', 'net-amt-tax': 'net_amt_tax', 'calculation': 'calculation_type' };
        var colOrder = (typeof window.getProductTableColumnOrder === 'function' && window.getProductTableColumnOrder()) ? window.getProductTableColumnOrder() : PRODUCT_LIST_COLUMNS;
        for (var i = 0; i < colOrder.length; i++) {
            var col = colOrder[i];
            var key = colToKey[col] || col.replace(/-/g, '_');
            var content = '';
            if (col === 'photo') {
                content = groupImage ? '<img src="' + escapeHtml(groupImage) + '" alt="Group" style="max-width: 50px; max-height: 50px; object-fit: contain;">' : '<span class="text-muted" style="font-size: 0.75rem;">—</span>';
            } else if (col === 'product') {
                content = '<a href="javascript:void(0)" style="color: #11294b; text-decoration: underline;">' + escapeHtml(val(key)) + '</a>';
            } else if (col === 'quantity' || col === 'gross-wt' || col === 'less-wt' || col === 'purity' || col === 'final-wt') {
                var v = col === 'quantity' ? fmtNum(data[key], 2) : fmtNum(data[key], 3);
                var f = col === 'quantity' ? 'quantity' : (col === 'gross-wt' ? 'gross_wt' : col === 'less-wt' ? 'less_wt' : col === 'purity' ? 'purity' : 'final_wt');
                content = '<input type="text" class="form-control form-control-sm editable-field" data-field="' + f + '" value="' + v + '" step="' + (col === 'quantity' ? '0.01' : '0.001') + '" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">';
            } else if (col === 'design-no') {
                content = '<input type="text" class="form-control form-control-sm editable-field" data-field="design_no" value="' + escapeHtml(val(key)) + '" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">';
            } else if (col === 'making-amount') {
                content = '<input type="text" class="form-control form-control-sm editable-field" data-field="making" value="' + fmtNum(data[key] || data.making_amount, 2) + '" step="0.01" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">';
            } else if (col === 'stone-amount' || col === 'other-amount' || col === 'diamond-amount') {
                var field = col === 'stone-amount' ? 'stone_charges' : col === 'other-amount' ? 'other_charges' : 'diamond_value';
                content = '<input type="text" class="form-control form-control-sm editable-field" data-field="' + field + '" value="' + fmtNum(data[key] || data.stone_amount || data.other_amount || data.diamond_amount, 2) + '" step="0.01" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">';
            } else if (col === 'tax') {
                content = '<input type="text" class="form-control form-control-sm editable-field" data-field="tax" value="' + fmtNum(data[key], 2) + '" step="0.01" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">';
            } else if (col === 'amount') {
                content = '<span style="font-weight: 600;">' + escapeHtml(fmtNum(data[key], 2)) + '</span>';
            } else if (col === 'metal-qty') {
                content = '<span style="font-size: 0.75rem; color: #11294b;">' + escapeHtml(fmtNum(data.metal_qty != null && data.metal_qty !== '' ? data.metal_qty : 1, 2)) + '</span>';
            } else if (col === 'metal-weight') {
                content = '<span style="font-size: 0.75rem; color: #11294b;">' + escapeHtml(fmtNum(data.metal_weight, 3)) + '</span>';
            } else if (col === 'barcode') {
                content = '<span style="font-size: 0.75rem; color: #11294b; font-weight: 500;">' + escapeHtml(data[key] != null ? String(data[key]) : '') + '</span>';
            } else {
                var isWeight = (key.indexOf('_wt') !== -1 || key === 'pkt_wt' || key === 'pkt_less_wt' || key === 'wastage_wt' || key === 'alloy_wt');
                var isNumeric = (key.indexOf('amount') !== -1 || key.indexOf('value') !== -1 || key.indexOf('rate') !== -1 || key === 'quantity' || key === 'tax' || key === 'purity' || key === 'carat' || key === 'requested_purity' || key === 'requested' || key === 'tax_percent' || key === 'wastage_per');
                if (isWeight) content = fmtNum(data[key], 3);
                else if (isNumeric) content = fmtNum(data[key], 2);
                else content = escapeHtml(data[key] != null && data[key] !== '' ? String(data[key]) : '');
            }
            cells.push(cell(col, content));
        }
        return cells;
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
        const hiddenId = document.getElementById('ledgerCustomerId');
        if (hiddenId) hiddenId.value = '';
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

    function setCustomerModalMode(mode) {
        const label = document.getElementById('customerCreationModalLabel');
        const saveBtn = document.getElementById('customerModalSaveBtn');
        if (mode === 'edit') {
            if (label) label.textContent = 'Edit Customer';
            if (saveBtn) saveBtn.textContent = 'Update';
        } else {
            if (label) label.textContent = 'Ledger Details';
            if (saveBtn) saveBtn.textContent = 'Save';
        }
    }

    function openCustomerModalForAdd() {
        clearCustomerForm();
        setCustomerModalMode('add');
        $('#customerCreationModal').modal('show');
    }

    async function openCustomerModalForEdit(customerId) {
        clearCustomerForm();
        setCustomerModalMode('edit');
        $('#customerCreationModal').modal('show');
        try {
            const res = await fetch('ajax/get-customer.php?customer_id=' + encodeURIComponent(customerId), { method: 'GET' });
            const data = await res.json();
            if (!data || !(data.status === 'success' || data.success === true) || !data.customer) {
                alert('Failed to load customer details');
                return;
            }
            fillCustomerForm(data.customer);
        } catch (err) {
            console.error(err);
            alert('Error loading customer details');
        }
    }

    function fillCustomerForm(c) {
        // Set hidden ID for update
        const hiddenId = document.getElementById('ledgerCustomerId');
        if (hiddenId) hiddenId.value = c.id || '';

        // Basic fields
        const setVal = (id, val) => { const el = document.getElementById(id); if (el != null) el.value = (val ?? ''); };
        setVal('ledgerName', c.name || '');
        setVal('ledgerAlternateName', c.alternate_name || '');
        setVal('ledgerFirstName', c.first_name || '');
        setVal('ledgerLastName', c.last_name || '');
        setVal('mobileCountryCode', c.mobile_country_code || '971');
        setVal('ledgerMobileNo', c.mobile_no || '');
        setVal('ledgerPhoneNo', c.phone_no || '');
        setVal('ledgerMailId', c.mail_id || '');
        setVal('ledgerIdentityNo', c.identity_no || '');
        setVal('ledgerNationalId', c.national_id || '');
        setVal('ledgerTradeNo', c.trade_no || '');
        setVal('identityIssueDate', c.identity_issue_date || '');
        setVal('identityExpiryDate', c.identity_expiry_date || '');
        setVal('specialDay', c.special_day || '');
        setVal('customerType', c.customer_type_id || '');
        setVal('registrationNo', c.registration_no || '');
        setVal('registrationDate', c.registration_date || '');
        setVal('nationality', c.nationality_id || '');
        setVal('country', c.country_id || '');
        setVal('ledgerGroup', c.group_id || '');
        setVal('ledgerSundryDebtors', c.sundry_debtors_id || '');
        setVal('billingAddress1', c.billing_address1 || '');
        setVal('billingAddress2', c.billing_address2 || '');
        setVal('billingCountry', c.billing_country || '');
        setVal('billingState', c.billing_state || '');
        setVal('billingZipCode', c.billing_zip_code || '');
        setVal('shippingAddress1', c.shipping_address1 || '');
        setVal('shippingAddress2', c.shipping_address2 || '');
        setVal('shippingCountry', c.shipping_country || '');
        setVal('shippingState', c.shipping_state || '');
        setVal('shippingZipCode', c.shipping_zip_code || '');
        setVal('bankAccountNo', c.bank_account_no || '');
        setVal('bankName', c.bank_name || '');
        setVal('bankIfscCode', c.bank_ifsc_code || '');
        setVal('bankBranch', c.bank_branch || '');
        setVal('ledgerNotes', c.notes || '');

        // Checkboxes / radios
        const cap = document.getElementById('ledgerNameCapital'); if (cap) cap.checked = String(c.ledger_name_capital || '0') === '1';
        const kyc = document.getElementById('ledgerKYC'); if (kyc) kyc.checked = String(c.kyc || '0') === '1';
        const aml = document.getElementById('ledgerAML'); if (aml) aml.checked = String(c.aml || '0') === '1';
        const btbYes = document.getElementById('billToBillYes');
        const btbNo = document.getElementById('billToBillNo');
        const btb = String(c.bill_to_bill || '0') === '1';
        if (btbYes) btbYes.checked = btb;
        if (btbNo) btbNo.checked = !btb;

        // Photo preview (if exists)
        if (c.ledger_photo) {
            const prev = document.getElementById('ledgerPhotoPreview');
            const img = document.getElementById('ledgerPhotoImg');
            if (prev && img) {
                prev.style.display = 'block';
                img.src = c.ledger_photo;
            }
        }

        // Share holders
        try {
            const body = document.getElementById('shareHoldersTableBody');
            if (body) body.innerHTML = '';
            shareHolderRowIndex = 0;
            shareHoldersData = [];
            const holders = Array.isArray(c.share_holders) ? c.share_holders : [];
            holders.forEach(function(h) {
                addShareHolderRow();
                const row = document.getElementById('shareHolderRow_' + shareHolderRowIndex);
                if (row) {
                    const nameInput = row.querySelector('input[type=\"text\"]');
                    const natSel = row.querySelector('select');
                    const perInput = row.querySelector('input[name*="share_percentage"]');
                    if (nameInput) nameInput.value = h.name || '';
                    if (natSel) natSel.value = h.nationality_id || '';
                    if (perInput) perInput.value = h.share_percentage != null ? h.share_percentage : '';
                }
            });
        } catch (e) {
            console.error('Share holders prefill failed', e);
        }
    }
    
    function saveCustomer() {
        const form = document.getElementById('customerCreationForm');
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        // Validate mobile number is not empty
        const mobileNo = document.getElementById('ledgerMobileNo').value.trim();
        if (!mobileNo || mobileNo === '') {
            alert('Mobile number is required');
            document.getElementById('ledgerMobileNo').focus();
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
                
                // Update the customer in the main form (keep selected customer id)
                if (data.customer_name && document.getElementById('customerName')) {
                    document.getElementById('customerName').value = data.customer_name;
                }
                if (data.customer_id && document.getElementById('customerId')) {
                    document.getElementById('customerId').value = data.customer_id;
                    selectedCustomerId = data.customer_id;
                }
                
                // Clear the form
                clearCustomerForm();
            } else {
                // Show error message with proper styling
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
    
    // Filter products in the modal table by metal ID (show/hide only - never remove rows so products persist when switching tabs)
    function filterProductsByMetal(metalId) {
        const tbody = document.getElementById('productListBody');
        if (!tbody) return;
        
        const allRows = tbody.querySelectorAll('tr.product-row');
        let visibleCount = 0;
        
        allRows.forEach(function(row) {
            const rowMetalId = row.getAttribute('data-metal-id');
            if (!rowMetalId || rowMetalId === metalId) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        const placeholderRow = tbody.querySelector('tr.no-category-products-placeholder');
        if (visibleCount === 0) {
            // Remove any other non-product rows (e.g. initial "Click Add Product") so only one placeholder remains
            tbody.querySelectorAll('tr:not(.product-row)').forEach(function(tr) {
                if (!tr.classList.contains('no-category-products-placeholder')) tr.remove();
            });
            if (!placeholderRow) {
                const tr = document.createElement('tr');
                tr.className = 'no-category-products-placeholder';
                tr.innerHTML = '<td colspan="70" class="text-center text-muted py-4">No products found for this category</td>';
                tbody.appendChild(tr);
            }
        } else {
            if (placeholderRow) placeholderRow.remove();
            // Also remove any stray initial-style placeholder (no class) so no gaps
            tbody.querySelectorAll('tr:not(.product-row)').forEach(function(tr) {
                tr.remove();
            });
        }
    }
    
    // Initialize category tabs (only in modal) - use single delegated listener so tabs work and we don't stack handlers
    function initCategoryTabs() {
        const modal = document.getElementById('productSelectionModal');
        if (!modal) return;
        
        // Attach one delegated click listener so Silver/Platinum etc. all work; avoid duplicate handlers
        if (!modal._categoryTabsInited) {
            modal._categoryTabsInited = true;
            modal.addEventListener('click', function(e) {
                const btn = e.target.closest('.category-tab-btn');
                if (!btn || !modal.contains(btn)) return;
                e.preventDefault();
                e.stopPropagation();
                // Only update tabs inside this modal
                modal.querySelectorAll('.category-tab-btn').forEach(function(b) {
                    b.classList.remove('active');
                });
                btn.classList.add('active');
                currentMetalId = btn.getAttribute('data-metal-id');
                currentMetalName = btn.getAttribute('data-metal-name');
                filterProductsByMetal(currentMetalId);
                var isDiamond = (typeof currentMetalName === 'string' && currentMetalName.toLowerCase().indexOf('diamond') !== -1);
                var modalEl = document.getElementById('productSelectionModal');
                var scrollWrap = document.getElementById('productListTableScrollWrapper');
                var outerWrap = document.getElementById('productListTableOuter');
                if (modalEl) {
                    if (isDiamond) {
                        modalEl.classList.remove('product-modal-metal-tab');
                        if (scrollWrap) { scrollWrap.style.width = ''; scrollWrap.style.maxWidth = ''; }
                        if (outerWrap) { outerWrap.style.width = ''; outerWrap.style.maxWidth = ''; }
                    } else {
                        modalEl.classList.add('product-modal-metal-tab');
                        if (scrollWrap) { scrollWrap.style.width = ''; scrollWrap.style.maxWidth = ''; }
                        if (outerWrap) { outerWrap.style.width = ''; outerWrap.style.maxWidth = ''; }
                    }
                }
                // Show Diamond Category filter only on Diamond & Stones tab
                var filterRow = document.getElementById('modalDiamondCategoryFilterRow');
                if (filterRow) {
                    filterRow.style.display = (currentMetalName && currentMetalName.toLowerCase().indexOf('diamond') !== -1) ? '' : 'none';
                }
                // Apply tab-wise column visibility (Gold vs Silver etc. each have their own)
                if (typeof applyProductModalColumnVisibilityForTab === 'function') {
                    applyProductModalColumnVisibilityForTab(currentMetalId || '');
                }
                // Apply stored group image for this tab if any
                var storedImage = window.productModalGroupImageByTab && window.productModalGroupImageByTab[currentMetalId || ''];
                if (storedImage && typeof applyProductModalGroupImageToPhotoColumns === 'function') {
                    applyProductModalGroupImageToPhotoColumns(storedImage, currentMetalId || '');
                }
            });
        }
        
        const firstTab = modal.querySelector('.category-tab-btn.active');
        if (firstTab) {
            currentMetalId = firstTab.getAttribute('data-metal-id');
            currentMetalName = firstTab.getAttribute('data-metal-name');
            var filterRow = document.getElementById('modalDiamondCategoryFilterRow');
            if (filterRow) {
                filterRow.style.display = (currentMetalName && currentMetalName.toLowerCase().indexOf('diamond') !== -1) ? '' : 'none';
            }
            // Apply column visibility for initial tab
            if (typeof applyProductModalColumnVisibilityForTab === 'function' && currentMetalId) {
                applyProductModalColumnVisibilityForTab(currentMetalId);
            }
        }
        
        const tbody = document.getElementById('productListBody');
        if (tbody && tbody.querySelectorAll('tr.product-row').length === 0) {
            const placeholder = tbody.querySelector('tr.no-category-products-placeholder, tr:not(.product-row)');
            if (!placeholder) {
                tbody.innerHTML = '<tr class="no-category-products-placeholder"><td colspan="70" class="text-center text-muted py-4">Click "Add Product" or select a category tab to add products</td></tr>';
            }
        }
    }
    
    // Switch to metal tab by metal_id (for edit mode - open Diamond tab when items were saved in Diamond & Stones)
    function switchToMetalTab(metalId) {
        if (!metalId) return;
        var modal = document.getElementById('productSelectionModal');
        if (!modal) return;
        var tabBtn = modal.querySelector('.category-tab-btn[data-metal-id="' + metalId + '"]');
        if (tabBtn) {
            modal.querySelectorAll('.category-tab-btn').forEach(function(b) { b.classList.remove('active'); });
            tabBtn.classList.add('active');
            currentMetalId = metalId;
            currentMetalName = tabBtn.getAttribute('data-metal-name') || '';
            if (typeof filterProductsByMetal === 'function') filterProductsByMetal(currentMetalId);
            var isDiamond = (typeof currentMetalName === 'string' && currentMetalName.toLowerCase().indexOf('diamond') !== -1);
            if (isDiamond) {
                modal.classList.remove('product-modal-metal-tab');
            } else {
                modal.classList.add('product-modal-metal-tab');
            }
            var filterRow = document.getElementById('modalDiamondCategoryFilterRow');
            if (filterRow) filterRow.style.display = isDiamond ? '' : 'none';
            if (typeof applyProductModalColumnVisibilityForTab === 'function') applyProductModalColumnVisibilityForTab(currentMetalId || '');
        }
    }
    window.switchToMetalTab = switchToMetalTab;
    
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
            // Clear the table body to show blank state (ready for new products)
            const tbody = document.getElementById('productListBody');
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="77" class="text-center text-muted py-4">Click "Add Product" button to add products for billing...</td></tr>';
            }
            var groupNameInput = document.getElementById('modalGroupName');
            if (groupNameInput) groupNameInput.value = '';
            var commentInput = document.getElementById('modalComment');
            if (commentInput) commentInput.value = '';
        } catch(error) {
            console.error('Error opening product modal:', error);
            alert('Error opening product selection: ' + error.message);
        }
    }
    window.openProductModal = openProductModal;
    
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
            <td data-column="barcode" style="text-align: center;">
                <div class="image-placeholder" style="width: 30px; height: 30px; background: #f1f5f9; border-radius: 4px; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                    <i class="feather icon-image" style="font-size: 0.9rem; color: #94a3b8;"></i>
                </div>
            </td>
            <td data-column="description" class="product-select-cell" style="cursor: pointer; color: #11294b; position: relative;">
                <a href="javascript:void(0)" style="color: #94a3b8; font-style: italic; text-decoration: underline;">Click to select product</a>
            </td>
            <td data-column="quantity" style="text-align: right; color: #11294b;">1.00</td>
            <td data-column="gross-wt" style="text-align: right; color: #11294b;">0.0</td>
            <td data-column="final-wt" style="text-align: right; color: #11294b;">0.0</td>
            <td data-column="net-wt" style="text-align: right; color: #11294b;">0.0</td>
            <td data-column="pure-wt" style="text-align: right; color: #11294b;">0.0</td>
            <td data-column="making" style="text-align: right; color: #11294b;">0</td>
            <td data-column="design-no" style="text-align: right; color: #11294b;">0</td>
            <td data-column="tax" style="text-align: right; color: #11294b;">0</td>
            <td data-column="amount" style="text-align: right; font-weight: 600; color: #11294b;">0.00</td>
            <td data-column="net-amt" style="text-align: right; color: #11294b;">0.00</td>
            <td data-column="net-amt-tax" style="text-align: right; color: #11294b;">0.00</td>
            <td data-column="stone-charges" style="text-align: right; color: #11294b;">0.00</td>
            <td data-column="other-charges" style="text-align: right; color: #11294b;">0.00</td>
            <td data-column="diamond-value" style="text-align: right; color: #11294b;">0.00</td>
            <td data-column="gemstone-value" style="text-align: right; color: #11294b;">0.00</td>
            <td>
                <div class="action-btns">
                    <button type="button" class="btn-edit" onclick="editProductRow('${rowId}')" title="Edit">
                        <i class="feather icon-edit"></i>
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
    function addEmptyProductRow() {
        const tbody = document.getElementById('productListBody');
        if (!tbody) return;
        
        // Remove ALL placeholder/non-product rows (avoids gaps after switching tabs: Gold -> Silver -> Platinum -> Gold)
        tbody.querySelectorAll('tr:not(.product-row)').forEach(function(tr) {
            tr.remove();
        });
        
        // Create a new empty product row (tied to current tab so no gaps when switching tabs)
        const row = document.createElement('tr');
        row.className = 'product-row';
        row.setAttribute('data-product-id', '');
        row.setAttribute('data-characteristic-id', '');
        row.setAttribute('data-metal-id', currentMetalId || '');
        
        // Generate the row HTML with all columns (empty values)
        row.innerHTML = `
            <td data-column="checkbox" style="text-align: center; background: #fff;">
                <input type="checkbox" class="product-checkbox" data-product-id="" data-characteristic-id="">
            </td>
            <td data-column="id"></td>
            <td data-column="rfid"><input type="text" class="form-control form-control-sm" value="" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="voucher-type"><input type="text" class="form-control form-control-sm" value="" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="photo" class="product-row-photo" style="text-align: center; vertical-align: middle; min-width: 70px;"><img src="" alt="" class="product-photo-thumb" style="max-width: 50px; max-height: 50px; display: none;"><span class="product-photo-placeholder text-muted" style="font-size: 0.65rem;">—</span></td>
            <td data-column="barcode"><input type="text" class="form-control form-control-sm" value="" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="design-no"><input type="text" class="form-control form-control-sm" value="" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="huid"><input type="text" class="form-control form-control-sm" value="" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="category"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="">Select</option></select></td>
            <td data-column="calculation"><select class="form-control form-control-sm" style="width: 120px; font-size: 0.7rem;"><option value="Carat X Rate">Carat X Rate</option><option value="Rate X Gross Wt" selected>Rate X Gross Wt</option><option value="Rate X Purity Wt">Rate X Purity Wt</option><option value="Rate X Net Wt">Rate X Net Wt</option><option value="Rate X Final Wt">Rate X Final Wt</option><option value="Fix">Fix</option><option value="Stone Charge">Stone Charge</option><option value="Attach Image Type">Attach Image Type</option></select></td>
            <td data-column="product"><input type="text" class="form-control form-control-sm" value="" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="location"><select class="form-control form-control-sm location-select" style="width: 100px; font-size: 0.7rem;"><option value="">Select</option></select></td>
            <td data-column="pkt-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="pkt-less-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="gross-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="stone-weight"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 110px; font-size: 0.7rem;"></td>
            <td data-column="less-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="net-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" readonly style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="quantity"><input type="text" class="form-control form-control-sm" value="1" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="rate"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="metal-qty"><input type="text" class="form-control form-control-sm" value="1" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="metal-weight"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="carat"><select class="form-control form-control-sm carat-select" style="width: 80px; font-size: 0.7rem;"><option value="">Select</option></select></td>
            <td data-column="purity"><input type="text" class="form-control form-control-sm" value="1" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="purity-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" readonly style="width: 90px; font-size: 0.7rem;"></td>
            <td data-column="gold-loss1"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="gold-loss2"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="metal-loss-value"><input type="text" class="form-control form-control-sm" value="0.000" step="0.001" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="wastage-per"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="wastage-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="metal-rate"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 90px; font-size: 0.7rem;"></td>
            <td data-column="metal-value"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="metal-cost"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem; color: #7b1fa2;"></td>
            <td data-column="requested-purity"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 120px; font-size: 0.7rem;"></td>
            <td data-column="requested"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="setting-charge"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 110px; font-size: 0.7rem;"></td>
            <td data-column="final-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="alloy-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="discount-type"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="On Amount" selected>On Amount</option><option value="On Percentage">On Percentage</option></select></td>
            <td data-column="discount-per"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="discount-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="discount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="discount-type2"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="On Amount" selected>On Amount</option><option value="On Percentage">On Percentage</option></select></td>
            <td data-column="discount-per2"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="discount-amount2"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="discounted-amt"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
            <td data-column="discounted-per"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 120px; font-size: 0.7rem;"></td>
            <td data-column="making-type"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="Fix" selected>Fix</option><option value="Per Gram">Per Gram</option><option value="Per Piece">Per Piece</option><option value="Per Kilogram">Per Kilogram</option><option value="Per Percent">Per Percent</option><option value="MRP">MRP</option><option value="M.KT">M.KT</option></select></td>
            <td data-column="making-rate"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="making-discount-amt"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 130px; font-size: 0.7rem;"></td>
            <td data-column="making-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="making-actual-value"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
            <td data-column="making-cost"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 110px; font-size: 0.7rem;"></td>
            <td data-column="min-price"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="minimum"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="stone-charge-type"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="Fix" selected>Fix</option><option value="Per Gram">Per Gram</option></select></td>
            <td data-column="stone-rate"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="stone-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
            <td data-column="stone-cost"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="diamond-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 120px; font-size: 0.7rem;"></td>
            <td data-column="purchase-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 130px; font-size: 0.7rem;"></td>
                        <td data-column="sale-percent"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;" placeholder="%" title="Sale % on Purchase Amount"></td>            <td data-column="sale-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 110px; font-size: 0.7rem;"></td>
<td data-column="sale-amount-with"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 130px; font-size: 0.7rem;"></td>
            <td data-column="net-amt"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="tax-type"><select class="form-control form-control-sm" style="width: 120px; font-size: 0.7rem;"><option value="tax_on_making">Tax on making</option><option value="tax_of_netamount">Tax of net amount</option><option value="no_tax" selected>No tax</option></select></td>
            <td data-column="tax-percent"><input type="text" class="form-control form-control-sm" value="0" min="0" max="100" step="0.01" readonly style="width: 70px; font-size: 0.7rem; background: #f1f5f9; cursor: not-allowed;" title="From product opening (read-only)"></td>
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

        // Reorder row cells to match current modal header order (after user drag-and-drop columns)
        if (typeof reorderModalRowCellsToMatchHeader === 'function') {
            reorderModalRowCellsToMatchHeader(row);
        }

        // Apply current tab column visibility so hidden columns stay hidden on new row
        if (tbody.id === 'productListBody' && typeof applyProductModalColumnVisibilityForTab === 'function') {
            applyProductModalColumnVisibilityForTab(currentMetalId || '');
        }
        
        // Tax % is read-only; filled from product opening when product is selected (no default)
        
        // Populate dropdowns
        const caratSelect = row.querySelector('.carat-select');
        if (caratSelect) {
            populateSelect(caratSelect, carats, 'id', 'name', 'Select Karat');
        }
        
        const locationSelect = row.querySelector('.location-select');
        if (locationSelect) {
            populateSelect(locationSelect, locations, 'id', 'name', 'Select Location');
        }
        
        // Populate category dropdown: Diamond tab = Diamond Category (Diamonds/GemStones/Jewellery), else Select Category
        const categorySelect = row.querySelector('[data-column="category"] select');
        if (categorySelect) {
            populateCategorySelectForModal(categorySelect, isDiamondTabActive());
        }
        
        // Add calculation type change listener; Diamond tab = only Carat X Rate, Fix
        const calculationSelect = row.querySelector('[data-column="calculation"] select');
        if (calculationSelect) {
            if (typeof applyCalculationSelectOptionsForTab === 'function') applyCalculationSelectOptionsForTab(calculationSelect, isDiamondTabActive());
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
        if (typeof updateJewelleryDiamondCaratFromDiamondAndGemstone === 'function') updateJewelleryDiamondCaratFromDiamondAndGemstone();
        
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
        // Use current tab's metal; on Diamond & Stones tab, filter by row's Diamond Category (Diamonds/GemStones/Jewellery)
        var metalIdForSearch = null;
        if (row != null && typeof currentMetalId !== 'undefined') {
            metalIdForSearch = currentMetalId;
            var diamondCategory = '';
            if (row) {
                var categorySelect = row.querySelector('[data-column="category"] select');
                diamondCategory = categorySelect ? (categorySelect.value || '').trim() : '';
            }
            window.productSearchDiamondCategory = diamondCategory || '';
            // Keep metal = current tab (Diamond & Stones). Products like Gold Bar have Jewellery on their Diamond & Stones characteristic, not on Gold metal.
        }
        window.productSearchMetalId = metalIdForSearch;
        
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
    
    // Search products via AJAX (filter by current tab metal when opened from Product Selection modal)
    function searchProducts(searchTerm) {
        const resultsDiv = document.getElementById('productSearchResults');
        resultsDiv.innerHTML = '<div class="text-muted text-center" style="padding: 20px;">Searching...</div>';
        
        let url = 'ajax/search-products.php?search=' + encodeURIComponent(searchTerm) + '&limit=50';
        const metalId = (typeof window.productSearchMetalId !== 'undefined') ? window.productSearchMetalId : (typeof currentMetalId !== 'undefined' ? currentMetalId : null);
        if (metalId) {
            url += '&metal_id=' + encodeURIComponent(metalId);
        }
        const diamondCat = (typeof window.productSearchDiamondCategory !== 'undefined') ? (window.productSearchDiamondCategory || '') : '';
        if (diamondCat && ['Diamonds', 'GemStones', 'Jewellery'].indexOf(diamondCat) !== -1) {
            url += '&diamond_category=' + encodeURIComponent(diamondCat);
        }
        
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
        
        const row = currentProductRow;
        // Populate row with product data
        populateRowWithProduct(row, product);
        
        // Close modal
        closeProductSearchModal();
        
        // Move focus to next column (Location) so Tab goes there after selecting product
        setTimeout(function() {
            const locationSelect = row.querySelector('[data-column="location"] select, .location-select');
            if (locationSelect) {
                locationSelect.focus();
            }
        }, 50);
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
        row.setAttribute('data-metal-id', (typeof currentMetalId !== 'undefined' ? currentMetalId : '') || product.metal_id || '');
        
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
        
        // Update Design No
        const designNoInput = row.querySelector('[data-column="design-no"] input');
        if (designNoInput && product.article) {
            designNoInput.value = product.article;
        }
        
        // Gross Weight: do not fetch from product; keep default 0 when product is selected
        const grossWtInput = row.querySelector('[data-column="gross-wt"] input');
        if (grossWtInput) {
            grossWtInput.value = '0';
        }
        
        // Update Purity
        const purityInput = row.querySelector('[data-column="purity"] input');
        if (purityInput && product.opening_purity) {
            purityInput.value = product.opening_purity;
        }
        
        // Update Barcode
        const barcodeInput = row.querySelector('[data-column="barcode"] input');
        if (barcodeInput && product.barcode) {
            barcodeInput.value = product.barcode;
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
        
        // Update Tax % from product opening VAT (product-wise)
        const taxPercentInput = row.querySelector('[data-column="tax-percent"] input');
        if (taxPercentInput) {
            var taxPct = (product.total_tax_percent != null && product.total_tax_percent !== '') ? product.total_tax_percent : ((product.vat_value != null && product.vat_value !== '') ? product.vat_value : '');
            if (taxPct !== '') taxPercentInput.value = taxPct;
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
    
    // Load products by metal (diamondCategory: optional filter for Diamond & Stones tab - 'Diamonds', 'GemStones', 'Jewellery')
    function loadProducts(metalId, search = '', diamondCategory = '') {
        const tbody = document.getElementById('productListBody');
        tbody.innerHTML = '<tr><td colspan="70" class="text-center text-muted py-4">Loading products...</td></tr>';
        
        var ajaxData = { metal_id: metalId, search: search };
        if (diamondCategory && ['Diamonds', 'GemStones', 'Jewellery'].indexOf(diamondCategory) !== -1) {
            ajaxData.diamond_category = diamondCategory;
        }
        // Use jQuery if available, otherwise use fetch
        if (typeof jQuery !== 'undefined' && jQuery.ajax) {
            jQuery.ajax({
            url: 'ajax/get-products-by-metal.php',
            type: 'GET',
            data: ajaxData,
            dataType: 'json',
            success: function(response) {
                if (response.success && response.products.length > 0) {
                    let html = '';
                    response.products.forEach(function(product) {
                        const productName = product.name + (product.metal_name ? ' - ' + product.metal_name : '');
                        html += `
                            <tr class="product-row" data-product-id="${product.id}" data-characteristic-id="${product.characteristic_id || ''}">
                                <td data-column="checkbox" style="text-align: center; background: #fff;">
                                    <input type="checkbox" class="product-checkbox" data-product-id="${product.id}" data-characteristic-id="${product.characteristic_id || ''}">
                                </td>
                                <td data-column="id">${product.id || ''}</td>
                                <td data-column="rfid"><input type="text" class="form-control form-control-sm" value="" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="voucher-type"><input type="text" class="form-control form-control-sm" value="" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="photo" class="product-row-photo" style="text-align: center; vertical-align: middle; min-width: 70px;"><img src="" alt="" class="product-photo-thumb" style="max-width: 50px; max-height: 50px; display: none;"><span class="product-photo-placeholder text-muted" style="font-size: 0.65rem;">—</span></td>
                                <td data-column="barcode"><input type="text" class="form-control form-control-sm" value="${escapeHtml(product.barcode || '')}" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="design-no"><input type="text" class="form-control form-control-sm" value="${escapeHtml(product.article || '')}" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="huid"><input type="text" class="form-control form-control-sm" value="" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="category"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="">Select</option></select></td>
                                <td data-column="calculation"><select class="form-control form-control-sm" style="width: 120px; font-size: 0.7rem;"><option value="Carat X Rate">Carat X Rate</option><option value="Rate X Gross Wt" selected>Rate X Gross Wt</option><option value="Rate X Purity Wt">Rate X Purity Wt</option><option value="Rate X Net Wt">Rate X Net Wt</option><option value="Rate X Final Wt">Rate X Final Wt</option><option value="Fix">Fix</option><option value="Stone Charge">Stone Charge</option><option value="Attach Image Type">Attach Image Type</option></select></td>
                                <td data-column="product"><input type="text" class="form-control form-control-sm" value="${escapeHtml(productName)}" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="location"><select class="form-control form-control-sm location-select" style="width: 100px; font-size: 0.7rem;"><option value="">Select</option></select></td>
                                <td data-column="pkt-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
                                <td data-column="pkt-less-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="gross-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
                                <td data-column="stone-weight"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 110px; font-size: 0.7rem;"></td>
                                <td data-column="less-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
                                <td data-column="net-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" readonly style="width: 80px; font-size: 0.7rem;"></td>
                                <td data-column="quantity"><input type="text" class="form-control form-control-sm" value="1" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
                                <td data-column="rate"><input type="text" class="form-control form-control-sm" value="${product.rate || 0}" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
                                <td data-column="amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="metal-qty"><input type="text" class="form-control form-control-sm" value="1" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
                                <td data-column="metal-weight"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
                                <td data-column="carat"><select class="form-control form-control-sm carat-select" style="width: 80px; font-size: 0.7rem;"><option value="">Select</option></select></td>
                                <td data-column="purity"><input type="text" class="form-control form-control-sm" value="${product.opening_purity || 1}" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
                                <td data-column="purity-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" readonly style="width: 90px; font-size: 0.7rem;"></td>
                                <td data-column="gold-loss1"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="gold-loss2"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="metal-loss-value"><input type="text" class="form-control form-control-sm" value="0.000" step="0.001" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="wastage-per"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="wastage-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="metal-rate"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 90px; font-size: 0.7rem;"></td>
                                <td data-column="metal-value"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="metal-cost"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem; color: #7b1fa2;"></td>
                                <td data-column="requested-purity"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 120px; font-size: 0.7rem;"></td>
                                <td data-column="requested"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="setting-charge"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 110px; font-size: 0.7rem;"></td>
                                <td data-column="final-wt"><input type="text" class="form-control form-control-sm" value="${product.final_weight || product.opening_weight || 0}" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
                                <td data-column="alloy-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
                                <td data-column="discount-type"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="On Amount" selected>On Amount</option><option value="On Percentage">On Percentage</option></select></td>
                                <td data-column="discount-per"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
                                <td data-column="discount-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="discount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="discount-type2"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="On Amount" selected>On Amount</option><option value="On Percentage">On Percentage</option></select></td>
                                <td data-column="discount-per2"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
                                <td data-column="discount-amount2"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="discounted-amt"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
                                <td data-column="discounted-per"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 120px; font-size: 0.7rem;"></td>
                                <td data-column="making-type"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="Fix" selected>Fix</option><option value="Per Gram">Per Gram</option><option value="Per Piece">Per Piece</option><option value="Per Kilogram">Per Kilogram</option><option value="Per Percent">Per Percent</option><option value="MRP">MRP</option><option value="M.KT">M.KT</option></select></td>
                                <td data-column="making-rate"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="making-discount-amt"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 130px; font-size: 0.7rem;"></td>
                                <td data-column="making-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="making-actual-value"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
                                <td data-column="making-cost"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 110px; font-size: 0.7rem;"></td>
                                <td data-column="min-price"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="minimum"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="stone-charge-type"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="Fix" selected>Fix</option><option value="Per Gram">Per Gram</option></select></td>
                                <td data-column="stone-rate"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="stone-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
                                <td data-column="stone-cost"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="diamond-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 120px; font-size: 0.7rem;"></td>
                                <td data-column="purchase-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 130px; font-size: 0.7rem;"></td>
                                            <td data-column="sale-percent"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;" placeholder="%" title="Sale % on Purchase Amount"></td>            <td data-column="sale-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 110px; font-size: 0.7rem;"></td>
                                <td data-column="sale-amount-with"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 130px; font-size: 0.7rem;"></td>
                                <td data-column="net-amt"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="tax-type"><select class="form-control form-control-sm" style="width: 120px; font-size: 0.7rem;"><option value="tax_on_making">Tax on making</option><option value="tax_of_netamount">Tax of net amount</option><option value="no_tax" selected>No tax</option></select></td>
                                <td data-column="tax-percent"><input type="text" class="form-control form-control-sm" value="${(product.total_tax_percent != null && product.total_tax_percent !== '') ? product.total_tax_percent : ((product.vat_value != null && product.vat_value !== '') ? product.vat_value : 0)}" min="0" max="100" step="0.01" readonly style="width: 70px; font-size: 0.7rem; background: #f1f5f9; cursor: not-allowed;" title="From product opening (sum of all taxes, read-only)"></td>
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
                    if (typeof reorderModalRowCellsToMatchHeader === 'function') {
                        tbody.querySelectorAll('.product-row').forEach(function(row) {
                            reorderModalRowCellsToMatchHeader(row);
                        });
                    }
                    // Populate carat and location dropdowns
                    tbody.querySelectorAll('.carat-select').forEach(function(select) {
                        populateSelect(select, carats, 'id', 'name', 'Select Karat');
                    });
                    
                    tbody.querySelectorAll('.location-select').forEach(function(select) {
                        populateSelect(select, locations, 'id', 'name', 'Select Location');
                    });
                    
                    // Populate category dropdowns (Diamond tab = Diamond Category options)
                    tbody.querySelectorAll('[data-column="category"] select').forEach(function(select) {
                        if (typeof populateCategorySelectForModal === 'function') populateCategorySelectForModal(select, isDiamondTabActive());
                        else if (typeof categories !== 'undefined') { populateSelect(select, categories, 'id', 'name', 'Select Category'); select.classList.add('category-select'); }
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
                    if (typeof updateJewelleryDiamondCaratFromDiamondAndGemstone === 'function') updateJewelleryDiamondCaratFromDiamondAndGemstone();
                    
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
            let url = 'ajax/get-products-by-metal.php?metal_id=' + metalId + (search ? '&search=' + encodeURIComponent(search) : '');
            if (diamondCategory && ['Diamonds', 'GemStones', 'Jewellery'].indexOf(diamondCategory) !== -1) {
                url += '&diamond_category=' + encodeURIComponent(diamondCategory);
            }
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.products.length > 0) {
                        let html = '';
                        data.products.forEach(function(product) {
                            const productName = product.name + (product.metal_name ? ' - ' + product.metal_name : '');
                            html += `
                                <tr class="product-row" data-product-id="${product.id}" data-characteristic-id="${product.characteristic_id || ''}">
                                    <td data-column="checkbox" style="text-align: center; background: #fff;">
                                        <input type="checkbox" class="product-checkbox" data-product-id="${product.id}" data-characteristic-id="${product.characteristic_id || ''}">
                                    </td>
                                    <td data-column="id">${product.id || ''}</td>
                                    <td data-column="rfid"><input type="text" class="form-control form-control-sm" value="" style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="voucher-type"><input type="text" class="form-control form-control-sm" value="" style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="photo" class="product-row-photo" style="text-align: center; vertical-align: middle; min-width: 70px;"><img src="" alt="" class="product-photo-thumb" style="max-width: 50px; max-height: 50px; display: none;"><span class="product-photo-placeholder text-muted" style="font-size: 0.65rem;">—</span></td>
                                    <td data-column="barcode"><input type="text" class="form-control form-control-sm" value="${escapeHtml(product.barcode || '')}" style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="design-no"><input type="text" class="form-control form-control-sm" value="${escapeHtml(product.article || '')}" style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="huid"><input type="text" class="form-control form-control-sm" value="" style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="category"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="">Select</option></select></td>
                                    <td data-column="calculation"><select class="form-control form-control-sm" style="width: 120px; font-size: 0.7rem;"><option value="Carat X Rate">Carat X Rate</option><option value="Rate X Gross Wt" selected>Rate X Gross Wt</option><option value="Rate X Purity Wt">Rate X Purity Wt</option><option value="Rate X Net Wt">Rate X Net Wt</option><option value="Rate X Final Wt">Rate X Final Wt</option><option value="Fix">Fix</option><option value="Stone Charge">Stone Charge</option><option value="Attach Image Type">Attach Image Type</option></select></td>
                                    <td data-column="product"><input type="text" class="form-control form-control-sm" value="${escapeHtml(productName)}" style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="location"><select class="form-control form-control-sm location-select" style="width: 100px; font-size: 0.7rem;"><option value="">Select</option></select></td>
                                    <td data-column="pkt-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
                                    <td data-column="pkt-less-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="gross-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
                                    <td data-column="stone-weight"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 110px; font-size: 0.7rem;"></td>
                                    <td data-column="less-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
                                    <td data-column="net-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" readonly style="width: 80px; font-size: 0.7rem;"></td>
                                    <td data-column="quantity"><input type="text" class="form-control form-control-sm" value="1" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
                                    <td data-column="rate"><input type="text" class="form-control form-control-sm" value="${product.rate || 0}" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
                                    <td data-column="amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="metal-weight"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
                                    <td data-column="carat"><select class="form-control form-control-sm carat-select" style="width: 80px; font-size: 0.7rem;"><option value="">Select</option></select></td>
                                    <td data-column="purity"><input type="text" class="form-control form-control-sm" value="${product.opening_purity || 1}" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
                                    <td data-column="purity-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" readonly style="width: 90px; font-size: 0.7rem;"></td>
                                    <td data-column="gold-loss1"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="gold-loss2"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="metal-loss-value"><input type="text" class="form-control form-control-sm" value="0.000" step="0.001" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="wastage-per"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="wastage-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="metal-rate"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 90px; font-size: 0.7rem;"></td>
                                    <td data-column="metal-value"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="metal-cost"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem; color: #7b1fa2;"></td>
                                    <td data-column="requested-purity"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 120px; font-size: 0.7rem;"></td>
                                    <td data-column="requested"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="setting-charge"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 110px; font-size: 0.7rem;"></td>
                                    <td data-column="final-wt"><input type="text" class="form-control form-control-sm" value="${product.final_weight || product.opening_weight || 0}" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
                                    <td data-column="alloy-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
                                    <td data-column="discount-type"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="On Amount" selected>On Amount</option><option value="On Percentage">On Percentage</option></select></td>
                                    <td data-column="discount-per"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
                                    <td data-column="discount-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="discount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="discount-type2"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="On Amount" selected>On Amount</option><option value="On Percentage">On Percentage</option></select></td>
                                    <td data-column="discount-per2"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
                                    <td data-column="discount-amount2"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="discounted-amt"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
                                    <td data-column="discounted-per"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 120px; font-size: 0.7rem;"></td>
                                    <td data-column="making-type"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="Fix" selected>Fix</option><option value="Per Gram">Per Gram</option><option value="Per Piece">Per Piece</option><option value="Per Kilogram">Per Kilogram</option><option value="Per Percent">Per Percent</option><option value="MRP">MRP</option><option value="M.KT">M.KT</option></select></td>
                                    <td data-column="making-rate"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="making-discount-amt"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 130px; font-size: 0.7rem;"></td>
                                    <td data-column="making-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="making-actual-value"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
                                    <td data-column="making-cost"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 110px; font-size: 0.7rem;"></td>
                                    <td data-column="min-price"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="minimum"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="stone-charge-type"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="Fix" selected>Fix</option><option value="Per Gram">Per Gram</option></select></td>
                                    <td data-column="stone-rate"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="stone-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
                                    <td data-column="stone-cost"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="diamond-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 120px; font-size: 0.7rem;"></td>
                                    <td data-column="purchase-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 130px; font-size: 0.7rem;"></td>
                                                <td data-column="sale-percent"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;" placeholder="%" title="Sale % on Purchase Amount"></td>            <td data-column="sale-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 110px; font-size: 0.7rem;"></td>
                                    <td data-column="sale-amount-with"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 130px; font-size: 0.7rem;"></td>
                                    <td data-column="net-amt"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="tax-type"><select class="form-control form-control-sm" style="width: 120px; font-size: 0.7rem;"><option value="tax_on_making">Tax on making</option><option value="tax_of_netamount">Tax of net amount</option><option value="no_tax" selected>No tax</option></select></td>
                                    <td data-column="tax-percent"><input type="text" class="form-control form-control-sm" value="${(product.total_tax_percent != null && product.total_tax_percent !== '') ? product.total_tax_percent : ((product.vat_value != null && product.vat_value !== '') ? product.vat_value : 0)}" min="0" max="100" step="0.01" readonly style="width: 70px; font-size: 0.7rem; background: #f1f5f9; cursor: not-allowed;" title="From product opening (sum of all taxes, read-only)"></td>
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
                        if (typeof reorderModalRowCellsToMatchHeader === 'function') {
                            tbody.querySelectorAll('.product-row').forEach(function(row) {
                                reorderModalRowCellsToMatchHeader(row);
                            });
                        }
                        // Populate carat and location dropdowns
                        tbody.querySelectorAll('.carat-select').forEach(function(select) {
                            populateSelect(select, carats, 'id', 'name', 'Select Karat');
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
                            if (categorySelect && !categorySelect.classList.contains('category-select') && !categorySelect.classList.contains('diamond-category-select')) {
                                if (typeof populateCategorySelectForModal === 'function') populateCategorySelectForModal(categorySelect, isDiamondTabActive());
                                else if (typeof categories !== 'undefined') { populateSelect(categorySelect, categories, 'id', 'name', 'Select Category'); categorySelect.classList.add('category-select'); }
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
        
        // Use delegation so checkboxes work after panel content is replaced (Diamond tab list)
        const checkboxes = settingsDropdown.querySelectorAll('input[type="checkbox"]');
        
        // Toggle dropdown on button click
        settingsBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            settingsDropdown.classList.toggle('show');
            // Clear search when opening dropdown
            const searchInput = document.getElementById('modalTableSettingsSearch');
            if (searchInput && settingsDropdown.classList.contains('show')) {
                searchInput.value = '';
                // Show all items when opening
                const items = settingsDropdown.querySelectorAll('.table-settings-item');
                items.forEach(function(item) {
                    item.classList.remove('hidden');
                });
            }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!settingsBtn.contains(e.target) && !settingsDropdown.contains(e.target)) {
                settingsDropdown.classList.remove('show');
                // Clear search when closing dropdown
                const searchInput = document.getElementById('modalTableSettingsSearch');
                if (searchInput) {
                    searchInput.value = '';
                    // Show all items when closing
                    const items = settingsDropdown.querySelectorAll('.table-settings-item');
                    items.forEach(function(item) {
                        item.classList.remove('hidden');
                    });
                }
            }
        });

        // Column groups and updateGroupHeaderVisibility are in product-modal-add-item-common.js (window.PRODUCT_MODAL_COLUMN_GROUPS, window.PRODUCT_MODAL_GROUP_ORIGINAL_COLSPANS, window.updateGroupHeaderVisibility)
        // Handle column visibility (delegated so it works when Diamond panel list replaces content)
        settingsDropdown.addEventListener('change', function(e) {
            if (e.target.type !== 'checkbox' || !e.target.getAttribute('data-column')) return;
            const columnName = e.target.getAttribute('data-column');
            const isVisible = e.target.checked;
            const table = document.getElementById('productListTable');
            if (!table) return;
            const headers = table.querySelectorAll('th[data-column="' + columnName + '"]');
            const cells = table.querySelectorAll('td[data-column="' + columnName + '"]');
            headers.forEach(function(header) {
                header.classList.toggle('hidden', !isVisible);
                header.style.removeProperty('display');
            });
            cells.forEach(function(cell) {
                cell.classList.toggle('hidden', !isVisible);
                cell.style.removeProperty('display');
                cell.querySelectorAll('input, select').forEach(function(inp) {
                    inp.style.setProperty('display', isVisible ? '' : 'none', 'important');
                });
            });
            requestAnimationFrame(function() {
                requestAnimationFrame(function() {
                    if (typeof updateGroupHeaderVisibility === 'function') updateGroupHeaderVisibility();
                });
            });
            saveProductModalColumnPreferencesDebounced(currentMetalId || '');
        });
        
        // Initialize group header visibility on page load
        // Use setTimeout to ensure DOM is fully loaded
        setTimeout(function() {
            updateGroupHeaderVisibility();
        }, 100);
        
        // Also update when modal is shown (in case columns were changed while modal was closed)
        const productSelectionModal = document.getElementById('productSelectionModal');
        if (productSelectionModal) {
            productSelectionModal.addEventListener('shown.bs.modal', function() {
                var isDiamond = (typeof currentMetalName === 'string' && currentMetalName.toLowerCase().indexOf('diamond') !== -1);
                var scrollWrap = document.getElementById('productListTableScrollWrapper');
                var outerWrap = document.getElementById('productListTableOuter');
                if (isDiamond) productSelectionModal.classList.remove('product-modal-metal-tab');
                else productSelectionModal.classList.add('product-modal-metal-tab');
                if (scrollWrap) { scrollWrap.style.width = ''; scrollWrap.style.maxWidth = ''; }
                if (outerWrap) { outerWrap.style.width = ''; outerWrap.style.maxWidth = ''; }
                // Reload saved column preferences and apply after load; Diamond & Stones tab gets fixed column set
                loadProductModalColumnPreferences(function() {
                    setTimeout(function() {
                        var tabKey = (typeof currentMetalId !== 'undefined' && currentMetalId !== null) ? String(currentMetalId) : '';
                        if (typeof applyProductModalColumnVisibilityForTab === 'function') applyProductModalColumnVisibilityForTab(tabKey);
                        if (typeof updateGroupHeaderVisibility === 'function') updateGroupHeaderVisibility();
                        var storedImage = window.productModalGroupImageByTab && window.productModalGroupImageByTab[tabKey];
                        if (storedImage && typeof applyProductModalGroupImageToPhotoColumns === 'function') {
                            applyProductModalGroupImageToPhotoColumns(storedImage, tabKey);
                        }
                    }, 50);
                });
            });
        }
        
        // Column search functionality for modal
        const modalSearchInput = document.getElementById('modalTableSettingsSearch');
        if (modalSearchInput) {
            modalSearchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                const items = settingsDropdown.querySelectorAll('.table-settings-item');
                
                items.forEach(function(item) {
                    const label = item.querySelector('label');
                    if (label) {
                        const labelText = label.textContent.toLowerCase();
                        if (labelText.includes(searchTerm)) {
                            item.classList.remove('hidden');
                        } else {
                            item.classList.add('hidden');
                        }
                    }
                });
            });
        }
    })();
    
    // Load product modal column preferences (tab-wise) for logged-in user
    function loadProductModalColumnPreferences(onLoaded) {
        $.ajax({
            url: 'ajax/get-column-preferences.php',
            type: 'POST',
            data: { page_name: PRODUCT_MODAL_COLUMNS_PAGE },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success' && response.by_tab) {
                    window.productModalColumnVisibilityByTab = response.by_tab;
                } else {
                    window.productModalColumnVisibilityByTab = {};
                }
                // Apply visibility only when we have a valid tab (modal open with metal tab selected).
                // On initial page load currentMetalId is null - skip apply so we don't overwrite with "all visible".
                // Visibility will be applied when modal opens (initCategoryTabs / shown.bs.modal).
                var tabKey = (currentMetalId === undefined || currentMetalId === null) ? '' : String(currentMetalId);
                if (tabKey !== '' && typeof applyProductModalColumnVisibilityForTab === 'function') {
                    applyProductModalColumnVisibilityForTab(tabKey);
                }
                if (typeof onLoaded === 'function') onLoaded();
            }
        });
    }
    // Load tab-wise column preferences on page load (for logged-in user)
    loadProductModalColumnPreferences();
    
    // Apply column visibility for a given tab (Gold, Silver, etc.) from saved state. Diamond & Stones tab uses fixed list only.
    function applyProductModalColumnVisibilityForTab(tabKey) {
        if (tabKey === undefined || tabKey === null) return; // allow '' for default tab
        var tk = (tabKey === '') ? '' : String(tabKey);
        const settingsDropdown = document.getElementById('modalTableSettingsDropdown');
        const table = document.getElementById('productListTable');
        if (!settingsDropdown || !table) return;
        // Detect Diamond & Stones tab from active tab button (so it works on modal reopen / preference load)
        var activeTabBtn = document.querySelector('#productSelectionModal .category-tab-btn.active');
        var tabDisplayName = (activeTabBtn && activeTabBtn.getAttribute('data-metal-name')) || (typeof currentMetalName !== 'undefined' ? currentMetalName : '');
        var isDiamondTab = (typeof tabDisplayName === 'string' && tabDisplayName.toLowerCase().indexOf('diamond') !== -1);
        var diamondVisibleSet = {};
        if (typeof DIAMOND_TAB_VISIBLE_COLUMNS !== 'undefined') {
            DIAMOND_TAB_VISIBLE_COLUMNS.forEach(function(col) { diamondVisibleSet[col] = 1; });
        }
        // Diamond tab: use saved preferences when available so hide/show persists when switching tabs
        var savedDiamond = window.productModalColumnVisibilityByTab && (window.productModalColumnVisibilityByTab[tk] || window.productModalColumnVisibilityByTab[tabKey]);
        var prefs = isDiamondTab
            ? (savedDiamond && Object.keys(savedDiamond).length > 0 ? savedDiamond : diamondVisibleSet)
            : ((typeof window.mergeProductModalMetalTabPrefs === 'function')
                ? window.mergeProductModalMetalTabPrefs(tk, tabKey)
                : (window.productModalColumnVisibilityByTab[tk] || window.productModalColumnVisibilityByTab[tabKey]));
        // For Diamond tab: apply to ALL columns in table (including those without checkboxes); use prefs (saved or default)
        if (isDiamondTab && typeof DIAMOND_TAB_VISIBLE_COLUMNS !== 'undefined') {
            const headerRow = table.querySelector('thead tr:last-child');
            if (headerRow) {
                const allCols = headerRow.querySelectorAll('th[data-column]');
                allCols.forEach(function(th) {
                    const columnName = th.getAttribute('data-column');
                    const isVisible = (prefs && prefs[columnName] === 1);
                    th.classList.toggle('hidden', !isVisible);
                    th.style.removeProperty('display');
                    const cells = table.querySelectorAll('td[data-column="' + columnName + '"]');
                    cells.forEach(function(el) {
                        el.classList.toggle('hidden', !isVisible);
                        el.style.removeProperty('display');
                        el.querySelectorAll('input, select').forEach(function(inp) {
                            inp.style.setProperty('display', isVisible ? '' : 'none', 'important');
                        });
                    });
                });
            }
            const checkboxCol = table.querySelector('th[data-column="checkbox"]');
            if (checkboxCol) {
                const isVisible = (prefs && prefs['checkbox'] === 1);
                checkboxCol.classList.toggle('hidden', !isVisible);
                table.querySelectorAll('td[data-column="checkbox"]').forEach(function(el) {
                    el.classList.toggle('hidden', !isVisible);
                });
            }
        }
        const checkboxes = settingsDropdown.querySelectorAll('input[type="checkbox"][data-column]');
        checkboxes.forEach(function(checkbox) {
            const columnName = checkbox.getAttribute('data-column');
            const isVisible = (prefs && prefs.hasOwnProperty(columnName)) ? (prefs[columnName] === 1) : (isDiamondTab ? (diamondVisibleSet[columnName] === 1) : true);
            checkbox.checked = isVisible;
            if (!isDiamondTab) {
                const headers = table.querySelectorAll('th[data-column="' + columnName + '"]');
                const cells = table.querySelectorAll('td[data-column="' + columnName + '"]');
                headers.forEach(function(el) {
                    el.classList.toggle('hidden', !isVisible);
                    el.style.removeProperty('display');
                });
                cells.forEach(function(el) {
                    el.classList.toggle('hidden', !isVisible);
                    el.style.removeProperty('display');
                    el.querySelectorAll('input, select').forEach(function(inp) {
                        inp.style.setProperty('display', isVisible ? '' : 'none', 'important');
                    });
                });
            }
        });
        // Diamond group: show only on Diamond & Stones tab; hide on Gold, Silver, and other tabs
        (function applyDiamondGroupVisibilityByTab() {
            var diamondGroupColumns = ['pkt-wt', 'pkt-less-wt', 'gross-wt', 'stone-weight', 'less-wt', 'net-wt', 'quantity', 'rate', 'amount'];
            var groupHeaderRow = table.querySelector('thead tr:first-child');
            var diamondGroupHeader = groupHeaderRow ? groupHeaderRow.querySelector('th[data-group="diamond-group"]') : null;
            if (isDiamondTab) {
                if (diamondGroupHeader) {
                    diamondGroupHeader.style.display = '';
                    diamondGroupHeader.classList.remove('hidden');
                    diamondGroupHeader.setAttribute('colspan', '9');
                }
                diamondGroupColumns.forEach(function(columnName) {
                    var visible = (prefs && prefs[columnName] === 1);
                    table.querySelectorAll('th[data-column="' + columnName + '"]').forEach(function(el) {
                        el.classList.toggle('hidden', !visible);
                        el.style.removeProperty('display');
                    });
                    table.querySelectorAll('td[data-column="' + columnName + '"]').forEach(function(el) {
                        el.classList.toggle('hidden', !visible);
                        el.style.removeProperty('display');
                        el.querySelectorAll('input, select').forEach(function(inp) {
                            inp.style.setProperty('display', visible ? '' : 'none', 'important');
                        });
                    });
                });
            } else {
                if (diamondGroupHeader) {
                    diamondGroupHeader.style.display = 'none';
                    diamondGroupHeader.classList.add('hidden');
                    diamondGroupHeader.setAttribute('colspan', '0');
                }
                diamondGroupColumns.forEach(function(columnName) {
                    table.querySelectorAll('th[data-column="' + columnName + '"]').forEach(function(el) {
                        el.classList.add('hidden');
                        el.style.display = 'none';
                    });
                    table.querySelectorAll('td[data-column="' + columnName + '"]').forEach(function(el) {
                        el.classList.add('hidden');
                        el.style.display = 'none';
                        el.querySelectorAll('input, select').forEach(function(inp) {
                            inp.style.setProperty('display', 'none', 'important');
                        });
                    });
                });
            }
        })();
        // Diamond & Stones tab: show exact column names; other tabs: restore original header labels
        (function applyOrRestoreDiamondHeaderLabels() {
            var allTh = table.querySelectorAll('thead th[data-column]');
            if (!allTh.length) return;
            if (isDiamondTab && typeof DIAMOND_TAB_HEADER_LABELS !== 'undefined') {
                allTh.forEach(function(th) {
                    var col = th.getAttribute('data-column');
                    if (!col) return;
                    if (!window.productModalOriginalHeaderHtml[col]) window.productModalOriginalHeaderHtml[col] = th.innerHTML;
                    var label = DIAMOND_TAB_HEADER_LABELS[col];
                    if (label !== undefined) {
                        if (col === 'checkbox') th.innerHTML = 'Active <input type="checkbox" id="selectAllProducts" title="Select All">';
                        else {
                            th.innerHTML = window.productModalOriginalHeaderHtml[col];
                            var labelSpan = th.querySelector('.product-modal-th-label');
                            if (labelSpan) {
                                labelSpan.textContent = label;
                            }
                            th.setAttribute('title', label);
                        }
                    }
                });
            } else {
                allTh.forEach(function(th) {
                    var col = th.getAttribute('data-column');
                    if (window.productModalOriginalHeaderHtml[col]) th.innerHTML = window.productModalOriginalHeaderHtml[col];
                });
            }
        })();
        // Diamond tab: ensure all category dropdowns in modal show Diamond Category (Diamonds, GemStones, Jewellery)
        if (typeof populateCategorySelectForModal === 'function') {
            var categorySelects = table.querySelectorAll('#productListBody [data-column="category"] select');
            categorySelects.forEach(function(sel) { populateCategorySelectForModal(sel, isDiamondTab); });
        }
        // Diamond tab: Calculation Type by row category (Jewellery = metal options only); other tabs = full list
        if (typeof applyCalculationSelectOptionsForRow === 'function') {
            table.querySelectorAll('#productListBody .product-row').forEach(function(r) {
                var sel = r.querySelector('[data-column="calculation"] select');
                if (sel) applyCalculationSelectOptionsForRow(sel, r, isDiamondTab);
            });
        }
        // Show/Hide Columns panel: when Diamond tab show full column list (as before); otherwise restore original
        (function setShowHidePanelContentForDiamond() {
            var dropdown = document.getElementById('modalTableSettingsDropdown');
            if (!dropdown) return;
            if (isDiamondTab && typeof DIAMOND_TAB_PANEL_COLUMNS !== 'undefined') {
                if (!window.productModalOriginalSettingsDropdownContent) window.productModalOriginalSettingsDropdownContent = dropdown.innerHTML;
                var searchDiv = dropdown.querySelector('.table-settings-search');
                var searchHtml = searchDiv ? searchDiv.outerHTML : '';
                var parts = [];
                DIAMOND_TAB_PANEL_COLUMNS.forEach(function(entry, idx) {
                    var col = entry.dataColumn;
                    var label = entry.label;
                    var checked = (prefs && prefs[col] === 1);
                    var id = 'modal-col-diamond-' + idx;
                    parts.push('<div class="table-settings-item"><input type="checkbox" id="' + id + '" data-column="' + (col || '') + '"' + (checked ? ' checked' : '') + '><label for="' + id + '">' + (label ? String(label).replace(/&/g, '&amp;').replace(/</g, '&lt;') : '') + '</label></div>');
                });
                dropdown.innerHTML = '<h6>Show/Hide Columns</h6>' + searchHtml + parts.join('');
            } else if (window.productModalOriginalSettingsDropdownContent) {
                dropdown.innerHTML = window.productModalOriginalSettingsDropdownContent;
            }
        })();
        // After dropdown content may have been restored (e.g. switching back to Gold), re-sync checkboxes from prefs so they match actual column visibility
        if (prefs && settingsDropdown) {
            settingsDropdown.querySelectorAll('input[type="checkbox"][data-column]').forEach(function(checkbox) {
                var columnName = checkbox.getAttribute('data-column');
                checkbox.checked = (prefs.hasOwnProperty(columnName) && prefs[columnName] === 1);
            });
        }
        setTimeout(function() {
            const groupHeaderRow = document.querySelector('#productListTable thead tr:first-child');
            if (groupHeaderRow && typeof updateGroupHeaderVisibility === 'function') updateGroupHeaderVisibility();
            if (typeof sizeProductModalTableWrapperForMetalTab === 'function') sizeProductModalTableWrapperForMetalTab();
        }, 50);
    }
    
    // Clear any inline width on table wrappers so CSS full-width (100%) applies and no blank space on right
    function sizeProductModalTableWrapperForMetalTab() {
        const outer = document.getElementById('productListTableOuter');
        const scrollWrap = document.getElementById('productListTableScrollWrapper');
        if (outer) { outer.style.width = ''; outer.style.maxWidth = ''; }
        if (scrollWrap) { scrollWrap.style.width = ''; scrollWrap.style.maxWidth = ''; }
    }
    
    // Save current tab column preferences to server (debounced) - login user wise
    function saveProductModalColumnPreferencesDebounced(tabKey) {
        if (tabKey === undefined || tabKey === null) return; // allow '' for default tab
        clearTimeout(productModalColumnSaveTimeout);
        productModalColumnSaveTimeout = setTimeout(function() {
            const settingsDropdown = document.getElementById('modalTableSettingsDropdown');
            if (!settingsDropdown) return;
            const checkboxes = settingsDropdown.querySelectorAll('input[type="checkbox"][data-column]');
            const prefs = {};
            checkboxes.forEach(function(cb) {
                prefs[cb.getAttribute('data-column')] = cb.checked ? 1 : 0;
            });
            if (!window.productModalColumnVisibilityByTab[tabKey]) window.productModalColumnVisibilityByTab[tabKey] = {};
            Object.keys(prefs).forEach(function(k) { window.productModalColumnVisibilityByTab[tabKey][k] = prefs[k]; });
            $.ajax({
                url: 'ajax/save-product-modal-column-preferences.php',
                type: 'POST',
                data: {
                    page_name: PRODUCT_MODAL_COLUMNS_PAGE,
                    tab_key: tabKey,
                    preferences: JSON.stringify(prefs)
                },
                dataType: 'json'
            });
        }, 400);
    }
    
    // getModalRowDataFromRow, savedItemToModalRowData, getItemAndProductFromModalRowData from product-modal-add-item-common.js
    
    // Select product and add to table
    function selectProduct(row, closeModal = false) {
        if (row && typeof window.calculateModalRowNetWeight === 'function') window.calculateModalRowNetWeight(row);
        const modalRowData = getModalRowDataFromRow(row, false);
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
    function addProductToTableFromModalRow(modalRowData, metalIdForImage) {
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
        
        // Use barcode from modal data (should be from saved product characteristics)
        let barcode = modalRowData.barcode || '';
        
        // If barcode is still empty, fetch it from product details API
        if ((!barcode || barcode.trim() === '') && modalRowData.product_id && modalRowData.characteristic_id) {
            // Fetch barcode synchronously using XMLHttpRequest (blocking)
            const xhr = new XMLHttpRequest();
            xhr.open('GET', 'ajax/get-product-details.php?product_id=' + modalRowData.product_id + '&characteristic_id=' + modalRowData.characteristic_id, false);
            xhr.send();
            if (xhr.status === 200) {
                try {
                    const data = JSON.parse(xhr.responseText);
                    if (data.success && data.product && data.product.barcode) {
                        barcode = data.product.barcode;
                    }
                } catch (e) {
                    console.error('Error parsing product details:', e);
                }
            }
        }
        
        const row = document.createElement('tr');
        row.id = rowId;
        row.setAttribute('data-product-id', modalRowData.product_id || '');
        row.setAttribute('data-characteristic-id', modalRowData.characteristic_id || '');
        row.setAttribute('data-purity', parseFloat(modalRowData.purity || 0));
        row.setAttribute('data-rate', parseFloat(modalRowData.rate || 0));
        row.setAttribute('data-barcode', barcode);
        row.setAttribute('data-calculation-type', modalRowData.calculation_type || 'Rate X Gross Wt');
        row.setAttribute('data-group-items', JSON.stringify([modalRowData]));
        
        // Ensure barcode is not empty - if still empty, use a placeholder
        if (!barcode || barcode.trim() === '') {
            console.warn('Barcode is empty for product:', modalRowData.product_id, 'characteristic:', modalRowData.characteristic_id);
        }
        
        try {
            var groupImagePayload = (metalIdForImage != null && window.productModalGroupImageByTab && window.productModalGroupImageByTab[metalIdForImage]) ? window.productModalGroupImageByTab[metalIdForImage] : '';
            var groupImageAttr = (typeof groupImagePayload === 'object' && groupImagePayload != null) ? JSON.stringify(groupImagePayload) : (groupImagePayload || '');
            row.setAttribute('data-group-image', groupImageAttr);
            var primaryUrl = typeof getGroupImagePrimary === 'function' ? getGroupImagePrimary(groupImagePayload) : (typeof groupImagePayload === 'string' ? groupImagePayload : '');
            var modalData = Object.assign({}, modalRowData);
            if (barcode) modalData.barcode = barcode;
            var actionCell = '<td><div class="action-btns"><button type="button" class="btn-edit" onclick="editProductRow(\'' + rowId + '\')" title="Edit"><i class="feather icon-edit"></i></button><button type="button" class="btn-delete" onclick="deleteProductRow(\'' + rowId + '\')" title="Delete"><i class="feather icon-trash-2"></i></button></div></td>';
            row.innerHTML = (typeof getProductListRowCells === 'function' ? getProductListRowCells(modalData, { groupImage: primaryUrl }) : []).join('') + actionCell;
            
            tbody.appendChild(row);
            if (typeof window.applyProductListColumnVisibilityToRow === 'function') window.applyProductListColumnVisibilityToRow(row);
            console.log('Row added to table from modal:', rowId);
            
            addRowCalculationListeners(row);
            var productCell = row.querySelector('[data-column="product"]');
            if (productCell) {
                productCell.addEventListener('click', function(e) {
                    if (e.target.tagName !== 'A') editProductRow(rowId);
                });
            }
            
            updateSummaryRow();
            updateSummaryPanel();
            
        } catch (error) {
            console.error('Error adding product to table from modal:', error);
            alert('Error adding product: ' + error.message);
        }
    }
    
    // Merge multiple modal products into one Product List row (single entry with summed qty/weight); store group for edit
    function addMergedProductsToTable(modalRowsData, metalIdForImage) {
        if (!modalRowsData || modalRowsData.length === 0) return;
        if (modalRowsData.length === 1) {
            addProductToTableFromModalRow(modalRowsData[0], metalIdForImage);
            return;
        }
        const tbody = document.getElementById('productTableBody');
        if (!tbody) return;
        const emptyRow = tbody.querySelector('.no-drag');
        if (emptyRow) emptyRow.remove();
        
        var productNames = modalRowsData.map(function(d) { return (d.product_name || '').trim(); }).filter(Boolean);
        // Ensure each row has barcode (fetch from API if missing so merged row shows all barcodes e.g. RND00001, DD00001, RBG00001)
        modalRowsData.forEach(function(d) {
            if ((!d.barcode || !(d.barcode + '').trim()) && d.product_id && d.characteristic_id) {
                try {
                    var xhr = new XMLHttpRequest();
                    xhr.open('GET', 'ajax/get-product-details.php?product_id=' + encodeURIComponent(d.product_id) + '&characteristic_id=' + encodeURIComponent(d.characteristic_id), false);
                    xhr.send();
                    if (xhr.status === 200) {
                        var data = JSON.parse(xhr.responseText);
                        if (data && data.success && data.product && data.product.barcode) d.barcode = data.product.barcode;
                    }
                } catch (e) {}
            }
        });
        // Collect all barcodes (one per product); use placeholder if empty so "2 products" shows 2 barcode slots
        var barcodes = modalRowsData.map(function(d) { var b = (d.barcode || '').trim(); return b || '—'; });
        var first = modalRowsData[0];
        var merged = {
            product_id: first.product_id,
            characteristic_id: first.characteristic_id,
            product_name: productNames.length ? productNames.join(' + ') : (modalRowsData.length + ' items'),
            barcode: (first.barcode || '').trim() || (barcodes[0] !== '—' ? barcodes[0] : '') || '',
            purity: parseFloat(first.purity) || 0,
            quantity: 0, gross_wt: 0, less_wt: 0, net_wt: 0, pure_wt: 0, final_wt: 0,
            rate: 0, metal_value: 0, amount: 0, discount: 0, making_amount: 0, stone_amount: 0,
            other_amount: 0, diamond_amount: 0, tax: 0, net_amt: 0, net_amt_tax: 0,
            purchase_amount: 0, sale_amount: 0, sale_amount_with: 0, reverse: 0,
            design_no: first.design_no || '', calculation_type: first.calculation_type || 'Rate X Gross Wt',
            short_code: first.short_code || '', rfid: first.rfid || '', voucher_type: first.voucher_type || '',
            huid: first.huid || '', category: first.category || '', location: first.location || '',
            carat: first.carat || 0, pkt_wt: first.pkt_wt || 0, pkt_less_wt: first.pkt_less_wt || 0,
            requested_purity: first.requested_purity || 0, requested: first.requested || 0,
            gold_loss1: first.gold_loss1 || 0, gold_loss2: first.gold_loss2 || 0, setting_charge: first.setting_charge || 0,
            wastage_per: first.wastage_per || 0, wastage_wt: first.wastage_wt || 0, alloy_wt: first.alloy_wt || 0,
            metal_cost: first.metal_cost || 0, discount_type: first.discount_type || '', discount_per: first.discount_per || 0,
            discount_amount: first.discount_amount || 0, making_type: first.making_type || '', making_rate: first.making_rate || 0,
            making_cost: first.making_cost || 0, min_price: first.min_price || 0,
            stone_charge_type: first.stone_charge_type || '', stone_weight: first.stone_weight || 0, stone_rate: first.stone_rate || 0,
            stone_cost: first.stone_cost || 0, tax_type: first.tax_type || '', tax_percent: first.tax_percent || 0,
            other_charge_type: first.other_charge_type || '', other_weight: first.other_weight || 0, other_rate: first.other_rate || 0,
            other_info: first.other_info || '', hallmark_amount: first.hallmark_amount || 0, hallmark_rate: first.hallmark_rate || 0
        };
        modalRowsData.forEach(function(d) {
            merged.quantity += parseFloat(d.quantity) || 0;
            merged.gross_wt += parseFloat(d.gross_wt) || 0;
            merged.less_wt += parseFloat(d.less_wt) || 0;
            merged.net_wt += parseFloat(d.net_wt) || 0;
            merged.pure_wt += parseFloat(d.pure_wt) || 0;
            merged.final_wt += parseFloat(d.final_wt) || 0;
            merged.rate += parseFloat(d.rate) || 0;
            merged.metal_value += parseFloat(d.metal_value) || 0;
            merged.amount += parseFloat(d.amount) || 0;
            merged.discount += parseFloat(d.discount) || 0;
            merged.making_amount += parseFloat(d.making_amount) || 0;
            merged.stone_amount += parseFloat(d.stone_amount) || 0;
            merged.other_amount += parseFloat(d.other_amount) || 0;
            merged.diamond_amount += parseFloat(d.diamond_amount) || 0;
            merged.tax += parseFloat(d.tax) || 0;
            merged.net_amt += parseFloat(d.net_amt) || 0;
            merged.net_amt_tax += parseFloat(d.net_amt_tax) || 0;
            merged.purchase_amount += parseFloat(d.purchase_amount) || 0;
            merged.sale_amount += parseFloat(d.sale_amount) || 0;
            merged.sale_amount_with += parseFloat(d.sale_amount_with) || 0;
            merged.reverse += parseFloat(d.reverse) || 0;
        });
        // Diamond & Stones: use ONLY Jewellery category Net Amt+Tax for merged total (exclude Diamonds and GemStones)
        var jewelleryRow = modalRowsData.filter(function(d) { return (String(d.category || '').trim() === 'Jewellery'); })[0];
        if (jewelleryRow && modalRowsData.some(function(d) { return (String(d.category || '').trim() === 'Diamonds') || (String(d.category || '').trim() === 'GemStones'); })) {
            var jNetAmt = parseFloat(jewelleryRow.net_amt) || 0;
            var jNetAmtTax = parseFloat(jewelleryRow.net_amt_tax) || 0;
            if (jNetAmtTax <= 0) jNetAmtTax = jNetAmt;
            merged.net_amt = jNetAmt;
            merged.net_amt_tax = jNetAmtTax;
            merged.amount = jNetAmt;
            merged.purchase_amount = jNetAmt;
            merged.sale_amount = jNetAmt;
            merged.sale_amount_with = jNetAmtTax;
            merged.metal_value = parseFloat(jewelleryRow.metal_value) || 0;
            merged.other_amount = parseFloat(jewelleryRow.other_amount) || 0;
            merged.making_amount = parseFloat(jewelleryRow.making_amount) || 0;
            merged.discount = parseFloat(jewelleryRow.discount) || 0;
            merged.stone_amount = parseFloat(jewelleryRow.stone_amount) || 0;
            merged.diamond_amount = parseFloat(jewelleryRow.diamond_amount) || 0;
            merged.tax = parseFloat(jewelleryRow.tax) || 0;
        }
        
        productTableRowIndex++;
        var rowId = 'product-row-' + productTableRowIndex;
        var row = document.createElement('tr');
        row.id = rowId;
        row.setAttribute('data-product-id', merged.product_id || '');
        row.setAttribute('data-characteristic-id', merged.characteristic_id || '');
        row.setAttribute('data-group-items', JSON.stringify(modalRowsData));
        row.setAttribute('data-purity', merged.purity || 0);
        row.setAttribute('data-rate', merged.rate || 0);
        row.setAttribute('data-calculation-type', merged.calculation_type || 'Rate X Gross Wt');
        
        var groupImagePayload = (metalIdForImage != null && window.productModalGroupImageByTab && window.productModalGroupImageByTab[metalIdForImage]) ? window.productModalGroupImageByTab[metalIdForImage] : '';
        var groupImageAttr = (typeof groupImagePayload === 'object' && groupImagePayload != null) ? JSON.stringify(groupImagePayload) : (groupImagePayload || '');
        row.setAttribute('data-group-image', groupImageAttr);
        var primaryUrl = typeof getGroupImagePrimary === 'function' ? getGroupImagePrimary(groupImagePayload) : (typeof groupImagePayload === 'string' ? groupImagePayload : '');
        var actionCell = '<td><div class="action-btns"><button type="button" class="btn-edit" onclick="editProductRow(\'' + rowId + '\')" title="Edit"><i class="feather icon-edit"></i></button><button type="button" class="btn-delete" onclick="deleteProductRow(\'' + rowId + '\')" title="Delete"><i class="feather icon-trash-2"></i></button></div></td>';
        row.innerHTML = (typeof getProductListRowCells === 'function' ? getProductListRowCells(merged, { groupImage: primaryUrl }) : []).join('') + actionCell;
        
        tbody.appendChild(row);
        if (typeof window.applyProductListColumnVisibilityToRow === 'function') window.applyProductListColumnVisibilityToRow(row);
        var productCell = row.querySelector('[data-column="product"]');
        if (productCell) {
            productCell.addEventListener('click', function(e) {
                if (e.target.tagName !== 'A') editProductRow(rowId);
            });
        }
        addRowCalculationListeners(row);
        updateSummaryRow();
        updateSummaryPanel();
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
        // Store calculation type, default to 'Rate X Gross Wt'
        row.setAttribute('data-calculation-type', 'Rate X Gross Wt');
        
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
            var rowData = {
                product_name: product.name || '',
                barcode: product.barcode || '',
                design_no: designNo,
                quantity: quantity, gross_wt: grossWt, less_wt: lessWt, purity: purity, final_wt: finalWt,
                net_wt: netWt, pure_wt: parseFloat(purityWt), rate: rate, metal_value: metalValue, amount: amount,
                making_amount: makingAmount, stone_amount: stoneCharges, other_amount: otherCharges, diamond_amount: diamondValue,
                tax: tax, net_amt: netAmt, net_amt_tax: netAmtWithTax,
                purchase_amount: netAmt, sale_amount: netAmt, sale_amount_with: netAmtWithTax, reverse: 0
            };
            var actionCell = '<td><div class="action-btns"><button type="button" class="btn-edit" onclick="editProductRow(\'' + rowId + '\')" title="Edit"><i class="feather icon-edit"></i></button><button type="button" class="btn-delete" onclick="deleteProductRow(\'' + rowId + '\')" title="Delete"><i class="feather icon-trash-2"></i></button></div></td>';
            row.innerHTML = (typeof getProductListRowCells === 'function' ? getProductListRowCells(rowData, { groupImage: '' }) : []).join('') + actionCell;
            
            tbody.appendChild(row);
            if (typeof window.applyProductListColumnVisibilityToRow === 'function') window.applyProductListColumnVisibilityToRow(row);
            console.log('Row added to table:', rowId);
            
            addRowCalculationListeners(row);
            calculateRowAmounts(row);
            var productCell = row.querySelector('[data-column="product"]');
            if (productCell) {
                productCell.addEventListener('click', function(e) {
                    if (e.target.tagName !== 'A') editProductRow(rowId);
                });
            }
            updateSummaryRow();
            updateSummaryPanel();
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
        
        // Update cells
        const barcodeCell = row.querySelector('[data-column="barcode"]');
        if (barcodeCell) {
            barcodeCell.innerHTML = `
                <div class="image-placeholder" style="width: 30px; height: 30px; background: #f1f5f9; border-radius: 4px; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
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
    
    // Update a merged Product List row from current modal rows (re-merge and save)
    function updateMergedRowFromModalRows(rowId, modalRowsData) {
        const row = document.getElementById(rowId);
        if (!row || !modalRowsData || modalRowsData.length === 0) return;
        var productNames = modalRowsData.map(function(d) { return (d.product_name || '').trim(); }).filter(Boolean);
        // One barcode per product; use placeholder if empty
        var barcodes = modalRowsData.map(function(d) { var b = (d.barcode || '').trim(); return b || '—'; });
        var merged = {
            product_name: productNames.length ? productNames.join(' + ') : (modalRowsData.length + ' items'),
            barcode: (modalRowsData[0] && (modalRowsData[0].barcode || '').trim()) || (barcodes[0] !== '—' ? barcodes[0] : '') || '',
            quantity: 0, gross_wt: 0, less_wt: 0, net_wt: 0, pure_wt: 0, final_wt: 0,
            amount: 0, discount: 0, making_amount: 0, stone_amount: 0, other_amount: 0,
            diamond_amount: 0, tax: 0, net_amt: 0, net_amt_tax: 0, metal_value: 0,
            purchase_amount: 0, sale_amount: 0, sale_amount_with: 0, reverse: 0
        };
        modalRowsData.forEach(function(d) {
            merged.quantity += parseFloat(d.quantity) || 0;
            merged.gross_wt += parseFloat(d.gross_wt) || 0;
            merged.less_wt += parseFloat(d.less_wt) || 0;
            merged.net_wt += parseFloat(d.net_wt) || 0;
            merged.pure_wt += parseFloat(d.pure_wt) || 0;
            merged.final_wt += parseFloat(d.final_wt) || 0;
            merged.amount += parseFloat(d.amount) || 0;
            merged.discount += parseFloat(d.discount) || 0;
            merged.making_amount += parseFloat(d.making_amount) || 0;
            merged.stone_amount += parseFloat(d.stone_amount) || 0;
            merged.other_amount += parseFloat(d.other_amount) || 0;
            merged.diamond_amount += parseFloat(d.diamond_amount) || 0;
            merged.tax += parseFloat(d.tax) || 0;
            merged.net_amt += parseFloat(d.net_amt) || 0;
            merged.net_amt_tax += parseFloat(d.net_amt_tax) || 0;
            merged.metal_value += parseFloat(d.metal_value) || 0;
            merged.purchase_amount += parseFloat(d.purchase_amount) || 0;
            merged.sale_amount += parseFloat(d.sale_amount) || 0;
            merged.sale_amount_with += parseFloat(d.sale_amount_with) || 0;
            merged.reverse += parseFloat(d.reverse) || 0;
        });
        // Diamond & Stones: use ONLY Jewellery category Net Amt+Tax for merged total (exclude Diamonds and GemStones)
        var jewelleryRowEdit = modalRowsData.filter(function(d) { return (String(d.category || '').trim() === 'Jewellery'); })[0];
        if (jewelleryRowEdit && modalRowsData.some(function(d) { return (String(d.category || '').trim() === 'Diamonds') || (String(d.category || '').trim() === 'GemStones'); })) {
            var jNetAmtEdit = parseFloat(jewelleryRowEdit.net_amt) || 0;
            var jNetAmtTaxEdit = parseFloat(jewelleryRowEdit.net_amt_tax) || 0;
            if (jNetAmtTaxEdit <= 0) jNetAmtTaxEdit = jNetAmtEdit;
            merged.net_amt = jNetAmtEdit;
            merged.net_amt_tax = jNetAmtTaxEdit;
            merged.amount = jNetAmtEdit;
            merged.purchase_amount = jNetAmtEdit;
            merged.sale_amount = jNetAmtEdit;
            merged.sale_amount_with = jNetAmtTaxEdit;
            merged.metal_value = parseFloat(jewelleryRowEdit.metal_value) || 0;
            merged.other_amount = parseFloat(jewelleryRowEdit.other_amount) || 0;
            merged.making_amount = parseFloat(jewelleryRowEdit.making_amount) || 0;
            merged.discount = parseFloat(jewelleryRowEdit.discount) || 0;
            merged.stone_amount = parseFloat(jewelleryRowEdit.stone_amount) || 0;
            merged.diamond_amount = parseFloat(jewelleryRowEdit.diamond_amount) || 0;
            merged.tax = parseFloat(jewelleryRowEdit.tax) || 0;
        }
        row.setAttribute('data-group-items', JSON.stringify(modalRowsData));
        var q = row.querySelector('[data-field="quantity"]'); if (q) q.value = (merged.quantity || 0).toFixed(2);
        var g = row.querySelector('[data-field="gross_wt"]'); if (g) g.value = (merged.gross_wt || 0).toFixed(3);
        var l = row.querySelector('[data-field="less_wt"]'); if (l) l.value = (merged.less_wt || 0).toFixed(3);
        var p = row.querySelector('[data-field="purity"]'); if (p) p.value = (parseFloat(modalRowsData[0].purity) || 0).toFixed(2);
        var f = row.querySelector('[data-field="final_wt"]'); if (f) f.value = (merged.final_wt || 0).toFixed(3);
        var n = row.querySelector('[data-column="net-wt"]'); if (n) n.textContent = (merged.net_wt || 0).toFixed(3);
        var pw = row.querySelector('[data-column="pure-wt"]'); if (pw) pw.textContent = (merged.pure_wt || 0).toFixed(3);
        var m = row.querySelector('[data-field="making"]'); if (m) m.value = (merged.making_amount || 0).toFixed(2);
        var st = row.querySelector('[data-field="stone_charges"]'); if (st) st.value = (merged.stone_amount || 0).toFixed(2);
        var o = row.querySelector('[data-field="other_charges"]'); if (o) o.value = (merged.other_amount || 0).toFixed(2);
        var d = row.querySelector('[data-field="diamond_value"]'); if (d) d.value = (merged.diamond_amount || 0).toFixed(2);
        var t = row.querySelector('[data-field="tax"]'); if (t) t.value = (merged.tax || 0).toFixed(2);
        var amt = row.querySelector('[data-column="amount"]'); if (amt) amt.textContent = (merged.amount || 0).toFixed(2);
        var na = row.querySelector('[data-column="net-amt"]'); if (na) na.textContent = (merged.net_amt || 0).toFixed(2);
        var nat = row.querySelector('[data-column="net-amt-tax"]'); if (nat) nat.textContent = (merged.net_amt_tax || 0).toFixed(2);
        var desc = row.querySelector('[data-column="product"] a'); if (desc) desc.textContent = merged.product_name;
        var bc = row.querySelector('[data-column="barcode"] span'); if (bc) bc.textContent = merged.barcode;
        var photoCell = row.querySelector('[data-column="photo"]');
        var tabKey = (typeof currentMetalId !== 'undefined' && currentMetalId !== null) ? String(currentMetalId) : '';
        var groupImagePayload = window.productModalGroupImageByTab && window.productModalGroupImageByTab[tabKey];
        if (groupImagePayload) {
            row.setAttribute('data-group-image', (typeof groupImagePayload === 'object' && groupImagePayload != null) ? JSON.stringify(groupImagePayload) : groupImagePayload);
        }
        if (photoCell) {
            var img = photoCell.querySelector('img');
            var placeholder = photoCell.querySelector('.text-muted');
            var primaryUrl = typeof getGroupImagePrimary === 'function' ? getGroupImagePrimary(groupImagePayload) : (typeof groupImagePayload === 'string' ? groupImagePayload : '');
            if (primaryUrl && img) {
                img.src = primaryUrl;
                img.style.display = '';
                if (placeholder) placeholder.style.display = 'none';
            } else if (placeholder) {
                placeholder.style.display = '';
                if (img) img.style.display = 'none';
            }
        }
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
        
        // Update product name cell
        const productCell = productListRow.querySelector('[data-column="product"]');
        if (productCell) {
            productCell.innerHTML = `<a href="javascript:void(0)" style="color: #11294b; text-decoration: underline;">${escapeHtml(rowData.product_name || '')}</a>`;
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
    
    // addModalRowCalculationListeners, calculateModalRowNetWeight from product-modal-add-item-common.js
    
    // Update summary row in table footer (removed - no footer in this design)
    function updateSummaryRow() {
        // Summary calculations moved to updateSummaryPanel
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
            const amount = parseFloat(row.querySelector('[data-column="amount"]')?.textContent) || 0;
            const netAmt = parseFloat(row.querySelector('[data-column="net-amt"]')?.textContent) || 0;
            const netAmtTax = parseFloat(row.querySelector('[data-column="net-amt-tax"]')?.textContent) || 0;
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
            var fq = document.getElementById('footerQuantity'); if (fq) fq.textContent = totalQuantity.toFixed(2);
            var fg = document.getElementById('footerGrossWt'); if (fg) fg.textContent = totalGrossWt.toFixed(1);
            var ff = document.getElementById('footerFinalWt'); if (ff) ff.textContent = totalFinalWt.toFixed(1);
            var fn = document.getElementById('footerNetWt'); if (fn) fn.textContent = totalNetWt.toFixed(1);
            var fp = document.getElementById('footerPureWt'); if (fp) fp.textContent = totalPureWt.toFixed(3);
            var fm = document.getElementById('footerMaking'); if (fm) fm.textContent = totalMaking;
            var ft = document.getElementById('footerTax'); if (ft) ft.textContent = totalTax;
            var famt = document.getElementById('footerAmount'); if (famt) famt.textContent = totalAmount.toFixed(2);
            var fna = document.getElementById('footerNetAmt'); if (fna) fna.textContent = totalNetAmt.toFixed(2);
            var fnat = document.getElementById('footerNetAmtTax'); if (fnat) fnat.textContent = totalNetAmtTax.toFixed(2);
            var fsc = document.getElementById('footerStoneCharges'); if (fsc) fsc.textContent = totalStoneCharges.toFixed(2);
            var foc = document.getElementById('footerOtherCharges'); if (foc) foc.textContent = totalOtherCharges.toFixed(2);
            var fdv = document.getElementById('footerDiamondValue'); if (fdv) fdv.textContent = totalDiamondValue.toFixed(2);
            var fgv = document.getElementById('footerGemstoneValue'); if (fgv) fgv.textContent = totalGemstoneValue.toFixed(2);
            var fr = document.getElementById('footerRate'); if (fr) fr.textContent = totalRate.toFixed(2);
            var fmv = document.getElementById('footerMetalValue'); if (fmv) fmv.textContent = totalMetalValue.toFixed(2);
            var fdc = document.getElementById('footerDiscount'); if (fdc) fdc.textContent = totalDiscount.toFixed(2);
            var fma = document.getElementById('footerMakingAmount'); if (fma) fma.textContent = totalMakingAmount.toFixed(2);
            var fsa = document.getElementById('footerStoneAmount'); if (fsa) fsa.textContent = totalStoneAmount.toFixed(2);
            var foa = document.getElementById('footerOtherAmount'); if (foa) foa.textContent = totalOtherAmount.toFixed(2);
            var fda = document.getElementById('footerDiamondAmount'); if (fda) fda.textContent = totalDiamondAmount.toFixed(2);
            var fpa = document.getElementById('footerPurchaseAmount'); if (fpa) fpa.textContent = totalPurchaseAmount.toFixed(2);
            var fsale = document.getElementById('footerSaleAmount'); if (fsale) fsale.textContent = totalSaleAmount.toFixed(2);
            var fsaw = document.getElementById('footerSaleAmountWith'); if (fsaw) fsaw.textContent = totalSaleAmountWith.toFixed(2);
            var frev = document.getElementById('footerReverse'); if (frev) frev.textContent = totalReverse.toFixed(2);
        } else if (footer) {
            footer.style.display = 'none';
        }
        
        // Get discount values
        const discountPercent = parseFloat(document.getElementById('discountPercent')?.value || 0);
        const discountAmount = parseFloat(document.getElementById('discountAmount')?.value || 0);
        
        // Get additional amount
        const additionalAmt = parseFloat(document.getElementById('summaryAdditionalAmt')?.textContent || 0);
        
        // Calculate totals
        const summaryTotal = document.getElementById('summaryTotal');
        if (summaryTotal) summaryTotal.textContent = totalNetAmtTax.toFixed(2);
        
        // Net Total = Total + Additional Amount
        const netTotal = totalNetAmtTax + additionalAmt;
        const summaryNetTotal = document.getElementById('summaryNetTotal');
        if (summaryNetTotal) summaryNetTotal.textContent = netTotal.toFixed(2);
        
        // Grand Total = Net Total - Discount
        let grandTotal = netTotal - discountAmount;
        // Apply round off when checkbox is checked (adjustment amount, can be + or -)
        const roundOffCheck = document.getElementById('roundOff');
        const roundOffInput = document.getElementById('roundOffValue');
        const roundOffApplied = roundOffCheck && roundOffCheck.checked && roundOffInput ? (parseFloat(roundOffInput.value) || 0) : 0;
        grandTotal = grandTotal + roundOffApplied;
        const summaryGrandTotal = document.getElementById('summaryGrandTotal');
        if (summaryGrandTotal) summaryGrandTotal.textContent = grandTotal.toFixed(2);
        
        // Calculate paid amounts from payment table
        const paymentRows = document.querySelectorAll('#paymentTableBody tr:not(.no-payment-row)');
        let paidAmt = 0; // Total paid (current order + previous balance)
        let paidCurrentOrderAmt = 0; // Paid for current order only
        let paidPreviousBalanceAmt = 0; // Paid for previous balance only
        
        paymentRows.forEach(function(row) {
            const totalAmt = parseFloat(row.querySelector('[data-payment-amount]')?.textContent || 0);
            const prevBalAmt = parseFloat(row.getAttribute('data-previous-balance-amount') || 0);
            const currentOrderAmt = parseFloat(row.getAttribute('data-current-order-amount') || (totalAmt - prevBalAmt));
            
            paidAmt += totalAmt; // Total payment includes both current order and previous balance
            paidCurrentOrderAmt += currentOrderAmt;
            paidPreviousBalanceAmt += prevBalAmt;
        });
        
        // Do NOT auto-clear all payments when paid > grandTotal (e.g. user entered 2000 instead of 1000 in UPI).
        // Overpayment shows as negative Balance Amt (customer credit). Ledger + next invoice Previous Balance reflect it.
        
        const summaryPaidAmt = document.getElementById('summaryPaidAmt');
        if (summaryPaidAmt) summaryPaidAmt.textContent = paidAmt.toFixed(2);
        
        // Get original previous balance (from ledger/loadCustomerBalance - can be positive or negative)
        const previousBalanceEl = document.getElementById('previousBalanceAmount');
        let originalPreviousBalance = 0;
        
        if (previousBalanceEl) {
            const storedOriginal = previousBalanceEl.getAttribute('data-original-balance');
            if (storedOriginal !== null && storedOriginal !== '') {
                originalPreviousBalance = parseFloat(storedOriginal) || 0;
            } else {
                const textContentValue = parseFloat(previousBalanceEl.textContent || 0);
                originalPreviousBalance = textContentValue;
                previousBalanceEl.setAttribute('data-original-balance', originalPreviousBalance.toFixed(2));
            }
        }
        
        // Calculate remaining previous balance (original - paid towards previous balance)
        let remainingPreviousBalance = originalPreviousBalance - paidPreviousBalanceAmt;
        
        // For sale invoices: if remaining is negative (customer has credit), treat as 0 for "amount to pay"
        const remainingForDisplay = remainingPreviousBalance < 0 ? 0 : remainingPreviousBalance;
        
        // Show original previous balance (e.g. -1000) so it matches Payment Voucher; do NOT overwrite with remaining
        // The "Previous Balance" display is the ledger balance; payment modals use remaining for "amount to pay"
        if (previousBalanceEl) {
            const currentDisplay = previousBalanceEl.getAttribute('data-original-balance');
            previousBalanceEl.textContent = (currentDisplay !== null && currentDisplay !== '') ? parseFloat(currentDisplay).toFixed(2) : remainingForDisplay.toFixed(2);
        }
        
        // Balance Amt: if "Use previous balance" is checked, deduct the entered amount from amount due
        const usePreviousBalanceCheck = document.getElementById('usePreviousBalanceCheck');
        const previousBalanceUseAmountEl = document.getElementById('previousBalanceUseAmount');
        const usePreviousBalance = usePreviousBalanceCheck && usePreviousBalanceCheck.checked;
        const amountUseFromPrevious = (usePreviousBalance && previousBalanceUseAmountEl) ? (parseFloat(previousBalanceUseAmountEl.value) || 0) : 0;
        // Amount due = Grand Total - Paid; then subtract how much we use from previous balance (when checked)
        let balanceAmt = grandTotal - paidCurrentOrderAmt - (usePreviousBalance ? amountUseFromPrevious : 0);
        const summaryBalanceAmt = document.getElementById('summaryBalanceAmt');
        if (summaryBalanceAmt) {
            summaryBalanceAmt.textContent = balanceAmt.toFixed(2);
            summaryBalanceAmt.style.color = balanceAmt < 0 ? '#059669' : '#c5a864';
        }
    }
    
    // Delete product row
    function deleteProductRow(rowId) {
        if (confirm('Are you sure you want to remove this product?')) {
            const row = document.getElementById(rowId);
            if (row) {
                row.remove();
                checkEmptyTable();
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
        
        // Merged row: restore all N records in modal for editing
        const groupItemsJson = row.getAttribute('data-group-items');
        if (groupItemsJson) {
            try {
                const groupItems = JSON.parse(groupItemsJson);
                if (groupItems && groupItems.length > 0) {
                    // Read current making amount from row input (data-group-items is not updated when user edits)
                    const rowMakingInput = row.querySelector('[data-column="making-amount"] input') || row.querySelector('[data-field="making"]');
                    let rowMakingAmount = parseFloat(rowMakingInput?.value || row.querySelector('[data-column="making-amount"]')?.textContent) || 0;
                    if (rowMakingAmount === 0 && groupItems.length > 0) {
                        const fromStored = groupItems.reduce(function(sum, d) { return sum + (parseFloat(d.making_amount) || parseFloat(d.making) || 0); }, 0);
                        if (fromStored > 0) rowMakingAmount = fromStored;
                    }
                    const makingPerItem = groupItems.length > 0 ? (rowMakingAmount / groupItems.length) : 0;
                    groupItems.forEach(function(d) {
                        d.making_amount = makingPerItem;
                        d.making = makingPerItem;
                        if (makingPerItem > 0 && (parseFloat(d.making_rate) || 0) === 0 && (String(d.making_type || 'Fix').toLowerCase()) === 'fix') {
                            d.making_rate = makingPerItem;
                        }
                    });
                    currentEditingRowId = rowId;
                    window.currentEditingRowId = rowId;
                    // Pick metal_id for tab: if any item has diamond_category (Jewellery/Diamonds/GemStones), use Diamond & Stones tab
                    var bestMetalId = null;
                    var hasDiamondCategory = groupItems.some(function(d) {
                        var cat = (d.diamond_category || d.category || '').toString().trim();
                        return cat === 'Jewellery' || cat === 'Diamonds' || cat === 'GemStones';
                    });
                    if (hasDiamondCategory && typeof metals !== 'undefined' && metals.length) {
                        var diamondMetal = metals.find(function(m) {
                            var name = (m.display_name || m.name || '').toString();
                            return name.toLowerCase().indexOf('diamond') !== -1;
                        });
                        if (diamondMetal) bestMetalId = String(diamondMetal.id);
                    }
                    if (!bestMetalId) {
                        var metalCounts = {};
                        groupItems.forEach(function(d) {
                            var m = (d.metal_id != null && d.metal_id !== '') ? String(d.metal_id) : '_';
                            if (m !== '_') metalCounts[m] = (metalCounts[m] || 0) + 1;
                        });
                        var maxCount = 0;
                        for (var m in metalCounts) {
                            if (metalCounts[m] > maxCount) { maxCount = metalCounts[m]; bestMetalId = m; }
                        }
                        if (!bestMetalId && groupItems[0]) bestMetalId = (groupItems[0].metal_id != null && groupItems[0].metal_id !== '') ? String(groupItems[0].metal_id) : null;
                    }
                    openProductModal();
                    setTimeout(function() {
                        if (bestMetalId && typeof switchToMetalTab === 'function') switchToMetalTab(bestMetalId);
                        const productListBody = document.getElementById('productListBody');
                        if (!productListBody) return;
                        productListBody.innerHTML = '';
                        groupItems.forEach(function(d) {
                            var pair = getItemAndProductFromModalRowData(d);
                            if (typeof addProductRowToSelectionTable === 'function') {
                                addProductRowToSelectionTable(pair.item, pair.product);
                            }
                        });
                        if (typeof updateJewelleryDiamondCaratFromDiamondAndGemstone === 'function') updateJewelleryDiamondCaratFromDiamondAndGemstone();
                        if (typeof updateJewelleryNetAmountAndFinal === 'function') updateJewelleryNetAmountAndFinal();
                    }, 500);
                    return;
                }
            } catch (e) { console.error('Parse data-group-items:', e); }
        }
        
        // Single product row: existing flow
        const productId = row.getAttribute('data-product-id');
        const characteristicId = row.getAttribute('data-characteristic-id');
        
        if (!productId) {
            alert('Product ID not found');
            return;
        }
        
        // Extract all data from the Product List table row (read making from input so Edit shows current value)
        const makingInput = row.querySelector('[data-column="making-amount"] input') || row.querySelector('[data-field="making"]');
        const makingVal = parseFloat(makingInput?.value || row.querySelector('[data-column="making-amount"]')?.textContent?.trim() || 0);
        const rowData = {
            product_id: productId,
            characteristic_id: characteristicId || '',
            quantity: parseFloat(row.querySelector('[data-field="quantity"]')?.value || row.querySelector('[data-column="quantity"]')?.textContent?.trim() || 1),
            gross_wt: parseFloat(row.querySelector('[data-field="gross_wt"]')?.value || row.querySelector('[data-column="gross-wt"]')?.textContent?.trim() || 0),
            less_wt: parseFloat(row.querySelector('[data-field="less_wt"]')?.value || row.querySelector('[data-column="less-wt"]')?.textContent?.trim() || 0),
            purity: parseFloat(row.querySelector('[data-field="purity"]')?.value || row.querySelector('[data-column="purity"]')?.textContent?.trim() || 1),
            final_wt: parseFloat(row.querySelector('[data-field="final_wt"]')?.value || row.querySelector('[data-column="final-wt"]')?.textContent?.trim() || 0),
            making: makingVal,
            design_no: row.querySelector('[data-field="design_no"]')?.value || row.querySelector('[data-column="design-no"]')?.textContent?.trim() || '',
            tax: parseFloat(row.querySelector('[data-field="tax"]')?.value || row.querySelector('[data-column="tax"]')?.textContent?.trim() || 0),
            amount: parseFloat(row.querySelector('[data-column="amount"]')?.textContent?.trim() || 0),
            net_amt: parseFloat(row.querySelector('[data-column="net-amt"]')?.textContent?.trim() || 0),
            net_amt_tax: parseFloat(row.querySelector('[data-column="net-amt-tax"]')?.textContent?.trim() || 0),
            stone_charges: parseFloat(row.querySelector('[data-field="stone_charges"]')?.value || row.querySelector('[data-column="stone-charges"]')?.textContent?.trim() || 0),
            other_charges: parseFloat(row.querySelector('[data-field="other_charges"]')?.value || row.querySelector('[data-column="other-charges"]')?.textContent?.trim() || 0),
            diamond_value: parseFloat(row.querySelector('[data-field="diamond_value"]')?.value || row.querySelector('[data-column="diamond-value"]')?.textContent?.trim() || 0),
            gemstone_value: parseFloat(row.querySelector('[data-field="gemstone_value"]')?.value || row.querySelector('[data-column="gemstone-value"]')?.textContent?.trim() || 0),
            product_name: row.querySelector('[data-column="product"] a')?.textContent?.trim() || row.querySelector('[data-column="product"]')?.textContent?.trim() || ''
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
                    currentEditingRowId = rowId;
                    window.currentEditingRowId = rowId; // Also set on window for backward compatibility
                    
                    // Wait for modal to be fully shown, then add row with data
                    setTimeout(function() {
                        // Clear the product list body
                        const productListBody = document.getElementById('productListBody');
                        if (productListBody) {
                            productListBody.innerHTML = '';
                        }
                        
                        // Create item object from row data and product data (set making_rate so modal Fix type shows amount)
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
                            making_rate: rowData.making > 0 ? rowData.making : (data.product.making_rate || 0),
                            making_type: 'Fix',
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
                            calculation_mode: 'Rate X Gross Wt',
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
                            article: rowData.design_no,
                            vat_value: data.product.vat_value != null ? data.product.vat_value : '',
                            total_tax_percent: data.product.total_tax_percent != null ? data.product.total_tax_percent : ''
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
            const prevBalanceAmtEl = document.getElementById('previousBalanceAmount');
            const prevBalanceGoldEl = document.getElementById('previousBalanceGold');
            const prevBalanceSilverEl = document.getElementById('previousBalanceSilver');
            const prevBalanceDiamondEl = document.getElementById('previousBalanceDiamond');
            const prevBalanceGemstoneEl = document.getElementById('previousBalanceGemstone');
            if (prevBalanceAmtEl) { prevBalanceAmtEl.textContent = '0'; prevBalanceAmtEl.removeAttribute('data-original-balance'); }
            if (prevBalanceGoldEl) prevBalanceGoldEl.textContent = '0';
            if (prevBalanceSilverEl) prevBalanceSilverEl.textContent = '0';
            if (prevBalanceDiamondEl) prevBalanceDiamondEl.textContent = '0';
            if (prevBalanceGemstoneEl) prevBalanceGemstoneEl.textContent = '0';
            const usePrevChk = document.getElementById('usePreviousBalanceCheck');
            const useAmountRow = document.getElementById('previousBalanceUseAmountRow');
            const useAmountInput = document.getElementById('previousBalanceUseAmount');
            if (usePrevChk) { usePrevChk.checked = false; }
            if (useAmountRow) useAmountRow.style.display = 'none';
            if (useAmountInput) { useAmountInput.value = '0.00'; useAmountInput.removeAttribute('max'); }
            if (typeof updateSummaryPanel === 'function') {
                updateSummaryPanel();
            }
            return;
        }
        
        // Fetch supplier/customer balance from tbl_customer_ledger (same as Payment Voucher)
        let url = 'ajax/get-customer-balance.php?';
        if (customerId && customerId !== '') {
            url += 'customer_id=' + encodeURIComponent(customerId) + '&';
        }
        if (customerName) {
            url += 'customer_name=' + encodeURIComponent(customerName) + '&';
        }
        url += 'type=supplier';
        
        console.log('Loading customer balance:', { customerId, customerName, url });
        
        fetch(url)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                const prevBalanceAmtEl = document.getElementById('previousBalanceAmount');
                const prevBalanceGoldEl = document.getElementById('previousBalanceGold');
                const prevBalanceSilverEl = document.getElementById('previousBalanceSilver');
                const prevBalanceDiamondEl = document.getElementById('previousBalanceDiamond');
                const prevBalanceGemstoneEl = document.getElementById('previousBalanceGemstone');
                if (data.status === 'success' && (data.balance || data.original_balance)) {
                    const rawAmount = data.original_balance ? parseFloat(data.original_balance.amount) || 0 : parseFloat(data.balance.amount) || 0;
                    const gold = data.original_balance ? parseFloat(data.original_balance.gold) || 0 : parseFloat(data.balance.gold) || 0;
                    const silver = data.original_balance ? parseFloat(data.original_balance.silver) || 0 : parseFloat(data.balance.silver) || 0;
                    const diamond = data.original_balance ? parseFloat(data.original_balance.diamond) || 0 : parseFloat(data.balance.diamond) || 0;
                    const gemstone = data.original_balance ? parseFloat(data.original_balance.gemstone) || 0 : parseFloat(data.balance.gemstone) || 0;
                    if (prevBalanceAmtEl) { prevBalanceAmtEl.textContent = rawAmount.toFixed(2); prevBalanceAmtEl.setAttribute('data-original-balance', rawAmount.toFixed(2)); }
                    if (prevBalanceGoldEl) prevBalanceGoldEl.textContent = gold.toFixed(3);
                    if (prevBalanceSilverEl) prevBalanceSilverEl.textContent = silver.toFixed(3);
                    if (prevBalanceDiamondEl) prevBalanceDiamondEl.textContent = diamond.toFixed(3);
                    if (prevBalanceGemstoneEl) prevBalanceGemstoneEl.textContent = gemstone.toFixed(3);
                    // "Use previous balance": set max amount user can enter
                    const useAmountInput = document.getElementById('previousBalanceUseAmount');
                    if (useAmountInput) {
                        const availableToUse = Math.abs(rawAmount);
                        useAmountInput.setAttribute('max', availableToUse.toFixed(2));
                        if (!document.getElementById('usePreviousBalanceCheck').checked) {
                            useAmountInput.value = '0.00';
                        }
                    }
                } else {
                    console.warn('Balance not found or error in response:', data);
                    if (prevBalanceAmtEl) { prevBalanceAmtEl.textContent = '0'; prevBalanceAmtEl.setAttribute('data-original-balance', '0'); }
                    if (prevBalanceGoldEl) prevBalanceGoldEl.textContent = '0';
                    if (prevBalanceSilverEl) prevBalanceSilverEl.textContent = '0';
                    if (prevBalanceDiamondEl) prevBalanceDiamondEl.textContent = '0';
                    if (prevBalanceGemstoneEl) prevBalanceGemstoneEl.textContent = '0';
                }
                if (typeof updateSummaryPanel === 'function') updateSummaryPanel();
            })
            .catch(error => {
                console.error('Error loading customer balance:', error);
                const a = document.getElementById('previousBalanceAmount');
                const g = document.getElementById('previousBalanceGold');
                const s = document.getElementById('previousBalanceSilver');
                const d = document.getElementById('previousBalanceDiamond');
                const gs = document.getElementById('previousBalanceGemstone');
                if (a) { a.textContent = '0'; a.removeAttribute('data-original-balance'); }
                if (g) g.textContent = '0';
                if (s) s.textContent = '0';
                if (d) d.textContent = '0';
                if (gs) gs.textContent = '0';
                if (typeof updateSummaryPanel === 'function') updateSummaryPanel();
            });
    }
    
    // Show Additional Amount modal
    function showAdditionalAmtModal() {
        const currentAmt = parseFloat(document.getElementById('summaryAdditionalAmt')?.textContent || 0);
        const newAmt = prompt('Enter Additional Amount:', currentAmt);
        if (newAmt !== null && !isNaN(parseFloat(newAmt))) {
            document.getElementById('summaryAdditionalAmt').textContent = parseFloat(newAmt).toFixed(2);
            updateSummaryPanel();
        }
    }
    
    // Show Advance Payment modal/details
    function showAdvancePaymentModal() {
        const detailsDiv = document.getElementById('advancePaymentDetails');
        if (detailsDiv) {
            detailsDiv.style.display = detailsDiv.style.display === 'none' ? 'block' : 'none';
        }
    }
    
    // Update discount calculation
    function updateDiscount() {
        const discountPercent = parseFloat(document.getElementById('discountPercent')?.value || 0);
        const summaryTotal = parseFloat(document.getElementById('summaryTotal')?.textContent || 0);
        const additionalAmt = parseFloat(document.getElementById('summaryAdditionalAmt')?.textContent || 0);
        const netTotal = summaryTotal + additionalAmt;
        
        if (discountPercent > 0) {
            const calculatedDiscount = (netTotal * discountPercent) / 100;
            document.getElementById('discountAmount').value = calculatedDiscount.toFixed(2);
        }
        updateSummaryPanel();
    }
    
    // Add Item button/link click - Use event delegation to ensure it works
    $(document).ready(function() {
        // Load customer balance when customer name changes
        const customerNameField = document.getElementById('customerName');
        if (customerNameField) {
            // Load balance when field loses focus (blur)
            customerNameField.addEventListener('blur', function() {
                if (this.value.trim()) {
                    console.log('Customer name blur - loading balance for:', this.value);
                    loadCustomerBalance();
                }
            });
            
            // Load balance when field value changes (for dropdown selections)
            customerNameField.addEventListener('change', function() {
                if (this.value.trim()) {
                    console.log('Customer name change - loading balance for:', this.value);
                    loadCustomerBalance();
                }
            });
            
            // Load balance when user types and stops (debounced input)
            let inputTimeout;
            customerNameField.addEventListener('input', function() {
                clearTimeout(inputTimeout);
                inputTimeout = setTimeout(function() {
                    const customerNameEl = document.getElementById('customerName');
                    if (customerNameEl && customerNameEl.value.trim()) {
                        console.log('Customer name input - loading balance for:', customerNameEl.value);
                        loadCustomerBalance();
                    }
                }, 1000); // Wait 1 second after user stops typing
            });
        }
        
        // Load balance on page load if customer name is already filled (skip in edit mode - we use saved totals)
        // Use multiple attempts with increasing delays to ensure DOM is ready
        setTimeout(function() {
            if (window.isPurchaseInvoiceEditMode) return;
            const customerNameEl = document.getElementById('customerName');
            if (customerNameEl && customerNameEl.value.trim()) {
                console.log('Page load (500ms): Customer name found, loading balance:', customerNameEl.value);
                if (typeof loadCustomerBalance === 'function') {
                    loadCustomerBalance();
                }
            } else {
                console.log('Page load (500ms): Customer name not found or empty');
            }
        }, 500);
        
        // Also try after a longer delay in case DOM takes longer to initialize
        setTimeout(function() {
            if (window.isPurchaseInvoiceEditMode) return;
            const customerNameEl = document.getElementById('customerName');
            if (customerNameEl && customerNameEl.value.trim()) {
                console.log('Page load (1500ms): Customer name found, loading balance:', customerNameEl.value);
                if (typeof loadCustomerBalance === 'function') {
                    loadCustomerBalance();
                }
            }
        }, 1500);
        
        // Also try on window load event (after all resources are loaded)
        window.addEventListener('load', function() {
            setTimeout(function() {
                if (window.isPurchaseInvoiceEditMode) return;
                const customerNameEl = document.getElementById('customerName');
                if (customerNameEl && customerNameEl.value.trim()) {
                    console.log('Window load: Customer name found, loading balance:', customerNameEl.value);
                    if (typeof loadCustomerBalance === 'function') {
                        loadCustomerBalance();
                    }
                }
            }, 200);
        });
        
        // Add discount calculation listeners
        const discountPercentField = document.getElementById('discountPercent');
        const discountAmountField = document.getElementById('discountAmount');
        if (discountPercentField) {
            discountPercentField.addEventListener('input', updateDiscount);
            discountPercentField.addEventListener('change', updateDiscount);
        }
        if (discountAmountField) {
            discountAmountField.addEventListener('input', function() {
                updateSummaryPanel();
            });
            discountAmountField.addEventListener('change', function() {
                updateSummaryPanel();
            });
        }
        // Round off: when checkbox checked, auto-calculate round-off amount (nearest whole number); when unchecked, clear to 0
        const roundOffCheckEl = document.getElementById('roundOff');
        const roundOffValueEl = document.getElementById('roundOffValue');
        if (roundOffCheckEl) {
            roundOffCheckEl.addEventListener('change', function() {
                if (this.checked && roundOffValueEl) {
                    var summaryNetTotal = document.getElementById('summaryNetTotal');
                    var discountAmt = parseFloat(document.getElementById('discountAmount')?.value || 0);
                    var netTotal = summaryNetTotal ? parseFloat(summaryNetTotal.textContent.replace(/,/g, '')) || 0 : 0;
                    var grandTotalBeforeRoundOff = netTotal - discountAmt;
                    var roundedTotal = Math.round(grandTotalBeforeRoundOff);
                    var roundOffAmount = roundedTotal - grandTotalBeforeRoundOff;
                    roundOffValueEl.value = roundOffAmount.toFixed(2);
                } else if (roundOffValueEl) {
                    roundOffValueEl.value = '0.00';
                }
                if (typeof updateSummaryPanel === 'function') updateSummaryPanel();
            });
        }
        if (roundOffValueEl) {
            roundOffValueEl.addEventListener('input', function() {
                if (typeof updateSummaryPanel === 'function') updateSummaryPanel();
            });
            roundOffValueEl.addEventListener('change', function() {
                if (typeof updateSummaryPanel === 'function') updateSummaryPanel();
            });
        }
        // Use previous balance: show/hide amount row and recalc
        const usePreviousBalanceCheck = document.getElementById('usePreviousBalanceCheck');
        const previousBalanceUseAmountRow = document.getElementById('previousBalanceUseAmountRow');
        const previousBalanceUseAmountInput = document.getElementById('previousBalanceUseAmount');
        if (usePreviousBalanceCheck) {
            usePreviousBalanceCheck.addEventListener('change', function() {
                if (previousBalanceUseAmountRow) {
                    previousBalanceUseAmountRow.style.display = this.checked ? 'flex' : 'none';
                }
                if (!this.checked && previousBalanceUseAmountInput) {
                    previousBalanceUseAmountInput.value = '0.00';
                }
                if (typeof updateSummaryPanel === 'function') updateSummaryPanel();
            });
        }
        if (previousBalanceUseAmountInput) {
            previousBalanceUseAmountInput.addEventListener('input', function() {
                if (typeof updateSummaryPanel === 'function') updateSummaryPanel();
            });
            previousBalanceUseAmountInput.addEventListener('change', function() {
                if (typeof updateSummaryPanel === 'function') updateSummaryPanel();
            });
        }
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
    
    // Helper: get current Diamond Category filter for modal (when on Diamond tab)
    function getModalDiamondCategoryFilter() {
        var sel = document.getElementById('modalDiamondCategoryFilter');
        if (!sel) return '';
        var v = (sel.value || '').trim();
        return (v && ['Diamonds', 'GemStones', 'Jewellery'].indexOf(v) !== -1) ? v : '';
    }
    
    // updateJewelleryDiamondCaratFromDiamondAndGemstone: product-modal-add-item-common.js (Jewellery carat/D.Weight only sync from Diamonds+GemStones when those rows exist)
    
    // Jewellery category: Net Amount = Metal Value + Diamond + Stone + Making + Other - Discount; Final = NetAmt + Tax
    function updateJewelleryNetAmountAndFinal() {
        var tbody = document.getElementById('productListBody');
        if (!tbody) return;
        var rows = tbody.querySelectorAll('.product-row');
        var totalMetalValue = 0;
        var totalMaking = 0;
        var totalStone = 0;
        var totalDiamond = 0;
        var totalOther = 0;
        var totalDiscount = 0;
        var totalTax = 0;
        var jewelleryRows = [];
        for (var i = 0; i < rows.length; i++) {
            var r = rows[i];
            var catSel = r.querySelector('[data-column="category"] select');
            var catVal = (catSel && catSel.value) ? (catSel.value || '').trim() : '';
            if (catVal === 'Jewellery' || catVal === 'Diamonds' || catVal === 'GemStones') {
                var mvInp = r.querySelector('[data-column="metal-value"] input');
                if (mvInp) totalMetalValue += parseFloat(mvInp.value) || 0;
                var maInp = r.querySelector('[data-column="making-amount"] input');
                if (maInp) totalMaking += parseFloat(maInp.value) || 0;
                var saInp = r.querySelector('[data-column="stone-amount"] input');
                if (saInp) totalStone += parseFloat(saInp.value) || 0;
                var daInp = r.querySelector('[data-column="diamond-amount"] input');
                if (daInp) totalDiamond += parseFloat(daInp.value) || 0;
                var oaInp = r.querySelector('[data-column="other-amount"] input');
                if (oaInp) totalOther += parseFloat(oaInp.value) || 0;
                var dcInp = r.querySelector('[data-column="discount"] input');
                if (dcInp) totalDiscount += parseFloat(dcInp.value) || 0;
                var taxInp = r.querySelector('[data-column="tax"] input');
                if (taxInp) totalTax += parseFloat(taxInp.value) || 0;
                if (catVal === 'Jewellery') jewelleryRows.push(r);
            }
        }
        var netAmt = totalMetalValue + totalMaking + totalStone + totalDiamond + totalOther - totalDiscount;
        if (netAmt < 0) netAmt = 0;
        var finalAmount = netAmt + totalTax;
        for (var j = 0; j < jewelleryRows.length; j++) {
            var jr = jewelleryRows[j];
            var netAmtInp = jr.querySelector('[data-column="net-amt"] input');
            var netAmtTaxInp = jr.querySelector('[data-column="net-amt-tax"] input');
            if (netAmtInp) netAmtInp.value = netAmt.toFixed(2);
            if (netAmtTaxInp) netAmtTaxInp.value = finalAmount.toFixed(2);
        }
    }
    
    // Product search in modal
    const modalProductSearchInput = document.getElementById('modalProductSearchInput');
    if (modalProductSearchInput) {
        let searchTimeout;
        modalProductSearchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const search = this.value;
            searchTimeout = setTimeout(function() {
                if (currentMetalId) {
                    loadProducts(currentMetalId, search, getModalDiamondCategoryFilter());
                }
            }, 300); // Debounce search
        });
    }
    
    // Diamond Category filter dropdown (Product Selection modal): reload products when filter changes
    const modalDiamondCategoryFilter = document.getElementById('modalDiamondCategoryFilter');
    if (modalDiamondCategoryFilter) {
        modalDiamondCategoryFilter.addEventListener('change', function() {
            if (currentMetalId) {
                var searchVal = (modalProductSearchInput && modalProductSearchInput.value) ? modalProductSearchInput.value : '';
                loadProducts(currentMetalId, searchVal, getModalDiamondCategoryFilter());
            }
        });
    }
    
    // Modal Add Button - Add all products directly to table (no checkbox required)
    const modalAddBtn = document.getElementById('modalAddBtn');
    if (modalAddBtn) {
        modalAddBtn.addEventListener('click', function() {
            // Get all product rows (not just checked ones)
            const allProductRows = document.querySelectorAll('#productListBody .product-row');
            
            if (allProductRows.length === 0) {
                alert('No products available to add');
                return;
            }
            
            // Check if we're in edit mode
            if (currentEditingRowId) {
                if (typeof updateJewelleryDiamondCaratFromDiamondAndGemstone === 'function') updateJewelleryDiamondCaratFromDiamondAndGemstone();
                if (typeof updateJewelleryNetAmountAndFinal === 'function') updateJewelleryNetAmountAndFinal();
                const mainRow = document.getElementById(currentEditingRowId);
                const groupItemsJson = mainRow ? mainRow.getAttribute('data-group-items') : null;
                if (groupItemsJson) {
                    // Merged row: re-merge all modal rows and update main row
                    const modalRowsData = [];
                    allProductRows.forEach(function(r) { modalRowsData.push(getModalRowDataFromRow(r, true)); });
                    if (modalRowsData.length > 0) {
                        updateMergedRowFromModalRows(currentEditingRowId, modalRowsData);
                    }
                } else {
                    // Single row: update from first modal row
                    const firstRow = allProductRows[0];
                    if (firstRow) {
                        updateProductListRowFromModalRow(currentEditingRowId, firstRow);
                    }
                }
                hideProductModal();
                currentEditingRowId = null;
                updateSummaryPanel();
                return;
            }
            
            // Add mode: Only add rows that are visible (current tab); ignore hidden rows from other tabs
            const productRows = Array.from(allProductRows).filter(function(row) {
                if (!row) return false;
                return row.style.display !== 'none';
            });
            if (productRows.length === 0) {
                alert('No products in current tab. Switch to the tab with products you want to add, or add a product first.');
                return;
            }
            if (typeof updateJewelleryDiamondCaratFromDiamondAndGemstone === 'function') updateJewelleryDiamondCaratFromDiamondAndGemstone();
            if (typeof updateJewelleryNetAmountAndFinal === 'function') updateJewelleryNetAmountAndFinal();
            try {
                // Run modal row calculation so making-amount (and other calculated fields) are up to date before reading
                if (typeof window.calculateModalRowNetWeight === 'function') {
                    productRows.forEach(function(r) { window.calculateModalRowNetWeight(r); });
                }
                var byMetal = {};
                productRows.forEach(function(row) {
                    var metalId = row.getAttribute('data-metal-id') || '';
                    if (!byMetal[metalId]) byMetal[metalId] = [];
                    byMetal[metalId].push(row);
                });
                Object.keys(byMetal).forEach(function(metalId) {
                    var rows = byMetal[metalId];
                    var modalRowsData = [];
                    rows.forEach(function(r) {
                        modalRowsData.push(getModalRowDataFromRow(r, false));
                    });
                    if (modalRowsData.length > 0) addMergedProductsToTable(modalRowsData, metalId);
                });
            } finally {
                // Always clear modal product list after add so user can add more products (modal stays open)
                const productListBody = document.getElementById('productListBody');
                if (productListBody) {
                    productListBody.innerHTML = '<tr><td colspan="77" class="text-center text-muted py-4">Click "Add Product" button to add products for billing...</td></tr>';
                }
                var groupNameInput = document.getElementById('modalGroupName');
                if (groupNameInput) groupNameInput.value = '';
                var commentInput = document.getElementById('modalComment');
                if (commentInput) commentInput.value = '';
            }
            updateSummaryPanel();
        });
    }
    
    // Group image per tab: { primary: dataUrl, images: [dataUrl,...] } or legacy string (single image)
    window.productModalGroupImageByTab = window.productModalGroupImageByTab || {};
    function getGroupImagePrimary(payload) {
        if (!payload) return '';
        if (typeof payload === 'string') return payload;
        if (payload && payload.primary) return payload.primary;
        if (payload && payload.images && payload.images[0]) return payload.images[0];
        return '';
    }
    function applyProductModalGroupImageToPhotoColumns(dataUrl, tabKey) {
        var primary = getGroupImagePrimary(dataUrl);
        if (!primary) return;
        var key = (tabKey === undefined || tabKey === null) ? (currentMetalId === undefined || currentMetalId === null ? '' : String(currentMetalId)) : String(tabKey);
        var photoCells = document.querySelectorAll('#productListTable td[data-column="photo"]');
        photoCells.forEach(function(cell) {
            var row = cell.closest('tr.product-row');
            if (row && key !== '' && (row.getAttribute('data-metal-id') || '') !== key) return;
            var img = cell.querySelector('.product-photo-thumb');
            var placeholder = cell.querySelector('.product-photo-placeholder');
            if (img && placeholder) {
                img.src = primary;
                img.style.display = '';
                img.alt = 'Group photo';
                placeholder.style.display = 'none';
            }
        });
    }
    const productModalUploadImageBtn = document.getElementById('productModalUploadImageBtn');
    const productModalGroupImageInput = document.getElementById('productModalGroupImageInput');
    var addImageModalImages = [];
    var addImageModalPrimaryIndex = 0;
    var addImageModalTabKey = '';

    function openAddImageModal() {
        addImageModalTabKey = (currentMetalId === undefined || currentMetalId === null) ? '' : String(currentMetalId);
        var existing = window.productModalGroupImageByTab && window.productModalGroupImageByTab[addImageModalTabKey];
        addImageModalImages = [];
        addImageModalPrimaryIndex = 0;
        if (existing) {
            if (typeof existing === 'string') {
                addImageModalImages = [existing];
            } else if (existing && Array.isArray(existing.images) && existing.images.length > 0) {
                addImageModalImages = existing.images.slice();
                var idx = existing.primary ? existing.images.indexOf(existing.primary) : -1;
                addImageModalPrimaryIndex = idx >= 0 ? idx : 0;
            } else if (existing && existing.primary) {
                addImageModalImages = [existing.primary];
            }
        }
        addImageRenderModalPreview();
        var modal = document.getElementById('addImageModal');
        if (modal) {
            if (typeof jQuery !== 'undefined' && jQuery.fn.modal) jQuery('#addImageModal').modal('show');
            else { modal.style.display = 'block'; modal.classList.add('show'); }
        }
    }

    function closeAddImageModal() {
        var modal = document.getElementById('addImageModal');
        if (modal) {
            if (typeof jQuery !== 'undefined' && jQuery.fn.modal) jQuery('#addImageModal').modal('hide');
            else { modal.style.display = 'none'; modal.classList.remove('show'); }
        }
        addImageModalImages = [];
        addImageModalPrimaryIndex = 0;
    }

    function addImageRenderModalPreview() {
        var placeholder = document.getElementById('addImagePreviewPlaceholder');
        var previewImg = document.getElementById('addImagePreviewImg');
        var primaryUrl = addImageModalImages[addImageModalPrimaryIndex] || '';
        if (placeholder) placeholder.style.display = primaryUrl ? 'none' : '';
        if (previewImg) {
            if (primaryUrl) { previewImg.src = primaryUrl; previewImg.style.display = 'block'; }
            else { previewImg.style.display = 'none'; previewImg.src = ''; }
        }
        var wrap = document.getElementById('addImageThumbnailsWrap');
        if (!wrap) return;
        var uploadZone = document.getElementById('addImageUploadZone');
        var existingAddZone = wrap.querySelector('#addImageUploadZone');
        var thumbContainer = wrap.querySelector('.addImage-thumb-list');
        if (thumbContainer) thumbContainer.remove();
        var list = document.createElement('div');
        list.className = 'addImage-thumb-list d-flex flex-wrap';
        list.style.gap = '0.5rem';
        addImageModalImages.forEach(function(dataUrl, idx) {
            var box = document.createElement('div');
            box.style.cssText = 'width: 70px; height: 70px; border-radius: 8px; overflow: hidden; position: relative; border: 2px solid ' + (idx === addImageModalPrimaryIndex ? '#11294b' : '#e2e8f0') + '; cursor: pointer; flex-shrink: 0;';
            var img = document.createElement('img');
            img.src = dataUrl;
            img.alt = 'Image ' + (idx + 1);
            img.style.cssText = 'width: 100%; height: 100%; object-fit: cover;';
            box.appendChild(img);
            var x = document.createElement('span');
            x.setAttribute('aria-label', 'Remove');
            x.style.cssText = 'position: absolute; top: 2px; right: 2px; width: 20px; height: 20px; background: rgba(0,0,0,0.6); color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; line-height: 1; cursor: pointer;';
            x.textContent = '×';
            x.onclick = function(ev) { ev.stopPropagation(); addImageRemoveAt(idx); };
            box.appendChild(x);
            box.onclick = function(ev) { if (ev.target !== x) addImageSetPrimary(idx); };
            list.appendChild(box);
        });
        if (existingAddZone && existingAddZone.parentNode) existingAddZone.parentNode.insertBefore(list, existingAddZone.nextSibling);
        else wrap.appendChild(list);
    }

    function addImageAddFiles(files) {
        if (!files || !files.length) return;
        var added = 0;
        function readNext(i) {
            if (i >= files.length) { if (added) addImageRenderModalPreview(); return; }
            var file = files[i];
            if (!file.type || !file.type.match(/^image\//)) { readNext(i + 1); return; }
            var reader = new FileReader();
            reader.onload = function(e) {
                addImageModalImages.push(e.target.result);
                if (addImageModalImages.length === 1) addImageModalPrimaryIndex = 0;
                added++;
                readNext(i + 1);
            };
            reader.readAsDataURL(file);
        }
        readNext(0);
    }

    function addImageRemoveAt(idx) {
        addImageModalImages.splice(idx, 1);
        if (addImageModalPrimaryIndex >= addImageModalImages.length) addImageModalPrimaryIndex = Math.max(0, addImageModalImages.length - 1);
        if (addImageModalPrimaryIndex > idx) addImageModalPrimaryIndex--;
        addImageRenderModalPreview();
    }

    function addImageSetPrimary(idx) {
        addImageModalPrimaryIndex = idx;
        addImageRenderModalPreview();
    }

    if (productModalUploadImageBtn) {
        productModalUploadImageBtn.addEventListener('click', function() {
            openAddImageModal();
        });
    }
    if (productModalGroupImageInput) {
        productModalGroupImageInput.addEventListener('change', function() {
            var file = this.files && this.files[0];
            if (!file || !file.type.match(/^image\//)) { this.value = ''; return; }
            var reader = new FileReader();
            reader.onload = function(e) {
                var dataUrl = e.target.result;
                var tabKey = (currentMetalId === undefined || currentMetalId === null) ? '' : String(currentMetalId);
                window.productModalGroupImageByTab = window.productModalGroupImageByTab || {};
                window.productModalGroupImageByTab[tabKey] = { primary: dataUrl, images: [dataUrl] };
                applyProductModalGroupImageToPhotoColumns({ primary: dataUrl, images: [dataUrl] }, tabKey);
            };
            reader.readAsDataURL(file);
            this.value = '';
        });
    }

    (function setupAddImageModal() {
        var addImageModal = document.getElementById('addImageModal');
        var addImageModalFileInput = document.getElementById('addImageModalFileInput');
        var addImageUploadZone = document.getElementById('addImageUploadZone');
        var addImageModalSaveBtn = document.getElementById('addImageModalSaveBtn');
        var addImageModalCameraBtn = document.getElementById('addImageModalCameraBtn');
        var addImageModalClose = document.getElementById('addImageModalClose');

        if (addImageUploadZone && addImageModalFileInput) {
            addImageUploadZone.addEventListener('click', function() { addImageModalFileInput.click(); });
            addImageUploadZone.addEventListener('dragover', function(e) { e.preventDefault(); addImageUploadZone.style.background = '#e2e8f0'; });
            addImageUploadZone.addEventListener('dragleave', function() { addImageUploadZone.style.background = '#f1f5f9'; });
            addImageUploadZone.addEventListener('drop', function(e) {
                e.preventDefault();
                addImageUploadZone.style.background = '#f1f5f9';
                var files = e.dataTransfer && e.dataTransfer.files;
                if (files && files.length) addImageAddFiles(Array.prototype.slice.call(files));
            });
        }
        if (addImageModalFileInput) {
            addImageModalFileInput.addEventListener('change', function() {
                var files = this.files;
                if (files && files.length) addImageAddFiles(Array.prototype.slice.call(files));
                this.value = '';
            });
        }
        if (addImageModalCameraBtn && addImageModalFileInput) {
            addImageModalCameraBtn.addEventListener('click', function() { addImageModalFileInput.click(); });
        }
        if (addImageModalSaveBtn) {
            addImageModalSaveBtn.addEventListener('click', function() {
                window.productModalGroupImageByTab = window.productModalGroupImageByTab || {};
                if (addImageModalImages.length > 0) {
                    var payload = { primary: addImageModalImages[addImageModalPrimaryIndex], images: addImageModalImages.slice() };
                    window.productModalGroupImageByTab[addImageModalTabKey] = payload;
                    if (typeof applyProductModalGroupImageToPhotoColumns === 'function') {
                        applyProductModalGroupImageToPhotoColumns(payload, addImageModalTabKey);
                    }
                }
                closeAddImageModal();
            });
        }
        if (addImageModalClose) {
            addImageModalClose.addEventListener('click', function() { closeAddImageModal(); });
        }
        if (addImageModal) {
            addImageModal.addEventListener('click', function(e) {
                if (e.target === addImageModal) closeAddImageModal();
            });
        }
    })();

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

    // Table Settings - Column Visibility Toggle (with localStorage persistence)
    (function() {
        const STORAGE_KEY = 'auragold_product_list_column_visibility';
        const settingsBtn = document.getElementById('tableSettingsBtn');
        const settingsDropdown = document.getElementById('tableSettingsDropdown');
        const checkboxes = settingsDropdown ? settingsDropdown.querySelectorAll('input[type="checkbox"][data-column]') : [];

        function saveColumnVisibility() {
            const state = {};
            checkboxes.forEach(function(cb) {
                const col = cb.getAttribute('data-column');
                if (col) state[col] = cb.checked;
            });
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
            } catch (e) {}
        }

        function applySavedColumnVisibility() {
            var state = null;
            try {
                var raw = localStorage.getItem(STORAGE_KEY);
                if (raw) state = JSON.parse(raw);
            } catch (e) {}
            if (!state || typeof state !== 'object') return;
            checkboxes.forEach(function(checkbox) {
                const columnName = checkbox.getAttribute('data-column');
                if (!columnName) return;
                const isVisible = state[columnName];
                if (typeof isVisible !== 'boolean') return;
                checkbox.checked = isVisible;
                const headers = document.querySelectorAll('.product-table th[data-column="' + columnName + '"]');
                const cells = document.querySelectorAll('.product-table td[data-column="' + columnName + '"]');
                headers.forEach(function(header) {
                    if (isVisible) header.classList.remove('hidden'); else header.classList.add('hidden');
                });
                cells.forEach(function(cell) {
                    if (isVisible) cell.classList.remove('hidden'); else cell.classList.add('hidden');
                });
            });
            const emptyRowCell = document.getElementById('emptyRowCell');
            if (emptyRowCell) {
                const visibleCount = Array.from(checkboxes).filter(function(cb) { return cb.checked; }).length;
                emptyRowCell.setAttribute('colspan', visibleCount + 1);
            }
        }

        // Apply current column visibility to a row (call after adding new product row so columns don't misalign)
        window.applyProductListColumnVisibilityToRow = function(row) {
            if (!row || !row.querySelectorAll) return;
            var state = {};
            checkboxes.forEach(function(cb) {
                var col = cb.getAttribute('data-column');
                if (col) state[col] = cb.checked;
            });
            if (Object.keys(state).length === 0) {
                try {
                    var raw = localStorage.getItem(STORAGE_KEY);
                    if (raw) state = JSON.parse(raw);
                } catch (e) {}
            }
            var cells = row.querySelectorAll('td[data-column]');
            cells.forEach(function(cell) {
                var col = cell.getAttribute('data-column');
                if (!col) return;
                var isVisible = (state && typeof state[col] === 'boolean') ? state[col] : true;
                if (isVisible) cell.classList.remove('hidden'); else cell.classList.add('hidden');
            });
        };

        if (!settingsBtn || !settingsDropdown) return;

        // Apply saved preferences on page load
        applySavedColumnVisibility();

        // Toggle dropdown on button click
        settingsBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            settingsDropdown.classList.toggle('show');
            // Clear search when opening dropdown
            const searchInput = document.getElementById('tableSettingsSearch');
            if (searchInput && settingsDropdown.classList.contains('show')) {
                searchInput.value = '';
                // Show all items when opening
                const items = settingsDropdown.querySelectorAll('.table-settings-item');
                items.forEach(function(item) {
                    item.classList.remove('hidden');
                });
            }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!settingsBtn.contains(e.target) && !settingsDropdown.contains(e.target)) {
                settingsDropdown.classList.remove('show');
                // Clear search when closing dropdown
                const searchInput = document.getElementById('tableSettingsSearch');
                if (searchInput) {
                    searchInput.value = '';
                    // Show all items when closing
                    const items = settingsDropdown.querySelectorAll('.table-settings-item');
                    items.forEach(function(item) {
                        item.classList.remove('hidden');
                    });
                }
            }
        });

        // Handle column visibility and persist
        checkboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                const columnName = this.getAttribute('data-column');
                const isVisible = this.checked;
                
                const headers = document.querySelectorAll('.product-table th[data-column="' + columnName + '"]');
                const cells = document.querySelectorAll('.product-table td[data-column="' + columnName + '"]');
                
                headers.forEach(function(header) {
                    if (isVisible) header.classList.remove('hidden'); else header.classList.add('hidden');
                });
                cells.forEach(function(cell) {
                    if (isVisible) cell.classList.remove('hidden'); else cell.classList.add('hidden');
                });

                const emptyRowCell = document.getElementById('emptyRowCell');
                if (emptyRowCell) {
                    const visibleColumns = Array.from(checkboxes).filter(function(cb) { return cb.checked; }).length;
                    emptyRowCell.setAttribute('colspan', visibleColumns + 1);
                }

                saveColumnVisibility();
            });
        });
        
        // Column search functionality for main table
        const tableSearchInput = document.getElementById('tableSettingsSearch');
        if (tableSearchInput) {
            tableSearchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                const items = settingsDropdown.querySelectorAll('.table-settings-item');
                
                items.forEach(function(item) {
                    const label = item.querySelector('label');
                    if (label) {
                        const labelText = label.textContent.toLowerCase();
                        if (labelText.includes(searchTerm)) {
                            item.classList.remove('hidden');
                        } else {
                            item.classList.add('hidden');
                        }
                    }
                });
            });
        }
    })();

    // Column Drag and Drop Functionality – mouse-based, order saved to DB and restored on load
    (function() {
        const table = document.querySelector('.product-table');
        if (!table) return;

        const thead = table.querySelector('thead tr');
        const tbody = document.getElementById('productTableBody');
        const tfoot = document.getElementById('productTableFooter');
        const tfootRow = tfoot ? tfoot.querySelector('tr') : null;
        if (!thead || !tbody) return;

        const PAGE_COLUMN_PREFS = 'sale-invoice-product-table';
        const TAB_MAIN = 'main';

        let draggedColumn = null;
        let draggedColumnIndex = null;
        let dropTargetColumn = null;
        let dropPositionRight = false;

        function getDraggableColumns() {
            return thead.querySelectorAll('th.draggable-column');
        }
        function getColumnIndex(th) {
            return Array.from(thead.children).indexOf(th);
        }
        function clearColumnDropHighlight() {
            getDraggableColumns().forEach(col => {
                col.classList.remove('drag-over-column', 'drag-over-column-right');
            });
            dropTargetColumn = null;
        }

        function reorderColumns(dragIndex, dropIndex) {
            const rows = [thead, ...Array.from(tbody.querySelectorAll('tr'))];
            if (tfootRow) rows.push(tfootRow);
            rows.forEach(row => {
                const cells = Array.from(row.children);
                const draggedCell = cells[dragIndex];
                if (draggedCell) {
                    cells.splice(dragIndex, 1);
                    cells.splice(dropIndex, 0, draggedCell);
                    cells.forEach(cell => row.appendChild(cell));
                }
            });
            saveProductTableColumnOrder();
        }

        function getCurrentColumnOrder() {
            return Array.from(getDraggableColumns()).map(th => th.getAttribute('data-column'));
        }

        function saveProductTableColumnOrder() {
            const order = getCurrentColumnOrder();
            if (!order.length) return;
            const prefs = {};
            order.forEach(k => { prefs[k] = 1; });
            $.ajax({
                url: 'ajax/save-product-modal-column-preferences.php',
                type: 'POST',
                data: {
                    page_name: PAGE_COLUMN_PREFS,
                    tab_key: TAB_MAIN,
                    preferences: JSON.stringify(prefs)
                },
                dataType: 'json'
            }).fail(function() {});
        }

        function applySavedColumnOrder(orderedKeys) {
            if (!orderedKeys || !orderedKeys.length) return;
            const rows = [thead, ...Array.from(tbody.querySelectorAll('tr'))];
            if (tfootRow) rows.push(tfootRow);
            rows.forEach(row => {
                const cells = Array.from(row.children);
                const fixed = cells.length ? cells[cells.length - 1] : null;
                const map = {};
                const dataCells = fixed ? cells.slice(0, -1) : cells;
                dataCells.forEach(cell => {
                    const key = cell.getAttribute('data-column');
                    if (key) map[key] = cell;
                });
                orderedKeys.forEach(k => { if (map[k]) row.appendChild(map[k]); });
                if (fixed) row.appendChild(fixed);
            });
        }

        function onColumnMouseMove(e) {
            if (!draggedColumn || !thead) return;
            const el = document.elementFromPoint(e.clientX, e.clientY);
            const th = el ? el.closest('th.draggable-column') : null;
            clearColumnDropHighlight();
            if (!th || th.parentNode !== thead || th === draggedColumn) return;
            dropTargetColumn = th;
            const rect = th.getBoundingClientRect();
            const colMiddle = rect.left + rect.width / 2;
            dropPositionRight = e.clientX >= colMiddle;
            if (dropPositionRight) th.classList.add('drag-over-column-right'); else th.classList.add('drag-over-column');
        }
        function onColumnMouseUp(e) {
            if (!draggedColumn || !thead) {
                finishColumnDrag();
                return;
            }
            if (dropTargetColumn && dropTargetColumn !== draggedColumn) {
                const dropIndex = getColumnIndex(dropTargetColumn);
                const dragIndex = draggedColumnIndex;
                let finalDropIndex = dropIndex;
                if (dropPositionRight && dragIndex < dropIndex) finalDropIndex = dropIndex + 1;
                else if (!dropPositionRight && dragIndex > dropIndex) finalDropIndex = dropIndex;
                else if (dropPositionRight && dragIndex > dropIndex) finalDropIndex = dropIndex + 1;
                else finalDropIndex = dropIndex;
                reorderColumns(dragIndex, finalDropIndex);
            }
            finishColumnDrag();
        }
        function finishColumnDrag() {
            if (draggedColumn) {
                draggedColumn.classList.remove('dragging-column');
                draggedColumn = null;
            }
            draggedColumnIndex = null;
            clearColumnDropHighlight();
            document.removeEventListener('mousemove', onColumnMouseMove);
            document.removeEventListener('mouseup', onColumnMouseUp);
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
        }

        thead.addEventListener('mousedown', function(e) {
            const th = e.target.closest('th.draggable-column');
            if (!th) return;
            e.preventDefault();
            draggedColumn = th;
            draggedColumnIndex = getColumnIndex(th);
            th.classList.add('dragging-column');
            document.body.style.cursor = 'grabbing';
            document.body.style.userSelect = 'none';
            document.addEventListener('mousemove', onColumnMouseMove);
            document.addEventListener('mouseup', onColumnMouseUp);
        });

        function loadAndApplyColumnOrder() {
            $.ajax({
                url: 'ajax/get-column-preferences.php',
                type: 'POST',
                data: { page_name: PAGE_COLUMN_PREFS },
                dataType: 'json'
            }).done(function(res) {
                if (res.status !== 'success' || !res.preferences || !res.preferences.length) return;
                const currentOrder = getCurrentColumnOrder();
                const savedOrder = res.preferences.map(p => p.column_key);
                const merged = savedOrder.slice();
                currentOrder.forEach(k => { if (merged.indexOf(k) === -1) merged.push(k); });
                applySavedColumnOrder(merged);
            });
        }

        window.getProductTableColumnOrder = getCurrentColumnOrder;

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', loadAndApplyColumnOrder);
        } else {
            loadAndApplyColumnOrder();
        }
    })();

    // Product Selection modal table (Basic Information) – column drag, mouse-based, order saved to DB
    (function() {
        var PAGE_MODAL_COLUMN_PREFS = 'sale-invoice-product-modal';
        var TAB_MAIN = 'main';

        function getModalTableColumnOrder() {
            var table = document.getElementById('productListTable');
            if (!table) return [];
            var headerRow = table.querySelector('thead tr:nth-child(2)');
            if (!headerRow) return [];
            var order = [];
            headerRow.querySelectorAll('th[data-column]').forEach(function(th) {
                order.push(th.getAttribute('data-column'));
            });
            return order;
        }
        function reorderModalRowCellsToMatchHeader(row) {
            var order = getModalTableColumnOrder();
            if (!order || order.length === 0) return;
            var cells = Array.from(row.children);
            if (cells.length < 2) return;
            var checkbox = cells[0];
            var map = {};
            for (var i = 1; i < cells.length; i++) {
                var k = cells[i].getAttribute('data-column');
                if (k) map[k] = cells[i];
            }
            for (var i = 1; i < cells.length; i++) {
                cells[i].remove();
            }
            order.forEach(function(k) {
                if (map[k]) row.appendChild(map[k]);
            });
            row.insertBefore(checkbox, row.firstChild);
        }
        window.reorderModalRowCellsToMatchHeader = reorderModalRowCellsToMatchHeader;

        function initProductSelectionModalColumnDrag() {
            var table = document.getElementById('productListTable');
            if (!table) return;
            var headerRow1 = table.querySelector('thead tr:first-child');
            var headerRow2 = table.querySelector('thead tr:nth-child(2)');
            var tbody = document.getElementById('productListBody');
            if (!headerRow1 || !headerRow2 || !tbody) return;
            if (headerRow2.getAttribute('data-column-drag-inited') === '1') return;
            headerRow2.setAttribute('data-column-drag-inited', '1');

            // Build column -> group map from DOM (which columns belong to which group)
            function buildGroupByColumnMap() {
                var groupByColKey = {};
                var colIdx = 1; // skip checkbox
                var groupHeaders = headerRow1 ? headerRow1.querySelectorAll('th[data-group]') : [];
                groupHeaders.forEach(function(gh) {
                    var span = parseInt(gh.getAttribute('colspan') || '0', 10) || 0;
                    if (span <= 0) return;
                    var gk = gh.getAttribute('data-group');
                    for (var s = 0; s < span && colIdx < headerRow2.children.length; s++) {
                        var th = headerRow2.children[colIdx];
                        var colKey = th.getAttribute('data-column');
                        if (colKey && colKey !== 'actions') groupByColKey[colKey] = gk;
                        colIdx++;
                    }
                });
                return groupByColKey;
            }
            function getGroupForColumn(colKey) {
                var map = buildGroupByColumnMap();
                return map[colKey] || null;
            }

            function getModalColHeaders() {
                return headerRow2.querySelectorAll('th[data-column]');
            }
            function getModalColIndex(th) {
                return Array.from(headerRow2.children).indexOf(th);
            }
            function getModalCurrentColumnOrder() {
                var order = [];
                getModalColHeaders().forEach(function(th) {
                    var k = th.getAttribute('data-column');
                    if (k && k !== 'actions') order.push(k);
                });
                return order;
            }
            function clearModalColHighlight() {
                getModalColHeaders().forEach(function(h) {
                    h.classList.remove('modal-col-dragging', 'modal-col-drag-over-left', 'modal-col-drag-over-right');
                });
            }
            function saveModalColumnOrder() {
                var order = getModalCurrentColumnOrder();
                if (!order.length) return;
                var prefs = {};
                order.forEach(function(k) { prefs[k] = 1; });
                $.ajax({
                    url: 'ajax/save-product-modal-column-preferences.php',
                    type: 'POST',
                    data: {
                        page_name: PAGE_MODAL_COLUMN_PREFS,
                        tab_key: TAB_MAIN,
                        preferences: JSON.stringify(prefs)
                    },
                    dataType: 'json'
                });
            }
            function sanitizeColumnOrderToRespectGroups(orderedKeys) {
                if (!orderedKeys || !orderedKeys.length) return orderedKeys;
                var columnGroups = window.PRODUCT_MODAL_COLUMN_GROUPS || {};
                var columnToGroup = {};
                Object.keys(columnGroups).forEach(function(gk) {
                    var cols = columnGroups[gk];
                    if (Array.isArray(cols)) cols.forEach(function(c) { columnToGroup[c] = gk; });
                });
                var firstIndex = {};
                orderedKeys.forEach(function(k, idx) {
                    var gk = columnToGroup[k];
                    if (gk && firstIndex[gk] === undefined) firstIndex[gk] = idx;
                });
                var groupOrder = Object.keys(columnGroups).filter(function(gk) { return firstIndex[gk] !== undefined; }).sort(function(a, b) { return (firstIndex[a] || 0) - (firstIndex[b] || 0); });
                var columnsByGroup = {};
                Object.keys(columnGroups).forEach(function(gk) { columnsByGroup[gk] = []; });
                orderedKeys.forEach(function(k) {
                    var gk = columnToGroup[k];
                    if (gk) columnsByGroup[gk].push(k);
                });
                var sanitized = [];
                groupOrder.forEach(function(gk) {
                    sanitized = sanitized.concat(columnsByGroup[gk]);
                });
                orderedKeys.forEach(function(k) {
                    if (!columnToGroup[k] && sanitized.indexOf(k) === -1) sanitized.push(k);
                });
                return sanitized.length ? sanitized : orderedKeys;
            }
            function applySavedModalColumnOrder(orderedKeys) {
                if (!orderedKeys || !orderedKeys.length) return;
                orderedKeys = sanitizeColumnOrderToRespectGroups(orderedKeys);
                var headerCells = Array.from(headerRow2.children);
                var headerMap = {};
                headerCells.forEach(function(th) {
                    var k = th.getAttribute('data-column');
                    if (k) headerMap[k] = th;
                });
                orderedKeys.forEach(function(k) {
                    if (headerMap[k]) headerRow2.appendChild(headerMap[k]);
                });
                if (headerMap['actions']) headerRow2.appendChild(headerMap['actions']);

                var bodyRows = tbody.querySelectorAll('tr');
                var dataStart = 1;
                bodyRows.forEach(function(row) {
                    var cells = Array.from(row.children);
                    if (cells.length <= dataStart) return;
                    var map = {};
                    var actionsCell = null;
                    for (var i = dataStart; i < cells.length; i++) {
                        var key = cells[i].getAttribute('data-column');
                        if (key) {
                            if (key === 'actions') actionsCell = cells[i];
                            else map[key] = cells[i];
                        }
                    }
                    orderedKeys.forEach(function(k) {
                        if (map[k]) row.appendChild(map[k]);
                    });
                    if (actionsCell) row.appendChild(actionsCell);
                });
                syncGroupHeaderOrderToColumnOrder(orderedKeys);
            }
            function syncGroupHeaderOrderToColumnOrder(orderedKeys) {
                if (!headerRow1 || !orderedKeys || !orderedKeys.length) return;
                var columnGroups = window.PRODUCT_MODAL_COLUMN_GROUPS || {};
                var columnToGroup = {};
                Object.keys(columnGroups).forEach(function(gk) {
                    var cols = columnGroups[gk];
                    if (Array.isArray(cols)) cols.forEach(function(c) { columnToGroup[c] = gk; });
                });
                var firstIndex = {};
                orderedKeys.forEach(function(k, idx) {
                    var gk = columnToGroup[k];
                    if (gk && firstIndex[gk] === undefined) firstIndex[gk] = idx;
                });
                var groupOrder = Object.keys(columnGroups).filter(function(gk) { return firstIndex[gk] !== undefined; }).sort(function(a, b) { return (firstIndex[a] || 0) - (firstIndex[b] || 0); });
                var groupMap = {};
                headerRow1.querySelectorAll('th[data-group]').forEach(function(gh) {
                    var gk = gh.getAttribute('data-group');
                    if (gk) groupMap[gk] = gh;
                });
                var actionsTh = headerRow1.querySelector('th[data-column="actions"]');
                groupOrder.forEach(function(gk) {
                    if (groupMap[gk] && actionsTh) headerRow1.insertBefore(groupMap[gk], actionsTh);
                    else if (groupMap[gk]) headerRow1.appendChild(groupMap[gk]);
                });
                Object.keys(groupMap).forEach(function(gk) {
                    if (groupOrder.indexOf(gk) === -1 && groupMap[gk] && actionsTh) headerRow1.insertBefore(groupMap[gk], actionsTh);
                });
                if (typeof updateGroupHeaderVisibility === 'function') updateGroupHeaderVisibility();
            }
            function reorderModalColumns(dragIdx, dropIdx) {
                if (dragIdx === dropIdx) return;
                var numHeaderCols = headerRow2.children.length;
                var dataStart = 1;
                var bodyRows = tbody.querySelectorAll('tr');
                bodyRows.forEach(function(row) {
                    var cells = Array.from(row.children);
                    if (cells.length < dataStart + numHeaderCols) return;
                    var dragCell = cells[dataStart + dragIdx];
                    if (!dragCell) return;
                    cells.splice(dataStart + dragIdx, 1);
                    cells.splice(dataStart + (dropIdx > dragIdx ? dropIdx - 1 : dropIdx), 0, dragCell);
                    cells.forEach(function(c) { row.appendChild(c); });
                });
                var headerCells = Array.from(headerRow2.children);
                var hDrag = headerCells[dragIdx];
                if (hDrag) {
                    headerCells.splice(dragIdx, 1);
                    headerCells.splice(dropIdx > dragIdx ? dropIdx - 1 : dropIdx, 0, hDrag);
                    headerCells.forEach(function(c) { headerRow2.appendChild(c); });
                }
                saveModalColumnOrder();
            }

            var modalDraggedTh = null;
            var modalDragIdx = null;
            var modalDropTh = null;
            var modalDropRight = false;

            function onModalColMove(e) {
                if (!modalDraggedTh || !headerRow2) return;
                var el = document.elementFromPoint(e.clientX, e.clientY);
                var th = el ? el.closest('#productListTable thead tr:nth-child(2) th[data-column]') : null;
                getModalColHeaders().forEach(function(h) {
                    h.classList.remove('modal-col-drag-over-left', 'modal-col-drag-over-right');
                });
                modalDropTh = null;
                if (!th || th === modalDraggedTh || th.getAttribute('data-column') === 'actions') return;
                // Only allow drop within same group (no cross-group column moves)
                var dragGroup = getGroupForColumn(modalDraggedTh.getAttribute('data-column'));
                var dropGroup = getGroupForColumn(th.getAttribute('data-column'));
                if (dragGroup !== dropGroup) return;
                modalDropTh = th;
                var rect = th.getBoundingClientRect();
                modalDropRight = e.clientX >= rect.left + rect.width / 2;
                if (modalDropRight) th.classList.add('modal-col-drag-over-right'); else th.classList.add('modal-col-drag-over-left');
            }
            function onModalColUp(e) {
                if (!modalDraggedTh) {
                    finishModalColDrag();
                    return;
                }
                if (modalDropTh && modalDropTh !== modalDraggedTh) {
                    var dropIdx = getModalColIndex(modalDropTh);
                    var dragIdx = modalDragIdx;
                    var finalIdx = dropIdx;
                    if (modalDropRight && dragIdx < dropIdx) finalIdx = dropIdx + 1;
                    else if (!modalDropRight && dragIdx > dropIdx) finalIdx = dropIdx;
                    else if (modalDropRight && dragIdx > dropIdx) finalIdx = dropIdx + 1;
                    else finalIdx = dropIdx;
                    if (finalIdx !== dragIdx) reorderModalColumns(dragIdx, finalIdx);
                }
                finishModalColDrag();
            }
            function finishModalColDrag() {
                if (modalDraggedTh) {
                    modalDraggedTh.classList.remove('modal-col-dragging');
                    modalDraggedTh = null;
                }
                modalDragIdx = null;
                modalDropTh = null;
                clearModalColHighlight();
                document.removeEventListener('mousemove', onModalColMove);
                document.removeEventListener('mouseup', onModalColUp);
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
            }

            getModalColHeaders().forEach(function(th) {
                if (th.getAttribute('data-column') !== 'actions') th.setAttribute('title', 'Drag to reorder columns (within group)');
            });

            // ----- Group-level drag: move whole groups -----
            function getCurrentGroupOrder() {
                var order = [];
                (headerRow1.querySelectorAll('th[data-group]') || []).forEach(function(gh) {
                    var gk = gh.getAttribute('data-group');
                    if (gk) order.push(gk);
                });
                return order;
            }
            function getColumnsInGroupFromDOM(groupKey) {
                var groupByCol = buildGroupByColumnMap();
                var cols = [];
                Object.keys(groupByCol).forEach(function(c) {
                    if (groupByCol[c] === groupKey) cols.push(c);
                });
                return cols;
            }
            function reorderModalColumnsByGroupOrder(newGroupOrder) {
                var newColumnOrder = [];
                newGroupOrder.forEach(function(gk) {
                    getColumnsInGroupFromDOM(gk).forEach(function(c) { newColumnOrder.push(c); });
                });
                var headerMap = {};
                Array.from(headerRow2.querySelectorAll('th[data-column]')).forEach(function(th) {
                    var k = th.getAttribute('data-column');
                    if (k) headerMap[k] = th;
                });
                newColumnOrder = newColumnOrder.filter(function(k) { return headerMap[k]; });
                var extra = [];
                Array.from(headerRow2.querySelectorAll('th[data-column]')).forEach(function(th) {
                    var k = th.getAttribute('data-column');
                    if (k && k !== 'actions' && newColumnOrder.indexOf(k) === -1) extra.push(k);
                });
                newColumnOrder = newColumnOrder.concat(extra);
                if (newColumnOrder.length) {
                    applySavedModalColumnOrder(newColumnOrder);
                    saveModalColumnOrder();
                }

                var groupMap = {};
                headerRow1.querySelectorAll('th[data-group]').forEach(function(gh) {
                    var gk = gh.getAttribute('data-group');
                    if (gk) groupMap[gk] = gh;
                });
                var actionsTh = headerRow1.querySelector('th[data-column="actions"]');
                newGroupOrder.forEach(function(gk) {
                    if (groupMap[gk] && actionsTh) headerRow1.insertBefore(groupMap[gk], actionsTh);
                    else if (groupMap[gk]) headerRow1.appendChild(groupMap[gk]);
                });
                if (typeof updateGroupHeaderVisibility === 'function') updateGroupHeaderVisibility();
            }

            var modalDraggedGroupTh = null;
            var modalDropGroupTh = null;
            var modalDropGroupRight = false;
            function clearGroupHighlight() {
                (headerRow1.querySelectorAll('th[data-group]') || []).forEach(function(h) {
                    h.classList.remove('modal-group-dragging', 'modal-group-drag-over-left', 'modal-group-drag-over-right');
                });
            }
            function onGroupMove(e) {
                if (!modalDraggedGroupTh) return;
                var el = document.elementFromPoint(e.clientX, e.clientY);
                var gh = el ? el.closest('#productListTable thead tr:first-child th[data-group]') : null;
                clearGroupHighlight();
                modalDropGroupTh = null;
                if (!gh || gh === modalDraggedGroupTh) return;
                modalDropGroupTh = gh;
                var rect = gh.getBoundingClientRect();
                modalDropGroupRight = e.clientX >= rect.left + rect.width / 2;
                gh.classList.add(modalDropGroupRight ? 'modal-group-drag-over-right' : 'modal-group-drag-over-left');
            }
            function onGroupUp(e) {
                if (!modalDraggedGroupTh) { finishGroupDrag(); return; }
                if (modalDropGroupTh && modalDropGroupTh !== modalDraggedGroupTh) {
                    var current = getCurrentGroupOrder();
                    var dragGk = modalDraggedGroupTh.getAttribute('data-group');
                    var dropGk = modalDropGroupTh.getAttribute('data-group');
                    var dragIdx = current.indexOf(dragGk);
                    var dropIdx = current.indexOf(dropGk);
                    if (dragIdx >= 0 && dropIdx >= 0 && dragIdx !== dropIdx) {
                        var newOrder = current.slice();
                        newOrder.splice(dragIdx, 1);
                        var insertAt = newOrder.indexOf(dropGk);
                        if (modalDropGroupRight) insertAt++;
                        newOrder.splice(insertAt, 0, dragGk);
                        reorderModalColumnsByGroupOrder(newOrder);
                    }
                }
                finishGroupDrag();
            }
            function finishGroupDrag() {
                if (modalDraggedGroupTh) {
                    modalDraggedGroupTh.classList.remove('modal-group-dragging');
                    modalDraggedGroupTh = null;
                }
                modalDropGroupTh = null;
                clearGroupHighlight();
                document.removeEventListener('mousemove', onGroupMove);
                document.removeEventListener('mouseup', onGroupUp);
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
            }
            headerRow1.querySelectorAll('th[data-group]').forEach(function(gh) {
                gh.setAttribute('title', 'Drag to reorder entire group');
                gh.style.cursor = 'grab';
                gh.addEventListener('mousedown', function(e) {
                    if (e.target.closest('th[data-column]')) return;
                    e.preventDefault();
                    modalDraggedGroupTh = gh;
                    gh.classList.add('modal-group-dragging');
                    document.body.style.cursor = 'grabbing';
                    document.body.style.userSelect = 'none';
                    document.addEventListener('mousemove', onGroupMove);
                    document.addEventListener('mouseup', onGroupUp);
                });
            });

            headerRow2.addEventListener('mousedown', function(e) {
                var th = e.target.closest('th[data-column]');
                if (!th || th.getAttribute('data-column') === 'actions') return;
                e.preventDefault();
                modalDraggedTh = th;
                modalDragIdx = getModalColIndex(th);
                th.classList.add('modal-col-dragging');
                document.body.style.cursor = 'grabbing';
                document.body.style.userSelect = 'none';
                document.addEventListener('mousemove', onModalColMove);
                document.addEventListener('mouseup', onModalColUp);
            });

            function loadAndApplyModalColumnOrder() {
                $.ajax({
                    url: 'ajax/get-column-preferences.php',
                    type: 'POST',
                    data: { page_name: PAGE_MODAL_COLUMN_PREFS },
                    dataType: 'json'
                }).done(function(res) {
                    if (res.status !== 'success' || !res.preferences || !res.preferences.length) return;
                    var currentOrder = getModalCurrentColumnOrder();
                    var savedOrder = res.preferences.map(function(p) { return p.column_key; });
                    var merged = savedOrder.slice();
                    currentOrder.forEach(function(k) {
                        if (merged.indexOf(k) === -1) merged.push(k);
                    });
                    var sanitized = sanitizeColumnOrderToRespectGroups(merged);
                    applySavedModalColumnOrder(sanitized);
                    if (sanitized.join(',') !== merged.join(',')) saveModalColumnOrder();
                });
            }
            loadAndApplyModalColumnOrder();
        }
        function runInit() {
            initProductSelectionModalColumnDrag();
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', runInit);
        } else {
            runInit();
        }
        var modalEl = document.getElementById('productSelectionModal');
        if (modalEl && typeof jQuery !== 'undefined') {
            jQuery(modalEl).off('shown.bs.modal.saleInvoiceColumnDrag').on('shown.bs.modal.saleInvoiceColumnDrag', function() {
                initProductSelectionModalColumnDrag();
            });
        }
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
    
    // Flag to prevent duplicate submissions
    let isSaving = false;
    
    // Save order to database
    function saveOrder() {
        // Prevent duplicate submissions
        if (isSaving) {
            console.log('Save already in progress, please wait...');
            return;
        }
        
        // Validate required fields
        const customerName = document.getElementById('customerName')?.value.trim();
        if (!customerName) {
            alert('Please enter customer name');
            document.getElementById('customerName')?.focus();
            return;
        }
        
        // Set saving flag
        isSaving = true;
        
        // Get current order number from display
        const currentOrderNoText = document.getElementById('currentOrderNo')?.textContent || <?php echo json_encode(isset($next_order_no) ? $next_order_no : 'SI-1'); ?>;
        
        // Get order ID from URL or current order
        const urlParams = new URLSearchParams(window.location.search);
        const urlOrderId = urlParams.get('id');
        const currentOrderId = urlOrderId ? parseInt(urlOrderId) : <?php echo (int)($edit_order_id ?? 0); ?>;
        
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
            order_date: document.getElementById('orderDate')?.value || <?php echo json_encode(date('Y-m-d')); ?>,
            due_date: document.getElementById('dueDate')?.value || '',
            layaways: document.getElementById('layaways')?.value || '',
            fixing_type: document.getElementById('fixingType')?.value || 'Standard',
            hedge_contract_ref: document.getElementById('hedgeContractRef')?.value || '',
            hedge_date: document.getElementById('hedgeDate')?.value || '',
            group_name: document.getElementById('groupName')?.value || '',
            comment: document.getElementById('orderComment')?.value || '',
            payment_comments: document.getElementById('paymentCommentsData')?.value || '[]'
        };
        
        // Collect summary values
        const summaryTotal = parseFloat(document.getElementById('summaryTotal')?.textContent || 0);
        const summaryGrandTotal = parseFloat(document.getElementById('summaryGrandTotal')?.textContent || 0);
        const summaryPaidAmt = parseFloat(document.getElementById('summaryPaidAmt')?.textContent || 0);
        const summaryBalanceAmt = parseFloat(document.getElementById('summaryBalanceAmt')?.textContent || 0);
        const summaryMetalAmt = parseFloat(document.getElementById('summaryMetalAmt')?.textContent || 0);
        const roundOffValue = parseFloat(document.getElementById('roundOffValue')?.value || 0);
        const roundOffChecked = document.getElementById('roundOff')?.checked || false;
        
        orderData.previous_balance = parseFloat(document.getElementById('previousBalanceAmount')?.getAttribute('data-original-balance') || document.getElementById('previousBalanceAmount')?.textContent || 0);
        orderData.previous_gold = parseFloat(document.getElementById('previousBalanceGold')?.textContent || 0);
        orderData.previous_silver = parseFloat(document.getElementById('previousBalanceSilver')?.textContent || 0);
        orderData.previous_diamond = parseFloat(document.getElementById('previousBalanceDiamond')?.textContent || 0);
        orderData.previous_gemstone = parseFloat(document.getElementById('previousBalanceGemstone')?.textContent || 0);
        const usePrevChk = document.getElementById('usePreviousBalanceCheck');
        const usePrevAmtInput = document.getElementById('previousBalanceUseAmount');
        orderData.use_previous_balance = (usePrevChk && usePrevChk.checked) ? 1 : 0;
        orderData.previous_balance_used_amt = (usePrevChk && usePrevChk.checked && usePrevAmtInput) ? (parseFloat(usePrevAmtInput.value) || 0) : 0;
        orderData.subtotal = summaryTotal;
        orderData.additional_amt = 0;
        orderData.net_total = summaryTotal;
        orderData.reward_points = 0;
        orderData.coupon_code = '';
        orderData.coupon_discount = 0;
        orderData.discount_amt = parseFloat(document.getElementById('discountAmount')?.value || 0);
        orderData.discount_percent = parseFloat(document.getElementById('discountPercent')?.value || 0);
        orderData.redeem_points = 0;
        orderData.grand_total = summaryGrandTotal;
        orderData.advance_payment = 0;
        orderData.metal_amt = summaryMetalAmt;
        orderData.round_off = roundOffChecked ? roundOffValue : 0;
        orderData.paid_amt = summaryPaidAmt;
        orderData.balance_amt = summaryBalanceAmt;
        
        // Collect product items (merged row: push one item per product in data-group-items so load shows all on edit)
        const items = [];
        const productRows = document.querySelectorAll('#productTableBody tr:not(.no-drag)');
        productRows.forEach(function(row) {
            const groupItemsJson = row.getAttribute('data-group-items');
            if (groupItemsJson) {
                try {
                    const groupItems = JSON.parse(groupItemsJson);
                    if (groupItems && groupItems.length > 0) {
                        // Read current making amount from row input (data-group-items is not updated when user edits)
                        const rowMakingInput = row.querySelector('[data-column="making-amount"] input') || row.querySelector('[data-field="making"]');
                        const rowMakingAmount = parseFloat(rowMakingInput?.value || row.querySelector('[data-column="making-amount"]')?.textContent) || 0;
                        const makingPerItem = groupItems.length > 0 ? (rowMakingAmount / groupItems.length) : 0;
                        const rowGroupImage = row.getAttribute('data-group-image') || '';
                        var rowMetalId = row.getAttribute('data-metal-id') || '';
                        var rowPureWtEl = row.querySelector('[data-column="purity-wt"] input') || row.querySelector('[data-column="pure-wt"] input');
                        var rowPureWt = parseFloat(rowPureWtEl?.value || row.querySelector('[data-column="purity-wt"]')?.textContent || row.querySelector('[data-column="pure-wt"]')?.textContent) || 0;
                        var rowNetWtEl = row.querySelector('[data-column="net-wt"] input');
                        var rowNetWt = parseFloat(rowNetWtEl?.value || row.querySelector('[data-column="net-wt"]')?.textContent) || 0;
                        var rowPurityEl = row.querySelector('[data-column="purity"] input');
                        var rowPurity = parseFloat(rowPurityEl?.value || row.getAttribute('data-purity')) || 0;
                        groupItems.forEach(function(d) {
                            var metalWt = parseFloat(d.metal_weight) || 0;
                            var grossWt = parseFloat(d.gross_wt) || 0;
                            var netWt = parseFloat(d.net_wt) || (rowNetWt > 0 ? rowNetWt : 0);
                            var pureWt = parseFloat(d.pure_wt) || (rowPureWt > 0 ? rowPureWt : 0);
                            if (pureWt <= 0 && netWt > 0 && rowPurity > 0) {
                                pureWt = rowPurity <= 1 ? (netWt * rowPurity) : (netWt * rowPurity / 100);
                            }
                            items.push({
                                product_id: d.product_id || '',
                                characteristic_id: d.characteristic_id || '',
                                metal_id: (d.metal_id != null && d.metal_id !== '') ? d.metal_id : (rowMetalId ? (parseInt(rowMetalId, 10) || rowMetalId) : ''),
                                barcode: d.barcode || '',
                                product_name: d.product_name || '',
                                group_image: rowGroupImage,
                                carat: (d.stone_weight != null && d.stone_weight !== '') ? String(d.stone_weight) : (d.carat || ''),
                                category: (d.category != null && d.category !== '') ? String(d.category).trim() : '',
                                diamond_category: (d.diamond_category != null && d.diamond_category !== '') ? String(d.diamond_category).trim() : ((d.category != null && d.category !== '') ? String(d.category).trim() : ''),
                                calculation_type: (d.calculation_type != null && d.calculation_type !== '') ? String(d.calculation_type).trim() : ((d.calculation != null && d.calculation !== '') ? String(d.calculation).trim() : 'Rate X Gross Wt'),
                                quantity: parseFloat(d.quantity) || 0,
                                metal_qty: (d.metal_qty != null && d.metal_qty !== '') ? (parseFloat(d.metal_qty) || 1) : 1,
                                metal_weight: metalWt,
                                gross_weight: grossWt || metalWt,
                                less_weight: parseFloat(d.less_wt) || 0,
                                purity: parseFloat(d.purity) || rowPurity || 0,
                                purity_weight: pureWt,
                                final_weight: parseFloat(d.final_wt) || 0,
                                net_weight: netWt,
                                pure_weight: pureWt,
                                rate: parseFloat(d.rate) || 0,
                                metal_rate: parseFloat(d.metal_rate) || 0,
                                metal_value: parseFloat(d.metal_value) || 0,
                                making: makingPerItem,
                                making_amount: makingPerItem,
                                making_type: (d.making_type != null && d.making_type !== '') ? String(d.making_type).trim() : 'Fix',
                                making_rate: parseFloat(d.making_rate) != null && d.making_rate !== '' ? (parseFloat(d.making_rate) || 0) : makingPerItem,
                                making_discount_amt: parseFloat(d.making_discount_amt) || parseFloat(d.making_discount_amount) || 0,
                                making_actual_value: parseFloat(d.making_actual_value) != null && d.making_actual_value !== '' ? (parseFloat(d.making_actual_value) || 0) : makingPerItem,
                                making_cost: parseFloat(d.making_cost) != null && d.making_cost !== '' ? (parseFloat(d.making_cost) || 0) : makingPerItem,
                                design_no: d.design_no || '',
                                calculation_type: (d.calculation_type || d.calculation || '').toString().trim() || null,
                                tax: parseFloat(d.tax) || 0,
                                amount: parseFloat(d.amount) || 0,
                                net_amount: parseFloat(d.net_amt) || 0,
                                net_amt_with_tax: parseFloat(d.net_amt_tax) || 0,
                                stone_charges: parseFloat(d.stone_amount) || 0,
                                stone_amount: parseFloat(d.stone_amount) || 0,
                                stone_weight: parseFloat(d.stone_weight) || 0,
                                other_charges: parseFloat(d.other_amount) || 0,
                                other_amount: parseFloat(d.other_amount) || 0,
                                diamond_value: parseFloat(d.diamond_amount) || 0,
                                diamond_amount: parseFloat(d.diamond_amount) || 0,
                                gemstone_value: 0,
                                metal_value: parseFloat(d.metal_value) || 0,
                                discount: parseFloat(d.discount) || 0,
                                purchase_amount: parseFloat(d.purchase_amount) || 0,
                                sale_amount: parseFloat(d.sale_amount) || 0,
                                sale_amount_with: parseFloat(d.sale_amount_with) || 0,
                                reverse: parseFloat(d.reverse) || 0
                            });
                        });
                        return;
                    }
                } catch (e) { console.error('Parse data-group-items on save:', e); }
            }
            const productId = row.getAttribute('data-product-id');
            const characteristicId = row.getAttribute('data-characteristic-id');
            if (productId) {
                const productName = row.querySelector('[data-column="product"] a')?.textContent || '';
                const quantity = parseFloat(row.querySelector('[data-field="quantity"]')?.value || row.querySelector('[data-column="quantity"]')?.textContent) || 0;
                const grossWeight = parseFloat(row.querySelector('[data-field="gross_wt"]')?.value || row.querySelector('[data-column="gross-wt"]')?.textContent) || 0;
                const lessWeight = parseFloat(row.querySelector('[data-field="less_wt"]')?.value || row.querySelector('[data-column="less-wt"]')?.textContent) || 0;
                const finalWeight = parseFloat(row.querySelector('[data-field="final_wt"]')?.value || row.querySelector('[data-column="final-wt"]')?.textContent) || 0;
                const netWeight = parseFloat(row.querySelector('[data-column="net-wt"]')?.textContent || row.querySelector('[data-column="net-wt"] input')?.value) || 0;
                const pureWtInput = row.querySelector('[data-column="purity-wt"] input') || row.querySelector('[data-column="pure-wt"] input');
                const pureWtCell = row.querySelector('[data-column="purity-wt"]') || row.querySelector('[data-column="pure-wt"]');
                const pureWeight = parseFloat(pureWtInput?.value || pureWtCell?.textContent) || 0;
                const purity = parseFloat(row.getAttribute('data-purity')) || parseFloat(row.querySelector('[data-field="purity"]')?.value || row.querySelector('[data-column="purity"] input')?.value || 0);
                const rate = parseFloat(row.getAttribute('data-rate')) || parseFloat(row.querySelector('[data-column="rate"]')?.textContent || 0);
                const metalRateEl = row.querySelector('[data-column="metal-rate"] input') || row.querySelector('[data-column="metal-rate"]');
                const metalRate = metalRateEl ? (parseFloat(metalRateEl.value) || parseFloat(metalRateEl.textContent) || 0) : 0;
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
                const makingAmount = parseFloat(row.querySelector('[data-column="making-amount"] input')?.value || row.querySelector('[data-field="making"]')?.value || row.querySelector('[data-column="making-amount"]')?.textContent) || 0;
                const stoneAmount = parseFloat(row.querySelector('[data-column="stone-amount"]')?.textContent) || 0;
                const otherAmount = parseFloat(row.querySelector('[data-column="other-amount"]')?.textContent) || 0;
                const diamondAmount = parseFloat(row.querySelector('[data-column="diamond-amount"]')?.textContent) || 0;
                const purchaseAmount = parseFloat(row.querySelector('[data-column="purchase-amount"]')?.textContent) || 0;
                const saleAmount = parseFloat(row.querySelector('[data-column="sale-amount"]')?.textContent) || 0;
                const saleAmountWith = parseFloat(row.querySelector('[data-column="sale-amount-with"]')?.textContent) || 0;
                const reverse = parseFloat(row.querySelector('[data-column="reverse"]')?.textContent) || 0;
                const metalQtyEl = row.querySelector('[data-column="metal-qty"] input');
                const metalQty = metalQtyEl ? (parseFloat(metalQtyEl.value) || 1) : 1;
                const metalWeightEl = row.querySelector('[data-column="metal-weight"] input');
                const metalWeight = metalWeightEl ? (parseFloat(metalWeightEl.value) || 0) : 0;
                const barcode = row.getAttribute('data-barcode') || row.querySelector('[data-column="barcode"]')?.textContent?.trim() || '';
                const groupImage = row.getAttribute('data-group-image') || '';
                const metalId = row.getAttribute('data-metal-id') || '';
                const calculationSelect = row.querySelector('[data-column="calculation"] select');
                const categorySelect = row.querySelector('[data-column="category"] select');
                const calculationType = row.getAttribute('data-calculation-type') || (calculationSelect ? calculationSelect.value : '') || (row.querySelector('[data-column="calculation"]')?.textContent?.trim()) || 'Rate X Gross Wt';
                const diamondCategory = (categorySelect ? categorySelect.value : '') || (row.querySelector('[data-column="category"]')?.textContent?.trim()) || row.getAttribute('data-diamond-category') || '';
                items.push({
                    product_id: productId,
                    characteristic_id: characteristicId,
                    metal_id: metalId ? (parseInt(metalId, 10) || metalId) : '',
                    barcode: barcode,
                    product_name: productName,
                    group_image: groupImage,
                    calculation_type: calculationType,
                    diamond_category: diamondCategory,
                    category: diamondCategory,
                    category_id: categorySelect ? (categorySelect.value || null) : null,
                    carat: '',
                    quantity: quantity,
                    metal_qty: metalQty,
                    metal_weight: metalWeight,
                    gross_weight: grossWeight,
                    less_weight: lessWeight,
                    purity: purity,
                    purity_weight: pureWeight,
                    final_weight: finalWeight,
                    net_weight: netWeight,
                    pure_weight: pureWeight,
                    rate: rate,
                    metal_rate: metalRate || rate,
                    making: making,
                    making_amount: makingAmount,
                    making_type: 'Fix',
                    making_rate: makingAmount > 0 ? makingAmount : 0,
                    making_discount_amt: 0,
                    making_actual_value: makingAmount,
                    making_cost: makingAmount,
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

        // Hedging: separate making from net; sale invoice = net amount (exclude making); making goes to purchase fixing entry
        var fixingTypeVal = (document.getElementById('fixingType') && document.getElementById('fixingType').value) ? document.getElementById('fixingType').value : 'Standard';
        if (fixingTypeVal === 'Hedging' && orderData.items && orderData.items.length > 0) {
            var totalMakingForSaleFixing = 0;
            orderData.items.forEach(function(item) {
                var makingAmt = parseFloat(item.making_amount) || parseFloat(item.making) || 0;
                totalMakingForSaleFixing += makingAmt;
                item.making = 0;
                item.making_amount = 0;
                if (makingAmt > 0) {
                    item.amount = Math.max(0, (parseFloat(item.amount) || 0) - makingAmt);
                    item.net_amount = Math.max(0, (parseFloat(item.net_amount) || 0) - makingAmt);
                    item.net_amt_with_tax = Math.max(0, (parseFloat(item.net_amt_with_tax) || 0) - makingAmt);
                }
            });
            orderData.making_amount_for_sale_fixing = totalMakingForSaleFixing;
            orderData.subtotal = Math.max(0, (parseFloat(orderData.subtotal) || 0) - totalMakingForSaleFixing);
            orderData.net_total = Math.max(0, (parseFloat(orderData.net_total) || 0) - totalMakingForSaleFixing);
            orderData.grand_total = Math.max(0, (parseFloat(orderData.grand_total) || 0) - totalMakingForSaleFixing);
        }

        // Collect payments
        const paymentRows = document.querySelectorAll('#paymentTableBody tr:not(.no-payment-row)');
        const paymentData = [];
        paymentRows.forEach(function(row) {
            const paymentType = row.querySelector('td:first-child')?.textContent || '';
            const depositInto = row.querySelector('td:nth-child(2)')?.textContent || '';
            const transactionNo = row.querySelector('td:nth-child(3)')?.textContent || '';
            const chequeDate = row.querySelector('td:nth-child(4)')?.textContent || '';
            const purityCarat = row.querySelector('td:nth-child(5)')?.textContent || '';
            const totalAmt = parseFloat(row.querySelector('[data-payment-amount]')?.textContent || 0);
            const prevBalAmt = parseFloat(row.getAttribute('data-previous-balance-amount') || 0);
            const currentOrderAmt = parseFloat(row.getAttribute('data-current-order-amount') || (totalAmt - prevBalAmt));
            const diamondCategory = row.querySelector('td:nth-child(7)')?.textContent || '';
            const quantity = parseFloat(row.querySelector('td:nth-child(8)')?.textContent || 0);
            
            if (totalAmt > 0) {
                paymentData.push({
                    payment_type: paymentType,
                    deposit_into: depositInto,
                    transaction_no: transactionNo,
                    cheque_date: chequeDate || null,
                    purity_carat: purityCarat,
                    amount: totalAmt,
                    previous_balance_amount: prevBalAmt,
                    current_order_amount: currentOrderAmt,
                    diamond_category: diamondCategory,
                    quantity: quantity
                });
            }
        });
        
        orderData.payments = paymentData;
        
        // Show loading
        const saveBtn = document.querySelector('.btn-save-invoice, .btn-purple');
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
                url: 'ajax/save-sale-invoice.php',
                type: 'POST',
                data: postData,
                dataType: 'json',
                success: function(response) {
                    // Reset saving flag
                    isSaving = false;
                    
                    if (saveBtn) {
                        saveBtn.disabled = false;
                        saveBtn.textContent = originalText;
                    }
                    
                    if (response.status === 'success') {
                        const invoiceId = response.invoice_id || response.order_id;
                        if (invoiceId) {
                            // After save: go to create page (not edit) when user closes print modal
                            window.pendingRedirectUrl = 'sale-invoice.php';
                            setTimeout(function() {
                                showPrintInvoiceModal(invoiceId);
                            }, 100);
                        } else {
                            alert('Sale Invoice saved successfully! Invoice No: ' + (response.invoice_no || response.order_no || 'N/A'));
                            window.location.href = 'sale-invoice.php';
                        }
                    } else {
                        alert('Error: ' + (response.message || 'Failed to save sale invoice'));
                    }
                },
                error: function(xhr, status, error) {
                    // Reset saving flag
                    isSaving = false;
                    
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
            
            fetch('ajax/save-sale-invoice.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Reset saving flag
                isSaving = false;
                
                if (saveBtn) {
                    saveBtn.disabled = false;
                    saveBtn.textContent = originalText;
                }
                
                if (data.status === 'success') {
                    const invoiceId = data.invoice_id || data.order_id;
                    if (invoiceId) {
                        // After save: go to create page (not edit) when user closes print modal
                        window.pendingRedirectUrl = 'sale-invoice.php';
                        setTimeout(function() {
                            showPrintInvoiceModal(invoiceId);
                        }, 100);
                    } else {
                        alert('Sale Invoice saved successfully! Invoice No: ' + (data.invoice_no || data.order_no || 'N/A'));
                        window.location.href = 'sale-invoice.php';
                    }
                } else {
                    alert('Error: ' + (data.message || 'Failed to save sale invoice'));
                }
            })
            .catch(error => {
                // Reset saving flag
                isSaving = false;
                
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
        if (confirm('Are you sure you want to create a new invoice? All unsaved data will be lost.')) {
            window.location.href = 'sale-invoice.php';
        }
    }
    
    // Add event listener to Save button in summary panel
    const saveInvoiceBtn = document.querySelector('.btn-save-invoice');
    if (saveInvoiceBtn) {
        // Remove onclick attribute to prevent double calls (we're using addEventListener)
        saveInvoiceBtn.removeAttribute('onclick');
        saveInvoiceBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            saveOrder();
        });
    }
    
    // Also handle the other save button (btn-purple)
    const saveBtnPurple = document.querySelector('.btn-purple[onclick*="saveOrder"]');
    if (saveBtnPurple) {
        // Remove onclick attribute to prevent double calls
        saveBtnPurple.removeAttribute('onclick');
        saveBtnPurple.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            saveOrder();
        });
    }
    
    // Function to print sale invoice
    function printPurchaseInvoice(invoiceId) {
        if (!invoiceId) {
            // Try to get invoice ID from URL
            const urlParams = new URLSearchParams(window.location.search);
            invoiceId = urlParams.get('id');
        }
        
        if (!invoiceId || invoiceId <= 0) {
            alert('Please save the invoice first before printing.');
            return;
        }
        
        // Open print page in new window
        window.open('sale-invoice-print.php?id=' + invoiceId, '_blank', 'width=1200,height=800');
    }
    
    // Update print icon when invoice is loaded
    function updatePrintIcon(invoiceId) {
        const printIcon = document.getElementById('printInvoiceIcon');
        if (printIcon) {
            if (invoiceId && invoiceId > 0) {
                printIcon.style.color = '#c5a864';
                printIcon.style.opacity = '1';
                printIcon.style.cursor = 'pointer';
                printIcon.style.pointerEvents = 'auto';
                printIcon.setAttribute('onclick', 'printPurchaseInvoice(' + invoiceId + ')');
                printIcon.setAttribute('title', 'Print Invoice');
            } else {
                printIcon.style.color = '#94a3b8';
                printIcon.style.opacity = '0.5';
                printIcon.style.cursor = 'not-allowed';
                printIcon.style.pointerEvents = 'none';
                printIcon.removeAttribute('onclick');
                printIcon.setAttribute('title', 'Save invoice first to print');
            }
        }
    }
    
    // Initialize print icon on page load
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const invoiceId = urlParams.get('id');
        updatePrintIcon(invoiceId ? parseInt(invoiceId) : null);
    });
    
    // Also update print icon when URL changes (after save redirect)
    window.addEventListener('popstate', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const invoiceId = urlParams.get('id');
        updatePrintIcon(invoiceId ? parseInt(invoiceId) : null);
    });
    
    // ================== LOAD SAVED ORDER FUNCTIONALITY ==================
    
    // Load order from dropdown selection
    // Function removed - using search instead of dropdown
    
    // Load order data
    function loadOrder(orderId) {
        if (!orderId) return;
        
        // Show loading
        const loadingMsg = document.createElement('div');
        loadingMsg.id = 'orderLoadingMsg';
        loadingMsg.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); z-index: 10000;';
        loadingMsg.innerHTML = '<p>Loading order...</p>';
        document.body.appendChild(loadingMsg);
        
        // Fetch order data (cache-bust so we always get fresh data)
        var getUrl = 'ajax/get-sale-invoice.php?id=' + orderId + '&_=' + (Date.now ? Date.now() : '');
        if (typeof jQuery !== 'undefined' && jQuery.ajax) {
            jQuery.ajax({
                url: getUrl,
                type: 'GET',
                dataType: 'json',
                cache: false,
                success: function(response) {
                    const msg = document.getElementById('orderLoadingMsg');
                    if (msg) document.body.removeChild(msg);
                    if (response.status === 'success') {
                        populateOrderForm(response.order, response.items || [], response.payments || []);
                        // Update URL without reload and set edit mode so Save updates this invoice
                        window.history.pushState({}, '', 'sale-invoice.php?id=' + orderId);
                        window.isPurchaseInvoiceEditMode = true;
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
            fetch(getUrl, { cache: 'no-store' })
                .then(response => response.json())
                .then(data => {
                    const msg = document.getElementById('orderLoadingMsg');
                    if (msg) document.body.removeChild(msg);
                    if (data.status === 'success') {
                        populateOrderForm(data.order, data.items || [], data.payments || []);
                        window.history.pushState({}, '', 'sale-invoice.php?id=' + orderId);
                        window.isPurchaseInvoiceEditMode = true;
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
        row.setAttribute('data-metal-id', currentMetalId || item.metal_id || '');
        
        // Generate the row HTML with all columns (populated with saved values)
        row.innerHTML = `
            <td data-column="checkbox" style="text-align: center; background: #fff;">
                <input type="checkbox" class="product-checkbox" data-product-id="${item.product_id || ''}" data-characteristic-id="${item.product_characteristic_id || ''}">
            </td>
            <td data-column="id">${item.product_id || ''}</td>
            <td data-column="rfid"><input type="text" class="form-control form-control-sm" value="${escapeHtml(item.rfid_code || '')}" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="voucher-type"><input type="text" class="form-control form-control-sm" value="${escapeHtml(item.voucher_type_id || '')}" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="photo" class="product-row-photo" style="text-align: center; vertical-align: middle; min-width: 70px;"><img src="" alt="" class="product-photo-thumb" style="max-width: 50px; max-height: 50px; display: none;"><span class="product-photo-placeholder text-muted" style="font-size: 0.65rem;">—</span></td>
            <td data-column="barcode"><input type="text" class="form-control form-control-sm" value="${escapeHtml(item.barcode_no || '')}" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="design-no"><input type="text" class="form-control form-control-sm" value="${escapeHtml(item.design_no || '')}" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="huid"><input type="text" class="form-control form-control-sm" value="${escapeHtml(item.huid_no || '')}" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="category"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="">Select</option></select></td>
            <td data-column="calculation"><select class="form-control form-control-sm" style="width: 120px; font-size: 0.7rem;"><option value="Carat X Rate" ${item.calculation === 'Carat X Rate' ? 'selected' : ''}>Carat X Rate</option><option value="Rate X Gross Wt" ${(item.calculation || 'Rate X Gross Wt') === 'Rate X Gross Wt' ? 'selected' : ''}>Rate X Gross Wt</option><option value="Rate X Purity Wt" ${item.calculation === 'Rate X Purity Wt' ? 'selected' : ''}>Rate X Purity Wt</option><option value="Rate X Net Wt" ${item.calculation === 'Rate X Net Wt' ? 'selected' : ''}>Rate X Net Wt</option><option value="Rate X Final Wt" ${item.calculation === 'Rate X Final Wt' ? 'selected' : ''}>Rate X Final Wt</option><option value="Fix" ${item.calculation === 'Fix' ? 'selected' : ''}>Fix</option><option value="Stone Charge" ${item.calculation === 'Stone Charge' ? 'selected' : ''}>Stone Charge</option><option value="Attach Image Type" ${item.calculation === 'Attach Image Type' ? 'selected' : ''}>Attach Image Type</option></select></td>
            <td data-column="product"><input type="text" class="form-control form-control-sm" value="${escapeHtml(product.name || '')}" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="location"><select class="form-control form-control-sm location-select" style="width: 100px; font-size: 0.7rem;"><option value="">Select</option></select></td>
            <td data-column="pkt-wt"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.pkt_weight || 0).toFixed(3)}" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="pkt-less-wt"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.pkt_less_weight || 0).toFixed(3)}" step="0.001" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="gross-wt"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.gross_weight || 0).toFixed(3)}" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="stone-weight"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.stone_weight || 0).toFixed(3)}" step="0.001" style="width: 110px; font-size: 0.7rem;"></td>
            <td data-column="less-wt"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.less_weight || 0).toFixed(3)}" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="net-wt"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.net_weight || 0).toFixed(3)}" step="0.001" readonly style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="quantity"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.quantity || 1).toFixed(2)}" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="rate"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.rate || 0).toFixed(2)}" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="amount"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.amount || 0).toFixed(2)}" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="metal-qty"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.metal_qty != null && item.metal_qty !== '' ? item.metal_qty : 1).toFixed(2)}" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="metal-weight"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.metal_weight || 0).toFixed(3)}" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="carat"><select class="form-control form-control-sm carat-select" style="width: 80px; font-size: 0.7rem;"><option value="">Select</option></select></td>
            <td data-column="purity"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.purity || 1).toFixed(2)}" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="purity-wt"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.purity_weight || 0).toFixed(3)}" step="0.001" readonly style="width: 90px; font-size: 0.7rem;"></td>
            <td data-column="gold-loss1"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.gold_loss1 || 0).toFixed(3)}" step="0.001" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="gold-loss2"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.gold_loss2 || 0).toFixed(2)}" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="metal-loss-value"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.metal_loss_value || 0).toFixed(3)}" step="0.001" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="wastage-per"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.wastage_per || 0).toFixed(2)}" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="wastage-wt"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.wastage_weight || 0).toFixed(3)}" step="0.001" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="metal-rate"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.metal_rate || item.rate || 0).toFixed(2)}" step="0.01" style="width: 90px; font-size: 0.7rem;"></td>
            <td data-column="metal-value"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.metal_value || 0).toFixed(2)}" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="metal-cost"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.metal_cost || 0).toFixed(2)}" step="0.01" readonly style="width: 100px; font-size: 0.7rem; color: #7b1fa2;"></td>
            <td data-column="requested-purity"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.requested_purity || 0).toFixed(2)}" step="0.01" style="width: 120px; font-size: 0.7rem;"></td>
            <td data-column="requested"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.requested || 0).toFixed(3)}" step="0.001" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="setting-charge"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.setting_charge || 0).toFixed(2)}" step="0.01" style="width: 110px; font-size: 0.7rem;"></td>
            <td data-column="final-wt"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.final_weight || 0).toFixed(3)}" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="alloy-wt"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.alloy_weight || 0).toFixed(3)}" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="discount-type"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="On Amount" ${(item.discount_type || 'On Amount') === 'On Amount' ? 'selected' : ''}>On Amount</option><option value="On Percentage" ${item.discount_type === 'On Percentage' ? 'selected' : ''}>On Percentage</option></select></td>
            <td data-column="discount-per"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.discount_per || 0).toFixed(2)}" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="discount-amount"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.discount_amount || 0).toFixed(2)}" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="discount"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.discount || 0).toFixed(2)}" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="discount-type2"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="On Amount" ${(item.discount_type2 || 'On Amount') === 'On Amount' ? 'selected' : ''}>On Amount</option><option value="On Percentage" ${item.discount_type2 === 'On Percentage' ? 'selected' : ''}>On Percentage</option></select></td>
            <td data-column="discount-per2"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.discount_per2 || 0).toFixed(2)}" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="discount-amount2"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.discount_amount2 || 0).toFixed(2)}" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="discounted-amt"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.discounted_amount || 0).toFixed(2)}" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
            <td data-column="discounted-per"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.discounted_per || 0).toFixed(2)}" step="0.01" style="width: 120px; font-size: 0.7rem;"></td>
            <td data-column="making-type"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="Fix" ${(item.making_type || 'Fix') === 'Fix' ? 'selected' : ''}>Fix</option><option value="Per Gram" ${item.making_type === 'Per Gram' ? 'selected' : ''}>Per Gram</option><option value="Per Piece" ${item.making_type === 'Per Piece' ? 'selected' : ''}>Per Piece</option><option value="Per Kilogram" ${item.making_type === 'Per Kilogram' ? 'selected' : ''}>Per Kilogram</option><option value="Per Percent" ${item.making_type === 'Per Percent' ? 'selected' : ''}>Per Percent</option><option value="MRP" ${item.making_type === 'MRP' ? 'selected' : ''}>MRP</option><option value="M.KT" ${item.making_type === 'M.KT' ? 'selected' : ''}>M.KT</option></select></td>
            <td data-column="making-rate"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.making_rate || 0).toFixed(2)}" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="making-discount-amt"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.making_discount_amount || 0).toFixed(2)}" step="0.01" style="width: 130px; font-size: 0.7rem;"></td>
            <td data-column="making-amount"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.making_amount || 0).toFixed(2)}" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="making-actual-value"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.making_actual_value || 0).toFixed(2)}" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
            <td data-column="making-cost"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.making_cost || 0).toFixed(2)}" step="0.01" readonly style="width: 110px; font-size: 0.7rem;"></td>
            <td data-column="min-price"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.min_price || 0).toFixed(2)}" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="minimum"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.minimum || 0).toFixed(2)}" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="stone-charge-type"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="Fix" ${(item.stone_charge_type || 'Fix') === 'Fix' ? 'selected' : ''}>Fix</option><option value="Per Gram" ${item.stone_charge_type === 'Per Gram' ? 'selected' : ''}>Per Gram</option></select></td>
            <td data-column="stone-rate"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.stone_rate || 0).toFixed(2)}" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="stone-amount"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.stone_amount || 0).toFixed(2)}" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
            <td data-column="stone-cost"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.stone_cost || 0).toFixed(2)}" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="diamond-amount"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.diamond_amount || 0).toFixed(2)}" step="0.01" style="width: 120px; font-size: 0.7rem;"></td>
            <td data-column="purchase-amount"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.purchase_amount || 0).toFixed(2)}" step="0.01" readonly style="width: 130px; font-size: 0.7rem;"></td>
                        <td data-column="sale-percent"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;" placeholder="%" title="Sale % on Purchase Amount"></td>            <td data-column="sale-amount"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.sale_amount || 0).toFixed(2)}" step="0.01" readonly style="width: 110px; font-size: 0.7rem;"></td>
            <td data-column="sale-amount-with"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.sale_amount_with || 0).toFixed(2)}" step="0.01" readonly style="width: 130px; font-size: 0.7rem;"></td>
            <td data-column="net-amt"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.net_amount || 0).toFixed(2)}" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="tax-type"><select class="form-control form-control-sm" style="width: 120px; font-size: 0.7rem;"><option value="tax_on_making">Tax on making</option><option value="tax_of_netamount">Tax of net amount</option><option value="no_tax" selected>No tax</option></select></td>
            <td data-column="tax-percent"><input type="text" class="form-control form-control-sm" value="${(product.total_tax_percent != null && product.total_tax_percent !== '') ? product.total_tax_percent : ((product.vat_value != null && product.vat_value !== '') ? product.vat_value : 0)}" min="0" max="100" step="0.01" readonly style="width: 70px; font-size: 0.7rem; background: #f1f5f9; cursor: not-allowed;" title="From product opening (sum of all taxes, read-only)"></td>
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
        if (typeof reorderModalRowCellsToMatchHeader === 'function') {
            reorderModalRowCellsToMatchHeader(row);
        }
        console.log('Row appended to productListBody. Total rows now:', tbody.querySelectorAll('tr').length);
        
        // Apply current tab column visibility so hidden columns stay hidden on new row
        if (tbody.id === 'productListBody' && typeof applyProductModalColumnVisibilityForTab === 'function') {
            applyProductModalColumnVisibilityForTab(currentMetalId || '');
        }
        
        // Restore saved values for category, tax-type, and calculation (edit mode load)
        var categorySelectEl = row.querySelector('[data-column="category"] select');
        if (categorySelectEl) {
            // If item has diamond category (Jewellery/Diamonds/GemStones), use Diamond options regardless of active tab
            var isDiamondCat = (item.category || '').toString().trim();
            var useDiamondOptions = (isDiamondCat === 'Jewellery' || isDiamondCat === 'Diamonds' || isDiamondCat === 'GemStones');
            var isDiamondTab = useDiamondOptions || (typeof isDiamondTabActive === 'function' && isDiamondTabActive());
            if (typeof populateCategorySelectForModal === 'function') {
                populateCategorySelectForModal(categorySelectEl, isDiamondTab);
            }
            if (item.category) {
                try { categorySelectEl.value = item.category; } catch (e) {}
            }
        }
        var taxTypeSelectEl = row.querySelector('[data-column="tax-type"] select');
        if (taxTypeSelectEl && item.tax_type) {
            try { taxTypeSelectEl.value = item.tax_type; } catch (e) {}
        }
        var calculationSelectEl = row.querySelector('[data-column="calculation"] select');
        if (calculationSelectEl && typeof applyCalculationSelectOptionsForRow === 'function') {
            var isDiamondTabCalc = useDiamondOptions || (typeof isDiamondTabActive === 'function' && isDiamondTabActive());
            applyCalculationSelectOptionsForRow(calculationSelectEl, row, isDiamondTabCalc);
            if (item.calculation) {
                try { calculationSelectEl.value = item.calculation; } catch (e) {}
            }
        } else if (calculationSelectEl && item.calculation) {
            try { calculationSelectEl.value = item.calculation; } catch (e) {}
        }
        
        // Populate dropdowns (location, carat, etc.)
        const caratSelect = row.querySelector('.carat-select');
        if (caratSelect && typeof populateSelect === 'function') {
            populateSelect(caratSelect, carats, 'id', 'name', 'Select Karat');
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
    
    // Populate form with order data (param loadedPayments to avoid shadowing global payments)
    function populateOrderForm(order, items, loadedPayments) {
        console.log('populateOrderForm executing', { orderId: order && order.id, itemsCount: (items && items.length) || 0, paymentsCount: (loadedPayments && loadedPayments.length) || 0 });
        
        // Update order number
        if (document.getElementById('currentOrderNo')) {
            document.getElementById('currentOrderNo').textContent = order.invoice_no || order.order_no;
        }
        
        // Update print icon with invoice ID
        if (order.id) {
            updatePrintIcon(order.id);
        }
        
        // Clear search input when invoice is loaded
        const searchInput = document.getElementById('searchSaleInvoice');
        if (searchInput) {
            searchInput.value = '';
        }
        const suggestionsDiv = document.getElementById('saleInvoiceSuggestions');
        if (suggestionsDiv) {
            suggestionsDiv.style.display = 'none';
        }
        
        // Populate billing form
        if (document.getElementById('customerName')) {
            document.getElementById('customerName').value = order.supplier_name || order.customer_name || '';
        }
        if (document.getElementById('customerId')) {
            document.getElementById('customerId').value = order.supplier_id || order.customer_id || '';
        }
        if (document.getElementById('againstOf')) {
            var againstVal = (order.against_of != null && order.against_of !== '') ? String(order.against_of) : '';
            var sel = document.getElementById('againstOf');
            var hasOption = false;
            for (var i = 0; i < sel.options.length; i++) {
                if (sel.options[i].value === againstVal) { hasOption = true; break; }
            }
            if (againstVal && !hasOption) {
                var opt = document.createElement('option');
                opt.value = againstVal;
                opt.textContent = againstVal;
                sel.appendChild(opt);
            }
            sel.value = againstVal;
        }
        if (document.getElementById('currency')) {
            document.getElementById('currency').value = order.currency || 'AED';
        }
        if (document.getElementById('refNo')) {
            document.getElementById('refNo').value = order.ref_no || '';
        }
        if (document.getElementById('salesPerson')) {
            document.getElementById('salesPerson').value = order.purchase_person || order.sales_person || '';
        }
        if (document.getElementById('orderDate')) {
            var rawDate = order.invoice_date || order.order_date || '';
            // Normalize to Y-m-d for type="date" (e.g. "2026-02-17 00:00:00" -> "2026-02-17")
            if (rawDate && rawDate.length >= 10) rawDate = rawDate.substring(0, 10);
            document.getElementById('orderDate').value = rawDate || '';
        }
        if (document.getElementById('dueDate')) {
            var rawDue = order.due_date || '';
            if (rawDue && String(rawDue).length >= 10) rawDue = String(rawDue).substring(0, 10);
            document.getElementById('dueDate').value = rawDue || '';
        }
        if (document.getElementById('layaways')) {
            document.getElementById('layaways').value = order.layaways_id || '';
        }
        if (document.getElementById('fixingType')) {
            document.getElementById('fixingType').value = order.fixing_type || 'Standard';
        }
        // Hedging fields: show/hide (toggleHedgingSection may be in another scope, so run inline)
        var fixingTypeEl = document.getElementById('fixingType');
        var showHedging = fixingTypeEl && fixingTypeEl.value === 'Hedging';
        var displayVal = showHedging ? 'block' : 'none';
        var hedgingSection = document.getElementById('hedgingSection');
        var hedgingSectionDate = document.getElementById('hedgingSectionDate');
        if (hedgingSection) hedgingSection.style.display = displayVal;
        if (hedgingSectionDate) hedgingSectionDate.style.display = displayVal;
        if (typeof toggleHedgingSection === 'function') try { toggleHedgingSection(); } catch (e) {}
        if (document.getElementById('hedgeContractRef')) document.getElementById('hedgeContractRef').value = order.hedge_contract_ref || '';
        if (document.getElementById('hedgeDate')) document.getElementById('hedgeDate').value = order.hedge_date || '';
        if (document.getElementById('groupName')) {
            document.getElementById('groupName').value = order.group_name || '';
        }
        if (document.getElementById('orderComment')) {
            document.getElementById('orderComment').value = order.comment || '';
        }
        if (typeof order.payment_comments !== 'undefined' && order.payment_comments) {
            try {
                window.paymentCommentsList = JSON.parse(order.payment_comments);
                if (!Array.isArray(window.paymentCommentsList)) window.paymentCommentsList = [];
            } catch (e) { window.paymentCommentsList = []; }
        } else {
            window.paymentCommentsList = [];
        }
        if (typeof renderPaymentCommentsList === 'function') renderPaymentCommentsList();
        console.log('populateOrderForm: form fields set, loading items and totals');
        
        // Populate previous balance from saved order
        const prevBalanceAmtEl = document.getElementById('previousBalanceAmount');
        const prevBalanceGoldEl = document.getElementById('previousBalanceGold');
        const prevBalanceSilverEl = document.getElementById('previousBalanceSilver');
        const prevBalanceDiamondEl = document.getElementById('previousBalanceDiamond');
        const prevBalanceGemstoneEl = document.getElementById('previousBalanceGemstone');
        const prevAmt = parseFloat(order.previous_balance || 0);
        const prevGold = parseFloat(order.previous_gold || 0);
        const prevSilver = parseFloat(order.previous_silver || 0);
        const prevDiamond = parseFloat(order.previous_diamond || 0);
        const prevGemstone = parseFloat(order.previous_gemstone || 0);
        if (prevBalanceAmtEl) { prevBalanceAmtEl.textContent = prevAmt.toFixed(2); prevBalanceAmtEl.setAttribute('data-original-balance', prevAmt.toFixed(2)); }
        if (prevBalanceGoldEl) prevBalanceGoldEl.textContent = prevGold.toFixed(3);
        if (prevBalanceSilverEl) prevBalanceSilverEl.textContent = prevSilver.toFixed(3);
        if (prevBalanceDiamondEl) prevBalanceDiamondEl.textContent = prevDiamond.toFixed(3);
        if (prevBalanceGemstoneEl) prevBalanceGemstoneEl.textContent = prevGemstone.toFixed(3);
        
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
        
        // Load items as one merged Product List row with data-group-items (so Edit shows all products)
        var itemList = (items && Array.isArray(items)) ? items.filter(Boolean) : [];
        if (itemList.length > 0) {
            console.log('Found ' + itemList.length + ' items to load');
            try {
                const modalRowsData = itemList.map(function(item) { return savedItemToModalRowData(item); });
                var added = false;
                if (typeof addMergedProductsToTable === 'function') {
                    addMergedProductsToTable(modalRowsData);
                    added = productTableBody && productTableBody.querySelectorAll('tr:not(.no-drag)').length > 0;
                }
                if (!added && typeof addProductToTableFromModalRow === 'function') {
                    modalRowsData.forEach(function(rowData) { addProductToTableFromModalRow(rowData); });
                } else if (!added) {
                    itemList.forEach(function(item, index) {
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
                    if (typeof addProductToTable === 'function') addProductToTable(product);
                    const rows = productTableBody.querySelectorAll('tr:not(.no-drag)');
                    const lastRow = rows[rows.length - 1];
                    if (lastRow) {
                        lastRow.setAttribute('data-product-id', item.product_id || '');
                        lastRow.setAttribute('data-characteristic-id', item.product_characteristic_id || '');
                        lastRow.setAttribute('data-purity', parseFloat(item.purity || 0));
                        lastRow.setAttribute('data-rate', parseFloat(item.rate || 0));
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
                        calculateRowAmounts(lastRow);
                    }
                });
            }
                // Restore group_image (primary + multiple) on first row from first item if saved
                var firstRow = productTableBody && productTableBody.querySelector('tr:not(.no-drag)');
                var firstItem = itemList[0];
                if (firstRow && firstItem && firstItem.group_image) {
                    firstRow.setAttribute('data-group-image', firstItem.group_image);
                    var payload = firstItem.group_image;
                    if (typeof payload === 'string' && payload.trim().startsWith('{')) { try { payload = JSON.parse(payload); } catch(e) {} }
                    var primary = typeof getGroupImagePrimary === 'function' ? getGroupImagePrimary(payload) : (typeof payload === 'string' ? payload : '');
                    if (primary) {
                        var photoCell = firstRow.querySelector('[data-column="photo"]');
                        if (photoCell) {
                            var img = photoCell.querySelector('img');
                            if (img) { img.src = primary; img.style.display = ''; }
                            var placeholder = photoCell.querySelector('.product-photo-placeholder');
                            if (placeholder) placeholder.style.display = 'none';
                        }
                    }
                }
            } catch (e) {
                console.error('Error loading invoice items into form:', e);
                if (productTableBody) {
                    productTableBody.innerHTML = '<tr class="no-drag"><td colspan="32" class="text-center text-muted py-4" id="emptyRowCell">No Rows To Show</td></tr>';
                }
            }
        } else {
            // Show empty message
            if (productTableBody) {
                productTableBody.innerHTML = '<tr class="no-drag"><td colspan="32" class="text-center text-muted py-4" id="emptyRowCell">No Rows To Show</td></tr>';
            }
            if (productListBody) {
                productListBody.innerHTML = '<tr><td colspan="70" class="text-center text-muted py-4">Click "Add Product" button to add products for billing...</td></tr>';
            }
        }
        
        // Clear existing payments and global payments array so Edit finds correct list
        const paymentTableBody = document.getElementById('paymentTableBody');
        if (paymentTableBody) {
            paymentTableBody.innerHTML = '';
        }
        if (typeof payments !== 'undefined') payments.length = 0;
        
        // Add payments to table (and to global payments array via addPaymentToTable)
        if (loadedPayments && loadedPayments.length > 0) {
            loadedPayments.forEach(function(payment) {
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
                
                const paymentData = {
                    id: 'payment-' + payment.id,
                    type: paymentType,
                    deposit_into: payment.deposit_into || '',
                    transaction_no: payment.transaction_no || '',
                    cheque_date: payment.cheque_date || '',
                    purity_carat: payment.purity_carat || '',
                    amount: parseFloat(payment.amount || 0),
                    previous_balance_amount: parseFloat(payment.previous_balance_amount || 0),
                    diamond_category: payment.diamond_category || '',
                    quantity: parseFloat(payment.quantity || 0)
                };
                if (paymentType === 'scrap') {
                    paymentData.scrap_metal_id = payment.scrap_metal_id || payment.metal_id || '';
                    paymentData.scrap_product_id = payment.scrap_product_id || payment.product_id || '';
                    paymentData.scrap_product_name = payment.scrap_product_name || payment.product_name || '';
                    paymentData.scrap_gross_wt = payment.scrap_gross_wt != null && payment.scrap_gross_wt !== '' ? String(payment.scrap_gross_wt) : '0';
                    paymentData.scrap_less_wt = payment.scrap_less_wt != null && payment.scrap_less_wt !== '' ? String(payment.scrap_less_wt) : '0';
                    paymentData.scrap_stone_wt = payment.scrap_stone_wt != null && payment.scrap_stone_wt !== '' ? String(payment.scrap_stone_wt) : '0';
                    paymentData.scrap_net_wt = payment.scrap_net_wt != null && payment.scrap_net_wt !== '' ? String(payment.scrap_net_wt) : '0';
                    paymentData.scrap_purity_wt = payment.scrap_purity_wt != null && payment.scrap_purity_wt !== '' ? String(payment.scrap_purity_wt) : '0';
                    paymentData.scrap_rate = payment.scrap_rate != null && payment.scrap_rate !== '' ? String(payment.scrap_rate) : '0';
                    paymentData.scrap_item_code = payment.scrap_item_code || payment.item_code || '';
                }
                addPaymentToTable(paymentData);
            });
        } else {
            // Show empty message
            if (paymentTableBody) {
                paymentTableBody.innerHTML = '<tr class="no-payment-row"><td colspan="9" class="text-center text-muted py-3">No payment entries</td></tr>';
            }
        }
        
        // Update summary panel with saved values from database (so edit mode shows exact saved totals and balance)
        const savedSubtotal = parseFloat(order.subtotal || 0);
        const savedGrandTotal = parseFloat(order.grand_total || 0);
        const savedPaidAmt = parseFloat(order.paid_amt || 0);
        const savedBalanceAmt = parseFloat(order.balance_amt || 0);
        if (document.getElementById('summaryTotal')) {
            document.getElementById('summaryTotal').textContent = savedSubtotal.toFixed(2);
        }
        if (document.getElementById('summaryGrandTotal')) {
            document.getElementById('summaryGrandTotal').textContent = savedGrandTotal.toFixed(2);
        }
        if (document.getElementById('summaryPaidAmt')) {
            document.getElementById('summaryPaidAmt').textContent = savedPaidAmt.toFixed(2);
        }
        if (document.getElementById('summaryBalanceAmt')) {
            document.getElementById('summaryBalanceAmt').textContent = savedBalanceAmt.toFixed(2);
        }
        if (document.getElementById('summaryMetalAmt')) {
            document.getElementById('summaryMetalAmt').textContent = parseFloat(order.metal_amt || 0).toFixed(2);
        }
        const roundOffVal = parseFloat(order.round_off || 0);
        const roundOffValueInput = document.getElementById('roundOffValue');
        const roundOffCheckbox = document.getElementById('roundOff');
        if (roundOffValueInput) roundOffValueInput.value = roundOffVal.toFixed(2);
        if (roundOffCheckbox) roundOffCheckbox.checked = roundOffVal !== 0;
        // Restore discount values
        const savedDiscountAmt = parseFloat(order.discount_amt || 0);
        const savedDiscountPercent = parseFloat(order.discount_percent || 0);
        if (document.getElementById('discountAmount')) document.getElementById('discountAmount').value = savedDiscountAmt.toFixed(2);
        if (document.getElementById('discountPercent')) document.getElementById('discountPercent').value = savedDiscountPercent;
        if (document.getElementById('summaryNetTotal')) {
            document.getElementById('summaryNetTotal').textContent = parseFloat(order.net_total || order.subtotal || 0).toFixed(2);
        }
        
        // Restore "Use previous balance" and "Amount to use" in edit mode (so 7875 total, 7375 paid, 500 used shows correctly)
        const usePrevChkEl = document.getElementById('usePreviousBalanceCheck');
        const useAmountInputEl = document.getElementById('previousBalanceUseAmount');
        const useAmountRowEl = document.getElementById('previousBalanceUseAmountRow');
        const previousBalanceUsedAmt = parseFloat(order.previous_balance_used_amt || 0) || Math.max(0, savedGrandTotal - savedPaidAmt - savedBalanceAmt);
        if (previousBalanceUsedAmt > 0 && usePrevChkEl && useAmountInputEl) {
            usePrevChkEl.checked = true;
            useAmountInputEl.value = previousBalanceUsedAmt.toFixed(2);
            if (useAmountRowEl) useAmountRowEl.style.display = 'flex';
        } else if (usePrevChkEl) {
            usePrevChkEl.checked = false;
            if (useAmountInputEl) useAmountInputEl.value = '0.00';
            if (useAmountRowEl) useAmountRowEl.style.display = 'none';
        }
        
        // Recalculate footer from product rows, then restore saved summary totals so edit mode shows exact DB values
        updateSummaryPanel();
        updatePaymentTotals();
        // Restore ALL summary and previous balance from saved order (updateSummaryPanel overwrites them when product rows exist or are empty)
        if (document.getElementById('summaryTotal')) {
            document.getElementById('summaryTotal').textContent = savedSubtotal.toFixed(2);
        }
        if (document.getElementById('summaryGrandTotal')) {
            document.getElementById('summaryGrandTotal').textContent = savedGrandTotal.toFixed(2);
        }
        if (document.getElementById('summaryPaidAmt')) {
            document.getElementById('summaryPaidAmt').textContent = savedPaidAmt.toFixed(2);
        }
        if (document.getElementById('summaryBalanceAmt')) {
            document.getElementById('summaryBalanceAmt').textContent = savedBalanceAmt.toFixed(2);
        }
        if (document.getElementById('summaryNetTotal')) {
            document.getElementById('summaryNetTotal').textContent = parseFloat(order.net_total || order.subtotal || 0).toFixed(2);
        }
        if (document.getElementById('summaryMetalAmt')) {
            document.getElementById('summaryMetalAmt').textContent = parseFloat(order.metal_amt || 0).toFixed(2);
        }
        if (roundOffValueInput) roundOffValueInput.value = roundOffVal.toFixed(2);
        if (roundOffCheckbox) roundOffCheckbox.checked = roundOffVal !== 0;
        // Restore previous balance display and data-original-balance
        if (prevBalanceAmtEl) {
            prevBalanceAmtEl.textContent = prevAmt.toFixed(2);
            prevBalanceAmtEl.setAttribute('data-original-balance', prevAmt.toFixed(2));
        }
        
        // Hide dropdown
        const orderDropdownWrapper = document.getElementById('orderDropdownWrapper');
        if (orderDropdownWrapper) {
            orderDropdownWrapper.style.display = 'none';
        }
        const orderSearchInput = document.getElementById('orderSearchInput');
        if (orderSearchInput) {
            orderSearchInput.value = '';
        }
        
        // Do not call loadCustomerBalance when loading for edit - we already set previous balance from order.
        // When creating a new invoice, balance is loaded when user selects customer (blur/click on customer field).
    }
    
    // Load order on page load when ?id= is in URL (edit mode) - run after window.load so DOM (product table, summary) is ready
    <?php if ($edit_order_id > 0): ?>
    (function() {
        var editId = <?php echo (int)($edit_order_id ?? 0); ?>;
        function doPopulateFromEmbed() {
            if (!window.EDIT_ORDER_DATA || !window.EDIT_ORDER_DATA.order) return false;
            if (typeof populateOrderForm !== 'function') return false;
            try {
                populateOrderForm(window.EDIT_ORDER_DATA.order, window.EDIT_ORDER_DATA.items || [], window.EDIT_ORDER_DATA.payments || []);
                if (window.history && window.history.replaceState) window.history.replaceState({}, '', 'sale-invoice.php?id=' + editId);
                window.isPurchaseInvoiceEditMode = true;
                return true;
            } catch (e) {
                console.error('populateOrderForm error:', e);
                return false;
            }
        }
        function doLoadOrder() {
            if (editId && typeof loadOrder === 'function') loadOrder(editId);
        }
        function runEditLoad() {
            if (doPopulateFromEmbed()) return;
            doLoadOrder();
        }
        // Run after full page load so productTableBody and summary panel exist
        function scheduleEditLoad() {
            setTimeout(runEditLoad, 150);
        }
        if (document.readyState === 'complete') {
            scheduleEditLoad();
        } else {
            window.addEventListener('load', scheduleEditLoad);
        }
        // Retry: if items/totals still empty after 1.2s, run again
        setTimeout(function() {
            var tbody = document.getElementById('productTableBody');
            var gt = document.getElementById('summaryGrandTotal');
            var hasRows = tbody && tbody.querySelectorAll('tr:not(.no-drag)').length > 0;
            var gtZero = gt && parseFloat(gt.textContent || 0) > 0;
            if ((!hasRows || !gtZero) && window.EDIT_ORDER_DATA && window.EDIT_ORDER_DATA.order) {
                doPopulateFromEmbed();
            }
        }, 1200);
    })();
    <?php endif; ?>
    
    // ================== PAYMENT FUNCTIONALITY ==================
    
    let paymentRowIndex = 0;
    let payments = [];
    
    // Payment icon click handlers (run after DOM ready; Bootstrap modal and jQuery loaded via footer-script)
    function initPaymentIconHandlers() {
        document.querySelectorAll('.payment-icon').forEach(function(icon) {
            if (icon._paymentHandlerBound) return;
            icon._paymentHandlerBound = true;
            icon.addEventListener('click', function() {
                window._editingPaymentId = null;
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
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPaymentIconHandlers);
    } else {
        initPaymentIconHandlers();
    }
    
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
            // Use Balance Amt from summary (already accounts for "Use previous balance" amount)
            const summaryBalanceAmtEl = document.getElementById('summaryBalanceAmt');
            const balanceAmt = summaryBalanceAmtEl ? parseFloat(summaryBalanceAmtEl.textContent.replace(/,/g, '')) || 0 : 0;
            const amountToShow = balanceAmt > 0 ? balanceAmt.toFixed(2) : '0.00';
            
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
    
    // Scrap Payment: single searchable product input – type to see list, select to fill rate/purity
    (function initScrapPaymentModal() {
        const scrapMetal = document.getElementById('scrapMetal');
        const scrapProductInput = document.getElementById('scrapProductInput');
        const scrapProductId = document.getElementById('scrapProductId');
        const scrapProductList = document.getElementById('scrapProductList');
        var scrapSearchTimeout;
        function showScrapProductList(products) {
            if (!scrapProductList) return;
            scrapProductList.innerHTML = '';
            scrapProductList.style.display = 'block';
            if (!products || products.length === 0) {
                scrapProductList.innerHTML = '<div class="p-2 text-muted small">No products found</div>';
                return;
            }
            products.forEach(function(p) {
                const div = document.createElement('div');
                div.className = 'scrap-product-item p-2 border-bottom';
                div.style.cursor = 'pointer';
                div.style.fontSize = '0.9rem';
                div.onmouseover = function() { this.style.background = '#f1f5f9'; };
                div.onmouseout = function() { this.style.background = ''; };
                div.textContent = (p.name || '') + (p.metal_name ? ' (' + p.metal_name + ')' : '');
                div.addEventListener('click', function() {
                    if (scrapProductInput) scrapProductInput.value = (p.name || '') + (p.metal_name ? ' (' + p.metal_name + ')' : '');
                    if (scrapProductId) scrapProductId.value = (p.characteristic_id || p.id) || '';
                    var rateEl = document.getElementById('scrapRate');
                    var purityEl = document.getElementById('scrapPurity');
                    if (rateEl && p.rate != null) rateEl.value = p.rate;
                    if (purityEl && p.opening_purity != null) purityEl.value = p.opening_purity;
                    scrapProductList.style.display = 'none';
                    scrapProductList.innerHTML = '';
                    if (typeof updateScrapCalculations === 'function') updateScrapCalculations();
                });
                scrapProductList.appendChild(div);
            });
        }
        function searchScrapProducts() {
            var mid = scrapMetal ? parseInt(scrapMetal.value, 10) : 0;
            var search = (scrapProductInput && scrapProductInput.value) ? scrapProductInput.value.trim() : '';
            if (!mid) {
                showScrapProductList([]);
                scrapProductList.innerHTML = '<div class="p-2 text-muted small">Select metal first</div>';
                return;
            }
            scrapProductList.innerHTML = '<div class="p-2 text-muted small">Loading...</div>';
            scrapProductList.style.display = 'block';
            var url = 'ajax/get-products-by-metal.php?metal_id=' + encodeURIComponent(mid) + (search ? '&search=' + encodeURIComponent(search) : '');
            fetch(url)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    showScrapProductList(data.success && data.products ? data.products : []);
                })
                .catch(function() {
                    scrapProductList.innerHTML = '<div class="p-2 text-danger small">Error loading products</div>';
                });
        }
        if (scrapProductInput) {
            scrapProductInput.addEventListener('input', function() {
                clearTimeout(scrapSearchTimeout);
                scrapProductId.value = '';
                scrapSearchTimeout = setTimeout(searchScrapProducts, 300);
            });
            scrapProductInput.addEventListener('focus', function() {
                if (scrapMetal && scrapMetal.value) searchScrapProducts();
            });
        }
        document.addEventListener('click', function(e) {
            if (scrapProductList && scrapProductList.style.display === 'block' && !scrapProductList.contains(e.target) && e.target !== scrapProductInput) {
                scrapProductList.style.display = 'none';
            }
        });
        if (scrapMetal) {
            scrapMetal.addEventListener('change', function() {
                if (scrapProductInput) scrapProductInput.value = '';
                if (scrapProductId) scrapProductId.value = '';
                if (scrapProductList) { scrapProductList.style.display = 'none'; scrapProductList.innerHTML = ''; }
            });
        }
        function updateScrapCalculations() {
            var gross = parseFloat(document.getElementById('scrapGrossWt').value) || 0;
            var less = parseFloat(document.getElementById('scrapLessWt').value) || 0;
            var stoneWt = parseFloat(document.getElementById('scrapStoneWt').value) || 0;
            var purity = parseFloat(document.getElementById('scrapPurity').value) || 0;
            var rate = parseFloat(document.getElementById('scrapRate').value) || 0;
            var netWtEl = document.getElementById('scrapNetWt');
            var purityWtEl = document.getElementById('scrapPurityWt');
            var amountEl = document.getElementById('scrapAmount');
            var netWt = Math.max(0, gross - less - stoneWt);
            if (netWtEl) netWtEl.value = netWt.toFixed(3);
            var purityFactor = (purity > 0 && purity <= 1) ? purity : (purity / 100);
            var purityWt = netWt * purityFactor;
            if (purityWtEl) purityWtEl.value = purityWt.toFixed(3);
            var amount = purityWt * rate;
            if (amountEl) amountEl.value = amount.toFixed(2);
        }
        function updateScrapRateFromAmount() {
            var gross = parseFloat(document.getElementById('scrapGrossWt').value) || 0;
            var less = parseFloat(document.getElementById('scrapLessWt').value) || 0;
            var stoneWt = parseFloat(document.getElementById('scrapStoneWt').value) || 0;
            var purity = parseFloat(document.getElementById('scrapPurity').value) || 0;
            var amount = parseFloat(document.getElementById('scrapAmount').value) || 0;
            var netWt = Math.max(0, gross - less - stoneWt);
            var purityFactor = (purity > 0 && purity <= 1) ? purity : (purity / 100);
            var purityWt = netWt * purityFactor;
            var rateEl = document.getElementById('scrapRate');
            if (rateEl && purityWt > 0) rateEl.value = (amount / purityWt).toFixed(4);
        }
        window.updateScrapCalculations = updateScrapCalculations;
        ['scrapGrossWt', 'scrapLessWt', 'scrapStoneWt', 'scrapPurity', 'scrapRate'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) {
                el.addEventListener('input', updateScrapCalculations);
                el.addEventListener('change', updateScrapCalculations);
            }
        });
        var scrapAmountEl = document.getElementById('scrapAmount');
        if (scrapAmountEl) {
            scrapAmountEl.addEventListener('input', updateScrapRateFromAmount);
            scrapAmountEl.addEventListener('change', updateScrapRateFromAmount);
        }
    })();
    
    // Save payment
    function savePayment(type) {
        let paymentData = {
            type: type,
            deposit_into: '',
            transaction_no: '',
            cheque_date: '',
            card_no: '',
            amount: 0,
            purity_carat: '',
            quantity: 0,
            diamond_category: '',
            q_more: ''
        };
        
        if (type === 'cash') {
            paymentData.deposit_into = document.getElementById('cashDepositInto').value;
            paymentData.amount = parseFloat(document.getElementById('cashAmount').value) || 0;
        } else if (type === 'bank') {
            paymentData.deposit_into = document.getElementById('bankDepositInto').value;
            paymentData.transaction_no = document.getElementById('bankTransNo').value;
            paymentData.amount = parseFloat(document.getElementById('bankAmount').value) || 0;
        } else if (type === 'cheque') {
            paymentData.deposit_into = document.getElementById('chequeDepositInto').value;
            paymentData.transaction_no = document.getElementById('chequeTransNo').value;
            paymentData.cheque_date = document.getElementById('chequeDate').value;
            paymentData.amount = parseFloat(document.getElementById('chequeAmount').value) || 0;
        } else if (type === 'upi') {
            paymentData.deposit_into = document.getElementById('upiDepositInto').value;
            paymentData.transaction_no = document.getElementById('upiTransNo').value;
            paymentData.amount = parseFloat(document.getElementById('upiAmount').value) || 0;
        } else if (type === 'card') {
            paymentData.deposit_into = document.getElementById('cardDepositInto').value;
            paymentData.transaction_no = document.getElementById('cardTransNo').value;
            paymentData.card_no = document.getElementById('cardNumber').value;
            paymentData.amount = parseFloat(document.getElementById('cardAmount').value) || 0;
        } else if (type === 'metal-exchange') {
            paymentData.deposit_into = 'Metal Exchange';
            paymentData.purity_carat = document.getElementById('metalExchangePurity').value;
            paymentData.quantity = parseFloat(document.getElementById('metalExchangeQty').value) || 0;
            paymentData.amount = parseFloat(document.getElementById('metalExchangeAmount').value) || 0;
        } else if (type === 'scrap') {
            paymentData.deposit_into = 'Scrap';
            paymentData.purity_carat = document.getElementById('scrapPurity').value;
            paymentData.quantity = parseFloat(document.getElementById('scrapQty').value) || 0;
            paymentData.amount = parseFloat(document.getElementById('scrapAmount').value) || 0;
            // Store all scrap fields so edit can restore them
            paymentData.scrap_metal_id = (document.getElementById('scrapMetal') && document.getElementById('scrapMetal').value) ? document.getElementById('scrapMetal').value : '';
            paymentData.scrap_product_id = (document.getElementById('scrapProductId') && document.getElementById('scrapProductId').value) ? document.getElementById('scrapProductId').value : '';
            paymentData.scrap_product_name = (document.getElementById('scrapProductInput') && document.getElementById('scrapProductInput').value) ? document.getElementById('scrapProductInput').value : '';
            paymentData.scrap_gross_wt = (document.getElementById('scrapGrossWt') && document.getElementById('scrapGrossWt').value) ? document.getElementById('scrapGrossWt').value : '0';
            paymentData.scrap_less_wt = (document.getElementById('scrapLessWt') && document.getElementById('scrapLessWt').value) ? document.getElementById('scrapLessWt').value : '0';
            paymentData.scrap_stone_wt = (document.getElementById('scrapStoneWt') && document.getElementById('scrapStoneWt').value) ? document.getElementById('scrapStoneWt').value : '0';
            paymentData.scrap_net_wt = (document.getElementById('scrapNetWt') && document.getElementById('scrapNetWt').value) ? document.getElementById('scrapNetWt').value : '0';
            paymentData.scrap_purity_wt = (document.getElementById('scrapPurityWt') && document.getElementById('scrapPurityWt').value) ? document.getElementById('scrapPurityWt').value : '0';
            paymentData.scrap_rate = (document.getElementById('scrapRate') && document.getElementById('scrapRate').value) ? document.getElementById('scrapRate').value : '0';
            paymentData.scrap_item_code = (document.getElementById('scrapItemCode') && document.getElementById('scrapItemCode').value) ? document.getElementById('scrapItemCode').value : '';
        }
        paymentData.previous_balance_amount = 0; // Previous balance is applied via "Use previous balance" on main form only
        
        if (paymentData.amount <= 0) {
            alert('Please enter a valid amount');
            return;
        }
        
        // Refresh summary panel so totals and balance are up to date
        if (typeof updateSummaryPanel === 'function') updateSummaryPanel();
        
        // User can add any amount; extra over balance due is recorded and shows as previous balance (credit/advance) for next time
        // No validation that blocks overpayment - backend handles ledger and balance_amt.
        
        // If editing existing payment: update row and array, then close
        if (window._editingPaymentId) {
            const existingPayment = typeof payments !== 'undefined' ? payments.find(function(p) { return p.id === window._editingPaymentId; }) : null;
            if (existingPayment) {
                existingPayment.amount = paymentData.amount;
                existingPayment.deposit_into = paymentData.deposit_into || '';
                existingPayment.transaction_no = paymentData.transaction_no || '';
                existingPayment.cheque_date = paymentData.cheque_date || '';
                existingPayment.previous_balance_amount = parseFloat(existingPayment.previous_balance_amount) || 0;
                if (type === 'scrap' && paymentData.scrap_metal_id !== undefined) {
                    existingPayment.purity_carat = paymentData.purity_carat;
                    existingPayment.quantity = paymentData.quantity;
                    existingPayment.scrap_metal_id = paymentData.scrap_metal_id;
                    existingPayment.scrap_product_id = paymentData.scrap_product_id;
                    existingPayment.scrap_product_name = paymentData.scrap_product_name;
                    existingPayment.scrap_gross_wt = paymentData.scrap_gross_wt;
                    existingPayment.scrap_less_wt = paymentData.scrap_less_wt;
                    existingPayment.scrap_stone_wt = paymentData.scrap_stone_wt;
                    existingPayment.scrap_net_wt = paymentData.scrap_net_wt;
                    existingPayment.scrap_purity_wt = paymentData.scrap_purity_wt;
                    existingPayment.scrap_rate = paymentData.scrap_rate;
                    existingPayment.scrap_item_code = paymentData.scrap_item_code;
                }
                const totalDisplay = paymentData.amount + (parseFloat(existingPayment.previous_balance_amount) || 0);
                const row = document.getElementById(window._editingPaymentId);
                if (row) {
                    row.setAttribute('data-current-order-amount', paymentData.amount.toFixed(2));
                    const amountTd = row.querySelector('[data-payment-amount]');
                    if (amountTd) amountTd.textContent = totalDisplay.toFixed(2);
                }
            }
            window._editingPaymentId = null;
            $('.modal').modal('hide');
            clearPaymentModal(type);
            updatePaymentTotals();
            updateSummaryPanel();
            return;
        }
        
        // Add new payment
        paymentRowIndex++;
        paymentData.id = 'payment-' + paymentRowIndex;
        payments.push(paymentData);
        addPaymentToTable(paymentData);
        
        $('.modal').modal('hide');
        clearPaymentModal(type);
        if (typeof updatePaymentTotals === 'function') updatePaymentTotals();
        if (typeof updateSummaryPanel === 'function') updateSummaryPanel();
    }
    window.savePayment = savePayment;
    
    // Add payment to table
    function addPaymentToTable(payment) {
        const tbody = document.getElementById('paymentTableBody');
        if (!tbody) {
            console.error('Payment table body (#paymentTableBody) not found. Scrap/payment row not added.');
            return;
        }
        const noPaymentRow = tbody.querySelector('.no-payment-row');
        if (noPaymentRow) {
            noPaymentRow.remove();
        }
        
        // Keep global payments array in sync so editPayment() can find this payment
        if (typeof payments !== 'undefined' && payments.indexOf(payment) === -1) payments.push(payment);
        
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
    
    // Edit payment: open modal and populate with row data so user can change amount
    function editPayment(paymentId) {
        let payment = typeof payments !== 'undefined' ? payments.find(function(p) { return p.id === paymentId; }) : null;
        if (!payment) {
            // Fallback: get type and fields from row (e.g. loaded payments not in array)
            const row = document.getElementById(paymentId);
            if (row) {
                const cells = row.querySelectorAll('td');
                const typeCell = cells[0];
                const typeLabel = typeCell ? (typeCell.textContent || '').trim().toLowerCase() : '';
                const depositInto = cells.length > 1 ? (cells[1].textContent || '').trim() : '';
                const transactionNo = cells.length > 2 ? (cells[2].textContent || '').trim() : '';
                const amount = parseFloat(row.getAttribute('data-current-order-amount') || 0) || parseFloat(row.querySelector('[data-payment-amount]')?.textContent || 0);
                const typeMap = { 'cash': 'cash', 'bank': 'bank', 'cheque': 'cheque', 'upi': 'upi', 'card': 'card', 'm. exch.': 'metal-exchange', 'scrap': 'scrap' };
                const type = typeMap[typeLabel] || 'cash';
                openPaymentModal(type);
                if (type === 'cash' && amount > 0) {
                    const cashAmountEl = document.getElementById('cashAmount');
                    if (cashAmountEl) cashAmountEl.value = amount.toFixed(2);
                } else if (type === 'bank') {
                    setSelectByText(document.getElementById('bankDepositInto'), depositInto);
                    var bankTransEl = document.getElementById('bankTransNo');
                    if (bankTransEl) bankTransEl.value = transactionNo;
                    var bankAmtEl = document.getElementById('bankAmount');
                    if (bankAmtEl) bankAmtEl.value = amount > 0 ? amount.toFixed(2) : '0.00';
                } else if (type === 'cheque') {
                    setSelectByText(document.getElementById('chequeDepositInto'), depositInto);
                    var chqTransEl = document.getElementById('chequeTransNo');
                    if (chqTransEl) chqTransEl.value = transactionNo;
                    var chqAmtEl = document.getElementById('chequeAmount');
                    if (chqAmtEl) chqAmtEl.value = amount > 0 ? amount.toFixed(2) : '0.00';
                } else if (type === 'upi') {
                    setSelectByText(document.getElementById('upiDepositInto'), depositInto);
                    var upiAmtEl = document.getElementById('upiAmount');
                    if (upiAmtEl) upiAmtEl.value = amount > 0 ? amount.toFixed(2) : '0.00';
                } else if (type === 'card') {
                    setSelectByText(document.getElementById('cardDepositInto'), depositInto);
                    var cardAmtEl = document.getElementById('cardAmount');
                    if (cardAmtEl) cardAmtEl.value = amount > 0 ? amount.toFixed(2) : '0.00';
                } else if (type === 'scrap') {
                    var scrapAmtEl = document.getElementById('scrapAmount');
                    if (scrapAmtEl) scrapAmtEl.value = amount > 0 ? amount.toFixed(2) : '0.00';
                    var purityVal = cells.length > 4 ? (cells[4].textContent || '').trim() : '';
                    var scrapPurityEl = document.getElementById('scrapPurity');
                    if (scrapPurityEl && purityVal) scrapPurityEl.value = purityVal;
                    var qtyVal = cells.length > 7 ? parseFloat((cells[7].textContent || '').trim()) : 1;
                    var scrapQtyEl = document.getElementById('scrapQty');
                    if (scrapQtyEl) scrapQtyEl.value = !isNaN(qtyVal) ? qtyVal : 1;
                }
                window._editingPaymentId = paymentId;
                return;
            }
        }
        if (payment) {
            openPaymentModal(payment.type);
            if (payment.type === 'cash') {
                const cashAmountEl = document.getElementById('cashAmount');
                if (cashAmountEl) cashAmountEl.value = (parseFloat(payment.amount) || 0).toFixed(2);
            } else if (payment.type === 'bank') {
                setSelectByText(document.getElementById('bankDepositInto'), payment.deposit_into || '');
                var bankTransEl = document.getElementById('bankTransNo');
                if (bankTransEl) bankTransEl.value = payment.transaction_no || '';
                var bankAmtEl = document.getElementById('bankAmount');
                if (bankAmtEl) bankAmtEl.value = (parseFloat(payment.amount) || 0).toFixed(2);
            } else if (payment.type === 'cheque') {
                setSelectByText(document.getElementById('chequeDepositInto'), payment.deposit_into || '');
                var chqTransEl = document.getElementById('chequeTransNo');
                if (chqTransEl) chqTransEl.value = payment.transaction_no || '';
                var chqAmtEl = document.getElementById('chequeAmount');
                if (chqAmtEl) chqAmtEl.value = (parseFloat(payment.amount) || 0).toFixed(2);
            } else if (payment.type === 'upi') {
                setSelectByText(document.getElementById('upiDepositInto'), payment.deposit_into || '');
                var upiAmtEl = document.getElementById('upiAmount');
                if (upiAmtEl) upiAmtEl.value = (parseFloat(payment.amount) || 0).toFixed(2);
            } else if (payment.type === 'card') {
                setSelectByText(document.getElementById('cardDepositInto'), payment.deposit_into || '');
                var cardAmtEl = document.getElementById('cardAmount');
                if (cardAmtEl) cardAmtEl.value = (parseFloat(payment.amount) || 0).toFixed(2);
            } else if (payment.type === 'scrap') {
                var sm = document.getElementById('scrapMetal');
                if (sm && payment.scrap_metal_id) sm.value = payment.scrap_metal_id;
                var spi = document.getElementById('scrapProductInput');
                if (spi && payment.scrap_product_name) spi.value = payment.scrap_product_name;
                var spid = document.getElementById('scrapProductId');
                if (spid && payment.scrap_product_id) spid.value = payment.scrap_product_id;
                var sq = document.getElementById('scrapQty');
                if (sq) sq.value = (payment.quantity != null && payment.quantity !== '') ? payment.quantity : '1';
                var sgw = document.getElementById('scrapGrossWt');
                if (sgw) sgw.value = (payment.scrap_gross_wt != null && payment.scrap_gross_wt !== '') ? payment.scrap_gross_wt : '0';
                var slw = document.getElementById('scrapLessWt');
                if (slw) slw.value = (payment.scrap_less_wt != null && payment.scrap_less_wt !== '') ? payment.scrap_less_wt : '0';
                var ssw = document.getElementById('scrapStoneWt');
                if (ssw) ssw.value = (payment.scrap_stone_wt != null && payment.scrap_stone_wt !== '') ? payment.scrap_stone_wt : '0';
                var snw = document.getElementById('scrapNetWt');
                if (snw) snw.value = (payment.scrap_net_wt != null && payment.scrap_net_wt !== '') ? payment.scrap_net_wt : '0';
                var sp = document.getElementById('scrapPurity');
                if (sp) sp.value = (payment.purity_carat != null && payment.purity_carat !== '') ? payment.purity_carat : '1';
                var spw = document.getElementById('scrapPurityWt');
                if (spw) spw.value = (payment.scrap_purity_wt != null && payment.scrap_purity_wt !== '') ? payment.scrap_purity_wt : '0';
                var sr = document.getElementById('scrapRate');
                if (sr) sr.value = (payment.scrap_rate != null && payment.scrap_rate !== '') ? payment.scrap_rate : '0';
                var sa = document.getElementById('scrapAmount');
                if (sa) sa.value = (parseFloat(payment.amount) || 0).toFixed(2);
                var sic = document.getElementById('scrapItemCode');
                if (sic && payment.scrap_item_code != null) sic.value = payment.scrap_item_code;
                if (typeof updateScrapCalculations === 'function') updateScrapCalculations();
            }
            window._editingPaymentId = paymentId;
        }
    }
    // Helper: set <select> value by option text (in case stored value is label not value)
    function setSelectByText(selectEl, text) {
        if (!selectEl || text === '') { if (selectEl) selectEl.value = ''; return; }
        text = String(text).trim();
        var opts = selectEl.options;
        for (var i = 0; i < opts.length; i++) {
            if (opts[i].text.trim() === text || opts[i].value === text) {
                selectEl.value = opts[i].value;
                return;
            }
        }
        selectEl.value = '';
    }
    
    // Update payment totals
    function updatePaymentTotals() {
        // Calculate payment totals (handled in updateSummaryPanel now)
        // This function can be used for additional payment-specific calculations if needed
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
    
    // Multiple comments: list with add, edit, delete, date/time
    window.paymentCommentsList = [];
    var paymentCommentUserName = <?php echo json_encode(isset($_SESSION['user_name']) ? $_SESSION['user_name'] : (isset($_SESSION['name']) ? $_SESSION['name'] : 'User')); ?>;
    function renderPaymentCommentsList() {
        var listEl = document.getElementById('paymentCommentsList');
        var hiddenEl = document.getElementById('paymentCommentsData');
        if (!listEl) return;
        try { if (hiddenEl) hiddenEl.value = JSON.stringify(window.paymentCommentsList); } catch (e) {}
        if (window.paymentCommentsList.length === 0) {
            listEl.innerHTML = '<div class="text-muted small py-2">No comments yet.</div>';
            return;
        }
        var html = '';
        window.paymentCommentsList.forEach(function(c, idx) {
            var dt = c.added_at || '';
            var by = (c.added_by || paymentCommentUserName || 'User').toString().replace(/</g, '&lt;').replace(/"/g, '&quot;');
            var text = (c.text || '').toString().replace(/</g, '&lt;').replace(/"/g, '&quot;');
            html += '<div class="d-flex align-items-start justify-content-between border-bottom py-2 px-1" data-comment-index="' + idx + '" style="gap: 8px;">';
            html += '<div class="flex-grow-1"><span class="comment-text">' + text + '</span>';
            html += '<div class="small text-muted mt-1">' + by + ' &middot; ' + dt + '</div></div>';
            html += '<div class="d-flex align-items-center" style="flex-shrink: 0;">';
            html += '<button type="button" class="btn btn-link p-0 mr-1 text-primary" onclick="editPaymentComment(' + idx + ')" title="Edit"><i class="feather icon-edit-2" style="width: 14px; height: 14px;"></i></button>';
            html += '<button type="button" class="btn btn-link p-0 text-danger" onclick="deletePaymentComment(' + idx + ')" title="Delete"><i class="feather icon-trash-2" style="width: 14px; height: 14px;"></i></button>';
            html += '</div></div>';
        });
        listEl.innerHTML = html;
    }
    function addPaymentComment() {
        var input = document.getElementById('paymentComment');
        if (!input) return;
        var text = (input.value || '').trim();
        if (!text) return;
        var now = new Date();
        var dateStr = now.toLocaleDateString('en-IN', { day: '2-digit', month: '2-digit', year: 'numeric' });
        var timeStr = now.toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit', hour12: true });
        window.paymentCommentsList.push({ text: text, added_by: paymentCommentUserName, added_at: dateStr + ' ' + timeStr });
        input.value = '';
        renderPaymentCommentsList();
    }
    function deletePaymentComment(idx) {
        if (idx < 0 || idx >= window.paymentCommentsList.length) return;
        window.paymentCommentsList.splice(idx, 1);
        renderPaymentCommentsList();
    }
    function editPaymentComment(idx) {
        if (idx < 0 || idx >= window.paymentCommentsList.length) return;
        var newText = prompt('Edit comment:', window.paymentCommentsList[idx].text || '');
        if (newText === null) return;
        newText = (newText || '').trim();
        if (newText) {
            window.paymentCommentsList[idx].text = newText;
            renderPaymentCommentsList();
        }
    }
    var paymentCommentAddBtn = document.getElementById('paymentCommentAddBtn');
    if (paymentCommentAddBtn) {
        paymentCommentAddBtn.addEventListener('click', function() { addPaymentComment(); });
    }
    var paymentCommentInput = document.getElementById('paymentComment');
    if (paymentCommentInput) {
        paymentCommentInput.addEventListener('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); addPaymentComment(); } });
    }
    renderPaymentCommentsList();
    
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
            document.getElementById('chequeDate').value = <?php echo json_encode(date('Y-m-d')); ?>;
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
            const scrapMetalEl = document.getElementById('scrapMetal');
            const scrapProductInputEl = document.getElementById('scrapProductInput');
            const scrapProductIdEl = document.getElementById('scrapProductId');
            const scrapProductListEl = document.getElementById('scrapProductList');
            if (scrapMetalEl) scrapMetalEl.value = '';
            if (scrapProductInputEl) scrapProductInputEl.value = '';
            if (scrapProductIdEl) scrapProductIdEl.value = '';
            if (scrapProductListEl) { scrapProductListEl.style.display = 'none'; scrapProductListEl.innerHTML = ''; }
            document.getElementById('scrapQty').value = '1';
            document.getElementById('scrapLessWt').value = '0';
            document.getElementById('scrapStoneWt').value = '0';
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
            'carat': 'Karat',
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
            'tax-type': 'Tax Type',
            'tax-percent': 'Tax %',
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
</script>
<!-- Print Invoice Confirmation Modal -->
<div id="printInvoiceModal" class="print-invoice-modal" style="display: none;">
    <div class="print-invoice-modal-content">
        <button class="print-invoice-modal-close" onclick="closePrintInvoiceModal()">&times;</button>
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
        <p class="print-invoice-modal-message">Do you want to print invoice?</p>
        <div class="print-invoice-modal-buttons">
            <button class="print-invoice-btn-yes" onclick="confirmPrintInvoice()">Print</button>
            <button class="print-invoice-btn-no" onclick="closePrintInvoiceModal()">No</button>
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
    background: linear-gradient(135deg, #E8EAF6 0%, #C5CAE9 100%);
    border-radius: 8px;
    position: relative;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: space-between;
    padding: 12px 8px;
}

.receipt-lines {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-top: 8px;
}

.receipt-line {
    height: 2px;
    background: #9CA3AF;
    border-radius: 1px;
}

.receipt-line:nth-child(1) {
    width: 90%;
}

.receipt-line:nth-child(2) {
    width: 75%;
}

.receipt-line:nth-child(3) {
    width: 60%;
}

.receipt-dollar {
    position: absolute;
    left: 12px;
    top: 25px;
    width: 20px;
    height: 20px;
    background: #F59E0B;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 12px;
}

.receipt-checkmark {
    position: absolute;
    right: 10px;
    bottom: 15px;
    width: 24px;
    height: 24px;
    background: #10B981;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    font-size: 12px;
}

.print-invoice-modal-title {
    font-size: 28px;
    font-weight: 700;
    color: #1E40AF;
    margin: 0 0 12px 0;
}

.print-invoice-modal-message {
    font-size: 16px;
    color: #64748B;
    margin: 0 0 30px 0;
}

.print-invoice-modal-buttons {
    display: flex;
    gap: 12px;
    justify-content: center;
}

.print-invoice-btn-yes,
.print-invoice-btn-no {
    padding: 12px 32px;
    border: none;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    min-width: 100px;
}

.print-invoice-btn-yes {
    background: #11294b;
    color: #fff;
}

.print-invoice-btn-yes:hover {
    background: #4a2d6c;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(90, 59, 140, 0.3);
}

.print-invoice-btn-no {
    background: #FCE7F3;
    color: #EC4899;
}

.print-invoice-btn-no:hover {
    background: #FBCFE8;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(236, 72, 153, 0.2);
}
</style>

<script>
    // Store invoice ID for print confirmation
    let savedInvoiceId = null;
    
    // Show print invoice confirmation modal
    function showPrintInvoiceModal(invoiceId) {
        console.log('showPrintInvoiceModal called with invoiceId:', invoiceId);
        savedInvoiceId = invoiceId;
        
        // Use a small delay to ensure DOM is ready
        setTimeout(function() {
            const modal = document.getElementById('printInvoiceModal');
            console.log('Modal element:', modal);
            if (modal) {
                modal.style.display = 'flex';
                modal.style.zIndex = '10000';
                modal.style.visibility = 'visible';
                modal.style.opacity = '1';
                console.log('Modal displayed');
            } else {
                console.error('Print invoice modal not found!');
                // Fallback: show confirm dialog if modal not found
                if (confirm('Sale Invoice saved successfully! Do you want to print the invoice?')) {
                    window.open('sale-invoice-print.php?id=' + invoiceId, '_blank', 'width=1200,height=800');
                }
                if (window.pendingRedirectUrl) {
                    window.location.href = window.pendingRedirectUrl;
                }
            }
        }, 200);
    }
    
    // Close print invoice modal
    function closePrintInvoiceModal() {
        const modal = document.getElementById('printInvoiceModal');
        if (modal) {
            modal.style.display = 'none';
        }
        savedInvoiceId = null;
        
        // Redirect after closing modal
        if (window.pendingRedirectUrl) {
            window.location.href = window.pendingRedirectUrl;
            window.pendingRedirectUrl = null;
        }
    }
    
    // Confirm print invoice
    function confirmPrintInvoice() {
        if (savedInvoiceId) {
            window.open('sale-invoice-print.php?id=' + savedInvoiceId, '_blank', 'width=1200,height=800');
        }
        closePrintInvoiceModal();
    }
    
    // Close modal when clicking outside
    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById('printInvoiceModal');
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closePrintInvoiceModal();
                }
            });
        }
    });
    
    // Make functions globally accessible
    window.showPrintInvoiceModal = showPrintInvoiceModal;
    window.closePrintInvoiceModal = closePrintInvoiceModal;
    window.confirmPrintInvoice = confirmPrintInvoice;
</script>

</body>

</html>




