<?php

/**
 * Top navbar visibility for tbl_users with permission rows: align with permission-management.php
 * (module "Menu access" + page "View"). Other login types: all links visible (existing behavior).
 */
require_once __DIR__ . '/permission_helpers.php';
require_once __DIR__ . '/sidebar_permission_tree_data.php';

/**
 * @return array<string, array{0:string,1:string}> basename => [ moduleKey, pageKey ]
 */
function auragold_nav_basename_permission_map()
{
    static $map = null;
    if ($map !== null) {
        return $map;
    }
    $map = [
        'dashboard-retailer.php'        => ['dashboards', 'retailer'],
        'dashboard-wholesaler.php'      => ['dashboards', 'wholesaler'],
        'dashboard-manufacturing.php'   => ['dashboards', 'manufacturing'],
        'dashboard-sales-person.php'    => ['dashboards', 'sales_person'],
        'dashboard.php'                 => ['dashboards', 'gold_rates'],
        'dashboard-stock.php'           => ['dashboards', 'stock_dash'],
        'product-opening.php'           => ['utilities', 'product_opening'],
        'assign-inventory-to-sales-team.php' => ['employee_management', 'assign_inventory_sales_team'],
        'assign-inventory.php'              => ['employee_management', 'assign_inventory'],
        'unassign-inventory.php'            => ['employee_management', 'unassign_inventory'],
        'assign-inventory-items.php'    => ['employee_management', 'assign_inventory_items'],
        'account-ledger.php'            => ['utilities', 'account_ledger'],
        'customer-details-import.php'   => ['utilities', 'customer_details_import'],
        'metal-to-amount.php'            => ['utilities', 'metal_to_amount'],
        'amount-to-metal.php'           => ['utilities', 'amount_to_metal'],
        'bank-reconciliation.php'       => ['utilities', 'bank_reconciliation'],
        'ewaybill-api-settings.php'     => ['utilities', 'ewaybill_api_settings'],
        'ewaybill-authentication.php'   => ['utilities', 'ewaybill_authentication'],
        'sale-invoice.php'              => ['transaction', 'sale_invoice'],
        'draft-sale-invoice.php'        => ['transaction', 'sale_invoice'],
        'pos-sale-invoice.php'          => ['transaction', 'sale_invoice'],
        'sale-quotations.php'           => ['transaction', 'sale_quotation'],
        'sale-return.php'               => ['transaction', 'sale_return'],
        'purchase-invoice.php'          => ['transaction', 'purchase_invoice'],
        'purchase-quotation.php'        => ['transaction', 'purchase_quotation'],
        'purchase-return.php'           => ['transaction', 'purchase_return'],
        'payment-voucher.php'           => ['transaction', 'payment_voucher'],
        'receipt-voucher.php'           => ['transaction', 'receipt_voucher'],
        'advance-payment.php'           => ['transaction', 'advance_payment'],
        'cheque-entry.php'              => ['transaction', 'cheque_entry'],
        'material-issue.php'            => ['transaction', 'material_issue'],
        'material-receive.php'          => ['transaction', 'material_receive'],
        'old-jewelry-scrap-invoice.php' => ['transaction', 'old_jewelry_scrap_invoice'],
        'old-jewellery.php'             => ['transaction', 'old_jewellery_scrap'],
        'stock-journal.php'             => ['transaction', 'stock_journal'],
        'credit-note.php'               => ['transaction', 'credit_note'],
        'debit-note.php'                => ['transaction', 'debit_note'],
        'contra-voucher.php'            => ['transaction', 'contra_voucher'],
        'journal-voucher.php'         => ['transaction', 'journal_voucher'],
        'expense-invoice.php'         => ['transaction', 'expense_invoice'],
        'repair-invoice.php'            => ['transaction', 'repair_invoice'],
        'investment-fund.php'           => ['transaction', 'investment_fund'],
        'installment-report.php'        => ['transaction', 'installment_report'],
        'consignment-in.php'            => ['inventory', 'memo_consignment_in'],
        'consignment-out.php'           => ['inventory', 'memo_consignment_out'],
        'consignment-out-report.php'    => ['inventory', 'memo_consignment_items'],
        'stock-history.php'             => ['inventory', 'stock_history'],
        'stock-transfer.php'            => ['inventory', 'stock_transfer'],
        'stock-transfer-history.php'    => ['inventory', 'stock_transfer_history'],
        'stock-receive-history.php'     => ['inventory', 'stock_receive_history'],
        'gold-and-silver.php'           => ['inventory', 'gold_silver'],
        'barcode-management.php'        => ['inventory', 'barcode'],
        'gold-silver-analysis.php'      => ['inventory', 'gold_silver_analysis'],
        'platinum-analysis.php'         => ['inventory', 'platinum_analysis'],
        'platinum-stock.php'            => ['inventory', 'platinum_analysis'],
        'diamond-stone-analysis.php'    => ['inventory', 'diamond_stone_analysis'],
        'diamond-stock.php'             => ['inventory', 'diamond_stone_analysis'],
        'jewelry-catalogue.php'         => ['inventory', 'jewellery_catalogue'],
        'jewelry-catalogue-create.php'  => ['inventory', 'jewellery_catalogue'],
        'stock-verification.php'        => ['inventory', 'physical_stock'],
        'imitation-and-watches.php'     => ['inventory', 'imitation_watches'],
        'imitation-analysis.php'        => ['inventory', 'imitation_watches_analysis'],
        'rfid-barcode-scan.php'         => ['inventory', 'rfid_barcode_scan'],
        'sale-order.php'                => ['orders', 'sale_order'],
        'repair-order.php'              => ['orders', 'repair_order'],
        'sale-order-process.php'        => ['orders', 'sale_repair_order_process'],
        'jobwork-order.php'             => ['orders', 'jobwork_order_manufacturing'],
        'manufacturing-outsource.php'       => ['orders', 'jobwork_order_outsource'],
        'job-work-order-manufacturing.php' => ['orders', 'jobwork_order_outsource'],
        'department.php'                => ['manufacturer', 'department'],
        'department-report.php'         => ['manufacturer', 'department_report'],
        'outsource-department-report.php' => ['manufacturer', 'outsource_department_report'],
        'jobwork-queue.php'             => ['manufacturer', 'jobwork_queue'],
        'manufacturing-process.php'     => ['manufacturer', 'manufacturing_process'],
        'closing-report.php'            => ['manufacturer', 'closing_report'],
        'loss-tracking.php'             => ['manufacturer', 'loss_tracking'],
        'job-card-print.php'            => ['manufacturer', 'jobcard_print'],
        'trial-balance.php'             => ['financial_statement', 'trial_balance'],
        'balance-sheet.php'             => ['financial_statement', 'balance_sheet'],
        'profit-loss.php'               => ['financial_statement', 'profit_loss'],
        'cash-flow.php'                 => ['financial_statement', 'cash_flow'],
        'fund-flow.php'                 => ['financial_statement', 'fund_flow'],
        'chart-of-account.php'          => ['financial_statement', 'chart_of_account'],
        'tax-return.php'                => ['financial_statement', 'tax_return'],
        'sale-analysis.php'             => ['financial_statement', 'sale_analysis'],
        'gold-silver-financial-analysis.php' => ['financial_statement', 'sale_analysis'],
        'diamond-stone-financial-analysis.php' => ['financial_statement', 'sale_analysis'],
        'imitation-watches-financial-analysis.php' => ['financial_statement', 'sale_analysis'],
        'salesperson-performance.php'   => ['financial_statement', 'sale_analysis'],
        'vendor-report.php'             => ['financial_statement', 'sale_analysis'],
        'purchase-financial-analysis.php' => ['financial_statement', 'purchase_financial_analysis'],
        'trial-balance-detailed-report.php' => ['financial_statement', 'trial_balance_detailed_report'],
        'transaction-report.php'        => ['report', 'transaction_report'],
        'accountledger-report.php'      => ['report', 'account_ledger_report'],
        'day-report.php'                => ['report', 'day_report'],
        'ageing-report.php'             => ['report', 'ageing_report'],
        'ledger-balance-report.php'      => ['report', 'ledger_balance_report'],
        'Ledger-Balance-Report.php'     => ['report', 'ledger_balance_report'],
        'kyc-report.php'                => ['report', 'customer_kyc_report'],
        'kyc-form-pdf.php'               => ['report', 'customer_kyc_report'],
        'reward-point-report.php'       => ['report', 'reward_point_report'],
        'barcode-scan-report.php'       => ['report', 'barcode_scan_report'],
        'gst-reports.php'               => ['gst_report', 'gst_reports_hub'],
        'gst-report.php'                => ['gst_report', 'gst_reports_hub'],
        'employee-dashboard.php'        => ['employee_management', 'employee_dashboard'],
        'employee-attendance.php'       => ['employee_management', 'employee_attendance'],
        'employee-attendance-report.php'=> ['employee_management', 'employee_reports'],
        'employee-advance.php'          => ['employee_management', 'employee_advance'],
        'employee-advance-request.php'  => ['employee_management', 'employee_advance_request'],
        'employee-incentive.php'        => ['employee_management', 'employee_incentive'],
        'expense-category.php'          => ['employee_management', 'expense_category'],
        'employee-expenses.php'         => ['employee_management', 'expenses'],
        'employee-reports.php'          => ['employee_management', 'employee_reports'],
        'employee-salary-payroll.php'   => ['employee_management', 'employee_salary'],
        // Legacy pages (kept accessible if bookmarked)
        'employee-tracking.php'         => ['employee_management', 'employee_dashboard'],
        'employee-documents.php'        => ['employee_management', 'employee_documents'],
        'employee-leave-management.php' => ['employee_management', 'leave_management'],
        'employee-tasks.php'            => ['employee_management', 'employee_tasks'],
        'employee-performance.php'      => ['employee_management', 'employee_performance'],
        'employee-settings.php'         => ['employee_management', 'employee_settings'],
        'set-software.php'              => ['settings', 'set_software'],
        'barcode-prefix-settings.php'   => ['settings', 'set_software'],
        'language-settings.php'         => ['settings', 'set_software'],
        'mail-settings.php'             => ['settings', 'set_software'],
        'mobile-menu-settings.php'      => ['settings', 'set_software'],
        'voucher-type.php'              => ['settings', 'voucher_type'],
        'invoice-print-settings.php'    => ['settings', 'invoice_print_settings'],
        'reward-point-coupons-referral.php' => ['settings', 'set_software'],
        'user-management.php'           => ['administration', 'user_management'],
        'department-management.php'     => ['administration', 'masters'],
        'designation-management.php'    => ['administration', 'masters'],
        'activity-log.php'              => ['administration', 'activity_log'],
        'masters.php'                   => ['administration', 'masters'],
        'role-management.php'           => ['administration', 'role_management'],
        'permission-management.php'     => ['administration', 'permission_management'],
        'whitelist-management.php'      => ['administration', 'whitelist_management'],
        'blocklist-management.php'      => ['administration', 'blocklist_management'],
        'crm.php'                       => ['administration', 'crm'],
    ];

    return $map;
}

/**
 * @return array<string, array<string, mixed>>
 */
function auragold_nav_page_index()
{
    static $idx = null;
    if ($idx !== null) {
        return $idx;
    }
    $idx = [];
    foreach (auragold_sidebar_permission_tree_data() as $mod) {
        $mk = (string) ($mod['key'] ?? '');
        if ($mk === '') {
            continue;
        }
        foreach ($mod['pages'] as $p) {
            $pk = (string) ($p['key'] ?? '');
            if ($pk === '') {
                continue;
            }
            $idx[$mk . '.' . $pk] = $p;
        }
    }
    return $idx;
}

/**
 * Precompute module/page visibility once per request (avoids ~144 linear tree scans).
 *
 * @return array{modules: array<string, bool>, pages: array<string, bool>}
 */
function auragold_nav_visibility_cache()
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = ['modules' => [], 'pages' => []];
    foreach (auragold_sidebar_permission_tree_data() as $mod) {
        $mk = (string) ($mod['key'] ?? '');
        if ($mk === '') {
            continue;
        }
        $any = false;
        foreach ($mod['pages'] as $p) {
            $pk = (string) ($p['key'] ?? '');
            if ($pk === '') {
                continue;
            }
            $visible = auragold_nav_can_view_page($mk, $p);
            $cache['pages'][$mk . '.' . $pk] = $visible;
            if ($visible) {
                $any = true;
            }
        }
        $cache['modules'][$mk] = $any;
    }
    return $cache;
}

/**
 * @return array<string, array<string, mixed>>|null
 */
function auragold_nav_find_page_row($moduleKey, $pageKey)
{
    $idx = auragold_nav_page_index();
    $k   = (string) $moduleKey . '.' . (string) $pageKey;
    return $idx[$k] ?? null;
}

/**
 * Menu access + page view (and grant_namespace for the view key).
 */
function auragold_nav_can_view_page($moduleKey, array $pageRow)
{
    if (!auragold_user_can($moduleKey . '.menu')) {
        return false;
    }
    $ns = !empty($pageRow['grant_namespace']) ? (string) $pageRow['grant_namespace'] : $moduleKey;

    return auragold_user_can($ns . '.' . $pageRow['key'] . '.view');
}

function auragold_nav_can_page_keys($moduleKey, $pageKey)
{
    $cache = auragold_nav_visibility_cache();
    $k     = (string) $moduleKey . '.' . (string) $pageKey;
    if (array_key_exists($k, $cache['pages'])) {
        return (bool) $cache['pages'][$k];
    }
    $row = auragold_nav_find_page_row($moduleKey, $pageKey);
    if ($row === null) {
        return true;
    }

    return auragold_nav_can_view_page($moduleKey, $row);
}

/**
 * Whether a top navbar file link should render (basename from href).
 */
function auragold_nav_show_php_href($href)
{
    $path = parse_url(trim((string) $href), PHP_URL_PATH);
    $bn   = $path ? basename($path) : basename((string) $href);
    if ($bn === '' || $bn === '#') {
        return true;
    }
    $map = auragold_nav_basename_permission_map();
    if (!isset($map[$bn])) {
        return true;
    }
    $pair = $map[$bn];
    $cache = auragold_nav_visibility_cache();
    $k     = $pair[0] . '.' . $pair[1];
    if (array_key_exists($k, $cache['pages']) && $cache['pages'][$k]) {
        return true;
    }
    if (array_key_exists($k, $cache['pages']) && !$cache['pages'][$k]) {
        // fall through to legacy alias checks below
    } else {
        $row = auragold_nav_find_page_row($pair[0], $pair[1]);
        if ($row === null) {
            return true;
        }
        if (auragold_nav_can_view_page($pair[0], $row)) {
            return true;
        }
    }
    // Outsource Department Report: honor existing Insource Department Report grants.
    if ($bn === 'outsource-department-report.php' && auragold_nav_can_page_keys('manufacturer', 'department_report')) {
        return true;
    }
    // Inventory assign pages moved Opening → Employee Management; honor legacy utilities grants.
    $legacyUtil = [
        'assign-inventory-to-sales-team.php' => 'assign_inventory_sales_team',
        'assign-inventory.php' => 'assign_inventory',
        'unassign-inventory.php' => 'unassign_inventory',
        'assign-inventory-items.php' => 'assign_inventory_items',
    ];
    if (isset($legacyUtil[$bn]) && auragold_nav_can_page_keys('utilities', $legacyUtil[$bn])) {
        return true;
    }
    // Expense pages added after many roles were saved — inherit EM menu when grants omit them.
    if (($bn === 'employee-expenses.php' || $bn === 'expense-category.php')) {
        require_once __DIR__ . '/auragold_employee_management_menu.php';
        $emKey = ($bn === 'expense-category.php') ? 'expense_category' : 'expenses';
        if (auragold_em_expense_menu_fallback_allowed($emKey)) {
            return true;
        }
    }

    return false;
}

/**
 * True if at least one page in the module passes menu + view checks.
 */
function auragold_nav_module_has_visible_link($moduleKey)
{
    $cache = auragold_nav_visibility_cache();
    $mk    = (string) $moduleKey;
    if (array_key_exists($mk, $cache['modules'])) {
        return (bool) $cache['modules'][$mk];
    }

    return true;
}

/**
 * Administration dropdown: combine admin-only links with shared links.
 */
function auragold_nav_administration_dropdown_visible($isAdminRole)
{
    if (auragold_nav_can_page_keys('administration', 'activity_log')) {
        return true;
    }
    if (auragold_nav_can_page_keys('administration', 'crm')) {
        return true;
    }
    if ($isAdminRole) {
        $adminPages = [
            'user_management',
            'masters',
            'role_management',
            'permission_management',
            'whitelist_management',
            'blocklist_management',
        ];
        foreach ($adminPages as $pk) {
            if (auragold_nav_can_page_keys('administration', $pk)) {
                return true;
            }
        }
    }

    return false;
}

/**
 * True when the top navbar would render zero items (tbl_users + branch-scoped grants that deny all modules).
 * Used for minimal nav fallback and access control.
 */
function auragold_nav_sidebar_entirely_hidden_for_session()
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    $src = isset($_SESSION['login_source']) ? (string) $_SESSION['login_source'] : '';
    if ($src !== 'user' || (int) ($_SESSION['user_id'] ?? 0) <= 0) {
        $cached = false;

        return false;
    }
    $modules = [
        'dashboards',
        'utilities',
        'transaction',
        'inventory',
        'orders',
        'manufacturer',
        'financial_statement',
        'report',
        'settings',
    ];
    foreach ($modules as $m) {
        if (auragold_nav_module_has_visible_link($m)) {
            $cached = false;

            return false;
        }
    }
    $cached = true;

    return true;
}
