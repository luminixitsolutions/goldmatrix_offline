<?php 
require_once __DIR__ . '/includes/session_init.php';
require_once 'config.php';
if (function_exists('auragold_voucher_page_init')) {
    auragold_voucher_page_init();
}
require_once __DIR__ . '/includes/auragold_party_select2.php';
require_once __DIR__ . '/includes/auragold-gst-page-vars.php';
require_once __DIR__ . '/includes/auragold_branch_data_scope.php';
require_once __DIR__ . '/includes/user_management_schema.php';

$auragold_working_branch_id = function_exists('auragold_resolve_working_branch_id_for_page')
    ? auragold_resolve_working_branch_id_for_page()
    : 0;
if ($auragold_working_branch_id <= 0 && function_exists('auragold_effective_branch_id')) {
    $auragold_working_branch_id = (int) auragold_effective_branch_id();
}
if ($auragold_working_branch_id <= 0 && !empty($_SESSION['working_branch_id'])) {
    $auragold_working_branch_id = (int) $_SESSION['working_branch_id'];
} elseif ($auragold_working_branch_id <= 0 && !empty($_SESSION['branch_id'])) {
    $auragold_working_branch_id = (int) $_SESSION['branch_id'];
}

// Load Metals for category tabs (branch-scoped when logged into a branch)
$metals = getList("SELECT id, display_name, system_name FROM tbl_metal WHERE status = 1 " . auragold_master_list_sql_suffix($conn, 'tbl_metal') . " ORDER BY id ASC");
require_once __DIR__ . '/includes/auragold_voucher_runtime_settings.php';
$auragold_voucher_runtime_client = auragold_voucher_runtime_bootstrap($conn, $metals, 'Purchase Invoice');
// Voucher settings (metal-wise): used for reverse calculation result column
$voucher_settings_by_metal = function_exists('getVoucherSettings') ? getVoucherSettings() : [];

// Load Karat master data (Purchase + Common)
require_once __DIR__ . '/includes/auragold_carat_purity_for_schema.php';
$carats = auragold_get_carat_list($conn, 'purchase', $auragold_working_branch_id);

// Load Location master data
$locations = getList("SELECT id, name FROM tbl_location WHERE status = 1 " . auragold_master_list_sql_suffix($conn, 'tbl_location') . " ORDER BY id ASC");

// Active currencies (masters)
$currencies = function_exists('auragold_load_master_currencies')
    ? auragold_load_master_currencies($conn)
    : [];
if (!is_array($currencies)) {
    $currencies = [];
}
$pi_currency_rates_by_name = function_exists('auragold_currency_exchange_rates_by_name')
    ? auragold_currency_exchange_rates_by_name($conn)
    : [];
if (!is_array($pi_currency_rates_by_name)) {
    $pi_currency_rates_by_name = [];
}
$pi_base_currency_name = '';
foreach ($currencies as $_piCur) {
    if (!empty($_piCur['is_base'])) {
        $pi_base_currency_name = trim((string) ($_piCur['name'] ?? ''));
        break;
    }
}
if ($pi_base_currency_name === '' && !empty($currencies[0]['name'])) {
    $pi_base_currency_name = trim((string) $currencies[0]['name']);
}

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

// Next invoice no: Bill Series for voucher "Purchase Invoice" (tbl_bill_series + tbl_voucher_types); else legacy PI-1, PI-2
$next_order_no = function_exists('getNextPurchaseInvoiceNo') ? getNextPurchaseInvoiceNo($conn) : 'PI-1';

// Purchase / sales person dropdown (stored in tbl_purchase_invoices.purchase_person) — branch-scoped
$sales_person_users = auragold_sales_person_user_display_names($conn_master);

// Load invoice for editing if ID provided (tbl_purchase_invoices)
$edit_order_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$edit_order = null;
$edit_items = [];
$edit_payments = [];

$auragold_voucher_ds_kind = 'purchase_invoice';
$auragold_voucher_ds_db_id = (int) ($edit_order_id ?? 0);

if (!empty($edit_order_id)) {
    $edit_order = getRecord("SELECT * FROM tbl_purchase_invoices WHERE id = " . intval($edit_order_id));
    if ($edit_order) {
        // Include metal_id from product_characteristics so edit modal opens on correct tab (Diamond & Stones etc.)
        $edit_items = getList("
            SELECT i.*, COALESCE(pc.metal_id,
                (SELECT metal_id FROM tbl_product_characteristics WHERE product_id = i.product_id AND status = 1 LIMIT 1)
            ) as metal_id
            FROM tbl_purchase_invoice_items i
            LEFT JOIN tbl_product_characteristics pc ON pc.id = i.product_characteristic_id AND pc.product_id = i.product_id
            WHERE i.invoice_id = " . intval($edit_order_id) . " ORDER BY i.id ASC");
        $edit_payments = getList("SELECT * FROM tbl_purchase_invoice_payments WHERE invoice_id = " . intval($edit_order_id));
        $next_order_no = $edit_order['invoice_no'] ?? '';
        if (!isset($edit_order['order_no'])) {
            $edit_order['order_no'] = $edit_order['invoice_no'] ?? '';
        }
        if (!array_key_exists('against_of', $edit_order)) {
            $edit_order['against_of'] = '';
        }
        $edit_order['against_of'] = $edit_order['against_of'] ?? '';
        $edit_order['supplier_name'] = $edit_order['supplier_name'] ?? '';
        $edit_order['customer_name'] = $edit_order['supplier_name'] ?? '';
        $edit_order['supplier_id'] = (int)($edit_order['supplier_id'] ?? 0);
        $edit_order['customer_id'] = $edit_order['supplier_id'];
        $edit_order['invoice_date'] = $edit_order['invoice_date'] ?? '';
        $edit_order['purchase_person'] = $edit_order['purchase_person'] ?? '';
        $edit_order['sales_person'] = $edit_order['purchase_person'] ?? '';
    }
}

$pi_save_blocked_by_sale_fixing = false;
if (!empty($edit_order) && is_array($edit_order) && !empty($edit_order['invoice_no']) && function_exists('auragold_pi_has_active_sale_fixing')) {
    $pi_save_blocked_by_sale_fixing = auragold_pi_has_active_sale_fixing($edit_order['invoice_no']);
}

//// Optional: set to true to debug edit load (prints $edit_order_id and getRecord result)
// if (defined('PI_EDIT_DEBUG') && PI_EDIT_DEBUG) { echo '<pre>edit_order_id=' . var_export($edit_order_id, true) . "\ngetRecord result=" . print_r($edit_order, true) . '</pre>'; }

// Allowed Purchase Invoice print languages (English + optional one from Invoice Print Settings)
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
    /* Currency + rate combined control */
    .pi-currency-rate-combo {
        display: flex;
        align-items: stretch;
        width: 100%;
        border: 1px solid #ced4da;
        border-radius: 0.2rem;
        background: #fff;
        overflow: hidden;
    }
    .pi-currency-rate-combo:focus-within {
        border-color: #80bdff;
        box-shadow: 0 0 0 0.15rem rgba(0, 123, 255, 0.2);
    }
    .pi-currency-rate-combo #currency {
        flex: 1 1 auto;
        min-width: 0;
        border: none !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        background: transparent;
    }
    .pi-currency-rate-combo #currencyRate {
        flex: 0 0 4.5rem;
        width: 4.5rem;
        max-width: 4.5rem;
        border: none !important;
        border-left: 1px solid #ced4da !important;
        border-radius: 0 !important;
        box-shadow: none !important;
        text-align: right;
        padding-left: 0.35rem;
        padding-right: 0.45rem;
        background: #f8fafc;
        font-variant-numeric: tabular-nums;
    }

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
    .btn-save-invoice:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }
    .btn-save-invoice:disabled:hover {
        background: linear-gradient(135deg, #c5a864 0%, #a68a4a 100%);
        transform: none;
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
    .billing-form .pi-show-stock-journal-wrap {
        margin-top: 4px !important;
    }
    .billing-form .pi-show-stock-journal-label {
        cursor: pointer;
        font-weight: 500 !important;
        font-size: 12px !important;
        text-transform: none !important;
        letter-spacing: normal;
        color: #334155 !important;
        white-space: nowrap;
    }
    .billing-form .pi-show-stock-journal-label input[type="checkbox"] {
        width: 15px;
        height: 15px;
        flex-shrink: 0;
        cursor: pointer;
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
    .add-item-link.add-item-link--disabled,
    .add-item-link.add-item-link--disabled:hover {
        opacity: 0.45;
        cursor: not-allowed !important;
        pointer-events: none;
        transform: none;
        box-shadow: none;
        border-color: #cbd5e1;
        color: #94a3b8;
        background: rgba(148, 163, 184, 0.08);
    }
    .add-item-link.add-item-link--disabled a {
        cursor: not-allowed !important;
        color: #94a3b8;
        pointer-events: none;
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
    @media (min-width: 992px) {
    .company-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 4px;
        background-color: #F8F6F1;
        border-radius: 0;
    }
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
    @media (min-width: 992px) {
        .container-fluid {
            padding-top: 0 !important;
            padding-bottom: 0 !important;
        }
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
    /* Column Groups: parent (group) vs sub-column hierarchy */
    .table-settings-group-block {
        margin-bottom: 0.25rem;
    }
    .table-settings-group-block:last-child {
        margin-bottom: 0;
    }
    .table-settings-group-block.hidden {
        display: none !important;
    }
    .table-settings-group-item {
        font-weight: 600;
    }
    .table-settings-sub-column {
        padding-left: 1.25rem !important;
    }
    .table-settings-item.sub-column-disabled {
        opacity: 0.6;
        pointer-events: none;
    }
    .table-settings-item.sub-column-disabled label {
        cursor: default;
        color: #94a3b8;
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
        margin-top: 1.25rem !important;
        margin-bottom: 1rem !important;
        height: auto !important;
        min-height: 0 !important;
    }
    #productSelectionModal .modal-content {
        border-radius: 10px;
        border: none;
        box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        max-height: calc(100vh - 2.5rem) !important;
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
        max-height: calc(100vh - 8rem) !important;
        padding: 1rem 1.5rem 10px 1.5rem !important;
        -webkit-overflow-scrolling: touch;
    }
    #productSelectionModal .product-category-tabs {
        position: sticky;
        top: 0;
        z-index: 12;
        background: #fff;
        padding-top: 0.35rem;
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
    /* Hidden columns: remove from layout so no empty space; remaining columns shift left */
    #productSelectionModal #productListTable th.hidden,
    #productSelectionModal #productListTable td.hidden {
        width: 0 !important;
        min-width: 0 !important;
        max-width: 0 !important;
        padding: 0 !important;
        margin: 0 !important;
        border-width: 0 !important;
        overflow: hidden !important;
        visibility: collapse !important;
        line-height: 0 !important;
        font-size: 0 !important;
    }
    /* Hidden group header (whole group hidden): ensure no layout space */
    #productSelectionModal #productListTable thead tr:first-child th[data-group].hidden {
        display: none !important;
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
    /* Right columns: Net Amt+Tax, Reverse, Action — right values set dynamically by JS (adjustProductModalStickyRightColumns) */
    #productSelectionModal #productListTable td.sticky-right {
        z-index: 2;
    }
    #productSelectionModal #productListTable thead th.sticky-right {
        z-index: 6;
    }
    #productSelectionModal #productListTable th[data-column="actions"].sticky-right,
    #productSelectionModal #productListTable td[data-column="actions"].sticky-right {
        position: sticky;
        z-index: 5;
        isolation: isolate !important;
        box-shadow: -2px 0 6px rgba(0,0,0,0.06);
        border-left: 1px solid #dee2e6 !important;
    }
    #productSelectionModal #productListTable th[data-column="reverse"].sticky-right,
    #productSelectionModal #productListTable td[data-column="reverse"].sticky-right {
        position: sticky;
        z-index: 5;
        isolation: isolate !important;
        box-shadow: -2px 0 6px rgba(0,0,0,0.06);
        border-left: 1px solid #dee2e6 !important;
    }
    #productSelectionModal #productListTable th[data-column="net-amt-tax"].sticky-right,
    #productSelectionModal #productListTable td[data-column="net-amt-tax"].sticky-right {
        position: sticky;
        z-index: 5;
        isolation: isolate !important;
        box-shadow: -4px 0 10px rgba(0,0,0,0.1) !important;
        border-left: 2px solid #cbd5e1 !important;
    }
    /* Group header must stack above scrolling blue headers (default first-row z-index 8) so no overlap */
    #productSelectionModal #productListTable thead tr:first-child th[data-group="net-reverse"].sticky-right {
        position: sticky;
        min-width: 200px !important;
        width: 200px !important;
        z-index: 9 !important;
        box-shadow: -4px 0 10px rgba(0,0,0,0.1) !important;
        border-left: 2px solid #cbd5e1 !important;
    }
    #productSelectionModal #productListTable thead tr:first-child th[data-column="actions"].sticky-right {
        position: sticky;
        z-index: 10 !important;
        box-shadow: -2px 0 6px rgba(0,0,0,0.08);
    }
    #productSelectionModal #productListTable th[data-column="actions"],
    #productSelectionModal #productListTable td[data-column="actions"] {
        min-width: 80px !important;
        width: 80px !important;
        max-width: 80px !important;
        background: #fff !important;
    }
    #productSelectionModal #productListTable thead th[data-column="actions"] {
        background: #a68a4a !important;
        z-index: 6;
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
    }
    #productSelectionModal #productListTable thead th[data-column="reverse"] {
        background: #a68a4a !important;
        z-index: 6;
    }
    #productSelectionModal #productListTable thead tr:first-child th[data-group="net-reverse"] {
        min-width: 200px !important;
        width: 200px !important;
        z-index: 6;
        border-left: 2px solid #cbd5e1 !important;
        background: #a68a4a !important;
        color: #fff !important;
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
    }
    #productSelectionModal #productListTable thead th[data-column="net-amt-tax"] {
        background: #a68a4a !important;
        z-index: 6;
    }
    #productSelectionModal #productListTable tbody tr:hover td[data-column="net-amt-tax"] {
        background: #f8fafc !important;
    }
    /* Tbody: keep sticky-right cells from painting over scrolling columns to the left on horizontal scroll */
    #productSelectionModal #productListTable tbody td.sticky-right,
    #productSelectionModal #productListTable tbody td[data-column="actions"],
    #productSelectionModal #productListTable tbody td[data-column="reverse"],
    #productSelectionModal #productListTable tbody td[data-column="net-amt-tax"] {
        z-index: 0 !important;
    }
    /* Sticky two-row header: group row + column row stay aligned with vertical scroll */
    #productSelectionModal #productListTable thead.product-modal-thead tr:first-child > th {
        position: sticky;
        top: 0;
        z-index: 8;
        vertical-align: middle;
    }
    #productSelectionModal #productListTable thead.product-modal-thead tr:nth-child(2) > th {
        position: sticky;
        top: 36px;
        z-index: 7;
        box-shadow: 0 1px 0 #c5a864;
    }
    /* Do not use white (#fff) text on #f8fafc — row-2 labels vanish (look like empty white columns). */
    #productSelectionModal #productListTable thead th {
        font-weight: 700;
        font-size: 0.7rem;
        border: 1px solid #c5a864;
    }
    #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column]:not([data-column="actions"]):not([data-column="reverse"]):not([data-column="net-amt-tax"]) {
        background: #11294b !important;
        color: #fff !important;
    }
    #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column] .feather {
        color: #c5a864;
    }
    #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column="reverse"] .feather,
    #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column="net-amt-tax"] .feather {
        color: rgba(255, 255, 255, 0.95);
    }
    #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column] {
        cursor: grab;
    }
    /* Sub-columns in a group: still draggable within group (cursor grab) */
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
    #productSelectionModal #productListTable thead tr:first-child th[data-column="checkbox"].modal-group-drop-before-first {
        box-shadow: inset 0 0 0 3px #c5a864;
        background: rgba(197, 168, 100, 0.3) !important;
    }
    #productSelectionModal #productListTable thead tr:first-child th.product-modal-group-sortable-ghost {
        opacity: 0.45 !important;
    }
    #productSelectionModal #productListTable thead tr:first-child th.product-modal-group-sortable-drag-chosen {
        background: rgba(197, 168, 100, 0.35) !important;
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

    /* —— Purchase Invoice: mobile / tablet (≤991.98px) —— */
    @media (max-width: 991.98px) {
        html {
            height: auto;
            min-height: 100%;
            overflow-x: hidden;
            overflow-y: auto;
        }
        body {
            height: auto;
            min-height: 100vh;
            min-height: 100dvh;
            overflow-x: hidden;
            overflow-y: auto;
        }
        .layout-wrapper {
            height: auto;
            min-height: 100vh;
            min-height: 100dvh;
            overflow-x: hidden;
            overflow-y: visible;
        }
        .layout-content {
            height: auto !important;
            min-height: 0;
            overflow-x: hidden;
            overflow-y: visible !important;
            padding-bottom: 88px !important;
        }

        .layout-content > .container-fluid {
            padding-top: calc(58px + 16px + env(safe-area-inset-top, 0px)) !important;
        }
        .invoice-content-row.row {
            margin-left: 0;
            margin-right: 0;
            margin-top: 8px;
        }
        .invoice-content-row .card-body.billing-form {
            padding-top: 18px !important;
        }
        .invoice-content-row > .col-lg-8,
        .invoice-content-row > .col-lg-4 {
            min-width: 0 !important;
            max-width: 100%;
            flex: 0 0 100%;
        }
        .invoice-content-row .card {
            margin-left: 0 !important;
            margin-right: 0 !important;
        }

        .summary-panel {
            position: relative;
            top: auto;
            margin-top: 0.75rem;
        }
        .summary-panel .invoice-header[style*="margin"] {
            margin-left: 0 !important;
            margin-right: 0 !important;
            margin-top: 0 !important;
            flex-wrap: wrap;
            row-gap: 0.5rem;
        }
        .summary-panel .summary-label {
            white-space: normal;
            max-width: 58%;
            line-height: 1.3;
        }
        .summary-row {
            flex-wrap: wrap;
            row-gap: 0.25rem;
        }

        .billing-form .auragold-mq-billing-grid > [class*="col-"] {
            flex: 0 0 100% !important;
            max-width: 100% !important;
        }
        .billing-form .auragold-mq-billing-grid > [class*="col-"] > .form-group {
            margin-bottom: 14px !important;
            flex-direction: column;
            align-items: stretch;
            gap: 0;
            position: relative;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 14px 10px 10px;
            background: #fff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        }
        .billing-form .auragold-mq-billing-grid > [class*="col-"] > .form-group > label:first-of-type {
            position: absolute;
            top: 0;
            left: 10px;
            transform: translateY(-50%);
            margin: 0;
            padding: 0 6px;
            background: #fff;
            font-size: 10px;
            font-weight: 700;
            color: #11294b;
            letter-spacing: 0.04em;
            line-height: 1;
            max-width: calc(100% - 24px);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .billing-form .auragold-mq-billing-grid > [class*="col-"] > .form-group > .d-flex,
        .billing-form .auragold-mq-billing-grid > [class*="col-"] > .form-group > div:not(.input-group) {
            width: 100%;
            flex: 1 1 auto;
            min-width: 0;
        }
        .billing-form .auragold-mq-billing-grid .add-customer-icon,
        .billing-form .auragold-mq-billing-grid .feather.icon-search {
            top: 50% !important;
        }
        .billing-form .auragold-mq-billing-grid .pi-show-stock-journal-wrap {
            border: none !important;
            box-shadow: none !important;
            padding: 0 !important;
            margin-top: 6px !important;
            margin-bottom: 0 !important;
        }
        .billing-form .auragold-mq-billing-grid .pi-show-stock-journal-wrap > label {
            position: static !important;
            transform: none !important;
            padding: 0 !important;
            background: transparent !important;
            max-width: none !important;
            white-space: normal !important;
        }

        .payment-icons {
            justify-content: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .invoice-content-row .product-list-table-responsive {
            min-height: 280px;
            max-height: min(520px, 58vh);
        }
        .product-list-card .table-header-wrapper {
            flex-direction: column;
            align-items: stretch;
            gap: 8px;
        }
        .product-list-card .table-header-wrapper h6 .text-muted {
            display: block;
            margin-top: 4px;
            font-size: 0.68rem !important;
            line-height: 1.35;
            font-weight: 500;
        }
        .product-list-card .table-settings-btn {
            align-self: flex-start;
        }
    }

    @media (max-width: 575.98px) {
        .invoice-content-row .product-list-table-responsive {
            min-height: 260px;
            max-height: min(480px, 56vh);
        }
        .invoice-header h5 {
            font-size: 0.8rem !important;
        }
        .invoice-header-actions {
            flex-wrap: wrap;
        }
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
                    <?php
                    if (function_exists('auragold_reset_branch_context_caches')) {
                        auragold_reset_branch_context_caches();
                    }
                    include 'sidebar.php';
                    ?>

                    <!-- Top Navigation Tabs -->
                   

                    <div class="row invoice-content-row">
                        <!-- Main Content Area -->
                        <div class="col-lg-8" >
                            <!-- Transaction Details Form -->
                            <div class="card mb-4">
                                <div class="card-body billing-form">
                                        <div class="row auragold-mq-billing-grid">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label>Search Purchase Invoice</label>
                                                    <div style="position: relative;">
                                                        <input type="text" class="form-control form-control-sm" id="searchSaleInvoice" placeholder="Search by vendor name or invoice number..." autocomplete="off" style="padding-right: 35px;">
                                                        <i class="feather icon-search" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); color: #94a3b8; font-size: 12px; pointer-events: none;"></i>
                                                        <div id="saleInvoiceSuggestions" style="display: none; position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #e2e8f0; border-radius: 4px; max-height: 300px; overflow-y: auto; z-index: 1000; box-shadow: 0 4px 12px rgba(0,0,0,0.15); margin-top: 2px;"></div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label>Name *</label>
                                                    <div style="display:flex;align-items:stretch;gap:4px;"><div class="auragold-party-select2-wrap"><select class="form-control form-control-sm" id="customerId" name="customer_id" required><option value="">Select supplier...</option></select><input type="hidden" id="customerName" name="customer_name" value=""><input type="hidden" id="customerBillingState" name="customer_billing_state" value=""></div><button type="button" class="btn btn-sm btn-outline-secondary p-0" id="addCustomerBtn" title="Add / Edit Supplier" style="width:32px;min-width:32px;line-height:1;align-self:stretch;"><i class="feather icon-plus"></i></button></div>
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
                                                <div class="pi-currency-rate-combo">
                                                    <select class="form-control form-control-sm" id="currency" title="Currency">
                                                        <?php
                                                        $selected_currency = (!empty($edit_order) && is_array($edit_order)) ? ($edit_order['currency'] ?? '') : '';
                                                        include __DIR__ . '/includes/currency-select-options.php';
                                                        ?>
                                                    </select>
                                                    <input type="number" class="form-control form-control-sm" id="currencyRate" name="currency_rate"
                                                           value="<?php
                                                               $pi_saved_rate = '';
                                                               if (!empty($edit_order) && is_array($edit_order) && isset($edit_order['exchange_rate']) && $edit_order['exchange_rate'] !== '' && $edit_order['exchange_rate'] !== null) {
                                                                   $pi_saved_rate = (string) $edit_order['exchange_rate'];
                                                               }
                                                               echo htmlspecialchars($pi_saved_rate !== '' ? $pi_saved_rate : '1', ENT_QUOTES, 'UTF-8');
                                                           ?>"
                                                           step="0.000001" min="0" title="Currency Rate" placeholder="Rate">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Ref No.</label>
                                                <input type="text" class="form-control form-control-sm" id="refNo" placeholder="Reference number">
                                            </div>
                                        </div>
                                        
                                        <?php
                                        $pi_order_date_val = date('Y-m-d');
                                        $pi_due_date_val = '';
                                        $pi_sp_selected = '';
                                        if (!empty($edit_order) && is_array($edit_order)) {
                                            if (!empty($edit_order['invoice_date'])) {
                                                $pi_order_date_val = substr($edit_order['invoice_date'], 0, 10);
                                                $pi_due_date_val = auragold_due_date_value($edit_order['due_date'] ?? null);
                                            } else {
                                                $pi_due_date_val = auragold_due_date_value($edit_order['due_date'] ?? null);
                                            }
                                            $pi_sp_selected = trim((string)($edit_order['purchase_person'] ?? $edit_order['sales_person'] ?? ''));
                                        }
                                        $pi_sp_in_list = false;
                                        if ($pi_sp_selected !== '') {
                                            foreach ($sales_person_users as $_spn) {
                                                if (strcasecmp($pi_sp_selected, $_spn) === 0) {
                                                    $pi_sp_in_list = true;
                                                    break;
                                                }
                                            }
                                        }
                                        $pi_show_in_stock_journal = (!empty($edit_order) && is_array($edit_order) && !empty($edit_order['show_in_stock_journal']));
                                        ?>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Date</label>
                                                <input type="date" class="form-control form-control-sm" id="orderDate" value="<?php echo htmlspecialchars($pi_order_date_val); ?>">
                                            </div>
                                            <div class="form-group mb-0 mt-1 pi-show-stock-journal-wrap">
                                                <label class="d-flex align-items-center mb-0 pi-show-stock-journal-label" for="showInStockJournal">
                                                    <input type="checkbox" class="mr-2" id="showInStockJournal" name="show_in_stock_journal" value="1"<?php echo $pi_show_in_stock_journal ? ' checked' : ''; ?>>
                                                    Show in Stock Journal
                                                </label>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Due Date</label>
                                                <input type="date" class="form-control form-control-sm" id="dueDate" value="<?php echo htmlspecialchars($pi_due_date_val); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Sales Person</label>
                                                <select class="form-control form-control-sm" id="salesPerson">
                                                    <option value="">Sales Person</option>
                                                    <?php foreach ($sales_person_users as $sp_name): ?>
                                                    <option value="<?php echo htmlspecialchars($sp_name); ?>"<?php echo ($pi_sp_selected !== '' && strcasecmp($pi_sp_selected, $sp_name) === 0) ? ' selected' : ''; ?>><?php echo htmlspecialchars($sp_name); ?></option>
                                                    <?php endforeach; ?>
                                                    <?php if ($pi_sp_selected !== '' && !$pi_sp_in_list): ?>
                                                    <option value="<?php echo htmlspecialchars($pi_sp_selected); ?>" selected><?php echo htmlspecialchars($pi_sp_selected); ?></option>
                                                    <?php endif; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label>Fixing Type</label>
                                                <select class="form-control form-control-sm" id="fixingType">
                                                    <?php $pi_fix = (!empty($edit_order) && is_array($edit_order)) ? ($edit_order['fixing_type'] ?? 'Standard') : 'Standard'; ?>
                                                    <option value="Standard"<?php echo ($pi_fix === 'Standard') ? ' selected' : ''; ?>>Standard</option>
                                                    <option value="Hedging"<?php echo ($pi_fix === 'Hedging') ? ' selected' : ''; ?>>Hedging</option>
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
                                    <div class="add-item-link add-item-link--disabled" id="addItemBtn" role="button" aria-disabled="true" title="Select a customer first" style="cursor: not-allowed;">
                                        <a href="javascript:void(0)" tabindex="-1" aria-disabled="true"><i class="feather icon-plus"></i> Add Item (Shift + Q)</a>
                                    </div>
                                </div>
                            </div>

                            <!-- Product List Table (shared: includes/product-list-table.php) -->
                            <?php
                            $product_list_prefs_page = 'purchase-invoice-product-table';
                            require __DIR__ . '/includes/product-list-table.php';
                            ?>

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

<?php require __DIR__ . '/includes/payment-cards-markup.php'; ?>
<?php require __DIR__ . '/includes/voucher_diamond_stone_panels.php'; ?>
                                    
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
                                        <h5 class="mb-0" style="font-size: 0.9rem;">Purchase Invoice No: <span id="currentOrderNo"><?php echo htmlspecialchars($next_order_no); ?></span></h5>
                                    </div>
                                    <div class="invoice-header-actions">
                                        <button class="btn-new-invoice btn-sm" onclick="resetOrder()">New +</button>
                                        <button type="button" class="btn-save-invoice btn-sm" id="btnSavePurchaseInvoice" onclick="saveOrder()" <?php echo !empty($pi_save_blocked_by_sale_fixing) ? 'disabled' : ''; ?> title="<?php echo !empty($pi_save_blocked_by_sale_fixing) ? 'Delete the sale fixing first to save changes' : 'Save'; ?>">Save</button>
                                    </div>
                                </div>
                                <!-- Previous Balance (shared: includes/previous-balance-panel.php + js/previous-balance-common.js) -->
                                <?php include __DIR__ . '/includes/previous-balance-panel.php'; ?>

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
                                        <span class="summary-label" id="summaryNetTotalLabel">Net Total</span>
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
                                        <span class="summary-label" id="summaryGrandTotalLabel">Grand Total</span>
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
<?php
$common_modal_show_excel_import = true;
$common_modal_excel_sample_href = 'ajax/download-stock-journal-excel-sample.php?voucher=sale_order';
$common_modal_show_group_single_item_checkbox = true;
include 'includes/common-modal.php';
?>
<?php auragold_echo_party_select2_styles(); ?>
<?php include 'footer-script.php';?>
<?php auragold_echo_party_select2_scripts(['placeholder' => 'Select supplier...'], true); ?>

<?php require __DIR__ . '/includes/voucher_diamond_stone_assets.php'; ?>

<script src="assets/js/product-modal-add-item-common.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/product-modal-add-item-common.js'); ?>"></script>
<?php require __DIR__ . '/includes/auragold-gst-page-bootstrap.php'; ?>
<script src="assets/js/product-list-table-shared.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/product-list-table-shared.js'); ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<?php include __DIR__ . '/includes/auragold_voucher_runtime_scripts.php'; ?>

<script>
    // Runs after footer-script.php (jQuery, Bootstrap). EDIT_ORDER_DATA and master data are valid JSON.
    // Master data for dropdowns (safe fallbacks so no invalid/empty JSON)
    window.AURAGOLD_CURRENCY_RATES = <?php echo json_encode($pi_currency_rates_by_name, JSON_UNESCAPED_UNICODE); ?>;
    window.AURAGOLD_BASE_CURRENCY = <?php echo json_encode($pi_base_currency_name, JSON_UNESCAPED_UNICODE); ?>;
    (function initPurchaseInvoiceCurrencyRate() {
        function rateForCurrency(code) {
            var key = String(code || '').trim().toUpperCase();
            if (!key) return 1;
            var map = window.AURAGOLD_CURRENCY_RATES || {};
            if (Object.prototype.hasOwnProperty.call(map, key)) {
                var n = parseFloat(map[key]);
                return isFinite(n) && n > 0 ? n : 1;
            }
            for (var k in map) {
                if (!Object.prototype.hasOwnProperty.call(map, k)) continue;
                if (String(k).toUpperCase() === key) {
                    var v = parseFloat(map[k]);
                    return isFinite(v) && v > 0 ? v : 1;
                }
            }
            return 1;
        }
        function applyCurrencyRate(force) {
            var sel = document.getElementById('currency');
            var rateEl = document.getElementById('currencyRate');
            if (!sel || !rateEl) return;
            var key = String(sel.value || '').trim().toUpperCase();
            var map = window.AURAGOLD_CURRENCY_RATES || {};
            var next = 1;
            if (key && Object.prototype.hasOwnProperty.call(map, key)) {
                var n = parseFloat(map[key]);
                next = (isFinite(n) && n > 0) ? n : 1;
            } else {
                next = rateForCurrency(sel.value);
            }
            var opt = sel.options[sel.selectedIndex];
            if (opt && String(opt.getAttribute('data-is-base') || '') === '1') {
                next = 1;
            }
            if (force || !rateEl.dataset.userEdited) {
                rateEl.value = (Math.round(next * 1000000) / 1000000).toString();
            }
            // Avoid wiping edit totals before product rows are restored
            var tbody = document.getElementById('productTableBody');
            var hasRows = tbody && tbody.querySelectorAll('tr:not(.no-drag)').length > 0;
            if (typeof updateSummaryPanel === 'function' && (!window.isPurchaseInvoiceEditMode || hasRows)) {
                updateSummaryPanel();
            } else if (typeof window.auragoldUpdatePurchaseCurrencyLabels === 'function') {
                window.auragoldUpdatePurchaseCurrencyLabels();
            }
        }
        window.auragoldApplyPurchaseCurrencyRate = applyCurrencyRate;
        window.auragoldUpdatePurchaseCurrencyLabels = function () {
            var base = String(window.AURAGOLD_BASE_CURRENCY || '').trim();
            var selected = String((document.getElementById('currency') || {}).value || '').trim();
            var netLabel = document.getElementById('summaryNetTotalLabel');
            var grandLabel = document.getElementById('summaryGrandTotalLabel');
            if (netLabel) {
                netLabel.textContent = base ? ('Net Total (' + base + ')') : 'Net Total';
            }
            if (grandLabel) {
                grandLabel.textContent = selected ? ('Grand Total (' + selected + ')') : 'Grand Total';
            }
        };
        document.addEventListener('DOMContentLoaded', function () {
            var sel = document.getElementById('currency');
            var rateEl = document.getElementById('currencyRate');
            if (!sel || !rateEl) return;
            var saved = parseFloat(rateEl.value);
            var hasSaved = isFinite(saved) && saved > 0 && rateEl.value !== '' && rateEl.value !== '1';
            if (!window.isPurchaseInvoiceEditMode || !hasSaved) {
                applyCurrencyRate(true);
            } else if (typeof window.auragoldUpdatePurchaseCurrencyLabels === 'function') {
                window.auragoldUpdatePurchaseCurrencyLabels();
            }
            // Edit mode: do not recalculate totals before populateOrderForm restores rows/DB values
            if (!window.isPurchaseInvoiceEditMode && typeof updateSummaryPanel === 'function') {
                updateSummaryPanel();
            } else if (typeof window.auragoldUpdatePurchaseCurrencyLabels === 'function') {
                window.auragoldUpdatePurchaseCurrencyLabels();
            }
            sel.addEventListener('change', function () {
                if (rateEl) delete rateEl.dataset.userEdited;
                applyCurrencyRate(true);
            });
            rateEl.addEventListener('input', function () {
                rateEl.dataset.userEdited = '1';
                if (typeof updateSummaryPanel === 'function') {
                    updateSummaryPanel();
                }
            });
        });
    })();
    const carats = <?php echo json_encode(isset($carats) && is_array($carats) ? $carats : []); ?>;
    window.carats = carats;
    const locations = <?php echo json_encode(isset($locations) && is_array($locations) ? $locations : []); ?>;
    const categories = <?php echo json_encode(isset($categories) && is_array($categories) ? $categories : []); ?>;
    const metals = <?php echo json_encode(isset($metals) && is_array($metals) ? $metals : []); ?>;
    window.metals = metals;
    window.AURAGOLD_WORKING_BRANCH_ID = <?php echo (int) (isset($auragold_working_branch_id) ? $auragold_working_branch_id : 0); ?>;
    window.voucherSettingsByMetal = <?php echo json_encode(isset($voucher_settings_by_metal) && is_array($voucher_settings_by_metal) ? $voucher_settings_by_metal : []); ?>;
    const nationalities = <?php
        $nationalities_js = getList("SELECT id, name FROM tbl_nationalities WHERE status = 1 ORDER BY name ASC");
        echo json_encode(is_array($nationalities_js ?? null) ? $nationalities_js : []);
    ?>;
    
    // Edit mode: skip deferred loadCustomerBalance so it doesn't overwrite saved Grand Total / Paid / Balance
    window.isPurchaseInvoiceEditMode = <?php echo (!empty($edit_order_id) && $edit_order_id > 0) ? 'true' : 'false'; ?>;
    window.isPurchaseInvoicePage = true;
    window.piSaveBlockedBySaleFixing = <?php echo !empty($pi_save_blocked_by_sale_fixing) ? 'true' : 'false'; ?>;
</script>
<script>
window.PB_PAGE_CONFIG = {
    partyNameSelector: '#customerName',
    partyIdSelector: '#customerId',
    balanceType: 'supplier',
    ledgerClBalance: false,
    purchaseLedgerPrevBalance: true,
    onBeforeLoad: function () {
        var el = document.getElementById('previousBalancePanelLoader');
        if (el) {
            el.classList.add('pb-is-loading');
            el.setAttribute('aria-hidden', 'false');
        }
    },
    onAfterLoad: function () { if (typeof updateSummaryPanel === 'function') updateSummaryPanel(); },
    onAfterLoadAlways: function () {
        var el = document.getElementById('previousBalancePanelLoader');
        if (el) {
            el.classList.remove('pb-is-loading');
            el.setAttribute('aria-hidden', 'true');
        }
    },
    onAfterClear: function () { if (typeof updateSummaryPanel === 'function') updateSummaryPanel(); },
    skipIfEditMode: true,
    skipAutoLoad: function () { return !!window.isPurchaseInvoiceEditMode; }
};
</script>
<script src="js/previous-balance-common.js"></script>
<?php auragold_echo_party_select2_init(); ?>
<script>
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
            'exchange_rate' => $edit_order['exchange_rate'] ?? ($edit_order['currency_rate'] ?? ''),
            'currency_rate' => $edit_order['exchange_rate'] ?? ($edit_order['currency_rate'] ?? ''),
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
            'show_in_stock_journal' => (int)($edit_order['show_in_stock_journal'] ?? 0),
            'diamond_issues' => [],
            'stone_issues' => [],
        ];
        require_once __DIR__ . '/includes/auragold_voucher_diamond_stock.php';
        require_once __DIR__ . '/includes/auragold_voucher_stone_stock.php';
        $embed_order['diamond_issues'] = auragold_voucher_list_diamond_issue_rows_for_kind($conn, 'purchase_invoice', (int) ($edit_order['id'] ?? 0));
        $embed_order['stone_issues'] = auragold_voucher_list_stone_issue_rows_for_kind($conn, 'purchase_invoice', (int) ($edit_order['id'] ?? 0));
        $embed_items = is_array($edit_items) ? $edit_items : [];
        $embed_payments = is_array($edit_payments) ? $edit_payments : [];
        // Convert saved image paths (images / group_image columns) to full URLs for UI
        $base_url = (isset($SiteUrl) ? rtrim((string) $SiteUrl, '/') . '/' : '');
        foreach ($embed_items as &$emb_it) {
            $img_json = !empty($emb_it['images']) ? $emb_it['images'] : (!empty($emb_it['group_image']) ? $emb_it['group_image'] : '');
            if ($img_json === '') {
                continue;
            }
            $dec = @json_decode($img_json, true);
            if (!$dec || empty($dec['images']) || !is_array($dec['images'])) {
                continue;
            }
            $to_url = function ($p) use ($base_url) {
                $p = trim((string) $p);
                if ($p === '') {
                    return '';
                }
                if (preg_match('#^https?://#i', $p)) {
                    return $p;
                }
                return $base_url . auragold_uploads_public_rel(ltrim($p, '/'));
            };
            $urls = array_values(array_filter(array_map($to_url, $dec['images'])));
            if (empty($urls)) {
                continue;
            }
            $primary = isset($dec['primary']) ? $dec['primary'] : $dec['images'][0];
            $primary_url = $to_url($primary);
            if ($primary_url === '') {
                $primary_url = $urls[0];
            }
            $emb_it['group_image'] = json_encode(['primary' => $primary_url, 'images' => $urls]);
        }
        unset($emb_it);
    }
    $edit_data_obj = $embed_order !== null ? ['order' => $embed_order, 'items' => $embed_items, 'payments' => $embed_payments] : null;
    $json_flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
    $edit_data_json = $edit_data_obj !== null ? json_encode($edit_data_obj, $json_flags) : 'null';
    if ($edit_data_json === false) { $edit_data_json = 'null'; }
    echo "window.EDIT_ORDER_DATA = " . $edit_data_json . ";\n";
    ?>
    // Product search modal from product-search-modal-common.js
    var openProductSearchModal = window.openProductSearchModal;

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
    const PRODUCT_MODAL_COLUMNS_PAGE = 'purchase-invoice-product-modal';
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
        { dataColumn: 'sale-amount', label: 'Purchase Amt' },
        { dataColumn: 'sale-amount-with', label: 'Purchase Amt With Tax' },
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
        { dataColumn: 'discount-amount', label: 'Disc. base (for %)' },
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
    
    function normalizeProductListBarcodeKeyPi(s) {
        return String(s || '').trim().toLowerCase();
    }
    /** Duplicate when same purchase line, or same barcode+characteristic. Diamond only: match characteristic before barcode sync. */
    function isProductLineInProductListPi(barcode, characteristicId, purchaseInvoiceItemId, productMetalId) {
        var bKey = normalizeProductListBarcodeKeyPi(barcode);
        var cid = characteristicId != null && characteristicId !== '' ? String(characteristicId).trim() : '';
        var pii = purchaseInvoiceItemId != null && purchaseInvoiceItemId !== '' ? String(purchaseInvoiceItemId).trim() : '';
        var tbody = document.getElementById('productListBody');
        if (!tbody) return false;
        var rows = tbody.querySelectorAll('tr.product-row');
        if (pii) {
            for (var p = 0; p < rows.length; p++) {
                if (String(rows[p].getAttribute('data-purchase-invoice-item-id') || '').trim() === pii) return true;
            }
            return false;
        }
        if (cid && typeof auragoldProductMetalIsDiamondStones === 'function' && auragoldProductMetalIsDiamondStones(productMetalId)) {
            for (var a = 0; a < rows.length; a++) {
                if (String(rows[a].getAttribute('data-characteristic-id') || '').trim() === cid) return true;
            }
        }
        if (!bKey) return false;
        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            var inp = row.querySelector('[data-column="barcode"] input');
            var fromInput = inp ? String(inp.value || '').trim() : '';
            var fromAttr = String(row.getAttribute('data-barcode') || '').trim();
            var v = fromInput || fromAttr;
            if (normalizeProductListBarcodeKeyPi(v) !== bKey) continue;
            var rowCid = String(row.getAttribute('data-characteristic-id') || '').trim();
            if (cid === rowCid) return true;
        }
        return false;
    }
    function parseBarcodeAjaxJsonPi(response) {
        return response.text().then(function(text) {
            var t = (text || '').trim();
            if (!t) {
                throw new Error('Empty server response. Check PHP error log and ajax/get-product-by-barcode.php.');
            }
            try {
                return JSON.parse(t);
            } catch (e) {
                throw new Error('Server did not return valid JSON.');
            }
        });
    }
    
    /** True when a modal product row has no product/barcode yet (placeholder row). */
    function isProductListRowEmpty(row) {
        if (!row || !row.classList || !row.classList.contains('product-row')) {
            return false;
        }
        if (String(row.getAttribute('data-product-id') || '').trim() !== '') {
            return false;
        }
        if (String(row.getAttribute('data-barcode') || '').trim() !== '') {
            return false;
        }
        var inp = row.querySelector('[data-column="barcode"] input');
        if (inp && String(inp.value || '').trim() !== '') {
            return false;
        }
        return true;
    }

    /** Reuse the blank row created when the modal opens (or Add Product). */
    function getFirstEmptyProductListRow() {
        var tbody = document.getElementById('productListBody');
        if (!tbody) {
            return null;
        }
        var rows = tbody.querySelectorAll('tr.product-row');
        for (var i = 0; i < rows.length; i++) {
            if (isProductListRowEmpty(rows[i])) {
                return rows[i];
            }
        }
        return null;
    }

    // Add product to Product Selection table (productListBody) by barcode
    // Reuse an existing empty product-row when present so scan does not leave a blank first line.
    function addProductToProductSelectionTable(product) {
        const tbody = document.getElementById('productListBody');
        if (!tbody) {
            console.error('productListBody not found');
            return;
        }
        const emptyRow = tbody.querySelector('tr:not(.product-row)');
        if (emptyRow) emptyRow.remove();
        var row = getFirstEmptyProductListRow();
        if (!row) {
            if (typeof addEmptyProductRow !== 'function') {
                console.error('addEmptyProductRow not found');
                return;
            }
            addEmptyProductRow();
            row = tbody.querySelector('.product-row:last-of-type');
        }
        if (!row) return;
        if (typeof populateRowWithProduct === 'function') populateRowWithProduct(row, product, { fromBarcode: true });
        if (typeof updateJewelleryDiamondCaratFromDiamondAndGemstone === 'function') updateJewelleryDiamondCaratFromDiamondAndGemstone();
        if (typeof syncDiamondTabSharedBarcodes === 'function') syncDiamondTabSharedBarcodes();
    }
    
    var modalProductBarcodeFetchInFlight = false;
    var modalProductBarcodeLastFetchDoneBarcode = '';
    var modalProductBarcodeLastFetchDoneTime = 0;
    var MODAL_BARCODE_BLUR_DEDUPE_MS = 2500;
    function shouldSuppressModalProductBarcodeCheck(barcode, fromBlur) {
        if (modalProductBarcodeFetchInFlight) {
            return true;
        }
        if (fromBlur && barcode && barcode === modalProductBarcodeLastFetchDoneBarcode) {
            if (Date.now() - modalProductBarcodeLastFetchDoneTime < MODAL_BARCODE_BLUR_DEDUPE_MS) {
                return true;
            }
        }
        return false;
    }
    
    // Fetch product by barcode and add to Product Selection table (expand_invoice_siblings = all lines on same purchase tag)
    function fetchProductByBarcodeAndAdd(barcode) {
        if (!barcode || barcode.trim() === '') {
            return;
        }
        var trimmed = barcode.trim();
        if (modalProductBarcodeFetchInFlight) {
            return;
        }
        modalProductBarcodeFetchInFlight = true;
        const barcodeInput = document.getElementById('modalProductBarcode');
        if (barcodeInput) {
            barcodeInput.style.borderColor = '#c5a864';
        }
        fetch('ajax/get-product-by-barcode.php?' + (typeof auragoldBuildProductByBarcodeQuery === 'function' ? auragoldBuildProductByBarcodeQuery(trimmed, {expand_invoice_siblings: 1, include_purchase_invoice_sibling_barcodes: 1, allow_non_stock_barcode: 1}) : ('barcode=' + encodeURIComponent(trimmed) + '&expand_invoice_siblings=1&include_purchase_invoice_sibling_barcodes=1&allow_non_stock_barcode=1')))
            .then(parseBarcodeAjaxJsonPi)
            .then(function(data) {
                if (typeof auragoldApplyProductFetchResolvedToInput === 'function') {
                    auragoldApplyProductFetchResolvedToInput(barcodeInput, data, trimmed);
                } else if (data.resolved_barcode && barcodeInput) {
                    barcodeInput.value = String(data.resolved_barcode).trim();
                }
                if (data.success && data.products && data.products.length) {
                    var firstPrPi = data.products[0];
                    if (firstPrPi && firstPrPi.metal_id != null && firstPrPi.metal_id !== '' && typeof switchToMetalTab === 'function') {
                        switchToMetalTab(String(firstPrPi.metal_id));
                    }
                    var lastMid = '';
                    for (var pi = 0; pi < data.products.length; pi++) {
                        var pr = data.products[pi];
                        var rbc = String(pr.barcode || trimmed).trim();
                        var rcid = pr.characteristic_id != null && pr.characteristic_id !== '' ? pr.characteristic_id : '';
                        if (isProductLineInProductListPi(rbc, rcid, pr.purchase_invoice_item_id, pr.metal_id)) continue;
                        addProductToProductSelectionTable(pr);
                        if (pr.metal_id != null && pr.metal_id !== '') lastMid = String(pr.metal_id);
                    }
                    if (lastMid && typeof switchToMetalTab === 'function') switchToMetalTab(lastMid);
                    if (typeof auragoldClearModalProductBarcodeInput === 'function') {
                        auragoldClearModalProductBarcodeInput(barcodeInput);
                    } else if (barcodeInput) {
                        barcodeInput.value = '';
                        barcodeInput.style.borderColor = '';
                        barcodeInput.focus();
                    }
                    return;
                }
                if (data.success && data.product) {
                    var resolvedBc = String(data.product.barcode || trimmed).trim();
                    var resCid = data.product.characteristic_id != null && data.product.characteristic_id !== '' ? data.product.characteristic_id : '';
                    if (isProductLineInProductListPi(resolvedBc, resCid, data.product.purchase_invoice_item_id, data.product.metal_id)) {
                        alert('This barcode is already added in the list.');
                        if (barcodeInput) {
                            barcodeInput.style.borderColor = '#ef4444';
                            setTimeout(function() { barcodeInput.style.borderColor = ''; }, 2000);
                        }
                        return;
                    }
                    addProductToProductSelectionTable(data.product);
                    var mid = data.product.metal_id != null && data.product.metal_id !== '' ? String(data.product.metal_id) : '';
                    if (mid && typeof switchToMetalTab === 'function') switchToMetalTab(mid);
                    function addSiblingSequentialPi(idx, list) {
                        if (!list || idx >= list.length) {
                            if (typeof auragoldClearModalProductBarcodeInput === 'function') {
                                auragoldClearModalProductBarcodeInput(barcodeInput);
                            } else if (barcodeInput) {
                                barcodeInput.value = '';
                                barcodeInput.style.borderColor = '';
                                barcodeInput.focus();
                            }
                            return;
                        }
                        var sb = String(list[idx] || '').trim();
                        if (!sb) {
                            addSiblingSequentialPi(idx + 1, list);
                            return;
                        }
                        fetch('ajax/get-product-by-barcode.php?' + (typeof auragoldBuildProductByBarcodeQuery === 'function' ? auragoldBuildProductByBarcodeQuery(sb, {expand_invoice_siblings: 1, include_purchase_invoice_sibling_barcodes: 1, allow_non_stock_barcode: 1}) : ('barcode=' + encodeURIComponent(sb) + '&expand_invoice_siblings=1&include_purchase_invoice_sibling_barcodes=1&allow_non_stock_barcode=1')))
                            .then(parseBarcodeAjaxJsonPi)
                            .then(function(d2) {
                                if (d2.success && d2.products && d2.products.length) {
                                    for (var j = 0; j < d2.products.length; j++) {
                                        var p2 = d2.products[j];
                                        var b2 = String(p2.barcode || sb).trim();
                                        var c2 = p2.characteristic_id != null && p2.characteristic_id !== '' ? p2.characteristic_id : '';
                                        if (!isProductLineInProductListPi(b2, c2, p2.purchase_invoice_item_id, p2.metal_id)) {
                                            addProductToProductSelectionTable(p2);
                                            if (p2.metal_id != null && p2.metal_id !== '' && typeof switchToMetalTab === 'function') {
                                                switchToMetalTab(String(p2.metal_id));
                                            }
                                        }
                                    }
                                } else if (d2.success && d2.product) {
                                    var b2 = String(d2.product.barcode || sb).trim();
                                    var c2 = d2.product.characteristic_id != null && d2.product.characteristic_id !== '' ? d2.product.characteristic_id : '';
                                    if (!isProductLineInProductListPi(b2, c2, d2.product.purchase_invoice_item_id, d2.product.metal_id)) {
                                        addProductToProductSelectionTable(d2.product);
                                        if (d2.product.metal_id != null && d2.product.metal_id !== '' && typeof switchToMetalTab === 'function') {
                                            switchToMetalTab(String(d2.product.metal_id));
                                        }
                                    }
                                }
                                addSiblingSequentialPi(idx + 1, list);
                            })
                            .catch(function() { addSiblingSequentialPi(idx + 1, list); });
                    }
                    if (data.sibling_barcodes && data.sibling_barcodes.length) {
                        addSiblingSequentialPi(0, data.sibling_barcodes);
                    } else {
                        if (barcodeInput) {
                            barcodeInput.value = '';
                            barcodeInput.style.borderColor = '';
                            barcodeInput.focus();
                        }
                    }
                } else {
                    alert(data.message || 'Product with barcode "' + barcode + '" not found');
                    if (barcodeInput) {
                        barcodeInput.style.borderColor = '#ef4444';
                        setTimeout(function() { barcodeInput.style.borderColor = ''; }, 2000);
                    }
                }
            })
            .catch(function(error) {
                console.error('Error fetching product by barcode:', error);
                alert('Error fetching product by barcode: ' + (error && error.message ? error.message : error));
                if (barcodeInput) {
                    barcodeInput.style.borderColor = '#ef4444';
                    setTimeout(function() { barcodeInput.style.borderColor = ''; }, 2000);
                }
            })
            .finally(function() {
                modalProductBarcodeFetchInFlight = false;
                modalProductBarcodeLastFetchDoneBarcode = trimmed;
                modalProductBarcodeLastFetchDoneTime = Date.now();
                if (typeof auragoldModalBarcodeBatchOnFetchComplete === 'function') {
                    auragoldModalBarcodeBatchOnFetchComplete();
                }
            });
    }

    if (typeof auragoldWrapFetchProductByBarcodeWithResolve === 'function' && typeof fetchProductByBarcodeAndAdd === 'function' && !fetchProductByBarcodeAndAdd.__auragoldResolvedWrap) {
        fetchProductByBarcodeAndAdd = auragoldWrapFetchProductByBarcodeWithResolve(fetchProductByBarcodeAndAdd);
        fetchProductByBarcodeAndAdd.__auragoldResolvedWrap = true;
    }
    
    let modalProductBarcodeCheckTimer = null;
    var modalProductBarcodeLastCheck = '';
    var modalProductBarcodeLastCheckTime = 0;
    function triggerModalProductBarcodeCheck(input, fromBlur) {
        fromBlur = !!fromBlur;
        var $input = $(input);
        var raw = $input.val().trim();
        if (!raw) {
            modalProductBarcodeLastCheck = '';
            return;
        }
        var barcodes = typeof auragoldParseModalBarcodeTokens === 'function'
            ? auragoldParseModalBarcodeTokens(raw)
            : raw.split(/\s+/).filter(function (s) { return s.length > 0; });
        if (shouldSuppressModalProductBarcodeCheck(barcodes.length > 1 ? raw : (barcodes[0] || raw), fromBlur)) {
            return;
        }
        var t = Date.now();
        if (raw === modalProductBarcodeLastCheck && (t - modalProductBarcodeLastCheckTime) < 250) {
            return;
        }
        modalProductBarcodeLastCheck = raw;
        modalProductBarcodeLastCheckTime = t;
        if (barcodes.length > 1 && typeof auragoldStartModalBarcodeBatch === 'function') {
            auragoldStartModalBarcodeBatch(barcodes, input, fetchProductByBarcodeAndAdd);
            return;
        }
        fetchProductByBarcodeAndAdd(barcodes[0] || raw);
    }
    
    // Handle Add Product Icon Click
    $(document).ready(function() {
        // Barcode: check only on Enter, Tab, or blur — not on each keystroke (scanners still send Enter at end)
        $(document).on('keydown', '#modalProductBarcode', function(e) {
            if (e.key === 'Enter') {
                var barcode = $(this).val().trim();
                if (barcode) {
                    e.preventDefault();
                    if (modalProductBarcodeCheckTimer) {
                        clearTimeout(modalProductBarcodeCheckTimer);
                        modalProductBarcodeCheckTimer = null;
                    }
                    triggerModalProductBarcodeCheck(this, false);
                }
                return;
            }
            if (e.key === 'Tab' && !e.shiftKey) {
                var btab = $(this).val().trim();
                if (btab) {
                    if (modalProductBarcodeCheckTimer) {
                        clearTimeout(modalProductBarcodeCheckTimer);
                        modalProductBarcodeCheckTimer = null;
                    }
                    triggerModalProductBarcodeCheck(this, false);
                }
            }
        });
        
        $(document).on('input', '#modalProductBarcode', function() {
            modalProductBarcodeLastFetchDoneBarcode = '';
            modalProductBarcodeLastFetchDoneTime = 0;
        });
        
        $(document).on('blur', '#modalProductBarcode', function() {
            if (modalProductBarcodeCheckTimer) {
                clearTimeout(modalProductBarcodeCheckTimer);
            }
            var self = this;
            modalProductBarcodeCheckTimer = setTimeout(function() {
                modalProductBarcodeCheckTimer = null;
                triggerModalProductBarcodeCheck(self, true);
            }, 150);
        });
        
        $('#productCreationModal').on('hidden.bs.modal', function() {
            if (modalProductBarcodeCheckTimer) {
                clearTimeout(modalProductBarcodeCheckTimer);
                modalProductBarcodeCheckTimer = null;
            }
            modalProductBarcodeLastCheck = '';
            modalProductBarcodeLastFetchDoneBarcode = '';
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
                var typedName = '';
                if (typeof window.getAuragoldPartySearchTerm === 'function') {
                    typedName = String(window.getAuragoldPartySearchTerm() || '').trim();
                }
                if (!typedName) {
                    typedName = String($('#customerName').val() || '').trim();
                }
                if (!typedName && String($('#customerId').val() || '') === '0') {
                    var $opt = $('#customerId option:selected');
                    typedName = $opt.length ? String($opt.text() || '').trim() : '';
                }
                openCustomerModalForAdd(typedName);
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
                var cbsClr = document.getElementById('customerBillingState');
                if (cbsClr) cbsClr.value = '';
                window.customerState = '';
                if (typeof window.updateSaleInvoiceAddItemButtonState === 'function') window.updateSaleInvoiceAddItemButtonState();
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
                                var _bs = (customer.billing_state != null ? String(customer.billing_state) : '').replace(/"/g, '&quot;');
                                html += `
                                    <div class="customer-suggestion-item" 
                                         data-customer-id="${customer.id}" 
                                         data-customer-name="${customer.name}"
                                         data-billing-state="${_bs}"
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
            const billingState = $(this).attr('data-billing-state') || '';
            
            $('#customerName').val(customerName);
            $('#customerId').val(customerId);
            selectedCustomerId = customerId;
            var cbs = document.getElementById('customerBillingState');
            if (cbs) cbs.value = billingState;
            window.customerState = billingState || '';
            $('#customerSuggestions').hide();
            if (typeof window.updateSaleInvoiceAddItemButtonState === 'function') {
                window.updateSaleInvoiceAddItemButtonState();
            }
            
            // Load customer balance when customer is selected (with small delay to ensure DOM is updated)
            setTimeout(function() {
                if (typeof loadCustomerBalance === 'function') {
                    loadCustomerBalance();
                }
                if (typeof window.auragoldSaleInvoiceRefreshGstForAllRows === 'function') {
                    window.auragoldSaleInvoiceRefreshGstForAllRows();
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
                    url: 'ajax/search-purchase-invoices.php',
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
                            saleInvoiceSuggestions.html('<div style="padding: 0.75rem; color: #94a3b8; text-align: center;">No purchase invoices found</div>').show();
                        }
                    },
                    error: function() {
                        saleInvoiceSuggestions.hide();
                    }
                });
            }, 300);
        });
        
        // Handle invoice selection from suggestions → open in edit mode (?id=)
        $(document).on('click', '#saleInvoiceSuggestions .invoice-suggestion-item', function() {
            const invoiceId = parseInt($(this).data('invoice-id'), 10) || 0;
            saleInvoiceSearchInput.val('');
            saleInvoiceSuggestions.hide();
            if (invoiceId > 0) {
                window.location.href = 'purchase-invoice.php?id=' + invoiceId;
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
                    
                    initNewLedgerModalDefaults();
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
                            const metalIdRow = row.getAttribute('data-metal-id') || '';
                            const url = typeof window.auragoldGetProductDetailsUrl === 'function'
                                ? window.auragoldGetProductDetailsUrl(productId, characteristicId, metalIdRow)
                                : ('ajax/get-product-details.php?product_id=' + encodeURIComponent(productId) + (characteristicId ? '&characteristic_id=' + encodeURIComponent(characteristicId) : '') + (metalIdRow ? '&metal_id=' + encodeURIComponent(metalIdRow) : ''));
                            if (url) {
                            fetch(url)
                                .then(response => response.json())
                                .then(data => {
                                    if (!data || !data.success) {
                                        console.error('API Error:', data && data.message);
                                        return;
                                    }
                                    if (data.product) {
                                        const descCell = row.querySelector('[data-column="product"] a');
                                        if (descCell) {
                                            descCell.textContent = data.product.name || '';
                                        } else {
                                            const productInp = row.querySelector('[data-column="product"] input');
                                            if (productInp) productInp.value = data.product.name || '';
                                        }
                                        const taxPercentInput = row.querySelector('[data-column="tax-percent"] input');
                                        row.setAttribute('data-gst-local-pct', (data.product.gst_local_percent != null && data.product.gst_local_percent !== '') ? String(data.product.gst_local_percent) : '');
                                        row.setAttribute('data-gst-interstate-pct', (data.product.gst_interstate_percent != null && data.product.gst_interstate_percent !== '') ? String(data.product.gst_interstate_percent) : '');
                                        row.setAttribute('data-gst-invoice-slab-pct', typeof window.auragoldGstInvoiceSlabFromProductPayload === 'function' ? window.auragoldGstInvoiceSlabFromProductPayload(data.product) : '');
                                        if (data.product.gst_tax_breakdown && typeof window.auragoldGstLineTaxesFromProductPayload === 'function') {
                                            var ltJson2 = window.auragoldGstLineTaxesFromProductPayload(data.product);
                                            if (ltJson2) row.setAttribute('data-gst-line-taxes', ltJson2);
                                            else row.removeAttribute('data-gst-line-taxes');
                                        } else {
                                            row.removeAttribute('data-gst-line-taxes');
                                        }
                                        if (typeof window.auragoldGstSetProductTaxesAttrOnRow === 'function') {
                                            window.auragoldGstSetProductTaxesAttrOnRow(row, data.product);
                                        }
                                        if (taxPercentInput && typeof window.setSaleInvoiceGstTaxPercentDisplay === 'function') {
                                            window.setSaleInvoiceGstTaxPercentDisplay(row, taxPercentInput);
                                        }
                                        if (typeof window.calculateModalRowNetWeight === 'function') {
                                            window.calculateModalRowNetWeight(row);
                                        }
                                    }
                                });
                            }
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

    /** Select Customer Type by option label (matches tbl_customer_types.name, case-insensitive). */
    function applyDefaultLedgerCustomerType(typeName) {
        const sel = document.getElementById('customerType');
        if (!sel || !typeName) return;
        const want = String(typeName).trim().toLowerCase();
        for (let i = 0; i < sel.options.length; i++) {
            const opt = sel.options[i];
            if (!opt.value) continue;
            if (opt.text.trim().toLowerCase() === want) {
                sel.selectedIndex = i;
                return;
            }
        }
    }

    function initNewLedgerModalDefaults() {
        clearCustomerForm();
        setCustomerModalMode('add');
        applyDefaultLedgerCustomerType('supplier');
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

    function openCustomerModalForAdd(prefillName) {
        initNewLedgerModalDefaults();
        $('#customerCreationModal').modal('show');
        var term = String(prefillName || '').trim();
        if (!term) return;
        setTimeout(function () {
            var ledgerNameField = document.getElementById('ledgerName');
            if (!ledgerNameField) return;
            ledgerNameField.value = term;
            if (typeof handleNameInput === 'function') {
                handleNameInput(ledgerNameField);
            }
            ledgerNameField.focus();
        }, 300);
    }
    window.openCustomerModalForAdd = openCustomerModalForAdd;

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
        setVal('ledgerGstin', c.gstin || '');
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
        try {
            if (typeof window.fillCustomerShareHolderDocumentsFromCustomer === 'function') {
                window.fillCustomerShareHolderDocumentsFromCustomer(c);
            }
        } catch (eDocs) {
            console.error('Share holder documents prefill failed', eDocs);
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
                
                if (typeof window.auragoldApplySavedCustomerToPartyDropdown === 'function') {
                    window.auragoldApplySavedCustomerToPartyDropdown(data);
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
                tr.innerHTML = '<td colspan="73" class="text-center text-muted py-4">No products found for this category</td>';
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
                if (typeof syncProductModalExcelSampleLink === 'function') {
                    syncProductModalExcelSampleLink();
                }
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
                if (typeof syncModalGroupSingleItemCheckboxForActiveTab === 'function') {
                    syncModalGroupSingleItemCheckboxForActiveTab();
                } else if (typeof auragoldApplyManualAmountFieldsEditability === 'function') {
                    auragoldApplyManualAmountFieldsEditability();
                }
            });
        }
        
        const firstTab = modal.querySelector('.category-tab-btn.active');
        if (firstTab) {
            currentMetalId = firstTab.getAttribute('data-metal-id');
            currentMetalName = firstTab.getAttribute('data-metal-name');
            if (typeof syncProductModalExcelSampleLink === 'function') {
                syncProductModalExcelSampleLink();
            }
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
                tbody.innerHTML = '<tr class="no-category-products-placeholder"><td colspan="73" class="text-center text-muted py-4">Click "Add Product" or select a category tab to add products</td></tr>';
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
            if (typeof syncProductModalExcelSampleLink === 'function') {
                syncProductModalExcelSampleLink();
            }
            if (typeof syncModalGroupSingleItemCheckboxForActiveTab === 'function') {
                syncModalGroupSingleItemCheckboxForActiveTab();
            } else if (typeof auragoldApplyManualAmountFieldsEditability === 'function') {
                auragoldApplyManualAmountFieldsEditability();
            }
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
            if (typeof syncProductModalExcelSampleLink === 'function') {
                syncProductModalExcelSampleLink();
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
            
            // Clear modal product list, then add one empty row by default for new Add Item
            // (skip when editing an existing invoice row — callers populate after open)
            const tbody = document.getElementById('productListBody');
            if (tbody) {
                tbody.innerHTML = '';
            }
            var groupNameInput = document.getElementById('modalGroupName');
            if (groupNameInput) groupNameInput.value = '';
            var commentInput = document.getElementById('modalComment');
            if (commentInput) commentInput.value = '';

            var isEditingExisting = (typeof currentEditingRowId !== 'undefined' && currentEditingRowId);
            if (!isEditingExisting) {
                try {
                    if (typeof addEmptyProductRow === 'function') {
                        addEmptyProductRow();
                    } else if (typeof window.addEmptyProductRow === 'function') {
                        window.addEmptyProductRow();
                    }
                } catch (rowErr) {
                    console.error('Failed to add default product row on modal open:', rowErr);
                    if (tbody) {
                        tbody.innerHTML = '<tr><td colspan="73" class="text-center text-muted py-4">Click "Add Product" button to add products for billing...</td></tr>';
                    }
                }
            } else if (tbody) {
                tbody.innerHTML = '<tr><td colspan="73" class="text-center text-muted py-4">Click "Add Product" button to add products for billing...</td></tr>';
            }
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
        
        // Full column set must match #productListTable thead in common-modal-product-selection.php
        // (Platinum, certificate, spec, etc.); a short row + reorderModalRowCellsToMatchHeader left gaps/blank cells.
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
            <td data-column="item-code"><input type="text" class="form-control form-control-sm" value="" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="category"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="">Select</option></select></td>
            <td data-column="product-category"><select class="form-control form-control-sm product-category-select" style="width: 120px; font-size: 0.7rem;"><option value="">Select Category</option></select></td>
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
            <td data-column="fc-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 90px; font-size: 0.7rem;"></td>
            <td data-column="diamond-line-metal-value"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="rapnet-valuation"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="setting-charge"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 110px; font-size: 0.7rem;"></td>
            <td data-column="stone-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
            <td data-column="mark-up-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="mark-up-per"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
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
            <td data-column="final-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="alloy-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="platinum-weight"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="platinum-karat"><input type="text" class="form-control form-control-sm" value="" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="platinum-purity"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="platinum-purity-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 90px; font-size: 0.7rem;"></td>
            <td data-column="platinum-rate"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 90px; font-size: 0.7rem;"></td>
            <td data-column="platinum-wastage-per"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="platinum-wastage-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="platinum-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="discount-type"><select class="form-control form-control-sm" style="width: 150px; font-size: 0.7rem;"><option value="Fix" selected>Fix</option><option value="On Amount">On Amount</option><option value="On Making Amount">On Making Amount</option><option value="On Diamond Amount">On Diamond Amount</option><option value="On Stone Amount">On Stone Amount</option><option value="On Net Amount">On Net Amount</option></select></td>
            <td data-column="discount-per"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="discount-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="discount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
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
            <td data-column="stone-cost"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="diamond-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 120px; font-size: 0.7rem;"></td>
            <td data-column="purchase-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 130px; font-size: 0.7rem;"></td>
                        <td data-column="sale-percent"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;" placeholder="%" title="Sale % on Purchase Amount"></td>            <td data-column="sale-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 110px; font-size: 0.7rem;"></td>
            <td data-column="sale-amount-with"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 130px; font-size: 0.7rem;"></td>
            <td data-column="net-amt"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="tax-type"><select class="form-control form-control-sm" style="width: 120px; font-size: 0.7rem;"><option value="tax_on_making">Tax on making</option><option value="tax_of_netamount" selected>Tax of net amount</option><option value="no_tax">No tax</option></select></td>
            <td data-column="tax-percent"><input type="text" class="form-control form-control-sm" value="0" min="0" max="100" step="0.01" readonly style="width: 70px; font-size: 0.7rem; background: #f1f5f9; cursor: not-allowed;" title="From product opening (read-only)"></td>
            <td data-column="tax"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="other-charge-type"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="Fix" selected>Fix</option><option value="Percentage">Percentage</option></select></td>
            <td data-column="other-weight"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 110px; font-size: 0.7rem;"></td>
            <td data-column="other-rate"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="other-info"><input type="text" class="form-control form-control-sm" value="" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="other-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
            <td data-column="certificate-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 110px; font-size: 0.7rem;"></td>
            <td data-column="certificate-no"><input type="text" class="form-control form-control-sm" value="" style="width: 110px; font-size: 0.7rem;"></td>
            <td data-column="certificate-link"><input type="text" class="form-control form-control-sm" value="" style="width: 120px; font-size: 0.7rem;" placeholder="https://"></td>
            <td data-column="video-link"><input type="text" class="form-control form-control-sm" value="" style="width: 120px; font-size: 0.7rem;" placeholder="https://"></td>
            <td data-column="cut"><select class="form-control form-control-sm auragold-spec-select" data-auragold-spec="cut" style="min-width: 100px; font-size: 0.7rem;"><option value="">Select Cut</option></select></td>
            <td data-column="color"><select class="form-control form-control-sm auragold-spec-select" data-auragold-spec="color" style="min-width: 100px; font-size: 0.7rem;"><option value="">Select Color</option></select></td>
            <td data-column="seive-size"><select class="form-control form-control-sm auragold-spec-select" data-auragold-spec="seive" style="min-width: 100px; font-size: 0.7rem;"><option value="">Select Seive</option></select></td>
            <td data-column="size"><select class="form-control form-control-sm auragold-spec-select" data-auragold-spec="size" style="min-width: 100px; font-size: 0.7rem;"><option value="">Select Size</option></select></td>
            <td data-column="shape"><select class="form-control form-control-sm auragold-spec-select" data-auragold-spec="shape" style="min-width: 100px; font-size: 0.7rem;"><option value="">Select Shape</option></select></td>
            <td data-column="clarity"><select class="form-control form-control-sm auragold-spec-select" data-auragold-spec="clarity" style="min-width: 100px; font-size: 0.7rem;"><option value="">Select Clarity</option></select></td>
            <td data-column="unit-price"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 90px; font-size: 0.7rem;"></td>
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
            if (typeof populateCaratSelectForModalRow === 'function') populateCaratSelectForModalRow(caratSelect, row);
            else populateSelect(caratSelect, carats, 'id', 'name', 'Select Karat');
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
        const productCategorySelect = row.querySelector('.product-category-select');
        if (productCategorySelect && typeof populateSelect === 'function' && typeof categories !== 'undefined') {
            populateSelect(productCategorySelect, categories, 'id', 'name', 'Select Category');
        }
        if (typeof auragoldPopulateModalSpecSelectsForRow === 'function') {
            auragoldPopulateModalSpecSelectsForRow(row);
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
        
        // Discount type: voucher default (Fix) before first calculation
        if (typeof getVoucherDefaultDiscountTypeForModalRow === 'function') {
            var defDisc = getVoucherDefaultDiscountTypeForModalRow(row);
            setModalSelectIfOptionExists(row, 'discount-type', defDisc);
        }
        
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
    window.addEmptyProductRow = addEmptyProductRow;
    
    /** Set <select> by option value, or by matching option text (for category/location stored as name in stock journal). */
    function selectOptionByValueOrText(select, raw) {
        if (!select || raw == null || raw === '') return;
        const s = String(raw).trim();
        if (!s) return;
        for (let i = 0; i < select.options.length; i++) {
            if (select.options[i].value === s) {
                select.selectedIndex = i;
                return;
            }
        }
        for (let i = 0; i < select.options.length; i++) {
            const t = (select.options[i].textContent || '').trim();
            if (t === s || t.replace(/\s+/g, ' ') === s) {
                select.selectedIndex = i;
                return;
            }
        }
        const sLower = s.toLowerCase();
        for (let i = 0; i < select.options.length; i++) {
            if (String(select.options[i].value || '').toLowerCase() === sLower) {
                select.selectedIndex = i;
                return;
            }
        }
        for (let i = 0; i < select.options.length; i++) {
            const t = (select.options[i].textContent || '').trim();
            if (t.toLowerCase() === sLower) {
                select.selectedIndex = i;
                return;
            }
        }
    }
    
    /** Match karat from tbl_stock_journal (numeric 22, 24, etc.) to carat dropdown (id / "22K" label). */
    function selectCaratFromStockJournal(caratSelect, karatVal) {
        if (!caratSelect || karatVal == null || karatVal === '') return;
        const raw = String(karatVal).trim();
        if (!raw) return;
        if ([...caratSelect.options].some(function(o) { return o.value === raw; })) {
            caratSelect.value = raw;
            return;
        }
        const num = parseFloat(raw.replace(/[^0-9.]/g, ''));
        if (isNaN(num)) return;
        for (let i = 0; i < caratSelect.options.length; i++) {
            const o = caratSelect.options[i];
            const nameNum = parseFloat(String(o.textContent || '').replace(/[^0-9.]/g, ''));
            if (!isNaN(nameNum) && Math.abs(nameNum - num) < 0.001) {
                caratSelect.selectedIndex = i;
                return;
            }
        }
    }
    
    function setModalCellInput(row, column, value) {
        if (value == null || value === '') return;
        const cell = row.querySelector('[data-column="' + column + '"]');
        if (!cell) return;
        const inp = cell.querySelector('input, textarea');
        if (inp && inp.type !== 'checkbox') {
            inp.value = value;
        }
    }
    
    function setModalSelectIfOptionExists(row, column, value) {
        if (value == null || value === '') return;
        const sel = row.querySelector('[data-column="' + column + '"] select');
        if (!sel) return;
        const s = String(value).trim();
        if (!s) return;
        selectOptionByValueOrText(sel, s);
    }

    /** Voucher default discount type for current row metal (matches product-modal / reverse logic). Fallback Fix. */
    function getVoucherDefaultDiscountTypeForModalRow(row) {
        var fallback = 'Fix';
        if (typeof window.voucherSettingsByMetal !== 'object' || window.voucherSettingsByMetal === null) {
            return fallback;
        }
        var metalWise = 'Gold';
        var metalId = row && row.getAttribute ? row.getAttribute('data-metal-id') : null;
        if (typeof window.metals !== 'undefined' && window.metals && metalId != null && metalId !== '') {
            var metal = window.metals.find(function(m) { return String(m.id) === String(metalId); });
            if (metal && (metal.display_name || metal.name)) {
                metalWise = metal.display_name || metal.name;
            }
        }
        var vs = window.voucherSettingsByMetal[metalWise];
        if (!vs && window.voucherSettingsByMetal) {
            var mwLower = String(metalWise).toLowerCase().trim();
            for (var k in window.voucherSettingsByMetal) {
                if (Object.prototype.hasOwnProperty.call(window.voucherSettingsByMetal, k) && String(k).toLowerCase().trim() === mwLower) {
                    vs = window.voucherSettingsByMetal[k];
                    break;
                }
            }
        }
        if (vs && vs.default_discount_type) {
            var d = String(vs.default_discount_type).trim();
            // Legacy DB / voucher "On Amount" → use Fix for new lines & scans
            if (d && d !== 'On Amount') return d;
        }
        return fallback;
    }
    
    /**
     * Fill modal row fields from tbl_stock_journal row (returned as product.stock_journal from get-product-by-barcode.php).
     */
    function applyStockJournalToModalRow(row, sj) {
        if (!row || !sj || typeof sj !== 'object') return;
        const textCols = [
            ['rfid_code', 'rfid'],
            ['voucher_type', 'voucher-type'],
            ['huid_no', 'huid'],
            ['design_no', 'design-no'],
        ];
        textCols.forEach(function(pair) {
            var k = pair[0], col = pair[1];
            if (sj[k] != null && sj[k] !== '') setModalCellInput(row, col, sj[k]);
        });
        var inputMap = [
            ['pkt_wt', 'pkt-wt'], ['pkt_less_wt', 'pkt-less-wt'],
            ['gross_weight', 'gross-wt'], ['less_weight', 'less-wt'], ['net_weight', 'net-wt'],
            ['quantity', 'quantity'], ['rate', 'rate'], ['rate', 'metal-rate'], ['purity', 'purity'], ['purity_weight', 'purity-wt'],
            ['final_weight', 'final-wt'], ['wastage_per', 'wastage-per'], ['wastage_wt', 'wastage-wt'],
            ['alloy_wt', 'alloy-wt'], ['gold_loss_1', 'gold-loss1'], ['gold_loss_2', 'gold-loss2'],
            ['setting_charge', 'setting-charge'], ['metal_value', 'metal-value'], ['metal_cost', 'metal-cost'],
            ['amount', 'amount'], ['making_rate', 'making-rate'], ['making_amount', 'making-amount'],
            ['making_cost', 'making-cost'], ['minimum_price', 'min-price'],
            ['stone_weight', 'stone-weight'], ['stone_rate', 'stone-rate'], ['stone_amount', 'stone-amount'],
            ['stone_cost', 'stone-cost'], ['diamond_amount', 'diamond-amount'],
            ['purchase_amount', 'purchase-amount'], ['sale_percent', 'sale-percent'], ['sale_amount', 'sale-amount'], ['sale_amount_with', 'sale-amount-with'],
            ['net_amount', 'net-amt'], ['net_amt_with_tax', 'net-amt-tax'], ['tax_amount', 'tax'],
            ['requested_purity', 'requested-purity'], ['requested', 'requested'],
            ['discount_per', 'discount-per'], ['discount_amount', 'discount-amount'], ['discount', 'discount'],
            ['hallmark_amount', 'hallmark-amount'], ['hallmark_rate', 'hallmark-rate'],
            ['reverse', 'reverse'], ['other_weight', 'other-weight'], ['other_rate', 'other-rate'],
            ['other_amount', 'other-amount'],
        ];
        inputMap.forEach(function(pair) {
            var k = pair[0], col = pair[1];
            if (!Object.prototype.hasOwnProperty.call(sj, k)) return;
            var v = sj[k];
            if (v === null || v === '') return;
            if (typeof v === 'string' && String(v).trim() === '') return;
            setModalCellInput(row, col, v);
        });
        if (sj.other_info != null && sj.other_info !== '') setModalCellInput(row, 'other-info', sj.other_info);
        var catSel = row.querySelector('[data-column="category"] select');
        if (catSel && sj.category) selectOptionByValueOrText(catSel, sj.category);
        var locSel = row.querySelector('[data-column="location"] select');
        if (locSel && sj.location) selectOptionByValueOrText(locSel, sj.location);
        var calcSel = row.querySelector('[data-column="calculation"] select');
        if (calcSel && sj.calculation) setModalSelectIfOptionExists(row, 'calculation', sj.calculation);
        var makingTypeSel = row.querySelector('[data-column="making-type"] select');
        if (makingTypeSel && sj.making_type) setModalSelectIfOptionExists(row, 'making-type', sj.making_type);
        // Discount Type: use voucher default (e.g. Fix), not legacy journal discount_type (often On Amount)
        var discSel = row.querySelector('[data-column="discount-type"] select');
        if (discSel) {
            setModalSelectIfOptionExists(row, 'discount-type', getVoucherDefaultDiscountTypeForModalRow(row));
        }
        var stoneChargeSel = row.querySelector('[data-column="stone-charge-type"] select');
        if (stoneChargeSel && sj.stone_charge_type) setModalSelectIfOptionExists(row, 'stone-charge-type', sj.stone_charge_type);
        var otherChargeSel = row.querySelector('[data-column="other-charge-type"] select');
        if (otherChargeSel && sj.other_charge_type) setModalSelectIfOptionExists(row, 'other-charge-type', sj.other_charge_type);
        var caratSel = row.querySelector('[data-column="carat"] select');
        if (caratSel && (sj.karat != null && sj.karat !== '')) selectCaratFromStockJournal(caratSel, sj.karat);
        if (sj.id) row.setAttribute('data-stock-journal-id', String(sj.id));
    }
    
    // Populate row with product data
    // opts.fromBarcode: true when loaded via barcode scan (merge stock journal + stock weights). Omit or false for manual product pick (defaults: Metal Qty 1, Weight 0, Purity % 1).
    function populateRowWithProduct(row, product, opts) {
        opts = opts || {};
        const fromBarcode = !!opts.fromBarcode;
        // Update product name
        const productInput = row.querySelector('[data-column="product"] input');
        if (productInput) {
            const productName = product.name + (product.metal_name ? ' - ' + product.metal_name : '');
            productInput.value = productName;
        }
        
        // Update row data attributes
        row.setAttribute('data-product-id', product.id || '');
        row.setAttribute('data-characteristic-id', product.characteristic_id || '');
        if (product.purchase_invoice_item_id != null && product.purchase_invoice_item_id !== '') {
            row.setAttribute('data-purchase-invoice-item-id', String(product.purchase_invoice_item_id));
        } else {
            row.removeAttribute('data-purchase-invoice-item-id');
        }
        row.setAttribute('data-metal-id', (typeof currentMetalId !== 'undefined' ? currentMetalId : '') || product.metal_id || '');
        var bpx = (product.barcode_prefix != null && String(product.barcode_prefix).trim() !== '') ? String(product.barcode_prefix).trim() : '';
        var bdg = parseInt(product.barcode_digits, 10);
        if (bpx) row.setAttribute('data-barcode-prefix', bpx);
        else row.removeAttribute('data-barcode-prefix');
        if (!isNaN(bdg) && bdg >= 1) row.setAttribute('data-barcode-digits', String(bdg));
        else row.removeAttribute('data-barcode-digits');
        
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
        
        // Diamond Category from API / purchase (get-product-by-barcode sends diamond_category; row may still have generic category options).
        var dcFromProduct = product.diamond_category || product.category || (product.stock_journal && product.stock_journal.category) || '';
        var dcTrimPi = dcFromProduct ? String(dcFromProduct).trim() : '';
        if (dcTrimPi && ['Jewellery', 'Diamonds', 'GemStones'].indexOf(dcTrimPi) !== -1) {
            var catSelDiamondPi = row.querySelector('[data-column="category"] select');
            if (catSelDiamondPi && typeof populateCategorySelectForModal === 'function') {
                populateCategorySelectForModal(catSelDiamondPi, true);
            }
        }
        if (dcTrimPi !== '') {
            setModalSelectIfOptionExists(row, 'category', dcTrimPi);
            var calcSelDc = row.querySelector('[data-column="calculation"] select');
            if (calcSelDc && typeof applyCalculationSelectOptionsForRow === 'function') {
                var useDiamondCalcPi = ['Jewellery', 'Diamonds', 'GemStones'].indexOf(dcTrimPi) !== -1;
                applyCalculationSelectOptionsForRow(calcSelDc, row, useDiamondCalcPi || (typeof isDiamondTabActive === 'function' && isDiamondTabActive()));
            }
        }
        
        // Update Design No (prefer stock journal design_no when present on barcode scan)
        const designNoInput = row.querySelector('[data-column="design-no"] input');
        if (designNoInput) {
            const sjDn = fromBarcode && product.stock_journal && product.stock_journal.design_no;
            if (sjDn) designNoInput.value = sjDn;
            else if (product.article) designNoInput.value = product.article;
        }
        
        // Gross weight: barcode = journal / product / opening; manual pick = 0
        const grossWtInput = row.querySelector('[data-column="gross-wt"] input');
        if (grossWtInput) {
            let gw = null;
            if (fromBarcode) {
                if (product.stock_journal && product.stock_journal.gross_weight != null && product.stock_journal.gross_weight !== '') {
                    gw = product.stock_journal.gross_weight;
                } else if (product.gross_weight != null && product.gross_weight !== '') {
                    gw = product.gross_weight;
                } else if (product.opening_weight != null && product.opening_weight !== '') {
                    gw = product.opening_weight;
                }
            }
            grossWtInput.value = (gw != null && gw !== '') ? String(gw) : '0';
        }
        
        // Purity %: manual pick = 1; barcode = product / journal (applyStockJournal may refine)
        const purityInput = row.querySelector('[data-column="purity"] input');
        if (purityInput) {
            const p = (fromBarcode && product.opening_purity != null && product.opening_purity !== '') ? product.opening_purity : 1;
            purityInput.value = p;
        }
        
        // Update Barcode
        const barcodeInput = row.querySelector('[data-column="barcode"] input');
        if (barcodeInput && product.barcode) {
            barcodeInput.value = product.barcode;
        }
        
        // Final Weight: manual = 0; barcode = saved weights
        const finalWtInput = row.querySelector('[data-column="final-wt"] input');
        if (finalWtInput) {
            if (fromBarcode && product.final_weight) {
                finalWtInput.value = product.final_weight;
            } else if (fromBarcode && product.opening_weight) {
                finalWtInput.value = product.opening_weight;
            } else {
                finalWtInput.value = '0';
            }
        }
        
        // Update Rate
        const rateInput = row.querySelector('[data-column="rate"] input');
        if (rateInput && product.rate) {
            rateInput.value = product.rate;
        }
        // Metal Rate column (Jewellery calcs use metal-rate, not rate — sync from product / journal rate)
        const metalRateInput = row.querySelector('[data-column="metal-rate"] input');
        if (metalRateInput) {
            var mr = (product.metal_rate != null && product.metal_rate !== '') ? product.metal_rate : product.rate;
            if (mr != null && mr !== '' && !(typeof mr === 'string' && String(mr).trim() === '')) {
                metalRateInput.value = mr;
            }
        }
        
        // Metal Qty / Weight column: manual = 1 / 0; barcode = stock / journal
        const metalQtyInput = row.querySelector('[data-column="metal-qty"] input');
        if (metalQtyInput) {
            let mq = 1;
            if (fromBarcode) {
                if (product.metal_qty != null && product.metal_qty !== '') mq = product.metal_qty;
                else if (product.opening_qty != null && product.opening_qty !== '') mq = product.opening_qty;
                else if (product.quantity != null && product.quantity !== '') mq = product.quantity;
            }
            metalQtyInput.value = mq;
        }
        const metalWtInput = row.querySelector('[data-column="metal-weight"] input');
        if (metalWtInput) {
            let mw = 0;
            if (fromBarcode) {
                if (product.metal_weight != null && product.metal_weight !== '') mw = product.metal_weight;
                else if (product.stock_journal && product.stock_journal.gross_weight != null && product.stock_journal.gross_weight !== '') mw = product.stock_journal.gross_weight;
                else if (product.opening_weight != null && product.opening_weight !== '') mw = product.opening_weight;
                else if (product.gross_weight != null && product.gross_weight !== '') mw = product.gross_weight;
            }
            metalWtInput.value = mw;
        }
        
        // GST: data-* + data-gst-line-taxes from product; tax % from tbl_product_tax + owner vs customer state
        const taxPercentInput = row.querySelector('[data-column="tax-percent"] input');
        row.setAttribute('data-gst-local-pct', (product.gst_local_percent != null && product.gst_local_percent !== '') ? String(product.gst_local_percent) : '');
        row.setAttribute('data-gst-interstate-pct', (product.gst_interstate_percent != null && product.gst_interstate_percent !== '') ? String(product.gst_interstate_percent) : '');
        row.setAttribute('data-gst-invoice-slab-pct', typeof window.auragoldGstInvoiceSlabFromProductPayload === 'function' ? window.auragoldGstInvoiceSlabFromProductPayload(product) : '');
        if (product.gst_tax_breakdown && typeof window.auragoldGstLineTaxesFromProductPayload === 'function') {
            var ltJson = window.auragoldGstLineTaxesFromProductPayload(product);
            if (ltJson) row.setAttribute('data-gst-line-taxes', ltJson);
            else row.removeAttribute('data-gst-line-taxes');
        } else {
            row.removeAttribute('data-gst-line-taxes');
        }
        if (typeof window.auragoldGstSetProductTaxesAttrOnRow === 'function') {
            window.auragoldGstSetProductTaxesAttrOnRow(row, product);
        }
        if (taxPercentInput && typeof window.setSaleInvoiceGstTaxPercentDisplay === 'function') {
            window.setSaleInvoiceGstTaxPercentDisplay(row, taxPercentInput);
        }
        
        if (fromBarcode && product.stock_journal && typeof product.stock_journal === 'object') {
            applyStockJournalToModalRow(row, product.stock_journal);
        } else {
            // No journal row: still apply voucher default (Fix) for discount type
            var defDt = typeof getVoucherDefaultDiscountTypeForModalRow === 'function' ? getVoucherDefaultDiscountTypeForModalRow(row) : 'Fix';
            setModalSelectIfOptionExists(row, 'discount-type', defDt);
        }
        var dcAfterJournalPi = product.diamond_category || product.category || (product.stock_journal && product.stock_journal.category) || '';
        var dcAfterTrimPi = dcAfterJournalPi ? String(dcAfterJournalPi).trim() : '';
        if (dcAfterTrimPi && ['Jewellery', 'Diamonds', 'GemStones'].indexOf(dcAfterTrimPi) !== -1) {
            var catAfterPi = row.querySelector('[data-column="category"] select');
            if (catAfterPi && typeof populateCategorySelectForModal === 'function') {
                populateCategorySelectForModal(catAfterPi, true);
            }
            if (catAfterPi && typeof selectOptionByValueOrText === 'function') {
                selectOptionByValueOrText(catAfterPi, dcAfterTrimPi);
            }
        }
        var dcRowTagPi = product.diamond_category || product.category || (product.stock_journal && product.stock_journal.category) || '';
        var dcRowTagTrimPi = dcRowTagPi ? String(dcRowTagPi).trim() : '';
        if (dcRowTagTrimPi && ['Jewellery', 'Diamonds', 'GemStones'].indexOf(dcRowTagTrimPi) !== -1) {
            row.setAttribute('data-diamond-category', dcRowTagTrimPi);
        } else {
            row.removeAttribute('data-diamond-category');
        }
        
        // Trigger calculation to update all calculated fields
        calculateModalRowNetWeight(row);
        if (typeof syncDiamondTabSharedBarcodes === 'function') syncDiamondTabSharedBarcodes();
        // Diamond & Stones: Metal Qty is piece count — never use stock/opening_qty style values (often 10).
        if (typeof isDiamondTabActive === 'function' && isDiamondTabActive()) {
            var mqDiamond = row.querySelector('[data-column="metal-qty"] input');
            if (mqDiamond) mqDiamond.value = '1';
        }
        if (typeof window.auragoldApplyJournalImagesToModalRowPhoto === 'function') {
            window.auragoldApplyJournalImagesToModalRowPhoto(row, product);
        }
        if (typeof afterPopulateRowWithProductUniqueBarcode === 'function') {
            afterPopulateRowWithProductUniqueBarcode(row, product, opts);
        }
        if (typeof auragoldApplyManualAmountFieldsEditability === 'function') {
            auragoldApplyManualAmountFieldsEditability(row);
        }
    }
    
    window.populateRowWithProduct = populateRowWithProduct;
    
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
                tbody.innerHTML = '<tr><td colspan="73" class="text-center text-muted py-4">Click "Add Product" button to add products for billing...</td></tr>';
            }
        }
    }
    
    // Load products by metal (diamondCategory: optional filter for Diamond & Stones tab - 'Diamonds', 'GemStones', 'Jewellery')
    function loadProducts(metalId, search = '', diamondCategory = '') {
        const tbody = document.getElementById('productListBody');
        tbody.innerHTML = '<tr><td colspan="73" class="text-center text-muted py-4">Loading products...</td></tr>';
        
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
                            <tr class="product-row" data-product-id="${product.id}" data-characteristic-id="${product.characteristic_id || ''}" data-metal-id="${product.metal_id != null && product.metal_id !== '' ? product.metal_id : metalId}" data-barcode-prefix="${escapeHtml(String((product.barcode_prefix != null && product.barcode_prefix !== '') ? product.barcode_prefix : '').trim())}" data-barcode-digits="${(parseInt(product.barcode_digits, 10) > 0 ? parseInt(product.barcode_digits, 10) : 5)}">
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
                                <td data-column="discount-type"><select class="form-control form-control-sm" style="width: 150px; font-size: 0.7rem;"><option value="Fix" selected>Fix</option><option value="On Amount">On Amount</option><option value="On Making Amount">On Making Amount</option><option value="On Diamond Amount">On Diamond Amount</option><option value="On Stone Amount">On Stone Amount</option><option value="On Net Amount">On Net Amount</option></select></td>
                                <td data-column="discount-per"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
                                <td data-column="discount-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="discount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
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
                                            <td data-column="sale-percent"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;" placeholder="%" title="Sale % on Purchase Amount"></td>            <td data-column="sale-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 110px; font-size: 0.7rem;"></td>
                                <td data-column="sale-amount-with"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 130px; font-size: 0.7rem;"></td>
                                <td data-column="net-amt"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="tax-type"><select class="form-control form-control-sm" style="width: 120px; font-size: 0.7rem;"><option value="tax_on_making">Tax on making</option><option value="tax_of_netamount" selected>Tax of net amount</option><option value="no_tax">No tax</option></select></td>
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
                        var crow = select.closest('tr');
                        if (typeof populateCaratSelectForModalRow === 'function') populateCaratSelectForModalRow(select, crow);
                        else populateSelect(select, carats, 'id', 'name', 'Select Karat');
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
                    tbody.innerHTML = '<tr><td colspan="73" class="text-center text-muted py-4">No products found</td></tr>';
                }
            },
            error: function() {
                tbody.innerHTML = '<tr><td colspan="73" class="text-center text-danger py-4">Error loading products</td></tr>';
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
                                <tr class="product-row" data-product-id="${product.id}" data-characteristic-id="${product.characteristic_id || ''}" data-metal-id="${product.metal_id != null && product.metal_id !== '' ? product.metal_id : metalId}" data-barcode-prefix="${escapeHtml(String((product.barcode_prefix != null && product.barcode_prefix !== '') ? product.barcode_prefix : '').trim())}" data-barcode-digits="${(parseInt(product.barcode_digits, 10) > 0 ? parseInt(product.barcode_digits, 10) : 5)}">
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
                                    <td data-column="discount-type"><select class="form-control form-control-sm" style="width: 150px; font-size: 0.7rem;"><option value="Fix" selected>Fix</option><option value="On Amount">On Amount</option><option value="On Making Amount">On Making Amount</option><option value="On Diamond Amount">On Diamond Amount</option><option value="On Stone Amount">On Stone Amount</option><option value="On Net Amount">On Net Amount</option></select></td>
                                    <td data-column="discount-per"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
                                    <td data-column="discount-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="discount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
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
                                                <td data-column="sale-percent"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;" placeholder="%" title="Sale % on Purchase Amount"></td>            <td data-column="sale-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 110px; font-size: 0.7rem;"></td>
                                    <td data-column="sale-amount-with"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 130px; font-size: 0.7rem;"></td>
                                    <td data-column="net-amt"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                    <td data-column="tax-type"><select class="form-control form-control-sm" style="width: 120px; font-size: 0.7rem;"><option value="tax_on_making">Tax on making</option><option value="tax_of_netamount" selected>Tax of net amount</option><option value="no_tax">No tax</option></select></td>
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
                            var crow = select.closest('tr');
                            if (typeof populateCaratSelectForModalRow === 'function') populateCaratSelectForModalRow(select, crow);
                            else populateSelect(select, carats, 'id', 'name', 'Select Karat');
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
    
    function purchaseInvoiceAddProductModalRow(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        try {
            if (typeof window.addEmptyProductRow === 'function') {
                window.addEmptyProductRow();
            } else if (typeof addEmptyProductRow === 'function') {
                addEmptyProductRow();
            } else {
                console.error('addEmptyProductRow is not available');
                alert('Could not add product row. Please refresh the page.');
            }
        } catch (err) {
            console.error('addEmptyProductRow failed:', err);
            alert('Could not add product row: ' + (err && err.message ? err.message : 'Unknown error'));
        }
    }

    // Add Product Row Button Event Listener
    document.addEventListener('DOMContentLoaded', function() {
        const addProductRowBtn = document.getElementById('addProductRowBtn');
        if (addProductRowBtn) {
            addProductRowBtn.addEventListener('click', purchaseInvoiceAddProductModalRow);
        }
    });
    
    // Also use event delegation for dynamically added button
    $(document).on('click', '#addProductRowBtn', purchaseInvoiceAddProductModalRow);
    
    // Modal Table Column Visibility Toggle (includes Column Groups)
    (function() {
        const settingsBtn = document.getElementById('modalTableSettingsBtn');
        const settingsDropdown = document.getElementById('modalTableSettingsDropdown');
        if (!settingsBtn || !settingsDropdown) return;

        var PRODUCT_MODAL_GROUP_LABELS = {
            'basic-information': 'Basic Information',
            'diamond-group': 'Diamond group',
            'metal-group': 'Metal group',
            'request-final-group': 'Request & Final Wt.',
            'platinum-group': 'Platinum (group)',
            'discount-group': 'Discount (group)',
            'making-group': 'Making (group)',
            'minimum-group': 'Minimum',
            'stone-group': 'Stone group',
            'amounts': 'Amounts',
            'other-charge-group': 'Other Charge (group)',
            'cert-spec-group': 'Certificate & spec',
            'hallmark': 'Hallmark',
            'net-reverse': 'Net Amt+Tax / Reverse'
        };

        function ensureGroupCheckboxesInDropdown() {
            if (typeof window.auragoldEnsureProductModalGroupCheckboxesInDropdown === 'function') {
                window.auragoldEnsureProductModalGroupCheckboxesInDropdown(settingsDropdown, PRODUCT_MODAL_GROUP_LABELS);
                return;
            }
        }

        function setSubColumnDisabledState(groupKey, disabled) {
            if (typeof window.auragoldSetProductModalSubColumnDisabledState === 'function') {
                window.auragoldSetProductModalSubColumnDisabledState(settingsDropdown, groupKey, disabled);
            }
        }

        function updateAllSubColumnDisabledStates() {
            if (typeof window.auragoldUpdateAllProductModalSubColumnDisabledStates === 'function') {
                window.auragoldUpdateAllProductModalSubColumnDisabledStates(settingsDropdown);
            }
        }

        function getColumnVisibilityPrefs() {
            var prefs = {};
            settingsDropdown.querySelectorAll('input[type="checkbox"][data-column]').forEach(function(cb) {
                prefs[cb.getAttribute('data-column')] = cb.checked ? 1 : 0;
            });
            return prefs;
        }

        function setColumnVisible(columnName, isVisible) {
            if (typeof window.toggleColumnVisibility === 'function') {
                window.toggleColumnVisibility(columnName, isVisible);
            } else {
                var table = document.getElementById('productListTable');
                if (!table) return;
                table.querySelectorAll('[data-column="' + columnName + '"]').forEach(function(el) {
                    el.style.display = isVisible ? '' : 'none';
                    el.classList.toggle('hidden', !isVisible);
                    el.querySelectorAll('input, select').forEach(function(inp) {
                        inp.style.setProperty('display', isVisible ? '' : 'none', 'important');
                    });
                });
            }
            var cb = settingsDropdown.querySelector('input[data-column="' + columnName + '"]');
            if (cb) cb.checked = isVisible;
        }

        function syncGroupCheckboxState(groupKey) {
            var columnGroups = window.PRODUCT_MODAL_COLUMN_GROUPS || {};
            var cols = columnGroups[groupKey];
            if (!cols || !cols.length) return;
            var anyVisible = cols.some(function(c) {
                var cb = settingsDropdown.querySelector('input[data-column="' + c + '"]');
                return cb && cb.checked;
            });
            var groupCb = settingsDropdown.querySelector('input[data-group="' + groupKey + '"]');
            if (groupCb) groupCb.checked = anyVisible;
        }

        function syncAllGroupCheckboxStates() {
            var columnGroups = window.PRODUCT_MODAL_COLUMN_GROUPS || {};
            Object.keys(columnGroups).forEach(syncGroupCheckboxState);
        }
        
        // Toggle dropdown on button click
        settingsBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            ensureGroupCheckboxesInDropdown();
            if (typeof syncProductModalColumnGroupMasterCheckboxes === 'function') {
                syncProductModalColumnGroupMasterCheckboxes();
            } else {
                syncAllGroupCheckboxStates();
            }
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

        settingsDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
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

        // Column groups and updateGroupHeaderVisibility are in product-modal-add-item-common.js (window.PRODUCT_MODAL_COLUMN_GROUPS, window.updateGroupHeaderVisibility)
        // Handle column visibility and group visibility (delegated)
        settingsDropdown.addEventListener('change', function(e) {
            if (e.target.type !== 'checkbox') return;
            var groupKey = e.target.getAttribute('data-group');
            var columnName = e.target.getAttribute('data-column');
            var columnGroups = window.PRODUCT_MODAL_COLUMN_GROUPS || {};

            if (groupKey && !columnName) {
                var isVisible = e.target.checked;
                var cols = columnGroups[groupKey];
                if (cols && cols.length) {
                    cols.forEach(function(c) {
                        setColumnVisible(c, isVisible);
                    });
                    setSubColumnDisabledState(groupKey, !isVisible);
                    requestAnimationFrame(function() {
                        requestAnimationFrame(function() {
                            if (typeof window.syncProductModalColumnLayoutAfterToggle === 'function') {
                                window.syncProductModalColumnLayoutAfterToggle();
                            } else if (typeof updateGroupHeaderVisibility === 'function') {
                                updateGroupHeaderVisibility();
                            }
                            var modalBody = document.querySelector('#productSelectionModal .modal-body');
                            if (modalBody) modalBody.scrollLeft = 0;
                            var scrollWrap = document.getElementById('productListTableScrollWrapper');
                            if (scrollWrap) scrollWrap.scrollLeft = 0;
                        });
                    });
                    if (typeof window.auragoldSyncProductModalColumnPrefsToMemory === 'function') {
                        window.auragoldSyncProductModalColumnPrefsToMemory(currentMetalId || '', settingsDropdown);
                    }
                    saveProductModalColumnPreferencesDebounced(currentMetalId || '');
                }
                return;
            }

            if (!columnName) return;
            var isVisible = e.target.checked;
            var colGroup = null;
            Object.keys(columnGroups).forEach(function(gk) {
                if ((columnGroups[gk] || []).indexOf(columnName) !== -1) colGroup = gk;
            });
            if (isVisible && colGroup) {
                var groupCb = settingsDropdown.querySelector('input[data-group="' + colGroup + '"]');
                if (groupCb && !groupCb.checked) {
                    groupCb.checked = true;
                    setSubColumnDisabledState(colGroup, false);
                }
            }
            setColumnVisible(columnName, isVisible);
            if (colGroup) syncGroupCheckboxState(colGroup);
            updateAllSubColumnDisabledStates();
            var tabKey = (typeof currentMetalId !== 'undefined' && currentMetalId !== null) ? String(currentMetalId) : '';
            if (typeof window.auragoldSyncProductModalColumnPrefsToMemory === 'function') {
                window.auragoldSyncProductModalColumnPrefsToMemory(tabKey, settingsDropdown);
            }
            requestAnimationFrame(function() {
                requestAnimationFrame(function() {
                    if (typeof window.syncProductModalColumnLayoutAfterToggle === 'function') {
                        window.syncProductModalColumnLayoutAfterToggle();
                    } else if (typeof updateGroupHeaderVisibility === 'function') {
                        updateGroupHeaderVisibility();
                    }
                });
            });
            saveProductModalColumnPreferencesDebounced(currentMetalId || '');
        });
        
        // Initialize group header visibility on page load
        // Use setTimeout to ensure DOM is fully loaded
        setTimeout(function() {
            if (typeof window.syncProductModalColumnLayoutAfterToggle === 'function') {
                window.syncProductModalColumnLayoutAfterToggle();
            } else if (typeof updateGroupHeaderVisibility === 'function') {
                updateGroupHeaderVisibility();
            }
        }, 100);
        // Re-evaluate sticky vs flow layout when window is resized while modal is open
        var productModalStickyResizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(productModalStickyResizeTimer);
            productModalStickyResizeTimer = setTimeout(function() {
                var m = document.getElementById('productSelectionModal');
                if (m && m.classList.contains('show') && typeof window.syncProductModalColumnLayoutAfterToggle === 'function') {
                    window.syncProductModalColumnLayoutAfterToggle();
                }
            }, 150);
        });
        
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
                // Ensure one empty row for new Add Item if table was left empty (e.g. race with tab init)
                try {
                    var editingId = (typeof currentEditingRowId !== 'undefined') ? currentEditingRowId : null;
                    var bodyEl = document.getElementById('productListBody');
                    if (!editingId && bodyEl && bodyEl.querySelectorAll('tr.product-row').length === 0
                        && typeof window.addEmptyProductRow === 'function') {
                        window.addEmptyProductRow();
                    }
                } catch (ensureRowErr) {
                    console.error('Ensure default product row failed:', ensureRowErr);
                }
                // Reload saved column preferences and apply after load; Diamond & Stones tab gets fixed column set
                loadProductModalColumnPreferences(function() {
                    setTimeout(function() {
                        var tabKey = (typeof currentMetalId !== 'undefined' && currentMetalId !== null) ? String(currentMetalId) : '';
                        if (typeof applyProductModalColumnVisibilityForTab === 'function') applyProductModalColumnVisibilityForTab(tabKey);
                        ensureGroupCheckboxesInDropdown();
                        syncAllGroupCheckboxStates();
                        if (typeof window.syncProductModalColumnLayoutAfterToggle === 'function') {
                            window.syncProductModalColumnLayoutAfterToggle();
                        } else if (typeof updateGroupHeaderVisibility === 'function') {
                            updateGroupHeaderVisibility();
                        }
                        var modalBody = document.querySelector('#productSelectionModal .modal-body');
                        if (modalBody) modalBody.scrollLeft = 0;
                        var scrollWrap = document.getElementById('productListTableScrollWrapper');
                        if (scrollWrap) scrollWrap.scrollLeft = 0;
                        var storedImage = window.productModalGroupImageByTab && window.productModalGroupImageByTab[tabKey];
                        if (storedImage && typeof applyProductModalGroupImageToPhotoColumns === 'function') {
                            applyProductModalGroupImageToPhotoColumns(storedImage, tabKey);
                        }
                    }, 50);
                });
            });
        }
        
        // Column search functionality for modal (show group block if group or any nested column matches)
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
                            var block = item.closest('.table-settings-group-block');
                            if (block) block.classList.remove('hidden');
                        } else {
                            item.classList.add('hidden');
                        }
                    }
                });
                if (!searchTerm) {
                    settingsDropdown.querySelectorAll('.table-settings-group-block, .table-settings-item').forEach(function(el) {
                        el.classList.remove('hidden');
                    });
                } else {
                    settingsDropdown.querySelectorAll('.table-settings-group-block').forEach(function(block) {
                        var anyVisible = block.querySelectorAll('.table-settings-item:not(.hidden)').length > 0;
                        block.classList.toggle('hidden', !anyVisible);
                    });
                }
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
        var isDiamondTab = (typeof isDiamondTabActive === 'function' && isDiamondTabActive());
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
        var diamondGroupColumns = (typeof getDiamondGroupColumnKeys === 'function')
            ? getDiamondGroupColumnKeys()
            : ['pkt-wt', 'pkt-less-wt', 'gross-wt', 'stone-weight', 'less-wt', 'net-wt', 'quantity', 'rate', 'amount'];
        function modalTabColumnShouldShow(columnName) {
            if (!isDiamondTab && columnName === 'category') return false;
            if (!isDiamondTab && diamondGroupColumns.indexOf(columnName) !== -1) return false;
            if (isDiamondTab) return !!(prefs && prefs[columnName] === 1);
            if (prefs && prefs.hasOwnProperty(columnName)) return prefs[columnName] === 1;
            return true;
        }
        // Single visibility path: every th/td with data-column uses the same toggle (header + body stay aligned)
        var columnKeys = (typeof window.getProductModalHeaderColumnKeys === 'function')
            ? window.getProductModalHeaderColumnKeys()
            : [];
        if (!columnKeys.length) {
            var kmap = {};
            table.querySelectorAll('thead [data-column]').forEach(function(el) {
                var c = el.getAttribute('data-column');
                if (c) kmap[c] = true;
            });
            columnKeys = Object.keys(kmap);
        }
        columnKeys.forEach(function(col) {
            var show = modalTabColumnShouldShow(col);
            if (typeof window.toggleColumnVisibility === 'function') {
                window.toggleColumnVisibility(col, show);
            }
        });
        const checkboxes = settingsDropdown.querySelectorAll('input[type="checkbox"][data-column]');
        checkboxes.forEach(function(checkbox) {
            var columnName = checkbox.getAttribute('data-column');
            checkbox.checked = modalTabColumnShouldShow(columnName);
        });
        if (typeof syncProductModalColumnGroupMasterCheckboxes === 'function') {
            syncProductModalColumnGroupMasterCheckboxes();
        }
        // Diamond group header row: on/off by tab (column cells already toggled above)
        (function applyDiamondGroupVisibilityByTab() {
            var groupHeaderRow = table.querySelector('thead tr:first-child');
            var diamondGroupHeader = groupHeaderRow ? groupHeaderRow.querySelector('th[data-group="diamond-group"]') : null;
            if (isDiamondTab) {
                if (diamondGroupHeader) {
                    diamondGroupHeader.style.display = '';
                    diamondGroupHeader.classList.remove('hidden');
                }
            } else if (diamondGroupHeader) {
                diamondGroupHeader.style.display = 'none';
                diamondGroupHeader.classList.add('hidden');
                diamondGroupHeader.setAttribute('colspan', '1');
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
        // Diamond tab: category options — preserve rows already set to Jewellery/Diamonds/GemStones when active tab is not Diamond yet (multi-line barcode scan).
        if (typeof populateCategorySelectForModal === 'function') {
            var categorySelects = table.querySelectorAll('#productListBody [data-column="category"] select');
            categorySelects.forEach(function(sel) {
                var v = (sel.value || '').trim();
                var tr = sel.closest ? sel.closest('tr.product-row') : null;
                var dataDc = tr && tr.getAttribute ? (tr.getAttribute('data-diamond-category') || '').trim() : '';
                var forceDiamond = sel.classList.contains('diamond-category-select')
                    || ['Jewellery', 'Diamonds', 'GemStones'].indexOf(v) !== -1
                    || (dataDc && ['Jewellery', 'Diamonds', 'GemStones'].indexOf(dataDc) !== -1);
                populateCategorySelectForModal(sel, isDiamondTab || forceDiamond);
            });
        }
        if (typeof applyCalculationSelectOptionsForRow === 'function') {
            table.querySelectorAll('#productListBody .product-row').forEach(function(r) {
                var sel = r.querySelector('[data-column="calculation"] select');
                if (!sel) return;
                var catSel = r.querySelector('[data-column="category"] select');
                var cv = (catSel && catSel.value) ? String(catSel.value).trim() : '';
                var rowDiamondCat = ['Jewellery', 'Diamonds', 'GemStones'].indexOf(cv) !== -1;
                applyCalculationSelectOptionsForRow(sel, r, isDiamondTab || rowDiamondCat);
            });
        }
        // Snapshot dropdown HTML once (optional); do not replace panel on Diamond tab — use common-modal list for all metals
        (function captureProductModalSettingsDropdownSnapshot() {
            var dropdown = document.getElementById('modalTableSettingsDropdown');
            if (!dropdown) return;
            if (!window.productModalOriginalSettingsDropdownContent) {
                window.productModalOriginalSettingsDropdownContent = dropdown.innerHTML;
            }
        })();
        setTimeout(function() {
            if (typeof window.syncProductModalColumnLayoutAfterToggle === 'function') {
                window.syncProductModalColumnLayoutAfterToggle();
            } else if (typeof updateGroupHeaderVisibility === 'function') {
                updateGroupHeaderVisibility();
            }
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
            if (typeof window.auragoldSyncProductModalColumnPrefsToMemory === 'function') {
                window.auragoldSyncProductModalColumnPrefsToMemory(tabKey, settingsDropdown);
            }
            const prefs = window.productModalColumnVisibilityByTab[String(tabKey)] || {};
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
            addProductToTableFromModalRow(modalRowData, (typeof currentMetalId !== 'undefined' ? currentMetalId : undefined));
                        if (closeModal) {
                            hideProductModal();
                        }
                    }
        
                    updateSummaryPanel();
    }
    
    /** Purchase invoice: main table Quantity column = modal Metal Qty (modal "Quantity" is often 1 for piece count). */
    function purchaseInvoiceQuantityFromModalLine(d) {
        if (!d || typeof d !== 'object') return 0;
        var mq = parseFloat(d.metal_qty);
        if (!isNaN(mq) && mq > 0) return mq;
        return parseFloat(d.quantity) || 0;
    }
    
    // Add product to table from modal row data (with all calculated values)
    function addProductToTableFromModalRow(modalRowData, metalIdForImage, loadOpts) {
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
        
        // Ensure barcode is not empty - if still empty, use a placeholder
        if (!barcode || barcode.trim() === '') {
            console.warn('Barcode is empty for product:', modalRowData.product_id, 'characteristic:', modalRowData.characteristic_id);
        }
        
        try {
            var tabPayload = (metalIdForImage != null && window.productModalGroupImageByTab && window.productModalGroupImageByTab[metalIdForImage]) ? window.productModalGroupImageByTab[metalIdForImage] : '';
            var rowGi = (typeof window.auragoldCoParseGroupImageAttr === 'function') ? window.auragoldCoParseGroupImageAttr(modalRowData.group_image || '') : '';
            var groupImagePayload = tabPayload || rowGi || '';
            var groupImageAttr = (typeof groupImagePayload === 'object' && groupImagePayload != null) ? JSON.stringify(groupImagePayload) : (groupImagePayload || '');
            row.setAttribute('data-group-image', groupImageAttr);
            var primaryUrl = typeof getGroupImagePrimary === 'function' ? getGroupImagePrimary(groupImagePayload) : (typeof groupImagePayload === 'string' ? groupImagePayload : '');
            var modalData = Object.assign({}, modalRowData);
            if (barcode) modalData.barcode = barcode;
            var pq = purchaseInvoiceQuantityFromModalLine(modalData);
            if (pq > 0) modalData.quantity = pq;
            if (!(parseFloat(modalData.diamond_carat) > 0)) {
                modalData.diamond_carat = parseFloat(modalData.stone_weight) || 0;
            }
            if (!(parseFloat(modalData.diamond_wt) > 0)) {
                modalData.diamond_wt = parseFloat(modalData.less_wt) || 0;
            }
            row.setAttribute('data-group-items', JSON.stringify([modalData]));
            var actionCell = '<td><div class="action-btns"><button type="button" class="btn-edit" onclick="editProductRow(\'' + rowId + '\')" title="Edit"><i class="feather icon-edit"></i></button><button type="button" class="btn-delete" onclick="deleteProductRow(\'' + rowId + '\')" title="Delete"><i class="feather icon-trash-2"></i></button></div></td>';
            row.innerHTML = (typeof getProductListRowCells === 'function' ? getProductListRowCells(modalData, { groupImage: primaryUrl }) : []).join('') + actionCell;
            
            tbody.appendChild(row);
            if (typeof window.applyProductListColumnVisibilityToRow === 'function') window.applyProductListColumnVisibilityToRow(row);
            if (typeof window.auragoldRefreshProductTableRowPhotoFromJournal === 'function') window.auragoldRefreshProductTableRowPhotoFromJournal(row);
            if (loadOpts && loadOpts.fromSavedInvoice) {
                row.setAttribute('data-preserve-line-amounts', '1');
            }
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
    function addMergedProductsToTable(modalRowsData, metalIdForImage, loadOpts) {
        if (!modalRowsData || modalRowsData.length === 0) return;
        if (modalRowsData.length === 1) {
            addProductToTableFromModalRow(modalRowsData[0], metalIdForImage, loadOpts);
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
            merged.quantity += purchaseInvoiceQuantityFromModalLine(d);
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
        // Diamond & Stones composite: use Jewellery line only (weights + amounts) — summing diamonds double-counts D.Weight/Less Wt
        if (typeof window.applyDiamondJewelleryCompositeRollup === 'function') {
            window.applyDiamondJewelleryCompositeRollup(merged, modalRowsData, {
                quantityFromLine: purchaseInvoiceQuantityFromModalLine,
                saleMode: false
            });
        } else {
            var jewelleryRow = modalRowsData.filter(function(d) { return (String(d.category || '').trim() === 'Jewellery'); })[0];
            if (jewelleryRow && modalRowsData.some(function(d) { return (String(d.category || '').trim() === 'Diamonds') || (String(d.category || '').trim() === 'GemStones'); })) {
                var jNetAmt = parseFloat(jewelleryRow.net_amt) || 0;
                var jNetAmtTax = parseFloat(jewelleryRow.net_amt_tax) || 0;
                if (jNetAmtTax <= 0) jNetAmtTax = jNetAmt;
                merged.net_amt = jNetAmt;
                merged.net_amt_tax = jNetAmtTax;
                merged.amount = jNetAmt;
                merged.purchase_amount = jNetAmt;
                merged.purchase_amount_with = jNetAmtTax;
                merged.metal_value = parseFloat(jewelleryRow.metal_value) || 0;
                merged.other_amount = parseFloat(jewelleryRow.other_amount) || 0;
                merged.making_amount = parseFloat(jewelleryRow.making_amount) || 0;
                merged.discount = parseFloat(jewelleryRow.discount) || 0;
                merged.stone_amount = parseFloat(jewelleryRow.stone_amount) || 0;
                merged.diamond_amount = parseFloat(jewelleryRow.diamond_amount) || 0;
                merged.tax = parseFloat(jewelleryRow.tax) || 0;
                merged.gross_wt = parseFloat(jewelleryRow.gross_wt) || 0;
                merged.less_wt = parseFloat(jewelleryRow.less_wt) || 0;
                merged.net_wt = parseFloat(jewelleryRow.net_wt) || 0;
                merged.pure_wt = parseFloat(jewelleryRow.pure_wt) || 0;
                merged.final_wt = parseFloat(jewelleryRow.final_wt) || 0;
                merged.stone_weight = parseFloat(jewelleryRow.stone_weight) || 0;
                merged.quantity = purchaseInvoiceQuantityFromModalLine(jewelleryRow) || parseFloat(jewelleryRow.quantity) || 0;
                merged.rate = parseFloat(jewelleryRow.rate) || 0;
            }
        }
        if (!merged.purchase_amount_with) merged.purchase_amount_with = merged.net_amt_tax || merged.sale_amount_with || 0;
        if (!(parseFloat(merged.diamond_carat) > 0)) {
            merged.diamond_carat = parseFloat(merged.stone_weight) || 0;
        }
        if (!(parseFloat(merged.diamond_wt) > 0)) {
            merged.diamond_wt = parseFloat(merged.less_wt) || 0;
        }
        
        var normalizedGroupItems = modalRowsData.map(function(d) {
            var o = Object.assign({}, d);
            var pq = purchaseInvoiceQuantityFromModalLine(d);
            if (pq > 0) o.quantity = pq;
            return o;
        });
        
        productTableRowIndex++;
        var rowId = 'product-row-' + productTableRowIndex;
        var row = document.createElement('tr');
        row.id = rowId;
        row.setAttribute('data-product-id', merged.product_id || '');
        row.setAttribute('data-characteristic-id', merged.characteristic_id || '');
        row.setAttribute('data-group-items', JSON.stringify(normalizedGroupItems));
        row.setAttribute('data-purity', merged.purity || 0);
        row.setAttribute('data-rate', merged.rate || 0);
        row.setAttribute('data-calculation-type', merged.calculation_type || 'Rate X Gross Wt');
        row.setAttribute('data-barcode', (merged.barcode || '').trim());
        
        var tabPayloadMerge = (metalIdForImage != null && window.productModalGroupImageByTab && window.productModalGroupImageByTab[metalIdForImage]) ? window.productModalGroupImageByTab[metalIdForImage] : '';
        var firstRowGiPi = '';
        for (var _agiPi = 0; _agiPi < modalRowsData.length; _agiPi++) {
            var rgi = modalRowsData[_agiPi].group_image;
            if (rgi && String(rgi).trim()) { firstRowGiPi = rgi; break; }
        }
        var groupImagePayload = tabPayloadMerge || ((typeof window.auragoldCoParseGroupImageAttr === 'function') ? window.auragoldCoParseGroupImageAttr(firstRowGiPi) : '') || '';
        var groupImageAttr = (typeof groupImagePayload === 'object' && groupImagePayload != null) ? JSON.stringify(groupImagePayload) : (groupImagePayload || '');
        row.setAttribute('data-group-image', groupImageAttr);
        var primaryUrl = typeof getGroupImagePrimary === 'function' ? getGroupImagePrimary(groupImagePayload) : (typeof groupImagePayload === 'string' ? groupImagePayload : '');
        var actionCell = '<td><div class="action-btns"><button type="button" class="btn-edit" onclick="editProductRow(\'' + rowId + '\')" title="Edit"><i class="feather icon-edit"></i></button><button type="button" class="btn-delete" onclick="deleteProductRow(\'' + rowId + '\')" title="Delete"><i class="feather icon-trash-2"></i></button></div></td>';
        row.innerHTML = (typeof getProductListRowCells === 'function' ? getProductListRowCells(merged, { groupImage: primaryUrl }) : []).join('') + actionCell;
        
        tbody.appendChild(row);
        if (typeof window.applyProductListColumnVisibilityToRow === 'function') window.applyProductListColumnVisibilityToRow(row);
        if (typeof window.auragoldRefreshProductTableRowPhotoFromJournal === 'function') window.auragoldRefreshProductTableRowPhotoFromJournal(row);
        if (loadOpts && loadOpts.fromSavedInvoice) {
            row.setAttribute('data-preserve-line-amounts', '1');
        }
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
                purchase_amount: netAmt, sale_amount: 0, sale_amount_with: 0, purchase_amount_with: netAmtWithTax, reverse: 0
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
        if (typeof window.rebuildProductListRowFromModalRows === 'function') {
            var _qFn = null;
            if (typeof purchaseInvoiceQuantityFromModalLine === 'function') _qFn = purchaseInvoiceQuantityFromModalLine;
            else if (typeof saleInvoiceQuantityFromModalLine === 'function') _qFn = saleInvoiceQuantityFromModalLine;
            else if (typeof quantityFromModalLine === 'function') _qFn = quantityFromModalLine;
            window.rebuildProductListRowFromModalRows(rowId, modalRowsData, _qFn ? { quantityFromLine: _qFn } : {});
            return;
        }
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
            merged.quantity += purchaseInvoiceQuantityFromModalLine(d);
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
        // Diamond & Stones composite: use Jewellery line only (weights + amounts)
        if (typeof window.applyDiamondJewelleryCompositeRollup === 'function') {
            window.applyDiamondJewelleryCompositeRollup(merged, modalRowsData, {
                quantityFromLine: purchaseInvoiceQuantityFromModalLine,
                saleMode: false
            });
        } else {
            var jewelleryRowEdit = modalRowsData.filter(function(d) { return (String(d.category || '').trim() === 'Jewellery'); })[0];
            if (jewelleryRowEdit && modalRowsData.some(function(d) { return (String(d.category || '').trim() === 'Diamonds') || (String(d.category || '').trim() === 'GemStones'); })) {
                var jNetAmtEdit = parseFloat(jewelleryRowEdit.net_amt) || 0;
                var jNetAmtTaxEdit = parseFloat(jewelleryRowEdit.net_amt_tax) || 0;
                if (jNetAmtTaxEdit <= 0) jNetAmtTaxEdit = jNetAmtEdit;
                merged.net_amt = jNetAmtEdit;
                merged.net_amt_tax = jNetAmtTaxEdit;
                merged.amount = jNetAmtEdit;
                merged.purchase_amount = jNetAmtEdit;
                merged.purchase_amount_with = jNetAmtTaxEdit;
                merged.metal_value = parseFloat(jewelleryRowEdit.metal_value) || 0;
                merged.other_amount = parseFloat(jewelleryRowEdit.other_amount) || 0;
                merged.making_amount = parseFloat(jewelleryRowEdit.making_amount) || 0;
                merged.discount = parseFloat(jewelleryRowEdit.discount) || 0;
                merged.stone_amount = parseFloat(jewelleryRowEdit.stone_amount) || 0;
                merged.diamond_amount = parseFloat(jewelleryRowEdit.diamond_amount) || 0;
                merged.tax = parseFloat(jewelleryRowEdit.tax) || 0;
                merged.gross_wt = parseFloat(jewelleryRowEdit.gross_wt) || 0;
                merged.less_wt = parseFloat(jewelleryRowEdit.less_wt) || 0;
                merged.net_wt = parseFloat(jewelleryRowEdit.net_wt) || 0;
                merged.pure_wt = parseFloat(jewelleryRowEdit.pure_wt) || 0;
                merged.final_wt = parseFloat(jewelleryRowEdit.final_wt) || 0;
                merged.stone_weight = parseFloat(jewelleryRowEdit.stone_weight) || 0;
                merged.quantity = purchaseInvoiceQuantityFromModalLine(jewelleryRowEdit) || parseFloat(jewelleryRowEdit.quantity) || 0;
            }
        }
        if (!merged.purchase_amount_with) merged.purchase_amount_with = merged.net_amt_tax || merged.sale_amount_with || 0;
        var normalizedEditItems = modalRowsData.map(function(d) {
            var o = Object.assign({}, d);
            var pq = purchaseInvoiceQuantityFromModalLine(d);
            if (pq > 0) o.quantity = pq;
            return o;
        });
        row.setAttribute('data-group-items', JSON.stringify(normalizedEditItems));
        var q = row.querySelector('[data-field="quantity"]'); if (q) q.value = (merged.quantity || 0).toFixed(2);
        var g = row.querySelector('[data-field="gross_wt"]'); if (g) g.value = (merged.gross_wt || 0).toFixed(3);
        var l = row.querySelector('[data-field="less_wt"]'); if (l) l.value = (merged.less_wt || 0).toFixed(3);
        var p = row.querySelector('[data-field="purity"]'); if (p) p.value = (parseFloat(modalRowsData[0].purity) || 0).toFixed(2);
        var f = row.querySelector('[data-field="final_wt"]'); if (f) f.value = (merged.final_wt || 0).toFixed(3);
        var n = row.querySelector('[data-column="net-wt"]'); if (n) n.textContent = (merged.net_wt || 0).toFixed(3);
        var pw = row.querySelector('[data-column="pure-wt"]'); if (pw) pw.textContent = (merged.pure_wt || 0).toFixed(3);
        var sw = row.querySelector('[data-column="stone-weight"]'); if (sw) sw.textContent = (merged.stone_weight || 0).toFixed(3);
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
        if (typeof window.rebuildProductListRowFromModalRows === 'function' && typeof getModalRowDataFromRow === 'function' && modalRow) {
            var _d = getModalRowDataFromRow(modalRow, true);
            var _qFn2 = null;
            if (typeof purchaseInvoiceQuantityFromModalLine === 'function') _qFn2 = purchaseInvoiceQuantityFromModalLine;
            else if (typeof saleInvoiceQuantityFromModalLine === 'function') _qFn2 = saleInvoiceQuantityFromModalLine;
            else if (typeof quantityFromModalLine === 'function') _qFn2 = quantityFromModalLine;
            window.rebuildProductListRowFromModalRows(productListRowId, [_d], _qFn2 ? { quantityFromLine: _qFn2 } : {});
            return;
        }
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
        
        const metalQtyFromModal = getValue('metal-qty');
        const qtyColFromModal = getValue('quantity');
        const rowData = {
            product_id: modalRow.getAttribute('data-product-id') || '',
            characteristic_id: modalRow.getAttribute('data-characteristic-id') || '',
            product_name: getValue('product', false),
            quantity: (metalQtyFromModal > 0) ? metalQtyFromModal : qtyColFromModal,
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
        if (!tbody) return;
        const rows = tbody.querySelectorAll('tr:not(.no-drag)');
        function plRowCellFloat(row, dataColumn) {
            var cell = row.querySelector('[data-column="' + dataColumn + '"]');
            if (!cell) return 0;
            var inp = cell.querySelector('input');
            if (inp && inp.value != null && String(inp.value).trim() !== '') {
                var v = parseFloat(String(inp.value).replace(/,/g, ''));
                if (!isNaN(v)) return v;
            }
            var sel = cell.querySelector('select');
            if (sel && sel.value != null && String(sel.value).trim() !== '') {
                var vs = parseFloat(String(sel.value).replace(/,/g, ''));
                if (!isNaN(vs)) return vs;
            }
            var sp = cell.querySelector('span');
            if (sp) {
                var sv = parseFloat(String(sp.textContent || '').replace(/,/g, ''));
                if (!isNaN(sv)) return sv;
            }
            var t = parseFloat(String(cell.textContent || '').replace(/,/g, ''));
            return isNaN(t) ? 0 : t;
        }
        function plRowAmountFromGroup(row, key) {
            try {
                var raw = row.getAttribute('data-group-items');
                if (!raw) return 0;
                var arr = JSON.parse(raw);
                if (!Array.isArray(arr) || !arr.length) return 0;
                var sum = 0;
                arr.forEach(function(d) {
                    var n = parseFloat(d && d[key]);
                    if (!isNaN(n)) sum += n;
                });
                return sum;
            } catch (e) { return 0; }
        }
        
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
            const qty = plRowCellFloat(row, 'quantity') || parseFloat(row.querySelector('[data-field="quantity"]')?.value) || 0;
            const grossWt = plRowCellFloat(row, 'gross-wt') || parseFloat(row.querySelector('[data-field="gross_wt"]')?.value) || 0;
            const lessWt = plRowCellFloat(row, 'less-wt') || parseFloat(row.querySelector('[data-field="less_wt"]')?.value) || 0;
            const purity = plRowCellFloat(row, 'purity') || parseFloat(row.querySelector('[data-field="purity"]')?.value) || 0;
            const finalWt = plRowCellFloat(row, 'final-wt') || parseFloat(row.querySelector('[data-field="final_wt"]')?.value) || 0;
            const netWt = plRowCellFloat(row, 'net-wt');
            const pureWt = row.querySelector('[data-column="purity-wt"]') ? plRowCellFloat(row, 'purity-wt') : plRowCellFloat(row, 'pure-wt');
            const making = parseFloat(row.querySelector('[data-field="making"]')?.value) || plRowCellFloat(row, 'making-amount') || 0;
            const tax = plRowCellFloat(row, 'tax') || parseFloat(row.querySelector('[data-field="tax"]')?.value) || 0;
            let amount = plRowCellFloat(row, 'amount');
            let netAmt = plRowCellFloat(row, 'net-amt');
            let netAmtTax = plRowCellFloat(row, 'net-amt-tax');
            const stoneCharges = parseFloat(row.querySelector('[data-field="stone_charges"]')?.value) || plRowCellFloat(row, 'stone-amount') || 0;
            const otherCharges = parseFloat(row.querySelector('[data-field="other_charges"]')?.value) || plRowCellFloat(row, 'other-amount') || 0;
            const diamondValue = parseFloat(row.querySelector('[data-field="diamond_value"]')?.value) || plRowCellFloat(row, 'diamond-amount') || 0;
            const gemstoneValue = parseFloat(row.querySelector('[data-field="gemstone_value"]')?.value) || plRowCellFloat(row, 'gemstone-value') || 0;
            const rate = plRowCellFloat(row, 'rate');
            const metalValue = plRowCellFloat(row, 'metal-value');
            const discount = plRowCellFloat(row, 'discount');
            const makingAmount = plRowCellFloat(row, 'making-amount');
            const stoneAmount = plRowCellFloat(row, 'stone-amount');
            const otherAmount = plRowCellFloat(row, 'other-amount');
            const diamondAmount = plRowCellFloat(row, 'diamond-amount');
            let purchaseAmount = plRowCellFloat(row, 'purchase-amount');
            const saleAmount = plRowCellFloat(row, 'sale-amount');
            const saleAmountWith = plRowCellFloat(row, 'sale-amount-with');
            const reverse = plRowCellFloat(row, 'reverse');

            // Edit-load / hidden columns: fall back to data-group-items amounts
            if (!(amount > 0)) amount = plRowAmountFromGroup(row, 'amount') || plRowAmountFromGroup(row, 'net_amt');
            if (!(netAmt > 0)) netAmt = plRowAmountFromGroup(row, 'net_amt') || amount;
            if (!(netAmtTax > 0)) {
                netAmtTax = plRowAmountFromGroup(row, 'net_amt_tax')
                    || plRowAmountFromGroup(row, 'net_amt_with_tax')
                    || purchaseAmount
                    || amount
                    || netAmt;
            }
            if (!(purchaseAmount > 0)) purchaseAmount = plRowAmountFromGroup(row, 'purchase_amount') || amount || netAmt;
            
            totalQuantity += qty;
            totalGrossWt += grossWt;
            totalFinalWt += finalWt;
            totalNetWt += netWt;
            
            // Update footer totals for less weight and purity (if needed)
            const footerLessWt = document.getElementById('footerLessWt');
            if (footerLessWt) {
                const totalLessWt = Array.from(rows).reduce((sum, r) => {
                    return sum + (plRowCellFloat(r, 'less-wt') || parseFloat(r.querySelector('[data-field="less_wt"]')?.value || 0) || 0);
                }, 0);
                footerLessWt.textContent = totalLessWt.toFixed(3);
            }
            totalPureWt += parseFloat(pureWt) || 0;
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

        // Edit mode safety: if row sum is still 0 but we restored non-zero DB totals, keep those
        var savedEdit = window._piEditSavedTotals;
        var plRowsHadLineTotal = totalNetAmtTax > 0;
        if (window.isPurchaseInvoiceEditMode && savedEdit && rows.length > 0 && !plRowsHadLineTotal) {
            var savedSub = parseFloat(savedEdit.subtotal) || 0;
            var savedNet = parseFloat(savedEdit.net_total) || savedSub;
            if (savedNet > 0 || savedSub > 0) {
                totalNetAmtTax = savedNet > 0 ? savedNet : savedSub;
                if (!(totalAmount > 0)) totalAmount = savedSub || totalNetAmtTax;
                if (!(totalNetAmt > 0)) totalNetAmt = savedSub || totalNetAmtTax;
            }
        }        
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
        
        // Net Total = Total + Additional Amount (always in base currency)
        const netTotal = totalNetAmtTax + additionalAmt;
        const summaryNetTotal = document.getElementById('summaryNetTotal');
        if (summaryNetTotal) summaryNetTotal.textContent = netTotal.toFixed(2);

        // Grand Total (selected currency) = (Net Total - Discount + Round Off) × currency rate
        let grandTotalBase = netTotal - discountAmount;
        const roundOffCheck = document.getElementById('roundOff');
        const roundOffInput = document.getElementById('roundOffValue');
        const roundOffApplied = roundOffCheck && roundOffCheck.checked && roundOffInput ? (parseFloat(roundOffInput.value) || 0) : 0;
        grandTotalBase = grandTotalBase + roundOffApplied;
        let currencyRate = parseFloat(document.getElementById('currencyRate')?.value || 1);
        if (!isFinite(currencyRate) || currencyRate <= 0) {
            currencyRate = 1;
        }
        let grandTotal = grandTotalBase * currencyRate;
        const summaryGrandTotal = document.getElementById('summaryGrandTotal');
        if (summaryGrandTotal) {
            summaryGrandTotal.textContent = grandTotal.toFixed(2);
            summaryGrandTotal.setAttribute('data-base-amount', grandTotalBase.toFixed(2));
            summaryGrandTotal.setAttribute('data-currency-rate', String(currencyRate));
        }
        if (typeof window.auragoldUpdatePurchaseCurrencyLabels === 'function') {
            window.auragoldUpdatePurchaseCurrencyLabels();
        }
        
        // Calculate paid amounts from payment cards
        const paymentRows = document.querySelectorAll('#paymentTableBody .pos-payment-card');
        let paidAmt = 0;
        let paidCurrentOrderAmt = 0;
        let paidPreviousBalanceAmt = 0;
        
        paymentRows.forEach(function(row) {
            var prevBalAmt = parseFloat(String(row.getAttribute('data-previous-balance-amount') || '0').replace(/,/g, ''), 10);
            var currentOrderAmt = parseFloat(String(row.getAttribute('data-current-order-amount') || '0').replace(/,/g, ''), 10);
            if (isNaN(prevBalAmt)) prevBalAmt = 0;
            if (isNaN(currentOrderAmt)) currentOrderAmt = 0;
            var totalAmt = currentOrderAmt + prevBalAmt;
            paidAmt += totalAmt;
            paidCurrentOrderAmt += currentOrderAmt;
            paidPreviousBalanceAmt += prevBalAmt;
        });
        if (isNaN(paidAmt)) paidAmt = 0;
        if (isNaN(paidCurrentOrderAmt)) paidCurrentOrderAmt = 0;
        if (isNaN(paidPreviousBalanceAmt)) paidPreviousBalanceAmt = 0;
        
        // Do NOT auto-clear all payments when paid > grandTotal (e.g. user entered 2000 instead of 1000 in UPI).
        // Overpayment shows as negative Balance Amt (vendor credit/advance). save-purchase-invoice posts this to balance_amt
        // and ledger reduces running balance, so get-customer-balance shows it under Previous Balance on next invoice.
        
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
        
        // For purchase invoices: if remaining is negative (vendor has credit), treat as 0 for "amount to pay"
        const remainingForDisplay = remainingPreviousBalance < 0 ? 0 : remainingPreviousBalance;
        
        // Show original previous balance (e.g. -1000) so it matches Payment Voucher; do NOT overwrite with remaining
        // The "Previous Balance" display is the ledger balance; payment modals use remaining for "amount to pay"
        if (previousBalanceEl) {
            const currentDisplay = previousBalanceEl.getAttribute('data-original-balance');
            const val = (currentDisplay !== null && currentDisplay !== '') ? parseFloat(currentDisplay) : remainingForDisplay;
            formatPurchasePreviousBalanceAmount(previousBalanceEl, isNaN(val) ? 0 : val);
        }
        
        // Balance Amt: if "Use previous balance" is checked, deduct the entered amount from amount due
        const usePreviousBalanceCheck = document.getElementById('usePreviousBalanceCheck');
        const previousBalanceUseAmountEl = document.getElementById('previousBalanceUseAmount');
        const usePreviousBalance = usePreviousBalanceCheck && usePreviousBalanceCheck.checked;
        const amountUseFromPrevious = (usePreviousBalance && previousBalanceUseAmountEl) ? (parseFloat(previousBalanceUseAmountEl.value) || 0) : 0;
        // Amount due = Grand Total - Paid; then subtract how much we use from previous balance (when checked).
        // Negative = overpaid (credit); same value is saved as balance_amt and reflected in customer ledger.
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
                // Always recalculate: checkEmptyTable only updates when the table is fully empty
                updateSummaryPanel();
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
                            var modalRows = productListBody.querySelectorAll('tr.product-row');
                            var loadedRow = modalRows[modalRows.length - 1];
                            if (loadedRow && d.making_type) {
                                loadedRow.setAttribute('data-manual-making', '1');
                            }
                        });
                        if (typeof auragoldRestoreCaratSelectOnModalRow === 'function') {
                            var modalRows = productListBody.querySelectorAll('tr.product-row');
                            groupItems.forEach(function(d, idx) {
                                var r = modalRows[idx];
                                if (!r) return;
                                auragoldRestoreCaratSelectOnModalRow(r, {
                                    carat_id: d.carat_id || d.carat,
                                    carat: d.carat,
                                    carat_name: d.carat_name,
                                    purity: d.purity
                                });
                            });
                        }
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
                        
                        var storedGroup = null;
                        try {
                            var groupJson = row.getAttribute('data-group-items');
                            if (groupJson) {
                                var parsedGroup = JSON.parse(groupJson);
                                if (parsedGroup && parsedGroup.length > 0) storedGroup = parsedGroup[0];
                            }
                        } catch (e) {}
                        var restoredMakingType = (storedGroup && storedGroup.making_type) ? storedGroup.making_type : 'Fix';
                        if (typeof auragoldNormalizeMakingType === 'function') {
                            restoredMakingType = auragoldNormalizeMakingType(restoredMakingType);
                        }
                        var restoredMakingRate = (storedGroup && storedGroup.making_rate != null && storedGroup.making_rate !== '')
                            ? (parseFloat(storedGroup.making_rate) || 0)
                            : (rowData.making > 0 ? rowData.making : (data.product.making_rate || 0));

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
                            making_rate: restoredMakingRate,
                            making_type: restoredMakingType,
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
            tbody.innerHTML = '<tr class="no-drag"><td colspan="' + saleInvoiceProductListEmptyColspan() + '" class="text-center text-muted py-4" id="emptyRowCell">No Rows To Show</td></tr>';
            updateSummaryPanel();
        }
    }
    
    // formatPurchasePreviousBalance* / loadCustomerBalance: js/previous-balance-common.js (window.PB_PAGE_CONFIG)
    
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
        // Previous balance: previous-balance-common.js (window.PB_PAGE_CONFIG)
        
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
        // Use previous balance row: previous-balance-common.js + PB_PAGE_CONFIG.onAfterLoad
        // Use jQuery event delegation for better reliability
        $(document).on('click', '#addItemBtn, #addItemBtn a', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof window.saleInvoiceHasCustomerSelected === 'function' && !window.saleInvoiceHasCustomerSelected()) {
                alert('Please select a customer before adding items.');
                return;
            }
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
        if (e.shiftKey && (e.key === 'Q' || e.key === 'q')) {
            if (typeof window.saleInvoiceHasCustomerSelected === 'function' && !window.saleInvoiceHasCustomerSelected()) {
                e.preventDefault();
                alert('Please select a customer before adding items.');
                return;
            }
            e.preventDefault();
            currentEditingRowId = null;
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
    
    // updateJewelleryDiamondCaratFromDiamondAndGemstone, updateJewelleryNetAmountAndFinal, updateDiamondTabFcAmountAndLineMetalValue: product-modal-add-item-common.js (JewelStep-style rollups)

    function mapSaleOrderExcelProductToModalRow(p) {
        if (!p) p = {};
        var tpaths = p.temp_image_paths;
        if (!Array.isArray(tpaths)) tpaths = [];
        return {
            product_id: p.product_id,
            characteristic_id: p.characteristic_id,
            product_name: (p.product_name != null && String(p.product_name).trim() !== '') ? String(p.product_name).trim() : '',
            quantity: p.quantity,
            gross_wt: p.gross_weight,
            less_wt: p.less_weight,
            pkt_wt: p.pkt_wt,
            pkt_less_wt: p.pkt_less_wt,
            purity: p.purity,
            final_wt: p.final_weight,
            net_wt: p.net_weight,
            pure_wt: p.pure_weight,
            rate: p.rate,
            making_amount: p.making_amount,
            making_type: p.making_type,
            making_rate: p.making_rate,
            making_discount_amt: p.making_discount_amt,
            making_actual_value: p.making_actual_value,
            tax: p.tax_amount,
            design_no: p.design_no,
            stone_amount: p.stone_amount,
            stone_weight: p.stone_weight,
            other_amount: p.other_amount,
            diamond_amount: p.diamond_amount,
            amount: p.amount,
            net_amt: p.net_amount,
            net_amt_tax: p.net_amt_tax,
            metal_value: p.metal_value,
            discount: p.discount,
            purchase_amount: p.purchase_amount,
            sale_amount: p.sale_amount,
            sale_amount_with: p.sale_amount_with,
            reverse: p.reverse,
            stone_charges: p.stone_charges,
            other_charges: p.other_charges,
            diamond_value: p.diamond_value,
            gemstone_value: p.gemstone_value,
            barcode: (p.barcode != null && String(p.barcode).trim() !== '') ? String(p.barcode).trim() : '',
            from_excel: true,
            metal_id: p.metal_id,
            metal_name: p.metal_name,
            carat_id: (p.karat != null && String(p.karat).trim() !== '') ? String(p.karat).trim() : '',
            location_id: (p.location != null && String(p.location).trim() !== '') ? String(p.location).trim() : '',
            calculation_type: (p.calculation != null && String(p.calculation).trim() !== '') ? String(p.calculation).trim() : '',
            category: (p.category != null && String(p.category).trim() !== '') ? String(p.category).trim() : '',
            excelTempImagePaths: tpaths,
            excel_extra_columns: Array.isArray(p.excel_extra_columns) ? p.excel_extra_columns : []
        };
    }

    function saleOrderExcelProductToItemProduct(p) {
        var md = mapSaleOrderExcelProductToModalRow(p);
        if (typeof getItemAndProductFromModalRowData !== 'function') {
            return null;
        }
        var pair = getItemAndProductFromModalRowData(md);
        var item = pair.item;
        var product = pair.product;
        item.barcode = item.barcode || md.barcode || '';
        item.barcode_no = item.barcode_no || md.barcode || '';
        item.calculation = md.calculation_type || item.calculation;
        item.category = md.category || item.category;
        item.rfid_code = p.rfid_code || item.rfid_code || '';
        item.huid_no = p.huid_no || item.huid_no || '';
        item.gold_loss1 = p.gold_loss1;
        item.gold_loss2 = p.gold_loss2;
        item.wastage_per = p.wastage_per;
        item.wastage_weight = p.wastage_wt;
        item.metal_rate = p.metal_rate || item.metal_rate;
        item.setting_charge = p.setting_charge;
        if (p.metal_id) {
            item.metal_id = p.metal_id;
        }
        return { item: item, product: product };
    }
    window.saleOrderExcelProductToItemProduct = saleOrderExcelProductToItemProduct;

    function clearMainRowBarcodeForReassign(mainRow) {
        if (!mainRow) return;
        mainRow.setAttribute('data-barcode', '');
        var binp = mainRow.querySelector('[data-column="barcode"] input');
        if (binp) binp.value = '';
        var bsp = mainRow.querySelector('[data-column="barcode"] span');
        if (bsp) bsp.textContent = '';
        try {
            var gi = mainRow.getAttribute('data-group-items');
            if (gi) {
                var arr = JSON.parse(gi);
                if (Array.isArray(arr)) {
                    arr.forEach(function(x) { if (x) x.barcode = ''; });
                    mainRow.setAttribute('data-group-items', JSON.stringify(arr));
                }
            }
        } catch (e) {}
    }

    function commitProductSelectionModalToMainTable(opts) {
        opts = opts || {};
        var forceNewBarcodes = !!opts.forceNewBarcodes;
        var forceSeparateRows = !!opts.forceSeparateRows;
        var closeModal = !!opts.closeModal;
        var notifyEmpty = opts.notifyEmpty !== false;

        var allProductRows = document.querySelectorAll('#productListBody .product-row');
        if (allProductRows.length === 0) {
            if (notifyEmpty) alert('No products available to add');
            return { ok: false, reason: 'no_products' };
        }

        if (currentEditingRowId) {
            if (forceNewBarcodes) {
                alert('Excel lines are in the modal. Cancel or save the row you are editing, then click Add (Shift + A) to add them to the invoice.');
                return { ok: false, reason: 'edit_mode' };
            }
            if (typeof updateJewelleryDiamondCaratFromDiamondAndGemstone === 'function') updateJewelleryDiamondCaratFromDiamondAndGemstone();
            if (typeof updateJewelleryNetAmountAndFinal === 'function') updateJewelleryNetAmountAndFinal();
            var mainRowEd = document.getElementById(currentEditingRowId);
            var groupItemsJsonEd = mainRowEd ? mainRowEd.getAttribute('data-group-items') : null;
            if (groupItemsJsonEd) {
                var modalRowsDataEd = [];
                allProductRows.forEach(function(r) { modalRowsDataEd.push(getModalRowDataFromRow(r, true)); });
                if (modalRowsDataEd.length > 0) {
                    updateMergedRowFromModalRows(currentEditingRowId, modalRowsDataEd);
                }
            } else {
                var firstRowEd = allProductRows[0];
                if (firstRowEd) {
                    updateProductListRowFromModalRow(currentEditingRowId, firstRowEd);
                }
            }
            hideProductModal();
            currentEditingRowId = null;
            updateSummaryPanel();
            return { ok: true, reason: 'edit_saved', mainRowsAdded: 0 };
        }

        var productRows = Array.from(allProductRows).filter(function(row) {
            if (!row) return false;
            return row.style.display !== 'none';
        });
        if (productRows.length === 0) {
            if (notifyEmpty) {
                alert('No products in current tab. Switch to the tab with products you want to add, or add a product first.');
            }
            return { ok: false, reason: 'no_visible' };
        }

        if (typeof updateJewelleryDiamondCaratFromDiamondAndGemstone === 'function') updateJewelleryDiamondCaratFromDiamondAndGemstone();
        if (typeof updateJewelleryNetAmountAndFinal === 'function') updateJewelleryNetAmountAndFinal();

        var byMetal = {};
        var metalsTouched = {};
        var mainRowsAdded = 0;
        try {
            if (typeof window.calculateModalRowNetWeight === 'function') {
                productRows.forEach(function(r) { window.calculateModalRowNetWeight(r); });
            }
            if (forceSeparateRows) {
                var barcodeAssignPairs = [];
                productRows.forEach(function(row) {
                    var metalId = row.getAttribute('data-metal-id') || '';
                    metalsTouched[metalId] = true;
                    var d = getModalRowDataFromRow(row, false);
                    if (forceNewBarcodes) d.barcode = '';
                    var nBefore = document.querySelectorAll('#productTableBody tr:not(.no-drag)').length;
                    addProductToTableFromModalRow(d, metalId);
                    mainRowsAdded++;
                    if (forceNewBarcodes) {
                        var afterRows = document.querySelectorAll('#productTableBody tr:not(.no-drag)');
                        if (afterRows.length > nBefore) {
                            var newRow = afterRows[afterRows.length - 1];
                            clearMainRowBarcodeForReassign(newRow);
                            barcodeAssignPairs.push({ row: newRow, modalRowData: Object.assign({}, d, { barcode: '' }) });
                        }
                    }
                });
                if (forceNewBarcodes && barcodeAssignPairs.length && typeof window.assignUniqueBarcodesSequentialForProductListRows === 'function') {
                    window.assignUniqueBarcodesSequentialForProductListRows(barcodeAssignPairs);
                } else if (forceNewBarcodes && barcodeAssignPairs.length) {
                    barcodeAssignPairs.forEach(function(p) {
                        if (typeof window.assignUniqueBarcodeToProductListRow === 'function') {
                            window.assignUniqueBarcodeToProductListRow(p.row, p.modalRowData);
                        }
                    });
                }
            } else {
                productRows.forEach(function(row) {
                    var metalId = row.getAttribute('data-metal-id') || '';
                    if (!byMetal[metalId]) byMetal[metalId] = [];
                    byMetal[metalId].push(row);
                });
                Object.keys(byMetal).forEach(function(metalId) {
                    metalsTouched[metalId] = true;
                    var rows = byMetal[metalId];
                    var modalRowsData = [];
                    rows.forEach(function(r) {
                        var d = getModalRowDataFromRow(r, false);
                        if (forceNewBarcodes) d.barcode = '';
                        modalRowsData.push(d);
                    });
                    if (modalRowsData.length === 0) return;
                    var nBefore = document.querySelectorAll('#productTableBody tr:not(.no-drag)').length;
                    if (typeof auragoldAddModalRowsToProductTable === 'function') {
                        auragoldAddModalRowsToProductTable(modalRowsData, metalId);
                    } else if (typeof addMergedProductsToTable === 'function') {
                        addMergedProductsToTable(modalRowsData, metalId);
                    }
                    mainRowsAdded++;
                    if (forceNewBarcodes) {
                        var afterRows = document.querySelectorAll('#productTableBody tr:not(.no-drag)');
                        if (afterRows.length > nBefore) {
                            var newRow = afterRows[afterRows.length - 1];
                            clearMainRowBarcodeForReassign(newRow);
                            if (modalRowsData.length === 1 && typeof window.assignUniqueBarcodeToProductListRow === 'function') {
                                window.assignUniqueBarcodeToProductListRow(newRow, Object.assign({}, modalRowsData[0], { barcode: '' }));
                            } else if (modalRowsData.length > 1 && typeof window.assignUniqueBarcodesToMergedProductListRow === 'function') {
                                var cleared = modalRowsData.map(function(d) { return Object.assign({}, d, { barcode: '' }); });
                                window.assignUniqueBarcodesToMergedProductListRow(newRow, cleared);
                            }
                        }
                    }
                });
            }
        } finally {
            var productListBody = document.getElementById('productListBody');
            if (productListBody) {
                productListBody.innerHTML = '';
                // Keep modal open for continuous entry: add a fresh empty row after commit
                try {
                    if (typeof window.addEmptyProductRow === 'function') {
                        window.addEmptyProductRow();
                    } else if (typeof addEmptyProductRow === 'function') {
                        addEmptyProductRow();
                    } else {
                        productListBody.innerHTML = '<tr><td colspan="73" class="text-center text-muted py-4">Click "Add Product" button to add products for billing...</td></tr>';
                    }
                } catch (afterAddRowErr) {
                    console.error('Failed to add next empty row after commit:', afterAddRowErr);
                    productListBody.innerHTML = '<tr><td colspan="73" class="text-center text-muted py-4">Click "Add Product" button to add products for billing...</td></tr>';
                }
            }
            if (window.productModalGroupImageByTab && metalsTouched && typeof metalsTouched === 'object') {
                Object.keys(metalsTouched).forEach(function(metalId) {
                    delete window.productModalGroupImageByTab[String(metalId || '')];
                });
            }
            var groupNameInput = document.getElementById('modalGroupName');
            if (groupNameInput) groupNameInput.value = '';
            var commentInput = document.getElementById('modalComment');
            if (commentInput) commentInput.value = '';

            // Clear header barcode/code/qty for next entry
            try {
                if (typeof clearModalFields === 'function') {
                    clearModalFields();
                } else {
                    ['modalProductBarcode', 'modalProductCode', 'modalProductDesignNo', 'modalProductQty'].forEach(function(fid) {
                        var el = document.getElementById(fid);
                        if (el) el.value = '';
                    });
                }
            } catch (clearErr) { /* ignore */ }
        }
        updateSummaryPanel();
        if (closeModal && typeof hideProductModal === 'function') {
            hideProductModal();
        }
        return { ok: true, mainRowsAdded: mainRowsAdded };
    }
    window.commitProductSelectionModalToMainTable = commitProductSelectionModalToMainTable;

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
            commitProductSelectionModalToMainTable({ forceNewBarcodes: false, closeModal: false, notifyEmpty: true });
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
            var rowTr = cell.closest('tr');
            if (rowTr && rowTr.getAttribute('data-journal-photo') === '1') return;
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
            'modalGroupSingleItem': true,
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

    // Product Selection modal table (Basic Information) – column drag, mouse-based, order saved to DB
    (function() {
        var PAGE_MODAL_COLUMN_PREFS = 'purchase-invoice-product-modal';
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
            if (order.indexOf('actions') === -1 && table.querySelector('thead th[data-column="actions"]')) {
                order.push('actions');
            }
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
                if (k !== 'actions' && map[k]) row.appendChild(map[k]);
            });
            if (map['actions']) row.appendChild(map['actions']);
            row.insertBefore(checkbox, row.firstChild);
            if (typeof window.stampProductModalDataGroupOnCells === 'function') {
                window.stampProductModalDataGroupOnCells(row);
            }
        }
        window.reorderModalRowCellsToMatchHeader = reorderModalRowCellsToMatchHeader;

        function initProductSelectionModalColumnDrag() {
            var table = document.getElementById('productListTable');
            if (!table) return;
            var headerRow1 = table.querySelector('thead tr:first-child');
            var headerRow2 = table.querySelector('thead tr:nth-child(2)');
            var tbody = document.getElementById('productListBody');
            if (!headerRow1 || !headerRow2 || !tbody) return;
            var modalColDragCoreReady = headerRow2.getAttribute('data-column-drag-inited') === '1';
            if (!modalColDragCoreReady) {
                headerRow2.setAttribute('data-column-drag-inited', '1');
            }

            // Build column -> group map from DOM (which columns belong to which group)
            // Row 2 has no checkbox cell (checkbox has rowspan=2), so colIdx starts at 0.
            function buildGroupByColumnMap() {
                var groupByColKey = {};
                var columnGroups = window.PRODUCT_MODAL_COLUMN_GROUPS || {};
                headerRow2.querySelectorAll('th[data-column]').forEach(function(th) {
                    var colKey = th.getAttribute('data-column');
                    if (!colKey || colKey === 'actions') return;
                    Object.keys(columnGroups).forEach(function(gk) {
                        if ((columnGroups[gk] || []).indexOf(colKey) !== -1) {
                            groupByColKey[colKey] = gk;
                        }
                    });
                });
                return groupByColKey;
            }
            function getGroupForColumn(colKey) {
                var map = buildGroupByColumnMap();
                return map[colKey] || null;
            }
            // Return columns in group in DOM order (header row 2) so group drag preserves sub-column order.
            function getColumnsInGroupFromDOM(groupKey) {
                var groupByCol = buildGroupByColumnMap();
                var cols = [];
                var headers = headerRow2.querySelectorAll('th[data-column]');
                for (var i = 0; i < headers.length; i++) {
                    var k = headers[i].getAttribute('data-column');
                    if (k && k !== 'actions' && groupByCol[k] === groupKey) cols.push(k);
                }
                return cols;
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
                if (typeof window.syncProductModalColumnLayoutAfterToggle === 'function') {
                    window.syncProductModalColumnLayoutAfterToggle();
                } else if (typeof updateGroupHeaderVisibility === 'function') {
                    updateGroupHeaderVisibility();
                }
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
                setTimeout(function() { if (typeof window.clampProductModalScroll === 'function') window.clampProductModalScroll(); }, 0);
                if (typeof window.syncProductModalColumnLayoutAfterToggle === 'function') {
                    window.syncProductModalColumnLayoutAfterToggle();
                }
            }

            var modalDraggedTh = null;
            var modalDragIdx = null;
            var modalDropTh = null;
            var modalDropRight = false;

            function onModalColMove(e) {
                if (!modalDraggedTh || !headerRow2) return;
                if (typeof window.productModalColDragUi !== 'undefined' && window.productModalColDragUi.move) window.productModalColDragUi.move(e);
                var th = (typeof window.findProductModalRow2DropTh === 'function')
                    ? window.findProductModalRow2DropTh(headerRow2, e.clientX, e.clientY)
                    : (function() {
                        var el = document.elementFromPoint(e.clientX, e.clientY);
                        return el ? el.closest('#productListTable thead tr:nth-child(2) th[data-column]') : null;
                    })();
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
                if (typeof window.productModalColDragUi !== 'undefined' && window.productModalColDragUi.hide) window.productModalColDragUi.hide();
                if (modalDraggedTh) {
                    modalDraggedTh.classList.remove('modal-col-dragging');
                    modalDraggedTh = null;
                }
                modalDragIdx = null;
                modalDropTh = null;
                clearModalColHighlight();
                document.removeEventListener('mousemove', onModalColMove);
                document.removeEventListener('mouseup', onModalColUp, true);
                document.removeEventListener('pointerup', onModalColUp, true);
                document.removeEventListener('pointercancel', onModalColUp, true);
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
            }

            getModalColHeaders().forEach(function(th) {
                var colKey = th.getAttribute('data-column');
                if (colKey === 'actions') return;
                if (getGroupForColumn(colKey)) {
                    th.setAttribute('title', 'Drag to reorder within this group (use the move icon on the group title row above to move the whole group).');
                    th.classList.add('column-in-group');
                } else {
                    th.setAttribute('title', 'Drag to reorder columns');
                }
            });

            // ----- Group-level reorder (SortableJS on row-1 headers; sync row-2 + tbody) -----
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
                    setTimeout(function() { if (typeof window.clampProductModalScroll === 'function') window.clampProductModalScroll(); }, 0);
                }
            }

            if (!modalColDragCoreReady) {
                headerRow2.addEventListener('mousedown', function(e) {
                    if (e.button !== 0) return;
                    var th = e.target.closest('th[data-column]');
                    if (!th || !headerRow2.contains(th)) return;
                    if (th.getAttribute('data-column') === 'actions') return;
                    if (e.target.closest('.product-modal-col-drag-handle--locked')) return;
                    if (e.target.closest('input,button,select,textarea,a,.add-category-icon,.add-product-icon,.add-location-icon')) return;
                    e.preventDefault();
                    modalDraggedTh = th;
                    modalDragIdx = getModalColIndex(th);
                    th.classList.add('modal-col-dragging');
                    if (typeof window.productModalColDragUi !== 'undefined' && window.productModalColDragUi.show) window.productModalColDragUi.show(th, e);
                    document.body.style.cursor = 'grabbing';
                    document.body.style.userSelect = 'none';
                    document.addEventListener('mousemove', onModalColMove);
                    document.addEventListener('mouseup', onModalColUp, true);
                    document.addEventListener('pointerup', onModalColUp, true);
                    document.addEventListener('pointercancel', onModalColUp, true);
                });
            }

            function refreshProductModalGroupSortable() {
                if (typeof window.stampProductModalDataGroupOnCells === 'function') {
                    window.stampProductModalDataGroupOnCells();
                }
                if (headerRow1._productModalGroupSortable && typeof headerRow1._productModalGroupSortable.destroy === 'function') {
                    try { headerRow1._productModalGroupSortable.destroy(); } catch (eSort) {}
                    headerRow1._productModalGroupSortable = null;
                }
                if (typeof Sortable === 'undefined') return;
                headerRow1._productModalGroupSortable = new Sortable(headerRow1, {
                    animation: 150,
                    forceFallback: true,
                    fallbackOnBody: true,
                    draggable: 'th[data-group]:not([data-group-locked])',
                    handle: '.product-modal-group-drag-handle',
                    filter: 'input,button,select,textarea,a,.add-category-icon,.add-product-icon,.add-location-icon',
                    preventOnFilter: true,
                    ghostClass: 'product-modal-group-sortable-ghost',
                    dragClass: 'product-modal-group-sortable-drag-chosen',
                    onEnd: function() {
                        var order = [];
                        headerRow1.querySelectorAll('th[data-group]').forEach(function(cell) {
                            var g = cell.getAttribute('data-group');
                            if (g) order.push(g);
                        });
                        reorderModalColumnsByGroupOrder(order);
                        refreshProductModalGroupSortable();
                    }
                });
            }

            function loadAndApplyModalColumnOrder() {
                $.ajax({
                    url: 'ajax/get-column-preferences.php',
                    type: 'POST',
                    data: { page_name: PAGE_MODAL_COLUMN_PREFS, tab_key: TAB_MAIN },
                    dataType: 'json'
                }).done(function(res) {
                    if (res.status !== 'success' || !res.preferences || !res.preferences.length) {
                        refreshProductModalGroupSortable();
                        return;
                    }
                    var currentOrder = getModalCurrentColumnOrder();
                    var savedOrder = res.preferences.map(function(p) { return p.column_key; });
                    var merged = savedOrder.slice();
                    currentOrder.forEach(function(k) {
                        if (merged.indexOf(k) === -1) merged.push(k);
                    });
                    var sanitized = sanitizeColumnOrderToRespectGroups(merged);
                    applySavedModalColumnOrder(sanitized);
                    if (sanitized.join(',') !== merged.join(',')) saveModalColumnOrder();
                    setTimeout(function() { if (typeof window.clampProductModalScroll === 'function') window.clampProductModalScroll(); }, 0);
                    refreshProductModalGroupSortable();
                });
            }
            if (!modalColDragCoreReady) {
                loadAndApplyModalColumnOrder();
            }
            refreshProductModalGroupSortable();
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

    // Product Selection modal: fixed columns (Net Amt+Tax, Reverse, Action) stay on top via z-index and opaque background; no scroll clamping so user scroll is not fought
    window.clampProductModalScroll = function() {};

    // Product Selection modal: on Tab/focus, scroll so focused input is always visible and never behind fixed columns
    (function() {
        var FIXED_COLUMNS_WIDTH = 280;
        var PADDING = 8;
        var scrollWrapId = 'productListTableScrollWrapper';
        var tableId = 'productListTable';

        function scrollFocusedInputIntoView() {
            var scrollWrap = document.getElementById(scrollWrapId);
            var table = document.getElementById(tableId);
            var active = document.activeElement;
            if (!scrollWrap || !table || !active) return;
            if (!table.contains(active)) return;
            var tag = (active.tagName || '').toLowerCase();
            if (tag !== 'input' && tag !== 'select' && tag !== 'textarea') return;
            if (active.closest('td[data-column="net-amt-tax"], td[data-column="reverse"], td[data-column="actions"]')) return;

            requestAnimationFrame(function() {
                var scrollWrap = document.getElementById(scrollWrapId);
                if (!scrollWrap) return;
                var wrapRect = scrollWrap.getBoundingClientRect();
                var elRect = active.getBoundingClientRect();
                var visibleLeft = wrapRect.left + PADDING;
                var visibleRight = wrapRect.right - FIXED_COLUMNS_WIDTH - PADDING;
                var scrollBy = 0;
                if (elRect.left < visibleLeft) {
                    scrollBy = elRect.left - visibleLeft;
                } else if (elRect.right > visibleRight) {
                    scrollBy = elRect.right - visibleRight;
                }
                if (scrollBy !== 0) {
                    var newLeft = scrollWrap.scrollLeft + scrollBy;
                    scrollWrap.scrollLeft = Math.max(0, Math.min(newLeft, scrollWrap.scrollWidth - scrollWrap.clientWidth));
                }
            });
        }

        function initTabScrollIntoView() {
            var modal = document.getElementById('productSelectionModal');
            var scrollWrap = document.getElementById(scrollWrapId);
            if (!modal || !scrollWrap) return;
            modal.addEventListener('focusin', function(e) {
                var table = document.getElementById(tableId);
                if (table && table.contains(e.target)) scrollFocusedInputIntoView();
            });
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initTabScrollIntoView);
        } else {
            initTabScrollIntoView();
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
        if (typeof window.piSaveBlockedBySaleFixing !== 'undefined' && window.piSaveBlockedBySaleFixing) {
            alert('A sale fixing is linked to this invoice. Delete the sale fixing first, then you can save.');
            return;
        }
        // Prevent duplicate submissions
        if (isSaving) {
            console.log('Save already in progress, please wait...');
            return;
        }
        
        // Validate required fields
        const customerName = document.getElementById('customerName')?.value.trim();
        if (!customerName) {
            alert('Please enter vendor / supplier name');
            document.getElementById('customerName')?.focus();
            return;
        }
        
        const customerId = document.getElementById('customerId')?.value || '';
        if (!customerId || parseInt(customerId, 10) <= 0) {
            alert('Please select a vendor (supplier) from the search list');
            document.getElementById('customerName')?.focus();
            return;
        }
        
        // Set saving flag
        isSaving = true;
        
        // Get current order number from display
        const currentOrderNoText = document.getElementById('currentOrderNo')?.textContent || <?php echo json_encode(isset($next_order_no) ? $next_order_no : 'PI-1'); ?>;
        
        // Get order ID from URL or current order
        const urlParams = new URLSearchParams(window.location.search);
        const urlOrderId = urlParams.get('id');
        const currentOrderId = urlOrderId ? parseInt(urlOrderId) : <?php echo (int)($edit_order_id ?? 0); ?>;
        
        // Collect billing form data
        const orderData = {
            order_no: currentOrderNoText,
            order_id: currentOrderId,
            customer_id: customerId,
            customer_name: customerName,
            against_of: document.getElementById('againstOf')?.value || '',
            currency: document.getElementById('currency')?.value || 'AED',
            currency_rate: document.getElementById('currencyRate')?.value || '1',
            exchange_rate: document.getElementById('currencyRate')?.value || '1',
            ref_no: document.getElementById('refNo')?.value || '',
            sales_person: document.getElementById('salesPerson')?.value || '',
            order_date: document.getElementById('orderDate')?.value || <?php echo json_encode(date('Y-m-d')); ?>,
            due_date: (typeof window.auragoldGetDueDateValue === 'function' ? window.auragoldGetDueDateValue() : (document.getElementById('dueDate')?.value || '')),
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
        const pg = document.getElementById('previousBalanceGold');
        const ps = document.getElementById('previousBalanceSilver');
        const pd = document.getElementById('previousBalanceDiamond');
        const pgs = document.getElementById('previousBalanceGemstone');
        orderData.previous_gold = parseFloat(pg?.getAttribute('data-original-gold') || pg?.textContent || 0);
        orderData.previous_silver = parseFloat(ps?.getAttribute('data-original-silver') || ps?.textContent || 0);
        orderData.previous_diamond = parseFloat(pd?.getAttribute('data-original-diamond') || pd?.textContent || 0);
        orderData.previous_gemstone = parseFloat(pgs?.getAttribute('data-original-gemstone') || pgs?.textContent || 0);
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
        orderData.show_in_stock_journal = document.getElementById('showInStockJournal')?.checked ? 1 : 0;
        orderData.paid_amt = summaryPaidAmt;
        orderData.balance_amt = summaryBalanceAmt;
        
        // Collect product items (merged row: push one item per product in data-group-items so load shows all on edit)
        const items = [];
        let mergeGroupSeq = 0;
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
                            var lineQty = parseFloat(d.quantity) || 0;
                            var lineMetalQty = (d.metal_qty != null && d.metal_qty !== '') ? (parseFloat(d.metal_qty) || 1) : 1;
                            if (lineQty <= 0 && lineMetalQty > 0) lineQty = lineMetalQty;
                            var linePurchaseAmt = parseFloat(d.purchase_amount) || 0;
                            var lineAmount = parseFloat(d.amount) || 0;
                            var lineNetAmt = parseFloat(d.net_amt) || parseFloat(d.net_amount) || 0;
                            // Imitation / Watches: purchase amount is the typed value; keep amount/net_amount in sync for totals + stock journal.
                            if (lineAmount <= 0 && linePurchaseAmt > 0) lineAmount = linePurchaseAmt;
                            if (lineNetAmt <= 0 && lineAmount > 0) lineNetAmt = lineAmount;
                            if (linePurchaseAmt <= 0 && lineAmount > 0) linePurchaseAmt = lineAmount;
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
                                quantity: lineQty,
                                metal_qty: lineMetalQty,
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
                                amount: lineAmount,
                                net_amount: lineNetAmt,
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
                                purchase_amount: linePurchaseAmt,
                                sale_amount: parseFloat(d.sale_amount) || 0,
                                sale_amount_with: parseFloat(d.sale_amount_with) || 0,
                                purchase_amount_with: parseFloat(d.purchase_amount_with) || parseFloat(d.sale_amount_with) || 0,
                                reverse: parseFloat(d.reverse) || 0,
                                merge_group_index: mergeGroupSeq
                            });
                        });
                        mergeGroupSeq++;
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
                var savedMakingType = 'Fix';
                var savedMakingRate = makingAmount > 0 ? makingAmount : 0;
                try {
                    var groupJsonSave = row.getAttribute('data-group-items');
                    if (groupJsonSave) {
                        var groupSave = JSON.parse(groupJsonSave);
                        if (groupSave && groupSave.length > 0) {
                            var firstSaved = groupSave[0];
                            if (firstSaved.making_type) {
                                savedMakingType = (typeof auragoldNormalizeMakingType === 'function')
                                    ? auragoldNormalizeMakingType(firstSaved.making_type)
                                    : String(firstSaved.making_type).trim();
                            }
                            if (firstSaved.making_rate != null && firstSaved.making_rate !== '') {
                                savedMakingRate = parseFloat(firstSaved.making_rate) || savedMakingRate;
                            }
                        }
                    }
                } catch (e) {}
                const stoneAmount = parseFloat(row.querySelector('[data-column="stone-amount"]')?.textContent) || 0;
                const otherAmount = parseFloat(row.querySelector('[data-column="other-amount"]')?.textContent) || 0;
                const diamondAmount = parseFloat(row.querySelector('[data-column="diamond-amount"]')?.textContent) || 0;
                const purchaseAmount = parseFloat(row.querySelector('[data-column="purchase-amount"]')?.textContent) || 0;
                const saleAmount = parseFloat(row.querySelector('[data-column="sale-amount"] input')?.value || row.querySelector('[data-column="sale-amount"]')?.textContent) || 0;
                const saleAmountWith = parseFloat(row.querySelector('[data-column="sale-amount-with"] input')?.value || row.querySelector('[data-column="sale-amount-with"]')?.textContent) || 0;
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
                    making_type: savedMakingType,
                    making_rate: savedMakingRate,
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
                    purchase_amount_with: saleAmountWith,
                    reverse: reverse,
                    merge_group_index: mergeGroupSeq
                });
                mergeGroupSeq++;
            }
        });
        
        orderData.items = (typeof auragoldEnrichVoucherItemsExtraFields === 'function' ? auragoldEnrichVoucherItemsExtraFields(items) : items);
        
        if (currentOrderId <= 0) {
            var productLineCount = 0;
            items.forEach(function(it) {
                if (parseInt(it.product_id, 10) > 0) productLineCount++;
            });
            if (productLineCount === 0) {
                isSaving = false;
                alert('Please add at least one product line');
                return;
            }
        }

        // Hedging: backend uses making_amount_for_sale_fixing for sale fixing (grand_total - making); line items stay full (see save-purchase-invoice.php).
        // Do not zero making_amount or shrink totals here — that saved 0 making to DB and cleared the grid after reload.
        orderData.making_amount_for_sale_fixing = 0;
        var fixingTypeVal = (document.getElementById('fixingType') && document.getElementById('fixingType').value) ? document.getElementById('fixingType').value : 'Standard';
        if (fixingTypeVal === 'Hedging' && orderData.items && orderData.items.length > 0) {
            var totalMakingForSaleFixing = 0;
            orderData.items.forEach(function(item) {
                totalMakingForSaleFixing += parseFloat(item.making_amount) || parseFloat(item.making) || 0;
            });
            orderData.making_amount_for_sale_fixing = totalMakingForSaleFixing;
        }

                // Collect payments (cards + global `payments` via collectPosPaymentsForSave)
        orderData.payments = (typeof collectPosPaymentsForSave === 'function')
            ? collectPosPaymentsForSave()
            : [];
        if (typeof window.auragoldVoucherDiamondStoneAppendPendingToOrderData === 'function') {
            window.auragoldVoucherDiamondStoneAppendPendingToOrderData(orderData);
        }

        
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
            if (typeof window.auragoldVoucherDiamondStonePostDataShouldStringify === 'function' && window.auragoldVoucherDiamondStonePostDataShouldStringify(key)) {
                postData[key] = JSON.stringify(orderData[key]);
            } else {
                postData[key] = orderData[key];
            }
        });
        
        // Send to server using jQuery if available, otherwise fetch
        if (typeof jQuery !== 'undefined' && jQuery.ajax) {
            jQuery.ajax({
                url: 'ajax/save-purchase-invoice.php',
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
                        if (typeof window.auragoldVoucherDiamondStoneOnSaveSuccess === 'function') {
                            window.auragoldVoucherDiamondStoneOnSaveSuccess(response.invoice_id || response.order_id);
                        }
                        const invoiceId = response.invoice_id || response.order_id;
                        if (invoiceId) {
                            window.pendingRedirectUrl = 'purchase-invoice.php';
                            setTimeout(function() {
                                purchaseInvoiceAfterSavePrompts(invoiceId, response.invoice_no || response.order_no, items, response.new_barcodes || []);
                            }, 100);
                        } else {
                            alert('Purchase Invoice saved successfully! Invoice No: ' + (response.invoice_no || response.order_no || 'N/A'));
                            window.location.href = 'purchase-invoice.php';
                        }
                    } else {
                        alert('Error: ' + (response.message || 'Failed to save purchase invoice'));
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
            
            fetch('ajax/save-purchase-invoice.php', {
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
                    if (typeof window.auragoldVoucherDiamondStoneOnSaveSuccess === 'function') {
                        window.auragoldVoucherDiamondStoneOnSaveSuccess(data.invoice_id || data.order_id);
                    }
                    const invoiceId = data.invoice_id || data.order_id;
                    if (invoiceId) {
                        window.pendingRedirectUrl = 'purchase-invoice.php';
                        setTimeout(function() {
                            purchaseInvoiceAfterSavePrompts(invoiceId, data.invoice_no || data.order_no, items, data.new_barcodes || []);
                        }, 100);
                    } else {
                        alert('Purchase Invoice saved successfully! Invoice No: ' + (data.invoice_no || data.order_no || 'N/A'));
                        window.location.href = 'purchase-invoice.php';
                    }
                } else {
                    alert('Error: ' + (data.message || 'Failed to save purchase invoice'));
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
            window.location.href = 'purchase-invoice.php';
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
    
    // Function to print purchase invoice
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
        window.open('purchase-invoice-print.php?id=' + invoiceId, '_blank', 'width=1200,height=800');
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
        var getUrl = 'ajax/get-purchase-invoice.php?id=' + orderId + '&_=' + (Date.now ? Date.now() : '');
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
                        window.history.pushState({}, '', 'purchase-invoice.php?id=' + orderId);
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
                        window.history.pushState({}, '', 'purchase-invoice.php?id=' + orderId);
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
    
    function buildDiscountTypeSelectOptions(selectedVal) {
        var types = ['Fix', 'On Amount', 'On Making Amount', 'On Diamond Amount', 'On Stone Amount', 'On Net Amount'];
        var v = (selectedVal === undefined || selectedVal === null || selectedVal === '') ? 'Fix' : String(selectedVal);
        var opts = types.map(function(t) {
            return '<option value="' + t + '"' + (v === t ? ' selected' : '') + '>' + t + '</option>';
        }).join('');
        if (v === 'On Percentage') {
            opts += '<option value="On Percentage" selected>On Percentage</option>';
        }
        return opts;
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
            <td data-column="barcode"><input type="text" class="form-control form-control-sm" value="${escapeHtml(item.barcode || item.barcode_no || '')}" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="design-no"><input type="text" class="form-control form-control-sm" value="${escapeHtml(item.design_no || '')}" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="huid"><input type="text" class="form-control form-control-sm" value="${escapeHtml(item.huid_no || '')}" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="item-code"><input type="text" class="form-control form-control-sm" value="${escapeHtml(item.item_code != null && item.item_code !== '' ? item.item_code : (item.short_code || ''))}" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="category"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="">Select</option></select></td>
            <td data-column="product-category"><select class="form-control form-control-sm product-category-select" style="width: 120px; font-size: 0.7rem;"><option value="">Select Category</option></select></td>
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
            <td data-column="fc-amount"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.fc_amount != null && item.fc_amount !== '' ? item.fc_amount : 0).toFixed(2)}" step="0.01" style="width: 90px; font-size: 0.7rem;"></td>
            <td data-column="diamond-line-metal-value"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.diamond_line_metal_value != null && item.diamond_line_metal_value !== '' ? item.diamond_line_metal_value : 0).toFixed(2)}" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="rapnet-valuation"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.rapnet_valuation != null && item.rapnet_valuation !== '' ? item.rapnet_valuation : 0).toFixed(2)}" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="setting-charge"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.setting_charge || 0).toFixed(2)}" step="0.01" style="width: 110px; font-size: 0.7rem;"></td>
            <td data-column="stone-amount"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.stone_amount || 0).toFixed(2)}" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
            <td data-column="mark-up-amount"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.mark_up_amount != null && item.mark_up_amount !== '' ? item.mark_up_amount : 0).toFixed(2)}" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="mark-up-per"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.mark_up_per != null && item.mark_up_per !== '' ? item.mark_up_per : 0).toFixed(2)}" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
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
            <td data-column="final-wt"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.final_weight || 0).toFixed(3)}" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="alloy-wt"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.alloy_weight || 0).toFixed(3)}" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="platinum-weight"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.platinum_weight || 0).toFixed(3)}" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="platinum-karat"><input type="text" class="form-control form-control-sm" value="${escapeHtml(item.platinum_karat || '')}" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="platinum-purity"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.platinum_purity || 0).toFixed(2)}" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="platinum-purity-wt"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.platinum_purity_wt || 0).toFixed(3)}" step="0.001" style="width: 90px; font-size: 0.7rem;"></td>
            <td data-column="platinum-rate"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.platinum_rate || 0).toFixed(2)}" step="0.01" style="width: 90px; font-size: 0.7rem;"></td>
            <td data-column="platinum-wastage-per"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.platinum_wastage_per || 0).toFixed(2)}" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="platinum-wastage-wt"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.platinum_wastage_wt || 0).toFixed(3)}" step="0.001" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="platinum-amount"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.platinum_amount || 0).toFixed(2)}" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="discount-type"><select class="form-control form-control-sm" style="width: 150px; font-size: 0.7rem;">${buildDiscountTypeSelectOptions(item.discount_type)}</select></td>
            <td data-column="discount-per"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.discount_per || 0).toFixed(2)}" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="discount-amount"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.discount_amount || 0).toFixed(2)}" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="discount"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.discount || 0).toFixed(2)}" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
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
            <td data-column="stone-cost"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.stone_cost || 0).toFixed(2)}" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="diamond-amount"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.diamond_amount || 0).toFixed(2)}" step="0.01" style="width: 120px; font-size: 0.7rem;"></td>
            <td data-column="purchase-amount"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.purchase_amount || 0).toFixed(2)}" step="0.01" readonly style="width: 130px; font-size: 0.7rem;"></td>
                        <td data-column="sale-percent"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;" placeholder="%" title="Sale % on Purchase Amount"></td>            <td data-column="sale-amount"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.sale_amount || 0).toFixed(2)}" step="0.01" style="width: 110px; font-size: 0.7rem;"></td>
            <td data-column="sale-amount-with"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.sale_amount_with || 0).toFixed(2)}" step="0.01" style="width: 130px; font-size: 0.7rem;"></td>
            <td data-column="net-amt"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.net_amount || 0).toFixed(2)}" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="tax-type"><select class="form-control form-control-sm" style="width: 120px; font-size: 0.7rem;"><option value="tax_on_making">Tax on making</option><option value="tax_of_netamount" selected>Tax of net amount</option><option value="no_tax">No tax</option></select></td>
            <td data-column="tax-percent"><input type="text" class="form-control form-control-sm" value="${(product.total_tax_percent != null && product.total_tax_percent !== '') ? product.total_tax_percent : ((product.vat_value != null && product.vat_value !== '') ? product.vat_value : 0)}" min="0" max="100" step="0.01" readonly style="width: 70px; font-size: 0.7rem; background: #f1f5f9; cursor: not-allowed;" title="From product opening (sum of all taxes, read-only)"></td>
            <td data-column="tax"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.tax_amount || 0).toFixed(2)}" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="other-charge-type"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="Fix" ${(item.other_charge_type || 'Fix') === 'Fix' ? 'selected' : ''}>Fix</option><option value="Percentage" ${item.other_charge_type === 'Percentage' ? 'selected' : ''}>Percentage</option></select></td>
            <td data-column="other-weight"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.other_weight || 0).toFixed(3)}" step="0.001" style="width: 110px; font-size: 0.7rem;"></td>
            <td data-column="other-rate"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.other_rate || 0).toFixed(2)}" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="other-info"><input type="text" class="form-control form-control-sm" value="${escapeHtml(item.other_info || '')}" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="other-amount"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.other_amount || 0).toFixed(2)}" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
            <td data-column="certificate-amount"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.certificate_amount || 0).toFixed(2)}" step="0.01" style="width: 110px; font-size: 0.7rem;"></td>
            <td data-column="certificate-no"><input type="text" class="form-control form-control-sm" value="${escapeHtml(item.certificate_no || '')}" style="width: 110px; font-size: 0.7rem;"></td>
            <td data-column="certificate-link"><input type="text" class="form-control form-control-sm" value="${escapeHtml(item.certificate_link || '')}" style="width: 120px; font-size: 0.7rem;" placeholder="https://"></td>
            <td data-column="video-link"><input type="text" class="form-control form-control-sm" value="${escapeHtml(item.video_link || '')}" style="width: 120px; font-size: 0.7rem;" placeholder="https://"></td>
            <td data-column="cut"><select class="form-control form-control-sm auragold-spec-select" data-auragold-spec="cut" style="min-width: 100px; font-size: 0.7rem;"><option value="">Select Cut</option></select></td>
            <td data-column="color"><select class="form-control form-control-sm auragold-spec-select" data-auragold-spec="color" style="min-width: 100px; font-size: 0.7rem;"><option value="">Select Color</option></select></td>
            <td data-column="seive-size"><select class="form-control form-control-sm auragold-spec-select" data-auragold-spec="seive" style="min-width: 100px; font-size: 0.7rem;"><option value="">Select Seive</option></select></td>
            <td data-column="size"><select class="form-control form-control-sm auragold-spec-select" data-auragold-spec="size" style="min-width: 100px; font-size: 0.7rem;"><option value="">Select Size</option></select></td>
            <td data-column="shape"><select class="form-control form-control-sm auragold-spec-select" data-auragold-spec="shape" style="min-width: 100px; font-size: 0.7rem;"><option value="">Select Shape</option></select></td>
            <td data-column="clarity"><select class="form-control form-control-sm auragold-spec-select" data-auragold-spec="clarity" style="min-width: 100px; font-size: 0.7rem;"><option value="">Select Clarity</option></select></td>
            <td data-column="unit-price"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.unit_price != null && item.unit_price !== '' ? item.unit_price : 0).toFixed(2)}" step="0.01" style="width: 90px; font-size: 0.7rem;"></td>
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

        var makingTypeVal = (typeof auragoldNormalizeMakingType === 'function')
            ? auragoldNormalizeMakingType(item.making_type)
            : (item.making_type || 'Fix');
        if (item.making_type && typeof setModalSelectIfOptionExists === 'function') {
            setModalSelectIfOptionExists(row, 'making-type', makingTypeVal);
            row.setAttribute('data-manual-making', '1');
        }
        
        // Populate dropdowns (location, carat, etc.)
        if (typeof auragoldRestoreCaratSelectOnModalRow === 'function') {
            auragoldRestoreCaratSelectOnModalRow(row, {
                carat_id: item.carat_id,
                carat: item.carat,
                carat_name: item.carat_name,
                purity: item.purity
            });
        } else {
            const caratSelect = row.querySelector('.carat-select');
            if (caratSelect && typeof populateSelect === 'function') {
                if (typeof populateCaratSelectForModalRow === 'function') populateCaratSelectForModalRow(caratSelect, row);
                else populateSelect(caratSelect, carats, 'id', 'name', 'Select Karat');
                if (item.carat_id) {
                    caratSelect.value = item.carat_id;
                }
            }
        }
        
        const locationSelect = row.querySelector('.location-select');
        if (locationSelect && typeof populateSelect === 'function') {
            populateSelect(locationSelect, locations, 'id', 'name', 'Select Location');
            if (item.location_id) {
                locationSelect.value = item.location_id;
            }
        }
        const productCategorySelect = row.querySelector('.product-category-select');
        if (productCategorySelect && typeof populateSelect === 'function' && typeof categories !== 'undefined') {
            populateSelect(productCategorySelect, categories, 'id', 'name', 'Select Category');
            var pcid = item.product_category_id != null && item.product_category_id !== '' ? item.product_category_id : (item.category_id != null && item.category_id !== '' && !item.diamond_category ? item.category_id : '');
            if (pcid) {
                try { productCategorySelect.value = String(pcid); } catch (e) {}
            }
        }
        if (typeof auragoldPopulateModalSpecSelectsForRow === 'function') {
            auragoldPopulateModalSpecSelectsForRow(row);
            var specSet = function(col, id) {
                if (id == null || id === '') return;
                var c = row.querySelector('[data-column="' + col + '"] select');
                if (c) try { c.value = String(id); } catch (e2) {}
            };
            specSet('cut', item.cut_id);
            specSet('color', item.color_id);
            specSet('shape', item.shape_id);
            specSet('clarity', item.clarity_id);
            specSet('seive-size', item.sieve_size_id);
            specSet('size', item.size_id);
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
        if (typeof updateJewelleryDiamondCaratFromDiamondAndGemstone === 'function') {
            updateJewelleryDiamondCaratFromDiamondAndGemstone();
        }
        if (typeof syncDiamondTabSharedBarcodes === 'function') {
            syncDiamondTabSharedBarcodes();
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
    
    /**
     * Split flat tbl_purchase_invoice_items rows back into one array per Product List row (merged modal lines stay together).
     * Uses merge_group_index when saved; otherwise Jewellery-boundary heuristic for old invoices with diamond lines.
     */
    function partitionPurchaseInvoiceItemsForProductRows(itemList) {
        if (!itemList || itemList.length === 0) return [];
        var hasIdx = itemList.every(function(it) {
            return it.merge_group_index != null && it.merge_group_index !== '';
        });
        if (hasIdx) {
            var map = {};
            itemList.forEach(function(it) {
                var k = parseInt(it.merge_group_index, 10);
                if (isNaN(k)) k = 0;
                if (!map[k]) map[k] = [];
                map[k].push(it);
            });
            return Object.keys(map).sort(function(a, b) { return parseInt(a, 10) - parseInt(b, 10); }).map(function(key) {
                return map[key];
            });
        }
        var hasDiamondLine = itemList.some(function(it) {
            var c = String(it.diamond_category || it.category || '').trim();
            return c === 'Diamonds' || c === 'GemStones';
        });
        if (!hasDiamondLine) {
            return itemList.map(function(it) { return [it]; });
        }
        var groups = [];
        var current = [];
        itemList.forEach(function(item) {
            var cat = String(item.diamond_category || item.category || '').trim();
            if (cat === 'Jewellery' && current.length > 0) {
                groups.push(current);
                current = [item];
            } else {
                current.push(item);
            }
        });
        if (current.length) groups.push(current);
        return groups;
    }

    // Populate form with order data (param loadedPayments to avoid shadowing global payments)
    function populateOrderForm(order, items, loadedPayments) {
        console.log('populateOrderForm executing', { orderId: order && order.id, itemsCount: (items && items.length) || 0, paymentsCount: (loadedPayments && loadedPayments.length) || 0 });

        if (typeof window.auragoldVoucherDiamondStonePopulateFromOrder === 'function') {
            window.auragoldVoucherDiamondStonePopulateFromOrder(order);
        }
        
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
        if (typeof setAuragoldPartyValue === 'function') {
            setAuragoldPartyValue(
                order.supplier_id || order.customer_id || '',
                order.supplier_name || order.customer_name || ''
            );
        } else {
            if (document.getElementById('customerName')) {
                document.getElementById('customerName').value = order.supplier_name || order.customer_name || '';
            }
            if (document.getElementById('customerId')) {
                document.getElementById('customerId').value = order.supplier_id || order.customer_id || '';
            }
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
        if (document.getElementById('currencyRate')) {
            var er = order.exchange_rate != null && order.exchange_rate !== ''
                ? order.exchange_rate
                : (order.currency_rate != null && order.currency_rate !== '' ? order.currency_rate : '');
            if (er !== '' && er != null) {
                document.getElementById('currencyRate').value = er;
                document.getElementById('currencyRate').dataset.userEdited = '1';
            } else if (typeof window.auragoldApplyPurchaseCurrencyRate === 'function') {
                window.auragoldApplyPurchaseCurrencyRate(true);
            }
        }
        if (document.getElementById('refNo')) {
            document.getElementById('refNo').value = order.ref_no || '';
        }
        (function setSalesPersonField() {
            var sel = document.getElementById('salesPerson');
            if (!sel) return;
            var sp = String(order.purchase_person || order.sales_person || '').trim();
            if (sp) {
                var found = false;
                for (var si = 0; si < sel.options.length; si++) {
                    if (sel.options[si].value === sp) { found = true; break; }
                }
                if (!found) {
                    var opt = document.createElement('option');
                    opt.value = sp;
                    opt.textContent = sp;
                    sel.appendChild(opt);
                }
            }
            sel.value = sp;
        })();
        if (document.getElementById('orderDate')) {
            var rawDate = order.invoice_date || order.order_date || '';
            // Normalize to Y-m-d for type="date" (e.g. "2026-02-17 00:00:00" -> "2026-02-17")
            if (rawDate && rawDate.length >= 10) rawDate = rawDate.substring(0, 10);
            document.getElementById('orderDate').value = rawDate || '';
        }
        if (document.getElementById('dueDate')) {
            var rawDue = order.due_date || '';
            if (rawDue && String(rawDue).length >= 10) rawDue = String(rawDue).substring(0, 10);
            document.getElementById('dueDate').value = (typeof window.auragoldNormalizeDueDate === 'function' ? window.auragoldNormalizeDueDate(rawDue) : (rawDue || ''));
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
        if (prevBalanceAmtEl) formatPurchasePreviousBalanceAmount(prevBalanceAmtEl, prevAmt);
        if (prevBalanceGoldEl) formatPurchasePreviousBalanceMetal(prevBalanceGoldEl, prevGold, 3, 'data-original-gold');
        if (prevBalanceSilverEl) formatPurchasePreviousBalanceMetal(prevBalanceSilverEl, prevSilver, 3, 'data-original-silver');
        if (prevBalanceDiamondEl) formatPurchasePreviousBalanceMetal(prevBalanceDiamondEl, prevDiamond, 3, 'data-original-diamond');
        if (prevBalanceGemstoneEl) formatPurchasePreviousBalanceMetal(prevBalanceGemstoneEl, prevGemstone, 3, 'data-original-gemstone');
        
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
        
        // Load items: one Product List row per merge group (each group may have multiple modal lines in data-group-items)
        var itemList = (items && Array.isArray(items)) ? items.filter(Boolean) : [];
        if (itemList.length > 0) {
            console.log('Found ' + itemList.length + ' items to load');
            try {
                var rowGroups = partitionPurchaseInvoiceItemsForProductRows(itemList);
                var added = false;
                rowGroups.forEach(function(groupItems) {
                    const modalRowsData = groupItems.map(function(item) { return savedItemToModalRowData(item); });
                    if (typeof addMergedProductsToTable === 'function') {
                        addMergedProductsToTable(modalRowsData, undefined, { fromSavedInvoice: true });
                    } else if (typeof addProductToTableFromModalRow === 'function') {
                        modalRowsData.forEach(function(rowData) { addProductToTableFromModalRow(rowData, undefined, { fromSavedInvoice: true }); });
                    }
                });
                added = productTableBody && productTableBody.querySelectorAll('tr:not(.no-drag)').length > 0;
                if (!added && typeof addProductToTableFromModalRow === 'function') {
                    itemList.forEach(function(item) { addProductToTableFromModalRow(savedItemToModalRowData(item), undefined, { fromSavedInvoice: true }); });
                    added = productTableBody && productTableBody.querySelectorAll('tr:not(.no-drag)').length > 0;
                }
                if (!added) {
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
                    productTableBody.innerHTML = '<tr class="no-drag"><td colspan="' + saleInvoiceProductListEmptyColspan() + '" class="text-center text-muted py-4" id="emptyRowCell">No Rows To Show</td></tr>';
                }
            }
        } else {
            // Show empty message
            if (productTableBody) {
                productTableBody.innerHTML = '<tr class="no-drag"><td colspan="' + saleInvoiceProductListEmptyColspan() + '" class="text-center text-muted py-4" id="emptyRowCell">No Rows To Show</td></tr>';
            }
            if (productListBody) {
                productListBody.innerHTML = '<tr><td colspan="73" class="text-center text-muted py-4">Click "Add Product" button to add products for billing...</td></tr>';
            }
        }
        
        // Clear existing payments and global payments array so Edit finds correct list
        const paymentTableBody = document.getElementById('paymentTableBody');
        if (paymentTableBody) {
            paymentTableBody.innerHTML = '';
        }
        var _pfClearPayments = document.getElementById('paymentTableFooter');
        if (_pfClearPayments) _pfClearPayments.style.display = 'none';
        if (typeof payments !== 'undefined') payments.length = 0;
        
        // Add payments to table (and to global payments array via addPaymentToTable)
        if (loadedPayments && loadedPayments.length > 0) {
            loadedPayments.forEach(function(paymentRow) {
                // Scrap / metal-exchange modal fields are stored in payment_details JSON on save, not as DB columns
                var payment = paymentRow;
                if (paymentRow.payment_details) {
                    var pd = paymentRow.payment_details;
                    if (typeof pd === 'string' && pd.trim() !== '') {
                        try { pd = JSON.parse(pd); } catch (e) { pd = null; }
                    }
                    if (pd && typeof pd === 'object') {
                        var dbPayId = paymentRow.id;
                        payment = Object.assign({}, paymentRow, pd);
                        payment.id = dbPayId;
                    }
                }
                // Map payment type from database to modal type
                var rawPayType = payment.payment_type != null && String(payment.payment_type) !== '' ? payment.payment_type : (payment.type || '');
                let paymentType = String(rawPayType).toLowerCase();
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
                    amount: (function() {
                        var co = payment.current_order_amount;
                        if (co != null && co !== '' && !isNaN(parseFloat(co))) return parseFloat(co);
                        return parseFloat(payment.amount || 0);
                    })(),
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
                if (paymentType === 'metal-exchange') {
                    paymentData.metal_exchange_metal_id = payment.metal_exchange_metal_id || payment.metal_id || payment.scrap_metal_id || '';
                    paymentData.metal_exchange_product_id = payment.metal_exchange_product_id || payment.product_id || payment.scrap_product_id || '';
                    paymentData.metal_exchange_product_name = payment.metal_exchange_product_name || payment.product_name || payment.scrap_product_name || '';
                    paymentData.metal_exchange_gross_wt = payment.metal_exchange_gross_wt != null && payment.metal_exchange_gross_wt !== '' ? String(payment.metal_exchange_gross_wt) : (payment.gross_weight != null ? String(payment.gross_weight) : '0');
                    paymentData.metal_exchange_item_code = payment.metal_exchange_item_code || payment.item_code || '';
                    paymentData.metal_exchange_rate = payment.metal_exchange_rate != null && payment.metal_exchange_rate !== '' ? String(payment.metal_exchange_rate) : (payment.rate != null ? String(payment.rate) : '0');
                    paymentData.metal_exchange_purity_wt = payment.metal_exchange_purity_wt != null && payment.metal_exchange_purity_wt !== '' ? String(payment.metal_exchange_purity_wt) : (payment.purity_weight != null ? String(payment.purity_weight) : '0');
                }
                addPaymentToTable(paymentData);
            });
        } else {
            // Show empty message
            if (paymentTableBody) {
                paymentTableBody.innerHTML = '<div class="no-payment-row pos-payment-empty w-100 text-center text-muted py-3">No payment entries</div>';
            }
            var _pfEmptyPayments = document.getElementById('paymentTableFooter');
            if (_pfEmptyPayments) _pfEmptyPayments.style.display = 'none';
        }
        
        // Update summary panel with saved values from database (so edit mode shows exact saved totals and balance)
        const savedSubtotal = parseFloat(order.subtotal || 0);
        const savedGrandTotal = parseFloat(order.grand_total || 0);
        const savedPaidAmt = parseFloat(order.paid_amt || 0);
        const savedBalanceAmt = parseFloat(order.balance_amt || 0);
        const savedNetTotal = parseFloat(order.net_total || order.subtotal || 0);
        window._piEditSavedTotals = {
            subtotal: savedSubtotal,
            net_total: savedNetTotal,
            grand_total: savedGrandTotal,
            paid_amt: savedPaidAmt,
            balance_amt: savedBalanceAmt,
            metal_amt: parseFloat(order.metal_amt || 0),
            discount_amt: parseFloat(order.discount_amt || 0),
            round_off: parseFloat(order.round_off || 0)
        };
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
        const showInStockJournalCheckbox = document.getElementById('showInStockJournal');
        if (showInStockJournalCheckbox) {
            showInStockJournalCheckbox.checked = !!(parseInt(order.show_in_stock_journal, 10) || order.show_in_stock_journal === true);
        }
        // Restore discount values
        const savedDiscountAmt = parseFloat(order.discount_amt || 0);
        const savedDiscountPercent = parseFloat(order.discount_percent || 0);
        if (document.getElementById('discountAmount')) document.getElementById('discountAmount').value = savedDiscountAmt.toFixed(2);
        if (document.getElementById('discountPercent')) document.getElementById('discountPercent').value = savedDiscountPercent;
        if (document.getElementById('summaryNetTotal')) {
            document.getElementById('summaryNetTotal').textContent = savedNetTotal.toFixed(2);
        }
        
        // Restore "Use previous balance" and "Amount to use" in edit mode (so 7875 total, 7375 paid, 500 used shows correctly)
        const usePrevChkEl = document.getElementById('usePreviousBalanceCheck');
        const useAmountInputEl = document.getElementById('previousBalanceUseAmount');
        const useAmountRowEl = document.getElementById('previousBalanceUseAmountRow');
        const previousBalanceUsedAmt = parseFloat(order.previous_balance_used_amt || 0) || Math.max(0, savedGrandTotal - savedPaidAmt - savedBalanceAmt);
        if (previousBalanceUsedAmt > 0 && usePrevChkEl && useAmountInputEl) {
            usePrevChkEl.checked = true;
            useAmountInputEl.value = previousBalanceUsedAmt.toFixed(2);
            if (useAmountRowEl) useAmountRowEl.classList.add('is-open');
        } else if (usePrevChkEl) {
            usePrevChkEl.checked = false;
            if (useAmountInputEl) useAmountInputEl.value = '0.00';
            if (useAmountRowEl) useAmountRowEl.classList.remove('is-open');
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

        // Re-apply saved totals after any deferred recalcs (currency labels, party widgets, etc.)
        function reapplyPiEditSavedTotals() {
            var s = window._piEditSavedTotals;
            if (!s) return;
            if (document.getElementById('summaryTotal') && (parseFloat(s.subtotal) || 0) > 0) {
                document.getElementById('summaryTotal').textContent = (parseFloat(s.subtotal) || 0).toFixed(2);
            }
            if (document.getElementById('summaryNetTotal') && (parseFloat(s.net_total) || parseFloat(s.subtotal) || 0) > 0) {
                document.getElementById('summaryNetTotal').textContent = (parseFloat(s.net_total) || parseFloat(s.subtotal) || 0).toFixed(2);
            }
            if (document.getElementById('summaryGrandTotal') && (parseFloat(s.grand_total) || 0) > 0) {
                document.getElementById('summaryGrandTotal').textContent = (parseFloat(s.grand_total) || 0).toFixed(2);
            }
            if (document.getElementById('summaryPaidAmt')) {
                document.getElementById('summaryPaidAmt').textContent = (parseFloat(s.paid_amt) || 0).toFixed(2);
            }
            if (document.getElementById('summaryBalanceAmt')) {
                document.getElementById('summaryBalanceAmt').textContent = (parseFloat(s.balance_amt) || 0).toFixed(2);
            }
            if (document.getElementById('summaryMetalAmt')) {
                document.getElementById('summaryMetalAmt').textContent = (parseFloat(s.metal_amt) || 0).toFixed(2);
            }
            if (typeof window.auragoldUpdatePurchaseCurrencyLabels === 'function') {
                window.auragoldUpdatePurchaseCurrencyLabels();
            }
        }
        reapplyPiEditSavedTotals();
        setTimeout(reapplyPiEditSavedTotals, 0);
        setTimeout(function () {
            if (typeof updateSummaryPanel === 'function') updateSummaryPanel();
            reapplyPiEditSavedTotals();
        }, 250);
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
                if (window.history && window.history.replaceState) window.history.replaceState({}, '', 'purchase-invoice.php?id=' + editId);
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
            var hasRows = tbody && tbody.querySelectorAll('tr:not(.no-drag)').length > 0;
            if (!hasRows && window.EDIT_ORDER_DATA && window.EDIT_ORDER_DATA.order) {
                doPopulateFromEmbed();
            }
        }, 1200);
    })();
    <?php endif; ?>
    
    // ================== PAYMENT FUNCTIONALITY ==================
    
    let paymentRowIndex = 0;
    // Must be `var` so window.payments exists — collectPosPaymentsForSave() (auragold-payment-cards.js) reads global.payments
    var payments = [];
    
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
            var meMetal = document.getElementById('metalExchangeMetal');
            var meProdIn = document.getElementById('metalExchangeProductInput');
            var meProdId = document.getElementById('metalExchangeProductId');
            paymentData.metal_exchange_metal_id = (meMetal && meMetal.value) ? meMetal.value : '';
            paymentData.metal_exchange_product_id = (meProdId && meProdId.value) ? meProdId.value : '';
            paymentData.metal_exchange_product_name = (meProdIn && meProdIn.value) ? meProdIn.value : '';
            var meGw = document.getElementById('metalExchangeGrossWt');
            var meIc = document.getElementById('metalExchangeItemCode');
            var meRt = document.getElementById('metalExchangeRate');
            var mePw = document.getElementById('metalExchangePurityWt');
            paymentData.metal_exchange_gross_wt = meGw ? meGw.value : '0';
            paymentData.metal_exchange_item_code = meIc ? meIc.value : '';
            paymentData.metal_exchange_rate = meRt ? meRt.value : '0';
            paymentData.metal_exchange_purity_wt = mePw ? mePw.value : '0';
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
        
        if (paymentData.amount < 0 || !isFinite(paymentData.amount)) {
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
                if (type === 'metal-exchange' && paymentData.metal_exchange_metal_id !== undefined) {
                    existingPayment.purity_carat = paymentData.purity_carat;
                    existingPayment.quantity = paymentData.quantity;
                    existingPayment.metal_exchange_metal_id = paymentData.metal_exchange_metal_id;
                    existingPayment.metal_exchange_product_id = paymentData.metal_exchange_product_id;
                    existingPayment.metal_exchange_product_name = paymentData.metal_exchange_product_name;
                    existingPayment.metal_exchange_gross_wt = paymentData.metal_exchange_gross_wt;
                    existingPayment.metal_exchange_item_code = paymentData.metal_exchange_item_code;
                    existingPayment.metal_exchange_rate = paymentData.metal_exchange_rate;
                    existingPayment.metal_exchange_purity_wt = paymentData.metal_exchange_purity_wt;
                }
                const totalDisplay = paymentData.amount + (parseFloat(existingPayment.previous_balance_amount) || 0);
                const row = document.getElementById(window._editingPaymentId);
                if (row) {
                    row.setAttribute('data-current-order-amount', paymentData.amount.toFixed(2));
                    const amountTd = row.querySelector('[data-payment-amount]');
                    if (amountTd) amountTd.textContent = totalDisplay.toFixed(2);
                    const qtyTd = row.querySelector('td:nth-child(8)');
                    if (qtyTd && (type === 'scrap' || type === 'metal-exchange')) {
                        qtyTd.textContent = (parseFloat(paymentData.quantity) || 0).toFixed(2);
                    }
                    if (type === 'scrap' || type === 'metal-exchange') {
                        try {
                            row.setAttribute('data-payment-payload', encodeURIComponent(JSON.stringify(existingPayment)));
                        } catch (ePl) {}
                    }
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
    
    // Add payment as card (shared UI: assets/js/auragold-payment-cards.js)
    function addPaymentToTable(payment) {
        const tbody = document.getElementById('paymentTableBody');
        if (!tbody) {
            console.error('Payment list (#paymentTableBody) not found.');
            return;
        }
        const noPaymentRow = tbody.querySelector('.no-payment-row');
        if (noPaymentRow) {
            noPaymentRow.remove();
        }
        if (typeof payments !== 'undefined' && payments.indexOf(payment) === -1) payments.push(payment);
        var buildFn = typeof buildPosPaymentCardElement === 'function' ? buildPosPaymentCardElement : (typeof AuragoldPaymentCards !== 'undefined' ? AuragoldPaymentCards.buildCardElement : null);
        if (typeof buildFn !== 'function') {
            console.error('auragold-payment-cards.js not loaded');
            return;
        }
        tbody.appendChild(buildFn(payment));
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
            const rows = tbody.querySelectorAll('.pos-payment-card');
            if (rows.length === 0) {
                tbody.innerHTML = '<div class="no-payment-row pos-payment-empty w-100 text-center text-muted py-3">No payment entries</div>';
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
            const row = document.getElementById(paymentId);
            if (row && row.classList && row.classList.contains('pos-payment-card')) {
                const type = (row.getAttribute('data-payment-type') || 'cash').trim();
                const depositInto = (row.getAttribute('data-deposit-into') || '').trim();
                const transactionNo = (row.getAttribute('data-transaction-no') || '').trim();
                const amount = parseFloat(row.getAttribute('data-current-order-amount') || 0) || 0;
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
                    var purityVal = (row.getAttribute('data-purity-carat') || '').trim();
                    var scrapPurityEl = document.getElementById('scrapPurity');
                    if (scrapPurityEl && purityVal) scrapPurityEl.value = purityVal;
                    var qtyVal = parseFloat(row.getAttribute('data-quantity') || '1');
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
            } else if (payment.type === 'metal-exchange') {
                var mem = document.getElementById('metalExchangeMetal');
                if (mem && payment.metal_exchange_metal_id) mem.value = String(payment.metal_exchange_metal_id);
                var mepi = document.getElementById('metalExchangeProductInput');
                if (mepi && payment.metal_exchange_product_name) mepi.value = payment.metal_exchange_product_name;
                var mepid = document.getElementById('metalExchangeProductId');
                if (mepid && payment.metal_exchange_product_id) mepid.value = String(payment.metal_exchange_product_id);
                var meq = document.getElementById('metalExchangeQty');
                if (meq) meq.value = (payment.quantity != null && payment.quantity !== '') ? payment.quantity : '1';
                var mep = document.getElementById('metalExchangePurity');
                if (mep) mep.value = (payment.purity_carat != null && payment.purity_carat !== '') ? payment.purity_carat : '1';
                var mer = document.getElementById('metalExchangeRate');
                if (mer) mer.value = (payment.metal_exchange_rate != null && payment.metal_exchange_rate !== '') ? payment.metal_exchange_rate : '0';
                var meg = document.getElementById('metalExchangeGrossWt');
                if (meg) meg.value = (payment.metal_exchange_gross_wt != null && payment.metal_exchange_gross_wt !== '') ? payment.metal_exchange_gross_wt : '0';
                var mepw = document.getElementById('metalExchangePurityWt');
                if (mepw) mepw.value = (payment.metal_exchange_purity_wt != null && payment.metal_exchange_purity_wt !== '') ? payment.metal_exchange_purity_wt : '0';
                var mea = document.getElementById('metalExchangeAmount');
                if (mea) mea.value = (parseFloat(payment.amount) || 0).toFixed(2);
                var meic = document.getElementById('metalExchangeItemCode');
                if (meic && payment.metal_exchange_item_code != null) meic.value = payment.metal_exchange_item_code;
                var mel = document.getElementById('metalExchangeProductList');
                if (mel) { mel.style.display = 'none'; mel.innerHTML = ''; }
                if (typeof window.updateMetalExchangeCalculations === 'function') window.updateMetalExchangeCalculations();
            }
            window._editingPaymentId = paymentId;
        }
    }
    window.deletePayment = deletePayment;
    window.editPayment = editPayment;
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
    
    function updatePaymentTotals() {
        if (typeof AuragoldPaymentCards !== 'undefined' && AuragoldPaymentCards.updateFooterTotals) {
            AuragoldPaymentCards.updateFooterTotals();
            return;
        }
        const rows = document.querySelectorAll('#paymentTableBody .pos-payment-card');
        let totalAmount = 0;
        let totalQuantity = 0;
        rows.forEach(function(row) {
            var elAmt = row.querySelector('[data-payment-amount]');
            var amt = parseFloat(String((elAmt && elAmt.textContent) || '0').replace(/[^\d.-]/g, '')) || 0;
            var qty = parseFloat(row.getAttribute('data-quantity') || 0) || 0;
            if (!isNaN(amt)) totalAmount += amt;
            if (!isNaN(qty)) totalQuantity += qty;
        });
        if (isNaN(totalAmount)) totalAmount = 0;
        if (isNaN(totalQuantity)) totalQuantity = 0;
        var ta = document.getElementById('paymentTotalAmount');
        var tq = document.getElementById('paymentTotalQuantity');
        if (ta) ta.textContent = totalAmount.toFixed(2);
        if (tq) tq.textContent = totalQuantity.toFixed(2);
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
            var meMetalEl = document.getElementById('metalExchangeMetal');
            var meProdInEl = document.getElementById('metalExchangeProductInput');
            var meProdIdEl = document.getElementById('metalExchangeProductId');
            var meProdListEl = document.getElementById('metalExchangeProductList');
            if (meMetalEl) meMetalEl.value = '';
            if (meProdInEl) meProdInEl.value = '';
            if (meProdIdEl) meProdIdEl.value = '';
            if (meProdListEl) { meProdListEl.style.display = 'none'; meProdListEl.innerHTML = ''; }
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
                        'calculation', 'product', 'location', 'pkt-wt', 'pkt-less-wt', 'gross-wt', 'stone-weight',
                        'less-wt', 'net-wt', 'quantity', 'rate', 'amount', 'metal-qty', 'metal-weight', 'carat',
                        'requested-purity', 'requested', 'gold-loss1', 'gold-loss2',
                        'setting-charge', 'purity', 'purity-wt', 'wastage-per', 'wastage-wt',
                        'final-wt', 'alloy-wt', 'metal-rate', 'metal-value', 'metal-cost',
                        'discount-type', 'discount-per', 'discount-amount', 'discount',
                        'making-type', 'making-rate', 'making-discount-amt', 'making-amount',
                        'making-actual-value', 'making-cost', 'min-price', 'minimum',
                        'stone-charge-type', 'stone-rate', 'stone-amount', 'stone-cost',
                        'diamond-amount', 'purchase-amount', 'sale-percent', 'sale-amount', 'sale-amount-with',
                        'net-amt', 'tax-type', 'tax-percent', 'tax', 'other-charge-type', 'other-weight', 'other-rate',
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
            'diamond-carat': 'Diamond Carat',
            'pkt-wt': 'Pkt. Wt.',
            'pkt-less-wt': 'Pkt. Less Wt.',
            'requested-purity': 'Requested Purity',
            'requested': 'Requested',
            'gross-wt': 'Gross Wt.',
            'less-wt': 'D.Weight',
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
            'metal-rate': 'Metal Rate',
            'rate': 'Rate',
            'metal-value': 'Metal Value',
            'metal-cost': 'Metal Cost',
            'amount': 'Amount',
            'discount-type': 'Discount Type',
            'discount-per': 'Discount %',
            'discount-amount': 'Discount Amount',
            'discount': 'Discount',
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
            'sale-amount': 'Purchase Amt',
            'sale-amount-with': 'Purchase Amt With Tax',
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
<script src="assets/js/product-modal-excel-import-sale-order-voucher.js"></script>
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
        <h3 class="print-invoice-modal-title" id="printInvoiceModalTitle">Print bill</h3>
        <p class="print-invoice-modal-message" id="printInvoiceModalMessage">Do you want to print purchase invoice?</p>
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
    window._piPostSaveFlow = window._piPostSaveFlow || null;
    window._piPendingPostSaveRedirect = window._piPendingPostSaveRedirect || null;

    function formatPurchaseInvoiceSavedBarcodes(items, newBarcodesFromServer) {
        var out = { hasAny: false, list: '', message: '' };
        var allCodes = [];
        var lines = [];
        var seen = {};
        function pushBarcode(bc, name) {
            bc = String(bc || '').trim();
            if (!bc || seen[bc]) return;
            seen[bc] = true;
            allCodes.push(bc);
            var nm = String(name || '').trim();
            lines.push(nm ? (bc + ' — ' + nm) : bc);
        }
        if (items && items.length) {
            items.forEach(function(it) {
                pushBarcode(it.barcode, it.product_name || '');
            });
        }
        if (newBarcodesFromServer && newBarcodesFromServer.length) {
            newBarcodesFromServer.forEach(function(b) {
                if (b && typeof b === 'object') {
                    pushBarcode(b.barcode, b.product_name || '');
                } else {
                    pushBarcode(b, '');
                }
            });
        }
        if (!allCodes.length) return out;
        out.hasAny = true;
        out.list = allCodes.join(', ');
        out.message = lines.join('\n');
        return out;
    }

    function piOpenPrintBillModal(opts) {
        opts = opts || {};
        var modal = document.getElementById('printInvoiceModal');
        if (!modal) return false;
        var t = document.getElementById('printInvoiceModalTitle');
        var m = document.getElementById('printInvoiceModalMessage');
        if (t) t.textContent = opts.title != null ? opts.title : 'Print bill';
        if (m) {
            m.textContent = opts.message != null ? opts.message : '';
            m.style.whiteSpace = opts.whiteSpace != null ? opts.whiteSpace : 'normal';
        }
        modal.style.display = 'flex';
        modal.style.zIndex = '10000';
        modal.style.visibility = 'visible';
        modal.style.opacity = '1';
        return true;
    }

    function purchaseInvoiceAfterSavePrompts(invoiceId, invoiceNo, savedItems, newBarcodesFromServer) {
        var barcodeFmt = formatPurchaseInvoiceSavedBarcodes(savedItems, newBarcodesFromServer);
        var barcodeList = barcodeFmt.list;
        var barcodeMessage = barcodeFmt.message;

        function doRedirect() {
            window.location.href = window.pendingRedirectUrl || 'purchase-invoice.php';
            window.pendingRedirectUrl = null;
        }
        window._piPendingPostSaveRedirect = doRedirect;
        window.pendingRedirectUrl = window.pendingRedirectUrl || 'purchase-invoice.php';

        function openInvoicePrintModalOnly() {
            if (!invoiceId) {
                doRedirect();
                return;
            }
            savedInvoiceId = invoiceId;
            window._piPostSaveFlow = { phase: 'invoice', invoiceId: invoiceId };
            setTimeout(function() {
                if (!piOpenPrintBillModal({
                    title: 'Print bill',
                    message: 'Do you want to print purchase invoice?',
                    whiteSpace: 'normal'
                })) {
                    if (confirm('Purchase Invoice saved successfully! Do you want to print the invoice?')) {
                        window.open('purchase-invoice-print.php?id=' + invoiceId, '_blank', 'width=1200,height=800');
                    }
                    doRedirect();
                }
            }, 200);
        }

        if (barcodeList) {
            window._piPostSaveFlow = { phase: 'barcode', invoiceId: invoiceId, barcodeList: barcodeList };
            setTimeout(function() {
                if (!piOpenPrintBillModal({
                    title: 'Print barcode',
                    message: 'Do you want to print barcode?\n\n' + (barcodeMessage || barcodeList),
                    whiteSpace: 'pre-line'
                })) {
                    if (confirm('Do you want to print barcode?\n\n' + (barcodeMessage || barcodeList))) {
                        window.open('barcode-print.php?barcodes=' + encodeURIComponent(barcodeList), '_blank', 'width=900,height=700');
                    }
                    setTimeout(openInvoicePrintModalOnly, 300);
                }
            }, 200);
        } else {
            setTimeout(openInvoicePrintModalOnly, 200);
        }
    }

    // Store invoice ID for print confirmation
    let savedInvoiceId = null;
    
    // Show print invoice confirmation modal (manual print / legacy)
    function showPrintInvoiceModal(invoiceId) {
        savedInvoiceId = invoiceId;
        window._piPostSaveFlow = null;
        window._piPendingPostSaveRedirect = null;
        setTimeout(function() {
            if (!piOpenPrintBillModal({
                title: 'Print bill',
                message: 'Do you want to print purchase invoice?',
                whiteSpace: 'normal'
            })) {
                if (confirm('Purchase Invoice saved successfully! Do you want to print the invoice?')) {
                    window.open('purchase-invoice-print.php?id=' + invoiceId, '_blank', 'width=1200,height=800');
                }
                if (window.pendingRedirectUrl) {
                    window.location.href = window.pendingRedirectUrl;
                    window.pendingRedirectUrl = null;
                }
            }
        }, 200);
    }
    
    // Close print modal — No on barcode step opens invoice print prompt; No on invoice step redirects
    function closePrintInvoiceModal(opts) {
        opts = opts || {};
        var afterPrint = !!opts.afterPrint;
        var modal = document.getElementById('printInvoiceModal');
        if (modal) modal.style.display = 'none';

        var flow = window._piPostSaveFlow;

        if (afterPrint && window.pendingRedirectUrl) {
            window.location.href = window.pendingRedirectUrl;
            window.pendingRedirectUrl = null;
            savedInvoiceId = null;
            window._piPostSaveFlow = null;
            return;
        }

        if (!afterPrint && flow && flow.phase === 'barcode') {
            var nextInvoiceId = flow.invoiceId;
            window._piPostSaveFlow = { phase: 'invoice', invoiceId: nextInvoiceId };
            savedInvoiceId = nextInvoiceId;
            setTimeout(function() {
                piOpenPrintBillModal({
                    title: 'Print bill',
                    message: 'Do you want to print purchase invoice?',
                    whiteSpace: 'normal'
                });
            }, 250);
            return;
        }

        if (!afterPrint && flow && flow.phase === 'invoice') {
            window._piPostSaveFlow = null;
            savedInvoiceId = null;
            if (window._piPendingPostSaveRedirect) {
                window._piPendingPostSaveRedirect();
                window._piPendingPostSaveRedirect = null;
            } else if (window.pendingRedirectUrl) {
                window.location.href = window.pendingRedirectUrl;
                window.pendingRedirectUrl = null;
            }
            return;
        }

        savedInvoiceId = null;
        if (window.pendingRedirectUrl) {
            window.location.href = window.pendingRedirectUrl;
            window.pendingRedirectUrl = null;
        }
    }
    
    // Confirm print — barcode first, then purchase invoice
    function confirmPrintInvoice() {
        var flow = window._piPostSaveFlow;
        if (flow && flow.phase === 'barcode') {
            window.open('barcode-print.php?barcodes=' + encodeURIComponent(flow.barcodeList), '_blank', 'width=900,height=700');
            var modalEl = document.getElementById('printInvoiceModal');
            if (modalEl) modalEl.style.display = 'none';
            window._piPostSaveFlow = { phase: 'invoice', invoiceId: flow.invoiceId };
            savedInvoiceId = flow.invoiceId;
            setTimeout(function() {
                piOpenPrintBillModal({
                    title: 'Print bill',
                    message: 'Do you want to print purchase invoice?',
                    whiteSpace: 'normal'
                });
            }, 250);
            return;
        }
        if (flow && flow.phase === 'invoice') {
            window.open('purchase-invoice-print.php?id=' + encodeURIComponent(flow.invoiceId), '_blank', 'width=1200,height=800');
            window._piPostSaveFlow = null;
            if (window._piPendingPostSaveRedirect) {
                window._piPendingPostSaveRedirect();
                window._piPendingPostSaveRedirect = null;
            }
            closePrintInvoiceModal({ afterPrint: true });
            return;
        }
        if (savedInvoiceId) {
            window.open('purchase-invoice-print.php?id=' + savedInvoiceId, '_blank', 'width=1200,height=800');
        }
        closePrintInvoiceModal({ afterPrint: true });
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
    
    window.purchaseInvoiceAfterSavePrompts = purchaseInvoiceAfterSavePrompts;
    window.showPrintInvoiceModal = showPrintInvoiceModal;
    window.closePrintInvoiceModal = closePrintInvoiceModal;
    window.confirmPrintInvoice = confirmPrintInvoice;
</script>

</body>

</html>




