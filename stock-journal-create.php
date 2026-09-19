<?php 
session_start();
require_once 'config.php';
require_once __DIR__ . '/includes/auragold_product_opening_field_helpers.php';
require_once __DIR__ . '/includes/auragold_metal_opening_fields_schema.php';
if (isset($conn) && $conn instanceof mysqli && function_exists('auragold_ensure_opening_stock_weight_precision')) {
    auragold_ensure_opening_stock_weight_precision($conn);
}

$sj_effective_branch_id = function_exists('auragold_effective_branch_id') ? (int) auragold_effective_branch_id() : 0;

// Load Metals for category tabs: same branch scope as Masters + unique display names (avoids duplicate tabs)
$sj_metal_suffix = function_exists('auragold_master_list_sql_suffix') ? auragold_master_list_sql_suffix($conn, 'tbl_metal') : '';
$metals_raw = getList('SELECT id, display_name, system_name, hsn_code FROM tbl_metal WHERE status = 1 ' . $sj_metal_suffix . ' ORDER BY id ASC');
$metals = [];
$sj_seen_metal_dn = [];
foreach ($metals_raw as $_mj) {
    $dn = trim((string) ($_mj['display_name'] ?? ''));
    if ($dn === '') {
        continue;
    }
    $nk = strtolower($dn);
    if (isset($sj_seen_metal_dn[$nk])) {
        continue;
    }
    $sj_seen_metal_dn[$nk] = true;
    $metals[] = $_mj;
}
$metals_list = $metals;

// Load Karat master data
$carats = getList("SELECT id, name, purity, description FROM tbl_carat WHERE status = 1 ORDER BY id ASC");

// Load Location master data
$locations = getList("SELECT id, name FROM tbl_location WHERE status = 1 ORDER BY id ASC");

// Load master data for product creation modal
$categories = getList("SELECT id, name FROM tbl_categories WHERE status = 1 ORDER BY name ASC");
$branches = getListMaster("SELECT id, name, code FROM tbl_branches WHERE status = 1 ORDER BY name ASC");
$calculation_modes = getList("SELECT id, name, code FROM tbl_calculation_modes WHERE status = 1 ORDER BY sort_order ASC, name ASC");

// Product table column drag: Feather icon-move (see assets/css/column-drag-icons.css)
$sj_drag_icons = '<i class="feather icon-move"></i>';
$sj_col_drag = '<span class="product-modal-col-drag-handle" title="Drag to reorder within this group (use the move icon on the group title row above to move the whole group).">' . $sj_drag_icons . '</span>';
$sj_col_drag_locked = '<span class="product-modal-col-drag-handle product-modal-col-drag-handle--locked" title="Fixed column order"><i class="feather icon-move"></i></span>';

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

// Load stock journal items for editing if item_id provided
$edit_item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;
// Detect edit mode: do not add new products; only update existing inward/outward stock
$edit_mode = isset($_GET['edit']) && $_GET['edit'] == 'true';
$add_mode = isset($_GET['mode']) && $_GET['mode'] === 'add';
$edit_stock_journal_items = [];
$purchase_invoice_item = null;

if ($edit_item_id > 0) {
    // Load purchase invoice item details (total qty and gross weight available). Use metal_qty when set (e.g. 100), else quantity.
    $purchase_invoice_item = getRecord("
        SELECT pii.id,
               COALESCE(pii.metal_qty, pii.quantity, 0) as total_quantity,
               COALESCE(pii.gross_weight, 0) as total_gross_weight,
               pi.invoice_no, pi.supplier_name
        FROM tbl_purchase_invoice_items pii
        LEFT JOIN tbl_purchase_invoices pi ON pii.invoice_id = pi.id
        WHERE pii.id = $edit_item_id
    ");
    
    // Always load existing stock journal items for balance calculation
    // But only display them in the table if in edit mode
    $query = "
        SELECT sj.*, 
               p.name as product_name, 
               p.article,
               pc.metal_id,
               m.display_name as metal_name
        FROM tbl_stock_journal sj
        LEFT JOIN tbl_products p ON sj.product_id = p.id
        LEFT JOIN tbl_product_characteristics pc ON sj.product_characteristic_id = pc.id
        LEFT JOIN tbl_metal m ON pc.metal_id = m.id
        WHERE sj.item_id = $edit_item_id AND sj.status = 'active'
            AND (sj.comment IS NULL OR sj.comment NOT LIKE 'auragold_doc|src=pi|%')
        ORDER BY sj.id ASC
    ";
    $edit_stock_journal_items = getList($query);
    
    // Calculate existing used quantities and weights for balance display
    $existing_used_qty = 0;
    $existing_used_gross_wt = 0;
    if (!empty($edit_stock_journal_items)) {
        foreach ($edit_stock_journal_items as $sj_item) {
            $existing_used_qty += (float)($sj_item['quantity'] ?? 0);
            $existing_used_gross_wt += (float)($sj_item['gross_weight'] ?? 0);
        }
    }
    
    // Add existing used amounts to purchase invoice item data for JavaScript
    if ($purchase_invoice_item) {
        $purchase_invoice_item['existing_used_quantity'] = $existing_used_qty;
        $purchase_invoice_item['existing_used_gross_weight'] = $existing_used_gross_wt;
    }
}

// Product opening voucher: load stock details from product characteristic (opening qty/weight) and existing stock journal
$product_opening_item = null;
$sj_product_opening_line = null;
$sj_context_metal_id = 0;
$voucher_type_param = isset($_GET['voucher']) ? trim($_GET['voucher']) : '';
$characteristic_id_param = isset($_GET['characteristic_id']) ? (int)$_GET['characteristic_id'] : 0;
$product_id_param = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
$product_opening_single_product = ($voucher_type_param === 'product_opening' && $characteristic_id_param > 0 && $product_id_param > 0);
$product_opening_is_diamond_or_stones = false;
if ($voucher_type_param === 'product_opening' && $characteristic_id_param > 0 && !$purchase_invoice_item) {
    $pc_branch_sql_sj = '';
    if ($sj_effective_branch_id > 0 && !empty($conn_master) && function_exists('getRecordMaster')) {
        $br_pc_sj = getRecordMaster('SELECT main_branch_id FROM tbl_branches WHERE id = ' . (int) $sj_effective_branch_id . ' LIMIT 1');
        if ($br_pc_sj && (int) ($br_pc_sj['main_branch_id'] ?? 0) > 0) {
            $pc_branch_sql_sj = ' AND pc.branch_id = ' . (int) $sj_effective_branch_id;
        } elseif ($br_pc_sj) {
            $pc_branch_sql_sj = ' AND (pc.branch_id = ' . (int) $sj_effective_branch_id . ' OR pc.branch_id IS NULL OR pc.branch_id = 0)';
        }
    }
    $pc = getRecord("
        SELECT pc.id,
               pc.product_id,
               pc.metal_id,
               pc.barcode_prefix,
               pc.barcode_digits,
               pc.diamond_category,
               pc.opening_purity,
               pc.opening_weight,
               pc.rate,
               pc.carat,
               pc.sku_code,
               pc.barcode,
               COALESCE(pc.opening_qty, 0) as total_quantity,
               COALESCE(pc.opening_weight, 0) as total_gross_weight,
               p.name as product_name,
               p.article as product_article,
               p.category_id,
               m.display_name as metal_display_name
        FROM tbl_product_characteristics pc
        LEFT JOIN tbl_products p ON pc.product_id = p.id
        LEFT JOIN tbl_metal m ON m.id = pc.metal_id
        WHERE pc.id = $characteristic_id_param $pc_branch_sql_sj
    ");
    if ($pc) {
        if (!empty($pc['metal_id'])) {
            $sj_context_metal_id = (int) $pc['metal_id'];
        }
        $mdn = isset($pc['metal_display_name']) ? strtolower((string) $pc['metal_display_name']) : '';
        if ($mdn !== '' && (strpos($mdn, 'diamond') !== false || strpos($mdn, 'stone') !== false)) {
            $product_opening_is_diamond_or_stones = true;
        }
        $product_opening_item = [
            'id' => (int)$pc['id'],
            'invoice_no' => $pc['product_name'] ? ('Product Opening - ' . $pc['product_name']) : 'Product Opening',
            'total_quantity' => (float)($pc['total_quantity'] ?? 0),
            'total_gross_weight' => (string)($pc['total_gross_weight'] ?? '0'),
            'existing_used_quantity' => 0,
            'existing_used_gross_weight' => '0',
        ];
        $sj_product_opening_line = [
            'id' => (int) ($pc['product_id'] ?? $product_id_param),
            'name' => (string) ($pc['product_name'] ?? ''),
            'article' => (string) ($pc['product_article'] ?? ''),
            'category_id' => $pc['category_id'] ?? null,
            'characteristic_id' => (int) $pc['id'],
            'sku_code' => (string) ($pc['sku_code'] ?? ''),
            'barcode' => (string) ($pc['barcode'] ?? ''),
            'carat' => $pc['carat'] ?? null,
            'opening_purity' => (float) ($pc['opening_purity'] ?? 1),
            'opening_weight' => (string) ($pc['opening_weight'] ?? '0'),
            'rate' => (float) ($pc['rate'] ?? 0),
            'diamond_category' => (string) ($pc['diamond_category'] ?? ''),
            'barcode_prefix' => (string) ($pc['barcode_prefix'] ?? ''),
            'barcode_digits' => (int) ($pc['barcode_digits'] ?? 0) > 0 ? (int) $pc['barcode_digits'] : 5,
            'metal_name' => (string) ($pc['metal_display_name'] ?? ''),
            'metal_id' => (int) ($pc['metal_id'] ?? 0),
        ];
        $sj_used = getRecord("
            SELECT COALESCE(SUM(sj.quantity), 0) as used_qty, COALESCE(SUM(sj.gross_weight), 0) as used_gross_wt
            FROM tbl_stock_journal sj
            WHERE sj.product_characteristic_id = $characteristic_id_param AND sj.status = 'active'
                AND (sj.item_id IS NULL OR sj.item_id = 0)
                AND (sj.comment IS NULL OR sj.comment NOT LIKE 'auragold_doc|src=pi|%')
        ");
        if ($sj_used) {
            $product_opening_item['existing_used_quantity'] = (float)($sj_used['used_qty'] ?? 0);
            $product_opening_item['existing_used_gross_weight'] = (string)($sj_used['used_gross_wt'] ?? '0');
        }
    }
}

// Resolve active metal tab (product opening metal_id may not match deduped branch metal list ids)
$sj_active_tab_metal_id = $sj_context_metal_id;
if ($sj_active_tab_metal_id > 0) {
    $sj_metal_ids_in_tabs = array_map(function ($m) {
        return (int) ($m['id'] ?? 0);
    }, $metals);
    if (!in_array($sj_active_tab_metal_id, $sj_metal_ids_in_tabs, true)) {
        $sj_match_name = '';
        if (!empty($sj_product_opening_line['metal_name'])) {
            $sj_match_name = trim((string) $sj_product_opening_line['metal_name']);
        }
        foreach ($metals as $_sm) {
            if ($sj_match_name !== '' && strcasecmp(trim((string) ($_sm['display_name'] ?? '')), $sj_match_name) === 0) {
                $sj_active_tab_metal_id = (int) $_sm['id'];
                break;
            }
        }
        if (!in_array($sj_active_tab_metal_id, $sj_metal_ids_in_tabs, true) && !empty($metals)) {
            $sj_active_tab_metal_id = (int) ($metals[0]['id'] ?? 0);
        }
    }
} elseif (!empty($metals)) {
    $sj_active_tab_metal_id = (int) ($metals[0]['id'] ?? 0);
}

// Add Product / extra grid rows: hide for single-product gold-style opening; keep for Diamond & Stones (multi-line, one barcode group).
// Purchase-invoice flow uses item_id in the URL — still allow adding rows up to PI line balance (not tied to edit_item_id).
$stock_journal_show_add_product_row = (!$product_opening_single_product || $product_opening_is_diamond_or_stones);

// Sample Excel + Excel import: product opening with balance context, or purchase-invoice line (same template/import pipeline).
$sj_excel_sample_import_enabled = ($product_id_param > 0 && $characteristic_id_param > 0) && (
    ($voucher_type_param === 'product_opening' && !empty($product_opening_item))
    || ($voucher_type_param === 'purchase_invoice' && $edit_item_id > 0 && !empty($purchase_invoice_item))
);

// Single variable for the balance block: show for either purchase invoice or product opening
$stock_detail_item = $purchase_invoice_item ?: $product_opening_item;
$stock_detail_label = $purchase_invoice_item ? 'Purchase Invoice' : ($product_opening_item ? 'Product Opening' : '');

// Default voucherTypeId in Product Selection (match product_opening + purchase line context for UI + save)
if ($edit_item_id > 0 && $purchase_invoice_item) {
    $raw_pi_inv = trim((string) ($purchase_invoice_item['invoice_no'] ?? ''));
    if ($raw_pi_inv !== '' && stripos($raw_pi_inv, 'Purchase Invoice') !== 0) {
        $purchase_invoice_item['invoice_no'] = 'Purchase Invoice - ' . $raw_pi_inv;
    } elseif ($raw_pi_inv === '') {
        $purchase_invoice_item['invoice_no'] = 'Purchase Invoice';
    }
    $sj_default_voucher_type = 'purchase_invoice';
} elseif ($voucher_type_param === 'product_opening' && $characteristic_id_param > 0) {
    $sj_default_voucher_type = 'product_opening';
} else {
    $sj_default_voucher_type = '';
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
<script>window.AURAGOLD_STOCK_JOURNAL_BRANCH_ID=<?php echo (int) $sj_effective_branch_id; ?>;</script>
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
        padding-bottom: 24px;
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
        color: #000;
       
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
        background: #11294b;
        font-weight: 700;
        font-size: 11px;
        padding: 8px;
        border-bottom: 2px solid #c5a864;
        color: #ffffff !important;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        position: relative;
    }
    .product-table thead th,
    .product-table thead th a {
        color: #ffffff !important;
    }
    .product-table thead th .feather,
    .product-table thead th i {
        color: rgba(255, 255, 255, 0.95) !important;
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
        display: none !important;
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
    .table-settings-search {
        margin-bottom: 0.75rem;
    }
    .table-settings-search input,
    .table-settings-search .form-control-sm {
        width: 100%;
        font-size: 0.8rem;
        padding: 0.35rem 0.5rem;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
    }
    .table-settings-search input:focus {
        border-color: #c5a864;
        outline: none;
        box-shadow: 0 0 0 2px rgba(197, 168, 100, 0.2);
    }
    .table-settings-item.hidden {
        display: none !important;
    }
    .table-settings-groups-title {
        font-weight: 700;
        margin: 0.5rem 0 0.25rem 0;
        font-size: 0.8rem;
        color: #64748b;
    }
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
        opacity: 0.45;
        pointer-events: none;
    }
    .table-settings-item.sub-column-disabled label {
        cursor: not-allowed;
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
    .category-tab-btn:disabled,
    .category-tab-btn.sj-metal-tab-locked {
        opacity: 0.45;
        cursor: not-allowed;
    }
    /* Product modal table: group drag handles (same as includes/common-modal.php) */
    #productListTablePage .product-modal-group-drag-handle,
    #productListTable .product-modal-group-drag-handle,
    #productSelectionModal #productListTable .product-modal-group-drag-handle {
        display: inline-block;
        padding: 0 4px 0 0;
        margin-right: 2px;
        vertical-align: middle;
        color: #475569;
        line-height: 1;
    }
    #productListTablePage .product-modal-group-header-th .feather,
    #productListTable .product-modal-group-header-th .feather,
    #productSelectionModal #productListTable .product-modal-group-header-th .feather {
        width: 14px;
        height: 14px;
    }
    /* Per-column drag grip (row2 + fixed tail headers) */
    #productListTablePage .product-modal-col-drag-handle,
    #productListTable .product-modal-col-drag-handle,
    #productSelectionModal #productListTable .product-modal-col-drag-handle {
        display: inline-block;
        padding: 0 4px 0 0;
        margin-right: 2px;
        vertical-align: middle;
        line-height: 1;
    }
    #productListTablePage .product-modal-col-drag-handle .feather,
    #productListTable .product-modal-col-drag-handle .feather,
    #productSelectionModal #productListTable .product-modal-col-drag-handle .feather {
        width: 12px;
        height: 12px;
    }
    #productListTablePage .product-modal-col-drag-handle--locked,
    #productListTable .product-modal-col-drag-handle--locked,
    #productSelectionModal #productListTable .product-modal-col-drag-handle--locked {
        cursor: default;
        opacity: 0.45;
    }
    #productListTablePage thead tr:first-child th[data-group="net-reverse"] .product-modal-col-drag-handle--locked,
    #productListTable thead tr:first-child th[data-group="net-reverse"] .product-modal-col-drag-handle--locked,
    #productSelectionModal #productListTable thead tr:first-child th[data-group="net-reverse"] .product-modal-col-drag-handle--locked {
        color: rgba(255, 255, 255, 0.9);
        opacity: 0.85;
    }
    /* Sub-column drag: clear “ready to move” state (group drag uses Sortable ghost; row-2 uses these) */
    #productListTablePage thead tr:nth-child(2) th.modal-col-dragging,
    #productListTable thead tr:nth-child(2) th.modal-col-dragging,
    #productSelectionModal #productListTable thead tr:nth-child(2) th.modal-col-dragging {
        background: #bae6fd !important;
        outline: 2px solid #0284c7;
        outline-offset: -2px;
        box-shadow: 0 0 0 1px #fff, 0 4px 14px rgba(2, 132, 199, 0.4);
        z-index: 25;
    }
    #productListTablePage thead tr:nth-child(2) th.modal-col-drag-over-left,
    #productListTable thead tr:nth-child(2) th.modal-col-drag-over-left,
    #productSelectionModal #productListTable thead tr:nth-child(2) th.modal-col-drag-over-left {
        box-shadow: inset 5px 0 0 0 #0ea5e9;
        background: rgba(14, 165, 233, 0.16) !important;
    }
    #productListTablePage thead tr:nth-child(2) th.modal-col-drag-over-right,
    #productListTable thead tr:nth-child(2) th.modal-col-drag-over-right,
    #productSelectionModal #productListTable thead tr:nth-child(2) th.modal-col-drag-over-right {
        box-shadow: inset -5px 0 0 0 #0ea5e9;
        background: rgba(14, 165, 233, 0.16) !important;
    }
    .sj-modal-col-drag-ghost {
        max-width: 300px;
        padding: 10px 14px;
        border-radius: 8px;
        background: linear-gradient(145deg, #e0f2fe 0%, #dbeafe 100%);
        border: 2px solid #0ea5e9;
        box-shadow: 0 10px 28px rgba(15, 23, 42, 0.22);
        font-size: 0.8rem;
        line-height: 1.25;
    }
    .sj-modal-col-drag-ghost__badge {
        display: block;
        font-size: 0.62rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #0369a1;
        margin-bottom: 4px;
    }
    .sj-modal-col-drag-ghost__name {
        display: block;
        font-weight: 600;
        color: #0f172a;
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
    /* Product Selection modal: column settings use same flow as main card / Sale Invoice (structural columns forced in JS on load). */
    #productListTableScrollWrapper.table-responsive {
        overflow-x: auto;
    }
    #productListTable th.hidden,
    #productListTable td.hidden,
    #productListTablePage th.hidden,
    #productListTablePage td.hidden {
        display: none !important;
    }
    #productListTable th,
    #productListTable td,
    #productListTablePage th,
    #productListTablePage td {
        min-width: 120px;
        white-space: nowrap;
        padding: 0.5rem 0.4rem;
        vertical-align: middle;
    }
    /* net-amt-tax / reverse excluded: they must stay position:sticky (right-pinned) like their body cells. */
    #productListTablePage thead tr:nth-child(2) th[data-column]:not([data-column="actions"]):not([data-column="net-amt-tax"]):not([data-column="reverse"]),
    #productListTable thead tr:nth-child(2) th[data-column]:not([data-column="actions"]):not([data-column="net-amt-tax"]):not([data-column="reverse"]),
    #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column]:not([data-column="actions"]):not([data-column="net-amt-tax"]):not([data-column="reverse"]) {
        position: relative;
        box-sizing: border-box;
    }
    #productListTablePage .pm-col-resizer,
    #productListTable .pm-col-resizer,
    #productSelectionModal #productListTable .pm-col-resizer {
        position: absolute;
        top: 0;
        right: 0;
        width: 8px;
        min-width: 8px;
        max-width: 8px;
        cursor: col-resize;
        user-select: none;
        z-index: 4;
        height: 100%;
        background: transparent !important;
        box-shadow: none;
    }
    #productListTablePage .pm-col-resizer:hover,
    #productListTable .pm-col-resizer:hover,
    #productSelectionModal #productListTable .pm-col-resizer:hover {
        background: rgba(212, 175, 55, 0.45) !important;
    }
    /* Modal grid: match main card — sticky Id column (shared common-modal thead otherwise loses left anchor and headers drift on scroll) */
    #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column="id"].product-modal-th-cell {
        position: sticky;
        left: 0;
        z-index: 7;
        box-shadow: 1px 0 0 rgba(255, 255, 255, 0.22);
        color: #ffffff !important;
        background: #11294b !important;
    }
    /*
     * Sticky right columns: `right` must equal the sum of widths of columns to the right.
     * Fixed widths + z-index stack (outer-right = highest) prevent overlap on horizontal scroll.
     */
    #productListTable.product-list-table-fit,
    #productListTablePage.product-list-table-fit {
        --sj-sticky-actions-w: 90px;
        --sj-sticky-images-w: 140px;
        --sj-sticky-reverse-w: 110px;
        --sj-sticky-netamt-w: 130px;
    }
    #productListTable th[data-column="actions"],
    #productListTablePage th[data-column="actions"],
    #productListTable td[data-column="actions"],
    #productListTablePage td[data-column="actions"] {
        position: sticky !important;
        right: 0 !important;
        width: var(--sj-sticky-actions-w);
        min-width: var(--sj-sticky-actions-w);
        max-width: var(--sj-sticky-actions-w);
        box-sizing: border-box;
        z-index: 20;
        box-shadow: -2px 0 4px rgba(0,0,0,0.08);
    }
    #productListTable td[data-column="actions"],
    #productListTablePage td[data-column="actions"] {
        background: #fff;
    }
    #productListTable thead th[data-column="actions"],
    #productListTablePage thead th[data-column="actions"] {
        z-index: 24;
    }
    #productListTable tbody tr:hover td[data-column="actions"],
    #productListTablePage tbody tr:hover td[data-column="actions"] {
        background: #f8fafc;
    }
    #productListTable th[data-column="images"],
    #productListTablePage th[data-column="images"],
    #productListTable td[data-column="images"],
    #productListTablePage td[data-column="images"] {
        position: sticky !important;
        right: var(--sj-sticky-actions-w) !important;
        width: var(--sj-sticky-images-w);
        min-width: var(--sj-sticky-images-w);
        max-width: var(--sj-sticky-images-w);
        box-sizing: border-box;
        z-index: 19;
        box-shadow: -2px 0 4px rgba(0,0,0,0.08);
    }
    #productListTable td[data-column="images"],
    #productListTablePage td[data-column="images"] {
        background: #fff;
    }
    #productListTable thead th[data-column="images"],
    #productListTablePage thead th[data-column="images"] {
        z-index: 23;
    }
    #productListTable tbody tr:hover td[data-column="images"],
    #productListTablePage tbody tr:hover td[data-column="images"] {
        background: #f8fafc;
    }
    #productListTable th[data-column="reverse"],
    #productListTablePage th[data-column="reverse"],
    #productListTable td[data-column="reverse"],
    #productListTablePage td[data-column="reverse"] {
        position: sticky !important;
        right: calc(var(--sj-sticky-actions-w) + var(--sj-sticky-images-w)) !important;
        width: var(--sj-sticky-reverse-w);
        min-width: var(--sj-sticky-reverse-w);
        max-width: var(--sj-sticky-reverse-w);
        box-sizing: border-box;
        z-index: 18;
        box-shadow: -2px 0 4px rgba(0,0,0,0.08);
    }
    #productListTable td[data-column="reverse"],
    #productListTablePage td[data-column="reverse"] {
        background: #fff;
    }
    #productListTable thead th[data-column="reverse"],
    #productListTablePage thead th[data-column="reverse"] {
        z-index: 22;
    }
    #productListTable tbody tr:hover td[data-column="reverse"],
    #productListTablePage tbody tr:hover td[data-column="reverse"] {
        background: #f8fafc;
    }
    #productListTable th[data-column="net-amt-tax"],
    #productListTablePage th[data-column="net-amt-tax"],
    #productListTable td[data-column="net-amt-tax"],
    #productListTablePage td[data-column="net-amt-tax"] {
        position: sticky !important;
        right: calc(
            var(--sj-sticky-actions-w) +
            var(--sj-sticky-images-w) +
            var(--sj-sticky-reverse-w)
        ) !important;
        width: var(--sj-sticky-netamt-w);
        min-width: var(--sj-sticky-netamt-w);
        max-width: var(--sj-sticky-netamt-w);
        box-sizing: border-box;
        z-index: 17;
        box-shadow: -2px 0 4px rgba(0,0,0,0.08);
    }
    #productListTable td[data-column="net-amt-tax"],
    #productListTablePage td[data-column="net-amt-tax"] {
        background: #fff;
    }
    #productListTable thead th[data-column="net-amt-tax"],
    #productListTablePage thead th[data-column="net-amt-tax"] {
        z-index: 21;
    }
    /* Row 1: do not stick the colspan net-reverse group th — sticky+colspan breaks row1/row2 column alignment in Chrome. */
    #productListTable.product-list-table-fit thead th[data-group="net-reverse"],
    #productListTablePage.product-list-table-fit thead th[data-group="net-reverse"],
    #productSelectionModal #productListTable.product-list-table-fit thead th[data-group="net-reverse"] {
        position: static;
        z-index: auto;
        box-shadow: none;
    }
    .table-responsive {
        overflow-x: auto;
        position: relative;
    }
    #productListTable tbody tr:hover td[data-column="net-amt-tax"],
    #productListTablePage tbody tr:hover td[data-column="net-amt-tax"] {
        background: #f8fafc;
    }
    /*
     * Tbody: do not use high z-index on sticky-right cells — otherwise when the grid scrolls
     * horizontally, those layers paint on top of scrolling columns to the left (e.g. Platinum,
     * Certificate) and inputs look "missing" under the header.
     * Thead keeps the higher z-index for vertical header stacking; among the four stickies, DOM
     * order (actions last) is enough.
     */
    #productListTable.product-list-table-fit tbody td[data-column="actions"],
    #productListTablePage.product-list-table-fit tbody td[data-column="actions"] {
        z-index: 0;
    }
    #productListTable.product-list-table-fit tbody td[data-column="images"],
    #productListTablePage.product-list-table-fit tbody td[data-column="images"] {
        z-index: 0;
    }
    #productListTable.product-list-table-fit tbody td[data-column="reverse"],
    #productListTablePage.product-list-table-fit tbody td[data-column="reverse"] {
        z-index: 0;
    }
    #productListTable.product-list-table-fit tbody td[data-column="net-amt-tax"],
    #productListTablePage.product-list-table-fit tbody td[data-column="net-amt-tax"] {
        z-index: 0;
    }
    #productListTable td[data-column="images"] .sj-images-wrap,
    #productListTablePage td[data-column="images"] .sj-images-wrap {
        max-width: 100%;
        overflow-x: auto;
    }
    /* Base thead: subheader row and general typography (row1 group row gets navy+white below) */
    #productListTable thead th,
    #productListTablePage thead th,
    #productSelectionModal #productListTable thead th {
        font-weight: 700;
        font-size: 0.7rem;
        color: #0f172a !important;
        border-bottom: 2px solid #c5a864;
    }
    #productListTable.product-list-table-fit thead tr:nth-child(1) th:not([data-column="checkbox"]),
    #productListTablePage.product-list-table-fit thead tr:nth-child(1) th:not([data-column="checkbox"]),
    #productSelectionModal #productListTable thead tr:nth-child(1) th:not([data-column="checkbox"]) {
        background: #11294b !important;
        color: #ffffff !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.25);
    }
    #productListTablePage.product-list-table-fit thead tr:nth-child(1) th.product-modal-group-header-th,
    #productListTablePage.product-list-table-fit thead tr:nth-child(1) th .product-modal-group-label,
    #productListTablePage.product-list-table-fit thead tr:nth-child(1) th .feather,
    #productListTable.product-list-table-fit thead tr:nth-child(1) th.product-modal-group-header-th,
    #productListTable.product-list-table-fit thead tr:nth-child(1) th .product-modal-group-label,
    #productListTable.product-list-table-fit thead tr:nth-child(1) th .feather,
    #productSelectionModal #productListTable thead tr:nth-child(1) th.product-modal-group-header-th,
    #productSelectionModal #productListTable thead tr:nth-child(1) th .product-modal-group-label,
    #productSelectionModal #productListTable thead tr:nth-child(1) th .feather {
        color: #ffffff !important;
    }
    #productListTablePage.product-list-table-fit thead tr:nth-child(1) th .product-modal-group-drag-handle,
    #productListTable.product-list-table-fit thead tr:nth-child(1) th .product-modal-group-drag-handle,
    #productSelectionModal #productListTable thead tr:nth-child(1) th .product-modal-group-drag-handle {
        color: rgba(255, 255, 255, 0.92) !important;
    }
    /* Subheader row 2: navy band (matches group row / not grey) */
    #productListTable.product-list-table-fit thead tr:nth-child(2) th[data-column]:not([data-column="net-amt-tax"]):not([data-column="reverse"]),
    #productListTablePage.product-list-table-fit thead tr:nth-child(2) th[data-column]:not([data-column="net-amt-tax"]):not([data-column="reverse"]),
    #productSelectionModal #productListTable.product-list-table-fit thead tr:nth-child(2) th[data-column]:not([data-column="net-amt-tax"]):not([data-column="reverse"]),
    #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column]:not([data-column="net-amt-tax"]):not([data-column="reverse"]) {
        color: #ffffff !important;
        background: #11294b !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.18);
    }
    #productListTablePage.product-list-table-fit thead tr:nth-child(2) th[data-column]:not([data-column="net-amt-tax"]):not([data-column="reverse"]) .product-modal-th-label,
    #productListTable.product-list-table-fit thead tr:nth-child(2) th[data-column]:not([data-column="net-amt-tax"]):not([data-column="reverse"]) .product-modal-th-label,
    #productSelectionModal #productListTable.product-list-table-fit thead tr:nth-child(2) th[data-column]:not([data-column="net-amt-tax"]):not([data-column="reverse"]) .product-modal-th-label,
    #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column]:not([data-column="net-amt-tax"]):not([data-column="reverse"]) .product-modal-th-label {
        color: #ffffff !important;
    }
    #productListTablePage.product-list-table-fit thead tr:nth-child(2) th[data-column]:not([data-column="net-amt-tax"]):not([data-column="reverse"]) .product-modal-col-drag-handle,
    #productListTablePage.product-list-table-fit thead tr:nth-child(2) th[data-column]:not([data-column="net-amt-tax"]):not([data-column="reverse"]) .feather,
    #productListTable.product-list-table-fit thead tr:nth-child(2) th[data-column]:not([data-column="net-amt-tax"]):not([data-column="reverse"]) .product-modal-col-drag-handle,
    #productListTable.product-list-table-fit thead tr:nth-child(2) th[data-column]:not([data-column="net-amt-tax"]):not([data-column="reverse"]) .feather,
    #productSelectionModal #productListTable.product-list-table-fit thead tr:nth-child(2) th[data-column]:not([data-column="net-amt-tax"]):not([data-column="reverse"]) .product-modal-col-drag-handle,
    #productSelectionModal #productListTable.product-list-table-fit thead tr:nth-child(2) th[data-column]:not([data-column="net-amt-tax"]):not([data-column="reverse"]) .feather,
    #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column]:not([data-column="net-amt-tax"]):not([data-column="reverse"]) .product-modal-col-drag-handle,
    #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column]:not([data-column="net-amt-tax"]):not([data-column="reverse"]) .feather {
        color: rgba(255, 255, 255, 0.92) !important;
    }
    /* Net+Reverse (row2): gold band (Add Product / theme accent) + white text — not slate grey */
    #productListTablePage.product-list-table-fit thead tr:nth-child(2) th[data-column="net-amt-tax"],
    #productListTablePage.product-list-table-fit thead tr:nth-child(2) th[data-column="reverse"],
    #productListTable.product-list-table-fit thead tr:nth-child(2) th[data-column="net-amt-tax"],
    #productListTable.product-list-table-fit thead tr:nth-child(2) th[data-column="reverse"],
    #productSelectionModal #productListTable.product-list-table-fit thead tr:nth-child(2) th[data-column="net-amt-tax"],
    #productSelectionModal #productListTable.product-list-table-fit thead tr:nth-child(2) th[data-column="reverse"] {
        color: #ffffff !important;
        background: linear-gradient(180deg, #c5a864 0%, #a68a4a 100%) !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    }
    #productListTablePage.product-list-table-fit thead tr:nth-child(2) th[data-column="net-amt-tax"] .feather,
    #productListTablePage.product-list-table-fit thead tr:nth-child(2) th[data-column="reverse"] .feather,
    #productListTable.product-list-table-fit thead tr:nth-child(2) th[data-column="net-amt-tax"] .feather,
    #productListTable.product-list-table-fit thead tr:nth-child(2) th[data-column="reverse"] .feather,
    #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column="net-amt-tax"] .feather,
    #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column="reverse"] .feather {
        color: rgba(255, 255, 255, 0.95) !important;
    }
    #productListTablePage.product-list-table-fit thead tr:nth-child(2) th[data-column="net-amt-tax"] .product-modal-col-drag-handle,
    #productListTablePage.product-list-table-fit thead tr:nth-child(2) th[data-column="reverse"] .product-modal-col-drag-handle,
    #productListTable.product-list-table-fit thead tr:nth-child(2) th[data-column="net-amt-tax"] .product-modal-col-drag-handle,
    #productListTable.product-list-table-fit thead tr:nth-child(2) th[data-column="reverse"] .product-modal-col-drag-handle,
    #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column="net-amt-tax"] .product-modal-col-drag-handle,
    #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column="reverse"] .product-modal-col-drag-handle {
        color: rgba(255, 255, 255, 0.9) !important;
        opacity: 1;
    }
    #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column="net-amt-tax"],
    #productSelectionModal #productListTable thead tr:nth-child(2) th[data-column="reverse"] {
        color: #ffffff !important;
        background: linear-gradient(180deg, #c5a864 0%, #a68a4a 100%) !important;
    }
    #productListTable tbody tr:hover,
    #productListTablePage tbody tr:hover {
        background: #f8fafc;
    }
    /* Ensure sticky columns work in tbody cells */
    #productListTable tbody tr:hover td[data-column="actions"],
    #productListTable tbody tr:hover td[data-column="images"],
    #productListTable tbody tr:hover td[data-column="reverse"],
    #productListTable tbody tr:hover td[data-column="net-amt-tax"],
    #productListTablePage tbody tr:hover td[data-column="actions"],
    #productListTablePage tbody tr:hover td[data-column="images"],
    #productListTablePage tbody tr:hover td[data-column="reverse"],
    #productListTablePage tbody tr:hover td[data-column="net-amt-tax"] {
        background: #f8fafc;
    }
    #productListTable tbody tr.selected,
    #productListTablePage tbody tr.selected {
        background: #fff3cd !important;
    }
    #productListTable tbody tr.selected:hover,
    #productListTablePage tbody tr.selected:hover {
        background: #ffe69c !important;
    }
    #productListTable input.form-control-sm,
    #productListTable select.form-control-sm,
    #productListTablePage input.form-control-sm,
    #productListTablePage select.form-control-sm {
        border: 1px solid #e2e8f0;
        padding: 0.25rem 0.4rem;
        font-size: 0.7rem;
    }
    #productListTable input.form-control-sm:focus,
    #productListTable select.form-control-sm:focus,
    #productListTablePage input.form-control-sm:focus,
    #productListTablePage select.form-control-sm:focus {
        border-color: #c5a864;
        outline: none;
        box-shadow: 0 0 0 2px rgba(197, 168, 100, 0.2);
    }
    #productListTable tbody tr.product-row.selected,
    #productListTablePage tbody tr.product-row.selected {
        background-color: #fff3cd !important;
    }
    #productListTable tbody tr.product-row:hover,
    #productListTablePage tbody tr.product-row:hover {
        background-color: #f8f9fa;
    }
    #productListTable tbody tr,
    #productListTablePage tbody tr {
        cursor: pointer;
        transition: all 0.2s ease;
    }
    #productListTable tbody tr:hover,
    #productListTablePage tbody tr:hover {
        background: rgba(197, 168, 100, 0.1);
    }
    #productListTable tbody tr.selected,
    #productListTablePage tbody tr.selected {
        background: rgba(17, 41, 75, 0.1);
        border-left: 3px solid #c5a864;
    }
    #productListTable thead th.group-end,
    #productListTablePage thead th.group-end,
    #productListTable tbody td.group-end,
    #productListTablePage tbody td.group-end {
        border-right: 2px solid #c9a44c;
    }
    /* Hallmark vs Net Amt+Tax / Reverse: distinct groups — dividers so they are not read as one block */
    #productListTable thead tr:first-child th[data-group="hallmark"],
    #productListTablePage thead tr:first-child th[data-group="hallmark"] {
        border-right: 2px solid #475569;
    }
    #productListTable thead tr:first-child th[data-group="net-reverse"],
    #productListTablePage thead tr:first-child th[data-group="net-reverse"] {
        border-left: 2px solid rgba(255, 255, 255, 0.65);
    }
    #productListTable thead tr:nth-child(2) th[data-column="net-amt-tax"],
    #productListTablePage thead tr:nth-child(2) th[data-column="net-amt-tax"] {
        border-left: 2px solid #475569;
    }
    #productListTable tbody td[data-column="net-amt-tax"],
    #productListTablePage tbody td[data-column="net-amt-tax"] {
        border-left: 2px solid #cbd5e1;
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
    
    /* Stock journal images column */
    .stock-journal-images-cell .sj-images-wrap {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 4px;
    }
    .stock-journal-images-cell .sj-image-btn {
        flex-shrink: 0;
    }
    .stock-journal-images-cell .sj-image-previews {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px;
        max-width: 140px;
    }
    .stock-journal-images-cell .sj-first-preview-wrap {
        position: relative;
        flex-shrink: 0;
    }
    .stock-journal-images-cell .sj-first-preview {
        width: 52px;
        height: 52px;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid #c5a864;
        display: block;
    }
    .stock-journal-images-cell .sj-more-badge {
        position: absolute;
        bottom: -2px;
        right: -2px;
        background: #11294b;
        color: #fff;
        font-size: 10px;
        font-weight: 600;
        min-width: 18px;
        height: 18px;
        border-radius: 9px;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 0 4px;
    }
    .stock-journal-images-cell .sj-thumb {
        width: 28px;
        height: 28px;
        object-fit: cover;
        border-radius: 4px;
        border: 1px solid #e2e8f0;
    }
    .stock-journal-images-cell .sj-thumb-wrap {
        position: relative;
    }
    .sj-photo-cell .sj-photo-first-wrap img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .stock-journal-images-cell .sj-thumb-remove {
        position: absolute;
        top: -4px;
        right: -4px;
        width: 14px;
        height: 14px;
        border-radius: 50%;
        background: #ef4444;
        color: #fff;
        border: none;
        cursor: pointer;
        font-size: 10px;
        line-height: 1;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
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

    /* Stock journal Add (Shift+A) — overlay while barcode is allocated and row is added */
    #sjAddLoader {
        position: fixed;
        inset: 0;
        z-index: 10050;
        display: none;
    }
    #sjAddLoader .sj-add-loader-backdrop {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, 0.35);
    }
    #sjAddLoader .sj-add-loader-panel {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: #fff;
        border-radius: 10px;
        padding: 1.25rem 1.75rem;
        box-shadow: 0 12px 40px rgba(15, 23, 42, 0.18);
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 220px;
    }
    #sjAddLoader .sj-add-loader-spinner {
        width: 28px;
        height: 28px;
        border: 3px solid #e2e8f0;
        border-top-color: #c5a864;
        border-radius: 50%;
        animation: sjAddSpin 0.85s linear infinite;
        flex-shrink: 0;
    }
    #sjAddLoader .sj-add-loader-text {
        font-size: 0.9rem;
        font-weight: 600;
        color: #1e293b;
    }
    @keyframes sjAddSpin {
        to { transform: rotate(360deg); }
    }
    .stock-journal-add-btn .sj-add-btn-spinner {
        display: inline-block;
        width: 14px;
        height: 14px;
        border: 2px solid rgba(255, 255, 255, 0.35);
        border-top-color: #fff;
        border-radius: 50%;
        animation: sjAddSpin 0.85s linear infinite;
        vertical-align: -2px;
        margin-right: 6px;
    }
</style>
</head>

<body>
<!-- [ Preloader ] Start -->
<div class="page-loader">
    <div class="bg-primary"></div>
</div>
<!-- [ Preloader ] End -->
<div id="sjAddLoader" aria-hidden="true" aria-live="polite">
    <div class="sj-add-loader-backdrop"></div>
    <div class="sj-add-loader-panel">
        <div class="sj-add-loader-spinner"></div>
        <div class="sj-add-loader-text">Adding product…</div>
    </div>
</div>

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
                                            if ($sj_active_tab_metal_id > 0) {
                                                $tab_class = ((int) $metal['id'] === $sj_active_tab_metal_id) ? 'active' : '';
                                            } else {
                                                $tab_class = $first_metal ? 'active' : '';
                                            }
                                            $tab_id = 'sj-main-tab-' . (int) $metal['id'];
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
                                    
                                    <!-- Balance Display Section (show for purchase invoice item_id or product opening voucher) -->
                                    <?php if ($stock_detail_item): ?>
                                    <div class="balance-info-card" style="background: linear-gradient(135deg, #11294b 0%, #1a3a5c 100%); color: #fff; padding: 12px 16px; border-radius: 10px; margin-bottom: 12px; box-shadow: 0 4px 15px rgba(17, 41, 75, 0.3);">
                                        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 12px;">
                                            <!-- Voucher Badge (Purchase Invoice or Product Opening) -->
                                            <div style="background: rgba(255,255,255,0.15); padding: 8px 14px; border-radius: 8px; display: flex; align-items: center; gap: 8px;">
                                                <i class="feather icon-file-text" style="font-size: 16px; opacity: 0.9;"></i>
                                                <div>
                                                    <div style="font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.7;"><?php echo htmlspecialchars($stock_detail_label); ?></div>
                                                    <div style="font-size: 14px; font-weight: 700;"><?php echo htmlspecialchars($stock_detail_item['invoice_no'] ?? 'N/A'); ?></div>
                                                </div>
                                            </div>
                                            
                                            <!-- Quantity Section -->
                                            <div style="background: rgba(255,255,255,0.1); padding: 8px 14px; border-radius: 8px; display: flex; align-items: center; gap: 16px; flex: 1; min-width: 280px;">
                                                <div style="text-align: center;">
                                                    <div style="font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.7;">Total Qty</div>
                                                    <div id="totalQuantityDisplay" style="font-size: 15px; font-weight: 700; color: #93c5fd;"><?php echo number_format($stock_detail_item['total_quantity'] ?? 0, 2); ?></div>
                                                </div>
                                                <div style="text-align: center;">
                                                    <div style="font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.7;">Used Qty</div>
                                                    <div id="usedQuantityDisplay" style="font-size: 15px; font-weight: 700; color: #fca5a5;"><?php echo number_format($stock_detail_item['existing_used_quantity'] ?? 0, 2); ?></div>
                                                </div>
                                                <div style="text-align: center;">
                                                    <div style="font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.7;">Balance Qty</div>
                                                    <div id="balanceQuantityDisplay" style="font-size: 15px; font-weight: 700; color: #86efac;"><?php echo number_format(($stock_detail_item['total_quantity'] ?? 0) - ($stock_detail_item['existing_used_quantity'] ?? 0), 2); ?></div>
                                                </div>
                                            </div>
                                            
                                            <!-- Gross Weight Section -->
                                            <div style="background: rgba(255,255,255,0.1); padding: 8px 14px; border-radius: 8px; display: flex; align-items: center; gap: 16px; flex: 1; min-width: 320px;">
                                                <div style="text-align: center;">
                                                    <div style="font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.7;">Total Gross Wt</div>
                                                    <div id="totalGrossWeightDisplay" style="font-size: 15px; font-weight: 700; color: #93c5fd;"><?php echo htmlspecialchars(auragold_format_weight_list($stock_detail_item['total_gross_weight'] ?? 0)); ?></div>
                                                </div>
                                                <div style="text-align: center;">
                                                    <div style="font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.7;">Used Gross Wt</div>
                                                    <div id="usedGrossWeightDisplay" style="font-size: 15px; font-weight: 700; color: #fca5a5;"><?php echo htmlspecialchars(auragold_format_weight_list($stock_detail_item['existing_used_gross_weight'] ?? 0)); ?></div>
                                                </div>
                                                <div style="text-align: center;">
                                                    <div style="font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.7;">Balance Gross Wt</div>
                                                    <div id="balanceGrossWeightDisplay" style="font-size: 15px; font-weight: 700; color: #86efac;"><?php echo htmlspecialchars(auragold_format_weight_list((float)($stock_detail_item['total_gross_weight'] ?? 0) - (float)($stock_detail_item['existing_used_gross_weight'] ?? 0))); ?></div>
                                                </div>
                                            </div>
                                            
                                            <!-- Barcodes Badge -->
                                            <div style="background: linear-gradient(135deg, #c5a864 0%, #d4af37 100%); padding: 8px 14px; border-radius: 8px; display: flex; align-items: center; gap: 8px; box-shadow: 0 2px 8px rgba(197, 168, 100, 0.4);">
                                                <i class="feather icon-tag" style="font-size: 16px;color:#fff"></i>
                                                <div>
                                                    <div style="font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.8;">Barcodes</div>
                                                    <div id="totalBarcodesDisplay" style="font-size: 15px; font-weight: 700;">0</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Column Visibility Settings -->
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 style="margin: 0; font-size: 0.9rem; font-weight: 700; color: #1e293b;">Product Selection</h6>
                                        <div class="d-flex align-items-center" style="gap: 0.5rem;">
                                            <?php if (!empty($sj_excel_sample_import_enabled)): ?>
                                            <?php
                                            $sj_excel_sample_href = ($voucher_type_param === 'purchase_invoice')
                                                ? 'ajax/download-stock-journal-excel-sample.php?voucher=purchase_invoice&item_id=' . (int) $edit_item_id . '&product_id=' . (int) $product_id_param . '&characteristic_id=' . (int) $characteristic_id_param
                                                : 'ajax/download-stock-journal-excel-sample.php?voucher=product_opening&product_id=' . (int) $product_id_param . '&characteristic_id=' . (int) $characteristic_id_param;
                                            if ($sj_context_metal_id > 0) {
                                                $sj_excel_sample_href .= '&metal_id=' . (int) $sj_context_metal_id;
                                            }
                                            ?>
                                            <a href="<?php echo htmlspecialchars($sj_excel_sample_href, ENT_QUOTES, 'UTF-8'); ?>" id="sjExcelSampleDownload" class="btn btn-sm" data-sj-excel-sample-base="<?php echo htmlspecialchars($sj_excel_sample_href, ENT_QUOTES, 'UTF-8'); ?>" title="Download template for the active metal tab (Gold / Silver / Diamond columns + extra fields), fill rows, then use Excel import" style="background: #334155; color: #fff; border: none; padding: 0.45rem 0.9rem; border-radius: 6px; font-size: 0.8rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.25rem;">
                                                <i class="feather icon-download"></i> Sample Excel
                                            </a>
                                            <button type="button" class="btn btn-sm" id="sjExcelImportBtn" title="Upload .xlsx: rows load into the Product List with generated barcodes; stock is updated only when you click Save Stock Journal. Metal Qty and Gross Wt. columns are required per row." style="background: #0f766e; color: #fff; border: none; padding: 0.45rem 0.9rem; border-radius: 6px; font-size: 0.8rem; font-weight: 600;">
                                                <i class="feather icon-upload"></i> Excel import
                                            </button>
                                            <input type="file" id="sjExcelImportFile" accept=".xlsx,.xls" style="display: none;" tabindex="-1">
                                            <?php endif; ?>
                                            <?php if ($stock_journal_show_add_product_row): ?>
                                            <button type="button" class="table-settings-btn sj-add-product-row-btn" id="addProductRowBtnPage" style="background: #c5a864; color: #fff; border: none; padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer; font-size: 0.85rem; display: flex; align-items: center; gap: 0.25rem;">
                                                <i class="feather icon-plus"></i> Add Product
                                            </button>
                                            <?php endif; ?>
                                            <div class="table-settings-wrapper">
                                                <button type="button" class="table-settings-btn" id="modalTableSettingsBtnPage">
                                                    <i class="feather icon-settings"></i> Show/Hide Columns
                                                </button>
                                            <div class="table-settings-dropdown sj-sj-column-dropdown" id="modalTableSettingsDropdown">
                                                <h6>Show/Hide Columns</h6>
                                                <div class="table-settings-search">
                                                    <input type="text" class="form-control form-control-sm sj-modal-table-settings-search" placeholder="Search columns..." autocomplete="off" aria-label="Search columns">
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-id" data-column="id" checked>
                                                    <label for="modal-col-id">Id</label>
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
                                                    <input type="checkbox" id="modal-col-photo" data-column="photo" checked>
                                                    <label for="modal-col-photo">Photo</label>
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
                                                    <input type="checkbox" id="modal-col-item-code" data-column="item-code" checked>
                                                    <label for="modal-col-item-code">Item Code</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-product-category" data-column="product-category" checked>
                                                    <label for="modal-col-product-category">Category (product)</label>
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
                                                    <input type="checkbox" id="modal-col-product" data-column="product" checked>
                                                    <label for="modal-col-product">Product</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-location" data-column="location" checked>
                                                    <label for="modal-col-location">Location</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-pkt-wt" data-column="pkt-wt" checked>
                                                    <label for="modal-col-pkt-wt">Pkt. Wt.</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-pkt-less-wt" data-column="pkt-less-wt" checked>
                                                    <label for="modal-col-pkt-less-wt">PKt. Less Wt.</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-gross-wt" data-column="gross-wt" checked>
                                                    <label for="modal-col-gross-wt">Gross Wt.</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-stone-weight" data-column="stone-weight" checked>
                                                    <label for="modal-col-stone-weight">Carat</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-less-wt" data-column="less-wt" checked>
                                                    <label for="modal-col-less-wt">D.Weight</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-net-wt" data-column="net-wt" checked>
                                                    <label for="modal-col-net-wt">Net Wt.</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-quantity" data-column="quantity" checked>
                                                    <label for="modal-col-quantity">Quantity</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-rate" data-column="rate" checked>
                                                    <label for="modal-col-rate">Rate</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-fc-amount" data-column="fc-amount" checked>
                                                    <label for="modal-col-fc-amount">FC Amount</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-diamond-line-metal" data-column="diamond-line-metal-value" checked>
                                                    <label for="modal-col-diamond-line-metal">Metal Value (line)</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-rapnet" data-column="rapnet-valuation" checked>
                                                    <label for="modal-col-rapnet">RapNet Valuation</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-setting-charge" data-column="setting-charge" checked>
                                                    <label for="modal-col-setting-charge">Setting Charge</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-stone-amount" data-column="stone-amount" checked>
                                                    <label for="modal-col-stone-amount">Setting Charge Amt.</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-mark-up-amount" data-column="mark-up-amount" checked>
                                                    <label for="modal-col-mark-up-amount">Mark Up Amt.</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-mark-up-per" data-column="mark-up-per" checked>
                                                    <label for="modal-col-mark-up-per">Mark Up %</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-amount" data-column="amount" checked>
                                                    <label for="modal-col-amount">Amount</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-metal-qty" data-column="metal-qty" checked>
                                                    <label for="modal-col-metal-qty">Metal Qty</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-metal-weight" data-column="metal-weight" checked>
                                                    <label for="modal-col-metal-weight">Weight</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-carat" data-column="carat" checked>
                                                    <label for="modal-col-carat">Carat</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-purity" data-column="purity" checked>
                                                    <label for="modal-col-purity">Purity %</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-purity-wt" data-column="purity-wt" checked>
                                                    <label for="modal-col-purity-wt">Purity Wt</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-gold-loss1" data-column="gold-loss1" checked>
                                                    <label for="modal-col-gold-loss1">Loss Wt.</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-gold-loss2" data-column="gold-loss2" checked>
                                                    <label for="modal-col-gold-loss2">Loss Wt. Per</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-metal-loss-value" data-column="metal-loss-value" checked>
                                                    <label for="modal-col-metal-loss-value">Loss Value</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-wastage-per" data-column="wastage-per" checked>
                                                    <label for="modal-col-wastage-per">Wastage Per</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-wastage-wt" data-column="wastage-wt" checked>
                                                    <label for="modal-col-wastage-wt">Wastage Wt</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-metal-rate" data-column="metal-rate" checked>
                                                    <label for="modal-col-metal-rate">Metal Rate</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-metal-value" data-column="metal-value" checked>
                                                    <label for="modal-col-metal-value">Actual Metal Value</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-purchase-rate" data-column="purchase-rate" checked>
                                                    <label for="modal-col-purchase-rate">Actual Purchase Rate</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-metal-cost" data-column="metal-cost" checked>
                                                    <label for="modal-col-metal-cost">Metal Cost</label>
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
                                                    <input type="checkbox" id="modal-col-final-wt" data-column="final-wt" checked>
                                                    <label for="modal-col-final-wt">Final Wt.</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-alloy-wt" data-column="alloy-wt" checked>
                                                    <label for="modal-col-alloy-wt">Alloy Wt.</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-platinum-weight" data-column="platinum-weight" checked>
                                                    <label for="modal-col-platinum-weight">Pt. Wt.</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-platinum-karat" data-column="platinum-karat" checked>
                                                    <label for="modal-col-platinum-karat">Pt. Karat</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-platinum-purity" data-column="platinum-purity" checked>
                                                    <label for="modal-col-platinum-purity">Pt. Purity %</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-platinum-purity-wt" data-column="platinum-purity-wt" checked>
                                                    <label for="modal-col-platinum-purity-wt">Pt. Purity Wt</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-platinum-rate" data-column="platinum-rate" checked>
                                                    <label for="modal-col-platinum-rate">Pt. Rate</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-platinum-wastage-per" data-column="platinum-wastage-per" checked>
                                                    <label for="modal-col-platinum-wastage-per">Pt. Wastg. %</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-platinum-wastage-wt" data-column="platinum-wastage-wt" checked>
                                                    <label for="modal-col-platinum-wastage-wt">Pt. Wastg. Wt</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-platinum-amount" data-column="platinum-amount" checked>
                                                    <label for="modal-col-platinum-amount">Pt. Amount</label>
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
                                                    <input type="checkbox" id="modal-col-making-discount-amt" data-column="making-discount-amt" checked>
                                                    <label for="modal-col-making-discount-amt">Making Discount Amt.</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-making-amount" data-column="making-amount" checked>
                                                    <label for="modal-col-making-amount">Making Amount</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-making-actual-value" data-column="making-actual-value" checked>
                                                    <label for="modal-col-making-actual-value">Actual Making Value</label>
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
                                                    <input type="checkbox" id="modal-col-minimum" data-column="minimum" checked>
                                                    <label for="modal-col-minimum">Minimum Code</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-stone-charge-type" data-column="stone-charge-type" checked>
                                                    <label for="modal-col-stone-charge-type">Stone Charge Type</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-stone-rate" data-column="stone-rate" checked>
                                                    <label for="modal-col-stone-rate">Stone Rate</label>
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
                                                    <input type="checkbox" id="modal-col-sale-percent" data-column="sale-percent" checked>
                                                    <label for="modal-col-sale-percent">Sale Percentages</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-sale-amount" data-column="sale-amount" checked>
                                                    <label for="modal-col-sale-amount">Sale Amount</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-sale-amount-with" data-column="sale-amount-with" checked>
                                                    <label for="modal-col-sale-amount-with">Sale Amount With Tax</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-net-amt" data-column="net-amt" checked>
                                                    <label for="modal-col-net-amt">Net Amt</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-tax-type" data-column="tax-type" checked>
                                                    <label for="modal-col-tax-type">Tax Type</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-tax-percent" data-column="tax-percent" checked>
                                                    <label for="modal-col-tax-percent">Tax %</label>
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
                                                    <input type="checkbox" id="modal-col-certificate-amount" data-column="certificate-amount" checked>
                                                    <label for="modal-col-certificate-amount">Certificate Amt.</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-certificate-no" data-column="certificate-no" checked>
                                                    <label for="modal-col-certificate-no">Certificate No.</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-certificate-link" data-column="certificate-link" checked>
                                                    <label for="modal-col-certificate-link">Certificate Link</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-video-link" data-column="video-link" checked>
                                                    <label for="modal-col-video-link">Video Link</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-cut" data-column="cut" checked>
                                                    <label for="modal-col-cut">Cut</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-color" data-column="color" checked>
                                                    <label for="modal-col-color">Color</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-seive-size" data-column="seive-size" checked>
                                                    <label for="modal-col-seive-size">Seive Size</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-size" data-column="size" checked>
                                                    <label for="modal-col-size">Size</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-shape" data-column="shape" checked>
                                                    <label for="modal-col-shape">Shape</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-clarity" data-column="clarity" checked>
                                                    <label for="modal-col-clarity">Clarity</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-unit-price" data-column="unit-price" checked>
                                                    <label for="modal-col-unit-price">Unit Price</label>
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
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-images" data-column="images" checked>
                                                    <label for="modal-col-images">Images</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="modal-col-actions" data-column="actions" checked>
                                                    <label for="modal-col-actions">Action</label>
                                                </div>
                                            </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Product List Table with All Options - Horizontally Scrollable -->
                                    <div style="overflow-x: auto; overflow-y: auto; max-height: 500px; border: 1px solid #e2e8f0; border-radius: 6px;">
                                        <table class="table table-bordered table-sm mb-0 product-list-table-fit" id="productListTablePage" style="min-width: 4000px; font-size: 0.75rem;">
                                            <thead class="product-modal-thead">
                                                <!-- Group/leaf headers match includes/common-modal-product-selection.php (PRODUCT_MODAL_COLUMN_GROUPS + row cells). -->
                                                <tr style="font-weight: 600;">
                                                    <th colspan="13" data-group="basic-information" class="product-modal-group-header-th" style="text-align: center;"><span class="product-modal-group-drag-handle" title="Drag to move this entire column group"><?php echo $sj_drag_icons; ?></span><span class="product-modal-group-label">Basic Information</span></th>
                                                    <th colspan="16" data-group="diamond-group" class="product-modal-group-header-th" style="text-align: center;"><span class="product-modal-group-drag-handle" title="Drag to move this entire column group"><?php echo $sj_drag_icons; ?></span><span class="product-modal-group-label">Diamond group</span></th>
                                                    <th colspan="14" data-group="metal-group" class="product-modal-group-header-th" style="text-align: center;"><span class="product-modal-group-drag-handle" title="Drag to move this entire column group"><?php echo $sj_drag_icons; ?></span><span class="product-modal-group-label">Metal group</span></th>
                                                    <th colspan="4" data-group="request-final-group" class="product-modal-group-header-th" style="text-align: center;"><span class="product-modal-group-drag-handle" title="Drag to move this entire column group"><?php echo $sj_drag_icons; ?></span><span class="product-modal-group-label">Request &amp; Final Wt.</span></th>
                                                    <th colspan="8" data-group="platinum-group" class="product-modal-group-header-th" style="text-align: center;"><span class="product-modal-group-drag-handle" title="Drag to move this entire column group"><?php echo $sj_drag_icons; ?></span><span class="product-modal-group-label">Platinum (group)</span></th>
                                                    <th colspan="4" data-group="discount-group" class="product-modal-group-header-th" style="text-align: center;"><span class="product-modal-group-drag-handle" title="Drag to move this entire column group"><?php echo $sj_drag_icons; ?></span><span class="product-modal-group-label">Discount (group)</span></th>
                                                    <th colspan="6" data-group="making-group" class="product-modal-group-header-th" style="text-align: center;"><span class="product-modal-group-drag-handle" title="Drag to move this entire column group"><?php echo $sj_drag_icons; ?></span><span class="product-modal-group-label">Making (group)</span></th>
                                                    <th colspan="2" data-group="minimum-group" class="product-modal-group-header-th" style="text-align: center;"><span class="product-modal-group-drag-handle" title="Drag to move this entire column group"><?php echo $sj_drag_icons; ?></span><span class="product-modal-group-label">Minimum</span></th>
                                                    <th colspan="4" data-group="stone-group" class="product-modal-group-header-th" style="text-align: center;"><span class="product-modal-group-drag-handle" title="Drag to move this entire column group"><?php echo $sj_drag_icons; ?></span><span class="product-modal-group-label">Stone group</span></th>
                                                    <th colspan="8" data-group="amounts" class="product-modal-group-header-th" style="text-align: center;"><span class="product-modal-group-drag-handle" title="Drag to move this entire column group"><?php echo $sj_drag_icons; ?></span><span class="product-modal-group-label">Amounts</span></th>
                                                    <th colspan="5" data-group="other-charge-group" class="product-modal-group-header-th" style="text-align: center;"><span class="product-modal-group-drag-handle" title="Drag to move this entire column group"><?php echo $sj_drag_icons; ?></span><span class="product-modal-group-label">Other Charge (group)</span></th>
                                                    <th colspan="11" data-group="cert-spec-group" class="product-modal-group-header-th" style="text-align: center;"><span class="product-modal-group-drag-handle" title="Drag to move this entire column group"><?php echo $sj_drag_icons; ?></span><span class="product-modal-group-label">Certificate &amp; spec</span></th>
                                                    <th colspan="2" data-group="hallmark" class="product-modal-group-header-th" style="text-align: center;"><span class="product-modal-group-drag-handle" title="Drag to move this entire column group"><?php echo $sj_drag_icons; ?></span><span class="product-modal-group-label">Hallmark</span></th>
                                                    <th colspan="2" data-group="net-reverse" data-group-locked="1" style="text-align: center;"><?php echo $sj_col_drag_locked; ?><span class="product-modal-group-label">Net Amt+Tax / Reverse</span></th>
                                                    <th rowspan="2" data-column="images" style="text-align: center; vertical-align: middle;"><?php echo $sj_col_drag_locked; ?>Images</th>
                                                    <th rowspan="2" data-column="actions" style="text-align: center; vertical-align: middle;"><?php echo $sj_col_drag_locked; ?>Action</th>
                                                </tr>
                                                <tr>
                                                    <th data-column="id" style="min-width: 60px; position: sticky; left: 0; background: #f8fafc; z-index: 7; box-shadow: 1px 0 0 #e2e8f0;"><?php echo $sj_col_drag; ?>Id</th>
                                                    <th data-column="rfid" style="min-width: 100px;"><?php echo $sj_col_drag; ?>RFIDCode</th>
                                                    <th data-column="voucher-type" style="min-width: 120px;"><?php echo $sj_col_drag; ?>voucherTypeId</th>
                                                    <th data-column="photo" style="min-width: 70px;"><?php echo $sj_col_drag; ?>Photo</th>
                                                    <th data-column="barcode" style="min-width: 120px;"><?php echo $sj_col_drag; ?>Barcode No.</th>
                                                    <th data-column="design-no" style="min-width: 100px;"><?php echo $sj_col_drag; ?>Design No</th>
                                                    <th data-column="huid" style="min-width: 100px;"><?php echo $sj_col_drag; ?>HUID No.</th>
                                                    <th data-column="item-code" style="min-width: 100px;"><?php echo $sj_col_drag; ?>Item Code</th>
                                                    <th data-column="category" style="min-width: 120px;"><?php echo $sj_col_drag; ?>Category <i class="feather icon-plus add-category-icon" style="font-size: 0.7rem; cursor: pointer;" title="Add New Category"></i></th>
                                                    <th data-column="product-category" style="min-width: 120px;"><?php echo $sj_col_drag; ?>Category (prod.)</th>
                                                    <th data-column="calculation" style="min-width: 140px;"><?php echo $sj_col_drag; ?>Calculation ...</th>
                                                    <th data-column="product" style="min-width: 120px;"><?php echo $sj_col_drag; ?>Product* <?php if ($stock_journal_show_add_product_row): ?><i class="feather icon-plus add-product-icon" style="font-size: 0.7rem; cursor: pointer;" title="Add New Product"></i><?php endif; ?></th>
                                                    <th data-column="location" style="min-width: 120px;"><?php echo $sj_col_drag; ?>Location <i class="feather icon-plus add-location-icon" style="font-size: 0.7rem; cursor: pointer;" title="Add Location"></i></th>
                                                    <th data-column="pkt-wt" style="min-width: 80px;"><?php echo $sj_col_drag; ?>Pkt. Wt.</th>
                                                    <th data-column="pkt-less-wt" style="min-width: 100px;"><?php echo $sj_col_drag; ?>PKt. Less Wt.</th>
                                                    <th data-column="gross-wt" style="min-width: 80px;"><?php echo $sj_col_drag; ?>Gross Wt.</th>
                                                    <th data-column="stone-weight" style="min-width: 110px;"><?php echo $sj_col_drag; ?>Carat / Stone Wt.</th>
                                                    <th data-column="less-wt" style="min-width: 80px;"><?php echo $sj_col_drag; ?>D.Weight</th>
                                                    <th data-column="net-wt" style="min-width: 80px;"><?php echo $sj_col_drag; ?>Net Wt.</th>
                                                    <th data-column="quantity" style="min-width: 80px;"><?php echo $sj_col_drag; ?>Quantity</th>
                                                    <th data-column="rate" style="min-width: 80px;"><?php echo $sj_col_drag; ?>Rate</th>
                                                    <th data-column="fc-amount" style="min-width: 90px;"><?php echo $sj_col_drag; ?>FC Amt</th>
                                                    <th data-column="diamond-line-metal-value" style="min-width: 100px;"><?php echo $sj_col_drag; ?>Metal Val (line)</th>
                                                    <th data-column="rapnet-valuation" style="min-width: 100px;"><?php echo $sj_col_drag; ?>RapNet</th>
                                                    <th data-column="setting-charge" style="min-width: 110px;"><?php echo $sj_col_drag; ?>Setting Ch.</th>
                                                    <th data-column="stone-amount" style="min-width: 120px;"><?php echo $sj_col_drag; ?>Setting Ch. Amt.</th>
                                                    <th data-column="mark-up-amount" style="min-width: 100px;"><?php echo $sj_col_drag; ?>Mark Up Amt.</th>
                                                    <th data-column="mark-up-per" style="min-width: 80px;"><?php echo $sj_col_drag; ?>Mark Up %</th>
                                                    <th data-column="amount" style="min-width: 100px;"><?php echo $sj_col_drag; ?>Amount</th>
                                                    <th data-column="metal-qty" style="min-width: 80px;"><?php echo $sj_col_drag; ?>Metal Qty</th>
                                                    <th data-column="metal-weight" style="min-width: 80px;"><?php echo $sj_col_drag; ?>Weight</th>
                                                    <th data-column="carat" style="min-width: 80px;"><?php echo $sj_col_drag; ?>Karat <i class="feather icon-plus" style="font-size: 0.7rem; cursor: pointer;"></i></th>
                                                    <th data-column="purity" style="min-width: 80px;"><?php echo $sj_col_drag; ?>Purity %</th>
                                                    <th data-column="purity-wt" style="min-width: 90px;"><?php echo $sj_col_drag; ?>Purity Wt</th>
                                                    <th data-column="gold-loss1" style="min-width: 100px;"><?php echo $sj_col_drag; ?>Loss Wt.</th>
                                                    <th data-column="gold-loss2" style="min-width: 100px;"><?php echo $sj_col_drag; ?>Loss Wt. Per</th>
                                                    <th data-column="metal-loss-value" style="min-width: 100px;"><?php echo $sj_col_drag; ?>Loss Value</th>
                                                    <th data-column="wastage-per" style="min-width: 100px;"><?php echo $sj_col_drag; ?>Wastage Per</th>
                                                    <th data-column="wastage-wt" style="min-width: 100px;"><?php echo $sj_col_drag; ?>Wastage Wt</th>
                                                    <th data-column="metal-rate" style="min-width: 90px;"><?php echo $sj_col_drag; ?>Metal Rate</th>
                                                    <th data-column="metal-value" style="min-width: 100px;"><?php echo $sj_col_drag; ?>Actual Metal Value</th>
                                                    <th data-column="purchase-rate" style="min-width: 100px;"><?php echo $sj_col_drag; ?>Actual Purchase Rate</th>
                                                    <th data-column="metal-cost" style="min-width: 100px;"><?php echo $sj_col_drag; ?>Metal Cost</th>
                                                    <th data-column="requested-purity" style="min-width: 120px;"><?php echo $sj_col_drag; ?>Requested Pu...</th>
                                                    <th data-column="requested" style="min-width: 100px;"><?php echo $sj_col_drag; ?>Requested</th>
                                                    <th data-column="final-wt" style="min-width: 80px;"><?php echo $sj_col_drag; ?>Final Wt.</th>
                                                    <th data-column="alloy-wt" style="min-width: 80px;"><?php echo $sj_col_drag; ?>Alloy Wt.</th>
                                                    <th data-column="platinum-weight" style="min-width: 80px;"><?php echo $sj_col_drag; ?>Pt. Wt.</th>
                                                    <th data-column="platinum-karat" style="min-width: 80px;"><?php echo $sj_col_drag; ?>Pt. Karat</th>
                                                    <th data-column="platinum-purity" style="min-width: 80px;"><?php echo $sj_col_drag; ?>Pt. Purity %</th>
                                                    <th data-column="platinum-purity-wt" style="min-width: 90px;"><?php echo $sj_col_drag; ?>Pt. Purity Wt</th>
                                                    <th data-column="platinum-rate" style="min-width: 90px;"><?php echo $sj_col_drag; ?>Pt. Rate</th>
                                                    <th data-column="platinum-wastage-per" style="min-width: 100px;"><?php echo $sj_col_drag; ?>Pt. Wastg. %</th>
                                                    <th data-column="platinum-wastage-wt" style="min-width: 100px;"><?php echo $sj_col_drag; ?>Pt. Wastg. Wt</th>
                                                    <th data-column="platinum-amount" style="min-width: 100px;"><?php echo $sj_col_drag; ?>Pt. Amount</th>
                                                    <th data-column="discount-type" style="min-width: 100px;"><?php echo $sj_col_drag; ?>Type</th>
                                                    <th data-column="discount-per" style="min-width: 80px;"><?php echo $sj_col_drag; ?>Per.</th>
                                                    <th data-column="discount-amount" style="min-width: 100px;"><?php echo $sj_col_drag; ?>Amount</th>
                                                    <th data-column="discount" style="min-width: 100px;"><?php echo $sj_col_drag; ?>Discount</th>
                                                    <th data-column="making-type" style="min-width: 100px;"><?php echo $sj_col_drag; ?>Type</th>
                                                    <th data-column="making-rate" style="min-width: 100px;"><?php echo $sj_col_drag; ?>Rate</th>
                                                    <th data-column="making-discount-amt" style="min-width: 130px;"><?php echo $sj_col_drag; ?>Discount Amount</th>
                                                    <th data-column="making-amount" style="min-width: 100px;"><?php echo $sj_col_drag; ?>Amount</th>
                                                    <th data-column="making-actual-value" style="min-width: 120px;"><?php echo $sj_col_drag; ?>Actual Making Value</th>
                                                    <th data-column="making-cost" style="min-width: 110px;"><?php echo $sj_col_drag; ?>Making Cost</th>
                                                    <th data-column="min-price" style="min-width: 100px;"><?php echo $sj_col_drag; ?>Minimum Price</th>
                                                    <th data-column="minimum" style="min-width: 100px;"><?php echo $sj_col_drag; ?>Minimum ...</th>
                                                    <th data-column="stone-charge-type" style="min-width: 100px;"><?php echo $sj_col_drag; ?>Type</th>
                                                    <th data-column="stone-rate" style="min-width: 100px;"><?php echo $sj_col_drag; ?>Stone Rate</th>
                                                    <th data-column="stone-cost" style="min-width: 100px;"><?php echo $sj_col_drag; ?>Stone Cost</th>
                                                    <th data-column="diamond-amount" style="min-width: 120px;"><?php echo $sj_col_drag; ?>Diamond Amount</th>
                                                    <th data-column="purchase-amount" style="min-width: 130px;"><?php echo $sj_col_drag; ?>Purchase Amount</th>
                                                    <th data-column="sale-percent" style="min-width: 100px;"><?php echo $sj_col_drag; ?>Sale Percentages</th>
                                                    <th data-column="sale-amount" style="min-width: 110px;"><?php echo $sj_col_drag; ?>Sale Amount</th>
                                                    <th data-column="sale-amount-with" style="min-width: 130px;"><?php echo $sj_col_drag; ?>Sale Amount Wi...</th>
                                                    <th data-column="net-amt" style="min-width: 100px;"><?php echo $sj_col_drag; ?>Net Amt</th>
                                                    <th data-column="tax-type" style="min-width: 120px;"><?php echo $sj_col_drag; ?>Tax Type</th>
                                                    <th data-column="tax-percent" style="min-width: 70px;"><?php echo $sj_col_drag; ?>Tax %</th>
                                                    <th data-column="tax" style="min-width: 80px;"><?php echo $sj_col_drag; ?>Tax</th>
                                                    <th data-column="other-charge-type" style="min-width: 100px;"><?php echo $sj_col_drag; ?>Type</th>
                                                    <th data-column="other-weight" style="min-width: 110px;"><?php echo $sj_col_drag; ?>Other Weight</th>
                                                    <th data-column="other-rate" style="min-width: 100px;"><?php echo $sj_col_drag; ?>Other Rate</th>
                                                    <th data-column="other-info" style="min-width: 100px;"><?php echo $sj_col_drag; ?>Other Info</th>
                                                    <th data-column="other-amount" style="min-width: 120px;"><?php echo $sj_col_drag; ?>Other Amount</th>
                                                    <th data-column="certificate-amount" style="min-width: 110px;"><?php echo $sj_col_drag; ?>Certificate Amt.</th>
                                                    <th data-column="certificate-no" style="min-width: 110px;"><?php echo $sj_col_drag; ?>Certificate No.</th>
                                                    <th data-column="certificate-link" style="min-width: 120px;"><?php echo $sj_col_drag; ?>Certificate Link</th>
                                                    <th data-column="video-link" style="min-width: 120px;"><?php echo $sj_col_drag; ?>Video Link</th>
                                                    <th data-column="cut" style="min-width: 100px;"><?php echo $sj_col_drag; ?>Cut</th>
                                                    <th data-column="color" style="min-width: 100px;"><?php echo $sj_col_drag; ?>Color</th>
                                                    <th data-column="seive-size" style="min-width: 90px;"><?php echo $sj_col_drag; ?>Seive</th>
                                                    <th data-column="size" style="min-width: 80px;"><?php echo $sj_col_drag; ?>Size</th>
                                                    <th data-column="shape" style="min-width: 100px;"><?php echo $sj_col_drag; ?>Shape</th>
                                                    <th data-column="clarity" style="min-width: 100px;"><?php echo $sj_col_drag; ?>Clarity</th>
                                                    <th data-column="unit-price" style="min-width: 90px;"><?php echo $sj_col_drag; ?>Unit Price</th>
                                                    <th data-column="hallmark-amount" style="min-width: 130px;"><?php echo $sj_col_drag; ?>Hallmark A...</th>
                                                    <th data-column="hallmark-rate" style="min-width: 120px;"><?php echo $sj_col_drag; ?>HallMark Rate</th>
                                                    <th data-column="net-amt-tax" style="min-width: 120px; vertical-align: middle;"><?php echo $sj_col_drag_locked; ?>Net Amt+Tax</th>
                                                    <th data-column="reverse" style="min-width: 80px; vertical-align: middle;"><?php echo $sj_col_drag_locked; ?>Reverse</th>
                                                </tr>
                                            </thead>
                                            <tbody id="productListBodyPage">
                                                <tr>
                                                    <td colspan="104" class="text-center text-muted py-4">Click "Add Product" button to add products for billing...</td>
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
                                        <button type="button" class="btn btn-purple btn-sm stock-journal-add-btn" id="modalAddBtn">
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
                                                    <input type="checkbox" id="col-photo" data-column="photo" checked>
                                                    <label for="col-photo">Photo</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="col-barcode" data-column="barcode" checked>
                                                    <label for="col-barcode">Barcode</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="col-description" data-column="description" checked>
                                                    <label for="col-description">Product</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="col-location" data-column="location" checked>
                                                    <label for="col-location">Location</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="col-quantity" data-column="quantity" checked>
                                                    <label for="col-quantity">Quantity</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="col-carat" data-column="carat" checked>
                                                    <label for="col-carat">Karat</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="col-pkt-wt" data-column="pkt-wt" checked>
                                                    <label for="col-pkt-wt">Pkt. Wt.</label>
                                                </div>
                                                <div class="table-settings-item">
                                                    <input type="checkbox" id="col-pkt-less-wt" data-column="pkt-less-wt" checked>
                                                    <label for="col-pkt-less-wt">Pkt. Less Wt.</label>
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
                                                    <input type="checkbox" id="col-huid" data-column="huid" checked>
                                                    <label for="col-huid">HUID No.</label>
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
                                                    <th data-column="photo" style="width: 56px; text-align: center;">Photo</th>
                                                    <th data-column="barcode">
                                                        Barcode
                                                    </th>
                                                    <th data-column="description">
                                                        Product
                                                    </th>
                                                    <th data-column="location">
                                                        Location
                                                    </th>
                                                    <th data-column="quantity">
                                                        Quantity
                                                    </th>
                                                    <th data-column="carat">
                                                        Karat
                                                    </th>
                                                    <th data-column="pkt-wt">
                                                        Pkt. Wt.
                                                    </th>
                                                    <th data-column="pkt-less-wt">
                                                        Pkt. Less Wt.
                                                    </th>
                                                    <th data-column="gross-wt">
                                                        Gross Wt.
                                                    </th>
                                                    <th data-column="less-wt">
                                                        Less Wt.
                                                    </th>
                                                    <th data-column="purity">
                                                        Purity
                                                    </th>
                                                    <th data-column="final-wt">
                                                        Final Wt.
                                                    </th>
                                                    <th data-column="net-wt">
                                                        Net Wt.
                                                    </th>
                                                    <th data-column="pure-wt">
                                                        Pure Wt
                                                    </th>
                                                    <th data-column="making">
                                                        Making
                                                    </th>
                                                    <th data-column="design-no">
                                                        Design No.
                                                    </th>
                                                    <th data-column="huid">
                                                        HUID No.
                                                    </th>
                                                    <th data-column="stone-charges">
                                                        Stone Charges
                                                    </th>
                                                    <th data-column="other-charges">
                                                        Other Charges
                                                    </th>
                                                    <th data-column="diamond-value">
                                                        Diamond Value
                                                    </th>
                                                    <th data-column="gemstone-value">
                                                        Gemstone Value
                                                    </th>
                                                    <th data-column="rate">
                                                        Rate
                                                    </th>
                                                    <th data-column="metal-value">
                                                        Actual Metal Value
                                                    </th>
                                                    <th data-column="discount">
                                                        Discount
                                                    </th>
                                                    <th data-column="making-amount">
                                                        Making Amount
                                                    </th>
                                                    <th data-column="stone-amount">
                                                        Stone Amount
                                                    </th>
                                                    <th data-column="other-amount">
                                                        Other Amount
                                                    </th>
                                                    <th data-column="diamond-amount">
                                                        Diamond Amount
                                                    </th>
                                                    <th data-column="purchase-amount">
                                                        Purchase Amount
                                                    </th>
                                                    <th data-column="sale-percent">
                                                        Sale Percentages
                                                    </th>
                                                    <th data-column="sale-amount">
                                                        Sale Amount
                                                    </th>
                                                    <th data-column="sale-amount-with">
                                                        Sale Amount With
                                                    </th>
                                                    <th data-column="reverse">
                                                        Reverse
                                                    </th>
                                                    <th data-column="tax">
                                                        Tax
                                                    </th>
                                                    <th data-column="amount">
                                                        Amount
                                                    </th>
                                                    <th data-column="net-amt">
                                                        Net Amt
                                                    </th>
                                                    <th data-column="net-amt-tax">
                                                        Net Amt With Tax
                                                    </th>
                                                    <th data-column="excel-extra" style="min-width: 168px; max-width: 300px;">
                                                        Excel (extra columns)
                                                    </th>
                                                    <th data-column="images" style="width: 100px; text-align: center;">Images</th>
                                                    <th style="width: 80px; text-align: center;">
                                                        <i class="feather icon-settings" style="cursor: pointer;"></i>
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody id="productTableBody">
                                                <tr class="no-drag">
                                                    <td colspan="39" class="text-center text-muted py-4" id="emptyRowCell">No Rows To Show</td>
                                                </tr>
                                            </tbody>
                                            <tfoot id="productTableFooter" style="display: none;">
                                                <tr style="background: #f8fafc; font-weight: 600;">
                                                    <td></td>
                                                    <td></td>
                                                    <td></td>
                                                    <td colspan="1" style="text-align: right; color: #11294b;">Grand Total:</td>
                                                    <td></td>
                                                    <td id="footerQuantity" style="text-align: right; color: #11294b;">0.00</td>
                                                    <td></td>
                                                    <td></td>
                                                    <td></td>
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
                                                    <td></td>
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
// Product selection modal: includes/includes/common-modal.php (same as Sale Invoice) with stock-journal flags; early-exit omits Add Image + duplicate Add Product from that file.
$common_modal_include_product_selection_only = true;
$common_modal_show_images_column = true;
$common_modal_show_checkbox_column = false;
$common_modal_show_checkbox_in_settings = false;
$common_modal_product_footer_mode = 'stock_journal';
$common_modal_show_add_product_in_header = $stock_journal_show_add_product_row;
$common_modal_show_add_product_icon = $stock_journal_show_add_product_row;
$common_modal_add_row_btn_id = 'addProductRowBtnModal';
$common_modal_add_row_btn_class = 'table-settings-btn sj-add-product-row-btn';
$common_modal_table_settings_btn_id = 'modalTableSettingsBtnModal';
$common_modal_table_settings_dropdown_id = 'modalTableSettingsDropdownModal';
$common_modal_table_settings_dropdown_class = 'table-settings-dropdown sj-sj-column-dropdown';
$common_modal_modal_body_attr = ' style="padding: 1.5rem;"';
$common_modal_pl_table_class_extra = 'product-list-table-fit';
$common_modal_pl_table_style = 'min-width: 4000px; font-size: 0.75rem;';
$common_modal_omit_table_settings_search_id = true;
$common_modal_table_settings_search_extra_class = 'sj-modal-table-settings-search';
ob_start();
include __DIR__ . '/includes/common-modal.php';
$__sj_ps = ob_get_clean();
$__sj_ps = str_replace('id="modal-col-', 'id="modal-col-m-', $__sj_ps);
$__sj_ps = str_replace('for="modal-col-', 'for="modal-col-m-', $__sj_ps);
echo $__sj_ps;
unset(
    $common_modal_include_product_selection_only,
    $common_modal_show_images_column,
    $common_modal_show_checkbox_column,
    $common_modal_show_checkbox_in_settings,
    $common_modal_product_footer_mode,
    $common_modal_show_add_product_in_header,
    $common_modal_show_add_product_icon,
    $common_modal_add_row_btn_id,
    $common_modal_add_row_btn_class,
    $common_modal_table_settings_btn_id,
    $common_modal_table_settings_dropdown_id,
    $common_modal_table_settings_dropdown_class,
    $common_modal_modal_body_attr,
    $common_modal_pl_table_class_extra,
    $common_modal_pl_table_style,
    $common_modal_omit_table_settings_search_id,
    $common_modal_table_settings_search_extra_class
);
?>

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
                                                <th rowspan="2" class="draggable carat-header" data-col="carat">Karat</th>
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
                                            // Metals + HSN: same branch-scoped list as category tabs (see top of file)
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
                                            <i class="feather icon-upload" style="font-size: 2.5rem; color: #c5a864; margin-bottom: 0.5rem;"></i>
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
                            <label>Purity / Karat</label>
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
                            <label>Purity / Karat</label>
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

<!-- Stock Journal: Add Image Modal (multiple images, one by one; like sale invoice) -->
<div class="modal fade" id="sjAddImageModal" tabindex="-1" role="dialog" aria-labelledby="sjAddImageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document" style="max-width: 480px;">
        <div class="modal-content">
            <div class="modal-header" style="border-bottom: 1px solid #e2e8f0; background: #11294b;">
                <h5 class="modal-title" id="sjAddImageModalLabel" style="color: #fff;">Add Image</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" id="sjAddImageModalClose" style="color: #fff; opacity: 1;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding: 1.25rem;">
                <div class="d-flex align-items-stretch" style="gap: 0.75rem;">
                    <div id="sjAddImagePreviewWrap" style="flex: 1; min-height: 180px; border: 1px dashed #cbd5e1; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: #f8fafc; overflow: hidden;">
                        <div id="sjAddImagePreviewPlaceholder" class="text-center text-muted" style="padding: 1rem;">
                            <i class="feather icon-image" style="font-size: 2rem; display: block; margin-bottom: 0.5rem;"></i>
                            <span style="font-size: 0.8rem;">NO PREVIEW AVAILABLE</span>
                        </div>
                        <img id="sjAddImagePreviewImg" src="" alt="Primary" style="max-width: 100%; max-height: 200px; object-fit: contain; display: none; border-radius: 6px;">
                    </div>
                    <div class="d-flex flex-column" style="gap: 0.5rem;">
                        <div id="sjAddImageThumbnailsWrap" class="d-flex flex-wrap" style="gap: 0.5rem; max-width: 120px;">
                            <div id="sjAddImageUploadZone" style="width: 70px; height: 70px; border: 2px dashed #94a3b8; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: #f1f5f9; cursor: pointer; transition: background 0.2s; flex-shrink: 0;">
                                <input type="file" id="sjAddImageModalFileInput" accept="image/jpeg,image/jpg,image/png,image/webp" multiple style="display: none;">
                                <i class="feather icon-upload" style="font-size: 1.5rem; color: #64748b;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <p class="text-muted small mt-2 mb-0">Click the upload area or use the camera below to add images one by one. Click a thumbnail to set as primary.</p>
            </div>
            <div class="modal-footer" style="border-top: 1px solid #e2e8f0; padding: 0.75rem 1.25rem;">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="sjAddImageModalCameraBtn" title="Select image(s)">
                    <i class="feather icon-camera" style="font-size: 1.1rem;"></i>
                </button>
                <div class="ml-auto">
                    <button type="button" class="btn btn-secondary btn-sm" id="sjAddImageModalCancelBtn">Cancel</button>
                    <button type="button" class="btn btn-purple btn-sm" id="sjAddImageModalSaveBtn">Save</button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer-script.php';?>

<script src="assets/js/product-modal-add-item-common.js"></script>
<script>
(function ensureSjPurchaseRateInMetalGroup() {
    var g = window.PRODUCT_MODAL_COLUMN_GROUPS;
    if (!g || !g['metal-group']) return;
    var mg = g['metal-group'];
    if (mg.indexOf('purchase-rate') !== -1) return;
    var vi = mg.indexOf('metal-value');
    if (vi >= 0) mg.splice(vi + 1, 0, 'purchase-rate');
    else {
        var ci = mg.indexOf('metal-cost');
        if (ci >= 0) mg.splice(ci, 0, 'purchase-rate');
        else mg.push('purchase-rate');
    }
})();
</script>
<script src="assets/js/product-list-table-shared.js"></script>
<script src="assets/libs/sortablejs/sortable.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<?php
if (!isset($auragold_voucher_runtime_client) || !is_array($auragold_voucher_runtime_client)) {
    $auragold_voucher_runtime_client = [];
}
include __DIR__ . '/includes/auragold_voucher_runtime_scripts.php';
?>
<script src="assets/js/stock-journal-column-drag.js"></script>
<script>
(function wrapSjColumnOrderSanitizerForPurchaseRate() {
    var orig = window.sanitizeStockJournalColumnOrderForGroups;
    if (typeof orig !== 'function') return;
    window.sanitizeStockJournalColumnOrderForGroups = function(order, columnGroups) {
        var sanitized = orig(order, columnGroups);
        if (!sanitized || sanitized.indexOf('purchase-rate') === -1) return sanitized;
        var mg = (columnGroups && columnGroups['metal-group']) || [];
        if (mg.indexOf('purchase-rate') === -1) return sanitized;
        var prIdx = sanitized.indexOf('purchase-rate');
        var afterMetalValue = sanitized.indexOf('metal-value');
        var beforeMetalCost = sanitized.indexOf('metal-cost');
        var targetIdx = afterMetalValue >= 0 ? afterMetalValue + 1 : (beforeMetalCost >= 0 ? beforeMetalCost : -1);
        if (targetIdx < 0 || prIdx === targetIdx || (prIdx === targetIdx - 1 && afterMetalValue >= 0)) return sanitized;
        sanitized = sanitized.filter(function(k) { return k !== 'purchase-rate'; });
        if (targetIdx > sanitized.length) targetIdx = sanitized.length;
        sanitized.splice(targetIdx, 0, 'purchase-rate');
        return orig(sanitized, columnGroups);
    };
})();
</script>
<script>
(function() {
    if (typeof window.clampProductModalScroll !== 'function') {
        window.clampProductModalScroll = function() {};
    }
    var PAGE_NAME = 'stock-journal-product-modal-order';
    var TAIL = ['net-amt-tax', 'reverse', 'images', 'actions'];
    /** Same tab_key scheme as column visibility: metal_id from active Gold/Silver/etc. tab (tbl_user_column_preferences.tab_key). */
    function getStockJournalColumnOrderTabKey() {
        var btn = document.querySelector('.product-category-tabs .category-tab-btn.active') || document.querySelector('.category-tab-btn.active');
        var id = null;
        if (btn) id = btn.getAttribute('data-metal-id');
        if ((id === null || id === undefined || String(id).trim() === '') && typeof window.sjCurrentMetalId !== 'undefined' && window.sjCurrentMetalId != null && String(window.sjCurrentMetalId).trim() !== '') {
            id = String(window.sjCurrentMetalId).trim();
        }
        return (id !== null && id !== undefined && String(id).trim() !== '') ? String(id).trim() : 'main';
    }
    function runStockJournalColumnDragInit() {
        if (typeof window.applyStockJournalSavedColumnOrderToAll !== 'function') return;
        var groups = window.PRODUCT_MODAL_COLUMN_GROUPS;
        if (!groups) return;
        var modalTable = document.querySelector('#productSelectionModal #productListTable');
        var modalBody = document.querySelector('#productSelectionModal #productListBody');
        var pageTable = document.getElementById('productListTablePage');
        var pageBody = document.getElementById('productListBodyPage');
        var pairs = [];
        if (pageTable && pageBody) pairs.push({ table: pageTable, tbody: pageBody });
        if (modalTable && modalBody) pairs.push({ table: modalTable, tbody: modalBody });
        if (!pairs.length) return;
        window.applyStockJournalSavedColumnOrderToAll(pairs, {
            pageName: PAGE_NAME,
            getTabKey: getStockJournalColumnOrderTabKey,
            columnGroups: groups,
            tailColumnKeys: TAIL,
            anchorColumnKey: 'net-amt-tax',
            syncGlobalProductModalLayout: true
        });
    }
    window.runStockJournalColumnDragInit = runStockJournalColumnDragInit;
    var modalEl = document.getElementById('productSelectionModal');
    if (modalEl && typeof jQuery !== 'undefined') {
        jQuery(modalEl).off('shown.bs.modal.stockJournalColDrag').on('shown.bs.modal.stockJournalColDrag', runStockJournalColumnDragInit);
    }
    if (document.getElementById('productListTablePage') && typeof jQuery !== 'undefined' && window.runStockJournalColumnDragInit) {
        jQuery(function () {
            setTimeout(function () { window.runStockJournalColumnDragInit(); }, 0);
        });
    }
})();
</script>

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
        if (e.target.closest('#productListBody, #productListBodyPage')) {
            const row = e.target.closest('.product-row');
            if (row) {
                // Trigger calculation for any input field change
                calculateModalRowNetWeight(row);
            }
        }
    });
    
    document.addEventListener('change', function(e) {
        if (e.target.closest('#productListBody, #productListBodyPage')) {
            const row = e.target.closest('.product-row');
            if (row) {
                // Trigger calculation for any select or input field change
                calculateModalRowNetWeight(row);
            }
        }
    });
    
    // Event delegation for product field clicks (handles dynamically added rows)
    document.addEventListener('click', function(e) {
        if (sjProductOpeningLockProductField()) return;
        if (e.target.closest('#productListBody, #productListBodyPage')) {
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
    
    // Purchase invoice item data for balance tracking
    <?php if ($edit_item_id > 0 && $purchase_invoice_item): ?>
    const purchaseInvoiceItem = <?php echo json_encode($purchase_invoice_item); ?>;
    <?php else: ?>
    const purchaseInvoiceItem = null;
    <?php endif; ?>
    // Product opening stock data (when voucher=product_opening and characteristic_id set)
    <?php if ($product_opening_item): ?>
    const productOpeningItem = <?php echo json_encode($product_opening_item); ?>;
    <?php else: ?>
    const productOpeningItem = null;
    <?php endif; ?>
    // Unified: use either for balance block and balance tracking
    const stockDetailItem = purchaseInvoiceItem || productOpeningItem || null;
    /** Pre-fill voucherTypeId in Product Selection (product_opening / purchase invoice line) */
    window.SJ_DEFAULT_VOUCHER_TYPE = <?php echo json_encode($sj_default_voucher_type ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    
    // Edit mode: Add (Shift+A) disabled; save only updates existing inward/outward stock
    window.STOCK_JOURNAL_EDIT_MODE = <?php echo $edit_mode ? 'true' : 'false'; ?>;
    // Product opening single product: voucher=product_opening + characteristic_id + product_id -> product field readonly, no Add Product button
    window.PRODUCT_OPENING_SINGLE_PRODUCT = <?php echo !empty($product_opening_single_product) ? 'true' : 'false'; ?>;
    window.PRODUCT_OPENING_ALLOW_MULTI_ROWS = <?php echo !empty($product_opening_is_diamond_or_stones) ? 'true' : 'false'; ?>;
    window.SJ_PRODUCT_OPENING_LINE = <?php echo !empty($sj_product_opening_line) ? json_encode($sj_product_opening_line, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : 'null'; ?>;
    window.SJ_CONTEXT_METAL_ID = <?php echo (int) ($sj_active_tab_metal_id ?? $sj_context_metal_id ?? 0); ?>;

    function sjGetProductOpeningLineTemplate() {
        if (window.SJ_PRODUCT_OPENING_LINE && typeof window.SJ_PRODUCT_OPENING_LINE === 'object') {
            return window.SJ_PRODUCT_OPENING_LINE;
        }
        return null;
    }

    function sjProductOpeningLockProductField() {
        return window.PRODUCT_OPENING_SINGLE_PRODUCT && !window.PRODUCT_OPENING_ALLOW_MULTI_ROWS;
    }
    /**
     * Product opening: each new list line must get a fresh server barcode — never merge into a row
     * that already has the same code, and never re-use the staging line's barcode (was only enforced
     * for single-SKU + non-diamond; diamond/stones could duplicate RN00…).
     */
    function sjProductOpeningNewLineBarcodePolicy() {
        if (typeof window.SJ_DEFAULT_VOUCHER_TYPE === 'string' && window.SJ_DEFAULT_VOUCHER_TYPE === 'product_opening') {
            return true;
        }
        try {
            if (typeof window !== 'undefined' && window.location && window.location.search) {
                var p = new URLSearchParams(window.location.search);
                if (p.get('voucher') === 'product_opening' && p.get('characteristic_id') && p.get('product_id')) {
                    return true;
                }
            }
        } catch (e) {}
        return (typeof sjProductOpeningLockProductField === 'function' && sjProductOpeningLockProductField());
    }

    /** Purchase invoice / product opening: every ADD must create a new list line with a fresh serial (no merge on staging barcode). */
    function sjEachAddNeedsFreshListBarcode() {
        if (typeof sjProductOpeningNewLineBarcodePolicy === 'function' && sjProductOpeningNewLineBarcodePolicy()) {
            return true;
        }
        if (typeof stockDetailItem !== 'undefined' && stockDetailItem) {
            return true;
        }
        try {
            if (typeof window !== 'undefined' && window.location && window.location.search) {
                var p = new URLSearchParams(window.location.search);
                if (p.get('voucher') === 'purchase_invoice' && p.get('item_id')) {
                    return true;
                }
            }
        } catch (e) {}
        return false;
    }

    function sjFormatExcelExtrasForRow(arr) {
        if (!arr || !arr.length) {
            return '<span class="text-muted small">—</span>';
        }
        return arr.map(function(x) {
            if (!x || !x.h) {
                return '';
            }
            return '<div class="sj-excel-kv py-1 border-bottom border-light" style="font-size:0.72rem;line-height:1.25;text-align:left;"><span class="text-secondary">' + escapeHtml(String(x.h)) + ':</span> ' + escapeHtml(String(x.v != null ? x.v : '')) + '</div>';
        }).join('');
    }

    function sjResolveCaratIdFromExcel(karatVal, serverCaratId) {
        if (serverCaratId != null && String(serverCaratId).trim() !== '') {
            return String(serverCaratId).trim();
        }
        if (karatVal == null || String(karatVal).trim() === '') return '';
        var raw = String(karatVal).trim();
        if (typeof carats === 'undefined' || !Array.isArray(carats)) return raw;
        for (var i = 0; i < carats.length; i++) {
            if (String(carats[i].id) === raw) return String(carats[i].id);
        }
        var rawLower = raw.toLowerCase();
        for (var j = 0; j < carats.length; j++) {
            var nm = String(carats[j].name || '').trim();
            if (nm === raw || nm.toLowerCase() === rawLower) return String(carats[j].id);
        }
        var num = parseFloat(raw.replace(/[^0-9.]/g, ''));
        if (!isNaN(num)) {
            for (var k = 0; k < carats.length; k++) {
                var nameNum = parseFloat(String(carats[k].name || '').replace(/[^0-9.]/g, ''));
                if (!isNaN(nameNum) && Math.abs(nameNum - num) < 0.001) return String(carats[k].id);
            }
        }
        return raw;
    }

    function sjCaratDisplayName(caratIdOrName) {
        if (caratIdOrName == null || String(caratIdOrName).trim() === '') return '';
        var raw = String(caratIdOrName).trim();
        if (typeof carats === 'undefined' || !Array.isArray(carats)) return raw;
        var byId = carats.find(function(c) { return c.id == raw || String(c.id) === raw; });
        if (byId) return byId.name || raw;
        var rawLower = raw.toLowerCase();
        var byName = carats.find(function(c) {
            var nm = String(c.name || '').trim();
            return nm === raw || nm.toLowerCase() === rawLower;
        });
        return byName ? (byName.name || raw) : raw;
    }

    /** Pre-select Karat from purchase invoice line / product characteristic / stock journal karat. */
    function sjApplyCaratSelectFromProduct(row, product) {
        if (!row || !product) return;
        var caratSelect = row.querySelector('.carat-select') || row.querySelector('[data-column="carat"] select');
        if (!caratSelect) return;
        var raw = '';
        if (product.purchase_invoice_carat != null && String(product.purchase_invoice_carat).trim() !== '') {
            raw = product.purchase_invoice_carat;
        } else if (product.carat != null && String(product.carat).trim() !== '') {
            raw = product.carat;
        } else if (product.carat_id != null && String(product.carat_id).trim() !== '') {
            raw = product.carat_id;
        } else if (product.karat != null && String(product.karat).trim() !== '') {
            raw = product.karat;
        }
        if (raw === '' || raw == null) return;
        var resolved = typeof sjResolveCaratIdFromExcel === 'function'
            ? sjResolveCaratIdFromExcel(raw, raw)
            : String(raw).trim();
        if (!resolved) return;
        var matched = false;
        for (var i = 0; i < caratSelect.options.length; i++) {
            if (String(caratSelect.options[i].value) === String(resolved)) {
                caratSelect.value = caratSelect.options[i].value;
                matched = true;
                break;
            }
        }
        if (!matched) {
            var rawLower = String(raw).trim().toLowerCase();
            for (var j = 0; j < caratSelect.options.length; j++) {
                var optText = String(caratSelect.options[j].text || '').trim().toLowerCase();
                if (optText && (optText === rawLower || optText.replace(/\s+/g, '') === rawLower.replace(/\s+/g, ''))) {
                    caratSelect.value = caratSelect.options[j].value;
                    matched = true;
                    break;
                }
            }
        }
        if (matched && typeof window.auragoldSyncPurityFromCaratSelect === 'function') {
            window.auragoldSyncPurityFromCaratSelect(row);
        }
    }

    /** Pre-fill making type/rate from purchase invoice line; lock purchase rate for Actual Making Value calc. */
    function sjApplyPurchaseMakingFromProduct(row, product) {
        if (!row || !product) return;
        var makingRate = parseFloat(product.making_rate);
        if (isNaN(makingRate)) makingRate = 0;
        var makingType = (product.making_type != null && String(product.making_type).trim() !== '')
            ? String(product.making_type).trim()
            : 'Fix';
        if (typeof auragoldNormalizeMakingType === 'function') {
            makingType = auragoldNormalizeMakingType(makingType);
        }
        if (typeof setModalSelectIfOptionExists === 'function') {
            setModalSelectIfOptionExists(row, 'making-type', makingType);
        } else {
            var typeSel = row.querySelector('[data-column="making-type"] select');
            if (typeSel) typeSel.value = makingType;
        }
        var rateInp = row.querySelector('[data-column="making-rate"] input');
        if (rateInp) rateInp.value = makingRate.toFixed(2);
        if (makingRate > 0) {
            row.setAttribute('data-purchase-making-rate', String(makingRate));
        }
        var discInp = row.querySelector('[data-column="making-discount-amt"] input');
        if (discInp && product.making_discount_amt != null && product.making_discount_amt !== '') {
            discInp.value = (parseFloat(product.making_discount_amt) || 0).toFixed(2);
        }
        if (typeof window.auragoldApplyPurchaseMakingActualValueField === 'function') {
            window.auragoldApplyPurchaseMakingActualValueField(row);
        }
    }

    /** Keep up to 6 decimals (opening stock / gross wt). Name kept for existing call sites. */
    function sjRoundWeight3(v) {
        var x = parseFloat(v);
        return isFinite(x) ? Math.round(x * 1e6) / 1e6 : 0;
    }
    function sjFormatWeight(v) {
        if (typeof window.auragoldFormatWeight === 'function') {
            return window.auragoldFormatWeight(v);
        }
        if (v === '' || v == null) return '0';
        var raw = String(v).trim().replace(/,/g, '');
        if (raw !== '' && isFinite(Number(raw)) && raw.search(/e/i) === -1) {
            var neg = raw.charAt(0) === '-';
            var abs = raw.replace(/^[+-]/, '');
            var parts = abs.split('.');
            var intPart = parts[0].replace(/^0+(?=\d)/, '') || '0';
            var dec = parts[1] || '';
            if (dec.length > 6) dec = dec.substring(0, 6);
            dec = dec.replace(/0+$/, '');
            var out = intPart + (dec ? '.' + dec : '');
            if (neg && out !== '0') out = '-' + out;
            return out;
        }
        var x = parseFloat(v);
        if (!isFinite(x)) return '0';
        var s = x.toFixed(6).replace(/\.?0+$/, '');
        if (s === '-0') s = '0';
        return s;
    }
    var SJ_BALANCE_WT_EPS = 0.0000005;
    var SJ_BALANCE_QTY_EPS = 0.0001;

    function mapSjExcelProductToModalRow(p) {
        if (!p) p = {};
        var tpaths = p.temp_image_paths;
        if (!Array.isArray(tpaths)) tpaths = [];
        function n(v) {
            var x = parseFloat(v);
            return isFinite(x) ? x : 0;
        }
        var purchase = n(p.purchase_amount);
        var salePct = n(p.sale_percent);
        var sale = n(p.sale_amount);
        var saleWith = n(p.sale_amount_with);
        var amount = n(p.amount);
        var netAmt = n(p.net_amount != null ? p.net_amount : p.net_amt);
        var netTax = n(p.net_amt_tax);
        var tax = n(p.tax_amount != null ? p.tax_amount : p.tax);
        var reverse = n(p.reverse);
        var diamondAmt = n(p.diamond_amount);
        var makingAmt = n(p.making_amount);
        var stoneAmt = n(p.stone_amount);
        var otherAmt = n(p.other_amount);
        var metalVal = n(p.metal_value);
        if (salePct > 0 && purchase > 0 && sale <= 0) {
            sale = purchase + (purchase * (salePct / 100));
        }
        var comp = metalVal + makingAmt + stoneAmt + otherAmt + diamondAmt;
        if (amount <= 0 && comp > 0) amount = comp;
        var fallback = purchase > 0 ? purchase : (netAmt > 0 ? netAmt : (amount > 0 ? amount : (sale > 0 ? sale : reverse)));
        if (purchase <= 0 && fallback > 0) purchase = fallback;
        if (amount <= 0 && fallback > 0) amount = fallback;
        if (netAmt <= 0 && fallback > 0) netAmt = fallback;
        if (sale <= 0 && reverse > 0) sale = reverse;
        if (netTax <= 0) netTax = netAmt + tax;
        if (saleWith <= 0 && sale > 0) saleWith = sale + tax;
        return {
            product_id: p.product_id,
            characteristic_id: p.characteristic_id,
            product_name: (p.product_name != null && String(p.product_name).trim() !== '') ? String(p.product_name).trim() : '',
            quantity: p.quantity,
            gross_wt: sjRoundWeight3(p.gross_weight),
            less_wt: sjRoundWeight3(p.less_weight),
            pkt_wt: sjRoundWeight3(p.pkt_wt),
            pkt_less_wt: sjRoundWeight3(p.pkt_less_wt),
            purity: p.purity,
            final_wt: sjRoundWeight3(p.final_weight),
            net_wt: sjRoundWeight3(p.net_weight),
            pure_wt: p.pure_weight,
            rate: (function () {
                var r = parseFloat(p.rate);
                var mr = parseFloat(p.metal_rate);
                if (!isNaN(r) && Math.abs(r) > 1e-9) return r;
                if (!isNaN(mr) && Math.abs(mr) > 1e-9) return mr;
                return 0;
            })(),
            metal_rate: (function () {
                var mr = parseFloat(p.metal_rate);
                var r = parseFloat(p.rate);
                if (!isNaN(mr) && Math.abs(mr) > 1e-9) return mr;
                if (!isNaN(r) && Math.abs(r) > 1e-9) return r;
                return 0;
            })(),
            making_amount: makingAmt,
            making_type: p.making_type,
            making_rate: p.making_rate,
            making_discount_amt: p.making_discount_amt,
            making_actual_value: p.making_actual_value,
            tax: tax,
            design_no: p.design_no,
            huid_no: (p.huid_no != null && String(p.huid_no).trim() !== '') ? String(p.huid_no).trim() : '',
            stone_amount: stoneAmt,
            other_amount: otherAmt,
            diamond_amount: diamondAmt,
            amount: amount,
            net_amt: netAmt,
            net_amt_tax: netTax,
            metal_value: metalVal,
            discount: n(p.discount),
            purchase_amount: purchase,
            sale_percent: salePct,
            sale_amount: sale,
            sale_amount_with: saleWith,
            reverse: reverse,
            stone_charges: n(p.stone_charges) > 0 ? n(p.stone_charges) : stoneAmt,
            other_charges: n(p.other_charges) > 0 ? n(p.other_charges) : otherAmt,
            diamond_value: n(p.diamond_value) > 0 ? n(p.diamond_value) : diamondAmt,
            gemstone_value: n(p.gemstone_value),
            barcode: (p.barcode != null && String(p.barcode).trim() !== '') ? String(p.barcode).trim() : '',
            from_excel: true,
            barcode_prefix: p.barcode_prefix,
            barcode_digits: p.barcode_digits,
            metal_id: p.metal_id,
            metal_name: p.metal_name,
            voucher_type: (p.voucher_type != null && String(p.voucher_type).trim() !== '') ? String(p.voucher_type).trim() : ((typeof window.SJ_DEFAULT_VOUCHER_TYPE === 'string' && window.SJ_DEFAULT_VOUCHER_TYPE) ? window.SJ_DEFAULT_VOUCHER_TYPE : ''),
            carat_id: sjResolveCaratIdFromExcel(p.karat, p.carat_id),
            location_id: (p.location != null && String(p.location).trim() !== '') ? String(p.location).trim() : '',
            excelTempImagePaths: tpaths,
            excel_extra_columns: Array.isArray(p.excel_extra_columns) ? p.excel_extra_columns : [],
            extra_fields: (p.extra_fields && typeof p.extra_fields === 'object') ? p.extra_fields : {}
        };
    }

    function sjUpdateExcelSampleDownloadHref(metalId) {
        var a = document.getElementById('sjExcelSampleDownload');
        if (!a) return;
        var base = a.getAttribute('data-sj-excel-sample-base') || a.getAttribute('href') || '';
        if (!base) return;
        var url = base.replace(/([?&])metal_id=\d+/g, '$1').replace(/[?&]$/, '');
        if (metalId !== null && metalId !== undefined && String(metalId).trim() !== '') {
            url += (url.indexOf('?') >= 0 ? '&' : '?') + 'metal_id=' + encodeURIComponent(String(metalId).trim());
        }
        a.setAttribute('href', url);
    }
    window.sjUpdateExcelSampleDownloadHref = sjUpdateExcelSampleDownloadHref;

    (function initSjExcelImport() {
        var btn = document.getElementById('sjExcelImportBtn');
        var inp = document.getElementById('sjExcelImportFile');
        if (!btn || !inp) return;
        btn.addEventListener('click', function() {
            inp.value = '';
            inp.click();
        });
        inp.addEventListener('change', function() {
            if (!inp.files || !inp.files.length) return;
            if (typeof window !== 'undefined' && window.STOCK_JOURNAL_EDIT_MODE) {
                alert('Excel import is disabled in edit mode.');
                inp.value = '';
                return;
            }
            var fd = new FormData();
            fd.append('excel_file', inp.files[0]);
            fd.append('voucher', <?php echo json_encode(($voucher_type_param === 'purchase_invoice' && $edit_item_id > 0 && !empty($purchase_invoice_item)) ? 'purchase_invoice' : 'product_opening', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>);
            fd.append('product_id', String(<?php echo (int)($product_id_param ?? 0); ?>));
            fd.append('characteristic_id', String(<?php echo (int)($characteristic_id_param ?? 0); ?>));
            var sjImpMetalBtn = document.querySelector('.product-category-tabs .category-tab-btn.active') || document.querySelector('.category-tab-btn.active');
            var sjImpMetalId = sjImpMetalBtn ? sjImpMetalBtn.getAttribute('data-metal-id') : '';
            if (!sjImpMetalId && typeof currentMetalId !== 'undefined' && currentMetalId) {
                sjImpMetalId = String(currentMetalId);
            }
            if (sjImpMetalId) {
                fd.append('metal_id', String(sjImpMetalId));
            }
            <?php if ($voucher_type_param === 'purchase_invoice' && $edit_item_id > 0 && !empty($purchase_invoice_item)): ?>
            fd.append('item_id', String(<?php echo (int) $edit_item_id; ?>));
            <?php endif; ?>
            fd.append('preview_only', '1');
            var od = document.getElementById('orderDate');
            if (od && od.value) fd.append('date', od.value);
            var gn = document.getElementById('modalGroupName');
            if (gn && gn.value) fd.append('group_name', gn.value);
            var cm = document.getElementById('modalComment');
            if (cm && cm.value) fd.append('comment', cm.value);
            btn.disabled = true;
            var orig = btn.innerHTML;
            btn.innerHTML = '<i class="feather icon-loader"></i> Loading...';
            fetch('ajax/import-stock-journal-excel.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function(r) {
                    return r.text().then(function(text) {
                        var data = null;
                        try {
                            data = text ? JSON.parse(text) : null;
                        } catch (e) {
                            var s = (text || '').replace(/\s+/g, ' ').trim().slice(0, 300);
                            throw new Error(s || 'Server did not return JSON (HTTP ' + r.status + ')');
                        }
                        if (!data) {
                            throw new Error('Empty response (HTTP ' + r.status + ')');
                        }
                        return data;
                    });
                })
                .then(async function(data) {
                    btn.disabled = false;
                    btn.innerHTML = orig;
                    if (data.status === 'success' || data.status === true) {
                        var rows = data.products || [];
                        if (!rows.length) {
                            alert('No data rows in the Excel file.');
                            return;
                        }
                        if (window.STOCK_JOURNAL_EDIT_MODE) {
                            alert('Excel import is disabled in edit mode.');
                            return;
                        }
                        var n = 0;
                        for (var i = 0; i < rows.length; i++) {
                            if (typeof addProductToTableFromModalRow === 'function') {
                                var ok = await addProductToTableFromModalRow(mapSjExcelProductToModalRow(rows[i]));
                                if (ok) n++;
                            }
                        }
                        if (n > 0) {
                            alert('Added ' + n + ' line(s) to the Product List. Review, edit, or remove rows, then click Save Stock Journal to post to stock.');
                        } else {
                            alert('No lines were added. Check balance limits or the Product List for errors.');
                        }
                        return;
                    }
                    alert(data.message || 'Import failed');
                })
                .catch(function(err) {
                    btn.disabled = false;
                    btn.innerHTML = orig;
                    alert(err && err.message ? err.message : 'Import request failed');
                });
        });
    })();

    // Handle Add Product Icon Click
    $(document).ready(function() {
        $(document).on('click', '.add-product-icon', function(e) {
            e.stopPropagation();
            e.preventDefault();
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
        
        // Clear current editing row ID (keep let + window in sync for Product List / Product Selection)
        if (typeof currentEditingRowId !== 'undefined') currentEditingRowId = null;
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
                    if (typeof currentEditingRowId !== 'undefined') currentEditingRowId = null;
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
    
    // Calculation dropdown options (fixed list - same as stock-journal-update)
    window.CALCULATION_MODES = [{ name: 'Carat X Rate' }, { name: 'Rate X Gross Wt' }, { name: 'Rate X Purity Wt' }, { name: 'Rate X Net Wt' }, { name: 'Rate X Final Wt' }, { name: 'Fix' }, { name: 'Stone Charge' }, { name: 'Attach Image Type' }];
    function buildCalculationSelectOptions(selectedValue) {
        if (!window.CALCULATION_MODES || !window.CALCULATION_MODES.length) return '<option value="Carat X Rate" selected>Carat X Rate</option>';
        var first = (window.CALCULATION_MODES[0] && window.CALCULATION_MODES[0].name) || 'Carat X Rate';
        return window.CALCULATION_MODES.map(function(cm) {
            var name = cm.name || '';
            var sel = (selectedValue !== undefined && selectedValue !== null && selectedValue !== '' ? selectedValue === name : name === first) ? ' selected' : '';
            return '<option value="' + name.replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;') + '"' + sel + '>' + name.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</option>';
        }).join('');
    }
    function buildSJTaxTypeSelectHtml(selectedValue) {
        var opts = [
            { v: 'tax_on_making', t: 'Tax on making' },
            { v: 'tax_of_netamount', t: 'Tax of net amount' },
            { v: 'no_tax', t: 'No tax' }
        ];
        var def = 'tax_of_netamount';
        return opts.map(function(o) {
            var sel = (selectedValue !== undefined && selectedValue !== null && String(selectedValue).trim() !== '' ? String(selectedValue) === o.v : o.v === def) ? ' selected' : '';
            return '<option value="' + o.v + '"' + sel + '>' + o.t.replace(/</g,'&lt;') + '</option>';
        }).join('');
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
    
    /** Product list on main card vs duplicate table inside #productSelectionModal */
    function getProductSelectionListTbody(contextEl) {
        if (contextEl && contextEl.closest && contextEl.closest('#productSelectionModal')) {
            return document.querySelector('#productSelectionModal #productListBody');
        }
        var page = document.getElementById('productListBodyPage');
        if (page) return page;
        return document.querySelector('#productSelectionModal #productListBody');
    }
    
    /** Metal display name from a modal/selection row or product-list row (data-metal-name, or tab lookup, or current tab). */
    function sjRowMetalDisplayName(row) {
        if (!row) return '';
        var mn = (row.getAttribute('data-metal-name') || '').trim();
        if (mn) return mn;
        var mid = row.getAttribute('data-metal-id');
        if (mid) {
            var sel = '.category-tab-btn[data-metal-id="' + String(mid).replace(/"/g, '') + '"]';
            var tab = document.querySelector(sel);
            if (tab) return (tab.getAttribute('data-metal-name') || '').trim();
        }
        return typeof currentMetalName !== 'undefined' ? String(currentMetalName || '').trim() : '';
    }

    function sjMetalNameIsDiamondStones(name) {
        if (typeof window.isDiamondStonesMetalDisplayName === 'function') {
            return window.isDiamondStonesMetalDisplayName(name);
        }
        return typeof name === 'string' && name.trim().toLowerCase().replace(/\s+/g, ' ') === 'diamond & stones';
    }

    /** Main Product List row that is not an empty "click to select" placeholder. */
    function sjProductListRowIsCommittedForMetalLock(tr) {
        if (!tr || !tr.id || tr.id.indexOf('product-row-') !== 0) return false;
        var desc = tr.querySelector('[data-column="description"]');
        var descText = desc ? String(desc.textContent || '').trim() : '';
        if (/click to select product/i.test(descText)) return false;
        var pid = String(tr.getAttribute('data-product-id') || '').trim();
        var bc = String(tr.getAttribute('data-barcode') || '').trim();
        return pid !== '' || bc !== '';
    }

    /**
     * Metal for tab-locking: only explicit row attrs + tab lookup.
     * Do not use currentMetalName — placeholders on the list would inherit the active tab and wrongly lock Gold/Silver.
     */
    function sjProductListRowMetalDisplayName(tr) {
        if (!tr) return '';
        var mn = (tr.getAttribute('data-metal-name') || '').trim();
        if (mn) return mn;
        var mid = tr.getAttribute('data-metal-id');
        if (mid) {
            var sel = '.category-tab-btn[data-metal-id="' + String(mid).replace(/"/g, '') + '"]';
            var tab = document.querySelector(sel);
            if (tab) return (tab.getAttribute('data-metal-name') || '').trim();
        }
        return '';
    }

    function sjProductTableBodyHasDiamondLine() {
        var tbody = document.getElementById('productTableBody');
        if (!tbody) return false;
        var rows = tbody.querySelectorAll('tr[id^="product-row-"]');
        for (var i = 0; i < rows.length; i++) {
            if (!sjProductListRowIsCommittedForMetalLock(rows[i])) continue;
            if (sjMetalNameIsDiamondStones(sjProductListRowMetalDisplayName(rows[i]))) return true;
        }
        return false;
    }

    /** When any Diamond & Stones line exists on the main product list, only that metal tab stays usable. */
    function sjUpdateMetalTabsLockFromProductList() {
        var lockOthers = sjProductTableBodyHasDiamondLine();
        var tabs = document.querySelectorAll('.category-tab-btn');
        tabs.forEach(function(btn) {
            var mname = btn.getAttribute('data-metal-name') || '';
            var isDiamondTab = sjMetalNameIsDiamondStones(mname);
            if (lockOthers) {
                var lockThis = !isDiamondTab;
                btn.disabled = lockThis;
                btn.classList.toggle('sj-metal-tab-locked', lockThis);
                btn.title = lockThis ? 'Remove Diamond & Stones lines from the product list to switch metal' : '';
            } else {
                btn.disabled = false;
                btn.classList.remove('sj-metal-tab-locked');
                btn.title = '';
            }
        });
        if (lockOthers) {
            var active = document.querySelector('.category-tab-btn.active');
            var activeName = active ? (active.getAttribute('data-metal-name') || '') : '';
            if (!sjMetalNameIsDiamondStones(activeName)) {
                var dTab = null;
                tabs.forEach(function(b) {
                    if (sjMetalNameIsDiamondStones(b.getAttribute('data-metal-name') || '')) dTab = b;
                });
                if (dTab && !dTab.disabled) dTab.click();
            }
        }
    }
    window.sjUpdateMetalTabsLockFromProductList = sjUpdateMetalTabsLockFromProductList;

    /** Activate product-opening metal tab and load the template row if the grid is still empty. */
    function sjActivateProductOpeningMetalTab() {
        var ctxMid = (typeof window.SJ_CONTEXT_METAL_ID !== 'undefined') ? parseInt(window.SJ_CONTEXT_METAL_ID, 10) : 0;
        var tab = null;
        if (ctxMid > 0) {
            tab = document.querySelector('.product-category-tabs .category-tab-btn[data-metal-id="' + String(ctxMid) + '"]');
        }
        if (!tab) {
            var tpl = (typeof sjGetProductOpeningLineTemplate === 'function') ? sjGetProductOpeningLineTemplate() : null;
            if (tpl && tpl.metal_id) {
                tab = document.querySelector('.product-category-tabs .category-tab-btn[data-metal-id="' + String(tpl.metal_id) + '"]');
            }
            if (!tab && tpl && tpl.metal_name) {
                document.querySelectorAll('.product-category-tabs .category-tab-btn').forEach(function(btn) {
                    if (tab) return;
                    var mn = (btn.getAttribute('data-metal-name') || '').trim().toLowerCase();
                    if (mn && mn === String(tpl.metal_name).trim().toLowerCase()) tab = btn;
                });
            }
        }
        if (!tab) {
            tab = document.querySelector('.product-category-tabs .category-tab-btn');
        }
        if (!tab) return null;
        document.querySelectorAll('.category-tab-btn').forEach(function(b) { b.classList.remove('active'); });
        tab.classList.add('active');
        currentMetalId = tab.getAttribute('data-metal-id');
        window.sjCurrentMetalId = currentMetalId;
        currentMetalName = tab.getAttribute('data-metal-name') || '';
        if (typeof applyProductModalColumnVisibilityForTab === 'function') {
            applyProductModalColumnVisibilityForTab(currentMetalId || '');
        }
        return tab;
    }

    function sjEnsureProductOpeningGridLoaded() {
        try {
            var p = new URLSearchParams(window.location.search);
            if ((p.get('voucher') || '') !== 'product_opening' || !p.get('characteristic_id')) return;
            var tbody = document.getElementById('productListBodyPage');
            if (!tbody || tbody.querySelector('tr.product-row')) return;
            if (/Loading products/i.test(tbody.textContent || '')) return;
            var tab = sjActivateProductOpeningMetalTab();
            var mid = (tab && tab.getAttribute('data-metal-id')) || currentMetalId || window.sjCurrentMetalId || window.SJ_CONTEXT_METAL_ID;
            if (mid && typeof loadProducts === 'function') {
                loadProducts(mid, '', tab || null);
            }
        } catch (e) {
            console.warn('sjEnsureProductOpeningGridLoaded:', e);
        }
    }

    // Initialize category tabs (main card + modal share .category-tab-btn; bind once)
    function initCategoryTabs() {
        if (window._sjCategoryTabsInited) return;
        window._sjCategoryTabsInited = true;
        document.querySelectorAll('.category-tab-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (this.disabled) return;
                // Remove active class from all tabs
                document.querySelectorAll('.category-tab-btn').forEach(function(b) {
                    b.classList.remove('active');
                });
                // Add active class to clicked tab
                this.classList.add('active');
                const metalId = this.getAttribute('data-metal-id');
                const metalName = this.getAttribute('data-metal-name');
                currentMetalId = metalId;
                window.sjCurrentMetalId = metalId;
                currentMetalName = metalName;
                
                console.log('Tab clicked - Metal ID:', metalId, 'Metal Name:', metalName);
                
                // Load products for the selected metal (into main page table or modal, depending on which tab was clicked)
                if (metalId) {
                    loadProducts(metalId, '', this);
                }
                
                // Also filter existing products in the table by metal
                filterProductsByMetal(metalId);
                
                // Apply tab-wise column visibility (Gold vs Silver etc. each have their own)
                if (typeof applyProductModalColumnVisibilityForTab === 'function') {
                    applyProductModalColumnVisibilityForTab(metalId || '');
                }
                if (typeof window.auragoldSyncProductModalExtraFields === 'function') {
                    window.auragoldSyncProductModalExtraFields(metalName || '');
                }
                if (typeof sjUpdateExcelSampleDownloadHref === 'function') {
                    sjUpdateExcelSampleDownloadHref(metalId || '');
                }
                if (typeof window.runStockJournalColumnDragInit === 'function') {
                    window.runStockJournalColumnDragInit();
                }
            });
        });
        
        // Set initial metal (main card tabs only — modal duplicates must not steal .active)
        let firstTab = document.querySelector('.product-category-tabs .category-tab-btn.active')
            || document.querySelector('.product-category-tabs .category-tab-btn');
        if (firstTab && !document.querySelector('.product-category-tabs .category-tab-btn.active')) {
            firstTab.classList.add('active');
        }
        if (firstTab) {
            currentMetalId = firstTab.getAttribute('data-metal-id');
            window.sjCurrentMetalId = currentMetalId;
            currentMetalName = firstTab.getAttribute('data-metal-name');
            // Load products for initial metal into the main product selection table when present
            if (currentMetalId) {
                loadProducts(currentMetalId, '', firstTab);
            }
            // Apply column visibility for initial tab
            if (currentMetalId && typeof applyProductModalColumnVisibilityForTab === 'function') {
                applyProductModalColumnVisibilityForTab(currentMetalId);
            }
            if (currentMetalName && typeof window.auragoldSyncProductModalExtraFields === 'function') {
                window.auragoldSyncProductModalExtraFields(currentMetalName);
            }
            if (typeof sjUpdateExcelSampleDownloadHref === 'function') {
                sjUpdateExcelSampleDownloadHref(currentMetalId || <?php echo (int) $sj_active_tab_metal_id; ?>);
            }
        }
        sjUpdateMetalTabsLockFromProductList();
    }
    
    // Filter product rows by metal in both selection tables (main + modal)
    function filterProductsByMetal(metalId) {
        document.querySelectorAll('#productListBody, #productListBodyPage').forEach(function(tbody) {
            if (!tbody) return;
        var uItem = (typeof window !== 'undefined' && window.location && window.location.search) ? (new URLSearchParams(window.location.search).get('item_id')) : null;
        if (uItem && parseInt(uItem, 10) > 0) {
            tbody.querySelectorAll('tr.product-row').forEach(function(row) { row.style.display = ''; });
            var onlyEmpty = tbody.querySelector('tr:not(.product-row)');
            if (onlyEmpty) onlyEmpty.remove();
            return;
        }
        var uPoVoucher = (typeof window !== 'undefined' && window.location && window.location.search) ? (new URLSearchParams(window.location.search).get('voucher')) : null;
        var uPoChar = (typeof window !== 'undefined' && window.location && window.location.search) ? (new URLSearchParams(window.location.search).get('characteristic_id')) : null;
        if ((uPoVoucher || '') === 'product_opening' && uPoChar && parseInt(uPoChar, 10) > 0) {
            tbody.querySelectorAll('tr.product-row').forEach(function(row) { row.style.display = ''; });
            var onlyEmptyPo = tbody.querySelector('tr:not(.product-row)');
            if (onlyEmptyPo && tbody.querySelectorAll('tr.product-row').length > 0) onlyEmptyPo.remove();
            return;
        }
        const allRows = tbody.querySelectorAll('tr.product-row');
        let visibleCount = 0;
        
        allRows.forEach(function(row) {
            const rowMetalId = row.getAttribute('data-metal-id');
            // Show row if it matches the selected metal, or if no metal_id is set (manually added rows)
            if (!rowMetalId || rowMetalId === metalId) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        // Show empty message if no products visible
        const emptyRow = tbody.querySelector('tr:not(.product-row)');
        if (visibleCount === 0 && !emptyRow) {
            tbody.innerHTML = '<tr><td colspan="104" class="text-center text-muted py-4">No products found for this category</td></tr>';
        } else if (visibleCount > 0 && emptyRow) {
            emptyRow.remove();
        }
        
            console.log('Filtered products - Visible:', visibleCount, 'Total:', allRows.length);
        });
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
            const tbody = document.querySelector('#productSelectionModal #productListBody');
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="104" class="text-center text-muted py-4">Click "Add Product" button to add products for billing...</td></tr>';
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
            <td data-column="photo" class="sj-photo-cell" style="text-align: center; vertical-align: middle; width: 56px;">
                <div class="sj-photo-first-wrap" style="width: 48px; height: 48px; margin: 0 auto; border-radius: 6px; overflow: hidden; background: #f1f5f9; display: flex; align-items: center; justify-content: center;"></div>
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
            <td data-column="images" class="stock-journal-images-cell" style="vertical-align: middle;">
                <div class="sj-images-wrap">
                    <input type="file" class="sj-image-input" accept=".jpg,.jpeg,.png,.webp" multiple style="display:none;">
                    <button type="button" class="btn btn-sm btn-outline-secondary sj-image-btn" style="font-size:0.7rem; padding:2px 6px; white-space:nowrap;" title="Add images (jpg, png, webp, max 2MB)"><i class="feather icon-upload" style="vertical-align:middle;"></i> Add</button>
                    <div class="sj-image-previews"></div>
                </div>
            </td>
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
        initStockJournalImageCell(row.querySelector('[data-column="images"]'));
        
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
    
    /** Barcodes already on Product List + Product Selection modal (avoid duplicate serials in one session). */
    window.__sjBarcodeReserved = window.__sjBarcodeReserved || {};
    window.__sjBarcodeFetchQueue = window.__sjBarcodeFetchQueue || Promise.resolve();
    window.__sjAddOperationQueue = window.__sjAddOperationQueue || Promise.resolve();

    function sjBarcodeReserve(barcode) {
        var bc = String(barcode || '').trim();
        if (!bc) return;
        window.__sjBarcodeReserved[bc] = (window.__sjBarcodeReserved[bc] || 0) + 1;
    }

    function sjBarcodeRelease(barcode) {
        var bc = String(barcode || '').trim();
        if (!bc || !window.__sjBarcodeReserved[bc]) return;
        window.__sjBarcodeReserved[bc]--;
        if (window.__sjBarcodeReserved[bc] <= 0) delete window.__sjBarcodeReserved[bc];
    }

    function sjIsBarcodeInUseLocally(barcode, excludeTbodyInput) {
        var bc = String(barcode || '').trim();
        if (!bc) return false;
        if (window.__sjBarcodeReserved[bc]) return true;
        var tbody = document.getElementById('productTableBody');
        if (tbody && typeof findProductListRowByBarcode === 'function' && findProductListRowByBarcode(tbody, bc)) {
            return true;
        }
        var used = sjCollectUsedBarcodesForNextSerial();
        return used.indexOf(bc) !== -1;
    }

    function sjRunSerialBarcodeTask(fn) {
        var run = window.__sjBarcodeFetchQueue.then(fn);
        window.__sjBarcodeFetchQueue = run.catch(function() {});
        return run;
    }

    function sjRunWithAddLock(fn) {
        var run = window.__sjAddOperationQueue.then(function() { return fn(); });
        window.__sjAddOperationQueue = run.catch(function() { return undefined; });
        return run;
    }

    function sjAwaitBarcodeQueue() {
        return Promise.resolve(window.__sjBarcodeFetchQueue).catch(function() {});
    }

    function sjShowStockJournalAddLoader() {
        var el = document.getElementById('sjAddLoader');
        if (el) {
            el.style.display = 'block';
            el.setAttribute('aria-hidden', 'false');
        }
        document.querySelectorAll('#modalAddBtn, #modalAddBtn2, .stock-journal-add-btn').forEach(function(btn) {
            if (!btn.dataset.sjAddOrigHtml) btn.dataset.sjAddOrigHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="sj-add-btn-spinner"></span> Adding...';
        });
    }

    function sjHideStockJournalAddLoader() {
        var el = document.getElementById('sjAddLoader');
        if (el) {
            el.style.display = 'none';
            el.setAttribute('aria-hidden', 'true');
        }
        document.querySelectorAll('#modalAddBtn, #modalAddBtn2, .stock-journal-add-btn').forEach(function(btn) {
            btn.disabled = false;
            if (btn.dataset.sjAddOrigHtml) btn.innerHTML = btn.dataset.sjAddOrigHtml;
        });
    }

    function sjIsBarcodeOnProductList(barcode) {
        var bc = String(barcode || '').trim();
        if (!bc) return false;
        var tbody = document.getElementById('productTableBody');
        return !!(tbody && typeof findProductListRowByBarcode === 'function' && findProductListRowByBarcode(tbody, bc));
    }

    function sjCollectUsedBarcodesForNextSerial() {
        const usedBarcodes = [];
        function add(t) {
            t = (t || '').trim();
            if (t && usedBarcodes.indexOf(t) === -1) usedBarcodes.push(t);
        }
        Object.keys(window.__sjBarcodeReserved || {}).forEach(function(k) {
            if (window.__sjBarcodeReserved[k]) add(k);
        });
        document.querySelectorAll('#productTableBody [data-column="barcode"]').forEach(function(cell) {
            add(cell.textContent);
        });
        document.querySelectorAll('#productTableBody tr[data-barcode]').forEach(function(tr) {
            add(tr.getAttribute('data-barcode'));
        });
        document.querySelectorAll('#productListBody [data-column="barcode"] input, #productListBodyPage [data-column="barcode"] input').forEach(function(inp) {
            add(inp.value);
        });
        return usedBarcodes;
    }

    /** Use first loaded product row's prefix/digits (e.g. DM + 5 from product characteristic) when adding extra modal lines. */
    function sjInferModalBarcodeRulesFromTbody(tbody) {
        if (!tbody) return { prefix: '', digits: 0 };
        var rows = tbody.querySelectorAll('tr.product-row');
        for (var i = 0; i < rows.length; i++) {
            var pr = (rows[i].getAttribute('data-barcode-prefix') || '').trim();
            if (pr !== '') {
                var dg = parseInt(rows[i].getAttribute('data-barcode-digits'), 10) || 5;
                return { prefix: pr, digits: dg };
            }
        }
        if (typeof sjGetProductOpeningLineTemplate === 'function') {
            var poLine = sjGetProductOpeningLineTemplate();
            if (poLine) {
                var poPr = (poLine.barcode_prefix != null ? String(poLine.barcode_prefix).trim() : '');
                if (poPr !== '') {
                    var poDg = parseInt(poLine.barcode_digits, 10);
                    return { prefix: poPr, digits: (!poDg || poDg < 1) ? 5 : poDg };
                }
            }
        }
        return { prefix: '', digits: 0 };
    }

    /** e.g. "RNN00004" -> { prefix: "RNN", digits: 5 } (digit count from serial part). */
    function sjParseBarcodeStringPrefixDigits(code) {
        var s = String(code || '').trim();
        if (!s) return { prefix: '', digits: 0 };
        var m = s.match(/^([A-Za-z]+)(\d+)$/);
        if (!m) return { prefix: '', digits: 0 };
        return { prefix: m[1], digits: m[2].length };
    }

    /** Manual / non-Excel barcodes: must match row master prefix + numeric suffix length (Excel rows skip via data-sj-excel-import). */
    function sjBarcodeMatchesPrefixDigitsStrict(barcode, prefix, digits) {
        var bc = String(barcode || '').trim();
        var pfx = String(prefix || '').trim();
        var dig = parseInt(digits, 10) || 0;
        if (!pfx || dig < 1) {
            return true;
        }
        if (bc.length < pfx.length) {
            return false;
        }
        if (bc.slice(0, pfx.length) !== pfx) {
            return false;
        }
        var suf = bc.slice(pfx.length);
        return (/^\d+$/.test(suf) && suf.length === dig);
    }

    /**
     * Product opening: fetch a new serial only when the modal line has no barcode yet, still shows
     * the characteristic master tag, or does not match prefix+digit rules. Re-use the modal serial
     * when addEmptyProductRow / get-next-barcode already assigned one — avoids skipping numbers
     * (e.g. GD00005, GD00007) from a second server call on Add + refresh.
     */
    function sjShouldFetchFreshBarcodeForProductOpeningLine(modalRowData) {
        modalRowData = modalRowData || {};
        var bc = String(modalRowData.barcode || '').trim();
        if (!bc) return true;
        var tpl = (typeof sjGetProductOpeningLineTemplate === 'function') ? sjGetProductOpeningLineTemplate() : null;
        if (tpl && tpl.barcode) {
            var masterBc = String(tpl.barcode).trim();
            if (masterBc !== '' && bc === masterBc) return true;
        }
        var prefix = String(modalRowData.barcode_prefix || '').trim();
        var digits = parseInt(modalRowData.barcode_digits, 10) || 0;
        if (!prefix || digits < 1) {
            var parsed = sjParseBarcodeStringPrefixDigits(bc);
            if (parsed.prefix) {
                prefix = parsed.prefix;
                digits = parsed.digits > 0 ? parsed.digits : 5;
            }
        }
        if (prefix && digits > 0) {
            return !sjBarcodeMatchesPrefixDigitsStrict(bc, prefix, digits);
        }
        return false;
    }

    /**
     * When allocating the next server barcode, never fall back to hardcoded "RN" if this journal
     * already has lines like RNN00004 — use that prefix (and digit width) so the next is RNN00005.
     */
    function sjResolveBarcodePrefixDigitForNewLine(modalRowData) {
        var p = (modalRowData && modalRowData.barcode_prefix) ? String(modalRowData.barcode_prefix).trim() : '';
        var d = parseInt(modalRowData && modalRowData.barcode_digits, 10) || 0;
        if (p) {
            if (d < 1) d = 5;
            return { prefix: p, digits: d };
        }
        var pt = document.getElementById('productTableBody');
        if (pt) {
            var trs = pt.querySelectorAll('tr[id^="product-row-"]');
            for (var i = 0; i < trs.length; i++) {
                var tr = trs[i];
                var pAttr = (tr.getAttribute('data-barcode-prefix') || '').trim();
                var dAttr = parseInt(tr.getAttribute('data-barcode-digits'), 10) || 0;
                if (pAttr) {
                    if (dAttr < 1) dAttr = 5;
                    return { prefix: pAttr, digits: dAttr };
                }
                var db = (tr.getAttribute('data-barcode') || '').trim();
                if (!db) {
                    var bcell = tr.querySelector('[data-column="barcode"]');
                    if (bcell) db = (bcell.textContent || '').replace(/\s+/g, ' ').trim();
                }
                if (db) {
                    var parsed = sjParseBarcodeStringPrefixDigits(db);
                    if (parsed.prefix) {
                        return { prefix: parsed.prefix, digits: parsed.digits > 0 ? parsed.digits : 5 };
                    }
                }
            }
        }
        var page = document.getElementById('productListBodyPage');
        var r1 = sjInferModalBarcodeRulesFromTbody(page);
        if (r1.prefix) {
            return { prefix: r1.prefix, digits: r1.digits > 0 ? r1.digits : 5 };
        }
        var mod = document.querySelector('#productSelectionModal #productListBody');
        var r2 = sjInferModalBarcodeRulesFromTbody(mod);
        if (r2.prefix) {
            return { prefix: r2.prefix, digits: r2.digits > 0 ? r2.digits : 5 };
        }
        if (modalRowData && modalRowData.barcode) {
            var pb = sjParseBarcodeStringPrefixDigits(modalRowData.barcode);
            if (pb.prefix) {
                return { prefix: pb.prefix, digits: pb.digits > 0 ? pb.digits : 5 };
            }
        }
        return { prefix: '', digits: 5 };
    }

    /** Assign server barcodes to modal rows that still have an empty barcode input (sequential so used[] stays consistent). */
    function sjAssignModalBarcodesSequential(rowList) {
        if (!rowList || !rowList.length) return Promise.resolve();
        var i = 0;
        return new Promise(function(resolve) {
            function step() {
                if (i >= rowList.length) {
                    resolve();
                    return;
                }
                var row = rowList[i++];
                var inp = row.querySelector('[data-column="barcode"] input');
                if (!inp) {
                    step();
                    return;
                }
                var prefix = (row.getAttribute('data-barcode-prefix') || '').trim();
                var digits = parseInt(row.getAttribute('data-barcode-digits'), 10) || 5;
                var needsBarcode = !inp.value || String(inp.value).trim() === '';
                if (!needsBarcode && typeof sjShouldFetchFreshBarcodeForProductOpeningLine === 'function') {
                    needsBarcode = sjShouldFetchFreshBarcodeForProductOpeningLine({
                        barcode: String(inp.value).trim(),
                        barcode_prefix: prefix,
                        barcode_digits: digits
                    });
                }
                if (!needsBarcode) {
                    step();
                    return;
                }
                getNextBarcodeFromServer(prefix, digits).then(function(bc) {
                    inp.value = bc;
                    var esc = (bc || '').replace(/'/g, "\\'");
                    inp.setAttribute('onclick', "printBarcode('" + esc + "', event)");
                    var printIcon = row.querySelector('.barcode-print-icon');
                    if (printIcon) printIcon.setAttribute('onclick', "printBarcode('" + esc + "', event)");
                    step();
                }).catch(function() { step(); });
            }
            step();
        });
    }

    // Fetch next serial barcode from server (same logic as purchase_invoice for all vouchers).
    // Uses prefix + zero-padded serial; reads from barcode settings if prefix/digit not provided.
    // Queued so rapid ADD clicks never receive the same serial from concurrent requests.
    function getNextBarcodeFromServer(prefix, digit) {
        prefix = (prefix && String(prefix).trim()) ? String(prefix).trim() : '';
        digit = parseInt(digit, 10) || 0;
        if (digit < 1) digit = 5;
        return sjRunSerialBarcodeTask(function() {
            const usedBarcodes = sjCollectUsedBarcodesForNextSerial();
            const params = new URLSearchParams();
            if (prefix) params.set('prefix', prefix);
            if (digit > 0) params.set('digit', digit);
            if (typeof window.AURAGOLD_STOCK_JOURNAL_BRANCH_ID !== 'undefined' && window.AURAGOLD_STOCK_JOURNAL_BRANCH_ID > 0) {
                params.set('branch_id', String(window.AURAGOLD_STOCK_JOURNAL_BRANCH_ID));
            }
            usedBarcodes.forEach(function(b) { params.append('used[]', b); });
            return fetch('ajax/get-next-barcode.php?' + params.toString(), { method: 'GET', credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success && data.barcode) {
                        sjBarcodeReserve(data.barcode);
                        return data.barcode;
                    }
                    throw new Error(data.message || 'Failed to get barcode');
                });
        });
    }

    async function sjEnsureUniqueListBarcode(barcode, modalRowData) {
        modalRowData = modalRowData || {};
        var bc = String(barcode || '').trim();
        var freshListPolicy = typeof sjEachAddNeedsFreshListBarcode === 'function' && sjEachAddNeedsFreshListBarcode();

        if (!freshListPolicy) {
            if (bc && !sjIsBarcodeInUseLocally(bc)) {
                sjBarcodeReserve(bc);
                return bc;
            }
        } else if (bc) {
            // Product opening / purchase invoice: move the staging serial into the list (one number per Add).
            // A second server call here plus sjRefreshProductSelectionBarcodeAfterAdd was skipping odd numbers (12, 14, 16…).
            var rowCtx = Object.assign({}, modalRowData, { barcode: bc });
            var needsFresh = typeof sjShouldFetchFreshBarcodeForProductOpeningLine === 'function'
                && sjShouldFetchFreshBarcodeForProductOpeningLine(rowCtx);
            if (!needsFresh && !sjIsBarcodeOnProductList(bc)) {
                sjBarcodeReserve(bc);
                return bc;
            }
        }

        var rules = (typeof sjResolveBarcodePrefixDigitForNewLine === 'function')
            ? sjResolveBarcodePrefixDigitForNewLine(modalRowData)
            : { prefix: (modalRowData.barcode_prefix || ''), digits: parseInt(modalRowData.barcode_digits, 10) || 5 };
        var prefix = (rules && rules.prefix) ? rules.prefix : '';
        var digit = (rules && rules.digits) ? parseInt(rules.digits, 10) : 5;
        var maxAttempts = 8;
        for (var attempt = 0; attempt < maxAttempts; attempt++) {
            var nextBc = await getNextBarcodeFromServer(prefix, digit);
            if (!nextBc) continue;
            if (sjIsBarcodeOnProductList(nextBc)) {
                sjBarcodeRelease(nextBc);
                continue;
            }
            return nextBc;
        }
        throw new Error('Could not allocate a unique barcode.');
    }
    
    // Add empty product row to modal table
    // Legacy: random barcode (use getNextBarcodeFromServer for serial barcode)
    function generateUniqueBarcode(productId = null, characteristicId = null, prefix = 'B', digits = 8) {
        // Get all existing barcodes from both Product Selection table and Product List table
        const existingBarcodes = new Set();
        
        // Check Product Selection table (productListBody)
        const productSelectionBarcodes = document.querySelectorAll('#productListBody [data-column="barcode"] input, #productListBodyPage [data-column="barcode"] input');
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
        
        // If product_id is provided, try to get prefix and digits from product data
        if (productId) {
            // Try to get from product row in modal
            const productRow = document.querySelector(`#productListBody tr[data-product-id="${productId}"]${characteristicId ? `[data-characteristic-id="${characteristicId}"]` : ''}, #productListBodyPage tr[data-product-id="${productId}"]${characteristicId ? `[data-characteristic-id="${characteristicId}"]` : ''}`);
            if (productRow) {
                // Get prefix and digits from product data attributes or row data
                const rowPrefix = productRow.getAttribute('data-barcode-prefix');
                const rowDigits = productRow.getAttribute('data-barcode-digits');
                if (rowPrefix && rowPrefix.trim() !== '') {
                    prefix = rowPrefix;
                }
                if (rowDigits && !isNaN(parseInt(rowDigits))) {
                    digits = parseInt(rowDigits);
                }
            }
        }
        
        // Ensure prefix is not empty, default to 'B'
        if (!prefix || prefix.trim() === '') {
            prefix = 'B';
        }
        
        // Ensure digits is valid, default to 8
        digits = parseInt(digits) || 8;
        if (digits < 1) digits = 8;
        if (digits > 20) digits = 20; // Limit to reasonable size
        
        // Generate random barcode: prefix followed by digits number of random digits
        let barcode;
        let attempts = 0;
        const maxAttempts = 100;
        
        do {
            // Generate random number with specified digits
            const min = Math.pow(10, digits - 1);
            const max = Math.pow(10, digits) - 1;
            const randomNum = Math.floor(min + Math.random() * (max - min + 1));
            barcode = prefix + randomNum.toString().padStart(digits, '0');
            attempts++;
            
            // If we've tried too many times, add timestamp to ensure uniqueness
            if (attempts >= maxAttempts) {
                const timestamp = Date.now().toString().slice(-Math.min(6, digits));
                const remainingDigits = Math.max(1, digits - timestamp.length);
                const randomSuffix = Math.floor(Math.random() * Math.pow(10, remainingDigits)).toString().padStart(remainingDigits, '0');
                barcode = prefix + timestamp.slice(-digits) + randomSuffix;
                barcode = prefix + barcode.slice(prefix.length, prefix.length + digits);
                break;
            }
        } while (existingBarcodes.has(barcode));
        
        return barcode;
    }
    
    function addEmptyProductRow() {
        const tbody = document.getElementById('productListBodyPage') || document.querySelector('#productSelectionModal #productListBody');
        if (!tbody) return;
        
        // Remove the "no products" message if it exists
        const emptyRow = tbody.querySelector('tr:not(.product-row)');
        if (emptyRow) {
            emptyRow.remove();
        }
        
        var isDiamondMetalTab = typeof window.isDiamondStonesMetalDisplayName === 'function'
            ? window.isDiamondStonesMetalDisplayName(currentMetalName)
            : (typeof currentMetalName === 'string' && currentMetalName.trim().toLowerCase().replace(/\s+/g, ' ') === 'diamond & stones');
        var reuseBarcode = '';
        if (isDiamondMetalTab && tbody) {
            var prevRows = tbody.querySelectorAll('.product-row');
            if (prevRows.length) {
                var lastBc = prevRows[prevRows.length - 1].querySelector('[data-column="barcode"] input');
                if (lastBc && String(lastBc.value || '').trim()) reuseBarcode = String(lastBc.value).trim();
            }
        }
        var bcRules = sjInferModalBarcodeRulesFromTbody(tbody);
        var fetchPrefix = bcRules.prefix || '';
        var fetchDigits = bcRules.digits > 0 ? bcRules.digits : 5;
        var attrDigits = bcRules.digits > 0 ? bcRules.digits : 5;

        function finishAddEmptyProductRow(uniqueBarcode, attrPrefix, attrDigitsParam) {
            var vtd = (typeof window.SJ_DEFAULT_VOUCHER_TYPE === 'string' && window.SJ_DEFAULT_VOUCHER_TYPE) ? String(window.SJ_DEFAULT_VOUCHER_TYPE) : '';
            if (vtd === '' && typeof window !== 'undefined' && window.location && window.location.search) {
                try {
                    var u = new URLSearchParams(window.location.search);
                    var iu = u.get('item_id') ? parseInt(u.get('item_id'), 10) : 0;
                    if (iu > 0) vtd = 'purchase_invoice';
                    else if ((u.get('voucher') || '') === 'product_opening') vtd = 'product_opening';
                } catch (e) {}
            }
            const row = document.createElement('tr');
            row.className = 'product-row';
            row.setAttribute('data-product-id', '');
            row.setAttribute('data-characteristic-id', '');
            row.setAttribute('data-metal-id', currentMetalId || '');
            row.setAttribute('data-metal-name', typeof currentMetalName !== 'undefined' ? String(currentMetalName || '') : '');
            var ap = (attrPrefix != null && String(attrPrefix).trim() !== '') ? String(attrPrefix).trim() : '';
            var ad = parseInt(attrDigitsParam, 10);
            if (!ad || ad < 1) ad = 5;
            row.setAttribute('data-barcode-prefix', ap);
            row.setAttribute('data-barcode-digits', String(ad));
            const barcodeEsc = (uniqueBarcode || '').replace(/'/g, "\\'");
            row.innerHTML = `
            <td data-column="id" style="position: sticky; left: 0; background: #fff; z-index: 1; box-shadow: 1px 0 0 #e2e8f0;"></td>
            <td data-column="rfid"><input type="text" class="form-control form-control-sm" value="" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="voucher-type"><input type="text" class="form-control form-control-sm" value="${typeof escapeHtml === 'function' ? escapeHtml(vtd) : (vtd || '')}" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="photo" class="product-row-photo" style="text-align: center; vertical-align: middle; min-width: 70px;"><img src="" alt="" class="product-photo-thumb" style="max-width: 50px; max-height: 50px; display: none;"><span class="product-photo-placeholder text-muted" style="font-size: 0.65rem;">—</span></td>
            <td data-column="barcode" style="position: relative;">
                <div style="display: flex; align-items: center; gap: 5px;">
                    <input type="text" class="form-control form-control-sm barcode-input" value="${uniqueBarcode}" style="width: 100px; font-size: 0.7rem;" title="Auto-filled from server; you may edit if it matches this product barcode prefix and digit count." onclick="printBarcode('${barcodeEsc}', event)">
                    <i class="feather icon-printer barcode-print-icon" style="cursor: pointer; font-size: 0.9rem; color: #c5a864; flex-shrink: 0;" onclick="printBarcode('${barcodeEsc}', event)" title="Print Barcode"></i>
                </div>
            </td>
            <td data-column="design-no"><input type="text" class="form-control form-control-sm" value="" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="huid"><input type="text" class="form-control form-control-sm" value="" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="item-code"><input type="text" class="form-control form-control-sm" value="" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="category"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="">Select</option></select></td>
            <td data-column="product-category"><select class="form-control form-control-sm product-category-select" style="width: 120px; font-size: 0.7rem;"><option value="">Select Category</option></select></td>
            <td data-column="calculation"><select class="form-control form-control-sm" style="width: 120px; font-size: 0.7rem;">${buildCalculationSelectOptions()}</select></td>
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
            <td data-column="purchase-rate"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 90px; font-size: 0.7rem;"></td>
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
            <td data-column="purchase-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 130px; font-size: 0.7rem;"></td>
                        <td data-column="sale-percent"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;" placeholder="%" title="Sale % on Purchase Amount"></td>            <td data-column="sale-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 110px; font-size: 0.7rem;"></td>
            <td data-column="sale-amount-with"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 130px; font-size: 0.7rem;"></td>
            <td data-column="net-amt"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="tax-type"><select class="form-control form-control-sm" style="width: 120px; font-size: 0.7rem;">${buildSJTaxTypeSelectHtml()}</select></td>
            <td data-column="tax-percent"><input type="text" class="form-control form-control-sm" value="5" step="0.01" style="width: 70px; font-size: 0.7rem;"></td>
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
            <td data-column="images" class="stock-journal-images-cell" style="vertical-align: middle;">
                <div class="sj-images-wrap">
                    <input type="file" class="sj-image-input" accept=".jpg,.jpeg,.png,.webp" multiple style="display:none;">
                    <button type="button" class="btn btn-sm btn-outline-secondary sj-image-btn" style="font-size:0.7rem; padding:2px 6px; white-space:nowrap;" title="Add images (jpg, png, webp, max 2MB)"><i class="feather icon-upload" style="vertical-align:middle;"></i> Add</button>
                    <div class="sj-image-previews"></div>
                </div>
            </td>
            <td data-column="actions" style="text-align: center;">
                <i class="feather icon-edit" style="cursor: pointer; font-size: 0.8rem; color: #c5a864; margin-right: 8px;" onclick="editProductRowInTable(this)" title="Edit Row"></i>
                <i class="feather icon-trash-2" style="cursor: pointer; font-size: 0.8rem; color: #ef4444;" onclick="deleteProductRowFromModal(this)" title="Delete Row"></i>
            </td>
        `;
        
        // Append row to tbody
        tbody.appendChild(row);
        initStockJournalImageCell(row.querySelector('[data-column="images"]'));
        if (typeof reorderModalRowCellsToMatchHeader === 'function') {
            reorderModalRowCellsToMatchHeader(row);
        }
        if (typeof applyProductModalColumnVisibilityForTab === 'function' && (tbody.id === 'productListBody' || tbody.id === 'productListBodyPage' || (tbody && tbody.closest && tbody.closest('#productSelectionModal')))) {
            applyProductModalColumnVisibilityForTab(currentMetalId || '');
        }
        
        // Populate dropdowns
        const caratSelect = row.querySelector('.carat-select');
        if (caratSelect) {
            populateSelect(caratSelect, carats, 'id', 'name', 'Select Karat');
        }
        
        const locationSelect = row.querySelector('.location-select');
        if (locationSelect) {
            populateSelect(locationSelect, locations, 'id', 'name', 'Select Location');
        }
        
        // Populate category dropdown (Diamond & Stones tab → Jewellery / Diamonds / GemStones)
        const categorySelect = row.querySelector('[data-column="category"] select');
        if (categorySelect) {
            if (typeof populateCategorySelectForModal === 'function' && typeof isDiamondTabActive === 'function' && isDiamondTabActive()) {
                populateCategorySelectForModal(categorySelect, true);
            } else if (typeof categories !== 'undefined') {
                populateSelect(categorySelect, categories, 'id', 'name', 'Select Category');
                categorySelect.classList.add('category-select');
            }
        }
        const productCategorySelect = row.querySelector('.product-category-select');
        if (productCategorySelect && typeof populateSelect === 'function' && typeof categories !== 'undefined') {
            populateSelect(productCategorySelect, categories, 'id', 'name', 'Select Category');
        }
        if (typeof auragoldPopulateModalSpecSelectsForRow === 'function') {
            auragoldPopulateModalSpecSelectsForRow(row);
        }
        
        const calculationSelect = row.querySelector('[data-column="calculation"] select');
        if (calculationSelect && typeof applyCalculationSelectOptionsForRow === 'function' && typeof isDiamondTabActive === 'function') {
            applyCalculationSelectOptionsForRow(calculationSelect, row, isDiamondTabActive());
        }
        if (calculationSelect) {
            calculationSelect.addEventListener('change', function() {
                calculateModalRowNetWeight(row);
            });
        }
        
        // Add row double-click handler to edit row
        row.addEventListener('dblclick', function(e) {
            // Don't edit if clicking on action buttons, or any input/select/textarea elements
            if (e.target.closest('[data-column="actions"]') ||
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
            if (e.target.closest('[data-column="product"]') || e.target.closest('[data-column="actions"]') ||
                e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT' || e.target.tagName === 'TEXTAREA' ||
                e.target.closest('input') || e.target.closest('select') || e.target.closest('textarea') || e.target.closest('button') || e.target.closest('a')) {
                if (e.target.closest('[data-column="product"]') && !sjProductOpeningLockProductField()) {
                    openProductSearchModal(row);
                }
                return;
            }
            updateRowSelection(row, !row.classList.contains('selected'));
        });
        row.style.cursor = 'pointer';
        
        // Add calculation listeners
        addModalRowCalculationListeners(row);
        
        // Add click handler to Product field (readonly when single-product opening; editable for Diamond multi-line)
        const productInput = row.querySelector('[data-column="product"] input');
        if (productInput) {
            productInput.readOnly = true;
            if (sjProductOpeningLockProductField()) {
                productInput.style.cursor = 'default';
            } else {
                productInput.addEventListener('click', function(e) {
                    e.stopPropagation();
                    openProductSearchModal(row);
                });
                productInput.style.cursor = 'pointer';
            }
        }
        
        // Calculate initial values
        calculateModalRowNetWeight(row);
        
        // Product opening (Diamond & Stones): pre-fill product + barcode rules from URL context
        if (typeof sjGetProductOpeningLineTemplate === 'function') {
            var poCtx = sjGetProductOpeningLineTemplate();
            if (poCtx && typeof populateRowWithProduct === 'function') {
                try {
                    var uPo = new URLSearchParams(window.location.search);
                    if ((uPo.get('voucher') || '') === 'product_opening' && uPo.get('characteristic_id')) {
                        populateRowWithProduct(row, poCtx);
                    }
                } catch (ePo) {}
            }
        }
        
        // Do not focus Product Selection (modal) Gross Wt - focus goes to Product List Gross Wt after ADD (Shift + A) only
        
        function updateRowSelection(row, isSelected) {
            if (isSelected) {
                row.classList.add('selected');
                row.style.backgroundColor = '#fff3cd';
            } else {
                row.classList.remove('selected');
                row.style.backgroundColor = '';
            }
        }
        if (typeof syncDiamondTabSharedBarcodes === 'function') syncDiamondTabSharedBarcodes();
        if (typeof runStockJournalProductRowAlignmentPipeline === 'function') {
            runStockJournalProductRowAlignmentPipeline({ rows: row });
        } else {
            if (typeof stampProductModalDataGroupOnCells === 'function') stampProductModalDataGroupOnCells(row);
            if (typeof updateGroupHeaderVisibility === 'function') updateGroupHeaderVisibility();
        }
        }
        if (reuseBarcode) {
            finishAddEmptyProductRow(reuseBarcode, bcRules.prefix, attrDigits);
        } else {
            getNextBarcodeFromServer(fetchPrefix, fetchDigits).then(function(uniqueBarcode) {
                finishAddEmptyProductRow(uniqueBarcode || '', bcRules.prefix, attrDigits);
            }).catch(function() {
                finishAddEmptyProductRow('', bcRules.prefix, attrDigits);
            });
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
        
        // Include metal_id in search if available
        let url = 'ajax/search-products.php?search=' + encodeURIComponent(searchTerm) + '&limit=50';
        if (currentMetalId) {
            url += '&metal_id=' + currentMetalId;
        }
        if (typeof window.AURAGOLD_STOCK_JOURNAL_BRANCH_ID !== 'undefined' && window.AURAGOLD_STOCK_JOURNAL_BRANCH_ID > 0) {
            url += '&branch_id=' + encodeURIComponent(String(window.AURAGOLD_STOCK_JOURNAL_BRANCH_ID));
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
        row.setAttribute('data-metal-id', product.metal_id || currentMetalId || '');
        row.setAttribute('data-metal-name', (product.metal_name || currentMetalName || '').trim());
        var bpx = (product.barcode_prefix != null && String(product.barcode_prefix).trim() !== '') ? String(product.barcode_prefix).trim() : '';
        var bdg = parseInt(product.barcode_digits, 10);
        if (bpx) row.setAttribute('data-barcode-prefix', bpx);
        else row.removeAttribute('data-barcode-prefix');
        if (!isNaN(bdg) && bdg >= 1) row.setAttribute('data-barcode-digits', String(bdg));
        else row.removeAttribute('data-barcode-digits');
        
        // Update ID column
        const idCell = row.querySelector('[data-column="id"]');
        if (idCell) {
            idCell.textContent = product.id || '';
        }
        
        // Generate and set barcode if empty, or replace product-opening master tag with next serial
        const barcodeInput = row.querySelector('[data-column="barcode"] input');
        if (barcodeInput) {
            var rowPrefix = row.getAttribute('data-barcode-prefix') || '';
            var rowDigits = parseInt(row.getAttribute('data-barcode-digits'), 10) || 0;
            var needsBarcode = !barcodeInput.value || barcodeInput.value.trim() === '';
            if (!needsBarcode && typeof sjShouldFetchFreshBarcodeForProductOpeningLine === 'function') {
                needsBarcode = sjShouldFetchFreshBarcodeForProductOpeningLine({
                    barcode: barcodeInput.value.trim(),
                    barcode_prefix: rowPrefix,
                    barcode_digits: rowDigits
                });
            }
            if (needsBarcode) {
                const prefix = rowPrefix;
                const digits = rowDigits;
                getNextBarcodeFromServer(prefix, digits).then(function(barcode) {
                    barcodeInput.value = barcode;
                    barcodeInput.setAttribute('onclick', "printBarcode('" + barcode.replace(/'/g, "\\'") + "', event)");
                    const printIcon = row.querySelector('.barcode-print-icon');
                    if (printIcon) printIcon.setAttribute('onclick', "printBarcode('" + barcode.replace(/'/g, "\\'") + "', event)");
                }).catch(function() {
                    barcodeInput.value = '';
                });
            }
        }
        
        // Update Design No
        const designNoInput = row.querySelector('[data-column="design-no"] input');
        if (designNoInput && product.article) {
            designNoInput.value = product.article;
        }
        
        // Gross Wt: show 0 so user can enter (purchase weight not pre-filled)
        const grossWtInput = row.querySelector('[data-column="gross-wt"] input');
        if (grossWtInput) {
            grossWtInput.value = '0';
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
        
        // Update Category (diamond tab → string options; else category_id)
        const categorySelect = row.querySelector('[data-column="category"] select');
        if (categorySelect) {
            var dTabPick = typeof isDiamondTabActive === 'function' && isDiamondTabActive();
            if (typeof populateCategorySelectForModal === 'function') {
                populateCategorySelectForModal(categorySelect, dTabPick);
            } else if (typeof categories !== 'undefined' && typeof populateSelect === 'function') {
                populateSelect(categorySelect, categories, 'id', 'name', 'Select Category');
                categorySelect.classList.add('category-select');
            }
            if (product.category_id || product.diamond_category) {
                sjApplyModalCategoryFromProduct(categorySelect, product);
            }
            var calcPick = row.querySelector('[data-column="calculation"] select');
            if (calcPick && typeof applyCalculationSelectOptionsForRow === 'function' && typeof isDiamondTabActive === 'function') {
                applyCalculationSelectOptionsForRow(calcPick, row, isDiamondTabActive());
            }
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
            
            const tbody = row.closest('tbody');
            if (tbody && tbody.querySelectorAll('.product-row').length === 0) {
                const inModal = tbody.closest('#productSelectionModal');
                const msg = inModal ? 'Click "Add Product" button to add products for billing...' : 'No products found for this category';
                tbody.innerHTML = '<tr><td colspan="104" class="text-center text-muted py-4">' + msg + '</td></tr>';
            }
        }
    }
    
    /** After populating category options: diamond tab uses Diamonds/GemStones/Jewellery (string values); other tabs use category_id. */
    function sjApplyModalCategoryFromProduct(catSelect, p) {
        if (!catSelect || !p) return;
        var diamondTab = typeof isDiamondTabActive === 'function' && isDiamondTabActive();
        var opts = (typeof window.DIAMOND_CATEGORY_OPTIONS !== 'undefined' ? window.DIAMOND_CATEGORY_OPTIONS : []);
        if (diamondTab) {
            var v = (p.diamond_category != null && String(p.diamond_category).trim() !== '') ? String(p.diamond_category).trim() : '';
            if (!v || !opts.some(function(o) { return o.value === v; })) {
                v = '';
                if (p.category_id != null && p.category_id !== '' && typeof categories !== 'undefined') {
                    for (var i = 0; i < categories.length; i++) {
                        if (String(categories[i].id) === String(p.category_id)) {
                            var nm = String(categories[i].name || '').trim();
                            if (opts.some(function(o) { return o.value === nm; })) { v = nm; break; }
                        }
                    }
                }
            }
            if (!v || !opts.some(function(o) { return o.value === v; })) v = 'Jewellery';
            catSelect.value = v;
        } else if (p.category_id != null && p.category_id !== '') {
            catSelect.value = p.category_id;
        }
    }
    
    // Load products by metal, or by item_id (purchase invoice), or by characteristic_id (product_opening - single product only)
    function loadProducts(metalId, search = '', contextEl) {
        const tbody = getProductSelectionListTbody(contextEl);
        if (!tbody) {
            console.warn('loadProducts: no product selection tbody');
            return;
        }
        tbody.innerHTML = '<tr><td colspan="104" class="text-center text-muted py-4">Loading products...</td></tr>';
        
        const urlParams = new URLSearchParams(window.location.search);
        const itemId = urlParams.get('item_id') ? parseInt(urlParams.get('item_id')) : 0;
        const voucher = urlParams.get('voucher') || '';
        const characteristicId = urlParams.get('characteristic_id') ? parseInt(urlParams.get('characteristic_id'), 10) : 0;
        var vtDef = '';
        if (itemId > 0) vtDef = 'purchase_invoice';
        else if (voucher === 'product_opening') vtDef = 'product_opening';
        else if (typeof window.SJ_DEFAULT_VOUCHER_TYPE === 'string' && window.SJ_DEFAULT_VOUCHER_TYPE) vtDef = window.SJ_DEFAULT_VOUCHER_TYPE;
        
        let ajaxUrl = 'ajax/get-products-by-metal.php';
        let ajaxData = { metal_id: metalId, search: search };
        
        // Product Opening: show only the selected product (filter by characteristic_id)
        if (voucher === 'product_opening' && characteristicId > 0) {
            ajaxUrl = 'ajax/get-products-by-characteristic-id.php';
            ajaxData = { characteristic_id: characteristicId };
            var urlProductId = urlParams.get('product_id') ? parseInt(urlParams.get('product_id'), 10) : 0;
            if (urlProductId > 0) ajaxData.product_id = urlProductId;
        }
        // Purchase invoice: load products from purchase invoice item
        else if (itemId > 0) {
            ajaxUrl = 'ajax/get-products-by-item-id.php';
            ajaxData = { item_id: itemId };
        }
        
        // Use jQuery if available, otherwise use fetch
        if (typeof jQuery !== 'undefined' && jQuery.ajax) {
            var sjPoRenderLoadedProducts = function(response) {
                if (window.__sjForceLoadProductsResponse) {
                    response = window.__sjForceLoadProductsResponse;
                    window.__sjForceLoadProductsResponse = null;
                }
                if ((!response || !response.success || !response.products || !response.products.length)
                    && voucher === 'product_opening' && characteristicId > 0
                    && typeof sjGetProductOpeningLineTemplate === 'function') {
                    var poTpl = sjGetProductOpeningLineTemplate();
                    if (poTpl) {
                        response = { success: true, products: [poTpl] };
                    }
                }
                if (response.success && response.products.length > 0) {
                    let html = '';
                    response.products.forEach(function(product) {
                        const productName = product.name + (product.metal_name ? ' - ' + product.metal_name : '');
                        var pBarcode = product.barcode || '';
                        if (voucher === 'product_opening' && characteristicId > 0) {
                            pBarcode = '';
                        }
                        var pBarcodeEsc = pBarcode.replace(/'/g, "\\'");
                        html += `
                            <tr class="product-row" data-product-id="${product.id}" data-characteristic-id="${product.characteristic_id || ''}" data-metal-id="${product.metal_id || metalId || ''}" data-metal-name="${escapeHtml(product.metal_name || '')}" data-barcode-prefix="${escapeHtml(String((product.barcode_prefix != null && product.barcode_prefix !== '') ? product.barcode_prefix : '').trim())}" data-barcode-digits="${(parseInt(product.barcode_digits, 10) > 0 ? parseInt(product.barcode_digits, 10) : 5)}">
                                <td data-column="id" style="position: sticky; left: 0; background: #fff; z-index: 1; box-shadow: 1px 0 0 #e2e8f0;">${product.id || ''}</td>
                                <td data-column="rfid"><input type="text" class="form-control form-control-sm" value="" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="voucher-type"><input type="text" class="form-control form-control-sm" value="${escapeHtml(vtDef)}" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="photo" class="product-row-photo" style="text-align: center; vertical-align: middle; min-width: 70px;"><img src="" alt="" class="product-photo-thumb" style="max-width: 50px; max-height: 50px; display: none;"><span class="product-photo-placeholder text-muted" style="font-size: 0.65rem;">—</span></td>
                                <td data-column="barcode" style="position: relative;">
                                    <div style="display: flex; align-items: center; gap: 5px;">
                                        <input type="text" class="form-control form-control-sm barcode-input" value="${escapeHtml(pBarcode)}" style="width: 100px; font-size: 0.7rem;" title="Must match product prefix + digit count when edited manually." onclick="printBarcode('${pBarcodeEsc}', event)">
                                        <i class="feather icon-printer barcode-print-icon" style="cursor: pointer; font-size: 0.9rem; color: #c5a864; flex-shrink: 0;" onclick="printBarcode('${pBarcodeEsc}', event)" title="Print Barcode"></i>
                                    </div>
                                </td>
                                <td data-column="design-no"><input type="text" class="form-control form-control-sm" value="${escapeHtml(product.article || '')}" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="huid"><input type="text" class="form-control form-control-sm" value="" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="item-code"><input type="text" class="form-control form-control-sm" value="${escapeHtml(product.item_code != null && product.item_code !== '' ? product.item_code : (product.short_code || ''))}" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="category"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="">Select</option></select></td>
                                <td data-column="product-category"><select class="form-control form-control-sm product-category-select" style="width: 120px; font-size: 0.7rem;"><option value="">Select Category</option></select></td>
                                <td data-column="calculation"><select class="form-control form-control-sm" style="width: 120px; font-size: 0.7rem;">${buildCalculationSelectOptions()}</select></td>
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
                                <td data-column="purity"><input type="text" class="form-control form-control-sm" value="${product.opening_purity || 1}" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
                                <td data-column="purity-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" readonly style="width: 90px; font-size: 0.7rem;"></td>
                                <td data-column="gold-loss1"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="gold-loss2"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="metal-loss-value"><input type="text" class="form-control form-control-sm" value="0.000" step="0.001" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="wastage-per"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="wastage-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="metal-rate"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 90px; font-size: 0.7rem;"></td>
                                <td data-column="metal-value"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="purchase-rate"><input type="text" class="form-control form-control-sm" value="${parseFloat(product.purchase_rate != null && product.purchase_rate !== '' ? product.purchase_rate : (product.rate || 0)).toFixed(2)}" step="0.01" style="width: 90px; font-size: 0.7rem;"></td>
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
                                <td data-column="purchase-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 130px; font-size: 0.7rem;"></td>
                                            <td data-column="sale-percent"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;" placeholder="%" title="Sale % on Purchase Amount"></td>            <td data-column="sale-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 110px; font-size: 0.7rem;"></td>
                                <td data-column="sale-amount-with"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 130px; font-size: 0.7rem;"></td>
                                <td data-column="net-amt"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="tax-type"><select class="form-control form-control-sm" style="width: 120px; font-size: 0.7rem;">${buildSJTaxTypeSelectHtml()}</select></td>
                                <td data-column="tax-percent"><input type="text" class="form-control form-control-sm" value="5" step="0.01" style="width: 70px; font-size: 0.7rem;"></td>
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
                                <td data-column="images" class="stock-journal-images-cell" style="vertical-align: middle;">
                                    <div class="sj-images-wrap">
                                        <input type="file" class="sj-image-input" accept=".jpg,.jpeg,.png,.webp" multiple style="display:none;">
                                        <button type="button" class="btn btn-sm btn-outline-secondary sj-image-btn" style="font-size:0.7rem; padding:2px 6px; white-space:nowrap;" title="Add images (jpg, png, webp, max 2MB)"><i class="feather icon-upload" style="vertical-align:middle;"></i> Add</button>
                                        <div class="sj-image-previews"></div>
                                    </div>
                                </td>
                                <td data-column="actions" style="text-align: center;">
                <i class="feather icon-edit" style="cursor: pointer; font-size: 0.8rem; color: #c5a864; margin-right: 8px;" onclick="editProductRowInTable(this)" title="Edit Row"></i>
                                    <i class="feather icon-trash-2" style="cursor: pointer; font-size: 0.8rem; color: #ef4444;" onclick="deleteProductRowFromModal(this)" title="Delete Row"></i>
                                </td>
                            </tr>
                        `;
                    });
                    tbody.innerHTML = html;
                    // Product opening (single characteristic) or purchase-invoice line (item_id): the row's data-metal-id
                    // must match the active tab, otherwise a later tab click runs filterProductsByMetal() and hides the row.
                    (function sjAlignProductSelectionTabToLoadedLine() {
                        if (!response || !response.products || !response.products[0] || !response.products[0].metal_id) return;
                        var need = (itemId > 0) || (voucher === 'product_opening' && characteristicId > 0);
                        if (!need) return;
                        var mid = String(response.products[0].metal_id);
                        var tab = document.querySelector('.product-category-tabs .category-tab-btn[data-metal-id="' + mid.replace(/"/g, '\\"') + '"]');
                        if (!tab && response.products[0].metal_name) {
                            var mnl = String(response.products[0].metal_name).trim().toLowerCase();
                            document.querySelectorAll('.product-category-tabs .category-tab-btn').forEach(function(b) {
                                if (tab) return;
                                var bn = (b.getAttribute('data-metal-name') || '').trim().toLowerCase();
                                if (bn && bn === mnl) tab = b;
                            });
                        }
                        if (!tab) return;
                        if (!tab.classList.contains('active')) {
                            document.querySelectorAll('.category-tab-btn').forEach(function(t) { t.classList.remove('active'); });
                            tab.classList.add('active');
                        }
                        currentMetalId = mid;
                        if (typeof window !== 'undefined') window.sjCurrentMetalId = currentMetalId;
                        if (response.products[0].metal_name && typeof currentMetalName !== 'undefined') {
                            currentMetalName = response.products[0].metal_name;
                        }
                        if (typeof window.auragoldSyncProductModalExtraFields === 'function') {
                            window.auragoldSyncProductModalExtraFields(currentMetalName || response.products[0].metal_name || '');
                        }
                        if (typeof sjUpdateExcelSampleDownloadHref === 'function') {
                            sjUpdateExcelSampleDownloadHref(mid);
                        }
                    })();
                    tbody.querySelectorAll('.stock-journal-images-cell').forEach(function(cell) { initStockJournalImageCell(cell); });
                    tbody.querySelectorAll('tr.product-row').forEach(function(row) {
                        if (typeof reorderModalRowCellsToMatchHeader === 'function') reorderModalRowCellsToMatchHeader(row);
                        var pcat = row.querySelector('.product-category-select');
                        if (pcat && typeof populateSelect === 'function' && typeof categories !== 'undefined') {
                            populateSelect(pcat, categories, 'id', 'name', 'Select Category');
                        }
                        if (typeof auragoldPopulateModalSpecSelectsForRow === 'function') auragoldPopulateModalSpecSelectsForRow(row);
                    });
                    if (typeof applyProductModalColumnVisibilityForTab === 'function') {
                        applyProductModalColumnVisibilityForTab(typeof currentMetalId !== 'undefined' ? (currentMetalId || '') : '');
                    }
                    if (typeof runStockJournalProductRowAlignmentPipeline === 'function') {
                        runStockJournalProductRowAlignmentPipeline();
                    }
                    
                    // Populate carat and location dropdowns
                    tbody.querySelectorAll('.carat-select').forEach(function(select) {
                        populateSelect(select, carats, 'id', 'name', 'Select Karat');
                    });
                    
                    tbody.querySelectorAll('.location-select').forEach(function(select) {
                        populateSelect(select, locations, 'id', 'name', 'Select Location');
                    });
                    
                    // Category: Diamond & Stones tab → Diamonds / GemStones / Jewellery; else tbl categories by id
                    var diamondTabLoad = typeof isDiamondTabActive === 'function' && isDiamondTabActive();
                    tbody.querySelectorAll('[data-column="category"] select').forEach(function(select) {
                        if (typeof populateCategorySelectForModal === 'function') {
                            populateCategorySelectForModal(select, diamondTabLoad);
                        } else if (typeof populateSelect === 'function' && typeof categories !== 'undefined') {
                            populateSelect(select, categories, 'id', 'name', 'Select Category');
                            select.classList.add('category-select');
                        }
                    });
                    tbody.querySelectorAll('.product-row').forEach(function(row, idx) {
                        var p = response.products[idx];
                        if (!p) return;
                        sjApplyCaratSelectFromProduct(row, p);
                        sjApplyPurchaseMakingFromProduct(row, p);
                        var catSelect = row.querySelector('[data-column="category"] select');
                        if (catSelect) sjApplyModalCategoryFromProduct(catSelect, p);
                        var calcSel = row.querySelector('[data-column="calculation"] select');
                        if (calcSel && typeof applyCalculationSelectOptionsForRow === 'function' && typeof isDiamondTabActive === 'function') {
                            applyCalculationSelectOptionsForRow(calcSel, row, isDiamondTabActive());
                        }
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
                    
                    // Add click handler to product rows (toggle highlight on row click)
                    tbody.querySelectorAll('.product-row').forEach(function(row) {
                        row.addEventListener('click', function(e) {
                            if (e.target.closest('[data-column="product"]') || e.target.closest('[data-column="actions"]') ||
                                e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT' || e.target.tagName === 'TEXTAREA' ||
                                e.target.closest('input') || e.target.closest('select') || e.target.closest('textarea') || e.target.closest('button') || e.target.closest('a')) {
                                if (e.target.closest('[data-column="product"]') && !sjProductOpeningLockProductField()) {
                                    openProductSearchModal(row);
                                }
                                return;
                            }
                            updateRowSelection(row, !row.classList.contains('selected'));
                        });
                        row.style.cursor = 'pointer';
                        
                        // Add calculation listeners for this row
                        addModalRowCalculationListeners(row);
                        
                        // Calculate initial values
                        calculateModalRowNetWeight(row);
                    });
                    
                    sjAssignModalBarcodesSequential(Array.prototype.slice.call(tbody.querySelectorAll('.product-row')));
                    
                    // Do not focus Product Selection (modal) Gross Wt - focus goes to Product List Gross Wt after ADD (Shift + A) only
                    
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
                    var poTplEmpty = (voucher === 'product_opening' && characteristicId > 0 && typeof sjGetProductOpeningLineTemplate === 'function')
                        ? sjGetProductOpeningLineTemplate() : null;
                    if (poTplEmpty) {
                        sjPoRenderLoadedProducts({ success: true, products: [poTplEmpty] });
                        return;
                    }
                    tbody.innerHTML = '<tr><td colspan="104" class="text-center text-muted py-4">No products found</td></tr>';
                }
            };
            var poTplImmediate = (voucher === 'product_opening' && characteristicId > 0 && typeof sjGetProductOpeningLineTemplate === 'function')
                ? sjGetProductOpeningLineTemplate() : null;
            if (poTplImmediate) {
                sjPoRenderLoadedProducts({ success: true, products: [poTplImmediate] });
                return;
            }
            jQuery.ajax({
            url: ajaxUrl,
            type: 'GET',
            data: ajaxData,
            dataType: 'json',
            success: sjPoRenderLoadedProducts,
            error: function() {
                var poTplRender = (voucher === 'product_opening' && characteristicId > 0 && typeof sjGetProductOpeningLineTemplate === 'function')
                    ? sjGetProductOpeningLineTemplate() : null;
                if (poTplRender) {
                    sjPoRenderLoadedProducts({ success: true, products: [poTplRender] });
                    return;
                }
                tbody.innerHTML = '<tr><td colspan="104" class="text-center text-danger py-4">Error loading products</td></tr>';
            }
        });
        } else {
            // Fallback using fetch API
            let url = ajaxUrl + '?' + new URLSearchParams(ajaxData).toString();
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if ((!data || !data.success || !data.products || !data.products.length)
                        && voucher === 'product_opening' && characteristicId > 0
                        && typeof sjGetProductOpeningLineTemplate === 'function') {
                        var poTplFetch = sjGetProductOpeningLineTemplate();
                        if (poTplFetch) {
                            data = { success: true, products: [poTplFetch] };
                        }
                    }
                    if (data.success && data.products.length > 0) {
                        let html = '';
                        data.products.forEach(function(product) {
                            const productName = product.name + (product.metal_name ? ' - ' + product.metal_name : '');
                            var pBarcode2 = product.barcode || '';
                            if (voucher === 'product_opening' && characteristicId > 0) {
                                pBarcode2 = '';
                            }
                            var pBarcodeEsc2 = pBarcode2.replace(/'/g, "\\'");
                            html += `
                            <tr class="product-row" data-product-id="${product.id}" data-characteristic-id="${product.characteristic_id || ''}" data-metal-id="${product.metal_id || metalId || ''}" data-metal-name="${escapeHtml(product.metal_name || '')}" data-barcode-prefix="${escapeHtml(String((product.barcode_prefix != null && product.barcode_prefix !== '') ? product.barcode_prefix : '').trim())}" data-barcode-digits="${(parseInt(product.barcode_digits, 10) > 0 ? parseInt(product.barcode_digits, 10) : 5)}">
                                <td data-column="id" style="position: sticky; left: 0; background: #fff; z-index: 1; box-shadow: 1px 0 0 #e2e8f0;">${product.id || ''}</td>
                                <td data-column="rfid"><input type="text" class="form-control form-control-sm" value="" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="voucher-type"><input type="text" class="form-control form-control-sm" value="${escapeHtml(vtDef)}" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="photo" class="product-row-photo" style="text-align: center; vertical-align: middle; min-width: 70px;"><img src="" alt="" class="product-photo-thumb" style="max-width: 50px; max-height: 50px; display: none;"><span class="product-photo-placeholder text-muted" style="font-size: 0.65rem;">—</span></td>
                                <td data-column="barcode" style="position: relative;">
                                    <div style="display: flex; align-items: center; gap: 5px;">
                                        <input type="text" class="form-control form-control-sm barcode-input" value="${escapeHtml(pBarcode2)}" style="width: 100px; font-size: 0.7rem;" title="Must match product prefix + digit count when edited manually." onclick="printBarcode('${pBarcodeEsc2}', event)">
                                        <i class="feather icon-printer barcode-print-icon" style="cursor: pointer; font-size: 0.9rem; color: #c5a864; flex-shrink: 0;" onclick="printBarcode('${pBarcodeEsc2}', event)" title="Print Barcode"></i>
                                    </div>
                                </td>
                                <td data-column="design-no"><input type="text" class="form-control form-control-sm" value="${escapeHtml(product.article || '')}" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="huid"><input type="text" class="form-control form-control-sm" value="" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="item-code"><input type="text" class="form-control form-control-sm" value="${escapeHtml(product.item_code != null && product.item_code !== '' ? product.item_code : (product.short_code || ''))}" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="category"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="">Select</option></select></td>
                                <td data-column="product-category"><select class="form-control form-control-sm product-category-select" style="width: 120px; font-size: 0.7rem;"><option value="">Select Category</option></select></td>
                                <td data-column="calculation"><select class="form-control form-control-sm" style="width: 120px; font-size: 0.7rem;">${buildCalculationSelectOptions()}</select></td>
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
                                <td data-column="purity"><input type="text" class="form-control form-control-sm" value="${product.opening_purity || 1}" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
                                <td data-column="purity-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" readonly style="width: 90px; font-size: 0.7rem;"></td>
                                <td data-column="gold-loss1"><input type="text" class="form-control form-control-sm" value="0" step="0.001" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="gold-loss2"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="metal-loss-value"><input type="text" class="form-control form-control-sm" value="0.000" step="0.001" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="wastage-per"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="wastage-wt"><input type="text" class="form-control form-control-sm" value="0" step="0.001" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="metal-rate"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 90px; font-size: 0.7rem;"></td>
                                <td data-column="metal-value"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="purchase-rate"><input type="text" class="form-control form-control-sm" value="${parseFloat(product.purchase_rate != null && product.purchase_rate !== '' ? product.purchase_rate : (product.rate || 0)).toFixed(2)}" step="0.01" style="width: 90px; font-size: 0.7rem;"></td>
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
                                <td data-column="purchase-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 130px; font-size: 0.7rem;"></td>
                                            <td data-column="sale-percent"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;" placeholder="%" title="Sale % on Purchase Amount"></td>            <td data-column="sale-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 110px; font-size: 0.7rem;"></td>
                                <td data-column="sale-amount-with"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 130px; font-size: 0.7rem;"></td>
                                <td data-column="net-amt"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                                <td data-column="tax-type"><select class="form-control form-control-sm" style="width: 120px; font-size: 0.7rem;">${buildSJTaxTypeSelectHtml()}</select></td>
                                <td data-column="tax-percent"><input type="text" class="form-control form-control-sm" value="5" step="0.01" style="width: 70px; font-size: 0.7rem;"></td>
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
                                <td data-column="images" class="stock-journal-images-cell" style="vertical-align: middle;">
                                    <div class="sj-images-wrap">
                                        <input type="file" class="sj-image-input" accept=".jpg,.jpeg,.png,.webp" multiple style="display:none;">
                                        <button type="button" class="btn btn-sm btn-outline-secondary sj-image-btn" style="font-size:0.7rem; padding:2px 6px; white-space:nowrap;" title="Add images (jpg, png, webp, max 2MB)"><i class="feather icon-upload" style="vertical-align:middle;"></i> Add</button>
                                        <div class="sj-image-previews"></div>
                                    </div>
                                </td>
                                <td data-column="actions" style="text-align: center;">
                                    <i class="feather icon-edit" style="cursor: pointer; font-size: 0.8rem; color: #c5a864; margin-right: 8px;" onclick="editProductRowInTable(this)" title="Edit Row"></i>
                                    <i class="feather icon-trash-2" style="cursor: pointer; font-size: 0.8rem; color: #ef4444;" onclick="deleteProductRowFromModal(this)" title="Delete Row"></i>
                                </td>
                            </tr>
                            `;
                        });
                        tbody.innerHTML = html;
                        (function sjAlignProductSelectionTabToLoadedLineFetch() {
                            if (!data || !data.products || !data.products[0] || !data.products[0].metal_id) return;
                            var needF = (itemId > 0) || (voucher === 'product_opening' && characteristicId > 0);
                            if (!needF) return;
                            var midF = String(data.products[0].metal_id);
                            var tabF = document.querySelector('.category-tab-btn[data-metal-id="' + midF.replace(/"/g, '\\"') + '"]');
                            if (!tabF) return;
                            if (!tabF.classList.contains('active')) {
                                document.querySelectorAll('.category-tab-btn').forEach(function(t) { t.classList.remove('active'); });
                                tabF.classList.add('active');
                            }
                            currentMetalId = midF;
                            if (typeof window !== 'undefined') window.sjCurrentMetalId = currentMetalId;
                            if (data.products[0].metal_name && typeof currentMetalName !== 'undefined') {
                                currentMetalName = data.products[0].metal_name;
                            }
                        })();
                        tbody.querySelectorAll('.stock-journal-images-cell').forEach(function(cell) { initStockJournalImageCell(cell); });
                        tbody.querySelectorAll('tr.product-row').forEach(function(row) {
                            if (typeof reorderModalRowCellsToMatchHeader === 'function') reorderModalRowCellsToMatchHeader(row);
                            if (typeof applyProductModalColumnVisibilityForTab === 'function' && (tbody.id === 'productListBody' || tbody.id === 'productListBodyPage' || (tbody && tbody.closest && tbody.closest('#productSelectionModal')))) {
                                applyProductModalColumnVisibilityForTab(typeof currentMetalId !== 'undefined' ? (currentMetalId || '') : '');
                            }
                            var pcat = row.querySelector('.product-category-select');
                            if (pcat && typeof populateSelect === 'function' && typeof categories !== 'undefined') {
                                populateSelect(pcat, categories, 'id', 'name', 'Select Category');
                            }
                            if (typeof auragoldPopulateModalSpecSelectsForRow === 'function') auragoldPopulateModalSpecSelectsForRow(row);
                        });
                        
                        // Populate carat and location dropdowns
                        tbody.querySelectorAll('.carat-select').forEach(function(select) {
                            populateSelect(select, carats, 'id', 'name', 'Select Karat');
                        });
                        
                        tbody.querySelectorAll('.location-select').forEach(function(select) {
                            populateSelect(select, locations, 'id', 'name', 'Select Location');
                        });
                        
                        var diamondTabFetch = typeof isDiamondTabActive === 'function' && isDiamondTabActive();
                        tbody.querySelectorAll('[data-column="category"] select').forEach(function(select) {
                            if (typeof populateCategorySelectForModal === 'function') {
                                populateCategorySelectForModal(select, diamondTabFetch);
                            } else if (typeof populateSelect === 'function' && typeof categories !== 'undefined') {
                                populateSelect(select, categories, 'id', 'name', 'Select Category');
                                select.classList.add('category-select');
                            }
                        });
                        tbody.querySelectorAll('.product-row').forEach(function(row, idx) {
                            var p = data.products[idx];
                            if (!p) return;
                            sjApplyCaratSelectFromProduct(row, p);
                            sjApplyPurchaseMakingFromProduct(row, p);
                            var catSelect = row.querySelector('[data-column="category"] select');
                            if (catSelect) sjApplyModalCategoryFromProduct(catSelect, p);
                            var calcSel = row.querySelector('[data-column="calculation"] select');
                            if (calcSel && typeof applyCalculationSelectOptionsForRow === 'function' && typeof isDiamondTabActive === 'function') {
                                applyCalculationSelectOptionsForRow(calcSel, row, isDiamondTabActive());
                            }
                        });
                        
                        // Add click handler and calculation listeners to product rows
                        tbody.querySelectorAll('.product-row').forEach(function(row) {
                            row.addEventListener('click', function(e) {
                                if (e.target.closest('[data-column="product"]') || e.target.closest('[data-column="actions"]') ||
                                    e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT' || e.target.tagName === 'TEXTAREA' ||
                                    e.target.closest('input') || e.target.closest('select') || e.target.closest('textarea') || e.target.closest('button') || e.target.closest('a')) {
                                    if (e.target.closest('[data-column="product"]') && !sjProductOpeningLockProductField()) {
                                        openProductSearchModal(row);
                                    }
                                    return;
                                }
                                updateRowSelection(row, !row.classList.contains('selected'));
                            });
                            row.style.cursor = 'pointer';
                            
                            const productInput = row.querySelector('[data-column="product"] input');
                            if (productInput) {
                                productInput.readOnly = true;
                                if (sjProductOpeningLockProductField()) {
                                    productInput.style.cursor = 'default';
                                } else {
                                    productInput.addEventListener('click', function(e) {
                                        e.stopPropagation();
                                        openProductSearchModal(row);
                                    });
                                    productInput.style.cursor = 'pointer';
                                }
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
                        
                        sjAssignModalBarcodesSequential(Array.prototype.slice.call(tbody.querySelectorAll('.product-row')));
                        
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
                        tbody.innerHTML = '<tr><td colspan="104" class="text-center text-muted py-4">No products found</td></tr>';
                    }
                })
                .catch(error => {
                    tbody.innerHTML = '<tr><td colspan="104" class="text-center text-danger py-4">Error loading products</td></tr>';
                });
        }
    }
    
    // Add Product Row Button Event Listener
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize category tabs on page load (sets active metal before column prefs / order load)
        initCategoryTabs();
        if (typeof loadProductModalColumnPreferences === 'function') {
            loadProductModalColumnPreferences(function() {
                if (typeof sjEnsureProductOpeningGridLoaded === 'function') sjEnsureProductOpeningGridLoaded();
            });
        } else if (typeof sjEnsureProductOpeningGridLoaded === 'function') {
            sjEnsureProductOpeningGridLoaded();
        }
        if (typeof window.runStockJournalColumnDragInit === 'function') {
            window.runStockJournalColumnDragInit();
        }
        setTimeout(function() {
            if (typeof window.applyMetalGroupHeaderLabelsToGrids === 'function') {
                window.applyMetalGroupHeaderLabelsToGrids();
            }
            if (typeof runStockJournalProductRowAlignmentPipeline === 'function') {
                runStockJournalProductRowAlignmentPipeline();
            }
        }, 0);
        
        document.querySelectorAll('.sj-add-product-row-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                addEmptyProductRow();
            });
        });
    });
    
    $(document).on('click', '.sj-add-product-row-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        addEmptyProductRow();
    });
    
    // Product Selection modal: tab-wise column preferences (Gold / Silver / etc. each have their own)
    const STOCK_JOURNAL_MODAL_COLUMNS_PAGE = 'stock-journal-product-modal';
    let productModalColumnSaveTimeout = null;
    var SJ_COLUMN_DROPDOWN_SEL = '#modalTableSettingsDropdown, #modalTableSettingsDropdownModal';

    /** Prefs from DB must never hide thead/body contract columns (net + reverse + images + actions). */
    var STOCK_JOURNAL_FORCED_MODAL_COLUMNS = { 'net-amt-tax': 1, 'reverse': 1, 'images': 1, 'actions': 1 };
    function mergeStockJournalForcedStructuralColumnPrefs() {
        var by = window.productModalColumnVisibilityByTab;
        if (!by || typeof by !== 'object') return;
        Object.keys(by).forEach(function(tabKey) {
            if (!by[tabKey] || typeof by[tabKey] !== 'object') by[tabKey] = {};
            Object.keys(STOCK_JOURNAL_FORCED_MODAL_COLUMNS).forEach(function(col) {
                by[tabKey][col] = 1;
            });
        });
    }
    let sjAlignmentQueued = false;
    let sjAlignmentRunning = false;

    function runStockJournalProductRowAlignmentPipeline(options) {
        options = options || {};

        if (sjAlignmentRunning || sjAlignmentQueued) {
            return;
        }

        sjAlignmentQueued = true;

        requestAnimationFrame(function() {
            sjAlignmentRunning = true;
            sjAlignmentQueued = false;

            try {
                if (typeof console !== 'undefined' && console.time) console.time('sj-align');
                var rows = options.rows;
                if (!rows) {
                    rows = document.querySelectorAll('#productListBodyPage tr.product-row, #productListBody tr.product-row');
                } else if (rows instanceof Element) {
                    rows = [rows];
                } else if (!Array.isArray(rows) && !(rows instanceof NodeList)) {
                    rows = [rows];
                }

                Array.prototype.forEach.call(rows, function(row) {
                    if (!row) return;
                    if (typeof window.reorderModalRowCellsToMatchHeader === 'function') {
                        window.reorderModalRowCellsToMatchHeader(row);
                    }
                    if (typeof window.stampProductModalDataGroupOnCells === 'function') {
                        window.stampProductModalDataGroupOnCells(row);
                    }
                });

                if (typeof window.fixProductModalHeader === 'function') {
                    window.fixProductModalHeader();
                }
                if (typeof window.fixBodyAlignment === 'function') {
                    window.fixBodyAlignment();
                }
                if (typeof window.updateProductModalPlaceholderColspan === 'function') {
                    window.updateProductModalPlaceholderColspan();
                }
                if (typeof window.adjustProductModalStickyRightColumns === 'function') {
                    window.adjustProductModalStickyRightColumns();
                }
                if (typeof window.markProductModalGroupEndColumns === 'function') {
                    window.markProductModalGroupEndColumns();
                }
            } finally {
                if (typeof console !== 'undefined' && console.timeEnd) console.timeEnd('sj-align');
                sjAlignmentRunning = false;
            }
        });
    }

    window.runStockJournalProductRowAlignmentPipeline = runStockJournalProductRowAlignmentPipeline;

    function getStockJournalColumnDropdowns() {
        return document.querySelectorAll(SJ_COLUMN_DROPDOWN_SEL);
    }

    var SJ_PRODUCT_MODAL_GROUP_LABELS = {
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

    function sjColumnSettingsLabel(col) {
        if (window.METAL_GROUP_HEADER_LABELS && window.METAL_GROUP_HEADER_LABELS[col]) {
            return window.METAL_GROUP_HEADER_LABELS[col];
        }
        if (window.PRODUCT_MODAL_COLUMN_LABELS && window.PRODUCT_MODAL_COLUMN_LABELS[col]) {
            return window.PRODUCT_MODAL_COLUMN_LABELS[col];
        }
        return String(col || '').replace(/-/g, ' ').replace(/\b\w/g, function(c) { return c.toUpperCase(); });
    }

    function sjCreateColumnSettingsItem(settingsDropdown, col) {
        var colPrefix = settingsDropdown.id === 'modalTableSettingsDropdownModal' ? 'modal-m-col-' : 'modal-col-';
        var id = colPrefix + col;
        var item = document.createElement('div');
        item.className = 'table-settings-item';
        var safeLabel = String(sjColumnSettingsLabel(col)).replace(/&/g, '&amp;').replace(/</g, '&lt;');
        item.innerHTML = '<input type="checkbox" id="' + id + '" data-column="' + col + '" checked><label for="' + id + '">' + safeLabel + '</label>';
        return item;
    }

    function ensureStockJournalGroupCheckboxesInDropdown(settingsDropdown) {
        if (!settingsDropdown) return;
        var columnGroups = window.PRODUCT_MODAL_COLUMN_GROUPS || {};
        var listHost = settingsDropdown.querySelector('.table-settings-dropdown-body') || settingsDropdown;
        var searchDiv = settingsDropdown.querySelector('.table-settings-search');
        /* Flat dropdown (e.g. main table): first slot for column rows = after search. Common modal: rows live in .table-settings-dropdown-body */
        var firstListSlot = (listHost === settingsDropdown && searchDiv && searchDiv.nextSibling) ? searchDiv.nextSibling : listHost.firstChild;
        var itemsByColumn = {};
        var existingSection = settingsDropdown.querySelector('.table-settings-groups-section');
        if (existingSection) {
            var liftRef = firstListSlot;
            var subCols = Array.prototype.slice.call(existingSection.querySelectorAll('.table-settings-sub-column'));
            for (var li = subCols.length - 1; li >= 0; li--) {
                var liftItem = subCols[li];
                if (liftItem.parentNode) {
                    listHost.insertBefore(liftItem, liftRef);
                }
            }
            existingSection.remove();
        }
        settingsDropdown.querySelectorAll('.table-settings-item input[data-column]').forEach(function(inp) {
            var item = inp.closest('.table-settings-item');
            if (item) itemsByColumn[inp.getAttribute('data-column')] = item;
        });
        var groupIdPrefix = settingsDropdown.id === 'modalTableSettingsDropdownModal' ? 'modal-m-col-group-' : 'modal-col-group-';
        var wrapper = document.createElement('div');
        wrapper.className = 'table-settings-groups-section';
        wrapper.innerHTML = '<div class="table-settings-groups-title" style="font-weight: 700; margin: 0.5rem 0 0.25rem 0; font-size: 0.8rem; color: #64748b;">Column groups</div>';
        Object.keys(columnGroups).forEach(function(groupKey) {
            var block = document.createElement('div');
            block.className = 'table-settings-group-block';
            block.setAttribute('data-group', groupKey);
            var label = SJ_PRODUCT_MODAL_GROUP_LABELS[groupKey] || groupKey;
            var gid = groupIdPrefix + groupKey;
            var groupRow = document.createElement('div');
            groupRow.className = 'table-settings-item table-settings-group-item';
            groupRow.setAttribute('data-group', groupKey);
            var safeLabel = String(label).replace(/&/g, '&amp;').replace(/</g, '&lt;');
            groupRow.innerHTML = '<input type="checkbox" id="' + gid + '" data-group="' + groupKey + '" checked><label for="' + gid + '">' + safeLabel + '</label>';
            block.appendChild(groupRow);
            (columnGroups[groupKey] || []).forEach(function(col) {
                var item = itemsByColumn[col];
                if (!item) {
                    item = sjCreateColumnSettingsItem(settingsDropdown, col);
                    itemsByColumn[col] = item;
                }
                if (item) {
                    item.classList.add('table-settings-sub-column');
                    item.setAttribute('data-group', groupKey);
                    block.appendChild(item);
                    delete itemsByColumn[col];
                }
            });
            wrapper.appendChild(block);
        });
        var insertPoint = (listHost === settingsDropdown && searchDiv && searchDiv.nextSibling) ? searchDiv.nextSibling : listHost.firstChild;
        var orphanOrder = ['checkbox'];
        orphanOrder.forEach(function(col) {
            if (itemsByColumn[col]) {
                listHost.insertBefore(itemsByColumn[col], insertPoint);
                insertPoint = itemsByColumn[col].nextSibling;
            }
        });
        Object.keys(itemsByColumn).forEach(function(col) {
            if (orphanOrder.indexOf(col) === -1) {
                listHost.insertBefore(itemsByColumn[col], insertPoint);
                insertPoint = itemsByColumn[col].nextSibling;
            }
        });
        listHost.insertBefore(wrapper, insertPoint);
        sjUpdateAllSubColumnDisabledStates(settingsDropdown);
    }

    function sjSetSubColumnDisabledState(settingsDropdown, groupKey, disabled) {
        var columnGroups = window.PRODUCT_MODAL_COLUMN_GROUPS || {};
        var cols = columnGroups[groupKey];
        if (!cols || !cols.length) return;
        cols.forEach(function(c) {
            var cb = settingsDropdown.querySelector('input[data-column="' + c + '"]');
            if (cb) {
                cb.disabled = disabled;
                var item = cb.closest('.table-settings-item');
                if (item) item.classList.toggle('sub-column-disabled', disabled);
            }
        });
    }

    function sjUpdateAllSubColumnDisabledStates(settingsDropdown) {
        var columnGroups = window.PRODUCT_MODAL_COLUMN_GROUPS || {};
        Object.keys(columnGroups).forEach(function(groupKey) {
            var groupCb = settingsDropdown.querySelector('.table-settings-groups-section input[data-group="' + groupKey + '"]');
            var groupChecked = groupCb ? groupCb.checked : true;
            sjSetSubColumnDisabledState(settingsDropdown, groupKey, !groupChecked);
        });
    }

    function sjSyncGroupCheckboxState(settingsDropdown, groupKey) {
        var columnGroups = window.PRODUCT_MODAL_COLUMN_GROUPS || {};
        var cols = columnGroups[groupKey];
        if (!cols || !cols.length) return;
        var anyVisible = cols.some(function(c) {
            var cb = settingsDropdown.querySelector('input[data-column="' + c + '"]');
            return cb && cb.checked;
        });
        var groupCb = settingsDropdown.querySelector('.table-settings-groups-section input[data-group="' + groupKey + '"]');
        if (groupCb) groupCb.checked = anyVisible;
    }

    function sjSyncAllGroupCheckboxStatesEverywhere() {
        var columnGroups = window.PRODUCT_MODAL_COLUMN_GROUPS || {};
        getStockJournalColumnDropdowns().forEach(function(d) {
            Object.keys(columnGroups).forEach(function(gk) {
                sjSyncGroupCheckboxState(d, gk);
            });
        });
    }

    function sjSetProductColumnVisibleEverywhere(columnName, isVisible) {
        if (columnName == null || columnName === '') return;
        if (typeof window.toggleColumnVisibility === 'function') {
            window.toggleColumnVisibility(columnName, isVisible);
        } else {
            document.querySelectorAll('#productListTable, #productListTablePage').forEach(function(table) {
                var headers = table.querySelectorAll('th[data-column="' + columnName + '"]');
                var cells = table.querySelectorAll('td[data-column="' + columnName + '"]');
                headers.forEach(function(header) {
                    header.classList.toggle('hidden', !isVisible);
                    header.style.display = isVisible ? '' : 'none';
                });
                cells.forEach(function(cell) {
                    cell.classList.toggle('hidden', !isVisible);
                    cell.style.display = isVisible ? '' : 'none';
                });
            });
        }
        getStockJournalColumnDropdowns().forEach(function(drop) {
            var cb = drop.querySelector('input[data-column="' + columnName + '"]');
            if (cb) cb.checked = isVisible;
        });
    }

    function loadProductModalColumnPreferences(onLoaded) {
        $.ajax({
            url: 'ajax/get-column-preferences.php',
            type: 'POST',
            data: { page_name: STOCK_JOURNAL_MODAL_COLUMNS_PAGE },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success' && response.by_tab) {
                    window.productModalColumnVisibilityByTab = response.by_tab;
                    mergeStockJournalForcedStructuralColumnPrefs();
                } else {
                    window.productModalColumnVisibilityByTab = {};
                }
                // Resolve metal tab from active tab button (currentMetalId may still be unset if AJAX beat DOMContentLoaded)
                var tabKey = '';
                var activeMetalBtn = document.querySelector('.product-category-tabs .category-tab-btn.active') || document.querySelector('.category-tab-btn.active');
                if (activeMetalBtn) {
                    var mid = activeMetalBtn.getAttribute('data-metal-id');
                    if (mid !== null && mid !== undefined && String(mid).trim() !== '') {
                        tabKey = String(mid).trim();
                    }
                }
                if (tabKey === '' && currentMetalId !== undefined && currentMetalId !== null && String(currentMetalId).trim() !== '') {
                    tabKey = String(currentMetalId).trim();
                }
                if (tabKey === '' && typeof window.sjCurrentMetalId !== 'undefined' && window.sjCurrentMetalId != null && String(window.sjCurrentMetalId).trim() !== '') {
                    tabKey = String(window.sjCurrentMetalId).trim();
                }
                if (tabKey === '') tabKey = 'main';
                if (typeof applyProductModalColumnVisibilityForTab === 'function') {
                    applyProductModalColumnVisibilityForTab(tabKey);
                }
                if (typeof runStockJournalProductRowAlignmentPipeline === 'function') {
                    runStockJournalProductRowAlignmentPipeline();
                }
                if (typeof onLoaded === 'function') onLoaded();
                if (typeof window.runStockJournalColumnDragInit === 'function') {
                    window.runStockJournalColumnDragInit();
                }
            },
            error: function() {
                if (!window.productModalColumnVisibilityByTab) window.productModalColumnVisibilityByTab = {};
                if (typeof window.runStockJournalColumnDragInit === 'function') {
                    window.runStockJournalColumnDragInit();
                }
                if (typeof onLoaded === 'function') onLoaded();
            }
        });
    }
    
    function applyProductModalColumnVisibilityForTab(tabKey) {
        if (tabKey === undefined || tabKey === null) return;
        var tk = String(tabKey).trim();
        if (tk === '') tk = 'main';
        if (!window.productModalColumnVisibilityByTab) window.productModalColumnVisibilityByTab = {};
        if (!window.productModalColumnVisibilityByTab[tk]) window.productModalColumnVisibilityByTab[tk] = {};
        Object.keys(STOCK_JOURNAL_FORCED_MODAL_COLUMNS).forEach(function(col) {
            window.productModalColumnVisibilityByTab[tk][col] = 1;
        });
        var isDiamondFamilyTab = (typeof window.isDiamondTabActive === 'function' && window.isDiamondTabActive());
        var diamondVisibleSet = {};
        if (typeof window.DIAMOND_TAB_VISIBLE_COLUMNS !== 'undefined' && window.DIAMOND_TAB_VISIBLE_COLUMNS && window.DIAMOND_TAB_VISIBLE_COLUMNS.length) {
            window.DIAMOND_TAB_VISIBLE_COLUMNS.forEach(function(col) { diamondVisibleSet[col] = 1; });
        }
        var savedDiamond = window.productModalColumnVisibilityByTab &&
            (window.productModalColumnVisibilityByTab[tk] || window.productModalColumnVisibilityByTab[tabKey]);
        var prefs;
        if (isDiamondFamilyTab) {
            // Start from diamond defaults, then apply saved overrides (do not replace entire set with sparse saved prefs)
            prefs = Object.assign({}, diamondVisibleSet, savedDiamond && typeof savedDiamond === 'object' ? savedDiamond : {});
            Object.keys(STOCK_JOURNAL_FORCED_MODAL_COLUMNS).forEach(function(col) { prefs[col] = 1; });
            if (typeof window.auragoldProductModalApplyAlwaysVisibleAmountColumns === 'function') {
                prefs = window.auragoldProductModalApplyAlwaysVisibleAmountColumns(prefs);
            }
        } else {
            prefs = (typeof window.mergeProductModalMetalTabPrefs === 'function')
                ? window.mergeProductModalMetalTabPrefs(tk, tabKey)
                : savedDiamond;
        }
        var diamondGroupColumns = (typeof window.getDiamondGroupColumnKeys === 'function')
            ? window.getDiamondGroupColumnKeys()
            : ['pkt-wt', 'pkt-less-wt', 'gross-wt', 'stone-weight', 'less-wt', 'net-wt', 'quantity', 'rate', 'amount'];
        var certSpecColumns = (window.PRODUCT_MODAL_COLUMN_GROUPS && window.PRODUCT_MODAL_COLUMN_GROUPS['cert-spec-group'])
            ? window.PRODUCT_MODAL_COLUMN_GROUPS['cert-spec-group'].slice()
            : ['certificate-amount', 'certificate-no', 'certificate-link', 'video-link', 'cut', 'color', 'seive-size', 'size', 'shape', 'clarity', 'unit-price'];
        var diamondOnlyColumnSet = {};
        diamondGroupColumns.forEach(function(c) { diamondOnlyColumnSet[c] = 1; });
        certSpecColumns.forEach(function(c) { diamondOnlyColumnSet[c] = 1; });
        ['voucher-type', 'item-code', 'product-category', 'fc-amount', 'diamond-line-metal-value', 'rapnet-valuation', 'mark-up-amount', 'mark-up-per', 'setting-charge', 'stone-amount'].forEach(function(c) {
            diamondOnlyColumnSet[c] = 1;
        });
        function modalColumnShouldShow(columnName) {
            if (columnName && columnName.indexOf('extra-field-') === 0) {
                if (prefs && Object.prototype.hasOwnProperty.call(prefs, columnName)) {
                    return prefs[columnName] === 1;
                }
                return true;
            }
            if (!isDiamondFamilyTab && columnName === 'category') {
                return false;
            }
            if (!isDiamondFamilyTab && diamondOnlyColumnSet[columnName]) {
                return false;
            }
            if (isDiamondFamilyTab) {
                if (prefs && Object.prototype.hasOwnProperty.call(prefs, columnName)) {
                    return prefs[columnName] === 1;
                }
                return diamondVisibleSet[columnName] === 1;
            }
            if (prefs && Object.prototype.hasOwnProperty.call(prefs, columnName)) {
                return prefs[columnName] === 1;
            }
            return true;
        }
        // Main page + modal each have a column dropdown (#modalTableSettingsDropdown / #modalTableSettingsDropdownModal)
        var dropdowns = getStockJournalColumnDropdowns();
        var tables = document.querySelectorAll('#productListTable, #productListTablePage');
        if (!dropdowns.length || !tables.length) return;
        dropdowns.forEach(function(settingsDropdown) {
            var checkboxes = settingsDropdown.querySelectorAll('input[type="checkbox"][data-column]');
            checkboxes.forEach(function(checkbox) {
                var columnName = checkbox.getAttribute('data-column');
                checkbox.checked = modalColumnShouldShow(columnName);
            });
        });
        tables.forEach(function(table) {
            var checkboxes = dropdowns[0].querySelectorAll('input[type="checkbox"][data-column]');
            checkboxes.forEach(function(checkbox) {
                var columnName = checkbox.getAttribute('data-column');
                var isVisible = modalColumnShouldShow(columnName);
                var headers = table.querySelectorAll('th[data-column="' + columnName + '"]');
                var cells = table.querySelectorAll('td[data-column="' + columnName + '"]');
                headers.forEach(function(el) {
                    el.classList.toggle('hidden', !isVisible);
                    el.style.display = isVisible ? '' : 'none';
                });
                cells.forEach(function(el) {
                    el.classList.toggle('hidden', !isVisible);
                    el.style.display = isVisible ? '' : 'none';
                    /* Like toggleColumnVisibility: keep nested controls in sync. Stale display:none !important
                       on inputs would leave a column looking empty while the th is visible again. */
                    el.querySelectorAll('input, select, textarea').forEach(function(inp) {
                        if (isVisible) {
                            if (inp.style.getPropertyValue('display') === 'none' && inp.style.getPropertyPriority('display') === 'important') {
                                inp.style.removeProperty('display');
                            }
                        } else {
                            inp.style.setProperty('display', 'none', 'important');
                        }
                    });
                });
            });
        });
        dropdowns.forEach(function(d) {
            ensureStockJournalGroupCheckboxesInDropdown(d);
        });
        dropdowns.forEach(function(d) {
            sjUpdateAllSubColumnDisabledStates(d);
        });
        sjSyncAllGroupCheckboxStatesEverywhere();
        tables.forEach(function(table) {
            var groupHeaderRow = table.querySelector('thead tr:first-child');
            if (!groupHeaderRow) return;
            ['diamond-group', 'cert-spec-group'].forEach(function(gk) {
                var gh = groupHeaderRow.querySelector('th[data-group="' + gk + '"]');
                if (!gh) return;
                if (isDiamondFamilyTab) {
                    gh.style.display = '';
                    gh.classList.remove('hidden');
                } else {
                    gh.style.display = 'none';
                    gh.classList.add('hidden');
                }
            });
        });
        (function sjHideProductCategoryOnDiamondFamilyMetalTabs() {
            var btn = document.querySelector('.product-category-tabs .category-tab-btn.active') || document.querySelector('.category-tab-btn.active');
            var mname = btn ? (btn.getAttribute('data-metal-name') || '').trim() : '';
            var hidePcat = (typeof window.isDiamondStonesMetalDisplayName === 'function' && window.isDiamondStonesMetalDisplayName(mname))
                || (typeof window.isLoosOrLooseDiamondMetalDisplayName === 'function' && window.isLoosOrLooseDiamondMetalDisplayName(mname));
            if (hidePcat && typeof sjSetProductColumnVisibleEverywhere === 'function') {
                sjSetProductColumnVisibleEverywhere('product-category', false);
            }
        })();
        requestAnimationFrame(function() {
            if (typeof window.syncProductModalColumnLayoutAfterToggle === 'function') {
                window.syncProductModalColumnLayoutAfterToggle();
            }
        });
        if (typeof window.applyMetalGroupHeaderLabelsToGrids === 'function') {
            window.applyMetalGroupHeaderLabelsToGrids();
        }
    }
    
    function saveProductModalColumnPreferencesDebounced(tabKey) {
        if (tabKey === undefined || tabKey === null) return;
        var tkNorm = String(tabKey).trim();
        if (tkNorm === '') tkNorm = 'main';
        clearTimeout(productModalColumnSaveTimeout);
        productModalColumnSaveTimeout = setTimeout(function() {
            var settingsDropdown = document.querySelector('#modalTableSettingsDropdown') || document.querySelector('#modalTableSettingsDropdownModal');
            if (!settingsDropdown) return;
            var checkboxes = settingsDropdown.querySelectorAll('input[type="checkbox"][data-column]');
            var prefs = {};
            checkboxes.forEach(function(cb) {
                prefs[cb.getAttribute('data-column')] = cb.checked ? 1 : 0;
            });
            if (!window.productModalColumnVisibilityByTab) window.productModalColumnVisibilityByTab = {};
            if (!window.productModalColumnVisibilityByTab[tkNorm]) window.productModalColumnVisibilityByTab[tkNorm] = {};
            for (var k in prefs) window.productModalColumnVisibilityByTab[tkNorm][k] = prefs[k];
            $.ajax({
                url: 'ajax/save-product-modal-column-preferences.php',
                type: 'POST',
                data: {
                    page_name: STOCK_JOURNAL_MODAL_COLUMNS_PAGE,
                    tab_key: tkNorm,
                    preferences: JSON.stringify(prefs)
                },
                dataType: 'json'
            });
        }, 400);
    }
    
    if (!window.productModalColumnVisibilityByTab) window.productModalColumnVisibilityByTab = {};
    
    // Modal Table Column Visibility Toggle (tab-wise + persist per user)
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('#modalTableSettingsBtnPage, #modalTableSettingsBtnModal');
        if (btn) {
            e.stopPropagation();
            e.preventDefault();
            var wrapper = btn.closest('.table-settings-wrapper');
            var drop = wrapper && wrapper.querySelector('.sj-sj-column-dropdown, .table-settings-dropdown');
            if (drop) {
                var willShow = !drop.classList.contains('show');
                if (willShow) {
                    ensureStockJournalGroupCheckboxesInDropdown(drop);
                    sjSyncAllGroupCheckboxStatesEverywhere();
                    sjUpdateAllSubColumnDisabledStates(drop);
                    var si = drop.querySelector('.sj-modal-table-settings-search');
                    if (si) {
                        si.value = '';
                        si.dispatchEvent(new Event('input', { bubbles: true }));
                    }
                }
                drop.classList.toggle('show');
            }
            return;
        }
        if (!e.target.closest('.table-settings-btn') && !e.target.closest('.table-settings-dropdown')) {
            document.querySelectorAll('.table-settings-dropdown.show').forEach(function(d) { d.classList.remove('show'); });
        }
    });

    document.addEventListener('change', function(e) {
        var drop = e.target.closest('.sj-sj-column-dropdown');
        if (!drop || e.target.type !== 'checkbox') return;
        var columnGroups = window.PRODUCT_MODAL_COLUMN_GROUPS || {};
        var groupKey = e.target.getAttribute('data-group');
        var columnName = e.target.getAttribute('data-column');

        if (groupKey && !columnName) {
            var isVisible = e.target.checked;
            var cols = columnGroups[groupKey];
            if (cols && cols.length) {
                cols.forEach(function(c) {
                    sjSetProductColumnVisibleEverywhere(c, isVisible);
                });
                getStockJournalColumnDropdowns().forEach(function(d) {
                    sjSetSubColumnDisabledState(d, groupKey, !isVisible);
                });
                requestAnimationFrame(function() {
                    requestAnimationFrame(function() {
                        if (typeof window.syncProductModalColumnLayoutAfterToggle === 'function') {
                            window.syncProductModalColumnLayoutAfterToggle();
                        }
                    });
                });
                saveProductModalColumnPreferencesDebounced(currentMetalId || '');
            }
            return;
        }

        if (!columnName) return;
        var isVisibleCol = e.target.checked;
        var colGroup = null;
        Object.keys(columnGroups).forEach(function(gk) {
            if ((columnGroups[gk] || []).indexOf(columnName) !== -1) colGroup = gk;
        });
        if (isVisibleCol && colGroup) {
            var blocked = false;
            getStockJournalColumnDropdowns().forEach(function(d) {
                var groupCb = d.querySelector('.table-settings-groups-section input[data-group="' + colGroup + '"]');
                if (groupCb && !groupCb.checked) blocked = true;
            });
            if (blocked) {
                e.target.checked = false;
                sjSetProductColumnVisibleEverywhere(columnName, false);
                return;
            }
        }
        sjSetProductColumnVisibleEverywhere(columnName, isVisibleCol);
        if (colGroup) {
            getStockJournalColumnDropdowns().forEach(function(d) {
                sjSyncGroupCheckboxState(d, colGroup);
            });
        }
        getStockJournalColumnDropdowns().forEach(function(d) {
            sjUpdateAllSubColumnDisabledStates(d);
        });
        requestAnimationFrame(function() {
            requestAnimationFrame(function() {
                if (typeof window.syncProductModalColumnLayoutAfterToggle === 'function') {
                    window.syncProductModalColumnLayoutAfterToggle();
                }
            });
        });
        saveProductModalColumnPreferencesDebounced(currentMetalId || '');
    });

    document.addEventListener('input', function(e) {
        if (!e.target.classList || !e.target.classList.contains('sj-modal-table-settings-search')) return;
        var settingsDropdown = e.target.closest('.sj-sj-column-dropdown');
        if (!settingsDropdown) return;
        var searchTerm = (e.target.value || '').toLowerCase().trim();
        settingsDropdown.querySelectorAll('.table-settings-item').forEach(function(item) {
            var label = item.querySelector('label');
            if (label) {
                var labelText = label.textContent.toLowerCase();
                if (labelText.indexOf(searchTerm) !== -1) {
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
                var visible = block.querySelectorAll('.table-settings-item:not(.hidden)').length > 0;
                block.classList.toggle('hidden', !visible);
            });
        }
    });
    
    /** After a successful Add, keep product/weight/etc. but set barcode to the next serial so the next Add does not merge into the same line. */
    function sjRefreshProductSelectionBarcodeAfterAdd(row) {
        if (!row) return Promise.resolve();
        var barcodeInput = row.querySelector('[data-column="barcode"] input.barcode-input, [data-column="barcode"] input[type="text"]');
        if (!barcodeInput) return Promise.resolve();
        var prefix = row.getAttribute('data-barcode-prefix') || '';
        var digits = parseInt(row.getAttribute('data-barcode-digits'), 10) || 5;
        var attempts = 0;
        function assignNext() {
            return getNextBarcodeFromServer(prefix, digits).then(function(bc) {
                if (bc && typeof sjIsBarcodeOnProductList === 'function' && sjIsBarcodeOnProductList(bc) && attempts < 8) {
                    sjBarcodeRelease(bc);
                    attempts++;
                    return assignNext();
                }
                barcodeInput.value = bc;
                var esc = String(bc).replace(/'/g, "\\'");
                barcodeInput.setAttribute('onclick', "printBarcode('" + esc + "', event)");
                var printIcon = row.querySelector('.barcode-print-icon');
                if (printIcon) printIcon.setAttribute('onclick', "printBarcode('" + esc + "', event)");
            });
        }
        return assignNext().catch(function() {});
    }
    
    // Select product and add to table
    async function selectProduct(row, closeModal = false) {
        const productId = row.getAttribute('data-product-id');
        const characteristicId = row.getAttribute('data-characteristic-id');
        
        // Extract ALL calculated values directly from the modal row
        const getRaw = function(column) {
            const cell = row.querySelector(`[data-column="${column}"]`);
            if (!cell) return '';
            const input = cell.querySelector('input');
            const select = cell.querySelector('select');
            if (input) return String(input.value || '').trim();
            if (select) return String(select.value || '').trim();
            return String(cell.textContent || '').trim();
        };
        const getValue = function(column, isNumber = true) {
            const raw = getRaw(column);
            if (!isNumber) return raw;
            if (raw === '') return 0;
            return parseFloat(String(raw).replace(/,/g, '')) || 0;
        };
        
        // Extract barcode specifically - handle input and text display
        let barcode = '';
        const barcodeCell = row.querySelector('[data-column="barcode"]');
        if (barcodeCell) {
            const barcodeInput = barcodeCell.querySelector('input.barcode-input, input[type="text"]');
            if (barcodeInput && barcodeInput.value && barcodeInput.value.trim() !== '') {
                barcode = barcodeInput.value.trim();
            } else {
                // Try to get from text content (if displayed as text)
                let barcodeText = barcodeCell.textContent.trim();
                barcodeText = barcodeText.replace(/icon-image|icon-printer|Click to select product|No barcode/gi, '').trim();
                if (barcodeText && barcodeText !== '') {
                    barcode = barcodeText;
                }
            }
        }
        
        // Extract all values from modal row
        const modalRowData = {
            product_id: productId,
            characteristic_id: characteristicId,
            product_name: getValue('product', false),
            barcode: barcode,
            location_id: getValue('location'),
            quantity: (function () {
                var mq = getValue('metal-qty');
                var q = getValue('quantity');
                return (Math.abs(mq) > 1e-9) ? mq : q;
            })(),
            carat_id: getValue('carat'),
            pkt_wt: getValue('pkt-wt'),
            pkt_less_wt: getValue('pkt-less-wt'),
            gross_wt: (function () {
                var mRaw = getRaw('metal-weight');
                var gRaw = getRaw('gross-wt');
                var m = parseFloat(String(mRaw).replace(/,/g, '')) || 0;
                var g = parseFloat(String(gRaw).replace(/,/g, '')) || 0;
                if (m > 0) return mRaw;
                if (g > 0) return gRaw;
                return '0';
            })(),
            less_wt: getValue('less-wt'),
            purity: getValue('purity'),
            final_wt: getValue('final-wt'),
            net_wt: getValue('net-wt'),
            pure_wt: getValue('purity-wt'),
            rate: (function () {
                var r = getValue('rate');
                var mr = getValue('metal-rate');
                return (Math.abs(r) > 1e-9) ? r : mr;
            })(),
            metal_rate: getValue('metal-rate'),
            metal_value: getValue('metal-value'),
            purchase_rate: getValue('purchase-rate'),
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
            design_no: getValue('design-no', false),
            huid_no: getValue('huid', false),
            making_type: getValue('making-type', false) || 'Fix',
            making_rate: getValue('making-rate'),
            purchase_making_rate: row.getAttribute('data-purchase-making-rate') || '',
            making_actual_value: getValue('making-actual-value'),
            barcode_prefix: row.getAttribute('data-barcode-prefix') || '',
            barcode_digits: parseInt(row.getAttribute('data-barcode-digits'), 10) || 0,
            metal_id: row.getAttribute('data-metal-id') || '',
            metal_name: sjRowMetalDisplayName(row),
            voucher_type: (function() {
                var v = getValue('voucher-type', false);
                if (v && String(v).trim() !== '') return String(v).trim();
                if (typeof window.SJ_DEFAULT_VOUCHER_TYPE === 'string' && window.SJ_DEFAULT_VOUCHER_TYPE) return window.SJ_DEFAULT_VOUCHER_TYPE;
                return '';
            })()
        };
        // Imitation / typed amounts: fill amount & net from purchase/sale/reverse when metal calc left them at 0
        (function () {
            var p = parseFloat(modalRowData.purchase_amount) || 0;
            var s = parseFloat(modalRowData.sale_amount) || 0;
            var r = parseFloat(modalRowData.reverse) || 0;
            var a = parseFloat(modalRowData.amount) || 0;
            var n = parseFloat(modalRowData.net_amt) || 0;
            var fallback = p > 0 ? p : (s > 0 ? s : r);
            if (a <= 0 && fallback > 0) modalRowData.amount = fallback;
            if (n <= 0 && fallback > 0) modalRowData.net_amt = fallback;
            if ((parseFloat(modalRowData.net_amt_tax) || 0) <= 0) {
                modalRowData.net_amt_tax = (parseFloat(modalRowData.net_amt) || 0) + (parseFloat(modalRowData.tax) || 0);
            }
            if (p <= 0 && (parseFloat(modalRowData.net_amt) || 0) > 0) {
                modalRowData.purchase_amount = modalRowData.net_amt;
            }
            if (s <= 0 && r > 0) modalRowData.sale_amount = r;
            if ((parseFloat(modalRowData.sale_amount_with) || 0) <= 0 && (parseFloat(modalRowData.sale_amount) || 0) > 0) {
                modalRowData.sale_amount_with = (parseFloat(modalRowData.sale_amount) || 0) + (parseFloat(modalRowData.tax) || 0);
            }
        })();
        modalRowData._imageFiles = (row._modalImageFiles || []).slice();
        (function () {
            var gw = modalRowData.gross_wt;
            var less = parseFloat(modalRowData.less_wt) || 0;
            if (gw != null && String(gw).trim() !== '' && (parseFloat(gw) || 0) > 0 && less === 0) {
                modalRowData.net_wt = gw;
                modalRowData.final_wt = gw;
                var pur = parseFloat(modalRowData.purity) || 0;
                if (pur > 1) pur = pur / 100;
                if (pur === 1 || pur === 0) {
                    modalRowData.pure_wt = gw;
                }
            }
        })();
        
        console.log('Extracted modal row data:', modalRowData);
        var _sjFromLet = (typeof currentEditingRowId !== 'undefined' && currentEditingRowId) ? String(currentEditingRowId).trim() : '';
        var _sjFromWin = (typeof window !== 'undefined' && window.currentEditingRowId) ? String(window.currentEditingRowId).trim() : '';
        var _sjFromAttr = (row && row.getAttribute) ? String(row.getAttribute('data-staging-edit-target-row-id') || '').trim() : '';
        var _sjEditId = _sjFromLet || _sjFromWin || _sjFromAttr || null;
        if (_sjEditId) {
            currentEditingRowId = _sjEditId;
            if (typeof window !== 'undefined') window.currentEditingRowId = _sjEditId;
        }
        
                    if (_sjEditId) {
            // Update existing row with modal data
                        console.log('Updating row:', _sjEditId);
            await updateProductListRowFromModalRow(_sjEditId, row);
            currentEditingRowId = null;
            if (typeof window !== 'undefined') window.currentEditingRowId = null;
            if (row && row.removeAttribute) try { row.removeAttribute('data-staging-edit-target-row-id'); } catch (e) {}
                    } else {
            // Add new row with modal data (serialized so rapid ADD never reuses the same barcode)
            console.log('Adding new row from modal data');
            var addOk = false;
            await sjRunWithAddLock(async function() {
                var bcCell = row.querySelector('[data-column="barcode"]');
                if (bcCell) {
                    var bcInp = bcCell.querySelector('input.barcode-input, input[type="text"]');
                    if (bcInp && bcInp.value && String(bcInp.value).trim() !== '') {
                        modalRowData.barcode = String(bcInp.value).trim();
                    }
                }
                addOk = await addProductToTableFromModalRow(modalRowData);
                if (addOk) {
                    await sjRefreshProductSelectionBarcodeAfterAdd(row);
                }
            });
                    }
        row._modalImageFiles = [];
        const imgCell = row.querySelector('[data-column="images"]');
        if (imgCell) {
            const inp = imgCell.querySelector('.sj-image-input');
            if (inp) inp.value = '';
            renderStockJournalPreviews(imgCell, []);
        }
        if (closeModal) hideProductModal();
        updateSummaryPanel();
    }
    
    function findProductListRowByBarcode(tbody, barcode) {
        if (!tbody || !barcode) return null;
        var b = String(barcode).trim();
        if (!b) return null;
        var rows = tbody.querySelectorAll('tr');
        for (var i = 0; i < rows.length; i++) {
            var tr = rows[i];
            if (tr.classList.contains('no-drag') || tr.id === 'summaryRow' || tr.id === 'grandTotalRow') continue;
            if (!tr.id || tr.id.indexOf('product-row-') !== 0) continue;
            var db = (tr.getAttribute('data-barcode') || '').trim();
            if (db && db === b) return tr;
            var bc = tr.querySelector('[data-column="barcode"]');
            if (bc) {
                var t = (bc.textContent || '').replace(/icon-image|icon-printer|Click to select product|No barcode/gi, '').trim();
                if (t === b) return tr;
            }
        }
        return null;
    }
    
    /**
     * Merge modal line into an existing Product List row that already has the same barcode (single line, no new serial).
     */
    async function mergeProductListRowWithModalData(existingRow, modalRowData) {
        function num(v) { return parseFloat(v) || 0; }
        function getInp(field) {
            var el = existingRow.querySelector('[data-field="' + field + '"]');
            return el ? num(el.value) : 0;
        }
        function setInp(field, val, decimals) {
            var el = existingRow.querySelector('[data-field="' + field + '"]');
            if (!el) return;
            if (field === 'gross_wt' || field === 'less_wt' || field === 'final_wt') {
                el.value = sjFormatWeight(val);
            } else {
                el.value = parseFloat(val).toFixed(decimals);
            }
        }
        var oldQty = getInp('quantity');
        var addQty = num(modalRowData.quantity);
        var oldGross = getInp('gross_wt');
        var addGross = sjRoundWeight3(modalRowData.gross_wt);
        if (typeof stockDetailItem !== 'undefined' && stockDetailItem) {
            var balance = getBalanceQtyAndWeight(existingRow);
            if (balance) {
                var newQty = oldQty + addQty;
                var newGross = oldGross + addGross;
                if (newQty > balance.balanceQty + SJ_BALANCE_QTY_EPS) {
                    alert('Cannot add: Quantity ' + newQty.toFixed(2) + ' exceeds balance quantity (' + balance.balanceQty.toFixed(2) + '). Total qty: ' + balance.totalQty.toFixed(2) + '. You cannot add more than the balance.');
                    return false;
                }
                if (newGross > balance.balanceGrossWt + SJ_BALANCE_WT_EPS) {
                    alert('Cannot add: Gross weight ' + sjFormatWeight(newGross) + ' exceeds balance gross weight (' + sjFormatWeight(balance.balanceGrossWt) + '). Total gross wt: ' + sjFormatWeight(balance.totalGrossWt) + '. You cannot add more than the balance.');
                    return false;
                }
            }
        }
        var oldPur = getInp('purity');
        var addPur = num(modalRowData.purity);
        var sumQty = oldQty + addQty;
        var wPurity = sumQty > 0 ? (oldQty * oldPur + addQty * addPur) / sumQty : addPur;
        setInp('quantity', sumQty, 2);
        setInp('gross_wt', oldGross + addGross, 6);
        setInp('less_wt', getInp('less_wt') + num(modalRowData.less_wt), 3);
        setInp('final_wt', getInp('final_wt') + num(modalRowData.final_wt), 3);
        setInp('purity', wPurity, 2);
        setInp('making', getInp('making') + num(modalRowData.making_amount), 2);
        setInp('tax', getInp('tax') + num(modalRowData.tax), 2);
        setInp('stone_charges', getInp('stone_charges') + num(modalRowData.stone_amount), 2);
        setInp('other_charges', getInp('other_charges') + num(modalRowData.other_amount), 2);
        setInp('diamond_value', getInp('diamond_value') + num(modalRowData.diamond_amount), 2);
        setInp('gemstone_value', getInp('gemstone_value') + num(modalRowData.gemstone_value || 0), 2);
        if (modalRowData.huid_no) {
            var huidInp = existingRow.querySelector('[data-field="huid_no"]');
            if (huidInp) huidInp.value = modalRowData.huid_no;
        }
        var pktWtCell = existingRow.querySelector('[data-column="pkt-wt"]');
        if (pktWtCell) pktWtCell.textContent = (num(pktWtCell.textContent) + num(modalRowData.pkt_wt)).toFixed(3);
        var pktLessCell = existingRow.querySelector('[data-column="pkt-less-wt"]');
        if (pktLessCell) pktLessCell.textContent = (num(pktLessCell.textContent) + num(modalRowData.pkt_less_wt)).toFixed(3);
        function sumTextColumn(col, addVal) {
            var td = existingRow.querySelector('[data-column="' + col + '"]');
            if (!td || td.querySelector('input')) return;
            td.textContent = (num(td.textContent) + addVal).toFixed(2);
        }
        sumTextColumn('discount', num(modalRowData.discount));
        sumTextColumn('reverse', num(modalRowData.reverse));
        if (modalRowData.metal_id) existingRow.setAttribute('data-metal-id', modalRowData.metal_id);
        var mmn = (modalRowData.metal_name && String(modalRowData.metal_name).trim()) ? String(modalRowData.metal_name).trim() : '';
        if (mmn) existingRow.setAttribute('data-metal-name', mmn);
        var rowId = existingRow.id;
        window.stockJournalRowImages = window.stockJournalRowImages || {};
        var prevImgs = window.stockJournalRowImages[rowId] || [];
        var addImgs = (modalRowData._imageFiles || []).slice();
        window.stockJournalRowImages[rowId] = prevImgs.concat(addImgs);
        var imagesCell = existingRow.querySelector('[data-column="images"]');
        if (imagesCell && window.stockJournalRowImages[rowId].length) {
            renderStockJournalPreviews(imagesCell, window.stockJournalRowImages[rowId]);
        }
        // Merge stored line amounts (add new modal amounts onto existing preserved values)
        (function () {
            var prevP = parseFloat(existingRow.getAttribute('data-sj-purchase-amount')) || 0;
            var prevS = parseFloat(existingRow.getAttribute('data-sj-sale-amount')) || 0;
            var prevSw = parseFloat(existingRow.getAttribute('data-sj-sale-amount-with')) || 0;
            var prevA = parseFloat(existingRow.getAttribute('data-sj-amount')) || 0;
            var prevN = parseFloat(existingRow.getAttribute('data-sj-net-amt')) || 0;
            var prevNt = parseFloat(existingRow.getAttribute('data-sj-net-amt-tax')) || 0;
            var prevR = parseFloat(existingRow.getAttribute('data-sj-reverse')) || 0;
            if (typeof sjStoreProductListLineAmounts === 'function') {
                sjStoreProductListLineAmounts(existingRow, {
                    purchase_amount: prevP + (parseFloat(modalRowData.purchase_amount) || 0),
                    sale_amount: prevS + (parseFloat(modalRowData.sale_amount) || 0),
                    sale_amount_with: prevSw + (parseFloat(modalRowData.sale_amount_with) || 0),
                    amount: prevA + (parseFloat(modalRowData.amount) || 0),
                    net_amt: prevN + (parseFloat(modalRowData.net_amt) || 0),
                    net_amt_tax: prevNt + (parseFloat(modalRowData.net_amt_tax) || 0),
                    reverse: prevR + (parseFloat(modalRowData.reverse) || 0),
                    tax: parseFloat(modalRowData.tax) || 0
                });
            }
        })();
        calculateRowAmounts(existingRow);
        updateBalance();
        return true;
    }
    
    // Add product to table from modal row data (with all calculated values)
    async function addProductToTableFromModalRow(modalRowData) {
        if (window.STOCK_JOURNAL_EDIT_MODE) {
            alert('Add is disabled in edit mode. You can only update existing records.');
            return false;
        }
        console.log('addProductToTableFromModalRow called with data:', modalRowData);
        
        const tbody = document.getElementById('productTableBody');
        if (!tbody) {
            console.error('productTableBody not found');
            return false;
        }
        
        var mergeBarcode = (modalRowData.barcode && String(modalRowData.barcode).trim() !== '') ? String(modalRowData.barcode).trim() : '';
        var freshListBarcodePolicy = typeof sjEachAddNeedsFreshListBarcode === 'function' && sjEachAddNeedsFreshListBarcode();
        var newLinePOpeningBarcode = typeof sjProductOpeningNewLineBarcodePolicy === 'function' && sjProductOpeningNewLineBarcodePolicy();
        var skipBarcodeMerge = freshListBarcodePolicy || newLinePOpeningBarcode;
        if (!skipBarcodeMerge && mergeBarcode) {
            var mergeTarget = findProductListRowByBarcode(tbody, mergeBarcode);
            if (mergeTarget) {
                var emptyForMerge = tbody.querySelector('.no-drag');
                if (emptyForMerge) emptyForMerge.remove();
                var mergedOk = await mergeProductListRowWithModalData(mergeTarget, modalRowData);
                if (!mergedOk) return false;
                sjUpdateMetalTabsLockFromProductList();
                updateSummaryRow();
                updateSummaryPanel();
                return true;
            }
        }
        
        // Restrict: cannot add more than balance qty / balance gross weight (when item_id is set)
        if (typeof stockDetailItem !== 'undefined' && stockDetailItem) {
            const balance = getBalanceQtyAndWeight(null);
            if (balance) {
                const addQty = parseFloat(modalRowData.quantity || 0) || 0;
                const addWt = sjRoundWeight3(modalRowData.gross_wt || 0);
                if (addQty > balance.balanceQty + SJ_BALANCE_QTY_EPS) {
                    alert('Cannot add: Quantity ' + addQty.toFixed(2) + ' exceeds balance quantity (' + balance.balanceQty.toFixed(2) + '). Total qty: ' + balance.totalQty.toFixed(2) + '. You cannot add more than the balance.');
                    return false;
                }
                if (addWt > balance.balanceGrossWt + SJ_BALANCE_WT_EPS) {
                    alert('Cannot add: Gross weight ' + sjFormatWeight(addWt) + ' exceeds balance gross weight (' + sjFormatWeight(balance.balanceGrossWt) + '). Total gross wt: ' + sjFormatWeight(balance.totalGrossWt) + '. You cannot add more than the balance.');
                    return false;
                }
            }
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
        row.setAttribute('data-purchase-rate', parseFloat(modalRowData.purchase_rate != null && modalRowData.purchase_rate !== '' ? modalRowData.purchase_rate : (modalRowData.metal_rate || modalRowData.rate || 0)));
        row.setAttribute('data-location-id', modalRowData.location_id || '');
        row.setAttribute('data-carat-id', modalRowData.carat_id || '');
        row.setAttribute('data-making-type', modalRowData.making_type || 'Fix');
        row.setAttribute('data-making-rate', modalRowData.making_rate != null && modalRowData.making_rate !== '' ? String(modalRowData.making_rate) : '0');
        (function () {
            var pmr = parseFloat(modalRowData.purchase_making_rate);
            if (!isNaN(pmr) && pmr > 0) {
                row.setAttribute('data-purchase-making-rate', String(pmr));
            }
        })();
        row.setAttribute('data-making-discount-amt', modalRowData.making_discount_amt != null && modalRowData.making_discount_amt !== '' ? String(modalRowData.making_discount_amt) : '0');
        row.setAttribute('data-metal-id', modalRowData.metal_id || '');
        row.setAttribute('data-metal-name', (modalRowData.metal_name || '').trim());
        if (modalRowData.from_excel) {
            row.setAttribute('data-sj-excel-import', '1');
        }
        if ((modalRowData.barcode_prefix || '').trim() !== '') {
            row.setAttribute('data-barcode-prefix', String(modalRowData.barcode_prefix).trim());
        }
        var _mbd = parseInt(modalRowData.barcode_digits, 10);
        if (_mbd > 0) {
            row.setAttribute('data-barcode-digits', String(_mbd));
        }
        var reservedListBarcode = '';
        try {
            // Product opening / purchase invoice: always allocate a unique serial for each new list line.
            let barcode = '';
            var excelBarcode = (modalRowData.from_excel && modalRowData.barcode && String(modalRowData.barcode).trim() !== '')
                ? String(modalRowData.barcode).trim() : '';
            if (excelBarcode) {
                barcode = excelBarcode;
                sjBarcodeReserve(barcode);
                reservedListBarcode = barcode;
            } else {
                try {
                    barcode = await sjEnsureUniqueListBarcode(modalRowData.barcode, modalRowData);
                    reservedListBarcode = barcode;
                } catch (err) {
                    console.error('sjEnsureUniqueListBarcode failed:', err);
                    alert('Could not generate barcode. ' + (err.message || ''));
                    return false;
                }
                if (barcode) {
                    var prFix = sjParseBarcodeStringPrefixDigits(barcode);
                    if (prFix.prefix) {
                        row.setAttribute('data-barcode-prefix', prFix.prefix);
                        if (prFix.digits > 0) row.setAttribute('data-barcode-digits', String(prFix.digits));
                    }
                }
            }
            if (barcode && (typeof row.getAttribute === 'function') && !((row.getAttribute('data-barcode-prefix') || '').trim())) {
                var prReuse = sjParseBarcodeStringPrefixDigits(barcode);
                if (prReuse.prefix) {
                    row.setAttribute('data-barcode-prefix', prReuse.prefix);
                    if (prReuse.digits > 0) row.setAttribute('data-barcode-digits', String(prReuse.digits));
                }
            }
            
            // Store barcode in row data attribute for easy retrieval
            row.setAttribute('data-barcode', barcode);
            
            row.innerHTML = `
                <td data-column="print-barcode" style="text-align: center; width: 50px;">
                    <i class="feather icon-printer" style="cursor: pointer; font-size: 0.9rem; color: #c5a864;" onclick="printBarcodeFromRow(this)" title="Print Barcode"></i>
                </td>
                <td data-column="photo" class="sj-photo-cell" style="text-align: center; vertical-align: middle; width: 56px;">
                    <div class="sj-photo-first-wrap" style="width: 48px; height: 48px; margin: 0 auto; border-radius: 6px; overflow: hidden; background: #f1f5f9; display: flex; align-items: center; justify-content: center;"></div>
                </td>
                <td data-column="barcode" style="text-align: center; color: #11294b; font-weight: 600; cursor: pointer;" onclick="printBarcodeFromRow(this)" title="Click to print barcode">
                    ${escapeHtml(barcode)}
                </td>
                <td data-column="description" class="product-select-cell" style="cursor: pointer; color: #11294b; position: relative;">
                    <a href="javascript:void(0)" style="color: #11294b; text-decoration: underline;">${escapeHtml(modalRowData.product_name || '')}</a>
                </td>
                <td data-column="location" style="text-align: center; color: #11294b;">${escapeHtml(typeof locations !== 'undefined' && modalRowData.location_id && (locations.find(function(l){ return l.id == modalRowData.location_id || l.id == String(modalRowData.location_id); }) || {}).name || modalRowData.location_id || '')}</td>
                <td data-column="quantity" style="text-align: right;">
                    <input type="text" class="form-control form-control-sm editable-field" data-field="quantity" value="${parseFloat(modalRowData.quantity || 1).toFixed(2)}" step="0.01" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">
                </td>
                <td data-column="carat" style="text-align: center; color: #11294b;">${escapeHtml(sjCaratDisplayName(modalRowData.carat_id || modalRowData.karat || ''))}</td>
                <td data-column="pkt-wt" style="text-align: right; color: #11294b;">${parseFloat(modalRowData.pkt_wt || 0).toFixed(3)}</td>
                <td data-column="pkt-less-wt" style="text-align: right; color: #11294b;">${parseFloat(modalRowData.pkt_less_wt || 0).toFixed(3)}</td>
                <td data-column="gross-wt" style="text-align: right;">
                    <input type="text" class="form-control form-control-sm editable-field" data-field="gross_wt" value="${sjFormatWeight(modalRowData.gross_wt || 0)}" step="0.000001" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 110px;">
                </td>
                <td data-column="less-wt" style="text-align: right;">
                    <input type="text" class="form-control form-control-sm editable-field" data-field="less_wt" value="${sjFormatWeight(modalRowData.less_wt || 0)}" step="0.000001" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">
                </td>
                <td data-column="purity" style="text-align: right;">
                    <input type="text" class="form-control form-control-sm editable-field" data-field="purity" value="${parseFloat(modalRowData.purity || 0).toFixed(2)}" step="0.01" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">
                </td>
                <td data-column="final-wt" style="text-align: right;">
                    <input type="text" class="form-control form-control-sm editable-field" data-field="final_wt" value="${sjFormatWeight(modalRowData.final_wt || modalRowData.gross_wt || 0)}" step="0.000001" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 110px;">
                </td>
                <td data-column="net-wt" style="text-align: right; color: #11294b;">${sjFormatWeight(modalRowData.net_wt || modalRowData.gross_wt || 0)}</td>
                <td data-column="pure-wt" style="text-align: right; color: #11294b;">${sjFormatWeight(modalRowData.pure_wt || modalRowData.gross_wt || 0)}</td>
                <td data-column="making" style="text-align: right;">
                    <input type="text" class="form-control form-control-sm editable-field" data-field="making" value="${parseFloat(modalRowData.making_amount || 0).toFixed(2)}" step="0.01" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">
                </td>
                <td data-column="design-no" style="text-align: right;">
                    <input type="text" class="form-control form-control-sm editable-field" data-field="design_no" value="${escapeHtml(modalRowData.design_no || '')}" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">
                </td>
                <td data-column="huid" style="text-align: center;">
                    <input type="text" class="form-control form-control-sm editable-field" data-field="huid_no" value="${escapeHtml(modalRowData.huid_no || '')}" style="text-align: center; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 100px;">
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
                <td data-column="images" class="stock-journal-images-cell" style="vertical-align: middle;">
                    <div class="sj-images-wrap">
                        <input type="file" class="sj-image-input" accept=".jpg,.jpeg,.png,.webp" multiple style="display:none;">
                        <button type="button" class="btn btn-sm btn-outline-secondary sj-image-btn" style="font-size:0.7rem; padding:2px 6px; white-space:nowrap;" title="Add images (jpg, png, webp, max 2MB)"><i class="feather icon-upload" style="vertical-align:middle;"></i> Add</button>
                        <div class="sj-image-previews"></div>
                    </div>
                </td>
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
            console.log('Row added to table from modal:', rowId);
            if (modalRowData.extra_fields && typeof modalRowData.extra_fields === 'object' && Object.keys(modalRowData.extra_fields).length) {
                try {
                    row.setAttribute('data-sj-extra-fields', JSON.stringify(modalRowData.extra_fields));
                } catch (e) {}
            }
            
            // Images: copy from modal row and show first image in Photo column
            window.stockJournalRowImages = window.stockJournalRowImages || {};
            var imageFiles = (modalRowData._imageFiles || []).slice();
            var excelPaths = modalRowData.excelTempImagePaths;
            if (Array.isArray(excelPaths) && excelPaths.length) {
                row.setAttribute('data-sj-temp-image-paths', JSON.stringify(excelPaths));
                var forPrev = excelPaths.map(function (rel) {
                    var s = (rel == null) ? '' : String(rel).replace(/^\s+|\s+$/g, '').replace(/^\/+/, '');
                    return s ? { url: s } : null;
                }).filter(Boolean);
                if (forPrev.length) {
                    imageFiles = forPrev;
                }
            }
            window.stockJournalRowImages[rowId] = imageFiles;
            var imagesCell = row.querySelector('[data-column="images"]');
            if (imagesCell) {
                initStockJournalImageCell(imagesCell);
                if (imageFiles.length) renderStockJournalPreviews(imagesCell, imageFiles);
            }
            
            // Add event listeners for calculations
            addRowCalculationListeners(row);
            if (typeof sjStoreProductListLineAmounts === 'function') {
                sjStoreProductListLineAmounts(row, modalRowData);
            }
            calculateRowAmounts(row);
            if (typeof sjWriteProductListAmountCells === 'function' && typeof sjReadProductListLineAmounts === 'function') {
                var storedAmts = sjReadProductListLineAmounts(row);
                if (storedAmts) sjWriteProductListAmountCells(row, storedAmts);
            }
            
            // Update summary
            updateSummaryRow();
            updateSummaryPanel();
            // Update balance
            updateBalance();
            sjUpdateMetalTabsLockFromProductList();
            return true;
        } catch (error) {
            if (reservedListBarcode) sjBarcodeRelease(reservedListBarcode);
            console.error('Error adding product to table from modal:', error);
            alert('Error adding product: ' + error.message);
            return false;
        }
    }
    
    /** @returns {{ ok: boolean, message: string }} */
    function sjValidateProductOpeningTableBalanceForSave() {
        if (typeof stockDetailItem === 'undefined' || !stockDetailItem) {
            return { ok: true, message: '' };
        }
        var tq = parseFloat(stockDetailItem.total_quantity || 0) || 0;
        var twt = parseFloat(stockDetailItem.total_gross_weight || 0) || 0;
        var exq = parseFloat(stockDetailItem.existing_used_quantity || 0) || 0;
        var exw = parseFloat(stockDetailItem.existing_used_gross_weight || 0) || 0;
        var tbody = document.getElementById('productTableBody');
        var sq = 0, sw = 0;
        if (tbody) {
            tbody.querySelectorAll('tr[id^="product-row-"]').forEach(function(row) {
                var qi = row.querySelector('[data-field="quantity"]');
                if (qi && qi.value) {
                    sq += parseFloat(qi.value) || 0;
                }
                var gi = row.querySelector('[data-field="gross_wt"]');
                if (gi && gi.value) {
                    sw += parseFloat(gi.value) || 0;
                }
            });
        }
        if (tq > 0 && exq + sq > tq + 0.0001) {
            return { ok: false, message: 'Total quantity in the Product List exceeds the opening limit for this product. Reduce quantity or remove lines, then save again.' };
        }
        if (twt > 0 && exw + sw > twt + 0.0001) {
            return { ok: false, message: 'Total gross weight in the Product List exceeds the opening limit for this product. Reduce weight or remove lines, then save again.' };
        }
        return { ok: true, message: '' };
    }

    // Get current balance qty and balance gross weight (for item_id or product_opening context). Returns null if no stock detail.
    function getBalanceQtyAndWeight(excludeRow) {
        if (typeof stockDetailItem === 'undefined' || !stockDetailItem) {
            return null;
        }
        const totalQty = parseFloat(stockDetailItem.total_quantity || 0);
        const totalGrossWt = parseFloat(stockDetailItem.total_gross_weight || 0);
        const existingUsedQty = parseFloat(stockDetailItem.existing_used_quantity || 0);
        const existingUsedGrossWt = parseFloat(stockDetailItem.existing_used_gross_weight || 0);
        const tbody = document.getElementById('productTableBody');
        let currentSessionQty = 0;
        let currentSessionGrossWt = 0;
        if (tbody) {
            tbody.querySelectorAll('tr').forEach(function(row) {
                if (row.classList.contains('no-drag') || row.id === 'summaryRow' || row.id === 'grandTotalRow' || !row.id || !row.id.startsWith('product-row-')) return;
                if (excludeRow && row === excludeRow) return;
                let qty = 0, grossWt = 0;
                const qtyInput = row.querySelector('[data-field="quantity"]');
                if (qtyInput && qtyInput.value) qty = parseFloat(qtyInput.value) || 0;
                const grossWtInput = row.querySelector('[data-field="gross_wt"]');
                if (grossWtInput && grossWtInput.value) grossWt = parseFloat(grossWtInput.value) || 0;
                currentSessionQty += qty;
                currentSessionGrossWt += grossWt;
            });
        }
        const usedQty = existingUsedQty + currentSessionQty;
        const usedGrossWt = existingUsedGrossWt + currentSessionGrossWt;
        return {
            balanceQty: Math.max(0, totalQty - usedQty),
            balanceGrossWt: Math.max(0, totalGrossWt - usedGrossWt),
            totalQty: totalQty,
            totalGrossWt: totalGrossWt
        };
    }

    // Calculate and update quantity and gross weight balance (for purchase invoice or product opening)
    function updateBalance() {
        if (typeof stockDetailItem === 'undefined' || !stockDetailItem) {
            return; // No balance tracking if no stock detail (no item_id and no product_opening)
        }
        
        const totalQty = parseFloat(stockDetailItem.total_quantity || 0);
        const totalGrossWt = parseFloat(stockDetailItem.total_gross_weight || 0);
        
        // Get existing used amounts from database (already saved stock journal items)
        const existingUsedQty = parseFloat(stockDetailItem.existing_used_quantity || 0);
        const existingUsedGrossWt = parseFloat(stockDetailItem.existing_used_gross_weight || 0);
        
        // Calculate new/current qty and gross weight from Product List table (productTableBody)
        // This represents items being added in the current session
        const tbody = document.getElementById('productTableBody');
        let currentSessionQty = 0;
        let currentSessionGrossWt = 0;
        let barcodeCount = 0;
        
        if (tbody) {
            // Get all product rows (exclude empty row and footer)
            const allRows = tbody.querySelectorAll('tr');
            
            allRows.forEach(function(row) {
                // Skip empty row and footer rows
                if (row.classList.contains('no-drag') || row.id === 'summaryRow' || row.id === 'grandTotalRow') {
                    return;
                }
                
                // Skip if row doesn't have product data
                if (!row.id || !row.id.startsWith('product-row-')) {
                    return;
                }
                
                // Get quantity - try data-field="quantity" first (editable input), then data-column
                let qty = 0;
                const qtyInput = row.querySelector('[data-field="quantity"]');
                if (qtyInput && qtyInput.value) {
                    qty = parseFloat(qtyInput.value) || 0;
                } else {
                    const qtyCell = row.querySelector('[data-column="quantity"]');
                    if (qtyCell) {
                        const qtyInput2 = qtyCell.querySelector('input');
                        if (qtyInput2 && qtyInput2.value) {
                            qty = parseFloat(qtyInput2.value) || 0;
                        } else {
                            const qtyText = qtyCell.textContent.trim();
                            qty = parseFloat(qtyText) || 0;
                        }
                    }
                }
                currentSessionQty += qty;
                
                // Get gross weight - try data-field="gross_wt" first, then data-column
                let grossWt = 0;
                const grossWtInput = row.querySelector('[data-field="gross_wt"]');
                if (grossWtInput && grossWtInput.value) {
                    grossWt = parseFloat(grossWtInput.value) || 0;
                } else {
                    const grossWtCell = row.querySelector('[data-column="gross-wt"]');
                    if (grossWtCell) {
                        const grossWtInput2 = grossWtCell.querySelector('input');
                        if (grossWtInput2 && grossWtInput2.value) {
                            grossWt = parseFloat(grossWtInput2.value) || 0;
                        } else {
                            const grossWtText = grossWtCell.textContent.trim();
                            grossWt = parseFloat(grossWtText) || 0;
                        }
                    }
                }
                currentSessionGrossWt += grossWt;
                
                // Count barcodes
                const barcodeCell = row.querySelector('[data-column="barcode"]');
                if (barcodeCell) {
                    const barcode = barcodeCell.textContent.trim();
                    if (barcode && barcode !== '' && barcode !== 'Click to select product') {
                        barcodeCount++;
                    }
                }
            });
        }
        
        // Total used = existing (from database) + current session (being added now)
        const usedQty = existingUsedQty + currentSessionQty;
        const usedGrossWt = existingUsedGrossWt + currentSessionGrossWt;
        
        // Calculate balance (Balance = Total - Total Used)
        const balanceQty = Math.max(0, totalQty - usedQty);
        const balanceGrossWt = Math.max(0, totalGrossWt - usedGrossWt);
        
        // Update display
        const totalQtyDisplay = document.getElementById('totalQuantityDisplay');
        const usedQtyDisplay = document.getElementById('usedQuantityDisplay');
        const balanceQtyDisplay = document.getElementById('balanceQuantityDisplay');
        const totalGrossWtDisplay = document.getElementById('totalGrossWeightDisplay');
        const usedGrossWtDisplay = document.getElementById('usedGrossWeightDisplay');
        const balanceGrossWtDisplay = document.getElementById('balanceGrossWeightDisplay');
        const totalBarcodesDisplay = document.getElementById('totalBarcodesDisplay');
        
        // Ensure values are numbers (not NaN)
        const safeUsedQty = isNaN(usedQty) ? 0 : usedQty;
        const safeBalanceQty = isNaN(balanceQty) ? totalQty : balanceQty;
        const safeUsedGrossWt = isNaN(usedGrossWt) ? 0 : usedGrossWt;
        const safeBalanceGrossWt = isNaN(balanceGrossWt) ? totalGrossWt : balanceGrossWt;
        
        if (totalQtyDisplay) totalQtyDisplay.textContent = totalQty.toFixed(2);
        if (usedQtyDisplay) {
            usedQtyDisplay.textContent = safeUsedQty.toFixed(2);
            // Change color if over limit
            if (safeUsedQty > totalQty) {
                usedQtyDisplay.style.color = '#ef4444';
            } else {
                usedQtyDisplay.style.color = '#fff';
            }
        }
        if (balanceQtyDisplay) {
            balanceQtyDisplay.textContent = safeBalanceQty.toFixed(2);
            // Change color if balance is low or negative
            if (safeBalanceQty < 0) {
                balanceQtyDisplay.style.color = '#ef4444';
            } else if (safeBalanceQty < (totalQty * 0.1)) {
                balanceQtyDisplay.style.color = '#f59e0b';
            } else {
                balanceQtyDisplay.style.color = '#10b981';
            }
        }
        
        if (totalGrossWtDisplay) totalGrossWtDisplay.textContent = sjFormatWeight(totalGrossWt);
        if (usedGrossWtDisplay) {
            usedGrossWtDisplay.textContent = sjFormatWeight(safeUsedGrossWt);
            // Change color if over limit
            if (safeUsedGrossWt > totalGrossWt) {
                usedGrossWtDisplay.style.color = '#ef4444';
            } else {
                usedGrossWtDisplay.style.color = '#fff';
            }
        }
        if (balanceGrossWtDisplay) {
            balanceGrossWtDisplay.textContent = sjFormatWeight(safeBalanceGrossWt);
            // Change color if balance is low or negative
            if (safeBalanceGrossWt < 0) {
                balanceGrossWtDisplay.style.color = '#ef4444';
            } else if (safeBalanceGrossWt < (totalGrossWt * 0.1)) {
                balanceGrossWtDisplay.style.color = '#f59e0b';
            } else {
                balanceGrossWtDisplay.style.color = '#10b981';
            }
        }
        
        if (totalBarcodesDisplay) totalBarcodesDisplay.textContent = barcodeCount;
        
        console.log('Balance updated - Total Qty:', totalQty, 'Production/Used Qty:', safeUsedQty, 'Balance Qty:', safeBalanceQty);
        console.log('Balance updated - Total Gross Wt:', totalGrossWt, 'Used Gross Wt:', safeUsedGrossWt, 'Balance Gross Wt:', safeBalanceGrossWt);
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
        row.setAttribute('data-metal-id', product.metal_id || currentMetalId || '');
        row.setAttribute('data-metal-name', (product.metal_name || currentMetalName || '').trim());
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
                <td data-column="photo" class="sj-photo-cell" style="text-align: center; vertical-align: middle; width: 56px;">
                    <div class="sj-photo-first-wrap" style="width: 48px; height: 48px; margin: 0 auto; border-radius: 6px; overflow: hidden; background: #f1f5f9; display: flex; align-items: center; justify-content: center;"></div>
                </td>
                <td data-column="barcode" style="text-align: center;">
                    <div class="image-placeholder" style="width: 30px; height: 30px; background: #f1f5f9; border-radius: 4px; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                        <i class="feather icon-image" style="font-size: 0.9rem; color: #94a3b8;"></i>
                    </div>
                </td>
                <td data-column="description" class="product-select-cell" style="cursor: pointer; color: #11294b; position: relative;">
                    <a href="javascript:void(0)" style="color: #11294b; text-decoration: underline;">${escapeHtml(product.name || '')}</a>
                </td>
                <td data-column="location" style="text-align: center; color: #11294b;"></td>
                <td data-column="quantity" style="text-align: right;">
                    <input type="text" class="form-control form-control-sm editable-field" data-field="quantity" value="${quantity.toFixed(2)}" step="0.01" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">
                </td>
                <td data-column="carat" style="text-align: center; color: #11294b;"></td>
                <td data-column="pkt-wt" style="text-align: right; color: #11294b;">0.000</td>
                <td data-column="pkt-less-wt" style="text-align: right; color: #11294b;">0.000</td>
                <td data-column="gross-wt" style="text-align: right;">
                    <input type="text" class="form-control form-control-sm editable-field" data-field="gross_wt" value="${sjFormatWeight(grossWt)}" step="0.000001" style="text-align: right; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 80px;">
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
                <td data-column="huid" style="text-align: center;">
                    <input type="text" class="form-control form-control-sm editable-field" data-field="huid_no" value="" style="text-align: center; border: none; background: transparent; padding: 0.25rem; color: #11294b; width: 100px;">
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
                <td data-column="images" class="stock-journal-images-cell" style="vertical-align: middle;">
                    <div class="sj-images-wrap">
                        <input type="file" class="sj-image-input" accept=".jpg,.jpeg,.png,.webp" multiple style="display:none;">
                        <button type="button" class="btn btn-sm btn-outline-secondary sj-image-btn" style="font-size:0.7rem; padding:2px 6px; white-space:nowrap;" title="Add images (jpg, png, webp, max 2MB)"><i class="feather icon-upload" style="vertical-align:middle;"></i> Add</button>
                        <div class="sj-image-previews"></div>
                    </div>
                </td>
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
            initStockJournalImageCell(row.querySelector('[data-column="images"]'));
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
            sjUpdateMetalTabsLockFromProductList();
            
            // Clear modal input fields (if modal is still open)
            clearModalFields();
        } catch (error) {
            console.error('Error adding product to table:', error);
            alert('Error adding product: ' + error.message);
        }
    }
    
    // Open product modal for a specific row (must match window.currentEditingRowId for product master save + selectProduct/Add flows)
    let currentEditingRowId = null;
    function openProductModalForRow(rowId) {
        if (!rowId) {
            console.error('No rowId provided to openProductModalForRow');
            return;
        }
        currentEditingRowId = rowId;
        if (typeof window !== 'undefined') window.currentEditingRowId = rowId;
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
        const selectedRow = document.querySelector('#productListBody .product-row.selected, #productListBodyPage .product-row.selected');
        if (selectedRow) selectedRow.classList.remove('selected');
        
        // Clear current editing row ID (let + window — editProductRow was only setting window, which broke "add new" after edit)
        currentEditingRowId = null;
        if (typeof window !== 'undefined') window.currentEditingRowId = null;
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
        row.setAttribute('data-metal-id', product.metal_id || currentMetalId || '');
        row.setAttribute('data-metal-name', (product.metal_name || currentMetalName || '').trim());
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
            printCell.innerHTML = `<i class="feather icon-printer" style="cursor: pointer; font-size: 0.9rem; color: #c5a864;" onclick="printBarcodeFromRow(this)" title="Print Barcode"></i>`;
        }
        
        const barcodeCell = row.querySelector('[data-column="barcode"]');
        if (barcodeCell) {
            const barcode = row.getAttribute('data-barcode') || '';
            barcodeCell.innerHTML = `
                <div class="image-placeholder" style="width: 30px; height: 30px; background: #f1f5f9; border-radius: 4px; display: flex; align-items: center; justify-content: center; margin: 0 auto; cursor: pointer;" onclick="printBarcodeFromRow(this)" title="Click to print barcode">
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
        if (grossWtInput) grossWtInput.value = sjFormatWeight(grossWt);
        
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
        sjUpdateMetalTabsLockFromProductList();
    }
    
    // Update Product List table row with data from Product Selection modal row
    async function updateProductListRowFromModalRow(productListRowId, modalRow) {
        const productListRow = document.getElementById(productListRowId);
        if (!productListRow || !modalRow) {
            console.error('Row not found');
            return;
        }
        
        // Extract all data from the Product Selection modal row
        const getRaw = function(column) {
            const cell = modalRow.querySelector(`[data-column="${column}"]`);
            if (!cell) return '';
            const input = cell.querySelector('input');
            const select = cell.querySelector('select');
            if (input) return String(input.value || '').trim();
            if (select) return String(select.value || '').trim();
            return String(cell.textContent || '').trim();
        };
        const getValue = function(column, isNumber = true) {
            const raw = getRaw(column);
            if (!isNumber) return raw;
            if (raw === '') return 0;
            return parseFloat(String(raw).replace(/,/g, '')) || 0;
        };
        
        // Get barcode from modal row (preserve existing barcode in edit mode)
        const barcodeCell = modalRow.querySelector('[data-column="barcode"]');
        let barcode = '';
        if (barcodeCell) {
            const barcodeInput = barcodeCell.querySelector('input.barcode-input, input[type="text"]');
            if (barcodeInput && barcodeInput.value && barcodeInput.value.trim() !== '') {
                barcode = barcodeInput.value.trim();
            } else {
                const barcodeText = barcodeCell.textContent.trim();
                if (barcodeText && barcodeText !== '' && !barcodeText.includes('icon')) {
                    barcode = barcodeText;
                }
            }
        }
        // If still no barcode, keep the existing one from the product list row
        if (!barcode || barcode === '') {
            barcode = productListRow.getAttribute('data-barcode') || '';
        }
        // If still blank, get next serial from server (no duplicate) — use same prefix rules as new lines (RNN… not branch RN).
        if (!barcode || barcode === '') {
            var bcInp = modalRow.querySelector('[data-column="barcode"] input');
            var mrd0 = {
                barcode_prefix: modalRow.getAttribute('data-barcode-prefix') || '',
                barcode_digits: parseInt(modalRow.getAttribute('data-barcode-digits'), 10) || 0,
                barcode: (bcInp && bcInp.value) ? String(bcInp.value).trim() : ''
            };
            var rbc0 = (typeof sjResolveBarcodePrefixDigitForNewLine === 'function')
                ? sjResolveBarcodePrefixDigitForNewLine(mrd0)
                : { prefix: mrd0.barcode_prefix, digits: mrd0.barcode_digits || 5 };
            const prefix = (rbc0 && rbc0.prefix) ? rbc0.prefix : '';
            const digit = (rbc0 && rbc0.digits) ? parseInt(rbc0.digits, 10) : 5;
            try {
                barcode = await getNextBarcodeFromServer(prefix, digit);
            } catch (err) {
                console.error('getNextBarcodeFromServer failed:', err);
                barcode = await getNextBarcodeFromServer('', 0);
            }
        }
        
        const rowData = {
            product_id: modalRow.getAttribute('data-product-id') || '',
            characteristic_id: modalRow.getAttribute('data-characteristic-id') || '',
            product_name: getValue('product', false),
            barcode: barcode,
            quantity: getValue('quantity'),
            gross_wt: (function () {
                var mRaw = getRaw('metal-weight');
                var gRaw = getRaw('gross-wt');
                var m = parseFloat(String(mRaw).replace(/,/g, '')) || 0;
                var g = parseFloat(String(gRaw).replace(/,/g, '')) || 0;
                if (m > 0) return mRaw;
                if (g > 0) return gRaw;
                return '0';
            })(),
            less_wt: getValue('less-wt'),
            pkt_wt: getValue('pkt-wt'),
            pkt_less_wt: getValue('pkt-less-wt'),
            purity: getValue('purity'),
            final_wt: getValue('final-wt'),
            net_wt: getValue('net-wt'),
            pure_wt: getValue('purity-wt'),
            making: getValue('making-amount'),
            design_no: getValue('design-no', false),
            huid_no: getValue('huid', false),
            tax: getValue('tax'),
            amount: getValue('amount'),
            net_amt: getValue('net-amt'),
            net_amt_tax: getValue('net-amt-tax'),
            purchase_amount: getValue('purchase-amount'),
            sale_amount: getValue('sale-amount'),
            sale_amount_with: getValue('sale-amount-with'),
            reverse: getValue('reverse'),
            stone_charges: getValue('stone-amount'),
            other_charges: getValue('other-amount'),
            diamond_value: getValue('diamond-amount'),
            gemstone_value: 0, // Not in modal
            rate: getValue('rate'),
            category_id: getValue('category'),
            location_id: getValue('location'),
            carat_id: getValue('carat'),
            making_type: getValue('making-type', false) || 'Fix',
            making_rate: getValue('making-rate')
        };
        // When the user edits "Weight" (metal-weight), gross-wt in the wide row can still be the old value; we already
        // take metal-weight into rowData.gross_wt. Final Wt on the list must follow the same line weight.
        (function () {
            var mLine = getValue('metal-weight');
            if (mLine > 0) {
                rowData.final_wt = mLine;
            }
        })();
        
        // Update Product List row data attributes
        productListRow.setAttribute('data-product-id', rowData.product_id);
        productListRow.setAttribute('data-characteristic-id', rowData.characteristic_id);
        productListRow.setAttribute('data-purity', rowData.purity);
        productListRow.setAttribute('data-rate', rowData.rate);
        productListRow.setAttribute('data-barcode', barcode);
        productListRow.setAttribute('data-location-id', rowData.location_id || '');
        productListRow.setAttribute('data-carat-id', rowData.carat_id || '');
        productListRow.setAttribute('data-making-type', rowData.making_type || 'Fix');
        productListRow.setAttribute('data-making-rate', rowData.making_rate != null ? rowData.making_rate : '');
        productListRow.setAttribute('data-metal-id', modalRow.getAttribute('data-metal-id') || '');
        productListRow.setAttribute('data-metal-name', sjRowMetalDisplayName(modalRow));
        
        // Update barcode cell in Product List row
        const productListBarcodeCell = productListRow.querySelector('[data-column="barcode"]');
        if (productListBarcodeCell && barcode) {
            productListBarcodeCell.innerHTML = `${escapeHtml(barcode)}`;
            productListBarcodeCell.style.cssText = 'text-align: center; color: #11294b; font-weight: 600; cursor: pointer;';
            productListBarcodeCell.setAttribute('onclick', 'printBarcodeFromRow(this)');
            productListBarcodeCell.setAttribute('title', 'Click to print barcode');
        }
        
        // Update print barcode icon
        const productListPrintCell = productListRow.querySelector('[data-column="print-barcode"]');
        if (productListPrintCell && barcode) {
            productListPrintCell.innerHTML = `<i class="feather icon-printer" style="cursor: pointer; font-size: 0.9rem; color: #c5a864;" onclick="printBarcodeFromRow(this)" title="Print Barcode"></i>`;
        }
        
        // Update description cell
        const descriptionCell = productListRow.querySelector('[data-column="description"]');
        if (descriptionCell) {
            descriptionCell.innerHTML = `<a href="javascript:void(0)" style="color: #11294b; text-decoration: underline;">${escapeHtml(rowData.product_name || '')}</a>`;
        }
        
        // Update location cell (display name from id)
        const locationCell = productListRow.querySelector('[data-column="location"]');
        if (locationCell && typeof locations !== 'undefined') {
            const loc = locations.find(function(l){ return l.id == rowData.location_id || l.id == String(rowData.location_id); });
            locationCell.textContent = loc ? (loc.name || '') : (rowData.location_id || '');
        }
        // Update carat cell (display name from id)
        const caratCell = productListRow.querySelector('[data-column="carat"]');
        if (caratCell && typeof carats !== 'undefined') {
            const car = carats.find(function(c){ return c.id == rowData.carat_id || c.id == String(rowData.carat_id); });
            caratCell.textContent = car ? (car.name || '') : (rowData.carat_id || '');
        }
        // Update pkt-wt and pkt-less-wt cells
        const pktWtCell = productListRow.querySelector('[data-column="pkt-wt"]');
        if (pktWtCell) pktWtCell.textContent = parseFloat(rowData.pkt_wt || 0).toFixed(3);
        const pktLessWtCell = productListRow.querySelector('[data-column="pkt-less-wt"]');
        if (pktLessWtCell) pktLessWtCell.textContent = parseFloat(rowData.pkt_less_wt || 0).toFixed(3);
        
        // Update all editable fields
        const updateField = function(fieldName, value, isNumber = true) {
            const input = productListRow.querySelector(`[data-field="${fieldName}"]`);
            if (input) {
                if (isNumber) {
                    if (fieldName.indexOf('_wt') !== -1) {
                        input.value = sjFormatWeight(value);
                    } else {
                        input.value = parseFloat(value).toFixed(fieldName === 'purity' ? 3 : fieldName === 'quantity' ? 2 : 0);
                    }
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
        updateField('huid_no', rowData.huid_no, false);
        updateField('tax', rowData.tax);
        updateField('stone_charges', rowData.stone_charges);
        updateField('other_charges', rowData.other_charges);
        updateField('diamond_value', rowData.diamond_value);
        
        // Update data attributes for calculations
        productListRow.setAttribute('data-purity', rowData.purity);
        productListRow.setAttribute('data-rate', rowData.rate);
        if (typeof sjStoreProductListLineAmounts === 'function') {
            sjStoreProductListLineAmounts(productListRow, rowData);
        }
        
        // Trigger calculation to update all dependent fields (this will update all calculated columns)
        calculateRowAmounts(productListRow);
        if (typeof sjWriteProductListAmountCells === 'function' && typeof sjReadProductListLineAmounts === 'function') {
            var storedUpd = sjReadProductListLineAmounts(productListRow);
            if (storedUpd) sjWriteProductListAmountCells(productListRow, storedUpd);
        }
        
        // Update summary
        updateSummaryRow();
        updateSummaryPanel();
        // Update balance
        updateBalance();
        sjUpdateMetalTabsLockFromProductList();
    }
    
    // ----- Stock journal multiple images (per row) -----
    window.stockJournalRowImages = window.stockJournalRowImages || {};
    const SJ_MAX_SIZE = 2 * 1024 * 1024;
    const SJ_ACCEPT = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    
    function renderStockJournalPreviews(cell, files) {
        if (!cell) return;
        const container = cell.querySelector('.sj-image-previews');
        if (!container) return;
        container.innerHTML = '';
        var list = files || [];
        if (list.length === 0) return;
        var firstFile = list[0];
        var firstUrl = (firstFile && firstFile instanceof File && firstFile.type && firstFile.type.indexOf('image') === 0) ? URL.createObjectURL(firstFile) : (firstFile && firstFile.url) ? firstFile.url : '';
        if (firstUrl) {
            var firstWrap = document.createElement('div');
            firstWrap.className = 'sj-first-preview-wrap';
            var firstImg = document.createElement('img');
            firstImg.className = 'sj-first-preview';
            firstImg.src = firstUrl;
            firstImg.alt = 'Image 1';
            firstWrap.appendChild(firstImg);
            if (list.length > 1) {
                var badge = document.createElement('span');
                badge.className = 'sj-more-badge';
                badge.textContent = '+' + (list.length - 1);
                firstWrap.appendChild(badge);
            }
            container.appendChild(firstWrap);
        }
        for (var i = 1; i < list.length; i++) {
            var file = list[i];
            var wrap = document.createElement('div');
            wrap.className = 'sj-thumb-wrap';
            var img = document.createElement('img');
            img.className = 'sj-thumb';
            img.dataset.index = i;
            var url = (file && file instanceof File && file.type && file.type.indexOf('image') === 0) ? URL.createObjectURL(file) : (file && file.url) ? file.url : '';
            if (url) img.src = url;
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'sj-thumb-remove';
            btn.innerHTML = '&times;';
            btn.dataset.index = i;
            wrap.appendChild(img);
            wrap.appendChild(btn);
            container.appendChild(wrap);
        }
        var row = cell.closest('tr');
        if (row && row.closest('#productTableBody')) {
            var photoCell = row.querySelector('[data-column="photo"]');
            if (photoCell) updateStockJournalPhotoCell(row, list);
        }
    }
    
    function updateStockJournalPhotoCell(row, files) {
        var photoCell = row && row.querySelector('[data-column="photo"]');
        if (!photoCell) return;
        var wrap = photoCell.querySelector('.sj-photo-first-wrap');
        if (!wrap) return;
        wrap.innerHTML = '';
        var list = files || [];
        if (list.length === 0) return;
        var first = list[0];
        var url = (first && first instanceof File && first.type && first.type.indexOf('image') === 0) ? URL.createObjectURL(first) : (first && first.url) ? first.url : '';
        if (url) {
            var img = document.createElement('img');
            img.src = url;
            img.alt = 'Photo';
            img.style.cssText = 'width:100%;height:100%;object-fit:cover;';
            wrap.appendChild(img);
        }
    }
    
    function initStockJournalImageCell(cell) {
        if (!cell) return;
        const wrap = cell.querySelector('.sj-images-wrap');
        const input = cell.querySelector('.sj-image-input');
        const btn = cell.querySelector('.sj-image-btn');
        const previews = cell.querySelector('.sj-image-previews');
        if (!wrap || !input || !btn || !previews) return;
        const row = cell.closest('tr');
        const rowId = row ? row.id : '';
        const isProductList = row && row.closest('#productTableBody');
        
        btn.addEventListener('click', function(e) { e.preventDefault(); if (typeof openSjAddImageModal === 'function') openSjAddImageModal(cell); else input.click(); });
        
        input.addEventListener('change', function() {
            const files = Array.from(this.files || []);
            const valid = [];
            for (let i = 0; i < files.length; i++) {
                const f = files[i];
                if (f.size > SJ_MAX_SIZE) {
                    alert('Image "' + (f.name || '') + '" exceeds 2MB. Skipped.');
                    continue;
                }
                if (!SJ_ACCEPT.includes(f.type)) {
                    alert('Invalid type for "' + (f.name || '') + '". Use jpg, png or webp.');
                    continue;
                }
                valid.push(f);
            }
            if (isProductList && rowId) {
                window.stockJournalRowImages[rowId] = (window.stockJournalRowImages[rowId] || []).concat(valid);
                renderStockJournalPreviews(cell, window.stockJournalRowImages[rowId]);
            } else {
                row._modalImageFiles = (row._modalImageFiles || []).concat(valid);
                renderStockJournalPreviews(cell, row._modalImageFiles);
            }
            this.value = '';
        });
        
        previews.addEventListener('click', function(e) {
            const rm = e.target.closest('.sj-thumb-remove');
            if (!rm) return;
            const i = parseInt(rm.dataset.index, 10);
            if (isProductList && rowId && window.stockJournalRowImages[rowId]) {
                window.stockJournalRowImages[rowId].splice(i, 1);
                if (!window.stockJournalRowImages[rowId].length && row && row.removeAttribute) {
                    row.removeAttribute('data-sj-temp-image-paths');
                }
                renderStockJournalPreviews(cell, window.stockJournalRowImages[rowId]);
            } else if (row && row._modalImageFiles) {
                row._modalImageFiles.splice(i, 1);
                renderStockJournalPreviews(cell, row._modalImageFiles);
            }
        });
    }
    
    // ----- Stock Journal Add Image Modal (open on ADD click, add multiple one by one) -----
    var sjAddImageModalFiles = [];
    var sjAddImageModalPrimaryIndex = 0;
    var sjAddImageTargetCell = null;
    var sjAddImageTargetRow = null;
    var sjAddImageIsProductList = false;
    var sjAddImageObjectUrls = [];
    
    function openSjAddImageModal(cell) {
        sjAddImageTargetCell = cell;
        var row = cell && cell.closest('tr');
        sjAddImageTargetRow = row;
        sjAddImageIsProductList = row && row.closest('#productTableBody');
        var rowId = row ? row.id : '';
        sjAddImageModalFiles = [];
        sjAddImageModalPrimaryIndex = 0;
        if (sjAddImageObjectUrls.length) { sjAddImageObjectUrls.forEach(function(u) { try { URL.revokeObjectURL(u); } catch(e) {} }); sjAddImageObjectUrls = []; }
        if (sjAddImageIsProductList && rowId && window.stockJournalRowImages && window.stockJournalRowImages[rowId] && window.stockJournalRowImages[rowId].length) {
            sjAddImageModalFiles = window.stockJournalRowImages[rowId].slice();
            sjAddImageModalPrimaryIndex = 0;
        } else if (row && row._modalImageFiles && row._modalImageFiles.length) {
            sjAddImageModalFiles = row._modalImageFiles.slice();
            sjAddImageModalPrimaryIndex = 0;
        }
        sjAddImageRenderModalPreview();
        var modal = document.getElementById('sjAddImageModal');
        if (modal && typeof jQuery !== 'undefined' && jQuery.fn.modal) jQuery('#sjAddImageModal').modal('show');
        else if (modal) { modal.classList.add('show'); modal.style.display = 'block'; modal.setAttribute('aria-hidden', 'false'); }
    }
    
    function closeSjAddImageModal() {
        if (sjAddImageObjectUrls.length) { sjAddImageObjectUrls.forEach(function(u) { try { URL.revokeObjectURL(u); } catch(e) {} }); sjAddImageObjectUrls = []; }
        sjAddImageModalFiles = [];
        sjAddImageModalPrimaryIndex = 0;
        sjAddImageTargetCell = null;
        sjAddImageTargetRow = null;
        var modal = document.getElementById('sjAddImageModal');
        if (modal && typeof jQuery !== 'undefined' && jQuery.fn.modal) jQuery('#sjAddImageModal').modal('hide');
        else if (modal) { modal.classList.remove('show'); modal.style.display = 'none'; modal.setAttribute('aria-hidden', 'true'); }
    }
    
    function sjAddImageRenderModalPreview() {
        if (sjAddImageObjectUrls.length) { sjAddImageObjectUrls.forEach(function(u) { try { URL.revokeObjectURL(u); } catch(e) {} }); sjAddImageObjectUrls = []; }
        var placeholder = document.getElementById('sjAddImagePreviewPlaceholder');
        var previewImg = document.getElementById('sjAddImagePreviewImg');
        var primaryFile = sjAddImageModalFiles[sjAddImageModalPrimaryIndex];
        var primaryUrl = '';
        if (primaryFile && primaryFile instanceof File) {
            primaryUrl = URL.createObjectURL(primaryFile);
            sjAddImageObjectUrls.push(primaryUrl);
        }
        if (placeholder) placeholder.style.display = primaryUrl ? 'none' : 'block';
        if (previewImg) {
            if (primaryUrl) { previewImg.src = primaryUrl; previewImg.style.display = 'block'; }
            else { previewImg.style.display = 'none'; previewImg.src = ''; }
        }
        var wrap = document.getElementById('sjAddImageThumbnailsWrap');
        if (!wrap) return;
        var uploadZone = document.getElementById('sjAddImageUploadZone');
        var list = wrap.querySelector('.sjAddImage-thumb-list');
        if (list) list.remove();
        list = document.createElement('div');
        list.className = 'sjAddImage-thumb-list d-flex flex-wrap';
        list.style.gap = '0.5rem';
        sjAddImageModalFiles.forEach(function(file, idx) {
            var box = document.createElement('div');
            box.style.cssText = 'width: 70px; height: 70px; border-radius: 8px; overflow: hidden; position: relative; border: 2px solid ' + (idx === sjAddImageModalPrimaryIndex ? '#11294b' : '#e2e8f0') + '; cursor: pointer; flex-shrink: 0;';
            var img = document.createElement('img');
            if (file instanceof File) { var u = URL.createObjectURL(file); sjAddImageObjectUrls.push(u); img.src = u; }
            img.alt = 'Image ' + (idx + 1);
            img.style.cssText = 'width: 100%; height: 100%; object-fit: cover;';
            box.appendChild(img);
            var x = document.createElement('span');
            x.setAttribute('aria-label', 'Remove');
            x.style.cssText = 'position: absolute; top: 2px; right: 2px; width: 20px; height: 20px; background: rgba(0,0,0,0.6); color: #fff; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; line-height: 1; cursor: pointer;';
            x.textContent = '×';
            x.onclick = function(ev) { ev.stopPropagation(); sjAddImageRemoveAt(idx); };
            box.appendChild(x);
            box.onclick = function(ev) { if (ev.target !== x) sjAddImageSetPrimary(idx); };
            list.appendChild(box);
        });
        if (uploadZone && uploadZone.parentNode) uploadZone.parentNode.insertBefore(list, uploadZone.nextSibling);
        else wrap.appendChild(list);
    }
    
    function sjAddImageAddFiles(files) {
        if (!files || !files.length) return;
        for (var i = 0; i < files.length; i++) {
            var file = files[i];
            if (!file.type || file.type.indexOf('image/') !== 0) continue;
            if (file.size > SJ_MAX_SIZE) { alert('Image "' + (file.name || '') + '" exceeds 2MB. Skipped.'); continue; }
            if (!SJ_ACCEPT.includes(file.type)) { alert('Invalid type. Use jpg, png or webp.'); continue; }
            sjAddImageModalFiles.push(file);
        }
        if (sjAddImageModalFiles.length === 1) sjAddImageModalPrimaryIndex = 0;
        sjAddImageRenderModalPreview();
    }
    
    function sjAddImageRemoveAt(idx) {
        sjAddImageModalFiles.splice(idx, 1);
        if (sjAddImageModalPrimaryIndex >= sjAddImageModalFiles.length) sjAddImageModalPrimaryIndex = Math.max(0, sjAddImageModalFiles.length - 1);
        if (sjAddImageModalPrimaryIndex > idx) sjAddImageModalPrimaryIndex--;
        sjAddImageRenderModalPreview();
    }
    
    function sjAddImageSetPrimary(idx) {
        sjAddImageModalPrimaryIndex = idx;
        sjAddImageRenderModalPreview();
    }
    
    (function setupSjAddImageModal() {
        var modal = document.getElementById('sjAddImageModal');
        var fileInput = document.getElementById('sjAddImageModalFileInput');
        var uploadZone = document.getElementById('sjAddImageUploadZone');
        var saveBtn = document.getElementById('sjAddImageModalSaveBtn');
        var cancelBtn = document.getElementById('sjAddImageModalCancelBtn');
        var cameraBtn = document.getElementById('sjAddImageModalCameraBtn');
        var closeBtn = document.getElementById('sjAddImageModalClose');
        if (uploadZone && fileInput) {
            uploadZone.addEventListener('click', function() { fileInput.click(); });
            uploadZone.addEventListener('dragover', function(e) { e.preventDefault(); uploadZone.style.background = '#e2e8f0'; });
            uploadZone.addEventListener('dragleave', function() { uploadZone.style.background = '#f1f5f9'; });
            uploadZone.addEventListener('drop', function(e) {
                e.preventDefault();
                uploadZone.style.background = '#f1f5f9';
                var files = e.dataTransfer && e.dataTransfer.files;
                if (files && files.length) sjAddImageAddFiles(Array.prototype.slice.call(files));
            });
        }
        if (fileInput) {
            fileInput.addEventListener('change', function() {
                var files = this.files;
                if (files && files.length) sjAddImageAddFiles(Array.prototype.slice.call(files));
                this.value = '';
            });
        }
        if (cameraBtn && fileInput) cameraBtn.addEventListener('click', function() { fileInput.click(); });
        if (saveBtn) {
            saveBtn.addEventListener('click', function() {
                if (sjAddImageTargetCell && sjAddImageModalFiles.length >= 0) {
                    if (sjAddImageIsProductList && sjAddImageTargetRow && sjAddImageTargetRow.id) {
                        window.stockJournalRowImages = window.stockJournalRowImages || {};
                        window.stockJournalRowImages[sjAddImageTargetRow.id] = sjAddImageModalFiles.slice();
                        renderStockJournalPreviews(sjAddImageTargetCell, window.stockJournalRowImages[sjAddImageTargetRow.id]);
                    } else if (sjAddImageTargetRow) {
                        sjAddImageTargetRow._modalImageFiles = sjAddImageModalFiles.slice();
                        renderStockJournalPreviews(sjAddImageTargetCell, sjAddImageTargetRow._modalImageFiles);
                    }
                }
                closeSjAddImageModal();
            });
        }
        if (cancelBtn) cancelBtn.addEventListener('click', closeSjAddImageModal);
        if (closeBtn) closeBtn.addEventListener('click', closeSjAddImageModal);
        if (modal) {
            modal.addEventListener('click', function(e) {
                if (e.target === modal) closeSjAddImageModal();
            });
        }
    })();
    
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
        const quantityField = row.querySelector('[data-field="quantity"]');
        
        if (grossWtField) {
            grossWtField.addEventListener('input', function() { 
                calculateRowAmounts(row);
                updateBalance();
            });
            grossWtField.addEventListener('change', function() { 
                // Cap gross wt at balance when item_id is set
                if (typeof stockDetailItem !== 'undefined' && stockDetailItem) {
                    const balance = getBalanceQtyAndWeight(row);
                    if (balance) {
                        const val = parseFloat(this.value) || 0;
                        if (val > balance.balanceGrossWt + SJ_BALANCE_WT_EPS) {
                            this.value = sjFormatWeight(balance.balanceGrossWt);
                            calculateRowAmounts(row);
                            alert('Gross weight capped at balance: ' + sjFormatWeight(balance.balanceGrossWt) + '. You cannot exceed total gross weight.');
                        }
                    }
                }
                calculateRowAmounts(row);
                updateBalance();
            });
        }
        if (lessWtField) {
            lessWtField.addEventListener('input', function() { calculateRowAmounts(row); });
            lessWtField.addEventListener('change', function() { calculateRowAmounts(row); });
        }
        if (purityField) {
            purityField.addEventListener('input', function() { calculateRowAmounts(row); });
            purityField.addEventListener('change', function() { calculateRowAmounts(row); });
        }
        if (quantityField) {
            quantityField.addEventListener('input', function() { 
                updateBalance();
            });
            quantityField.addEventListener('change', function() { 
                // Cap quantity at balance when item_id is set
                if (typeof stockDetailItem !== 'undefined' && stockDetailItem) {
                    const balance = getBalanceQtyAndWeight(row);
                    if (balance) {
                        const val = parseFloat(this.value) || 0;
                        if (val > balance.balanceQty + SJ_BALANCE_QTY_EPS) {
                            this.value = balance.balanceQty.toFixed(2);
                            updateBalance();
                            alert('Quantity capped at balance: ' + balance.balanceQty.toFixed(2) + '. You cannot exceed total quantity.');
                        }
                    }
                }
                updateBalance();
            });
        }
    }
    
    // Calculate row amounts - Using same comprehensive logic as modal
    /** Store typed / modal line amounts on product-list row so calculateRowAmounts does not wipe them (imitation wt=0). */
    function sjStoreProductListLineAmounts(row, data) {
        if (!row || !data) return;
        function n(v) {
            var x = parseFloat(v);
            return isFinite(x) ? x : 0;
        }
        var purchase = n(data.purchase_amount);
        var sale = n(data.sale_amount);
        var saleWith = n(data.sale_amount_with);
        var amount = n(data.amount);
        var netAmt = n(data.net_amt);
        var netTax = n(data.net_amt_tax);
        var reverse = n(data.reverse);
        var tax = n(data.tax);
        // Imitation / PI: if amount/net empty, fall back to purchase then sale then reverse
        if (amount <= 0) {
            amount = purchase > 0 ? purchase : (sale > 0 ? sale : reverse);
        }
        if (netAmt <= 0) {
            netAmt = purchase > 0 ? purchase : (sale > 0 ? sale : (amount > 0 ? amount : reverse));
        }
        if (netTax <= 0) {
            netTax = saleWith > 0 ? saleWith : (netAmt + tax);
        }
        if (purchase <= 0 && netAmt > 0) purchase = netAmt;
        if (sale <= 0 && reverse > 0) sale = reverse;
        if (saleWith <= 0 && sale > 0) saleWith = sale + tax;
        row.setAttribute('data-sj-purchase-amount', purchase.toFixed(2));
        row.setAttribute('data-sj-sale-amount', sale.toFixed(2));
        row.setAttribute('data-sj-sale-amount-with', saleWith.toFixed(2));
        row.setAttribute('data-sj-amount', amount.toFixed(2));
        row.setAttribute('data-sj-net-amt', netAmt.toFixed(2));
        row.setAttribute('data-sj-net-amt-tax', netTax.toFixed(2));
        row.setAttribute('data-sj-reverse', reverse.toFixed(2));
        if (purchase > 0 || sale > 0 || netAmt > 0 || amount > 0 || reverse > 0) {
            row.setAttribute('data-preserve-line-amounts', '1');
            if (purchase > 0) row.setAttribute('data-manual-purchase-amount', '1');
            if (sale > 0 && sale !== purchase) row.setAttribute('data-manual-sale-amount', '1');
        }
    }

    function sjReadProductListLineAmounts(row) {
        if (!row) return null;
        function a(name) {
            var x = parseFloat(row.getAttribute(name));
            return isFinite(x) ? x : 0;
        }
        if (row.getAttribute('data-preserve-line-amounts') !== '1'
            && a('data-sj-purchase-amount') <= 0
            && a('data-sj-sale-amount') <= 0
            && a('data-sj-net-amt') <= 0
            && a('data-sj-amount') <= 0) {
            return null;
        }
        return {
            purchase: a('data-sj-purchase-amount'),
            sale: a('data-sj-sale-amount'),
            saleWith: a('data-sj-sale-amount-with'),
            amount: a('data-sj-amount'),
            netAmt: a('data-sj-net-amt'),
            netTax: a('data-sj-net-amt-tax'),
            reverse: a('data-sj-reverse')
        };
    }

    function sjWriteProductListAmountCells(row, vals) {
        if (!row || !vals) return;
        function setText(col, val) {
            var td = row.querySelector('[data-column="' + col + '"]');
            if (!td) return;
            var inp = td.querySelector('input');
            var s = (parseFloat(val) || 0).toFixed(2);
            if (inp) inp.value = s;
            else td.textContent = s;
        }
        setText('amount', vals.amount);
        setText('net-amt', vals.netAmt);
        setText('net-amt-tax', vals.netTax);
        setText('purchase-amount', vals.purchase);
        setText('sale-amount', vals.sale);
        setText('sale-amount-with', vals.saleWith);
        if (vals.reverse != null) setText('reverse', vals.reverse);
    }

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
        let amount = metalValue + making + stoneCharges + otherCharges + diamondValue + gemstoneValue;
        
        // Net Amt = Amount
        let netAmt = amount;
        
        // Net Amt With Tax = Amount + Tax
        let netAmtWithTax = amount + tax;
        
        // Purchase Amount = Net Amount
        let purchaseAmount = netAmt;
        
        // Sale Amount = Net Amount
        let saleAmount = netAmt;
        
        // Sale Amount With = Net Amount With Tax
        let saleAmountWith = netAmtWithTax;

        // Keep modal / imitation typed amounts (weight often 0 → metal calc would wipe Purchase/Sale/Net)
        var stored = typeof sjReadProductListLineAmounts === 'function' ? sjReadProductListLineAmounts(row) : null;
        if (stored && (stored.purchase > 0 || stored.sale > 0 || stored.netAmt > 0 || stored.amount > 0 || stored.reverse > 0)) {
            if (stored.purchase > 0) purchaseAmount = stored.purchase;
            if (stored.sale > 0) saleAmount = stored.sale;
            if (stored.saleWith > 0) saleAmountWith = stored.saleWith;
            if (stored.amount > 0) amount = stored.amount;
            else if (amount <= 0) amount = purchaseAmount > 0 ? purchaseAmount : saleAmount;
            if (stored.netAmt > 0) netAmt = stored.netAmt;
            else if (netAmt <= 0) netAmt = purchaseAmount > 0 ? purchaseAmount : (amount > 0 ? amount : saleAmount);
            if (stored.netTax > 0) netAmtWithTax = stored.netTax;
            else netAmtWithTax = netAmt + tax;
            if (saleAmountWith <= 0 && saleAmount > 0) saleAmountWith = saleAmount + tax;
        }
        
        // Update calculated fields (read-only cells)
        const netWtCell = row.querySelector('[data-column="net-wt"]');
        if (netWtCell) netWtCell.textContent = sjFormatWeight(netWt);
        
        const pureWtCell = row.querySelector('[data-column="pure-wt"]');
        if (pureWtCell) pureWtCell.textContent = sjFormatWeight(pureWt);
        
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

        if (stored && stored.reverse > 0) {
            const reverseCell = row.querySelector('[data-column="reverse"]');
            if (reverseCell && !reverseCell.querySelector('input')) {
                reverseCell.textContent = stored.reverse.toFixed(2);
            }
        }
        
        // Update summary
        updateSummaryRow();
        updateSummaryPanel();
    }
    
    /**
     * Discount (group) — "Per." is always a percentage of the base implied by Type:
     * On Amount = metal + making + stone (if not already in metal) + other
     * On Making Amount = making amount
     * On Diamond Amount = diamond amount
     * On Stone Amount = stone amount
     * On Net Amount = full line before group discount (amount base + diamond)
     * On Percentage (legacy) = metal value only
     */
    function getDiscountBaseByType(discountType, ctx) {
        const t = discountType || 'On Amount';
        if (t === 'On Percentage') {
            return ctx.metalValue;
        }
        if (t === 'On Making Amount') {
            return ctx.makingAmount;
        }
        if (t === 'On Diamond Amount') {
            return ctx.diamondAmount;
        }
        if (t === 'On Stone Amount') {
            return ctx.stoneAmount;
        }
        if (t === 'On Net Amount') {
            return ctx.netBasePreDiscount;
        }
        return ctx.amountBase;
    }

    function computePctDiscountFromType(discountType, discountPer, ctx) {
        const base = getDiscountBaseByType(discountType, ctx);
        const per = parseFloat(discountPer) || 0;
        return base * (per / 100);
    }
    
    // addModalRowCalculationListeners: product-modal-add-item-common.js (carat ↔ D.Weight sync on Diamond tab)
    
    // Calculate ALL values for modal product rows - COMPREHENSIVE CALCULATION FUNCTION
    // Formulas:
    // 1. Net Weight = Gross Weight - Less Weight
    // 2. Purity Weight = Net Weight × Purity
    // 3. Wastage Weight = Net Weight × (Wastage % / 100)
    // 4. Final Weight = Net Weight + Wastage Weight
    // 5. Alloy Weight = Gross Weight - Net Weight
    // 6. Metal Value = Gold Rate × Gross Weight
    // 7. Group discount: Per. × (base by Type) / 100 — bases: Amount / Making / Diamond / Stone / Net (see getDiscountBaseByType)
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
        
        // Making fields
        const makingTypeSelect = row.querySelector('[data-column="making-type"] select');
        const makingRateInput = row.querySelector('[data-column="making-rate"] input');
        const makingDiscountAmtInput = row.querySelector('[data-column="making-discount-amt"] input');
        const makingAmountInput = row.querySelector('[data-column="making-amount"] input');
        const makingActualValueInput = row.querySelector('[data-column="making-actual-value"] input');
        const makingCostInput = row.querySelector('[data-column="making-cost"] input');
        
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
        
        // Parse basic values: UI "Weight" is metal-weight. If it is set, it wins over gross-wt (avoids stale 10 vs edited 12).
        const metalWtInput = row.querySelector('[data-column="metal-weight"] input');
        let grossWt = parseFloat(grossWtInput.value) || 0;
        const metalRaw = metalWtInput ? String(metalWtInput.value || '').trim() : '';
        const metalWtCol = parseFloat(String(metalRaw).replace(/,/g, '')) || 0;
        if (metalWtCol > 0) {
            grossWt = metalWtCol;
            if (grossWtInput) grossWtInput.value = metalRaw;
        }
        const lessWt = parseFloat(lessWtInput.value) || 0;
        let purity = parseFloat(purityInput.value) || 0;
        const wastagePer = parseFloat(wastagePerInput?.value) || 0;
        const goldRate = parseFloat(rateInput?.value) || 0;
        const metalRateInput = row.querySelector('[data-column="metal-rate"] input');
        const metalRate = parseFloat(metalRateInput?.value) || 0;
        
        // Handle purity format: if purity > 1, assume it's percentage (e.g., 75 = 0.75)
        if (purity > 1) {
            purity = purity / 100;
        }
        
        // ========== WEIGHT CALCULATIONS ==========
        // 1. Net Weight = Gross Weight - Less Weight
        const netWt = grossWt - lessWt;
        if (netWtInput) netWtInput.value = sjFormatWeight(netWt);
        
        // 3. Wastage Weight = Net Weight × (Wastage % / 100)
        const wastageWt = netWt * (wastagePer / 100);
        if (wastageWtInput) wastageWtInput.value = sjFormatWeight(wastageWt);
        
        // 2. Purity Weight = Net Weight × Purity
        const purityWt = netWt * purity;
        if (purityWtInput) purityWtInput.value = sjFormatWeight(purityWt);
        
        // 4. Final Weight = Purity Weight (Final Wt. should equal Purity Wt.)
        const finalWt = purityWt;
        if (finalWtInput) finalWtInput.value = sjFormatWeight(finalWt);
        
        // 5. Alloy Weight - Don't auto-calculate, preserve user's entered value or default to 0
        // Only set to 0.000 if the field is truly empty/undefined, otherwise preserve whatever user typed
        if (alloyWtInput) {
            const currentValue = alloyWtInput.value;
            // Only set to 0.000 if field is empty or undefined (not if user has typed 0)
            // This preserves any value the user has manually entered
            if (!currentValue || currentValue.trim() === '') {
                alloyWtInput.value = '0.000';
            }
            // If user has entered any value (including 0), keep it as-is - don't overwrite
        }
        
        // ========== METAL VALUE CALCULATION ==========
        // Align with product-modal-add-item-common.js: gold/silver use Metal Rate; diamond/stone lines use Rate column.
        const calculationSelect = row.querySelector('[data-column="calculation"] select');
        const categorySelect = row.querySelector('[data-column="category"] select');
        const calculationType = calculationSelect ? calculationSelect.value : 'Weight X Rate';
        const categoryId = categorySelect ? categorySelect.value : '';
        let isDiamondOrStone = (categoryId === 'Diamonds' || categoryId === 'GemStones');
        if (!isDiamondOrStone && categoryId && typeof categories !== 'undefined' && categories && Array.isArray(categories)) {
            const cr = categories.find(function(x) { return String(x.id) === String(categoryId); });
            if (cr && cr.name) {
                isDiamondOrStone = (cr.name === 'Diamonds' || cr.name === 'GemStones');
            }
        }
        const rateForMetalValue = isDiamondOrStone ? goldRate : (metalRate > 0 ? metalRate : goldRate);
        const quantityForCalc = parseFloat(row.querySelector('[data-column="quantity"] input')?.value) || 1;
        const stoneWeightForCalc = parseFloat(stoneWeightInput?.value) || 0;
        const caratSelectEl = row.querySelector('[data-column="carat"] select');
        
        let metalValue = 0;
        if (calculationType === 'Fix') {
            metalValue = rateForMetalValue;
        } else if (calculationType === 'Quantity X Rate') {
            metalValue = quantityForCalc * rateForMetalValue;
        } else if (calculationType === 'Stone Charge') {
            const stoneWeight = parseFloat(stoneWeightInput?.value) || 0;
            const stoneRate = parseFloat(stoneRateInput?.value) || 0;
            const stoneAmount = stoneWeight * stoneRate;
            metalValue = stoneAmount;
            if (stoneAmountInput) stoneAmountInput.value = stoneAmount.toFixed(2);
        } else if (calculationType === 'Carat X Rate') {
            // Carat / Stone Wt. × Rate (Rate column). Do not use Karat dropdown id as a weight.
            // If no stone carat is entered, fall back to Metal/Rate × Final/Purity/Net wt (gold lines).
            if (stoneWeightForCalc > 0 && goldRate > 0) {
                metalValue = stoneWeightForCalc * goldRate;
            } else {
                const fw = parseFloat(finalWtInput?.value) || purityWt || netWt;
                const rateFallback = rateForMetalValue > 0 ? rateForMetalValue : goldRate;
                metalValue = rateFallback * fw;
            }
        } else if (calculationType === 'Weight X Rate') {
            const fw = parseFloat(finalWtInput?.value) || purityWt || netWt;
            metalValue = rateForMetalValue * fw;
        } else if (calculationType === 'Rate X Gross Wt') {
            const grossForCalc = isDiamondOrStone ? grossWt : ((metalWtCol > 0) ? metalWtCol : grossWt);
            metalValue = rateForMetalValue * grossForCalc;
        } else if (calculationType === 'Rate X Purity Wt') {
            metalValue = rateForMetalValue * purityWt;
        } else if (calculationType === 'Rate X Net Wt') {
            metalValue = rateForMetalValue * netWt;
        } else if (calculationType === 'Rate X Final Wt') {
            const fw = parseFloat(finalWtInput?.value) || purityWt || netWt;
            metalValue = rateForMetalValue * fw;
        } else if (calculationType === 'Metal Rate x Metal Weight') {
            metalValue = metalRate * metalWtCol;
        } else if (calculationType === 'Metal Carat x Metal Rate') {
            const cv = caratSelectEl ? (parseFloat(caratSelectEl.value) || 0) : 0;
            metalValue = cv * metalRate;
        } else if (calculationType === 'Metal Rate x Metal Purity') {
            metalValue = metalRate * purityWt;
        } else {
            const fw = parseFloat(finalWtInput?.value) || purityWt || netWt;
            metalValue = rateForMetalValue * fw;
        }

        if (calculationType !== 'Stone Charge' && typeof window.auragoldCalcMetalValueFromPurchaseRate === 'function') {
            var piMetalValue = window.auragoldCalcMetalValueFromPurchaseRate(row);
            if (piMetalValue != null) metalValue = piMetalValue;
        }
        
        if (metalValueInput) metalValueInput.value = metalValue.toFixed(2);
        
        // ========== MAKING CALCULATION ==========
        // 9. Making Amount (by type: Fix, Per Gram, Per Piece, Per Kilogram, Per Percent, MRP, M.KT)
        let makingAmount = 0;
        if (makingTypeSelect && makingRateInput) {
            const makingType = makingTypeSelect.value || 'Fix';
            const makingRate = parseFloat(makingRateInput.value) || 0;
            const makingDiscountAmt = parseFloat(makingDiscountAmtInput?.value) || 0;
            const quantityInput = row.querySelector('[data-column="quantity"] input');
            const quantity = parseFloat(quantityInput?.value) || 1;
            
            if (makingType === 'Fix' || makingType === 'MRP' || makingType === 'M.KT') {
                makingAmount = makingRate;
            } else if (makingType === 'Per Gram') {
                makingAmount = netWt * makingRate;
            } else if (makingType === 'Per Piece') {
                makingAmount = quantity * makingRate;
            } else if (makingType === 'Per Kilogram') {
                makingAmount = (netWt / 1000) * makingRate;
            } else if (makingType === 'Per Percent' || makingType === 'Percentage') {
                makingAmount = metalValue * (makingRate / 100);
            } else {
                makingAmount = makingRate;
            }
            
            // Apply making discount
            makingAmount = makingAmount - makingDiscountAmt;
            if (makingAmount < 0) makingAmount = 0;
            
            if (makingAmountInput) makingAmountInput.value = makingAmount.toFixed(2);
            if (makingCostInput) makingCostInput.value = makingAmount.toFixed(2);
        }

        // Actual Making Value: PI purchase rate × weight only — never follows editable Making Rate
        if (typeof window.auragoldApplyPurchaseMakingActualValueField === 'function') {
            window.auragoldApplyPurchaseMakingActualValueField(row);
        } else if (makingActualValueInput && makingAmountInput) {
            makingActualValueInput.value = (parseFloat(makingAmountInput.value) || 0).toFixed(2);
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
        
        // ========== GROUP DISCOUNT (Per. = % of base chosen by Type) ==========
        const diamondAmount = parseFloat(diamondAmountInput?.value) || 0;
        const stonePartForAmount = (calculationType === 'Stone Charge' ? 0 : stoneAmount);
        const amountBase = metalValue + makingAmount + stonePartForAmount + otherAmount;
        const netBasePreDiscount = amountBase + diamondAmount;

        const discountCtx = {
            metalValue: metalValue,
            makingAmount: makingAmount,
            stoneAmount: stoneAmount,
            otherAmount: otherAmount,
            diamondAmount: diamondAmount,
            amountBase: amountBase,
            netBasePreDiscount: netBasePreDiscount
        };

        let discount1 = 0;
        if (discountTypeSelect && discountPerInput) {
            const dt1 = discountTypeSelect.value || 'On Amount';
            const base1 = getDiscountBaseByType(dt1, discountCtx);
            discount1 = computePctDiscountFromType(dt1, discountPerInput.value, discountCtx);
            if (discountAmountInput) discountAmountInput.value = base1.toFixed(2);
            if (discountInput) discountInput.value = discount1.toFixed(2);
        }

        // ========== AMOUNT CALCULATION ==========
        // Amount = Metal Value + Making Amount + Stone Amount + Other Amount (discount is informational only, not deducted)
        // Note: If calculation type is "Stone Charge", metalValue already contains stoneAmount, so don't add it twice
        let calculatedAmount = metalValue + makingAmount + (calculationType === 'Stone Charge' ? 0 : stoneAmount) + otherAmount;
        if (calculatedAmount < 0) calculatedAmount = 0;
        
        if (amountInput) amountInput.value = calculatedAmount.toFixed(2);
        
        // ========== NET AMOUNT ==========
        // Net Amount = Amount + diamond (diamond already in netBasePreDiscount for "On Net Amount" discount base)
        let netAmt = calculatedAmount + diamondAmount;
        // Stock journal create: Purchase/Sale Amount are user-editable (esp. imitation / product opening / PI line)
        var manualSaleAmt = row.getAttribute('data-manual-sale-amount') === '1';
        var manualPurchaseAmt = row.getAttribute('data-manual-purchase-amount') === '1';
        var saleFocused = false;
        var purchaseFocused = false;
        try {
            saleFocused = !!(saleAmountInput && document.activeElement === saleAmountInput);
            purchaseFocused = !!(purchaseAmountInput && document.activeElement === purchaseAmountInput);
        } catch (eFocus) {}
        var isPurchasePiCtx = (typeof window.auragoldIsPurchaseInvoiceContext === 'function')
            ? window.auragoldIsPurchaseInvoiceContext()
            : false;
        var salePercentApplied = false;
        var piCostBase = netAmt;
        if (typeof window.auragoldApplySalePercentFromPurchase === 'function') {
            salePercentApplied = window.auragoldApplySalePercentFromPurchase(row);
        }
        if (manualSaleAmt && saleAmountInput) {
            netAmt = parseFloat(saleAmountInput.value) || 0;
            if (netAmt < 0) netAmt = 0;
            calculatedAmount = Math.max(0, netAmt - diamondAmount);
            if (amountInput) amountInput.value = calculatedAmount.toFixed(2);
        } else if (salePercentApplied && saleAmountInput) {
            netAmt = parseFloat(saleAmountInput.value) || 0;
            if (netAmt < 0) netAmt = 0;
            calculatedAmount = Math.max(0, netAmt - diamondAmount);
            if (amountInput) amountInput.value = calculatedAmount.toFixed(2);
        } else if ((manualPurchaseAmt || purchaseFocused) && purchaseAmountInput) {
            netAmt = parseFloat(purchaseAmountInput.value) || 0;
            if (netAmt < 0) netAmt = 0;
            calculatedAmount = Math.max(0, netAmt - diamondAmount);
            if (amountInput) amountInput.value = calculatedAmount.toFixed(2);
        } else if (purchaseAmountInput && (parseFloat(purchaseAmountInput.value) || 0) > 0
            && (typeof window.auragoldAllowManualPurchaseSaleAmounts === 'function'
                ? window.auragoldAllowManualPurchaseSaleAmounts()
                : true)
            && calculatedAmount <= 0.00001 && diamondAmount <= 0.00001) {
            // Imitation / SJ: typed purchase with no metal calc — use purchase as Net Amt even if flag was cleared
            netAmt = parseFloat(purchaseAmountInput.value) || 0;
            if (netAmt < 0) netAmt = 0;
            row.setAttribute('data-manual-purchase-amount', '1');
            manualPurchaseAmt = true;
        }
        
        if (netAmtInput) netAmtInput.value = netAmt.toFixed(2);
        
        // ========== PURCHASE AMOUNT ==========
        if (purchaseAmountInput && !(manualPurchaseAmt || purchaseFocused)) {
            if (isPurchasePiCtx && salePercentApplied && piCostBase > 0) {
                purchaseAmountInput.value = piCostBase.toFixed(2);
            } else {
                purchaseAmountInput.value = netAmt.toFixed(2);
            }
        }
        
        // ========== SALE AMOUNT ==========
        if (saleAmountInput && !(manualSaleAmt || saleFocused) && !salePercentApplied) {
            saleAmountInput.value = netAmt.toFixed(2);
        }
        
        // ========== TAX CALCULATION ==========
        // Respect tax-type; Tax % column (0 = no tax). Do not force a 5% default when % is blank/0.
        const taxTypeSelect = row.querySelector('[data-column="tax-type"] select');
        const taxType = taxTypeSelect ? (taxTypeSelect.value || 'no_tax') : 'no_tax';
        const taxPercentInput = row.querySelector('[data-column="tax-percent"] input');
        let taxPercent = 0;
        if (taxPercentInput && String(taxPercentInput.value || '').trim() !== '') {
            taxPercent = parseFloat(taxPercentInput.value) || 0;
        }
        let tax = 0;
        if (taxType === 'tax_of_netamount') {
            tax = netAmt * (taxPercent / 100);
        } else if (taxType === 'tax_on_making') {
            tax = makingAmount * (taxPercent / 100);
        }
        if (taxInput) taxInput.value = tax.toFixed(2);
        
        // ========== NET AMOUNT + TAX ==========
        const netAmtTax = tax + netAmt;
        if (netAmtTaxInput) netAmtTaxInput.value = netAmtTax.toFixed(2);
        if (saleAmountWithInput) {
            if (isPurchasePiCtx) {
                var saleAmtForWith = saleAmountInput ? (parseFloat(saleAmountInput.value) || 0) : 0;
                saleAmountWithInput.value = saleAmtForWith.toFixed(2);
            } else {
                saleAmountWithInput.value = netAmtTax.toFixed(2);
            }
        }
    }
    // Prefer this page's calculator over product-modal-add-item-common.js defaults.
    window.calculateModalRowNetWeight = calculateModalRowNetWeight;
    if (typeof window.auragoldApplyManualAmountFieldsEditability === 'function') {
        window.auragoldApplyManualAmountFieldsEditability();
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
                    // Use original_balance so Previous Balance matches ledger (amount, gold, silver)
                    const bal = data.original_balance || data.balance;
                    const amount = Math.abs(parseFloat(bal.amount) || 0);
                    const gold = Math.abs(parseFloat(bal.gold) || 0);
                    const silver = Math.abs(parseFloat(bal.silver) || 0);
                    
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
        function plRowCellFloat(row, dataColumn) {
            var cell = row.querySelector('[data-column="' + dataColumn + '"]');
            if (!cell) return 0;
            var inp = cell.querySelector('input');
            if (inp && inp.value != null && String(inp.value).trim() !== '') {
                var v = parseFloat(inp.value);
                if (!isNaN(v)) return v;
            }
            var sel = cell.querySelector('select');
            if (sel && sel.value != null && String(sel.value).trim() !== '') {
                var vs = parseFloat(sel.value);
                if (!isNaN(vs)) return vs;
            }
            var t = parseFloat(String(cell.textContent || '').replace(/,/g, ''));
            return isNaN(t) ? 0 : t;
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
        let totalLessWt = 0;
        let totalPurityDisplay = 0;
        
        rows.forEach(function(row) {
            const qty = plRowCellFloat(row, 'quantity');
            const grossWt = plRowCellFloat(row, 'gross-wt');
            const lessWt = plRowCellFloat(row, 'less-wt');
            const purity = plRowCellFloat(row, 'purity');
            const finalWt = plRowCellFloat(row, 'final-wt');
            const netWt = plRowCellFloat(row, 'net-wt');
            const pureWt = plRowCellFloat(row, 'pure-wt');
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
            totalLessWt += lessWt;
            totalFinalWt += finalWt;
            totalNetWt += netWt;
            totalPurityDisplay += purity;
            totalPureWt += pureWt;
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
        
        var footerLessWtEl = document.getElementById('footerLessWt');
        if (footerLessWtEl && rows.length > 0) {
            footerLessWtEl.textContent = sjFormatWeight(totalLessWt);
        }
        
        // Update grand total footer
        const footer = document.getElementById('productTableFooter');
        if (footer && rows.length > 0) {
            footer.style.display = '';
            document.getElementById('footerQuantity').textContent = totalQuantity.toFixed(2);
            document.getElementById('footerGrossWt').textContent = sjFormatWeight(totalGrossWt);
            document.getElementById('footerFinalWt').textContent = sjFormatWeight(totalFinalWt);
            document.getElementById('footerNetWt').textContent = sjFormatWeight(totalNetWt);
            document.getElementById('footerPureWt').textContent = sjFormatWeight(totalPureWt);
            var footerPurityEl = document.getElementById('footerPurity');
            if (footerPurityEl) {
                var effPurity = (totalNetWt > 0.00001) ? (totalPureWt / totalNetWt) : 0;
                if (effPurity <= 0.00001 && totalPurityDisplay > 0) {
                    footerPurityEl.textContent = totalPurityDisplay.toFixed(2);
                } else {
                    if (effPurity > 1.00001) effPurity = effPurity / 100;
                    footerPurityEl.textContent = effPurity.toFixed(2);
                }
            }
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
                // Update balance
                updateBalance();
                sjUpdateMetalTabsLockFromProductList();
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
            huid_no: row.querySelector('[data-field="huid_no"]')?.value || row.querySelector('[data-column="huid"] input')?.value || '',
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
                    var productListPageBody = document.getElementById('productListBodyPage');
                    // Product Selection on the main page: keep user on the card; do not open the hidden duplicate table in the modal.
                    if (!productListPageBody) {
                        openProductModal();
                    }
                    
                    // Store row ID for updating after save (let + window — selectProduct/Add use the let; edit was only setting window)
                    currentEditingRowId = rowId;
                    window.currentEditingRowId = rowId;
                    
                    var dataBarcode = (row.getAttribute('data-barcode') || '').trim();
                    if (!dataBarcode) {
                        var bcCell = row.querySelector('[data-column="barcode"]');
                        if (bcCell) dataBarcode = (bcCell.textContent || '').trim();
                    }
                    var locId = row.getAttribute('data-location-id') || '';
                    var caratId = row.getAttribute('data-carat-id') || '';
                    var mId = row.getAttribute('data-metal-id') || '';
                    var mName = (row.getAttribute('data-metal-name') || '').trim();
                    var rowPureWt = parseFloat(row.querySelector('[data-field="pure_wt"]')?.value || row.querySelector('[data-column="pure-wt"]')?.textContent?.trim() || 0);
                    
                    // Wait for modal to be fully shown, then add row with data
                    setTimeout(function() {
                        // Clear the same Product Selection tbody addProductRowToSelectionTable will use
                        var stagingTbody = document.getElementById('productListBodyPage') || document.querySelector('#productSelectionModal #productListBody');
                        if (stagingTbody) {
                            stagingTbody.innerHTML = '';
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
                            huid_no: rowData.huid_no,
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
                            location_id: locId,
                            carat_id: caratId,
                            barcode_no: dataBarcode,
                            metal_id: mId,
                            metal_name: mName,
                            metal_weight: rowData.gross_wt,
                            metal_qty: rowData.quantity,
                            purity_weight: isNaN(rowPureWt) ? 0 : rowPureWt
                        };
                        
                        // Create product object
                        const product = {
                            id: data.product.id,
                            name: data.product.name,
                            characteristic_id: data.product.characteristic_id || '',
                            metal_id: mId || data.product.metal_id,
                            metal_name: mName,
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
                            
                            const addedRow = stagingTbody && stagingTbody.querySelector('.product-row');
                            if (addedRow) {
                                addedRow.setAttribute('data-staging-edit-target-row-id', rowId);
                                var plPfx = (row.getAttribute('data-barcode-prefix') || '').trim();
                                var plDigs = parseInt(row.getAttribute('data-barcode-digits'), 10) || 0;
                                if (!plPfx && dataBarcode) {
                                    var pz0 = (typeof sjParseBarcodeStringPrefixDigits === 'function') ? sjParseBarcodeStringPrefixDigits(dataBarcode) : { prefix: '', digits: 0 };
                                    if (pz0.prefix) { plPfx = pz0.prefix; if (!plDigs && pz0.digits) plDigs = pz0.digits; }
                                }
                                if (plPfx) {
                                    addedRow.setAttribute('data-barcode-prefix', plPfx);
                                    if (plDigs > 0) addedRow.setAttribute('data-barcode-digits', String(plDigs));
                                }
                                addedRow.classList.add('selected');
                                addedRow.style.backgroundColor = '#fff3cd';
                            }
                            if (productListPageBody) {
                                try {
                                    var hostCard = productListPageBody.closest && productListPageBody.closest('.card');
                                    (hostCard || productListPageBody).scrollIntoView({ block: 'start', behavior: 'smooth' });
                                } catch (eScroll) {}
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
            sjUpdateMetalTabsLockFromProductList();
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
            window.currentEditingRowId = null;
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
                window.currentEditingRowId = null;
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
                    window.currentEditingRowId = null;
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
            window.currentEditingRowId = null;
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
            const inputEl = this;
            searchTimeout = setTimeout(function() {
                if (currentMetalId) {
                    loadProducts(currentMetalId, search, inputEl);
                }
            }, 300); // Debounce search
        });
    }
    
    // Add (Shift+A) — Product Selection lives on the main card (#productListBodyPage) or in #productSelectionModal (#productListBody). The handler must use the same tbody as the clicked button.
    const modalAddBtn = document.getElementById('modalAddBtn');
    const modalAddBtn2 = document.getElementById('modalAddBtn2');
    async function handleStockJournalModalAddClick(ev) {
            if (window.__sjModalAddInProgress) return;
            window.__sjModalAddInProgress = true;
            const triggerEl = ev && ev.currentTarget ? ev.currentTarget : null;
            var addButtons = document.querySelectorAll('#addProductRowBtnPage, #addProductRowBtnModal, .sj-add-product-row-btn');
            addButtons.forEach(function(btn) { btn.disabled = true; });
            if (typeof sjShowStockJournalAddLoader === 'function') sjShowStockJournalAddLoader();
            try {
            if (typeof sjAwaitBarcodeQueue === 'function') {
                await sjAwaitBarcodeQueue();
            }
            console.log('Modal Add button clicked');
            const tbody = typeof getProductSelectionListTbody === 'function'
                ? getProductSelectionListTbody(triggerEl)
                : (document.getElementById('productListBodyPage') || document.querySelector('#productSelectionModal #productListBody'));
            if (!tbody) {
                console.error('Product selection tbody not found');
                alert('Product list table not found');
                return;
            }
            
            // Get all product rows (not just checked ones, and exclude empty message rows)
            const allProductRows = tbody.querySelectorAll('tr.product-row:not(.no-drag)');
            const firstStaging = allProductRows.length > 0 ? allProductRows[0] : null;
            var fromAttr = (firstStaging && firstStaging.getAttribute) ? String(firstStaging.getAttribute('data-staging-edit-target-row-id') || '').trim() : '';
            var editingId = ((typeof currentEditingRowId !== 'undefined' && currentEditingRowId) ? String(currentEditingRowId).trim() : '') || (typeof window !== 'undefined' && window.currentEditingRowId ? String(window.currentEditingRowId).trim() : '') || fromAttr;
            if (editingId) {
                if (typeof currentEditingRowId !== 'undefined') currentEditingRowId = editingId;
                if (typeof window !== 'undefined') window.currentEditingRowId = editingId;
            }
            // Stock journal edit mode: do not add new rows; only allow updating existing row
            if (window.STOCK_JOURNAL_EDIT_MODE && !editingId) {
                alert('Add is disabled in edit mode. You can only update existing records.');
                return;
            }
            console.log('Found product rows:', allProductRows.length, 'editingId:', editingId);
            
            // Check if we're in edit mode (editing a row in the product list) — use let, window, or staging tr attribute
            if (editingId) {
                console.log('Edit mode - updating row:', editingId);
                // Edit mode: Use first row if available
                const selectedRow = allProductRows.length > 0 ? allProductRows[0] : null;
                if (selectedRow) {
                    try {
                        await updateProductListRowFromModalRow(editingId, selectedRow);
                        if (selectedRow.removeAttribute) {
                            try { selectedRow.removeAttribute('data-staging-edit-target-row-id'); } catch (e) {}
                        }
                        // Close modal
                        hideProductModal();
                        // Clear editing state (hideProductModal also clears; keep in sync for window)
                        currentEditingRowId = null;
                        window.currentEditingRowId = null;
                        // Update summary
                        updateSummaryPanel();
                        // Update balance
                        updateBalance();
                        return;
                    } catch (error) {
                        console.error('Error updating product row:', error);
                        alert('Error updating product: ' + (error.message || 'Unknown error'));
                        return;
                    }
                } else {
                    alert('No product row selected for editing');
                    return;
                }
            }
            
            // Add mode: Add all product rows sequentially
            if (allProductRows.length === 0) {
                alert('No products in the list to add. Please add a product row first using the "Add Product" button.');
                return;
            }
            
            const selectedRows = Array.from(allProductRows).filter(function(row) { 
                return row !== null && row.classList.contains('product-row'); 
            });
            
            console.log('Selected rows to add:', selectedRows.length);
            
            if (selectedRows.length === 0) {
                alert('No valid product rows found to add');
                return;
            }
            
            // Restrict: total qty and gross wt being added must not exceed balance (when item_id is set)
            if (typeof stockDetailItem !== 'undefined' && stockDetailItem) {
                const balance = getBalanceQtyAndWeight(null);
                if (balance) {
                    let sumQty = 0, sumWt = 0;
                    const getVal = function(row, col) {
                        const cell = row.querySelector('[data-column="' + col + '"]');
                        if (!cell) return 0;
                        const input = cell.querySelector('input');
                        return input && input.value ? (parseFloat(input.value) || 0) : 0;
                    };
                    const getRowQty = function(row) {
                        var mq = getVal(row, 'metal-qty');
                        var q = getVal(row, 'quantity');
                        return (Math.abs(mq) > 1e-9) ? mq : q;
                    };
                    const getRowWeight = function(row) {
                        var g = getVal(row, 'gross-wt');
                        var m = getVal(row, 'metal-weight');
                        return m > 0 ? m : (g > 0 ? g : 0);
                    };
                    selectedRows.forEach(function(row) {
                        sumQty += getRowQty(row);
                        sumWt += getRowWeight(row);
                    });
                    if (sumQty > balance.balanceQty + SJ_BALANCE_QTY_EPS) {
                        alert('Cannot add: Total quantity (' + sumQty.toFixed(2) + ') exceeds balance quantity (' + balance.balanceQty.toFixed(2) + '). Total qty: ' + balance.totalQty.toFixed(2) + '. You cannot add more than the balance.');
                        return;
                    }
                    if (sumWt > balance.balanceGrossWt + SJ_BALANCE_WT_EPS) {
                        alert('Cannot add: Total gross weight (' + sjFormatWeight(sumWt) + ') exceeds balance gross weight (' + sjFormatWeight(balance.balanceGrossWt) + '). Total gross wt: ' + sjFormatWeight(balance.totalGrossWt) + '. You cannot add more than the balance.');
                        return;
                    }
                }
            }
            
            let errorCount = 0;
            for (let i = 0; i < selectedRows.length; i++) {
                try {
                    await selectProduct(selectedRows[i], false);
                    console.log('Product ' + (i + 1) + ' added successfully');
                } catch (error) {
                    errorCount++;
                    console.error('Error adding product ' + (i + 1) + ':', error);
                }
            }
            if (errorCount > 0) {
                alert('Added ' + (selectedRows.length - errorCount) + ' products. ' + errorCount + ' products failed to add.');
            }
            updateSummaryPanel();
            setTimeout(function() {
                if (tbody) {
                    const wtInp = tbody.querySelector('[data-column="metal-weight"] input') || tbody.querySelector('[data-column="gross-wt"] input');
                    if (wtInp) {
                        wtInp.focus();
                        if (typeof wtInp.select === 'function') wtInp.select();
                    }
                }
            }, 150);
            } finally {
                window.__sjModalAddInProgress = false;
                addButtons.forEach(function(btn) { btn.disabled = false; });
                if (typeof sjHideStockJournalAddLoader === 'function') sjHideStockJournalAddLoader();
            }
    }
    if (modalAddBtn) modalAddBtn.addEventListener('click', handleStockJournalModalAddClick);
    if (modalAddBtn2) modalAddBtn2.addEventListener('click', handleStockJournalModalAddClick);
    if (!modalAddBtn && !modalAddBtn2) {
        console.error('modalAddBtn / modalAddBtn2 not found');
    }
    
    // Keyboard shortcut for Add in modal (Shift + A) - disabled in stock journal edit mode
    document.addEventListener('keydown', function(e) {
        if (window.STOCK_JOURNAL_EDIT_MODE && !currentEditingRowId && !window.currentEditingRowId) return;
        const modal = document.getElementById('productSelectionModal');
        if (modal && (modal.classList.contains('show') || modal.style.display === 'block')) {
            if (e.shiftKey && e.key === 'A') {
                e.preventDefault();
                if (modalAddBtn2) modalAddBtn2.click();
                else if (modalAddBtn) modalAddBtn.click();
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
                    // Add 1 for print barcode column (always visible)
                    emptyRowCell.setAttribute('colspan', visibleColumns + 1);
                }
            });
        });
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
        // On stock journal, Product Selection is on the main card (#productListBodyPage). That node must win over
        // #productListBody in #productSelectionModal (always in the DOM) or rows appear in a hidden table.
        const tbody = document.getElementById('productListBodyPage') || document.querySelector('#productSelectionModal #productListBody');
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
        row.setAttribute('data-metal-id', item.metal_id || (product && product.metal_id) || '');
        row.setAttribute('data-metal-name', String(item.metal_name || (product && product.metal_name) || '').trim());
        if (item.making_rate != null && item.making_rate !== '' && parseFloat(item.making_rate) > 0) {
            row.setAttribute('data-purchase-making-rate', String(parseFloat(item.making_rate)));
        }
        
        // Generate the row HTML with all columns (order matches common-modal thead / finishAddEmptyProductRow)
        var itemPhotoUrl = (product && (product.photo_url || product.image || product.thumbnail)) ? String(product.photo_url || product.image || product.thumbnail) : '';
        var itemPhotoThumbHtml = itemPhotoUrl
            ? '<img src="' + itemPhotoUrl.replace(/"/g, '&quot;').replace(/</g, '') + '" alt="" class="product-photo-thumb" style="max-width: 50px; max-height: 50px; display: block;"><span class="product-photo-placeholder text-muted" style="font-size: 0.65rem; display: none;">—</span>'
            : '<img src="" alt="" class="product-photo-thumb" style="max-width: 50px; max-height: 50px; display: none;"><span class="product-photo-placeholder text-muted" style="font-size: 0.65rem;">—</span>';
        row.innerHTML = `
            <td data-column="id" style="position: sticky; left: 0; background: #fff; z-index: 1; box-shadow: 1px 0 0 #e2e8f0;">${item.product_id || ''}</td>
            <td data-column="rfid"><input type="text" class="form-control form-control-sm" value="${escapeHtml(item.rfid_code || '')}" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="voucher-type"><input type="text" class="form-control form-control-sm" value="${escapeHtml(item.voucher_type_id || '')}" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="photo" class="product-row-photo" style="text-align: center; vertical-align: middle; min-width: 70px;">${itemPhotoThumbHtml}</td>
            <td data-column="barcode" style="position: relative;">
                <div style="display: flex; align-items: center; gap: 5px;">
                    <input type="text" class="form-control form-control-sm barcode-input" value="${escapeHtml(item.barcode_no || '')}" style="width: 100px; font-size: 0.7rem;" title="Must match product prefix + digit count when edited manually." onclick="printBarcode(this.value, event)">
                    <i class="feather icon-printer barcode-print-icon" style="cursor: pointer; font-size: 0.9rem; color: #c5a864; flex-shrink: 0;" onclick="printBarcode(this.previousElementSibling.value, event)" title="Print Barcode"></i>
                </div>
            </td>
            <td data-column="design-no"><input type="text" class="form-control form-control-sm" value="${escapeHtml(item.design_no || '')}" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="huid"><input type="text" class="form-control form-control-sm" value="${escapeHtml(item.huid_no || '')}" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="item-code"><input type="text" class="form-control form-control-sm" value="${escapeHtml(item.item_code != null && item.item_code !== '' ? item.item_code : (item.short_code || ''))}" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="category"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="">Select</option></select></td>
            <td data-column="product-category"><select class="form-control form-control-sm product-category-select" style="width: 120px; font-size: 0.7rem;"><option value="">Select Category</option></select></td>
            <td data-column="calculation"><select class="form-control form-control-sm" style="width: 120px; font-size: 0.7rem;">${buildCalculationSelectOptions(item.calculation || '')}</select></td>
            <td data-column="product"><input type="text" class="form-control form-control-sm" value="${escapeHtml(product.name || '')}" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="location"><select class="form-control form-control-sm location-select" style="width: 100px; font-size: 0.7rem;"><option value="">Select</option></select></td>
            <td data-column="pkt-wt"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.pkt_weight || 0).toFixed(3)}" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="pkt-less-wt"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.pkt_less_weight || 0).toFixed(3)}" step="0.001" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="gross-wt"><input type="text" class="form-control form-control-sm" value="${sjFormatWeight(item.gross_weight || 0)}" step="0.000001" style="width: 80px; font-size: 0.7rem;"></td>
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
            <td data-column="metal-qty"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.metal_qty != null && item.metal_qty !== '' ? item.metal_qty : (item.quantity || 1)).toFixed(2)}" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="metal-weight"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.metal_weight || 0).toFixed(3)}" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="carat"><select class="form-control form-control-sm carat-select" style="width: 80px; font-size: 0.7rem;"><option value="">Select</option></select></td>
            <td data-column="purity"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.purity || 1).toFixed(2)}" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="purity-wt"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.purity_weight || 0).toFixed(3)}" step="0.001" readonly style="width: 90px; font-size: 0.7rem;"></td>
            <td data-column="gold-loss1"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.gold_loss1 || 0).toFixed(3)}" step="0.001" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="gold-loss2"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.gold_loss2 || 0).toFixed(2)}" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="metal-loss-value"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.metal_loss_value || 0).toFixed(3)}" step="0.001" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="wastage-per"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.wastage_per || 0).toFixed(2)}" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="wastage-wt"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.wastage_weight || 0).toFixed(3)}" step="0.001" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="metal-rate"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.metal_rate || 0).toFixed(2)}" step="0.01" style="width: 90px; font-size: 0.7rem;"></td>
            <td data-column="metal-value"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.metal_value || 0).toFixed(2)}" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="purchase-rate"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.purchase_rate != null && item.purchase_rate !== '' ? item.purchase_rate : (item.metal_rate || item.rate || 0)).toFixed(2)}" step="0.01" style="width: 90px; font-size: 0.7rem;"></td>
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
            <td data-column="making-type"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="Fix" ${(item.making_type || 'Fix') === 'Fix' ? 'selected' : ''}>Fix</option><option value="Per Gram" ${item.making_type === 'Per Gram' ? 'selected' : ''}>Per Gram</option><option value="Per Piece" ${item.making_type === 'Per Piece' ? 'selected' : ''}>Per Piece</option><option value="Per Kilogram" ${item.making_type === 'Per Kilogram' ? 'selected' : ''}>Per Kilogram</option><option value="Per Percent" ${(item.making_type || '') === 'Per Percent' || item.making_type === 'Percentage' ? 'selected' : ''}>Per Percent</option><option value="MRP" ${item.making_type === 'MRP' ? 'selected' : ''}>MRP</option><option value="M.KT" ${item.making_type === 'M.KT' ? 'selected' : ''}>M.KT</option></select></td>
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
            <td data-column="purchase-amount"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.purchase_amount || 0).toFixed(2)}" step="0.01" style="width: 130px; font-size: 0.7rem;"></td>
                        <td data-column="sale-percent"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;" placeholder="%" title="Sale % on Purchase Amount"></td>            <td data-column="sale-amount"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.sale_amount || 0).toFixed(2)}" step="0.01" style="width: 110px; font-size: 0.7rem;"></td>
            <td data-column="sale-amount-with"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.sale_amount_with || 0).toFixed(2)}" step="0.01" readonly style="width: 130px; font-size: 0.7rem;"></td>
            <td data-column="net-amt"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.net_amount || 0).toFixed(2)}" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
            <td data-column="tax-type"><select class="form-control form-control-sm" style="width: 120px; font-size: 0.7rem;">${buildSJTaxTypeSelectHtml(item.tax_type)}</select></td>
            <td data-column="tax-percent"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.tax_percent != null ? item.tax_percent : item.tax_percentage != null ? item.tax_percentage : 5).toFixed(2)}" step="0.01" style="width: 70px; font-size: 0.7rem;"></td>
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
            <td data-column="net-amt-tax"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.net_amount_tax != null && item.net_amount_tax !== '' ? item.net_amount_tax : (item.net_amount_with_tax != null && item.net_amount_with_tax !== '' ? item.net_amount_with_tax : 0)).toFixed(2)}" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
            <td data-column="reverse"><input type="text" class="form-control form-control-sm" value="${parseFloat(item.reverse || 0).toFixed(2)}" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
            <td data-column="images" class="stock-journal-images-cell" style="vertical-align: middle;">
                <div class="sj-images-wrap">
                    <input type="file" class="sj-image-input" accept=".jpg,.jpeg,.png,.webp" multiple style="display:none;">
                    <button type="button" class="btn btn-sm btn-outline-secondary sj-image-btn" style="font-size:0.7rem; padding:2px 6px; white-space:nowrap;" title="Add images (jpg, png, webp, max 2MB)"><i class="feather icon-upload" style="vertical-align:middle;"></i> Add</button>
                    <div class="sj-image-previews"></div>
                </div>
            </td>
            <td data-column="actions" style="text-align: center;">
                <i class="feather icon-edit" style="cursor: pointer; font-size: 0.8rem; color: #c5a864; margin-right: 8px;" onclick="editProductRowInTable(this)" title="Edit Row"></i>
                <i class="feather icon-trash-2" style="cursor: pointer; font-size: 0.8rem; color: #ef4444;" onclick="deleteProductRowFromModal(this)" title="Delete Row"></i>
            </td>
        `;
        
        // Append row to tbody
        tbody.appendChild(row);
        initStockJournalImageCell(row.querySelector('[data-column="images"]'));
        if (typeof reorderModalRowCellsToMatchHeader === 'function') {
            reorderModalRowCellsToMatchHeader(row);
        }
        if (typeof applyProductModalColumnVisibilityForTab === 'function' && (tbody.id === 'productListBody' || tbody.id === 'productListBodyPage' || (tbody && tbody.closest && tbody.closest('#productSelectionModal')))) {
            applyProductModalColumnVisibilityForTab((typeof currentMetalId !== 'undefined' ? currentMetalId : null) || item.metal_id || (product && product.metal_id) || '');
        }
        console.log('Row appended to productListBody. Total rows now:', tbody.querySelectorAll('tr').length);
        
        // Populate dropdowns (location, carat, etc.)
        const caratSelect = row.querySelector('.carat-select');
        if (caratSelect && typeof populateSelect === 'function') {
            populateSelect(caratSelect, carats, 'id', 'name', 'Select Karat');
            sjApplyCaratSelectFromProduct(row, item);
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
            var pcid2 = item.product_category_id != null && item.product_category_id !== '' ? item.product_category_id : (item.category_id != null && item.category_id !== '' && !item.diamond_category ? item.category_id : '');
            if (pcid2) {
                try { productCategorySelect.value = String(pcid2); } catch (e) {}
            }
        }
        if (typeof auragoldPopulateModalSpecSelectsForRow === 'function') {
            auragoldPopulateModalSpecSelectsForRow(row);
            (function() {
                var specSet2 = function(col, id) {
                    if (id == null || id === '') return;
                    var c = row.querySelector('[data-column="' + col + '"] select');
                    if (c) try { c.value = String(id); } catch (e2) {}
                };
                specSet2('cut', item.cut_id);
                specSet2('color', item.color_id);
                specSet2('shape', item.shape_id);
                specSet2('clarity', item.clarity_id);
                specSet2('seive-size', item.sieve_size_id);
                specSet2('size', item.size_id);
            })();
        }
        
        const categorySelect = row.querySelector('[data-column="category"] select');
        if (categorySelect) {
            var dTabRow = typeof isDiamondTabActive === 'function' && isDiamondTabActive();
            if (typeof populateCategorySelectForModal === 'function') {
                populateCategorySelectForModal(categorySelect, dTabRow);
            } else if (typeof populateSelect === 'function' && typeof categories !== 'undefined') {
                populateSelect(categorySelect, categories, 'id', 'name', 'Select Category');
                categorySelect.classList.add('category-select');
            }
            sjApplyModalCategoryFromProduct(categorySelect, {
                category_id: item.category_id != null ? item.category_id : (product && product.category_id),
                diamond_category: item.diamond_category != null ? item.diamond_category : (product && product.diamond_category)
            });
            var calcRow = row.querySelector('[data-column="calculation"] select');
            if (calcRow && typeof applyCalculationSelectOptionsForRow === 'function' && typeof isDiamondTabActive === 'function') {
                applyCalculationSelectOptionsForRow(calcRow, row, isDiamondTabActive());
            }
        }
        
        // Add row double-click handler to edit row
        row.addEventListener('dblclick', function(e) {
            if (e.target.closest('[data-column="actions"]') ||
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
        
        row.addEventListener('click', function(e) {
            if (e.target.closest('[data-column="product"]') || e.target.closest('[data-column="actions"]') ||
                e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT' || e.target.tagName === 'TEXTAREA' ||
                e.target.closest('input') || e.target.closest('select') || e.target.closest('textarea') || e.target.closest('button') || e.target.closest('a')) {
                if (e.target.closest('[data-column="product"]') && !sjProductOpeningLockProductField()) {
                    openProductSearchModal(row);
                }
                return;
            }
            updateRowSelection(row, !row.classList.contains('selected'));
        });
        row.style.cursor = 'pointer';
        
        if (typeof addModalRowCalculationListeners === 'function') {
            addModalRowCalculationListeners(row);
        }
        
        const productInput = row.querySelector('[data-column="product"] input');
        if (productInput) {
            productInput.readOnly = true;
            if (sjProductOpeningLockProductField()) {
                productInput.style.cursor = 'default';
            } else {
                productInput.addEventListener('click', function(e) {
                    e.stopPropagation();
                    openProductSearchModal(row);
                });
                productInput.style.cursor = 'pointer';
            }
        }
        
        function sjAfterDashboardRatesForRow() {
            if (typeof calculateModalRowNetWeight === 'function') {
                calculateModalRowNetWeight(row);
            }
        }
        if (item.carat_id && typeof window.applyDashboardMetalRateFromCaratSelect === 'function') {
            window.applyDashboardMetalRateFromCaratSelect(row, sjAfterDashboardRatesForRow);
        } else {
            sjAfterDashboardRatesForRow();
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
        const productListBody = document.querySelector('#productSelectionModal #productListBody');
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
                    metal_id: item.metal_id || '',
                    metal_name: item.metal_name || '',
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
                    if (item.metal_id) lastRow.setAttribute('data-metal-id', item.metal_id);
                    if (item.metal_name) lastRow.setAttribute('data-metal-name', String(item.metal_name).trim());
                    lastRow.setAttribute('data-purity', parseFloat(item.purity || 0));
                    lastRow.setAttribute('data-rate', parseFloat(item.rate || 0));
                    
                    // Update editable fields
                    const quantityInput = lastRow.querySelector('[data-field="quantity"]');
                    if (quantityInput) quantityInput.value = parseFloat(item.quantity || 1).toFixed(2);
                    
                    const grossWtInput = lastRow.querySelector('[data-field="gross_wt"]');
                    if (grossWtInput) grossWtInput.value = sjFormatWeight(item.gross_weight || 0);
                    
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

                    const huidInput = lastRow.querySelector('[data-field="huid_no"]');
                    if (huidInput) huidInput.value = item.huid_no || '';
                    
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
            if (typeof sjUpdateMetalTabsLockFromProductList === 'function') sjUpdateMetalTabsLockFromProductList();
        } else {
            // Show empty message
            if (productTableBody) {
                productTableBody.innerHTML = '<tr class="no-drag"><td colspan="34" class="text-center text-muted py-4" id="emptyRowCell">No Rows To Show</td></tr>';
            }
            if (productListBody) {
                productListBody.innerHTML = '<tr><td colspan="104" class="text-center text-muted py-4">Click "Add Product" button to add products for billing...</td></tr>';
            }
            if (typeof sjUpdateMetalTabsLockFromProductList === 'function') sjUpdateMetalTabsLockFromProductList();
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
            // Reload saved column preferences and apply after load so Gold tab shows saved check/uncheck
            loadProductModalColumnPreferences();
            console.log('Product Selection modal opened, checking if items need to be loaded');
            const productListBody = document.querySelector('#productSelectionModal #productListBody');
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
        
        // Extract all data from the row (every data-column on the row, same contract as product modal)
        const rowData = {};
        row.querySelectorAll('td[data-column]').forEach(function (cell) {
            const column = cell.getAttribute('data-column');
            if (!column || column === 'images' || column === 'actions') return;
            const input = cell.querySelector('input');
            const select = cell.querySelector('select');
            if (input) {
                rowData[column] = input.value;
            } else if (select) {
                rowData[column] = select.value;
            } else {
                rowData[column] = cell.textContent.trim();
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
            'metal-value': 'Actual Metal Value',
            'purchase-rate': 'Actual Purchase Rate',
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
            'making-actual-value': 'Actual Making Value',
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
            'reverse': 'Reverse',
            'item-code': 'Item Code',
            'product-category': 'Product category',
            'photo': 'Photo',
            'fc-amount': 'FC Amount',
            'diamond-line-metal-value': 'Metal Value (line)',
            'rapnet-valuation': 'RapNet Valuation',
            'mark-up-amount': 'Mark Up Amt.',
            'mark-up-per': 'Mark Up %',
            'metal-qty': 'Metal Qty',
            'metal-weight': 'Metal Weight',
            'metal-rate': 'Metal Rate',
            'metal-loss-value': 'Loss Value',
            'tax-type': 'Tax Type',
            'tax-percent': 'Tax %',
            'platinum-weight': 'Pt. Wt.',
            'platinum-karat': 'Pt. Karat',
            'platinum-purity': 'Pt. Purity %',
            'platinum-purity-wt': 'Pt. Purity Wt',
            'platinum-rate': 'Pt. Rate',
            'platinum-wastage-per': 'Pt. Wastage %',
            'platinum-wastage-wt': 'Pt. Wastage Wt',
            'platinum-amount': 'Pt. Amount',
            'certificate-amount': 'Certificate Amt.',
            'certificate-no': 'Certificate No.',
            'certificate-link': 'Certificate Link',
            'video-link': 'Video Link',
            'seive-size': 'Seive Size',
            'unit-price': 'Unit Price'
        };
        function sjEditFieldLabel(key) {
            if (fieldLabels[key]) return fieldLabels[key];
            return String(key || '').replace(/-/g, ' ').replace(/\b\w/g, function (ch) { return ch.toUpperCase(); });
        }
        Object.keys(rowData).forEach(function (key) {
            const fieldHtml = `
                    <div class="form-group">
                        <label style="font-size: 0.85rem; font-weight: 500; color: #ffffff; margin-bottom: 5px;">${sjEditFieldLabel(key)}</label>
                        <input type="text" class="form-control form-control-sm" id="edit_${key}" value="${escapeHtml(rowData[key] || '')}" style="font-size: 0.85rem;">
                    </div>
                `;
            formDiv.insertAdjacentHTML('beforeend', fieldHtml);
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
    
    function sjReadProductListBarcodeForSave(barcodeCell) {
        if (!barcodeCell) return '';
        const inp = barcodeCell.querySelector('input.barcode-input, input[type="text"]');
        if (inp && (inp.value || '').trim() !== '') {
            return String(inp.value).trim();
        }
        return String(barcodeCell.textContent || '').replace(/\s+/g, ' ').trim();
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
        
        if (typeof sjValidateProductOpeningTableBalanceForSave === 'function') {
            const bal = sjValidateProductOpeningTableBalanceForSave();
            if (bal && bal.ok === false) {
                alert(bal.message || 'Opening quantity or weight limit exceeded.');
                return;
            }
        }
        
        const products = [];
        const sjSaveRowRefs = [];
        let lineNum = 0;
        productRows.forEach(row => {
            // Skip if it's the empty row message
            if (row.classList.contains('no-drag')) {
                return;
            }
            lineNum++;
            
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
            
            // Barcode: text in Product List, or input in other layouts
            const barcodeCell = row.querySelector('[data-column="barcode"]');
            const barcode = sjReadProductListBarcodeForSave(barcodeCell);
            
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
                karat: row.getAttribute('data-carat-id') || getValue('carat'),
                gross_weight: getValue('gross-wt'),
                less_weight: getValue('less-wt'),
                pkt_wt: getValue('pkt-wt'),
                pkt_less_wt: getValue('pkt-less-wt'),
                category: getValue('category', false),
                calculation: getValue('calculation', false),
                location: getValue('location', false),
                purity: getValue('purity'),
                final_weight: getValue('final-wt'),
                net_weight: getValue('net-wt'),
                pure_weight: getValue('pure-wt'),
                making_type: row.getAttribute('data-making-type') || 'Fix',
                making_rate: parseFloat(row.getAttribute('data-making-rate')) || getValue('making-rate') || 0,
                making_amount: getValue('making-amount'),
                stone_charges: getValue('stone-charges'),
                other_charges: getValue('other-charges'),
                diamond_value: getValue('diamond-value'),
                gemstone_value: getValue('gemstone-value'),
                rate: getValue('rate'),
                metal_value: getValue('metal-value'),
                purchase_rate: parseFloat(row.getAttribute('data-purchase-rate') || '') || 0,
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
                design_no: getValue('design-no', false),
                huid_no: getValue('huid', false)
            };
            const sjTempAttr = row.getAttribute('data-sj-temp-image-paths');
            if (sjTempAttr) {
                try {
                    const parsed = JSON.parse(sjTempAttr);
                    if (Array.isArray(parsed) && parsed.length) {
                        product.temp_image_paths = parsed;
                    }
                } catch (e) {}
            }
            const sjEfAttr = row.getAttribute('data-sj-extra-fields');
            if (sjEfAttr) {
                try {
                    const efParsed = JSON.parse(sjEfAttr);
                    if (efParsed && typeof efParsed === 'object') {
                        product.extra_fields = efParsed;
                    }
                } catch (e) {}
            } else if (typeof window.auragoldCollectExtraFieldsFromRow === 'function') {
                const efRow = window.auragoldCollectExtraFieldsFromRow(row);
                if (efRow && typeof efRow === 'object' && Object.keys(efRow).length) {
                    product.extra_fields = efRow;
                }
            }
            
            products.push(product);
            sjSaveRowRefs.push(row);
        });
        
        if (products.length === 0) {
            alert('No products to save');
            return;
        }
        
        const seenBc = new Set();
        for (let vi = 0; vi < products.length; vi++) {
            const pr = products[vi];
            const pi = parseInt(pr.product_id, 10) || 0;
            const pnm = (pr.product_name != null) ? String(pr.product_name).replace(/\s+/g, ' ').trim() : '';
            const qty = parseFloat(pr.quantity);
            const rateNum = parseFloat(pr.rate);
            const bc = (pr.barcode != null) ? String(pr.barcode).trim() : '';
            if (pi <= 0) {
                alert('Line ' + (vi + 1) + ': product (ID) is missing. Select a valid product line.');
                return;
            }
            if (pnm === '') {
                alert('Line ' + (vi + 1) + ': product name is required.');
                return;
            }
            if (!Number.isFinite(qty) || qty <= 0) {
                alert('Line ' + (vi + 1) + ': quantity must be greater than zero.');
                return;
            }
            if (!Number.isFinite(rateNum)) {
                alert('Line ' + (vi + 1) + ': rate is required (enter a valid number).');
                return;
            }
            if (bc === '') {
                alert('Line ' + (vi + 1) + ': barcode is required. Use edit or re-add the line to assign a barcode.');
                return;
            }
            if (seenBc.has(bc)) {
                alert('Duplicate barcode in this journal: ' + bc + '. Each line must have a unique barcode.');
                return;
            }
            seenBc.add(bc);
            const domRow = sjSaveRowRefs[vi];
            if (domRow) {
                const skipFmt = domRow.getAttribute('data-sj-excel-import') === '1';
                if (!skipFmt) {
                    const pfx = (domRow.getAttribute('data-barcode-prefix') || '').trim();
                    const dig = parseInt(domRow.getAttribute('data-barcode-digits'), 10) || 0;
                    if (pfx && dig > 0 && typeof sjBarcodeMatchesPrefixDigitsStrict === 'function' && !sjBarcodeMatchesPrefixDigitsStrict(bc, pfx, dig)) {
                        alert('Line ' + (vi + 1) + ': barcode "' + bc + '" must start with prefix "' + pfx + '" followed by exactly ' + dig + ' digit(s). Rows loaded from Excel may use any unique barcode from the sheet.');
                        return;
                    }
                }
            }
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
        const isEditMode = window.STOCK_JOURNAL_EDIT_MODE === true;
        
        // Show loading
        const saveBtn = document.getElementById('saveStockJournalBtn');
        const originalText = saveBtn.innerHTML;
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="feather icon-loader"></i> ' + (isEditMode ? 'Updating...' : 'Saving...');
        
        const payload = {
            date: journalDate,
            item_id: itemId,
            products: products,
            group_name: groupName,
            comment: comment,
            edit: isEditMode
        };
        
        const sjRowHasUploadableFile = function(row) {
            const files = window.stockJournalRowImages && window.stockJournalRowImages[row.id];
            if (!files || !files.length) return false;
            for (let j = 0; j < files.length; j++) {
                if (files[j] instanceof File || (typeof Blob !== 'undefined' && files[j] instanceof Blob)) return true;
            }
            return false;
        };
        let hasFileUploads = false;
        productRows.forEach(row => {
            if (row.classList.contains('no-drag')) return;
            if (sjRowHasUploadableFile(row)) hasFileUploads = true;
        });
        
        let fetchOpts = { method: 'POST' };
        if (hasFileUploads) {
            const formData = new FormData();
            formData.append('data', JSON.stringify(payload));
            let idx = 0;
            productRows.forEach(row => {
                if (row.classList.contains('no-drag')) return;
                const files = window.stockJournalRowImages && window.stockJournalRowImages[row.id];
                if (files && files.length) {
                    let uj = 0;
                    files.forEach((file) => {
                        if (file instanceof File || (typeof Blob !== 'undefined' && file instanceof Blob)) {
                            formData.append('images_' + idx + '_' + uj, file);
                            uj++;
                        }
                    });
                }
                idx++;
            });
            fetchOpts.body = formData;
        } else {
            fetchOpts.headers = { 'Content-Type': 'application/json' };
            fetchOpts.body = JSON.stringify(payload);
        }
        
        // Send AJAX request
        fetch('ajax/save-stock-journal.php', fetchOpts)
        .then(response => response.json())
        .then(data => {
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalText;
            
            if (data.status === 'success' || data.status === true) {
                const successMsg = data.message || (isEditMode ? 'Stock updated successfully' : 'Stock Journal saved successfully!');
                if (typeof swal === 'function') {
                    swal({
                        title: successMsg,
                        text: 'Do you want to print barcode?',
                        type: 'success',
                        showCancelButton: true,
                        confirmButtonClass: 'btn-primary',
                        confirmButtonText: 'Yes',
                        cancelButtonText: 'No',
                        closeOnConfirm: true,
                        closeOnCancel: true
                    }, function(isConfirm) {
                        if (isConfirm) {
                            const barcodes = products.map(function(p) { return (p.barcode || '').trim(); }).filter(Boolean);
                            if (barcodes.length > 0) {
                                const printUrl = 'barcode-print.php?barcodes=' + encodeURIComponent(barcodes.join(','));
                                window.open(printUrl, '_blank', 'width=1200,height=800');
                            }
                        }
                        if (!isEditMode) {
                            window.location.href = 'stock-journal.php';
                        }
                    });
                } else {
                    alert(successMsg);
                    if (!isEditMode) {
                        window.location.href = 'stock-journal.php';
                    }
                }
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

        document.addEventListener('focus', function(e) {
            var inp = e.target;
            if (!inp || !inp.classList || !inp.classList.contains('barcode-input')) return;
            var tr = inp.closest && inp.closest('#productListBodyPage tr.product-row, #productListBody tr.product-row');
            if (!tr) return;
            inp.setAttribute('data-sj-bc-prev', String(inp.value || ''));
        }, true);
        document.addEventListener('blur', function(e) {
            var inp = e.target;
            if (!inp || !inp.classList || !inp.classList.contains('barcode-input')) return;
            var tr = inp.closest && inp.closest('#productListBodyPage tr.product-row, #productListBody tr.product-row');
            if (!tr) return;
            var pfx = (tr.getAttribute('data-barcode-prefix') || '').trim();
            var dig = parseInt(tr.getAttribute('data-barcode-digits'), 10) || 0;
            var v = String(inp.value || '').trim();
            if (v === '') return;
            if (typeof sjBarcodeMatchesPrefixDigitsStrict === 'function' && !sjBarcodeMatchesPrefixDigitsStrict(v, pfx, dig)) {
                alert('Barcode must start with "' + (pfx || '(prefix)') + '" and have exactly ' + (dig > 0 ? dig : 'N') + ' digit(s) after the prefix for this product.');
                inp.value = inp.getAttribute('data-sj-bc-prev') || '';
            } else {
                tr.setAttribute('data-barcode', v);
                var esc = v.replace(/'/g, "\\'");
                inp.setAttribute('onclick', "printBarcode('" + esc + "', event)");
                var printIcon = tr.querySelector('.barcode-print-icon');
                if (printIcon) printIcon.setAttribute('onclick', "printBarcode('" + esc + "', event)");
            }
        }, true);
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
            if (row && typeof sjProductListRowMetalDisplayName === 'function') {
                metalType = sjProductListRowMetalDisplayName(row) || '';
            } else if (row && typeof sjRowMetalDisplayName === 'function') {
                metalType = sjRowMetalDisplayName(row) || '';
            }
        }
        
        var printUrl = 'barcode-print.php?barcode=' + encodeURIComponent(barcode.trim());
        if (metalType) {
            printUrl += '&metal_type=' + encodeURIComponent(metalType);
        }
        window.open(printUrl, '_blank', 'width=800,height=600');
    }
    
    // Get current row data for barcode print (values visible in Product List, before Save)
    function getRowDataForBarcodePrint(row) {
        if (!row) return {};
        const getVal = function(sel, useInput) {
            const el = row.querySelector(sel);
            if (!el) return '';
            if (useInput !== false) {
                const input = el.querySelector && el.querySelector('[data-field]') || el.querySelector('input');
                if (input && input.value !== undefined) return input.value.trim();
            }
            const t = (el.textContent || '').trim();
            return t;
        };
        const grossWt = getVal('[data-column="gross-wt"]');
        const lessWt = getVal('[data-column="less-wt"]');
        const purity = getVal('[data-column="purity"]');
        const finalWt = getVal('[data-column="final-wt"]');
        const netWt = getVal('[data-column="net-wt"]');
        const pureWt = getVal('[data-column="pure-wt"]');
        const descEl = row.querySelector('[data-column="description"] a');
        const productName = (descEl && descEl.textContent) ? descEl.textContent.trim() : getVal('[data-column="description"]');
        const designNo = getVal('[data-column="design-no"]');
        const amount = getVal('[data-column="amount"]');
        const netAmt = getVal('[data-column="net-amt"]');
        const rate = getVal('[data-column="rate"]');
        const makingAmount = getVal('[data-column="making-amount"]');
        const shortCodeCell = row.querySelector('[data-column="item-code"]') || row.querySelector('[data-column="short-code"]');
        let shortCode = '';
        if (shortCodeCell) {
            const shortInput = shortCodeCell.querySelector('input');
            shortCode = shortInput ? shortInput.value.trim() : shortCodeCell.textContent.trim();
        }
        const huidNo = getVal('[data-column="huid"]') || getVal('[data-field="huid_no"]');
        return { gross_wt: grossWt, less_wt: lessWt, purity: purity, final_wt: finalWt, net_wt: netWt, pure_wt: pureWt, product_name: productName, design_no: designNo, huid_no: huidNo, amount: amount, net_amt: netAmt, rate: rate, making_amount: makingAmount, short_code: shortCode };
    }
    
    // Function to print barcode from row (gets barcode and row data from the row)
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
                        // Legacy B+digits or typical stock codes (e.g. NK00002)
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
        
        // Build URL with row data so print shows current values even before Save
        const params = new URLSearchParams();
        params.set('barcode', barcode);
        var metalType = '';
        if (typeof sjProductListRowMetalDisplayName === 'function') {
            metalType = sjProductListRowMetalDisplayName(row) || '';
        } else if (typeof sjRowMetalDisplayName === 'function') {
            metalType = sjRowMetalDisplayName(row) || '';
        }
        if (metalType) {
            params.set('metal_type', metalType);
        }
        const rowData = getRowDataForBarcodePrint(row);
        ['gross_wt', 'less_wt', 'purity', 'final_wt', 'net_wt', 'pure_wt', 'product_name', 'design_no', 'huid_no', 'amount', 'net_amt', 'rate', 'making_amount', 'short_code'].forEach(function(k) {
            const v = rowData[k];
            if (v !== undefined && v !== null && String(v).trim() !== '') params.set(k, String(v).trim());
        });
        const printUrl = 'barcode-print.php?' + params.toString();
        window.open(printUrl, '_blank', 'width=800,height=600');
    }
    
    // Function to print multiple barcodes
    function printMultipleBarcodes() {
        const barcodeInputs = document.querySelectorAll('#productListBody .barcode-input, #productListBodyPage .barcode-input, #productTableBody [data-column="barcode"] input.barcode-input');
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
    
    // Load stock journal items when in edit mode (item_id provided and edit=true, not add mode)
    <?php if ($edit_item_id > 0 && $edit_mode && !$add_mode && !empty($edit_stock_journal_items)): ?>
    $(document).ready(function() {
        const stockJournalItems = <?php echo json_encode($edit_stock_journal_items); ?>;
        
        console.log('Edit mode - Loading stock journal items:', stockJournalItems);
        console.log('Edit mode - Items count:', stockJournalItems ? stockJournalItems.length : 0);
        
        // Function to load items into Product Selection (main card #productListBodyPage and/or #productListBody in modal — same as addProductRowToSelectionTable).
        function loadStockJournalItemsIntoModal() {
            const pageBody = document.getElementById('productListBodyPage');
            const modalBody = document.querySelector('#productSelectionModal #productListBody');
            const productListBody = pageBody || modalBody;
            if (!productListBody) {
                console.error('productListBody / productListBodyPage not found');
                return;
            }
            if (pageBody) {
                pageBody.innerHTML = '';
            }
            if (modalBody) {
                modalBody.innerHTML = '';
            }
            
            if (!stockJournalItems || stockJournalItems.length === 0) {
                const emptyHtml = '<tr><td colspan="104" class="text-center text-muted py-4">No stock journal items found</td></tr>';
                if (pageBody) {
                    pageBody.innerHTML = emptyHtml;
                }
                if (modalBody) {
                    modalBody.innerHTML = emptyHtml;
                }
                return;
            }
            
            // Load each stock journal item into the modal table
            stockJournalItems.forEach(function(sjItem) {
                // Use the addRowFromStockJournal function if it exists
                if (typeof addRowFromStockJournal === 'function') {
                    addRowFromStockJournal(sjItem);
                } else {
                    // Fallback: Create row manually
                    const row = document.createElement('tr');
                    row.className = 'product-row';
                    row.setAttribute('data-product-id', sjItem.product_id || '');
                    row.setAttribute('data-characteristic-id', sjItem.product_characteristic_id || '');
                    row.setAttribute('data-stock-journal-id', sjItem.id || '');
                    row.setAttribute('data-metal-id', sjItem.metal_id || '');
                    row.setAttribute('data-metal-name', (sjItem.metal_name || '').trim());
                    
                    const barcode = sjItem.barcode || '';
                    const sjBcEsc = (barcode || '').replace(/'/g, "\\'");
                    const productName = (sjItem.product_name || '') + (sjItem.metal_name ? ' - ' + sjItem.metal_name : '');
                    
                    // Create row HTML (column order matches common-modal / addProductRowToSelectionTable)
                    row.innerHTML = `
                        <td data-column="id" style="position: sticky; left: 0; background: #fff; z-index: 1; box-shadow: 1px 0 0 #e2e8f0;">${sjItem.product_id || ''}</td>
                        <td data-column="rfid"><input type="text" class="form-control form-control-sm" value="${escapeHtml(sjItem.rfid_code || '')}" style="width: 100px; font-size: 0.7rem;"></td>
                        <td data-column="voucher-type"><input type="text" class="form-control form-control-sm" value="Stock Journal" style="width: 100px; font-size: 0.7rem;"></td>
                        <td data-column="photo" class="product-row-photo" style="text-align: center; vertical-align: middle; min-width: 70px;"><img src="" alt="" class="product-photo-thumb" style="max-width: 50px; max-height: 50px; display: none;"><span class="product-photo-placeholder text-muted" style="font-size: 0.65rem;">—</span></td>
                        <td data-column="barcode" style="position: relative;">
                            <div style="display: flex; align-items: center; gap: 5px;">
                                <input type="text" class="form-control form-control-sm barcode-input" value="${escapeHtml(barcode)}" style="width: 100px; font-size: 0.7rem;" title="Must match product prefix + digit count when edited manually." onclick="printBarcode('${sjBcEsc}', event)">
                                <i class="feather icon-printer barcode-print-icon" style="cursor: pointer; font-size: 0.9rem; color: #c5a864; flex-shrink: 0;" onclick="printBarcode('${sjBcEsc}', event)" title="Print Barcode"></i>
                            </div>
                        </td>
                        <td data-column="design-no"><input type="text" class="form-control form-control-sm" value="${escapeHtml(sjItem.article || '')}" style="width: 100px; font-size: 0.7rem;"></td>
                        <td data-column="huid"><input type="text" class="form-control form-control-sm" value="${escapeHtml(sjItem.huid_no || '')}" style="width: 100px; font-size: 0.7rem;"></td>
                        <td data-column="item-code"><input type="text" class="form-control form-control-sm" value="${escapeHtml(sjItem.item_code != null && sjItem.item_code !== '' ? sjItem.item_code : (sjItem.short_code || sjItem.code || ''))}" style="width: 100px; font-size: 0.7rem;"></td>
                        <td data-column="category"><select class="form-control form-control-sm category-select" style="width: 100px; font-size: 0.7rem;"><option value="">Select</option></select></td>
                        <td data-column="product-category"><select class="form-control form-control-sm product-category-select" style="width: 120px; font-size: 0.7rem;"><option value="">Select Category</option></select></td>
                        <td data-column="calculation"><select class="form-control form-control-sm" style="width: 120px; font-size: 0.7rem;">${buildCalculationSelectOptions(sjItem.calculation || '')}</select></td>
                        <td data-column="product"><input type="text" class="form-control form-control-sm" value="${escapeHtml(productName)}" style="width: 100px; font-size: 0.7rem;"></td>
                        <td data-column="location"><select class="form-control form-control-sm location-select" style="width: 100px; font-size: 0.7rem;"><option value="">Select</option></select></td>
                        <td data-column="pkt-wt"><input type="text" class="form-control form-control-sm" value="${sjItem.pkt_weight || 0}" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
                        <td data-column="pkt-less-wt"><input type="text" class="form-control form-control-sm" value="${sjItem.pkt_less_weight || 0}" step="0.001" style="width: 100px; font-size: 0.7rem;"></td>
                        <td data-column="gross-wt"><input type="text" class="form-control form-control-sm" value="${sjFormatWeight(sjItem.gross_weight || 0)}" step="0.000001" style="width: 80px; font-size: 0.7rem;"></td>
                        <td data-column="stone-weight"><input type="text" class="form-control form-control-sm" value="${sjItem.stone_weight || 0}" step="0.001" style="width: 110px; font-size: 0.7rem;"></td>
                        <td data-column="less-wt"><input type="text" class="form-control form-control-sm" value="${sjItem.less_weight || 0}" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
                        <td data-column="net-wt"><input type="text" class="form-control form-control-sm" value="${sjItem.net_weight || 0}" step="0.001" readonly style="width: 80px; font-size: 0.7rem;"></td>
                        <td data-column="quantity"><input type="text" class="form-control form-control-sm" value="${sjItem.quantity || 1}" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
                        <td data-column="rate"><input type="text" class="form-control form-control-sm" value="${sjItem.rate || 0}" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
                        <td data-column="fc-amount"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.fc_amount != null && sjItem.fc_amount !== '' ? sjItem.fc_amount : 0).toFixed(2)}" step="0.01" style="width: 90px; font-size: 0.7rem;"></td>
                        <td data-column="diamond-line-metal-value"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.diamond_line_metal_value != null && sjItem.diamond_line_metal_value !== '' ? sjItem.diamond_line_metal_value : 0).toFixed(2)}" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                        <td data-column="rapnet-valuation"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.rapnet_valuation != null && sjItem.rapnet_valuation !== '' ? sjItem.rapnet_valuation : 0).toFixed(2)}" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                        <td data-column="setting-charge"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.setting_charge || 0).toFixed(2)}" step="0.01" style="width: 110px; font-size: 0.7rem;"></td>
                        <td data-column="stone-amount"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.stone_amount || 0).toFixed(2)}" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
                        <td data-column="mark-up-amount"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.mark_up_amount != null && sjItem.mark_up_amount !== '' ? sjItem.mark_up_amount : 0).toFixed(2)}" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                        <td data-column="mark-up-per"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.mark_up_per != null && sjItem.mark_up_per !== '' ? sjItem.mark_up_per : 0).toFixed(2)}" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
                        <td data-column="amount"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.amount || 0).toFixed(2)}" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                        <td data-column="metal-qty"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.metal_qty != null && sjItem.metal_qty !== '' ? sjItem.metal_qty : (sjItem.quantity || 1)).toFixed(2)}" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
                        <td data-column="metal-weight"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.metal_weight || 0).toFixed(3)}" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
                        <td data-column="carat"><select class="form-control form-control-sm carat-select" style="width: 80px; font-size: 0.7rem;"><option value="">Select</option></select></td>
                        <td data-column="purity"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.purity || 1).toFixed(2)}" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
                        <td data-column="purity-wt"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.purity_weight || sjItem.pure_weight || 0).toFixed(3)}" step="0.001" readonly style="width: 90px; font-size: 0.7rem;"></td>
                        <td data-column="gold-loss1"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.gold_loss1 || 0).toFixed(3)}" step="0.001" style="width: 100px; font-size: 0.7rem;"></td>
                        <td data-column="gold-loss2"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.gold_loss2 || 0).toFixed(2)}" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                        <td data-column="metal-loss-value"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.metal_loss_value || 0).toFixed(3)}" step="0.001" readonly style="width: 100px; font-size: 0.7rem;"></td>
                        <td data-column="wastage-per"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.wastage_per || 0).toFixed(2)}" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                        <td data-column="wastage-wt"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.wastage_weight || 0).toFixed(3)}" step="0.001" readonly style="width: 100px; font-size: 0.7rem;"></td>
                        <td data-column="metal-rate"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.metal_rate || 0).toFixed(2)}" step="0.01" style="width: 90px; font-size: 0.7rem;"></td>
                        <td data-column="metal-value"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.metal_value || 0).toFixed(2)}" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                        <td data-column="purchase-rate"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.purchase_rate != null && sjItem.purchase_rate !== '' ? sjItem.purchase_rate : (sjItem.metal_rate || sjItem.rate || 0)).toFixed(2)}" step="0.01" style="width: 90px; font-size: 0.7rem;"></td>
                        <td data-column="metal-cost"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.metal_cost || 0).toFixed(2)}" step="0.01" readonly style="width: 100px; font-size: 0.7rem; color: #7b1fa2;"></td>
                        <td data-column="requested-purity"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.requested_purity || 0).toFixed(2)}" step="0.01" style="width: 120px; font-size: 0.7rem;"></td>
                        <td data-column="requested"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.requested || 0).toFixed(3)}" step="0.001" style="width: 100px; font-size: 0.7rem;"></td>
                        <td data-column="final-wt"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.final_weight != null && sjItem.final_weight !== '' ? sjItem.final_weight : (sjItem.gross_weight || 0)).toFixed(3)}" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
                        <td data-column="alloy-wt"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.alloy_weight || 0).toFixed(3)}" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
                        <td data-column="platinum-weight"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.platinum_weight || 0).toFixed(3)}" step="0.001" style="width: 80px; font-size: 0.7rem;"></td>
                        <td data-column="platinum-karat"><input type="text" class="form-control form-control-sm" value="${escapeHtml(sjItem.platinum_karat || '')}" style="width: 80px; font-size: 0.7rem;"></td>
                        <td data-column="platinum-purity"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.platinum_purity || 0).toFixed(2)}" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
                        <td data-column="platinum-purity-wt"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.platinum_purity_wt || 0).toFixed(3)}" step="0.001" style="width: 90px; font-size: 0.7rem;"></td>
                        <td data-column="platinum-rate"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.platinum_rate || 0).toFixed(2)}" step="0.01" style="width: 90px; font-size: 0.7rem;"></td>
                        <td data-column="platinum-wastage-per"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.platinum_wastage_per || 0).toFixed(2)}" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                        <td data-column="platinum-wastage-wt"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.platinum_wastage_wt || 0).toFixed(3)}" step="0.001" style="width: 100px; font-size: 0.7rem;"></td>
                        <td data-column="platinum-amount"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.platinum_amount || 0).toFixed(2)}" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                        <td data-column="discount-type"><select class="form-control form-control-sm" style="width: 150px; font-size: 0.7rem;"><option value="Fix" selected>Fix</option><option value="On Amount">On Amount</option><option value="On Making Amount">On Making Amount</option><option value="On Diamond Amount">On Diamond Amount</option><option value="On Stone Amount">On Stone Amount</option><option value="On Net Amount">On Net Amount</option></select></td>
                        <td data-column="discount-per"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
                        <td data-column="discount-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                        <td data-column="discount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                        <td data-column="making-type"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="Fix" selected>Fix</option><option value="Per Gram">Per Gram</option><option value="Per Piece">Per Piece</option><option value="Per Kilogram">Per Kilogram</option><option value="Per Percent">Per Percent</option><option value="MRP">MRP</option><option value="M.KT">M.KT</option></select></td>
                        <td data-column="making-rate"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                        <td data-column="making-discount-amt"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 130px; font-size: 0.7rem;"></td>
                        <td data-column="making-amount"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.making_amount || 0).toFixed(2)}" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                        <td data-column="making-actual-value"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
                        <td data-column="making-cost"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 110px; font-size: 0.7rem;"></td>
                        <td data-column="min-price"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                        <td data-column="minimum"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                        <td data-column="stone-charge-type"><select class="form-control form-control-sm" style="width: 100px; font-size: 0.7rem;"><option value="Fix" selected>Fix</option><option value="Per Gram">Per Gram</option></select></td>
                        <td data-column="stone-rate"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 100px; font-size: 0.7rem;"></td>
                        <td data-column="stone-cost"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                        <td data-column="diamond-amount"><input type="text" class="form-control form-control-sm" value="0.00" step="0.01" style="width: 120px; font-size: 0.7rem;"></td>
                        <td data-column="purchase-amount"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.purchase_amount != null && sjItem.purchase_amount !== '' ? sjItem.purchase_amount : 0).toFixed(2)}" step="0.01" style="width: 130px; font-size: 0.7rem;"></td>
                                    <td data-column="sale-percent"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;" placeholder="%" title="Sale % on Purchase Amount"></td>            <td data-column="sale-amount"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.net_amount || 0).toFixed(2)}" step="0.01" style="width: 110px; font-size: 0.7rem;"></td>
                        <td data-column="sale-amount-with"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.sale_amount_with != null && sjItem.sale_amount_with !== '' ? sjItem.sale_amount_with : (sjItem.net_amt_with_tax || 0)).toFixed(2)}" step="0.01" readonly style="width: 130px; font-size: 0.7rem;"></td>
                        <td data-column="net-amt"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.net_amount || 0).toFixed(2)}" step="0.01" readonly style="width: 100px; font-size: 0.7rem;"></td>
                        <td data-column="tax-type"><select class="form-control form-control-sm" style="width: 120px; font-size: 0.7rem;">${buildSJTaxTypeSelectHtml(sjItem.tax_type)}</select></td>
                        <td data-column="tax-percent"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.tax_percent != null ? sjItem.tax_percent : sjItem.tax_percentage != null ? sjItem.tax_percentage : 5).toFixed(2)}" step="0.01" style="width: 70px; font-size: 0.7rem;"></td>
                        <td data-column="tax"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.tax_amount || 0).toFixed(2)}" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
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
                        <td data-column="net-amt-tax"><input type="text" class="form-control form-control-sm" value="${parseFloat(sjItem.net_amount_tax != null && sjItem.net_amount_tax !== '' ? sjItem.net_amount_tax : (sjItem.net_amt_with_tax != null && sjItem.net_amt_with_tax !== '' ? sjItem.net_amt_with_tax : 0)).toFixed(2)}" step="0.01" readonly style="width: 120px; font-size: 0.7rem;"></td>
                        <td data-column="reverse"><input type="text" class="form-control form-control-sm" value="0" step="0.01" style="width: 80px; font-size: 0.7rem;"></td>
                        <td data-column="images" class="stock-journal-images-cell" style="vertical-align: middle;">
                            <div class="sj-images-wrap">
                                <input type="file" class="sj-image-input" accept=".jpg,.jpeg,.png,.webp" multiple style="display:none;">
                                <button type="button" class="btn btn-sm btn-outline-secondary sj-image-btn" style="font-size:0.7rem; padding:2px 6px; white-space:nowrap;" title="Add images (jpg, png, webp, max 2MB)"><i class="feather icon-upload" style="vertical-align:middle;"></i> Add</button>
                                <div class="sj-image-previews"></div>
                            </div>
                        </td>
                        <td data-column="actions" style="text-align: center;">
                            <i class="feather icon-edit" style="cursor: pointer; font-size: 0.8rem; color: #c5a864; margin-right: 8px;" onclick="editProductRowInTable(this)" title="Edit Row"></i>
                            <i class="feather icon-trash-2" style="cursor: pointer; font-size: 0.8rem; color: #ef4444;" onclick="deleteProductRowFromModal(this)" title="Delete Row"></i>
                        </td>
                    `;
                    
                    productListBody.appendChild(row);
                    if (typeof reorderModalRowCellsToMatchHeader === 'function') reorderModalRowCellsToMatchHeader(row);
                    if (typeof applyProductModalColumnVisibilityForTab === 'function' && (productListBody.id === 'productListBody' || productListBody.id === 'productListBodyPage' || (productListBody && productListBody.closest && productListBody.closest('#productSelectionModal')))) {
                        applyProductModalColumnVisibilityForTab(typeof currentMetalId !== 'undefined' ? (currentMetalId || '') : (sjItem.metal_id || ''));
                    }
                    var sjPcat = row.querySelector('.product-category-select');
                    if (sjPcat && typeof populateSelect === 'function' && typeof categories !== 'undefined') {
                        populateSelect(sjPcat, categories, 'id', 'name', 'Select Category');
                        var sjPcid = sjItem.product_category_id != null && sjItem.product_category_id !== '' ? sjItem.product_category_id : (sjItem.category_id != null && sjItem.category_id !== '' && !sjItem.diamond_category ? sjItem.category_id : '');
                        if (sjPcid) try { sjPcat.value = String(sjPcid); } catch (e) {}
                    }
                    if (typeof auragoldPopulateModalSpecSelectsForRow === 'function') auragoldPopulateModalSpecSelectsForRow(row);
                    const imgCell = row.querySelector('[data-column="images"]');
                    if (imgCell) initStockJournalImageCell(imgCell);
                    
                    // Initialize dropdowns and event listeners for this row
                    setTimeout(function() {
                        // Populate category (diamond tab → Diamonds / GemStones / Jewellery)
                        const categorySelect = row.querySelector('[data-column="category"] select');
                        if (categorySelect) {
                            var dTabSj = typeof isDiamondTabActive === 'function' && isDiamondTabActive();
                            if (typeof populateCategorySelectForModal === 'function') {
                                populateCategorySelectForModal(categorySelect, dTabSj);
                            } else if (typeof categories !== 'undefined' && typeof populateSelect === 'function') {
                                populateSelect(categorySelect, categories, 'id', 'name', 'Select Category');
                            }
                            sjApplyModalCategoryFromProduct(categorySelect, sjItem);
                            var calcSj = row.querySelector('[data-column="calculation"] select');
                            if (calcSj && typeof applyCalculationSelectOptionsForRow === 'function' && typeof isDiamondTabActive === 'function') {
                                applyCalculationSelectOptionsForRow(calcSj, row, isDiamondTabActive());
                            }
                        }
                        
                        // Populate location dropdown
                        const locationSelect = row.querySelector('[data-column="location"] select');
                        if (locationSelect && typeof locations !== 'undefined') {
                            populateSelect(locationSelect, locations, 'id', 'name', 'Select Location');
                        }
                        
                        // Populate carat dropdown
                        const caratSelect = row.querySelector('[data-column="carat"] select');
                        if (caratSelect && typeof carats !== 'undefined') {
                            populateSelect(caratSelect, carats, 'id', 'name', 'Select Karat');
                            sjApplyCaratSelectFromProduct(row, sjItem);
                        }
                        
                        // Add calculation listeners
                        if (typeof addModalRowCalculationListeners === 'function') {
                            addModalRowCalculationListeners(row);
                        }
                        
                        // Calculate initial values
                        if (typeof calculateModalRowNetWeight === 'function') {
                            calculateModalRowNetWeight(row);
                        }
                    }, 100);
                }
            });
            if (pageBody && modalBody && productListBody === pageBody) {
                setTimeout(function () {
                    try {
                        modalBody.innerHTML = '';
                        pageBody.querySelectorAll('tr.product-row').forEach(function (srcRow) {
                            modalBody.appendChild(srcRow.cloneNode(true));
                        });
                        modalBody.querySelectorAll('td[data-column="images"].stock-journal-images-cell, td[data-column="images"]').forEach(function (cell) {
                            if (typeof initStockJournalImageCell === 'function') initStockJournalImageCell(cell);
                        });
                        if (typeof window.runStockJournalColumnDragInit === 'function') {
                            window.runStockJournalColumnDragInit();
                        }
                        if (typeof runStockJournalProductRowAlignmentPipeline === 'function') {
                            runStockJournalProductRowAlignmentPipeline();
                        }
                    } catch (eMir) {
                        if (typeof console !== 'undefined') console.warn('Sync modal product rows from page', eMir);
                    }
                }, 300);
            } else {
                setTimeout(function () {
                    if (typeof window.runStockJournalColumnDragInit === 'function') {
                        window.runStockJournalColumnDragInit();
                    }
                    if (typeof runStockJournalProductRowAlignmentPipeline === 'function') {
                        runStockJournalProductRowAlignmentPipeline();
                    }
                }, 0);
            }
        }
        
        // Load items after DOM is ready
        setTimeout(function() {
            loadStockJournalItemsIntoModal();
            
            // Update balance after loading items
            setTimeout(function() {
                updateBalance();
                if (typeof sjUpdateMetalTabsLockFromProductList === 'function') sjUpdateMetalTabsLockFromProductList();
            }, 1000);
            
            // Also load items when Product Selection modal is opened (in case modal wasn't ready when page loaded)
            $('#productSelectionModal').on('shown.bs.modal', function() {
                loadProductModalColumnPreferences();
                const modalTbody = document.querySelector('#productSelectionModal #productListBody');
                if (modalTbody && modalTbody.querySelectorAll('tr.product-row').length === 0 && stockJournalItems && stockJournalItems.length > 0) {
                    loadStockJournalItemsIntoModal();
                    setTimeout(function() {
                        updateBalance();
                    }, 500);
                }
            });
        }, 500);
    });
    <?php endif; ?>
    
    // Initialize balance on page load
    $(document).ready(function() {
        // Update balance after a short delay to ensure all elements are loaded
        setTimeout(function() {
            updateBalance();
        }, 1000);
    });
</script>
</body>

</html>




